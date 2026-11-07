<?php
declare(strict_types=1);
namespace Babanoell\Integrations\Provider;
use Babanoell\Integrations\Support\Config;
use Babanoell\Integrations\Support\ProviderException;
use Babanoell\Integrations\Support\Validation;
final class Sms {
 public function enabled():bool{return Config::get('INTEGRATIONS_SMS_USERNAME')!==''&&Config::get('INTEGRATIONS_SMS_PASSWORD')!==''&&extension_loaded('soap');}
 public function send(string $mobile,int $bodyId,array $variables):array {
  $mobile=Validation::mobile($mobile);
  if($bodyId<1||$bodyId>2147483647||count($variables)<1||count($variables)>20)throw new \InvalidArgumentException('invalid_sms_pattern');
  foreach($variables as $v){if(!is_scalar($v)||is_bool($v)||strlen((string)$v)>720||str_contains((string)$v,';'))throw new \InvalidArgumentException('invalid_sms_variables');}
  if(!$this->enabled())throw new ProviderException('sms_not_configured',503);
  if(Config::get('INTEGRATIONS_SMS_PROVIDER','melipayamak')!=='melipayamak')throw new ProviderException('sms_provider_not_supported',503);
  $wsdl=Config::get('INTEGRATIONS_SMS_WSDL','https://api.payamak-panel.com/post/send.asmx?wsdl');
  if(!preg_match('~^https://api\.payamak-panel\.com/post/send\.asmx\?wsdl$~i',$wsdl))throw new ProviderException('sms_endpoint_not_allowed',503);
  try{$client=new \SoapClient($wsdl,['encoding'=>'UTF-8','connection_timeout'=>8,'exceptions'=>true,'cache_wsdl'=>WSDL_CACHE_MEMORY,'stream_context'=>stream_context_create(['http'=>['timeout'=>20],'ssl'=>['verify_peer'=>true,'verify_peer_name'=>true]])]);
   $result=$client->SendByBaseNumber2(['username'=>Config::get('INTEGRATIONS_SMS_USERNAME'),'password'=>Config::get('INTEGRATIONS_SMS_PASSWORD'),'to'=>$mobile,'bodyId'=>$bodyId,'text'=>implode(';',array_map('strval',$variables))])->SendByBaseNumber2Result??null;
  }catch(\Throwable){throw new ProviderException('sms_delivery_unconfirmed',503);}
  if(!is_scalar($result)||!ctype_digit((string)$result)||strlen((string)$result)<=10)throw new ProviderException('sms_provider_rejected',502);
  return ['accepted'=>true,'provider'=>'melipayamak','provider_reference'=>(string)$result];
 }
}
