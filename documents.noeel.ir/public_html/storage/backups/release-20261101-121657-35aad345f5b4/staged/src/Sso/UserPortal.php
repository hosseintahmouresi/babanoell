<?php
declare(strict_types=1);
/** First-party portal handoff; does not grant direct access to any business API. */
final class NoelUserPortal
{
    private const PANEL = 'https://panel.noeel.ir';
    private const SUPPORTED = ['identity','loans','documents','partners','finance','integrations'];
    private static function b64(string $bytes): string { return rtrim(strtr(base64_encode($bytes),'+/','-_'),'='); }
    private static function secret(string $root): string
    {
        require_once $root.'/config/env.php';
        babanoell_documents_load_env();
        return trim((string)babanoell_documents_env('NOEEL_SERVICE_SHARED_SECRET',''));
    }
    private static function secure(string $root): bool
    {
        $value=strtolower((string)($_SERVER['HTTPS']??''));
        if (in_array($value,['on','1'],true) || (string)($_SERVER['SERVER_PORT']??'')==='443') return true;
        $config=is_file($root.'/config/local.env.php')?require $root.'/config/local.env.php':[];
        $raw=is_array($config)?(string)($config['NOEEL_TRUSTED_PROXIES']??''):'';
        $proxy=(string)($_SERVER['REMOTE_ADDR']??'');
        $ip=@inet_pton($proxy);
        if ($ip===false) return false;
        foreach (explode(',',$raw) as $item) {
            [$subnet,$mask]=array_pad(explode('/',trim($item),2),2,'');
            $net=@inet_pton($subnet);
            if ($net===false || strlen($net)!==strlen($ip)) continue;
            $bits=$mask===''?strlen($ip)*8:(ctype_digit($mask)?(int)$mask:-1);
            if ($bits<0 || $bits>strlen($ip)*8) continue;
            $whole=intdiv($bits,8);$part=$bits%8;
            if (substr($ip,0,$whole)!==substr($net,0,$whole)) continue;
            if ($part && ((ord($ip[$whole]) ^ ord($net[$whole])) & (0xff << (8-$part)))) continue;
            return strtolower(trim((string)($_SERVER['HTTP_X_FORWARDED_PROTO']??'')))==='https';
        }
        return false;
    }
    private static function session(string $service, string $root): void
    {
        $dir=$root.'/storage/portal-sessions';
        if (!is_dir($dir) && !@mkdir($dir,0700,true) && !is_dir($dir)) throw new RuntimeException('Private session directory unavailable');
        if (!is_writable($dir)) throw new RuntimeException('Private session directory not writable');
        session_save_path($dir);
        ini_set('session.use_strict_mode','1');
        ini_set('session.use_only_cookies','1');
        ini_set('session.cookie_httponly','1');
        session_name('noel_'.$service.'_portal');
        session_set_cookie_params(['lifetime'=>0,'path'=>'/portal.php','domain'=>'','secure'=>true,'httponly'=>true,'samesite'=>'Lax']);
        if (session_status()!==PHP_SESSION_ACTIVE) session_start();
    }
    /** Signed POST to Panel, retaining the proven updater SSO request format. */
    private static function call(string $service,string $root,string $path,array $payload): ?array
    {
        $secret=self::secret($root);
        if (strlen($secret)<32 || !in_array($service,self::SUPPORTED,true)) return null;
        $body=json_encode($payload, JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
        $timestamp=(string)time();$requestId='portal_'.bin2hex(random_bytes(16));
        $canonical=implode("\n",[$service,$timestamp,$requestId,'POST',$path]);
        $headers=['Accept: application/json','Content-Type: application/json',
            'X-Noel-Service: '.$service,'X-Noel-Timestamp: '.$timestamp,
            'X-Request-Id: '.$requestId,'X-Noel-Signature: v1='.hash_hmac('sha256',$canonical,$secret),
            'X-Noel-Sso-Mac: v1='.hash_hmac('sha256',$canonical."\n".hash('sha256',$body),$secret)];
        if (function_exists('curl_init')) {
            $ch=curl_init(self::PANEL.$path);
            if ($ch===false) return null;
            curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$body,CURLOPT_HTTPHEADER=>$headers,
                CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>3,CURLOPT_TIMEOUT=>7,
                CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_FOLLOWLOCATION=>false]);
            $raw=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);
            if (!is_string($raw)||$status!==200)return null;
        } else {
            $context=stream_context_create(['http'=>['method'=>'POST','header'=>implode("\r\n",$headers),
                'content'=>$body,'timeout'=>7,'ignore_errors'=>true,'follow_location'=>0],
                'ssl'=>['verify_peer'=>true,'verify_peer_name'=>true]]);
            $raw=@file_get_contents(self::PANEL.$path,false,$context);
            if (!is_string($raw)||!preg_match('/^HTTP\/\S+ 200(?:\s|$)/',(string)($http_response_header[0]??'')))return null;
        }
        $reply=json_decode($raw,true);
        return is_array($reply['data']??null)?$reply['data']:null;
    }
    private static function begin(string $service): never
    {
        $state=bin2hex(random_bytes(24));$verifier=self::b64(random_bytes(32));
        $_SESSION['portal_pending']=['state'=>$state,'verifier'=>$verifier,'expires'=>time()+180];
        header('Location: '.self::PANEL.'/sso/user/authorize?'.http_build_query([
            'service'=>$service,'state'=>$state,'challenge'=>self::b64(hash('sha256',$verifier,true)),
        ]),true,302);exit;
    }
    private static function callback(string $service,string $root): never
    {
        $pending=$_SESSION['portal_pending']??null;
        unset($_SESSION['portal_pending']);
        $state=(string)($_GET['sso_state']??'');$code=(string)($_GET['sso_code']??'');
        if (!is_array($pending) || (int)($pending['expires']??0)<time()
            || !preg_match('/^[a-f0-9]{48}$/D',$state)
            || !hash_equals((string)($pending['state']??''),$state)
            || !preg_match('/^[A-Za-z0-9_-]{43}$/D',$code))self::failed('درخواست ورود نامعتبر یا منقضی شده است.');
        $response=self::call($service,$root,'/sso/user/exchange',[
            'state'=>$state,'code'=>$code,'verifier'=>(string)($pending['verifier']??''),
        ]);
        if (!is_array($response) || !preg_match('/^[A-Za-z0-9_-]{32}$/D',(string)($response['id']??''))
            || !preg_match('/^[A-Za-z0-9_-]{43}$/D',(string)($response['secret']??''))
            || !is_string($response['user_id']??null) || $response['user_id']==='') {
            self::failed('اعتبار ورود مرکزی تأیید نشد.');
        }
        session_regenerate_id(true);
        $_SESSION['portal_grant']=['id'=>$response['id'],'secret'=>$response['secret'],
            'user_id'=>$response['user_id'],'expires'=>time()+min(900,(int)($response['expires_in']??900)),
            'started'=>time()];
        header('Location: /portal.php',true,303);exit;
    }
    private static function principal(string $service,string $root): ?array
    {
        $grant=$_SESSION['portal_grant']??null;
        if (!is_array($grant)) return null;
        if ((int)($grant['expires']??0)<time() || time()-(int)($grant['started']??0)>900) {
            unset($_SESSION['portal_grant']); return null;
        }
        $info=self::call($service,$root,'/sso/user/check',[
            'grant_id'=>(string)($grant['id']??''),'grant_secret'=>(string)($grant['secret']??''),
        ]);
        if (!is_array($info) || ($info['valid']??false)!==true
            || !is_string($info['user_id']??null)
            || !hash_equals((string)($grant['user_id']??''),$info['user_id'])) {
            unset($_SESSION['portal_grant']);return null;
        }
        return $info;
    }
    private static function failed(string $message): never
    {
        http_response_code(403);
        self::page('ورود تأیید نشد','<div class="bn-alert bn-alert--error">'.self::esc($message).'</div><a class="bn-btn bn-btn--primary" href="/portal.php">تلاش مجدد</a>');
    }
    private static function esc(string $text): string { return htmlspecialchars($text,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
    private static function page(string $title,string $content): never
    {
        echo '<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
           .'<meta name="robots" content="noindex,nofollow"><title>'.self::esc($title).' | نوئل</title>'
           .'<link rel="stylesheet" href="/assets/babanoell-ui.css"></head><body class="bn-body"><main class="bn-container">'
           .'<section class="bn-section"><div class="bn-pagehead"><h1 class="bn-title">'.self::esc($title).'</h1></div>'.$content.'</section></main></body></html>';
        exit;
    }
    public static function run(string $service,string $root): never
    {
        header('Cache-Control: no-store, private');header('Referrer-Policy: no-referrer');
        header('X-Frame-Options: DENY');header('X-Content-Type-Options: nosniff');
        header('Content-Security-Policy: default-src \'self\'; style-src \'self\'; form-action \'self\'; frame-ancestors \'none\'; base-uri \'none\'');
        if (!in_array($service,self::SUPPORTED,true) || !self::secure($root)) { http_response_code(403);exit('HTTPS required.'); }
        if (!is_file($root.'/storage/installed.lock') || strlen(self::secret($root))<32) { http_response_code(503);exit('Portal configuration unavailable.'); }
        try { self::session($service,$root); } catch (Throwable) { http_response_code(503);exit('Portal sessions unavailable.'); }
        if (isset($_GET['sso_code'])) self::callback($service,$root);
        if (($_SERVER['REQUEST_METHOD']??'GET')==='POST' && ($_POST['action']??'')==='local_logout') {
            $csrf=(string)($_SESSION['portal_csrf']??'');
            if ($csrf==='' || !hash_equals($csrf,(string)($_POST['csrf']??'')))self::failed('اعتبار درخواست خروج نامعتبر است.');
            $_SESSION=[];session_regenerate_id(true);
            header('Location: '.self::PANEL.'/logout/confirm',true,303);exit;
        }
        $principal=self::principal($service,$root);
        if ($principal===null) self::begin($service);
        if (!is_string($_SESSION['portal_csrf']??null)) $_SESSION['portal_csrf']=bin2hex(random_bytes(24));
        $names=['identity'=>'احراز هویت','loans'=>'تسهیلات','documents'=>'مدارک','partners'=>'همکاری و پذیرندگان','finance'=>'مالی','integrations'=>'اتصال‌دهنده‌ها'];
        $name=self::esc((string)($principal['display_name']??'کاربر سامانه'));
        $content='<p class="bn-subtitle">'.$name.'، هویت و دسترسی شما برای درگاه سرویس <strong>'.self::esc($names[$service]).'</strong> به‌صورت زنده تأیید شد.</p>'
            .'<p>عملیات پرونده، اسناد و پرداخت‌ها در پنل مرکزی نوئل انجام می‌شود و همچنان مجوز هر عملیات توسط سرویس مربوطه کنترل می‌گردد.</p>'
            .'<div class="bn-form-actions"><a class="bn-btn bn-btn--primary" href="'.self::PANEL.'/dashboard">ورود به فضای کاری</a>'
            .'<form action="/portal.php" method="post"><input type="hidden" name="csrf" value="'.self::esc($_SESSION['portal_csrf']).'">'
            .'<button class="bn-btn bn-btn--secondary" name="action" value="local_logout" type="submit">خروج از تمام سرویس‌ها</button></form></div>';
        self::page('درگاه یکپارچه '.($names[$service]??''),$content);
    }
}
