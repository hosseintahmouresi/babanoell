<?php
declare(strict_types=1);
use Babanoell\Loans\Database\Migration;
return new class implements Migration {
    public function version(): string { return '202608260005'; }
    public function name(): string { return 'create_loan_request_status_history_table'; }
    public function up(PDO $pdo): void {
        $pdo->exec("CREATE TABLE IF NOT EXISTS loan_request_status_history (
            id CHAR(26) NOT NULL PRIMARY KEY,
            request_id CHAR(26) NOT NULL,
            from_status VARCHAR(32) NULL,
            to_status VARCHAR(32) NOT NULL,
            actor_user_id CHAR(26) NOT NULL,
            reason TEXT NULL,
            source VARCHAR(80) NOT NULL DEFAULT 'api',
            created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
            KEY idx_loan_request_status_history_request (request_id, created_at),
            CONSTRAINT fk_loan_request_status_history_request FOREIGN KEY (request_id) REFERENCES loan_requests(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
};
