<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Config\Database;
use App\Models\Product;
use PDO;

class ProductRepository
{
    public function findAll(?bool $active = null, ?int $categoryId = null): array
    {
        $pdo = Database::getConnection();
        $sql = 'SELECT p.*, c.name AS category_name FROM products p
                 LEFT JOIN product_categories c ON c.id = p.category_id';
        $where = [];
        $params = [];
        if ($active !== null) {
            $where[] = 'p.active = :active';
            $params['active'] = $active ? 1 : 0;
        }
        if ($categoryId !== null) {
            $where[] = 'p.category_id = :categoryId';
            $params['categoryId'] = $categoryId;
        }
        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY p.name ASC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return array_map([Product::class, 'fromRow'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function findById(int $id): ?Product
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('
            SELECT p.*, c.name AS category_name
              FROM products p
              LEFT JOIN product_categories c ON c.id = p.category_id
             WHERE p.id = :id
             LIMIT 1
        ');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? Product::fromRow($row) : null;
    }

    public function findByReference(string $reference): ?Product
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT * FROM products WHERE sku = :ref OR reference = :ref LIMIT 1');
        $stmt->execute(['ref' => $reference]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? Product::fromRow($row) : null;
    }

    public function create(array $data): Product
    {
        $pdo = Database::getConnection();
        $ref = $data['reference'] ?? $data['sku'] ?? null;
        $stmt = $pdo->prepare('
            INSERT INTO products (category_id, name, sku, reference, description, unit, image_url, active)
            VALUES (:category_id, :name, :sku, :reference, :description, :unit, :image_url, :active)
        ');
        $stmt->execute([
            'category_id' => isset($data['categoryId']) && $data['categoryId'] !== null ? (int)$data['categoryId'] : null,
            'name' => $data['name'],
            'sku' => $ref,
            'reference' => $ref,
            'description' => $data['description'] ?? null,
            'unit' => $data['unit'] ?? 'unités',
            'image_url' => $data['imageUrl'] ?? null,
            'active' => (int)($data['active'] ?? 1),
        ]);
        return $this->findById((int)$pdo->lastInsertId());
    }

    public function update(int $id, array $data): ?Product
    {
        $pdo = Database::getConnection();
        $current = $this->findById($id);
        if ($current === null) {
            return null;
        }
        $ref = array_key_exists('reference', $data) ? ($data['reference'] ?? null) : $current->reference;
        $fields = [
            'category_id' => array_key_exists('categoryId', $data)
                ? ($data['categoryId'] !== null ? (int)$data['categoryId'] : null)
                : $current->categoryId,
            'name' => $data['name'] ?? $current->name,
            'sku' => $ref,
            'reference' => $ref,
            'description' => array_key_exists('description', $data) ? ($data['description'] ?? null) : $current->description,
            'unit' => $data['unit'] ?? $current->unit,
            'image_url' => array_key_exists('imageUrl', $data) ? ($data['imageUrl'] ?? null) : $current->imageUrl,
            'active' => array_key_exists('active', $data) ? (int)$data['active'] : (int)$current->active,
        ];
        $stmt = $pdo->prepare('
            UPDATE products
               SET category_id = :category_id, name = :name, sku = :sku, reference = :reference,
                   description = :description, unit = :unit, image_url = :image_url, active = :active
             WHERE id = :id
        ');
        $stmt->execute(['id' => $id, ...$fields]);
        return $this->findById($id);
    }

    public function delete(int $id): bool
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('DELETE FROM products WHERE id = :id');
        $stmt->execute(['id' => $id]);
        return $stmt->rowCount() > 0;
    }
}
