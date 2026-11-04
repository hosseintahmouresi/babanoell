<?php
declare(strict_types=1);

use Babanoell\Auth\Tests\Assertions;
use Babanoell\Auth\Tests\TestCase;
use Babanoell\Auth\UseCases\Login\InactiveLoginUseCase;
use Babanoell\Auth\UseCases\Login\LoginRequest;
use Babanoell\Auth\UseCases\Logout\InactiveLogoutUseCase;
use Babanoell\Auth\UseCases\Logout\LogoutRequest;
use Babanoell\Auth\UseCases\Me\InactiveMeUseCase;
use Babanoell\Auth\UseCases\Me\MeRequest;
use Babanoell\Auth\UseCases\Refresh\InactiveRefreshUseCase;
use Babanoell\Auth\UseCases\Refresh\RefreshRequest;

return new class extends TestCase {
    public function testLoginRequestTracksSubmittedFieldsWithoutExposingSecretGetter(): void
    {
        $request = LoginRequest::fromPayload([
            'identifier' => ' local-user@example.test ',
            'password' => 'local-placeholder',
            'second_factor_code' => '000000',
        ], 'req_local');

        Assertions::same('local-user@example.test', $request->identifier());
        Assertions::true($request->passwordSubmitted());
        Assertions::true($request->secondFactorSubmitted());
        Assertions::same('req_local', $request->requestId());
        Assertions::false(method_exists($request, 'password'));
        Assertions::false(method_exists($request, 'plainPassword'));
    }

    public function testInactiveLoginUseCaseDoesNotIssueTokenOrUserData(): void
    {
        $result = (new InactiveLoginUseCase())->handle(LoginRequest::fromPayload([
            'identifier' => 'local-user@example.test',
            'password' => 'local-placeholder',
        ]));

        Assertions::false($result->active());
        Assertions::same(501, $result->statusCode());
        $this->assertNoAuthData($result->toErrorPayload());
    }

    public function testInactiveLogoutUseCaseDoesNotCreateWorkflowResult(): void
    {
        $result = (new InactiveLogoutUseCase())->handle(LogoutRequest::fromPayload([
            'all_sessions' => true,
        ]));

        Assertions::false($result->active());
        Assertions::same(501, $result->statusCode());
        $this->assertNoAuthData($result->toErrorPayload());
    }

    public function testInactiveRefreshUseCaseDoesNotIssueTokens(): void
    {
        $request = RefreshRequest::fromPayload([
            'refresh_token' => 'local-placeholder',
        ]);

        Assertions::true($request->refreshCredentialSubmitted());

        $result = (new InactiveRefreshUseCase())->handle($request);

        Assertions::false($result->active());
        Assertions::same(501, $result->statusCode());
        $this->assertNoAuthData($result->toErrorPayload());
    }

    public function testInactiveMeUseCaseDoesNotReturnUserRecord(): void
    {
        $result = (new InactiveMeUseCase())->handle(new MeRequest('req_local'));

        Assertions::false($result->active());
        Assertions::same(501, $result->statusCode());
        $this->assertNoAuthData($result->toErrorPayload());
    }

    /**
     * @param array<string,mixed> $payload
     */
    private function assertNoAuthData(array $payload): void
    {
        $body = json_encode($payload, JSON_THROW_ON_ERROR);

        foreach (['access_token', 'refresh_token', 'token', 'session', 'user', 'password'] as $term) {
            Assertions::false(str_contains($body, $term), "Inactive result must not contain {$term}.");
        }
    }
};
