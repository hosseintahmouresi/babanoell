<?php
declare(strict_types=1);
namespace Babanoell\Loans\Domain;
use Babanoell\Loans\Support\PersianDate; use InvalidArgumentException;
final class ScheduleGenerator
{
    public function generate(string $type,array $result,int $installmentInterval,string $firstDueJalali): array
    {
        $interval=max(1,$installmentInterval);$count=$type==='leasing'?(float)($result['numberOfInstallments']??0):(float)($result['installmentCount']??0);if($count<=0||abs($count-round($count))>0.000001)throw new InvalidArgumentException('تعداد واقعی سررسیدها باید عدد صحیح باشد. فاصله اقساط را بررسی کنید.');$count=(int)round($count);$amount=(int)round((float)($result['installmentAmount']??0));$total=(int)round((float)($result['totalPayment']??($amount*$count)));$profit=(int)round((float)($result['profit']??0));$principal=$total-$profit;$rows=[];$paid=0;$profitPaid=0;
        for($i=1;$i<=$count;$i++){ $dueJ=$i===1?$firstDueJalali:PersianDate::addMonths($firstDueJalali,($i-1)*$interval);$dueIso=PersianDate::jalaliToIso($dueJ);$rowAmount=$i===$count?$total-$paid:$amount;$pProfit=$i===$count?$profit-$profitPaid:(int)round($profit/$count);$pPrincipal=$rowAmount-$pProfit;$paid+=$rowAmount;$profitPaid+=$pProfit;$rows[]=['sequence'=>$i,'due_date'=>$dueIso,'due_date_jalali'=>$dueJ,'amount'=>$rowAmount,'principal_amount'=>$pPrincipal,'profit_amount'=>$pProfit]; }
        return$rows;
    }
}
