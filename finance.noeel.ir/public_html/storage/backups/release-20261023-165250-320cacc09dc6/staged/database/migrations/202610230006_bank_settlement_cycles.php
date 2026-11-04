<?php
declare(strict_types=1);
use Babanoell\Finance\Database\Migration;
return new class implements Migration {
    public function version(): string { return '202610230006'; }
    public function name(): string { return 'bank_settlement_cycles'; }
    public function up(PDO $pdo): void {
        $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS bank_settlement_cycles (
 id CHAR(26) NOT NULL, cycle_no VARCHAR(42) NOT NULL,
 bank_user_id CHAR(26) NOT NULL, created_by_user_id CHAR(26) NOT NULL,
 settlement_date DATE NOT NULL, notes VARCHAR(3000) NULL,
 item_count INT UNSIGNED NOT NULL, total_loan_amount DECIMAL(20,2) NOT NULL,
 request_hash CHAR(64) NOT NULL, idempotency_key VARCHAR(128) NOT NULL,
 projection_status VARCHAR(24) NOT NULL DEFAULT 'pending',
 projected_at DATETIME(6) NULL, projection_attempts INT UNSIGNED NOT NULL DEFAULT 0,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 PRIMARY KEY(id), UNIQUE KEY uq_bank_cycles_no(cycle_no),
 UNIQUE KEY uq_bank_cycles_idempotency(created_by_user_id,idempotency_key),
 KEY idx_bank_cycles_owner(bank_user_id,settlement_date,created_at),
 KEY idx_bank_cycles_projection(projection_status,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS bank_settlement_cycle_items (
 cycle_id CHAR(26) NOT NULL, request_id CHAR(26) NOT NULL,
 applicant_name VARCHAR(255) NOT NULL DEFAULT '',
 national_code VARCHAR(20) NOT NULL DEFAULT '',
 loan_amount DECIMAL(20,2) NOT NULL,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 PRIMARY KEY(cycle_id,request_id), UNIQUE KEY uq_bank_cycle_request(request_id),
 KEY idx_bank_cycle_items_cycle(cycle_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }
};
