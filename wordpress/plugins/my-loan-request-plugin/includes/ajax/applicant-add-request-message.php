<?php
// FILE: my-loan-request-plugin/includes/ajax/applicant-add-request-message.php
if (!defined('ABSPATH')) { exit; }

add_action('wp_ajax_mlr_applicant_add_request_message', 'mlr_applicant_add_request_message_callback');

function mlr_applicant_add_request_message_callback() {
    if (!function_exists('mlr_verify_any_nonce') || !mlr_verify_any_nonce('nonce')) {
        wp_send_json_error(['message' => 'خطای امنیتی.']);
        return;
    }

    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'برای ارسال پیام، ابتدا وارد شوید.']);
        return;
    }

    $request_id = isset($_POST['request_id']) ? absint($_POST['request_id']) : 0;
    $message    = isset($_POST['message']) ? wp_kses_post(wp_unslash($_POST['message'])) : '';
    if (!$request_id) {
        wp_send_json_error(['message' => 'شناسه نامعتبر.']);
        return;
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'loan_requests';
    $req = $wpdb->get_row($wpdb->prepare(
        "SELECT id, user_id, applicant_user_id, acceptor_user_id, request_origin, assigned_bank_user_id, status, settlement_status FROM {$table_name} WHERE id=%d",
        $request_id
    ));
    if (!$req) {
        wp_send_json_error(['message' => 'درخواست یافت نشد.']);
        return;
    }

    // This endpoint is intentionally limited to applicant-origin requests. Authorization
    // must complete before any attachment is written to disk.
    if (!function_exists('mlr_is_applicant_origin_request')
        || !mlr_is_applicant_origin_request($req)
        || !function_exists('mlr_can_access_loan_request')
        || !mlr_can_access_loan_request($req)
    ) {
        wp_send_json_error(['message' => 'دسترسی ندارید.']);
        return;
    }

    if (!function_exists('mlr_add_request_message')) {
        wp_send_json_error(['message' => 'زیرساخت پیام‌ها فعال نیست.']);
        return;
    }

    $current_user = wp_get_current_user();
    $is_admin = current_user_can('manage_options');
    $is_bank  = in_array('bank', (array) $current_user->roles, true);

    $uploaded_files = [];
    $uploaded_urls = [];

    if (!empty($_FILES['attachments'])) {
        if (!function_exists('wp_handle_upload')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        $files = $_FILES['attachments'];
        $file_ary = [];
        if (is_array($files['name'] ?? null)) {
            foreach ($files['name'] as $key => $value) {
                if (!empty($value)) {
                    $file_ary[] = [
                        'name'     => $files['name'][$key],
                        'type'     => $files['type'][$key] ?? '',
                        'tmp_name' => $files['tmp_name'][$key] ?? '',
                        'error'    => $files['error'][$key] ?? UPLOAD_ERR_NO_FILE,
                        'size'     => $files['size'][$key] ?? 0,
                    ];
                }
            }
        } elseif (!empty($files['name'])) {
            $file_ary[] = $files;
        }

        foreach ($file_ary as $file) {
            if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                continue;
            }

            $upload_error = '';
            $validated = function_exists('mlr_validate_uploaded_file')
                ? mlr_validate_uploaded_file($file, $upload_error)
                : $file;
            if (!$validated) {
                if (function_exists('mlr_log')) {
                    mlr_log('Applicant message attachment rejected.', ['reason' => $upload_error]);
                }
                continue;
            }

            $movefile = wp_handle_upload($validated, ['test_form' => false]);
            if ($movefile && empty($movefile['error'])) {
                $uploaded_files[] = [
                    'url'  => $movefile['url'],
                    'name' => basename($movefile['file']),
                ];
                $uploaded_urls[] = $movefile['url'];
            } elseif (function_exists('mlr_log')) {
                mlr_log('Applicant message attachment upload failed.', ['reason' => $movefile['error'] ?? 'unknown']);
            }
        }
    }

    if (trim(wp_strip_all_tags($message)) === '' && empty($uploaded_files)) {
        if ($uploaded_urls && function_exists('mlr_delete_local_document_uploads')) {
            mlr_delete_local_document_uploads($uploaded_urls);
        }
        wp_send_json_error(['message' => 'متن پیام یا فایل پیوست الزامی است.']);
        return;
    }

    $attachments_json = $uploaded_files
        ? wp_json_encode($uploaded_files, JSON_UNESCAPED_UNICODE)
        : null;

    $insert_id = mlr_add_request_message($request_id, $message, $attachments_json);
    if (!$insert_id) {
        if ($uploaded_urls && function_exists('mlr_delete_local_document_uploads')) {
            mlr_delete_local_document_uploads($uploaded_urls);
        }
        wp_send_json_error(['message' => 'ارسال پیام ناموفق بود.']);
        return;
    }

    if (function_exists('mlr_add_request_event')) {
        mlr_add_request_event($request_id, 'message', 'ارسال پیام جدید');
    }

    if (function_exists('mlr_create_notification')) {
        $sender_label = $is_admin ? 'ادمین' : ($is_bank ? 'کارشناس بانک' : 'کاربر');
        mlr_create_notification(
            'new_message',
            'پیام جدید از ' . $sender_label . ' برای درخواست #' . $request_id,
            admin_url('admin.php?page=mlr-loan-requests&s=' . $request_id)
        );
    }

    $existing_comments_json = $wpdb->get_var($wpdb->prepare("SELECT comments FROM {$table_name} WHERE id = %d", $request_id));
    $comments = !empty($existing_comments_json) ? json_decode($existing_comments_json, true) : [];
    if (!is_array($comments)) { $comments = []; }
    $msgs = function_exists('mlr_get_request_messages') ? mlr_get_request_messages($request_id, 500) : [];

    wp_send_json_success([
        'message' => 'پیام ارسال شد.',
        'messages_html' => function_exists('mlr_render_request_messages_html') ? mlr_render_request_messages_html($msgs) : '',
        'conversation_html' => function_exists('mlr_render_request_conversation_html') ? mlr_render_request_conversation_html($comments, $msgs) : '',
    ]);
}
