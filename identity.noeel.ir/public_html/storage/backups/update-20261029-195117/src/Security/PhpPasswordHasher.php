<?php
declare(strict_types=1);

namespace Babanoell\Auth\Security;

use InvalidArgumentException;
use RuntimeException;

final class PhpPasswordHasher implements PasswordHasherInterface
{
    /**
     * @param array<string,int> $options
     */
    public function __construct(
        private readonly string $algorithm = PASSWORD_ARGON2ID,
        private readonly array $options = [
            'memory_cost' => 65536,
            'time_cost' => 4,
            'threads' => 1,
        ]
    ) {
    }

    public function hash(string $plainValue): string
    {
        if ($plainValue === '') {
            throw new InvalidArgumentException('Value to hash must not be empty.');
        }

        $hash = password_hash($plainValue, $this->algorithm, $this->options);

        if ($hash === false) {
            throw new RuntimeException('Password hash operation failed.');
        }

        return $hash;
    }

    public function verify(string $plainValue, string $storedHash): bool
    {
        if ($plainValue === '' || $storedHash === '') {
            return false;
        }

        return password_verify($plainValue, $storedHash);
    }

    public function needsRehash(string $storedHash): bool
    {
        if ($storedHash === '') {
            return true;
        }

        return password_needs_rehash($storedHash, $this->algorithm, $this->options);
    }
}
