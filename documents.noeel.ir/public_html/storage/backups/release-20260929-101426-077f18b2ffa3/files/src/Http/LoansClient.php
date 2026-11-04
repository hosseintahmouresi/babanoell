<?php
declare(strict_types=1);
namespace Babanoell\Documents\Http;
use Babanoell\Documents\App\Config;
final class LoansClient
{
 public function __construct(private readonly Config$config,private readonly HttpTransportInterface$transport){}
 public function health():HttpResponse{return$this->transport->request('GET',$this->config->loansBaseUrl().'/health',['Accept'=>'application/json','User-Agent'=>'BabanoellDocuments/1.5.0'],null,$this->config->httpTimeoutSeconds());}
 public function request(string$requestId,string$bearer):HttpResponse{return$this->transport->request('GET',$this->config->loansBaseUrl().'/api/v1/requests/'.rawurlencode($requestId),['Accept'=>'application/json','Authorization'=>'Bearer '.$bearer,'X-Request-Id'=>'documents_loans_'.bin2hex(random_bytes(8)),'User-Agent'=>'BabanoellDocuments/1.5.0'],null,$this->config->httpTimeoutSeconds());}
 public function requirements(string$requestId,string$bearer):HttpResponse{return$this->transport->request('GET',$this->config->loansBaseUrl().'/api/v1/requests/'.rawurlencode($requestId).'/document-requirements',['Accept'=>'application/json','Authorization'=>'Bearer '.$bearer,'X-Request-Id'=>'documents_requirements_'.bin2hex(random_bytes(8)),'User-Agent'=>'BabanoellDocuments/1.5.0'],null,$this->config->httpTimeoutSeconds());}
}
