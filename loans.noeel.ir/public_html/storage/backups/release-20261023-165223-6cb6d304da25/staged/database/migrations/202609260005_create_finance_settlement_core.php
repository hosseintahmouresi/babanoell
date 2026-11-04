<?php
declare(strict_types=1);
use Babanoell\Loans\Database\Migration;
return new class implements Migration {
    public function version(): string { return '202609260005'; }
    public function name(): string { return 'create_finance_settlement_core'; }
    public function up(PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS financial_receipts (
    id CHAR(26) NOT NULL,
    request_id CHAR(26) NOT NULL,
    receipt_type VARCHAR(32) NOT NULL DEFAULT 'transfer',
    amount DECIMAL(20,2) NULL,
    document_id CHAR(26) NOT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'pending',
    external_reference VARCHAR(190) NULL,
    submitted_by_user_id CHAR(26) NOT NULL,
    reviewed_by_user_id CHAR(26) NULL,
    review_note VARCHAR(1000) NULL,
    submitted_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    reviewed_at DATETIME(6) NULL,
    PRIMARY KEY (id),
    KEY idx_financial_receipts_request (request_id, status, submitted_at),
    KEY idx_financial_receipts_document (document_id),
    CONSTRAINT fk_financial_receipts_request FOREIGN KEY (request_id) REFERENCES loan_requests(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS settlement_cycles (
    id CHAR(26) NOT NULL,
    cycle_no VARCHAR(40) NOT NULL,
    merchant_id CHAR(26) NOT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'open',
    settlement_date DATE NULL,
    total_amount DECIMAL(20,2) NOT NULL DEFAULT 0,
    leasing_total DECIMAL(20,2) NOT NULL DEFAULT 0,
    merchant_total DECIMAL(20,2) NOT NULL DEFAULT 0,
    description TEXT NULL,
    created_by_user_id CHAR(26) NOT NULL,
    closed_by_user_id CHAR(26) NULL,
    closed_at DATETIME(6) NULL,
    reopened_by_user_id CHAR(26) NULL,
    reopened_at DATETIME(6) NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    UNIQUE KEY uq_settlement_cycles_no (cycle_no),
    KEY idx_settlement_cycles_merchant (merchant_id, status, settlement_date),
    CONSTRAINT fk_settlement_cycles_merchant FOREIGN KEY (merchant_id) REFERENCES merchants(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS settlement_cycle_items (
    cycle_id CHAR(26) NOT NULL,
    request_id CHAR(26) NOT NULL,
    settlement_amount DECIMAL(20,2) NOT NULL,
    leasing_amount DECIMAL(20,2) NOT NULL DEFAULT 0,
    merchant_amount DECIMAL(20,2) NOT NULL DEFAULT 0,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (cycle_id, request_id),
    UNIQUE KEY uq_settlement_item_request (request_id),
    CONSTRAINT fk_settlement_items_cycle FOREIGN KEY (cycle_id) REFERENCES settlement_cycles(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_settlement_items_request FOREIGN KEY (request_id) REFERENCES loan_requests(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS settlement_cycle_receipts (
    id CHAR(26) NOT NULL,
    cycle_id CHAR(26) NOT NULL,
    document_id CHAR(26) NOT NULL,
    amount DECIMAL(20,2) NULL,
    created_by_user_id CHAR(26) NOT NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    KEY idx_settlement_receipts_cycle (cycle_id, created_at),
    KEY idx_settlement_receipts_document (document_id),
    CONSTRAINT fk_settlement_receipts_cycle FOREIGN KEY (cycle_id) REFERENCES settlement_cycles(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }
};
