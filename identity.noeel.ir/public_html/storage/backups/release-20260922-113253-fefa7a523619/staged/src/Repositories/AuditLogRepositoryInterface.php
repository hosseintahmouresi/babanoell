<?php
declare(strict_types=1);

namespace Babanoell\Auth\Repositories;

interface AuditLogRepositoryInterface
{
    /**
     * @param array<string,mixed> $metadata
     */
    public function record(string $eventType, string $result, array $metadata = []): void;
}
