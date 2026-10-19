<?php
declare(strict_types=1);

namespace App\Admin;

use App\Support\Db;
use App\Support\Env;

/**
 * Admin sign-in: PHP sessions kept in a private folder, secure cookie flags, session id rotation on login,
 * login throttling (5 failures per 15 minutes per connection or per email), idle timeout, and per-session CSRF tokens.
 */
final class Auth
{
    private const IDLE_SECONDS = 8 * 3600;

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        $dir = Env::root() . '/storage/sessions';
        if (!is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }
        session_save_path($dir);
        session_name('piadmin');
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        session_set_cookie_params(['lifetime' => 0, 'path' => '/admin', 'secure' => $https, 'httponly' => true, 'samesite' => 'Lax']);
        ini_set('session.use_strict_mode', '1');
        ini_set('session.gc_maxlifetime', (string) self::IDLE_SECONDS);
        session_start();
        if (isset($_SESSION['last']) && time() - (int) $_SESSION['last'] > self::IDLE_SECONDS) {
            self::logout();
            session_start();
        }
        if (isset($_SESSION['uid'])) {
            $_SESSION['last'] = time();
        }
    }

    /** @return array<string,mixed>|null the signed-in user */
    public static function user(): ?array
    {
        if (empty($_SESSION['uid'])) {
            return null;
        }
        $st = Db::pdo()->prepare('SELECT id, email, name FROM admin_users WHERE id = ?');
        $st->execute([(int) $_SESSION['uid']]);
        return $st->fetch() ?: null;
    }

    public static function ipHash(): string
    {
        return substr(hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . Env::get('APP_KEY', '')), 0, 24);
    }

    /** True when there have been too many recent failures for this connection or email. */
    public static function throttled(string $email): bool
    {
        $st = Db::pdo()->prepare('SELECT COUNT(*) FROM admin_login_attempts WHERE created_at > ? AND (ip_hash = ? OR email = ?)');
        $st->execute([gmdate('Y-m-d H:i:s', time() - 900), self::ipHash(), $email]);
        return (int) $st->fetchColumn() >= 5;
    }

    public static function attempt(string $email, string $password): bool
    {
        $email = strtolower(trim($email));
        $st = Db::pdo()->prepare('SELECT id, password_hash FROM admin_users WHERE email = ?');
        $st->execute([$email]);
        $u = $st->fetch();
        // Always run a hash comparison so timing does not reveal whether the email exists.
        $ok = password_verify($password, $u['password_hash'] ?? '$2y$10$usesomesillystringfore7hnbRJHxXVLeakoG8K30oukPsA.ztMG');
        if ($u && $ok) {
            Db::pdo()->prepare('DELETE FROM admin_login_attempts WHERE email = ?')->execute([$email]);
            session_regenerate_id(true);
            $_SESSION['uid'] = (int) $u['id'];
            $_SESSION['last'] = time();
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
            Db::pdo()->prepare('UPDATE admin_users SET last_login_at = ? WHERE id = ?')->execute([Db::utcNow(), $u['id']]);
            return true;
        }
        Db::pdo()->prepare('INSERT INTO admin_login_attempts (ip_hash, email, created_at) VALUES (?,?,?)')->execute([self::ipHash(), $email, Db::utcNow()]);
        return false;
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    public static function csrf(): string
    {
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf'];
    }

    public static function checkCsrf(): bool
    {
        return isset($_POST['csrf'], $_SESSION['csrf']) && hash_equals($_SESSION['csrf'], (string) $_POST['csrf']);
    }
}
