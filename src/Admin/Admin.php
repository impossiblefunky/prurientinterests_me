<?php
declare(strict_types=1);

namespace App\Admin;

use App\Import\HtmlSanitizer;
use App\Media\Images;
use App\Support\Db;
use App\Support\Env;
use App\Web\View;
use DateTimeImmutable;
use DateTimeZone;
use PDO;

/**
 * The CMS at /admin. One resource table drives list, edit, save and delete for every content type.
 * Field types: text, url, textarea, html, select, number, date, datetime, checkbox, image, tags.
 */
final class Admin
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Db::pdo();
    }

    /** @return array<string,string> id => title, with an empty first choice */
    private function lookup(string $sql, string $none): array
    {
        $out = ['' => $none];
        foreach ($this->pdo->query($sql) as $r) {
            $out[(string) $r['id']] = $r['title'];
        }
        return $out;
    }

    /** @return array<string,array<string,mixed>> */
    private function resources(): array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        $status = ['draft' => 'Draft', 'published' => 'Published'];
        $series = $this->lookup('SELECT id, title FROM series ORDER BY title', 'No series');
        $albums = $this->lookup('SELECT id, title FROM albums ORDER BY sort_order, title', 'No album');
        $books = $this->lookup('SELECT id, title FROM books ORDER BY title', 'Choose a book');
        return $cache = [
            'stories' => [
                'group' => 'Stories & posts', 'label' => 'Stories & posts', 'singular' => 'story', 'table' => 'stories', 'tags' => 'story', 'revisions' => true, 'order' => 'COALESCE(published_at, created_at) DESC',
                'columns' => ['title' => 'Title', 'kind' => 'Type', 'status' => 'Status', 'published_at' => 'Publish date', 'word_count' => 'Words'],
                'filters' => ['kind' => ['story' => 'Stories', 'post' => 'Posts', 'picture' => 'Pictures', 'video' => 'Videos'], 'status' => $status],
                'fields' => [
                    ['title', 'Title', 'text', 'required' => true, 'max' => 255],
                    ['summary', 'Short description', 'textarea', 'max' => 500, 'rows' => 2, 'help' => 'Shown on cards and in search results. Leave blank to use the opening text.'],
                    ['body_html', 'Story', 'html', 'required' => true],
                    ['slug', 'URL slug', 'text', 'help' => 'Leave blank to build it from the title. New stories live at /story/slug.'],
                    ['author', 'Author name', 'text', 'max' => 120, 'help' => 'Leave blank to use the pen name from Settings.'],
                    ['legacy_path', 'Old Blogger path', 'text', 'max' => 255, 'help' => 'Only for imported posts, e.g. /2012/12/forbidden-fruit.html. Leave it alone so the old link keeps working.'],
                    ['cover_image', 'Cover image', 'image', 'dir' => 'covers'],
                    ['status', 'Status', 'select', 'options' => $status, 'panel' => true, 'help' => 'A published story with a future date stays hidden until then.'],
                    ['published_at', 'Publish date', 'datetime', 'panel' => true],
                    ['kind', 'Type', 'select', 'options' => ['story' => 'Story', 'post' => 'Short post', 'picture' => 'Picture post', 'video' => 'Video post'], 'panel' => true, 'help' => 'Picture and video posts stay out of the story library.'],
                    ['series_id', 'Series', 'select', 'options' => $series, 'nullable' => true, 'panel' => true],
                    ['chapter_order', 'Chapter order', 'number', 'panel' => true],
                    ['featured', 'Feature on homepage', 'checkbox', 'panel' => true],
                    ['tags', 'Interests (tags)', 'tags', 'panel' => true],
                ],
            ],
            'series' => [
                'group' => 'Stories & posts', 'label' => 'Series', 'singular' => 'series', 'table' => 'series', 'order' => 'title',
                'list_sql' => 'SELECT s.*, (SELECT COUNT(*) FROM stories x WHERE x.series_id = s.id) AS chapters FROM series s',
                'columns' => ['title' => 'Title', 'chapters' => 'Chapters'],
                'fields' => [
                    ['title', 'Series title', 'text', 'required' => true, 'max' => 255],
                    ['slug', 'URL slug', 'text', 'help' => 'The series page lives at /series/slug.'],
                    ['description', 'Description', 'textarea', 'max' => 500, 'rows' => 3],
                ],
                'help' => 'To put a story in a series, edit the story and choose the series and its chapter order.',
            ],
            'pages' => [
                'group' => 'Pages & homepage', 'label' => 'Pages', 'singular' => 'page', 'table' => 'pages', 'order' => 'title',
                'columns' => ['title' => 'Title', 'slug' => 'Slug', 'status' => 'Status'],
                'fields' => [
                    ['title', 'Page or section title', 'text', 'required' => true, 'max' => 255],
                    ['body_html', 'Copy', 'html', 'required' => true],
                    ['slug', 'URL slug', 'text', 'help' => 'The page lives at /slug. The slug "about" (or "about-prurient-interests") is the About page.'],
                    ['legacy_path', 'Old Blogger path', 'text', 'max' => 255, 'help' => 'e.g. /p/about.html'],
                    ['summary', 'Summary', 'textarea', 'max' => 500, 'rows' => 2],
                    ['status', 'Status', 'select', 'options' => $status],
                ],
            ],
            'navigation' => [
                'group' => 'Pages & homepage', 'label' => 'Navigation', 'singular' => 'link', 'table' => 'nav_items', 'order' => 'location, sort_order',
                'columns' => ['label' => 'Label', 'url' => 'Destination', 'location' => 'Where', 'sort_order' => 'Order'],
                'fields' => [
                    ['label', 'Navigation label', 'text', 'required' => true, 'max' => 80],
                    ['url', 'Destination', 'text', 'required' => true, 'max' => 500, 'help' => 'A path like /stories or a full https:// address.'],
                    ['location', 'Where', 'select', 'options' => ['header' => 'Header', 'footer' => 'Footer']],
                    ['sort_order', 'Order', 'number', 'help' => 'Lower numbers come first.'],
                    ['visible', 'Show this link', 'checkbox'],
                ],
            ],
            'redirects' => [
                'group' => 'Pages & homepage', 'label' => 'Redirects', 'singular' => 'redirect', 'table' => 'redirects', 'order' => 'from_path',
                'columns' => ['from_path' => 'Original path', 'to_url' => 'New destination', 'code' => 'Type', 'hits' => 'Hits'],
                'fields' => [
                    ['from_path', 'Original Blogger path', 'text', 'required' => true, 'max' => 255, 'help' => 'Starts with a slash, e.g. /2012/12/old-name.html'],
                    ['to_url', 'New destination', 'text', 'required' => true, 'max' => 500],
                    ['code', 'Type', 'select', 'options' => ['301' => 'Permanent (301)', '302' => 'Temporary (302)']],
                ],
                'help' => 'Every imported post already keeps its old address, so you only need a redirect when you change a path.',
            ],
            'gallery' => [
                'group' => 'Media & galleries', 'label' => 'Images', 'singular' => 'image', 'table' => 'gallery_items', 'tags' => 'gallery', 'order' => 'sort_order, id DESC',
                'list_sql' => 'SELECT g.*, a.title AS album FROM gallery_items g LEFT JOIN albums a ON a.id = g.album_id',
                'columns' => ['thumb' => 'Picture', 'title' => 'Title', 'album' => 'Album', 'status' => 'Status', 'sort_order' => 'Order'],
                'bulk' => true, 'bulk_status' => true, 'per_page' => 60,
                'fields' => [
                    ['title', 'Title', 'text', 'required' => true, 'max' => 255],
                    ['file', 'Image', 'image', 'dir' => 'gallery', 'required' => true, 'extra' => ['thumb' => 'thumb', 'width' => 'width', 'height' => 'height']],
                    ['caption', 'Selected image caption', 'text', 'max' => 500],
                    ['alt_text', 'Image description / alternative text', 'text', 'required' => true, 'max' => 255, 'help' => 'Describe the image for people who cannot see it.'],
                    ['source_url', 'Source or credit URL', 'url', 'max' => 500],
                    ['credit', 'Photographer / creator credit', 'text', 'max' => 255],
                    ['album_id', 'Album', 'select', 'options' => $albums, 'nullable' => true, 'panel' => true],
                    ['sort_order', 'Display order', 'number', 'panel' => true],
                    ['is_cover', 'Album cover image', 'checkbox', 'panel' => true],
                    ['featured', 'Feature on homepage', 'checkbox', 'panel' => true],
                    ['status', 'Visibility', 'select', 'options' => $status, 'panel' => true],
                    ['tags', 'Tags', 'tags', 'panel' => true],
                ],
            ],
            'albums' => [
                'group' => 'Media & galleries', 'label' => 'Albums', 'singular' => 'album', 'table' => 'albums', 'tags' => 'album', 'order' => 'sort_order, title', 'bulk_status' => true,
                'list_sql' => 'SELECT a.*, (SELECT COUNT(*) FROM gallery_items g WHERE g.album_id = a.id) AS images FROM albums a',
                'columns' => ['title' => 'Album', 'images' => 'Images', 'status' => 'Status', 'sort_order' => 'Order'],
                'fields' => [
                    ['title', 'Album title', 'text', 'required' => true, 'max' => 255],
                    ['intro', 'Album introduction', 'textarea', 'max' => 500, 'rows' => 3],
                    ['slug', 'URL slug', 'text'],
                    ['sort_order', 'Display order', 'number', 'panel' => true],
                    ['featured', 'Feature on homepage', 'checkbox', 'panel' => true],
                    ['status', 'Visibility', 'select', 'options' => $status, 'panel' => true],
                    ['tags', 'Tags', 'tags', 'panel' => true],
                ],
            ],
            'books' => [
                'group' => 'Books', 'label' => 'Books', 'singular' => 'book', 'table' => 'books', 'tags' => 'book', 'order' => 'sort_order, id DESC',
                'columns' => ['title' => 'Title', 'status' => 'Status', 'published_on' => 'Published'],
                'fields' => [
                    ['title', 'Title', 'text', 'required' => true, 'max' => 255],
                    ['cover_image', 'Cover image', 'image', 'dir' => 'books'],
                    ['description_html', 'Description', 'html', 'required' => true],
                    ['excerpt_html', 'Excerpt', 'html'],
                    ['slug', 'URL slug', 'text'],
                    ['subtitle', 'Subtitle', 'text', 'max' => 255],
                    ['published_on', 'Published on', 'date'],
                    ['status', 'Status', 'select', 'options' => $status, 'panel' => true],
                    ['sort_order', 'Display order', 'number', 'panel' => true],
                    ['featured', 'Feature on homepage', 'checkbox', 'panel' => true],
                    ['tags', 'Tags', 'tags', 'panel' => true],
                ],
                'help' => 'Add editions (Paperback, Kindle, prices, purchase links, ISBN) under Editions.',
            ],
            'editions' => [
                'group' => 'Books', 'label' => 'Editions', 'singular' => 'edition', 'table' => 'book_editions', 'order' => 'book_id, sort_order',
                'list_sql' => 'SELECT e.*, b.title AS book FROM book_editions e JOIN books b ON b.id = e.book_id',
                'columns' => ['book' => 'Book', 'name' => 'Edition', 'price_display' => 'Price', 'sort_order' => 'Order'],
                'fields' => [
                    ['book_id', 'Book', 'select', 'options' => $books, 'required' => true],
                    ['name', 'Edition name', 'text', 'required' => true, 'max' => 120, 'help' => 'For example Paperback, Kindle, Audiobook.'],
                    ['price_display', 'Display price', 'text', 'max' => 40, 'help' => 'Shown as typed. Leave blank to show no price.'],
                    ['purchase_url', 'Purchase link', 'url', 'max' => 500],
                    ['isbn', 'ISBN (optional)', 'text', 'max' => 20],
                    ['sort_order', 'Order', 'number'],
                ],
                'help' => 'Prices and purchase links stay unset until you confirm them. Nothing is shown for a blank field.',
            ],
            'tags' => [
                'group' => 'Interests', 'label' => 'Tags', 'singular' => 'tag', 'table' => 'tags', 'order' => 'name', 'aliases' => true, 'merge' => true,
                'columns' => ['name' => 'Name', 'slug' => 'Slug', 'kind' => 'Kind', 'uses' => 'Uses', 'featured' => 'Featured', 'hidden' => 'Hidden'],
                'list_sql' => 'SELECT t.*, (SELECT COUNT(*) FROM item_tags i WHERE i.tag_id = t.id) AS uses FROM tags t',
                'fields' => [
                    ['name', 'Canonical tag (public name)', 'text', 'required' => true, 'max' => 120],
                    ['slug', 'URL slug', 'text'],
                    ['description', 'Description', 'textarea', 'max' => 500, 'rows' => 3, 'help' => 'Shown at the top of the tag page.'],
                    ['aliases', 'Tag aliases / imported Blogger labels', 'aliases', 'help' => 'Other spellings that should lead here, separated by commas. Old tag addresses keep working through redirects.'],
                    ['kind', 'What kind of tag is this?', 'select', 'options' => ['subject' => 'Subject (something a reader wants to explore)', 'format' => 'Format (Story, Pictures, Book)', 'source' => 'Source (another site or an old promotional label)'], 'help' => 'Only subjects are featured on the homepage; formats and sources are still listed, further down the Interests page.'],
                    ['featured', 'Homepage featured interest', 'checkbox'],
                    ['hidden', 'Hide from the public Interests list', 'checkbox', 'help' => 'The tag page still works if someone has the address, but the tag is not listed or shown on stories.'],
                    ['sort_order', 'Order among featured interests', 'number'],
                ],
            ],
        ];
    }

    public function handle(string $method, string $path): void
    {
        header('X-Robots-Tag: noindex, nofollow');
        header('Cache-Control: no-store');
        Auth::start();
        $parts = array_values(array_filter(explode('/', substr($path, strlen('/admin'))), 'strlen'));

        if (($parts[0] ?? '') === 'login') {
            $this->login($method);
            return;
        }
        $user = Auth::user();
        if (!$user) {
            header('Location: /admin/login', true, 302);
            return;
        }
        if ($method === 'POST' && !Auth::checkCsrf()) {
            http_response_code(400);
            $this->page('Session expired', '<p role="alert">Your form expired. Go back, reload the page and try again.</p>');
            return;
        }
        $res = $parts[0] ?? '';
        if ($res === '') {
            $this->dashboard();
        } elseif ($res === 'logout') {
            Auth::logout();
            header('Location: /admin/login', true, 302);
        } elseif ($res === 'settings') {
            $this->settings($method);
        } elseif ($res === 'inbox') {
            // old address (the alert emails used it): send the visitor to whichever list has something new
            $b = $this->badges();
            header('Location: ' . ($b['comments'] === 0 && $b['messages'] > 0 ? '/admin/messages' : '/admin/comments'), true, 303);
        } elseif ($res === 'comments') {
            $this->comments($method, array_slice($parts, 1));
        } elseif ($res === 'messages') {
            $this->messages($method, array_slice($parts, 1));
        } elseif (isset($this->resources()[$res])) {
            $this->resource($res, $method, array_slice($parts, 1));
        } else {
            http_response_code(404);
            $this->page('Not found', '<p>That admin page does not exist.</p>');
        }
    }

    // ---- sign-in -------------------------------------------------------------------------------------------

    private function login(string $method): void
    {
        $error = '';
        if ($method === 'POST') {
            $email = strtolower(trim((string) ($_POST['email'] ?? '')));
            if (!Auth::checkCsrf()) {
                $error = 'Your form expired. Please try again.';
            } elseif (!$this->validEmail($email) || ($_POST['password'] ?? '') === '') {
                $error = 'Enter a valid email address and your password.';
            } elseif (Auth::throttled($email)) {
                $error = 'Too many attempts. Wait 15 minutes and try again.';
            } elseif (Auth::attempt($email, (string) $_POST['password'])) {
                header('Location: /admin', true, 303);
                return;
            } else {
                $error = 'That email and password do not match.';
            }
        }
        header('Content-Type: text/html; charset=utf-8');
        echo View::partial('admin/login', ['error' => $error, 'csrf' => Auth::csrf()]);
    }

    private function validEmail(string $email): bool
    {
        return strlen($email) >= 5 && preg_match('/^[^@\s]+@[^@\s]+\.[^@\s]+$/', $email) === 1;
    }

    // ---- screens -------------------------------------------------------------------------------------------

    private function dashboard(): void
    {
        $count = fn(string $sql) => (int) $this->pdo->query($sql)->fetchColumn();
        $stats = [
            'Published stories' => $count('SELECT COUNT(*) FROM stories WHERE status = "published" AND published_at <= UTC_TIMESTAMP()'),
            'Scheduled' => $count('SELECT COUNT(*) FROM stories WHERE status = "published" AND published_at > UTC_TIMESTAMP()'),
            'Drafts' => $count('SELECT COUNT(*) FROM stories WHERE status = "draft"'),
            'Tags' => $count('SELECT COUNT(*) FROM tags'),
            'Gallery images' => $count('SELECT COUNT(*) FROM gallery_items'),
        ];
        $recent = $this->pdo->query('SELECT id, title, status, updated_at FROM stories ORDER BY updated_at DESC LIMIT 8')->fetchAll();
        $this->page('Dashboard', View::partial('admin/dashboard', ['stats' => $stats, 'recent' => $recent]));
    }

    private function settings(string $method): void
    {
        $names = ['site_title' => 'Site title', 'author_name' => 'Pen name (shown on stories and books)', 'tagline' => 'Tagline', 'home_eyebrow' => 'Homepage small heading',
            'home_headline' => 'Homepage headline', 'home_intro' => 'Homepage introduction', 'footer_note' => 'Footer note',
            'contact_email' => 'Email address that receives contact messages and comment notices',
            'turnstile_site_key' => 'Cloudflare Turnstile site key (the public one)',
            'turnstile_secret_key' => 'Cloudflare Turnstile secret key (kept private; leave blank to keep the current one)',
            'google_site_verification' => 'Google Search Console verification code (just the code from the meta tag)'];
        $errors = [];
        if ($method === 'POST') {
            foreach ($names as $n => $label) {
                $v = trim((string) ($_POST[$n] ?? ''));
                if ($n === 'site_title' && $v === '') {
                    $errors[] = 'Site title is required.';
                }
                $names[$n] = $label;
                $_POST[$n] = $v;
            }
            if ($_POST['contact_email'] !== '' && !\App\Web\Forms::emailOk($_POST['contact_email'])) {
                $errors[] = 'The contact email address does not look right.';
            }
            if (!$errors) {
                $st = $this->pdo->prepare('INSERT INTO settings (name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)');
                foreach (array_keys($names) as $n) {
                    if ($n === 'turnstile_secret_key' && $_POST[$n] === '') {
                        continue;   // blank means "keep the one already saved"
                    }
                    $st->execute([$n, $_POST[$n]]);
                }
                $this->flash('Settings saved.');
                header('Location: /admin/settings', true, 303);
                return;
            }
        }
        $values = $this->pdo->query('SELECT name, value FROM settings')->fetchAll(PDO::FETCH_KEY_PAIR);
        $this->page('Settings', View::partial('admin/settings', ['names' => $names, 'values' => ($method === 'POST' ? $_POST : $values) + ['turnstile_secret_saved' => ($values['turnstile_secret_key'] ?? '') !== '' ? '1' : ''], 'errors' => $errors, 'csrf' => Auth::csrf()]));
    }

    /** @param list<string> $rest */
    private function resource(string $key, string $method, array $rest): void
    {
        $def = $this->resources()[$key];
        $id = ($rest[0] ?? '') === 'new' ? 0 : (int) ($rest[0] ?? 0);
        $action = $rest[1] ?? '';

        if ($rest === []) {
            $this->listing($key, $def);
            return;
        }
        if ($method === 'POST' && $action === 'delete' && $id) {
            $this->delete($key, $def, $id);
            return;
        }
        if ($method === 'POST' && ($rest[0] ?? '') === 'bulk' && !empty($def['bulk_status'])) {
            $this->bulkStatus($key, $def);
            return;
        }
        if ($key === 'gallery' && ($rest[0] ?? '') === 'duplicates') {
            $this->duplicates();
            return;
        }
        if ($key === 'gallery' && ($rest[0] ?? '') === 'upload') {
            $this->bulkUpload($method);
            return;
        }
        if ($method === 'POST' && $action === 'merge' && $id && !empty($def['merge'])) {
            $this->mergeTag($id, (int) ($_POST['target_id'] ?? 0));
            return;
        }
        if ($method === 'POST' && $action === 'restore' && $id && !empty($def['revisions'])) {
            $this->restore($key, $id, (int) ($rest[2] ?? 0));
            return;
        }
        $row = null;
        if ($id) {
            $st = $this->pdo->prepare("SELECT * FROM {$def['table']} WHERE id = ?");
            $st->execute([$id]);
            $row = $st->fetch();
            if (!$row) {
                http_response_code(404);
                $this->page('Not found', '<p>That record does not exist.</p>');
                return;
            }
        } elseif (($rest[0] ?? '') !== 'new') {
            http_response_code(404);
            $this->page('Not found', '<p>That admin page does not exist.</p>');
            return;
        }
        $errors = [];
        $values = $row ?? $this->defaults($key);
        if ($row && !empty($def['tags'])) {
            $values['tags'] = implode(', ', $this->tagNames($def['tags'], $id));
        }
        if ($row && !empty($def['aliases'])) {
            $a = $this->pdo->prepare('SELECT alias_slug FROM tag_aliases WHERE tag_id = ? ORDER BY alias_slug');
            $a->execute([$id]);
            $values['aliases'] = implode(', ', $a->fetchAll(PDO::FETCH_COLUMN));
        }
        if ($method === 'POST') {
            [$data, $errors] = $this->collect($key, $def, $row);
            $values = array_merge($values, $data, ['tags' => (string) ($_POST['tags'] ?? ''), 'aliases' => (string) ($_POST['aliases'] ?? '')]);
            if (!$errors) {
                $savedId = $this->save($key, $def, $id, $data, $row);
                if (!empty($def['tags'])) {
                    $this->syncTags($def['tags'], $savedId, (string) ($_POST['tags'] ?? ''));
                }
                if (!empty($def['aliases'])) {
                    $this->syncAliases($savedId, (string) ($_POST['aliases'] ?? ''), (string) $data['slug']);
                }
                $this->flash(ucfirst($def['singular']) . ' saved.');
                header('Location: /admin/' . $key . '/' . $savedId, true, 303);
                return;
            }
            http_response_code(422);
        }
        $revisions = [];
        if ($id && !empty($def['revisions'])) {
            $r = $this->pdo->prepare('SELECT id, title, saved_at FROM story_revisions WHERE story_id = ? ORDER BY id DESC LIMIT 20');
            $r->execute([$id]);
            $revisions = $r->fetchAll();
        }
        $allTags = $this->pdo->query('SELECT name FROM tags ORDER BY name')->fetchAll(PDO::FETCH_COLUMN);
        $this->page(($id ? 'Edit ' : 'New ') . $def['singular'], View::partial('admin/form', [
            'key' => $key, 'def' => $def, 'id' => $id, 'values' => $values, 'errors' => $errors, 'csrf' => Auth::csrf(),
            'revisions' => $revisions, 'allTags' => $allTags, 'tz' => $this->tz()->getName(),
            'mergeTargets' => $id && !empty($def['merge']) ? $this->lookupExcept('SELECT id, name AS title FROM tags WHERE id <> ' . $id . ' ORDER BY name') : [],
        ]));
    }

    /** @param array<string,mixed> $def */
    private function listing(string $key, array $def): void
    {
        $sql = $def['list_sql'] ?? "SELECT * FROM {$def['table']}";
        $where = [];
        $args = [];
        foreach ($def['filters'] ?? [] as $col => $options) {
            $v = (string) ($_GET[$col] ?? '');
            if ($v !== '' && array_key_exists($v, $options)) {
                $where[] = ($def['list_sql'] ?? false ? '' : '') . "$col = ?";
                $args[] = $v;
            }
        }
        $per = (int) ($def['per_page'] ?? 0);
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $total = 0;
        if ($per) {
            $cnt = $this->pdo->prepare('SELECT COUNT(*) FROM (' . $sql . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ') x');
            $cnt->execute($args);
            $total = (int) $cnt->fetchColumn();
            $page = min($page, max(1, (int) ceil($total / $per)));
        }
        $st = $this->pdo->prepare($sql . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY ' . $def['order'] . ($per ? ' LIMIT ' . $per . ' OFFSET ' . (($page - 1) * $per) : ' LIMIT 3000'));
        $st->execute($args);
        $this->page($def['label'], View::partial('admin/list', ['key' => $key, 'def' => $def, 'rows' => $st->fetchAll(), 'csrf' => Auth::csrf(), 'tz' => $this->tz(),
            'page' => $page, 'pages' => $per ? (int) ceil($total / $per) : 1, 'total' => $total]));
    }

    // ---- data handling -------------------------------------------------------------------------------------

    /** @return array<string,mixed> */
    private function defaults(string $key): array
    {
        return match ($key) {
            'stories' => ['status' => 'draft', 'published_at' => Db::utcNow(), 'author' => '', 'kind' => 'story', 'chapter_order' => 1],
            'navigation' => ['visible' => 1, 'location' => 'header', 'sort_order' => 100],
            'redirects' => ['code' => 301],
            default => ['status' => 'draft', 'sort_order' => 0],
        };
    }

    /**
     * Reads the POST data for a resource, mirroring each field's browser rules on the server.
     * @param array<string,mixed> $def @param array<string,mixed>|null $row
     * @return array{0:array<string,mixed>,1:list<string>}
     */
    private function collect(string $key, array $def, ?array $row): array
    {
        $data = [];
        $errors = [];
        foreach ($def['fields'] as $f) {
            [$name, $label, $type] = $f;
            if ($type === 'tags' || $type === 'aliases') {
                continue;
            }
            $raw = $_POST[$name] ?? '';
            $required = !empty($f['required']);
            switch ($type) {
                case 'image':
                    $up = $_FILES[$name] ?? null;
                    if ($up && ($up['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                        try {
                            $img = Images::store($up['tmp_name'], $f['dir']);
                            $data[$name] = $img['file'];
                            foreach ($f['extra'] ?? [] as $k => $col) {
                                $data[$col] = $img[$k];
                            }
                        } catch (\InvalidArgumentException $e) {
                            $errors[] = "$label: " . $e->getMessage();
                        }
                    } elseif ($up && ($up['error'] ?? 0) !== UPLOAD_ERR_NO_FILE) {
                        $errors[] = "$label: the upload failed. Try a smaller file.";
                    } elseif ($required && empty($row[$name])) {
                        $errors[] = "$label is required.";
                    }
                    if (!empty($_POST[$name . '_remove']) && empty($f['required'])) {
                        $data[$name] = null;
                    }
                    continue 2;
                case 'checkbox':
                    $data[$name] = empty($raw) ? 0 : 1;
                    continue 2;
                case 'number':
                    $data[$name] = (int) $raw;
                    continue 2;
                case 'html':
                    $val = HtmlSanitizer::clean((string) $raw);
                    break;
                case 'datetime':
                    $val = trim((string) $raw);
                    if ($val === '') {
                        $data[$name] = null;
                        if ($required) {
                            $errors[] = "$label is required.";
                        }
                        continue 2;
                    }
                    try {
                        $val = (new DateTimeImmutable($val, $this->tz()))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
                    } catch (\Exception) {
                        $errors[] = "$label is not a valid date and time.";
                        continue 2;
                    }
                    break;
                case 'date':
                    $val = trim((string) $raw);
                    if ($val !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $val)) {
                        $errors[] = "$label is not a valid date.";
                    }
                    $data[$name] = $val === '' ? null : $val;
                    continue 2;
                case 'select':
                    $val = (string) $raw;
                    if (!array_key_exists($val, $f['options'])) {
                        $errors[] = "$label has an invalid choice.";
                        continue 2;
                    }
                    if ($val === '' && $required) {
                        $errors[] = "$label is required.";
                    }
                    if (!empty($f['nullable']) || str_ends_with($name, '_id')) {
                        $data[$name] = $val === '' ? null : (int) $val;
                        continue 2;
                    }
                    break;
                default:
                    $val = trim((string) $raw);
            }
            if ($required && trim(strip_tags((string) $val)) === '' && $type !== 'html') {
                $errors[] = "$label is required.";
            }
            if ($type === 'html' && $required && trim(strip_tags($val, '<img>')) === '') {
                $errors[] = "$label is required.";
            }
            if (isset($f['max']) && mb_strlen((string) $val) > $f['max']) {
                $errors[] = "$label must be {$f['max']} characters or fewer.";
            }
            if ($type === 'url' && $val !== '' && !preg_match('#^https?://\S+$#i', $val)) {
                $errors[] = "$label must start with http:// or https://.";
            }
            $data[$name] = $val;
        }

        // Per-resource rules
        if (isset($data['slug']) || in_array($key, ['stories', 'pages', 'tags', 'books', 'series', 'albums'], true)) {
            $base = isset($data['slug']) && $data['slug'] !== '' ? $data['slug'] : ($data['title'] ?? $data['name'] ?? '');
            $data['slug'] = $this->uniqueSlug($def['table'], slugify((string) $base), (int) ($row['id'] ?? 0));
        }
        if ($key === 'stories') {
            $data['word_count'] = word_count((string) ($data['body_html'] ?? ''));
            if (($data['legacy_path'] ?? '') === '') {
                $data['legacy_path'] = null;
            } elseif (!preg_match('#^/\d{4}/\d{2}/[^/]+\.html$#', (string) $data['legacy_path'])) {
                $errors[] = 'Old Blogger path must look like /2012/12/forbidden-fruit.html.';
            }
            if (($data['status'] ?? '') === 'published' && empty($data['published_at'])) {
                $data['published_at'] = Db::utcNow();
            }
        }
        if ($key === 'pages') {
            if (($data['legacy_path'] ?? '') === '') {
                $data['legacy_path'] = null;
            } elseif (!preg_match('#^/p/[^/]+\.html$#', (string) $data['legacy_path'])) {
                $errors[] = 'Old Blogger path must look like /p/about.html.';
            }
        }
        if ($key === 'redirects') {
            if (!str_starts_with((string) $data['from_path'], '/') || str_starts_with((string) $data['from_path'], '/admin')) {
                $errors[] = 'From path must start with / and cannot be under /admin.';
            }
            if (!preg_match('#^(/(?!/)|https?://)#i', (string) $data['to_url'])) {
                $errors[] = 'Send to must be a path starting with / or a full https:// address.';
            }
            $data['code'] = (int) $data['code'];
        }
        if ($key === 'navigation' && !preg_match('#^(/(?!/)|https?://|mailto:)#i', (string) ($data['url'] ?? ''))) {
            $errors[] = 'Link must be a path starting with / or a full https:// address.';
        }
        // Unique legacy_path
        foreach (['legacy_path'] as $col) {
            if (!empty($data[$col]) && in_array($key, ['stories', 'pages'], true)) {
                $st = $this->pdo->prepare("SELECT COUNT(*) FROM {$def['table']} WHERE $col = ? AND id <> ?");
                $st->execute([$data[$col], (int) ($row['id'] ?? 0)]);
                if ($st->fetchColumn()) {
                    $errors[] = 'Another record already uses that old Blogger path.';
                }
            }
        }
        if ($key === 'redirects') {
            $st = $this->pdo->prepare('SELECT COUNT(*) FROM redirects WHERE from_path = ? AND id <> ?');
            $st->execute([$data['from_path'], (int) ($row['id'] ?? 0)]);
            if ($st->fetchColumn()) {
                $errors[] = 'A redirect from that path already exists.';
            }
        }
        return [$data, $errors];
    }

    private function uniqueSlug(string $table, string $slug, int $selfId): string
    {
        $candidate = $slug;
        for ($i = 2; $i < 500; $i++) {
            $st = $this->pdo->prepare("SELECT COUNT(*) FROM $table WHERE slug = ? AND id <> ?");
            $st->execute([$candidate, $selfId]);
            if (!(int) $st->fetchColumn()) {
                return $candidate;
            }
            $candidate = $slug . '-' . $i;
        }
        return $slug . '-' . bin2hex(random_bytes(3));
    }

    /** @param array<string,mixed> $def @param array<string,mixed> $data @param array<string,mixed>|null $row */
    private function save(string $key, array $def, int $id, array $data, ?array $row): int
    {
        $now = Db::utcNow();
        if ($key === 'stories' && $row && ($row['body_html'] !== $data['body_html'] || $row['title'] !== $data['title'])) {
            $this->pdo->prepare('INSERT INTO story_revisions (story_id, title, body_html, saved_at) VALUES (?,?,?,?)')->execute([$id, $row['title'], $row['body_html'], $now]);
            $this->pdo->prepare('DELETE FROM story_revisions WHERE story_id = ? AND id NOT IN (SELECT id FROM (SELECT id FROM story_revisions WHERE story_id = ? ORDER BY id DESC LIMIT 20) k)')->execute([$id, $id]);
        }
        // Replace images drop the old files.
        foreach ($def['fields'] as $f) {
            if ($f[2] === 'image' && $row && array_key_exists($f[0], $data) && $data[$f[0]] !== $row[$f[0]]) {
                Images::delete($row[$f[0]]);
                foreach ($f['extra'] ?? [] as $k => $col) {
                    if ($k === 'thumb') {
                        Images::delete($row[$col]);
                    }
                }
            }
        }
        if ($key === 'stories') {
            $data['search_text'] = search_plain((string) ($data['body_html'] ?? ''));
        }
        $cols = array_keys($data);
        if ($id) {
            if (in_array($def['table'], ['stories', 'pages', 'books'], true)) {
                $data['updated_at'] = $now;
                $cols[] = 'updated_at';
            }
            $set = implode(', ', array_map(fn($c) => "$c = :$c", $cols));
            $data['__id'] = $id;
            $this->pdo->prepare("UPDATE {$def['table']} SET $set WHERE id = :__id")->execute($this->named($data));
            return $id;
        }
        if (in_array($def['table'], ['stories', 'pages', 'books'], true)) {
            $data['created_at'] = $data['updated_at'] = $now;
            array_push($cols, 'created_at', 'updated_at');
        } elseif (in_array($def['table'], ['tags', 'gallery_items', 'redirects', 'series', 'albums'], true)) {
            $data['created_at'] = $now;
            $cols[] = 'created_at';
        }
        $sql = "INSERT INTO {$def['table']} (" . implode(', ', $cols) . ') VALUES (' . implode(', ', array_map(fn($c) => ":$c", $cols)) . ')';
        $this->pdo->prepare($sql)->execute($this->named($data));
        return (int) $this->pdo->lastInsertId();
    }

    /** @param array<string,mixed> $data @return array<string,mixed> */
    private function named(array $data): array
    {
        $out = [];
        foreach ($data as $k => $v) {
            $out[':' . $k] = $v;
        }
        return $out;
    }

    /** @param array<string,mixed> $def */
    private function delete(string $key, array $def, int $id): void
    {
        $st = $this->pdo->prepare("SELECT * FROM {$def['table']} WHERE id = ?");
        $st->execute([$id]);
        if ($row = $st->fetch()) {
            $shared = false;
            if ($key === 'gallery') {   // never remove a picture file another entry still uses
                $u = $this->pdo->prepare('SELECT COUNT(*) FROM gallery_items WHERE file = ? AND id <> ?');
                $u->execute([$row['file'], $id]);
                $shared = (int) $u->fetchColumn() > 0;
            }
            foreach ($def['fields'] as $f) {
                if ($f[2] === 'image' && !$shared) {
                    Images::delete($row[$f[0]] ?? null);
                    foreach ($f['extra'] ?? [] as $k => $col) {
                        if ($k === 'thumb') {
                            Images::delete($row[$col] ?? null);
                        }
                    }
                }
            }
            if (!empty($def['tags'])) {
                $this->pdo->prepare('DELETE FROM item_tags WHERE item_type = ? AND item_id = ?')->execute([$def['tags'], $id]);
            }
            if ($key === 'series') {
                $this->pdo->prepare('UPDATE stories SET series_id = NULL WHERE series_id = ?')->execute([$id]);
            } elseif ($key === 'albums') {
                $this->pdo->prepare('UPDATE gallery_items SET album_id = NULL WHERE album_id = ?')->execute([$id]);
            }
            $this->pdo->prepare("DELETE FROM {$def['table']} WHERE id = ?")->execute([$id]);
            $this->flash(ucfirst($def['singular']) . ' deleted.');
        }
        $back = (string) ($_POST['back'] ?? '');
        header('Location: ' . (preg_match('#^/admin/[a-z/]+(\?[a-z0-9=&]*)?$#', $back) ? $back : '/admin/' . $key), true, 303);
    }

    private function restore(string $key, int $storyId, int $revId): void
    {
        $st = $this->pdo->prepare('SELECT * FROM story_revisions WHERE id = ? AND story_id = ?');
        $st->execute([$revId, $storyId]);
        if ($rev = $st->fetch()) {
            $cur = $this->pdo->prepare('SELECT title, body_html FROM stories WHERE id = ?');
            $cur->execute([$storyId]);
            if ($c = $cur->fetch()) {
                $this->pdo->prepare('INSERT INTO story_revisions (story_id, title, body_html, saved_at) VALUES (?,?,?,?)')->execute([$storyId, $c['title'], $c['body_html'], Db::utcNow()]);
            }
            $this->pdo->prepare('UPDATE stories SET title = ?, body_html = ?, search_text = ?, word_count = ?, updated_at = ? WHERE id = ?')
                ->execute([$rev['title'], $rev['body_html'], search_plain($rev['body_html']), word_count($rev['body_html']), Db::utcNow(), $storyId]);
            $this->flash('Earlier version restored.');
        }
        header('Location: /admin/stories/' . $storyId, true, 303);
    }

    /**
     * Publish or hide many images or albums at once, from the checkboxes on the list (or every draft in one go).
     * @param array<string,mixed> $def
     */
    private function bulkStatus(string $key, array $def): void
    {
        $do = (string) ($_POST['do'] ?? '');
        $ids = array_values(array_filter(array_map('intval', (array) ($_POST['ids'] ?? []))));
        $table = $def['table'];
        $n = 0;
        if ($do === 'publish_all_drafts') {
            $n = $this->pdo->exec("UPDATE $table SET status = 'published' WHERE status = 'draft'");
            if ($key === 'albums') {
                $this->pdo->exec("UPDATE gallery_items SET status = 'published' WHERE album_id IN (SELECT id FROM albums WHERE status = 'published')");
            }
        } elseif ($ids && in_array($do, ['publish', 'draft', 'publish_with_images'], true)) {
            $in = implode(',', array_fill(0, count($ids), '?'));
            $st = $this->pdo->prepare("UPDATE $table SET status = ? WHERE id IN ($in)");
            $st->execute([$do === 'draft' ? 'draft' : 'published', ...$ids]);
            $n = $st->rowCount();
            if ($key === 'albums' && $do === 'publish_with_images') {
                $this->pdo->prepare("UPDATE gallery_items SET status = 'published' WHERE album_id IN ($in)")->execute($ids);
            }
            if ($key === 'albums' && $do === 'draft') {
                $this->pdo->prepare("UPDATE gallery_items SET status = 'draft' WHERE album_id IN ($in)")->execute($ids);
            }
        } else {
            $this->flash('Tick at least one row first.');
            header('Location: /admin/' . $key, true, 303);
            return;
        }
        $this->flash(match ($do) {
            'draft' => "$n " . $def['singular'] . ($n === 1 ? '' : 's') . ' moved back to draft.',
            default => "$n " . $def['singular'] . ($n === 1 ? '' : 's') . ' published.',
        });
        header('Location: /admin/' . $key, true, 303);
    }

    /** @return array<string,string> id => title for a query returning id and title */
    private function lookupExcept(string $sql): array
    {
        $out = ['' => 'Choose a tag'];
        foreach ($this->pdo->query($sql) as $r) {
            $out[(string) $r['id']] = $r['title'];
        }
        return $out;
    }

    /** Moves everything tagged with $id onto $target, keeps the old address working as an alias, and removes the old tag. */
    private function mergeTag(int $id, int $target): void
    {
        $exists = $this->pdo->prepare('SELECT slug FROM tags WHERE id = ?');
        $exists->execute([$target]);
        $to = $exists->fetchColumn();
        $exists->execute([$id]);
        $from = $exists->fetchColumn();
        if ($target === $id || !$to || !$from) {
            $this->flash('Choose a different tag to merge into.');
            header('Location: /admin/tags/' . $id, true, 303);
            return;
        }
        $this->pdo->beginTransaction();
        $this->pdo->prepare('INSERT IGNORE INTO item_tags (tag_id, item_type, item_id) SELECT ?, item_type, item_id FROM item_tags WHERE tag_id = ?')->execute([$target, $id]);
        $this->pdo->prepare('UPDATE tag_aliases SET tag_id = ? WHERE tag_id = ?')->execute([$target, $id]);
        $this->pdo->prepare('INSERT INTO tag_aliases (alias_slug, tag_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE tag_id = VALUES(tag_id)')->execute([$from, $target]);
        $this->pdo->prepare('DELETE FROM tags WHERE id = ?')->execute([$id]);
        $this->pdo->commit();
        $this->flash("Merged into that tag. The old address /tag/$from now redirects to /tag/$to.");
        header('Location: /admin/tags/' . $target, true, 303);
    }

    private function syncAliases(int $tagId, string $csv, string $ownSlug): void
    {
        $this->pdo->prepare('DELETE FROM tag_aliases WHERE tag_id = ?')->execute([$tagId]);
        foreach (explode(',', $csv) as $a) {
            $slug = slugify(trim($a));
            if (trim($a) === '' || $slug === $ownSlug) {
                continue;
            }
            $taken = $this->pdo->prepare('SELECT COUNT(*) FROM tags WHERE slug = ?');
            $taken->execute([$slug]);
            if ((int) $taken->fetchColumn() === 0) {      // an alias can never shadow a real tag
                $this->pdo->prepare('INSERT INTO tag_aliases (alias_slug, tag_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE tag_id = VALUES(tag_id)')->execute([$slug, $tagId]);
            }
        }
    }

    /** Add several images at once. They land as drafts so each can be described and checked before it goes public. */
    private function bulkUpload(string $method): void
    {
        $albums = $this->lookup('SELECT id, title FROM albums ORDER BY sort_order, title', 'No album');
        $errors = [];
        $added = 0;
        if ($method === 'POST') {
            $album = (int) ($_POST['album_id'] ?? 0) ?: null;
            $files = $_FILES['files'] ?? null;
            if ($files && is_array($files['name'])) {
                $order = (int) $this->pdo->query('SELECT COALESCE(MAX(sort_order), 0) FROM gallery_items')->fetchColumn();
                foreach ($files['name'] as $i => $name) {
                    if (($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                        continue;
                    }
                    if ($files['error'][$i] !== UPLOAD_ERR_OK) {
                        $errors[] = "$name: the upload failed. Try a smaller file.";
                        continue;
                    }
                    try {
                        $img = Images::store($files['tmp_name'][$i], 'gallery');
                    } catch (\InvalidArgumentException $e) {
                        $errors[] = "$name: " . $e->getMessage();
                        continue;
                    }
                    $title = ucfirst(trim(preg_replace('/[_\-]+|\s+/', ' ', pathinfo($name, PATHINFO_FILENAME)) ?? ''));
                    $this->pdo->prepare('INSERT INTO gallery_items (album_id, title, alt_text, file, thumb, width, height, status, sort_order, created_at) VALUES (?,?,?,?,?,?,?,"draft",?,?)')
                        ->execute([$album, $title !== '' ? $title : 'Untitled', $title, $img['file'], $img['thumb'], $img['width'], $img['height'], ++$order, Db::utcNow()]);
                    $id = (int) $this->pdo->lastInsertId();
                    $this->syncTags('gallery', $id, (string) ($_POST['tags'] ?? ''));
                    $added++;
                }
            }
            if (!$errors) {
                $this->flash($added ? "$added image" . ($added === 1 ? '' : 's') . ' added as drafts. Add descriptions and credits, then publish them.' : 'Choose at least one image.');
                header('Location: /admin/gallery', true, 303);
                return;
            }
            http_response_code(422);
        }
        $this->page('Add images to the library', View::partial('admin/bulk', ['albums' => $albums, 'errors' => $errors, 'added' => $added ?? 0, 'csrf' => Auth::csrf(),
            'allTags' => $this->pdo->query('SELECT name FROM tags ORDER BY name')->fetchAll(PDO::FETCH_COLUMN)]));
    }

    // ---- tags ----------------------------------------------------------------------------------------------

    /** @return list<string> */
    private function tagNames(string $type, int $id): array
    {
        $st = $this->pdo->prepare('SELECT t.name FROM tags t JOIN item_tags i ON i.tag_id = t.id WHERE i.item_type = ? AND i.item_id = ? ORDER BY t.name');
        $st->execute([$type, $id]);
        return $st->fetchAll(PDO::FETCH_COLUMN);
    }

    public function syncTags(string $type, int $id, string $csv): void
    {
        $names = [];
        foreach (explode(',', $csv) as $n) {
            $n = trim(preg_replace('/\s+/', ' ', $n) ?? '');
            if ($n !== '') {
                $names[mb_strtolower($n)] ??= mb_substr($n, 0, 120);
            }
        }
        $this->pdo->prepare('DELETE FROM item_tags WHERE item_type = ? AND item_id = ?')->execute([$type, $id]);
        foreach ($names as $name) {
            $slug = slugify($name);
            $st = $this->pdo->prepare('SELECT id FROM tags WHERE slug = ?');
            $st->execute([$slug]);
            $tagId = $st->fetchColumn();
            if (!$tagId) {
                $this->pdo->prepare('INSERT INTO tags (slug, name, created_at) VALUES (?,?,?)')->execute([$slug, $name, Db::utcNow()]);
                $tagId = $this->pdo->lastInsertId();
            }
            $this->pdo->prepare('INSERT IGNORE INTO item_tags (tag_id, item_type, item_id) VALUES (?,?,?)')->execute([$tagId, $type, $id]);
        }
    }

    // ---- shell ---------------------------------------------------------------------------------------------

    private function tz(): DateTimeZone
    {
        try {
            return new DateTimeZone(Env::get('APP_TIMEZONE', 'America/New_York') ?: 'America/New_York');
        } catch (\Exception) {
            return new DateTimeZone('America/New_York');
        }
    }

    private function flash(string $msg): void
    {
        $_SESSION['flash'] = $msg;
    }

    private function duplicates(): void
    {
        $waiting = \App\Media\Dupes::fill($this->pdo, 600);
        $groups = $waiting ? [] : \App\Media\Dupes::groups($this->pdo);
        $this->page('Duplicate images', View::partial('admin/duplicates', ['groups' => $groups, 'waiting' => $waiting, 'csrf' => Auth::csrf()]));
    }

    /** Comments left on this site: approve, hide again, or delete. */
    private function comments(string $method, array $rest): void
    {
        if ($method === 'POST') {
            [$id, $do] = array_pad($rest, 2, '');
            $id = (int) $id;
            if ($id) {
                if ($do === 'approve') {
                    $this->pdo->prepare('UPDATE comments_archive SET status = "LIVE" WHERE id = ? AND source_id LIKE "site:%"')->execute([$id]);
                    $this->flash('Comment approved. It is now visible under the story.');
                } elseif ($do === 'hide') {
                    $this->pdo->prepare('UPDATE comments_archive SET status = "pending" WHERE id = ? AND source_id LIKE "site:%"')->execute([$id]);
                    $this->flash('Comment hidden again. It is back in the waiting list.');
                } elseif ($do === 'delete') {
                    $this->pdo->prepare('DELETE FROM comments_archive WHERE id = ? AND source_id LIKE "site:%"')->execute([$id]);   // only comments left on this site, never the imported ones
                    $this->flash('Comment deleted.');
                }
            }
            header('Location: /admin/comments', true, 303);
            return;
        }
        $sel = 'SELECT c.id, c.author, c.author_email, c.body_html, c.commented_at, s.title AS story, s.slug, s.legacy_path FROM comments_archive c LEFT JOIN stories s ON s.id = c.story_id ';
        $waiting = $this->pdo->query($sel . 'WHERE c.status = "pending" ORDER BY c.commented_at')->fetchAll();
        $approved = $this->pdo->query($sel . 'WHERE c.status = "LIVE" AND c.source_id LIKE "site:%" ORDER BY c.commented_at DESC LIMIT 30')->fetchAll();
        $this->page('Comments', View::partial('admin/comments', ['waiting' => $waiting, 'approved' => $approved, 'csrf' => Auth::csrf(), 'tz' => $this->tz()]));
    }

    /** Messages from the contact form. */
    private function messages(string $method, array $rest): void
    {
        if ($method === 'POST') {
            [$id, $do] = array_pad($rest, 2, '');
            if ($id === 'all' && $do === 'read') {
                $this->pdo->exec('UPDATE contact_messages SET status = "read" WHERE status = "new"');
                $this->flash('All messages marked as read.');
            } elseif ((int) $id) {
                $id = (int) $id;
                if ($do === 'read') {
                    $this->pdo->prepare('UPDATE contact_messages SET status = "read" WHERE id = ?')->execute([$id]);
                } elseif ($do === 'unread') {
                    $this->pdo->prepare('UPDATE contact_messages SET status = "new" WHERE id = ?')->execute([$id]);
                } elseif ($do === 'delete') {
                    $this->pdo->prepare('DELETE FROM contact_messages WHERE id = ?')->execute([$id]);
                    $this->flash('Message deleted.');
                }
            }
            header('Location: /admin/messages', true, 303);
            return;
        }
        $messages = $this->pdo->query('SELECT * FROM contact_messages ORDER BY (status = "new") DESC, created_at DESC LIMIT 200')->fetchAll();
        $this->page('Messages', View::partial('admin/messages', ['messages' => $messages, 'csrf' => Auth::csrf(), 'tz' => $this->tz()]));
    }

    /** @return array{comments:int,messages:int} what is waiting for Mike: comments to approve, messages not yet read */
    private function badges(): array
    {
        try {
            return [
                'comments' => (int) $this->pdo->query('SELECT COUNT(*) FROM comments_archive WHERE status = "pending"')->fetchColumn(),
                'messages' => (int) $this->pdo->query('SELECT COUNT(*) FROM contact_messages WHERE status = "new"')->fetchColumn(),
            ];
        } catch (\Throwable) {
            return ['comments' => 0, 'messages' => 0];
        }
    }

    private function page(string $title, string $body): void
    {
        $flash = $_SESSION['flash'] ?? '';
        unset($_SESSION['flash']);
        header('Content-Type: text/html; charset=utf-8');
        echo View::partial('admin/layout', ['title' => $title, 'body' => $body, 'flash' => $flash, 'csrf' => Auth::csrf(), 'user' => Auth::user(), 'resources' => $this->resources(), 'badges' => $this->badges()]);
    }
}
