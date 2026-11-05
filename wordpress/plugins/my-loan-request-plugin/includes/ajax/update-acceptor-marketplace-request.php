<?php
if (!defined('ABSPATH')) {
    exit;
}

add_action('wp_ajax_mlr_update_acceptor_marketplace_request', 'mlr_update_acceptor_marketplace_request_callback');

function mlr_update_acceptor_marketplace_request_callback() {
    check_ajax_referer('mlr_ajax_nonce_action', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'دسترسی غیرمجاز.']);
    }

    $request_id = isset($_POST['request_id']) ? absint($_POST['request_id']) : 0;
    if ($request_id <= 0) {
        wp_send_json_error(['message' => 'شناسه پرونده معتبر نیست.']);
    }

    global $wpdb;
    $table_requests = $wpdb->prefix . 'loan_requests';
    $request = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_requests} WHERE id = %d", $request_id));
    if (!$request) {
        wp_send_json_error(['message' => 'پرونده یافت نشد.']);
    }

    if (!function_exists('mlr_is_applicant_origin_request') || !mlr_is_applicant_origin_request($request)) {
        wp_send_json_error(['message' => 'این تنظیمات فقط برای درخواست مستقیم متقاضی قابل تغییر است.']);
    }

    $admin_action = isset($_POST['marketplace_admin_action']) ? sanitize_key(wp_unslash($_POST['marketplace_admin_action'])) : 'keep';
    if (!in_array($admin_action, ['keep', 'assign_acceptor', 'reopen_marketplace', 'close_marketplace'], true)) {
        $admin_action = 'keep';
    }

    $requested_service_scope = isset($_POST['requested_service_scope']) ? sanitize_key(wp_unslash($_POST['requested_service_scope'])) : 'all_country';
    $requested_service_scope = in_array($requested_service_scope, ['selected_locations', 'selected_cities'], true) ? 'selected_locations' : 'all_country';
    $location_taxonomy = function_exists('mlr_get_acceptor_location_taxonomy') ? mlr_get_acceptor_location_taxonomy() : '';
    $category_taxonomy = function_exists('mlr_get_acceptor_category_taxonomy') ? mlr_get_acceptor_category_taxonomy() : '';
    $requested_location_terms = function_exists('mlr_decode_marketplace_term_ids')
        ? mlr_decode_marketplace_term_ids(wp_unslash($_POST['requested_location_terms'] ?? ($_POST['requested_cities'] ?? [])), $location_taxonomy)
        : [];
    $requested_categories = function_exists('mlr_decode_marketplace_term_ids')
        ? mlr_decode_marketplace_term_ids(wp_unslash($_POST['requested_activity_categories'] ?? []), $category_taxonomy)
        : [];
    $requested_location_names = function_exists('mlr_get_term_names_by_ids') ? mlr_get_term_names_by_ids($requested_location_terms, $location_taxonomy) : [];
    $request_public_summary = isset($_POST['request_public_summary']) ? sanitize_textarea_field(wp_unslash($_POST['request_public_summary'])) : '';

    if ($admin_action !== 'assign_acceptor' && $requested_service_scope === 'selected_locations' && empty($requested_location_terms)) {
        wp_send_json_error(['message' => 'برای حالت موقعیت‌های منتخب، حداقل یک موقعیت انتخاب کنید.']);
    }

    $data = [
        'requested_service_scope' => $requested_service_scope,
        'requested_cities' => wp_json_encode($requested_location_names, JSON_UNESCAPED_UNICODE),
        'requested_location_terms' => wp_json_encode(array_values(array_map('absint', $requested_location_terms)), JSON_UNESCAPED_UNICODE),
        'requested_activity_categories' => wp_json_encode(array_values(array_map('absint', $requested_categories)), JSON_UNESCAPED_UNICODE),
        'request_public_summary' => $request_public_summary,
        'last_edited_at' => current_time('mysql'),
    ];
    $formats = ['%s', '%s', '%s', '%s', '%s', '%s'];

    if ($admin_action === 'assign_acceptor') {
        $selected_acceptor_id = isset($_POST['selected_acceptor_id']) ? absint($_POST['selected_acceptor_id']) : 0;
        if ($selected_acceptor_id <= 0 || !function_exists('mlr_user_has_role') || !mlr_user_has_role($selected_acceptor_id, 'acceptor')) {
            wp_send_json_error(['message' => 'شناسه پذیرنده معتبر نیست.']);
        }

        $plan_id = absint($request->installment_plan_id ?? 0);
        if ($plan_id > 0 && function_exists('mlr_user_can_access_installment_plan') && !mlr_user_can_access_installment_plan($selected_acceptor_id, $plan_id, true)) {
            wp_send_json_error(['message' => 'پذیرنده انتخاب‌شده به طرح این پرونده دسترسی ندارد.']);
        }

        $applicant_user_id = function_exists('mlr_get_request_applicant_user_id') ? mlr_get_request_applicant_user_id($request) : absint($request->applicant_user_id ?? $request->user_id ?? 0);
        if ($applicant_user_id > 0 && empty($request->applicant_user_id)) {
            $data['applicant_user_id'] = $applicant_user_id;
            $formats[] = '%d';
        }
        $data['user_id'] = $selected_acceptor_id;
        $data['acceptor_user_id'] = $selected_acceptor_id;
        $data['acceptor_assignment_mode'] = 'applicant_selected_acceptor';
        $data['marketplace_status'] = '';
        $data['marketplace_claimed_by'] = null;
        $data['marketplace_claimed_at'] = null;
        array_push($formats, '%d', '%d', '%s', '%s', '%d', '%s');
    } elseif ($admin_action === 'reopen_marketplace') {
        $applicant_user_id = function_exists('mlr_get_request_applicant_user_id') ? mlr_get_request_applicant_user_id($request) : absint($request->applicant_user_id ?? $request->user_id ?? 0);
        if ($applicant_user_id <= 0) {
            wp_send_json_error(['message' => 'شناسه متقاضی برای بازگشایی بازار پیدا نشد.']);
        }

        $data['user_id'] = $applicant_user_id;
        $data['acceptor_user_id'] = null;
        $data['acceptor_assignment_mode'] = 'open_acceptor_marketplace';
        $data['marketplace_status'] = 'open';
        $data['marketplace_claimed_by'] = null;
        $data['marketplace_claimed_at'] = null;
        array_push($formats, '%d', '%d', '%s', '%s', '%d', '%s');
    } elseif ($admin_action === 'close_marketplace') {
        $applicant_user_id = function_exists('mlr_get_request_applicant_user_id') ? mlr_get_request_applicant_user_id($request) : absint($request->applicant_user_id ?? $request->user_id ?? 0);
        if ($applicant_user_id <= 0) {
            wp_send_json_error(['message' => 'شناسه متقاضی برای بستن بازار پیدا نشد.']);
        }

        $data['user_id'] = $applicant_user_id;
        $data['acceptor_user_id'] = null;
        $data['acceptor_assignment_mode'] = 'open_acceptor_marketplace';
        $data['marketplace_status'] = 'closed';
        $data['marketplace_claimed_by'] = null;
        $data['marketplace_claimed_at'] = null;
        array_push($formats, '%d', '%d', '%s', '%s', '%d', '%s');
    }

    $request_columns = $wpdb->get_col("DESC `{$table_requests}`", 0);
    if (!empty($request_columns)) {
        $filtered_data = [];
        $filtered_formats = [];
        $format_index = 0;

        foreach ($data as $column => $value) {
            $format = $formats[$format_index] ?? '%s';
            if (in_array($column, $request_columns, true)) {
                $filtered_data[$column] = $value;
                $filtered_formats[] = $format;
            }
            $format_index++;
        }

        $data = $filtered_data;
        $formats = $filtered_formats;
    }

    if (empty($data)) {
        wp_send_json_error(['message' => 'ستون‌های لازم برای ذخیره تنظیمات اتصال پذیرنده در جدول درخواست‌ها پیدا نشد. یک‌بار بروزرسانی/فعال‌سازی مجدد پلاگین را اجرا کنید.']);
    }

    $updated = $wpdb->update(
        $table_requests,
        $data,
        ['id' => $request_id],
        $formats,
        ['%d']
    );

    if ($updated === false) {
        $error_message = 'خطا در ذخیره تنظیمات اتصال پذیرنده.';
        if (!empty($wpdb->last_error)) {
            $error_message .= ' جزئیات: ' . $wpdb->last_error;
        }
        wp_send_json_error(['message' => $error_message]);
    }

    if (function_exists('mlr_add_request_event')) {
        $event_labels = [
            'keep' => 'ویرایش شرایط نمایش بازار پذیرنده',
            'assign_acceptor' => 'اتصال دستی پرونده متقاضی به پذیرنده',
            'reopen_marketplace' => 'بازگشایی پرونده در بازار آزاد پذیرنده',
            'close_marketplace' => 'بستن بازار آزاد برای پرونده',
        ];
        mlr_add_request_event($request_id, 'acceptor_marketplace_admin_update', $event_labels[$admin_action] ?? 'ویرایش اتصال پذیرنده');
    }

    wp_send_json_success([
        'message' => 'تنظیمات اتصال پذیرنده ذخیره شد.',
        'request_id' => $request_id,
    ]);
}
