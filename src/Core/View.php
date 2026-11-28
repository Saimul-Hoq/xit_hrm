<?php

declare(strict_types=1);

namespace App\Core;

use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Renders templates from views/ into HTML strings.
 *
 * Templates see only the data passed to them, plus $view for the helpers:
 *   $view->e($value)          escape any value for HTML (use for EVERY printed value)
 *   $view->url('/path', [..]) internal URL, already escaped for an HTML attribute
 *   $view->asset('css/x.css') asset URL, already escaped
 *   $view->csrfField()        hidden CSRF input for POST forms
 *   $view->partial('name')    render views/partials/name.php
 *   $view->fieldError($errors, 'field')
 *   $view->errors()           field errors from the previous (failed) submission
 *   $view->old('field', $def) value typed in the previous (failed) submission
 *   $view->oldList('field')   checkbox values from the previous (failed) submission
 *
 * render() RETURNS the HTML: nothing is sent until the controller sends it.
 */
final class View
{
    /** Session flash-data keys written by ErrorHandler after a ValidationException. */
    public const ERRORS_KEY = 'errors';
    public const OLD_INPUT_KEY = 'old_input';

    /** "admin/dashboard", "partials/flash": lower-case words, digits, "-" and "_". */
    private const NAME_PATTERN = '#^[a-z0-9_-]+(?:/[a-z0-9_-]+)*$#';

    /** Variable names templates rely on; callers may not pass them as data. */
    private const RESERVED_KEYS = ['view', 'content'];

    public function __construct(
        private readonly string $viewsPath,
        private readonly Url $url,
        private readonly Csrf $csrf,
        private readonly Session $session,
        private readonly string $appName,
    ) {
    }

    /**
     * Renders views/{template}.php, wrapped in views/layouts/{layout}.php unless $layout is null.
     * When a layout is used, $data must contain 'title'. The layout receives the same data plus 'content'.
     *
     * All input is checked BEFORE anything is rendered.
     *
     * @param array<string, mixed> $data
     */
    public function render(string $template, array $data = [], ?string $layout = 'app'): string
    {
        $this->assertValidData($data);

        if ($layout !== null && (!isset($data['title']) || !is_string($data['title']))) {
            throw new InvalidArgumentException("View '{$template}' uses a layout, so \$data['title'] (string) is required.");
        }

        $content = $this->renderFile($this->resolve($template), $data);

        if ($layout === null) {
            return $content;
        }

        return $this->renderFile($this->resolve('layouts/' . $layout), $data + ['content' => $content]);
    }

    /**
     * Renders views/partials/{name}.php (no layout).
     *
     * @param array<string, mixed> $data
     */
    public function partial(string $name, array $data = []): string
    {
        $this->assertValidData($data);

        return $this->renderFile($this->resolve('partials/' . $name), $data);
    }

    /**
     * Escapes a value for safe output in HTML text and attributes.
     */
    public function e(string|int|float|null $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
    }

    /**
     * Internal URL, escaped and ready for href="..." / action="...".
     *
     * @param array<string, scalar|list<scalar>> $query
     */
    public function url(string $path, array $query = []): string
    {
        return $this->e($this->url->to($path, $query));
    }

    /**
     * Asset URL, escaped and ready for href="..." / src="...".
     */
    public function asset(string $file): string
    {
        return $this->e($this->url->asset($file));
    }

    /**
     * Hidden CSRF field. Put it inside EVERY <form method="post">.
     */
    public function csrfField(): string
    {
        return '<input type="hidden" name="' . $this->e(Csrf::FIELD_NAME) . '" value="' . $this->e($this->csrf->token()) . '">';
    }

    /**
     * The error message for one form field (empty string when there is none).
     *
     * @param array<string, string> $errors Field name => message
     */
    public function fieldError(array $errors, string $field): string
    {
        return $this->partial('field-error', ['errors' => $errors, 'field' => $field]);
    }

    /**
     * Field errors from the previous request's failed submission (empty array if none).
     *
     * @return array<string, string>
     */
    public function errors(): array
    {
        $errors = $this->session->getFlashData(self::ERRORS_KEY, []);

        if (!is_array($errors)) {
            return [];
        }

        return array_filter($errors, static fn (mixed $message, mixed $field): bool => is_string($field) && is_string($message), ARRAY_FILTER_USE_BOTH);
    }

    /**
     * The value typed into a field before a failed submission, otherwise $default
     * (e.g. the current database value on an edit form). Passwords are never kept.
     */
    public function old(string $field, string $default = ''): string
    {
        $old   = $this->session->getFlashData(self::OLD_INPUT_KEY, []);
        $value = is_array($old) ? ($old[$field] ?? null) : null;

        return is_string($value) ? $value : $default;
    }

    /**
     * Checkbox values (e.g. departments[]) from before a failed submission, otherwise $default.
     *
     * @param list<string> $default
     * @return list<string>
     */
    public function oldList(string $field, array $default = []): array
    {
        $old   = $this->session->getFlashData(self::OLD_INPUT_KEY, []);
        $value = is_array($old) ? ($old[$field] ?? null) : null;

        if (!is_array($value)) {
            return $default;
        }

        return array_values(array_filter($value, 'is_string'));
    }

    /**
     * Flash messages for this page view. They are removed from the session once read.
     *
     * @return list<array{type: string, message: string}>
     */
    public function flashes(): array
    {
        return $this->session->takeFlashes();
    }

    public function appName(): string
    {
        return $this->appName;
    }

    private function resolve(string $name): string
    {
        if (preg_match(self::NAME_PATTERN, $name) !== 1) {
            throw new InvalidArgumentException("Invalid view name '{$name}'.");
        }

        $file = $this->viewsPath . '/' . $name . '.php';

        if (!is_file($file)) {
            throw new RuntimeException("View file not found: views/{$name}.php");
        }

        return $file;
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private function assertValidData(array $data): void
    {
        foreach (array_keys($data) as $key) {
            if (!is_string($key) || preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $key) !== 1) {
                throw new InvalidArgumentException("Invalid view variable name '{$key}'.");
            }

            if (in_array($key, self::RESERVED_KEYS, true)) {
                throw new InvalidArgumentException("View variable name '{$key}' is reserved.");
            }
        }
    }

    /**
     * Runs a template in an isolated scope and returns its output.
     * If the template throws, its partial output is discarded before the exception continues.
     *
     * @param array<string, mixed> $variables
     */
    private function renderFile(string $file, array $variables): string
    {
        $bufferLevel = ob_get_level();
        ob_start();

        try {
            (static function (string $__file, array $__variables, View $view): void {
                // EXTR_SKIP: data can never overwrite $__file, $__variables or $view.
                extract($__variables, EXTR_SKIP);
                require $__file;
            })($file, $variables, $this);
        } catch (Throwable $exception) {
            while (ob_get_level() > $bufferLevel) {
                ob_end_clean();
            }

            throw $exception;
        }

        return (string) ob_get_clean();
    }
}