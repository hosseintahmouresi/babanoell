<?php
declare(strict_types=1);
namespace Babanoell\Loans\Domain;

use InvalidArgumentException;

final class RequiredDocumentCatalog
{
    /** @return array<string,string> */
    public static function all(): array
    {
        return [
            'national_card'=>'کارت ملی',
            'birth_certificate'=>'شناسنامه',
            'portrait'=>'تصویر چهره',
            'employment_certificate'=>'گواهی اشتغال',
            'business_license'=>'جواز کسب',
            'payslip'=>'فیش حقوقی',
            'bank_statement'=>'گردش حساب',
            'tax_return'=>'اظهارنامه مالیاتی',
            'primary_check'=>'چک اصلی',
            'secondary_check'=>'چک تکمیلی',
            'guarantor_identity'=>'مدارک هویتی ضامن',
            'guarantor_income'=>'مدارک درآمدی ضامن',
            'credit_report'=>'گزارش اعتبارسنجی',
            'contract'=>'قرارداد',
            'invoice'=>'فاکتور / پیش‌فاکتور',
            'supplemental'=>'مدارک تکمیلی',
            'case_receipt'=>'رسید پرونده',
            'settlement_receipt'=>'رسید تسویه',
        ];
    }

    /** @param array<int,mixed> $items @return array<int,string> */
    public static function normalize(array $items): array
    {
        $known=self::all();$out=[];
        foreach($items as$item){
            $code=is_array($item)?trim((string)($item['code']??'')):trim((string)$item);
            if($code==='')continue;
            if(!isset($known[$code]))throw new InvalidArgumentException('نوع مدرک الزامی معتبر نیست: '.$code);
            $out[$code]=true;
        }
        return array_keys($out);
    }
}
