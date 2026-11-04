<?php
declare(strict_types=1);
namespace Babanoell\Panel\Partners;

use Babanoell\Panel\Http\DocumentsClient;
use Babanoell\Panel\Http\HttpResponse;
use Babanoell\Panel\Http\LoansClient;
use Babanoell\Panel\Http\PartnersClient;
use Babanoell\Panel\Security\Csrf;
use Babanoell\Panel\Support\PersianDateTime;
use Babanoell\Panel\Support\FormOperation;
use Babanoell\Panel\View\Renderer;

final class PartnersModule
{
    public function __construct(
        private readonly PartnersClient $client,
        private readonly DocumentsClient $documents,
        private readonly LoansClient $loans,
        private readonly string $bearer,
        private readonly array $identity,
    ){}

    public function handle(string $path,string $method): bool
    {
        if(!$this->canAny()) return false;
        if($path==='/partners'){ $this->overview(); return true; }
        if($path==='/partners/import-loans-shadow'&&$method==='POST'){ $this->importLoansShadow(); return true; }
        if($path==='/partners/merchants'){ $this->merchants(); return true; }
        if($path==='/partners/merchants/new'){ $method==='POST'?$this->createMerchant():$this->merchantForm(); return true; }
        if(preg_match('#^/partners/merchants/([a-f0-9]{26})$#',$path,$m)){ $method==='POST'?$this->updateMerchant($m[1]):$this->merchantDetail($m[1]); return true; }
        if(preg_match('#^/partners/merchants/([a-f0-9]{26})/membership$#',$path,$m)&&$method==='POST'){ $this->saveMembership($m[1]); return true; }
        if(preg_match('#^/partners/merchants/([a-f0-9]{26})/plan-access$#',$path,$m)&&$method==='POST'){ $this->savePlanAccess($m[1]); return true; }
        if($path==='/partners/cooperation/form-settings'){if(!$this->isPlatformAdmin()){$this->forbidden();return true;}$method==='POST'?$this->saveCooperationForm():$this->cooperationSettings();return true;}
        if($path==='/partners/cooperation'){ $this->cooperation(); return true; }
        if($path==='/partners/cooperation/new'){ $method==='POST'?$this->createCooperation():$this->cooperationForm(); return true; }
        if(preg_match('#^/partners/cooperation/([a-f0-9]{26})/correct$#',$path,$m)){if(!$this->isPlatformAdmin()){$this->forbidden();return true;}$method==='POST'?$this->correctCooperation($m[1]):$this->cooperationCorrectionForm($m[1]);return true;}
        if($path==='/partners/cooperation/resolve-legacy'&&$method==='GET'){if(!$this->isPlatformAdmin()){$this->forbidden();return true;}$this->resolveCooperationLegacy();return true;}
        if(preg_match('#^/partners/cooperation/([a-f0-9]{26})$#',$path,$m)){ $this->cooperationDetail($m[1]); return true; }
        if(preg_match('#^/partners/cooperation/([a-f0-9]{26})/transition$#',$path,$m)&&$method==='POST'){ $this->transitionCooperation($m[1]); return true; }
        if(preg_match('#^/partners/cooperation/([a-f0-9]{26})/documents$#',$path,$m)&&$method==='POST'){ $this->uploadCooperationDocument($m[1]); return true; }
        if($path==='/partners/marketplace'){ $this->marketplace(); return true; }
        if(preg_match('#^/partners/marketplace/([a-f0-9]{26})/claim$#',$path,$m)&&$method==='POST'){ $this->claimMarketplace($m[1]); return true; }
        return false;
    }

    private function overview(): void
    {
        $r=$this->client->get('/api/v1/stats',$this->bearer);$s=$r->ok()&&is_array($r->json['data']??null)?$r->json['data']:[];
        $content=$this->head('شرکا و پذیرندگان','مدیریت پذیرندگان، همکاری، دسترسی طرح‌ها و بازار پرونده‌های متقاضی.').$this->takeFlash();
        if(!$r->ok())$content.=$this->alert('error','سرویس شرکا در دسترس نیست',$this->apiMessage($r));
        $content.='<div class="bn-stats">'.$this->stat('پذیرندگان',$s['merchants']??'—','کل رکوردها').$this->stat('پذیرنده فعال',$s['active_merchants']??'—','قابل استفاده در سامانه',true).$this->stat('همکاری باز',$s['cooperation_open']??'—','در انتظار اقدام').$this->stat('پذیرش بازار',$s['marketplace_claims']??'—','پرونده پذیرفته‌شده').'</div>';
        $cards='';
        if($this->can('partners.merchants.read'))$cards.=$this->card('/partners/merchants','پذیرندگان','پروفایل، اعضا و دسترسی پذیرنده به طرح‌ها.');
        if($this->can('partners.cooperation.read'))$cards.=$this->card('/partners/cooperation','درخواست‌های همکاری','درخواست حقیقی و حقوقی، مدارک و گردش وضعیت.');
        if($this->can('partners.marketplace.read'))$cards.=$this->card('/partners/marketplace','بازار پرونده‌ها','مشاهده و پذیرش پرونده‌های آزاد متقاضیان.');
        if($this->can('partners.cooperation.create'))$cards.=$this->card('/partners/cooperation/new','ثبت درخواست همکاری','ایجاد درخواست همکاری جدید.');
        $content.='<section class="bn-section"><div class="bn-action-grid">'.$cards.'</div></section>';if($this->isPlatformAdmin())$content.='<section class="bn-section"><h2 class="bn-sectiontitle">انتقال داده‌های قبلی</h2><p class="bn-muted">اگر قبلاً پذیرنده یا درخواست همکاری داخل Loans ثبت شده، این عملیات به‌صورت تکرارپذیر آن‌ها را به Partners منتقل می‌کند و داده مبدا را حذف نمی‌کند.</p><form method="post" action="/partners/import-loans-shadow"><input type="hidden" name="_csrf" value="'.Renderer::e(Csrf::token()).'"><button class="bn-btn bn-btn--secondary">همگام‌سازی داده‌های قبلی Loans</button></form></section>';
        echo Renderer::page('شرکا',$content,$this->identity,'partners');
    }

    private function merchants(): void
    {
        if(!$this->can('partners.merchants.read')){$this->forbidden();return;}$page=max(1,(int)($_GET['page']??1));$q=trim((string)($_GET['q']??''));$status=trim((string)($_GET['status']??''));
        $r=$this->client->get('/api/v1/merchants',$this->bearer,['page'=>$page,'per_page'=>20,'q'=>$q,'status'=>$status]);if(!$r->ok()){$this->errorPage('پذیرندگان',$r);return;}$data=(array)($r->json['data']??[]);$rows='';
        foreach((array)($data['items']??[])as$m){if(!is_array($m))continue;$rows.='<tr><td><a class="bn-table-link" href="/partners/merchants/'.Renderer::e((string)$m['id']).'"><strong>'.Renderer::e((string)($m['name']??'')).'</strong><small>'.Renderer::e((string)($m['code']??'')).'</small></a></td><td>'.$this->status((string)($m['status']??''),$this->merchantStatus((string)($m['status']??''))).'</td><td>'.Renderer::e($this->cooperationStatus((string)($m['cooperation_status']??''))).'</td><td>'.Renderer::e((string)($m['credit_level']??'—')).'</td><td>'.Renderer::e(PersianDateTime::fromIso((string)($m['updated_at']??''),false)??'—').'</td></tr>';}
        if($rows==='')$rows='<tr><td colspan="5"><div class="bn-empty-inline"><strong>پذیرنده‌ای پیدا نشد</strong><span>فیلتر یا عبارت جستجو را تغییر دهید.</span></div></td></tr>';
        $actions=$this->can('partners.merchants.manage')?'<a class="bn-btn bn-btn--primary" href="/partners/merchants/new">پذیرنده جدید</a>':'';
        $content=$this->head('پذیرندگان','پروفایل‌های مستقل Partners و وضعیت همکاری هر پذیرنده.',$actions).$this->takeFlash().'<section class="bn-section"><form method="get" class="bn-filterbar"><div class="bn-field bn-field--grow"><label class="bn-label">جستجو</label><input name="q" value="'.Renderer::e($q).'" placeholder="نام یا کد پذیرنده"></div><div class="bn-field"><label class="bn-label">وضعیت</label><select name="status"><option value="">همه</option>'.$this->option('active','فعال',$status).$this->option('inactive','غیرفعال',$status).$this->option('suspended','تعلیق',$status).$this->option('archived','بایگانی',$status).'</select></div><div class="bn-filter-actions"><button class="bn-btn bn-btn--primary">اعمال</button></div></form><div class="bn-table-wrap"><table class="bn-table"><thead><tr><th>پذیرنده</th><th>وضعیت</th><th>همکاری</th><th>سطح اعتبار</th><th>آخرین تغییر</th></tr></thead><tbody>'.$rows.'</tbody></table></div>'.$this->pagination('/partners/merchants',(int)($data['page']??$page),(int)($data['per_page']??20),(int)($data['total']??0),['q'=>$q,'status'=>$status]).'</section>';
        echo Renderer::page('پذیرندگان',$content,$this->identity,'partners');
    }

    private function merchantForm(string $error=''): void
    {
        if(!$this->can('partners.merchants.manage')){$this->forbidden();return;}$v=static fn(string$k,string$d='')=>(string)($_POST[$k]??$d);
        $content=$this->head('پذیرنده جدید','پذیرنده پس از ثبت در Partners به شکل سازگار با Loans همگام می‌شود.').($error!==''?$this->alert('error','ثبت انجام نشد',$error):'').'<form method="post"><input type="hidden" name="_csrf" value="'.Renderer::e(Csrf::token()).'"><section class="bn-section"><div class="bn-grid bn-grid--2">'.$this->field('کد پذیرنده','code',$v('code'),'مثال: MERCHANT01').$this->field('نام پذیرنده','name',$v('name')).$this->field('نام حقوقی','legal_name',$v('legal_name')).$this->field('شناسه مالک کاربر','owner_user_id',$v('owner_user_id'),'شناسه ۲۶ کاراکتری Identity').$this->field('سطح اعتبار','credit_level',$v('credit_level')).'<div class="bn-field"><label class="bn-label">وضعیت</label><select name="status">'.$this->option('active','فعال',$v('status','active')).$this->option('inactive','غیرفعال',$v('status','active')).$this->option('suspended','تعلیق',$v('status','active')).'</select></div></div></section><div class="bn-form-actions"><button class="bn-btn bn-btn--primary">ثبت پذیرنده</button><a class="bn-btn bn-btn--secondary" href="/partners/merchants">انصراف</a></div></form>';
        echo Renderer::page('پذیرنده جدید',$content,$this->identity,'partners');
    }
    private function createMerchant(): void
    {
        if(!$this->csrf('/partners/merchants/new'))return;$payload=['code'=>trim((string)($_POST['code']??'')),'name'=>trim((string)($_POST['name']??'')),'legal_name'=>trim((string)($_POST['legal_name']??'')),'owner_user_id'=>trim((string)($_POST['owner_user_id']??'')),'credit_level'=>trim((string)($_POST['credit_level']??'')),'status'=>trim((string)($_POST['status']??'active')),'cooperation_status'=>'approved'];$r=$this->client->post('/api/v1/merchants',$this->bearer,$payload);if(!$r->ok()){$this->merchantForm($this->apiMessage($r));return;}$id=(string)($r->json['data']['merchant']['merchant']['id']??$r->json['data']['merchant']['id']??'');$this->flash('success','پذیرنده ثبت شد.');$this->redirect($id!==''?'/partners/merchants/'.$id:'/partners/merchants');
    }

    private function merchantDetail(string$id): void
    {
        if(!$this->can('partners.merchants.read')){$this->forbidden();return;}$r=$this->client->get('/api/v1/merchants/'.$id,$this->bearer);if(!$r->ok()){$this->errorPage('پذیرنده',$r);return;}$d=(array)($r->json['data']??[]);$m=(array)($d['merchant']??[]);$members=(array)($d['memberships']??[]);$access=(array)($d['plan_permissions']??[]);
        $content=$this->head((string)($m['name']??'پذیرنده'),'کد: '.(string)($m['code']??'—')).$this->takeFlash().'<div class="bn-detail-grid">'.$this->detail('وضعیت',$this->merchantStatus((string)($m['status']??''))).$this->detail('وضعیت همکاری',$this->cooperationStatus((string)($m['cooperation_status']??''))).$this->detail('سطح اعتبار',(string)($m['credit_level']??'—')).$this->detail('نسخه رکورد',(string)($m['row_version']??0)).'</div>';
        if($this->can('partners.merchants.manage')){$content.='<section class="bn-section"><h2 class="bn-sectiontitle">ویرایش پایه</h2><form method="post"><input type="hidden" name="_csrf" value="'.Renderer::e(Csrf::token()).'"><input type="hidden" name="row_version" value="'.Renderer::e((string)($m['row_version']??0)).'"><div class="bn-grid bn-grid--2">'.$this->field('نام','name',(string)($m['name']??'')).$this->field('نام حقوقی','legal_name',(string)($m['legal_name']??'')).$this->field('سطح اعتبار','credit_level',(string)($m['credit_level']??'')).'<div class="bn-field"><label class="bn-label">وضعیت</label><select name="status">'.$this->option('active','فعال',(string)($m['status']??'')).$this->option('inactive','غیرفعال',(string)($m['status']??'')).$this->option('suspended','تعلیق',(string)($m['status']??'')).$this->option('archived','بایگانی',(string)($m['status']??'')).'</select></div></div><div class="bn-form-actions"><button class="bn-btn bn-btn--primary">ذخیره</button></div></form></section>';}
        $mr='';foreach($members as$x){if(!is_array($x))continue;$mr.='<tr><td>'.Renderer::e((string)($x['user_id']??'')).'</td><td>'.Renderer::e((string)($x['membership_role']??'')).'</td><td>'.Renderer::e((string)($x['status']??'')).'</td></tr>';}if($mr==='')$mr='<tr><td colspan="3">عضوی ثبت نشده است.</td></tr>';
        $content.='<section class="bn-section"><h2 class="bn-sectiontitle">کاربران پذیرنده</h2><div class="bn-table-wrap"><table class="bn-table"><thead><tr><th>شناسه کاربر</th><th>نقش عضویت</th><th>وضعیت</th></tr></thead><tbody>'.$mr.'</tbody></table></div>';
        if($this->can('partners.memberships.manage'))$content.='<form method="post" action="/partners/merchants/'.$id.'/membership" class="bn-form-actions"><input type="hidden" name="_csrf" value="'.Renderer::e(Csrf::token()).'"><input name="user_id" placeholder="شناسه کاربر Identity" required><select name="membership_role"><option value="operator">کاربر</option><option value="manager">مدیر</option><option value="finance">مالی</option><option value="viewer">مشاهده‌گر</option><option value="owner">مالک</option></select><button class="bn-btn bn-btn--primary">افزودن/بروزرسانی</button></form>';
        $content.='</section>';
        $ar='';foreach($access as$x){if(!is_array($x))continue;$ar.='<tr><td>'.Renderer::e((string)($x['plan_id']??'')).'</td><td>'.Renderer::e((string)($x['status']??'')).'</td></tr>';}if($ar==='')$ar='<tr><td colspan="2">دسترسی اختصاصی ثبت نشده است.</td></tr>';
        $content.='<section class="bn-section"><h2 class="bn-sectiontitle">دسترسی طرح‌ها</h2><div class="bn-table-wrap"><table class="bn-table"><thead><tr><th>شناسه طرح</th><th>وضعیت</th></tr></thead><tbody>'.$ar.'</tbody></table></div>';
        if($this->can('partners.plan_access.manage')){$plans=$this->loans->get('/api/v1/plans',$this->bearer,['status'=>'active','per_page'=>100]);$opts='';foreach((array)($plans->json['data']['items']??[])as$p){if(is_array($p))$opts.='<option value="'.Renderer::e((string)$p['id']).'">'.Renderer::e((string)$p['name']).'</option>';}$content.='<form method="post" action="/partners/merchants/'.$id.'/plan-access" class="bn-form-actions"><input type="hidden" name="_csrf" value="'.Renderer::e(Csrf::token()).'"><select name="plan_id" required><option value="">انتخاب طرح</option>'.$opts.'</select><select name="status"><option value="active">فعال</option><option value="inactive">غیرفعال</option></select><button class="bn-btn bn-btn--primary">ثبت دسترسی</button></form>';}
        $content.='</section>';
        echo Renderer::page('پذیرنده',$content,$this->identity,'partners');
    }
    private function updateMerchant(string$id): void
    {
        if(!$this->csrf('/partners/merchants/'.$id))return;if(!$this->can('partners.merchants.manage')){$this->forbidden();return;}$payload=['name'=>trim((string)($_POST['name']??'')),'legal_name'=>trim((string)($_POST['legal_name']??'')),'credit_level'=>trim((string)($_POST['credit_level']??'')),'status'=>trim((string)($_POST['status']??'')),'row_version'=>(int)($_POST['row_version']??0)];$r=$this->client->put('/api/v1/merchants/'.$id,$this->bearer,$payload);$this->flash($r->ok()?'success':'error',$r->ok()?'پذیرنده بروزرسانی شد.':$this->apiMessage($r));$this->redirect('/partners/merchants/'.$id);
    }
    private function saveMembership(string$id): void
    {
        if(!$this->csrf('/partners/merchants/'.$id))return;$r=$this->client->post('/api/v1/merchants/'.$id.'/memberships',$this->bearer,['user_id'=>trim((string)($_POST['user_id']??'')),'membership_role'=>(string)($_POST['membership_role']??'operator'),'status'=>'active','is_primary'=>(string)($_POST['membership_role']??'')==='owner']);$this->flash($r->ok()?'success':'error',$r->ok()?'عضویت ذخیره شد.':$this->apiMessage($r));$this->redirect('/partners/merchants/'.$id);
    }
    private function savePlanAccess(string$id): void
    {
        if(!$this->csrf('/partners/merchants/'.$id))return;$plan=trim((string)($_POST['plan_id']??''));$r=$this->client->post('/api/v1/merchants/'.$id.'/plan-permissions/'.$plan,$this->bearer,['status'=>(string)($_POST['status']??'active'),'limits'=>[]]);$this->flash($r->ok()?'success':'error',$r->ok()?'دسترسی طرح ذخیره شد.':$this->apiMessage($r));$this->redirect('/partners/merchants/'.$id);
    }

    private function cooperation(): void
    {
        if(!$this->can('partners.cooperation.read')){$this->forbidden();return;}$page=max(1,(int)($_GET['page']??1));$status=trim((string)($_GET['status']??''));$r=$this->client->get('/api/v1/cooperation/requests',$this->bearer,['page'=>$page,'per_page'=>20,'status'=>$status]);if(!$r->ok()){$this->errorPage('درخواست‌های همکاری',$r);return;}$data=(array)($r->json['data']??[]);$rows='';foreach((array)($data['items']??[])as$x){if(!is_array($x))continue;$name=(string)(($x['data']['full_name']??$x['data']['company_name']??'—'));$rows.='<tr><td><a class="bn-table-link" href="/partners/cooperation/'.Renderer::e((string)$x['id']).'"><strong>'.Renderer::e((string)$x['request_no']).'</strong><small>'.Renderer::e($name).'</small></a></td><td>'.Renderer::e($x['request_type']==='legal'?'حقوقی':'حقیقی').'</td><td>'.$this->status((string)$x['status'],$this->cooperationStatus((string)$x['status'])).'</td><td>'.Renderer::e(PersianDateTime::fromIso((string)($x['updated_at']??''),false)??'—').'</td></tr>';}$rows=$rows?:'<tr><td colspan="4">درخواستی پیدا نشد.</td></tr>';
        $actions=$this->can('partners.cooperation.create')?'<a class="bn-btn bn-btn--primary" href="/partners/cooperation/new">درخواست جدید</a>':'';if($this->isPlatformAdmin())$actions.='<a class="bn-btn bn-btn--secondary" href="/partners/cooperation/form-settings">تنظیمات فرم همکاری</a>';$content=$this->head('درخواست‌های همکاری','همکاری حقیقی و حقوقی در سرویس مستقل Partners.',$actions).$this->takeFlash().'<section class="bn-section"><div class="bn-table-wrap"><table class="bn-table"><thead><tr><th>درخواست</th><th>نوع</th><th>وضعیت</th><th>آخرین تغییر</th></tr></thead><tbody>'.$rows.'</tbody></table></div>'.$this->pagination('/partners/cooperation',(int)($data['page']??1),(int)($data['per_page']??20),(int)($data['total']??0),['status'=>$status]).'</section>';echo Renderer::page('همکاری',$content,$this->identity,'partners');
    }
    private function cooperationSettings(string$error=''):void
    {
        $r=$this->client->get('/api/v1/cooperation/form',$this->bearer);$d=$r->json['data']['form']??null;
        if(!$r->ok()){$this->errorPage('تنظیمات فرم همکاری',$r);return;}
        if(!is_array($d)||!CooperationFormView::valid($d)){http_response_code(503);echo Renderer::page('فرم همکاری',$this->alert('error','فرم قابل دریافت نیست','تعریف معتبر فرم دریافت نشد.'),$this->identity,'partners');return;}
        $expected=$d['revision'];
        if($error!==''&&is_array($_POST['options']??null)){foreach(['activity_field_options','how_heard_options']as$k)if(is_string($_POST['options'][$k]??null))$d['options'][$k]=preg_split('/\R/u',$_POST['options'][$k])?:[];$expected=is_scalar($_POST['expected_revision']??null)?$_POST['expected_revision']:$expected;foreach((array)($_POST['modes']??[])as$k=>$mode)if(isset($d['fields'][$k])&&in_array($mode,['hidden','optional','required'],true))$d['fields'][$k]=['visible'=>$mode!=='hidden','required'=>$mode==='required'];}
        $content=$this->head('تنظیمات فرم همکاری','گزینه‌ها و فیلدهای درخواست‌های جدید را مدیریت کنید. نسخه فرم پرونده‌های قبلی حفظ می‌شود.','<a class="bn-btn bn-btn--secondary" href="/partners/cooperation">درخواست‌های همکاری</a><a class="bn-btn bn-btn--secondary" href="/partners/cooperation/form-settings">بازخوانی تنظیمات</a>').$this->takeFlash();
        if($error!=='')$content.=$this->alert('error','تنظیمات ذخیره نشد',$error.' در تداخل هم‌زمان، ابتدا مقادیر دلخواه را نگه دارید و «بازخوانی تنظیمات» را بزنید.');
        $content.='<p class="bn-helper">نسخه تنظیمات: '.Renderer::e((string)$d['revision']).'؛ نام مالک یا شرکت همواره الزامی است. مدارک در گام بعدی درخواست بارگذاری می‌شوند.</p><form method="post"><input type="hidden" name="_csrf" value="'.Renderer::e(Csrf::token()).'"><input type="hidden" name="expected_revision" value="'.Renderer::e((string)$expected).'">'.CooperationFormView::settings($d).'<div class="bn-form-actions"><button class="bn-btn bn-btn--primary">ذخیره تنظیمات فرم</button></div></form>';
        echo Renderer::page('تنظیمات فرم همکاری',$content,$this->identity,'partners');
    }
    private function saveCooperationForm():void
    {
        if(!$this->csrf('/partners/cooperation/form-settings'))return;$fields=[];
        if(!is_array($_POST['modes']??null)||!is_array($_POST['options']??null)){$this->cooperationSettings('ساختار تنظیمات معتبر نیست.');return;}
        foreach($_POST['modes']as$key=>$mode){if(!in_array($mode,['hidden','optional','required'],true)){$this->cooperationSettings('وضعیت یکی از فیلدها معتبر نیست.');return;}$fields[$key]=['visible'=>$mode!=='hidden','required'=>$mode==='required'];}
        $r=$this->client->post('/api/v1/cooperation/form',$this->bearer,['expected_revision'=>$_POST['expected_revision']??null,'fields'=>$fields,'options'=>$_POST['options']]);
        if(!$r->ok()){$this->cooperationSettings($this->apiMessage($r));return;}$this->flash('success','تنظیمات فرم برای درخواست‌های جدید ذخیره شد.');$this->redirect('/partners/cooperation/form-settings');
    }
    private function cooperationForm(string$error='',array$fieldErrors=[]):void
    {
        if(!$this->can('partners.cooperation.create')){$this->forbidden();return;}
        $type=$_POST['request_type']??$_GET['request_type']??'individual';if(!in_array($type,['individual','legal'],true))$type='individual';
        $r=$this->client->get('/api/v1/cooperation/form',$this->bearer);$d=$r->json['data']['form']??null;
        if(!$r->ok()){$this->errorPage('فرم همکاری',$r);return;}
        if(!is_array($d)||!CooperationFormView::valid($d)){http_response_code(503);echo Renderer::page('فرم همکاری',$this->alert('error','فرم قابل دریافت نیست','تعریف معتبر فرم دریافت نشد.'),$this->identity,'partners');return;}
        $scope='cooperation-create:'.(string)($this->identity['user']['id']??'').':'.$type;$key=FormOperation::reuseOrIssue($scope,$_POST['_operation_key']??null);
        $values=CooperationFormView::values($type,$_POST);
        $content=$this->head('درخواست همکاری '.($type==='legal'?'حقوقی':'حقیقی'),'اطلاعات را ثبت کنید و مدارک را در گام بعد تکمیل کنید.','<a class="bn-btn bn-btn--secondary" href="/partners/cooperation/new?request_type=individual">شخص حقیقی</a><a class="bn-btn bn-btn--secondary" href="/partners/cooperation/new?request_type=legal">شخص حقوقی</a>');
        if($error!=='')$content.=$this->alert('error','ثبت انجام نشد',$error);
        $content.='<form method="post"><input type="hidden" name="_csrf" value="'.Renderer::e(Csrf::token()).'"><input type="hidden" name="request_type" value="'.$type.'"><input type="hidden" name="form_revision" value="'.$d['revision'].'"><input type="hidden" name="_operation_key" value="'.Renderer::e($key).'"><section class="bn-section"><div class="bn-grid bn-grid--2">'.CooperationFormView::fields($d,$type,$values,$fieldErrors).'</div></section>'.CooperationFormView::documentSummary($d,$type).'<div class="bn-form-actions"><button class="bn-btn bn-btn--primary">ثبت اطلاعات و ادامه</button><a class="bn-btn bn-btn--secondary" href="/partners/cooperation">انصراف</a></div></form>';
        echo Renderer::page('درخواست همکاری',$content,$this->identity,'partners');
    }
    private function createCooperation():void
    {
        if(!$this->can('partners.cooperation.create')){$this->forbidden();return;}if(!$this->csrf('/partners/cooperation/new'))return;
        $type=$_POST['request_type']??'individual';if(!in_array($type,['individual','legal'],true)){$this->cooperationForm('نوع درخواست معتبر نیست.');return;}
        $scope='cooperation-create:'.(string)($this->identity['user']['id']??'').':'.$type;$key=$_POST['_operation_key']??null;
        if(!FormOperation::valid($scope,$key)){$this->cooperationForm('اعتبار فرم منقضی شده است؛ اطلاعات را بررسی و دوباره ارسال کنید.');return;}
        $data=$_POST;unset($data['_csrf'],$data['_operation_key'],$data['request_type'],$data['form_revision']);$payload=['request_type'=>$type,'form_revision'=>$_POST['form_revision']??null,'data'=>$data];
        if(!FormOperation::bind($scope,$key,$payload)){$this->cooperationForm('نتیجه ارسال قبلی نامشخص است؛ همان اطلاعات را دوباره بفرستید یا درخواست‌های خود را بررسی کنید.');return;}
        $r=$this->client->post('/api/v1/cooperation/requests',$this->bearer,$payload,$key);
        if(!$r->ok()){
            if(($r->status===422&&($r->json['error']['code']??null)==='validation_failed')||($r->json['error']['code']??null)==='form_revision_conflict')FormOperation::complete($scope,$key);
            $errors=$r->json['error']['details']['fields']??[];$this->cooperationForm($this->apiMessage($r),is_array($errors)?$errors:[]);return;
        }
        $id=$r->json['data']['request']['id']??null;if(!is_string($id)||!preg_match('/^[a-f0-9]{26}$/D',$id)){$this->cooperationForm('پاسخ ثبت قابل تأیید نیست؛ ارسال مجدد با همان شناسه انجام می‌شود.');return;}
        FormOperation::complete($scope,$key);$this->flash('success','اطلاعات ثبت شد؛ مدارک الزامی را در این صفحه تکمیل کنید.');$this->redirect('/partners/cooperation/'.$id);
    }
    private function cooperationDetail(string$id): void
    {
        $r=$this->client->get('/api/v1/cooperation/requests/'.$id,$this->bearer);if(!$r->ok()){$this->errorPage('درخواست همکاری',$r);return;}$x=(array)($r->json['data']['request']??[]);$d=(array)($x['data']??[]);$docs=$this->documents->get('/api/v1/entities/cooperation_request/'.$id.'/documents',$this->bearer);$docRows='';if($docs->ok()){foreach((array)($docs->json['data']['items']??[])as$doc){if(!is_array($doc))continue;$docRows.='<tr><td>'.Renderer::e((string)($doc['label']??'مدرک')).'</td><td>'.Renderer::e((string)($doc['review_status']??'pending')).'</td><td><a href="/documents/files/'.Renderer::e((string)$doc['id']).'">مشاهده</a></td></tr>';}}$docRows=$docRows?:'<tr><td colspan="3">مدرکی ثبت نشده است.</td></tr>';
        $content=$this->head('درخواست '.(string)($x['request_no']??''),'وضعیت: '.$this->cooperationStatus((string)($x['status']??'')),'<a class="bn-btn bn-btn--secondary" href="/contracts/cooperation/'.$id.'">قرارداد همکاری</a>'.($this->isPlatformAdmin()?'<a class="bn-btn bn-btn--secondary" href="/partners/cooperation/'.$id.'/correct">اصلاح مدیریتی</a>':'')).$this->takeFlash().'<div class="bn-detail-grid">'.$this->detail('نوع',$x['request_type']==='legal'?'حقوقی':'حقیقی').$this->detail('نام',(string)($d['full_name']??$d['company_name']??'—')).$this->detail('موبایل',(string)($d['mobile']??'—')).$this->detail('پذیرنده ایجادشده',(string)($x['merchant_id']??'—')).'</div>';
        if(is_array($x['form_catalog']??null))$content.=CooperationFormView::record($x['form_catalog'],(string)$x['request_type'],CooperationFormView::values((string)$x['request_type'],$d));
        $policy=is_array($x['document_policy']??null)?$x['document_policy']:[['code'=>'cooperation_identity','label'=>'مدارک هویتی','visible'=>true,'required'=>false],['code'=>'cooperation_license','label'=>'مجوز فعالیت','visible'=>true,'required'=>false],['code'=>'cooperation_contract','label'=>'قرارداد همکاری','visible'=>true,'required'=>false]];
        $options='';$requiredCount=0;foreach($policy as$f){if(!is_array($f)||($f['visible']??null)!==true||!is_string($f['code']??null)||!preg_match('/^cooperation_[a-z0-9_]+$/D',$f['code']))continue;$required=($f['required']??false)===true;if($required)$requiredCount++;$options.='<option value="'.Renderer::e($f['code']).'">'.Renderer::e((string)($f['label']??'مدرک')).($required?' — الزامی':' — اختیاری').'</option>';}
        $content.='<section class="bn-section"><h2 class="bn-sectiontitle">مدارک همکاری</h2><p class="bn-helper">مدارک مطابق نسخه فرم زمان ثبت درخواست کنترل می‌شوند. تعداد انواع الزامی: '.Renderer::e((string)$requiredCount).'</p>';
        if($requiredCount>0){$completion=$this->documents->get('/api/v1/entities/cooperation_request/'.$id.'/completion',$this->bearer);$c=$completion->json['data']??null;if($completion->ok()&&is_array($c)&&is_bool($c['complete']??null))$content.=$this->alert($c['complete']?'success':'warning',$c['complete']?'مدارک الزامی تأیید شده‌اند':'مدارک الزامی نیازمند تکمیل یا بررسی‌اند','تأیید شده: '.(int)($c['approved_count']??0).' از '.(int)($c['required_count']??0));else$content.=$this->alert('warning','وضعیت مدارک قابل دریافت نیست','پس از برقراری ارتباط، وضعیت را دوباره بررسی کنید.');}
        $content.='<div class="bn-table-wrap"><table class="bn-table"><thead><tr><th>مدرک</th><th>بازبینی</th><th></th></tr></thead><tbody>'.$docRows.'</tbody></table></div>';
        if($this->can('documents.upload')&&$options!=='')$content.='<form method="post" enctype="multipart/form-data" action="/partners/cooperation/'.$id.'/documents" class="bn-form-actions"><input type="hidden" name="_csrf" value="'.Renderer::e(Csrf::token()).'"><label class="bn-label" for="cooperation-document-type">نوع مدرک</label><select id="cooperation-document-type" name="document_type">'.$options.'</select><label class="bn-label" for="cooperation-document-file">فایل مدرک</label><input id="cooperation-document-file" type="file" name="file" accept=".jpg,.jpeg,.png,.webp,.pdf" required><button class="bn-btn bn-btn--primary">بارگذاری مدرک</button></form>';
        $content.='</section>';
        if($this->can('partners.cooperation.manage')){$content.='<section class="bn-section"><h2 class="bn-sectiontitle">تغییر وضعیت</h2><form method="post" action="/partners/cooperation/'.$id.'/transition"><input type="hidden" name="_csrf" value="'.Renderer::e(Csrf::token()).'"><div class="bn-grid bn-grid--2"><div class="bn-field"><label class="bn-label">وضعیت جدید</label><select name="status">'.$this->option('under_review','در حال بررسی','').$this->option('need_correction','نقص مدارک','').$this->option('approved','تأیید','').$this->option('rejected','رد','').$this->option('cancelled','لغو','').$this->option('archived','بایگانی','').'</select></div>'.$this->field('توضیح','note','').'</div><div class="bn-form-actions"><button class="bn-btn bn-btn--primary">ثبت وضعیت</button></div></form></section>';}
        echo Renderer::page('درخواست همکاری',$content,$this->identity,'partners');
    }
    private function transitionCooperation(string$id): void
    {
        if(!$this->csrf('/partners/cooperation/'.$id))return;$r=$this->client->post('/api/v1/cooperation/requests/'.$id.'/transition',$this->bearer,['status'=>(string)($_POST['status']??''),'note'=>trim((string)($_POST['note']??''))]);$this->flash($r->ok()?'success':'error',$r->ok()?'وضعیت درخواست همکاری بروزرسانی شد.':$this->apiMessage($r));$this->redirect('/partners/cooperation/'.$id);
    }
    private function uploadCooperationDocument(string$id): void
    {
        if(!$this->csrf('/partners/cooperation/'.$id))return;$file=$_FILES['file']??null;if(!is_array($file)){$this->flash('error','فایل انتخاب نشده است.');$this->redirect('/partners/cooperation/'.$id);} $type=(string)($_POST['document_type']??'cooperation_identity');$r=$this->documents->upload('cooperation_request',$id,$this->bearer,$file,['document_type'=>$type,'relation_code'=>$type]);if($r->ok()){$docId=(string)($r->json['data']['document']['id']??'');if($docId!=='')$this->client->post('/api/v1/cooperation/requests/'.$id.'/documents',$this->bearer,['document_id'=>$docId,'relation_code'=>$type]);}$this->flash($r->ok()?'success':'error',$r->ok()?'مدرک همکاری بارگذاری شد.':$this->apiMessage($r));$this->redirect('/partners/cooperation/'.$id);
    }

    private function marketplace(): void
    {
        if(!$this->can('partners.marketplace.read')){$this->forbidden();return;}$page=max(1,(int)($_GET['page']??1));$r=$this->client->get('/api/v1/marketplace/requests',$this->bearer,['page'=>$page,'per_page'=>20,'q'=>trim((string)($_GET['q']??''))]);if(!$r->ok()){$this->errorPage('بازار پرونده‌ها',$r);return;}$data=(array)($r->json['data']??[]);$rows='';foreach((array)($data['items']??[])as$x){if(!is_array($x))continue;$claim=((string)($x['marketplace_status']??''))==='open'&&$this->can('partners.marketplace.claim')?'<form method="post" action="/partners/marketplace/'.Renderer::e((string)$x['id']).'/claim"><input type="hidden" name="_csrf" value="'.Renderer::e(Csrf::token()).'"><button class="bn-btn bn-btn--small bn-btn--primary">پذیرش پرونده</button></form>':'—';$rows.='<tr><td>'.Renderer::e((string)($x['request_no']??'')).'</td><td>'.Renderer::e((string)($x['plan_name']??'')).'</td><td>'.Renderer::e((string)($x['occupation']??'')).'</td><td>'.Renderer::e((string)($x['request_public_summary']??'')).'</td><td>'.$this->status((string)($x['marketplace_status']??''),(string)($x['marketplace_status']??'')).'</td><td>'.$claim.'</td></tr>';}$rows=$rows?:'<tr><td colspan="6">پرونده‌ای در بازار وجود ندارد.</td></tr>';$content=$this->head('بازار پرونده‌های متقاضی','اطلاعات این فهرست عمداً محدود و بدون داده هویتی حساس است.').$this->takeFlash().'<section class="bn-section"><div class="bn-table-wrap"><table class="bn-table"><thead><tr><th>پرونده</th><th>طرح</th><th>شغل</th><th>خلاصه</th><th>وضعیت</th><th>اقدام</th></tr></thead><tbody>'.$rows.'</tbody></table></div></section>';echo Renderer::page('بازار پرونده‌ها',$content,$this->identity,'partners');
    }
    private function claimMarketplace(string$id): void
    {
        if(!$this->csrf('/partners/marketplace'))return;$r=$this->client->post('/api/v1/marketplace/requests/'.$id.'/claim',$this->bearer);$this->flash($r->ok()?'success':'error',$r->ok()?'پرونده با موفقیت به پذیرنده شما اختصاص یافت.':$this->apiMessage($r));$this->redirect('/partners/marketplace');
    }

    private function importLoansShadow(): void
    {
        if(!$this->isPlatformAdmin()){$this->forbidden();return;}
        if(!$this->csrf('/partners'))return;
        $r=$this->client->post('/api/v1/admin/import-loans-shadow',$this->bearer,[]);
        if(!$r->ok()){
            $this->flash('error',$this->apiMessage($r));
            $this->redirect('/partners');
        }
        $summary=is_array($r->json['data']['import']??null)?$r->json['data']['import']:[];
        $labels=['merchants'=>'پذیرنده','memberships'=>'عضویت','plan_permissions'=>'دسترسی طرح','cooperation_requests'=>'درخواست همکاری','cooperation_documents'=>'مدرک همکاری'];
        $parts=[];$sourceTotal=0;$importedTotal=0;
        foreach($labels as$key=>$label){
            $row=is_array($summary[$key]??null)?$summary[$key]:[];
            $source=max(0,(int)($row['source']??0));$imported=max(0,(int)($row['imported']??0));
            $sourceTotal+=$source;$importedTotal+=$imported;
            if($source>0||$imported>0)$parts[]=$label.': '.PersianDateTime::digits((string)$imported).' از '.PersianDateTime::digits((string)$source);
        }
        if($sourceTotal===0){
            $this->flash('error','همگام‌سازی اجرا شد، اما هیچ داده‌ای در Loans برای انتقال پیدا نشد. اگر پذیرنده قدیمی دارید، ابتدا Hotfix همگام‌سازی Loans را نصب کنید و دوباره این دکمه را بزنید.');
            $this->redirect('/partners');
        }
        $msg='همگام‌سازی کامل شد. '.implode(' | ',$parts);
        $this->flash('success',$msg);
        $this->redirect('/partners');
    }

    private function isPlatformAdmin(): bool{$roles=(array)($this->identity['roles']??[]);return in_array('super_admin',$roles,true)||in_array('admin',$roles,true);}
    private function canAny(): bool{return $this->can('partners.merchants.read')||$this->can('partners.cooperation.read')||$this->can('partners.cooperation.create')||$this->can('partners.marketplace.read')||$this->can('partners.directory.read');}
    private function can(string$p): bool{$roles=(array)($this->identity['roles']??[]);$perms=(array)($this->identity['permissions']??[]);return in_array('super_admin',$roles,true)||in_array($p,$perms,true);}
    private function csrf(string$return): bool{if(Csrf::valid((string)($_POST['_csrf']??'')))return true;$this->flash('error','اعتبار فرم منقضی شده است.');$this->redirect($return);}
    private function redirect(string$p): never{header('Location: '.$p,true,303);exit;}
    private function flash(string$t,string$m): void{$_SESSION['partners_flash']=['type'=>$t,'message'=>$m];}
    private function takeFlash(): string{$f=$_SESSION['partners_flash']??null;unset($_SESSION['partners_flash']);return is_array($f)?$this->alert((string)($f['type']??'info'),($f['type']??'')==='success'?'عملیات انجام شد':'عملیات کامل نشد',(string)($f['message']??'')):'';}
    private function head(string$t,string$s,string$a=''): string{return'<div class="bn-pagehead"><div class="bn-pagehead-main"><div class="bn-eyebrow">Partners Service</div><h1 class="bn-title">'.Renderer::e($t).'</h1><p class="bn-subtitle">'.Renderer::e($s).'</p></div>'.($a!==''?'<div class="bn-actions">'.$a.'</div>':'').'</div>';}
    private function card(string$href,string$title,string$text): string{return'<a class="bn-action-card" href="'.Renderer::e($href).'"><strong>'.Renderer::e($title).'</strong><span>'.Renderer::e($text).'</span></a>';}
    private function stat(string$l,mixed$v,string$c,bool$a=false): string{return'<div class="bn-stat'.($a?' bn-stat--accent':'').'"><div class="bn-stat-label">'.Renderer::e($l).'</div><div class="bn-stat-value">'.Renderer::e(PersianDateTime::digits((string)$v)).'</div><div class="bn-stat-caption">'.Renderer::e($c).'</div></div>';}
    private function field(string$l,string$n,string$v,string$ph=''): string{return'<div class="bn-field"><label class="bn-label">'.Renderer::e($l).'</label><input name="'.Renderer::e($n).'" value="'.Renderer::e($v).'" placeholder="'.Renderer::e($ph).'"></div>';}
    private function detail(string$l,string$v): string{return'<div class="bn-detail-item"><span>'.Renderer::e($l).'</span><strong>'.Renderer::e($v).'</strong></div>';}
    private function option(string$v,string$l,string$c): string{return'<option value="'.Renderer::e($v).'"'.($v===$c?' selected':'').'>'.Renderer::e($l).'</option>';}
    private function status(string$c,string$l): string{$cls=in_array($c,['active','approved','claimed'],true)?'bn-status--ok':(in_array($c,['rejected','suspended'],true)?'bn-status--bad':'bn-status--warn');return'<span class="bn-status '.$cls.'">'.Renderer::e($l).'</span>';}
    private function merchantStatus(string$s): string{return['active'=>'فعال','inactive'=>'غیرفعال','suspended'=>'تعلیق','archived'=>'بایگانی'][$s]??$s;}
    private function cooperationStatus(string$s): string{return['pending'=>'در انتظار','submitted'=>'ثبت‌شده','under_review'=>'در حال بررسی','need_correction'=>'نقص مدارک','approved'=>'تأییدشده','rejected'=>'ردشده','cancelled'=>'لغوشده','suspended'=>'تعلیق','archived'=>'بایگانی'][$s]??$s;}
    private function alert(string$t,string$title,string$m): string{return'<div class="bn-alert bn-alert--'.Renderer::e($t).'"><strong>'.Renderer::e($title).'</strong><br>'.Renderer::e($m).'</div>';}
    private function apiMessage(HttpResponse$r): string{$m=$r->json['error']['message']??null;return is_string($m)&&trim($m)!==''?$m:($r->networkError!==null?'ارتباط با سرویس برقرار نشد.':'عملیات با پاسخ معتبر کامل نشد.');}
    private function errorPage(string$t,HttpResponse$r): void{http_response_code($r->status>=400&&$r->status<600?$r->status:503);echo Renderer::page($t,$this->head($t,'این بخش فعلاً قابل نمایش نیست.').$this->alert('error','عملیات کامل نشد',$this->apiMessage($r)),$this->identity,'partners');}
    private function forbidden(): void{http_response_code(403);echo Renderer::page('دسترسی محدود',$this->head('دسترسی محدود','مجوز لازم برای این بخش روی حساب شما فعال نیست.'),$this->identity,'partners');}
    private function pagination(string$path,int$page,int$per,int$total,array$params=[]): string{$pages=max(1,(int)ceil($total/max(1,$per)));if($pages<=1)return'';$mk=static function(int$p)use($path,$params):string{$q=$params;$q['page']=$p;return$path.'?'.http_build_query(array_filter($q,static fn($v)=>$v!==''&&$v!==null));};return'<nav class="bn-pagination"><span>صفحه '.PersianDateTime::digits((string)$page).' از '.PersianDateTime::digits((string)$pages).'</span><span class="bn-pagination-actions"><a class="bn-btn bn-btn--small bn-btn--secondary" href="'.Renderer::e($mk(max(1,$page-1))).'">قبلی</a><a class="bn-btn bn-btn--small bn-btn--secondary" href="'.Renderer::e($mk(min($pages,$page+1))).'">بعدی</a></span></nav>';}
 private function resolveCooperationLegacy():void
 {
  $legacy=trim((string)($_GET['legacy_id']??''));if(!preg_match('/^[1-9][0-9]{0,15}$/',$legacy)){$this->flash('error','شناسه قدیمی معتبر نیست.');$this->redirect('/partners/cooperation');}
  $r=$this->client->get('/api/v1/admin/cooperation/resolve-legacy',$this->bearer,['legacy_id'=>$legacy]);$id=(string)($r->json['data']['request']['id']??'');
  if($r->ok()&&preg_match('/^[a-f0-9]{26}$/',$id))$this->redirect('/partners/cooperation/'.$id.'/correct');
  $this->flash('error',$r->ok()?'درخواست همکاری با این شناسه پیدا نشد.':$this->apiMessage($r));$this->redirect('/partners/cooperation');
 }
 private function cooperationCorrectionForm(string$id):void
 {
  $r=$this->client->get('/api/v1/admin/cooperation/'.$id.'/profile-corrections',$this->bearer);
  if(!$r->ok()){$this->errorPage('اصلاح درخواست همکاری',$r);return;}
  $x=(array)($r->json['data']['request']??[]);$d=(array)($x['data']??[]);$history=(array)($r->json['data']['corrections']??[]);
  $fields=['full_name'=>'نام و نام خانوادگی','father_name'=>'نام پدر','company_name'=>'نام شرکت','legal_name'=>'نام حقوقی','trade_name'=>'نام تجاری','national_code'=>'کد ملی','applicant_id_number'=>'شناسه ملی متقاضی','national_id'=>'شناسه ملی شرکت','registration_number'=>'شماره ثبت','mobile'=>'موبایل','phone'=>'تلفن','postal_code'=>'کد پستی','address'=>'نشانی','activity'=>'فعالیت','representative_name'=>'نماینده'];
  $inputs='';foreach($fields as$k=>$label){$inputs.='<div class="bn-field"><label class="bn-label">'.Renderer::e($label).'</label><input name="fields['.Renderer::e($k).']" value="'.Renderer::e((string)($d[$k]??'')).'"></div>';}
  $rows='';foreach($history as$h){if(!is_array($h))continue;$rows.='<tr><td>'.Renderer::e((string)($h['actor_user_id']??'')).'</td><td>'.Renderer::e(implode('، ',(array)($h['changed_fields']??[]))).'</td><td>'.Renderer::e((string)($h['reason']??'')).'</td><td>'.Renderer::e((string)($h['created_at']??'')).'</td></tr>';}$rows=$rows?:'<tr><td colspan="4">تاریخچه‌ای ثبت نشده است.</td></tr>';
  $content=$this->head('اصلاح اطلاعات همکاری '.(string)($x['request_no']??''),'اطلاعات پروفایل اصلاح می‌شوند؛ وضعیت درخواست، قرارداد و امضا تغییر نمی‌کنند.','<a class="bn-btn bn-btn--secondary" href="/partners/cooperation/'.$id.'">بازگشت</a>').$this->takeFlash();
  $content.='<section class="bn-section"><form method="post" action="/partners/cooperation/'.$id.'/correct" data-submit-lock="1"><input type="hidden" name="_csrf" value="'.Renderer::e(Csrf::token()).'"><input type="hidden" name="row_version" value="'.(int)($x['row_version']??0).'"><div class="bn-grid bn-grid--2">'.$inputs.'</div><div class="bn-field"><label class="bn-label">دلیل اصلاح</label><textarea name="reason" required minlength="10" maxlength="1000"></textarea></div><label><input type="checkbox" name="acknowledge_signed" value="1"> تأیید می‌کنم قراردادهای امضاشده موجود تغییر نمی‌کنند.</label><div class="bn-form-actions"><button type="submit" class="bn-btn bn-btn--primary">ثبت اصلاح</button></div></form></section>';
  $content.='<section class="bn-section"><h2 class="bn-sectiontitle">تاریخچه اصلاحات</h2><div class="bn-table-wrap"><table class="bn-table"><thead><tr><th>مدیر</th><th>فیلدها</th><th>دلیل</th><th>زمان</th></tr></thead><tbody>'.$rows.'</tbody></table></div></section>';
  echo Renderer::page('اصلاح همکاری',$content,$this->identity,'partners');
 }
 private function correctCooperation(string$id):void
 {
  if(!$this->csrf('/partners/cooperation/'.$id.'/correct'))return;
  $r=$this->client->post('/api/v1/admin/cooperation/'.$id.'/profile-corrections',$this->bearer,['fields'=>is_array($_POST['fields']??null)?$_POST['fields']:[],'row_version'=>(int)($_POST['row_version']??0),'reason'=>trim((string)($_POST['reason']??'')),'acknowledge_signed'=>isset($_POST['acknowledge_signed'])]);
  $this->flash($r->ok()?'success':'error',$r->ok()?'مشخصات درخواست همکاری اصلاح شد.':$this->apiMessage($r));$this->redirect('/partners/cooperation/'.$id.'/correct');
 }

}
