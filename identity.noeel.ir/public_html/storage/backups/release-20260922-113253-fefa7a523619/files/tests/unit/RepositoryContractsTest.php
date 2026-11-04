<?php
declare(strict_types=1);

use Babanoell\Auth\Models\Permission;
use Babanoell\Auth\Models\Role;
use Babanoell\Auth\Models\SessionRecord;
use Babanoell\Auth\Models\User;
use Babanoell\Auth\Repositories\Pdo\PdoAuditLogRepository;
use Babanoell\Auth\Repositories\Pdo\PdoPermissionRepository;
use Babanoell\Auth\Repositories\Pdo\PdoRoleRepository;
use Babanoell\Auth\Repositories\Pdo\PdoSessionRepository;
use Babanoell\Auth\Repositories\Pdo\PdoUserRepository;
use Babanoell\Auth\Repositories\AuditLogRepositoryInterface;
use Babanoell\Auth\Repositories\PermissionRepositoryInterface;
use Babanoell\Auth\Repositories\RoleRepositoryInterface;
use Babanoell\Auth\Repositories\SessionRepositoryInterface;
use Babanoell\Auth\Repositories\UserRepositoryInterface;
use Babanoell\Auth\Tests\Assertions;
use Babanoell\Auth\Tests\TestCase;

return new class extends TestCase {
    public function testPdoRepositoriesCanBeConstructed(): void
    {
        $pdo = new class extends PDO {
            public function __construct()
            {
            }
        };

        foreach ([
            new PdoUserRepository($pdo),
            new PdoRoleRepository($pdo),
            new PdoPermissionRepository($pdo),
            new PdoSessionRepository($pdo),
            new PdoAuditLogRepository($pdo),
        ] as $repository) {
            Assertions::true(is_object($repository));
        }
    }

    public function testPdoRepositoriesImplementContracts(): void
    {
        $expected = [
            PdoUserRepository::class => UserRepositoryInterface::class,
            PdoRoleRepository::class => RoleRepositoryInterface::class,
            PdoPermissionRepository::class => PermissionRepositoryInterface::class,
            PdoSessionRepository::class => SessionRepositoryInterface::class,
            PdoAuditLogRepository::class => AuditLogRepositoryInterface::class,
        ];

        foreach ($expected as $className => $interfaceName) {
            Assertions::true(is_subclass_of($className, $interfaceName), "{$className} must implement {$interfaceName}");
        }
    }

    public function testRepositoryReturnTypesAreModelBased(): void
    {
        $expectations = [
            [UserRepositoryInterface::class, 'findById', '?' . User::class],
            [RoleRepositoryInterface::class, 'findById', '?' . Role::class],
            [PermissionRepositoryInterface::class, 'findById', '?' . Permission::class],
            [SessionRepositoryInterface::class, 'findActiveById', '?' . SessionRecord::class],
        ];

        foreach ($expectations as [$className, $methodName, $expectedType]) {
            $method = new ReflectionMethod($className, $methodName);
            $returnType = $method->getReturnType();
            Assertions::same($expectedType, ($returnType->allowsNull() ? '?' : '') . $returnType->getName());
        }
    }

    public function testPdoRepositoryQueriesUsePreparedStatements(): void
    {
        foreach ([
            PdoUserRepository::class,
            PdoRoleRepository::class,
            PdoPermissionRepository::class,
            PdoSessionRepository::class,
            PdoAuditLogRepository::class,
        ] as $className) {
            $source = file_get_contents((new ReflectionClass($className))->getFileName());
            Assertions::contains('->prepare(', $source, "{$className} must use prepared statements.");
        }
    }
};
