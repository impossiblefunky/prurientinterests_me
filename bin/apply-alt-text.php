<?php
declare(strict_types=1);

/**
 * Writes reviewed alt text and theme tags onto gallery pictures.
 *   php bin/apply-alt-text.php --preview    (says how many gallery rows each picture matches; writes nothing)
 *   php bin/apply-alt-text.php              (does it)
 * Reads config/alt-text/*.tsv: picture fingerprint, alt text, tags. Pictures are found by fingerprint (the same
 * look-alike check the duplicate finder uses), never by file name. Alt text typed by hand is never overwritten:
 * only an empty alt, or one that is just the post title or caption, is replaced. Safe to run again.
 * Prints counts only, never the descriptions.
 */
ini_set('memory_limit', '512M');
set_time_limit(0);
require dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/src/Web/helpers.php';

use App\Media\Dupes;
use App\Support\Db;
use App\Support\Env;

$preview = in_array('--preview', $argv, true);
$pdo = Db::pdo();

while (Dupes::fill($pdo, 300) > 0) {
    // fingerprint every picture that does not have one yet
}
$rows = $pdo->query("SELECT id, dhash, title, caption, alt_text FROM gallery_items WHERE dhash IS NOT NULL AND dhash <> 'unreadable0000000'")->fetchAll();

$findTag = $pdo->prepare('SELECT id FROM tags WHERE slug = ?');
$newTag = $pdo->prepare('INSERT INTO tags (slug, name, created_at) VALUES (?,?,?)');
$link = $pdo->prepare("INSERT IGNORE INTO item_tags (tag_id, item_type, item_id) VALUES (?, 'gallery', ?)");
$setAlt = $pdo->prepare('UPDATE gallery_items SET alt_text = ? WHERE id = ?');
$tagId = function (string $name) use ($findTag, $newTag, $pdo): int {
    $slug = slugify($name);
    $findTag->execute([$slug]);
    $id = $findTag->fetchColumn();
    if (!$id) {
        $newTag->execute([$slug, $name, Db::utcNow()]);
        $id = $pdo->lastInsertId();
    }
    return (int) $id;
};

// Tags listed in config/alt-text/retired-tags.txt are taken off a picture when its line no longer lists them, so a tag
// can be split into finer ones. Only pictures that have a line in the files are touched.
$retired = [];
$retiredFile = Env::root() . '/config/alt-text/retired-tags.txt';
if (is_file($retiredFile)) {
    $retired = array_filter(array_map('trim', file($retiredFile, FILE_IGNORE_NEW_LINES)));
}
$unlink = $pdo->prepare("DELETE FROM item_tags WHERE tag_id = ? AND item_type = 'gallery' AND item_id = ?");
$tagsRemoved = 0;

$pictures = $matched = $altSet = $altKept = $tagLinks = $unmatched = $crowded = 0;
foreach (glob(Env::root() . '/config/alt-text/*.tsv') ?: [] as $file) {
    foreach (file($file, FILE_IGNORE_NEW_LINES) as $line) {
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        [$hash, $alt, $tags] = array_pad(explode("\t", $line), 3, '');
        if (!preg_match('/^[0-9a-f]{16}$/', $hash) || trim($alt) === '') {
            continue;
        }
        $pictures++;
        $ones = substr_count(decbin(hexdec(substr($hash, 0, 8))) . decbin(hexdec(substr($hash, 8, 8))), '1');
        if ($ones < 14 || $ones > 50) {
            $crowded++;   // a very plain picture has a fingerprint too generic to trust; a person should do these
            continue;
        }
        $hits = array_values(array_filter($rows, fn($r) => Dupes::distance($hash, (string) $r['dhash']) <= 2));
        if (!$hits) {
            $unmatched++;
            continue;
        }
        if (count($hits) > 8) {
            $crowded++;   // too many look-alikes to be sure
            continue;
        }
        $matched++;
        if ($preview) {
            continue;
        }
        foreach ($hits as $r) {
            $cur = trim((string) $r['alt_text']);
            if ($cur === '' || $cur === trim((string) $r['title']) || $cur === trim((string) $r['caption'])) {
                $setAlt->execute([trim($alt), $r['id']]);
                $altSet++;
            } else {
                $altKept++;
            }
            $wanted = array_filter(array_map('trim', explode(',', $tags)));
            foreach ($retired as $old) {
                if (!in_array($old, $wanted, true)) {
                    $findTag->execute([slugify($old)]);
                    if ($oldId = $findTag->fetchColumn()) {
                        $unlink->execute([$oldId, $r['id']]);
                        $tagsRemoved += $unlink->rowCount();
                    }
                }
            }
            foreach ($wanted as $name) {
                $link->execute([$tagId($name), $r['id']]);
                $tagLinks += $link->rowCount();
            }
        }
    }
}
printf("Pictures in the files: %d\nFound on the site: %d\nNot found: %d\nSkipped (too plain or too many look-alikes): %d\n", $pictures, $matched, $unmatched, $crowded);
if ($preview) {
    echo "Preview only: nothing was written.\n";
} else {
    printf("Alt text written: %d (kept hand-written alt text: %d)\nNew tag links: %d\nOld tag links removed: %d\n", $altSet, $altKept, $tagLinks, $tagsRemoved);
}
