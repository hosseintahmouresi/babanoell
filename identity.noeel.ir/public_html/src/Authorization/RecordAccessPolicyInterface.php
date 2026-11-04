<?php
declare(strict_types=1);

namespace Babanoell\Auth\Authorization;

interface RecordAccessPolicyInterface
{
    public function decideRecordAccess(AuthorizationContext $context, PermissionCheck $check): AuthorizationDecision;
}
