<?php

/*
 * PHP Unison Fiber Framework
 * https://github.com/php-puff/routing
 * https://github.com/php-puff/routing/issues
 * Copyright (c) Puff
 */

declare(strict_types=1);

namespace Puff\Routing\Tests;

use PHPUnit\Framework\TestCase;
use Puff\Http\Request;
use Puff\Routing\Exception\NotFoundException;
use Puff\Routing\Router;

final class RouterTest extends TestCase
{
    public function testNestedGroupsMergeAttributes(): void
    {
        $router = new Router(new Request());
        $router->group([
            'id' => 'api',
            'prefix' => '/api',
            'namespace' => 'App\\Controller',
            'pipeline' => ['global'],
            'locale' => 'en',
        ], static function (Router $router): void {
            $router->group([
                'id' => 'private',
                'prefix' => 'private',
                'namespace' => 'Api',
                'pipeline' => ['auth'],
            ], static function (Router $router): void {
                $router->get('/profile', [
                    'call' => 'Profile@index',
                    'pipeline' => ['ability'],
                ])->id('profile');
            });
        });

        $route = $router->collection()->named('profile');
        self::assertNotNull($route);
        self::assertSame('/api/private/profile', $route->path);
        self::assertSame('App\\Controller\\Api\\Profile@index', $route->callable());
        self::assertSame(['global', 'auth', 'ability'], $route->pipeline);
        self::assertSame('en', $route->locale);
    }

    public function testGroupStackIsClearedWhenCallbackThrows(): void
    {
        $router = new Router(new Request());
        try {
            $router->group(['prefix' => '/failed'], static function (): void {
                throw new \RuntimeException('stop');
            });
        } catch (\RuntimeException) {
        }
        $router->get('/outside', static fn (): string => 'outside')->id('outside');

        $route = $router->collection()->named('outside');
        self::assertNotNull($route);
        self::assertSame('/outside', $route->path);
        self::assertNull($route->group);
    }

    public function testResourceRoutesContainParameterSeparator(): void
    {
        $router = new Router(new Request());
        $routes = $router->resource('/users', 'User');

        self::assertCount(6, $routes);
        self::assertSame('/users/(id:str)', $routes[1]->path());
        self::assertSame(['id' => '42'], $router->dispatch(new Request('GET', '/users/42'))->args());
    }

    public function testDispatchDecodesPathOnce(): void
    {
        $router = new Router(new Request());
        $router->get('/hello/(name:str)', static fn (): string => 'hello');

        self::assertSame(['name' => 'Puff'], $router->dispatch(new Request('GET', '/hello/%50uff'))->args());
    }

    public function testDispatchRejectsEncodedPathSeparators(): void
    {
        $router = new Router(new Request());
        $router->get('/files/(name:str)', static fn (): string => 'file');

        $this->expectException(NotFoundException::class);
        $router->dispatch(new Request('GET', '/files/a%2Fb'));
    }

    public function testRouteLookupReturnsCurrentAndNamedRoutes(): void
    {
        $router = new Router(new Request());
        $router->get('/health', static fn (): string => 'ok')->id('health');
        $matched = $router->dispatch(new Request('GET', '/health'));

        self::assertSame($matched, $router->current());
        self::assertSame('health', $router->route('health')?->id);
        self::assertNull($router->route('missing'));
    }
}
