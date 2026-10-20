<?php
declare(strict_types=1);

namespace App\Import;

use App\Support\Db;
use DateTimeImmutable;
use DateTimeZone;
use PDO;

/**
 * Imports a Blogger Takeout feed.atom: live posts become published stories (exact old path kept), drafts stay drafts,
 * pages become pages, comments go to a private archive. Raw source HTML is kept in source_html. Safe to re-run.
 */
final class BloggerImporter
{
    /** @var array<string,int> */
    public array $counts = ['stories' => 0, 'drafts' => 0, 'pages' => 0, 'books' => 0, 'comments' => 0, 'skipped' => 0, 'external_images' => 0, 'internal_links' => 0];

    /** Blogger pages that are really books: they become entries on the Books page (slug => display order). */
    private const BOOK_PAGES = ['freedom-is-slavery' => 1, 'life-on-my-knees' => 2];

    private PDO $pdo;

    public function __construct(private bool $dryRun = false)
    {
        $this->pdo = Db::pdo();
    }

    public function run(string $feedFile): void
    {
        $storyIdByAtom = [];
        $comments = [];
        foreach (BloggerFeed::entries($feedFile) as $e) {
            if ($e['trashed'] !== '') { $this->counts['skipped']++; continue; }
            switch ($e['kind']) {
                case 'POST':
                    $id = $this->story($e);
                    if ($id !== null) { $storyIdByAtom[$e['id']] = $id; }
                    break;
                case 'PAGE':
                    $this->page($e);
                    break;
                case 'COMMENT':
                    $comments[] = $e;
                    break;
                default:
                    $this->counts['skipped']++;
            }
        }
        foreach ($comments as $c) {
            if (in_array($c['status'], ['SPAM_COMMENT', 'GHOSTED_COMMENT'], true)) { $this->counts['skipped']++; continue; }
            if ($this->dryRun) { $this->counts['comments']++; continue; }
            // A comment's post is named by blogger:parent (blogger:inReplyTo is only set for replies to other comments).
            $this->pdo->prepare('INSERT INTO comments_archive (story_id, author, body_html, status, commented_at, source_id) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE story_id = VALUES(story_id), author = VALUES(author), body_html = VALUES(body_html), commented_at = VALUES(commented_at)')
                ->execute([$storyIdByAtom[$c['parent']] ?? null, $c['author'], HtmlSanitizer::clean($c['content']), $c['status'], $this->utc($c['published']), $c['id']]);
            $this->counts['comments']++;
        }
    }

    /** @param array<string,mixed> $e */
    private function story(array $e): ?int
    {
        $isLive = $e['status'] === 'LIVE';
        $isDraft = $e['status'] === 'DRAFT';
        if (!$isLive && !$isDraft) { $this->counts['skipped']++; return null; }
        $body = $this->internalLinks(self::paragraphize(HtmlSanitizer::clean($e['content'])));
        $this->counts['external_images'] += preg_match_all('/<img\b[^>]*\bsrc="https?:/i', $body);
        $legacy = $isLive && preg_match('#^/\d{4}/\d{2}/[^/]+\.html$#', $e['filename']) ? $e['filename'] : null;
        $title = trim($e['title']) !== '' ? trim($e['title']) : 'Untitled';
        $slug = $legacy ? preg_replace('/\.html$/', '', basename($legacy)) : slugify($title);
        $now = Db::utcNow();
        $words = word_count($body);
        $published = $this->utc($e['published']) ?? $now;
        $isLive ? $this->counts['stories']++ : $this->counts['drafts']++;
        if ($this->dryRun) { return null; }

        $st = $this->pdo->prepare('SELECT id FROM stories WHERE source = "blogger" AND source_id = ?');
        $st->execute([$e['id']]);
        $id = $st->fetchColumn();
        $kind = self::kindOf($e['labels'], $words, $body);
        $this->counts['kind_' . $kind] = ($this->counts['kind_' . $kind] ?? 0) + 1;
        $vals = [$title, $body, $isLive ? 'published' : 'draft', $published, $e['author'], $words, $legacy, trim($e['meta']), $e['content'], $this->utc($e['updated']) ?? $now, $kind];
        if ($id) {
            $this->pdo->prepare('UPDATE stories SET title=?, body_html=?, status=?, published_at=?, author=?, word_count=?, legacy_path=?, summary=?, source_html=?, updated_at=?, kind=? WHERE id=?')->execute([...$vals, $id]);
        } else {
            $this->pdo->prepare('INSERT INTO stories (title, body_html, status, published_at, author, word_count, legacy_path, summary, source_html, updated_at, kind, slug, source, source_id, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,"blogger",?,?)')
                ->execute([...$vals, $this->uniqueSlug('stories', $slug), $e['id'], $now]);
            $id = $this->pdo->lastInsertId();
        }
        $this->labels((int) $id, $e['labels']);
        return (int) $id;
    }

    /** @param array<string,mixed> $e */
    private function page(array $e): void
    {
        if ($e['status'] !== 'LIVE' && $e['status'] !== 'DRAFT') { $this->counts['skipped']++; return; }
        $legacy = preg_match('#^/p/[^/]+\.html$#', $e['filename']) ? $e['filename'] : null;
        $slug = $legacy ? preg_replace('/\.html$/', '', basename($legacy)) : slugify($e['title']);
        if (isset(self::BOOK_PAGES[$slug])) {
            $this->counts['books']++;
            if (!$this->dryRun) {
                $this->bookFromPage($e, $slug, $legacy);
            }
            return;
        }
        $this->counts['pages']++;
        if ($this->dryRun) { return; }
        $now = Db::utcNow();
        $body = $this->internalLinks(self::paragraphize(HtmlSanitizer::clean($e['content'])));
        $st = $this->pdo->prepare('SELECT id FROM pages WHERE legacy_path <=> ? OR slug = ?');
        $st->execute([$legacy, $slug]);
        if ($id = $st->fetchColumn()) {
            $this->pdo->prepare('UPDATE pages SET title=?, body_html=?, source_html=?, updated_at=? WHERE id=?')->execute([$e['title'], $body, $e['content'], $now, $id]);
        } else {
            $this->pdo->prepare('INSERT INTO pages (slug, legacy_path, title, body_html, status, source_html, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?)')
                ->execute([$slug, $legacy, $e['title'], $body, $e['status'] === 'LIVE' ? 'published' : 'draft', $e['content'], $now, $now]);
        }
    }

    /**
     * Turns a Blogger book page into an entry on the Books page: the buy links become editions (Paperback, eBook),
     * the ISBN is read from the spec table, and the old /p/ address redirects to /books. An existing book is never overwritten.
     * @param array<string,mixed> $e
     */
    private function bookFromPage(array $e, string $slug, ?string $legacy): void
    {
        $now = Db::utcNow();
        $body = $this->internalLinks(self::paragraphize(HtmlSanitizer::clean($e['content'])));
        $editions = [];
        $body = preg_replace_callback('#<a href="([^"]+)"[^>]*>\s*<strong>\s*(Buy[^<]*)</strong>\s*</a>#i', function (array $m) use (&$editions): string {
            $name = stripos($m[2], 'ebook') !== false ? 'eBook' : 'Paperback';
            $editions[$name] ??= html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            return '';
        }, $body) ?? $body;
        $body = trim(preg_replace('#(?:<p>\s*</p>\s*)+#', '', $body) ?? $body);
        $isbn = preg_match('#ISBN:</td>\s*<td>\s*([0-9Xx\- ]{10,20})\s*</td>#', $body, $im) ? trim($im[1]) : '';
        $exists = $this->pdo->prepare('SELECT id FROM books WHERE slug = ?');
        $exists->execute([$slug]);
        if (!$exists->fetchColumn()) {
            $this->pdo->prepare('INSERT INTO books (slug, title, description_html, status, sort_order, isbn, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?)')
                ->execute([$slug, trim($e['title']), $body, $e['status'] === 'LIVE' ? 'published' : 'draft', self::BOOK_PAGES[$slug], $isbn, $now, $now]);
            $bookId = (int) $this->pdo->lastInsertId();
            $order = 0;
            foreach ($editions as $name => $url) {
                $this->pdo->prepare('INSERT INTO book_editions (book_id, name, purchase_url, isbn, sort_order) VALUES (?,?,?,?,?)')
                    ->execute([$bookId, $name, $url, $name === 'Paperback' ? $isbn : '', $order++]);
            }
        }
        if ($legacy) {
            $this->pdo->prepare('INSERT INTO redirects (from_path, to_url, code, created_at) VALUES (?,?,301,?) ON DUPLICATE KEY UPDATE to_url = VALUES(to_url)')
                ->execute([$legacy, '/books#' . $slug, $now]);
        }
    }

    /** Links to this blog's own posts, pages and labels at the old addresses become links within the new site. */
    public function internalLinks(string $html): string
    {
        return preg_replace_callback('#href="https?://(?:www\.)?(?:pornoperson\.blogspot\.com|pornoperson\.com|prurientinterests\.me)(/[^"]*)?"#i', function (array $m): string {
            $path = preg_replace('/[?#].*$/', '', html_entity_decode($m[1] ?? '/', ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '/';
            if (preg_match('#^/\d{4}/\d{2}/[^/]+\.html$#', $path) || preg_match('#^/p/[^/]+\.html$#', $path)) {
                $this->counts['internal_links']++;
                return 'href="' . $path . '"';
            }
            if (preg_match('#^/search/label/([^/]+)#', $path, $l)) {
                $this->counts['internal_links']++;
                return 'href="/tag/' . slugify(rawurldecode($l[1])) . '"';
            }
            if ($path === '/' || $path === '') {
                $this->counts['internal_links']++;
                return 'href="/"';
            }
            return $m[0];
        }, $html) ?? $html;
    }

    /** @param list<string> $labels */
    private function labels(int $storyId, array $labels): void
    {
        $this->pdo->prepare('DELETE FROM item_tags WHERE item_type = "story" AND item_id = ?')->execute([$storyId]);
        foreach ($labels as $label) {
            $slug = slugify($label);
            $name = self::tagName($label);
            $st = $this->pdo->prepare('SELECT id FROM tags WHERE slug = ?');
            $st->execute([$slug]);
            $tagId = $st->fetchColumn();
            if (!$tagId) {
                $this->pdo->prepare('INSERT INTO tags (slug, name, created_at) VALUES (?,?,?)')->execute([$slug, $name, Db::utcNow()]);
                $tagId = $this->pdo->lastInsertId();
            }
            $this->pdo->prepare('INSERT IGNORE INTO item_tags (tag_id, item_type, item_id) VALUES (?,?,?)')->execute([$tagId, 'story', $storyId]);
        }
    }

    /**
     * Guesses a post's type from its Blogger labels and length: written pieces are 'story' (tagged story) or 'post';
     * mostly-pictures posts are 'picture'; YouTube posts are 'video'. Mike can change any of them in the CMS.
     * @param list<string> $labels
     */
    public static function kindOf(array $labels, int $words, string $body): string
    {
        $l = array_map('mb_strtolower', $labels);
        if (in_array('story', $l, true) && $words >= 150) { return 'story'; }
        if (in_array('youtube', $l, true) && $words < 150) { return 'video'; }
        if ($words < 60 && preg_match('/<img\b/i', $body)) { return 'picture'; }
        return $words >= 400 ? 'story' : 'post';
    }

    /** Blogger labels are often all lower case; show them capitalised, keeping labels that already have capitals. */
    public static function tagName(string $label): string
    {
        $label = trim($label);
        if ($label !== mb_strtolower($label)) {
            return $label;
        }
        $special = ['m2m' => 'M2M', 'bdsm' => 'BDSM', 'bdsm terms' => 'BDSM terms'];
        return $special[$label] ?? mb_strtoupper(mb_substr($label, 0, 1)) . mb_substr($label, 1);
    }

    /** Blogger separates paragraphs with <br><br>; turn that into real <p> elements. */
    public static function paragraphize(string $html): string
    {
        if (preg_match('/<p[\s>]/i', $html)) {
            return $html;
        }
        $parts = preg_split('/(?:<br\s*\/?>\s*){2,}/i', $html) ?: [$html];
        $out = '';
        foreach ($parts as $p) {
            $p = trim($p);
            if ($p === '') { continue; }
            $out .= preg_match('/^<(h[1-6]|ul|ol|blockquote|table|hr)\b/i', $p) ? $p . "\n" : "<p>$p</p>\n";
        }
        return $out;
    }

    private function utc(string $s): ?string
    {
        if ($s === '') { return null; }
        try {
            return (new DateTimeImmutable($s))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        } catch (\Exception) {
            return null;
        }
    }

    private function uniqueSlug(string $table, string $slug): string
    {
        $c = $slug;
        for ($i = 2; $i < 500; $i++) {
            $st = $this->pdo->prepare("SELECT COUNT(*) FROM $table WHERE slug = ?");
            $st->execute([$c]);
            if (!(int) $st->fetchColumn()) { return $c; }
            $c = "$slug-$i";
        }
        return $slug . '-' . bin2hex(random_bytes(3));
    }
}
