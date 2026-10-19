<?php
declare(strict_types=1);

use App\Support\Db;
use App\Support\Env;

/** HTML-escape for output. */
function e(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function is_production(): bool
{
    return Env::get('APP_ENV', 'production') === 'production';
}

function site_url(string $path = ''): string
{
    return rtrim((string) Env::get('APP_URL', ''), '/') . $path;
}

function slugify(string $s): string
{
    $s = mb_strtolower(trim($s), 'UTF-8');
    $t = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
    $s = $t !== false && $t !== '' ? $t : $s;
    $s = preg_replace('/[^a-z0-9]+/', '-', $s) ?? '';
    return trim($s, '-') ?: 'untitled';
}

/** Site-wide editable text (title, tagline, footer note). */
function setting(string $name, string $default = ''): string
{
    static $all = null;
    if ($all === null) {
        try {
            $all = Db::pdo()->query('SELECT name, value FROM settings')->fetchAll(PDO::FETCH_KEY_PAIR);
        } catch (Throwable) {
            $all = [];
        }
    }
    return $all[$name] ?? $default;
}

function format_date(?string $utc, string $fmt = 'F j, Y'): string
{
    return $utc ? gmdate($fmt, strtotime($utc . ' UTC')) : '';
}

/** Visible words in an HTML fragment. */
function word_count(string $html): int
{
    $text = trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    return $text === '' ? 0 : count(preg_split('/\s+/u', $text) ?: []);
}

function reading_minutes(int $words): int
{
    return max(1, (int) ceil($words / 230));
}

function excerpt(string $html, int $chars = 160): string
{
    $text = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
    if (mb_strlen($text) <= $chars) {
        return $text;
    }
    $cut = mb_substr($text, 0, $chars);
    $sp = mb_strrpos($cut, ' ');
    return rtrim($sp !== false ? mb_substr($cut, 0, $sp) : $cut, " ,.;:-") . '…';
}

/** Public URL of a story: its exact old Blogger path when it has one, otherwise /story/slug. */
function story_url(array $s): string
{
    return !empty($s['legacy_path']) ? $s['legacy_path'] : '/story/' . $s['slug'];
}

/** Turns plain text with blank-line paragraphs into HTML. */
function text_to_html(string $text): string
{
    $paras = preg_split('/\R{2,}/', trim($text)) ?: [];
    $out = '';
    foreach ($paras as $p) {
        $p = trim($p);
        if ($p !== '') {
            $out .= '<p>' . nl2br(e($p), false) . "</p>\n";
        }
    }
    return $out;
}
