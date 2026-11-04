<?php
declare(strict_types=1);
use Babanoell\Loans\Database\Migration;
return new class implements Migration {
    public function version(): string { return '202610230002'; }
    public function name(): string { return 'bank_cycle_projection'; }
    public function up(PDO $pdo): void {
        $stmt=$pdo->query("SHOW COLUMNS FROM loan_requests LIKE 'bank_settlement_cycle_id'");
        if(!$stmt->fetch())$pdo->exec('ALTER TABLE loan_requests ADD COLUMN bank_settlement_cycle_id CHAR(26) NULL, ADD KEY idx_bank_cycle_projection (bank_settlement_cycle_id)');
    }
};
