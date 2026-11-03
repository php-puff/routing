<?php

/*
 * PHP Unison Fiber Framework
 * https://github.com/php-puff/routing
 * https://github.com/php-puff/routing/issues
 * Copyright (c) Puff
 */

declare(strict_types=1);

namespace Puff\Routing;

use Closure;
use Puff\Http\Request;
use Puff\Routing\Exception\NotFoundException;

final class Router
{
    /** @var array<string, string> */
    public const PATTERNS = [
        '*' => '.+',
        'str' => '[A-Za-z0-9_-]+',
        'int' => '(?:0|[1-9][0-9]*)',
        'num' => '-?(?:[0-9]+(?:\.[0-9]+)?)',
        'any' => '[A-Za-z0-9_!@$^&+\-=|]+',
        'hex' => '[A-Fa-f0-9]+',
        'hash' => '[A-Za-z0-9]+',
        'uuid' => '[0-9A-Fa-f]{8}-[0-9A-Fa-f]{4}-[0-9A-Fa-f]{4}-[0-9A-Fa-f]{4}-[0-9A-Fa-f]{12}',
    ];

    /** @var list<Route> */
    private array $routes = [];

    /** @var array<string, string> */
    private array $patterns = self::PATTERNS;

    /** @var list<array{id: string, prefix: string, namespace: ?string, pipeline: list<string|object>, locale: ?string, menu: mixed}> */
    private array $groups = [];

    private ?RouteCollection $collection;
    private ?RouteMatched $current = null;

    public function __construct(
        private readonly Request $request,
        ?RouteCollection $collection = null,
    ) {
        $this->collection = $collection;
    }

    public function any(string $path, mixed $action): Route
    {
        return $this->register('any', $path, $action);
    }

    public function get(string $path, mixed $action): Route
    {
        return $this->register('get', $path, $action);
    }

    public function post(string $path, mixed $action): Route
    {
        return $this->register('post', $path, $action);
    }

    public function put(string $path, mixed $action): Route
    {
        return $this->register('put', $path, $action);
    }

    public function patch(string $path, mixed $action): Route
    {
        return $this->register('patch', $path, $action);
    }

    public function delete(string $path, mixed $action): Route
    {
        return $this->register('delete', $path, $action);
    }

    public function head(string $path, mixed $action): Route
    {
        return $this->register('head', $path, $action);
    }

    public function options(string $path, mixed $action): Route
    {
        return $this->register('options', $path, $action);
    }

    /** @return list<Route> */
    public function resource(string $path, string $controller): array
    {
        $path = self::normalizePath($path);
        return [
            $this->get($path, $controller . '@get'),
            $this->get($path . '/(id:str)', $controller . '@get'),
            $this->post($path, $controller . '@post'),
            $this->put($path . '/(id:str)', $controller . '@put'),
            $this->patch($path . '/(id:str)', $controller . '@patch'),
            $this->delete($path . '/(id:str)', $controller . '@delete'),
        ];
    }

    public function regex(string $type, string $pattern): self
    {
        $type = \ltrim(\trim($type), ':');
        $pattern = \ltrim(\trim($pattern), ':');
        if (\preg_match('/^(?:[A-Za-z][A-Za-z0-9_-]*|\*)$/D', $type) !== 1 || $pattern === '') {
            throw new \InvalidArgumentException('Invalid route pattern definition.');
        }
        if (@\preg_match('~\A(?:' . $pattern . ')\z~D', '') === false) {
            throw new \InvalidArgumentException("Invalid regular expression for route type [{$type}].");
        }
        $this->patterns[$type] = $pattern;
        $this->collection = null;
        return $this;
    }

    /** @param array<string, mixed> $attributes */
    public function group(array $attributes, Closure $callback): void
    {
        $this->groups[] = $this->mergeGroup($attributes);
        try {
            $callback($this);
        } finally {
            \array_pop($this->groups);
        }
    }

    public function route(string $name): ?RouteDefinition
    {
        return $this->collection()->named($name);
    }

    public function current(): ?RouteMatched
    {
        return $this->current;
    }

    /** @return list<Route> */
    public function routes(): array
    {
        return $this->routes;
    }

    /** @param list<string> $files */
    public function load(array $files): RouteCollection
    {
        foreach ($files as $file) {
            $file = \str_ends_with($file, '.php') ? $file : $file . '.php';
            if (!\is_file($file)) {
                throw new NotFoundException("Route file [{$file}] does not exist.");
            }
            require $file;
        }
        return $this->collection();
    }

    public function collection(): RouteCollection
    {
        return $this->collection ??= (new RouteCompiler($this->patterns))->compile($this->routes);
    }

    /** @param string|list<string> $files */
    public function through(string|array $files, ?callable $callback = null): mixed
    {
        if ($this->collection === null) {
            $this->load((array) $files);
        }
        $matched = $this->dispatch($this->request);
        return $callback === null ? $matched : $callback($matched);
    }

    public function dispatch(Request $request): RouteMatched
    {
        $path = self::requestPath($request->path());
        return $this->current = (new RouteMatcher($this->collection()))->match($request->method(), $path);
    }

    private function register(string $method, string $path, mixed $action): Route
    {
        [$handler, $options] = self::action($action);
        $group = $this->groups === [] ? null : $this->groups[\array_key_last($this->groups)];
        $path = self::joinPath($group['prefix'] ?? '/', self::normalizePath($path));
        $namespace = self::namespace(
            $group['namespace'] ?? null,
            isset($options['namespace']) ? (string) $options['namespace'] : null,
        );
        $pipeline = [
            ...($group['pipeline'] ?? []),
            ...self::pipeline($options['pipeline'] ?? []),
        ];
        $route = new Route(
            $method,
            $path,
            $handler,
            $options['id'] ?? \hash('xxh3', $method . ':' . $path),
            isset($options['name']) ? (string) $options['name'] : null,
            $namespace,
            $group['id'] ?? null,
            isset($options['locale']) ? (string) $options['locale'] : ($group['locale'] ?? null),
            $options['menu'] ?? ($group['menu'] ?? null),
            isset($options['icon']) ? (string) $options['icon'] : null,
            $pipeline,
        );
        $this->routes[] = $route;
        $this->collection = null;
        return $route;
    }

    /** @return array{mixed, array<string, mixed>} */
    private static function action(mixed $action): array
    {
        if (\is_array($action) && \array_key_exists('call', $action)) {
            $handler = $action['call'];
            unset($action['call']);
            self::validateHandler($handler);
            return [$handler, $action];
        }
        self::validateHandler($action);
        return [$action, []];
    }

    private static function validateHandler(mixed $handler): void
    {
        if (\is_string($handler) || \is_callable($handler)) {
            return;
        }
        if (\is_array($handler) && \count($handler) === 2 && (\is_string($handler[0]) || \is_object($handler[0])) && \is_string($handler[1])) {
            return;
        }
        throw new \InvalidArgumentException('Route action must contain a valid handler.');
    }

    /** @param array<string, mixed> $attributes
     * @return array{id: string, prefix: string, namespace: ?string, pipeline: list<string|object>, locale: ?string, menu: mixed}
     */
    private function mergeGroup(array $attributes): array
    {
        $parent = $this->groups === [] ? null : $this->groups[\array_key_last($this->groups)];
        $prefix = isset($attributes['prefix']) ? (string) $attributes['prefix'] : '';
        $prefix = self::joinPath($parent['prefix'] ?? '/', self::normalizePath($prefix));
        $namespace = self::namespace(
            $parent['namespace'] ?? null,
            isset($attributes['namespace']) ? (string) $attributes['namespace'] : null,
        );
        $pipeline = [...($parent['pipeline'] ?? []), ...self::pipeline($attributes['pipeline'] ?? [])];
        return [
            'id' => isset($attributes['id']) ? (string) $attributes['id'] : 'group-' . \count($this->groups),
            'prefix' => $prefix,
            'namespace' => $namespace,
            'pipeline' => $pipeline,
            'locale' => isset($attributes['locale']) ? (string) $attributes['locale'] : ($parent['locale'] ?? null),
            'menu' => $attributes['menu'] ?? ($parent['menu'] ?? null),
        ];
    }

    private static function namespace(?string $parent, ?string $namespace): ?string
    {
        if ($namespace === null || $namespace === '') {
            return $parent;
        }
        if ($namespace[0] === '\\' || $parent === null || $parent === '') {
            return \trim($namespace, '\\');
        }
        return \trim($parent, '\\') . '\\' . \trim($namespace, '\\');
    }

    /** @return list<string|object> */
    private static function pipeline(mixed $pipeline): array
    {
        $result = [];
        foreach ((array) $pipeline as $stage) {
            if (!\is_string($stage) && !\is_object($stage)) {
                throw new \InvalidArgumentException('Route pipeline stages must be strings or objects.');
            }
            $result[] = $stage;
        }
        return $result;
    }

    private static function requestPath(string $path): string
    {
        if (\preg_match('/%(?![A-Fa-f0-9]{2})/', $path) === 1) {
            throw new NotFoundException('Request path contains invalid percent encoding.');
        }
        if (\preg_match('/%(?:2f|5c)/i', $path) === 1) {
            throw new NotFoundException('Request path contains an encoded separator.');
        }
        $path = \rawurldecode($path);
        if (\preg_match('/[\x00-\x1F\x7F]/', $path) === 1) {
            throw new NotFoundException('Request path contains control characters.');
        }
        if ($path === '') {
            return '/';
        }
        $path = '/' . \ltrim($path, '/');
        return $path === '/' ? '/' : \rtrim($path, '/');
    }

    private static function normalizePath(string $path): string
    {
        $path = \trim($path);
        if ($path === '') {
            return '/';
        }
        if (\str_contains($path, '?') || \str_contains($path, '#') || \preg_match('/[\x00-\x1F\x7F]/', $path) === 1) {
            throw new \InvalidArgumentException("Invalid route path [{$path}].");
        }
        $path = '/' . \ltrim($path, '/');
        return $path === '/' ? '/' : \rtrim($path, '/');
    }

    private static function joinPath(string $prefix, string $path): string
    {
        if ($prefix === '/') {
            return $path;
        }
        if ($path === '/') {
            return $prefix;
        }
        return \rtrim($prefix, '/') . '/' . \ltrim($path, '/');
    }
}
