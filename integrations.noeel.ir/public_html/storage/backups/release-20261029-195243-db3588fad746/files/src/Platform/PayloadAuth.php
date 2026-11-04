<?php
declare(strict_types=1);
namespace Babanoell\Integrations\Platform;
final class PayloadAuth {
 public static function sign(string $service,string $time,string $requestId,string $method,string $path,string $body,string $secret):string {
  $canonical=implode("\n",['v1',$service,$time,$requestId,strtoupper($method),'/'.ltrim((string)parse_url($path,PHP_URL_PATH),'/'),hash('sha256',$body)]);
  return 'v1='.hash_hmac('sha256',$canonical,$secret);
 }
 public static function verify(?string $mac,string $service,string $time,string $requestId,string $method,string $path,string $body,string $secret):bool {
  return is_string($mac)&&hash_equals(self::sign($service,$time,$requestId,$method,$path,$body,$secret),$mac);
 }
}
