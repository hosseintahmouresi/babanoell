<?php
declare(strict_types=1);
namespace Babanoell\Panel\Notification;
use Babanoell\Panel\Http\LoansClient;
use Babanoell\Panel\Security\Csrf;
use Babanoell\Panel\Support\PersianDateTime;
use Babanoell\Panel\View\Renderer;

final class NotificationModule
{
    private const STATUSES=['under_review'=>'در حال بررسی','need_correction'=>'نقص مدارک','docs_completion'=>'تکمیل مدارک','docs_verified'=>'تأیید مدارک','granted'=>'اعطا شده','rejected'=>'رد شده'];
    public function __construct(private readonly LoansClient $client,private readonly string $bearer,private readonly array $identity){}
    private function admin(): bool {return (bool)array_intersect(['super_admin','admin'],$this->identity['roles']??[]);}
    public function handle(string $path,string $method): bool
    {
        if(!preg_match('#^/notifications(?:/(settings|deliveries)|/([a-f0-9]{26})/(read|retry|reconcile))?$#',$path,$match))return false;
        $section=$match[1]??'';$id=$match[2]??'';$action=$match[3]??'';
        if(($section!==''||in_array($action,['retry','reconcile'],true))&&!$this->admin()){http_response_code(403);$this->page('دسترسی محدود','<p>مدیریت پیامک‌ها فقط برای مدیر سامانه مجاز است.</p>');return true;}
        if($method==='POST'){
            if(!Csrf::valid((string)($_POST['_csrf']??''))){http_response_code(403);$this->page('فرم منقضی شده','<p>صفحه را تازه‌سازی کنید.</p>');return true;}
            if($action==='read')$response=$this->client->post('/api/v1/notifications/'.$id.'/read',$this->bearer,[]);
            elseif($action==='retry')$response=$this->client->post('/api/v1/admin/notifications/deliveries/'.$id.'/retry',$this->bearer,[]);
            elseif($action==='reconcile')$response=$this->client->post('/api/v1/admin/notifications/deliveries/'.$id.'/reconcile',$this->bearer,['status'=>(string)($_POST['status']??''),'reference'=>(string)($_POST['reference']??''),'note'=>(string)($_POST['note']??''),'confirmed'=>isset($_POST['confirmed'])]);
            elseif($section==='settings'){
                $rules=[];
                foreach(self::STATUSES as $key=>$title){
                    $input=is_array($_POST['statuses'][$key]??null)?$_POST['statuses'][$key]:[];
                    $variables=[];foreach(['var_0','var_1','var_2'] as $var)if(isset($input[$var])&&trim((string)$input[$var])!=='')$variables[]=trim((string)$input[$var]);
                    $rules[$key]=['enabled'=>isset($input['enabled']),'pattern_id'=>(int)strtr((string)($input['pattern_id']??'0'),['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9']),'variables'=>$variables,'send_to_applicant'=>isset($input['send_to_applicant']),'send_to_acceptor'=>isset($input['send_to_acceptor'])];
                }
                $response=$this->client->post('/api/v1/admin/notifications/settings',$this->bearer,['revision'=>(int)($_POST['revision']??0),'settings'=>['loan_statuses'=>$rules]]);
            }else{http_response_code(405);return true;}
            $_SESSION['notification_flash']=$response->ok()?'ذخیره شد.':(string)($response->json['error']['message']??'عملیات کامل نشد.');
            header('Location: /notifications'.($section!==''?'/'.$section:(in_array($action,['retry','reconcile'],true)?'/deliveries':'')),true,303);return true;
        }
        if($method!=='GET'){http_response_code(405);return true;}
        if($section==='settings')$this->settings();elseif($section==='deliveries')$this->deliveries();else $this->inbox();return true;
    }
    private function page(string $title,string $body): void
    {
        header('Cache-Control: private,no-store');
        $flash=$_SESSION['notification_flash']??null;unset($_SESSION['notification_flash']);
        $nav='<div class="bn-actions"><a class="bn-btn bn-btn--secondary" href="/notifications">اعلان‌های من</a>'.($this->admin()?'<a class="bn-btn bn-btn--secondary" href="/notifications/deliveries">وضعیت ارسال پیامک</a><a class="bn-btn bn-btn--secondary" href="/notifications/settings">قالب‌ها و گیرندگان</a>':'').'</div>';
        echo Renderer::page($title,'<div class="bn-pagehead"><h1 class="bn-title">'.$title.'</h1></div>'.$nav.($flash?'<p class="bn-alert" role="status">'.Renderer::e((string)$flash).'</p>':'').$body,$this->identity,'notifications');
    }
    private function load(string $path): ?array
    {
        $response=$this->client->get($path,$this->bearer);
        if(!$response->ok()){http_response_code($response->status>=400?$response->status:503);$this->page('اعلان‌ها','<p class="bn-alert bn-alert--error">'.Renderer::e((string)($response->json['error']['message']??'سرویس اعلان‌ها در دسترس نیست.')).'</p>');return null;}
        return $response->json['data'];
    }
    private function inbox(): void
    {
        $page=max(1,(int)($_GET['page']??1));$data=$this->load('/api/v1/notifications?page='.$page);if($data===null)return;
        $html='<p>اعلان خوانده‌نشده: '.Renderer::e(PersianDateTime::digits((string)$data['unread'])).'</p>';
        foreach($data['items'] as $row){
            $html.='<article class="bn-section"><h2 class="bn-sectiontitle">'.Renderer::e((string)$row['title']).'</h2><p>'.Renderer::e((string)$row['body']).'</p><p class="bn-helper">'.Renderer::e(PersianDateTime::fromIso((string)$row['created_at'],false)??'').'</p>';
            $id=$row['metadata']['request_id']??'';
            if(is_string($id)&&preg_match('/^[a-f0-9]{26}$/D',$id)){
                $roles=$this->identity['roles']??[];$workspace=in_array('applicant',$roles,true)?'applicant':(in_array('acceptor',$roles,true)?'acceptor':'loans');
                $html.='<a class="bn-btn bn-btn--secondary" href="/'.$workspace.'/requests/'.$id.'">مشاهده پرونده</a>';
            }
            if(empty($row['read_at']))$html.=$this->form('/notifications/'.$row['id'].'/read','خواندم');
            $html.='</article>';
        }
        if($data['items']===[])$html.='<section class="bn-section"><p>هنوز اعلانی ندارید.</p></section>';
        $this->page('اعلان‌های من',$html.$this->paging('/notifications',$page,(int)$data['total']));
    }
    private function deliveries(): void
    {
        $page=max(1,(int)($_GET['page']??1));$data=$this->load('/api/v1/admin/notifications/deliveries?page='.$page);if($data===null)return;
        $labels=['pending'=>'در صف','preparing'=>'آماده‌سازی','sending'=>'در حال ارسال','retry'=>'منتظر تلاش مجدد','accepted'=>'پذیرفته‌شده توسط پنل پیامک','uncertain'=>'نتیجه نامشخص','failed'=>'ناموفق','skipped'=>'ارسال تکراری حذف شد'];$rows='';
        foreach($data['items'] as $row){
            $action=in_array($row['status'],['retry','failed'],true)?$this->form('/notifications/'.$row['id'].'/retry','تلاش مجدد'):'';
            if($row['status']==='uncertain')$action='<a class="bn-btn bn-btn--secondary bn-btn--small" href="#reconcile-'.$row['id'].'">ثبت نتیجهٔ بررسی</a>';
            $rows.='<tr><td><a href="/loans/requests/'.Renderer::e($row['request_id']).'">مشاهده پرونده</a></td><td dir="ltr">'.Renderer::e($row['target_masked']).'</td><td>'.Renderer::e($labels[$row['status']]??$row['status']).'</td><td>'.Renderer::e((string)$row['attempts']).'</td><td>'.Renderer::e($row['provider_reference']??'—').'</td><td>'.Renderer::e($this->error($row['last_error']??'')).'</td><td>'.$action.'</td></tr>';
        }
        $html='<section class="bn-section"><p class="bn-helper">پذیرش پنل پیامک به معنی تحویل قطعی به گوشی نیست. ارسال با نتیجهٔ نامشخص، خودکار تکرار نمی‌شود و باید با سابقهٔ پنل پیامک تطبیق داده شود.</p><div class="bn-table-wrap"><table class="bn-table"><thead><tr><th>پرونده</th><th>گیرنده</th><th>وضعیت</th><th>تلاش</th><th>شناسه ارسال</th><th>توضیح</th><th>اقدام</th></tr></thead><tbody>'.($rows?:'<tr><td colspan="7">پیامکی در صف ثبت نشده است.</td></tr>').'</tbody></table></div></section>';
        foreach($data['items'] as $row)if($row['status']==='uncertain'){
            $html.='<details class="bn-section" id="reconcile-'.$row['id'].'"><summary>ثبت نتیجهٔ بررسی ارسال به '.Renderer::e($row['target_masked']).'</summary><p>ابتدا سابقهٔ همین ارسال را در پنل پیامک بررسی کنید. ثبت «پذیرفته نشده» به‌تنهایی پیامکی ارسال نمی‌کند.</p><form method="post" action="/notifications/'.$row['id'].'/reconcile" class="bn-form-grid"><input type="hidden" name="_csrf" value="'.Renderer::e(Csrf::token()).'"><label class="bn-field">نتیجهٔ بررسی<select name="status" required><option value="">انتخاب کنید</option><option value="accepted">پنل پیامک پذیرفته است</option><option value="failed">پنل پیامک نپذیرفته است</option></select></label><label class="bn-field">شناسهٔ ارسال (در صورت پذیرش)<input name="reference" maxlength="128"></label><label class="bn-field bn-field--full">توضیح بررسی<textarea name="note" minlength="6" maxlength="1000" required></textarea></label><label class="bn-check"><input type="checkbox" name="confirmed" required>سابقهٔ ارسال را در پنل پیامک بررسی کردم.</label><div class="bn-form-actions"><button class="bn-btn bn-btn--primary">ثبت نتیجهٔ بررسی</button></div></form></details>';
        }
        $this->page('وضعیت ارسال پیامک',$html.$this->paging('/notifications/deliveries',$page,(int)$data['total']));
    }
    private function settings(): void
    {
        $data=$this->load('/api/v1/admin/notifications/settings');if($data===null)return;
        $html='<section class="bn-section"><p>قالب و ترتیب متغیرها را عین قالب تأییدشدهٔ پنل پیامک وارد کنید. هر وضعیت تا زمان فعال‌سازی، پیامک ندارد.</p><p class="bn-helper">متغیرها: <span dir="ltr">[[request_id]] · [[status]] · [[full_name]] · [[national_code]] · [[mobile_number]] · [[loan_amount]] · [[installment_count]] · [[installment_amount]] · [[plan_name]] · [[acceptor_name]]</span></p><form method="post"><input type="hidden" name="_csrf" value="'.Renderer::e(Csrf::token()).'"><input type="hidden" name="revision" value="'.$data['revision'].'">';
        foreach(self::STATUSES as $key=>$title){
            $rule=$data['settings']['loan_statuses'][$key]??[];$prefix='statuses['.$key.']';
            $html.='<fieldset class="bn-section"><legend>'.$title.'</legend><div class="bn-form-grid">';
            foreach(['enabled'=>'فعال','send_to_applicant'=>'ارسال به متقاضی','send_to_acceptor'=>'ارسال به پذیرنده'] as $field=>$label){
                $selected=$rule[$field]??($field==='send_to_applicant');
                $html.='<label class="bn-check"><input type="checkbox" name="'.$prefix.'['.$field.']" value="1"'.($selected?' checked':'').'>'.$label.'</label>';
            }
            $html.='<label class="bn-field">شناسهٔ قالب<input inputmode="numeric" name="'.$prefix.'[pattern_id]" value="'.Renderer::e((string)($rule['pattern_id']??0)).'"></label>';
            for($i=0;$i<3;$i++)$html.='<label class="bn-field">متغیر '.($i+1).'<input dir="auto" name="'.$prefix.'[var_'.$i.']" maxlength="240" value="'.Renderer::e((string)($rule['variables'][$i]??'')).'"></label>';
            $html.='</div></fieldset>';
        }
        $html.='<div class="bn-form-actions"><button class="bn-btn bn-btn--primary">ذخیره تنظیمات</button></div></form></section>';
        $this->page('قالب‌ها و گیرندگان پیامک',$html);
    }
    private function form(string $action,string $label): string {return '<form method="post" action="'.$action.'" class="bn-inline-form"><input type="hidden" name="_csrf" value="'.Renderer::e(Csrf::token()).'"><button class="bn-btn bn-btn--secondary bn-btn--small">'.$label.'</button></form>';}
    private function paging(string $path,int $page,int $total): string {return '<div class="bn-actions">'.($page>1?'<a class="bn-btn bn-btn--secondary" href="'.$path.'?page='.($page-1).'">صفحه قبل</a>':'').($page*25<$total?'<a class="bn-btn bn-btn--secondary" href="'.$path.'?page='.($page+1).'">صفحه بعد</a>':'').'</div>';}
    private function error(string $code): string {return match($code){''=>'—','sms_not_configured'=>'اتصال پیامک تنظیم نشده','rate_limit_exceeded','request_temporarily_locked'=>'محدودیت موقت ارسال','recipient_lookup_unavailable'=>'اطلاعات گیرنده موقتاً در دسترس نیست','recipient_invalid'=>'شماره گیرنده معتبر نیست','sms_provider_rejected'=>'قالب یا ارسال توسط پنل پیامک رد شد','duplicate_recipient'=>'شمارهٔ متقاضی و پذیرنده یکسان است',default=>'نیازمند بررسی سابقهٔ ارسال'};}
}
