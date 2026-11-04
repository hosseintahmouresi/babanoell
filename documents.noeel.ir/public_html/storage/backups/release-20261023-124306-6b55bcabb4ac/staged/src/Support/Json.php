<?php
declare(strict_types=1);
namespace Babanoell\Documents\Support;
use JsonException; use RuntimeException;
final class Json
{
    public static function encode(mixed $value): string { try{return json_encode($value,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);}catch(JsonException $e){throw new RuntimeException('JSON encoding failed.',0,$e);} }
    public static function decode(?string $value, mixed $default=[]): mixed { if($value===null||trim($value)==='')return$default;try{return json_decode($value,true,512,JSON_THROW_ON_ERROR);}catch(JsonException){return$default;} }
}
