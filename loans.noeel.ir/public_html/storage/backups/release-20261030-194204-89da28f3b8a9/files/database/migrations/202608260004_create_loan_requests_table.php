<?php
declare(strict_types=1);
use Babanoell\Loans\Database\Migration;
return new class implements Migration {
    public function version(): string { return '202608260004'; }
    public function name(): string { return 'create_loan_requests_table'; }
    public function up(PDO $pdo): void {
        $pdo->exec("CREATE TABLE IF NOT EXISTS loan_requests (
            id CHAR(26) NOT NULL PRIMARY KEY,
            request_no VARCHAR(32) NOT NULL,
            idempotency_key VARCHAR(128) NULL,
            applicant_user_id CHAR(26) NULL,
            created_by_user_id CHAR(26) NOT NULL,
            merchant_ref VARCHAR(190) NULL,
            assigned_bank_user_id CHAR(26) NULL,
            plan_id CHAR(26) NOT NULL,
            plan_version_id CHAR(26) NOT NULL,
            calculation_run_id CHAR(26) NULL,
            status VARCHAR(32) NOT NULL DEFAULT 'draft',
            full_name VARCHAR(190) NULL,
            father_name VARCHAR(190) NULL,
            certificate_number VARCHAR(80) NULL,
            national_code VARCHAR(10) NULL,
            mobile_number VARCHAR(11) NULL,
            phone_number VARCHAR(32) NULL,
            birth_date DATE NULL,
            occupation VARCHAR(190) NULL,
            residential_address TEXT NULL,
            postal_code VARCHAR(10) NULL,
            work_address TEXT NULL,
            product_name VARCHAR(255) NULL,
            order_items_json JSON NULL,
            order_total DECIMAL(20,2) NOT NULL DEFAULT 0,
            requested_amount DECIMAL(20,2) NOT NULL DEFAULT 0,
            facility_amount DECIMAL(20,2) NOT NULL DEFAULT 0,
            prepayment DECIMAL(20,2) NOT NULL DEFAULT 0,
            wallet_charge DECIMAL(20,2) NOT NULL DEFAULT 0,
            acceptor_payment_amount DECIMAL(20,2) NOT NULL DEFAULT 0,
            installment_interval INT UNSIGNED NOT NULL DEFAULT 1,
            installment_count INT UNSIGNED NOT NULL DEFAULT 0,
            installment_amount DECIMAL(20,2) NOT NULL DEFAULT 0,
            total_payment DECIMAL(20,2) NOT NULL DEFAULT 0,
            profit DECIMAL(20,2) NOT NULL DEFAULT 0,
            guarantee_check_principal DECIMAL(20,2) NOT NULL DEFAULT 0,
            guarantee_check_total DECIMAL(20,2) NOT NULL DEFAULT 0,
            first_due_date DATE NULL,
            submitted_at DATETIME(6) NULL,
            granted_at DATETIME(6) NULL,
            created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
            updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
            UNIQUE KEY uq_loan_requests_no (request_no),
            UNIQUE KEY uq_loan_requests_idempotency (created_by_user_id, idempotency_key),
            KEY idx_loan_requests_status (status, updated_at),
            KEY idx_loan_requests_national (national_code, created_at),
            KEY idx_loan_requests_plan (plan_id, plan_version_id),
            KEY idx_loan_requests_bank_user (assigned_bank_user_id, status),
            CONSTRAINT fk_loan_requests_plan FOREIGN KEY (plan_id) REFERENCES installment_plans(id),
            CONSTRAINT fk_loan_requests_version FOREIGN KEY (plan_version_id) REFERENCES installment_plan_versions(id),
            CONSTRAINT fk_loan_requests_calculation FOREIGN KEY (calculation_run_id) REFERENCES calculation_runs(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
};
