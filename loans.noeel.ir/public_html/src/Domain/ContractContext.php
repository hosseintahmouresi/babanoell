<?php
declare(strict_types=1);
namespace Babanoell\Loans\Domain;

final class ContractContext
{
    public static function fromLoan(array $loan, ?\DateTimeImmutable $now=null): array
    {
        $rules=is_array($loan['plan_rules']??null)?$loan['plan_rules']:[];
        $policy=['mode'=>(string)($rules['contract_mode']??'physical'),
            'loan_application_enabled'=>CaseReadiness::enabled($rules['loan_application_contract_enabled']??true),
            'facility_agreement_enabled'=>CaseReadiness::enabled($rules['facility_agreement_contract_enabled']??true),
            'loan_application_template_code'=>(string)($rules['loan_application_template_code']??'loan_application_default'),
            'facility_agreement_template_code'=>(string)($rules['facility_agreement_template_code']??'facility_agreement_default')];
        return ReferenceContractContext::expand($loan,$now)+['request_id'=>(string)$loan['id'],'request_no'=>(string)($loan['request_no']??$loan['id']),
            'applicant_user_id'=>(string)($loan['applicant_user_id']??''),'signer_name'=>(string)($loan['full_name']??''),
            'mobile'=>(string)($loan['mobile_number']??''),'requested_amount'=>(string)($loan['requested_amount']??''),
            'facility_amount'=>(string)($loan['facility_amount']??''),'plan_id'=>(string)($loan['plan_id']??''),
            'plan_name'=>(string)($loan['plan_name']??''),'plan_version'=>(int)($loan['plan_version']??0),'contract_policy'=>$policy];
    }
}
