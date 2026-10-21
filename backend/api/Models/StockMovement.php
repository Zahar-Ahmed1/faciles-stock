<?php

declare(strict_types=1);

namespace App\Models;

class StockMovement
{
    public const TYPE_ENTRY = 'ENTRY';
    public const TYPE_EXIT = 'EXIT';
    public const TYPE_ADJUSTMENT = 'ADJUSTMENT';

    public function __construct(
        public readonly int $id,
        public readonly int $stockId,
        public readonly ?int $siteId,
        public readonly ?int $productId,
        public readonly string $type,
        public readonly int $quantity,
        public readonly int $stockBefore,
        public readonly int $stockAfter,
        public readonly ?int $userId,
        public readonly ?string $userFullName = null,
        public readonly ?string $reason = null,
        public readonly ?string $productName = null,
        public readonly ?string $siteName = null,
        public readonly ?string $createdAt = null,
    ) {
    }

    private static function reverseMapType(string $dbType): string
    {
        return match ($dbType) {
            'INCREMENT' => self::TYPE_ENTRY,
            'DECREMENT' => self::TYPE_EXIT,
            self::TYPE_ADJUSTMENT, 'ADJUSTMENT' => self::TYPE_ADJUSTMENT,
            default => $dbType,
        };
    }

    public static function fromRow(array $row): self
    {
        return new self(
            id: (int)$row['id'],
            stockId: (int)($row['stock_id'] ?? $row['id']),
            siteId: isset($row['site_id']) && $row['site_id'] !== null ? (int)$row['site_id'] : null,
            productId: isset($row['product_id']) && $row['product_id'] !== null ? (int)$row['product_id'] : null,
            type: self::reverseMapType((string)$row['type']),
            quantity: (int)$row['quantity'],
            stockBefore: (int)($row['stock_before'] ?? $row['balance_after'] - $row['quantity']),
            stockAfter: (int)($row['stock_after'] ?? $row['balance_after']),
            userId: isset($row['user_id']) && $row['user_id'] !== null ? (int)$row['user_id'] : null,
            userFullName: isset($row['user_first_name'], $row['user_last_name'])
                ? trim($row['user_first_name'] . ' ' . $row['user_last_name'])
                : ($row['user_full_name'] ?? null),
            reason: $row['reason'] ?? $row['note'] ?? null,
            productName: $row['product_name'] ?? null,
            siteName: $row['site_name'] ?? null,
            createdAt: $row['created_at'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'stockId' => $this->stockId,
            'siteId' => $this->siteId,
            'siteName' => $this->siteName,
            'productId' => $this->productId,
            'productName' => $this->productName,
            'type' => $this->type,
            'quantity' => $this->quantity,
            'stockBefore' => $this->stockBefore,
            'stockAfter' => $this->stockAfter,
            'userId' => $this->userId,
            'userFullName' => $this->userFullName,
            'reason' => $this->reason,
            'createdAt' => $this->createdAt,
        ];
    }
}
