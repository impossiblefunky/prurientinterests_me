<?php
declare(strict_types=1);

namespace App\Support;

/** Loads KEY=value lines from the project's .env (outside public/). Values are not shell-evaluated. */
final class Env
{
    /** @var array<string,string> */
    private static array $values = [];
    private static bool $loaded = false;

    public static function load(?string $file = null): void
    {
        if (self::$loaded) {
            return;
        }
        $file ??= dirname(__DIR__, 2) . '/.env';
        if (is_readable($file)) {
            foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
                $line = trim($line);
                if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
                    continue;
                }
                [$k, $v] = explode('=', $line, 2);
                self::$values[trim($k)] = trim($v);
            }
        }
        self::$loaded = true;
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        self::load();
        return self::$values[$key] ?? $_ENV[$key] ?? ($default);
    }

    public static function root(): string
    {
        return dirname(__DIR__, 2);
    }
}
