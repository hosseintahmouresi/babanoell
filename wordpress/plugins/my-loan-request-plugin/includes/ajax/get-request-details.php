<?php
// FILE: my-loan-request-plugin/includes/ajax/get-request-details.php
if (!defined('ABSPATH')) {
    exit;
}

add_action('wp_ajax_mlr_get_request_details', 'mlr_get_request_details_callback');

function mlr_get_request_details_callback() {
    check_ajax_referer('mlr_ajax_nonce_action', 'nonce');

    if (!current_user_can('manage_options') && !current_user_can('mlr_amaliat_operations') && !current_user_can('mlr_mali_operations')) {
        wp_send_json_error(['message' => 'عدم دسترسی.']);
        return;
    }

    $request_id = isset($_POST['request_id']) ? intval($_POST['request_id']) : 0;
    if (!$request_id) {
        wp_send_json_error(['message' => 'شناسه نامعتبر.']);
        return;
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'loan_requests';
    $plans_table = $wpdb->prefix . 'installment_plans';
    
    $request = $wpdb->get_row($wpdb->prepare("
        SELECT r.*, p.name AS plan_name, p.display_fields, p.display_fields_context
        FROM {$table_name} r 
        LEFT JOIN {$plans_table} p ON r.installment_plan_id = p.id 
        WHERE r.id = %d
    ", $request_id));

    if (!$request) {
        wp_send_json_error(['message' => 'درخواست یافت نشد.']);
        return;
    }
    
    $internal_loan_contract_review_html = function_exists('mlr_render_loan_contract_review_card')
        ? mlr_render_loan_contract_review_card($request, [
            'title' => 'قرارداد دریافت تسهیلات',
            'context' => 'internal',
        ])
        : '';

    // Occupations Mapping
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
    $occupation_display = isset($occupations[$request->occupation]) ? $occupations[$request->occupation] : esc_html($request->occupation);

    // Documents & Items Processing
    $uploaded_documents = function_exists('mlr_get_request_uploaded_documents')
        ? mlr_get_request_uploaded_documents($request->required_documents)
        : maybe_unserialize($request->required_documents);
    if (!is_array($uploaded_documents)) {
        $uploaded_documents = [];
    }

    // --- MLR EXTRA DETAILS SECTION (ADMIN) ---
    // فقط نمایش کامل‌تر جزئیات؛ منطق ذخیره/عملیات را تغییر نمی‌دهد.
    $extra_files = [];
    $extra_files['bank_validation_file'] = $request->bank_validation_file ?? '';
    $extra_files['signature_image']      = $request->signature_image ?? '';
    $extra_files['contract_file']        = $request->contract_file ?? '';

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

    // Guarantor info (ضامن اول و ضامن دوم)
    $guarantor_info = !empty($request->guarantor_info) ? json_decode($request->guarantor_info, true) : [];
    if (!is_array($guarantor_info)) {
        $guarantor_info = [];
    }
    $guarantor_info_2 = !empty($request->guarantor_info_2) ? json_decode($request->guarantor_info_2, true) : [];
    if (!is_array($guarantor_info_2)) {
        $guarantor_info_2 = [];
    }
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
    foreach ($guarantor_file_labels as $gk => $gl) {
        if (!empty($guarantor_info[$gk]) && is_string($guarantor_info[$gk]) && strpos($guarantor_info[$gk], 'http') === 0) {
            $extra_files['guarantor_' . $gk] = $guarantor_info[$gk];
            $extra_file_labels['guarantor_' . $gk] = $gl;
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
    foreach ($guarantor_file_labels_2 as $gk => $gl) {
        if (!empty($guarantor_info_2[$gk]) && is_string($guarantor_info_2[$gk]) && strpos($guarantor_info_2[$gk], 'http') === 0) {
            $extra_files['guarantor_2_' . $gk] = $guarantor_info_2[$gk];
            $extra_file_labels['guarantor_2_' . $gk] = $gl;
        }
    }
    // پیوست‌های مرحله در انتظار مدارک (برای نمایش به پذیرنده در کنار فاکتور و قرارداد)
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
    // --- END: MLR EXTRA DETAILS SECTION (ADMIN) ---
    $order_items = !empty($request->order_items) ? json_decode($request->order_items, true) : [];
    $comments = !empty($request->comments) ? json_decode($request->comments, true) : [];
    $form_data = !empty($request->form_data) ? json_decode($request->form_data, true) : [];
    if (!is_array($form_data)) { $form_data = []; }
    
    // استخراج گزارش اعتبارسنجی
    $credit_report = null;
    if (isset($request->credit_report_json) && !empty($request->credit_report_json)) {
        $credit_report = json_decode($request->credit_report_json, true);
    } elseif (isset($form_data['credit_report_json'])) {
        $cr_data = $form_data['credit_report_json'];
        if (is_string($cr_data)) {
            $credit_report = json_decode($cr_data, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $credit_report = json_decode(stripslashes($cr_data), true);
            }
        } else {
            $credit_report = $cr_data;
        }
    }

    // حذف از form_data
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
        'father_name'          => 'نام پدر',
        'certificate_number'   => 'شماره شناسنامه',
        'certificate_city'     => 'شهر صدور شناسنامه',
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
        'guarantee_check_principal' => 'مبلغ چک ضمانت (اصل)',
        'guarantee_check_total'     => 'مبلغ چک ضمانت (اصل و فرع)',
        'prepayment'           => 'پیش پرداخت',
        'wallet_charge'        => 'اعتبار خرید',
        'acceptor_payment_amount' => 'مبلغ تسویه',
        'comments'             => 'توضیحات',
        'address'              => 'آدرس',
    ];

    $request_events = function_exists('mlr_get_request_events') ? mlr_get_request_events($request_id, 500) : [];
    $request_messages = function_exists('mlr_get_request_messages') ? mlr_get_request_messages($request_id, 500) : [];
    
    // Get document labels from helper if exists, or use keys
    $all_doc_labels = function_exists('mlr_get_all_document_options') ? mlr_get_all_document_options() : [];

    $document_receipt_status_display = function_exists('mlr_get_document_receipt_status_label')
        ? mlr_get_document_receipt_status_label($request->document_receipt_status ?? '')
        : 'بررسی نشده';
    $can_manage_document_receipt_status = function_exists('mlr_can_manage_document_receipt_status') && mlr_can_manage_document_receipt_status();
    $can_manage_document_received_date = function_exists('mlr_can_manage_document_received_date') && mlr_can_manage_document_received_date();
    $can_manage_transfer_receipts = function_exists('mlr_can_manage_transfer_receipts') && mlr_can_manage_transfer_receipts();
    $can_view_leasing_deposit_amount = function_exists('mlr_can_view_leasing_deposit_amount') && mlr_can_view_leasing_deposit_amount();
    $acceptor_settlement_amount_display = function_exists('mlr_format_acceptor_settlement_amount') ? mlr_format_acceptor_settlement_amount($request) : '—';
    $acceptor_settlement_amount_value = $acceptor_settlement_amount_display !== '—' ? $acceptor_settlement_amount_display . ' ریال' : '—';
    $leasing_deposit_amount_display = function_exists('mlr_format_leasing_deposit_amount') ? mlr_format_leasing_deposit_amount($request) : '—';
    $leasing_deposit_amount_value = $leasing_deposit_amount_display !== '—' ? $leasing_deposit_amount_display . ' ریال' : '—';

    // Bank -> Babanoell settlement is an internal tracking layer and is intentionally
    // separate from every acceptor settlement status/date/cycle.
    $bank_settlement_status_raw = trim((string) ($request->bank_settlement_status ?? ''));
    $request_status_normalized = function_exists('mlr_normalize_status')
        ? mlr_normalize_status((string) ($request->status ?? ''))
        : trim((string) ($request->status ?? ''));
    $bank_settlement_is_recorded = in_array($bank_settlement_status_raw, ['انجام شده', 'تسویه شده', 'ثبت شده'], true);
    $bank_settlement_status_display = ($bank_settlement_status_raw !== '' || $request_status_normalized === 'اعطا شده')
        ? ($bank_settlement_is_recorded ? 'انجام شده' : 'ثبت نشده')
        : '—';
    $bank_settled_at_display = !empty($request->bank_settled_at) && function_exists('mlr_gregorian_to_jalali')
        ? mlr_gregorian_to_jalali($request->bank_settled_at, 'Y/m/d')
        : (!empty($request->bank_settled_at) ? $request->bank_settled_at : '—');
    $bank_settled_by_display = '—';
    if (!empty($request->bank_settled_by)) {
        $bank_settled_by_user = get_userdata((int) $request->bank_settled_by);
        if ($bank_settled_by_user) {
            $bank_settled_by_display = $bank_settled_by_user->display_name ?: $bank_settled_by_user->user_login;
        }
    }
    $bank_settlement_note_display = trim((string) ($request->bank_settlement_note ?? '')) ?: '—';
    $transfer_receipt_files = $can_manage_transfer_receipts && function_exists('mlr_get_transfer_receipt_files')
        ? mlr_get_transfer_receipt_files($request->transfer_receipt_files ?? null)
        : [];
    if (!is_array($transfer_receipt_files)) {
        $transfer_receipt_files = [];
    }
    $document_received_date_display = function_exists('mlr_format_optional_jalali_datetime')
        ? mlr_format_optional_jalali_datetime($request->document_received_date ?? '')
        : (!empty($request->document_received_date) ? $request->document_received_date : '—');
    $document_received_date_input = '';
    if (!empty($request->document_received_date)) {
        $document_received_date_input = function_exists('mlr_gregorian_to_jalali')
            ? mlr_gregorian_to_jalali($request->document_received_date, 'Y/m/d')
            : wp_date('Y/m/d', strtotime($request->document_received_date));
    }
    $financier_payment_status_options = function_exists('mlr_get_financier_payment_status_options')
        ? mlr_get_financier_payment_status_options()
        : ['بررسی نشده', 'پرداخت شده', 'عدم پرداخت'];
    $financier_payment_status_display = function_exists('mlr_normalize_financier_payment_status')
        ? mlr_normalize_financier_payment_status($request->financier_payment_status ?? '')
        : (!empty($request->financier_payment_status) ? $request->financier_payment_status : 'بررسی نشده');
    $show_guarantee_check_principal = function_exists('mlr_is_plan_calculation_field_enabled')
        ? mlr_is_plan_calculation_field_enabled($request, 'guarantee_check_principal', 'default')
        : true;
    $show_guarantee_check_total = function_exists('mlr_is_plan_calculation_field_enabled')
        ? mlr_is_plan_calculation_field_enabled($request, 'guarantee_check_total', 'default')
        : true;

    $financial_fields = [
        ['label' => 'Ù†Ø§Ù… Ø·Ø±Ø­', 'value' => $request->plan_name],
        ['label' => 'Ù…Ø¨Ù„Øº Ø¯Ø±Ø®ÙˆØ§Ø³ØªÛŒ', 'value' => number_format($request->loan_amount) . ' Ø±ÛŒØ§Ù„'],
        ['label' => 'Ù…Ø¨Ù„Øº ØªØ³Ù‡ÛŒÙ„Ø§Øª', 'value' => isset($request->facility_amount) && $request->facility_amount !== '' ? number_format(floatval($request->facility_amount)) . ' Ø±ÛŒØ§Ù„' : (number_format($request->loan_amount) . ' Ø±ÛŒØ§Ù„')],
        ['label' => 'ÙØ§ØµÙ„Ù‡ Ø§Ù‚Ø³Ø§Ø·', 'value' => isset($request->installment_interval) ? $request->installment_interval . ' Ù…Ø§Ù‡' : 'â€”'],
        ['label' => 'ØªØ¹Ø¯Ø§Ø¯ Ø§Ù‚Ø³Ø§Ø·', 'value' => $request->installment_count . ' Ù‚Ø³Ø·'],
        ['label' => 'Ù…Ø¨Ù„Øº Ù‡Ø± Ù‚Ø³Ø·', 'value' => number_format($request->installment_amount) . ' Ø±ÛŒØ§Ù„'],
        ['label' => 'Ù¾ÛŒØ´â€ŒÙ¾Ø±Ø¯Ø§Ø®Øª', 'value' => number_format($request->prepayment) . ' Ø±ÛŒØ§Ù„'],
        ['label' => 'Ù…Ø¨Ù„Øº Ú©Ù„ Ø³ÙØ§Ø±Ø´', 'value' => number_format($request->order_total) . ' Ø±ÛŒØ§Ù„'],
        ['label' => 'Ø§Ø¹ØªØ¨Ø§Ø± Ø®Ø±ÛŒØ¯', 'value' => number_format($request->wallet_charge) . ' Ø±ÛŒØ§Ù„'],
    ];

    if ($show_guarantee_check_principal) {
        $financial_fields[] = ['label' => 'Ù…Ø¨Ù„Øº Ú†Ú© Ø¶Ù…Ø§Ù†Øª (Ø§ØµÙ„)', 'value' => isset($form_data['guarantee_check_principal']) ? number_format((float) $form_data['guarantee_check_principal']) . ' Ø±ÛŒØ§Ù„' : '-'];
    }
    if ($show_guarantee_check_total) {
        $financial_fields[] = ['label' => 'Ù…Ø¨Ù„Øº Ú†Ú© Ø¶Ù…Ø§Ù†Øª (Ú©Ù„)', 'value' => isset($form_data['guarantee_check_total']) ? number_format((float) $form_data['guarantee_check_total']) . ' Ø±ÛŒØ§Ù„' : '-'];
    }

    $financial_fields[] = ['label' => 'ÙˆØ¶Ø¹ÛŒØª Ø¯Ø±Ø®ÙˆØ§Ø³Øª', 'value' => $request->status, 'is_status' => true];
    $financial_fields[] = ['label' => 'Ø¯Ø±ÛŒØ§ÙØª Ù…Ø¯Ø§Ø±Ú©', 'value' => $document_receipt_status_display, 'css_class' => 'mlr-document-receipt-status-value'];

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
                ['label' => 'اعتبار خرید', 'value' => number_format($request->wallet_charge) . ' ریال'],
                ['label' => 'مبلغ تسویه', 'value' => $acceptor_settlement_amount_value],
                $can_view_leasing_deposit_amount ? ['label' => 'مبلغ (واریزی لیزینگ)', 'value' => $leasing_deposit_amount_value] : null,
                $show_guarantee_check_principal ? ['label' => 'مبلغ چک ضمانت (اصل)', 'value' => isset($form_data['guarantee_check_principal']) ? number_format((float)$form_data['guarantee_check_principal']) . ' ریال' : '-'] : null,
                $show_guarantee_check_total ? ['label' => 'مبلغ چک ضمانت (کل)', 'value' => isset($form_data['guarantee_check_total']) ? number_format((float)$form_data['guarantee_check_total']) . ' ریال' : '-'] : null,
                ['label' => 'وضعیت درخواست', 'value' => $request->status, 'is_status' => true],
                ['label' => 'دریافت مدارک', 'value' => $document_receipt_status_display, 'css_class' => 'mlr-document-receipt-status-value'],
                ['label' => 'ثبت تسویه بانک با بابانوئل', 'value' => $bank_settlement_status_display],
                ['label' => 'یادداشت داخلی تسویه بانک با بابانوئل', 'value' => $bank_settlement_note_display, 'wide' => true],
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
                ['label' => 'تاریخ تسویه بانک با بابانوئل', 'value' => $bank_settled_at_display],
                ['label' => 'ثبت‌کننده اطلاعات تسویه بانک با بابانوئل', 'value' => $bank_settled_by_display],
                ['label' => 'تاریخ دریافت مدارک', 'value' => $document_received_date_display, 'css_class' => 'mlr-document-received-date-value'],
            ]
        ]
    ];

    if (isset($sections['financial']['fields']) && is_array($sections['financial']['fields'])) {
        $sections['financial']['fields'] = array_values(array_filter($sections['financial']['fields'], static function ($field) {
            return !empty($field);
        }));
    }
    $section_render_order = ['identity', 'job_addr', 'financial', 'dates'];

    $marketplace_admin_data = null;
    if (function_exists('mlr_is_applicant_origin_request') && mlr_is_applicant_origin_request($request)) {
        $marketplace_location_ids = function_exists('mlr_get_request_location_term_ids') ? mlr_get_request_location_term_ids($request) : [];
        $marketplace_category_ids = function_exists('mlr_get_request_activity_category_term_ids') ? mlr_get_request_activity_category_term_ids($request) : [];
        $marketplace_cities = function_exists('mlr_get_request_location_names') ? mlr_get_request_location_names($request) : [];
        $marketplace_categories = function_exists('mlr_get_request_activity_category_names') ? mlr_get_request_activity_category_names($request) : [];
        $acceptor_name = !empty($request->acceptor_user_id) && function_exists('mlr_get_acceptor_display_name')
            ? mlr_get_acceptor_display_name($request->acceptor_user_id)
            : '';
        $claimed_name = !empty($request->marketplace_claimed_by) && function_exists('mlr_get_acceptor_display_name')
            ? mlr_get_acceptor_display_name($request->marketplace_claimed_by)
            : '';

        $mode_labels = [
            'applicant_selected_acceptor' => 'پذیرنده انتخاب‌شده توسط متقاضی',
            'open_acceptor_marketplace' => 'بازار آزاد پذیرنده',
            '' => 'ثبت مستقیم بدون اتصال ویژه',
        ];
        $status_labels = [
            'open' => 'باز',
            'claimed' => 'پذیرفته‌شده',
            'closed' => 'بسته',
            '' => 'ندارد',
        ];
        $scope_label = in_array((string) ($request->requested_service_scope ?? ''), ['selected_locations', 'selected_cities'], true) ? 'موقعیت‌های منتخب' : 'کل کشور';
        $assignment_mode = (string) ($request->acceptor_assignment_mode ?? '');
        $marketplace_status = (string) ($request->marketplace_status ?? '');

        $sections['marketplace'] = [
            'title' => 'اتصال پذیرنده',
            'icon' => 'dashicons-store',
            'fields' => [
                ['label' => 'مبدأ درخواست', 'value' => function_exists('mlr_get_request_origin_label') ? mlr_get_request_origin_label($request) : 'درخواست مستقیم متقاضی'],
                ['label' => 'حالت اتصال', 'value' => $mode_labels[$assignment_mode] ?? $assignment_mode],
                ['label' => 'وضعیت بازار آزاد', 'value' => $status_labels[$marketplace_status] ?? $marketplace_status],
                ['label' => 'پذیرنده', 'value' => $acceptor_name ? ($acceptor_name . ' (#' . absint($request->acceptor_user_id) . ')') : '—'],
                ['label' => 'پذیرنده claim کننده', 'value' => $claimed_name ? ($claimed_name . ' (#' . absint($request->marketplace_claimed_by) . ')') : '—'],
                ['label' => 'محدوده جغرافیایی', 'value' => $scope_label],
                ['label' => 'موقعیت‌ها', 'value' => !empty($marketplace_cities) ? implode('، ', $marketplace_cities) : '—'],
                ['label' => 'حوزه فعالیت', 'value' => !empty($marketplace_categories) ? implode('، ', $marketplace_categories) : '—'],
                ['label' => 'خلاصه عمومی', 'value' => !empty($request->request_public_summary) ? $request->request_public_summary : '—', 'wide' => true],
            ],
        ];

        $marketplace_admin_data = [
            'cities' => $marketplace_cities,
            'categories' => $marketplace_categories,
            'location_ids' => $marketplace_location_ids,
            'category_ids' => $marketplace_category_ids,
            'scope' => in_array((string) ($request->requested_service_scope ?? ''), ['selected_locations', 'selected_cities'], true) ? 'selected_locations' : 'all_country',
            'summary' => (string) ($request->request_public_summary ?? ''),
            'plan_id' => absint($request->installment_plan_id ?? 0),
            'acceptor_id' => absint($request->acceptor_user_id ?? 0),
            'acceptor_name' => $acceptor_name,
        ];
    }

    ob_start();
    ?>
    <div class="mlr-glass-wrapper">
        <div class="mlr-glass-header">
            <div class="mlr-header-title">
                <span class="dashicons dashicons-admin-generic"></span>
                <h3>مدیریت درخواست <span class="mlr-highlight">#<?php echo esc_html($request->id); ?></span></h3>
                <button type="button" class="mlr-btn mlr-btn-secondary mlr-export-single-excel-btn" data-id="<?php echo esc_attr($request->id); ?>" style="margin-right: 15px; height: 32px; padding: 0 15px; font-size: 12px; display: inline-flex; align-items: center; gap: 5px; cursor: pointer;">
                    <span class="dashicons dashicons-media-spreadsheet" style="font-size: 16px;"></span>
                    خروجی اکسل پرونده
                </button>
            </div>
            <span class="mlr-glass-badge status-<?php echo sanitize_html_class($request->status); ?>"><?php echo esc_html($request->status); ?></span>
        </div>

    <div class="mlr-glass-body">

        <?php // گفتگو و ارسال پیام برای همه وضعیت‌ها از جمله تسویه شده فعال است. ?>
        <div class="mlr-glass-card">
            <h4 class="mlr-card-title">
                <span class="dashicons dashicons-format-chat"></span>
                <span>گفتگو و یادداشت‌های پرونده</span>
            </h4>
                <div class="mlr-conversation-history" id="mlr-conversation-timeline-container" data-request-id="<?php echo esc_attr($request->id); ?>">
                    <?php echo function_exists('mlr_render_request_conversation_html') ? mlr_render_request_conversation_html($comments, $request_messages) : ''; ?>
                </div>

                <div class="mlr-conversation-compose">
                    <textarea id="mlr_new_comment_text" rows="3" class="mlr-glass-input" placeholder="متن پیام یا یادداشت داخلی..."></textarea>

                    <div class="mlr-attachment-controls">
                        <label for="mlr-message-attachment-input" class="mlr-attachment-btn">
                            <span class="dashicons dashicons-paperclip"></span> پیوست فایل به پیام
                        </label>
                        <input type="file" id="mlr-message-attachment-input" multiple style="display:none;">
                        <div id="mlr-attachment-previews"></div>
                    </div>

                    <div class="mlr-conversation-actions">
                        <button type="button" class="mlr-btn mlr-btn-primary mlr-send-message-btn" data-request-id="<?php echo esc_attr($request->id); ?>">
                            <span class="dashicons dashicons-send"></span>
                            ارسال پیام
                        </button>

                        <button type="button" class="mlr-btn mlr-btn-secondary" id="mlr-add-comment-btn" data-id="<?php echo esc_attr($request->id); ?>">
                            ثبت یادداشت (داخلی)
                        </button>
                        <span class="spinner" aria-hidden="true"></span>

                        <button type="button" class="mlr-btn mlr-btn-secondary mlr-refresh-messages-btn" data-request-id="<?php echo esc_attr($request->id); ?>">
                            <span class="dashicons dashicons-update"></span>
                            به‌روزرسانی
                        </button>
                    </div>
                </div>
        </div>

        <!-- Unified Info Sections -->
        <div class="mlr-data-grid">
            <?php foreach ($section_render_order as $section_key): ?>
                <?php if (empty($sections[$section_key])) { continue; } $section = $sections[$section_key]; ?>
                <div class="mlr-glass-card">
                    <h5 class="mlr-card-title"><span class="dashicons <?php echo esc_attr($section['icon']); ?>"></span> <?php echo esc_html($section['title']); ?></h5>
                    <?php foreach ($section['fields'] as $field): ?>
                        <?php if (empty($field)) continue; ?>
                        <div class="mlr-data-row <?php echo !empty($field['wide']) ? 'mlr-row-wide' : ''; ?>">
                            <span class="mlr-data-label"><?php echo esc_html($field['label']); ?></span>
                            <span class="mlr-data-value <?php echo !empty($field['is_status']) ? 'mlr-status-text' : ''; ?> <?php echo !empty($field['css_class']) ? esc_attr($field['css_class']) : ''; ?>">
                                <?php echo esc_html($field['value']); ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="mlr-glass-card mlr-docs-card" data-default-cat="">
            <h4 class="mlr-card-title"><span class="dashicons dashicons-paperclip"></span> مدارک و فایل‌ها</h4>
            <?php
                $grouped_docs = function_exists('mlr_group_uploaded_documents_by_category') ? mlr_group_uploaded_documents_by_category($uploaded_documents, $all_doc_labels) : [];
                $cat_labels = function_exists('mlr_get_document_category_labels') ? mlr_get_document_category_labels() : [];
                $default_cat = '';
                if (!empty($grouped_docs) && is_array($grouped_docs)) {
                    $keys = array_keys($grouped_docs);
                    $default_cat = isset($keys[0]) ? (string)$keys[0] : '';
                }
            ?>
            <?php if (!empty($grouped_docs)): ?>
                <div class="mlr-docs-categories" role="tablist" aria-label="دسته‌بندی مدارک">
                    <?php foreach ($grouped_docs as $cat => $items): 
                        $lbl = isset($cat_labels[$cat]) ? $cat_labels[$cat] : $cat;
                    ?>
                        <button type="button" class="mlr-btn mlr-btn-secondary mlr-doc-cat-btn" data-cat="<?php echo esc_attr($cat); ?>">
                            <?php echo esc_html($lbl); ?>
                        </button>
                    <?php endforeach; ?>
                </div>

                <div class="mlr-docs-sections">
                    <?php foreach ($grouped_docs as $cat => $items): ?>
                        <div class="mlr-doc-cat-section" data-cat="<?php echo esc_attr($cat); ?>" style="display:none;">
                            <div class="mlr-file-grid">
                                <?php foreach ($items as $doc): ?>
                                    <div class="mlr-file-item">
                                        <div class="mlr-file-label"><?php echo esc_html($doc['label']); ?></div>
                                        <div class="mlr-file-actions">
                                            <?php foreach ($doc['urls'] as $i => $url): ?>
                                                <a href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener noreferrer" class="mlr-btn mlr-btn-secondary mlr-view-file-btn" style="padding: 5px 10px; font-size: 12px;">
                                                    <span class="dashicons dashicons-visibility" aria-hidden="true"></span>
                                                    <?php echo (count($doc['urls']) > 1) ? ('مشاهده ' . intval($i + 1)) : 'مشاهده فایل'; ?>
                                                </a>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <style>
                    
                </style>
            <?php else: ?>
                <p class="mlr-text-muted">هیچ مدرکی بارگذاری نشده است.</p>
            <?php endif; ?>
        </div>

        <?php if ($internal_loan_contract_review_html !== ''): ?>
            <?php echo $internal_loan_contract_review_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- rendered by trusted helper. ?>
        <?php endif; ?>

        <div class="mlr-glass-card">
            <h4 class="mlr-card-title"><span class="dashicons dashicons-media-text"></span> اطلاعات تکمیلی سفارش</h4>
            <div class="mlr-data-grid">
                <?php if (!empty($request->product_name)): ?>
                    <div class="mlr-data-row"><span class="mlr-data-label">نام کالا</span><span class="mlr-data-value"><?php echo esc_html($request->product_name); ?></span></div>
                <?php else: ?>
                    <div class="mlr-data-row"><span class="mlr-data-label">نام کالا</span><span class="mlr-data-value">—</span></div>
                <?php endif; ?>
            </div>
            <?php if (!empty($request->description)): ?>
                <div style="margin-top:15px; padding-top:15px; border-top:1px dashed rgba(29,53,88,0.1);">
                    <span class="mlr-data-label" style="display:block; margin-bottom:5px;">توضیحات:</span>
                    <div class="mlr-data-value" style="font-weight:normal; line-height:1.6;"><?php echo nl2br(esc_html($request->description)); ?></div>
                </div>
            <?php endif; ?>
            </div>




        <?php if (!empty($order_items)): ?>
            <div class="mlr-glass-card">
                <h4 class="mlr-card-title"><span class="dashicons dashicons-cart"></span> ریز اقلام درخواستی</h4>
                <div class="mlr-table-wrapper">
                    <table class="mlr-report-table">
                        <thead>
                            <tr>
                                <th>نام کالا</th>
                                <th>تعداد</th>
                                <th>قیمت واحد (ریال)</th>
                                <th>قیمت کل (ریال)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($order_items as $item):
                                $price = floatval($item['price'] ?? 0);
                                $qty = intval($item['qty'] ?? 1);
                            ?>
                                <tr>
                                    <td><?php echo esc_html($item['name']); ?></td>
                                    <td><?php echo $qty; ?></td>
                                    <td><?php echo number_format($price); ?></td>
                                    <td><?php echo number_format($price * $qty); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>


        <?php if ($can_manage_document_receipt_status): ?>
            <div class="mlr-glass-card">
                <h4 class="mlr-card-title"><span class="dashicons dashicons-clipboard"></span> تنظیمات پرونده</h4>
                <form id="mlr-document-receipt-status-form" class="mlr-inline-settings-form" data-request-id="<?php echo esc_attr($request->id); ?>">
                    <div class="mlr-inline-settings-grid">
                        <div class="mlr-form-group">
                            <label for="mlr-document-receipt-status-select">دریافت مدارک</label>
                            <select id="mlr-document-receipt-status-select" name="document_receipt_status" class="mlr-glass-input mlr-document-receipt-status-select">
                                <?php foreach ((function_exists('mlr_get_document_receipt_status_options') ? mlr_get_document_receipt_status_options() : ['بررسی نشده', 'دریافت شده', 'عدم دریافت', 'عدم نیاز به دریافت مدارک']) as $document_receipt_option): ?>
                                    <option value="<?php echo esc_attr($document_receipt_option); ?>" <?php selected($document_receipt_status_display, $document_receipt_option); ?>><?php echo esc_html($document_receipt_option); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description" style="margin:8px 0 0;">این فیلد برای کارشناس عملیات، مالی و سوپر ادمین قابل تغییر است.</p>
                        </div>
                        <?php if ($can_manage_document_received_date): ?>
                            <div class="mlr-form-group">
                                <label for="mlr-document-received-date-input">تاریخ دریافت مدارک</label>
                                <div class="mlr-date-input-wrapper" style="position: relative; display: flex; align-items: center;">
                                    <input id="mlr-document-received-date-input" type="text" name="document_received_date" class="mlr-glass-input jalali-datepicker mlr-document-received-date-input" value="<?php echo esc_attr($document_received_date_input); ?>" placeholder="انتخاب تاریخ" autocomplete="off" readonly style="padding-left: 34px; cursor: pointer;">
                                    <span class="dashicons dashicons-calendar-alt mlr-datepicker-trigger" aria-hidden="true" style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: #64748b; cursor: pointer; z-index: 2;"></span>
                                </div>
                                <p class="description" style="margin:8px 0 0;">این فیلد فقط توسط کارشناس عملیات و سوپرادمین قابل ویرایش است.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="mlr-inline-settings-actions">
                        <button type="submit" class="mlr-btn mlr-btn-primary">ذخیره وضعیت دریافت مدارک</button>
                        <span class="mlr-document-receipt-status-msg"></span>
                    </div>
                </form>
            </div>
        <?php endif; ?>

        <?php if ($can_manage_transfer_receipts): ?>
            <div class="mlr-glass-card">
                <h4 class="mlr-card-title"><span class="dashicons dashicons-money-alt"></span> رسیدهای واریزی مالی</h4>
                <p class="description" style="margin:0 0 14px;">این بخش فقط برای کارشناس مالی و سوپرادمین نمایش داده می‌شود.</p>
                <div class="mlr-data-row" style="justify-content:flex-start; margin-bottom: 12px;">
                    <span class="mlr-data-label">وضعیت پرداخت تأمین‌کننده مالی</span>
                    <span class="mlr-data-value mlr-financier-payment-status-value"><?php echo esc_html($financier_payment_status_display); ?></span>
                </div>

                <?php if (!empty($transfer_receipt_files)): ?>
                    <div class="mlr-file-grid" style="margin-bottom:16px;">
                        <?php foreach ($transfer_receipt_files as $receipt_index => $receipt_url): ?>
                            <div class="mlr-file-item">
                                <div class="mlr-file-label">رسید واریزی <?php echo esc_html($receipt_index + 1); ?></div>
                                <a href="<?php echo esc_url($receipt_url); ?>" target="_blank" rel="noopener" class="mlr-btn mlr-btn-secondary mlr-view-file-btn" style="padding: 5px 10px; font-size: 12px;">مشاهده فایل</a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="mlr-data-row" style="justify-content:flex-start;">
                        <span class="mlr-data-label">هنوز رسید واریزی برای این پرونده بارگذاری نشده است.</span>
                    </div>
                <?php endif; ?>

                <form id="mlr-transfer-receipts-form" class="mlr-inline-settings-form" enctype="multipart/form-data" data-request-id="<?php echo esc_attr($request->id); ?>">
                    <input type="hidden" name="action" value="mlr_save_transfer_receipts">
                    <input type="hidden" name="request_id" value="<?php echo esc_attr($request->id); ?>">
                    <input type="hidden" name="nonce" value="<?php echo esc_attr(wp_create_nonce('mlr_ajax_nonce_action')); ?>">
                    <div class="mlr-inline-settings-grid">
                        <div class="mlr-form-group">
                            <label for="mlr-transfer-receipts-input">بارگذاری رسید واریزی</label>
                            <input id="mlr-transfer-receipts-input" type="file" name="transfer_receipt_files[]" class="mlr-glass-input" accept=".pdf,.jpg,.jpeg,.png,.webp" multiple>
                            <p class="description" style="margin:8px 0 0;">امکان بارگذاری چند فایل PDF یا تصویر به‌صورت هم‌زمان وجود دارد. فایل‌های جدید به رسیدهای فعلی اضافه می‌شوند.</p>
                        </div>
                        <div class="mlr-form-group">
                            <label for="mlr-financier-payment-status-select">پرداخت تأمین‌کننده مالی</label>
                            <select id="mlr-financier-payment-status-select" name="financier_payment_status" class="mlr-glass-input mlr-financier-payment-status-select" data-current="<?php echo esc_attr($financier_payment_status_display); ?>">
                                <?php foreach ($financier_payment_status_options as $financier_payment_option): ?>
                                    <option value="<?php echo esc_attr($financier_payment_option); ?>" <?php selected($financier_payment_status_display, $financier_payment_option); ?>><?php echo esc_html($financier_payment_option); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description" style="margin:8px 0 0;">این فیلد فقط برای کارشناس مالی و سوپرادمین قابل ویرایش است.</p>
                        </div>
                    </div>
                    <div class="mlr-inline-settings-actions">
                        <button type="submit" class="mlr-btn mlr-btn-primary">ذخیره رسیدهای واریزی</button>
                        <span class="mlr-transfer-receipts-msg"></span>
                    </div>
                </form>
            </div>
        <?php endif; ?>

        <?php if (!empty($extra_files) && is_array($extra_files)): ?>
            <div class="mlr-glass-card">
                <h4 class="mlr-card-title"><span class="dashicons dashicons-paperclip"></span> فایل‌های تکمیلی</h4>
                <div class="mlr-file-grid">
                    <?php foreach ($extra_files as $fkey => $fval):
                        if (empty($fval)) { continue; }
                        $label = isset($extra_file_labels[$fkey]) ? $extra_file_labels[$fkey] : (function_exists('mlr_get_field_label') ? mlr_get_field_label($fkey) : ucfirst(str_replace('_',' ', $fkey)));
                    ?>
                        <div class="mlr-file-item">
                            <div class="mlr-file-label"><?php echo esc_html($label); ?></div>
                            <?php if (is_array($fval)): ?>
                                <div class="mlr-file-actions">
                                    <?php foreach ($fval as $idx => $url): if (empty($url)) { continue; } ?>
                                        <a href="<?php echo esc_url($url); ?>" target="_blank" class="mlr-btn mlr-btn-secondary mlr-view-file-btn" style="padding: 5px 10px; font-size: 12px;">
                                            مشاهده <?php echo intval($idx)+1; ?>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <a href="<?php echo esc_url($fval); ?>" target="_blank" class="mlr-btn mlr-btn-secondary mlr-view-file-btn" style="padding: 5px 10px; font-size: 12px;">مشاهده فایل</a>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- پیوست‌های مرحله در انتظار مدارک (برای نمایش به پذیرنده در پاپ‌آپ جزئیات) -->
        <?php
        $pending_slots = ['invoice' => 'فاکتور', 'contract' => 'قرارداد', 'doc3' => 'پیوست ۳', 'doc4' => 'پیوست ۴', 'doc5' => 'پیوست ۵'];
        $pending_current = is_array($pending_docs_attachments) ? $pending_docs_attachments : [];
        ?>
        <div class="mlr-glass-card mlr-pending-docs-card" style="margin-top: 20px;">
            <h4 class="mlr-card-title"><span class="dashicons dashicons-media-default"></span> پیوست‌های مرحله در انتظار مدارک</h4>
            <p class="mlr-pending-docs-desc">این فایل‌ها و تصاویر در پاپ‌آپ جزئیات پرونده برای پذیرنده (در فرانت و شورتکدها) در کنار فاکتور و قرارداد نمایش داده می‌شوند.</p>
            <form id="mlr-pending-docs-attachments-form" class="mlr-pending-docs-form" enctype="multipart/form-data" data-request-id="<?php echo esc_attr($request->id); ?>">
                <input type="hidden" name="action" value="mlr_save_pending_docs_attachments">
                <input type="hidden" name="request_id" value="<?php echo esc_attr($request->id); ?>">
                <input type="hidden" name="nonce" value="<?php echo esc_attr(wp_create_nonce('mlr_ajax_nonce_action')); ?>">
                <div class="mlr-pending-docs-list">
                    <?php foreach ($pending_slots as $pkey => $plabel):
                        $cur = isset($pending_current[$pkey]) && is_array($pending_current[$pkey]) ? $pending_current[$pkey] : [];
                        $cur_url = $cur['url'] ?? '';
                        $cur_label = $cur['label'] ?? $plabel;
                    ?>
                        <div class="mlr-pending-docs-row" data-key="<?php echo esc_attr($pkey); ?>">
                            <div class="mlr-pending-docs-row-inner">
                                <div class="mlr-pending-docs-field mlr-pending-docs-label">
                                    <label>عنوان</label>
                                    <input type="text" name="pending_doc_label_<?php echo esc_attr($pkey); ?>" class="mlr-glass-input" value="<?php echo esc_attr($cur_label); ?>" placeholder="<?php echo esc_attr($plabel); ?>">
                                </div>
                                <div class="mlr-pending-docs-field mlr-pending-docs-file">
                                    <label>فایل / تصویر</label>
                                    <div class="mlr-pending-docs-file-wrap">
                                        <?php if ($cur_url): ?>
                                            <a href="<?php echo esc_url($cur_url); ?>" target="_blank" rel="noopener" class="mlr-view-file-btn mlr-pending-docs-current-link">مشاهده فایل فعلی</a>
                                        <?php endif; ?>
                                        <input type="file" name="pending_doc_file_<?php echo esc_attr($pkey); ?>" class="mlr-glass-input" accept=".pdf,.jpg,.jpeg,.png,.gif,.webp">
                                    </div>
                                </div>
                                <div class="mlr-pending-docs-field mlr-pending-docs-remove">
                                    <label>حذف پیوست</label>
                                    <input type="hidden" name="pending_doc_remove_<?php echo esc_attr($pkey); ?>" value="0" class="mlr-pending-doc-remove-input">
                                    <button type="button" class="mlr-pending-doc-remove-toggle" role="switch" aria-checked="false" title="روشن: این پیوست حذف می‌شود. خاموش: نگه داشته می‌شود.">
                                        <span class="mlr-toggle-track"><span class="mlr-toggle-thumb"></span></span>
                                        <span class="mlr-toggle-label">خاموش</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="mlr-pending-docs-actions">
                    <button type="submit" class="mlr-btn mlr-btn-primary">ذخیره پیوست‌ها</button>
                    <span class="mlr-pending-docs-msg"></span>
                </div>
            </form>
        </div>

        <!-- اطلاعات و مدارک ضامن: بعد از ذخیره فقط نمایش فقط‌خواندنی -->
        <?php 
        $admin_has_guarantor = !empty($guarantor_info);
        $admin_guarantor_text_labels = [
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
        $admin_guarantor_doc_labels = [
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
        ?>
        <details class="mlr-glass-card mlr-collapsible-card" style="margin-top: 20px;">
            <summary class="mlr-card-title" style="cursor:pointer; list-style:none;"><span class="dashicons dashicons-groups"></span> اطلاعات و مدارک ضامن</summary>
            <div class="mlr-collapsible-content" style="margin-top: 14px;">
            <?php if ($admin_has_guarantor): ?>
                <div class="mlr-data-grid" style="display:grid; grid-template-columns: 1fr 1fr; gap: 10px 20px;">
                    <?php foreach ($admin_guarantor_text_labels as $k => $l): if (isset($guarantor_info[$k]) && (string)$guarantor_info[$k] !== ''): ?>
                        <div class="mlr-data-row"><span class="mlr-data-label"><?php echo esc_html($l); ?></span><span class="mlr-data-value"><?php echo esc_html($guarantor_info[$k]); ?></span></div>
                    <?php endif; endforeach; ?>
                </div>
                <h5 style="margin: 15px 0 8px; border-bottom: 1px solid #eee; padding-bottom: 5px;">مدارک ضامن</h5>
                <div style="margin-top: 8px;">
                    <?php foreach ($admin_guarantor_doc_labels as $gkey => $glabel): if (!empty($guarantor_info[$gkey]) && is_string($guarantor_info[$gkey]) && strpos($guarantor_info[$gkey], 'http') === 0): ?>
                        <a href="<?php echo esc_url($guarantor_info[$gkey]); ?>" target="_blank" rel="noopener" class="mlr-view-file-btn" style="margin:2px 8px 2px 0;"><?php echo esc_html($glabel); ?></a>
                    <?php endif; endforeach; ?>
                </div>
            <?php else: ?>
                <?php $admin_guarantor_prefix = preg_replace('/[^a-z0-9_]/i', '', $wpdb->prefix); ?>
                <form id="mlr-guarantor-form" enctype="multipart/form-data" class="mlr-guarantor-form-admin" data-portal-context="auto" data-request-id="<?php echo esc_attr($request->id); ?>" data-blog-id="<?php echo esc_attr(function_exists('get_current_blog_id') ? get_current_blog_id() : 1); ?>" data-db-prefix="<?php echo esc_attr($admin_guarantor_prefix); ?>">
                    <input type="hidden" name="action" value="mlr_save_guarantor_info">
                    <input type="hidden" name="request_id" value="<?php echo esc_attr($request->id); ?>">
                    <input type="hidden" name="blog_id" value="<?php echo esc_attr(function_exists('get_current_blog_id') ? get_current_blog_id() : 1); ?>">
                    <input type="hidden" name="db_prefix" value="<?php echo esc_attr($admin_guarantor_prefix); ?>">
                    <div class="mlr-grid-2" style="display:grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                        <div class="mlr-form-group"><label>نام و نام خانوادگی</label><input type="text" name="full_name" class="mlr-glass-input" value=""></div>
                        <div class="mlr-form-group"><label>کد ملی</label><input type="text" name="national_code" class="mlr-glass-input" value=""></div>
                        <div class="mlr-form-group"><label>نام پدر</label><input type="text" name="father_name" class="mlr-glass-input" value=""></div>
                        <div class="mlr-form-group"><label>شماره شناسنامه</label><input type="text" name="id_number" class="mlr-glass-input" value=""></div>
                        <div class="mlr-form-group"><label>صادره از</label><input type="text" name="issued_from" class="mlr-glass-input" value=""></div>
                        <div class="mlr-form-group"><label>تاریخ تولد</label><input type="text" name="birth_date" class="mlr-glass-input" value=""></div>
                        <div class="mlr-form-group"><label>شماره موبایل</label><input type="text" name="mobile" class="mlr-glass-input" value=""></div>
                        <div class="mlr-form-group"><label>تلفن ثابت</label><input type="text" name="phone" class="mlr-glass-input" value=""></div>
                        <div class="mlr-form-group"><label>شغل</label><input type="text" name="job" class="mlr-glass-input" value=""></div>
                        <div class="mlr-form-group"><label>کد پستی</label><input type="text" name="postal_code" class="mlr-glass-input" value=""></div>
                        <div class="mlr-form-group" style="grid-column: 1 / -1;"><label>آدرس سکونت</label><textarea name="address" class="mlr-glass-input" rows="2"></textarea></div>
                    </div>
                    <h5 style="margin: 15px 0 10px; border-bottom: 1px solid #eee; padding-bottom: 5px;">مدارک ضامن</h5>
                    <div class="mlr-grid-2" style="display:grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                        <?php foreach ($admin_guarantor_doc_labels as $gkey => $glabel): ?>
                            <div class="mlr-form-group">
                                <label><?php echo esc_html($glabel); ?></label>
                                <input type="file" name="<?php echo esc_attr($gkey); ?>" class="mlr-glass-input" accept=".jpg,.jpeg,.png,.webp,.pdf,image/jpeg,image/png,image/webp,application/pdf">
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div style="margin-top: 15px; display:flex; gap:10px; align-items:center;">
                        <button type="submit" class="mlr-btn mlr-btn-primary">ذخیره اطلاعات و مدارک ضامن</button>
                        <span class="mlr-guarantor-msg" style="font-size:0.9em;"></span>
                    </div>
                </form>
            <?php endif; ?>
            </div>
        </details>

        <!-- اطلاعات و مدارک ضامن دوم (اختیاری) -->
        <?php
        $admin_has_guarantor_2 = !empty($guarantor_info_2);
        ?>
        <details class="mlr-glass-card mlr-collapsible-card" style="margin-top: 20px;">
            <summary class="mlr-card-title" style="cursor:pointer; list-style:none;"><span class="dashicons dashicons-groups"></span> اطلاعات و مدارک ضامن دوم (اختیاری)</summary>
            <div class="mlr-collapsible-content" style="margin-top: 14px;">
            <?php if ($admin_has_guarantor_2): ?>
                <div class="mlr-data-grid" style="display:grid; grid-template-columns: 1fr 1fr; gap: 10px 20px;">
                    <?php foreach ($admin_guarantor_text_labels as $k => $l): if (isset($guarantor_info_2[$k]) && (string)$guarantor_info_2[$k] !== ''): ?>
                        <div class="mlr-data-row"><span class="mlr-data-label"><?php echo esc_html($l); ?></span><span class="mlr-data-value"><?php echo esc_html($guarantor_info_2[$k]); ?></span></div>
                    <?php endif; endforeach; ?>
                </div>
                <h5 style="margin: 15px 0 8px; border-bottom: 1px solid #eee; padding-bottom: 5px;">مدارک ضامن دوم</h5>
                <div style="margin-top: 8px;">
                    <?php foreach ($admin_guarantor_doc_labels as $gkey => $glabel): if (!empty($guarantor_info_2[$gkey]) && is_string($guarantor_info_2[$gkey]) && strpos($guarantor_info_2[$gkey], 'http') === 0): ?>
                        <a href="<?php echo esc_url($guarantor_info_2[$gkey]); ?>" target="_blank" rel="noopener" class="mlr-view-file-btn" style="margin:2px 8px 2px 0;"><?php echo esc_html($glabel); ?></a>
                    <?php endif; endforeach; ?>
                </div>
            <?php else: ?>
                <?php $admin_guarantor_prefix = preg_replace('/[^a-z0-9_]/i', '', $wpdb->prefix); ?>
                <form id="mlr-guarantor-form-2" enctype="multipart/form-data" class="mlr-guarantor-form-admin" data-portal-context="auto" data-request-id="<?php echo esc_attr($request->id); ?>" data-blog-id="<?php echo esc_attr(function_exists('get_current_blog_id') ? get_current_blog_id() : 1); ?>" data-db-prefix="<?php echo esc_attr($admin_guarantor_prefix); ?>">
                    <input type="hidden" name="action" value="mlr_save_guarantor_info">
                    <input type="hidden" name="guarantor_index" value="2">
                    <input type="hidden" name="request_id" value="<?php echo esc_attr($request->id); ?>">
                    <input type="hidden" name="blog_id" value="<?php echo esc_attr(function_exists('get_current_blog_id') ? get_current_blog_id() : 1); ?>">
                    <input type="hidden" name="db_prefix" value="<?php echo esc_attr($admin_guarantor_prefix); ?>">
                    <div class="mlr-grid-2" style="display:grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                        <div class="mlr-form-group"><label>نام و نام خانوادگی</label><input type="text" name="full_name" class="mlr-glass-input" value=""></div>
                        <div class="mlr-form-group"><label>کد ملی</label><input type="text" name="national_code" class="mlr-glass-input" value=""></div>
                        <div class="mlr-form-group"><label>نام پدر</label><input type="text" name="father_name" class="mlr-glass-input" value=""></div>
                        <div class="mlr-form-group"><label>شماره شناسنامه</label><input type="text" name="id_number" class="mlr-glass-input" value=""></div>
                        <div class="mlr-form-group"><label>صادره از</label><input type="text" name="issued_from" class="mlr-glass-input" value=""></div>
                        <div class="mlr-form-group"><label>تاریخ تولد</label><input type="text" name="birth_date" class="mlr-glass-input" value=""></div>
                        <div class="mlr-form-group"><label>شماره موبایل</label><input type="text" name="mobile" class="mlr-glass-input" value=""></div>
                        <div class="mlr-form-group"><label>تلفن ثابت</label><input type="text" name="phone" class="mlr-glass-input" value=""></div>
                        <div class="mlr-form-group"><label>شغل</label><input type="text" name="job" class="mlr-glass-input" value=""></div>
                        <div class="mlr-form-group"><label>کد پستی</label><input type="text" name="postal_code" class="mlr-glass-input" value=""></div>
                        <div class="mlr-form-group" style="grid-column: 1 / -1;"><label>آدرس سکونت</label><textarea name="address" class="mlr-glass-input" rows="2"></textarea></div>
                    </div>
                    <h5 style="margin: 15px 0 10px; border-bottom: 1px solid #eee; padding-bottom: 5px;">مدارک ضامن دوم</h5>
                    <div class="mlr-grid-2" style="display:grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                        <?php foreach ($admin_guarantor_doc_labels as $gkey => $glabel): ?>
                            <div class="mlr-form-group">
                                <label><?php echo esc_html($glabel); ?></label>
                                <input type="file" name="<?php echo esc_attr($gkey); ?>" class="mlr-glass-input" accept=".jpg,.jpeg,.png,.webp,.pdf,image/jpeg,image/png,image/webp,application/pdf">
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div style="margin-top: 15px; display:flex; gap:10px; align-items:center;">
                        <button type="submit" class="mlr-btn mlr-btn-primary">ذخیره اطلاعات و مدارک ضامن دوم</button>
                        <span class="mlr-guarantor-msg" style="font-size:0.9em;"></span>
                    </div>
                </form>
            <?php endif; ?>
            </div>
        </details>

        <?php if (!empty($sections['marketplace'])): $section = $sections['marketplace']; ?>
            <div class="mlr-glass-card">
                <h4 class="mlr-card-title"><span class="dashicons <?php echo esc_attr($section['icon']); ?>"></span> مدیریت اتصال پذیرنده</h4>
                <div class="mlr-data-grid">
                    <?php foreach ($section['fields'] as $field): ?>
                        <?php if (empty($field)) continue; ?>
                        <div class="mlr-data-row <?php echo !empty($field['wide']) ? 'mlr-row-wide' : ''; ?>">
                            <span class="mlr-data-label"><?php echo esc_html($field['label']); ?></span>
                            <span class="mlr-data-value"><?php echo esc_html($field['value']); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php if (current_user_can('manage_options') && !empty($marketplace_admin_data)): ?>
                    <form id="mlr-marketplace-admin-form" class="mlr-marketplace-admin-form" data-request-id="<?php echo esc_attr($request->id); ?>">
                        <div class="mlr-marketplace-admin-grid">
                            <div class="mlr-marketplace-admin-field">
                                <label for="mlr-marketplace-admin-action">عملیات اتصال</label>
                                <select id="mlr-marketplace-admin-action" name="marketplace_admin_action" class="mlr-glass-input mlr-marketplace-admin-action">
                                    <option value="keep">فقط ذخیره وضعیت فعلی</option>
                                    <option value="assign_acceptor">اتصال یا تغییر پذیرنده</option>
                                    <option value="reopen_marketplace">بازگشایی در بازار آزاد</option>
                                    <option value="close_marketplace">بستن بازار آزاد</option>
                                </select>
                            </div>

                            <div class="mlr-marketplace-admin-field mlr-marketplace-acceptor-picker" data-request-id="<?php echo esc_attr($request->id); ?>" data-plan-id="<?php echo esc_attr($marketplace_admin_data['plan_id']); ?>" style="display:none;">
                                <label for="mlr-marketplace-acceptor-search-<?php echo esc_attr($request->id); ?>">جستجوی پذیرنده</label>
                                <input type="hidden" name="selected_acceptor_id" class="mlr-marketplace-selected-acceptor-id" value="<?php echo esc_attr($marketplace_admin_data['acceptor_id']); ?>">
                                <input type="text" id="mlr-marketplace-acceptor-search-<?php echo esc_attr($request->id); ?>" class="mlr-glass-input mlr-marketplace-acceptor-search" value="" placeholder="نام پذیرنده، فروشگاه، نام کاربری یا ایمیل..." autocomplete="off">
                                <div class="mlr-marketplace-selected-acceptor">
                                    <?php if (!empty($marketplace_admin_data['acceptor_id'])): ?>
                                        پذیرنده فعلی: <?php echo esc_html(($marketplace_admin_data['acceptor_name'] ?: 'پذیرنده') . ' (#' . $marketplace_admin_data['acceptor_id'] . ')'); ?>
                                    <?php else: ?>
                                        هنوز پذیرنده‌ای انتخاب نشده است.
                                    <?php endif; ?>
                                </div>
                                <div class="mlr-marketplace-acceptor-results" aria-live="polite"></div>
                            </div>
                        </div>

                        <input type="hidden" name="requested_service_scope" value="<?php echo esc_attr($marketplace_admin_data['scope']); ?>">
                        <?php foreach ((array) $marketplace_admin_data['location_ids'] as $location_id): ?>
                            <input type="hidden" name="requested_location_terms[]" value="<?php echo esc_attr(absint($location_id)); ?>">
                        <?php endforeach; ?>
                        <?php foreach ((array) $marketplace_admin_data['category_ids'] as $category_id): ?>
                            <input type="hidden" name="requested_activity_categories[]" value="<?php echo esc_attr(absint($category_id)); ?>">
                        <?php endforeach; ?>
                        <input type="hidden" name="request_public_summary" value="<?php echo esc_attr($marketplace_admin_data['summary']); ?>">

                        <div class="mlr-marketplace-admin-actions">
                            <button type="submit" class="mlr-btn mlr-btn-primary">ذخیره اتصال پذیرنده</button>
                            <span class="mlr-marketplace-admin-msg"></span>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($credit_report): ?>
            <div class="mlr-glass-card mlr-report-container" style="padding: 0; overflow: hidden;">
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

                    <style>
                        .mlr-report-container { font-family: 'Vazirmatn', Tahoma, sans-serif; direction: rtl; background: #fff; margin-top: 20px; }
                        .mlr-report-header { display: flex; align-items: center; padding: 20px; color: #fff; border-radius: 12px; margin-bottom: 20px; }
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
                        
                        .mlr-report-section { border: 1px solid rgba(29, 53, 88, 0.1); border-radius: 12px; margin-bottom: 20px; overflow: hidden; }
                        .mlr-report-section h4 { background: rgba(208, 226, 242, 0.3); padding: 12px 15px; margin: 0; border-bottom: 1px solid rgba(29, 53, 88, 0.1); font-size: 14px; font-weight: 700; color: var(--mlr-blue-dark); display: flex; align-items: center; gap: 8px; }
                        .mlr-report-content { padding: 15px; }
                        
                        .mlr-summary-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 10px; }
                        .mlr-summary-card { background: rgba(208, 226, 242, 0.4); padding: 15px; border-radius: 10px; text-align: center; border: 1px solid rgba(255,255,255,0.6); }
                        .mlr-summary-val { font-size: 16px; font-weight: 800; color: var(--mlr-blue-dark); display: block; margin-bottom: 4px; }
                        .mlr-summary-lbl { font-size: 11px; color: rgba(29, 53, 88, 0.7); }
                        
                        .mlr-table { width: 100%; border-collapse: collapse; font-size: 12px; }
                        .mlr-table th { background: #f8fafc; padding: 10px; text-align: right; color: #64748b; border-bottom: 1px solid #e2e8f0; white-space: nowrap; }
                        .mlr-table td { padding: 10px; border-bottom: 1px solid #f1f5f9; color: #334155; }
                        .mlr-table tr:last-child td { border-bottom: none; }

                        .mlr-cards-container { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 15px; }
                        .mlr-contract-card { border: 1px solid rgba(29, 53, 88, 0.15); border-radius: 12px; overflow: hidden; background: #fff; }
                        .mlr-contract-header { background: rgba(208, 226, 242, 0.6); padding: 10px 15px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid rgba(29, 53, 88, 0.1); }
                        .mlr-contract-org { font-weight: 700; font-size: 13px; color: var(--mlr-blue-dark); }
                        .mlr-contract-badge { font-size: 11px; padding: 3px 8px; border-radius: 12px; color: #fff; font-weight: 600; }
                        .mlr-contract-body { padding: 15px; font-size: 13px; }
                        .mlr-contract-row { display: flex; justify-content: space-between; margin-bottom: 8px; border-bottom: 1px dashed #f1f5f9; padding-bottom: 4px; }
                        .mlr-contract-row:last-child { margin-bottom: 0; border-bottom: none; }
                        
                        .mlr-badge { padding: 3px 8px; border-radius: 12px; font-size: 11px; color: #fff; display: inline-block; }
                        .mlr-badge-success { background: #10b981; }
                        .mlr-badge-warning { background: #f59e0b; }
                        .mlr-badge-danger { background: #ef4444; }
                    </style>
            </div>
        <?php endif; ?>

        <div class="mlr-glass-card">
            <h4 class="mlr-card-title"><span class="dashicons dashicons-list-view"></span> مراحل و رویدادهای پرونده</h4>
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
        .mlr-inline-settings-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 360px)); gap: 16px; }
        .mlr-inline-settings-actions { display: flex; align-items: center; gap: 10px; margin-top: 14px; flex-wrap: wrap; }
        .mlr-document-receipt-status-msg { font-size: 13px; }
        .mlr-marketplace-admin-form { margin-top: 18px; padding-top: 18px; border-top: 1px solid rgba(29, 53, 88, 0.08); }
        .mlr-marketplace-admin-grid { display: grid; grid-template-columns: minmax(180px, 260px) minmax(260px, 1fr); gap: 14px; align-items: start; }
        .mlr-marketplace-admin-field label { display: block; margin-bottom: 6px; color: rgba(29, 53, 88, 0.75); font-weight: 700; font-size: 13px; }
        .mlr-marketplace-selected-acceptor { margin-top: 8px; padding: 8px 10px; border-radius: 8px; background: rgba(29, 53, 88, 0.06); color: var(--mlr-blue-dark); font-size: 13px; line-height: 1.7; }
        .mlr-marketplace-acceptor-results { margin-top: 8px; display: grid; gap: 6px; }
        .mlr-marketplace-acceptor-result { width: 100%; text-align: right; border: 1px solid rgba(29, 53, 88, 0.12); border-radius: 8px; padding: 9px 10px; background: rgba(255,255,255,0.86); color: var(--mlr-blue-dark); cursor: pointer; }
        .mlr-marketplace-acceptor-result:hover { border-color: var(--mlr-blue-dark); background: #fff; }
        .mlr-marketplace-acceptor-result small { display: block; margin-top: 3px; color: rgba(29, 53, 88, 0.62); }
        .mlr-marketplace-admin-actions { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin-top: 14px; }
        .mlr-marketplace-admin-msg { font-size: 13px; }

        .mlr-data-row { display: flex; justify-content: space-between; margin-bottom: 12px; font-size: 14px; border-bottom: 1px dashed rgba(29, 53, 88, 0.1); padding-bottom: 8px; }
        .mlr-data-row:last-child { border-bottom: none; margin-bottom: 0; }
        .mlr-data-label { color: rgba(29, 53, 88, 0.7); }
        .mlr-data-value { font-weight: 600; color: var(--mlr-blue-dark); text-align: left; }
        .mlr-row-wide { flex-direction: column; gap: 5px; align-items: flex-start; }

        /* Inputs & Buttons */
        .mlr-glass-input { width: 100%; padding: 10px; border: 1px solid rgba(29, 53, 88, 0.2); border-radius: 8px; background: rgba(255,255,255,0.8); transition: all 0.3s; }
        .mlr-glass-input:focus { background: #fff; border-color: var(--mlr-blue-dark); outline: none; box-shadow: 0 0 0 3px rgba(29, 53, 88, 0.1); }
        
        .mlr-btn { padding: 10px 20px; border-radius: 8px; border: none; cursor: pointer; font-weight: 700; transition: all 0.3s; display: inline-flex; align-items: center; justify-content: center; gap: 5px; }
        .mlr-btn-primary { background: var(--mlr-blue-dark); color: #fff; }
        .mlr-btn-primary:hover { background: var(--mlr-gold); color: var(--mlr-blue-dark); }
        .mlr-btn-secondary { background: rgba(255,255,255,0.6); color: var(--mlr-blue-dark); border: 1px solid rgba(29, 53, 88, 0.1); }
        .mlr-btn-secondary:hover { background: #fff; }

        /* Files */
        .mlr-file-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 10px; }
        .mlr-file-item { background: #fff; padding: 10px; border-radius: 8px; border: 1px solid rgba(29, 53, 88, 0.1); text-align: center; }
        .mlr-file-label { font-size: 12px; margin-bottom: 5px; color: rgba(29, 53, 88, 0.7); }

        /* Comments */
        .mlr-comment { background: rgba(255,255,255,0.6); padding: 12px; border-radius: 8px; margin-bottom: 10px; border: 1px solid rgba(29, 53, 88, 0.05); }
        .mlr-conversation-history { max-height: 300px; overflow-y: auto; margin-bottom: 15px; padding-right: 5px; }
        .mlr-conversation-compose { border-top: 1px solid rgba(29, 53, 88, 0.1); padding-top: 15px; }
        .mlr-conversation-actions { display: flex; gap: 10px; margin-top: 10px; align-items: center; }

        @media (max-width: 768px) {
            .mlr-glass-wrapper { padding: 15px; }
            .mlr-data-grid { grid-template-columns: 1fr; }
            .mlr-marketplace-admin-grid { grid-template-columns: 1fr; }
        }
    </style>
    <?php

    $html_output = ob_get_clean();
    wp_send_json_success(['html' => $html_output]);
}
?>
