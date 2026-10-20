<?php
declare(strict_types=1);

/**
 * Spell-check report over the stories in the database. READ-ONLY: it never changes a story.
 *   php bin/spellcheck.php                 all stories
 *   php bin/spellcheck.php --status=draft  only drafts (or published)
 *   php bin/spellcheck.php --wordlist=/path/to/words
 * Writes storage/reports/spellcheck.md (read it) and storage/reports/spellcheck.json.
 *
 * Review the report, then put the corrections you approve in docs/spelling-fixes.tsv and run bin/apply-spelling.php.
 * Words that are correct for your writing (names, slang, brand names) go in docs/custom-dictionary.txt, one per line,
 * and stop being reported next time.
 */
require dirname(__DIR__) . '/vendor/autoload.php';

use App\Import\Spelling;
use App\Support\Db;
use App\Support\Env;

$opt = ['status' => '', 'wordlist' => '/usr/share/dict/american-english'];
foreach (array_slice($argv, 1) as $a) {
    if (preg_match('/^--([a-z]+)=(.*)$/', $a, $m)) { $opt[$m[1]] = $m[2]; }
}
$root = Env::root();
$sp = new Spelling([$opt['wordlist']], $root . '/docs/custom-dictionary.txt');

$sql = 'SELECT id, title, slug, status, body_html FROM stories' . ($opt['status'] !== '' ? ' WHERE status = ' . Db::pdo()->quote($opt['status']) : '') . ' ORDER BY id';
$unknown = [];   // lower => ['forms'=>[], 'count'=>n, 'stories'=>[id=>title], 'ctx'=>[]]
$doubles = [];
$totalWords = 0;
$storyCount = 0;
foreach (Db::pdo()->query($sql) as $s) {
    $storyCount++;
    $text = Spelling::visibleText($s['body_html']);
    $words = Spelling::words($text);
    $totalWords += count($words);
    foreach ($words as $i => $w) {
        $lw = mb_strtolower($w);
        if (isset($words[$i + 1]) && $lw === mb_strtolower($words[$i + 1]) && mb_strlen($w) > 1 && !in_array($lw, ['had', 'that', 'ha', 'no', 'very', 'so', 'blah', 'bye', 'yada', 'poo', 'knock', 'tick', 'bang', 'ho', 'hey', 'night'], true)) {
            $doubles[] = ['title' => $s['title'], 'id' => $s['id'], 'phrase' => "$w {$words[$i + 1]}"];
        }
        if ($sp->isKnown($w) || mb_strlen($w) < 2) { continue; }
        $u = &$unknown[$lw];
        $u ??= ['forms' => [], 'count' => 0, 'stories' => [], 'ctx' => []];
        $u['forms'][$w] = ($u['forms'][$w] ?? 0) + 1;
        $u['count']++;
        $u['stories'][$s['id']] = $s['title'];
        if (count($u['ctx']) < 2) {
            $pos = mb_strpos($text, $w);
            $u['ctx'][] = '…' . trim(mb_substr($text, max(0, $pos - 40), 90)) . '…';
        }
        unset($u);
    }
}
$sugg = Spelling::suggest(array_keys($unknown));
// Capitalised everywhere = probably a name; everything else = probably a typo or slang.
$names = $typos = [];
foreach ($unknown as $lw => $u) {
    $capOnly = !array_filter(array_keys($u['forms']), fn($f) => $f === mb_strtolower($f));
    $row = $u + ['word' => array_key_first($u['forms']), 'suggest' => $sugg[$lw] ?? $sugg[array_key_first($u['forms'])] ?? []];
    if ($capOnly) { $names[] = $row; } else { $typos[] = $row; }
}
usort($typos, fn($a, $b) => $b['count'] <=> $a['count']);
usort($names, fn($a, $b) => $b['count'] <=> $a['count']);

$md = "# Spelling report\n\nChecked $storyCount stories, " . number_format($totalWords) . " words. Nothing has been changed.\n\n"
    . "How to use this: copy the corrections you agree with into `docs/spelling-fixes.tsv` (wrong, a tab, then right). "
    . "Add words that are fine as written to `docs/custom-dictionary.txt`.\n\n"
    . "## Possible misspellings (" . count($typos) . ")\n\n| Word | Times | Aspell suggests | Where it appears |\n|---|---|---|---|\n";
$cell = fn(string $s) => str_replace(['|', "\n"], ['\\|', ' '], $s);
foreach ($typos as $r) {
    $md .= sprintf("| %s | %d | %s | %s<br><sub>%s</sub> |\n", $cell(implode(' / ', array_keys($r['forms']))), $r['count'], $cell(implode(', ', $r['suggest'])),
        $cell(implode('; ', array_slice($r['stories'], 0, 3))) . (count($r['stories']) > 3 ? ' +' . (count($r['stories']) - 3) . ' more' : ''), $cell($r['ctx'][0] ?? ''));
}
$md .= "\n## Names and capitalised words to confirm (" . count($names) . ")\n\n| Word | Times | Where it appears |\n|---|---|---|\n";
foreach ($names as $r) {
    $md .= sprintf("| %s | %d | %s |\n", $cell(implode(' / ', array_keys($r['forms']))), $r['count'], $cell(implode('; ', array_slice($r['stories'], 0, 3))));
}
$md .= "\n## Doubled words (" . count($doubles) . ")\n\n";
foreach ($doubles as $d) { $md .= "- “{$d['phrase']}” in {$d['title']}\n"; }
@mkdir($root . '/storage/reports', 0775, true);
file_put_contents($root . '/storage/reports/spellcheck.md', $md);
file_put_contents($root . '/storage/reports/spellcheck.json', json_encode(['typos' => $typos, 'names' => $names, 'doubles' => $doubles], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
printf("%d stories, %s words: %d possible misspellings, %d names to confirm, %d doubled words.\nReport: storage/reports/spellcheck.md\n",
    $storyCount, number_format($totalWords), count($typos), count($names), count($doubles));
