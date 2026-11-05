<?php
if (!defined('ABSPATH')) { exit; }

add_action('wp_ajax_mlr_update_cooperation_status', 'mlr_update_cooperation_status_callback');

function mlr_update_cooperation_status_callback() {
    check_ajax_referer('mlr_ajax_nonce_action', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'شما دسترسی لازم را ندارید.']);
        return;
    }

    $request_id = isset($_POST['request_id']) ? absint($_POST['request_id']) : 0;
    $new_status = isset($_POST['status']) ? sanitize_text_field(wp_unslash($_POST['status'])) : '';
    if (!$request_id || $new_status === '') {
        wp_send_json_error(['message' => 'اطلاعات نامعتبر است.']);
        return;
    }

    $allowed_statuses = mlr_get_cooperation_statuses();
    if (!in_array($new_status, $allowed_statuses, true)) {
        wp_send_json_error(['message' => 'وضعیت انتخاب شده مجاز نیست.']);
        return;
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'cooperation_requests';
    $guard_mode = function_exists('mlr_begin_financial_write_guard')
        ? mlr_begin_financial_write_guard([$table_name])
        : false;
    if (!$guard_mode) {
        wp_send_json_error(['message' => 'قفل امن برای بروزرسانی وضعیت ایجاد نشد. هیچ تغییری ثبت نشد.']);
        return;
    }

    $row_lock_sql = $guard_mode === 'transaction' ? ' FOR UPDATE' : '';
    $current_request = $wpdb->get_row($wpdb->prepare(
        "SELECT id, status, contract_token FROM `{$table_name}` WHERE id = %d{$row_lock_sql}",
        $request_id
    ));
    if (!$current_request) {
        mlr_end_financial_write_guard($guard_mode, false);
        wp_send_json_error(['message' => 'درخواست یافت نشد.']);
        return;
    }

    $data_to_update = ['status' => $new_status];
    $token = $current_request->contract_token;
    $new_token_generated = false;

    if ($new_status === 'تایید مدارک' && empty($token)) {
        // Same strong token format used by new cooperation submissions (256 bits / 64 hex chars).
        do {
            $token = bin2hex(random_bytes(32));
        } while ($wpdb->get_var($wpdb->prepare("SELECT id FROM `{$table_name}` WHERE contract_token = %s", $token)));
        $data_to_update['contract_token'] = $token;
        $new_token_generated = true;
    }

    $updated = $wpdb->update($table_name, $data_to_update, ['id' => $request_id]);
    if ($updated === false) {
        mlr_end_financial_write_guard($guard_mode, false);
        if (function_exists('mlr_log')) {
            mlr_log('Cooperation status update failed.', ['request_id' => $request_id, 'db_error' => $wpdb->last_error]);
        }
        wp_send_json_error(['message' => 'خطا در به‌روزرسانی وضعیت؛ هیچ تغییر ناقصی ثبت نشد.']);
        return;
    }

    $guard_end_ok = mlr_end_financial_write_guard($guard_mode, true);
    if ($guard_mode === 'transaction' && !$guard_end_ok) {
        // COMMIT failed; make a best-effort rollback before reporting failure.
        $wpdb->query('ROLLBACK');
        if (function_exists('mlr_log')) {
            mlr_log('Cooperation status transaction commit failed.', ['request_id' => $request_id, 'db_error' => $wpdb->last_error]);
        }
        wp_send_json_error(['message' => 'ثبت نهایی تغییر وضعیت انجام نشد؛ لطفاً دوباره تلاش کنید.']);
        return;
    }
    if ($guard_mode === 'table_lock' && !$guard_end_ok && function_exists('mlr_log')) {
        // The single UPDATE has already completed atomically under the WRITE lock. A failed
        // UNLOCK is logged, but the request must not be reported as unchanged.
        mlr_log('Cooperation status updated but explicit table unlock failed; connection close will release the lock.', [
            'request_id' => $request_id,
            'db_error' => $wpdb->last_error,
        ]);
    }

    if ($new_token_generated && function_exists('mlr_log')) {
        mlr_log('Generated a new strong contract token.', ['request_id' => $request_id]);
    }

    if ($current_request->status !== $new_status) {
        $updated_request_for_sms = $wpdb->get_row($wpdb->prepare("SELECT * FROM `{$table_name}` WHERE id = %d", $request_id));
        if ($updated_request_for_sms && function_exists('mlr_send_sms_on_cooperation_status_change')) {
            mlr_send_sms_on_cooperation_status_change($request_id, $new_status, $updated_request_for_sms);
        }
        if (function_exists('mlr_create_notification')) {
            mlr_create_notification('status_change', 'وضعیت درخواست همکاری #' . $request_id . ' به «' . $new_status . '» تغییر کرد.', admin_url('admin.php?page=mlr-cooperation-main&s=' . $request_id));
        }
    }

    $response_data = ['message' => 'وضعیت با موفقیت به‌روزرسانی شد.'];
    if (in_array($new_status, ['تایید مدارک', 'عقد قرارداد'], true) && !empty($token)) {
        $contract_page = get_page_by_path('cooperation-contract');
        if ($contract_page) {
            $contract_page_url = get_permalink($contract_page->ID);
            if ($contract_page_url) {
                $response_data['contract_link'] = esc_url(add_query_arg('token', $token, $contract_page_url));
            }
        }
    }

    wp_send_json_success($response_data);
}
