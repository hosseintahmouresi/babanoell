<?php
declare(strict_types=1);
namespace Babanoell\Integrations\Support;
final class Response {
 public static function json(int $status,array $data):never {
  http_response_code($status);header('Content-Type: application/json; charset=utf-8');
  header('Cache-Control: no-store, private');header('X-Content-Type-Options: nosniff');
  echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE);exit;
 }
 public static function ok(array $data,int $status=200):never {self::json($status,['ok'=>true,'data'=>$data]);}
 public static function fail(int $status,string $code,string $message):never {self::json($status,['ok'=>false,'error'=>['code'=>$code,'message'=>$message]]);}
}
