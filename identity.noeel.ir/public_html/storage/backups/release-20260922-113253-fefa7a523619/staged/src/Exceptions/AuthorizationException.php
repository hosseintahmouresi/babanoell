<?php
declare(strict_types=1);

namespace Babanoell\Auth\Exceptions;

final class AuthorizationException extends AuthException
{
    public function publicMessage(): string
    {
        return 'Access denied.';
    }
}
