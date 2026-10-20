<?php
declare(strict_types=1);

namespace App\Web;

use App\Support\Db;
use App\Support\Env;

/**
 * Protection shared by the contact form and the comment form: a hidden trap field, a timing check, a per-visitor
 * limit, and Cloudflare Turnstile (when its keys are set in Settings). Nothing here needs a login or a session.
 */
final class Forms
{
    private const MIN_SECONDS = 4;        // a person cannot read and fill a form faster than this
    private const MAX_SECONDS = 21600;    // a form older than six hours is stale

    public static function ipHash(): string
    {
        return substr(hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . Env::get('APP_KEY', '')), 0, 24);
    }

    /** A signed timestamp the form carries; nobody can forge or reuse an old one. */
    public static function token(): string
    {
        $t = (string) time();
        return $t . '.' . hash_hmac('sha256', $t, (string) Env::get('APP_KEY', ''));
    }

    public static function tokenOk(string $token): bool
    {
        [$t, $sig] = array_pad(explode('.', $token, 2), 2, '');
        if (!ctype_digit($t) || !hash_equals(hash_hmac('sha256', $t, (string) Env::get('APP_KEY', '')), $sig)) {
            return false;
        }
        $age = time() - (int) $t;
        return $age >= self::MIN_SECONDS && $age <= self::MAX_SECONDS;
    }

    public static function emailOk(string $email): bool
    {
        return strlen($email) >= 5 && preg_match('/^[^@\s]+@[^@\s]+\.[^@\s]+$/', $email) === 1 && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /** True when this visitor has already used up their allowance. Records the attempt either way. */
    public static function limited(string $kind, int $max, int $minutes): bool
    {
        $pdo = Db::pdo();
        $pdo->exec('DELETE FROM form_hits WHERE created_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 2 DAY)');
        $n = $pdo->prepare('SELECT COUNT(*) FROM form_hits WHERE kind = ? AND ip_hash = ? AND created_at > DATE_SUB(UTC_TIMESTAMP(), INTERVAL ? MINUTE)');
        $n->execute([$kind, self::ipHash(), $minutes]);
        $over = (int) $n->fetchColumn() >= $max;
        $pdo->prepare('INSERT INTO form_hits (kind, ip_hash, created_at) VALUES (?, ?, UTC_TIMESTAMP())')->execute([$kind, self::ipHash()]);
        return $over;
    }

    public static function turnstileKey(): string
    {
        return trim(setting('turnstile_site_key', ''));
    }

    public static function turnstileOn(): bool
    {
        return self::turnstileKey() !== '' && trim(setting('turnstile_secret_key', '')) !== '';
    }

    /** Asks Cloudflare whether the visitor passed the check. Any failure counts as "no". */
    public static function turnstilePassed(string $response): bool
    {
        if (!self::turnstileOn()) {
            return true;   // not switched on yet: the other protections still apply
        }
        if ($response === '' || strlen($response) > 2048) {
            return false;
        }
        $ch = curl_init('https://challenges.cloudflare.com/turnstile/v0/siteverify');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query(['secret' => trim(setting('turnstile_secret_key', '')), 'response' => $response, 'remoteip' => $_SERVER['REMOTE_ADDR'] ?? '']),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 6,
            CURLOPT_CONNECTTIMEOUT => 4,
        ]);
        $raw = curl_exec($ch);
        curl_close($ch);
        $json = is_string($raw) ? json_decode($raw, true) : null;
        return is_array($json) && ($json['success'] ?? false) === true;
    }

    /**
     * Checks everything that is not about the message itself.
     * @return list<string> problems to show the visitor (empty means all clear)
     */
    public static function guard(string $kind, int $max, int $minutes): array
    {
        if (trim((string) ($_POST['website'] ?? '')) !== '') {
            return ['trap'];   // a robot filled the hidden field; the caller shows a normal "thanks" so it learns nothing
        }
        if (!self::tokenOk((string) ($_POST['form_token'] ?? ''))) {
            return ['Please reload the page and send the form again (it either went too fast or sat open too long).'];
        }
        if (self::limited($kind, $max, $minutes)) {
            return ['You have sent several in a short time. Please try again later.'];
        }
        if (!self::turnstilePassed((string) ($_POST['cf-turnstile-response'] ?? ''))) {
            return ['We could not confirm you are not a robot. Please complete the check and try again.'];
        }
        return [];
    }
}
