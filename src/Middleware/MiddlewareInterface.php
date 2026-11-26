<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;

/**
 * An access gate the Router runs BEFORE the controller.
 *
 * - return null                 → allowed, continue to the next middleware / the controller
 * - return '/some/path'         → stop; the Router redirects to that internal path (303)
 * - throw AuthorizationException (Step 12) → stop with 403 Forbidden
 *
 * Middleware checks access only. It contains no business logic and produces no HTML.
 */
interface MiddlewareInterface
{
    public function handle(Request $request): ?string;
}