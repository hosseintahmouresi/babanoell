<?php
declare(strict_types=1);
namespace Babanoell\Panel\Http;
use Babanoell\Panel\App\Config;
use Babanoell\Panel\Platform\ServiceAuth;
use JsonException;
final class IdentityClient implements IdentityGatewayInterface
{
    public function __construct(private readonly Config $config, private readonly HttpTransportInterface $transport = new NativeHttpTransport()) {}
    public function health(): HttpResponse{return $this->request('GET','/healthz',null,null,null,null);}
    public function login(string $identifier,string $password,?string $clientIp,?string $userAgent):HttpResponse{return $this->request('POST','/api/v1/auth/login',['identifier'=>$identifier,'password'=>$password],null,$clientIp,$userAgent);}
    public function me(string $accessCredential,?string $clientIp,?string $userAgent):HttpResponse{return $this->request('GET','/api/v1/auth/me',null,$accessCredential,$clientIp,$userAgent);}
    public function refresh(string $refreshCredential,?string $clientIp,?string $userAgent):HttpResponse{return $this->request('POST','/api/v1/auth/refresh',['refresh_token'=>$refreshCredential],null,$clientIp,$userAgent);}
    public function logout(string $accessCredential,bool $allSessions,?string $clientIp,?string $userAgent):HttpResponse{return $this->request('POST','/api/v1/auth/logout',['all_sessions'=>$allSessions],$accessCredential,$clientIp,$userAgent);}
    public function get(string $path,string $accessCredential,array $query=[]):HttpResponse{if($query!==[])$path.=(str_contains($path,'?')?'&':'?').http_build_query($query);return $this->request('GET',$path,null,$accessCredential,null,null);}
    public function post(string $path,string $accessCredential,array $payload=[]):HttpResponse{return $this->request('POST',$path,$payload,$accessCredential,null,null);}
    private function request(string$method,string$path,?array$payload,?string$bearer,?string$clientIp,?string$userAgent):HttpResponse
    {try{$body=$payload===null?null:json_encode($payload,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);}catch(JsonException){return new HttpResponse(0,null,'','json_encode_failed');}$headers=array_merge(['Accept'=>'application/json','Content-Type'=>'application/json','User-Agent'=>$userAgent&&trim($userAgent)!==''?$userAgent:'BabanoellPanel/2.5.0'],ServiceAuth::outbound('panel',$method,$path,$body,$this->config->serviceAuthSecret()));if($bearer!==null&&$bearer!=='')$headers['Authorization']='Bearer '.$bearer;if($clientIp!==null&&filter_var($clientIp,FILTER_VALIDATE_IP))$headers['X-Forwarded-For']=$clientIp;return$this->transport->request($method,$this->config->identityBaseUrl().$path,$headers,$body,$this->config->httpTimeoutSeconds());}
}
