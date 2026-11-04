<?php
declare(strict_types=1);

use Babanoell\Auth\Http\JsonResponse;
use Babanoell\Auth\Http\Request;
use Babanoell\Auth\Http\Router;
use Babanoell\Auth\App\ErrorHandler;
use Babanoell\Auth\Tests\Assertions;
use Babanoell\Auth\Tests\TestCase;

return new class extends TestCase {
    public function testRequestNormalizesMethodPathHeadersAndJsonBody(): void
    {
        $request = new Request(
            'post',
            'api/v1/auth/login/',
            ['content-type' => 'application/json'],
            '{"identifier":"local-user@example.test"}',
            ['debug' => '0'],
        );

        Assertions::same('POST', $request->method());
        Assertions::same('/api/v1/auth/login', $request->path());
        Assertions::same('application/json', $request->header('Content-Type'));
        Assertions::same(['debug' => '0'], $request->queryParams());
        Assertions::same('local-user@example.test', $request->json()['identifier']);
    }

    public function testJsonResponseFormatsPayloadAndHeaders(): void
    {
        $response = new JsonResponse(202, [
            'data' => [
                'status' => 'accepted',
            ],
        ]);

        Assertions::same(202, $response->statusCode());
        Assertions::same('application/json; charset=utf-8', $response->headers()['Content-Type']);
        Assertions::same('nosniff', $response->headers()['X-Content-Type-Options']);

        $payload = json_decode($response->body(), true);
        Assertions::same('accepted', $payload['data']['status']);
    }

    public function testErrorResponseUsesConsistentShape(): void
    {
        $response = JsonResponse::error(501, 'not_implemented', 'This endpoint is not active yet.');
        $payload = $response->payload();

        Assertions::same(501, $response->statusCode());
        Assertions::same('not_implemented', $payload['error']['code']);
        Assertions::same('This endpoint is not active yet.', $payload['error']['message']);
    }

    public function testRouterMatchesMethodAndPath(): void
    {
        $router = new Router();
        $router->get('/health', static fn (Request $request): JsonResponse => new JsonResponse(200, [
            'status' => 'ok',
        ]));

        $response = $router->dispatch(new Request('GET', '/health'));
        $payload = json_decode($response->body(), true);

        Assertions::same(200, $response->statusCode());
        Assertions::same('ok', $payload['status']);
    }

    public function testRouterReturns404ForUnknownPath(): void
    {
        $response = (new Router())->dispatch(new Request('GET', '/missing'));
        $payload = json_decode($response->body(), true);

        Assertions::same(404, $response->statusCode());
        Assertions::same('not_found', $payload['error']['code']);
    }

    public function testRouterReturns405ForUnsupportedMethod(): void
    {
        $router = new Router();
        $router->post('/api/v1/auth/login', static fn (Request $request): JsonResponse => JsonResponse::error(501, 'not_implemented', 'This endpoint is not active yet.'));

        $response = $router->dispatch(new Request('GET', '/api/v1/auth/login'));
        $payload = json_decode($response->body(), true);

        Assertions::same(405, $response->statusCode());
        Assertions::same('method_not_allowed', $payload['error']['code']);
        Assertions::same('POST', $response->headers()['Allow']);
    }

    public function testErrorHandlerUsesSafeBadRequestResponse(): void
    {
        $response = (new ErrorHandler())->render(new \InvalidArgumentException('Unsafe detail should not be exposed.'));
        $payload = json_decode($response->body(), true);

        Assertions::same(400, $response->statusCode());
        Assertions::same('invalid_request', $payload['error']['code']);
        Assertions::same('Invalid request.', $payload['error']['message']);
        Assertions::false(str_contains($response->body(), 'Unsafe detail'));
    }
    public function testRequestReadsRedirectedAuthorizationHeaderFromCpanelFastCgi(): void
    {
        $previousServer = $_SERVER;
        $previousGet = $_GET;
        try {
            $_SERVER = [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/api/v1/auth/me',
                'REDIRECT_HTTP_AUTHORIZATION' => 'Bearer cpanel-forwarded-token',
            ];
            $_GET = [];
            $request = Request::fromGlobals();
            Assertions::same('Bearer cpanel-forwarded-token', $request->header('Authorization'));
        } finally {
            $_SERVER = $previousServer;
            $_GET = $previousGet;
        }
    }

};
