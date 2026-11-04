<?php
declare(strict_types=1);
use Babanoell\Loans\Database\Migration;
return new class implements Migration {
    public function version(): string { return '202609260004'; }
    public function name(): string { return 'create_review_grant_core'; }
    public function up(PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS credit_checks (
    id CHAR(26) NOT NULL,
    request_id CHAR(26) NOT NULL,
    provider VARCHAR(64) NOT NULL,
    external_report_id VARCHAR(255) NULL,
    status VARCHAR(32) NOT NULL,
    score INT NULL,
    risk VARCHAR(64) NULL,
    result_summary_json JSON NULL,
    response_hash CHAR(64) NULL,
    requested_by_user_id CHAR(26) NULL,
    requested_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    completed_at DATETIME(6) NULL,
    PRIMARY KEY (id),
    KEY idx_credit_checks_request (request_id, requested_at),
    KEY idx_credit_checks_external (provider, external_report_id),
    CONSTRAINT fk_credit_checks_request FOREIGN KEY (request_id) REFERENCES loan_requests(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS bank_reviews (
    id CHAR(26) NOT NULL,
    request_id CHAR(26) NOT NULL,
    reviewer_user_id CHAR(26) NOT NULL,
    status VARCHAR(32) NOT NULL,
    reason VARCHAR(1000) NULL,
    note TEXT NULL,
    external_reference VARCHAR(190) NULL,
    metadata_json JSON NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    KEY idx_bank_reviews_request (request_id, created_at),
    KEY idx_bank_reviews_reviewer (reviewer_user_id, created_at),
    CONSTRAINT fk_bank_reviews_request FOREIGN KEY (request_id) REFERENCES loan_requests(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS grant_records (
    id CHAR(26) NOT NULL,
    request_id CHAR(26) NOT NULL,
    grant_status VARCHAR(32) NOT NULL DEFAULT 'recorded',
    loan_number VARCHAR(190) NULL,
    contract_number VARCHAR(190) NULL,
    granted_amount DECIMAL(20,2) NOT NULL,
    granted_at DATETIME(6) NOT NULL,
    recorded_by_user_id CHAR(26) NOT NULL,
    metadata_json JSON NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    UNIQUE KEY uq_grant_records_request (request_id),
    KEY idx_grant_records_granted_at (granted_at),
    CONSTRAINT fk_grant_records_request FOREIGN KEY (request_id) REFERENCES loan_requests(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }
};
