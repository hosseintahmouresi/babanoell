<?php
declare(strict_types=1);
namespace Babanoell\Auth\Security;
final class InputNormalizer {
 public static function digits(string $v):string{return strtr($v,array_combine(preg_split('//u','۰۱۲۳۴۵۶۷۸۹٠١٢٣٤٥٦٧٨٩',-1,PREG_SPLIT_NO_EMPTY),str_split('01234567890123456789')));}
 public static function mobile(string $v):string{$v=preg_replace('/[\s()-]+/u','',self::digits(trim($v)));if(str_starts_with($v,'+98'))$v='0'.substr($v,3);elseif(str_starts_with($v,'0098'))$v='0'.substr($v,4);return $v;}
 public function loginIdentifier(string $v):string{$v=strtolower(trim(self::digits($v)));$mobile=self::mobile($v);return preg_match('/^09[0-9]{9}$/D',$mobile)?$mobile:$v;}
}
