<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Renders the SMS Settings page in the admin panel.
 */
function mlr_sms_settings_page() {
    // Check user capabilities
    if (!current_user_can('manage_options')) {
        wp_die('شما دسترسی لازم برای مشاهده این صفحه را ندارید.');
    }

    // Get defined statuses and placeholders for different sections
    $cooperation_statuses = mlr_get_cooperation_statuses();
    $cooperation_placeholders = mlr_get_cooperation_placeholders();
    $loan_statuses = mlr_get_loan_statuses();
    $loan_placeholders = mlr_get_loan_placeholders();
    // Define placeholders relevant for OTP context
    $otp_placeholders = [
        '[[otp_code]]' => 'کد تایید ۶ رقمی',
        '[[applicant_name]]' => 'نام متقاضی (مالک/رابط)',
        // Add other relevant placeholders from cooperation_placeholders if needed, e.g., store/company name
        '[[store_name]]' => 'نام فروشگاه',
        '[[company_name]]' => 'نام شرکت',
        '[[contract_link]]' => 'توکن امن لینک مشاهده قرارداد (سازگار با پترن فعلی همکاری)',
        '[[contract_url]]' => 'آدرس کامل مشاهده قرارداد تسهیلات',
    ];

    // Get currently saved SMS settings from WordPress options
    $options = get_option('mlr_sms_settings', []); // Default to empty array if not set

    // Start HTML output
    ?>
    <div class="wrap mlr-sms-settings-wrap">
        <div class="mlr-page-header">
            <h1>تنظیمات ملی پیامک</h1>
            <?php // Credit Checker Section ?>
            <div class="mlr-credit-checker-wrapper">
                <div id="mlr_credit_display"><strong>اعتبار حساب:</strong> <span id="mlr_credit_value">---</span></div>
                <button type="button" class="button" id="mlr_check_credit_btn"><span class="dashicons dashicons-update"></span> بررسی اعتبار</button>
                <span class="spinner"></span> <?php // Spinner for loading state ?>
            </div>
        </div>
        <p>در این بخش می‌توانید اطلاعات پنل پیامکی و الگوهای پیامک برای رویدادهای مختلف را مدیریت کنید.</p>

        <?php // Settings Form - points to options.php for saving ?>
        <form method="post" action="options.php">
            <?php settings_fields('mlr_sms_settings_group'); // Output nonce, action, and option_page fields for the settings group ?>

            <?php // --- SMS Panel Credentials Card --- ?>
            <div class="mlr-settings-card">
                <h2>اطلاعات پنل پیامک</h2>
                <table class="form-table">
                    <tbody>
                        <tr>
                            <th scope="row"><label for="mlr_sms_username">نام کاربری</label></th>
                            <td><input type="text" id="mlr_sms_username" name="mlr_sms_settings[username]" value="<?php echo esc_attr($options['username'] ?? ''); ?>" class="regular-text ltr" required aria-required="true"/></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="mlr_sms_password">رمز عبور</label></th>
                            <td><input type="password" id="mlr_sms_password" name="mlr_sms_settings[password]" value="" placeholder="<?php echo !empty($options['password']) ? '•••••••• (برای تغییر، رمز جدید را وارد کنید)' : ''; ?>" class="regular-text ltr" /></td>
                        </tr>
                    </tbody>
                </table>
                 <p class="description">برای اطمینان از صحت اطلاعات، پس از ذخیره، اعتبار حساب را بررسی کنید.</p>
            </div>

            <?php // --- OTP Settings Card --- ?>
            <div class="mlr-settings-card">
                <h2>الگوی پیامک کد تایید (OTP) امضای قرارداد</h2>
                 <div class="mlr-variable-guide">
                    <strong>متغیرهای در دسترس:</strong>
                    <div class="mlr-placeholders-list">
                        <?php foreach($otp_placeholders as $ph => $desc): ?><span class="mlr-placeholder-tag" title="<?php echo esc_attr($desc); ?>"><?php echo esc_html($ph); ?></span><?php endforeach; ?>
                    </div>
                 </div>
                 <div class="mlr-status-settings-grid" style="grid-template-columns: 1fr;"> <?php // Force single column layout for OTP settings ?>
                     <?php // Get OTP settings safely, default to empty array
                     $otp_settings = $options['otp_settings']['cooperation_sign'] ?? [];
                     $otp_enabled = isset($otp_settings['enabled']) && $otp_settings['enabled'] == 1;
                     ?>
                     <div class="mlr-status-item">
                        <div class="mlr-status-item-header">
                            <h3>کد تایید امضای قرارداد همکاری (الگوی پایه)</h3>
                            <label class="mlr-switch" title="فعال/غیرفعال کردن ارسال OTP">
                                <input type="checkbox" name="mlr_sms_settings[otp_settings][cooperation_sign][enabled]" value="1" <?php checked($otp_enabled); ?>>
                                <span class="mlr-slider"></span>
                            </label>
                        </div>
                        <div class="mlr-status-item-body">
                            <div class="mlr-pattern-field">
                                <label for="otp_body_id">شناسه قالب (Body ID) <span style="color:red;">*</span></label>
                                <input type="text" id="otp_body_id" name="mlr_sms_settings[otp_settings][cooperation_sign][bodyId]" value="<?php echo esc_attr($otp_settings['bodyId'] ?? ''); ?>" class="small-text ltr" aria-describedby="otp_body_id_desc">
                                <p id="otp_body_id_desc" class="description">شناسه الگوی تعریف‌شده در پنل ملی‌پیامک. قرارداد همکاری و قرارداد دیجیتال تسهیلات هر دو برای ارسال OTP دقیقاً از همین الگو و همین تعداد متغیر استفاده می‌کنند.</p>
                            </div>
                            <?php // *** ADDED: Variable fields for OTP *** ?>
                            <div class="mlr-variable-fields">
                                <label>محتوای متغیرها (مطابق الگوی ملی‌پیامک)</label>
                                <p class="description">مقدار `[[otp_code]]` به صورت خودکار جایگزین کد ۶ رقمی می‌شود. قرارداد دیجیتال تسهیلات دقیقاً همین پترن OTP همکاری را استفاده می‌کند. لینک قرارداد جداگانه و با همان پترن «تایید مدارک» همکاری ارسال می‌شود؛ بنابراین به پترن OTP هیچ متغیر لینک اضافه نکنید.</p>
                                <input type="text" name="mlr_sms_settings[otp_settings][cooperation_sign][var_0]" value="<?php echo esc_attr($otp_settings['var_0'] ?? ''); ?>" placeholder="محتوای پارامتر {0} (معمولا [[otp_code]])" aria-label="محتوای متغیر 0 برای OTP">
                                <input type="text" name="mlr_sms_settings[otp_settings][cooperation_sign][var_1]" value="<?php echo esc_attr($otp_settings['var_1'] ?? ''); ?>" placeholder="محتوای پارامتر {1} (مثلا [[applicant_name]])" aria-label="محتوای متغیر 1 برای OTP">
                                <input type="text" name="mlr_sms_settings[otp_settings][cooperation_sign][var_2]" value="<?php echo esc_attr($otp_settings['var_2'] ?? ''); ?>" placeholder="محتوای پارامتر {2}" aria-label="محتوای متغیر 2 برای OTP">
                            </div>
                            <?php // *** END: Variable fields for OTP *** ?>
                        </div>
                    </div>

                     <?php
                     $loan_otp_settings = $options['otp_settings']['loan_contract_sign'] ?? [];
                     $loan_otp_enabled = isset($loan_otp_settings['enabled']) && (int) $loan_otp_settings['enabled'] === 1;
                     $loan_otp_body_id = (string) ($loan_otp_settings['bodyId'] ?? '');
                     $loan_otp_var_0 = (string) ($loan_otp_settings['var_0'] ?? '[[otp_code]]');
                     $loan_otp_var_1 = (string) ($loan_otp_settings['var_1'] ?? '[[contract_url]]');
                     $loan_otp_var_2 = (string) ($loan_otp_settings['var_2'] ?? '');
                     $shared_has_link_mapping = false;
                     foreach (['var_0','var_1','var_2'] as $shared_var_key) {
                         $shared_var_value = (string) ($otp_settings[$shared_var_key] ?? '');
                         if (strpos($shared_var_value, '[[contract_url]]') !== false || strpos($shared_var_value, '[[contract_link]]') !== false) {
                             $shared_has_link_mapping = true;
                             break;
                         }
                     }
                     ?>
                     <div class="mlr-status-item" style="margin-top:16px;border:1px solid #cbd5e1;">
                        <div class="mlr-status-item-header">
                            <h3>تنظیمات قدیمی قرارداد تسهیلات (Legacy)</h3>
                            <label class="mlr-switch" title="فعال/غیرفعال کردن الگوی اختصاصی قرارداد تسهیلات">
                                <input type="checkbox" name="mlr_sms_settings[otp_settings][loan_contract_sign][enabled]" value="1" <?php checked($loan_otp_enabled); ?>>
                                <span class="mlr-slider"></span>
                            </label>
                        </div>
                        <div class="mlr-status-item-body">
                            <p class="description" style="margin-top:0;">از نسخه 8.4.6.62 این بخش در ارسال قرارداد تسهیلات استفاده نمی‌شود. برای جلوگیری از اختلاف با قرارداد همکاری، لینک و OTP تسهیلات دقیقاً از پترن‌های فعال همکاری استفاده می‌کنند. مقادیر این بخش فقط برای حفظ تنظیمات قبلی نمایش داده می‌شوند.</p>
                            <div class="mlr-pattern-field">
                                <label for="loan_contract_otp_body_id">شناسه قالب (Body ID)</label>
                                <input type="text" id="loan_contract_otp_body_id" name="mlr_sms_settings[otp_settings][loan_contract_sign][bodyId]" value="<?php echo esc_attr($loan_otp_body_id); ?>" class="small-text ltr">
                            </div>
                            <div class="mlr-variable-fields">
                                <label>متغیرهای پترن قرارداد تسهیلات</label>
                                <p class="description">این متغیرها Legacy هستند و در مسیر جدید استفاده نمی‌شوند. OTP از `cooperation_sign` و لینک از پترن وضعیت «تایید مدارک» همکاری ارسال می‌شود.</p>
                                <input type="text" name="mlr_sms_settings[otp_settings][loan_contract_sign][var_0]" value="<?php echo esc_attr($loan_otp_var_0); ?>" placeholder="[[otp_code]]" aria-label="متغیر صفر OTP قرارداد تسهیلات">
                                <input type="text" name="mlr_sms_settings[otp_settings][loan_contract_sign][var_1]" value="<?php echo esc_attr($loan_otp_var_1); ?>" placeholder="[[contract_url]]" aria-label="متغیر یک لینک قرارداد تسهیلات">
                                <input type="text" name="mlr_sms_settings[otp_settings][loan_contract_sign][var_2]" value="<?php echo esc_attr($loan_otp_var_2); ?>" placeholder="اختیاری" aria-label="متغیر دو قرارداد تسهیلات">
                            </div>
                            <?php if (!$loan_otp_enabled || $loan_otp_body_id === ''): ?>
                                <div style="margin-top:12px;padding:10px 12px;border-right:4px solid #dba617;background:#fff8e5;line-height:1.9;">
                                    <strong>مسیر فعال:</strong> قرارداد تسهیلات دقیقاً از پترن‌های قرارداد همکاری استفاده می‌کند.
                                    <?php if (!$shared_has_link_mapping): ?>
                                        لینک با پترن «تایید مدارک» همکاری و OTP با پترن `cooperation_sign` ارسال می‌شود؛ تعداد متغیرهای هر Body ID دست‌نخورده باقی می‌ماند.
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <div style="margin-top:12px;padding:10px 12px;border-right:4px solid #00a32a;background:#edfaef;line-height:1.9;">
                                    <strong>مسیر فعال:</strong> حتی در صورت وجود تنظیمات Legacy بالا، ارسال جدید تسهیلات از پترن‌های همکاری استفاده می‌کند.
                                </div>
                            <?php endif; ?>
                        </div>
                     </div>
                </div>
            </div>
            <?php // --- END: OTP Settings Card --- ?>


            <?php // --- Cooperation Status SMS Card --- ?>
            <div class="mlr-settings-card">
                <h2>الگوهای پیامک برای درخواست‌های همکاری</h2>
                <div class="mlr-variable-guide">
                    <strong>متغیرهای در دسترس:</strong>
                    <div class="mlr-placeholders-list">
                        <?php foreach($cooperation_placeholders as $ph => $desc): ?><span class="mlr-placeholder-tag" title="<?php echo esc_attr($desc); ?>"><?php echo esc_html($ph); ?></span><?php endforeach; ?>
                    </div>
                </div>
                <div class="mlr-status-settings-grid">
                    <?php foreach ($cooperation_statuses as $status_key):
                        // Get settings for this status safely
                        $settings = $options['cooperation_statuses'][$status_key] ?? [];
                        $status_enabled = isset($settings['enabled']) && $settings['enabled'] == 1;
                        $body_id = $settings['bodyId'] ?? '';
                        $var_0 = $settings['var_0'] ?? '';
                        $var_1 = $settings['var_1'] ?? '';
                        $var_2 = $settings['var_2'] ?? '';
                        ?>
                        <div class="mlr-status-item">
                            <div class="mlr-status-item-header">
                                <h3><?php echo esc_html($status_key); ?></h3>
                                <label class="mlr-switch" title="فعال/غیرفعال کردن پیامک برای این وضعیت">
                                    <input type="checkbox" name="mlr_sms_settings[cooperation_statuses][<?php echo esc_attr($status_key); ?>][enabled]" value="1" <?php checked($status_enabled); ?>>
                                    <span class="mlr-slider"></span>
                                </label>
                            </div>
                            <div class="mlr-status-item-body">
                                <div class="mlr-pattern-field">
                                    <label for="coop_body_id_<?php echo esc_attr(sanitize_key($status_key)); ?>">شناسه قالب (Body ID)</label>
                                    <input type="text" id="coop_body_id_<?php echo esc_attr(sanitize_key($status_key)); ?>" name="mlr_sms_settings[cooperation_statuses][<?php echo esc_attr($status_key); ?>][bodyId]" value="<?php echo esc_attr($body_id); ?>" class="small-text ltr">
                                </div>
                                <div class="mlr-variable-fields">
                                    <label>محتوای متغیرها (اختیاری، طبق الگوی ملی‌پیامک)</label>
                                    <input type="text" name="mlr_sms_settings[cooperation_statuses][<?php echo esc_attr($status_key); ?>][var_0]" value="<?php echo esc_attr($var_0); ?>" placeholder="محتوای پارامتر {0}" aria-label="محتوای متغیر 0 برای وضعیت <?php echo esc_attr($status_key); ?>">
                                    <input type="text" name="mlr_sms_settings[cooperation_statuses][<?php echo esc_attr($status_key); ?>][var_1]" value="<?php echo esc_attr($var_1); ?>" placeholder="محتوای پارامتر {1}" aria-label="محتوای متغیر 1 برای وضعیت <?php echo esc_attr($status_key); ?>">
                                    <input type="text" name="mlr_sms_settings[cooperation_statuses][<?php echo esc_attr($status_key); ?>][var_2]" value="<?php echo esc_attr($var_2); ?>" placeholder="محتوای پارامتر {2}" aria-label="محتوای متغیر 2 برای وضعیت <?php echo esc_attr($status_key); ?>">
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php // --- Loan Status SMS Card --- ?>
            <div class="mlr-settings-card">
                <h2>الگوهای پیامک برای درخواست‌های تسهیلات</h2>
                <div class="mlr-variable-guide">
                    <strong>متغیرهای در دسترس:</strong>
                    <div class="mlr-placeholders-list">
                        <?php foreach($loan_placeholders as $ph => $desc): ?><span class="mlr-placeholder-tag" title="<?php echo esc_attr($desc); ?>"><?php echo esc_html($ph); ?></span><?php endforeach; ?>
                         <?php // Ensure acceptor name placeholder is listed ?>
                         <span class="mlr-placeholder-tag" title="نام پذیرنده (ثبت کننده درخواست)">[[acceptor_name]]</span>
                    </div>
                </div>
                <div class="mlr-status-settings-grid">
                    <?php foreach ($loan_statuses as $status_key):
                        // Get settings for this status safely
                        $settings = $options['loan_statuses'][$status_key] ?? [];
                        $status_enabled = isset($settings['enabled']) && $settings['enabled'] == 1;
                        // Get recipient settings, default to applicant ON, acceptor OFF if not previously set
                        $send_to_applicant = isset($settings['send_to_applicant']) ? (bool)$settings['send_to_applicant'] : true; // Default ON
                        $send_to_acceptor = isset($settings['send_to_acceptor']) ? (bool)$settings['send_to_acceptor'] : false; // Default OFF
                        $body_id = $settings['bodyId'] ?? '';
                        $var_0 = $settings['var_0'] ?? '';
                        $var_1 = $settings['var_1'] ?? '';
                        $var_2 = $settings['var_2'] ?? '';
                        ?>
                        <div class="mlr-status-item">
                            <div class="mlr-status-item-header">
                                <h3><?php echo esc_html($status_key); ?></h3>
                                <label class="mlr-switch" title="فعال/غیرفعال کردن کلی این پیامک">
                                    <input type="checkbox" name="mlr_sms_settings[loan_statuses][<?php echo esc_attr($status_key); ?>][enabled]" value="1" <?php checked($status_enabled); ?>>
                                    <span class="mlr-slider"></span>
                                </label>
                            </div>
                            <div class="mlr-status-item-body">
                                <?php // *** ADDED: Recipient Toggles *** ?>
                                <div class="mlr-recipient-toggles">
                                     <label>ارسال به:</label>
                                     <label class="mlr-checkbox-label">
                                         <input type="checkbox" name="mlr_sms_settings[loan_statuses][<?php echo esc_attr($status_key); ?>][send_to_applicant]" value="1" <?php checked($send_to_applicant); ?>> متقاضی
                                     </label>
                                     <label class="mlr-checkbox-label">
                                         <input type="checkbox" name="mlr_sms_settings[loan_statuses][<?php echo esc_attr($status_key); ?>][send_to_acceptor]" value="1" <?php checked($send_to_acceptor); ?>> پذیرنده
                                     </label>
                                </div>
                                <?php // *** END: Recipient Toggles *** ?>

                                <div class="mlr-pattern-field">
                                    <label for="loan_body_id_<?php echo esc_attr(sanitize_key($status_key)); ?>">شناسه قالب (Body ID)</label>
                                    <input type="text" id="loan_body_id_<?php echo esc_attr(sanitize_key($status_key)); ?>" name="mlr_sms_settings[loan_statuses][<?php echo esc_attr($status_key); ?>][bodyId]" value="<?php echo esc_attr($body_id); ?>" class="small-text ltr">
                                </div>
                                <div class="mlr-variable-fields">
                                    <label>محتوای متغیرها (اختیاری، طبق الگوی ملی‌پیامک)</label>
                                    <input type="text" name="mlr_sms_settings[loan_statuses][<?php echo esc_attr($status_key); ?>][var_0]" value="<?php echo esc_attr($var_0); ?>" placeholder="محتوای پارامتر {0}" aria-label="محتوای متغیر 0 برای وضعیت <?php echo esc_attr($status_key); ?>">
                                    <input type="text" name="mlr_sms_settings[loan_statuses][<?php echo esc_attr($status_key); ?>][var_1]" value="<?php echo esc_attr($var_1); ?>" placeholder="محتوای پارامتر {1}" aria-label="محتوای متغیر 1 برای وضعیت <?php echo esc_attr($status_key); ?>">
                                    <input type="text" name="mlr_sms_settings[loan_statuses][<?php echo esc_attr($status_key); ?>][var_2]" value="<?php echo esc_attr($var_2); ?>" placeholder="محتوای پارامتر {2}" aria-label="محتوای متغیر 2 برای وضعیت <?php echo esc_attr($status_key); ?>">
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php submit_button('ذخیره تنظیمات'); // Output the Save Changes button ?>
        </form>
    </div> <?php // End wrap ?>

    <?php // Embedded CSS styles ?>
    <style>
        /* General Styles */
        .mlr-sms-settings-wrap { max-width: 1200px; }
        .mlr-sms-settings-wrap h1 { font-weight: 600; color: #2a4365; display: flex; align-items: center; }
        .mlr-sms-settings-wrap h1::before { content: ''; display: inline-block; width: 4px; height: 24px; background-color: #2563eb; margin-left: 12px; border-radius: 2px; }
        .mlr-sms-settings-wrap > p { color: #4a5568; margin-bottom: 25px; }
        .mlr-page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px; flex-wrap: wrap; gap: 15px; }

        /* Credit Checker */
        .mlr-credit-checker-wrapper { display: flex; align-items: center; gap: 12px; }
        #mlr_credit_display { font-size: 14px; color: #4a5568; }
        #mlr_credit_display strong { font-weight: 600; }
        #mlr_credit_value { font-weight: 700; font-size: 16px; color: #2a4365; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; }
        #mlr_check_credit_btn { padding: 6px 14px; background: #2563eb; color: #fff; border: none; border-radius: 6px; cursor: pointer; transition: all 0.2s; display: flex; align-items: center; gap: 6px; font-size: 13px; }
        #mlr_check_credit_btn:hover { background: #1d4ed8; }
        #mlr_check_credit_btn .dashicons { font-size: 16px; line-height: 1; margin-top: 1px; transition: transform .5s ease-in-out; }
        #mlr_check_credit_btn:hover .dashicons { transform: rotate(180deg); }
        .mlr-credit-checker-wrapper .spinner { float: none; margin: 0; }

        /* Settings Card */
        .mlr-settings-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; margin-bottom: 30px; box-shadow: 0 1px 3px rgba(0,0,0,0.03), 0 1px 2px rgba(0,0,0,0.03); }
        .mlr-settings-card h2 { font-size: 16px; font-weight: 600; padding: 16px 22px; margin: 0; border-bottom: 1px solid #e2e8f0; color: #2d3748; }
        .mlr-settings-card .form-table { margin-top: 0; }
        .mlr-settings-card .form-table th { padding: 20px 22px; font-weight: 600; width: 150px; text-align: right; }
        .mlr-settings-card .form-table td { padding: 15px 22px; }
        .mlr-settings-card .form-table input[type="text"],
        .mlr-settings-card .form-table input[type="password"] { border-radius: 6px; padding: 8px 12px; max-width: 350px; width: 100%; border-color: #cbd5e0; }
        .mlr-settings-card .form-table input:focus { border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.2); }
        .mlr-settings-card p.description { padding: 0 22px 20px; margin-top: -10px; color: #718096; font-size: 12px; }

        /* Variable Guide */
        .mlr-variable-guide { padding: 15px 22px; margin: 0; border-top: 1px solid #e2e8f0; border-bottom: 1px solid #e2e8f0; background: #f7fafc; color: #4a5568; }
        .mlr-variable-guide strong { font-weight: 600; color: #2d3748; }
        .mlr-variable-guide .mlr-placeholders-list { margin-top: 10px; display: flex; flex-wrap: wrap; gap: 8px; }
        .mlr-placeholder-tag { background-color: #e2e8f0; color: #4a5568; padding: 3px 8px; border-radius: 4px; font-size: 12px; font-family: monospace; cursor: help; }

        /* Status Settings Grid */
        .mlr-status-settings-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 20px; padding: 22px; }
        .mlr-status-item { background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.03), 0 1px 2px rgba(0,0,0,0.03); transition: all 0.2s ease; }
        .mlr-status-item:hover { transform: translateY(-3px); box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .mlr-status-item-header { display: flex; justify-content: space-between; align-items: center; padding: 12px 15px; border-bottom: 1px solid #f1f5f9; }
        .mlr-status-item-header h3 { margin: 0; font-size: 14px; font-weight: 600; color: #2d3748; }
        .mlr-status-item-body { display: flex; flex-direction: column; gap: 10px; padding: 15px; }
        .mlr-pattern-field label, .mlr-variable-fields label, .mlr-recipient-toggles label:first-child { display: block; font-size: 13px; margin-bottom: 8px; color: #4a5568; font-weight: 500; }
        .mlr-variable-fields input, .mlr-pattern-field input { width: 100%; margin-bottom: 8px; font-family: tahoma; border-radius: 6px; padding: 8px 12px; border: 1px solid #cbd5e0; box-sizing: border-box; }
        .mlr-variable-fields input:focus, .mlr-pattern-field input:focus { border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.2); }
        .mlr-pattern-field .description { margin-top: 5px; margin-bottom: 0; color: #718096; font-size: 12px; }
        .mlr-variable-fields .description { margin-bottom: 10px; color: #718096; font-size: 12px; }


        /* Switch Toggle */
        .mlr-switch { position: relative; display: inline-block; width: 44px; height: 24px; vertical-align: middle; }
        .mlr-switch input { opacity: 0; width: 0; height: 0; }
        .mlr-slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #cbd5e0; transition: .4s; border-radius: 24px; }
        .mlr-slider:before { position: absolute; content: ""; height: 18px; width: 18px; left: 3px; bottom: 3px; background-color: white; box-shadow: 0 1px 3px rgba(0,0,0,0.2); transition: .4s; border-radius: 50%; }
        input:checked + .mlr-slider { background-color: #48bb78; /* Green */ }
        input:checked + .mlr-slider:before { transform: translateX(20px); }

        /* Recipient Toggles */
        .mlr-recipient-toggles { display: flex; align-items: center; gap: 15px; margin-bottom: 10px; padding-bottom: 10px; border-bottom: 1px dashed #e2e8f0; }
        .mlr-recipient-toggles label:first-child { font-weight: 500; color: #4a5568; font-size: 13px; margin-left: 5px;}
        .mlr-checkbox-label { display: inline-flex; align-items: center; gap: 5px; font-size: 13px; cursor: pointer; color: #333; margin-bottom: 0 !important; /* Override default label margin */ }
        .mlr-checkbox-label input[type="checkbox"] { margin: 0; cursor: pointer; width: auto; /* Override potential width:100% */}

    </style>

    <?php // JavaScript for Credit Check and Form Validation ?>
    <script>
    jQuery(document).ready(function($) {
        // Function to check SMS credit
        function checkCredit() {
            var btn = $('#mlr_check_credit_btn'), spinner = btn.next('.spinner'), creditValue = $('#mlr_credit_value');
            var username = $('#mlr_sms_username').val();
            // Check only username as password field might be empty but saved
            if (!username) {
                creditValue.html('<span style="color: #d9534f;">نام کاربری پنل پیامک وارد نشده است.</span>');
                return;
            }
            btn.prop('disabled', true); spinner.addClass('is-active');
            creditValue.html('<i>در حال بررسی...</i>'); // Indicate loading state
            $.ajax({
                url: '<?php echo esc_url(admin_url('admin-ajax.php')); ?>', // Use esc_url for safety
                type: 'POST',
                data: {
                    action: 'mlr_get_sms_credit',
                    nonce: '<?php echo wp_create_nonce('mlr_ajax_nonce_action'); ?>'
                    // Backend uses saved credentials
                },
                dataType: 'json', // Expect JSON response
                success: function(res) {
                    if (res.success && res.data && res.data.credit !== undefined) {
                        creditValue.html(res.data.credit + ' <span style="font-size: 11px; color:#64748b;">عدد</span>'); // Smaller unit text
                    } else {
                        const errorMessage = (res.data && res.data.message) ? res.data.message : 'پاسخ نامعتبر از سرور.';
                        creditValue.html('<span style="color: #dc3545; font-size: 12px;">خطا: ' + errorMessage + '</span>'); // Use danger color
                    }
                },
                error: function(jqXHR) {
                    // Log detailed error for debugging
                    console.error('AJAX Error Checking Credit:', jqXHR.status, jqXHR.statusText, jqXHR.responseText);
                    creditValue.html('<span style="color: #dc3545; font-size: 12px;">خطای ارتباط. از صحت اطلاعات پنل اطمینان حاصل کنید.</span>');
                },
                complete: function() {
                    btn.prop('disabled', false);
                    spinner.removeClass('is-active');
                }
            });
        }
        // Attach click handler to the button
        $('#mlr_check_credit_btn').on('click', checkCredit);
        // Run check on page load if username seems to be filled and password might be saved
        if ($('#mlr_sms_username').val() !== '' && ($('#mlr_sms_password').attr('placeholder') && $('#mlr_sms_password').attr('placeholder').includes('••••••••'))) {
            checkCredit();
        }

        // Form submission validation for required Body ID when SMS is enabled
        $('form[action="options.php"]').on('submit', function(e){
            var validationPassed = true; // Flag for validation

            // Check OTP settings
            var otpEnabled = $('input[name="mlr_sms_settings[otp_settings][cooperation_sign][enabled]"]').is(':checked');
            var otpBodyIdInput = $('input[name="mlr_sms_settings[otp_settings][cooperation_sign][bodyId]"]');
            var otpBodyId = otpBodyIdInput.val().trim();
            if(otpEnabled && !otpBodyId){
                alert('لطفا شناسه قالب (Body ID) را برای پیامک کد تایید امضای قرارداد وارد کنید.');
                otpBodyIdInput.focus();
                validationPassed = false;
                e.preventDefault(); // Prevent form submission
                return false; // Stop further checks
            }

            // Check Loan Status settings
             $('input[name^="mlr_sms_settings[loan_statuses]"][name$="[enabled]"]').each(function(){
                 if(!validationPassed) return false; // Stop if previous validation failed

                if ($(this).is(':checked')) {
                    // Find the corresponding status key (e.g., 'در حال بررسی')
                    var nameAttr = $(this).attr('name');
                    var match = nameAttr.match(/\[loan_statuses\]\[(.*?)\]/);
                    if (match && match[1]){
                        var statusKey = match[1];
                        var bodyIdInput = $('input[name="mlr_sms_settings[loan_statuses][' + statusKey + '][bodyId]"]');
                        var sendToApplicant = $('input[name="mlr_sms_settings[loan_statuses][' + statusKey + '][send_to_applicant]"]').is(':checked');
                        var sendToAcceptor = $('input[name="mlr_sms_settings[loan_statuses][' + statusKey + '][send_to_acceptor]"]').is(':checked');

                        // Require Body ID only if enabled AND at least one recipient is selected
                        if ((sendToApplicant || sendToAcceptor) && bodyIdInput.val().trim() === '') {
                             alert('لطفا شناسه قالب (Body ID) را برای وضعیت تسهیلات "' + statusKey + '" وارد کنید، زیرا حداقل یک گیرنده برای آن فعال است.');
                             bodyIdInput.focus();
                             validationPassed = false;
                             e.preventDefault(); // Prevent form submission
                             return false; // Stop the .each loop
                        }
                    }
                }
             });
             
             if(!validationPassed) return false; // Stop if previous validation failed

             // Check Cooperation Status settings (similar logic)
             $('input[name^="mlr_sms_settings[cooperation_statuses]"][name$="[enabled]"]').each(function(){
                 if(!validationPassed) return false; // Stop if previous validation failed

                 if ($(this).is(':checked')) {
                     var nameAttr = $(this).attr('name');
                     var match = nameAttr.match(/\[cooperation_statuses\]\[(.*?)\]/);
                     if(match && match[1]){
                        var statusKey = match[1];
                        var bodyIdInput = $('input[name="mlr_sms_settings[cooperation_statuses][' + statusKey + '][bodyId]"]');

                        if (bodyIdInput.val().trim() === '') {
                             alert('لطفا شناسه قالب (Body ID) را برای وضعیت همکاری "' + statusKey + '" وارد کنید، زیرا پیامک برای این وضعیت فعال است.');
                             bodyIdInput.focus();
                             validationPassed = false;
                             e.preventDefault(); // Prevent form submission
                             return false; // Stop the .each loop
                        }
                     }
                 }
            });

            return validationPassed; // Allow submission only if all checks pass
        });
    });
    </script>
    <?php
}
?>