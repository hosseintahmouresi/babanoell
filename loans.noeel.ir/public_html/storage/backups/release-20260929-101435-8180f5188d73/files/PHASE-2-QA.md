# گزارش کنترل نهایی Phase 2 — 2026-08-26

## خروجی‌ها
- Identity 1.3.1
- Loans 1.0.1
- Panel 1.1.0

## تست‌های اجراشده در محیط ساخت

- Identity: `149` تست، `0` خطا.
- Identity migrations: `11` فایل معتبر و Version یکتا.
- Loans: `13` تست، `0` خطا.
- Loans migrations: `8` فایل معتبر و Version یکتا.
- Panel: `18` تست، `0` خطا.
- Legacy compatibility: `240` سناریوی Market/Leasing، `0` اختلاف با توابع Legacy.
- PHP syntax lint:
  - Identity: 183 فایل PHP
  - Loans: 49 فایل PHP
  - Panel: 24 فایل PHP

## کنترل‌های معماری

- DB مستقل برای Loans.
- Loans هیچ اتصال مستقیمی به DB سرویس Identity ندارد.
- احراز API Loans با Bearer و اعتبارسنجی از `/api/v1/auth/me` در Identity.
- مجوزهای `loans.*` با Migration forward-only به Identity اضافه شده‌اند.
- نسخه طرح در پرونده با `plan_version_id` Pin می‌شود.
- محاسبه با `calculation_run_id` و SHA-256 نتیجه ثبت می‌شود.
- Transition غیرمجاز Fail-Closed و Audit می‌شود.
- `config/local.env.php` و `storage/` توسط Updaterها محافظت می‌شوند.
- UI Panel/Loans بر Design System مشترک UI 1.2.6 است.

## نکته محیط تست

در محیط ساخت CLI، افزونه `pdo_mysql` و سرویس MySQL در دسترس نبود؛ بنابراین اجرای واقعی DDL روی MySQL محلی ممکن نبود. فایل‌های Migration از نظر قرارداد/نسخه و PHP lint بررسی شده‌اند و **اجرای واقعی MySQL توسط Installer روی Staging** بخشی از گیت استقرار است. اگر Installer هر Migration را رد کند، مرحله Panel نباید ادامه پیدا کند.
