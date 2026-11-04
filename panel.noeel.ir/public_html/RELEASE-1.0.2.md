# Panel 1.0.2 — Identity UI Synchronization

تاریخ: ۱۴۰۵/۰۶/۰۴ — ۲۰۲۶-۰۸-۲۶

## هدف

یکپارچه‌سازی کامل زبان بصری سرویس Panel با رابط نهایی Identity (`UI 1.2.6`) بدون تغییر در قراردادهای احراز هویت، نشست یا تنظیمات محلی نصب‌شده.

## تغییرات

- استفاده مستقیم از توکن‌ها و اجزای پایه Identity UI 1.2.6: پالت، پس‌زمینه، سایه، radius، فرم، دکمه، کارت، پیام، جدول، status و footer.
- بازطراحی Login پنل با همان ساختار split-card سرویس Identity.
- بازطراحی Dashboard، System و Update Center با کامپوننت‌های `bn-*` مشترک.
- بازطراحی Sidebar و Topbar به‌عنوان extension همان Design System، نه یک تم مستقل.
- استفاده از لوگوی canonical PNG پلتفرم بابانوئل.
- بازنویسی متن‌های قابل مشاهده برای لحن کوتاه، دقیق و یکپارچه.
- نمایش تاریخ و ساعت رابط به‌صورت شمسی، با ارقام فارسی و منطقه زمانی `Asia/Tehran`.
- CSS و JavaScript کاملاً خارج از PHP و در assetهای مستقل.
- font stack رابط دقیقاً با Identity UI 1.2.6 همگام است.
- Assetها همچنان از `ui-asset.php` بارگذاری می‌شوند تا روی ساختارهای مختلف Document Root در cPanel پایدار باشند.

## سازگاری Updater

بسته همچنان با Updater نسخه 1.0.1 Panel سازگار است. مسیرهای `config/local.env.php`، `storage/` و installer نصب‌شده محافظت می‌شوند.

## کنترل کیفیت

- PHP lint روی تمام فایل‌های PHP: موفق
- Panel test suite: ۱۵ تست، ۰ خطا
- پالت canonical: `#1D3559`, `#D0E2F2`, `#EDA900`
- لوگوی بسته: همان فایل canonical ارسال‌شده
