# سرویس Integrations نوئل — نسخه 1.1.0

سرویس مستقل PHP 8.1+ با دیتابیس اختصاصی برای ارتباط با ارائه‌دهندگان خارجی. فاقد وابستگی به وردپرس و دیتابیس سایر سرویس‌ها است.

## دامنه و قابلیت‌ها

- ملی‌پیامک (MeliPayamak): ارسال الگوی SOAP با `SendByBaseNumber2` و دریافت شناسه پذیرش.
- شاهکار فینوتک: توکن OAuth2 و تطبیق موبایل با کد ملی.
- اعتبارسنجی ICS24/MyCredit: احراز هویت، ارسال درخواست، بررسی OTP، وضعیت و دریافت گزارش.
- GreenPay: توکن، isReady، درخواست OTP، انتقال `transferByPocket` (به‌صورت پیش‌فرض غیرفعال).
- احراز هویت بین‌سرویسی: امضای قدیمی `X-Noel-Signature` + امضای افزوده `X-Noel-Payload-Signature` روی بدنه.
- کنترل مجوز سرویس فراخوان، اعتبارسنجی داده، شناسه یکتای عملیات، ثبت وضعیت امن، قفل‌گذاری و محدودیت درخواست.
- عدم ثبت متن OTP، اطلاعات شخصی، کلید سرویس‌دهنده یا پاسخ‌های خام حساس در جداول عملیات.

## نصب اولیه

1. محتوای پوشه `integrations/` داخل ZIP را به Document Root سرویس `integrations.noeel.ir` منتقل کنید. توصیه امنیتی: Document Root به مسیر `public_html/public` اشاره کند. برای میزبانی با Document Root روی `public_html`، `.htaccess` و RewriteRule الزامی است؛ در Nginx حتماً دسترسی به `config`, `src`, `storage`, `database`, `tests` و `bin` مسدود شود.
2. دیتابیس مستقل خالی بسازید و PHP Extensions شامل `pdo_mysql`، `curl`، `soap`، `zip`، `json` و `openssl` را فعال کنید.
3. از `config/local.env.example.php` یک فایل `config/local.env.php` بسازید و `INTEGRATIONS_DB_*`، کلید طولانی و تصادفی `INTEGRATIONS_INSTALL_KEY` و `NOEEL_SERVICE_SHARED_SECRET` یکسان با سایر سرویس‌ها را تنظیم کنید.
4. مجوز فایل تنظیمات خصوصی را محدود کنید (`600` یا معادل امن هاست). اطلاعات واقعی ارائه‌دهندگان را فقط همان‌جا وارد کنید؛ هرگز در ZIP یا مخزن Git نگه ندارید.
5. `https://integrations.noeel.ir/install.php` را باز کنید و **کلید نصب اختصاصی** را وارد کنید. ابزار، جداول و `storage/installed.lock` را ایجاد می‌کند.
6. دسترسی عمومی به `/install.php` پس از نصب متوقف می‌شود. برای کاهش سطح حمله، فایل installer را پس از نصب تغییر نام/محدود کنید. `/health` و `/healthz` سرویس را بررسی کنید.
7. قبل از هر به‌روزرسانی بعدی، بکاپ مستقل از فایل‌ها و دیتابیس تهیه کنید. در نسخه 1.1.0، مرکز `/update.php` مانند سایر سرویس‌های نوئل فقط با حساب مدیر ارشد Identity باز می‌شود؛ تغییرات SQL در MySQL ذاتاً قابل Rollback خودکار نیستند. تغییرات این انتشار Migration جدیدی ندارند.

## اتصال OTP قراردادها

فایل `Partners 1.3.0` را بعد از نصب Integrations توسط updater خود سرویس Partners نصب کنید و در `partners/config/local.env.php` کلیدهای زیر را **اضافه** کنید (کل تنظیمات قبلی باید حفظ شوند):

```php
'PARTNERS_INTEGRATIONS_BASE_URL' => 'https://integrations.noeel.ir',
'PARTNERS_SMS_CONTRACT_BODY_ID' => '12345', // با Body ID تأییدشده واقعی جایگزین شود
```

Body ID مربوط به الگوی تاییدشده ملی‌پیامک باید دقیقاً یک متغیر برای کد شش‌رقمی داشته باشد (یا تطبیق آن در `IntegrationsClient` انجام شود). رمز قرارداد فقط بعد از پاسخ پذیرش واقعی ارائه‌دهنده «ارسال‌شده» تلقی می‌شود؛ خطای ارسال موجب ابطال رمز صادرشده می‌گردد. در محیط‌های تست، رمز صرفاً آزمایشی باقی می‌ماند.

## API v1 (داخل شبکه سرویس‌های مجاز)

تمام مسیرهای `/api/v1/*` نیازمند هدرهای `X-Noel-Service`, `X-Noel-Timestamp`, `X-Request-Id`, `X-Noel-Signature`, `X-Noel-Payload-Signature` هستند. برای POSTهای اثرگذار همچنین `Idempotency-Key` یکتای 16 تا 120 کاراکتری لازم است. HMAC تکمیلی (`v1=<hex>`) روی موارد زیر با secret مشترک تولید می‌شود (هر جزء با `\n` جدا شده):

```text
v1
<caller service>
<timestamp>
<request id>
<UPPERCASE method>
<path without query>
<sha256 hex of exact raw JSON body>
```

| درخواست | سرویس فراخوان مجاز | داده ضروری |
|---|---|---|
| POST `/api/v1/sms/send-pattern` | partners / identity / loans / finance | `to`, `pattern_id`, `variables` (آرایه رشته‌ها) |
| POST `/api/v1/shahkar/verify` | identity / loans | `national_code`, `mobile` |
| POST `/api/v1/credit/request` | loans | `national_code`, `mobile` |
| POST `/api/v1/credit/verify` | loans | `reference`, `otp` |
| POST `/api/v1/credit/status` | loans | `reference` |
| POST `/api/v1/credit/report` | loans | `report_code` |
| GET `/api/v1/greenpay/readiness` | finance | - |
| POST `/api/v1/greenpay/send-otp` | finance | `pocket_number`, `amount_rial` |
| POST `/api/v1/greenpay/transfer` | finance | `from_pocket`, `to_pocket`, `amount_rial`, `reference`, `otp` |

توجه: اجازه فراخوانی به‌معنای اتصال پنل‌های فعلی Identity/Loans/Finance به این APIها نیست. فعلاً اتصال مستقیم و عملیاتی در بسته همراه **Partners → SMS قرارداد** پیاده‌سازی شده است. مصرف سایر APIها توسط سرویس‌های مالک فرایند باید در بسته‌های بعدی انجام شود.

## نکات عملیاتی

- برای GreenPay اعتبارسنجی/تراکنش واقعی، آدرس‌های AUTH/PURCHASE دقیق ارائه‌دهنده، کلیدها، شماره ترمینال و شناسه پذیرنده را وارد کنید.
- مبلغ GreenPay طبق افزونه مرجع **ریال** است؛ هیچ تبدیل خودکار تومان/ریال انجام نمی‌شود.
- زمان `SendTime` در انتقال مالی با ساعت تازه **Asia/Tehran** محاسبه می‌شود؛ `INTEGRATIONS_GREENPAY_TIME_SHIFT_SECONDS` تنها برای جبران انحراف مستند بین سرور و ارائه‌دهنده است، نه جایگزین همگام‌سازی NTP.
- انتقال وجه به‌صورت پیش‌فرض با `INTEGRATIONS_GREENPAY_TRANSFERS_ENABLED=0` غیرفعال است. صرفاً بعد از بررسی تیم مالی، سازگاری پاسخ‌ها، تطبیق حواله و تست با ارائه‌دهنده فعال شود.
- پیش از فعال‌سازی عملیاتی، **تمام کلیدهایی که قبلاً به‌صورت Hardcoded در سورس وردپرسی نگهداری شده‌اند باید چرخش/تعویض شوند**. هیچ‌یک از آن‌ها در این بسته کپی نشده‌اند.
- خطای نامشخص شبکه/Timeout به معنی عدم انجام عملیات در سمت ارائه‌دهنده نیست. برای جلوگیری از ارسال/برداشت تکراری، درخواست با همان شناسه عملیات دوباره به‌صورت خودکار اجرا نمی‌شود؛ ابتدا نیاز به تطبیق وضعیت سمت ارائه‌دهنده است.
- برای انتقال مالی به دفترکل/تأییدیه دوطرفه Finance و وب‌هوک، توسعه بیشتر لازم است؛ API ارائه‌دهنده به‌تنهایی تضمین تسویه عملیاتی یا حسابداری نیست.
- اعتبارسنجی و تطبیق شماره تنها برای افراد مجاز با مبنای قانونی/رضایت معتبر استفاده شود.

## آزمون و محدودیت

`php tests/run.php` و تست اتصال `php tests/partners-interoperability.php` (در کنار پوشه سورس Partners برای توسعه). تست‌های PHP syntax در بسته اجرا شده‌اند. در محیط ساخت، `curl`, `soap`, `pdo_mysql` و سرویس‌دهندگان واقعی موجود نیستند؛ تست واقعی شبکه، MySQL و برگشت پاسخ ارائه‌دهنده انجام نشده است و فعال‌سازی Production مستلزم تنظیمات و تأیید تیم فنی است.

## رابط مدیریت یکپارچه (نسخه 1.1.0)

ظاهر مرکز به‌روزرسانی از قالب مشترک Partners/Panel، با لوگو و رنگ‌بندی یکسان استفاده می‌کند. به‌روزرسانی بعدی با ورود حساب super_admin در Identity، بررسی بسته، بکاپ و بازیابی کنترل‌شده انجام می‌شود. صفحه اصلی در مرورگر به `/update.php` هدایت می‌شود. مرحله نصب اولیه همچنان جداگانه و فقط پیش از ایجاد `installed.lock` فعال است.
