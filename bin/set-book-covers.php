<?php
declare(strict_types=1);

/**
 * Sets book covers from the images kept in database/seed/covers/<book-slug>.jpg (the front panels of the Lulu cover PDFs).
 * Replaces a cover only when the file here is newer or the book has none; old cover files are removed.
 *   php bin/set-book-covers.php
 */
ini_set('memory_limit', '512M');
require dirname(__DIR__) . '/vendor/autoload.php';

use App\Media\Images;
use App\Support\Db;
use App\Support\Env;

$pdo = Db::pdo();
foreach (glob(Env::root() . '/database/seed/covers/*.jpg') ?: [] as $file) {
    $slug = basename($file, '.jpg');
    $st = $pdo->prepare('SELECT id, cover_image FROM books WHERE slug = ?');
    $st->execute([$slug]);
    $book = $st->fetch();
    if (!$book) { echo "$slug: no such book, skipped\n"; continue; }
    $tag = hash_file('sha1', $file);
    $mark = Env::root() . '/storage/cache/cover-' . $slug . '.sha1';
    if (!empty($book['cover_image']) && is_file($mark) && trim((string) file_get_contents($mark)) === $tag) { echo "$slug: cover already up to date\n"; continue; }
    $img = Images::store($file, 'books');
    $pdo->prepare('UPDATE books SET cover_image = ?, updated_at = ? WHERE id = ?')->execute([$img['file'], Db::utcNow(), $book['id']]);
    if (!empty($book['cover_image'])) { Images::delete($book['cover_image']); }
    @file_put_contents($mark, $tag);
    printf("%s: cover set (%dx%d)\n", $slug, $img['width'], $img['height']);
}
