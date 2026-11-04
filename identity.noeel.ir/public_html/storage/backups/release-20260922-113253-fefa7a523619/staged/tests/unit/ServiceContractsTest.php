<?php
declare(strict_types=1);

use Babanoell\Auth\Services\AuditServiceInterface;
use Babanoell\Auth\Services\AuthServiceInterface;
use Babanoell\Auth\Services\AuthorizationServiceInterface;
use Babanoell\Auth\Services\CredentialPolicyInterface;
use Babanoell\Auth\Services\InactiveAuthService;
use Babanoell\Auth\Services\SessionPolicyInterface;
use Babanoell\Auth\Tests\Assertions;
use Babanoell\Auth\Tests\TestCase;

return new class extends TestCase {
    public function testServiceContractsExist(): void
    {
        foreach ([
            AuthServiceInterface::class,
            AuthorizationServiceInterface::class,
            AuditServiceInterface::class,
            CredentialPolicyInterface::class,
            SessionPolicyInterface::class,
        ] as $interfaceName) {
            Assertions::true(interface_exists($interfaceName), "Missing interface {$interfaceName}");
        }
    }

    public function testInactiveAuthServiceDoesNotAuthenticate(): void
    {
        $service = new InactiveAuthService();

        Assertions::throws(LogicException::class, static function () use ($service): void {
            $service->authenticate('local@example.test', 'local-check-value');
        });
    }
};
