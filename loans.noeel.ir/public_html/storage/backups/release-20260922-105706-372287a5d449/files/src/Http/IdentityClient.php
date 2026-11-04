<?php
declare(strict_types=1);
namespace Babanoell\Loans\Http;
use Babanoell\Loans\App\Config;
use JsonException;
final class IdentityClient
{
    public function __construct(private readonly Config $config,private readonly HttpTransportInterface $transport){}
    public function health():HttpResponse{return$this->transport->request('GET',$this->config->identityBaseUrl().'/healthz',['Accept'=>'application/json','User-Agent'=>'BabanoellLoans/1.5.0'],null,$this->config->httpTimeoutSeconds());}
    public function login(string$identifier,string$password,?string$clientIp=null,?string$userAgent=null):HttpResponse{return$this->jsonRequest('POST','/api/v1/auth/login',['identifier'=>$identifier,'password'=>$password],null,$clientIp,$userAgent);}
    public function me(string$bearer,?string$clientIp=null,?string$userAgent=null):HttpResponse{return$this->jsonRequest('GET','/api/v1/auth/me',null,$bearer,$clientIp,$userAgent);}
    public function logout(string$bearer,?string$clientIp=null,?string$userAgent=null):HttpResponse{return$this->jsonRequest('POST','/api/v1/auth/logout',['all_sessions'=>false],$bearer,$clientIp,$userAgent);}
    private function jsonRequest(string$method,string$path,?array$payload,?string$bearer,?string$clientIp,?string$userAgent):HttpResponse
    {
        try{$body=$payload===null?null:json_encode($payload,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);}catch(JsonException){return new HttpResponse(0,null,'','json_encode_failed');}$headers=['Accept'=>'application/json','Content-Type'=>'application/json','X-Request-Id'=>'loans_'.bin2hex(random_bytes(8)),'User-Agent'=>$userAgent&&trim($userAgent)!==''?$userAgent:'BabanoellLoans/1.5.0'];if($bearer!==null&&$bearer!=='')$headers['Authorization']='Bearer '.$bearer;if($clientIp!==null&&filter_var($clientIp,FILTER_VALIDATE_IP))$headers['X-Forwarded-For']=$clientIp;return$this->transport->request($method,$this->config->identityBaseUrl().$path,$headers,$body,$this->config->httpTimeoutSeconds());
    }
}
