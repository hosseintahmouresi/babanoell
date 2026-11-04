<?php
declare(strict_types=1);

namespace Babanoell\Auth\RateLimit;

/** Clears only the caller account's throttle entry AFTER successful authentication. */
interface ResettableRateLimiterInterface extends RateLimiterInterface
{
    public function resetAfterSuccess(RateLimitContext $context): bool;
}
