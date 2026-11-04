<?php
declare(strict_types=1);

namespace Babanoell\Auth\Repositories;

use Babanoell\Auth\Models\SessionRecord;

interface SessionRepositoryInterface
{
    public function create(SessionRecord $session): void;

    public function findActiveById(string $sessionId): ?SessionRecord;

    /**
     * Returns any session row matching the hashed refresh credential,
     * including revoked rows, so rotated-token reuse can be detected.
     */
    public function findByRefreshTokenHash(string $refreshTokenHash): ?SessionRecord;

    public function findByAccessTokenHash(string $accessTokenHash): ?SessionRecord;

    public function revokeById(string $sessionId, string $reason): void;

    /**
     * Revokes every active session in a token family and returns the
     * number of affected rows. Used when refresh-token reuse is detected.
     */
    public function revokeByTokenFamily(string $tokenFamilyId, string $reason): int;

    /**
     * Revokes every active session of a user and returns the number of
     * affected rows. Used by logout-all requests.
     */
    public function revokeAllForUser(string $userId, string $reason): int;
}
