<?php
declare(strict_types=1);

/**
 * Pulls the blog feed out of the Blogger Takeout zip: storage/import/takeout.zip => storage/import/feed.atom.
 * The zip is left as it is (the image import reads pictures straight from it).
 *   php bin/unpack-takeout.php
 */
require dirname(__DIR__) . '/vendor/autoload.php';

use App\Support\Env;

$dir = Env::root() . '/storage/import';
$zipFile = $dir . '/takeout.zip';
if (!is_readable($zipFile)) {
    fwrite(STDERR, "storage/import/takeout.zip is not on the server yet. Upload it first.\n");
    exit(1);
}
$zip = new ZipArchive();
if ($zip->open($zipFile) !== true) {
    fwrite(STDERR, "That file could not be opened as a zip. The upload may have been cut off; upload it again.\n");
    exit(1);
}
$feed = null;
for ($i = 0; $i < $zip->numFiles; $i++) {
    $n = (string) $zip->getNameIndex($i);
    if (preg_match('#^Takeout/Blogger/Blogs/[^/]+/feed\.atom$#', $n)) {
        $feed = $n;
        break;
    }
}
if ($feed === null) {
    fwrite(STDERR, "No Blogger feed.atom inside the zip. Is this the right Takeout file?\n");
    exit(1);
}
$in = $zip->getStream($feed);
$out = fopen($dir . '/feed.atom', 'wb');
$bytes = stream_copy_to_stream($in, $out);
fclose($in);
fclose($out);
chmod($dir . '/feed.atom', 0600);
$images = 0;
for ($i = 0; $i < $zip->numFiles; $i++) {
    if (str_contains((string) $zip->getNameIndex($i), '/Albums/') && !str_ends_with((string) $zip->getNameIndex($i), '.json')) {
        $images++;
    }
}
printf("Unpacked %s (%s MB) to storage/import/feed.atom. The zip holds %d album files and %d entries in all.\n", $feed, number_format($bytes / 1048576, 1), $images, $zip->numFiles);
