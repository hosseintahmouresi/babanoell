<?php
declare(strict_types=1);

namespace Babanoell\Auth\Repositories;

use Babanoell\Auth\Models\Role;

interface RoleRepositoryInterface
{
    public function findById(string $roleId): ?Role;

    public function findByName(string $name): ?Role;

    /**
     * @return array<int,Role>
     */
    public function findByUserId(string $userId): array;
}
