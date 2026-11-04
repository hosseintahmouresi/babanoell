<?php
declare(strict_types=1);
namespace Babanoell\Documents\Domain;
use InvalidArgumentException;

final class DocumentCatalog
{
    /** @return array<string,array{label:string,category:string,visibility:string}> */
    public static function all(): array
    {
        return [
            'national_card'=>['label'=>'کارت ملی','category'=>'identity','visibility'=>'confidential'],
            'birth_certificate'=>['label'=>'شناسنامه','category'=>'identity','visibility'=>'confidential'],
            'portrait'=>['label'=>'تصویر چهره','category'=>'identity','visibility'=>'confidential'],
            'employment_certificate'=>['label'=>'گواهی اشتغال','category'=>'employment','visibility'=>'confidential'],
            'business_license'=>['label'=>'جواز کسب','category'=>'employment','visibility'=>'confidential'],
            'payslip'=>['label'=>'فیش حقوقی','category'=>'financial','visibility'=>'confidential'],
            'bank_statement'=>['label'=>'گردش حساب','category'=>'financial','visibility'=>'confidential'],
            'tax_return'=>['label'=>'اظهارنامه مالیاتی','category'=>'financial','visibility'=>'confidential'],
            'primary_check'=>['label'=>'چک اصل','category'=>'check_primary','visibility'=>'confidential'],
            'secondary_check'=>['label'=>'چک فرع','category'=>'check_secondary','visibility'=>'confidential'],
            'guarantor_identity'=>['label'=>'مدارک هویتی ضامن','category'=>'guarantor','visibility'=>'confidential'],
            'guarantor_income'=>['label'=>'مدارک درآمدی ضامن','category'=>'guarantor','visibility'=>'confidential'],
            'credit_report'=>['label'=>'فایل اعتبارسنجی','category'=>'credit_check','visibility'=>'confidential'],
            'contract'=>['label'=>'قرارداد','category'=>'contract','visibility'=>'confidential'],
            'invoice'=>['label'=>'فاکتور / پیش‌فاکتور','category'=>'invoice','visibility'=>'normal'],
            'supplemental'=>['label'=>'مدرک تکمیلی','category'=>'supplemental','visibility'=>'confidential'],
            'case_receipt'=>['label'=>'رسید واریزی پرونده','category'=>'case_receipt','visibility'=>'confidential'],
            'settlement_receipt'=>['label'=>'رسید سیکل تسویه','category'=>'settlement_receipt','visibility'=>'confidential'],
        ];
    }
    public static function get(string$type): array
    {
        $type=strtolower(trim($type));$item=self::all()[$type]??null;
        if(!is_array($item))throw new InvalidArgumentException('نوع مدرک انتخاب‌شده معتبر نیست.');
        return ['code'=>$type]+$item;
    }
    /** @return list<string> */ public static function codes():array{return array_keys(self::all());}
}
