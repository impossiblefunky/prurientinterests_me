<?php
declare(strict_types=1);

namespace App\Import;

use DOMElement;
use DOMDocument;
use Generator;
use XMLReader;

/** Streams entries from a Blogger Takeout feed.atom as plain arrays. */
final class BloggerFeed
{
    private const ATOM = 'http://www.w3.org/2005/Atom';
    private const BLOGGER = 'http://schemas.google.com/blogger/2018';

    /** @return Generator<int, array<string,mixed>> */
    public static function entries(string $file): Generator
    {
        $reader = new XMLReader();
        if (!$reader->open($file, 'UTF-8', LIBXML_NOENT | LIBXML_NONET | LIBXML_COMPACT)) {
            throw new \RuntimeException("Cannot open $file");
        }
        $doc = new DOMDocument();
        while ($reader->read()) {
            if ($reader->nodeType === XMLReader::ELEMENT && $reader->localName === 'entry' && $reader->namespaceURI === self::ATOM) {
                $node = $reader->expand($doc);
                if ($node instanceof DOMElement) {
                    yield self::toArray($node);
                }
                $reader->next();
            }
        }
        $reader->close();
    }

    /** @return array<string,mixed> */
    private static function toArray(DOMElement $e): array
    {
        $atom = fn(string $n): string => self::text($e, self::ATOM, $n);
        $blog = fn(string $n): string => self::text($e, self::BLOGGER, $n);

        $labels = [];
        foreach ($e->getElementsByTagNameNS(self::ATOM, 'category') as $c) {
            $t = trim($c->getAttribute('term'));
            if ($t !== '') {
                $labels[] = $t;
            }
        }
        $enclosures = [];
        foreach ($e->getElementsByTagNameNS(self::ATOM, 'link') as $l) {
            if ($l->getAttribute('rel') === 'enclosure') {
                $enclosures[] = ['href' => $l->getAttribute('href'), 'type' => $l->getAttribute('type')];
            }
        }
        $author = '';
        foreach ($e->getElementsByTagNameNS(self::ATOM, 'author') as $a) {
            $author = self::text($a, self::ATOM, 'name');
            break;
        }
        return [
            'id'          => $atom('id'),
            'kind'        => $blog('type'),                 // POST | PAGE | COMMENT
            'status'      => $blog('status'),               // LIVE | DRAFT | SPAM_COMMENT | GHOSTED_COMMENT
            'title'       => $atom('title'),
            'content'     => $atom('content'),
            'meta'        => $blog('metaDescription'),
            'published'   => $atom('published'),
            'created'     => $blog('created'),
            'updated'     => $atom('updated'),
            'filename'    => $blog('filename'),
            'parent'      => $blog('parent'),
            'in_reply_to' => $blog('inReplyTo'),
            'trashed'     => $blog('trashed'),
            'author'      => $author,
            'labels'      => $labels,
            'enclosures'  => $enclosures,
        ];
    }

    private static function text(DOMElement $e, string $ns, string $name): string
    {
        foreach ($e->getElementsByTagNameNS($ns, $name) as $n) {
            if ($n->parentNode === $e) {
                return trim($n->textContent);
            }
        }
        return '';
    }
}
