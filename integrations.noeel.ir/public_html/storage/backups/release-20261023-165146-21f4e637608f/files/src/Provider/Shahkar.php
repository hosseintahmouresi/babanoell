<?php
declare(strict_types=1);
namespace Babanoell\Integrations\Provider;
use Babanoell\Integrations\Http\Transport;
use Babanoell\Integrations\Support\Config;
use Babanoell\Integrations\Support\ProviderException;
use Babanoell\Integrations\Support\Validation;
final class Shahkar {
 public function __construct(private readonly Transport $http){}
 public function enabled():bool{return Config::get('INTEGRATIONS_SHAHKAR_CLIENT_ID')!==''&&Config::get('INTEGRATIONS_SHAHKAR_CLIENT_SECRET')!==''&&Config::get('INTEGRATIONS_SHAHKAR_OWNER_NID')!=='';}
 public function verify(string $mobile,string $national):array {
  $mobile=Validation::mobile($mobile);$national=Validation::national($national);
  if(!$this->enabled())throw new ProviderException('shahkar_not_configured',503);
  $id=Config::get('INTEGRATIONS_SHAHKAR_CLIENT_ID');if(!preg_match('/^[A-Za-z0-9_-]{3,120}$/',$id))throw new ProviderException('shahkar_config_invalid',503);
  $auth='Basic '.base64_encode($id.':'.Config::get('INTEGRATIONS_SHAHKAR_CLIENT_SECRET'));
  $t=$this->http->request('POST','https://api.finnotech.ir/dev/v2/oauth2/token',['grant_type'=>'client_credentials','nid'=>Config::get('INTEGRATIONS_SHAHKAR_OWNER_NID'),'scopes'=>'card:shahkar:get'],['Authorization'=>$auth],20,'json',['api.finnotech.ir']);
  $token=$t['data']['result']['value']??null;
  if($t['status']!==200||!is_string($token)||$token==='')throw new ProviderException('shahkar_auth_failed',502);
  $track='NOEEL'.time().bin2hex(random_bytes(4));$url='https://api.finnotech.ir/mpg/v2/clients/'.rawurlencode($id).'/shahkar/verify?'.http_build_query(['trackId'=>$track,'mobile'=>$mobile,'nationalCode'=>$national],'','&',PHP_QUERY_RFC3986);
  $r=$this->http->request('GET',$url,null,['Authorization'=>'Bearer '.$token],25,'json',['api.finnotech.ir']);
  if($r['status']!==200||($r['data']['status']??null)!=='DONE'||!isset($r['data']['result']['isValid'])||!is_bool($r['data']['result']['isValid']))throw new ProviderException('shahkar_result_unconfirmed',502);
  return ['matched'=>$r['data']['result']['isValid'],'provider'=>'finnotech','tracking_id'=>$track];
 }
}
