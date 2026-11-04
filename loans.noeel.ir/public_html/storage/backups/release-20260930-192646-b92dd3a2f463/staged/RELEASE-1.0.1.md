# Loans 1.0.1 — Database isolation repair

این نسخه یک اصلاح اجباری برای نصب Phase 2 است.

## اصلاحات

- جلوگیری قطعی از نصب Loans روی دیتابیس Identity پیش از اجرای هر migration.
- جدول رهگیری migration مستقل سرویس با نام `loans_schema_migrations` برای حذف collision نسخه‌ها بین سرویس‌ها.
- پشتیبانی امن از نصب نیمه‌تمام 1.0.0 در دیتابیس مخصوص Loans، فقط وقتی نام migration نیز دقیقاً متعلق به Loans باشد.
- پیام نصب شفاف‌تر درباره الزام دیتابیس مستقل.
- نسخه runtime و health به 1.0.1 ارتقا یافت.

## علت خطای 1005 / errno 150

Identity 1.3.1 یک migration با نسخه `202608260001` دارد. Loans 1.0.0 نیز migration اول خود را با همان نسخه ثبت می‌کرد و هر دو از جدول عمومی `schema_migrations` استفاده می‌کردند. اگر اشتباهاً دیتابیس Identity در installer سرویس Loans انتخاب می‌شد، migration اول Loans به‌عنوان «قبلاً اجراشده» تشخیص داده می‌شد، جدول `installment_plans` ساخته نمی‌شد و migration دوم هنگام ساخت Foreign Key شکست می‌خورد.

نسخه 1.0.1 هم دیتابیس مشترک را رد می‌کند و هم namespace رهگیری migration را مستقل کرده است.
