<?php
declare(strict_types=1);

namespace Babanoell\Auth\RateLimit;

final class InactiveRateLimiter implements RateLimiterInterface
{
    public function check(RateLimitContext $context): RateLimitDecision
    {
        return RateLimitDecision::inactive();
    }
}
