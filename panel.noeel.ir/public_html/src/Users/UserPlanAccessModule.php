<?php
declare(strict_types=1);
namespace Babanoell\Panel\Users;
use Babanoell\Panel\Http\LoansClient;
use Babanoell\Panel\Http\PartnersClient;
use Babanoell\Panel\Security\Csrf;
use Babanoell\Panel\View\Renderer;

final class UserPlanAccessModule
{
 public function __construct(private readonly PartnersClient $partners,private readonly LoansClient $loans,private readonly string $bearer,private readonly array $identity){}
 public function handle(string $path,string $method):bool {
  if(!preg_match('#^/users/([a-f0-9]{26})/plans$#',$path,$m))return false;
  header('Cache-Control: private,no-store');
  if(!array_intersect(['super_admin','admin'],$this->identity['roles']??[])){http_response_code(403);echo Renderer::page('دسترسی محدود','<p>تنظیم طرح‌های حساب فقط برای مدیر سامانه مجاز است.</p>',$this->identity);return true;}
  $endpoint='/api/v1/admin/users/'.$m[1].'/plan-exclusions';$message='';$good=false;
  if($method==='POST'){
   if(!Csrf::valid((string)($_POST['_csrf']??''))){http_response_code(403);$message='اعتبار فرم منقضی شده است.';}
   elseif(!isset($_POST['selection_complete'])){http_response_code(422);$message='فهرست کامل طرح‌ها تأیید نشده است.';}
   else{$ids=is_array($_POST['disabled_plan_ids']??null)?array_values($_POST['disabled_plan_ids']):[];$r=$this->partners->post($endpoint,$this->bearer,['revision'=>(int)($_POST['revision']??0),'disabled_plan_ids'=>$ids]);$good=$r->ok();$message=$good?'طرح‌های غیرفعال حساب ذخیره شدند.':(string)($r->json['error']['message']??'ذخیره کامل نشد.');}
  }elseif($method!=='GET'){http_response_code(405);header('Allow: GET, POST');return true;}
  $r=$this->partners->get($endpoint,$this->bearer);$plans=[];$complete=true;
  for($page=1;$page<=10;$page++){
   $p=$this->loans->get('/api/v1/plans',$this->bearer,['status'=>'all','page'=>$page,'per_page'=>100]);
   if(!$p->ok()||!is_array($p->json['data']['items']??null)){$complete=false;break;}
   foreach($p->json['data']['items'] as $plan)$plans[$plan['id']]=$plan;
   if(count($plans)>=(int)$p->json['data']['total'])break;
   if($page===10)$complete=false;
  }
  $h='<div class="bn-pagehead"><h1 class="bn-title">طرح‌های غیرفعال پذیرنده</h1></div>'.($message!==''?'<div class="bn-alert '.($good?'bn-alert--success':'bn-alert--error').'" role="status">'.Renderer::e($message).'</div>':'');
  if(!$r->ok()||!$complete){http_response_code(503);$h.='<div class="bn-alert bn-alert--error">فهرست کامل حساب و طرح‌ها دریافت نشد؛ ذخیره انجام نمی‌شود. دوباره تلاش کنید.</div>';}
  else{
   $d=$r->json['data'];$access=$d['access'];$selected=$access['disabled_plan_ids'];$boxes='';
   foreach($plans as $id=>$plan)$boxes.='<label class="bn-checkline"><input type="checkbox" name="disabled_plan_ids[]" value="'.Renderer::e($id).'"'.(in_array($id,$selected,true)?' checked':'').'><span>'.Renderer::e($plan['name']).' · '.($plan['type']==='leasing'?'لیزینگ':'بازاری').'</span></label>';
   // Keep exclusions for already removed plans until explicitly changed through the API.
   foreach(array_diff($selected,array_keys($plans)) as $id)$boxes.='<input type="hidden" name="disabled_plan_ids[]" value="'.Renderer::e($id).'">';
   $h.='<section class="bn-section"><p>کاربر: <strong>'.Renderer::e($d['user']['display_name']).'</strong></p><p>طرح‌هایی را انتخاب کنید که باید برای همین حساب پذیرنده غیرفعال باشند. خالی بودن انتخاب یعنی همهٔ طرح‌های فعالِ مجاز در دسترس‌اند؛ طرح‌های تازه نیز خودکار مجاز می‌شوند. این محدودیت به پرونده‌های قبلی دست نمی‌زند و برای نقش‌های مدیر، عملیات و مالی اعمال نمی‌شود.</p><form method="post"><input type="hidden" name="_csrf" value="'.Renderer::e(Csrf::token()).'"><input type="hidden" name="revision" value="'.(int)$access['revision'].'"><input type="hidden" name="selection_complete" value="1"><div class="bn-required-doc-grid">'.$boxes.'</div><div class="bn-form-actions"><button class="bn-btn bn-btn--primary">ذخیره طرح‌های غیرفعال</button><a class="bn-btn bn-btn--secondary" href="/users">بازگشت به کاربران</a></div></form></section>';
  }
  echo Renderer::page('طرح‌های غیرفعال پذیرنده',$h,$this->identity,'users');return true;
 }
}
