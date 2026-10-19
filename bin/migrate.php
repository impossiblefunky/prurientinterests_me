<?php
declare(strict_types=1);

/**
 * Applies database/migrations/*.sql in filename order, once each (recorded in schema_migrations).
 *   php bin/migrate.php            apply pending
 *   php bin/migrate.php --status   list applied/pending
 * Each file may contain several statements separated by semicolons at line end.
 */

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Support\Db;

$pdo = Db::pdo();
$pdo->exec('CREATE TABLE IF NOT EXISTS schema_migrations (
    filename VARCHAR(190) NOT NULL PRIMARY KEY,
    applied_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

$applied = $pdo->query('SELECT filename FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);
$files = glob(dirname(__DIR__) . '/database/migrations/*.sql') ?: [];
sort($files);

if (in_array('--status', $argv, true)) {
    foreach ($files as $f) {
        printf("%s  %s\n", in_array(basename($f), $applied, true) ? 'applied' : 'PENDING', basename($f));
    }
    exit(0);
}

$ran = 0;
foreach ($files as $f) {
    $name = basename($f);
    if (in_array($name, $applied, true)) {
        continue;
    }
    echo "Applying $name ... ";
    $sql = file_get_contents($f);
    // Strip full-line comments, then split on semicolons that end a line.
    $sql = preg_replace('/^\s*--.*$/m', '', $sql);
    $statements = preg_split('/;\s*(?:\r?\n|$)/', $sql);
    try {
        foreach ($statements as $stmt) {
            $stmt = trim($stmt);
            if ($stmt !== '') {
                $pdo->exec($stmt);
            }
        }
        $ins = $pdo->prepare('INSERT INTO schema_migrations (filename, applied_at) VALUES (?, ?)');
        $ins->execute([$name, Db::utcNow()]);
        echo "ok\n";
        $ran++;
    } catch (Throwable $e) {
        echo "FAILED\n" . $e->getMessage() . "\n";
        exit(1);
    }
}
echo $ran === 0 ? "Nothing to apply.\n" : "Applied $ran migration(s).\n";
