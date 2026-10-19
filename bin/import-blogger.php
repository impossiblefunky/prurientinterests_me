<?php
declare(strict_types=1);

/**
 * Imports the Blogger Takeout feed. Safe to run again; existing stories are updated, not duplicated.
 *   php bin/import-blogger.php path/to/feed.atom --dry-run     (counts only, writes nothing)
 *   php bin/import-blogger.php path/to/feed.atom
 */
require dirname(__DIR__) . '/vendor/autoload.php';

use App\Import\BloggerImporter;

$file = $argv[1] ?? '';
if ($file === '' || !is_readable($file)) {
    fwrite(STDERR, "Usage: php bin/import-blogger.php path/to/feed.atom [--dry-run]\n");
    exit(1);
}
$imp = new BloggerImporter(in_array('--dry-run', $argv, true));
$imp->run($file);
foreach ($imp->counts as $k => $v) {
    printf("%-16s %d\n", $k, $v);
}
if ($imp->counts['external_images'] > 0) {
    echo "Note: {$imp->counts['external_images']} images still point at Blogger/Google servers; they should be downloaded and self-hosted before launch.\n";
}
