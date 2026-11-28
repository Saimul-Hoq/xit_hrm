<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Submitted form data is malformed (missing, too long, wrong format...).
 * Thrown by Validators. Carries one message per field.
 *
 * ErrorHandler (Step 12b) sends the user back to the form with these errors.
 */
final class ValidationException extends RuntimeException
{
    /**
     * @param array<string, string> $errors Field name => user-facing message
     */
    public function __construct(
        private readonly array $errors,
        string $message = 'The submitted data is invalid.',
    ) {
        parent::__construct($message);
    }

    /**
     * @return array<string, string>
     */
    public function errors(): array
    {
        return $this->errors;
    }
}