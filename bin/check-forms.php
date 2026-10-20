<?php
declare(strict_types=1);

/**
 * Checks the pieces behind the contact form and comments, from the server:
 *   php bin/check-forms.php
 * It reads settings, asks Cloudflare whether the Turnstile keys are accepted (using a fake visitor token, so Cloudflare
 * should say "invalid-input-response" if the secret key is right), looks at the message tables, and sends ONE test email
 * to the contact address so you can see that mail from the site arrives. Nothing else is changed.
 */
require dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/src/Web/helpers.php';

use App\Support\Db;
use App\Support\Env;

$pdo = Db::pdo();
$mask = function (string $e): string {
    return preg_match('/^(.).*@(.+)$/', $e, $m) ? $m[1] . '***@' . $m[2] : ($e === '' ? '(empty)' : '(not an email address)');
};
$to = trim(setting('contact_email', 'pornoperson@gmail.com'));
echo 'Contact messages go to: ' . $mask($to) . "\n";
echo 'Site address in the environment: ' . (Env::get('APP_URL', '') ?: '(missing)') . "\n";
echo 'APP_KEY (signs the form timer): ' . (strlen((string) Env::get('APP_KEY', '')) >= 32 ? 'set' : 'MISSING or short') . "\n";

$site = trim(setting('turnstile_site_key', ''));
$secret = trim(setting('turnstile_secret_key', ''));
echo 'Turnstile site key: ' . ($site !== '' ? 'saved (' . strlen($site) . ' characters)' : 'NOT saved') . "\n";
echo 'Turnstile secret key: ' . ($secret !== '' ? 'saved (' . strlen($secret) . ' characters)' : 'NOT saved') . "\n";
if ($site !== '' && $secret !== '') {
    $ch = curl_init('https://challenges.cloudflare.com/turnstile/v0/siteverify');
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query(['secret' => $secret, 'response' => 'check-forms-test-token']),
        CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10, CURLOPT_CONNECTTIMEOUT => 6]);
    $raw = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    $j = is_string($raw) ? json_decode($raw, true) : null;
    if (!is_array($j)) {
        echo 'Turnstile check: could not reach Cloudflare (' . ($err ?: 'no answer') . ")\n";
    } else {
        $codes = implode(', ', (array) ($j['error-codes'] ?? []));
        $verdict = match (true) {
            in_array('invalid-input-secret', (array) ($j['error-codes'] ?? []), true) => 'the SECRET key is wrong or from a different widget',
            in_array('invalid-input-response', (array) ($j['error-codes'] ?? []), true) => 'the secret key is accepted (the fake token was rightly refused)',
            default => 'unexpected answer',
        };
        echo "Turnstile check: $verdict [$codes]\n";
    }
}

foreach (['contact_messages', 'comments_archive', 'form_hits'] as $t) {
    try {
        $n = (int) $pdo->query("SELECT COUNT(*) FROM $t")->fetchColumn();
        echo "Table $t: ok ($n rows)\n";
    } catch (Throwable) {
        echo "Table $t: MISSING\n";
    }
}
echo 'Messages waiting in the Inbox: ' . (int) $pdo->query("SELECT COUNT(*) FROM contact_messages")->fetchColumn() . ' contact, '
    . (int) $pdo->query("SELECT COUNT(*) FROM comments_archive WHERE status = 'pending'")->fetchColumn() . " comments awaiting approval\n";

if (preg_match('/^[^@\s]+@[^@\s]+\.[^@\s]+$/', $to)) {
    $host = parse_url((string) Env::get('APP_URL', ''), PHP_URL_HOST) ?: 'localhost';
    $headers = ['From: ' . setting('site_title') . ' <noreply@' . $host . '>', 'Content-Type: text/plain; charset=UTF-8'];
    $ok = @mail($to, 'Prurient Interests: test email from the site', "This is a test.\n\nIf you are reading it, messages from the contact form will reach you here.\nSent " . gmdate('Y-m-d H:i') . " UTC.\n", implode("\r\n", $headers));
    echo 'Test email handed to the mail system: ' . ($ok ? 'yes (now look in the inbox, and the spam folder)' : 'NO, the server refused it') . "\n";
} else {
    echo "Test email skipped: no valid contact address is saved in Settings.\n";
}
