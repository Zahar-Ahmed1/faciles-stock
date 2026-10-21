<?php

declare(strict_types=1);

namespace App\Models;

class Product
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly ?string $reference,
        public readonly ?string $imageUrl,
        public readonly string $unit,
        public readonly ?int $categoryId,
        public readonly ?string $categoryName = null,
        public readonly ?string $description = null,
        public readonly bool $active = true,
        public readonly ?string $createdAt = null,
        public readonly ?string $updatedAt = null,
    ) {
    }

    public static function fromRow(array $row): self
    {
        return new self(
            id: (int)$row['id'],
            name: (string)$row['name'],
            reference: $row['reference'] ?? $row['sku'] ?? null,
            imageUrl: $row['image_url'] ?? null,
            unit: (string)($row['unit'] ?? 'unités'),
            categoryId: isset($row['category_id']) && $row['category_id'] !== null ? (int)$row['category_id'] : null,
            categoryName: $row['category_name'] ?? null,
            description: $row['description'] ?? null,
            active: (bool)($row['active'] ?? true),
            createdAt: $row['created_at'] ?? null,
            updatedAt: $row['updated_at'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'reference' => $this->reference,
            'description' => $this->description,
            'unit' => $this->unit,
            'imageUrl' => $this->imageUrl,
            'categoryId' => $this->categoryId,
            'categoryName' => $this->categoryName,
            'active' => $this->active,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
        ];
    }
}
