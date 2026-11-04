<?php
declare(strict_types=1);

namespace Babanoell\Auth\Security;

use InvalidArgumentException;

final class SecureTokenGenerator
{
    public function generate(int $bytes = 32): string
    {
        if ($bytes < 16) {
            throw new InvalidArgumentException('Token size must be at least 16 bytes.');
        }

        return rtrim(strtr(base64_encode(random_bytes($bytes)), '+/', '-_'), '=');
    }
}
