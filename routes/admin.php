<?php

declare(strict_types=1);

use App\Core\Router;

/**
 * Admin routes. From Step 15, every route here sits inside one group:
 *
 *   $router->group([AdminMiddleware::class], static function (Router $router): void { ... });
 *
 * so no admin page can be left unprotected by accident.
 */
return static function (Router $router): void {
};