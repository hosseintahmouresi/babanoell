<?php
declare(strict_types=1);
namespace Babanoell\Partners\Http;
use Babanoell\Partners\App\Config;use Babanoell\Partners\Platform\ServiceAuth;
final class LoansClient
{
 public function __construct(private readonly Config$config,private readonly HttpTransportInterface$transport){}
 public function health():HttpResponse{return$this->call('GET','/health',null,null);}
 public function exportPartnerShadow(string$entity,int$page,int$perPage,string$bearer):HttpResponse{return$this->call('GET','/api/v1/compat/partners/export?'.http_build_query(['entity'=>$entity,'page'=>$page,'per_page'=>$perPage]),$bearer,null);}
 public function syncMerchant(array$merchant,string$bearer):HttpResponse{return$this->call('POST','/api/v1/compat/partners/merchant',$bearer,$merchant);}
 public function syncPlanPermission(string$merchantId,string$planId,string$status,array$limits,string$bearer):HttpResponse{return$this->call('POST','/api/v1/compat/partners/plan-permission',$bearer,['merchant_id'=>$merchantId,'plan_id'=>$planId,'status'=>$status,'limits'=>$limits]);}
 public function marketplace(string$bearer,array$query=[]):HttpResponse{$path='/api/v1/marketplace/requests';if($query!==[])$path.='?'.http_build_query($query);return$this->call('GET',$path,$bearer,null);}
 public function claimMarketplace(string$requestId,string$bearer):HttpResponse{return$this->call('POST','/api/v1/marketplace/requests/'.rawurlencode($requestId).'/claim',$bearer,[]);}
 public function invitationAssign(string$requestId,string$merchantId,string$code,string$bearer):HttpResponse{return$this->call('POST','/api/v1/marketplace/requests/'.rawurlencode($requestId).'/invite',$bearer,['merchant_id'=>$merchantId,'invitation_code'=>$code]);}
 private function call(string$method,string$path,?string$bearer,?array$payload):HttpResponse{$body=$payload===null?null:json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);$headers=array_merge(['Accept'=>'application/json','Content-Type'=>'application/json','User-Agent'=>'BabanoellPartners/1.0.0'],ServiceAuth::outbound('partners',$method,$path,$body,$this->config->serviceAuthSecret()));if($bearer!==null&&$bearer!=='')$headers['Authorization']='Bearer '.$bearer;return$this->transport->request($method,$this->config->loansBaseUrl().$path,$headers,$body,$this->config->httpTimeoutSeconds());}
}
