<?php
if (!defined('ABSPATH')) {
    exit;
}

add_action('wp_ajax_mlr_submit_cooperation_request', 'mlr_submit_cooperation_request_callback');
add_action('wp_ajax_nopriv_mlr_submit_cooperation_request', 'mlr_submit_cooperation_request_callback');

function mlr_submit_cooperation_request_callback() {
    while (ob_get_level()) {
        ob_end_clean();
    }

    if (check_ajax_referer('mlr_cooperation_request_action', '_wpnonce', false) === false) {
        wp_send_json_error(['message' => 'لطفاً صفحه را یک‌بار رفرش کرده و مجدداً ارسال کنید. (اعتبار فرم منقضی شده است)']);
        return;
    }

    if (!function_exists('mlr_get_all_cooperation_fields')) {
        wp_send_json_error(['message' => 'خطا در پیکربندی سیستم. لطفاً با مدیر سایت تماس بگیرید.']);
        return;
    }

    try {
        mlr_submit_cooperation_request_handle();
    } catch (Throwable $e) {
        if (function_exists('mlr_log')) {
            mlr_log('Cooperation submit exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        }
        wp_send_json_error(['message' => 'خطای غیرمنتظره در ثبت درخواست. لطفاً دوباره تلاش کنید یا با پشتیبانی تماس بگیرید.']);
    }
}

/**
 * نرمال‌سازی آرایهٔ URLهای آپلودشده به آرایه‌ای از رشته‌های معتبر
 */
function mlr_normalize_uploaded_urls($input) {
    if (is_string($input)) {
        $input = trim($input);
        return $input !== '' ? [esc_url_raw($input)] : [];
    }
    if (!is_array($input)) {
        return [];
    }
    $out = [];
    foreach ($input as $item) {
        $url = is_string($item) ? trim($item) : '';
        if ($url !== '') {
            $clean = esc_url_raw($url);
            if ($clean !== '') {
                $out[] = $clean;
            }
        }
    }
    return $out;
}

function mlr_submit_cooperation_request_handle() {
    global $wpdb;
    $errors = [];
    $data = [];
    $files = [];
    $initial_upload_tokens = [];
    $direct_upload_urls = [];

    if (function_exists('mlr_rate_limit_check')) {
        $limit = mlr_rate_limit_check('cooperation_submit', 10, HOUR_IN_SECONDS);
        if (is_wp_error($limit)) {
            wp_send_json_error(['message' => $limit->get_error_message()]);
            return;
        }
    }

    $request_type = isset($_POST['request_type']) ? sanitize_text_field(wp_unslash($_POST['request_type'])) : '';
    if (empty($request_type) || !in_array($request_type, ['individual', 'legal'], true)) {
        wp_send_json_error(['message' => 'نوع درخواست نامعتبر است.']);
        return;
    }

    // Idempotency/serialization for public submissions. This runs before any file I/O so a
    // double-click or network retry cannot create duplicate rows or reuse the same staged upload.
    $client_identity = function_exists('mlr_get_client_identity') ? mlr_get_client_identity() : (string) ($_SERVER['REMOTE_ADDR'] ?? 'guest');
    $fingerprint_payload = [];
    foreach ($_POST as $key => $value) {
        if (in_array($key, ['_wpnonce', 'action'], true) || is_array($value) || is_object($value)) {
            continue;
        }
        $fingerprint_payload[sanitize_key($key)] = (string) wp_unslash($value);
    }
    ksort($fingerprint_payload);
    $submit_fingerprint = hash('sha256', $client_identity . '|' . $request_type . '|' . wp_json_encode($fingerprint_payload));
    $submit_lock_key = 'mlr_coop_submit_lock_' . substr(hash('sha256', $client_identity), 0, 32);
    $submit_success_key = 'mlr_coop_submit_ok_' . substr($submit_fingerprint, 0, 40);
    $previous_success = get_transient($submit_success_key);
    if ($previous_success) {
        wp_send_json_success(['message' => 'این درخواست قبلاً با موفقیت ثبت شده است.', 'request_id' => absint($previous_success)]);
        return;
    }
    if (get_transient($submit_lock_key)) {
        wp_send_json_error(['message' => 'درخواست شما در حال ثبت است. لطفاً چند ثانیه صبر کنید و از ارسال مجدد خودداری کنید.']);
        return;
    }
    set_transient($submit_lock_key, 1, 45);

    $all_fields = mlr_get_all_cooperation_fields();
    $default_config = [];
    foreach ($all_fields as $key => $info) {
        $default_config[$key] = ['visible' => !empty($info['default_visible']), 'required' => !empty($info['default_required'])];
    }
    $saved_config = get_option('mlr_cooperation_form_config', []);
    if (!is_array($saved_config)) {
        $saved_config = [];
    }
    $form_config = wp_parse_args($saved_config, $default_config);

    foreach ($all_fields as $field_key => $field_info) {
        if ($field_info['form'] !== 'both' && $field_info['form'] !== $request_type) {
            continue;
        }

        $config = isset($form_config[$field_key]) && is_array($form_config[$field_key])
            ? wp_parse_args($form_config[$field_key], $default_config[$field_key])
            : $default_config[$field_key];
        $is_visible = !empty($config['visible']);
        $is_required = !empty($config['required']);

        if ($field_info['type'] === 'file') {
            if (!$is_visible) {
                continue;
            }

            $pre_uploaded = null;
            $post_key = 'mlr_uploaded_' . $field_key;
            if (isset($_POST[$post_key]) && is_string($_POST[$post_key])) {
                $parsed = function_exists('mlr_parse_initial_staged_uploads')
                    ? mlr_parse_initial_staged_uploads($_POST[$post_key], $field_key, 'cooperation_request', 20)
                    : new WP_Error('mlr_upload_proof_unavailable', 'اعتبارسنجی فایل‌های بارگذاری‌شده در دسترس نیست.');
                if (is_wp_error($parsed)) {
                    $errors[] = $parsed->get_error_message();
                    $pre_uploaded = [];
                } else {
                    $pre_uploaded = $parsed['urls'];
                    $initial_upload_tokens = array_merge($initial_upload_tokens, $parsed['tokens']);
                }
            }

            if ($pre_uploaded !== null && !empty($pre_uploaded)) {
                $files[$field_key] = array_values($pre_uploaded);
            } elseif (isset($_FILES[$field_key]) && is_array($_FILES[$field_key])) {
                if (!function_exists('wp_handle_upload')) {
                    require_once ABSPATH . 'wp-admin/includes/file.php';
                    require_once ABSPATH . 'wp-admin/includes/image.php';
                    require_once ABSPATH . 'wp-admin/includes/media.php';
                }

                $allowed_mimes = ['jpg|jpeg|jpe' => 'image/jpeg', 'png' => 'image/png', 'pdf' => 'application/pdf'];
                $uploaded_files_for_field = [];
                $file_data_arr = $_FILES[$field_key];

                $rearranged_files = [];
                if (isset($file_data_arr['name']) && is_array($file_data_arr['name'])) {
                    foreach ($file_data_arr['name'] as $idx => $name) {
                        if (isset($file_data_arr['error'][$idx]) && $file_data_arr['error'][$idx] !== UPLOAD_ERR_NO_FILE) {
                            $rearranged_files[] = [
                                'name'     => $file_data_arr['name'][$idx],
                                'type'     => $file_data_arr['type'][$idx],
                                'tmp_name' => $file_data_arr['tmp_name'][$idx],
                                'error'    => $file_data_arr['error'][$idx],
                                'size'     => $file_data_arr['size'][$idx]
                            ];
                        }
                    }
                } elseif (isset($file_data_arr['name']) && isset($file_data_arr['error']) && $file_data_arr['error'] !== UPLOAD_ERR_NO_FILE) {
                    $rearranged_files[] = $file_data_arr;
                }

                foreach ($rearranged_files as $single_file) {
                    if (!isset($single_file['error']) || $single_file['error'] !== UPLOAD_ERR_OK) {
                        continue;
                    }
                    $file_type = wp_check_filetype(basename($single_file['name']), $allowed_mimes);
                    if (empty($file_type['ext']) || empty($file_type['type']) || !in_array($file_type['type'], array_values($allowed_mimes), true)) {
                        $errors[] = 'نوع فایل «' . esc_html(basename($single_file['name'])) . '» مجاز نیست.';
                        continue;
                    }
                    if (isset($single_file['size']) && $single_file['size'] > 5 * 1024 * 1024) {
                        $errors[] = 'حجم فایل «' . esc_html(basename($single_file['name'])) . '» بیشتر از ۵ مگابایت است.';
                        continue;
                    }
                    $clean_name = sanitize_file_name($single_file['name']);
                    $single_file['name'] = $clean_name;
                    $upload_overrides = ['test_form' => false, 'mimes' => $allowed_mimes];
                    $movefile = wp_handle_upload($single_file, $upload_overrides);
                    if ($movefile && !isset($movefile['error'])) {
                        $uploaded_files_for_field[] = $movefile['url'];
                        $direct_upload_urls[] = $movefile['url'];
                    } else {
                        $errors[] = 'خطا در آپلود فایل: ' . (isset($movefile['error']) ? $movefile['error'] : 'نامشخص');
                    }
                }

                if (!empty($uploaded_files_for_field)) {
                    $files[$field_key] = $uploaded_files_for_field;
                } elseif ($is_required) {
                    $errors[] = 'فیلد «' . esc_html($field_info['label']) . '» اجباری است.';
                }
            } elseif ($is_required) {
                $errors[] = 'فیلد «' . esc_html($field_info['label']) . '» اجباری است.';
            }

        } else {
            if (!$is_visible) {
                $data[$field_key] = '';
                continue;
            }
            $value = isset($_POST[$field_key]) ? wp_unslash($_POST[$field_key]) : '';
            if (is_array($value)) {
                $value = '';
            }
            $value = is_string($value) ? $value : '';
            switch ($field_info['type']) {
                case 'email':
                    $sanitized_value = sanitize_email($value);
                    if ($value !== '' && !is_email($sanitized_value)) {
                        $errors[] = 'فرمت ایمیل نامعتبر است.';
                        $sanitized_value = '';
                    }
                    break;
                case 'textarea':
                    $sanitized_value = sanitize_textarea_field($value);
                    break;
                case 'text':
                case 'select':
                default:
                    $sanitized_value = sanitize_text_field($value);
                    break;
            }
            if ($is_required && trim((string) $sanitized_value) === '') {
                $errors[] = 'فیلد «' . esc_html($field_info['label']) . '» اجباری است.';
            }
            $data[$field_key] = is_scalar($sanitized_value) ? (string) $sanitized_value : '';
        }
    }

    if (!empty($errors)) {
        if ($direct_upload_urls && function_exists('mlr_delete_local_document_uploads')) mlr_delete_local_document_uploads($direct_upload_urls);
        delete_transient($submit_lock_key);
        wp_send_json_error(['message' => implode('<br>', $errors)]);
        return;
    }

    $table_requests = $wpdb->prefix . 'cooperation_requests';
    $table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_requests)) === $table_requests;
    if (!$table_exists) {
        if ($direct_upload_urls && function_exists('mlr_delete_local_document_uploads')) mlr_delete_local_document_uploads($direct_upload_urls);
        if (function_exists('mlr_log')) {
            mlr_log('Cooperation table missing: ' . $table_requests);
        }
        delete_transient($submit_lock_key);
        wp_send_json_error(['message' => 'خطا در پایگاه داده. لطفاً افزونه را مجدداً فعال کنید یا با پشتیبانی تماس بگیرید.']);
        return;
    }

    $data_json = wp_json_encode($data, JSON_UNESCAPED_UNICODE);
    $files_json = wp_json_encode($files, JSON_UNESCAPED_UNICODE);
    if ($data_json === false || $files_json === false) {
        if ($direct_upload_urls && function_exists('mlr_delete_local_document_uploads')) mlr_delete_local_document_uploads($direct_upload_urls);
        if (function_exists('mlr_log')) {
            mlr_log('Cooperation JSON encode failed.');
        }
        delete_transient($submit_lock_key);
        wp_send_json_error(['message' => 'خطا در پردازش اطلاعات. لطفاً دوباره تلاش کنید.']);
        return;
    }

    $max_retries = 5;
    $retry_count = 0;
    $inserted = false;
    $new_request_id = 0;
    $last_db_error = '';

    while ($retry_count < $max_retries) {
        $token_candidate = bin2hex(random_bytes(32));
        $existing_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM `{$table_requests}` WHERE contract_token = %s", $token_candidate));
        if ($existing_id) {
            $retry_count++;
            continue;
        }

        $insert_data = [
            'request_type'   => $request_type,
            'status'        => 'در حال بررسی',
            'contract_token' => $token_candidate,
            'data'          => $data_json,
            'files'         => $files_json,
            'created_at'    => current_time('mysql')
        ];

        $result = $wpdb->insert($table_requests, $insert_data);
        if ($result !== false) {
            $inserted = true;
            $new_request_id = (int) $wpdb->insert_id;
            break;
        }
        $last_db_error = $wpdb->last_error;
        $retry_count++;
    }

    if (!$inserted) {
        if ($direct_upload_urls && function_exists('mlr_delete_local_document_uploads')) mlr_delete_local_document_uploads($direct_upload_urls);
        if (function_exists('mlr_log')) {
            mlr_log('Cooperation insert failed after retries.', ['db_error' => $last_db_error]);
        }
        delete_transient($submit_lock_key);
        wp_send_json_error(['message' => 'خطا در ذخیره درخواست. لطفاً چند دقیقه دیگر تلاش کنید.']);
        return;
    }

    if (function_exists('mlr_consume_initial_upload_tokens') && !empty($initial_upload_tokens)) {
        mlr_consume_initial_upload_tokens($initial_upload_tokens);
    }
    set_transient($submit_success_key, $new_request_id, 5 * MINUTE_IN_SECONDS);
    delete_transient($submit_lock_key);

    try {
        if (function_exists('mlr_send_sms_on_cooperation_status_change')) {
            mlr_send_sms_on_cooperation_status_change($new_request_id, 'در حال بررسی');
        }
    } catch (Throwable $e) {
        if (function_exists('mlr_log')) {
            mlr_log('Cooperation SMS after submit: ' . $e->getMessage());
        }
    }

    try {
        if (function_exists('mlr_create_notification')) {
            mlr_create_notification('new_coop', 'درخواست همکاری جدید ثبت شد.', admin_url('admin.php?page=mlr-cooperation-main&s=' . $new_request_id));
        }
    } catch (Throwable $e) {
        if (function_exists('mlr_log')) {
            mlr_log('Cooperation notification after submit: ' . $e->getMessage());
        }
    }

    wp_send_json_success(['message' => 'درخواست همکاری شما با موفقیت ثبت گردید.']);
}
