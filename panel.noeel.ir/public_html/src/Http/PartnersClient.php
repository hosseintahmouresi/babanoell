<?php
declare(strict_types=1);
namespace Babanoell\Panel\Http;
use Babanoell\Panel\App\Config;use Babanoell\Panel\Platform\ServiceAuth;use JsonException;
final class PartnersClient
{
 public function __construct(private readonly Config$config,private readonly HttpTransportInterface$transport=new NativeHttpTransport()){}
 public function health():HttpResponse{return$this->request('GET','/health',null,null);}
 public function get(string$path,string$bearer,array$query=[]):HttpResponse{if($query!==[])$path.=(str_contains($path,'?')?'&':'?').http_build_query($query);return$this->request('GET',$path,$bearer,null);}
 public function post(string$path,string$bearer,array$payload=[],?string$idempotencyKey=null):HttpResponse{return$this->request('POST',$path,$bearer,$payload,$idempotencyKey);}
 public function put(string$path,string$bearer,array$payload=[]):HttpResponse{return$this->request('PUT',$path,$bearer,$payload);}
 public function patch(string$path,string$bearer,array$payload=[]):HttpResponse{return$this->request('PATCH',$path,$bearer,$payload);}
 public function absolute(string$path):string{return rtrim($this->config->partnersBaseUrl(),'/').'/'.ltrim($path,'/');}
 private function request(string$method,string$path,?string$bearer,?array$payload,?string$idempotencyKey=null):HttpResponse{try{$body=$payload===null?null:json_encode($payload,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);}catch(JsonException){return new HttpResponse(0,null,'','json_encode_failed');}$headers=array_merge(['Accept'=>'application/json','Content-Type'=>'application/json','User-Agent'=>'BabanoellPanel/2.5.0'],ServiceAuth::outbound('panel',$method,$path,$body,$this->config->serviceAuthSecret()));if($bearer!==null&&$bearer!=='')$headers['Authorization']='Bearer '.$bearer;if($idempotencyKey!==null&&$idempotencyKey!=='')$headers['Idempotency-Key']=$idempotencyKey;return$this->transport->request($method,$this->config->partnersBaseUrl().$path,$headers,$body,$this->config->httpTimeoutSeconds());}
}
