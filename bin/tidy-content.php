<?php
declare(strict_types=1);

/**
 * One-time tidy-up approved by Mike (2026-10-20). Safe to run again.
 *  - Contact page: the "pornoperson at gmail dot com" line becomes a real mailto link.
 *  - Tags: proper capitalisation (eLust, FetLife, CineKink) and "BDSM terms" merged into "Terminology",
 *    which is renamed "BDSM terminology". Old tag addresses keep working (alias redirect).
 */
require dirname(__DIR__) . '/vendor/autoload.php';

use App\Support\Db;

$pdo = Db::pdo();

$n = $pdo->prepare("UPDATE pages SET body_html = REPLACE(body_html, 'Email me at pornoperson at gmail dot com', 'Email me at <a href=\"mailto:pornoperson@gmail.com\">pornoperson@gmail.com</a>'), updated_at = UTC_TIMESTAMP() WHERE slug = 'contact' AND body_html LIKE '%pornoperson at gmail dot com%'");
$n->execute();
echo "Contact page updated: " . $n->rowCount() . "\n";

$rename = $pdo->prepare('UPDATE tags SET name = ? WHERE slug = ? AND BINARY name <> ?');
foreach (['elust' => 'eLust', 'fetlife' => 'FetLife', 'cinekink' => 'CineKink'] as $slug => $name) {
    $rename->execute([$name, $slug, $name]);
    echo "Tag $slug -> $name: " . $rename->rowCount() . "\n";
}

$id = fn(string $slug) => (int) $pdo->query('SELECT id FROM tags WHERE slug = ' . $pdo->quote($slug))->fetchColumn();
$from = $id('bdsm-terms');
$to = $id('terminology');
if ($from && $to) {
    $pdo->prepare('INSERT IGNORE INTO item_tags (tag_id, item_type, item_id) SELECT ?, item_type, item_id FROM item_tags WHERE tag_id = ?')->execute([$to, $from]);
    $pdo->prepare('DELETE FROM item_tags WHERE tag_id = ?')->execute([$from]);
    $pdo->prepare('INSERT IGNORE INTO tag_aliases (alias_slug, tag_id) VALUES (?, ?)')->execute(['bdsm-terms', $to]);
    $pdo->prepare('DELETE FROM tags WHERE id = ?')->execute([$from]);
    echo "Merged 'BDSM terms' into Terminology.\n";
}
if ($to) {
    $pdo->prepare("UPDATE tags SET name = 'BDSM terminology' WHERE id = ? AND name <> 'BDSM terminology'")->execute([$to]);
}

// Second round (Mike, 2026-10-20): "Golden" is Mistress Golden; label-type tags are hidden from the public Interests list;
// the very small tags (Kitty, Desires, Karma, Bully) are dropped. Stories keep everything else; old tag addresses go to /tags.
$pdo->prepare("UPDATE tags SET name = 'Mistress Golden' WHERE slug = 'golden' AND BINARY name <> 'Mistress Golden'")->execute();
foreach (['life', 'site', 'news', 'media', 'book', 'review'] as $slug) {
    $pdo->prepare('UPDATE tags SET hidden = 1 WHERE slug = ?')->execute([$slug]);
}
$redir = $pdo->prepare("INSERT IGNORE INTO redirects (from_path, to_url, code, created_at) VALUES (?, '/tags', 301, UTC_TIMESTAMP())");
foreach (['kitty', 'desires', 'karma', 'bully'] as $slug) {
    if ($pdo->prepare('DELETE FROM tags WHERE slug = ?')->execute([$slug])) {
        $redir->execute(['/tag/' . $slug]);
    }
}
echo "Tags tidied (renamed, hidden, dropped).\n";
// The Contact page no longer lists an email address (the form replaces it).
$row = $pdo->query("SELECT id, body_html FROM pages WHERE slug = 'contact'")->fetch();
$removed = 0;
if ($row && stripos($row['body_html'], 'Email me at') !== false) {
    $body = preg_replace('#<p>Email me at.*?</p>(\s*<br\s*/?>)?#is', '', $row['body_html']);
    $pdo->prepare('UPDATE pages SET body_html = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?')->execute([$body, $row['id']]);
    $removed = 1;
}
echo "Contact page email line removed: $removed\n";
// FetLife line becomes a real link (Mike's address).
$fl = $pdo->prepare("UPDATE pages SET body_html = REPLACE(body_html, 'Look for me on Fetlife.', 'Look for me on <a href=\"https://fetlife.com/pornoperson\">FetLife</a>.'), updated_at = UTC_TIMESTAMP() WHERE slug = 'contact' AND body_html LIKE '%Look for me on Fetlife.%'");
$fl->execute();
echo "FetLife link added: " . $fl->rowCount() . "\n";

echo "Done.\n";
