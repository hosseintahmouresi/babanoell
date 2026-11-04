<?php
declare(strict_types=1);

namespace Babanoell\Auth\Audit;

interface AuditWriterInterface
{
    public function write(AuditEvent $event): void;
}
