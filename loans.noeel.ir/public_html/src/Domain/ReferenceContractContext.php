<?php
declare(strict_types=1);
namespace Babanoell\Loans\Domain;

use Babanoell\Loans\Support\PersianDate;
use DateTimeImmutable;
use DateTimeZone;

/** Reference loan-contract-signing.php map. Partners escapes every replacement. */
final class ReferenceContractContext
{
    public static function expand(array $loan, ?DateTimeImmutable $now=null): array
    {
        $rules=is_array($loan['plan_rules']??null)?$loan['plan_rules']:[];
        $settings=is_array($loan['plan_settings']??null)?$loan['plan_settings']:[];
        $start=($now??new DateTimeImmutable('now',new DateTimeZone('Asia/Tehran')))->setTimezone(new DateTimeZone('Asia/Tehran'));
        $term=max(1,(int)($loan['installment_count']??1));$interval=max(1,(int)($loan['installment_interval']??1));
        $retirees=($rules['facility_agreement_template_code']??'')==='hami_retirees_reference';
        $end=$retirees?PersianDate::addMonths(self::display($start->format('Y-m-d')),$term):self::display($start->modify('+'.$term.' months')->format('Y-m-d'));
        $purchase=(float)($loan['requested_amount']??0);$facility=(float)($loan['facility_amount']??0);if($facility<=0)$facility=$purchase;
        $installment=(float)($loan['installment_amount']??0);$count=max(1,(int)round($term/$interval));
        $total=(float)($loan['total_payment']??0);if($total<=0)$total=$installment*$count;
        $price=$total>0?$total:((float)($loan['order_total']??0)?:$purchase);
        $rate=(float)($settings['interest_rate']??0);if($rate<=0)$rate=26.0;
        $number=(string)($loan['request_no']??$loan['id']);$ctx=[];
        foreach(['full_name','father_name','certificate_number','certificate_city','national_code','mobile_number','phone_number','residential_address','postal_code','retirement_code','retirement_organization','product_name'] as $key)$ctx[$key]=(string)($loan[$key]??'');
        $occupation=PlanRules::OCCUPATIONS[(string)($loan['occupation']??'')]??(string)($loan['occupation']??'');
        $ctx+=['contract_number'=>$number,'occupation'=>$occupation,'work_address'=>trim((string)($loan['work_address']??''))?:$occupation,
            'birth_date'=>self::display((string)($loan['birth_date']??'')),'created_at'=>self::display((string)($loan['created_at']??'')),
            'invoice_number'=>$number,'invoice_date'=>self::display((string)($loan['created_at']??''))?:self::display($start->format('Y-m-d')),
            'contract_date'=>self::display($start->format('Y-m-d')),'contract_start_date'=>self::display($start->format('Y-m-d')),
            'contract_end_date'=>$end,'contract_duration_months'=>(string)$term,'installment_count'=>(string)$term,
            'installment_term_months'=>(string)$term,'payment_count'=>(string)$count,'installment_interval'=>(string)$interval,
            'first_due_date'=>self::firstDue($start,$interval,(string)($rules['contract_first_due_rule']??'interval')),
            'delivery_date'=>self::display((string)($loan['delivery_date']??''))?:self::display($start->format('Y-m-d')),
            'annual_interest_rate'=>rtrim(rtrim(number_format($rate,2,'.',''),'0'),'.')];
        foreach(['loan_amount'=>$purchase,'facility_amount'=>$facility,'installment_amount'=>$installment,'total_payment'=>$total,
            'prepayment'=>(float)($loan['prepayment']??0),'guarantee_check_principal'=>(float)($loan['guarantee_check_principal']??0),
            'guarantee_check_total'=>(float)($loan['guarantee_check_total']??0),'total_price'=>$price,
            'first_installment_amount'=>$installment,'remaining_installment_amount'=>$installment,'cash_price'=>$facility,
            'installment_sale_price'=>$price,'marketing_development_amount'=>(float)($rules['contract_marketing_development_amount']??0)] as $key=>$value){
            $ctx[$key]=number_format($value);$ctx[$key.'_words']=PersianMoneyWords::convert($value);
        }
        return $ctx;
    }

    private static function display(string $date): string
    {
        return trim($date)===''?'':PersianDate::isoToJalali($date);
    }

    public static function firstDue(DateTimeImmutable $start,int $interval,string $rule): string
    {
        if($rule!=='jalali_month_end_half')return self::display($start->modify('+'.max(1,$interval).' months')->format('Y-m-d'));
        [$y,$m,$d]=PersianDate::parseJalali(self::display($start->format('Y-m-d')));$first=sprintf('%04d/%02d/01',$y,$m);
        if($d>=16)$first=PersianDate::addMonths($first,1);
        $next=PersianDate::jalaliToIso(PersianDate::addMonths($first,1));
        return self::display((new DateTimeImmutable($next))->modify('-1 day')->format('Y-m-d'));
    }
}
