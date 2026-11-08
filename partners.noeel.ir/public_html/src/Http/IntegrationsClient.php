<?php
declare(strict_types=1);
namespace Babanoell\Partners\Http;
use Babanoell\Partners\Platform\ServiceAuth;
use Babanoell\Partners\Platform\RequestContext;
final class IntegrationsClient {
 public function __construct(private readonly HttpTransportInterface $transport,private readonly string $base,private readonly string $secret,private readonly int $bodyId,private readonly int $timeout=20,private readonly bool $local=false){}
 public function smsPattern(array $payload,string $key):HttpResponse {
  $allowed=preg_match('~^https://integrations\.noeel\.ir$~D',$this->base)===1||($this->local&&preg_match('~^http://127\.0\.0\.1:[0-9]{1,5}$~D',$this->base)===1);
  if(!$allowed||strlen($this->secret)<32)return new HttpResponse(503,['error'=>['code'=>'sms_not_configured']]);
  $path='/api/v1/sms/send-pattern';$body=json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
  $headers=array_merge(['Accept'=>'application/json','Content-Type'=>'application/json','Idempotency-Key'=>$key],ServiceAuth::outbound('partners','POST',$path,$body,$this->secret));
  $canonical=implode("\n",['v1','partners',(string)($headers['X-Noel-Timestamp']??''),(string)($headers['X-Request-Id']??''),'POST',$path,hash('sha256',$body)]);
  $headers['X-Noel-Payload-Signature']='v1='.hash_hmac('sha256',$canonical,$this->secret);
  return $this->transport->request('POST',$this->base.$path,$headers,$body,$this->timeout);
 }
 public function sendContractOtp(string $mobile,string $otp,string $contractId):bool {
  if($this->bodyId<=0||strlen($this->secret)<32||!preg_match('~^https://integrations\.noeel\.ir$~',$this->base))return false;
  $path='/api/v1/sms/send-pattern';$payload=['to'=>$mobile,'pattern_id'=>$this->bodyId,'variables'=>[$otp]];
  $body=json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
  // Unique per challenge; never use the contract ID alone as idempotency key.
  $key='contract_'.bin2hex(random_bytes(18));
  $headers=array_merge(['Accept'=>'application/json','Content-Type'=>'application/json','Idempotency-Key'=>$key],ServiceAuth::outbound('partners','POST',$path,$body,$this->secret));
  $timestamp=(string)($headers['X-Noel-Timestamp']??'');$requestId=(string)($headers['X-Request-Id']??'');
  $canonical=implode("\n",['v1','partners',$timestamp,$requestId,'POST',$path,hash('sha256',$body)]);
  $headers['X-Noel-Payload-Signature']='v1='.hash_hmac('sha256',$canonical,$this->secret);
  $res=$this->transport->request('POST',$this->base.$path,$headers,$body,$this->timeout);
  return $res->ok()&&($res->json['ok']??false)===true&&($res->json['data']['accepted']??false)===true;
 }
}
