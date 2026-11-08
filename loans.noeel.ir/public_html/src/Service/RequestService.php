<?php
declare(strict_types=1);
namespace Babanoell\Loans\Service;

use Babanoell\Loans\Domain\InputValidator;
use Babanoell\Loans\Domain\PlanRules;
use Babanoell\Loans\Domain\RequestOrigin;
use Babanoell\Loans\Domain\ScheduleGenerator;
use Babanoell\Loans\Repository\PdoLoanRepository;
use InvalidArgumentException;

final class RequestService
{
    public function __construct(private readonly PdoLoanRepository $repo,private readonly CalculationService $calculations,private readonly ScheduleGenerator $schedule){}

    public function create(string $actor,array $input,string $idempotencyKey='',string $origin=RequestOrigin::ADMIN,?array $merchantContext=null): array
    {
        if($idempotencyKey!==''&&($old=$this->repo->findRequestByIdempotency($actor,$idempotencyKey))!==null)return$old;
        $origin=RequestOrigin::normalize($origin);
        $submitted=filter_var($input['submit']??false,FILTER_VALIDATE_BOOL);
        $d=InputValidator::request($input,$submitted);
        $d['request_origin']=$origin;
        if($origin===RequestOrigin::APPLICANT){$d['applicant_user_id']=$actor;$d['acceptor_user_id']='';}
        if($origin===RequestOrigin::MERCHANT){$d['acceptor_user_id']=$actor;}
        $planId=trim((string)($input['plan_id']??''));if($planId==='')throw new InvalidArgumentException('انتخاب طرح تسهیلات الزامی است.');
        $plan=$this->repo->getPlan($planId);if($plan===null)throw new InvalidArgumentException('طرح تسهیلات پیدا نشد.');if($plan['status']!=='active')throw new InvalidArgumentException('این طرح در حال حاضر فعال نیست.');
        // Like the reference form, required credit evidence precedes final submission.
        // Save the entered information as a private draft so the OTP/report flow has a stable owner and case.
        if(\Babanoell\Loans\Domain\CreditValidationPolicy::required(['plan_rules'=>$plan['rules']??[]]))$submitted=false;

        $context=trim((string)($input['plan_context']??''));
        if($origin===RequestOrigin::APPLICANT)$context='applicant_request';
        elseif($origin===RequestOrigin::MERCHANT)$context='merchant_request';
        elseif($context==='')$context=trim((string)($d['acceptor_user_id']??''))!==''?'merchant_request':'applicant_request';
        if(!isset(PlanRules::VISIBILITY_CONTEXTS[$context]))throw new InvalidArgumentException('محل استفاده از طرح معتبر نیست.');
        if(!PlanRules::visibleIn($plan,$context))throw new InvalidArgumentException('این طرح در مسیر انتخاب‌شده قابل استفاده نیست.');
        PlanRules::requiredDocuments($plan,(string)($d['occupation']??''));
        $merchantId=trim((string)($input['merchant_id']??''));
        if($merchantId!==''){
            if(!preg_match('/^[a-f0-9]{26}$/',$merchantId))throw new InvalidArgumentException('شناسه پذیرنده معتبر نیست.');
            if($merchantContext!==null){
                if(($merchantContext['merchant_id']??'')!==$merchantId||($merchantContext['plan_id']??'')!==$planId)throw new InvalidArgumentException('مرجع پذیرنده با درخواست مطابقت ندارد.');
                if($origin===RequestOrigin::APPLICANT&&!empty($merchantContext['owner_plan_excluded']))throw new InvalidArgumentException('این طرح برای پذیرندهٔ انتخاب‌شده غیرفعال است.');
                $permission=$merchantContext['permission']??null;$allowed=is_array($permission)?($permission['status']??'')==='active':($plan['rules']['merchant_access_mode']??'all')!=='allowlist';
            }else $allowed=$this->repo->merchantCanUsePlan($merchantId,$plan);
            if(!$allowed)throw new InvalidArgumentException('این طرح برای پذیرنده انتخاب‌شده فعال نیست.');
            if($origin===RequestOrigin::APPLICANT&&$merchantContext!==null)$d['acceptor_user_id']=(string)$merchantContext['owner_user_id'];
            $d['merchant_id']=$merchantId;
        }elseif((string)($plan['rules']['merchant_access_mode']??'all')==='allowlist'&&$context==='merchant_request'){
            throw new InvalidArgumentException('برای این طرح انتخاب پذیرنده مجاز الزامی است.');
        }

        if($origin===RequestOrigin::APPLICANT){
            $mode=(string)($d['acceptor_assignment_mode']??'');
            if($mode==='applicant_selected_acceptor'&&$merchantId==='')throw new InvalidArgumentException('برای روش انتخاب مستقیم، پذیرنده باید مشخص شود.');
            if($mode==='open_acceptor_marketplace'&&$merchantId!=='')throw new InvalidArgumentException('در حالت بازار پذیرندگان نباید پذیرنده از قبل انتخاب شده باشد.');
        }
        $this->validateEligibility($plan,$d);
        $this->calculations->validateInput($plan,$d['requested_amount'],$d['installment_count'],$d['installment_interval']);
        if($origin===RequestOrigin::MERCHANT&&$merchantContext!==null){$trusted=$merchantContext['actor_commission_percent']??null;if($trusted!==null&&(!is_numeric($trusted)||(float)$trusted<0||(float)$trusted>100))throw new InvalidArgumentException('کمیسیون پذیرنده از مرجع قابل تأیید نیست.');$d['commission_override']=$trusted===null?null:(float)$trusted;}
        $calculated=$this->calculations->calculate($actor,$planId,$d['requested_amount'],$d['installment_count'],$d['installment_interval'],$d['commission_override'],true);
        $schedule=[];if($d['first_due_date_jalali']!=='')$schedule=$this->schedule->generate((string)$plan['type'],$calculated['result'],$d['installment_interval'],$d['first_due_date_jalali']);
        $created=$this->repo->insertRequest($actor,$d,$plan,$calculated['calculation'],$schedule,$submitted,substr($idempotencyKey,0,128));
        if($origin===RequestOrigin::APPLICANT)$created=$this->repo->configureApplicantRouting((string)$created['id'],$d,$actor);
        return$created;
    }

    private function validateEligibility(array $plan,array $d): void
    {
        $e=is_array($plan['eligibility']??null)?$plan['eligibility']:[];
        $occupation=trim((string)($d['occupation']??''));
        if(isset($e['allowed_occupations'])&&is_array($e['allowed_occupations'])&&!in_array($occupation,$e['allowed_occupations'],true))throw new InvalidArgumentException('شغل انتخاب‌شده مشمول این طرح نیست.');
        if(isset($e['blocked_occupations'])&&is_array($e['blocked_occupations'])&&in_array($occupation,$e['blocked_occupations'],true))throw new InvalidArgumentException('شغل انتخاب‌شده در این طرح مجاز نیست.');
    }
}
