<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\StockMovementRepository;
use App\Utils\Response;

class MovementController
{
    public function __construct(
        private readonly StockMovementRepository $movements = new StockMovementRepository()
    ) {
    }

    public function index(array $params, array $data, array $user = []): void
    {
        $page = max(1, (int)($data['page'] ?? 1));
        $perPage = min(200, max(1, (int)($data['perPage'] ?? 50)));
        $siteId = $data['siteId'] ?? null;
        if (($user['role'] ?? '') === 'MANAGER') {
            $siteId = isset($user['siteId']) ? (int)$user['siteId'] : 0;
            if ($siteId <= 0) {
                Response::success(['items' => [], 'total' => 0, 'page' => $page, 'perPage' => $perPage]);
                return;
            }
        }
        $filters = [
            'siteId' => $siteId,
            'productId' => $data['productId'] ?? null,
            'type' => $data['type'] ?? null,
            'from' => $data['from'] ?? null,
            'to' => $data['to'] ?? null,
        ];
        $filters = array_filter($filters, static fn($v) => $v !== null && $v !== '');
        $result = $this->movements->findPaginated($filters, $page, $perPage);
        Response::success([
            'items' => array_map(static fn($m) => $m->toArray(), $result['items']),
            'total' => $result['total'],
            'page' => $result['page'],
            'perPage' => $result['perPage'],
        ]);
    }

    public function bySite(array $params, array $data, array $user = []): void
    {
        $siteId = (int)($params['siteId'] ?? 0);
        $list = $this->movements->findBySite($siteId);
        Response::success(array_map(static fn($m) => $m->toArray(), $list));
    }

    public function byProduct(array $params, array $data, array $user = []): void
    {
        $productId = (int)($params['productId'] ?? 0);
        $list = $this->movements->findByProduct($productId);
        Response::success(array_map(static fn($m) => $m->toArray(), $list));
    }
}
