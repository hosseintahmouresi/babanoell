<?php
declare(strict_types=1);
namespace Babanoell\Loans\Service;

use Babanoell\Loans\Domain\PlanRules;
use Babanoell\Loans\Domain\PlanSettingsValidator;
use Babanoell\Loans\Domain\RequiredDocumentCatalog;
use Babanoell\Loans\Repository\PdoLoanRepository;
use InvalidArgumentException;

final class PlanService
{
    public function __construct(private readonly PdoLoanRepository $repo,private readonly PlanSettingsValidator $validator){}

    public function create(string $actor,array $input): array
    {
        $code=strtoupper(trim((string)($input['code']??'')));
        $name=trim((string)($input['name']??''));
        $type=strtolower(trim((string)($input['type']??'')));
        if($code===''||!preg_match('/^[A-Z0-9][A-Z0-9_-]{2,79}$/',$code))throw new InvalidArgumentException('کد طرح باید ۳ تا ۸۰ نویسه و شامل حروف انگلیسی، عدد، خط تیره یا زیرخط باشد.');
        if($name==='')throw new InvalidArgumentException('نام طرح الزامی است.');
        $settings=$this->validator->normalized(is_array($input['settings']??null)?$input['settings']:[]);
        $errors=$this->validator->validate($type,$settings);
        $rules=PlanRules::normalize($input,$settings);
        $errors=array_merge($errors,PlanRules::validate($rules));
        if($errors!==[])throw new InvalidArgumentException(implode(' ',array_unique($errors)));
        $documents=RequiredDocumentCatalog::normalize(is_array($input['required_documents']??null)?$input['required_documents']:[]);
        return $this->repo->createPlan(
            $actor,$code,$name,$type,$settings,
            is_array($input['display_fields']??null)?$input['display_fields']:[],
            $documents,trim((string)($input['conditions']??'')),$rules,
            is_array($rules['eligibility']??null)?$rules['eligibility']:[],
            max(0,(int)($input['display_order']??0))
        );
    }

    public function revise(string $id,string $actor,array $input): array
    {
        $old=$this->repo->getPlan($id);if($old===null)throw new InvalidArgumentException('طرح پیدا نشد.');
        $name=trim((string)($input['name']??$old['name']));
        $requestedType=strtolower(trim((string)($input['type']??$old['type'])));
        if($requestedType!==(string)$old['type'])throw new InvalidArgumentException('نوع طرح بعد از ایجاد قابل تغییر نیست. برای نوع محاسبه متفاوت، یک طرح جدید بسازید.');
        $type=(string)$old['type'];
        $settings=$this->validator->normalized(is_array($input['settings']??null)?array_replace($old['settings'],$input['settings']):$old['settings']);
        $errors=$this->validator->validate($type,$settings);if($name==='')$errors[]='نام طرح الزامی است.';
        $ruleInput=$input;
        $ruleInput['rules']=array_replace(is_array($old['rules']??null)?$old['rules']:[],is_array($input['rules']??null)?$input['rules']:[]);
        if(!isset($ruleInput['eligibility'])&&is_array($old['eligibility']??null))$ruleInput['eligibility']=is_array($input['rules']['eligibility']??null)?$input['rules']['eligibility']:$old['eligibility'];
        $rules=PlanRules::normalize($ruleInput,$settings);$errors=array_merge($errors,PlanRules::validate($rules));
        if($errors!==[])throw new InvalidArgumentException(implode(' ',array_unique($errors)));
        $documents=RequiredDocumentCatalog::normalize(is_array($input['required_documents']??null)?$input['required_documents']:(is_array($old['required_documents']??null)?$old['required_documents']:[]));
        $plan=$this->repo->revisePlan($id,$actor,$name,$type,$settings,is_array($input['display_fields']??null)?$input['display_fields']:$old['display_fields'],$documents,trim((string)($input['conditions']??$old['conditions_text']??'')),$rules,is_array($rules['eligibility']??null)?$rules['eligibility']:[]);
        if(array_key_exists('display_order',$input))$plan=$this->repo->setPlanOrder($id,max(0,(int)$input['display_order']));
        return $plan;
    }

    public function duplicate(string $id,string $actor,array $input): array
    {
        $code=strtoupper(trim((string)($input['code']??'')));$name=trim((string)($input['name']??''));
        if($code===''||!preg_match('/^[A-Z0-9][A-Z0-9_-]{2,79}$/',$code))throw new InvalidArgumentException('کد طرح جدید معتبر نیست.');
        if($name==='')throw new InvalidArgumentException('نام طرح جدید الزامی است.');
        return $this->repo->duplicatePlan($id,$actor,$code,$name);
    }

    public function status(string $id,string $status): array
    {
        $status=strtolower(trim($status));
        if(!in_array($status,['draft','active','inactive','archived'],true))throw new InvalidArgumentException('وضعیت طرح معتبر نیست.');
        return $this->repo->setPlanStatus($id,$status);
    }

    public function order(string $id,int $order): array { return $this->repo->setPlanOrder($id,max(0,$order)); }

    public function merchantAccess(string $id,string $actor,array $input): array
    {
        $merchant=trim((string)($input['merchant_id']??''));if(!preg_match('/^[a-f0-9]{26}$/',$merchant))throw new InvalidArgumentException('شناسه پذیرنده معتبر نیست.');
        $status=strtolower(trim((string)($input['status']??'active')));
        $limits=is_array($input['limits']??null)?$input['limits']:[];
        return $this->repo->setMerchantPlanPermission($merchant,$id,$status,$limits,$actor);
    }
}
