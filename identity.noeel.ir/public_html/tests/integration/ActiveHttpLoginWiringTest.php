<?php
declare(strict_types=1);

use Babanoell\Auth\App\App;
use Babanoell\Auth\App\Config;
use Babanoell\Auth\App\Container;
use Babanoell\Auth\Audit\AuditEvent;
use Babanoell\Auth\Audit\AuditWriterInterface;
use Babanoell\Auth\Http\Request;
use Babanoell\Auth\Models\SessionRecord;
use Babanoell\Auth\Models\User;
use Babanoell\Auth\RateLimit\RateLimitContext;
use Babanoell\Auth\RateLimit\RateLimitDecision;
use Babanoell\Auth\RateLimit\RateLimiterInterface;
use Babanoell\Auth\Repositories\SessionRepositoryInterface;
use Babanoell\Auth\Repositories\UserRepositoryInterface;
use Babanoell\Auth\Security\HmacTokenHasher;
use Babanoell\Auth\Security\PhpPasswordHasher;
use Babanoell\Auth\Tests\Assertions;
use Babanoell\Auth\Tests\Fixtures\AuthTestData;
use Babanoell\Auth\Tests\TestCase;

final class InMemoryUserRepositoryForActiveHttpLoginWiringTest implements UserRepositoryInterface
{
    /**
     * @param list<User> $users
     */
    public function __construct(private readonly array $users = [])
    {
    }

    public function findById(string $userId): ?User
    {
        foreach ($this->users as $user) {
            if ($user->id === $userId) {
                return $user;
            }
        }

        return null;
    }

    public function findByLoginIdentifier(string $identifier): ?User
    {
        foreach ($this->users as $user) {
            if ($user->email === $identifier || $user->mobile === $identifier) {
                return $user;
            }
        }

        return null;
    }
}

final class ThrowingUserRepositoryForActiveHttpLoginWiringTest implements UserRepositoryInterface
{
    public function findById(string $userId): ?User
    {
        throw new RuntimeException('User repository must not be called.');
    }

    public function findByLoginIdentifier(string $identifier): ?User
    {
        throw new RuntimeException('User repository must not be called.');
    }
}

final class RecordingSessionRepositoryForActiveHttpLoginWiringTest implements SessionRepositoryInterface
{
    /**
     * @var list<SessionRecord>
     */
    public array $created = [];

    public function create(SessionRecord $session): void
    {
        $this->created[] = $session;
    }

    public function findActiveById(string $sessionId): ?SessionRecord
    {
        return null;
    }

    public function findByRefreshTokenHash(string $refreshTokenHash): ?SessionRecord
    {
        return null;
    }

    public function findByAccessTokenHash(string $accessTokenHash): ?SessionRecord
    {
        return null;
    }

    public function revokeById(string $sessionId, string $reason): void
    {
    }

    public function revokeByTokenFamily(string $tokenFamilyId, string $reason): int
    {
        return 0;
    }

    public function revokeAllForUser(string $userId, string $reason): int
    {
        return 0;
    }
}

final class RecordingAuditWriterForActiveHttpLoginWiringTest implements AuditWriterInterface
{
    /**
     * @var list<AuditEvent>
     */
    public array $events = [];

    public function write(AuditEvent $event): void
    {
        $this->events[] = $event;
    }
}

final class AllowingRateLimiterForActiveHttpLoginWiringTest implements RateLimiterInterface
{
    public function check(RateLimitContext $context): RateLimitDecision
    {
        return RateLimitDecision::allow(4);
    }
}

final class BlockingRateLimiterForActiveHttpLoginWiringTest implements RateLimiterInterface
{
    public function check(RateLimitContext $context): RateLimitDecision
    {
        return RateLimitDecision::block('rate_limited', 60);
    }
}

return new class extends TestCase {
    public function testDefaultHttpLoginEndpointRemainsInactive(): void
    {
        $response = App::create(new Config('auth-service', 'local'))->handle($this->loginRequest());
        $body = $response->body();

        Assertions::same(501, $response->statusCode());
        Assertions::contains('not_implemented', $body);
        Assertions::false(str_contains($body, 'session_credential'));
        Assertions::false(str_contains($body, 'refresh_credential'));
    }

    public function testHttpLoginCannotActivateWithoutExplicitLocalFlag(): void
    {
        $response = $this->app([
            'auth_environment' => 'local',
            'allow_http_login_in_local' => false,
            'login_enabled' => true,
            'session_issuing_enabled' => true,
            'require_token_pepper' => true,
            'token_pepper' => 'local-http-test-pepper',
        ], users: new ThrowingUserRepositoryForActiveHttpLoginWiringTest())->handle($this->loginRequest());

        Assertions::same(501, $response->statusCode());
        Assertions::contains('not_implemented', $response->body());
    }

    public function testHttpLoginCannotActivateOutsideLocalOrTestEnvironment(): void
    {
        $response = $this->app([
            'auth_environment' => 'production',
            'allow_http_login_in_local' => true,
            'login_enabled' => true,
            'session_issuing_enabled' => true,
            'require_token_pepper' => true,
            'token_pepper' => 'local-http-test-pepper',
        ], appEnvironment: 'production', users: new ThrowingUserRepositoryForActiveHttpLoginWiringTest())->handle($this->loginRequest());

        Assertions::same(501, $response->statusCode());
        Assertions::contains('not_implemented', $response->body());
    }

    public function testHttpLoginFailsClosedWhenSessionIssuingIsDisabled(): void
    {
        $sessions = new RecordingSessionRepositoryForActiveHttpLoginWiringTest();
        $response = $this->app([
            'session_issuing_enabled' => false,
        ], sessions: $sessions)->handle($this->loginRequest());

        Assertions::same(503, $response->statusCode());
        Assertions::contains('auth_unavailable', $response->body());
        Assertions::same(0, count($sessions->created));
    }

    public function testHttpLoginFailsClosedWhenRequiredTokenPepperIsMissing(): void
    {
        $sessions = new RecordingSessionRepositoryForActiveHttpLoginWiringTest();
        $response = $this->app([
            'token_pepper' => '',
        ], sessions: $sessions)->handle($this->loginRequest());

        Assertions::same(503, $response->statusCode());
        Assertions::contains('auth_unavailable', $response->body());
        Assertions::same(0, count($sessions->created));
    }

    public function testControlledLocalHttpLoginCanIssueSessionCredentialForSyntheticUser(): void
    {
        $sessions = new RecordingSessionRepositoryForActiveHttpLoginWiringTest();
        $audit = new RecordingAuditWriterForActiveHttpLoginWiringTest();
        $response = $this->app(sessions: $sessions, auditWriter: $audit)->handle($this->loginRequest());
        $payload = json_decode($response->body(), true, flags: JSON_THROW_ON_ERROR);

        Assertions::same(200, $response->statusCode());
        Assertions::true(isset($payload['data']['session']['session_credential']));
        Assertions::true(is_string($payload['data']['session']['session_credential']));
        Assertions::true(isset($payload['data']['session']['access_expires_at']));
        Assertions::false(isset($payload['data']['session']['id']));
        Assertions::false(isset($payload['data']['session']['refresh_credential']));
        Assertions::same(1, count($sessions->created));

        $credential = $payload['data']['session']['session_credential'];
        Assertions::false($sessions->created[0]->refreshTokenHash === $credential);
        Assertions::false(str_contains($response->body(), AuthTestData::PASSWORD));
        Assertions::false(str_contains($response->body(), 'password_hash'));
        Assertions::false(str_contains($response->body(), 'refresh_token_hash'));
        Assertions::same(1, count($audit->events));
        Assertions::same('auth.login.succeeded', $audit->events[0]->type()->value);
        Assertions::false(str_contains(json_encode($audit->events[0]->metadata(), JSON_THROW_ON_ERROR), $credential));
        Assertions::false(str_contains(json_encode($audit->events[0]->metadata(), JSON_THROW_ON_ERROR), AuthTestData::PASSWORD));
    }

    public function testWrongPasswordAndMissingUserReturnIndistinguishablePublicResponse(): void
    {
        $wrongPassword = $this->app()->handle($this->loginRequest(password: 'wrong-local-password'));
        $missingUser = $this->app(users: new InMemoryUserRepositoryForActiveHttpLoginWiringTest())->handle(
            $this->loginRequest(identifier: 'missing-login-test@example.invalid', password: 'wrong-local-password'),
        );

        Assertions::same(401, $wrongPassword->statusCode());
        Assertions::same($wrongPassword->statusCode(), $missingUser->statusCode());
        Assertions::same($wrongPassword->body(), $missingUser->body());
        Assertions::false(str_contains($wrongPassword->body(), 'wrong-local-password'));
        Assertions::false(str_contains($missingUser->body(), 'missing-login-test@example.invalid'));
    }

    public function testRateLimitBlocksBeforeUserLookup(): void
    {
        $response = $this->app(
            users: new ThrowingUserRepositoryForActiveHttpLoginWiringTest(),
            rateLimiter: new BlockingRateLimiterForActiveHttpLoginWiringTest(),
        )->handle($this->loginRequest());

        Assertions::same(429, $response->statusCode());
        Assertions::contains('rate_limited', $response->body());
    }

    public function testRefreshPasswordResetAndTwoFactorRemainInactive(): void
    {
        $app = $this->app();

        Assertions::same(501, $app->handle(new Request(
            'POST',
            '/api/v1/auth/refresh',
            ['content-type' => 'application/json'],
            '{"refresh_token":"synthetic-refresh-token"}',
        ))->statusCode());
        Assertions::same(404, $app->handle(new Request('POST', '/api/v1/auth/password-reset'))->statusCode());
        Assertions::same(404, $app->handle(new Request('POST', '/api/v1/auth/2fa/verify'))->statusCode());
    }

    /**
     * @param array<string,mixed> $securityOverrides
     */
    private function app(
        array $securityOverrides = [],
        string $appEnvironment = 'local',
        ?UserRepositoryInterface $users = null,
        ?SessionRepositoryInterface $sessions = null,
        ?AuditWriterInterface $auditWriter = null,
        ?RateLimiterInterface $rateLimiter = null,
    ): App {
        $passwordHasher = $this->passwordHasher();
        $security = array_replace([
            'auth_environment' => 'local',
            'allow_http_login_in_local' => true,
            'login_enabled' => true,
            'session_issuing_enabled' => true,
            'refresh_enabled' => false,
            'require_token_pepper' => true,
            'token_pepper' => 'local-http-test-pepper',
            'session_ttl_seconds' => 900,
            'refresh_ttl_seconds' => 1209600,
            'login_rate_limit_window_seconds' => 900,
            'login_rate_limit_max_attempts' => 5,
        ], $securityOverrides);

        return App::createFromContainer(new Container(
            new Config('auth-service', $appEnvironment, $security),
            users: $users ?? new InMemoryUserRepositoryForActiveHttpLoginWiringTest([
                AuthTestData::user($passwordHasher),
            ]),
            sessions: $sessions ?? new RecordingSessionRepositoryForActiveHttpLoginWiringTest(),
            auditWriter: $auditWriter ?? new RecordingAuditWriterForActiveHttpLoginWiringTest(),
            rateLimiter: $rateLimiter ?? new AllowingRateLimiterForActiveHttpLoginWiringTest(),
            passwordHasher: $passwordHasher,
            tokenHasher: new HmacTokenHasher('local-http-test-pepper'),
        ));
    }

    private function loginRequest(
        string $identifier = AuthTestData::EMAIL,
        string $password = AuthTestData::PASSWORD,
    ): Request {
        return new Request('POST', '/api/v1/auth/login', [
            'content-type' => 'application/json',
            'x-request-id' => 'req_local_http_login',
            'x-forwarded-for' => '192.0.2.45',
            'user-agent' => 'Local HTTP Login Test',
        ], json_encode([
            'identifier' => $identifier,
            'password' => $password,
        ], JSON_THROW_ON_ERROR));
    }

    private function passwordHasher(): PhpPasswordHasher
    {
        return new PhpPasswordHasher(PASSWORD_BCRYPT, ['cost' => 4]);
    }
};
