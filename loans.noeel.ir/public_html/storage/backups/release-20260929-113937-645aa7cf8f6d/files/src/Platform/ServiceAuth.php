<?php
declare(strict_types=1);
namespace Babanoell\Loans\Platform;

final class ServiceAuth
{
    public const HEADER_SERVICE = 'X-Noel-Service';
    public const HEADER_TIMESTAMP = 'X-Noel-Timestamp';
    public const HEADER_SIGNATURE = 'X-Noel-Signature';
    public const SIGNATURE_VERSION = 'v1';
    private const MAX_SKEW_SECONDS = 300;
    private const ALLOWED = ['panel','identity','loans','documents','partners','finance','integrations'];

    /** @return array<string,string> */
    public static function outbound(string $service, string $method, string $path, ?string $body, string $secret): array
    {
        $service = strtolower(trim($service));
        $headers = [
            'X-Request-Id' => RequestContext::id(),
            self::HEADER_SERVICE => in_array($service, self::ALLOWED, true) ? $service : 'loans',
            'X-Noel-Api-Version' => 'v1',
        ];
        if (trim($secret) === '') return $headers;
        $timestamp = (string) time();
        $headers[self::HEADER_TIMESTAMP] = $timestamp;
        $headers[self::HEADER_SIGNATURE] = self::SIGNATURE_VERSION . '=' . self::signature($headers[self::HEADER_SERVICE], $timestamp, RequestContext::id(), $method, $path, $body, $secret);
        return $headers;
    }

    /** @return array{ok:bool,authenticated:bool,service:?string,error:?string} */
    public static function verify(?string $service, ?string $timestamp, ?string $signature, string $method, string $path, ?string $body, string $secret, string $mode='optional'): array
    {
        $mode = strtolower(trim($mode)); if (!in_array($mode,['disabled','optional','required'],true)) $mode='optional';
        if ($mode === 'disabled') return ['ok'=>true,'authenticated'=>false,'service'=>null,'error'=>null];
        $service = is_string($service) ? strtolower(trim($service)) : '';
        $signature = is_string($signature) ? trim($signature) : '';
        $timestamp = is_string($timestamp) ? trim($timestamp) : '';
        if ($service === '' && $signature === '' && $timestamp === '') {
            return $mode === 'required'
                ? ['ok'=>false,'authenticated'=>false,'service'=>null,'error'=>'service_auth_required']
                : ['ok'=>true,'authenticated'=>false,'service'=>null,'error'=>null];
        }
        if (!in_array($service,self::ALLOWED,true)) return ['ok'=>false,'authenticated'=>false,'service'=>null,'error'=>'service_name_invalid'];
        if ($signature === '' || $timestamp === '') {
            return $mode === 'required'
                ? ['ok'=>false,'authenticated'=>false,'service'=>$service,'error'=>'service_signature_required']
                : ['ok'=>true,'authenticated'=>false,'service'=>$service,'error'=>null];
        }
        if (trim($secret) === '') return ['ok'=>false,'authenticated'=>false,'service'=>$service,'error'=>'service_auth_not_configured'];
        if (!ctype_digit($timestamp) || abs(time()-(int)$timestamp) > self::MAX_SKEW_SECONDS) return ['ok'=>false,'authenticated'=>false,'service'=>$service,'error'=>'service_signature_expired'];
        if (!str_starts_with($signature,self::SIGNATURE_VERSION.'=')) return ['ok'=>false,'authenticated'=>false,'service'=>$service,'error'=>'service_signature_invalid'];
        $provided=substr($signature,strlen(self::SIGNATURE_VERSION)+1);
        $expected=self::signature($service,$timestamp,RequestContext::id(),$method,$path,$body,$secret);
        if (!hash_equals($expected,$provided)) return ['ok'=>false,'authenticated'=>false,'service'=>$service,'error'=>'service_signature_invalid'];
        return ['ok'=>true,'authenticated'=>true,'service'=>$service,'error'=>null];
    }

    private static function signature(string $service,string $timestamp,string $requestId,string $method,string $path,?string $body,string $secret): string
    {
        $urlPath=parse_url($path,PHP_URL_PATH);$urlPath=is_string($urlPath)&&$urlPath!==''?$urlPath:'/';
        $canonical=implode("\n",[$service,$timestamp,$requestId,strtoupper($method),'/'.ltrim($urlPath,'/')]);
        return hash_hmac('sha256',$canonical,$secret);
    }
}
