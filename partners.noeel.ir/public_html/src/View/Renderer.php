<?php
declare(strict_types=1);
namespace Babanoell\Partners\View;
use Babanoell\Partners\Support\PersianDateTime;
use Babanoell\Partners\Platform\Ui;
final class Renderer
{
 private static function appVersion():string{return self::e((string)Ui::version()['version']);}
 private static function uiVersion():string{return self::e((string)Ui::version()['ui']);}
 public static function e(string$v):string{return htmlspecialchars($v,ENT_QUOTES,'UTF-8');}
 public static function page(string$title,string$content,string$tag='سرویس شرکا'):string{$now=PersianDateTime::now();return'<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><meta name="robots" content="noindex,nofollow,noarchive"><meta name="theme-color" content="#1D3559"><title>'.self::e($title).' | پلتفرم بابانوئل</title>'.\Babanoell\Partners\Platform\Ui::fonts().'<link rel="stylesheet" href="/ui-asset.php?file=babanoell-ui.css&amp;v='.self::appVersion().'"></head><body><main class="bn-shell bn-shell--narrow"><header class="bn-brandbar"><div class="bn-lockup"><span class="bn-logo"><img src="/ui-asset.php?file=brand-logo.png&amp;v='.self::appVersion().'" alt="لوگوی بابانوئل" width="118" height="40"></span><div class="bn-brandtext"><div class="bn-brandtitle">مرکز مدیریت سرویس‌ها</div><div class="bn-brandmeta">هسته پذیرندگان، همکاری و بازار درخواست‌ها</div></div></div><div class="bn-brandtag"><span class="bn-brandtag-dot"></span><span>'.self::e($tag).'</span><span class="bn-version">Partners</span></div></header><section class="bn-card">'.$content.'</section><footer class="bn-footer"><span><strong>پلتفرم بابانوئل</strong> · سرویس شرکا</span><time datetime="'.self::e($now->format(DATE_ATOM)).'">'.self::e(PersianDateTime::full($now)).'</time><span>Partners '.self::appVersion().' · UI '.self::uiVersion().'</span></footer></main><script src="/ui-asset.php?file=babanoell-ui.js&amp;v='.self::appVersion().'" defer></script></body></html>';}
}
