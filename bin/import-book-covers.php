<?php
declare(strict_types=1);

/**
 * Gives books a cover image taken from the Blogger Takeout, when a file with a clear cover name exists there.
 *   php bin/import-book-covers.php storage/import/takeout.zip
 * Only books without a cover are touched. The larger of two same-named files is used.
 * Books with no matching file (for example Life On My Knees) get their cover from the CMS: Books > the book > Cover image.
 */
ini_set('memory_limit', '512M');
require dirname(__DIR__) . '/vendor/autoload.php';

use App\Media\Images;
use App\Support\Db;

$zipFile = $argv[1] ?? '';
if ($zipFile === '' || !is_readable($zipFile)) {
    fwrite(STDERR, "Usage: php bin/import-book-covers.php path/to/takeout.zip\n");
    exit(1);
}
/** book slug => the Takeout file-name stem (case ignored; "(1)" copies and extension ignored) */
const COVERS = ['freedom-is-slavery' => 'freedom_is_slavery_cover'];

$zip = new ZipArchive();
if ($zip->open($zipFile) !== true) {
    fwrite(STDERR, "Cannot open the zip.\n");
    exit(1);
}
$pdo = Db::pdo();
foreach (COVERS as $slug => $stem) {
    $book = $pdo->prepare('SELECT id, cover_image FROM books WHERE slug = ?');
    $book->execute([$slug]);
    $row = $book->fetch();
    if (!$row) { echo "$slug: no such book yet\n"; continue; }
    if (!empty($row['cover_image'])) { echo "$slug: already has a cover, left alone\n"; continue; }
    $best = null;
    $bestSize = 0;
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $st = $zip->statIndex($i);
        $n = mb_strtolower(basename($st['name']));
        if (str_ends_with($n, '.json') || !preg_match('/^' . preg_quote($stem, '/') . '(\(\d+\))?\.(jpe?g|png|webp|gif)$/', $n)) { continue; }
        if ($st['size'] > $bestSize) { $best = $st['name']; $bestSize = $st['size']; }
    }
    if ($best === null) { echo "$slug: no cover file named $stem in the Takeout\n"; continue; }
    $tmp = tempnam(sys_get_temp_dir(), 'cov');
    file_put_contents($tmp, $zip->getFromName($best));
    try {
        $img = Images::store($tmp, 'books');
    } finally {
        @unlink($tmp);
    }
    $pdo->prepare('UPDATE books SET cover_image = ?, updated_at = ? WHERE id = ?')->execute([$img['file'], Db::utcNow(), $row['id']]);
    printf("%s: cover set from %s (%dx%d)\n", $slug, basename($best), $img['width'], $img['height']);
}
