<?php
declare(strict_types=1);

/**
 * Applies the corrections YOU approved in docs/spelling-fixes.tsv. Shows what it would do unless you add --write.
 *   php bin/apply-spelling.php            preview only
 *   php bin/apply-spelling.php --write    make the changes (each changed story keeps its old text as an earlier version)
 *
 * docs/spelling-fixes.tsv: one fix per line, columns separated by a TAB:
 *   wrong<TAB>right                    fixes the whole word everywhere (case must match)
 *   wrong<TAB>right<TAB>story-slug     fixes it in that story only
 * Lines starting with # are ignored. Only visible text is touched, never HTML tags, links or image addresses.
 */
require dirname(__DIR__) . '/vendor/autoload.php';

use App\Import\HtmlSanitizer;
use App\Support\Db;
use App\Support\Env;

$write = in_array('--write', $argv, true);
$file = Env::root() . '/docs/spelling-fixes.tsv';
if (!is_readable($file)) {
    fwrite(STDERR, "Create docs/spelling-fixes.tsv first (see the comment at the top of this script).\n");
    exit(1);
}
$fixes = [];
foreach (file($file, FILE_IGNORE_NEW_LINES) as $n => $line) {
    if (trim($line) === '' || $line[0] === '#') { continue; }
    $c = explode("\t", $line);
    if (count($c) < 2 || trim($c[0]) === '' || trim($c[0]) === trim($c[1])) {
        fwrite(STDERR, 'Line ' . ($n + 1) . ": needs 'wrong<TAB>right'. Skipped.\n");
        continue;
    }
    $fixes[] = ['wrong' => trim($c[0]), 'right' => trim($c[1]), 'slug' => trim($c[2] ?? '')];
}
$pdo = Db::pdo();
$changed = 0;
$total = 0;
foreach ($pdo->query('SELECT id, slug, title, body_html FROM stories ORDER BY id')->fetchAll() as $s) {
    $doc = HtmlSanitizer::load($s['body_html']);
    $root = $doc->getElementById('root');
    $count = 0;
    $apply = function (DOMNode $node) use (&$apply, $fixes, $s, &$count): void {
        foreach (iterator_to_array($node->childNodes) as $c) {
            if ($c instanceof DOMText) {
                $t = $c->nodeValue;
                foreach ($fixes as $f) {
                    if ($f['slug'] !== '' && $f['slug'] !== $s['slug']) { continue; }
                    $t = preg_replace_callback('/(?<![\p{L}\p{M}\'\x{2019}-])' . preg_quote($f['wrong'], '/') . '(?![\p{L}\p{M}\'\x{2019}-])/u', function () use ($f, &$count) { $count++; return $f['right']; }, $t) ?? $t;
                }
                if ($t !== $c->nodeValue) { $c->nodeValue = $t; }
            } elseif ($c instanceof DOMElement) {
                $apply($c);
            }
        }
    };
    $apply($root);
    if ($count === 0) { continue; }
    $new = '';
    foreach ($root->childNodes as $c) { $new .= $doc->saveHTML($c); }
    $changed++;
    $total += $count;
    printf("%3d fix%s in “%s”\n", $count, $count === 1 ? '' : 'es', $s['title']);
    if ($write) {
        $now = Db::utcNow();
        $pdo->prepare('INSERT INTO story_revisions (story_id, title, body_html, saved_at) VALUES (?,?,?,?)')->execute([$s['id'], $s['title'], $s['body_html'], $now]);
        $pdo->prepare('UPDATE stories SET body_html = ?, updated_at = ? WHERE id = ?')->execute([trim($new), $now, $s['id']]);
    }
}
echo $write ? "Done: $total fixes in $changed stories. Old text is kept under Earlier versions in the CMS.\n" : "Preview only: $total fixes in $changed stories. Run again with --write to apply.\n";
