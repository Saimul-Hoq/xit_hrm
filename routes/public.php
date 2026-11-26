<?php

declare(strict_types=1);

use App\Core\Router;

/**
 * Public routes: reachable without logging in.
 *
 * Added later:
 *   Step 15: GET/POST /admin/login         (GuestMiddleware)
 *   Step 21: GET /                         landing page
 *            GET/POST /employee/login      (GuestMiddleware)
 *
 * The admin login URL is never linked from any public page.
 */
return static function (Router $router): void {
};