<?php
declare(strict_types=1);
use Babanoell\Loans\Database\Migration;
return new class implements Migration {
    public function version(): string { return '202608260006'; }
    public function name(): string { return 'create_loan_request_events_table'; }
    public function up(PDO $pdo): void {
        $pdo->exec("CREATE TABLE IF NOT EXISTS loan_request_events (
            id CHAR(26) NOT NULL PRIMARY KEY,
            request_id CHAR(26) NULL,
            event_type VARCHAR(100) NOT NULL,
            actor_user_id CHAR(26) NULL,
            result VARCHAR(24) NOT NULL,
            details_json JSON NULL,
            created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
            KEY idx_loan_request_events_request (request_id, created_at),
            KEY idx_loan_request_events_type (event_type, created_at),
            CONSTRAINT fk_loan_request_events_request FOREIGN KEY (request_id) REFERENCES loan_requests(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
};
