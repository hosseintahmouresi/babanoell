<?php
declare(strict_types=1);
use Babanoell\Loans\Database\Migration;
return new class implements Migration {
    public function version(): string { return '202608260008'; }
    public function name(): string { return 'create_guarantors_table'; }
    public function up(PDO $pdo): void {
        $pdo->exec("CREATE TABLE IF NOT EXISTS loan_request_guarantors (
            id CHAR(26) NOT NULL PRIMARY KEY,
            request_id CHAR(26) NOT NULL,
            full_name VARCHAR(190) NOT NULL,
            national_code VARCHAR(10) NULL,
            mobile_number VARCHAR(11) NULL,
            relation_label VARCHAR(100) NULL,
            created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
            KEY idx_guarantors_request (request_id),
            CONSTRAINT fk_guarantors_request FOREIGN KEY (request_id) REFERENCES loan_requests(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
};
