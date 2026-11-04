<?php
declare(strict_types=1);
namespace Babanoell\Finance\Http;
use Babanoell\Finance\App\Config;use Babanoell\Finance\Platform\ServiceAuth;
final class PartnersClient
{
 public function __construct(private readonly Config$config,private readonly HttpTransportInterface$transport){}
 public function health():HttpResponse{return$this->call('GET','/health',null);}
 public function merchant(string$id,string$bearer):HttpResponse
 {
  $response=$this->call('GET','/api/v1/merchants/'.$id,$bearer);
  // Cycle creation only needs merchant existence. The financial role may read the
  // active directory without gaining access to private memberships or settings.
  if($response->status===403)return$this->call('GET','/api/v1/directory/merchants/'.$id,$bearer);
  return$response;
 }
 private function call(string$method,string$path,?string$bearer):HttpResponse{$headers=array_merge(['Accept'=>'application/json','User-Agent'=>'BabanoellFinance/1.0.0'],ServiceAuth::outbound('finance',$method,$path,null,$this->config->serviceAuthSecret()));if($bearer!==null&&$bearer!=='')$headers['Authorization']='Bearer '.$bearer;return$this->transport->request($method,$this->config->partnersBaseUrl().$path,$headers,null,$this->config->httpTimeoutSeconds());}
}
