<?php

declare(strict_types=1);

namespace App\Validators;

use App\Exceptions\ValidationException;
use DateTimeImmutable;

/**
 * Base for every form validator (Template Method pattern):
 *
 *   validate($input)  →  reset errors  →  rules($input) [subclass]  →  throw if errors  →  return clean data
 *
 * - Checks FORMAT only. Anything that needs the database belongs in a Service.
 * - One message per field: once a field has an error, later checks on it are skipped.
 * - Checks other than required() accept '' so they can be used on optional fields.
 * - Input is a plain array (Request::allInput()), so validators know nothing about HTTP.
 */
abstract class Validator
{
    /** @var array<string, string> Field => user-facing message */
    private array $errors = [];

    /**
     * @param array<string, string|list<string>> $input
     * @return array<string, mixed> Clean values, ready for a Service
     * @throws ValidationException When any rule fails
     */
    final public function validate(array $input): array
    {
        $this->errors = [];

        $clean = $this->rules($input);

        if ($this->errors !== []) {
            throw new ValidationException($this->errors);
        }

        return $clean;
    }

    /**
     * The form's own rules. Read fields with text()/raw(), check them with the helpers,
     * and return the clean values.
     *
     * @param array<string, string|list<string>> $input
     * @return array<string, mixed>
     */
    abstract protected function rules(array $input): array;

    // ------------------------------------------------------------------ reading input

    /**
     * A text field, trimmed. Missing or not a string → ''.
     *
     * @param array<string, string|list<string>> $input
     */
    protected function text(array $input, string $field): string
    {
        $value = $input[$field] ?? '';

        return is_string($value) ? trim($value) : '';
    }

    /**
     * A field exactly as typed (NOT trimmed). Use for passwords.
     *
     * @param array<string, string|list<string>> $input
     */
    protected function raw(array $input, string $field): string
    {
        $value = $input[$field] ?? '';

        return is_string($value) ? $value : '';
    }

    // ------------------------------------------------------------------ checks (true = OK)

    protected function required(string $field, string $value, string $label): bool
    {
        if ($this->hasError($field)) {
            return false;
        }

        if ($value === '') {
            $this->addError($field, "{$label} is required.");
            return false;
        }

        return true;
    }

    protected function maxLength(string $field, string $value, int $max, string $label): bool
    {
        if ($this->hasError($field)) {
            return false;
        }

        if (mb_strlen($value) > $max) {
            $this->addError($field, "{$label} must be at most {$max} characters.");
            return false;
        }

        return true;
    }

    protected function email(string $field, string $value, string $label): bool
    {
        if ($this->hasError($field) || $value === '') {
            return !$this->hasError($field);
        }

        if (filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            $this->addError($field, "{$label} must be a valid email address.");
            return false;
        }

        return true;
    }

    /**
     * A calendar date in YYYY-MM-DD format that really exists (2026-02-30 is rejected).
     */
    protected function date(string $field, string $value, string $label): bool
    {
        if ($this->hasError($field) || $value === '') {
            return !$this->hasError($field);
        }

        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        if ($parsed === false || $parsed->format('Y-m-d') !== $value) {
            $this->addError($field, "{$label} must be a valid date (YYYY-MM-DD).");
            return false;
        }

        return true;
    }

    // ------------------------------------------------------------------ errors

    /**
     * Records a message for a field. The first message for a field wins.
     */
    protected function addError(string $field, string $message): void
    {
        $this->errors[$field] ??= $message;
    }

    protected function hasError(string $field): bool
    {
        return isset($this->errors[$field]);
    }
}