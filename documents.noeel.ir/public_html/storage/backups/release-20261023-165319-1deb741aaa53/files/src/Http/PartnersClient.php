<?php
declare(strict_types=1);
namespace Babanoell\Documents\Http;
use Babanoell\Documents\App\Config;use Babanoell\Documents\Platform\ServiceAuth;
final class PartnersClient
{
 public function __construct(private readonly Config$config,private readonly HttpTransportInterface$transport){}
 public function health():HttpResponse{return$this->call('GET','/health',null,null);}
 public function cooperation(string$id,string$bearer):HttpResponse{return$this->call('GET','/api/v1/cooperation/requests/'.rawurlencode($id),$bearer,null);}
 public function merchant(string$id,string$bearer):HttpResponse{return$this->call('GET','/api/v1/merchants/'.rawurlencode($id),$bearer,null);}
 public function consumeContractArtifact(string$id,string$token,string$sha):HttpResponse{return$this->call('POST','/api/v1/contracts/'.rawurlencode($id).'/artifact-handoff/consume',null,['handoff_token'=>$token,'sha256'=>$sha]);}
 public function contractPdfStored(string$id,string$documentId,string$sha):HttpResponse{return$this->call('POST','/api/v1/contracts/'.rawurlencode($id).'/pdf-stored',null,['document_id'=>$documentId,'sha256'=>$sha]);}
 public function contractArtifactStored(string$id,string$documentId,string$sha):HttpResponse{return$this->call('POST','/api/v1/contracts/'.rawurlencode($id).'/artifact-stored',null,['document_id'=>$documentId,'sha256'=>$sha]);}
 private function call(string$method,string$path,?string$bearer,?array$payload=null):HttpResponse{$body=$payload===null?null:json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);$headers=array_merge(['Accept'=>'application/json','Content-Type'=>'application/json','User-Agent'=>'BabanoellDocuments/1.13.0'],ServiceAuth::outbound('documents',$method,$path,$body,$this->config->serviceAuthSecret()));if($bearer!==null&&$bearer!=='')$headers['Authorization']='Bearer '.$bearer;return$this->transport->request($method,$this->config->partnersBaseUrl().$path,$headers,$body,$this->config->httpTimeoutSeconds());}
}
