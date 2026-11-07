<?php
declare(strict_types=1);
namespace Babanoell\Panel\Credit;

use Babanoell\Panel\Support\PersianDateTime;
use Babanoell\Panel\View\Renderer;

/** Human-readable fields from the reference report; absent data is never described as zero debt. */
final class CreditReportView
{
    public static function render(array $evidence,string $download): string
    {
        $data=$evidence['report']??[];$score=$data['score']??[];$body=$data['report']??[];
        $person=$score['personInformation']??$body['personInformation']??[];
        $summary=$body['summaryInformationRelatedToContractsSum'][0]??[];
        $html='<section class="bn-section"><div class="bn-sectionhead"><div><h2 class="bn-sectiontitle">گزارش اعتبارسنجی</h2><p class="bn-sectiondesc">شناسهٔ '.self::e($evidence['report_code']??null).' · '.self::e(PersianDateTime::fromIso((string)($evidence['verified_at']??''),false)).'</p></div><a class="bn-btn bn-btn--secondary" href="'.Renderer::e($download).'">دریافت فایل گزارش</a></div>';
        $html.='<div class="bn-stats"><div class="bn-stat bn-stat--accent"><div class="bn-stat-label">امتیاز اعتباری</div><div class="bn-stat-value">'.self::e($score['score']??null).'</div></div><div class="bn-stat"><div class="bn-stat-label">ریسک</div><div class="bn-stat-value">'.self::e($score['risk']??null).'</div></div></div>';
        if(is_string($score['description']??null))$html.='<p>'.self::e($score['description']).'</p>';
        $html.='<h3 class="bn-sectiontitle">اطلاعات هویتی</h3><div class="bn-detail-grid">'
            .self::detail('نام و نام خانوادگی',trim(self::text($person['name']??$person['firstName']??'').' '.self::text($person['family']??$person['lastName']??'')))
            .self::detail('کد ملی',$person['nationalCode']??null).self::detail('نام پدر',$person['fatherName']??null)
            .self::detail('تاریخ تولد',$person['dateOfBirth']??$person['birthDate']??null).'</div>';
        $cheques=$data['bouncedCheque']['bouncedCheques']??null;
        $html.='<h3 class="bn-sectiontitle">خلاصه وضعیت مالی</h3><div class="bn-detail-grid">'
            .self::detail('تسهیلات جاری',$summary['inProgressContractsCount']??null)
            .self::detail('معوقات (ریال)',self::number($summary['overdueAmount']??null))
            .self::detail('کل تسهیلات (ریال)',self::number($summary['totalAmount']??null))
            .self::detail('تعداد چک برگشتی',is_array($cheques)?count($cheques):null).'</div>';
        foreach(['directContracts'=>'تسهیلات بانکی','indirectContracts'=>'تعهدات و ضمانت‌ها'] as $key=>$title){
            $html.=self::table($title,$body[$key]??null,['organizationName'=>'بانک','typeOfFinancingTitle'=>'نوع','negativeContractStatus'=>'وضعیت','totalAmount'=>'مبلغ کل (ریال)','overdueAmount'=>'معوقات (ریال)','startDate'=>'شروع','expectedEndDate'=>'سررسید'],['totalAmount','overdueAmount']);
        }
        $html.=self::table('چک‌های صیادی (پیچک)',$data['pichak']['personChequesData']['personChequeItems']??null,['checkStatusTitle'=>'وضعیت','count'=>'تعداد','totalAmount'=>'مبلغ کل (ریال)'],['totalAmount']);
        $html.=self::table('سوابق چک برگشتی',$cheques,['organizationName'=>'بانک','amount'=>'مبلغ (ریال)','bouncedDate'=>'تاریخ برگشت','clearedDate'=>'رفع سوءاثر'],['amount']);
        $html.=self::table('اطلاعات مالیاتی',$data['tax']['taxResult']??null,['year'=>'سال','daramadEbrazi'=>'درآمد ابرازی (ریال)','maliatGhati'=>'مالیات قطعی (ریال)','bedehiMaliatAmalkard'=>'بدهی (ریال)'],['daramadEbrazi','maliatGhati','bedehiMaliatAmalkard']);
        $judiciary=$data['judiciary']['judiciaryData']??null;
        $html.='<h3 class="bn-sectiontitle">پرونده‌های قضایی</h3>';
        if(is_array($judiciary)&&$judiciary!==[])$html.='<div class="bn-detail-grid">'.self::detail('اجراشده',$judiciary['sumExecuteCount']??null).self::detail('اجرانشده',$judiciary['sumNotExecuteCount']??null).self::detail('محکومیت اجرا نشده (ریال)',self::number($judiciary['sumNotExecuteSumPenaltyAmount']??null)).'</div>';
        else $html.='<p class="bn-helper">اطلاعات این بخش در گزارش موجود نیست.</p>';
        return $html.'<p class="bn-helper">تصمیم تسهیلات طبق قواعد طرح و بررسی مسئول پرونده انجام می‌شود. «اطلاعات موجود نیست» به معنی نداشتن بدهی نیست.</p><details><summary>مشخصات صحت گزارش</summary><p class="bn-code" dir="ltr">SHA-256: '.Renderer::e((string)($evidence['sha256']??'')).'</p></details></section>';
    }

    private static function table(string $title,mixed $rows,array $columns,array $amounts=[]): string
    {
        $html='<h3 class="bn-sectiontitle">'.$title.'</h3>';
        if(!is_array($rows))return $html.'<p class="bn-helper">اطلاعات این بخش در گزارش موجود نیست.</p>';
        if($rows===[])return $html.'<p class="bn-helper">موردی در گزارش ثبت نشده است.</p>';
        $html.='<div class="bn-table-wrap"><table class="bn-table"><thead><tr>';
        foreach($columns as $label)$html.='<th scope="col">'.$label.'</th>';
        $html.='</tr></thead><tbody>';
        foreach($rows as $row){
            if(!is_array($row))continue;
            $html.='<tr>';foreach($columns as $key=>$label)$html.='<td>'.self::e(in_array($key,$amounts,true)?self::number($row[$key]??null):($row[$key]??null)).'</td>';
            $html.='</tr>';
        }
        return $html.'</tbody></table></div>';
    }
    private static function text(mixed $value): string {return is_scalar($value)&&!is_bool($value)?trim((string)$value):'';}
    private static function e(mixed $value): string {$text=self::text($value);return Renderer::e(PersianDateTime::digits($text!==''?$text:'—'));}
    private static function detail(string $label,mixed $value): string {return '<div class="bn-detail-item"><span>'.$label.'</span><strong>'.self::e($value).'</strong></div>';}
    private static function number(mixed $value): ?string
    {
        $text=self::text($value);if(!preg_match('/^(-?)([0-9]+)(\.[0-9]+)?$/D',$text,$m))return null;
        return $m[1].preg_replace('/\B(?=(\d{3})+(?!\d))/u',',',$m[2]).($m[3]??'');
    }
}
