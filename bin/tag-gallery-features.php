<?php
declare(strict_types=1);

/**
 * Adds feature tags (Glasses, Green skin, Redheads, Bride of Frankenstein, ...) to gallery pictures from the words in
 * their own alt text. Never removes a tag and never touches alt text.
 *   php bin/tag-gallery-features.php --preview    (counts per tag; writes nothing)
 *   php bin/tag-gallery-features.php              (does it)
 * To add a feature tag, add a line to $rules below: tag name => regular expression on the alt text. Safe to run again.
 */
require dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/src/Web/helpers.php';

use App\Support\Db;

$rules = [
    'Glasses' => '/(?<!sun)glasses/i',
    'Sunglasses' => '/sunglasses/i',
    'Green skin' => '/green skin|green-skinned|green body paint|green face paint/i',
    'Bride of Frankenstein' => '/tall (streaked |dark |black )?hairdo(?!.*spiky)|streaked hairdo in a long pale|rolled hairdo/i',
    'Redheads' => '/\bred hair|red curly|red-brown|red bob|red pigtails|red ponytail|auburn|copper|strawberry|\bred wavy|red curls|red-haired|red and black hair/i',
    'Blondes' => '/blonde|platinum/i',
    'Pink hair' => '/pink (hair|bob|pigtails)|long pink hair/i',
    'Blue hair' => '/blue (hair|bob)|blue-streaked|blue and black hair|pale blue hair/i',
    'Purple hair' => '/purple (hair|bob|and black hair)|purple-tinted/i',
    'White hair' => '/white hair|white-silver|silver hair|pale hair|white curled|pale blonde|white-blonde/i',
    'Pigtails' => '/pigtail/i',
    'Nuns' => '/\bnun\b|nun-style/i',
    'Maids' => '/\bmaid/i',
    'Nurses' => '/nurse/i',
    'Cat ears' => '/cat-ear|cat ear|cat-eared/i',
    'Vampires' => '/fangs|vampire/i',
    'Robots' => '/robot|mechanical|cyborg|android|metal limbs/i',
    'Witches and devils' => '/witch|devil|small horns|black horns|horned(?! helmet)/i',
    'Wonder Woman' => '/star-spangled|golden rope|coiled rope|star-printed briefs/i',
    'Star Trek' => '/(red|blue|teal) uniform-style (dress|top)/i',
    'Snow White' => '/storybook|fairy-tale|snow white/i',
    'Gas masks' => '/gas mask|breathing-mask/i',
    'Superhero costumes' => '/superhero|\bcape\b|catsuit|armored|armor/i',
    'Fishnets' => '/fishnet/i',
    'Garter belts' => '/garter/i',
    'Thigh-highs' => '/thigh-high|thigh high/i',
    'Gloves' => '/gloves/i',
    'Collars' => '/collar|choker/i',
    'Leather' => '/leather/i',
    'Whips and crops' => '/whip|riding crop|\bcrop\b|flogger|paddle|\bcane\b/i',
    'Classroom' => '/classroom|school|chalkboard/i',
    'Mirror selfies' => '/selfie/i',
    'Swimwear' => '/bikini|swimsuit|\bpool\b|beach/i',
];

$preview = in_array('--preview', $argv, true);
$pdo = Db::pdo();
$rows = $pdo->query("SELECT id, alt_text FROM gallery_items WHERE status = 'published' AND CHAR_LENGTH(alt_text) >= 30")->fetchAll();   // full-sentence descriptions only
$findTag = $pdo->prepare('SELECT id FROM tags WHERE slug = ?');
$newTag = $pdo->prepare('INSERT INTO tags (slug, name, created_at) VALUES (?,?,?)');
$link = $pdo->prepare("INSERT IGNORE INTO item_tags (tag_id, item_type, item_id) VALUES (?, 'gallery', ?)");
$tagId = function (string $name) use ($findTag, $newTag, $pdo): int {
    $findTag->execute([slugify($name)]);
    $id = $findTag->fetchColumn();
    if (!$id) {
        $newTag->execute([slugify($name), $name, Db::utcNow()]);
        $id = $pdo->lastInsertId();
    }
    return (int) $id;
};

$added = 0;
printf("Pictures checked: %d\n", count($rows));
foreach ($rules as $name => $re) {
    $hits = array_filter($rows, fn($r) => preg_match($re, (string) $r['alt_text']) === 1);
    $new = 0;
    if (!$preview && $hits) {
        $tid = $tagId($name);
        foreach ($hits as $r) {
            $link->execute([$tid, $r['id']]);
            $new += $link->rowCount();
        }
    }
    $added += $new;
    printf("%-24s %4d pictures%s\n", $name, count($hits), $preview ? '' : "  (new links: $new)");
}
echo $preview ? "Preview only: nothing was written.\n" : "New tag links in all: $added\n";
