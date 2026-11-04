<?php
declare(strict_types=1);
namespace Babanoell\Loans\Support;
use DateTimeImmutable; use DateTimeZone; use InvalidArgumentException;
final class PersianDate
{
    private const DIGITS=['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9','٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9'];
    public static function normalize(string $value): string { return strtr(trim($value),self::DIGITS); }
    public static function gregorianToJalali(int $gy,int $gm,int $gd): array
    {
        $gdm=[0,31,59,90,120,151,181,212,243,273,304,334];$gy2=$gm>2?$gy+1:$gy;$days=355666+365*$gy+intdiv($gy2+3,4)-intdiv($gy2+99,100)+intdiv($gy2+399,400)+$gd+$gdm[$gm-1];$jy=-1595+33*intdiv($days,12053);$days%=12053;$jy+=4*intdiv($days,1461);$days%=1461;if($days>365){$jy+=intdiv($days-1,365);$days=($days-1)%365;}if($days<186){$jm=1+intdiv($days,31);$jd=1+$days%31;}else{$jm=7+intdiv($days-186,30);$jd=1+($days-186)%30;}return[$jy,$jm,$jd];
    }
    public static function jalaliToGregorian(int $jy,int $jm,int $jd): array
    {
        if($jm<1||$jm>12||$jd<1||$jd>31)throw new InvalidArgumentException('تاریخ شمسی معتبر نیست.');
        $jy+=1595;$days=-355668+365*$jy+intdiv($jy,33)*8+intdiv(($jy%33)+3,4)+$jd+($jm<7?($jm-1)*31:(($jm-7)*30)+186);
        $gy=400*intdiv($days,146097);$days%=146097;if($days>36524){$gy+=100*intdiv(--$days,36524);$days%=36524;if($days>=365)$days++;}$gy+=4*intdiv($days,1461);$days%=1461;if($days>365){$gy+=intdiv($days-1,365);$days=($days-1)%365;}$gd=$days+1;$leap=($gy%4===0&&$gy%100!==0)||($gy%400===0);$months=[0,31,$leap?29:28,31,30,31,30,31,31,30,31,30,31];$gm=1;while($gm<=12&&$gd>$months[$gm]){$gd-=$months[$gm];$gm++;}return[$gy,$gm,$gd];
    }
    public static function parseJalali(string $value): array
    {
        $v=self::normalize($value);if(!preg_match('#^(\d{4})[/-](\d{1,2})[/-](\d{1,2})$#',$v,$m))throw new InvalidArgumentException('تاریخ را به شکل ۱۴۰۵/۰۶/۰۴ وارد کنید.');$jy=(int)$m[1];$jm=(int)$m[2];$jd=(int)$m[3];$max=$jm<=6?31:($jm<=11?30:(self::isLeap($jy)?30:29));if($jm<1||$jm>12||$jd<1||$jd>$max)throw new InvalidArgumentException('تاریخ شمسی معتبر نیست.');return[$jy,$jm,$jd];
    }
    public static function jalaliToIso(string $value): string { [$jy,$jm,$jd]=self::parseJalali($value);[$gy,$gm,$gd]=self::jalaliToGregorian($jy,$jm,$jd);return sprintf('%04d-%02d-%02d',$gy,$gm,$gd); }
    public static function isoToJalali(string $iso): string { $d=new DateTimeImmutable($iso,new DateTimeZone('Asia/Tehran'));[$jy,$jm,$jd]=self::gregorianToJalali((int)$d->format('Y'),(int)$d->format('n'),(int)$d->format('j'));return sprintf('%04d/%02d/%02d',$jy,$jm,$jd); }
    public static function addMonths(string $jalali,int $months): string
    {
        [$jy,$jm,$jd]=self::parseJalali($jalali);$index=$jy*12+($jm-1)+$months;$ny=intdiv($index,12);$nm=($index%12)+1;$max=$nm<=6?31:($nm<=11?30:(self::isLeap($ny)?30:29));$nd=min($jd,$max);return sprintf('%04d/%02d/%02d',$ny,$nm,$nd);
    }
    public static function today(): string { $d=new DateTimeImmutable('now',new DateTimeZone('Asia/Tehran'));[$jy,$jm,$jd]=self::gregorianToJalali((int)$d->format('Y'),(int)$d->format('n'),(int)$d->format('j'));return sprintf('%04d/%02d/%02d',$jy,$jm,$jd); }
    private static function isLeap(int $jy): bool { try{[$gy,$gm,$gd]=self::jalaliToGregorian($jy,12,30);[$y,$m,$d]=self::gregorianToJalali($gy,$gm,$gd);return$y===$jy&&$m===12&&$d===30;}catch(\Throwable){return false;} }
}
