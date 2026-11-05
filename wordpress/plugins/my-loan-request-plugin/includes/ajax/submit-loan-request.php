<?php
// FILE: my-loan-request-plugin/includes/ajax/submit-loan-request.php
if (!defined('ABSPATH')) {
    exit;
}

add_action('wp_ajax_mlr_submit_loan_request', 'mlr_submit_loan_request_callback');
add_action('wp_ajax_nopriv_mlr_submit_loan_request', 'mlr_submit_loan_request_callback'); // جهت اطمینان برای کاربران لاگین نشده (اگر نیاز است)

// Helper function for normalizing uploaded files array
if (!function_exists('mlr_rearray_files')) {
    function mlr_rearray_files(&$file_post) {
        $file_ary = array();
        $file_count = count($file_post['name']);
        $file_keys = array_keys($file_post);

        for ($i = 0; $i < $file_count; $i++) {
            foreach ($file_keys as $key) {
                $file_ary[$i][$key] = $file_post[$key][$i];
            }
        }
        return $file_ary;
    }
}

function mlr_submit_loan_request_callback() {
    check_ajax_referer('mlr_loan_request_action', '_wpnonce');
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'برای ثبت درخواست، لطفاً ابتدا وارد شوید.']);
        return;
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'loan_requests';
    $plans_table = $wpdb->prefix . 'installment_plans';
    $user_id = get_current_user_id();
    if (!function_exists('mlr_user_has_role') || !mlr_user_has_role($user_id, 'acceptor')) {
        wp_send_json_error(['message' => 'این مسیر ثبت فقط برای حساب پذیرنده مجاز است. لطفاً از فرم متقاضی استفاده کنید.']);
        return;
    }
    $errors = [];

    $mobile_number = function_exists('mlr_normalize_digits') ? mlr_normalize_digits($_POST['mobile_number'] ?? '', true) : preg_replace('/\D+/', '', (string) ($_POST['mobile_number'] ?? ''));
    $national_code = function_exists('mlr_normalize_digits') ? mlr_normalize_digits($_POST['national_code'] ?? '', true) : preg_replace('/\D+/', '', (string) ($_POST['national_code'] ?? ''));
    $phone_number = sanitize_text_field(wp_unslash($_POST['phone_number'] ?? ''));
    if ($phone_number === '') {
        $phone_number = $mobile_number;
    }

    if (!function_exists('mlr_validate_iranian_national_code') || !mlr_validate_iranian_national_code($national_code)) {
        wp_send_json_error(['message' => 'کد ملی وارد شده معتبر نمی‌باشد.']);
        return;
    }
    if (!preg_match('/^09\d{9}$/', $mobile_number)) {
        wp_send_json_error(['message' => 'شماره موبایل وارد شده معتبر نیست.']);
        return;
    }

    $full_name = trim(sanitize_text_field(wp_unslash($_POST['full_name'] ?? '')));
    $father_name = trim(sanitize_text_field(wp_unslash($_POST['father_name'] ?? '')));
    $certificate_number = trim(sanitize_text_field(wp_unslash($_POST['certificate_number'] ?? '')));
    $birth_date_jalali = sanitize_text_field(wp_unslash($_POST['birth_date'] ?? ''));
    $postal_code = function_exists('mlr_normalize_digits') ? mlr_normalize_digits($_POST['postal_code'] ?? '', true) : preg_replace('/\D+/', '', (string) ($_POST['postal_code'] ?? ''));
    $residential_address = trim(sanitize_textarea_field(wp_unslash($_POST['residential_address'] ?? '')));
    $work_address = trim(sanitize_textarea_field(wp_unslash($_POST['work_address'] ?? '')));

    if ($full_name === '' || $father_name === '' || $certificate_number === '' || $birth_date_jalali === '') {
        wp_send_json_error(['message' => 'نام و نام خانوادگی، نام پدر، شماره شناسنامه و تاریخ تولد الزامی است.']);
        return;
    }
    if (!preg_match('/^\d{10}$/', $postal_code)) {
        wp_send_json_error(['message' => 'کد پستی ۱۰ رقمی معتبر وارد کنید.']);
        return;
    }
    if ($residential_address === '' || $work_address === '') {
        wp_send_json_error(['message' => 'آدرس محل سکونت و آدرس محل فعالیت الزامی است.']);
        return;
    }

    $birth_date_gregorian = function_exists('mlr_jalali_to_gregorian') ? mlr_jalali_to_gregorian($birth_date_jalali) : $birth_date_jalali;
    $loan_amount = function_exists('mlr_parse_decimal_number') ? mlr_parse_decimal_number($_POST['loan_amount'] ?? 0) : (float) ($_POST['loan_amount'] ?? 0);
    $plan_id = absint($_POST['installment_plan_id'] ?? 0);
    $installment_count = absint($_POST['installment_count'] ?? 0);
    $installment_interval = max(1, absint($_POST['installment_interval'] ?? 1));

    $canonical_order = function_exists('mlr_build_clean_order_items') ? mlr_build_clean_order_items($_POST['order_items'] ?? '[]') : null;
    if (!$canonical_order || is_wp_error($canonical_order)) {
        wp_send_json_error(['message' => is_wp_error($canonical_order) ? $canonical_order->get_error_message() : 'اطلاعات محصولات معتبر نیست.']);
        return;
    }
    $cleaned_items = $canonical_order['items'];
    $order_total = (float) $canonical_order['total'];
    if ($loan_amount <= 0 || abs($order_total - $loan_amount) > 1) {
        wp_send_json_error(['message' => 'جمع مبلغ محصولات باید با مبلغ درخواستی برابر باشد.']);
        return;
    }

    if (!function_exists('mlr_user_can_access_installment_plan') || !mlr_user_can_access_installment_plan($user_id, $plan_id, true, 'mlr_loan_request_form')) {
        wp_send_json_error(['message' => 'طرح انتخاب‌شده برای حساب شما مجاز نیست یا در دسترس نمی‌باشد.']);
        return;
    }
    $plan = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$plans_table} WHERE id = %d AND is_active = 1", $plan_id));
    $plan_validation = function_exists('mlr_validate_plan_request_values') ? mlr_validate_plan_request_values($plan, $loan_amount, $installment_count, $installment_interval) : null;
    if (!$plan || is_wp_error($plan_validation)) {
        wp_send_json_error(['message' => is_wp_error($plan_validation) ? $plan_validation->get_error_message() : 'طرح انتخاب‌شده فعال نیست یا پیدا نشد.']);
        return;
    }
    $settings = is_array($plan_validation) ? $plan_validation['settings'] : maybe_unserialize($plan->settings);
    if (!is_array($settings)) {
        $settings = [];
    }
    if (is_array($plan_validation)) {
        $installment_interval = (int) $plan_validation['interval'];
    }

    $occupation_raw = sanitize_text_field(wp_unslash($_POST['occupation'] ?? ''));
    $occupation_key = sanitize_key($occupation_raw);
    if (function_exists('mlr_validate_plan_occupation')) {
        $occupation_validation = mlr_validate_plan_occupation($settings, $occupation_key);
        if (is_wp_error($occupation_validation)) {
            wp_send_json_error(['message' => $occupation_validation->get_error_message()]);
            return;
        }
    }
    $occupation_value = (function_exists('mlr_plan_uses_occupation_documents') && mlr_plan_uses_occupation_documents($settings))
        ? (function_exists('mlr_normalize_occupation_key') ? mlr_normalize_occupation_key($occupation_key) : $occupation_key)
        : $occupation_raw;
    $required_docs_keys = function_exists('mlr_get_plan_required_document_keys')
        ? mlr_get_plan_required_document_keys($plan->required_documents ?? [], $settings, $occupation_key)
        : maybe_unserialize($plan->required_documents ?? []);
    if (!is_array($required_docs_keys)) {
        $required_docs_keys = [];
    }
    $allowed_required_doc_keys = array_fill_keys(array_map('sanitize_key', $required_docs_keys), true);

    // Credit report must originate from a successful server-side flow. A raw
    // browser JSON payload is accepted only when its digest is covered by the
    // server-signed submission token returned after OTP/report completion.
    $credit_proof = function_exists('mlr_credit_get_report_proof') ? mlr_credit_get_report_proof($national_code, $mobile_number) : null;
    $signed_credit_proof = null;
    if ((!$credit_proof || empty($credit_proof['report_json'])) && function_exists('mlr_credit_validate_submission_token')) {
        $signed_credit_proof = mlr_credit_validate_submission_token(
            $_POST['credit_proof_token'] ?? '',
            $_POST['credit_report_json'] ?? '',
            $national_code,
            $mobile_number
        );
    }
    $trusted_credit_proof = ($credit_proof && !empty($credit_proof['report_json'])) ? $credit_proof : $signed_credit_proof;
    $credit_report_raw = '';
    $credit_proof_used = false;
    if (!empty($settings['requires_bank_validation'])) {
        if (!$trusted_credit_proof || empty($trusted_credit_proof['report_json'])) {
            wp_send_json_error(['message' => 'برای این طرح انجام اعتبارسنجی بانکی الزامی است. اعتبارسنجی ثبت‌شده معتبر یا در دسترس نیست؛ لطفاً مرحله اعتبارسنجی را دوباره انجام دهید.']);
            return;
        }
        $credit_report_raw = (string) $trusted_credit_proof['report_json'];
        $credit_proof_used = ($credit_proof && !empty($credit_proof['report_json']));
    } elseif ($trusted_credit_proof && !empty($trusted_credit_proof['report_json'])) {
        $credit_report_raw = (string) $trusted_credit_proof['report_json'];
        $credit_proof_used = ($credit_proof && !empty($credit_proof['report_json']));
    }

    $raw_post = $_POST;
    unset($raw_post['action'], $raw_post['_wpnonce'], $raw_post['nonce'], $raw_post['credit_report_json']);
    $sanitized_form_data = [];
    foreach ($raw_post as $k => $v) {
        $key = sanitize_key($k);
        if (is_array($v)) {
            $sanitized_form_data[$key] = array_map(static function($vv) {
                return is_scalar($vv) ? sanitize_text_field(wp_unslash($vv)) : '';
            }, $v);
        } else {
            $sanitized_form_data[$key] = is_scalar($v) ? sanitize_textarea_field(wp_unslash($v)) : '';
        }
    }
    if ($credit_report_raw !== '') {
        $sanitized_form_data['credit_report_json'] = $credit_report_raw;
    }
    if (function_exists('mlr_plan_uses_occupation_documents') && mlr_plan_uses_occupation_documents($settings)) {
        $sanitized_form_data['occupation'] = $occupation_value;
    }

    $data = [
        'user_id' => $user_id,
        'acceptor_user_id' => $user_id,
        'applicant_user_id' => null,
        'request_origin' => 'acceptor_portal',
        'full_name' => $full_name,
        'national_code' => $national_code,
        'mobile_number' => $mobile_number,
        'phone_number' => $phone_number,
        'birth_date' => $birth_date_gregorian,
        'occupation' => $occupation_value,
        'residential_address' => $residential_address,
        'postal_code' => $postal_code,
        'work_address' => $work_address,
        'product_name' => $canonical_order['summary'],
        'order_items' => wp_json_encode($cleaned_items, JSON_UNESCAPED_UNICODE),
        'order_total' => $order_total,
        'form_data' => wp_json_encode($sanitized_form_data, JSON_UNESCAPED_UNICODE),
        'credit_report_json' => $credit_report_raw,
        'installment_plan_id' => $plan_id,
        'loan_amount' => $loan_amount,
        'facility_amount' => $loan_amount,
        'installment_count' => $installment_count,
        'installment_interval' => $installment_interval,
        'status' => 'در حال بررسی',
        'settlement_status' => '',
        'created_at' => current_time('mysql'),
        'last_edited_at' => current_time('mysql'),
    ];

    // Preserve existing financial formulas; only inputs are now server-authoritative.
    $user_commission = get_user_meta($user_id, 'acceptor_commission_percent', true);
    $plugin_dir = defined('MLR_PLUGIN_DIR') ? MLR_PLUGIN_DIR : WP_PLUGIN_DIR . '/my-loan-request-plugin/';
    if (file_exists($plugin_dir . 'includes/loan-calculations.php')) {
        require_once $plugin_dir . 'includes/loan-calculations.php';
        $calc_results = [];
        if (strtolower(trim((string) $plan->type)) === 'market' && function_exists('calculateMarketLoan')) {
            $calc_results = calculateMarketLoan($loan_amount, $installment_count, $settings, $user_commission);
        } elseif (strtolower(trim((string) $plan->type)) === 'leasing' && function_exists('calculateLeasingLoan')) {
            $calc_results = calculateLeasingLoan($loan_amount, $installment_count, $installment_interval, $settings, $user_commission);
        }
        if (!empty($calc_results) && isset($calc_results['loanAmount'])) {
            $data['facility_amount'] = round((float) $calc_results['loanAmount']);
            $data['prepayment'] = $calc_results['prepayment'] ?? 0;
            $data['installment_amount'] = $calc_results['installmentAmount'] ?? 0;
            $data['wallet_charge'] = $calc_results['walletCharge'] ?? 0;
            $data['leasing_deposit_amount'] = $calc_results['netLeasingPayment'] ?? null;
            $data['acceptor_payment_amount'] = $calc_results['acceptorPayment'] ?? 0;
            $data['total_payment'] = $calc_results['totalPayment'] ?? 0;
            $data['profit'] = $calc_results['profit'] ?? 0;
            $data['final_settlement_amount'] = $data['acceptor_payment_amount'];
        }
    }

    // Prevent double-click/network retries from creating duplicate financial records.
    $duplicate_after = date('Y-m-d H:i:s', current_time('timestamp') - (5 * MINUTE_IN_SECONDS));
    $find_duplicate = static function() use ($wpdb, $table_name, $user_id, $plan_id, $loan_amount, $national_code, $duplicate_after) {
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table_name} WHERE user_id=%d AND installment_plan_id=%d AND ROUND(CAST(loan_amount AS DECIMAL(20,2)),2)=ROUND(%f,2) AND national_code=%s AND request_origin=%s AND created_at >= %s ORDER BY id DESC LIMIT 1",
            $user_id, $plan_id, $loan_amount, $national_code, 'acceptor_portal', $duplicate_after
        ));
    };
    $duplicate_id = $find_duplicate();
    if ($duplicate_id > 0) {
        wp_send_json_success(['message' => 'این درخواست قبلاً ثبت شده است.', 'request_id' => $duplicate_id, 'redirect_url' => esc_url(home_url('/panel/'))]);
        return;
    }
    $lock_key = 'mlr_acceptor_submit_lock_' . $user_id;
    if (get_transient($lock_key)) {
        $duplicate_id = $find_duplicate();
        if ($duplicate_id > 0) {
            wp_send_json_success(['message' => 'این درخواست قبلاً ثبت شده است.', 'request_id' => $duplicate_id, 'redirect_url' => esc_url(home_url('/panel/'))]);
            return;
        }
        wp_send_json_error(['message' => 'درخواست دیگری در حال ثبت است. لطفاً چند ثانیه بعد دوباره تلاش کنید.']);
        return;
    }
    set_transient($lock_key, 1, 30);
    $duplicate_id = $find_duplicate();
    if ($duplicate_id > 0) {
        delete_transient($lock_key);
        wp_send_json_success(['message' => 'این درخواست قبلاً ثبت شده است.', 'request_id' => $duplicate_id, 'redirect_url' => esc_url(home_url('/panel/'))]);
        return;
    }


    if (!function_exists('wp_handle_upload')) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
    }
    $uploaded_documents = [];
    $initial_upload_tokens = [];
    $direct_upload_urls = [];
    foreach ($_POST as $post_key => $post_value) {
        if (strpos($post_key, 'mlr_uploaded_doc_') !== 0) {
            continue;
        }
        $doc_key = sanitize_key(substr($post_key, strlen('mlr_uploaded_doc_')));
        if ($doc_key === '' || !isset($allowed_required_doc_keys[$doc_key])) {
            continue;
        }
        $parsed = function_exists('mlr_parse_initial_staged_uploads') ? mlr_parse_initial_staged_uploads($post_value, $doc_key, 'loan_request', 20) : new WP_Error('mlr_upload_proof_unavailable', 'اعتبارسنجی فایل‌های بارگذاری‌شده در دسترس نیست.');
        if (is_wp_error($parsed)) {
            $errors[] = $parsed->get_error_message();
            continue;
        }
        if (!empty($parsed['urls'])) {
            $urls = array_values($parsed['urls']);
            $uploaded_documents[$doc_key] = count($urls) > 1 ? $urls : $urls[0];
            $initial_upload_tokens = array_merge($initial_upload_tokens, $parsed['tokens']);
        }
    }

    // Backward-compatible direct multipart upload, still validated server-side.
    foreach ($_FILES as $input_name => $file_info) {
        $clean_key = strpos($input_name, 'doc_file_') === 0 ? substr($input_name, strlen('doc_file_')) : str_replace(['[', ']'], '', $input_name);
        $clean_key = sanitize_key($clean_key);
        if ($clean_key === '' || !isset($allowed_required_doc_keys[$clean_key]) || isset($uploaded_documents[$clean_key])) {
            continue;
        }
        if (is_array($file_info['name'] ?? null)) {
            $files = mlr_rearray_files($file_info);
            $file_urls = [];
            foreach ($files as $file) {
                if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) continue;
                $upload_error = '';
                $validated = mlr_validate_uploaded_file($file, $upload_error);
                if (!$validated) continue;
                $moved = wp_handle_upload($validated, ['test_form' => false]);
                if ($moved && empty($moved['error'])) { $file_urls[] = $moved['url']; $direct_upload_urls[] = $moved['url']; }
            }
            if ($file_urls) $uploaded_documents[$clean_key] = $file_urls;
        } elseif (($file_info['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $upload_error = '';
            $validated = mlr_validate_uploaded_file($file_info, $upload_error);
            if ($validated) {
                $moved = wp_handle_upload($validated, ['test_form' => false]);
                if ($moved && empty($moved['error'])) { $uploaded_documents[$clean_key] = $moved['url']; $direct_upload_urls[] = $moved['url']; }
            }
        }
    }

    if (is_array($required_docs_keys)) {
        $labels = function_exists('mlr_get_all_document_options') ? mlr_get_all_document_options() : [];
        $missing = [];
        foreach ($required_docs_keys as $req_key) {
            $req_key = sanitize_key($req_key);
            if ($req_key === '') continue;
            $value = $uploaded_documents[$req_key] ?? null;
            $has_value = is_array($value) ? (bool) array_filter($value, 'is_string') : (is_string($value) && $value !== '');
            if (!$has_value) $missing[] = $labels[$req_key] ?? $req_key;
        }
        if ($missing) $errors[] = 'بارگذاری تمام مدارک اجباری الزامی است. مدارک بارگذاری‌نشده: ' . implode('، ', $missing);
    }
    if ($errors) {
        if ($direct_upload_urls && function_exists('mlr_delete_local_document_uploads')) mlr_delete_local_document_uploads($direct_upload_urls);
        delete_transient($lock_key);
        wp_send_json_error(['message' => implode('<br>', array_unique($errors))]);
        return;
    }

    $data['required_documents'] = serialize($uploaded_documents);
    $data['bank_validation_file'] = $uploaded_documents['bank_validation_file'] ?? null;
    $data['signature_image'] = $uploaded_documents['signature_image'] ?? null;
    $data['contract_file'] = $uploaded_documents['contract_file'] ?? null;
    $data['check_images'] = isset($uploaded_documents['check_images']) ? maybe_serialize($uploaded_documents['check_images']) : null;

    $db_columns = $wpdb->get_col("DESC `{$table_name}`", 0);
    if ($db_columns) {
        $data = array_intersect_key($data, array_flip($db_columns));
    }
    $inserted_request_id = function_exists('mlr_insert_loan_request_with_sequence')
        ? mlr_insert_loan_request_with_sequence($data)
        : (($wpdb->insert($table_name, $data) !== false) ? (int) $wpdb->insert_id : new WP_Error('mlr_request_insert_failed', 'ذخیره درخواست ناموفق بود.'));
    delete_transient($lock_key);
    if (is_wp_error($inserted_request_id) || (int) $inserted_request_id <= 0) {
        if ($direct_upload_urls && function_exists('mlr_delete_local_document_uploads')) mlr_delete_local_document_uploads($direct_upload_urls);
        if (function_exists('mlr_log')) mlr_log('Acceptor database insert failed', ['db_error' => $wpdb->last_error, 'sequence_error' => is_wp_error($inserted_request_id) ? $inserted_request_id->get_error_code() : 'invalid_id']);
        $message = is_wp_error($inserted_request_id) ? $inserted_request_id->get_error_message() : 'خطایی در ذخیره‌سازی اطلاعات رخ داد. لطفاً با پشتیبانی تماس بگیرید.';
        wp_send_json_error(['message' => $message]);
        return;
    }

    $new_request_id = (int) $inserted_request_id;
    if (function_exists('mlr_snapshot_loan_contract_mode')) {
        mlr_snapshot_loan_contract_mode($new_request_id, $plan_id);
    }
    if (function_exists('mlr_consume_initial_upload_tokens') && $initial_upload_tokens) {
        mlr_consume_initial_upload_tokens($initial_upload_tokens);
    }
    if ($credit_proof_used && function_exists('mlr_credit_consume_report_proof')) {
        mlr_credit_consume_report_proof($national_code, $mobile_number);
    }
    if (function_exists('mlr_send_sms_on_loan_status_change')) {
        mlr_send_sms_on_loan_status_change($new_request_id, 'در حال بررسی');
    }
    if (function_exists('mlr_create_notification')) {
        mlr_create_notification('new_loan', 'درخواست تسهیلات جدید توسط ' . $full_name . ' ثبت شد.', admin_url('admin.php?page=mlr-loan-requests&s=' . $new_request_id));
    }
    if (function_exists('mlr_add_request_event')) {
        mlr_add_request_event($new_request_id, 'created', 'ثبت اولیه درخواست');
    }

    wp_send_json_success([
        'message' => 'درخواست شما با موفقیت ثبت شد.',
        'request_id' => $new_request_id,
        'redirect_url' => esc_url(home_url('/panel/')),
    ]);
}

?>
