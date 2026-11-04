<?php
declare(strict_types=1);
namespace Babanoell\Loans\Http;
use Babanoell\Loans\App\Config;use Babanoell\Loans\Platform\ServiceAuth;use JsonException;
final class FinanceClient
{
 public function __construct(private readonly Config$config,private readonly HttpTransportInterface$transport){}
 public function health():HttpResponse{return$this->call('GET','/health',null,null,null);}
 public function snapshot(string$id,string$bearer):HttpResponse{return$this->call('GET','/api/v1/requests/'.$id.'/snapshot',$bearer,null,null);}
 public function bankSettlement(string$id,string$bearer,array$payload,?string$key=null):HttpResponse{return$this->call('POST','/api/v1/requests/'.$id.'/bank-settlement',$bearer,$payload,$key);}
 public function merchantSettlement(string$id,string$bearer,array$payload,?string$key=null):HttpResponse{return$this->call('POST','/api/v1/requests/'.$id.'/merchant-settlement',$bearer,$payload,$key);}
 public function financierPayment(string$id,string$bearer,array$payload,?string$key=null):HttpResponse{return$this->call('POST','/api/v1/requests/'.$id.'/financier-payment',$bearer,$payload,$key);}
 private function call(string$method,string$path,?string$bearer,?array$payload,?string$key):HttpResponse{try{$body=$payload===null?null:json_encode($payload,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);}catch(JsonException){return new HttpResponse(0,null,'','json_encode_failed');}$h=array_merge(['Accept'=>'application/json','Content-Type'=>'application/json','User-Agent'=>'BabanoellLoans/2.7.0'],ServiceAuth::outbound('loans',$method,$path,$body,$this->config->serviceAuthSecret()));if($bearer!==null&&$bearer!=='')$h['Authorization']='Bearer '.$bearer;if($key!==null&&trim($key)!=='')$h['Idempotency-Key']=$key;return$this->transport->request($method,$this->config->financeBaseUrl().$path,$h,$body,$this->config->httpTimeoutSeconds());}
}
