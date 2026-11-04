<?php
declare(strict_types=1);
namespace Babanoell\Loans\Service;

use Babanoell\Loans\Domain\InputValidator;
use Babanoell\Loans\Domain\PlanRules;
use Babanoell\Loans\Domain\ScheduleGenerator;
use Babanoell\Loans\Repository\PdoLoanRepository;
use InvalidArgumentException;

final class RequestService
{
    public function __construct(private readonly PdoLoanRepository $repo,private readonly CalculationService $calculations,private readonly ScheduleGenerator $schedule){}

    public function create(string $actor,array $input,string $idempotencyKey=''): array
    {
        if($idempotencyKey!==''&&($old=$this->repo->findRequestByIdempotency($actor,$idempotencyKey))!==null)return$old;
        $submitted=filter_var($input['submit']??false,FILTER_VALIDATE_BOOL);
        $d=InputValidator::request($input,$submitted);
        $planId=trim((string)($input['plan_id']??''));if($planId==='')throw new InvalidArgumentException('انتخاب طرح تسهیلات الزامی است.');
        $plan=$this->repo->getPlan($planId);if($plan===null)throw new InvalidArgumentException('طرح تسهیلات پیدا نشد.');if($plan['status']!=='active')throw new InvalidArgumentException('این طرح در حال حاضر فعال نیست.');

        $context=trim((string)($input['plan_context']??''));
        if($context==='')$context=trim((string)($d['acceptor_user_id']??''))!==''?'merchant_request':'applicant_request';
        if(!isset(PlanRules::VISIBILITY_CONTEXTS[$context]))throw new InvalidArgumentException('محل استفاده از طرح معتبر نیست.');
        if(!PlanRules::visibleIn($plan,$context))throw new InvalidArgumentException('این طرح در مسیر انتخاب‌شده قابل استفاده نیست.');
        PlanRules::requiredDocuments($plan,(string)($d['occupation']??''));
        $merchantId=trim((string)($input['merchant_id']??''));
        if($merchantId!==''){
            if(!preg_match('/^[a-f0-9]{26}$/',$merchantId))throw new InvalidArgumentException('شناسه پذیرنده معتبر نیست.');
            if(!$this->repo->merchantCanUsePlan($merchantId,$plan))throw new InvalidArgumentException('این طرح برای پذیرنده انتخاب‌شده فعال نیست.');
            $d['merchant_id']=$merchantId;
        }elseif((string)($plan['rules']['merchant_access_mode']??'all')==='allowlist'&&$context==='merchant_request'){
            throw new InvalidArgumentException('برای این طرح انتخاب پذیرنده مجاز الزامی است.');
        }

        $this->validateEligibility($plan,$d);
        $this->calculations->validateInput($plan,$d['requested_amount'],$d['installment_count'],$d['installment_interval']);
        $calculated=$this->calculations->calculate($actor,$planId,$d['requested_amount'],$d['installment_count'],$d['installment_interval'],$d['commission_override'],true);
        $schedule=[];if($d['first_due_date_jalali']!=='')$schedule=$this->schedule->generate((string)$plan['type'],$calculated['result'],$d['installment_interval'],$d['first_due_date_jalali']);
        return$this->repo->insertRequest($actor,$d,$plan,$calculated['calculation'],$schedule,$submitted,substr($idempotencyKey,0,128));
    }

    private function validateEligibility(array $plan,array $d): void
    {
        $e=is_array($plan['eligibility']??null)?$plan['eligibility']:[];
        $occupation=trim((string)($d['occupation']??''));
        if(isset($e['allowed_occupations'])&&is_array($e['allowed_occupations'])&&!in_array($occupation,$e['allowed_occupations'],true))throw new InvalidArgumentException('شغل انتخاب‌شده مشمول این طرح نیست.');
        if(isset($e['blocked_occupations'])&&is_array($e['blocked_occupations'])&&in_array($occupation,$e['blocked_occupations'],true))throw new InvalidArgumentException('شغل انتخاب‌شده در این طرح مجاز نیست.');
    }
}
