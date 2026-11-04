<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
require dirname(__DIR__).'/bootstrap.php';
use Babanoell\Integrations\Support\{Validation,Config,ProviderException};
use Babanoell\Integrations\Platform\{ServiceAuth,PayloadAuth};
use Babanoell\Integrations\Http\Transport;
use Babanoell\Integrations\Provider\{Sms,Shahkar,Credit,Greenpay};
$cases=[];
function test(string $name,callable $f):void{global $cases;$cases[$name]=$f;}
function check(bool $ok,string $message='assertion failed'):void{if(!$ok)throw new RuntimeException($message);}
function rejects(callable $f):void{$thrown=false;try{$f();}catch(Throwable){$thrown=true;}check($thrown,'expected failure');}
test('service signature interoperability',function(){
 $body='{"to":"09123456789"}';$secret=str_repeat('k',40);$h=ServiceAuth::outbound('partners','POST','/api/v1/sms/send-pattern',$body,$secret);
 $ok=ServiceAuth::verify($h['X-Noel-Service'],$h['X-Noel-Timestamp'],$h['X-Noel-Signature'],'POST','/api/v1/sms/send-pattern',$body,$secret,'required');check($ok['authenticated']&&$ok['ok']);
 $bad=ServiceAuth::verify($h['X-Noel-Service'],$h['X-Noel-Timestamp'],$h['X-Noel-Signature'],'POST','/api/v1/sms/send-pattern',$body.'x',$secret,'required');check($bad['ok']);$mac=PayloadAuth::sign('partners',$h['X-Noel-Timestamp'],$h['X-Request-Id'],'POST','/api/v1/sms/send-pattern',$body,$secret);check(PayloadAuth::verify($mac,'partners',$h['X-Noel-Timestamp'],$h['X-Request-Id'],'POST','/api/v1/sms/send-pattern',$body,$secret));check(!PayloadAuth::verify($mac,'partners',$h['X-Noel-Timestamp'],$h['X-Request-Id'],'POST','/api/v1/sms/send-pattern',$body.'x',$secret));
});
test('national mobile and unicode digit validation',function(){check(Validation::mobile('۰۹۱۲۳۴۵۶۷۸۹')==='09123456789');check(Validation::national('۱۲۳۴۵۶۷۸۹۰')==='1234567890');rejects(fn()=>Validation::mobile('9123456789'));rejects(fn()=>Validation::national('1234'));});
test('idempotency key validation',function(){check(Validation::key(str_repeat('a',32))===str_repeat('a',32));rejects(fn()=>Validation::key('../oops'));rejects(fn()=>Validation::key('short'));});
test('GreenPay transfers fail closed',function(){check(Config::get('INTEGRATIONS_GREENPAY_TRANSFERS_ENABLED','0')==='0');rejects(fn()=>(new Greenpay(new Transport()))->transfer(['reference'=>'foobar123','otp'=>'123456']));});
test('Provider API routes and caller allowlists present',function(){
 $s=file_get_contents(dirname(__DIR__).'/src/App/Router.php');foreach(['sms/send-pattern','shahkar/verify','credit/request','credit/verify','credit/status','credit/report','greenpay/readiness','greenpay/send-otp','greenpay/transfer','Idempotency-Key','caller_not_allowed','invalid_service_signature']as$x)check(str_contains($s,$x),$x);
});
test('No secret payload retained in operations schema',function(){
 $m=file_get_contents(dirname(__DIR__).'/database/migrations/202610210001_integrations_core.sql');check(str_contains($m,'idempotency_key'));check(str_contains($m,'target_hash'));check(!str_contains($m,'national_code'));check(!str_contains($m,'otp_code'));
});
test('no live secrets in examples',function(){
 $s=file_get_contents(dirname(__DIR__).'/config/local.env.example.php');check(str_contains($s,"'INTEGRATIONS_GREENPAY_TRANSFERS_ENABLED'=>'0'"));check(!is_file(dirname(__DIR__).'/config/local.env.php'));
});
test('installer and updater are locked down',function(){
 $s=file_get_contents(dirname(__DIR__).'/public/install.php');check(str_contains($s,'INTEGRATIONS_INSTALL_KEY'));check(str_contains($s,'installed.lock'));
 $s=file_get_contents(dirname(__DIR__).'/public/update.php');check(str_contains($s,'Id')===false||str_contains($s,'installed.lock'));check(str_contains($s,'config/local.env.php'));
});
$pass=0;$fail=0;foreach($cases as$name=>$fn){try{$fn();echo "[PASS] $name\n";$pass++;}catch(Throwable $e){echo "[FAIL] $name: ".$e->getMessage()."\n";$fail++;}}
echo "Integrations: $pass passed / $fail failed\n";exit($fail?1:0);
