<?php
declare(strict_types=1);

namespace Babanoell\Auth\Tests\Unit;

use Babanoell\Auth\Support\PersianDateTime;
use Babanoell\Auth\Tests\Assertions;
use Babanoell\Auth\Tests\TestCase;
use DateTimeImmutable;
use DateTimeZone;

final class PersianDateTimeTest extends TestCase
{
    public function testKnownGregorianDateConvertsToExpectedJalaliDate(): void
    {
        Assertions::same([1405, 6, 4], PersianDateTime::gregorianToJalali(2026, 8, 26));
    }

    public function testFullFormatUsesTehranTimePersianWeekdayAndDigits(): void
    {
        $utc = new DateTimeImmutable('2026-08-26T10:00:05+00:00', new DateTimeZone('UTC'));
        $formatted = PersianDateTime::full($utc);

        Assertions::contains('چهارشنبه', $formatted);
        Assertions::contains('۴ شهریور ۱۴۰۵', $formatted);
        Assertions::contains('ساعت ۱۳:۳۰:۰۵', $formatted);
    }

    public function testDigitsConvertsLatinNumeralsOnly(): void
    {
        Assertions::same('نسخه ۱.۳.۰', PersianDateTime::digits('نسخه 1.3.0'));
    }
}

return new PersianDateTimeTest();
