<?php

declare(strict_types=1);

namespace App\Models;

class DeliveryProposal
{
    public const STATUS_PENDING = 'PENDING';
    public const STATUS_CONFIRMED = 'CONFIRMED';
    public const STATUS_REJECTED = 'REJECTED';

    public function __construct(
        public readonly int $id,
        public readonly int $alertId,
        public readonly int $stockId,
        public readonly int $quantity,
        public readonly string $status,
        public readonly int $proposedBy,
        public readonly ?int $confirmedBy = null,
        public readonly ?int $siteId = null,
        public readonly ?int $productId = null,
        public readonly ?string $siteName = null,
        public readonly ?string $productName = null,
        public readonly ?string $proposedByName = null,
        public readonly ?int $managerConfirmedBy = null,
        public readonly ?string $managerConfirmedByName = null,
        public readonly ?int $responsableConfirmedBy = null,
        public readonly ?string $responsableConfirmedByName = null,
        public readonly ?string $createdAt = null,
        public readonly ?string $decidedAt = null,
    ) {
    }

    public static function fromRow(array $row): self
    {
        return new self(
            id: (int)$row['id'],
            alertId: (int)$row['alert_id'],
            stockId: (int)$row['stock_id'],
            quantity: (int)$row['quantity'],
            status: (string)$row['status'],
            proposedBy: (int)$row['proposed_by'],
            confirmedBy: isset($row['confirmed_by']) && $row['confirmed_by'] !== null
                ? (int)$row['confirmed_by']
                : null,
            siteId: isset($row['site_id']) && $row['site_id'] !== null ? (int)$row['site_id'] : null,
            productId: isset($row['product_id']) && $row['product_id'] !== null ? (int)$row['product_id'] : null,
            siteName: $row['site_name'] ?? null,
            productName: $row['product_name'] ?? null,
            proposedByName: $row['proposed_by_name'] ?? null,
            managerConfirmedBy: isset($row['manager_confirmed_by']) && $row['manager_confirmed_by'] !== null
                ? (int)$row['manager_confirmed_by']
                : null,
            managerConfirmedByName: $row['manager_confirmed_by_name'] ?? null,
            responsableConfirmedBy: isset($row['responsable_confirmed_by']) && $row['responsable_confirmed_by'] !== null
                ? (int)$row['responsable_confirmed_by']
                : null,
            responsableConfirmedByName: $row['responsable_confirmed_by_name'] ?? null,
            createdAt: $row['created_at'] ?? null,
            decidedAt: $row['decided_at'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'alertId' => $this->alertId,
            'stockId' => $this->stockId,
            'siteId' => $this->siteId,
            'siteName' => $this->siteName,
            'productId' => $this->productId,
            'productName' => $this->productName,
            'quantity' => $this->quantity,
            'status' => $this->status,
            'proposedBy' => $this->proposedBy,
            'proposedByName' => $this->proposedByName,
            'confirmedBy' => $this->confirmedBy,
            'managerConfirmedBy' => $this->managerConfirmedBy,
            'managerConfirmedByName' => $this->managerConfirmedByName,
            'responsableConfirmedBy' => $this->responsableConfirmedBy,
            'responsableConfirmedByName' => $this->responsableConfirmedByName,
            'createdAt' => $this->createdAt,
            'decidedAt' => $this->decidedAt,
        ];
    }
}
