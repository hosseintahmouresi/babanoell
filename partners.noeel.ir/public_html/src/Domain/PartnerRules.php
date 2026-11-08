<?php
declare(strict_types=1);
namespace Babanoell\Partners\Domain;
use InvalidArgumentException;
final class PartnerRules
{
 public const MERCHANT_STATUSES=['active','inactive','suspended','archived'];
 public const COOPERATION_STATUSES=['submitted','under_review','need_correction','docs_completion','docs_verified','contracting','approved','rejected','cancelled','archived'];
 public const COOPERATION_TRANSITIONS=[
  'submitted'=>['under_review','need_correction','rejected','cancelled'],
  'under_review'=>['need_correction','docs_completion','docs_verified','approved','rejected','cancelled'],
  'need_correction'=>['submitted','docs_completion','cancelled'],
  'docs_completion'=>['under_review','need_correction','docs_verified','rejected','cancelled'],
  'docs_verified'=>['contracting','approved','need_correction','rejected','cancelled'],
  'contracting'=>['approved','need_correction','rejected','cancelled'],
  'approved'=>['archived'],
  'rejected'=>['archived'],
  'cancelled'=>['archived'],
  'archived'=>[],
 ];
 public static function id(string$id,string$label='شناسه'):string{$id=strtolower(trim($id));if(preg_match('/^[a-f0-9]{26}$/',$id)!==1)throw new InvalidArgumentException($label.' معتبر نیست.');return$id;}
 public static function merchantCode(string$code):string{$code=strtoupper(trim($code));if(preg_match('/^[A-Z0-9][A-Z0-9_-]{2,79}$/',$code)!==1)throw new InvalidArgumentException('کد پذیرنده باید ۳ تا ۸۰ نویسه و شامل حروف انگلیسی، عدد، خط تیره یا زیرخط باشد.');return$code;}
 public static function merchantStatus(string$status):string{$status=strtolower(trim($status));if(!in_array($status,self::MERCHANT_STATUSES,true))throw new InvalidArgumentException('وضعیت پذیرنده معتبر نیست.');return$status;}
 public static function cooperationStatus(string$status):string{$status=strtolower(trim($status));if(!in_array($status,self::COOPERATION_STATUSES,true))throw new InvalidArgumentException('وضعیت درخواست همکاری معتبر نیست.');return$status;}
 public static function assertTransition(string$from,string$to):void{if(!in_array($to,self::COOPERATION_TRANSITIONS[$from]??[],true))throw new InvalidArgumentException('تغییر وضعیت درخواست همکاری از «'.$from.'» به «'.$to.'» مجاز نیست.');}
 public static function userId(string$id):string{return self::id($id,'شناسه کاربر');}
}
