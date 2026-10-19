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

    private Router $router;

    public function __construct()
    {
        $this->router = new Router();
        $r = $this->router;
        $r->get('/', fn() => $this->home());
        $r->get('/stories', fn() => $this->stories(null));
        $r->get('/tags', fn() => $this->tags());
        $r->get('/tag/{slug}', fn(array $p) => $this->stories($p['slug']));
        $r->get('/story/{slug}', fn(array $p) => $this->story('slug', $p['slug']));
        $r->get('/gallery', fn() => $this->gallery());
        $r->get('/books', fn() => $this->books());
        $r->get('/about', fn() => $this->page('about'));
        $r->get('/p/{file}', fn(array $p) => $this->legacyPage('/p/' . $p['file']));
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
            if ($this->gateOpen() || $this->gateExempt($path)) {
                $this->story('legacy_path', $path);
            } else {
                $this->gate($path);
            }
            return;
        }
        $m = $this->router->match($method, $path);
        if ($m === null) {
            $this->notFound();
            return;
        }
        if (!$this->gateOpen() && !$this->gateExempt($path)) {
            $this->gate($path);
            return;
        }
        ($m[0])($m[1]);
    }

    // ---- gate ---------------------------------------------------------------------------------------------

    private function gateOpen(): bool
    {
        return ($_COOKIE[self::GATE_COOKIE] ?? '') === '1';
    }

    private function gateExempt(string $path): bool
    {
        return in_array($path, ['/robots.txt', '/enter', '/leave'], true);
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
        $over18 = ($_POST['over18'] ?? '') === '1';
        $return = (string) ($_POST['return'] ?? '/');
        if (!preg_match('#^/(?!/)#', $return)) {
            $return = '/';
        }
        if (!$over18) {
            $this->gate($return);
            return;
        }
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        setcookie(self::GATE_COOKIE, '1', ['expires' => time() + 30 * 86400, 'path' => '/', 'secure' => $https, 'httponly' => true, 'samesite' => 'Lax']);
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

    // ---- pages --------------------------------------------------------------------------------------------

    private function home(): void
    {
        $pdo = Db::pdo();
        $latest = $pdo->query($this->storySql() . ' ORDER BY published_at DESC LIMIT 6')->fetchAll();
        $tags = $pdo->query('SELECT t.slug, t.name, COUNT(*) n FROM tags t JOIN item_tags it ON it.tag_id = t.id AND it.item_type = "story" JOIN stories s ON s.id = it.item_id AND s.status = "published" AND s.published_at <= UTC_TIMESTAMP() GROUP BY t.id ORDER BY n DESC LIMIT 12')->fetchAll();
        $this->send('home', ['latest' => $latest, 'tags' => $tags], [
            'title' => setting('site_title') . ' | ' . setting('tagline'),
            'description' => setting('tagline'),
            'canonical' => site_url('/'),
            'jsonld' => [['@context' => 'https://schema.org', '@type' => 'WebSite', 'name' => setting('site_title'), 'url' => site_url('/'),
                'potentialAction' => ['@type' => 'SearchAction', 'target' => site_url('/stories?q={search_term_string}'), 'query-input' => 'required name=search_term_string']]],
        ]);
    }

    private function stories(?string $tagSlug): void
    {
        $pdo = Db::pdo();
        $tag = null;
        $where = '';
        $args = [];
        if ($tagSlug !== null) {
            $st = $pdo->prepare('SELECT * FROM tags WHERE slug = ?');
            $st->execute([$tagSlug]);
            $tag = $st->fetch();
            if (!$tag) {
                $this->notFound();
                return;
            }
            $where .= ' AND id IN (SELECT item_id FROM item_tags WHERE item_type = "story" AND tag_id = ?)';
            $args[] = $tag['id'];
        }
        $q = trim((string) ($_GET['q'] ?? ''));
        if ($q !== '') {
            $where .= ' AND (title LIKE ? OR summary LIKE ? OR body_html LIKE ?)';
            $like = '%' . addcslashes($q, '%_\\') . '%';
            array_push($args, $like, $like, $like);
        }
        $sort = ($_GET['sort'] ?? 'new') === 'old' ? 'ASC' : 'DESC';
        $page = max(1, (int) ($_GET['page'] ?? 1));

        $count = $pdo->prepare('SELECT COUNT(*) FROM stories WHERE status = "published" AND published_at <= UTC_TIMESTAMP()' . $where);
        $count->execute($args);
        $total = (int) $count->fetchColumn();
        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min($page, $pages);

        $st = $pdo->prepare($this->storySql() . $where . " ORDER BY published_at $sort, id $sort LIMIT " . self::PER_PAGE . ' OFFSET ' . (($page - 1) * self::PER_PAGE));
        $st->execute($args);
        $base = $tag ? '/tag/' . $tag['slug'] : '/stories';
        $title = $tag ? $tag['name'] . ' stories' : 'Stories';
        $this->send('stories', ['stories' => $st->fetchAll(), 'tag' => $tag, 'q' => $q, 'sort' => $sort === 'ASC' ? 'old' : 'new',
            'page' => $page, 'pages' => $pages, 'total' => $total, 'base' => $base], [
            'title' => $title . ($page > 1 ? " (page $page)" : '') . ' | ' . setting('site_title'),
            'description' => $tag && $tag['description'] !== '' ? $tag['description'] : 'Read every story on ' . setting('site_title') . '.',
            'canonical' => site_url($base . ($page > 1 ? "?page=$page" : '')),
            'robots' => $q !== '' ? 'noindex,follow' : null,
            'breadcrumbs' => $tag ? [['Stories', '/stories'], [$tag['name'], $base]] : [['Stories', '/stories']],
        ]);
    }

    private function tags(): void
    {
        $tags = Db::pdo()->query('SELECT t.slug, t.name, t.description, COUNT(s.id) n FROM tags t JOIN item_tags it ON it.tag_id = t.id AND it.item_type = "story" JOIN stories s ON s.id = it.item_id AND s.status = "published" AND s.published_at <= UTC_TIMESTAMP() GROUP BY t.id ORDER BY t.name')->fetchAll();
        $this->send('tags', ['tags' => $tags], [
            'title' => 'Tags | ' . setting('site_title'),
            'description' => 'Browse every story by tag.',
            'canonical' => site_url('/tags'),
            'breadcrumbs' => [['Tags', '/tags']],
        ]);
    }

    private function story(string $col, string $value): void
    {
        $pdo = Db::pdo();
        $col = $col === 'legacy_path' ? 'legacy_path' : 'slug';
        $st = $pdo->prepare("SELECT * FROM stories WHERE $col = ? AND status = 'published' AND published_at <= UTC_TIMESTAMP()");
        $st->execute([$value]);
        $s = $st->fetch();
        if (!$s) {
            $this->notFound();
            return;
        }
        if (!$this->gateOpen()) {
            $this->gate($value);
            return;
        }
        $tg = $pdo->prepare('SELECT t.slug, t.name FROM tags t JOIN item_tags it ON it.tag_id = t.id WHERE it.item_type = "story" AND it.item_id = ? ORDER BY t.name');
        $tg->execute([$s['id']]);
        $tags = $tg->fetchAll();
        $rel = $pdo->prepare($this->storySql() . ' AND id <> ? ORDER BY (SELECT COUNT(*) FROM item_tags a JOIN item_tags b ON a.tag_id = b.tag_id WHERE a.item_type = "story" AND b.item_type = "story" AND a.item_id = stories.id AND b.item_id = ?) DESC, published_at DESC LIMIT 3');
        $rel->execute([$s['id'], $s['id']]);
        $desc = $s['summary'] !== '' ? $s['summary'] : excerpt($s['body_html']);
        $url = site_url(story_url($s));
        $this->send('story', ['s' => $s, 'tags' => $tags, 'related' => $rel->fetchAll()], [
            'title' => $s['title'] . ' | ' . setting('site_title'),
            'description' => $desc,
            'canonical' => $url,
            'og_type' => 'article',
            'breadcrumbs' => [['Stories', '/stories'], [$s['title'], story_url($s)]],
            'jsonld' => [['@context' => 'https://schema.org', '@type' => 'Article', 'headline' => $s['title'], 'description' => $desc,
                'datePublished' => gmdate('c', strtotime($s['published_at'] . ' UTC')), 'dateModified' => gmdate('c', strtotime($s['updated_at'] . ' UTC')),
                'author' => ['@type' => 'Person', 'name' => $s['author'] !== '' ? $s['author'] : setting('site_title')],
                'mainEntityOfPage' => $url, 'wordCount' => (int) $s['word_count'], 'isAccessibleForFree' => true,
                'keywords' => implode(', ', array_column($tags, 'name'))]],
        ]);
    }

    private function gallery(): void
    {
        $items = Db::pdo()->query('SELECT * FROM gallery_items WHERE status = "published" ORDER BY sort_order, id DESC')->fetchAll();
        $this->send('gallery', ['items' => $items], [
            'title' => 'Gallery | ' . setting('site_title'),
            'description' => 'The gallery.',
            'canonical' => site_url('/gallery'),
            'breadcrumbs' => [['Gallery', '/gallery']],
        ]);
    }

    private function books(): void
    {
        $books = Db::pdo()->query('SELECT * FROM books WHERE status = "published" ORDER BY sort_order, published_on DESC')->fetchAll();
        $this->send('books', ['books' => $books], [
            'title' => 'Books | ' . setting('site_title'),
            'description' => 'Books by ' . setting('site_title') . '.',
            'canonical' => site_url('/books'),
            'breadcrumbs' => [['Books', '/books']],
            'jsonld' => array_map(fn(array $b) => ['@context' => 'https://schema.org', '@type' => 'Book', 'name' => $b['title'],
                'description' => excerpt($b['description_html'], 300)] + ($b['isbn'] !== '' ? ['isbn' => $b['isbn']] : []), $books),
        ]);
    }

    private function page(string $slug): void
    {
        $st = Db::pdo()->prepare('SELECT * FROM pages WHERE slug = ? AND status = "published"');
        $st->execute([$slug]);
        $this->renderPage($st->fetch() ?: null);
    }

    private function legacyPage(string $path): void
    {
        $st = Db::pdo()->prepare('SELECT * FROM pages WHERE legacy_path = ? AND status = "published"');
        $st->execute([$path]);
        $this->renderPage($st->fetch() ?: null);
    }

    private function renderPage(?array $p): void
    {
        if (!$p) {
            $this->notFound();
            return;
        }
        $path = $p['legacy_path'] ?: '/' . $p['slug'];
        $this->send('page', ['p' => $p], [
            'title' => $p['title'] . ' | ' . setting('site_title'),
            'description' => $p['summary'] !== '' ? $p['summary'] : excerpt($p['body_html']),
            'canonical' => site_url($path),
            'breadcrumbs' => [[$p['title'], $path]],
        ]);
    }

    // ---- machine-readable ---------------------------------------------------------------------------------

    private function robots(): void
    {
        header('Content-Type: text/plain; charset=utf-8');
        echo "User-agent: *\nDisallow: /admin\nDisallow: /enter\nDisallow: /leave\nAllow: /\n\nSitemap: " . site_url('/sitemap.xml') . "\n";
    }

    private function sitemap(): void
    {
        $pdo = Db::pdo();
        $urls = [[site_url('/'), null]];
        foreach (['/stories', '/tags', '/gallery', '/books'] as $p) {
            $urls[] = [site_url($p), null];
        }
        foreach ($pdo->query('SELECT slug, legacy_path, updated_at FROM stories WHERE status = "published" AND published_at <= UTC_TIMESTAMP()') as $s) {
            $urls[] = [site_url(story_url($s)), $s['updated_at']];
        }
        foreach ($pdo->query('SELECT slug FROM tags') as $t) {
            $urls[] = [site_url('/tag/' . $t['slug']), null];
        }
        foreach ($pdo->query('SELECT slug, legacy_path, updated_at FROM pages WHERE status = "published"') as $p) {
            $urls[] = [site_url($p['legacy_path'] ?: '/' . $p['slug']), $p['updated_at']];
        }
        header('Content-Type: application/xml; charset=utf-8');
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($urls as [$loc, $mod]) {
            echo '<url><loc>' . e($loc) . '</loc>' . ($mod ? '<lastmod>' . gmdate('Y-m-d', strtotime($mod . ' UTC')) . '</lastmod>' : '') . "</url>\n";
        }
        echo '</urlset>';
    }

    // ---- shared -------------------------------------------------------------------------------------------

    private function storySql(): string
    {
        return 'SELECT id, slug, legacy_path, title, summary, body_html, published_at, updated_at, author, word_count, cover_image FROM stories WHERE status = "published" AND published_at <= UTC_TIMESTAMP()';
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
