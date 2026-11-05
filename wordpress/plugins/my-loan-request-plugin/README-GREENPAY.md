# GreenPay / BKI Integration — Babanoell 8.4.6.43

این اتصال به‌صورت مستقل از گردش اصلی پرونده کار می‌کند و خطای GreenPay نباید وضعیت پرونده تسهیلاتی را Rollback کند.

## اصل ایمنی

- GreenPay بعد از نصب/آپدیت به‌صورت پیش‌فرض غیرفعال است.
- هیچ پرونده‌ای صرفاً با فعال شدن اتصال وارد کسری نمی‌شود؛ مدیر باید آن پرونده را صراحتاً «مشمول GreenPay» کند.
- فقط پرونده مشمول که وارد «تأیید مدارک» شده است امکان صف‌بندی دارد.
- `request_id` و `ref_number` یکتا هستند.
- تراکنش `success` یا `unknown` خودکار Retry نمی‌شود.
- عملیات مالی اصلی با Compare-And-Swap روی وضعیت قبلی انجام می‌شوند تا Worker/Cron و عملیات هم‌زمان مدیر نتوانند یک State را روی یکدیگر overwrite کنند.

## مبلغ

مبلغ GreenPay همان `facility_amount` پرونده است و برای پرونده‌های قدیمی فاقد آن از `loan_amount` استفاده می‌شود. مبلغ قبل از ارسال به عدد صحیح ریال تبدیل می‌شود و هیچ تبدیل ×10 انجام نمی‌شود.

## Snapshot تراکنش

در زمان صف‌بندی، این موارد برای همان تراکنش Freeze می‌شوند:

- مبلغ
- FromPocketNumber
- ToPocketNumber
- Environment
- Username غیرمحرمانه
- Terminal Number
- Merchant ID
- Auth/Purchase Base URL
- Timeout و تنظیمات OTP

Password، API Key و OTP داخل Snapshot ذخیره نمی‌شوند. فقط HMAC/اثر انگشت Credentialها نگهداری می‌شود. اگر Credentialها بعد از صف‌بندی تغییر کنند، تراکنش Fail-Closed می‌شود تا تنظیمات قدیم و جدید با هم مخلوط نشوند. برای تراکنش `failed`، Retry صریح مدیر Snapshot جدید می‌سازد.

## وضعیت‌ها

- `not_started`: هنوز صف‌بندی نشده
- `queued`: آماده Worker
- `processing`: Worker/تأیید OTP تراکنش را Claim کرده
- `awaiting_otp`: OTP واقعی ارسال شده و منتظر ورود مدیر است
- `success`: کسر قطعی موفق
- `failed`: شکست قطعی؛ Retry صریح مدیر مجاز است
- `unknown`: نتیجه انتقال نامعلوم؛ Retry مستقیم ممنوع است
- `not_applicable`: پرونده از GreenPay خارج شده

## Unknown / استعلام دستی

اگر `transferByPocket` با Timeout، Transport Error، 5xx یا پاسخ مبهم روبه‌رو شود، وضعیت `unknown` می‌شود. برای جلوگیری از دوبارکسر، هیچ Retry خودکاری انجام نمی‌شود.

در صفحه «مدیریت GreenPay» مدیر باید ابتدا از پنل/پشتیبانی GreenPay با `RefNumber` و در صورت وجود `provider_reference` استعلام بگیرد و سپس نتیجه قطعی را ثبت کند:

- «کسر انجام شده است» → تراکنش به `success` می‌رود.
- «کسر انجام نشده است» → تراکنش به `failed` می‌رود و پس از آن Retry صریح مجاز می‌شود.

ثبت نتیجه نیازمند توضیح و تأیید صریح مدیر است و زمان/کاربر ثبت‌کننده در رکورد مالی نگهداری می‌شود.

## تنظیمات لازم

مسیر وردپرس: **درخواست‌های تسهیلات → تنظیمات API → اتصال GreenPay / BKI**

برای Production طبق Contract فعلی سورس این موارد لازم‌اند:

1. Username
2. Password
3. API Key
4. Terminal Number
5. Merchant ID
6. FromPocketNumber واقعی یا Mapping معتبر پرونده/متقاضی به Pocket
7. Auth Base URL رسمی Production
8. Purchase Base URL رسمی Production

`ToPocketNumber` فقط در صورتی می‌تواند خالی باشد که قرارداد رسمی GreenPay این حالت را مجاز بداند. نسخه 8.4.6.43 مقدار واقعی Snapshot همان تراکنش را به `transferByPocket` می‌فرستد.

### مهم: Username و Password به‌تنهایی کافی نیست

Endpoint دریافت Token در پیاده‌سازی فعلی Payload شامل `Username`، `Password` و `ApiKey` دارد و Endpointهای خرید نیز به `TerminalNumber` و `MerchantID` نیاز دارند. بنابراین اگر فقط Username/Password تحویل گرفته‌اید، برای Production باید حداقل API Key، Terminal Number، Merchant ID و آدرس‌های رسمی Production/اطلاعات Pocket را نیز از GreenPay بگیرید یا از قرارداد/پلاگین رسمی موجود استخراج کنید.

## Sandbox — پروفایل تست خرید آنلاین 1023

برای اطلاعات فنی تحویلی GreenPay، نسخه 8.4.6.43 یک پروفایل تست قفل‌شده دارد. با فعال بودن `test_profile_1023` در محیط Test:

- Username پذیرنده تست از فایل «پذیرنده تست.xlsx» استفاده می‌شود.
- Password مؤثر Wallet API همیشه `1` است؛ مقدار Password عملیاتی ذخیره‌شده overwrite نمی‌شود.
- API Key باید از فایل «پذیرنده تست.xlsx» در تنظیمات امن وارد شود و عمداً داخل سورس Hard-code نشده است.
- Terminal Number و Merchant ID از همان فایل پذیرنده تست اعمال می‌شوند.
- FromPocketNumber همیشه `1262010000002551` است.
- ToPocketNumber همیشه رشته خالی است.
- OTP همیشه `123456` است.
- `sendPurchaseOTP` در این پروفایل اصلاً درخواست شبکه نمی‌زند و Bypass می‌شود.
- بافر زمان OTP برای این پروفایل صفر است؛ `SendTime` در زمان انتقال از ساعت تهران ساخته می‌شود.
- برای Wallet API تست، نسخه 8.4.6.43 دقیقاً Endpointهای موجود در پلاگین مرجع تحویلی GreenPay را استفاده می‌کند: `https://wallet.bki.ir/walletbackend/api/sandbox` و `https://wallet.bki.ir/walletbackend/api/purchase`. مقدار `http://wlt-test-sandbox.htsc.ir` در فایل پذیرنده تست به عنوان Base API فرض نمی‌شود.

عبارت «خرید آنلاین 1023» در مستندات تحویلی به‌عنوان نام/سناریوی پروفایل تست نگهداری می‌شود. در پلاگین نمونه هیچ پارامتر Payload با نام ServiceCode/1023 وجود ندارد؛ بنابراین عدد 1023 به‌صورت حدسی به درخواست مالی اضافه نشده است.

### GreenPass / پیامک

Username مبتنی بر کد ملی و گذرواژه GreenPass که جداگانه تحویل شده‌اند مربوط به سرویس پیامک/GreenPass هستند. Contract پلاگین Wallet برای `submit`، `sendPurchaseOTP` و `transferByPocket` از این Credentialها استفاده نمی‌کند؛ بنابراین آن‌ها نباید در فیلد Username/Password کیف پول وارد شوند.

## ترتیب امن راه‌اندازی Production

1. ابتدا Environment را روی Test نگه دارید و Credentialهای کامل را وارد کنید.
2. تنظیمات را ذخیره کنید.
3. از «مدیریت GreenPay» دکمه «تست اتصال GreenPay» را اجرا کنید. تست باید Token و `isReady` را تأیید کند.
4. یک پرونده آزمایشی/کنترل‌شده را مشمول کنید و Flow Sandbox را بررسی کنید.
5. آدرس‌های رسمی Production، API Key، Terminal Number، Merchant ID و Pocketهای واقعی را از GreenPay تأیید کنید.
6. Environment را روی Production ببرید. افزونه در صورت ناقص بودن فیلدها، URL سندباکس، HTTP یا Pocket تستی اجازه فعال‌سازی نمی‌دهد.
7. مجدداً Test Connection را اجرا کنید.
8. فقط پس از موفقیت تست، گزینه فعال‌سازی اتصال را روشن کنید.
9. ابتدا با یک پرونده و مبلغ کنترل‌شده فرآیند واقعی را بررسی کنید؛ سپس Auto Debit را برای عملیات روزمره استفاده کنید.

## Mapping Pocket هر متقاضی

اگر هر متقاضی GreenPay Pocket جداگانه دارد، از فیلتر زیر استفاده کنید و فقط Pocket تأییدشده ۱۶ رقمی برگردانید. در 8.4.6.43 کل Object پرونده به Filter داده می‌شود تا بتوان Mapping معتبر بر اساس داده پرونده انجام داد:

```php
add_filter('mlr_greenpay_from_pocket_number', function ($default, $request, $settings) {
    // Return the verified 16-digit GreenPay pocket for this exact request/applicant.
    return $default;
}, 10, 3);
```

از حدس‌زدن Pocket بر اساس کد ملی/موبایل بدون API یا Mapping رسمی خودداری کنید.

## WP-Cron

صف خودکار به WP-Cron متکی است. اگر `DISABLE_WP_CRON` فعال است، Cron واقعی سرور باید `wp-cron.php` را اجرا کند. Worker یک تراکنش `queued` را به‌صورت اتمیک Claim می‌کند.

## لاگ و محرمانگی

- Password، API Key، Token و OTP در Snapshot ذخیره نمی‌شوند.
- پاسخ‌های JSON بر اساس نام کلید Redact می‌شوند.
- پاسخ خام/non-JSON نیز برای Token/Password/API Key/OTP و شماره‌های ۱۶ رقمی Redact می‌شود.

## Contract رسمی GreenPay

این افزونه Payload یا ResultCode ناشناخته را حدس نمی‌زند. اگر GreenPay مستند رسمی جدیدی برای Success Code، Lookup/Reconciliation API، Pocket Resolution یا پارامترهای اضافی ارائه کند، همان Contract باید مبنای توسعه بعدی قرار گیرد.


## جریان OTP بدون رفرش — 8.4.6.85

در صفحه «مدیریت GreenPay»، شروع کسری/ارسال OTP و ورود کد دیگر با POST به `admin-post.php` و Reload صفحه انجام نمی‌شود. دکمه «ارسال کد و ادامه» از AJAX استفاده می‌کند؛ پس از پاسخ `sendPurchaseOTP` پاپ‌آپ OTP همان‌جا باز می‌شود، مدیر کد ۶ رقمی را وارد می‌کند و `transferByPocket` نیز از همان پاپ‌آپ اجرا می‌شود. ارسال مجدد OTP هم بدون Reload است.

برای مشکل اختلاف ساعت، ترتیب منبع `SendTime` این است: زمان صریح برگشتی GreenPay → هدر استاندارد HTTP `Date` سرور GreenPay → ساعت تهران روی سرور سایت با شیفت اضطراری تنظیم‌شده. مقدار پیش‌فرض شیفت اضطراری `+30` ثانیه است چون پشتیبانی GreenPay اختلاف حدود ۳۰ ثانیه گزارش کرده؛ اگر API زمان خودش را برگرداند این شیفت استفاده نمی‌شود. تنظیم آن در «تنظیمات API → GreenPay» قابل تغییر از `-120` تا `+120` ثانیه است.


## اصلاح پنجره زمانی انتقال — 8.4.6.86

`SendTime` دیگر از زمان ارسال OTP نگهداری و برای `transferByPocket` استفاده نمی‌شود. چون اپراتور ممکن است بیش از پنجره کوتاه GreenPay صرف خواندن و ورود کد کند، در لحظه کلیک «تأیید کد و انجام کسری» یک `SendTime` تازه با ساعت تهران ساخته می‌شود و تصحیح ساعت تنظیم‌شده (پیش‌فرض +۳۰ ثانیه طبق اعلام پشتیبانی GreenPay) روی همان زمان اعمال می‌شود. این تصحیح از تنظیم جاری خوانده می‌شود و به Snapshot قدیمی تراکنش وابسته نیست. فقط در صورت رد صریح 4xx مربوط به پنجره زمانی، یک Retry امن با زمان تازه انجام می‌شود؛ خطاهای شبکه/5xx/نتیجه مبهم هرگز خودکار Retry نمی‌شوند.
