<?php
declare(strict_types=1);

/**
 * Gives gallery pictures a descriptive title and caption in place of a generic one.
 *   php bin/relabel-gallery.php --preview    (counts only; writes nothing)
 *   php bin/relabel-gallery.php              (does it)
 * Reads config/gallery-captions.tsv: picture fingerprint, new label. Pictures are found by fingerprint, as in
 * apply-alt-text.php. Only pictures whose caption is still a generic "... Dump" post title (Photo Dump, Picture Dump, NYE Picture Dump) are changed, so a label
 * written by hand is never overwritten. Safe to run again.
 */
set_time_limit(0);
require dirname(__DIR__) . '/vendor/autoload.php';

use App\Media\Dupes;
use App\Support\Db;
use App\Support\Env;

$preview = in_array('--preview', $argv, true);
$pdo = Db::pdo();
$rows = $pdo->query("SELECT id, dhash, title, caption FROM gallery_items WHERE dhash IS NOT NULL AND LOWER(caption) LIKE '%dump%'")->fetchAll();
$set = $pdo->prepare('UPDATE gallery_items SET title = ?, caption = ? WHERE id = ?');

$lines = $changed = $unmatched = 0;
foreach (file(Env::root() . '/config/gallery-captions.tsv', FILE_IGNORE_NEW_LINES) ?: [] as $line) {
    if ($line === '' || $line[0] === '#') {
        continue;
    }
    [$hash, $label] = array_pad(explode("\t", $line), 2, '');
    if (!preg_match('/^[0-9a-f]{16}$/', $hash) || trim($label) === '') {
        continue;
    }
    $lines++;
    $hits = array_values(array_filter($rows, fn($r) => Dupes::distance($hash, (string) $r['dhash']) <= 2));
    if (!$hits) {
        $unmatched++;
        continue;
    }
    foreach ($hits as $r) {
        if (!$preview) {
            $set->execute([trim($label), trim($label), $r['id']]);
        }
        $changed++;
    }
}
$left = (int) $pdo->query("SELECT COUNT(*) FROM gallery_items WHERE LOWER(caption) LIKE '%dump%' OR LOWER(title) LIKE '%dump%'")->fetchColumn();
printf("Labels in the file: %d\nPictures %s: %d\nLabels with no matching picture: %d\nPictures still labelled as a Dump: %d\n", $lines, $preview ? 'that would change' : 'changed', $changed, $unmatched, $left);

// Second pass: pictures that still carry a vague post title (config/gallery-vague-captions.txt) get a short description
// built from their own alt text. Pictures with no alt text are left as they are.
function shortLabel(string $alt): string
{
    $t = rtrim(trim($alt), '.');
    if (mb_strlen($t) <= 80) {
        return $t;
    }
    $cut = mb_substr($t, 0, 80);
    foreach ([', ', ' and ', ' with ', ' in ', ' on ', ' by ', ' against '] as $sep) {
        $p = mb_strrpos($cut, $sep);
        if ($p !== false && $p >= 30) {
            return mb_substr($cut, 0, $p);
        }
    }
    return rtrim(mb_substr($cut, 0, (int) mb_strrpos($cut, ' ')), ',; ');
}

$vagueFile = Env::root() . '/config/gallery-vague-captions.txt';
$vague = [];
foreach (is_file($vagueFile) ? file($vagueFile, FILE_IGNORE_NEW_LINES) : [] as $line) {
    if (trim($line) !== '' && $line[0] !== '#') {
        $vague[mb_strtolower(trim($line))] = true;
    }
}
$vagueChanged = $vagueSkipped = 0;
$all = $pdo->query("SELECT id, caption, alt_text FROM gallery_items WHERE alt_text <> ''")->fetchAll();
foreach ($all as $r) {
    if (!isset($vague[mb_strtolower(trim((string) $r['caption']))])) {
        continue;
    }
    $label = shortLabel((string) $r['alt_text']);
    if ($label === '') {
        $vagueSkipped++;
        continue;
    }
    if (!$preview) {
        $set->execute([$label, $label, $r['id']]);
    }
    $vagueChanged++;
}
printf("Vague titles replaced by a description: %s %d\n", $preview ? 'would change' : 'changed', $vagueChanged);
