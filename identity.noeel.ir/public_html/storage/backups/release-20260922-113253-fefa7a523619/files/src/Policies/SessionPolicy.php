<?php
declare(strict_types=1);

namespace Babanoell\Auth\Policies;

use Babanoell\Auth\Services\SessionPolicyInterface;

final class SessionPolicy implements SessionPolicyInterface
{
    public function __construct(
        private readonly bool $active = false,
        private readonly int $accessTokenTtlSeconds = 0,
        private readonly int $refreshTokenTtlSeconds = 0,
    ) {
    }

    public function accessTokenTtlSeconds(): int
    {
        if (!$this->active) {
            return 0;
        }

        return max(0, $this->accessTokenTtlSeconds);
    }

    public function refreshTokenTtlSeconds(): int
    {
        if (!$this->active) {
            return 0;
        }

        return max(0, $this->refreshTokenTtlSeconds);
    }
}
