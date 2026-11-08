<?php
declare(strict_types=1);
namespace Babanoell\Panel\Auth;
use Babanoell\Panel\Http\IdentityClient;
use Babanoell\Panel\Security\Csrf;
use Babanoell\Panel\View\Renderer;
final class AuthSettingsModule {
 public function __construct(private readonly IdentityClient $client,private readonly string $bearer,private readonly array $identity){}
 public function handle(string $path,string $method):bool {
  if($path!=='/system/auth-settings')return false;
  if(!in_array('super_admin',$this->identity['roles']??[],true)){http_response_code(403);echo Renderer::page('دسترسی محدود','<p>تنظیم ورود فقط برای مدیر ارشد مجاز است.</p>',$this->identity);return true;}
  $endpoint='/api/v1/admin/auth-settings';$message='';$success=false;
  if($method==='POST'){
   if(!Csrf::valid(is_string($_POST['_csrf']??null)?$_POST['_csrf']:null)){http_response_code(403);$message='اعتبار فرم تمام شده است.';}
   else{$pattern=is_string($_POST['sms_pattern_id']??null)?strtr(trim($_POST['sms_pattern_id']),array_combine(preg_split('//u','۰۱۲۳۴۵۶۷۸۹٠١٢٣٤٥٦٧٨٩',-1,PREG_SPLIT_NO_EMPTY),str_split('01234567890123456789'))):'';$revision=filter_var($_POST['revision']??null,FILTER_VALIDATE_INT);if(($pattern!==''&&(!ctype_digit($pattern)||(int)$pattern<1))||$revision===false){http_response_code(422);$message='شناسهٔ الگوی پیامک معتبر نیست.';}else{$r=$this->client->post($endpoint,$this->bearer,['sms_pattern_id'=>$pattern===''?null:(int)$pattern,'registration_enabled'=>isset($_POST['registration_enabled']),'otp_enabled'=>isset($_POST['otp_enabled']),'revision'=>$revision]);$success=$r->ok();$message=$success?'تنظیمات ورود ذخیره شد.':(string)($r->json['error']['message']??'ذخیره انجام نشد.');}}
  }elseif($method!=='GET'){http_response_code(405);header('Allow: GET, POST');return true;}
  $r=$this->client->get($endpoint,$this->bearer);$h='<h1 class="bn-title">ورود و ثبت‌نام</h1>';
  if($message!=='')$h.='<div class="bn-alert '.($success?'bn-alert--success':'bn-alert--error').'" role="status">'.Renderer::e($message).'</div>';
  if(!$r->ok()){http_response_code(503);$h.='<div class="bn-alert bn-alert--error">تنظیمات دریافت نشد. دوباره تلاش کنید.</div>';}
  else{$s=$r->json['data']['settings'];$h.='<section class="bn-section"><h2>پیامک تأیید شماره</h2><p>موبایل یا نام کاربری در مرحلهٔ اول دریافت می‌شود. حساب دارای رمز با رمز وارد می‌شود و حساب بدون رمز، کد پیامکی می‌گیرد. حساب تازه پس از تأیید شماره با نقش متقاضی ساخته می‌شود.</p><p>یک الگوی پیامکی تأییدشده با فقط یک متغیر (کد شش‌رقمی) ثبت کنید. مشخصات سرویس پیامکی در بخش مدیریت یکپارچه‌سازی‌ها تنظیم می‌شود. ذخیرهٔ این فرم پیامکی ارسال نمی‌کند.</p><form method="post"><input type="hidden" name="_csrf" value="'.Renderer::e(Csrf::token()).'"><input type="hidden" name="revision" value="'.(int)$s['revision'].'"><div class="bn-field"><label class="bn-label" for="sms_pattern_id">شناسهٔ الگوی پیامک</label><input id="sms_pattern_id" name="sms_pattern_id" inputmode="numeric" type="text" maxlength="10" dir="ltr" value="'.Renderer::e((string)($s['sms_pattern_id']??'')).'"></div><label class="bn-checkline"><input type="checkbox" name="registration_enabled"'.($s['registration_enabled']?' checked':'').'><span>ثبت‌نام متقاضی تازه</span></label><label class="bn-checkline"><input type="checkbox" name="otp_enabled"'.($s['otp_enabled']?' checked':'').'><span>تأیید شماره و ورود پیامکی برای حساب بدون رمز</span></label><div class="bn-form-actions"><button type="submit" class="bn-btn bn-btn--primary">ذخیره تنظیمات</button></div></form></section>';}
  echo Renderer::page('ورود و ثبت‌نام',$h,$this->identity,'settings');return true;
 }
}
