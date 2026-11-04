<?php
declare(strict_types=1);

namespace Babanoell\Auth\Audit;

use Babanoell\Auth\Exceptions\AuditException;

final class InactiveAuditWriter implements AuditWriterInterface
{
    public function write(AuditEvent $event): void
    {
        throw new AuditException('Audit writer is not active yet.');
    }
}
