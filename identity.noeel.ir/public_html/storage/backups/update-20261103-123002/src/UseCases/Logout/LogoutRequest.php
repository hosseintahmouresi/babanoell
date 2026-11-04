<?php
declare(strict_types=1);

namespace Babanoell\Auth\UseCases\Logout;

use Babanoell\Auth\UseCases\Login\LoginSecurityContext;
use SensitiveParameter;

final class LogoutRequest
{
    public function __construct(
        #[SensitiveParameter]
        private readonly ?string $bearerCredential = null,
        private readonly bool $allSessions = false,
        private readonly ?string $requestId = null,
        private readonly ?LoginSecurityContext $securityContext = null,
    ) {
    }

    /**
     * @param array<string,mixed> $payload
     */
    public static function fromPayload(
        array $payload,
        ?string $bearerCredential = null,
        ?string $requestId = null,
        ?LoginSecurityContext $securityContext = null,
    ): self {
        return new self(
            bearerCredential: $bearerCredential,
            allSessions: (bool) ($payload['all_sessions'] ?? false),
            requestId: $requestId,
            securityContext: $securityContext,
        );
    }

    public function credentialSubmitted(): bool
    {
        return $this->bearerCredential !== null && trim($this->bearerCredential) !== '';
    }

    /**
     * Only call after credentialSubmitted() returned true.
     */
    public function submittedCredential(): string
    {
        return trim((string) $this->bearerCredential);
    }

    public function allSessions(): bool
    {
        return $this->allSessions;
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
