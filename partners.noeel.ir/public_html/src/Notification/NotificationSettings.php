<?php
declare(strict_types=1);
namespace Babanoell\Partners\Notification;

final class NotificationSettings
{
    public const STATUSES=['under_review'=>'در حال بررسی','need_correction'=>'نقص مدارک','docs_completion'=>'تکمیل مدارک','docs_verified'=>'تایید مدارک','contracting'=>'عقد قرارداد','cancelled'=>'انصراف','rejected'=>'رد شده'];
    public const VARIABLES=['request_id','status','store_name','store_owner_name','company_name','contact_person','applicant_name','contract_link'];
    public static function normalize(array $settings): array
    {
        if(!is_array($settings['cooperation_statuses']??null))throw new \InvalidArgumentException('تنظیمات وضعیت‌های همکاری معتبر نیست.');$output=[];
        foreach($settings['cooperation_statuses'] as $status=>$rule){
            if(!isset(self::STATUSES[$status])||!is_array($rule))throw new \InvalidArgumentException('وضعیت پیامک همکاری معتبر نیست.');
            $enabled=($rule['enabled']??false)===true;$body=filter_var($rule['pattern_id']??0,FILTER_VALIDATE_INT);$vars=$rule['variables']??[];
            if($body===false||$body<0||$body>2147483647||!is_array($vars)||!array_is_list($vars)||count($vars)>3)throw new \InvalidArgumentException('شناسه یا متغیرهای قالب پیامک معتبر نیست.');
            foreach($vars as $v){if(!is_string($v)||strlen($v)>720||str_contains($v,';'))throw new \InvalidArgumentException('متن متغیر پیامک معتبر نیست.');preg_match_all('/\[\[([^\]]+)\]\]/u',$v,$m);foreach($m[1] as $name)if(!in_array($name,self::VARIABLES,true))throw new \InvalidArgumentException('نام متغیر پیامک همکاری ناشناخته است.');}
            if($enabled&&($body<1||$vars===[]))throw new \InvalidArgumentException('برای فعال‌سازی، قالب و حداقل یک متغیر لازم است.');
            $output[$status]=['enabled'=>$enabled,'pattern_id'=>$body,'variables'=>$vars];
        }return ['cooperation_statuses'=>$output];
    }
    public static function variables(array $request,string $status): array
    {
        $d=$request['data']??[];$individual=($request['request_type']??'')==='individual';
        $owner=(string)($d['store_owner_name']??$d['full_name']??'');$contact=(string)($d['contact_person']??$d['full_name']??'');
        $values=['request_id'=>$request['legacy_id']??$request['request_no']??$request['id'],'status'=>self::STATUSES[$status]??$status,
            'store_name'=>$d['store_name']??$d['trade_name']??'','store_owner_name'=>$owner,'company_name'=>$d['company_name']??'',
            'contact_person'=>$contact,'applicant_name'=>$individual?$owner:$contact,
            // The worker freezes a real current contract token before its first possible send.
            'contract_link'=>in_array($status,['docs_verified','contracting'],true)?'[[contract_link]]':''];
        $out=[];foreach($values as $key=>$value)$out['[['.$key.']]']=str_replace(';','،',(string)$value);return $out;
    }
    public static function mobile(string $value): string
    {
        $value=strtr(trim($value),array_combine(preg_split('//u','۰۱۲۳۴۵۶۷۸۹٠١٢٣٤٥٦٧٨٩',-1,PREG_SPLIT_NO_EMPTY),str_split('01234567890123456789')));
        $value=preg_replace('/[\s()\-]/u','',$value)??'';
        if(str_starts_with($value,'+98'))$value='0'.substr($value,3);elseif(str_starts_with($value,'0098'))$value='0'.substr($value,4);elseif(preg_match('/^9\d{9}$/D',$value))$value='0'.$value;
        return $value;
    }
}
