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
            'national_card_front'=>['label'=>'کارت ملی (رو)','category'=>'identity','visibility'=>'confidential'],
            'national_card_back'=>['label'=>'کارت ملی (پشت)','category'=>'identity','visibility'=>'confidential'],
            'birth_certificate'=>['label'=>'شناسنامه','category'=>'identity','visibility'=>'confidential'],
            'portrait'=>['label'=>'تصویر چهره','category'=>'identity','visibility'=>'confidential'],
            'sana_certificate'=>['label'=>'گواهی ثبت ثنا','category'=>'identity','visibility'=>'confidential'],
            'employment_certificate'=>['label'=>'گواهی اشتغال','category'=>'employment','visibility'=>'confidential'],
            'business_license'=>['label'=>'جواز کسب','category'=>'employment','visibility'=>'confidential'],
            'activity_license'=>['label'=>'مجوز فعالیت','category'=>'employment','visibility'=>'confidential'],
            'payslip'=>['label'=>'فیش حقوقی','category'=>'financial','visibility'=>'confidential'],
            'bank_statement'=>['label'=>'گردش حساب','category'=>'financial','visibility'=>'confidential'],
            'tax_return'=>['label'=>'اظهارنامه / اطلاعات مالیاتی','category'=>'financial','visibility'=>'confidential'],
            'residence_document'=>['label'=>'سند یا اجاره‌نامه محل سکونت','category'=>'address','visibility'=>'confidential'],
            'workplace_document'=>['label'=>'سند یا اجاره‌نامه محل کار','category'=>'address','visibility'=>'confidential'],
            'signature_certificate'=>['label'=>'گواهی امضا','category'=>'identity','visibility'=>'confidential'],
            'primary_check'=>['label'=>'چک اصلی','category'=>'check_primary','visibility'=>'confidential'],
            'secondary_check'=>['label'=>'چک تکمیلی','category'=>'check_secondary','visibility'=>'confidential'],
            'blank_check_image'=>['label'=>'تصویر چک صیادی','category'=>'check','visibility'=>'confidential'],
            'guarantor_identity'=>['label'=>'مدارک هویتی ضامن','category'=>'guarantor','visibility'=>'confidential'],
            'guarantor_income'=>['label'=>'مدارک درآمدی ضامن','category'=>'guarantor','visibility'=>'confidential'],
            'guarantor_check'=>['label'=>'چک ضامن','category'=>'guarantor','visibility'=>'confidential'],
            'credit_report'=>['label'=>'گزارش اعتبارسنجی','category'=>'credit_check','visibility'=>'confidential'],
            'bank_validation'=>['label'=>'فایل اعتبارسنجی بانک','category'=>'credit_check','visibility'=>'confidential'],
            'contract'=>['label'=>'قرارداد','category'=>'contract','visibility'=>'confidential'],
            'signed_contract'=>['label'=>'قرارداد امضا شده','category'=>'contract','visibility'=>'confidential'],
            'invoice'=>['label'=>'فاکتور / پیش‌فاکتور','category'=>'invoice','visibility'=>'normal'],
            'supplemental'=>['label'=>'مدارک تکمیلی','category'=>'supplemental','visibility'=>'confidential'],
            'completed_check'=>['label'=>'چک تکمیل شده','category'=>'check','visibility'=>'confidential'],
            'case_receipt'=>['label'=>'رسید واریزی پرونده','category'=>'case_receipt','visibility'=>'confidential'],
            'transfer_receipt'=>['label'=>'رسید انتقال وجه','category'=>'case_receipt','visibility'=>'confidential'],
            'settlement_receipt'=>['label'=>'رسید سیکل تسویه','category'=>'settlement_receipt','visibility'=>'confidential'],
            'bank_settlement_receipt'=>['label'=>'رسید تسویه بانک با بابانوئل','category'=>'bank_settlement','visibility'=>'confidential'],
            'cooperation_identity'=>['label'=>'مدارک هویتی همکاری','category'=>'cooperation','visibility'=>'confidential'],
            'cooperation_license'=>['label'=>'مجوز همکاری / فعالیت','category'=>'cooperation','visibility'=>'confidential'],
            'cooperation_contract'=>['label'=>'قرارداد همکاری','category'=>'cooperation','visibility'=>'confidential'],
        ];
    }
    public static function get(string$type): array{$type=strtolower(trim($type));$item=self::all()[$type]??null;if(!is_array($item))throw new InvalidArgumentException('نوع مدرک انتخاب‌شده معتبر نیست.');return ['code'=>$type]+$item;}
    /** @return list<string> */ public static function codes():array{return array_keys(self::all());}
}
