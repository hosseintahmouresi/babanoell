<?php
declare(strict_types=1);
namespace Babanoell\Panel\Partners;
use Babanoell\Panel\Http\PartnersClient;
use Babanoell\Panel\Security\Csrf;
use Babanoell\Panel\Support\PersianDateTime;
use Babanoell\Panel\View\Renderer;

final class ReferralModule
{
 public function __construct(private readonly PartnersClient $client,private readonly string $bearer,private readonly array $identity){}
 public function handle(string $path,string $method):bool{
  if(!in_array($path,['/acceptor/invitation','/applicant/invitation'],true))return false;
  $owner=$path==='/acceptor/invitation';$roles=$this->identity['roles']??[];
  if(!in_array($owner?'acceptor':'applicant',$roles,true)){http_response_code(403);echo Renderer::page('دعوت پذیرنده','<p>دسترسی به این بخش برای حساب شما فعال نیست.</p>',$this->identity);return true;}
  if(!in_array($method,['GET','POST'],true)){http_response_code(405);return true;}
  if($owner){
   $response=$this->client->post('/api/v1/me/invitation',$this->bearer,[]);$invitation=$response->json['data']['invitation']??null;
   if(!$response->ok()||!is_array($invitation)){$this->error((string)($response->json['error']['message']??'دریافت دعوت‌نامه انجام نشد.'));return true;}
   $link='https://panel.noeel.ir'.$invitation['path'];
   $content='<section class="bn-section"><p>این لینک برای دعوت متقاضیان به پذیرندهٔ شماست. کد دعوت قابل استفادهٔ مجدد است.</p><label class="bn-field">لینک دعوت<input dir="ltr" readonly value="'.Renderer::e($link).'"></label><label class="bn-field">کد دعوت<input dir="ltr" readonly value="'.Renderer::e($invitation['code']).'"></label><p class="bn-helper">پس از پذیرش دعوت، انتخاب پذیرنده برای '.Renderer::e(PersianDateTime::digits((string)$invitation['lock_days'])).' روز به پذیرندهٔ دعوت‌کننده متصل می‌شود.</p></section>';
  }else{
   $code=(string)($_POST['code']??$_GET['code']??$_SESSION['pending_referral_code']??'');$error='';
   if($method==='POST'){
    if(!Csrf::valid((string)($_POST['_csrf']??''))){http_response_code(403);$error='اعتبار فرم منقضی شده است.';}
    else{
     $response=$this->client->post('/api/v1/invitations/apply',$this->bearer,['code'=>$code]);
     if($response->ok()){unset($_SESSION['pending_referral_code']);header('Location: /applicant/requests/new',true,303);return true;}
     $error=(string)($response->json['error']['message']??'دعوت فعال یا قابل استفاده نیست.');
    }
   }
   $content=($error!==''?'<div class="bn-alert bn-alert--error">'.Renderer::e($error).'</div>':'').'<section class="bn-section"><p>کد دعوت پذیرنده را ثبت کنید تا درخواست شخصی شما به همان پذیرنده متصل شود. دعوت قبلی تا پایان مهلت آن، طبق تنظیمات سامانه معتبر می‌ماند.</p><form method="post"><input type="hidden" name="_csrf" value="'.Renderer::e(Csrf::token()).'"><label class="bn-field">کد دعوت<input name="code" dir="ltr" maxlength="40" required value="'.Renderer::e($code).'"></label><button class="bn-btn bn-btn--primary">ثبت دعوت و ادامهٔ درخواست</button></form></section>';
  }
  echo Renderer::page($owner?'دعوت متقاضی':'دعوت پذیرنده','<div class="bn-pagehead"><h1 class="bn-title">'.($owner?'دعوت متقاضی':'دعوت پذیرنده').'</h1></div>'.$content,$this->identity,$owner?'acceptor':'applicant');return true;
 }
 private function error(string $message):void{http_response_code(409);echo Renderer::page('دعوت پذیرنده','<p class="bn-alert bn-alert--error">'.Renderer::e($message).'</p>',$this->identity);}
}
