<?php
declare(strict_types=1);
namespace Babanoell\Loans\Domain;

/** Integer rounding, wording and scales from the reference contract mapper. */
final class PersianMoneyWords
{
    public static function convert(float|int $value): string
    {
        $number=(int)round($value);if($number===0)return 'صفر';if($number<0)return 'منفی '.self::convert(-$number);
        $ones=['','یک','دو','سه','چهار','پنج','شش','هفت','هشت','نه'];
        $teens=[10=>'ده',11=>'یازده',12=>'دوازده',13=>'سیزده',14=>'چهارده',15=>'پانزده',16=>'شانزده',17=>'هفده',18=>'هجده',19=>'نوزده'];
        $tens=[2=>'بیست',3=>'سی',4=>'چهل',5=>'پنجاه',6=>'شصت',7=>'هفتاد',8=>'هشتاد',9=>'نود'];
        $hundreds=[1=>'یکصد',2=>'دویست',3=>'سیصد',4=>'چهارصد',5=>'پانصد',6=>'ششصد',7=>'هفتصد',8=>'هشتصد',9=>'نهصد'];
        $scales=['','هزار','میلیون','میلیارد','تریلیون','کوادریلیون','کوینتیلیون'];$groups=[];$scale=0;
        while($number>0&&$scale<count($scales)){$n=$number%1000;
            if($n>0){$parts=[];$h=intdiv($n,100);$r=$n%100;if($h>0)$parts[]=$hundreds[$h];
                if($r>=10&&$r<=19)$parts[]=$teens[$r];else{$t=intdiv($r,10);$o=$r%10;if($t>=2)$parts[]=$tens[$t];if($o>0)$parts[]=$ones[$o];}
                $words=implode(' و ',$parts);if($scales[$scale]!=='')$words.=' '.$scales[$scale];array_unshift($groups,$words);
            }$number=intdiv($number,1000);$scale++;
        }return implode(' و ',$groups);
    }
}
