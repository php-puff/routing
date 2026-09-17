<?php

/*
 * PHP Unison Fiber Framework
 * https://github.com/php-puff/routing
 * https://github.com/php-puff/routing/issues
 * Copyright (c) Puff
 */

declare(strict_types=1);

namespace Puff\Routing\Tests;

use Fiber;
use PHPUnit\Framework\TestCase;
use Puff\Http\Request;
use Puff\Routing\Exception\MethodNotAllowedException;
use Puff\Routing\Exception\NotFoundException;
use Puff\Routing\RouteCompiler;
use Puff\Routing\RouteMatcher;
use Puff\Routing\Router;

final class RouteMatcherTest extends TestCase
{
    public function testMatchesExactAndDynamicRoutes(): void
    {
        $router = new Router(new Request());
        $router->get('/health', static fn (): string => 'ok')->id('health');
        $router->get('/users/(id:int)', static fn (): string => 'user')->id('users.show');
        $matcher = new RouteMatcher($router->collection());

        self::assertSame('health', $matcher->match('GET', '/health')->definition()->id);
        self::assertSame(['id' => '0'], $matcher->match('GET', '/users/0')->args());
        self::assertSame(['id' => '42'], $matcher->match('GET', '/users/42')->args());
    }

    public function testMatchedParametersAreFiberLocal(): void
    {
        $router = new Router(new Request());
        $router->get('/users/(id:int)', static fn (): string => 'user');
        $matcher = new RouteMatcher($router->collection());
        $first = new Fiber(static fn (): array => $matcher->match('GET', '/users/1')->args());
        $second = new Fiber(static fn (): array => $matcher->match('GET', '/users/2')->args());

        $first->start();
        $second->start();
        self::assertSame(['id' => '1'], $first->getReturn());
        self::assertSame(['id' => '2'], $second->getReturn());
    }

    public function testAnyAndExplicitMethodsAreSupported(): void
    {
        $router = new Router(new Request());
        $router->any('/echo', static fn (): string => 'ok');
        $router->head('/head', static fn (): string => 'head');
        $router->options('/options', static fn (): string => 'options');
        $matcher = new RouteMatcher($router->collection());

        foreach (['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'] as $method) {
            self::assertSame('any', $matcher->match($method, '/echo')->definition()->method);
        }
        self::assertSame('head', $matcher->match('HEAD', '/head')->definition()->method);
        self::assertSame('options', $matcher->match('OPTIONS', '/options')->definition()->method);
    }

    public function testHeadFallsBackToGet(): void
    {
        $router = new Router(new Request());
        $router->get('/health', static fn (): string => 'ok');

        self::assertSame('get', (new RouteMatcher($router->collection()))
            ->match('HEAD', '/health')
            ->definition()
            ->method);
    }

    public function testDistinguishesNotFoundAndMethodNotAllowed(): void
    {
        $router = new Router(new Request());
        $router->get('/health', static fn (): string => 'ok');
        $matcher = new RouteMatcher($router->collection());

        try {
            $matcher->match('POST', '/health');
            self::fail('Expected method error.');
        } catch (MethodNotAllowedException $exception) {
            self::assertSame(405, $exception->status());
            self::assertSame(['GET', 'HEAD'], $exception->allowed());
        }

        $this->expectException(NotFoundException::class);
        $matcher->match('GET', '/missing');
    }

    public function testDynamicRoutesAreIndexedByFirstSegment(): void
    {
        $router = new Router(new Request());
        $router->get('/users/(id:int)', static fn (): string => 'user');
        $router->get('/posts/(id:int)', static fn (): string => 'post');
        $router->get('/(tenant:str)/profile', static fn (): string => 'tenant');
        $collection = $router->collection();

        self::assertCount(2, $collection->dynamic('/users/42'));
        self::assertCount(2, $collection->dynamic('/posts/42'));
        self::assertSame(
            '/posts/(id:int)',
            (new RouteMatcher($collection))->match('GET', '/posts/42')->definition()->path,
        );
    }

    public function testStaticPartsOfDynamicRoutesAreEscaped(): void
    {
        $router = new Router(new Request());
        $router->get('/release/v1.0/(id:int)', static fn (): string => 'release');
        $matcher = new RouteMatcher($router->collection());

        self::assertSame(['id' => '3'], $matcher->match('GET', '/release/v1.0/3')->args());
        $this->expectException(NotFoundException::class);
        $matcher->match('GET', '/release/v1x0/3');
    }

    public function testCustomParameterPattern(): void
    {
        $router = new Router(new Request());
        $router->regex('slug', '[a-z0-9]+(?:-[a-z0-9]+)*');
        $router->get('/posts/(slug:slug)', static fn (): string => 'post');
        $matcher = new RouteMatcher($router->collection());

        self::assertSame(['slug' => 'fiber-routing'], $matcher->match('GET', '/posts/fiber-routing')->args());
        $this->expectException(NotFoundException::class);
        $matcher->match('GET', '/posts/Invalid');
    }

    public function testInlineEnumParameter(): void
    {
        $router = new Router(new Request());
        $router->get('/(page:about|contact|business|faq)', static fn (): string => 'page');
        $matcher = new RouteMatcher($router->collection());

        self::assertSame(['page' => 'about'], $matcher->match('GET', '/about')->args());
        self::assertSame(['page' => 'contact'], $matcher->match('GET', '/contact')->args());

        $this->expectException(NotFoundException::class);
        $matcher->match('GET', '/pricing');
    }

    public function testRejectsUnknownAndDuplicateParameters(): void
    {
        $compiler = new RouteCompiler();

        try {
            $compiler->compilePattern('/users/(id:missing)');
            self::fail('Unknown parameter type should fail.');
        } catch (\InvalidArgumentException $exception) {
            self::assertStringContainsString('Unknown route parameter type', $exception->getMessage());
        }

        $this->expectException(\InvalidArgumentException::class);
        $compiler->compilePattern('/(id:int)/(id:int)');
    }

    public function testRejectsDuplicateMethodAndRouteName(): void
    {
        $router = new Router(new Request());
        $router->get('/one', static fn (): string => 'one')->name('same');
        $router->post('/two', static fn (): string => 'two')->name('same');

        $this->expectException(\LogicException::class);
        $router->collection();
    }
}
