<?php
declare(strict_types=1);

namespace Babanoell\Auth\RateLimit;

use InvalidArgumentException;

final class RateLimitContext
{
    public function __construct(
        private readonly string $action,
        private readonly string $subjectHash,
        private readonly ?string $sourceHash = null,
        private readonly ?string $requestId = null,
    ) {
        if (trim($action) === '' || trim($subjectHash) === '') {
            throw new InvalidArgumentException('Rate limit action and subject hash are required.');
        }
    }

    public static function fromRawValues(
        string $action,
        string $subject,
        ?string $source = null,
        ?string $requestId = null,
    ): self {
        return new self(
            action: $action,
            subjectHash: hash('sha256', $subject),
            sourceHash: $source === null || trim($source) === '' ? null : hash('sha256', $source),
            requestId: $requestId,
        );
    }

    public function action(): string
    {
        return $this->action;
    }

    public function subjectHash(): string
    {
        return $this->subjectHash;
    }

    public function sourceHash(): ?string
    {
        return $this->sourceHash;
    }

    public function requestId(): ?string
    {
        return $this->requestId;
    }

    public function key(): string
    {
        return implode(':', array_filter([
            $this->action,
            $this->subjectHash,
            $this->sourceHash,
        ], static fn (?string $part): bool => $part !== null && $part !== ''));
    }
}
