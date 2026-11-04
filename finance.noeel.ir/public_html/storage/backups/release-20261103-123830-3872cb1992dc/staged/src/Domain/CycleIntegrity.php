<?php
declare(strict_types=1);
namespace Babanoell\Finance\Domain;

use InvalidArgumentException;

/** Deterministic, side-effect-free checks; amounts stored in 1/100 unit (DECIMAL(20,2)). */
final class CycleIntegrity
{
    public static function cents(mixed $amount): int
    {
        if (!is_string($amount) && !is_int($amount) && !is_float($amount)) {
            throw new InvalidArgumentException('مبلغ باید عدد معتبر باشد.');
        }
        $text = trim((string)$amount);
        if (!preg_match('/^(0|[1-9][0-9]{0,12})(?:\.([0-9]{1,2}))?$/D', $text, $m)) {
            throw new InvalidArgumentException('مبلغ باید مثبت، محدود و حداکثر با دو رقم اعشار باشد.');
        }
        return ((int)$m[1] * 100) + (int)str_pad($m[2] ?? '', 2, '0');
    }

    public static function validateItem(mixed $total, mixed $leasing, mixed $merchant): void
    {
        $t = self::cents($total);
        $l = self::cents($leasing);
        $m = self::cents($merchant);
        if ($t <= 0 || $l + $m !== $t) {
            throw new InvalidArgumentException('مبلغ تسویه باید بیشتر از صفر و برابر مجموع سهم لیزینگ و پذیرنده باشد.');
        }
    }

    /** @return list<array{type:string,severity:string,expected:array,actual:array}> */
    public static function issues(array $cycle, array $items, array $receipts = []): array
    {
        $issues = [];
        if (!$items) {
            $issues[] = ['type'=>'empty_cycle', 'severity'=>'critical','expected'=>['items_min'=>1],'actual'=>['items'=>0]];
        }
        $sum=['settlement_amount'=>0,'leasing_amount'=>0,'merchant_amount'=>0];
        foreach ($items as $item) {
            try {
                self::validateItem($item['settlement_amount'] ?? '', $item['leasing_amount'] ?? '', $item['merchant_amount'] ?? '');
                foreach (array_keys($sum) as $k) $sum[$k] += self::cents($item[$k]);
            } catch (InvalidArgumentException) {
                $issues[]=['type'=>'item_amount_mismatch','severity'=>'critical','expected'=>['settlement_equals_parts'=>true],'actual'=>['request_id'=>(string)($item['request_id']??'')]];
            }
        }
        foreach (['total_amount'=>'settlement_amount','leasing_total'=>'leasing_amount','merchant_total'=>'merchant_amount'] as $col=>$part) {
            try { $stored=self::cents($cycle[$col]??''); }
            catch (InvalidArgumentException) { $stored=-1; }
            if ($stored!==$sum[$part]) $issues[]=['type'=>'cycle_total_mismatch_'.$col,'severity'=>'critical','expected'=>['cents'=>$sum[$part]],'actual'=>['cents'=>$stored]];
        }
        if ((string)($cycle['status']??'')==='closed') {
            $pending=0;
            foreach ($receipts as $receipt) if (($receipt['status']??'')==='pending') $pending++;
            if ($pending) $issues[]=['type'=>'pending_receipts_in_closed_cycle','severity'=>'warning','expected'=>['pending'=>0],'actual'=>['pending'=>$pending]];
        }
        return $issues;
    }
}
