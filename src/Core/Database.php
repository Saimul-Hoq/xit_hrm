<?php

declare(strict_types=1);

namespace App\Core;

use App\Exceptions\DatabaseException;
use App\Traits\Loggable;
use mysqli;
use mysqli_sql_exception;
use mysqli_stmt;

/**
 * Owns the single mysqli connection for the request, and runs prepared statements.
 *
 * - Lazy: nothing connects until the first query (or connection()) needs it.
 * - TCP only: "localhost" is rejected because mysqli would use a Unix socket.
 * - Every connection: utf8mb4, strict SQL mode, time_zone = Clock's numeric offset.
 * - Query helpers: every value travels as a bound parameter, never inside the SQL text.
 *   Failures become DatabaseException whose message contains the SQL but NEVER the values.
 *
 * Repositories receive this object and use fetchOne() / fetchAll() / execute().
 */
final class Database
{
    use Loggable;

    private const CONNECT_TIMEOUT_SECONDS = 5;

    private const SQL_MODE = 'ONLY_FULL_GROUP_BY,STRICT_ALL_TABLES,NO_ZERO_IN_DATE,'
        . 'NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';

    private readonly string $password;

    private ?mysqli $connection = null;

    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly string $name,
        private readonly string $user,
        #[\SensitiveParameter] string $password,
        private readonly string $charset,
        private readonly Clock $clock,
    ) {
        if (strtolower($host) === 'localhost') {
            throw new DatabaseException(
                'DB_HOST "localhost" makes mysqli use a Unix socket. '
                . 'This application is TCP only: use 127.0.0.1 or the server\'s IP/hostname.'
            );
        }

        $this->password = $password;
    }

    /**
     * Returns the open connection, connecting on first use.
     */
    public function connection(): mysqli
    {
        if ($this->connection === null) {
            $this->connection = $this->connect();
        }

        return $this->connection;
    }

    public function isConnected(): bool
    {
        return $this->connection !== null;
    }

    /**
     * First row of a SELECT as column => value, or null when there is no row.
     *
     * @param list<int|float|string|bool|null> $params Values for the ? placeholders, in order
     * @return array<string, mixed>|null
     */
    public function fetchOne(string $sql, array $params = []): ?array
    {
        try {
            $statement = $this->prepareAndExecute($sql, $params);
            $result    = $statement->get_result();
            $row       = $result === false ? null : $result->fetch_assoc();
            $statement->close();
        } catch (mysqli_sql_exception $exception) {
            throw $this->queryFailed($sql, $exception);
        }

        return is_array($row) ? $row : null;
    }

    /**
     * All rows of a SELECT, each as column => value.
     *
     * @param list<int|float|string|bool|null> $params
     * @return list<array<string, mixed>>
     */
    public function fetchAll(string $sql, array $params = []): array
    {
        try {
            $statement = $this->prepareAndExecute($sql, $params);
            $result    = $statement->get_result();
            $rows      = $result === false ? [] : $result->fetch_all(MYSQLI_ASSOC);
            $statement->close();
        } catch (mysqli_sql_exception $exception) {
            throw $this->queryFailed($sql, $exception);
        }

        return $rows;
    }

    /**
     * INSERT / UPDATE / DELETE. Returns the number of affected rows.
     *
     * @param list<int|float|string|bool|null> $params
     */
    public function execute(string $sql, array $params = []): int
    {
        try {
            $statement = $this->prepareAndExecute($sql, $params);
            $affected  = (int) $statement->affected_rows;
            $statement->close();
        } catch (mysqli_sql_exception $exception) {
            throw $this->queryFailed($sql, $exception);
        }

        return $affected;
    }

    /**
     * The AUTO_INCREMENT id created by the last INSERT on this connection.
     */
    public function lastInsertId(): int
    {
        return (int) $this->connection()->insert_id;
    }

    /**
     * What var_dump()/print_r() show. The password is deliberately absent.
     *
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return [
            'host'      => $this->host,
            'port'      => $this->port,
            'name'      => $this->name,
            'user'      => $this->user,
            'charset'   => $this->charset,
            'connected' => $this->isConnected(),
        ];
    }

    /**
     * Prepares, binds (types chosen from the PHP values) and executes.
     *
     * @param list<int|float|string|bool|null> $params
     */
    private function prepareAndExecute(string $sql, array $params): mysqli_stmt
    {
        $statement = $this->connection()->prepare($sql);

        if ($params !== []) {
            $types  = '';
            $values = [];

            foreach ($params as $value) {
                $types .= match (true) {
                    is_int($value), is_bool($value) => 'i',
                    is_float($value)                => 'd',
                    default                         => 's', // strings and NULL
                };

                $values[] = is_bool($value) ? (int) $value : $value;
            }

            $statement->bind_param($types, ...$values);
        }

        $statement->execute();

        return $statement;
    }

    /**
     * The SQL text is safe to log (placeholders only); the parameter values are not.
     */
    private function queryFailed(string $sql, mysqli_sql_exception $exception): DatabaseException
    {
        $compactSql = (string) preg_replace('/\s+/', ' ', trim($sql));

        return new DatabaseException('Query failed: ' . $compactSql, 0, $exception);
    }

    private function connect(): mysqli
    {
        // Every mysqli failure becomes an exception instead of a silent false.
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

        $offset = $this->clock->utcOffset();

        // The offset is interpolated into SQL below, so verify its exact shape first.
        if (preg_match('/^[+-]\d{2}:\d{2}$/', $offset) !== 1) {
            throw new DatabaseException("Unexpected UTC offset '{$offset}' from Clock.");
        }

        try {
            $mysqli = mysqli_init();

            if ($mysqli === false) {
                throw new DatabaseException('mysqli_init() failed.');
            }

            $mysqli->options(MYSQLI_OPT_CONNECT_TIMEOUT, self::CONNECT_TIMEOUT_SECONDS);
            $mysqli->real_connect($this->host, $this->user, $this->password, $this->name, $this->port);
            $mysqli->set_charset($this->charset);
            $mysqli->query(sprintf(
                "SET SESSION sql_mode = '%s', time_zone = '%s'",
                self::SQL_MODE,
                $offset
            ));
        } catch (mysqli_sql_exception $e) {
            $this->logError('Database connection failed.', [
                'host'        => $this->host,
                'port'        => $this->port,
                'database'    => $this->name,
                'user'        => $this->user,
                'mysql_code'  => $e->getCode(),
                'mysql_error' => $e->getMessage(),
            ]);

            throw new DatabaseException('Database connection failed.', 0, $e);
        }

        return $mysqli;
    }
}