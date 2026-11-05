<?php
if (!defined('ABSPATH')) {
    exit;
}

add_action('wp_ajax_mlr_save_transfer_receipts', 'mlr_save_transfer_receipts_callback');

function mlr_save_transfer_receipts_callback() {
    if (ob_get_length()) {
        ob_clean();
    }

    $nonce_ok = false;
    if (function_exists('mlr_verify_any_nonce') && mlr_verify_any_nonce('nonce')) {
        $nonce_ok = true;
    } else {
        $nonce = isset($_REQUEST['nonce']) ? sanitize_text_field(wp_unslash($_REQUEST['nonce'])) : '';
        if ($nonce && function_exists('wp_verify_nonce') && wp_verify_nonce($nonce, 'mlr_ajax_nonce_action')) {
            $nonce_ok = true;
        }
    }

    if (!$nonce_ok) {
        wp_send_json_error(['message' => 'خطای امنیتی. لطفاً صفحه را رفرش کنید و دوباره تلاش کنید.']);
        return;
    }

    if (!function_exists('mlr_can_manage_transfer_receipts') || !mlr_can_manage_transfer_receipts()) {
        wp_send_json_error(['message' => 'عدم دسترسی.']);
        return;
    }

    $request_id = 0;
    if (isset($_POST['request_id']) && absint($_POST['request_id']) > 0) {
        $request_id = absint($_POST['request_id']);
    } elseif (isset($_GET['request_id']) && absint($_GET['request_id']) > 0) {
        $request_id = absint($_GET['request_id']);
    }

    if (!$request_id) {
        wp_send_json_error(['message' => 'شناسه پرونده نامعتبر است.']);
        return;
    }

    $raw_financier_payment_status = isset($_POST['financier_payment_status']) ? wp_unslash($_POST['financier_payment_status']) : '';
    $financier_payment_status = function_exists('mlr_normalize_financier_payment_status')
        ? mlr_normalize_financier_payment_status($raw_financier_payment_status)
        : 'بررسی نشده';

    $has_files = !empty($_FILES['transfer_receipt_files']) && is_array($_FILES['transfer_receipt_files']['name'] ?? null);
    if ($has_files && !function_exists('mlr_handle_wp_upload')) {
        wp_send_json_error(['message' => 'سیستم آپلود در دسترس نیست.']);
        return;
    }

    global $wpdb;
    $table = $wpdb->prefix . 'loan_requests';

    $col = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM `{$table}` LIKE %s", 'transfer_receipt_files'));
    $financier_payment_status_col = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM `{$table}` LIKE %s", 'financier_payment_status'));
    if (empty($col) || empty($financier_payment_status_col)) {
        if (function_exists('mlr_log')) mlr_log('Transfer receipt save blocked: database schema is outdated.');
        wp_send_json_error(['message' => 'ساختار پایگاه داده نیازمند بروزرسانی افزونه است. لطفاً یک‌بار وارد مدیریت وردپرس شوید و دوباره تلاش کنید.']);
        return;
    }

    $request_row = $wpdb->get_row($wpdb->prepare("SELECT id, transfer_receipt_files, financier_payment_status FROM `{$table}` WHERE id = %d LIMIT 1", $request_id));
    if (!$request_row) {
        wp_send_json_error(['message' => 'پرونده موردنظر یافت نشد.']);
        return;
    }

    $existing_files = function_exists('mlr_get_transfer_receipt_files')
        ? mlr_get_transfer_receipt_files($request_row->transfer_receipt_files)
        : [];
    if (!is_array($existing_files)) {
        $existing_files = [];
    }

    $uploaded_urls = [];
    if ($has_files) {
        $files = $_FILES['transfer_receipt_files'];
        $allowed_mimes = [
            'pdf' => 'application/pdf',
            'jpg|jpeg|jpe' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
        ];

        $file_count = count($files['name']);
        for ($i = 0; $i < $file_count; $i++) {
            $name = isset($files['name'][$i]) ? trim((string) $files['name'][$i]) : '';
            $error_code = isset($files['error'][$i]) ? (int) $files['error'][$i] : UPLOAD_ERR_NO_FILE;

            if ($name === '' || $error_code === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            $single_file = [
                'name' => $files['name'][$i] ?? '',
                'type' => $files['type'][$i] ?? '',
                'tmp_name' => $files['tmp_name'][$i] ?? '',
                'error' => $error_code,
                'size' => $files['size'][$i] ?? 0,
            ];

            $upload = mlr_handle_wp_upload($single_file, ['mimes' => $allowed_mimes, 'mlr_filename_prefix' => 'transfer-receipt']);
            if (is_wp_error($upload)) {
                if (!empty($uploaded_urls) && function_exists('mlr_delete_local_document_uploads')) {
                    mlr_delete_local_document_uploads($uploaded_urls);
                }
                wp_send_json_error(['message' => $upload->get_error_message()]);
                return;
            }

            $uploaded_url = is_array($upload) ? ($upload['url'] ?? '') : $upload;
            if (is_string($uploaded_url) && strpos($uploaded_url, 'http') === 0) {
                $uploaded_urls[] = $uploaded_url;
            }
        }
    }

    $merged_files = array_values(array_unique(array_filter(array_merge($existing_files, $uploaded_urls))));
    $stored_value = wp_json_encode($merged_files, JSON_UNESCAPED_UNICODE);
    $current_financier_payment_status = function_exists('mlr_normalize_financier_payment_status')
        ? mlr_normalize_financier_payment_status($request_row->financier_payment_status ?? '')
        : trim((string) ($request_row->financier_payment_status ?? ''));

    if (empty($uploaded_urls) && $current_financier_payment_status === $financier_payment_status) {
        wp_send_json_error(['message' => 'برای ذخیره، یک رسید جدید بارگذاری کنید یا وضعیت پرداخت تأمین‌کننده مالی را تغییر دهید.']);
        return;
    }

    $updated = $wpdb->update(
        $table,
        [
            'transfer_receipt_files' => $stored_value,
            'financier_payment_status' => $financier_payment_status,
        ],
        ['id' => $request_id],
        ['%s', '%s'],
        ['%d']
    );

    if ($updated === false) {
        if (!empty($uploaded_urls) && function_exists('mlr_delete_local_document_uploads')) {
            mlr_delete_local_document_uploads($uploaded_urls);
        }
        $error_message = $wpdb->last_error ? $wpdb->last_error : 'خطای نامشخص پایگاه داده';
        wp_send_json_error(['message' => 'خطا در ذخیره رسیدها: ' . $error_message]);
        return;
    }

    $message_parts = [];
    if (!empty($uploaded_urls)) {
        $message_parts[] = sprintf('%d رسید واریزی با موفقیت بارگذاری شد.', count($uploaded_urls));
    }
    if ($current_financier_payment_status !== $financier_payment_status) {
        $message_parts[] = 'وضعیت پرداخت تأمین‌کننده مالی ذخیره شد.';
    }

    wp_send_json_success([
        'message' => !empty($message_parts) ? implode(' ', $message_parts) : 'اطلاعات مالی با موفقیت ذخیره شد.',
        'uploaded_count' => count($uploaded_urls),
        'financier_payment_status' => $financier_payment_status,
    ]);
}
