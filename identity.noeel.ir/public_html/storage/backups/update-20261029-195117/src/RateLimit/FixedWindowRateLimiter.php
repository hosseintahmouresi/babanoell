<?php
declare(strict_types=1);

namespace Babanoell\Auth\RateLimit;

use Babanoell\Auth\Support\ClockInterface;
use Babanoell\Auth\Support\SystemClock;

final class FixedWindowRateLimiter implements ResettableRateLimiterInterface
{
    /**
     * @var array<string,array{window_start:int,count:int}>
     */
    private array $counters = [];

    public function __construct(
        private readonly int $limit,
        private readonly int $windowSeconds,
        private readonly ClockInterface $clock = new SystemClock(),
    ) {
    }

    public function check(RateLimitContext $context): RateLimitDecision
    {
        if ($this->limit < 1 || $this->windowSeconds < 1) {
            return RateLimitDecision::block('rate_limit_unavailable');
        }

        $now = $this->clock->now()->getTimestamp();
        $key = $context->key();
        $state = $this->counters[$key] ?? [
            'window_start' => $now,
            'count' => 0,
        ];

        if (($now - $state['window_start']) >= $this->windowSeconds) {
            $state = [
                'window_start' => $now,
                'count' => 0,
            ];
        }

        if ($state['count'] >= $this->limit) {
            $retryAfter = max(0, $this->windowSeconds - ($now - $state['window_start']));
            $this->counters[$key] = $state;

            return RateLimitDecision::block('rate_limited', $retryAfter);
        }

        $state['count']++;
        $this->counters[$key] = $state;

        return RateLimitDecision::allow($this->limit - $state['count']);
    }

    public function resetAfterSuccess(RateLimitContext $context): bool
    {
        unset($this->counters[$context->key()]);
        return true;
    }
}
