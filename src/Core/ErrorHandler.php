<?php

declare(strict_types=1);

namespace App\Core;

use App\Exceptions\AuthorizationException;
use App\Traits\Loggable;
use ErrorException;
use Throwable;

/**
 * Turns every failure into a safe response and a useful log entry.
 *
 * Global part (static, registered in bootstrap.php, no dependencies):
 *   - PHP warnings/notices become ErrorException (deprecations are only logged)
 *   - uncaught exceptions and fatal errors get a plain-text 500 + log line
 *
 * Request part (instance, used by index.php around the Router):
 *   - notFound()          → 404 page
 *   - methodNotAllowed()  → 405 page (the Router has already sent the Allow header)
 *   - handle($exception)  → 403 for AuthorizationException, 500 for everything else
 *     (Step 12b adds ValidationException and BusinessRuleException)
 *
 * Details are shown on the page only in debug mode; they are always logged.
 */
final class ErrorHandler
{
    use Loggable;

    private const FATAL_TYPES = E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR;

    /** Extra memory allowed after an out-of-memory fatal error, so the handler can still log and respond. */
    private const EMERGENCY_MEMORY_BYTES = 16 * 1024 * 1024;

    /**
     * Memory set aside at startup and released when a fatal error happens.
     * After an out-of-memory error, memory is full: without this reserve the
     * shutdown handler would itself die before logging anything.
     */
    private static ?string $reservedMemory = null;

    /** Output buffers opened before this handler existed are left alone. */
    private readonly int $baseBufferLevel;

    public function __construct(
        private readonly View $view,
        private readonly bool $debug,
    ) {
        $this->baseBufferLevel = ob_get_level();
    }

    // ------------------------------------------------------------------ global part

    /**
     * Installs the process-wide handlers. Called once, from bootstrap.php.
     */
    public static function registerGlobal(bool $debug): void
    {
        self::$reservedMemory = str_repeat(' ', 64 * 1024);

        set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
            // Suppressed with @, or not included in error_reporting: let PHP deal with it.
            if ((error_reporting() & $severity) === 0) {
                return false;
            }

            // Deprecations must never break a working request: PHP logs them as usual.
            if (($severity & (E_DEPRECATED | E_USER_DEPRECATED)) !== 0) {
                return false;
            }

            throw new ErrorException($message, 0, $severity, $file, $line);
        });

        set_exception_handler(static function (Throwable $exception) use ($debug): void {
            self::lastResort(self::describe($exception), $exception->getTraceAsString(), $debug);
        });

        register_shutdown_function(static function () use ($debug): void {
            $error = error_get_last();

            if ($error === null || ($error['type'] & self::FATAL_TYPES) === 0) {
                return;
            }

            // Free the reserve and allow a little more memory, so logging and the response can still happen.
            self::$reservedMemory = null;
            @ini_set('memory_limit', (string) (memory_get_usage() + self::EMERGENCY_MEMORY_BYTES));

            self::lastResort("Fatal error: {$error['message']} in {$error['file']}:{$error['line']}", '', $debug);
        });
    }

    // ------------------------------------------------------------------ request part

    public function notFound(): void
    {
        $this->renderErrorPage(404, 'errors/404', 'Page not found');
    }

    public function methodNotAllowed(): void
    {
        $this->renderErrorPage(405, 'errors/405', 'Method not allowed');
    }

    public function handle(Throwable $exception): void
    {
        if ($exception instanceof AuthorizationException) {
            $this->logWarning('Access denied.', ['reason' => $exception->getMessage()]);
            $this->renderErrorPage(403, 'errors/403', 'Access denied');
            return;
        }

        // Step 12b: ValidationException and BusinessRuleException are handled before this point.

        $description = self::describe($exception);
        $this->logError($description, ['trace' => $exception->getTraceAsString()]);

        $detail = $this->debug ? $description . "\n\n" . $exception->getTraceAsString() : null;
        $this->renderErrorPage(500, 'errors/500', 'Something went wrong', $detail);
    }

    // ------------------------------------------------------------------ helpers

    private function renderErrorPage(int $status, string $template, string $title, ?string $detail = null): void
    {
        // Throw away any half-finished page output.
        while (ob_get_level() > $this->baseBufferLevel) {
            ob_end_clean();
        }

        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: text/html; charset=UTF-8');
            header('Cache-Control: no-store');
        }

        try {
            echo $this->view->render($template, ['title' => $title, 'detail' => $detail], 'guest');
        } catch (Throwable $renderFailure) {
            // The error page itself failed (missing template, session problem...): plain fallback.
            $this->logError('Error page could not be rendered: ' . self::describe($renderFailure));
            echo $status . ' ' . $title;
        }
    }

    /**
     * Last line of defence: no View, no session, no templates. Plain text only.
     * Nothing in here may throw, so the warning→exception conversion is switched off first.
     */
    private static function lastResort(string $description, string $trace, bool $debug): void
    {
        restore_error_handler();

        $logLine = '[CRITICAL] ' . self::class . ': ' . str_replace(["\r", "\n"], ' ', $description);

        if ($trace !== '') {
            $logLine .= ' {"trace":' . json_encode($trace, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) . '}';
        }

        error_log($logLine);

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        if (!headers_sent()) {
            // After a fatal error PHP may already have set 500 itself; setting it again would only warn.
            if (http_response_code() !== 500) {
                @http_response_code(500);
            }

            @header('Content-Type: text/plain; charset=UTF-8');
            @header('Cache-Control: no-store');
        }

        echo $debug
            ? "500 Internal Server Error\n\n{$description}\n\n{$trace}\n"
            : "500 Internal Server Error\nSomething went wrong. The problem has been logged.\n";
    }

    /**
     * "Class: message in file:line <- caused by Class: message in file:line ..."
     */
    private static function describe(Throwable $exception): string
    {
        $parts = [];

        for ($current = $exception; $current !== null; $current = $current->getPrevious()) {
            $parts[] = sprintf(
                '%s: %s in %s:%d',
                $current::class,
                $current->getMessage(),
                $current->getFile(),
                $current->getLine()
            );
        }

        return implode(' <- caused by ', $parts);
    }
}