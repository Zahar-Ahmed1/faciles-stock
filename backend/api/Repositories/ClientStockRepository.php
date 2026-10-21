<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Config\Database;
use App\Models\ClientStock;
use PDO;

class ClientStockRepository
{
    public function findAll(?int $siteId = null): array
    {
        $pdo = Database::getConnection();
        $sql = '
            SELECT st.id, st.site_id, st.product_id, st.quantity, st.minimum_quantity, st.maximum_quantity,
                   st.created_at, st.updated_at,
                   si.name AS site_name, si.client_id,
                   cl.name AS client_name,
                   pr.name AS product_name, pr.sku AS product_reference, pr.image_url AS product_image_url, pr.unit AS product_unit
              FROM stocks st
              JOIN sites si ON si.id = st.site_id
              JOIN clients cl ON cl.id = si.client_id
              JOIN products pr ON pr.id = st.product_id
        ';
        $params = [];
        if ($siteId !== null) {
            $sql .= ' WHERE st.site_id = :siteId';
            $params['siteId'] = $siteId;
        }
        $sql .= ' ORDER BY pr.name ASC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return array_map([ClientStock::class, 'fromRow'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function findBySite(int $siteId): array
    {
        return $this->findAll($siteId);
    }

    public function findById(int $id, bool $forUpdate = false): ?ClientStock
    {
        $pdo = Database::getConnection();
        $sql = '
            SELECT st.id, st.site_id, st.product_id, st.quantity, st.minimum_quantity, st.maximum_quantity,
                   st.created_at, st.updated_at,
                   si.name AS site_name, si.client_id,
                   cl.name AS client_name,
                   pr.name AS product_name, pr.sku AS product_reference, pr.image_url AS product_image_url, pr.unit AS product_unit
              FROM stocks st
              JOIN sites si ON si.id = st.site_id
              JOIN clients cl ON cl.id = si.client_id
              JOIN products pr ON pr.id = st.product_id
             WHERE st.id = :id
             LIMIT 1
        ';
        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? ClientStock::fromRow($row) : null;
    }

    public function findBySiteAndProduct(int $siteId, int $productId, bool $forUpdate = false): ?ClientStock
    {
        $pdo = Database::getConnection();
        $sql = '
            SELECT st.id, st.site_id, st.product_id, st.quantity, st.minimum_quantity, st.maximum_quantity,
                   st.created_at, st.updated_at,
                   si.name AS site_name, si.client_id,
                   cl.name AS client_name,
                   pr.name AS product_name, pr.sku AS product_reference, pr.image_url AS product_image_url, pr.unit AS product_unit
              FROM stocks st
              JOIN sites si ON si.id = st.site_id
              JOIN clients cl ON cl.id = si.client_id
              JOIN products pr ON pr.id = st.product_id
             WHERE st.site_id = :siteId AND st.product_id = :productId
             LIMIT 1
        ';
        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['siteId' => $siteId, 'productId' => $productId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? ClientStock::fromRow($row) : null;
    }

    public function create(array $data): ClientStock
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('
            INSERT INTO stocks (site_id, product_id, quantity, minimum_quantity, maximum_quantity)
            VALUES (:site_id, :product_id, :quantity, :minimum_quantity, :maximum_quantity)
        ');
        $stmt->execute([
            'site_id' => (int)$data['siteId'],
            'product_id' => (int)$data['productId'],
            'quantity' => (int)($data['quantity'] ?? 0),
            'minimum_quantity' => (int)($data['minimumQuantity'] ?? 0),
            'maximum_quantity' => (int)($data['maximumQuantity'] ?? 100),
        ]);
        return $this->findById((int)$pdo->lastInsertId());
    }

    public function updateThresholds(int $id, int $min, int $max): ?ClientStock
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('
            UPDATE stocks
               SET minimum_quantity = :min, maximum_quantity = :max
             WHERE id = :id
        ');
        $stmt->execute(['id' => $id, 'min' => $min, 'max' => $max]);
        return $this->findById($id);
    }

    public function setQuantity(int $id, int $quantity): void
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('UPDATE stocks SET quantity = :qty WHERE id = :id');
        $stmt->execute(['id' => $id, 'qty' => $quantity]);
    }

    public function delete(int $id): bool
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('DELETE FROM stocks WHERE id = :id');
        $stmt->execute(['id' => $id]);
        return $stmt->rowCount() > 0;
    }

    public function countByProduct(int $productId): int
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM stocks WHERE product_id = :productId');
        $stmt->execute(['productId' => $productId]);
        return (int)$stmt->fetchColumn();
    }

    public function deleteBySiteAndProduct(int $siteId, int $productId): bool
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('DELETE FROM stocks WHERE site_id = :siteId AND product_id = :productId');
        $stmt->execute(['siteId' => $siteId, 'productId' => $productId]);
        return $stmt->rowCount() > 0;
    }
}
