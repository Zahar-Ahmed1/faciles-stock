<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Config\Database;
use App\Models\Alert;
use PDO;

class AlertRepository
{
    public function findAll(array $filters = []): array
    {
        $pdo = Database::getConnection();
        $sql = '
            SELECT a.*, st.site_id, st.product_id,
                   si.name AS site_name, pr.name AS product_name
              FROM alerts a
              JOIN stocks st ON st.id = a.stock_id
              JOIN sites si ON si.id = st.site_id
              JOIN products pr ON pr.id = st.product_id
             WHERE 1=1
        ';
        $params = [];
        if (!empty($filters['siteId'])) {
            $sql .= ' AND st.site_id = :siteId';
            $params['siteId'] = (int)$filters['siteId'];
        }
        if (!empty($filters['unread'])) {
            $sql .= ' AND a.is_read = 0 AND a.is_resolved = 0';
        }
        if (!empty($filters['onlyUnread'])) {
            $sql .= ' AND a.is_read = 0 AND a.is_resolved = 0';
        }
        if (!empty($filters['type'])) {
            $sql .= ' AND a.type = :type';
            $params['type'] = (string)$filters['type'];
        }
        $sql .= ' ORDER BY a.created_at DESC, a.id DESC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return array_map([Alert::class, 'fromRow'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function findUnread(): array
    {
        return $this->findAll(['unread' => true]);
    }

    public function findBySite(int $siteId): array
    {
        return $this->findAll(['siteId' => $siteId]);
    }

    public function findById(int $id): ?Alert
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('
            SELECT a.*, st.site_id, st.product_id,
                   si.name AS site_name, pr.name AS product_name
              FROM alerts a
              JOIN stocks st ON st.id = a.stock_id
              JOIN sites si ON si.id = st.site_id
              JOIN products pr ON pr.id = st.product_id
             WHERE a.id = :id
             LIMIT 1
        ');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? Alert::fromRow($row) : null;
    }

    public function findActiveByStockAndType(int $stockId, string $type): ?Alert
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('
            SELECT * FROM alerts
             WHERE stock_id = :stockId AND type = :type AND is_resolved = 0
             ORDER BY id DESC
             LIMIT 1
        ');
        $stmt->execute(['stockId' => $stockId, 'type' => $type]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? Alert::fromRow($row) : null;
    }

    public function create(int $stockId, string $type, string $message, int $quantityAtAlert, int $threshold): Alert
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('
            INSERT INTO alerts (stock_id, type, message, quantity_at_alert, threshold, is_read, is_resolved, created_at)
            VALUES (:stock_id, :type, :message, :quantity_at_alert, :threshold, 0, 0, NOW())
        ');
        $stmt->execute([
            'stock_id' => $stockId,
            'type' => $type,
            'message' => $message,
            'quantity_at_alert' => $quantityAtAlert,
            'threshold' => $threshold,
        ]);
        return $this->findById((int)$pdo->lastInsertId());
    }

    public function markRead(int $id): ?Alert
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('UPDATE alerts SET is_read = 1 WHERE id = :id AND is_resolved = 0');
        $stmt->execute(['id' => $id]);
        return $this->findById($id);
    }

    public function resolve(int $id): ?Alert
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('
            UPDATE alerts SET is_resolved = 1, resolved_at = NOW(), is_read = 1 WHERE id = :id
        ');
        $stmt->execute(['id' => $id]);
        return $this->findById($id);
    }

    public function autoResolveByType(int $stockId, string $type): void
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('
            UPDATE alerts
               SET is_resolved = 1, resolved_at = NOW(), is_read = 1
             WHERE stock_id = :stockId AND type = :type AND is_resolved = 0
        ');
        $stmt->execute(['stockId' => $stockId, 'type' => $type]);
    }
}
