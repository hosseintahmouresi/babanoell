<?php
declare(strict_types=1);

namespace Babanoell\Auth\Exceptions;

use RuntimeException;
use Throwable;

class AuthException extends RuntimeException
{
    public function __construct(
        string $message = 'Authentication service error.',
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function publicMessage(): string
    {
        return 'The request could not be processed.';
    }
}
