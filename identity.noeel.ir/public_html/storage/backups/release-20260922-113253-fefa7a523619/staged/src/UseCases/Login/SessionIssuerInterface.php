<?php
declare(strict_types=1);

namespace Babanoell\Auth\UseCases\Login;

use Babanoell\Auth\Models\User;

interface SessionIssuerInterface
{
    public function issue(User $user, LoginSecurityContext $context): SessionIssueResult;
}
