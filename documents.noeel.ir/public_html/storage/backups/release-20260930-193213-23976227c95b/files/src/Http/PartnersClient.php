<?php
declare(strict_types=1);
namespace Babanoell\Documents\Http;
use Babanoell\Documents\App\Config;use Babanoell\Documents\Platform\ServiceAuth;
final class PartnersClient
{
 public function __construct(private readonly Config$config,private readonly HttpTransportInterface$transport){}
 public function health():HttpResponse{return$this->call('GET','/health',null);}
 public function cooperation(string$id,string$bearer):HttpResponse{return$this->call('GET','/api/v1/cooperation/requests/'.rawurlencode($id),$bearer);}
 public function merchant(string$id,string$bearer):HttpResponse{return$this->call('GET','/api/v1/merchants/'.rawurlencode($id),$bearer);}
 private function call(string$method,string$path,?string$bearer):HttpResponse{$headers=array_merge(['Accept'=>'application/json','User-Agent'=>'BabanoellDocuments/1.12.0'],ServiceAuth::outbound('documents',$method,$path,null,$this->config->serviceAuthSecret()));if($bearer!==null&&$bearer!=='')$headers['Authorization']='Bearer '.$bearer;return$this->transport->request($method,$this->config->partnersBaseUrl().$path,$headers,null,$this->config->httpTimeoutSeconds());}
}
