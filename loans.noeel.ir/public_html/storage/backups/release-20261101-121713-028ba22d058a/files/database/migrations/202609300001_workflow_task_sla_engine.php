<?php
declare(strict_types=1);
use Babanoell\Loans\Database\Migration;

return new class implements Migration {
    public function version(): string { return '202609300001'; }
    public function name(): string { return 'workflow_task_sla_engine'; }

    public function up(PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS workflow_sla_policies (
    id CHAR(26) NOT NULL,
    code VARCHAR(64) NOT NULL,
    name VARCHAR(190) NOT NULL,
    task_code VARCHAR(64) NOT NULL,
    queue_code VARCHAR(64) NOT NULL,
    required_permission VARCHAR(128) NOT NULL,
    due_minutes INT UNSIGNED NOT NULL,
    warning_minutes INT UNSIGNED NOT NULL DEFAULT 0,
    escalation_minutes INT UNSIGNED NOT NULL DEFAULT 0,
    escalation_queue_code VARCHAR(64) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    UNIQUE KEY uq_workflow_sla_code (code),
    KEY idx_workflow_sla_task (task_code, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS workflow_tasks (
    id CHAR(26) NOT NULL,
    request_id CHAR(26) NOT NULL,
    task_code VARCHAR(64) NOT NULL,
    title VARCHAR(190) NOT NULL,
    description TEXT NULL,
    queue_code VARCHAR(64) NOT NULL,
    required_permission VARCHAR(128) NOT NULL,
    assigned_user_id CHAR(26) NULL,
    assigned_by_user_id CHAR(26) NULL,
    status VARCHAR(24) NOT NULL DEFAULT 'open',
    priority VARCHAR(16) NOT NULL DEFAULT 'normal',
    active_key VARCHAR(128) NULL,
    sla_policy_code VARCHAR(64) NULL,
    due_at DATETIME(6) NULL,
    warning_at DATETIME(6) NULL,
    escalation_at DATETIME(6) NULL,
    last_sla_event VARCHAR(32) NULL,
    last_sla_event_at DATETIME(6) NULL,
    source VARCHAR(32) NOT NULL DEFAULT 'workflow',
    source_event VARCHAR(100) NULL,
    version_no INT UNSIGNED NOT NULL DEFAULT 1,
    created_by_user_id CHAR(26) NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    started_at DATETIME(6) NULL,
    completed_at DATETIME(6) NULL,
    cancelled_at DATETIME(6) NULL,
    completion_note VARCHAR(2000) NULL,
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    UNIQUE KEY uq_workflow_tasks_active (request_id, active_key),
    KEY idx_workflow_tasks_assignee (assigned_user_id, status, due_at),
    KEY idx_workflow_tasks_queue (queue_code, status, due_at),
    KEY idx_workflow_tasks_request (request_id, created_at),
    KEY idx_workflow_tasks_sla (status, due_at, escalation_at),
    CONSTRAINT fk_workflow_tasks_request FOREIGN KEY (request_id) REFERENCES loan_requests(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS workflow_task_history (
    id CHAR(26) NOT NULL,
    task_id CHAR(26) NOT NULL,
    request_id CHAR(26) NOT NULL,
    action VARCHAR(64) NOT NULL,
    from_status VARCHAR(24) NULL,
    to_status VARCHAR(24) NULL,
    actor_user_id CHAR(26) NULL,
    assigned_user_id CHAR(26) NULL,
    note VARCHAR(2000) NULL,
    metadata_json JSON NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    KEY idx_workflow_task_history_task (task_id, created_at),
    KEY idx_workflow_task_history_request (request_id, created_at),
    KEY idx_workflow_task_history_action (action, created_at),
    CONSTRAINT fk_workflow_task_history_task FOREIGN KEY (task_id) REFERENCES workflow_tasks(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_workflow_task_history_request FOREIGN KEY (request_id) REFERENCES loan_requests(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $policies = [
            ['operations_review','بررسی اولیه عملیات','operations_review','operations','loans.requests.operations',1440,180,60,'operations'],
            ['bank_review','بررسی کارشناس بانک','bank_review','bank','loans.requests.bank_review',1440,180,60,'operations'],
            ['document_followup','پیگیری تکمیل مدارک','document_followup','operations','loans.requests.document_receipt',2880,360,120,'operations'],
            ['document_verification','تأیید نهایی مدارک','document_verification','operations','loans.requests.document_receipt',1440,180,60,'operations'],
            ['grant_registration','ثبت نهایی اعطا','grant_registration','operations','loans.requests.grant',480,120,60,'operations'],
            ['bank_settlement','ثبت تسویه بانک با بابانوئل','bank_settlement','finance','loans.requests.bank_settlement',2880,360,120,'finance'],
            ['finance_review','بررسی مالی پرونده','finance_review','finance','loans.requests.finance',1440,180,60,'finance'],
        ];
        $s=$pdo->prepare('INSERT INTO workflow_sla_policies (id,code,name,task_code,queue_code,required_permission,due_minutes,warning_minutes,escalation_minutes,escalation_queue_code,is_active) VALUES (:id,:code,:name,:task_code,:queue_code,:permission,:due,:warning,:escalation,:escalation_queue,1) ON DUPLICATE KEY UPDATE name=VALUES(name),task_code=VALUES(task_code),queue_code=VALUES(queue_code),required_permission=VALUES(required_permission),due_minutes=VALUES(due_minutes),warning_minutes=VALUES(warning_minutes),escalation_minutes=VALUES(escalation_minutes),escalation_queue_code=VALUES(escalation_queue_code),is_active=1');
        foreach($policies as $p){
            $id=substr(hash('sha256','noel-sla-'.$p[0]),0,26);
            $s->execute([':id'=>$id,':code'=>$p[0],':name'=>$p[1],':task_code'=>$p[2],':queue_code'=>$p[3],':permission'=>$p[4],':due'=>$p[5],':warning'=>$p[6],':escalation'=>$p[7],':escalation_queue'=>$p[8]]);
        }

        // Backfill one active workflow task per currently actionable request. IDs are deterministic so the migration is idempotent.
        $backfills = [
            ['under_review','operations_review','operations','loans.requests.operations','operations_review','assigned_operations_user_id'],
            ['expert_approved','document_followup','operations','loans.requests.document_receipt','document_followup','assigned_operations_user_id'],
            ['docs_completion','document_verification','operations','loans.requests.document_receipt','document_verification','assigned_operations_user_id'],
            ['docs_verified','grant_registration','operations','loans.requests.grant','grant_registration','assigned_operations_user_id'],
            ['granted','bank_settlement','finance','loans.requests.bank_settlement','bank_settlement',null],
            ['granted','finance_review','finance','loans.requests.finance','finance_review',null],
        ];
        foreach($backfills as $b){
            [$status,$taskCode,$queue,$permission,$policyCode,$assigneeColumn]=$b;
            $policyStmt=$pdo->prepare('SELECT due_minutes,warning_minutes,escalation_minutes FROM workflow_sla_policies WHERE code=:code LIMIT 1');
            $policyStmt->execute([':code'=>$policyCode]);$policy=$policyStmt->fetch(PDO::FETCH_ASSOC)?:['due_minutes'=>1440,'warning_minutes'=>180,'escalation_minutes'=>60];
            $due=(int)$policy['due_minutes'];$warning=(int)$policy['warning_minutes'];$escalation=(int)$policy['escalation_minutes'];
            $assignee=$assigneeColumn!==null?'r.`'.$assigneeColumn.'`':'NULL';
            $title=[
                'operations_review'=>'بررسی اولیه پرونده','document_followup'=>'پیگیری تکمیل مدارک','document_verification'=>'تأیید نهایی مدارک','grant_registration'=>'ثبت نهایی اعطای تسهیلات','bank_settlement'=>'ثبت تسویه بانک با بابانوئل','finance_review'=>'بررسی مالی پرونده'
            ][$taskCode]??$taskCode;
            $sql="INSERT IGNORE INTO workflow_tasks (id,request_id,task_code,title,queue_code,required_permission,assigned_user_id,status,priority,active_key,sla_policy_code,due_at,warning_at,escalation_at,source,source_event,created_by_user_id) SELECT LEFT(SHA2(CONCAT('workflow-task:',r.id,':{$taskCode}'),256),26),r.id,'{$taskCode}',".$pdo->quote($title).",'{$queue}','{$permission}',{$assignee},'open','normal',CONCAT('workflow:','{$taskCode}'),'{$policyCode}',DATE_ADD(NOW(6),INTERVAL {$due} MINUTE),DATE_ADD(DATE_ADD(NOW(6),INTERVAL {$due} MINUTE),INTERVAL -{$warning} MINUTE),DATE_ADD(DATE_ADD(NOW(6),INTERVAL {$due} MINUTE),INTERVAL {$escalation} MINUTE),'workflow','migration.backfill',NULL FROM loan_requests r WHERE r.status='{$status}'";
            $pdo->exec($sql);
        }
        // Bank-review tasks are conditional on an assigned bank expert.
        $policyStmt=$pdo->query("SELECT due_minutes,warning_minutes,escalation_minutes FROM workflow_sla_policies WHERE code='bank_review' LIMIT 1");$policy=$policyStmt->fetch(PDO::FETCH_ASSOC)?:['due_minutes'=>1440,'warning_minutes'=>180,'escalation_minutes'=>60];
        $due=(int)$policy['due_minutes'];$warning=(int)$policy['warning_minutes'];$escalation=(int)$policy['escalation_minutes'];
        $pdo->exec("INSERT IGNORE INTO workflow_tasks (id,request_id,task_code,title,queue_code,required_permission,assigned_user_id,status,priority,active_key,sla_policy_code,due_at,warning_at,escalation_at,source,source_event,created_by_user_id) SELECT LEFT(SHA2(CONCAT('workflow-task:',r.id,':bank_review'),256),26),r.id,'bank_review','بررسی کارشناس بانک','bank','loans.requests.bank_review',r.assigned_bank_user_id,'open','normal','workflow:bank_review','bank_review',DATE_ADD(NOW(6),INTERVAL {$due} MINUTE),DATE_ADD(DATE_ADD(NOW(6),INTERVAL {$due} MINUTE),INTERVAL -{$warning} MINUTE),DATE_ADD(DATE_ADD(NOW(6),INTERVAL {$due} MINUTE),INTERVAL {$escalation} MINUTE),'workflow','migration.backfill',NULL FROM loan_requests r WHERE r.status='under_review' AND r.assigned_bank_user_id IS NOT NULL AND r.assigned_bank_user_id<>''");
    }
};
