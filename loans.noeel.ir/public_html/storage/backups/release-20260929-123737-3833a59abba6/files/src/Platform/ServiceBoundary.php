<?php
declare(strict_types=1);
namespace Babanoell\Loans\Platform;
final class ServiceBoundary
{
    public const CONTRACT_VERSION='2026-09-29.phase2a';
    /** @return array<string,mixed> */
    public static function describe(): array
    {
        return [
            'service'=>'loans',
            'contract_version'=>self::CONTRACT_VERSION,
            'owns'=>['plans','plan_versions','calculations','loan_requests','guarantors','request_workflow','grant'],
            'planned_services'=>['partners','finance','integrations'],
            'grandfathered_out_of_boundary_tables'=>['merchants','merchant_user_memberships','merchant_plan_permissions','financial_receipts','financier_payment_history','bank_settlement_history','merchant_settlement_history','settlement_cycles','settlement_cycle_items','settlement_cycle_receipts','cooperation_requests','cooperation_documents','notifications','integration_outbox'],
            'forbidden_future_table_prefixes'=>['merchant_','settlement_','financial_','cooperation_','integration_'],
            'rules'=>[
                'database_ownership'=>'single_service',
                'cross_service_access'=>'http_api_only',
                'api_version'=>'v1',
                'request_id'=>'X-Request-Id',
                'idempotency'=>'Idempotency-Key',
                'service_identity'=>'X-Noel-Service + optional HMAC v1',
            ],
        ];
    }
}
