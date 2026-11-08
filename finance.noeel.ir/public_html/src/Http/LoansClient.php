<?php
declare(strict_types=1);
namespace Babanoell\Finance\Http;
use Babanoell\Finance\App\Config;use Babanoell\Finance\Platform\ServiceAuth;use JsonException;
final class LoansClient
{
 public function __construct(private readonly Config$config,private readonly HttpTransportInterface$transport){}
 public function health():HttpResponse{return$this->call('GET','/health',null,null);}
 public function request(string$id,string$bearer):HttpResponse{return$this->call('GET','/api/v1/requests/'.$id,$bearer,null);}
 public function greenpayContext(string $id):HttpResponse{return $this->call('GET','/api/v1/admin/finance-shadow/greenpay-context/'.$id,null,null);}
 public function financeShadow(int$page=1,int$perPage=100):HttpResponse{return$this->call('GET','/api/v1/admin/finance-shadow?page='.$page.'&per_page='.$perPage,null,null);}
 public function syncMerchantSettlementResetShadow(string$id,string$actor,string$reason):HttpResponse{return$this->call('POST','/api/v1/admin/finance-shadow/merchant-reset-sync',null,['request_id'=>$id,'actor_user_id'=>$actor,'reason'=>$reason]);}
 public function syncBankCycleProjection(string $cycle,string $actor,string $date,string $note,array $ids):HttpResponse{return $this->call('POST','/api/v1/admin/finance-shadow/bank-cycle-project',null,['cycle_id'=>$cycle,'bank_user_id'=>$actor,'settlement_date'=>$date,'notes'=>$note,'request_ids'=>$ids]);}
 public function financeCyclesShadow():HttpResponse{return$this->call('GET','/api/v1/admin/finance-shadow/cycles',null,null);}
 public function financeReceiptsShadow():HttpResponse{return$this->call('GET','/api/v1/admin/finance-shadow/receipts',null,null);}
 private function call(string$method,string$path,?string$bearer,?array$payload):HttpResponse{try{$body=$payload===null?null:json_encode($payload,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);}catch(JsonException){return new HttpResponse(0,null,'','json_encode_failed');}$headers=array_merge(['Accept'=>'application/json','Content-Type'=>'application/json','User-Agent'=>'BabanoellFinance/1.0.0'],ServiceAuth::outbound('finance',$method,$path,$body,$this->config->serviceAuthSecret()));if($bearer!==null&&$bearer!=='')$headers['Authorization']='Bearer '.$bearer;return$this->transport->request($method,$this->config->loansBaseUrl().$path,$headers,$body,$this->config->httpTimeoutSeconds());}
}
