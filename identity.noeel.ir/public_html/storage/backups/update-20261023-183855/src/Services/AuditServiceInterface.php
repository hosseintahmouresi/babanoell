<?php
declare(strict_types=1);

namespace Babanoell\Auth\Services;

interface AuditServiceInterface
{
    /**
     * @param array<string,mixed> $metadata
     */
    public function recordSecurityEvent(string $eventType, string $result, array $metadata = []): void;
}
