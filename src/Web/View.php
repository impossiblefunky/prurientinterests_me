<?php
declare(strict_types=1);

namespace App\Web;

/** Renders plain-PHP templates inside the shared layout. Templates receive $d (data) and $c (Content helpers via $d). */
final class View
{
    public static function render(string $template, array $d, array $meta): string
    {
        $content = self::partial($template, $d);
        return self::partial('layout', ['content' => $content, 'meta' => $meta, 'd' => $d]);
    }

    public static function partial(string $template, array $vars = []): string
    {
        $file = dirname(__DIR__, 2) . '/templates/' . $template . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException("Missing template $template");
        }
        extract($vars, EXTR_SKIP);
        ob_start();
        include $file;
        return (string) ob_get_clean();
    }
}
