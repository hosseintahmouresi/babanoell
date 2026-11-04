<?php
declare(strict_types=1);

namespace Babanoell\Auth\UseCases\Refresh;

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
use Babanoell\Auth\UseCases\Login\SessionIssueResult;
use Throwable;

final class ActiveRefreshUseCase implements RefreshUseCaseInterface
{
    public function __construct(
        private readonly SessionRepositoryInterface $sessions,
        private readonly UserRepositoryInterface $users,
        private readonly TokenHasherInterface $tokenHasher,
        private readonly SessionRotatorInterface $rotator,
        private readonly AuditWriterInterface $auditWriter,
        private readonly ClockInterface $clock = new SystemClock(),
        private readonly bool $refreshEnabled = false,
    ) {
    }

    public function handle(RefreshRequest $request): RefreshResult
    {
        if (!$this->refreshEnabled) {
            return RefreshResult::inactive();
        }

        $context = $request->securityContext();

        if (!$request->refreshCredentialSubmitted()) {
            $this->writeFailureAudit('missing_credential', $context);

            return RefreshResult::invalidCredentials();
        }

        try {
            $session = $this->sessions->findByRefreshTokenHash(
                $this->tokenHasher->hash($request->submittedRefreshCredential()),
            );
        } catch (Throwable) {
            $this->writeFailureAudit('unavailable', $context);

            return RefreshResult::unavailable();
        }

        if ($session === null) {
            $this->writeFailureAudit('invalid_credentials', $context);

            return RefreshResult::invalidCredentials();
        }

        if ($session->wasRotated()) {
            // Reuse of an already-rotated refresh credential: assume the
            // token family is compromised and revoke every session in it.
            try {
                $this->sessions->revokeByTokenFamily($session->tokenFamilyId, 'reuse_detected');
            } catch (Throwable) {
                return RefreshResult::unavailable();
            }

            $this->writeReuseAudit($session, $context);

            return RefreshResult::invalidCredentials();
        }

        if ($session->isRevoked()) {
            $this->writeFailureAudit('session_revoked', $context, $session);

            return RefreshResult::invalidCredentials();
        }

        if ($session->refreshExpiresAt <= $this->clock->now()) {
            $this->writeFailureAudit('refresh_expired', $context, $session);

            return RefreshResult::invalidCredentials();
        }

        $user = $this->users->findById($session->userId);

        if (!$this->userMayAuthenticate($user)) {
            $this->writeFailureAudit('user_not_allowed', $context, $session, $user);

            return RefreshResult::invalidCredentials();
        }

        try {
            $rotated = $this->rotator->rotate($session, $context);
        } catch (Throwable) {
            $rotated = SessionIssueResult::unavailable('session_rotation_failed');
        }

        if (!$rotated->issuedSuccessfully()) {
            $this->writeFailureAudit('unavailable', $context, $session);

            return RefreshResult::unavailable();
        }

        if (!$this->writeSuccessAudit($user, $rotated, $context)) {
            return RefreshResult::unavailable();
        }

        return RefreshResult::refreshed([
            'session' => array_filter([
                'session_credential' => $rotated->sessionCredential(),
                'refresh_credential' => $rotated->refreshCredential(),
                'access_expires_at' => $rotated->accessExpiresAt()?->format(DATE_ATOM),
                'refresh_expires_at' => $rotated->refreshExpiresAt()?->format(DATE_ATOM),
            ], static fn (mixed $value): bool => $value !== null),
        ]);
    }

    private function userMayAuthenticate(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        if ($user->status !== 'active' || $user->disabledAt !== null) {
            return false;
        }

        if ($user->lockedUntil !== null && $user->lockedUntil > $this->clock->now()) {
            return false;
        }

        return true;
    }

    private function writeFailureAudit(
        string $reason,
        LoginSecurityContext $context,
        ?SessionRecord $session = null,
        ?User $user = null,
    ): bool {
        return $this->writeAudit(new AuditEvent(
            AuditEventType::TokenRefreshRequested,
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

    private function writeReuseAudit(SessionRecord $session, LoginSecurityContext $context): bool
    {
        return $this->writeAudit(new AuditEvent(
            AuditEventType::TokenReuseDetected,
            'family_revoked',
            AuditContext::fromRawRequestData(
                targetUserId: $session->userId,
                sessionId: $session->id,
                ipAddress: $context->clientSource(),
                userAgent: $context->userAgent(),
            ),
            [
                'reason' => 'reuse_detected',
                'request_id' => $context->requestId(),
            ],
        ));
    }

    private function writeSuccessAudit(User $user, SessionIssueResult $session, LoginSecurityContext $context): bool
    {
        return $this->writeAudit(new AuditEvent(
            AuditEventType::TokenRefreshRequested,
            'succeeded',
            AuditContext::fromRawRequestData(
                actorUserId: $user->id,
                targetUserId: $user->id,
                sessionId: $session->sessionId(),
                ipAddress: $context->clientSource(),
                userAgent: $context->userAgent(),
            ),
            [
                'reason' => 'rotated',
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
