<?php
declare(strict_types=1);
namespace Babanoell\Tests;
use Babanoell\Integrations\Http\TransportInterface;
use Babanoell\Integrations\Support\ProviderException;
final class GreenpayTransport implements TransportInterface {
 public function __construct(private readonly string $path){}
 public function request(string $method,string $url,?array $payload=null,array $headers=[],int $timeout=18,string $encoding='json',array $hosts=[]):array{
  if(str_ends_with($url,'/submit'))return ['status'=>200,'valid_json'=>true,'data'=>['Token'=>'synthetic-provider-token']];
  if(str_ends_with($url,'/isReady/test123'))return ['status'=>200,'valid_json'=>true,'data'=>['Value'=>'test123']];
  $h=fopen($this->path,'c+b');if($h===false||!flock($h,LOCK_EX))throw new \RuntimeException('Synthetic state lock failed');
  try{
   $raw=stream_get_contents($h);$s=$raw!==''?json_decode($raw,true,512,JSON_THROW_ON_ERROR):[];$mode=$s['mode']??'success';
   if(str_ends_with($url,'/sendPurchaseOTP')){$s['otp_calls']=($s['otp_calls']??0)+1;$result=['status'=>200,'valid_json'=>true,'data'=>['Success'=>true]];}
   elseif(str_ends_with($url,'/transferByPocket')){
    $ref=(string)($payload['RefNumber']??'');$s['debit_calls']=($s['debit_calls']??0)+1;$s['references'][$ref]=($s['references'][$ref]??0)+1;
    $result=match(true){$mode==='unknown'=>null,$mode==='failed'=>['status'=>400,'valid_json'=>true,'data'=>['ErrorMessage'=>'Synthetic known rejection']],$mode==='time_window'&&$s['references'][$ref]===1=>['status'=>400,'valid_json'=>true,'data'=>['ErrorMessage'=>'SendTime time window expired']],default=>['status'=>200,'valid_json'=>true,'data'=>['Success'=>true,'TransactionId'=>'fake_'.substr($ref,1)]]};
   }else throw new \RuntimeException('Unexpected synthetic GreenPay route');
   rewind($h);ftruncate($h,0);fwrite($h,json_encode($s,JSON_THROW_ON_ERROR));fflush($h);
  }finally{flock($h,LOCK_UN);fclose($h);}
  if($result===null)throw new ProviderException('provider_timeout',503);return $result;
 }
}
