<?php
declare(strict_types=1);

/**
 * Router for PHP's built-in test server (local use only; DreamHost uses Apache and public/.htaccess).
 *   php -S 127.0.0.1:8099 -t public bin/dev-router.php
 * Real files (CSS, JS, images) are served as they are; everything else goes to the site.
 */
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$file = dirname(__DIR__) . '/public' . $path;
if ($path !== '/' && is_file($file) && !str_ends_with($file, '.php')) {
    return false;
}
require dirname(__DIR__) . '/public/index.php';
