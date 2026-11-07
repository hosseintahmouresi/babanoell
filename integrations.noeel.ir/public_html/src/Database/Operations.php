<?php
declare(strict_types=1);
namespace Babanoell\Integrations\Database;
use Babanoell\Integrations\Support\ProviderException;
use Babanoell\Integrations\Support\Validation;
final class Operations {
 public function __construct(private readonly \PDO $pdo,private readonly string $secret){}
 public function claim(string $caller,string $operation,string $key,array $payload,string $target,int $limit=5,int $windowSeconds=3600):array {
  $windowSeconds=max(1,min(86400,$windowSeconds));
  $key=Validation::key($key);$body=json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
  $hash=hash_hmac('sha256',$body,$this->secret);$targetHash=hash_hmac('sha256',$target,$this->secret);
  $find=$this->pdo->prepare('SELECT status,request_hash,result_json FROM integration_operations WHERE caller=:c AND operation=:o AND idempotency_key=:k LIMIT 1');$find->execute([':c'=>$caller,':o'=>$operation,':k'=>$key]);$old=$find->fetch();
  if(is_array($old))return $this->existing($old,$hash);
  $this->pdo->beginTransaction();
  try{
   // Concurrent requests on a target serialize on a MySQL advisory lock scoped to hashed target + operation.
   $lock='noel_int_'.substr(hash('sha256',$operation.'|'.$targetHash),0,45);
   $l=$this->pdo->prepare('SELECT GET_LOCK(:l,5)');$l->execute([':l'=>$lock]);if((int)$l->fetchColumn()!==1)throw new ProviderException('request_temporarily_locked',429);
   try {
    $find->execute([':c'=>$caller,':o'=>$operation,':k'=>$key]);$old=$find->fetch();if(is_array($old)){$this->pdo->commit();return $this->existing($old,$hash);}
    $q=$this->pdo->prepare('SELECT COUNT(*) FROM integration_operations WHERE caller=:c AND operation=:o AND target_hash=:h AND created_at > DATE_SUB(NOW(6), INTERVAL '.$windowSeconds.' SECOND)');$q->execute([':c'=>$caller,':o'=>$operation,':h'=>$targetHash]);
    if((int)$q->fetchColumn()>=$limit)throw new ProviderException('rate_limit_exceeded',429);
    $id=bin2hex(random_bytes(16));$s=$this->pdo->prepare("INSERT INTO integration_operations(id,caller,operation,idempotency_key,request_hash,target_hash,status) VALUES(:id,:c,:o,:k,:rh,:th,'pending')");$s->execute([':id'=>$id,':c'=>$caller,':o'=>$operation,':k'=>$key,':rh'=>$hash,':th'=>$targetHash]);
    $this->pdo->commit();return ['state'=>'new','id'=>$id];
   }finally{$u=$this->pdo->prepare('SELECT RELEASE_LOCK(:l)');$u->execute([':l'=>$lock]);}
  }catch(\Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
 }
 private function existing(array $old,string $hash):array{
  if(!hash_equals((string)$old['request_hash'],$hash))throw new ProviderException('idempotency_payload_conflict',409);
  if($old['status']!=='succeeded')throw new ProviderException('prior_operation_not_confirmed',409);
  $stored=json_decode((string)($old['result_json']??''),true);return ['state'=>'replay','result'=>is_array($stored)?$stored:[]];
 }
 public function finish(string $id,string $status,array $result=[],?string $error=null):void {
  if(!in_array($status,['succeeded','failed','uncertain'],true))throw new \InvalidArgumentException('invalid_operation_status');
  // Never record provider response bodies, tokens, OTPs or identities. Result is a sanitized, explicitly built map.
  $q=$this->pdo->prepare("UPDATE integration_operations SET status=:s,result_json=:r,error_code=:e,finished_at=NOW(6) WHERE id=:id AND status='pending'");
  $q->execute([':s'=>$status,':r'=>json_encode($result,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),':e'=>$error,':id'=>$id]);
 }
}
