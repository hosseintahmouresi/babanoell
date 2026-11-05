<?php
if (!defined('ABSPATH')) {
    exit;
}

// The form processing logic has been moved to includes/ajax/submit-loan-request.php

add_shortcode('mlr_applicant_loan_request_form', 'mlr_applicant_loan_request_form_shortcode');

function mlr_applicant_loan_request_form_shortcode() {
    wp_enqueue_style('mlr-loan-form-style', MLR_PLUGIN_URL . 'assets/css/loan-request-form.css', [], MLR_VERSION);

    if (!is_user_logged_in()) {
        return '<div class="mlr-form-container"><p style="padding: 30px; text-align:center;">برای ثبت درخواست، لطفاً ابتدا <a href="' . wp_login_url(get_permalink()) . '">وارد شوید</a>.</p></div>';
    }

    $user_id = get_current_user_id();
    if (!current_user_can('manage_options') && function_exists('mlr_is_applicant_user') && !mlr_is_applicant_user($user_id)) {
        return '<div class="mlr-form-container"><p style="padding: 30px; text-align:center;">دسترسی به پنل متقاضی برای حساب شما فعال نیست.</p></div>';
    }

    if (function_exists('mlr_get_applicant_visible_installment_plans')) {
        $plans = mlr_get_applicant_visible_installment_plans([
            'active_only' => true,
            'fields' => ['id', 'name', 'type', 'settings'],
            'order_by' => 'display_order',
        ]);
    } else {
        global $wpdb;
        $table_installment_plans = $wpdb->prefix . 'installment_plans';
        $plan_order_sql = function_exists('mlr_get_installment_plan_order_by_sql') ? mlr_get_installment_plan_order_by_sql() : 'ORDER BY id DESC';
        $plans = $wpdb->get_results("SELECT id, name, type, settings FROM $table_installment_plans WHERE is_active = 1 {$plan_order_sql}");
    }

    if (empty($plans)) {
        return '<div class="mlr-form-container"><p style="padding: 30px; text-align:center;">در حال حاضر هیچ طرح فعالی برای حساب شما فعال نشده است. لطفاً با مدیر سایت تماس بگیرید.</p></div>';
    }

    $js_plans = function_exists('mlr_prepare_frontend_js_plans')
        ? mlr_prepare_frontend_js_plans($plans)
        : [];
    $marketplace_settings = function_exists('mlr_get_acceptor_marketplace_settings') ? mlr_get_acceptor_marketplace_settings() : [];
    $marketplace_enabled = !empty($marketplace_settings['enabled'])
        && (!empty($marketplace_settings['allow_applicant_selected_acceptor']) || !empty($marketplace_settings['allow_open_marketplace']));
    $locked_acceptor_id = function_exists('mlr_get_locked_acceptor_for_current_applicant') ? mlr_get_locked_acceptor_for_current_applicant() : 0;
    $locked_acceptor_name = $locked_acceptor_id && function_exists('mlr_get_acceptor_display_name') ? mlr_get_acceptor_display_name($locked_acceptor_id) : '';
    if ($locked_acceptor_id > 0 && !empty($marketplace_settings['enable_acceptor_invitation_links'])) {
        $marketplace_enabled = true;
        $marketplace_settings['allow_applicant_selected_acceptor'] = 1;
        $marketplace_settings['allow_open_marketplace'] = 0;
    }
    $marketplace_category_terms = function_exists('mlr_get_acceptor_category_terms') ? mlr_get_acceptor_category_terms() : [];
    $marketplace_location_tree = function_exists('mlr_get_acceptor_location_terms_tree') ? mlr_get_acceptor_location_terms_tree() : [];
    $marketplace_location_terms = function_exists('mlr_flatten_acceptor_location_terms_tree')
        ? mlr_flatten_acceptor_location_terms_tree($marketplace_location_tree)
        : [];

    wp_enqueue_script('mlr-file-upload-helper', MLR_PLUGIN_URL . 'assets/js/mlr-file-upload-helper.js', [], '1.0.0', true);
    wp_enqueue_script('mlr-applicant-form-js', MLR_PLUGIN_URL . 'assets/js/applicant-loan-request-form.js', ['jquery', 'mlr-file-upload-helper'], MLR_VERSION, true);
    wp_localize_script('mlr-applicant-form-js', 'mlr_applicant_form_ajax', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('mlr_frontend_nonce'),
        'calc_nonce' => wp_create_nonce('mlr_applicant_frontend_calc_nonce'),
        'submit_nonce' => wp_create_nonce('mlr_applicant_loan_request_action'),
        'acceptor_search_nonce' => wp_create_nonce('mlr_applicant_acceptor_search_nonce'),
        'plans' => $js_plans, 
        'user_commission' => null,
        'context' => 'mlr_applicant_loan_request_form'
    ]);
    
    $occupation_options = function_exists('mlr_get_occupation_options') ? mlr_get_occupation_options() : [
        'official_employee' => 'کارمند رسمی', 'contract_employee' => 'کارمند قراردادی', 'worker' => 'کارگر',
        'freelancer' => 'آزاد', 'retired' => 'بازنشسته', 'pensioner' => 'مستمری بگیر', 'employer' => 'کارفرما', 'other' => 'سایر'
    ];

    ob_start();

    try {
        $jalali_year = (int)date_i18n('Y', time(), new DateTimeZone('Asia/Tehran')) - 621;
    } catch (Exception $e) {
        $jalali_year = 1403;
    }

    $end_year = $jalali_year - 18;
    $start_year = $jalali_year - 75;
    ?>
    <div class="mlr-form-container">
        <div class="mlr-glass-blob blob-1"></div>
        <div class="mlr-glass-blob blob-2"></div>
        
        <div class="mlr-form-wrapper">
            <div class="mlr-stepper-sidebar">
                <div class="mlr-steps-container">
                    <div class="mlr-step-item" data-step-id="1">
                        <div class="mlr-step-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                        </div>
                        <div class="mlr-step-text">
                            <span class="mlr-step-label">احراز هویت</span>
                            <span class="mlr-step-sublabel">تایید شماره موبایل</span>
                        </div>
                    </div>
                    <div class="mlr-step-item" data-step-id="2">
                        <div class="mlr-step-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line></svg>
                        </div>
                        <div class="mlr-step-text">
                            <span class="mlr-step-label">انتخاب طرح</span>
                            <span class="mlr-step-sublabel">محاسبه اقساط</span>
                        </div>
                    </div>
                    <div class="mlr-step-item" data-step-id="3" id="step-indicator-3" style="display:none;">
                        <div class="mlr-step-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 17l4 4L14 7l4 4L22 3"></path><path d="M12 7h4a2 2 0 0 1 2 2v4"></path></svg>
                        </div>
                        <div class="mlr-step-text">
                            <span class="mlr-step-label">اعتبارسنجی</span>
                            <span class="mlr-step-sublabel">بررسی بانکی</span>
                        </div>
                    </div>
                    <div class="mlr-step-item" data-step-id="4">
                        <div class="mlr-step-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                        </div>
                        <div class="mlr-step-text">
                            <span class="mlr-step-label">اطلاعات فردی</span>
                            <span class="mlr-step-sublabel">مشخصات و آدرس</span>
                        </div>
                    </div>
                    <div class="mlr-step-item" data-step-id="5">
                        <div class="mlr-step-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="12" y1="18" x2="12" y2="12"></line><line x1="9" y1="15" x2="15" y2="15"></line></svg>
                        </div>
                        <div class="mlr-step-text">
                            <span class="mlr-step-label">مدارک</span>
                            <span class="mlr-step-sublabel">بارگذاری فایل‌ها</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mlr-form-content">
                <form id="mlr-loan-request-form-main" method="post" enctype="multipart/form-data">
                    <input type="hidden" name="national_code" id="national_code">
                    <input type="hidden" name="mobile_number" id="mobile_number">
                    <input type="hidden" name="birth_date" id="birth_date">
                    <input type="hidden" name="order_items" id="order_items_json">
                    <input type="hidden" name="credit_report_json" id="credit_report_json">
                    <input type="hidden" name="credit_proof_token" id="credit_proof_token">
                    <input type="hidden" name="guarantee_check_principal" id="guarantee_check_principal">
                    <input type="hidden" name="guarantee_check_total" id="guarantee_check_total">
                    <input type="hidden" name="prepayment" id="prepayment">
                    <input type="hidden" name="order_total" id="order_total">

                    <div class="mlr-step-content" id="step1">
                        <p class="mlr-step-description">لطفا کد ملی و شماره موبایل خود را جهت استعلام تطابق وارد نمایید.</p>
                        <div id="mlr-message"></div>
                        <div class="mlr-form-group">
                            <label for="national_code_verify">کد ملی</label>
                            <input type="text" id="national_code_verify" class="mlr-input" placeholder="کد ملی ۱۰ رقمی" style="text-align:center; direction: ltr;">
                            <span class="mlr-error-message">کد ملی باید ۱۰ رقم باشد.</span>
                        </div>
                        <div class="mlr-form-group">
                            <label for="mobile_number_verify">شماره موبایل</label>
                            <input type="text" id="mobile_number_verify" class="mlr-input" placeholder="مثال: 09123456789" style="text-align:center; direction: ltr;">
                            <span class="mlr-error-message">شماره موبایل باید ۱۱ رقم و با ۰۹ شروع شود.</span>
                        </div>
                        <button type="button" id="verify_shahkar_btn" class="mlr-btn mlr-btn-primary" style="width: 100%;" disabled>بررسی تطابق و ادامه</button>
                    </div>

                    <div class="mlr-step-content" id="step2">
                        <div class="mlr-form-group">
                            <label for="calc_plan_select">طرح اقساطی:</label>
                            <select id="calc_plan_select" name="installment_plan_id" class="mlr-select" required>
                                <option value="">-- یک طرح را انتخاب کنید --</option>
                                <?php foreach($plans as $plan): ?>
                                    <option value="<?php echo esc_attr($plan->id); ?>" data-type="<?php echo esc_attr($plan->type); ?>"><?php echo esc_html($plan->name); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <span class="mlr-error-message">انتخاب طرح الزامی است.</span>
                        </div>
                        <div class="mlr-plan-info-accordions" style="display:none;">
                            <div class="mlr-plan-docs-accordion" data-state="collapsed">
                                <button type="button" class="mlr-plan-docs-toggle" aria-expanded="false">
                                    <span class="mlr-plan-docs-toggle-title">مدارک مورد نیاز طرح</span>
                                    <span class="mlr-plan-docs-toggle-icon" aria-hidden="true"></span>
                                </button>
                                <div class="mlr-plan-docs-panel" hidden>
                                    <div class="mlr-plan-docs-status" style="display:none;"></div>
                                    <div class="mlr-plan-docs-list"></div>
                                </div>
                            </div>
                            <div class="mlr-plan-docs-accordion mlr-plan-conditions-accordion" data-state="collapsed">
                                <button type="button" class="mlr-plan-docs-toggle" aria-expanded="false">
                                    <span class="mlr-plan-docs-toggle-title">شرایط طرح</span>
                                    <span class="mlr-plan-docs-toggle-icon" aria-hidden="true"></span>
                                </button>
                                <div class="mlr-plan-docs-panel" hidden>
                                    <div class="mlr-plan-conditions-status" style="display:none;"></div>
                                    <div class="mlr-plan-conditions-content"></div>
                                </div>
                            </div>
                        </div>
                        <div class="mlr-grid-2-col">
                            <div class="mlr-form-group"><label id="calc_amount_label" for="calc_amount">مبلغ (ریال):</label><input type="text" id="calc_amount" name="loan_amount" class="mlr-input" required><span class="mlr-error-message">مبلغ وارد شده خارج از محدوده مجاز طرح است.</span></div>
                            <div class="mlr-form-group"><label for="calc_installments">تعداد اقساط:</label><select id="calc_installments" name="installment_count" class="mlr-select" required></select></div>
                        </div>
                        <div class="mlr-form-group" id="calc_interval_wrapper" style="display:none;">
                            <label for="calc_interval">فاصله اقساط (ماه):</label>
                            <input type="number" id="calc_interval" name="installment_interval" value="1" class="mlr-input">
                            <span class="mlr-error-message">فاصله اقساط خارج از محدوده مجاز طرح است.</span>
                        </div>
                        <button type="button" id="run_calculator" class="mlr-btn mlr-btn-primary" style="width:100%; margin-bottom: 20px;" disabled>محاسبه</button>
                        <div id="calculation-result"></div>
                        <div class="mlr-step-buttons">
                            <button type="button" class="mlr-btn mlr-btn-secondary mlr-prev-btn" data-target-step="1">مرحله قبل</button>
                            <button type="button" class="mlr-btn mlr-btn-primary mlr-next-btn" id="next_step_2" disabled>مرحله بعد</button>
                        </div>
                    </div>
                    
                    <div class="mlr-step-content" id="step3">
                        <div id="mlr-credit-check-start">
                            <p class="mlr-step-description">برای ادامه فرآیند، نیاز به اعتبارسنجی بانکی است. لطفاً روی دکمه زیر کلیک کنید تا کد تایید برای شما ارسال شود.</p>
                            <button type="button" id="mlr-start-credit-check-btn" class="mlr-btn mlr-btn-primary" style="width:100%;">ارسال کد اعتبارسنجی</button>
                            <div class="mlr-step-buttons"><button type="button" class="mlr-btn mlr-btn-secondary mlr-prev-btn" data-target-step="2">مرحله قبل</button></div>
                        </div>
                        
                        <div id="mlr-credit-check-otp" style="display:none;">
                            <p class="mlr-step-description">کد تایید پیامک شده به شماره موبایل خود را وارد کنید.</p>
                            <div class="mlr-form-group"><label>کد تایید (OTP)</label><input type="text" id="mlr_credit_otp_input" class="mlr-input" style="text-align:center; letter-spacing: 5px;" placeholder="_ _ _ _ _"></div>
                            <button type="button" id="mlr-verify-credit-otp-btn" class="mlr-btn mlr-btn-primary" style="width:100%;">تایید و دریافت گزارش</button>
                        </div>

                        <div id="mlr-credit-check-loading" style="display:none; text-align:center; padding: 30px;">
                            <div class="mlr-loading-spinner" style="border: 4px solid #f3f3f3; border-top: 4px solid #3498db; border-radius: 50%; width: 40px; height: 40px; animation: spin 1s linear infinite; margin: 0 auto 15px;"></div>
                            <p id="mlr-credit-loading-text" style="font-weight:bold;">در حال بررسی وضعیت...</p>
                            <style>@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }</style>
                        </div>

                        <div id="mlr-credit-check-result" style="display:none;">
                            <div id="mlr-credit-score-display" style="margin-bottom: 20px;"></div>
                            <div class="mlr-step-buttons"><button type="button" class="mlr-btn mlr-btn-primary mlr-next-btn" id="mlr-credit-next-btn" data-target-step="4">ادامه و تکمیل اطلاعات</button></div>
                        </div>
                    </div>

                    <div class="mlr-step-content" id="step4">
                        <div class="mlr-grid-2-col">
                            <div class="mlr-form-group"><label for="full_name">نام و نام خانوادگی:</label><input type="text" name="full_name" id="full_name" class="mlr-input" required><span class="mlr-error-message">این فیلد الزامی است.</span></div>
                            <div class="mlr-form-group"><label for="phone_number">شماره تلفن ثابت (اختیاری):</label><input type="text" name="phone_number" id="phone_number" class="mlr-input"></div>
                        </div>
                        <div class="mlr-grid-2-col">
                            <div class="mlr-form-group"><label for="father_name">نام پدر:</label><input type="text" name="father_name" id="father_name" class="mlr-input" required><span class="mlr-error-message">این فیلد الزامی است.</span></div>
                            <div class="mlr-form-group"><label for="certificate_number">شماره شناسنامه:</label><input type="text" name="certificate_number" id="certificate_number" class="mlr-input" required><span class="mlr-error-message">این فیلد الزامی است.</span></div>
                        </div>
                        <div class="mlr-form-group"><label for="certificate_city">شهر صدور شناسنامه:</label><input type="text" name="certificate_city" id="certificate_city" class="mlr-input" required><span class="mlr-error-message">این فیلد الزامی است.</span></div>
                        <div class="mlr-form-group">
                            <label>تاریخ تولد:</label>
                            <div class="mlr-birthdate-group">
                                <select name="birth_day" id="birth_day" class="mlr-select" required><option value="">روز</option><?php for ($i = 1; $i <= 31; $i++): ?><option value="<?php echo $i; ?>"><?php echo $i; ?></option><?php endfor; ?></select>
                                <select name="birth_month" id="birth_month" class="mlr-select" required><option value="">ماه</option><option value="1">فروردین</option><option value="2">اردیبهشت</option><option value="3">خرداد</option><option value="4">تیر</option><option value="5">مرداد</option><option value="6">شهریور</option><option value="7">مهر</option><option value="8">آبان</option><option value="9">آذر</option><option value="10">دی</option><option value="11">بهمن</option><option value="12">اسفند</option></select>
                                <select name="birth_year" id="birth_year" class="mlr-select" required><option value="">سال</option><?php for ($year = $end_year; $year >= $start_year; $year--): ?><option value="<?php echo $year; ?>"><?php echo $year; ?></option><?php endfor; ?></select>
                            </div>
                             <span class="mlr-error-message" id="birthdate-error">تاریخ تولد کامل الزامی است.</span>
                        </div>
                        <div class="mlr-grid-2-col">
                            <div class="mlr-form-group"><label for="occupation">شغل:</label><select name="occupation" id="occupation" class="mlr-select" required><option value="">انتخاب کنید...</option><?php foreach ($occupation_options as $occupation_key => $occupation_label): ?><option value="<?php echo esc_attr($occupation_key); ?>"><?php echo esc_html($occupation_label); ?></option><?php endforeach; ?></select><span class="mlr-error-message">انتخاب شغل الزامی است.</span></div>
                            <div class="mlr-form-group"><label for="postal_code">کد پستی:</label><input type="text" name="postal_code" id="postal_code" class="mlr-input" required><span class="mlr-error-message">کد پستی ۱۰ رقمی الزامی است.</span></div>
                        </div>
                        <div class="mlr-form-group"><label for="residential_address">آدرس محل سکونت:</label><textarea name="residential_address" id="residential_address" rows="3" class="mlr-textarea" required></textarea><span class="mlr-error-message">آدرس محل سکونت الزامی است.</span></div>
                        <div class="mlr-form-group"><label for="work_address">آدرس محل فعالیت:</label><textarea name="work_address" id="work_address" rows="3" class="mlr-textarea" required></textarea><span class="mlr-error-message">آدرس محل فعالیت الزامی است.</span></div>
                        
                        <div class="mlr-form-group">
                            <label>محصولات درخواستی</label>
                            <div class="mlr-loan-amount-notice">مبلغ درخواستی شما: <span id="display_loan_amount">0</span> ریال</div>
                            <div id="mlr-product-repeater">
                                <div class="mlr-product-repeater-header">
                                    <div>شناسه محصول</div>
                                    <div>نام کامل محصول</div>
                                    <div>قیمت (ریال)</div>
                                    <div>تعداد</div>
                                    <div>مبلغ کل ردیف</div>
                                    <div></div>
                                </div>
                                <div class="mlr-product-row">
                                    <label class="mlr-product-field mlr-product-field--id">
                                        <span class="mlr-product-field-label">شناسه محصول <span class="mlr-product-optional">(اختیاری)</span></span>
                                        <input type="text" class="mlr-input product-id" placeholder="مثلاً کد یا شناسه کالا" autocomplete="off" aria-label="شناسه محصول (اختیاری)">
                                    </label>
                                    <label class="mlr-product-field mlr-product-field--name">
                                        <span class="mlr-product-field-label">نام کامل محصول <span class="mlr-required-mark" aria-hidden="true">*</span></span>
                                        <input type="text" class="mlr-input product-name" placeholder="نام کامل محصول را وارد کنید" autocomplete="off" aria-label="نام کامل محصول" required>
                                    </label>
                                    <label class="mlr-product-field mlr-product-field--price">
                                        <span class="mlr-product-field-label">قیمت واحد <span class="mlr-product-unit">(ریال)</span> <span class="mlr-required-mark" aria-hidden="true">*</span></span>
                                        <input type="text" class="mlr-input product-price" placeholder="قیمت واحد به ریال" inputmode="numeric" autocomplete="off" aria-label="قیمت واحد محصول به ریال" required>
                                    </label>
                                    <label class="mlr-product-field mlr-product-field--qty">
                                        <span class="mlr-product-field-label">تعداد <span class="mlr-required-mark" aria-hidden="true">*</span></span>
                                        <input type="number" class="mlr-input product-qty" min="1" step="1" value="1" inputmode="numeric" aria-label="تعداد محصول" required>
                                    </label>
                                    <div class="mlr-product-field mlr-product-field--total">
                                        <span class="mlr-product-field-label">مبلغ کل ردیف</span>
                                        <div class="product-row-total" aria-live="polite">0 ریال</div>
                                    </div>
                                    <button type="button" class="mlr-remove-product-btn" style="display: none;" aria-label="حذف این محصول" title="حذف این محصول">&times;</button>
                                </div>
                            </div>
                            <button type="button" id="mlr-add-product-btn" class="mlr-btn-add-row">+ افزودن محصول دیگر</button>
                            <div class="mlr-product-total-summary">
                                جمع کل مبلغ محصولات: <span id="display_product_total">0</span> ریال
                            </div>
                             <span class="mlr-error-message" id="product-error">لطفاً اطلاعات تمام محصولات را کامل کرده و مجموع مبلغ آن‌ها را با مبلغ درخواستی برابر کنید.</span>
                        </div>

                        <?php if ($marketplace_enabled): ?>
                        <div class="mlr-form-group mlr-acceptor-assignment-section">
                            <label>انتخاب پذیرنده و شرایط تأمین</label>
                            <?php if (!empty($marketplace_settings['applicant_help_text'])): ?>
                                <p class="mlr-step-description"><?php echo esc_html($marketplace_settings['applicant_help_text']); ?></p>
                            <?php endif; ?>
                            <?php if ($locked_acceptor_id > 0): ?>
                                <input type="hidden" name="acceptor_assignment_mode" value="applicant_selected_acceptor">
                                <input type="hidden" name="selected_acceptor_id" id="selected_acceptor_id" value="<?php echo esc_attr($locked_acceptor_id); ?>">
                                <div class="mlr-selected-acceptor-card" style="display:block;">
                                    <strong>پذیرنده انتخاب‌شده:</strong>
                                    <?php echo esc_html($locked_acceptor_name ?: ('#' . $locked_acceptor_id)); ?>
                                    <p class="description">این پذیرنده از طریق لینک اختصاصی ثبت درخواست قفل شده است.</p>
                                </div>
                            <?php else: ?>
                            <div class="mlr-assignment-options">
                                <?php if (!empty($marketplace_settings['allow_applicant_selected_acceptor'])): ?>
                                <label class="mlr-assignment-card">
                                    <input type="radio" name="acceptor_assignment_mode" value="applicant_selected_acceptor">
                                    <span>پذیرنده دارم</span>
                                </label>
                                <?php endif; ?>
                                <?php if (!empty($marketplace_settings['allow_open_marketplace'])): ?>
                                <label class="mlr-assignment-card">
                                    <input type="radio" name="acceptor_assignment_mode" value="open_acceptor_marketplace">
                                    <span>پذیرنده ندارم</span>
                                </label>
                                <?php endif; ?>
                            </div>
                            <span class="mlr-error-message" id="acceptor-assignment-error">لطفاً وضعیت پذیرنده را مشخص کنید.</span>
                            <?php endif; ?>
                        </div>

                        <?php if (!empty($marketplace_settings['allow_applicant_selected_acceptor']) && !$locked_acceptor_id): ?>
                        <div id="mlr-selected-acceptor-panel" class="mlr-marketplace-panel" style="display:none;">
                            <div class="mlr-form-group">
                                <label for="mlr-acceptor-search">جستجوی پذیرنده</label>
                                <input type="text" id="mlr-acceptor-search" class="mlr-input" autocomplete="off" placeholder="نام پذیرنده، فروشگاه، شهر یا حوزه فعالیت">
                                <input type="hidden" name="selected_acceptor_id" id="selected_acceptor_id" value="">
                                <span class="mlr-error-message" id="selected-acceptor-error">انتخاب پذیرنده الزامی است.</span>
                                <p class="description">ابتدا طرح تسهیلات را انتخاب کنید تا فقط پذیرنده‌های مجاز همان طرح نمایش داده شوند.</p>
                            </div>
                            <div id="mlr-acceptor-search-results" class="mlr-acceptor-search-results"></div>
                            <div id="mlr-selected-acceptor-card" class="mlr-selected-acceptor-card" style="display:none;"></div>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($marketplace_settings['allow_open_marketplace'])): ?>
                        <div id="mlr-open-marketplace-panel" class="mlr-marketplace-panel" style="display:none;" data-require-location="<?php echo !empty($marketplace_settings['require_location']) ? '1' : '0'; ?>" data-require-category="<?php echo !empty($marketplace_settings['require_category']) ? '1' : '0'; ?>">
                            <p class="mlr-step-description">درخواست شما برای پذیرنده‌هایی نمایش داده می‌شود که با موقعیت و حوزه فعالیت انتخابی شما همخوانی داشته باشند.</p>
                            <div class="mlr-form-group">
                                <label>محدوده جغرافیایی</label>
                                <div class="mlr-assignment-options">
                                    <?php if (!empty($marketplace_settings['allow_all_country'])): ?>
                                    <label class="mlr-assignment-card">
                                        <input type="radio" name="requested_service_scope" value="all_country" checked>
                                        <span>کل کشور</span>
                                    </label>
                                    <?php endif; ?>
                                    <label class="mlr-assignment-card">
                                        <input type="radio" name="requested_service_scope" value="selected_locations" <?php checked(empty($marketplace_settings['allow_all_country'])); ?>>
                                        <span>موقعیت‌های منتخب</span>
                                    </label>
                                </div>
                            </div>
                            <div class="mlr-form-group" id="mlr-requested-locations-wrap" style="display:none;">
                                <label for="requested_location_terms">موقعیت‌ها</label>
                                <?php if (!empty($marketplace_location_terms)): ?>
                                <input type="search" id="mlr-location-term-search" class="mlr-input mlr-term-search" placeholder="جستجوی استان یا شهر..." autocomplete="off">
                                <select name="requested_location_terms[]" id="requested_location_terms" class="mlr-select" multiple>
                                    <?php foreach ($marketplace_location_terms as $location_row): ?>
                                        <?php $location_term = $location_row['term']; ?>
                                        <option value="<?php echo esc_attr($location_term->term_id); ?>" data-search="<?php echo esc_attr($location_term->name . ' ' . $location_term->slug); ?>"><?php echo esc_html(str_repeat('— ', absint($location_row['depth'])) . $location_term->name); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <?php else: ?>
                                    <p class="description" style="color:#b32d2e;">هیچ term موقعیتی برای taxonomy تنظیم‌شده پیدا نشد.</p>
                                <?php endif; ?>
                                <span class="mlr-error-message" id="requested-location-error">حداقل یک موقعیت انتخاب کنید.</span>
                            </div>
                            <div class="mlr-form-group">
                                <label>حوزه فعالیت / محصول یا خدمات</label>
                                <input type="search" id="mlr-category-term-search" class="mlr-input mlr-term-search" placeholder="جستجوی حوزه فعالیت..." autocomplete="off">
                                <div class="mlr-category-grid">
                                    <?php if (!empty($marketplace_category_terms)): ?>
                                        <?php foreach ($marketplace_category_terms as $category): ?>
                                            <label class="mlr-category-option" data-search="<?php echo esc_attr($category->name . ' ' . $category->slug); ?>"><input type="checkbox" name="requested_activity_categories[]" value="<?php echo esc_attr($category->term_id); ?>"> <?php echo esc_html($category->name); ?></label>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <p class="description" style="color:#b32d2e;">هیچ term حوزه فعالیت برای taxonomy تنظیم‌شده پیدا نشد.</p>
                                    <?php endif; ?>
                                </div>
                                <span class="mlr-error-message" id="requested-categories-error">حداقل یک حوزه فعالیت انتخاب کنید.</span>
                            </div>
                            <div class="mlr-form-group">
                                <label for="request_public_summary">توضیح کوتاه درخواست</label>
                                <textarea name="request_public_summary" id="request_public_summary" class="mlr-textarea" rows="3" placeholder="مثلاً نوع کالا یا خدمت موردنیاز را کوتاه توضیح دهید."></textarea>
                                <span class="mlr-error-message" id="request-public-summary-error">توضیح کوتاه باید حداقل ۱۰ کاراکتر باشد.</span>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php endif; ?>

                        <div class="mlr-step-buttons">
                            <button type="button" class="mlr-btn mlr-btn-secondary mlr-prev-btn" data-target-step="2" id="prev_step_4">مرحله قبل</button>
                            <button type="button" class="mlr-btn mlr-btn-primary mlr-next-btn" id="next_step_4" data-target-step="5" disabled>مرحله بعد</button>
                        </div>
                    </div>

                    <div class="mlr-step-content" id="step5">
                        <p class="mlr-step-description">لطفا مدارک مورد نیاز طرح انتخابی را بارگذاری نمایید.</p>
                        <div id="dynamic-documents"></div>
                         <div id="mlr-submission-feedback"></div>
                        <div class="mlr-step-buttons">
                            <button type="button" class="mlr-btn mlr-btn-secondary mlr-prev-btn" data-target-step="4">مرحله قبل</button>
                            <button type="button" class="mlr-btn mlr-btn-primary" id="final-submit-btn" disabled>ارسال نهایی درخواست</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <style>
        /* --- Glassmorphism & Modern UI Variables --- */
        :root {
            --mlr-blue-dark: #1D3558;
            --mlr-blue-light: #D0E2F2;
            --mlr-gold: #eda900;
            --mlr-white-glass: rgba(255, 255, 255, 0.75);
            --mlr-white-glass-strong: rgba(255, 255, 255, 0.9);
            --mlr-border-glass: 1px solid rgba(255, 255, 255, 0.6);
            --mlr-shadow-soft: 0 10px 40px -10px rgba(29, 53, 88, 0.15);
            --mlr-radius-lg: 24px;
            --mlr-radius-md: 14px;
            --mlr-font-family: 'Vazirmatn', Tahoma, sans-serif;
        }

        /* --- Main Container --- */
        .mlr-form-container {
            font-family: var(--mlr-font-family) !important;
            max-width: 1200px;
            margin: 40px auto;
            background: linear-gradient(135deg, #f0f5f9 0%, var(--mlr-blue-light) 100%) !important;
            border-radius: var(--mlr-radius-lg) !important;
            box-shadow: var(--mlr-shadow-soft) !important;
            overflow: hidden;
            display: flex;
            min-height: 700px;
            position: relative;
            border: 4px solid rgba(255, 255, 255, 0.6) !important;
            direction: rtl;
            color: var(--mlr-blue-dark);
        }

        /* Decorative Blobs */
        .mlr-glass-blob {
            position: absolute;
            border-radius: 50%;
            filter: blur(80px);
            z-index: 0;
        }
        .blob-1 { width: 450px; height: 450px; background: var(--mlr-blue-light); top: -100px; left: -100px; opacity: 0.8; }
        .blob-2 { width: 350px; height: 350px; background: var(--mlr-gold); bottom: -80px; right: -80px; opacity: 0.15; }

        .mlr-form-wrapper {
            display: flex;
            width: 100%;
            position: relative;
            z-index: 1;
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            background: rgba(255, 255, 255, 0.3);
        }

        /* --- Sidebar Stepper --- */
        .mlr-stepper-sidebar {
            width: 300px;
            background: rgba(255, 255, 255, 0.4);
            border-left: 1px solid rgba(255, 255, 255, 0.5);
            padding: 40px 25px;
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .mlr-step-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px 20px;
            border-radius: var(--mlr-radius-md);
            transition: all 0.3s ease;
            position: relative;
            color: rgba(29, 53, 88, 0.6);
            cursor: default;
            border: 1px solid transparent;
        }
        
        .mlr-step-item.active,
        .mlr-step-item.is-active {
            background: var(--mlr-blue-dark);
            box-shadow: 0 10px 25px -5px rgba(29, 53, 88, 0.3);
            color: #fff;
            transform: translateX(-8px);
        }
        
        .mlr-step-item.completed,
        .mlr-step-item.is-completed {
            background: rgba(255, 255, 255, 0.6);
            color: var(--mlr-blue-dark);
        }

        .mlr-step-icon {
            width: 42px;
            height: 42px;
            border-radius: 12px !important;
            background: rgba(255,255,255,0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
            color: var(--mlr-blue-dark);
            flex-shrink: 0;
        }

        .mlr-step-item.active .mlr-step-icon,
        .mlr-step-item.is-active .mlr-step-icon {
            background: rgba(255,255,255,0.2);
            color: #fff;
        }
        
        .mlr-step-item.completed .mlr-step-icon,
        .mlr-step-item.is-completed .mlr-step-icon {
            background: var(--mlr-gold);
            color: #fff;
        }

        .mlr-step-text { display: flex; flex-direction: column; }
        .mlr-step-label { font-size: 14px; font-weight: 800; }
        .mlr-step-sublabel { font-size: 11px; opacity: 0.8; margin-top: 3px; }

        /* --- Content Area --- */
        .mlr-form-content {
            flex: 1;
            padding: 40px 50px;
            background: rgba(255, 255, 255, 0.55);
            overflow-y: auto;
        }

        .mlr-step-content { animation: fadeIn 0.5s cubic-bezier(0.16, 1, 0.3, 1); }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }

        .mlr-step-description {
            font-size: 15px;
            color: var(--mlr-blue-dark);
            margin-bottom: 30px;
            line-height: 1.7;
            background: var(--mlr-white-glass);
            padding: 15px 20px;
            border-radius: var(--mlr-radius-md);
            border: var(--mlr-border-glass);
            border-right: 4px solid var(--mlr-gold);
        }

        /* --- Form Controls --- */
        .mlr-form-group { margin-bottom: 25px; }
        .mlr-form-group label { display: block; font-weight: 700; color: var(--mlr-blue-dark); margin-bottom: 8px; font-size: 13px; }
        
        .mlr-input, .mlr-select, .mlr-textarea {
            width: 100% !important;
            padding: 12px 16px !important;
            border: 1px solid rgba(29, 53, 88, 0.2) !important;
            border-radius: 12px !important;
            font-size: 14px !important;
            transition: all 0.3s !important;
            background: rgba(255, 255, 255, 0.8) !important;
            color: var(--mlr-blue-dark) !important;
            box-shadow: none !important;
        }

        .mlr-input:focus, .mlr-select:focus, .mlr-textarea:focus {
            border-color: var(--mlr-blue-dark) !important;
            background: #fff !important;
            box-shadow: 0 0 0 4px rgba(29, 53, 88, 0.1) !important;
            outline: none !important;
        }

        .mlr-select {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%231D3558'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E") !important;
            background-repeat: no-repeat;
            background-position: left 15px center !important;
            background-size: 18px;
        }

        /* --- Grid System --- */
        .mlr-grid-2-col { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .mlr-birthdate-group { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px; }

        /* --- Buttons --- */
        .mlr-step-buttons {
            display: flex;
            justify-content: space-between;
            margin-top: 40px;
            padding-top: 25px;
            border-top: 1px solid rgba(29, 53, 88, 0.1);
        }

        .mlr-btn {
            padding: 12px 30px !important;
            border-radius: 12px !important;
            font-weight: 700;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s !important;
            border: none !important;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .mlr-btn-primary {
            background: var(--mlr-blue-dark) !important;
            color: #fff !important;
            box-shadow: 0 8px 20px -5px rgba(29, 53, 88, 0.4) !important;
        }

        .mlr-btn-primary:hover {
            background: var(--mlr-gold) !important;
            color: var(--mlr-blue-dark) !important;
            transform: translateY(-2px) !important;
        }

        .mlr-btn-primary:disabled {
            background: #cbd5e1 !important;
            color: #94a3b8 !important;
            cursor: not-allowed;
            box-shadow: none;
            transform: none;
        }

        .mlr-btn-secondary {
            background: rgba(255,255,255,0.6) !important;
            color: var(--mlr-blue-dark) !important;
            border: 1px solid rgba(29, 53, 88, 0.1) !important;
        }

        .mlr-btn-secondary:hover {
            background: #fff !important;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05) !important;
        }

        /* --- Product Repeater --- */
        #mlr-product-repeater {
            border: 1px solid rgba(29, 53, 88, 0.15);
            border-radius: 14px;
            overflow: hidden;
            margin-bottom: 15px;
            background: var(--mlr-white-glass);
        }

        .mlr-product-repeater-header {
            display: grid;
            grid-template-columns: 1fr 2fr 1fr 0.5fr 1fr 40px;
            background: rgba(29, 53, 88, 0.08);
            padding: 15px;
            font-weight: 700;
            font-size: 13px;
            color: var(--mlr-blue-dark);
            border-bottom: 1px solid rgba(29, 53, 88, 0.1);
        }

        .mlr-product-row {
            display: grid;
            grid-template-columns: 1fr 2fr 1fr 0.5fr 1fr 40px;
            padding: 15px;
            gap: 10px;
            align-items: center;
            border-bottom: 1px solid rgba(29, 53, 88, 0.05);
            transition: background 0.2s;
        }
        
        .mlr-product-row:hover { background: rgba(255,255,255,0.5); }
        .mlr-product-row input { padding: 8px 12px !important; font-size: 13px !important; height: auto !important; background: #fff !important; }

        .mlr-marketplace-panel {
            padding: 16px;
            border: 1px solid rgba(29, 53, 88, 0.12);
            border-radius: 14px;
            background: rgba(255,255,255,0.45);
            margin-bottom: 18px;
        }
        .mlr-assignment-options {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 12px;
        }
        .mlr-assignment-card {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 12px 14px;
            border: 1px solid rgba(29, 53, 88, 0.16);
            border-radius: 12px;
            background: rgba(255,255,255,0.65);
            cursor: pointer;
            font-weight: 700;
        }
        .mlr-assignment-card:has(input:checked) {
            border-color: var(--mlr-blue-dark);
            box-shadow: 0 0 0 2px rgba(29, 53, 88, 0.08);
        }
        .mlr-category-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 10px;
        }
        .mlr-acceptor-result,
        .mlr-selected-acceptor-card {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            padding: 12px;
            margin-top: 8px;
            border: 1px solid rgba(29, 53, 88, 0.12);
            border-radius: 10px;
            background: rgba(255,255,255,0.72);
        }
        .mlr-acceptor-result button,
        .mlr-selected-acceptor-card button {
            white-space: nowrap;
        }

        .mlr-btn-add-row {
            width: 100%;
            padding: 12px;
            background: rgba(208, 226, 242, 0.5);
            color: var(--mlr-blue-dark);
            border: 1px dashed var(--mlr-blue-dark);
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        .mlr-btn-add-row:hover { background: var(--mlr-blue-light); }

        /* --- Notices & Errors --- */
        .mlr-loan-amount-notice {
            background: var(--mlr-blue-light);
            color: var(--mlr-blue-dark);
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 15px;
            font-weight: 600;
            border: 1px solid rgba(255,255,255,0.5);
        }
        
        .mlr-error-message { color: #ef4444; font-size: 13px; margin-top: 6px; display: none; font-weight: 500; }
        .mlr-form-group.has-error input { border-color: #ef4444 !important; background: #fef2f2 !important; }

        /* --- Credit Report (Glass Style) --- */
        .mlr-credit-report {
            background: var(--mlr-white-glass-strong);
            border: 1px solid #fff;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
        }
        
        .mlr-report-header { display: flex; align-items: center; padding: 25px; color: #fff; gap: 20px; }
        .mlr-risk-low { background: linear-gradient(135deg, #10b981 0%, #059669 100%); }
        .mlr-risk-medium { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); }
        .mlr-risk-high { background: linear-gradient(135deg, #ef4444 0%, #b91c1c 100%); }
        .mlr-risk-unknown { background: linear-gradient(135deg, #64748b 0%, #475569 100%); }

        .mlr-report-section { padding: 20px; border-bottom: 1px solid rgba(29, 53, 88, 0.08); }
        .mlr-report-section:last-child { border-bottom: none; }
        .mlr-report-section h4 { margin: 0 0 15px 0; font-size: 15px; color: var(--mlr-blue-dark); border-right: 3px solid var(--mlr-gold); padding-right: 10px; display: flex; align-items: center; gap: 8px; }
        
        .mlr-report-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 15px; font-size: 14px; color: #586e8a; }
        
        /* Summary Cards */
        .mlr-summary-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 15px; margin-top: 10px; }
        .mlr-summary-card {
            background: rgba(208, 226, 242, 0.4);
            border: 1px solid rgba(255,255,255,0.6);
            border-radius: 16px;
            padding: 15px;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            transition: transform 0.2s;
        }
        .mlr-summary-card:hover { transform: translateY(-3px); background: #fff; box-shadow: 0 8px 20px rgba(29, 53, 88, 0.1); }
        
        .mlr-summary-icon { font-size: 24px; color: var(--mlr-blue-dark); margin-bottom: 8px; width: 24px; height: 24px; }
        .mlr-summary-val { font-size: 18px; font-weight: 800; color: var(--mlr-blue-dark); margin-bottom: 4px; }
        .mlr-summary-label { font-size: 11px; color: #586e8a; }

        /* Contract Cards */
        .mlr-cards-container { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 15px; }
        .mlr-contract-card {
            border: 1px solid rgba(29, 53, 88, 0.15);
            border-radius: 16px;
            overflow: hidden;
            background: var(--mlr-white-glass);
            transition: all 0.2s;
        }
        .mlr-contract-card:hover { background: #fff; box-shadow: 0 8px 20px rgba(29, 53, 88, 0.1); }
        
        .mlr-contract-header { background: rgba(208, 226, 242, 0.6); padding: 12px 15px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid rgba(29, 53, 88, 0.1); }
        .mlr-contract-org { font-weight: 700; font-size: 13px; color: var(--mlr-blue-dark); }
        .mlr-contract-badge { font-size: 11px; padding: 3px 8px; border-radius: 12px; color: #fff; font-weight: 600; }
        .mlr-contract-body { padding: 15px; font-size: 13px; }
        .mlr-contract-row { display: flex; justify-content: space-between; margin-bottom: 8px; border-bottom: 1px dashed rgba(29, 53, 88, 0.1); padding-bottom: 4px; }
        .mlr-contract-row:last-child { margin-bottom: 0; border-bottom: none; }

        /* Tables */
        .mlr-table-wrapper { overflow-x: auto; border: 1px solid rgba(29, 53, 88, 0.15); border-radius: 12px; }
        .mlr-report-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .mlr-report-table th { background: rgba(208, 226, 242, 0.6); color: var(--mlr-blue-dark); font-weight: 700; text-align: right; padding: 12px 15px; border-bottom: 1px solid rgba(29, 53, 88, 0.1); white-space: nowrap; }
        .mlr-report-table td { padding: 12px 15px; border-bottom: 1px solid rgba(29, 53, 88, 0.05); color: var(--mlr-blue-dark); }
        .mlr-report-table tr:hover td { background: rgba(208, 226, 242, 0.2); }

        /* Circular Chart */
        .mlr-score-circle-wrapper { text-align: center; min-width: 120px; }
        .mlr-circular-chart { display: block; margin: 0 auto; max-width: 80px; max-height: 80px; }
        .mlr-circle-bg { fill: none; stroke: rgba(255,255,255,0.3); stroke-width: 3.8; }
        .mlr-circle { fill: none; stroke-width: 2.8; stroke-linecap: round; animation: progress 1s ease-out forwards; stroke: #fff; }
        .mlr-percentage { fill: #fff; font-family: sans-serif; font-weight: bold; font-size: 0.5em; text-anchor: middle; }
        @keyframes progress { 0% { stroke-dasharray: 0 100; } }
        .mlr-score-label { display: block; font-size: 12px; opacity: 0.9; margin-top: 8px; }

        /* --- Responsive --- */
        @media (max-width: 992px) {
            .mlr-form-container { flex-direction: column; min-height: auto; }
            .mlr-stepper-sidebar {
                width: 100%;
                flex-direction: row;
                overflow-x: auto;
                padding: 20px;
                border-left: none;
                border-bottom: 1px solid rgba(255,255,255,0.5);
                gap: 15px;
            }
            .mlr-step-item { min-width: 170px; padding: 10px; background: rgba(255,255,255,0.5); border: 1px solid rgba(255,255,255,0.3); }
            .mlr-step-item.active, .mlr-step-item.is-active { background: var(--mlr-blue-dark); color: #fff; }
            .mlr-form-content { padding: 30px 20px; }
            .mlr-grid-2-col, .mlr-birthdate-group { grid-template-columns: 1fr; }
            .mlr-product-repeater-header { display: none; } /* Hide header on mobile */
            .mlr-product-row { grid-template-columns: 1fr; gap: 15px; position: relative; padding-top: 35px; }
            .mlr-remove-product-btn { position: absolute; top: 10px; left: 10px; }
            .mlr-product-row input { width: 100% !important; }
        }
    
/* === MLR 8.4.6.20: responsive form/stepper hardening === */
.mlr-form-container,
.mlr-form-container .mlr-form-wrapper,
.mlr-form-container .mlr-form-content,
.mlr-form-container form,
.mlr-form-container .mlr-step-content {
    min-width: 0;
    max-width: 100%;
    box-sizing: border-box;
}

.mlr-form-container .mlr-steps-container {
    width: 100%;
    min-width: 0;
}

.mlr-form-container .mlr-step-icon,
.mlr-form-container .mlr-step-icon svg {
    flex: 0 0 auto;
}

.mlr-form-container .mlr-step-icon svg {
    width: 22px;
    height: 22px;
    max-width: 100%;
}

.mlr-form-container .mlr-table-wrapper,
.mlr-form-container #calculation-result {
    max-width: 100%;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}

@media (max-width: 992px) {
    .mlr-form-container {
        display: block !important;
        width: 100% !important;
        max-width: 100% !important;
        min-height: 0 !important;
        margin: 16px 0 !important;
        border-width: 2px !important;
        border-radius: 18px !important;
        overflow: hidden !important;
    }

    .mlr-form-container .mlr-form-wrapper {
        display: flex !important;
        flex-direction: column !important;
        width: 100% !important;
        min-height: 0 !important;
        overflow: hidden !important;
    }

    .mlr-form-container .mlr-stepper-sidebar {
        display: block !important;
        width: 100% !important;
        max-width: 100% !important;
        min-width: 0 !important;
        padding: 12px !important;
        border-left: 0 !important;
        border-bottom: 1px solid rgba(29, 53, 88, 0.10) !important;
        overflow-x: auto !important;
        overflow-y: hidden !important;
        overscroll-behavior-inline: contain;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: thin;
    }

    .mlr-form-container .mlr-steps-container {
        display: flex !important;
        flex-direction: row !important;
        align-items: stretch !important;
        justify-content: flex-start !important;
        gap: 8px !important;
        width: max-content !important;
        min-width: 100% !important;
        padding: 1px !important;
    }

    .mlr-form-container .mlr-steps-container::before {
        display: none !important;
        content: none !important;
    }

    .mlr-form-container .mlr-step-item {
        position: relative !important;
        inset: auto !important;
        display: flex !important;
        flex: 1 0 76px !important;
        flex-direction: column !important;
        align-items: center !important;
        justify-content: center !important;
        gap: 6px !important;
        width: auto !important;
        min-width: 76px !important;
        max-width: 94px !important;
        min-height: 78px !important;
        margin: 0 !important;
        padding: 8px 6px !important;
        border: 1px solid rgba(29, 53, 88, 0.10) !important;
        border-radius: 12px !important;
        background: rgba(255, 255, 255, 0.58) !important;
        color: rgba(29, 53, 88, 0.74) !important;
        text-align: center !important;
        transform: none !important;
        box-shadow: none !important;
        overflow: hidden !important;
    }

    .mlr-form-container .mlr-step-item.active,
    .mlr-form-container .mlr-step-item.is-active {
        background: var(--mlr-blue-dark, #1D3558) !important;
        color: #fff !important;
        border-color: var(--mlr-blue-dark, #1D3558) !important;
        box-shadow: 0 6px 18px rgba(29, 53, 88, 0.22) !important;
        transform: none !important;
    }

    .mlr-form-container .mlr-step-item.completed,
    .mlr-form-container .mlr-step-item.is-completed {
        background: rgba(255, 255, 255, 0.88) !important;
        color: var(--mlr-blue-dark, #1D3558) !important;
    }

    .mlr-form-container .mlr-step-icon {
        position: static !important;
        inset: auto !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        flex: 0 0 40px !important;
        width: 40px !important;
        height: 40px !important;
        min-width: 40px !important;
        min-height: 40px !important;
        margin: 0 !important;
        border: 0 !important;
        border-radius: 11px !important;
        background: rgba(255, 255, 255, 0.76) !important;
        color: var(--mlr-blue-dark, #1D3558) !important;
        z-index: 1 !important;
    }

    .mlr-form-container .mlr-step-item.active .mlr-step-icon,
    .mlr-form-container .mlr-step-item.is-active .mlr-step-icon {
        background: rgba(255, 255, 255, 0.18) !important;
        color: #fff !important;
    }

    .mlr-form-container .mlr-step-item.completed .mlr-step-icon,
    .mlr-form-container .mlr-step-item.is-completed .mlr-step-icon {
        background: var(--mlr-gold, #eda900) !important;
        color: #fff !important;
    }

    .mlr-form-container .mlr-step-text {
        display: block !important;
        flex: 0 0 auto !important;
        width: 100% !important;
        min-width: 0 !important;
        text-align: center !important;
        line-height: 1.35 !important;
    }

    .mlr-form-container .mlr-step-label {
        display: block !important;
        width: 100% !important;
        font-size: 10.5px !important;
        font-weight: 800 !important;
        line-height: 1.45 !important;
        white-space: normal !important;
        overflow-wrap: anywhere !important;
    }

    .mlr-form-container .mlr-step-sublabel {
        display: none !important;
    }

    .mlr-form-container .mlr-form-content {
        width: 100% !important;
        min-width: 0 !important;
        padding: 24px 18px !important;
        overflow: visible !important;
    }

    .mlr-form-container .mlr-grid-2-col {
        grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
    }

    .mlr-form-container .mlr-birthdate-group {
        grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
    }

    .mlr-form-container .mlr-product-repeater-header {
        display: none !important;
    }

    .mlr-form-container .mlr-product-row {
        grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
        gap: 12px !important;
        position: relative !important;
        padding: 42px 12px 14px !important;
    }

    .mlr-form-container .mlr-product-row .product-name,
    .mlr-form-container .mlr-product-row .product-row-total {
        grid-column: 1 / -1 !important;
    }

    .mlr-form-container .mlr-remove-product-btn {
        position: absolute !important;
        top: 8px !important;
        left: 8px !important;
        width: 32px !important;
        height: 32px !important;
        margin: 0 !important;
    }

    .mlr-form-container .mlr-report-grid,
    .mlr-form-container .mlr-summary-grid,
    .mlr-form-container .mlr-cards-container,
    .mlr-form-container .mlr-assignment-options,
    .mlr-form-container .mlr-category-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
    }

    .mlr-form-container .mlr-plan-info-accordions {
        grid-template-columns: minmax(0, 1fr) !important;
    }
}

@media (max-width: 720px) {
    .mlr-form-container {
        margin: 10px 0 !important;
        border-radius: 14px !important;
    }

    .mlr-form-container .mlr-form-content {
        padding: 18px 13px !important;
    }

    .mlr-form-container .mlr-step-description {
        margin-bottom: 18px !important;
        padding: 12px 13px !important;
        font-size: 13px !important;
        line-height: 1.8 !important;
    }

    .mlr-form-container .mlr-grid-2-col,
    .mlr-form-container .mlr-report-grid,
    .mlr-form-container .mlr-cards-container,
    .mlr-form-container .mlr-assignment-options,
    .mlr-form-container .mlr-category-grid {
        grid-template-columns: minmax(0, 1fr) !important;
    }

    .mlr-form-container .mlr-summary-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
        gap: 10px !important;
    }

    .mlr-form-container .mlr-step-buttons {
        flex-direction: column-reverse !important;
        align-items: stretch !important;
        gap: 10px !important;
        margin-top: 24px !important;
        padding-top: 18px !important;
    }

    .mlr-form-container .mlr-step-buttons .mlr-btn,
    .mlr-form-container > .mlr-btn,
    .mlr-form-container form > .mlr-btn {
        width: 100% !important;
        max-width: 100% !important;
    }

    .mlr-form-container .mlr-product-row {
        grid-template-columns: minmax(0, 1fr) !important;
    }

    .mlr-form-container .mlr-product-row > * {
        grid-column: 1 / -1 !important;
        min-width: 0 !important;
    }

    .mlr-form-container .mlr-report-header {
        flex-direction: column !important;
        align-items: stretch !important;
        padding: 18px !important;
        text-align: center !important;
    }

    .mlr-form-container .mlr-score-circle-wrapper {
        min-width: 0 !important;
    }

    .mlr-form-container .mlr-report-section {
        padding: 14px !important;
    }

    .mlr-form-container .mlr-contract-header,
    .mlr-form-container .mlr-contract-row,
    .mlr-form-container .mlr-acceptor-result,
    .mlr-form-container .mlr-selected-acceptor-card {
        flex-direction: column !important;
        align-items: stretch !important;
        gap: 8px !important;
    }

    .mlr-form-container .mlr-acceptor-result button,
    .mlr-form-container .mlr-selected-acceptor-card button {
        width: 100% !important;
        white-space: normal !important;
    }

    .mlr-form-container .mlr-report-table,
    .mlr-form-container #calculation-result table {
        min-width: 560px;
    }

    .mlr-form-container input[type="file"] {
        width: 100% !important;
        max-width: 100% !important;
        overflow: hidden;
        font-size: 12px !important;
    }

    .mlr-form-container .mlr-uploaded-file-item {
        align-items: flex-start !important;
        flex-direction: column !important;
    }
}

@media (max-width: 480px) {
    .mlr-form-container .mlr-stepper-sidebar {
        padding: 10px 8px !important;
    }

    .mlr-form-container .mlr-steps-container {
        gap: 5px !important;
    }

    .mlr-form-container .mlr-step-item {
        flex-basis: 60px !important;
        min-width: 60px !important;
        max-width: 68px !important;
        min-height: 72px !important;
        padding: 7px 4px !important;
    }

    .mlr-form-container .mlr-step-icon {
        flex-basis: 34px !important;
        width: 34px !important;
        height: 34px !important;
        min-width: 34px !important;
        min-height: 34px !important;
    }

    .mlr-form-container .mlr-step-icon svg {
        width: 18px !important;
        height: 18px !important;
    }

    .mlr-form-container .mlr-step-label {
        font-size: 9px !important;
    }

    .mlr-form-container .mlr-form-content {
        padding: 15px 10px !important;
    }

    .mlr-form-container .mlr-birthdate-group,
    .mlr-form-container .mlr-summary-grid {
        grid-template-columns: minmax(0, 1fr) !important;
    }

    .mlr-form-container .mlr-form-group {
        margin-bottom: 18px !important;
    }

    .mlr-form-container .mlr-input,
    .mlr-form-container .mlr-select,
    .mlr-form-container .mlr-textarea {
        padding: 11px 12px !important;
        font-size: 13px !important;
    }
}

        </style>
    <?php
    return ob_get_clean();
}
