<?php
declare(strict_types=1);
namespace Babanoell\Documents\Service;
use Babanoell\Documents\Domain\ApiException;
use Babanoell\Documents\Domain\DocumentCatalog;
use Babanoell\Documents\Domain\InternalNotePolicy;
use Babanoell\Documents\Domain\DocumentReview;
use Babanoell\Documents\Domain\UploadValidator;
use Babanoell\Documents\Repository\PdoDocumentRepository;
use Babanoell\Documents\Security\Principal;
use Babanoell\Documents\Storage\PrivateStorage;
use RuntimeException;
use Throwable;

final class DocumentService
{
 public function __construct(private readonly PdoDocumentRepository$repo,private readonly PrivateStorage$storage,private readonly UploadValidator$validator,private readonly EntityAccessService$access){}
 public function upload(Principal$p,string$bearer,string$entityType,string$entityId,array$file,array$input,?string$ip,?string$ua):array
 {
  $type=strtolower(trim((string)($input['document_type']??'')));
  $entityType=strtolower(trim($entityType));
  $canUploadReceipt=$entityType==='settlement_cycle'&&$type==='settlement_receipt'&&$p->can('finance.receipts.manage');
  $canUploadContract=$entityType==='contract'&&$type==='signed_contract'&&($p->can('loans.requests.contract')||$p->can('partners.cooperation.manage'));
  if(!$p->can('documents.upload')&&!$canUploadReceipt&&!$canUploadContract)throw new ApiException(403,'permission_denied','مجوز بارگذاری مدارک برای این حساب فعال نیست.');
  $entity=$this->access->assert($entityType,$entityId,$bearer,$p);
  if($entityType==='contract'){
   if($type!=='signed_contract'||($entity['mode']??'')!=='physical'||!in_array($entity['status']??'',['pending','sent','viewed'],true))throw new ApiException(422,'physical_contract_upload_denied','فایل امضاشده فقط به نسخهٔ فیزیکی در انتظار امضا اضافه می‌شود.');
   if(!$p->can('documents.upload')&&!$p->can(($entity['subject_type']??'')==='cooperation_request'?'partners.cooperation.manage':'loans.requests.contract'))throw new ApiException(403,'permission_denied','بارگذاری برای این نوع قرارداد مجاز نیست.');
  }
  $catalog=DocumentCatalog::get($type);
  $isGuarantorDocument=str_starts_with($type,'guarantor_');
  if($isGuarantorDocument&&$entityType!=='loan_guarantor')throw new ApiException(422,'guarantor_document_entity_required','مدارک ضامن باید مستقیماً به شناسه همان ضامن متصل شوند.');
  if($entityType==='loan_guarantor'&&!$isGuarantorDocument)throw new ApiException(422,'guarantor_document_type_required','برای رکورد ضامن فقط انواع مدارک مخصوص ضامن قابل ثبت است.');
  $isCooperationDocument=str_starts_with($type,'cooperation_');
  if($isCooperationDocument&&$entityType!=='cooperation_request')throw new ApiException(422,'cooperation_document_entity_required','مدارک همکاری باید مستقیماً به درخواست همکاری مربوط متصل شوند.');
  if($entityType==='cooperation_request'&&!$isCooperationDocument)throw new ApiException(422,'cooperation_document_type_required','برای درخواست همکاری فقط انواع مدارک همکاری قابل ثبت است.');
  if($entityType==='cooperation_request'){
   $rules=\Babanoell\Documents\Domain\CooperationDocumentPolicy::fromOwner($entity);$visible=array_values(array_filter($rules,static fn(array$r):bool=>$r['code']===$type&&$r['visible']));
   if($visible===[])throw new ApiException(422,'cooperation_document_hidden','این مدرک در نسخه فرم درخواست فعال نیست.');
  }
  $requestedVisibility=strtolower(trim((string)($input['visibility']??$catalog['visibility'])));
  if(!in_array($requestedVisibility,['normal','confidential'],true))throw new ApiException(422,'validation_failed','سطح محرمانگی مدرک معتبر نیست.');
  // Catalog confidentiality is a security floor: a confidential document can never be downgraded by form/API input.
  $visibility=$catalog['visibility']==='confidential'?'confidential':$requestedVisibility;

  $owner=trim((string)($input['owner_user_id']??''));
  if($owner==='')$owner=$p->userId;
  if(!preg_match('/^[a-f0-9]{26}$/',$owner))throw new ApiException(422,'validation_failed','شناسه مالک مدرک معتبر نیست.');
  if($owner!==$p->userId&&!$p->isSuperAdmin()&&!$p->can('documents.manage'))throw new ApiException(403,'owner_override_denied','تعیین مالک متفاوت برای این حساب مجاز نیست.');

  $relation=strtolower(trim((string)($input['relation_code']??'attachment')));
  if($relation==='')$relation='attachment';
  if(!preg_match('/^[a-z0-9][a-z0-9_.-]{1,63}$/',$relation))throw new ApiException(422,'validation_failed','نوع ارتباط مدرک با رکورد معتبر نیست.');
  InternalNotePolicy::assertUpload($p,$entityType,$type,$relation);

  $validated=$this->validator->validate($file,true);$key=$this->storage->store($validated);
  try{return$this->repo->create($p->userId,$type,$catalog['category'],$catalog['label'],$visibility,$owner,$entityType,$entityId,$relation,$validated,$key,$ip,$ua);}catch(Throwable$e){$this->storage->remove($key);throw$e;}
 }
 public function replace(Principal$p,string$bearer,string$id,array$file,?string$ip,?string$ua):array
 {
  if(!$p->can('documents.replace'))throw new ApiException(403,'permission_denied','مجوز جایگزینی فایل برای این حساب فعال نیست.');$doc=$this->requireAccessible($p,$bearer,$id);if(in_array((string)($doc['document_type']??''),['signed_contract','signed_contract_pdf','internal_note_attachment'],true))throw new ApiException(409,'signed_artifact_immutable','نسخه سند محافظت‌شده قابل جایگزینی نیست.');if(($doc['status']??'')!=='active')throw new ApiException(409,'document_archived','مدرک بایگانی‌شده قابل جایگزینی نیست.');
  $validated=$this->validator->validate($file,true);$key=$this->storage->store($validated);try{return$this->repo->replace($id,$p->userId,$validated,$key,$ip,$ua);}catch(Throwable$e){$this->storage->remove($key);throw$e;}
 }
 public function review(Principal$p,string$bearer,string$id,string$status,string$reason,?string$ip,?string$ua):array
 {
  if(!$p->can('documents.review'))throw new ApiException(403,'permission_denied','مجوز بررسی مدارک برای این حساب فعال نیست.');$doc=$this->requireAccessible($p,$bearer,$id);if(in_array((string)($doc['document_type']??''),['signed_contract','signed_contract_pdf','internal_note_attachment'],true))throw new ApiException(409,'signed_artifact_immutable','وضعیت سند محافظت‌شده قابل بازبینی نیست.');[$status,$reason]=DocumentReview::validate($status,$reason);return$this->repo->review($id,$p->userId,$status,$reason,$ip,$ua);
 }
 public function archive(Principal$p,string$bearer,string$id,string$reason,?string$ip,?string$ua):array
 {
  if(!$p->can('documents.archive'))throw new ApiException(403,'permission_denied','مجوز بایگانی مدارک برای این حساب فعال نیست.');$doc=$this->requireAccessible($p,$bearer,$id);if(in_array((string)($doc['document_type']??''),['signed_contract','signed_contract_pdf','internal_note_attachment'],true))throw new ApiException(409,'signed_artifact_immutable','سند محافظت‌شده قابل بایگانی نیست.');$reason=trim($reason);if($reason==='')throw new ApiException(422,'validation_failed','برای بایگانی مدرک ثبت دلیل الزامی است.');return$this->repo->archive($id,$p->userId,$reason,$ip,$ua);
 }
 public function requireAccessible(Principal$p,string$bearer,string$id):array
 {
  $doc=$this->repo->get($id);if($doc===null)throw new ApiException(404,'document_not_found','مدرک پیدا نشد.');InternalNotePolicy::assertRead($p,$doc);if(($doc['status']??'active')==='archived'&&!$p->isSuperAdmin()&&!$p->can('documents.manage'))throw new ApiException(403,'document_archived','مدرک بایگانی‌شده فقط برای مدیر مجاز قابل دسترسی است.');$links=is_array($doc['links']??null)?$doc['links']:[];if($links===[])throw new ApiException(409,'document_unlinked','مدرک به رکورد عملیاتی متصل نیست.');foreach($links as$link){if(!is_array($link))continue;try{$this->access->assert((string)$link['entity_type'],(string)$link['entity_id'],$bearer,$p);return$doc;}catch(ApiException$e){if($e->status>=500)throw$e;}}
  throw new ApiException(403,'document_access_denied','دسترسی به این مدرک مجاز نیست.');
 }
 public function fileForRead(Principal$p,string$bearer,string$id,?string$versionId=null):array
 {
  if(!$p->can('documents.download'))throw new ApiException(403,'permission_denied','مجوز دریافت مدارک برای این حساب فعال نیست.');$doc=$this->requireAccessible($p,$bearer,$id);$version=$versionId!==null&&$versionId!==''?$this->repo->versionById($id,$versionId):$this->repo->currentVersion($id);if($version===null)throw new ApiException(404,'document_version_not_found','نسخه فایل پیدا نشد.');
  if(($doc['visibility']??'normal')==='confidential'&&($doc['document_type']??'')!=='internal_note_attachment'&&!$p->isSuperAdmin()&&!$p->can('documents.review')&&!$p->can('documents.manage')&&(string)($doc['owner_user_id']??'')!==$p->userId&&(string)($doc['created_by_user_id']??'')!==$p->userId)throw new ApiException(403,'confidential_document_denied','دسترسی به این مدرک محرمانه مجاز نیست.');
  $path=$this->storage->path((string)$version['storage_key']);$hash=hash_file('sha256',$path);if(!is_string($hash)||!hash_equals((string)$version['checksum_sha256'],$hash))throw new ApiException(503,'document_integrity_failed','صحت فایل ذخیره‌شده تأیید نشد.');return['document'=>$doc,'version'=>$version,'path'=>$path];
 }
 public function completion(Principal$p,string$bearer,string$requestId):array
 {
  $this->access->assert('loan_request',$requestId,$bearer,$p);
  $req=$this->access->requirements($requestId,$bearer);
  $types=is_array($req['required_documents']??null)?$req['required_documents']:[];
  $types=array_values(array_unique(array_filter(array_map(static function($v){if(is_string($v))return strtolower(trim($v));if(is_array($v)&&is_string($v['code']??null))return strtolower(trim((string)$v['code']));return'';},$types))));
  $requestTypes=array_values(array_filter($types,static fn(string$type):bool=>!str_starts_with($type,'guarantor_')));
  $guarantorTypes=array_values(array_filter($types,static fn(string$type):bool=>str_starts_with($type,'guarantor_')));
  $result=$this->repo->completion('loan_request',$requestId,$requestTypes);
  $guarantors=is_array($req['guarantors']??null)?array_values(array_filter($req['guarantors'],static fn($v):bool=>is_array($v)&&is_string($v['id']??null)&&($v['status']??'active')==='active')):[];
  $guarantorResults=[];$gRequired=0;$gApproved=0;$gMissing=[];$gPending=[];$gRejected=[];
  if($guarantorTypes!==[]&&$guarantors===[]){$gRequired=count($guarantorTypes);$gMissing=$guarantorTypes;}
  foreach($guarantors as$g){
   $gid=(string)$g['id'];$gr=$this->repo->completion('loan_guarantor',$gid,$guarantorTypes);$gr['guarantor_id']=$gid;$gr['position_no']=(int)($g['position_no']??0);$gr['full_name']=(string)($g['full_name']??'');$guarantorResults[]=$gr;
   $gRequired+=(int)$gr['required_count'];$gApproved+=(int)$gr['approved_count'];$gMissing=array_merge($gMissing,$gr['missing']);$gPending=array_merge($gPending,$gr['pending']);$gRejected=array_merge($gRejected,$gr['rejected']);
  }
  $result['required_count']+=(int)$gRequired;$result['approved_count']+=(int)$gApproved;$result['missing']=array_values(array_unique(array_merge($result['missing'],$gMissing)));$result['pending']=array_values(array_unique(array_merge($result['pending'],$gPending)));$result['rejected']=array_values(array_unique(array_merge($result['rejected'],$gRejected)));
  $result['guarantors']=$guarantorResults;$result['guarantor_documents_required']=$guarantorTypes;$result['complete']=$result['approved_count']===$result['required_count']&&$result['missing']===[]&&$result['pending']===[]&&$result['rejected']===[];$result['requirements']=$req;return$result;
 }
 public function cooperationCompletion(Principal$p,string$bearer,string$id):array
 {
  $request=$this->access->assert('cooperation_request',$id,$bearer,$p);
  if(($request['id']??null)!==$id)throw new ApiException(503,'partners_response_invalid','درخواست همکاری از سرویس مالک قابل تأیید نیست.');
  $policy=\Babanoell\Documents\Domain\CooperationDocumentPolicy::fromOwner($request);
  $required=array_column(array_values(array_filter($policy,static fn(array$f):bool=>$f['visible']&&$f['required'])),'code');
  return$this->repo->completion('cooperation_request',$id,$required)+['request_id'=>$id,'definition_revision'=>(int)($request['data']['_form']['revision']??0),'document_policy'=>$policy];
 }
}
