<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Any failure talking to the database: connecting, preparing, executing.
 *
 * The message is technical and meant for logs only. Users never see it:
 * ErrorHandler (Step 12) shows a generic 500 page instead.
 * The original mysqli_sql_exception is available through getPrevious().
 */
final class DatabaseException extends RuntimeException
{
}