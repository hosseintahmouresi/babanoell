<?php
declare(strict_types=1);

namespace Babanoell\Auth\Audit;

use Babanoell\Auth\Exceptions\AuditException;
use Babanoell\Auth\Support\ClockInterface;
use Babanoell\Auth\Support\IdGeneratorInterface;
use Babanoell\Auth\Support\RandomIdGenerator;
use Babanoell\Auth\Support\SystemClock;
use JsonException;
use PDO;

final class PdoAuditWriter implements AuditWriterInterface
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly IdGeneratorInterface $idGenerator = new RandomIdGenerator(),
        private readonly ClockInterface $clock = new SystemClock(),
    ) {
    }

    public function write(AuditEvent $event): void
    {
        $statement = $this->pdo->prepare(<<<'SQL'
INSERT INTO auth_audit_logs (
    id,
    event_type,
    actor_user_id,
    target_user_id,
    session_id,
    ip_address_hash,
    user_agent_hash,
    result,
    metadata_json,
    created_at
) VALUES (
    :id,
    :event_type,
    :actor_user_id,
    :target_user_id,
    :session_id,
    :ip_address_hash,
    :user_agent_hash,
    :result,
    :metadata_json,
    :created_at
)
SQL);

        $context = $event->context();

        $statement->execute([
            'id' => $this->idGenerator->generate(),
            'event_type' => $event->type()->value,
            'actor_user_id' => $context->actorUserId(),
            'target_user_id' => $context->targetUserId(),
            'session_id' => $context->sessionId(),
            'ip_address_hash' => $context->ipAddressHash(),
            'user_agent_hash' => $context->userAgentHash(),
            'result' => $event->result(),
            'metadata_json' => $this->metadataJson($event->metadata()),
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
            throw new AuditException('Audit metadata must be JSON encodable.', 0, $exception);
        }
    }
}
