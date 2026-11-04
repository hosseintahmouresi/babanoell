# Babanoell Identity 1.2.2

تاریخ: 2026-08-26

## اصلاح اضطراری Updater

- مسیر اسکن فایل‌های استخراج‌شده با کلاس مستقل `ExtractedPackageCollectorV122` اجرا می‌شود.
- رفع قطعی خطای `.htaccess` که در موتور قدیمی به `htaccess` تبدیل می‌شد.
- مقاوم در برابر OPcache قدیمی روی `UpdateTool.php`، چون اسکنر جدید نام فایل/کلاس مستقل دارد.
- فایل‌های محافظت‌شده مانند `config/local.env.php`، `storage/`، `public/install.php` و lock آن هنگام آپدیت حفظ می‌شوند.
- UI و Design System نسخه 1.2.x بدون تغییر در داده‌ها و تنظیمات محلی نصب می‌شود.
