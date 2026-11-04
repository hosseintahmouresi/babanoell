<?php
declare(strict_types=1);

namespace Babanoell\Loans\View;

use Babanoell\Loans\Support\PersianDateTime;

final class Renderer
{
    private const SERVICE_VERSION = '2.7.1';
    private const UI_VERSION = '2.6.0';

    public static function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    public static function page(string $title, string $content, string $tag = 'سرویس تسهیلات'): string
    {
        $now = PersianDateTime::now();

        return '<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">'
            . '<meta name="robots" content="noindex, nofollow, noarchive">'
            . '<meta name="theme-color" content="#1D3559"><meta name="color-scheme" content="light">'
            . '<title>' . self::e($title) . ' | پلتفرم بابانوئل</title>'
            . '<link rel="icon" type="image/png" href="/ui-asset.php?file=brand-logo.png&amp;v=' . self::SERVICE_VERSION . '">'
            . '<link rel="stylesheet" href="/ui-asset.php?file=babanoell-ui.css&amp;v=' . self::SERVICE_VERSION . '">'
            . '</head><body><main class="bn-shell bn-shell--narrow">'
            . self::brand($tag)
            . '<section class="bn-card">' . $content . '</section>'
            . '<footer class="bn-footer">'
            . '<span><strong>پلتفرم بابانوئل</strong> · هسته مدیریت تسهیلات</span>'
            . '<time class="bn-footer-time" datetime="' . self::e($now->format(DATE_ATOM)) . '">' . self::e(PersianDateTime::full($now)) . '</time>'
            . '<span class="bn-footer-version">Loans ' . self::SERVICE_VERSION . ' · UI ' . self::UI_VERSION . '</span>'
            . '</footer></main>'
            . '<script src="/ui-asset.php?file=babanoell-ui.js&amp;v=' . self::SERVICE_VERSION . '" defer></script>'
            . '</body></html>';
    }

    private static function brand(string $tag): string
    {
        return '<header class="bn-brandbar">'
            . '<div class="bn-lockup"><span class="bn-logo"><img src="/ui-asset.php?file=brand-logo.png&amp;v=' . self::SERVICE_VERSION . '" alt="لوگوی بابانوئل" width="118" height="40"></span>'
            . '<div class="bn-brandtext"><div class="bn-brandtitle">مرکز مدیریت سرویس‌ها</div><div class="bn-brandmeta">هسته مدیریت تسهیلات و اقساط</div></div></div>'
            . '<div class="bn-brandtag"><span class="bn-brandtag-dot"></span><span>' . self::e($tag) . '</span><span class="bn-version">Loans</span></div>'
            . '</header>';
    }

    public static function digits(string|int|float $value): string
    {
        return PersianDateTime::digits($value);
    }
}
