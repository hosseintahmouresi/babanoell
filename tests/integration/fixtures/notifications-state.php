<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
$fixture=json_decode((string)file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR);
if(!str_starts_with(basename($fixture['root']),'bb-e2e-')||!str_starts_with($fixture['socket'],$fixture['root'].'/'))throw new RuntimeException('Disposable fixture required');
require $fixture['root'].'/loans/bootstrap.php';
use Babanoell\Loans\Notification\{DeliveryWorker,NotificationRepository};
use Babanoell\Loans\Http\HttpResponse;
$requestId=(string)($argv[2]??'');if(!preg_match('/^[a-f0-9]{26}$/D',$requestId))throw new RuntimeException('Invalid synthetic request');
$actor=substr(hash('sha256','integration:user:super_admin'),0,26);$notifications=new NotificationRepository($pdo);$checks=[];
$check=static function(bool $ok,string $name) use (&$checks):void {$checks[]=['name'=>$name,'passed'=>$ok];if(!$ok)throw new RuntimeException($name);};
$calls=[];$mode='accepted';$overlap=false;$worker=null;
$worker=new DeliveryWorker($pdo,static function(array $payload,string $key) use (&$calls,&$mode,&$worker,&$overlap):HttpResponse {
    $calls[]=['payload'=>$payload,'key'=>$key];
    if(!$overlap){$overlap=true;$worker->run(1);}
    return match($mode){
        'accepted'=>new HttpResponse(200,['data'=>['accepted'=>true,'provider_reference'=>'123456789012345']]),
        'not_configured'=>new HttpResponse(503,['error'=>['code'=>'sms_not_configured']]),
        'uncertain'=>new HttpResponse(0,null,'','timeout'),
        default=>throw new RuntimeException('Synthetic interrupted send'),
    };
},static fn(string $id)=>$identityClient->notificationContact($id));
$worker->run();
$check(count($calls)===1,'Concurrent worker cannot reserve the same SMS twice');
$check($calls[0]['payload']['pattern_id']===123&&$calls[0]['payload']['variables'][0]==='Original در حال بررسی','Queued template is frozen despite later settings changes');
$check($notifications->deliveries()['items'][0]['status']==='accepted','Provider acceptance is recorded without claiming handset delivery');
$first=$notifications->deliveries()['items'][0]['id'];
try{$notifications->retry($first,$actor);$check(false,'Accepted SMS cannot be resent');}catch(DomainException){$check(true,'Accepted SMS cannot be resent');}
$worker->run();$check(count($calls)===1,'Repeated worker run does not resend accepted SMS');
$enqueue=static function()use($pdo,$repository,$requestId):void {
    $pdo->beginTransaction();try{$repository->queueOutbox('request.status_changed','loan_request',$requestId,'notifications',['request_id'=>$requestId,'to'=>'under_review']);$pdo->commit();}catch(Throwable $error){if($pdo->inTransaction())$pdo->rollBack();throw $error;}
};
$mode='not_configured';$enqueue();$worker->run();$row=$notifications->deliveries()['items'][0];
$check($row['status']==='retry'&&$row['next_attempt_at']!==null,'Known not-sent failure schedules a delayed retry');
$before=count($calls);$worker->run();$check(count($calls)===$before,'Backoff prevents an immediate retry loop');
$notifications->retry($row['id'],$actor);$mode='accepted';$worker->run();
$check(count($calls)===$before+1&&$calls[$before]['key']!==$calls[$before-1]['key'],'Explicit retry of a known failure uses a distinct provider attempt');
$mode='uncertain';$enqueue();$worker->run();$row=$notifications->deliveries()['items'][0];
$check($row['status']==='uncertain','Network timeout becomes uncertain, not retryable');
$before=count($calls);$worker->run();$check(count($calls)===$before,'Uncertain delivery is never automatically resent');
try{$notifications->retry($row['id'],$actor);$check(false,'Uncertain SMS cannot be resent');}catch(DomainException){$check(true,'Uncertain SMS cannot be resent');}
$notifications->reconcile($row['id'],$actor,['status'=>'accepted','reference'=>'confirmed_reference','note'=>'Synthetic provider reconciliation','confirmed'=>true]);
$check(count($calls)===$before&&$notifications->deliveries()['items'][0]['status']==='accepted','Manual reconciliation records known acceptance without sending another SMS');
$enqueue();$q=$pdo->prepare("UPDATE notification_deliveries SET status='sending',claim_token=:token,claimed_at=DATE_SUB(UTC_TIMESTAMP(6),INTERVAL 11 MINUTE) WHERE status='pending'");$q->execute([':token'=>str_repeat('a',32)]);
$worker->run();$check($notifications->deliveries()['items'][0]['status']==='uncertain','Interrupted send is quarantined after its lease expires');
$enqueue();$q=$pdo->prepare("UPDATE notification_deliveries SET status='preparing',claim_token=:token,claimed_at=DATE_SUB(UTC_TIMESTAMP(6),INTERVAL 11 MINUTE) WHERE status='pending'");$q->execute([':token'=>str_repeat('b',32)]);
$mode='accepted';$worker->run();$check($notifications->deliveries()['items'][0]['status']==='accepted','Interrupted preflight is safely resumed');
// Resolve the canonical acceptor contact through real, signed Identity HTTP, then deduplicate its phone.
$settings=$notifications->settings();$rule=['enabled'=>true,'pattern_id'=>123,'variables'=>['[[acceptor_name]]'],'send_to_applicant'=>true,'send_to_acceptor'=>true];
$notifications->saveSettings($settings['revision'],['loan_statuses'=>['under_review'=>$rule]],$actor);
$acceptor=substr(hash('sha256','integration:user:acceptor'),0,26);
$q=$pdo->prepare('UPDATE loan_requests SET acceptor_user_id=:user,mobile_number=:mobile WHERE id=:id');$q->execute([':user'=>$acceptor,':mobile'=>'09190000002',':id'=>$requestId]);
$enqueue();$before=count($calls);$worker->run();
$check(count($calls)===$before+1&&$calls[$before]['payload']['variables']===['Synthetic acceptor'],'Acceptor contact is resolved by signed Identity HTTP and duplicate phones receive one SMS');
$check(!str_contains(json_encode($notifications->deliveries()),'variables')&&!str_contains(json_encode($notifications->deliveries()),'09190000002'),'Delivery API omits raw recipients and rendered message variables');
echo json_encode(['checks'=>$checks],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);
