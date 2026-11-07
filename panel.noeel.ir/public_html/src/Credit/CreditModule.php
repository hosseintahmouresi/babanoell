<?php
declare(strict_types=1);
namespace Babanoell\Panel\Credit;

use Babanoell\Panel\Http\LoansClient;
use Babanoell\Panel\Http\HttpResponse;
use Babanoell\Panel\Security\Csrf;
use Babanoell\Panel\Support\PersianDateTime;
use Babanoell\Panel\View\Renderer;

final class CreditModule
{
    public function __construct(private readonly LoansClient $client, private readonly string $bearer,
        private readonly array $identity) {}

    public static function entry(array $loan, string $workspace = '', array $identity = []): string
    {
        if(empty($loan['plan_rules']['requires_bank_validation'])&&$workspace!=='bank')return '';
        $id=(string)($loan['id']??'');if(!preg_match('/^[a-f0-9]{26}$/D',$id))return '';
        if($identity!==[]){
            $actor=(string)($identity['user']['id']??$identity['user_id']??$identity['id']??'');
            $owner=$actor!==''&&in_array($actor,[(string)($loan['created_by_user_id']??''),(string)($loan['applicant_user_id']??''),(string)($loan['acceptor_user_id']??'')],true);
            $bank=in_array('super_admin',$identity['roles']??[],true)||in_array('loans.requests.bank_review',$identity['permissions']??[],true)||in_array('loans.requests.workflow_admin',$identity['permissions']??[],true);
            if(!$owner&&!$bank)return '';
        }
        $url='/credit/requests/'.$id.($workspace!==''?'?workspace='.rawurlencode($workspace):'');
        return '<section class="bn-section"><div class="bn-sectionhead"><div><h2 class="bn-sectiontitle">اعتبارسنجی بانکی</h2>'
            .'<p class="bn-sectiondesc">ارسال رمز تأیید، دریافت گزارش و مشاهدهٔ سابقهٔ اعتبارسنجی همین پرونده.</p></div>'
            .'<a class="bn-btn bn-btn--primary" href="'.Renderer::e($url).'">مشاهده و ادامهٔ اعتبارسنجی</a></div></section>';
    }

    public static function continueCreated(array $loan,bool $requestedSubmit,string $workspace): void
    {
        if(!$requestedSubmit||($loan['status']??'')!=='draft'||empty($loan['plan_rules']['requires_bank_validation']))return;
        $id=(string)($loan['id']??'');if(!preg_match('/^[a-f0-9]{26}$/D',$id))return;
        $_SESSION['credit_flash']=['type'=>'success','message'=>'اطلاعات درخواست ذخیره شد. برای ثبت نهایی، اعتبارسنجی متقاضی را تکمیل کنید.'];
        header('Location: /credit/requests/'.$id.'?workspace='.rawurlencode($workspace),true,303);exit;
    }

    public function handle(string $path,string $method): bool
    {
        if(!preg_match('#^/credit/requests/([a-f0-9]{26})(?:/(request|dispatch|recover|otp|refresh|poll|submit|reports/([a-f0-9]{26})))?$#',$path,$match))return false;
        $id=$match[1];$action=$match[2]??'';
        if(str_starts_with($action,'reports/')&&$method==='GET'){$this->download($id,$match[3]);return true;}
        if($action===''&&$method==='GET'){$this->page($id);return true;}
        if($method!=='POST'){http_response_code(405);return true;}
        if(!Csrf::valid((string)($_POST['_csrf']??''))){
            if($action==='poll'){$this->json(419,['ok'=>false,'message'=>'نشست این صفحه به‌روز نیست؛ صفحه را تازه‌سازی کنید.']);}
            $this->flash('error','اعتبار فرم منقضی شده است.');$this->go($id);
        }
        $base='/api/v1/requests/'.$id.'/credit';$key=(string)($_POST['_operation']??'');
        if($action==='submit'){
            $response=$this->client->post('/api/v1/requests/'.$id.'/submit',$this->bearer,[]);
            $this->flash($response->ok()?'success':'error',$response->ok()?'درخواست با گزارش معتبر ثبت و برای بررسی ارسال شد.':$this->message($response));
            $this->go($id);
        }
        $verification=(string)($_POST['verification_id']??'');
        if($action==='request'){
            if(!preg_match('/^[A-Za-z0-9_-]{16,120}$/D',$key)){$this->flash('error','شناسهٔ این عملیات معتبر نیست؛ صفحه را تازه‌سازی کنید.');$this->go($id);}
            $queued=$this->client->post($base.'/request',$this->bearer,[],$key);
            if(!$queued->ok()){$this->flash('error',$this->message($queued));$this->go($id);}
            $verification=(string)($queued->json['data']['verification']['id']??'');
            if(($queued->json['data']['verification']['status']??'queued')!=='queued'){
                $this->flash('success','درخواست قبلی بازیابی شد؛ می‌توانید از همین صفحه ادامه دهید.');$this->go($id);
            }
        }
        if(!preg_match('/^[a-f0-9]{26}$/D',$verification)){
            if($action==='poll')$this->json(422,['ok'=>false,'message'=>'درخواست اعتبارسنجی معتبر نیست.']);
            $this->flash('error','درخواست اعتبارسنجی معتبر نیست.');$this->go($id);
        }
        $operation=match($action){'request','dispatch'=>'dispatch','recover'=>'recover','otp'=>'otp',default=>'refresh'};
        $payload=$operation==='otp'?['otp'=>trim((string)($_POST['otp']??''))]:[];
        $response=$this->client->post($base.'/verifications/'.$verification.'/'.$operation,$this->bearer,$payload,$operation==='otp'?$key:null);
        $status=(string)($response->json['data']['verification']['status']??'pending');
        $message=$response->ok()?match($operation){
            'dispatch','recover'=>'درخواست کد تأیید برای موبایل متقاضی ثبت شد؛ کد دریافتی را وارد کنید.',
            'otp'=>'کد تأیید شد؛ در حال دریافت گزارش نهایی هستیم.',
            default=>$status==='verified'?'گزارش نهایی دریافت و با پرونده تطبیق داده شد.':'گزارش هنوز در حال آماده شدن است.',
        }:$this->message($response);
        if($action==='poll')$this->json($response->ok()?200:($response->status>=400?$response->status:503),
            ['ok'=>$response->ok(),'status'=>$status,'message'=>$message,'code'=>$response->json['error']['code']??null]);
        $this->flash($response->ok()?'success':'error',$message);$this->go($id);
    }

    private function page(string $id): void
    {
        $response=$this->client->get('/api/v1/requests/'.$id.'/credit',$this->bearer);
        $data=$response->json['data']??null;
        if(!$response->ok()||!is_array($data)||!is_array($data['request']??null)||($data['request']['id']??null)!==$id){
            http_response_code($response->status>=400?$response->status:503);
            echo Renderer::page('اعتبارسنجی بانکی','<div class="bn-alert bn-alert--error">'.Renderer::e($this->message($response)).'</div>',$this->identity);return;
        }
        header('Cache-Control: private,no-store');header('Referrer-Policy: no-referrer');
        $loan=$data['request'];$workspace=$this->workspace($loan);$back=$workspace==='bank'?'/bank/cases/'.$id:'/'.$workspace.'/requests/'.$id;
        $base='/credit/requests/'.$id;$state=$data['submission']??[];$latest=null;$rows='';
        foreach($data['verification_requests']??[] as $item){
            if(($item['verification_type']??'')!=='credit_report')continue;
            $latest??=$item;$vid=(string)$item['id'];
            $link=isset($item['metadata']['evidence_id'])?'<a class="bn-btn bn-btn--secondary bn-btn--small" href="'.$base.'/reports/'.$vid.'">دریافت گزارش</a>':'—';
            $rows.='<tr><td>'.Renderer::e($this->status((string)$item['status'])).'</td><td>'.Renderer::e($item['metadata']['report_code']??'—').'</td><td>'.Renderer::e(PersianDateTime::fromIso((string)$item['requested_at'],false)??'—').'</td><td>'.$link.'</td></tr>';
        }
        $content='<div class="bn-pagehead"><div><div class="bn-eyebrow">پرونده '.Renderer::e((string)$loan['request_no']).'</div><h1 class="bn-title">اعتبارسنجی بانکی</h1><p class="bn-subtitle">'.Renderer::e((string)$loan['full_name']).' · '.Renderer::e((string)$loan['plan_name']).'</p></div><a class="bn-btn bn-btn--secondary" href="'.$back.'">بازگشت به پرونده</a></div>'.$this->takeFlash();
        $content.='<section class="bn-section"><div class="bn-detail-grid">'.$this->detail('کد ملی',(string)$loan['national_code']).$this->detail('شماره موبایل',(string)$loan['mobile_number']).'</div><p class="bn-helper">رمز به شمارهٔ متقاضی ارسال می‌شود. تأیید رمز به معنی دریافت گزارش یا تأیید تسهیلات نیست.</p></section>';
        $mayWrite=in_array((string)$loan['status'],['draft','need_correction'],true)||$this->bankAllowed();
        $mayWrite=$mayWrite&&!in_array((string)$loan['status'],['granted','archived','cancelled'],true);
        if($mayWrite){
            $expired=$latest!==null&&$latest['status']!=='verified'&&!empty($latest['expires_at'])&&strtotime((string)$latest['expires_at'].' UTC')<=time();
            if($latest===null||$expired||in_array($latest['status'],['failed','expired','cancelled'],true)
                ||($latest['status']==='verified'&&empty($state['ready']))){
                $content.='<section class="bn-section"><h2 class="bn-sectiontitle">شروع اعتبارسنجی</h2><p>برای دریافت گزارش، ابتدا کد تأیید متقاضی را درخواست کنید.</p>'.$this->form($base.'/request',$workspace,'','درخواست کد تأیید').'</section>';
            }elseif($latest['status']==='queued'){
                $content.='<section class="bn-section"><h2 class="bn-sectiontitle">درخواست آمادهٔ ارسال است</h2><p>اطلاعات درخواست ذخیره شده است. برای ارسال به سرویس اعتبارسنجی اقدام کنید.</p>'.$this->form($base.'/dispatch',$workspace,(string)$latest['id'],'ارسال درخواست کد تأیید').'</section>';
            }elseif($latest['status']==='pending'){
                if(empty($latest['provider_reference'])){
                    $content.='<section class="bn-section"><div class="bn-alert bn-alert--warning">نتیجهٔ ارسال درخواست هنوز قطعی نیست. نتیجهٔ همان ارسال قبلی را بازیابی کنید.</div>'.$this->form($base.'/recover',$workspace,(string)$latest['id'],'بازیابی نتیجهٔ ارسال').'</section>';
                }elseif(empty($latest['metadata']['otp_verified'])){
                    $field='<div class="bn-field"><label class="bn-label" for="credit-otp">کد تأیید متقاضی</label><input id="credit-otp" name="otp" inputmode="numeric" autocomplete="one-time-code" minlength="4" maxlength="8" required></div>';
                    $content.='<section class="bn-section"><h2 class="bn-sectiontitle">تأیید کد</h2>'.$this->form($base.'/otp',$workspace,(string)$latest['id'],'تأیید و دریافت گزارش',$field).'</section>';
                }else{
                    $content.='<section class="bn-section" data-credit-poll data-poll-url="'.$base.'/poll" data-verification="'.Renderer::e((string)$latest['id']).'" data-csrf="'.Renderer::e(Csrf::token()).'"><h2 class="bn-sectiontitle">در حال آماده شدن گزارش</h2><p role="status" data-credit-status>پس از آماده شدن گزارش، این صفحه به‌روز می‌شود.</p>'.$this->form($base.'/refresh',$workspace,(string)$latest['id'],'بررسی دوبارهٔ گزارش').'</section><script defer src="/ui-asset.php?file=credit.js&amp;v='.rawurlencode((string)\Babanoell\Panel\Platform\Ui::version()['ui']).'"></script>';
                }
            }
        }
        if($latest!==null&&isset($latest['metadata']['evidence_id'])){
            $report=$this->client->get('/api/v1/requests/'.$id.'/credit/reports/'.(string)$latest['id'],$this->bearer);
            if($report->ok())$content.=$this->report($report->json['data'],$base.'/reports/'.(string)$latest['id']);
        }
        if(in_array((string)$loan['status'],['draft','need_correction'],true)&&($state['ready']??false)===true){
            $content.='<section class="bn-section"><div class="bn-alert bn-alert--success">اعتبارسنجی لازم برای ثبت نهایی این درخواست تکمیل است.</div>'.$this->form($base.'/submit',$workspace,'','ثبت نهایی و ارسال برای بررسی').'</section>';
        }
        $content.='<section class="bn-section"><h2 class="bn-sectiontitle">سابقهٔ اعتبارسنجی</h2><div class="bn-table-wrap"><table class="bn-table"><thead><tr><th>وضعیت</th><th>شناسهٔ گزارش</th><th>زمان درخواست</th><th>گزارش</th></tr></thead><tbody>'.($rows!==''?$rows:'<tr><td colspan="4">هنوز درخواستی ثبت نشده است.</td></tr>').'</tbody></table></div></section>';
        echo Renderer::page('اعتبارسنجی بانکی',$content,$this->identity,$workspace==='applicant'?'applicant-requests':($workspace==='acceptor'?'acceptor-requests':($workspace==='bank'?'bank':'loans')));
    }

    private function report(array $evidence,string $download): string
    {
        return CreditReportView::render($evidence,$download);
    }

    private function download(string $id,string $verification): never
    {
        $response=$this->client->get('/api/v1/requests/'.$id.'/credit/reports/'.$verification,$this->bearer);
        if(!$response->ok()){http_response_code($response->status===403?403:404);echo Renderer::page('گزارش اعتبارسنجی','<p class="bn-alert bn-alert--error">'.Renderer::e($this->message($response)).'</p>',$this->identity);exit;}
        $data=$response->json['data'];$json=(string)($data['canonical_json']??'');
        if($json===''||!hash_equals((string)($data['sha256']??''),hash('sha256',$json))){http_response_code(503);exit('Report integrity unavailable');}
        header('Content-Type: application/json; charset=utf-8');header('Content-Disposition: attachment; filename="credit-report-'.$verification.'.json"');
        header('Cache-Control: private,no-store');header('X-Content-Type-Options: nosniff');header('Referrer-Policy: no-referrer');header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'");
        header('X-Noel-Content-SHA256: '.$data['sha256']);echo $json;exit;
    }

    private function form(string $action,string $workspace,string $verification,string $label,string $fields=''): string
    {
        return '<form method="post" action="'.$action.'"><input type="hidden" name="_csrf" value="'.Renderer::e(Csrf::token()).'"><input type="hidden" name="_operation" value="'.bin2hex(random_bytes(24)).'"><input type="hidden" name="_workspace" value="'.Renderer::e($workspace).'"><input type="hidden" name="verification_id" value="'.Renderer::e($verification).'">'.$fields.'<div class="bn-form-actions"><button class="bn-btn bn-btn--primary" type="submit">'.$label.'</button></div></form>';
    }
    private function workspace(array $loan): string
    {
        $requested=(string)($_POST['_workspace']??$_GET['workspace']??'');
        $roles=$this->identity['roles']??[];
        $actor=(string)($this->identity['user']['id']??'');
        $applicant=in_array('applicant',$roles,true)&&$actor!==''&&in_array($actor,[(string)($loan['applicant_user_id']??''),(string)($loan['created_by_user_id']??'')],true);
        $acceptor=in_array('acceptor',$roles,true)&&$actor!==''&&in_array($actor,[(string)($loan['acceptor_user_id']??''),(string)($loan['created_by_user_id']??'')],true);
        if($requested==='applicant'&&$applicant)return 'applicant';
        if($requested==='acceptor'&&$acceptor)return 'acceptor';
        if($requested==='bank'&&$this->bankAllowed())return 'bank';
        if($requested==='loans'&&in_array('loans.requests.workflow_admin',$this->identity['permissions']??[],true))return 'loans';
        if($applicant)return 'applicant';
        if($acceptor)return 'acceptor';
        return $this->bankAllowed()?'bank':'loans';
    }

    private function bankAllowed(): bool {return in_array('super_admin',$this->identity['roles']??[],true)||in_array('loans.requests.bank_review',$this->identity['permissions']??[],true)||in_array('loans.requests.workflow_admin',$this->identity['permissions']??[],true);}
    private function status(string $status): string {return ['queued'=>'آمادهٔ ارسال','pending'=>'در انتظار گزارش','verified'=>'تکمیل‌شده','failed'=>'ناموفق','expired'=>'منقضی','cancelled'=>'لغوشده'][$status]??'در حال بررسی';}
    private function detail(string $title,string $value): string {return '<div class="bn-detail-item"><span>'.Renderer::e($title).'</span><strong>'.Renderer::e(PersianDateTime::digits($value!==''?$value:'—')).'</strong></div>';}
    private function message(HttpResponse $response): string
    {
        $code=(string)($response->json['error']['code']??'');
        foreach(['verification_expired'=>'مهلت این درخواست پایان یافته است؛ اعتبارسنجی را از ابتدا انجام دهید.','verification_subject_changed'=>'اطلاعات هویتی پرونده تغییر کرده است؛ اعتبارسنجی تازه لازم است.','verification_superseded'=>'این درخواست جایگزین شده است؛ صفحه را تازه‌سازی کنید و درخواست جدید را ادامه دهید.','credit_otp_required'=>'ابتدا رمز تأیید متقاضی را وارد کنید.'] as $reason=>$message)if(str_contains($code,$reason))return $message;
        $message=(string)($response->json['error']['message']??'');
        return $message!==''&&!preg_match('/^[a-z0-9_]+$/D',$message)?$message:'گزارش معتبر دریافت نشد؛ وضعیت ذخیره شده است. کمی بعد دوباره بررسی کنید.';
    }
    private function flash(string $type,string $message): void {$_SESSION['credit_flash']=['type'=>$type,'message'=>$message];}
    private function takeFlash(): string {$flash=$_SESSION['credit_flash']??null;unset($_SESSION['credit_flash']);return is_array($flash)?'<div class="bn-alert bn-alert--'.($flash['type']==='success'?'success':'error').'" role="status">'.Renderer::e((string)$flash['message']).'</div>':'';}
    private function go(string $id): never {$workspace=(string)($_POST['_workspace']??'');$suffix=in_array($workspace,['bank','applicant','acceptor','loans'],true)?'?workspace='.$workspace:'';header('Location: /credit/requests/'.$id.$suffix,true,303);exit;}
    private function json(int $status,array $data): never {http_response_code($status);header('Content-Type: application/json; charset=utf-8');header('Cache-Control: private,no-store');echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}
}
