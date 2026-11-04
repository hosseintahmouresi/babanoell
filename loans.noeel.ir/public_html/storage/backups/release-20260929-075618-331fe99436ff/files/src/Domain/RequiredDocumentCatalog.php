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
            'national_card_front'=>'کارت ملی (رو)',
            'national_card_back'=>'کارت ملی (پشت)',
            'birth_certificate'=>'شناسنامه',
            'portrait'=>'تصویر چهره',
            'sana_certificate'=>'گواهی ثبت ثنا',
            'employment_certificate'=>'گواهی اشتغال',
            'business_license'=>'جواز کسب',
            'activity_license'=>'مجوز فعالیت',
            'payslip'=>'فیش حقوقی',
            'bank_statement'=>'گردش حساب',
            'tax_return'=>'اظهارنامه / اطلاعات مالیاتی',
            'residence_document'=>'سند یا اجاره‌نامه محل سکونت',
            'workplace_document'=>'سند یا اجاره‌نامه محل کار',
            'signature_certificate'=>'گواهی امضا',
            'primary_check'=>'چک اصلی',
            'secondary_check'=>'چک تکمیلی',
            'blank_check_image'=>'تصویر چک صیادی',
            'guarantor_identity'=>'مدارک هویتی ضامن',
            'guarantor_income'=>'مدارک درآمدی ضامن',
            'guarantor_check'=>'چک ضامن',
            'credit_report'=>'گزارش اعتبارسنجی',
            'bank_validation'=>'فایل اعتبارسنجی بانک',
            'contract'=>'قرارداد',
            'signed_contract'=>'قرارداد امضا شده',
            'invoice'=>'فاکتور / پیش‌فاکتور',
            'supplemental'=>'مدارک تکمیلی',
            'completed_check'=>'چک تکمیل شده',
            'case_receipt'=>'رسید واریزی پرونده',
            'transfer_receipt'=>'رسید انتقال وجه',
            'settlement_receipt'=>'رسید سیکل تسویه',
            'bank_settlement_receipt'=>'رسید تسویه بانک با بابانوئل',
            'cooperation_identity'=>'مدارک هویتی همکاری',
            'cooperation_license'=>'مجوز همکاری / فعالیت',
            'cooperation_contract'=>'قرارداد همکاری',
        ];
    }
    /** @param array<int,mixed> $items @return array<int,string> */
    public static function normalize(array $items): array
    {
        $known=self::all();$out=[];
        foreach($items as$item){$code=is_array($item)?trim((string)($item['code']??'')):trim((string)$item);if($code==='')continue;if(!isset($known[$code]))throw new InvalidArgumentException('نوع مدرک الزامی معتبر نیست: '.$code);$out[$code]=true;}
        return array_keys($out);
    }
}
