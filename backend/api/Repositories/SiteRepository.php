<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Config\Database;
use App\Models\Site;
use PDO;

class SiteRepository
{
    public function findAll(?bool $active = null): array
    {
        $pdo = Database::getConnection();
        $sql = 'SELECT s.*, c.name AS client_name FROM sites s LEFT JOIN clients c ON c.id = s.client_id';
        $where = [];
        $params = [];
        if ($active !== null) {
            $where[] = 's.active = :active';
            $params['active'] = $active ? 1 : 0;
        }
        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY s.name ASC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return array_map([Site::class, 'fromRow'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function findByClient(int $clientId, ?bool $active = null): array
    {
        $pdo = Database::getConnection();
        $sql = 'SELECT s.*, c.name AS client_name FROM sites s LEFT JOIN clients c ON c.id = s.client_id WHERE s.client_id = :clientId';
        $params = ['clientId' => $clientId];
        if ($active !== null) {
            $sql .= ' AND s.active = :active';
            $params['active'] = $active ? 1 : 0;
        }
        $sql .= ' ORDER BY s.name ASC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return array_map([Site::class, 'fromRow'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function findById(int $id): ?Site
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('
            SELECT s.*, c.name AS client_name
              FROM sites s
              LEFT JOIN clients c ON c.id = s.client_id
             WHERE s.id = :id
             LIMIT 1
        ');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? Site::fromRow($row) : null;
    }

    public function create(array $data): Site
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('
            INSERT INTO sites (client_id, name, code, address, city, postal_code, country, active)
            VALUES (:client_id, :name, :code, :address, :city, :postal_code, :country, :active)
        ');
        $stmt->execute([
            'client_id' => (int)$data['clientId'],
            'name' => $data['name'],
            'code' => $data['code'] ?? null,
            'address' => $data['address'] ?? null,
            'city' => $data['city'] ?? null,
            'postal_code' => $data['postalCode'] ?? null,
            'country' => $data['country'] ?? 'Morocco',
            'active' => (int)($data['active'] ?? 1),
        ]);
        return $this->findById((int)$pdo->lastInsertId());
    }

    public function update(int $id, array $data): ?Site
    {
        $pdo = Database::getConnection();
        $current = $this->findById($id);
        if ($current === null) {
            return null;
        }
        $fields = [
            'client_id' => (int)($data['clientId'] ?? $current->clientId),
            'name' => $data['name'] ?? $current->name,
            'code' => array_key_exists('code', $data) ? ($data['code'] ?? null) : $current->code,
            'address' => array_key_exists('address', $data) ? ($data['address'] ?? null) : $current->address,
            'city' => array_key_exists('city', $data) ? ($data['city'] ?? null) : $current->city,
            'postal_code' => array_key_exists('postalCode', $data) ? ($data['postalCode'] ?? null) : $current->postalCode,
            'country' => $data['country'] ?? $current->country,
            'active' => array_key_exists('active', $data) ? (int)$data['active'] : (int)$current->active,
        ];
        $stmt = $pdo->prepare('
            UPDATE sites
               SET client_id = :client_id, name = :name, code = :code, address = :address, city = :city,
                   postal_code = :postal_code, country = :country, active = :active
             WHERE id = :id
        ');
        $stmt->execute(['id' => $id, ...$fields]);
        return $this->findById($id);
    }

    public function delete(int $id): bool
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('DELETE FROM sites WHERE id = :id');
        $stmt->execute(['id' => $id]);
        return $stmt->rowCount() > 0;
    }
}
