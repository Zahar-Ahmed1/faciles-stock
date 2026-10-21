<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Config\Database;
use App\Models\Category;
use PDO;

class CategoryRepository
{
    public function findAll(?bool $active = null): array
    {
        $pdo = Database::getConnection();
        $sql = 'SELECT * FROM product_categories';
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
        return array_map([Category::class, 'fromRow'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function findById(int $id): ?Category
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT * FROM product_categories WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? Category::fromRow($row) : null;
    }

    public function create(array $data): Category
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('INSERT INTO product_categories (name, description) VALUES (:name, :description)');
        $stmt->execute([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
        ]);
        return $this->findById((int)$pdo->lastInsertId());
    }

    public function update(int $id, array $data): ?Category
    {
        $pdo = Database::getConnection();
        $current = $this->findById($id);
        if ($current === null) {
            return null;
        }
        $fields = [
            'name' => $data['name'] ?? $current->name,
            'description' => array_key_exists('description', $data) ? ($data['description'] ?? null) : $current->description,
            'active' => array_key_exists('active', $data) ? (int)$data['active'] : (int)$current->active,
        ];
        $stmt = $pdo->prepare('
            UPDATE product_categories
               SET name = :name, description = :description, active = :active
             WHERE id = :id
        ');
        $stmt->execute(['id' => $id, ...$fields]);
        return $this->findById($id);
    }

    public function delete(int $id): bool
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('DELETE FROM product_categories WHERE id = :id');
        $stmt->execute(['id' => $id]);
        return $stmt->rowCount() > 0;
    }
}
