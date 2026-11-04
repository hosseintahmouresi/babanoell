<?php
declare(strict_types=1);
return [
    'APP_ENV' => 'staging',
    'SERVICE_NAME' => 'documents',
    'DOCUMENTS_DB_HOST' => 'localhost',
    'DOCUMENTS_DB_PORT' => '3306',
    'DOCUMENTS_DB_NAME' => '',
    'DOCUMENTS_DB_USER' => '',
    'DOCUMENTS_DB_PASSWORD' => '',
    'DOCUMENTS_DB_CHARSET' => 'utf8mb4',
    'DOCUMENTS_IDENTITY_BASE_URL' => 'https://identity.noeel.ir',
    'DOCUMENTS_LOANS_BASE_URL' => 'https://loans.noeel.ir',
    'DOCUMENTS_PARTNERS_BASE_URL' => 'https://partners.noeel.ir',
    'DOCUMENTS_HTTP_TIMEOUT_SECONDS' => '8',
    'DOCUMENTS_MAX_UPLOAD_BYTES' => '10485760',

    // Phase 2A: optional HMAC service identity. Use the SAME secret in every Noel service before switching to required.
    'NOEEL_SERVICE_AUTH_MODE' => 'optional', // disabled | optional | required
    'NOEEL_SERVICE_SHARED_SECRET' => '',
    'DOCUMENTS_FINANCE_BASE_URL'=>'https://finance.noeel.ir',
];
