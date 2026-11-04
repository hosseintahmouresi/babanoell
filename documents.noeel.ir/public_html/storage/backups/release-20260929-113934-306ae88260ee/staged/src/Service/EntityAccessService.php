<?php
declare(strict_types=1);
namespace Babanoell\Documents\Service;
use Babanoell\Documents\Domain\ApiException;
use Babanoell\Documents\Http\LoansClient;
use Babanoell\Documents\Security\Principal;

final class EntityAccessService
{
 public function __construct(private readonly LoansClient$loans){}
 public function assert(string$entityType,string$entityId,string$bearer,Principal$principal):array
 {
  $entityType=strtolower(trim($entityType));$entityId=trim($entityId);
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
