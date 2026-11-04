<?php
declare(strict_types=1);
namespace Babanoell\Integrations\Provider;
use Babanoell\Integrations\Http\Transport;
use Babanoell\Integrations\Support\Config;
use Babanoell\Integrations\Support\ProviderException;
use Babanoell\Integrations\Support\Validation;
final class Credit {
 private ?array $token=null;
 public function __construct(private readonly Transport $http){}
 public function enabled():bool{return Config::get('INTEGRATIONS_ICS24_USERNAME')!==''&&Config::get('INTEGRATIONS_ICS24_PASSWORD')!=='';}
 private function base():string {$s=rtrim(Config::get('INTEGRATIONS_ICS24_URL','https://mycredit.ics24.ir'),'/');if($s!=='https://mycredit.ics24.ir')throw new ProviderException('credit_endpoint_not_allowed',503);return $s;}
 private function auth():array {
  if($this->token!==null)return $this->token;
  if(!$this->enabled())throw new ProviderException('credit_not_configured',503);
  $r=$this->http->request('POST',$this->base().'/Connect/Token',['Username'=>Config::get('INTEGRATIONS_ICS24_USERNAME'),'Password'=>Config::get('INTEGRATIONS_ICS24_PASSWORD')],['x-version'=>'2.0'],24,'form',['mycredit.ics24.ir']);
  $obj=$r['data']['data']??[];
  if($r['status']!==200||($r['data']['hasError']??true)!==false||!is_array($obj)||!is_string($obj['accessToken']??null)||!is_string($obj['apiKey']??null)||$obj['accessToken']===''||$obj['apiKey']==='')throw new ProviderException('credit_auth_failed',502);
  return $this->token=['accessToken'=>$obj['accessToken'],'apiKey'=>$obj['apiKey']];
 }
 private function call(string $method,string $path,array $payload=[]):array {
  $auth=$this->auth();$r=$this->http->request($method,$this->base().$path,$method==='GET'?null:$payload,['Authorization'=>'Bearer '.$auth['accessToken'],'x-apikey'=>$auth['apiKey'],'x-version'=>'2.0'],28,'form',['mycredit.ics24.ir']);
  if($r['status']<200||$r['status']>=300||$r['valid_json']!==true||($r['data']['hasError']??true)!==false)throw new ProviderException('credit_provider_rejected',502);
  return $r['data'];
 }
 public function start(string $national,string $mobile):array {
  $r=$this->call('POST','/b2b/api/request',['RealPersonNationalCode'=>Validation::national($national),'MobileNumber'=>Validation::mobile($mobile)]);
  $v=$r['data']??null;if(!is_string($v)||!preg_match('/^[A-Za-z0-9_-]{6,180}$/D',$v))throw new ProviderException('credit_reference_missing',502);
  return ['reference'=>$v,'provider'=>'ics24','status'=>'otp_requested'];
 }
 public function validate(string $reference,string $otp):array {
  $ref=Validation::opaque($reference);$r=$this->call('POST','/b2b/api/request/'.rawurlencode($ref).'/validate',['Token'=>Validation::otp($otp)]);
  return ['accepted'=>true,'status'=>is_scalar($r['data']??null)?(string)$r['data']:'InProcessing'];
 }
 public function status(string $reference):array {
  $ref=Validation::opaque($reference);$r=$this->call('GET','/b2b/api/request/'.rawurlencode($ref).'/status');
  return ['status'=>$r['data']??null];
 }
 public function report(string $reportCode):array {
  $ref=Validation::opaque($reportCode);$r=$this->call('GET','/report/'.rawurlencode($ref).'/json');
  return ['report'=>$r['data']??null];
 }
}
