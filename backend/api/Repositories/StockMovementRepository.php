<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Config\Database;
use App\Models\StockMovement;
use PDO;

class StockMovementRepository
{
    private const TYPES = ['ENTRY' => 'INCREMENT', 'EXIT' => 'DECREMENT', 'ADJUSTMENT' => 'ADJUSTMENT'];

    public function create(
        int $stockId,
        string $type,
        int $quantity,
        int $stockBefore,
        int $stockAfter,
        int $userId,
        ?string $reason = null
    ): StockMovement {
        $pdo = Database::getConnection();
        $dbType = self::mapType($type);
        $stmt = $pdo->prepare('
            INSERT INTO stock_movements (stock_id, user_id, type, quantity, balance_after, note, created_at)
            VALUES (:stock_id, :user_id, :type, :quantity, :balance_after, :note, NOW())
        ');
        $stmt->execute([
            'stock_id' => $stockId,
            'user_id' => $userId,
            'type' => $dbType,
            'quantity' => $quantity,
            'balance_after' => $stockAfter,
            'note' => $reason,
        ]);
        $id = (int)$pdo->lastInsertId();
        return $this->findById($id, $stockBefore);
    }

    private function mapType(string $type): string
    {
        return match ($type) {
            StockMovement::TYPE_ENTRY => 'INCREMENT',
            StockMovement::TYPE_EXIT => 'DECREMENT',
            StockMovement::TYPE_ADJUSTMENT => 'ADJUSTMENT',
            default => $type,
        };
    }

    public function findById(int $id, ?int $stockBefore = null): StockMovement
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('
            SELECT sm.id, sm.stock_id, sm.user_id, sm.type, sm.quantity, sm.balance_after, sm.note AS reason, sm.created_at,
                   st.site_id, st.product_id,
                   u.first_name AS user_first_name, u.last_name AS user_last_name,
                   pr.name AS product_name,
                   si.name AS site_name
              FROM stock_movements sm
              JOIN stocks st ON st.id = sm.stock_id
              JOIN users u ON u.id = sm.user_id
              JOIN products pr ON pr.id = st.product_id
              JOIN sites si ON si.id = st.site_id
             WHERE sm.id = :id
             LIMIT 1
        ');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($stockBefore !== null) {
            $row['stock_before'] = $stockBefore;
        }
        return StockMovement::fromRow($row);
    }

    public function findPaginated(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $pdo = Database::getConnection();
        $sql = '
            SELECT SQL_CALC_FOUND_ROWS
                   sm.id, sm.stock_id, sm.user_id, sm.type, sm.quantity, sm.balance_after, sm.note AS reason, sm.created_at,
                   st.site_id, st.product_id,
                   u.first_name AS user_first_name, u.last_name AS user_last_name,
                   pr.name AS product_name,
                   si.name AS site_name
              FROM stock_movements sm
              JOIN stocks st ON st.id = sm.stock_id
              JOIN users u ON u.id = sm.user_id
              JOIN products pr ON pr.id = st.product_id
              JOIN sites si ON si.id = st.site_id
             WHERE 1=1
        ';
        $params = [];
        if (!empty($filters['siteId'])) {
            $sql .= ' AND st.site_id = :siteId';
            $params['siteId'] = (int)$filters['siteId'];
        }
        if (!empty($filters['productId'])) {
            $sql .= ' AND st.product_id = :productId';
            $params['productId'] = (int)$filters['productId'];
        }
        if (!empty($filters['type'])) {
            $sql .= ' AND sm.type = :type';
            $params['type'] = self::mapType((string)$filters['type']);
        }
        if (!empty($filters['from'])) {
            $sql .= ' AND sm.created_at >= :from';
            $params['from'] = (string)$filters['from'];
        }
        if (!empty($filters['to'])) {
            $sql .= ' AND sm.created_at <= :to';
            $params['to'] = (string)$filters['to'];
        }
        $sql .= ' ORDER BY sm.created_at DESC, sm.id DESC LIMIT :offset, :limit';
        $offset = max(0, ($page - 1) * $perPage);

        $stmt = $pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
        $stmt->bindValue('limit', $perPage, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $total = (int)$pdo->query('SELECT FOUND_ROWS()')->fetchColumn();

        return [
            'items' => array_map([StockMovement::class, 'fromRow'], $rows),
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
        ];
    }

    public function findBySite(int $siteId): array
    {
        $res = $this->findPaginated(['siteId' => $siteId], 1, 500);
        return $res['items'];
    }

    public function findByProduct(int $productId): array
    {
        $res = $this->findPaginated(['productId' => $productId], 1, 500);
        return $res['items'];
    }
}
