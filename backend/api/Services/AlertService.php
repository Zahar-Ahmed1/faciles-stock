<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Alert;
use App\Models\ClientStock;
use App\Repositories\AlertRepository;
use App\Repositories\ClientStockRepository;

class AlertService
{
    public function __construct(
        private readonly AlertRepository $alerts = new AlertRepository(),
        private readonly ClientStockRepository $stocks = new ClientStockRepository()
    ) {
    }

    /**
     * Recrée les alertes manquantes pour les stocks bas / en rupture.
     * Corrige le cas où une alerte a été résolue sans livraison.
     */
    public function syncOpenAlerts(?int $siteId = null): int
    {
        $created = 0;
        foreach ($this->stocks->findAll($siteId) as $stock) {
            if ($stock->quantity > $stock->minimumQuantity) {
                continue;
            }
            $outcome = $this->evaluate($stock);
            if (!empty($outcome['created'])) {
                $created++;
            }
        }
        return $created;
    }

    public function evaluate(ClientStock $stock): array
    {
        $created = null;
        $resolved = null;
        $qty = $stock->quantity;
        $min = $stock->minimumQuantity;

        if ($qty <= 0) {
            $created = $this->ensureAlert($stock, Alert::TYPE_OUT_OF_STOCK, $qty, 0);
            $this->alerts->autoResolveByType($stock->id, Alert::TYPE_LOW_STOCK);
            $resolved = Alert::TYPE_LOW_STOCK;
        } elseif ($qty <= $min) {
            $created = $this->ensureAlert($stock, Alert::TYPE_LOW_STOCK, $qty, $min);
            $this->alerts->autoResolveByType($stock->id, Alert::TYPE_OUT_OF_STOCK);
            $resolved = Alert::TYPE_OUT_OF_STOCK;
        } else {
            $this->alerts->autoResolveByType($stock->id, Alert::TYPE_LOW_STOCK);
            $this->alerts->autoResolveByType($stock->id, Alert::TYPE_OUT_OF_STOCK);
            $resolved = 'BOTH';
        }

        return [
            'created' => $created !== null,
            'resolvedType' => $resolved,
            'alert' => $created?->toArray(),
        ];
    }

    private function ensureAlert(ClientStock $stock, string $type, int $qty, int $threshold): ?Alert
    {
        $existing = $this->alerts->findActiveByStockAndType($stock->id, $type);
        if ($existing !== null) {
            return null;
        }
        $message = match ($type) {
            Alert::TYPE_OUT_OF_STOCK => sprintf(
                'Rupture de stock : %s sur %s',
                $stock->productName ?? ('Produit #' . $stock->productId),
                $stock->siteName ?? ('Site #' . $stock->siteId),
            ),
            Alert::TYPE_LOW_STOCK => sprintf(
                'Stock faible : %s (%d unités, seuil %d) sur %s',
                $stock->productName ?? ('Produit #' . $stock->productId),
                $qty,
                $threshold,
                $stock->siteName ?? ('Site #' . $stock->siteId),
            ),
            default => sprintf('Alerte %s sur stock #%d', $type, $stock->id),
        };
        return $this->alerts->create($stock->id, $type, $message, $qty, $threshold);
    }

    public function markRead(int $id): ?Alert
    {
        return $this->alerts->markRead($id);
    }

    public function resolve(int $id): ?Alert
    {
        return $this->alerts->resolve($id);
    }
}
