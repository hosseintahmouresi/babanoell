<?php
return [
 'SERVICE_NAME'=>'finance','APP_ENV'=>'production',
 'FINANCE_DB_HOST'=>'localhost','FINANCE_DB_PORT'=>'3306','FINANCE_DB_NAME'=>'','FINANCE_DB_USER'=>'','FINANCE_DB_PASSWORD'=>'','FINANCE_DB_CHARSET'=>'utf8mb4',
 'FINANCE_IDENTITY_BASE_URL'=>'https://identity.noeel.ir','FINANCE_LOANS_BASE_URL'=>'https://loans.noeel.ir','FINANCE_PARTNERS_BASE_URL'=>'https://partners.noeel.ir','FINANCE_DOCUMENTS_BASE_URL'=>'https://documents.noeel.ir',
 'FINANCE_INTEGRATIONS_BASE_URL'=>'https://integrations.noeel.ir','FINANCE_GREENPAY_OTP_ENABLED'=>'0',
 'FINANCE_GREENPAY_TRANSFERS_ENABLED'=>'0', // Keep disabled until bank sandbox, provider verification and operational approval
 'FINANCE_GREENPAY_ENCRYPTION_KEY'=>'', // 64 hex chars generated independently; keep stable for decryption
 'FINANCE_GREENPAY_EXECUTION_KEY'=>'', // 32+ chars, match Integrations execution key only
 'FINANCE_HTTP_TIMEOUT_SECONDS'=>'8','NOEEL_SERVICE_SHARED_SECRET'=>'','NOEEL_SERVICE_AUTH_MODE'=>'optional',
];
