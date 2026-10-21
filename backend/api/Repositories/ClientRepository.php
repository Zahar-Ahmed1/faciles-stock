<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Config\Database;
use App\Models\Client;
use PDO;

class ClientRepository
{
    public function findAll(?bool $active = null): array
    {
        $pdo = Database::getConnection();
        $sql = 'SELECT * FROM clients';
        $where = [];
        $params = [];
        if ($active !== null) {
            $where[] = 'active = :active';
            $params['active'] = $active ? 1 : 0;
        }
        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY name ASC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return array_map([Client::class, 'fromRow'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function findById(int $id): ?Client
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT * FROM clients WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? Client::fromRow($row) : null;
    }

    public function findByCode(string $code): ?Client
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT * FROM clients WHERE code = :code LIMIT 1');
        $stmt->execute(['code' => $code]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? Client::fromRow($row) : null;
    }

    public function create(array $data): Client
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('
            INSERT INTO clients (name, code, email, phone, address, active)
            VALUES (:name, :code, :email, :phone, :address, :active)
        ');
        $stmt->execute([
            'name' => $data['name'],
            'code' => $data['code'] ?? null,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'address' => $data['address'] ?? null,
            'active' => (int)($data['active'] ?? 1),
        ]);
        return $this->findById((int)$pdo->lastInsertId());
    }

    public function update(int $id, array $data): ?Client
    {
        $pdo = Database::getConnection();
        $current = $this->findById($id);
        if ($current === null) {
            return null;
        }
        $fields = [
            'name' => $data['name'] ?? $current->name,
            'code' => array_key_exists('code', $data) ? ($data['code'] ?? null) : $current->code,
            'email' => array_key_exists('email', $data) ? ($data['email'] ?? null) : $current->email,
            'phone' => array_key_exists('phone', $data) ? ($data['phone'] ?? null) : $current->phone,
            'address' => array_key_exists('address', $data) ? ($data['address'] ?? null) : $current->address,
            'active' => array_key_exists('active', $data) ? (int)$data['active'] : (int)$current->active,
        ];
        $stmt = $pdo->prepare('
            UPDATE clients
               SET name = :name, code = :code, email = :email, phone = :phone, address = :address, active = :active
             WHERE id = :id
        ');
        $stmt->execute(['id' => $id, ...$fields]);
        return $this->findById($id);
    }

    public function delete(int $id): bool
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('DELETE FROM clients WHERE id = :id');
        $stmt->execute(['id' => $id]);
        return $stmt->rowCount() > 0;
    }
}
