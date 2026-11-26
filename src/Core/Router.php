<?php

declare(strict_types=1);

namespace App\Core;

use App\Middleware\MiddlewareInterface;
use Closure;
use InvalidArgumentException;
use LogicException;

/**
 * Exact-match router: (METHOD, normalized path) → controller method, behind middleware.
 *
 * - The URL is only a lookup key. It never becomes a file, class or method name.
 * - Unknown path → 404 handler. Known path, wrong method → 405 handler + "Allow" header.
 * - HEAD is answered by the GET route (the server drops the body).
 * - Controllers and middleware are built lazily through $resolve: only what the matched route needs.
 *
 * Controller action contract: public function action(Request $request): void
 * (the action sends its own response: echo rendered HTML or redirect).
 */
final class Router
{
    /** Must match Request::path()'s whitelist, so every route is reachable. */
    private const PATH_PATTERN = '#^/(?:[A-Za-z0-9_-]+(?:/[A-Za-z0-9_-]+)*)?$#';

    /** Action names: lowerCamelCase method names. */
    private const ACTION_PATTERN = '/^[a-z][A-Za-z0-9]*$/';

    /**
     * $routes[path][METHOD] = ['controller' => class, 'action' => method, 'middleware' => [classes]]
     *
     * @var array<string, array<string, array{controller: string, action: string, middleware: list<string>}>>
     */
    private array $routes = [];

    /** Middleware of the groups currently being defined (outermost first). @var list<string> */
    private array $groupMiddleware = [];

    /**
     * @param Closure(string): object              $resolve          Builds an object from a class name (index.php)
     * @param Closure(Request): void               $notFound         Sends the 404 response
     * @param Closure(Request, list<string>): void $methodNotAllowed Sends the 405 response
     */
    public function __construct(
        private readonly Closure $resolve,
        private readonly Url $url,
        private readonly Closure $notFound,
        private readonly Closure $methodNotAllowed,
    ) {
    }

    /**
     * @param list<string> $middleware Middleware class names for this route only (after group middleware)
     */
    public function get(string $path, string $controller, string $action, array $middleware = []): void
    {
        $this->add('GET', $path, $controller, $action, $middleware);
    }

    /**
     * @param list<string> $middleware
     */
    public function post(string $path, string $controller, string $action, array $middleware = []): void
    {
        $this->add('POST', $path, $controller, $action, $middleware);
    }

    /**
     * Every route defined inside $define gets $middleware (in addition to any outer group's).
     *
     * @param list<string>            $middleware
     * @param Closure(Router): void   $define
     */
    public function group(array $middleware, Closure $define): void
    {
        $outer = $this->groupMiddleware;
        $this->groupMiddleware = [...$outer, ...array_values($middleware)];

        try {
            $define($this);
        } finally {
            $this->groupMiddleware = $outer;
        }
    }

    /**
     * Loads a route file. It must return: static function (Router $router): void { ... }
     */
    public function loadRoutes(string $file): void
    {
        $define = require $file;

        if (!$define instanceof Closure) {
            throw new LogicException("Route file {$file} must return a closure that receives the Router.");
        }

        $define($this);
    }

    public function dispatch(Request $request): void
    {
        $path = $request->path();

        // 404: rejected by Request::path(), or not in the table.
        if ($path === null || !isset($this->routes[$path])) {
            ($this->notFound)($request);
            return;
        }

        $method = $request->method() === 'HEAD' ? 'GET' : $request->method();
        $route  = $this->routes[$path][$method] ?? null;

        // 405: the path exists, but not for this method.
        if ($route === null) {
            $allowed = array_keys($this->routes[$path]);

            if (in_array('GET', $allowed, true)) {
                $allowed[] = 'HEAD';
            }

            sort($allowed);
            header('Allow: ' . implode(', ', $allowed));
            ($this->methodNotAllowed)($request, $allowed);
            return;
        }

        // Middleware, in order. The first one that returns a path stops the request.
        foreach ($route['middleware'] as $middlewareClass) {
            $middleware = ($this->resolve)($middlewareClass);

            if (!$middleware instanceof MiddlewareInterface) {
                throw new LogicException("{$middlewareClass} does not implement MiddlewareInterface.");
            }

            $redirectTo = $middleware->handle($request);

            if ($redirectTo !== null) {
                header('Location: ' . $this->url->to($redirectTo), true, 303);
                return;
            }
        }

        // Controller: built only now, only this one.
        $controller = ($this->resolve)($route['controller']);
        $action     = $route['action'];

        if (!is_callable([$controller, $action])) {
            throw new LogicException("Action {$route['controller']}::{$action}() does not exist or is not public.");
        }

        $controller->{$action}($request);
    }

    /**
     * @param list<string> $middleware
     */
    private function add(string $method, string $path, string $controller, string $action, array $middleware): void
    {
        if (preg_match(self::PATH_PATTERN, $path) !== 1) {
            throw new InvalidArgumentException("Route path '{$path}' is not a normalized path like '/admin/login'.");
        }

        if ($controller === '' || preg_match(self::ACTION_PATTERN, $action) !== 1) {
            throw new InvalidArgumentException("Invalid route target '{$controller}::{$action}' for {$method} {$path}.");
        }

        if (isset($this->routes[$path][$method])) {
            throw new LogicException("Duplicate route: {$method} {$path}");
        }

        $this->routes[$path][$method] = [
            'controller' => $controller,
            'action'     => $action,
            'middleware' => [...$this->groupMiddleware, ...array_values($middleware)],
        ];
    }
}