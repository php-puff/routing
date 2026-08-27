<?php

/*
 * PHP Fiber Framework
 * https://github.com/php-puff/routing
 * https://github.com/php-puff/routing/issues
 * Copyright (c) Puff
 */

declare(strict_types=1);

namespace Puff\Routing;

final readonly class RouteMatched
{
    /** @param array<string, string> $parameters */
    public function __construct(
        private RouteDefinition $route,
        private array $parameters = [],
    ) {
    }

    public function callable(): mixed
    {
        return $this->route->callable();
    }

    /** @return array<string, string> */
    public function args(): array
    {
        return $this->parameters;
    }

    /** @return list<string|object> */
    public function pipeline(): array
    {
        return $this->route->pipeline;
    }

    public function locale(): ?string
    {
        return $this->route->locale;
    }

    public function definition(): RouteDefinition
    {
        return $this->route;
    }
}
