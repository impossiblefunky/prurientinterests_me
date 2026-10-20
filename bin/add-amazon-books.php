<?php
declare(strict_types=1);

/**
 * Adds the Kindle titles listed on Louis Friend's Amazon author page as books (safe to run again).
 * Only the titles are filled in: no covers, descriptions, prices or product links are invented.
 * Each Kindle edition's button points to the author page; replace it with the exact product link in the CMS (Editions).
 */
require dirname(__DIR__) . '/vendor/autoload.php';

use App\Support\Db;

const AUTHOR_PAGE = 'https://www.amazon.com/stores/Louis-Friend/author/B007IJ8WAQ/allbooks';
$titles = ['Meeting Max', 'The Girl in the Mirror', 'The Other Man', 'Dollar Store Domme', 'Doing the Chores', 'Class Reunion', 'Satin Met a Laddie', 'Francesca', "Doctor's Orders"];

$pdo = Db::pdo();
$find = $pdo->prepare('SELECT id FROM books WHERE slug = ?');
$add = $pdo->prepare("INSERT INTO books (slug, title, description_html, status, sort_order, created_at, updated_at) VALUES (?, ?, '', 'published', ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())");
$ed = $pdo->prepare("INSERT INTO book_editions (book_id, name, purchase_url, sort_order) VALUES (?, 'Kindle Edition', ?, 0)");
$added = 0;
foreach ($titles as $i => $t) {
    $slug = slugify($t);
    $find->execute([$slug]);
    if ($find->fetchColumn()) {
        continue;
    }
    $add->execute([$slug, $t, 10 + $i]);
    $ed->execute([(int) $pdo->lastInsertId(), AUTHOR_PAGE]);
    $added++;
}

// Kindle links Mike supplied for the two books that already exist (kept exactly as given).
$kindle = ['freedom-is-slavery' => 'https://amzn.to/4eHrV3P', 'life-on-my-knees' => 'https://amzn.to/4enhSRn'];
$has = $pdo->prepare('SELECT 1 FROM book_editions WHERE book_id = ? AND purchase_url = ?');
$next = $pdo->prepare('SELECT COALESCE(MAX(sort_order), -1) + 1 FROM book_editions WHERE book_id = ?');
$put = $pdo->prepare("INSERT INTO book_editions (book_id, name, purchase_url, sort_order) VALUES (?, 'Kindle Edition', ?, ?)");
$linked = 0;
foreach ($kindle as $slug => $url) {
    $find->execute([$slug]);
    $id = (int) $find->fetchColumn();
    if (!$id) {
        continue;
    }
    $has->execute([$id, $url]);
    if (!$has->fetchColumn()) {
        // the old Blogger "eBook" button already points at the Amazon Kindle book: update it rather than list it twice
        $swap = $pdo->prepare("UPDATE book_editions SET name = 'Kindle Edition', purchase_url = ? WHERE book_id = ? AND name = 'eBook' AND purchase_url LIKE '%amazon.com%'");
        $swap->execute([$url, $id]);
        if ($swap->rowCount()) {
            $linked++;
            continue;
        }
        $next->execute([$id]);
        $put->execute([$id, $url, (int) $next->fetchColumn()]);
        $linked++;
    }
}
echo "$added books added, $linked Kindle links added.\n";
