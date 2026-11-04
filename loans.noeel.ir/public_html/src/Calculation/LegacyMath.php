<?php
declare(strict_types=1);
namespace Babanoell\Loans\Calculation;
final class LegacyMath
{
    public static function pmt(float $rate,float $nper,float $pv): float { if($nper<=0)return 0.0;if($rate==0.0)return-($pv/$nper);$pow=pow(1+$rate,$nper);if(($pow-1)==0.0)return 0.0;return-($pv*$rate*$pow)/($pow-1); }
    public static function pv(float $rate,float $nper,float $pmt): float { if($nper<=0)return 0.0;if($rate==0.0)return-($pmt*$nper);$pow=pow(1+$rate,-$nper);return-($pmt*(1-$pow))/$rate; }
}
