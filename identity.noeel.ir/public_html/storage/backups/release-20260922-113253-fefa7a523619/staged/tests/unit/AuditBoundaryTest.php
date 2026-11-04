<?php
declare(strict_types=1);

use Babanoell\Auth\Audit\AuditContext;
use Babanoell\Auth\Audit\AuditEvent;
use Babanoell\Auth\Audit\AuditEventType;
use Babanoell\Auth\Audit\InactiveAuditWriter;
use Babanoell\Auth\Audit\PdoAuditWriter;
use Babanoell\Auth\Exceptions\AuditException;
use Babanoell\Auth\Tests\Assertions;
use Babanoell\Auth\Tests\TestCase;

return new class extends TestCase {
    public function testAuditContextHashesRequestData(): void
    {
        $context = AuditContext::fromRawRequestData(
            actorUserId: 'usr_local',
            targetUserId: 'usr_target',
            sessionId: 'ses_local',
            ipAddress: '192.0.2.10',
            userAgent: 'Local Test Browser',
        );

        Assertions::same('usr_local', $context->actorUserId());
        Assertions::same('usr_target', $context->targetUserId());
        Assertions::same('ses_local', $context->sessionId());
        Assertions::same(hash('sha256', '192.0.2.10'), $context->ipAddressHash());
        Assertions::same(hash('sha256', 'Local Test Browser'), $context->userAgentHash());
    }

    public function testAuditEventMatchesAuthAuditLogShape(): void
    {
        $context = AuditContext::fromRawRequestData(actorUserId: 'usr_local');
        $event = new AuditEvent(
            AuditEventType::PermissionDenied,
            'denied',
            $context,
            ['permission' => 'auth.roles.read'],
        );

        Assertions::same(AuditEventType::PermissionDenied, $event->type());
        Assertions::same('denied', $event->result());
        Assertions::same('usr_local', $event->context()->actorUserId());
        Assertions::same('auth.roles.read', $event->metadata()['permission']);
    }

    public function testAuditEventRejectsSensitiveMetadataFields(): void
    {
        Assertions::throws(AuditException::class, static function (): void {
            new AuditEvent(AuditEventType::LoginAttempt, 'blocked', metadata: [
                'password' => 'local-placeholder',
            ]);
        });

        Assertions::throws(AuditException::class, static function (): void {
            new AuditEvent(AuditEventType::LoginAttempt, 'blocked', metadata: [
                'nested' => [
                    'refresh_token' => 'local-placeholder',
                ],
            ]);
        });
    }

    public function testInactiveAuditWriterFailsClosed(): void
    {
        $writer = new InactiveAuditWriter();
        $event = new AuditEvent(AuditEventType::AuthorizationChecked, 'blocked');

        Assertions::throws(AuditException::class, static function () use ($writer, $event): void {
            $writer->write($event);
        });
    }

    public function testPdoAuditWriterUsesPreparedStatementsAndAuditTable(): void
    {
        $source = file_get_contents((new ReflectionClass(PdoAuditWriter::class))->getFileName());

        Assertions::contains('->prepare(', $source);
        Assertions::contains('auth_audit_logs', $source);
        Assertions::contains('actor_user_id', $source);
        Assertions::contains('metadata_json', $source);
    }
};
