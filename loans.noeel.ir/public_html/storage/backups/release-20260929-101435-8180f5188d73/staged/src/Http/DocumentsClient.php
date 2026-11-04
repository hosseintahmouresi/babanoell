<?php
declare(strict_types=1);
namespace Babanoell\Loans\Http;

use Babanoell\Loans\App\Config;
use JsonException;

final class DocumentsClient
{
    public function __construct(private readonly Config $config,private readonly HttpTransportInterface $transport){}
    public function health():HttpResponse{return$this->transport->request('GET',$this->config->documentsBaseUrl().'/health',['Accept'=>'application/json','User-Agent'=>'BabanoellLoans/1.5.2'],null,$this->config->httpTimeoutSeconds());}
    public function completion(string$requestId,string$bearer):HttpResponse{return$this->json('GET','/api/v1/entities/loan_request/'.$requestId.'/completion',$bearer,null);}
    private function json(string$method,string$path,string$bearer,?array$payload):HttpResponse
    {
        try{$body=$payload===null?null:json_encode($payload,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);}catch(JsonException){return new HttpResponse(0,null,'','json_encode_failed');}
        $headers=['Accept'=>'application/json','Content-Type'=>'application/json','Authorization'=>'Bearer '.$bearer,'X-Request-Id'=>'loans_documents_'.bin2hex(random_bytes(8)),'User-Agent'=>'BabanoellLoans/1.5.2'];
        return$this->transport->request($method,$this->config->documentsBaseUrl().$path,$headers,$body,$this->config->httpTimeoutSeconds());
    }
}
