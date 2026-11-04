<?php
declare(strict_types=1);
use Babanoell\Loans\Database\Migration;
return new class implements Migration {
    public function version(): string { return '202608260001'; }
    public function name(): string { return 'create_installment_plans_table'; }
    public function up(PDO $pdo): void {
        $pdo->exec("CREATE TABLE IF NOT EXISTS installment_plans (
            id CHAR(26) NOT NULL PRIMARY KEY,
            code VARCHAR(80) NOT NULL,
            name VARCHAR(190) NOT NULL,
            type VARCHAR(20) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'draft',
            current_version INT UNSIGNED NOT NULL DEFAULT 1,
            created_by_user_id CHAR(26) NOT NULL,
            created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
            updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
            UNIQUE KEY uq_installment_plans_code (code),
            KEY idx_installment_plans_status_type (status, type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
};
