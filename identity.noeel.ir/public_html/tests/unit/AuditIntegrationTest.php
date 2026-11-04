<?php
declare(strict_types=1);

use Babanoell\Auth\Audit\AuditContext;
use Babanoell\Auth\Audit\AuditEvent;
use Babanoell\Auth\Audit\AuditEventType;
use Babanoell\Auth\Audit\PdoAuditWriter;
use Babanoell\Auth\Support\ClockInterface;
use Babanoell\Auth\Support\IdGeneratorInterface;
use Babanoell\Auth\Tests\Assertions;
use Babanoell\Auth\Tests\TestCase;

return new class extends TestCase {
    public function testPdoAuditWriterBuildsSyntheticLocalAuditInsert(): void
    {
        $pdo = new class extends PDO {
            public string $query = '';

            /**
             * @var array<string,mixed>
             */
            public array $params = [];

            public function __construct()
            {
            }

            public function prepare(string $query, array $options = []): PDOStatement|false
            {
                $this->query = $query;

                return new class($this) extends PDOStatement {
                    public function __construct(private readonly object $sink)
                    {
                    }

                    public function execute(?array $params = null): bool
                    {
                        $this->sink->params = $params ?? [];

                        return true;
                    }
                };
            }
        };

        $idGenerator = new class implements IdGeneratorInterface {
            public function generate(): string
            {
                return 'aud_local_0000000000000001';
            }
        };

        $clock = new class implements ClockInterface {
            public function now(): DateTimeImmutable
            {
                return new DateTimeImmutable('2026-06-13 00:00:00.123456', new DateTimeZone('UTC'));
            }
        };

        $event = new AuditEvent(
            AuditEventType::AuthorizationChecked,
            'blocked',
            AuditContext::fromRawRequestData(
                actorUserId: 'usr_local',
                targetUserId: 'usr_target',
                sessionId: 'ses_local',
                ipAddress: '192.0.2.25',
                userAgent: 'Local Test Browser',
            ),
            ['permission' => 'auth.roles.read'],
        );

        (new PdoAuditWriter($pdo, $idGenerator, $clock))->write($event);

        Assertions::contains('INSERT INTO auth_audit_logs', $pdo->query);
        Assertions::same('aud_local_0000000000000001', $pdo->params['id']);
        Assertions::same('auth.authorization.checked', $pdo->params['event_type']);
        Assertions::same('usr_local', $pdo->params['actor_user_id']);
        Assertions::same('usr_target', $pdo->params['target_user_id']);
        Assertions::same('ses_local', $pdo->params['session_id']);
        Assertions::same(hash('sha256', '192.0.2.25'), $pdo->params['ip_address_hash']);
        Assertions::same(hash('sha256', 'Local Test Browser'), $pdo->params['user_agent_hash']);
        Assertions::same('blocked', $pdo->params['result']);
        Assertions::same('auth.roles.read', json_decode($pdo->params['metadata_json'], true)['permission']);
        Assertions::false(str_contains(json_encode($pdo->params, JSON_THROW_ON_ERROR), '192.0.2.25'));
        Assertions::false(str_contains(json_encode($pdo->params, JSON_THROW_ON_ERROR), 'Local Test Browser'));
    }
};
