<?php
declare(strict_types=1);
namespace Babanoell\Panel\Auth;
use Babanoell\Panel\Http\{IdentityClient,HttpResponse};
use Babanoell\Panel\Security\Csrf;
use Babanoell\Panel\View\Renderer;

/** Progressive server-rendered flow: works with JavaScript disabled as well. */
final class SmartLoginModule {
 public function __construct(private readonly IdentityClient $client,private readonly PanelSession $session,private readonly ?string $ip,private readonly ?string $ua,private readonly string $version,private readonly string $ui){}
 public function handle(string $method):void {
  header('Cache-Control: private,no-store');
  if(!in_array($method,['GET','POST'],true)){http_response_code(405);header('Allow: GET, POST');return;}
  if($method==='GET'&&$this->session->identity($this->ip,$this->ua)!==null){header('Location: /dashboard',true,302);return;}
  $state=$_SESSION['panel_smart_login']??[];if(!is_array($state)||($state['until']??0)<time())$state=[];
  $error='';$notice='';
  if($method==='POST'){
   if(!Csrf::valid(self::post('_csrf'))){http_response_code(403);$error='اعتبار فرم تمام شده است. صفحه را تازه کنید.';}
   else{
    $action=self::post('action')?:'identify';
    if($action==='reset'){$state=[];}
    // Preserve the old trusted form contract for existing clients/password managers.
    elseif(($action==='password'&&($state['next']??'')==='password')||(self::post('password')!==''&&self::post('identifier')!==''&&$action==='identify')){
     $identifier=($state['next']??'')==='password'?(string)$state['identifier']:self::post('identifier');
     $r=$this->session->login($identifier,self::post('password'),$this->ip,$this->ua);if($r['ok']){$this->redirect();return;}$error=$r['message'];
     $state=['next'=>'password','identifier'=>$identifier,'until'=>time()+900];
    }
    else{
     $response=null;
     if($action==='identify')$response=$this->client->smart('discover',['identifier'=>self::post('identifier')],$this->ip,$this->ua);
     elseif($action==='register'&&($state['next']??'')==='register'){
      $state['display_name']=self::post('display_name');$state['mobile']=self::post('mobile');$state['username']=self::post('username');
      $response=$this->client->smart('register',['display_name'=>$state['display_name'],'mobile'=>$state['mobile'],'username'=>$state['username'],'password'=>self::post('password')],$this->ip,$this->ua);
     }
     elseif(in_array($action,['verify','resend'],true)&&($state['next']??'')==='otp')$response=$this->client->smart($action,['challenge_id'=>$state['challenge_id'],'code'=>self::post('code')],$this->ip,$this->ua);
     if($response instanceof HttpResponse){
      if($response->ok()&&$action==='verify'){$result=$this->session->completeLogin($response,$this->ip,$this->ua);if($result['ok']){$this->redirect();return;}$error=$result['message'];$state=[];}
      elseif($response->ok()){
       $d=$response->json['data']??[];$next=$d['next']??'';
       if(!in_array($next,['register','password','otp'],true)||($next==='otp'&&(!is_string($d['challenge_id']??null)||!preg_match('/^[a-f0-9]{48}$/D',$d['challenge_id'])))){$error='پاسخ ورود کامل نبود. دوباره تلاش کنید.';}
       else{$state=array_intersect_key($d,array_flip(['next','identifier','mobile','username','challenge_id','mobile_hint']));$state['until']=time()+900;if($next==='otp')$notice='کد تأیید به شمارهٔ شما پیامک شد.';}
      }
      else{$error=(string)($response->json['error']['message']??'ارتباط با سرویس ورود برقرار نشد. دوباره تلاش کنید.');if(($response->json['error']['retry_after']??0)>0)$error.=' '.\Babanoell\Panel\Support\PersianDateTime::digits((string)$response->json['error']['retry_after']).' ثانیهٔ دیگر تلاش کنید.';if($response->status===410)$state=[];}
     }else{$state=[];$error='از مرحلهٔ ورود شروع کنید.';}
    }
   }
  }
  $_SESSION['panel_smart_login']=$state;$next=$state['next']??'identify';
  $alert=$error!==''?'<div class="bn-alert bn-alert--error" role="alert">'.Renderer::e($error).'</div>':($notice!==''?'<div class="bn-alert bn-alert--success" role="status">'.Renderer::e($notice).'</div>':'');
  $token='<input type="hidden" name="_csrf" value="'.Renderer::e(Csrf::token()).'">';
  $start='<form method="post" action="/login" autocomplete="on" data-submit-lock="1">'.$token;
  $title='به بابانوئل خوش آمدید';$description='شمارهٔ موبایل یا نام کاربری خود را وارد کنید.';$form='';
  if($next==='password'){
   $title='رمز عبورتان را وارد کنید';$description='برای ورود به حساب «'.(string)$state['identifier'].'» از رمز عبور استفاده کنید.';
   $form=$start.'<input type="hidden" name="action" value="password"><input type="hidden" name="identifier" autocomplete="username" value="'.Renderer::e($state['identifier']).'">'.self::password(false).'<button type="submit" class="bn-btn bn-btn--primary">ورود به حساب</button></form>';
  }elseif($next==='register'){
   $title='حساب تازه بسازید';$description='اطلاعات اولیه را کامل کنید؛ سپس شمارهٔ موبایل را تأیید می‌کنیم.';
   $form=$start.'<input type="hidden" name="action" value="register">'.self::field('display_name','نام و نام خانوادگی',(string)($state['display_name']??''),'text','name',true,200).self::field('mobile','شمارهٔ موبایل',(string)($state['mobile']??''),'tel','tel-national',true,32).self::field('username','نام کاربری (اختیاری)',(string)($state['username']??''),'text','username',false,64).'<p class="bn-field-hint">نام کاربری انگلیسی و بدون فاصله؛ مانند ali.reza</p>'.self::password(true).'<p class="bn-field-hint">رمز اختیاری است؛ اگر خالی بماند، با کد پیامکی وارد می‌شوید.</p><button type="submit" class="bn-btn bn-btn--primary">تأیید اطلاعات و دریافت کد</button></form>';
  }elseif($next==='otp'){
   $title='کد پیامک را وارد کنید';$description='کد شش‌رقمی ارسال‌شده به '.(string)$state['mobile_hint'].' تا ۵ دقیقه معتبر است.';
   $form=$start.'<input type="hidden" name="action" value="verify"><div class="bn-field"><label class="bn-label" for="code">کد تأیید</label><input id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="[0-9۰-۹٠-٩]{6}" dir="ltr" required autofocus></div><button type="submit" class="bn-btn bn-btn--primary">تأیید و ورود</button></form><form method="post" action="/login" data-submit-lock="1">'.$token.'<input type="hidden" name="action" value="resend"><button class="bn-btn bn-btn--ghost" type="submit">ارسال دوبارهٔ کد</button><p class="bn-field-hint">ارسال دوباره پس از یک دقیقه امکان‌پذیر است.</p></form>';
  }else{$form=$start.'<input type="hidden" name="action" value="identify">'.self::field('identifier','موبایل یا نام کاربری','','text','username',true,255).'<button type="submit" class="bn-btn bn-btn--primary">ادامه</button></form>';}
  if($next!=='identify')$form.='<form method="post" action="/login">'.$token.'<input type="hidden" name="action" value="reset"><button type="submit" class="bn-btn bn-btn--ghost">تغییر موبایل یا نام کاربری</button></form>';
  $descriptionHtml=$next==='otp'?'کد شش‌رقمی ارسال‌شده به <bdi dir="ltr">'.Renderer::e((string)$state['mobile_hint']).'</bdi> تا ۵ دقیقه معتبر است.':Renderer::e($description);
  $h='<div class="bn-login-grid"><aside class="bn-login-aside"><div><div class="bn-eyebrow bn-eyebrow--inverse">همراه شما در مسیر تسهیلات</div><h2>از شروع درخواست،<br>تا قدم آخر</h2><p>درخواست‌ها، مدارک و قراردادهای خود را در یک فضای روشن و منظم پیگیری کنید.</p><div class="bn-login-points"><div class="bn-login-point"><i>۱</i><span>یک حساب برای پیگیری همهٔ کارها</span></div><div class="bn-login-point"><i>۲</i><span>حفظ امنیت اطلاعات شما</span></div><div class="bn-login-point"><i>۳</i><span>دسترسی سریع به اقدام بعدی</span></div></div></div><div class="bn-version">Panel '.Renderer::e($this->version).' · UI '.Renderer::e($this->ui).'</div></aside><div class="bn-login-form"><div class="bn-eyebrow">ورود و ثبت‌نام</div><h1>'.Renderer::e($title).'</h1><p class="bn-subtitle">'.$descriptionHtml.'</p>'.$alert.$form.'<div class="bn-panel-login-note"><span class="bn-panel-login-note-mark"><i class="bn-icon bn-icon--shield-halved" aria-hidden="true"></i></span><span>کد پیامک و رمز عبور را در اختیار دیگران نگذارید.</span></div></div></div>';
  echo Renderer::page('ورود به بابانوئل',$h);
 }
 private function redirect():void {
  unset($_SESSION['panel_smart_login']);$continue=$_SESSION['panel_sso_continue']??null;unset($_SESSION['panel_sso_continue']);$destination=is_string($continue)&&(str_starts_with($continue,'/sso/authorize?')||str_starts_with($continue,'/sso/user/authorize?'))&&strlen($continue)<500?$continue:(!empty($_SESSION['pending_referral_code'])?'/applicant/invitation':'/dashboard');header('Location: '.$destination,true,303);
 }
 private static function post(string $key):string{$v=$_POST[$key]??'';return is_string($v)?$v:'';}
 private static function field(string $key,string $label,string $value,string $type,string $autocomplete,bool $required,int $max):string{return '<div class="bn-field"><label class="bn-label" for="'.Renderer::e($key).'">'.Renderer::e($label).'</label><input id="'.Renderer::e($key).'" name="'.Renderer::e($key).'" type="'.$type.'" autocomplete="'.$autocomplete.'" value="'.Renderer::e($value).'" maxlength="'.$max.'"'.($required?' required':'').($type==='tel'?' inputmode="tel" dir="ltr"':'').'></div>';}
 private static function password(bool $optional):string{return '<div class="bn-field"><label class="bn-label" for="password">'.($optional?'رمز عبور (اختیاری)':'رمز عبور').'</label><div class="bn-password-wrap"><input id="password" name="password" type="password" autocomplete="'.($optional?'new-password':'current-password').'" maxlength="1024"'.($optional?' minlength="10"':' required').'><button class="bn-password-toggle" type="button" data-password-toggle aria-label="نمایش یا پنهان‌کردن رمز عبور">نمایش</button></div></div>';}
}
