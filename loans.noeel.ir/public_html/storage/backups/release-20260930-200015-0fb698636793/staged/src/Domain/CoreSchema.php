<?php
declare(strict_types=1);
namespace Babanoell\Loans\Domain;
use PDO;

final class CoreSchema
{
    private const TABLES = [
        'installment_plans','installment_plan_versions','calculation_runs','loan_requests','loan_request_status_history','loan_request_events','installment_schedule','loan_request_guarantors',
        'merchants','merchant_user_memberships','merchant_plan_permissions','loan_request_assignments','request_messages','request_comments','request_tags','credit_checks','bank_reviews','grant_records',
        'financial_receipts','settlement_cycles','settlement_cycle_items','settlement_cycle_receipts','bank_settlement_history','merchant_settlement_history','financier_payment_history','request_message_reads','cooperation_requests','cooperation_documents','notifications','integration_outbox','workflow_sla_policies','workflow_tasks','workflow_task_history'
    ];

    /** @return array{ready:bool,missing:list<string>} */
    public static function status(PDO $pdo): array
    {
        $s=$pdo->prepare('SELECT table_name FROM information_schema.tables WHERE table_schema=DATABASE()');
        $s->execute();
        $available=[];
        foreach($s->fetchAll(PDO::FETCH_COLUMN) as $table) $available[(string)$table]=true;
        $missing=[];
        foreach(self::TABLES as $table) if(!isset($available[$table])) $missing[]=$table;
        return ['ready'=>$missing===[],'missing'=>$missing];
    }
}
