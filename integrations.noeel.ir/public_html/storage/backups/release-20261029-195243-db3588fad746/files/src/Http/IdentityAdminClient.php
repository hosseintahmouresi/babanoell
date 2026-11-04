<?php
declare(strict_types=1);
namespace Babanoell\Integrations\Http;

use Babanoell\Integrations\Platform\RequestContext;
use Babanoell\Integrations\Platform\ServiceAuth;
use Babanoell\Integrations\Support\Config;

/** Canonical Identity API; all diagnostics are non-sensitive metadata. */
final class IdentityAdminClient
{
    private string $base;
    public function __construct()
    {
        $url = rtrim(trim(Config::get('INTEGRATIONS_IDENTITY_BASE_URL', 'https://identity.noeel.ir')), '/');
        $parts = parse_url($url);
        if (!is_array($parts) || ($parts['scheme'] ?? '') !== 'https' || !is_string($parts['host'] ?? null)
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])
            || filter_var($parts['host'], FILTER_VALIDATE_IP)) {
            throw new \RuntimeException('Identity HTTPS base URL is invalid.');
        }
        $this->base = $url;
    }
    public function login(string $identifier, string $password, ?string $ip = null, ?string $ua = null): array
    {
        return $this->request('POST', '/api/v1/auth/login', ['identifier' => $identifier, 'password' => $password], null, $ip, $ua);
    }
    public function me(string $token, ?string $ip = null, ?string $ua = null): array
    {
        return $this->request('GET', '/api/v1/auth/me', null, $token, $ip, $ua);
    }
    public function logout(string $token, ?string $ip = null, ?string $ua = null): array
    {
        return $this->request('POST', '/api/v1/auth/logout', ['all_sessions' => false], $token, $ip, $ua);
    }

    /** @return array{ok:bool,data:array,status:int,code:string,transport:string} */
    private function request(string $method, string $path, ?array $payload, ?string $token, ?string $ip, ?string $ua): array
    {
        $body = $payload === null ? null : json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $headers = array_merge(
            ['Accept'=>'application/json', 'Content-Type'=>'application/json', 'User-Agent'=>$ua ?: 'BabanoellIntegrations/1.1.1'],
            ServiceAuth::outbound('integrations', $method, $path, $body, Config::get('NOEEL_SERVICE_SHARED_SECRET'))
        );
        if ($token !== null && $token !== '') $headers['Authorization'] = 'Bearer '.$token;
        if ($ip !== null && filter_var($ip, FILTER_VALIDATE_IP)) $headers['X-Forwarded-For'] = $ip;
        $lines = [];
        foreach ($headers as $key => $value) $lines[] = $key.': '.$value;
        $status = 0;
        $raw = false;
        $transport = '';
        if (function_exists('curl_init')) {
            $ch = curl_init($this->base.$path);
            if ($ch === false) return self::result(0, false, 'curl_init_failed');
            curl_setopt_array($ch, [
                CURLOPT_CUSTOMREQUEST=>$method,
                CURLOPT_RETURNTRANSFER=>true,
                CURLOPT_CONNECTTIMEOUT=>4,
                CURLOPT_TIMEOUT=>8,
                CURLOPT_FOLLOWLOCATION=>false,
                CURLOPT_SSL_VERIFYPEER=>true,
                CURLOPT_SSL_VERIFYHOST=>2,
                CURLOPT_HTTPHEADER=>$lines,
            ]);
            if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            $raw = curl_exec($ch);
            $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $errno = curl_errno($ch);
            $transport = $raw === false ? match ($errno) {
                6 => 'dns_failed', 7 => 'connection_failed', 28 => 'timeout',
                35, 51, 58, 60 => 'tls_failed', default => 'network_failed',
            } : '';
            curl_close($ch);
        } else {
            $ctx = stream_context_create([
                'http'=>['method'=>$method,'header'=>implode("\r\n",$lines),
                    'content'=>$body ?? '', 'timeout'=>8,'ignore_errors'=>true,'follow_location'=>0],
                'ssl'=>['verify_peer'=>true,'verify_peer_name'=>true],
            ]);
            $raw = @file_get_contents($this->base.$path, false, $ctx);
            foreach ($http_response_header ?? [] as $line) {
                if (preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $matches)) {
                    $status = (int)$matches[1]; break;
                }
            }
            if ($raw === false) $transport = 'stream_failed';
        }
        return self::result($status, $raw, $transport);
    }

    /** @return array{ok:bool,data:array,status:int,code:string,transport:string} */
    public static function result(int $status, string|false $raw, string $transport=''): array
    {
        $json = is_string($raw) ? json_decode($raw, true) : null;
        $data = is_array($json) ? $json : [];
        $code = $data['error']['code'] ?? '';
        $code = is_string($code) && preg_match('/^[a-z0-9_]{1,64}$/', $code) ? $code : '';
        if ($raw !== false && $data === [] && $transport === '') $transport = 'invalid_json';
        return [
            'ok' => $status >= 200 && $status < 300 && is_array($json),
            'data' => $data,
            'status' => $status,
            'code' => $code,
            'transport' => $transport,
        ];
    }

    public static function explanation(array $reply, string $phase='login'): string
    {
        $status = (int)($reply['status'] ?? 0);
        $code = (string)($reply['code'] ?? '');
        $transport = (string)($reply['transport'] ?? '');
        if ($transport === 'timeout') return 'زمان پاسخ‌گویی Identity تمام شد؛ ارتباط سرور با سرویس احراز هویت را بررسی کنید.';
        if ($transport === 'dns_failed') return 'سرور قادر به شناسایی دامنه Identity نیست (DNS).';
        if ($transport === 'tls_failed') return 'اعتبار گواهی SSL یا ارتباط امن با Identity قابل تأیید نیست.';
        if (in_array($transport, ['connection_failed','network_failed','stream_failed','curl_init_failed'], true)) return 'ارتباط سرور Integrations با Identity برقرار نمی‌شود؛ تنظیمات شبکه، فایروال و آدرس Identity را بررسی کنید.';
        if ($transport === 'invalid_json') return 'Identity پاسخ معتبر JSON برنگرداند؛ مسیر API یا تنظیمات وب‌سرور را بررسی کنید.';
        if ($code === 'not_implemented' || $status === 501) return 'ورود API در Identity فعال نشده است (HTTP 501). برای استفاده در Production باید فعال‌سازی امن سرویس Identity انجام شود.';
        if (in_array($code, ['service_signature_invalid','service_auth_not_configured','service_signature_required','service_auth_required'], true)) return 'امضای امنیتی ارتباط با Identity رد شد؛ کلید مشترک و تنظیمات احراز هویت سرویس‌ها باید یکسان باشند.';
        if ($code === 'service_signature_expired') return 'امضای بین‌سرویسی منقضی شده است؛ ساعت سرورهای Identity و Integrations را هماهنگ کنید.';
        if ($code === 'invalid_credentials') return 'نام کاربری یا رمز عبور معتبر نیست.';
        if ($code === 'rate_limited' || $status === 429) return 'Identity به علت تعداد زیاد درخواست، ورود را موقتاً محدود کرده است.';
        if ($status === 401) return $phase === 'me' ? 'نشست کاربری توسط Identity پذیرفته نشد.' : 'Identity درخواست ورود را نپذیرفت؛ پاسخ امنیتی سرویس را بررسی کنید.';
        if ($status === 403) return 'Identity دسترسی به این درخواست را نداد (HTTP 403).';
        if ($status === 404) return 'مسیر ورود Identity پیدا نشد (HTTP 404)؛ آدرس پایه API را بررسی کنید.';
        if ($status >= 500) return 'Identity با خطای سرور پاسخ داده است (HTTP '.$status.').';
        if ($status >= 300 && $status < 400) return 'درخواست به آدرس دیگری هدایت شد؛ مقدار INTEGRATIONS_IDENTITY_BASE_URL را بررسی کنید.';
        if ($status === 422) return 'Identity قالب ورودی را معتبر ندانست (HTTP 422).';
        if ($status >= 200 && $status < 300) return 'پاسخ Identity ناقص است و اطلاعات نشست معتبر دریافت نشد.';
        return 'احراز هویت مرکزی انجام نشد (HTTP '.$status.').';
    }
}
