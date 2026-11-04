<?php
declare(strict_types=1);
use Babanoell\Loans\Database\Migration;
return new class implements Migration {
    public function version(): string { return '202609260006'; }
    public function name(): string { return 'create_cooperation_notification_core'; }
    public function up(PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS cooperation_requests (
    id CHAR(26) NOT NULL,
    request_no VARCHAR(40) NOT NULL,
    legacy_id BIGINT UNSIGNED NULL,
    applicant_user_id CHAR(26) NULL,
    request_type VARCHAR(32) NOT NULL DEFAULT 'individual',
    status VARCHAR(32) NOT NULL DEFAULT 'submitted',
    contract_token_hash CHAR(64) NULL,
    contract_signed_status VARCHAR(24) NOT NULL DEFAULT 'pending',
    contract_signed_at DATETIME(6) NULL,
    contract_expires_at DATETIME(6) NULL,
    otp_hash VARCHAR(255) NULL,
    otp_expires_at DATETIME(6) NULL,
    data_json JSON NULL,
    created_by_user_id CHAR(26) NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    UNIQUE KEY uq_cooperation_requests_no (request_no),
    KEY idx_cooperation_requests_status_type (status, request_type),
    KEY idx_cooperation_requests_applicant (applicant_user_id, created_at),
    KEY idx_cooperation_requests_legacy (legacy_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS cooperation_documents (
    cooperation_request_id CHAR(26) NOT NULL,
    document_id CHAR(26) NOT NULL,
    relation_code VARCHAR(64) NOT NULL DEFAULT 'attachment',
    created_by_user_id CHAR(26) NOT NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (cooperation_request_id, document_id, relation_code),
    KEY idx_cooperation_documents_document (document_id),
    CONSTRAINT fk_cooperation_documents_request FOREIGN KEY (cooperation_request_id) REFERENCES cooperation_requests(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS notifications (
    id CHAR(26) NOT NULL,
    recipient_user_id CHAR(26) NOT NULL,
    notification_type VARCHAR(64) NOT NULL,
    channel VARCHAR(24) NOT NULL DEFAULT 'panel',
    title VARCHAR(190) NOT NULL,
    body TEXT NOT NULL,
    target_url VARCHAR(1000) NULL,
    status VARCHAR(24) NOT NULL DEFAULT 'queued',
    read_at DATETIME(6) NULL,
    sent_at DATETIME(6) NULL,
    metadata_json JSON NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    KEY idx_notifications_recipient (recipient_user_id, status, created_at),
    KEY idx_notifications_type (notification_type, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS integration_outbox (
    id CHAR(26) NOT NULL,
    event_type VARCHAR(100) NOT NULL,
    aggregate_type VARCHAR(64) NOT NULL,
    aggregate_id CHAR(26) NULL,
    destination VARCHAR(100) NOT NULL,
    payload_json JSON NOT NULL,
    payload_hash CHAR(64) NOT NULL,
    status VARCHAR(24) NOT NULL DEFAULT 'pending',
    attempts INT UNSIGNED NOT NULL DEFAULT 0,
    next_attempt_at DATETIME(6) NULL,
    last_http_code INT NULL,
    last_error VARCHAR(1000) NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    delivered_at DATETIME(6) NULL,
    PRIMARY KEY (id),
    KEY idx_integration_outbox_delivery (status, next_attempt_at, created_at),
    KEY idx_integration_outbox_aggregate (aggregate_type, aggregate_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }
};
