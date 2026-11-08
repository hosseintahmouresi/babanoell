<?php
declare(strict_types=1);
namespace Babanoell\Partners\Notification;

use Babanoell\Partners\Http\HttpResponse;
use Babanoell\Partners\Support\Json;

/** A send is reserved durably before network I/O; an unknown result is never resent automatically. */
final class DeliveryWorker
{
    public function __construct(private readonly \PDO $pdo,private readonly \Closure $send,private readonly \Closure $contact,private readonly ?\Closure $contractToken=null){}
    public function run(int $limit=20): array
    {
        $limit=max(1,min(50,$limit));$counts=['accepted'=>0,'retry'=>0,'failed'=>0,'uncertain'=>0,'skipped'=>0];
        // A crashed preflight cannot have sent SMS; a crashed send may have reached the provider.
        $this->pdo->exec("UPDATE notification_deliveries SET status='retry',next_attempt_at=UTC_TIMESTAMP(6),last_error='preflight_interrupted',claim_token=NULL WHERE status='preparing' AND claimed_at<DATE_SUB(UTC_TIMESTAMP(6),INTERVAL 10 MINUTE)");
        $this->pdo->exec("UPDATE notification_deliveries SET status='uncertain',last_error='send_interrupted',claim_token=NULL WHERE status='sending' AND claimed_at<DATE_SUB(UTC_TIMESTAMP(6),INTERVAL 10 MINUTE)");
        $this->acknowledgeEvents();
        for($i=0;$i<$limit;$i++){
            $row=$this->claim();if($row===null)break;
            try{
                $payload=Json::decode((string)$row['payload_json']);$needsName=false;
                if(str_contains(Json::encode($payload['variables']??[]),'[[contract_link]]')){
                    $token=$this->contractToken!==null?($this->contractToken)((string)$row['request_id']):null;
                    if(!is_string($token)||$token===''){$counts[$this->finish($row,'retry','cooperation_contract_not_ready')]++;continue;}
                    foreach($payload['variables'] as &$v)$v=str_replace('[[contract_link]]',$token,$v);unset($v);
                }
                foreach($payload['variables']??[] as $value)if(str_contains((string)$value,'[[acceptor_name]]'))$needsName=true;
                if(empty($payload['to'])||($needsName&&!empty($payload['acceptor_user_id']))){
                    $lookup=($this->contact)((string)($payload['acceptor_user_id']??''));
                    if(!$lookup instanceof HttpResponse||!$lookup->ok()||!is_array($lookup->json['data']['contact']??null)){
                        $counts[$this->finish($row,'retry','recipient_lookup_unavailable')]++;continue;
                    }
                    $contact=$lookup->json['data']['contact'];
                    if(empty($payload['to']))$payload['to']=(string)($contact['mobile']??'');
                    foreach($payload['variables'] as &$value)$value=str_replace('[[acceptor_name]]',str_replace(';','،',(string)($contact['display_name']??'---')),$value);unset($value);
                }
                if(!preg_match('/^09[0-9]{9}$/D',(string)($payload['to']??''))){$counts[$this->finish($row,'failed','recipient_invalid')]++;continue;}
                if(!$this->reserveSend($row,$payload)){$counts['skipped']++;continue;}
                // Same delivery and send number always has the same integration idempotency key.
                $key=hash('sha256','cooperation-notification|'.$row['id'].'|'.((int)$row['attempts']+1));
                $response=($this->send)(array_intersect_key($payload,array_flip(['to','pattern_id','variables'])),$key);
                if(!$response instanceof HttpResponse)throw new \RuntimeException('invalid_transport_response');
                $reference=$response->json['data']['provider_reference']??null;
                if($response->ok()&&($response->json['data']['accepted']??false)===true&&is_string($reference)&&preg_match('/^[A-Za-z0-9_-]{1,128}$/D',$reference)){
                    $this->finish($row,'accepted',null,$reference);$counts['accepted']++;continue;
                }
                $code=$response->errorCode();
                // Only an explicit known-not-sent reply permits another attempt.
                $safeRetry=['sms_not_configured','sms_provider_not_supported','sms_endpoint_not_allowed','rate_limit_exceeded','request_temporarily_locked'];
                $safeFailure=['invalid_sms_payload','invalid_sms_pattern','invalid_sms_variables','sms_provider_rejected'];
                $state=in_array($code,$safeRetry,true)?'retry':(in_array($code,$safeFailure,true)?'failed':'uncertain');
                $safeCode=in_array($code,array_merge($safeRetry,$safeFailure),true)?$code:'provider_result_unconfirmed';
                $counts[$this->finish($row,$state,$safeCode)]++;
            }catch(\Throwable){
                // No exception text, recipient, rendered variables or response body enters job logs.
                $q=$this->pdo->prepare('SELECT status FROM notification_deliveries WHERE id=:id');$q->execute([':id'=>$row['id']]);
                $counts[$this->finish($row,$q->fetchColumn()==='preparing'?'retry':'uncertain','worker_interrupted')]++;
            }
        }
        return $counts;
    }
    private function claim(): ?array
    {
        $this->pdo->beginTransaction();
        try{
            $query=$this->pdo->query("SELECT * FROM notification_deliveries WHERE status IN ('pending','retry') AND (next_attempt_at IS NULL OR next_attempt_at<=UTC_TIMESTAMP(6)) ORDER BY created_at,id LIMIT 1 FOR UPDATE");
            $row=$query->fetch();if(!is_array($row)){$this->pdo->commit();return null;}
            $row['claim_token']=bin2hex(random_bytes(16));
            $q=$this->pdo->prepare("UPDATE notification_deliveries SET status='preparing',attempts=attempts+1,claim_token=:token,claimed_at=UTC_TIMESTAMP(6) WHERE id=:id");$q->execute([':token'=>$row['claim_token'],':id'=>$row['id']]);
            $this->pdo->commit();return $row;
        }catch(\Throwable $error){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $error;}
    }
    private function reserveSend(array $row,array $payload): bool
    {
        $hash=hash('sha256',$payload['to']);
        try{
            $q=$this->pdo->prepare("UPDATE notification_deliveries SET status='sending',recipient_hash=:hash,target_masked=:masked,payload_json=:payload WHERE id=:id AND status='preparing' AND claim_token=:token");
            $q->execute([':hash'=>$hash,':masked'=>substr($payload['to'],0,4).'***'.substr($payload['to'],-4),':payload'=>Json::encode($payload),':id'=>$row['id'],':token'=>$row['claim_token']]);
            if($q->rowCount()!==1)throw new \RuntimeException('delivery_claim_lost');
            return true;
        }catch(\PDOException $error){
            if((string)$error->getCode()!=='23000')throw $error;
            $this->finish($row,'skipped','duplicate_recipient');return false;
        }
    }
    private function finish(array $row,string $status,?string $code,?string $reference=null): string
    {
        $attempts=(int)$row['attempts']+1;
        if($status==='retry'&&$attempts>=5)$status='failed';
        $delay=min(21600,60*(2**min(8,$attempts)));
        $next=$status==='retry'?gmdate('Y-m-d H:i:s',time()+$delay):null;
        $q=$this->pdo->prepare("UPDATE notification_deliveries SET status=:status,last_error=:error,next_attempt_at=:next,provider_reference=COALESCE(:reference,provider_reference),accepted_at=CASE WHEN :accepted=1 THEN UTC_TIMESTAMP(6) ELSE accepted_at END,claim_token=NULL WHERE id=:id AND claim_token=:token AND status IN ('preparing','sending')");
        $q->execute([':status'=>$status,':error'=>$code,':next'=>$next,':reference'=>$reference,':accepted'=>$status==='accepted'?1:0,':id'=>$row['id'],':token'=>$row['claim_token']]);
        return $status;
    }
    private function acknowledgeEvents(): void
    {
        $rows=$this->pdo->query("SELECT id,event_type,destination,payload_json,payload_hash FROM integration_outbox WHERE status='pending' ORDER BY created_at,id LIMIT 100")->fetchAll();
        $q=$this->pdo->prepare("UPDATE integration_outbox SET status=:status,last_error=:code,delivered_at=CASE WHEN :done=1 THEN UTC_TIMESTAMP(6) ELSE delivered_at END WHERE id=:id AND status='pending'");
        foreach($rows as $row){
            $valid=hash_equals((string)$row['payload_hash'],hash('sha256',(string)$row['payload_json']));
            [$status,$code]=$valid?match($row['destination']){
                'notifications'=>['processed',null],
                'integrations'=>['held','explicit_verification_action_required'],
                'finance'=>['skipped','canonical_finance_owns_projection'],
                default=>['held','unknown_destination'],
            }:['failed','event_hash_mismatch'];
            $q->execute([':status'=>$status,':code'=>$code,':done'=>$status==='processed'?1:0,':id'=>$row['id']]);
        }
    }
}
