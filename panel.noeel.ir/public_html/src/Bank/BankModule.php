<?php
declare(strict_types=1);
namespace Babanoell\Panel\Bank;

use Babanoell\Panel\Http\HttpResponse;
use Babanoell\Panel\Http\LoansClient;
use Babanoell\Panel\Loans\CaseJourney;
use Babanoell\Panel\Security\Csrf;
use Babanoell\Panel\Support\PersianDateTime;
use Babanoell\Panel\View\Renderer;

final class BankModule
{
    public function __construct(
        private readonly LoansClient $client,
        private readonly string $bearer,
        private readonly array $identity,
    ) {}

    public function handle(string $path,string $method): bool
    {
        if(!$this->allowed()) return false;
        if($path==='/bank'&&$method==='GET'){ $this->overview(); return true; }
        if($path==='/bank/export.csv'&&$method==='GET'){ $this->export(); return true; }
        if(preg_match('#^/bank/cases/([a-f0-9]{26})/verify/request$#',$path,$m)&&$method==='POST'){ $this->queueVerification($m[1]); return true; }
        if(preg_match('#^/bank/cases/([a-f0-9]{26})/verify/result$#',$path,$m)&&$method==='POST'){ $this->completeVerification($m[1]); return true; }
        if(preg_match('#^/bank/cases/([a-f0-9]{26})/verify/([a-f0-9]{26})/(dispatch|otp|refresh)$#',$path,$m)&&$method==='POST'){ $this->providerAction($m[1],$m[2],$m[3]); return true; }
        if(preg_match('#^/bank/cases/([a-f0-9]{26})/review$#',$path,$m)&&$method==='POST'){ $this->review($m[1]); return true; }
        if(preg_match('#^/bank/cases/([a-f0-9]{26})$#',$path,$m)&&$method==='GET'){ $this->detail($m[1]); return true; }
        return false;
    }

    private function allowed(): bool
    {
        $roles=is_array($this->identity['roles']??null)?$this->identity['roles']:[];
        $permissions=is_array($this->identity['permissions']??null)?$this->identity['permissions']:[];
        return in_array('super_admin',$roles,true)||in_array('loans.requests.bank_review',$permissions,true)||in_array('loans.requests.workflow_admin',$permissions,true);
    }

    private function canOverrideVerification(): bool
    {
        $roles=is_array($this->identity['roles']??null)?$this->identity['roles']:[];
        $permissions=is_array($this->identity['permissions']??null)?$this->identity['permissions']:[];
        return in_array('super_admin',$roles,true)||in_array('loans.requests.workflow_admin',$permissions,true);
    }

    private function overview(): void
    {
        $filters=[
            'page'=>max(1,(int)($_GET['page']??1)),
            'per_page'=>25,
            'q'=>trim((string)($_GET['q']??'')),
            'status'=>trim((string)($_GET['status']??'')),
            'identity_status'=>trim((string)($_GET['identity_status']??'')),
            'credit_status'=>trim((string)($_GET['credit_status']??'')),
            'scope'=>trim((string)($_GET['scope']??'')),
        ];
        $summary=$this->client->get('/api/v1/bank/summary',$this->bearer);
        $cases=$this->client->get('/api/v1/bank/cases',$this->bearer,$filters);
        if(!$summary->ok()||!$cases->ok()){ $this->errorPage('کارتابل بانک',!$summary->ok()?$summary:$cases); return; }
        $s=is_array($summary->json['data']??null)?$summary->json['data']:[];
        $data=is_array($cases->json['data']??null)?$cases->json['data']:[];
        $items=is_array($data['items']??null)?$data['items']:[];
        $rows='';
        foreach($items as$item){
            if(!is_array($item))continue;
            $id=(string)($item['id']??'');
            $rows.='<tr><td><a class="bn-table-link" href="/bank/cases/'.Renderer::e($id).'"><strong>'.Renderer::e((string)($item['request_no']??'—')).'</strong><small>'.Renderer::e((string)($item['full_name']??'—')).'</small></a></td>'
                .'<td>'.Renderer::e((string)($item['plan_name']??'—')).'</td>'
                .'<td>'.Renderer::e(PersianDateTime::digits((string)($item['installment_count']??'—'))).'</td>'
                .'<td>'.Renderer::e(PersianDateTime::fromIso((string)($item['granted_at']??''),false)??'—').'</td>'
                .'<td>'.$this->badge((string)($item['bank_settlement_status']??'not_recorded'),$this->bankSettlementStatus((string)($item['bank_settlement_status']??'not_recorded'))).'</td>'
                .'<td>'.Renderer::e(PersianDateTime::fromIso((string)($item['bank_settled_at']??''),false)??'—').'</td>'
                .'<td>'.$this->badge((string)($item['status']??''),$this->requestStatus((string)($item['status']??''))).'</td>'
                .'<td>'.$this->verificationBadge((string)($item['identity_verification_status']??'not_required')).'</td>'
                .'<td>'.$this->verificationBadge((string)($item['credit_validation_status']??'not_required')).'</td>'
                .'<td>'.Renderer::e(PersianDateTime::digits((string)($item['credit_score']??'—'))).'</td>'
                .'<td>'.Renderer::e((string)($item['credit_risk']??'—')).'</td>'
                .'<td>'.Renderer::e(PersianDateTime::fromIso((string)($item['updated_at']??''),false)??'—').'</td></tr>';
        }
        if($rows==='')$rows='<tr><td colspan="12"><div class="bn-empty-inline"><strong>پرونده‌ای در کارتابل بانک نیست</strong><span>فیلترها را تغییر دهید یا منتظر ارجاع پرونده باشید.</span></div></td></tr>';
        $content=$this->head('کارتابل بانک','پرونده‌های ارجاع‌شده، تطبیق هویت، اعتبارسنجی و نتیجه بررسی بانک.','<a class="bn-btn bn-btn--secondary" href="/bank/export.csv?'.Renderer::e(http_build_query(array_filter($filters,static fn($v)=>$v!==''&&$v!==null))).'">خروجی CSV</a>'.(in_array('finance.bank_settlement.read',(array)($this->identity['permissions']??[]),true)||in_array('super_admin',(array)($this->identity['roles']??[]),true)?'<a class="bn-btn bn-btn--secondary" href="/finance/bank-cycles">سیکل‌های تسویه بانک</a>':''))
            .$this->takeFlash()
            .'<div class="bn-stats">'.$this->stat('کل پرونده‌ها',$s['total']??0,'در محدوده دسترسی').$this->stat('در حال بررسی',$s['under_review']??0,'نیازمند اقدام').$this->stat('شاهکار در انتظار',$s['identity_pending']??0,'تطبیق هویت').$this->stat('اعتبارسنجی در انتظار',$s['credit_pending']??0,'اعتبار بانکی').$this->stat('نیازمند توجه',$s['attention']??0,'ناموفق یا منقضی',true).'</div>'
            .'<section class="bn-section"><form class="bn-filterbar" method="get" action="/bank">'
            .'<div class="bn-field"><label class="bn-label">جستجو</label><input name="q" value="'.Renderer::e((string)$filters['q']).'" placeholder="شماره پرونده، نام، کد ملی یا موبایل"></div>'
            .'<div class="bn-field"><label class="bn-label">وضعیت پرونده</label><select name="status"><option value="">همه</option>'.$this->options($this->requestStatuses(),(string)$filters['status']).'</select></div>'
            .'<div class="bn-field"><label class="bn-label">شاهکار</label><select name="identity_status"><option value="">همه</option>'.$this->options($this->verificationStatuses(),(string)$filters['identity_status']).'</select></div>'
            .'<div class="bn-field"><label class="bn-label">اعتبارسنجی</label><select name="credit_status"><option value="">همه</option>'.$this->options($this->verificationStatuses(),(string)$filters['credit_status']).'</select></div>'
            .'<div class="bn-filter-actions"><button class="bn-btn bn-btn--primary" type="submit">اعمال فیلتر</button><a class="bn-btn bn-btn--secondary" href="/bank">پاک کردن</a></div></form></section>'
            .'<section class="bn-section"><div class="bn-table-wrap"><table class="bn-table"><thead><tr><th>پرونده</th><th>طرح</th><th>اقساط</th><th>تاریخ اعطا</th><th>وضعیت تسویه بانک</th><th>تاریخ تسویه بانک</th><th>وضعیت</th><th>شاهکار</th><th>اعتبار</th><th>امتیاز</th><th>ریسک</th><th>آخرین تغییر</th></tr></thead><tbody>'.$rows.'</tbody></table></div>'.$this->pagination('/bank',(int)($data['page']??$filters['page']),(int)($data['per_page']??25),(int)($data['total']??count($items)),$filters).'</section>';
        echo Renderer::page('کارتابل بانک',$content,$this->identity,'bank');
    }

    private function detail(string$id): void
    {
        $case=$this->client->get('/api/v1/requests/'.$id,$this->bearer);
        $credit=$this->client->get('/api/v1/requests/'.$id.'/bank-credit',$this->bearer);
        $workflow=$this->client->get('/api/v1/requests/'.$id.'/workflow',$this->bearer);
        if(!$case->ok()||!$credit->ok()||!$workflow->ok()){ $this->errorPage('پرونده بانکی',!$case->ok()?$case:(!$credit->ok()?$credit:$workflow)); return; }
        $request=is_array($case->json['data']['request']??null)?$case->json['data']['request']:[];
        $bc=is_array($credit->json['data']??null)?$credit->json['data']:[];
        $wf=is_array($workflow->json['data']['workflow']??null)?$workflow->json['data']['workflow']:[];
        $validation=is_array($bc['validation']??null)?$bc['validation']:[];
        $identity=is_array($validation['identity']??null)?$validation['identity']:[];
        $verifications=is_array($bc['verification_requests']??null)?$bc['verification_requests']:[];
        $reviews=is_array($wf['bank_reviews']??null)?$wf['bank_reviews']:[];

        $verificationRows='';
        foreach($verifications as$v){if(!is_array($v))continue;$verificationRows.='<tr><td>'.Renderer::e($this->verificationType((string)($v['verification_type']??''))).'</td><td>'.Renderer::e((string)($v['provider']??'—')).'</td><td>'.$this->verificationBadge((string)($v['status']??'')).'</td><td>'.Renderer::e((string)($v['provider_reference']??'—')).'</td><td>'.Renderer::e(PersianDateTime::fromIso((string)($v['requested_at']??''),false)??'—').'</td><td>'.Renderer::e(PersianDateTime::fromIso((string)($v['completed_at']??''),false)??'—').'</td><td>'.$this->providerControls($id,$v).'</td></tr>';}
        if($verificationRows==='')$verificationRows='<tr><td colspan="7">هنوز درخواست اعتبارسنجی ثبت نشده است.</td></tr>';
        $reviewRows='';
        foreach($reviews as$r){if(!is_array($r))continue;$reviewRows.='<tr><td>'.$this->badge((string)($r['status']??''),$this->reviewStatus((string)($r['status']??''))).'</td><td>'.Renderer::e((string)($r['reason']??'—')).'</td><td>'.Renderer::e((string)($r['note']??'—')).'</td><td>'.Renderer::e((string)($r['external_reference']??'—')).'</td><td>'.Renderer::e(PersianDateTime::fromIso((string)($r['created_at']??''),false)??'—').'</td></tr>';}
        if($reviewRows==='')$reviewRows='<tr><td colspan="5">هنوز نتیجه بررسی بانک ثبت نشده است.</td></tr>';

        $content=$this->head('پرونده '.((string)($request['request_no']??'')),'بررسی پرونده، شاهکار، اعتبارسنجی و تصمیم بانک.','<a class="bn-btn bn-btn--secondary" href="/loans/tasks?request_id='.$id.'&amp;queue=bank&amp;status=all">وظایف پرونده</a><a class="bn-btn bn-btn--secondary" href="/loans/requests/'.$id.'">نمایش کامل پرونده</a><a class="bn-btn bn-btn--secondary" href="/loans/requests/'.$id.'/documents">مدارک پرونده</a><a class="bn-btn bn-btn--secondary" href="/bank">بازگشت به کارتابل</a>')
            .$this->takeFlash()
            .'<section class="bn-section"><div class="bn-detail-grid">'.$this->detailItem('تعداد اقساط',(string)($request['installment_count']??'—')).$this->detailItem('تاریخ اعطا',PersianDateTime::fromIso((string)($request['granted_at']??''),false)??'—').$this->detailItem('وضعیت تسویه بانک با بابانوئل',$this->bankSettlementStatus((string)($request['bank_settlement_status']??'not_recorded'))).$this->detailItem('تاریخ تسویه بانک با بابانوئل',PersianDateTime::fromIso((string)($request['bank_settled_at']??''),false)??'—').$this->detailItem('متقاضی',(string)($request['full_name']??'—')).$this->detailItem('کد ملی',(string)($request['national_code']??'—')).$this->detailItem('موبایل',(string)($request['mobile_number']??'—')).$this->detailItem('طرح',(string)($request['plan_name']??'—')).$this->detailItem('وضعیت',$this->requestStatus((string)($request['status']??''))).$this->detailItem('کارشناس بانک',(string)($request['assigned_bank_user_id']??'—')).'</div></section>'
            .'<div class="bn-stats">'.$this->stat('شاهکار',$this->verificationLabel((string)($identity['status']??'not_required')),!empty($identity['required'])?'الزامی برای این طرح':'غیرالزامی').$this->stat('اعتبارسنجی',$this->verificationLabel((string)($validation['status']??'not_required')),!empty($validation['required'])?'الزامی برای این طرح':'غیرالزامی').$this->stat('امتیاز',(string)($request['credit_score']??'—'),'آخرین امتیاز').$this->stat('ریسک',(string)($request['credit_risk']??'—'),'آخرین سطح ریسک',true).'</div>'
            .CaseJourney::render($this->client,$this->bearer,$id,$this->identity)
            .\Babanoell\Panel\Credit\CreditModule::entry($request,'bank',$this->identity)
            .$this->verificationActions($id,$identity,$validation,$verifications)
            .'<section class="bn-section"><div class="bn-sectionhead"><span class="bn-step">۲</span><div><h2 class="bn-sectiontitle">سوابق اعتبارسنجی</h2></div></div><div class="bn-table-wrap"><table class="bn-table"><thead><tr><th>نوع</th><th>Provider</th><th>وضعیت</th><th>مرجع</th><th>درخواست</th><th>نتیجه</th><th>اقدام ارائه‌دهنده</th></tr></thead><tbody>'.$verificationRows.'</tbody></table></div></section>'
            .$this->reviewForm($id)
            .'<section class="bn-section"><div class="bn-sectionhead"><span class="bn-step">۴</span><div><h2 class="bn-sectiontitle">سوابق بررسی بانک</h2></div></div><div class="bn-table-wrap"><table class="bn-table"><thead><tr><th>نتیجه</th><th>دلیل</th><th>یادداشت</th><th>مرجع</th><th>زمان</th></tr></thead><tbody>'.$reviewRows.'</tbody></table></div></section>';
        echo Renderer::page('پرونده بانکی',$content,$this->identity,'bank');
    }

    private function providerControls(string $caseId,array $verification):string
    {
        $type=(string)($verification['verification_type']??'');
        $status=(string)($verification['status']??'');
        $ref=(string)($verification['provider_reference']??'');
        $id=(string)($verification['id']??'');
        if(!preg_match('/^[a-f0-9]{26}$/D',$id))return '—';
        if($type==='credit_report'&&($verification['provider']??'')==='ics24')return '<a class="bn-btn bn-btn--secondary bn-btn--small" href="/credit/requests/'.$caseId.'?workspace=bank">پیگیری گزارش</a>';
        $url='/bank/cases/'.$caseId.'/verify/'.$id.'/';
        $csrf='<input type="hidden" name="_csrf" value="'.Renderer::e(Csrf::token()).'">';
        if($status==='queued')return '<form class="bn-inline-form" method="post" action="'.$url.'dispatch">'.$csrf.'<button class="bn-btn bn-btn--secondary bn-btn--small" type="submit">ارسال به ارائه‌دهنده</button></form>';
        if($status==='pending'&&$type==='credit_report'&&preg_match('/^[A-Za-z0-9_-]{6,180}$/D',$ref)){
            $meta=is_array($verification['metadata']??null)?$verification['metadata']:[];
            $otpVerified=!empty($meta['otp_verified']);
            $otpForm=$otpVerified?'<span class="bn-status bn-status--warn">کد ثبت شده؛ در انتظار نتیجه</span>':'<form class="bn-inline-form" method="post" action="'.$url.'otp">'.$csrf.'<input class="bn-task-note" name="otp" inputmode="numeric" pattern="[0-9]{4,8}" autocomplete="one-time-code" minlength="4" maxlength="8" placeholder="کد OTP" required><button class="bn-btn bn-btn--secondary bn-btn--small" type="submit">ثبت کد</button></form>';
            return $otpForm.'<form class="bn-inline-form" method="post" action="'.$url.'refresh">'.$csrf.'<button class="bn-btn bn-btn--secondary bn-btn--small" type="submit">بررسی وضعیت</button></form>';
        }
        return $status==='pending'?'در انتظار نتیجه / پیگیری دستی':'—';
    }

    private function verificationActions(string$id,array$identity,array$credit,array$verifications): string
    {
        $latest=[];foreach($verifications as$v)if(is_array($v))$latest[(string)($v['verification_type']??'')]=$v;
        $csrf=Renderer::e(Csrf::token());
        $queue='<div class="bn-action-grid">'
            .'<form class="bn-action-card" method="post" action="/bank/cases/'.$id.'/verify/request"><input type="hidden" name="_csrf" value="'.$csrf.'"><input type="hidden" name="verification_type" value="identity_match"><input type="hidden" name="provider" value="shahkar"><strong>درخواست شاهکار</strong><span>تطبیق کد ملی و موبایل متقاضی.</span><button class="bn-btn bn-btn--primary bn-btn--small" type="submit">ایجاد درخواست</button></form>'
            .'<form class="bn-action-card" method="post" action="/bank/cases/'.$id.'/verify/request"><input type="hidden" name="_csrf" value="'.$csrf.'"><input type="hidden" name="verification_type" value="credit_report"><input type="hidden" name="provider" value="ics24"><strong>درخواست اعتبارسنجی</strong><span>ایجاد درخواست گزارش اعتبار و ریسک.</span><button class="bn-btn bn-btn--primary bn-btn--small" type="submit">ایجاد درخواست</button></form></div>';
        $manual='';
        if($this->canOverrideVerification())foreach(['identity_match'=>'شاهکار','credit_report'=>'اعتبارسنجی'] as$type=>$label){
            $v=$latest[$type]??null;if(!is_array($v)||in_array((string)($v['status']??''),['verified','failed','expired','cancelled'],true))continue;
            $vid=Renderer::e((string)$v['id']);
            $manual.='<details class="bn-occupation-docs"><summary><strong>Override مدیریتی '.$label.'</strong><span>فقط برای مدیر مجاز</span></summary><form method="post" action="/bank/cases/'.$id.'/verify/result" class="bn-grid"><input type="hidden" name="_csrf" value="'.$csrf.'"><input type="hidden" name="verification_id" value="'.$vid.'">'
                .'<div class="bn-field"><label class="bn-label">نتیجه</label><select name="status" required>'.$this->options(['verified'=>'تأیید','failed'=>'ناموفق','expired'=>'منقضی','cancelled'=>'لغو'],'').'</select></div>'
                .'<div class="bn-field"><label class="bn-label">مرجع Provider</label><input name="provider_reference" maxlength="255"></div>'
                .'<div class="bn-field"><label class="bn-label">دلیل Override</label><input name="override_reason" maxlength="1000" required></div>'
                .($type==='credit_report'?'<div class="bn-field"><label class="bn-label">امتیاز</label><input name="score" type="number"></div><div class="bn-field"><label class="bn-label">ریسک</label><input name="risk" maxlength="64"></div>':'')
                .'<div class="bn-form-actions"><button class="bn-btn bn-btn--primary" type="submit">ثبت نتیجه</button></div></form></details>';
        }
        return '<section class="bn-section"><div class="bn-sectionhead"><span class="bn-step">۱</span><div><h2 class="bn-sectiontitle">احراز هویت و اعتبارسنجی</h2><p class="bn-sectiondesc">درخواست‌ها Provider-neutral ثبت می‌شوند؛ نتیجه معتبر از Integrations ثبت می‌شود و Override دستی فقط برای مدیر مجاز و Audit‌شده است.</p></div></div>'.$queue.$manual.'</section>';
    }

    private function reviewForm(string$id): string
    {
        return '<section class="bn-section"><div class="bn-sectionhead"><span class="bn-step">۳</span><div><h2 class="bn-sectiontitle">ثبت نتیجه بررسی بانک</h2><p class="bn-sectiondesc">برای تأیید، تمام اعتبارسنجی‌های الزامی طرح باید تأیید شده باشند.</p></div></div><form method="post" action="/bank/cases/'.$id.'/review" class="bn-grid"><input type="hidden" name="_csrf" value="'.Renderer::e(Csrf::token()).'">'
            .'<div class="bn-field"><label class="bn-label">نتیجه</label><select name="status" required>'.$this->options(['approved'=>'تأیید شده','needs_documents'=>'نیازمند تکمیل مدارک','rejected'=>'رد شده','noted'=>'ثبت یادداشت بررسی'],'').'</select></div>'
            .'<div class="bn-field"><label class="bn-label">دلیل</label><input name="reason" maxlength="1000" placeholder="برای نقص مدارک و رد الزامی است"></div>'
            .'<div class="bn-field"><label class="bn-label">مرجع بانک</label><input name="external_reference" maxlength="190"></div>'
            .'<div class="bn-field"><label class="bn-label">یادداشت</label><textarea name="note" rows="4" maxlength="5000"></textarea></div>'
            .'<div class="bn-form-actions"><button class="bn-btn bn-btn--primary" type="submit">ثبت نتیجه بانک</button></div></form></section>';
    }

    private function queueVerification(string$id): void
    {
        if(!$this->csrf())return;
        $type=(string)($_POST['verification_type']??'');$provider=(string)($_POST['provider']??'');
        $r=$this->client->post('/api/v1/requests/'.$id.'/bank-credit/request',$this->bearer,['verification_type'=>$type,'provider'=>$provider],bin2hex(random_bytes(16)));
        $this->flash($r->ok()?'success':'error',$r->ok()?'درخواست اعتبارسنجی ثبت شد.':$this->apiMessage($r));
        $this->redirect('/bank/cases/'.$id);
    }

    private function providerAction(string $id,string $verificationId,string $action):void
    {
        if(!$this->csrf())return;
        $payload=[];$key=null;
        if($action==='otp'){
            $otp=trim((string)($_POST['otp']??''));
            if(preg_match('/^[0-9]{4,8}$/D',$otp)!==1){$this->flash('error','کد اعتبارسنجی معتبر نیست.');$this->redirect('/bank/cases/'.$id);}
            $payload=['otp'=>$otp];$key=bin2hex(random_bytes(16));
        }
        $path='/api/v1/requests/'.$id.'/bank-credit/verifications/'.$verificationId.'/'.$action;
        $r=$this->client->post($path,$this->bearer,$payload,$key);
        $message=$r->ok()?($action==='dispatch'?'درخواست به ارائه‌دهنده ارسال شد.':($action==='otp'?'کد ثبت شد؛ نتیجه نهایی اعتبارسنجی هنوز در انتظار است.':'وضعیت ارائه‌دهنده بررسی شد؛ نتیجه نهایی صرفاً از پاسخ معتبر ثبت می‌شود.')):$this->apiMessage($r);
        $this->flash($r->ok()?'success':'error',$message);
        $this->redirect('/bank/cases/'.$id);
    }

    private function completeVerification(string$id): void
    {
        if(!$this->csrf())return;
        $payload=['verification_id'=>(string)($_POST['verification_id']??''),'status'=>(string)($_POST['status']??''),'provider_reference'=>(string)($_POST['provider_reference']??''),'score'=>(string)($_POST['score']??''),'risk'=>(string)($_POST['risk']??''),'override_reason'=>(string)($_POST['override_reason']??'')];
        $r=$this->client->post('/api/v1/requests/'.$id.'/bank-credit/result',$this->bearer,$payload);
        $this->flash($r->ok()?'success':'error',$r->ok()?'نتیجه اعتبارسنجی ثبت شد.':$this->apiMessage($r));
        $this->redirect('/bank/cases/'.$id);
    }

    private function review(string$id): void
    {
        if(!$this->csrf())return;
        $payload=['status'=>(string)($_POST['status']??''),'reason'=>(string)($_POST['reason']??''),'note'=>(string)($_POST['note']??''),'external_reference'=>(string)($_POST['external_reference']??'')];
        $r=$this->client->post('/api/v1/requests/'.$id.'/bank-review',$this->bearer,$payload);
        $this->flash($r->ok()?'success':'error',$r->ok()?'نتیجه بررسی بانک ثبت شد.':$this->apiMessage($r));
        $this->redirect('/bank/cases/'.$id);
    }

    private function export(): void
    {
        $query=[];foreach(['q','status','identity_status','credit_status','scope'] as$k){$v=trim((string)($_GET[$k]??''));if($v!=='')$query[$k]=$v;}
        $r=$this->client->get('/api/v1/bank/export',$this->bearer,$query);
        if(!$r->ok()){ $this->errorPage('خروجی کارتابل بانک',$r); return; }
        $items=is_array($r->json['data']['items']??null)?$r->json['data']['items']:[];
        header('Content-Type: text/csv; charset=UTF-8');header('Content-Disposition: attachment; filename="bank-cases-'.gmdate('Ymd-His').'.csv"');header('Cache-Control: no-store');
        echo "\xEF\xBB\xBF";$out=fopen('php://output','wb');if($out===false)return;
        fputcsv($out,['شماره پرونده','نام متقاضی','کد ملی','موبایل','طرح','تعداد اقساط','تاریخ اعطا','وضعیت تسویه بانک','تاریخ تسویه بانک','وضعیت','شاهکار','اعتبارسنجی','امتیاز','ریسک','آخرین تغییر']);
        foreach($items as$i){if(!is_array($i))continue;$row=[(string)($i['request_no']??''),(string)($i['full_name']??''),(string)($i['national_code']??''),(string)($i['mobile_number']??''),(string)($i['plan_name']??''),(string)($i['installment_count']??''),(string)($i['granted_at']??''),$this->bankSettlementStatus((string)($i['bank_settlement_status']??'not_recorded')),(string)($i['bank_settled_at']??''),$this->requestStatus((string)($i['status']??'')),$this->verificationLabel((string)($i['identity_verification_status']??'')),$this->verificationLabel((string)($i['credit_validation_status']??'')),(string)($i['credit_score']??''),(string)($i['credit_risk']??''),(string)($i['updated_at']??'')];fputcsv($out,array_map([SpreadsheetSafeCell::class,'escape'],$row));}
        fclose($out);
    }

    private function csrf(): bool
    {
        if(Csrf::valid(isset($_POST['_csrf'])?(string)$_POST['_csrf']:null))return true;
        http_response_code(419);echo Renderer::page('اعتبار فرم منقضی شده',$this->alert('error','عملیات انجام نشد','اعتبار فرم منقضی شده است. صفحه را تازه‌سازی کنید.'),$this->identity,'bank');return false;
    }

    private function redirect(string$url): never { header('Location: '.$url,true,303);exit; }
    private function flash(string$type,string$message): void { $_SESSION['panel_bank_flash']=['type'=>$type,'message'=>$message]; }
    private function takeFlash(): string { $f=$_SESSION['panel_bank_flash']??null;unset($_SESSION['panel_bank_flash']);if(!is_array($f))return'';return$this->alert(($f['type']??'')==='success'?'success':'error',($f['type']??'')==='success'?'عملیات موفق بود':'عملیات انجام نشد',(string)($f['message']??'')); }
    private function head(string$title,string$subtitle,string$actions=''): string { return '<div class="bn-pagehead"><div class="bn-pagehead-main"><div class="bn-eyebrow">عملیات بانک و اعتبار</div><h1 class="bn-title">'.Renderer::e($title).'</h1><p class="bn-subtitle">'.Renderer::e($subtitle).'</p></div>'.($actions!==''?'<div class="bn-actions">'.$actions.'</div>':'').'</div>'; }
    private function stat(string$label,mixed$value,string$caption,bool$accent=false): string { return '<div class="bn-stat'.($accent?' bn-stat--accent':'').'"><div class="bn-stat-label">'.Renderer::e($label).'</div><div class="bn-stat-value">'.Renderer::e(PersianDateTime::digits((string)$value)).'</div><div class="bn-stat-caption">'.Renderer::e($caption).'</div></div>'; }
    private function detailItem(string$label,string$value): string { return '<div class="bn-detail-item"><span>'.Renderer::e($label).'</span><strong>'.Renderer::e(PersianDateTime::digits($value)).'</strong></div>'; }
    private function alert(string$type,string$title,string$message): string { return '<div class="bn-alert bn-alert--'.Renderer::e($type).'" role="alert"><strong>'.Renderer::e($title).'</strong><br>'.Renderer::e($message).'</div>'; }
    private function badge(string$code,string$label): string { $ok=in_array($code,['approved','verified','expert_approved','docs_verified','granted'],true);$bad=in_array($code,['rejected','failed','expired','cancelled'],true);$neutral=in_array($code,['not_required','draft','archived'],true);return '<span class="bn-status '.($ok?'bn-status--ok':($bad?'bn-status--bad':($neutral?'bn-status--neutral':'bn-status--warn'))).'">'.Renderer::e($label).'</span>'; }
    private function verificationBadge(string$status): string { return $this->badge($status,$this->verificationLabel($status)); }
    private function verificationLabel(string$status): string { return $this->verificationStatuses()[$status]??($status!==''?$status:'—'); }
    private function verificationType(string$type): string { return ['identity_match'=>'تطبیق هویت شاهکار','credit_report'=>'گزارش اعتبارسنجی'][$type]??$type; }
    private function reviewStatus(string$status): string { return ['approved'=>'تأیید شده','needs_documents'=>'نیازمند تکمیل مدارک','rejected'=>'رد شده','noted'=>'یادداشت بررسی'][$status]??$status; }
    private function requestStatus(string$status): string { return $this->requestStatuses()[$status]??$status; }
    private function requestStatuses(): array { return ['submitted'=>'ثبت‌شده','under_review'=>'در حال بررسی','need_correction'=>'نقص مدارک','expert_approved'=>'تأیید کارشناس','docs_completion'=>'تکمیل مدارک','docs_verified'=>'تأیید مدارک','granted'=>'اعطا شده','rejected'=>'ردشده']; }
    private function bankSettlementStatus(string $status):string{return ['not_recorded'=>'ثبت نشده','recorded'=>'ثبت شده','cancelled'=>'ابطال ثبت'][$status]??($status!==''?$status:'ثبت نشده');}
    private function verificationStatuses(): array { return ['not_required'=>'غیرالزامی','queued'=>'در صف','pending'=>'در انتظار','verified'=>'تأیید شده','failed'=>'ناموفق','expired'=>'منقضی','cancelled'=>'لغو شده']; }
    private function options(array$items,string$selected): string { $out='';foreach($items as$v=>$l)$out.='<option value="'.Renderer::e((string)$v).'"'.((string)$v===$selected?' selected':'').'>'.Renderer::e((string)$l).'</option>';return$out; }
    private function apiMessage(HttpResponse$r): string { $m=$r->json['error']['message']??null;return is_string($m)&&trim($m)!==''?$m:($r->status===0?'ارتباط با سرویس تسهیلات برقرار نشد.':'عملیات کامل نشد.'); }
    private function errorPage(string$title,HttpResponse$r): void { echo Renderer::page($title,$this->head($title,'این بخش در حال حاضر قابل نمایش نیست.').$this->alert('error','دریافت اطلاعات انجام نشد',$this->apiMessage($r)).'<div class="bn-form-actions"><a class="bn-btn bn-btn--primary" href="/bank">بازگشت به کارتابل</a></div>',$this->identity,'bank'); }
    private function pagination(string$path,int$page,int$perPage,int$total,array$params=[]): string { $pages=max(1,(int)ceil($total/max(1,$perPage)));if($pages<=1)return'<div class="bn-pagination-meta">'.PersianDateTime::digits((string)$total).' مورد</div>';$page=max(1,min($pages,$page));$link=function(int$t)use($path,$params){$q=array_filter($params,static fn($v)=>$v!==''&&$v!==null);$q['page']=$t;return$path.'?'.http_build_query($q);};return'<nav class="bn-pagination"><a class="bn-btn bn-btn--secondary bn-btn--small'.($page<=1?' is-disabled':'').'"'.($page>1?' href="'.Renderer::e($link($page-1)).'"':' aria-disabled="true"').'>قبلی</a><span>صفحه '.PersianDateTime::digits((string)$page).' از '.PersianDateTime::digits((string)$pages).' · '.PersianDateTime::digits((string)$total).' مورد</span><a class="bn-btn bn-btn--secondary bn-btn--small'.($page>=$pages?' is-disabled':'').'"'.($page<$pages?' href="'.Renderer::e($link($page+1)).'"':' aria-disabled="true"').'>بعدی</a></nav>'; }
}
