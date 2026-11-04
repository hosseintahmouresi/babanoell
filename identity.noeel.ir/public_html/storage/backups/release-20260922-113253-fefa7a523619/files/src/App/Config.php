<?php
declare(strict_types=1);

namespace Babanoell\Auth\App;

final class Config
{
    /**
     * @param array<string,mixed> $security
     */
    public function __construct(
        private readonly string $serviceName,
        private readonly string $environment,
        private readonly array $security = [],
    ) {
    }

    public static function fromEnvironment(): self
    {
        $serviceRoot = dirname(__DIR__, 2);
        $envFile = $serviceRoot . '/config/env.php';
        $securityConfigFile = $serviceRoot . '/config/security.php';

        if (is_file($envFile)) {
            require_once $envFile;
            \babanoell_auth_load_env();
        }

        $readEnv = static function (string $key, string $default): string {
            if (function_exists('babanoell_auth_env')) {
                return (string) (\babanoell_auth_env($key, $default) ?? $default);
            }

            if (function_exists('getenv')) {
                $value = getenv($key);

                if ($value !== false && trim((string) $value) !== '') {
                    return (string) $value;
                }
            }

            return $default;
        };

        return new self(
            $readEnv('SERVICE_NAME', 'auth-service'),
            $readEnv('APP_ENV', 'local'),
            is_file($securityConfigFile) ? require $securityConfigFile : [],
        );
    }

    public function serviceName(): string
    {
        return $this->serviceName;
    }

    public function environment(): string
    {
        return $this->environment;
    }

    public function authEnvironment(): string
    {
        $value = trim((string) ($this->security['auth_environment'] ?? $this->environment));

        return $value !== '' ? $value : $this->environment;
    }

    public function allowHttpLoginInLocal(): bool
    {
        return $this->boolSecurity('allow_http_login_in_local', false);
    }

    public function allowHttpLoginInStaging(): bool
    {
        return $this->boolSecurity('allow_http_login_in_staging', false);
    }

    public function loginEnabled(): bool
    {
        return $this->boolSecurity('login_enabled', false);
    }

    public function sessionIssuingEnabled(): bool
    {
        return $this->boolSecurity('session_issuing_enabled', false);
    }

    public function refreshEnabled(): bool
    {
        return $this->boolSecurity('refresh_enabled', false);
    }

    public function tokenPepperRequired(): bool
    {
        return $this->boolSecurity('require_token_pepper', true);
    }

    public function tokenPepper(): string
    {
        return (string) ($this->security['token_pepper'] ?? '');
    }

    public function tokenPepperReady(): bool
    {
        return !$this->tokenPepperRequired() || trim($this->tokenPepper()) !== '';
    }

    /**
     * Controlled activation gate for HTTP authentication routes.
     *
     * Each environment has an explicit switch. Production additionally requires
     * session issuance, a non-placeholder pepper and bounded security settings.
     */
    public function httpLoginActivationAllowed(): bool
    {
        if (!$this->loginEnabled()) {
            return false;
        }

        $appEnvironment = strtolower($this->environment());
        $authEnvironment = strtolower($this->authEnvironment());

        if ($appEnvironment !== $authEnvironment) {
            return false;
        }

        if (in_array($appEnvironment, ['local', 'test'], true)) {
            return $this->allowHttpLoginInLocal();
        }

        if ($appEnvironment === 'staging') {
            return $this->allowHttpLoginInStaging();
        }

        if ($appEnvironment === 'production') {
            $pepper = trim($this->tokenPepper());
            return $this->boolSecurity('allow_login_in_production', false)
                && $this->sessionIssuingEnabled()
                && $this->tokenPepperRequired()
                && strlen($pepper) >= 32
                && preg_match('/placeholder|change.?me|example|missing/i', $pepper) !== 1
                && $this->loginRateLimitMaxAttempts() >= 1
                && $this->loginRateLimitMaxAttempts() <= 20
                && $this->loginRateLimitWindowSeconds() >= 60
                && $this->loginRateLimitWindowSeconds() <= 86400
                && $this->sessionTtlSeconds() >= 60
                && $this->sessionTtlSeconds() <= 86400;
        }

        return false;
    }

    public function sessionTtlSeconds(): int
    {
        return $this->intSecurity('session_ttl_seconds', 900);
    }

    public function refreshTtlSeconds(): int
    {
        return $this->intSecurity('refresh_ttl_seconds', 1209600);
    }

    public function loginRateLimitWindowSeconds(): int
    {
        return $this->intSecurity('login_rate_limit_window_seconds', 900);
    }

    public function loginRateLimitMaxAttempts(): int
    {
        return $this->intSecurity('login_rate_limit_max_attempts', 5);
    }

    public function auditRetentionDays(): int
    {
        return max(1, $this->intSecurity('audit_retention_days', 365));
    }

    public function passwordHashMemoryCost(): int
    {
        return $this->intSecurity('password_hash_memory_cost', 65536);
    }

    public function passwordHashTimeCost(): int
    {
        return $this->intSecurity('password_hash_time_cost', 4);
    }

    public function passwordHashThreads(): int
    {
        return $this->intSecurity('password_hash_threads', 2);
    }

    private function boolSecurity(string $key, bool $default): bool
    {
        $value = $this->security[$key] ?? $default;

        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
        }

        return $default;
    }

    private function intSecurity(string $key, int $default): int
    {
        $value = $this->security[$key] ?? $default;

        if (is_int($value)) {
            return max(0, $value);
        }

        if (is_string($value) && filter_var($value, FILTER_VALIDATE_INT) !== false) {
            return max(0, (int) $value);
        }

        return $default;
    }
}
