<?php
declare(strict_types=1);
namespace Babanoell\Loans\Domain;

final class StatusMachine
{
    private const LABELS = [
        'draft' => 'پیش‌نویس',
        'submitted' => 'ثبت‌شده',
        'under_review' => 'در حال بررسی',
        'need_correction' => 'نقص مدارک',
        'expert_approved' => 'تأیید کارشناس',
        'docs_completion' => 'تکمیل مدارک',
        'docs_verified' => 'تأیید مدارک',
        'rejected' => 'ردشده',
        'granted' => 'اعطا شده',
        'cancelled' => 'لغوشده',
        'archived' => 'بایگانی',
    ];

    /** @var array<string,array<int,string>> */
    private const MAP = [
        'draft' => ['submitted', 'cancelled'],
        'submitted' => ['under_review', 'cancelled'],
        'under_review' => ['need_correction', 'rejected', 'expert_approved'],
        'need_correction' => ['under_review'],
        'expert_approved' => ['need_correction', 'rejected', 'docs_completion'],
        'docs_completion' => ['docs_verified'],
        'docs_verified' => ['granted', 'docs_completion', 'rejected'],
        'rejected' => ['under_review'],
        'granted' => ['archived'],
        'cancelled' => ['archived'],
        'archived' => [],
    ];

    /** @return array{0:bool,1:string} */
    public function can(string $from, string $to, array $request = []): array
    {
        if (!isset(self::MAP[$from]) || !isset(self::LABELS[$to])) {
            return [false, 'وضعیت مبدأ یا مقصد معتبر نیست.'];
        }
        if ($from === $to) {
            return [false, 'پرونده در حال حاضر در همین وضعیت قرار دارد.'];
        }
        if (!in_array($to, self::MAP[$from], true)) {
            return [false, 'این تغییر وضعیت در گردش‌کار پرونده مجاز نیست.'];
        }
        if ($to === 'expert_approved' && trim((string)($request['assigned_bank_user_id'] ?? '')) === '') {
            return [false, 'پیش از تأیید کارشناس باید کارشناس بانک پرونده مشخص شود.'];
        }
        return [true, ''];
    }

    public function requiresReason(string $target): bool
    {
        return in_array($target, ['need_correction', 'rejected', 'cancelled'], true);
    }

    public function label(string $status): string { return self::LABELS[$status] ?? $status; }
    /** @return array<string,string> */
    public function labels(): array { return self::LABELS; }
    /** @return array<int,string> */
    public function transitions(string $status): array { return self::MAP[$status] ?? []; }
}
