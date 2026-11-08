<?php
declare(strict_types=1);
namespace Babanoell\Loans\Domain;

use Babanoell\Loans\Support\PersianDate;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/** Shared, bound-parameter filters for lists, totals, bank queues and exports. */
final class RequestFilters
{
    public static function normalize(array $input):array
    {
        $out=[];
        foreach(['q','status','settlement_status','bank_settlement_status','financier_payment_status','date_from','date_to','plan_id','plan_type','assigned_bank_user_id','assigned_operations_user_id'] as $key){
            $v=$input[$key]??'';
            if(!is_scalar($v))throw new InvalidArgumentException('مقدار فیلتر معتبر نیست.');
            $v=trim((string)$v);if($v==='')continue;
            if(mb_strlen($v,'UTF-8')>200)throw new InvalidArgumentException('مقدار فیلتر بیش از حد طولانی است.');
            if(in_array($key,['q','date_from','date_to'],true))$v=InputValidator::normalizeDigits($v);
            $out[$key]=$v;
        }
        foreach(['status'=>(new StatusMachine())->labels(),'settlement_status'=>WorkflowCatalog::merchantSettlementStatuses(),'bank_settlement_status'=>WorkflowCatalog::bankSettlementStatuses(),'financier_payment_status'=>WorkflowCatalog::financierPaymentStatuses(),'plan_type'=>['market'=>'','leasing'=>'']] as $key=>$allowed){
            if(isset($out[$key])&&!array_key_exists($out[$key],$allowed))throw new InvalidArgumentException('فیلتر وضعیت یا نوع طرح معتبر نیست.');
        }
        foreach(['plan_id','assigned_bank_user_id','assigned_operations_user_id'] as $key)if(isset($out[$key])&&!preg_match('/^[a-f0-9]{26}$/D',$out[$key]))throw new InvalidArgumentException('شناسهٔ فیلتر معتبر نیست.');
        foreach(['date_from','date_to'] as $key)if(isset($out[$key]))$out[$key]=self::isoDate($out[$key]);
        if(isset($out['date_from'],$out['date_to'])&&$out['date_from']>$out['date_to'])throw new InvalidArgumentException('تاریخ شروع باید قبل از تاریخ پایان باشد.');
        return $out;
    }

    public static function isoDate(string $value):string
    {
        $v=InputValidator::normalizeDigits(trim($value));
        if(preg_match('#^1[0-5][0-9]{2}[/-]#',$v))$v=PersianDate::jalaliToIso($v);
        if(!preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D',$v))throw new InvalidArgumentException('تاریخ فیلتر را به شکل شمسی یا میلادی معتبر وارد کنید.');
        $date=DateTimeImmutable::createFromFormat('!Y-m-d',$v,new DateTimeZone('Asia/Tehran'));
        if(!$date||$date->format('Y-m-d')!==$v||(int)$date->format('Y')<1900||(int)$date->format('Y')>2200)throw new InvalidArgumentException('تاریخ فیلتر معتبر نیست.');
        return $v;
    }

    public static function append(array &$where,array &$params,array $filters):void
    {
        $f=self::normalize($filters);
        foreach(['status','settlement_status','bank_settlement_status','financier_payment_status','plan_id','assigned_bank_user_id','assigned_operations_user_id'] as $key){
            if(isset($f[$key])){$where[]='r.'.$key.'=:filter_'.$key;$params[':filter_'.$key]=$f[$key];}
        }
        if(isset($f['plan_type'])){$where[]='EXISTS (SELECT 1 FROM installment_plan_versions fv WHERE fv.id=r.plan_version_id AND fv.type=:filter_plan_type)';$params[':filter_plan_type']=$f['plan_type'];}
        if(isset($f['q'])){
            $like='%'.str_replace(['!','%','_'],['!!','!%','!_'],$f['q']).'%';$parts=[];
            foreach(['request_no','full_name','national_code','mobile_number'] as $i=>$column){$key=':filter_search_'.$i;$parts[]="r.$column LIKE $key ESCAPE '!'";$params[$key]=$like;}
            $where[]='('.implode(' OR ',$parts).')';
        }
        // API persistence timestamps are UTC; user date boundaries are Tehran civil days.
        foreach(['date_from','date_to'] as $key){
            if(!isset($f[$key]))continue;
            $d=new DateTimeImmutable($f[$key].' 00:00:00',new DateTimeZone('Asia/Tehran'));
            if($key==='date_to')$d=$d->modify('+1 day');
            $param=':filter_'.$key;$where[]='r.created_at'.($key==='date_from'?'>=':'<').$param;$params[$param]=$d->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        }
    }
}
