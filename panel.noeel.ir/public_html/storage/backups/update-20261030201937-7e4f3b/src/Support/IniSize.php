<?php
declare(strict_types=1);
namespace Babanoell\Panel\Support;

final class IniSize
{
    public static function toBytes(string|false $value): int
    {
        if ($value === false) return 0;
        $value = trim((string) $value);
        if ($value === '' || $value === '-1') return PHP_INT_MAX;
        if (preg_match('/^([0-9]+(?:\.[0-9]+)?)\s*([KMGT]?)B?$/i', $value, $m) !== 1) return max(0, (int) $value);
        $number = (float) $m[1];
        $power = match (strtoupper($m[2])) { 'K'=>1, 'M'=>2, 'G'=>3, 'T'=>4, default=>0 };
        $bytes = $number * (1024 ** $power);
        return $bytes >= PHP_INT_MAX ? PHP_INT_MAX : (int) floor($bytes);
    }

    public static function effectiveUploadLimit(int $serviceLimit): int
    {
        $limits = [max(1, $serviceLimit)];
        $upload = self::toBytes(ini_get('upload_max_filesize'));
        $post = self::toBytes(ini_get('post_max_size'));
        if ($upload > 0 && $upload !== PHP_INT_MAX) $limits[] = $upload;
        if ($post > 0 && $post !== PHP_INT_MAX) $limits[] = max(1, $post - 1048576); // reserve multipart/form fields overhead
        return min($limits);
    }

    public static function requestExceedsPostLimit(?int $contentLength = null): bool
    {
        $contentLength ??= isset($_SERVER['CONTENT_LENGTH']) ? (int) $_SERVER['CONTENT_LENGTH'] : 0;
        $post = self::toBytes(ini_get('post_max_size'));
        return $contentLength > 0 && $post > 0 && $post !== PHP_INT_MAX && $contentLength > $post;
    }
}
