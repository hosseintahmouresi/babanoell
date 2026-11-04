<?php
declare(strict_types=1);
namespace Babanoell\Documents\Http;
use Throwable;
final class NativeHttpTransport implements HttpTransportInterface
{
    public function request(string $method,string $url,array $headers=[],?string $body=null,int $timeoutSeconds=6): HttpResponse
    { return function_exists('curl_init')?$this->curl($method,$url,$headers,$body,$timeoutSeconds):$this->stream($method,$url,$headers,$body,$timeoutSeconds); }
    private function curl(string $method,string $url,array $headers,?string $body,int $timeout): HttpResponse
    {
        $ch=curl_init($url);if($ch===false)return new HttpResponse(0,null,'','curl_init_failed');$lines=[];foreach($headers as$n=>$v)$lines[]=$n.': '.$v;
        curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADER=>false,CURLOPT_CUSTOMREQUEST=>strtoupper($method),CURLOPT_HTTPHEADER=>$lines,CURLOPT_CONNECTTIMEOUT=>min(4,$timeout),CURLOPT_TIMEOUT=>$timeout,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2]);
        if($body!==null)curl_setopt($ch,CURLOPT_POSTFIELDS,$body);$raw=curl_exec($ch);if($raw===false){$e='curl_'.curl_errno($ch);curl_close($ch);return new HttpResponse(0,null,'',$e);} $status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);return $this->decode($status,(string)$raw);
    }
    private function stream(string $method,string $url,array $headers,?string $body,int $timeout): HttpResponse
    {
        $lines=[];foreach($headers as$n=>$v)$lines[]=$n.': '.$v;$opt=['http'=>['method'=>strtoupper($method),'header'=>implode("\r\n",$lines),'content'=>$body??'','timeout'=>$timeout,'ignore_errors'=>true,'follow_location'=>0],'ssl'=>['verify_peer'=>true,'verify_peer_name'=>true]];
        try{$raw=@file_get_contents($url,false,stream_context_create($opt));if($raw===false)return new HttpResponse(0,null,'','stream_request_failed');$status=0;foreach($http_response_header??[] as$line){if(preg_match('#^HTTP/\S+\s+(\d{3})#',$line,$m)){ $status=(int)$m[1];break;}}return $this->decode($status,$raw);}catch(Throwable){return new HttpResponse(0,null,'','stream_exception');}
    }
    private function decode(int $status,string $body): HttpResponse { $j=json_decode($body,true);return new HttpResponse($status,is_array($j)?$j:null,$body); }
}
