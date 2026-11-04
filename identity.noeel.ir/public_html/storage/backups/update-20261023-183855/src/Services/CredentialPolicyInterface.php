<?php
declare(strict_types=1);

namespace Babanoell\Auth\Services;

interface CredentialPolicyInterface
{
    public function passwordMeetsPolicy(string $plainPassword): bool;
}
