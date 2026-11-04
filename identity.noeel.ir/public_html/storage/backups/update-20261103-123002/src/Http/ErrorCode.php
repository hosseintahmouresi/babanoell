<?php
declare(strict_types=1);

namespace Babanoell\Auth\Http;

final class ErrorCode
{
    public const INTERNAL_ERROR = 'internal_error';
    public const INVALID_REQUEST = 'invalid_request';
    public const METHOD_NOT_ALLOWED = 'method_not_allowed';
    public const NOT_FOUND = 'not_found';
    public const NOT_IMPLEMENTED = 'not_implemented';
    public const UNAUTHORIZED = 'unauthorized';
    public const FORBIDDEN = 'forbidden';
    public const VALIDATION_ERROR = 'validation_failed';
}
