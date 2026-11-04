<?php
declare(strict_types=1);
namespace Babanoell\Partners\Domain;
use InvalidArgumentException;
use RuntimeException;

final class CooperationFormDefinition
{
    public static function catalog():array
    {
        return [
            'store_name'=>['type'=>'text','form'=>'individual','label'=>'نام فروشگاه'],
            'store_owner_name'=>['type'=>'text','form'=>'individual','label'=>'نام مالک فروشگاه'],
            'store_address'=>['type'=>'textarea','form'=>'individual','label'=>'آدرس فروشگاه'],
            'store_images'=>['type'=>'file','form'=>'individual','label'=>'تصاویر فروشگاه'],
            'company_name'=>['type'=>'text','form'=>'legal','label'=>'نام شرکت'],
            'contact_person'=>['type'=>'text','form'=>'legal','label'=>'نام رابط'],
            'company_address'=>['type'=>'textarea','form'=>'legal','label'=>'آدرس شرکت'],
            'email'=>['type'=>'email','form'=>'legal','label'=>'ایمیل'],
            'company_images'=>['type'=>'file','form'=>'legal','label'=>'تصاویر شرکت'],
            'company_docs'=>['type'=>'file','form'=>'legal','label'=>'اساسنامه و روزنامه رسمی'],
            'activity_field'=>['type'=>'select','form'=>'both','label'=>'زمینه فعالیت','options_key'=>'activity_field_options'],
            'how_heard'=>['type'=>'select','form'=>'both','label'=>'نحوه آشنایی با ما','options_key'=>'how_heard_options'],
            'activity_description'=>['type'=>'text','form'=>'both','label'=>'شرح فعالیت'],
            'postal_code'=>['type'=>'text','form'=>'both','label'=>'کد پستی'],
            'applicant_id_number'=>['type'=>'text','form'=>'both','label'=>'کد ملی / شناسه ملی'],
            'mobile_number'=>['type'=>'text','form'=>'both','label'=>'شماره همراه'],
            'phone_number'=>['type'=>'text','form'=>'both','label'=>'تلفن ثابت'],
            'website'=>['type'=>'text','form'=>'both','label'=>'آدرس سایت'],
            'branches'=>['type'=>'textarea','form'=>'both','label'=>'آدرس شعب'],
            'description'=>['type'=>'textarea','form'=>'both','label'=>'توضیحات تکمیلی'],
            'national_card'=>['type'=>'file','form'=>'both','label'=>'کارت ملی (رو و پشت)'],
            'id_card'=>['type'=>'file','form'=>'both','label'=>'شناسنامه (تمام صفحات)'],
            'business_license'=>['type'=>'file','form'=>'both','label'=>'جواز کسب'],
            'ownership_or_lease'=>['type'=>'file','form'=>'both','label'=>'سند مالکیت یا اجاره‌نامه'],
            'thana_certificate'=>['type'=>'file','form'=>'both','label'=>'گواهی ثبت ثنا'],
            'logo'=>['type'=>'file','form'=>'both','label'=>'لوگو'],
            'check_image'=>['type'=>'file','form'=>'both','label'=>'تصویر چک صیادی'],
            'signature_certificate'=>['type'=>'file','form'=>'both','label'=>'گواهی امضا'],
            'tax_info'=>['type'=>'file','form'=>'both','label'=>'اطلاعات سامانه مالیاتی'],
            'enamad'=>['type'=>'file','form'=>'both','label'=>'تصویر اینماد'],
        ];
    }
    public static function defaults():array
    {
        $fields=[];foreach(self::catalog()as$key=>$field)$fields[$key]=['visible'=>true,'required'=>in_array($key,['store_owner_name','company_name'],true)];
        return ['fields'=>$fields,'options'=>['activity_field_options'=>['پوشاک','لوازم خانگی','موبایل و کامپیوتر','طلا و جواهر','خدمات'],'how_heard_options'=>['جستجوی گوگل','شبکه‌های اجتماعی','معرفی دوستان','تبلیغات','سایر']]];
    }
    public static function configure(array$current,array$input):array
    {
        foreach(['fields','options']as$key)if(isset($input[$key])&&!is_array($input[$key]))throw new InvalidArgumentException('ساختار تنظیمات فرم معتبر نیست.');
        $out=['fields'=>$current['fields'],'options'=>$current['options']];
        foreach(($input['fields']??[])as$key=>$rule){
            if(!isset(self::catalog()[$key])||!is_array($rule)||array_diff(array_keys($rule),['visible','required']))throw new InvalidArgumentException('تنظیم فیلد معتبر نیست.');
            foreach($rule as$flag=>$value){if(!in_array($value,[true,false,0,1,'0','1'],true))throw new InvalidArgumentException('وضعیت فیلد باید فعال یا غیرفعال باشد.');$out['fields'][$key][$flag]=in_array($value,[true,1,'1'],true);}
            if(!$out['fields'][$key]['visible']&&$out['fields'][$key]['required'])throw new InvalidArgumentException('فیلد پنهان نمی‌تواند الزامی باشد.');
        }
        foreach(['store_owner_name','company_name']as$key)if(!$out['fields'][$key]['visible']||!$out['fields'][$key]['required'])throw new InvalidArgumentException('نام مالک یا شرکت برای ثبت درخواست همواره الزامی است.');
        foreach(($input['options']??[])as$key=>$values){
            if(!array_key_exists($key,$out['options']))throw new InvalidArgumentException('گروه گزینه‌ها معتبر نیست.');
            if(is_string($values)){if(mb_strlen($values)>20000)throw new InvalidArgumentException('متن گزینه‌ها بیش از حد طولانی است.');$values=preg_split('/\R/u',$values)?:[];}
            if(!is_array($values)||!array_is_list($values)||count($values)>100)throw new InvalidArgumentException('حداکثر صد گزینه برای هر گروه مجاز است.');
            $clean=[];foreach($values as$value){if(!is_string($value))throw new InvalidArgumentException('متن گزینه معتبر نیست.');$value=trim($value);if($value==='')continue;if(mb_strlen($value)>150||preg_match('/[<>\x00-\x1f]/u',$value))throw new InvalidArgumentException('طول یا نویسه‌های گزینه معتبر نیست.');if(!in_array($value,$clean,true))$clean[]=$value;}
            if($clean===[])throw new InvalidArgumentException('هر گروه باید دست‌کم یک گزینه داشته باشد.');$out['options'][$key]=$clean;
        }
        return$out;
    }
    public static function values(string$type,array$data):array
    {
        $aliases=['full_name'=>$type==='legal'?'company_name':'store_owner_name','trade_name'=>'store_name','national_code'=>'applicant_id_number','mobile'=>'mobile_number','activity'=>'activity_field','phone'=>'phone_number','address'=>$type==='legal'?'company_address':'store_address'];
        foreach($aliases as$old=>$new)if(!array_key_exists($new,$data)&&array_key_exists($old,$data))$data[$new]=$data[$old];return$data;
    }
    public static function validate(string$type,array$input,array$definition):array
    {
        if(!in_array($type,['individual','legal'],true))throw new InvalidArgumentException('نوع درخواست همکاری معتبر نیست.');
        $values=self::values($type,$input);$data=[];$errors=[];
        foreach(self::catalog()as$key=>$field){
            if($field['type']==='file'||!in_array($field['form'],['both',$type],true)||empty($definition['fields'][$key]['visible']))continue;
            $raw=$values[$key]??'';if(!is_scalar($raw)&&$raw!==null){$errors[$key]='مقدار این فیلد معتبر نیست.';continue;}$value=trim((string)$raw);
            if(in_array($key,['mobile_number','phone_number','postal_code','applicant_id_number'],true))$value=strtr($value,['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9','٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9']);
            if($value===''){if(!empty($definition['fields'][$key]['required']))$errors[$key]=$field['label'].' الزامی است.';continue;}
            if(mb_strlen($value)>($field['type']==='textarea'?4000:512))$errors[$key]='متن واردشده بیش از حد طولانی است.';
            elseif($field['type']==='select'&&!in_array($value,$definition['options'][$field['options_key']]??[],true))$errors[$key]='یکی از گزینه‌های فعال را انتخاب کنید.';
            elseif($key==='mobile_number'&&!preg_match('/^09[0-9]{9}$/D',$value))$errors[$key]='شماره همراه معتبر وارد کنید.';
            elseif($key==='postal_code'&&!preg_match('/^[0-9]{10}$/D',$value))$errors[$key]='کد پستی باید ده رقم باشد.';
            elseif($key==='applicant_id_number'&&!preg_match($type==='legal'?'/^[0-9]{11}$/D':'/^[0-9]{10}$/D',$value))$errors[$key]='کد ملی یا شناسه ملی معتبر وارد کنید.';
            elseif($key==='email'&&!filter_var($value,FILTER_VALIDATE_EMAIL))$errors[$key]='نشانی ایمیل معتبر وارد کنید.';
            elseif($key==='website'&&(!filter_var($value,FILTER_VALIDATE_URL)||!in_array(strtolower((string)parse_url($value,PHP_URL_SCHEME)),['https','http'],true)))$errors[$key]='نشانی سایت باید با http یا https شروع شود.';
            $data[$key]=$value;
        }
        if($errors!==[])throw new CooperationFormValidationException($errors);
        $data['full_name']=$type==='legal'?$data['company_name']:$data['store_owner_name'];
        foreach(['store_name'=>'trade_name','mobile_number'=>'mobile','applicant_id_number'=>'national_code','activity_field'=>'activity','phone_number'=>'phone']as$from=>$to)if(isset($data[$from]))$data[$to]=$data[$from];
        if($type==='legal'&&is_scalar($input['trade_name']??null)&&trim((string)$input['trade_name'])!=='')$data['trade_name']=mb_substr(trim((string)$input['trade_name']),0,190);
        return$data;
    }
    public static function documentPolicy(array$request):array
    {
        $snapshot=$request['data']['_form']??null;
        if($snapshot===null)return [['code'=>'cooperation_identity','label'=>'مدارک هویتی','visible'=>true,'required'=>false],['code'=>'cooperation_license','label'=>'مجوز فعالیت','visible'=>true,'required'=>false],['code'=>'cooperation_contract','label'=>'قرارداد همکاری','visible'=>true,'required'=>false]];
        if(!is_array($snapshot)||!is_array($snapshot['fields']??null))throw new RuntimeException('cooperation_definition_invalid');
        $policy=[];foreach(self::catalog()as$key=>$field){if($field['type']!=='file'||!in_array($field['form'],['both',$request['request_type']],true))continue;$rule=$snapshot['fields'][$key]??null;if(!is_array($rule)||!is_bool($rule['visible']??null)||!is_bool($rule['required']??null))throw new RuntimeException('cooperation_definition_invalid');$code=match($key){'national_card'=>'cooperation_identity','business_license'=>'cooperation_license',default=>'cooperation_'.$key};$policy[]=['code'=>$code,'label'=>$field['label'],'visible'=>$rule['visible'],'required'=>$rule['visible']&&$rule['required']];}
        $policy[]=['code'=>'cooperation_contract','label'=>'قرارداد همکاری','visible'=>true,'required'=>false];return$policy;
    }
    public static function fingerprint(string$type,array$input):string
    {
        $values=self::values($type,$input);$clean=[];foreach(self::catalog()as$key=>$field)if($field['type']!=='file'&&in_array($field['form'],['both',$type],true)&&isset($values[$key])&&is_scalar($values[$key]))$clean[$key]=trim((string)$values[$key]);ksort($clean);return hash('sha256',json_encode([$type,$clean],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
    }
    public static function patchAliases(string$type,array$data,array$changes):array
    {
        $map=['full_name'=>$type==='legal'?'company_name':'store_owner_name','trade_name'=>'store_name','mobile'=>'mobile_number','national_code'=>'applicant_id_number','phone'=>'phone_number','address'=>$type==='legal'?'company_address':'store_address'];
        foreach($map as$old=>$key)if(array_key_exists($old,$changes)){if($changes[$old]===null)unset($data[$key]);else$data[$key]=$changes[$old];}
        if($type==='legal'&&array_key_exists('company_name',$changes))$data['full_name']=$changes['company_name'];
        if(array_key_exists('applicant_id_number',$changes))$data['national_code']=$changes['applicant_id_number'];
        return$data;
    }
}
