<?php
declare(strict_types=1);

namespace Babanoell\Auth\UseCases\Refresh;

use Babanoell\Auth\UseCases\Login\LoginSecurityContext;
use SensitiveParameter;

final class RefreshRequest
{
    public function __construct(
        #[SensitiveParameter]
        private readonly ?string $submittedRefreshCredential = null,
        private readonly ?string $requestId = null,
        private readonly ?LoginSecurityContext $securityContext = null,
    ) {
    }

    /**
     * @param array<string,mixed> $payload
     */
    public static function fromPayload(
        array $payload,
        ?string $requestId = null,
        ?LoginSecurityContext $securityContext = null,
    ): self {
        return new self(
            submittedRefreshCredential: isset($payload['refresh_token']) ? (string) $payload['refresh_token'] : null,
            requestId: $requestId,
            securityContext: $securityContext,
        );
    }

    public function refreshCredentialSubmitted(): bool
    {
        return $this->submittedRefreshCredential !== null && trim($this->submittedRefreshCredential) !== '';
    }

    /**
     * Only call after refreshCredentialSubmitted() returned true.
     */
    public function submittedRefreshCredential(): string
    {
        return trim((string) $this->submittedRefreshCredential);
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
