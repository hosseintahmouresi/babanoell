<?php
declare(strict_types=1);

namespace Babanoell\Auth\UseCases\Me;

use Babanoell\Auth\UseCases\Login\LoginSecurityContext;
use SensitiveParameter;

final class MeRequest
{
    public function __construct(
        #[SensitiveParameter]
        private readonly ?string $bearerCredential = null,
        private readonly ?string $requestId = null,
        private readonly ?LoginSecurityContext $securityContext = null,
    ) {
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

    public function requestId(): ?string
    {
        return $this->requestId;
    }

    public function securityContext(): LoginSecurityContext
    {
        return $this->securityContext ?? new LoginSecurityContext(requestId: $this->requestId);
    }
}
