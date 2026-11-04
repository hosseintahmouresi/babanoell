<?php
declare(strict_types=1);
use Babanoell\Loans\Database\Migration;

return new class implements Migration {
    public function version(): string { return '202609260001'; }
    public function name(): string { return 'extend_core_entities'; }

    public function up(PDO $pdo): void
    {
        $columnExists = static function(PDO $pdo, string $table, string $column): bool {
            $s=$pdo->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=:table_name AND column_name=:column_name');
            $s->execute([':table_name'=>$table, ':column_name'=>$column]);
            return (int)$s->fetchColumn() > 0;
        };
        $indexExists = static function(PDO $pdo, string $table, string $index): bool {
            $s=$pdo->prepare('SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name=:table_name AND index_name=:index_name');
            $s->execute([':table_name'=>$table, ':index_name'=>$index]);
            return (int)$s->fetchColumn() > 0;
        };
        $addColumn = static function(PDO $pdo, string $table, string $column, string $definition) use ($columnExists): void {
            if (!$columnExists($pdo,$table,$column)) $pdo->exec('ALTER TABLE `'.$table.'` ADD COLUMN `'.$column.'` '.$definition);
        };
        $addIndex = static function(PDO $pdo, string $table, string $index, string $columns) use ($indexExists): void {
            if (!$indexExists($pdo,$table,$index)) $pdo->exec('ALTER TABLE `'.$table.'` ADD KEY `'.$index.'` '.$columns);
        };

        $addColumn($pdo,'installment_plans','legacy_id','BIGINT UNSIGNED NULL');
        $addColumn($pdo,'installment_plans','display_order','INT UNSIGNED NOT NULL DEFAULT 0');
        $addColumn($pdo,'installment_plans','eligibility_json','JSON NULL');
        $addIndex($pdo,'installment_plans','idx_installment_plans_display_order','(display_order, status)');
        $addIndex($pdo,'installment_plans','idx_installment_plans_legacy_id','(legacy_id)');

        $columns = [
            'legacy_id' => 'BIGINT UNSIGNED NULL',
            'acceptor_user_id' => 'CHAR(26) NULL',
            'request_origin' => "VARCHAR(30) NOT NULL DEFAULT 'panel'",
            'acceptor_assignment_mode' => "VARCHAR(30) NOT NULL DEFAULT ''",
            'marketplace_status' => "VARCHAR(30) NOT NULL DEFAULT ''",
            'marketplace_claimed_by_user_id' => 'CHAR(26) NULL',
            'marketplace_claimed_at' => 'DATETIME(6) NULL',
            'requested_service_scope' => "VARCHAR(30) NOT NULL DEFAULT ''",
            'requested_cities_json' => 'JSON NULL',
            'requested_location_terms_json' => 'JSON NULL',
            'requested_activity_categories_json' => 'JSON NULL',
            'request_public_summary' => 'TEXT NULL',
            'acceptor_invitation_code' => 'VARCHAR(100) NULL',
            'acceptor_invited_by_user_id' => 'CHAR(26) NULL',
            'acceptor_invitation_locked' => 'TINYINT(1) NOT NULL DEFAULT 0',
            'assigned_operations_user_id' => 'CHAR(26) NULL',
            'description' => 'TEXT NULL',
            'final_settlement_amount' => 'DECIMAL(20,2) NOT NULL DEFAULT 0',
            'leasing_deposit_amount' => 'DECIMAL(20,2) NULL',
            'settlement_status' => "VARCHAR(32) NOT NULL DEFAULT 'not_started'",
            'settlement_cycle_id' => 'CHAR(26) NULL',
            'settlement_date' => 'DATETIME(6) NULL',
            'document_receipt_status' => "VARCHAR(32) NOT NULL DEFAULT 'unreviewed'",
            'document_received_at' => 'DATETIME(6) NULL',
            'bank_account_number' => 'VARCHAR(80) NULL',
            'bank_payment_amount' => 'DECIMAL(20,2) NOT NULL DEFAULT 0',
            'bank_payment_at' => 'DATETIME(6) NULL',
            'bank_settlement_status' => "VARCHAR(32) NOT NULL DEFAULT ''",
            'bank_settlement_note' => 'TEXT NULL',
            'bank_settled_at' => 'DATETIME(6) NULL',
            'bank_settled_by_user_id' => 'CHAR(26) NULL',
            'bank_settlement_updated_at' => 'DATETIME(6) NULL',
            'credit_report_external_id' => 'VARCHAR(255) NULL',
            'credit_score' => 'INT NULL',
            'credit_risk' => 'VARCHAR(64) NULL',
            'financier_payment_status' => "VARCHAR(32) NOT NULL DEFAULT 'unreviewed'"
        ];
        foreach ($columns as $name=>$definition) $addColumn($pdo,'loan_requests',$name,$definition);

        foreach ([
            'idx_loan_requests_legacy_id'=>'(legacy_id)',
            'idx_loan_requests_acceptor'=>'(acceptor_user_id, updated_at)',
            'idx_loan_requests_origin'=>'(request_origin, updated_at)',
            'idx_loan_requests_marketplace'=>'(marketplace_status, marketplace_claimed_by_user_id)',
            'idx_loan_requests_operations'=>'(assigned_operations_user_id, status)',
            'idx_loan_requests_settlement'=>'(settlement_status, settlement_date)',
            'idx_loan_requests_settlement_cycle'=>'(settlement_cycle_id)',
            'idx_loan_requests_bank_settlement'=>'(bank_settlement_status, bank_settled_at)',
            'idx_loan_requests_credit_report'=>'(credit_report_external_id)'
        ] as $name=>$cols) $addIndex($pdo,'loan_requests',$name,$cols);
    }
};
