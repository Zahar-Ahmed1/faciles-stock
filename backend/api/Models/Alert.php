<?php

declare(strict_types=1);

namespace App\Models;

class Alert
{
    public const TYPE_LOW_STOCK = 'LOW_STOCK';
    public const TYPE_OUT_OF_STOCK = 'OUT_OF_STOCK';
    public const TYPE_OVER_STOCK = 'OVER_STOCK';

    public const STATUS_UNREAD = 'UNREAD';
    public const STATUS_READ = 'READ';
    public const STATUS_RESOLVED = 'RESOLVED';

    public function __construct(
        public readonly int $id,
        public readonly int $stockId,
        public readonly ?int $siteId,
        public readonly ?int $productId,
        public readonly string $type,
        public readonly string $message,
        public readonly string $status,
        public readonly ?string $siteName = null,
        public readonly ?string $productName = null,
        public readonly int $quantityAtAlert = 0,
        public readonly int $threshold = 0,
        public readonly ?string $createdAt = null,
        public readonly ?string $resolvedAt = null,
    ) {
    }

    public static function fromRow(array $row): self
    {
        $status = self::STATUS_UNREAD;
        if (!empty($row['is_resolved'])) {
            $status = self::STATUS_RESOLVED;
        } elseif (!empty($row['is_read'])) {
            $status = self::STATUS_READ;
        }
        $legacy = $row['status'] ?? null;
        if (is_string($legacy) && in_array($legacy, [self::STATUS_UNREAD, self::STATUS_READ, self::STATUS_RESOLVED], true)) {
            $status = $legacy;
        }

        return new self(
            id: (int)$row['id'],
            stockId: (int)($row['stock_id'] ?? 0),
            siteId: isset($row['site_id']) && $row['site_id'] !== null ? (int)$row['site_id'] : null,
            productId: isset($row['product_id']) && $row['product_id'] !== null ? (int)$row['product_id'] : null,
            type: (string)$row['type'],
            message: (string)$row['message'],
            status: $status,
            siteName: $row['site_name'] ?? null,
            productName: $row['product_name'] ?? null,
            quantityAtAlert: (int)($row['quantity_at_alert'] ?? 0),
            threshold: (int)($row['threshold'] ?? 0),
            createdAt: $row['created_at'] ?? null,
            resolvedAt: $row['resolved_at'] ?? null,
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
            'message' => $this->message,
            'status' => $this->status,
            'quantityAtAlert' => $this->quantityAtAlert,
            'threshold' => $this->threshold,
            'createdAt' => $this->createdAt,
            'resolvedAt' => $this->resolvedAt,
        ];
    }
}
