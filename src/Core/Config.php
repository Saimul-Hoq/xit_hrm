<?php

declare(strict_types=1);

namespace App\Core;

use Closure;
use RuntimeException;

/**
 * Read-only access to the application's settings.
 *
 * Built once at startup from config/*.php, then passed into constructors.
 * Nothing else in the application reads Env or .env.
 */
final class Config
{
    /**
     * @param array<string, array<string, mixed>> $items Section name => settings
     */
    private function __construct(private readonly array $items)
    {
    }

    /**
     * Loads every config/*.php file. Each file must return a closure: fn(Env): array.
     * The file name becomes the section name (app.php -> "app").
     */
    public static function load(string $directory, Env $env): self
    {
        $files = glob(rtrim($directory, '/') . '/*.php');

        if ($files === false || $files === []) {
            throw new RuntimeException("No config files found in {$directory}");
        }

        sort($files);

        $items = [];

        foreach ($files as $file) {
            $factory = require $file;

            if (!$factory instanceof Closure) {
                throw new RuntimeException("Config file {$file} must return a closure.");
            }

            $section = $factory($env);

            if (!is_array($section)) {
                throw new RuntimeException("Config file {$file} must return an array from its closure.");
            }

            $items[basename($file, '.php')] = $section;
        }

        return new self($items);
    }

    /**
     * Reads a setting with dot notation, e.g. "database.port" or "app.session.name".
     * A missing key is a programming error (usually a typo), so it throws.
     */
    public function get(string $key): mixed
    {
        $value = $this->items;

        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                throw new RuntimeException("Unknown config key: {$key}");
            }

            $value = $value[$segment];
        }

        return $value;
    }

    public function has(string $key): bool
    {
        $value = $this->items;

        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return false;
            }

            $value = $value[$segment];
        }

        return true;
    }
}