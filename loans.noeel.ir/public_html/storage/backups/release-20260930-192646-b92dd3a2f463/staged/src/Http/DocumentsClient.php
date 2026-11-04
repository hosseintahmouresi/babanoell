<?php
declare(strict_types=1);
namespace Babanoell\Loans\Http;
use Babanoell\Loans\App\Config;
use Babanoell\Loans\Platform\ServiceAuth;
use JsonException;
final class DocumentsClient
{
    public function __construct(private readonly Config $config,private readonly HttpTransportInterface $transport){}
    public function health():HttpResponse{return$this->json('GET','/health',null,null);}
    public function completion(string$requestId,string$bearer):HttpResponse{return$this->json('GET','/api/v1/entities/loan_request/'.$requestId.'/completion',$bearer,null);}
    private function json(string$method,string$path,?string$bearer,?array$payload):HttpResponse
    {
        try{$body=$payload===null?null:json_encode($payload,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);}catch(JsonException){return new HttpResponse(0,null,'','json_encode_failed');}
        $headers=array_merge(['Accept'=>'application/json','Content-Type'=>'application/json','User-Agent'=>'BabanoellLoans/2.4.0'],ServiceAuth::outbound('loans',$method,$path,$body,$this->config->serviceAuthSecret()));if($bearer!==null&&$bearer!=='')$headers['Authorization']='Bearer '.$bearer;
        return$this->transport->request($method,$this->config->documentsBaseUrl().$path,$headers,$body,$this->config->httpTimeoutSeconds());
    }
}
