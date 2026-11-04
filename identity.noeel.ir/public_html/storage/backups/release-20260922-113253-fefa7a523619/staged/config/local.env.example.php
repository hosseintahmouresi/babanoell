<?php
declare(strict_types=1);

/**
 * Local/shared-hosting configuration template for the identity (auth) service.
 *
 * Copy to `config/local.env.php` (or let the web installer generate it) and fill
 * real values. NEVER commit the generated `local.env.php` file.
 *
 * On Docker/CI deployments these values come from real environment variables,
 * which always take precedence over this file.
 */
return [
    // Application
    'APP_ENV' => 'production',          // local | staging | production
    'SERVICE_NAME' => 'identity',

    // Database (from cPanel → MySQL Databases)
    'AUTH_DB_HOST' => 'localhost',
    'AUTH_DB_PORT' => '3306',
    'AUTH_DB_NAME' => 'cpaneluser_babanoell_auth',
    'AUTH_DB_USER' => 'cpaneluser_auth',
    'AUTH_DB_PASSWORD' => 'CHANGE_ME',
    'AUTH_DB_CHARSET' => 'utf8mb4',

    // Security switches — login stays OFF until you are ready to enable it.
    'AUTH_ENVIRONMENT' => 'production',
    'AUTH_ALLOW_HTTP_LOGIN_IN_LOCAL' => 'false',
    'AUTH_ALLOW_HTTP_LOGIN_IN_STAGING' => 'false',
    'AUTH_LOGIN_ENABLED' => 'false',
    'AUTH_SESSION_ISSUING_ENABLED' => 'false',
    'AUTH_REFRESH_ENABLED' => 'false',
    'AUTH_REQUIRE_TOKEN_PEPPER' => 'true',
    // Generate with: any long random string (32+ chars). Required to enable login.
    'AUTH_TOKEN_PEPPER' => 'CHANGE_ME_LONG_RANDOM_SECRET',
    'AUTH_SESSION_TTL_SECONDS' => '900',
    'AUTH_REFRESH_TTL_SECONDS' => '1209600',
    'AUTH_LOGIN_RATE_LIMIT_WINDOW_SECONDS' => '900',
    'AUTH_LOGIN_RATE_LIMIT_MAX_ATTEMPTS' => '5',
    'AUTH_AUDIT_RETENTION_DAYS' => '365',
];
