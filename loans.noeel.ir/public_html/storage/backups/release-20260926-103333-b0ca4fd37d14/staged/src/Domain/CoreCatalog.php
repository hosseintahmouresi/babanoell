<?php
declare(strict_types=1);
namespace Babanoell\Loans\Domain;

final class CoreCatalog
{
    /** @return array<string,mixed> */
    public static function describe(): array
    {
        return [
            'version'=>'1.7.0',
            'architecture'=>'microservice',
            'modules'=>[
                'identity_scope'=>['organizations','branches','memberships','external_identities'],
                'merchants'=>['merchants','merchant_user_memberships','merchant_plan_permissions'],
                'plans'=>['installment_plans','installment_plan_versions','calculation_runs'],
                'requests'=>['loan_requests','loan_request_status_history','loan_request_events','loan_request_guarantors'],
                'workflow'=>['loan_request_assignments','request_tags'],
                'communications'=>['request_messages','request_comments','notifications'],
                'bank_credit'=>['credit_checks','bank_reviews','grant_records'],
                'finance'=>['financial_receipts','settlement_cycles','settlement_cycle_items','settlement_cycle_receipts'],
                'cooperation'=>['cooperation_requests','cooperation_documents'],
                'integrations'=>['integration_outbox'],
                'documents'=>'documents-service',
            ],
            'canonical_request_statuses'=>(new StatusMachine())->labels(),
            'legacy_request_status_map'=>LegacyStatusMap::requestStatuses(),
            'legacy_settlement_status_map'=>LegacyStatusMap::settlementStatuses(),
            'required_document_codes'=>array_keys(RequiredDocumentCatalog::all()),
        ];
    }
}
