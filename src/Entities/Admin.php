<?php

declare(strict_types=1);

namespace App\Entities;

use App\Core\Clock;
use DateTimeImmutable;

/**
 * One row of the `admins` table, as a typed, read-only object.
 *
 * Deliberately has NO password hash: entities may be passed to views,
 * and a hash must never be able to reach a template. AdminRepository
 * hands the hash to AuthService separately.
 */
final class Admin
{
    private function __construct(
        private readonly int $id,
        private readonly string $name,
        private readonly string $email,
        private readonly ?string $phone,
        private readonly DateTimeImmutable $createdAt,
        private readonly DateTimeImmutable $updatedAt,
    ) {
    }

    /**
     * Builds an Admin from a database row. Any `password` column in the row is ignored.
     *
     * @param array<string, mixed> $row
     */
    public static function fromArray(array $row, Clock $clock): self
    {
        return new self(
            id:        (int) $row['id'],
            name:      (string) $row['name'],
            email:     (string) $row['email'],
            phone:     $row['phone'] === null ? null : (string) $row['phone'],
            createdAt: $clock->fromDbDateTime((string) $row['created_at']),
            updatedAt: $clock->fromDbDateTime((string) $row['updated_at']),
        );
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }
}