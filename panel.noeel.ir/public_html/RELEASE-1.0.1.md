# Panel 1.0.1 — Asset Routing Repair

- رفع قطعی لود نشدن CSS، JavaScript، فونت و لوگو در cPanel با Document Rootهای متفاوت.
- افزودن `public/ui-asset.php` با allow-list، MIME صحیح، ETag و cache.
- همهٔ ارجاع‌های UI از مسیر ثابت `/assets/...` به gateway امن منتقل شدند.
- استایل‌ها همچنان کاملاً در فایل جداگانه `public/assets/panel.css` هستند.
- محیط پیش‌فرض نصب همچنان `staging` است و هیچ فلگ production فعال نمی‌شود.
