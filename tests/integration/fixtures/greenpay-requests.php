<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
$fixture=json_decode((string)file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR);
if(!str_starts_with(basename($fixture['root']),'bb-e2e-')||!str_starts_with($fixture['socket'],$fixture['root'].'/'))throw new RuntimeException('Disposable fixture required');
require $fixture['root'].'/finance/bootstrap.php';
use Babanoell\Finance\Http\{GreenpayGateway,HttpResponse};
use Babanoell\Finance\Service\{GreenpayRequestService,RequestDebitException};
final class SyntheticDebitGateway implements GreenpayGateway {
 public string $fingerprint;public string $mode='success';public array $sent=[];public array $otps=[];public ?Closure $overlap=null;
 public function __construct(){$this->fingerprint=str_repeat('a',64);}
 public function greenpayCapabilities():HttpResponse{return new HttpResponse(200,['data'=>['authorized'=>true,'transfers_enabled'=>true]]);}
 public function greenpayConfiguration():HttpResponse{return new HttpResponse(200,['data'=>['configured'=>true,'transfers_enabled'=>true,'fingerprint'=>$this->fingerprint]]);}
 public function greenpayOtp(string $pocket,int $amount,string $key):HttpResponse{$this->otps[]=[$pocket,$amount,$key];return new HttpResponse(200,['data'=>['accepted'=>true]]);}
 public function greenpayTransfer(array $payload,string $key):HttpResponse{throw new RuntimeException('Per-case debit reached the legacy cycle operation');}
 public function greenpayRequestDebit(array $payload,string $key):HttpResponse{
  $this->sent[]=[$payload,$key];if($this->overlap!==null){$c=$this->overlap;$this->overlap=null;$c();}
  if($this->mode==='timeout')return new HttpResponse(0,null,'','timeout');
  return new HttpResponse(200,['data'=>['reference'=>$payload['reference'],'accepted'=>$this->mode==='success','outcome'=>$this->mode,'provider_reference'=>'synthetic_provider_receipt']]);
 }
}
$id=(string)$argv[2];$actor=substr(hash('sha256','integration:user:super_admin'),0,26);$gateway=new SyntheticDebitGateway();$checks=[];
$check=static function(bool $ok,string $label)use(&$checks):void{$checks[]=['name'=>$label,'passed'=>$ok];if(!$ok)throw new RuntimeException($label);};
$reject=static function(callable $call,string $label)use($check):void{try{$call();}catch(RequestDebitException){$check(true,$label);return;}$check(false,$label);};
$initial=$loansClient->greenpayContext($id);if(!$initial->ok())throw new RuntimeException('Signed finance case context failed');$canonical=$initial->json['data']['request'];
$check($canonical['id']===$id&&$canonical['status']==='docs_verified','Finance reads a verified per-case financial context through the signed Loans service');
$check(GreenpayRequestService::amount($canonical)===70000000,'Reference GreenPay uses 70m financed amount for a 100m purchase at 70%');
$check(GreenpayRequestService::amount(['facility_amount'=>null,'requested_amount'=>'100.60'])===101,'Reference fallback and integer rounding retain their exact meanings');
$from='111122223333';$to='444455556666';$svc=new GreenpayRequestService($pdo,$repository,$gateway,static function(string $requested)use(&$canonical):array{return $canonical;},str_repeat('s',32),$to,true);
$none=$svc->get($id);$check(!$none['eligible']&&$none['status']==='not_applicable','A case is excluded from GreenPay until explicitly enrolled');
$state=$svc->enroll($id,$actor,['eligible'=>true,'from_pocket'=>$from,'row_version'=>0]);$check($state['eligible']&&$state['status']==='not_started','Explicit enrollment reserves no OTP and moves no funds');
$replay=$svc->enroll($id,$actor,['eligible'=>true,'from_pocket'=>$from,'row_version'=>0]);$check($replay['row_version']===$state['row_version'],'A lost enrollment response can be replayed without changing the intent');
$canonical['status']='need_correction';$reject(fn()=>$svc->sendOtp($id,$actor,$state['row_version']),'A case outside verified documents cannot start an OTP/debit attempt');$check($gateway->otps===[],'Rejected case stage causes no provider OTP call');$canonical['status']='docs_verified';
$state=$svc->sendOtp($id,$actor,$state['row_version']);$check($state['status']==='awaiting_otp'&&count($gateway->otps)===1,'OTP dispatch creates a durable financially frozen per-case attempt');
$check($state['attempts'][0]['amount_rial']==70000000,'The attempt snapshot retains the financed amount');
$reject(fn()=>$svc->enroll($id,$actor,['eligible'=>false,'row_version'=>$state['row_version']]),'An OTP/in-flight financial intent cannot be detached');
$reject(fn()=>$svc->sendOtp($id,$actor,$state['row_version']),'Immediate OTP resend respects its cooldown');
$first=$state['attempts'][0];$pdo->prepare('UPDATE finance_greenpay_request_attempts SET otp_requested_at=DATE_SUB(UTC_TIMESTAMP(6),INTERVAL 2 MINUTE) WHERE id=:id')->execute([':id'=>$first['id']]);
$canonical['facility_amount']='90000000';$state=$svc->sendOtp($id,$actor,$state['row_version']);$check($state['attempts'][0]['id']===$first['id']&&$state['attempts'][0]['amount_rial']==70000000,'OTP resend preserves its original amount despite later canonical changes');
$gateway->fingerprint=str_repeat('b',64);$reject(fn()=>$svc->execute($id,$actor,$state['row_version'],'123456'),'Credential drift is rejected before irreversible dispatch');$check($gateway->sent===[],'Configuration drift cannot reach the debit provider');$gateway->fingerprint=str_repeat('a',64);
$gateway->mode='timeout';$state=$svc->execute($id,$actor,$state['row_version'],'۱۲۳۴۵۶');$check($state['status']==='unknown'&&count($gateway->sent)===1,'Lost debit response is quarantined as unknown');
$reject(fn()=>$svc->execute($id,$actor,$state['row_version'],'123456'),'Unknown debit is never sent again automatically or by repeated confirmation');
$reject(fn()=>$svc->enroll($id,$actor,['eligible'=>false,'row_version'=>$state['row_version']]),'An unknown financial outcome cannot be hidden by removing eligibility');
$reject(fn()=>$svc->reconcile($id,$actor,$state['row_version'],['decision'=>'unpaid','reference'=>'wrong','confirmed'=>true,'note'=>'Synthetic provider investigation']),'Reconciliation is bound to the exact attempt reference');
$state=$svc->reconcile($id,$actor,$state['row_version'],['decision'=>'unpaid','reference'=>$first['external_reference'],'confirmed'=>true,'note'=>'Synthetic provider confirmed no debit']);$check($state['status']==='failed'&&count($gateway->sent)===1,'Explicit unpaid reconciliation enables correction without another transfer');
$canonical['facility_amount']='70000000';$state=$svc->sendOtp($id,$actor,$state['row_version']);$second=$state['attempts'][0];$check($second['id']!==$first['id']&&count($state['attempts'])===2&&$state['attempts'][1]['status']==='failed','A fresh authorized attempt keeps all prior financial evidence');
$gateway->mode='success';$gateway->overlap=static function()use($svc,$id,$actor,$reject):void{$current=$svc->get($id);$reject(fn()=>$svc->execute($id,$actor,$current['row_version'],'123456'),'Concurrent confirmation cannot reserve a second debit');};
$state=$svc->execute($id,$actor,$state['row_version'],'123456');$check($state['status']==='success'&&count($gateway->sent)===2,'Exactly one provider execution completes the fresh attempt');
$svc->execute($id,$actor,$state['row_version'],'123456');$check(count($gateway->sent)===2,'Successful debit confirmation is idempotent');
$reject(fn()=>$svc->enroll($id,$actor,['eligible'=>false,'row_version'=>$state['row_version']]),'Successful history cannot be detached or overwritten');
$raw=$pdo->query('SELECT * FROM finance_greenpay_request_debits')->fetchAll(PDO::FETCH_ASSOC);$check(!str_contains(json_encode($raw),$from)&&!str_contains(json_encode($raw),$to),'Full wallet numbers are encrypted at rest');
$check(!str_contains(json_encode($state),$from)&&!str_contains(json_encode($state),$to)&&!str_contains(json_encode($state),'123456'),'Public finance snapshots omit complete wallets and OTP');
$pdo->prepare("UPDATE finance_greenpay_request_attempts SET status='processing',dispatch_started_at=DATE_SUB(UTC_TIMESTAMP(6),INTERVAL 3 MINUTE) WHERE id=:id")->execute([':id'=>$second['id']]);$pdo->prepare("UPDATE finance_greenpay_request_debits SET status='processing' WHERE request_id=:id")->execute([':id'=>$id]);
$state=$svc->get($id);$state=$svc->reconcile($id,$actor,$state['row_version'],['decision'=>'paid','reference'=>$second['external_reference'],'confirmed'=>true,'note'=>'Synthetic worker interruption confirmed paid']);$check($state['status']==='success'&&count($gateway->sent)===2,'A worker interrupted after dispatch is reconciled without sending again');
echo json_encode(['checks'=>$checks],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);
