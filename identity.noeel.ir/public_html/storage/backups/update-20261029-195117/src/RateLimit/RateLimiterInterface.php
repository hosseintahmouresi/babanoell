<?php
declare(strict_types=1);

namespace Babanoell\Auth\RateLimit;

interface RateLimiterInterface
{
    public function check(RateLimitContext $context): RateLimitDecision;
}
