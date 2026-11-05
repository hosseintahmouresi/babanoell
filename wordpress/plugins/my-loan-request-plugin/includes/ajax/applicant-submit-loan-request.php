<?php
// FILE: my-loan-request-plugin/includes/ajax/submit-loan-request.php
if (!defined('ABSPATH')) {
    exit;
}

add_action('wp_ajax_mlr_applicant_submit_loan_request', 'mlr_applicant_submit_loan_request_callback');

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

function mlr_applicant_submit_loan_request_callback() {
    // 1. Security Check
    check_ajax_referer('mlr_applicant_loan_request_action', '_wpnonce');
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'برای ثبت درخواست، لطفاً ابتدا وارد شوید.']);
        return;
    }

    if (!current_user_can('manage_options') && function_exists('mlr_is_applicant_user') && !mlr_is_applicant_user()) {
        wp_send_json_error(['message' => 'دسترسی به پنل متقاضی برای حساب شما فعال نیست.']);
        return;
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'loan_requests';
    $plans_table = $wpdb->prefix . 'installment_plans';
    $errors = [];
    $current_user_id = get_current_user_id();
    $build_success_response = static function($request_id, $message = '') {
        $request_id = absint($request_id);
        $portal_url = function_exists('mlr_get_applicant_portal_url') ? mlr_get_applicant_portal_url() : home_url('/');
        $redirect_url = add_query_arg([
            'mlr_submitted' => 1,
            'request_id' => $request_id,
        ], $portal_url);

        return [
            'message' => $message ?: 'درخواست شما با موفقیت ثبت شد. کد پیگیری: #' . $request_id,
            'request_id' => $request_id,
            'redirect_url' => esc_url_raw($redirect_url),
        ];
    };
    $find_recent_duplicate_request = static function($user_id, $plan_id, $loan_amount, $national_code, $minutes = 5) use ($wpdb, $table_name) {
        $user_id = absint($user_id);
        $plan_id = absint($plan_id);
        $loan_amount = (float) $loan_amount;
        $national_code = sanitize_text_field($national_code);
        $minutes = max(2, min(5, absint($minutes)));

        if (!$user_id || !$plan_id || $loan_amount <= 0 || $national_code === '') {
            return 0;
        }

        $duplicate_after = date('Y-m-d H:i:s', current_time('timestamp') - ($minutes * MINUTE_IN_SECONDS));
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table_name}
             WHERE applicant_user_id = %d
               AND installment_plan_id = %d
               AND ROUND(CAST(loan_amount AS DECIMAL(20,2)), 2) = ROUND(%f, 2)
               AND national_code = %s
               AND request_origin = %s
               AND created_at >= %s
             ORDER BY id DESC
             LIMIT 1",
            $user_id,
            $plan_id,
            $loan_amount,
            $national_code,
            'applicant_portal',
            $duplicate_after
        ));
    };
    $submit_lock_key = 'mlr_applicant_submit_lock_' . $current_user_id;

    // 2. Data Sanitization & Collection
    $normalize_digits = static function($value) {
        return function_exists('mlr_normalize_digits') ? mlr_normalize_digits($value, true) : preg_replace('/\D+/', '', (string) $value);
    };

    $phone_number = sanitize_text_field($_POST['phone_number'] ?? '');
    $mobile_number = $normalize_digits($_POST['mobile_number'] ?? '');
    $national_code = $normalize_digits($_POST['national_code'] ?? '');
    
    // Fallback for phone number
    if (empty($phone_number)) $phone_number = $mobile_number;

    // Validate National Code (Server-side)
    if (!function_exists('mlr_validate_iranian_national_code')) {
        function mlr_validate_iranian_national_code($code) {
            if (!preg_match('/^\d{10}$/', $code)) return false;
            $check = (int) $code[9];
            $sum = 0;
            for ($i = 0; $i < 9; $i++) $sum += (int) $code[$i] * (10 - $i);
            $rem = $sum % 11;
            return ($rem < 2 && $check == $rem) || ($rem >= 2 && $check == (11 - $rem));
        }
    }

    if (!mlr_validate_iranian_national_code($national_code)) {
        wp_send_json_error(['message' => 'کد ملی وارد شده معتبر نمی‌باشد.']);
        return;
    }

    if (!preg_match('/^09\d{9}$/', $mobile_number)) {
        wp_send_json_error(['message' => 'شماره موبایل وارد شده معتبر نیست.']);
        return;
    }

    // نام پدر و شماره شناسنامه اجباری
    $father_name = isset($_POST['father_name']) ? trim(sanitize_text_field(wp_unslash($_POST['father_name']))) : '';
    $certificate_number = isset($_POST['certificate_number']) ? trim(sanitize_text_field(wp_unslash($_POST['certificate_number']))) : '';
    if (empty($father_name)) {
        wp_send_json_error(['message' => 'نام پدر الزامی است.']);
        return;
    }
    if (empty($certificate_number)) {
        wp_send_json_error(['message' => 'شماره شناسنامه الزامی است.']);
        return;
    }

    // Date Handling
    $birth_date_jalali = sanitize_text_field($_POST['birth_date'] ?? '');
    $birth_date_gregorian = null;
    // Check if helper function exists, usually in functions.php
    if (!empty($birth_date_jalali) && function_exists('mlr_jalali_to_gregorian')) {
        $birth_date_gregorian = mlr_jalali_to_gregorian($birth_date_jalali);
    } else {
        // Fallback or keep as is if function missing
        $birth_date_gregorian = $birth_date_jalali; 
    }

    // Number Handling
    $persian_numbers = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
    $english_numbers = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
    $loan_amount_str = str_replace([',', '٬'], '', str_replace($persian_numbers, $english_numbers, sanitize_text_field($_POST['loan_amount'] ?? '0')));
    $loan_amount = floatval($loan_amount_str);

    // 3. Product Items Parsing
    $order_items_json = isset($_POST['order_items']) ? wp_unslash($_POST['order_items']) : '[]';
    $order_items_array = json_decode($order_items_json, true);
    $cleaned_items = [];
    $product_summary = [];
    
    if (is_array($order_items_array)) {
        foreach($order_items_array as $item) {
            if (!is_array($item)) continue;

            $qty = intval($item['qty'] ?? ($item['quantity'] ?? 1));
            if ($qty <= 0) { $qty = 1; }

            $unit_price = floatval($item['price'] ?? ($item['unit_price'] ?? 0));
            $total_price = isset($item['total_price']) ? floatval($item['total_price']) : ($qty * $unit_price);

            // Preserve common invoice fields (backward compatible)
            $cleaned_items[] = [
                'id'          => sanitize_text_field($item['id'] ?? ''),
                'name'        => sanitize_text_field($item['name'] ?? ''),
                'code'        => sanitize_text_field($item['code'] ?? ($item['id'] ?? '')),
                'description' => sanitize_text_field($item['description'] ?? ($item['name'] ?? '')),
                'qty'         => $qty,
                'quantity'    => $qty,
                'unit'        => sanitize_text_field($item['unit'] ?? ''),
                'price'       => $unit_price,
                'unit_price'  => $unit_price,
                'total_price' => $total_price,
                'meta'        => is_array($item['meta'] ?? null) ? array_map('sanitize_text_field', $item['meta']) : sanitize_text_field($item['meta'] ?? ''),
            ];
            $product_summary[] = sanitize_text_field($item['name'] ?? $item['description'] ?? '');
        }
    }
    $product_name_string = implode('، ', $product_summary);

    // Canonical server-side product calculation. Browser total_price/order_total are never authoritative.
    if (function_exists('mlr_build_clean_order_items')) {
        $canonical_order = mlr_build_clean_order_items($order_items_json);
        if (is_wp_error($canonical_order)) {
            wp_send_json_error(['message' => $canonical_order->get_error_message()]);
            return;
        }
        $cleaned_items = $canonical_order['items'];
        $product_name_string = $canonical_order['summary'];
        $calculated_order_total = (float) $canonical_order['total'];
    }

    $full_name_for_validation = isset($_POST['full_name']) ? trim(sanitize_text_field(wp_unslash($_POST['full_name']))) : '';
    $postal_code_for_validation = $normalize_digits($_POST['postal_code'] ?? '');
    $residential_address_for_validation = isset($_POST['residential_address']) ? trim(sanitize_textarea_field(wp_unslash($_POST['residential_address']))) : '';
    $work_address_for_validation = isset($_POST['work_address']) ? trim(sanitize_textarea_field(wp_unslash($_POST['work_address']))) : '';
    $order_total_post = isset($_POST['order_total']) ? floatval(str_replace([',', '٬'], '', strtr((string) $_POST['order_total'], ['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9']))) : 0;
    if (!isset($calculated_order_total)) {
        $calculated_order_total = 0;
        foreach ($cleaned_items as $cleaned_item) {
            $calculated_order_total += (float) ($cleaned_item['total_price'] ?? 0);
        }
        $calculated_order_total = round($calculated_order_total, 2);
    }

    if ($full_name_for_validation === '') {
        wp_send_json_error(['message' => 'نام و نام خانوادگی الزامی است.']);
        return;
    }
    if ($birth_date_jalali === '') {
        wp_send_json_error(['message' => 'تاریخ تولد کامل الزامی است.']);
        return;
    }
    if ($postal_code_for_validation === '' || !preg_match('/^\d{10}$/', $postal_code_for_validation)) {
        wp_send_json_error(['message' => 'کد پستی ۱۰ رقمی معتبر وارد کنید.']);
        return;
    }
    if ($residential_address_for_validation === '' || $work_address_for_validation === '') {
        wp_send_json_error(['message' => 'آدرس محل سکونت و آدرس محل فعالیت الزامی است.']);
        return;
    }
    if (empty($cleaned_items) || $calculated_order_total <= 0) {
        wp_send_json_error(['message' => 'ثبت حداقل یک محصول معتبر الزامی است.']);
        return;
    }
    if ($loan_amount <= 0 || abs($calculated_order_total - $loan_amount) > 1) {
        wp_send_json_error(['message' => 'جمع مبلغ محصولات باید با مبلغ درخواستی برابر باشد.']);
        return;
    }

    // 4. Base Data Construction
    
    // 4. Store full submitted form data (for auditing & details popup)
    $raw_post = $_POST;
    // Remove technical keys
    unset($raw_post['action'], $raw_post['_wpnonce'], $raw_post['nonce'], $raw_post['credit_report_json']);
    // Avoid storing very large blobs in form_data (files are handled separately)
    if (isset($raw_post['order_items'])) {
        // keep, but ensure it's a string (original JSON)
        $raw_post['order_items'] = sanitize_textarea_field(wp_unslash($raw_post['order_items']));
    }
    $sanitized_form_data = [];
    foreach ($raw_post as $k => $v) {
        $key = sanitize_key($k);
        if (is_array($v)) {
            $sanitized_form_data[$key] = array_map(function($vv){
                return is_scalar($vv) ? sanitize_text_field($vv) : '';
            }, $v);
        } else {
            $sanitized_form_data[$key] = is_scalar($v) ? sanitize_textarea_field(wp_unslash($v)) : '';
        }
    }
    $form_data_json = wp_json_encode($sanitized_form_data, JSON_UNESCAPED_UNICODE);

    // Credit report is resolved from a server-side proof after plan validation.
    $credit_report_raw = '';
    $credit_proof_used = false;

    $data = [
        'user_id' => $current_user_id,
        'applicant_user_id' => $current_user_id,
        'acceptor_user_id' => null,
        'request_origin' => 'applicant_portal',
        'full_name' => $full_name_for_validation,
        'national_code' => $national_code,
        'mobile_number' => $mobile_number,
        'phone_number' => $phone_number,
        'birth_date' => $birth_date_gregorian,
        'occupation' => sanitize_text_field($_POST['occupation'] ?? ''),
        'residential_address' => $residential_address_for_validation,
        'postal_code' => $postal_code_for_validation,
        'work_address' => $work_address_for_validation,
        'product_name' => $product_name_string, // Summary string
        'order_items' => wp_json_encode($cleaned_items, JSON_UNESCAPED_UNICODE), // Full JSON
        'order_total' => $calculated_order_total,
        'form_data'  => $form_data_json,
        'credit_report_json' => $credit_report_raw, // Save raw JSON to dedicated column
        'installment_plan_id' => intval($_POST['installment_plan_id'] ?? 0),
        'loan_amount' => $loan_amount,
        'facility_amount' => $loan_amount,
        'installment_count' => intval($_POST['installment_count'] ?? 0),
        'installment_interval' => isset($_POST['installment_interval']) ? intval($_POST['installment_interval']) : 1,
        'status' => 'در حال بررسی',
        'settlement_status' => '',
        'created_at' => current_time('mysql'),
        'last_edited_at' => current_time('mysql'),
    ];

    if (!function_exists('mlr_applicant_can_access_installment_plan') || !mlr_applicant_can_access_installment_plan($data['installment_plan_id'], true)) {
        wp_send_json_error(['message' => 'طرح انتخاب‌شده برای حساب شما مجاز نیست یا در دسترس نمی‌باشد.']);
        return;
    }

    $plan_for_validation = $wpdb->get_row($wpdb->prepare("SELECT * FROM $plans_table WHERE id = %d AND is_active = 1", $data['installment_plan_id']));
    if (!$plan_for_validation) {
        wp_send_json_error(['message' => 'طرح انتخاب‌شده فعال نیست یا پیدا نشد.']);
        return;
    }
    $plan_validation = function_exists('mlr_validate_plan_request_values')
        ? mlr_validate_plan_request_values($plan_for_validation, $data['loan_amount'], $data['installment_count'], $data['installment_interval'])
        : null;
    if (is_wp_error($plan_validation)) {
        wp_send_json_error(['message' => $plan_validation->get_error_message()]);
        return;
    }
    $plan_settings_for_validation = is_array($plan_validation) ? $plan_validation['settings'] : maybe_unserialize($plan_for_validation->settings);
    if (!is_array($plan_settings_for_validation)) {
        $plan_settings_for_validation = [];
    }
    if (is_array($plan_validation)) {
        $data['installment_interval'] = (int) $plan_validation['interval'];
    }

    $occupation_raw = sanitize_text_field(wp_unslash($_POST['occupation'] ?? ''));
    $occupation_key = sanitize_key($occupation_raw);
    if (function_exists('mlr_validate_plan_occupation')) {
        $occupation_validation = mlr_validate_plan_occupation($plan_settings_for_validation, $occupation_key);
        if (is_wp_error($occupation_validation)) {
            wp_send_json_error(['message' => $occupation_validation->get_error_message()]);
            return;
        }
    }
    if (function_exists('mlr_plan_uses_occupation_documents') && mlr_plan_uses_occupation_documents($plan_settings_for_validation)) {
        $data['occupation'] = function_exists('mlr_normalize_occupation_key') ? mlr_normalize_occupation_key($occupation_key) : $occupation_key;
        $sanitized_form_data['occupation'] = $data['occupation'];
        $data['form_data'] = wp_json_encode($sanitized_form_data, JSON_UNESCAPED_UNICODE);
    }
    $required_docs_keys = function_exists('mlr_get_plan_required_document_keys')
        ? mlr_get_plan_required_document_keys($plan_for_validation->required_documents ?? [], $plan_settings_for_validation, $occupation_key)
        : maybe_unserialize($plan_for_validation->required_documents ?? []);
    if (!is_array($required_docs_keys)) {
        $required_docs_keys = [];
    }
    $allowed_required_doc_keys = array_fill_keys(array_map('sanitize_key', $required_docs_keys), true);

    // A browser-supplied credit JSON is never accepted on its own. Prefer the
    // existing transient proof, then fall back to the server-signed report token
    // issued by the completed credit-validation step.
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
    if (!empty($plan_settings_for_validation['requires_bank_validation'])) {
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
    $data['credit_report_json'] = $credit_report_raw;
    if ($credit_report_raw !== '') {
        $sanitized_form_data['credit_report_json'] = $credit_report_raw;
        $data['form_data'] = wp_json_encode($sanitized_form_data, JSON_UNESCAPED_UNICODE);
    }


    $marketplace_settings = function_exists('mlr_get_acceptor_marketplace_settings') ? mlr_get_acceptor_marketplace_settings() : ['enabled' => 1, 'allow_applicant_selected_acceptor' => 1, 'allow_open_marketplace' => 1];
    $marketplace_enabled = !empty($marketplace_settings['enabled'])
        && (!empty($marketplace_settings['allow_applicant_selected_acceptor']) || !empty($marketplace_settings['allow_open_marketplace']));
    $assignment_mode = isset($_POST['acceptor_assignment_mode']) ? sanitize_key(wp_unslash($_POST['acceptor_assignment_mode'])) : '';
    $locked_acceptor_id = function_exists('mlr_get_locked_acceptor_for_current_applicant') ? mlr_get_locked_acceptor_for_current_applicant() : 0;
    $locked_invitation_code = '';
    if ($locked_acceptor_id > 0 && !empty($marketplace_settings['enable_acceptor_invitation_links'])) {
        $marketplace_enabled = true;
        $assignment_mode = 'applicant_selected_acceptor';
        $locked_invitation_code = (string) get_user_meta($current_user_id, 'mlr_locked_acceptor_invitation_code', true);
        if ($locked_invitation_code === '' && function_exists('mlr_get_current_acceptor_invitation_code')) {
            $locked_invitation_code = mlr_get_current_acceptor_invitation_code();
        }
    }

    if ($marketplace_enabled) {
    if (!in_array($assignment_mode, ['applicant_selected_acceptor', 'open_acceptor_marketplace'], true)) {
        wp_send_json_error(['message' => 'لطفاً وضعیت پذیرنده را مشخص کنید.']);
        return;
    }

    if ($assignment_mode === 'applicant_selected_acceptor') {
        if (empty($marketplace_settings['allow_applicant_selected_acceptor']) && $locked_acceptor_id <= 0) {
            wp_send_json_error(['message' => 'انتخاب مستقیم پذیرنده در حال حاضر فعال نیست.']);
            return;
        }

        $selected_acceptor_id = $locked_acceptor_id > 0 ? $locked_acceptor_id : (isset($_POST['selected_acceptor_id']) ? absint($_POST['selected_acceptor_id']) : 0);
        if ($selected_acceptor_id <= 0 || !function_exists('mlr_user_has_role') || !mlr_user_has_role($selected_acceptor_id, 'acceptor')) {
            wp_send_json_error(['message' => 'پذیرنده انتخاب‌شده معتبر نیست.']);
            return;
        }

        if (get_user_meta($selected_acceptor_id, 'mlr_acceptor_marketplace_enabled', true) === '0') {
            wp_send_json_error(['message' => 'پذیرنده انتخاب‌شده در حال حاضر فعال نیست.']);
            return;
        }

        if (function_exists('mlr_get_acceptor_profile_post_id') && function_exists('mlr_get_acceptor_cpt_slug') && post_type_exists(mlr_get_acceptor_cpt_slug()) && mlr_get_acceptor_profile_post_id($selected_acceptor_id) <= 0) {
            wp_send_json_error(['message' => 'پروفایل پذیرنده انتخاب‌شده در سامانه پذیرندگان تکمیل نشده است.']);
            return;
        }

        if (function_exists('mlr_user_can_access_installment_plan') && !mlr_user_can_access_installment_plan($selected_acceptor_id, $data['installment_plan_id'], true)) {
            wp_send_json_error(['message' => 'پذیرنده انتخاب‌شده به طرح انتخابی شما دسترسی ندارد.']);
            return;
        }

        $data['user_id'] = $selected_acceptor_id;
        $data['acceptor_user_id'] = $selected_acceptor_id;
        $data['acceptor_assignment_mode'] = 'applicant_selected_acceptor';
        $data['marketplace_status'] = '';
        $data['marketplace_claimed_by'] = null;
        $data['marketplace_claimed_at'] = null;
        $data['requested_service_scope'] = '';
        $data['requested_cities'] = null;
        $data['requested_location_terms'] = null;
        $data['requested_activity_categories'] = null;
        $data['request_public_summary'] = null;
        if ($locked_acceptor_id > 0) {
            $data['acceptor_invitation_code'] = sanitize_text_field($locked_invitation_code);
            $data['acceptor_invited_by'] = $locked_acceptor_id;
            $data['acceptor_invitation_locked'] = 1;
        }
    } else {
        if (empty($marketplace_settings['allow_open_marketplace'])) {
            wp_send_json_error(['message' => 'ثبت درخواست بدون پذیرنده در حال حاضر فعال نیست.']);
            return;
        }

        $requested_service_scope = isset($_POST['requested_service_scope']) ? sanitize_key(wp_unslash($_POST['requested_service_scope'])) : 'all_country';
        $requested_service_scope = in_array($requested_service_scope, ['selected_locations', 'selected_cities'], true) ? 'selected_locations' : 'all_country';
        if ($requested_service_scope === 'all_country' && empty($marketplace_settings['allow_all_country'])) {
            $requested_service_scope = 'selected_locations';
        }
        $requested_locations_raw = isset($_POST['requested_location_terms']) ? wp_unslash($_POST['requested_location_terms']) : (isset($_POST['requested_cities']) ? wp_unslash($_POST['requested_cities']) : []);
        $requested_categories_raw = isset($_POST['requested_activity_categories']) ? wp_unslash($_POST['requested_activity_categories']) : [];
        $location_taxonomy = function_exists('mlr_get_acceptor_location_taxonomy') ? mlr_get_acceptor_location_taxonomy() : '';
        $category_taxonomy = function_exists('mlr_get_acceptor_category_taxonomy') ? mlr_get_acceptor_category_taxonomy() : '';
        $requested_location_terms = function_exists('mlr_decode_marketplace_term_ids') ? mlr_decode_marketplace_term_ids($requested_locations_raw, $location_taxonomy) : [];
        $requested_categories = function_exists('mlr_decode_marketplace_term_ids') ? mlr_decode_marketplace_term_ids($requested_categories_raw, $category_taxonomy) : [];
        $requested_location_names = function_exists('mlr_get_term_names_by_ids') ? mlr_get_term_names_by_ids($requested_location_terms, $location_taxonomy) : [];
        $request_public_summary = isset($_POST['request_public_summary']) ? trim(sanitize_textarea_field(wp_unslash($_POST['request_public_summary']))) : '';

        if ($requested_service_scope === 'selected_locations' && !empty($marketplace_settings['require_location']) && empty($requested_location_terms)) {
            wp_send_json_error(['message' => 'حداقل یک موقعیت برای نمایش درخواست انتخاب کنید.']);
            return;
        }

        if (!empty($marketplace_settings['require_category']) && empty($requested_categories)) {
            wp_send_json_error(['message' => 'حداقل یک حوزه فعالیت انتخاب کنید.']);
            return;
        }

        $summary_length = function_exists('mb_strlen') ? mb_strlen($request_public_summary) : strlen($request_public_summary);
        if ($summary_length < 10) {
            wp_send_json_error(['message' => 'توضیح کوتاه درخواست باید حداقل ۱۰ کاراکتر باشد.']);
            return;
        }

        $data['acceptor_assignment_mode'] = 'open_acceptor_marketplace';
        $data['marketplace_status'] = 'open';
        $data['marketplace_claimed_by'] = null;
        $data['marketplace_claimed_at'] = null;
        $data['requested_service_scope'] = $requested_service_scope;
        $data['requested_cities'] = wp_json_encode($requested_location_names, JSON_UNESCAPED_UNICODE);
        $data['requested_location_terms'] = wp_json_encode(array_values(array_map('absint', $requested_location_terms)), JSON_UNESCAPED_UNICODE);
        $data['requested_activity_categories'] = wp_json_encode(array_values(array_map('absint', $requested_categories)), JSON_UNESCAPED_UNICODE);
        $data['request_public_summary'] = $request_public_summary;
    }
    }

    $duplicate_request_id = $find_recent_duplicate_request($current_user_id, $data['installment_plan_id'], $data['loan_amount'], $national_code, 5);
    if ($duplicate_request_id > 0) {
        wp_send_json_success($build_success_response($duplicate_request_id));
    }

    if (get_transient($submit_lock_key)) {
        $locked_duplicate_request_id = $find_recent_duplicate_request($current_user_id, $data['installment_plan_id'], $data['loan_amount'], $national_code, 5);
        if ($locked_duplicate_request_id > 0) {
            wp_send_json_success($build_success_response($locked_duplicate_request_id));
        }

        wp_send_json_error(['message' => 'درخواست شما در حال ثبت است یا لحظاتی قبل ثبت شده است. لطفاً پنل درخواست‌ها را بررسی کنید.']);
        return;
    }
    set_transient($submit_lock_key, 1, 60);

    // 5. Financial Calculations
    $plan = isset($plan_for_validation) ? $plan_for_validation : $wpdb->get_row($wpdb->prepare("SELECT * FROM $plans_table WHERE id = %d AND is_active = 1", $data['installment_plan_id']));
    if ($plan) {
        $settings = maybe_unserialize($plan->settings);
        if (!is_array($settings)) {
            $settings = [];
        }
        $user_commission = null;
        
        // Ensure constant is defined, fallback to direct path if needed
        $plugin_dir = defined('MLR_PLUGIN_DIR') ? MLR_PLUGIN_DIR : WP_PLUGIN_DIR . '/my-loan-request-plugin/';
        
        if (file_exists($plugin_dir . 'includes/loan-calculations.php')) {
            require_once $plugin_dir . 'includes/loan-calculations.php';
            
            $plan_type = is_string($plan->type) ? strtolower(trim($plan->type)) : '';
            $calc_results = [];
            if ($plan_type === 'market' && function_exists('calculateMarketLoan')) {
                $calc_results = calculateMarketLoan($data['loan_amount'], $data['installment_count'], $settings, $user_commission);
            } elseif ($plan_type === 'leasing' && function_exists('calculateLeasingLoan')) {
                // فاصله اقساط نباید صفر باشد وگرنه تابع آرایه خالی برمی‌گرداند و مبلغ تسهیلات برابر مبلغ درخواستی می‌ماند
                $interval = isset($data['installment_interval']) && (int) $data['installment_interval'] > 0 ? (int) $data['installment_interval'] : 1;
                $calc_results = calculateLeasingLoan($data['loan_amount'], $data['installment_count'], $interval, $settings, $user_commission);
            }

            if (!empty($calc_results) && isset($calc_results['loanAmount'])) {
                $data['facility_amount'] = round(floatval($calc_results['loanAmount']));
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
    }

    // 6. Robust File Upload Handling (پذیرش آپلود از قبل انجام‌شده یا فایل مستقیم)
    if (!function_exists('wp_handle_upload')) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
    }

    $uploaded_documents = [];
    $initial_upload_tokens = [];
    $direct_upload_urls = [];

    // اول: فایل‌های staged فقط با proof سروری متعلق به همین session/field پذیرفته می‌شوند.
    foreach ($_POST as $post_key => $post_value) {
        if (strpos($post_key, 'mlr_uploaded_doc_') !== 0) {
            continue;
        }
        $doc_key = sanitize_key(substr($post_key, strlen('mlr_uploaded_doc_')));
        if ($doc_key === '' || !isset($allowed_required_doc_keys[$doc_key])) {
            continue;
        }
        $parsed_uploads = function_exists('mlr_parse_initial_staged_uploads')
            ? mlr_parse_initial_staged_uploads($post_value, $doc_key, 'loan_request', 20)
            : new WP_Error('mlr_upload_proof_unavailable', 'اعتبارسنجی فایل‌های بارگذاری‌شده در دسترس نیست.');
        if (is_wp_error($parsed_uploads)) {
            $errors[] = $parsed_uploads->get_error_message();
            continue;
        }
        if (!empty($parsed_uploads['urls'])) {
            $urls = array_values($parsed_uploads['urls']);
            $uploaded_documents[$doc_key] = count($urls) > 1 ? $urls : $urls[0];
            $initial_upload_tokens = array_merge($initial_upload_tokens, $parsed_uploads['tokens']);
        }
    }

    // دوم: اگر فایلی از طریق فرم سنتی ارسال شده (پشتیبانی بازگشتی)
    if (!empty($_FILES)) {
        foreach ($_FILES as $input_name => $file_info) {
            $clean_key = str_replace(['[', ']'], '', $input_name);
            if (strpos($input_name, 'doc_file_') === 0) {
                $clean_key = substr($input_name, strlen('doc_file_'));
            }
            $clean_key = sanitize_key($clean_key);
            if ($clean_key === '' || !isset($allowed_required_doc_keys[$clean_key]) || isset($uploaded_documents[$clean_key])) {
                continue;
            }
            if (is_array($file_info['name'])) {
                $files = mlr_rearray_files($file_info);
                $file_urls = [];
                foreach ($files as $file) {
                    if ($file['error'] === UPLOAD_ERR_OK) {
                        $upload_error = '';
                        $validated_file = mlr_validate_uploaded_file($file, $upload_error);
                        if (!$validated_file) continue;
                        $file = $validated_file;
                        $upload_overrides = ['test_form' => false];
                        $movefile = wp_handle_upload($file, $upload_overrides);
                        if ($movefile && !isset($movefile['error'])) {
                            $file_urls[] = $movefile['url'];
                            $direct_upload_urls[] = $movefile['url'];
                        }
                    }
                }
                if (!empty($file_urls)) {
                    $uploaded_documents[$clean_key] = $file_urls;
                }
            } else {
                if ($file_info['error'] === UPLOAD_ERR_OK) {
                    $upload_error = '';
                    $validated_file = mlr_validate_uploaded_file($file_info, $upload_error);
                    if ($validated_file) {
                        $upload_overrides = ['test_form' => false];
                        $movefile = wp_handle_upload($validated_file, $upload_overrides);
                        if ($movefile && !isset($movefile['error'])) {
                            $uploaded_documents[$clean_key] = $movefile['url'];
                            $direct_upload_urls[] = $movefile['url'];
                        }
                    }
                }
            }
        }
    }
    
    // اعتبارسنجی مدارک اجباری طرح بر اساس شغل انتخاب‌شده.
    if (!empty($required_docs_keys)) {
        $missing_labels = [];
        $labels = function_exists('mlr_get_all_document_options') ? mlr_get_all_document_options() : [];
        foreach ($required_docs_keys as $req_key) {
            $req_key = sanitize_key((string) $req_key);
            if ($req_key === '') continue;
            $value = $uploaded_documents[$req_key] ?? null;
            $has_value = is_array($value)
                ? count(array_filter($value, 'is_string')) > 0
                : (is_string($value) && $value !== '');
            if (!$has_value) {
                $missing_labels[] = $labels[$req_key] ?? $req_key;
            }
        }
        if (!empty($missing_labels)) {
            $errors[] = 'بارگذاری تمام مدارک اجباری الزامی است. مدارک بارگذاری‌نشده: ' . implode('، ', $missing_labels);
        }
    }

    // Explicitly serialize using standard serialize()
    $data['required_documents'] = serialize($uploaded_documents);

    // Map specific files to their dedicated DB columns
    $data['bank_validation_file'] = $uploaded_documents['bank_validation_file'] ?? null;
    $data['signature_image']      = $uploaded_documents['signature_image'] ?? null;
    $data['contract_file']        = $uploaded_documents['contract_file'] ?? null;
    $data['check_images']         = isset($uploaded_documents['check_images']) ? maybe_serialize($uploaded_documents['check_images']) : null;

    // 7. Insert to Database
    if (empty($errors)) {
        // Safety: Ensure we only insert columns that exist in the DB
        // This prevents "Unknown column" errors if the DB schema update failed
        $db_columns = $wpdb->get_col("DESC `{$table_name}`", 0);
        if ($db_columns) {
            $data = array_intersect_key($data, array_flip($db_columns));
        }

        $inserted_request_id = function_exists('mlr_insert_loan_request_with_sequence')
            ? mlr_insert_loan_request_with_sequence($data)
            : (($wpdb->insert($table_name, $data) !== false) ? (int) $wpdb->insert_id : new WP_Error('mlr_request_insert_failed', 'ذخیره درخواست ناموفق بود.'));
        
        if (is_wp_error($inserted_request_id) || (int) $inserted_request_id <= 0) {
            delete_transient($submit_lock_key);
            if (!empty($direct_upload_urls) && function_exists('mlr_delete_local_document_uploads')) { mlr_delete_local_document_uploads($direct_upload_urls); }
            $errors[] = is_wp_error($inserted_request_id) ? $inserted_request_id->get_error_message() : "خطایی در ذخیره‌سازی اطلاعات رخ داد. لطفاً با پشتیبانی تماس بگیرید.";
            if (function_exists('mlr_log')) { mlr_log('Applicant database insert failed', ['db_error' => $wpdb->last_error, 'sequence_error' => is_wp_error($inserted_request_id) ? $inserted_request_id->get_error_code() : 'invalid_id']); }
        } else {
            $new_request_id = (int) $inserted_request_id;
            if (function_exists('mlr_snapshot_loan_contract_mode')) {
                mlr_snapshot_loan_contract_mode($new_request_id, $data['installment_plan_id']);
            }
            if (function_exists('mlr_consume_initial_upload_tokens') && !empty($initial_upload_tokens)) {
                mlr_consume_initial_upload_tokens($initial_upload_tokens);
            }
            if ($credit_proof_used && function_exists('mlr_credit_consume_report_proof')) {
                mlr_credit_consume_report_proof($national_code, $mobile_number);
            }
            
            // Send SMS if function exists
            if (function_exists('mlr_send_sms_on_loan_status_change')) {
                mlr_send_sms_on_loan_status_change($new_request_id, 'در حال بررسی');
            }

            // Notification
            if (function_exists('mlr_create_notification')) {
                mlr_create_notification('new_loan', 'درخواست تسهیلات جدید توسط ' . sanitize_text_field($_POST['full_name'] ?? 'کاربر') . ' ثبت شد.', admin_url('admin.php?page=mlr-loan-requests&s=' . $new_request_id));
            }
            
            // Success Response
            if (!empty($new_request_id)) {
                if (function_exists('mlr_add_request_event')) {
                    mlr_add_request_event($new_request_id, 'created', 'ثبت اولیه درخواست');
                }
            }

            wp_send_json_success($build_success_response($new_request_id));
        }
    }

    // 8. Error Response
    if (!empty($errors)) {
        if (!empty($direct_upload_urls) && function_exists('mlr_delete_local_document_uploads')) { mlr_delete_local_document_uploads($direct_upload_urls); }
        delete_transient($submit_lock_key);
        wp_send_json_error(['message' => implode('<br>', $errors)]);
    }
}
?>
