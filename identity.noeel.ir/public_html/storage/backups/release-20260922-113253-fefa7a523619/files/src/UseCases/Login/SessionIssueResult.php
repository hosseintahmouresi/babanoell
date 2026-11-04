<?php
declare(strict_types=1);

namespace Babanoell\Auth\UseCases\Login;

use DateTimeImmutable;

final class SessionIssueResult
{
    private function __construct(
        private readonly bool $issued,
        private readonly string $failureCode,
        private readonly ?string $sessionId,
        private readonly ?string $sessionCredential,
        private readonly ?string $refreshCredential,
        private readonly ?DateTimeImmutable $accessExpiresAt,
        private readonly ?DateTimeImmutable $refreshExpiresAt,
    ) {
    }

    public static function inactive(): self
    {
        return new self(false, 'session_issuing_inactive', null, null, null, null, null);
    }

    public static function unavailable(string $failureCode = 'session_issuing_unavailable'): self
    {
        return new self(false, $failureCode, null, null, null, null, null);
    }

    public static function issued(
        string $sessionId,
        string $sessionCredential,
        ?string $refreshCredential,
        DateTimeImmutable $accessExpiresAt,
        DateTimeImmutable $refreshExpiresAt,
    ): self {
        return new self(true, '', $sessionId, $sessionCredential, $refreshCredential, $accessExpiresAt, $refreshExpiresAt);
    }

    public function issuedSuccessfully(): bool
    {
        return $this->issued;
    }

    public function failureCode(): string
    {
        return $this->failureCode;
    }

    public function sessionId(): ?string
    {
        return $this->sessionId;
    }

    public function sessionCredential(): ?string
    {
        return $this->sessionCredential;
    }

    public function refreshCredential(): ?string
    {
        return $this->refreshCredential;
    }

    public function accessExpiresAt(): ?DateTimeImmutable
    {
        return $this->accessExpiresAt;
    }

    public function refreshExpiresAt(): ?DateTimeImmutable
    {
        return $this->refreshExpiresAt;
    }
}
