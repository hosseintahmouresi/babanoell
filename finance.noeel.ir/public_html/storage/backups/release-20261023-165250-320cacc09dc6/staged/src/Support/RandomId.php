<?php
declare(strict_types=1);
namespace Babanoell\Finance\Support;
final class RandomId
{
    public static function generate(): string { return bin2hex(random_bytes(13)); }
    public static function make(): string { return self::generate(); }
}
