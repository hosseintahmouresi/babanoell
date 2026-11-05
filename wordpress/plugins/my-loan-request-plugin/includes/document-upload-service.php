<?php
/**
 * Central document-upload authorization, validation and storage helpers.
 *
 * Keeps every frontend upload path aligned with the same ownership and
 * document-stage rules. This is especially important for applicant-origin
 * requests that are assigned to an acceptor through acceptor_user_id.
 */
if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('mlr_document_upload_allowed_mimes')) {
    function mlr_document_upload_allowed_mimes() {
        return [
            'jpg|jpeg|jpe' => 'image/jpeg',
            'png'          => 'image/png',
            'webp'         => 'image/webp',
            'pdf'          => 'application/pdf',
        ];
    }
}

if (!function_exists('mlr_document_upload_allowed_extensions')) {
    function mlr_document_upload_allowed_extensions() {
        return ['jpg', 'jpeg', 'jpe', 'png', 'webp', 'pdf'];
    }
}

if (!function_exists('mlr_document_upload_max_bytes')) {
    function mlr_document_upload_max_bytes() {
        $max_bytes = 10 * 1024 * 1024; // 10 MB per file.
        if (function_exists('wp_max_upload_size')) {
            $wordpress_limit = (int) wp_max_upload_size();
            if ($wordpress_limit > 0) {
                $max_bytes = min($max_bytes, $wordpress_limit);
            }
        }
        return (int) apply_filters('mlr_document_upload_max_bytes', $max_bytes);
    }
}

if (!function_exists('mlr_document_upload_max_files')) {
    function mlr_document_upload_max_files($field_key = '') {
        $default = in_array($field_key, ['signed_contract', 'signed_invoice'], true) ? 1 : 20;
        return max(1, (int) apply_filters('mlr_document_upload_max_files', $default, $field_key));
    }
}

if (!function_exists('mlr_is_acceptor_side_request_owner')) {
    function mlr_is_acceptor_side_request_owner($request, $user_id) {
        if (!$request || $user_id <= 0) {
            return false;
        }

        $origin = trim((string) (is_array($request) ? ($request['request_origin'] ?? '') : ($request->request_origin ?? '')));
        $request_user_id = absint(is_array($request) ? ($request['user_id'] ?? 0) : ($request->user_id ?? 0));
        $applicant_user_id = absint(is_array($request) ? ($request['applicant_user_id'] ?? 0) : ($request->applicant_user_id ?? 0));
        $acceptor_user_id = absint(is_array($request) ? ($request['acceptor_user_id'] ?? 0) : ($request->acceptor_user_id ?? 0));

        // Current schema: when acceptor_user_id exists it is authoritative and
        // prevents a stale legacy user_id from granting access to a previous acceptor.
        if ($acceptor_user_id > 0) {
            return $acceptor_user_id === $user_id;
        }

        // Native/legacy acceptor-portal requests are owned by user_id.
        if ($request_user_id === $user_id && $origin !== 'applicant_portal') {
            return true;
        }

        // Compatibility for older applicant-origin rows that were assigned by changing
        // user_id but did not receive acceptor_user_id during a partial migration.
        if ($request_user_id === $user_id && $origin === 'applicant_portal' && $applicant_user_id !== $user_id) {
            return true;
        }

        return false;
    }
}

if (!function_exists('mlr_is_applicant_side_request_owner')) {
    function mlr_is_applicant_side_request_owner($request, $user_id) {
        if (!$request || $user_id <= 0) {
            return false;
        }

        $origin = trim((string) (is_array($request) ? ($request['request_origin'] ?? '') : ($request->request_origin ?? '')));
        $request_user_id = absint(is_array($request) ? ($request['user_id'] ?? 0) : ($request->user_id ?? 0));
        $applicant_user_id = absint(is_array($request) ? ($request['applicant_user_id'] ?? 0) : ($request->applicant_user_id ?? 0));

        if ($origin !== 'applicant_portal') {
            return false;
        }

        if ($applicant_user_id > 0) {
            return $applicant_user_id === $user_id;
        }

        // Legacy applicant-origin rows created before applicant_user_id existed.
        return $request_user_id === $user_id;
    }
}

if (!function_exists('mlr_get_authorized_document_request')) {
    /**
     * Fetch and authorize a request for a frontend document operation.
     *
     * @param int          $request_id
     * @param string       $portal_context acceptor|applicant|auto
     * @param array|null   $allowed_stages Canonical document stages, null skips stage check.
     * @return object|WP_Error
     */
    function mlr_get_authorized_document_request($request_id, $portal_context = 'auto', $allowed_stages = null) {
        $request_id = absint($request_id);
        if ($request_id <= 0) {
            return new WP_Error('mlr_invalid_request_id', 'شناسه درخواست نامعتبر است.');
        }

        if (!is_user_logged_in()) {
            return new WP_Error('mlr_document_upload_login_required', 'برای بارگذاری مدارک، لطفاً ابتدا وارد شوید.');
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'loan_requests';
        $request = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM `{$table_name}` WHERE id = %d LIMIT 1",
            $request_id
        ));

        if (!$request) {
            return new WP_Error('mlr_document_upload_request_not_found', 'درخواست مورد نظر یافت نشد.');
        }

        $user_id = get_current_user_id();
        $portal_context = sanitize_key((string) $portal_context);
        $allowed = false;

        if ($portal_context === 'acceptor') {
            $allowed = mlr_is_acceptor_side_request_owner($request, $user_id);
        } elseif ($portal_context === 'applicant') {
            $allowed = mlr_is_applicant_side_request_owner($request, $user_id);
        } else {
            $allowed = function_exists('mlr_can_access_loan_request')
                ? mlr_can_access_loan_request($request, $user_id)
                : (mlr_is_acceptor_side_request_owner($request, $user_id) || mlr_is_applicant_side_request_owner($request, $user_id));
        }

        if (!$allowed) {
            return new WP_Error('mlr_document_upload_forbidden', 'شما اجازه بارگذاری مدارک برای این پرونده را ندارید.');
        }

        if (is_array($allowed_stages)) {
            $stage = function_exists('mlr_get_document_flow_stage')
                ? mlr_get_document_flow_stage($request->status ?? '')
                : '';
            if (!in_array($stage, $allowed_stages, true)) {
                return new WP_Error('mlr_document_upload_invalid_state', 'پرونده در وضعیت معتبر برای این نوع بارگذاری نیست.');
            }
        }

        return $request;
    }
}

if (!function_exists('mlr_document_upload_url_to_path')) {
    function mlr_document_upload_url_to_path($url) {
        $url = esc_url_raw((string) $url);
        if ($url === '') {
            return '';
        }

        $uploads = wp_upload_dir(null, false);
        if (!empty($uploads['error']) || empty($uploads['baseurl']) || empty($uploads['basedir'])) {
            return '';
        }

        $clean_url = strtok($url, '?#');
        $base_url = untrailingslashit(set_url_scheme($uploads['baseurl'], is_ssl() ? 'https' : 'http'));
        $alternate_base_url = untrailingslashit(set_url_scheme($uploads['baseurl'], is_ssl() ? 'http' : 'https'));

        $relative = '';
        foreach ([$base_url, $alternate_base_url] as $candidate_base) {
            if (strpos($clean_url, $candidate_base . '/') === 0) {
                $relative = substr($clean_url, strlen($candidate_base) + 1);
                break;
            }
        }

        if ($relative === '') {
            return '';
        }

        $relative = ltrim(rawurldecode($relative), '/');
        if ($relative === '' || strpos($relative, '..') !== false) {
            return '';
        }

        $path = trailingslashit($uploads['basedir']) . $relative;
        $real_path = realpath($path);
        $real_base = realpath($uploads['basedir']);
        if (!$real_path || !$real_base || strpos($real_path, trailingslashit($real_base)) !== 0 || !is_file($real_path)) {
            return '';
        }

        return $real_path;
    }
}

if (!function_exists('mlr_validate_local_document_upload_url')) {
    function mlr_validate_local_document_upload_url($url, &$error_message = '') {
        $path = mlr_document_upload_url_to_path($url);
        if ($path === '') {
            $error_message = 'فایل بارگذاری‌شده در فضای مجاز سایت یافت نشد.';
            return false;
        }

        $max_bytes = mlr_document_upload_max_bytes();
        $size = @filesize($path);
        if ($size === false || $size <= 0 || $size > $max_bytes) {
            $error_message = 'حجم فایل بارگذاری‌شده معتبر نیست.';
            return false;
        }

        $allowed_mimes = mlr_document_upload_allowed_mimes();
        $check = wp_check_filetype_and_ext($path, basename($path), $allowed_mimes);
        $ext = strtolower((string) ($check['ext'] ?? ''));
        $type = strtolower((string) ($check['type'] ?? ''));
        if (!in_array($ext, mlr_document_upload_allowed_extensions(), true) || !in_array($type, array_values($allowed_mimes), true)) {
            $error_message = 'نوع فایل بارگذاری‌شده مجاز نیست.';
            return false;
        }

        return esc_url_raw((string) $url);
    }
}

if (!function_exists('mlr_create_document_upload_token')) {
    function mlr_create_document_upload_token($url, $request_id, $field_key, $user_id = 0, $ttl = 3600) {
        $user_id = $user_id > 0 ? absint($user_id) : get_current_user_id();
        $request_id = absint($request_id);
        $field_key = sanitize_key($field_key);
        $expires = time() + max(300, min(7200, absint($ttl)));
        $payload = implode('|', [$user_id, $request_id, $field_key, $expires, hash('sha256', (string) $url)]);
        $signature = hash_hmac('sha256', $payload, wp_salt('nonce'));
        return $expires . '.' . $signature;
    }
}

if (!function_exists('mlr_verify_document_upload_token')) {
    function mlr_verify_document_upload_token($token, $url, $request_id, $field_key, $user_id = 0) {
        $token = (string) $token;
        if (!preg_match('/^(\d{10})\.([a-f0-9]{64})$/', $token, $matches)) {
            return false;
        }

        $expires = (int) $matches[1];
        if ($expires < time() || $expires > time() + 7200) {
            return false;
        }

        $user_id = $user_id > 0 ? absint($user_id) : get_current_user_id();
        $request_id = absint($request_id);
        $field_key = sanitize_key($field_key);
        $payload = implode('|', [$user_id, $request_id, $field_key, $expires, hash('sha256', (string) $url)]);
        $expected = hash_hmac('sha256', $payload, wp_salt('nonce'));
        return hash_equals($expected, $matches[2]);
    }
}


if (!function_exists('mlr_guarantor_upload_fields')) {
    function mlr_guarantor_upload_fields() {
        return [
            'national_card', 'id_card', 'payslip', 'sana', 'check',
            'residence_deed', 'work_deed', 'account_statement', 'extra_docs',
        ];
    }
}

if (!function_exists('mlr_guarantor_upload_field_key')) {
    function mlr_guarantor_upload_field_key($guarantor_index, $field) {
        $guarantor_index = ((int) $guarantor_index === 2) ? 2 : 1;
        $field = sanitize_key((string) $field);
        if (!in_array($field, mlr_guarantor_upload_fields(), true)) {
            return '';
        }
        return 'guarantor_' . $guarantor_index . '_' . $field;
    }
}

if (!function_exists('mlr_is_valid_guarantor_upload_field_key')) {
    function mlr_is_valid_guarantor_upload_field_key($field_key) {
        $field_key = sanitize_key((string) $field_key);
        foreach ([1, 2] as $index) {
            foreach (mlr_guarantor_upload_fields() as $field) {
                if (hash_equals(mlr_guarantor_upload_field_key($index, $field), $field_key)) {
                    return true;
                }
            }
        }
        return false;
    }
}

if (!function_exists('mlr_parse_guarantor_staged_uploads')) {
    /**
     * Validate request-bound staged guarantor uploads.
     *
     * @return array|WP_Error Map of guarantor field => validated local URL.
     */
    function mlr_parse_guarantor_staged_uploads($raw, $request_id, $guarantor_index) {
        if ($raw === null || $raw === '') {
            return [];
        }
        $decoded = is_string($raw) ? json_decode(wp_unslash($raw), true) : $raw;
        if (!is_array($decoded)) {
            return new WP_Error('mlr_invalid_guarantor_uploads', 'اطلاعات مدارک ضامن معتبر نیست.');
        }
        if (count($decoded) > count(mlr_guarantor_upload_fields())) {
            return new WP_Error('mlr_too_many_guarantor_uploads', 'تعداد مدارک ضامن معتبر نیست.');
        }

        $result = [];
        foreach ($decoded as $field => $item) {
            $field = sanitize_key((string) $field);
            if (!in_array($field, mlr_guarantor_upload_fields(), true) || !is_array($item)) {
                return new WP_Error('mlr_invalid_guarantor_upload_field', 'یکی از مدارک ضامن معتبر نیست.');
            }
            $url = esc_url_raw((string) ($item['url'] ?? ''));
            $token = sanitize_text_field((string) ($item['token'] ?? ''));
            $field_key = mlr_guarantor_upload_field_key($guarantor_index, $field);
            if ($url === '' || $token === '' || $field_key === '' || !mlr_verify_document_upload_token($token, $url, $request_id, $field_key)) {
                return new WP_Error('mlr_invalid_guarantor_upload_token', 'اعتبار یکی از مدارک ضامن منقضی یا نامعتبر است. لطفاً فایل را دوباره انتخاب کنید.');
            }
            $error = '';
            $validated = mlr_validate_local_document_upload_url($url, $error);
            if (!$validated) {
                return new WP_Error('mlr_invalid_guarantor_upload_file', $error ?: 'فایل مدرک ضامن معتبر نیست.');
            }
            $result[$field] = $validated;
        }
        return $result;
    }
}

if (!function_exists('mlr_parse_staged_document_uploads')) {
    function mlr_parse_staged_document_uploads($raw, $request_id, $field_key, $max_files = null) {
        if ($raw === null || $raw === '') {
            return [];
        }

        if (is_string($raw)) {
            $decoded = json_decode(wp_unslash($raw), true);
        } else {
            $decoded = $raw;
        }

        if (!is_array($decoded)) {
            return new WP_Error('mlr_invalid_staged_uploads', 'اطلاعات فایل‌های بارگذاری‌شده معتبر نیست.');
        }

        $max_files = $max_files === null ? mlr_document_upload_max_files($field_key) : max(1, absint($max_files));
        if (count($decoded) > $max_files) {
            return new WP_Error('mlr_too_many_staged_uploads', sprintf('حداکثر %d فایل برای این بخش مجاز است.', $max_files));
        }

        $urls = [];
        foreach ($decoded as $item) {
            if (!is_array($item)) {
                return new WP_Error('mlr_invalid_staged_upload_item', 'یکی از فایل‌های بارگذاری‌شده معتبر نیست.');
            }

            $url = esc_url_raw((string) ($item['url'] ?? ''));
            $token = sanitize_text_field((string) ($item['token'] ?? ''));
            if ($url === '' || !mlr_verify_document_upload_token($token, $url, $request_id, $field_key)) {
                return new WP_Error('mlr_invalid_staged_upload_token', 'اعتبار یکی از فایل‌های بارگذاری‌شده منقضی یا نامعتبر است. لطفاً فایل را دوباره انتخاب کنید.');
            }

            $error = '';
            $validated_url = mlr_validate_local_document_upload_url($url, $error);
            if (!$validated_url) {
                return new WP_Error('mlr_invalid_staged_upload_file', $error ?: 'فایل بارگذاری‌شده معتبر نیست.');
            }
            $urls[] = $validated_url;
        }

        return array_values(array_unique($urls));
    }
}

if (!function_exists('mlr_normalize_php_upload_files')) {
    function mlr_normalize_php_upload_files($files) {
        if (empty($files) || !isset($files['name'])) {
            return [];
        }

        if (!is_array($files['name'])) {
            return [$files];
        }

        $normalized = [];
        $count = count($files['name']);
        for ($i = 0; $i < $count; $i++) {
            if (empty($files['name'][$i]) && (int) ($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $normalized[] = [
                'name'     => $files['name'][$i] ?? '',
                'type'     => $files['type'][$i] ?? '',
                'tmp_name' => $files['tmp_name'][$i] ?? '',
                'error'    => $files['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                'size'     => $files['size'][$i] ?? 0,
            ];
        }
        return $normalized;
    }
}

if (!function_exists('mlr_store_document_upload_file')) {
    function mlr_store_document_upload_file($file) {
        if (empty($file) || !is_array($file)) {
            return new WP_Error('mlr_missing_upload_file', 'فایل دریافت نشد.');
        }

        $error_code = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error_code !== UPLOAD_ERR_OK) {
            $message = function_exists('mlr_get_upload_error_message')
                ? mlr_get_upload_error_message($error_code)
                : 'خطا در دریافت فایل.';
            return new WP_Error('mlr_php_upload_error', $message);
        }

        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0) {
            return new WP_Error('mlr_empty_upload_file', 'فایل انتخاب‌شده خالی است.');
        }
        if ($size > mlr_document_upload_max_bytes()) {
            return new WP_Error('mlr_upload_too_large', sprintf('حداکثر حجم هر فایل %s مگابایت است.', number_format_i18n(mlr_document_upload_max_bytes() / 1048576, 0)));
        }

        $file['name'] = sanitize_file_name((string) ($file['name'] ?? ''));
        if ($file['name'] === '' || empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return new WP_Error('mlr_invalid_uploaded_file', 'فایل بارگذاری‌شده معتبر نیست.');
        }

        $allowed_mimes = mlr_document_upload_allowed_mimes();
        $check = wp_check_filetype_and_ext($file['tmp_name'], $file['name'], $allowed_mimes);
        $ext = strtolower((string) ($check['ext'] ?? ''));
        $type = strtolower((string) ($check['type'] ?? ''));
        if (!in_array($ext, mlr_document_upload_allowed_extensions(), true) || !in_array($type, array_values($allowed_mimes), true)) {
            return new WP_Error('mlr_upload_type_not_allowed', 'نوع فایل مجاز نیست. فقط JPG، PNG، WEBP و PDF قابل بارگذاری است.');
        }

        if (!function_exists('wp_handle_upload')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        $result = wp_handle_upload($file, [
            'test_form' => false,
            'mimes'     => $allowed_mimes,
        ]);

        if (!$result || isset($result['error']) || empty($result['url'])) {
            return new WP_Error('mlr_upload_store_failed', isset($result['error']) ? $result['error'] : 'خطا در ذخیره فایل.');
        }

        return [
            'url'  => esc_url_raw($result['url']),
            'file' => isset($result['file']) ? $result['file'] : '',
            'type' => isset($result['type']) ? $result['type'] : $type,
        ];
    }
}

if (!function_exists('mlr_collect_direct_document_uploads')) {
    function mlr_collect_direct_document_uploads($files_key, $max_files = null) {
        if (empty($_FILES[$files_key])) {
            return [];
        }

        $files = mlr_normalize_php_upload_files($_FILES[$files_key]);
        $max_files = $max_files === null ? mlr_document_upload_max_files($files_key) : max(1, absint($max_files));
        if (count($files) > $max_files) {
            return new WP_Error('mlr_too_many_direct_uploads', sprintf('حداکثر %d فایل برای این بخش مجاز است.', $max_files));
        }

        $urls = [];
        foreach ($files as $file) {
            $stored = mlr_store_document_upload_file($file);
            if (is_wp_error($stored)) {
                if (!empty($urls)) {
                    mlr_delete_local_document_uploads($urls);
                }
                return $stored;
            }
            $urls[] = $stored['url'];
        }
        return array_values(array_unique($urls));
    }
}

if (!function_exists('mlr_delete_local_document_uploads')) {
    function mlr_delete_local_document_uploads($urls) {
        foreach ((array) $urls as $url) {
            $path = mlr_document_upload_url_to_path($url);
            if ($path !== '' && is_file($path)) {
                @unlink($path);
            }
        }
    }
}

if (!function_exists('mlr_merge_document_urls')) {
    function mlr_merge_document_urls($existing, $new_urls) {
        $existing = is_array($existing) ? $existing : [];
        $merged = [];
        foreach (array_merge($existing, (array) $new_urls) as $url) {
            $url = esc_url_raw((string) $url);
            if ($url !== '') {
                $merged[] = $url;
            }
        }
        return array_values(array_unique($merged));
    }
}

if (!function_exists('mlr_get_request_documents_array')) {
    function mlr_get_request_documents_array($raw_documents) {
        $documents = function_exists('mlr_get_request_uploaded_documents')
            ? mlr_get_request_uploaded_documents($raw_documents)
            : maybe_unserialize($raw_documents);
        return is_array($documents) ? $documents : [];
    }
}

if (!function_exists('mlr_send_document_upload_error')) {
    function mlr_send_document_upload_error($error, $cleanup_urls = []) {
        if (!empty($cleanup_urls)) {
            mlr_delete_local_document_uploads($cleanup_urls);
        }
        $message = is_wp_error($error) ? $error->get_error_message() : (string) $error;
        wp_send_json_error(['message' => $message ?: 'خطایی در بارگذاری مدارک رخ داد.']);
    }
}

if (!function_exists('mlr_handle_completed_documents_submission')) {
    function mlr_handle_completed_documents_submission($portal_context = 'acceptor') {
        check_ajax_referer('mlr_frontend_nonce', 'nonce');

        $request_id = isset($_POST['request_id']) ? absint($_POST['request_id']) : 0;
        $request = mlr_get_authorized_document_request(
            $request_id,
            $portal_context,
            ['completion_documents', 'pending_documents']
        );
        if (is_wp_error($request)) {
            mlr_send_document_upload_error($request);
        }

        $digital_contract = function_exists('mlr_is_loan_contract_digital') && mlr_is_loan_contract_digital($request, true);
        $check_files_enabled = function_exists('mlr_completed_check_files_enabled_for_request')
            ? mlr_completed_check_files_enabled_for_request($request)
            : true;
        if ($digital_contract && (!function_exists('mlr_is_loan_contract_signed') || !mlr_is_loan_contract_signed($request_id))) {
            mlr_send_document_upload_error('ابتدا قرارداد دریافت تسهیلات را با کد تأیید به‌صورت دیجیتال تأیید کنید.');
        }

        $field_map = [];
        if ($check_files_enabled) {
            $field_map['completed_check_files'] = [
                'staged_key' => 'completed_check_uploads',
                'token_key'  => 'completed_check_files',
                'required'   => true,
                'multiple'   => true,
                'max'        => 20,
                'label'      => 'تصاویر چک',
            ];
        }
        if (!$digital_contract) {
            // Keep the legacy physical contract upload behavior exactly as before.
            $field_map['signed_contract'] = [
                'staged_key' => 'signed_contract_uploads',
                'token_key'  => 'signed_contract',
                'required'   => true,
                'multiple'   => false,
                'max'        => 1,
                'label'      => 'قرارداد امضا‌شده',
            ];
        }
        $field_map['signed_invoice'] = [
            'staged_key' => 'signed_invoice_uploads',
            'token_key'  => 'signed_invoice',
            'required'   => true,
            'multiple'   => false,
            'max'        => 1,
            'label'      => 'فاکتور امضا/مهر‌شده',
        ];
        $field_map['other_completed_files'] = [
            'staged_key' => 'other_completed_uploads',
            'token_key'  => 'other_completed_files',
            'required'   => false,
            'multiple'   => true,
            'max'        => 20,
            'label'      => 'سایر مدارک',
        ];

        $collected = [];
        $cleanup_urls = [];

        foreach ($field_map as $direct_key => $config) {
            $staged_raw = isset($_POST[$config['staged_key']]) ? $_POST[$config['staged_key']] : '';
            $staged = mlr_parse_staged_document_uploads(
                $staged_raw,
                $request_id,
                $config['token_key'],
                $config['max']
            );
            if (is_wp_error($staged)) {
                mlr_send_document_upload_error($staged, $cleanup_urls);
            }

            $direct = mlr_collect_direct_document_uploads($direct_key, $config['max']);
            if (is_wp_error($direct)) {
                mlr_send_document_upload_error($direct, array_merge($cleanup_urls, (array) $staged));
            }

            $urls = mlr_merge_document_urls($staged, $direct);
            if (!$config['multiple'] && count($urls) > 1) {
                $extra_urls = array_slice($urls, 1);
                mlr_delete_local_document_uploads($extra_urls);
                $urls = array_slice($urls, 0, 1);
            }

            if ($config['required'] && empty($urls)) {
                mlr_send_document_upload_error('فایل «' . $config['label'] . '» الزامی است.', $cleanup_urls);
            }

            $collected[$direct_key] = $urls;
            $cleanup_urls = array_merge($cleanup_urls, $urls);
        }

        $documents = mlr_get_request_documents_array($request->required_documents ?? '');
        if ($check_files_enabled) {
            $documents['completed_check_files'] = mlr_merge_document_urls(
                $documents['completed_check_files'] ?? [],
                $collected['completed_check_files'] ?? []
            );
        }
        if (!$digital_contract) {
            $documents['signed_contract_files'] = $collected['signed_contract'];
        }
        $documents['signed_invoice_files'] = $collected['signed_invoice'];
        if (!empty($collected['other_completed_files'])) {
            $documents['other_completed_files'] = mlr_merge_document_urls(
                $documents['other_completed_files'] ?? [],
                $collected['other_completed_files']
            );
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'loan_requests';
        $update_data = [
            'required_documents' => maybe_serialize($documents),
            'status'             => 'تأیید مدارک',
            'last_edited_at'     => current_time('mysql'),
        ];
        if (!$digital_contract && !empty($collected['signed_contract'][0])) {
            $update_data['contract_file'] = $collected['signed_contract'][0];
        }

        $db_columns = $wpdb->get_col("DESC `{$table_name}`", 0);
        if (!empty($db_columns)) {
            $update_data = array_intersect_key($update_data, array_flip($db_columns));
        }

        $updated = $wpdb->update(
            $table_name,
            $update_data,
            [
                'id'     => $request_id,
                'status' => $request->status,
            ]
        );

        if ($updated !== 1) {
            $fresh_status = $wpdb->get_var($wpdb->prepare("SELECT status FROM `{$table_name}` WHERE id = %d", $request_id));
            $message = $updated === false
                ? 'خطایی در ثبت مدارک پرونده رخ داد.'
                : 'وضعیت پرونده هم‌زمان تغییر کرده است. صفحه را تازه‌سازی کرده و دوباره بررسی کنید.';
            if (function_exists('mlr_log')) {
                mlr_log([
                    'event' => 'completed_documents_update_failed',
                    'request_id' => $request_id,
                    'portal_context' => $portal_context,
                    'expected_status' => $request->status,
                    'fresh_status' => $fresh_status,
                    'db_error' => $wpdb->last_error,
                ]);
            }
            mlr_send_document_upload_error($message, $cleanup_urls);
        }

        // Notify additive integrations only after the core status update has succeeded.
        // GreenPay listens here without controlling or rolling back the document workflow.
        do_action('mlr_loan_status_changed', $request_id, $request->status, 'تأیید مدارک', 'completed_documents_upload');

        if (function_exists('mlr_send_sms_on_loan_status_change')) {
            mlr_send_sms_on_loan_status_change($request_id, 'تأیید مدارک');
        }
        if (function_exists('mlr_create_notification')) {
            mlr_create_notification(
                'status_change',
                ($digital_contract
                    ? ('مدارک نهایی (' . ($check_files_enabled ? 'چک و ' : '') . 'فاکتور؛ قرارداد به‌صورت دیجیتال تأیید شده) برای درخواست #')
                    : ('مدارک نهایی (' . ($check_files_enabled ? 'چک، ' : '') . 'قرارداد و فاکتور) برای درخواست #')) . $request_id . ' ارسال شد.',
                admin_url('admin.php?page=mlr-loan-requests&s=' . $request_id)
            );
        }
        if (function_exists('mlr_add_request_event')) {
            mlr_add_request_event(
                $request_id,
                'upload_completed_docs',
                'بارگذاری مدارک تکمیل‌شده از پنل ' . ($portal_context === 'applicant' ? 'متقاضی' : 'پذیرنده')
            );
        }

        wp_send_json_success([
            'message' => 'مدارک با موفقیت ثبت شد و پرونده به مرحله «تأیید مدارک» منتقل گردید.',
            'status'  => 'تأیید مدارک',
        ]);
    }
}

if (!function_exists('mlr_handle_additional_documents_submission')) {
    function mlr_handle_additional_documents_submission($portal_context = 'acceptor') {
        check_ajax_referer('mlr_frontend_nonce', 'nonce');

        $request_id = isset($_POST['request_id']) ? absint($_POST['request_id']) : 0;
        $request = mlr_get_authorized_document_request($request_id, $portal_context, ['defect_documents']);
        if (is_wp_error($request)) {
            mlr_send_document_upload_error($request);
        }

        $staged_raw = isset($_POST['additional_uploads']) ? $_POST['additional_uploads'] : '';
        $staged = mlr_parse_staged_document_uploads($staged_raw, $request_id, 'additional_documents', 20);
        if (is_wp_error($staged)) {
            mlr_send_document_upload_error($staged);
        }

        $direct_key = !empty($_FILES['additional_files']) ? 'additional_files' : (!empty($_FILES['additional_documents']) ? 'additional_documents' : 'additional_files');
        $direct = mlr_collect_direct_document_uploads($direct_key, 20);
        if (is_wp_error($direct)) {
            mlr_send_document_upload_error($direct, $staged);
        }

        $new_urls = mlr_merge_document_urls($staged, $direct);
        if (empty($new_urls)) {
            mlr_send_document_upload_error('لطفاً حداقل یک فایل معتبر انتخاب کنید.');
        }

        $documents = mlr_get_request_documents_array($request->required_documents ?? '');
        $documents['additional_documents'] = mlr_merge_document_urls(
            $documents['additional_documents'] ?? [],
            $new_urls
        );

        global $wpdb;
        $table_name = $wpdb->prefix . 'loan_requests';
        $update_data = [
            'required_documents' => maybe_serialize($documents),
            'status'             => 'در حال بررسی',
            'last_edited_at'     => current_time('mysql'),
        ];
        $db_columns = $wpdb->get_col("DESC `{$table_name}`", 0);
        if (!empty($db_columns)) {
            $update_data = array_intersect_key($update_data, array_flip($db_columns));
        }

        $updated = $wpdb->update(
            $table_name,
            $update_data,
            [
                'id'     => $request_id,
                'status' => $request->status,
            ]
        );

        if ($updated !== 1) {
            if (function_exists('mlr_log')) {
                mlr_log([
                    'event' => 'additional_documents_update_failed',
                    'request_id' => $request_id,
                    'portal_context' => $portal_context,
                    'expected_status' => $request->status,
                    'db_error' => $wpdb->last_error,
                ]);
            }
            $message = $updated === false
                ? 'خطایی در ثبت مدارک رفع نقص رخ داد.'
                : 'وضعیت پرونده هم‌زمان تغییر کرده است. صفحه را تازه‌سازی کنید.';
            mlr_send_document_upload_error($message, $new_urls);
        }

        if (function_exists('mlr_send_sms_on_loan_status_change')) {
            mlr_send_sms_on_loan_status_change($request_id, 'در حال بررسی');
        }
        if (function_exists('mlr_create_notification')) {
            mlr_create_notification(
                'status_change',
                'مدارک رفع نقص برای درخواست #' . $request_id . ' ارسال شد.',
                admin_url('admin.php?page=mlr-loan-requests&s=' . $request_id)
            );
        }
        if (function_exists('mlr_add_request_event')) {
            $user_note = isset($_POST['user_note']) ? sanitize_textarea_field(wp_unslash($_POST['user_note'])) : '';
            $event_details = 'بارگذاری مدارک رفع نقص از پنل ' . ($portal_context === 'applicant' ? 'متقاضی' : 'پذیرنده');
            if ($user_note !== '') {
                $event_details .= ' — توضیح: ' . $user_note;
            }
            mlr_add_request_event($request_id, 'upload_additional_docs', $event_details);
        }

        wp_send_json_success([
            'message' => 'مدارک با موفقیت ارسال شد و پرونده مجدداً به وضعیت «در حال بررسی» منتقل گردید.',
            'status'  => 'در حال بررسی',
        ]);
    }
}
