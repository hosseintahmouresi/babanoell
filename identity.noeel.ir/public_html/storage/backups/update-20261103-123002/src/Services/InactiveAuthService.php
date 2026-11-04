<?php
declare(strict_types=1);

namespace Babanoell\Auth\Services;

use LogicException;

final class InactiveAuthService implements AuthServiceInterface
{
    public function authenticate(string $identifier, string $plainPassword): never
    {
        throw new LogicException('Auth workflow is not implemented yet.');
    }
}
