<?php
declare(strict_types=1);

namespace Babanoell\Auth\UseCases\Refresh;

use Babanoell\Auth\Models\SessionRecord;
use Babanoell\Auth\Repositories\SessionRepositoryInterface;
use Babanoell\Auth\Security\SecureTokenGenerator;
use Babanoell\Auth\Security\TokenHasherInterface;
use Babanoell\Auth\Support\ClockInterface;
use Babanoell\Auth\Support\IdGeneratorInterface;
use Babanoell\Auth\Support\RandomIdGenerator;
use Babanoell\Auth\Support\SystemClock;
use Babanoell\Auth\UseCases\Login\LoginSecurityContext;
use Babanoell\Auth\UseCases\Login\SessionIssueResult;
use DateInterval;
use Throwable;

final class DatabaseSessionRotator implements SessionRotatorInterface
{
    public function __construct(
        private readonly SessionRepositoryInterface $sessions,
        private readonly TokenHasherInterface $tokenHasher,
        private readonly SecureTokenGenerator $tokenGenerator = new SecureTokenGenerator(),
        private readonly IdGeneratorInterface $idGenerator = new RandomIdGenerator(),
        private readonly ClockInterface $clock = new SystemClock(),
        private readonly bool $refreshEnabled = false,
        private readonly bool $tokenPepperReady = false,
        private readonly int $sessionTtlSeconds = 900,
        private readonly int $refreshTtlSeconds = 1209600,
    ) {
    }

    public function rotate(SessionRecord $session, ?LoginSecurityContext $context = null): SessionIssueResult
    {
        if (!$this->refreshEnabled) {
            return SessionIssueResult::inactive();
        }

        if (!$this->tokenPepperReady) {
            return SessionIssueResult::unavailable('token_pepper_missing');
        }

        $now = $this->clock->now();
        $sessionCredential = $this->tokenGenerator->generate();
        $refreshCredential = $this->tokenGenerator->generate();

        try {
            // Fail-closed: revoke the current row first. If creating the
            // successor row then fails, no credential from the old row
            // stays usable and the client must authenticate again.
            $this->sessions->revokeById($session->id, 'rotated');
            $successorId = $this->idGenerator->generate();
            $accessExpiresAt = $now->add(new DateInterval('PT' . max(1, $this->sessionTtlSeconds) . 'S'));
            $refreshExpiresAt = $now->add(new DateInterval('PT' . max(1, $this->refreshTtlSeconds) . 'S'));

            $this->sessions->create(new SessionRecord(
                id: $successorId,
                userId: $session->userId,
                refreshTokenHash: $this->tokenHasher->hash($refreshCredential),
                tokenFamilyId: $session->tokenFamilyId,
                userAgentHash: $this->hashNullable($context?->userAgent()) ?? $session->userAgentHash,
                ipAddressHash: $this->hashNullable($context?->clientSource()) ?? $session->ipAddressHash,
                createdAt: $now,
                updatedAt: $now,
                lastUsedAt: $now,
                accessExpiresAt: $accessExpiresAt,
                refreshExpiresAt: $refreshExpiresAt,
                revokedAt: null,
                revokedReason: null,
                accessTokenHash: $this->tokenHasher->hash($sessionCredential),
            ));
        } catch (Throwable) {
            return SessionIssueResult::unavailable('session_rotation_failed');
        }

        return SessionIssueResult::issued(
            $successorId,
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
