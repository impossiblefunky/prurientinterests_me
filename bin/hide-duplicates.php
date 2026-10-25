<?php
declare(strict_types=1);

/**
 * Hides gallery pictures that are the same photo as another published picture, keeping the biggest copy.
 *   php bin/hide-duplicates.php --preview    (says what it would hide; writes nothing)
 *   php bin/hide-duplicates.php              (hides the extra copies: status goes back to draft, nothing is deleted)
 * Two ways to find a pair: (1) automatically, when two pictures match almost pixel for pixel at small size
 * (re-saved or resized copies), and (2) config/gallery-duplicates.tsv, pairs of fingerprints for the same photo
 * that was cropped or edited differently. To bring a hidden picture back, publish it again in the admin.
 * Prints counts and picture numbers only.
 */
ini_set('memory_limit', '512M');
set_time_limit(0);
require dirname(__DIR__) . '/vendor/autoload.php';

use App\Media\Dupes;
use App\Support\Db;
use App\Support\Env;

const SAME = 0.93;   // how alike two small greyscale copies must be to count as the same picture (1.0 = identical)

$preview = in_array('--preview', $argv, true);
$pdo = Db::pdo();
while (Dupes::fill($pdo, 300) > 0) {
}

/** A 24x24 greyscale version of the picture, centred and scaled to length 1, or null if it cannot be read. */
function tiny(string $path): ?array
{
    $info = is_file($path) ? @getimagesize($path) : false;
    $src = $info ? match ($info['mime']) {
        'image/jpeg' => @imagecreatefromjpeg($path),
        'image/png' => @imagecreatefrompng($path),
        'image/webp' => @imagecreatefromwebp($path),
        'image/gif' => @imagecreatefromgif($path),
        default => false,
    } : false;
    if (!$src) {
        return null;
    }
    $s = imagescale($src, 24, 24, IMG_BILINEAR_FIXED);
    $v = [];
    for ($y = 0; $y < 24; $y++) {
        for ($x = 0; $x < 24; $x++) {
            $c = imagecolorat($s, $x, $y);
            $v[] = (($c >> 16) & 255) * 0.3 + (($c >> 8) & 255) * 0.59 + ($c & 255) * 0.11;
        }
    }
    $mean = array_sum($v) / count($v);
    $norm = 0.0;
    foreach ($v as $i => $n) {
        $v[$i] = $n - $mean;
        $norm += $v[$i] ** 2;
    }
    if ($norm < 1e-3) {
        return null;   // a flat picture tells us nothing
    }
    $norm = sqrt($norm);
    return array_map(fn($n) => $n / $norm, $v);
}

$rows = $pdo->query("SELECT id, dhash, IF(thumb <> '', thumb, file) AS f, width * height AS px FROM gallery_items WHERE status = 'published'")->fetchAll();
$vec = [];
foreach ($rows as $r) {
    $t = tiny(Env::root() . '/public/uploads/' . $r['f']);
    if ($t) {
        $vec[(int) $r['id']] = $t;
    }
}

$parent = [];
$find = function (int $a) use (&$parent, &$find): int {
    $parent[$a] ??= $a;
    return $parent[$a] === $a ? $a : ($parent[$a] = $find($parent[$a]));
};
$join = function (int $a, int $b) use (&$parent, $find): void {
    $parent[$find($a)] = $find($b);
};

$ids = array_keys($vec);
$n = count($ids);
$auto = 0;
for ($i = 0; $i < $n; $i++) {
    $a = $vec[$ids[$i]];
    for ($j = $i + 1; $j < $n; $j++) {
        $dot = 0.0;
        foreach ($vec[$ids[$j]] as $k => $bv) {
            $dot += $a[$k] * $bv;
        }
        if ($dot >= SAME) {
            $join($ids[$i], $ids[$j]);
            $auto++;
        }
    }
}

$manual = 0;
foreach (file(Env::root() . '/config/gallery-duplicates.tsv', FILE_IGNORE_NEW_LINES) ?: [] as $line) {
    if ($line === '' || $line[0] === '#') {
        continue;
    }
    [$ha, $hb] = array_pad(explode("\t", $line), 2, '');
    $as = array_filter($rows, fn($r) => preg_match('/^[0-9a-f]{16}$/', $ha) && Dupes::distance($ha, (string) $r['dhash']) <= 2);
    $bs = array_filter($rows, fn($r) => preg_match('/^[0-9a-f]{16}$/', $hb) && Dupes::distance($hb, (string) $r['dhash']) <= 2);
    if (!$as || !$bs || count($as) > 4 || count($bs) > 4) {
        continue;
    }
    foreach (array_merge($as, $bs) as $r) {
        $join((int) $as[array_key_first($as)]['id'], (int) $r['id']);
    }
    $manual++;
}

$px = array_column($rows, 'px', 'id');
$groups = [];
foreach (array_keys($parent) as $id) {
    $groups[$find($id)][] = $id;
}
$hide = $pdo->prepare("UPDATE gallery_items SET status = 'draft' WHERE id = ?");
$hidden = $kept = 0;
$listed = [];
foreach ($groups as $members) {
    if (count($members) < 2) {
        continue;
    }
    usort($members, fn($x, $y) => [$px[$y] ?? 0, $x] <=> [$px[$x] ?? 0, $y]);   // biggest first, then lowest number
    $kept++;
    foreach (array_slice($members, 1) as $id) {
        if (!$preview) {
            $hide->execute([$id]);
        }
        $hidden++;
        $listed[] = $id;
    }
}
printf("Published pictures checked: %d\nSame-photo matches found by the automatic check: %d\nCrop pairs from the list: %d\nGroups of the same photo: %d\nCopies %s: %d\n", $n, $auto, $manual, $kept, $preview ? 'that would be hidden' : 'hidden', $hidden);
if ($listed) {
    echo 'Picture numbers: ' . implode(', ', $listed) . "\n";
}
