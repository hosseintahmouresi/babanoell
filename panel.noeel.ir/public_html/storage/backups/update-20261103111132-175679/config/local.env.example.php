<?php
declare(strict_types=1);
return [
    'APP_ENV'=>'staging',
    'SERVICE_NAME'=>'panel',
    'PANEL_IDENTITY_BASE_URL'=>'https://identity.noeel.ir',
    'PANEL_LOANS_BASE_URL'=>'https://loans.noeel.ir',
    'PANEL_DOCUMENTS_BASE_URL'=>'https://documents.noeel.ir',
    'PANEL_PARTNERS_BASE_URL'=>'https://partners.noeel.ir',
    'PANEL_FINANCE_BASE_URL'=>'https://finance.noeel.ir',
    'PANEL_INTEGRATIONS_BASE_URL'=>'https://integrations.noeel.ir',
    'PANEL_SESSION_ENCRYPTION_KEY'=>'CHANGE_ME_BASE64_32_BYTE_KEY',
    'PANEL_HTTP_TIMEOUT_SECONDS'=>'6',
    'PANEL_SESSION_IDLE_SECONDS'=>'1800',
    'PANEL_SESSION_ABSOLUTE_SECONDS'=>'28800',

    // Phase 2A: optional HMAC service identity. Use the SAME secret in every Noel service before switching to required.
    'NOEEL_SERVICE_AUTH_MODE' => 'optional', // disabled | optional | required
    'NOEEL_SERVICE_SHARED_SECRET' => '',
];
