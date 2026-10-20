<?php
declare(strict_types=1);

/**
 * Word documents are NOT the master copy; the blog is. By default this only lists what it would do.
 *
 *   php bin/import-docx.php confessions.docx --split                  preview: which pieces match a story, which are new
 *   php bin/import-docx.php story.doc.docx --title "The Other Man"    preview for a single story
 *   --add-missing      add pieces with no matching story as DRAFTS (never touches existing stories)
 *   --replace-text     ALSO replace the text of matching stories with the Word text (the old text is kept as an earlier version)
 * To see what differs first, run bin/compare-docx.php.
 */
require dirname(__DIR__) . '/vendor/autoload.php';

use App\Import\DocxImporter;
use App\Support\Db;

$args = array_slice($argv, 1);
$addMissing = in_array('--add-missing', $args, true);
$replace = in_array('--replace-text', $args, true);
$dry = !$addMissing && !$replace;
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
$pdo = Db::pdo();
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
    if ($existing && !$replace) {
        $stats['unchanged']++;
        printf("= %s (matches an existing story; left alone)\n", $t);
        continue;
    }
    if ($existing) {
        if (trim($existing['body_html']) === trim($p['html'])) { $stats['unchanged']++; printf("= %s\n", $t); continue; }
        $pdo->prepare('INSERT INTO story_revisions (story_id, title, body_html, saved_at) VALUES (?,?,?,?)')->execute([$existing['id'], $existing['title'], $existing['body_html'], $now]);
        $pdo->prepare('UPDATE stories SET body_html = ?, word_count = ?, updated_at = ? WHERE id = ?')->execute([$p['html'], $words, $now, $existing['id']]);
        $stats['replaced']++;
        printf("~ %s (%d words, text replaced)\n", $t, $words);
    } else {
        $stats['added_as_draft']++;
        printf("+ %s (%d words, no matching story)\n", $t, $words);
        if ($pdo && $addMissing) {
            $slug = slugify($t);
            $c = $slug;
            for ($k = 2; $pdo->query('SELECT COUNT(*) FROM stories WHERE slug = ' . $pdo->quote($c))->fetchColumn(); $k++) { $c = "$slug-$k"; }
            $pdo->prepare('INSERT INTO stories (slug, title, body_html, status, published_at, word_count, source, source_id, created_at, updated_at) VALUES (?,?,?,"draft",NULL,?,"docx",?,?,?)')
                ->execute([$c, $t, $p['html'], $words, $src . '#' . ($n + 1), $now, $now]);
        }
    }
}
echo ($dry ? "Preview only: nothing was written. Add --add-missing and/or --replace-text to act.\n" : "Done.\n"), json_encode($stats), "\n";
