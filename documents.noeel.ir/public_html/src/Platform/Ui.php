<?php
declare(strict_types=1);
namespace Babanoell\Documents\Platform;

final class Ui
{
    public static function version(): array {return require dirname(__DIR__,2).'/config/version.php';}
    public static function e(string $value): string {return htmlspecialchars($value,ENT_QUOTES,'UTF-8');}
    public static function fonts(): string
    {
        return '<link rel="preload" href="/ui-asset.php?file=YekanBakhFaNum-Regular.woff" as="font" type="font/woff" crossorigin><link rel="preload" href="/ui-asset.php?file=YekanBakhFaNum-SemiBold.woff" as="font" type="font/woff" crossorigin>';
    }
    public static function icon(string $name): string
    {
        if (!preg_match('/^[a-z][a-z0-9-]*$/D', $name)) return '';
        return '<i class="bn-icon bn-icon--'.$name.'" aria-hidden="true"></i>';
    }
    public static function head(string $title,string $tag): string
    {
        $v=self::e(self::version()['ui']);
        return '<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><meta name="robots" content="noindex,nofollow,noarchive"><meta name="theme-color" content="#1D3559"><meta name="color-scheme" content="light"><title>'.self::e($title).' | پلتفرم بابانوئل</title><link rel="icon" href="/ui-asset.php?file=brand-logo.png&amp;v='.$v.'">'.self::fonts().'<link rel="stylesheet" href="/ui-asset.php?file=platform.css&amp;v='.$v.'"></head><body><a class="bn-skip-link" href="#main-content">رفتن به محتوای اصلی</a><main class="bn-shell"><header class="bn-brandbar"><div class="bn-lockup"><span class="bn-logo"><img src="/ui-asset.php?file=brand-logo.png&amp;v='.$v.'" alt="بابانوئل" width="118" height="40"></span><div class="bn-brandtext"><div class="bn-brandtitle">پلتفرم بابانوئل</div><div class="bn-brandmeta">سامانه یکپارچه مدیریت تسهیلات</div></div></div><div class="bn-brandtag"><span class="bn-brandtag-dot"></span><span>'.self::e($tag).'</span><span class="bn-version">نسخه '.$v.'</span></div></header><section class="bn-card" id="main-content" tabindex="-1">';
    }
    public static function footer(): string
    {
        $v=self::version();
        return '<footer class="bn-footer"><span><strong>پلتفرم بابانوئل</strong> · سامانه یکپارچه مدیریت تسهیلات</span><span class="bn-footer-version" dir="ltr">Noeel '.self::e($v['version']).' · UI '.self::e($v['ui']).'</span></footer>';
    }
    public static function end(): string
    {
        return '</section>'.self::footer().'</main><script defer src="/ui-asset.php?file=platform.js&amp;v='.self::e(self::version()['ui']).'"></script></body></html>';
    }
    public static function loginBody(string $form): string
    {
        return '<div class="bn-login-grid"><aside class="bn-login-aside"><div><div class="bn-eyebrow bn-eyebrow--inverse">همراه شما در مسیر تسهیلات</div><h2>همهٔ کارها،<br>در یک فضای روشن</h2><p>اطلاعات، درخواست‌ها و کارهای خود را با حساب مجاز سامانه پیگیری کنید.</p><div class="bn-login-points"><div class="bn-login-point"><i>۱</i><span>ورود امن و دسترسی کنترل‌شده</span></div><div class="bn-login-point"><i>۲</i><span>پیگیری روشن وضعیت کارها</span></div><div class="bn-login-point"><i>۳</i><span>دسترسی به سوابق و مدارک</span></div></div></div><span class="bn-version">Noeel '.self::e(self::version()['version']).'</span></aside><div class="bn-login-form">'.$form.'</div></div>';
    }
    public static function recoveryForm(string $csrfName,string $csrfValue,string $actionName,string $actionValue): string
    {
        return '<section class="bn-section"><div class="bn-alert bn-alert--warning"><strong>به‌روزرسانی ناتمام است</strong><p>اگر تغییر دیتابیس آغاز شده، ابتدا بکاپ دیتابیس پیش از همین آپدیت را بازیابی کنید. سپس فایل‌های نسخهٔ قبل را با این فرم بازگردانید.</p></div><form method="post"><input type="hidden" name="'.self::e($csrfName).'" value="'.self::e($csrfValue).'"><input type="hidden" name="'.self::e($actionName).'" value="'.self::e($actionValue).'"><label class="bn-checkbox"><input type="checkbox" name="database_restored" value="1"> بازیابی دیتابیس در صورت نیاز انجام شده است.</label><div class="bn-form-actions"><button class="bn-btn bn-btn--secondary" type="submit">بازیابی فایل‌های نسخهٔ قبل</button></div></form></section>';
    }
}
