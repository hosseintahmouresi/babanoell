<?php
declare(strict_types=1);
namespace Babanoell\Finance\Service;

use Babanoell\Finance\Http\IntegrationsClient;
use Babanoell\Finance\Repository\PdoFinanceRepository;
use Babanoell\Finance\Support\RandomId;
use InvalidArgumentException;
use PDO;
use PDOException;
use RuntimeException;
use Throwable;

/** Finance-only, irreversible dispatch state machine. No network calls within SQL transactions. */
final class GreenpayTransferService {
 public function __construct(
  private readonly PDO $pdo,
  private readonly PdoFinanceRepository $events,
  private readonly IntegrationsClient $client,
  private readonly string $encryptionKey,
  private readonly bool $transfersEnabled=false
 ) {}
 public static function encryptionKeyFromEnvironment():string {
  $hex=trim((string)\babanoell_finance_env('FINANCE_GREENPAY_ENCRYPTION_KEY',''));
  if(!preg_match('/^[0-9a-f]{64}$/iD',$hex))return '';
  return hex2bin($hex)?:'';
 }
 public static function sameRialAmount(string $decimal,int $rial):bool {
  // Compare fixed-point decimal values exactly, without floats or optional BCMath.
  if(!preg_match('/^([0-9]+)(?:\.([0-9]{1,2}))?$/D',$decimal,$m))return false;
  $whole=ltrim($m[1],'0');if($whole==='')$whole='0';
  return $whole===(string)$rial && (int)($m[2]??'0')===0;
 }
 private function requireStorageKey():void {
  if(!function_exists('sodium_crypto_secretbox') || strlen($this->encryptionKey)!==SODIUM_CRYPTO_SECRETBOX_KEYBYTES)throw new RuntimeException('greenpay_encryption_not_configured');
 }
 private function box(string $pocket):string {
  $nonce=random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
  return base64_encode($nonce.sodium_crypto_secretbox($pocket,$nonce,$this->encryptionKey));
 }
 private function unbox(string $data):string {
  $binary=base64_decode($data,true);
  if(!is_string($binary)||strlen($binary)<SODIUM_CRYPTO_SECRETBOX_NONCEBYTES+SODIUM_CRYPTO_SECRETBOX_MACBYTES)throw new RuntimeException('greenpay_encrypted_data_invalid');
  $nonce=substr($binary,0,SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
  $plaintext=sodium_crypto_secretbox_open(substr($binary,SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),$nonce,$this->encryptionKey);
  if(!is_string($plaintext))throw new RuntimeException('greenpay_encrypted_data_invalid');return $plaintext;
 }
 private function validateId(string $id):void {if(preg_match('/^[a-f0-9]{26}$/D',$id)!==1)throw new InvalidArgumentException('شناسه معتبر نیست.');}
 private function event(string $id,string $actor,string $action,?string $old,string $new,string $note=''):void {
  $q=$this->pdo->prepare('INSERT INTO finance_greenpay_transfer_events(id,transfer_id,actor_user_id,action,previous_status,new_status,note) VALUES(:id,:tid,:actor,:action,:old,:new,:note)');
  $q->execute([':id'=>RandomId::make(),':tid'=>$id,':actor'=>$actor,':action'=>$action,':old'=>$old,':new'=>$new,':note'=>$note!==''?$note:null]);
 }
 private function row(string $id,bool $locked=false):array {
  $this->validateId($id);$q=$this->pdo->prepare('SELECT * FROM finance_greenpay_transfers WHERE id=:id'.($locked?' FOR UPDATE':''));$q->execute([':id'=>$id]);$row=$q->fetch(PDO::FETCH_ASSOC);
  if(!is_array($row))throw new RuntimeException('greenpay_transfer_not_found');return $row;
 }
 private function safe(array $r):array {
  foreach(['from_pocket_encrypted','to_pocket_encrypted','request_hash'] as $k)unset($r[$k]);return $r;
 }
 public function get(string $id):array{return $this->safe($this->row($id));}
 public function list(int $page=1,int $perPage=25,string $status=''):array {
  $page=max(1,$page);$perPage=min(100,max(1,$perPage));
  if($status!==''&&!in_array($status,['prepared','approved','dispatching','provider_accepted','uncertain','reconciled_paid','reconciled_unpaid','cancelled'],true))throw new InvalidArgumentException('وضعیت نامعتبر است.');
  $sql='SELECT id,cycle_id,created_by,approved_by,reviewed_by,external_reference,from_pocket_mask,to_pocket_mask,amount_rial,status,reason,decision_note,provider_error,created_at,approved_at,dispatch_started_at,provider_accepted_at,reviewed_at FROM finance_greenpay_transfers';
  $where=$status!==''?' WHERE status=:status':'';
  $q=$this->pdo->prepare($sql.$where.' ORDER BY created_at DESC,id DESC LIMIT :lim OFFSET :off');
  if($status!=='')$q->bindValue(':status',$status);$q->bindValue(':lim',$perPage,PDO::PARAM_INT);$q->bindValue(':off',($page-1)*$perPage,PDO::PARAM_INT);$q->execute();
  return ['items'=>$q->fetchAll(PDO::FETCH_ASSOC),'page'=>$page,'per_page'=>$perPage];
 }
 public function prepare(string $actor,array $b,string $key):array {
  $this->requireStorageKey();$cycle=trim((string)($b['cycle_id']??''));$this->validateId($cycle);
  $from=trim((string)($b['from_pocket']??''));$to=trim((string)($b['to_pocket']??''));
  if(!preg_match('/^[0-9]{8,32}$/D',$from)||!preg_match('/^[0-9]{8,32}$/D',$to)||$from===$to)throw new InvalidArgumentException('شماره کیف پول معتبر نیست.');
  $amount=filter_var($b['amount_rial']??null,FILTER_VALIDATE_INT);
  if($amount===false||$amount<1||$amount>100000000000000)throw new InvalidArgumentException('مبلغ انتقال معتبر نیست.');
  $reason=trim((string)($b['reason']??''));if(strlen($reason)<10||strlen($reason)>1000)throw new InvalidArgumentException('دلیل عملیات باید بین ۱۰ تا ۱۰۰۰ کاراکتر باشد.');
  if(!preg_match('/^[A-Za-z0-9._:-]{16,128}$/D',$key))throw new InvalidArgumentException('کلید یکتای درخواست معتبر نیست.');
  $fingerprint=hash_hmac('sha256',json_encode([$cycle,$from,$to,$amount,$reason],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),$this->encryptionKey);
  $this->pdo->beginTransaction();
  try {
   $q=$this->pdo->prepare('SELECT * FROM finance_greenpay_transfers WHERE created_by=:actor AND idempotency_key=:key FOR UPDATE');$q->execute([':actor'=>$actor,':key'=>$key]);$same=$q->fetch(PDO::FETCH_ASSOC);
   if(is_array($same)){
    if(!hash_equals((string)$same['request_hash'],$fingerprint))throw new RuntimeException('greenpay_idempotency_conflict');
    $this->pdo->commit();return $this->safe($same);
   }
   $q=$this->pdo->prepare('SELECT status,merchant_total FROM settlement_cycles WHERE id=:id FOR UPDATE');$q->execute([':id'=>$cycle]);$c=$q->fetch(PDO::FETCH_ASSOC);
   if(!is_array($c)||$c['status']!=='closed')throw new RuntimeException('greenpay_cycle_must_be_closed');
   if(!is_numeric($c['merchant_total'])||!self::sameRialAmount((string)$c['merchant_total'],$amount))throw new RuntimeException('greenpay_amount_mismatch');
   $q=$this->pdo->prepare('SELECT COUNT(*) FROM finance_reconciliation_items WHERE cycle_id=:id AND status=\'open\'');$q->execute([':id'=>$cycle]);
   if((int)$q->fetchColumn()!==0)throw new RuntimeException('greenpay_cycle_has_discrepancies');
   $id=RandomId::make();$ref='N'.$id;
   $sql="INSERT INTO finance_greenpay_transfers(id,cycle_id,created_by,idempotency_key,request_hash,external_reference,from_pocket_encrypted,to_pocket_encrypted,from_pocket_mask,to_pocket_mask,amount_rial,status,reason) VALUES(:id,:cycle,:actor,:ikey,:fp,:ref,:from,:to,:frommask,:tomask,:amount,'prepared',:reason)";
   $q=$this->pdo->prepare($sql);
   $q->execute([':id'=>$id,':cycle'=>$cycle,':actor'=>$actor,':ikey'=>$key,':fp'=>$fingerprint,':ref'=>$ref,':from'=>$this->box($from),':to'=>$this->box($to),':frommask'=>'***'.substr($from,-4),':tomask'=>'***'.substr($to,-4),':amount'=>$amount,':reason'=>$reason]);
   $this->event($id,$actor,'prepared',null,'prepared',$reason);$this->pdo->commit();return $this->get($id);
  }catch(PDOException $e){if($this->pdo->inTransaction())$this->pdo->rollBack();if((string)$e->getCode()==='23000')throw new RuntimeException('greenpay_cycle_already_has_transfer',0,$e);throw $e;}
   catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
 }
 public function approve(string $id,string $actor,string $note):array {
  $note=trim($note);if(strlen($note)<10||strlen($note)>1000)throw new InvalidArgumentException('توضیح تأیید باید بین ۱۰ تا ۱۰۰۰ کاراکتر باشد.');
  $this->pdo->beginTransaction();try{
   $r=$this->row($id,true);if($r['status']!=='prepared')throw new RuntimeException('greenpay_transfer_not_prepared');
   if($r['created_by']===$actor)throw new RuntimeException('greenpay_second_approver_required');
   $q=$this->pdo->prepare('UPDATE finance_greenpay_transfers SET status=\'approved\',approved_by=:actor,approved_at=NOW(6),decision_note=:note WHERE id=:id AND status=\'prepared\'');
   $q->execute([':actor'=>$actor,':note'=>$note,':id'=>$id]);$this->event($id,$actor,'approved','prepared','approved',$note);
   $this->pdo->commit();return $this->get($id);
  }catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
 }
 public function sendOtp(string $id,string $actor,string $key):array {
  $this->requireStorageKey();$r=$this->row($id);
  if($r['status']!=='approved')throw new RuntimeException('greenpay_transfer_not_approved');
  if($actor===$r['created_by'])throw new RuntimeException('greenpay_second_approver_required');
  if(!preg_match('/^[A-Za-z0-9._:-]{16,128}$/D',$key))throw new InvalidArgumentException('کلید یکتا معتبر نیست.');
  $bridge=new GreenpayBridgeService($this->pdo,$this->client,$this->events,(string)\babanoell_finance_env('NOEEL_SERVICE_SHARED_SECRET',''));
  return $bridge->sendOtp($actor,$this->unbox((string)$r['from_pocket_encrypted']),(int)$r['amount_rial'],$key);
 }
 public function dispatch(string $id,string $actor,string $otp):array {
  $this->requireStorageKey();
  if(!$this->transfersEnabled)throw new RuntimeException('greenpay_transfer_disabled');
  if(strlen(trim((string)\babanoell_finance_env('FINANCE_GREENPAY_EXECUTION_KEY','')))<32)throw new RuntimeException('greenpay_execution_key_not_configured');
  if(!preg_match('/^[0-9]{4,8}$/D',$otp))throw new InvalidArgumentException('OTP معتبر نیست.');
  $capabilities=$this->client->greenpayCapabilities();
  if(!$capabilities->ok()||($capabilities->json['data']['authorized']??false)!==true||($capabilities->json['data']['transfers_enabled']??false)!==true)throw new RuntimeException('greenpay_provider_execution_not_authorized');
  // Fail before claiming irreversible dispatch if provider is clearly unavailable.
  $ready=$this->client->greenpayReadiness();
  if(!$ready->ok()||($ready->json['data']['ready']??false)!==true)throw new RuntimeException('greenpay_provider_not_ready');
  $this->pdo->beginTransaction();try {
   $r=$this->row($id,true);
   if($r['status']!=='approved')throw new RuntimeException('greenpay_transfer_already_handled');
   if($actor===$r['created_by'])throw new RuntimeException('greenpay_second_approver_required');
   $c=$this->pdo->prepare('SELECT status,merchant_total FROM settlement_cycles WHERE id=:id FOR UPDATE');$c->execute([':id'=>$r['cycle_id']]);$cycle=$c->fetch(PDO::FETCH_ASSOC);
   if(!is_array($cycle)||$cycle['status']!=='closed'||!self::sameRialAmount((string)$cycle['merchant_total'],(int)$r['amount_rial']))throw new RuntimeException('greenpay_cycle_changed');
   $q=$this->pdo->prepare('SELECT COUNT(*) FROM finance_reconciliation_items WHERE cycle_id=:id AND status=\'open\'');$q->execute([':id'=>$r['cycle_id']]);if((int)$q->fetchColumn()>0)throw new RuntimeException('greenpay_cycle_has_discrepancies');
   $q=$this->pdo->prepare('UPDATE finance_greenpay_transfers SET status=\'dispatching\',dispatch_started_at=NOW(6) WHERE id=:id AND status=\'approved\'');$q->execute([':id'=>$id]);if($q->rowCount()!==1)throw new RuntimeException('greenpay_transfer_already_handled');
   $this->event($id,$actor,'dispatch_started','approved','dispatching');$this->pdo->commit();
  }catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
  // From this point no retries are permitted: even timeouts may hide a successful debit.
  $status='uncertain';$error='provider_outcome_unknown';
  try {
   $response=$this->client->greenpayTransfer([
    'finance_intent_id'=>$id,'reference'=>(string)$r['external_reference'],
    'from_pocket'=>$this->unbox((string)$r['from_pocket_encrypted']),
    'to_pocket'=>$this->unbox((string)$r['to_pocket_encrypted']),
    'amount_rial'=>(int)$r['amount_rial'],'otp'=>$otp
   ],$id);
   if($response->ok()&&($response->json['data']['accepted']??false)===true&&($response->json['data']['reference']??'')===$r['external_reference']){$status='provider_accepted';$error='';}
   else $error=substr((string)($response->errorCode()??$response->networkError??'provider_response_unconfirmed'),0,80);
  }catch(Throwable $e){error_log('[finance] greenpay dispatch outcome unknown; category='.get_class($e));$error='provider_dispatch_exception';}
  // Any failed finalization leaves dispatching, which is also non-retryable and discoverable.
  $this->pdo->beginTransaction();try{
   $q=$this->pdo->prepare('UPDATE finance_greenpay_transfers SET status=:status,provider_error=:error,provider_accepted_at=IF(:accepted=1,NOW(6),NULL) WHERE id=:id AND status=\'dispatching\'');
   $q->execute([':status'=>$status,':error'=>$error?:null,':accepted'=>$status==='provider_accepted'?1:0,':id'=>$id]);$this->event($id,$actor,'dispatch_result','dispatching',$status,$error);$this->pdo->commit();
  }catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;throw $e;}
  return $this->get($id);
 }
 public function reconcile(string $id,string $actor,string $decision,string $note):array {
  if(!in_array($decision,['reconciled_paid','reconciled_unpaid'],true))throw new InvalidArgumentException('نتیجه بررسی معتبر نیست.');
  $note=trim($note);if(strlen($note)<20||strlen($note)>1000)throw new InvalidArgumentException('ثبت مستندات بررسی (۲۰ تا ۱۰۰۰ کاراکتر) الزامی است.');
  $this->pdo->beginTransaction();try{
   $r=$this->row($id,true);if(!in_array($r['status'],['dispatching','provider_accepted','uncertain'],true))throw new RuntimeException('greenpay_transfer_not_reconcilable');
   if($actor===$r['created_by']||$actor===$r['approved_by'])throw new RuntimeException('greenpay_independent_reviewer_required');
   $q=$this->pdo->prepare('UPDATE finance_greenpay_transfers SET status=:status,reviewed_by=:actor,reviewed_at=NOW(6),decision_note=:note WHERE id=:id');
   $q->execute([':status'=>$decision,':actor'=>$actor,':note'=>$note,':id'=>$id]);
   $this->event($id,$actor,'reconciled',(string)$r['status'],$decision,$note);$this->pdo->commit();return $this->get($id);
  }catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
 }
}
