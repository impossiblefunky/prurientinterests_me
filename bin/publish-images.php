<?php
declare(strict_types=1);

/** Publishes every draft gallery image and album. Safe to run again. */
require dirname(__DIR__) . '/vendor/autoload.php';

use App\Support\Db;

$pdo = Db::pdo();
$before = (int) $pdo->query("SELECT COUNT(*) FROM gallery_items WHERE status <> 'published'")->fetchColumn();
$pdo->exec("UPDATE gallery_items SET status = 'published' WHERE status <> 'published'");
$albums = $pdo->exec("UPDATE albums SET status = 'published' WHERE status <> 'published'");
echo "$before draft images and $albums draft albums published.\n";
