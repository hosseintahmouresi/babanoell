<?php
declare(strict_types=1);
namespace Babanoell\Loans\Domain;

/** Evaluates owner evidence; never interprets provider-specific success codes. */
final class CaseReadiness
{
    public static function enabled(mixed $value): bool
    {
        return !in_array($value,[false,0,'0','false','off','no'],true);
    }

    public static function requiredContractKinds(array $loan): array
    {
        $rules=is_array($loan['plan_rules']??null)?$loan['plan_rules']:[];$kinds=[];
        foreach(['loan_application','facility_agreement']as$kind)
            if(self::enabled($rules[$kind.'_contract_enabled']??true))$kinds[]=$kind;
        return $kinds;
    }

    public static function evaluate(array $loan, ?array $documents, ?array $contracts, array $proofs=[]): array
    {
        $steps=[];$blockers=[];$evidence=['contracts'=>[],'verification_ids'=>[]];
        $add=static function(string$code,string$title,string$status,string$action,string$message,string$error='')use(&$steps,&$blockers):void{
            $steps[]=['code'=>$code,'title'=>$title,'status'=>$status,'action'=>$action,'message'=>$message];
            if(!in_array($status,['complete','ready'],true))$blockers[]=['code'=>$error!==''?$error:$code.'_incomplete','step'=>$code,'action'=>$action,'message'=>$message];
        };
        $profile=true;
        try{InputValidator::assertStoredRequestSubmittable($loan);}catch(\InvalidArgumentException){$profile=false;}
        $add('profile','اطلاعات و سفارش',$profile?'complete':'blocked','edit',$profile?'اطلاعات اصلی پرونده کامل است.':'اطلاعات هویتی، نشانی، سفارش یا سررسید نیازمند تکمیل است.');
        $documentKnown=is_bool($documents['complete']??null);
        $documentComplete=$documentKnown&&$documents['complete']===true;
        $add('documents','مدارک و ضامن‌ها',$documentComplete?'complete':($documentKnown?'blocked':'unknown'),'documents',
            $documentComplete?'مدارک الزامی تأیید شده‌اند.':($documentKnown?'مدارک الزامی هنوز کامل و تأییدشده نیستند.':'وضعیت مدارک فعلاً قابل تأیید نیست.'),$documentKnown?'required_documents_incomplete':'documents_unavailable');
        $verification=true;$fingerprint=hash('sha256',InputValidator::normalizeDigits((string)($loan['national_code']??'')).'|'.InputValidator::normalizeDigits((string)($loan['mobile_number']??'')));
        foreach(['identity_match'=>CreditValidationPolicy::identityRequired($loan),'credit_report'=>CreditValidationPolicy::required($loan)]as$type=>$required){
            if(!$required)continue;
            $verified=$type==='identity_match'?CreditValidationPolicy::identityVerified($loan):CreditValidationPolicy::verified($loan);
            $proof=null;
            // Repository returns latest first. A failed/newer attempt must not fall back to an old proof.
            foreach($proofs as$item){
                if(!is_array($item)||($item['verification_type']??null)!==$type)continue;
                if(($item['status']??null)==='verified'&&is_string($item['subject_fingerprint']??null)&&hash_equals($fingerprint,$item['subject_fingerprint'])
                    &&is_string($item['id']??null)&&preg_match('/^[a-f0-9]{26}$/D',$item['id']))$proof=$item;
                break;
            }
            if(!$verified||$proof===null)$verification=false;else$evidence['verification_ids'][]=$proof['id'];
        }
        $add('verification','هویت و اعتبار',$verification?'complete':'blocked','verification',
            $verification?'کنترل‌های الزامی هویت و اعتبار برقرارند.':'نتیجه معتبر برای هویت جاری متقاضی باید توسط کارشناس پیگیری شود.');
        $contractStatus='complete';$required=self::requiredContractKinds($loan);
        if($required!==[]){
            if($contracts===null||!array_is_list($contracts))$contractStatus='unknown';
            else{
                $mode=$loan['plan_rules']['contract_mode']??'physical';
                if(!in_array($mode,['physical','digital'],true))$contractStatus='unknown';
                foreach($contracts as$item)if(!is_array($item)||!is_string($item['contract_kind']??null))$contractStatus='unknown';
                if($contractStatus!=='unknown')foreach($required as$kind){
                    $latest=null;$ambiguous=false;
                    foreach($contracts as$item){
                        if($item['contract_kind']!==$kind)continue;
                        $revision=$item['revision']??null;
                        if(!is_int($revision)&&!(is_string($revision)&&ctype_digit($revision))){$ambiguous=true;break;}
                        $revision=(int)$revision;if($revision<1){$ambiguous=true;break;}
                        if($latest!==null&&$revision===(int)$latest['revision']){$ambiguous=true;break;}
                        if($latest===null||$revision>(int)$latest['revision'])$latest=$item;
                    }
                    $valid=!$ambiguous&&$latest!==null&&($latest['subject_type']??null)==='loan_request'
                        &&($latest['subject_id']??null)===($loan['id']??null)&&($latest['status']??null)==='signed'
                        &&self::validMode($latest,$mode,$contracts)&&is_string($latest['signed_at']??null)&&$latest['signed_at']!==''
                        &&is_string($latest['id']??null)&&preg_match('/^[a-f0-9]{26}$/D',$latest['id'])
                        &&is_string($latest['rendered_content']??null)&&$latest['rendered_content']!==''
                        &&is_string($latest['rendered_hash']??null)&&preg_match('/^[a-f0-9]{64}$/D',$latest['rendered_hash'])
                        &&hash_equals(hash('sha256',$latest['rendered_content']),$latest['rendered_hash']);
                    if(!$valid)$contractStatus='blocked';
                    else$evidence['contracts'][]=['id'=>$latest['id'],'kind'=>$kind,'revision'=>(int)$latest['revision'],'mode'=>$latest['mode'],'sha256'=>$latest['rendered_hash']];
                }
            }
        }
        $add('contracts','قراردادهای پرونده',$contractStatus,'contracts',$contractStatus==='complete'?'قراردادهای الزامی امضاشده‌اند یا برای این طرح الزامی نیستند.':($contractStatus==='unknown'?'وضعیت قراردادها فعلاً قابل تأیید نیست.':'آخرین نسخه قراردادهای الزامی باید امضا و تأیید شود.'),$contractStatus==='unknown'?'contracts_unavailable':'required_contracts_incomplete');
        $state=(string)($loan['status']??'');$version=(int)($loan['row_version']??0);
        $prerequisites=$blockers===[];$ready=$prerequisites&&$state==='docs_verified'&&$version>0;
        $grantDone=$state==='granted'||($state==='archived'&&!empty($loan['granted_at']));
        $closed=!$grantDone&&in_array($state,['cancelled','archived'],true);
        $add('grant','ثبت اعطا',$grantDone?'complete':($ready?'ready':'waiting'),$ready?'grant':'review',
            $grantDone?'اعطای این پرونده قبلاً ثبت شده است.':($closed?'پرونده بسته شده و اعطایی برای آن ثبت نشده است.':($ready?'پرونده آماده ثبت اعطا توسط کاربر مجاز است.':'پس از تکمیل پیش‌نیازها و تأیید مدارک، اعطا قابل ثبت است.')),'workflow_not_ready');
        $next=$ready?'grant':($blockers[0]['action']??'review');
        return ['request_id'=>(string)($loan['id']??''),'status'=>$state,'loan_row_version'=>$version,
            'ready_for_grant'=>$ready,'steps'=>$steps,'blockers'=>$blockers,'next_action'=>$grantDone?'done':($closed?'closed':$next),'evidence'=>$evidence];
    }

    /** Partners owns authorized per-contract mode conversions; a broken revision chain is not evidence. */
    private static function validMode(array $latest,string $expected,array $contracts):bool
    {
        $current=$latest;$visited=[];
        for($depth=0;$depth<=count($contracts);$depth++){
            if(!in_array($current['mode']??null,['digital','physical'],true))return false;
            if($current['mode']===$expected)return true;
            $from=$current['reissued_from_id']??null;$actor=$current['created_by_user_id']??null;
            if(!is_string($from)||isset($visited[$from])||!is_string($actor)||!preg_match('/^[a-f0-9]{26}$/D',$actor)
                ||!is_string($current['reissue_reason']??null)||trim($current['reissue_reason'])==='')return false;
            $visited[$from]=true;$parent=null;
            foreach($contracts as$item)if(($item['id']??null)===$from){if($parent!==null)return false;$parent=$item;}
            if($parent===null||($parent['subject_type']??null)!==($latest['subject_type']??null)
                ||($parent['subject_id']??null)!==($latest['subject_id']??null)||($parent['contract_kind']??null)!==($latest['contract_kind']??null)
                ||(int)($parent['revision']??0)+1!==(int)($current['revision']??0))return false;
            $current=$parent;
        }
        return false;
    }
}
