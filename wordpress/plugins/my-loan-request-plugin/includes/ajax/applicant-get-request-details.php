<?php
// FILE: my-loan-request-plugin/includes/ajax/get-frontend-request-details.php
if (!defined('ABSPATH')) {
    exit;
}

add_action('wp_ajax_mlr_applicant_get_request_details', 'mlr_applicant_get_request_details_callback');

function mlr_applicant_get_request_details_callback() {
    check_ajax_referer('mlr_frontend_nonce', 'nonce');

    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'لطفاً ابتدا وارد سایت شوید.']);
        return;
    }

    $request_id = isset($_POST['request_id']) ? intval($_POST['request_id']) : 0;
    if (!$request_id) {
        wp_send_json_error(['message' => 'شناسه درخواست نامعتبر است.']);
        return;
    }

    // Check context to hide specific fields for expert dashboards
    $context = isset($_POST['context']) ? sanitize_text_field($_POST['context']) : '';
    $is_expert_dashboard = ($context === 'expert_dashboard');

    global $wpdb;
    $current_user = wp_get_current_user();
    $user_id = $current_user->ID;
    $is_admin = current_user_can('manage_options');
    $is_bank_expert = in_array('bank', (array) $current_user->roles);

    $table_name = $wpdb->prefix . 'loan_requests';
    $plans_table = $wpdb->prefix . 'installment_plans';

    $request = $wpdb->get_row($wpdb->prepare("
        SELECT r.*, p.name AS plan_name, p.display_fields, p.display_fields_context
        FROM {$table_name} r 
        LEFT JOIN {$plans_table} p ON r.installment_plan_id = p.id 
        WHERE r.id = %d
    ", $request_id));

    if (!$request) {
        wp_send_json_error(['message' => 'درخواست مورد نظر یافت نشد.']);
        return;
    }

    if (!function_exists('mlr_is_applicant_origin_request') || !mlr_is_applicant_origin_request($request)) {
        wp_send_json_error(['message' => 'این درخواست متعلق به پنل متقاضی نیست.']);
        return;
    }

    if (!function_exists('mlr_can_access_loan_request') || !mlr_can_access_loan_request($request)) {
        wp_send_json_error(['message' => 'شما دسترسی به این درخواست ندارید.']);
        return;
    }

    // بررسی سطح دسترسی
    if (!$is_admin && !function_exists('mlr_can_access_loan_request')) {
        if ($is_bank_expert) {
            if ($request->assigned_bank_user_id != $user_id) {
                wp_send_json_error(['message' => 'شما دسترسی به این پرونده ندارید.']);
                return;
            }
        } else {
            if ($request->user_id != $user_id) {
                wp_send_json_error(['message' => 'شما دسترسی به این درخواست ندارید.']);
                return;
            }
        }
    }

    $uploaded_documents = function_exists('mlr_get_request_uploaded_documents')
        ? mlr_get_request_uploaded_documents($request->required_documents)
        : maybe_unserialize($request->required_documents);
    if (!is_array($uploaded_documents)) {
        $uploaded_documents = [];
    }
    
    $comments = !empty($request->comments) ? json_decode($request->comments, true) : [];
    $form_data = !empty($request->form_data) ? json_decode($request->form_data, true) : [];
    if (!is_array($form_data)) { $form_data = []; }
    
    // استخراج گزارش اعتبارسنجی (اولویت با ستون اختصاصی، سپس form_data)
    $credit_report = null;
    if (isset($request->credit_report_json) && !empty($request->credit_report_json)) {
        $credit_report = json_decode($request->credit_report_json, true);
    } elseif (isset($form_data['credit_report_json'])) {
        // اگر در form_data باشد، ممکن است رشته JSON باشد یا آرایه
        $cr_data = $form_data['credit_report_json'];
        if (is_string($cr_data)) {
            $credit_report = json_decode($cr_data, true);
            // تلاش مجدد با حذف اسلش‌ها اگر دیکد اولیه ناموفق بود
            if (json_last_error() !== JSON_ERROR_NONE) {
                $credit_report = json_decode(stripslashes($cr_data), true);
            }
        } else {
            $credit_report = $cr_data;
        }
    }

    // حذف فیلد گزارش اعتبارسنجی از لیست داده‌های فرم برای جلوگیری از نمایش تکراری و خام
    if (isset($form_data['credit_report_json'])) {
        unset($form_data['credit_report_json']);
    }
    

    $mlr_detail_hidden_form_data_keys = [
        'loan_amount',
        'facility_amount',
        'wallet_charge',
        'installment_interval',
        'installment_count',
        'installment_amount',
        'prepayment',
        'order_total',
        'total_payment',
        'profit',
        'acceptor_payment_amount',
        'final_settlement_amount',
    ];
    foreach ($mlr_detail_hidden_form_data_keys as $mlr_detail_hidden_form_data_key) {
        if (array_key_exists($mlr_detail_hidden_form_data_key, $form_data)) {
            unset($form_data[$mlr_detail_hidden_form_data_key]);
        }
    }

    $form_data_labels = [
        'full_name'            => 'نام کامل',
        'national_code'        => 'کد ملی',
        'mobile_number'        => 'شماره موبایل',
        'phone_number'         => 'تلفن ثابت',
        'birth_date'           => 'تاریخ تولد',
        'birth_day'            => 'روز تولد',
        'birth_month'          => 'ماه تولد',
        'birth_year'           => 'سال تولد',
        'occupation'           => 'شغل',
        'postal_code'          => 'کد پستی',
        'residential_address'  => 'آدرس محل سکونت',
        'order_items'          => 'اقلام سفارش',
        'installment_plan_id'  => 'شناسه طرح اقساط',
        'plan_id'              => 'شناسه طرح',
        'loan_amount'          => 'مبلغ درخواستی',
        'facility_amount'      => 'مبلغ تسهیلات',
        'installment_count'    => 'تعداد اقساط',
        'installment_interval' => 'فاصله اقساط',
        'wallet_charge'        => 'اعتبار خرید',
        'acceptor_payment_amount' => 'مبلغ تسویه',
    ];
    $request_events = function_exists('mlr_get_request_events') ? mlr_get_request_events($request_id, 500) : [];
    $request_messages = function_exists('mlr_get_request_messages') ? mlr_get_request_messages($request_id, 500) : [];
    $acceptor_settlement_amount_display = function_exists('mlr_format_acceptor_settlement_amount') ? mlr_format_acceptor_settlement_amount($request) : '—';
    $acceptor_settlement_amount_value = $acceptor_settlement_amount_display !== '—' ? $acceptor_settlement_amount_display . ' ریال' : '—';
    $can_view_leasing_deposit_amount = function_exists('mlr_can_view_leasing_deposit_amount') && mlr_can_view_leasing_deposit_amount();
    $leasing_deposit_amount_display = function_exists('mlr_format_leasing_deposit_amount') ? mlr_format_leasing_deposit_amount($request) : '—';
    $leasing_deposit_amount_value = $leasing_deposit_amount_display !== '—' ? $leasing_deposit_amount_display . ' ریال' : '—';

    // Guarantor Info (ضامن اول و ضامن دوم)
    $guarantor_info = !empty($request->guarantor_info) ? json_decode($request->guarantor_info, true) : [];
    $guarantor_info_2 = !empty($request->guarantor_info_2) ? json_decode($request->guarantor_info_2, true) : [];
    if (!is_array($guarantor_info_2)) {
        $guarantor_info_2 = [];
    }

    // فایل‌های تکمیلی
    $extra_files = [];
    $extra_files['bank_validation_file'] = $request->bank_validation_file ?? '';
    $extra_files['signature_image'] = $request->signature_image ?? '';
    $extra_files['contract_file'] = $request->contract_file ?? '';

    $parsed_check_images = !empty($request->check_images) && function_exists('mlr_extract_document_urls')
        ? mlr_extract_document_urls($request->check_images)
        : [];
    if (!empty($parsed_check_images)) {
        $extra_files['check_images'] = $parsed_check_images;
    }

    $extra_file_labels = [
        'bank_validation_file' => 'فایل اعتبارسنجی بانک',
        'signature_image'      => 'تصویر امضا',
        'contract_file'        => 'فایل قرارداد',
        'check_images'         => 'تصاویر چک',
    ];
    // مدارک ضامن در همان بخش فایل‌های تکمیلی (مثل بقیه مدارک پرونده)
    $guarantor_file_labels = [
        'national_card' => 'ضامن: کارت ملی',
        'id_card' => 'ضامن: شناسنامه',
        'payslip' => 'ضامن: فیش حقوقی/جواز',
        'sana' => 'ضامن: گواهی ثنا',
        'check' => 'ضامن: تصویر چک',
        'residence_deed' => 'ضامن: سند/اجاره سکونت',
        'work_deed' => 'ضامن: سند/اجاره محل کار',
        'account_statement' => 'ضامن: گردش حساب',
        'extra_docs' => 'ضامن: مدارک تکمیلی',
    ];
    if (!empty($guarantor_info) && is_array($guarantor_info)) {
        foreach ($guarantor_file_labels as $gk => $gl) {
            if (!empty($guarantor_info[$gk]) && is_string($guarantor_info[$gk]) && strpos($guarantor_info[$gk], 'http') === 0) {
                $extra_files['guarantor_' . $gk] = $guarantor_info[$gk];
                $extra_file_labels['guarantor_' . $gk] = $gl;
            }
        }
    }
    $guarantor_file_labels_2 = [
        'national_card' => 'ضامن دوم: کارت ملی',
        'id_card' => 'ضامن دوم: شناسنامه',
        'payslip' => 'ضامن دوم: فیش حقوقی/جواز',
        'sana' => 'ضامن دوم: گواهی ثنا',
        'check' => 'ضامن دوم: تصویر چک',
        'residence_deed' => 'ضامن دوم: سند/اجاره سکونت',
        'work_deed' => 'ضامن دوم: سند/اجاره محل کار',
        'account_statement' => 'ضامن دوم: گردش حساب',
        'extra_docs' => 'ضامن دوم: مدارک تکمیلی',
    ];
    if (!empty($guarantor_info_2) && is_array($guarantor_info_2)) {
        foreach ($guarantor_file_labels_2 as $gk => $gl) {
            if (!empty($guarantor_info_2[$gk]) && is_string($guarantor_info_2[$gk]) && strpos($guarantor_info_2[$gk], 'http') === 0) {
                $extra_files['guarantor_2_' . $gk] = $guarantor_info_2[$gk];
                $extra_file_labels['guarantor_2_' . $gk] = $gl;
            }
        }
    }
    // پیوست‌های مرحله در انتظار مدارک (نمایش به پذیرنده در کنار فاکتور و قرارداد)
    $pending_docs_attachments = isset($request->pending_docs_attachments) && $request->pending_docs_attachments !== '' && $request->pending_docs_attachments !== null
        ? json_decode($request->pending_docs_attachments, true)
        : [];
    if (is_array($pending_docs_attachments)) {
        foreach ($pending_docs_attachments as $pkey => $pitem) {
            if (is_array($pitem) && !empty($pitem['url']) && strpos($pitem['url'], 'http') === 0) {
                $extra_files['pending_doc_' . $pkey] = $pitem['url'];
                $extra_file_labels['pending_doc_' . $pkey] = !empty($pitem['label']) ? $pitem['label'] : $pkey;
            }
        }
    }

    $occupations = [
        'official_employee' => 'کارمند رسمی', 
        'contract_employee' => 'کارمند قراردادی', 
        'worker' => 'کارگر', 
        'freelancer' => 'آزاد', 
        'retired' => 'بازنشسته', 
        'pensioner' => 'مستمری بگیر', 
        'employer' => 'کارفرما', 
        'other' => 'سایر'
    ];
    $occupation_display = isset($occupations[$request->occupation]) ? $occupations[$request->occupation] : $request->occupation;
    $all_doc_labels = function_exists('mlr_get_all_document_options') ? mlr_get_all_document_options() : [];
    $request_document_stage = function_exists('mlr_get_document_flow_stage') ? mlr_get_document_flow_stage($request->status) : '';
    $can_show_document_upload_forms = !$is_bank_expert && !$is_admin;
    $show_defect_documents_box = $can_show_document_upload_forms && $request_document_stage === 'defect_documents';
    $show_completion_documents_box = $can_show_document_upload_forms && in_array($request_document_stage, ['completion_documents', 'pending_documents'], true);
    $show_guarantee_check_principal = function_exists('mlr_is_plan_calculation_field_enabled')
        ? mlr_is_plan_calculation_field_enabled($request, 'guarantee_check_principal', $context)
        : true;
    $show_guarantee_check_total = function_exists('mlr_is_plan_calculation_field_enabled')
        ? mlr_is_plan_calculation_field_enabled($request, 'guarantee_check_total', $context)
        : true;

    // تولید شناسه‌های کاملاً یکتا برای جلوگیری از تداخل DOM
    $uniq = uniqid('req_');
    $form_id = 'mlr_upload_form_' . $uniq;
    $input_file_id = 'mlr_file_' . $uniq;
    $input_note_id = 'mlr_note_' . $uniq;
    $input_req_id = 'mlr_req_' . $uniq;
    $guarantor_acc_id = 'mlr_guarantor_acc_' . $uniq;
    $guarantor_acc_id_2 = 'mlr_guarantor_acc_2_' . $uniq;

    // --- Data Preparation for Unified View ---
    $sections = [
        'identity' => [
            'title' => 'اطلاعات هویتی و تماس',
            'icon' => 'dashicons-id',
            'fields' => [
                ['label' => 'نام و نام خانوادگی', 'value' => $request->full_name],
                ['label' => 'کد ملی', 'value' => $request->national_code],
                ['label' => 'نام پدر', 'value' => $form_data['father_name'] ?? '-'],
                ['label' => 'شماره شناسنامه', 'value' => $form_data['certificate_number'] ?? '-'],
                ['label' => 'صادره از', 'value' => $form_data['certificate_city'] ?? '-'],
                ['label' => 'تاریخ تولد', 'value' => function_exists('mlr_gregorian_to_jalali') ? mlr_gregorian_to_jalali($request->birth_date) : $request->birth_date],
                ['label' => 'شماره موبایل', 'value' => $request->mobile_number],
                ['label' => 'تلفن ثابت', 'value' => $request->phone_number ?: '-'],
            ]
        ],
        'job_addr' => [
            'title' => 'اطلاعات شغلی و سکونت',
            'icon' => 'dashicons-location',
            'fields' => [
                ['label' => 'شغل', 'value' => $occupation_display],
                ['label' => 'کد پستی', 'value' => $request->postal_code ?: '-'],
                ['label' => 'آدرس سکونت', 'value' => $request->residential_address ?: '-', 'wide' => true],
                ['label' => 'آدرس محل کار', 'value' => $request->work_address ?: '-', 'wide' => true],
            ]
        ],
        'financial' => [
            'title' => 'اطلاعات مالی و طرح',
            'icon' => 'dashicons-chart-line',
            'fields' => [
                ['label' => 'نام طرح', 'value' => $request->plan_name],
                ['label' => 'مبلغ درخواستی', 'value' => number_format($request->loan_amount) . ' ریال'],
                ['label' => 'مبلغ تسهیلات', 'value' => isset($request->facility_amount) && $request->facility_amount !== '' ? number_format(floatval($request->facility_amount)) . ' ریال' : (number_format($request->loan_amount) . ' ریال')],
                ['label' => 'فاصله اقساط', 'value' => isset($request->installment_interval) ? $request->installment_interval . ' ماه' : '—'],
                ['label' => 'تعداد اقساط', 'value' => $request->installment_count . ' قسط'],
                ['label' => 'مبلغ هر قسط', 'value' => number_format($request->installment_amount) . ' ریال'],
                ['label' => 'پیش‌پرداخت', 'value' => number_format($request->prepayment) . ' ریال'],
                ['label' => 'مبلغ کل سفارش', 'value' => number_format($request->order_total) . ' ریال'],
                !$is_expert_dashboard ? ['label' => 'اعتبار خرید', 'value' => number_format($request->wallet_charge) . ' ریال'] : null,
                ['label' => 'مبلغ تسویه', 'value' => $acceptor_settlement_amount_value],
                $can_view_leasing_deposit_amount ? ['label' => 'مبلغ (واریزی لیزینگ)', 'value' => $leasing_deposit_amount_value] : null,
                ['label' => 'وضعیت درخواست', 'value' => $request->status, 'is_status' => true],
            ]
        ],
        'dates' => [
            'title' => 'تاریخ‌های پرونده',
            'icon' => 'dashicons-calendar-alt',
            'fields' => [
                ['label' => 'تاریخ ثبت', 'value' => !empty($request->created_at) && function_exists('mlr_gregorian_to_jalali') ? mlr_gregorian_to_jalali($request->created_at, 'Y/m/d H:i') : (!empty($request->created_at) ? $request->created_at : '—')],
                ['label' => 'تاریخ اعطا', 'value' => !empty($request->last_edited_at) && function_exists('mlr_gregorian_to_jalali') ? mlr_gregorian_to_jalali($request->last_edited_at, 'Y/m/d H:i') : (!empty($request->last_edited_at) ? $request->last_edited_at : '—')],
                ['label' => 'وضعیت تسویه', 'value' => !empty($request->settlement_status) ? $request->settlement_status : '—'],
                ['label' => 'تاریخ تسویه', 'value' => !empty($request->settlement_date) && function_exists('mlr_gregorian_to_jalali') ? mlr_gregorian_to_jalali($request->settlement_date, 'Y/m/d H:i') : (!empty($request->settlement_date) ? $request->settlement_date : '—')],
            ]
        ]
    ];

    if (!$show_guarantee_check_principal && isset($sections['financial']['fields'][9])) {
        unset($sections['financial']['fields'][9]);
    }
    if (!$show_guarantee_check_total && isset($sections['financial']['fields'][10])) {
        unset($sections['financial']['fields'][10]);
    }
    if (isset($sections['financial']['fields']) && is_array($sections['financial']['fields'])) {
        $sections['financial']['fields'] = array_values(array_filter($sections['financial']['fields'], static function ($field) {
            return !empty($field);
        }));
    }

    ob_start();
    ?>
    <div class="mlr-modal-header">
        <h3 class="mlr-modal-title">جزئیات درخواست <span style="color:var(--mlr-primary); margin-right: 5px;">#<?php echo esc_html($request->id); ?></span></h3>
        <span class="mlr-status-badge status-<?php echo sanitize_html_class($request->status); ?>"><?php echo esc_html($request->status); ?></span>
    </div>
    
    <div class="mlr-modal-body" data-document-flow-stage="<?php echo esc_attr($request_document_stage); ?>">
        
        <?php if ($show_defect_documents_box): ?>
            <div class="mlr-reupload-section mlr-defect-documents-box" data-document-flow-stage="defect_documents" style="margin-bottom: 25px; padding: 20px; border: 2px dashed #f39c12; background: #fffdf6; border-radius: 8px;">
                <h4 style="color: #d35400; margin-top: 0; margin-bottom: 10px; display: flex; align-items: center;">
                    <span class="dashicons dashicons-warning" style="margin-left: 5px;"></span> رفع نقص مدارک
                </h4>
                <p style="margin-bottom: 15px; font-size: 0.95em; line-height: 1.6; color: #444;">
                    پرونده شما به دلیل نقص در مدارک بازگردانده شده است. لطفاً بخش "سوابق و پیام‌ها" را مطالعه کنید تا از علت نقص مطلع شوید، سپس مدارک صحیح را بارگذاری کنید.
                    <br><strong>پس از ارسال، وضعیت پرونده به "در حال بررسی" تغییر خواهد کرد.</strong>
                </p>
                
                <form id="<?php echo esc_attr($form_id); ?>" class="mlr-universal-upload-form" data-upload-action="mlr_applicant_upload_additional_docs" method="post" enctype="multipart/form-data">
                    <input type="hidden" name="request_id" id="<?php echo esc_attr($input_req_id); ?>" value="<?php echo esc_attr($request->id); ?>">
                    
                    <div class="mlr-form-group">
                        <label for="<?php echo esc_attr($input_file_id); ?>" style="font-weight: bold; display: block; margin-bottom: 8px; color: #333;">انتخاب مدارک جدید (تصویر یا PDF):</label>
                        <input type="file" id="<?php echo esc_attr($input_file_id); ?>" name="additional_files[]" multiple accept=".jpg,.jpeg,.png,.webp,.pdf" required style="width: 100%; padding: 10px; background: #fff; border: 1px solid #ddd; border-radius: 4px;">
                        <p class="description" style="font-size: 0.85em; color: #666; margin-top: 5px;">می‌توانید چند فایل را همزمان انتخاب کنید.</p>
                    </div>

                    <div class="mlr-form-group" style="margin-top: 15px;">
                        <label for="<?php echo esc_attr($input_note_id); ?>" style="font-weight: bold; display: block; margin-bottom: 8px; color: #333;">توضیحات شما (اختیاری):</label>
                        <textarea id="<?php echo esc_attr($input_note_id); ?>" name="user_note" rows="3" style="width: 100%; border: 1px solid #ddd; border-radius: 4px; padding: 8px; min-height: 80px;" placeholder="توضیحات رفع نقص..."></textarea>
                    </div>

                    <button type="submit" class="button button-primary mlr-submit-docs-btn" style="margin-top: 15px; width: 100%; height: 40px; display:flex; justify-content:center; align-items:center; font-size: 14px;">ارسال مدارک و بررسی مجدد</button>
                    <div class="mlr-upload-status" style="display:none; margin-top: 15px; padding: 10px; border-radius: 4px; text-align: center;"></div>
                </form>
            </div>
        <?php endif; ?>

        <?php // در مرحله تکمیل مدارک، این باکس باید مانند «رفع نقص مدارک» در ابتدای پنجره نمایش داده شود. ?>
        <?php if ($show_completion_documents_box): ?>
            <?php

                // Build the contract through the shared canonical renderer used by digital signing.
                $contract_payload = function_exists('mlr_get_loan_contract_payload') ? mlr_get_loan_contract_payload($request) : null;
                if (is_wp_error($contract_payload) || !is_array($contract_payload)) {
                    $contract_payload = [
                        'settings' => [],
                        'letterhead_url' => '',
                        'footer_url' => '',
                        'repeat_footer_html' => '',
                        'contract_text_raw' => '',
                        'contract_html' => '',
                    ];
                }
                $plan_settings = is_array($contract_payload['settings'] ?? null) ? $contract_payload['settings'] : [];
                $completed_check_files_enabled = function_exists('mlr_completed_check_files_enabled_for_request')
                    ? mlr_completed_check_files_enabled_for_request($request)
                    : true;
                $contract_letterhead_url = (string) ($contract_payload['letterhead_url'] ?? '');
                $contract_footer_url = (string) ($contract_payload['footer_url'] ?? '');
                $contract_repeat_footer_html = (string) ($contract_payload['repeat_footer_html'] ?? '');
                $contract_text_raw = (string) ($contract_payload['contract_text_raw'] ?? '');
                $contract_text_rendered = (string) ($contract_payload['contract_html'] ?? '');
                $contract_signing_mode = function_exists('mlr_get_loan_contract_mode_for_request')
                    ? mlr_get_loan_contract_mode_for_request($request, true)
                    : 'physical';
                $is_digital_contract = ($contract_signing_mode === 'digital');
                $digital_contract_signature = $is_digital_contract && function_exists('mlr_get_loan_contract_signature_for_display')
                    ? mlr_get_loan_contract_signature_for_display($request->id, $request)
                    : ($is_digital_contract && function_exists('mlr_get_loan_contract_signature') ? mlr_get_loan_contract_signature($request->id) : null);
                $digital_contract_signed = is_array($digital_contract_signature);
                $digital_contract_signed_at = $digital_contract_signed && function_exists('mlr_loan_contract_signed_display_date')
                    ? mlr_loan_contract_signed_display_date($digital_contract_signature)
                    : '';
                $digital_contract_url = $is_digital_contract && function_exists('mlr_get_loan_contract_view_url')
                    ? mlr_get_loan_contract_view_url($request->id)
                    : '';
                $digital_signature_certificate = $digital_contract_signed && function_exists('mlr_render_loan_contract_signature_certificate')
                    ? mlr_render_loan_contract_signature_certificate($digital_contract_signature)
                    : '';
                if ($digital_contract_signed && !empty($digital_contract_signature['contract_html'])) {
                    $contract_text_rendered = (string) $digital_contract_signature['contract_html'];
                    if (array_key_exists('letterhead_url', $digital_contract_signature)) {
                        $contract_letterhead_url = (string) $digital_contract_signature['letterhead_url'];
                    }
                    if (array_key_exists('footer_url', $digital_contract_signature)) {
                        $contract_footer_url = (string) $digital_contract_signature['footer_url'];
                    }
                    if (array_key_exists('repeat_footer_html', $digital_contract_signature)) {
                        $contract_repeat_footer_html = (string) $digital_contract_signature['repeat_footer_html'];
                    }
                }
                $contract_plan_name = (string) ($digital_contract_signature['plan_name'] ?? ($contract_payload['plan_name'] ?? ''));
                if (function_exists('mlr_apply_hami_footer_policy')) {
                    $footer_policy = mlr_apply_hami_footer_policy(
                        $contract_text_rendered,
                        $contract_footer_url,
                        $contract_repeat_footer_html,
                        (string) ($digital_contract_signature['signer_name'] ?? $request->full_name ?? ''),
                        $contract_plan_name
                    );
                    $contract_text_rendered = (string) ($footer_policy['contract_html'] ?? $contract_text_rendered);
                    $contract_repeat_footer_html = (string) ($footer_policy['repeat_footer_html'] ?? '');
                }
                $contract_sign_portal = 'applicant';

                $contract_acc_id = 'mlr_contract_acc_' . $uniq;
                $invoice_acc_id  = 'mlr_invoice_acc_' . $uniq;
                $contract_print_id = 'mlr_contract_print_' . $uniq;
                $invoice_print_id  = 'mlr_invoice_print_' . $uniq;
            ?>
            <?php // NOTE: Accordion/print styles are provided by the unified modal stylesheet (assets/css/mlr-modal-unified.css). ?>

            <div class="mlr-reupload-section mlr-completion-documents-box" data-document-flow-stage="<?php echo esc_attr($request_document_stage); ?>" style="margin-bottom: 25px; padding: 25px; border: 2px dashed var(--mlr-primary); background: #eff6ff; border-radius: 16px;">
                <h4 style="color: var(--mlr-primary); margin-top: 0; margin-bottom: 10px; display: flex; align-items: center;">
                    <span class="dashicons dashicons-yes-alt" style="margin-left: 5px;"></span> تکمیل مدارک
                </h4>
                <p style="margin-bottom: 15px; font-size: 0.95em; line-height: 1.8; color: #444;">
                    <?php if ($is_digital_contract): ?>
                        لطفاً متن قرارداد دریافت تسهیلات را کامل مطالعه و با کد تأیید پیامکی امضا کنید؛ سپس <?php echo $completed_check_files_enabled ? 'تصاویر چک و ' : ''; ?>فاکتور امضا/مهرشده را بارگذاری نمایید.
                    <?php else: ?>
                        لطفاً قرارداد و فاکتور را مشاهده و در صورت نیاز چاپ کنید، سپس <?php echo $completed_check_files_enabled ? 'تصاویر چک و سایر ' : ''; ?>مدارک تکمیلی را بارگذاری نمایید.
                    <?php endif; ?>
                </p>

                <div class="mlr-accordion">
                    <button type="button" class="mlr-acc-btn mlr-no-print" data-target="<?php echo esc_attr($contract_acc_id); ?>">
                        <span><?php echo $is_digital_contract ? 'مشاهده قرارداد دریافت تسهیلات' : 'مشاهده و پرینت قرارداد'; ?></span>
                        <span class="dashicons dashicons-arrow-down-alt2"></span>
                    </button>
                    <div id="<?php echo esc_attr($contract_acc_id); ?>" class="mlr-acc-content" style="padding:0;">
                        <div id="<?php echo esc_attr($contract_print_id); ?>" class="mlr-print-area" style="padding:15px;">
                            <style type="text/css" media="print">
                                .mlr-print-layout-table { width: 100%; border-collapse: collapse; page-break-inside: auto; }
                                .mlr-print-layout-table thead { display: table-header-group; }
                                .mlr-print-layout-table tfoot { display: table-footer-group; }
                                .mlr-print-layout-table tr { page-break-inside: auto; }
                                .mlr-print-layout-table td { padding: 0; border: none !important; vertical-align: top; }
                                .mlr-print-header, .mlr-print-footer { text-align: center; width: 100%; }
                                .mlr-print-header img, .mlr-print-footer img { max-width: 100%; height: auto; }
                            </style>
                            <table class="mlr-print-layout-table">
                                <thead>
                                    <tr>
                                        <td>
                                            <div class="mlr-print-header">
                                                <?php if (!empty($contract_letterhead_url)): ?>
                                                    <img src="<?php echo esc_url($contract_letterhead_url); ?>" alt="سربرگ قرارداد" class="mlr-contract-media-image">
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>
                                            <div class="mlr-print-main-content">
                                                <?php if (!empty($contract_text_rendered)): ?>
                                                    <div class="mlr-contract-body<?php echo $digital_contract_signed ? ' mlr-contract-body-signed' : ''; ?>" style="font-size: 12.5px; line-height: 2; color:#111; padding: 10px 0;">
                                                        <?php echo wp_kses_post(mlr_normalize_contract_markup_for_display($contract_text_rendered)); ?>
                                                        <?php if ($digital_signature_certificate !== ''): ?>
                                                            <?php echo wp_kses_post($digital_signature_certificate); ?>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php else: ?>
                                                    <p>برای این طرح، متن قرارداد تنظیم نشده است.</p>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td>
                                            <div class="mlr-print-footer">
                                                <?php if (!empty($contract_repeat_footer_html)): ?>
                                                    <?php echo wp_kses_post($contract_repeat_footer_html); ?>
                                                <?php endif; ?>
                                                <?php if (!empty($contract_footer_url)): ?>
                                                    <img src="<?php echo esc_url($contract_footer_url); ?>" alt="پابرگ قرارداد" class="mlr-contract-media-image">
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                            <?php
                                $plan_files = [];
                                if (isset($plan_settings['plan_downloadable_files']) && is_array($plan_settings['plan_downloadable_files'])) {
                                    $plan_files = array_values(array_filter($plan_settings['plan_downloadable_files']));
                                }
                            ?>
                            <?php if (!empty($plan_files)): ?>
                                <div class="mlr-no-print" style="margin-top: 12px;">
                                    <div style="font-weight:600; margin-bottom:8px;">فایل‌های پیوست قابل دانلود</div>
                                    <div style="display:flex; flex-wrap:wrap; gap:8px;">
                                        <?php foreach ($plan_files as $file_url): ?>
                                            <a class="button button-secondary" href="<?php echo esc_url($file_url); ?>" target="_blank" rel="noopener noreferrer">دانلود فایل</a>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                        </div>

                        <?php if (!$is_digital_contract): ?>
                            <div class="mlr-print-actions mlr-no-print">
                                <button type="button" class="mlr-btn mlr-btn-secondary" data-print="<?php echo esc_attr($contract_print_id); ?>">چاپ قرارداد</button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($is_digital_contract): ?>
                    <div class="mlr-loan-contract-sign-box"
                         data-request-id="<?php echo esc_attr($request->id); ?>"
                         data-portal-context="<?php echo esc_attr($contract_sign_portal); ?>"
                         data-signed="<?php echo $digital_contract_signed ? '1' : '0'; ?>"
                         style="margin:16px 0; padding:18px; background:#fff; border:1px solid #bfdbfe; border-radius:14px; box-shadow:0 4px 16px rgba(15,23,42,.04);">
                        <?php if ($digital_contract_signed): ?>
                            <div class="mlr-loan-contract-sign-success" style="display:flex; gap:10px; align-items:flex-start; color:#166534; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:10px; padding:12px 14px;">
                                <span class="dashicons dashicons-yes-alt" style="margin-top:2px;"></span>
                                <div>
                                    <strong style="display:block; margin-bottom:4px;">قرارداد دریافت تسهیلات به‌صورت دیجیتال تأیید و امضا شده است.</strong>
                                    <?php if ($digital_contract_signed_at): ?>
                                        <small style="display:block; margin-bottom:8px;">این قرارداد در تاریخ و ساعت <?php echo esc_html($digital_contract_signed_at); ?> امضا و تأیید شده است.</small>
                                    <?php endif; ?>
                                    <?php if ($digital_contract_url): ?>
                                        <a class="mlr-btn mlr-btn-secondary mlr-loan-contract-signed-link" href="<?php echo esc_url($digital_contract_url); ?>" target="_blank" rel="noopener noreferrer" style="display:inline-flex; margin-top:4px;">مشاهده قرارداد امضاشده</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="mlr-loan-contract-sign-pending">
                                <label style="display:flex; gap:8px; align-items:flex-start; cursor:pointer; line-height:1.8;">
                                    <input type="checkbox" class="mlr-loan-contract-consent" value="1" style="margin-top:6px;">
                                    <span>متن کامل قرارداد دریافت تسهیلات فوق را مطالعه کردم و مفاد آن را می‌پذیرم.</span>
                                </label>
                                <div style="margin-top:12px; display:flex; flex-wrap:wrap; gap:10px; align-items:center;">
                                    <button type="button" class="mlr-btn mlr-btn-primary mlr-loan-contract-send-otp" disabled>ارسال کد تأیید</button>
                                    <small style="color:#64748b;">کد تأیید ۶ رقمی به شماره موبایل ثبت‌شده متقاضی ارسال می‌شود.</small>
                                </div>
                                <div class="mlr-loan-contract-otp-panel" style="display:none; margin-top:14px;">
                                    <div style="display:flex; flex-wrap:wrap; gap:10px; align-items:center;">
                                        <input type="text" class="mlr-glass-input mlr-loan-contract-otp-input" inputmode="numeric" pattern="[0-9۰-۹٠-٩]{6}" maxlength="6" autocomplete="one-time-code" placeholder="کد تأیید ۶ رقمی" style="max-width:180px;">
                                        <button type="button" class="mlr-btn mlr-btn-primary mlr-loan-contract-verify-otp">تأیید و امضای قرارداد</button>
                                        <span class="mlr-loan-contract-otp-timer" style="font-size:12px; color:#64748b;"></span>
                                    </div>
                                </div>
                                <div class="mlr-loan-contract-otp-message" style="display:none; margin-top:10px; padding:9px 11px; border-radius:8px; font-size:13px;"></div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <div class="mlr-glass-accordion">
                    <button type="button" class="mlr-glass-acc-btn mlr-no-print" data-target="<?php echo esc_attr($invoice_acc_id); ?>">
                        <span>مشاهده و پرینت فاکتور</span>
                        <span class="dashicons dashicons-arrow-down-alt2"></span>
                    </button>
                    <div id="<?php echo esc_attr($invoice_acc_id); ?>" class="mlr-glass-acc-content">
                        <div id="<?php echo esc_attr($invoice_print_id); ?>" class="mlr-print-area">
                            <?php echo do_shortcode('[mlr_invoice id="' . intval($request->id) . '"]'); ?>
                        </div>
                        <div class="mlr-print-actions mlr-no-print">
                            <button type="button" class="mlr-btn mlr-btn-secondary" data-print="<?php echo esc_attr($invoice_print_id); ?>">چاپ فاکتور</button>
                        </div>
                    </div>
                </div>

                <?php
                    $pending_for_complete = is_array($pending_docs_attachments) ? $pending_docs_attachments : [];
                    $has_pending_for_complete = !empty($pending_for_complete);
                ?>
                <?php if ($has_pending_for_complete): ?>
                <div class="mlr-pending-docs-for-acceptor" style="margin-top: 16px; padding: 14px 16px; background: rgba(79, 70, 229, 0.06); border: 1px solid rgba(79, 70, 229, 0.2); border-radius: 12px;">
                    <h5 style="margin: 0 0 12px; font-size: 14px; font-weight: 700; color: var(--mlr-primary, #4f46e5); display: flex; align-items: center; gap: 6px;">
                        <span class="dashicons dashicons-paperclip" style="font-size: 18px; width: 18px; height: 18px;"></span>
                        فایل‌های پیوست‌شده از طرف کارشناس (برای تکمیل مدارک)
                    </h5>
                    <p style="margin: 0 0 12px; font-size: 12px; color: #64748b; line-height: 1.5;">این ها فایل ها و تصاویر تکمیلی توسط کارشناس برای این پرونده ارسال شده. موارد را پرینت و مهر/اثرانگشت و امضا کنید و سپس در فیلد سایر مدارک بارگزاری نمائید.</p>
                    <div class="mlr-pending-docs-for-acceptor-list" style="display: flex; flex-wrap: wrap; gap: 10px;">
                        <?php foreach ($pending_for_complete as $pkey => $pitem): ?>
                            <?php if (is_array($pitem) && !empty($pitem['url']) && strpos($pitem['url'], 'http') === 0): ?>
                                <a href="<?php echo esc_url($pitem['url']); ?>" target="_blank" rel="noopener noreferrer" class="mlr-view-file-btn" style="display: inline-flex; align-items: center; gap: 6px; padding: 8px 14px; border-radius: 8px; background: #fff; border: 1px solid #e2e8f0; color: #4f46e5; font-size: 13px; font-weight: 600; text-decoration: none; transition: all 0.2s;">
                                    <span class="dashicons dashicons-download" style="font-size: 16px; width: 16px; height: 16px;"></span>
                                    <?php echo esc_html(!empty($pitem['label']) ? $pitem['label'] : $pkey); ?>
                                </a>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php
                    $completed_docs_form_id = 'mlr_completed_docs_form_' . $uniq;
                ?>
                <form id="<?php echo esc_attr($completed_docs_form_id); ?>" class="mlr-completed-docs-form" data-contract-mode="<?php echo esc_attr($contract_signing_mode); ?>" data-contract-signed="<?php echo $digital_contract_signed ? '1' : '0'; ?>" data-check-files-enabled="<?php echo $completed_check_files_enabled ? '1' : '0'; ?>" data-upload-action="mlr_applicant_submit_completed_docs" enctype="multipart/form-data" style="margin-top: 15px;">
                    <input type="hidden" name="action" value="mlr_applicant_submit_completed_docs">
                    <input type="hidden" name="request_id" value="<?php echo esc_attr($request->id); ?>">
                    <input type="hidden" name="document_flow_state" value="<?php echo esc_attr($request_document_stage); ?>">
                    <div class="mlr-grid-2">
                        <?php if ($completed_check_files_enabled): ?>
                            <div class="mlr-form-group">
                                <strong>تصاویر چک (ضروری)</strong>
                                <input type="file" name="completed_check_files[]" multiple accept=".jpg,.jpeg,.png,.webp,.pdf" class="mlr-glass-input" required>
                                <small class="description">فرمت‌های مجاز: JPG، PNG، WEBP و PDF؛ حداکثر ۱۰ مگابایت برای هر فایل.</small>
                            </div>
                        <?php endif; ?>

                        <?php if (!$is_digital_contract): ?>
                            <div class="mlr-form-group">
                                <strong>قرارداد امضا‌شده</strong>
                                <input type="file" name="signed_contract" accept=".jpg,.jpeg,.png,.webp,.pdf" class="mlr-glass-input" required>
                            </div>
                        <?php endif; ?>
                        <div class="mlr-form-group">
                            <strong>فاکتور امضا/مهر‌شده</strong>
                            <input type="file" name="signed_invoice" accept=".jpg,.jpeg,.png,.webp,.pdf" class="mlr-glass-input" required>
                        </div>
                        <div class="mlr-form-group">
                            <strong>سایر مدارک (اختیاری)</strong>
                            <input type="file" name="other_completed_files[]" multiple accept=".jpg,.jpeg,.png,.webp,.pdf" class="mlr-glass-input">
                        </div>
                    </div>

                    <?php if ($is_digital_contract && !$digital_contract_signed): ?>
                        <div class="mlr-contract-required-note" style="margin-top:12px; padding:10px 12px; border-radius:9px; background:#fff7ed; border:1px solid #fed7aa; color:#9a3412; font-size:13px;">
                            برای ارسال مدارک، ابتدا قرارداد دریافت تسهیلات را با کد تأیید امضا کنید.
                        </div>
                    <?php endif; ?>
                    <div style="margin-top: 15px; display:flex; gap:10px; align-items:center;">
                        <button type="submit" class="mlr-btn mlr-btn-primary mlr-completed-docs-submit" <?php disabled($is_digital_contract && !$digital_contract_signed); ?>>ارسال مدارک</button>
                        <span class="mlr-upload-status" style="display:none; font-size:0.9em; padding:10px; border-radius:8px;"></span>
                    </div>
                </form>

                <?php // NOTE: Do not include <script> tags in AJAX HTML. Interactions are handled in mlr-user-requests.js. ?>
            </div>
        <?php endif; ?>


        <?php // بخش گفتگو برای همه وضعیت‌ها در بالای اطلاعات پرونده نمایش داده می‌شود. ?>
<?php // بخش گفتگو برای همه وضعیت‌ها از جمله تسویه شده نمایش داده می‌شود و امکان ارسال پیام فعال است. ?>
<div class="mlr-glass-card mlr-modal-messages-section">
    <h4 class="mlr-card-title"><span class="dashicons dashicons-email"></span>مکالمات پرونده</h4>
    <div class="mlr-messages-history" id="mlr-messages-history-container" data-request-id="<?php echo esc_attr($request->id); ?>">
        <?php echo function_exists('mlr_render_request_messages_html') ? mlr_render_request_messages_html($request_messages) : ''; ?>
    </div>
    <div class="mlr-add-message-form" style="margin-top:15px;">
        <textarea class="mlr-glass-input mlr-new-message-text" rows="3" placeholder="پیام خود را اینجا بنویسید..."></textarea>

        <div class="mlr-attachment-controls">
            <label for="mlr-message-attachment-input-front" class="mlr-attachment-btn">
                <span class="dashicons dashicons-paperclip"></span> پیوست فایل
            </label>
            <input type="file" id="mlr-message-attachment-input-front" multiple style="display:none;">
            <div id="mlr-attachment-previews-front"></div>
        </div>

        <div class="mlr-conversation-actions">
            <button type="button" class="mlr-btn mlr-btn-primary mlr-send-message-btn" data-request-id="<?php echo esc_attr($request->id); ?>" data-nonce="<?php echo esc_attr(wp_create_nonce('mlr_frontend_nonce')); ?>">
                <span class="dashicons dashicons-send"></span> ارسال پیام
            </button>
            <button type="button" class="mlr-btn mlr-btn-secondary mlr-refresh-messages-btn" data-request-id="<?php echo esc_attr($request->id); ?>" data-nonce="<?php echo esc_attr(wp_create_nonce('mlr_frontend_nonce')); ?>">
                <span class="dashicons dashicons-update"></span> به‌روزرسانی
            </button>
        </div>
    </div>
</div>

<!-- اطلاعات هویتی، شغلی، مالی و مدارک؛ در مرحله تکمیل مدارک، باکس اقدام در ابتدای پنجره قرار می‌گیرد. -->
        <div class="mlr-data-grid">
            <?php foreach ($sections as $key => $section): ?>
                <div class="mlr-glass-card">
                    <h5 class="mlr-card-title"><span class="dashicons <?php echo esc_attr($section['icon']); ?>"></span> <?php echo esc_html($section['title']); ?></h5>
                    <?php foreach ($section['fields'] as $field): ?>
                        <?php if (empty($field)) continue; ?>
                        <?php if ($is_expert_dashboard) { $label_to_check = $field['label'] ?? ''; $labels_to_hide = ['اعتبار خرید', 'هزینه خدمات', 'جمع هزینه عملیات + ارزش افزوده']; if (in_array($label_to_check, $labels_to_hide, true)) continue; } ?>
                        <div class="mlr-data-row <?php echo !empty($field['wide']) ? 'mlr-row-wide' : ''; ?>">
                            <span class="mlr-data-label"><?php echo esc_html($field['label']); ?></span>
                            <span class="mlr-data-value <?php echo !empty($field['is_status']) ? 'mlr-status-text' : ''; ?>"><?php echo esc_html($field['value']); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="mlr-glass-card" style="margin-top: 20px;">
            <h4 class="mlr-card-title"><span class="dashicons dashicons-info"></span> اطلاعات تکمیلی سفارش</h4>
            <div class="mlr-info-grid">
                <div class="mlr-data-row"><span class="mlr-data-label">نام کالا/خدمت</span><strong class="mlr-data-value"><?php echo !empty($request->product_name) ? esc_html($request->product_name) : '—'; ?></strong></div>
            </div>
            <?php if (!empty($request->description)): ?><div style="margin-top:12px;"><div style="font-weight:800; color: var(--mlr-text-dark); margin-bottom: 5px;">توضیحات متقاضی:</div><div class="mlr-long-text" style="background: #f9f9f9; padding: 10px; border-radius: 4px; border: 1px solid #eee;"><?php echo nl2br(esc_html($request->description)); ?></div></div><?php endif; ?>
        </div>
        <?php if (!empty($extra_files) && is_array($extra_files)): ?>
        <div class="mlr-glass-card">
            <h4 class="mlr-card-title"><span class="dashicons dashicons-media-document"></span> فایل‌های سیستمی و قرارداد</h4>
            <div class="mlr-file-grid">
                <?php foreach ($extra_files as $fkey => $fval): if (empty($fval)) continue; $label = isset($extra_file_labels[$fkey]) ? $extra_file_labels[$fkey] : (function_exists('mlr_get_field_label') ? mlr_get_field_label($fkey) : ucfirst(str_replace('_',' ', $fkey))); ?>
                <div class="mlr-file-item">
                    <div class="mlr-file-label"><?php echo esc_html($label); ?></div>
                    <div class="mlr-file-actions">
                        <?php if (is_array($fval)): foreach ($fval as $idx => $url): if (empty($url)) continue; ?><a class="mlr-view-file-btn" href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener noreferrer" data-no-lightbox="true" data-elementor-open-lightbox="no">مشاهده <?php echo intval($idx)+1; ?></a><?php endforeach; else: ?><a class="mlr-view-file-btn" href="<?php echo esc_url($fval); ?>" target="_blank" rel="noopener noreferrer" data-no-lightbox="true" data-elementor-open-lightbox="no">مشاهده فایل</a><?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        <?php $grouped_docs_top = function_exists('mlr_group_uploaded_documents_by_category') ? mlr_group_uploaded_documents_by_category($uploaded_documents, $all_doc_labels) : []; $cat_labels_top = function_exists('mlr_get_document_category_labels') ? mlr_get_document_category_labels() : []; $default_cat_top = !empty($grouped_docs_top) && is_array($grouped_docs_top) ? (array_keys($grouped_docs_top)[0] ?? '') : ''; ?>
        <?php if (!empty($grouped_docs_top)): ?>
        <div class="mlr-glass-card mlr-docs-card" data-default-cat="<?php echo esc_attr($default_cat_top); ?>">
            <h4 class="mlr-card-title"><span class="dashicons dashicons-paperclip"></span> مدارک بارگذاری شده (هویتی، شغلی، مالی و سایر)</h4>
            <div class="mlr-docs-categories" role="tablist" aria-label="دسته‌بندی مدارک"><?php foreach ($grouped_docs_top as $cat => $items): $lbl = isset($cat_labels_top[$cat]) ? $cat_labels_top[$cat] : $cat; ?>
                <button type="button" class="mlr-btn mlr-btn-secondary mlr-doc-cat-btn" data-cat="<?php echo esc_attr($cat); ?>"><?php echo esc_html($lbl); ?></button><?php endforeach; ?>
            </div>
            <div class="mlr-docs-sections"><?php foreach ($grouped_docs_top as $cat => $items): ?>
                <div class="mlr-doc-cat-section" data-cat="<?php echo esc_attr($cat); ?>" style="display:none;"><div class="mlr-docs-list"><?php foreach ($items as $doc): ?>
                    <div class="mlr-doc-item"><strong><?php echo esc_html($doc['label']); ?>:</strong> <?php foreach ($doc['urls'] as $i => $url): ?><a href="<?php echo esc_url($url); ?>" target="_blank" class="mlr-view-file-btn" rel="noopener noreferrer" data-no-lightbox="true" data-elementor-open-lightbox="no"><?php echo (count($doc['urls']) > 1) ? ('مشاهده ' . intval($i + 1)) : 'مشاهده فایل'; ?></a><?php endforeach; ?></div><?php endforeach; ?></div></div><?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php 
        // --- Guarantor Section ---
        // بعد از ذخیره فقط نمایش فقط‌خواندنی (مثل بقیه بخش‌های پرونده). قبل از ذخیره فرم ویرایش.
        $has_guarantor_data = !empty($guarantor_info);
        $guarantor_text_labels = [
                            'full_name' => 'نام و نام خانوادگی',
                            'national_code' => 'کد ملی',
                            'father_name' => 'نام پدر',
                            'id_number' => 'شماره شناسنامه',
                            'issued_from' => 'صادره از',
                            'birth_date' => 'تاریخ تولد',
                            'mobile' => 'شماره موبایل',
                            'phone' => 'تلفن ثابت',
                            'job' => 'شغل',
                            'postal_code' => 'کد پستی',
                            'address' => 'آدرس سکونت',
                        ];
        $guarantor_doc_labels = [
                            'national_card' => 'کارت ملی (پشت و رو)',
                            'id_card' => 'شناسنامه (تمام صفحات)',
                            'payslip' => 'فیش حقوقی/ گواهی اشتغال/ جواز',
                            'sana' => 'گواهی ثبت ثنا',
                            'check' => 'تصویر چک صیادی',
                            'residence_deed' => 'سند/اجاره‌نامه سکونت',
                            'work_deed' => 'سند/اجاره‌نامه محل کار',
                            'account_statement' => 'گردش حساب ۳ ماهه',
                            'extra_docs' => 'مدارک تکمیلی',
                        ];
        $current_blog_id = function_exists('get_current_blog_id') ? get_current_blog_id() : 1;
        $guarantor_table_prefix = preg_replace('/[^a-z0-9_]/i', '', $wpdb->prefix);
        ?>
            <div class="mlr-glass-accordion" style="margin-top: 20px;">
                <button type="button" class="mlr-glass-acc-btn" data-target="<?php echo esc_attr($guarantor_acc_id); ?>">
                    <span><span class="dashicons dashicons-groups"></span> اطلاعات و مدارک ضامن</span>
                    <span class="dashicons dashicons-arrow-down-alt2"></span>
                </button>
                <div id="<?php echo esc_attr($guarantor_acc_id); ?>" class="mlr-glass-acc-content">
        <?php if ($has_guarantor_data): ?>
                    <!-- نمایش فقط‌خواندنی بعد از ذخیره؛ بدون امکان ویرایش یا آپلود -->
                    <div class="mlr-glass-card" style="margin:0;">
                        <div class="mlr-data-grid">
                            <?php foreach ($guarantor_text_labels as $k => $l): if (isset($guarantor_info[$k]) && (string)$guarantor_info[$k] !== ''): ?>
                                <div class="mlr-data-row"><span class="mlr-data-label"><?php echo esc_html($l); ?></span><span class="mlr-data-value"><?php echo esc_html($guarantor_info[$k]); ?></span></div>
                            <?php endif; endforeach; ?>
                        </div>
                        <div style="margin-top:12px; padding-top:10px; border-top:1px solid #eee;">
                            <span class="mlr-data-label" style="display:block; margin-bottom:6px;">مدارک ضامن:</span>
                            <?php foreach ($guarantor_doc_labels as $gk => $gl): if (!empty($guarantor_info[$gk]) && is_string($guarantor_info[$gk]) && strpos($guarantor_info[$gk], 'http') === 0): ?>
                                <a href="<?php echo esc_url($guarantor_info[$gk]); ?>" target="_blank" rel="noopener" class="mlr-view-file-btn" style="margin:2px 8px 2px 0;"><?php echo esc_html($gl); ?></a>
                            <?php endif; endforeach; ?>
                        </div>
                    </div>
        <?php else: ?>
                    <?php
                    $guarantor_save_url = add_query_arg([
                        'action' => 'mlr_save_guarantor_info',
                        'request_id' => (int) $request->id,
                        'blog_id' => $current_blog_id,
                        'db_prefix' => $guarantor_table_prefix
                    ], admin_url('admin-ajax.php'));
                    ?>
                    <form id="mlr-guarantor-form" class="mlr-guarantor-form-frontend" enctype="multipart/form-data" data-portal-context="applicant" data-request-id="<?php echo esc_attr($request->id); ?>" data-blog-id="<?php echo esc_attr($current_blog_id); ?>" data-db-prefix="<?php echo esc_attr($guarantor_table_prefix); ?>" data-details-nonce="<?php echo esc_attr(wp_create_nonce('mlr_frontend_nonce')); ?>" data-save-url="<?php echo esc_url($guarantor_save_url); ?>">
                        <input type="hidden" name="action" value="mlr_save_guarantor_info">
                        <input type="hidden" name="request_id" value="<?php echo esc_attr($request->id); ?>">
                        <input type="hidden" name="blog_id" value="<?php echo esc_attr($current_blog_id); ?>">
                        <input type="hidden" name="db_prefix" value="<?php echo esc_attr($guarantor_table_prefix); ?>">
                        <input type="hidden" name="nonce" value="<?php echo esc_attr(wp_create_nonce('mlr_frontend_nonce')); ?>">
                        <div class="mlr-grid-2">
                            <div class="mlr-form-group"><label>نام و نام خانوادگی</label><input type="text" name="full_name" class="mlr-glass-input" value=""></div>
                            <div class="mlr-form-group"><label>کد ملی</label><input type="text" name="national_code" class="mlr-glass-input" value=""></div>
                            <div class="mlr-form-group"><label>نام پدر</label><input type="text" name="father_name" class="mlr-glass-input" value=""></div>
                            <div class="mlr-form-group"><label>شماره شناسنامه</label><input type="text" name="id_number" class="mlr-glass-input" value=""></div>
                            <div class="mlr-form-group"><label>صادره از</label><input type="text" name="issued_from" class="mlr-glass-input" value=""></div>
                            <div class="mlr-form-group"><label>تاریخ تولد</label><input type="text" name="birth_date" class="mlr-glass-input jalali-datepicker" value=""></div>
                            <div class="mlr-form-group"><label>شماره موبایل</label><input type="text" name="mobile" class="mlr-glass-input" value=""></div>
                            <div class="mlr-form-group"><label>تلفن ثابت</label><input type="text" name="phone" class="mlr-glass-input" value=""></div>
                            <div class="mlr-form-group"><label>شغل</label><input type="text" name="job" class="mlr-glass-input" value=""></div>
                            <div class="mlr-form-group"><label>کد پستی</label><input type="text" name="postal_code" class="mlr-glass-input" value=""></div>
                            <div class="mlr-form-group" style="grid-column: 1 / -1;"><label>آدرس سکونت</label><textarea name="address" class="mlr-glass-input" rows="2"></textarea></div>
                        </div>
                        <h5 style="margin: 15px 0 10px; border-bottom: 1px solid #eee; padding-bottom: 5px;">مدارک ضامن</h5>
                        <div class="mlr-grid-2">
                            <?php foreach ($guarantor_doc_labels as $key => $label): ?>
                                <div class="mlr-form-group">
                                    <label><?php echo esc_html($label); ?></label>
                                    <input type="file" name="<?php echo esc_attr($key); ?>" class="mlr-glass-input" accept=".jpg,.jpeg,.png,.webp,.pdf,image/jpeg,image/png,image/webp,application/pdf">
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div style="margin-top: 15px; display:flex; gap:10px; align-items:center;">
                            <button type="submit" class="mlr-btn mlr-btn-primary">ذخیره اطلاعات ضامن</button>
                            <span class="mlr-guarantor-msg" style="font-size:0.9em;"></span>
                        </div>
                    </form>
        <?php endif; ?>
                </div>
            </div>

            <!-- ضامن دوم (اختیاری) -->
            <?php
            $has_guarantor_2_data = !empty($guarantor_info_2);
            ?>
            <div class="mlr-glass-accordion" style="margin-top: 20px;">
                <button type="button" class="mlr-glass-acc-btn" data-target="<?php echo esc_attr($guarantor_acc_id_2); ?>">
                    <span><span class="dashicons dashicons-groups"></span> اطلاعات و مدارک ضامن دوم (اختیاری)</span>
                    <span class="dashicons dashicons-arrow-down-alt2"></span>
                </button>
                <div id="<?php echo esc_attr($guarantor_acc_id_2); ?>" class="mlr-glass-acc-content">
            <?php if ($has_guarantor_2_data): ?>
                    <div class="mlr-glass-card" style="margin:0;">
                        <div class="mlr-data-grid">
                            <?php foreach ($guarantor_text_labels as $k => $l): if (isset($guarantor_info_2[$k]) && (string)$guarantor_info_2[$k] !== ''): ?>
                                <div class="mlr-data-row"><span class="mlr-data-label"><?php echo esc_html($l); ?></span><span class="mlr-data-value"><?php echo esc_html($guarantor_info_2[$k]); ?></span></div>
                            <?php endif; endforeach; ?>
                        </div>
                        <div style="margin-top:12px; padding-top:10px; border-top:1px solid #eee;">
                            <span class="mlr-data-label" style="display:block; margin-bottom:6px;">مدارک ضامن دوم:</span>
                            <?php foreach ($guarantor_doc_labels as $gk => $gl): if (!empty($guarantor_info_2[$gk]) && is_string($guarantor_info_2[$gk]) && strpos($guarantor_info_2[$gk], 'http') === 0): ?>
                                <a href="<?php echo esc_url($guarantor_info_2[$gk]); ?>" target="_blank" rel="noopener" class="mlr-view-file-btn" style="margin:2px 8px 2px 0;"><?php echo esc_html($gl); ?></a>
                            <?php endif; endforeach; ?>
                        </div>
                    </div>
            <?php else: ?>
                    <?php
                    $guarantor_2_save_url = add_query_arg([
                        'action' => 'mlr_save_guarantor_info',
                        'request_id' => (int) $request->id,
                        'blog_id' => $current_blog_id,
                        'db_prefix' => $guarantor_table_prefix
                    ], admin_url('admin-ajax.php'));
                    ?>
                    <form id="mlr-guarantor-form-2" class="mlr-guarantor-form-frontend" enctype="multipart/form-data" data-portal-context="applicant" data-request-id="<?php echo esc_attr($request->id); ?>" data-blog-id="<?php echo esc_attr($current_blog_id); ?>" data-db-prefix="<?php echo esc_attr($guarantor_table_prefix); ?>" data-details-nonce="<?php echo esc_attr(wp_create_nonce('mlr_frontend_nonce')); ?>" data-save-url="<?php echo esc_url($guarantor_2_save_url); ?>">
                        <input type="hidden" name="action" value="mlr_save_guarantor_info">
                        <input type="hidden" name="guarantor_index" value="2">
                        <input type="hidden" name="request_id" value="<?php echo esc_attr($request->id); ?>">
                        <input type="hidden" name="blog_id" value="<?php echo esc_attr($current_blog_id); ?>">
                        <input type="hidden" name="db_prefix" value="<?php echo esc_attr($guarantor_table_prefix); ?>">
                        <input type="hidden" name="nonce" value="<?php echo esc_attr(wp_create_nonce('mlr_frontend_nonce')); ?>">
                        <div class="mlr-grid-2">
                            <div class="mlr-form-group"><label>نام و نام خانوادگی</label><input type="text" name="full_name" class="mlr-glass-input" value=""></div>
                            <div class="mlr-form-group"><label>کد ملی</label><input type="text" name="national_code" class="mlr-glass-input" value=""></div>
                            <div class="mlr-form-group"><label>نام پدر</label><input type="text" name="father_name" class="mlr-glass-input" value=""></div>
                            <div class="mlr-form-group"><label>شماره شناسنامه</label><input type="text" name="id_number" class="mlr-glass-input" value=""></div>
                            <div class="mlr-form-group"><label>صادره از</label><input type="text" name="issued_from" class="mlr-glass-input" value=""></div>
                            <div class="mlr-form-group"><label>تاریخ تولد</label><input type="text" name="birth_date" class="mlr-glass-input jalali-datepicker" value=""></div>
                            <div class="mlr-form-group"><label>شماره موبایل</label><input type="text" name="mobile" class="mlr-glass-input" value=""></div>
                            <div class="mlr-form-group"><label>تلفن ثابت</label><input type="text" name="phone" class="mlr-glass-input" value=""></div>
                            <div class="mlr-form-group"><label>شغل</label><input type="text" name="job" class="mlr-glass-input" value=""></div>
                            <div class="mlr-form-group"><label>کد پستی</label><input type="text" name="postal_code" class="mlr-glass-input" value=""></div>
                            <div class="mlr-form-group" style="grid-column: 1 / -1;"><label>آدرس سکونت</label><textarea name="address" class="mlr-glass-input" rows="2"></textarea></div>
                        </div>
                        <h5 style="margin: 15px 0 10px; border-bottom: 1px solid #eee; padding-bottom: 5px;">مدارک ضامن دوم</h5>
                        <div class="mlr-grid-2">
                            <?php foreach ($guarantor_doc_labels as $key => $label): ?>
                                <div class="mlr-form-group">
                                    <label><?php echo esc_html($label); ?></label>
                                    <input type="file" name="<?php echo esc_attr($key); ?>" class="mlr-glass-input" accept=".jpg,.jpeg,.png,.webp,.pdf,image/jpeg,image/png,image/webp,application/pdf">
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div style="margin-top: 15px; display:flex; gap:10px; align-items:center;">
                            <button type="submit" class="mlr-btn mlr-btn-primary">ذخیره اطلاعات ضامن دوم</button>
                            <span class="mlr-guarantor-msg" style="font-size:0.9em;"></span>
                        </div>
                    </form>
            <?php endif; ?>
                </div>
            </div>

        <?php if (!empty($comments)): ?>
        <div class="mlr-glass-card">
            <h4 class="mlr-card-title"><span class="dashicons dashicons-format-chat"></span> سوابق و پیام‌ها (یادداشت‌های کارشناس)</h4>
            <div class="mlr-comments-list">
                <?php foreach($comments as $cm): ?>
                    <div class="mlr-comment">
                        <div style="font-size: 0.85em; color: #777; margin-bottom: 5px;">
                            <span style="font-weight: bold; color: #333;"><?php echo esc_html($cm['display_name']); ?></span> - 
                            <?php echo (function_exists('mlr_gregorian_to_jalali') ? mlr_gregorian_to_jalali($cm['timestamp'], 'Y/m/d H:i') : $cm['timestamp']); ?>
                        </div>
                        <p style="margin: 0; line-height: 1.5;"><?php echo nl2br(esc_html($cm['text'])); ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if ($is_bank_expert && $request->assigned_bank_user_id == $user_id): ?>
             <div class="mlr-glass-card mlr-action-box">
                 <h4 class="mlr-card-title text-primary">بررسی و تغییر وضعیت پرونده</h4>
                 <?php 
                 $action_form_id = 'mlr_expert_action_' . $uniq;
                 $status_id = 'status_' . $uniq;
                 $expert_comment_id = 'expert_comment_' . $uniq;
                 ?>
                 <form id="<?php echo esc_attr($action_form_id); ?>" class="mlr-bank-expert-action-form">
                     <input type="hidden" name="request_id" value="<?php echo esc_attr($request->id); ?>">
                     <div class="mlr-form-group">
                         <label for="<?php echo esc_attr($status_id); ?>">تغییر وضعیت به:</label>
                         <select name="new_status" id="<?php echo esc_attr($status_id); ?>" class="mlr-glass-input">
                            <?php 
                            $request_status_norm = function_exists('mlr_normalize_status') ? mlr_normalize_status($request->status) : $request->status;
                            $opts = ($request_status_norm == 'تأیید کارشناس')
                                ? ['تکمیل مدارک'=>'ارسال برای تکمیل مدارک','نقص مدارک'=>'اعلام نقص مدارک','رد شده'=>'رد درخواست'] 
                                : ['اعطا شده'=>'تأیید نهایی و اعطا شده','رد شده'=>'رد درخواست','تکمیل مدارک'=>'بازگشت به تکمیل مدارک'];
                            foreach($opts as $v => $l): echo "<option value='$v'>$l</option>"; endforeach; 
                            ?>
                         </select>
                     </div>
                     <div class="mlr-form-group">
                         <label for="<?php echo esc_attr($expert_comment_id); ?>">توضیحات (اختیاری):</label>
                         <textarea name="comment_text" id="<?php echo esc_attr($expert_comment_id); ?>" rows="3" class="mlr-glass-input" placeholder="توضیحاتی برای کاربر یا سیستم..."></textarea>
                     </div>
                     <div class="mlr-action-message" style="display:none; margin-bottom:10px;"></div>
                     <button type="submit" class="mlr-btn mlr-btn-primary" style="width:100%;">ثبت بررسی</button>
                 </form>
             </div>
        <?php endif; ?>


        <?php if (isset($form_data['order_items'])): 
             $v = $form_data['order_items'];
             $items = is_string($v) ? json_decode($v, true) : $v;
        ?>
            <div class="mlr-glass-card" style="margin-top: 20px;">
                <h5 class="mlr-card-title"><span class="dashicons dashicons-cart"></span> اقلام سفارش (فرم)</h5>
                <?php if (is_array($items)): ?>
                    <ul class="mlr-order-items-list">
                    <?php foreach ($items as $item): 
                        if (is_array($item)) {
                            $i_name = isset($item['name']) ? $item['name'] : '';
                            $i_qty = isset($item['qty']) ? $item['qty'] : 1;
                            $i_price = isset($item['price']) ? $item['price'] : 0;
                            echo '<li>' . sprintf('<strong>%s</strong> <span class="mlr-item-meta">(تعداد: %s | قیمت: %s ریال)</span>', esc_html($i_name), esc_html($i_qty), number_format((float)$i_price)) . '</li>';
                        } else {
                            echo '<li>' . esc_html($item) . '</li>';
                        }
                    endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p><?php echo esc_html((string)$v); ?></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

<?php if ($credit_report): ?>
    <?php
        // Helper for formatting
        $fmt = function($n) { return number_format((float)$n); };
        
        // Extract Data
        $scoreObj = $credit_report['score'] ?? [];
        $reportObj = $credit_report['report'] ?? [];
        
        $score = $scoreObj['score'] ?? 0;
        $risk = $scoreObj['risk'] ?? 'نامشخص';
        $desc = $scoreObj['description'] ?? '';
        
        // اصلاح محدوده امتیاز برای نمودار
        $minScore = $scoreObj['minLimit'] ?? 0;
        $maxScore = $scoreObj['maxLimit'] ?? 1000;
        if ($maxScore < $score) $maxScore = 1000;
        
        $percent = ($maxScore > $minScore) ? (($score - $minScore) / ($maxScore - $minScore)) * 100 : 0;
        $percent = max(0, min(100, $percent));
        
        // Risk Color
        $riskClass = 'mlr-risk-unknown';
        if (strpos($risk, 'کم') !== false || strpos($risk, 'Low') !== false || in_array($risk, ['A1','A2','A3'])) $riskClass = 'mlr-risk-low';
        elseif (strpos($risk, 'متوسط') !== false || strpos($risk, 'Medium') !== false || in_array($risk, ['B1','B2','B3'])) $riskClass = 'mlr-risk-medium';
        else $riskClass = 'mlr-risk-high';
        
        // Summary
        $summary = ($reportObj['summaryInformationRelatedToContractsSum'][0]) ?? [];
        $bouncedObj = $credit_report['bouncedCheque'] ?? [];
        $bouncedCount = isset($bouncedObj['bouncedCheques']) && is_array($bouncedObj['bouncedCheques']) ? count($bouncedObj['bouncedCheques']) : 0;
    ?>
    
    <div class="mlr-glass-card mlr-report-container" style="margin-top: 20px; padding: 0; overflow: hidden;">
        <!-- Header -->
        <div class="mlr-report-header <?php echo $riskClass; ?>">
            <div class="mlr-score-circle">
                <svg viewBox="0 0 36 36" class="mlr-circular-chart">
                    <path class="mlr-circle-bg" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                    <path class="mlr-circle" stroke-dasharray="<?php echo $percent; ?>, 100" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                    <text x="18" y="20.35" class="mlr-percentage"><?php echo $score; ?></text>
                </svg>
                <span class="mlr-score-label">امتیاز</span>
            </div>
            <div>
                <div style="font-size: 18px; font-weight: 700;">ریسک: <?php echo esc_html($risk); ?></div>
                <div style="font-size: 13px; opacity: 0.9;"><?php echo esc_html($desc); ?></div>
            </div>
        </div>

        <!-- Person Info -->
        <?php 
        $pInfo = $scoreObj['personInformation'] ?? ($reportObj['personInformation'] ?? []);
        if (!empty($pInfo)): ?>
        <div class="mlr-report-section">
            <h4><span class="dashicons dashicons-id-alt"></span> اطلاعات هویتی</h4>
            <div class="mlr-report-content mlr-data-grid">
                <div class="mlr-data-row"><span class="mlr-data-label">نام و نام خانوادگی</span><span class="mlr-data-value"><?php echo esc_html(($pInfo['name'] ?? $pInfo['firstName'] ?? '') . ' ' . ($pInfo['family'] ?? $pInfo['lastName'] ?? '')); ?></span></div>
                <div class="mlr-data-row"><span class="mlr-data-label">کد ملی</span><span class="mlr-data-value"><?php echo esc_html($pInfo['nationalCode'] ?? '-'); ?></span></div>
                <div class="mlr-data-row"><span class="mlr-data-label">نام پدر</span><span class="mlr-data-value"><?php echo esc_html($pInfo['fatherName'] ?? '-'); ?></span></div>
                <div class="mlr-data-row"><span class="mlr-data-label">تاریخ تولد</span><span class="mlr-data-value"><?php echo esc_html($pInfo['dateOfBirth'] ?? $pInfo['birthDate'] ?? '-'); ?></span></div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Summary Cards -->
        <div class="mlr-report-section">
            <h4><span class="dashicons dashicons-chart-bar"></span> خلاصه وضعیت مالی</h4>
            <div class="mlr-report-content mlr-summary-cards">
                <div class="mlr-summary-card">
                    <span class="mlr-summary-val"><?php echo $summary['inProgressContractsCount'] ?? 0; ?></span>
                    <span class="mlr-summary-lbl">تسهیلات جاری</span>
                </div>
                <div class="mlr-summary-card">
                    <span class="mlr-summary-val"><?php echo $fmt($summary['overdueAmount'] ?? 0); ?></span>
                    <span class="mlr-summary-lbl">معوقات (ریال)</span>
                </div>
                <div class="mlr-summary-card">
                    <span class="mlr-summary-val"><?php echo $bouncedCount; ?></span>
                    <span class="mlr-summary-lbl">چک برگشتی</span>
                </div>
                <div class="mlr-summary-card">
                    <span class="mlr-summary-val"><?php echo $fmt($summary['totalAmount'] ?? 0); ?></span>
                    <span class="mlr-summary-lbl">کل تسهیلات (ریال)</span>
                </div>
            </div>
        </div>

        <!-- Loans (Direct Contracts) -->
        <?php if (!empty($reportObj['directContracts'])): ?>
        <div class="mlr-report-section">
            <h4><span class="dashicons dashicons-bank"></span> تسهیلات بانکی (وام‌ها)</h4>
            <div class="mlr-report-content mlr-cards-container">
                <?php foreach($reportObj['directContracts'] as $loan): 
                    $statusText = $loan['negativeContractStatus'] ?? 'جاری';
                    $statusClass = (strpos($statusText, 'معوق') !== false || strpos($statusText, 'مشکوک') !== false) ? 'mlr-badge-danger' : ((strpos($statusText, 'گذشته') !== false) ? 'mlr-badge-warning' : 'mlr-badge-success');
                ?>
                <div class="mlr-contract-card">
                    <div class="mlr-contract-header">
                        <span class="mlr-contract-org"><?php echo esc_html($loan['organizationName'] ?? 'بانک'); ?></span>
                        <span class="mlr-contract-badge <?php echo $statusClass; ?>"><?php echo esc_html($statusText); ?></span>
                    </div>
                    <div class="mlr-contract-body">
                        <div class="mlr-contract-row"><span>نوع:</span> <strong><?php echo esc_html($loan['typeOfFinancingTitle'] ?? '-'); ?></strong></div>
                        <div class="mlr-contract-row"><span>مبلغ کل:</span> <strong><?php echo $fmt($loan['totalAmount'] ?? 0); ?> ریال</strong></div>
                        <div class="mlr-contract-row"><span>مانده بدهی:</span> <strong><?php echo $fmt($loan['overdueAmount'] ?? 0); ?> ریال</strong></div>
                        <div class="mlr-contract-row"><span>شروع:</span> <strong><?php echo esc_html($loan['startDate'] ?? '-'); ?></strong></div>
                        <div class="mlr-contract-row"><span>پایان:</span> <strong><?php echo esc_html($loan['expectedEndDate'] ?? '-'); ?></strong></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Guarantees (Indirect Contracts) -->
        <?php if (!empty($reportObj['indirectContracts'])): ?>
        <div class="mlr-report-section">
            <h4><span class="dashicons dashicons-groups"></span> تعهدات (ضمانت‌ها)</h4>
            <div class="mlr-report-content mlr-cards-container">
                <?php foreach($reportObj['indirectContracts'] as $g): 
                    $statusText = $g['negativeContractStatus'] ?? 'جاری';
                    $statusClass = (strpos($statusText, 'معوق') !== false) ? 'mlr-badge-danger' : 'mlr-badge-success';
                ?>
                <div class="mlr-contract-card">
                    <div class="mlr-contract-header">
                        <span class="mlr-contract-org"><?php echo esc_html($g['organizationName'] ?? 'بانک'); ?> (ضامن)</span>
                        <span class="mlr-contract-badge <?php echo $statusClass; ?>"><?php echo esc_html($statusText); ?></span>
                    </div>
                    <div class="mlr-contract-body">
                        <div class="mlr-contract-row"><span>مبلغ ضمانت:</span> <strong><?php echo $fmt($g['totalAmount'] ?? 0); ?> ریال</strong></div>
                        <div class="mlr-contract-row"><span>معوقات وام‌گیرنده:</span> <strong><?php echo $fmt($g['overdueAmount'] ?? 0); ?> ریال</strong></div>
                        <div class="mlr-contract-row"><span>سررسید:</span> <strong><?php echo esc_html($g['expectedEndDate'] ?? '-'); ?></strong></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Pichak (Cheques) -->
        <?php 
        $pichakItems = $credit_report['pichak']['personChequesData']['personChequeItems'] ?? [];
        if (!empty($pichakItems)): ?>
        <div class="mlr-report-section">
            <h4><span class="dashicons dashicons-tickets-alt"></span> وضعیت چک‌های صیادی (پیچک)</h4>
            <div class="mlr-report-content">
                <table class="mlr-table">
                    <thead><tr><th>وضعیت</th><th>تعداد</th><th>مبلغ کل (ریال)</th></tr></thead>
                    <tbody>
                        <?php foreach($pichakItems as $item): 
                            if (($item['count'] ?? 0) == 0) continue;
                        ?>
                        <tr>
                            <td><?php echo esc_html($item['checkStatusTitle'] ?? '-'); ?></td>
                            <td><?php echo esc_html($item['count'] ?? 0); ?></td>
                            <td><?php echo $fmt($item['totalAmount'] ?? 0); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Bounced Cheques -->
        <div class="mlr-report-section">
            <h4><span class="dashicons dashicons-warning"></span> سوابق چک برگشتی</h4>
            <div class="mlr-report-content">
                <?php if (!empty($bouncedObj['bouncedCheques'])): ?>
                <table class="mlr-table">
                    <thead><tr><th>بانک</th><th>مبلغ (ریال)</th><th>تاریخ برگشت</th><th>تاریخ رفع سوءاثر</th></tr></thead>
                    <tbody>
                        <?php foreach($bouncedObj['bouncedCheques'] as $item): 
                            $bouncedDate = !empty($item['bouncedDate']) ? preg_split('/[ T]/', $item['bouncedDate'])[0] : '-';
                            $clearedDate = !empty($item['clearedDate']) ? preg_split('/[ T]/', $item['clearedDate'])[0] : '---';
                        ?>
                        <tr>
                            <td><?php echo esc_html($item['organizationName'] ?? '-'); ?></td>
                            <td><?php echo $fmt($item['amount'] ?? 0); ?></td>
                            <td><?php echo esc_html($bouncedDate); ?></td>
                            <td><?php echo esc_html($clearedDate); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                    <div class="mlr-badge mlr-badge-success" style="display:inline-block; font-size:13px; padding:5px 10px;">✅ فاقد چک برگشتی</div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Tax -->
        <div class="mlr-report-section">
            <h4><span class="dashicons dashicons-money"></span> وضعیت مالیاتی</h4>
            <div class="mlr-report-content">
                <?php 
                $taxItems = $credit_report['tax']['taxResult'] ?? [];
                $hasTax = false;
                foreach($taxItems as $t) { if(!empty($t['year'])) $hasTax = true; }
                
                if ($hasTax): ?>
                <table class="mlr-table">
                    <thead><tr><th>سال</th><th>درآمد ابرازی</th><th>مالیات قطعی</th><th>بدهی</th></tr></thead>
                    <tbody>
                        <?php foreach($taxItems as $item): if(empty($item['year'])) continue; ?>
                        <tr>
                            <td><?php echo esc_html($item['year']); ?></td>
                            <td><?php echo $fmt($item['daramadEbrazi'] ?? 0); ?></td>
                            <td><?php echo $fmt($item['maliatGhati'] ?? 0); ?></td>
                            <td><?php echo $fmt($item['bedehiMaliatAmalkard'] ?? 0); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                    <div class="mlr-badge mlr-badge-success" style="display:inline-block; font-size:13px; padding:5px 10px;">✅ فاقد بدهی مالیاتی</div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Judiciary -->
        <?php 
        $jud = $credit_report['judiciary']['judiciaryData'] ?? [];
        if (($jud['sumExecuteCount'] ?? 0) > 0 || ($jud['sumNotExecuteCount'] ?? 0) > 0): 
        ?>
        <div class="mlr-report-section">
            <h4><span class="dashicons dashicons-gavel"></span> پرونده‌های قضایی</h4>
            <div class="mlr-report-content mlr-grid-view">
                <div class="mlr-info-item">پرونده‌های اجرا شده: <strong><?php echo esc_html($jud['sumExecuteCount']); ?></strong></div>
                <div class="mlr-info-item">پرونده‌های اجرا نشده: <strong><?php echo esc_html($jud['sumNotExecuteCount']); ?></strong></div>
                <div class="mlr-info-item">محکومیت اجرا نشده: <strong><?php echo $fmt($jud['sumNotExecuteSumPenaltyAmount'] ?? 0); ?> ریال</strong></div>
            </div>
        </div>
        <?php endif; ?>

<div class="mlr-glass-card">
    <h4 class="mlr-card-title"><span class="dashicons dashicons-list-view"></span>مراحل و رویدادهای پرونده</h4>
    <div class="mlr-events-container">
        <?php echo function_exists('mlr_render_request_events_html') ? mlr_render_request_events_html($request_events) : ''; ?>
    </div>
</div>

    </div>
    
    <style>
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

        .mlr-glass-wrapper {
            font-family: var(--mlr-font-family);
            direction: rtl;
            background: linear-gradient(135deg, #f0f5f9 0%, var(--mlr-blue-light) 100%);
            padding: 30px;
            border-radius: var(--mlr-radius-lg);
            box-shadow: var(--mlr-shadow-soft);
            color: var(--mlr-blue-dark);
            max-width: 1000px;
            margin: 0 auto;
        }

        .mlr-glass-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid rgba(29, 53, 88, 0.1);
        }

        .mlr-header-title h3 {
            margin: 0;
            font-size: 24px;
            font-weight: 800;
            color: var(--mlr-blue-dark);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .mlr-header-title .dashicons { font-size: 28px; width: 28px; height: 28px; color: var(--mlr-gold); }
        .mlr-highlight { color: var(--mlr-blue-dark); opacity: 0.7; }

        .mlr-glass-badge {
            padding: 6px 15px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 700;
            background: var(--mlr-white-glass-strong);
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
            color: var(--mlr-blue-dark);
        }

        .mlr-glass-card {
            background: var(--mlr-white-glass);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: var(--mlr-border-glass);
            border-radius: var(--mlr-radius-md);
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.05);
            padding: 25px;
            margin-bottom: 25px;
            transition: transform 0.3s ease;
        }
        .mlr-glass-card:hover { transform: translateY(-3px); background: var(--mlr-white-glass-strong); }

        .mlr-card-title {
            margin: 0 0 20px 0;
            font-size: 16px;
            font-weight: 700;
            color: var(--mlr-blue-dark);
            display: flex;
            align-items: center;
            gap: 8px;
            border-bottom: 1px solid rgba(29, 53, 88, 0.08);
            padding-bottom: 10px;
        }
        .mlr-card-title .dashicons { color: var(--mlr-gold); }

        .mlr-data-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; }
        
        .mlr-data-row { display: flex; justify-content: space-between; margin-bottom: 12px; font-size: 14px; border-bottom: 1px dashed rgba(29, 53, 88, 0.1); padding-bottom: 8px; }
        .mlr-data-row:last-child { border-bottom: none; margin-bottom: 0; }
        .mlr-data-label { color: rgba(29, 53, 88, 0.7); }
        .mlr-data-value { font-weight: 600; color: var(--mlr-blue-dark); text-align: left; }
        .mlr-row-wide { flex-direction: column; gap: 5px; align-items: flex-start; }

        /* Report Specifics */
        .mlr-report-header { display: flex; align-items: center; padding: 25px; color: #fff; margin-bottom: 0; }
        .mlr-risk-low { background: linear-gradient(135deg, #10b981 0%, #059669 100%); }
        .mlr-risk-medium { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); }
        .mlr-risk-high { background: linear-gradient(135deg, #ef4444 0%, #b91c1c 100%); }
        .mlr-risk-unknown { background: linear-gradient(135deg, #64748b 0%, #475569 100%); }
        
        .mlr-score-circle { width: 80px; height: 80px; margin-left: 20px; position: relative; flex-shrink: 0; text-align: center; }
        .mlr-circular-chart { display: block; margin: 0 auto; max-width: 100%; max-height: 100%; }
        .mlr-circle-bg { fill: none; stroke: rgba(255,255,255,0.3); stroke-width: 3.8; }
        .mlr-circle { fill: none; stroke: #fff; stroke-width: 2.8; stroke-linecap: round; transition: stroke-dasharray 1s ease-out; }
        .mlr-percentage { fill: #fff; font-family: sans-serif; font-weight: bold; font-size: 0.5em; text-anchor: middle; }
        
        .mlr-score-label { font-size: 10px; font-weight: normal; margin-top: 2px; opacity: 0.9; }
        
        .mlr-report-section { padding: 20px; border-bottom: 1px solid rgba(29, 53, 88, 0.08); }
        .mlr-report-section h4 { margin: 0 0 15px 0; font-size: 15px; color: var(--mlr-blue-dark); border-right: 3px solid var(--mlr-gold); padding-right: 10px; display: flex; align-items: center; gap: 8px; }
        .mlr-report-content { padding: 15px; }
        
        .mlr-summary-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 10px; }
        .mlr-summary-card { background: rgba(208, 226, 242, 0.4); border: 1px solid rgba(255,255,255,0.6); border-radius: 12px; padding: 15px; text-align: center; }
        .mlr-summary-val { font-size: 16px; font-weight: 800; color: var(--mlr-blue-dark); display: block; margin-bottom: 4px; }
        .mlr-summary-lbl { font-size: 11px; color: rgba(29, 53, 88, 0.7); }
        
        .mlr-table { width: 100%; border-collapse: collapse; font-size: 12px; }
        .mlr-table th { background: rgba(208, 226, 242, 0.6); padding: 10px; text-align: right; color: var(--mlr-blue-dark); border-bottom: 1px solid rgba(29, 53, 88, 0.1); white-space: nowrap; }
        .mlr-table td { padding: 10px; border-bottom: 1px solid rgba(29, 53, 88, 0.05); color: var(--mlr-blue-dark); }
        .mlr-table tr:last-child td { border-bottom: none; }

        .mlr-cards-container { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 15px; }
        .mlr-contract-card { border: 1px solid rgba(29, 53, 88, 0.15); border-radius: 12px; overflow: hidden; background: var(--mlr-white-glass); }
        .mlr-contract-header { background: rgba(208, 226, 242, 0.6); padding: 10px 15px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid rgba(29, 53, 88, 0.1); }
        .mlr-contract-org { font-weight: 700; font-size: 13px; color: var(--mlr-blue-dark); }
        .mlr-contract-badge { font-size: 11px; padding: 3px 8px; border-radius: 12px; color: #fff; font-weight: 600; }
        .mlr-contract-body { padding: 15px; font-size: 13px; }
        .mlr-contract-row { display: flex; justify-content: space-between; margin-bottom: 8px; border-bottom: 1px dashed rgba(29, 53, 88, 0.1); padding-bottom: 4px; }
        .mlr-contract-row:last-child { margin-bottom: 0; border-bottom: none; }
        
        .mlr-badge { padding: 3px 8px; border-radius: 12px; font-size: 11px; color: #fff; display: inline-block; }
        .mlr-badge-success { background: #10b981; }
        .mlr-badge-warning { background: #f59e0b; }
        .mlr-badge-danger { background: #ef4444; }

        /* Inputs & Buttons */
        .mlr-glass-input { width: 100%; padding: 10px; border: 1px solid rgba(29, 53, 88, 0.2); border-radius: 8px; background: rgba(255,255,255,0.8); transition: all 0.3s; }
        .mlr-glass-input:focus { background: #fff; border-color: var(--mlr-blue-dark); outline: none; box-shadow: 0 0 0 3px rgba(29, 53, 88, 0.1); }
        
        .mlr-btn { padding: 10px 20px; border-radius: 8px; border: none; cursor: pointer; font-weight: 700; transition: all 0.3s; display: inline-flex; align-items: center; justify-content: center; gap: 5px; }
        .mlr-btn-primary { background: var(--mlr-blue-dark); color: #fff; }
        .mlr-btn-primary:hover { background: var(--mlr-gold); color: var(--mlr-blue-dark); }
        .mlr-btn-secondary { background: rgba(255,255,255,0.6); color: var(--mlr-blue-dark); border: 1px solid rgba(29, 53, 88, 0.1); }
        .mlr-btn-secondary:hover { background: #fff; }

        /* Accordion */
        .mlr-glass-accordion { border: 1px solid rgba(29, 53, 88, 0.15); border-radius: 12px; overflow: hidden; background: var(--mlr-white-glass); margin-bottom: 10px; }
        .mlr-glass-acc-btn { width: 100%; text-align: right; background: rgba(208, 226, 242, 0.3); border: 0; padding: 12px 15px; cursor: pointer; display: flex; align-items: center; justify-content: space-between; font-weight: 700; color: var(--mlr-blue-dark); }
        .mlr-glass-acc-content { display: none; padding: 15px; background: rgba(255,255,255,0.4); }
        .mlr-glass-acc-content.is-open { display: block; }

        /* Files */
        .mlr-file-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 10px; }
        .mlr-file-item { background: #fff; padding: 10px; border-radius: 8px; border: 1px solid rgba(29, 53, 88, 0.1); text-align: center; }
        .mlr-file-label { font-size: 12px; margin-bottom: 5px; color: rgba(29, 53, 88, 0.7); }

        /* Comments */
        .mlr-comment { background: rgba(255,255,255,0.6); padding: 12px; border-radius: 8px; margin-bottom: 10px; border: 1px solid rgba(29, 53, 88, 0.05); }

        .mlr-order-items-list { list-style: none; padding: 0; margin: 0; }
        .mlr-order-items-list li { padding: 8px 0; border-bottom: 1px solid rgba(29, 53, 88, 0.05); }
        .mlr-order-items-list li:last-child { border-bottom: none; }
        .mlr-item-meta { color: rgba(29, 53, 88, 0.6); font-size: 0.9em; }

        @media (max-width: 768px) {
            .mlr-glass-wrapper { padding: 15px; }
            .mlr-data-grid { grid-template-columns: 1fr; }
        }
    </style>
<?php endif; ?>


    </div>
    <?php
    $html = ob_get_clean();
    wp_send_json_success(['html' => $html]);
}
?>
