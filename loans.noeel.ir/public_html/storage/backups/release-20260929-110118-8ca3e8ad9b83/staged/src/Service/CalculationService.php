<?php
declare(strict_types=1);
namespace Babanoell\Loans\Service;
use Babanoell\Loans\Calculation\CalculatorRegistry;
use Babanoell\Loans\Repository\PdoLoanRepository;
use InvalidArgumentException;
final class CalculationService
{
    public function __construct(private readonly PdoLoanRepository $repo,private readonly CalculatorRegistry $calculators){}
    public function calculate(string $actor,string $planId,float $amount,int $installments,int $interval=1,?float $commission=null,bool $persist=true): array
    {
        $plan=$this->repo->getPlan($planId);if($plan===null)throw new InvalidArgumentException('طرح تسهیلات پیدا نشد.');if($plan['status']!=='active'&&$persist)throw new InvalidArgumentException('محاسبه عملیاتی فقط برای طرح فعال مجاز است.');$this->validateInput($plan,$amount,$installments,$interval);$result=$this->calculators->calculate((string)$plan['type'],$amount,$installments,$interval,$plan['settings'],$commission);if($result===[])throw new InvalidArgumentException('محاسبه با ورودی‌های فعلی قابل انجام نیست.');$base=['plan'=>['id'=>$plan['id'],'code'=>$plan['code'],'name'=>$plan['name'],'type'=>$plan['type'],'version'=>(int)$plan['current_version'],'version_id'=>$plan['current_version_id']],'result'=>$result];if(!$persist)return$base;return$base+['calculation'=>$this->repo->createCalculation($actor,$plan,$amount,$installments,$interval,$commission,$result)];
    }
    public function validateInput(array $plan,float $amount,int $installments,int $interval): void
    {
        $s=$plan['settings'];$min=(float)($s['min_amount']??0);$max=(float)($s['max_amount']??PHP_FLOAT_MAX);if($amount<$min||$amount>$max)throw new InvalidArgumentException('مبلغ خارج از بازه مجاز این طرح است.');$active=$s['active_installments']??[];if(is_string($active))$active=array_map('intval',preg_split('/\s*,\s*/',$active)?:[]);$active=array_map('intval',is_array($active)?$active:[]);if(!in_array($installments,$active,true))throw new InvalidArgumentException('تعداد اقساط برای این طرح فعال نیست.');if($plan['type']==='leasing'){$mi=(int)($s['min_interval']??1);$ma=(int)($s['max_interval']??1);if($interval<$mi||$interval>$ma)throw new InvalidArgumentException('فاصله اقساط خارج از بازه مجاز طرح است.');if($installments%$interval!==0)throw new InvalidArgumentException('تعداد اقساط باید بر فاصله زمانی اقساط بخش‌پذیر باشد.');}else{if($interval!==1)throw new InvalidArgumentException('برای طرح بازاری فاصله اقساط باید یک ماه باشد.');}
    }
}
