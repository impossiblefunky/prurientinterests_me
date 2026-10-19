<?php
declare(strict_types=1);

namespace App\Media;

use App\Support\Env;

/** Validates an uploaded image, stores a size-limited copy plus a thumbnail under public/uploads/<dir>/. */
final class Images
{
    private const TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    private const MAX_EDGE = 2200;
    private const THUMB_EDGE = 640;

    /** @return array{file:string,thumb:string,width:int,height:int} paths relative to public/uploads */
    public static function store(string $tmpFile, string $dir): array
    {
        $info = @getimagesize($tmpFile);
        $mime = $info['mime'] ?? '';
        if (!$info || !isset(self::TYPES[$mime])) {
            throw new \InvalidArgumentException('That file is not a JPEG, PNG, WebP or GIF image.');
        }
        $ext = self::TYPES[$mime];
        $src = match ($mime) {
            'image/jpeg' => imagecreatefromjpeg($tmpFile),
            'image/png' => imagecreatefrompng($tmpFile),
            'image/webp' => imagecreatefromwebp($tmpFile),
            default => imagecreatefromgif($tmpFile),
        };
        if (!$src) {
            throw new \InvalidArgumentException('That image could not be read.');
        }
        $root = Env::root() . '/public/uploads/' . $dir;
        if (!is_dir($root) && !mkdir($root, 0755, true) && !is_dir($root)) {
            throw new \RuntimeException('Cannot create the uploads folder.');
        }
        $name = bin2hex(random_bytes(8));
        [$big, $w, $h] = self::fit($src, self::MAX_EDGE);
        [$small] = self::fit($src, self::THUMB_EDGE);
        self::save($big, "$root/$name.$ext", $ext);
        self::save($small, "$root/$name-thumb.$ext", $ext);
        return ['file' => "$dir/$name.$ext", 'thumb' => "$dir/$name-thumb.$ext", 'width' => $w, 'height' => $h];
    }

    /** @return array{0:\GdImage,1:int,2:int} */
    private static function fit(\GdImage $src, int $edge): array
    {
        $w = imagesx($src);
        $h = imagesy($src);
        $scale = min(1, $edge / max($w, $h));
        $nw = max(1, (int) round($w * $scale));
        $nh = max(1, (int) round($h * $scale));
        $dst = imagecreatetruecolor($nw, $nh);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);   // re-encoding also strips EXIF/GPS data
        return [$dst, $nw, $nh];
    }

    private static function save(\GdImage $img, string $path, string $ext): void
    {
        match ($ext) {
            'jpg' => imagejpeg($img, $path, 85),
            'png' => imagepng($img, $path, 7),
            'webp' => imagewebp($img, $path, 82),
            default => imagegif($img, $path),
        };
    }

    public static function delete(?string $rel): void
    {
        if ($rel && !str_contains($rel, '..')) {
            @unlink(Env::root() . '/public/uploads/' . $rel);
        }
    }
}
