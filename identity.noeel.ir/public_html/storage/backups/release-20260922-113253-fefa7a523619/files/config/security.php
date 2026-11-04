<?php
declare(strict_types=1);

require_once __DIR__ . '/env.php';
babanoell_auth_load_env();

$env = static fn (string $key, ?string $default = null): ?string => babanoell_auth_env($key, $default);

$intEnv = static function (string $key, int $default) use ($env): int {
    $value = $env($key);

    if ($value === null || filter_var($value, FILTER_VALIDATE_INT) === false) {
        return $default;
    }

    return max(0, (int) $value);
};

$boolEnv = static function (string $key, bool $default) use ($env): bool {
    $value = $env($key);

    if ($value === null) {
        return $default;
    }

    return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
};

return [
    'auth_environment' => $env('AUTH_ENVIRONMENT', $env('APP_ENV', 'local') ?? 'local'),
    'allow_http_login_in_local' => $boolEnv('AUTH_ALLOW_HTTP_LOGIN_IN_LOCAL', false),
    'allow_http_login_in_staging' => $boolEnv('AUTH_ALLOW_HTTP_LOGIN_IN_STAGING', false),
    'allow_login_in_production' => $boolEnv('AUTH_ALLOW_LOGIN_IN_PRODUCTION', false),
    'login_enabled' => $boolEnv('AUTH_LOGIN_ENABLED', false),
    'session_issuing_enabled' => $boolEnv('AUTH_SESSION_ISSUING_ENABLED', false),
    'refresh_enabled' => $boolEnv('AUTH_REFRESH_ENABLED', false),
    'require_token_pepper' => $boolEnv('AUTH_REQUIRE_TOKEN_PEPPER', true),
    'session_ttl_seconds' => $intEnv('AUTH_SESSION_TTL_SECONDS', 900),
    'refresh_ttl_seconds' => $intEnv('AUTH_REFRESH_TTL_SECONDS', 1209600),
    'token_pepper' => $env('AUTH_TOKEN_PEPPER', ''),
    'login_rate_limit_window_seconds' => $intEnv('AUTH_LOGIN_RATE_LIMIT_WINDOW_SECONDS', 900),
    'login_rate_limit_max_attempts' => $intEnv('AUTH_LOGIN_RATE_LIMIT_MAX_ATTEMPTS', 5),
    'audit_retention_days' => $intEnv('AUTH_AUDIT_RETENTION_DAYS', 365),
    'password_hash_memory_cost' => $intEnv('AUTH_PASSWORD_HASH_MEMORY_COST', 65536),
    'password_hash_time_cost' => $intEnv('AUTH_PASSWORD_HASH_TIME_COST', 4),
    'password_hash_threads' => $intEnv('AUTH_PASSWORD_HASH_THREADS', 2),
];
