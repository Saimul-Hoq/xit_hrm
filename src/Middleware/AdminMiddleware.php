<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Services\AuthService;

/**
 * Only a logged-in admin who still exists in the database may pass.
 * Everyone else is sent to the admin login page.
 */
final class AdminMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly AuthService $auth)
    {
    }

    public function handle(Request $request): ?string
    {
        return $this->auth->currentAdmin() === null ? '/admin/login' : null;
    }
}