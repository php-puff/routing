# Puff Routing

Fast, Fiber-safe HTTP route registration and matching for Puff.

The package compiles mutable route declarations once at application startup. Exact paths are stored in a hash index, dynamic paths are prebuilt as regular expressions, and every request receives an immutable `RouteMatched` value so route parameters are never shared between Fibers.

## Installation

```bash
composer require puff/routing
```

`puff/http-server` registers the router and loads route files automatically. A route file can use the global `Router` facade:

```php
<?php

Router::get('/', 'Home@index')->id('home');
Router::post('/users', 'User@create')->id('users.create');
Router::any('/health', static fn (): array => ['status' => 'ok']);
```

Supported registration methods are `any`, `get`, `post`, `put`, `patch`, `delete`, `head`, and `options`. A `HEAD` request falls back to its matching `GET` route when no explicit `HEAD` route exists.

## Route parameters

Parameters use `(name:type)` syntax:

```php
Router::get('/users/(id:int)', 'User@show');
Router::get('/posts/(slug:str)', 'Post@show');
Router::get('/files/(path:*)', 'File@show');
Router::get('/colors/(value:hex)', 'Color@show');
Router::get('/tokens/(value:hash)', 'Token@show');
Router::get('/events/(id:uuid)', 'Event@show');
Router::get('/jobs/(id:ulid)', 'Job@show');
Router::get('/prices/(value:num)', 'Price@show');
Router::get('/echo/(value:any)', 'Echo@show');
```

For a small fixed set of literal values, use an inline enum. It is compiled into a route
pattern automatically, so no separate `regex()` registration is needed:

```php
Router::get('/(page:about|contact|business|faq)', 'Page@show');
```

The controller receives the matched value in its `$page` argument.

Built-in parameter types:

| Type | Pattern | Notes |
| --- | --- | --- |
| `*` | `.+` | Any non-empty path content |
| `str` | `[\w-]+` | Letters, numbers, underscore and hyphen |
| `int` | `(?:0\|[1-9][0-9]*)` | Unsigned integer including zero |
| `num` | `-?(?:[0-9]+(?:\.[0-9]+)?)` | Integer or decimal value |
| `any` | `[\w!@$^&+\-=|]+` | Common non-path-separator characters |
| `hex` | `[A-Fa-f0-9]+` | Hexadecimal characters |
| `hash` | `[A-Za-z0-9]+` | Alphanumeric characters |
| `uuid` | UUID-shaped hexadecimal value | Standard 8-4-4-4-12 shape |
| `ulid` | Crockford Base32 ULID | 26 characters; excludes I, L, O, and U |

Register an application-specific pattern before declaring routes that use it:

```php
Router::regex(':slug', ':[a-z0-9]+(?:-[a-z0-9]+)*');
Router::get('/articles/(slug:slug)', 'Article@show');
```

Matched parameters are injected into controller arguments by name:

```php
final class User
{
    public function show(string $id): array
    {
        return ['id' => $id];
    }
}
```

## Route options

Pass an option array when a route needs a pipeline or metadata:

```php
Router::get('/account', [
    'call' => 'Account@index',
    'pipeline' => [Authenticate::class, AuthorizeAccount::class],
    'locale' => 'en',
    'menu' => true,
])->id('account');
```

Route pipelines run after the global HTTP pipeline. Duplicate pipeline class names are removed while preserving their first occurrence.

## Groups

Groups share a path prefix, controller namespace, pipeline and optional metadata:

```php
Router::group([
    'id' => 'api',
    'prefix' => '/api',
    'namespace' => 'App\\Controller\\Api',
    'pipeline' => [ApiHeaders::class],
    'locale' => 'en',
], static function (): void {
    Router::get('/status', 'Status@index')->id('api.status');

    Router::group([
        'id' => 'api.private',
        'prefix' => 'private',
        'pipeline' => [Authenticate::class],
    ], static function (): void {
        Router::get('/profile', 'Profile@index')->id('api.profile');
    });
});
```

Nested prefixes, namespaces and pipelines inherit from their parent. A nested prefix beginning with `/`, or a namespace beginning with `\`, replaces the corresponding parent value.

Configure a global pipeline on an HTTP item in `config/server.php`:

```php
'pipeline' => [
    RequestId::class,
    AccessLog::class,
],
```

The execution order is global pipeline first, followed by inherited group pipelines and then route pipelines.

## Named and current routes

```php
$current = route();
$route = route('api.profile');

$id = $current->definition()->id;
$parameters = $current->args();
```

During request dispatch, `route()` returns the request-local `RouteMatched`. Looking up a name returns its immutable `RouteDefinition`.

## Standalone matching

The compiler and matcher can be used without the HTTP server dispatcher:

```php
use Puff\Http\Request;
use Puff\Routing\RouteCompiler;
use Puff\Routing\RouteMatcher;
use Puff\Routing\Router;

$router = new Router(new Request());
$router->get('/users/(id:int)', 'User@show')->id('users.show');

$routes = (new RouteCompiler())->compile($router->routes());
$matched = (new RouteMatcher($routes))->match('GET', '/users/42');

echo $matched->definition()->id; // users.show
print_r($matched->args());       // ['id' => '42']
```

`RouteMatcher::match()` throws `NotFoundException` with HTTP status `404`, or `MethodNotAllowedException` with status `405`.

## Performance characteristics

- Route files are loaded and compiled once by `puff/http-server`, not once per request.
- Exact route lookup is average `O(1)`.
- Dynamic routes are indexed by their longest static path prefix. Only patterns in the matching prefix buckets are tested; overlapping patterns within the same bucket remain declaration ordered.
- Exact routes always take priority over dynamic routes.
- Compiled definitions and matched results are immutable; only the request-scoped router stores the current match.

For large applications, prefer exact routes where practical and avoid many overlapping catch-all patterns.

## Development

```bash
composer validate --strict
composer test
composer analyse
```

Puff Routing requires PHP 8.2 or later and is released under the MIT License.
