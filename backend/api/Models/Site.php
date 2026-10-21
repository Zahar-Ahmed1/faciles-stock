<?php

declare(strict_types=1);

namespace App\Models;

class Site
{
    public function __construct(
        public readonly int $id,
        public readonly int $clientId,
        public readonly string $name,
        public readonly ?string $code = null,
        public readonly ?string $address = null,
        public readonly ?string $city = null,
        public readonly ?string $postalCode = null,
        public readonly ?string $country = 'Morocco',
        public readonly bool $active = true,
        public readonly ?string $clientName = null,
        public readonly ?string $createdAt = null,
        public readonly ?string $updatedAt = null,
    ) {
    }

    public static function fromRow(array $row): self
    {
        return new self(
            id: (int)$row['id'],
            clientId: (int)$row['client_id'],
            name: (string)$row['name'],
            code: $row['code'] ?? null,
            address: $row['address'] ?? null,
            city: $row['city'] ?? null,
            postalCode: $row['postal_code'] ?? null,
            country: $row['country'] ?? 'Morocco',
            active: (bool)($row['active'] ?? true),
            clientName: $row['client_name'] ?? null,
            createdAt: $row['created_at'] ?? null,
            updatedAt: $row['updated_at'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'clientId' => $this->clientId,
            'clientName' => $this->clientName,
            'name' => $this->name,
            'code' => $this->code,
            'address' => $this->address,
            'city' => $this->city,
            'postalCode' => $this->postalCode,
            'country' => $this->country,
            'active' => $this->active,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
        ];
    }
}
