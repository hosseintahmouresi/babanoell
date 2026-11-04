<?php
declare(strict_types=1);
namespace Babanoell\Panel\Reports;

use Babanoell\Panel\Http\LoansClient;
use Babanoell\Panel\Http\PartnersClient;
use Babanoell\Panel\Http\FinanceClient;
use Babanoell\Panel\System\OperationalPreferences;
use Babanoell\Panel\View\Renderer;
use DateTimeImmutable;
use DateTimeZone;

/** Aggregated historical counts read only through owner-service APIs. */
final class HistoricalReportsModule
{
    private const DEFINITIONS=[
        'loans'=>['title'=>'تسهیلات','keys'=>['requests_created'=>'پرونده‌های ثبت‌شده']],
        'partners'=>['title'=>'همکاری و قرارداد','keys'=>['cooperation_created'=>'درخواست همکاری','contracts_created'=>'قرارداد ایجادشده','contracts_signed'=>'امضای قرارداد']],
        'finance'=>['title'=>'مالی','keys'=>['cycles_created'=>'سیکل ایجادشده','cycles_closed'=>'سیکل بسته‌شده','receipts_submitted'=>'رسید ثبت‌شده','receipts_reviewed'=>'رسید بررسی‌شده']],
    ];
    public function __construct(
        private readonly LoansClient $loans,
        private readonly PartnersClient $partners,
        private readonly FinanceClient $finance,
        private readonly string $bearer,
        private readonly array $identity,
        private readonly OperationalPreferences $preferences,
    ){}
    public static function range(array $query,array $preferences): array
    {
        $today=new DateTimeImmutable('today',new DateTimeZone('UTC'));
        $default=max(1,(int)$preferences['report_default_days']);
        $from=(string)($query['from']??$today->modify('-'.($default-1).' days')->format('Y-m-d'));
        $to=(string)($query['to']??$today->format('Y-m-d'));
        foreach([$from,$to] as $date){
            if(!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$date))throw new \InvalidArgumentException('invalid_date');
            $d=DateTimeImmutable::createFromFormat('!Y-m-d',$date,new DateTimeZone('UTC'));
            if(!$d||$d->format('Y-m-d')!==$date)throw new \InvalidArgumentException('invalid_date');
        }
        $start=new DateTimeImmutable($from,new DateTimeZone('UTC'));
        $end=new DateTimeImmutable($to,new DateTimeZone('UTC'));
        if($start>$end||$end>$today||$start->diff($end)->days>=min(180,(int)$preferences['report_max_days']))throw new \InvalidArgumentException('invalid_range');
        return [$from,$to];
    }
    public function handle(string $path,string $method):bool
    {
        if(!in_array($path,['/reports/history','/reports/history/export.csv'],true))return false;
        if($method!=='GET'){header('Allow: GET');http_response_code(405);return true;}
        if(!in_array('super_admin',(array)($this->identity['roles']??[]),true)){
            http_response_code(403);
            echo Renderer::page('دسترسی غیرمجاز','<section class="bn-section"><p>این گزارش صرفاً برای مدیر ارشد در دسترس است.</p></section>',$this->identity,'reports');
            return true;
        }
        try{[$from,$to]=self::range($_GET,$this->preferences->get());}
        catch(\Throwable){http_response_code(422);echo Renderer::page('بازه نامعتبر','<section class="bn-section"><p>بازه تاریخ گزارش نامعتبر است یا از سقف مجاز عبور می‌کند.</p><a href="/reports/history">بازگشت</a></section>',$this->identity,'reports');return true;}
        $sources=[];
        foreach(['loans','partners','finance'] as $name){
            $response=match($name){
                'loans'=>$this->loans->get('/api/v1/admin/reports/daily',$this->bearer,['from'=>$from,'to'=>$to]),
                'partners'=>$this->partners->get('/api/v1/admin/reports/daily',$this->bearer,['from'=>$from,'to'=>$to]),
                'finance'=>$this->finance->get('/api/v1/admin/reports/daily',$this->bearer,['from'=>$from,'to'=>$to]),
            };
            $sources[$name]=self::parse($name,$response->status,$response->json,$from,$to);
        }
        if($path==='/reports/history/export.csv'){
            if(!self::complete($sources)){
                http_response_code(503);
                header('Cache-Control: private, no-store, max-age=0');
                header('Retry-After: 60');
                echo Renderer::page('گزارش ناقص',
                    '<section class="bn-section"><h1>دریافت گزارش کامل ممکن نیست</h1><p>دست‌کم یکی از سرویس‌های منبع پاسخ معتبر نداده است. برای جلوگیری از استفاده از گزارش ناقص، فایل CSV ایجاد نشد.</p><a href="/reports/history" class="bn-btn bn-btn--secondary">بازگشت به گزارش‌ها</a></section>',
                    $this->identity,'reports-history');
                return true;
            }
            $this->csv($sources,$from,$to);return true;
        }
        $this->page($sources,$from,$to);return true;
    }
    public static function parse(string $name,int $status,?array $json,string $from,string $to): array
    {
        $empty=['state'=>'unavailable','metrics'=>[]];
        if(!isset(self::DEFINITIONS[$name])||$status<200||$status>=300)return $empty;
        $payload=$json['data']??null;
        if(!is_array($payload)||($payload['from']??null)!==$from||($payload['to']??null)!==$to||!is_array($payload['series']??null))return $empty;
        $metrics=[];
        foreach(self::DEFINITIONS[$name]['keys'] as $key=>$label){
            if(!isset($payload['series'][$key])||!is_array($payload['series'][$key]))return $empty;
            $points=[];$total=0;
            foreach($payload['series'][$key] as $point){
                if(!is_array($point)||!is_string($point['day']??null)||!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$point['day'])||$point['day']<$from||$point['day']>$to||!is_int($point['count']??null)||$point['count']<0)return $empty;
                if(isset($points[$point['day']]))return $empty;
                $points[$point['day']]=$point['count'];$total+=$point['count'];
            }
            $metrics[$key]=['label'=>$label,'points'=>$points,'total'=>$total];
        }
        return ['state'=>'ok','metrics'=>$metrics];
    }
    private function page(array $sources,string $from,string $to):void
    {
        $content='<div class="bn-pagehead"><div class="bn-pagehead-main"><div class="bn-eyebrow">تحلیل تاریخی</div><h1 class="bn-title">گزارش رویدادهای سامانه</h1><p class="bn-subtitle">تعداد رخدادها بر اساس تاریخ ذخیره‌شده در دیتابیس هر سرویس؛ بدون انتشار اطلاعات اشخاص یا اتصال مستقیم دیتابیس‌ها.</p></div><div class="bn-actions"><a class="bn-btn bn-btn--secondary" href="/reports">گزارش کلی</a></div></div>';
        $content.='<section class="bn-section"><form method="get" action="/reports/history" class="bn-detail-grid"><div class="bn-field"><label class="bn-label" for="from">از تاریخ (میلادی)</label><input class="bn-input" id="from" name="from" type="date" value="'.Renderer::e($from).'" required></div><div class="bn-field"><label class="bn-label" for="to">تا تاریخ (میلادی)</label><input class="bn-input" id="to" name="to" type="date" value="'.Renderer::e($to).'" required></div><div class="bn-form-actions"><button class="bn-btn bn-btn--primary" type="submit">نمایش گزارش</button></div></form></section>';
        $missing=[];
        foreach($sources as $name=>$source){
            $def=self::DEFINITIONS[$name];
            if($source['state']!=='ok'){$missing[]=$def['title'];$content.='<section class="bn-section"><h2 class="bn-sectiontitle">'.Renderer::e($def['title']).'</h2><p class="bn-alert bn-alert--warning">پاسخ این سرویس معتبر نیست یا در دسترس قرار ندارد؛ هیچ عددی به جای نتیجه واقعی نمایش داده نمی‌شود.</p></section>';continue;}
            $content.='<section class="bn-section"><div class="bn-sectionhead"><h2 class="bn-sectiontitle">'.Renderer::e($def['title']).'</h2></div><div class="bn-stats">';
            foreach($source['metrics'] as $metric)$content.='<div class="bn-stat"><div class="bn-stat-label">'.Renderer::e($metric['label']).'</div><div class="bn-stat-value">'.Renderer::e(number_format($metric['total'])).'</div></div>';
            $content.='</div></section>';
        }
        $content.='<section class="bn-section"><h2 class="bn-sectiontitle">جزئیات روزانه</h2><div class="bn-table-wrap"><table class="bn-table"><thead><tr><th>تاریخ ثبت</th><th>سرویس</th><th>شاخص</th><th>تعداد</th></tr></thead><tbody>';
        foreach(self::rows($sources,$from,$to) as $row)$content.='<tr><td dir="ltr">'.Renderer::e($row[0]).'</td><td>'.Renderer::e($row[1]).'</td><td>'.Renderer::e($row[2]).'</td><td>'.Renderer::e($row[3]).'</td></tr>';
        $content.='</tbody></table></div><div class="bn-actions"><a class="bn-btn bn-btn--secondary" href="/reports/history/export.csv?from='.rawurlencode($from).'&amp;to='.rawurlencode($to).'">خروجی کامل CSV</a></div></section>';
        if($missing)$content.='<p class="bn-sectiondesc">هشدار: گزارش ناقص است؛ منابع ناموفق: '.Renderer::e(implode('، ',$missing)).'.</p>';
        header('Cache-Control: private, no-store, max-age=0');
        echo Renderer::page('گزارش تاریخی',$content,$this->identity,'reports-history');
    }
    /** Incomplete or missing owner-service data must never be exported as a complete report. */
    public static function complete(array $sources):bool
    {
        foreach(array_keys(self::DEFINITIONS) as $owner){
            if(($sources[$owner]['state']??null)!=='ok')return false;
        }
        return true;
    }
    public static function rows(array $sources,string $from,string $to):array
    {
        $rows=[];$start=new DateTimeImmutable($from,new DateTimeZone('UTC'));$end=new DateTimeImmutable($to,new DateTimeZone('UTC'));
        foreach($sources as $name=>$source){
            if(($source['state']??null)!=='ok')continue;
            $day=$start;
            while($day<=$end){
                $stamp=$day->format('Y-m-d');
                foreach($source['metrics'] as $metric){$rows[]=[$stamp,self::DEFINITIONS[$name]['title'],$metric['label'],(string)($metric['points'][$stamp]??0)];}
                $day=$day->modify('+1 day');
            }
        }
        return $rows;
    }
    private function csv(array $sources,string $from,string $to):void
    {
        $stream=fopen('php://temp','w+');if($stream===false){http_response_code(500);return;}
        fwrite($stream,"\xEF\xBB\xBF");fputcsv($stream,['تاریخ ثبت','سرویس','شاخص','تعداد'],',','"','');
        foreach(self::rows($sources,$from,$to) as $row){
            // Values originate only from fixed labels, server-generated dates and validated nonnegative integers.
            fputcsv($stream,$row,',','"','');
        }
        rewind($stream);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="noel-history-'.$from.'-'.$to.'.csv"');
        header('Cache-Control: private, no-store, max-age=0');
        header('X-Content-Type-Options: nosniff');
        fpassthru($stream);fclose($stream);
    }
}
