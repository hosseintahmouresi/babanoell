<?php
declare(strict_types=1);
namespace Babanoell\Loans\Domain;

use InvalidArgumentException;

final class BankCreditCatalog
{
    public const TYPES=['identity_match','credit_report'];
    public const STATUSES=['queued','pending','verified','failed','expired','cancelled'];
    public const DEFAULT_PROVIDERS=['identity_match'=>'shahkar','credit_report'=>'ics24'];

    public static function type(string $value): string
    {
        $value=strtolower(trim($value));
        if(!in_array($value,self::TYPES,true))throw new InvalidArgumentException('نوع اعتبارسنجی بانکی معتبر نیست.');
        return$value;
    }
    public static function provider(string $value,string $type): string
    {
        $value=strtolower(trim($value));if($value==='')$value=self::DEFAULT_PROVIDERS[$type]??'';
        if($value===''||preg_match('/^[a-z0-9][a-z0-9_.-]{1,63}$/',$value)!==1)throw new InvalidArgumentException('نام سرویس اعتبارسنجی معتبر نیست.');
        return$value;
    }
    public static function status(string $value): string
    {
        $value=strtolower(trim($value));if(!in_array($value,self::STATUSES,true))throw new InvalidArgumentException('وضعیت اعتبارسنجی معتبر نیست.');return$value;
    }
}
