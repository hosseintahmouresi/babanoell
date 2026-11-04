<?php
declare(strict_types=1);

use Babanoell\Auth\Audit\AuditEvent;
use Babanoell\Auth\Audit\AuditWriterInterface;
use Babanoell\Auth\Models\Permission;
use Babanoell\Auth\Models\Role;
use Babanoell\Auth\Models\SessionRecord;
use Babanoell\Auth\Models\User;
use Babanoell\Auth\App\App;
use Babanoell\Auth\App\Config;
use Babanoell\Auth\App\Container;
use Babanoell\Auth\Http\Request;
use Babanoell\Auth\RateLimit\RateLimitContext;
use Babanoell\Auth\RateLimit\RateLimitDecision;
use Babanoell\Auth\RateLimit\RateLimiterInterface;
use Babanoell\Auth\Repositories\PermissionRepositoryInterface;
use Babanoell\Auth\Repositories\RoleRepositoryInterface;
use Babanoell\Auth\Repositories\SessionRepositoryInterface;
use Babanoell\Auth\Repositories\UserRepositoryInterface;
use Babanoell\Auth\Security\HmacTokenHasher;
use Babanoell\Auth\Security\PhpPasswordHasher;
use Babanoell\Auth\Tests\Assertions;
use Babanoell\Auth\Tests\Fixtures\AuthTestData;
use Babanoell\Auth\Tests\TestCase;

final class MutableSessionRepositoryForSessionHttpWiringTest implements SessionRepositoryInterface
{
    /** @var array<string,SessionRecord> */
    public array $rows = [];

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
        $this->rows[$sessionId] = $this->withRevoked($this->rows[$sessionId] ?? null, $reason);
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

    private function withRevoked(?SessionRecord $row, string $reason): ?SessionRecord
    {
        if ($row === null) {
            return null;
        }

        return new SessionRecord(
            id: $row->id,
            userId: $row->userId,
            refreshTokenHash: $row->refreshTokenHash,
            tokenFamilyId: $row->tokenFamilyId,
            userAgentHash: $row->userAgentHash,
            ipAddressHash: $row->ipAddressHash,
            createdAt: $row->createdAt,
            updatedAt: $row->updatedAt,
            lastUsedAt: $row->lastUsedAt,
            accessExpiresAt: $row->accessExpiresAt,
            refreshExpiresAt: $row->refreshExpiresAt,
            revokedAt: new DateTimeImmutable('2026-06-14 00:00:00', new DateTimeZone('UTC')),
            revokedReason: $reason,
            accessTokenHash: $row->accessTokenHash,
        );
    }
}

final class InMemoryUserRepositoryForSessionHttpWiringTest implements UserRepositoryInterface
{
    /** @param list<User> $users */
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

final class InMemoryRoleRepositoryForSessionHttpWiringTest implements RoleRepositoryInterface
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
        return [];
    }
}

final class InMemoryPermissionRepositoryForSessionHttpWiringTest implements PermissionRepositoryInterface
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
        return [];
    }
}

final class RecordingAuditWriterForSessionHttpWiringTest implements AuditWriterInterface
{
    /** @var array<int,AuditEvent> */
    public array $events = [];

    public function write(AuditEvent $event): void
    {
        $this->events[] = $event;
    }
}

final class AllowingRateLimiterForSessionHttpWiringTest implements RateLimiterInterface
{
    public function check(RateLimitContext $context): RateLimitDecision
    {
        return RateLimitDecision::allow(4);
    }
}

return new class extends TestCase {
    public function testSessionEndpointsStayInactiveWhenSessionIssuingIsDisabled(): void
    {
        $app = $this->app(['session_issuing_enabled' => false]);

        Assertions::same(501, $app->handle($this->request('POST', '/api/v1/auth/refresh', [
            'content-type' => 'application/json',
        ], '{"refresh_token":"synthetic-refresh-token"}'))->statusCode());
        Assertions::same(501, $app->handle($this->request('GET', '/api/v1/auth/me', [
            'authorization' => 'Bearer synthetic-access-token',
        ]))->statusCode());
        Assertions::same(501, $app->handle($this->request('POST', '/api/v1/auth/logout', [
            'authorization' => 'Bearer synthetic-access-token',
            'content-type' => 'application/json',
        ], '{}'))->statusCode());
    }

    public function testRefreshStaysInactiveWithoutRefreshFlag(): void
    {
        $app = $this->app(['refresh_enabled' => false]);

        $refresh = $app->handle($this->request('POST', '/api/v1/auth/refresh', [
            'content-type' => 'application/json',
        ], '{"refresh_token":"synthetic-refresh-token"}'));

        Assertions::same(501, $refresh->statusCode());
        Assertions::same('not_implemented', json_decode($refresh->body(), true)['error']['code']);
    }

    public function testFullHttpSessionLifecycle(): void
    {
        $sessions = new MutableSessionRepositoryForSessionHttpWiringTest();
        $audit = new RecordingAuditWriterForSessionHttpWiringTest();
        $app = $this->app([], sessions: $sessions, auditWriter: $audit);

        // Login returns both credentials when refresh is enabled.
        $login = $app->handle($this->request('POST', '/api/v1/auth/login', [
            'content-type' => 'application/json',
            'x-request-id' => 'req_http_lifecycle_login',
        ], json_encode([
            'identifier' => AuthTestData::EMAIL,
            'password' => AuthTestData::PASSWORD,
        ], JSON_THROW_ON_ERROR)));
        $loginPayload = json_decode($login->body(), true, flags: JSON_THROW_ON_ERROR);

        Assertions::same(200, $login->statusCode());
        Assertions::true(is_string($loginPayload['data']['session']['session_credential']));
        Assertions::true(is_string($loginPayload['data']['session']['refresh_credential']));
        $sessionCredential = $loginPayload['data']['session']['session_credential'];
        $refreshCredential = $loginPayload['data']['session']['refresh_credential'];

        // Current identity via Bearer credential.
        $me = $app->handle($this->request('GET', '/api/v1/auth/me', [
            'authorization' => 'Bearer ' . $sessionCredential,
        ]));
        $mePayload = json_decode($me->body(), true, flags: JSON_THROW_ON_ERROR);

        Assertions::same(200, $me->statusCode());
        Assertions::same(AuthTestData::USER_ID, $mePayload['data']['user']['id']);
        Assertions::false(str_contains($me->body(), 'password_hash'));

        // Refresh rotates both credentials over HTTP.
        $refresh = $app->handle($this->request('POST', '/api/v1/auth/refresh', [
            'content-type' => 'application/json',
        ], json_encode(['refresh_token' => $refreshCredential], JSON_THROW_ON_ERROR)));
        $refreshPayload = json_decode($refresh->body(), true, flags: JSON_THROW_ON_ERROR);

        Assertions::same(200, $refresh->statusCode());
        $newSessionCredential = $refreshPayload['data']['session']['session_credential'];
        $newRefreshCredential = $refreshPayload['data']['session']['refresh_credential'];
        Assertions::notSame($sessionCredential, $newSessionCredential);
        Assertions::notSame($refreshCredential, $newRefreshCredential);

        // The rotated-out access credential no longer identifies the user.
        $meWithOld = $app->handle($this->request('GET', '/api/v1/auth/me', [
            'authorization' => 'Bearer ' . $sessionCredential,
        ]));
        Assertions::same(401, $meWithOld->statusCode());

        // Logout with the current Bearer credential.
        $logout = $app->handle($this->request('POST', '/api/v1/auth/logout', [
            'authorization' => 'Bearer ' . $newSessionCredential,
            'content-type' => 'application/json',
        ], '{}'));

        Assertions::same(200, $logout->statusCode());
        Assertions::same('logged_out', json_decode($logout->body(), true)['data']['status']);

        // Everything is revoked afterwards.
        $meAfterLogout = $app->handle($this->request('GET', '/api/v1/auth/me', [
            'authorization' => 'Bearer ' . $newSessionCredential,
        ]));
        Assertions::same(401, $meAfterLogout->statusCode());

        $refreshAfterLogout = $app->handle($this->request('POST', '/api/v1/auth/refresh', [
            'content-type' => 'application/json',
        ], json_encode(['refresh_token' => $newRefreshCredential], JSON_THROW_ON_ERROR)));
        Assertions::same(401, $refreshAfterLogout->statusCode());

        // No credential value ever reaches the audit trail.
        $serialized = json_encode(
            array_map(
                static fn (AuditEvent $event): array => [
                    'type' => $event->type()->value,
                    'metadata' => $event->metadata(),
                ],
                $audit->events,
            ),
            JSON_THROW_ON_ERROR,
        );
        Assertions::false(str_contains($serialized, $sessionCredential));
        Assertions::false(str_contains($serialized, $refreshCredential));
        Assertions::false(str_contains($serialized, $newSessionCredential));
    }

    public function testRefreshTokenReuseIsRejectedOverHttpAndRevokesFamily(): void
    {
        $app = $this->app([]);

        $login = $app->handle($this->request('POST', '/api/v1/auth/login', [
            'content-type' => 'application/json',
        ], json_encode([
            'identifier' => AuthTestData::EMAIL,
            'password' => AuthTestData::PASSWORD,
        ], JSON_THROW_ON_ERROR)));
        $firstRefreshCredential = json_decode($login->body(), true)['data']['session']['refresh_credential'];

        // First use rotates the credential.
        $refresh = $app->handle($this->request('POST', '/api/v1/auth/refresh', [
            'content-type' => 'application/json',
        ], json_encode(['refresh_token' => $firstRefreshCredential], JSON_THROW_ON_ERROR)));
        Assertions::same(200, $refresh->statusCode());
        $newRefreshCredential = json_decode($refresh->body(), true)['data']['session']['refresh_credential'];

        // Replaying the rotated credential is rejected.
        $reuse = $app->handle($this->request('POST', '/api/v1/auth/refresh', [
            'content-type' => 'application/json',
        ], json_encode(['refresh_token' => $firstRefreshCredential], JSON_THROW_ON_ERROR)));

        Assertions::same(401, $reuse->statusCode());
        Assertions::same('invalid_credentials', json_decode($reuse->body(), true)['error']['code']);

        // Even the newest refresh credential is dead after reuse detection.
        $afterReuse = $app->handle($this->request('POST', '/api/v1/auth/refresh', [
            'content-type' => 'application/json',
        ], json_encode(['refresh_token' => $newRefreshCredential], JSON_THROW_ON_ERROR)));

        Assertions::same(401, $afterReuse->statusCode());
    }

    public function testMeWithoutBearerCredentialIsRejected(): void
    {
        $app = $this->app([]);

        $me = $app->handle($this->request('GET', '/api/v1/auth/me', []));
        $payload = json_decode($me->body(), true, flags: JSON_THROW_ON_ERROR);

        Assertions::same(401, $me->statusCode());
        Assertions::same('auth_required', $payload['error']['code']);
    }

    public function testLogoutWithoutBearerCredentialIsRejected(): void
    {
        $app = $this->app([]);

        $logout = $app->handle($this->request('POST', '/api/v1/auth/logout', [
            'content-type' => 'application/json',
        ], '{}'));
        $payload = json_decode($logout->body(), true, flags: JSON_THROW_ON_ERROR);

        Assertions::same(401, $logout->statusCode());
        Assertions::same('auth_required', $payload['error']['code']);
    }

    /**
     * @param array<string,mixed> $securityOverrides
     */
    private function app(
        array $securityOverrides = [],
        ?SessionRepositoryInterface $sessions = null,
        ?AuditWriterInterface $auditWriter = null,
    ): App {
        $passwordHasher = new PhpPasswordHasher(PASSWORD_BCRYPT, ['cost' => 4]);
        $security = array_replace([
            'auth_environment' => 'local',
            'allow_http_login_in_local' => true,
            'login_enabled' => true,
            'session_issuing_enabled' => true,
            'refresh_enabled' => true,
            'require_token_pepper' => true,
            'token_pepper' => 'local-http-session-test-pepper',
            'session_ttl_seconds' => 900,
            'refresh_ttl_seconds' => 1209600,
            'login_rate_limit_window_seconds' => 900,
            'login_rate_limit_max_attempts' => 5,
        ], $securityOverrides);

        return App::createFromContainer(new Container(
            new Config('auth-service', 'local', $security),
            users: new InMemoryUserRepositoryForSessionHttpWiringTest([
                AuthTestData::user($passwordHasher),
            ]),
            sessions: $sessions ?? new MutableSessionRepositoryForSessionHttpWiringTest(),
            auditWriter: $auditWriter ?? new RecordingAuditWriterForSessionHttpWiringTest(),
            rateLimiter: new AllowingRateLimiterForSessionHttpWiringTest(),
            passwordHasher: $passwordHasher,
            tokenHasher: new HmacTokenHasher('local-http-session-test-pepper'),
            roles: new InMemoryRoleRepositoryForSessionHttpWiringTest(),
            permissions: new InMemoryPermissionRepositoryForSessionHttpWiringTest(),
        ));
    }

    /**
     * @param array<string,string> $headers
     */
    private function request(string $method, string $path, array $headers = [], string $body = ''): Request
    {
        return new Request($method, $path, $headers, $body);
    }
};
