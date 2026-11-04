<?php
declare(strict_types=1);

namespace Babanoell\Auth\RateLimit;

final class RateLimitDecision
{
    private function __construct(
        private readonly bool $allowed,
        private readonly string $code,
        private readonly int $remainingAttempts,
        private readonly int $retryAfterSeconds,
    ) {
    }

    public static function allow(int $remainingAttempts): self
    {
        return new self(true, 'allowed', max(0, $remainingAttempts), 0);
    }

    public static function block(string $code = 'rate_limited', int $retryAfterSeconds = 0): self
    {
        return new self(false, $code, 0, max(0, $retryAfterSeconds));
    }

    public static function inactive(): self
    {
        return self::block('rate_limit_inactive');
    }

    public function allowed(): bool
    {
        return $this->allowed;
    }

    public function code(): string
    {
        return $this->code;
    }

    public function remainingAttempts(): int
    {
        return $this->remainingAttempts;
    }

    public function retryAfterSeconds(): int
    {
        return $this->retryAfterSeconds;
    }
}
