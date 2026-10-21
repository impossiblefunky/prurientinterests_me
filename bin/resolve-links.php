<?php
declare(strict_types=1);

/**
 * Shows where each book purchase link really goes (short links like bit.ly and amzn.to are followed). Read-only.
 *   php bin/resolve-links.php
 * Prints the book, the edition, and the final website and page (the part after ? is not shown).
 */
require dirname(__DIR__) . '/vendor/autoload.php';

use App\Support\Db;

$rows = Db::pdo()->query('SELECT b.title, e.id, e.name, e.purchase_url FROM book_editions e JOIN books b ON b.id = e.book_id WHERE e.purchase_url <> "" ORDER BY b.sort_order, b.id, e.sort_order')->fetchAll();
foreach ($rows as $r) {
    $ch = curl_init($r['purchase_url']);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 8, CURLOPT_NOBODY => true,
        CURLOPT_TIMEOUT => 20, CURLOPT_CONNECTTIMEOUT => 8, CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; PrurientLinkCheck/1.0)']);
    curl_exec($ch);
    $final = (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    $short = parse_url($r['purchase_url'], PHP_URL_HOST) . parse_url($r['purchase_url'], PHP_URL_PATH);
    $dest = parse_url($final, PHP_URL_HOST) . parse_url($final, PHP_URL_PATH);
    printf("%-34s | %-16s | %s -> %s [%d]\n", mb_substr($r['title'], 0, 34), mb_substr($r['name'], 0, 16), $short, $dest ?: '(no answer)', $code);
}
