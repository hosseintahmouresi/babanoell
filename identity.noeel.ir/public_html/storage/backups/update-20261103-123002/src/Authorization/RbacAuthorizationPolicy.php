<?php
declare(strict_types=1);

namespace Babanoell\Auth\Authorization;

use Babanoell\Auth\Repositories\PermissionRepositoryInterface;

final class RbacAuthorizationPolicy implements AuthorizationPolicyInterface
{
    public function __construct(
        private readonly ?PermissionRepositoryInterface $permissions = null,
    ) {
    }

    public function decide(AuthorizationContext $context, PermissionCheck $check): AuthorizationDecision
    {
        if ($context->userId() === null) {
            return AuthorizationDecision::deny('authentication_required', 'Authentication is required.');
        }

        if (in_array($check->permissionName(), $context->permissionNames(), true)) {
            return AuthorizationDecision::allow('Permission is present in the authorization context.');
        }

        if ($this->permissions !== null) {
            $permissionNames = $this->permissions->findPermissionNamesByUserId($context->userId());

            if (in_array($check->permissionName(), $permissionNames, true)) {
                return AuthorizationDecision::allow('Permission is present in the repository result.');
            }
        }

        return AuthorizationDecision::deny('permission_denied', 'The required permission is not available.');
    }
}
