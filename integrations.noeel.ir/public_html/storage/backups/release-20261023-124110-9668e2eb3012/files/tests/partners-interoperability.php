<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') exit(1);
require dirname(__DIR__).'/bootstrap.php';
$partners = dirname(__DIR__,2).'/partners';
spl_autoload_register(static function(string $class)use($partners):void{$prefix='Babanoell\\Partners\\';if(!str_starts_with($class,$prefix))return;$file=$partners.'/src/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';if(is_file($file))require$file;});
class MockTransport implements \Babanoell\Partners\Http\HttpTransportInterface {
 public array $record=[];
 public function request(string $method,string $url,array $headers=[],?string $body=null,int $timeoutSeconds=6):\Babanoell\Partners\Http\HttpResponse {
  $this->record=[$method,$url,$headers,$body];return new \Babanoell\Partners\Http\HttpResponse(200,['ok'=>true,'data'=>['accepted'=>true]],'');
 }
}
$secret=str_repeat('A',40);$tr=new MockTransport();
\Babanoell\Partners\Platform\RequestContext::initialize('req_test_0987654321','partners');
$client=new \Babanoell\Partners\Http\IntegrationsClient($tr,'https://integrations.noeel.ir',$secret,1234);
if(!$client->sendContractOtp('09123456789','123456',str_repeat('a',26)))throw new RuntimeException('client did not accept delivery');
[$method,$url,$headers,$body]=$tr->record;$path='/api/v1/sms/send-pattern';
\Babanoell\Integrations\Platform\RequestContext::initialize($headers['X-Request-Id'],'integrations');
$r=\Babanoell\Integrations\Platform\ServiceAuth::verify($headers['X-Noel-Service'],$headers['X-Noel-Timestamp'],$headers['X-Noel-Signature'],$method,$path,$body,$secret,'required');
if(!$r['ok']||!$r['authenticated'])throw new RuntimeException('base HMAC mismatch');
if(!\Babanoell\Integrations\Platform\PayloadAuth::verify($headers['X-Noel-Payload-Signature'],$headers['X-Noel-Service'],$headers['X-Noel-Timestamp'],$headers['X-Request-Id'],$method,$path,$body,$secret))throw new RuntimeException('payload HMAC mismatch');
if(\Babanoell\Integrations\Platform\PayloadAuth::verify($headers['X-Noel-Payload-Signature'],$headers['X-Noel-Service'],$headers['X-Noel-Timestamp'],$headers['X-Request-Id'],$method,$path,$body.'bad',$secret))throw new RuntimeException('tamper accepted');
if(!isset($headers['Idempotency-Key'])||strlen($headers['Idempotency-Key'])<16)throw new RuntimeException('missing idempotency key');
echo "[PASS] Partners -> Integrations HMAC, body MAC and idempotency interoperability\n";
