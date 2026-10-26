<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Web\Site;

if (is_production()) {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    ini_set('log_errors', '1');
}

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header("Content-Security-Policy: frame-ancestors 'self'");
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=(), interest-cohort=()');
if (is_production() && (($_SERVER['HTTPS'] ?? '') === 'on' || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')) {
    header('Strict-Transport-Security: max-age=300');   // raise only after the certificate is confirmed good for the bare domain and www
}

set_exception_handler(function (Throwable $e): void {
    error_log('[site] ' . $e::class . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
    header('X-Robots-Tag: noindex');
    echo '<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex"><title>Something went wrong | Prurient Interests</title>'
        . '<style>body{margin:0;background:#0f0d10;color:#f3eae3;font:18px/1.7 Georgia,serif}main{max-width:36rem;margin:0 auto;padding:4rem 1.5rem}h1{font-weight:400;font-size:2.2rem;line-height:1.15}'
        . 'a{color:#e0a6c2}.row{display:flex;flex-wrap:wrap;gap:.5rem;margin-top:1.5rem}.row a{display:inline-flex;align-items:center;min-height:44px;padding:0 1rem;border:1px solid #5b4d55;text-decoration:none;font:16px system-ui}'
        . 'a:focus-visible{outline:3px solid #ffd166;outline-offset:2px}</style>'
        . '<body><main><h1>Something went wrong on our end.</h1><p>That page could not be shown. It is not you, and nothing was lost. Please try again in a moment, or head somewhere else:</p>'
        . '<nav class="row" aria-label="Other places"><a href="/">Home</a><a href="/stories">Stories</a><a href="/tags">Interests</a><a href="/gallery">Gallery</a><a href="/books">Books</a><a href="/contact">Tell me about it</a></nav></main></body></html>';
});

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// Safety net for what public/.htaccess already does: www goes to the bare domain, and a trailing slash is dropped,
// in one 301 hop, for page requests (not form posts, not the admin).
$host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
$trailing = $path !== '/' && str_ends_with($path, '/') && !str_starts_with($path, '/admin');
$wwwHop = $host === 'www.prurientinterests.me';
if (in_array($method, ['GET', 'HEAD'], true) && ($wwwHop || $trailing)) {
    $clean = $path === '/' ? '/' : rtrim($path, '/');
    $query = (string) ($_SERVER['QUERY_STRING'] ?? '');
    $to = ($wwwHop ? 'https://prurientinterests.me' : '') . ($clean === '' ? '/' : $clean) . ($query !== '' ? '?' . $query : '');
    header('Location: ' . $to, true, 301);
    return;
}
(new Site())->handle($method, $path);
