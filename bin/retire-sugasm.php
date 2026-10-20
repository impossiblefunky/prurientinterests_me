<?php
declare(strict_types=1);

/**
 * Takes the old Sugasm link-roundup posts off the public site by turning them into drafts (nothing is deleted:
 * each one can be republished from the admin Stories screen).
 *   php bin/retire-sugasm.php --preview   counts them, changes nothing
 *   php bin/retire-sugasm.php             does it
 * A post counts when "Sugasm" is in its title. Posts only tagged Sugasm (like the eLust roundups) are left alone.
 * Safe to run again.
 */
require dirname(__DIR__) . '/vendor/autoload.php';

use App\Support\Db;

$preview = in_array('--preview', $argv, true);
$pdo = Db::pdo();
$ids = $pdo->query("SELECT id FROM stories WHERE title LIKE '%sugasm%' AND status = 'published'")->fetchAll(PDO::FETCH_COLUMN);
echo count($ids) . " published Sugasm posts found.\n";
if (!$preview && $ids) {
    $in = implode(',', array_map('intval', $ids));
    $pdo->exec("UPDATE stories SET status = 'draft', updated_at = UTC_TIMESTAMP() WHERE id IN ($in)");
    echo count($ids) . " posts moved to draft.\n";
    // keep the site search in step
    passthru(PHP_BINARY . ' ' . escapeshellarg(__DIR__ . '/rebuild-search.php') . ' --all');
} elseif ($preview) {
    echo "Preview only: nothing was changed.\n";
}
