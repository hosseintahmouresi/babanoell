<?php
declare(strict_types=1);
namespace Babanoell\Integrations\Support;
final class Validation {
 public static function digits(string $s):string {return strtr($s,['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9','٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9']);}
 public static function mobile(mixed $v):string {$s=self::digits(trim((string)$v));if(!preg_match('/^09[0-9]{9}$/D',$s))throw new \InvalidArgumentException('invalid_mobile');return $s;}
 public static function national(mixed $v):string {$s=self::digits(trim((string)$v));if(!preg_match('/^[0-9]{10}$/D',$s))throw new \InvalidArgumentException('invalid_national_code');return $s;}
 public static function key(mixed $v):string{$s=(string)$v;if(!preg_match('/^[A-Za-z0-9_-]{16,120}$/D',$s))throw new \InvalidArgumentException('invalid_idempotency_key');return $s;}
 public static function opaque(mixed $v):string{$s=(string)$v;if(!preg_match('/^[A-Za-z0-9_-]{6,180}$/D',$s))throw new \InvalidArgumentException('invalid_reference');return $s;}
 public static function otp(mixed $v):string{$s=self::digits(trim((string)$v));if(!preg_match('/^[0-9]{4,8}$/D',$s))throw new \InvalidArgumentException('invalid_otp');return $s;}
}
