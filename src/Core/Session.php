<?php

declare(strict_types=1);

namespace App\Core;

use InvalidArgumentException;
use LogicException;
use RuntimeException;

/**
 * The only class that touches PHP's session ($_SESSION, session_* functions).
 *
 * - Secure cookie: HttpOnly, SameSite=Lax, Secure on HTTPS, browser-session lifetime,
 *   scoped to the app's base path.
 * - Strict mode: PHP only accepts session IDs it created (fixation defence).
 * - regenerate() after login, destroy() on logout.
 * - Flash messages: stored once, read once.
 *
 * Callers store only small identifiers (e.g. role + id), never copies of user data.
 */
final class Session
{
    /** Keys starting with "_" are reserved for this class. */
    private const FLASH_KEY = '_flash';

    private const FLASH_TYPES = ['success', 'error', 'warning', 'info'];

    private readonly string $cookiePath;

    /**
     * @param string $name       Cookie name, from SESSION_NAME (letters and digits only)
     * @param bool   $secure     True on HTTPS: the cookie is then never sent over plain HTTP
     * @param string $basePath   Normalized base path from Url ("" or e.g. "/hrm/public")
     */
    public function __construct(
        private readonly string $name,
        private readonly bool $secure,
        string $basePath,
    ) {
        // PHP session names must be alphanumeric and contain at least one letter.
        if (preg_match('/^[A-Za-z0-9]*[A-Za-z][A-Za-z0-9]*$/', $name) !== 1) {
            throw new InvalidArgumentException(
                "Invalid session name '{$name}': use letters and digits only, with at least one letter."
            );
        }

        $this->cookiePath = $basePath === '' ? '/' : $basePath;
    }

    /**
     * Starts the session with secure settings. Safe to call more than once.
     * Must run before any output is sent (the cookie is an HTTP header).
     */
    public function start(): void
    {
        if ($this->isStarted()) {
            return;
        }

        if (headers_sent($file, $line)) {
            throw new RuntimeException("Cannot start the session: output already started at {$file}:{$line}.");
        }

        // Checked, not changed: altering these is deprecated since PHP 8.4, and the
        // safe values are the defaults. A server that changed them is refused.
        $onlyCookies = filter_var(ini_get('session.use_only_cookies'), FILTER_VALIDATE_BOOLEAN);
        $transSid    = filter_var(ini_get('session.use_trans_sid'), FILTER_VALIDATE_BOOLEAN);

        if (!$onlyCookies || $transSid) {
            throw new RuntimeException(
                'Insecure PHP session settings: session.use_only_cookies must be 1 and session.use_trans_sid must be 0.'
            );
        }

        $started = session_start([
            'name'            => $this->name,
            'use_strict_mode' => true,
            'use_cookies'     => true,
            'cookie_lifetime' => 0,
            'cookie_path'     => $this->cookiePath,
            'cookie_secure'   => $this->secure,
            'cookie_httponly' => true,
            'cookie_samesite' => 'Lax',
        ]);

        if (!$started) {
            throw new RuntimeException('session_start() failed.');
        }
    }

    public function isStarted(): bool
    {
        return session_status() === PHP_SESSION_ACTIVE;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $this->assertStarted();

        return $_SESSION[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->assertStarted();
        $this->assertNotReserved($key);

        $_SESSION[$key] = $value;
    }

    public function has(string $key): bool
    {
        $this->assertStarted();

        return array_key_exists($key, $_SESSION);
    }

    public function remove(string $key): void
    {
        $this->assertStarted();
        $this->assertNotReserved($key);

        unset($_SESSION[$key]);
    }

    /**
     * New session ID, same data. Call right after a successful login.
     * The old ID is deleted on the server, so a previously known ID becomes useless.
     */
    public function regenerate(): void
    {
        $this->assertStarted();

        if (!session_regenerate_id(true)) {
            throw new RuntimeException('session_regenerate_id() failed.');
        }
    }

    /**
     * Full logout: empty the data, delete the server file, expire the browser cookie.
     * Call start() again afterwards if the next response needs a session (e.g. a flash message).
     */
    public function destroy(): void
    {
        if (!$this->isStarted()) {
            return;
        }

        $_SESSION = [];

        $params = session_get_cookie_params();

        setcookie(session_name(), '', [
            'expires'  => 1, // 1970-01-01: a date in the past tells the browser to delete it
            'path'     => $params['path'],
            'domain'   => $params['domain'],
            'secure'   => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => $params['samesite'],
        ]);

        session_destroy();
    }

    /**
     * Stores a message for the NEXT page view only.
     */
    public function flash(string $type, string $message): void
    {
        if (!in_array($type, self::FLASH_TYPES, true)) {
            throw new InvalidArgumentException(
                "Unknown flash type '{$type}'. Allowed: " . implode(', ', self::FLASH_TYPES) . '.'
            );
        }

        $this->assertStarted();

        $_SESSION[self::FLASH_KEY][] = ['type' => $type, 'message' => $message];
    }

    /**
     * Returns all flash messages and removes them, so each is shown exactly once.
     *
     * @return list<array{type: string, message: string}>
     */
    public function takeFlashes(): array
    {
        $this->assertStarted();

        $flashes = $_SESSION[self::FLASH_KEY] ?? [];
        unset($_SESSION[self::FLASH_KEY]);

        return is_array($flashes) ? array_values($flashes) : [];
    }

    private function assertStarted(): void
    {
        if (!$this->isStarted()) {
            throw new LogicException('The session is not started. Call Session::start() first.');
        }
    }

    private function assertNotReserved(string $key): void
    {
        if (str_starts_with($key, '_')) {
            throw new InvalidArgumentException("Session key '{$key}' is reserved (keys starting with '_').");
        }
    }
}