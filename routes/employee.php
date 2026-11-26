<?php

declare(strict_types=1);

use App\Core\Router;

/**
 * Employee routes. From Step 21, two groups:
 *
 *   [EmployeeMiddleware]                              logout, profile completion
 *   [EmployeeMiddleware, ProfileCompleteMiddleware]   everything else (dashboard, attendance, ...)
 *
 * All state-changing actions (Start Work, Break, Resume, End Work, logout) are POST.
 */
return static function (Router $router): void {
};