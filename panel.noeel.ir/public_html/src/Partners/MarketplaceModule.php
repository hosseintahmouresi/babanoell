<?php
declare(strict_types=1);
namespace Babanoell\Panel\Partners;

use Babanoell\Panel\Http\PartnersClient;
use Babanoell\Panel\Security\Csrf;
use Babanoell\Panel\View\Renderer;

final class MarketplaceModule
{
 public function __construct(private readonly PartnersClient $client,private readonly string $bearer,private readonly array $identity){}

 public static function selection(string $name,string $label,array $terms,array $selected=[]):string
 {
  $options='';
  foreach($terms as$t)if(!empty($t['active']))$options.='<option value="'.Renderer::e($t['id']).'"'.(in_array($t['id'],$selected,true)?' selected':'').'>'.Renderer::e($t['label']).'</option>';
  return '<div class="bn-field"><label class="bn-label" for="'.Renderer::e($name).'">'.Renderer::e($label).'</label><select id="'.Renderer::e($name).'" name="'.Renderer::e($name).'[]" multiple size="6">'.$options.'</select><small class="bn-field-hint">امکان انتخاب چند مورد وجود دارد.</small></div>';
 }

 public function handle(string $path,string $method):bool
 {
  $settings=$path==='/partners/marketplace/settings';
  $profile=preg_match('#^/partners/merchants/([a-f0-9]{26})/marketplace$#',$path,$match)===1;
  if(!$settings&&!$profile)return false;
  $roles=(array)($this->identity['roles']??[]);$permissions=(array)($this->identity['permissions']??[]);
  $admin=(bool)array_intersect(['super_admin','admin'],$roles);
  if(($settings&&!$admin)||($profile&&!$admin&&!in_array('partners.merchants.manage',$permissions,true))){http_response_code(403);echo Renderer::page('دسترسی مجاز نیست','<p>اجازه مدیریت بازار را ندارید.</p>',$this->identity,'partners');return true;}
  $response=$this->client->get('/api/v1/marketplace/catalog',$this->bearer);$catalog=$response->json['data']['catalog']??null;
  if(!$response->ok()||!is_array($catalog)){http_response_code(503);echo Renderer::page('بازار پذیرندگان','<p>تنظیمات بازار در دسترس نیست؛ دوباره تلاش کنید.</p>',$this->identity,'partners');return true;}
  $error='';
  if($settings){
   if($method==='POST'){
    if(!Csrf::valid((string)($_POST['_csrf']??''))){http_response_code(403);$error='اعتبار فرم منقضی شده است.';}
    else{
     $payload=$catalog;$payload['expected_revision']=(int)($_POST['revision']??0);
     if(($_POST['action']??'')==='term'){
      $kind=($_POST['kind']??'')==='locations'?'locations':'categories';$id=trim((string)($_POST['term_id']??''));if($id==='')$id=bin2hex(random_bytes(13));
      $term=['id'=>$id,'label'=>trim((string)($_POST['label']??'')),'parent_id'=>$kind==='locations'?trim((string)($_POST['parent_id']??'')):'','active'=>isset($_POST['active'])];
      $found=false;foreach($payload[$kind]as&$row)if($row['id']===$id){$row=$term;$found=true;}unset($row);if(!$found)$payload[$kind][]=$term;
     }else foreach(['enabled','require_category','require_location','allow_all_country']as$key)$payload[$key]=isset($_POST[$key]);
     $r=$this->client->put('/api/v1/marketplace/configuration',$this->bearer,$payload);
     if($r->ok()){header('Location:'.$path,true,303);return true;}$error=(string)($r->json['error']['message']??'ذخیره تنظیمات انجام نشد.');
    }
   }
   $content=$this->settings($catalog);
  }else{
   $id=$match[1];$r=$this->client->get('/api/v1/merchants/'.$id,$this->bearer);$merchant=$r->json['data']['merchant']??null;
   if(!$r->ok()||!is_array($merchant)){http_response_code($r->status===403?403:404);echo Renderer::page('پذیرنده','<p>پذیرنده در دسترس نیست.</p>',$this->identity,'partners');return true;}
   if($method==='POST'){
    if(!Csrf::valid((string)($_POST['_csrf']??''))){http_response_code(403);$error='اعتبار فرم منقضی شده است.';}
    else{
     $amount=strtr((string)($_POST['max_facility_amount']??'0'),['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9']);$amount=str_replace([',','٬',' '],'',$amount);
     $payload=['row_version'=>(int)($_POST['row_version']??0),'enabled'=>isset($_POST['enabled']),'category_ids'=>(array)($_POST['category_ids']??[]),'location_ids'=>(array)($_POST['location_ids']??[]),'max_facility_amount'=>$amount];
     $saved=$this->client->put('/api/v1/merchants/'.$id.'/marketplace',$this->bearer,$payload);
     if($saved->ok()){header('Location:'.$path,true,303);return true;}$error=(string)($saved->json['error']['message']??'ذخیره تنظیمات پذیرنده انجام نشد.');
    }
   }
   $p=(array)($merchant['metadata']['marketplace']??[]);
   $content='<h1 class="bn-page-title">بازار پذیرنده: '.Renderer::e($merchant['name']).'</h1><p>فقط پرونده‌های مطابق حوزه فعالیت، محدوده خدمت، سقف مبلغ و مجوز طرح نمایش داده می‌شوند.</p><form method="post">'.$this->csrf().'<input type="hidden" name="row_version" value="'.(int)$merchant['row_version'].'"><section class="bn-section"><div class="bn-grid bn-grid--2">'.self::selection('category_ids','حوزه‌های فعالیت',$catalog['categories'],(array)($p['category_ids']??[])).self::selection('location_ids','محدوده خدمت',$catalog['locations'],(array)($p['location_ids']??[])).'<div class="bn-field"><label class="bn-label" for="market-limit">سقف تسهیلات هر پرونده (ریال)</label><input id="market-limit" name="max_facility_amount" inputmode="numeric" value="'.Renderer::e((string)($p['max_facility_amount']??0)).'"><small>صفر یعنی بدون سقف اختصاصی.</small></div>'.$this->checkbox('enabled','فعال در بازار',($p['enabled']??true)===true).'</div></section><button class="bn-btn bn-btn--primary">ذخیره</button> <a href="/partners/merchants/'.$id.'">بازگشت به پذیرنده</a></form>';
  }
  if($error!=='')$content='<div class="bn-alert bn-alert--error" role="alert">'.Renderer::e($error).'</div>'.$content;
  echo Renderer::page('تنظیمات بازار',$content,$this->identity,'partners');return true;
 }

 private function settings(array $c):string
 {
  $html='<h1 class="bn-page-title">تنظیمات بازار پذیرندگان</h1><p>حوزه‌های فعالیت و درخت موقعیت‌ها مرجع مشترک فرم متقاضی و پروفایل پذیرنده‌اند.</p><form method="post">'.$this->csrf().'<input type="hidden" name="revision" value="'.(int)$c['revision'].'"><section class="bn-section">';
  foreach(['enabled'=>'بازار فعال باشد','require_category'=>'حوزه فعالیت الزامی باشد','require_location'=>'موقعیت در محدوده انتخابی الزامی باشد','allow_all_country'=>'درخواست سراسر کشور مجاز باشد']as$key=>$label)$html.=$this->checkbox($key,$label,$c[$key]);
  $html.='<button class="bn-btn bn-btn--primary">ذخیره قواعد</button></section></form>';
  $kind=($_GET['kind']??'')==='locations'?'locations':'categories';$locations=$kind==='locations';$title=$locations?'موقعیت':'حوزه فعالیت';
  $html.='<nav class="bn-actions"><a href="?kind=categories">حوزه‌های فعالیت</a> · <a href="?kind=locations">موقعیت‌ها</a></nav><section class="bn-section"><h2>فهرست '.$title.'</h2><div class="bn-table-wrap"><table class="bn-table"><thead><tr><th>نام</th><th>وضعیت</th><th>ویرایش</th></tr></thead><tbody>';
  $page=max(1,(int)($_GET['page']??1));$edit=null;
  foreach($c[$kind]as$t)if($t['id']===($_GET['term']??''))$edit=$t;
  foreach(array_slice($c[$kind],($page-1)*100,100)as$t)$html.='<tr><td>'.Renderer::e($t['label']).'</td><td>'.($t['active']?'فعال':'غیرفعال').'</td><td><a href="?kind='.$kind.'&amp;term='.Renderer::e($t['id']).'">ویرایش</a></td></tr>';
  $html.='</tbody></table></div><div class="bn-actions">';
  if($page>1)$html.='<a href="?kind='.$kind.'&amp;page='.($page-1).'">صفحه قبل</a>';
  if(count($c[$kind])>$page*100)$html.='<a href="?kind='.$kind.'&amp;page='.($page+1).'">صفحه بعد</a>';
  $html.='</div><h2>'.($edit?'ویرایش':'افزودن').' '.$title.'</h2><form method="post">'.$this->csrf().'<input type="hidden" name="revision" value="'.(int)$c['revision'].'"><input type="hidden" name="action" value="term"><input type="hidden" name="kind" value="'.$kind.'"><input type="hidden" name="term_id" value="'.Renderer::e($edit['id']??'').'"><label class="bn-label" for="term-name">نام</label><input id="term-name" name="label" maxlength="120" required value="'.Renderer::e($edit['label']??'').'">';
  if($locations){$html.='<label class="bn-label" for="term-parent">موقعیت بالادست</label><select id="term-parent" name="parent_id"><option value="">بدون بالادست</option>';foreach($c['locations']as$t)if($t['id']!==($edit['id']??''))$html.='<option value="'.Renderer::e($t['id']).'"'.($t['id']===($edit['parent_id']??'')?' selected':'').'>'.Renderer::e($t['label']).'</option>';$html.='</select>';}
  return $html.$this->checkbox('active','فعال',($edit['active']??true)===true).'<div class="bn-form-actions"><button class="bn-btn bn-btn--primary">ذخیره</button><a href="?kind='.$kind.'">افزودن مورد جدید</a></div></form></section>';
 }
 private function csrf():string{return '<input type="hidden" name="_csrf" value="'.Renderer::e(Csrf::token()).'">';}
 private function checkbox(string $name,string $label,bool $checked):string{return '<label class="bn-checkbox"><input type="checkbox" name="'.$name.'" value="1"'.($checked?' checked':'').'>'.Renderer::e($label).'</label>';}
}
