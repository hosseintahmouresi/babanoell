<?php
declare(strict_types=1);

namespace Babanoell\Panel\Loans;

use Babanoell\Panel\Http\LoansClient;
use Babanoell\Panel\Support\PersianDateTime;

/** The presentation contract for owner-service dashboard aggregates. */
final class DashboardSummary
{
    private const FOLLOW_UP_STATUSES = ['submitted'=>'ثبت‌شده','under_review'=>'در حال بررسی','need_correction'=>'نقص مدارک',
        'expert_approved'=>'تأیید کارشناس','docs_completion'=>'تکمیل مدارک','docs_verified'=>'تأیید مدارک'];

    public static function load(LoansClient $client, string $bearer): array
    {
        $response = $client->get('/api/v1/dashboard/summary', $bearer);
        if (!$response->ok()) return self::unavailable('دریافت آمار پرونده‌ها انجام نشد. پس از برقراری ارتباط دوباره تلاش کنید.');
        $data = $response->json['data'] ?? null;
        if (!is_array($data) || !self::valid($data)) {
            return self::unavailable('پاسخ آماری سرویس کامل یا معتبر نیست. صفحه را پس از بررسی سرویس تازه‌سازی کنید.');
        }
        $summary = $data['summary'];
        $notices = [];
        foreach ($data['follow_up'] as $case) {
            $notices[] = 'پرونده ' . $case['request_no'] . ' در وضعیت ' . self::FOLLOW_UP_STATUSES[$case['status']] . ' قرار دارد.';
        }
        if ($notices === []) $notices[] = 'در حال حاضر پرونده‌ای نیازمند پیگیری فوری نیست.';
        return ['error'=>'', 'total_cases'=>$summary['total_cases'], 'in_progress'=>$summary['in_progress'],
            'need_correction'=>$summary['need_correction'], 'granted'=>$summary['granted'],
            'total_sales_amount'=>$summary['total_order_amount'], 'monthly_sales_amount'=>$summary['month_order_amount'],
            'open_case_amount'=>$summary['open_requested_amount'], 'merchant_grade'=>self::grade($summary['total_order_amount']),
            'period_label'=>$data['period']['label'], 'case_notifications'=>$notices];
    }

    public static function money(?string $value): string
    {
        if ($value === null || !self::validMoney($value)) return '—';
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $whole = ltrim($whole, '0');
        if ($whole === '') $whole = '0';
        $formatted = (string) preg_replace('/\B(?=(\d{3})+(?!\d))/', '٬', $whole);
        if (trim($fraction, '0') !== '') $formatted .= '٫' . str_pad($fraction, 2, '0');
        return PersianDateTime::digits($formatted);
    }

    public static function grade(?string $value): string
    {
        if ($value === null || !self::validMoney($value)) return '—';
        $whole = ltrim(explode('.', $value, 2)[0], '0');
        foreach (['5000000000'=>'A+', '2000000000'=>'A', '1000000000'=>'B', '500000000'=>'C'] as $threshold=>$grade) {
            $threshold = (string) $threshold;
            if (strlen($whole) > strlen($threshold) || (strlen($whole) === strlen($threshold) && strcmp($whole, $threshold) >= 0)) return $grade;
        }
        return 'D';
    }

    private static function validMoney(mixed $value): bool
    {
        return is_string($value) && preg_match('/^\d{1,65}(?:\.\d{1,2})?$/D', $value) === 1;
    }

    private static function valid(array $data): bool
    {
        $summary = $data['summary'] ?? null;
        $period = $data['period'] ?? null;
        $followUp = $data['follow_up'] ?? null;
        if (!is_array($summary) || !is_array($period) || !is_array($followUp) || !array_is_list($followUp) || count($followUp) > 5) return false;
        foreach (['total_cases','in_progress','need_correction','granted'] as $key) {
            if (!isset($summary[$key]) || !is_int($summary[$key]) || $summary[$key] < 0) return false;
        }
        if ($summary['need_correction'] > $summary['in_progress'] || $summary['in_progress'] > $summary['total_cases']
            || $summary['granted'] > $summary['total_cases'] - $summary['in_progress']) return false;
        foreach (['total_order_amount','month_order_amount','open_requested_amount'] as $key) {
            if (!self::validMoney($summary[$key] ?? null)) return false;
        }
        if (($period['calendar'] ?? null) !== 'jalali' || ($period['timezone'] ?? null) !== 'Asia/Tehran'
            || !is_string($period['label'] ?? null) || !preg_match('/^\d{4}\/(?:0[1-9]|1[0-2])$/D', $period['label'])) return false;
        foreach (['from_utc','to_utc'] as $key) {
            if (!is_string($period[$key] ?? null) || !preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/D', $period[$key])) return false;
        }
        if ($period['from_utc'] >= $period['to_utc']) return false;
        foreach ($followUp as $case) {
            if (!is_array($case) || !is_string($case['request_no'] ?? null) || $case['request_no'] === '' || strlen($case['request_no']) > 128
                || !is_string($case['status'] ?? null) || !isset(self::FOLLOW_UP_STATUSES[$case['status']])) return false;
        }
        return true;
    }

    private static function unavailable(string $message): array
    {
        return ['error'=>$message, 'total_cases'=>null, 'in_progress'=>null, 'need_correction'=>null, 'granted'=>null,
            'total_sales_amount'=>null, 'monthly_sales_amount'=>null, 'open_case_amount'=>null,
            'merchant_grade'=>'—', 'period_label'=>'—', 'case_notifications'=>['وضعیت پرونده‌ها فعلاً قابل دریافت نیست.']];
    }
}
