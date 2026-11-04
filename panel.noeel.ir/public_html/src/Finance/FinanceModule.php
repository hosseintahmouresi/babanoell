<?php
declare(strict_types=1);
namespace Babanoell\Panel\Finance;

use Babanoell\Panel\Http\FinanceClient;
use Babanoell\Panel\Http\HttpResponse;
use Babanoell\Panel\Security\Csrf;
use Babanoell\Panel\Support\PersianDateTime;
use Babanoell\Panel\View\Renderer;

final class FinanceModule
{
    public function __construct(private readonly FinanceClient $client, private readonly string $bearer, private readonly array $identity) {}

    public function handle(string $path,string $method): bool
    {
        if(str_starts_with($path,'/finance/bank-cycles')) {
            return (new BankCyclesModule($this->client,$this->bearer,$this->identity))->handle($path,$method);
        }
        if(str_starts_with($path,'/finance/bank-cycles'))return (new BankCyclesModule($this->client,$this->bearer,$this->identity))->handle($path,$method);
        if(!$this->canAny()) return false;
        if($path==='/finance'&&$method==='GET'){ $this->overview(); return true; }
        if($path==='/finance/import-loans-shadow'&&$method==='POST'){ $this->importShadow(); return true; }
        if($path==='/finance/greenpay'&&$method==='GET'){ $this->greenpay();return true; }
        if($path==='/finance/greenpay'&&$method==='POST'){ $this->greenpayPrepare();return true; }
        if(preg_match('#^/finance/greenpay/([a-f0-9]{26})/(approve|send-otp|execute|reconcile)$#',$path,$m)&&$method==='POST'){$this->greenpayAction($m[1],$m[2]);return true;}

        if($path==='/finance/cycles'&&$method==='GET'){ $this->cycles(); return true; }
        if($path==='/finance/cycles/export'&&$method==='GET'){ $this->exportCycles(); return true; }
        if($path==='/finance/receipts'&&$method==='GET'){ $this->receipts();return true; }
        if(preg_match('#^/finance/receipts/([a-f0-9]{26})/review$#',$path,$m)&&$method==='POST'){ $this->reviewReceipt($m[1]);return true; }
        if($path==='/finance/reconciliation/scan'&&$method==='POST'){ $this->scanReconciliation();return true; }

        if($path==='/finance/cycles'&&$method==='POST'){ $this->createCycle(); return true; }
        if(preg_match('#^/finance/cycles/([a-f0-9]{26})$#',$path,$m)&&$method==='GET'){ $this->cycle($m[1]); return true; }
        if(preg_match('#^/finance/cycles/([a-f0-9]{26})/items$#',$path,$m)&&$method==='POST'){ $this->addCycleItem($m[1]); return true; }
        if(preg_match('#^/finance/cycles/([a-f0-9]{26})/items/([a-f0-9]{26})/remove$#',$path,$m)&&$method==='POST'){$this->removeCycleItem($m[1],$m[2]);return true;}
        if(preg_match('#^/finance/cycles/([a-f0-9]{26})/close$#',$path,$m)&&$method==='POST'){ $this->cycleAction($m[1],'close'); return true; }
        if(preg_match('#^/finance/cycles/([a-f0-9]{26})/reopen$#',$path,$m)&&$method==='POST'){ $this->cycleAction($m[1],'reopen'); return true; }
        if($path==='/finance/reconciliation'&&$method==='GET'){ $this->reconciliation(); return true; }
        if(preg_match('#^/finance/reconciliation/([a-f0-9]{26})/resolve$#',$path,$m)&&$method==='POST'){ $this->resolveReconciliation($m[1]); return true; }
        if(preg_match('#^/finance/requests/([a-f0-9]{26})/resync-merchant-settlement$#',$path,$m)&&$method==='POST'){ $this->resyncMerchantSettlement($m[1]);return true;}
        if(preg_match('#^/finance/requests/([a-f0-9]{26})/reset-merchant-settlement$#',$path,$m)&&$method==='POST'){ $this->resetMerchantSettlement($m[1]);return true;}
        if($path==='/finance/request'&&$method==='GET'){ $id=trim((string)($_GET['id']??'')); if($id===''){ $this->redirect('/finance'); } $this->requestSnapshot($id); return true; }
        return false;
    }

    private function overview(): void
    {
        if(!$this->can('finance.dashboard.read')){$this->forbidden();return;}
        $r=$this->client->get('/api/v1/stats',$this->bearer);
        if(!$r->ok()){$this->errorPage('مدیریت مالی',$r);return;}
        $s=is_array($r->json['data']['stats']??null)?$r->json['data']['stats']:[];
        $actions='<a class="bn-btn bn-btn--secondary" href="/finance/bank-cycles">سیکل‌های تسویه بانک</a><a class="bn-btn bn-btn--secondary" href="/finance/cycles">سیکل‌های تسویه</a>';
        if($this->can('finance.reconciliation.read'))$actions.='<a class="bn-btn bn-btn--secondary" href="/finance/reconciliation">مغایرت‌ها</a>';
        if($this->isAdmin())$actions.='<a class="bn-btn bn-btn--secondary" href="/finance/greenpay">کنترل پرداخت‌های GreenPay</a>';
        $content=$this->head('مرکز مالی','تسویه بانک، تسویه پذیرنده، سیکل‌ها، رسیدها و کنترل مغایرت‌ها.',$actions).$this->takeFlash()
            .'<div class="bn-stats">'.$this->stat('تسویه بانک ثبت‌شده',$s['bank_recorded']??0,'Bank → Babanoell').$this->stat('تسویه پذیرنده در انتظار',$s['merchant_pending']??0,'نیازمند اقدام').$this->stat('سیکل باز',$s['open_cycles']??0,'قابل تکمیل').$this->stat('رسید در انتظار',$s['pending_receipts']??0,'نیازمند بازبینی').$this->stat('مغایرت باز',$s['open_discrepancies']??0,'نیازمند بررسی',true).'</div>'
            .'<section class="bn-section"><div class="bn-sectionhead"><span class="bn-step">۱</span><div><h2 class="bn-sectiontitle">دسترسی سریع</h2><p class="bn-sectiondesc">عملیات مالی از Finance Service انجام می‌شود.</p></div></div><div class="bn-action-grid">'
            .$this->card('/finance/bank-cycles','سیکل‌های تسویه بانک','ثبت تجمیعی بانک با نوئل؛ مستقل از تسویه پذیرندگان.').$this->card('/finance/cycles','سیکل‌های تسویه','ایجاد، مشاهده، بستن و بازگشایی کنترل‌شده سیکل‌های پذیرنده.').($this->can('finance.receipts.manage')?$this->card('/finance/receipts','رسیدهای مالی','بررسی و تأیید یا رد رسید با ثبت تاریخچه.'):'')
            .($this->can('finance.reconciliation.read')?$this->card('/finance/reconciliation','مغایرت‌های مالی','مشاهده و رفع کنترل‌شده مغایرت‌های ثبت‌شده.'):'')
            .'</div></section>'
            .'<section class="bn-section"><div class="bn-sectionhead"><span class="bn-step">۲</span><div><h2 class="bn-sectiontitle">پرونده مالی</h2><p class="bn-sectiondesc">شناسه پرونده را برای مشاهده Snapshot مالی وارد کنید.</p></div></div><form class="bn-form-grid" method="get" action="/finance/request"><div class="bn-field"><label class="bn-label">شناسه پرونده</label><input name="id" required pattern="[a-f0-9]{26}" placeholder="26-character request id"></div><div class="bn-form-actions"><button class="bn-btn bn-btn--primary">مشاهده</button></div></form></section>';
        if($this->isAdmin()&&$this->can('finance.migration.manage'))$content.='<section class="bn-section"><div class="bn-sectionhead"><span class="bn-step">۳</span><div><h2 class="bn-sectiontitle">انتقال داده مالی قبلی</h2><p class="bn-sectiondesc">این عملیات idempotent است و داده‌های shadow داخل Loans را به Finance همگام می‌کند.</p></div></div><form method="post" action="/finance/import-loans-shadow"><input type="hidden" name="_csrf" value="'.Renderer::e(Csrf::token()).'"><button class="bn-btn bn-btn--secondary" type="submit">همگام‌سازی مالی Loans</button></form></section>';
        echo Renderer::page('مدیریت مالی',$content,$this->identity,'finance');
    }

    private function cycles(): void
    {
        if(!$this->can('finance.cycles.read')){$this->forbidden();return;}
        $filters=['page'=>max(1,(int)($_GET['page']??1)),'per_page'=>25,'status'=>trim((string)($_GET['status']??'')),'merchant_id'=>trim((string)($_GET['merchant_id']??''))];
        $r=$this->client->get('/api/v1/cycles',$this->bearer,$filters);if(!$r->ok()){$this->errorPage('سیکل‌های تسویه',$r);return;}
        $d=is_array($r->json['data']??null)?$r->json['data']:[];$items=is_array($d['items']??null)?$d['items']:[];$rows='';
        foreach($items as$x){if(!is_array($x))continue;$id=(string)($x['id']??'');$rows.='<tr><td><a class="bn-table-link" href="/finance/cycles/'.Renderer::e($id).'"><strong>'.Renderer::e((string)($x['cycle_no']??$id)).'</strong></a></td><td>'.Renderer::e((string)($x['merchant_id']??'—')).'</td><td>'.$this->badge((string)($x['status']??''),(string)($x['status']??'—')).'</td><td>'.Renderer::e(PersianDateTime::digits(number_format((float)($x['total_amount']??0)))).'</td><td>'.Renderer::e((string)($x['settlement_date']??'—')).'</td></tr>';}
        if($rows==='')$rows='<tr><td colspan="5">سیکل تسویه‌ای وجود ندارد.</td></tr>';
        $content=$this->head('سیکل‌های تسویه','تسویه‌های پذیرنده به‌صورت تجمیعی و transaction-safe مدیریت می‌شوند.','<a class="bn-btn bn-btn--secondary" href="/finance">بازگشت</a><a class="bn-btn bn-btn--secondary" href="/finance/cycles/export?'.http_build_query(['status'=>$filters['status'],'merchant_id'=>$filters['merchant_id']]).'">خروجی CSV</a>').$this->takeFlash()
            .'<section class="bn-section"><form class="bn-filterbar" method="get"><div class="bn-field"><label class="bn-label">وضعیت</label><select name="status"><option value="">همه</option><option value="open"'.($filters['status']==='open'?' selected':'').'>باز</option><option value="closed"'.($filters['status']==='closed'?' selected':'').'>بسته</option></select></div><div class="bn-field"><label class="bn-label">شناسه پذیرنده</label><input name="merchant_id" value="'.Renderer::e((string)$filters['merchant_id']).'"></div><div class="bn-filter-actions"><button class="bn-btn bn-btn--primary">اعمال</button></div></form></section>'
            .($this->can('finance.cycles.manage')?'<section class="bn-section"><h2 class="bn-sectiontitle">ایجاد سیکل</h2><form class="bn-form-grid" method="post" action="/finance/cycles"><input type="hidden" name="_csrf" value="'.Renderer::e(Csrf::token()).'"><div class="bn-field"><label class="bn-label">شناسه پذیرنده</label><input name="merchant_id" required></div><div class="bn-field"><label class="bn-label">تاریخ تسویه</label><input name="settlement_date" type="date"></div><div class="bn-field bn-field--full"><label class="bn-label">توضیحات</label><textarea name="description"></textarea></div><div class="bn-form-actions"><button class="bn-btn bn-btn--primary">ایجاد سیکل</button></div></form></section>':'')
            .'<section class="bn-section"><div class="bn-table-wrap"><table class="bn-table"><thead><tr><th>سیکل</th><th>پذیرنده</th><th>وضعیت</th><th>مجموع</th><th>تاریخ</th></tr></thead><tbody>'.$rows.'</tbody></table></div></section>';
        echo Renderer::page('سیکل‌های تسویه',$content,$this->identity,'finance');
    }

    private function createCycle(): void
    {
        if(!$this->can('finance.cycles.manage')){$this->forbidden();return;}if(!$this->csrf('/finance/cycles'))return;
        $r=$this->client->post('/api/v1/cycles',$this->bearer,['merchant_id'=>trim((string)($_POST['merchant_id']??'')),'settlement_date'=>trim((string)($_POST['settlement_date']??'')),'description'=>trim((string)($_POST['description']??''))],bin2hex(random_bytes(16)));
        $this->flash($r->ok()?'success':'error',$r->ok()?'سیکل تسویه ایجاد شد.':$this->apiMessage($r));$id=(string)($r->json['data']['cycle']['id']??'');$this->redirect($r->ok()&&$id!==''?'/finance/cycles/'.$id:'/finance/cycles');
    }

    private function cycle(string$id): void
    {
        if(!$this->can('finance.cycles.read')){$this->forbidden();return;}$r=$this->client->get('/api/v1/cycles/'.$id,$this->bearer);if(!$r->ok()){$this->errorPage('سیکل تسویه',$r);return;}$c=is_array($r->json['data']['cycle']??null)?$r->json['data']['cycle']:[];$items=is_array($c['items']??null)?$c['items']:[];$rows='';foreach($items as$x){if(!is_array($x))continue;$rows.='<tr><td>'.Renderer::e((string)($x['request_id']??'')).($this->isAdmin()&&($c['status']??'')==='open'?'<form method="post" action="/finance/cycles/'.Renderer::e($id).'/items/'.Renderer::e((string)($x['request_id']??'')).'/remove"><input type="hidden" name="_csrf" value="'.Renderer::e(Csrf::token()).'"><input name="reason" minlength="10" maxlength="1000" required placeholder="دلیل حذف (حداقل ۱۰ کاراکتر)"><button class="bn-btn bn-btn--secondary" type="submit">حذف کنترل‌شده</button></form>':'').'</td><td>'.Renderer::e(PersianDateTime::digits(number_format((float)($x['settlement_amount']??0)))).'</td><td>'.Renderer::e(PersianDateTime::digits(number_format((float)($x['leasing_amount']??0)))).'</td><td>'.Renderer::e(PersianDateTime::digits(number_format((float)($x['merchant_amount']??0)))).'</td></tr>';}$rows=$rows?:'<tr><td colspan="4">هنوز پرونده‌ای در این سیکل ثبت نشده است.</td></tr>';
        $status=(string)($c['status']??'open');$check=$this->client->get('/api/v1/cycles/'.$id.'/integrity',$this->bearer);$issues=(array)($check->json['data']['integrity']['issues']??[]);$content=$this->head('سیکل '.(string)($c['cycle_no']??''),'پذیرنده: '.(string)($c['merchant_id']??'—'),'<a class="bn-btn bn-btn--secondary" href="/finance/cycles">بازگشت</a>').$this->takeFlash().'<div class="bn-stats">'.$this->stat('وضعیت',$status,'').$this->stat('مجموع',$c['total_amount']??0,'').$this->stat('سهم لیزینگ',$c['leasing_total']??0,'').$this->stat('سهم پذیرنده',$c['merchant_total']??0,'').'</div><section class="bn-section"><div class="bn-table-wrap"><table class="bn-table"><thead><tr><th>پرونده</th><th>تسویه</th><th>لیزینگ</th><th>پذیرنده</th></tr></thead><tbody>'.$rows.'</tbody></table></div></section>';
        if($check->ok()&&count($issues)>0)$content.=$this->alert('error','مغایرت ساختار سیکل',count($issues).' مورد اختلاف شناسایی شد؛ پیش از بستن سیکل بررسی کنید.');
        if($this->can('finance.cycles.manage')&&$status==='open')$content.='<section class="bn-section"><h2 class="bn-sectiontitle">افزودن پرونده</h2><form class="bn-form-grid" method="post" action="/finance/cycles/'.Renderer::e($id).'/items"><input type="hidden" name="_csrf" value="'.Renderer::e(Csrf::token()).'"><div class="bn-field"><label class="bn-label">شناسه پرونده</label><input name="request_id" required></div><div class="bn-field"><label class="bn-label">مبلغ تسویه</label><input name="settlement_amount" type="number" min="0" required></div><div class="bn-field"><label class="bn-label">مبلغ لیزینگ</label><input name="leasing_amount" type="number" min="0" required></div><div class="bn-field"><label class="bn-label">مبلغ پذیرنده</label><input name="merchant_amount" type="number" min="0" required></div><div class="bn-form-actions"><button class="bn-btn bn-btn--primary">افزودن</button></div></form><form method="post" action="/finance/cycles/'.Renderer::e($id).'/close"><input type="hidden" name="_csrf" value="'.Renderer::e(Csrf::token()).'"><button class="bn-btn bn-btn--secondary" type="submit">بستن سیکل</button></form></section>';
        if($status==='closed'&&($this->isAdmin()||$this->can('finance.reconciliation.manage')))$content.='<section class="bn-section"><h2 class="bn-sectiontitle">بازگشایی کنترل‌شده</h2><form method="post" action="/finance/cycles/'.Renderer::e($id).'/reopen"><input type="hidden" name="_csrf" value="'.Renderer::e(Csrf::token()).'"><div class="bn-field"><label class="bn-label">دلیل</label><textarea name="reason" required></textarea></div><button class="bn-btn bn-btn--secondary">بازگشایی</button></form></section>';
        echo Renderer::page('سیکل تسویه',$content,$this->identity,'finance');
    }

    private function addCycleItem(string$id): void
    {if(!$this->can('finance.cycles.manage')){$this->forbidden();return;}if(!$this->csrf('/finance/cycles/'.$id))return;$payload=['request_id'=>trim((string)($_POST['request_id']??'')),'settlement_amount'=>trim((string)($_POST['settlement_amount']??'')),'leasing_amount'=>trim((string)($_POST['leasing_amount']??'')),'merchant_amount'=>trim((string)($_POST['merchant_amount']??''))];$r=$this->client->post('/api/v1/cycles/'.$id.'/items',$this->bearer,$payload,bin2hex(random_bytes(16)));$this->flash($r->ok()?'success':'error',$r->ok()?'پرونده به سیکل اضافه شد.':$this->apiMessage($r));$this->redirect('/finance/cycles/'.$id);}
    private function cycleAction(string$id,string$action): void
    {if(!$this->can('finance.cycles.manage')&&$action==='close'){$this->forbidden();return;}if(!$this->csrf('/finance/cycles/'.$id))return;$payload=$action==='reopen'?['reason'=>trim((string)($_POST['reason']??''))]:[];$r=$this->client->post('/api/v1/cycles/'.$id.'/'.$action,$this->bearer,$payload);$this->flash($r->ok()?'success':'error',$r->ok()?($action==='close'?'سیکل بسته شد.':'سیکل بازگشایی شد.'):$this->apiMessage($r));$this->redirect('/finance/cycles/'.$id);}

    private function reconciliation(): void
    {if(!$this->can('finance.reconciliation.read')){$this->forbidden();return;}$status=trim((string)($_GET['status']??'open'))?:'open';$r=$this->client->get('/api/v1/reconciliation',$this->bearer,['status'=>$status]);if(!$r->ok()){$this->errorPage('مغایرت‌های مالی',$r);return;}$items=is_array($r->json['data']['items']??null)?$r->json['data']['items']:[];$rows='';foreach($items as$x){if(!is_array($x))continue;$id=(string)($x['id']??'');$action=$this->can('finance.reconciliation.manage')&&$status==='open'?'<form method="post" action="/finance/reconciliation/'.Renderer::e($id).'/resolve"><input type="hidden" name="_csrf" value="'.Renderer::e(Csrf::token()).'"><input name="note" required placeholder="توضیح رفع مغایرت"><button class="bn-btn bn-btn--small bn-btn--primary">رفع</button></form>':'—';$rows.='<tr><td>'.Renderer::e((string)($x['discrepancy_type']??'—')).'</td><td>'.Renderer::e((string)($x['request_id']??$x['aggregate_id']??'—')).'</td><td>'.Renderer::e((string)($x['expected_json']??'—').' / '.(string)($x['actual_json']??'—')).'</td><td>'.Renderer::e((string)($x['status']??'')).'</td><td>'.$action.'</td></tr>';}$rows=$rows?:'<tr><td colspan="5">مغایرتی در این وضعیت ثبت نشده است.</td></tr>';$content=$this->head('مغایرت‌های مالی','رفع مغایرت فقط با ثبت توضیح و Audit انجام می‌شود.','<a class="bn-btn bn-btn--secondary" href="/finance">بازگشت</a>').$this->takeFlash().'<section class="bn-section"><div class="bn-table-wrap"><table class="bn-table"><thead><tr><th>نوع</th><th>مرجع</th><th>شرح</th><th>وضعیت</th><th>اقدام</th></tr></thead><tbody>'.$rows.'</tbody></table></div></section>'.($this->isAdmin()?'<section class="bn-section"><h2 class="bn-sectiontitle">کنترل مغایرت سیکل‌های بسته</h2><p>اسکن فقط مغایرت‌های جدید را ثبت می‌کند و هیچ پرداختی انجام نمی‌دهد.</p><form method="post" action="/finance/reconciliation/scan"><input type="hidden" name="_csrf" value="'.Renderer::e(Csrf::token()).'"><label><input type="checkbox" name="confirm" value="1" required> اجرای اسکن کنترل مالی را تأیید می‌کنم</label><button class="bn-btn bn-btn--secondary">اسکن ۱۰۰ سیکل اخیر</button></form></section>':'');echo Renderer::page('مغایرت‌های مالی',$content,$this->identity,'finance');}
    private function resolveReconciliation(string$id): void
    {if(!$this->can('finance.reconciliation.manage')){$this->forbidden();return;}if(!$this->csrf('/finance/reconciliation'))return;$r=$this->client->post('/api/v1/reconciliation/'.$id.'/resolve',$this->bearer,['note'=>trim((string)($_POST['note']??''))]);$this->flash($r->ok()?'success':'error',$r->ok()?'مغایرت رفع شد.':$this->apiMessage($r));$this->redirect('/finance/reconciliation');}
    private function requestSnapshot(string$id): void
    {if(!$this->can('finance.dashboard.read')){$this->forbidden();return;}$r=$this->client->get('/api/v1/requests/'.$id.'/snapshot',$this->bearer);if(!$r->ok()){$this->errorPage('پرونده مالی',$r);return;}$d=is_array($r->json['data']??null)?$r->json['data']:[];$req=is_array($d['request']??null)?$d['request']:[];$f=is_array($d['finance']??null)?$d['finance']:[];$content=$this->head('پرونده مالی '.(string)($req['request_no']??$id),'نمای یکپارچه وضعیت مالی پرونده.','<a class="bn-btn bn-btn--secondary" href="/finance">بازگشت</a>').$this->takeFlash().'<div class="bn-detail-grid">'.$this->detail('وضعیت پرونده',(string)($req['status']??'—')).$this->detail('پذیرنده',(string)($req['merchant_id']??'—')).$this->detail('تسویه بانک',(string)($f['bank_settlement']['status']??'—')).$this->detail('تسویه پذیرنده',(string)($f['merchant_settlement']['status']??'—')).$this->detail('پرداخت تأمین‌کننده',(string)($f['financier_payment']['status']??'—')).'</div>';
      if($this->isAdmin()&&(string)($req['status']??'')==='granted'){
       $merchant=(array)($f['merchant_settlement']??[]);
       $content.='<section class="bn-section"><h2 class="bn-sectiontitle">بازنشانی کنترل‌شده تاریخ تسویه پذیرنده</h2><p>اگر پرونده در سیکل تسویه باشد، سیستم عملیات را رد می‌کند. تسویه بانک تغییری نخواهد کرد.</p><form method="post" action="/finance/requests/'.Renderer::e($id).'/reset-merchant-settlement"><input type="hidden" name="_csrf" value="'.Renderer::e(Csrf::token()).'"><input type="hidden" name="row_version" value="'.(int)($merchant['row_version']??0).'"><div class="bn-field"><label class="bn-label">دلیل اصلاح</label><textarea name="reason" required minlength="10" maxlength="1000"></textarea></div><label><input type="checkbox" name="confirm" value="1" required> بازنشانی تاریخ و وضعیت تسویه پذیرنده را تأیید می‌کنم.</label><div class="bn-form-actions"><button type="submit" class="bn-btn bn-btn--secondary">تنظیم به «در انتظار تعیین تاریخ»</button></div></form></section>';
      }
      if($this->isAdmin()&&(string)($f['merchant_settlement']['status']??'')==='awaiting_date'&&empty($f['merchant_settlement']['settlement_date'])){
       $content.='<section class="bn-section"><h2 class="bn-sectiontitle">بازیابی همگام‌سازی اطلاعات تسویه</h2><p>اگر وضعیت Loans با Finance متفاوت نمایش داده می‌شود، با تأیید مدیر همگام‌سازی را دوباره انجام دهید. تغییری در تسویه بانک یا اطلاعات سیکل ایجاد نمی‌شود.</p><form method="post" action="/finance/requests/'.Renderer::e($id).'/resync-merchant-settlement"><input type="hidden" name="_csrf" value="'.Renderer::e(Csrf::token()).'"><div class="bn-field"><label class="bn-label">دلیل بازهمگام‌سازی</label><textarea name="reason" minlength="10" maxlength="1000" required></textarea></div><label><input type="checkbox" name="confirm" value="1" required> همگام‌سازی مجدد را تأیید می‌کنم.</label><button type="submit" class="bn-btn bn-btn--secondary">بازهمگام‌سازی با Loans</button></form></section>';
      }
      echo Renderer::page('پرونده مالی',$content,$this->identity,'finance');}
    private function importShadow(): void
    {if(!$this->isAdmin()||!$this->can('finance.migration.manage')){$this->forbidden();return;}if(!$this->csrf('/finance'))return;$r=$this->client->post('/api/v1/import/loans-shadow',$this->bearer,[]);$import=is_array($r->json['data']['import']??null)?$r->json['data']['import']:[];$this->flash($r->ok()?'success':'error',$r->ok()?'همگام‌سازی داده‌های مالی قدیمی کامل شد. '.Renderer::e(json_encode($import,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)):$this->apiMessage($r));$this->redirect('/finance');}

    private function removeCycleItem(string $cycle,string $request):void
    {
        if(!$this->isAdmin()){$this->forbidden();return;}
        if(!$this->csrf('/finance/cycles/'.$cycle))return;
        $r=$this->client->post('/api/v1/cycles/'.$cycle.'/items/'.$request.'/remove',$this->bearer,['reason'=>trim((string)($_POST['reason']??''))]);
        $this->flash($r->ok()?'success':'error',$r->ok()?'پرونده به‌صورت کنترل‌شده از سیکل حذف شد.':$this->apiMessage($r));
        $this->redirect('/finance/cycles/'.$cycle);
    }
    private function scanReconciliation():void
    {
        if(!$this->isAdmin()){$this->forbidden();return;}if(!$this->csrf('/finance/reconciliation'))return;
        $r=$this->client->post('/api/v1/reconciliation/scan',$this->bearer,['confirm'=>isset($_POST['confirm']),'limit'=>100]);
        $result=$r->json['data']['result']??[];
        $this->flash($r->ok()?'success':'error',$r->ok()?'اسکن '.(int)($result['scanned']??0).' سیکل انجام شد؛ '.(int)($result['new']??0).' مغایرت جدید ثبت شد.':$this->apiMessage($r));
        $this->redirect('/finance/reconciliation');
    }
    private function receipts():void
    {
        if(!$this->can('finance.receipts.manage')){$this->forbidden();return;}
        $status=(string)($_GET['status']??'pending');if(!in_array($status,['pending','approved','rejected'],true))$status='pending';
        $r=$this->client->get('/api/v1/receipts',$this->bearer,['status'=>$status,'page'=>max(1,(int)($_GET['page']??1)),'per_page'=>50]);
        if(!$r->ok()){$this->errorPage('رسیدهای مالی',$r);return;}
        $data=$r->json['data']['receipts']??[];$rows='';
        foreach((array)($data['items']??[])as$x){
            $id=Renderer::e((string)($x['id']??''));$action='—';
            if($status==='pending'){
                $action='<form method="post" action="/finance/receipts/'.$id.'/review"><input type="hidden" name="_csrf" value="'.Renderer::e(Csrf::token()).'"><textarea name="note" maxlength="1000" placeholder="دلیل رد یا توضیح تأیید"></textarea><button name="status" value="approved" class="bn-btn bn-btn--primary">تأیید</button><button name="status" value="rejected" class="bn-btn bn-btn--secondary">رد</button></form>';
            }
            $rows.='<tr><td>'.$id.'</td><td>'.Renderer::e((string)($x['request_id']??'—')).'</td><td>'.Renderer::e((string)($x['cycle_id']??'—')).'</td><td>'.Renderer::e((string)($x['amount']??'—')).'</td><td>'.Renderer::e((string)($x['status']??'—')).'</td><td>'.$action.'</td></tr>';
        }
        if($rows==='')$rows='<tr><td colspan="6">رسیدی در این وضعیت وجود ندارد.</td></tr>';
        $content=$this->head('مدیریت رسیدهای مالی','تأیید یا رد رسید فقط توسط فردی غیر از ثبت‌کننده انجام می‌شود.','<a class="bn-btn bn-btn--secondary" href="/finance">بازگشت</a>')
        .$this->takeFlash().'<section class="bn-section"><div class="bn-actions"><a href="/finance/receipts?status=pending">در انتظار</a> | <a href="/finance/receipts?status=approved">تأییدشده</a> | <a href="/finance/receipts?status=rejected">ردشده</a></div><div class="bn-table-wrap"><table class="bn-table"><thead><tr><th>رسید</th><th>پرونده</th><th>سیکل</th><th>مبلغ</th><th>وضعیت</th><th>اقدام</th></tr></thead><tbody>'.$rows.'</tbody></table></div></section>';
        echo Renderer::page('مدیریت رسیدهای مالی',$content,$this->identity,'finance');
    }
    private function reviewReceipt(string$id):void
    {
        if(!$this->can('finance.receipts.manage')){$this->forbidden();return;}if(!$this->csrf('/finance/receipts'))return;
        $status=trim((string)($_POST['status']??''));$note=trim((string)($_POST['note']??''));
        $r=$this->client->post('/api/v1/receipts/'.$id.'/review',$this->bearer,['status'=>$status,'note'=>$note]);
        $this->flash($r->ok()?'success':'error',$r->ok()?'رسید بررسی شد.':$this->apiMessage($r));$this->redirect('/finance/receipts');
    }
    private function exportCycles():void
    {
        if(!$this->can('finance.cycles.read')){$this->forbidden();return;}
        $filters=['status'=>trim((string)($_GET['status']??'')),'merchant_id'=>trim((string)($_GET['merchant_id']??''))];
        $records=[];$total=null;
        for($page=1;$page<=10;$page++){
            $response=$this->client->get('/api/v1/reports/cycles',$this->bearer,$filters+['page'=>$page,'per_page'=>100]);
            if(!$response->ok()){$this->errorPage('خروجی مالی',$response);return;}
            $data=$response->json['data']['report']??[];$total=(int)($data['total']??0);
            if($total>1000){http_response_code(422);echo Renderer::page('خروجی مالی',$this->head('خروجی بزرگ','بیش از ۱۰۰۰ سیکل با این فیلتر یافت شد. برای خروجی دقیق، فیلتر را محدودتر کنید.'),$this->identity,'finance');return;}
            foreach((array)($data['items']??[])as$item)$records[]=$item;
            if(count($records)>=$total)break;
        }
        $escape=static fn(mixed $value):string=>\Babanoell\Panel\Bank\SpreadsheetSafeCell::escape($value);
        $stream=fopen('php://temp','w+');if($stream===false){http_response_code(500);return;}
        fwrite($stream,"\xEF\xBB\xBF");
        fputcsv($stream,['شناسه سیکل','شماره سیکل','شناسه پذیرنده','وضعیت','تاریخ تسویه','تعداد پرونده','جمع تسویه','سهم لیزینگ','سهم پذیرنده','رسید در انتظار','رسید تأییدشده'],',','"','');
        foreach($records as$x){$rr=(array)($x['receipt_summary']??[]);fputcsv($stream,array_map($escape,[(string)($x['id']??''),(string)($x['cycle_no']??''),(string)($x['merchant_id']??''),(string)($x['status']??''),(string)($x['settlement_date']??''),(string)($x['item_count']??0),(string)($x['total_amount']??0),(string)($x['leasing_total']??0),(string)($x['merchant_total']??0),(string)($rr['pending']??0),(string)($rr['approved']??0)]),',','"','');}
        rewind($stream);header('Content-Type: text/csv; charset=utf-8');header('Content-Disposition: attachment; filename="noel-finance-cycles-'.gmdate('Ymd-His').'.csv"');header('Cache-Control: no-store');fpassthru($stream);fclose($stream);
    }

    private function greenpay():void
    {
        if(!$this->isAdmin()){$this->forbidden();return;}
        $status=trim((string)($_GET['status']??''));
        $r=$this->client->get('/api/v1/greenpay/transfers',$this->bearer,['status'=>$status,'per_page'=>50]);
        if(!$r->ok()){$this->errorPage('تراکنش‌های GreenPay',$r);return;}
        $items=$r->json['data']['transfers']['items']??[];$rows='';
        foreach((array)$items as $x){
            if(!is_array($x))continue;$id=(string)($x['id']??'');
            if(preg_match('/^[a-f0-9]{26}$/D',$id)!==1)continue;
            $esc=static fn(mixed $v):string=>Renderer::e((string)$v);
            $state=(string)($x['status']??'');$actions='—';
            if($state==='prepared')$actions='<form method="post" action="/finance/greenpay/'.$id.'/approve"><input type="hidden" name="_csrf" value="'.$esc(Csrf::token()).'"><textarea name="note" required minlength="10" maxlength="1000" placeholder="دلیل تأیید مستقل"></textarea><button class="bn-btn bn-btn--secondary">تأیید توسط مدیر دوم</button></form>';
            if($state==='approved')$actions='<form method="post" action="/finance/greenpay/'.$id.'/send-otp"><input type="hidden" name="_csrf" value="'.$esc(Csrf::token()).'"><button class="bn-btn bn-btn--secondary">ارسال OTP کیف پول مبدا</button></form>'
                  .'<form method="post" action="/finance/greenpay/'.$id.'/execute"><input type="hidden" name="_csrf" value="'.$esc(Csrf::token()).'"><input type="password" inputmode="numeric" autocomplete="one-time-code" name="otp" required pattern="[0-9]{4,8}" placeholder="کد OTP" maxlength="8"><label><input type="checkbox" name="confirm" value="1" required> اجرای انتقال برگشت‌ناپذیر را تأیید می‌کنم</label><button class="bn-btn bn-btn--primary">ارسال یک‌باره به GreenPay</button></form>';
            if(in_array($state,['dispatching','uncertain','provider_accepted'],true))$actions='<form method="post" action="/finance/greenpay/'.$id.'/reconcile"><input type="hidden" name="_csrf" value="'.$esc(Csrf::token()).'"><select name="decision" required><option value="">نتیجه بررسی مستقل</option><option value="reconciled_paid">پرداخت تأییدشده بر اساس سند بانکی</option><option value="reconciled_unpaid">عدم پرداخت تأییدشده بر اساس سند بانکی</option></select><textarea name="note" required minlength="20" maxlength="1000" placeholder="مرجع سند/صورت‌حساب و توضیح دقیق"></textarea><button class="bn-btn bn-btn--secondary">ثبت نتیجه مغایرت</button></form>';
            $rows.='<tr><td>'.$esc($id).'<small>'.$esc($x['external_reference']??'').'</small></td><td>'.$esc($x['cycle_id']??'—').'</td><td>'.$esc($x['amount_rial']??'—').'</td><td>'.$esc($x['from_pocket_mask']??'—').' → '.$esc($x['to_pocket_mask']??'—').'</td><td>'.$esc($state).'</td><td>'.$actions.'</td></tr>';
        }
        if($rows==='')$rows='<tr><td colspan="6">تراکنشی با این وضعیت وجود ندارد.</td></tr>';
        $token=Renderer::e(Csrf::token());
        $content=$this->head('کنترل تراکنش‌های GreenPay','ثبت درخواست، تأیید دوم و رسیدگی به وضعیت‌های نامشخص؛ بدون تکرار خودکار انتقال.','<a class="bn-btn bn-btn--secondary" href="/finance">بازگشت</a>')
            .$this->takeFlash().'<section class="bn-section"><h2 class="bn-sectiontitle">درخواست جدید انتقال</h2><p>هر انتقال فقط به یک سیکل بسته‌شده با مبلغ برابر سهم پذیرنده مرتبط می‌شود. کلید رمزنگاری و مجوز انتقال باید در سرور تنظیم شده باشند.</p><form class="bn-form-grid" method="post" action="/finance/greenpay"><input type="hidden" name="_csrf" value="'.$token.'"><div class="bn-field"><label class="bn-label">شناسه سیکل بسته</label><input name="cycle_id" pattern="[a-f0-9]{26}" required></div><div class="bn-field"><label class="bn-label">کیف پول مبدا</label><input name="from_pocket" required inputmode="numeric" pattern="[0-9]{8,32}"></div><div class="bn-field"><label class="bn-label">کیف پول مقصد</label><input name="to_pocket" required inputmode="numeric" pattern="[0-9]{8,32}"></div><div class="bn-field"><label class="bn-label">مبلغ به ریال</label><input name="amount_rial" type="number" min="1" max="100000000000000" required></div><div class="bn-field"><label class="bn-label">دلیل درخواست</label><textarea name="reason" minlength="10" maxlength="1000" required></textarea></div><div class="bn-form-actions"><button class="bn-btn bn-btn--primary">ثبت درخواست بدون انتقال وجه</button></div></form></section>'
            .'<section class="bn-section"><h2 class="bn-sectiontitle">سوابق و کارتابل انتقال</h2><p>وضعیت provider_accepted به‌معنای پاسخ پذیرش ارائه‌دهنده است و لزوماً تأیید نهایی تسویه بانکی نیست. برای وضعیت‌های نامشخص هیچ دکمه تکرار انتقال وجود ندارد.</p><form method="get" action="/finance/greenpay"><select name="status"><option value="">تمام وضعیت‌ها</option>';
        foreach(['prepared','approved','dispatching','provider_accepted','uncertain','reconciled_paid','reconciled_unpaid'] as $opt)$content.='<option value="'.$opt.'"'.($status===$opt?' selected':'').'>'.$opt.'</option>';
        $content.='</select><button class="bn-btn bn-btn--secondary">فیلتر</button></form><div class="bn-table-wrap"><table class="bn-table"><thead><tr><th>شناسه و مرجع</th><th>سیکل</th><th>مبلغ (ریال)</th><th>کیف پول‌ها</th><th>وضعیت</th><th>اقدام</th></tr></thead><tbody>'.$rows.'</tbody></table></div></section>';
        echo Renderer::page('کنترل پرداخت‌های GreenPay',$content,$this->identity,'finance');
    }
    private function greenpayPrepare():void
    {
        if(!$this->isAdmin()){$this->forbidden();return;}if(!$this->csrf('/finance/greenpay'))return;
        $b=[];foreach(['cycle_id','from_pocket','to_pocket','amount_rial','reason'] as $key)$b[$key]=trim((string)($_POST[$key]??''));
        $r=$this->client->post('/api/v1/greenpay/transfers',$this->bearer,$b,bin2hex(random_bytes(16)));
        $this->flash($r->ok()?'success':'error',$r->ok()?'درخواست ثبت شد و منتظر تأیید مدیر دوم است.':$this->apiMessage($r));$this->redirect('/finance/greenpay');
    }
    private function greenpayAction(string $id,string $action):void
    {
        if(!$this->isAdmin()){$this->forbidden();return;}if(!$this->csrf('/finance/greenpay'))return;
        $body=[];$idem=null;
        if($action==='approve')$body=['note'=>trim((string)($_POST['note']??''))];
        if($action==='send-otp')$idem=bin2hex(random_bytes(16));
        if($action==='execute'){
            if(($_POST['confirm']??'')!=='1'){$this->flash('error','تأیید اجرای انتقال لازم است.');$this->redirect('/finance/greenpay');}
            $body=['otp'=>trim((string)($_POST['otp']??''))];
        }
        if($action==='reconcile')$body=['decision'=>trim((string)($_POST['decision']??'')),'note'=>trim((string)($_POST['note']??''))];
        $r=$this->client->post('/api/v1/greenpay/transfers/'.$id.'/'.$action,$this->bearer,$body,$idem);
        $message=$r->ok()?'عملیات ثبت شد؛ برای وضعیت نهایی به کارتابل مراجعه کنید.':$this->apiMessage($r);
        $this->flash($r->ok()?'success':'error',$message);$this->redirect('/finance/greenpay');
    }

    private function isAdmin(): bool{$roles=(array)($this->identity['roles']??[]);return in_array('super_admin',$roles,true)||in_array('admin',$roles,true);}
    private function canAny(): bool{return $this->isAdmin()||$this->can('finance.dashboard.read')||$this->can('finance.bank_settlement.read')||$this->can('finance.merchant_settlement.read')||$this->can('finance.cycles.read')||$this->can('finance.receipts.manage')||$this->can('finance.reconciliation.read');}
    private function can(string$p): bool{$roles=(array)($this->identity['roles']??[]);$perms=(array)($this->identity['permissions']??[]);return in_array('super_admin',$roles,true)||in_array($p,$perms,true);}
    private function csrf(string$return): bool{if(Csrf::valid((string)($_POST['_csrf']??'')))return true;$this->flash('error','اعتبار فرم منقضی شده است.');$this->redirect($return);}
    private function redirect(string$p): never{header('Location: '.$p,true,303);exit;}
    private function flash(string$t,string$m): void{$_SESSION['finance_flash']=['type'=>$t,'message'=>$m];}
    private function takeFlash(): string{$f=$_SESSION['finance_flash']??null;unset($_SESSION['finance_flash']);return is_array($f)?$this->alert((string)($f['type']??'info'),($f['type']??'')==='success'?'عملیات انجام شد':'عملیات کامل نشد',(string)($f['message']??'')):'';}
    private function head(string$t,string$s,string$a=''): string{return'<div class="bn-pagehead"><div class="bn-pagehead-main"><div class="bn-eyebrow">Finance Service</div><h1 class="bn-title">'.Renderer::e($t).'</h1><p class="bn-subtitle">'.Renderer::e($s).'</p></div>'.($a!==''?'<div class="bn-actions">'.$a.'</div>':'').'</div>';}
    private function card(string$href,string$title,string$text): string{return'<a class="bn-action-card" href="'.Renderer::e($href).'"><strong>'.Renderer::e($title).'</strong><span>'.Renderer::e($text).'</span></a>';}
    private function stat(string$l,mixed$v,string$c,bool$a=false): string{return'<div class="bn-stat'.($a?' bn-stat--accent':'').'"><div class="bn-stat-label">'.Renderer::e($l).'</div><div class="bn-stat-value">'.Renderer::e(PersianDateTime::digits((string)$v)).'</div><div class="bn-stat-caption">'.Renderer::e($c).'</div></div>';}
    private function detail(string$l,string$v): string{return'<div class="bn-detail-item"><span>'.Renderer::e($l).'</span><strong>'.Renderer::e($v).'</strong></div>';}
    private function badge(string$c,string$l): string{$cls=$c==='closed'||$c==='settled'||$c==='recorded'?'bn-status--ok':'bn-status--warn';return'<span class="bn-status '.$cls.'">'.Renderer::e($l).'</span>';}
    private function alert(string$t,string$title,string$m): string{return'<div class="bn-alert bn-alert--'.Renderer::e($t).'"><strong>'.Renderer::e($title).'</strong><br>'.Renderer::e($m).'</div>';}
    private function apiMessage(HttpResponse$r): string{$m=$r->json['error']['message']??null;return is_string($m)&&trim($m)!==''?$m:($r->networkError!==null?'ارتباط با سرویس برقرار نشد.':'عملیات با پاسخ معتبر کامل نشد.');}
    private function errorPage(string$t,HttpResponse$r): void{http_response_code($r->status>=400&&$r->status<600?$r->status:503);echo Renderer::page($t,$this->head($t,'این بخش فعلاً قابل نمایش نیست.').$this->alert('error','عملیات کامل نشد',$this->apiMessage($r)),$this->identity,'finance');}
    private function forbidden(): void{http_response_code(403);echo Renderer::page('دسترسی محدود',$this->head('دسترسی محدود','مجوز لازم برای این بخش روی حساب شما فعال نیست.'),$this->identity,'finance');}
    private function resyncMerchantSettlement(string $id): void
    {
        if(!$this->isAdmin()){$this->forbidden();return;}
        if(!$this->csrf('/finance/request?id='.$id))return;
        $r=$this->client->post('/api/v1/admin/requests/'.$id.'/merchant-settlement/resync',$this->bearer,['reason'=>trim((string)($_POST['reason']??'')),'confirm'=>isset($_POST['confirm'])]);
        $this->flash($r->ok()?'success':'error',$r->ok()?'نمای سازگاری Loans با وضعیت Finance همگام شد.':$this->apiMessage($r));
        $this->redirect('/finance/request?id='.$id);
    }
    private function resetMerchantSettlement(string $id): void
    {
        if(!$this->isAdmin()){$this->forbidden();return;}
        if(!$this->csrf('/finance/request?id='.$id))return;
        $r=$this->client->post('/api/v1/admin/requests/'.$id.'/merchant-settlement/reset',$this->bearer,['reason'=>trim((string)($_POST['reason']??'')),'row_version'=>(int)($_POST['row_version']??-1),'confirm'=>isset($_POST['confirm'])]);
        $needsRetry=$r->ok()&&($r->json['data']['merchant_settlement']['shadow_sync']['retry_required']??false)===true;
        $this->flash($needsRetry?'error':($r->ok()?'success':'error'),$needsRetry?'تسویه Finance بازنشانی شد؛ اما نمایش سازگاری در Loans همگام نشد. لطفاً از دکمه بازهمگام‌سازی استفاده کنید.':($r->ok()?'وضعیت تسویه پذیرنده در هر دو سرویس همگام شد.':$this->apiMessage($r)));
        $this->redirect('/finance/request?id='.$id);
    }

}
