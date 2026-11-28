<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * The current user is not allowed to do this (wrong role, someone else's data...).
 * ErrorHandler answers 403 Forbidden.
 *
 * The message is for the LOG only (e.g. "Employee #7 requested employee #9's photo");
 * users always see the generic 403 page.
 */
final class AuthorizationException extends RuntimeException
{
}