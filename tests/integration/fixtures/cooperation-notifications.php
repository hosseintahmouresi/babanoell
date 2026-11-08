<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
$fixture=json_decode((string)file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR);
if(!str_starts_with(basename($fixture['root']),'bb-e2e-')||!str_starts_with($fixture['socket'],$fixture['root'].'/'))throw new RuntimeException('Disposable fixture required');
require $fixture['root'].'/partners/bootstrap.php';
use Babanoell\Partners\Notification\{DeliveryWorker,NotificationRepository};
use Babanoell\Partners\Http\HttpResponse;
$id=(string)$argv[2];$actor=substr(hash('sha256','integration:user:super_admin'),0,26);$n=new NotificationRepository($pdo);$checks=[];
$check=static function(bool $ok,string $name)use(&$checks):void{$checks[]=['name'=>$name,'passed'=>$ok];if(!$ok)throw new RuntimeException($name);};
$transport=$integrationsClient->smsPattern(['to'=>'09121234567','pattern_id'=>123,'variables'=>['Synthetic cooperation verification']],hash('sha256','synthetic-cooperation-http|'.$id));
$check($transport->errorCode()==='sms_not_configured','Cooperation SMS client reaches signed Integrations HTTP with a known unconfigured synthetic provider');
$calls=[];$mode='accepted';$worker=null;$overlap=false;
$worker=new DeliveryWorker($pdo,static function(array $payload,string $key)use(&$calls,&$mode,&$worker,&$overlap):HttpResponse{
 $calls[]=['payload'=>$payload,'key'=>$key];if(!$overlap){$overlap=true;$worker->run(1);}
 return match($mode){'accepted'=>new HttpResponse(200,['data'=>['accepted'=>true,'provider_reference'=>'synthetic_cooperation_reference']]),'not_configured'=>new HttpResponse(503,['error'=>['code'=>'sms_not_configured']]),default=>new HttpResponse(0,null,'','timeout')};
},static fn(string $id)=>new HttpResponse(503,null),static fn(string $id)=>'synthetic_contract_token');
$worker->run();$check(count($calls)===1,'Concurrent cooperation workers cannot send the same event twice');
$check($calls[0]['payload']['variables'][0]==='Original در حال بررسی','Cooperation template is frozen at the workflow transition');
$first=$n->deliveries()['items'][0]['id'];try{$n->retry($first,$actor);$check(false,'Accepted cooperation SMS is immutable');}catch(DomainException){$check(true,'Accepted cooperation SMS is immutable');}
$worker->run();$check(count($calls)===1,'Completed cooperation delivery is not repeated');
$enqueue=static function(string $status='under_review')use($pdo,$repository,$id,$actor):void{$pdo->beginTransaction();try{$repository->event('cooperation_request',$id,'cooperation.status.changed',$actor,'allowed',['from'=>'need_correction','to'=>$status]);$pdo->commit();}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}};
$mode='not_configured';$enqueue();$worker->run();$row=$n->deliveries()['items'][0];$check($row['status']==='retry'&&$row['next_attempt_at']!==null,'Known not-sent cooperation result uses delayed retry');
$before=count($calls);$worker->run();$check(count($calls)===$before,'Cooperation backoff prevents tight retries');
$n->retry($row['id'],$actor);$mode='accepted';$worker->run();$check(count($calls)===$before+1,'Explicit known-not-sent retry is processed once');
$mode='uncertain';$enqueue();$worker->run();$row=$n->deliveries()['items'][0];$check($row['status']==='uncertain','Cooperation transport timeout is quarantined');
$before=count($calls);$worker->run();$check(count($calls)===$before,'Uncertain cooperation SMS is not resent');
try{$n->retry($row['id'],$actor);$check(false,'Uncertain cooperation retry is denied');}catch(DomainException){$check(true,'Uncertain cooperation retry is denied');}
$n->reconcile($row['id'],$actor,['status'=>'accepted','reference'=>'synthetic_reconciled','note'=>'Synthetic panel investigation','confirmed'=>true]);$check(count($calls)===$before&&$n->deliveries()['items'][0]['status']==='accepted','Cooperation manual reconciliation records acceptance without another send');
$settings=$n->settings();$n->saveSettings($settings['revision'],['cooperation_statuses'=>['docs_verified'=>['enabled'=>true,'pattern_id'=>123,'variables'=>['[[contract_link]]']]]],$actor);
$enqueue('docs_verified');$mode='accepted';$worker->run();$check(end($calls)['payload']['variables']===['synthetic_contract_token'],'Contract token is resolved and frozen before a cooperation send');
$pdo->beginTransaction();$enqueueFailed=false;try{$repository->event('cooperation_request',$id,'cooperation.status.changed',$actor,'allowed',['from'=>'need_correction','to'=>'docs_verified']);$pdo->rollBack();}catch(Throwable $e){$pdo->rollBack();throw $e;}
$before=count($calls);$worker->run();$check(count($calls)===$before,'Rolled-back cooperation transition leaves no deliverable SMS');
$check(!str_contains(json_encode($n->deliveries()),'variables')&&!str_contains(json_encode($n->deliveries()),'09121234567'),'Cooperation administration omits raw mobile and message variables');
echo json_encode(['checks'=>$checks],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);
