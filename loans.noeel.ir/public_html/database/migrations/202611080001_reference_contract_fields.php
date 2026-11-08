<?php
declare(strict_types=1);
use Babanoell\Loans\Database\Migration;
return new class implements Migration {
    public function version(): string {return '202611080001';}
    public function name(): string {return 'reference_contract_fields';}
    public function up(PDO $pdo): void {
        $pdo->exec('ALTER TABLE loan_requests ADD COLUMN IF NOT EXISTS certificate_city VARCHAR(180) NULL, ADD COLUMN IF NOT EXISTS retirement_code VARCHAR(120) NULL, ADD COLUMN IF NOT EXISTS retirement_organization VARCHAR(255) NULL, ADD COLUMN IF NOT EXISTS delivery_date DATE NULL');
    }
};
