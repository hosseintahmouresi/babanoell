<?php
declare(strict_types=1);
use Babanoell\Loans\Database\Migration;

return new class implements Migration {
    public function version(): string { return '202608270001'; }
    public function name(): string { return 'add_request_access_indexes'; }
    public function up(PDO $pdo): void {
        $indexes = [
            'idx_loan_requests_created_by' => '(created_by_user_id, updated_at)',
            'idx_loan_requests_applicant' => '(applicant_user_id, updated_at)',
        ];
        foreach ($indexes as $name => $columns) {
            $s = $pdo->prepare('SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = :table_name AND index_name = :index_name');
            $s->execute([':table_name' => 'loan_requests', ':index_name' => $name]);
            if ((int)$s->fetchColumn() === 0) {
                $pdo->exec('ALTER TABLE loan_requests ADD KEY '.$name.' '.$columns);
            }
        }
    }
};
