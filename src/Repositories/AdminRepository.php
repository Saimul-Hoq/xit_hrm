<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Clock;
use App\Core\Database;
use App\Entities\Admin;

/**
 * All SQL for the `admins` table. Prepared statements only.
 *
 * The password hash is never part of the Admin entity:
 * only findCredentialsByEmail() returns it, separately, for AuthService.
 */
final class AdminRepository
{
    /** Every column EXCEPT password. */
    private const COLUMNS = 'id, name, email, phone, created_at, updated_at';

    public function __construct(
        private readonly Database $database,
        private readonly Clock $clock,
    ) {
    }

    /**
     * Used on every admin request to re-check that the logged-in admin still exists.
     */
    public function findById(int $id): ?Admin
    {
        $row = $this->database->fetchOne(
            'SELECT ' . self::COLUMNS . ' FROM admins WHERE id = ?',
            [$id]
        );

        return $row === null ? null : Admin::fromArray($row, $this->clock);
    }

    /**
     * For login only. The email match is case-insensitive (utf8mb4_unicode_ci collation).
     *
     * @return array{admin: Admin, passwordHash: string}|null
     */
    public function findCredentialsByEmail(string $email): ?array
    {
        $row = $this->database->fetchOne(
            'SELECT ' . self::COLUMNS . ', password FROM admins WHERE email = ? LIMIT 1',
            [$email]
        );

        if ($row === null) {
            return null;
        }

        return [
            'admin'        => Admin::fromArray($row, $this->clock),
            'passwordHash' => (string) $row['password'],
        ];
    }

    /**
     * Stores a new password hash (login rehash in Step 14, password change in Step 16).
     * updated_at comes from PHP's Clock, never from SQL NOW().
     */
    public function updatePassword(int $id, string $passwordHash): void
    {
        $this->database->execute(
            'UPDATE admins SET password = ?, updated_at = ? WHERE id = ?',
            [$passwordHash, $this->clock->nowForDb(), $id]
        );
    }
}