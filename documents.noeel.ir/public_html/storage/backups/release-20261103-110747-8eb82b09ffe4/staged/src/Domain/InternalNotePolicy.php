<?php
declare(strict_types=1);
namespace Babanoell\Documents\Domain;
use Babanoell\Documents\Security\Principal;
final class InternalNotePolicy {
 public const TYPE='internal_note_attachment';
 public const RELATION='internal_note';
 public static function allowed(Principal $p):bool{return $p->can('loans.requests.internal_notes');}
 public static function assertUpload(Principal $p,string $entity,string $type,string $relation):void {
  if($type===self::TYPE){
   if(!self::allowed($p))throw new ApiException(403,'internal_notes_denied','مجوز پیوست یادداشت داخلی وجود ندارد.');
   if($entity!=='loan_request'||$relation!==self::RELATION)throw new ApiException(422,'internal_note_entity_invalid','پیوست یادداشت باید به پرونده و رابطه مخصوص آن متصل شود.');
  }elseif($relation===self::RELATION)throw new ApiException(422,'internal_note_type_invalid','نوع مدرک برای یادداشت داخلی معتبر نیست.');
 }
 public static function assertRead(Principal $p,array $doc):void {
  if(($doc['document_type']??'')===self::TYPE && !self::allowed($p))throw new ApiException(403,'internal_notes_denied','دسترسی به پیوست یادداشت داخلی مجاز نیست.');
 }
}
