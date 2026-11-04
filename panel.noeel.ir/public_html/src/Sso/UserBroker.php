<?php
declare(strict_types=1);
namespace Babanoell\Panel\Sso;

use Babanoell\Panel\App\Config;
use Babanoell\Panel\Auth\PanelSession;
use Babanoell\Panel\Http\IdentityClient;

/** First-party user SSO. Admin updater tickets have separate endpoints and stores. */
final class UserBroker
{
    public function __construct(private readonly GrantStore $store, private readonly Config $config, private readonly IdentityClient $identity) {}

    private function json(int $status, array $data): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        header('Referrer-Policy: no-referrer');
        echo json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        exit;
    }
    private function authenticatedService(string $raw, string $path): ?string
    {
        $secret=$this->config->serviceAuthSecret();
        $service=strtolower(trim((string)($_SERVER['HTTP_X_NOEL_SERVICE']??'')));
        $timestamp=(string)($_SERVER['HTTP_X_NOEL_TIMESTAMP']??'');
        $requestId=(string)($_SERVER['HTTP_X_REQUEST_ID']??'');
        if (strlen($secret)<32 || !UserRolePolicy::target($service)
            || !preg_match('/^[0-9]{10,12}$/D',$timestamp) || abs(time()-(int)$timestamp)>60
            || !preg_match('/^[a-zA-Z0-9._-]{8,128}$/D',$requestId)) return null;
        $canonical=implode("\n",[$service,$timestamp,$requestId,'POST',$path]);
        if (!hash_equals('v1='.hash_hmac('sha256',$canonical,$secret),(string)($_SERVER['HTTP_X_NOEL_SIGNATURE']??''))
            || !hash_equals('v1='.hash_hmac('sha256',$canonical."\n".hash('sha256',$raw),$secret),(string)($_SERVER['HTTP_X_NOEL_SSO_MAC']??''))) return null;
        return $service;
    }
    /** Only live, active Identity users whose current permissions allow the service. */
    private function liveUser(string $access, string $userId, string $service): ?array
    {
        $reply=$this->identity->me($access,null,null);
        $data=$reply->json['data']??null;
        if (!$reply->ok() || !is_array($data) || !is_array($data['user']??null)) return null;
        $actual=(string)($data['user']['id']??'');
        if ($actual==='' || !hash_equals($userId,$actual)
            || (string)($data['user']['status']??'')!=='active') return null;
        $roles=is_array($data['roles']??null)?$data['roles']:[];
        $permissions=is_array($data['permissions']??null)?$data['permissions']:[];
        if (!UserRolePolicy::allowed($service,$roles,$permissions)) return null;
        return [
            'user_id'=>$actual,
            'display_name'=>is_string($data['user']['display_name']??null)?substr($data['user']['display_name'],0,480):'کاربر سامانه',
            'roles'=>array_values(array_filter($roles,'is_string')),
            'permissions'=>array_values(array_filter($permissions,'is_string')),
        ];
    }
    public function authorize(PanelSession $panelSession, ?string $ip, ?string $ua): never
    {
        $service=(string)($_GET['service']??'');$state=(string)($_GET['state']??'');$challenge=(string)($_GET['challenge']??'');
        if (!UserRolePolicy::target($service) || !preg_match('/^[a-f0-9]{48}$/D',$state)
            || !preg_match('/^[A-Za-z0-9_-]{43}$/D',$challenge)) { http_response_code(400);exit('Invalid SSO request.'); }
        $identity=$panelSession->identity($ip,$ua);
        if ($identity===null) {
            $_SESSION['panel_sso_continue']='/sso/user/authorize?'.http_build_query(['service'=>$service,'state'=>$state,'challenge'=>$challenge]);
            header('Location: /login',true,302);exit;
        }
        $access=$panelSession->serviceCredential();$user=(string)($identity['user']['id']??'');
        if ($access===null || $user==='' || $this->liveUser($access,$user,$service)===null) {
            http_response_code(403);exit('SSO access denied.');
        }
        if (!is_string($_SESSION['panel_user_sso_browser_id']??null) || !preg_match('/^[a-f0-9]{48}$/D',$_SESSION['panel_user_sso_browser_id'])) {
            $_SESSION['panel_user_sso_browser_id']=bin2hex(random_bytes(24));
        }
        try { $code=$this->store->issueUser($service,$state,$challenge,$access,$user,$_SESSION['panel_user_sso_browser_id']); }
        catch (\Throwable) { http_response_code(503);exit('SSO unavailable.'); }
        header('Cache-Control: no-store');header('Referrer-Policy: no-referrer');
        header('Location: https://'.$service.'.noeel.ir/portal.php?'.http_build_query(['sso_code'=>$code,'sso_state'=>$state]),true,303);
        exit;
    }
    public function api(string $path): never
    {
        if (($_SERVER['REQUEST_METHOD']??'')!=='POST') $this->json(405,['error'=>'method_not_allowed']);
        $raw=file_get_contents('php://input')?:'';
        if (strlen($raw)>4096) $this->json(413,['error'=>'payload_too_large']);
        $service=$this->authenticatedService($raw,$path);
        if ($service===null) $this->json(401,['error'=>'service_auth_failed']);
        $body=json_decode($raw,true);
        if (!is_array($body)) $this->json(400,['error'=>'invalid_json']);
        if ($path==='/sso/user/exchange') {
            $data=$this->store->exchangeUser($service,(string)($body['state']??''),(string)($body['code']??''),(string)($body['verifier']??''));
            if ($data===null || $this->liveUser($data['credential'],$data['user_id'],$service)===null)
                $this->json(401,['error'=>'invalid_or_expired_code']);
            $this->json(200,['data'=>$this->store->createUserGrant($service,$data['credential'],$data['user_id'],$data['browser_id'])]);
        }
        $grant=$this->store->getUserGrant($service,(string)($body['grant_id']??''),(string)($body['grant_secret']??''));
        if ($grant===null) $this->json(401,['error'=>'invalid_or_expired_grant']);
        $live=$this->liveUser($grant['credential'],$grant['user_id'],$service);
        if ($live===null) $this->json(401,['error'=>'session_revoked_or_access_denied']);
        $this->json(200,['data'=>$live+['valid'=>true]]);
    }
}
