<?php
declare(strict_types=1);
namespace Babanoell\Loans\Http;
use Babanoell\Loans\App\Config;
use Babanoell\Loans\Platform\PayloadAuth;
use Babanoell\Loans\Platform\RequestContext;
use Babanoell\Loans\Platform\ServiceAuth;
/** Server-side only; no browser-supplied URL, token, or caller identity. */
final class IntegrationsClient {
 public function __construct(private readonly Config $config, private readonly HttpTransportInterface $transport) {}
 public function shahkar(string $national,string $mobile,string $key):HttpResponse {return $this->call('POST','/api/v1/shahkar/verify',['national_code'=>$national,'mobile'=>$mobile],$key);}
 public function creditStart(string $national,string $mobile,string $key):HttpResponse {return $this->call('POST','/api/v1/credit/request',['national_code'=>$national,'mobile'=>$mobile],$key);}
 public function creditVerify(string $reference,string $otp,string $key):HttpResponse {return $this->call('POST','/api/v1/credit/verify',['reference'=>$reference,'otp'=>$otp],$key);}
 public function creditStatus(string $reference):HttpResponse {return $this->call('POST','/api/v1/credit/status',['reference'=>$reference]);}
 public function creditFinalize(array $context):HttpResponse {return $this->call('POST','/api/v1/credit/finalize',$context);}
 public function smsPattern(array $payload,string $key):HttpResponse {return $this->call('POST','/api/v1/sms/send-pattern',$payload,$key);}
 public function greenpayReadiness():HttpResponse {return $this->call('GET','/api/v1/greenpay/readiness');}
 public function greenpayOtp(string $pocket,int $amount,string $key):HttpResponse {return $this->call('POST','/api/v1/greenpay/send-otp',['pocket_number'=>$pocket,'amount_rial'=>$amount],$key);}
 private function call(string $method,string $path,?array $payload=null,?string $idempotencyKey=null):HttpResponse {
  // Production calls fail closed if shared credentials or base URL are unsafe.
  $secret=$this->config->serviceAuthSecret();
  $base=$this->config->integrationsBaseUrl();
  $allowed=preg_match('~^https://integrations\.noeel\.ir$~i',$base)===1
    ||($this->config->environment()==='local'&&preg_match('~^http://127\.0\.0\.1:[0-9]{1,5}$~D',$base)===1);
  if(strlen($secret)<32 || !$allowed)return new HttpResponse(0,null,'','integrations_configuration_invalid');
  try {$body=$payload===null?'':json_encode($payload,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);}
  catch(\JsonException){return new HttpResponse(0,null,'','integrations_json_failed');}
  $headers=ServiceAuth::outbound('loans',$method,$path,$body,$secret);
  $timestamp=(string)($headers['X-Noel-Timestamp']??'');$rid=RequestContext::id();
  if($timestamp==='' || (string)($headers['X-Request-Id']??'')!==$rid)return new HttpResponse(0,null,'','integrations_signature_unavailable');
  $headers['X-Noel-Payload-Signature']=PayloadAuth::sign('loans',$timestamp,$rid,$method,$path,$body,$secret);
  $headers['Accept']='application/json';$headers['Content-Type']='application/json';$headers['User-Agent']='BabanoellLoans/IntegrationsBridge-v1';
  if($idempotencyKey!==null){if(!preg_match('/^[A-Za-z0-9_-]{16,120}$/D',$idempotencyKey))return new HttpResponse(0,null,'','invalid_idempotency_key');$headers['Idempotency-Key']=$idempotencyKey;}
  $timeout=$this->config->httpTimeoutSeconds();
  if($path==='/api/v1/sms/send-pattern')$timeout=max($timeout,40);
  if(str_starts_with($path,'/api/v1/credit/'))$timeout=max($timeout,$path==='/api/v1/credit/finalize'?90:60);
  return $this->transport->request($method,$base.$path,$headers,$payload===null?null:$body,$timeout);
 }
}
