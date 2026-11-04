<?php
declare(strict_types=1);
namespace Babanoell\Finance\Service;
use Babanoell\Finance\Http\IntegrationsClient;
use Babanoell\Finance\Http\HttpResponse;
use Babanoell\Finance\Repository\PdoFinanceRepository;
use Babanoell\Finance\Support\RandomId;
use InvalidArgumentException;
use PDO;
use PDOException;
use RuntimeException;

/** Finance owns durable OTP dispatch intent. Never offers unguarded fund transfers. */
final class GreenpayBridgeService {
 public function __construct(private readonly PDO $pdo,private readonly IntegrationsClient $client,private readonly PdoFinanceRepository $repo,private readonly string $secret){}
 public function readiness():array {
  $r=$this->client->greenpayReadiness();
  if(!$r->ok()||!is_array($r->json['data']??null))return ['reachable'=>false,'ready'=>false,'code'=>$r->errorCode()??$r->networkError??'unavailable'];
  return ['reachable'=>true,'ready'=>($r->json['data']['ready']??false)===true];
 }
 public function sendOtp(string $actor,string $pocket,int $amount,string $key):array {
  if(!preg_match('/^[0-9]{8,32}$/D',$pocket)||$amount<1||$amount>100000000000000)throw new InvalidArgumentException('اطلاعات درخواست OTP معتبر نیست.');
  if(!preg_match('/^[A-Za-z0-9_-]{16,120}$/D',$key))throw new InvalidArgumentException('کلید یکتای عملیات نامعتبر است.');
  if(strlen($this->secret)<32)throw new RuntimeException('greenpay_auth_unavailable');
  $hash=hash_hmac('sha256',$actor.'|'.$pocket.'|'.$amount,$this->secret);
  $get=$this->pdo->prepare('SELECT id,status,request_fingerprint FROM finance_greenpay_otp_intents WHERE actor_user_id=:actor AND idempotency_key=:key LIMIT 1');
  $get->execute([':actor'=>$actor,':key'=>$key]);$old=$get->fetch();
  if(is_array($old)){
   if(!hash_equals((string)$old['request_fingerprint'],$hash))throw new RuntimeException('greenpay_idempotency_conflict');
   return ['intent_id'=>$old['id'],'status'=>$old['status'],'replayed'=>true];
  }
  $id=RandomId::make();
  try {
   $s=$this->pdo->prepare("INSERT INTO finance_greenpay_otp_intents(id,actor_user_id,idempotency_key,request_fingerprint,pocket_hash,amount_rial,status) VALUES(:id,:actor,:key,:fingerprint,:pocket,:amount,'pending')");
   $s->execute([':id'=>$id,':actor'=>$actor,':key'=>$key,':fingerprint'=>$hash,':pocket'=>hash_hmac('sha256',$pocket,$this->secret),':amount'=>$amount]);
  }catch(PDOException $e){if((string)$e->getCode()==='23000')throw new RuntimeException('greenpay_idempotency_in_progress');throw $e;}
  $this->repo->event('greenpay.otp.requested','greenpay_otp',$id,$actor,['amount_rial'=>$amount]);
  $r=$this->client->greenpayOtp($pocket,$amount,$key);
  $code=$r->errorCode()??$r->networkError;
  $status=$r->ok()&&(($r->json['data']['accepted']??false)===true)?'accepted':'uncertain';
  $u=$this->pdo->prepare("UPDATE finance_greenpay_otp_intents SET status=:status,provider_code=:code,finished_at=NOW(6) WHERE id=:id AND status='pending'");
  $u->execute([':status'=>$status,':code'=>$code!==null?substr($code,0,64):null,':id'=>$id]);
  $this->repo->event('greenpay.otp.result','greenpay_otp',$id,$actor,['status'=>$status,'code'=>$code]);
  if($status!=='accepted')throw new RuntimeException('greenpay_otp_unconfirmed');
  return ['intent_id'=>$id,'status'=>$status,'replayed'=>false];
 }
}
