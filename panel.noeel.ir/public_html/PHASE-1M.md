# معیار پذیرش فاز 1M

- [ ] نصب Panel با web installer بدون DB و بدون خط فرمان
- [ ] `/health` = `status: ok`
- [ ] `/healthz` = `identity: ok`
- [ ] Identity staging با فلگ staging فعال
- [ ] ورود با حساب واقعی staging
- [ ] مشاهده داشبورد نقش‌محور
- [ ] refresh خودکار بعد از انقضای access credential
- [ ] logout و بی‌اعتبار شدن نشست
- [ ] super_admin به `/system` و `/update.php` دسترسی دارد
- [ ] کاربر غیر super_admin به مسیرهای مدیریتی 403 می‌گیرد
- [ ] تست Chrome/Safari موبایل و دسکتاپ، RTL، فونت، کنتراست و responsive
- [ ] هیچ token/password در HTML، JS، URL یا log وجود ندارد
- [ ] production Identity هنوز غیرفعال می‌ماند تا بازبینی امنیتی نهایی
