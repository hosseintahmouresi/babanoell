<?php
declare(strict_types=1);

namespace Babanoell\Auth\Services;

interface AuthorizationServiceInterface
{
    public function userHasPermission(string $userId, string $permissionName): bool;
}
