<?php
declare(strict_types=1);
use Babanoell\Loans\Database\Migration;
return new class implements Migration {
    public function version(): string { return '202608260003'; }
    public function name(): string { return 'create_calculation_runs_table'; }
    public function up(PDO $pdo): void {
        $pdo->exec("CREATE TABLE IF NOT EXISTS calculation_runs (
            id CHAR(26) NOT NULL PRIMARY KEY,
            plan_id CHAR(26) NOT NULL,
            plan_version_id CHAR(26) NOT NULL,
            actor_user_id CHAR(26) NOT NULL,
            input_amount DECIMAL(20,2) NOT NULL,
            installment_count INT UNSIGNED NOT NULL,
            installment_interval INT UNSIGNED NOT NULL DEFAULT 1,
            commission_override DECIMAL(10,4) NULL,
            input_json JSON NOT NULL,
            result_json JSON NOT NULL,
            result_hash CHAR(64) NOT NULL,
            created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
            KEY idx_calculation_runs_plan (plan_id, created_at),
            KEY idx_calculation_runs_actor (actor_user_id, created_at),
            CONSTRAINT fk_calculation_runs_plan FOREIGN KEY (plan_id) REFERENCES installment_plans(id),
            CONSTRAINT fk_calculation_runs_version FOREIGN KEY (plan_version_id) REFERENCES installment_plan_versions(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
};
