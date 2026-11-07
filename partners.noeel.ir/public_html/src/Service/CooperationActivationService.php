<?php
declare(strict_types=1);
namespace Babanoell\Partners\Service;

use Babanoell\Partners\Domain\PartnerRules;
use Babanoell\Partners\Http\IdentityClient;
use Babanoell\Partners\Http\LoansClient;
use Babanoell\Partners\Repository\PdoPartnerRepository;
use InvalidArgumentException;
use PDO;
use RuntimeException;
use Throwable;

/** Commit local approval atomically, then deliver idempotent activation steps. */
final class CooperationActivationService
{
 public function __construct(private readonly PdoPartnerRepository $repo,private readonly IdentityClient $identity,private readonly LoansClient $loans){}

 public function state(string $id):?array
 {
  $s=$this->repo->pdo()->prepare('SELECT * FROM cooperation_activations WHERE cooperation_id=:id');$s->execute(['id'=>$id]);$row=$s->fetch(PDO::FETCH_ASSOC);
  if(!is_array($row))return null;$row['evidence']=\Babanoell\Partners\Support\Json::decode($row['evidence_json']);unset($row['evidence_json']);return $row;
 }

 private function agreement(string $id,string $owner,bool $lock=false):array
 {
  $s=$this->repo->pdo()->prepare("SELECT * FROM contracts WHERE subject_type='cooperation_request' AND subject_id=:id AND contract_kind='cooperation_agreement' ORDER BY revision DESC LIMIT 1".($lock?' FOR UPDATE':''));$s->execute(['id'=>$id]);$c=$s->fetch(PDO::FETCH_ASSOC);
  if(!is_array($c)||$c['status']!=='signed'||$c['signer_user_id']!==$owner||empty($c['signed_at'])||!preg_match('/^[a-f0-9]{64}$/D',(string)$c['rendered_hash'])||
   !hash_equals($c['rendered_hash'],hash('sha256',(string)$c['rendered_content']))||$c['artifact_status']!=='stored'||empty($c['artifact_document_id'])||empty($c['pdf_document_id'])||($c['mode']==='digital'&&empty($c['otp_verified_at'])))throw new RuntimeException('cooperation_contract_incomplete');
  $request=$this->repo->getCooperation($id);$fingerprint=\Babanoell\Partners\Domain\CooperationFormDefinition::fingerprint($request['request_type'],$request['data']);
  if(!is_string($c['subject_snapshot_hash']??null)||!hash_equals($fingerprint,$c['subject_snapshot_hash']))throw new RuntimeException('cooperation_contract_changed');
  return $c;
 }

 public function approve(string $id,string $actor,array $input,string $bearer,array $evidence):array
 {
  $pdo=$this->repo->pdo();$pdo->beginTransaction();
  try{
   $s=$pdo->prepare('SELECT * FROM cooperation_requests WHERE id=:id FOR UPDATE');$s->execute(['id'=>$id]);$row=$s->fetch(PDO::FETCH_ASSOC);
   if(!is_array($row))throw new InvalidArgumentException('درخواست همکاری پیدا نشد.');
   if($row['status']!=='approved'){
    PartnerRules::assertTransition($row['status'],'approved');
    $version=filter_var($input['row_version']??null,FILTER_VALIDATE_INT);
    if($version===false||$version<1)throw new InvalidArgumentException('نسخه درخواست برای تأیید همکاری الزامی است.');
    if($version!==(int)$row['row_version'])throw new RuntimeException('cooperation_revision_conflict');
    $owner=(string)$row['applicant_user_id'];PartnerRules::userId($owner);
    $contract=$this->agreement($id,$owner,true);$request=$this->repo->getCooperation($id);$data=$request['data'];
    $merchant=$this->repo->createMerchant($actor,[
     'code'=>'CO-'.strtoupper($id),'name'=>mb_substr((string)($data['trade_name']??$data['company_name']??$data['full_name']),0,190),
     'legal_name'=>$data['company_name']??'','owner_user_id'=>$owner,'organization_ref'=>'','status'=>'inactive','cooperation_status'=>'approved',
     'credit_level'=>'','financial_settings'=>[],'metadata'=>['cooperation_request_id'=>$id],'legacy_user_id'=>0,'legacy_profile_ref'=>'',
    ]);
    $this->repo->transitionCooperation($id,$actor,'approved',mb_substr(trim((string)($input['note']??'')),0,4000),null,(int)$row['row_version']);
    $this->repo->linkCooperationMerchant($id,$merchant['id'],$actor);
    $s=$pdo->prepare("UPDATE cooperation_requests SET contract_signed_status='signed',contract_signed_at=:signed WHERE id=:id");$s->execute(['signed'=>$contract['signed_at'],'id'=>$id]);
    $s=$pdo->prepare('INSERT INTO cooperation_activations(cooperation_id,merchant_id,applicant_user_id,contract_id,contract_hash,approved_by_user_id,evidence_json) VALUES(:id,:merchant,:owner,:contract,:hash,:actor,:evidence)');
    $s->execute(['id'=>$id,'merchant'=>$merchant['id'],'owner'=>$owner,'contract'=>$contract['id'],'hash'=>$contract['rendered_hash'],'actor'=>$actor,'evidence'=>\Babanoell\Partners\Support\Json::encode($evidence)]);
    $this->repo->event('cooperation_request',$id,'cooperation.activation.pending',$actor,'allowed',['merchant_id'=>$merchant['id'],'contract_id'=>$contract['id'],'contract_hash'=>$contract['rendered_hash']]);
   }else{
    $activation=$this->state($id);if($activation===null)throw new RuntimeException('cooperation_activation_missing');
    if($activation['status']==='pending'){
     $contract=$this->agreement($id,$activation['applicant_user_id'],true);
     if($contract['id']!==$activation['contract_id']||!hash_equals($contract['rendered_hash'],$activation['contract_hash'])){
      if((int)($input['row_version']??0)!==(int)$row['row_version'])throw new RuntimeException('cooperation_revision_conflict');
      $s=$pdo->prepare('UPDATE cooperation_activations SET contract_id=:contract,contract_hash=:hash,evidence_json=:evidence,approved_by_user_id=:actor,last_error=NULL WHERE cooperation_id=:id');
      $s->execute(['contract'=>$contract['id'],'hash'=>$contract['rendered_hash'],'evidence'=>\Babanoell\Partners\Support\Json::encode($evidence),'actor'=>$actor,'id'=>$id]);
      $s=$pdo->prepare("UPDATE cooperation_requests SET contract_signed_status='signed',contract_signed_at=:signed,decided_at=NOW(6),decided_by_user_id=:actor,row_version=row_version+1 WHERE id=:id");$s->execute(['signed'=>$contract['signed_at'],'actor'=>$actor,'id'=>$id]);
      $this->repo->event('cooperation_request',$id,'cooperation.activation.reapproved',$actor,'allowed',['previous_contract_id'=>$activation['contract_id'],'contract_id'=>$contract['id'],'contract_hash'=>$contract['rendered_hash']]);
     }
    }
   }
   $pdo->commit();
  }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
  return $this->activate($id,$actor,$bearer);
 }

 public function activate(string $id,string $actor,string $bearer):array
 {
  $pdo=$this->repo->pdo();$lock='coop-activation:'.$id;
  $s=$pdo->prepare('SELECT GET_LOCK(:name,0)');$s->execute(['name'=>$lock]);
  if((int)$s->fetchColumn()!==1)return $this->result($id);
  try{
   $activation=$this->state($id);$request=$this->repo->getCooperation($id);
   if($activation===null||$request===null||$request['status']!=='approved')throw new RuntimeException('cooperation_activation_missing');
   if($activation['status']==='active')return $this->result($id);
   $contract=$this->agreement($id,$activation['applicant_user_id']);
   if($contract['id']!==$activation['contract_id']||!hash_equals($contract['rendered_hash'],$activation['contract_hash']))throw new RuntimeException('cooperation_contract_changed');
   $pdo->prepare('UPDATE cooperation_activations SET attempts=attempts+1,last_error=NULL WHERE cooperation_id=:id')->execute(['id'=>$id]);
   $identity=$this->identity->activateAcceptor(['cooperation_id'=>$id,'merchant_id'=>$activation['merchant_id'],'user_id'=>$activation['applicant_user_id']],$bearer);
   $ack=$identity->json['data']['activation']??null;
   if(($identity->json['error']['code']??null)==='partner_account_inactive')return $this->pending($id,'account_inactive',$actor);
   if(!$identity->ok()||!is_array($ack)||($ack['user_id']??null)!==$activation['applicant_user_id']||($ack['merchant_id']??null)!==$activation['merchant_id']||($ack['cooperation_id']??null)!==$id||($ack['role']??null)!=='acceptor')return $this->pending($id,'identity_activation_pending',$actor);
   $pdo->prepare('UPDATE cooperation_activations SET identity_confirmed_at=COALESCE(identity_confirmed_at,NOW(6)) WHERE cooperation_id=:id')->execute(['id'=>$id]);
   $merchant=$this->repo->getMerchant($activation['merchant_id']);
   if($merchant===null||$merchant['status']!=='inactive'||$merchant['owner_user_id']!==$activation['applicant_user_id'])return $this->pending($id,'merchant_activation_conflict',$actor);
   $projection=$merchant;$projection['status']='active';
   $sync=$this->loans->syncMerchant($projection,$bearer);$shadow=$sync->json['data']['merchant']??null;
   if(!$sync->ok()||!is_array($shadow)||($shadow['id']??null)!==$merchant['id']||($shadow['status']??null)!=='active')return $this->pending($id,'loans_activation_pending',$actor);
   // Partners remains inactive until both remote steps have acknowledged the same entity.
   $pdo->beginTransaction();
   try{
    $s=$pdo->prepare('SELECT status FROM cooperation_requests WHERE id=:id FOR UPDATE');$s->execute(['id'=>$id]);
    if($s->fetchColumn()!=='approved')throw new RuntimeException('cooperation_revision_conflict');
    $this->repo->updateMerchant($merchant['id'],$actor,['status'=>'active'],(int)$merchant['row_version']);
    $s=$pdo->prepare("UPDATE cooperation_activations SET status='active',last_error=NULL,loans_confirmed_at=NOW(6),activated_at=NOW(6) WHERE cooperation_id=:id AND status='pending'");$s->execute(['id'=>$id]);
    $this->repo->event('cooperation_request',$id,'cooperation.activation.completed',$actor,'allowed',['merchant_id'=>$merchant['id'],'user_id'=>$activation['applicant_user_id']]);
    $pdo->commit();
   }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
   return $this->result($id);
  }finally{$s=$pdo->prepare('SELECT RELEASE_LOCK(:name)');$s->execute(['name'=>$lock]);}
 }

 private function pending(string $id,string $error,string $actor):array
 {
  $s=$this->repo->pdo()->prepare('UPDATE cooperation_activations SET last_error=:error WHERE cooperation_id=:id');$s->execute(['error'=>$error,'id'=>$id]);
  $this->repo->event('cooperation_request',$id,'cooperation.activation.pending',$actor,'pending',['reason'=>$error]);return $this->result($id);
 }

 private function result(string $id):array
 {
  $state=$this->state($id);return ['request'=>$this->repo->getCooperation($id),'created_merchant'=>$state!==null?$this->repo->getMerchant($state['merchant_id']):null,
   'activation'=>$state,'compatibility_sync'=>['ok'=>($state['status']??null)==='active','status'=>($state['status']??null)==='active'?200:503,'error'=>$state['last_error']??null]];
 }
}
