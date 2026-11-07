<?php
declare(strict_types=1);
namespace Babanoell\Documents\Service;
use Babanoell\Documents\Domain\ApiException;
use Babanoell\Documents\Http\LoansClient;
use Babanoell\Documents\Http\PartnersClient;
use Babanoell\Documents\Http\FinanceClient;
use Babanoell\Documents\Security\Principal;

final class EntityAccessService
{
 public function __construct(private readonly LoansClient$loans,private readonly ?PartnersClient$partners=null,private readonly ?FinanceClient$finance=null){}
 public function assert(string$entityType,string$entityId,string$bearer,Principal$principal):array
 {
  $entityType=strtolower(trim($entityType));$entityId=trim($entityId);
  if($entityType==='contract'){
   if($this->partners===null)throw new ApiException(503,'partners_unavailable','سرویس قرارداد برای بررسی دسترسی تنظیم نشده است.');
   $r=$this->partners->contract($entityId,$bearer);
   if(!$r->ok())throw new ApiException(in_array($r->status,[401,403,404],true)?$r->status:503,'contract_access_denied','دسترسی به این نسخهٔ قرارداد قابل تأیید نیست.');
   $contract=$r->json['data']['contract']??null;
   if(!is_array($contract)||($contract['id']??null)!==$entityId)throw new ApiException(503,'partners_response_invalid','پاسخ نسخهٔ قرارداد معتبر نیست.');
   return $contract;
  }
  if($entityType==='loan_request'){
   $r=$this->loans->request($entityId,$bearer);
   if($r->status===404)throw new ApiException(404,'entity_not_found','پرونده مرتبط پیدا نشد.');
   if($r->status===401)throw new ApiException(401,'invalid_session','نشست کاربری معتبر نیست.');
   if($r->status===403)throw new ApiException(403,'entity_access_denied','دسترسی به پرونده مرتبط مجاز نیست.');
   if(!$r->ok())throw new ApiException(503,'loans_unavailable','سرویس تسهیلات برای بررسی دسترسی در دسترس نیست.');
   $loan=$r->json['data']['request']??null;if(!is_array($loan))throw new ApiException(503,'loans_response_invalid','پاسخ سرویس تسهیلات قابل تأیید نیست.');return$loan;
  }
  if($entityType==='loan_guarantor'){
   $r=$this->loans->guarantor($entityId,$bearer);
   if($r->status===404)throw new ApiException(404,'entity_not_found','ضامن مرتبط پیدا نشد.');
   if($r->status===401)throw new ApiException(401,'invalid_session','نشست کاربری معتبر نیست.');
   if($r->status===403)throw new ApiException(403,'entity_access_denied','دسترسی به ضامن مرتبط مجاز نیست.');
   if(!$r->ok())throw new ApiException(503,'loans_unavailable','سرویس تسهیلات برای بررسی دسترسی ضامن در دسترس نیست.');
   $data=$r->json['data']??null;if(!is_array($data)||!is_array($data['guarantor']??null)||!is_array($data['request']??null))throw new ApiException(503,'loans_response_invalid','پاسخ ضامن از سرویس تسهیلات قابل تأیید نیست.');
   return['guarantor'=>$data['guarantor'],'request'=>$data['request']];
  }
  if($entityType==='cooperation_request'){
   if($this->partners===null)throw new ApiException(503,'partners_unavailable','سرویس شرکا برای بررسی دسترسی تنظیم نشده است.');
   $r=$this->partners->cooperation($entityId,$bearer);
   if($r->status===404)throw new ApiException(404,'entity_not_found','درخواست همکاری مرتبط پیدا نشد.');
   if($r->status===401)throw new ApiException(401,'invalid_session','نشست کاربری معتبر نیست.');
   if($r->status===403)throw new ApiException(403,'entity_access_denied','دسترسی به درخواست همکاری مرتبط مجاز نیست.');
   if(!$r->ok())throw new ApiException(503,'partners_unavailable','سرویس شرکا برای بررسی دسترسی در دسترس نیست.');
   $coop=$r->json['data']['request']??null;if(!is_array($coop))throw new ApiException(503,'partners_response_invalid','پاسخ سرویس شرکا قابل تأیید نیست.');return$coop;
  }
  if($entityType==='merchant'){
   if($this->partners===null)throw new ApiException(503,'partners_unavailable','سرویس شرکا برای بررسی دسترسی تنظیم نشده است.');
   $r=$this->partners->merchant($entityId,$bearer);
   if($r->status===404)throw new ApiException(404,'entity_not_found','پذیرنده مرتبط پیدا نشد.');
   if($r->status===401)throw new ApiException(401,'invalid_session','نشست کاربری معتبر نیست.');
   if($r->status===403)throw new ApiException(403,'entity_access_denied','دسترسی به پذیرنده مرتبط مجاز نیست.');
   if(!$r->ok())throw new ApiException(503,'partners_unavailable','سرویس شرکا برای بررسی دسترسی در دسترس نیست.');
   $merchant=$r->json['data']['merchant']??null;if(!is_array($merchant))throw new ApiException(503,'partners_response_invalid','پاسخ سرویس شرکا قابل تأیید نیست.');return$merchant;
  }
  if($entityType==='settlement_cycle'){
   if($this->finance===null)throw new ApiException(503,'finance_unavailable','سرویس مالی برای بررسی دسترسی تنظیم نشده است.');
   $r=$this->finance->cycle($entityId,$bearer);
   if($r->status===404)throw new ApiException(404,'entity_not_found','سیکل تسویه مرتبط پیدا نشد.');
   if($r->status===401)throw new ApiException(401,'invalid_session','نشست کاربری معتبر نیست.');
   if($r->status===403)throw new ApiException(403,'entity_access_denied','دسترسی به سیکل تسویه مرتبط مجاز نیست.');
   if(!$r->ok())throw new ApiException(503,'finance_unavailable','سرویس مالی برای بررسی دسترسی در دسترس نیست.');
   $cycle=$r->json['data']['cycle']??null;if(!is_array($cycle))throw new ApiException(503,'finance_response_invalid','پاسخ سرویس مالی قابل تأیید نیست.');return$cycle;
  }
  if(!$principal->isSuperAdmin()&&!$principal->can('documents.manage'))throw new ApiException(403,'entity_access_denied','دسترسی به این نوع رکورد مجاز نیست.');
  if(!preg_match('/^[a-f0-9]{26}$/',$entityId))throw new ApiException(422,'entity_id_invalid','شناسه رکورد مرتبط معتبر نیست.');
  return['id'=>$entityId,'entity_type'=>$entityType];
 }
 public function requirements(string$requestId,string$bearer):array
 {
  $r=$this->loans->requirements($requestId,$bearer);if(!$r->ok())throw new ApiException($r->status===403?403:503,$r->status===403?'entity_access_denied':'loans_unavailable',$r->status===403?'دسترسی به پرونده مرتبط مجاز نیست.':'مدارک الزامی طرح از سرویس تسهیلات دریافت نشد.');
  $data=$r->json['data']??null;if(!is_array($data))throw new ApiException(503,'loans_response_invalid','پاسخ مدارک الزامی قابل تأیید نیست.');return$data;
 }
}
