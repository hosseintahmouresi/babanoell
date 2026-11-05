# آنالیز کامل افزونه «درخواست تسهیلات بابانوئل» (MLR)

## ۱. شناسه افزونه
- **نام:** درخواست تسهیلات بابانوئل  
- **نسخه:** 8.4.2.5 / 8.4.3.0 (ثابت در کد)  
- **نویسنده:** شرکت پدیده تجارت  
- **ورودی اصلی:** `my-loan-request-plugin.php`

---

## ۲. ساختار پوشه‌ها و فایل‌ها

```
my-loan-request-plugin/
├── my-loan-request-plugin.php   # بوت‌استرپ، منوها، ثبت هوک‌ها، احراز شاهکار، PDF/Excel
├── includes/
│   ├── install.php              # تعریف جداول (استفاده در نصب قدیمی)
│   ├── functions.php            # توابع مشترک، تاریخ شمسی، جدول درخواست‌ها، مدارک، رویداد/پیام، ICS24
│   ├── loan-calculations.php    # pmt, pv, calculateMarketLoan, calculateLeasingLoan
│   ├── sms-functions.php        # MLR_MeliPayamak_Provider، ارسال پیامک بر اساس وضعیت
│   ├── ajax-handlers.php        # فقط require همه فایل‌های ajax
│   ├── ajax/                    # همه اکشن‌های AJAX (۲۵+ فایل)
│   ├── admin-pages/             # صفحات ادمین (۱۰ فایل)
│   └── shortcodes/              # شورتکدهای فرانت (۱۸ فایل)
└── assets/
    ├── js/                      # اسکریپت‌های فرانت و ادمین
    ├── css/                     # استایل‌های فرانت و ادمین
    └── sounds/                  # صدای اعلان
```

---

## ۳. جداول دیتابیس (پیشوند: `$wpdb->prefix`)

| جدول | کاربرد |
|------|--------|
| `loan_requests` | درخواست‌های تسهیلات (متقاضی، مبلغ، طرح، وضعیت، تسویه، مدارک، ضامن، ارجاع به کارشناس بانک و…) |
| `installment_plans` | طرح‌های اقساطی (نوع بازار/لیزینگ، تنظیمات، فیلدهای نمایش، مدارک لازم) |
| `cooperation_requests` | درخواست‌های همکاری حقیقی/حقوقی (وضعیت، قرارداد، OTP، امضا) |
| `mlr_settlement_cycles` | سیکل‌های تسویه به ازای هر کاربر (پذیرنده) |
| `mlr_request_events` | رویدادهای هر درخواست (تغییر وضعیت، تسویه و…) |
| `mlr_request_messages` | پیام‌ها و چت هر درخواست (با پیوست) |
| `mlr_admin_notifications` | اعلان‌های ادمین (روزانه پاک می‌شوند) |

مهاجرت اسکیمان در `mlr_check_and_update_database_schema()` و در نصب در `mlr_install_and_update()` انجام می‌شود.

---

## ۴. جریان اصلی «درخواست تسهیلات»

1. **ثبت درخواست (فرانت)**  
   - شورتکد: `[mlr_loan_request_form]`  
   - فایل: `shortcodes/loan-request-form.php` + `ajax/submit-loan-request.php`  
   - مراحل: احراز شاهکار (کد ملی + موبایل) → انتخاب طرح و محاسبه اقساط → (اختیاری) اعتبارسنجی ICS24 → اطلاعات فردی → مدارک → ارسال AJAX با `mlr_submit_loan_request`  
   - وضعیت اولیه: **«در حال بررسی»**

2. **وضعیت‌های درخواست (status)**  
   - ترتیب منطقی:  
     `در حال بررسی` → `نقص مدارک` | `رد شده` | `تأیید کارشناس` → `تکمیل مدارک` → `تأیید مدارک` → `اعطا شده`  
   - قوانین تغییر در `admin-pages/loan-requests.php` داخل `$status_transitions`.  
   - برای «تأیید کارشناس» انتخاب `assigned_bank_user_id` اجباری است.

3. **تسویه (بعد از اعطا)**  
   - `settlement_status`: `منتظر تعیین تاریخ` → `در انتظار تسویه` → `تسویه شده`  
   - سیکل‌ها در `mlr_settlement_cycles` و ارتباط با `settlement_cycle_id` روی درخواست.

4. **محاسبات**  
   - `loan-calculations.php`:  
     - `calculateMarketLoan`: طرح نوع بازار (نرخ ماهانه، پیش‌پرداخت، هزینه‌ها، کمیسیون، چک ضمانت و…)  
     - `calculateLeasingLoan`: طرح لیزینگ (نرخ اسمی/موثر، سهم اعتبار، اقساط، خالص واریز، کمیسیون پذیرنده و…)

---

## ۵. شورتکدها (فرانت)

| شورتکد | فایل | توضیح |
|--------|------|--------|
| `[mlr_user_loan_requests]` | user-loan-requests.php | همه درخواست‌های کاربر (پذیرنده) |
| `[mlr_needs_review_requests]` | needs-review-requests.php | فقط وضعیت «نقص مدارک» |
| `[mlr_processing_requests]` | processing-requests.php | در حال بررسی + تأیید کارشناس و… |
| `[mlr_granted_user_requests]` | granted-user-requests.php | وضعیت «اعطا شده» |
| `[mlr_user_settlement_cycles]` | user-settlement-cycles.php | سیکل‌های تسویه کاربر |
| `[mlr_settlement_cycles]` | settlement-cycles.php | همان سیکل‌ها (alias) |
| `[mlr_bank_expert_dashboard]` | bank-expert-dashboard.php | داشبورد کارشناس بانک (درخواست‌های ارجاع‌شده) |
| `[mlr_granted_expert_dashboard]` | granted-expert-dashboard.php | داشبورد اعطا شده برای کارشناس |
| `[mlr_verified_docs_dashboard]` | verified-docs-dashboard.php | داشبورد تأیید مدارک |
| `[mlr_loan_request_form]` | loan-request-form.php | فرم ثبت درخواست تسهیلات (استپ‌ها) |
| `[mlr_loan_calculator]` | loan-calculator.php | فقط محاسبه‌گر اقساط |
| `[mlr_individual_request_form]` | cooperation-request-form.php | فرم همکاری حقیقی |
| `[mlr_legal_request_form]` | cooperation-request-form.php | فرم همکاری حقوقی |
| `[mlr_cooperation_contract_viewer]` | cooperation-contract-viewer.php | مشاهده/امضای قرارداد همکاری (با OTP) |
| `[mlr_complete_request]` | complete-request.php | تکمیل مدارک پس از اعطا |
| `[mlr_edit_requests]` | edit-requests.php | ویرایش درخواست‌ها |
| `[mlr_user_stats]` | user-stats.php | آمار کاربر |
| `[mlr_invoice]` | invoice.php | فاکتور (با id درخواست) |

جدول و فیلتر و مودال جزئیات در فرانت از **functions.php** با `mlr_render_user_requests_table()` و `mlr_render_expert_dashboard_html()` رندر می‌شوند؛ داده از AJAX مثل `mlr_get_user_requests` و `mlr_get_bank_expert_requests` و… می‌آید.

---

## ۶. صفحات ادمین (پنل وردپرس)

- **عملیات تسهیلات** (`mlr-loan-requests`):  
  - زیرمنوها: همه‌درخواست‌ها، در حال بررسی، نقص مدارک، رد شده، تأیید کارشناس، تکمیل مدارک، تأیید مدارک، اعطا شده، در انتظار تسویه، تسویه شده، طرح‌های اقساطی، تنظیمات API.  
  - فایل‌ها: `loan-requests.php`, `granted-requests.php`, `unsettled-requests.php`, `settled-requests.php`, `installment-plans.php` و API در خود فایل اصلی.

- **مدیریت همکاری** (`mlr-cooperation-main`):  
  - درخواست‌های همکاری، ویرایشگر فرم، تنظیمات قرارداد.  
  - فایل‌ها: `cooperation-requests.php`, `form-editor.php`, `cooperation-contract-settings.php`.

- **تنظیمات پیامک** (`mlr-sms-settings`): تنظیمات پیامک + مشاهده لاگ.  
  - فایل‌ها: `sms-settings.php`, `log-viewer.php`.

جزئیات هر درخواست در ادمین از `ajax/get-request-details.php` با اکشن `mlr_get_request_details` و nonce ادمین لود می‌شود؛ فرم ضامن در ادمین با `mlr-guarantor-form-admin` و `admin.js` ارسال می‌شود.

---

## ۷. اکشن‌های AJAX (خلاصه)

- **فرانت / کاربر:**  
  `mlr_get_user_requests`, `mlr_get_frontend_request_details`, `mlr_submit_loan_request`, `mlr_submit_completed_docs`, `mlr_save_guarantor_info`, `mlr_add_request_message`, `mlr_get_request_messages`, `mlr_get_user_cycle_details`, `mlr_verify_shahkar`, `mlr_credit_request_otp`, `mlr_credit_verify_otp`, `mlr_credit_check_status`, `mlr_frontend_calculate_loan`, و…

- **ادمین:**  
  `mlr_get_request_details`, `mlr_add_comment`, `mlr_admin_calculate_loan`, `mlr_filter_user_requests`, `mlr_save_guarantor_info`, `mlr_save_expert_notes`, `mlr_submit_bank_expert_review`, `mlr_update_invoice`, `mlr_get_plan_documents`, `mlr_upload_additional_docs`, `mlr_get_sms_credit`, و ناتیفیکیشن‌ها.

- **همکاری:**  
  `mlr_submit_cooperation_request`, `mlr_get_cooperation_request_details`, `mlr_update_cooperation_status`, OTP قرارداد و…

همه از طریق `includes/ajax-handlers.php` لود می‌شوند.

---

## ۸. امنیت و دسترسی

- **Nonceها:**  
  `mlr_frontend_nonce`, `mlr_ajax_nonce_action`, `mlr_user_requests_nonce`, `mlr_loan_request_action`, `mlr_user_cycle_nonce` و غیره.  
  در `functions.php` تابع `mlr_verify_any_nonce()` چند nonce را برای هندلرهای مشترک چک می‌کند.

- **دسترسی به درخواست:**  
  `mlr_can_access_loan_request()`: ادمین، یا مالک درخواست (`user_id`)، یا کارشناس بانک ارجاع‌شده (`assigned_bank_user_id`).

- **قابلیت‌ها:**  
  ادمین: `manage_options`. نقش `bank` برای داشبورد کارشناس بانک و دسترسی به درخواست‌های ارجاع‌شده.

---

## ۹. یکپارچه‌سازیهای خارجی

- **شاهکار (فینوتک):** تطابق کد ملی و موبایل در فرم ثبت درخواست؛ از تنظیمات API در ادمین.
- **ICS24:** درخواست OTP، تأیید OTP، وضعیت و دریافت گزارش اعتبارسنجی؛ ثابت در `functions.php` (MLR_ICS24_*).
- **پیامک (ملی‌پیمک):** کلاس `MLR_MeliPayamak_Provider` در `sms-functions.php`؛ ارسال بر اساس وضعیت تسهیلات/همکاری و OTP با الگو و متغیرها از تنظیمات پیامک.

---

## ۱۰. دارایی‌های فرانت و ادمین

- **JS:**  
  `mlr-user-requests.js` (لیست، مودال، فیلتر، ضامن، تکمیل مدارک، پیام)، `loan-request-form.js`, `loan-calculator.js`, `cooperation-form.js`, `contract-viewer.js`, `bank-expert-dashboard.js`, `verified-docs-dashboard.js`, `expert-dashboard.js`, `admin.js`, `admin-notifications.js`, `jalali-datepicker.js`, `frontend.js`.

- **CSS:**  
  `frontend.css`, `admin.css`, `loan-request-form.css`, `mlr-modal-unified.css`, `contract-viewer.css`, `bank-expert-dashboard.css`, `verified-docs-dashboard.css`, `expert-dashboard.css`, `admin-notifications.css`.

اسکریپت/استایل فقط در صفحاتی که شورتکد مربوطه یا صفحه ادمین مربوطه لود شده باشد، enqueue می‌شوند (شرط `has_shortcode` / `$hook` در فایل اصلی).

---

## ۱۱. نکات مهم برای توسعه و رفع باگ

- **ضامن:**  
  ذخیره در `loan_requests.guarantor_info` (JSON). فرم فرانت با `data-save-url`, `request_id`, `blog_id`, `db_prefix` و در صورت نیاز `switch_to_blog` و استفاده از همان پیشوند جدول در `save-guarantor-info.php` تا در هر محیط (و در صورت multisite) درست کار کند.

- **تاریخ:**  
  تبدیل شمسی/میلادی با `mlr_jalali_to_gregorian` و `mlr_gregorian_to_jalali` در `functions.php`؛ در فیلترها و خروجی‌ها استفاده می‌شود.

- **لاگ:**  
  `mlr_log()` در `wp-content/uploads/mlr-logs/mlr_debug.log`؛ حساس‌ها ماسک می‌شوند. پاکسازی روزانه اعلان‌ها با کرون `mlr_daily_notification_purge`.

- **چاپ و خروجی:**  
  گزارش PDF اعتبار/پرونده و خروجی اکسل از همان فایل اصلی با `mlr_process_pdf_export` و `mlr_process_excel_export` و `mlr_process_dossier_excel_export`؛ فیلترها مشابه صفحه لیست ادمین اعمال می‌شوند.

این سند خلاصهٔ آنالیز و درک از کل فایل‌ها و روند افزونه است و می‌توان آن را به‌روز کرد.
