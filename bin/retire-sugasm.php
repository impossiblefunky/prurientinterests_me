<?php
declare(strict_types=1);

/**
 * Takes the old Sugasm link-roundup posts off the public site by turning them into drafts (nothing is deleted:
 * each one can be republished from the admin Stories screen).
 *   php bin/retire-sugasm.php --preview   counts them, changes nothing
 *   php bin/retire-sugasm.php             does it
 *   add --like=elust to retire the posts with that word in the title instead of Sugasm
 * A post counts when "Sugasm" is in its title. Posts only tagged Sugasm (like the eLust roundups) are left alone.
 * Safe to run again.
 */
require dirname(__DIR__) . '/vendor/autoload.php';

use App\Support\Db;

$preview = in_array('--preview', $argv, true);
$word = 'sugasm';
foreach ($argv as $a) {
    if (preg_match('/^--like=([a-z0-9]{3,30})$/i', $a, $m)) {
        $word = $m[1];
    }
}
$pdo = Db::pdo();
$find = $pdo->prepare("SELECT id FROM stories WHERE title LIKE ? AND status = 'published'");
$find->execute(['%' . $word . '%']);
$ids = $find->fetchAll(PDO::FETCH_COLUMN);
echo count($ids) . " published posts with \"$word\" in the title found.\n";
if (!$preview && $ids) {
    $in = implode(',', array_map('intval', $ids));
    $pdo->exec("UPDATE stories SET status = 'draft', updated_at = UTC_TIMESTAMP() WHERE id IN ($in)");
    echo count($ids) . " posts moved to draft.\n";
    // keep the site search in step
    passthru(PHP_BINARY . ' ' . escapeshellarg(__DIR__ . '/rebuild-search.php') . ' --all');
} elseif ($preview) {
    echo "Preview only: nothing was changed.\n";
}
