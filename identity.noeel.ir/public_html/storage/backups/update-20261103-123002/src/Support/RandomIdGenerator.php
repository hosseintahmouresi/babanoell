<?php
declare(strict_types=1);

namespace Babanoell\Auth\Support;

final class RandomIdGenerator implements IdGeneratorInterface
{
    public function generate(): string
    {
        // Database identifiers are CHAR(26) across the identity schema.
        // 13 random bytes => 26 lowercase hex characters (104 bits of entropy).
        return bin2hex(random_bytes(13));
    }
}
