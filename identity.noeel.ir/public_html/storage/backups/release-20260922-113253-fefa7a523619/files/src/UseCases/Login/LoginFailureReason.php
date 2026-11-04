<?php
declare(strict_types=1);

namespace Babanoell\Auth\UseCases\Login;

enum LoginFailureReason: string
{
    case Disabled = 'disabled';
    case InvalidCredentials = 'invalid_credentials';
    case Locked = 'locked';
    case MissingCredential = 'missing_credential';
    case RateLimited = 'rate_limited';
    case Unavailable = 'unavailable';
}
