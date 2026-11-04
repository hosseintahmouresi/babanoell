<?php
declare(strict_types=1);

namespace Babanoell\Auth\Repositories\Pdo;

use Babanoell\Auth\Models\Permission;
use Babanoell\Auth\Repositories\PermissionRepositoryInterface;
use PDO;

final class PdoPermissionRepository implements PermissionRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function findById(string $permissionId): ?Permission
    {
        $statement = $this->pdo->prepare('SELECT * FROM permissions WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $permissionId]);

        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : Permission::fromRow($row);
    }

    public function findByName(string $name): ?Permission
    {
        $statement = $this->pdo->prepare('SELECT * FROM permissions WHERE name = :name LIMIT 1');
        $statement->execute(['name' => $name]);

        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : Permission::fromRow($row);
    }

    public function findByRoleId(string $roleId): array
    {
        $statement = $this->pdo->prepare(<<<'SQL'
SELECT permissions.*
FROM permissions
INNER JOIN role_permissions ON role_permissions.permission_id = permissions.id
WHERE role_permissions.role_id = :role_id
ORDER BY permissions.name ASC
SQL);
        $statement->execute(['role_id' => $roleId]);

        return array_map(
            static fn (array $row): Permission => Permission::fromRow($row),
            $statement->fetchAll(PDO::FETCH_ASSOC)
        );
    }

    public function findPermissionNamesByUserId(string $userId): array
    {
        $statement = $this->pdo->prepare(<<<'SQL'
SELECT DISTINCT permissions.name
FROM permissions
INNER JOIN role_permissions ON role_permissions.permission_id = permissions.id
INNER JOIN roles ON roles.id = role_permissions.role_id
INNER JOIN user_roles ON user_roles.role_id = roles.id
WHERE user_roles.user_id = :user_id
  AND (user_roles.expires_at IS NULL OR user_roles.expires_at > UTC_TIMESTAMP(6))
ORDER BY permissions.name ASC
SQL);
        $statement->execute(['user_id' => $userId]);

        return array_map(
            static fn (array $row): string => (string) $row['name'],
            $statement->fetchAll(PDO::FETCH_ASSOC)
        );
    }
}
