<?php
declare(strict_types=1);

/**
 * Creates (or resets the password of) an admin user.
 *   php bin/create-admin.php you@example.com "Your Name"
 * The password is typed at the prompt and is not shown or stored anywhere but as a hash.
 */
require dirname(__DIR__) . '/vendor/autoload.php';

use App\Support\Db;

$email = strtolower(trim($argv[1] ?? ''));
$name = $argv[2] ?? '';
if (strlen($email) < 5 || !preg_match('/^[^@\s]+@[^@\s]+\.[^@\s]+$/', $email)) {
    fwrite(STDERR, "Usage: php bin/create-admin.php you@example.com \"Your Name\"\n");
    exit(1);
}
// Typed at a prompt (hidden), or piped in on standard input by the GitHub "Server task" workflow.
$tty = function_exists('posix_isatty') && posix_isatty(STDIN);
if ($tty) {
    echo 'Password (at least 12 characters): ';
    system('stty -echo');
}
$pw = trim((string) fgets(STDIN));
if ($tty) {
    system('stty echo');
    echo "\n";
}
if (mb_strlen($pw) < 12) {
    fwrite(STDERR, "Password must be at least 12 characters.\n");
    exit(1);
}
$pdo = Db::pdo();
$pdo->prepare('INSERT INTO admin_users (email, name, password_hash, created_at) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash), name = VALUES(name)')
    ->execute([$email, $name, password_hash($pw, PASSWORD_DEFAULT), Db::utcNow()]);
echo "Admin $email is ready.\n";
