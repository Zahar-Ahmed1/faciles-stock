<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\UserRepository;
use App\Utils\Jwt;
use App\Utils\Response;

class AuthService
{
    public function __construct(
        private readonly UserRepository $users = new UserRepository()
    ) {
    }

    public function login(string $email, string $password): array
    {
        $user = $this->users->findByEmail($email);

        if ($user === null) {
            Response::unauthorized('Invalid email or password');
        }

        if (empty($user['active'])) {
            Response::forbidden('Your account has been disabled. Please contact your administrator.');
        }

        $hash = $user['password_hash'] ?? '';
        if (!password_verify($password, $hash)) {
            Response::unauthorized('Invalid email or password');
        }

        $userId = (int)$user['id'];
        $role = $user['role'];

        $token = Jwt::generate([
            'sub' => $userId,
            'role' => $role,
            'email' => $user['email'],
        ]);

        return [
            'token' => $token,
            'user' => $this->users->toPublicArray($user),
        ];
    }
}
