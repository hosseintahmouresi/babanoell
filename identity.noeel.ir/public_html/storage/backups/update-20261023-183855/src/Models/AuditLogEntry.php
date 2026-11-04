<?php
declare(strict_types=1);

namespace Babanoell\Auth\Models;

use DateTimeImmutable;

final class AuditLogEntry
{
    use DateTimeValue;

    /**
     * @param array<string,mixed> $metadata
     */
    public function __construct(
        public readonly string $id,
        public readonly string $eventType,
        public readonly ?string $actorUserId,
        public readonly ?string $targetUserId,
        public readonly ?string $sessionId,
        public readonly ?string $ipAddressHash,
        public readonly ?string $userAgentHash,
        public readonly string $result,
        public readonly array $metadata,
        public readonly ?DateTimeImmutable $createdAt
    ) {
    }

    /**
     * @param array<string,mixed> $row
     */
    public static function fromRow(array $row): self
    {
        $metadata = [];
        $metadataJson = $row['metadata_json'] ?? null;

        if (is_string($metadataJson) && $metadataJson !== '') {
            $decoded = json_decode($metadataJson, true);
            $metadata = is_array($decoded) ? $decoded : [];
        }

        return new self(
            id: (string) $row['id'],
            eventType: (string) $row['event_type'],
            actorUserId: self::nullableString($row['actor_user_id'] ?? null),
            targetUserId: self::nullableString($row['target_user_id'] ?? null),
            sessionId: self::nullableString($row['session_id'] ?? null),
            ipAddressHash: self::nullableString($row['ip_address_hash'] ?? null),
            userAgentHash: self::nullableString($row['user_agent_hash'] ?? null),
            result: (string) $row['result'],
            metadata: $metadata,
            createdAt: self::optionalDateTime($row['created_at'] ?? null)
        );
    }

    private static function nullableString(mixed $value): ?string
    {
        return $value === null || $value === '' ? null : (string) $value;
    }
}
