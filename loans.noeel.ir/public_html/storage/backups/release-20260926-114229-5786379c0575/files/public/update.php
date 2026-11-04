<?php
declare(strict_types=1);

use Babanoell\Loans\Database\MigrationRunner;
use Babanoell\Loans\Update\UpdateTool;
use Babanoell\Loans\View\Renderer;

$root=dirname(__DIR__);require_once $root.'/src/Platform/ProxyContext.php';require_once $root.'/config/env.php';$storage=$root.'/storage';$lock=$storage.'/installed.lock';$local=$root.'/config/local.env.php';if(!is_file($lock)||!is_file($local)){header('Location:/install.php',true,302);exit;}foreach([$storage.'/tmp',$storage.'/backups']as$d)if(!is_dir($d))@mkdir($d,0770,true);if(is_dir($storage.'/tmp')&&is_writable($storage.'/tmp'))session_save_path($storage.'/tmp');session_name('babanoell_loans_update');session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>\Babanoell\Loans\Platform\ProxyContext::secure($_SERVER),'httponly'=>true,'samesite'=>'Strict']);session_start();
require$root.'/bootstrap.php';
if(strtolower($config->environment())==='production'&&!\Babanoell\Loans\Platform\ProxyContext::secure($_SERVER)){http_response_code(403);exit('HTTPS is required.');}
header('X-Content-Type-Options: nosniff');header('X-Frame-Options: DENY');header('Referrer-Policy: no-referrer');header('X-Robots-Tag: noindex,nofollow,noarchive');header('Cache-Control:no-store,max-age=0');header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
function up_csrf():string{if(!is_string($_SESSION['_csrf']??null))$_SESSION['_csrf']=bin2hex(random_bytes(24));return$_SESSION['_csrf'];}function up_csrf_ok():bool{return is_string($_POST['_csrf']??null)&&is_string($_SESSION['_csrf']??null)&&hash_equals($_SESSION['_csrf'],$_POST['_csrf']);}
function up_rate_file(string$s):string{return$s.'/.update-rate-limit.json';}function up_rate_check(string$s):array{$f=up_rate_file($s);$d=is_file($f)?json_decode((string)@file_get_contents($f),true):[];$now=time();$until=(int)($d['locked_until']??0);if($until>$now)return[false,$until-$now];if($until>0){@unlink($f);$d=[];}return[true,0];}function up_rate_fail(string$s):void{$f=up_rate_file($s);$d=is_file($f)?json_decode((string)@file_get_contents($f),true):[];$now=time();$start=(int)($d['window_started_at']??0);$attempts=($start>0&&$now-$start<900)?(int)($d['attempts']??0)+1:1;$out=['window_started_at'=>$start>0&&$now-$start<900?$start:$now,'attempts'=>$attempts,'locked_until'=>$attempts>=5?$now+900:0];@file_put_contents($f,json_encode($out),LOCK_EX);}function up_rate_clear(string$s):void{@unlink(up_rate_file($s));}
$ip=\Babanoell\Loans\Platform\ProxyContext::clientIp($_SERVER);$ua=is_string($_SERVER['HTTP_USER_AGENT']??null)?substr($_SERVER['HTTP_USER_AGENT'],0,512):null;$message='';$messageType='';
if(isset($_POST['logout'])&&up_csrf_ok()){$_SESSION=[];session_regenerate_id(true);header('Location:/update.php',true,303);exit;}
$authorized=(int)($_SESSION['loans_updater_admin_until']??0)>time()&&($_SESSION['loans_updater_super_admin']??false)===true;
if (!$authorized && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['identifier'])) {
    if (!up_csrf_ok()) {
        $message = 'اعتبار فرم منقضی شده است.';
        $messageType = 'error';
    } else {
        [$allowed, $wait] = up_rate_check($storage);
        if (!$allowed) {
            $message = 'ورود موقتاً قفل شده است. حدود ' . ceil($wait / 60) . ' دقیقه دیگر تلاش کنید.';
            $messageType = 'error';
        } else {
            $r = $identityClient->login(trim((string) $_POST['identifier']), (string) ($_POST['password'] ?? ''), $ip, $ua);
            $token = is_array($r->json['data']['session'] ?? null) ? (string) ($r->json['data']['session']['session_credential'] ?? '') : '';
            if (!$r->ok() || $token === '') {
                up_rate_fail($storage);
                $message = 'موبایل/ایمیل یا رمز عبور معتبر نیست، یا ورود مرکزی در این محیط فعال نیست.';
                $messageType = 'error';
            } else {
                $me = $identityClient->me($token, $ip, $ua);
                $roles = is_array($me->json['data']['roles'] ?? null) ? $me->json['data']['roles'] : [];
                $identityClient->logout($token, $ip, $ua);
                if (!$me->ok() || !in_array('super_admin', $roles, true)) {
                    up_rate_fail($storage);
                    $message = 'این حساب مجوز مدیریت سرویس را ندارد.';
                    $messageType = 'error';
                } else {
                    up_rate_clear($storage);
                    $_SESSION['loans_updater_admin_until'] = time() + 1200;
                    $_SESSION['loans_updater_super_admin'] = true;
                    session_regenerate_id(true);
                    header('Location:/update.php', true, 303);
                    exit;
                }
            }
        }
    }
}
$authorized=(int)($_SESSION['loans_updater_admin_until']??0)>time()&&($_SESSION['loans_updater_super_admin']??false)===true;
if(!$authorized){$alert=$message!==''?'<div class="bn-alert bn-alert--error"><strong>ورود انجام نشد</strong><br>'.Renderer::e($message).'</div>':'';$content='<div class="bn-pagehead"><div class="bn-pagehead-main"><div class="bn-eyebrow">مرکز به‌روزرسانی امن</div><h1 class="bn-title">مدیریت نسخه سرویس تسهیلات</h1><p class="bn-subtitle">ورود این صفحه فقط برای مدیر ارشد Identity مجاز است.</p></div></div>'.$alert.'<form method="post" autocomplete="on"><input type="hidden" name="_csrf" value="'.Renderer::e(up_csrf()).'"><div class="bn-field"><label class="bn-label">موبایل یا ایمیل مدیر</label><input name="identifier" autocomplete="username" required></div><div class="bn-field"><label class="bn-label">رمز عبور</label><input name="password" type="password" autocomplete="current-password" required></div><div class="bn-form-actions"><button class="bn-btn bn-btn--primary" type="submit">ورود به مرکز به‌روزرسانی</button></div></form>';echo Renderer::page('به‌روزرسانی سرویس تسهیلات',$content,'مرکز به‌روزرسانی');exit;}
if(($_SERVER['REQUEST_METHOD']??'')==='POST' && isset($_POST['recover']) && up_csrf_ok()){
 try{\Babanoell\Loans\Platform\ReleaseTransaction::recover($root,$storage,($_POST['database_restored']??'')==='1');header('Location: /update.php',true,303);exit;}
 catch(Throwable $e){echo Renderer::page('خطای بازیابی','<div class="bn-alert bn-alert--error">'.Renderer::e($e->getMessage()).'</div>');exit;}
}
if(\Babanoell\Loans\Platform\ReleaseTransaction::pending($storage)){
 echo Renderer::page('بازیابی نسخه',\Babanoell\Loans\Platform\Ui::recoveryForm('_csrf',up_csrf(),'recover','1'));exit;
}
\Babanoell\Loans\Platform\ReleaseTransaction::syncVersion($root);
$zipPath=$storage.'/update.zip';$tool=new UpdateTool();$plan=null;
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'&&!isset($_POST['identifier'])){
 if(!up_csrf_ok()){$message='اعتبار فرم منقضی شده است. صفحه را تازه‌سازی کنید.';$messageType='error';}
 elseif(isset($_POST['upload_validate'])){if(isset($_FILES['package'])&&is_array($_FILES['package'])&&($_FILES['package']['error']??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_OK){if(!is_uploaded_file((string)$_FILES['package']['tmp_name'])||!move_uploaded_file((string)$_FILES['package']['tmp_name'],$zipPath)){$message='ذخیره فایل آپلودشده انجام نشد.';$messageType='error';}}if($message===''){try{$plan=$tool->plan($zipPath);$_SESSION['loans_update_sha']=hash_file('sha256',$zipPath);$message='بسته معتبر است و آماده اعمال می‌باشد.';$messageType='success';}catch(Throwable$e){$message='بسته قابل اعمال نیست: '.$e->getMessage();$messageType='error';}}}
 elseif(isset($_POST['apply_update'])){try{if(!is_file($zipPath))throw new RuntimeException('فایل storage/update.zip پیدا نشد.');$sha=hash_file('sha256',$zipPath);if(!is_string($_SESSION['loans_update_sha']??null)||!hash_equals($_SESSION['loans_update_sha'],$sha))throw new RuntimeException('بسته پس از بررسی تغییر کرده است؛ دوباره «بررسی بسته» را اجرا کنید.');$result=\Babanoell\Loans\Platform\ReleaseTransaction::apply($zipPath,$root,$storage,$sha,static fn():array=>(new MigrationRunner($pdo,$root.'/database/migrations'))->up());$migrations=$result['migrations'];unset($_SESSION['loans_update_sha']);$message='به‌روزرسانی با موفقیت اعمال شد؛ '.$result['copied'].' فایل به‌روزرسانی و '.count($migrations).' تغییر دیتابیس اعمال شد.';$messageType='success';}catch(Throwable$e){@file_put_contents($storage.'/update-error.log','['.gmdate('c').'] '.get_class($e).': '.$e->getMessage()."\n",FILE_APPEND|LOCK_EX);$message='به‌روزرسانی کامل نشد: '.$e->getMessage();$messageType='error';}}
}
if($plan===null&&is_file($zipPath)){try{$plan=$tool->plan($zipPath);}catch(Throwable){$plan=null;}}
$status=(new MigrationRunner($pdo,$root.'/database/migrations'))->status();$pending=count(array_filter($status['migrations'],static fn($m)=>($m['status']??'')==='pending'));$alert=$message!==''?'<div class="bn-alert bn-alert--'.$messageType.'"><strong>'.($messageType==='success'?'عملیات انجام شد':'عملیات کامل نشد').'</strong><br>'.Renderer::e($message).'</div>':'';$fileState=is_file($zipPath)?'فایل آماده است: update.zip':'هنوز بسته‌ای در storage/update.zip وجود ندارد.';
$content='<div class="bn-pagehead"><div class="bn-pagehead-main"><div class="bn-eyebrow">Loans 1.7.0</div><h1 class="bn-title">به‌روزرسانی سرویس تسهیلات</h1><p class="bn-subtitle">سلامت بسته، سازگاری فایل‌ها و تغییرات پایگاه داده پیش از نصب بررسی می‌شوند.</p></div><div class="bn-actions"><form method="post"><input type="hidden" name="_csrf" value="'.Renderer::e(up_csrf()).'"><button class="bn-btn bn-btn--secondary" name="logout" value="1">خروج</button></form></div></div>'.$alert.'<div class="bn-stats"><div class="bn-stat"><div class="bn-stat-label">ساختارهای دیتابیس</div><div class="bn-stat-value">'.count($status['migrations']).'</div></div><div class="bn-stat"><div class="bn-stat-label">تغییرات در انتظار</div><div class="bn-stat-value">'.$pending.'</div></div><div class="bn-stat bn-stat--accent"><div class="bn-stat-label">وضعیت بسته</div><div class="bn-stat-value">'.($plan!==null?'آماده':'منتظر فایل').'</div></div></div><section class="bn-section"><div class="bn-sectionhead"><span class="bn-step">۱</span><div><h2 class="bn-sectiontitle">بسته به‌روزرسانی</h2><p class="bn-sectiondesc">'.Renderer::e($fileState).'</p></div></div><form method="post" enctype="multipart/form-data"><input type="hidden" name="_csrf" value="'.Renderer::e(up_csrf()).'"><div class="bn-field"><label class="bn-label">فایل ZIP نسخه جدید</label><input type="file" name="package" accept=".zip,application/zip"></div><div class="bn-form-actions"><button class="bn-btn bn-btn--primary" name="upload_validate" value="1">بررسی بسته</button></div></form></section>';
if($plan!==null)$content.='<section class="bn-section"><div class="bn-sectionhead"><span class="bn-step">۲</span><div><h2 class="bn-sectiontitle">تأیید و اعمال</h2><p class="bn-sectiondesc">'.count($plan['files']).' فایل قابل اعمال شناسایی شد؛ '.$plan['protected'].' مسیر محافظت‌شده تغییر نمی‌کند.</p></div></div><div class="bn-alert bn-alert--warning"><strong>قبل از اعمال:</strong> از پایگاه داده تسهیلات در phpMyAdmin یک خروجی پشتیبان کامل بگیرید.</div><form method="post"><input type="hidden" name="_csrf" value="'.Renderer::e(up_csrf()).'"><button class="bn-btn bn-btn--primary" name="apply_update" value="1">اعمال نسخه تأییدشده</button></form></section>';
echo Renderer::page('به‌روزرسانی سرویس تسهیلات',$content,'مرکز به‌روزرسانی');
