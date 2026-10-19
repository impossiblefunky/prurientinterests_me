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

    /** @return array<string,array<string,mixed>> */
    private function resources(): array
    {
        $status = ['draft' => 'Draft', 'published' => 'Published'];
        return [
            'stories' => [
                'label' => 'Stories', 'singular' => 'story', 'table' => 'stories', 'tags' => 'story', 'revisions' => true, 'order' => 'COALESCE(published_at, created_at) DESC',
                'columns' => ['title' => 'Title', 'status' => 'Status', 'published_at' => 'Publish date', 'word_count' => 'Words'],
                'fields' => [
                    ['title', 'Title', 'text', 'required' => true, 'max' => 255],
                    ['slug', 'URL slug', 'text', 'help' => 'Leave blank to build it from the title. New stories live at /story/slug.'],
                    ['summary', 'Summary', 'textarea', 'max' => 500, 'rows' => 3, 'help' => 'Shown on cards and in search results. Leave blank to use the opening text.'],
                    ['body_html', 'Story', 'html', 'required' => true],
                    ['status', 'Status', 'select', 'options' => $status, 'help' => 'A published story with a future date stays hidden until that moment.'],
                    ['published_at', 'Publish date', 'datetime'],
                    ['author', 'Author name', 'text', 'max' => 120],
                    ['legacy_path', 'Old Blogger path', 'text', 'max' => 255, 'help' => 'Only for imported stories, e.g. /2012/12/forbidden-fruit.html. Leave it alone so the old link keeps working.'],
                    ['cover_image', 'Cover image', 'image', 'dir' => 'covers'],
                    ['tags', 'Tags', 'tags'],
                ],
            ],
            'pages' => [
                'label' => 'Pages', 'singular' => 'page', 'table' => 'pages', 'order' => 'title',
                'columns' => ['title' => 'Title', 'slug' => 'Slug', 'status' => 'Status'],
                'fields' => [
                    ['title', 'Title', 'text', 'required' => true, 'max' => 255],
                    ['slug', 'URL slug', 'text', 'help' => 'The page lives at /slug. The slug "about" is the About page.'],
                    ['legacy_path', 'Old Blogger path', 'text', 'max' => 255, 'help' => 'e.g. /p/about.html'],
                    ['summary', 'Summary', 'textarea', 'max' => 500, 'rows' => 3],
                    ['body_html', 'Content', 'html', 'required' => true],
                    ['status', 'Status', 'select', 'options' => $status],
                ],
            ],
            'gallery' => [
                'label' => 'Gallery', 'singular' => 'image', 'table' => 'gallery_items', 'tags' => 'gallery', 'order' => 'sort_order, id DESC',
                'columns' => ['title' => 'Title', 'status' => 'Status', 'sort_order' => 'Order'],
                'fields' => [
                    ['title', 'Title', 'text', 'required' => true, 'max' => 255],
                    ['file', 'Image', 'image', 'dir' => 'gallery', 'required' => true, 'extra' => ['thumb' => 'thumb', 'width' => 'width', 'height' => 'height']],
                    ['alt_text', 'Alt text', 'text', 'required' => true, 'max' => 255, 'help' => 'Describe the image for people who cannot see it.'],
                    ['caption', 'Caption', 'text', 'max' => 500],
                    ['status', 'Status', 'select', 'options' => $status],
                    ['sort_order', 'Order', 'number', 'help' => 'Lower numbers come first.'],
                    ['tags', 'Tags', 'tags'],
                ],
            ],
            'books' => [
                'label' => 'Books', 'singular' => 'book', 'table' => 'books', 'order' => 'sort_order, id DESC',
                'columns' => ['title' => 'Title', 'status' => 'Status', 'published_on' => 'Published'],
                'fields' => [
                    ['title', 'Title', 'text', 'required' => true, 'max' => 255],
                    ['slug', 'URL slug', 'text'],
                    ['subtitle', 'Subtitle', 'text', 'max' => 255],
                    ['description_html', 'Description', 'html', 'required' => true],
                    ['cover_image', 'Cover image', 'image', 'dir' => 'books'],
                    ['buy_url', 'Buy link', 'url', 'max' => 500],
                    ['buy_label', 'Buy button text', 'text', 'max' => 80],
                    ['isbn', 'ISBN', 'text', 'max' => 20],
                    ['published_on', 'Published on', 'date'],
                    ['status', 'Status', 'select', 'options' => $status],
                    ['sort_order', 'Order', 'number'],
                ],
            ],
            'tags' => [
                'label' => 'Tags', 'singular' => 'tag', 'table' => 'tags', 'order' => 'name',
                'columns' => ['name' => 'Name', 'slug' => 'Slug', 'uses' => 'Uses'],
                'list_sql' => 'SELECT t.*, (SELECT COUNT(*) FROM item_tags i WHERE i.tag_id = t.id) AS uses FROM tags t',
                'fields' => [
                    ['name', 'Name', 'text', 'required' => true, 'max' => 120],
                    ['slug', 'URL slug', 'text'],
                    ['description', 'Description', 'textarea', 'max' => 500, 'rows' => 3, 'help' => 'Shown at the top of the tag page.'],
                ],
            ],
            'navigation' => [
                'label' => 'Navigation', 'singular' => 'link', 'table' => 'nav_items', 'order' => 'location, sort_order',
                'columns' => ['label' => 'Label', 'url' => 'Link', 'location' => 'Where', 'sort_order' => 'Order'],
                'fields' => [
                    ['label', 'Label', 'text', 'required' => true, 'max' => 80],
                    ['url', 'Link', 'text', 'required' => true, 'max' => 500, 'help' => 'A path like /stories or a full https:// address.'],
                    ['location', 'Where', 'select', 'options' => ['header' => 'Header', 'footer' => 'Footer']],
                    ['sort_order', 'Order', 'number'],
                    ['visible', 'Show this link', 'checkbox'],
                ],
            ],
            'redirects' => [
                'label' => 'Redirects', 'singular' => 'redirect', 'table' => 'redirects', 'order' => 'from_path',
                'columns' => ['from_path' => 'From', 'to_url' => 'To', 'code' => 'Type', 'hits' => 'Hits'],
                'fields' => [
                    ['from_path', 'From path', 'text', 'required' => true, 'max' => 255, 'help' => 'Starts with a slash, e.g. /2012/12/old-name.html'],
                    ['to_url', 'Send to', 'text', 'required' => true, 'max' => 500],
                    ['code', 'Type', 'select', 'options' => ['301' => 'Permanent (301)', '302' => 'Temporary (302)']],
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
        $names = ['site_title' => 'Site title', 'tagline' => 'Tagline', 'footer_note' => 'Footer note'];
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
            if (!$errors) {
                $st = $this->pdo->prepare('INSERT INTO settings (name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)');
                foreach (array_keys($names) as $n) {
                    $st->execute([$n, $_POST[$n]]);
                }
                $this->flash('Settings saved.');
                header('Location: /admin/settings', true, 303);
                return;
            }
        }
        $values = $this->pdo->query('SELECT name, value FROM settings')->fetchAll(PDO::FETCH_KEY_PAIR);
        $this->page('Settings', View::partial('admin/settings', ['names' => $names, 'values' => $method === 'POST' ? $_POST : $values, 'errors' => $errors, 'csrf' => Auth::csrf()]));
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
        if ($method === 'POST') {
            [$data, $errors] = $this->collect($key, $def, $row);
            $values = array_merge($values, $data, ['tags' => (string) ($_POST['tags'] ?? '')]);
            if (!$errors) {
                $savedId = $this->save($key, $def, $id, $data, $row);
                if (!empty($def['tags'])) {
                    $this->syncTags($def['tags'], $savedId, (string) ($_POST['tags'] ?? ''));
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
        ]));
    }

    /** @param array<string,mixed> $def */
    private function listing(string $key, array $def): void
    {
        $sql = $def['list_sql'] ?? "SELECT * FROM {$def['table']}";
        $rows = $this->pdo->query($sql . ' ORDER BY ' . $def['order'] . ' LIMIT 2000')->fetchAll();
        $this->page($def['label'], View::partial('admin/list', ['key' => $key, 'def' => $def, 'rows' => $rows, 'csrf' => Auth::csrf(), 'tz' => $this->tz()]));
    }

    // ---- data handling -------------------------------------------------------------------------------------

    /** @return array<string,mixed> */
    private function defaults(string $key): array
    {
        return match ($key) {
            'stories' => ['status' => 'draft', 'published_at' => Db::utcNow(), 'author' => ''],
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
            if ($type === 'tags') {
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
        if (isset($data['slug']) || in_array($key, ['stories', 'pages', 'tags', 'books'], true)) {
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
        } elseif (in_array($def['table'], ['tags', 'gallery_items', 'redirects'], true)) {
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
            foreach ($def['fields'] as $f) {
                if ($f[2] === 'image') {
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
            $this->pdo->prepare("DELETE FROM {$def['table']} WHERE id = ?")->execute([$id]);
            $this->flash(ucfirst($def['singular']) . ' deleted.');
        }
        header('Location: /admin/' . $key, true, 303);
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
            $this->pdo->prepare('UPDATE stories SET title = ?, body_html = ?, word_count = ?, updated_at = ? WHERE id = ?')
                ->execute([$rev['title'], $rev['body_html'], word_count($rev['body_html']), Db::utcNow(), $storyId]);
            $this->flash('Earlier version restored.');
        }
        header('Location: /admin/stories/' . $storyId, true, 303);
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

    private function page(string $title, string $body): void
    {
        $flash = $_SESSION['flash'] ?? '';
        unset($_SESSION['flash']);
        header('Content-Type: text/html; charset=utf-8');
        echo View::partial('admin/layout', ['title' => $title, 'body' => $body, 'flash' => $flash, 'csrf' => Auth::csrf(), 'user' => Auth::user(), 'resources' => $this->resources()]);
    }
}
