<?php
declare(strict_types=1);
use Babanoell\Loans\Database\Migration;

return new class implements Migration {
    public function version(): string { return '202611070001'; }
    public function name(): string { return 'immutable_credit_report_evidence'; }
    public function up(PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS credit_report_evidence (
    verification_id CHAR(26) NOT NULL PRIMARY KEY,
    request_id CHAR(26) NOT NULL,
    provider_reference VARCHAR(180) NOT NULL,
    report_code VARCHAR(180) NOT NULL,
    subject_fingerprint CHAR(64) NOT NULL,
    report_sha256 CHAR(64) NOT NULL,
    canonical_json LONGTEXT NOT NULL,
    proof_json JSON NOT NULL,
    verified_at DATETIME(6) NOT NULL,
    valid_until DATETIME(6) NOT NULL,
    consumed_at DATETIME(6) NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    KEY idx_credit_evidence_request (request_id, verified_at),
    CONSTRAINT fk_credit_evidence_verification FOREIGN KEY (verification_id) REFERENCES verification_requests (id),
    CONSTRAINT fk_credit_evidence_request FOREIGN KEY (request_id) REFERENCES loan_requests (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }
};
