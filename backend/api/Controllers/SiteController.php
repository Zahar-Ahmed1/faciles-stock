<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\ClientRepository;
use App\Repositories\SiteRepository;
use App\Utils\Response;
use App\Utils\Validator;

class SiteController
{
    public function __construct(
        private readonly SiteRepository $sites = new SiteRepository(),
        private readonly ClientRepository $clients = new ClientRepository()
    ) {
    }

    public function index(array $params, array $data, array $user = []): void
    {
        $active = isset($data['active']) ? filter_var($data['active'], FILTER_VALIDATE_BOOLEAN) : null;
        $list = $this->sites->findAll($active);
        $role = (string)($user['role'] ?? '');
        if ($role === 'MANAGER' || $role === 'STOCK_MANAGER') {
            $siteId = isset($user['siteId']) ? (int)$user['siteId'] : 0;
            $list = array_values(array_filter(
                $list,
                static fn($site) => $site->id === $siteId
            ));
        }
        Response::success(array_map(static fn($s) => $s->toArray(), $list));
    }

    public function byClient(array $params, array $data): void
    {
        $clientId = (int)($params['clientId'] ?? 0);
        if ($this->clients->findById($clientId) === null) {
            Response::notFound('Client not found');
        }
        $active = isset($data['active']) ? filter_var($data['active'], FILTER_VALIDATE_BOOLEAN) : null;
        $list = $this->sites->findByClient($clientId, $active);
        Response::success(array_map(static fn($s) => $s->toArray(), $list));
    }

    public function show(array $params, array $data): void
    {
        $id = (int)($params['id'] ?? 0);
        $site = $this->sites->findById($id);
        if ($site === null) {
            Response::notFound('Site not found');
        }
        Response::success($site->toArray());
    }

    public function store(array $params, array $data): void
    {
        $validator = Validator::make($data, [
            'clientId' => 'required|int',
            'name' => 'required|string|min:2|max:255',
            'code' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:150',
            'postalCode' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:100',
            'active' => 'nullable|boolean',
        ]);
        if ($validator->fails()) {
            Response::validationError($validator->errors());
        }
        $valid = $validator->validated();
        if ($this->clients->findById((int)$valid['clientId']) === null) {
            Response::notFound('Referenced client not found');
        }
        $site = $this->sites->create($valid);
        Response::created($site->toArray());
    }

    public function update(array $params, array $data): void
    {
        $id = (int)($params['id'] ?? 0);
        $existing = $this->sites->findById($id);
        if ($existing === null) {
            Response::notFound('Site not found');
        }
        $validator = Validator::make($data, [
            'clientId' => 'nullable|int',
            'name' => 'nullable|string|min:2|max:255',
            'code' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:150',
            'postalCode' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:100',
            'active' => 'nullable|boolean',
        ]);
        if ($validator->fails()) {
            Response::validationError($validator->errors());
        }
        $site = $this->sites->update($id, $validator->validated());
        Response::success($site?->toArray());
    }

    public function destroy(array $params, array $data): void
    {
        $id = (int)($params['id'] ?? 0);
        $existing = $this->sites->findById($id);
        if ($existing === null) {
            Response::notFound('Site not found');
        }
        $this->sites->delete($id);
        Response::noContent();
    }
}
