<?php
declare(strict_types=1);

use Babanoell\Auth\App\App;
use Babanoell\Auth\App\Config;
use Babanoell\Auth\App\ErrorHandler;
use Babanoell\Auth\Http\ErrorCode;
use Babanoell\Auth\Http\Request;
use Babanoell\Auth\Tests\Assertions;
use Babanoell\Auth\Tests\TestCase;
use Babanoell\Auth\Validation\Rules\EmailRule;

return new class extends TestCase {
    public function testMalformedJsonReturnsSafeValidationError(): void
    {
        $response = $this->app()->handle(new Request('POST', '/api/v1/auth/login', [], '{"identifier":'));
        $payload = $this->payload($response->body());

        Assertions::same(422, $response->statusCode());
        Assertions::same(ErrorCode::VALIDATION_ERROR, $payload['error']['code']);
        Assertions::same('body', $payload['error']['details'][0]['field']);
        Assertions::same('invalid_json', $payload['error']['details'][0]['code']);
        Assertions::false(str_contains($response->body(), '{"identifier":'));
    }

    public function testMissingLoginFieldsReturnValidationErrors(): void
    {
        $response = $this->app()->handle(new Request('POST', '/api/v1/auth/login', [], '{}'));
        $payload = $this->payload($response->body());

        Assertions::same(422, $response->statusCode());
        Assertions::same(ErrorCode::VALIDATION_ERROR, $payload['error']['code']);
        Assertions::same('identifier', $payload['error']['details'][0]['field']);
        Assertions::same('required', $payload['error']['details'][0]['code']);
        Assertions::same('password', $payload['error']['details'][1]['field']);
        Assertions::same('required', $payload['error']['details'][1]['code']);
    }

    public function testUnexpectedFieldTypesReturnValidationErrors(): void
    {
        $response = $this->app()->handle(new Request(
            'POST',
            '/api/v1/auth/login',
            [],
            '{"identifier":123,"password":{"raw":"local-placeholder"}}',
        ));
        $payload = $this->payload($response->body());

        Assertions::same(422, $response->statusCode());
        Assertions::same('identifier', $payload['error']['details'][0]['field']);
        Assertions::same('invalid_type', $payload['error']['details'][0]['code']);
        Assertions::same('password', $payload['error']['details'][1]['field']);
        Assertions::same('invalid_type', $payload['error']['details'][1]['code']);
        Assertions::false(str_contains($response->body(), 'local-placeholder'));
    }

    public function testOversizedLoginIdentifierIsRejectedWithoutEchoingInput(): void
    {
        $identifier = str_repeat('a', 256);
        $response = $this->app()->handle(new Request(
            'POST',
            '/api/v1/auth/login',
            [],
            json_encode([
                'identifier' => $identifier,
                'password' => 'local-placeholder',
            ], JSON_THROW_ON_ERROR),
        ));
        $payload = $this->payload($response->body());

        Assertions::same(422, $response->statusCode());
        Assertions::same('identifier', $payload['error']['details'][0]['field']);
        Assertions::same('max_length', $payload['error']['details'][0]['code']);
        Assertions::false(str_contains($response->body(), $identifier));
    }

    public function testEmailRuleRejectsInvalidEmailShape(): void
    {
        $error = (new EmailRule())->validate('email', ['email' => 'not-an-email']);

        Assertions::true($error !== null);
        Assertions::same('email', $error->field());
        Assertions::same('invalid_email', $error->code());
        Assertions::false(str_contains($error->message(), 'not-an-email'));
    }

    public function testValidShapedInactiveLoginDoesNotEchoPasswordOrIssueToken(): void
    {
        $response = $this->app()->handle(new Request(
            'POST',
            '/api/v1/auth/login',
            [],
            '{"identifier":"local-user@example.test","password":"SensitiveTestPassword!"}',
        ));

        Assertions::same(501, $response->statusCode());
        $this->assertNoSensitiveAuthData($response->body());
        Assertions::false(str_contains($response->body(), 'SensitiveTestPassword!'));
    }

    public function testValidShapedInactiveRefreshDoesNotEchoSubmittedCredential(): void
    {
        $response = $this->app()->handle(new Request(
            'POST',
            '/api/v1/auth/refresh',
            [],
            '{"refresh_token":"submitted-refresh-credential"}',
        ));

        Assertions::same(501, $response->statusCode());
        $this->assertNoSensitiveAuthData($response->body());
        Assertions::false(str_contains($response->body(), 'submitted-refresh-credential'));
    }

    public function testInactiveLoginDoesNotRevealUserExistence(): void
    {
        $first = $this->app()->handle(new Request(
            'POST',
            '/api/v1/auth/login',
            [],
            '{"identifier":"known-user@example.test","password":"local-placeholder"}',
        ));
        $second = $this->app()->handle(new Request(
            'POST',
            '/api/v1/auth/login',
            [],
            '{"identifier":"missing-user@example.test","password":"local-placeholder"}',
        ));

        Assertions::same(501, $first->statusCode());
        $firstPayload = $this->payload($first->body());
        $secondPayload = $this->payload($second->body());
        Assertions::same($firstPayload['error'], $secondPayload['error']);
        Assertions::true(($firstPayload['meta']['request_id'] ?? '') !== '');
        Assertions::true(($secondPayload['meta']['request_id'] ?? '') !== '');
        Assertions::false(str_contains($first->body(), 'known-user@example.test'));
        Assertions::false(str_contains($second->body(), 'missing-user@example.test'));
    }

    public function testInactiveMeDoesNotReturnUserOrSessionData(): void
    {
        $response = $this->app()->handle(new Request('GET', '/api/v1/auth/me'));

        Assertions::same(501, $response->statusCode());
        $this->assertNoSensitiveAuthData($response->body());
    }

    public function testUnknownRouteReturnsSafe404(): void
    {
        $response = $this->app()->handle(new Request('GET', '/api/v1/auth/missing'));
        $payload = $this->payload($response->body());

        Assertions::same(404, $response->statusCode());
        Assertions::same(ErrorCode::NOT_FOUND, $payload['error']['code']);
        Assertions::false(str_contains($response->body(), __FILE__));
    }

    public function testUnsupportedMethodReturnsSafe405(): void
    {
        $response = $this->app()->handle(new Request('GET', '/api/v1/auth/login'));
        $payload = $this->payload($response->body());

        Assertions::same(405, $response->statusCode());
        Assertions::same(ErrorCode::METHOD_NOT_ALLOWED, $payload['error']['code']);
        Assertions::same('POST', $response->headers()['Allow']);
        Assertions::false(str_contains($response->body(), __FILE__));
    }

    public function testInternalErrorMappingDoesNotExposeTraceOrPath(): void
    {
        $response = (new ErrorHandler())->render(new RuntimeException('Unsafe detail at ' . __FILE__));
        $payload = $this->payload($response->body());

        Assertions::same(500, $response->statusCode());
        Assertions::same(ErrorCode::INTERNAL_ERROR, $payload['error']['code']);
        Assertions::same('Internal server error.', $payload['error']['message']);
        Assertions::false(str_contains($response->body(), 'Unsafe detail'));
        Assertions::false(str_contains($response->body(), __FILE__));
        Assertions::false(str_contains($response->body(), 'Trace'));
    }

    public function testValidationErrorsUseConsistentJsonShape(): void
    {
        $response = $this->app()->handle(new Request('POST', '/api/v1/auth/refresh', [], '{}'));
        $payload = $this->payload($response->body());

        Assertions::same(422, $response->statusCode());
        Assertions::same(ErrorCode::VALIDATION_ERROR, $payload['error']['code']);
        Assertions::same('The request body contains invalid fields.', $payload['error']['message']);
        Assertions::same('refresh_token', $payload['error']['details'][0]['field']);
        Assertions::same('required', $payload['error']['details'][0]['code']);
        Assertions::true(isset($payload['error']['details'][0]['message']));
    }

    private function app(): App
    {
        return App::create(new Config('auth-service', 'local'));
    }

    /**
     * @return array<string,mixed>
     */
    private function payload(string $body): array
    {
        $payload = json_decode($body, true);

        Assertions::true(is_array($payload));

        return $payload;
    }

    private function assertNoSensitiveAuthData(string $body): void
    {
        foreach (['access_token', 'refresh_token', 'token', 'session', 'user', 'password'] as $term) {
            Assertions::false(str_contains($body, $term), "Response must not contain {$term}.");
        }
    }
};
