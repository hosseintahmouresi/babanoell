<?php
declare(strict_types=1);
namespace Babanoell\Loans\Domain;

use InvalidArgumentException;

final class WorkflowCatalog
{
    /** @return array<string,string> */
    public static function documentReceiptStatuses(): array
    {
        return [
            'unreviewed'=>'بررسی نشده',
            'received'=>'دریافت شده',
            'missing'=>'عدم دریافت',
            'not_required'=>'عدم نیاز به دریافت مدارک',
        ];
    }

    /** @return array<string,string> */
    public static function bankReviewStatuses(): array
    {
        return [
            'approved'=>'تأیید شده',
            'needs_documents'=>'نیازمند تکمیل مدارک',
            'rejected'=>'رد شده',
            'noted'=>'ثبت یادداشت بررسی',
        ];
    }

    /** @return array<string,string> */
    public static function bankSettlementStatuses(): array
    {
        return [
            'not_recorded'=>'ثبت نشده',
            'recorded'=>'ثبت شده',
            'cancelled'=>'ابطال ثبت',
        ];
    }

    /** @return array<string,string> */
    public static function merchantSettlementStatuses(): array
    {
        return [
            'not_started'=>'شروع نشده',
            'awaiting_date'=>'منتظر تعیین تاریخ',
            'pending'=>'در انتظار تسویه',
            'settled'=>'تسویه شده',
        ];
    }

    /** @return array<string,string> */
    public static function financierPaymentStatuses(): array
    {
        return [
            'unreviewed'=>'بررسی نشده',
            'paid'=>'پرداخت شده',
            'not_paid'=>'عدم پرداخت',
        ];
    }

    public static function assertValue(string $value, array $allowed, string $message): string
    {
        $value=strtolower(trim($value));
        if(!array_key_exists($value,$allowed)) throw new InvalidArgumentException($message);
        return $value;
    }
}
