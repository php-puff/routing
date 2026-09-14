<?php

/*
 * PHP Unison Fiber Framework
 * https://github.com/php-puff/routing
 * https://github.com/php-puff/routing/issues
 * Copyright (c) Puff
 */

declare(strict_types=1);

namespace Puff\Routing;

final readonly class RouteDefinition
{
    /** @param list<string|object> $pipeline */
    public function __construct(
        public int|string $id,
        public string $path,
        public string $method,
        public mixed $handler,
        public ?string $pattern,
        public ?string $name,
        public ?string $namespace,
        public ?string $group,
        public ?string $locale,
        public mixed $menu,
        public ?string $icon,
        public array $pipeline,
    ) {
    }

    public static function fromRoute(Route $route, ?string $pattern): self
    {
        return new self(
            $route->identifier(),
            $route->path(),
            $route->method(),
            $route->handler(),
            $pattern,
            $route->routeName(),
            $route->controllerNamespace(),
            $route->groupId(),
            $route->locale(),
            $route->menu(),
            $route->icon(),
            $route->pipelines(),
        );
    }

    public function callable(): mixed
    {
        if (!\is_string($this->handler) || !\str_contains($this->handler, '@') || $this->namespace === null) {
            return $this->handler;
        }
        return \trim($this->namespace, '\\') . '\\' . $this->handler;
    }
}
