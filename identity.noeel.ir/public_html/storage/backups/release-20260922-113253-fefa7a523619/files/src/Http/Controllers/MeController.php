<?php
declare(strict_types=1);

namespace Babanoell\Auth\Http\Controllers;

use Babanoell\Auth\Http\JsonResponse;
use Babanoell\Auth\Http\Request;
use Babanoell\Auth\Http\ResponseMapper;
use Babanoell\Auth\UseCases\Login\LoginSecurityContext;
use Babanoell\Auth\UseCases\Me\InactiveMeUseCase;
use Babanoell\Auth\UseCases\Me\MeRequest;
use Babanoell\Auth\UseCases\Me\MeRequestValidator;
use Babanoell\Auth\UseCases\Me\MeUseCaseInterface;

final class MeController
{
    public function __construct(
        private readonly MeUseCaseInterface $meUseCase = new InactiveMeUseCase(),
        private readonly MeRequestValidator $meRequestValidator = new MeRequestValidator(),
    ) {
    }

    public function show(Request $request): JsonResponse
    {
        $validation = $this->meRequestValidator->validate([
            'request_id' => $request->header('X-Request-Id'),
        ]);

        if (!$validation->isValid()) {
            return ResponseMapper::validation($validation);
        }

        $result = $this->meUseCase->handle(new MeRequest(
            bearerCredential: $this->bearerToken($request),
            requestId: $request->header('X-Request-Id'),
            securityContext: new LoginSecurityContext(
                clientSource: $this->clientSource($request),
                userAgent: $request->header('User-Agent'),
                requestId: $request->header('X-Request-Id'),
            ),
        ));

        if (!$result->active()) {
            return ResponseMapper::fromErrorPayload($result->statusCode(), $result->toErrorPayload());
        }

        return new JsonResponse($result->statusCode(), $result->toPayload());
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

    private function clientSource(Request $request): ?string
    {
        return $request->clientIp();
    }
}
