<?php

/*
 * PHP Fiber Framework
 * https://github.com/php-puff/routing
 * https://github.com/php-puff/routing/issues
 * Copyright (c) Puff
 */

declare(strict_types=1);

namespace Puff\Routing;

use JsonSerializable;
use Stringable;

final class Route implements JsonSerializable, Stringable
{
    /**
     * @param list<string|object> $pipeline
     */
    public function __construct(
        private readonly string $method,
        private readonly string $path,
        private readonly mixed $handler,
        private int|string $identifier,
        private ?string $routeName = null,
        private readonly ?string $controllerNamespace = null,
        private readonly ?string $groupId = null,
        private readonly ?string $locale = null,
        private readonly mixed $menu = null,
        private readonly ?string $icon = null,
        private array $pipeline = [],
    ) {
    }

    public function id(int|string $identifier): self
    {
        $this->identifier = $identifier;
        $this->routeName ??= (string) $identifier;
        return $this;
    }

    public function name(string $name): self
    {
        if ($name === '') {
            throw new \InvalidArgumentException('Route name must not be empty.');
        }
        $this->routeName = $name;
        return $this;
    }

    public function pipeline(string|object ...$pipeline): self
    {
        \array_push($this->pipeline, ...$pipeline);
        return $this;
    }

    public function identifier(): int|string
    {
        return $this->identifier;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function method(): string
    {
        return $this->method;
    }

    public function handler(): mixed
    {
        return $this->handler;
    }

    public function routeName(): ?string
    {
        return $this->routeName;
    }

    public function controllerNamespace(): ?string
    {
        return $this->controllerNamespace;
    }

    public function groupId(): ?string
    {
        return $this->groupId;
    }

    public function locale(): ?string
    {
        return $this->locale;
    }

    public function menu(): mixed
    {
        return $this->menu;
    }

    public function icon(): ?string
    {
        return $this->icon;
    }

    /** @return list<string|object> */
    public function pipelines(): array
    {
        return $this->pipeline;
    }

    public function __toString(): string
    {
        return $this->path;
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->identifier,
            'path' => $this->path,
            'method' => $this->method,
            'name' => $this->routeName,
            'namespace' => $this->controllerNamespace,
            'group' => $this->groupId,
            'locale' => $this->locale,
            'menu' => $this->menu,
            'icon' => $this->icon,
            'pipeline' => $this->pipeline,
        ];
    }
}
