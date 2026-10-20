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

/** Query string for pager and filter links: current filters merged with overrides; empty values and page 1 are dropped. */
function qs(array $cur, array $over = []): string
{
    $all = array_filter($over + $cur, fn($v) => $v !== null && $v !== '' && $v !== 1 && $v !== 0);
    return $all ? '?' . http_build_query($all) : '';
}

/** Story text for display: outside links open in a new tab and say so to screen readers. */
function prose(string $html): string
{
    return preg_replace_callback('#<a href="(https?://[^"]+)"(?: rel="noopener")?>(.*?)</a>#is', function (array $m): string {
        $host = parse_url($m[1], PHP_URL_HOST);
        $mine = parse_url((string) \App\Support\Env::get('APP_URL', ''), PHP_URL_HOST);
        if ($host && $mine && strcasecmp($host, $mine) === 0) {
            return '<a href="' . $m[1] . '">' . $m[2] . '</a>';
        }
        return '<a href="' . $m[1] . '" target="_blank" rel="noopener noreferrer">' . $m[2] . '<span class="sr-only"> (opens in a new tab)</span></a>';
    }, $html) ?? $html;
}

/** "Standalone · 2026 · 8 min read" or "Series · 2020 · 4 chapters". */
function card_meta(array $s): string
{
    $year = gmdate('Y', strtotime($s['published_at'] . ' UTC'));
    if (!empty($s['series_id'])) {
        $n = (int) $s['chapters'];
        return "Series · $year · $n chapter" . ($n === 1 ? '' : 's');
    }
    return "Standalone · $year · " . reading_minutes((int) $s['word_count']) . ' min read';
}

/** A book cover: the uploaded image, or a typeset placeholder card when there is none. */
function View_cover(array $b): string
{
    if (!empty($b['cover_image'])) {
        return '<img class="cover" src="/uploads/' . e($b['cover_image']) . '" alt="Cover of ' . e($b['title']) . '" width="200" loading="lazy">';
    }
    return '<div class="cover placeholder" role="img" aria-label="' . e($b['title']) . ' by ' . e(setting('author_name')) . '"><span class="eyebrow">' . e(setting('author_name')) . '</span><span class="cover-title">' . e($b['title']) . '</span></div>';
}


/** Plain text of story HTML for the site search: no tags, entities decoded, whitespace squeezed. */
function search_plain(string $html): string
{
    $html = preg_replace('#<(br|/p|/h[1-6]|/li|/blockquote|hr|/div|/tr)[^>]*>#i', ' ', $html) ?? $html;
    return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
}

/** A short passage of $text around the first match of any term, with the terms wrapped in <mark>. Safe to print as HTML. */
function search_snippet(string $text, array $terms, int $span = 170): string
{
    $pos = null;
    foreach ($terms as $t) {
        $p = mb_stripos($text, $t);
        if ($p !== false && ($pos === null || $p < $pos)) {
            $pos = $p;
        }
    }
    $start = max(0, ($pos ?? 0) - 60);
    $part = mb_substr($text, $start, $span);
    $out = e(($start > 0 ? '…' : '') . $part . (mb_strlen($text) > $start + $span ? '…' : ''));
    foreach ($terms as $t) {
        $out = preg_replace('/(' . preg_quote(e($t), '/') . ')/iu', '<mark>$1</mark>', $out) ?? $out;
    }
    return $out;
}


/** $text escaped for HTML with every search term wrapped in <mark>. */
function search_mark(string $text, array $terms): string
{
    $out = e($text);
    foreach ($terms as $t) {
        $out = preg_replace('/(' . preg_quote(e($t), '/') . ')/iu', '<mark>$1</mark>', $out) ?? $out;
    }
    return $out;
}

/** Address of a file under public/ with its change time added, so browsers fetch it again whenever it changes. */
function asset(string $path): string
{
    $file = dirname(__DIR__, 2) . '/public' . $path;
    return $path . (is_file($file) ? '?v=' . filemtime($file) : '');
}
