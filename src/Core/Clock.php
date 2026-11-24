<?php

declare(strict_types=1);

namespace App\Core;

use DateTimeImmutable;
use DateTimeZone;
use Exception;
use RuntimeException;

/**
 * The application's single source of "now".
 *
 * - Always works in the configured time zone (Asia/Dhaka), never PHP's global default.
 * - Returns DateTimeImmutable with whole-second precision (matches MySQL DATETIME).
 * - Repositories write timestamps from here; SQL NOW() is never used.
 */
final class Clock
{
    /** Format of MySQL DATETIME columns. */
    public const DB_DATETIME = 'Y-m-d H:i:s';

    /** Format of MySQL DATE columns. */
    public const DB_DATE = 'Y-m-d';

    private readonly DateTimeZone $timezone;

    public function __construct(string $timezone)
    {
        try {
            $this->timezone = new DateTimeZone($timezone);
        } catch (Exception) {
            throw new RuntimeException(
                "Invalid timezone '{$timezone}'. Use an IANA name such as Asia/Dhaka."
            );
        }
    }

    /**
     * Current moment in the app time zone, microseconds removed.
     */
    public function now(): DateTimeImmutable
    {
        $now = DateTimeImmutable::createFromFormat('U', (string) time());

        if ($now === false) {
            throw new RuntimeException('Could not read the system time.');
        }

        return $now->setTimezone($this->timezone);
    }

    /**
     * Today at 00:00:00 in the app time zone.
     */
    public function today(): DateTimeImmutable
    {
        return $this->now()->setTime(0, 0);
    }

    /**
     * Current moment as a MySQL DATETIME string, e.g. "2026-11-24 09:05:00".
     */
    public function nowForDb(): string
    {
        return $this->now()->format(self::DB_DATETIME);
    }

    /**
     * Today's date as a MySQL DATE string, e.g. "2026-11-24".
     */
    public function todayForDb(): string
    {
        return $this->now()->format(self::DB_DATE);
    }

    /**
     * Parses a MySQL DATETIME string (stored in app time) into an object. Strict.
     */
    public function fromDbDateTime(string $value): DateTimeImmutable
    {
        return $this->parseStrict($value, self::DB_DATETIME);
    }

    /**
     * Parses a MySQL DATE string into an object at 00:00:00. Strict.
     */
    public function fromDbDate(string $value): DateTimeImmutable
    {
        return $this->parseStrict($value, self::DB_DATE);
    }

    public function timezone(): DateTimeZone
    {
        return $this->timezone;
    }

    /**
     * Numeric UTC offset for the current moment, e.g. "+06:00".
     * Used to set the MySQL connection time_zone (works without MySQL tz tables).
     */
    public function utcOffset(): string
    {
        return $this->now()->format('P');
    }

    /**
     * "!" resets unspecified fields to zero (so a DATE becomes 00:00:00).
     * Formatting the result back and comparing rejects rollovers like 2026-02-30.
     */
    private function parseStrict(string $value, string $format): DateTimeImmutable
    {
        $parsed = DateTimeImmutable::createFromFormat('!' . $format, $value, $this->timezone);

        if ($parsed === false || $parsed->format($format) !== $value) {
            throw new RuntimeException(
                "Invalid date/time value '{$value}', expected format {$format}."
            );
        }

        return $parsed;
    }
}