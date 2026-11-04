# Babanoell Panel — Phase 1M

نسخه: **1.2.1**  
تاریخ: ۱۴۰۵/۰۶/۰۴ — ۲۰۲۶-۰۸-۲۶

سرویس `panel` دروازه و پوسته SSR فارسی/RTL پلتفرم بابانوئل است. این سرویس در فاز ۲ دیتابیس مستقل ندارد و برای هویت، نشست، نقش و دسترسی فقط با API سرویس `identity` ارتباط HTTP JSON برقرار می‌کند.

## امکانات این نسخه

- صفحه ورود SSR با CSRF و پیام‌های فارسی بازنویسی‌شده.
- نگهداری credentialهای Identity فقط در session سرور؛ هیچ access/refresh token وارد HTML، URL، localStorage یا JavaScript نمی‌شود.
- رمزگذاری credentialهای داخل PHP session با AES-256-GCM و کلید تصادفی نصب.
- اتصال کامل به `login`، `me`، `refresh` و `logout` سرویس Identity.
- Refresh خودکار در صورت انقضای access credential و پاک‌سازی نشست پس از 401 قطعی.
- داشبورد نقش‌محور؛ `super_admin` به صفحه وضعیت سامانه و updater دسترسی دارد.
- Sidebar/Header/Responsive کامل با Design System برند: `#1D3559`, `#D0E2F2`, `#EDA900`.
- تاریخ و ساعت نمایشی شمسی و بر اساس `Asia/Tehran`.
- `/health` و `/healthz`؛ healthz اتصال واقعی به Identity را بررسی می‌کند.
- Installer تحت وب برای cPanel؛ بدون Composer/npm/build.
- Updater تحت وب با validation، فایل‌های محافظت‌شده، بکاپ فایل‌ها و rollback فایل در خطا.
- صفحات 403/404 و وضعیت‌های خطا/خالی.

## مرزهای فاز ۲

ماژول‌های کسب‌وکار (`loans`, `documents`, `partners`, `finance`, `reports`) عمداً هنوز داخل Panel پیاده‌سازی نشده‌اند و در منو با وضعیت «به‌زودی» نمایش داده می‌شوند. مرحله بعد از بسته‌شدن 1M، فاز 2 یعنی `loans` است.

## نصب روی cPanel

1. یک ساب‌دامین مثل `panel.noeel.ir` بسازید.
2. ZIP را Extract کنید و محتویات پوشه `panel/` را در ریشه سرویس قرار دهید.
3. Document Root را ترجیحاً روی `.../panel/public` بگذارید. اگر ممکن نبود، `.htaccess` ریشه هم مسیر را محافظت و route می‌کند.
4. `https://panel.noeel.ir/install.php` را باز کنید.
5. Environment را ابتدا `staging` بگذارید و URL سرویس Identity را وارد کنید.
6. Installer فقط healthz سرویس Identity را بررسی می‌کند؛ هیچ رمز/توکن Identity درخواست نمی‌شود.
7. پس از نصب، `/health` و `/healthz` را بررسی کنید.

## نکته فعال‌سازی

کد Panel کامل است، اما ورود واقعی فقط وقتی کار می‌کند که Identity همان محیط با فلگ‌های مربوطه فعال باشد. Production در Identity 1.3.0 همچنان fail-closed است؛ برای پایان فاز ۲ ابتدا staging را فعال و تست کنید.


## رابط کاربری

نسخه 1.2.1 پنل با **Babanoell UI 1.3.0** همگام است و از همان پالت، لوگو، تایپوگرافی و font stack مشترک، کارت‌ها، فرم‌ها، پیام‌ها و الگوی واکنش‌گرا سرویس Identity استفاده می‌کند. CSS و JavaScript کاملاً در فایل‌های asset مستقل نگهداری می‌شوند.
