<?php

declare(strict_types=1);

namespace App\Core;

use InvalidArgumentException;

/**
 * Builds internal URLs that work at the domain root AND in a subfolder.
 *
 * - Only internal paths ("/admin/login") are accepted: no "//host", no "http://",
 *   no "..", no line breaks. This is what makes redirects safe (no open redirects,
 *   no header injection).
 * - Returns RAW URLs: views must still escape them when printing into HTML.
 */
final class Url
{
    /** "" or "/folder" or "/folder/sub" (folder names may contain dots) */
    private const BASE_PATH_PATTERN = '#^(?:/[A-Za-z0-9._~-]+)*$#';

    /** "/" or "/segment/segment..." (dots allowed for asset files like app.css) */
    private const PATH_PATTERN = '#^/(?:[A-Za-z0-9._~-]+(?:/[A-Za-z0-9._~-]+)*)?$#';

    private readonly string $basePath;

    /**
     * @param string $basePath Raw APP_BASE_PATH value, e.g. "", "/", "/hrm/public", "/hrm/public/"
     */
    public function __construct(string $basePath)
    {
        $normalized = rtrim(trim($basePath), '/');

        if (preg_match(self::BASE_PATH_PATTERN, $normalized) !== 1 || self::hasDotSegment($normalized)) {
            throw new InvalidArgumentException(
                "Invalid APP_BASE_PATH '{$basePath}'. Use a form like /hrm/public (leading slash, no trailing slash)."
            );
        }

        $this->basePath = $normalized;
    }

    /**
     * Normalized base path: "" (domain root) or e.g. "/hrm/public".
     */
    public function basePath(): string
    {
        return $this->basePath;
    }

    /**
     * Internal URL with optional query string.
     *
     *   to('/admin/login')                          -> /hrm/public/admin/login
     *   to('/employee/login', ['employee' => 'XIT-001']) -> /hrm/public/employee/login?employee=XIT-001
     *
     * @param array<string, scalar|list<scalar>> $query
     */
    public function to(string $path, array $query = []): string
    {
        if (preg_match(self::PATH_PATTERN, $path) !== 1 || self::hasDotSegment($path)) {
            throw new InvalidArgumentException("Url::to() needs an internal path like '/admin/login', got '{$path}'.");
        }

        $url = $this->basePath . $path;

        if ($query !== []) {
            $url .= '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        }

        return $url;
    }

    /**
     * URL of a file under public/assets, e.g. asset('css/app.css').
     */
    public function asset(string $file): string
    {
        return $this->to('/assets/' . ltrim($file, '/'));
    }

    /**
     * True if the path contains a "." or ".." segment.
     */
    private static function hasDotSegment(string $path): bool
    {
        return preg_match('#(?:^|/)\.{1,2}(?:/|$)#', $path) === 1;
    }
}