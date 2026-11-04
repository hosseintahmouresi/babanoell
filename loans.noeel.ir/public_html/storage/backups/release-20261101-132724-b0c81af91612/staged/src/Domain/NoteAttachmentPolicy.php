<?php
declare(strict_types=1);
namespace Babanoell\Loans\Domain;
use InvalidArgumentException;
final class NoteAttachmentPolicy {
 public static function normalize(mixed $raw): array {
  if(!is_array($raw))throw new InvalidArgumentException('فهرست پیوست‌ها معتبر نیست.');
  if(count($raw)>5)throw new InvalidArgumentException('حداکثر پنج پیوست برای یک یادداشت مجاز است.');
  $ids=[];
  foreach($raw as $v){if(!is_string($v)||preg_match('/^[a-f0-9]{26}$/D',$v)!==1)throw new InvalidArgumentException('شناسه پیوست معتبر نیست.');if(in_array($v,$ids,true))throw new InvalidArgumentException('شناسه پیوست تکراری است.');$ids[]=$v;}
  return $ids;
 }
 public static function assertDocument(array $doc,string $requestId,string $actor):void {
  if(($doc['document_type']??'')!=='internal_note_attachment'||($doc['visibility']??'')!=='confidential'||($doc['status']??'')!=='active')throw new InvalidArgumentException('پیوست از نوع یادداشت داخلی نیست.');
  if(($doc['created_by_user_id']??'')!==$actor)throw new InvalidArgumentException('این پیوست توسط کاربر دیگری بارگذاری شده است.');
  $links=is_array($doc['links']??null)?$doc['links']:[];
  if(count($links)!==1||($links[0]['entity_type']??'')!=='loan_request'||($links[0]['entity_id']??'')!==$requestId||($links[0]['relation_code']??'')!=='internal_note')throw new InvalidArgumentException('پیوست متعلق به همین پرونده نیست.');
 }
}
