<?php
declare(strict_types=1);

namespace Babanoell\Finance\Support;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Throwable;

/**
 * Small dependency-free Persian/Jalali display formatter.
 *
 * Persistence and protocol timestamps remain UTC/ISO-8601. This class is
 * intentionally presentation-only and converts dates to Asia/Tehran for UI.
 */
final class PersianDateTime
{
    private const MONTHS = [
        1 => 'فروردین',
        2 => 'اردیبهشت',
        3 => 'خرداد',
        4 => 'تیر',
        5 => 'مرداد',
        6 => 'شهریور',
        7 => 'مهر',
        8 => 'آبان',
        9 => 'آذر',
        10 => 'دی',
        11 => 'بهمن',
        12 => 'اسفند',
    ];

    private const WEEKDAYS = [
        'Saturday' => 'شنبه',
        'Sunday' => 'یکشنبه',
        'Monday' => 'دوشنبه',
        'Tuesday' => 'سه‌شنبه',
        'Wednesday' => 'چهارشنبه',
        'Thursday' => 'پنجشنبه',
        'Friday' => 'جمعه',
    ];

    private const DIGITS = [
        '0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴',
        '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹',
    ];

    public static function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('Asia/Tehran'));
    }

    public static function full(DateTimeInterface $dateTime): string
    {
        $local = DateTimeImmutable::createFromInterface($dateTime)
            ->setTimezone(new DateTimeZone('Asia/Tehran'));

        [$year, $month, $day] = self::gregorianToJalali(
            (int) $local->format('Y'),
            (int) $local->format('n'),
            (int) $local->format('j'),
        );

        $weekday = self::WEEKDAYS[$local->format('l')] ?? '';
        $monthName = self::MONTHS[$month] ?? '';
        $date = trim($weekday . ' ' . $day . ' ' . $monthName . ' ' . $year);
        $time = $local->format('H:i:s');

        return self::digits($date . '، ساعت ' . $time);
    }

    public static function compact(DateTimeInterface $dateTime): string
    {
        $local = DateTimeImmutable::createFromInterface($dateTime)
            ->setTimezone(new DateTimeZone('Asia/Tehran'));

        [$year, $month, $day] = self::gregorianToJalali(
            (int) $local->format('Y'),
            (int) $local->format('n'),
            (int) $local->format('j'),
        );

        $monthName = self::MONTHS[$month] ?? '';

        return self::digits($day . ' ' . $monthName . ' ' . $year . '، ' . $local->format('H:i'));
    }

    public static function fromIso(?string $value, bool $full = true): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        try {
            $dateTime = new DateTimeImmutable($value);

            return $full ? self::full($dateTime) : self::compact($dateTime);
        } catch (Throwable) {
            return null;
        }
    }


    public static function dateOnly(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        try {
            $date = new DateTimeImmutable(substr(trim($value), 0, 10) . ' 12:00:00', new DateTimeZone('UTC'));
        } catch (Throwable) {
            return null;
        }

        [$year, $month, $day] = self::gregorianToJalali(
            (int) $date->format('Y'),
            (int) $date->format('n'),
            (int) $date->format('j'),
        );

        return self::digits(sprintf('%04d/%02d/%02d', $year, $month, $day));
    }

    public static function digits(string|int|float $value): string
    {
        return strtr((string) $value, self::DIGITS);
    }

    /**
     * @return array{0:int,1:int,2:int}
     */
    public static function gregorianToJalali(int $gy, int $gm, int $gd): array
    {
        $gDayMonth = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
        $gy2 = $gm > 2 ? $gy + 1 : $gy;

        $days = 355666
            + (365 * $gy)
            + intdiv($gy2 + 3, 4)
            - intdiv($gy2 + 99, 100)
            + intdiv($gy2 + 399, 400)
            + $gd
            + $gDayMonth[$gm - 1];

        $jy = -1595 + (33 * intdiv($days, 12053));
        $days %= 12053;
        $jy += 4 * intdiv($days, 1461);
        $days %= 1461;

        if ($days > 365) {
            $jy += intdiv($days - 1, 365);
            $days = ($days - 1) % 365;
        }

        if ($days < 186) {
            $jm = 1 + intdiv($days, 31);
            $jd = 1 + ($days % 31);
        } else {
            $jm = 7 + intdiv($days - 186, 30);
            $jd = 1 + (($days - 186) % 30);
        }

        return [$jy, $jm, $jd];
    }
}
