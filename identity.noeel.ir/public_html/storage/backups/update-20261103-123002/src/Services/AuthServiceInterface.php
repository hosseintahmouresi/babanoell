<?php
declare(strict_types=1);

namespace Babanoell\Auth\Services;

interface AuthServiceInterface
{
    public function authenticate(string $identifier, string $plainPassword): never;
}
