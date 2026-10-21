<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Config\Config;
use App\Config\Database;
use App\Repositories\CategoryRepository;
use App\Repositories\ClientStockRepository;
use App\Repositories\ProductRepository;
use App\Services\AlertService;
use App\Utils\Response;
use App\Utils\Validator;

class ProductController
{
    public function __construct(
        private readonly ProductRepository $products = new ProductRepository(),
        private readonly CategoryRepository $categories = new CategoryRepository(),
        private readonly ClientStockRepository $stocks = new ClientStockRepository(),
        private readonly AlertService $alertService = new AlertService()
    ) {
    }

    public function index(array $params, array $data, array $user = []): void
    {
        $active = isset($data['active']) ? filter_var($data['active'], FILTER_VALIDATE_BOOLEAN) : null;
        $categoryId = isset($data['categoryId']) ? (int)$data['categoryId'] : null;
        $list = $this->products->findAll($active, $categoryId);
        Response::success(array_map(static fn($p) => $p->toArray(), $list));
    }

    public function show(array $params, array $data, array $user = []): void
    {
        $id = (int)($params['id'] ?? 0);
        $product = $this->products->findById($id);
        if ($product === null) {
            Response::notFound('Product not found');
        }
        Response::success($product->toArray());
    }

    public function store(array $params, array $data, array $user = []): void
    {
        $this->assertStockManager($user);

        $siteId = isset($user['siteId']) ? (int)$user['siteId'] : 0;
        if ($siteId <= 0) {
            Response::forbidden('Aucun site assigné à ce responsable');
        }

        $validator = Validator::make($data, [
            'name' => 'required|string|min:2|max:255',
            'reference' => 'nullable|string|max:80',
            'categoryId' => 'nullable|int',
            'description' => 'nullable|string|max:2000',
            'unit' => 'nullable|string|max:40',
            'active' => 'nullable|boolean',
            'quantity' => 'nullable|int|min:0',
            'minimumQuantity' => 'nullable|int|min:0',
            'maximumQuantity' => 'nullable|int|min:0',
        ]);
        if ($validator->fails()) {
            Response::validationError($validator->errors());
        }
        $valid = $validator->validated();
        if (!empty($valid['categoryId']) && $this->categories->findById((int)$valid['categoryId']) === null) {
            Response::notFound('Referenced category not found');
        }
        $imageUrl = $this->handleUpload($data);
        if ($imageUrl !== null) {
            $valid['imageUrl'] = $imageUrl;
        }

        $quantity = (int)($valid['quantity'] ?? 0);
        $minimum = (int)($valid['minimumQuantity'] ?? 10);
        $maximum = (int)($valid['maximumQuantity'] ?? max(100, $minimum, $quantity));
        if ($maximum < $minimum) {
            Response::badRequest('Maximum quantity must be greater or equal to minimum quantity');
        }

        Database::beginTransaction();
        try {
            $product = $this->products->create($valid);
            $stock = $this->stocks->create([
                'siteId' => $siteId,
                'productId' => $product->id,
                'quantity' => $quantity,
                'minimumQuantity' => $minimum,
                'maximumQuantity' => $maximum,
            ]);
            $this->alertService->evaluate($stock);
            Database::commit();
            Response::created([
                'product' => $product->toArray(),
                'stock' => $stock->toArray(),
            ]);
        } catch (\Throwable $e) {
            if (Database::inTransaction()) {
                Database::rollBack();
            }
            throw $e;
        }
    }

    public function update(array $params, array $data, array $user = []): void
    {
        $this->assertStockManager($user);
        $siteId = $this->requireSiteId($user);

        $id = (int)($params['id'] ?? 0);
        $existing = $this->products->findById($id);
        if ($existing === null) {
            Response::notFound('Product not found');
        }

        $stock = $this->stocks->findBySiteAndProduct($siteId, $id);
        if ($stock === null) {
            Response::forbidden('Ce produit n\'appartient pas à votre site');
        }

        $validator = Validator::make($data, [
            'name' => 'nullable|string|min:2|max:255',
            'reference' => 'nullable|string|max:80',
            'categoryId' => 'nullable|int',
            'description' => 'nullable|string|max:2000',
            'unit' => 'nullable|string|max:40',
            'imageUrl' => 'nullable|url',
            'active' => 'nullable|boolean',
            'quantity' => 'nullable|int|min:0',
            'minimumQuantity' => 'nullable|int|min:0',
            'maximumQuantity' => 'nullable|int|min:0',
        ]);
        if ($validator->fails()) {
            Response::validationError($validator->errors());
        }
        $valid = $validator->validated();
        if (!empty($valid['categoryId']) && $this->categories->findById((int)$valid['categoryId']) === null) {
            Response::notFound('Referenced category not found');
        }
        $imageUrl = $this->handleUpload($data);
        if ($imageUrl !== null) {
            $valid['imageUrl'] = $imageUrl;
        }

        Database::beginTransaction();
        try {
            $product = $this->products->update($id, $valid);
            $min = (int)($valid['minimumQuantity'] ?? $stock->minimumQuantity);
            $max = (int)($valid['maximumQuantity'] ?? $stock->maximumQuantity);
            if ($max < $min) {
                Database::rollBack();
                Response::badRequest('Maximum quantity must be greater or equal to minimum quantity');
            }
            $updatedStock = $this->stocks->updateThresholds($stock->id, $min, $max);
            if (array_key_exists('quantity', $valid) && (int)$valid['quantity'] !== $stock->quantity) {
                $this->stocks->setQuantity($stock->id, (int)$valid['quantity']);
                $updatedStock = $this->stocks->findById($stock->id);
            }
            if ($updatedStock !== null) {
                $this->alertService->evaluate($updatedStock);
            }
            Database::commit();
            Response::success([
                'product' => $product?->toArray(),
                'stock' => $updatedStock?->toArray(),
            ]);
        } catch (\Throwable $e) {
            if (Database::inTransaction()) {
                Database::rollBack();
            }
            throw $e;
        }
    }

    public function destroy(array $params, array $data, array $user = []): void
    {
        $this->assertStockManager($user);
        $siteId = $this->requireSiteId($user);

        $id = (int)($params['id'] ?? 0);
        $existing = $this->products->findById($id);
        if ($existing === null) {
            Response::notFound('Product not found');
        }

        $stock = $this->stocks->findBySiteAndProduct($siteId, $id);
        if ($stock === null) {
            Response::forbidden('Ce produit n\'appartient pas à votre site');
        }

        Database::beginTransaction();
        try {
            $this->stocks->delete($stock->id);
            if ($this->stocks->countByProduct($id) === 0) {
                $this->products->delete($id);
            }
            Database::commit();
            Response::noContent();
        } catch (\Throwable $e) {
            if (Database::inTransaction()) {
                Database::rollBack();
            }
            throw $e;
        }
    }

    private function assertStockManager(array $user): void
    {
        if (($user['role'] ?? '') !== 'STOCK_MANAGER') {
            Response::forbidden('Seul le responsable peut gérer les produits');
        }
    }

    private function requireSiteId(array $user): int
    {
        $siteId = isset($user['siteId']) ? (int)$user['siteId'] : 0;
        if ($siteId <= 0) {
            Response::forbidden('Aucun site assigné à ce responsable');
        }
        return $siteId;
    }

    private function handleUpload(array $data): ?string
    {
        if (!isset($_FILES['image']) || !is_uploaded_file($_FILES['image']['tmp_name'])) {
            return $data['imageUrl'] ?? null;
        }
        $file = $_FILES['image'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            Response::badRequest('Image upload failed', ['code' => $file['error']]);
        }

        $maxSize = (int)Config::get('upload.max_size', 5 * 1024 * 1024);
        if ($file['size'] > $maxSize) {
            Response::badRequest(sprintf('Image exceeds maximum size (%d KB)', (int)($maxSize / 1024)));
        }

        $allowedTypes = Config::get('upload.allowed_types', ['jpg', 'jpeg', 'png', 'webp']);
        if (is_string($allowedTypes)) {
            $allowedTypes = array_map('trim', explode(',', $allowedTypes));
        }
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $allowedTypes, true)) {
            Response::badRequest('Invalid image type. Allowed: ' . implode(', ', $allowedTypes));
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = (string)$finfo->file($file['tmp_name']);
        $allowedMime = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
        if (!in_array($mime, $allowedMime, true)) {
            Response::badRequest('Invalid image mime type');
        }

        $uploadDir = (string)Config::get('upload.products_dir');
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
            Response::serverError('Could not create upload directory');
        }

        $safeName = 'product_' . date('YmdHis') . '_' . bin2hex(random_bytes(6)) . '.' . $extension;
        $destination = rtrim($uploadDir, '/') . '/' . $safeName;
        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            Response::serverError('Could not save uploaded image');
        }

        return '/uploads/products/' . $safeName;
    }
}
