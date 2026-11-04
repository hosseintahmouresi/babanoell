<?php
declare(strict_types=1);

/**
 * Phase 11D: browser redirect + PKCE + signed server-to-server exchange.
 * Does not share browser cookies, passwords or Identity bearer credentials.
 */
final class NoelUpdaterSso
{
    private const PANEL_URL = 'https://panel.noeel.ir';
    private const SERVICES = ['identity','loans','documents','partners','finance','integrations'];
    private static function b64(string $bytes): string {return rtrim(strtr(base64_encode($bytes),'+/','-_'),'=');}
    private static function secret(string $root): string
    {
        require_once $root.'/config/env.php';
        babanoell_documents_load_env();
        return trim((string)babanoell_documents_env('NOEEL_SERVICE_SHARED_SECRET',''));
    }
    public static function loginLink(): string
    {
        return '<div class="bn-form-actions"><a class="bn-btn bn-btn--secondary" href="/update.php?sso_start=1">ورود یکپارچه با حساب پنل نوئل</a></div>';
    }
    private static function secure(string $service): bool
    {
        $value=strtolower((string)($_SERVER['HTTPS']??''));
        if (in_array($value,['on','1'],true)||($_SERVER['SERVER_PORT']??'')==='443')return true;
        $names=['identity'=>'Auth','loans'=>'Loans','documents'=>'Documents','partners'=>'Partners','finance'=>'Finance','integrations'=>'Integrations'];
        $class='\\Babanoell\\'.($names[$service]??'').'\\Platform\\ProxyContext';
        return class_exists($class) && method_exists($class,'secure') && $class::secure($_SERVER);
    }
    public static function begin(string $service,string $root): never
    {
        if (!in_array($service,self::SERVICES,true) || strlen(self::secret($root))<32 || !self::secure($service)) {
            http_response_code(503);exit('SSO prerequisites unavailable.');
        }
        $state=bin2hex(random_bytes(24));
        $verifier=self::b64(random_bytes(32));
        $challenge=self::b64(hash('sha256',$verifier,true));
        $_SESSION['noel_sso_pending']=['state'=>$state,'verifier'=>$verifier,'until'=>time()+180];
        header('Cache-Control: no-store');header('Referrer-Policy: no-referrer');
        header('Location: '.self::PANEL_URL.'/sso/authorize?'.http_build_query([
            'service'=>$service,'state'=>$state,'challenge'=>$challenge,
        ]),true,302);exit;
    }
    /** @return array<string,mixed>|null */
    private static function call(string $service,string $root,string $path,array $payload): ?array
    {
        $secret=self::secret($root);
        if (strlen($secret)<32||!in_array($service,self::SERVICES,true))return null;
        $body=json_encode($payload,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
        $timestamp=(string)time();
        $requestId='sso_'.bin2hex(random_bytes(16));
        $canonical=implode("\n",[$service,$timestamp,$requestId,'POST',$path]);
        $headers=['Accept: application/json','Content-Type: application/json',
            'X-Noel-Service: '.$service,'X-Noel-Timestamp: '.$timestamp,
            'X-Request-Id: '.$requestId,'X-Noel-Signature: v1='.hash_hmac('sha256',$canonical,$secret),
            'X-Noel-Sso-Mac: v1='.hash_hmac('sha256',$canonical."\n".hash('sha256',$body),$secret)];
        $url=self::PANEL_URL.$path;
        if (function_exists('curl_init')) {
            $ch=curl_init($url);
            if ($ch===false)return null;
            curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$body,CURLOPT_HTTPHEADER=>$headers,
                CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>3,CURLOPT_TIMEOUT=>7,
                CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_FOLLOWLOCATION=>false]);
            $raw=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);
            if (!is_string($raw)||$status!==200)return null;
        }else{
            $ctx=stream_context_create(['http'=>['method'=>'POST','header'=>implode("\r\n",$headers),
                'content'=>$body,'timeout'=>7,'ignore_errors'=>true,'follow_location'=>0],
                'ssl'=>['verify_peer'=>true,'verify_peer_name'=>true]]);
            $raw=@file_get_contents($url,false,$ctx);
            if(!is_string($raw)||!preg_match('/^HTTP\/\S+ 200(?:\s|$)/',(string)($http_response_header[0]??'')))return null;
        }
        $reply=json_decode($raw,true);
        return is_array($reply['data']??null)?$reply['data']:null;
    }
    public static function callback(string $service,string $root): never
    {
        $pending=$_SESSION['noel_sso_pending']??null;
        unset($_SESSION['noel_sso_pending']);
        $code=(string)($_GET['sso_code']??'');$state=(string)($_GET['sso_state']??'');
        if (!self::secure($service) || !is_array($pending) || time()>(int)($pending['until']??0)
            || !preg_match('/^[a-f0-9]{48}$/D',$state) || !hash_equals((string)$pending['state'],$state)
            || !preg_match('/^[A-Za-z0-9_-]{43}$/D',$code)) {
            http_response_code(403);exit('SSO verification failed. Use manual login.');
        }
        $result=self::call($service,$root,'/sso/exchange',[
            'state'=>$state,'code'=>$code,'verifier'=>(string)$pending['verifier'],
        ]);
        $id=(string)($result['id']??'');$secret=(string)($result['secret']??'');$user=(string)($result['user_id']??'');
        if (!preg_match('/^[A-Za-z0-9_-]{32}$/D',$id)||!preg_match('/^[A-Za-z0-9_-]{43}$/D',$secret)||$user==='') {
            http_response_code(403);exit('SSO authorization failed. Use manual login.');
        }
        session_regenerate_id(true);
        $_SESSION['noel_sso_grant']=['id'=>$id,'secret'=>$secret,'user_id'=>$user];
        if ($service==='identity') {
            $_SESSION['update_admin']=['id'=>$user,'name'=>'مدیر ارشد (Identity)','login_at'=>gmdate('c')];
        } else {
            $_SESSION[$service.'_updater_super_admin']=true;
            $_SESSION[$service.'_updater_admin_until']=time()+1200;
        }
        header('Location: /update.php',true,303);exit;
    }
    public static function active(string $service,string $root): ?bool
    {
        $grant=$_SESSION['noel_sso_grant']??null;
        if (!is_array($grant))return null; // legacy auth remains a separate safe fallback
        $data=self::call($service,$root,'/sso/check',[
            'grant_id'=>(string)($grant['id']??''),'grant_secret'=>(string)($grant['secret']??''),
        ]);
        $valid=($data['valid']??false)===true&&is_string($data['user_id']??null)
            &&hash_equals((string)($grant['user_id']??''),$data['user_id']);
        if (!$valid) {
            unset($_SESSION['noel_sso_grant']);
            if ($service==='identity') unset($_SESSION['update_admin']);
            else {unset($_SESSION[$service.'_updater_super_admin'],$_SESSION[$service.'_updater_admin_until']);}
        }
        return $valid;
    }
    public static function dispatch(string $service,string $root): void
    {
        if (($_SERVER['REQUEST_METHOD']??'GET')==='GET' && isset($_GET['sso_start'])) self::begin($service,$root);
        if (($_SERVER['REQUEST_METHOD']??'GET')==='GET' && isset($_GET['sso_code'])) self::callback($service,$root);
        self::active($service,$root);
    }
}
