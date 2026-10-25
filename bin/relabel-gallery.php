<?php
declare(strict_types=1);

/**
 * Gives gallery pictures a descriptive title and caption in place of a generic one.
 *   php bin/relabel-gallery.php --preview    (counts only; writes nothing)
 *   php bin/relabel-gallery.php              (does it)
 * Reads config/gallery-captions.tsv: picture fingerprint, new label. Pictures are found by fingerprint, as in
 * apply-alt-text.php. Only pictures whose caption is still the generic "Photo Dump" are changed, so a label
 * written by hand is never overwritten. Safe to run again.
 */
set_time_limit(0);
require dirname(__DIR__) . '/vendor/autoload.php';

use App\Media\Dupes;
use App\Support\Db;
use App\Support\Env;

$preview = in_array('--preview', $argv, true);
$pdo = Db::pdo();
$rows = $pdo->query("SELECT id, dhash, title, caption FROM gallery_items WHERE dhash IS NOT NULL AND LOWER(TRIM(caption)) = 'photo dump'")->fetchAll();
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
$left = (int) $pdo->query("SELECT COUNT(*) FROM gallery_items WHERE LOWER(TRIM(caption)) = 'photo dump' OR LOWER(TRIM(title)) = 'photo dump'")->fetchColumn();
printf("Labels in the file: %d\nPictures %s: %d\nLabels with no matching picture: %d\nPictures still labelled Photo Dump: %d\n", $lines, $preview ? 'that would change' : 'changed', $changed, $unmatched, $left);
