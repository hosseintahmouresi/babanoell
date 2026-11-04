<?php
declare(strict_types=1);

namespace Babanoell\Auth\Repositories\Pdo;

use Babanoell\Auth\Models\Role;
use Babanoell\Auth\Repositories\RoleRepositoryInterface;
use PDO;

final class PdoRoleRepository implements RoleRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function findById(string $roleId): ?Role
    {
        $statement = $this->pdo->prepare('SELECT * FROM roles WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $roleId]);

        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : Role::fromRow($row);
    }

    public function findByName(string $name): ?Role
    {
        $statement = $this->pdo->prepare('SELECT * FROM roles WHERE name = :name LIMIT 1');
        $statement->execute(['name' => $name]);

        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : Role::fromRow($row);
    }

    public function findByUserId(string $userId): array
    {
        $statement = $this->pdo->prepare(<<<'SQL'
SELECT roles.*
FROM roles
INNER JOIN user_roles ON user_roles.role_id = roles.id
WHERE user_roles.user_id = :user_id
  AND (user_roles.expires_at IS NULL OR user_roles.expires_at > UTC_TIMESTAMP(6))
ORDER BY roles.name ASC
SQL);
        $statement->execute(['user_id' => $userId]);

        return array_map(
            static fn (array $row): Role => Role::fromRow($row),
            $statement->fetchAll(PDO::FETCH_ASSOC)
        );
    }
}
