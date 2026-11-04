<?php
declare(strict_types=1);

namespace Babanoell\Auth\Repositories;

use Babanoell\Auth\Models\User;

interface UserRepositoryInterface
{
    public function findById(string $userId): ?User;

    public function findByLoginIdentifier(string $identifier): ?User;
}
