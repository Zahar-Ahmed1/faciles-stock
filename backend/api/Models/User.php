<?php

declare(strict_types=1);

namespace App\Models;

class User
{
    public function __construct(
        public readonly int $id,
        public readonly string $firstName,
        public readonly string $lastName,
        public readonly string $email,
        public readonly string $role,
        public readonly bool $active,
        public readonly ?int $siteId,
        public readonly ?string $createdAt = null,
        public readonly ?string $updatedAt = null,
    ) {
    }

    public static function fromRow(array $row): self
    {
        return new self(
            id: (int)$row['id'],
            firstName: (string)$row['first_name'],
            lastName: (string)$row['last_name'],
            email: (string)$row['email'],
            role: (string)$row['role'],
            active: (bool)($row['active'] ?? true),
            siteId: isset($row['site_id']) && $row['site_id'] !== null ? (int)$row['site_id'] : null,
            createdAt: $row['created_at'] ?? null,
            updatedAt: $row['updated_at'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'firstName' => $this->firstName,
            'lastName' => $this->lastName,
            'email' => $this->email,
            'role' => $this->role,
            'active' => $this->active,
            'siteId' => $this->siteId,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
        ];
    }
}
