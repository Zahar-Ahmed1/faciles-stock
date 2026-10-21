<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Utils\Response;

class RoleMiddleware
{
    public function hasRole(array $user, array $allowedRoles): void
    {
        $role = (string)($user['role'] ?? '');
        if (in_array('ADMIN', $allowedRoles, true) && $role === 'ADMIN') {
            return;
        }
        if (in_array($role, $allowedRoles, true)) {
            return;
        }
        Response::forbidden('Insufficient permissions');
    }

    public function assertCanManageStockGlobal(array $user): void
    {
        $this->hasRole($user, ['ADMIN', 'MANAGER', 'STOCK_MANAGER']);
    }

    public function assertCanWriteMasterData(array $user): void
    {
        $this->hasRole($user, ['ADMIN', 'MANAGER']);
    }

    public function assertAdmin(array $user): void
    {
        $this->hasRole($user, ['ADMIN']);
    }

    public function assertCanAccessSite(array $user, ?int $siteId): void
    {
        $role = (string)($user['role'] ?? '');
        if ($role === 'ADMIN') {
            return;
        }
        if ($siteId === null) {
            Response::forbidden('Site scope required for STOCK_MANAGER');
        }
        $userSiteId = isset($user['siteId']) && $user['siteId'] !== null ? (int)$user['siteId'] : null;
        if ($userSiteId === null || $userSiteId !== $siteId) {
            Response::forbidden('You can only access your assigned site');
        }
    }
}
