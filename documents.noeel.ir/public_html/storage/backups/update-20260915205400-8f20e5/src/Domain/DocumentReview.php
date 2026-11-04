<?php
declare(strict_types=1);
namespace Babanoell\Documents\Domain;
use InvalidArgumentException;
final class DocumentReview
{
    public static function validate(string$status,string$reason):array
    {
        $status=strtolower(trim($status));$reason=trim($reason);
        if(!in_array($status,['pending','approved','rejected'],true))throw new InvalidArgumentException('وضعیت بررسی مدرک معتبر نیست.');
        if($status==='rejected'&&$reason==='')throw new InvalidArgumentException('برای رد مدرک، ثبت دلیل الزامی است.');
        if(function_exists('mb_strlen')&&mb_strlen($reason,'UTF-8')>1000)throw new InvalidArgumentException('توضیح بررسی بیش از حد طولانی است.');
        return[$status,$reason];
    }
}
