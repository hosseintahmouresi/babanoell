<?php
/**
 * AJAX: بروزرسانی تاریخ تسویه — در دسترس برای نقش‌های administrator و mali (قابلیت mlr_edit_settlement_date)
 */
if (!defined('ABSPATH')) {
    exit;
}

add_action('wp_ajax_mlr_update_settlement_date', 'mlr_update_settlement_date_callback');

function mlr_update_settlement_date_callback() {
    if (!current_user_can('mlr_edit_settlement_date')) {
        wp_send_json_error(['message' => 'دسترسی غیرمجاز.']);
        return;
    }
    check_ajax_referer('mlr_settlement_date_edit_nonce', 'nonce');

    $request_id = absint($_POST['request_id'] ?? 0);
    $settlement_date_jalali = sanitize_text_field(wp_unslash($_POST['settlement_date'] ?? ''));
    if (!$request_id || $settlement_date_jalali === '') {
        wp_send_json_error(['message' => 'شناسه درخواست یا تاریخ تسویه نامعتبر است.']);
        return;
    }
    if (!function_exists('mlr_jalali_to_gregorian')) {
        wp_send_json_error(['message' => 'تابع تبدیل تاریخ در دسترس نیست.']);
        return;
    }
    $gregorian_date = mlr_jalali_to_gregorian($settlement_date_jalali);
    if (!$gregorian_date) {
        wp_send_json_error(['message' => 'فرمت تاریخ شمسی نامعتبر است (مثال: 1403/06/15).']);
        return;
    }
    $settlement_date_gregorian = $gregorian_date . ' ' . current_time('H:i:s');

    global $wpdb;
    $table = $wpdb->prefix . 'loan_requests';
    $guard_mode = function_exists('mlr_begin_financial_write_guard')
        ? mlr_begin_financial_write_guard([$table])
        : false;
    if (!$guard_mode) {
        wp_send_json_error(['message' => 'شروع عملیات امن بروزرسانی تاریخ تسویه ممکن نشد. هیچ تغییری ثبت نشد.']);
        return;
    }

    $lock_suffix = $guard_mode === 'transaction' ? ' FOR UPDATE' : '';
    $request_row = $wpdb->get_row($wpdb->prepare(
        "SELECT id, user_id, applicant_user_id, acceptor_user_id, request_origin, status, settlement_status, settlement_cycle_id, settlement_date
         FROM `{$table}` WHERE id = %d{$lock_suffix}",
        $request_id
    ), ARRAY_A);

    $fail = static function($message) use ($guard_mode) {
        if (function_exists('mlr_end_financial_write_guard')) {
            mlr_end_financial_write_guard($guard_mode, false);
        }
        wp_send_json_error(['message' => $message]);
    };

    if (empty($request_row)) {
        $fail('درخواست یافت نشد.');
        return;
    }
    $db_request_row = $request_row; // exact persisted values for legacy-engine compensation
    if (function_exists('mlr_reconcile_request_workflow_state')) {
        $request_row = array_merge($request_row, mlr_reconcile_request_workflow_state($request_row));
    }
    $current_status = function_exists('mlr_normalize_status') ? mlr_normalize_status($request_row['status'] ?? '') : trim((string) ($request_row['status'] ?? ''));
    if ($current_status !== 'اعطا شده') {
        $fail('فقط تاریخ تسویه پرونده اعطا شده قابل تغییر است.');
        return;
    }
    if (!empty($request_row['settlement_cycle_id'])) {
        $fail('این پرونده وارد سیکل تسویه شده و تاریخ آن دیگر قابل تغییر نیست.');
        return;
    }
    $current_settlement_status = function_exists('mlr_normalize_status') ? mlr_normalize_status($request_row['settlement_status'] ?? '') : trim((string) ($request_row['settlement_status'] ?? ''));
    if ($current_settlement_status === 'تسویه شده') {
        $fail('پرونده تسویه‌شده قابل ویرایش تاریخ نیست.');
        return;
    }

    $has_effective_acceptor = function_exists('mlr_request_has_effective_acceptor')
        ? mlr_request_has_effective_acceptor($request_row)
        : (!empty($request_row['acceptor_user_id']) || trim((string) ($request_row['request_origin'] ?? '')) !== 'applicant_portal');
    if (!$has_effective_acceptor) {
        $fail('برای تعیین تاریخ تسویه ابتدا باید پذیرنده پرونده مشخص شود.');
        return;
    }

    $data_to_update = ['settlement_date' => $settlement_date_gregorian];
    if (function_exists('mlr_reconcile_request_workflow_state')) {
        foreach (mlr_reconcile_request_workflow_state(array_merge($request_row, $data_to_update)) as $field_key => $field_value) {
            if (in_array($field_key, ['status', 'settlement_status', 'settlement_cycle_id'], true)) {
                $data_to_update[$field_key] = $field_value;
            }
        }
    }
    if (function_exists('mlr_get_canonical_granted_request_settlement_state')) {
        $canonical_state = mlr_get_canonical_granted_request_settlement_state(array_merge($request_row, ['settlement_date' => $settlement_date_gregorian]));
        if (!empty($canonical_state['settlement_status'])) {
            $data_to_update['settlement_status'] = $canonical_state['settlement_status'];
        }
    }
    if (isset($request_row['settlement_cycle_id']) && (int) $request_row['settlement_cycle_id'] === 0) {
        $data_to_update['settlement_cycle_id'] = null;
    }

    $updated = $wpdb->update($table, $data_to_update, ['id' => $request_id], null, ['%d']);
    $verified = $wpdb->get_row($wpdb->prepare(
        "SELECT settlement_date, settlement_status, settlement_cycle_id FROM `{$table}` WHERE id = %d",
        $request_id
    ), ARRAY_A);
    $verified_status = $verified && function_exists('mlr_normalize_status')
        ? mlr_normalize_status($verified['settlement_status'] ?? '')
        : trim((string) ($verified['settlement_status'] ?? ''));
    $expected_status = isset($data_to_update['settlement_status']) && function_exists('mlr_normalize_status')
        ? mlr_normalize_status($data_to_update['settlement_status'])
        : trim((string) ($data_to_update['settlement_status'] ?? $request_row['settlement_status'] ?? ''));
    $verify_ok = $updated !== false
        && $verified
        && (string) ($verified['settlement_date'] ?? '') === $settlement_date_gregorian
        && $verified_status === $expected_status
        && (int) ($verified['settlement_cycle_id'] ?? 0) === 0;

    if (!$verify_ok) {
        if ($guard_mode === 'table_lock') {
            $wpdb->update(
                $table,
                [
                    'settlement_date' => $db_request_row['settlement_date'] ?? null,
                    'settlement_status' => $db_request_row['settlement_status'] ?? '',
                    'settlement_cycle_id' => !empty($db_request_row['settlement_cycle_id']) ? (int) $db_request_row['settlement_cycle_id'] : null,
                ],
                ['id' => $request_id],
                null,
                ['%d']
            );
        }
        if (function_exists('mlr_end_financial_write_guard')) mlr_end_financial_write_guard($guard_mode, false);
        if (function_exists('mlr_log')) mlr_log('Settlement date update verification failed.', ['request_id' => $request_id, 'mode' => $guard_mode, 'db_error' => $wpdb->last_error]);
        wp_send_json_error(['message' => 'خطا در بروزرسانی دیتابیس. هیچ تغییر ناقصی نگه‌داری نشد.']);
        return;
    }

    if ($guard_mode === 'transaction') {
        $committed = function_exists('mlr_end_financial_write_guard') && mlr_end_financial_write_guard($guard_mode, true);
        if (!$committed) {
            if (function_exists('mlr_end_financial_write_guard')) mlr_end_financial_write_guard($guard_mode, false);
            wp_send_json_error(['message' => 'ثبت نهایی تغییر تاریخ تسویه ناموفق بود.']);
            return;
        }
    } else {
        $unlocked = function_exists('mlr_end_financial_write_guard') && mlr_end_financial_write_guard($guard_mode, true);
        if (!$unlocked && function_exists('mlr_log')) {
            mlr_log('MyISAM single settlement-date write verified but UNLOCK TABLES returned false.', ['request_id' => $request_id]);
        }
    }

    if ($updated > 0 && function_exists('mlr_add_request_event')) {
        mlr_add_request_event($request_id, 'settlement_date_edited', 'تاریخ تسویه به ' . $settlement_date_jalali . ' تغییر یافت.');
        if (isset($data_to_update['settlement_status'])) {
            mlr_add_request_event($request_id, 'settlement_status_change', 'تغییر وضعیت تسویه به: ' . $data_to_update['settlement_status']);
        }
    }
    $display_date = function_exists('mlr_gregorian_to_jalali') ? mlr_gregorian_to_jalali($settlement_date_gregorian, 'Y/m/d') : $settlement_date_gregorian;
    wp_send_json_success([
        'message' => $updated > 0 ? 'تاریخ تسویه با موفقیت بروزرسانی شد.' : 'تاریخ واردشده با مقدار فعلی یکسان بود.',
        'settlement_date_display' => $display_date,
        'settlement_status_display' => $data_to_update['settlement_status'] ?? ($request_row['settlement_status'] ?: 'منتظر تعیین تاریخ'),
    ]);
}
