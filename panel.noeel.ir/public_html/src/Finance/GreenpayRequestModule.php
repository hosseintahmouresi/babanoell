<?php
declare(strict_types=1);
namespace Babanoell\Panel\Finance;
use Babanoell\Panel\Http\FinanceClient;
use Babanoell\Panel\Security\Csrf;
use Babanoell\Panel\View\Renderer;
use Babanoell\Panel\Support\PersianDateTime;

final class GreenpayRequestModule
{
    private const LABELS=['not_applicable'=>'غیرمشمول','not_started'=>'شروع نشده','otp_pending'=>'در حال درخواست رمز','awaiting_otp'=>'منتظر رمز مدیر','processing'=>'در حال ارسال','success'=>'موفق','failed'=>'ناموفق','unknown'=>'نتیجه نامشخص','cancelled'=>'لغو شده'];
    public function __construct(private readonly FinanceClient $client,private readonly string $bearer,private readonly array $identity){}
    public function handle(string $path,string $method):bool
    {
        if(!preg_match('#^/finance/greenpay/requests(?:/([a-f0-9]{26})(?:/(enrollment|send-otp|execute|reconcile))?)?$#',$path,$m))return false;
        header('Cache-Control: private,no-store');
        if(!array_intersect(['super_admin','admin'],$this->identity['roles']??[])){http_response_code(403);$this->page('دسترسی محدود','<p>مدیریت کسری GreenPay فقط برای مدیر سامانه مجاز است.</p>');return true;}
        $id=$m[1]??'';$action=$m[2]??'';
        if($method==='POST'){
            if(!Csrf::valid((string)($_POST['_csrf']??''))){http_response_code(403);$this->page('فرم منقضی شده','<p>صفحه را تازه‌سازی کنید.</p>');return true;}
            if($id===''||$action===''){http_response_code(405);return true;}
            $body=['row_version'=>(int)($_POST['row_version']??0)];
            if($action==='enrollment')$body+=['eligible'=>isset($_POST['eligible']),'from_pocket'=>$this->digits((string)($_POST['from_pocket']??''))];
            if($action==='execute')$body['otp']=$this->digits((string)($_POST['otp']??''));
            if($action==='reconcile')$body+=['decision'=>(string)($_POST['decision']??''),'reference'=>(string)($_POST['reference']??''),'note'=>(string)($_POST['note']??''),'confirmed'=>isset($_POST['confirmed'])];
            $r=$this->client->post('/api/v1/greenpay/requests/'.$id.'/'.$action,$this->bearer,$body);
            $_SESSION['request_debit_flash']=$r->ok()?'نتیجه عملیات ثبت شد.':(string)($r->json['error']['message']??'عملیات کامل نشد.');
            header('Location: /finance/greenpay/requests/'.$id,true,303);return true;
        }
        if($method!=='GET'){http_response_code(405);return true;}
        if($id==='')$this->listing();else $this->detail($id);return true;
    }
    private function listing():void
    {
        $page=max(1,(int)($_GET['page']??1));$r=$this->client->get('/api/v1/greenpay/requests?page='.$page,$this->bearer);if(!$r->ok()){$this->error($r);return;}
        $data=$r->json['data'];$rows='';foreach($data['debits']['items']??[] as $x)$rows.='<tr><td><a href="/finance/greenpay/requests/'.Renderer::e($x['request_id']).'">'.Renderer::e($x['request_no']).'</a></td><td>'.Renderer::e(self::LABELS[$x['status']]??$x['status']).'</td><td dir="ltr">'.Renderer::e($x['from_mask']).'</td><td dir="ltr">'.Renderer::e($x['to_mask']).'</td><td>'.Renderer::e(PersianDateTime::fromIso($x['updated_at'])??'—').'</td></tr>';
        $search='<a class="bn-btn bn-btn--primary" href="/loans/requests">انتخاب پرونده از فهرست تسهیلات</a><p class="bn-helper">داخل پروندهٔ موردنظر، گزینهٔ «کسری GreenPay» را انتخاب کنید.</p>';
        $html='<section class="bn-section"><p>فقط پرونده‌ای که صریحاً مشمول شده است وارد این جریان می‌شود. مبلغ از تسهیلات ثبت‌شدهٔ پرونده خوانده می‌شود و تغییر مبلغ در فرم ممکن نیست.</p>'.$search.'</section>';
        if(empty($data['enabled']))$html.='<p class="bn-alert" role="status">اجرای کسری پرونده در تنظیمات عملیاتی غیرفعال است.</p>';
        $html.='<section class="bn-section"><div class="bn-table-wrap"><table class="bn-table"><thead><tr><th>پرونده</th><th>وضعیت</th><th>کیف پول مبدأ</th><th>کیف پول مقصد</th><th>آخرین تغییر</th></tr></thead><tbody>'.($rows?:'<tr><td colspan="5">پرونده‌ای مشمول نشده است.</td></tr>').'</tbody></table></div></section>';
        $base='/finance/greenpay/requests?page=';$html.='<div class="bn-actions">'.($page>1?'<a class="bn-btn bn-btn--secondary" href="'.$base.($page-1).'">صفحه قبل</a>':'').($page*25<(int)$data['debits']['total']?'<a class="bn-btn bn-btn--secondary" href="'.$base.($page+1).'">صفحه بعد</a>':'').'</div>';
        $this->page('کسری GreenPay پرونده‌ها',$html);
    }
    private function detail(string $id):void
    {
        $r=$this->client->get('/api/v1/greenpay/requests/'.$id,$this->bearer);if(!$r->ok()){$this->error($r);return;}$x=$r->json['data']['debit'];$version=(int)$x['row_version'];$status=(string)$x['status'];
        $base='/finance/greenpay/requests/'.$id;$hidden='<input type="hidden" name="_csrf" value="'.Renderer::e(Csrf::token()).'"><input type="hidden" name="row_version" value="'.$version.'">';
        $html='<section class="bn-section"><p>وضعیت: <strong>'.Renderer::e(self::LABELS[$status]??$status).'</strong></p><a class="bn-btn bn-btn--secondary" href="/loans/requests/'.$id.'">مشاهده پرونده تسهیلات</a> <a class="bn-btn bn-btn--secondary" href="/finance/greenpay/requests">فهرست GreenPay</a></section>';
        if(!in_array($status,['success','processing','unknown','awaiting_otp','otp_pending'],true))$html.='<section class="bn-section"><h2 class="bn-sectiontitle">مشمول بودن پرونده</h2><form method="post" action="'.$base.'/enrollment">'.$hidden.'<label class="bn-check"><input type="checkbox" name="eligible" value="1"'.(!empty($x['eligible'])?' checked':'').'>این پرونده مشمول کسری GreenPay باشد.</label><label class="bn-field">شماره کیف پول مبدأ<input name="from_pocket" inputmode="numeric" autocomplete="off" maxlength="32"><span class="bn-helper">برای مشمول‌کردن اولیه الزامی است؛ در ویرایش می‌توانید برای حفظ کیف پول فعلی خالی بگذارید. مقصد از تنظیمات مجموعه خوانده می‌شود.</span></label><div class="bn-form-actions"><button class="bn-btn bn-btn--primary">ثبت مشمول بودن</button></div></form></section>';
        if(!empty($x['eligible'])&&in_array($status,['not_started','failed','cancelled','awaiting_otp','otp_pending'],true))$html.='<section class="bn-section"><form method="post" action="'.$base.'/send-otp">'.$hidden.'<p>شروع تازه فقط پس از تأیید مدارک انجام می‌شود. ارسال مجدد رمزِ تلاش جاری، مبلغ و کیف پول همان تلاش را حفظ می‌کند.</p><button class="bn-btn bn-btn--primary">'.($status==='awaiting_otp'?'ارسال مجدد رمز':'درخواست رمز GreenPay').'</button></form></section>';
        if($status==='awaiting_otp'){$a=$x['attempts'][0];$html.='<section class="bn-section"><h2 class="bn-sectiontitle">تأیید کسری</h2><p>مبلغ تثبیت‌شده: <strong>'.Renderer::e(number_format((float)$a['amount_rial'])).' ریال</strong>؛ مبدأ: <span dir="ltr">'.Renderer::e($x['from_mask']).'</span>؛ مقصد: <span dir="ltr">'.Renderer::e($x['to_mask']).'</span></p><form method="post" action="'.$base.'/execute">'.$hidden.'<label class="bn-field">رمز شش‌رقمی<input name="otp" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9۰-۹٠-٩]{6}" maxlength="6" required></label><div class="bn-form-actions"><button class="bn-btn bn-btn--primary">تأیید و ارسال کسری</button></div></form></section>';}
        if(in_array($status,['processing','unknown'],true)){$a=$x['attempts'][0];$html.='<section class="bn-section"><h2 class="bn-sectiontitle">بررسی نتیجهٔ نامشخص</h2><p>ابتدا همین مرجع را در سامانه ارائه‌دهنده بررسی کنید: <b dir="ltr">'.Renderer::e($a['external_reference']).'</b>. ثبت نتیجه، انتقال جدیدی ارسال نمی‌کند. ارسال در حال پردازش پس از دو دقیقه قابل بررسی است.</p><form method="post" action="'.$base.'/reconcile">'.$hidden.'<label class="bn-field">نتیجه بررسی<select name="decision" required><option value="">انتخاب کنید</option><option value="paid">کسری انجام شده است</option><option value="unpaid">کسری انجام نشده است</option></select></label><label class="bn-field">مرجع همین تلاش<input name="reference" required></label><label class="bn-field">شرح بررسی<textarea name="note" minlength="10" maxlength="2000" required></textarea></label><label class="bn-check"><input type="checkbox" name="confirmed" required>نتیجهٔ همین تلاش را در سامانه ارائه‌دهنده بررسی کردم.</label><div class="bn-form-actions"><button class="bn-btn bn-btn--primary">ثبت نتیجهٔ بررسی</button></div></form></section>';}
        $rows='';foreach($x['attempts'] as $a)$rows.='<tr><td>'.(int)$a['sequence_no'].'</td><td>'.Renderer::e(number_format((float)$a['amount_rial'])).'</td><td>'.Renderer::e(self::LABELS[$a['status']]??$a['status']).'</td><td dir="ltr">'.Renderer::e($a['external_reference']).'</td><td>'.Renderer::e($a['provider_reference']??'—').'</td><td>'.Renderer::e($a['review_note']??'—').'</td></tr>';
        $html.='<section class="bn-section"><h2 class="bn-sectiontitle">سابقهٔ تلاش‌ها</h2><div class="bn-table-wrap"><table class="bn-table"><thead><tr><th>تلاش</th><th>مبلغ (ریال)</th><th>وضعیت</th><th>مرجع عملیات</th><th>مرجع ارائه‌دهنده</th><th>بررسی مدیر</th></tr></thead><tbody>'.($rows?:'<tr><td colspan="6">هنوز درخواستی برای رمز یا کسری ثبت نشده است.</td></tr>').'</tbody></table></div></section>';
        $this->page('GreenPay پرونده '.(string)($x['request_no']??''),$html);
    }
    private function page(string $title,string $html):void
    {
        $flash=$_SESSION['request_debit_flash']??null;unset($_SESSION['request_debit_flash']);echo Renderer::page($title,'<div class="bn-pagehead"><h1 class="bn-title">'.Renderer::e($title).'</h1></div>'.($flash?'<p class="bn-alert" role="status">'.Renderer::e((string)$flash).'</p>':'').$html,$this->identity,'finance');
    }
    private function error(object $r):void{http_response_code($r->status>=400?$r->status:503);$this->page('GreenPay','<p class="bn-alert bn-alert--error">'.Renderer::e((string)($r->json['error']['message']??'دریافت اطلاعات کامل نشد.')).'</p>');}
    private function digits(string $s):string{return strtr(trim($s),['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9']);}
}
