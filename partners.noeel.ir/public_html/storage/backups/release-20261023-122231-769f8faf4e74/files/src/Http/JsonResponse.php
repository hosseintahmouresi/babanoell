<?php
declare(strict_types=1);
namespace Babanoell\Partners\Http;
use Babanoell\Partners\Platform\RequestContext;
final class JsonResponse
{
    public static function send(int $status,array $payload): never
    {
        $meta=is_array($payload['meta']??null)?$payload['meta']:[];$payload['meta']=array_merge(RequestContext::responseMeta(),$meta);
        http_response_code($status);header('Content-Type: application/json; charset=utf-8');header('X-Content-Type-Options: nosniff');header('Cache-Control: no-store');header('X-Request-Id: '.RequestContext::id());header('X-Noel-Service: partners');header('X-Noel-Api-Version: v1');echo json_encode($payload,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;
    }
    public static function ok(array $data=[],int $status=200,array $meta=[]): never { self::send($status,['data'=>$data,'meta'=>$meta]); }
    public static function error(int $status,string $code,string $message,array $details=[]): never { $p=['error'=>['code'=>$code,'message'=>$message]];if($details!==[]){$p['error']['details']=$details;$p['error']['meta']=$details;}self::send($status,$p); }
}
