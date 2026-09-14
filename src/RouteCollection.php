<?php

/*
 * PHP Unison Fiber Framework
 * https://github.com/php-puff/routing
 * https://github.com/php-puff/routing/issues
 * Copyright (c) Puff
 */

declare(strict_types=1);

namespace Puff\Routing;

final class RouteCollection
{
    /** @var array<string, array<string, RouteDefinition>> */
    private array $exact = [];

    /** @var array<string, array<string, array<string, RouteDefinition>>> */
    private array $dynamic = [];

    /** @var array<string, RouteDefinition> */
    private array $named = [];

    /** @var list<RouteDefinition> */
    private array $routes = [];

    public function add(RouteDefinition $route): void
    {
        $methods = $route->pattern === null
            ? $this->exact[$route->path] ?? []
            : $this->dynamic[self::routeBucket($route->path)][$route->pattern] ?? [];
        if (isset($methods[$route->method])) {
            throw new \LogicException("Duplicate [{$route->method}] route [{$route->path}].");
        }

        if ($route->pattern === null) {
            $this->exact[$route->path][$route->method] = $route;
        } else {
            $this->dynamic[self::routeBucket($route->path)][$route->pattern][$route->method] = $route;
        }
        $this->registerName((string) $route->id, $route);
        if ($route->name !== null) {
            $this->registerName($route->name, $route);
        }
        $this->routes[] = $route;
    }

    /** @return array<string, RouteDefinition>|null */
    public function exact(string $path): ?array
    {
        return $this->exact[$path] ?? null;
    }

    /** @return list<array{pattern: string, routes: array<string, RouteDefinition>}> */
    public function dynamic(string $path): array
    {
        $groups = [];
        $segments = \explode('/', \trim($path, '/'));
        for ($length = \count($segments); $length > 0; --$length) {
            $bucket = \implode('/', \array_slice($segments, 0, $length));
            foreach ($this->dynamic[$bucket] ?? [] as $pattern => $routes) {
                $groups[$pattern] = $routes;
            }
        }
        foreach ($this->dynamic['*'] ?? [] as $pattern => $routes) {
            $groups[$pattern] = $routes;
        }

        $result = [];
        foreach ($groups as $pattern => $routes) {
            $result[] = ['pattern' => $pattern, 'routes' => $routes];
        }
        return $result;
    }

    public function named(string $name): ?RouteDefinition
    {
        return $this->named[$name] ?? null;
    }

    /** @return list<RouteDefinition> */
    public function all(): array
    {
        return $this->routes;
    }

    private function registerName(string $name, RouteDefinition $route): void
    {
        if ($name === '') {
            return;
        }
        if (isset($this->named[$name]) && $this->named[$name] !== $route) {
            throw new \LogicException("Duplicate route name or identifier [{$name}].");
        }
        $this->named[$name] = $route;
    }

    private static function routeBucket(string $path): string
    {
        $position = \strpos($path, '(');
        if ($position === false) {
            return \trim($path, '/') ?: '*';
        }
        $prefix = \substr($path, 0, $position);
        if (!\str_ends_with($prefix, '/')) {
            $separator = \strrpos($prefix, '/');
            $prefix = $separator === false ? '' : \substr($prefix, 0, $separator + 1);
        }
        return \trim($prefix, '/') ?: '*';
    }
}
