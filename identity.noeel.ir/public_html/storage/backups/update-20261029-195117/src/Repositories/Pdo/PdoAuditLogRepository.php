<?php
declare(strict_types=1);

namespace Babanoell\Auth\Repositories\Pdo;

use Babanoell\Auth\Repositories\AuditLogRepositoryInterface;
use Babanoell\Auth\Support\ClockInterface;
use Babanoell\Auth\Support\IdGeneratorInterface;
use Babanoell\Auth\Support\RandomIdGenerator;
use Babanoell\Auth\Support\SystemClock;
use JsonException;
use PDO;

final class PdoAuditLogRepository implements AuditLogRepositoryInterface
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly IdGeneratorInterface $idGenerator = new RandomIdGenerator(),
        private readonly ClockInterface $clock = new SystemClock()
    ) {
    }

    public function record(string $eventType, string $result, array $metadata = []): void
    {
        $statement = $this->pdo->prepare(<<<'SQL'
INSERT INTO auth_audit_logs (
    id,
    event_type,
    result,
    metadata_json,
    created_at
) VALUES (
    :id,
    :event_type,
    :result,
    :metadata_json,
    :created_at
)
SQL);

        $statement->execute([
            'id' => $this->idGenerator->generate(),
            'event_type' => $eventType,
            'result' => $result,
            'metadata_json' => $this->metadataJson($metadata),
            'created_at' => $this->clock->now()->format('Y-m-d H:i:s.u'),
        ]);
    }

    /**
     * @param array<string,mixed> $metadata
     */
    private function metadataJson(array $metadata): string
    {
        try {
            return json_encode($metadata, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        } catch (JsonException $exception) {
            throw new \InvalidArgumentException('Audit metadata must be JSON encodable.', 0, $exception);
        }
    }
}
