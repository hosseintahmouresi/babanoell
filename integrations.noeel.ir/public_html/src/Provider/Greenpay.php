<?php
declare(strict_types=1);
namespace Babanoell\Integrations\Provider;
use Babanoell\Integrations\Http\TransportInterface;
use Babanoell\Integrations\Support\Config;
use Babanoell\Integrations\Support\ProviderException;
use Babanoell\Integrations\Support\Validation;
final class Greenpay {
 public function __construct(private readonly TransportInterface $http){}
 public function enabled():bool {foreach(['USERNAME','PASSWORD','API_KEY','AUTH_URL','PURCHASE_URL','TERMINAL','MERCHANT_ID']as$k)if(Config::get('INTEGRATIONS_GREENPAY_'.$k)==='')return false;return true;}
 private function base(string $type):string {
  $url=rtrim(Config::get('INTEGRATIONS_GREENPAY_'.$type.'_URL'),'/');$parts=parse_url($url);
  if(!is_array($parts)||($parts['scheme']??'')!=='https'||!is_string($parts['host']??null)||!preg_match('/^[a-z0-9.-]+\.ir$/i',$parts['host'])||isset($parts['user'])||isset($parts['pass'])||isset($parts['query'])||isset($parts['fragment'])||filter_var($parts['host'],FILTER_VALIDATE_IP))throw new ProviderException('greenpay_url_not_allowed',503);
  return $url;
 }
 private function call(string $method,string $type,string $path,?array $body=null,?string $token=null):array {
  $base=$this->base($type);$host=(string)parse_url($base,PHP_URL_HOST);$headers=$token!==null?['Authorization'=>'Bearer '.$token]:[];
  $r=$this->http->request($method,$base.$path,$body,$headers,25,'json',[$host]);
  if($r['status']<200||$r['status']>=300||!$r['valid_json'])throw new ProviderException('greenpay_provider_rejected',502);
  return $r['data'];
 }
 private function token():string {
  if(!$this->enabled())throw new ProviderException('greenpay_not_configured',503);
  $r=$this->call('POST','AUTH','/submit',['Username'=>Config::get('INTEGRATIONS_GREENPAY_USERNAME'),'Password'=>Config::get('INTEGRATIONS_GREENPAY_PASSWORD'),'ApiKey'=>Config::get('INTEGRATIONS_GREENPAY_API_KEY')]);
  $token=$r['Token']??$r['token']??null;if(!is_string($token)||$token==='')throw new ProviderException('greenpay_token_missing',502);return $token;
 }
 public function readiness():array {$token=$this->token();$r=$this->call('GET','AUTH','/isReady/test123',null,$token);return ['ready'=>isset($r['Value'])&&(string)$r['Value']==='test123'];}
 public function configuration():array {
  $values=[];foreach(['USERNAME','PASSWORD','API_KEY','AUTH_URL','PURCHASE_URL','TERMINAL','MERCHANT_ID','ENVIRONMENT','TEST_PROFILE_1023'] as $key)$values[$key]=Config::get('INTEGRATIONS_GREENPAY_'.$key);
  $sandbox=Config::get('INTEGRATIONS_GREENPAY_ENVIRONMENT','live')==='test'&&Config::get('INTEGRATIONS_GREENPAY_TEST_PROFILE_1023','0')==='1';
  if($sandbox&&(rtrim(Config::get('INTEGRATIONS_GREENPAY_AUTH_URL'),'/')!=='https://wallet.bki.ir/walletbackend/api/sandbox'||Config::get('INTEGRATIONS_GREENPAY_TERMINAL')!=='1023'))throw new ProviderException('greenpay_sandbox_configuration_invalid',503);
  // No credentials are exposed or persisted. Clock correction may change without rebuilding a transaction.
  return ['configured'=>$this->enabled(),'transfers_enabled'=>Config::get('INTEGRATIONS_GREENPAY_TRANSFERS_ENABLED','0')==='1',
   'sandbox_profile_1023'=>$sandbox,'fingerprint'=>hash_hmac('sha256',json_encode($values,JSON_THROW_ON_ERROR),Config::requireSecret())];
 }
 private static function success(array $r):bool {
  $value=$r['Success']??$r['success']??null;
  return in_array(strtolower(trim((string)($r['Status']??$r['status']??''))),['success','successful','ok'],true)||$value===true||$value===1||$value==='1'||strtolower((string)$value)==='true';
 }
 /** Per-request reference debit protocol. Legacy cycle transfers retain their original path. */
 public function requestDebit(array $b):array {
  if(Config::get('INTEGRATIONS_GREENPAY_TRANSFERS_ENABLED','0')!=='1')throw new ProviderException('greenpay_transfer_disabled',503);
  $configuration=$this->configuration();$expected=(string)($b['configuration_fingerprint']??'');
  if(!preg_match('/^[a-f0-9]{64}$/D',$expected)||!hash_equals($configuration['fingerprint'],$expected))throw new ProviderException('greenpay_configuration_changed',409);
  $amount=filter_var($b['amount_rial']??null,FILTER_VALIDATE_INT);$from=(string)($b['from_pocket']??'');$to=(string)($b['to_pocket']??'');
  $otp=Validation::otp($b['otp']??'');$reference=Validation::opaque($b['reference']??'');
  $sandbox=$configuration['sandbox_profile_1023'];
  if($amount===false||$amount<1||$amount>100000000000000||!preg_match('/^[0-9]{8,32}$/D',$from)||(!$sandbox&&!preg_match('/^[0-9]{8,32}$/D',$to))||$from===$to)throw new \InvalidArgumentException('invalid_greenpay_debit');
  if($sandbox){if($from!=='1262010000002551'||$to!=='')throw new \InvalidArgumentException('invalid_greenpay_sandbox_pockets');$otp='123456';}
  try{$token=$this->token();}catch(ProviderException){return ['accepted'=>false,'outcome'=>'failed','reference'=>$reference,'error'=>'greenpay_preflight_not_sent'];}
  $base=$this->base('PURCHASE');$host=(string)parse_url($base,PHP_URL_HOST);$retry=false;$result=[];
  for($i=0;$i<2;$i++){
   $shift=max(-120,min(120,(int)Config::get('INTEGRATIONS_GREENPAY_TIME_SHIFT_SECONDS','30')))+$i;
   $clock=(new \DateTimeImmutable('now',new \DateTimeZone('Asia/Tehran')))->modify(($shift>=0?'+':'').$shift.' seconds');
   $payload=['ApiKey'=>Config::get('INTEGRATIONS_GREENPAY_API_KEY'),'FromPocketNumber'=>$from,'ToPocketNumber'=>$to,'Amount'=>(string)$amount,
    'TerminalNumber'=>Config::get('INTEGRATIONS_GREENPAY_TERMINAL'),'OTP'=>$otp,'SendTime'=>$clock->format('H:i:s'),'RefNumber'=>$reference];
   try{$result=$this->http->request('POST',$base.'/transferByPocket',$payload,['Authorization'=>'Bearer '.$token],25,'json',[$host]);}
   catch(ProviderException){return ['accepted'=>false,'outcome'=>'unknown','reference'=>$reference,'error'=>'greenpay_result_unknown'];}
   $status=(int)($result['status']??0);$data=is_array($result['data']??null)?$result['data']:[];
   $text=mb_strtolower(json_encode($data,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),'UTF-8');$timeRejected=false;
   foreach(['مهلت زمانی','زمانی مقرر','تراکنش با مبدا','تراکنش با مبدأ','منقضی','sendtime','send time','time window','deadline','time limit','expired','expiration'] as $marker)if(str_contains($text,$marker))$timeRejected=true;
   if($i===0&&$status>=400&&$status<500&&$timeRejected){$retry=true;continue;}break;
  }
  $status=(int)($result['status']??0);$data=is_array($result['data']??null)?$result['data']:[];$valid=($result['valid_json']??false)===true;
  $success=$status>=200&&$status<300&&$valid&&self::success($data);$outcome='unknown';
  if($success)$outcome='success';elseif($status>=400&&$status<500)$outcome='failed';
  elseif($status>=200&&$status<300&&$valid){$explicit=!empty($data['Errors'])||!empty($data['ErrorMessage'])||!empty($data['error'])||($data['Success']??null)===false||in_array(strtolower((string)($data['Status']??$data['status']??'')),['failed','failure','error','invalid','rejected'],true);if($explicit)$outcome='failed';}
  $providerReference=null;foreach(['TransactionId','TransactionID','transactionId','ReferenceNumber','RefNumber','TraceNumber','TrackingCode','RequestId','requestId'] as $key)if(isset($data[$key])&&is_scalar($data[$key])&&preg_match('/^[A-Za-z0-9_-]{1,128}$/D',(string)$data[$key])){$providerReference=(string)$data[$key];break;}
  return ['accepted'=>$success,'outcome'=>$outcome,'reference'=>$reference,'provider_reference'=>$providerReference,'time_window_retry'=>$retry,
   'error'=>$success?null:($outcome==='failed'?'greenpay_request_rejected':'greenpay_result_unknown')];
 }
 public function sendOtp(string $pocket,int $amount):array {
  if(!preg_match('/^[0-9]{8,32}$/D',$pocket)||$amount<1)throw new \InvalidArgumentException('invalid_greenpay_request');
  if($this->configuration()['sandbox_profile_1023'])return ['accepted'=>true,'sandbox_bypass'=>true];
  $r=$this->call('POST','PURCHASE','/sendPurchaseOTP',['PocketNumber'=>$pocket,'Amount'=>$amount,'ApiKey'=>Config::get('INTEGRATIONS_GREENPAY_API_KEY'),'TerminalNumber'=>Config::get('INTEGRATIONS_GREENPAY_TERMINAL'),'MerchantID'=>(int)Config::get('INTEGRATIONS_GREENPAY_MERCHANT_ID')],$this->token());
  $success=self::success($r);
  if(!$success)throw new ProviderException('greenpay_otp_unconfirmed',502);
  return ['accepted'=>true];
 }
 public function transfer(array $b):array {
  if(Config::get('INTEGRATIONS_GREENPAY_TRANSFERS_ENABLED','0')!=='1')throw new ProviderException('greenpay_transfer_disabled',503);
  $amount=(int)($b['amount_rial']??0);$from=(string)($b['from_pocket']??'');$to=(string)($b['to_pocket']??'');$otp=Validation::otp($b['otp']??'');
  if($amount<1||$amount>100000000000000||!preg_match('/^[0-9]{8,32}$/D',$from)||!preg_match('/^[0-9]{8,32}$/D',$to)||$from===$to)throw new \InvalidArgumentException('invalid_greenpay_transfer');
  $reference=Validation::opaque($b['reference']??'');
  // The reference plugin uses fresh Tehran H:i:s time for each transfer; never reuse OTP timestamp.
  $shift=max(-120,min(120,(int)Config::get('INTEGRATIONS_GREENPAY_TIME_SHIFT_SECONDS','0')));
  $clock=new \DateTimeImmutable('now',new \DateTimeZone('Asia/Tehran'));
  $sendTime=$clock->modify(($shift>=0?'+':'').$shift.' seconds')->format('H:i:s');
  $r=$this->call('POST','PURCHASE','/transferByPocket',['ApiKey'=>Config::get('INTEGRATIONS_GREENPAY_API_KEY'),'FromPocketNumber'=>$from,'ToPocketNumber'=>$to,'Amount'=>(string)$amount,'TerminalNumber'=>Config::get('INTEGRATIONS_GREENPAY_TERMINAL'),'OTP'=>$otp,'SendTime'=>$sendTime,'RefNumber'=>$reference],$this->token());
  $success=($r['Success']??null)===true||strtolower((string)($r['Status']??''))==='success';
  if(!$success)throw new ProviderException('greenpay_transfer_unconfirmed',502);
  return ['accepted'=>true,'reference'=>$reference,'provider'=>'greenpay'];
 }
}
