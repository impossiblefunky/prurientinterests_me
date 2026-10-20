<?php
declare(strict_types=1);

/**
 * Groups the numbered stories (Part 1, Part 2 ...) into series.
 *   php bin/make-series.php --preview   shows what would happen
 *   php bin/make-series.php             does it (safe to run again)
 * Every story is named by its id and its title must still start with the series name,
 * so a story that was renamed or re-imported is skipped rather than put in the wrong series.
 */
require dirname(__DIR__) . '/vendor/autoload.php';

use App\Support\Db;

$preview = in_array('--preview', $argv, true);

// series title => story ids in reading order
$plan = [
    'High School Bully' => [813, 485, 842, 1147, 889, 803],
    'Teaching the Teacher' => [157, 565, 1120, 579, 277, 1101],
    'The Therapist' => [838, 51, 237],
    'Political Maneuvers' => [105, 880],
    'Re-Education' => [210, 110],
    'Cerebral Trainee Subject: Susan' => [288, 764],
    'Pet for MAGA' => [1173, 268, 541],
];

$pdo = Db::pdo();
$find = $pdo->prepare('SELECT id, title, status FROM stories WHERE id = ?');
$getSeries = $pdo->prepare('SELECT id FROM series WHERE slug = ?');
$addSeries = $pdo->prepare('INSERT INTO series (slug, title, description, created_at) VALUES (?, ?, ?, UTC_TIMESTAMP())');
$setStory = $pdo->prepare('UPDATE stories SET series_id = ?, chapter_order = ? WHERE id = ?');

foreach ($plan as $title => $ids) {
    $members = [];
    foreach ($ids as $i => $id) {
        $find->execute([$id]);
        $s = $find->fetch();
        if (!$s || stripos($s['title'], substr($title, 0, 10)) !== 0) {
            echo "  SKIPPED id $id (not found or title changed)\n";
            continue;
        }
        $members[] = [$s, $i + 1];
    }
    if (count($members) < 2) {
        echo "$title: not enough stories found, series not made.\n";
        continue;
    }
    echo "$title\n";
    foreach ($members as [$s, $n]) {
        echo sprintf("  %d. %s (%s)\n", $n, $s['title'], $s['status']);
    }
    if ($preview) {
        continue;
    }
    $slug = slugify($title);
    $getSeries->execute([$slug]);
    $sid = $getSeries->fetchColumn();
    if (!$sid) {
        $addSeries->execute([$slug, $title, '']);
        $sid = (int) $pdo->lastInsertId();
    }
    foreach ($members as [$s, $n]) {
        $setStory->execute([$sid, $n, $s['id']]);
    }
}
echo $preview ? "Preview only, nothing changed.\n" : "Done.\n";
