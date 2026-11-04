<?php
declare(strict_types=1);

use Babanoell\Auth\RateLimit\FixedWindowRateLimiter;
use Babanoell\Auth\RateLimit\InactiveRateLimiter;
use Babanoell\Auth\RateLimit\RateLimitContext;
use Babanoell\Auth\Support\ClockInterface;
use Babanoell\Auth\Tests\Assertions;
use Babanoell\Auth\Tests\TestCase;

return new class extends TestCase {
    public function testRateLimitContextHashesRawValues(): void
    {
        $context = RateLimitContext::fromRawValues(
            action: 'auth.login',
            subject: 'local-user@example.test',
            source: '192.0.2.25',
            requestId: 'req_local',
        );

        Assertions::same('auth.login', $context->action());
        Assertions::same(hash('sha256', 'local-user@example.test'), $context->subjectHash());
        Assertions::same(hash('sha256', '192.0.2.25'), $context->sourceHash());
        Assertions::same('req_local', $context->requestId());
        Assertions::false(str_contains($context->key(), 'local-user@example.test'));
        Assertions::false(str_contains($context->key(), '192.0.2.25'));
    }

    public function testInactiveRateLimiterFailsClosed(): void
    {
        $decision = (new InactiveRateLimiter())->check(RateLimitContext::fromRawValues(
            action: 'auth.login',
            subject: 'local-user@example.test',
        ));

        Assertions::false($decision->allowed());
        Assertions::same('rate_limit_inactive', $decision->code());
    }

    public function testFixedWindowRateLimiterIsDeterministicAndLocal(): void
    {
        $clock = new class implements ClockInterface {
            public int $timestamp = 1_000;

            public function now(): DateTimeImmutable
            {
                return new DateTimeImmutable('@' . $this->timestamp);
            }
        };

        $limiter = new FixedWindowRateLimiter(limit: 2, windowSeconds: 60, clock: $clock);
        $context = RateLimitContext::fromRawValues(
            action: 'auth.login',
            subject: 'local-user@example.test',
            source: '192.0.2.25',
        );

        $first = $limiter->check($context);
        $second = $limiter->check($context);
        $third = $limiter->check($context);

        Assertions::true($first->allowed());
        Assertions::same(1, $first->remainingAttempts());
        Assertions::true($second->allowed());
        Assertions::same(0, $second->remainingAttempts());
        Assertions::false($third->allowed());
        Assertions::same('rate_limited', $third->code());
        Assertions::same(60, $third->retryAfterSeconds());

        $clock->timestamp = 1_060;
        Assertions::true($limiter->check($context)->allowed());
    }

    public function testFixedWindowRateLimiterFailsSafelyWhenMisconfigured(): void
    {
        $decision = (new FixedWindowRateLimiter(limit: 0, windowSeconds: 60))->check(
            RateLimitContext::fromRawValues('auth.login', 'local-user@example.test'),
        );

        Assertions::false($decision->allowed());
        Assertions::same('rate_limit_unavailable', $decision->code());
    }
};
