<?php
declare(strict_types=1);

/**
 * Fills (or refreshes) the plain-text copy of every story that the site search reads.
 *   php bin/rebuild-search.php          only stories that have none yet
 *   php bin/rebuild-search.php --all    every story
 * The deploy runs this after the database migrations, so it normally needs no attention.
 */
require dirname(__DIR__) . '/vendor/autoload.php';

use App\Support\Db;

$all = in_array('--all', $argv, true);
$pdo = Db::pdo();
$rows = $pdo->query('SELECT id, body_html FROM stories' . ($all ? '' : ' WHERE search_text IS NULL'))->fetchAll();
$up = $pdo->prepare('UPDATE stories SET search_text = ? WHERE id = ?');
foreach ($rows as $r) {
    $up->execute([search_plain($r['body_html']), $r['id']]);
}
echo count($rows) . " stories indexed for search.\n";
