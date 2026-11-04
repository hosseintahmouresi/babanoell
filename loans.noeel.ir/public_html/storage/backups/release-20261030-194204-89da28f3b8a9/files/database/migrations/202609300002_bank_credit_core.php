<?php
declare(strict_types=1);
use Babanoell\Loans\Database\Migration;

return new class implements Migration {
    public function version(): string { return '202609300002'; }
    public function name(): string { return 'bank_credit_core'; }

    public function up(PDO $pdo): void
    {
        $columnExists=static function(string $table,string $column)use($pdo):bool{
            $s=$pdo->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=:t AND column_name=:c');
            $s->execute([':t'=>$table,':c'=>$column]);return (int)$s->fetchColumn()>0;
        };
        $indexExists=static function(string $table,string $index)use($pdo):bool{
            $s=$pdo->prepare('SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name=:t AND index_name=:i');
            $s->execute([':t'=>$table,':i'=>$index]);return (int)$s->fetchColumn()>0;
        };
        $addColumn=static function(string $table,string $column,string $definition)use($pdo,$columnExists):void{
            if(!$columnExists($table,$column))$pdo->exec('ALTER TABLE `'.$table.'` ADD COLUMN `'.$column.'` '.$definition);
        };
        $addIndex=static function(string $table,string $index,string $columns)use($pdo,$indexExists):void{
            if(!$indexExists($table,$index))$pdo->exec('ALTER TABLE `'.$table.'` ADD KEY `'.$index.'` '.$columns);
        };

        $addColumn('loan_requests','identity_verification_status',"VARCHAR(24) NOT NULL DEFAULT 'not_required'");
        $addColumn('loan_requests','identity_verification_provider','VARCHAR(64) NULL');
        $addColumn('loan_requests','identity_verification_reference','VARCHAR(255) NULL');
        $addColumn('loan_requests','identity_verification_verified_at','DATETIME(6) NULL');
        $addColumn('loan_requests','identity_verification_request_id','CHAR(26) NULL');
        $addIndex('loan_requests','idx_loan_requests_identity_verification','(identity_verification_status, updated_at)');

        $addColumn('credit_checks','verification_request_id','CHAR(26) NULL');
        $addColumn('credit_checks','proof_hash','CHAR(64) NULL');
        $addColumn('credit_checks','expires_at','DATETIME(6) NULL');
        $addIndex('credit_checks','idx_credit_checks_verification_request','(verification_request_id)');

        $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS verification_requests (
    id CHAR(26) NOT NULL,
    request_id CHAR(26) NOT NULL,
    verification_type VARCHAR(32) NOT NULL,
    provider VARCHAR(64) NOT NULL,
    status VARCHAR(24) NOT NULL DEFAULT 'queued',
    idempotency_key VARCHAR(128) NOT NULL,
    subject_fingerprint CHAR(64) NOT NULL,
    request_hash CHAR(64) NOT NULL,
    provider_reference VARCHAR(255) NULL,
    response_hash CHAR(64) NULL,
    score INT NULL,
    risk VARCHAR(64) NULL,
    metadata_json JSON NULL,
    requested_by_user_id CHAR(26) NOT NULL,
    completed_by_user_id CHAR(26) NULL,
    requested_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    completed_at DATETIME(6) NULL,
    expires_at DATETIME(6) NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    UNIQUE KEY uq_verification_idempotency (request_id, verification_type, idempotency_key),
    KEY idx_verification_request (request_id, verification_type, requested_at),
    KEY idx_verification_delivery (status, provider, requested_at),
    CONSTRAINT fk_verification_request_case FOREIGN KEY (request_id) REFERENCES loan_requests(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        // Existing cases did not have a Shahkar policy snapshot, therefore they remain not_required.
        $pdo->exec("UPDATE loan_requests SET identity_verification_status='not_required' WHERE identity_verification_status IS NULL OR identity_verification_status=''");

        // Dedicated SLA policies for bank identity/credit verification tasks.
        $policies=[
            ['identity_verification','احراز تطبیق هویت شاهکار','identity_verification','bank','loans.requests.bank_review',720,120,60,'bank'],
            ['credit_validation','اعتبارسنجی اعتباری','credit_validation','bank','loans.requests.bank_review',1440,180,60,'bank'],
        ];
        $s=$pdo->prepare('INSERT INTO workflow_sla_policies (id,code,name,task_code,queue_code,required_permission,due_minutes,warning_minutes,escalation_minutes,escalation_queue_code,is_active) VALUES (:id,:code,:name,:task_code,:queue_code,:permission,:due,:warning,:escalation,:queue,1) ON DUPLICATE KEY UPDATE name=VALUES(name),task_code=VALUES(task_code),queue_code=VALUES(queue_code),required_permission=VALUES(required_permission),due_minutes=VALUES(due_minutes),warning_minutes=VALUES(warning_minutes),escalation_minutes=VALUES(escalation_minutes),escalation_queue_code=VALUES(escalation_queue_code),is_active=1');
        foreach($policies as$p){$s->execute([':id'=>substr(hash('sha256','noel-sla-'.$p[0]),0,26),':code'=>$p[0],':name'=>$p[1],':task_code'=>$p[2],':queue_code'=>$p[3],':permission'=>$p[4],':due'=>$p[5],':warning'=>$p[6],':escalation'=>$p[7],':queue'=>$p[8]]);}
    }
};
