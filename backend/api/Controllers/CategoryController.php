<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\CategoryRepository;
use App\Utils\Response;
use App\Utils\Validator;

class CategoryController
{
    public function __construct(
        private readonly CategoryRepository $categories = new CategoryRepository()
    ) {
    }

    public function index(array $params, array $data): void
    {
        $active = isset($data['active']) ? filter_var($data['active'], FILTER_VALIDATE_BOOLEAN) : null;
        $list = $this->categories->findAll($active);
        Response::success(array_map(static fn($c) => $c->toArray(), $list));
    }

    public function show(array $params, array $data): void
    {
        $id = (int)($params['id'] ?? 0);
        $category = $this->categories->findById($id);
        if ($category === null) {
            Response::notFound('Category not found');
        }
        Response::success($category->toArray());
    }

    public function store(array $params, array $data): void
    {
        $validator = Validator::make($data, [
            'name' => 'required|string|min:2|max:150',
            'description' => 'nullable|string|max:500',
            'active' => 'nullable|boolean',
        ]);
        if ($validator->fails()) {
            Response::validationError($validator->errors());
        }
        $category = $this->categories->create($validator->validated());
        Response::created($category->toArray());
    }

    public function update(array $params, array $data): void
    {
        $id = (int)($params['id'] ?? 0);
        $existing = $this->categories->findById($id);
        if ($existing === null) {
            Response::notFound('Category not found');
        }
        $validator = Validator::make($data, [
            'name' => 'nullable|string|min:2|max:150',
            'description' => 'nullable|string|max:500',
            'active' => 'nullable|boolean',
        ]);
        if ($validator->fails()) {
            Response::validationError($validator->errors());
        }
        $category = $this->categories->update($id, $validator->validated());
        Response::success($category?->toArray());
    }

    public function destroy(array $params, array $data): void
    {
        $id = (int)($params['id'] ?? 0);
        $existing = $this->categories->findById($id);
        if ($existing === null) {
            Response::notFound('Category not found');
        }
        $this->categories->delete($id);
        Response::noContent();
    }
}
