<?php
declare(strict_types=1);

namespace Babanoell\Auth\Audit;

final class AuditContext
{
    public function __construct(
        private readonly ?string $actorUserId = null,
        private readonly ?string $targetUserId = null,
        private readonly ?string $sessionId = null,
        private readonly ?string $ipAddressHash = null,
        private readonly ?string $userAgentHash = null,
    ) {
    }

    public static function fromRawRequestData(
        ?string $actorUserId = null,
        ?string $targetUserId = null,
        ?string $sessionId = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): self {
        return new self(
            $actorUserId,
            $targetUserId,
            $sessionId,
            self::hashNullable($ipAddress),
            self::hashNullable($userAgent),
        );
    }

    public function actorUserId(): ?string
    {
        return $this->actorUserId;
    }

    public function targetUserId(): ?string
    {
        return $this->targetUserId;
    }

    public function sessionId(): ?string
    {
        return $this->sessionId;
    }

    public function ipAddressHash(): ?string
    {
        return $this->ipAddressHash;
    }

    public function userAgentHash(): ?string
    {
        return $this->userAgentHash;
    }

    private static function hashNullable(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return hash('sha256', $value);
    }
}
