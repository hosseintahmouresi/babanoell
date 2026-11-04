<?php
declare(strict_types=1);

namespace Babanoell\Auth\UseCases\Login;

use Babanoell\Auth\Audit\AuditContext;
use Babanoell\Auth\Audit\AuditEvent;
use Babanoell\Auth\Audit\AuditEventType;
use Babanoell\Auth\Audit\AuditWriterInterface;
use Babanoell\Auth\Models\User;
use Babanoell\Auth\RateLimit\RateLimitContext;
use Babanoell\Auth\RateLimit\RateLimiterInterface;
use Babanoell\Auth\RateLimit\ResettableRateLimiterInterface;
use Babanoell\Auth\Repositories\UserRepositoryInterface;
use Babanoell\Auth\Security\InputNormalizer;
use Babanoell\Auth\Security\PasswordHasherInterface;
use Babanoell\Auth\Support\ClockInterface;
use Babanoell\Auth\Support\SystemClock;
use Throwable;

final class ActiveLoginUseCase implements LoginUseCaseInterface
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly PasswordHasherInterface $passwordHasher,
        private readonly RateLimiterInterface $rateLimiter,
        private readonly AuditWriterInterface $auditWriter,
        private readonly SessionIssuerInterface $sessionIssuer,
        private readonly ClockInterface $clock = new SystemClock(),
        private readonly InputNormalizer $inputNormalizer = new InputNormalizer(),
        private readonly bool $loginEnabled = false,
    ) {
    }

    public function handle(LoginRequest $request): LoginResult
    {
        if (!$this->loginEnabled) {
            return LoginResult::inactive();
        }

        $context = $request->securityContext();
        $identifier = $this->inputNormalizer->loginIdentifier($request->identifier());

        if ($identifier === '' || !$request->passwordSubmitted()) {
            $this->writeFailureAudit(LoginFailureReason::MissingCredential, $context);

            return LoginResult::invalidCredentials();
        }

        $rateLimitDecision = $this->rateLimiter->check(RateLimitContext::fromRawValues(
            action: 'auth.login',
            subject: $identifier,
            source: $context->clientSource(),
            requestId: $context->requestId(),
        ));

        if (!$rateLimitDecision->allowed()) {
            $this->writeFailureAudit(
                LoginFailureReason::RateLimited,
                $context,
                null,
                [
                    'rate_limit' => [
                        'decision' => 'blocked',
                        'code' => $rateLimitDecision->code(),
                        'retry_after_seconds' => $rateLimitDecision->retryAfterSeconds(),
                    ],
                ],
            );

            // Storage/locking failures are service errors, NOT excessive attempts.
            return $rateLimitDecision->code() === 'rate_limited'
                ? LoginResult::rateLimited()
                : LoginResult::unavailable();
        }

        $user = $this->users->findByLoginIdentifier($identifier);

        if (!$this->credentialsAreValid($user, $request)) {
            $this->writeFailureAudit(LoginFailureReason::InvalidCredentials, $context, $user);

            return LoginResult::invalidCredentials();
        }

        $session = $this->issueSession($user, $context);

        if (!$session->issuedSuccessfully()) {
            $this->writeFailureAudit(LoginFailureReason::Unavailable, $context, $user);

            return LoginResult::unavailable();
        }

        if (!$this->writeSuccessAudit($context, $user, $session)) {
            return LoginResult::unavailable();
        }

        // Earlier versions counted *every* login attempt, even a valid login.
        // Keep failed-attempt reservations to limit concurrent brute forcing,
        // but clear the successful account's counter after all success checks.
        if ($this->rateLimiter instanceof ResettableRateLimiterInterface) {
            $this->rateLimiter->resetAfterSuccess(RateLimitContext::fromRawValues(
                action: 'auth.login',
                subject: $identifier,
                source: $context->clientSource(),
                requestId: $context->requestId(),
            ));
        }

        return LoginResult::authenticated([
            'session' => array_filter([
                'id' => $session->sessionId(),
                'session_credential' => $session->sessionCredential(),
                'refresh_credential' => $session->refreshCredential(),
                'access_expires_at' => $session->accessExpiresAt()?->format(DATE_ATOM),
                'refresh_expires_at' => $session->refreshExpiresAt()?->format(DATE_ATOM),
            ], static fn (mixed $value): bool => $value !== null),
        ]);
    }

    private function credentialsAreValid(?User $user, LoginRequest $request): bool
    {
        if ($user === null || $user->passwordHash === null || $user->passwordHash === '') {
            return false;
        }

        if ($user->status !== 'active' || $user->disabledAt !== null) {
            return false;
        }

        if ($user->lockedUntil !== null && $user->lockedUntil > $this->clock->now()) {
            return false;
        }

        return $request->submittedPasswordMatches($this->passwordHasher, $user->passwordHash);
    }

    private function issueSession(User $user, LoginSecurityContext $context): SessionIssueResult
    {
        try {
            return $this->sessionIssuer->issue($user, $context);
        } catch (Throwable) {
            return SessionIssueResult::unavailable();
        }
    }

    private function writeFailureAudit(
        LoginFailureReason $reason,
        LoginSecurityContext $context,
        ?User $user = null,
        array $extraMetadata = [],
    ): bool {
        return $this->writeAudit(new AuditEvent(
            AuditEventType::LoginFailed,
            'failed',
            AuditContext::fromRawRequestData(
                targetUserId: $user?->id,
                ipAddress: $context->clientSource(),
                userAgent: $context->userAgent(),
            ),
            array_merge([
                'reason' => $reason->value,
                'request_id' => $context->requestId(),
            ], $extraMetadata),
        ));
    }

    private function writeSuccessAudit(LoginSecurityContext $context, User $user, SessionIssueResult $session): bool
    {
        return $this->writeAudit(new AuditEvent(
            AuditEventType::LoginSucceeded,
            'succeeded',
            AuditContext::fromRawRequestData(
                actorUserId: $user->id,
                targetUserId: $user->id,
                sessionId: $session->sessionId(),
                ipAddress: $context->clientSource(),
                userAgent: $context->userAgent(),
            ),
            [
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
