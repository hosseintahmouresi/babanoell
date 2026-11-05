<?php
/**
 * MLR reliability / validation helpers.
 *
 * This layer intentionally does not replace the existing UI or database model.
 * It centralizes server-authoritative validation and temporary proof handling so
 * legacy pages continue to work while new writes are consistent.
 */
if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('mlr_normalize_digits')) {
    function mlr_normalize_digits($value, $digits_only = false) {
        $value = is_scalar($value) ? (string) $value : '';
        $value = strtr($value, [
            '۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9',
            '٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9',
        ]);
        return $digits_only ? preg_replace('/\D+/', '', $value) : $value;
    }
}

if (!function_exists('mlr_parse_decimal_number')) {
    function mlr_parse_decimal_number($value) {
        $value = mlr_normalize_digits($value, false);
        $value = str_replace([',', '٬', ' '], '', $value);
        $value = preg_replace('/[^0-9.\-]/', '', $value);
        if ($value === '' || $value === '-' || !is_numeric($value)) {
            return 0.0;
        }
        return (float) $value;
    }
}

if (!function_exists('mlr_validate_iranian_national_code')) {
    function mlr_validate_iranian_national_code($code) {
        $code = mlr_normalize_digits($code, true);
        if (!preg_match('/^\d{10}$/', $code) || preg_match('/^(\d)\1{9}$/', $code)) {
            return false;
        }
        $check = (int) $code[9];
        $sum = 0;
        for ($i = 0; $i < 9; $i++) {
            $sum += (int) $code[$i] * (10 - $i);
        }
        $rem = $sum % 11;
        return ($rem < 2 && $check === $rem) || ($rem >= 2 && $check === (11 - $rem));
    }
}

if (!function_exists('mlr_get_client_identity')) {
    function mlr_get_client_identity() {
        $user_id = is_user_logged_in() ? get_current_user_id() : 0;
        if ($user_id > 0) {
            return 'user:' . $user_id;
        }
        $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
        $ua = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '';
        return 'guest:' . hash('sha256', $ip . '|' . $ua . '|' . wp_salt('nonce'));
    }
}

if (!function_exists('mlr_rate_limit_check')) {
    /**
     * Fixed-window limiter. Returns true when allowed or WP_Error when blocked.
     */
    function mlr_rate_limit_check($scope, $limit, $window_seconds, array $parts = []) {
        $scope = sanitize_key($scope);
        $limit = max(1, absint($limit));
        $window_seconds = max(30, absint($window_seconds));
        $identity = mlr_get_client_identity();
        $key_material = $identity . '|' . implode('|', array_map(static function($v) {
            return is_scalar($v) ? (string) $v : wp_json_encode($v);
        }, $parts));
        $key = 'mlr_rl_' . substr(hash('sha256', $scope . '|' . $key_material), 0, 40);
        $state = get_transient($key);
        if (!is_array($state) || empty($state['reset']) || (int) $state['reset'] <= time()) {
            $state = ['count' => 0, 'reset' => time() + $window_seconds];
        }
        if ((int) $state['count'] >= $limit) {
            $retry_after = max(1, (int) $state['reset'] - time());
            return new WP_Error('mlr_rate_limited', 'تعداد درخواست‌ها بیش از حد مجاز است. لطفاً کمی بعد دوباره تلاش کنید.', ['retry_after' => $retry_after]);
        }
        $state['count'] = (int) $state['count'] + 1;
        set_transient($key, $state, max(1, (int) $state['reset'] - time()));
        return true;
    }
}

if (!function_exists('mlr_build_clean_order_items')) {
    function mlr_build_clean_order_items($raw) {
        if (is_string($raw)) {
            $decoded = json_decode(wp_unslash($raw), true);
        } elseif (is_array($raw)) {
            $decoded = $raw;
        } else {
            $decoded = [];
        }
        if (!is_array($decoded)) {
            return new WP_Error('mlr_invalid_order_items', 'اطلاعات محصولات معتبر نیست.');
        }

        $items = [];
        $summary = [];
        $total = 0.0;
        foreach ($decoded as $item) {
            if (!is_array($item)) {
                continue;
            }
            $qty = isset($item['qty']) ? absint($item['qty']) : absint($item['quantity'] ?? 1);
            $qty = max(1, $qty);
            $unit_price = mlr_parse_decimal_number($item['price'] ?? ($item['unit_price'] ?? 0));
            if ($unit_price <= 0) {
                continue;
            }
            // Never trust a browser-provided total_price; calculate it here.
            $line_total = round($unit_price * $qty, 2);
            $name = sanitize_text_field($item['name'] ?? ($item['description'] ?? ''));
            $meta = $item['meta'] ?? '';
            if (is_array($meta)) {
                $clean_meta = [];
                foreach ($meta as $meta_key => $meta_value) {
                    $clean_meta[sanitize_key($meta_key)] = is_scalar($meta_value) ? sanitize_text_field($meta_value) : '';
                }
                $meta = $clean_meta;
            } else {
                $meta = sanitize_text_field($meta);
            }
            $items[] = [
                'id'          => sanitize_text_field($item['id'] ?? ''),
                'name'        => $name,
                'code'        => sanitize_text_field($item['code'] ?? ($item['id'] ?? '')),
                'description' => sanitize_text_field($item['description'] ?? $name),
                'qty'         => $qty,
                'quantity'    => $qty,
                'unit'        => sanitize_text_field($item['unit'] ?? ''),
                'price'       => $unit_price,
                'unit_price'  => $unit_price,
                'total_price' => $line_total,
                'meta'        => $meta,
            ];
            $total += $line_total;
            if ($name !== '') {
                $summary[] = $name;
            }
        }
        if (empty($items)) {
            return new WP_Error('mlr_empty_order_items', 'ثبت حداقل یک محصول معتبر الزامی است.');
        }
        return [
            'items'   => $items,
            'total'   => round($total, 2),
            'summary' => implode('، ', array_values(array_unique($summary))),
        ];
    }
}

if (!function_exists('mlr_validate_plan_request_values')) {
    /**
     * Validate plan amount/installments/interval using one canonical rule set.
     * Returns normalized settings or WP_Error.
     */
    function mlr_validate_plan_request_values($plan, $amount, $installments, $interval = 1) {
        if (!$plan || empty($plan->id) || isset($plan->is_active) && !(int) $plan->is_active) {
            return new WP_Error('mlr_plan_unavailable', 'طرح انتخاب‌شده یافت نشد یا غیرفعال است.');
        }
        $settings = maybe_unserialize($plan->settings ?? []);
        if (!is_array($settings)) {
            $settings = [];
        }
        $amount = (float) $amount;
        $installments = absint($installments);
        $interval = max(1, absint($interval));
        if ($amount <= 0 || $installments <= 0) {
            return new WP_Error('mlr_invalid_plan_values', 'مبلغ و تعداد اقساط باید معتبر باشند.');
        }
        $min_amount = max(0, (float) ($settings['min_amount'] ?? 0));
        $max_amount = max(0, (float) ($settings['max_amount'] ?? 0));
        if ($min_amount > 0 && $amount < $min_amount) {
            return new WP_Error('mlr_amount_too_low', 'مبلغ واردشده کمتر از حداقل مبلغ مجاز این طرح است.');
        }
        if ($max_amount > 0 && $amount > $max_amount) {
            return new WP_Error('mlr_amount_too_high', 'مبلغ واردشده بیشتر از حداکثر مبلغ مجاز این طرح است.');
        }

        $allowed_installments = [];
        $raw_active = trim((string) ($settings['active_installments'] ?? ''));
        if ($raw_active !== '') {
            foreach (preg_split('/\s*[,،]\s*/u', mlr_normalize_digits($raw_active, false)) as $candidate) {
                $candidate = absint($candidate);
                if ($candidate > 0) {
                    $allowed_installments[] = $candidate;
                }
            }
            $allowed_installments = array_values(array_unique($allowed_installments));
            if (!empty($allowed_installments) && !in_array($installments, $allowed_installments, true)) {
                return new WP_Error('mlr_installments_not_allowed', 'تعداد اقساط انتخاب‌شده برای این طرح مجاز نیست.');
            }
        }

        $plan_type = strtolower(trim((string) ($plan->type ?? '')));
        if (!in_array($plan_type, ['market', 'leasing'], true)) {
            return new WP_Error('mlr_invalid_plan_type', 'نوع طرح معتبر نیست.');
        }
        if ($plan_type === 'leasing') {
            $min_interval = max(1, absint($settings['min_interval'] ?? 1));
            $max_interval = max($min_interval, absint($settings['max_interval'] ?? $min_interval));
            if ($interval < $min_interval || $interval > $max_interval) {
                return new WP_Error('mlr_interval_not_allowed', 'فاصله اقساط انتخاب‌شده برای این طرح مجاز نیست.');
            }
            // The formula models payment periods as total_installments / interval.
            // Fractional payment counts are not meaningful and used to create rounding drift.
            if ($installments % $interval !== 0) {
                return new WP_Error('mlr_fractional_payment_count', 'تعداد اقساط باید بر فاصله اقساط بخش‌پذیر باشد.');
            }
        } else {
            $interval = 1;
        }

        return [
            'settings' => $settings,
            'type' => $plan_type,
            'amount' => $amount,
            'installments' => $installments,
            'interval' => $interval,
            'allowed_installments' => $allowed_installments,
        ];
    }
}

// -----------------------------------------------------------------------------
// Database transaction capability checks for financial state transitions.
// -----------------------------------------------------------------------------
if (!function_exists('mlr_get_table_storage_engine')) {
    function mlr_get_table_storage_engine($table_name) {
        global $wpdb;
        $table_name = (string) $table_name;
        if (empty($wpdb) || $table_name === '') {
            return '';
        }
        $pattern = method_exists($wpdb, 'esc_like') ? $wpdb->esc_like($table_name) : addcslashes($table_name, '_%\\');
        $status = $wpdb->get_row($wpdb->prepare('SHOW TABLE STATUS LIKE %s', $pattern));
        return $status && !empty($status->Engine) ? strtolower((string) $status->Engine) : '';
    }
}

if (!function_exists('mlr_table_supports_transactions')) {
    function mlr_table_supports_transactions($table_name) {
        return in_array(mlr_get_table_storage_engine($table_name), ['innodb', 'xtradb'], true);
    }
}


// MyISAM/legacy compatibility: short-lived WRITE table locks are used only when
// transactions are unavailable. This keeps the legacy installation usable without
// silently converting a live table engine during a financial action.
if (!function_exists('mlr_lock_tables_for_write')) {
    function mlr_lock_tables_for_write($table_names) {
        global $wpdb;
        $table_names = array_values(array_unique(array_filter(array_map('strval', (array) $table_names))));
        if (empty($wpdb) || empty($table_names)) {
            return false;
        }
        $clauses = [];
        foreach ($table_names as $table_name) {
            if (!preg_match('/^[A-Za-z0-9_]+$/', $table_name)) {
                return false;
            }
            $clauses[] = '`' . $table_name . '` WRITE';
        }
        return $wpdb->query('LOCK TABLES ' . implode(', ', $clauses)) !== false;
    }
}

if (!function_exists('mlr_unlock_tables')) {
    function mlr_unlock_tables() {
        global $wpdb;
        return !empty($wpdb) && $wpdb->query('UNLOCK TABLES') !== false;
    }
}

if (!function_exists('mlr_begin_financial_write_guard')) {
    /**
     * Returns "transaction" on InnoDB/XtraDB, "table_lock" on legacy engines,
     * or false if no safe serialization mechanism could be started.
     */
    function mlr_begin_financial_write_guard($table_names) {
        global $wpdb;
        $table_names = array_values(array_unique(array_filter(array_map('strval', (array) $table_names))));
        if (empty($wpdb) || empty($table_names)) {
            return false;
        }
        $all_transactional = true;
        foreach ($table_names as $table_name) {
            if (!mlr_table_supports_transactions($table_name)) {
                $all_transactional = false;
                break;
            }
        }
        if ($all_transactional) {
            return $wpdb->query('START TRANSACTION') !== false ? 'transaction' : false;
        }
        return mlr_lock_tables_for_write($table_names) ? 'table_lock' : false;
    }
}

if (!function_exists('mlr_end_financial_write_guard')) {
    function mlr_end_financial_write_guard($mode, $commit = true) {
        global $wpdb;
        if ($mode === 'transaction') {
            return $wpdb->query($commit ? 'COMMIT' : 'ROLLBACK') !== false;
        }
        if ($mode === 'table_lock') {
            return mlr_unlock_tables();
        }
        return false;
    }
}

// -----------------------------------------------------------------------------
// Initial/staged file proof. Existing request uploads already use HMAC tokens;
// initial forms have no request ID yet, so a short-lived server registry is used.
// -----------------------------------------------------------------------------
if (!function_exists('mlr_initial_upload_transient_key')) {
    function mlr_initial_upload_transient_key($token) {
        return 'mlr_iup_' . substr(hash('sha256', (string) $token), 0, 40);
    }
}

if (!function_exists('mlr_initial_upload_compat_key')) {
    function mlr_initial_upload_compat_key($url, $field_key, $context) {
        return 'mlr_iuc_' . substr(hash('sha256', mlr_get_client_identity() . '|' . $context . '|' . $field_key . '|' . $url), 0, 40);
    }
}

if (!function_exists('mlr_issue_initial_upload_token')) {
    function mlr_issue_initial_upload_token($url, $field_key, $context, $ttl = 21600) {
        $url = esc_url_raw((string) $url);
        $field_key = sanitize_key($field_key);
        $context = sanitize_key($context);
        $ttl = max(600, min(DAY_IN_SECONDS, absint($ttl)));
        if ($url === '' || $field_key === '' || !in_array($context, ['loan_request', 'cooperation_request'], true)) {
            return new WP_Error('mlr_invalid_initial_upload', 'اطلاعات فایل بارگذاری‌شده معتبر نیست.');
        }
        if (function_exists('mlr_validate_local_document_upload_url')) {
            $error = '';
            if (!mlr_validate_local_document_upload_url($url, $error)) {
                return new WP_Error('mlr_invalid_initial_upload_url', $error ?: 'فایل بارگذاری‌شده معتبر نیست.');
            }
        }
        try {
            $token = bin2hex(random_bytes(32));
        } catch (Throwable $e) {
            $token = wp_generate_password(64, false, false);
        }
        $payload = [
            'identity' => mlr_get_client_identity(),
            'url' => $url,
            'field_key' => $field_key,
            'context' => $context,
            'created_at' => time(),
            'expires_at' => time() + $ttl,
        ];
        set_transient(mlr_initial_upload_transient_key($token), $payload, $ttl);
        // Compatibility bridge for a page cached with the previous JS that only posts URL strings.
        set_transient(mlr_initial_upload_compat_key($url, $field_key, $context), $token, $ttl);

        // Keep the cleanup target in the cron arguments instead of relying on the proof transient.
        // The cleanup intentionally runs *after* proof expiry, so looking the URL up from that
        // transient would make orphan cleanup silently fail. Cron arguments survive until the event
        // actually runs, even when WP-Cron is delayed on a low-traffic site.
        $cleanup_args = [$token, $url, $field_key, $context];
        if (!wp_next_scheduled('mlr_cleanup_initial_upload', $cleanup_args)) {
            wp_schedule_single_event(time() + $ttl + 60, 'mlr_cleanup_initial_upload', $cleanup_args);
        }
        return $token;
    }
}

if (!function_exists('mlr_verify_initial_upload_token')) {
    function mlr_verify_initial_upload_token($token, $url, $field_key, $context) {
        $token = sanitize_text_field((string) $token);
        $url = esc_url_raw((string) $url);
        $field_key = sanitize_key($field_key);
        $context = sanitize_key($context);
        if ($token === '' || $url === '') {
            return false;
        }
        $payload = get_transient(mlr_initial_upload_transient_key($token));
        if (!is_array($payload)) {
            return false;
        }
        if (!hash_equals((string) ($payload['identity'] ?? ''), mlr_get_client_identity())) {
            return false;
        }
        if (!hash_equals((string) ($payload['url'] ?? ''), $url) || !hash_equals((string) ($payload['field_key'] ?? ''), $field_key) || !hash_equals((string) ($payload['context'] ?? ''), $context)) {
            return false;
        }
        if (!empty($payload['expires_at']) && (int) $payload['expires_at'] < time()) {
            return false;
        }
        if (function_exists('mlr_validate_local_document_upload_url')) {
            $error = '';
            if (!mlr_validate_local_document_upload_url($url, $error)) {
                return false;
            }
        }
        return true;
    }
}

if (!function_exists('mlr_parse_initial_staged_uploads')) {
    function mlr_parse_initial_staged_uploads($raw, $field_key, $context, $max_files = 20) {
        if (is_string($raw)) {
            $decoded = json_decode(wp_unslash($raw), true);
        } else {
            $decoded = $raw;
        }
        if ($decoded === null || $decoded === '' || $decoded === []) {
            return ['urls' => [], 'tokens' => []];
        }
        if (!is_array($decoded)) {
            return new WP_Error('mlr_invalid_staged_upload_json', 'اطلاعات فایل‌های بارگذاری‌شده معتبر نیست.');
        }
        $max_files = max(1, min(50, absint($max_files)));
        if (count($decoded) > $max_files) {
            return new WP_Error('mlr_too_many_initial_uploads', sprintf('حداکثر %d فایل برای این بخش مجاز است.', $max_files));
        }
        $urls = [];
        $tokens = [];
        foreach ($decoded as $item) {
            $url = '';
            $token = '';
            if (is_array($item)) {
                $url = esc_url_raw((string) ($item['url'] ?? ''));
                $token = sanitize_text_field((string) ($item['token'] ?? ''));
            } elseif (is_string($item)) {
                $url = esc_url_raw(trim($item));
                // Backward-compatible bridge for already-rendered/cached pages.
                $token = (string) get_transient(mlr_initial_upload_compat_key($url, $field_key, $context));
            }
            if ($url === '' || $token === '' || !mlr_verify_initial_upload_token($token, $url, $field_key, $context)) {
                return new WP_Error('mlr_invalid_initial_upload_proof', 'اعتبار یکی از فایل‌های بارگذاری‌شده منقضی یا نامعتبر است. لطفاً فایل را دوباره انتخاب کنید.');
            }
            $urls[] = $url;
            $tokens[] = $token;
        }
        return ['urls' => array_values(array_unique($urls)), 'tokens' => array_values(array_unique($tokens))];
    }
}

if (!function_exists('mlr_consume_initial_upload_tokens')) {
    function mlr_consume_initial_upload_tokens(array $tokens) {
        foreach (array_unique($tokens) as $token) {
            $payload = get_transient(mlr_initial_upload_transient_key($token));
            if (is_array($payload)) {
                $url = (string) ($payload['url'] ?? '');
                $field_key = (string) ($payload['field_key'] ?? '');
                $context = (string) ($payload['context'] ?? '');
                delete_transient(mlr_initial_upload_compat_key($url, $field_key, $context));
                wp_clear_scheduled_hook('mlr_cleanup_initial_upload', [$token, $url, $field_key, $context]);
            }
            // Also clear the 8.4.6.28 one-argument schedule if one is still pending.
            wp_clear_scheduled_hook('mlr_cleanup_initial_upload', [$token]);
            delete_transient(mlr_initial_upload_transient_key($token));
        }
    }
}

if (!function_exists('mlr_initial_upload_is_referenced')) {
    /**
     * Conservative reference check used by orphan cleanup.
     *
     * A staged-upload cleanup event may run after an otherwise-successful database
     * insert if PHP stopped between INSERT and token consumption. To protect live
     * data, never unlink a file that is already referenced by a canonical record.
     * On database uncertainty this helper deliberately returns true (keep file).
     */
    function mlr_initial_upload_is_referenced($url, $context) {
        global $wpdb;

        $url = esc_url_raw((string) $url);
        $context = sanitize_key((string) $context);
        if ($url === '' || empty($wpdb)) {
            return true;
        }

        if ($context === 'loan_request' || $context === 'guarantor') {
            $table = $wpdb->prefix . 'loan_requests';
            $candidate_columns = [
                'required_documents', 'form_data', 'bank_validation_file', 'signature_image',
                'contract_file', 'check_images', 'pending_docs_attachments', 'transfer_receipt_files',
                'guarantor_info', 'guarantor_info_2',
            ];
        } elseif ($context === 'cooperation_request') {
            $table = $wpdb->prefix . 'cooperation_requests';
            $candidate_columns = ['files', 'data'];
        } else {
            // Unknown context: safest behavior is to preserve the file.
            return true;
        }

        $table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
        if ($table_exists !== $table) {
            return true;
        }

        $columns = $wpdb->get_col("DESC `{$table}`", 0);
        if (!is_array($columns) || empty($columns)) {
            return true;
        }
        $candidate_columns = array_values(array_intersect($candidate_columns, $columns));
        if (empty($candidate_columns)) {
            return true;
        }

        $needle = '%' . $wpdb->esc_like($url) . '%';
        $clauses = [];
        $params = [];
        foreach ($candidate_columns as $column) {
            // Column names come only from the fixed whitelist above + DESC intersection.
            $clauses[] = "`{$column}` LIKE %s";
            $params[] = $needle;
        }
        $sql = "SELECT id FROM `{$table}` WHERE " . implode(' OR ', $clauses) . ' LIMIT 1';
        $prepared = $wpdb->prepare($sql, $params);
        if (!is_string($prepared) || $prepared === '') {
            return true;
        }

        // Clear stale diagnostic state so a previous unrelated query error cannot make
        // this cleanup decision ambiguous.
        if (property_exists($wpdb, 'last_error')) {
            $wpdb->last_error = '';
        }
        $found = $wpdb->get_var($prepared);
        if (!empty($wpdb->last_error)) {
            return true;
        }
        return !empty($found);
    }
}

if (!function_exists('mlr_cleanup_initial_upload_callback')) {
    function mlr_cleanup_initial_upload_callback($token, $url = '', $field_key = '', $context = '') {
        $token = sanitize_text_field((string) $token);
        $url = esc_url_raw((string) $url);
        $field_key = sanitize_key($field_key);
        $context = sanitize_key($context);

        // Backward compatibility for a pending 8.4.6.28 cron event that only contains the token.
        if ($url === '') {
            $payload = get_transient(mlr_initial_upload_transient_key($token));
            if (is_array($payload)) {
                $url = isset($payload['url']) ? esc_url_raw((string) $payload['url']) : '';
                $field_key = sanitize_key((string) ($payload['field_key'] ?? ''));
                $context = sanitize_key((string) ($payload['context'] ?? ''));
            }
        }

        // Only remove a true orphan. If a successful submit already references this
        // URL, preserve it even when the token-consumption step did not get a chance to run.
        $is_referenced = $url !== '' && function_exists('mlr_initial_upload_is_referenced')
            ? mlr_initial_upload_is_referenced($url, $context)
            : true;
        if ($url !== '' && !$is_referenced && function_exists('mlr_delete_local_document_uploads')) {
            mlr_delete_local_document_uploads([$url]);
        }
        if ($url !== '' && $field_key !== '' && $context !== '') {
            delete_transient(mlr_initial_upload_compat_key($url, $field_key, $context));
        }
        delete_transient(mlr_initial_upload_transient_key($token));
    }
}
add_action('mlr_cleanup_initial_upload', 'mlr_cleanup_initial_upload_callback', 10, 4);


// Request-bound staged uploads (currently used by guarantor documents) receive a
// delayed orphan cleanup. The callback preserves any URL already referenced by a
// live loan request, so a PHP/network interruption after a successful DB write
// cannot delete a valid document.
if (!function_exists('mlr_schedule_request_staged_upload_cleanup')) {
    function mlr_schedule_request_staged_upload_cleanup($url, $request_id, $field_key, $context = 'guarantor', $ttl = 7200) {
        $url = esc_url_raw((string) $url);
        $request_id = absint($request_id);
        $field_key = sanitize_key((string) $field_key);
        $context = sanitize_key((string) $context);
        if ($url === '' || $request_id <= 0 || $field_key === '' || $context !== 'guarantor') {
            return false;
        }
        $ttl = max(900, min(DAY_IN_SECONDS, absint($ttl)));
        $args = [$url, $request_id, $field_key, $context];
        if (!wp_next_scheduled('mlr_cleanup_request_staged_upload', $args)) {
            return (bool) wp_schedule_single_event(time() + $ttl, 'mlr_cleanup_request_staged_upload', $args);
        }
        return true;
    }
}

if (!function_exists('mlr_cancel_request_staged_upload_cleanup')) {
    function mlr_cancel_request_staged_upload_cleanup($url, $request_id, $field_key, $context = 'guarantor') {
        $args = [esc_url_raw((string) $url), absint($request_id), sanitize_key((string) $field_key), sanitize_key((string) $context)];
        wp_clear_scheduled_hook('mlr_cleanup_request_staged_upload', $args);
    }
}

if (!function_exists('mlr_cleanup_request_staged_upload_callback')) {
    function mlr_cleanup_request_staged_upload_callback($url, $request_id, $field_key, $context = 'guarantor') {
        $url = esc_url_raw((string) $url);
        $context = sanitize_key((string) $context);
        if ($url === '' || $context !== 'guarantor') {
            return;
        }
        $is_referenced = function_exists('mlr_initial_upload_is_referenced')
            ? mlr_initial_upload_is_referenced($url, 'guarantor')
            : true;
        if (!$is_referenced && function_exists('mlr_delete_local_document_uploads')) {
            mlr_delete_local_document_uploads([$url]);
        }
    }
}
add_action('mlr_cleanup_request_staged_upload', 'mlr_cleanup_request_staged_upload_callback', 10, 4);

if (!function_exists('mlr_cleanup_stale_chunk_directories')) {
    function mlr_cleanup_stale_chunk_directories() {
        $root = trailingslashit(get_temp_dir()) . 'mlr-document-chunks';
        if (!is_dir($root)) {
            return;
        }
        $cutoff = time() - (2 * HOUR_IN_SECONDS);
        foreach (glob(trailingslashit($root) . '*', GLOB_ONLYDIR) ?: [] as $dir) {
            $mtime = @filemtime($dir);
            if (!$mtime || $mtime > $cutoff) {
                continue;
            }
            foreach (glob(trailingslashit($dir) . '*') ?: [] as $file) {
                if (is_file($file)) {
                    @unlink($file);
                }
            }
            @rmdir($dir);
        }
    }
}
add_action('mlr_hourly_housekeeping', 'mlr_cleanup_stale_chunk_directories');

// -----------------------------------------------------------------------------
// Server-authoritative MyCredit flow/proof.
// -----------------------------------------------------------------------------
if (!function_exists('mlr_credit_flow_key')) {
    function mlr_credit_flow_key($hash_code) {
        return 'mlr_cflow_' . substr(hash('sha256', (string) $hash_code), 0, 40);
    }
}
if (!function_exists('mlr_credit_proof_key')) {
    function mlr_credit_proof_key($national_code, $mobile) {
        return 'mlr_cproof_' . substr(hash('sha256', mlr_get_client_identity() . '|' . mlr_normalize_digits($national_code, true) . '|' . mlr_normalize_digits($mobile, true)), 0, 40);
    }
}
if (!function_exists('mlr_credit_store_flow')) {
    function mlr_credit_store_flow($hash_code, $national_code, $mobile) {
        $payload = [
            'identity' => mlr_get_client_identity(),
            'national_code' => mlr_normalize_digits($national_code, true),
            'mobile' => mlr_normalize_digits($mobile, true),
            'created_at' => time(),
            'otp_verified' => false,
        ];
        set_transient(mlr_credit_flow_key($hash_code), $payload, 2 * HOUR_IN_SECONDS);
        return $payload;
    }
}
if (!function_exists('mlr_credit_get_flow')) {
    function mlr_credit_get_flow($hash_code) {
        $flow = get_transient(mlr_credit_flow_key($hash_code));
        if (!is_array($flow) || empty($flow['identity']) || !hash_equals((string) $flow['identity'], mlr_get_client_identity())) {
            return null;
        }
        return $flow;
    }
}
if (!function_exists('mlr_credit_mark_otp_verified')) {
    function mlr_credit_mark_otp_verified($hash_code) {
        $flow = mlr_credit_get_flow($hash_code);
        if (!$flow) {
            return false;
        }
        $flow['otp_verified'] = true;
        $flow['otp_verified_at'] = time();
        set_transient(mlr_credit_flow_key($hash_code), $flow, 2 * HOUR_IN_SECONDS);
        return true;
    }
}
if (!function_exists('mlr_credit_store_report_proof')) {
    function mlr_credit_store_report_proof($hash_code, array $report_data) {
        $flow = mlr_credit_get_flow($hash_code);
        if (!$flow || empty($flow['otp_verified'])) {
            return false;
        }
        $json = wp_json_encode($report_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($json) || $json === '') {
            return false;
        }
        $proof = [
            'identity' => mlr_get_client_identity(),
            'national_code' => $flow['national_code'],
            'mobile' => $flow['mobile'],
            'hash_code' => sanitize_text_field($hash_code),
            'report_json' => $json,
            'report_digest' => hash('sha256', $json),
            'verified_at' => time(),
        ];
        set_transient(mlr_credit_proof_key($flow['national_code'], $flow['mobile']), $proof, 2 * HOUR_IN_SECONDS);
        return true;
    }
}
if (!function_exists('mlr_credit_get_report_proof')) {
    function mlr_credit_get_report_proof($national_code, $mobile) {
        $national_code = mlr_normalize_digits($national_code, true);
        $mobile = mlr_normalize_digits($mobile, true);
        $proof = get_transient(mlr_credit_proof_key($national_code, $mobile));
        if (!is_array($proof) || empty($proof['identity']) || !hash_equals((string) $proof['identity'], mlr_get_client_identity())) {
            return null;
        }
        if (!hash_equals((string) ($proof['national_code'] ?? ''), $national_code) || !hash_equals((string) ($proof['mobile'] ?? ''), $mobile)) {
            return null;
        }
        $json = (string) ($proof['report_json'] ?? '');
        if ($json === '' || !hash_equals((string) ($proof['report_digest'] ?? ''), hash('sha256', $json))) {
            return null;
        }
        return $proof;
    }
}
if (!function_exists('mlr_credit_consume_report_proof')) {
    function mlr_credit_consume_report_proof($national_code, $mobile) {
        delete_transient(mlr_credit_proof_key($national_code, $mobile));
    }
}

// -----------------------------------------------------------------------------
// Durable signed submission proof for completed credit reports.
//
// The report itself stays in the existing hidden JSON field for compatibility,
// but it is accepted at final submit only when its canonical digest matches a
// server-signed token issued after OTP verification + report generation. This
// prevents a completed validation from being lost when a transient disappears
// between the credit step and the final form submission, without trusting
// browser-supplied JSON on its own.
// -----------------------------------------------------------------------------
if (!function_exists('mlr_credit_canonicalize_report_value')) {
    function mlr_credit_canonicalize_report_value($value) {
        if (is_object($value)) {
            $value = (array) $value;
        }
        if (!is_array($value)) {
            return $value;
        }

        $keys = array_keys($value);
        $is_list = ($keys === range(0, count($value) - 1));
        if ($is_list) {
            $out = [];
            foreach ($value as $item) {
                $out[] = mlr_credit_canonicalize_report_value($item);
            }
            return $out;
        }

        ksort($value, SORT_STRING);
        $out = [];
        foreach ($value as $key => $item) {
            $out[(string) $key] = mlr_credit_canonicalize_report_value($item);
        }
        return $out;
    }
}

if (!function_exists('mlr_credit_report_digest')) {
    function mlr_credit_report_digest($report) {
        if (is_string($report)) {
            $decoded = json_decode(wp_unslash($report), true);
            if (!is_array($decoded)) {
                return '';
            }
            $report = $decoded;
        } elseif (is_object($report)) {
            $report = (array) $report;
        }
        if (!is_array($report)) {
            return '';
        }

        $canonical = mlr_credit_canonicalize_report_value($report);
        $json = wp_json_encode($canonical, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return is_string($json) && $json !== '' ? hash('sha256', $json) : '';
    }
}

if (!function_exists('mlr_credit_base64url_encode')) {
    function mlr_credit_base64url_encode($value) {
        return rtrim(strtr(base64_encode((string) $value), '+/', '-_'), '=');
    }
}

if (!function_exists('mlr_credit_base64url_decode')) {
    function mlr_credit_base64url_decode($value) {
        $value = strtr((string) $value, '-_', '+/');
        $pad = strlen($value) % 4;
        if ($pad) {
            $value .= str_repeat('=', 4 - $pad);
        }
        $decoded = base64_decode($value, true);
        return $decoded === false ? '' : $decoded;
    }
}

if (!function_exists('mlr_credit_issue_submission_token')) {
    function mlr_credit_issue_submission_token($hash_code, array $report_data, $ttl = DAY_IN_SECONDS) {
        $flow = function_exists('mlr_credit_get_flow') ? mlr_credit_get_flow($hash_code) : null;
        if (!$flow || empty($flow['otp_verified'])) {
            return '';
        }

        $digest = mlr_credit_report_digest($report_data);
        if ($digest === '') {
            return '';
        }

        $now = time();
        $ttl = max(HOUR_IN_SECONDS, min(2 * DAY_IN_SECONDS, absint($ttl)));
        $payload = [
            'v' => 1,
            'identity' => hash('sha256', (string) mlr_get_client_identity()),
            'national_code' => mlr_normalize_digits($flow['national_code'] ?? '', true),
            'mobile' => mlr_normalize_digits($flow['mobile'] ?? '', true),
            'hash_code' => sanitize_text_field($hash_code),
            'report_digest' => $digest,
            'iat' => $now,
            'exp' => $now + $ttl,
        ];
        if ($payload['national_code'] === '' || $payload['mobile'] === '' || $payload['hash_code'] === '') {
            return '';
        }

        $json = wp_json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($json) || $json === '') {
            return '';
        }
        $body = mlr_credit_base64url_encode($json);
        $signature = hash_hmac('sha256', $body, wp_salt('auth'));
        return $body . '.' . $signature;
    }
}

if (!function_exists('mlr_credit_validate_submission_token')) {
    function mlr_credit_validate_submission_token($token, $report_json, $national_code, $mobile) {
        $token = trim((string) $token);
        if ($token === '' || !str_contains($token, '.')) {
            return null;
        }
        [$body, $signature] = array_pad(explode('.', $token, 2), 2, '');
        if ($body === '' || !preg_match('/^[a-f0-9]{64}$/', $signature)) {
            return null;
        }
        $expected_signature = hash_hmac('sha256', $body, wp_salt('auth'));
        if (!hash_equals($expected_signature, $signature)) {
            return null;
        }

        $payload_json = mlr_credit_base64url_decode($body);
        $payload = json_decode($payload_json, true);
        if (!is_array($payload) || (int) ($payload['v'] ?? 0) !== 1) {
            return null;
        }

        $now = time();
        $issued_at = (int) ($payload['iat'] ?? 0);
        $expires_at = (int) ($payload['exp'] ?? 0);
        if ($issued_at <= 0 || $issued_at > ($now + 300) || $expires_at <= $now || $expires_at <= $issued_at) {
            return null;
        }

        $identity_hash = hash('sha256', (string) mlr_get_client_identity());
        if (empty($payload['identity']) || !hash_equals((string) $payload['identity'], $identity_hash)) {
            return null;
        }

        $national_code = mlr_normalize_digits($national_code, true);
        $mobile = mlr_normalize_digits($mobile, true);
        if (!hash_equals((string) ($payload['national_code'] ?? ''), $national_code)
            || !hash_equals((string) ($payload['mobile'] ?? ''), $mobile)) {
            return null;
        }

        $report_json = wp_unslash((string) $report_json);
        $decoded_report = json_decode($report_json, true);
        if (!is_array($decoded_report)) {
            return null;
        }
        $digest = mlr_credit_report_digest($decoded_report);
        if ($digest === '' || empty($payload['report_digest']) || !hash_equals((string) $payload['report_digest'], $digest)) {
            return null;
        }

        $trusted_json = wp_json_encode($decoded_report, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($trusted_json) || $trusted_json === '') {
            return null;
        }

        return [
            'hash_code' => sanitize_text_field($payload['hash_code'] ?? ''),
            'report_json' => $trusted_json,
            'report_digest' => $digest,
            'issued_at' => $issued_at,
            'expires_at' => $expires_at,
            'source' => 'signed_submission_token',
        ];
    }
}

// -----------------------------------------------------------------------------
// Logging redaction helper.
// -----------------------------------------------------------------------------
if (!function_exists('mlr_redact_log_content')) {
    function mlr_redact_log_content($content) {
        if (is_array($content)) {
            $redacted = [];
            foreach ($content as $key => $value) {
                $key_string = strtolower((string) $key);
                if (preg_match('/pass(word)?|secret|authorization|api[_-]?key|token|otp|national[_-]?code|mobile|phone|report/i', $key_string)) {
                    $redacted[$key] = '***REDACTED***';
                } else {
                    $redacted[$key] = mlr_redact_log_content($value);
                }
            }
            return $redacted;
        }
        if (is_object($content)) {
            return mlr_redact_log_content((array) $content);
        }
        $text = (string) $content;
        $patterns = [
            '/((?:password|client_secret|authorization|api[_-]?key|token|otp)\s*[=:>]\s*)[^\s,;&]+/iu' => '$1***REDACTED***',
            '/\b09\d{9}\b/u' => '09*******XX',
            '/\b\d{10}\b/u' => '**********',
            '/(contract[_ -]?token\s*[=:>]\s*)[A-Fa-f0-9]{12,}/iu' => '$1***REDACTED***',
        ];
        foreach ($patterns as $pattern => $replacement) {
            $text = preg_replace($pattern, $replacement, $text);
        }
        return $text;
    }
}

if (!function_exists('mlr_hardening_activate')) {
    function mlr_hardening_activate() {
        if (!wp_next_scheduled('mlr_hourly_housekeeping')) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'hourly', 'mlr_hourly_housekeeping');
        }
    }
}

if (!function_exists('mlr_unschedule_all_hook_events')) {
    function mlr_unschedule_all_hook_events($hook) {
        wp_clear_scheduled_hook($hook);
        if (!function_exists('_get_cron_array')) return;
        $cron = _get_cron_array();
        if (!is_array($cron)) return;
        foreach ($cron as $timestamp => $hooks) {
            if (empty($hooks[$hook]) || !is_array($hooks[$hook])) continue;
            foreach ($hooks[$hook] as $event) {
                $args = isset($event['args']) && is_array($event['args']) ? $event['args'] : [];
                wp_unschedule_event((int) $timestamp, $hook, $args);
            }
        }
    }
}

if (!function_exists('mlr_hardening_deactivate')) {
    function mlr_hardening_deactivate() {
        foreach ([
            'mlr_hourly_housekeeping',
            'mlr_api_settings_snapshot_event',
            'mlr_daily_notification_purge',
            'mlr_cleanup_initial_upload',
            'mlr_cleanup_request_staged_upload',
            'mlr_greenpay_process_debit',
        ] as $hook) {
            mlr_unschedule_all_hook_events($hook);
        }
    }
}
