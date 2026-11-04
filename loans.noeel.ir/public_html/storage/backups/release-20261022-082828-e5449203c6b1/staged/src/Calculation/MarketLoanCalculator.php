<?php
declare(strict_types=1);
namespace Babanoell\Loans\Calculation;
final class MarketLoanCalculator
{
    /** Exact port of legacy calculateMarketLoan(). */
    public function calculate(float $amount,int $installments,array $settings,?float $userCommission=null): array
    {
        if($amount<=0||$installments<=0)return[];$r=(float)($settings['fixed_monthly_rate']??0)/100;$pre=(float)($settings['prepayment_percent']??0)/100;$supplier=(float)($settings['supplier_costs_percent']??0)/100;$inter=(float)($settings['intermediary_deductions_percent']??0)/100;$baba=(float)($settings['babanovel_deductions_percent']??($settings['babanoell_deductions_percent']??0))/100;$ins=(float)($settings['insurance_percent']??0)/100;$guarantee=(float)($settings['guarantee_check_percent']??0)/100;$commission=$userCommission!==null?$userCommission/100:(float)($settings['acceptor_commission_percent']??0)/100;$additional=(float)($settings['additional_deductions_percent']??0)/100;$extra=(float)($settings['acceptor_deductions_amount']??0);$vat=(float)($settings['vat_percent']??0)/100;
        $c=$amount*$pre;$f=($amount-$c)*$r*$installments;$d=($amount*(1-$pre))+$f;$e=$d/$installments;$g=$amount*($supplier+$inter+$baba+$ins)*(1+$vat);$h=$amount-$c;$i=($amount*(1-($inter+$baba+$ins+$additional+$pre+(($supplier+$commission)*(1+$vat)))))-$extra;$j=$amount*$guarantee;$k=($d+$c)*$guarantee;
        return['loanAmount'=>round($amount),'installmentCount'=>$installments,'prepayment'=>round($c),'totalPayment'=>round($d),'installmentAmount'=>round($e),'profit'=>round($f),'operationCost'=>round($g),'walletCharge'=>round($h),'acceptorPayment'=>round($i),'guaranteeCheckPrincipal'=>round($j),'guaranteeCheckTotal'=>round($k)];
    }
}
