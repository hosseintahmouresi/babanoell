<?php
declare(strict_types=1);
namespace Babanoell\Loans\Http;
use Babanoell\Loans\App\Config;use Babanoell\Loans\Platform\ServiceAuth;
final class PartnersClient
{
 public function __construct(private readonly Config$config,private readonly HttpTransportInterface$transport){}
 public function health():HttpResponse{return$this->call('GET','/health',null);}
 public function myMerchant(string$bearer):HttpResponse{return$this->call('GET','/api/v1/me/merchant',$bearer);}
 public function loanContracts(string$id,string$bearer):HttpResponse{return$this->call('GET','/api/v1/loan-requests/'.rawurlencode($id).'/contracts',$bearer);}
 private function call(string$method,string$path,?string$bearer):HttpResponse{$headers=array_merge(['Accept'=>'application/json','User-Agent'=>'BabanoellLoans/2.5.0'],ServiceAuth::outbound('loans',$method,$path,null,$this->config->serviceAuthSecret()));if($bearer!==null&&$bearer!=='')$headers['Authorization']='Bearer '.$bearer;return$this->transport->request($method,$this->config->partnersBaseUrl().$path,$headers,null,$this->config->httpTimeoutSeconds());}
}
