<?php
declare(strict_types=1);

namespace Babanoell\Auth\UseCases\Login;

final class LoginSecurityContext
{
    public function __construct(
        private readonly ?string $clientSource = null,
        private readonly ?string $userAgent = null,
        private readonly ?string $requestId = null,
    ) {
    }

    public function clientSource(): ?string
    {
        return $this->clientSource;
    }

    public function userAgent(): ?string
    {
        return $this->userAgent;
    }

    public function requestId(): ?string
    {
        return $this->requestId;
    }
}
