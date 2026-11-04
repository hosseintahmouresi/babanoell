<?php
declare(strict_types=1);

namespace Babanoell\Auth\Security;

final class InputNormalizer
{
    public function loginIdentifier(string $value): string
    {
        return strtolower(trim($value));
    }
}
