<?php
declare(strict_types=1);

namespace Babanoell\Documents\Support;

final class IniSize
{
    public static function toBytes(string|false $value): int
    {
        if ($value === false) return 0;
        $value = trim($value);
        if ($value === '') return 0;
        if ($value === '-1') return PHP_INT_MAX;
        $last = strtolower(substr($value, -1));
        $number = (float) $value;
        return match ($last) {
            'g' => (int) round($number * 1024 * 1024 * 1024),
            'm' => (int) round($number * 1024 * 1024),
            'k' => (int) round($number * 1024),
            default => (int) round($number),
        };
    }

    public static function human(int $bytes): string
    {
        if ($bytes === PHP_INT_MAX) return 'نامحدود';
        if ($bytes <= 0) return 'نامشخص';
        if ($bytes >= 1073741824) return rtrim(rtrim(number_format($bytes / 1073741824, 1, '.', ''), '0'), '.') . ' GB';
        return rtrim(rtrim(number_format($bytes / 1048576, 1, '.', ''), '0'), '.') . ' MB';
    }
}
