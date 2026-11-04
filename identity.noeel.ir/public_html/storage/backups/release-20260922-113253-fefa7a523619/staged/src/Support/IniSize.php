<?php
declare(strict_types=1);

namespace Babanoell\Auth\Support;

final class IniSize
{
    public static function toBytes(string|false $value): int
    {
        if ($value === false) {
            return 0;
        }

        $value = trim($value);

        if ($value === '') {
            return 0;
        }

        if ($value === '-1') {
            return PHP_INT_MAX;
        }

        $last = strtolower(substr($value, -1));
        $number = (float) $value;

        return match ($last) {
            'g' => (int) round($number * 1024 * 1024 * 1024),
            'm' => (int) round($number * 1024 * 1024),
            'k' => (int) round($number * 1024),
            default => (int) round($number),
        };
    }
}
