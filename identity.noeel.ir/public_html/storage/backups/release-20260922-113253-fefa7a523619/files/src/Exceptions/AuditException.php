<?php
declare(strict_types=1);

namespace Babanoell\Auth\Exceptions;

final class AuditException extends AuthException
{
    public function publicMessage(): string
    {
        return 'Audit event could not be recorded.';
    }
}
