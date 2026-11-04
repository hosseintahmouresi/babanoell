<?php
declare(strict_types=1);
namespace Babanoell\Loans\Domain;

final class PlanSettingsValidator
{
    /** @var array<string,string> */
    private const LABELS = [
        'min_amount' => 'حداقل مبلغ',
        'max_amount' => 'حداکثر مبلغ',
        'supplier_costs_percent' => 'درصد هزینه تأمین‌کننده',
        'intermediary_deductions_percent' => 'درصد کسورات واسط',
        'babanovel_deductions_percent' => 'درصد کسورات بابانوئل',
        'insurance_percent' => 'درصد بیمه',
        'guarantee_check_percent' => 'درصد چک تضمین',
        'acceptor_commission_percent' => 'درصد کمیسیون پذیرنده',
        'additional_deductions_percent' => 'درصد کسورات تکمیلی',
        'vat_percent' => 'درصد مالیات بر ارزش افزوده',
        'fixed_monthly_rate' => 'نرخ ماهانه ثابت',
        'prepayment_percent' => 'درصد پیش‌پرداخت',
        'interest_rate' => 'نرخ سود',
        'effective_interest_rate' => 'نرخ سود مؤثر',
        'credit_share_percent' => 'درصد سهم اعتباری',
        'min_interval' => 'حداقل فاصله سررسیدها',
        'max_interval' => 'حداکثر فاصله سررسیدها',
    ];

    /** @return string[] */
    public function validate(string $type, array $settings): array
    {
        $s = $this->normalized($settings);
        $errors = [];
        if (!in_array($type, ['market', 'leasing'], true)) {
            $errors[] = 'نوع طرح معتبر نیست. طرح باید «بازاری» یا «لیزینگی» باشد.';
            return $errors;
        }

        $min = InputValidator::money($s['min_amount'] ?? 0);
        $max = InputValidator::money($s['max_amount'] ?? 0);
        if ($min < 0) $errors[] = 'حداقل مبلغ نمی‌تواند منفی باشد.';
        if ($max <= 0) $errors[] = 'حداکثر مبلغ باید بیشتر از صفر باشد.';
        if ($max > 0 && $max < $min) $errors[] = 'حداکثر مبلغ نمی‌تواند کمتر از حداقل مبلغ باشد.';

        $active = $s['active_installments'] ?? [];
        if (!is_array($active) || $active === []) $errors[] = 'حداقل یک تعداد قسط فعال تعیین کنید.';
        foreach (is_array($active) ? $active : [] as $count) {
            if (InputValidator::integer($count) <= 0) $errors[] = 'تعداد اقساط فعال باید یک عدد صحیح بیشتر از صفر باشد.';
        }

        foreach (['supplier_costs_percent','intermediary_deductions_percent','babanovel_deductions_percent','insurance_percent','guarantee_check_percent','acceptor_commission_percent','additional_deductions_percent','vat_percent'] as $key) {
            if (isset($s[$key]) && trim((string)$s[$key]) !== '' && !$this->isDecimal($s[$key])) {
                $errors[] = self::LABELS[$key].' باید به‌صورت عددی وارد شود.';
            }
        }

        if ($type === 'market') {
            foreach (['fixed_monthly_rate','prepayment_percent'] as $key) {
                if (!isset($s[$key]) || trim((string)$s[$key]) === '' || !$this->isDecimal($s[$key])) {
                    $errors[] = self::LABELS[$key].' الزامی است و باید به‌صورت عددی وارد شود.';
                }
            }
        }

        if ($type === 'leasing') {
            foreach (['interest_rate','effective_interest_rate','credit_share_percent','min_interval','max_interval'] as $key) {
                if (!isset($s[$key]) || trim((string)$s[$key]) === '' || !$this->isDecimal($s[$key])) {
                    $errors[] = self::LABELS[$key].' الزامی است و باید به‌صورت عددی وارد شود.';
                }
            }
            $minInterval = InputValidator::integer($s['min_interval'] ?? 0);
            $maxInterval = InputValidator::integer($s['max_interval'] ?? 0);
            if ($minInterval <= 0 || $maxInterval < $minInterval) $errors[] = 'بازه فاصله سررسید اقساط معتبر نیست.';
            $share = InputValidator::decimal($s['credit_share_percent'] ?? 0);
            if ($share <= 0 || $share > 100) $errors[] = 'درصد سهم اعتباری باید بیشتر از صفر و حداکثر ۱۰۰ باشد.';
        }

        return array_values(array_unique($errors));
    }

    /** @return array<string,mixed> */
    public function normalized(array $settings): array
    {
        $s = $settings;
        if (isset($s['active_installments']) && is_string($s['active_installments'])) {
            $parts = preg_split('/\s*[,،]\s*/u', InputValidator::normalizeDigits($s['active_installments'])) ?: [];
            $s['active_installments'] = array_values(array_unique(array_filter(
                array_map(static fn(string $v): int => InputValidator::integer($v), $parts),
                static fn(int $v): bool => $v > 0
            )));
        }
        if (isset($s['active_installments']) && is_array($s['active_installments'])) {
            $s['active_installments'] = array_map(static fn(mixed $value): mixed =>
                is_string($value) && preg_match('/^-?\d+$/', trim(InputValidator::normalizeDigits($value))) === 1
                    ? InputValidator::integer($value) : $value,
                $s['active_installments']);
        }
        if (isset($s['babanoell_deductions_percent']) && !isset($s['babanovel_deductions_percent'])) {
            $s['babanovel_deductions_percent'] = $s['babanoell_deductions_percent'];
        }
        $moneyFields=['min_amount','max_amount','acceptor_deductions_amount','other_deductions_amount'];
        foreach(array_unique([...array_keys(self::LABELS),...$moneyFields])as$key){
            if(!isset($s[$key])||!is_string($s[$key]))continue;
            $value=InputValidator::normalizeDigits($s[$key]);
            if(in_array($key,$moneyFields,true)){
                $value=str_replace([',','٬','٫'],['','','.'],$value);
                $value=preg_replace('/\s+/u','',$value)??$value;
            }else{
                $value=str_replace(['٪','%','٫','،'],['','','.','.'],$value);
            }
            // Preserve malformed/blank input so validation can still reject it.
            if(is_numeric(trim($value)))$s[$key]=$value+0;
        }
        $raw = $s['requires_bank_validation'] ?? false;
        $s['requires_bank_validation'] = is_bool($raw)
            ? $raw
            : in_array(strtolower(trim((string)$raw)), ['1','true','yes','on'], true);
        return $s;
    }

    private function isDecimal(mixed $value): bool
    {
        if (is_int($value) || is_float($value)) return true;
        $v = str_replace(['٪','%','٫','،'], ['','','.', '.'], InputValidator::normalizeDigits((string)$value));
        return is_numeric(trim($v));
    }
}
