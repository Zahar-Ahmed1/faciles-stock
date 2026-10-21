<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\ClientStockRepository;
use App\Repositories\ProductRepository;
use App\Repositories\SiteRepository;
use App\Services\StockService;
use App\Utils\Response;
use App\Utils\Validator;

class StockController
{
    public function __construct(
        private readonly StockService $stockService = new StockService(),
        private readonly ClientStockRepository $stocks = new ClientStockRepository(),
        private readonly SiteRepository $sites = new SiteRepository(),
        private readonly ProductRepository $products = new ProductRepository()
    ) {
    }

    public function index(array $params, array $data, array $user = []): void
    {
        $siteId = isset($data['siteId']) ? (int)$data['siteId'] : null;
        if (($user['role'] ?? '') === 'MANAGER') {
            $siteId = isset($user['siteId']) ? (int)$user['siteId'] : 0;
            if ($siteId <= 0) {
                Response::success([]);
                return;
            }
        }
        $list = $this->stocks->findAll($siteId);
        Response::success(array_map(static fn($s) => $s->toArray(), $list));
    }

    public function bySite(array $params, array $data, array $user = []): void
    {
        $siteId = (int)($params['siteId'] ?? 0);
        if ($this->sites->findById($siteId) === null) {
            Response::notFound('Site not found');
        }
        $list = $this->stockService->listBySite($siteId);
        Response::success(array_map(static fn($s) => $s->toArray(), $list));
    }

    public function show(array $params, array $data, array $user = []): void
    {
        $id = (int)($params['id'] ?? 0);
        $stock = $this->stockService->getOne($id);
        if ($stock === null) {
            Response::notFound('Stock not found');
        }
        Response::success($stock->toArray());
    }

    public function store(array $params, array $data, array $user = []): void
    {
        if (($user['role'] ?? '') === 'STOCK_MANAGER') {
            $userSiteId = isset($user['siteId']) ? (int)$user['siteId'] : 0;
            if ($userSiteId <= 0) {
                Response::forbidden('Aucun site assigné à ce responsable');
            }
            $data['siteId'] = $userSiteId;
        }

        $validator = Validator::make($data, [
            'siteId' => 'required|int',
            'productId' => 'required|int',
            'quantity' => 'nullable|int|min:0',
            'minimumQuantity' => 'nullable|int|min:0',
            'maximumQuantity' => 'nullable|int|min:0',
        ]);
        if ($validator->fails()) {
            Response::validationError($validator->errors());
        }
        $valid = $validator->validated();
        if ($this->sites->findById((int)$valid['siteId']) === null) {
            Response::notFound('Referenced site not found');
        }
        if ($this->products->findById((int)$valid['productId']) === null) {
            Response::notFound('Referenced product not found');
        }
        $stock = $this->stockService->createStock($valid + ['userId' => $user['id'] ?? 0]);
        Response::created($stock->toArray());
    }

    public function update(array $params, array $data, array $user = []): void
    {
        $id = (int)($params['id'] ?? 0);
        $existing = $this->stockService->getOne($id);
        if ($existing === null) {
            Response::notFound('Stock not found');
        }
        $validator = Validator::make($data, [
            'quantity' => 'nullable|int|min:0',
            'minimumQuantity' => 'nullable|int|min:0',
            'maximumQuantity' => 'nullable|int|min:0',
        ]);
        if ($validator->fails()) {
            Response::validationError($validator->errors());
        }
        $stock = $this->stockService->updateStock($id, $validator->validated() + ['userId' => $user['id'] ?? 0]);
        Response::success($stock?->toArray());
    }

    public function decrementOne(array $params, array $data, array $user = []): void
    {
        $id = (int)($params['id'] ?? 0);
        $this->assertStockAccess($id, $user);
        $result = $this->stockService->decrementOne($id, (int)($user['id'] ?? 0));
        Response::success($result);
    }

    public function incrementOne(array $params, array $data, array $user = []): void
    {
        $id = (int)($params['id'] ?? 0);
        $this->assertStockAccess($id, $user);
        $result = $this->stockService->incrementOne($id, (int)($user['id'] ?? 0));
        Response::success($result);
    }

    public function decrementBy(array $params, array $data, array $user = []): void
    {
        $id = (int)($params['id'] ?? 0);
        $qty = (int)($params['quantity'] ?? 1);
        $this->assertStockAccess($id, $user);
        $result = $this->stockService->decrementBy($id, $qty, (int)($user['id'] ?? 0));
        Response::success($result);
    }

    public function incrementBy(array $params, array $data, array $user = []): void
    {
        $id = (int)($params['id'] ?? 0);
        $qty = (int)($params['quantity'] ?? 1);
        $result = $this->stockService->incrementBy($id, $qty, (int)($user['id'] ?? 0));
        Response::success($result);
    }

    private function assertStockAccess(int $stockId, array $user): void
    {
        if (($user['role'] ?? '') !== 'STOCK_MANAGER') {
            return;
        }
        $userSiteId = isset($user['siteId']) ? (int)$user['siteId'] : 0;
        $stock = $this->stockService->getOne($stockId);
        if ($stock === null) {
            Response::notFound('Stock not found');
        }
        if ($userSiteId <= 0 || $stock->siteId !== $userSiteId) {
            Response::forbidden('You can only access your assigned site');
        }
    }
}
