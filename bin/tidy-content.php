<?php
declare(strict_types=1);

/**
 * One-time tidy-up approved by Mike (2026-10-20). Safe to run again.
 *  - Contact page: the "pornoperson at gmail dot com" line becomes a real mailto link.
 *  - Tags: proper capitalisation (eLust, FetLife, CineKink) and "BDSM terms" merged into "Terminology",
 *    which is renamed "BDSM terminology". Old tag addresses keep working (alias redirect).
 */
require dirname(__DIR__) . '/vendor/autoload.php';

use App\Support\Db;

$pdo = Db::pdo();

$n = $pdo->prepare("UPDATE pages SET body_html = REPLACE(body_html, 'Email me at pornoperson at gmail dot com', 'Email me at <a href=\"mailto:pornoperson@gmail.com\">pornoperson@gmail.com</a>'), updated_at = UTC_TIMESTAMP() WHERE slug = 'contact' AND body_html LIKE '%pornoperson at gmail dot com%'");
$n->execute();
echo "Contact page updated: " . $n->rowCount() . "\n";

$rename = $pdo->prepare('UPDATE tags SET name = ? WHERE slug = ? AND BINARY name <> ?');
foreach (['elust' => 'eLust', 'fetlife' => 'FetLife', 'cinekink' => 'CineKink'] as $slug => $name) {
    $rename->execute([$name, $slug, $name]);
    echo "Tag $slug -> $name: " . $rename->rowCount() . "\n";
}

$id = fn(string $slug) => (int) $pdo->query('SELECT id FROM tags WHERE slug = ' . $pdo->quote($slug))->fetchColumn();
$from = $id('bdsm-terms');
$to = $id('terminology');
if ($from && $to) {
    $pdo->prepare('INSERT IGNORE INTO item_tags (tag_id, item_type, item_id) SELECT ?, item_type, item_id FROM item_tags WHERE tag_id = ?')->execute([$to, $from]);
    $pdo->prepare('DELETE FROM item_tags WHERE tag_id = ?')->execute([$from]);
    $pdo->prepare('INSERT IGNORE INTO tag_aliases (alias_slug, tag_id) VALUES (?, ?)')->execute(['bdsm-terms', $to]);
    $pdo->prepare('DELETE FROM tags WHERE id = ?')->execute([$from]);
    echo "Merged 'BDSM terms' into Terminology.\n";
}
if ($to) {
    $pdo->prepare("UPDATE tags SET name = 'BDSM terminology' WHERE id = ? AND name <> 'BDSM terminology'")->execute([$to]);
}

// Second round (Mike, 2026-10-20): "Golden" is Mistress Golden; label-type tags are hidden from the public Interests list;
// the very small tags (Kitty, Desires, Karma, Bully) are dropped. Stories keep everything else; old tag addresses go to /tags.
$pdo->prepare("UPDATE tags SET name = 'Mistress Golden' WHERE slug = 'golden' AND BINARY name <> 'Mistress Golden'")->execute();
foreach (['life', 'site', 'news', 'media', 'book', 'review'] as $slug) {
    $pdo->prepare('UPDATE tags SET hidden = 1 WHERE slug = ?')->execute([$slug]);
}
$redir = $pdo->prepare("INSERT IGNORE INTO redirects (from_path, to_url, code, created_at) VALUES (?, '/tags', 301, UTC_TIMESTAMP())");
foreach (['kitty', 'desires', 'karma', 'bully'] as $slug) {
    if ($pdo->prepare('DELETE FROM tags WHERE slug = ?')->execute([$slug])) {
        $redir->execute(['/tag/' . $slug]);
    }
}
echo "Tags tidied (renamed, hidden, dropped).\n";
// The Contact page no longer lists an email address (the form replaces it).
$row = $pdo->query("SELECT id, body_html FROM pages WHERE slug = 'contact'")->fetch();
$removed = 0;
if ($row && stripos($row['body_html'], 'Email me at') !== false) {
    $body = preg_replace('#<p>Email me at.*?</p>(\s*<br\s*/?>)?#is', '', $row['body_html']);
    $pdo->prepare('UPDATE pages SET body_html = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?')->execute([$body, $row['id']]);
    $removed = 1;
}
echo "Contact page email line removed: $removed\n";
// FetLife line becomes a real link (Mike's address).
$fl = $pdo->prepare("UPDATE pages SET body_html = REPLACE(body_html, 'Look for me on Fetlife.', 'Look for me on <a href=\"https://fetlife.com/pornoperson\">FetLife</a>.'), updated_at = UTC_TIMESTAMP() WHERE slug = 'contact' AND body_html LIKE '%Look for me on Fetlife.%'");
$fl->execute();
echo "FetLife link added: " . $fl->rowCount() . "\n";

// Book descriptions list the stories in each collection. Where a listed story (or series) is on this site, link to it here instead of to the old outside site.
$stories = [];
foreach ($pdo->query("SELECT title, slug, legacy_path FROM stories WHERE status = 'published' AND kind IN ('story','post') ORDER BY published_at")->fetchAll() as $r) {
    $stories[mb_strtolower(trim($r['title']))] = $r['legacy_path'] ?: '/story/' . $r['slug'];
}
foreach ($pdo->query('SELECT title, slug FROM series')->fetchAll() as $r) {
    $stories[mb_strtolower(trim($r['title']))] = '/series/' . $r['slug'];   // a series name wins over a single chapter of the same name
}
$swapped = 0;
$upd = $pdo->prepare('UPDATE books SET description_html = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?');
foreach ($pdo->query('SELECT id, description_html FROM books')->fetchAll() as $b) {
    $html = preg_replace_callback('#<a\b[^>]*\bhref="https?://[^"]+"[^>]*>(.*?)</a>#is', function (array $m) use ($stories, &$swapped): string {
        $key = mb_strtolower(trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        if (!isset($stories[$key])) {
            return $m[0];
        }
        $swapped++;
        return '<a href="' . $stories[$key] . '">' . $m[1] . '</a>';
    }, (string) $b['description_html']) ?? $b['description_html'];
    if ($html !== $b['description_html']) {
        $upd->execute([$html, $b['id']]);
    }
}
echo "Book story links moved to this site: $swapped\n";

// About page: drop the "I will be building this out" promise; link the two collections and mention the single stories.
$about = $pdo->query("SELECT id, body_html FROM pages WHERE slug = 'about-prurient-interests'")->fetch();
$aboutChanged = 0;
if ($about) {
    $b = $about['body_html'];
    $b = str_replace(' I will be building out this "About" page a bit more though there\'s not a whole lot to say about the site that isn\'t up front and in your face.', ' There\'s not a whole lot to say about the site that isn\'t up front and in your face.', $b);
    $b = str_replace('I will be building out this "About" page a bit more though there\'s not a whole lot to say about the site that isn\'t up front and in your face.', 'There\'s not a whole lot to say about the site that isn\'t up front and in your face.', $b);
    $b = str_replace('my two collections, Life on My Knees and Freedom is Slavery. I highly recommend you pick up either or both of these fine volumes.</p>', 'my two collections, <a href="/books/life-on-my-knees">Life on My Knees</a> and <a href="/books/freedom-is-slavery">Freedom is Slavery</a>. I highly recommend you pick up either or both of these fine volumes. I have also released single stories on Kindle; they are all on the <a href="/books">Books page</a>.</p>', $b);
    if ($b !== $about['body_html']) {
        $pdo->prepare('UPDATE pages SET body_html = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?')->execute([$b, $about['id']]);
        $aboutChanged = 1;
    }
}
echo "About page reworded: $aboutChanged\n";

// A link inside an old post points at /2010/04/satin-met-laddie.html; the story lives at /2012/03/.
$pdo->prepare('INSERT INTO redirects (from_path, to_url, code, created_at) VALUES (?,?,301,UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE to_url = VALUES(to_url)')
    ->execute(['/2010/04/satin-met-laddie.html', '/2012/03/satin-met-laddie.html']);
echo "Redirect for the old Satin Met a Laddie address: ok\n";

// Links to the old pornoperson.blogspot.com addresses become links to the same page on this site (the paths are identical);
// a link ending in .htm is fixed to .html when that is the real page.
$known = [];
foreach ($pdo->query('SELECT legacy_path FROM stories WHERE legacy_path IS NOT NULL UNION SELECT legacy_path FROM pages WHERE legacy_path IS NOT NULL')->fetchAll(PDO::FETCH_COLUMN) as $lp) {
    $known[$lp] = true;
}
$moved = 0;
foreach (['stories', 'pages'] as $table) {
    $upd = $pdo->prepare("UPDATE $table SET body_html = ? WHERE id = ?");
    foreach ($pdo->query("SELECT id, body_html FROM $table WHERE body_html LIKE '%blogspot.%'")->fetchAll() as $r) {
        $html = preg_replace_callback('#(href=")https?://(?:www\.)?pornoperson\.blogspot\.[a-z.]+(/[^"\s<>]*)(")#i', function (array $m) use ($known, &$moved): string {
            $path = preg_replace('/[?#].*$/', '', $m[2]);
            if (!isset($known[$path]) && isset($known[$path . 'l'])) {
                $path .= 'l';
            }
            if (!isset($known[$path])) {
                return $m[0];
            }
            $moved++;
            return $m[1] . $path . $m[3];
        }, $r['body_html']) ?? $r['body_html'];
        if ($html !== $r['body_html']) {
            $upd->execute([$html, $r['id']]);
        }
    }
}
echo "Old blogspot links moved to this site: $moved\n";

echo "Done.\n";
