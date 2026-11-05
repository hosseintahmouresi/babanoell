<?php
/**
 * AJAX: upload one document file.
 * Used by initial forms and by the resilient staged uploader in dashboards.
 */
if (!defined('ABSPATH')) {
    exit;
}

add_action('wp_ajax_mlr_upload_single_file', 'mlr_upload_single_file_callback');
add_action('wp_ajax_nopriv_mlr_upload_single_file', 'mlr_upload_single_file_callback');

function mlr_upload_single_file_callback() {
    while (ob_get_level()) {
        ob_end_clean();
    }

    $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
    $context = isset($_POST['context']) ? sanitize_text_field(wp_unslash($_POST['context'])) : '';
    $field_key = isset($_POST['field_key']) ? sanitize_key(wp_unslash($_POST['field_key'])) : '';
    $request_id = isset($_POST['request_id']) ? absint($_POST['request_id']) : 0;
    $portal_context = isset($_POST['portal_context']) ? sanitize_key(wp_unslash($_POST['portal_context'])) : 'auto';
    if (!in_array($portal_context, ['acceptor', 'applicant', 'auto'], true)) {
        $portal_context = 'auto';
    }

    $valid_nonce =
        wp_verify_nonce($nonce, 'mlr_loan_request_action') ||
        wp_verify_nonce($nonce, 'mlr_applicant_loan_request_action') ||
        wp_verify_nonce($nonce, 'mlr_cooperation_request_action') ||
        wp_verify_nonce($nonce, 'mlr_frontend_nonce') ||
        wp_verify_nonce($nonce, 'mlr_ajax_nonce_action') ||
        wp_verify_nonce($nonce, 'mlr_ajax_nonce');

    if (!$valid_nonce) {
        wp_send_json_error(['message' => 'اعتبار فرم منقضی شده است. صفحه را تازه‌سازی کنید.']);
    }

    // On multisite, perform authorization and storage in the same site context
    // that rendered the guarantor form. This keeps upload URLs, request tables,
    // transients and final-save validation aligned.
    $restore_blog = false;
    $requested_blog_id = isset($_POST['blog_id']) ? absint($_POST['blog_id']) : 0;
    if ($requested_blog_id > 0 && function_exists('is_multisite') && is_multisite()
        && function_exists('get_current_blog_id') && function_exists('switch_to_blog')
        && function_exists('restore_current_blog') && get_current_blog_id() !== $requested_blog_id) {
        switch_to_blog($requested_blog_id);
        $restore_blog = true;
    }
    if ($restore_blog && function_exists('register_shutdown_function')) {
        register_shutdown_function(static function () use (&$restore_blog) {
            if ($restore_blog && function_exists('restore_current_blog')) {
                restore_current_blog();
                $restore_blog = false;
            }
        });
    }

    if (!in_array($context, ['loan_request', 'cooperation_request', 'guarantor'], true)) {
        wp_send_json_error(['message' => 'پارامتر نوع فرم معتبر نیست.']);
    }

    if ($context === 'guarantor') {
        if ($request_id <= 0) {
            wp_send_json_error(['message' => 'شناسه پرونده برای بارگذاری مدرک ضامن معتبر نیست.']);
        }
        if (!function_exists('mlr_is_valid_guarantor_upload_field_key') || !mlr_is_valid_guarantor_upload_field_key($field_key)) {
            wp_send_json_error(['message' => 'نوع مدرک ضامن معتبر نیست.']);
        }
    }

    if ($request_id > 0) {
        $allowed_stages = ($context === 'guarantor')
            ? null
            : (function_exists('mlr_get_allowed_document_upload_stages') ? mlr_get_allowed_document_upload_stages() : ['defect_documents', 'completion_documents', 'pending_documents']);
        $request = function_exists('mlr_get_authorized_document_request')
            ? mlr_get_authorized_document_request($request_id, $portal_context, $allowed_stages)
            : new WP_Error('mlr_upload_service_missing', 'سرویس بررسی دسترسی فایل در دسترس نیست.');
        if (is_wp_error($request)) {
            wp_send_json_error(['message' => $request->get_error_message()]);
        }
    }

    if ($request_id === 0 && function_exists('mlr_rate_limit_check')) {
        $limit_check = mlr_rate_limit_check('initial_file_upload', 50, HOUR_IN_SECONDS, [$context]);
        if (is_wp_error($limit_check)) {
            wp_send_json_error(['message' => $limit_check->get_error_message(), 'retry_after' => (int) (($limit_check->get_error_data()['retry_after'] ?? 0))]);
        }
    }

    $input_name = isset($_POST['input_name']) ? sanitize_key(wp_unslash($_POST['input_name'])) : 'file';
    if (!isset($_FILES[$input_name])) {
        wp_send_json_error(['message' => 'فایلی انتخاب نشده است.']);
    }

    if (!function_exists('mlr_store_document_upload_file')) {
        wp_send_json_error(['message' => 'سرویس ذخیره فایل در دسترس نیست.']);
    }

    $stored = mlr_store_document_upload_file($_FILES[$input_name]);
    if (is_wp_error($stored)) {
        wp_send_json_error(['message' => $stored->get_error_message()]);
    }

    $response = [
        'url'  => $stored['url'],
        'type' => $stored['type'] ?? '',
    ];

    if ($request_id > 0) {
        $response['token'] = mlr_create_document_upload_token(
            $stored['url'],
            $request_id,
            $field_key
        );
        if ($context === 'guarantor' && function_exists('mlr_schedule_request_staged_upload_cleanup')) {
            mlr_schedule_request_staged_upload_cleanup($stored['url'], $request_id, $field_key, 'guarantor');
        }
    } elseif (function_exists('mlr_issue_initial_upload_token')) {
        $initial_token = mlr_issue_initial_upload_token($stored['url'], $field_key, $context);
        if (is_wp_error($initial_token)) {
            if (function_exists('mlr_delete_local_document_uploads')) {
                mlr_delete_local_document_uploads([$stored['url']]);
            }
            wp_send_json_error(['message' => $initial_token->get_error_message()]);
        }
        $response['token'] = $initial_token;
    }

    if ($restore_blog) {
        restore_current_blog();
        $restore_blog = false;
    }
    wp_send_json_success($response);
}
