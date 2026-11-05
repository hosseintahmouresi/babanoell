<?php
/**
 * AJAX: secure chunked upload for larger documents.
 */
if (!defined('ABSPATH')) {
    exit;
}

add_action('wp_ajax_mlr_upload_chunk', 'mlr_upload_chunk_callback');
add_action('wp_ajax_nopriv_mlr_upload_chunk', 'mlr_upload_chunk_callback');

function mlr_upload_chunk_callback() {
    while (ob_get_level()) {
        ob_end_clean();
    }

    $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
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

    $context = isset($_POST['context']) ? sanitize_text_field(wp_unslash($_POST['context'])) : '';
    if (!in_array($context, ['loan_request', 'cooperation_request', 'guarantor'], true)) {
        wp_send_json_error(['message' => 'پارامتر نوع فرم معتبر نیست.']);
    }

    $upload_id = isset($_POST['upload_id']) ? sanitize_text_field(wp_unslash($_POST['upload_id'])) : '';
    $chunk_index = isset($_POST['chunk_index']) ? absint($_POST['chunk_index']) : 0;
    $total_chunks = isset($_POST['total_chunks']) ? absint($_POST['total_chunks']) : 0;
    $total_size = isset($_POST['total_size']) ? absint($_POST['total_size']) : 0;
    $original_name = isset($_POST['original_name']) ? sanitize_file_name(wp_unslash($_POST['original_name'])) : '';
    $field_key = isset($_POST['field_key']) ? sanitize_key(wp_unslash($_POST['field_key'])) : '';
    $request_id = isset($_POST['request_id']) ? absint($_POST['request_id']) : 0;
    $portal_context = isset($_POST['portal_context']) ? sanitize_key(wp_unslash($_POST['portal_context'])) : 'auto';
    if (!in_array($portal_context, ['acceptor', 'applicant', 'auto'], true)) {
        $portal_context = 'auto';
    }

    if ($context === 'guarantor') {
        if ($request_id <= 0) {
            wp_send_json_error(['message' => 'شناسه پرونده برای بارگذاری مدرک ضامن معتبر نیست.']);
        }
        if (!function_exists('mlr_is_valid_guarantor_upload_field_key') || !mlr_is_valid_guarantor_upload_field_key($field_key)) {
            wp_send_json_error(['message' => 'نوع مدرک ضامن معتبر نیست.']);
        }
    }

    if (!preg_match('/^[A-Za-z0-9_-]{10,80}$/', $upload_id)) {
        wp_send_json_error(['message' => 'شناسه آپلود معتبر نیست.']);
    }

    $max_bytes = function_exists('mlr_document_upload_max_bytes') ? mlr_document_upload_max_bytes() : 10 * 1024 * 1024;
    $max_chunks = 20;
    if ($total_chunks < 1 || $total_chunks > $max_chunks || $chunk_index >= $total_chunks) {
        wp_send_json_error(['message' => 'تعداد تکه‌های فایل معتبر نیست.']);
    }
    if ($total_size < 1 || $total_size > $max_bytes) {
        wp_send_json_error(['message' => sprintf('حداکثر حجم هر فایل %s مگابایت است.', number_format_i18n($max_bytes / 1048576, 0))]);
    }
    if ($original_name === '') {
        wp_send_json_error(['message' => 'نام فایل معتبر نیست.']);
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
    } elseif ($chunk_index === 0 && function_exists('mlr_rate_limit_check')) {
        $limit_check = mlr_rate_limit_check('initial_chunk_upload', 30, HOUR_IN_SECONDS, [$context]);
        if (is_wp_error($limit_check)) {
            wp_send_json_error(['message' => $limit_check->get_error_message(), 'retry_after' => (int) (($limit_check->get_error_data()['retry_after'] ?? 0))]);
        }
    }

    if (!isset($_FILES['chunk']) || (int) ($_FILES['chunk']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $error_code = (int) ($_FILES['chunk']['error'] ?? UPLOAD_ERR_NO_FILE);
        $message = function_exists('mlr_get_upload_error_message') ? mlr_get_upload_error_message($error_code) : 'تکه فایل دریافت نشد.';
        wp_send_json_error(['message' => $message]);
    }

    $chunk_size = (int) ($_FILES['chunk']['size'] ?? 0);
    if ($chunk_size <= 0 || $chunk_size > 2 * 1024 * 1024) {
        wp_send_json_error(['message' => 'اندازه تکه فایل معتبر نیست.']);
    }

    $owner_id = is_user_logged_in() ? get_current_user_id() : 0;
    $meta_key = 'mlr_chunk_' . md5($upload_id . '|' . wp_salt('nonce'));
    $meta = get_transient($meta_key);
    $expected_meta = [
        'owner_id'     => $owner_id,
        'request_id'   => $request_id,
        'field_key'    => $field_key,
        'context'      => $context,
        'portal_context'=> $portal_context,
        'total_chunks' => $total_chunks,
        'total_size'   => $total_size,
        'original_name'=> $original_name,
    ];

    if ($meta === false) {
        $meta = $expected_meta;
        set_transient($meta_key, $meta, HOUR_IN_SECONDS);
    } else {
        foreach ($expected_meta as $key => $value) {
            if (!array_key_exists($key, $meta) || (string) $meta[$key] !== (string) $value) {
                wp_send_json_error(['message' => 'اطلاعات آپلود با درخواست اولیه مطابقت ندارد.']);
            }
        }
    }

    $temp_root = trailingslashit(get_temp_dir()) . 'mlr-document-chunks';
    if (!wp_mkdir_p($temp_root)) {
        wp_send_json_error(['message' => 'پوشه موقت آپلود در دسترس نیست.']);
    }

    $upload_dir = trailingslashit($temp_root) . hash('sha256', $upload_id . '|' . $owner_id . '|' . $request_id);
    if (!wp_mkdir_p($upload_dir)) {
        wp_send_json_error(['message' => 'ایجاد پوشه موقت فایل ممکن نشد.']);
    }

    $part_path = trailingslashit($upload_dir) . 'part-' . $chunk_index;
    if (!move_uploaded_file($_FILES['chunk']['tmp_name'], $part_path)) {
        wp_send_json_error(['message' => 'ذخیره تکه فایل ممکن نشد.']);
    }

    // Keep accepting chunks until every part exists. The client sends them sequentially,
    // but this also tolerates a retry of the final chunk.
    for ($i = 0; $i < $total_chunks; $i++) {
        if (!is_file(trailingslashit($upload_dir) . 'part-' . $i)) {
            wp_send_json_success([
                'chunk_index' => $chunk_index,
                'upload_id'   => $upload_id,
                'complete'    => false,
            ]);
        }
    }

    $assembled_path = trailingslashit($upload_dir) . 'assembled-' . wp_generate_password(12, false, false);
    $out = @fopen($assembled_path, 'wb');
    if (!$out) {
        wp_send_json_error(['message' => 'ساخت فایل نهایی ممکن نشد.']);
    }

    $assembled_size = 0;
    $assembly_ok = true;
    for ($i = 0; $i < $total_chunks; $i++) {
        $part = trailingslashit($upload_dir) . 'part-' . $i;
        $in = @fopen($part, 'rb');
        if (!$in) {
            $assembly_ok = false;
            break;
        }
        while (!feof($in)) {
            $buffer = fread($in, 1024 * 1024);
            if ($buffer === false) {
                $assembly_ok = false;
                break 2;
            }
            $written = fwrite($out, $buffer);
            if ($written === false) {
                $assembly_ok = false;
                break 2;
            }
            $assembled_size += $written;
            if ($assembled_size > $max_bytes) {
                $assembly_ok = false;
                break 2;
            }
        }
        fclose($in);
    }
    fclose($out);

    if (!$assembly_ok || $assembled_size !== $total_size) {
        @unlink($assembled_path);
        foreach (glob(trailingslashit($upload_dir) . 'part-*') ?: [] as $part) {
            @unlink($part);
        }
        @rmdir($upload_dir);
        delete_transient($meta_key);
        wp_send_json_error(['message' => 'فایل نهایی ناقص است؛ لطفاً دوباره بارگذاری کنید.']);
    }

    $allowed_mimes = function_exists('mlr_document_upload_allowed_mimes') ? mlr_document_upload_allowed_mimes() : [
        'jpg|jpeg|jpe' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
        'pdf' => 'application/pdf',
    ];
    $check = wp_check_filetype_and_ext($assembled_path, $original_name, $allowed_mimes);
    $ext = strtolower((string) ($check['ext'] ?? ''));
    $type = strtolower((string) ($check['type'] ?? ''));
    $allowed_exts = function_exists('mlr_document_upload_allowed_extensions') ? mlr_document_upload_allowed_extensions() : ['jpg', 'jpeg', 'jpe', 'png', 'webp', 'pdf'];
    if (!in_array($ext, $allowed_exts, true) || !in_array($type, array_values($allowed_mimes), true)) {
        @unlink($assembled_path);
        foreach (glob(trailingslashit($upload_dir) . 'part-*') ?: [] as $part) {
            @unlink($part);
        }
        @rmdir($upload_dir);
        delete_transient($meta_key);
        wp_send_json_error(['message' => 'نوع فایل مجاز نیست. فقط JPG، PNG، WEBP و PDF قابل بارگذاری است.']);
    }

    if (!function_exists('wp_handle_sideload')) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
    }

    $sideload = [
        'name'     => $original_name,
        'type'     => $type,
        'tmp_name' => $assembled_path,
        'error'    => UPLOAD_ERR_OK,
        'size'     => $assembled_size,
    ];
    $stored = wp_handle_sideload($sideload, [
        'test_form' => false,
        'mimes'     => $allowed_mimes,
    ]);

    foreach (glob(trailingslashit($upload_dir) . 'part-*') ?: [] as $part) {
        @unlink($part);
    }
    @unlink($assembled_path);
    @rmdir($upload_dir);
    delete_transient($meta_key);

    if (!$stored || isset($stored['error']) || empty($stored['url'])) {
        wp_send_json_error(['message' => isset($stored['error']) ? $stored['error'] : 'ذخیره فایل نهایی ممکن نشد.']);
    }

    $response = [
        'url'      => esc_url_raw($stored['url']),
        'type'     => $stored['type'] ?? $type,
        'complete' => true,
    ];
    if ($request_id > 0) {
        $response['token'] = mlr_create_document_upload_token($response['url'], $request_id, $field_key);
        if ($context === 'guarantor' && function_exists('mlr_schedule_request_staged_upload_cleanup')) {
            mlr_schedule_request_staged_upload_cleanup($response['url'], $request_id, $field_key, 'guarantor');
        }
    } elseif (function_exists('mlr_issue_initial_upload_token')) {
        $initial_token = mlr_issue_initial_upload_token($response['url'], $field_key, $context);
        if (is_wp_error($initial_token)) {
            if (function_exists('mlr_delete_local_document_uploads')) {
                mlr_delete_local_document_uploads([$response['url']]);
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
