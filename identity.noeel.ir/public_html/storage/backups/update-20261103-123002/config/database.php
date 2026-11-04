<?php
declare(strict_types=1);

require_once __DIR__ . '/env.php';
babanoell_auth_load_env();

$env = static fn (string $key, ?string $default = null): ?string => babanoell_auth_env($key, $default);

return [
    'driver' => 'mysql',
    'host' => $env('AUTH_DB_HOST', '127.0.0.1'),
    'port' => (int) ($env('AUTH_DB_PORT', '3306') ?? '3306'),
    'database' => $env('AUTH_DB_NAME', 'babanoell_auth'),
    'username' => $env('AUTH_DB_USER', 'auth_service'),
    'password' => $env('AUTH_DB_PASSWORD', ''),
    'charset' => $env('AUTH_DB_CHARSET', 'utf8mb4'),
];
