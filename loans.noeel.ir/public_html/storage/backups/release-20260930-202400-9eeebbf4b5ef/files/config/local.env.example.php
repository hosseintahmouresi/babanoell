<?php
declare(strict_types=1);
return [
    'APP_ENV' => 'staging',
    'SERVICE_NAME' => 'loans',
    'LOANS_DB_HOST' => 'localhost',
    'LOANS_DB_PORT' => '3306',
    'LOANS_DB_NAME' => '',
    'LOANS_DB_USER' => '',
    'LOANS_DB_PASSWORD' => '',
    'LOANS_DB_CHARSET' => 'utf8mb4',
    'LOANS_IDENTITY_BASE_URL' => 'https://identity.noeel.ir',
    'LOANS_DOCUMENTS_BASE_URL' => 'https://documents.noeel.ir',
    'LOANS_PARTNERS_BASE_URL' => 'https://partners.noeel.ir',
    'LOANS_HTTP_TIMEOUT_SECONDS' => '6',

    // Phase 2A: optional HMAC service identity. Use the SAME secret in every Noel service before switching to required.
    'NOEEL_SERVICE_AUTH_MODE' => 'optional', // disabled | optional | required
    'NOEEL_SERVICE_SHARED_SECRET' => '',
];
