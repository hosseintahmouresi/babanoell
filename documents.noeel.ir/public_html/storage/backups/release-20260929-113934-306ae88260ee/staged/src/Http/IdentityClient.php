<?php
declare(strict_types=1);
namespace Babanoell\Documents\Http;
use Babanoell\Documents\App\Config;
use Babanoell\Documents\Platform\ServiceAuth;
use JsonException;
final class IdentityClient
{
 public function __construct(private readonly Config$config,private readonly HttpTransportInterface$transport){}
 public function health():HttpResponse{return$this->request('GET','/health',null,null,null,null);}
 public function login(string$identifier,string$password,?string$clientIp=null,?string$userAgent=null):HttpResponse{return$this->request('POST','/api/v1/auth/login',['identifier'=>$identifier,'password'=>$password],null,$clientIp,$userAgent);}
 public function me(string$bearer,?string$clientIp=null,?string$userAgent=null):HttpResponse{return$this->request('GET','/api/v1/auth/me',null,$bearer,$clientIp,$userAgent);}
 public function logout(string$bearer,?string$clientIp=null,?string$userAgent=null):HttpResponse{return$this->request('POST','/api/v1/auth/logout',['all_sessions'=>false],$bearer,$clientIp,$userAgent);}
 private function request(string$method,string$path,?array$payload,?string$bearer,?string$clientIp,?string$userAgent):HttpResponse
 {try{$body=$payload===null?null:json_encode($payload,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);}catch(JsonException){return new HttpResponse(0,null,'','json_encode_failed');}$headers=array_merge(['Accept'=>'application/json','Content-Type'=>'application/json','User-Agent'=>$userAgent&&trim($userAgent)!==''?$userAgent:'BabanoellDocuments/1.11.0'],ServiceAuth::outbound('documents',$method,$path,$body,$this->config->serviceAuthSecret()));if($bearer!==null&&$bearer!=='')$headers['Authorization']='Bearer '.$bearer;if($clientIp!==null&&filter_var($clientIp,FILTER_VALIDATE_IP))$headers['X-Forwarded-For']=$clientIp;return$this->transport->request($method,$this->config->identityBaseUrl().$path,$headers,$body,$this->config->httpTimeoutSeconds());}
}
