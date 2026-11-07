<?php
declare(strict_types=1);
use Babanoell\Finance\Database\Migration;

return new class implements Migration {
    public function version(): string { return '202611070001'; }
    public function name(): string { return 'bank_cycle_amount_basis'; }
    public function up(PDO $pdo): void
    {
        $query=$pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='bank_settlement_cycles' AND column_name='amount_basis'");
        $query->execute();
        if((int)$query->fetchColumn()===0)$pdo->exec("ALTER TABLE bank_settlement_cycles ADD COLUMN amount_basis VARCHAR(40) NOT NULL DEFAULT 'facility_amount_legacy'");
    }
};
