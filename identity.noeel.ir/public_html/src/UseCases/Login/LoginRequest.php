<?php
declare(strict_types=1);

namespace Babanoell\Auth\UseCases\Login;

use Babanoell\Auth\Security\PasswordHasherInterface;
use SensitiveParameter;

final class LoginRequest
{
    public function __construct(
        private readonly string $identifier,
        #[SensitiveParameter]
        private readonly string $submittedPassword,
        #[SensitiveParameter]
        private readonly ?string $secondFactorCode = null,
        private readonly ?string $requestId = null,
        private readonly ?LoginSecurityContext $securityContext = null,
    ) {
    }

    /**
     * @param array<string,mixed> $payload
     */
    public static function fromPayload(array $payload, ?string $requestId = null, ?LoginSecurityContext $securityContext = null): self
    {
        return new self(
            identifier: trim((string) ($payload['identifier'] ?? '')),
            submittedPassword: (string) ($payload['password'] ?? ''),
            secondFactorCode: isset($payload['second_factor_code']) ? (string) $payload['second_factor_code'] : null,
            requestId: $requestId,
            securityContext: $securityContext,
        );
    }

    public function identifier(): string
    {
        return $this->identifier;
    }

    public function passwordSubmitted(): bool
    {
        return $this->submittedPassword !== '';
    }

    public function submittedPasswordMatches(PasswordHasherInterface $passwordHasher, string $storedHash): bool
    {
        return $passwordHasher->verify($this->submittedPassword, $storedHash);
    }

    public function secondFactorSubmitted(): bool
    {
        return $this->secondFactorCode !== null && $this->secondFactorCode !== '';
    }

    public function requestId(): ?string
    {
        return $this->requestId;
    }

    public function securityContext(): LoginSecurityContext
    {
        return $this->securityContext ?? new LoginSecurityContext(requestId: $this->requestId);
    }
}
