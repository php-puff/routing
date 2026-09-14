<?php

/*
 * PHP Unison Fiber Framework
 * https://github.com/php-puff/routing
 * https://github.com/php-puff/routing/issues
 * Copyright (c) Puff
 */

declare(strict_types=1);

use Puff\Di\Container;
use Puff\Routing\RouteDefinition;
use Puff\Routing\RouteMatched;
use Puff\Routing\Router;

if (!\function_exists('route')) {
    function route(?string $name = null): RouteMatched|RouteDefinition|null
    {
        $router = Container::getInstance()?->get(Router::class);
        if (!$router instanceof Router) {
            throw new LogicException('No scoped router is available.');
        }
        return $name === null ? $router->current() : $router->route($name);
    }
}
