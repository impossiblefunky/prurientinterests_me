<?php
declare(strict_types=1);

/**
 * Turns every image from the image-only blog posts into one big, public photo gallery:
 * caption = the post's title, no per-year albums, all published, newest post first.
 * The posts themselves stay where they are (old addresses keep working). Safe to run again.
 *   php bin/rebuild-gallery.php            (does it)
 *   php bin/rebuild-gallery.php --preview  (counts only)
 */
require dirname(__DIR__) . '/vendor/autoload.php';

use App\Support\Db;

$preview = in_array('--preview', $argv, true);
$pdo = Db::pdo();
$one = fn(string $sql) => (int) $pdo->query($sql)->fetchColumn();
$items = $one('SELECT COUNT(*) FROM gallery_items WHERE story_id IS NOT NULL');
$needCaption = $one("SELECT COUNT(*) FROM gallery_items WHERE story_id IS NOT NULL AND caption = '' AND title NOT IN ('Untitled', 'Untitled image')");
$notPublished = $one("SELECT COUNT(*) FROM gallery_items WHERE story_id IS NOT NULL AND status <> 'published'");
$inAlbums = $one('SELECT COUNT(*) FROM gallery_items WHERE story_id IS NOT NULL AND album_id IS NOT NULL');
printf("Images from blog posts: %d; need a caption: %d; not yet public: %d; sitting in year albums: %d\n", $items, $needCaption, $notPublished, $inAlbums);
if ($preview) { echo "Preview only: nothing changed.\n"; exit(0); }
$pdo->exec("UPDATE gallery_items SET caption = title WHERE story_id IS NOT NULL AND caption = '' AND title NOT IN ('Untitled', 'Untitled image')");
$pdo->exec("UPDATE gallery_items SET alt_text = IF(title IN ('Untitled', 'Untitled image'), 'Picture', title) WHERE story_id IS NOT NULL AND alt_text = ''");
$pdo->exec("UPDATE gallery_items SET album_id = NULL, status = 'published', sort_order = 0 WHERE story_id IS NOT NULL");
$removed = $pdo->exec("DELETE FROM albums WHERE slug LIKE 'pictures-%' AND NOT EXISTS (SELECT 1 FROM gallery_items g WHERE g.album_id = albums.id)");
printf("Done. %d images are public in the one big gallery; %d empty year albums removed.\n", $items, $removed);
