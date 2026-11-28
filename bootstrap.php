<?php

declare(strict_types=1);

/**
 * bootstrap.php: prepares the PHP environment. Every entry point includes it first.
 *
 *   1. PHP version check
 *   2. Required extensions check
 *   3. PSR-4 autoloader (App\ -> src/)
 *   4. Settings: .env -> Env -> config/*.php -> Config
 *   5. Default time zone
 *   6. Error logging to the daily log file (storage/logs/app-YYYY-MM-DD.log).
 *      PHP never DISPLAYS errors itself: ErrorHandler decides what visitors see.
 *   7. Global error handling: warnings become exceptions, uncaught/fatal errors get a safe 500
 *
 * Returns the loaded Config.
 *
 * NOTE: code before the version check must stay parseable by old PHP versions,
 * otherwise the check could never show its message. Avoid PHP 8-only SYNTAX here.
 */

use App\Core\Config;
use App\Core\Env;
use App\Core\ErrorHandler;

return (static function (): Config {
    $projectRoot = __DIR__;

    /**
     * Stops startup. CLI: reason to STDERR. Web: generic 500 page, reason to the server log.
     */
    $fail = static function (string $reason): never {
        if (PHP_SAPI === 'cli') {
            fwrite(STDERR, 'Startup failed: ' . $reason . PHP_EOL);
        } else {
            error_log('[CRITICAL] bootstrap: ' . $reason);

            if (!headers_sent()) {
                http_response_code(500);
                header('Content-Type: text/plain; charset=UTF-8');
            }

            echo 'The application could not start. Details were written to the server log.';
        }

        exit(1);
    };

    // 1. PHP version
    if (PHP_VERSION_ID < 80100) {
        $fail('PHP 8.1 or newer is required; this server runs PHP ' . PHP_VERSION . '.');
    }

    // 2. Required extensions
    $missing = [];

    foreach (['mysqli', 'gd', 'fileinfo', 'mbstring'] as $extension) {
        if (!extension_loaded($extension)) {
            $missing[] = $extension;
        }
    }

    if ($missing !== []) {
        $fail('Missing PHP extensions: ' . implode(', ', $missing) . '.');
    }

    // 3. Autoloader: App\Core\Clock -> src/Core/Clock.php (case-sensitive on Linux)
    spl_autoload_register(static function (string $class) use ($projectRoot): void {
        $prefix = 'App\\';

        if (!str_starts_with($class, $prefix)) {
            return;
        }

        $relativeClass = substr($class, strlen($prefix));
        $file          = $projectRoot . '/src/' . str_replace('\\', '/', $relativeClass) . '.php';

        if (is_file($file)) {
            require $file;
        }
    });

    // 4. Settings
    try {
        $config = Config::load($projectRoot . '/config', Env::load($projectRoot . '/.env'));
    } catch (RuntimeException $e) {
        $fail('Configuration error: ' . $e->getMessage());
    }

    // 5. Default time zone (Clock never relies on this; it keeps log timestamps and date() consistent)
    $timezone = $config->get('app.timezone');

    try {
        new DateTimeZone($timezone);
    } catch (Exception $e) {
        $fail("Invalid APP_TIMEZONE '{$timezone}'.");
    }

    date_default_timezone_set($timezone);

    // 6. Errors: report and log everything, display nothing.
    //    If PHP displayed a fatal error itself, it would send "200 OK" headers before
    //    ErrorHandler could set 500. ErrorHandler shows details itself in debug mode.
    error_reporting(E_ALL);
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    ini_set('log_errors', '1');

    $logDirectory = $projectRoot . '/storage/logs';
    $logFile      = $logDirectory . '/app-' . date('Y-m-d') . '.log';

    $logFileUsable = is_dir($logDirectory)
        && is_writable($logDirectory)
        && (!file_exists($logFile) || is_writable($logFile));

    if ($logFileUsable) {
        ini_set('error_log', $logFile);
    } else {
        // error_log still points at the server's default log here.
        error_log('[WARNING] bootstrap: storage/logs is not writable; using the server log instead.');
    }

    // 7. Global error handling (the per-request part is wired in public/index.php, Step 12c)
    ErrorHandler::registerGlobal($config->get('app.debug'));

    return $config;
})();