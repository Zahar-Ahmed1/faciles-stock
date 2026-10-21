<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Config\Database;
use App\Models\DeliveryProposal;
use PDO;

class DeliveryProposalRepository
{
    private static bool $ready = false;

    public function ensureSchema(): void
    {
        if (self::$ready) {
            return;
        }

        Database::getConnection()->exec('
            CREATE TABLE IF NOT EXISTS delivery_proposals (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                alert_id INT UNSIGNED NOT NULL,
                stock_id INT UNSIGNED NOT NULL,
                quantity INT NOT NULL,
                status ENUM(\'PENDING\',\'CONFIRMED\',\'REJECTED\') NOT NULL DEFAULT \'PENDING\',
                proposed_by INT UNSIGNED NOT NULL,
                confirmed_by INT UNSIGNED NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                decided_at DATETIME NULL,
                CONSTRAINT fk_proposals_alert FOREIGN KEY (alert_id) REFERENCES alerts(id) ON DELETE CASCADE,
                CONSTRAINT fk_proposals_stock FOREIGN KEY (stock_id) REFERENCES stocks(id) ON DELETE CASCADE,
                CONSTRAINT fk_proposals_proposed_by FOREIGN KEY (proposed_by) REFERENCES users(id) ON DELETE RESTRICT,
                CONSTRAINT fk_proposals_confirmed_by FOREIGN KEY (confirmed_by) REFERENCES users(id) ON DELETE SET NULL,
                INDEX idx_proposals_status (status),
                INDEX idx_proposals_alert (alert_id),
                INDEX idx_proposals_stock (stock_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');

        $this->ensureConfirmationColumns();
        self::$ready = true;
    }

    private function ensureConfirmationColumns(): void
    {
        $pdo = Database::getConnection();
        foreach (['manager_confirmed_by', 'responsable_confirmed_by'] as $column) {
            $stmt = $pdo->prepare('
                SELECT COUNT(*)
                  FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = \'delivery_proposals\'
                   AND COLUMN_NAME = :column
            ');
            $stmt->execute(['column' => $column]);
            if ((int)$stmt->fetchColumn() > 0) {
                continue;
            }
            $pdo->exec("ALTER TABLE delivery_proposals ADD COLUMN {$column} INT UNSIGNED NULL");
        }
    }

    public function findAll(array $filters = []): array
    {
        $this->ensureSchema();
        $pdo = Database::getConnection();
        $sql = $this->selectSql() . ' WHERE 1=1';
        $params = [];

        if (!empty($filters['status'])) {
            $sql .= ' AND p.status = :status';
            $params['status'] = (string)$filters['status'];
        }
        if (!empty($filters['siteId'])) {
            $sql .= ' AND st.site_id = :siteId';
            $params['siteId'] = (int)$filters['siteId'];
        }
        if (!empty($filters['stockId'])) {
            $sql .= ' AND p.stock_id = :stockId';
            $params['stockId'] = (int)$filters['stockId'];
        }

        $sql .= ' ORDER BY p.created_at DESC, p.id DESC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return array_map([DeliveryProposal::class, 'fromRow'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function findById(int $id): ?DeliveryProposal
    {
        $this->ensureSchema();
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare($this->selectSql() . ' WHERE p.id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? DeliveryProposal::fromRow($row) : null;
    }

    public function lockById(int $id): ?DeliveryProposal
    {
        $this->ensureSchema();
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT id FROM delivery_proposals WHERE id = :id FOR UPDATE');
        $stmt->execute(['id' => $id]);
        if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
            return null;
        }

        return $this->findById($id);
    }

    public function savePending(int $alertId, int $stockId, int $quantity, int $userId): DeliveryProposal
    {
        $this->ensureSchema();
        $pdo = Database::getConnection();
        $ownsTransaction = !Database::inTransaction();
        if ($ownsTransaction) {
            Database::beginTransaction();
        }

        try {
            $stmt = $pdo->prepare('
                SELECT id
                  FROM delivery_proposals
                 WHERE stock_id = :stockId AND status = :status
                 ORDER BY id DESC
                 LIMIT 1
                 FOR UPDATE
            ');
            $stmt->execute([
                'stockId' => $stockId,
                'status' => DeliveryProposal::STATUS_PENDING,
            ]);
            $existingId = $stmt->fetchColumn();

            if ($existingId) {
                $update = $pdo->prepare('
                    UPDATE delivery_proposals
                       SET alert_id = :alertId,
                           quantity = :quantity,
                           proposed_by = :proposedBy,
                           manager_confirmed_by = NULL,
                           responsable_confirmed_by = NULL,
                           created_at = NOW()
                     WHERE id = :id
                ');
                $update->execute([
                    'alertId' => $alertId,
                    'quantity' => $quantity,
                    'proposedBy' => $userId,
                    'id' => (int)$existingId,
                ]);
                $id = (int)$existingId;
            } else {
                $insert = $pdo->prepare('
                    INSERT INTO delivery_proposals
                        (alert_id, stock_id, quantity, status, proposed_by, created_at)
                    VALUES
                        (:alertId, :stockId, :quantity, :status, :proposedBy, NOW())
                ');
                $insert->execute([
                    'alertId' => $alertId,
                    'stockId' => $stockId,
                    'quantity' => $quantity,
                    'status' => DeliveryProposal::STATUS_PENDING,
                    'proposedBy' => $userId,
                ]);
                $id = (int)$pdo->lastInsertId();
            }

            if ($ownsTransaction) {
                Database::commit();
            }

            $saved = $this->findById($id);
            if ($saved === null) {
                throw new \RuntimeException('Could not reload delivery proposal');
            }

            return $saved;
        } catch (\Throwable $e) {
            if ($ownsTransaction && Database::inTransaction()) {
                Database::rollBack();
            }
            throw $e;
        }
    }

    public function markDecided(int $id, string $status, int $userId): ?DeliveryProposal
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('
            UPDATE delivery_proposals
               SET status = :status,
                   confirmed_by = :userId,
                   decided_at = NOW()
             WHERE id = :id AND status = :pending
        ');
        $stmt->execute([
            'status' => $status,
            'userId' => $userId,
            'id' => $id,
            'pending' => DeliveryProposal::STATUS_PENDING,
        ]);

        return $this->findById($id);
    }

    public function recordConfirmation(int $id, string $role, int $userId): bool
    {
        $column = $role === 'MANAGER' ? 'manager_confirmed_by' : 'responsable_confirmed_by';
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            UPDATE delivery_proposals
               SET {$column} = :userId
             WHERE id = :id
               AND status = :pending
               AND {$column} IS NULL
        ");
        $stmt->execute([
            'userId' => $userId,
            'id' => $id,
            'pending' => DeliveryProposal::STATUS_PENDING,
        ]);

        return $stmt->rowCount() > 0;
    }

    private function selectSql(): string
    {
        return '
            SELECT p.*,
                   st.site_id,
                   st.product_id,
                   si.name AS site_name,
                   pr.name AS product_name,
                   TRIM(CONCAT(u.first_name, \' \', u.last_name)) AS proposed_by_name,
                   TRIM(CONCAT(mu.first_name, \' \', mu.last_name)) AS manager_confirmed_by_name,
                   TRIM(CONCAT(ru.first_name, \' \', ru.last_name)) AS responsable_confirmed_by_name
              FROM delivery_proposals p
              JOIN stocks st ON st.id = p.stock_id
              JOIN sites si ON si.id = st.site_id
              JOIN products pr ON pr.id = st.product_id
              LEFT JOIN users u ON u.id = p.proposed_by
              LEFT JOIN users mu ON mu.id = p.manager_confirmed_by
              LEFT JOIN users ru ON ru.id = p.responsable_confirmed_by
        ';
    }
}
