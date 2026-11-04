<?php
declare(strict_types=1);

namespace Babanoell\Auth\Security;

use InvalidArgumentException;

final class HmacTokenHasher implements TokenHasherInterface
{
    public function __construct(
        private readonly string $key,
        private readonly string $algorithm = 'sha256'
    ) {
        if ($this->key === '') {
            throw new InvalidArgumentException('Token hash key must not be empty.');
        }

        if (!in_array($this->algorithm, hash_hmac_algos(), true)) {
            throw new InvalidArgumentException('Unsupported token hash algorithm.');
        }
    }

    public function hash(string $token): string
    {
        if ($token === '') {
            throw new InvalidArgumentException('Token value must not be empty.');
        }

        return hash_hmac($this->algorithm, $token, $this->key);
    }

    public function verify(string $token, string $storedHash): bool
    {
        if ($token === '' || $storedHash === '') {
            return false;
        }

        return hash_equals($storedHash, $this->hash($token));
    }
}
