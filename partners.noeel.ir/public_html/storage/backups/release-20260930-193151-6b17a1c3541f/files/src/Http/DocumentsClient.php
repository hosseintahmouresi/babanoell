<?php
declare(strict_types=1);
namespace Babanoell\Partners\Http;
use Babanoell\Partners\App\Config;use Babanoell\Partners\Platform\ServiceAuth;
final class DocumentsClient
{
 public function __construct(private readonly Config$config,private readonly HttpTransportInterface$transport){}
 public function health():HttpResponse{return$this->call('GET','/health',null);}
 private function call(string$method,string$path,?string$bearer):HttpResponse{$headers=array_merge(['Accept'=>'application/json','User-Agent'=>'BabanoellPartners/1.0.0'],ServiceAuth::outbound('partners',$method,$path,null,$this->config->serviceAuthSecret()));if($bearer!==null&&$bearer!=='')$headers['Authorization']='Bearer '.$bearer;return$this->transport->request($method,$this->config->documentsBaseUrl().$path,$headers,null,$this->config->httpTimeoutSeconds());}
}
