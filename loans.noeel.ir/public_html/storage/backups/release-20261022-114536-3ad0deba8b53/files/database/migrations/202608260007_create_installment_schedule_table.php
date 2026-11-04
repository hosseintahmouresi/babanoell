<?php
declare(strict_types=1);
use Babanoell\Loans\Database\Migration;
return new class implements Migration {
    public function version(): string { return '202608260007'; }
    public function name(): string { return 'create_installment_schedule_table'; }
    public function up(PDO $pdo): void {
        $pdo->exec("CREATE TABLE IF NOT EXISTS installment_schedule (
            id CHAR(26) NOT NULL PRIMARY KEY,
            request_id CHAR(26) NOT NULL,
            sequence_no INT UNSIGNED NOT NULL,
            due_date DATE NOT NULL,
            amount DECIMAL(20,2) NOT NULL,
            principal_amount DECIMAL(20,2) NOT NULL,
            profit_amount DECIMAL(20,2) NOT NULL,
            status VARCHAR(24) NOT NULL DEFAULT 'scheduled',
            created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
            UNIQUE KEY uq_installment_schedule_request_sequence (request_id, sequence_no),
            KEY idx_installment_schedule_due_date (due_date, status),
            CONSTRAINT fk_installment_schedule_request FOREIGN KEY (request_id) REFERENCES loan_requests(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
};
