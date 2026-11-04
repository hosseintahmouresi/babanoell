<?php
declare(strict_types=1);
namespace Babanoell\Panel\Sso;

use Babanoell\Panel\App\Config;
use Babanoell\Panel\Auth\PanelSession;
use Babanoell\Panel\Http\IdentityClient;
use Babanoell\Panel\Security\TokenVault;

final class Broker
{
    public function __construct(private readonly GrantStore $store,private readonly Config $config,private readonly IdentityClient $identity) {}

    private function json(int $status, array $data): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        header('Referrer-Policy: no-referrer');
        echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        exit;
    }
    /** Verify BOTH the standard service headers and an additional body-bound MAC. */
    private function authenticate(string $raw, string $path): ?string
    {
        $secret=$this->config->serviceAuthSecret();
        $service=strtolower(trim((string)($_SERVER['HTTP_X_NOEL_SERVICE']??'')));
        $timestamp=(string)($_SERVER['HTTP_X_NOEL_TIMESTAMP']??'');
        $sig=(string)($_SERVER['HTTP_X_NOEL_SIGNATURE']??'');
        $requestId=(string)($_SERVER['HTTP_X_REQUEST_ID']??'');
        $bodySig=(string)($_SERVER['HTTP_X_NOEL_SSO_MAC']??'');
        if (strlen($secret)<32|| !GrantStore::validTarget($service)
            || !preg_match('/^[0-9]{10,12}$/D',$timestamp)
            || abs(time()-(int)$timestamp)>60
            || !preg_match('/^[a-zA-Z0-9._-]{8,128}$/D',$requestId)) return null;
        $canonical=implode("\n",[$service,$timestamp,$requestId,'POST',$path]);
        $expected='v1='.hash_hmac('sha256',$canonical,$secret);
        $bodyExpected='v1='.hash_hmac('sha256',$canonical."\n".hash('sha256',$raw),$secret);
        if (!hash_equals($expected,$sig)||!hash_equals($bodyExpected,$bodySig)) return null;
        return $service;
    }
    /** @return array<string,mixed>|null */
    private function liveAdmin(string $access, string $expectedUser): ?array
    {
        $reply=$this->identity->me($access,null,null);
        $data=$reply->json['data']??null;
        if (!$reply->ok()||!is_array($data)||!is_array($data['user']??null)) return null;
        $user=(string)($data['user']['id']??'');
        $roles=$data['roles']??[];
        if ($user===''||!hash_equals($expectedUser,$user)||!is_array($roles)||!in_array('super_admin',$roles,true)
            || (string)($data['user']['status']??'active')!=='active') return null;
        return $data;
    }
    public function authorize(PanelSession $panelSession, ?string $ip, ?string $ua): never
    {
        $service=(string)($_GET['service']??'');
        $state=(string)($_GET['state']??'');
        $challenge=(string)($_GET['challenge']??'');
        if (!GrantStore::validTarget($service)||!preg_match('/^[a-f0-9]{32,64}$/D',$state)
            || !preg_match('/^[A-Za-z0-9_-]{43}$/D',$challenge)) { http_response_code(400);exit('Invalid SSO request.'); }
        $identity=$panelSession->identity($ip,$ua);
        if ($identity===null) {
            $_SESSION['panel_sso_continue']='/sso/authorize?'.http_build_query(['service'=>$service,'state'=>$state,'challenge'=>$challenge]);
            header('Location: /login',true,302);exit;
        }
        if (!in_array('super_admin',$identity['roles']??[],true)) { http_response_code(403);exit('Access denied.'); }
        $access=$panelSession->serviceCredential();
        if ($access===null || $this->liveAdmin($access,(string)($identity['user']['id']??''))===null) { http_response_code(403);exit('Access denied.'); }
        try { $code=$this->store->issue($service,$state,$challenge,$access,(string)$identity['user']['id']); }
        catch (\Throwable) { http_response_code(503);exit('SSO unavailable.'); }
        header('Referrer-Policy: no-referrer');
        header('Cache-Control: no-store');
        header('Location: https://'.$service.'.noeel.ir/update.php?'.http_build_query(['sso_code'=>$code,'sso_state'=>$state]),true,303);
        exit;
    }
    public function api(string $path): never
    {
        if (($_SERVER['REQUEST_METHOD']??'')!=='POST') $this->json(405,['error'=>'method_not_allowed']);
        $raw=file_get_contents('php://input')?:'';
        if (strlen($raw)>4096) $this->json(413,['error'=>'payload_too_large']);
        $service=$this->authenticate($raw,$path);
        if ($service===null) $this->json(401,['error'=>'service_auth_failed']);
        $body=json_decode($raw,true);
        if (!is_array($body)) $this->json(400,['error'=>'invalid_json']);
        if ($path==='/sso/exchange') {
            $data=$this->store->exchange($service,(string)($body['state']??''),(string)($body['code']??''),(string)($body['verifier']??''));
            if ($data===null || $this->liveAdmin($data['credential'],$data['user_id'])===null)
                $this->json(401,['error'=>'invalid_or_expired_code']);
            $this->json(200,['data'=>$this->store->createGrant($service,$data['credential'],$data['user_id'])]);
        }
        $data=$this->store->getGrant($service,(string)($body['grant_id']??''),(string)($body['grant_secret']??''));
        if ($data===null||$this->liveAdmin($data['credential'],$data['user_id'])===null)
            $this->json(401,['error'=>'grant_invalid_or_revoked']);
        $this->json(200,['data'=>['valid'=>true,'user_id'=>$data['user_id']]]);
    }
}
