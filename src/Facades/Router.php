<?php

/*
 * PHP Fiber Framework
 * https://github.com/php-puff/routing
 * https://github.com/php-puff/routing/issues
 * Copyright (c) Puff
 */

declare(strict_types=1);

use Puff\Di\Facade;

/**
 * @method static Puff\Routing\Route                any(string $path, mixed $action)
 * @method static Puff\Routing\Route                get(string $path, mixed $action)
 * @method static Puff\Routing\Route                post(string $path, mixed $action)
 * @method static Puff\Routing\Route                put(string $path, mixed $action)
 * @method static Puff\Routing\Route                patch(string $path, mixed $action)
 * @method static Puff\Routing\Route                delete(string $path, mixed $action)
 * @method static Puff\Routing\Route                head(string $path, mixed $action)
 * @method static Puff\Routing\Route                options(string $path, mixed $action)
 * @method static list<Puff\Routing\Route>          resource(string $path, string $controller)
 * @method static Puff\Routing\Router               regex(string $type, string $pattern)
 * @method static void                              group(array<string, mixed> $attributes, Closure $callback)
 * @method static Puff\Routing\RouteDefinition|null route(string $name)
 * @method static Puff\Routing\RouteMatched|null    current()
 */
final class Router extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return Puff\Routing\Router::class;
    }
}
