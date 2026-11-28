<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * The request breaks a business rule, e.g. "An active employee cannot be deleted".
 * Thrown by Services.
 *
 * Unlike other exceptions, the message IS shown to the user (as a flash message,
 * Step 12b), so it must be a clear, safe sentence written by our own code:
 * never include raw input, SQL or internal details in it.
 */
final class BusinessRuleException extends RuntimeException
{
}