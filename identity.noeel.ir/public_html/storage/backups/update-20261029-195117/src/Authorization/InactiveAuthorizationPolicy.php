<?php
declare(strict_types=1);

namespace Babanoell\Auth\Authorization;

final class InactiveAuthorizationPolicy implements AuthorizationPolicyInterface, RecordAccessPolicyInterface
{
    public function decide(AuthorizationContext $context, PermissionCheck $check): AuthorizationDecision
    {
        return AuthorizationDecision::defaultDeny();
    }

    public function decideRecordAccess(AuthorizationContext $context, PermissionCheck $check): AuthorizationDecision
    {
        return AuthorizationDecision::defaultDeny();
    }
}
