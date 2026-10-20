<?php
declare(strict_types=1);

/**
 * Checks every outside link (and hot-linked picture) in stories, pages and books.
 *   php bin/check-links.php          prints counts and the dead links; writes storage/reports/links.tsv
 * Run it on the server (the work computer cannot always reach other sites). Read-only: it changes nothing.
 * "dead" = the page is gone (404/410), the site name no longer exists, refuses connections, or has a broken certificate.
 * "blocked" = the site answered but would not talk to a robot (403, 429, 999) or had a server error; check those by hand.
 */
ini_set('memory_limit', '512M');
set_time_limit(0);
require dirname(__DIR__) . '/vendor/autoload.php';

use App\Support\Db;
use App\Support\Env;

$pdo = Db::pdo();
$mine = array_filter([parse_url((string) Env::get('APP_URL', ''), PHP_URL_HOST), 'prurientinterests.me', 'www.prurientinterests.me']);
/** @var array<string,array{n:int,where:list<string>}> $found */
$found = [];
$scan = function (string $html, string $where) use (&$found, $mine): void {
    if (!preg_match_all('#<(?:a|img)\b[^>]*?\b(?:href|src)="(https?://[^"\s]+)"#i', $html, $m)) {
        return;
    }
    foreach (array_unique($m[1]) as $u) {
        $u = html_entity_decode($u, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $u = preg_replace('/#.*$/', '', $u) ?? $u;
        $host = strtolower((string) parse_url($u, PHP_URL_HOST));
        if ($host === '' || in_array($host, $mine, true)) {
            continue;
        }
        $found[$u]['n'] = ($found[$u]['n'] ?? 0) + 1;
        if (count($found[$u]['where'] ?? []) < 3) {
            $found[$u]['where'][] = $where;
        }
    }
};
foreach ($pdo->query("SELECT slug, legacy_path, body_html FROM stories WHERE status = 'published'") as $r) {
    $scan((string) $r['body_html'], $r['legacy_path'] ?: '/story/' . $r['slug']);
}
foreach ($pdo->query("SELECT slug, legacy_path, body_html FROM pages WHERE status = 'published'") as $r) {
    $scan((string) $r['body_html'], $r['legacy_path'] ?: '/' . $r['slug']);
}
foreach ($pdo->query("SELECT slug, description_html, excerpt_html FROM books WHERE status = 'published'") as $r) {
    $scan((string) $r['description_html'] . (string) $r['excerpt_html'], '/books/' . $r['slug']);
}
echo number_format(count($found)) . " outside links to check.\n";

/** @param list<string> $urls @return array<string,array{code:int,err:int}> */
function probe(array $urls, bool $get): array
{
    $mh = curl_multi_init();
    $out = [];
    $handles = [];
    $queue = $urls;
    $start = time();
    $launch = function () use (&$queue, &$handles, $mh, $get): void {
        while (count($handles) < 25 && $queue) {
            $u = array_shift($queue);
            $ch = curl_init($u);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 6,
                CURLOPT_CONNECTTIMEOUT => 8, CURLOPT_TIMEOUT => 15, CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; PrurientLinkCheck/1.0; +https://prurientinterests.me)',
                CURLOPT_ENCODING => '', CURLOPT_NOBODY => !$get,
            ]);
            if ($get) {
                curl_setopt($ch, CURLOPT_RANGE, '0-1023');
            }
            curl_multi_add_handle($mh, $ch);
            $handles[(int) $ch] = [$ch, $u];
        }
    };
    $launch();
    while ($handles) {
        curl_multi_exec($mh, $running);
        curl_multi_select($mh, 1.0);
        while ($done = curl_multi_info_read($mh)) {
            $ch = $done['handle'];
            [, $u] = $handles[(int) $ch];
            $out[$u] = ['code' => (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE), 'err' => (int) $done['result']];
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
            unset($handles[(int) $ch]);
        }
        $launch();
        if (time() - $start > 1500) {
            break;   // never run past 25 minutes
        }
    }
    curl_multi_close($mh);
    return $out;
}

$first = probe(array_keys($found), false);
// Some sites refuse HEAD requests; ask those again with a tiny GET.
$again = array_keys(array_filter($first, fn($r) => $r['code'] >= 400 && !in_array($r['code'], [404, 410], true) || ($r['code'] === 0 && !in_array($r['err'], [6, 7, 60], true))));
$second = $again ? probe($again, true) : [];
$res = array_merge($first, $second);

$class = fn(array $r): string => match (true) {
    $r['code'] >= 200 && $r['code'] < 400 => 'ok',
    in_array($r['code'], [404, 410], true) => 'dead',
    $r['code'] === 0 && in_array($r['err'], [6, 7, 60, 35, 51, 58], true) => 'dead',   // no such site, refused, bad certificate
    $r['code'] === 0 => 'slow',
    default => 'blocked',
};
$counts = ['ok' => 0, 'dead' => 0, 'blocked' => 0, 'slow' => 0];
$lines = ["class\tstatus\turl\tpages\tfirst places"];
$dead = [];
foreach ($found as $u => $info) {
    $r = $res[$u] ?? ['code' => 0, 'err' => 28];
    $c = $class($r);
    $counts[$c]++;
    $lines[] = implode("\t", [$c, $r['code'] ?: 'err' . $r['err'], $u, $info['n'], implode(' ', $info['where'])]);
    if ($c === 'dead') {
        $dead[] = [$r['code'] ?: 'err' . $r['err'], $u, $info['n'], $info['where'][0]];
    }
}
@mkdir(Env::root() . '/storage/reports', 0775, true);
file_put_contents(Env::root() . '/storage/reports/links.tsv', implode("\n", $lines) . "\n");
printf("Fine: %d   Dead: %d   Blocked or server trouble (check by hand): %d   No answer in time: %d\n", ...array_values($counts));
usort($dead, fn($a, $b) => $b[2] <=> $a[2]);
foreach (array_slice($dead, 0, 300) as [$code, $u, $n, $where]) {
    echo "DEAD $code $u  ($n page" . ($n === 1 ? '' : 's') . ", e.g. $where)\n";
}
if (count($dead) > 300) {
    echo '... and ' . (count($dead) - 300) . " more in storage/reports/links.tsv\n";
}
