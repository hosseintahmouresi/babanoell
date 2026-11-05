<?php
/**
 * ذخیره پیوست‌های مرحله «در انتظار مدارک» (فایل/تصویر برای نمایش در کنار فاکتور و قرارداد به پذیرنده)
 */
if (!defined('ABSPATH')) {
    exit;
}

add_action('wp_ajax_mlr_save_pending_docs_attachments', 'mlr_save_pending_docs_attachments_callback');

function mlr_save_pending_docs_attachments_callback() {
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

    if (!current_user_can('manage_options')) {
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
        wp_send_json_error(['message' => 'شناسه درخواست نامعتبر است.']);
        return;
    }

    global $wpdb;
    $table = $wpdb->prefix . 'loan_requests';

    $col = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM `{$table}` LIKE %s", 'pending_docs_attachments'));
    if (empty($col)) {
        if (function_exists('mlr_log')) mlr_log('Pending docs save blocked: database schema is outdated.');
        wp_send_json_error(['message' => 'ساختار پایگاه داده نیازمند بروزرسانی افزونه است. لطفاً یک‌بار وارد مدیریت وردپرس شوید و دوباره تلاش کنید.']);
        return;
    }

    $req = $wpdb->get_row($wpdb->prepare("SELECT id, pending_docs_attachments FROM `{$table}` WHERE id = %d LIMIT 1", $request_id));
    if (!$req) {
        wp_send_json_error(['message' => 'درخواست یافت نشد.']);
        return;
    }

    $existing = [];
    if (!empty($req->pending_docs_attachments)) {
        $decoded = json_decode($req->pending_docs_attachments, true);
        if (is_array($decoded)) {
            $existing = $decoded;
        }
    }

    $slot_keys = ['invoice' => 'فاکتور', 'contract' => 'قرارداد', 'doc3' => 'پیوست ۳', 'doc4' => 'پیوست ۴', 'doc5' => 'پیوست ۵'];
    if (!function_exists('mlr_handle_wp_upload')) {
        wp_send_json_error(['message' => 'سیستم آپلود در دسترس نیست.']);
        return;
    }

    $new_upload_urls = [];
    foreach ($slot_keys as $key => $default_label) {
        $label = isset($_POST['pending_doc_label_' . $key]) ? sanitize_text_field(wp_unslash($_POST['pending_doc_label_' . $key])) : $default_label;
        if ($label === '') {
            $label = $default_label;
        }

        $remove = !empty($_POST['pending_doc_remove_' . $key]) && $_POST['pending_doc_remove_' . $key] === '1';
        if ($remove) {
            unset($existing[$key]);
            continue;
        }

        $url = isset($existing[$key]['url']) ? $existing[$key]['url'] : '';
        if (!empty($_FILES['pending_doc_file_' . $key]['name']) && ($_FILES['pending_doc_file_' . $key]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $upload = mlr_handle_wp_upload($_FILES['pending_doc_file_' . $key]);
            if (is_wp_error($upload)) {
                if ($new_upload_urls && function_exists('mlr_delete_local_document_uploads')) {
                    mlr_delete_local_document_uploads($new_upload_urls);
                }
                wp_send_json_error(['message' => $upload->get_error_message()]);
                return;
            }
            $url = is_array($upload) ? ($upload['url'] ?? '') : $upload;
            if (!is_string($url) || strpos($url, 'http') !== 0) {
                if ($new_upload_urls && function_exists('mlr_delete_local_document_uploads')) {
                    mlr_delete_local_document_uploads($new_upload_urls);
                }
                wp_send_json_error(['message' => 'ذخیره یکی از پیوست‌ها ناموفق بود.']);
                return;
            }
            $new_upload_urls[] = $url;
        }

        if ($url !== '' && strpos($url, 'http') === 0) {
            $existing[$key] = ['label' => $label, 'url' => $url];
        } elseif (isset($existing[$key]) && ($url === '' || strpos($url, 'http') !== 0)) {
            unset($existing[$key]);
        }
    }

    $json = wp_json_encode($existing, JSON_UNESCAPED_UNICODE);
    $updated = $wpdb->update(
        $table,
        ['pending_docs_attachments' => $json],
        ['id' => $request_id],
        ['%s'],
        ['%d']
    );

    if ($updated === false) {
        if ($new_upload_urls && function_exists('mlr_delete_local_document_uploads')) {
            mlr_delete_local_document_uploads($new_upload_urls);
        }
        $error_message = $wpdb->last_error ? $wpdb->last_error : 'خطای نامشخص پایگاه داده';
        wp_send_json_error(['message' => 'خطا در ذخیره: ' . $error_message]);
        return;
    }

    wp_send_json_success(['message' => 'پیوست‌های مرحله در انتظار مدارک ذخیره شد.']);
}
