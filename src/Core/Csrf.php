<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Cross-Site Request Forgery protection (synchronizer token pattern).
 *
 * - One random token per session, stored in the Session.
 * - Every POST form carries it in a hidden field named Csrf::FIELD_NAME.
 * - Controllers check the submitted value with isValid() before doing anything.
 * - rotate() after login (privilege change); logout destroys the session and the token with it.
 *
 * The HTML for the hidden field is produced by View (Step 10), not here.
 */
final class Csrf
{
    /** Name of the hidden form field. */
    public const FIELD_NAME = '_csrf';

    private const SESSION_KEY = 'csrf_token';

    /** 32 bytes = 256 bits = 64 hex characters. */
    private const TOKEN_BYTES = 32;

    public function __construct(private readonly Session $session)
    {
    }

    /**
     * The current session's token, created on first use.
     */
    public function token(): string
    {
        $token = $this->session->get(self::SESSION_KEY);

        if (!is_string($token) || $token === '') {
            $token = $this->generate();
        }

        return $token;
    }

    /**
     * True only if a token exists in the session AND the submitted value matches it exactly.
     * Never creates a token: a session without one cannot have produced a valid form.
     */
    public function isValid(?string $submitted): bool
    {
        $expected = $this->session->get(self::SESSION_KEY);

        if (!is_string($expected) || $expected === '' || $submitted === null || $submitted === '') {
            return false;
        }

        // Constant-time comparison: no timing leak.
        return hash_equals($expected, $submitted);
    }

    /**
     * Replaces the token. Call after login, together with Session::regenerate().
     */
    public function rotate(): void
    {
        $this->generate();
    }

    private function generate(): string
    {
        $token = bin2hex(random_bytes(self::TOKEN_BYTES));
        $this->session->set(self::SESSION_KEY, $token);

        return $token;
    }
}