<?php

/*
 * PHP Unison Fiber Framework
 * https://github.com/php-puff/routing
 * https://github.com/php-puff/routing/issues
 * Copyright (c) Puff
 */

declare(strict_types=1);

namespace Puff\Routing;

final readonly class RouteCompiler
{
    private const PARAMETER = '/\(([A-Za-z_][A-Za-z0-9_]*):([A-Za-z][A-Za-z0-9_-]*|\*)\)/';

    /** @param array<string, string> $patterns */
    public function __construct(private array $patterns = Router::PATTERNS)
    {
    }

    /** @param list<Route> $routes */
    public function compile(array $routes): RouteCollection
    {
        $collection = new RouteCollection();
        foreach ($routes as $route) {
            $collection->add(RouteDefinition::fromRoute($route, $this->compilePattern($route->path())));
        }
        return $collection;
    }

    public function compilePattern(string $path): ?string
    {
        $count = \preg_match_all(self::PARAMETER, $path, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
        if ($count === false) {
            throw new \LogicException('Unable to parse route pattern.');
        }
        if ($count === 0) {
            return null;
        }

        $compiled = '';
        $offset = 0;
        $parameters = [];
        foreach ($matches as $match) {
            $token = $match[0][0];
            $position = $match[0][1];
            $name = $match[1][0];
            $type = $match[2][0];
            if (isset($parameters[$name])) {
                throw new \InvalidArgumentException("Duplicate route parameter [{$name}] in [{$path}].");
            }
            if (!isset($this->patterns[$type])) {
                throw new \InvalidArgumentException("Unknown route parameter type [{$type}].");
            }
            $parameters[$name] = true;
            $compiled .= \preg_quote(\substr($path, $offset, $position - $offset), '~');
            $compiled .= '(?P<' . $name . '>' . $this->patterns[$type] . ')';
            $offset = $position + \strlen($token);
        }
        $compiled .= \preg_quote(\substr($path, $offset), '~');
        $pattern = '~\A' . $compiled . '\z~D';
        if (@\preg_match($pattern, '') === false) {
            throw new \InvalidArgumentException("Invalid compiled route pattern [{$path}].");
        }
        return $pattern;
    }
}
