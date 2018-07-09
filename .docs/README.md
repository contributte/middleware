## Middlewares / Relay

The middlewares / relay conception is a strong pattern with many benefits.

![Cycle](https://raw.githubusercontent.com/contributte/middlewares/master/.docs/assets/cycle.png)

## Content

- [Installation - how to register an extension](#installation)
- [Modes - nette/standalone mode](#modes)
- [Application - life cycle](#application)
- [Middlewares](#middlewares)
    - [AbstractRootMiddleware](#abstractrootmiddleware)
    - [AutoBasePathMiddleware](#autobasepathmiddleware)
    - [BaseMiddleware](#basemiddleware)
    - [BasePathMiddleware](#basepathmiddleware)
    - [BasicAuthMiddleware](#basicauthmiddleware)
    - [BuilderMiddleware](#buildermiddleware)
    - [PresenterMiddleware](#presentermiddleware)
    - [SecurityMiddleware](#securitymiddleware)
    - [TracyMiddleware](#tracymiddleware)
    - [TryCatchMiddleware](#trycactchmiddleware)
- [Utils](#utils)
    - [ChainBuilder](#chainbuilder)
    - [Lambda](#lambda)

## Installation

First of all you have to register one of the given extensions ([CompilerExtensions](https://api.nette.org/2.4/Nette.DI.CompilerExtension.html))) in your config file.
There are basically 2 single modes.

**Standalone mode** is best suitable for new projects with middleware architecture, works great with [apitte](https://github.com/apitte/core).

**Nette mode** is for integration to already running Nette projects, it overrides `Nette\Application\Application`.

```yaml
extensions:
    # standalone mode
    middleware: Contributte\Middlewares\DI\MiddlewaresExtension

    # nette application mode
    middleware: Contributte\Middlewares\DI\NetteMiddlewaresExtension
```

## Modes

Main difference to `nette/sandbox` application is in `index.php`. You have to `run` the middleware native `IApplication::run()`.

```php
$container->getByType(Contributte\Middlewares\Application\IApplication::class)->run();
```

That's all. The main purpose of this is to start via our application, not the default one `Nette\Application\Application`.

## Application

`AbstractApplication` adds a life cycle events you can interact with. There are 4 events:

- `startup` - triggered when `$app->run()` is called
- `request` - triggered before the chain is called
- `error` - triggered when exceptions is ocurred
- `response` - triggered after the chain is called

You attach listener calling the method `$app->addListener(type, callback)`.

```yaml
services:
    middleware.application:
      setup:
        - addListener(startup, [@logger, 'logStartup'])
        - addListener(request, [@logger, 'logRequest'])
        - addListener(error, [@logger, 'logError'])
        - addListener(response, [@logger, 'logResponse'])
```

## Middlewares

Build your own middleware chain cannot be easier. Just place your middleware (services) under `middleware` section.
It is pretty same as register new service in `NEON` file.

### List

You can register list of middlewares like this:

```yaml
middleware:
  middlewares:
    # Catch all exceptions
    - Contributte\Middlewares\TracyMiddleware

    # Your custom middlewares
    - TrailingSlashMiddleware
    - UuidMiddleware
    - CspMiddleware

    # Compatibility with Nette applications
    - Contributte\Middlewares\PresenterMiddleware
```

### RootMiddleware

Or just register **root** middleware as the very first entrypoint.

```yaml
middleware:
  root: App\Model\AppMiddleware
```

### Tags

Or you can use tags at services.

```yaml
services:
  m1:
    factory: App\Model\AppMiddleware1
    tags: [middleware: [priority: 5]]

  m2:
    factory: App\Model\AppMiddleware2
    tags: [middleware]
    # default priority = 10

  m3:
    factory: App\Model\AppMiddleware3
    tags: [middleware: [priority: 15]]
```

The final middleware list is:

- `AppMiddleware1`
- `AppMiddleware2`
- `AppMiddleware3`

### Ready-to-use middlewares

At this time we have prepared a few middlewares:

#### `AbstractRootMiddleware`

Use should use this middleware if you prefer PHP scripts before NEON declarations.

```php
namespace App;

use Contributte\Middleares\AbstractRootMiddleware;
use Contributte\Middleares\TracyMiddleware;
use Contributte\Middleares\Utils\ChainBuilder;

final class MyAppMiddleware extends AbstractRootMiddleware
{

    public function create()
    {
        $chain = new ChainBuilder();
        $chain->add(new TracyMiddleware());
        $chain->add(new MyCustomMiddleware());

        return $chain->build();
    }

}
```

#### `AutoBasePathMiddleware`

It strips basePath from URL address and pass new URL (without basePath) to next middleware.

```yaml
middleware:
  middlewares:
    # Catch all exceptions
    - Contributte\Middlewares\TracyMiddleware
    - Contributte\Middlewares\AutoBasePathMiddleware

    # Your custom middlewares
    - TrailingSlashMiddleware
    - UuidMiddleware
    - CspMiddleware
```

#### `BaseMiddleware`

This is just abstract class for your middlewares.

```php
namespace App;

use Contributte\Middleares\BaseMiddleware;

final class MyCustomMiddleware extends BaseMiddleware
{


    /**
     * @param ServerRequestInterface $psr7Request
     * @param ResponseInterface $psr7Response
     * @param callable $next
     * @return ResponseInterface
     */
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, callable $next)
    {
        // Let's play
    }

}
```

#### `BasePathMiddleware`

It's quite similar with `AutoBasePathMiddleware`, but you could define the basePath.

```yaml
middleware:
  middlewares:
    # Catch all exceptions
    - Contributte\Middlewares\TracyMiddleware
    - Contributte\Middlewares\BasePathMiddleware(project/www)

    # Your custom middlewares
    - TrailingSlashMiddleware
    - UuidMiddleware
    - CspMiddleware
```

#### `BasicAuthMiddleware`

Basic HTTP Authentication.

```yaml
middleware:
    middlewares:
        -
            class: Contributte\Middlewares\BasicAuthMiddleware
            setup:
                - addUser('user1', 'password1', true) // third parameter sets that password is not hashed
                - addUser('user2', '$2y$10$p.U5q.BuQp02srggig.VDOqj5m7pE1rCwKavVQ3S2TrqWlkqu3qlC')
                - addUser('user3', '$2y$10$bgievYVQMzsRn5Ysup.NKOVUk66aitAniAmts2EJAa91eqkAhohvC')
```

#### `BuilderMiddleware`

Over this middleware you can build your own chain of middlewares.

```yaml
middleware:
  middlewares:
    # Catch all exceptions
    - Contributte\Middlewares\TracyMiddleware
    - @builder

services:
    builders:
      class: Contributte\Middlewares\BuilderMiddleware
      setup:
        - add(TrailingSlashMiddleware())
        - add(UuidMiddleware())
        - add(CspMiddleware())
```

#### `PresenterMiddleware`

This middleware simulates original nette application behaviours. It converts Psr7Request to `Nette\Application\Request`
and process returned `Nette\Application\Response`.

```yaml
middleware:
  middlewares:
    # Catch all exceptions
    - Contributte\Middlewares\TracyMiddleware
    -
        class: Contributte\Middlewares\PresenterMiddleware
        setup:
            - setErrorPresenter(Nette:Error)
            - setCatchExceptions(%productionMode%)
```

PresenterMiddleware requires to run middlewares in Nette mode. Take a look at running [modes#application].

#### `TracyMiddleware`

This middleware catch all exceptions and shows tracy dump. It can be instanced normally or via factory `TracyMiddleware::factory($debugMode)`.

```yaml
middleware:
  middlewares:
    tracy1:
      class: Contributte\Middlewares\TracyMiddleware
      setup:
        - enable()
        - setMode(Tracy\Debugger::PRODUCTION)
        - setEmail(cool@contributte.org)

    tracy2:
      class: Contributte\Middlewares\TracyMiddleware::factory(%debugMode%)
      setup:
        - setMode(Tracy\Debugger::PRODUCTION)
        - setEmail(cool@contributte.org)
```

#### `TryCatchMiddleware`

This middlewares catches all exceptions thrown in application and outputs their message with status code 500. It should be registered first.

```yaml
middleware:
    middlewares:
        -
            class: Contributte\Middlewares\TryCatchMiddleware
            setup:
                - setCatchExceptions(%productionMode%)
```

#### `MethodOverrideMiddleware`

This middleware overrides HTTP method using `X-HTTP-Method-Override` header. A typical use case would be when your API is behind some proxy/VPN which only allows some HTTP methods, for example only `GET` and `POST`. Sending header `X-HTTP-Method-Override: PUT` will change the request method to `PUT`.

```yaml
middleware:
  middlewares:
    - Contributte\Middlewares\MethodOverrideMiddleware
```

## Utils

### ChainBuilder

```php
$builder = new ChainBuilder();
$builder->add(function ($req, $res, callable $next) {
    return $next($req, $res);
});
$builder->add(function ($req, $res, callable $next) {
    return $next($req, $res);
});
$middleware = $builder->create();
```

### Lambda

Lambda utils class creates anonymous functions.

```php
Lambda::leaf()
// ===
return function (RequestInterface $request, ResponseInterface $response) {
    return $response;
};
```

```php
Lambda::blank()
// ===
return function () {
};
```
