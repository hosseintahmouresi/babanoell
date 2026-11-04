<?php
declare(strict_types=1);

namespace Babanoell\Auth\UseCases\Login;

use Babanoell\Auth\Models\SessionRecord;
use Babanoell\Auth\Models\User;
use Babanoell\Auth\Repositories\SessionRepositoryInterface;
use Babanoell\Auth\Security\SecureTokenGenerator;
use Babanoell\Auth\Security\TokenHasherInterface;
use Babanoell\Auth\Support\ClockInterface;
use Babanoell\Auth\Support\IdGeneratorInterface;
use Babanoell\Auth\Support\RandomIdGenerator;
use Babanoell\Auth\Support\SystemClock;
use DateInterval;

final class DatabaseSessionIssuer implements SessionIssuerInterface
{
    public function __construct(
        private readonly SessionRepositoryInterface $sessions,
        private readonly TokenHasherInterface $tokenHasher,
        private readonly SecureTokenGenerator $tokenGenerator = new SecureTokenGenerator(),
        private readonly IdGeneratorInterface $idGenerator = new RandomIdGenerator(),
        private readonly ClockInterface $clock = new SystemClock(),
        private readonly bool $sessionIssuingEnabled = false,
        private readonly bool $refreshEnabled = false,
        private readonly bool $tokenPepperReady = false,
        private readonly int $sessionTtlSeconds = 900,
        private readonly int $refreshTtlSeconds = 1209600,
    ) {
    }

    public function issue(User $user, LoginSecurityContext $context): SessionIssueResult
    {
        if (!$this->sessionIssuingEnabled) {
            return SessionIssueResult::inactive();
        }

        if (!$this->tokenPepperReady) {
            return SessionIssueResult::unavailable('token_pepper_missing');
        }

        $now = $this->clock->now();
        $sessionCredential = $this->tokenGenerator->generate();
        $refreshCredential = $this->refreshEnabled ? $this->tokenGenerator->generate() : null;
        $storedCredential = $refreshCredential ?? $sessionCredential;
        $accessExpiresAt = $now->add(new DateInterval('PT' . max(1, $this->sessionTtlSeconds) . 'S'));
        $refreshExpiresAt = $now->add(new DateInterval('PT' . max(1, $this->refreshTtlSeconds) . 'S'));
        $sessionId = $this->idGenerator->generate();

        $this->sessions->create(new SessionRecord(
            id: $sessionId,
            userId: $user->id,
            refreshTokenHash: $this->tokenHasher->hash($storedCredential),
            tokenFamilyId: $this->idGenerator->generate(),
            userAgentHash: $this->hashNullable($context->userAgent()),
            ipAddressHash: $this->hashNullable($context->clientSource()),
            createdAt: $now,
            updatedAt: $now,
            lastUsedAt: null,
            accessExpiresAt: $accessExpiresAt,
            refreshExpiresAt: $refreshExpiresAt,
            revokedAt: null,
            revokedReason: null,
            accessTokenHash: $this->tokenHasher->hash($sessionCredential),
        ));

        return SessionIssueResult::issued(
            $sessionId,
            $sessionCredential,
            $refreshCredential,
            $accessExpiresAt,
            $refreshExpiresAt,
        );
    }

    private function hashNullable(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return hash('sha256', $value);
    }
}
