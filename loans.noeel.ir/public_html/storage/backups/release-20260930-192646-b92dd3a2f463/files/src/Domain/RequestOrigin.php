<?php
declare(strict_types=1);
namespace Babanoell\Loans\Domain;

use InvalidArgumentException;

final class RequestOrigin
{
    public const MERCHANT='merchant';
    public const APPLICANT='applicant';
    public const ADMIN='admin';
    public const MIGRATION='migration';
    public const API='api';

    /** @return list<string> */
    public static function all(): array
    {
        return [self::MERCHANT,self::APPLICANT,self::ADMIN,self::MIGRATION,self::API];
    }

    public static function normalize(string $value): string
    {
        $value=strtolower(trim($value));
        $aliases=['acceptor'=>self::MERCHANT,'panel'=>self::ADMIN,'operator'=>self::ADMIN];
        $value=$aliases[$value]??$value;
        if(!in_array($value,self::all(),true)) throw new InvalidArgumentException('مبدأ ثبت پرونده معتبر نیست.');
        return $value;
    }
}
