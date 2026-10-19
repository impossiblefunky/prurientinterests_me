<?php
declare(strict_types=1);

namespace App\Import;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Allowlist sanitizer for editorial HTML. Drops scripts, styles, event handlers, microdata attributes, iframes
 * and unknown tags (keeping their text). Links keep href only for http/https/mailto and gain rel on external targets.
 */
final class HtmlSanitizer
{
    private const ALLOWED = ['p', 'br', 'em', 'strong', 'b', 'i', 'u', 'a', 'ul', 'ol', 'li', 'blockquote', 'h2', 'h3', 'h4', 'hr', 'sup', 'sub', 'img', 'table', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td', 'caption'];
    private const DROP_WITH_CONTENT = ['script', 'style', 'iframe', 'object', 'embed', 'form', 'noscript', 'textarea', 'select'];

    public static function load(string $html): DOMDocument
    {
        $doc = new DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8" ?><div id="root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        return $doc;
    }

    public static function clean(string $html): string
    {
        $doc = self::load($html);
        $root = $doc->getElementById('root');
        if (!$root) {
            return '';
        }
        self::walk($root);
        $out = '';
        foreach ($root->childNodes as $c) {
            $out .= $doc->saveHTML($c);
        }
        $out = preg_replace('/(?:<br\s*\/?>\s*){3,}/i', '<br><br>', $out) ?? $out;
        $out = preg_replace('/^\s*(?:<br\s*\/?>\s*)+/i', '', $out) ?? $out;
        $out = preg_replace('/(?:\s*<br\s*\/?>)+\s*$/i', '', $out) ?? $out;
        return trim($out);
    }

    private static function walk(DOMNode $node): void
    {
        $children = [];
        foreach ($node->childNodes as $c) {
            $children[] = $c;
        }
        foreach ($children as $child) {
            if ($child instanceof DOMElement) {
                $name = strtolower($child->tagName);
                if (in_array($name, self::DROP_WITH_CONTENT, true)) {
                    $node->removeChild($child);
                    continue;
                }
                self::walk($child);
                if (!in_array($name, self::ALLOWED, true)) {
                    while ($child->firstChild) {            // unwrap: keep children, drop the tag
                        $node->insertBefore($child->firstChild, $child);
                    }
                    $node->removeChild($child);
                    continue;
                }
                self::cleanAttributes($child, $name);
            } elseif ($child->nodeType === XML_COMMENT_NODE) {
                $node->removeChild($child);
            }
        }
    }

    private static function cleanAttributes(DOMElement $el, string $name): void
    {
        $keep = [];
        if ($name === 'a') {
            $href = trim($el->getAttribute('href'));
            if (preg_match('#^(https?://|mailto:|/)#i', $href)) {
                $keep['href'] = $href;
                if (preg_match('#^https?://#i', $href)) {
                    $keep['rel'] = 'noopener';
                }
            }
        } elseif (in_array($name, ['td', 'th'], true)) {
            foreach (['colspan', 'rowspan'] as $span) {                 // table cells keep only their span (a plain number)
                $n = (int) $el->getAttribute($span);
                if ($n > 1 && $n <= 50) {
                    $keep[$span] = (string) $n;
                }
            }
            if ($name === 'th' && in_array($el->getAttribute('scope'), ['col', 'row'], true)) {
                $keep['scope'] = $el->getAttribute('scope');
            }
        } elseif ($name === 'img') {
            $src = trim($el->getAttribute('src'));
            if (preg_match('#^(https?://|/)#i', $src)) {
                $keep['src'] = $src;
                $keep['alt'] = $el->getAttribute('alt');
                if ($el->getAttribute('width') !== '') {
                    $keep['width'] = (string) (int) $el->getAttribute('width');
                }
                if ($el->getAttribute('height') !== '') {
                    $keep['height'] = (string) (int) $el->getAttribute('height');
                }
            }
        }
        foreach (iterator_to_array($el->attributes) as $attr) {
            $el->removeAttribute($attr->name);
        }
        foreach ($keep as $k => $v) {
            $el->setAttribute($k, $v);
        }
    }
}
