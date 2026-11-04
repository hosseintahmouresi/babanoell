<?php
declare(strict_types=1);
use Babanoell\Loans\Database\Migration;

return new class implements Migration {
    public function version(): string { return '202609260008'; }
    public function name(): string { return 'complete_case_workflow'; }

    public function up(PDO $pdo): void
    {
        $columnExists = static function(string $table, string $column) use ($pdo): bool {
            $s=$pdo->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=:t AND column_name=:c');
            $s->execute([':t'=>$table, ':c'=>$column]);
            return (int)$s->fetchColumn() > 0;
        };
        $indexExists = static function(string $table, string $index) use ($pdo): bool {
            $s=$pdo->prepare('SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name=:t AND index_name=:i');
            $s->execute([':t'=>$table, ':i'=>$index]);
            return (int)$s->fetchColumn() > 0;
        };
        $addColumn = static function(string $table, string $column, string $definition) use ($pdo,$columnExists): void {
            if (!$columnExists($table,$column)) $pdo->exec('ALTER TABLE `'.$table.'` ADD COLUMN `'.$column.'` '.$definition);
        };
        $addIndex = static function(string $table, string $index, string $definition) use ($pdo,$indexExists): void {
            if (!$indexExists($table,$index)) $pdo->exec('ALTER TABLE `'.$table.'` ADD KEY `'.$index.'` '.$definition);
        };

        foreach ([
            'row_version'=>'INT UNSIGNED NOT NULL DEFAULT 1',
            'last_transition_at'=>'DATETIME(6) NULL',
            'rejected_at'=>'DATETIME(6) NULL',
            'cancelled_at'=>'DATETIME(6) NULL',
            'archived_at'=>'DATETIME(6) NULL',
            'document_receipt_note'=>'VARCHAR(1000) NULL',
            'document_received_by_user_id'=>'CHAR(26) NULL',
            'settlement_updated_by_user_id'=>'CHAR(26) NULL',
            'financier_payment_note'=>'VARCHAR(1000) NULL',
            'financier_payment_updated_at'=>'DATETIME(6) NULL',
            'financier_payment_updated_by_user_id'=>'CHAR(26) NULL'
        ] as $column=>$definition) $addColumn('loan_requests',$column,$definition);
        $addIndex('loan_requests','idx_loan_requests_row_version','(id,row_version)');
        $addIndex('loan_requests','idx_loan_requests_last_transition','(last_transition_at,status)');
        $addIndex('loan_requests','idx_loan_requests_document_receipt','(document_receipt_status,document_received_at)');
        $addIndex('loan_requests','idx_loan_requests_financier_payment','(financier_payment_status,financier_payment_updated_at)');

        $pdo->exec("UPDATE loan_requests SET bank_settlement_status='not_recorded' WHERE bank_settlement_status IS NULL OR TRIM(bank_settlement_status)=''");
        $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS request_message_reads (
    message_id CHAR(26) NOT NULL,
    user_id CHAR(26) NOT NULL,
    read_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (message_id,user_id),
    KEY idx_request_message_reads_user (user_id,read_at),
    CONSTRAINT fk_request_message_reads_message FOREIGN KEY (message_id) REFERENCES request_messages(id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS bank_settlement_history (
    id CHAR(26) NOT NULL,
    request_id CHAR(26) NOT NULL,
    status VARCHAR(32) NOT NULL,
    settled_at DATETIME(6) NULL,
    note VARCHAR(1000) NULL,
    actor_user_id CHAR(26) NOT NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    KEY idx_bank_settlement_history_request (request_id,created_at),
    CONSTRAINT fk_bank_settlement_history_request FOREIGN KEY (request_id) REFERENCES loan_requests(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS merchant_settlement_history (
    id CHAR(26) NOT NULL,
    request_id CHAR(26) NOT NULL,
    status VARCHAR(32) NOT NULL,
    settlement_date DATETIME(6) NULL,
    cycle_id CHAR(26) NULL,
    note VARCHAR(1000) NULL,
    actor_user_id CHAR(26) NOT NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    KEY idx_merchant_settlement_history_request (request_id,created_at),
    KEY idx_merchant_settlement_history_cycle (cycle_id,created_at),
    CONSTRAINT fk_merchant_settlement_history_request FOREIGN KEY (request_id) REFERENCES loan_requests(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS financier_payment_history (
    id CHAR(26) NOT NULL,
    request_id CHAR(26) NOT NULL,
    status VARCHAR(32) NOT NULL,
    note VARCHAR(1000) NULL,
    actor_user_id CHAR(26) NOT NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    KEY idx_financier_payment_history_request (request_id,created_at),
    CONSTRAINT fk_financier_payment_history_request FOREIGN KEY (request_id) REFERENCES loan_requests(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }
};
