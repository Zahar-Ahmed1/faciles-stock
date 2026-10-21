<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Alert;
use App\Repositories\AlertRepository;
use App\Repositories\DeliveryProposalRepository;
use App\Services\AlertService;
use App\Services\StockService;
use App\Utils\Response;
use App\Utils\Validator;

class AlertController
{
    public function __construct(
        private readonly AlertRepository $alerts = new AlertRepository(),
        private readonly AlertService $alertService = new AlertService(),
        private readonly StockService $stockService = new StockService(),
        private readonly DeliveryProposalRepository $proposals = new DeliveryProposalRepository()
    ) {
    }

    public function index(array $params, array $data, array $user = []): void
    {
        $filters = [
            'siteId' => $data['siteId'] ?? null,
            'type' => $data['type'] ?? null,
        ];
        if (($user['role'] ?? '') === 'STOCK_MANAGER' || ($user['role'] ?? '') === 'MANAGER') {
            $ownSite = isset($user['siteId']) ? (int)$user['siteId'] : 0;
            if ($ownSite <= 0) {
                Response::success([]);
                return;
            }
            $filters['siteId'] = $ownSite;
        }

        $syncSiteId = null;
        if (($user['role'] ?? '') === 'STOCK_MANAGER' || ($user['role'] ?? '') === 'MANAGER') {
            $syncSiteId = isset($user['siteId']) ? (int)$user['siteId'] : null;
        } elseif (!empty($filters['siteId'])) {
            $syncSiteId = (int)$filters['siteId'];
        }
        // Resync avant lecture : stocks en rupture/bas sans alerte active.
        $this->alertService->syncOpenAlerts($syncSiteId);

        $filters = array_filter($filters, static fn($v) => $v !== null && $v !== '');
        $list = $this->alerts->findAll($filters);
        Response::success(array_map(static fn($a) => $a->toArray(), $list));
    }

    public function unread(array $params, array $data, array $user = []): void
    {
        $siteId = isset($data['siteId']) ? (int)$data['siteId'] : null;
        if (($user['role'] ?? '') === 'STOCK_MANAGER' || ($user['role'] ?? '') === 'MANAGER') {
            $siteId = isset($user['siteId']) ? (int)$user['siteId'] : null;
        }
        $this->alertService->syncOpenAlerts($siteId);

        if ($siteId !== null) {
            $list = $this->alerts->findBySite($siteId);
            $list = array_values(array_filter($list, static fn($a) => $a->status === 'UNREAD'));
        } else {
            $list = $this->alerts->findUnread();
        }
        Response::success(array_map(static fn($a) => $a->toArray(), $list));
    }

    public function bySite(array $params, array $data, array $user = []): void
    {
        $siteId = (int)($params['siteId'] ?? 0);
        $list = $this->alerts->findBySite($siteId);
        Response::success(array_map(static fn($a) => $a->toArray(), $list));
    }

    public function markRead(array $params, array $data, array $user = []): void
    {
        $id = (int)($params['id'] ?? 0);
        $alert = $this->alerts->findById($id);
        if ($alert === null) {
            Response::notFound('Alert not found');
        }
        $updated = $this->alertService->markRead($id);
        Response::success($updated?->toArray());
    }

    public function resolve(array $params, array $data, array $user = []): void
    {
        $id = (int)($params['id'] ?? 0);
        $alert = $this->alerts->findById($id);
        if ($alert === null) {
            Response::notFound('Alert not found');
        }
        $updated = $this->alertService->resolve($id);

        // Si le stock est toujours bas/vide, recréer une alerte (pas de "ignorer" silencieux).
        $stock = $this->stockService->getOne($alert->stockId);
        $followUp = null;
        if ($stock !== null) {
            $outcome = $this->alertService->evaluate($stock);
            $followUp = $outcome['alert'] ?? null;
        }

        Response::success([
            'alert' => $updated?->toArray(),
            'followUpAlert' => $followUp,
        ]);
    }

    public function deliver(array $params, array $data, array $user = []): void
    {
        $id = (int)($params['id'] ?? 0);
        $alert = $this->alerts->findById($id);
        if ($alert === null) {
            Response::notFound('Alert not found');
        }
        if ($alert->status === Alert::STATUS_RESOLVED) {
            Response::badRequest('Cette alerte est déjà résolue');
        }

        $validator = Validator::make($data, [
            'quantity' => 'required|int|min:1',
        ]);
        if ($validator->fails()) {
            Response::validationError($validator->errors());
        }
        $quantity = (int)$validator->validated()['quantity'];

        $proposal = $this->proposals->savePending(
            $alert->id,
            $alert->stockId,
            $quantity,
            (int)($user['id'] ?? 0)
        );

        Response::success([
            'proposal' => $proposal->toArray(),
            'stockUpdated' => false,
        ]);
    }
}
