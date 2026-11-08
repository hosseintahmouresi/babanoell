<?php
declare(strict_types=1);
namespace Babanoell\Loans\Domain;

use InvalidArgumentException;

final class PlanRules
{
    public const OCCUPATIONS = [
        'official_employee'=>'کارمند رسمی',
        'contract_employee'=>'کارمند قراردادی',
        'worker'=>'کارگر',
        'freelancer'=>'آزاد',
        'retired'=>'بازنشسته',
        'pensioner'=>'مستمری بگیر',
        'employer'=>'کارفرما',
        'other'=>'سایر',
    ];

    public const VISIBILITY_CONTEXTS = [
        'merchant_request'=>'فرم ثبت درخواست پذیرنده',
        'applicant_request'=>'فرم ثبت درخواست متقاضی',
        'calculator'=>'محاسبه‌گر',
    ];

    public const CONTRACT_MODES = ['physical'=>'فیزیکی','digital'=>'دیجیتال'];

    /** @return array<string,mixed> */
    public static function normalize(array $input, array $legacySettings=[]): array
    {
        $rules = is_array($input['rules']??null) ? $input['rules'] : [];
        $legacyVisibility = $legacySettings['visibility_contexts'] ?? null;
        if (!array_key_exists('visibility_contexts',$rules) && $legacyVisibility!==null) $rules['visibility_contexts']=$legacyVisibility;
        if (!array_key_exists('occupation_documents_enabled',$rules) && array_key_exists('occupation_documents_enabled',$legacySettings)) $rules['occupation_documents_enabled']=$legacySettings['occupation_documents_enabled'];
        if (!array_key_exists('required_documents_by_occupation',$rules) && array_key_exists('required_documents_by_occupation',$legacySettings)) $rules['required_documents_by_occupation']=$legacySettings['required_documents_by_occupation'];
        if (!array_key_exists('secondary_check_required',$rules) && array_key_exists('secondary_check_required',$legacySettings)) $rules['secondary_check_required']=$legacySettings['secondary_check_required'];

        $rawContexts=$rules['visibility_contexts']??array_keys(self::VISIBILITY_CONTEXTS);
        if(is_string($rawContexts))$rawContexts=preg_split('/\s*[,،]\s*/u',$rawContexts,-1,PREG_SPLIT_NO_EMPTY)?:[];
        $aliases=['mlr_loan_request_form'=>'merchant_request','mlr_applicant_loan_request_form'=>'applicant_request','mlr_loan_calculator'=>'calculator'];
        $contexts=[];
        foreach(is_array($rawContexts)?$rawContexts:[] as $ctx){$ctx=trim((string)$ctx);$ctx=$aliases[$ctx]??$ctx;if(isset(self::VISIBILITY_CONTEXTS[$ctx]))$contexts[$ctx]=true;}
        $rules['visibility_contexts']=array_keys($contexts);
        if($rules['visibility_contexts']===[])$rules['visibility_contexts']=array_keys(self::VISIBILITY_CONTEXTS);

        $rules['merchant_access_mode']=in_array((string)($rules['merchant_access_mode']??'all'),['all','allowlist'],true)?(string)($rules['merchant_access_mode']??'all'):'all';
        $rules['occupation_documents_enabled']=self::bool($rules['occupation_documents_enabled']??false);
        $rules['secondary_check_required']=self::bool($rules['secondary_check_required']??true);
        $rules['check_images_required']=self::bool($rules['check_images_required']??($legacySettings['check_images_required']??true));
        $rules['requires_bank_validation']=self::bool($rules['requires_bank_validation']??($legacySettings['requires_bank_validation']??false));
        $rules['requires_identity_verification']=self::bool($rules['requires_identity_verification']??($legacySettings['requires_identity_verification']??false));
        $rules['calculation_schema_version']=trim((string)($rules['calculation_schema_version']??'v1'))?:'v1';
        $mode=strtolower(trim((string)($rules['contract_mode']??($legacySettings['contract_mode']??'physical'))));
        $rules['contract_mode']=isset(self::CONTRACT_MODES[$mode])?$mode:'physical';
        $rules['loan_application_contract_enabled']=self::bool($rules['loan_application_contract_enabled']??true);
        $rules['facility_agreement_contract_enabled']=self::bool($rules['facility_agreement_contract_enabled']??true);
        $rules['loan_application_template_code']=trim((string)($rules['loan_application_template_code']??'loan_application_default'))?:'loan_application_default';
        $rules['facility_agreement_template_code']=trim((string)($rules['facility_agreement_template_code']??'facility_agreement_default'))?:'facility_agreement_default';

        $rules['contract_first_due_rule']=(string)($rules['contract_first_due_rule']??($legacySettings['contract_first_due_rule']??'interval'));
        $rules['contract_marketing_development_amount']=InputValidator::money($rules['contract_marketing_development_amount']??($legacySettings['contract_marketing_development_amount']??0));

        $map=[];
        $rawMap=$rules['required_documents_by_occupation']??[];
        if(is_array($rawMap)){
            foreach(self::OCCUPATIONS as $code=>$label){
                if(!array_key_exists($code,$rawMap))continue;
                $items=is_array($rawMap[$code])?$rawMap[$code]:[];
                $map[$code]=RequiredDocumentCatalog::normalize($items);
            }
        }
        $rules['required_documents_by_occupation']=$map;

        $eligibility=is_array($input['eligibility']??null)?$input['eligibility']:(is_array($rules['eligibility']??null)?$rules['eligibility']:[]);
        $rules['eligibility']=self::normalizeEligibility($eligibility);
        return $rules;
    }

    /** @return string[] */
    public static function validate(array $rules): array
    {
        $errors=[];
        if(!in_array((string)($rules['contract_first_due_rule']??'interval'),['interval','jalali_month_end_half'],true))$errors[]='قاعده اولین سررسید قرارداد معتبر نیست.';
        if((float)($rules['contract_marketing_development_amount']??0)<0)$errors[]='مبلغ توسعه بازار قرارداد منفی نیست.';
        if(!in_array((string)($rules['merchant_access_mode']??''),['all','allowlist'],true))$errors[]='حالت دسترسی پذیرنده معتبر نیست.';
        if(!isset(self::CONTRACT_MODES[(string)($rules['contract_mode']??'')]))$errors[]='نوع قرارداد طرح معتبر نیست.';
        foreach(['loan_application_template_code','facility_agreement_template_code'] as $key){$v=(string)($rules[$key]??'');if($v===''||!preg_match('/^[a-z0-9_\-]{3,80}$/',$v))$errors[]='کد قالب قرارداد معتبر نیست.';}
        foreach((array)($rules['visibility_contexts']??[]) as $ctx)if(!isset(self::VISIBILITY_CONTEXTS[(string)$ctx]))$errors[]='محل نمایش طرح معتبر نیست.';
        if(!empty($rules['occupation_documents_enabled'])){
            $map=is_array($rules['required_documents_by_occupation']??null)?$rules['required_documents_by_occupation']:[];
            if($map===[])$errors[]='برای حالت مدارک وابسته به شغل، حداقل یک شغل باید تنظیم شود.';
            foreach($map as $occupation=>$docs){
                if(!isset(self::OCCUPATIONS[(string)$occupation]))$errors[]='شغل نامعتبر در تنظیمات مدارک وجود دارد.';
                if(!is_array($docs)||$docs===[])$errors[]='برای هر شغل فعال حداقل یک مدرک الزامی است.';
            }
        }
        return array_values(array_unique($errors));
    }

    /** @return string[] */
    public static function requiredDocuments(array $plan,string $occupation=''): array
    {
        $fallback=is_array($plan['required_documents']??null)?$plan['required_documents']:[];
        $rules=is_array($plan['rules']??null)?$plan['rules']:[];
        if(empty($rules['check_images_required']))$fallback=array_values(array_diff($fallback,['blank_check_image','completed_check_files']));
        if(empty($rules['secondary_check_required']))$fallback=array_values(array_diff($fallback,['secondary_check']));
        if(empty($rules['occupation_documents_enabled']))return $fallback;
        $occupation=trim($occupation);
        if($occupation==='')return $fallback;
        if(!isset(self::OCCUPATIONS[$occupation]))throw new InvalidArgumentException('برای این طرح انتخاب شغل معتبر الزامی است.');
        $map=is_array($rules['required_documents_by_occupation']??null)?$rules['required_documents_by_occupation']:[];
        if(!isset($map[$occupation])||!is_array($map[$occupation])||$map[$occupation]===[])throw new InvalidArgumentException('مدارک شغل انتخاب‌شده برای این طرح تنظیم نشده است.');
        $docs=array_values($map[$occupation]);
        if(empty($rules['check_images_required']))$docs=array_values(array_diff($docs,['blank_check_image','completed_check_files']));
        if(empty($rules['secondary_check_required']))$docs=array_values(array_diff($docs,['secondary_check']));
        return $docs;
    }

    public static function visibleIn(array $plan,string $context): bool
    {
        $rules=is_array($plan['rules']??null)?$plan['rules']:[];
        $contexts=is_array($rules['visibility_contexts']??null)?$rules['visibility_contexts']:array_keys(self::VISIBILITY_CONTEXTS);
        return in_array($context,$contexts,true);
    }

    /** @return array<string,mixed> */
    private static function normalizeEligibility(array $e): array
    {
        $out=[];
        foreach(['min_age','max_age'] as $k){if(isset($e[$k])&&$e[$k]!==''&&$e[$k]!==null)$out[$k]=max(0,(int)$e[$k]);}
        if(isset($out['min_age'],$out['max_age'])&&$out['max_age']<$out['min_age'])throw new InvalidArgumentException('حداکثر سن نمی‌تواند کمتر از حداقل سن باشد.');
        foreach(['allowed_occupations','blocked_occupations'] as $k){
            $vals=is_array($e[$k]??null)?$e[$k]:[];$clean=[];
            foreach($vals as$v){$v=trim((string)$v);if(isset(self::OCCUPATIONS[$v]))$clean[$v]=true;}
            if($clean!==[])$out[$k]=array_keys($clean);
        }
        if(isset($e['nationality']))$out['nationality']=substr(trim((string)$e['nationality']),0,32);
        return $out;
    }

    private static function bool(mixed $v): bool
    {
        return is_bool($v)?$v:in_array(strtolower(trim((string)$v)),['1','true','yes','on'],true);
    }
}
