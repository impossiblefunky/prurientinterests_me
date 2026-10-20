<?php
declare(strict_types=1);

namespace App\Media;

use App\Support\Env;
use PDO;

/** Finds gallery images that are the same picture, even if re-saved at another size. */
final class Dupes
{
    /** Pairs this close (out of 64 bits) count as the same picture. */
    private const NEAR = 4;

    public static function hash(string $relPath): ?string
    {
        return self::hashFile(Env::root() . '/public/uploads/' . $relPath);
    }

    /** Fingerprint of any image file on disk. */
    public static function hashFile(string $path): ?string
    {
        $info = is_file($path) ? @getimagesize($path) : false;
        if (!$info) {
            return null;
        }
        $src = match ($info['mime']) {
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/png' => @imagecreatefrompng($path),
            'image/webp' => @imagecreatefromwebp($path),
            'image/gif' => @imagecreatefromgif($path),
            default => false,
        };
        if (!$src) {
            return null;
        }
        $small = imagescale($src, 9, 8, IMG_BILINEAR_FIXED);
        $bits = '';
        for ($y = 0; $y < 8; $y++) {
            for ($x = 0; $x < 8; $x++) {
                $bits .= self::grey($small, $x, $y) > self::grey($small, $x + 1, $y) ? '1' : '0';
            }
        }
        $hex = '';
        foreach (str_split($bits, 4) as $nib) {
            $hex .= dechex(bindec($nib));
        }
        return $hex;
    }

    private static function grey(\GdImage $im, int $x, int $y): int
    {
        $c = imagecolorat($im, $x, $y);
        return (int) ((($c >> 16) & 255) * 0.3 + (($c >> 8) & 255) * 0.59 + ($c & 255) * 0.11);
    }

    /** Fingerprints up to $limit images that have none yet. Returns how many are still waiting. */
    public static function fill(PDO $pdo, int $limit): int
    {
        $rows = $pdo->query("SELECT id, IF(thumb <> '', thumb, file) AS f FROM gallery_items WHERE dhash IS NULL LIMIT " . $limit)->fetchAll();
        $up = $pdo->prepare('UPDATE gallery_items SET dhash = ? WHERE id = ?');
        foreach ($rows as $r) {
            // an unreadable file gets a marker so it is not retried on every visit
            $up->execute([self::hash((string) $r['f']) ?? 'unreadable0000000', $r['id']]);
        }
        return (int) $pdo->query('SELECT COUNT(*) FROM gallery_items WHERE dhash IS NULL')->fetchColumn();
    }

    public static function distance(string $a, string $b): int
    {
        $d = 0;
        for ($i = 0; $i < 16; $i += 8) {
            $x = hexdec(substr($a, $i, 8)) ^ hexdec(substr($b, $i, 8));
            while ($x) {
                $d += $x & 1;
                $x >>= 1;
            }
        }
        return $d;
    }

    /** @return list<array{items:list<array<string,mixed>>,exact:bool}> groups of two or more, biggest first */
    public static function groups(PDO $pdo): array
    {
        $rows = $pdo->query("SELECT g.id, g.title, g.caption, g.file, g.thumb, g.width, g.height, g.status, g.story_id, g.created_at, g.dhash, a.title AS album
            FROM gallery_items g LEFT JOIN albums a ON a.id = g.album_id WHERE g.dhash IS NOT NULL AND g.dhash <> 'unreadable0000000' ORDER BY g.id")->fetchAll();
        $n = count($rows);
        $parent = range(0, max(0, $n - 1));
        $find = function (int $i) use (&$parent, &$find): int {
            return $parent[$i] === $i ? $i : ($parent[$i] = $find($parent[$i]));
        };
        // exact matches first (cheap), then near matches by comparing every pair
        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                if ($rows[$i]['dhash'] === $rows[$j]['dhash'] || self::distance($rows[$i]['dhash'], $rows[$j]['dhash']) <= self::NEAR) {
                    $parent[$find($j)] = $find($i);
                }
            }
        }
        $by = [];
        foreach ($rows as $i => $r) {
            $by[$find($i)][] = $r;
        }
        $out = [];
        foreach ($by as $items) {
            if (count($items) > 1) {
                $out[] = ['items' => $items, 'exact' => count(array_unique(array_column($items, 'dhash'))) === 1];
            }
        }
        usort($out, fn($a, $b) => count($b['items']) <=> count($a['items']));
        return $out;
    }
}
