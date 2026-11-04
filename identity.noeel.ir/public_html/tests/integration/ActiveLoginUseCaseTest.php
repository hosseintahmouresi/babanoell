<?php
declare(strict_types=1);

use Babanoell\Auth\App\App;
use Babanoell\Auth\App\Config;
use Babanoell\Auth\Audit\AuditEvent;
use Babanoell\Auth\Audit\AuditWriterInterface;
use Babanoell\Auth\Models\SessionRecord;
use Babanoell\Auth\Models\User;
use Babanoell\Auth\RateLimit\RateLimitContext;
use Babanoell\Auth\RateLimit\RateLimitDecision;
use Babanoell\Auth\RateLimit\RateLimiterInterface;
use Babanoell\Auth\Repositories\SessionRepositoryInterface;
use Babanoell\Auth\Repositories\UserRepositoryInterface;
use Babanoell\Auth\Security\HmacTokenHasher;
use Babanoell\Auth\Security\PhpPasswordHasher;
use Babanoell\Auth\Support\ClockInterface;
use Babanoell\Auth\Support\IdGeneratorInterface;
use Babanoell\Auth\Tests\Assertions;
use Babanoell\Auth\Tests\Fixtures\AuthTestData;
use Babanoell\Auth\Tests\TestCase;
use Babanoell\Auth\Http\Request as HttpRequest;
use Babanoell\Auth\UseCases\Login\ActiveLoginUseCase;
use Babanoell\Auth\UseCases\Login\DatabaseSessionIssuer;
use Babanoell\Auth\UseCases\Login\InactiveSessionIssuer;
use Babanoell\Auth\UseCases\Login\LoginRequest;
use Babanoell\Auth\UseCases\Login\LoginSecurityContext;

final class InMemoryUserRepositoryForActiveLoginTest implements UserRepositoryInterface
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

final class InMemorySessionRepositoryForActiveLoginTest implements SessionRepositoryInterface
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

final class AllowingRateLimiterForActiveLoginTest implements RateLimiterInterface
{
    public function check(RateLimitContext $context): RateLimitDecision
    {
        return RateLimitDecision::allow(4);
    }
}

final class RecordingAuditWriterForActiveLoginTest implements AuditWriterInterface
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

final class FailingAuditWriterForActiveLoginTest implements AuditWriterInterface
{
    public function write(AuditEvent $event): void
    {
        throw new RuntimeException('audit storage unavailable');
    }
}

final class SequentialIdGeneratorForActiveLoginTest implements IdGeneratorInterface
{
    private int $next = 1;

    public function generate(): string
    {
        return 'id_local_' . str_pad((string) $this->next++, 18, '0', STR_PAD_LEFT);
    }
}

final class FixedClockForActiveLoginTest implements ClockInterface
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-06-14 00:00:00', new DateTimeZone('UTC'));
    }
}

return new class extends TestCase {
    public function testDefaultLoginEndpointRemainsInactive(): void
    {
        $response = App::create(new Config('auth-service', 'local'))->handle(new HttpRequest(
            'POST',
            '/api/v1/auth/login',
            [],
            '{"identifier":"local-login-test@example.invalid","password":"local-login-test-password"}',
        ));

        Assertions::same(501, $response->statusCode());
        Assertions::false(str_contains($response->body(), 'session_credential'));
        Assertions::false(str_contains($response->body(), 'refresh_credential'));
    }

    public function testActiveLoginCannotRunWhenFeatureFlagIsDisabled(): void
    {
        $result = $this->useCase(loginEnabled: false)->handle($this->validRequest());

        Assertions::same(501, $result->statusCode());
        Assertions::same('not_implemented', $result->errorCode());
        Assertions::false($result->active());
    }

    public function testSessionCannotBeIssuedWhenSessionFlagIsDisabled(): void
    {
        $result = $this->useCase(
            loginEnabled: true,
            sessionIssuer: new InactiveSessionIssuer(),
        )->handle($this->validRequest());

        Assertions::same(503, $result->statusCode());
        Assertions::same('auth_unavailable', $result->errorCode());
        Assertions::false($result->active());
    }

    public function testMissingTokenPepperFailsClosed(): void
    {
        $sessions = new InMemorySessionRepositoryForActiveLoginTest();
        $result = $this->useCase(
            loginEnabled: true,
            sessionIssuer: $this->sessionIssuer($sessions, tokenPepperReady: false),
        )->handle($this->validRequest());

        Assertions::same(503, $result->statusCode());
        Assertions::same('auth_unavailable', $result->errorCode());
        Assertions::same(0, count($sessions->created));
    }

    public function testMissingUserAndWrongPasswordAreIndistinguishable(): void
    {
        $wrongPassword = $this->useCase(loginEnabled: true)->handle(LoginRequest::fromPayload([
            'identifier' => AuthTestData::EMAIL,
            'password' => 'wrong-local-password',
        ], 'req_wrong_password'));

        $missingUser = $this->useCase(loginEnabled: true, users: new InMemoryUserRepositoryForActiveLoginTest())->handle(
            LoginRequest::fromPayload([
                'identifier' => 'missing-login-test@example.invalid',
                'password' => 'wrong-local-password',
            ], 'req_missing_user'),
        );

        Assertions::same(401, $wrongPassword->statusCode());
        Assertions::same($wrongPassword->toErrorPayload(), $missingUser->toErrorPayload());
    }

    public function testPasswordIsNeverEchoedInFailureOrSuccessPayload(): void
    {
        $failure = $this->useCase(loginEnabled: true)->handle(LoginRequest::fromPayload([
            'identifier' => AuthTestData::EMAIL,
            'password' => 'wrong-local-password',
        ]));

        $success = $this->useCase(
            loginEnabled: true,
            sessionIssuer: $this->sessionIssuer(new InMemorySessionRepositoryForActiveLoginTest(), tokenPepperReady: true),
        )->handle($this->validRequest());

        Assertions::false(str_contains(json_encode($failure->toPayload(), JSON_THROW_ON_ERROR), 'wrong-local-password'));
        Assertions::false(str_contains(json_encode($success->toPayload(), JSON_THROW_ON_ERROR), AuthTestData::PASSWORD));
    }

    public function testSyntheticSuccessfulLoginCreatesSessionOnlyWhenEnabled(): void
    {
        $sessions = new InMemorySessionRepositoryForActiveLoginTest();
        $audit = new RecordingAuditWriterForActiveLoginTest();
        $result = $this->useCase(
            loginEnabled: true,
            sessionIssuer: $this->sessionIssuer($sessions, tokenPepperReady: true),
            auditWriter: $audit,
        )->handle($this->validRequest());

        Assertions::true($result->active());
        Assertions::same(200, $result->statusCode());
        Assertions::same(1, count($sessions->created));

        $payload = $result->toPayload();
        $credential = $payload['data']['session']['session_credential'];

        Assertions::true(is_string($credential));
        Assertions::false($sessions->created[0]->refreshTokenHash === $credential);
        Assertions::false(isset($payload['data']['session']['refresh_credential']));
        Assertions::false(str_contains(json_encode($payload, JSON_THROW_ON_ERROR), 'password_hash'));
        Assertions::same(1, count($audit->events));
        Assertions::same('auth.login.succeeded', $audit->events[0]->type()->value);
        Assertions::false(str_contains(json_encode($audit->events[0]->metadata(), JSON_THROW_ON_ERROR), $credential));
    }

    public function testAuditFailureReturnsSafeUnavailableResult(): void
    {
        $result = $this->useCase(
            loginEnabled: true,
            sessionIssuer: $this->sessionIssuer(new InMemorySessionRepositoryForActiveLoginTest(), tokenPepperReady: true),
            auditWriter: new FailingAuditWriterForActiveLoginTest(),
        )->handle($this->validRequest());

        Assertions::same(503, $result->statusCode());
        Assertions::same('auth_unavailable', $result->errorCode());
        Assertions::same('Authentication is temporarily unavailable.', $result->message());
        Assertions::false(str_contains(json_encode($result->toPayload(), JSON_THROW_ON_ERROR), 'audit storage unavailable'));
    }

    public function testRefreshPasswordResetAndTwoFactorRemainInactive(): void
    {
        $app = App::create(new Config('auth-service', 'local'));

        Assertions::same(501, $app->handle(new HttpRequest(
            'POST',
            '/api/v1/auth/refresh',
            [],
            '{"refresh_token":"local-placeholder"}',
        ))->statusCode());
        Assertions::same(404, $app->handle(new HttpRequest('POST', '/api/v1/auth/password-reset'))->statusCode());
        Assertions::same(404, $app->handle(new HttpRequest('POST', '/api/v1/auth/2fa/verify'))->statusCode());
    }

    public function testPendingUserCannotLogin(): void
    {
        $result = $this->useCase(
            loginEnabled: true,
            users: new InMemoryUserRepositoryForActiveLoginTest([
                AuthTestData::user($this->passwordHasher(), status: 'pending'),
            ]),
            sessionIssuer: $this->sessionIssuer(new InMemorySessionRepositoryForActiveLoginTest(), tokenPepperReady: true),
        )->handle($this->validRequest());

        Assertions::same(401, $result->statusCode());
        Assertions::same('invalid_credentials', $result->errorCode());
        Assertions::false($result->active());
    }

    public function testLockedUserCannotLoginWhileLockIsActive(): void
    {
        $result = $this->useCase(
            loginEnabled: true,
            users: new InMemoryUserRepositoryForActiveLoginTest([
                AuthTestData::user(
                    $this->passwordHasher(),
                    lockedUntil: new DateTimeImmutable('2026-06-15 00:00:00', new DateTimeZone('UTC')),
                ),
            ]),
            sessionIssuer: $this->sessionIssuer(new InMemorySessionRepositoryForActiveLoginTest(), tokenPepperReady: true),
        )->handle($this->validRequest());

        Assertions::same(401, $result->statusCode());
        Assertions::same('invalid_credentials', $result->errorCode());
    }

    public function testDisabledUserCannotLogin(): void
    {
        $result = $this->useCase(
            loginEnabled: true,
            users: new InMemoryUserRepositoryForActiveLoginTest([
                AuthTestData::user(
                    $this->passwordHasher(),
                    disabledAt: new DateTimeImmutable('2026-06-13 00:00:00', new DateTimeZone('UTC')),
                ),
            ]),
            sessionIssuer: $this->sessionIssuer(new InMemorySessionRepositoryForActiveLoginTest(), tokenPepperReady: true),
        )->handle($this->validRequest());

        Assertions::same(401, $result->statusCode());
        Assertions::same('invalid_credentials', $result->errorCode());
    }

    public function testExpiredLockAllowsLoginAgain(): void
    {
        $sessions = new InMemorySessionRepositoryForActiveLoginTest();
        $result = $this->useCase(
            loginEnabled: true,
            users: new InMemoryUserRepositoryForActiveLoginTest([
                AuthTestData::user(
                    $this->passwordHasher(),
                    lockedUntil: new DateTimeImmutable('2026-06-13 00:00:00', new DateTimeZone('UTC')),
                ),
            ]),
            sessionIssuer: $this->sessionIssuer($sessions, tokenPepperReady: true),
        )->handle($this->validRequest());

        Assertions::true($result->active());
        Assertions::same(200, $result->statusCode());
        Assertions::same(1, count($sessions->created));
    }

    public function testStateFailuresAreIndistinguishableFromWrongPassword(): void
    {
        $wrongPassword = $this->useCase(loginEnabled: true)->handle(LoginRequest::fromPayload([
            'identifier' => AuthTestData::EMAIL,
            'password' => 'wrong-local-password',
        ]));
        $pending = $this->useCase(
            loginEnabled: true,
            users: new InMemoryUserRepositoryForActiveLoginTest([
                AuthTestData::user($this->passwordHasher(), status: 'pending'),
            ]),
        )->handle($this->validRequest());
        $locked = $this->useCase(
            loginEnabled: true,
            users: new InMemoryUserRepositoryForActiveLoginTest([
                AuthTestData::user(
                    $this->passwordHasher(),
                    lockedUntil: new DateTimeImmutable('2026-06-15 00:00:00', new DateTimeZone('UTC')),
                ),
            ]),
        )->handle($this->validRequest());

        Assertions::same($wrongPassword->toErrorPayload(), $pending->toErrorPayload());
        Assertions::same($wrongPassword->toErrorPayload(), $locked->toErrorPayload());
    }


    public function testSuccessfulLoginClearsOnlyItsRateLimitEntry(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'noel-auth-limiter-');
        if (!is_string($tmp)) throw new RuntimeException('Cannot allocate limiter test file');
        try {
            $limiter = new \Babanoell\Auth\RateLimit\FileWindowRateLimiter($tmp, 3, 900);
            $sessions = new InMemorySessionRepositoryForActiveLoginTest();
            $uc = $this->useCase(loginEnabled: true,
                sessionIssuer: $this->sessionIssuer($sessions, tokenPepperReady: true),
                rateLimiter: $limiter);
            $bad = LoginRequest::fromPayload([
                'identifier' => AuthTestData::EMAIL,
                'password' => 'wrong-password',
            ]);
            Assertions::same(401, $uc->handle($bad)->statusCode());
            Assertions::same(200, $uc->handle($this->validRequest())->statusCode());
            // Before the fix, this fourth call would be locked out despite valid credentials.
            for ($i = 0; $i < 5; $i++) {
                Assertions::same(200, $uc->handle($this->validRequest())->statusCode());
            }
        } finally {
            @unlink($tmp);
        }
    }

    public function testFailedLoginsStillBlockAndLimiterFailureIs503(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'noel-auth-limiter-');
        if (!is_string($tmp)) throw new RuntimeException('Cannot allocate limiter test file');
        try {
            $uc = $this->useCase(loginEnabled: true,
                sessionIssuer: $this->sessionIssuer(new InMemorySessionRepositoryForActiveLoginTest(), tokenPepperReady: true),
                rateLimiter: new \Babanoell\Auth\RateLimit\FileWindowRateLimiter($tmp, 2, 900));
            $bad = LoginRequest::fromPayload([
                'identifier' => AuthTestData::EMAIL,
                'password' => 'wrong-password',
            ]);
            Assertions::same(401, $uc->handle($bad)->statusCode());
            Assertions::same(401, $uc->handle($bad)->statusCode());
            Assertions::same(429, $uc->handle($this->validRequest())->statusCode());
        } finally {
            @unlink($tmp);
        }

        $broken = new \Babanoell\Auth\RateLimit\FileWindowRateLimiter(
            sys_get_temp_dir() . '/noel-nonexistent-' . bin2hex(random_bytes(8)) . '/counter.json', 2, 900);
        $uc = $this->useCase(loginEnabled: true, rateLimiter: $broken);
        $result = $uc->handle($this->validRequest());
        Assertions::same(503, $result->statusCode());
        Assertions::same('auth_unavailable', $result->errorCode());
    }

    private function useCase(
        bool $loginEnabled,
        ?UserRepositoryInterface $users = null,
        ?\Babanoell\Auth\UseCases\Login\SessionIssuerInterface $sessionIssuer = null,
        ?AuditWriterInterface $auditWriter = null,
        ?RateLimiterInterface $rateLimiter = null,
    ): ActiveLoginUseCase {
        return new ActiveLoginUseCase(
            users: $users ?? new InMemoryUserRepositoryForActiveLoginTest([
                AuthTestData::user($this->passwordHasher()),
            ]),
            passwordHasher: $this->passwordHasher(),
            rateLimiter: $rateLimiter ?? new AllowingRateLimiterForActiveLoginTest(),
            auditWriter: $auditWriter ?? new RecordingAuditWriterForActiveLoginTest(),
            sessionIssuer: $sessionIssuer ?? new InactiveSessionIssuer(),
            clock: new FixedClockForActiveLoginTest(),
            loginEnabled: $loginEnabled,
        );
    }

    private function sessionIssuer(
        InMemorySessionRepositoryForActiveLoginTest $sessions,
        bool $tokenPepperReady,
    ): DatabaseSessionIssuer {
        return new DatabaseSessionIssuer(
            sessions: $sessions,
            tokenHasher: new HmacTokenHasher('local-test-pepper'),
            idGenerator: new SequentialIdGeneratorForActiveLoginTest(),
            clock: new FixedClockForActiveLoginTest(),
            sessionIssuingEnabled: true,
            refreshEnabled: false,
            tokenPepperReady: $tokenPepperReady,
        );
    }

    private function validRequest(): LoginRequest
    {
        return LoginRequest::fromPayload([
            'identifier' => AuthTestData::EMAIL,
            'password' => AuthTestData::PASSWORD,
        ], 'req_local', new LoginSecurityContext(
            clientSource: '192.0.2.25',
            userAgent: 'Local Test Browser',
            requestId: 'req_local',
        ));
    }

    private function passwordHasher(): PhpPasswordHasher
    {
        return new PhpPasswordHasher(PASSWORD_BCRYPT, ['cost' => 4]);
    }
};
