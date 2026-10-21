<?php

declare(strict_types=1);

namespace App\Services;

use App\Config\Database;
use App\Models\ClientStock;
use App\Models\StockMovement;
use App\Repositories\ClientStockRepository;
use App\Repositories\StockMovementRepository;
use App\Utils\Response;
use PDO;

class StockService
{
    public function __construct(
        private readonly ClientStockRepository $stocks = new ClientStockRepository(),
        private readonly StockMovementRepository $movements = new StockMovementRepository(),
        private readonly AlertService $alertService = new AlertService()
    ) {
    }

    public function decrementOne(int $stockId, int $userId): array
    {
        return $this->apply($stockId, $userId, -1);
    }

    public function incrementOne(int $stockId, int $userId): array
    {
        return $this->apply($stockId, $userId, 1);
    }

    public function decrementBy(int $stockId, int $quantity, int $userId): array
    {
        if ($quantity <= 0) {
            Response::badRequest('Quantity must be greater than 0');
        }
        return $this->apply($stockId, $userId, -$quantity);
    }

    public function incrementBy(int $stockId, int $quantity, int $userId, ?string $reason = null): array
    {
        if ($quantity <= 0) {
            Response::badRequest('Quantity must be greater than 0');
        }
        return $this->apply($stockId, $userId, $quantity, $reason);
    }

    public function deliver(int $stockId, int $quantity, int $userId): array
    {
        return $this->incrementBy(
            $stockId,
            $quantity,
            $userId,
            sprintf('Livraison Facilyx confirmée par le responsable (%d unités)', $quantity)
        );
    }

    private function apply(int $stockId, int $userId, int $delta, ?string $reason = null): array
    {
        $ownsTransaction = !Database::inTransaction();
        if ($ownsTransaction) {
            Database::beginTransaction();
        }

        try {
            $stock = $this->stocks->findById($stockId, true);
            if ($stock === null) {
                Database::rollBack();
                Response::notFound('Stock not found');
            }

            $oldQty = $stock->quantity;
            $newQty = $oldQty + $delta;

            if ($newQty < 0) {
                Database::rollBack();
                Response::error(
                    'OUT_OF_STOCK',
                    $oldQty <= 0 ? 'Le stock est épuisé.' : 'Stock insuffisant pour cette opération.',
                    409,
                    ['requested' => abs($delta), 'available' => $oldQty]
                );
            }

            if ($newQty !== $oldQty) {
                $this->stocks->setQuantity($stock->id, $newQty);
            }

            $movementType = $delta > 0 ? StockMovement::TYPE_ENTRY : StockMovement::TYPE_EXIT;
            if ($reason !== null && str_starts_with($reason, 'Livraison Facilyx')) {
                $movementType = StockMovement::TYPE_ENTRY;
            } elseif (abs($delta) > 1) {
                $movementType = StockMovement::TYPE_ADJUSTMENT;
            }
            $movement = $this->movements->create(
                stockId: $stock->id,
                type: $movementType,
                quantity: abs($delta),
                stockBefore: $oldQty,
                stockAfter: $newQty,
                userId: $userId,
                reason: $reason ?? $this->reasonFor($delta)
            );

            $refreshed = $this->stocks->findById($stock->id, false);
            if ($refreshed === null) {
                Database::rollBack();
                Response::serverError('Could not refresh stock after update');
            }

            $alertOutcome = $this->alertService->evaluate($refreshed);

            if ($ownsTransaction) {
                Database::commit();
            }

            $status = $refreshed->status();
            return [
                'stockId' => $refreshed->id,
                'siteId' => $refreshed->siteId,
                'productId' => $refreshed->productId,
                'productName' => $refreshed->productName,
                'productUnit' => $refreshed->productUnit,
                'oldQuantity' => $oldQty,
                'quantity' => $newQty,
                'minimumQuantity' => $refreshed->minimumQuantity,
                'maximumQuantity' => $refreshed->maximumQuantity,
                'status' => $status,
                'movementId' => $movement->id,
                'alertCreated' => $alertOutcome['created'],
                'alert' => $alertOutcome['alert'] ?? null,
            ];
        } catch (\Throwable $e) {
            if (Database::inTransaction()) {
                Database::rollBack();
            }
            throw $e;
        }
    }

    private function reasonFor(int $delta): string
    {
        if ($delta === -1) {
            return 'Retrait unitaire (bouton -)';
        }
        if ($delta === 1) {
            return 'Ajout unitaire (bouton +)';
        }
        if ($delta < 0) {
            return sprintf('Retrait de %d unités', abs($delta));
        }
        return sprintf('Ajout de %d unités', $delta);
    }

    public function getOne(int $id): ?ClientStock
    {
        return $this->stocks->findById($id);
    }

    public function listBySite(int $siteId): array
    {
        return $this->stocks->findBySite($siteId);
    }

    public function createStock(array $data): ClientStock
    {
        $existing = $this->stocks->findBySiteAndProduct((int)$data['siteId'], (int)$data['productId']);
        if ($existing !== null) {
            Response::conflict('Stock already exists for this site and product');
        }
        $stock = $this->stocks->create($data);
        $this->alertService->evaluate($stock);
        return $stock;
    }

    public function updateStock(int $id, array $data): ?ClientStock
    {
        $stock = $this->stocks->findById($id);
        if ($stock === null) {
            return null;
        }
        Database::beginTransaction();
        try {
            $min = (int)($data['minimumQuantity'] ?? $stock->minimumQuantity);
            $max = (int)($data['maximumQuantity'] ?? $stock->maximumQuantity);
            if ($max < $min) {
                Database::rollBack();
                Response::badRequest('Maximum quantity must be greater or equal to minimum quantity');
            }
            if (isset($data['quantity']) && (int)$data['quantity'] !== $stock->quantity) {
                $newQty = (int)$data['quantity'];
                if ($newQty < 0) {
                    Database::rollBack();
                    Response::badRequest('Quantity must not be negative');
                }
                $this->movements->create(
                    stockId: $id,
                    type: StockMovement::TYPE_ADJUSTMENT,
                    quantity: abs($newQty - $stock->quantity),
                    stockBefore: $stock->quantity,
                    stockAfter: $newQty,
                    userId: (int)($data['userId'] ?? 0),
                    reason: 'Ajustement manuel du stock'
                );
                $this->stocks->setQuantity($id, $newQty);
            }
            $updated = $this->stocks->updateThresholds($id, $min, $max);
            if ($updated !== null) {
                $this->alertService->evaluate($updated);
            }
            Database::commit();
            return $updated;
        } catch (\Throwable $e) {
            if (Database::inTransaction()) {
                Database::rollBack();
            }
            throw $e;
        }
    }
}
