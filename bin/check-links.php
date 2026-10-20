<?php
declare(strict_types=1);

/**
 * Checks every outside link (and hot-linked picture) in stories, pages and books.
 *   php bin/check-links.php          prints counts and the dead links; writes storage/reports/links.tsv
 *   php bin/check-links.php --strip-preview   also says how many dead links and pictures would be removed from posts
 *   php bin/check-links.php --strip           removes them (the words stay; each changed story keeps its old text as a revision)
 * Run it on the server (the work computer cannot always reach other sites). Read-only: it changes nothing.
 * "dead" = the page is gone (404/410), the site name no longer exists, refuses connections, or has a broken certificate.
 * "blocked" = the site answered but would not talk to a robot (403, 429, 999) or had a server error; check those by hand.
 */
ini_set('memory_limit', '512M');
set_time_limit(0);
require dirname(__DIR__) . '/vendor/autoload.php';

use App\Support\Db;
use App\Support\Env;
use App\Support\LinkStripper;

$pdo = Db::pdo();
$mine = array_filter([parse_url((string) Env::get('APP_URL', ''), PHP_URL_HOST), 'prurientinterests.me', 'www.prurientinterests.me']);
/** @var array<string,array{n:int,where:list<string>}> $found */
$found = [];
$scan = function (string $html, string $where) use (&$found, $mine): void {
    if (!preg_match_all('#<(?:a|img)\b[^>]*?\b(?:href|src)="(https?://[^"\s]+)"#i', $html, $m)) {
        return;
    }
    $pics = [];
    if (preg_match_all('#<img\b[^>]*?\bsrc="(https?://[^"\s]+)"#i', $html, $pm)) {
        foreach ($pm[1] as $pu) {
            $pics[preg_replace('/#.*$/', '', html_entity_decode($pu, ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? $pu] = true;
        }
    }
    foreach (array_unique($m[1]) as $u) {
        $u = html_entity_decode($u, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $u = preg_replace('/#.*$/', '', $u) ?? $u;
        $host = strtolower((string) parse_url($u, PHP_URL_HOST));
        if ($host === '' || in_array($host, $mine, true)) {
            continue;
        }
        if (isset($pics[$u])) {
            $found[$u]['img'] = true;
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
function probe(array $urls, bool $get, int $parallel = 25): array
{
    $mh = curl_multi_init();
    $out = [];
    $handles = [];
    $queue = $urls;
    $start = time();
    $launch = function () use (&$queue, &$handles, $mh, $get, $parallel): void {
        while (count($handles) < $parallel && $queue) {
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
// Name-lookup and connection failures are often just the server being overloaded by 25 lookups at once.
// Try those again, a few at a time, and keep the second answer.
$retry = array_keys(array_filter($res, fn($r) => $r['code'] === 0 && in_array($r['err'], [6, 7, 28, 35, 56], true)));
if ($retry) {
    echo count($retry) . " links had no answer; trying those again slowly...\n";
    $res = array_merge($res, probe($retry, true, 4));
}

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

$strip = in_array('--strip', $argv, true);
if ($strip || in_array('--strip-preview', $argv, true)) {
    // Only links that are certainly gone: the page answered 404/410, or the site name no longer exists (after the slow retry).
    $gone = [];
    // Browsers load pictures on this https site over https even when the old post says http://, so a picture whose
    // site has no working https version is broken for visitors even though the plain http address still answers.
    $httpPics = array_values(array_filter(array_keys($found), fn($u) => !empty($found[$u]['img']) && str_starts_with($u, 'http://')));
    $secure = [];
    if ($httpPics) {
        echo count($httpPics) . " pictures use http://; checking they also load over https...\n";
        $variants = [];
        foreach ($httpPics as $u) {
            $variants['https://' . substr($u, 7)] = $u;
        }
        foreach (probe(array_keys($variants), true, 8) as $v => $r2) {
            $secure[$variants[$v]] = $r2;
        }
    }
    foreach ($found as $u => $info) {
        $r = $res[$u] ?? ['code' => 0, 'err' => 28];
        $isPic = !empty($info['img']);
        if (!$isPic && preg_match('/pornoperson|pr0noperson/i', $u)) {
            continue;   // Mike's own profile addresses are never removed automatically (pictures are judged on their own)
        }
        if (in_array($r['code'], [404, 410], true) || ($r['code'] === 0 && $r['err'] === 6)) {
            $gone[$u] = true;
        } elseif ($isPic && isset($secure[$u])) {
            $r2 = $secure[$u];
            if (in_array($r2['code'], [404, 410], true) || ($r2['code'] === 0 && in_array($r2['err'], [6, 7, 35, 51, 58, 60], true))) {
                $gone[$u] = true;   // no usable https version: the visitor's browser cannot show it
            }
        }
    }
    echo count($gone) . " links are certainly dead.\n";
    $totLinks = $totPics = $changed = 0;
    $now = Db::utcNow();
    foreach ($pdo->query("SELECT id, title, body_html FROM stories WHERE status = 'published'")->fetchAll() as $r) {
        [$new, $l, $p] = LinkStripper::strip((string) $r['body_html'], $gone);
        if ($new === $r['body_html']) {
            continue;
        }
        $changed++;
        $totLinks += $l;
        $totPics += $p;
        if ($strip) {
            $pdo->prepare('INSERT INTO story_revisions (story_id, title, body_html, saved_at) VALUES (?,?,?,?)')->execute([$r['id'], $r['title'], $r['body_html'], $now]);
            $pdo->prepare('UPDATE stories SET body_html = ? WHERE id = ?')->execute([$new, $r['id']]);
        }
    }
    foreach ($pdo->query("SELECT id, body_html FROM pages WHERE status = 'published'")->fetchAll() as $r) {
        [$new, $l, $p] = LinkStripper::strip((string) $r['body_html'], $gone);
        if ($new !== $r['body_html']) {
            $changed++;
            $totLinks += $l;
            $totPics += $p;
            if ($strip) {
                $pdo->prepare('UPDATE pages SET body_html = ? WHERE id = ?')->execute([$new, $r['id']]);
            }
        }
    }
    foreach ($pdo->query("SELECT id, description_html, excerpt_html FROM books WHERE status = 'published'")->fetchAll() as $r) {
        [$d, $l1, $p1] = LinkStripper::strip((string) $r['description_html'], $gone);
        [$x, $l2, $p2] = LinkStripper::strip((string) $r['excerpt_html'], $gone);
        if ($d !== $r['description_html'] || $x !== $r['excerpt_html']) {
            $changed++;
            $totLinks += $l1 + $l2;
            $totPics += $p1 + $p2;
            if ($strip) {
                $pdo->prepare('UPDATE books SET description_html = ?, excerpt_html = ? WHERE id = ?')->execute([$d, $x, $r['id']]);
            }
        }
    }
    printf("%s: %d links unwrapped and %d broken pictures removed across %d posts, pages and books.\n", $strip ? 'Done' : 'Preview only, nothing changed', $totLinks, $totPics, $changed);
    if ($strip && $changed) {
        passthru(PHP_BINARY . ' ' . escapeshellarg(__DIR__ . '/rebuild-search.php') . ' --all');
    }
}
