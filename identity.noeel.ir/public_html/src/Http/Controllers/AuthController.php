<?php
declare(strict_types=1);

namespace Babanoell\Auth\Http\Controllers;

use Babanoell\Auth\Http\JsonResponse;
use Babanoell\Auth\Http\Request;
use Babanoell\Auth\Http\ResponseMapper;
use Babanoell\Auth\UseCases\Login\InactiveLoginUseCase;
use Babanoell\Auth\UseCases\Login\LoginRequest;
use Babanoell\Auth\UseCases\Login\LoginRequestValidator;
use Babanoell\Auth\UseCases\Login\LoginResult;
use Babanoell\Auth\UseCases\Login\LoginSecurityContext;
use Babanoell\Auth\UseCases\Login\LoginUseCaseInterface;
use Babanoell\Auth\UseCases\Logout\InactiveLogoutUseCase;
use Babanoell\Auth\UseCases\Logout\LogoutRequest;
use Babanoell\Auth\UseCases\Logout\LogoutRequestValidator;
use Babanoell\Auth\UseCases\Logout\LogoutResult;
use Babanoell\Auth\UseCases\Logout\LogoutUseCaseInterface;
use Babanoell\Auth\UseCases\Refresh\InactiveRefreshUseCase;
use Babanoell\Auth\UseCases\Refresh\RefreshRequest;
use Babanoell\Auth\UseCases\Refresh\RefreshRequestValidator;
use Babanoell\Auth\UseCases\Refresh\RefreshResult;
use Babanoell\Auth\UseCases\Refresh\RefreshUseCaseInterface;
use InvalidArgumentException;

final class AuthController
{
    public function __construct(
        private readonly LoginUseCaseInterface $loginUseCase = new InactiveLoginUseCase(),
        private readonly LogoutUseCaseInterface $logoutUseCase = new InactiveLogoutUseCase(),
        private readonly RefreshUseCaseInterface $refreshUseCase = new InactiveRefreshUseCase(),
        private readonly LoginRequestValidator $loginRequestValidator = new LoginRequestValidator(),
        private readonly LogoutRequestValidator $logoutRequestValidator = new LogoutRequestValidator(),
        private readonly RefreshRequestValidator $refreshRequestValidator = new RefreshRequestValidator(),
    ) {
    }

    public function login(Request $request): JsonResponse
    {
        $payload = $this->payload($request);

        if ($payload instanceof JsonResponse) {
            return $payload;
        }

        $validation = $this->loginRequestValidator->validate($payload);

        if (!$validation->isValid()) {
            return ResponseMapper::validation($validation);
        }

        $result = $this->loginUseCase->handle(LoginRequest::fromPayload(
            $payload,
            $request->header('X-Request-Id'),
            $this->securityContext($request),
        ));

        return $this->loginResponse($result);
    }

    public function logout(Request $request): JsonResponse
    {
        $payload = $this->payload($request);

        if ($payload instanceof JsonResponse) {
            return $payload;
        }

        $validation = $this->logoutRequestValidator->validate($payload);

        if (!$validation->isValid()) {
            return ResponseMapper::validation($validation);
        }

        $result = $this->logoutUseCase->handle(LogoutRequest::fromPayload(
            $payload,
            $this->bearerToken($request),
            $request->header('X-Request-Id'),
            $this->securityContext($request),
        ));

        return $this->resultResponse($result->active(), $result->statusCode(), $result->toPayload());
    }

    public function refresh(Request $request): JsonResponse
    {
        $payload = $this->payload($request);

        if ($payload instanceof JsonResponse) {
            return $payload;
        }

        $validation = $this->refreshRequestValidator->validate($payload);

        if (!$validation->isValid()) {
            return ResponseMapper::validation($validation);
        }

        $result = $this->refreshUseCase->handle(RefreshRequest::fromPayload(
            $payload,
            $request->header('X-Request-Id'),
            $this->securityContext($request),
        ));

        return $this->resultResponse($result->active(), $result->statusCode(), $result->toPayload());
    }

    /**
     * @return array<string,mixed>|JsonResponse
     */
    private function payload(Request $request): array|JsonResponse
    {
        try {
            return $request->json();
        } catch (InvalidArgumentException) {
            return ResponseMapper::malformedJson();
        }
    }

    private function securityContext(Request $request): LoginSecurityContext
    {
        return new LoginSecurityContext(
            clientSource: $this->clientSource($request),
            userAgent: $request->header('User-Agent'),
            requestId: $request->header('X-Request-Id'),
        );
    }

    private function clientSource(Request $request): ?string
    {
        $forwardedFor = $request->header('X-Forwarded-For');

        if ($forwardedFor !== null && trim($forwardedFor) !== '') {
            return trim(explode(',', $forwardedFor)[0]);
        }

        return $request->header('X-Real-IP');
    }

    private function bearerToken(Request $request): ?string
    {
        $header = $request->header('Authorization');

        if ($header === null || trim($header) === '') {
            return null;
        }

        if (preg_match('/^Bearer\s+(\S+)$/i', trim($header), $matches) !== 1) {
            return null;
        }

        return $matches[1];
    }

    private function loginResponse(LoginResult $result): JsonResponse
    {
        if (!$result->active()) {
            return ResponseMapper::fromErrorPayload($result->statusCode(), $result->toErrorPayload());
        }

        $session = $result->data()['session'] ?? [];
        $publicSession = [];

        if (is_array($session)) {
            foreach (['session_credential', 'refresh_credential', 'access_expires_at', 'refresh_expires_at'] as $field) {
                if (isset($session[$field]) && is_string($session[$field]) && $session[$field] !== '') {
                    $publicSession[$field] = $session[$field];
                }
            }
        }

        return new JsonResponse($result->statusCode(), [
            'data' => [
                'session' => $publicSession,
            ],
        ]);
    }

    /**
     * @param array<string,mixed> $payload
     */
    private function resultResponse(bool $active, int $statusCode, array $payload): JsonResponse
    {
        if (!$active) {
            return ResponseMapper::fromErrorPayload($statusCode, $payload);
        }

        return new JsonResponse($statusCode, $payload);
    }
}
