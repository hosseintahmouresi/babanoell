<?php
declare(strict_types=1);

use Babanoell\Auth\Http\JsonResponse;
use Babanoell\Auth\Http\Middleware\AuthenticationMiddleware;
use Babanoell\Auth\Http\Middleware\AuthorizationMiddleware;
use Babanoell\Auth\Http\Middleware\RequestIdMiddleware;
use Babanoell\Auth\Http\Middleware\SecurityHeadersMiddleware;
use Babanoell\Auth\Http\Request;
use Babanoell\Auth\Http\Response;
use Babanoell\Auth\Policies\CredentialPolicy;
use Babanoell\Auth\Policies\SessionPolicy;
use Babanoell\Auth\Tests\Assertions;
use Babanoell\Auth\Tests\TestCase;

return new class extends TestCase {
    public function testCredentialPolicyFailsClosedByDefault(): void
    {
        $policy = new CredentialPolicy();

        Assertions::false($policy->passwordMeetsPolicy('local-long-placeholder'));
    }

    public function testSessionPolicyFailsClosedByDefault(): void
    {
        $policy = new SessionPolicy();

        Assertions::same(0, $policy->accessTokenTtlSeconds());
        Assertions::same(0, $policy->refreshTokenTtlSeconds());
    }

    public function testRequestIdMiddlewareAddsGeneratedRequestId(): void
    {
        $middleware = new RequestIdMiddleware(static fn (): string => 'req_local_123456');
        $response = $middleware->process(new Request('GET', '/health'), $this->okHandler());

        Assertions::same('req_local_123456', $response->headers()['X-Request-Id']);
    }

    public function testRequestIdMiddlewarePreservesValidIncomingRequestId(): void
    {
        $middleware = new RequestIdMiddleware(static fn (): string => 'req_generated');
        $response = $middleware->process(new Request('GET', '/health', [
            'x-request-id' => 'req_incoming_123',
        ]), $this->okHandler());

        Assertions::same('req_incoming_123', $response->headers()['X-Request-Id']);
    }

    public function testSecurityHeadersMiddlewareAddsSafeHeaders(): void
    {
        $response = (new SecurityHeadersMiddleware())->process(new Request('GET', '/health'), $this->okHandler());

        Assertions::same('nosniff', $response->headers()['X-Content-Type-Options']);
        Assertions::same('no-referrer', $response->headers()['Referrer-Policy']);
        Assertions::same('DENY', $response->headers()['X-Frame-Options']);
    }

    public function testAuthenticationMiddlewareAllowsInactiveSafeRoutesOnly(): void
    {
        $middleware = new AuthenticationMiddleware();

        Assertions::same(200, $middleware->process(new Request('GET', '/health'), $this->okHandler())->statusCode());

        $response = $middleware->process(new Request('POST', '/api/v1/auth/login'), $this->okHandler());
        $payload = json_decode($response->body(), true);

        Assertions::same(401, $response->statusCode());
        Assertions::same('authentication_inactive', $payload['error']['code']);
    }

    public function testAuthorizationMiddlewareFailsClosedOutsideSafeRoutes(): void
    {
        $middleware = new AuthorizationMiddleware();

        Assertions::same(200, $middleware->process(new Request('GET', '/healthz'), $this->okHandler())->statusCode());

        $response = $middleware->process(new Request('GET', '/api/v1/roles'), $this->okHandler());
        $payload = json_decode($response->body(), true);

        Assertions::same(403, $response->statusCode());
        Assertions::same('authorization_inactive', $payload['error']['code']);
    }

    /**
     * @return callable(Request):Response
     */
    private function okHandler(): callable
    {
        return static fn (Request $request): JsonResponse => new JsonResponse(200, [
            'status' => 'ok',
        ]);
    }
};
