<?php
declare(strict_types=1);
namespace Babanoell\Panel\Reports;

use Babanoell\Panel\App\Config;
use Babanoell\Panel\Http\FinanceClient;
use Babanoell\Panel\Http\HttpResponse;
use Babanoell\Panel\Http\IntegrationsClient;
use Babanoell\Panel\System\HealthAudit;
use Babanoell\Panel\Http\HttpTransportInterface;
use Babanoell\Panel\Http\LoansClient;
use Babanoell\Panel\Http\PartnersClient;
use Babanoell\Panel\Support\PersianDateTime;
use Babanoell\Panel\View\Renderer;
use Babanoell\Panel\Security\Csrf;
use Babanoell\Panel\System\OperationalPreferences;

/** Read-only operational reports. Never connects directly to another service database. */
final class ReportsModule
{
    private const METRICS = [
        'loans'=>['title'=>'تسهیلات و پرونده‌ها','permission'=>'loans.requests.read','url'=>'/loans',
            'keys'=>['active_plans'=>'طرح فعال','requests'=>'تمام پرونده‌های قابل مشاهده','in_progress'=>'در حال بررسی','granted'=>'اعطا شده']],
        'partners'=>['title'=>'پذیرندگان و همکاری‌ها','permission'=>'partners.merchants.read','url'=>'/partners',
            'keys'=>['merchants'=>'کل پذیرندگان','active_merchants'=>'پذیرنده فعال','cooperation_open'=>'درخواست همکاری باز','marketplace_claims'=>'پرونده واگذارشده در بازار','contract_versions'=>'نسخه‌های ثبت‌شده قرارداد','contract_signed_versions'=>'نسخه قرارداد امضاشده','contract_pending_versions'=>'نسخه قرارداد در انتظار امضا']],
        'finance'=>['title'=>'تسویه و عملیات مالی','permission'=>'finance.dashboard.read','url'=>'/finance',
            'keys'=>['bank_recorded'=>'تسویه بانک ثبت‌شده','merchant_pending'=>'تسویه پذیرنده در انتظار','open_cycles'=>'سیکل تسویه باز','pending_receipts'=>'رسید در انتظار','open_discrepancies'=>'مغایرت باز']],
    ];

    public function __construct(
        private readonly LoansClient $loans,
        private readonly PartnersClient $partners,
        private readonly FinanceClient $finance,
        private readonly IntegrationsClient $integrations,
        private readonly Config $config,
        private readonly string $bearer,
        private readonly array $identity,
        private readonly ?HttpTransportInterface $healthTransport = null,
        private readonly ?OperationalPreferences $preferences = null
    ) {}

    public function handle(string $path, string $method): bool
    {
        if (!in_array($path,['/reports','/reports/export.csv','/system/settings','/system/health','/system/updates'],true)) return false;
        if ($path==='/system/settings') {
            if (!in_array($method,['GET','POST'],true)) { http_response_code(405);header('Allow: GET, POST');return true; }
            $this->settings($method); return true;
        }
        if ($method !== 'GET') { http_response_code(405);header('Allow: GET'); return true; }
        if ($path==='/system/health') { $this->healthPage(); return true; }
        if ($path==='/system/updates') { $this->updatesHub(); return true; }
        $allowed=self::allowedSources($this->identity);
        if (!$allowed) { $this->forbidden();return true; }
        $groups=$this->collect($allowed);
        if ($path==='/reports/export.csv') { $this->downloadCsv($groups);return true; }
        $this->reportPage($groups);
        return true;
    }

    /** @return list<string> */
    public static function allowedSources(array $identity): array
    {
        $roles=is_array($identity['roles']??null)?$identity['roles']:[];
        $permissions=is_array($identity['permissions']??null)?$identity['permissions']:[];
        $super=in_array('super_admin',$roles,true);
        $allowed=[];
        foreach(self::METRICS as $service=>$meta) {
            if($super||in_array($meta['permission'],$permissions,true))$allowed[]=$service;
        }
        return $allowed;
    }

    /** @return array<string,array{title:string,url:string,status:string,metrics:array<string,array{label:string,value:int|null}>}> */
    private function collect(array $allowed): array
    {
        $groups=[];
        foreach($allowed as $service) {
            // Never call an unauthorized service: each backend also independently checks the token.
            $response=match($service){
                'loans'=>$this->loans->get('/api/v1/stats',$this->bearer),
                'partners'=>$this->partners->get('/api/v1/stats',$this->bearer),
                'finance'=>$this->finance->get('/api/v1/stats',$this->bearer),
            };
            $meta=self::METRICS[$service];
            $groups[$service]=self::parseResponse($service,$response);
            $groups[$service]['title']=$meta['title'];
            $groups[$service]['url']=$meta['url'];
        }
        return $groups;
    }

    /** @return array{title:string,url:string,status:string,metrics:array<string,array{label:string,value:int|null}>} */
    public static function parseResponse(string $service,HttpResponse $response): array
    {
        if(!isset(self::METRICS[$service]))throw new \InvalidArgumentException('Unknown report source');
        $definition=self::METRICS[$service];
        $payload=$response->json['data']??null;
        if($service==='finance' && is_array($payload))$payload=$payload['stats']??null;
        $good=$response->ok()&&is_array($payload);
        $metrics=[];
        foreach($definition['keys'] as $key=>$label){
            $raw=$good?($payload[$key]??null):null;
            // Missing / partial responses must never be shown as numeric zero.
            $value=is_int($raw)&&$raw>=0?$raw:(is_string($raw)&&ctype_digit($raw)?(int)$raw:null);
            $metrics[$key]=['label'=>$label,'value'=>$value];
        }
        $complete=$good;
        foreach($metrics as $metric)if($metric['value']===null)$complete=false;
        return ['title'=>$definition['title'],'url'=>$definition['url'],
            'status'=>!$good?'unavailable':($complete?'ok':'incomplete'),'metrics'=>$metrics];
    }

    private function reportPage(array $groups): void
    {
        $stamp=gmdate('Y-m-d H:i:s').' UTC';
        $content='<div class="bn-pagehead"><div class="bn-pagehead-main"><div class="bn-eyebrow">گزارش‌های یکپارچه</div><h1 class="bn-title">گزارش مدیریتی نوئل</h1>'
            .'<p class="bn-subtitle">آمار لحظه درخواست از سرویس‌های مستقل، مطابق مجوزهای حساب شما؛ بدون تجمیع مستقیم دیتابیس‌ها.</p></div>'
            .'<div class="bn-actions">'.(in_array('super_admin',(array)($this->identity['roles']??[]),true)?'<a class="bn-btn bn-btn--secondary" href="/reports/history">گزارش تاریخی</a>':'').'<a class="bn-btn bn-btn--secondary" href="/reports/export.csv">خروجی CSV</a></div></div>'
            .'<p class="bn-sectiondesc">زمان درخواست: '.Renderer::e($stamp).' — مقدار «—» یعنی داده تأیید نشده و معادل صفر نیست.</p>';
        foreach($groups as $group){
            $status=$group['status'];
            $label=$status==='ok'?'دریافت موفق':($status==='incomplete'?'پاسخ ناقص':'سرویس در دسترس نیست یا دسترسی API رد شد');
            $content.='<section class="bn-section"><div class="bn-sectionhead"><div><h2 class="bn-sectiontitle">'.Renderer::e($group['title']).'</h2><p class="bn-sectiondesc">'.Renderer::e($label).'</p></div><a class="bn-btn bn-btn--secondary" href="'.Renderer::e($group['url']).'">مشاهده جزئیات</a></div><div class="bn-stats">';
            foreach($group['metrics'] as $metric){
                $formatted=$metric['value']===null?'—':PersianDateTime::digits(number_format($metric['value']));
                $content.='<div class="bn-stat"><div class="bn-stat-label">'.Renderer::e($metric['label']).'</div><div class="bn-stat-value">'.Renderer::e($formatted).'</div></div>';
            }
            $content.='</div></section>';
        }
        $content.='<section class="bn-section"><p class="bn-sectiondesc">این گزارش فقط شاخص‌های موجود در API هر سرویس را نمایش می‌دهد؛ گزارش تفصیلی، تحلیل تاریخی و اطلاعات محرمانه کاربران در این خروجی قرار نمی‌گیرند.</p></section>';
        echo Renderer::page('گزارش مدیریتی',$content,$this->identity,'reports');
    }

    /** @param array<string,array{title:string,url:string,status:string,metrics:array<string,array{label:string,value:int|null}>}> $groups */
    public static function csv(array $groups,string $timestamp): string
    {
        $rows=[['سرویس','شاخص','مقدار','وضعیت منبع','زمان تهیه']];
        foreach($groups as $group){
            foreach($group['metrics'] as $metric){
                $rows[]=[$group['title'],$metric['label'],$metric['value']===null?'':(string)$metric['value'],$group['status'],$timestamp];
            }
        }
        $stream=fopen('php://temp','w+');if($stream===false)throw new \RuntimeException('CSV buffer unavailable');
        fwrite($stream,"\xEF\xBB\xBF");
        foreach($rows as $row){
            $sanitized=[];
            foreach($row as $field){
                $s=(string)$field;
                // Protect spreadsheet imports from formula injection; numeric metrics remain plain numbers.
                if(preg_match('/^[\s\x00-\x1f]*[=+@\-]/u',$s))$s="'".$s;
                $sanitized[]=$s;
            }
            fputcsv($stream,$sanitized,',','"','');
        }
        rewind($stream);$out=stream_get_contents($stream);fclose($stream);
        return $out===false?'':$out;
    }

    /** A CSV without one of its requested owner-service groups is an incomplete financial report. */
    public static function completeForExport(array $groups):bool
    {
        if($groups===[])return false;
        foreach($groups as $group){if(($group['status']??null)!=='ok')return false;}
        return true;
    }

    private function downloadCsv(array $groups): void
    {
        if(!self::completeForExport($groups)){
            http_response_code(503);
            header('Cache-Control: private, no-store, max-age=0');
            header('Retry-After: 60');
            echo Renderer::page('خروجی ناقص',
                '<section class="bn-section"><h1>گزارش قابل دانلود نیست</h1><p>پاسخ یکی از سرویس‌های مجاز ناقص یا نامعتبر است. برای جلوگیری از انتشار فایل ناقص، خروجی CSV تولید نشد.</p><a class="bn-btn bn-btn--secondary" href="/reports">بازگشت به گزارش‌ها</a></section>',
                $this->identity,'reports');
            return;
        }
        $csv=self::csv($groups,gmdate('Y-m-d H:i:s').' UTC');
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="noel-management-report-'.gmdate('Ymd-His').'.csv"');
        header('Cache-Control: no-store, private');
        header('X-Content-Type-Options: nosniff');
        echo $csv;
    }

    private function healthPage(): void
    {
        if (!in_array('super_admin',(array)($this->identity['roles']??[]),true)) {
            $this->forbidden();
            return;
        }
        $audit = new HealthAudit($this->config, $this->healthTransport);
        $results = $audit->probe();
        $rows = '';
        foreach ($results as $result) {
            $status = $result['state'];
            $style = $status==='healthy'?'bn-status--ok':($status==='reachable'?'bn-status--neutral':'bn-status--warn');
            $label = match($status){
                'healthy'=>'پاسخ سالم',
                'reachable'=>'در دسترس؛ وضعیت نامشخص',
                'degraded'=>'اختلال گزارش‌شده',
                'not_checked'=>'بررسی‌نشده؛ تنظیمات مقصد',
                default=>'در دسترس نیست',
            };
            $http = $result['http_status']===null?'—':(string)$result['http_status'];
            $duration = $result['elapsed_ms']===null?'—':(string)$result['elapsed_ms'].' ms';
            $slowThreshold=(int)($this->preferences?->get()['health_slow_threshold_ms']??1500);
            if($result['elapsed_ms']!==null && $result['elapsed_ms']>$slowThreshold && $status==='healthy'){$label='سالم، با پاسخ کند';$style='bn-status--warn';}
            $rows .= '<tr><td>'.Renderer::e($result['name']).'</td><td><span class="bn-status '.$style.'">'.Renderer::e($label).'</span></td><td>'.Renderer::e($http).'</td><td dir="ltr">'.Renderer::e($duration).'</td></tr>';
        }
        $content = '<div class="bn-pagehead"><div class="bn-pagehead-main"><div class="bn-eyebrow">پایش زیرساخت</div><h1 class="bn-title">وضعیت سرویس‌های نوئل</h1><p class="bn-subtitle">بررسی مستقیم و فقط‌خواندنی از مسیرهای سلامت؛ اطلاعات محرمانه یا جزئیات پاسخ API نمایش داده نمی‌شود.</p></div><div class="bn-actions"><a href="/system/settings" class="bn-btn bn-btn--secondary">بازگشت به تنظیمات</a></div></div>'
            . '<section class="bn-section"><div class="bn-table-wrap"><table class="bn-table"><thead><tr><th>سرویس</th><th>نتیجه</th><th>HTTP</th><th>زمان پاسخ</th></tr></thead><tbody>'.$rows.'</tbody></table></div></section>'
            . '<section class="bn-section"><p class="bn-sectiondesc">این نتیجه فقط دسترس‌پذیری مسیر سلامت را در لحظه بررسی نشان می‌دهد. پاسخ HTTP موفق به معنی تأیید عملکرد مالی، دیتابیس، امضای قرارداد یا ارتباط ارائه‌دهنده خارجی نیست. هیچ عملیات نوشتن یا تغییر تنظیمات انجام نمی‌شود.</p></section>';
        header('Cache-Control: private, no-store, max-age=0');
        echo Renderer::page('پایش سرویس‌ها',$content,$this->identity,'settings');
    }

    private function settings(string $method): void
    {
        if(!in_array('super_admin',(array)($this->identity['roles']??[]),true)){$this->forbidden();return;}
        $pref=$this->preferences;
        $message='';$failed=false;
        if($method==='POST'){
            if(!Csrf::valid(is_string($_POST['_csrf']??null)?$_POST['_csrf']:null)){
                http_response_code(403);$message='اعتبار فرم منقضی شده است.';$failed=true;
            }elseif($pref===null){
                http_response_code(503);$message='ذخیره تنظیمات عملیاتی در دسترس نیست.';$failed=true;
            }else{
                try{
                    $fields=[];
                    foreach(array_keys(OperationalPreferences::DEFAULTS) as $key)$fields[$key]=$_POST[$key]??'';
                    $revision=filter_var($_POST['revision']??null,FILTER_VALIDATE_INT);
                    if($revision===false)throw new \InvalidArgumentException('Invalid revision');
                    $actor=(string)($this->identity['user']['id']??'');
                    $pref->save($fields,(int)$revision,$actor);
                    header('Location: /system/settings?saved=1',true,303);return;
                }catch(\DomainException){http_response_code(409);$message='تنظیمات توسط مدیر دیگری تغییر کرده است؛ صفحه را بازخوانی کنید.';$failed=true;}
                catch(\InvalidArgumentException){http_response_code(422);$message='مقادیر تنظیمات معتبر نیستند.';$failed=true;}
                catch(\Throwable){http_response_code(503);$message='ذخیره امن تنظیمات ممکن نبود. سطح دسترسی پوشه storage را بررسی کنید.';$failed=true;}
            }
        }
        $values=OperationalPreferences::DEFAULTS+['revision'=>0];$events=[];
        try{if($pref!==null){$values=$pref->get();$events=$pref->history(10);}}catch(\Throwable){$message='خواندن تنظیمات ذخیره‌شده ممکن نیست.';$failed=true;}
        if(isset($_GET['saved'])&&$_GET['saved']==='1'&&!$failed)$message='تنظیمات عملیاتی ذخیره شد.';
        $entries=[
            ['هویت',$this->config->identityBaseUrl()],['تسهیلات',$this->config->loansBaseUrl()],
            ['مدارک',$this->config->documentsBaseUrl()],['همکاری',$this->config->partnersBaseUrl()],
            ['مالی',$this->config->financeBaseUrl()],['ارتباطات',$this->config->integrationsBaseUrl()],
        ];
        $rows='';foreach($entries as [$name,$url]){
            $safe=filter_var($url,FILTER_VALIDATE_URL)!==false&&str_starts_with(strtolower($url),'https://');
            $rows.='<tr><td>'.Renderer::e($name).'</td><td><code dir="ltr">'.Renderer::e($url).'</code></td><td>'.($safe?'HTTPS':'نیازمند بازبینی').'</td></tr>';
        }
        $integration=$this->integrations->health();
        $integrationOk=$integration->ok()&&(($integration->json['data']['status']??$integration->json['status']??null)==='ok');
        $serviceAuthWarning=strlen(trim($this->config->serviceAuthSecret()))<32
            ?'<div class="bn-alert bn-alert--warning" role="status" data-service-auth-warning><strong>کلید ارتباط سرویس‌ها در پنل تنظیم یا تکمیل نشده است.</strong><p>برای ارتباط معتبر قراردادها، بازار پرونده‌ها و ثبت اسناد، مقدار NOEEL_SERVICE_SHARED_SECRET باید در سرویس‌های همین محیط قوی و هماهنگ باشد. تنظیمات خصوصی را در محیط امن هر سرویس بررسی کنید؛ کلید را در گزارش یا پیام منتشر نکنید.</p></div>'
            :'';
        $content='<div class="bn-pagehead"><div class="bn-pagehead-main"><div class="bn-eyebrow">مدیریت زیرساخت</div><h1 class="bn-title">تنظیمات اتصال سرویس‌ها</h1><p class="bn-subtitle">نمای فقط‌خواندنی پیکربندی عمومی؛ کلیدها و رمزها نمایش داده نمی‌شوند.</p></div></div>'
            .$serviceAuthWarning
            .'<section class="bn-section"><div class="bn-detail-grid"><div class="bn-detail"><span>محیط</span><strong>'.Renderer::e($this->config->environment()).'</strong></div>'
            .'<div class="bn-detail"><span>آمادگی پیکربندی پایه</span><strong>'.($this->config->isReady()?'آماده':'نیازمند بررسی').'</strong></div>'
            .'<div class="bn-detail"><span>ارتباط Integrations</span><strong>'.($integrationOk?'در دسترس':'ناموفق').'</strong></div></div></section>'
            .'<section class="bn-section"><div class="bn-table-wrap"><table class="bn-table"><thead><tr><th>سرویس</th><th>آدرس مقصد</th><th>وضعیت TLS</th></tr></thead><tbody>'.$rows.'</tbody></table></div></section>'
            .'<section class="bn-section"><p><a class="bn-btn bn-btn--secondary" href="/system/health">بررسی وضعیت هفت سرویس</a> <a class="bn-btn bn-btn--secondary" href="/system/updates">مرکز آپدیت هفت سرویس</a></p></section>'
            .'<section class="bn-section"><p>تغییر تنظیمات محرمانه همچنان باید در محیط امن همان سرویس و با کنترل تغییر انجام شود. این صفحه امکان مشاهده یا تغییر رمزها، کلیدهای API یا تنظیمات پرداخت واقعی را ندارد.</p></section>';
        $content.='<section class="bn-section"><div class="bn-sectionhead"><h2 class="bn-sectiontitle">تنظیمات عملیاتی پنل مرکزی</h2></div><p class="bn-sectiondesc">این گزینه‌ها بر گزارش‌ها و پایش پنل اثر دارند؛ تنظیمات محرمانه، اختیار سرویس‌ها و وضعیت پرداخت را تغییر نمی‌دهند.</p>';
        if($message!=='')$content.='<div class="bn-alert '.($failed?'bn-alert--error':'bn-alert--success').'">'.Renderer::e($message).'</div>';
        $content.='<form method="post" action="/system/settings" class="bn-form"><input type="hidden" name="_csrf" value="'.Renderer::e(Csrf::token()).'"><input type="hidden" name="revision" value="'.(int)$values['revision'].'">';
        $options=['report_default_days'=>['title'=>'بازه پیش‌فرض گزارش‌های تاریخی','items'=>[7,14,30,60,90],'suffix'=>'روز'],'report_max_days'=>['title'=>'حداکثر بازه مجاز گزارش','items'=>[30,90,180],'suffix'=>'روز']];
        foreach($options as $key=>$meta){$content.='<div class="bn-field"><label class="bn-label" for="'.Renderer::e($key).'">'.Renderer::e($meta['title']).'</label><select class="bn-input" id="'.Renderer::e($key).'" name="'.Renderer::e($key).'">';foreach($meta['items'] as $item)$content.='<option value="'.$item.'"'.((int)$values[$key]===$item?' selected':'').'>'.$item.' '.Renderer::e($meta['suffix']).'</option>';$content.='</select></div>';}
        $content.='<div class="bn-field"><label class="bn-label" for="health_slow_threshold_ms">هشدار کندی پاسخ سرویس (میلی‌ثانیه)</label><input class="bn-input" id="health_slow_threshold_ms" name="health_slow_threshold_ms" type="number" min="500" max="10000" step="100" required value="'.(int)$values['health_slow_threshold_ms'].'"></div>';
        $content.='<div class="bn-form-actions"><button type="submit" class="bn-btn bn-btn--primary">ذخیره تنظیمات</button></div></form></section>';
        $content.='<section class="bn-section"><h2 class="bn-sectiontitle">تاریخچه تغییر تنظیمات</h2><div class="bn-table-wrap"><table class="bn-table"><thead><tr><th>زمان UTC</th><th>شناسه مدیر</th><th>نسخه</th></tr></thead><tbody>';
        foreach($events as $e)$content.='<tr><td dir="ltr">'.Renderer::e((string)($e['at']??'')).'</td><td dir="ltr">'.Renderer::e((string)($e['actor']??'')).'</td><td>'.(int)($e['current']['revision']??0).'</td></tr>';
        if(!$events)$content.='<tr><td colspan="3">هنوز تغییری ثبت نشده است.</td></tr>';
        $content.='</tbody></table></div></section>';
        echo Renderer::page('تنظیمات سرویس‌ها',$content,$this->identity,'settings');
    }

    /** Central directory only: updates still run within the owner service's secured updater. */
    private function updatesHub(): void
    {
        $roles=is_array($this->identity['roles']??null)?$this->identity['roles']:[];
        if(!in_array('super_admin',$roles,true)){$this->forbidden();return;}
        $services=[
            ['هویت', $this->config->identityBaseUrl(), 'Identity'],
            ['تسهیلات', $this->config->loansBaseUrl(), 'Loans'],
            ['مدارک', $this->config->documentsBaseUrl(), 'Documents'],
            ['همکاری', $this->config->partnersBaseUrl(), 'Partners'],
            ['مالی', $this->config->financeBaseUrl(), 'Finance'],
            ['ارتباطات', $this->config->integrationsBaseUrl(), 'Integrations'],
        ];
        $rows='';
        foreach($services as [$title,$base,$key]){
            $base=rtrim((string)$base,'/');
            $parts=parse_url($base);
            $allowed=is_array($parts)&&strtolower((string)($parts['scheme']??''))==='https'
               &&is_string($parts['host']??null)&&$parts['host']!==''
               &&!isset($parts['user'])&&!isset($parts['pass'])&&!isset($parts['query'])&&!isset($parts['fragment']);
            $link=$allowed?'<a class="bn-btn bn-btn--secondary" href="'.Renderer::e($base.'/update.php').'" rel="noopener noreferrer" target="_blank">باز کردن آپدیتر</a>':'<span class="bn-status bn-status--warn">آدرس نیازمند بررسی است</span>';
            $rows.='<tr><td>'.Renderer::e($title).'</td><td dir="ltr">'.Renderer::e($key).'</td><td>'.$link.'</td></tr>';
        }
        $rows.='<tr><td>پنل مرکزی</td><td dir="ltr">Panel</td><td><a class="bn-btn bn-btn--secondary" href="/update.php">باز کردن آپدیتر</a></td></tr>';
        $content='<div class="bn-pagehead"><div class="bn-pagehead-main"><div class="bn-eyebrow">مدیریت انتشار</div><h1 class="bn-title">مرکز آپدیت سرویس‌های نوئل</h1><p class="bn-subtitle">دسترسی یکجا به آپدیتر هفت سرویس؛ اعمال هر نسخه همچنان با ورود مدیر و اعتبارسنجی مستقل همان سرویس انجام می‌شود.</p></div></div>'
             .'<section class="bn-section"><div class="bn-table-wrap"><table class="bn-table"><thead><tr><th>سرویس</th><th>نام فنی</th><th>عملیات</th></tr></thead><tbody>'.$rows.'</tbody></table></div></section>'
             .'<div class="bn-alert bn-alert--hint">این صفحه هیچ کلید محرمانه‌ای را نمایش نمی‌دهد و آپدیت هم‌زمان یا خودکار سرویس‌ها را اجرا نمی‌کند. قبل از هر آپدیت، از دیتابیس همان سرویس بکاپ تهیه شود.</div>';
        echo Renderer::page('مرکز آپدیت سرویس‌ها',$content,$this->identity,'settings');
    }

    private function forbidden(): void
    {
        http_response_code(403);
        echo Renderer::page('دسترسی غیرمجاز','<section class="bn-section bn-panel-error"><h1>دسترسی به این گزارش مجاز نیست</h1><a class="bn-btn bn-btn--secondary" href="/dashboard">بازگشت</a></section>',$this->identity,'dashboard');
    }
}
