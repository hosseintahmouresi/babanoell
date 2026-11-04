<?php
declare(strict_types=1);

namespace Babanoell\Auth\Authorization;

interface AuthorizationPolicyInterface
{
    public function decide(AuthorizationContext $context, PermissionCheck $check): AuthorizationDecision;
}
