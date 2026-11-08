<?php
declare(strict_types=1);
namespace Babanoell\Panel\Http;
use Babanoell\Panel\App\Config;
use Babanoell\Panel\Platform\ServiceAuth;
use JsonException;
final class DocumentsClient
{
    public function __construct(private readonly Config $config,private readonly HttpTransportInterface $transport=new NativeHttpTransport()){}
    public function health():HttpResponse{return$this->requestJson('GET','/health',null,null);}
    public function get(string$path,string$bearer,array$query=[]):HttpResponse{if($query!==[])$path.=(str_contains($path,'?')?'&':'?').http_build_query($query);return$this->requestJson('GET',$path,$bearer,null);}
    public function post(string$path,string$bearer,array$payload=[]):HttpResponse{return$this->requestJson('POST',$path,$bearer,$payload);}
    public function upload(string$entityType,string$entityId,string$bearer,array$file,array$fields):HttpResponse{return$this->multipart('POST','/api/v1/entities/'.rawurlencode($entityType).'/'.rawurlencode($entityId).'/documents',$bearer,$file,$fields);}
    public function uploadPart(string $id,string $bearer,array $file,int $index):HttpResponse{return $this->multipart('POST','/api/v1/uploads/'.rawurlencode($id).'/parts',$bearer,$file,['index'=>$index]);}
    public function replace(string$documentId,string$bearer,array$file):HttpResponse{return$this->multipart('POST','/api/v1/files/'.rawurlencode($documentId).'/versions',$bearer,$file,[]);}
    public function absolute(string$path):string{return$this->config->documentsBaseUrl().'/'.ltrim($path,'/');}
    private function requestJson(string$method,string$path,?string$bearer,?array$payload):HttpResponse
    {try{$body=$payload===null?null:json_encode($payload,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);}catch(JsonException){return new HttpResponse(0,null,'','json_encode_failed');}$headers=array_merge(['Accept'=>'application/json','Content-Type'=>'application/json','User-Agent'=>'BabanoellPanel/2.5.0'],ServiceAuth::outbound('panel',$method,$path,$body,$this->config->serviceAuthSecret()));if($bearer!==null&&$bearer!=='')$headers['Authorization']='Bearer '.$bearer;return$this->transport->request($method,$this->config->documentsBaseUrl().$path,$headers,$body,$this->config->httpTimeoutSeconds());}
    private function multipart(string$method,string$path,string$bearer,array$file,array$fields):HttpResponse
    {$tmp=(string)($file['tmp_name']??'');$name=(string)($file['name']??'document');if($tmp===''||!is_file($tmp)||!is_readable($tmp))return new HttpResponse(0,null,'','upload_file_unreadable');$content=file_get_contents($tmp);if($content===false)return new HttpResponse(0,null,'','upload_file_unreadable');$boundary='----BabanoellPanel'.bin2hex(random_bytes(16));$eol="\r\n";$body='';foreach($fields as$key=>$value){if(is_array($value)||is_object($value))continue;$body.='--'.$boundary.$eol.'Content-Disposition: form-data; name="'.$this->quote((string)$key).'"'.$eol.$eol.(string)$value.$eol;}$safeName=preg_replace('/[\r\n"]+/u','_',basename($name))?:'document';$body.='--'.$boundary.$eol.'Content-Disposition: form-data; name="file"; filename="'.$safeName.'"'.$eol.'Content-Type: application/octet-stream'.$eol.$eol.$content.$eol.'--'.$boundary.'--'.$eol;$headers=array_merge(['Accept'=>'application/json','Content-Type'=>'multipart/form-data; boundary='.$boundary,'Authorization'=>'Bearer '.$bearer,'User-Agent'=>'BabanoellPanel/2.5.0'],ServiceAuth::outbound('panel',$method,$path,$body,$this->config->serviceAuthSecret()));return$this->transport->request($method,$this->config->documentsBaseUrl().$path,$headers,$body,max(20,$this->config->httpTimeoutSeconds()));}
    private function quote(string$value):string{return str_replace(['"',"\r","\n"],['_','',''],$value);}
}
