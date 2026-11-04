<?php
declare(strict_types=1);
namespace Babanoell\Documents\Http;
final class JsonResponse
{
    public static function send(int $status,array $payload): never
    {
        http_response_code($status);header('Content-Type: application/json; charset=utf-8');header('X-Content-Type-Options: nosniff');header('Cache-Control: no-store');echo json_encode($payload,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;
    }
    public static function ok(array $data=[],int $status=200): never { self::send($status,['data'=>$data]); }
    public static function error(int $status,string $code,string $message,array $meta=[]): never { $p=['error'=>['code'=>$code,'message'=>$message]];if($meta!==[])$p['error']['meta']=$meta;self::send($status,$p); }
}
