<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\SiteRepository;
use App\Repositories\UserRepository;
use App\Utils\Response;
use App\Utils\Validator;

class UserController
{
    public function __construct(
        private readonly UserRepository $users = new UserRepository(),
        private readonly SiteRepository $sites = new SiteRepository()
    ) {
    }

    public function index(array $params, array $data): void
    {
        $role = isset($data['role']) && is_string($data['role']) ? $data['role'] : null;
        $siteId = isset($data['siteId']) ? (int)$data['siteId'] : null;
        $list = $this->users->findAll($role, $siteId);
        Response::success(array_map(static fn($u) => $u->toArray(), $list));
    }

    public function show(array $params, array $data): void
    {
        $id = (int)($params['id'] ?? 0);
        $user = $this->users->findById($id);
        if ($user === null) {
            Response::notFound('User not found');
        }
        Response::success($user->toArray());
    }

    public function store(array $params, array $data): void
    {
        $validator = Validator::make($data, [
            'firstName' => 'required|string|min:2|max:100',
            'lastName' => 'required|string|min:2|max:100',
            'email' => 'required|email|max:255',
            'password' => 'required|string|min:6|max:100',
            'role' => 'required|string|in:ADMIN,MANAGER,STOCK_MANAGER',
            'siteId' => 'nullable|int',
            'active' => 'nullable|boolean',
        ]);
        if ($validator->fails()) {
            Response::validationError($validator->errors());
        }
        $valid = $validator->validated();
        if ($this->users->findByEmail($valid['email']) !== null) {
            Response::conflict('User with this email already exists');
        }
        if (!empty($valid['siteId']) && $this->sites->findById((int)$valid['siteId']) === null) {
            Response::notFound('Referenced site not found');
        }
        $hash = password_hash($valid['password'], PASSWORD_DEFAULT, ['cost' => 12]);
        $user = $this->users->create($valid, $hash);
        Response::created($user->toArray());
    }

    public function update(array $params, array $data): void
    {
        $id = (int)($params['id'] ?? 0);
        $existing = $this->users->findById($id);
        if ($existing === null) {
            Response::notFound('User not found');
        }
        $validator = Validator::make($data, [
            'firstName' => 'nullable|string|min:2|max:100',
            'lastName' => 'nullable|string|min:2|max:100',
            'email' => 'nullable|email|max:255',
            'password' => 'nullable|string|min:6|max:100',
            'role' => 'nullable|string|in:ADMIN,MANAGER,STOCK_MANAGER',
            'siteId' => 'nullable|int',
            'active' => 'nullable|boolean',
        ]);
        if ($validator->fails()) {
            Response::validationError($validator->errors());
        }
        $valid = $validator->validated();
        if (!empty($valid['siteId']) && $this->sites->findById((int)$valid['siteId']) === null) {
            Response::notFound('Referenced site not found');
        }
        if (!empty($valid['email']) && $valid['email'] !== $existing->email) {
            if ($this->users->findByEmail($valid['email']) !== null) {
                Response::conflict('User with this email already exists');
            }
        }
        if (!empty($valid['password'])) {
            $hash = password_hash($valid['password'], PASSWORD_DEFAULT, ['cost' => 12]);
            $pdo = \App\Config\Database::getConnection();
            $stmt = $pdo->prepare('UPDATE users SET password_hash = :h WHERE id = :id');
            $stmt->execute(['h' => $hash, 'id' => $id]);
            unset($valid['password']);
        }
        $user = $this->users->update($id, $valid);
        Response::success($user?->toArray());
    }

    public function destroy(array $params, array $data): void
    {
        $id = (int)($params['id'] ?? 0);
        $existing = $this->users->findById($id);
        if ($existing === null) {
            Response::notFound('User not found');
        }
        try {
            $this->users->delete($id);
        } catch (\PDOException $e) {
            $code = (int)($e->errorInfo[1] ?? 0);
            if ($code === 1451) {
                Response::conflict('Ce compte a déjà des mouvements ou des livraisons. Il ne peut pas être supprimé.');
            }
            throw $e;
        }
        Response::noContent();
    }
}
