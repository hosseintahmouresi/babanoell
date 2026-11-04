<?php
declare(strict_types=1);

namespace Babanoell\Auth\App;

use Babanoell\Auth\Audit\AuditWriterInterface;
use Babanoell\Auth\Audit\PdoAuditWriter;
use Babanoell\Auth\Database\Connection;
use Babanoell\Auth\Http\Controllers\AuthController;
use Babanoell\Auth\Http\Controllers\MeController;
use Babanoell\Auth\RateLimit\FileWindowRateLimiter;
use Babanoell\Auth\RateLimit\RateLimiterInterface;
use Babanoell\Auth\Repositories\Pdo\PdoPermissionRepository;
use Babanoell\Auth\Repositories\Pdo\PdoRoleRepository;
use Babanoell\Auth\Repositories\Pdo\PdoSessionRepository;
use Babanoell\Auth\Repositories\Pdo\PdoUserRepository;
use Babanoell\Auth\Repositories\PermissionRepositoryInterface;
use Babanoell\Auth\Repositories\RoleRepositoryInterface;
use Babanoell\Auth\Repositories\SessionRepositoryInterface;
use Babanoell\Auth\Repositories\UserRepositoryInterface;
use Babanoell\Auth\Security\HmacTokenHasher;
use Babanoell\Auth\Security\PasswordHasherInterface;
use Babanoell\Auth\Security\PhpPasswordHasher;
use Babanoell\Auth\Security\TokenHasherInterface;
use Babanoell\Auth\UseCases\Login\ActiveLoginUseCase;
use Babanoell\Auth\UseCases\Login\DatabaseSessionIssuer;
use Babanoell\Auth\UseCases\Login\InactiveLoginUseCase;
use Babanoell\Auth\UseCases\Login\InactiveSessionIssuer;
use Babanoell\Auth\UseCases\Login\LoginUseCaseInterface;
use Babanoell\Auth\UseCases\Login\UnavailableLoginUseCase;
use Babanoell\Auth\UseCases\Logout\ActiveLogoutUseCase;
use Babanoell\Auth\UseCases\Logout\InactiveLogoutUseCase;
use Babanoell\Auth\UseCases\Logout\LogoutUseCaseInterface;
use Babanoell\Auth\UseCases\Me\ActiveMeUseCase;
use Babanoell\Auth\UseCases\Me\InactiveMeUseCase;
use Babanoell\Auth\UseCases\Me\MeUseCaseInterface;
use Babanoell\Auth\UseCases\Refresh\ActiveRefreshUseCase;
use Babanoell\Auth\UseCases\Refresh\DatabaseSessionRotator;
use Babanoell\Auth\UseCases\Refresh\InactiveRefreshUseCase;
use Babanoell\Auth\UseCases\Refresh\RefreshUseCaseInterface;
use PDO;
use Throwable;

final class Container
{
    public function __construct(
        private readonly Config $config,
        private readonly ?UserRepositoryInterface $users = null,
        private readonly ?SessionRepositoryInterface $sessions = null,
        private readonly ?AuditWriterInterface $auditWriter = null,
        private readonly ?RateLimiterInterface $rateLimiter = null,
        private readonly ?PasswordHasherInterface $passwordHasher = null,
        private readonly ?TokenHasherInterface $tokenHasher = null,
        private ?PDO $pdo = null,
        private readonly ?RoleRepositoryInterface $roles = null,
        private readonly ?PermissionRepositoryInterface $permissions = null,
    ) {
    }

    public function config(): Config
    {
        return $this->config;
    }

    public function authController(): AuthController
    {
        return new AuthController(
            loginUseCase: $this->loginUseCase(),
            logoutUseCase: $this->logoutUseCase(),
            refreshUseCase: $this->refreshUseCase(),
        );
    }

    public function meController(): MeController
    {
        return new MeController(
            meUseCase: $this->meUseCase(),
        );
    }

    private function loginUseCase(): LoginUseCaseInterface
    {
        if (!$this->config->httpLoginActivationAllowed()) {
            return new InactiveLoginUseCase();
        }

        try {
            return new ActiveLoginUseCase(
                users: $this->userRepository(),
                passwordHasher: $this->passwordHasher(),
                rateLimiter: $this->rateLimiter(),
                auditWriter: $this->auditWriter(),
                sessionIssuer: $this->sessionIssuer(),
                loginEnabled: $this->config->loginEnabled(),
            );
        } catch (Throwable) {
            return new UnavailableLoginUseCase();
        }
    }

    private function logoutUseCase(): LogoutUseCaseInterface
    {
        if (!$this->config->httpLoginActivationAllowed() || !$this->config->sessionIssuingEnabled()) {
            return new InactiveLogoutUseCase();
        }

        try {
            return new ActiveLogoutUseCase(
                sessions: $this->sessionRepository(),
                users: $this->userRepository(),
                tokenHasher: $this->tokenHasher(),
                auditWriter: $this->auditWriter(),
                logoutEnabled: true,
            );
        } catch (Throwable) {
            return new InactiveLogoutUseCase();
        }
    }

    private function refreshUseCase(): RefreshUseCaseInterface
    {
        if (
            !$this->config->httpLoginActivationAllowed()
            || !$this->config->refreshEnabled()
            || !$this->config->sessionIssuingEnabled()
        ) {
            return new InactiveRefreshUseCase();
        }

        try {
            return new ActiveRefreshUseCase(
                sessions: $this->sessionRepository(),
                users: $this->userRepository(),
                tokenHasher: $this->tokenHasher(),
                rotator: new DatabaseSessionRotator(
                    sessions: $this->sessionRepository(),
                    tokenHasher: $this->tokenHasher(),
                    refreshEnabled: true,
                    tokenPepperReady: $this->config->tokenPepperReady(),
                    sessionTtlSeconds: $this->config->sessionTtlSeconds(),
                    refreshTtlSeconds: $this->config->refreshTtlSeconds(),
                ),
                auditWriter: $this->auditWriter(),
                refreshEnabled: true,
            );
        } catch (Throwable) {
            return new InactiveRefreshUseCase();
        }
    }

    private function meUseCase(): MeUseCaseInterface
    {
        if (!$this->config->httpLoginActivationAllowed() || !$this->config->sessionIssuingEnabled()) {
            return new InactiveMeUseCase();
        }

        try {
            return new ActiveMeUseCase(
                sessions: $this->sessionRepository(),
                users: $this->userRepository(),
                tokenHasher: $this->tokenHasher(),
                roles: $this->roleRepository(),
                permissions: $this->permissionRepository(),
                meEnabled: true,
            );
        } catch (Throwable) {
            return new InactiveMeUseCase();
        }
    }

    private function sessionIssuer(): InactiveSessionIssuer|DatabaseSessionIssuer
    {
        if (!$this->config->sessionIssuingEnabled()) {
            return new InactiveSessionIssuer();
        }

        return new DatabaseSessionIssuer(
            sessions: $this->sessionRepository(),
            tokenHasher: $this->tokenHasher(),
            sessionIssuingEnabled: true,
            refreshEnabled: $this->config->refreshEnabled(),
            tokenPepperReady: $this->config->tokenPepperReady(),
            sessionTtlSeconds: $this->config->sessionTtlSeconds(),
            refreshTtlSeconds: $this->config->refreshTtlSeconds(),
        );
    }

    private function userRepository(): UserRepositoryInterface
    {
        return $this->users ?? new PdoUserRepository($this->pdo());
    }

    private function sessionRepository(): SessionRepositoryInterface
    {
        return $this->sessions ?? new PdoSessionRepository($this->pdo());
    }

    private function roleRepository(): RoleRepositoryInterface
    {
        return $this->roles ?? new PdoRoleRepository($this->pdo());
    }

    private function permissionRepository(): PermissionRepositoryInterface
    {
        return $this->permissions ?? new PdoPermissionRepository($this->pdo());
    }

    private function auditWriter(): AuditWriterInterface
    {
        return $this->auditWriter ?? new PdoAuditWriter($this->pdo());
    }

    private function rateLimiter(): RateLimiterInterface
    {
        return $this->rateLimiter ?? new FileWindowRateLimiter(
            dirname(__DIR__, 2) . '/storage/.login-rate-limit.json',
            $this->config->loginRateLimitMaxAttempts(),
            $this->config->loginRateLimitWindowSeconds(),
        );
    }

    private function passwordHasher(): PasswordHasherInterface
    {
        return $this->passwordHasher ?? new PhpPasswordHasher(PASSWORD_ARGON2ID, [
            'memory_cost' => $this->config->passwordHashMemoryCost(),
            'time_cost' => $this->config->passwordHashTimeCost(),
            'threads' => $this->config->passwordHashThreads(),
        ]);
    }

    private function tokenHasher(): TokenHasherInterface
    {
        if ($this->tokenHasher !== null) {
            return $this->tokenHasher;
        }

        $pepper = trim($this->config->tokenPepper());

        return new HmacTokenHasher($pepper !== '' ? $pepper : '__missing_token_pepper_not_used__');
    }

    private function pdo(): PDO
    {
        if ($this->pdo !== null) {
            return $this->pdo;
        }

        $config = require dirname(__DIR__, 2) . '/config/database.php';
        $this->pdo = Connection::fromConfig($config);

        return $this->pdo;
    }
}
