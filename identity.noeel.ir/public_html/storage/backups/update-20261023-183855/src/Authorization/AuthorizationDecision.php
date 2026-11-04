<?php
declare(strict_types=1);

namespace Babanoell\Auth\Authorization;

final class AuthorizationDecision
{
    private function __construct(
        private readonly bool $allowed,
        private readonly string $code,
        private readonly string $reason,
    ) {
    }

    public static function allow(string $reason = 'Allowed.'): self
    {
        return new self(true, 'allowed', $reason);
    }

    public static function deny(string $code = 'authorization_denied', string $reason = 'Access denied.'): self
    {
        return new self(false, $code, $reason);
    }

    public static function defaultDeny(): self
    {
        return self::deny('authorization_inactive', 'Authorization is not active for this request.');
    }

    public function allowed(): bool
    {
        return $this->allowed;
    }

    public function code(): string
    {
        return $this->code;
    }

    public function reason(): string
    {
        return $this->reason;
    }
}
