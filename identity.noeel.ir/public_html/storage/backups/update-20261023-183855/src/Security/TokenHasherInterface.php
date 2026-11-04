<?php
declare(strict_types=1);

namespace Babanoell\Auth\Security;

interface TokenHasherInterface
{
    public function hash(string $token): string;

    public function verify(string $token, string $storedHash): bool;
}
