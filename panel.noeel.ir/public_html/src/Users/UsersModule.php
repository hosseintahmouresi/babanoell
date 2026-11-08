<?php
declare(strict_types=1);

namespace Babanoell\Panel\Users;

use Babanoell\Panel\Http\HttpResponse;
use Babanoell\Panel\Http\IdentityClient;
use Babanoell\Panel\Security\Csrf;
use Babanoell\Panel\Support\PersianDateTime;
use Babanoell\Panel\View\Renderer;

final class UsersModule
{
    public function __construct(
        private readonly IdentityClient $client,
        private readonly string $bearer,
        private readonly array $identity,
    ) {}

    public function handle(string $path, string $method): bool
    {
        if (!$this->canReadUsers()) return false;
        if ($path === '/users') { $this->index(); return true; }
        if ($path === '/users/new') { if(!$this->canManageUsers()){$this->errorPage(new HttpResponse(403,['error'=>['message'=>'اجازه مدیریت کاربران برای این حساب فعال نیست.']],'',''));return true;} $method === 'POST' ? $this->create() : $this->form(); return true; }
        if ($path === '/users/roles' && $method === 'POST') { if(!$this->canManageUsers()){$this->flash('error','اجازه مدیریت نقش‌ها برای این حساب فعال نیست.');header('Location: /users',true,303);exit;} $this->saveRoles(); return true; }
        if ($path === '/users/status' && $method === 'POST') { if(!$this->canManageUsers()){$this->flash('error','اجازه مدیریت وضعیت کاربران برای این حساب فعال نیست.');header('Location: /users',true,303);exit;} $this->saveStatus(); return true; }
        if ($path === '/users/access' && $this->canReadAccess()) { $method === 'POST' ? $this->saveAccess() : $this->accessForm(); return true; }
        return false;
    }

    private function index(): void
    {
        $page=max(1,(int)($_GET['page']??1));$q=trim((string)($_GET['q']??''));
        $users=$this->client->get('/api/v1/admin/users',$this->bearer,['page'=>$page,'per_page'=>30,'q'=>$q]);
        $rolesResponse=$this->client->get('/api/v1/roles',$this->bearer);
        if(!$users->ok()||!$rolesResponse->ok()){$this->errorPage(!$users->ok()?$users:$rolesResponse);return;}
        $data=is_array($users->json['data']??null)?$users->json['data']:[];
        $items=is_array($data['items']??null)?$data['items']:[];
        $roles=is_array($rolesResponse->json['data']['items']??null)?$rolesResponse->json['data']['items']:[];
        $rows='';
        foreach($items as$user){if(!is_array($user))continue;$id=(string)($user['id']??'');$current=is_array($user['roles']??null)?$user['roles']:[];$checks='';foreach($roles as$role){if(!is_array($role))continue;$name=(string)($role['name']??'');if($name==='')continue;if(!$this->isSuperAdmin()&&in_array($name,['super_admin','admin'],true))continue;$checks.='<label class="bn-check"><input type="checkbox" name="roles[]" value="'.Renderer::e($name).'"'.(in_array($name,$current,true)?' checked':'').'><span>'.Renderer::e((string)($role['label']??$name)).'</span></label>';}
            $status=(string)($user['status']??'');$protectedTarget=in_array('super_admin',$current,true)||in_array('admin',$current,true);$editable=$this->canManageUsers()&&(!$protectedTarget||$this->isSuperAdmin());
            $commissionLink=array_intersect(['super_admin','admin'],$this->identity['roles']??[])?'<a class="bn-textlink" href="/users/'.$id.'/commission">کمیسیون پذیرنده</a> <a class="bn-textlink" href="/users/'.$id.'/plans">طرح‌های غیرفعال</a>':'';$accessLink=$this->canReadAccess()?'<a class="bn-textlink" href="/users/access?user_id='.rawurlencode($id).'">دامنه دسترسی</a>':'';$roleCell=$editable?'<form method="post" action="/users/roles" class="bn-role-form"><input type="hidden" name="_csrf" value="'.Renderer::e(Csrf::token()).'"><input type="hidden" name="user_id" value="'.Renderer::e($id).'"><div class="bn-check-group">'.$checks.'</div><button class="bn-btn bn-btn--secondary bn-btn--small" type="submit">ذخیره نقش‌ها</button></form>':'<span class="bn-muted-inline">فقط مدیر ارشد</span>';$statusCell=$editable?'<form method="post" action="/users/status" class="bn-inline-form"><input type="hidden" name="_csrf" value="'.Renderer::e(Csrf::token()).'"><input type="hidden" name="user_id" value="'.Renderer::e($id).'"><input type="hidden" name="status" value="'.($status==='active'?'disabled':'active').'"><button class="bn-btn bn-btn--ghost bn-btn--small" type="submit">'.($status==='active'?'غیرفعال‌کردن':'فعال‌کردن').'</button></form>':'<span class="bn-muted-inline">—</span>';$rows.='<tr><td><strong>'.Renderer::e((string)($user['display_name']??'—')).'</strong><small>'.Renderer::e((string)($user['mobile']??$user['email']??'—')).'</small>'.$accessLink.$commissionLink.'</td>'
                .'<td>'.Renderer::e((string)($user['role_labels']??implode('، ',$current) ?: 'بدون نقش')).'</td>'
                .'<td>'.($status==='active'?'<span class="bn-status bn-status--ok">فعال</span>':'<span class="bn-status bn-status--bad">غیرفعال</span>').'</td>'
                .'<td>'.$roleCell.'</td><td>'.$statusCell.'</td></tr>';
        }
        if($rows==='')$rows='<tr><td colspan="5"><div class="bn-empty-inline"><strong>کاربری پیدا نشد</strong><span>عبارت جستجو را تغییر دهید یا کاربر جدید بسازید.</span></div></td></tr>';
        $createAction=$this->canManageUsers()?'<a class="bn-btn bn-btn--primary" href="/users/new">ایجاد کاربر</a>':'';$content=$this->head('کاربران و نقش‌ها','ساخت حساب سازمانی، تخصیص نقش‌های عملیاتی و مدیریت وضعیت دسترسی کاربران.',$createAction)
            .$this->takeFlash()
            .'<section class="bn-section"><form class="bn-filterbar" method="get" action="/users"><div class="bn-field bn-filter-search"><label class="bn-label">جستجوی کاربر</label><input type="search" name="q" value="'.Renderer::e($q).'" placeholder="نام، موبایل یا ایمیل"></div><div class="bn-filter-actions"><button class="bn-btn bn-btn--primary" type="submit">جستجو</button><a class="bn-btn bn-btn--secondary" href="/users">پاک‌کردن</a></div></form></section>'
            .'<section class="bn-section"><div class="bn-table-wrap"><table class="bn-table"><thead><tr><th>کاربر</th><th>نقش فعلی</th><th>وضعیت</th><th>تخصیص نقش</th><th>حساب</th></tr></thead><tbody>'.$rows.'</tbody></table></div>'.$this->pagination((int)($data['page']??$page),(int)($data['per_page']??30),(int)($data['total']??count($items)),$q).'</section>';
        echo Renderer::page('کاربران و نقش‌ها',$content,$this->identity,'users');
    }

    private function form(string $error=''): void
    {
        $rolesResponse=$this->client->get('/api/v1/roles',$this->bearer);
        if(!$rolesResponse->ok()){$this->errorPage($rolesResponse);return;}
        $roles=is_array($rolesResponse->json['data']['items']??null)?$rolesResponse->json['data']['items']:[];$selected=is_array($_POST['roles']??null)?$_POST['roles']:[];$checks='';
        foreach($roles as$role){if(!is_array($role))continue;$name=(string)($role['name']??'');if(!$this->isSuperAdmin()&&in_array($name,['super_admin','admin'],true))continue;$checks.='<label class="bn-check"><input type="checkbox" name="roles[]" value="'.Renderer::e($name).'"'.(in_array($name,$selected,true)?' checked':'').'><span><strong>'.Renderer::e((string)($role['label']??$name)).'</strong><small>'.Renderer::e((string)($role['description']??'')).'</small></span></label>';}
        $v=static fn(string$key):string=>(string)($_POST[$key]??'');
        $content=$this->head('ایجاد کاربر','حساب سازمانی بسازید و حداقل یک نقش متناسب با مسئولیت کاربر انتخاب کنید.')
            .($error!==''?$this->alert('error','ایجاد کاربر انجام نشد',$error):'')
            .'<form method="post" action="/users/new" data-submit-lock="1"><input type="hidden" name="_csrf" value="'.Renderer::e(Csrf::token()).'"><section class="bn-section"><div class="bn-grid bn-grid--2">'
            .$this->field('نام نمایشی','display_name',$v('display_name'),true).$this->field('شماره موبایل','mobile',$v('mobile'),false,'tel','مثال: 09121234567').$this->field('نام کاربری (اختیاری)','username',$v('username'),false,'text','مثال: ali.reza').$this->field('ایمیل','email',$v('email'),false,'email').$this->field('رمز عبور اولیه','password','',true,'password','حداقل ۱۰ نویسه').'</div></section>'
            .'<section class="bn-section"><div class="bn-sectionhead"><span class="bn-step">۲</span><div><h2 class="bn-sectiontitle">نقش کاربری</h2><p class="bn-sectiondesc">دسترسی‌ها از Role مرکزی Identity اعمال می‌شوند و صرفاً نمایشی نیستند.</p></div></div><div class="bn-check-grid">'.$checks.'</div></section>'
            .'<div class="bn-form-actions"><button class="bn-btn bn-btn--primary" type="submit">ایجاد حساب</button><a class="bn-btn bn-btn--secondary" href="/users">انصراف</a></div></form>';
        echo Renderer::page('ایجاد کاربر',$content,$this->identity,'users');
    }

    private function create(): void
    {
        if(!Csrf::valid((string)($_POST['_csrf']??''))){$this->form('اعتبار فرم منقضی شده است.');return;}
        $roles=is_array($_POST['roles']??null)?array_values(array_filter(array_map('strval',$_POST['roles']))):[];
        if($roles===[]){$this->form('حداقل یک نقش برای کاربر انتخاب کنید.');return;}
        $r=$this->client->post('/api/v1/admin/users',$this->bearer,['username'=>trim((string)($_POST['username']??'')),'display_name'=>trim((string)($_POST['display_name']??'')),'mobile'=>trim((string)($_POST['mobile']??'')),'email'=>trim((string)($_POST['email']??'')),'password'=>(string)($_POST['password']??''),'roles'=>$roles]);
        if(!$r->ok()){$this->form($this->apiMessage($r));return;}$this->flash('success','حساب کاربری ایجاد و نقش‌های انتخاب‌شده اعمال شد.');header('Location: /users',true,303);exit;
    }

    private function saveRoles(): void
    {
        if(!Csrf::valid((string)($_POST['_csrf']??''))){$this->flash('error','اعتبار فرم منقضی شده است.');header('Location: /users',true,303);exit;}
        $roles=is_array($_POST['roles']??null)?array_values(array_filter(array_map('strval',$_POST['roles']))):[];$r=$this->client->post('/api/v1/admin/user-roles',$this->bearer,['user_id'=>trim((string)($_POST['user_id']??'')),'roles'=>$roles]);$this->flash($r->ok()?'success':'error',$r->ok()?'نقش‌های کاربر به‌روزرسانی شد.':$this->apiMessage($r));header('Location: /users',true,303);exit;
    }

    private function saveStatus(): void
    {
        if(!Csrf::valid((string)($_POST['_csrf']??''))){$this->flash('error','اعتبار فرم منقضی شده است.');header('Location: /users',true,303);exit;}
        $r=$this->client->post('/api/v1/admin/user-status',$this->bearer,['user_id'=>trim((string)($_POST['user_id']??'')),'status'=>trim((string)($_POST['status']??''))]);$this->flash($r->ok()?'success':'error',$r->ok()?'وضعیت حساب کاربر به‌روزرسانی شد.':$this->apiMessage($r));header('Location: /users',true,303);exit;
    }


    private function accessForm(string $error=''): void
    {
        $uid=trim((string)($_GET['user_id']??$_POST['user_id']??''));if($uid===''){$this->flash('error','کاربر انتخاب نشده است.');header('Location: /users',true,303);exit;}
        $access=$this->client->get('/api/v1/admin/user-access',$this->bearer,['user_id'=>$uid]);$orgs=$this->client->get('/api/v1/admin/organizations',$this->bearer);$branches=$this->client->get('/api/v1/admin/branches',$this->bearer);
        foreach([$access,$orgs,$branches]as$r)if(!$r->ok()){$this->errorPage($r);return;}
        $a=is_array($access->json['data']??null)?$access->json['data']:[];$user=is_array($a['user']??null)?$a['user']:[];$members=is_array($a['memberships']??null)?$a['memberships']:[];$grants=is_array($a['grants']??null)?$a['grants']:[];$organizations=is_array($orgs->json['data']['items']??null)?$orgs->json['data']['items']:[];$branchItems=is_array($branches->json['data']['items']??null)?$branches->json['data']['items']:[];
        $memberMap=[];foreach($members as$m)if(is_array($m)&&is_string($m['organization_id']??null))$memberMap[$m['organization_id']]=$m;
        $branchMap=[];foreach($branchItems as$b)if(is_array($b)&&is_string($b['organization_id']??null))$branchMap[$b['organization_id']][]=$b;
        $editable=$this->canManageAccess();$rows='';foreach($organizations as$o){if(!is_array($o))continue;$oid=(string)($o['id']??'');if($oid==='')continue;$m=$memberMap[$oid]??[];$checked=$m!==[];$opts='<option value="">همه سازمان / بدون شعبه</option>';foreach($branchMap[$oid]??[]as$b){$bid=(string)($b['id']??'');$opts.='<option value="'.Renderer::e($bid).'"'.(((string)($m['branch_id']??''))===$bid?' selected':'').'>'.Renderer::e((string)($b['name']??$bid)).'</option>';}$rows.='<div class="bn-access-row"><label class="bn-check"><input type="checkbox" name="memberships['.Renderer::e($oid).'][enabled]" value="1"'.($checked?' checked':'').($editable?'':' disabled').'><span><strong>'.Renderer::e((string)($o['name']??$oid)).'</strong><small>'.Renderer::e((string)($o['organization_type']??'')).'</small></span></label><select name="memberships['.Renderer::e($oid).'][branch_id]"'.($editable?'':' disabled').'>'.$opts.'</select><input type="text" name="memberships['.Renderer::e($oid).'][title]" value="'.Renderer::e((string)($m['title']??'')).'" placeholder="عنوان سازمانی"'.($editable?'':' disabled').'><label class="bn-check bn-check--compact"><input type="radio" name="primary_organization_id" value="'.Renderer::e($oid).'"'.(!empty($m['is_primary'])?' checked':'').($editable?'':' disabled').'><span>اصلی</span></label></div>';}
        if($rows==='')$rows='<div class="bn-empty-inline"><strong>سازمانی تعریف نشده است</strong><span>ابتدا سازمان‌ها و شعب را در Identity تعریف کنید.</span></div>';
        $grantLines=[];foreach($grants as$g)if(is_array($g))$grantLines[]=implode(' | ',[(string)($g['effect']??'allow'),(string)($g['scope_type']??''),(string)($g['scope_id']??'')]);$grantText=implode("\n",$grantLines);
        $name=(string)($user['display_name']??$uid);$actions='<a class="bn-btn bn-btn--secondary" href="/users">بازگشت</a>';
        $content=$this->head('دامنه دسترسی '.$name,'محدوده سازمانی و استثناهای دسترسی این حساب در سطح Identity و سرویس‌ها اعمال می‌شود.',$actions).($error!==''?$this->alert('error','ذخیره انجام نشد',$error):'').$this->takeFlash();
        $content.='<section class="bn-section"><div class="bn-sectionhead"><div><h2 class="bn-sectiontitle">عضویت سازمانی</h2><p class="bn-sectiondesc">عضویت سازمان/شعبه، دامنه پیش‌فرض مشاهده رکوردهای مرتبط را تعیین می‌کند.</p></div></div><form method="post" action="/users/access"><input type="hidden" name="_csrf" value="'.Renderer::e(Csrf::token()).'"><input type="hidden" name="user_id" value="'.Renderer::e($uid).'"><input type="hidden" name="mode" value="memberships"><div class="bn-access-list">'.$rows.'</div>'.($editable?'<div class="bn-form-actions"><button class="bn-btn bn-btn--primary" type="submit">ذخیره عضویت‌ها</button></div>':'').'</form></section>';
        $content.='<section class="bn-section"><div class="bn-sectionhead"><div><h2 class="bn-sectiontitle">استثناهای Scope</h2><p class="bn-sectiondesc">هر خط: allow یا deny | نوع دامنه | شناسه. انواع مجاز: global، organization، branch، merchant، loan_request.</p></div></div><form method="post" action="/users/access"><input type="hidden" name="_csrf" value="'.Renderer::e(Csrf::token()).'"><input type="hidden" name="user_id" value="'.Renderer::e($uid).'"><input type="hidden" name="mode" value="scopes"><textarea class="bn-textarea" name="grants_text" rows="8"'.($editable?'':' readonly').' placeholder="allow | merchant | ...&#10;deny | loan_request | ...">'.Renderer::e($grantText).'</textarea>'.($editable?'<div class="bn-form-actions"><button class="bn-btn bn-btn--primary" type="submit">ذخیره Scopeها</button></div>':'').'</form></section>';
        echo Renderer::page('دامنه دسترسی',$content,$this->identity,'users');
    }

    private function saveAccess(): void
    {
        $uid=trim((string)($_POST['user_id']??''));if(!$this->canManageAccess()){$this->accessForm('اجازه تغییر دامنه دسترسی برای این حساب فعال نیست.');return;}if(!Csrf::valid((string)($_POST['_csrf']??''))){$this->accessForm('اعتبار فرم منقضی شده است.');return;}$mode=(string)($_POST['mode']??'');
        if($mode==='memberships'){$raw=is_array($_POST['memberships']??null)?$_POST['memberships']:[];$primary=trim((string)($_POST['primary_organization_id']??''));$items=[];foreach($raw as$org=>$row){if(!is_array($row)||empty($row['enabled']))continue;$org=(string)$org;$items[]=['organization_id'=>$org,'branch_id'=>trim((string)($row['branch_id']??'')),'title'=>trim((string)($row['title']??'')),'is_primary'=>$org===$primary];}$r=$this->client->post('/api/v1/admin/user-memberships',$this->bearer,['user_id'=>$uid,'memberships'=>$items]);}
        elseif($mode==='scopes'){$items=[];$lines=preg_split('/\R/u',(string)($_POST['grants_text']??''))?:[];foreach($lines as$line){$line=trim($line);if($line==='')continue;$parts=array_map('trim',explode('|',$line,3));if(count($parts)!==3){$this->accessForm('فرمت یکی از خطوط Scope معتبر نیست.');return;}$items[]=['effect'=>strtolower($parts[0]),'scope_type'=>strtolower($parts[1]),'scope_id'=>$parts[2]];}$r=$this->client->post('/api/v1/admin/user-scopes',$this->bearer,['user_id'=>$uid,'grants'=>$items]);}
        else{$this->accessForm('عملیات درخواستی معتبر نیست.');return;}
        if(!$r->ok()){$this->accessForm($this->apiMessage($r));return;}$this->flash('success','دامنه دسترسی کاربر به‌روزرسانی شد.');header('Location: /users/access?user_id='.rawurlencode($uid),true,303);exit;
    }

    private function canReadAccess(): bool{$r=is_array($this->identity['roles']??null)?$this->identity['roles']:[];$p=is_array($this->identity['permissions']??null)?$this->identity['permissions']:[];return in_array('super_admin',$r,true)||in_array('identity.memberships.read',$p,true);}
    private function canManageAccess(): bool{$r=is_array($this->identity['roles']??null)?$this->identity['roles']:[];$p=is_array($this->identity['permissions']??null)?$this->identity['permissions']:[];return in_array('super_admin',$r,true)||in_array('identity.memberships.manage',$p,true);}

    private function isSuperAdmin(): bool{$r=is_array($this->identity['roles']??null)?$this->identity['roles']:[];return in_array('super_admin',$r,true); }
    private function canManageUsers(): bool{$p=is_array($this->identity['permissions']??null)?$this->identity['permissions']:[];return $this->isSuperAdmin()||in_array('identity.users.manage',$p,true); }
    private function canReadUsers(): bool{$r=is_array($this->identity['roles']??null)?$this->identity['roles']:[];$p=is_array($this->identity['permissions']??null)?$this->identity['permissions']:[];return in_array('super_admin',$r,true)||in_array('identity.users.read',$p,true);}
    private function head(string$title,string$subtitle,string$actions=''):string{return'<div class="bn-pagehead"><div class="bn-pagehead-main"><div class="bn-eyebrow">مدیریت دسترسی</div><h1 class="bn-title">'.Renderer::e($title).'</h1><p class="bn-subtitle">'.Renderer::e($subtitle).'</p></div>'.($actions!==''?'<div class="bn-actions">'.$actions.'</div>':'').'</div>';}
    private function field(string$label,string$name,string$value,bool$required=false,string$type='text',string$helper=''):string{return'<div class="bn-field"><label class="bn-label">'.Renderer::e($label).($required?' <span class="bn-required">*</span>':'').'</label><input type="'.Renderer::e($type).'" name="'.Renderer::e($name).'" value="'.Renderer::e($value).'"'.($required?' required':'').' autocomplete="off">'.($helper!==''?'<span class="bn-helper">'.Renderer::e($helper).'</span>':'').'</div>';}
    private function apiMessage(HttpResponse$r):string{$m=$r->json['error']['message']??null;return is_string($m)&&trim($m)!==''?$m:($r->status===0?'ارتباط با سرویس احراز هویت برقرار نشد.':'عملیات کامل نشد. دوباره تلاش کنید.');}
    private function errorPage(HttpResponse$r):void{$content=$this->head('کاربران و نقش‌ها','این بخش در حال حاضر قابل نمایش نیست.').$this->alert('error','دریافت اطلاعات انجام نشد',$this->apiMessage($r));echo Renderer::page('کاربران و نقش‌ها',$content,$this->identity,'users');}
    private function flash(string$type,string$message):void{$_SESSION['panel_users_flash']=['type'=>$type,'message'=>$message];}
    private function takeFlash():string{$f=$_SESSION['panel_users_flash']??null;unset($_SESSION['panel_users_flash']);if(!is_array($f)||!is_string($f['message']??null))return'';$t=($f['type']??'')==='success'?'success':'error';return$this->alert($t,$t==='success'?'عملیات موفق بود':'عملیات انجام نشد',$f['message']);}
    private function alert(string$type,string$title,string$message):string{return'<div class="bn-alert bn-alert--'.Renderer::e($type).'" role="alert"><strong>'.Renderer::e($title).'</strong><br>'.Renderer::e($message).'</div>';}
    private function pagination(int$page,int$per,int$total,string$q):string{$pages=max(1,(int)ceil($total/max(1,$per)));if($pages<=1)return'<div class="bn-pagination-meta">'.PersianDateTime::digits((string)$total).' کاربر</div>';$page=max(1,min($pages,$page));$link=static fn(int$p):string=>'/users?'.http_build_query(array_filter(['q'=>$q,'page'=>$p],static fn($v)=>$v!==''));return'<nav class="bn-pagination"><a class="bn-btn bn-btn--secondary bn-btn--small'.($page<=1?' is-disabled':'').'"'.($page>1?' href="'.Renderer::e($link($page-1)).'"':' aria-disabled="true"').'>قبلی</a><span>صفحه '.PersianDateTime::digits((string)$page).' از '.PersianDateTime::digits((string)$pages).'</span><a class="bn-btn bn-btn--secondary bn-btn--small'.($page>=$pages?' is-disabled':'').'"'.($page<$pages?' href="'.Renderer::e($link($page+1)).'"':' aria-disabled="true"').'>بعدی</a></nav>';}
}
