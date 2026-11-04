# Phase 3 — Documents

هدف Phase 3 ایجاد مرجع امن و مستقل مدارک برای کل سامانه است.

## دامنه تکمیل‌شده

- Storage خصوصی و محافظت‌شده.
- MIME/extension/size/content validation و SHA-256 integrity.
- نسخه‌بندی فایل و نگهداری تاریخچه.
- لینک رکوردی عمومی با `entity_type` و `entity_id`.
- Role + Ownership/record access؛ برای پرونده تسهیلات از Loans API.
- Review و الزام Reason در رد.
- Archive بدون حذف فیزیکی تاریخچه.
- Audit برای upload/replace/review/archive/download/preview/access-token.
- لینک کوتاه‌مدت، یک‌بارمصرف و Hash‌شده برای دسترسی فایل.
- Checklist مدارک الزامی بر اساس Plan Version پین‌شده در Loans.
- Gate گردش‌کار Loans برای `docs_verified` و `granted`.
- ماژول کامل Documents در Panel و تعریف مدارک الزامی در فرم نسخه طرح.

## معیار پایان روی Staging

یک پرونده آزمایشی باید بتواند: مدارک الزامی را دریافت کند، فایل معتبر بارگذاری کند، نسخه جدید بسازد، نسخه قبلی را حفظ کند، Review شود، دانلود و Preview Audit شوند، و تا زمانی که همه مدارک الزامی جدیدترین Upload تأیید نشده‌اند Transition مدارکی رد شود. پس از تأیید همه موارد، Transition باید مجاز باشد.
