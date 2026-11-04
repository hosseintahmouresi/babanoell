<?php
declare(strict_types=1);
namespace Babanoell\Partners\Http;
use Babanoell\Partners\App\Config;use Babanoell\Partners\Platform\ServiceAuth;
final class DocumentsClient
{
 public function __construct(private readonly Config$config,private readonly HttpTransportInterface$transport){}
 public function health():HttpResponse{return$this->call('GET','/health',null,null);}
 public function cooperationCompletion(string$id,string$bearer):HttpResponse{return$this->call('GET','/api/v1/entities/cooperation_request/'.$id.'/completion',$bearer,null);}
 public function storeContractArtifact(string$contractId,string$handoff,string$sha,string$content,string$filename,string$mime):HttpResponse{return$this->call('POST','/api/v1/contracts/artifacts',null,['contract_id'=>$contractId,'handoff_token'=>$handoff,'sha256'=>$sha,'content_base64'=>base64_encode($content),'filename'=>$filename,'mime_type'=>$mime]);}
 private function call(string$method,string$path,?string$bearer,?array$payload):HttpResponse{$body=$payload===null?null:json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);$headers=array_merge(['Accept'=>'application/json','Content-Type'=>'application/json','User-Agent'=>'BabanoellPartners/1.1.0'],ServiceAuth::outbound('partners',$method,$path,$body,$this->config->serviceAuthSecret()));if($bearer!==null&&$bearer!=='')$headers['Authorization']='Bearer '.$bearer;return$this->transport->request($method,$this->config->documentsBaseUrl().$path,$headers,$body,str_contains($path,'/contracts/artifacts')?max(45,$this->config->httpTimeoutSeconds()):$this->config->httpTimeoutSeconds());}
}
