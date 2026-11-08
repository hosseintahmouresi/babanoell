<?php
declare(strict_types=1);
namespace Babanoell\Documents\Contract;

final class SignatureCertificate
{
    public static function render(array $c):string
    {
        if(($c['status']??'')!=='signed'||($c['mode']??'')!=='digital'||!in_array($c['template_code']??'',['hami_plus_reference','hami_retirees_reference'],true))return '';
        $buyer=(string)($c['buyer_confirmation_token']??'');$platform=(string)($c['platform_confirmation_token']??'');
        if(!preg_match('/^BUY-[A-F0-9]{24}$/D',$buyer)||!preg_match('/^PLT-[A-F0-9]{24}$/D',$platform))return '';
        $e=static fn(string $v):string=>htmlspecialchars($v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
        $stamp=(new \DateTimeImmutable((string)$c['signed_at'],new \DateTimeZone('UTC')))->setTimezone(new \DateTimeZone('Asia/Tehran'));
        $date=\Babanoell\Documents\Support\PersianDate::isoToJalali($stamp->format('Y-m-d')).' '.$stamp->format('H:i:s');
        return '<section class="bn-reference-signatures"><h3>تأیید و امضای دیجیتال خریدار</h3><p>این قرارداد در تاریخ و ساعت '.$e($date).' با کد تأیید پیامکی توسط خریدار تأیید و امضا شده است.</p><p>موبایل تأییدشده: <span dir="ltr">'.$e((string)($c['signer_mobile']??'')).'</span></p><p>توکن تأیید دیجیتال قرارداد: <span dir="ltr">'.$e($buyer).'</span></p><h3>تأیید و امضای دیجیتال پلتفرم</h3><p>این قرارداد در تاریخ و ساعت '.$e($date).' توسط پلتفرم امضا و تأیید شده است.</p><p>شناسه ملی: 14011126653</p><p>توکن تأیید دیجیتال قرارداد: <span dir="ltr">'.$e($platform).'</span></p></section>';
    }
}
