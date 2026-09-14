<?php

/*
 * PHP Unison Fiber Framework
 * https://github.com/php-puff/routing
 * https://github.com/php-puff/routing/issues
 * Copyright (c) Puff
 */

declare(strict_types=1);

namespace Puff\Routing;

use Puff\Routing\Exception\MethodNotAllowedException;
use Puff\Routing\Exception\NotFoundException;

final readonly class RouteMatcher
{
    public function __construct(private RouteCollection $routes)
    {
    }

    public function match(string $method, string $path): RouteMatched
    {
        $method = \strtolower($method);
        $exact = $this->routes->exact($path);
        if ($exact !== null) {
            return new RouteMatched($this->routeForMethod($exact, $method, $path));
        }

        $pathMatched = false;
        $allowed = [];
        foreach ($this->routes->dynamic($path) as $candidate) {
            if (\preg_match($candidate['pattern'], $path, $matches) !== 1) {
                continue;
            }
            $pathMatched = true;
            $route = $this->findMethod($candidate['routes'], $method);
            if ($route !== null) {
                return new RouteMatched($route, self::parameters($matches));
            }
            $allowed = [...$allowed, ...self::allowed($candidate['routes'])];
        }

        if ($pathMatched) {
            throw MethodNotAllowedException::for($method, $allowed);
        }
        throw new NotFoundException("Invalid Request Route [{$path}]");
    }

    /** @param array<string, RouteDefinition> $routes */
    private function routeForMethod(array $routes, string $method, string $path): RouteDefinition
    {
        return $this->findMethod($routes, $method)
            ?? throw MethodNotAllowedException::for($method, self::allowed($routes), $path);
    }

    /** @param array<string, RouteDefinition> $routes */
    private function findMethod(array $routes, string $method): ?RouteDefinition
    {
        return $routes[$method]
            ?? ($method === 'head' ? $routes['get'] ?? null : null)
            ?? $routes['any']
            ?? null;
    }

    /**
     * @param  array<int|string, mixed> $matches
     * @return array<string, string>
     */
    private static function parameters(array $matches): array
    {
        $parameters = [];
        foreach ($matches as $name => $value) {
            if (\is_string($name) && \is_string($value)) {
                $parameters[$name] = $value;
            }
        }
        return $parameters;
    }

    /**
     * @param  array<string, RouteDefinition> $routes
     * @return list<string>
     */
    private static function allowed(array $routes): array
    {
        if (isset($routes['any'])) {
            return [];
        }
        $methods = \array_keys($routes);
        if (isset($routes['get']) && !isset($routes['head'])) {
            $methods[] = 'head';
        }
        return \array_values(\array_unique($methods));
    }
}
