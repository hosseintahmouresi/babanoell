<?php
declare(strict_types=1);
namespace Babanoell\Loans\Http;
final class IdempotencyKey
{
    public static function normalize(?string $value): string
    {
        $value=trim((string)$value);
        if($value==='') return '';
        if(strlen($value)<8||strlen($value)>128||preg_match('/^[A-Za-z0-9._:-]+$/',$value)!==1) throw new \InvalidArgumentException('کلید Idempotency-Key معتبر نیست.');
        return $value;
    }
}
