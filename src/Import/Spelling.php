<?php
declare(strict_types=1);

namespace App\Import;

/** Word list checking and aspell suggestions. Read-only helpers used by the spelling and comparison tools. */
final class Spelling
{
    /** @var array<string,true> */
    private array $known = [];

    /** @param list<string> $wordLists @param string $customDict one word per line, # comments */
    public function __construct(array $wordLists = ['/usr/share/dict/american-english'], ?string $customDict = null)
    {
        foreach ($wordLists as $f) {
            if (is_readable($f)) {
                foreach (file($f, FILE_IGNORE_NEW_LINES) ?: [] as $w) {
                    $this->known[mb_strtolower($w)] = true;
                }
            }
        }
        if ($customDict && is_readable($customDict)) {
            foreach (file($customDict, FILE_IGNORE_NEW_LINES) ?: [] as $w) {
                $w = trim($w);
                if ($w !== '' && $w[0] !== '#') {
                    $this->known[mb_strtolower($w)] = true;
                }
            }
        }
        if (!$this->known) {
            throw new \RuntimeException('No word list found. Install one (on DreamHost: ask support for /usr/share/dict, or pass a list with --wordlist).');
        }
    }

    public function add(string $word): void
    {
        $this->known[mb_strtolower($word)] = true;
    }

    /** True when the word, or its base form without a possessive or common ending, is known. */
    public function isKnown(string $word): bool
    {
        $w = mb_strtolower(str_replace("\u{2019}", "'", $word));
        if (isset($this->known[$w])) {
            return true;
        }
        if (preg_match("/^(.+)'s$/u", $w, $m) && isset($this->known[$m[1]])) {
            return true;
        }
        if (preg_match("/^(.+)s'$/u", $w, $m) && isset($this->known[$m[1] . 's'])) {
            return true;
        }
        // Hyphenated compounds are fine when each part is.
        if (str_contains($w, '-')) {
            foreach (explode('-', $w) as $p) {
                if ($p !== '' && !isset($this->known[$p])) {
                    return false;
                }
            }
            return true;
        }
        return false;
    }

    /** @return list<string> */
    public static function words(string $text): array
    {
        preg_match_all("/[\\p{L}][\\p{L}\\p{M}]*(?:['\x{2019}][\\p{L}]+)*(?:-[\\p{L}]+)*/u", $text, $m);
        return $m[0];
    }

    /** Visible text of an HTML fragment, with block tags turned into spaces. */
    public static function visibleText(string $html): string
    {
        $html = preg_replace('#<(br|/p|/h[1-6]|/li|/blockquote|hr)[^>]*>#i', ' $0 ', $html) ?? $html;
        return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
    }

    /**
     * Asks aspell for suggestions. Returns word => first few suggestions; words aspell accepts map to [].
     * @param list<string> $words @return array<string,list<string>>
     */
    public static function suggest(array $words): array
    {
        $out = [];
        if (!$words || !self::aspellAvailable()) {
            return $out;
        }
        $p = proc_open(['aspell', '-a', '--lang=en', '--encoding=utf-8'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($p)) {
            return $out;
        }
        fwrite($pipes[0], "!\n");
        foreach ($words as $w) {
            fwrite($pipes[0], '^' . $w . "\n");
        }
        fclose($pipes[0]);
        $lines = explode("\n", stream_get_contents($pipes[1]) ?: '');
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($p);
        foreach ($lines as $l) {
            if (preg_match('/^& (\S+) \d+ \d+: (.+)$/u', $l, $m)) {
                $out[$m[1]] = array_slice(array_map('trim', explode(',', $m[2])), 0, 4);
            } elseif (preg_match('/^# (\S+) /u', $l, $m)) {
                $out[$m[1]] = [];
            }
        }
        return $out;
    }

    public static function aspellAvailable(): bool
    {
        static $ok = null;
        if ($ok === null) {
            $ok = trim((string) @shell_exec('command -v aspell')) !== '';
        }
        return $ok;
    }
}
