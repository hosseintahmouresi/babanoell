<?php
declare(strict_types=1);

namespace Babanoell\Auth\Security;

final class ConstantTimeComparator
{
    public function equals(string $knownValue, string $userValue): bool
    {
        if ($knownValue === '' || $userValue === '') {
            return false;
        }

        return hash_equals($knownValue, $userValue);
    }
}
