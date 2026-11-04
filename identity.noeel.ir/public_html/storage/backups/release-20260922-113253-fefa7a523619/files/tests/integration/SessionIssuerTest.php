<?php
declare(strict_types=1);

use Babanoell\Auth\Models\SessionRecord;
use Babanoell\Auth\Repositories\SessionRepositoryInterface;
use Babanoell\Auth\Security\HmacTokenHasher;
use Babanoell\Auth\Security\PhpPasswordHasher;
use Babanoell\Auth\Support\ClockInterface;
use Babanoell\Auth\Support\IdGeneratorInterface;
use Babanoell\Auth\Tests\Assertions;
use Babanoell\Auth\Tests\Fixtures\AuthTestData;
use Babanoell\Auth\Tests\TestCase;
use Babanoell\Auth\UseCases\Login\DatabaseSessionIssuer;
use Babanoell\Auth\UseCases\Login\LoginSecurityContext;

final class InMemorySessionRepositoryForSessionIssuerTest implements SessionRepositoryInterface
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

final class SequentialIdGeneratorForSessionIssuerTest implements IdGeneratorInterface
{
    private int $next = 1;

    public function generate(): string
    {
        return 'id_local_' . str_pad((string) $this->next++, 18, '0', STR_PAD_LEFT);
    }
}

final class FixedClockForSessionIssuerTest implements ClockInterface
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-06-14 00:00:00', new DateTimeZone('UTC'));
    }
}

return new class extends TestCase {
    public function testSessionIssuerIsInactiveByDefault(): void
    {
        $sessions = new class implements SessionRepositoryInterface {
            public function create(SessionRecord $session): void
            {
                throw new RuntimeException('Session must not be created.');
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
        };

        $issuer = new DatabaseSessionIssuer(
            sessions: $sessions,
            tokenHasher: new HmacTokenHasher('local-test-pepper'),
        );

        $result = $issuer->issue(AuthTestData::user($this->passwordHasher()), new LoginSecurityContext());

        Assertions::false($result->issuedSuccessfully());
        Assertions::same('session_issuing_inactive', $result->failureCode());
    }

    public function testSessionIssuerFailsClosedWhenTokenPepperIsRequiredButUnavailable(): void
    {
        $issuer = new DatabaseSessionIssuer(
            sessions: new InMemorySessionRepositoryForSessionIssuerTest(),
            tokenHasher: new HmacTokenHasher('local-test-pepper'),
            tokenPepperReady: false,
            sessionIssuingEnabled: true,
        );

        $result = $issuer->issue(AuthTestData::user($this->passwordHasher()), new LoginSecurityContext());

        Assertions::false($result->issuedSuccessfully());
        Assertions::same('token_pepper_missing', $result->failureCode());
    }

    public function testSessionIssuerStoresOnlyCredentialHash(): void
    {
        $sessions = new InMemorySessionRepositoryForSessionIssuerTest();
        $issuer = new DatabaseSessionIssuer(
            sessions: $sessions,
            tokenHasher: new HmacTokenHasher('local-test-pepper'),
            idGenerator: new SequentialIdGeneratorForSessionIssuerTest(),
            clock: new FixedClockForSessionIssuerTest(),
            sessionIssuingEnabled: true,
            refreshEnabled: false,
            tokenPepperReady: true,
            sessionTtlSeconds: 900,
            refreshTtlSeconds: 1209600,
        );

        $result = $issuer->issue(AuthTestData::user($this->passwordHasher()), new LoginSecurityContext(
            clientSource: '192.0.2.25',
            userAgent: 'Local Test Browser',
            requestId: 'req_local',
        ));

        Assertions::true($result->issuedSuccessfully());
        Assertions::same(1, count($sessions->created));
        Assertions::false($result->sessionCredential() === null);
        Assertions::false($sessions->created[0]->refreshTokenHash === $result->sessionCredential());
        Assertions::same(null, $result->refreshCredential());
        Assertions::same(hash('sha256', '192.0.2.25'), $sessions->created[0]->ipAddressHash);
        Assertions::same(hash('sha256', 'Local Test Browser'), $sessions->created[0]->userAgentHash);
    }

    private function passwordHasher(): PhpPasswordHasher
    {
        return new PhpPasswordHasher(PASSWORD_BCRYPT, ['cost' => 4]);
    }
};
