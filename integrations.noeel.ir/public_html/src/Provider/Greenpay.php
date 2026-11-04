<?php
declare(strict_types=1);
namespace Babanoell\Integrations\Provider;
use Babanoell\Integrations\Http\Transport;
use Babanoell\Integrations\Support\Config;
use Babanoell\Integrations\Support\ProviderException;
use Babanoell\Integrations\Support\Validation;
final class Greenpay {
 public function __construct(private readonly Transport $http){}
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
 public function sendOtp(string $pocket,int $amount):array {
  if(!preg_match('/^[0-9]{8,32}$/D',$pocket)||$amount<1)throw new \InvalidArgumentException('invalid_greenpay_request');
  $r=$this->call('POST','PURCHASE','/sendPurchaseOTP',['PocketNumber'=>$pocket,'Amount'=>$amount,'ApiKey'=>Config::get('INTEGRATIONS_GREENPAY_API_KEY'),'TerminalNumber'=>Config::get('INTEGRATIONS_GREENPAY_TERMINAL'),'MerchantID'=>(int)Config::get('INTEGRATIONS_GREENPAY_MERCHANT_ID')],$this->token());
  $success=($r['Success']??null)===true||strtolower((string)($r['Status']??''))==='success';
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
