<?php
declare(strict_types=1);

namespace Babanoell\Auth\Models;

use DateTimeImmutable;

final class SessionRecord
{
    use DateTimeValue;

    public function __construct(
        public readonly string $id,
        public readonly string $userId,
        public readonly string $refreshTokenHash,
        public readonly string $tokenFamilyId,
        public readonly ?string $userAgentHash,
        public readonly ?string $ipAddressHash,
        public readonly ?DateTimeImmutable $createdAt,
        public readonly ?DateTimeImmutable $updatedAt,
        public readonly ?DateTimeImmutable $lastUsedAt,
        public readonly DateTimeImmutable $accessExpiresAt,
        public readonly DateTimeImmutable $refreshExpiresAt,
        public readonly ?DateTimeImmutable $revokedAt,
        public readonly ?string $revokedReason,
        public readonly ?string $accessTokenHash = null,
    ) {
    }

    /**
     * @param array<string,mixed> $row
     */
    public static function fromRow(array $row): self
    {
        return new self(
            id: (string) $row['id'],
            userId: (string) $row['user_id'],
            refreshTokenHash: (string) $row['refresh_token_hash'],
            tokenFamilyId: (string) $row['token_family_id'],
            userAgentHash: self::nullableString($row['user_agent_hash'] ?? null),
            ipAddressHash: self::nullableString($row['ip_address_hash'] ?? null),
            createdAt: self::optionalDateTime($row['created_at'] ?? null),
            updatedAt: self::optionalDateTime($row['updated_at'] ?? null),
            lastUsedAt: self::optionalDateTime($row['last_used_at'] ?? null),
            accessExpiresAt: self::optionalDateTime($row['access_expires_at']) ?? new DateTimeImmutable('@0'),
            refreshExpiresAt: self::optionalDateTime($row['refresh_expires_at']) ?? new DateTimeImmutable('@0'),
            revokedAt: self::optionalDateTime($row['revoked_at'] ?? null),
            revokedReason: self::nullableString($row['revoked_reason'] ?? null),
            accessTokenHash: self::nullableString($row['access_token_hash'] ?? null),
        );
    }

    public function isRevoked(): bool
    {
        return $this->revokedAt !== null;
    }

    public function wasRotated(): bool
    {
        return $this->isRevoked() && $this->revokedReason === 'rotated';
    }

    private static function nullableString(mixed $value): ?string
    {
        return $value === null || $value === '' ? null : (string) $value;
    }
}
