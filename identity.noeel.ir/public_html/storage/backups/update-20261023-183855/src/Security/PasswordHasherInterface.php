<?php
declare(strict_types=1);

namespace Babanoell\Auth\Security;

interface PasswordHasherInterface
{
    public function hash(string $plainValue): string;

    public function verify(string $plainValue, string $storedHash): bool;

    public function needsRehash(string $storedHash): bool;
}
