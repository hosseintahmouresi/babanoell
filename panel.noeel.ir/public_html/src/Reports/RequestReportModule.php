<?php
declare(strict_types=1);
namespace Babanoell\Panel\Reports;

use Babanoell\Panel\Http\LoansClient;
use Babanoell\Panel\Support\PersianDateTime;
use Babanoell\Panel\View\Renderer;

final class RequestReportModule
{
    public const STATUS=['draft'=>'پیش‌نویس','submitted'=>'ثبت شده','under_review'=>'در حال بررسی','need_correction'=>'نقص مدارک','expert_approved'=>'تأیید کارشناس','docs_completion'=>'تکمیل مدارک','docs_verified'=>'تأیید مدارک','rejected'=>'رد شده','granted'=>'اعطا شده','cancelled'=>'لغو شده','archived'=>'بایگانی'];
    public const SETTLEMENT=['not_started'=>'شروع نشده','awaiting_date'=>'منتظر تعیین تاریخ','pending'=>'در انتظار تسویه','settled'=>'تسویه شده'];
    private const CSV_COLUMNS=['request_no'=>'شماره پرونده','full_name'=>'نام متقاضی','national_code'=>'کد ملی','mobile_number'=>'موبایل','plan_name'=>'طرح','plan_type'=>'نوع طرح','requested_amount'=>'مبلغ خرید (ریال)','facility_amount'=>'تأمین بانک (ریال)','wallet_charge'=>'شارژ کیف پول (ریال)','acceptor_payment_amount'=>'سهم پذیرنده (ریال)','installment_count'=>'تعداد اقساط','installment_amount'=>'مبلغ قسط (ریال)','total_payment'=>'جمع بازپرداخت (ریال)','status'=>'وضعیت پرونده','settlement_status'=>'تسویه پذیرنده','settlement_date'=>'تاریخ تسویه','bank_settlement_status'=>'تسویه بانک','financier_payment_status'=>'پرداخت تأمین‌کننده','created_at'=>'تاریخ ثبت به وقت تهران'];
    public const FILTERS=['q','status','settlement_status','bank_settlement_status','financier_payment_status','date_from','date_to','plan_id','plan_type','assigned_bank_user_id','assigned_operations_user_id'];
    public function __construct(private readonly LoansClient $loans,private readonly string $bearer,private readonly array $identity){}
    public static function filters(array $input):array
    {
        $out=[];foreach(self::FILTERS as $key)if(isset($input[$key])){if(!is_scalar($input[$key])){$out[$key]=$input[$key];continue;}$v=trim((string)$input[$key]);if($v!=='')$out[$key]=$v;}return $out;
    }
    private static function select(string $key,string $label,array $options,array $filters,string $id):string
    {
        $html='<div class="bn-field"><label class="bn-label" for="'.$id.'">'.Renderer::e($label).'</label><select id="'.$id.'" name="'.$key.'"><option value="">همه</option>';
        foreach($options as $code=>$text)$html.='<option value="'.Renderer::e((string)$code).'"'.(($filters[$key]??'')===(string)$code?' selected':'').'>'.Renderer::e((string)$text).'</option>';
        return $html.'</select></div>';
    }
    public static function filterForm(string $path,array $filters,bool $advanced=false,array $plans=[]):string
    {
        foreach($filters as &$value)if(!is_scalar($value))$value='';unset($value);
        $id='filter-'.substr(hash('sha256',$path),0,8);$h='<section class="bn-section"><form class="bn-filterbar" method="get" action="'.Renderer::e($path).'">';
        $h.='<div class="bn-field bn-filter-search"><label class="bn-label" for="'.$id.'-q">جستجو</label><input id="'.$id.'-q" type="search" name="q" value="'.Renderer::e($filters['q']??'').'" placeholder="شماره پرونده، نام، کد ملی یا موبایل"></div>';
        $h.=self::select('status','وضعیت پرونده',self::STATUS,$filters,$id.'-status').self::select('settlement_status','تسویه پذیرنده',self::SETTLEMENT,$filters,$id.'-settlement');
        foreach(['date_from'=>'از تاریخ ثبت','date_to'=>'تا تاریخ ثبت'] as $key=>$label)$h.='<div class="bn-field"><label class="bn-label" for="'.$id.'-'.$key.'">'.$label.'</label><input id="'.$id.'-'.$key.'" name="'.$key.'" value="'.Renderer::e($filters[$key]??'').'" dir="ltr" inputmode="numeric" placeholder="۱۴۰۵/۰۱/۰۱" aria-describedby="'.$id.'-help"></div>';
        if($advanced){
            $open=array_intersect_key($filters,array_flip(['plan_type','bank_settlement_status','financier_payment_status','plan_id']))!==[];
            $h.='<details class="bn-report-advanced"'.($open?' open':'').'><summary>فیلترهای بیشتر</summary><div class="bn-grid bn-grid--2">';
            $h.=self::select('plan_type','نوع طرح',['market'=>'بازاری','leasing'=>'لیزینگ'],$filters,$id.'-type');
            $h.=self::select('bank_settlement_status','تسویه بانک',['not_recorded'=>'ثبت نشده','recorded'=>'ثبت شده','cancelled'=>'ابطال ثبت'],$filters,$id.'-bank');
            $h.=self::select('financier_payment_status','پرداخت تأمین‌کننده',['unreviewed'=>'بررسی نشده','paid'=>'پرداخت شده','not_paid'=>'عدم پرداخت'],$filters,$id.'-financier');
            if($plans!==[])$h.=self::select('plan_id','طرح',$plans,$filters,$id.'-plan');
            $h.='</div></details>';
        }else{
            foreach(array_diff(self::FILTERS,['q','status','settlement_status','date_from','date_to']) as $key)if(isset($filters[$key]))$h.='<input type="hidden" name="'.$key.'" value="'.Renderer::e($filters[$key]).'">';
        }
        $h.='<div class="bn-filter-actions"><button class="bn-btn bn-btn--primary" type="submit">نمایش</button><a class="bn-btn bn-btn--secondary" href="'.Renderer::e($path).'">پاک‌کردن</a>'.($path!=='/reports/requests'?'<a class="bn-btn bn-btn--secondary" href="/reports/requests?'.Renderer::e(http_build_query($filters)).'">گزارش و خروجی</a>':'').'</div><p id="'.$id.'-help" class="bn-sectiondesc">تاریخ شمسی یا میلادی؛ بازه شامل تمام روز پایان، به وقت تهران است.</p></form></section>';
        return $h;
    }
    public static function money(mixed $raw):string
    {
        $v=(string)$raw;if(!preg_match('/^(-?)([0-9]+)(?:\.([0-9]{1,2}))?$/D',$v,$m))return '—';
        $whole=preg_replace('/\B(?=(\d{3})+(?!\d))/u',',',$m[2]);$fraction=rtrim($m[3]??'','0');
        return PersianDateTime::digits($m[1].$whole.($fraction!==''?'.'.$fraction:''));
    }
    public static function validData(array $d,bool $export):bool
    {
        if(!is_array($d['items']??null)||!array_is_list($d['items'])||!is_int($d['total']??null)||$d['total']<count($d['items']))return false;
        foreach($d['items'] as $row){if(!is_array($row))return false;foreach(array_merge(['id'],array_keys(self::CSV_COLUMNS)) as $key)if(!array_key_exists($key,$row)||(!is_scalar($row[$key])&&$row[$key]!==null))return false;}
        if($export)return ($d['truncated']??null)===false&&$d['total']===count($d['items']);
        if(!is_array($d['totals']??null)||!is_array($d['statuses']??null)||!is_int($d['page']??null)||!is_int($d['per_page']??null)||$d['page']<1||$d['per_page']<1||$d['per_page']>100||($d['totals']['total']??null)!==$d['total'])return false;
        foreach(['requested_amount','facility_amount','wallet_charge'] as $key)if(!isset($d['totals'][$key])||!is_scalar($d['totals'][$key])||!preg_match('/^-?[0-9]+(?:\.[0-9]{1,2})?$/D',(string)$d['totals'][$key]))return false;
        if(!is_int($d['totals']['missing_facility_count']??null)||$d['totals']['missing_facility_count']<0||$d['totals']['missing_facility_count']>$d['total'])return false;
        $count=0;foreach($d['statuses'] as $row){if(!is_array($row)||!is_int($row['count']??null)||$row['count']<0)return false;$count+=$row['count'];}return $count===$d['total'];
    }

    public function handle(string $path,string $method):bool
    {
        if(!in_array($path,['/reports/requests','/reports/requests/export.csv'],true))return false;
        if($method!=='GET'){http_response_code(405);header('Allow: GET');return true;}
        if(!in_array('super_admin',(array)($this->identity['roles']??[]),true)&&!in_array('loans.requests.read',(array)($this->identity['permissions']??[]),true)){http_response_code(403);echo Renderer::page('دسترسی مجاز نیست','<div class="bn-alert bn-alert--error">اجازهٔ مشاهدهٔ گزارش پرونده‌ها را ندارید.</div>',$this->identity);return true;}
        $filters=self::filters($_GET);$export=str_ends_with($path,'export.csv');$query=$filters+['page'=>max(1,(int)($_GET['page']??1)),'per_page'=>25];
        $r=$this->loans->get('/api/v1/reports/requests'.($export?'/export':''),$this->bearer,$query);header('Cache-Control: no-store');
        if(!$r->ok()||!is_array($r->json['data']??null)){http_response_code($r->status>=400&&$r->status<600?$r->status:503);$message=(string)($r->json['error']['message']??'دریافت گزارش انجام نشد. دوباره تلاش کنید.');echo Renderer::page('گزارش پرونده‌ها','<div class="bn-alert bn-alert--error">'.Renderer::e($message).'</div>'.self::filterForm('/reports/requests',$filters,true),$this->identity,'request-reports');return true;}
        $d=$r->json['data'];if(!self::validData($d,$export)){http_response_code(503);echo Renderer::page('گزارش کامل دریافت نشد','<div class="bn-alert bn-alert--error">پاسخ کامل گزارش تأیید نشد. دوباره تلاش کنید.</div>',$this->identity);return true;}$plans=[];foreach($d['plans']??[] as $plan)$plans[$plan['id']]=$plan['name'];
        if($export){
            if(($d['truncated']??null)!==false||(int)($d['total']??-1)!==count($d['items']??[])){http_response_code(503);echo Renderer::page('گزارش کامل دریافت نشد','<div class="bn-alert bn-alert--error">خروجی کامل تأیید نشد. فیلتر دقیق‌تری انتخاب کنید.</div>',$this->identity);return true;}
            header('Content-Type: text/csv; charset=UTF-8');header('Content-Disposition: attachment; filename="noeel-request-report.csv"');echo self::csv($d['items']);return true;
        }
        $totals=$d['totals'];$h='<div class="bn-pagehead"><div class="bn-pagehead-main"><div class="bn-eyebrow">گزارش پرونده‌ها</div><h1 class="bn-title">گزارش تفصیلی تسهیلات</h1><p class="bn-subtitle">پرونده‌های مجاز حساب شما با جمع مبالغ همان فیلتر؛ واحد مبلغ‌ها ریال است.</p></div><div class="bn-actions"><a class="bn-btn bn-btn--secondary" href="/reports/requests/export.csv?'.Renderer::e(http_build_query($filters)).'">خروجی CSV</a></div></div>';
        $h.=self::filterForm('/reports/requests',$filters,true,$plans).'<section class="bn-stats bn-report-stats">';
        foreach(['total'=>'تعداد پرونده','requested_amount'=>'جمع مبلغ خرید','facility_amount'=>'جمع تأمین بانک','wallet_charge'=>'جمع شارژ کیف پول'] as $key=>$label){$value=$key==='facility_amount'&&(int)($totals['missing_facility_count']??0)>0?'—':self::money($totals[$key]??null);$h.='<div class="bn-stat"><div class="bn-stat-label">'.$label.'</div><div class="bn-stat-value'.(mb_strlen($value)>16?' bn-stat-value--compact':'').'">'.Renderer::e($value).'</div></div>';}$h.='</section>';
        $rows='';foreach($d['items'] as $x)$rows.='<tr><td><a class="bn-table-link" href="/loans/requests/'.Renderer::e($x['id']).'">'.Renderer::e($x['request_no']).'</a></td><td>'.Renderer::e((string)($x['full_name']??'—')).'</td><td>'.Renderer::e($x['plan_name']).'</td><td>'.Renderer::e(self::STATUS[$x['status']]??$x['status']).'</td><td>'.Renderer::e(self::SETTLEMENT[$x['settlement_status']]??$x['settlement_status']).'</td><td>'.Renderer::e(self::money($x['requested_amount'])).'</td><td>'.Renderer::e(self::money($x['facility_amount'])).'</td><td>'.Renderer::e(PersianDateTime::fromIso($x['created_at'])??'—').'</td></tr>';
        if($rows==='')$rows='<tr><td colspan="8"><div class="bn-empty-inline">پرونده‌ای مطابق فیلترها پیدا نشد.</div></td></tr>';
        $h.='<section class="bn-section"><div class="bn-table-wrap"><table class="bn-table bn-report-table"><thead><tr><th>شماره</th><th>متقاضی</th><th>طرح</th><th>وضعیت</th><th>تسویه پذیرنده</th><th>مبلغ خرید</th><th>تأمین بانک</th><th>تاریخ ثبت</th></tr></thead><tbody>'.$rows.'</tbody></table></div>';
        $page=(int)$d['page'];$last=max(1,(int)ceil((int)$d['total']/(int)$d['per_page']));$h.='<div class="bn-pagination">';if($page>1)$h.='<a class="bn-btn bn-btn--secondary" href="/reports/requests?'.Renderer::e(http_build_query($filters+['page'=>$page-1])).'">قبلی</a>';$h.='<span>'.PersianDateTime::digits((string)$page).' از '.PersianDateTime::digits((string)$last).'</span>';if($page<$last)$h.='<a class="bn-btn bn-btn--secondary" href="/reports/requests?'.Renderer::e(http_build_query($filters+['page'=>$page+1])).'">بعدی</a>';$h.='</div></section>';
        echo Renderer::page('گزارش پرونده‌ها',$h,$this->identity,'request-reports');return true;
    }
    public static function csv(array $items):string
    {
        $columns=self::CSV_COLUMNS;
        $f=fopen('php://temp','w+');fwrite($f,"\xEF\xBB\xBF");fputcsv($f,array_values($columns),',','"','');
        foreach($items as $item){$row=[];foreach($columns as $key=>$label){$v=(string)($item[$key]??'');if($key==='status')$v=self::STATUS[$v]??$v;if($key==='settlement_status')$v=self::SETTLEMENT[$v]??$v;if($key==='bank_settlement_status')$v=['not_recorded'=>'ثبت نشده','recorded'=>'ثبت شده','cancelled'=>'ابطال ثبت'][$v]??$v;if($key==='financier_payment_status')$v=['unreviewed'=>'بررسی نشده','paid'=>'پرداخت شده','not_paid'=>'عدم پرداخت'][$v]??$v;if($key==='plan_type')$v=['market'=>'بازاری','leasing'=>'لیزینگ'][$v]??$v;if($key==='settlement_date'&&$v!=='')$v=PersianDateTime::dateOnly($v)??'';if($key==='created_at')$v=PersianDateTime::fromIso($v)??'';if(!(in_array($key,['requested_amount','facility_amount','wallet_charge','acceptor_payment_amount','installment_count','installment_amount','total_payment'],true)&&preg_match('/^-?[0-9]+(?:\.[0-9]{1,2})?$/D',$v))&&preg_match('/^[\s\x00-\x1f]*[=+@\-]/u',$v))$v="'".$v;$row[]=$v;}fputcsv($f,$row,',','"','');}
        rewind($f);$out=stream_get_contents($f);fclose($f);return (string)$out;
    }
}
