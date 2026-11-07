<?php
declare(strict_types=1);
namespace Babanoell\Loans\Notification;

final class NotificationSettings
{
    public const STATUSES=['under_review'=>'در حال بررسی','need_correction'=>'نقص مدارک','docs_completion'=>'تکمیل مدارک','docs_verified'=>'تأیید مدارک','granted'=>'اعطا شده','rejected'=>'رد شده'];
    public const VARIABLES=['request_id','status','full_name','national_code','mobile_number','loan_amount','installment_count','installment_amount','plan_name','acceptor_name'];
    public static function normalize(array $settings): array
    {
        $output=[];
        if(!is_array($settings['loan_statuses']??null))throw new \InvalidArgumentException('تنظیمات وضعیت‌های پیامک معتبر نیست.');
        foreach($settings['loan_statuses']??[] as $status=>$rule){
            if(!isset(self::STATUSES[$status])||!is_array($rule))throw new \InvalidArgumentException('وضعیت پیامک معتبر نیست.');
            $enabled=($rule['enabled']??false)===true;
            $body=filter_var($rule['pattern_id']??0,FILTER_VALIDATE_INT);
            $variables=$rule['variables']??[];
            if($body===false||$body<0||$body>2147483647||!is_array($variables)||!array_is_list($variables)||count($variables)>3)throw new \InvalidArgumentException('شناسه یا متغیرهای قالب پیامک معتبر نیست.');
            foreach($variables as $template){
                if(!is_string($template)||strlen($template)>720||str_contains($template,';'))throw new \InvalidArgumentException('متن متغیر پیامک معتبر نیست.');
                preg_match_all('/\[\[([^\]]+)\]\]/u',$template,$matches);
                foreach($matches[1] as $name)if(!in_array($name,self::VARIABLES,true))throw new \InvalidArgumentException('نام متغیر پیامک ناشناخته است.');
            }
            $applicant=($rule['send_to_applicant']??true)===true;$acceptor=($rule['send_to_acceptor']??false)===true;
            if($enabled&&($body<1||$variables===[]||(!$applicant&&!$acceptor)))throw new \InvalidArgumentException('برای فعال‌سازی، قالب، متغیر و حداقل یک گیرنده لازم است.');
            $output[$status]=['enabled'=>$enabled,'pattern_id'=>$body,'variables'=>$variables,'send_to_applicant'=>$applicant,'send_to_acceptor'=>$acceptor];
        }
        return ['loan_statuses'=>$output];
    }
    public static function variables(array $loan,string $status,string $acceptorName): array
    {
        $result=[];foreach(self::VARIABLES as $key)$result['[['.$key.']]']='';
        $values=['request_id'=>$loan['legacy_id']??$loan['request_no']??$loan['id'],
            'status'=>self::STATUSES[$status]??$status,'full_name'=>$loan['full_name']??'',
            'national_code'=>$loan['national_code']??'','mobile_number'=>$loan['mobile_number']??'',
            'loan_amount'=>number_format((float)($loan['requested_amount']??0)),
            'installment_count'=>$loan['installment_count']??0,'installment_amount'=>number_format((float)($loan['installment_amount']??0)),
            'plan_name'=>$loan['plan_name']??'---','acceptor_name'=>$acceptorName];
        foreach($values as $key=>$value)$result['[['.$key.']]']=str_replace(';','،',(string)$value);
        return $result;
    }
}
