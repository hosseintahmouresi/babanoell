# Documents 1.0.0 — Phase 3

اولین Release مستقل سرویس مدارک بابانوئل.

- دیتابیس و migration tracking مستقل.
- شش migration forward-only برای documents، versions، links، reviews، audit و access tokens.
- Storage خصوصی با کلیدهای opaque و checksum SHA-256.
- Validation واقعی JPEG/PNG/WEBP/PDF و سقف قابل تنظیم فایل.
- Versioning غیرمخرب و Review تاریخچه‌دار.
- Permission + record-level access و fail-closed شدن خطای سرویس مالک رکورد.
- Audit کامل عملیات حساس.
- Secure direct stream و token access کوتاه‌مدت یک‌بارمصرف.
- Installer/Updater cPanel با محافظت از `config/local.env.php` و `storage/`.
- Babanoell UI 1.3.0، فارسی RTL، تاریخ شمسی و منطقه زمانی تهران.
