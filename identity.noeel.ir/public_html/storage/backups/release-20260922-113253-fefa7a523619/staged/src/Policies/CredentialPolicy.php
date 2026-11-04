<?php
declare(strict_types=1);

namespace Babanoell\Auth\Policies;

use Babanoell\Auth\Services\CredentialPolicyInterface;

final class CredentialPolicy implements CredentialPolicyInterface
{
    public function __construct(
        private readonly bool $active = false,
        private readonly int $minimumLength = 12,
    ) {
    }

    public function passwordMeetsPolicy(string $plainPassword): bool
    {
        if (!$this->active) {
            return false;
        }

        return strlen($plainPassword) >= $this->minimumLength;
    }
}
