<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\ClientRepository;
use App\Utils\Response;
use App\Utils\Validator;

class ClientController
{
    public function __construct(
        private readonly ClientRepository $clients = new ClientRepository()
    ) {
    }

    public function index(array $params, array $data): void
    {
        $active = isset($data['active']) ? filter_var($data['active'], FILTER_VALIDATE_BOOLEAN) : null;
        $list = $this->clients->findAll($active);
        Response::success(array_map(static fn($c) => $c->toArray(), $list));
    }

    public function show(array $params, array $data): void
    {
        $id = (int)($params['id'] ?? 0);
        $client = $this->clients->findById($id);
        if ($client === null) {
            Response::notFound('Client not found');
        }
        Response::success($client->toArray());
    }

    public function store(array $params, array $data): void
    {
        $validator = Validator::make($data, [
            'name' => 'required|string|min:2|max:255',
            'code' => 'nullable|string|max:50',
            'email' => 'nullable|email',
            'phone' => 'nullable|string|max:30',
            'address' => 'nullable|string|max:500',
            'active' => 'nullable|boolean',
        ]);
        if ($validator->fails()) {
            Response::validationError($validator->errors());
        }

        $code = $data['code'] ?? null;
        if ($code !== null && trim((string)$code) !== '') {
            if ($this->clients->findByCode(trim((string)$code)) !== null) {
                Response::conflict('Client with this code already exists');
            }
        }

        $client = $this->clients->create($validator->validated());
        Response::created($client->toArray());
    }

    public function update(array $params, array $data): void
    {
        $id = (int)($params['id'] ?? 0);
        $existing = $this->clients->findById($id);
        if ($existing === null) {
            Response::notFound('Client not found');
        }
        $validator = Validator::make($data, [
            'name' => 'nullable|string|min:2|max:255',
            'code' => 'nullable|string|max:50',
            'email' => 'nullable|email',
            'phone' => 'nullable|string|max:30',
            'address' => 'nullable|string|max:500',
            'active' => 'nullable|boolean',
        ]);
        if ($validator->fails()) {
            Response::validationError($validator->errors());
        }

        $code = $data['code'] ?? null;
        if ($code !== null && trim((string)$code) !== '' && (string)$code !== (string)$existing->code) {
            if ($this->clients->findByCode(trim((string)$code)) !== null) {
                Response::conflict('Client with this code already exists');
            }
        }

        $client = $this->clients->update($id, $validator->validated());
        Response::success($client?->toArray());
    }

    public function destroy(array $params, array $data): void
    {
        $id = (int)($params['id'] ?? 0);
        $existing = $this->clients->findById($id);
        if ($existing === null) {
            Response::notFound('Client not found');
        }
        $this->clients->delete($id);
        Response::noContent();
    }
}
