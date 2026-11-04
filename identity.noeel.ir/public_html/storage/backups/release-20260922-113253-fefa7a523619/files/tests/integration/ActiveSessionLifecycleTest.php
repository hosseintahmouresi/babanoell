<?php
declare(strict_types=1);

use Babanoell\Auth\Audit\AuditEvent;
use Babanoell\Auth\Audit\AuditWriterInterface;
use Babanoell\Auth\Models\Permission;
use Babanoell\Auth\Models\Role;
use Babanoell\Auth\Models\SessionRecord;
use Babanoell\Auth\Models\User;
use Babanoell\Auth\RateLimit\RateLimitContext;
use Babanoell\Auth\RateLimit\RateLimitDecision;
use Babanoell\Auth\RateLimit\RateLimiterInterface;
use Babanoell\Auth\Repositories\PermissionRepositoryInterface;
use Babanoell\Auth\Repositories\RoleRepositoryInterface;
use Babanoell\Auth\Repositories\SessionRepositoryInterface;
use Babanoell\Auth\Repositories\UserRepositoryInterface;
use Babanoell\Auth\Security\HmacTokenHasher;
use Babanoell\Auth\Security\PhpPasswordHasher;
use Babanoell\Auth\Support\ClockInterface;
use Babanoell\Auth\Support\IdGeneratorInterface;
use Babanoell\Auth\Tests\Assertions;
use Babanoell\Auth\Tests\Fixtures\AuthTestData;
use Babanoell\Auth\Tests\TestCase;
use Babanoell\Auth\UseCases\Login\ActiveLoginUseCase;
use Babanoell\Auth\UseCases\Login\DatabaseSessionIssuer;
use Babanoell\Auth\UseCases\Login\LoginRequest;
use Babanoell\Auth\UseCases\Login\LoginSecurityContext;
use Babanoell\Auth\UseCases\Login\LoginUseCaseInterface;
use Babanoell\Auth\UseCases\Login\SessionIssuerInterface;
use Babanoell\Auth\UseCases\Logout\ActiveLogoutUseCase;
use Babanoell\Auth\UseCases\Logout\LogoutRequest;
use Babanoell\Auth\UseCases\Me\ActiveMeUseCase;
use Babanoell\Auth\UseCases\Me\MeRequest;
use Babanoell\Auth\UseCases\Refresh\ActiveRefreshUseCase;
use Babanoell\Auth\UseCases\Refresh\DatabaseSessionRotator;
use Babanoell\Auth\UseCases\Refresh\RefreshRequest;

final class MutableClockForSessionLifecycleTest implements ClockInterface
{
    public function __construct(private DateTimeImmutable $now)
    {
    }

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }

    public function advance(int $seconds): void
    {
        $this->now = $this->now->add(new DateInterval('PT' . $seconds . 'S'));
    }
}

final class MutableInMemorySessionRepositoryForSessionLifecycleTest implements SessionRepositoryInterface
{
    /** @var array<string,SessionRecord> */
    public array $rows = [];
    private DateTimeImmutable $revokedAt;

    public function __construct()
    {
        $this->revokedAt = new DateTimeImmutable('2026-06-14 00:00:00', new DateTimeZone('UTC'));
    }

    public function create(SessionRecord $session): void
    {
        $this->rows[$session->id] = $session;
    }

    public function findActiveById(string $sessionId): ?SessionRecord
    {
        foreach ($this->rows as $row) {
            if ($row->id === $sessionId && !$row->isRevoked()) {
                return $row;
            }
        }

        return null;
    }

    public function findByRefreshTokenHash(string $refreshTokenHash): ?SessionRecord
    {
        foreach ($this->rows as $row) {
            if ($row->refreshTokenHash === $refreshTokenHash) {
                return $row;
            }
        }

        return null;
    }

    public function findByAccessTokenHash(string $accessTokenHash): ?SessionRecord
    {
        foreach ($this->rows as $row) {
            if ($row->accessTokenHash === $accessTokenHash) {
                return $row;
            }
        }

        return null;
    }

    public function revokeById(string $sessionId, string $reason): void
    {
        if (!isset($this->rows[$sessionId])) {
            return;
        }

        $this->rows[$sessionId] = $this->withRevoked($this->rows[$sessionId], $reason);
    }

    public function revokeByTokenFamily(string $tokenFamilyId, string $reason): int
    {
        $count = 0;

        foreach ($this->rows as $id => $row) {
            if ($row->tokenFamilyId === $tokenFamilyId && !$row->isRevoked()) {
                $this->rows[$id] = $this->withRevoked($row, $reason);
                $count++;
            }
        }

        return $count;
    }

    public function revokeAllForUser(string $userId, string $reason): int
    {
        $count = 0;

        foreach ($this->rows as $id => $row) {
            if ($row->userId === $userId && !$row->isRevoked()) {
                $this->rows[$id] = $this->withRevoked($row, $reason);
                $count++;
            }
        }

        return $count;
    }

    /**
     * @return array<int,SessionRecord>
     */
    public function all(): array
    {
        return array_values($this->rows);
    }

    private function withRevoked(SessionRecord $row, string $reason): SessionRecord
    {
        return new SessionRecord(
            id: $row->id,
            userId: $row->userId,
            refreshTokenHash: $row->refreshTokenHash,
            tokenFamilyId: $row->tokenFamilyId,
            userAgentHash: $row->userAgentHash,
            ipAddressHash: $row->ipAddressHash,
            createdAt: $row->createdAt,
            updatedAt: $this->revokedAt,
            lastUsedAt: $row->lastUsedAt,
            accessExpiresAt: $row->accessExpiresAt,
            refreshExpiresAt: $row->refreshExpiresAt,
            revokedAt: $this->revokedAt,
            revokedReason: $reason,
            accessTokenHash: $row->accessTokenHash,
        );
    }
}

final class InMemoryUserRepositoryForSessionLifecycleTest implements UserRepositoryInterface
{
    /** @var array<string,User> */
    private array $users = [];

    public function add(User $user): void
    {
        $this->users[$user->id] = $user;
    }

    public function findById(string $userId): ?User
    {
        return $this->users[$userId] ?? null;
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

final class InMemoryRoleRepositoryForSessionLifecycleTest implements RoleRepositoryInterface
{
    public function findById(string $roleId): ?Role
    {
        return null;
    }

    public function findByName(string $name): ?Role
    {
        return null;
    }

    public function findByUserId(string $userId): array
    {
        return [
            new Role('rol_lifecycle_local_01', 'operations_expert', 'Operations Expert', null, true, null, null),
        ];
    }
}

final class InMemoryPermissionRepositoryForSessionLifecycleTest implements PermissionRepositoryInterface
{
    public function findById(string $permissionId): ?Permission
    {
        return null;
    }

    public function findByName(string $name): ?Permission
    {
        return null;
    }

    public function findByRoleId(string $roleId): array
    {
        return [];
    }

    public function findPermissionNamesByUserId(string $userId): array
    {
        return ['auth.roles.read'];
    }
}

final class RecordingAuditWriterForSessionLifecycleTest implements AuditWriterInterface
{
    /** @var array<int,AuditEvent> */
    public array $events = [];

    public function write(AuditEvent $event): void
    {
        $this->events[] = $event;
    }
}

final class AllowingRateLimiterForSessionLifecycleTest implements RateLimiterInterface
{
    public function check(RateLimitContext $context): RateLimitDecision
    {
        return RateLimitDecision::allow(4);
    }
}

final class SequentialIdGeneratorForSessionLifecycleTest implements IdGeneratorInterface
{
    private int $next = 1;

    public function generate(): string
    {
        return 'id_lifecycle_' . str_pad((string) $this->next++, 12, '0', STR_PAD_LEFT);
    }
}

final class SessionLifecycleHarness
{
    public MutableInMemorySessionRepositoryForSessionLifecycleTest $sessions;
    public InMemoryUserRepositoryForSessionLifecycleTest $users;
    public RecordingAuditWriterForSessionLifecycleTest $audit;
    public MutableClockForSessionLifecycleTest $clock;
    public HmacTokenHasher $tokenHasher;
    public SequentialIdGeneratorForSessionLifecycleTest $idGenerator;

    public function __construct()
    {
        $this->sessions = new MutableInMemorySessionRepositoryForSessionLifecycleTest();
        $this->users = new InMemoryUserRepositoryForSessionLifecycleTest();
        $this->audit = new RecordingAuditWriterForSessionLifecycleTest();
        $this->clock = new MutableClockForSessionLifecycleTest(
            new DateTimeImmutable('2026-06-14 00:00:00', new DateTimeZone('UTC')),
        );
        $this->tokenHasher = new HmacTokenHasher('local-lifecycle-test-pepper');
        $this->idGenerator = new SequentialIdGeneratorForSessionLifecycleTest();
        $this->users->add(AuthTestData::user(self::passwordHasher()));
    }

    public static function passwordHasher(): PhpPasswordHasher
    {
        return new PhpPasswordHasher(PASSWORD_BCRYPT, ['cost' => 4]);
    }

    public function loginUseCase(): LoginUseCaseInterface
    {
        return new ActiveLoginUseCase(
            users: $this->users,
            passwordHasher: self::passwordHasher(),
            rateLimiter: new AllowingRateLimiterForSessionLifecycleTest(),
            auditWriter: $this->audit,
            sessionIssuer: new DatabaseSessionIssuer(
                sessions: $this->sessions,
                tokenHasher: $this->tokenHasher,
                idGenerator: $this->idGenerator,
                clock: $this->clock,
                sessionIssuingEnabled: true,
                refreshEnabled: true,
                tokenPepperReady: true,
            ),
            clock: $this->clock,
            loginEnabled: true,
        );
    }

    public function refreshUseCase(): ActiveRefreshUseCase
    {
        return new ActiveRefreshUseCase(
            sessions: $this->sessions,
            users: $this->users,
            tokenHasher: $this->tokenHasher,
            rotator: new DatabaseSessionRotator(
                sessions: $this->sessions,
                tokenHasher: $this->tokenHasher,
                idGenerator: $this->idGenerator,
                clock: $this->clock,
                refreshEnabled: true,
                tokenPepperReady: true,
            ),
            auditWriter: $this->audit,
            clock: $this->clock,
            refreshEnabled: true,
        );
    }

    public function meUseCase(): ActiveMeUseCase
    {
        return new ActiveMeUseCase(
            sessions: $this->sessions,
            users: $this->users,
            tokenHasher: $this->tokenHasher,
            roles: new InMemoryRoleRepositoryForSessionLifecycleTest(),
            permissions: new InMemoryPermissionRepositoryForSessionLifecycleTest(),
            clock: $this->clock,
            meEnabled: true,
        );
    }

    public function logoutUseCase(): ActiveLogoutUseCase
    {
        return new ActiveLogoutUseCase(
            sessions: $this->sessions,
            users: $this->users,
            tokenHasher: $this->tokenHasher,
            auditWriter: $this->audit,
            clock: $this->clock,
            logoutEnabled: true,
        );
    }

    /**
     * @return array{session_credential:string,refresh_credential:string}
     */
    public function login(): array
    {
        $result = $this->loginUseCase()->handle(new LoginRequest(
            identifier: AuthTestData::EMAIL,
            submittedPassword: AuthTestData::PASSWORD,
            requestId: 'req_lifecycle_login',
            securityContext: new LoginSecurityContext('192.0.2.77', 'Lifecycle Test Browser', 'req_lifecycle_login'),
        ));

        if (!$result->active()) {
            throw new RuntimeException('Synthetic login failed: ' . $result->errorCode());
        }

        $session = $result->data()['session'];

        return [
            'session_credential' => (string) $session['session_credential'],
            'refresh_credential' => (string) $session['refresh_credential'],
        ];
    }
}

return new class extends TestCase {
    public function testFullLifecycleLoginMeRefreshLogout(): void
    {
        $harness = new SessionLifecycleHarness();
        $credentials = $harness->login();

        // Identity read with the access credential.
        $me = $harness->meUseCase()->handle(new MeRequest(
            bearerCredential: $credentials['session_credential'],
            requestId: 'req_lifecycle_me',
        ));

        Assertions::true($me->active());
        Assertions::same(200, $me->statusCode());
        Assertions::same(AuthTestData::USER_ID, $me->data()['user']['id']);
        Assertions::same('active', $me->data()['user']['status']);
        Assertions::same(['operations_expert'], $me->data()['roles']);
        Assertions::same(['auth.roles.read'], $me->data()['permissions']);
        Assertions::false(str_contains(json_encode($me->toPayload(), JSON_THROW_ON_ERROR), 'password_hash'));

        // Rotate the refresh credential.
        $refresh = $harness->refreshUseCase()->handle(new RefreshRequest(
            submittedRefreshCredential: $credentials['refresh_credential'],
            requestId: 'req_lifecycle_refresh',
        ));

        Assertions::true($refresh->active());
        Assertions::same(200, $refresh->statusCode());
        $rotatedSession = $refresh->data()['session'];
        Assertions::notSame($credentials['session_credential'], $rotatedSession['session_credential']);
        Assertions::notSame($credentials['refresh_credential'], $rotatedSession['refresh_credential']);

        // Old access credential is dead, the new one works.
        $meWithOld = $harness->meUseCase()->handle(new MeRequest($credentials['session_credential'], 'req_lifecycle_me'));
        Assertions::false($meWithOld->active());
        Assertions::same(401, $meWithOld->statusCode());

        $meWithNew = $harness->meUseCase()->handle(new MeRequest(
            bearerCredential: (string) $rotatedSession['session_credential'],
            requestId: 'req_lifecycle_me',
        ));
        Assertions::true($meWithNew->active());

        // Logout revokes the current session row.
        $logout = $harness->logoutUseCase()->handle(new LogoutRequest(
            bearerCredential: (string) $rotatedSession['session_credential'],
            requestId: 'req_lifecycle_logout',
        ));

        Assertions::true($logout->active());
        Assertions::same(200, $logout->statusCode());
        Assertions::same('logged_out', $logout->data()['status']);

        $meAfterLogout = $harness->meUseCase()->handle(new MeRequest(
            bearerCredential: (string) $rotatedSession['session_credential'],
            requestId: 'req_lifecycle_me',
        ));
        Assertions::same(401, $meAfterLogout->statusCode());

        $refreshAfterLogout = $harness->refreshUseCase()->handle(new RefreshRequest(
            submittedRefreshCredential: (string) $rotatedSession['refresh_credential'],
            requestId: 'req_lifecycle_refresh',
        ));
        Assertions::same(401, $refreshAfterLogout->statusCode());
    }

    public function testRefreshTokenReuseRevokesWholeTokenFamily(): void
    {
        $harness = new SessionLifecycleHarness();
        $credentials = $harness->login();

        $first = $harness->refreshUseCase()->handle(new RefreshRequest(
            submittedRefreshCredential: $credentials['refresh_credential'],
            requestId: 'req_reuse_first',
        ));
        Assertions::true($first->active());

        // Replay the already-rotated credential: this must be treated as reuse.
        $reuse = $harness->refreshUseCase()->handle(new RefreshRequest(
            submittedRefreshCredential: $credentials['refresh_credential'],
            requestId: 'req_reuse_second',
        ));

        Assertions::same(401, $reuse->statusCode());
        Assertions::same('invalid_credentials', $reuse->errorCode());

        // Every row of the family is revoked.
        $rows = $harness->sessions->all();
        Assertions::same(2, count($rows));

        foreach ($rows as $row) {
            Assertions::true($row->isRevoked());
            Assertions::true(in_array($row->revokedReason, ['reuse_detected', 'rotated'], true));
        }

        // The newest credential is dead too.
        $newest = $harness->refreshUseCase()->handle(new RefreshRequest(
            submittedRefreshCredential: (string) $first->data()['session']['refresh_credential'],
            requestId: 'req_reuse_third',
        ));
        Assertions::same(401, $newest->statusCode());

        // The reuse event is audited.
        $reuseEvents = array_filter(
            $harness->audit->events,
            static fn (AuditEvent $event): bool => $event->type()->value === 'auth.token_refresh.reuse_detected',
        );
        Assertions::same(1, count($reuseEvents));
    }

    public function testUnknownRefreshCredentialIsIndistinguishable(): void
    {
        $harness = new SessionLifecycleHarness();
        $harness->login();

        $unknown = $harness->refreshUseCase()->handle(new RefreshRequest(
            submittedRefreshCredential: 'unknown-lifecycle-credential',
            requestId: 'req_unknown',
        ));
        $missing = $harness->refreshUseCase()->handle(new RefreshRequest(
            submittedRefreshCredential: '',
            requestId: 'req_missing',
        ));

        Assertions::same(401, $unknown->statusCode());
        Assertions::same('invalid_credentials', $unknown->errorCode());
        Assertions::same(401, $missing->statusCode());
        Assertions::same($unknown->toErrorPayload(), $missing->toErrorPayload());
        Assertions::false(str_contains(json_encode($unknown->toPayload(), JSON_THROW_ON_ERROR), 'unknown-lifecycle-credential'));
    }

    public function testExpiredRefreshCredentialIsRejected(): void
    {
        $harness = new SessionLifecycleHarness();
        $credentials = $harness->login();

        $harness->clock->advance(1209601);

        $expired = $harness->refreshUseCase()->handle(new RefreshRequest(
            submittedRefreshCredential: $credentials['refresh_credential'],
            requestId: 'req_expired',
        ));

        Assertions::same(401, $expired->statusCode());
        Assertions::same('invalid_credentials', $expired->errorCode());
    }

    public function testRefreshFailsClosedForPendingUser(): void
    {
        $harness = new SessionLifecycleHarness();
        $pendingUser = AuthTestData::user(SessionLifecycleHarness::passwordHasher(), status: 'pending');
        $harness->users->add($pendingUser);

        // Issue a session directly, bypassing login, to isolate refresh behavior.
        $issuer = new DatabaseSessionIssuer(
            sessions: $harness->sessions,
            tokenHasher: $harness->tokenHasher,
            idGenerator: $harness->idGenerator,
            clock: $harness->clock,
            sessionIssuingEnabled: true,
            refreshEnabled: true,
            tokenPepperReady: true,
        );
        $issued = $issuer->issue($pendingUser, new LoginSecurityContext('192.0.2.78', 'Lifecycle Test Browser', 'req_pending'));
        Assertions::true($issued->issuedSuccessfully());

        $refresh = $harness->refreshUseCase()->handle(new RefreshRequest(
            submittedRefreshCredential: (string) $issued->refreshCredential(),
            requestId: 'req_pending_refresh',
        ));

        Assertions::same(401, $refresh->statusCode());
        Assertions::same('invalid_credentials', $refresh->errorCode());
    }

    public function testLogoutAllRevokesEverySessionOfTheUser(): void
    {
        $harness = new SessionLifecycleHarness();
        $firstLogin = $harness->login();
        $secondLogin = $harness->login();

        Assertions::same(2, count($harness->sessions->all()));

        $logout = $harness->logoutUseCase()->handle(new LogoutRequest(
            bearerCredential: $secondLogin['session_credential'],
            allSessions: true,
            requestId: 'req_logout_all',
        ));

        Assertions::true($logout->active());
        Assertions::same(2, $logout->data()['revoked_sessions']);

        foreach ($harness->sessions->all() as $row) {
            Assertions::true($row->isRevoked());
            Assertions::same('logout_all', $row->revokedReason);
        }
    }

    public function testMeAndLogoutRequireSubmittedCredential(): void
    {
        $harness = new SessionLifecycleHarness();
        $harness->login();

        $me = $harness->meUseCase()->handle(new MeRequest(null, 'req_me_missing'));
        Assertions::same(401, $me->statusCode());
        Assertions::same('auth_required', $me->errorCode());

        $logout = $harness->logoutUseCase()->handle(new LogoutRequest(null, false, 'req_logout_missing'));
        Assertions::same(401, $logout->statusCode());
        Assertions::same('auth_required', $logout->errorCode());

        $meInvalid = $harness->meUseCase()->handle(new MeRequest('not-a-real-credential', 'req_me_invalid'));
        Assertions::same(401, $meInvalid->statusCode());
        Assertions::same('invalid_credentials', $meInvalid->errorCode());
    }

    public function testUseCasesStayInactiveWhenTheirFlagsAreOff(): void
    {
        $harness = new SessionLifecycleHarness();

        $refresh = new ActiveRefreshUseCase(
            sessions: $harness->sessions,
            users: $harness->users,
            tokenHasher: $harness->tokenHasher,
            rotator: new DatabaseSessionRotator($harness->sessions, $harness->tokenHasher),
            auditWriter: $harness->audit,
            refreshEnabled: false,
        );
        Assertions::same(501, $refresh->handle(new RefreshRequest('anything', 'req'))->statusCode());

        $logout = new ActiveLogoutUseCase(
            sessions: $harness->sessions,
            users: $harness->users,
            tokenHasher: $harness->tokenHasher,
            auditWriter: $harness->audit,
            logoutEnabled: false,
        );
        Assertions::same(501, $logout->handle(new LogoutRequest('anything', false, 'req'))->statusCode());

        $me = new ActiveMeUseCase(
            sessions: $harness->sessions,
            users: $harness->users,
            tokenHasher: $harness->tokenHasher,
            roles: new InMemoryRoleRepositoryForSessionLifecycleTest(),
            permissions: new InMemoryPermissionRepositoryForSessionLifecycleTest(),
            meEnabled: false,
        );
        Assertions::same(501, $me->handle(new MeRequest('anything', 'req'))->statusCode());
    }

    public function testAuditTrailNeverContainsCredentialValues(): void
    {
        $harness = new SessionLifecycleHarness();
        $credentials = $harness->login();

        $harness->refreshUseCase()->handle(new RefreshRequest(
            submittedRefreshCredential: $credentials['refresh_credential'],
            requestId: 'req_audit',
        ));
        $harness->logoutUseCase()->handle(new LogoutRequest(
            bearerCredential: $credentials['session_credential'],
            requestId: 'req_audit',
        ));

        $serialized = json_encode(
            array_map(
                static fn (AuditEvent $event): array => [
                    'type' => $event->type()->value,
                    'result' => $event->result(),
                    'metadata' => $event->metadata(),
                ],
                $harness->audit->events,
            ),
            JSON_THROW_ON_ERROR,
        );

        Assertions::false(str_contains($serialized, $credentials['session_credential']));
        Assertions::false(str_contains($serialized, $credentials['refresh_credential']));
        Assertions::false(str_contains($serialized, AuthTestData::PASSWORD));
    }
};
