<?php
declare(strict_types=1);
namespace Babanoell\Panel\Auth;

use Babanoell\Panel\App\Config;
use Babanoell\Panel\Http\HttpResponse;
use Babanoell\Panel\Http\IdentityGatewayInterface;
use Babanoell\Panel\Security\TokenVault;
use Throwable;

final class PanelSession
{
    public function __construct(
        private readonly Config $config,
        private readonly IdentityGatewayInterface $identity,
        private readonly TokenVault $vault,
    ) {}

    /** @return array{ok:bool,message:string,code:string} */
    public function login(string $identifier, string $password, ?string $ip, ?string $userAgent): array
    {
        $identifier = trim($identifier);
        if ($identifier === '' || $password === '') return $this->fail('اطلاعات ورود را کامل وارد کنید.', 'validation');
        if (strlen($identifier) > 255 || strlen($password) > 4096) return $this->fail('اطلاعات ورود معتبر نیست.', 'validation');

        $response = $this->identity->login($identifier, $password, $ip, $userAgent);
        if (!$response->ok()) return $this->mappedError($response);

        $session = $response->json['data']['session'] ?? null;
        if (!is_array($session) || !is_string($session['session_credential'] ?? null) || ($session['session_credential'] ?? '') === '') {
            return $this->fail('پاسخ سرویس احراز هویت کامل نبود. دوباره تلاش کنید.', 'invalid_identity_response');
        }

        try {
            $access = (string) $session['session_credential'];
            $_SESSION['panel_access'] = $this->vault->seal($access);
            if (is_string($session['refresh_credential'] ?? null) && $session['refresh_credential'] !== '') {
                $_SESSION['panel_refresh'] = $this->vault->seal((string) $session['refresh_credential']);
            } else {
                unset($_SESSION['panel_refresh']);
            }
            $_SESSION['panel_access_expires_at'] = (string) ($session['access_expires_at'] ?? '');
            $_SESSION['panel_refresh_expires_at'] = (string) ($session['refresh_expires_at'] ?? '');
        } catch (Throwable) {
            $this->clearLocal();
            return $this->fail('نشست امن ایجاد نشد. دوباره تلاش کنید.', 'session_protection_failed');
        }

        $me = $this->identity->me($access, $ip, $userAgent);
        if (!$me->ok()) {
            $this->clearLocal();
            return $this->mappedError($me);
        }
        if (!$this->acceptIdentity($me)) {
            $this->clearLocal();
            return $this->fail('اطلاعات حساب کاربری قابل تأیید نبود.', 'invalid_identity_response');
        }

        session_regenerate_id(true);
        $_SESSION['panel_login_at'] = time();
        $_SESSION['panel_last_seen_at'] = time();
        return ['ok' => true, 'message' => '', 'code' => 'ok'];
    }

    /** @return array<string,mixed>|null */
    public function identity(?string $ip, ?string $userAgent): ?array
    {
        if (!$this->withinLocalLifetime()) {
            $this->clearLocal();
            return null;
        }
        $access = $this->credential('panel_access');
        if ($access === null) return null;

        $me = $this->identity->me($access, $ip, $userAgent);
        if ($me->ok() && $this->acceptIdentity($me)) {
            $_SESSION['panel_last_seen_at'] = time();
            return $this->cachedIdentity();
        }

        if ($me->status === 401) {
            $refresh = $this->credential('panel_refresh');
            if ($refresh !== null) {
                $rotated = $this->identity->refresh($refresh, $ip, $userAgent);
                if ($rotated->ok() && $this->acceptRotation($rotated)) {
                    $newAccess = $this->credential('panel_access');
                    if ($newAccess !== null) {
                        $retry = $this->identity->me($newAccess, $ip, $userAgent);
                        if ($retry->ok() && $this->acceptIdentity($retry)) {
                            $_SESSION['panel_last_seen_at'] = time();
                            return $this->cachedIdentity();
                        }
                    }
                }
            }
            $this->clearLocal();
            return null;
        }

        // Fail closed when Identity cannot validate the current access credential.
        // Credentials stay server-side so a later retry can recover, but no
        // protected page is rendered from stale cached authorization data.
        return null;
    }

    public function logout(?string $ip, ?string $userAgent, bool $allSessions = false): void
    {
        $access = $this->credential('panel_access');
        if ($access !== null) {
            $this->identity->logout($access, $allSessions, $ip, $userAgent);
        }
        $this->clearLocal();
        session_regenerate_id(true);
    }

    public function isSuperAdmin(): bool
    {
        $roles = $_SESSION['panel_identity']['roles'] ?? [];
        return is_array($roles) && in_array('super_admin', $roles, true);
    }

    /** Server-side access credential for trusted service-to-service calls. Never render it. */
    public function serviceCredential(): ?string
    {
        return $this->credential('panel_access');
    }

    /** @return array<string,mixed>|null */
    public function cachedIdentity(): ?array
    {
        $value = $_SESSION['panel_identity'] ?? null;
        return is_array($value) ? $value : null;
    }

    private function withinLocalLifetime(): bool
    {
        $login = (int) ($_SESSION['panel_login_at'] ?? 0);
        $last = (int) ($_SESSION['panel_last_seen_at'] ?? 0);
        $now = time();
        if ($login <= 0 || $last <= 0) return false;
        return ($now - $last) <= $this->config->sessionIdleSeconds() && ($now - $login) <= $this->config->sessionAbsoluteSeconds();
    }

    private function acceptIdentity(HttpResponse $response): bool
    {
        $data = $response->json['data'] ?? null;
        $user = is_array($data) ? ($data['user'] ?? null) : null;
        if (!is_array($user) || !is_string($user['id'] ?? null) || ($user['id'] ?? '') === '') return false;
        $_SESSION['panel_identity'] = [
            'user' => [
                'id' => (string) $user['id'],
                'display_name' => is_string($user['display_name'] ?? null) ? $user['display_name'] : 'کاربر سامانه',
                'status' => is_string($user['status'] ?? null) ? $user['status'] : 'active',
            ],
            'roles' => array_values(array_filter(is_array($data['roles'] ?? null) ? $data['roles'] : [], 'is_string')),
            'permissions' => array_values(array_filter(is_array($data['permissions'] ?? null) ? $data['permissions'] : [], 'is_string')),
            'session' => is_array($data['session'] ?? null) ? $data['session'] : [],
        ];
        return true;
    }

    private function acceptRotation(HttpResponse $response): bool
    {
        $session = $response->json['data']['session'] ?? null;
        if (!is_array($session) || !is_string($session['session_credential'] ?? null) || $session['session_credential'] === '') return false;
        try {
            $_SESSION['panel_access'] = $this->vault->seal($session['session_credential']);
            if (is_string($session['refresh_credential'] ?? null) && $session['refresh_credential'] !== '') {
                $_SESSION['panel_refresh'] = $this->vault->seal($session['refresh_credential']);
            }
            $_SESSION['panel_access_expires_at'] = (string) ($session['access_expires_at'] ?? '');
            $_SESSION['panel_refresh_expires_at'] = (string) ($session['refresh_expires_at'] ?? '');
            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function credential(string $key): ?string
    {
        $sealed = $_SESSION[$key] ?? null;
        if (!is_string($sealed) || $sealed === '') return null;
        try { return $this->vault->open($sealed); } catch (Throwable) { return null; }
    }

    private function clearLocal(): void
    {
        foreach (['panel_access','panel_refresh','panel_access_expires_at','panel_refresh_expires_at','panel_identity','panel_login_at','panel_last_seen_at', 'panel_user_sso_browser_id'] as $key) unset($_SESSION[$key]);
    }

    /** @return array{ok:false,message:string,code:string} */
    private function mappedError(HttpResponse $response): array
    {
        if ($response->status === 0) return $this->fail('ارتباط با سرویس احراز هویت برقرار نشد. چند لحظه دیگر دوباره تلاش کنید.', 'identity_unreachable');
        return match ($response->errorCode()) {
            'invalid_credentials' => $this->fail('موبایل/ایمیل یا رمز عبور صحیح نیست.', 'invalid_credentials'),
            'rate_limited' => $this->fail('تعداد تلاش‌های ورود زیاد بوده است. کمی بعد دوباره تلاش کنید.', 'rate_limited'),
            'not_implemented', 'authentication_inactive' => $this->fail('ورود مرکزی هنوز برای این محیط فعال نشده است.', 'auth_inactive'),
            'auth_unavailable' => $this->fail('سرویس احراز هویت موقتاً در دسترس نیست.', 'auth_unavailable'),
            default => $this->fail('ورود انجام نشد. دوباره تلاش کنید.', 'login_failed'),
        };
    }

    /** @return array{ok:false,message:string,code:string} */
    private function fail(string $message, string $code): array { return ['ok' => false, 'message' => $message, 'code' => $code]; }
}
