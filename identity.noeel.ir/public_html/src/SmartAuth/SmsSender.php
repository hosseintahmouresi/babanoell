<?php
declare(strict_types=1);
namespace Babanoell\Auth\SmartAuth;
use Babanoell\Auth\Platform\ServiceAuth;
use Babanoell\Auth\Platform\PayloadAuth;
final class SmsSender {
 public function __construct(private readonly string $base,private readonly string $secret,private readonly string $environment){}
 public function send(string $mobile,int $pattern,string $code,string $key):bool {
  $allowed=preg_match('~^https://integrations\.noeel\.ir$~i',$this->base)===1||($this->environment==='local'&&preg_match('~^http://127\.0\.0\.1:[0-9]{1,5}$~D',$this->base)===1);
  if(!$allowed||strlen($this->secret)<32||!extension_loaded('curl'))return false;
  $path='/api/v1/sms/send-pattern';$body=json_encode(['to'=>$mobile,'pattern_id'=>$pattern,'variables'=>[$code]],JSON_THROW_ON_ERROR);
  $h=ServiceAuth::outbound('identity','POST',$path,$body,$this->secret);
  $h['X-Noel-Payload-Signature']=PayloadAuth::sign('identity',$h['X-Noel-Timestamp'],$h['X-Request-Id'],'POST',$path,$body,$this->secret);
  $h+=['Content-Type'=>'application/json','Accept'=>'application/json','Idempotency-Key'=>$key];$headers=[];foreach($h as $k=>$v)$headers[]=$k.': '.$v;
  $c=curl_init($this->base.$path);curl_setopt_array($c,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$body,CURLOPT_HTTPHEADER=>$headers,CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>40,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2]);
  $raw=curl_exec($c);$status=(int)curl_getinfo($c,CURLINFO_HTTP_CODE);curl_close($c);$data=is_string($raw)?json_decode($raw,true):null;
  return $status>=200&&$status<300&&is_array($data)&&($data['data']['accepted']??false)===true;
 }
}
