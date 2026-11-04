<?php
declare(strict_types=1);

namespace Babanoell\Auth\Repositories\Pdo;

use Babanoell\Auth\Models\SessionRecord;
use Babanoell\Auth\Repositories\SessionRepositoryInterface;
use PDO;

final class PdoSessionRepository implements SessionRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function create(SessionRecord $session): void
    {
        $statement = $this->pdo->prepare(<<<'SQL'
INSERT INTO sessions (
    id,
    user_id,
    refresh_token_hash,
    token_family_id,
    user_agent_hash,
    ip_address_hash,
    created_at,
    updated_at,
    last_used_at,
    access_expires_at,
    refresh_expires_at,
    revoked_at,
    revoked_reason,
    access_token_hash
) VALUES (
    :id,
    :user_id,
    :refresh_token_hash,
    :token_family_id,
    :user_agent_hash,
    :ip_address_hash,
    :created_at,
    :updated_at,
    :last_used_at,
    :access_expires_at,
    :refresh_expires_at,
    :revoked_at,
    :revoked_reason,
    :access_token_hash
)
SQL);

        $statement->execute([
            'id' => $session->id,
            'user_id' => $session->userId,
            'refresh_token_hash' => $session->refreshTokenHash,
            'token_family_id' => $session->tokenFamilyId,
            'user_agent_hash' => $session->userAgentHash,
            'ip_address_hash' => $session->ipAddressHash,
            'created_at' => $session->createdAt?->format('Y-m-d H:i:s.u'),
            'updated_at' => $session->updatedAt?->format('Y-m-d H:i:s.u'),
            'last_used_at' => $session->lastUsedAt?->format('Y-m-d H:i:s.u'),
            'access_expires_at' => $session->accessExpiresAt->format('Y-m-d H:i:s.u'),
            'refresh_expires_at' => $session->refreshExpiresAt->format('Y-m-d H:i:s.u'),
            'revoked_at' => $session->revokedAt?->format('Y-m-d H:i:s.u'),
            'revoked_reason' => $session->revokedReason,
            'access_token_hash' => $session->accessTokenHash,
        ]);
    }

    public function findActiveById(string $sessionId): ?SessionRecord
    {
        $statement = $this->pdo->prepare(<<<'SQL'
SELECT *
FROM sessions
WHERE id = :id
  AND revoked_at IS NULL
  AND refresh_expires_at > UTC_TIMESTAMP(6)
LIMIT 1
SQL);
        $statement->execute(['id' => $sessionId]);

        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : SessionRecord::fromRow($row);
    }

    public function findByRefreshTokenHash(string $refreshTokenHash): ?SessionRecord
    {
        $statement = $this->pdo->prepare('SELECT * FROM sessions WHERE refresh_token_hash = :hash LIMIT 1');
        $statement->execute(['hash' => $refreshTokenHash]);

        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : SessionRecord::fromRow($row);
    }

    public function findByAccessTokenHash(string $accessTokenHash): ?SessionRecord
    {
        $statement = $this->pdo->prepare('SELECT * FROM sessions WHERE access_token_hash = :hash LIMIT 1');
        $statement->execute(['hash' => $accessTokenHash]);

        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : SessionRecord::fromRow($row);
    }

    public function revokeById(string $sessionId, string $reason): void
    {
        $statement = $this->pdo->prepare(<<<'SQL'
UPDATE sessions
SET revoked_at = UTC_TIMESTAMP(6),
    revoked_reason = :reason
WHERE id = :id
SQL);
        $statement->execute([
            'id' => $sessionId,
            'reason' => $reason,
        ]);
    }

    public function revokeByTokenFamily(string $tokenFamilyId, string $reason): int
    {
        $statement = $this->pdo->prepare(<<<'SQL'
UPDATE sessions
SET revoked_at = UTC_TIMESTAMP(6),
    revoked_reason = :reason
WHERE token_family_id = :token_family_id
  AND revoked_at IS NULL
SQL);
        $statement->execute([
            'token_family_id' => $tokenFamilyId,
            'reason' => $reason,
        ]);

        return $statement->rowCount();
    }

    public function revokeAllForUser(string $userId, string $reason): int
    {
        $statement = $this->pdo->prepare(<<<'SQL'
UPDATE sessions
SET revoked_at = UTC_TIMESTAMP(6),
    revoked_reason = :reason
WHERE user_id = :user_id
  AND revoked_at IS NULL
SQL);
        $statement->execute([
            'user_id' => $userId,
            'reason' => $reason,
        ]);

        return $statement->rowCount();
    }
}
