<?php
declare(strict_types=1);

/**
 * Self-hosts the images used in posts, from the Blogger Takeout zip.
 *   php bin/import-images.php path/to/takeout.zip --dry-run     (counts what would happen; writes nothing)
 *   php bin/import-images.php path/to/takeout.zip               (does it)
 *   add --limit=50 to try the first 50 posts that have images
 * Run it after bin/import-blogger.php. Safe to run again. Writes storage/reports/images-not-found.txt.
 * Gallery items it creates are DRAFTS (so nothing explicit goes public until you review it) in draft albums named by year.
 */
ini_set('memory_limit', '1024M');
set_time_limit(0);
require dirname(__DIR__) . '/vendor/autoload.php';

use App\Import\ImageImporter;
use App\Support\Env;

$zip = $argv[1] ?? '';
if ($zip === '' || str_starts_with($zip, '--') || !is_readable($zip)) {
    fwrite(STDERR, "Usage: php bin/import-images.php path/to/takeout.zip [--dry-run] [--limit=N]\n");
    exit(1);
}
$limit = null;
foreach ($argv as $a) {
    if (preg_match('/^--limit=(\d+)$/', $a, $m)) { $limit = (int) $m[1]; }
}
$imp = new ImageImporter($zip, in_array('--dry-run', $argv, true));
echo number_format($imp->indexSize()) . " image files found in the Takeout.\n";
$imp->run($limit);
foreach ($imp->counts as $k => $v) {
    printf("%-18s %s\n", $k, number_format($v));
}
@mkdir(Env::root() . '/storage/reports', 0775, true);
file_put_contents(Env::root() . '/storage/reports/images-not-found.txt', implode("\n", $imp->missing) . "\n");
if ($imp->problems) {
    file_put_contents(Env::root() . '/storage/reports/images-problems.txt', implode("\n", $imp->problems) . "\n");
    echo "Some files could not be processed: storage/reports/images-problems.txt\n";
}
echo "Images that are not in the Takeout (other websites, old Blogger hosts): storage/reports/images-not-found.txt\n";
