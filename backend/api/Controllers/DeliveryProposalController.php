<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Config\Database;
use App\Models\Alert;
use App\Models\DeliveryProposal;
use App\Repositories\AlertRepository;
use App\Repositories\DeliveryProposalRepository;
use App\Services\AlertService;
use App\Services\StockService;
use App\Utils\Response;

class DeliveryProposalController
{
    public function __construct(
        private readonly DeliveryProposalRepository $proposals = new DeliveryProposalRepository(),
        private readonly AlertRepository $alerts = new AlertRepository(),
        private readonly AlertService $alertService = new AlertService(),
        private readonly StockService $stockService = new StockService()
    ) {
    }

    public function index(array $params, array $data, array $user = []): void
    {
        $filters = [
            'status' => $data['status'] ?? DeliveryProposal::STATUS_PENDING,
        ];

        if (($user['role'] ?? '') === 'STOCK_MANAGER' || ($user['role'] ?? '') === 'MANAGER') {
            $siteId = isset($user['siteId']) ? (int)$user['siteId'] : 0;
            if ($siteId <= 0) {
                Response::success([]);
                return;
            }
            $filters['siteId'] = $siteId;
        } elseif (!empty($data['siteId'])) {
            $filters['siteId'] = (int)$data['siteId'];
        }

        $list = $this->proposals->findAll($filters);
        Response::success(array_map(static fn($p) => $p->toArray(), $list));
    }

    public function confirm(array $params, array $data, array $user = []): void
    {
        $this->decide($params, $user, DeliveryProposal::STATUS_CONFIRMED);
    }

    public function reject(array $params, array $data, array $user = []): void
    {
        $this->decide($params, $user, DeliveryProposal::STATUS_REJECTED);
    }

    private function decide(array $params, array $user, string $status): void
    {
        $role = (string)($user['role'] ?? '');
        if ($role !== 'MANAGER' && $role !== 'STOCK_MANAGER') {
            Response::forbidden('Seul le manager du site ou le responsable peut confirmer ou refuser');
        }

        $siteId = isset($user['siteId']) ? (int)$user['siteId'] : 0;
        if ($siteId <= 0) {
            Response::forbidden('Aucun site assigné à ce compte');
        }

        $id = (int)($params['id'] ?? 0);
        $userId = (int)($user['id'] ?? 0);

        $this->proposals->ensureSchema();
        Database::beginTransaction();
        try {
            $proposal = $this->proposals->lockById($id);
            if ($proposal === null) {
                Database::rollBack();
                Response::notFound('Proposition introuvable');
            }
            if ($proposal->status !== DeliveryProposal::STATUS_PENDING) {
                Database::rollBack();
                Response::badRequest('Cette proposition a déjà été traitée');
            }
            if ((int)$proposal->siteId !== $siteId) {
                Database::rollBack();
                Response::forbidden('Cette proposition concerne un autre site');
            }

            $delivery = null;
            $resolved = null;
            $followUp = null;

            if ($status === DeliveryProposal::STATUS_REJECTED) {
                $updated = $this->proposals->markDecided($id, DeliveryProposal::STATUS_REJECTED, $userId);
                if ($updated === null || $updated->status !== DeliveryProposal::STATUS_REJECTED) {
                    Database::rollBack();
                    Response::badRequest('Cette proposition a déjà été traitée');
                }
                Database::commit();
                Response::success([
                    'proposal' => $updated->toArray(),
                    'alert' => null,
                    'followUpAlert' => null,
                    'delivery' => null,
                ]);
            }

            $already = $role === 'MANAGER'
                ? $proposal->managerConfirmedBy
                : $proposal->responsableConfirmedBy;
            if ($already !== null) {
                Database::rollBack();
                Response::badRequest('Vous avez déjà confirmé cette livraison');
            }

            if (!$this->proposals->recordConfirmation($id, $role, $userId)) {
                Database::rollBack();
                Response::badRequest('Vous avez déjà confirmé cette livraison');
            }

            $updated = $this->proposals->findById($id);
            $both = $updated !== null
                && $updated->managerConfirmedBy !== null
                && $updated->responsableConfirmedBy !== null;

            if ($both) {
                $delivery = $this->stockService->deliver($proposal->stockId, $proposal->quantity, $userId);
                $alert = $this->alerts->findById($proposal->alertId);
                if ($alert !== null && $alert->status !== Alert::STATUS_RESOLVED) {
                    $resolved = $this->alertService->resolve($proposal->alertId);
                }
                $stock = $this->stockService->getOne($proposal->stockId);
                if ($stock !== null) {
                    $outcome = $this->alertService->evaluate($stock);
                    $followUp = $outcome['alert'] ?? null;
                }
                $updated = $this->proposals->markDecided($id, DeliveryProposal::STATUS_CONFIRMED, $userId);
                if ($updated === null || $updated->status !== DeliveryProposal::STATUS_CONFIRMED) {
                    Database::rollBack();
                    Response::badRequest('Cette proposition a déjà été traitée');
                }
            }

            Database::commit();

            Response::success([
                'proposal' => $updated?->toArray(),
                'alert' => $resolved?->toArray(),
                'followUpAlert' => $followUp,
                'delivery' => $delivery,
            ]);
        } catch (\Throwable $e) {
            if (Database::inTransaction()) {
                Database::rollBack();
            }
            throw $e;
        }
    }
}
