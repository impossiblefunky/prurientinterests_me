<?php
declare(strict_types=1);

namespace App\Import;

use App\Media\Images;
use App\Support\Db;
use DOMElement;
use PDO;
use ZipArchive;

/**
 * Self-hosts the images used in posts. For every <img> whose file name matches an image in the Blogger Takeout:
 * the original is re-encoded into public/uploads/posts/, the post text is rewritten to use the local copy, and
 * (for picture posts) a DRAFT gallery item with a credit parsed from the file name is created in a per-year draft album.
 * Safe to re-run. Images it cannot find are listed in the returned report and left untouched.
 */
final class ImageImporter
{
    /** @var array<string,int> */
    public array $counts = ['posts_changed' => 0, 'images_rewritten' => 0, 'images_new_files' => 0, 'images_reused' => 0, 'not_in_takeout' => 0, 'already_local' => 0, 'gallery_items' => 0, 'errors' => 0];
    /** @var list<string> */
    public array $missing = [];
    /** @var list<string> */
    public array $problems = [];

    private PDO $pdo;
    private ZipArchive $zip;
    /** @var array<string,string> lower-case base name => zip entry */
    private array $index = [];
    /** @var array<string,string> letters-and-digits-only name without extension => index key */
    private array $loose = [];
    /** @var array<int,int> year => album id */
    private array $albums = [];

    public function __construct(string $takeoutZip, private bool $dryRun = false)
    {
        $this->pdo = Db::pdo();
        $this->zip = new ZipArchive();
        if ($this->zip->open($takeoutZip) !== true) {
            throw new \RuntimeException("Cannot open $takeoutZip");
        }
        for ($i = 0; $i < $this->zip->numFiles; $i++) {
            $n = (string) $this->zip->getNameIndex($i);
            if (str_contains($n, '/Albums/') && !str_ends_with($n, '.json') && !str_ends_with($n, '/')) {
                $base = mb_strtolower(basename($n));
                $this->index[$base] ??= $n;
                $this->loose[self::looseKey($base)] ??= $base;
            }
        }
    }

    /** Name with punctuation, case and extension ignored, so photo.jpeg and Photo_.jpg count as the same file. */
    private static function looseKey(string $name): string
    {
        return preg_replace('/[^a-z0-9]/', '', mb_strtolower(pathinfo($name, PATHINFO_FILENAME))) ?? '';
    }

    /** The index key of the Takeout file an image address points at: exact name first, then the loose match. */
    private function find(string $src): ?string
    {
        $name = mb_strtolower(rawurldecode(rawurldecode(basename(parse_url($src, PHP_URL_PATH) ?: ''))));
        if ($name === '') {
            return null;
        }
        if (isset($this->index[$name])) {
            return $name;
        }
        $k = self::looseKey($name);
        return $k !== '' && isset($this->loose[$k]) ? $this->loose[$k] : null;
    }

    public function indexSize(): int
    {
        return count($this->index);
    }

    public function run(?int $limit = null): void
    {
        $stories = $this->pdo->query('SELECT id, title, kind, body_html, published_at FROM stories WHERE body_html LIKE "%<img%" ORDER BY id')->fetchAll();
        foreach ($stories as $n => $s) {
            if ($limit !== null && $n >= $limit) {
                break;
            }
            $this->story($s);
        }
    }

    /** @param array<string,mixed> $s */
    private function story(array $s): void
    {
        $doc = HtmlSanitizer::load($s['body_html']);
        $root = $doc->getElementById('root');
        if (!$root) {
            return;
        }
        $changed = false;
        $locals = [];   // images that now live on this server, for the gallery
        foreach (iterator_to_array($doc->getElementsByTagName('img')) as $img) {
            /** @var DOMElement $img */
            $src = $img->getAttribute('src');
            if (str_starts_with($src, '/uploads/')) {
                $this->counts['already_local']++;
                continue;
            }
            $key = $this->find($src);
            if ($key === null) {
                $this->counts['not_in_takeout']++;
                $this->missing[] = $src . '  (in: ' . $s['title'] . ')';
                continue;
            }
            if ($this->dryRun) {
                $this->counts['images_rewritten']++;
                continue;
            }
            try {
                $local = $this->localCopy($key);
            } catch (\Throwable $e) {
                $this->counts['errors']++;
                $this->problems[] = "$key: " . $e->getMessage();
                continue;
            }
            $oldHref = '';
            if (($a = $img->parentNode) instanceof DOMElement && $a->tagName === 'a') {
                $oldHref = $a->getAttribute('href');
                // A link to the big Blogger copy now points at our big copy.
                if (preg_match('#^https?://[^/]*(googleusercontent|blogspot|blogger)\.#i', $oldHref) || mb_strtolower(basename(parse_url($oldHref, PHP_URL_PATH) ?: '')) === $key) {
                    $a->setAttribute('href', '/uploads/' . $local['file']);
                    $a->removeAttribute('rel');
                }
            }
            $img->setAttribute('src', '/uploads/' . $local['file']);
            $img->setAttribute('width', (string) $local['width']);
            $img->setAttribute('height', (string) $local['height']);
            $img->setAttribute('loading', 'lazy');
            $this->counts['images_rewritten']++;
            $changed = true;
            $locals[] = [$local, trim($img->getAttribute('alt')), $key];
        }
        if ($changed) {
            $new = '';
            foreach ($root->childNodes as $c) {
                $new .= $doc->saveHTML($c);
            }
            $this->pdo->prepare('UPDATE stories SET body_html = ? WHERE id = ?')->execute([trim($new), $s['id']]);
            $this->counts['posts_changed']++;
        }
        if ($s['kind'] === 'picture' && $locals && !$this->dryRun) {
            foreach ($locals as [$local, $alt, $key]) {
                $this->galleryItem($s, $local, $alt, $key);
            }
        }
    }

    /** @return array{file:string,thumb:string,width:int,height:int} */
    private function localCopy(string $key): array
    {
        $st = $this->pdo->prepare('SELECT file, thumb, width, height FROM image_sources WHERE takeout_name = ?');
        $st->execute([$key]);
        if ($row = $st->fetch()) {
            $this->counts['images_reused']++;
            return $row;
        }
        $bytes = $this->zip->getFromName($this->index[$key]);
        if ($bytes === false) {
            throw new \RuntimeException('could not be read from the Takeout');
        }
        $tmp = tempnam(sys_get_temp_dir(), 'img');
        file_put_contents($tmp, $bytes);
        unset($bytes);
        try {
            $img = Images::store($tmp, 'posts');   // checks it is a real image and re-encodes it; the extension comes from the content, never the file name
        } finally {
            @unlink($tmp);
        }
        $this->pdo->prepare('INSERT INTO image_sources (takeout_name, file, thumb, width, height, created_at) VALUES (?,?,?,?,?,?)')
            ->execute([$key, $img['file'], $img['thumb'], $img['width'], $img['height'], Db::utcNow()]);
        $this->counts['images_new_files']++;
        return $img;
    }

    /** @param array<string,mixed> $s @param array{file:string,thumb:string,width:int,height:int} $local */
    private function galleryItem(array $s, array $local, string $alt, string $key): void
    {
        $exists = $this->pdo->prepare('SELECT COUNT(*) FROM gallery_items WHERE story_id = ? AND file = ?');
        $exists->execute([$s['id'], $local['file']]);
        if ((int) $exists->fetchColumn()) {
            return;
        }
        $year = (int) gmdate('Y', strtotime($s['published_at'] . ' UTC'));
        $title = trim($s['title']) !== '' && $s['title'] !== 'Untitled' ? $s['title'] : self::titleFromFile($key);
        $this->pdo->prepare('INSERT INTO gallery_items (album_id, title, alt_text, source_url, credit, file, thumb, width, height, status, sort_order, story_id, created_at) VALUES (?,?,?,?,?,?,?,?,?,"draft",?,?,?)')
            ->execute([$this->album($year), $title, $alt !== '' ? $alt : $title, '', self::creditFromFile($key), $local['file'], $local['thumb'], $local['width'], $local['height'], 0, $s['id'], Db::utcNow()]);
        $itemId = (int) $this->pdo->lastInsertId();
        $this->pdo->prepare('INSERT IGNORE INTO item_tags (tag_id, item_type, item_id) SELECT tag_id, "gallery", ? FROM item_tags WHERE item_type = "story" AND item_id = ?')->execute([$itemId, $s['id']]);
        $this->counts['gallery_items']++;
    }

    private function album(int $year): int
    {
        if (isset($this->albums[$year])) {
            return $this->albums[$year];
        }
        $slug = "pictures-$year";
        $st = $this->pdo->prepare('SELECT id FROM albums WHERE slug = ?');
        $st->execute([$slug]);
        $id = $st->fetchColumn();
        if (!$id) {
            $this->pdo->prepare('INSERT INTO albums (slug, title, intro, status, sort_order, created_at) VALUES (?,?,?,?,?,?)')
                ->execute([$slug, "Pictures $year", 'Images first shown on the blog in ' . $year . '.', 'draft', 3000 - $year, Db::utcNow()]);
            $id = $this->pdo->lastInsertId();
        }
        return $this->albums[$year] = (int) $id;
    }

    /** "Pin_Up_by_Faith636.jpg" => "Faith636". Only a clear "_by_Name" ending is treated as a credit. */
    public static function creditFromFile(string $file): string
    {
        $base = pathinfo($file, PATHINFO_FILENAME);
        return preg_match('/_by_([A-Za-z0-9][A-Za-z0-9_.\-]{1,40})$/', $base, $m) ? str_replace('_', ' ', $m[1]) : '';
    }

    public static function titleFromFile(string $file): string
    {
        $base = preg_replace('/_by_[A-Za-z0-9][A-Za-z0-9_.\-]{1,40}$/', '', pathinfo($file, PATHINFO_FILENAME)) ?? '';
        $t = trim(preg_replace('/[_\-]+|\s+/', ' ', $base) ?? '');
        return $t !== '' && !preg_match('/^[0-9a-f]{16,}$/i', $t) ? ucfirst($t) : 'Untitled image';
    }
}
