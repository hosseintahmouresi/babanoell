<?php
declare(strict_types=1);

use Babanoell\Auth\Authorization\AuthorizationContext;
use Babanoell\Auth\Authorization\AuthorizationDecision;
use Babanoell\Auth\Authorization\InactiveAuthorizationPolicy;
use Babanoell\Auth\Authorization\PermissionCheck;
use Babanoell\Auth\Authorization\RbacAuthorizationPolicy;
use Babanoell\Auth\Models\Permission;
use Babanoell\Auth\Repositories\PermissionRepositoryInterface;
use Babanoell\Auth\Tests\Assertions;
use Babanoell\Auth\Tests\TestCase;

return new class extends TestCase {
    public function testAuthorizationDecisionDefaultsDeny(): void
    {
        $decision = AuthorizationDecision::defaultDeny();

        Assertions::false($decision->allowed());
        Assertions::same('authorization_inactive', $decision->code());
    }

    public function testInactiveAuthorizationPolicyFailsClosed(): void
    {
        $policy = new InactiveAuthorizationPolicy();
        $context = new AuthorizationContext('usr_local');
        $check = new PermissionCheck('auth.roles.read');

        Assertions::false($policy->decide($context, $check)->allowed());
        Assertions::false($policy->decideRecordAccess($context, $check)->allowed());
    }

    public function testRbacPolicyAllowsPermissionFromContext(): void
    {
        $policy = new RbacAuthorizationPolicy();
        $context = new AuthorizationContext('usr_local', ['operations_expert'], ['auth.roles.read']);

        $decision = $policy->decide($context, new PermissionCheck('auth.roles.read'));

        Assertions::true($decision->allowed());
        Assertions::same('allowed', $decision->code());
    }

    public function testRbacPolicyUsesRepositoryWhenAvailable(): void
    {
        $repository = new class implements PermissionRepositoryInterface {
            public function findById(string $permissionId): ?Permission
            {
                return null;
            }

            public function findByName(string $name): ?Permission
            {
                return null;
            }

            public function findByRoleId(string $roleId): array
            {
                return [];
            }

            public function findPermissionNamesByUserId(string $userId): array
            {
                return $userId === 'usr_local' ? ['auth.permissions.read'] : [];
            }
        };

        $policy = new RbacAuthorizationPolicy($repository);
        $decision = $policy->decide(new AuthorizationContext('usr_local'), new PermissionCheck('auth.permissions.read'));

        Assertions::true($decision->allowed());
    }

    public function testRbacPolicyDeniesAnonymousContext(): void
    {
        $policy = new RbacAuthorizationPolicy();
        $decision = $policy->decide(AuthorizationContext::anonymous(), new PermissionCheck('auth.roles.read'));

        Assertions::false($decision->allowed());
        Assertions::same('authentication_required', $decision->code());
    }

    public function testRbacPolicyDeniesMissingPermission(): void
    {
        $policy = new RbacAuthorizationPolicy();
        $context = new AuthorizationContext('usr_local', ['operations_expert'], ['auth.users.read']);
        $decision = $policy->decide($context, new PermissionCheck('auth.roles.read'));

        Assertions::false($decision->allowed());
        Assertions::same('permission_denied', $decision->code());
    }
};
