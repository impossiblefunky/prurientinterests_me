<?php
declare(strict_types=1);

/**
 * Brings the spell-checked Word documents in. A piece whose title matches a story already imported from Blogger
 * replaces that story's text (the old text is kept as an earlier version); otherwise it is added as a DRAFT.
 *
 *   php bin/import-docx.php confessions.docx --split                (a collection; every title line starts a piece)
 *   php bin/import-docx.php story.doc.docx --title "The Other Man"  (a single story)
 *   add --dry-run to see what would happen without changing anything
 */
require dirname(__DIR__) . '/vendor/autoload.php';

use App\Import\DocxImporter;
use App\Support\Db;

$args = array_slice($argv, 1);
$dry = in_array('--dry-run', $args, true);
$split = in_array('--split', $args, true);
$title = '';
if (($i = array_search('--title', $args, true)) !== false) { $title = (string) ($args[$i + 1] ?? ''); }
$file = $args[0] ?? '';
if ($file === '' || str_starts_with($file, '--') || !is_readable($file)) {
    fwrite(STDERR, "Usage: php bin/import-docx.php file.docx [--split | --title \"Title\"] [--dry-run]\n");
    exit(1);
}
$paras = DocxImporter::paragraphs($file);
if ($split) {
    $pieces = DocxImporter::splitByTitles($paras);
} else {
    if ($title === '') { fwrite(STDERR, "Single stories need --title \"...\".\n"); exit(1); }
    $pieces = [['title' => $title, 'html' => DocxImporter::toHtml($paras)]];
}
$pdo = $dry ? null : Db::pdo();
$src = basename($file);
$stats = ['replaced' => 0, 'added_as_draft' => 0, 'unchanged' => 0];
$now = Db::utcNow();

foreach ($pieces as $n => $p) {
    $t = $p['title'] !== '' ? $p['title'] : "Untitled piece " . ($n + 1);
    $words = word_count($p['html']);
    $key = DocxImporter::normalizeTitle($t);
    $existing = null;
    if ($pdo) {
        foreach ($pdo->query('SELECT id, title, body_html FROM stories') as $row) {
            if (DocxImporter::normalizeTitle($row['title']) === $key) { $existing = $row; break; }
        }
    }
    if ($existing) {
        if (trim($existing['body_html']) === trim($p['html'])) { $stats['unchanged']++; printf("= %s\n", $t); continue; }
        $pdo->prepare('INSERT INTO story_revisions (story_id, title, body_html, saved_at) VALUES (?,?,?,?)')->execute([$existing['id'], $existing['title'], $existing['body_html'], $now]);
        $pdo->prepare('UPDATE stories SET body_html = ?, word_count = ?, updated_at = ? WHERE id = ?')->execute([$p['html'], $words, $now, $existing['id']]);
        $stats['replaced']++;
        printf("~ %s (%d words, text replaced)\n", $t, $words);
    } else {
        $stats['added_as_draft']++;
        printf("+ %s (%d words, new draft)\n", $t, $words);
        if ($pdo) {
            $slug = slugify($t);
            $c = $slug;
            for ($k = 2; $pdo->query('SELECT COUNT(*) FROM stories WHERE slug = ' . $pdo->quote($c))->fetchColumn(); $k++) { $c = "$slug-$k"; }
            $pdo->prepare('INSERT INTO stories (slug, title, body_html, status, published_at, word_count, source, source_id, created_at, updated_at) VALUES (?,?,?,"draft",NULL,?,"docx",?,?,?)')
                ->execute([$c, $t, $p['html'], $words, $src . '#' . ($n + 1), $now, $now]);
        }
    }
}
echo ($dry ? "Dry run: nothing was written.\n" : "Done.\n"), json_encode($stats), "\n";
