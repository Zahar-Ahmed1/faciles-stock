<?php

declare(strict_types=1);

namespace App\Models;

class ClientStock
{
    public const STATUS_NORMAL = 'NORMAL';
    public const STATUS_LOW_STOCK = 'LOW_STOCK';
    public const STATUS_OUT_OF_STOCK = 'OUT_OF_STOCK';

    public function __construct(
        public readonly int $id,
        public readonly int $siteId,
        public readonly int $productId,
        public readonly int $quantity,
        public readonly int $minimumQuantity,
        public readonly int $maximumQuantity,
        public readonly ?string $siteName = null,
        public readonly ?string $clientName = null,
        public readonly ?int $clientId = null,
        public readonly ?string $productName = null,
        public readonly ?string $productImageUrl = null,
        public readonly ?string $productUnit = null,
        public readonly ?string $productReference = null,
        public readonly ?string $createdAt = null,
        public readonly ?string $updatedAt = null,
    ) {
    }

    public function status(): string
    {
        if ($this->quantity <= 0) {
            return self::STATUS_OUT_OF_STOCK;
        }
        if ($this->quantity <= $this->minimumQuantity) {
            return self::STATUS_LOW_STOCK;
        }
        return self::STATUS_NORMAL;
    }

    public static function fromRow(array $row): self
    {
        return new self(
            id: (int)$row['id'],
            siteId: (int)$row['site_id'],
            productId: (int)$row['product_id'],
            quantity: (int)($row['quantity'] ?? 0),
            minimumQuantity: (int)($row['minimum_quantity'] ?? 0),
            maximumQuantity: (int)($row['maximum_quantity'] ?? 0),
            siteName: $row['site_name'] ?? null,
            clientName: $row['client_name'] ?? null,
            clientId: isset($row['client_id']) && $row['client_id'] !== null ? (int)$row['client_id'] : null,
            productName: $row['product_name'] ?? null,
            productImageUrl: $row['product_image_url'] ?? null,
            productUnit: $row['product_unit'] ?? null,
            productReference: $row['product_reference'] ?? null,
            createdAt: $row['created_at'] ?? null,
            updatedAt: $row['updated_at'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'siteId' => $this->siteId,
            'siteName' => $this->siteName,
            'clientId' => $this->clientId,
            'clientName' => $this->clientName,
            'productId' => $this->productId,
            'productName' => $this->productName,
            'productReference' => $this->productReference,
            'productImageUrl' => $this->productImageUrl,
            'productUnit' => $this->productUnit,
            'quantity' => $this->quantity,
            'minimumQuantity' => $this->minimumQuantity,
            'maximumQuantity' => $this->maximumQuantity,
            'status' => $this->status(),
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
        ];
    }
}
