<?php

declare(strict_types=1);

namespace App\Validators;

/**
 * Admin login form: email + password (format only; AuthService checks them against the database).
 *
 * Employee login (Step 21) is identified by employee code instead of email;
 * its rules are added to this class in that step.
 */
final class LoginValidator extends Validator
{
    private const MAX_EMAIL_LENGTH = 255;

    /** Long enough for any real password, short enough to refuse absurd input. */
    private const MAX_PASSWORD_LENGTH = 255;

    /**
     * @param array<string, string|list<string>> $input
     * @return array{email: string, password: string}
     */
    protected function rules(array $input): array
    {
        // Emails are compared case-insensitively anyway; lower-case keeps them tidy.
        $email    = mb_strtolower($this->text($input, 'email'));
        $password = $this->raw($input, 'password'); // never trimmed

        $this->required('email', $email, 'Email');
        $this->email('email', $email, 'Email');
        $this->maxLength('email', $email, self::MAX_EMAIL_LENGTH, 'Email');

        $this->required('password', $password, 'Password');
        $this->maxLength('password', $password, self::MAX_PASSWORD_LENGTH, 'Password');

        return ['email' => $email, 'password' => $password];
    }
}