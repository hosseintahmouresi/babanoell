<?php
declare(strict_types=1);
namespace Babanoell\Partners\Service;
use Babanoell\Partners\Domain\PartnerRules;
use Babanoell\Partners\Http\LoansClient;
use Babanoell\Partners\Repository\PdoPartnerRepository;
use InvalidArgumentException;
use RuntimeException;
final class PartnerService
{
 public function __construct(private readonly PdoPartnerRepository$repo,private readonly LoansClient$loans,private readonly ?\Babanoell\Partners\Http\DocumentsClient$documents=null,private readonly ?\Babanoell\Partners\Http\IdentityClient$identity=null){}
 public function importLoansShadow(string$actor,string$bearer):array
 {
  $summary=[];$entities=['merchants','memberships','plan_permissions','cooperation_requests','cooperation_documents'];
  foreach($entities as$entity){$page=1;$imported=0;$source=0;do{$r=$this->loans->exportPartnerShadow($entity,$page,200,$bearer);if(!$r->ok())throw new RuntimeException('خواندن داده‌های قبلی '.$entity.' از سرویس تسهیلات ناموفق بود.');$data=is_array($r->json['data']??null)?$r->json['data']:[];$items=is_array($data['items']??null)?$data['items']:[];$source+=count($items);$imported+=$this->repo->importLoansShadowBatch($entity,$items,$actor);$pages=max(1,(int)($data['pages']??1));$page++;}while($page<=$pages);$summary[$entity]=['source'=>$source,'imported'=>$imported];}
  $this->repo->event('migration',null,'partners.shadow_import.completed',$actor,'allowed',$summary);return$summary;
 }
 public function createMerchant(string$actor,array$input,string$bearer):array{$d=$this->merchantInput($input,true);$merchant=$this->repo->createMerchant($actor,$d);$sync=$this->loans->syncMerchant($merchant,$bearer);return['merchant'=>$merchant,'compatibility_sync'=>$this->syncSummary($sync)];}
 public function updateMerchant(string$id,string$actor,array$input,string$bearer):array{$id=PartnerRules::id($id,'شناسه پذیرنده');if(($input['status']??null)==='active'){$pending=$this->repo->pdo()->prepare("SELECT 1 FROM cooperation_activations WHERE merchant_id=:id AND status='pending'");$pending->execute(['id'=>$id]);if($pending->fetchColumn()!==false)throw new RuntimeException('cooperation_activation_pending');}$d=$this->merchantInput($input,false);$expected=isset($input['row_version'])?(int)$input['row_version']:null;$merchant=$this->repo->updateMerchant($id,$actor,$d,$expected);$sync=$this->loans->syncMerchant($merchant,$bearer);return['merchant'=>$merchant,'compatibility_sync'=>$this->syncSummary($sync)];}
 public function setMembership(string$merchantId,string$actor,array$input):array{$merchantId=PartnerRules::id($merchantId,'شناسه پذیرنده');if($this->repo->getMerchant($merchantId)===null)throw new InvalidArgumentException('پذیرنده پیدا نشد.');$user=PartnerRules::userId((string)($input['user_id']??''));$role=strtolower(trim((string)($input['membership_role']??'operator')));if(!in_array($role,['owner','manager','operator','finance','viewer'],true))throw new InvalidArgumentException('نقش عضویت پذیرنده معتبر نیست.');$status=strtolower(trim((string)($input['status']??'active')));if(!in_array($status,['active','inactive','revoked'],true))throw new InvalidArgumentException('وضعیت عضویت معتبر نیست.');$primary=filter_var($input['is_primary']??false,FILTER_VALIDATE_BOOL);return$this->repo->setMembership($merchantId,$user,$role,$status,$primary,$actor);}
 public function setPlanPermission(string$merchantId,string$planId,string$actor,array$input,string$bearer):array{$merchantId=PartnerRules::id($merchantId,'شناسه پذیرنده');$planId=PartnerRules::id($planId,'شناسه طرح');if($this->repo->getMerchant($merchantId)===null)throw new InvalidArgumentException('پذیرنده پیدا نشد.');$status=strtolower(trim((string)($input['status']??'active')));if(!in_array($status,['active','inactive'],true))throw new InvalidArgumentException('وضعیت دسترسی طرح معتبر نیست.');$limits=is_array($input['limits']??null)?$input['limits']:[];$permission=$this->repo->setPlanPermission($merchantId,$planId,$status,$limits,$actor);$sync=$this->loans->syncPlanPermission($merchantId,$planId,$status,$limits,$bearer);return['permission'=>$permission,'compatibility_sync'=>$this->syncSummary($sync)];}
 public function cooperationForm():array{return(new \Babanoell\Partners\Repository\CooperationFormRepository($this->repo->pdo()))->current()+['catalog'=>\Babanoell\Partners\Domain\CooperationFormDefinition::catalog()];}
 public function publishCooperationForm(string$actor,array$input):array
 {
  $revision=filter_var($input['expected_revision']??null,FILTER_VALIDATE_INT);if($revision===false||$revision<1)throw new InvalidArgumentException('نسخه تنظیمات معتبر نیست.');
  return(new \Babanoell\Partners\Repository\CooperationFormRepository($this->repo->pdo()))->save($actor,$revision,$input)+['catalog'=>\Babanoell\Partners\Domain\CooperationFormDefinition::catalog()];
 }
 public function createCooperation(string$actor,array$input,string$idempotencyKey):array
 {
  $type=strtolower(trim((string)($input['request_type']??'individual')));if(!in_array($type,['individual','legal'],true))throw new InvalidArgumentException('نوع درخواست همکاری معتبر نیست.');
  $data=is_array($input['data']??null)?$input['data']:$input;
  $applicant=$input['applicant_user_id']??$actor;if(!is_string($applicant))throw new InvalidArgumentException('شناسه متقاضی معتبر نیست.');$applicant=trim($applicant);if($applicant==='')$applicant=$actor;PartnerRules::userId($applicant);
  $fingerprint=hash('sha256',\Babanoell\Partners\Domain\CooperationFormDefinition::fingerprint($type,$data).'|'.$applicant);
  $previous=$this->repo->findCooperationByIdempotency($actor,$idempotencyKey);
  if($previous!==null){if(!is_string($previous['data']['_submission_hash']??null)||!hash_equals($previous['data']['_submission_hash'],$fingerprint))throw new RuntimeException('cooperation_idempotency_conflict');return$previous;}
  $definition=$this->cooperationForm();unset($definition['catalog']);
  if(isset($input['form_revision'])&&(filter_var($input['form_revision'],FILTER_VALIDATE_INT)===false||(int)$input['form_revision']!==(int)$definition['revision']))throw new RuntimeException('form_revision_conflict');
  $data=\Babanoell\Partners\Domain\CooperationFormDefinition::validate($type,$data,$definition);$data['_form']=$definition;$data['_submission_hash']=$fingerprint;
  try{return$this->repo->createConfiguredCooperation($actor,['request_type'=>$type,'applicant_user_id'=>$applicant,'data'=>$data],$idempotencyKey);}
  catch(\PDOException$e){if($e->getCode()==='23000'&&$idempotencyKey!==''){$old=$this->repo->findCooperationByIdempotency($actor,$idempotencyKey);if($old!==null){if(!hash_equals((string)($old['data']['_submission_hash']??''),$fingerprint))throw new RuntimeException('cooperation_idempotency_conflict');return$old;}}throw$e;}
 }
 private function assertCooperationEvidence(array$request,string$target,string$bearer):array
 {
  if(!in_array($target,['submitted','under_review','docs_verified','contracting','approved'],true))return [];
  if(isset($request['data']['_form']))\Babanoell\Partners\Domain\CooperationFormDefinition::validate((string)$request['request_type'],$request['data'],$request['data']['_form']);
  $required=array_values(array_filter(\Babanoell\Partners\Domain\CooperationFormDefinition::documentPolicy($request),static fn(array$f):bool=>$f['required']));if($required===[])return ['complete'=>true,'required_count'=>0,'items'=>[],'definition_revision'=>$request['data']['_form']['revision']??null];
  if($this->documents===null)throw new RuntimeException('cooperation_documents_unavailable');
  $r=$this->documents->cooperationCompletion((string)$request['id'],$bearer);if(!$r->ok())throw new RuntimeException('cooperation_documents_unavailable');
  $data=$r->json['data']??null;if(!is_array($data)||!is_bool($data['complete']??null)||!is_array($data['missing']??null))throw new RuntimeException('cooperation_documents_unavailable');
  if($data['missing']!==[]||(in_array($target,['docs_verified','contracting','approved'],true)&&!$data['complete']))throw new InvalidArgumentException(in_array($target,['docs_verified','contracting','approved'],true)?'همه مدارک الزامی باید تأیید شوند.':'ابتدا مدارک الزامی فرم را بارگذاری کنید.');
  return $data;
 }
 public function activationService():CooperationActivationService
 {
  if($this->identity===null)throw new RuntimeException('cooperation_activation_not_configured');
  return new CooperationActivationService($this->repo,$this->identity,$this->loans);
 }
 public function updateCooperation(string$id,string$actor,array$input):array
 {
  $pdo=$this->repo->pdo();$pdo->beginTransaction();
  try{
   $s=$pdo->prepare('SELECT id FROM cooperation_requests WHERE id=:id FOR UPDATE');$s->execute(['id'=>$id]);
   $current=$this->repo->getCooperation($id);if($current===null)throw new InvalidArgumentException('درخواست همکاری پیدا نشد.');
   if(!in_array($current['status'],['submitted','need_correction'],true))throw new RuntimeException('cooperation_edit_locked');
   $version=filter_var($input['row_version']??null,FILTER_VALIDATE_INT);if($version===false||$version!==(int)$current['row_version'])throw new RuntimeException('cooperation_revision_conflict');
   $s=$pdo->prepare("SELECT id FROM contracts WHERE subject_type='cooperation_request' AND subject_id=:id LIMIT 1 FOR UPDATE");$s->execute(['id'=>$id]);
   if($s->fetchColumn()!==false)throw new RuntimeException('cooperation_contract_locked');
   $definition=$current['data']['_form']??null;if(!is_array($definition))throw new RuntimeException('cooperation_definition_invalid');
   $data=\Babanoell\Partners\Domain\CooperationFormDefinition::validate($current['request_type'],is_array($input['data']??null)?$input['data']:[],$definition);
   $data['_form']=$definition;$data['_submission_hash']=$current['data']['_submission_hash'];
   $s=$pdo->prepare('UPDATE cooperation_requests SET data_json=:data,row_version=row_version+1 WHERE id=:id AND row_version=:version');$s->execute(['data'=>\Babanoell\Partners\Support\Json::encode($data),'id'=>$id,'version'=>$version]);
   if($s->rowCount()!==1)throw new RuntimeException('cooperation_revision_conflict');
   $this->repo->event('cooperation_request',$id,'cooperation.profile.updated',$actor,'allowed',['row_version_before'=>$version,'row_version_after'=>$version+1]);
   $pdo->commit();return $this->repo->getCooperation($id);
  }catch(\Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
 }
 public function retryCooperationActivation(string$id,string$actor,string$bearer):array
 {
  $current=$this->repo->getCooperation($id);if($current===null||$current['status']!=='approved')throw new RuntimeException('cooperation_activation_missing');
  $this->assertCooperationEvidence($current,'approved',$bearer);return $this->activationService()->activate($id,$actor,$bearer);
 }
 public function transitionCooperation(string$id,string$actor,array$input,string$bearer):array
 {
  $id=PartnerRules::id($id,'شناسه درخواست همکاری');$current=$this->repo->getCooperation($id);
  if($current===null)throw new InvalidArgumentException('درخواست همکاری پیدا نشد.');
  $to=PartnerRules::cooperationStatus((string)($input['status']??''));
  if($to==='approved'&&$current['status']==='approved')return $this->activationService()->approve($id,$actor,$input,$bearer,$this->assertCooperationEvidence($current,'approved',$bearer));
  PartnerRules::assertTransition((string)$current['status'],$to);
  $note=trim((string)($input['note']??''));if(in_array($to,['need_correction','rejected'],true)&&$note==='')throw new InvalidArgumentException('ثبت توضیح برای این وضعیت الزامی است.');
  if(isset($input['row_version'])&&(int)$input['row_version']!==(int)$current['row_version'])throw new RuntimeException('cooperation_revision_conflict');
  $evidence=$this->assertCooperationEvidence($current,$to,$bearer);
  if($to==='approved')return $this->activationService()->approve($id,$actor,$input,$bearer,$evidence);
  $assigned=trim((string)($input['assigned_user_id']??''));if($assigned!=='')PartnerRules::userId($assigned);
  $updated=$this->repo->transitionCooperation($id,$actor,$to,$note,$assigned!==''?$assigned:null,(int)$current['row_version']);
  return ['request'=>$updated,'created_merchant'=>null,'compatibility_sync'=>null,'activation'=>null];
 }
 public function marketplace(string$bearer,array$query):array{$r=$this->loans->marketplace($bearer,$query);if(!$r->ok())throw new RuntimeException($r->status===0?'سرویس تسهیلات برای بازار درخواست‌ها در دسترس نیست.':'دریافت بازار درخواست‌ها از سرویس تسهیلات ناموفق بود.');$data=$r->json['data']??[];return is_array($data)?$data:[];}
 public function claimMarketplace(string$requestId,string$userId,string$bearer):array{$merchant=$this->repo->merchantForUser($userId);if($merchant===null)throw new InvalidArgumentException('برای پذیرش پرونده باید عضویت فعال در یک پذیرنده فعال داشته باشید.');$r=$this->loans->claimMarketplace(PartnerRules::id($requestId,'شناسه پرونده'),$bearer);if(!$r->ok()){if($r->status===409)throw new RuntimeException('marketplace_claim_conflict');throw new RuntimeException('ثبت پذیرش پرونده در سرویس تسهیلات کامل نشد.');}$data=is_array($r->json['data']??null)?$r->json['data']:[];$claim=$this->repo->recordMarketplaceClaim($requestId,(string)$merchant['id'],$userId,['loan_request'=>$data['request']??null]);return['claim'=>$claim,'merchant'=>$merchant,'request'=>$data['request']??null];}
 public function createInvitation(string$merchantId,string$actor,array$input):array{$merchantId=PartnerRules::id($merchantId,'شناسه پذیرنده');if($this->repo->getMerchant($merchantId)===null)throw new InvalidArgumentException('پذیرنده پیدا نشد.');$expires=trim((string)($input['expires_at']??''));if($expires!==''&&strtotime($expires)===false)throw new InvalidArgumentException('تاریخ انقضای دعوت معتبر نیست.');return$this->repo->createInvitation($merchantId,$actor,$expires!==''?date('Y-m-d H:i:s',strtotime($expires)):null);}
 public function redeemInvitation(string$code,string$userId):array{$code=trim($code);if(strlen($code)<8||strlen($code)>100)throw new InvalidArgumentException('کد دعوت معتبر نیست.');$r=$this->repo->redeemInvitation($code,$userId);if($r===null)throw new InvalidArgumentException('کد دعوت معتبر نیست یا منقضی شده است.');return$r;}
 private function merchantInput(array$input,bool$create):array{$out=[];if($create||array_key_exists('code',$input))$out['code']=PartnerRules::merchantCode((string)($input['code']??''));if($create||array_key_exists('name',$input)){$out['name']=trim((string)($input['name']??''));if($out['name']==='')throw new InvalidArgumentException('نام پذیرنده الزامی است.');}$strings=['legal_name','owner_user_id','organization_ref','credit_level','legacy_profile_ref'];foreach($strings as$k)if($create||array_key_exists($k,$input))$out[$k]=trim((string)($input[$k]??''));foreach(['owner_user_id','organization_ref']as$k)if(($out[$k]??'')!=='')PartnerRules::id($out[$k],$k==='owner_user_id'?'شناسه مالک':'شناسه سازمان');if($create||array_key_exists('status',$input))$out['status']=PartnerRules::merchantStatus((string)($input['status']??'active'));if($create||array_key_exists('cooperation_status',$input)){$c=strtolower(trim((string)($input['cooperation_status']??'approved')));if(!in_array($c,['pending','under_review','approved','rejected','suspended','archived'],true))throw new InvalidArgumentException('وضعیت همکاری پذیرنده معتبر نیست.');$out['cooperation_status']=$c;}if($create||array_key_exists('financial_settings',$input))$out['financial_settings']=is_array($input['financial_settings']??null)?$input['financial_settings']:[];if($create||array_key_exists('metadata',$input))$out['metadata']=is_array($input['metadata']??null)?$input['metadata']:[];if($create||array_key_exists('legacy_user_id',$input))$out['legacy_user_id']=max(0,(int)($input['legacy_user_id']??0));if(isset($out['metadata']['marketplace'])){if(!is_array($out['metadata']['marketplace']))throw new InvalidArgumentException('تنظیمات بازار پذیرنده معتبر نیست.');$catalog=(new \Babanoell\Partners\Marketplace\MarketplaceConfiguration($this->repo->pdo()))->current();$out['metadata']['marketplace']=\Babanoell\Partners\Marketplace\MarketplaceConfiguration::profile($out['metadata']['marketplace'],$catalog);}return$out;}
 private function syncSummary($r):array{return['ok'=>$r->ok(),'status'=>$r->status,'error'=>$r->ok()?null:($r->json['error']['code']??$r->error??'compatibility_sync_failed')];}
}
