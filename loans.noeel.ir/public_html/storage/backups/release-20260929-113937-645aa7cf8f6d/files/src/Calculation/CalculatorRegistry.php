<?php
declare(strict_types=1);
namespace Babanoell\Loans\Calculation;
use InvalidArgumentException;
final class CalculatorRegistry
{
    private MarketLoanCalculator $market; private LeasingLoanCalculator $leasing;
    public function __construct(){ $this->market=new MarketLoanCalculator();$this->leasing=new LeasingLoanCalculator(); }
    public function calculate(string $type,float $amount,int $installments,int $interval,array $settings,?float $userCommission=null): array
    { return match($type){'market'=>$this->market->calculate($amount,$installments,$settings,$userCommission),'leasing'=>$this->leasing->calculate($amount,$installments,$interval,$settings,$userCommission),default=>throw new InvalidArgumentException('نوع طرح برای محاسبه پشتیبانی نمی‌شود.')}; }
}
