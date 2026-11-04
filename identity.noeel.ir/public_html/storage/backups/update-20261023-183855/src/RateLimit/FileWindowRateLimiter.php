<?php
declare(strict_types=1);

namespace Babanoell\Auth\RateLimit;

use Babanoell\Auth\Support\ClockInterface;
use Babanoell\Auth\Support\SystemClock;
use Throwable;

/** Persistent limiter for a single host with private local storage. */
final class FileWindowRateLimiter implements RateLimiterInterface
{
    public function __construct(
        private readonly string $path,
        private readonly int $limit,
        private readonly int $windowSeconds,
        private readonly ClockInterface $clock = new SystemClock(),
    ) {}

    public function check(RateLimitContext $context): RateLimitDecision
    {
        if ($this->limit < 1 || $this->windowSeconds < 1 || is_link($this->path)) {
            return RateLimitDecision::block('rate_limit_unavailable');
        }
        $handle = @fopen($this->path, 'c+b');
        if ($handle === false) return RateLimitDecision::block('rate_limit_unavailable');
        try {
            if (!flock($handle, LOCK_EX)) return RateLimitDecision::block('rate_limit_unavailable');
            @chmod($this->path, 0600);
            $raw = stream_get_contents($handle, 2097153);
            if ($raw === false || strlen($raw) > 2097152) throw new \RuntimeException('Invalid limiter state');
            $states = $raw === '' ? [] : json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($states)) throw new \RuntimeException('Invalid limiter state');
            $now = $this->clock->now()->getTimestamp();
            foreach ($states as $key => $state) {
                if (!is_array($state) || !is_int($state['until'] ?? null) || !is_int($state['count'] ?? null) || $state['count'] < 0) {
                    throw new \RuntimeException('Invalid limiter state');
                }
                if ($state['until'] <= $now) unset($states[$key]);
            }
            // Limit by account: rotating or spoofing the source IP cannot reset attempts.
            $key = hash('sha256', $context->action() . ':' . $context->subjectHash());
            $state = $states[$key] ?? ['until' => $now + $this->windowSeconds, 'count' => 0];
            if ($state['count'] >= $this->limit) return RateLimitDecision::block('rate_limited', $state['until'] - $now);
            if (!isset($states[$key]) && count($states) >= 10000) return RateLimitDecision::block('rate_limit_unavailable');
            $state['count']++;
            $states[$key] = $state;
            $data = json_encode($states, JSON_THROW_ON_ERROR);
            // All read/modify/write operations share the same inode and exclusive lock.
            if (!rewind($handle) || fwrite($handle, $data) !== strlen($data) || !ftruncate($handle, strlen($data)) || !fflush($handle)) {
                throw new \RuntimeException('Cannot persist limiter state');
            }
            return RateLimitDecision::allow($this->limit - $state['count']);
        } catch (Throwable) {
            return RateLimitDecision::block('rate_limit_unavailable');
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}
