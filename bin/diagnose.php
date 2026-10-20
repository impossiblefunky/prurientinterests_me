<?php
declare(strict_types=1);

/**
 * Read-only health report: what is in the database, what is on disk, and whether pictures can be fetched over the web.
 *   php bin/diagnose.php
 * Prints counts and file names only, never story text.
 */
require dirname(__DIR__) . '/vendor/autoload.php';

use App\Support\Db;
use App\Support\Env;

$pdo = Db::pdo();
$one = fn(string $sql) => $pdo->query($sql)->fetchColumn();
echo "== Database ==\n";
printf("stories live: %d, drafts: %d\n", $one('SELECT COUNT(*) FROM stories WHERE status="published"'), $one('SELECT COUNT(*) FROM stories WHERE status="draft"'));
printf("posts whose text points at a local picture: %d\n", $one('SELECT COUNT(*) FROM stories WHERE body_html LIKE "%/uploads/posts/%"'));
printf("posts still pointing at another website's picture: %d\n", $one('SELECT COUNT(*) FROM stories WHERE body_html REGEXP "<img[^>]*src=\"https?:"'));
printf("gallery items: %d (published %d, draft %d); albums: %d (published %d)\n", $one('SELECT COUNT(*) FROM gallery_items'), $one('SELECT COUNT(*) FROM gallery_items WHERE status="published"'), $one('SELECT COUNT(*) FROM gallery_items WHERE status="draft"'), $one('SELECT COUNT(*) FROM albums'), $one('SELECT COUNT(*) FROM albums WHERE status="published"'));
printf("books: %d, with a cover image: %d, editions: %d\n", $one('SELECT COUNT(*) FROM books'), $one('SELECT COUNT(*) FROM books WHERE cover_image IS NOT NULL AND cover_image <> ""'), $one('SELECT COUNT(*) FROM book_editions'));
printf("image_sources rows: %d\n", $one('SELECT COUNT(*) FROM image_sources'));

echo "\n== Files on the server ==\n";
$up = Env::root() . '/public/uploads';
printf("public/uploads exists: %s, writable: %s, permissions: %s\n", is_dir($up) ? 'yes' : 'NO', is_writable($up) ? 'yes' : 'no', is_dir($up) ? substr(sprintf('%o', fileperms($up)), -4) : '-');
$files = glob($up . '/posts/*') ?: [];
printf("files in public/uploads/posts: %d\n", count($files));
$size = 0;
foreach ($files as $f) { $size += (int) @filesize($f); }
printf("size: %.1f MB\n", $size / 1048576);
foreach (['public/uploads/.htaccess', 'public/.htaccess', 'public/index.php'] as $rel) {
    printf("%s: %s\n", $rel, is_file(Env::root() . '/' . $rel) ? 'present' : 'missing');
}
if ($files) {
    $sample = $files[0];
    printf("sample file: %s (%d bytes, permissions %s)\n", basename($sample), filesize($sample), substr(sprintf('%o', fileperms($sample)), -4));
    $url = rtrim((string) Env::get('APP_URL', ''), '/') . '/uploads/posts/' . basename($sample);
    $h = @get_headers($url);
    printf("fetching %s from the server itself: %s\n", $url, $h ? $h[0] : 'no response (' . (error_get_last()['message'] ?? 'unknown') . ')');
}

echo "\n== A sample picture post ==\n";
$row = $pdo->query('SELECT legacy_path, body_html FROM stories WHERE status="published" AND kind="picture" AND body_html LIKE "%/uploads/posts/%" ORDER BY id LIMIT 1 OFFSET 20')->fetch();
if ($row) {
    preg_match('/<img[^>]*src="([^"]+)"/', $row['body_html'], $m);
    printf("page: %s\nfirst picture address in its text: %s\nfile exists on disk: %s\n", $row['legacy_path'], $m[1] ?? '(none)', isset($m[1]) && is_file(Env::root() . '/public' . $m[1]) ? 'yes' : 'NO');
}
