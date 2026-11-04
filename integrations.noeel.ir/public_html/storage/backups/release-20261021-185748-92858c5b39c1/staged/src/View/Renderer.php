<?php
declare(strict_types=1);
namespace Babanoell\Integrations\View;

final class Renderer
{
    public static function e(mixed $value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
    public static function page(string $title, string $content, string $tag='مرکز به‌روزرسانی'): string
    {
        $v=require dirname(__DIR__, 2).'/config/version.php';
        $version=self::e($v['version']??'?');
        $ui=self::e($v['ui']??'?');
        return '<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><meta name="robots" content="noindex,nofollow,noarchive"><meta name="theme-color" content="#1D3559"><meta name="color-scheme" content="light"><title>'.self::e($title).' | پلتفرم بابانوئل</title><link rel="icon" href="/ui-asset.php?file=brand-logo.png&amp;v='.$ui.'"><link rel="stylesheet" href="/ui-asset.php?file=babanoell-ui.css&amp;v='.$ui.'"></head><body><a class="bn-skip-link" href="#main-content">رفتن به محتوای اصلی</a><main class="bn-shell bn-shell--narrow"><header class="bn-brandbar"><div class="bn-lockup"><span class="bn-logo"><img src="/ui-asset.php?file=brand-logo.png&amp;v='.$ui.'" alt="بابانوئل" width="118" height="40"></span><div class="bn-brandtext"><div class="bn-brandtitle">مرکز مدیریت سرویس‌ها</div><div class="bn-brandmeta">سامانه یکپارچه مدیریت تسهیلات</div></div></div><div class="bn-brandtag"><span class="bn-brandtag-dot"></span><span>'.self::e($tag).'</span><span class="bn-version">Integrations</span></div></header><section id="main-content" class="bn-card" tabindex="-1">'.$content.'</section><footer class="bn-footer"><span><strong>پلتفرم بابانوئل</strong> · سرویس یکپارچه‌سازی</span><span dir="ltr">Integrations '.$version.' · UI '.$ui.'</span></footer></main><script src="/ui-asset.php?file=babanoell-ui.js&amp;v='.$ui.'" defer></script></body></html>';
    }
}
