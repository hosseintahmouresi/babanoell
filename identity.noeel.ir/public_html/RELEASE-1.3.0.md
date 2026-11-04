# Identity 1.3.0 — بستن فاز 1L و پیش‌نیاز فاز 1M

تاریخ: ۱۴۰۵/۰۶/۰۴ (۲۰۲۶-۰۸-۲۶)

- چرخهٔ نشست `login → me → refresh rotation → logout` تثبیت شده است.
- تشخیص استفادهٔ مجدد از refresh token و ابطال خانوادهٔ نشست فعال است.
- فعال‌سازی staging از local/test جدا شده و فقط با `AUTH_ALLOW_HTTP_LOGIN_IN_STAGING=true` ممکن است.
- production همچنان fail-closed است.
- بستهٔ updater هیچ‌وقت `config/local.env.php` یا `storage/` را بازنویسی نمی‌کند.
- این نسخه مرز احراز هویت مورد استفادهٔ سرویس `panel` در فاز 1M است.
- سازگاری Apache/FastCGI/cPanel برای عبور هدر `Authorization: Bearer …` اضافه شد تا `me/logout` روی هاست اشتراکی هدر را از دست ندهند.
