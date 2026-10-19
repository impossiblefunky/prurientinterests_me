<?php
declare(strict_types=1);

namespace App\Import;

use DOMDocument;
use DOMElement;
use ZipArchive;

/** Reads a .docx and returns its paragraphs as HTML; can split a multi-piece document by title lines. */
final class DocxImporter
{
    private const W = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    /** @return list<array{html:string,text:string}> one entry per non-empty paragraph */
    public static function paragraphs(string $file): array
    {
        $zip = new ZipArchive();
        if ($zip->open($file) !== true) {
            throw new \RuntimeException("Cannot open $file");
        }
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        if ($xml === false) {
            throw new \RuntimeException("$file has no document.xml");
        }
        $doc = new DOMDocument();
        $doc->loadXML($xml, LIBXML_NONET | LIBXML_NOENT);
        $out = [];
        foreach ($doc->getElementsByTagNameNS(self::W, 'p') as $p) {
            $html = '';
            $text = '';
            foreach ($p->getElementsByTagNameNS(self::W, 'r') as $r) {
                $piece = '';
                foreach ($r->childNodes as $n) {
                    if (!$n instanceof DOMElement) {
                        continue;
                    }
                    $piece .= match ($n->localName) {
                        't' => htmlspecialchars($n->textContent, ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                        'tab' => ' ',
                        'br' => '<br>',
                        default => '',
                    };
                }
                $plain = html_entity_decode(strip_tags($piece), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $text .= $plain;
                $rPr = $r->getElementsByTagNameNS(self::W, 'rPr')->item(0);
                $on = fn(string $tag) => $rPr instanceof DOMElement && ($el = $rPr->getElementsByTagNameNS(self::W, $tag)->item(0)) instanceof DOMElement
                    && !in_array($el->getAttributeNS(self::W, 'val'), ['0', 'false', 'none'], true);
                if (trim($plain) !== '') {
                    if ($on('i')) { $piece = "<em>$piece</em>"; }
                    if ($on('b')) { $piece = "<strong>$piece</strong>"; }
                }
                $html .= $piece;
            }
            $html = trim(preg_replace('/[ \x{00A0}]{2,}/u', ' ', $html) ?? $html);
            if (trim($text) !== '') {
                $out[] = ['html' => $html, 'text' => trim($text)];
            }
        }
        return $out;
    }

    /**
     * Splits a collection into pieces. A title line is short, has no closing punctuation, and is followed by a longer paragraph.
     * @param list<array{html:string,text:string}> $paras
     * @return list<array{title:string,html:string}>
     */
    public static function splitByTitles(array $paras): array
    {
        $pieces = [];
        $cur = null;
        foreach ($paras as $i => $p) {
            if (self::isTitle($p['text'], $paras[$i + 1]['text'] ?? '')) {
                if ($cur) { $pieces[] = $cur; }
                $cur = ['title' => $p['text'], 'html' => ''];
                continue;
            }
            if ($cur === null) {
                $cur = ['title' => '', 'html' => ''];
            }
            $cur['html'] .= self::block($p) . "\n";
        }
        if ($cur) { $pieces[] = $cur; }
        return $pieces;
    }

    private static function isTitle(string $text, string $next): bool
    {
        $words = count(preg_split('/\s+/u', $text) ?: []);
        return !self::isBreak($text) && $words <= 9 && mb_strlen($text) <= 70 && !preg_match('/[.!?,;:)]$/u', $text) && mb_strlen($next) > mb_strlen($text) * 2;
    }

    /** @param list<array{html:string,text:string}> $paras */
    public static function toHtml(array $paras): string
    {
        return implode("\n", array_map(fn($p) => self::block($p), $paras)) . "\n";
    }

    /** A scene-break line such as "* * *" becomes a divider; anything else a paragraph. */
    private static function block(array $p): string
    {
        return self::isBreak($p['text']) ? '<hr>' : '<p>' . $p['html'] . '</p>';
    }

    private static function isBreak(string $text): bool
    {
        return preg_match('/^[\s*#~•·-]{1,20}$/u', $text) === 1 && preg_match('/[*#~•·-]/u', $text) === 1;
    }

    /** Lower-case letters and digits only, for matching titles between sources. */
    public static function normalizeTitle(string $t): string
    {
        $t = html_entity_decode($t, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return preg_replace('/[^a-z0-9]+/', '', mb_strtolower($t)) ?? '';
    }
}
