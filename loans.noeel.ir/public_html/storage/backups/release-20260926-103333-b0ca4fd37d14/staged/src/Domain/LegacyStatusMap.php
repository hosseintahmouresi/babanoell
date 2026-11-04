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
            'دریافت شده'=>'received',
            'تأیید شده'=>'verified',
            'تایید شده'=>'verified',
        ];
    }

    public static function request(string $legacy): ?string
    {
        $legacy=trim($legacy);
        return self::requestStatuses()[$legacy] ?? null;
    }
}
