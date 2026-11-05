<?php
if (!defined('ABSPATH')) {
    exit;
}

add_action('wp_ajax_mlr_update_document_receipt_status', 'mlr_update_document_receipt_status_callback');

function mlr_update_document_receipt_status_callback() {
    check_ajax_referer('mlr_ajax_nonce_action', 'nonce');

    if (!function_exists('mlr_can_manage_document_receipt_status') || !mlr_can_manage_document_receipt_status()) {
        wp_send_json_error(['message' => 'شما دسترسی لازم برای تغییر وضعیت دریافت مدارک را ندارید.']);
    }

    $request_id = isset($_POST['request_id']) ? absint($_POST['request_id']) : 0;
    if ($request_id <= 0) {
        wp_send_json_error(['message' => 'شناسه پرونده نامعتبر است.']);
    }

    $raw_status = isset($_POST['document_receipt_status']) ? wp_unslash($_POST['document_receipt_status']) : '';
    $new_status = function_exists('mlr_normalize_document_receipt_status')
        ? mlr_normalize_document_receipt_status($raw_status)
        : 'بررسی نشده';
    $can_manage_document_received_date = function_exists('mlr_can_manage_document_received_date') && mlr_can_manage_document_received_date();
    $raw_document_received_date = isset($_POST['document_received_date']) ? wp_unslash($_POST['document_received_date']) : '';

    global $wpdb;
    $table_name = $wpdb->prefix . 'loan_requests';
    $request_row = $wpdb->get_row($wpdb->prepare("SELECT id, document_receipt_status, document_received_date FROM {$table_name} WHERE id = %d", $request_id));

    if (!$request_row) {
        wp_send_json_error(['message' => 'پرونده موردنظر یافت نشد.']);
    }

    $current_status = function_exists('mlr_get_document_receipt_status_label')
        ? mlr_get_document_receipt_status_label($request_row->document_receipt_status ?? '')
        : trim((string) ($request_row->document_receipt_status ?? ''));

    $current_document_received_date = trim((string) ($request_row->document_received_date ?? ''));
    $new_document_received_date = $current_document_received_date;
    if ($can_manage_document_received_date) {
        $raw_document_received_date = trim((string) $raw_document_received_date);
        if ($raw_document_received_date === '') {
            $new_document_received_date = null;
        } else {
            $date_part = $raw_document_received_date;
            $time_part = '00:00:00';

            if (preg_match('/^(.+?)\s+(\d{1,2}:\d{2})(?::\d{2})?$/u', $raw_document_received_date, $date_matches)) {
                $date_part = trim($date_matches[1]);
                $time_part = $date_matches[2] . ':00';
            }

            if (preg_match('/^1[34]\d{2}[\/\-]\d{1,2}[\/\-]\d{1,2}$/', $date_part)) {
                $gregorian_date = function_exists('mlr_jalali_to_gregorian') ? mlr_jalali_to_gregorian($date_part) : null;
                if (!$gregorian_date) {
                    wp_send_json_error(['message' => 'تاریخ دریافت مدارک نامعتبر است.']);
                }
                $new_document_received_date = $gregorian_date . ' ' . $time_part;
            } else {
                $parsed_ts = strtotime($raw_document_received_date);
                if (!$parsed_ts) {
                    wp_send_json_error(['message' => 'تاریخ دریافت مدارک نامعتبر است.']);
                }
                $new_document_received_date = wp_date('Y-m-d H:i:s', $parsed_ts);
            }
        }
    }

    if ($current_status === $new_status && (string) $current_document_received_date === (string) $new_document_received_date) {
        wp_send_json_success([
            'message' => 'تنظیمات پرونده بدون تغییر باقی ماند.',
            'document_receipt_status_display' => $new_status,
            'document_received_date_display' => function_exists('mlr_format_optional_jalali_datetime') ? mlr_format_optional_jalali_datetime($new_document_received_date) : ($new_document_received_date ?: '—'),
        ]);
    }

    $update_data = ['document_receipt_status' => $new_status];
    $update_formats = ['%s'];
    if ($can_manage_document_received_date) {
        $update_data['document_received_date'] = $new_document_received_date;
        $update_formats[] = $new_document_received_date === null ? '%s' : '%s';
    }

    $updated = $wpdb->update(
        $table_name,
        $update_data,
        ['id' => $request_id],
        $update_formats,
        ['%d']
    );

    if ($updated === false) {
        wp_send_json_error(['message' => 'خطا در ذخیره وضعیت دریافت مدارک.']);
    }

    if (function_exists('mlr_add_request_event')) {
        if ($current_status !== $new_status) {
            mlr_add_request_event($request_id, 'document_receipt_status_change', 'تغییر وضعیت دریافت مدارک به: ' . $new_status);
        }
        if ($can_manage_document_received_date && (string) $current_document_received_date !== (string) $new_document_received_date) {
            $date_label = function_exists('mlr_format_optional_jalali_datetime') ? mlr_format_optional_jalali_datetime($new_document_received_date) : ($new_document_received_date ?: '—');
            mlr_add_request_event($request_id, 'document_received_date_change', 'تغییر تاریخ دریافت مدارک به: ' . $date_label);
        }
    }

    wp_send_json_success([
        'message' => 'تنظیمات پرونده با موفقیت ذخیره شد.',
        'document_receipt_status_display' => $new_status,
        'document_received_date_display' => function_exists('mlr_format_optional_jalali_datetime') ? mlr_format_optional_jalali_datetime($new_document_received_date) : ($new_document_received_date ?: '—'),
    ]);
}
