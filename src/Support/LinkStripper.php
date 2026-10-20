<?php
declare(strict_types=1);

namespace App\Support;

/** Removes dead outside links and pictures from post HTML while keeping the visible words. */
final class LinkStripper
{
    /**
     * @param array<string,true> $dead URLs (entities decoded, no #fragment) known to be gone
     * @return array{0:string,1:int,2:int} new HTML, links unwrapped, pictures removed
     */
    public static function strip(string $html, array $dead): array
    {
        $links = 0;
        $pics = 0;
        $isDead = static function (string $u) use ($dead): bool {
            $u = html_entity_decode($u, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            return isset($dead[preg_replace('/#.*$/', '', $u) ?? $u]);
        };
        // 1. pictures that no longer load
        $html = preg_replace_callback('#<img\b[^>]*?\bsrc="(https?://[^"]+)"[^>]*>#i', function (array $m) use ($isDead, &$pics): string {
            if ($isDead($m[1])) {
                $pics++;
                return '';
            }
            return $m[0];
        }, $html) ?? $html;
        // 2. links left holding nothing (a picture link whose picture was removed)
        $html = preg_replace_callback('#<a\b[^>]*>(?:\s|<br\s*/?>)*</a>#i', function (array $m) use (&$links): string {
            $links++;
            return '';
        }, $html) ?? $html;
        // 3. dead links: keep what the reader sees, drop the link
        $html = preg_replace_callback('#<a\b[^>]*?\bhref="(https?://[^"]+)"[^>]*>(.*?)</a>#is', function (array $m) use ($isDead, &$links): string {
            if ($isDead($m[1])) {
                $links++;
                return $m[2];
            }
            return $m[0];
        }, $html) ?? $html;
        return [$html, $links, $pics];
    }
}
