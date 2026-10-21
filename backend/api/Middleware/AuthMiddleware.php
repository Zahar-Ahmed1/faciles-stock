<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Config\Config;
use App\Repositories\UserRepository;
use App\Utils\Jwt;
use App\Utils\Response;

class AuthMiddleware
{
    public function __construct(
        private readonly UserRepository $users = new UserRepository()
    ) {
    }

    public function authenticate(): array
    {
        $token = Jwt::extractFromHeader();
        if ($token === null) {
            Response::unauthorized('Authentication required');
        }

        $decoded = Jwt::decode($token);
        if ($decoded === null) {
            Response::unauthorized('Invalid or expired token');
        }

        $userId = (int)($decoded->sub ?? 0);
        if ($userId <= 0) {
            Response::unauthorized('Invalid token payload');
        }

        $user = $this->users->findById($userId);
        if ($user === null) {
            Response::unauthorized('User no longer exists');
        }

        $public = $this->users->toPublicArray($user);
        if (empty($public['active'])) {
            Response::forbidden('Your account has been disabled');
        }

        return $public;
    }

    public function requireRole(string ...$roles): array
    {
        $user = $this->authenticate();
        if (!in_array($user['role'], $roles, true)) {
            Response::forbidden('Insufficient permissions');
        }
        return $user;
    }
}
