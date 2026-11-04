<?php
declare(strict_types=1);
namespace Babanoell\Integrations\Http;
use Babanoell\Integrations\Support\ProviderException;
/** Fixed base URLs only; no redirects; HTTPS and hostname allowlist to avoid SSRF. */
final class Transport {
 public function request(string $method,string $url,?array $payload=null,array $headers=[],int $timeout=18,string $encoding='json',array $hosts=[]):array {
  $parts=parse_url($url);$host=is_array($parts)?strtolower((string)($parts['host']??'')):'';
  if(!is_array($parts)||strtolower((string)($parts['scheme']??''))!=='https'||$host===''||filter_var($host,FILTER_VALIDATE_IP)||isset($parts['user'])||isset($parts['pass'])||isset($parts['fragment'])||!in_array($host,$hosts,true))throw new ProviderException('provider_endpoint_not_allowed',503);
  if(!function_exists('curl_init'))throw new ProviderException('php_curl_required',503);
  $lines=['Accept: application/json','User-Agent: NoelIntegrations/1.0.0'];
  foreach($headers as $k=>$v){if(!preg_match('/^[a-zA-Z0-9-]{1,50}$/',$k)||preg_match('/[\r\n]/',(string)$v))throw new ProviderException('invalid_provider_header',500);$lines[]=$k.': '.$v;}
  $body=null;if($payload!==null){$body=$encoding==='form'?http_build_query($payload,'','&',PHP_QUERY_RFC3986):json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);$lines[]='Content-Type: '.($encoding==='form'?'application/x-www-form-urlencoded':'application/json');}
  $ch=curl_init($url);if($ch===false)throw new ProviderException('provider_transport_unavailable');
  curl_setopt_array($ch,[CURLOPT_CUSTOMREQUEST=>strtoupper($method),CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_MAXREDIRS=>0,CURLOPT_CONNECTTIMEOUT=>min(6,$timeout),CURLOPT_TIMEOUT=>$timeout,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_HTTPHEADER=>$lines,CURLOPT_USERAGENT=>'NoelIntegrations/1.0.0',CURLOPT_MAXFILESIZE=>1048576,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS]);
  if($body!==null)curl_setopt($ch,CURLOPT_POSTFIELDS,$body);
  $raw=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);$err=curl_errno($ch);curl_close($ch);
  if($raw===false)throw new ProviderException($err===28?'provider_timeout':'provider_transport_failure',503);
  if(strlen((string)$raw)>1048576)throw new ProviderException('provider_response_too_large');
  $json=json_decode((string)$raw,true);return ['status'=>$status,'data'=>is_array($json)?$json:[],'valid_json'=>is_array($json)];
 }
}
