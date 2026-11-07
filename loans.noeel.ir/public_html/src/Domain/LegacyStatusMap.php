<?php
declare(strict_types=1);
namespace Babanoell\Loans\Domain;

final class LegacyStatusMap
{
    /** @return array<string,string> */
    public static function requestStatuses(): array
    {
        return [
            'در حال بررسی'=>'under_review',
            'نقص مدارک'=>'need_correction',
            'رد شده'=>'rejected',
            'تأیید کارشناس'=>'expert_approved',
            'تایید کارشناس'=>'expert_approved',
            'تکمیل مدارک'=>'docs_completion',
            'تأیید مدارک'=>'docs_verified',
            'تایید مدارک'=>'docs_verified',
            'اعطا شده'=>'granted',
            'لغو شده'=>'cancelled',
            'بایگانی'=>'archived',
        ];
    }

    /** @return array<string,string> */
    public static function settlementStatuses(): array
    {
        return [
            ''=>'not_started',
            'منتظر تعیین تاریخ'=>'awaiting_date',
            'در انتظار تسویه'=>'pending',
            'تسویه شده'=>'settled',
        ];
    }

    /** @return array<string,string> */
    public static function documentReceiptStatuses(): array
    {
        return [
            ''=>'unreviewed',
            'بررسی نشده'=>'unreviewed',
            'دریافت نشده'=>'missing',
            'عدم دریافت'=>'missing',
            'دریافت شده'=>'received',
            'عدم نیاز به دریافت مدارک'=>'not_required',
            'عدم نیاز به مدارک'=>'not_required',
            'تأیید شده'=>'received',
            'تایید شده'=>'received',
        ];
    }

    /** @return array<string,string> */
    public static function bankSettlementStatuses(): array
    {
        return [
            ''=>'not_recorded',
            'ثبت نشده'=>'not_recorded',
            'ثبت شده'=>'recorded',
            'تسویه شده'=>'recorded',
            'انجام شده'=>'recorded',
            'لغو شده'=>'cancelled',
            'ابطال ثبت'=>'cancelled',
        ];
    }

    /** @return array<string,string> */
    public static function financierPaymentStatuses(): array
    {
        return [
            ''=>'unreviewed',
            'بررسی نشده'=>'unreviewed',
            'پرداخت شده'=>'paid',
            'عدم پرداخت'=>'not_paid',
        ];
    }

    public static function request(string $legacy): ?string
    {
        $legacy=trim($legacy);
        return self::requestStatuses()[$legacy] ?? null;
    }
}
