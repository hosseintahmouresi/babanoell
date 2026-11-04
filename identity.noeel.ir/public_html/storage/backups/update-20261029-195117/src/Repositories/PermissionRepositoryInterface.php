<?php
declare(strict_types=1);

namespace Babanoell\Auth\Repositories;

use Babanoell\Auth\Models\Permission;

interface PermissionRepositoryInterface
{
    public function findById(string $permissionId): ?Permission;

    public function findByName(string $name): ?Permission;

    /**
     * @return array<int,Permission>
     */
    public function findByRoleId(string $roleId): array;

    /**
     * @return array<int,string>
     */
    public function findPermissionNamesByUserId(string $userId): array;
}
