<?php
declare(strict_types=1);

use Babanoell\Auth\Repositories\AuditLogRepositoryInterface;
use Babanoell\Auth\Repositories\PermissionRepositoryInterface;
use Babanoell\Auth\Repositories\RoleRepositoryInterface;
use Babanoell\Auth\Repositories\SessionRepositoryInterface;
use Babanoell\Auth\Repositories\UserRepositoryInterface;
use Babanoell\Auth\Security\PasswordHasherInterface;
use Babanoell\Auth\Security\TokenHasherInterface;
use Babanoell\Auth\Support\ClockInterface;
use Babanoell\Auth\Support\IdGeneratorInterface;
use Babanoell\Auth\Support\RandomIdGenerator;
use Babanoell\Auth\Support\SystemClock;
use Babanoell\Auth\Tests\Assertions;
use Babanoell\Auth\Tests\TestCase;

return new class extends TestCase {
    public function testCoreInterfacesExist(): void
    {
        foreach ([
            UserRepositoryInterface::class,
            RoleRepositoryInterface::class,
            PermissionRepositoryInterface::class,
            SessionRepositoryInterface::class,
            AuditLogRepositoryInterface::class,
            PasswordHasherInterface::class,
            TokenHasherInterface::class,
            ClockInterface::class,
            IdGeneratorInterface::class,
        ] as $interfaceName) {
            Assertions::true(interface_exists($interfaceName), "Missing interface {$interfaceName}");
        }
    }

    public function testSystemClockReturnsUtcTime(): void
    {
        $clock = new SystemClock();

        Assertions::same('UTC', $clock->now()->getTimezone()->getName());
    }

    public function testRandomIdGeneratorReturnsHexIdentifier(): void
    {
        $generator = new RandomIdGenerator();
        $id = $generator->generate();

        Assertions::matches('/^[a-f0-9]{26}$/', $id);
    }
};
