<?php
declare(strict_types=1);

use Babanoell\Auth\App\App;
use Babanoell\Auth\App\Config;
use Babanoell\Auth\Http\Request;
use Babanoell\Auth\Tests\Assertions;
use Babanoell\Auth\Tests\TestCase;

return new class extends TestCase {
    public function testHealthEndpointRemainsCompatible(): void
    {
        $response = $this->app()->handle(new Request('GET', '/health'));
        $payload = json_decode($response->body(), true);

        Assertions::same(200, $response->statusCode());
        Assertions::same('auth-service', $payload['service']);
        Assertions::same('ok', $payload['status']);
        Assertions::same('local', $payload['environment']);
        Assertions::true(isset($payload['timestamp']));
    }

    public function testPlatformContractEndpointPublishesArchitectureMetadata(): void
    {
        $response = $this->app()->handle(new Request('GET', '/api/v1/meta/platform-contract'));
        $payload = json_decode($response->body(), true);

        Assertions::same(200, $response->statusCode());
        Assertions::same('identity', $payload['data']['contract']['service'] ?? null);
        Assertions::same('2026-10-01.phase7', $payload['data']['contract']['contract_version'] ?? null);
        Assertions::same('optional', $payload['data']['service_auth']['mode'] ?? null);
        Assertions::true(isset($payload['meta']['request_id']));
    }

    public function testHealthzEndpointRemainsCompatible(): void
    {
        $response = $this->app()->handle(new Request('GET', '/healthz'));
        $payload = json_decode($response->body(), true);

        Assertions::true(in_array($response->statusCode(), [200, 503], true));
        Assertions::same('auth-service', $payload['service']);
        if ($response->statusCode() === 200) {
            Assertions::same('ok', $payload['status']);
            Assertions::true(in_array($payload['database'] ?? null, ['ok', 'not_configured'], true));
        } else {
            Assertions::same('error', $payload['status']);
            Assertions::same('unavailable', $payload['database'] ?? null);
        }
    }

    public function testInactiveAuthEndpointsReturnNotImplemented(): void
    {
        foreach ([
            ['POST', '/api/v1/auth/login', '{"identifier":"local-user@example.test","password":"local-placeholder"}'],
            ['POST', '/api/v1/auth/logout', '{}'],
            ['POST', '/api/v1/auth/refresh', '{"refresh_token":"local-placeholder"}'],
            ['GET', '/api/v1/auth/me', ''],
        ] as [$method, $path, $body]) {
            $response = $this->app()->handle(new Request($method, $path, [], $body));
            $payload = json_decode($response->body(), true);

            Assertions::same(501, $response->statusCode(), "{$path} must remain inactive.");
            Assertions::same('not_implemented', $payload['error']['code']);
            Assertions::same('This endpoint is not active yet.', $payload['error']['message']);
        }
    }

    public function testPermissionDirectoryRequiresAuthentication(): void
    {
        $response = $this->app()->handle(new Request('GET', '/api/v1/permissions'));
        $payload = json_decode($response->body(), true);
        Assertions::true(in_array($response->statusCode(), [401, 503], true));
        Assertions::true(isset($payload['error']['code']));
    }

    public function testRoleManagementRequiresAuthentication(): void
    {
        $response = $this->app()->handle(new Request('GET', '/api/v1/roles'));
        $payload = json_decode($response->body(), true);
        Assertions::true(in_array($response->statusCode(), [401, 503], true));
        Assertions::true(isset($payload['error']['code']));
    }

    public function testInactiveLoginDoesNotReturnTokenOrUserData(): void
    {
        $response = $this->app()->handle(new Request('POST', '/api/v1/auth/login', [
            'content-type' => 'application/json',
        ], '{"identifier":"local-user@example.test","password":"local-placeholder"}'));

        $body = $response->body();
        $payload = json_decode($body, true);

        Assertions::same(501, $response->statusCode());
        Assertions::same('not_implemented', $payload['error']['code']);
        Assertions::false(str_contains($body, 'access_token'));
        Assertions::false(str_contains($body, 'refresh_token'));
        Assertions::false(str_contains($body, 'token'));
        Assertions::false(str_contains($body, 'user'));
        Assertions::false(str_contains($body, 'password'));
    }

    private function app(): App
    {
        return App::create(new Config('auth-service', 'local'));
    }
};
