<?php
declare(strict_types=1);
namespace Babanoell\Documents\Http;
use Babanoell\Documents\App\Config;use Babanoell\Documents\Platform\ServiceAuth;
final class FinanceClient
{
 public function __construct(private readonly Config$config,private readonly HttpTransportInterface$transport){}
 public function health():HttpResponse{return$this->call('GET','/health',null);}
 public function cycle(string$id,string$bearer):HttpResponse{return$this->call('GET','/api/v1/cycles/'.$id,$bearer);}
 public function snapshot(string$requestId,string$bearer):HttpResponse{return$this->call('GET','/api/v1/requests/'.$requestId.'/snapshot',$bearer);}
 private function call(string$method,string$path,?string$bearer):HttpResponse{$h=array_merge(['Accept'=>'application/json','User-Agent'=>'BabanoellDocuments/1.14.0'],ServiceAuth::outbound('documents',$method,$path,null,$this->config->serviceAuthSecret()));if($bearer!==null&&$bearer!=='')$h['Authorization']='Bearer '.$bearer;return$this->transport->request($method,$this->config->financeBaseUrl().$path,$h,null,$this->config->httpTimeoutSeconds());}
}
