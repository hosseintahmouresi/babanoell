<?php
declare(strict_types=1);
namespace Babanoell\Finance\Domain;
use InvalidArgumentException;
final class FinanceRules
{
 public static function bankSettlementStatus(string$v):string{$v=strtolower(trim($v));if(!in_array($v,['not_recorded','recorded','cancelled'],true))throw new InvalidArgumentException('وضعیت تسویه بانک معتبر نیست.');return$v;}
 public static function merchantSettlementStatus(string$v):string{$v=strtolower(trim($v));if(!in_array($v,['not_started','awaiting_date','pending','settled'],true))throw new InvalidArgumentException('وضعیت تسویه پذیرنده معتبر نیست.');return$v;}
 public static function financierPaymentStatus(string$v):string{$v=strtolower(trim($v));if(!in_array($v,['unreviewed','paid','not_paid'],true))throw new InvalidArgumentException('وضعیت پرداخت تأمین‌کننده معتبر نیست.');return$v;}
 public static function cycleStatus(string$v):string{$v=strtolower(trim($v));if(!in_array($v,['open','closed'],true))throw new InvalidArgumentException('وضعیت سیکل تسویه معتبر نیست.');return$v;}
 public static function receiptStatus(string$v):string{$v=strtolower(trim($v));if(!in_array($v,['pending','approved','rejected'],true))throw new InvalidArgumentException('وضعیت رسید معتبر نیست.');return$v;}
 public static function id(string$v,string$label='شناسه'):string{$v=trim($v);if(preg_match('/^[a-f0-9]{26}$/',$v)!==1)throw new InvalidArgumentException($label.' معتبر نیست.');return$v;}
 public static function money(mixed$v,string$label='مبلغ'):float{if(!is_numeric($v))throw new InvalidArgumentException($label.' معتبر نیست.');$n=round((float)$v,2);if($n<0)throw new InvalidArgumentException($label.' نمی‌تواند منفی باشد.');return$n;}
}
