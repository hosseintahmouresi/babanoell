<?php
declare(strict_types=1);
use Babanoell\Loans\Database\Migration;

return new class implements Migration {
    public function version(): string { return '202609220001'; }
    public function name(): string { return 'enforce_installment_plan_statuses'; }
    public function up(PDO $pdo): void
    {
        // Preserve known canonical states and fail closed for any legacy/unknown value.
        $pdo->exec("UPDATE installment_plans SET status='inactive' WHERE status NOT IN ('draft','active','inactive','archived') OR status IS NULL OR TRIM(status)=''");

        $check=$pdo->prepare("SELECT COUNT(*) FROM information_schema.table_constraints WHERE constraint_schema=DATABASE() AND table_name='installment_plans' AND constraint_name='chk_installment_plans_status' AND constraint_type='CHECK'");
        $check->execute();
        if((int)$check->fetchColumn()===0){
            $pdo->exec("ALTER TABLE installment_plans ADD CONSTRAINT chk_installment_plans_status CHECK (status IN ('draft','active','inactive','archived'))");
        }
    }
};
