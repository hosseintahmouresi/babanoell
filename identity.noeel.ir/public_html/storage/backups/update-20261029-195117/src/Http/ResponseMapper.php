<?php
declare(strict_types=1);

namespace Babanoell\Auth\Http;

use Babanoell\Auth\Validation\ValidationError;
use Babanoell\Auth\Validation\ValidationResult;

final class ResponseMapper
{
    public static function error(int $statusCode, string $code, string $message): JsonResponse
    {
        return self::apiError(new ApiError($statusCode, $code, $message));
    }

    public static function apiError(ApiError $error): JsonResponse
    {
        return new JsonResponse($error->statusCode(), $error->toPayload());
    }

    public static function validation(ValidationResult $result): JsonResponse
    {
        $details = $result->isValid()
            ? []
            : $result->toArray();

        return self::apiError(new ApiError(
            422,
            ErrorCode::VALIDATION_ERROR,
            'The request body contains invalid fields.',
            $details,
        ));
    }

    public static function malformedJson(): JsonResponse
    {
        return self::validation(ValidationResult::invalid([
            new ValidationError('body', 'invalid_json', 'Request body must be a valid JSON object.'),
        ]));
    }

    /**
     * @param array{error:array{code:string,message:string}} $payload
     */
    public static function fromErrorPayload(int $statusCode, array $payload): JsonResponse
    {
        return new JsonResponse($statusCode, $payload);
    }

    public static function notImplemented(): JsonResponse
    {
        return self::error(501, ErrorCode::NOT_IMPLEMENTED, 'This endpoint is not active yet.');
    }

    public static function unauthorized(): JsonResponse
    {
        return self::error(401, ErrorCode::UNAUTHORIZED, 'Authentication is required.');
    }

    public static function forbidden(): JsonResponse
    {
        return self::error(403, ErrorCode::FORBIDDEN, 'Access is forbidden.');
    }

    public static function notFound(): JsonResponse
    {
        return self::error(404, ErrorCode::NOT_FOUND, 'The requested endpoint was not found.');
    }

    public static function methodNotAllowed(string $allowHeader): Response
    {
        return self::error(
            405,
            ErrorCode::METHOD_NOT_ALLOWED,
            'The requested method is not allowed for this endpoint.',
        )->withHeader('Allow', $allowHeader);
    }

    public static function invalidRequest(): JsonResponse
    {
        return self::error(400, ErrorCode::INVALID_REQUEST, 'Invalid request.');
    }

    public static function internalError(): JsonResponse
    {
        return self::error(500, ErrorCode::INTERNAL_ERROR, 'Internal server error.');
    }
}
