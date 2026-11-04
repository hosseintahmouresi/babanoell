<?php
declare(strict_types=1);

use Babanoell\Integrations\Database\Migrator;
use Babanoell\Integrations\Http\IdentityAdminClient;
use Babanoell\Integrations\Platform\ProxyContext;
use Babanoell\Integrations\Platform\ReleaseTransaction;
use Babanoell\Integrations\Platform\RequestContext;
use Babanoell\Integrations\Support\Config;
use Babanoell\Integrations\Update\UpdateTool;
use Babanoell\Integrations\View\Renderer;

$root=dirname(__DIR__);require $root.'/bootstrap.php';
$storage=$root.'/storage';
if(!is_file($storage.'/installed.lock')||!is_file($root.'/config/local.env.php')){header('Location: /install.php',true,302);exit;}
foreach([$storage.'/tmp',$storage.'/backups'] as $dir)if(!is_dir($dir)&&!@mkdir($dir,0700,true)&&!is_dir($dir)){http_response_code(503);exit('Storage unavailable');}
if(Config::production()&&!ProxyContext::secure($_SERVER)){http_response_code(403);exit('HTTPS required.');}
if(is_writable($storage.'/tmp'))session_save_path($storage.'/tmp');
session_name('noeel_integrations_update');
session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>ProxyContext::secure($_SERVER),'httponly'=>true,'samesite'=>'Strict']);
if(!session_start()){http_response_code(503);exit('Session unavailable');}
header('X-Content-Type-Options: nosniff');header('X-Frame-Options: DENY');header('Referrer-Policy: no-referrer');
header('X-Robots-Tag: noindex,nofollow,noarchive');header('Cache-Control: no-store,max-age=0');
header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
RequestContext::initializeFromServer($_SERVER,'integrations');

function int_csrf():string{if(!is_string($_SESSION['_csrf']??null))$_SESSION['_csrf']=bin2hex(random_bytes(24));return $_SESSION['_csrf'];}
function int_csrf_ok():bool{return is_string($_POST['_csrf']??null)&&is_string($_SESSION['_csrf']??null)&&hash_equals($_SESSION['_csrf'],$_POST['_csrf']);}
function int_rate_check(string $file):array{
 $data=is_file($file)?json_decode((string)@file_get_contents($file),true):[];$now=time();$until=(int)($data['locked_until']??0);
 return [$until<=$now,max(0,$until-$now)];
}
function int_rate_fail(string $file):void{
 $data=is_file($file)?json_decode((string)@file_get_contents($file),true):[];$now=time();$start=(int)($data['window_started_at']??0);
 $count=$start>0&&$now-$start<900?(int)($data['attempts']??0)+1:1;
 @file_put_contents($file,json_encode(['window_started_at'=>($start>0&&$now-$start<900?$start:$now),
     'attempts'=>$count,'locked_until'=>$count>=5?$now+900:0]),LOCK_EX);
}
function int_auth_log(string $storage,string $stage,array $reply):void{
 $code=(string)($reply['code']??'');$transport=(string)($reply['transport']??'');
 $entry=['at'=>gmdate('c'),'request_id'=>RequestContext::id(),'stage'=>$stage,
   'status'=>(int)($reply['status']??0),
   'code'=>preg_match('/^[a-z0-9_]{1,64}$/',$code)?$code:'',
   'transport'=>preg_match('/^[a-z0-9_]{1,32}$/',$transport)?$transport:''];
 @file_put_contents($storage.'/identity-login-diagnostics.log',json_encode($entry,JSON_UNESCAPED_SLASHES)."\n",FILE_APPEND|LOCK_EX);
}
function int_alert(string $msg,string $type):string{
 return '<div class="bn-alert bn-alert--'.($type==='success'?'success':'error').'"><strong>'.($type==='success'?'عملیات انجام شد':'عملیات کامل نشد').'</strong><p>'.Renderer::e($msg).'</p></div>';
}
$ip=ProxyContext::clientIp($_SERVER);
$ua=is_string($_SERVER['HTTP_USER_AGENT']??null)?substr($_SERVER['HTTP_USER_AGENT'],0,512):null;
$message='';$type='error';$rate=$storage.'/.update-login-rate.json';
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'&&isset($_POST['logout'])&&int_csrf_ok()){
 $_SESSION=[];session_regenerate_id(true);header('Location: /update.php',true,303);exit;
}
$authorized=(int)($_SESSION['integrations_updater_admin_until']??0)>time()&&($_SESSION['integrations_updater_super_admin']??false)===true;
if(!$authorized&&($_SERVER['REQUEST_METHOD']??'GET')==='POST'&&isset($_POST['identifier'])){
 if(!int_csrf_ok())$message='اعتبار فرم منقضی شده است.';
 else{
  [$allow,$wait]=int_rate_check($rate);
  if(!$allow)$message='ورود موقتاً قفل شده است. حدود '.ceil($wait/60).' دقیقه دیگر تلاش کنید.';
  else{
   try{
    $identity=new IdentityAdminClient();
    $login=$identity->login(trim((string)$_POST['identifier']),(string)($_POST['password']??''),$ip,$ua);
    $token=is_array($login['data']['data']['session']??null)?(string)($login['data']['data']['session']['session_credential']??''):'';
    if(!$login['ok']||$token===''){int_auth_log($storage,'login',$login);$message=IdentityAdminClient::explanation($login);}
    else{
     $me=$identity->me($token,$ip,$ua);
     $roles=is_array($me['data']['data']['roles']??null)?$me['data']['data']['roles']:[];
     $identity->logout($token,$ip,$ua);
     if(!$me['ok']){int_auth_log($storage,'me',$me);$message=IdentityAdminClient::explanation($me,'me');}
     elseif(!in_array('super_admin',$roles,true))$message='این حساب مجوز مدیر ارشد (super_admin) ندارد.';
     else{
      @unlink($rate);session_regenerate_id(true);
      $_SESSION['integrations_updater_admin_until']=time()+1200;
      $_SESSION['integrations_updater_super_admin']=true;
      header('Location: /update.php',true,303);exit;
     }
    }
   }catch(Throwable $error){error_log('[integrations] admin_login_failure '.get_class($error));$message='ارتباط با Identity برای تأیید مدیر برقرار نشد.';}
   int_rate_fail($rate);
  }
 }
}
$authorized=(int)($_SESSION['integrations_updater_admin_until']??0)>time()&&($_SESSION['integrations_updater_super_admin']??false)===true;
if(!$authorized){
 $content='<div class="bn-pagehead"><div class="bn-pagehead-main"><div class="bn-eyebrow">مرکز به‌روزرسانی امن</div><h1 class="bn-title">مدیریت نسخه سرویس یکپارچه‌سازی</h1><p class="bn-subtitle">برای مدیریت Integrations، مانند سایر سرویس‌های نوئل با حساب مدیر ارشد Identity وارد شوید.</p></div></div>'.($message!==''?int_alert($message,'error'):'').
 '<form method="post"><input type="hidden" name="_csrf" value="'.Renderer::e(int_csrf()).'"><div class="bn-field"><label class="bn-label" for="identifier">موبایل یا ایمیل مدیر</label><input id="identifier" name="identifier" autocomplete="username" required></div><div class="bn-field" style="margin-top:14px"><label class="bn-label" for="password">رمز عبور</label><input id="password" type="password" name="password" autocomplete="current-password" required></div><div class="bn-form-actions"><button class="bn-btn bn-btn--primary" type="submit">ورود به مرکز به‌روزرسانی</button></div></form>';
 echo Renderer::page('به‌روزرسانی سرویس یکپارچه‌سازی',$content);exit;
}
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'&&isset($_POST['recover'])&&int_csrf_ok()){
 try{ReleaseTransaction::recover($root,$storage,($_POST['database_restored']??'')==='1');header('Location: /update.php',true,303);exit;}
 catch(Throwable $error){$message='بازیابی کامل نشد: '.$error->getMessage();}
}
if(ReleaseTransaction::pending($storage)){
 $content='<div class="bn-pagehead"><div class="bn-pagehead-main"><h1 class="bn-title">بازیابی نسخه قبلی</h1></div></div>'.
   ($message!==''?int_alert($message,'error'):'').\Babanoell\Integrations\Platform\Ui::recoveryForm('_csrf',int_csrf(),'recover','1');
 echo Renderer::page('بازیابی به‌روزرسانی',$content);exit;
}
ReleaseTransaction::syncVersion($root);
$zipPath=$storage.'/update.zip';$tool=new UpdateTool();$plan=null;
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'&&!isset($_POST['identifier'])){
 if(!int_csrf_ok())$message='اعتبار فرم منقضی شده است؛ صفحه را تازه‌سازی کنید.';
 elseif(isset($_POST['upload_validate'])){
  $upload=$_FILES['package']??null;
  if(is_array($upload)&&($upload['error']??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_OK){
   if(!is_uploaded_file((string)$upload['tmp_name'])||!move_uploaded_file((string)$upload['tmp_name'],$zipPath))$message='ذخیره فایل آپلودشده انجام نشد.';
   else @chmod($zipPath,0600);
  } elseif(!is_file($zipPath))$message='فایل ZIP برای بررسی انتخاب نشده است.';
  if($message===''){
   try{$plan=$tool->plan($zipPath);$_SESSION['integrations_update_sha']=hash_file('sha256',$zipPath);$message='بسته معتبر است و برای اعمال آماده است.';$type='success';}
   catch(Throwable $error){unset($_SESSION['integrations_update_sha']);$message='بسته قابل اعمال نیست: '.$error->getMessage();}
  }
 }
 elseif(isset($_POST['apply_update'])){
  try{
   if(!is_file($zipPath))throw new RuntimeException('فایل ZIP پیدا نشد.');
   $sha=hash_file('sha256',$zipPath);
   if(!is_string($_SESSION['integrations_update_sha']??null)||!hash_equals($_SESSION['integrations_update_sha'],$sha))
    throw new RuntimeException('بسته پس از بررسی تغییر کرده است؛ دوباره بررسی بسته را اجرا کنید.');
   $pdo=Config::db();
   $result=ReleaseTransaction::apply($zipPath,$root,$storage,$sha,static fn():array=>Migrator::up($pdo,$root));
   unset($_SESSION['integrations_update_sha']);
   $message='به‌روزرسانی اعمال شد: '.$result['copied'].' فایل و '.count($result['migrations']).' Migration جدید.';$type='success';
  }catch(Throwable $error){@file_put_contents($storage.'/update-error.log','['.gmdate('c').'] '.get_class($error).': '.$error->getMessage()."\n",FILE_APPEND|LOCK_EX);$message='به‌روزرسانی کامل نشد: '.$error->getMessage();}
 }
}
if($plan===null&&is_file($zipPath)){
 try{$plan=$tool->plan($zipPath);}catch(Throwable){$plan=null;}
}
try{$pdo=Config::db();$db='متصل';$status=Migrator::status($pdo,$root);}catch(Throwable){$db='ناموفق';$status=['migrations'=>[]];}
$pending=count(array_filter($status['migrations'],static fn(array $row):bool=>$row['status']==='pending'));
$version=require $root.'/config/version.php';
$content='<div class="bn-pagehead"><div class="bn-pagehead-main"><div class="bn-eyebrow">Integrations '.Renderer::e($version['version']).'</div><h1 class="bn-title">به‌روزرسانی سرویس یکپارچه‌سازی</h1><p class="bn-subtitle">ورود امن، بررسی بسته پیش از نصب، نگهداری بکاپ و امکان بازیابی مطابق سایر سرویس‌های نوئل.</p></div><div class="bn-actions"><form method="post"><input type="hidden" name="_csrf" value="'.Renderer::e(int_csrf()).'"><button class="bn-btn bn-btn--secondary" name="logout" value="1">خروج</button></form></div></div>'.
 ($message!==''?int_alert($message,$type):'').
 '<div class="bn-stats"><div class="bn-stat"><div class="bn-stat-label">اتصال دیتابیس</div><div class="bn-stat-value">'.Renderer::e($db).'</div></div><div class="bn-stat"><div class="bn-stat-label">تغییرات دیتابیس در انتظار</div><div class="bn-stat-value">'.$pending.'</div></div><div class="bn-stat bn-stat--accent"><div class="bn-stat-label">بسته به‌روزرسانی</div><div class="bn-stat-value">'.($plan===null?'منتظر فایل':'آماده').'</div></div></div>'.
 '<section class="bn-section"><div class="bn-sectionhead"><span class="bn-step">۱</span><div><h2 class="bn-sectiontitle">بارگذاری و بررسی نسخه</h2><p class="bn-sectiondesc">فقط بسته ZIP سازگار با سرویس Integrations پذیرفته می‌شود.</p></div></div><form method="post" enctype="multipart/form-data"><input type="hidden" name="_csrf" value="'.Renderer::e(int_csrf()).'"><div class="bn-field"><label class="bn-label" for="package">فایل ZIP نسخه جدید</label><input id="package" name="package" type="file" accept=".zip,application/zip"></div><div class="bn-form-actions"><button class="bn-btn bn-btn--primary" name="upload_validate" value="1">بررسی بسته</button></div></form></section>';
if($plan!==null){
 $content.='<section class="bn-section"><div class="bn-sectionhead"><span class="bn-step">۲</span><div><h2 class="bn-sectiontitle">تأیید و اعمال نسخه '.Renderer::e($plan['version']).'</h2><p class="bn-sectiondesc">'.count($plan['files']).' فایل تأیید شده، '.$plan['protected'].' مسیر محافظت‌شده.</p></div></div><div class="bn-alert bn-alert--warning"><strong>قبل از اعمال:</strong> بکاپ دیتابیس اختصاصی Integrations را ذخیره کنید. اطلاعات محرمانه در ZIP تغییر داده نمی‌شوند.</div><form method="post"><input type="hidden" name="_csrf" value="'.Renderer::e(int_csrf()).'"><button class="bn-btn bn-btn--primary" name="apply_update" value="1">اعمال نسخه تأییدشده</button></form></section>';
}
$content.='<section class="bn-section bn-section--soft"><div class="bn-sectionhead"><span class="bn-step">۳</span><div><h2 class="bn-sectiontitle">وضعیت یکپارچه‌سازی</h2><p class="bn-sectiondesc">نمایش تنظیمات بدون افشای رمزها یا کلیدها</p></div></div><div class="bn-grid">';
foreach([
 'پیامک'=>Config::get('INTEGRATIONS_SMS_USERNAME')!==''&&Config::get('INTEGRATIONS_SMS_PASSWORD')!=='',
 'شاهکار'=>Config::get('INTEGRATIONS_SHAHKAR_CLIENT_ID')!==''&&Config::get('INTEGRATIONS_SHAHKAR_CLIENT_SECRET')!=='',
 'اعتبارسنجی'=>Config::get('INTEGRATIONS_ICS24_USERNAME')!==''&&Config::get('INTEGRATIONS_ICS24_PASSWORD')!=='',
 'گرین‌پی'=>Config::get('INTEGRATIONS_GREENPAY_USERNAME')!==''&&Config::get('INTEGRATIONS_GREENPAY_PASSWORD')!=='',
]as$name=>$configured){$content.='<div class="bn-field"><div class="bn-label">'.Renderer::e($name).'</div><strong>'.($configured?'تنظیمات اولیه ثبت شده':'نیازمند تنظیمات').'</strong></div>';}
$content.='</div><div class="bn-alert bn-alert--warning" style="margin-top:16px">ثبت تنظیمات به معنی تأیید اتصال زنده ارائه‌دهندگان نیست.</div></section>';
echo Renderer::page('به‌روزرسانی سرویس یکپارچه‌سازی',$content);
