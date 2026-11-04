<?php
declare(strict_types=1);
namespace Babanoell\Finance\Http;
use Babanoell\Finance\App\Config;use Babanoell\Finance\Platform\ServiceAuth;
final class DocumentsClient
{
 public function __construct(private readonly Config$config,private readonly HttpTransportInterface$transport){}
 public function health():HttpResponse{return$this->call('GET','/health',null);}
 public function document(string$id,string$bearer):HttpResponse{return$this->call('GET','/api/v1/documents/'.$id,$bearer);}
 private function call(string$method,string$path,?string$bearer):HttpResponse{$headers=array_merge(['Accept'=>'application/json','User-Agent'=>'BabanoellFinance/1.0.0'],ServiceAuth::outbound('finance',$method,$path,null,$this->config->serviceAuthSecret()));if($bearer!==null&&$bearer!=='')$headers['Authorization']='Bearer '.$bearer;return$this->transport->request($method,$this->config->documentsBaseUrl().$path,$headers,null,$this->config->httpTimeoutSeconds());}
}
