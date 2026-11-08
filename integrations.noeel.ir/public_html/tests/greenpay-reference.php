<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
// This runner never loads installed configuration or real provider credentials.
$root=dirname(__DIR__);spl_autoload_register(static function(string $class)use($root):void{$prefix='Babanoell\\Integrations\\';if(str_starts_with($class,$prefix)){require $root.'/src/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';}});
function noeel_integrations_env(string $key,string $default=''):string{$v=getenv($key);return $v===false||$v===''?$default:$v;}
use Babanoell\Integrations\Http\TransportInterface;
use Babanoell\Integrations\Provider\Greenpay;
use Babanoell\Integrations\Support\ProviderException;
foreach(['USERNAME'=>'synthetic','PASSWORD'=>'synthetic','API_KEY'=>'synthetic','AUTH_URL'=>'https://auth.example.ir','PURCHASE_URL'=>'https://purchase.example.ir','TERMINAL'=>'1023','MERCHANT_ID'=>'1','TRANSFERS_ENABLED'=>'1','TIME_SHIFT_SECONDS'=>'30']as$key=>$value)putenv('INTEGRATIONS_GREENPAY_'.$key.'='.$value);
putenv('NOEEL_SERVICE_SHARED_SECRET='.str_repeat('synthetic-',6));
final class SyntheticGreenpayTransport implements TransportInterface {
 public array $transfers=[];public array $responses=[];public bool $tokenFails=false;
 public function request(string $method,string $url,?array $payload=null,array $headers=[],int $timeout=18,string $encoding='json',array $hosts=[]):array {
  if(str_ends_with($url,'/submit')){if($this->tokenFails)throw new ProviderException('provider_timeout',503);return ['status'=>200,'valid_json'=>true,'data'=>['Token'=>'synthetic-token']];}
  if(!str_ends_with($url,'/transferByPocket'))throw new RuntimeException('Unexpected synthetic provider route');
  $this->transfers[]=$payload;$next=array_shift($this->responses);if($next instanceof Throwable)throw $next;return $next??['status'=>200,'valid_json'=>true,'data'=>['Success'=>true,'TransactionId'=>'synthetic_receipt']];
 }
}
$checks=0;$check=static function(bool $ok,string $label)use(&$checks):void{if(!$ok)throw new RuntimeException($label);$checks++;};
$run=static function(array $responses)use(&$checks):array{$http=new SyntheticGreenpayTransport();$http->responses=$responses;$p=new Greenpay($http);$b=['amount_rial'=>70000000,'from_pocket'=>'111122223333','to_pocket'=>'444455556666','otp'=>'123456','reference'=>'N'.str_repeat('a',26),'configuration_fingerprint'=>$p->configuration()['fingerprint'],'SendTime'=>'00:00:00'];return [$p->requestDebit($b),$http];};
foreach([['Success'=>true],['success'=>1],['Success'=>'true'],['Status'=>'successful'],['status'=>'ok']]as$data){[$out,$http]=$run([['status'=>200,'valid_json'=>true,'data'=>$data]]);$check($out['outcome']==='success'&&count($http->transfers)===1,'Reference documented success shapes');}
foreach([['status'=>200,'valid_json'=>false,'data'=>[]],['status'=>200,'valid_json'=>true,'data'=>[]],['status'=>503,'valid_json'=>true,'data'=>['ErrorMessage'=>'temporary']],new ProviderException('provider_timeout',503)]as$response){[$out,$http]=$run([$response]);$check($out['outcome']==='unknown'&&count($http->transfers)===1,'Ambiguous or transport result is never automatically retried');}
foreach([['status'=>400,'valid_json'=>true,'data'=>['ErrorMessage'=>'invalid OTP']],['status'=>200,'valid_json'=>true,'data'=>['Success'=>false]],['status'=>200,'valid_json'=>true,'data'=>['Status'=>'rejected']]]as$response){[$out,$http]=$run([$response]);$check($out['outcome']==='failed'&&count($http->transfers)===1,'Explicit reference business rejection is a definite failure');}
$time=['status'=>400,'valid_json'=>true,'data'=>['ErrorMessage'=>'SendTime time window expired']];
[$out,$http]=$run([$time,['status'=>200,'valid_json'=>true,'data'=>['Success'=>true]]]);
$check($out['outcome']==='success'&&$out['time_window_retry']&&count($http->transfers)===2,'Exactly one automatic retry follows an explicit time-window 4xx rejection');
$first=$http->transfers[0];$second=$http->transfers[1];$check($first['SendTime']!==$second['SendTime']&&preg_match('/^[0-9]{2}:[0-9]{2}:[0-9]{2}$/D',$first['SendTime'])===1,'Transfer uses a fresh corrected Tehran clock, ignoring client timestamps');
unset($first['SendTime'],$second['SendTime']);$check($first===$second&&$first['Amount']==='70000000','Safe time-window retry preserves amount, wallet, OTP and reference');
[$out,$http]=$run([$time,$time]);$check($out['outcome']==='failed'&&count($http->transfers)===2,'A second time-window rejection never loops');
$http=new SyntheticGreenpayTransport();$p=new Greenpay($http);$fp=$p->configuration()['fingerprint'];putenv('INTEGRATIONS_GREENPAY_TIME_SHIFT_SECONDS=31');$check($p->configuration()['fingerprint']===$fp,'Operational clock correction does not invalidate the financial snapshot');
putenv('INTEGRATIONS_GREENPAY_API_KEY=synthetic-changed');$check($p->configuration()['fingerprint']!==$fp,'Credential drift changes the opaque configuration fingerprint');
try{$p->requestDebit(['configuration_fingerprint'=>$fp]);throw new RuntimeException('Changed configuration accepted');}catch(ProviderException $e){$check($e->publicCode==='greenpay_configuration_changed'&&$http->transfers===[],'Changed credentials fail before a possible debit');}
echo "PASS GreenPay reference protocol: $checks checks\n";
putenv('INTEGRATIONS_GREENPAY_ENVIRONMENT=test');putenv('INTEGRATIONS_GREENPAY_TEST_PROFILE_1023=1');putenv('INTEGRATIONS_GREENPAY_AUTH_URL=https://wallet.bki.ir/walletbackend/api/sandbox');
$http=new SyntheticGreenpayTransport();$p=new Greenpay($http);$check($p->configuration()['sandbox_profile_1023']===true,'Sandbox profile requires the explicit sandbox auth endpoint and terminal');
$otp=$p->sendOtp('1262010000002551',70000000);$check($otp['sandbox_bypass']===true&&$http->transfers===[],'Sandbox 1023 bypasses provider OTP without an SMS request');
$payload=['configuration_fingerprint'=>$p->configuration()['fingerprint'],'amount_rial'=>70000000,'from_pocket'=>'1262010000002551','to_pocket'=>'','otp'=>'999999','reference'=>'N'.str_repeat('b',26)];
$out=$p->requestDebit($payload);$check($out['outcome']==='success'&&$http->transfers[0]['OTP']==='123456'&&$http->transfers[0]['ToPocketNumber']==='','Sandbox transfer enforces the reference fixed OTP and empty destination');
putenv('INTEGRATIONS_GREENPAY_ENVIRONMENT=live');$check($p->configuration()['sandbox_profile_1023']===false,'A live environment never enables the sandbox OTP bypass');
putenv('INTEGRATIONS_GREENPAY_ENVIRONMENT=test');putenv('INTEGRATIONS_GREENPAY_AUTH_URL=https://auth.example.ir');
try{$p->configuration();throw new RuntimeException('Sandbox accepted a live/custom auth path');}catch(ProviderException $e){$check($e->publicCode==='greenpay_sandbox_configuration_invalid','Sandbox bypass rejects an unverified auth endpoint before network I/O');}
echo "PASS GreenPay reference including sandbox: $checks checks\n";
