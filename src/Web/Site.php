<?php
declare(strict_types=1);

namespace App\Web;

use App\Admin\Admin;
use App\Support\Db;
use PDO;

/** Public site: adults-only gate, redirects, routes, SEO output. */
final class Site
{
    private const PER_PAGE = 12;
    private const GATE_COOKIE = 'pi_adult';
    /** Post types listed in the story library and on the homepage. Picture and video posts live elsewhere. */
    private const WRITTEN = "('story','post')";
    private const LIVE = 's.status = "published" AND s.published_at <= UTC_TIMESTAMP()';

    private Router $router;

    public function __construct()
    {
        $this->router = new Router();
        $r = $this->router;
        $r->get('/', fn() => $this->home());
        $r->get('/stories', fn() => $this->library());
        $r->get('/series/{slug}', fn(array $p) => $this->series($p['slug']));
        $r->get('/tags', fn() => $this->tags());
        $r->get('/random', fn() => $this->randomStory());
        $r->get('/tag/{slug}', fn(array $p) => $this->tag($p['slug']));
        $r->get('/search/label/{label}', fn(array $p) => $this->oldLabel($p['label']));
        $r->get('/story/{slug}', fn(array $p) => $this->story('slug', $p['slug']));
        $r->get('/gallery', fn() => $this->gallery());
        $r->get('/search', fn() => $this->search());
        $r->get('/books', fn() => $this->books());
        $r->get('/books/{slug}', fn(array $p) => $this->book($p['slug']));
        $r->get('/about', fn() => $this->page(['about', 'about-prurient-interests']));
        $r->get('/p/{file}', fn(array $p) => $this->legacyPage('/p/' . $p['file']));
        $r->get('/contact', fn() => $this->contactPage());
        $r->post('/contact', fn() => $this->contactSubmit());
        $r->post('/comment', fn() => $this->commentSubmit());
        $r->get('/feed', fn() => $this->feed());
        $r->get('/feeds/posts/default', fn() => $this->feed());   // the address old Blogger readers already subscribe to
        $r->get('/robots.txt', fn() => $this->robots());
        $r->get('/sitemap.xml', fn() => $this->sitemap());
        $r->post('/enter', fn() => $this->enter());
        $r->get('/leave', fn() => $this->leave());
    }

    public function handle(string $method, string $path): void
    {
        if ($path === '/admin' || str_starts_with($path, '/admin/')) {
            (new Admin())->handle($method, $path);
            return;
        }
        $path = $path === '/' ? '/' : rtrim($path, '/');
        if ($this->redirect($path)) {
            return;
        }
        // Old Blogger story URLs, e.g. /2012/12/forbidden-fruit.html
        if (preg_match('#^/\d{4}/\d{2}/[^/]+\.html$#', $path)) {
            $this->story('legacy_path', $path);
            return;
        }
        $m = $this->router->match($method, $path);
        if ($m === null) {
            $this->notFound();
            return;
        }
        ($m[0])($m[1]);
    }

    // ---- gate ---------------------------------------------------------------------------------------------

    private function gateOpen(): bool
    {
        return ($_COOKIE[self::GATE_COOKIE] ?? '') === '1';
    }


    private function gate(string $returnTo): void
    {
        http_response_code(200);
        header('X-Robots-Tag: noindex');
        $this->send('gate', ['return' => $returnTo], [
            'title' => 'Adults only | ' . setting('site_title'),
            'description' => 'This site contains explicit adult material. You must be 18 or older to enter.',
            'robots' => 'noindex,nofollow',
            'bare' => true,
        ]);
    }

    private function enter(): void
    {
        $return = (string) ($_POST['return'] ?? '/');
        if (!preg_match('#^/(?!/)#', $return)) {
            $return = '/';
        }
        if (($_POST['over18'] ?? '') !== '1') {
            $this->gate($return);
            return;
        }
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        // A session cookie: remembered until the browser is closed, as the design says.
        setcookie(self::GATE_COOKIE, '1', ['expires' => 0, 'path' => '/', 'secure' => $https, 'httponly' => true, 'samesite' => 'Lax']);
        header('Location: ' . $return, true, 303);
    }

    private function leave(): void
    {
        header('Location: https://www.google.com/', true, 302);
    }

    // ---- redirects ----------------------------------------------------------------------------------------

    private function redirect(string $path): bool
    {
        $st = Db::pdo()->prepare('SELECT id, to_url, code FROM redirects WHERE from_path = ?');
        $st->execute([$path]);
        $row = $st->fetch();
        if (!$row) {
            return false;
        }
        Db::pdo()->prepare('UPDATE redirects SET hits = hits + 1 WHERE id = ?')->execute([$row['id']]);
        header('Location: ' . $row['to_url'], true, (int) $row['code']);
        return true;
    }

    /** Old Blogger label addresses, /search/label/Fantasy, go to the matching tag page. */
    private function oldLabel(string $label): void
    {
        header('Location: /tag/' . rawurlencode(slugify(urldecode($label))), true, 301);
    }

    // ---- home ---------------------------------------------------------------------------------------------

    private function home(): void
    {
        $pdo = Db::pdo();
        // Three ways in: the newest story, one chosen from the older archive (changes daily), and a series.
        $day = (int) gmdate('z') + 366 * (int) gmdate('Y');
        $picks = [];
        $recent = $pdo->query($this->listSql() . " AND s.kind = 'story' ORDER BY s.published_at DESC LIMIT 1")->fetch();
        if ($recent) {
            $recent['label'] = 'Newest';
            $picks[] = $recent;
        }
        $skip = $recent ? (int) $recent['id'] : 0;
        $older = $pdo->query($this->listSql() . " AND s.kind = 'story' AND s.id <> $skip AND s.published_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 6 YEAR) AND s.word_count >= 1500 ORDER BY RAND($day) LIMIT 1")->fetch();
        if ($older) {
            $older['label'] = 'From the archive · ' . gmdate('Y', strtotime($older['published_at'] . ' UTC'));
            $picks[] = $older;
        }
        $have = implode(',', array_map(fn($p) => (int) $p['id'], $picks ?: [['id' => 0]]));
        $ser = $pdo->query($this->listSql() . " AND s.series_id IS NOT NULL AND s.id NOT IN ($have) ORDER BY RAND($day + 7) LIMIT 1")->fetch();
        if ($ser) {
            $ser['label'] = 'A series';
            $picks[] = $ser;
        }
        $stats = [
            'written' => (int) $pdo->query('SELECT COUNT(*) FROM stories s WHERE ' . self::LIVE . ' AND s.kind IN ' . self::WRITTEN)->fetchColumn(),
            'pictures' => (int) $pdo->query('SELECT COUNT(*) FROM gallery_items WHERE status = "published"')->fetchColumn(),
            'books' => (int) $pdo->query('SELECT COUNT(*) FROM books WHERE status = "published"')->fetchColumn(),
            'since' => (int) $pdo->query('SELECT MIN(YEAR(s.published_at)) FROM stories s WHERE ' . self::LIVE . ' AND s.kind IN ' . self::WRITTEN)->fetchColumn(),
        ];
        $yearCounts = $pdo->query('SELECT YEAR(s.published_at) y, COUNT(*) n FROM stories s WHERE ' . self::LIVE . ' AND s.kind IN ' . self::WRITTEN . ' GROUP BY y ORDER BY y DESC')->fetchAll();
        $tags = $pdo->query('SELECT t.slug, t.name FROM tags t WHERE t.featured = 1 AND t.hidden = 0 AND t.kind = "subject" ORDER BY t.sort_order, t.name LIMIT 12')->fetchAll();
        if (!$tags) {
            $tags = $pdo->query('SELECT t.slug, t.name, COUNT(*) n FROM tags t JOIN item_tags it ON it.tag_id = t.id AND it.item_type = "story" JOIN stories s ON s.id = it.item_id AND ' . self::LIVE . ' AND s.kind IN ' . self::WRITTEN . ' WHERE t.hidden = 0 AND t.kind = "subject" GROUP BY t.id ORDER BY n DESC LIMIT 8')->fetchAll();
        }
        $albums = $pdo->query('SELECT a.slug, a.title FROM albums a WHERE a.status = "published" AND EXISTS (SELECT 1 FROM gallery_items g WHERE g.album_id = a.id AND g.status = "published") ORDER BY a.featured DESC, a.sort_order, a.id LIMIT 3')->fetchAll();
        $book = $pdo->query('SELECT * FROM books WHERE status = "published" ORDER BY featured DESC, sort_order, id DESC LIMIT 1')->fetch() ?: null;
        $this->send('home', ['picks' => $picks, 'tags' => $tags, 'albums' => $albums, 'book' => $book, 'yearCounts' => $yearCounts, 'stats' => $stats], [
            'title' => setting('site_title') . ' | ' . setting('tagline'),
            'description' => setting('home_intro', setting('tagline')),
            'canonical' => site_url('/'),
            'jsonld' => [['@context' => 'https://schema.org', '@type' => 'WebSite', 'name' => setting('site_title'), 'url' => site_url('/'),
                'potentialAction' => ['@type' => 'SearchAction', 'target' => site_url('/search?q={search_term_string}'), 'query-input' => 'required name=search_term_string']]],
        ]);
    }

    // ---- library, series, interests ------------------------------------------------------------------------

    private function library(): void
    {
        $pdo = Db::pdo();
        $where = '';
        $args = [];
        $q = trim((string) ($_GET['q'] ?? ''));
        if ($q !== '') {
            $where .= ' AND (s.title LIKE ? OR s.summary LIKE ? OR s.body_html LIKE ?)';
            $like = '%' . addcslashes($q, '%_\\') . '%';
            array_push($args, $like, $like, $like);
        }
        $format = in_array($_GET['format'] ?? '', ['standalone', 'series'], true) ? $_GET['format'] : '';
        if ($format === 'standalone') {
            $where .= ' AND s.series_id IS NULL';
        } elseif ($format === 'series') {
            $where .= ' AND s.series_id IS NOT NULL';
        }
        $year = (int) ($_GET['year'] ?? 0);
        if ($year >= 1990 && $year <= 2100) {
            $where .= ' AND YEAR(s.published_at) = ?';
            $args[] = $year;
        }
        $sort = ($_GET['sort'] ?? 'new') === 'old' ? 'ASC' : 'DESC';
        $years = $pdo->query('SELECT DISTINCT YEAR(s.published_at) y FROM stories s WHERE ' . self::LIVE . ' AND s.kind IN ' . self::WRITTEN . ' ORDER BY y DESC')->fetchAll(PDO::FETCH_COLUMN);
        $pg = $this->paged($this->listSql() . $where, $args, "ORDER BY s.published_at $sort, s.id $sort");
        $cur = ['q' => $q, 'format' => $format, 'year' => $year ?: '', 'sort' => $sort === 'ASC' ? 'old' : ''];
        $this->send('library', $pg + ['q' => $q, 'format' => $format, 'year' => $year, 'years' => $years, 'sort' => $sort === 'ASC' ? 'old' : 'new', 'cur' => $cur, 'base' => '/stories', 'topTags' => $this->topTags()], [
            'title' => 'The story library' . ($pg['page'] > 1 ? " (page {$pg['page']})" : '') . ' | ' . setting('site_title'),
            'description' => 'Find a beginning. Return to a favorite. Follow a story through the years.',
            'canonical' => site_url('/stories' . ($pg['page'] > 1 ? '?page=' . $pg['page'] : '')),
            'robots' => ($q !== '' || $format !== '' || $year) ? 'noindex,follow' : null,
            'breadcrumbs' => [['Stories', '/stories']],
        ]);
    }

    private function series(string $slug): void
    {
        $pdo = Db::pdo();
        $st = $pdo->prepare('SELECT * FROM series WHERE slug = ?');
        $st->execute([$slug]);
        $se = $st->fetch();
        if (!$se) {
            $this->notFound();
            return;
        }
        $ch = $pdo->prepare('SELECT s.id, s.slug, s.legacy_path, s.title, s.summary, s.body_html, s.published_at, s.word_count, s.chapter_order FROM stories s WHERE s.series_id = ? AND ' . self::LIVE . ' ORDER BY s.chapter_order, s.id');
        $ch->execute([$se['id']]);
        $chapters = $ch->fetchAll();
        if (!$chapters) {
            $this->notFound();
            return;
        }
        $this->send('series', ['series' => $se, 'chapters' => $chapters], [
            'title' => $se['title'] . ' (series) | ' . setting('site_title'),
            'description' => $se['description'] !== '' ? $se['description'] : $se['title'] . ': ' . count($chapters) . ' chapters.',
            'canonical' => site_url('/series/' . $se['slug']),
            'breadcrumbs' => [['Stories', '/stories'], [$se['title'], '/series/' . $se['slug']]],
        ]);
    }

    /** "Let chance choose": sends the visitor to a random published story. */
    private function randomStory(): void
    {
        $row = Db::pdo()->query('SELECT s.slug, s.legacy_path, s.id FROM stories s WHERE ' . self::LIVE . " AND s.kind = 'story' AND s.word_count >= 300 ORDER BY RAND() LIMIT 1")->fetch();
        if (!$row) {
            $this->notFound();
            return;
        }
        header('Cache-Control: no-store');
        header('X-Robots-Tag: noindex');
        header('Location: ' . story_url($row), true, 302);
    }

    private function tags(): void
    {
        $pdo = Db::pdo();
        $rows = $pdo->query('SELECT t.slug, t.name, t.description, t.featured, t.kind,
            (SELECT COUNT(*) FROM item_tags it JOIN stories s ON s.id = it.item_id AND ' . self::LIVE . ' AND s.kind IN ' . self::WRITTEN . ' WHERE it.tag_id = t.id AND it.item_type = "story")
          + (SELECT COUNT(*) FROM item_tags it JOIN gallery_items g ON g.id = it.item_id AND g.status = "published" WHERE it.tag_id = t.id AND it.item_type = "gallery")
          + (SELECT COUNT(*) FROM item_tags it JOIN books b ON b.id = it.item_id AND b.status = "published" WHERE it.tag_id = t.id AND it.item_type = "book") AS n
            FROM tags t WHERE t.hidden = 0 ORDER BY t.name')->fetchAll();
        $rows = array_values(array_filter($rows, fn($r) => (int) $r['n'] > 0));
        $subjects = array_values(array_filter($rows, fn($r) => $r['kind'] === 'subject'));
        $others = array_values(array_filter($rows, fn($r) => $r['kind'] !== 'subject'));
        $featured = array_values(array_filter($subjects, fn($r) => $r['featured']));
        // Split the A-Z list of subjects into three balanced columns labelled by their first and last letters.
        $cols = array_chunk($subjects, max(1, (int) ceil(count($subjects) / 3)));
        $this->send('tags', ['featured' => $featured ?: array_slice($subjects, 0, 8), 'cols' => $cols, 'others' => $others], [
            'title' => 'Interests | ' . setting('site_title'),
            'description' => 'One tag can lead across years of writing, pictures, and books.',
            'canonical' => site_url('/tags'),
            'breadcrumbs' => [['Interests', '/tags']],
        ]);
    }

    private function tag(string $slug): void
    {
        $pdo = Db::pdo();
        $st = $pdo->prepare('SELECT * FROM tags WHERE slug = ?');
        $st->execute([$slug]);
        $tag = $st->fetch();
        if (!$tag) {
            $a = $pdo->prepare('SELECT t.slug FROM tag_aliases al JOIN tags t ON t.id = al.tag_id WHERE al.alias_slug = ?');
            $a->execute([$slug]);
            if ($to = $a->fetchColumn()) {
                header('Location: /tag/' . rawurlencode((string) $to), true, 301);
                return;
            }
            $this->notFound();
            return;
        }
        $count = function (string $sql) use ($pdo, $tag): int {
            $c = $pdo->prepare($sql);
            $c->execute([$tag['id']]);
            return (int) $c->fetchColumn();
        };
        $nStories = $count('SELECT COUNT(*) FROM stories s WHERE ' . self::LIVE . ' AND s.kind <> "picture" AND s.id IN (SELECT item_id FROM item_tags WHERE item_type = "story" AND tag_id = ?)');
        $imgSql = 'SELECT g.* FROM gallery_items g WHERE g.status = "published" AND g.id IN (SELECT item_id FROM item_tags WHERE item_type = "gallery" AND tag_id = ?) ORDER BY g.sort_order, g.id DESC LIMIT 60';
        $images = $pdo->prepare($imgSql);
        $images->execute([$tag['id']]);
        $images = $images->fetchAll();
        $books = $pdo->prepare('SELECT * FROM books WHERE status = "published" AND id IN (SELECT item_id FROM item_tags WHERE item_type = "book" AND tag_id = ?) ORDER BY sort_order, id DESC');
        $books->execute([$tag['id']]);
        $books = $books->fetchAll();

        $type = in_array($_GET['type'] ?? '', ['stories', 'images', 'books'], true) ? $_GET['type'] : 'everything';
        $pg = ['rows' => [], 'page' => 1, 'pages' => 1, 'total' => $nStories];
        if ($nStories && in_array($type, ['everything', 'stories'], true)) {
            $pg = $this->paged($this->listSql(true) . ' AND s.kind <> "picture" AND s.id IN (SELECT item_id FROM item_tags WHERE item_type = "story" AND tag_id = ' . (int) $tag['id'] . ')', [], 'ORDER BY s.published_at DESC, s.id DESC');
        }
        $this->send('tag', $pg + ['tag' => $tag, 'type' => $type, 'images' => $images, 'books' => $books, 'nStories' => $nStories, 'base' => '/tag/' . $tag['slug'], 'cur' => ['type' => $type === 'everything' ? '' : $type]], [
            'title' => $tag['name'] . ' | ' . setting('site_title'),
            'description' => $tag['description'] !== '' ? $tag['description'] : 'Writing, images, and books connected by the interest “' . $tag['name'] . '”.',
            'canonical' => site_url('/tag/' . $tag['slug'] . ($pg['page'] > 1 ? '?page=' . $pg['page'] : '')),
            'robots' => $type !== 'everything' ? 'noindex,follow' : null,
            'breadcrumbs' => [['Interests', '/tags'], [$tag['name'], '/tag/' . $tag['slug']]],
        ]);
    }

    // ---- reading ------------------------------------------------------------------------------------------

    private function story(string $col, string $value): void
    {
        $pdo = Db::pdo();
        $col = $col === 'legacy_path' ? 'legacy_path' : 'slug';
        $st = $pdo->prepare("SELECT s.*, se.title AS series_title, se.slug AS series_slug FROM stories s LEFT JOIN series se ON se.id = s.series_id WHERE s.$col = ? AND " . self::LIVE);
        $st->execute([$value]);
        $s = $st->fetch();
        if (!$s) {
            $this->notFound();
            return;
        }
        $tg = $pdo->prepare('SELECT t.slug, t.name FROM tags t JOIN item_tags it ON it.tag_id = t.id WHERE it.item_type = "story" AND it.item_id = ? AND t.hidden = 0 ORDER BY t.name');
        $tg->execute([$s['id']]);
        $tags = $tg->fetchAll();

        $prev = $next = null;
        $chapters = 0;
        if ($s['series_id']) {
            $nav = $pdo->prepare('SELECT s.id, s.slug, s.legacy_path, s.title, s.chapter_order FROM stories s WHERE s.series_id = ? AND ' . self::LIVE . ' ORDER BY s.chapter_order, s.id');
            $nav->execute([$s['series_id']]);
            $all = $nav->fetchAll();
            $chapters = count($all);
            foreach ($all as $i => $c) {
                if ((int) $c['id'] === (int) $s['id']) {
                    $prev = $all[$i - 1] ?? null;
                    $next = $all[$i + 1] ?? null;
                }
            }
        }
        $rel = $pdo->prepare($this->listSql() . ' AND s.id <> ? ORDER BY (SELECT COUNT(*) FROM item_tags a JOIN item_tags b ON a.tag_id = b.tag_id WHERE a.item_type = "story" AND b.item_type = "story" AND a.item_id = s.id AND b.item_id = ?) DESC, s.published_at DESC LIMIT 3');
        $rel->execute([$s['id'], $s['id']]);
        $cm = $pdo->prepare('SELECT author, body_html, commented_at FROM comments_archive WHERE story_id = ? AND status = "LIVE" ORDER BY commented_at, id');
        $cm->execute([$s['id']]);
        $comments = $cm->fetchAll();
        $desc = $s['summary'] !== '' ? $s['summary'] : excerpt($s['body_html']);
        if ($desc === '') {
            $desc = $s['title'] . ' · a post from ' . setting('site_title') . ', ' . format_date($s['published_at']) . '.';
        }
        $url = site_url(story_url($s));
        $author = $s['author'] !== '' ? $s['author'] : setting('author_name', setting('site_title'));
        $this->send('story', ['s' => $s, 'tags' => $tags, 'related' => $rel->fetchAll(), 'prev' => $prev, 'next' => $next, 'chapters' => $chapters, 'author' => $author, 'comments' => $comments, 'form' => $this->form], [
            'title' => $s['title'] . $this->sameTitleYear($s) . ' | ' . setting('site_title'),
            'description' => $desc,
            'canonical' => $url,
            'og_type' => 'article',
            'breadcrumbs' => [['Stories', '/stories'], [$s['title'], story_url($s)]],
            'jsonld' => [['@context' => 'https://schema.org', '@type' => 'Article', 'headline' => $s['title'], 'description' => $desc,
                'datePublished' => gmdate('c', strtotime($s['published_at'] . ' UTC')), 'dateModified' => gmdate('c', strtotime($s['updated_at'] . ' UTC')),
                'author' => ['@type' => 'Person', 'name' => $author], 'publisher' => ['@type' => 'Person', 'name' => $author], 'inLanguage' => 'en', 'isFamilyFriendly' => false, 'mainEntityOfPage' => $url, 'wordCount' => (int) $s['word_count'],
                'isAccessibleForFree' => true, 'keywords' => implode(', ', array_column($tags, 'name'))]],
        ]);
    }

    // ---- gallery, books, pages -----------------------------------------------------------------------------

    /** The one big photo gallery: every published image, newest blog post first; optionally one album, a search, or an interest. */
    private function gallery(): void
    {
        $pdo = Db::pdo();
        $only = (string) ($_GET['album'] ?? '');
        $q = trim((string) ($_GET['q'] ?? ''));
        $tagSlug = (string) ($_GET['tag'] ?? '');
        $where = 'g.status = "published"';
        $args = [];
        if ($only !== '') {
            $where .= ' AND g.album_id = (SELECT id FROM albums WHERE slug = ? AND status = "published")';
            $args[] = $only;
        }
        if ($q !== '') {
            foreach ($this->terms($q) as $t) {
                $like = '%' . addcslashes($t, '%_\\') . '%';
                $where .= ' AND (g.title LIKE ? OR g.caption LIKE ? OR g.credit LIKE ?)';
                array_push($args, $like, $like, $like);
            }
        }
        if ($tagSlug !== '') {
            $where .= ' AND g.id IN (SELECT it.item_id FROM item_tags it JOIN tags t ON t.id = it.tag_id WHERE it.item_type = "gallery" AND t.slug = ?)';
            $args[] = $tagSlug;
        }
        $perPage = 48;
        $count = $pdo->prepare("SELECT COUNT(*) FROM gallery_items g WHERE $where");
        $count->execute($args);
        $total = (int) $count->fetchColumn();
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($pages, max(1, (int) ($_GET['page'] ?? 1)));
        $st = $pdo->prepare("SELECT g.* FROM gallery_items g LEFT JOIN stories s ON s.id = g.story_id WHERE $where ORDER BY COALESCE(s.published_at, g.created_at) DESC, g.id DESC LIMIT $perPage OFFSET " . (($page - 1) * $perPage));
        $st->execute($args);
        $albums = $pdo->query('SELECT a.slug, a.title FROM albums a WHERE a.status = "published" AND EXISTS (SELECT 1 FROM gallery_items g WHERE g.album_id = a.id AND g.status = "published") ORDER BY a.sort_order, a.id')->fetchAll();
        $filtered = $only !== '' || $q !== '' || $tagSlug !== '';
        $cur = array_filter(['album' => $only, 'q' => $q, 'tag' => $tagSlug], fn($v) => $v !== '');
        $this->send('gallery', ['items' => $st->fetchAll(), 'albums' => $albums, 'only' => $only, 'q' => $q, 'total' => $total, 'topTags' => $this->topTags(), 'page' => $page, 'pages' => $pages, 'base' => '/gallery', 'cur' => $cur], [
            'title' => 'The gallery' . ($page > 1 ? " (page $page)" : '') . ' | ' . setting('site_title'),
            'description' => 'Collected images, newest first.',
            'canonical' => site_url('/gallery' . ($page > 1 && !$filtered ? "?page=$page" : '')),
            'robots' => $filtered ? 'noindex,follow' : null,
            'breadcrumbs' => [['Gallery', '/gallery']],
        ]);
    }

    /** @return list<string> search words, at least two characters each */
    private function terms(string $q): array
    {
        $clean = preg_replace('/[^\p{L}\p{N}\' -]+/u', ' ', $q) ?? '';
        return array_values(array_unique(array_filter(preg_split('/\s+/u', trim($clean)) ?: [], fn($t) => mb_strlen($t) >= 2)));
    }

    /** Site-wide search: stories, gallery images, books and interests. */
    private function search(): void
    {
        $pdo = Db::pdo();
        $q = trim((string) ($_GET['q'] ?? ''));
        $terms = $this->terms($q);
        $like = fn(string $t) => '%' . addcslashes($t, '%_\\') . '%';
        $res = ['stories' => [], 'total' => 0, 'images' => [], 'imageTotal' => 0, 'books' => [], 'tags' => [], 'page' => 1, 'pages' => 1];
        if ($terms) {
            // Stories: every word must appear in the title or the story text; title matches first, then newest.
            $where = ' AND s.kind <> "picture"';
            $args = [];
            foreach ($terms as $t) {
                $where .= ' AND (s.title LIKE ? OR s.summary LIKE ? OR s.search_text LIKE ?)';
                array_push($args, $like($t), $like($t), $like($t));
            }
            $order = 'ORDER BY (s.title LIKE ?) DESC, s.published_at DESC, s.id DESC';
            $c = $pdo->prepare('SELECT COUNT(*) FROM stories s WHERE ' . self::LIVE . $where);
            $c->execute($args);
            $res['total'] = (int) $c->fetchColumn();
            $res['pages'] = max(1, (int) ceil($res['total'] / 10));
            $res['page'] = min($res['pages'], max(1, (int) ($_GET['page'] ?? 1)));
            $st = $pdo->prepare('SELECT s.id, s.slug, s.legacy_path, s.title, s.published_at, s.kind, s.search_text, s.word_count FROM stories s WHERE ' . self::LIVE . $where . " $order LIMIT 10 OFFSET " . (($res['page'] - 1) * 10));
            $st->execute([...$args, $like($q)]);
            $res['stories'] = $st->fetchAll();

            // Images: captions, titles and credits.
            $iw = 'g.status = "published"';
            $ia = [];
            foreach ($terms as $t) {
                $iw .= ' AND (g.title LIKE ? OR g.caption LIKE ? OR g.credit LIKE ?)';
                array_push($ia, $like($t), $like($t), $like($t));
            }
            $ic = $pdo->prepare("SELECT COUNT(*) FROM gallery_items g WHERE $iw");
            $ic->execute($ia);
            $res['imageTotal'] = (int) $ic->fetchColumn();
            if ($res['imageTotal']) {
                $is = $pdo->prepare("SELECT g.* FROM gallery_items g LEFT JOIN stories s ON s.id = g.story_id WHERE $iw ORDER BY COALESCE(s.published_at, g.created_at) DESC, g.id DESC LIMIT 12");
                $is->execute($ia);
                $res['images'] = $is->fetchAll();
            }

            // Books and interests: any word.
            $bw = [];
            $ba = [];
            $tw = [];
            $ta = [];
            foreach ($terms as $t) {
                $bw[] = '(b.title LIKE ? OR b.subtitle LIKE ? OR b.description_html LIKE ?)';
                array_push($ba, $like($t), $like($t), $like($t));
                $tw[] = 't.name LIKE ?';
                $ta[] = $like($t);
            }
            $bs = $pdo->prepare('SELECT b.slug, b.title FROM books b WHERE b.status = "published" AND (' . implode(' AND ', $bw) . ') ORDER BY b.sort_order');
            $bs->execute($ba);
            $res['books'] = $bs->fetchAll();
            $ts = $pdo->prepare('SELECT t.slug, t.name FROM tags t WHERE t.hidden = 0 AND (' . implode(' OR ', $tw) . ') ORDER BY t.name LIMIT 12');
            $ts->execute($ta);
            $res['tags'] = $ts->fetchAll();
        }
        $this->send('search', $res + ['q' => $q, 'terms' => $terms, 'base' => '/search', 'cur' => $q !== '' ? ['q' => $q] : [], 'page' => $res['page'], 'pages' => $res['pages']], [
            'title' => ($q !== '' ? 'Search: ' . $q : 'Search') . ' | ' . setting('site_title'),
            'description' => 'Search the stories, images, books and interests.',
            'canonical' => site_url('/search'),
            'robots' => 'noindex,follow',
            'breadcrumbs' => [['Search', '/search']],
        ]);
    }

    /** " (2009)" when another published story has exactly this title, so every page keeps its own search-result title. */
    private function sameTitleYear(array $s): string
    {
        $st = Db::pdo()->prepare('SELECT COUNT(*) FROM stories WHERE title = ? AND status = "published" AND id <> ?');
        $st->execute([$s['title'], $s['id']]);
        return (int) $st->fetchColumn() > 0 ? ' (' . gmdate('Y', strtotime($s['published_at'] . ' UTC')) . ')' : '';
    }

    /** @return list<array<string,mixed>> published books with their editions */
    private function loadBooks(?string $slug = null): array
    {
        $pdo = Db::pdo();
        $st = $pdo->prepare('SELECT * FROM books WHERE status = "published"' . ($slug !== null ? ' AND slug = ?' : '') . ' ORDER BY sort_order, published_on DESC, id DESC');
        $st->execute($slug !== null ? [$slug] : []);
        $books = $st->fetchAll();
        foreach ($books as &$b) {
            $ed = $pdo->prepare('SELECT * FROM book_editions WHERE book_id = ? ORDER BY sort_order, id');
            $ed->execute([$b['id']]);
            $b['editions'] = $ed->fetchAll();
        }
        unset($b);
        return $books;
    }

    private function books(): void
    {
        $books = $this->loadBooks();
        $this->send('books', ['books' => $books, 'topTags' => $this->topTags()], [
            'title' => 'The bookshelf | ' . setting('site_title'),
            'description' => 'Books by ' . setting('author_name', setting('site_title')) . '.',
            'canonical' => site_url('/books'),
            'breadcrumbs' => [['Books', '/books']],
            'jsonld' => [['@context' => 'https://schema.org', '@type' => 'CollectionPage', 'name' => 'The bookshelf', 'url' => site_url('/books'),
                'hasPart' => array_map(fn(array $b) => ['@type' => 'Book', 'name' => $b['title'], 'url' => site_url('/books/' . $b['slug'])], $books)]],
        ]);
    }

    private function book(string $slug): void
    {
        $all = $this->loadBooks();
        $book = null;
        foreach ($all as $b) {
            if ($b['slug'] === $slug) {
                $book = $b;
            }
        }
        if (!$book) {
            $this->notFound();
            return;
        }
        $others = array_values(array_filter($all, fn($b) => $b['slug'] !== $slug));
        $this->send('book', ['book' => $book, 'others' => array_slice($others, 0, 4)], [
            'title' => $book['title'] . ' | Books | ' . setting('site_title'),
            'description' => excerpt($book['description_html'], 155) ?: 'A book by ' . setting('author_name', setting('site_title')) . '.',
            'canonical' => site_url('/books/' . $book['slug']),
            'breadcrumbs' => [['Books', '/books'], [$book['title'], '/books/' . $book['slug']]],
            'jsonld' => [['@context' => 'https://schema.org', '@type' => 'Book', 'name' => $book['title'],
                'author' => ['@type' => 'Person', 'name' => setting('author_name', setting('site_title'))],
                'description' => excerpt($book['description_html'], 300), 'url' => site_url('/books/' . $book['slug'])]
                + ($book['isbn'] !== '' ? ['isbn' => $book['isbn']] : [])],
        ]);
    }

    /** @param list<string> $slugs */
    private function page(array $slugs): void
    {
        $in = implode(',', array_fill(0, count($slugs), '?'));
        $st = Db::pdo()->prepare("SELECT * FROM pages WHERE slug IN ($in) AND status = 'published' LIMIT 1");
        $st->execute($slugs);
        $this->renderPage($st->fetch() ?: null);
    }

    private function legacyPage(string $path): void
    {
        $st = Db::pdo()->prepare('SELECT * FROM pages WHERE legacy_path = ? AND status = "published"');
        $st->execute([$path]);
        $this->renderPage($st->fetch() ?: null);
    }

    // ---- contact form and comments --------------------------------------------------------------------------

    /** Errors and what the visitor typed, kept for the page that is shown again after a failed submit. */
    private array $form = [];

    private function contactPage(): void
    {
        $st = Db::pdo()->prepare('SELECT * FROM pages WHERE slug = "contact" AND status = "published"');
        $st->execute();
        $this->renderPage($st->fetch() ?: ['slug' => 'contact', 'legacy_path' => '/contact', 'title' => 'Contact', 'summary' => 'Send a message to ' . setting('author_name') . '.', 'body_html' => '']);
    }

    private function clean(string $v, int $max): string
    {
        return mb_substr(trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $v) ?? ''), 0, $max);
    }

    /** Sends a plain-text notice to the site owner. A failure here never loses the message: it is already saved. */
    private function notifyOwner(string $subject, string $body, string $replyTo = ''): void
    {
        $to = setting('contact_email', 'pornoperson@gmail.com');
        $host = parse_url((string) \App\Support\Env::get('APP_URL', ''), PHP_URL_HOST) ?: 'localhost';
        $headers = ['From: ' . setting('site_title') . ' <noreply@' . $host . '>', 'Content-Type: text/plain; charset=UTF-8'];
        if ($replyTo !== '' && Forms::emailOk($replyTo)) {
            $headers[] = 'Reply-To: ' . preg_replace('/[\r\n]+/', '', $replyTo);
        }
        @mail($to, '=?UTF-8?B?' . base64_encode(preg_replace('/[\r\n]+/', ' ', $subject) ?? '') . '?=', $body, implode("\r\n", $headers));
    }

    private function contactSubmit(): void
    {
        $name = $this->clean((string) ($_POST['name'] ?? ''), 120);
        $email = $this->clean((string) ($_POST['email'] ?? ''), 190);
        $msg = $this->clean((string) ($_POST['message'] ?? ''), 5000);
        $errors = [];
        if ($name === '') {
            $errors[] = 'Please enter your name.';
        }
        if (!Forms::emailOk($email)) {
            $errors[] = 'Please enter a valid email address (like name@example.com) so I can reply.';
        }
        if (mb_strlen($msg) < 10) {
            $errors[] = 'Please write a message of at least 10 characters.';
        }
        if (!$errors) {
            $errors = Forms::guard('contact', 3, 60);
            if ($errors === ['trap']) {
                header('Location: /contact?sent=1', true, 303);   // robots get the same answer a person does
                return;
            }
        }
        if ($errors) {
            $this->form = ['errors' => $errors, 'old' => ['name' => $name, 'email' => $email, 'message' => $msg]];
            http_response_code(422);
            $this->contactPage();
            return;
        }
        Db::pdo()->prepare('INSERT INTO contact_messages (name, email, body, ip_hash, created_at) VALUES (?, ?, ?, ?, UTC_TIMESTAMP())')
            ->execute([$name, $email, $msg, Forms::ipHash()]);
        $this->notifyOwner('New message from ' . $name, "From: $name <$email>\n\n$msg\n\nOpen it in the admin: " . site_url('/admin/messages') . "\n", $email);
        header('Location: /contact?sent=1', true, 303);
    }

    private function commentSubmit(): void
    {
        $pdo = Db::pdo();
        $st = $pdo->prepare('SELECT s.id, s.slug, s.legacy_path, s.title FROM stories s WHERE s.id = ? AND ' . self::LIVE);
        $st->execute([(int) ($_POST['story_id'] ?? 0)]);
        $story = $st->fetch();
        if (!$story) {
            $this->notFound();
            return;
        }
        $name = $this->clean((string) ($_POST['name'] ?? ''), 80);
        $email = $this->clean((string) ($_POST['email'] ?? ''), 190);
        $text = $this->clean((string) ($_POST['comment'] ?? ''), 3000);
        $errors = [];
        if (mb_strlen($text) < 2) {
            $errors[] = 'Please write a comment.';
        }
        if ($email !== '' && !Forms::emailOk($email)) {
            $errors[] = 'That email address does not look right (or leave it blank).';
        }
        if (preg_match_all('#https?://|www\.#i', $text) > 2) {
            $errors[] = 'Comments can include up to two links.';
        }
        if (!$errors) {
            $errors = Forms::guard('comment', 5, 60);
            if ($errors === ['trap']) {
                header('Location: ' . story_url($story) . '?comment=pending#comments', true, 303);
                return;
            }
        }
        $back = $story['legacy_path'] ?: null;
        if ($errors) {
            $this->form = ['errors' => $errors, 'old' => ['name' => $name, 'email' => $email, 'comment' => $text]];
            http_response_code(422);
            $this->story($back ? 'legacy_path' : 'slug', $back ?: $story['slug']);
            return;
        }
        $paras = array_filter(preg_split('/\n{2,}/', str_replace("\r", '', $text)) ?: []);
        $html = implode("\n", array_map(fn($p) => '<p>' . nl2br(e(trim($p))) . '</p>', $paras));
        $pdo->prepare('INSERT INTO comments_archive (story_id, author, author_email, body_html, status, commented_at, created_at, source_id, ip_hash) VALUES (?, ?, ?, ?, "pending", UTC_TIMESTAMP(), UTC_TIMESTAMP(), ?, ?)')
            ->execute([$story['id'], $name, $email, $html, 'site:' . bin2hex(random_bytes(8)), Forms::ipHash()]);
        $this->notifyOwner('Comment waiting on ' . $story['title'], ($name !== '' ? $name : 'Anonymous') . " wrote:\n\n$text\n\nApprove or delete it here: " . site_url('/admin/comments') . "\n", $email);
        header('Location: ' . story_url($story) . '?comment=pending#comments', true, 303);
    }

    private function renderPage(?array $p): void
    {
        if (!$p) {
            $this->notFound();
            return;
        }
        $path = $p['legacy_path'] ?: '/' . $p['slug'];
        // Contact and About answer at their old Blogger address and at a clean one; search engines are told the clean one.
        $clean = $p['slug'] === 'contact' ? '/contact' : (str_starts_with($p['slug'], 'about') ? '/about' : $path);
        $has = fn(string $slug) => (bool) Db::pdo()->query('SELECT 1 FROM pages WHERE status = "published" AND slug = ' . Db::pdo()->quote($slug))->fetchColumn();
        $this->send('page', ['form' => $this->form, 'p' => $p, 'isAbout' => str_starts_with($p['slug'], 'about'), 'hasContact' => $has('contact'), 'hasBooks' => (bool) Db::pdo()->query('SELECT 1 FROM books WHERE status = "published" LIMIT 1')->fetchColumn()], [
            'title' => $p['title'] . ' | ' . setting('site_title'),
            'description' => $p['summary'] !== '' ? $p['summary'] : ($p['slug'] === 'contact' ? 'Send a message to ' . setting('author_name', setting('site_title')) . ', or find him elsewhere online.' : excerpt($p['body_html'])),
            'canonical' => site_url($clean),
            'breadcrumbs' => [[$p['title'], $clean]],
        ]);
    }

    // ---- machine-readable ---------------------------------------------------------------------------------

    /** Atom feed of the newest 50 stories and posts (not the image-only posts), for people who follow the site in a reader. */
    private function feed(): void
    {
        $pdo = Db::pdo();
        $rows = $pdo->query('SELECT s.slug, s.legacy_path, s.title, s.summary, s.body_html, s.author, s.published_at, s.updated_at FROM stories s WHERE ' . self::LIVE . ' AND s.kind <> "picture" ORDER BY s.published_at DESC LIMIT 50')->fetchAll();
        $iso = fn(?string $t) => gmdate('c', strtotime(($t ?: 'now') . ' UTC'));
        $x = fn(string $v) => htmlspecialchars($v, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $name = setting('author_name', setting('site_title'));
        header('Content-Type: application/atom+xml; charset=utf-8');
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<feed xmlns="http://www.w3.org/2005/Atom">' . "\n";
        echo '<title>' . $x(setting('site_title')) . '</title><subtitle>' . $x(setting('tagline')) . '</subtitle>';
        echo '<link href="' . $x(site_url('/feed')) . '" rel="self" type="application/atom+xml"/><link href="' . $x(site_url('/')) . '"/>';
        echo '<id>' . $x(site_url('/')) . '</id><updated>' . $iso($rows[0]['updated_at'] ?? null) . '</updated>';
        foreach ($rows as $s) {
            $url = site_url(story_url($s));
            $sum = $s['summary'] !== '' ? $s['summary'] : excerpt($s['body_html'], 300);
            echo '<entry><title>' . $x($s['title']) . '</title><link href="' . $x($url) . '"/><id>' . $x($url) . '</id>';
            echo '<published>' . $iso($s['published_at']) . '</published><updated>' . $iso($s['updated_at']) . '</updated>';
            echo '<author><name>' . $x($s['author'] !== '' ? $s['author'] : $name) . '</name></author><summary>' . $x($sum) . '</summary></entry>' . "\n";
        }
        echo '</feed>';
    }

    private function robots(): void
    {
        header('Content-Type: text/plain; charset=utf-8');
        echo "User-agent: *\nDisallow: /admin\nDisallow: /enter\nDisallow: /leave\nDisallow: /random\nAllow: /\n\nSitemap: " . (preg_match('/(^|\.)prurientinterests\.me$/i', (string) ($_SERVER['HTTP_HOST'] ?? '')) ? 'https://prurientinterests.me' : site_url()) . "/sitemap.xml\n";
    }

    private function sitemap(): void
    {
        $pdo = Db::pdo();
        $urls = [[site_url('/'), null]];
        foreach (['/stories', '/tags', '/gallery', '/books'] as $p) {
            $urls[] = [site_url($p), null];
        }
        foreach ($pdo->query('SELECT slug, legacy_path, updated_at FROM stories s WHERE ' . self::LIVE) as $s) {
            $urls[] = [site_url(story_url($s)), $s['updated_at']];
        }
        foreach ($pdo->query('SELECT slug FROM series') as $t) {
            $urls[] = [site_url('/series/' . $t['slug']), null];
        }
        foreach ($pdo->query('SELECT slug FROM tags WHERE hidden = 0') as $t) {
            $urls[] = [site_url('/tag/' . $t['slug']), null];
        }
        foreach ($pdo->query('SELECT slug FROM books WHERE status = "published"') as $bk) {
            $urls[] = [site_url('/books/' . $bk['slug']), null];
        }
        $seen = [];
        foreach ($pdo->query('SELECT slug, legacy_path, updated_at FROM pages WHERE status = "published"') as $p) {
            // About and Contact are listed at their clean addresses; the old Blogger ones redirect there
            $path = $p['slug'] === 'contact' ? '/contact' : (str_starts_with($p['slug'], 'about') ? '/about' : ($p['legacy_path'] ?: '/' . $p['slug']));
            if (!isset($seen[$path])) {
                $seen[$path] = true;
                $urls[] = [site_url($path), $p['updated_at']];
            }
        }
        header('Content-Type: application/xml; charset=utf-8');
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($urls as [$loc, $mod]) {
            echo '<url><loc>' . e($loc) . '</loc>' . ($mod ? '<lastmod>' . gmdate('Y-m-d', strtotime($mod . ' UTC')) . '</lastmod>' : '') . "</url>\n";
        }
        echo '</urlset>';
    }

    // ---- shared -------------------------------------------------------------------------------------------

    /**
     * Listing query for written pieces. A series appears once, as its first chapter.
     * $anyKind keeps video and short posts too (tag pages), but never picture posts.
     */
    private function listSql(bool $anyKind = false): string
    {
        $kinds = $anyKind ? '' : ' AND s.kind IN ' . self::WRITTEN;
        return 'SELECT s.id, s.slug, s.legacy_path, s.title, s.summary, s.body_html, s.published_at, s.updated_at, s.author, s.word_count, s.kind, s.series_id, s.chapter_order,
                se.title AS series_title, se.slug AS series_slug,
                (SELECT COUNT(*) FROM stories c WHERE c.series_id = s.series_id AND c.status = "published" AND c.published_at <= UTC_TIMESTAMP()) AS chapters
            FROM stories s LEFT JOIN series se ON se.id = s.series_id
            WHERE ' . self::LIVE . $kinds . '
              AND (s.series_id IS NULL OR s.id = (SELECT c.id FROM stories c WHERE c.series_id = s.series_id AND c.status = "published" AND c.published_at <= UTC_TIMESTAMP() ORDER BY c.chapter_order, c.id LIMIT 1))';
    }

    /**
     * Runs a listing query with paging.
     * @param array<int,mixed> $args @return array{rows:list<array<string,mixed>>,page:int,pages:int,total:int}
     */
    private function paged(string $sql, array $args, string $order): array
    {
        $pdo = Db::pdo();
        $count = $pdo->prepare('SELECT COUNT(*) FROM (' . $sql . ') x');
        $count->execute($args);
        $total = (int) $count->fetchColumn();
        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min($pages, max(1, (int) ($_GET['page'] ?? 1)));
        $st = $pdo->prepare($sql . " $order LIMIT " . self::PER_PAGE . ' OFFSET ' . (($page - 1) * self::PER_PAGE));
        $st->execute($args);
        return ['rows' => $st->fetchAll(), 'page' => $page, 'pages' => $pages, 'total' => $total];
    }

    /** @return list<array{slug:string,name:string}> */
    private function topTags(): array
    {
        return Db::pdo()->query('SELECT t.slug, t.name FROM tags t WHERE t.featured = 1 AND t.hidden = 0 AND t.kind = "subject" ORDER BY t.sort_order, t.name LIMIT 3')->fetchAll()
            ?: Db::pdo()->query('SELECT t.slug, t.name, COUNT(*) n FROM tags t JOIN item_tags it ON it.tag_id = t.id AND it.item_type = "story" JOIN stories s ON s.id = it.item_id AND ' . self::LIVE . ' AND s.kind IN ' . self::WRITTEN . ' WHERE t.hidden = 0 AND t.kind = "subject" GROUP BY t.id ORDER BY n DESC LIMIT 3')->fetchAll();
    }

    private function notFound(): void
    {
        http_response_code(404);
        $this->send('404', [], ['title' => 'Not found | ' . setting('site_title'), 'description' => 'That page does not exist.', 'robots' => 'noindex']);
    }

    /** @param array<string,mixed> $d @param array<string,mixed> $meta */
    private function send(string $template, array $d, array $meta): void
    {
        header('Content-Type: text/html; charset=utf-8');
        header('Rating: RTA-5042-1996-1400-1577-RTA');
        header('Vary: Cookie');
        // Visitors without the age-gate cookie get the notice laid over the page; the page itself is served in full (and
        // indexable) so search engines can read it. Only the notice's own page (a failed /enter) is hidden from search.
        $d['gate'] = !$this->gateOpen() && empty($meta['bare']);
        $d['gateReturn'] = (string) (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
        $d['nav'] = ['header' => $this->nav('header'), 'footer' => $this->nav('footer')];
        echo View::render($template, $d, $meta);
    }

    /** @return list<array<string,mixed>> */
    private function nav(string $loc): array
    {
        $st = Db::pdo()->prepare('SELECT label, url FROM nav_items WHERE location = ? AND visible = 1 ORDER BY sort_order, id');
        $st->execute([$loc]);
        return $st->fetchAll();
    }
}
