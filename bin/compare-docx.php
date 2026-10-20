<?php
declare(strict_types=1);

/**
 * Compares a Word document with the stories already in the database (the blog is the master copy). READ-ONLY.
 *   php bin/compare-docx.php storage/import/confessions.docx --split
 *   php bin/compare-docx.php storage/import/story.doc.docx --title "Exact story title"
 * Writes storage/reports/docx-compare-<file name>.md: for each piece, whether it matches a story and every difference,
 * sorted into "spelling or punctuation only" and "wording changed" so you can see where the Word file was edited.
 */
require dirname(__DIR__) . '/vendor/autoload.php';

use App\Import\DocxImporter;
use App\Import\Spelling;
use App\Support\Db;
use App\Support\Env;

$args = array_slice($argv, 1);
$file = $args[0] ?? '';
$title = (($i = array_search('--title', $args, true)) !== false) ? (string) ($args[$i + 1] ?? '') : '';
if ($file === '' || !is_readable($file) || (!in_array('--split', $args, true) && $title === '')) {
    fwrite(STDERR, "Usage: php bin/compare-docx.php file.docx --split | --title \"Story title\"\n");
    exit(1);
}
$paras = DocxImporter::paragraphs($file);
$pieces = in_array('--split', $args, true) ? DocxImporter::splitByTitles($paras) : [['title' => $title, 'html' => DocxImporter::toHtml($paras)]];

$stories = [];
foreach (Db::pdo()->query('SELECT id, title, status, body_html FROM stories') as $s) {
    $stories[DocxImporter::normalizeTitle($s['title'])][] = $s;
}
$probe = fn(string $html) => implode(' ', array_slice(preg_split('/\s+/u', preg_replace('/[^a-z0-9 ]+/', '', mb_strtolower(Spelling::visibleText($html))), -1, PREG_SPLIT_NO_EMPTY) ?: [], 0, 10));
$allStories = Db::pdo()->query('SELECT id, title, status, body_html FROM stories')->fetchAll();
$tokens = fn(string $html) => preg_split('/\s+/u', Spelling::visibleText($html), -1, PREG_SPLIT_NO_EMPTY) ?: [];
$wordKey = fn(string $t) => mb_strtolower(preg_replace('/[^\p{L}\p{N}]+/u', '', $t) ?? '');

$report = '';
$summary = ['identical' => 0, 'identical_text' => 0, 'title_differs' => 0, 'spelling_only' => 0, 'wording_changed' => 0, 'no_match' => 0];
$unmatched = [];
foreach ($pieces as $p) {
    $key = DocxImporter::normalizeTitle($p['title']);
    $match = $stories[$key][0] ?? null;
    $byOpening = false;
    if (!$match && trim($p['html']) !== '') {
        // Titles may have been changed in the Word file: fall back to the first ten words of the text.
        $needle = $probe($p['html']);
        foreach ($allStories as $cand) {
            if ($needle !== '' && $probe($cand['body_html']) === $needle) { $match = $cand; $byOpening = true; break; }
        }
    }
    if (!$match) { $summary['no_match']++; $unmatched[] = $p['title']; continue; }
    $blog = $tokens($match['body_html']);
    $word = $tokens($p['html']);
    $diffs = diffWords($blog, $word);
    $titleNote = $byOpening ? "\n**Title differs.** Blog: “{$match['title']}” · Word file: “{$p['title']}”\n" : '';
    if ($byOpening) { $summary['title_differs']++; }
    if (!$diffs && !$byOpening) { $summary['identical']++; continue; }
    if (!$diffs) { $summary['identical_text']++; $report .= "\n## {$match['title']}\n$titleNote\nText identical.\n"; continue; }
    $spell = $wording = [];
    foreach ($diffs as $d) {
        $a = implode(' ', $d['blog']);
        $b = implode(' ', $d['word']);
        // Same letters apart from case/punctuation, or one small edit per word = a spelling or typography change.
        $small = count($d['blog']) === count($d['word']) && count($d['blog']) > 0
            && !array_filter(array_keys($d['blog']), fn($k) => levenshtein($wordKey($d['blog'][$k]), $wordKey($d['word'][$k])) > 2);
        $line = sprintf("- “%s” → “%s”  <sub>…%s…</sub>", $a === '' ? '(nothing)' : $a, $b === '' ? '(removed)' : $b, implode(' ', $d['ctx']));
        if ($small) { $spell[] = $line; } else { $wording[] = $line; }
    }
    $summary[$wording ? 'wording_changed' : 'spelling_only']++;
    $report .= "\n## {$match['title']}\n$titleNote\nBlog: " . count($blog) . " words · Word file: " . count($word) . " words\n";
    if ($wording) { $report .= "\n**Wording changed (" . count($wording) . ")**\n\n" . implode("\n", $wording) . "\n"; }
    if ($spell) { $report .= "\n**Spelling or punctuation (" . count($spell) . ")**\n\n" . implode("\n", $spell) . "\n"; }
}
$head = "# Word file vs. blog\n\nFile: " . basename($file) . "\nNothing has been changed. Left of the arrow is the BLOG text, right is the WORD file.\n\n"
    . "| Result | Pieces |\n|---|---|\n| Identical (title and text) | {$summary['identical']} |\n| Text identical but title differs | {$summary['identical_text']} |\n| Title differs (any text result) | {$summary['title_differs']} |\n| Spelling or punctuation differences only | {$summary['spelling_only']} |\n| Wording differs | {$summary['wording_changed']} |\n| No story with that title in the database | {$summary['no_match']} |\n";
if ($unmatched) { $head .= "\nNo match for: " . implode('; ', $unmatched) . "\n"; }
@mkdir(Env::root() . '/storage/reports', 0775, true);
$out = '/storage/reports/docx-compare-' . preg_replace('/[^a-z0-9]+/i', '-', pathinfo($file, PATHINFO_FILENAME)) . '.md';
file_put_contents(Env::root() . $out, $head . $report);
echo json_encode($summary), "\nReport: " . ltrim($out, '/') . "\n";

/**
 * Word-level diff using the system `diff` (present on DreamHost and Linux/macOS). Compares letters only, ignoring case/punctuation.
 * @param list<string> $a blog tokens @param list<string> $b word tokens
 * @return list<array{blog:list<string>,word:list<string>,ctx:list<string>}>
 */
function diffWords(array $a, array $b): array
{
    global $wordKey;
    $dir = sys_get_temp_dir();
    $fa = tempnam($dir, 'pa');
    $fb = tempnam($dir, 'pb');
    file_put_contents($fa, implode("\n", array_map(fn($t) => $wordKey($t) ?: '·', $a)) . "\n");
    file_put_contents($fb, implode("\n", array_map(fn($t) => $wordKey($t) ?: '·', $b)) . "\n");
    $out = (string) shell_exec('diff ' . escapeshellarg($fa) . ' ' . escapeshellarg($fb));
    unlink($fa);
    unlink($fb);
    $res = [];
    foreach (explode("\n", $out) as $l) {
        if (!preg_match('/^(\d+)(?:,(\d+))?([acd])(\d+)(?:,(\d+))?$/', $l, $m)) { continue; }
        $as = (int) $m[1]; $ae = (int) (($m[2] ?? '') ?: $m[1]); $bs = (int) $m[4]; $be = (int) (($m[5] ?? '') ?: $m[4]);
        $blog = $m[3] === 'a' ? [] : array_slice($a, $as - 1, $ae - $as + 1);
        $word = $m[3] === 'd' ? [] : array_slice($b, $bs - 1, $be - $bs + 1);
        $at = $m[3] === 'a' ? $as : $as - 1;
        $res[] = ['blog' => $blog, 'word' => $word, 'ctx' => array_merge(array_slice($a, max(0, $at - 4), 4), ['▸'], array_slice($a, $at + count($blog), 3))];
    }
    return $res;
}
