<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Reads settings from the real environment and from the .env file.
 *
 * Priority for every key:
 *   1. Real environment variable (getenv / $_ENV)  e.g. Apache SetEnv, PHP-FPM env[]
 *   2. Value from the .env file
 *   3. The default passed by the caller
 *
 * An empty value ("KEY=") counts as "not set", so the default applies.
 *
 * This is the ONLY class in the application that knows .env exists.
 */
final class Env
{
    private const UTF8_BOM = "\xEF\xBB\xBF";

    /**
     * @param array<string, string> $fileValues Values parsed from the .env file
     */
    private function __construct(private readonly array $fileValues)
    {
    }

    /**
     * Loads the .env file if it exists.
     * A missing file is NOT an error: the server may provide real environment variables instead.
     */
    public static function load(string $path): self
    {
        if (!is_file($path)) {
            return new self([]);
        }

        if (!is_readable($path)) {
            throw new RuntimeException("The .env file exists but is not readable: {$path}");
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException("Could not read the .env file: {$path}");
        }

        return new self(self::parse($contents));
    }

    /**
     * Returns the value, or $default when the key is missing or empty.
     */
    public function get(string $key, ?string $default = null): ?string
    {
        $value = $this->raw($key);

        return ($value === null || $value === '') ? $default : $value;
    }

    /**
     * Returns the value, or throws when it is missing or empty.
     */
    public function required(string $key): string
    {
        $value = $this->get($key);

        if ($value === null) {
            throw new RuntimeException(
                "Required setting {$key} is missing. Set it in .env or as a server environment variable."
            );
        }

        return $value;
    }

    public function getBool(string $key, bool $default): bool
    {
        $value = $this->get($key);

        if ($value === null) {
            return $default;
        }

        return match (strtolower($value)) {
            'true', '1', 'yes', 'on'  => true,
            'false', '0', 'no', 'off' => false,
            default => throw new RuntimeException(
                "Setting {$key} must be true or false, got '{$value}'."
            ),
        };
    }

    public function getInt(string $key, int $default, ?int $min = null, ?int $max = null): int
    {
        $value = $this->get($key);

        if ($value === null) {
            return $default;
        }

        if (preg_match('/^-?\d+$/', $value) !== 1) {
            throw new RuntimeException("Setting {$key} must be a whole number, got '{$value}'.");
        }

        $number = (int) $value;

        if (($min !== null && $number < $min) || ($max !== null && $number > $max)) {
            $range = ($min ?? '-∞') . ' to ' . ($max ?? '∞');
            throw new RuntimeException("Setting {$key} must be between {$range}, got {$number}.");
        }

        return $number;
    }

    /**
     * Returns the value only if it is one of the allowed choices (exact, case-sensitive match).
     *
     * @param list<string> $allowed
     */
    public function getChoice(string $key, array $allowed, string $default): string
    {
        $value = $this->get($key, $default);

        if (!in_array($value, $allowed, true)) {
            throw new RuntimeException(
                "Setting {$key} must be one of: " . implode(', ', $allowed) . ". Got '{$value}'."
            );
        }

        return $value;
    }

    /**
     * Real environment first, then the .env file.
     */
    private function raw(string $key): ?string
    {
        $fromServer = getenv($key);

        if ($fromServer !== false) {
            return $fromServer;
        }

        if (isset($_ENV[$key]) && is_string($_ENV[$key])) {
            return $_ENV[$key];
        }

        return $this->fileValues[$key] ?? null;
    }

    /**
     * Parses .env text into key => value pairs.
     *
     * Rules (fixed in Step 1):
     *  - One KEY=value per line; KEY is UPPER_SNAKE_CASE.
     *  - Everything after the FIRST "=" is the value, trimmed.
     *  - One pair of matching surrounding quotes (" or ') is removed.
     *  - Lines starting with # are comments; empty lines are ignored.
     *  - A UTF-8 BOM and Windows/old-Mac line endings are tolerated.
     *  - A duplicate key is an error.
     *
     * @return array<string, string>
     */
    private static function parse(string $contents): array
    {
        if (str_starts_with($contents, self::UTF8_BOM)) {
            $contents = substr($contents, strlen(self::UTF8_BOM));
        }

        $lines  = preg_split('/\r\n|\r|\n/', $contents) ?: [];
        $values = [];

        foreach ($lines as $index => $rawLine) {
            $lineNumber = $index + 1;
            $line       = trim($rawLine);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $equalsPosition = strpos($line, '=');

            if ($equalsPosition === false) {
                throw new RuntimeException(".env line {$lineNumber} is invalid: missing '='.");
            }

            $key = trim(substr($line, 0, $equalsPosition));

            if (preg_match('/^[A-Z][A-Z0-9_]*$/', $key) !== 1) {
                throw new RuntimeException(
                    ".env line {$lineNumber} is invalid: key '{$key}' must be UPPER_SNAKE_CASE."
                );
            }

            if (array_key_exists($key, $values)) {
                throw new RuntimeException(".env line {$lineNumber}: duplicate key {$key}.");
            }

            $values[$key] = self::unquote(trim(substr($line, $equalsPosition + 1)));
        }

        return $values;
    }

    private static function unquote(string $value): string
    {
        $length = strlen($value);

        if ($length >= 2) {
            $first = $value[0];
            $last  = $value[$length - 1];

            if (($first === '"' || $first === "'") && $first === $last) {
                return substr($value, 1, -1);
            }
        }

        return $value;
    }
}