<?php
declare(strict_types=1);

namespace Babanoell\Loans\Domain;

use Babanoell\Loans\Support\PersianDate;
use DateTimeImmutable;
use DateTimeZone;

final class DashboardPeriod
{
    /** UTC persistence boundaries; end is exclusive, including across Nowruz. */
    public static function current(DateTimeImmutable $now): array
    {
        $tehran = new DateTimeZone('Asia/Tehran');
        $local = $now->setTimezone($tehran);
        [$year, $month] = PersianDate::gregorianToJalali(
            (int) $local->format('Y'), (int) $local->format('n'), (int) $local->format('j'),
        );
        $nextYear = $month === 12 ? $year + 1 : $year;
        $nextMonth = $month === 12 ? 1 : $month + 1;
        $start = new DateTimeImmutable(PersianDate::jalaliToIso(sprintf('%04d/%02d/01', $year, $month)), $tehran);
        $end = new DateTimeImmutable(PersianDate::jalaliToIso(sprintf('%04d/%02d/01', $nextYear, $nextMonth)), $tehran);
        $utc = new DateTimeZone('UTC');
        return ['calendar'=>'jalali', 'timezone'=>'Asia/Tehran', 'label'=>sprintf('%04d/%02d', $year, $month),
            'from_utc'=>$start->setTimezone($utc)->format('Y-m-d H:i:s'),
            'to_utc'=>$end->setTimezone($utc)->format('Y-m-d H:i:s')];
    }
}
