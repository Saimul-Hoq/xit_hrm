<?php

declare(strict_types=1);

/**
 * Front controller: EVERY request that is not a real file enters here (see public/.htaccess).
 *
 *   1. bootstrap (startup checks, autoloader, config, logging, global error handling)
 *   2. security headers
 *   3. core objects  ← this file is the ONLY place where objects are created and wired
 *   4. lazy factories for controllers / middleware / services / repositories
 *   5. router: load routes, dispatch, inside ErrorHandler's safety net
 */

use App\Core\Clock;
use App\Core\Config;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\ErrorHandler;
use App\Core\Request;
use App\Core\Router;
use App\Core\Session;
use App\Core\Url;
use App\Core\View;

$projectRoot = dirname(__DIR__);

/** @var Config $config */
$config = require $projectRoot . '/bootstrap.php';

// ---------------------------------------------------------------- 2. security headers
header_remove('X-Powered-By');
header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'");
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
header('Cache-Control: no-store');

// ---------------------------------------------------------------- 3. core objects
$clock   = new Clock($config->get('app.timezone'));
$url     = new Url($config->get('app.base_path'));
$request = Request::fromGlobals($url->basePath());
$session = new Session($config->get('app.session.name'), $request->isHttps(), $url->basePath());
$csrf    = new Csrf($session);
$view    = new View($projectRoot . '/views', $url, $csrf, $session, $config->get('app.name'));

// Lazy: no connection is opened unless a repository actually runs a query.
$database = new Database(
    host:     $config->get('database.host'),
    port:     $config->get('database.port'),
    name:     $config->get('database.name'),
    user:     $config->get('database.user'),
    password: $config->get('database.password'),
    charset:  $config->get('database.charset'),
    clock:    $clock,
);

// Created before the output buffer, so it only ever discards buffers opened after it.
$errorHandler = new ErrorHandler($view, $session, $url, $config->get('app.debug'));

// ---------------------------------------------------------------- 4. lazy factories
/**
 * Class name => how to build it. A factory receives $resolve to ask for other objects.
 * Nothing here is built until a matched route needs it.
 *
 * Example (Step 15):
 *   AdminAuthController::class => static fn (Closure $resolve): object => new AdminAuthController(
 *       $resolve(AuthService::class), $view, $csrf, $url
 *   ),
 *
 * @var array<string, Closure(Closure): object> $factories
 */
$factories = [
];

/** @var array<string, object> $instances Objects already built during this request. */
$instances = [];

$resolve = static function (string $class) use (&$resolve, &$factories, &$instances): object {
    if (isset($instances[$class])) {
        return $instances[$class];
    }

    if (!isset($factories[$class])) {
        throw new LogicException("No factory registered for {$class}.");
    }

    return $instances[$class] = $factories[$class]($resolve);
};

// ---------------------------------------------------------------- 5. dispatch
ob_start(); // Output stays undecided until the end, so an error can still replace it.

try {
    $session->start();

    $router = new Router(
        $resolve,
        $url,
        static fn (Request $request) => $errorHandler->notFound(),
        static fn (Request $request, array $allowed) => $errorHandler->methodNotAllowed(),
    );

    foreach (['public', 'admin', 'employee'] as $routeFile) {
        $router->loadRoutes($projectRoot . "/routes/{$routeFile}.php");
    }

    $router->dispatch($request);
} catch (Throwable $exception) {
    $errorHandler->handle($exception, $request);
}

while (ob_get_level() > 0) {
    ob_end_flush();
}