<?php

declare(strict_types=1);

namespace App\Traits;

/**
 * Adds logging methods to any class.
 *
 * Writes through PHP's error_log(). bootstrap.php (Step 5) points error_log
 * at storage/logs/app-YYYY-MM-DD.log, falling back to the server log.
 *
 * NEVER put passwords, password hashes, CSRF tokens or session IDs in $context.
 */
trait Loggable
{
    /**
     * @param array<string, mixed> $context
     */
    protected function logError(string $message, array $context = []): void
    {
        $this->writeLog('ERROR', $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    protected function logWarning(string $message, array $context = []): void
    {
        $this->writeLog('WARNING', $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    protected function logInfo(string $message, array $context = []): void
    {
        $this->writeLog('INFO', $message, $context);
    }

    /**
     * Line format: [LEVEL] Fully\Qualified\ClassName: message {"json":"context"}
     *
     * @param array<string, mixed> $context
     */
    private function writeLog(string $level, string $message, array $context): void
    {
        // Collapse line breaks so one call can never produce a forged extra log line.
        $safeMessage = str_replace(["\r\n", "\r", "\n"], ' ', $message);

        $line = sprintf('[%s] %s: %s', $level, static::class, $safeMessage);

        if ($context !== []) {
            $json = json_encode(
                $context,
                JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
                | JSON_INVALID_UTF8_SUBSTITUTE
                | JSON_PARTIAL_OUTPUT_ON_ERROR
            );

            $line .= ' ' . ($json === false ? '{"context":"could not be encoded"}' : $json);
        }

        error_log($line);
    }
}