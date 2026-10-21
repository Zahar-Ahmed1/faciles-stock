<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Config\Database;
use App\Models\User;
use PDO;

class UserRepository
{
    public function findAll(?string $role = null, ?int $siteId = null): array
    {
        $pdo = Database::getConnection();
        $sql = 'SELECT u.*, s.name AS site_name FROM users u LEFT JOIN sites s ON s.id = u.site_id';
        $where = [];
        $params = [];
        if ($role !== null) {
            $where[] = 'u.role = :role';
            $params['role'] = $role;
        }
        if ($siteId !== null) {
            $where[] = 'u.site_id = :siteId';
            $params['siteId'] = $siteId;
        }
        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY u.first_name ASC, u.last_name ASC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return array_map([User::class, 'fromRow'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function findByEmail(string $email): ?array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('
            SELECT
                id, first_name, last_name, email, password_hash, role, active, site_id, created_at, updated_at
            FROM users WHERE email = :email LIMIT 1
        ');
        $stmt->execute(['email' => $email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findById(int $id): ?User
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('
            SELECT id, first_name, last_name, email, role, active, site_id, created_at, updated_at
              FROM users WHERE id = :id LIMIT 1
        ');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? User::fromRow($row) : null;
    }

    public function toPublicArray(array|User $user): array
    {
        if ($user instanceof User) {
            return $user->toArray();
        }
        return [
            'id' => (int)$user['id'],
            'firstName' => $user['first_name'],
            'lastName' => $user['last_name'],
            'email' => $user['email'],
            'role' => $user['role'],
            'active' => (bool)($user['active'] ?? true),
            'siteId' => isset($user['site_id']) && $user['site_id'] !== null ? (int)$user['site_id'] : null,
            'createdAt' => $user['created_at'] ?? null,
            'updatedAt' => $user['updated_at'] ?? null,
        ];
    }

    public function create(array $data, string $passwordHash): User
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('
            INSERT INTO users (first_name, last_name, email, password_hash, role, active, site_id)
            VALUES (:first_name, :last_name, :email, :password_hash, :role, :active, :site_id)
        ');
        $stmt->execute([
            'first_name' => $data['firstName'],
            'last_name' => $data['lastName'],
            'email' => $data['email'],
            'password_hash' => $passwordHash,
            'role' => $data['role'] ?? 'STOCK_MANAGER',
            'active' => (int)($data['active'] ?? 1),
            'site_id' => isset($data['siteId']) && $data['siteId'] !== null ? (int)$data['siteId'] : null,
        ]);
        return $this->findById((int)$pdo->lastInsertId());
    }

    public function update(int $id, array $data): ?User
    {
        $pdo = Database::getConnection();
        $current = $this->findById($id);
        if ($current === null) {
            return null;
        }
        $public = $current->toArray();
        $fields = [
            'first_name' => $data['firstName'] ?? $public['firstName'],
            'last_name' => $data['lastName'] ?? $public['lastName'],
            'email' => $data['email'] ?? $public['email'],
            'role' => $data['role'] ?? $public['role'],
            'active' => array_key_exists('active', $data) ? (int)$data['active'] : (int)$public['active'],
            'site_id' => array_key_exists('siteId', $data)
                ? ($data['siteId'] !== null ? (int)$data['siteId'] : null)
                : $public['siteId'],
        ];
        $stmt = $pdo->prepare('
            UPDATE users
               SET first_name = :first_name, last_name = :last_name, email = :email,
                   role = :role, active = :active, site_id = :site_id
             WHERE id = :id
        ');
        $stmt->execute(['id' => $id, ...$fields]);
        return $this->findById($id);
    }

    public function delete(int $id): bool
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('DELETE FROM users WHERE id = :id');
        $stmt->execute(['id' => $id]);
        return $stmt->rowCount() > 0;
    }
}
