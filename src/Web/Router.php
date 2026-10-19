<?php
declare(strict_types=1);

namespace App\Web;

/** Tiny pattern router. Patterns use {name} placeholders that match one path segment. */
final class Router
{
    /** @var list<array{method:string, regex:string, handler:callable}> */
    private array $routes = [];

    public function get(string $pattern, callable $handler): void
    {
        $this->add('GET', $pattern, $handler);
    }

    public function post(string $pattern, callable $handler): void
    {
        $this->add('POST', $pattern, $handler);
    }

    private function add(string $method, string $pattern, callable $handler): void
    {
        $regex = '#^' . preg_replace('#\{(\w+)\}#', '(?P<$1>[^/]+)', $pattern) . '$#u';
        $this->routes[] = ['method' => $method, 'regex' => $regex, 'handler' => $handler];
    }

    /** @return array{0:callable,1:array<string,string>}|null */
    public function match(string $method, string $path): ?array
    {
        foreach ($this->routes as $r) {
            if (($r['method'] === $method || ($method === 'HEAD' && $r['method'] === 'GET')) && preg_match($r['regex'], $path, $m)) {
                return [$r['handler'], array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY)];
            }
        }
        return null;
    }
}
