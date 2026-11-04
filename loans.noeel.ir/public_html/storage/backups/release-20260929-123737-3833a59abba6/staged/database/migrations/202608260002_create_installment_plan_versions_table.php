<?php
declare(strict_types=1);
use Babanoell\Loans\Database\Migration;
return new class implements Migration {
    public function version(): string { return '202608260002'; }
    public function name(): string { return 'create_installment_plan_versions_table'; }
    public function up(PDO $pdo): void {
        $pdo->exec("CREATE TABLE IF NOT EXISTS installment_plan_versions (
            id CHAR(26) NOT NULL PRIMARY KEY,
            plan_id CHAR(26) NOT NULL,
            version INT UNSIGNED NOT NULL,
            type VARCHAR(20) NOT NULL,
            settings_json JSON NOT NULL,
            display_fields_json JSON NULL,
            required_documents_json JSON NULL,
            conditions_text TEXT NULL,
            created_by_user_id CHAR(26) NOT NULL,
            created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
            UNIQUE KEY uq_installment_plan_version (plan_id, version),
            KEY idx_installment_plan_versions_plan (plan_id),
            CONSTRAINT fk_installment_plan_versions_plan FOREIGN KEY (plan_id) REFERENCES installment_plans(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
};
