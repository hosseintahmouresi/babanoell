<?php
declare(strict_types=1);
namespace Babanoell\Finance\Platform;
/** Exact v1 payload signature required by Noel Integrations. */
final class PayloadAuth {
 public static function sign(string $service,string $time,string $requestId,string $method,string $path,string $body,string $secret):string {
  $urlPath=parse_url($path,PHP_URL_PATH);
  $canonical=implode("\n",['v1',$service,$time,$requestId,strtoupper($method),'/'.ltrim(is_string($urlPath)?$urlPath:'/', '/'),hash('sha256',$body)]);
  return 'v1='.hash_hmac('sha256',$canonical,$secret);
 }
}
