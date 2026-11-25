<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Read-only, typed access to the current HTTP request.
 *
 * - Nothing else in the application reads $_GET, $_POST or $_SERVER.
 * - path() is whitelist-normalized: anything unexpected becomes null (-> 404).
 * - Values are returned as strings or lists of strings only, never raw arrays.
 */
final class Request
{
    private const MAX_PATH_LENGTH = 255;

    /** "/" or "/segment/segment..." where a segment is [A-Za-z0-9_-]+ */
    private const PATH_PATTERN = '#^/(?:[A-Za-z0-9_-]+(?:/[A-Za-z0-9_-]+)*)?$#';

    private readonly string $method;
    private readonly ?string $path;
    private readonly bool $https;

    /**
     * @param array<string, mixed> $server   Usually $_SERVER
     * @param array<string, mixed> $query    Usually $_GET
     * @param array<string, mixed> $body     Usually $_POST
     * @param string               $basePath Already normalized by Url (e.g. "" or "/hrm/public")
     */
    public function __construct(
        array $server,
        private readonly array $query,
        private readonly array $body,
        string $basePath,
    ) {
        $this->method = self::normalizeMethod($server['REQUEST_METHOD'] ?? 'GET');
        $this->path   = self::normalizePath($server['REQUEST_URI'] ?? '/', $basePath);
        $this->https  = self::detectHttps($server);
    }

    public static function fromGlobals(string $basePath): self
    {
        return new self($_SERVER, $_GET, $_POST, $basePath);
    }

    /**
     * Upper-case HTTP method ("GET", "POST"), or "" when the method is malformed.
     */
    public function method(): string
    {
        return $this->method;
    }

    public function isPost(): bool
    {
        return $this->method === 'POST';
    }

    /**
     * Normalized application path, e.g. "/admin/login".
     * Null means "not a valid application path": the Router answers 404.
     */
    public function path(): ?string
    {
        return $this->path;
    }

    public function isHttps(): bool
    {
        return $this->https;
    }

    /**
     * A single query-string value (?key=value). Null if missing or not a plain string.
     */
    public function query(string $key): ?string
    {
        return self::stringValue($this->query, $key);
    }

    /**
     * A single POST field. Null if missing or not a plain string.
     */
    public function input(string $key): ?string
    {
        return self::stringValue($this->body, $key);
    }

    /**
     * A query-string list (?key[]=a&key[]=b). Only string items are kept.
     *
     * @return list<string>
     */
    public function queryList(string $key): array
    {
        return self::stringList($this->query, $key);
    }

    /**
     * A POST list (checkbox groups like departments[]). Only string items are kept.
     *
     * @return list<string>
     */
    public function inputList(string $key): array
    {
        return self::stringList($this->body, $key);
    }

    private static function normalizeMethod(mixed $raw): string
    {
        if (!is_string($raw)) {
            return '';
        }

        $method = strtoupper($raw);

        return preg_match('/^[A-Z]{1,10}$/', $method) === 1 ? $method : '';
    }

    private static function normalizePath(mixed $uri, string $basePath): ?string
    {
        if (!is_string($uri) || $uri === '') {
            return null;
        }

        // 1. Drop the query string. (parse_url() is NOT used: it misreads "//admin" as a host.)
        $path = explode('?', $uri, 2)[0];

        // 2. Decode %xx once.
        $path = rawurldecode($path);

        // 3. Strip the base path for subfolder installs.
        if ($basePath !== '') {
            if ($path === $basePath) {
                $path = '/';
            } elseif (str_starts_with($path, $basePath . '/')) {
                $path = substr($path, strlen($basePath));
            } else {
                return null; // Outside this application.
            }
        }

        // 4. Collapse repeated slashes, drop a trailing slash.
        $path = (string) preg_replace('#/{2,}#', '/', $path);

        if ($path !== '/') {
            $path = rtrim($path, '/');
        }

        if ($path === '') {
            $path = '/';
        }

        // 5. Whitelist.
        if (strlen($path) > self::MAX_PATH_LENGTH || preg_match(self::PATH_PATTERN, $path) !== 1) {
            return null;
        }

        return $path;
    }

    /**
     * @param array<string, mixed> $server
     */
    private static function detectHttps(array $server): bool
    {
        $https = $server['HTTPS'] ?? '';

        if (is_string($https) && $https !== '' && strtolower($https) !== 'off') {
            return true;
        }

        return (string) ($server['SERVER_PORT'] ?? '') === '443';
    }

    /**
     * @param array<string, mixed> $source
     */
    private static function stringValue(array $source, string $key): ?string
    {
        $value = $source[$key] ?? null;

        return is_string($value) ? $value : null;
    }

    /**
     * @param array<string, mixed> $source
     * @return list<string>
     */
    private static function stringList(array $source, string $key): array
    {
        $value = $source[$key] ?? null;

        if (!is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, 'is_string'));
    }
}