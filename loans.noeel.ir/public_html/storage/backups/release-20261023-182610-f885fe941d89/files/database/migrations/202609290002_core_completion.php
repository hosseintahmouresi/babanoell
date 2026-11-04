<?php
declare(strict_types=1);
use Babanoell\Loans\Database\Migration;

return new class implements Migration {
    public function version(): string { return '202609290002'; }
    public function name(): string { return 'core_completion_request_origin_guarantor_credit'; }

    public function up(PDO $pdo): void
    {
        $columnExists=static function(string $table,string $column)use($pdo):bool{
            $s=$pdo->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=:table_name AND column_name=:column_name');
            $s->execute([':table_name'=>$table,':column_name'=>$column]);return(int)$s->fetchColumn()>0;
        };
        $indexExists=static function(string $table,string $index)use($pdo):bool{
            $s=$pdo->prepare('SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name=:table_name AND index_name=:index_name');
            $s->execute([':table_name'=>$table,':index_name'=>$index]);return(int)$s->fetchColumn()>0;
        };
        $addColumn=static function(string $table,string $column,string $definition)use($pdo,$columnExists):void{
            if(!$columnExists($table,$column))$pdo->exec('ALTER TABLE `'.$table.'` ADD COLUMN `'.$column.'` '.$definition);
        };
        $addIndex=static function(string $table,string $index,string $columns)use($pdo,$indexExists):void{
            if(!$indexExists($table,$index))$pdo->exec('ALTER TABLE `'.$table.'` ADD KEY `'.$index.'` '.$columns);
        };

        // Canonical immutable origin. Existing "panel" rows are classified from their ownership fields.
        $pdo->exec("UPDATE loan_requests SET request_origin=CASE
            WHEN request_origin IN ('merchant','acceptor') THEN 'merchant'
            WHEN request_origin='applicant' THEN 'applicant'
            WHEN request_origin IN ('admin','migration','api') THEN request_origin
            WHEN applicant_user_id IS NOT NULL AND applicant_user_id<>'' AND created_by_user_id=applicant_user_id THEN 'applicant'
            WHEN acceptor_user_id IS NOT NULL AND acceptor_user_id<>'' AND created_by_user_id=acceptor_user_id THEN 'merchant'
            WHEN acceptor_user_id IS NOT NULL AND acceptor_user_id<>'' AND (applicant_user_id IS NULL OR applicant_user_id='') THEN 'merchant'
            ELSE 'admin' END");
        $pdo->exec("ALTER TABLE loan_requests MODIFY COLUMN request_origin VARCHAR(30) NOT NULL DEFAULT 'admin'");

        // Credit-validation summary lives on the case; raw/provider history remains in credit_checks.
        $addColumn('loan_requests','credit_validation_status',"VARCHAR(24) NOT NULL DEFAULT 'not_required'");
        $addColumn('loan_requests','credit_validation_provider','VARCHAR(64) NULL');
        $addColumn('loan_requests','credit_validation_reference','VARCHAR(255) NULL');
        $addColumn('loan_requests','credit_validation_verified_at','DATETIME(6) NULL');
        $addColumn('loan_requests','credit_validation_check_id','CHAR(26) NULL');
        $addIndex('loan_requests','idx_loan_requests_credit_validation','(credit_validation_status, updated_at)');
        $addIndex('credit_checks','idx_credit_checks_status','(request_id, status, completed_at)');

        // Guarantors become stable entities. Soft deactivation prevents document links from becoming orphaned.
        $addColumn('loan_request_guarantors','position_no','TINYINT UNSIGNED NULL');
        $addColumn('loan_request_guarantors','status',"VARCHAR(16) NOT NULL DEFAULT 'active'");
        $addColumn('loan_request_guarantors','removed_at','DATETIME(6) NULL');
        $addColumn('loan_request_guarantors','updated_at','DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6)');

        $q=$pdo->query("SELECT id,request_id FROM loan_request_guarantors ORDER BY request_id ASC,created_at ASC,id ASC");
        $position=[];$u=$pdo->prepare('UPDATE loan_request_guarantors SET position_no=:position,status=\'active\',removed_at=NULL WHERE id=:id');
        foreach($q->fetchAll(PDO::FETCH_ASSOC) as$row){
            $requestId=(string)$row['request_id'];$position[$requestId]=($position[$requestId]??0)+1;
            $u->execute([':position'=>$position[$requestId],':id'=>$row['id']]);
        }
        $pdo->exec('ALTER TABLE loan_request_guarantors MODIFY COLUMN position_no TINYINT UNSIGNED NOT NULL DEFAULT 1');
        $addIndex('loan_request_guarantors','idx_guarantors_active_position','(request_id,status,position_no)');
    }
};
