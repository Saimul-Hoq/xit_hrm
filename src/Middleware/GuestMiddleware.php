<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Enums\Role;
use App\Services\AuthService;

/**
 * Only visitors who are NOT logged in may pass (login pages).
 * A logged-in user is sent to their own dashboard instead.
 */
final class GuestMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly AuthService $auth)
    {
    }

    public function handle(Request $request): ?string
    {
        return match ($this->auth->currentRole()) {
            Role::Admin    => '/admin/dashboard',
            Role::Employee => '/employee/dashboard',
            null           => null,
        };
    }
}