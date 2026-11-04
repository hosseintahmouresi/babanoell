<?php
declare(strict_types=1);

namespace Babanoell\Auth\UseCases\Logout;

use Babanoell\Auth\Audit\AuditContext;
use Babanoell\Auth\Audit\AuditEvent;
use Babanoell\Auth\Audit\AuditEventType;
use Babanoell\Auth\Audit\AuditWriterInterface;
use Babanoell\Auth\Models\SessionRecord;
use Babanoell\Auth\Models\User;
use Babanoell\Auth\Repositories\SessionRepositoryInterface;
use Babanoell\Auth\Repositories\UserRepositoryInterface;
use Babanoell\Auth\Security\TokenHasherInterface;
use Babanoell\Auth\Support\ClockInterface;
use Babanoell\Auth\Support\SystemClock;
use Babanoell\Auth\UseCases\Login\LoginSecurityContext;
use Throwable;

final class ActiveLogoutUseCase implements LogoutUseCaseInterface
{
    public function __construct(
        private readonly SessionRepositoryInterface $sessions,
        private readonly UserRepositoryInterface $users,
        private readonly TokenHasherInterface $tokenHasher,
        private readonly AuditWriterInterface $auditWriter,
        private readonly ClockInterface $clock = new SystemClock(),
        private readonly bool $logoutEnabled = false,
    ) {
    }

    public function handle(LogoutRequest $request): LogoutResult
    {
        if (!$this->logoutEnabled) {
            return LogoutResult::inactive();
        }

        $context = $request->securityContext();

        if (!$request->credentialSubmitted()) {
            $this->writeFailureAudit('missing_credential', $context);

            return LogoutResult::authenticationRequired();
        }

        try {
            $session = $this->sessions->findByAccessTokenHash(
                $this->tokenHasher->hash($request->submittedCredential()),
            );
        } catch (Throwable) {
            $this->writeFailureAudit('unavailable', $context);

            return LogoutResult::unavailable();
        }

        if ($session === null || $session->isRevoked()) {
            $this->writeFailureAudit('invalid_credentials', $context, $session);

            return LogoutResult::invalidCredentials();
        }

        $user = $this->users->findById($session->userId);

        if ($user === null) {
            $this->writeFailureAudit('invalid_credentials', $context, $session);

            return LogoutResult::invalidCredentials();
        }

        try {
            if ($request->allSessions()) {
                $revokedCount = $this->sessions->revokeAllForUser($user->id, 'logout_all');
            } else {
                $this->sessions->revokeById($session->id, 'logout');
                $revokedCount = 1;
            }
        } catch (Throwable) {
            $this->writeFailureAudit('unavailable', $context, $session, $user);

            return LogoutResult::unavailable();
        }

        if (!$this->writeSuccessAudit($user, $session, $request->allSessions(), $context)) {
            return LogoutResult::unavailable();
        }

        return LogoutResult::loggedOut([
            'status' => 'logged_out',
            'revoked_sessions' => $revokedCount,
        ]);
    }

    private function writeFailureAudit(
        string $reason,
        LoginSecurityContext $context,
        ?SessionRecord $session = null,
        ?User $user = null,
    ): bool {
        return $this->writeAudit(new AuditEvent(
            AuditEventType::LogoutRequested,
            'failed',
            AuditContext::fromRawRequestData(
                actorUserId: $user?->id,
                targetUserId: $user?->id ?? $session?->userId,
                sessionId: $session?->id,
                ipAddress: $context->clientSource(),
                userAgent: $context->userAgent(),
            ),
            [
                'reason' => $reason,
                'request_id' => $context->requestId(),
            ],
        ));
    }

    private function writeSuccessAudit(
        User $user,
        SessionRecord $session,
        bool $allSessions,
        LoginSecurityContext $context,
    ): bool {
        return $this->writeAudit(new AuditEvent(
            AuditEventType::LogoutRequested,
            'succeeded',
            AuditContext::fromRawRequestData(
                actorUserId: $user->id,
                targetUserId: $user->id,
                sessionId: $session->id,
                ipAddress: $context->clientSource(),
                userAgent: $context->userAgent(),
            ),
            [
                'reason' => $allSessions ? 'logout_all' : 'logout',
                'request_id' => $context->requestId(),
            ],
        ));
    }

    private function writeAudit(AuditEvent $event): bool
    {
        try {
            $this->auditWriter->write($event);

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
