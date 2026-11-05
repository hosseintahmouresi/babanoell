<?php
if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/class-mlr-greenpay-api.php';

if (!function_exists('mlr_greenpay_test_profile_1023')) {
    /**
     * Supplied GreenPay sandbox profile for "online purchase 1023".
     *
     * The API key is intentionally NOT hard-coded in the plugin. It must be
     * entered in the protected settings field from the merchant test sheet.
     * GreenPass/SMS credentials are a separate service and are not used here.
     */
    function mlr_greenpay_test_profile_1023() {
        return [
            'username'            => '11111111111',
            'password'            => '1',
            'terminal_number'     => 'T111111111111',
            'merchant_id'         => '1053',
            'from_pocket_number'  => '1262010000002551',
            'to_pocket_number'    => '',
            'test_otp'            => '123456',
            'test_skip_otp_request' => 1,
            // Use the exact API endpoints hard-coded in the vendor-supplied reference plugin.
            // The URL in the merchant spreadsheet is not treated as the Wallet API base.
            'auth_base_url'       => 'https://wallet.bki.ir/walletbackend/api/sandbox',
            'purchase_base_url'   => 'https://wallet.bki.ir/walletbackend/api/purchase',
        ];
    }
}

if (!function_exists('mlr_greenpay_apply_test_profile_1023')) {
    function mlr_greenpay_apply_test_profile_1023(array $settings) {
        if (($settings['environment'] ?? 'test') === 'live' || empty($settings['test_profile_1023'])) {
            return $settings;
        }
        $profile = mlr_greenpay_test_profile_1023();
        foreach ($profile as $key => $value) {
            $settings[$key] = $value;
        }
        return $settings;
    }
}

if (!function_exists('mlr_greenpay_default_settings')) {
    function mlr_greenpay_default_settings() {
        $profile = mlr_greenpay_test_profile_1023();
        return [
            // Safe-by-default: upgrading the working system never starts a financial debit by itself.
            'enabled'                       => 0,
            'auto_debit_on_docs_approved'   => 1,
            'environment'                   => 'test',
            'test_profile_1023'             => 1,
            'username'                      => $profile['username'],
            'password'                      => '', // effective sandbox password is forced to 1 by the test profile.
            'api_key'                       => '', // secret: paste from the supplied merchant test sheet.
            'terminal_number'               => $profile['terminal_number'],
            'merchant_id'                   => $profile['merchant_id'],
            'from_pocket_number'            => $profile['from_pocket_number'],
            'to_pocket_number'              => $profile['to_pocket_number'],
            'test_otp'                      => $profile['test_otp'],
            'test_skip_otp_request'         => 1,
            'auth_base_url'                 => $profile['auth_base_url'],
            'purchase_base_url'             => $profile['purchase_base_url'],
            'request_timeout'               => 25,
            'otp_time_buffer'               => 0,
            // Fallback only. GreenPay response time / HTTP Date is preferred.
            // Support reported the site clock about 30 seconds behind their clock.
            'provider_time_shift_seconds'   => 30,
        ];
    }
}

if (!function_exists('mlr_greenpay_to_english_digits')) {
    function mlr_greenpay_to_english_digits($value) {
        return strtr((string) $value, [
            '۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9',
            '٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9',
        ]);
    }
}

if (!function_exists('mlr_greenpay_get_settings')) {
    function mlr_greenpay_get_settings() {
        $stored = get_option('mlr_greenpay_settings', []);
        if (!is_array($stored)) {
            $stored = [];
        }
        return array_merge(mlr_greenpay_default_settings(), $stored);
    }
}

if (!function_exists('mlr_greenpay_normalize_amount')) {
    function mlr_greenpay_normalize_amount($value) {
        if (!is_numeric($value)) {
            $value = str_replace([',', '٬', ' '], '', mlr_greenpay_to_english_digits((string) $value));
        }
        return max(0, (int) round((float) $value));
    }
}

if (!function_exists('mlr_greenpay_credentials_fingerprint')) {
    function mlr_greenpay_credentials_fingerprint(array $settings) {
        $settings = mlr_greenpay_apply_test_profile_1023($settings);
        $payload = [
            'username' => (string) ($settings['username'] ?? ''),
            'password' => (string) ($settings['password'] ?? ''),
            'api_key' => (string) ($settings['api_key'] ?? ''),
            'test_otp' => (string) ($settings['test_otp'] ?? ''),
        ];
        return hash_hmac('sha256', wp_json_encode($payload), wp_salt('auth'));
    }
}

if (!function_exists('mlr_greenpay_build_config_snapshot')) {
    function mlr_greenpay_build_config_snapshot(array $settings, $from_pocket, $to_pocket) {
        $settings = mlr_greenpay_apply_test_profile_1023($settings);
        if (($settings['environment'] ?? 'test') !== 'live' && !empty($settings['test_profile_1023'])) {
            $from_pocket = $settings['from_pocket_number'];
            $to_pocket = '';
        }
        $snapshot = [
            'version' => 2,
            'environment' => (($settings['environment'] ?? 'test') === 'live') ? 'live' : 'test',
            'username' => trim((string) ($settings['username'] ?? '')),
            'terminal_number' => trim((string) ($settings['terminal_number'] ?? '')),
            'merchant_id' => preg_replace('/\D+/', '', (string) ($settings['merchant_id'] ?? '')),
            'from_pocket_number' => preg_replace('/\D+/', '', (string) $from_pocket),
            'to_pocket_number' => preg_replace('/\D+/', '', (string) $to_pocket),
            'auth_base_url' => rtrim((string) ($settings['auth_base_url'] ?? ''), '/'),
            'purchase_base_url' => rtrim((string) ($settings['purchase_base_url'] ?? ''), '/'),
            'request_timeout' => max(5, min(60, absint($settings['request_timeout'] ?? 25))),
            'otp_time_buffer' => max(0, min(60, absint($settings['otp_time_buffer'] ?? 10))),
            'provider_time_shift_seconds' => max(-120, min(120, intval($settings['provider_time_shift_seconds'] ?? 30))),
            'test_skip_otp_request' => empty($settings['test_skip_otp_request']) ? 0 : 1,
            'test_profile_1023' => empty($settings['test_profile_1023']) ? 0 : 1,
            // Secrets are never persisted here; only an HMAC is stored so credential drift is detected.
            'credentials_fingerprint' => mlr_greenpay_credentials_fingerprint($settings),
        ];
        return wp_json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}

if (!function_exists('mlr_greenpay_settings_for_transaction')) {
    function mlr_greenpay_settings_for_transaction($tx) {
        $current = mlr_greenpay_get_settings();
        $raw = isset($tx->config_snapshot) ? trim((string) $tx->config_snapshot) : '';
        $snapshot = $raw !== '' ? json_decode($raw, true) : null;

        // Legacy rows created before 8.4.6.39 have no snapshot. Preserve history and use
        // transaction-bound amount/pockets/environment while keeping current credentials.
        if (!is_array($snapshot)) {
            $current['environment'] = ((string) ($tx->environment ?? 'test') === 'live') ? 'live' : 'test';
            $current['from_pocket_number'] = (string) ($tx->from_pocket ?? '');
            $current['to_pocket_number'] = (string) ($tx->to_pocket ?? '');
            return $current;
        }

        $expected_fp = (string) ($snapshot['credentials_fingerprint'] ?? '');
        if ($expected_fp !== '' && !hash_equals($expected_fp, mlr_greenpay_credentials_fingerprint($current))) {
            return new WP_Error(
                'greenpay_credentials_changed',
                'Credentialهای GreenPay پس از ایجاد Snapshot تراکنش تغییر کرده‌اند. برای جلوگیری از اجرای تراکنش با تنظیمات مخلوط، ابتدا تراکنش را به‌صورت مجاز دوباره صف‌بندی کنید.'
            );
        }

        foreach ([
            'environment','username','terminal_number','merchant_id','auth_base_url','purchase_base_url',
            'request_timeout','otp_time_buffer','provider_time_shift_seconds','test_skip_otp_request','test_profile_1023'
        ] as $key) {
            if (array_key_exists($key, $snapshot)) {
                $current[$key] = $snapshot[$key];
            }
        }
        $current['from_pocket_number'] = (string) ($tx->from_pocket ?? ($snapshot['from_pocket_number'] ?? ''));
        $current['to_pocket_number'] = (string) ($tx->to_pocket ?? ($snapshot['to_pocket_number'] ?? ''));
        return $current;
    }
}

if (!function_exists('mlr_greenpay_validate_pockets')) {
    function mlr_greenpay_validate_pockets($from_pocket, $to_pocket, $environment = 'test') {
        $from_pocket = preg_replace('/\D+/', '', mlr_greenpay_to_english_digits($from_pocket));
        $to_pocket = preg_replace('/\D+/', '', mlr_greenpay_to_english_digits($to_pocket));
        if (strlen($from_pocket) !== 16) {
            return new WP_Error('greenpay_invalid_from_pocket', 'FromPocketNumber باید دقیقاً ۱۶ رقم باشد.');
        }
        if ($to_pocket !== '' && strlen($to_pocket) !== 16) {
            return new WP_Error('greenpay_invalid_to_pocket', 'ToPocketNumber در صورت استفاده باید دقیقاً ۱۶ رقم باشد.');
        }
        if ($environment === 'live' && $from_pocket === '1262010000002551') {
            return new WP_Error('greenpay_test_pocket_in_live', 'FromPocketNumber تستی در محیط عملیاتی مجاز نیست.');
        }
        return true;
    }
}

if (!function_exists('mlr_greenpay_sanitize_settings')) {
    function mlr_greenpay_sanitize_settings($input) {
        $current = get_option('mlr_greenpay_settings', []);
        $current = is_array($current) ? $current : [];
        $input = is_array($input) ? $input : [];
        $defaults = mlr_greenpay_default_settings();

        $out = [];
        $out['enabled'] = isset($input['enabled']) ? 1 : 0;
        $out['auto_debit_on_docs_approved'] = isset($input['auto_debit_on_docs_approved']) ? 1 : 0;
        $out['environment'] = (($input['environment'] ?? 'test') === 'live') ? 'live' : 'test';
        $out['test_profile_1023'] = isset($input['test_profile_1023']) ? 1 : 0;
        $out['username'] = sanitize_text_field(trim((string) ($input['username'] ?? '')));

        // Sensitive values stay untouched when their password fields are left empty.
        foreach (['password', 'api_key'] as $secret_key) {
            $incoming = trim((string) ($input[$secret_key] ?? ''));
            if ($incoming === '' && !empty($current[$secret_key])) {
                $out[$secret_key] = (string) $current[$secret_key];
            } else {
                $out[$secret_key] = sanitize_text_field($incoming);
            }
        }

        $out['terminal_number'] = sanitize_text_field(trim((string) ($input['terminal_number'] ?? '')));
        $out['merchant_id'] = preg_replace('/\D+/', '', mlr_greenpay_to_english_digits($input['merchant_id'] ?? ''));
        $out['from_pocket_number'] = preg_replace('/\D+/', '', mlr_greenpay_to_english_digits($input['from_pocket_number'] ?? ''));
        $out['to_pocket_number'] = preg_replace('/\D+/', '', mlr_greenpay_to_english_digits($input['to_pocket_number'] ?? ''));
        $out['test_otp'] = preg_replace('/\D+/', '', mlr_greenpay_to_english_digits($input['test_otp'] ?? '123456'));
        if ($out['test_otp'] === '') {
            $out['test_otp'] = '123456';
        }
        $out['test_skip_otp_request'] = isset($input['test_skip_otp_request']) ? 1 : 0;

        $out['auth_base_url'] = esc_url_raw(trim((string) ($input['auth_base_url'] ?? $defaults['auth_base_url'])));
        $out['purchase_base_url'] = esc_url_raw(trim((string) ($input['purchase_base_url'] ?? $defaults['purchase_base_url'])));
        $out['request_timeout'] = max(5, min(60, absint($input['request_timeout'] ?? $defaults['request_timeout'])));
        $out['otp_time_buffer'] = max(0, min(60, absint($input['otp_time_buffer'] ?? $defaults['otp_time_buffer'])));
        $out['provider_time_shift_seconds'] = max(-120, min(120, intval(mlr_greenpay_to_english_digits($input['provider_time_shift_seconds'] ?? $defaults['provider_time_shift_seconds']))));

        // The supplied online-purchase-1023 sandbox profile is intentionally deterministic:
        // no OTP SMS request, fixed OTP 123456, fixed FromPocket and empty ToPocket.
        // Password 1 is applied only at execution time, so a stored live password is never overwritten.
        if ($out['environment'] === 'test' && !empty($out['test_profile_1023'])) {
            $profile = mlr_greenpay_test_profile_1023();
            foreach (['username','terminal_number','merchant_id','from_pocket_number','to_pocket_number','test_otp','test_skip_otp_request','auth_base_url','purchase_base_url'] as $key) {
                $out[$key] = $profile[$key];
            }
            $out['otp_time_buffer'] = 0;
            $out['provider_time_shift_seconds'] = 0;
        } elseif ($out['environment'] === 'test') {
            if ($out['from_pocket_number'] === '') {
                $out['from_pocket_number'] = '1262010000002551';
            }
            if ($out['test_otp'] === '') {
                $out['test_otp'] = '123456';
            }
        } else {
            // Production always uses the applicant/request-bound source pocket captured at enrollment.
            // Never carry a global/sandbox source pocket or test OTP bypass into live execution.
            $out['from_pocket_number'] = '';
            $out['test_profile_1023'] = 0;
            $out['test_skip_otp_request'] = 0;
        }

        // Do not allow an incomplete live configuration to become active accidentally.
        if ($out['enabled'] && $out['environment'] === 'live') {
            $missing = [];
            foreach (['username','password','api_key','terminal_number','merchant_id','auth_base_url','purchase_base_url'] as $key) {
                if (trim((string) ($out[$key] ?? '')) === '') {
                    $missing[] = $key;
                }
            }
            // In Production the source pocket belongs to each applicant/request and is captured
            // when the request is explicitly enrolled in GreenPay. A global FromPocket is therefore
            // optional and never required to activate the integration.
            if ($out['to_pocket_number'] !== '' && strlen($out['to_pocket_number']) !== 16) {
                $pocket_check = new WP_Error('greenpay_invalid_to_pocket', 'ToPocketNumber در صورت استفاده باید دقیقاً ۱۶ رقم باشد.');
            } elseif ($out['from_pocket_number'] !== '') {
                $pocket_check = mlr_greenpay_validate_pockets($out['from_pocket_number'], $out['to_pocket_number'], 'live');
            } else {
                $pocket_check = true;
            }
            $https_ok = strtolower((string) wp_parse_url($out['auth_base_url'], PHP_URL_SCHEME)) === 'https'
                && strtolower((string) wp_parse_url($out['purchase_base_url'], PHP_URL_SCHEME)) === 'https';

            // The vendor reference plugin uses /api/sandbox/submit for token acquisition even when
            // the merchant credentials are operational. Follow the reference contract exactly.
            if ($missing || is_wp_error($pocket_check) || !$https_ok) {
                $out['enabled'] = 0;
                if ($missing) {
                    $message = 'GreenPay عملیاتی فعال نشد؛ فیلدهای الزامی ناقص‌اند: ' . implode(', ', array_unique($missing));
                } elseif (is_wp_error($pocket_check)) {
                    $message = $pocket_check->get_error_message();
                } else {
                    $message = 'GreenPay عملیاتی فعال نشد؛ هر دو آدرس API باید HTTPS باشند.';
                }
                add_settings_error('mlr_greenpay_settings', 'mlr_greenpay_live_invalid', $message, 'error');
            }
        }

        return $out;
    }
}

/**
 * Existing Shahkar fields on the shared API settings page must not be erased
 * simply because a masked/empty input is submitted.
 */
if (!function_exists('mlr_sanitize_preserve_shahkar_client_id')) {
    function mlr_sanitize_preserve_shahkar_client_id($value) {
        $value = sanitize_text_field(trim((string) $value));
        if ($value === '') {
            $current = get_option('mlr_shahkar_client_id', '');
            return is_scalar($current) ? (string) $current : '';
        }
        return $value;
    }
}
if (!function_exists('mlr_sanitize_preserve_shahkar_client_secret')) {
    function mlr_sanitize_preserve_shahkar_client_secret($value) {
        $value = sanitize_text_field(trim((string) $value));
        if ($value === '') {
            $current = get_option('mlr_shahkar_client_secret', '');
            return is_scalar($current) ? (string) $current : '';
        }
        return $value;
    }
}
if (!function_exists('mlr_sanitize_preserve_shahkar_owner_nid')) {
    function mlr_sanitize_preserve_shahkar_owner_nid($value) {
        $value = preg_replace('/\D+/', '', mlr_greenpay_to_english_digits($value));
        if ($value === '') {
            $current = get_option('mlr_shahkar_owner_nid', '');
            return is_scalar($current) ? (string) $current : '';
        }
        return sanitize_text_field($value);
    }
}

add_action('admin_init', 'mlr_greenpay_register_settings', 20);
function mlr_greenpay_register_settings() {
    register_setting('mlr_api_settings_group', 'mlr_greenpay_settings', [
        'sanitize_callback' => 'mlr_greenpay_sanitize_settings',
    ]);
}

/**
 * Keep a second, non-autoloaded snapshot of API credentials. WordPress plugin
 * file updates normally do not delete options, but this makes the settings
 * self-healing if an update/deployment accidentally writes empty values.
 */
function mlr_api_settings_persistence_bootstrap() {
    $backup = get_option('mlr_api_settings_persistent_backup', []);
    if (!is_array($backup)) $backup = [];
    $changed_backup = false;

    // The secondary snapshot deliberately contains no passwords/API secrets.
    foreach (['shahkar_client_secret'] as $secret_backup_key) {
        if (array_key_exists($secret_backup_key, $backup)) {
            unset($backup[$secret_backup_key]);
            $changed_backup = true;
        }
    }
    $legacy_map = [
        'mlr_shahkar_client_id' => 'shahkar_client_id',
        'mlr_shahkar_owner_nid' => 'shahkar_owner_nid',
        'mlr_calculator_plan_selector_mode' => 'calculator_plan_selector_mode',
    ];
    foreach ($legacy_map as $option_name => $backup_key) {
        $current = get_option($option_name, '');
        $current = is_scalar($current) ? trim((string) $current) : '';
        $saved = isset($backup[$backup_key]) && is_scalar($backup[$backup_key]) ? trim((string) $backup[$backup_key]) : '';
        if ($current !== '') {
            if ($saved !== $current) {
                $backup[$backup_key] = $current;
                $changed_backup = true;
            }
        } elseif ($saved !== '') {
            update_option($option_name, $backup[$backup_key], false);
        }
    }

    $greenpay_raw = get_option('mlr_greenpay_settings', null);
    $greenpay = is_array($greenpay_raw) ? $greenpay_raw : [];
    $saved_greenpay = isset($backup['greenpay']) && is_array($backup['greenpay']) ? $backup['greenpay'] : [];
    foreach (['password', 'api_key'] as $secret_key) {
        if (array_key_exists($secret_key, $saved_greenpay)) {
            unset($saved_greenpay[$secret_key]);
            $changed_backup = true;
        }
    }

    // Only non-secret operational configuration can self-heal from the secondary snapshot.
    $safe_backup_keys = [
        'enabled', 'auto_debit_on_docs_approved', 'environment', 'username',
        'terminal_number', 'merchant_id', 'from_pocket_number', 'to_pocket_number',
        'test_otp', 'test_skip_otp_request', 'auth_base_url', 'purchase_base_url',
        'request_timeout', 'otp_time_buffer', 'provider_time_shift_seconds',
    ];
    if (empty($greenpay) && !empty($saved_greenpay)) {
        foreach ($safe_backup_keys as $key) {
            if (array_key_exists($key, $saved_greenpay)) $greenpay[$key] = $saved_greenpay[$key];
        }
    }

    // Existing WooCommerce gateway remains an intentional primary-source migration path.
    $wc_greenpay = get_option('woocommerce_bki_wallet_settings', []);
    if (is_array($wc_greenpay) && !empty($wc_greenpay)) {
        $mapping = [
            'username' => 'username',
            'password' => 'password',
            'api_key' => 'api_key',
            'terminal_number' => 'terminal_number',
            'merchant_id' => 'merchant_id',
            'to_pocket_number' => 'to_pocket_number',
        ];
        foreach ($mapping as $new_key => $old_key) {
            if (empty($greenpay[$new_key]) && !empty($wc_greenpay[$old_key])) $greenpay[$new_key] = $wc_greenpay[$old_key];
        }
    }

    foreach ($safe_backup_keys as $key) {
        $current = isset($greenpay[$key]) && is_scalar($greenpay[$key]) ? trim((string) $greenpay[$key]) : '';
        $saved = isset($saved_greenpay[$key]) && is_scalar($saved_greenpay[$key]) ? trim((string) $saved_greenpay[$key]) : '';
        if ($current === '' && $saved !== '') $greenpay[$key] = $saved_greenpay[$key];
    }

    if (!empty($greenpay)) {
        update_option('mlr_greenpay_settings', $greenpay, false);
        $safe_snapshot = [];
        foreach ($safe_backup_keys as $key) {
            if (!array_key_exists($key, $greenpay)) continue;
            $value = $greenpay[$key];
            if (is_scalar($value) && trim((string) $value) !== '') {
                $safe_snapshot[$key] = $value;
            } elseif (in_array($key, ['enabled', 'auto_debit_on_docs_approved', 'test_skip_otp_request'], true)) {
                $safe_snapshot[$key] = $value;
            }
        }
        if ($safe_snapshot !== $saved_greenpay) {
            $backup['greenpay'] = $safe_snapshot;
            $changed_backup = true;
        }
    } elseif (isset($backup['greenpay']) && $saved_greenpay !== $backup['greenpay']) {
        $backup['greenpay'] = $saved_greenpay;
        $changed_backup = true;
    }

    if ($changed_backup || get_option('mlr_api_settings_persistent_backup', null) === null) {
        update_option('mlr_api_settings_persistent_backup', $backup, false);
    }
}

add_action('plugins_loaded', 'mlr_api_settings_persistence_bootstrap', 30);

function mlr_api_settings_persistence_on_update($option, $old_value, $value) {
    if (!in_array($option, [
        'mlr_shahkar_client_id',
        'mlr_shahkar_client_secret',
        'mlr_shahkar_owner_nid',
        'mlr_calculator_plan_selector_mode',
        'mlr_greenpay_settings',
    ], true)) {
        return;
    }

    // Defer the snapshot until the just-updated option is readable normally.
    if (!wp_next_scheduled('mlr_api_settings_snapshot_event')) {
        wp_schedule_single_event(time() + 1, 'mlr_api_settings_snapshot_event');
    }
}
add_action('updated_option', 'mlr_api_settings_persistence_on_update', 10, 3);
add_action('mlr_api_settings_snapshot_event', 'mlr_api_settings_persistence_bootstrap');

// Capture the credentials before WordPress replaces plugin files and restore/check them immediately after upgrade.
function mlr_api_settings_before_plugin_upgrade($response, $hook_extra) {
    mlr_api_settings_persistence_bootstrap();
    return $response;
}
add_filter('upgrader_pre_install', 'mlr_api_settings_before_plugin_upgrade', 10, 2);

function mlr_api_settings_after_plugin_upgrade($upgrader, $hook_extra) {
    if (!is_array($hook_extra) || ($hook_extra['type'] ?? '') !== 'plugin') {
        return;
    }
    mlr_api_settings_persistence_bootstrap();
}
add_action('upgrader_process_complete', 'mlr_api_settings_after_plugin_upgrade', 10, 2);

function mlr_greenpay_transactions_table() {
    global $wpdb;
    return $wpdb->prefix . 'mlr_greenpay_transactions';
}

function mlr_greenpay_install_schema() {
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $table = mlr_greenpay_transactions_table();
    $charset_collate = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE {$table} (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        request_id bigint(20) UNSIGNED NOT NULL,
        amount DECIMAL(15,2) NOT NULL DEFAULT 0,
        from_pocket varchar(32) NOT NULL DEFAULT '',
        to_pocket varchar(32) NOT NULL DEFAULT '',
        ref_number varchar(100) NOT NULL DEFAULT '',
        environment varchar(20) NOT NULL DEFAULT 'test',
        status varchar(30) NOT NULL DEFAULT 'queued',
        eligible tinyint(1) NOT NULL DEFAULT 0,
        eligibility_updated_at datetime DEFAULT NULL,
        eligibility_updated_by bigint(20) UNSIGNED NOT NULL DEFAULT 0,
        trigger_source varchar(50) NOT NULL DEFAULT '',
        attempts int(11) NOT NULL DEFAULT 0,
        otp_requested_at datetime DEFAULT NULL,
        send_time varchar(20) DEFAULT NULL,
        provider_status varchar(100) DEFAULT NULL,
        provider_reference varchar(191) DEFAULT NULL,
        last_http_code int(11) DEFAULT NULL,
        last_error text DEFAULT NULL,
        last_response longtext DEFAULT NULL,
        config_snapshot longtext DEFAULT NULL,
        reconciled_at datetime DEFAULT NULL,
        reconciled_by bigint(20) UNSIGNED NOT NULL DEFAULT 0,
        reconciliation_note text DEFAULT NULL,
        created_at datetime NOT NULL,
        updated_at datetime NOT NULL,
        debited_at datetime DEFAULT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY request_id (request_id),
        UNIQUE KEY ref_number (ref_number),
        KEY status (status),
        KEY eligible_status (eligible, status),
        KEY updated_at (updated_at)
    ) {$charset_collate};";
    dbDelta($sql);
    update_option('mlr_greenpay_db_version', '1.2', false);

    // 8.4.6.25 had no per-file opt-in. Preserve only financial/in-flight history automatically.
    // Old queued jobs are stopped and require an explicit administrator selection in 8.4.6.26.
    if (!get_option('mlr_greenpay_eligibility_migrated_1_1')) {
        $old_queued = $wpdb->get_col("SELECT request_id FROM {$table} WHERE status = 'queued'");
        foreach ((array) $old_queued as $old_request_id) {
            wp_clear_scheduled_hook('mlr_greenpay_process_debit', [absint($old_request_id)]);
        }
        $wpdb->query("UPDATE {$table} SET eligible = 1 WHERE status IN ('success','unknown','processing','awaiting_otp')");
        $wpdb->query("UPDATE {$table} SET eligible = 0, status = 'not_applicable' WHERE status = 'queued'");
        update_option('mlr_greenpay_eligibility_migrated_1_1', 1, false);
    }
}

function mlr_greenpay_maybe_install_schema() {
    global $wpdb;
    if (get_option('mlr_greenpay_db_version') === '1.2' && get_transient('mlr_greenpay_schema_ok')) {
        return;
    }

    $table = mlr_greenpay_transactions_table();
    $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
    if ($exists !== $table || get_option('mlr_greenpay_db_version') !== '1.2') {
        mlr_greenpay_install_schema();
    }
    set_transient('mlr_greenpay_schema_ok', 1, DAY_IN_SECONDS);
}
add_action('plugins_loaded', 'mlr_greenpay_maybe_install_schema', 40);

function mlr_greenpay_mask_pocket($pocket) {
    $pocket = preg_replace('/\D+/', '', (string) $pocket);
    if (strlen($pocket) <= 8) {
        return $pocket;
    }
    return substr($pocket, 0, 4) . str_repeat('*', max(4, strlen($pocket) - 8)) . substr($pocket, -4);
}

function mlr_greenpay_redact_string($value) {
    $value = (string) $value;
    // Redact common secret key/value fragments even when the provider returned non-JSON text.
    $value = preg_replace('/((?:Bearer|Token|Password|ApiKey|API[_ -]?Key|Authorization|OTP)\s*[:=]\s*)[^\s,;"\']+/iu', '$1***', $value);
    $value = preg_replace_callback('/(?<!\d)(\d{16})(?!\d)/', function ($m) {
        return mlr_greenpay_mask_pocket($m[1]);
    }, $value);
    if (strlen($value) > 2000) {
        $value = substr($value, 0, 2000) . '…';
    }
    return $value;
}

function mlr_greenpay_redact_response($value, $depth = 0) {
    if ($depth > 8) {
        return '[truncated]';
    }
    if (is_array($value)) {
        $out = [];
        foreach ($value as $key => $item) {
            $key_l = strtolower((string) $key);
            if (preg_match('/token|password|secret|apikey|api_key|authorization|otp/', $key_l)) {
                $out[$key] = '***';
                continue;
            }
            if (preg_match('/pocket/', $key_l) && is_scalar($item)) {
                $out[$key] = mlr_greenpay_mask_pocket((string) $item);
                continue;
            }
            $out[$key] = mlr_greenpay_redact_response($item, $depth + 1);
        }
        return $out;
    }
    if (is_object($value)) {
        return mlr_greenpay_redact_response((array) $value, $depth + 1);
    }
    if (is_string($value)) {
        return mlr_greenpay_redact_string($value);
    }
    return $value;
}

function mlr_greenpay_encode_response($response) {
    if (is_wp_error($response)) {
        $response = [
            'error_code' => $response->get_error_code(),
            'error_message' => $response->get_error_message(),
            'error_data' => $response->get_error_data(),
        ];
    }
    return wp_json_encode(mlr_greenpay_redact_response($response), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function mlr_greenpay_extract_error_message($response, $fallback = 'خطای نامشخص GreenPay') {
    if (is_wp_error($response)) {
        return $response->get_error_message();
    }
    if (!is_array($response)) {
        return $fallback;
    }
    if (!empty($response['Errors']) && is_array($response['Errors'])) {
        $first = reset($response['Errors']);
        if (is_array($first)) {
            foreach (['ErrorMessage','Message','message'] as $key) {
                if (!empty($first[$key])) {
                    return sanitize_text_field((string) $first[$key]);
                }
            }
        }
    }
    foreach (['Message','message','ErrorMessage','error'] as $key) {
        if (!empty($response[$key]) && is_scalar($response[$key])) {
            return sanitize_text_field((string) $response[$key]);
        }
    }
    return $fallback;
}

function mlr_greenpay_response_is_success($response) {
    if (!is_array($response)) {
        return false;
    }
    $http = (int) ($response['http_code'] ?? 0);
    if ($http < 200 || $http >= 300) {
        return false;
    }

    $status = strtolower(trim((string) ($response['Status'] ?? $response['status'] ?? '')));
    $success = $response['Success'] ?? $response['success'] ?? null;
    $is_success = in_array($status, ['success','successful','ok'], true)
        || $success === true
        || $success === 1
        || $success === '1'
        || strtolower((string) $success) === 'true';

    // Provider contracts can evolve; installations may opt into an additional *documented*
    // success shape without weakening the conservative default behavior.
    return (bool) apply_filters('mlr_greenpay_success_response', $is_success, $response);
}

function mlr_greenpay_extract_send_time($response, $buffer_seconds = 0, $fallback_shift_seconds = 0) {
    $send_time = '';
    $source = 'local_fallback';

    // First choice: an explicit transaction/OTP timestamp returned by GreenPay.
    if (is_array($response)) {
        foreach (['SendTime','OtpSendTime','TransactionTime','sendTime'] as $key) {
            if (!empty($response[$key]) && is_scalar($response[$key])) {
                $candidate = trim(sanitize_text_field((string) $response[$key]));
                // Keep only the time component expected by transferByPocket.
                if (preg_match('/(?:^|T|\s)(\d{1,2}:\d{2}:\d{2})(?:\.|Z|[+\-]|$)/', $candidate, $m)) {
                    $send_time = $m[1];
                } elseif (preg_match('/^\d{1,2}:\d{2}:\d{2}$/', $candidate)) {
                    $send_time = $candidate;
                }
                if ($send_time !== '') {
                    $source = 'provider_body';
                    break;
                }
            }
        }
    }

    // Second choice: GreenPay HTTP server Date header. This removes dependency
    // on the WordPress/PHP server clock and directly fixes small clock drift.
    if ($send_time === '' && is_array($response) && !empty($response['provider_date_header'])) {
        try {
            $provider_dt = new DateTime((string) $response['provider_date_header']);
            $provider_dt->setTimezone(new DateTimeZone('Asia/Tehran'));
            $send_time = $provider_dt->format('H:i:s');
            $source = 'provider_http_date';
        } catch (Exception $e) {
            $send_time = '';
        }
    }

    // Final fallback: local Tehran clock plus the configured signed correction.
    if ($send_time === '') {
        try {
            $dt = new DateTime('now', new DateTimeZone('Asia/Tehran'));
            $shift = max(-120, min(120, intval($fallback_shift_seconds)));
            if ($shift !== 0) {
                $dt->modify(($shift > 0 ? '+' : '') . $shift . ' seconds');
            }
            $send_time = $dt->format('H:i:s');
        } catch (Exception $e) {
            $timestamp = current_time('timestamp') + max(-120, min(120, intval($fallback_shift_seconds)));
            $send_time = date('H:i:s', $timestamp);
        }
    }

    // Keep the legacy configurable buffer, but apply it after choosing the most
    // authoritative clock source. Default is zero in Production.
    $buffer_seconds = max(0, min(60, absint($buffer_seconds)));
    if ($buffer_seconds > 0) {
        $timestamp = strtotime($send_time);
        if ($timestamp !== false) {
            $send_time = date('H:i:s', $timestamp + $buffer_seconds);
        }
    }

    return (string) apply_filters('mlr_greenpay_send_time', $send_time, $source, $response);
}

/**
 * Build SendTime at the exact moment transferByPocket is called.
 *
 * GreenPay validates SendTime in a very short server-side window. The OTP request
 * timestamp must therefore never be reused here: a human can easily spend more
 * than that window reading the SMS and entering the code. Support also confirmed
 * that the hosting clock is roughly 30 seconds behind GreenPay, so the signed
 * correction is applied to the *fresh transfer clock* on every attempt.
 *
 * Clock correction is an operational setting, not a financial snapshot value;
 * always prefer the current admin setting so an operator can correct drift
 * immediately without rebuilding/re-queueing existing transactions.
 */
function mlr_greenpay_fresh_transfer_send_time($settings = [], $extra_seconds = 0) {
    $current = mlr_greenpay_get_settings();
    $shift = array_key_exists('provider_time_shift_seconds', $current)
        ? intval($current['provider_time_shift_seconds'])
        : intval(is_array($settings) ? ($settings['provider_time_shift_seconds'] ?? 30) : 30);
    $shift = max(-120, min(120, $shift));
    $extra_seconds = max(-5, min(5, intval($extra_seconds)));

    try {
        $dt = new DateTime('now', new DateTimeZone('Asia/Tehran'));
        $total = $shift + $extra_seconds;
        if ($total !== 0) {
            $dt->modify(($total > 0 ? '+' : '') . $total . ' seconds');
        }
        $send_time = $dt->format('H:i:s');
    } catch (Exception $e) {
        $timestamp = current_time('timestamp') + $shift + $extra_seconds;
        $send_time = date('H:i:s', $timestamp);
    }

    return (string) apply_filters('mlr_greenpay_fresh_transfer_send_time', $send_time, $shift, $extra_seconds, $settings);
}

/**
 * Only an explicit provider time-window rejection is safe for a single automatic
 * transfer retry. Generic transport/5xx/unknown failures must never be retried,
 * because doing so could double-debit a wallet.
 */
function mlr_greenpay_is_time_window_rejection($response) {
    if (is_wp_error($response)) {
        return false;
    }
    if (!is_array($response)) {
        return false;
    }
    $http = (int) ($response['http_code'] ?? 0);
    if ($http < 400 || $http >= 500) {
        return false;
    }

    $message = mlr_greenpay_extract_error_message($response, '');
    $haystack = trim($message . ' ' . wp_json_encode(mlr_greenpay_redact_response($response), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    if ($haystack === '') {
        return false;
    }
    if (function_exists('mb_strtolower')) {
        $haystack = mb_strtolower($haystack, 'UTF-8');
    } else {
        $haystack = strtolower($haystack);
    }

    foreach ([
        'مهلت زمانی', 'زمانی مقرر', 'تراکنش با مبدا', 'تراکنش با مبدأ', 'منقضی',
        'sendtime', 'send time', 'time window', 'deadline', 'time limit', 'expired', 'expiration'
    ] as $marker) {
        if (strpos($haystack, $marker) !== false) {
            return true;
        }
    }
    return false;
}

function mlr_greenpay_extract_provider_reference($response) {
    if (!is_array($response)) {
        return '';
    }
    foreach (['TransactionId','TransactionID','transactionId','ReferenceNumber','RefNumber','TraceNumber','TrackingCode','RequestId','requestId'] as $key) {
        if (isset($response[$key]) && is_scalar($response[$key]) && trim((string) $response[$key]) !== '') {
            return sanitize_text_field((string) $response[$key]);
        }
    }
    return '';
}

function mlr_greenpay_classify_failure($response) {
    if (is_wp_error($response)) {
        $data = $response->get_error_data();
        if (is_array($data) && !empty($data['transport_error'])) {
            return 'unknown';
        }
        return 'failed';
    }
    if (!is_array($response)) {
        return 'unknown';
    }
    $http = (int) ($response['http_code'] ?? 0);
    if ($http === 0 || $http >= 500) {
        return 'unknown';
    }
    if ($http >= 400 && $http < 500) {
        return 'failed';
    }
    if ($http >= 200 && $http < 300) {
        $status = strtolower(trim((string) ($response['Status'] ?? $response['status'] ?? '')));
        $has_explicit_error = !empty($response['Errors'])
            || !empty($response['ErrorMessage'])
            || !empty($response['error'])
            || in_array($status, ['failed','failure','error','invalid','rejected'], true)
            || (($response['Success'] ?? null) === false);
        // An explicit business rejection is a definite failure and can be retried after correction.
        // An opaque/malformed 2xx result is unknown because the debit may already have happened.
        return $has_explicit_error ? 'failed' : 'unknown';
    }
    return 'failed';
}

function mlr_greenpay_add_event($request_id, $type, $details) {
    if (function_exists('mlr_add_request_event')) {
        mlr_add_request_event((int) $request_id, $type, $details);
    }
}

function mlr_greenpay_notify_admin($request_id, $message) {
    if (function_exists('mlr_create_notification')) {
        mlr_create_notification(
            'greenpay',
            $message,
            admin_url('admin.php?page=mlr-greenpay-transactions&request_id=' . absint($request_id))
        );
    }
}


function mlr_greenpay_get_transaction($request_id) {
    global $wpdb;
    $request_id = absint($request_id);
    if (!$request_id) {
        return null;
    }
    mlr_greenpay_maybe_install_schema();
    return $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM " . mlr_greenpay_transactions_table() . " WHERE request_id = %d",
        $request_id
    ));
}

function mlr_greenpay_is_eligible($request_id) {
    $tx = mlr_greenpay_get_transaction($request_id);
    return $tx && (int) $tx->eligible === 1;
}

function mlr_greenpay_current_amount_for_request($request) {
    if (!$request) {
        return 0;
    }
    $value = (isset($request->facility_amount) && $request->facility_amount !== '' && $request->facility_amount !== null)
        ? $request->facility_amount
        : ($request->loan_amount ?? 0);
    return mlr_greenpay_normalize_amount($value);
}

function mlr_greenpay_build_financial_snapshot($request, $settings = null, $from_pocket_override = '') {
    if (!$request) {
        return new WP_Error('greenpay_request_missing', 'اطلاعات پرونده برای ساخت Snapshot موجود نیست.');
    }
    if (!is_array($settings)) {
        $settings = mlr_greenpay_get_settings();
    }
    $settings = mlr_greenpay_apply_test_profile_1023($settings);

    $environment = (($settings['environment'] ?? 'test') === 'live') ? 'live' : 'test';
    $using_test_profile_1023 = $environment === 'test' && !empty($settings['test_profile_1023']);
    $from_pocket_override = preg_replace('/\D+/', '', mlr_greenpay_to_english_digits($from_pocket_override));
    $from_pocket = $from_pocket_override !== ''
        ? $from_pocket_override
        : preg_replace('/\D+/', '', mlr_greenpay_to_english_digits($settings['from_pocket_number'] ?? ''));
    if ($environment === 'test' && $from_pocket === '') {
        $from_pocket = '1262010000002551';
    }
    $to_pocket = preg_replace('/\D+/', '', mlr_greenpay_to_english_digits($settings['to_pocket_number'] ?? ''));

    if ($using_test_profile_1023) {
        // Contract supplied for sandbox online-purchase 1023: these are fixed values.
        $from_pocket = '1262010000002551';
        $to_pocket = '';
    } else {
        // The full request object is intentionally passed so an installation can map each applicant
        // to a verified GreenPay pocket without modifying the core integration.
        if ($from_pocket_override === '') {
            $from_pocket = preg_replace('/\D+/', '', mlr_greenpay_to_english_digits(
                apply_filters('mlr_greenpay_from_pocket_number', $from_pocket, $request, $settings)
            ));
        }
        $to_pocket = preg_replace('/\D+/', '', mlr_greenpay_to_english_digits(
            apply_filters('mlr_greenpay_to_pocket_number', $to_pocket, $request, $settings)
        ));
    }

    $pocket_check = mlr_greenpay_validate_pockets($from_pocket, $to_pocket, $environment);
    if (is_wp_error($pocket_check)) {
        return $pocket_check;
    }

    $amount = mlr_greenpay_current_amount_for_request($request);
    if ($amount <= 0) {
        return new WP_Error('greenpay_invalid_amount', 'مبلغ تسهیلات پرونده معتبر نیست.');
    }

    return [
        'amount' => $amount,
        'from_pocket' => $from_pocket,
        'to_pocket' => $to_pocket,
        'environment' => $environment,
        'config_snapshot' => mlr_greenpay_build_config_snapshot($settings, $from_pocket, $to_pocket),
    ];
}

function mlr_greenpay_status_is_docs_approved($status) {
    $status = function_exists('mlr_normalize_status') ? mlr_normalize_status($status) : trim((string) $status);
    return $status === 'تأیید مدارک';
}

/**
 * Explicit per-file GreenPay enrollment. Default is always non-eligible.
 * Financial/in-flight records cannot be detached because doing so would hide
 * an already completed or uncertain debit.
 */
function mlr_greenpay_set_eligibility($request_id, $eligible, $source = 'admin', $from_pocket_override = '') {
    global $wpdb;
    $request_id = absint($request_id);
    $eligible = $eligible ? 1 : 0;
    if (!$request_id) {
        return new WP_Error('greenpay_invalid_request', 'شناسه پرونده معتبر نیست.');
    }

    mlr_greenpay_maybe_install_schema();
    $requests_table = $wpdb->prefix . 'loan_requests';
    $tx_table = mlr_greenpay_transactions_table();
    $request = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$requests_table} WHERE id = %d",
        $request_id
    ));
    if (!$request) {
        return new WP_Error('greenpay_request_not_found', 'پرونده پیدا نشد.');
    }

    $tx = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$tx_table} WHERE request_id = %d", $request_id));
    $now = current_time('mysql');
    $user_id = get_current_user_id();

    if (!$eligible) {
        if (!$tx || (int) $tx->eligible !== 1) {
            return true;
        }
        if (in_array($tx->status, ['success','processing','awaiting_otp','unknown'], true)) {
            return new WP_Error('greenpay_eligibility_locked', 'این پرونده تراکنش انجام‌شده/درحال‌پردازش/نامشخص دارد و برای حفظ سابقه مالی نمی‌توان آن را از GreenPay خارج کرد.');
        }

        $updated = $wpdb->update($tx_table, [
            'eligible' => 0,
            'status' => 'not_applicable',
            'eligibility_updated_at' => $now,
            'eligibility_updated_by' => $user_id,
            'last_error' => $tx->status === 'queued' ? 'کسری پیش از ارسال توسط مدیر لغو و پرونده از GreenPay خارج شد.' : $tx->last_error,
            'updated_at' => $now,
        ], [
            'request_id' => $request_id,
            'eligible' => 1,
            'status' => $tx->status,
        ]);

        if ((int) $updated !== 1) {
            return new WP_Error('greenpay_concurrent_state_change', 'وضعیت GreenPay هم‌زمان تغییر کرده است؛ برای جلوگیری از تداخل مالی صفحه را تازه‌سازی و دوباره بررسی کنید.');
        }
        if ($tx->status === 'queued') {
            wp_clear_scheduled_hook('mlr_greenpay_process_debit', [$request_id]);
        }
        mlr_greenpay_add_event($request_id, 'greenpay_eligibility_off', 'پرونده از فرآیند کسری GreenPay خارج شد.');
        return true;
    }

    $settings = mlr_greenpay_get_settings();
    $existing_from_pocket = $tx && !empty($tx->from_pocket) ? (string) $tx->from_pocket : '';
    $effective_from_pocket = preg_replace('/\D+/', '', mlr_greenpay_to_english_digits($from_pocket_override));
    if ($effective_from_pocket === '') {
        $effective_from_pocket = $existing_from_pocket;
    }
    $snapshot = mlr_greenpay_build_financial_snapshot($request, $settings, $effective_from_pocket);
    if (is_wp_error($snapshot)) {
        return $snapshot;
    }

    if ($tx) {
        if ((int) $tx->eligible !== 1 && in_array($tx->status, ['success','processing','awaiting_otp','unknown'], true)) {
            return new WP_Error('greenpay_history_locked', 'سابقه مالی این پرونده در وضعیت قفل‌شده است و قابل بازنویسی نیست.');
        }

        $updates = [
            'eligible' => 1,
            'eligibility_updated_at' => $now,
            'eligibility_updated_by' => $user_id,
            'updated_at' => $now,
        ];
        if (in_array($tx->status, ['not_applicable','cancelled'], true)) {
            $updates = array_merge($updates, [
                'status' => 'not_started',
                'amount' => $snapshot['amount'],
                'from_pocket' => $snapshot['from_pocket'],
                'to_pocket' => $snapshot['to_pocket'],
                'environment' => $snapshot['environment'],
                'config_snapshot' => $snapshot['config_snapshot'],
                'last_error' => null,
                'last_http_code' => null,
                'last_response' => null,
                'provider_status' => null,
                'provider_reference' => null,
                'otp_requested_at' => null,
                'send_time' => null,
                'reconciled_at' => null,
                'reconciled_by' => 0,
                'reconciliation_note' => null,
            ]);
        }

        $updated = $wpdb->update($tx_table, $updates, [
            'request_id' => $request_id,
            'status' => $tx->status,
            'eligible' => (int) $tx->eligible,
        ]);
        if ($updated === false || ((int) $tx->eligible !== 1 && (int) $updated !== 1)) {
            return new WP_Error('greenpay_concurrent_state_change', 'ذخیره وضعیت GreenPay انجام نشد یا وضعیت هم‌زمان تغییر کرده است.');
        }
    } else {
        $inserted = $wpdb->insert($tx_table, [
            'request_id' => $request_id,
            'amount' => $snapshot['amount'],
            'from_pocket' => $snapshot['from_pocket'],
            'to_pocket' => $snapshot['to_pocket'],
            'ref_number' => 'MLR-GP-' . $request_id,
            'environment' => $snapshot['environment'],
            'config_snapshot' => $snapshot['config_snapshot'],
            'status' => 'not_started',
            'eligible' => 1,
            'eligibility_updated_at' => $now,
            'eligibility_updated_by' => $user_id,
            'trigger_source' => sanitize_key($source),
            'attempts' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        if (!$inserted) {
            return new WP_Error('greenpay_db_error', 'ثبت پرونده در GreenPay انجام نشد.');
        }
    }

    mlr_greenpay_add_event($request_id, 'greenpay_eligibility_on', 'پرونده به‌عنوان مشمول کسری GreenPay علامت‌گذاری شد.');

    if (mlr_greenpay_status_is_docs_approved($request->status) && !empty($settings['enabled']) && !empty($settings['auto_debit_on_docs_approved'])) {
        mlr_greenpay_queue_request($request_id, 'eligibility_enabled');
    }
    return true;
}


function mlr_greenpay_update_from_pocket($request_id, $from_pocket) {
    global $wpdb;
    $request_id = absint($request_id);
    $from_pocket = preg_replace('/\D+/', '', mlr_greenpay_to_english_digits($from_pocket));
    if (!$request_id || strlen($from_pocket) !== 16) {
        return new WP_Error('greenpay_invalid_from_pocket', 'FromPocketNumber باید دقیقاً ۱۶ رقم باشد.');
    }

    $table = mlr_greenpay_transactions_table();
    $tx = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE request_id = %d", $request_id));
    if (!$tx || (int) $tx->eligible !== 1) {
        return new WP_Error('greenpay_not_eligible', 'پرونده هنوز مشمول GreenPay نیست.');
    }
    if (!in_array($tx->status, ['not_started','cancelled','failed'], true)) {
        return new WP_Error('greenpay_pocket_locked', 'در وضعیت فعلی تراکنش، تغییر Pocket مبدأ مجاز نیست.');
    }

    $settings = mlr_greenpay_get_settings();
    $check = mlr_greenpay_validate_pockets($from_pocket, (string) ($tx->to_pocket ?? ''), (($settings['environment'] ?? 'test') === 'live') ? 'live' : 'test');
    if (is_wp_error($check)) {
        return $check;
    }

    $requests_table = $wpdb->prefix . 'loan_requests';
    $request = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$requests_table} WHERE id = %d", $request_id));
    if (!$request) {
        return new WP_Error('greenpay_request_not_found', 'پرونده پیدا نشد.');
    }
    $snapshot = mlr_greenpay_build_financial_snapshot($request, $settings, $from_pocket);
    if (is_wp_error($snapshot)) {
        return $snapshot;
    }

    $updated = $wpdb->update($table, [
        'from_pocket' => $snapshot['from_pocket'],
        'to_pocket' => $snapshot['to_pocket'],
        'amount' => $snapshot['amount'],
        'environment' => $snapshot['environment'],
        'config_snapshot' => $snapshot['config_snapshot'],
        'updated_at' => current_time('mysql'),
    ], [
        'request_id' => $request_id,
        'eligible' => 1,
        'status' => $tx->status,
    ]);
    if ((int) $updated !== 1) {
        return new WP_Error('greenpay_concurrent_state_change', 'Pocket ذخیره نشد یا وضعیت تراکنش هم‌زمان تغییر کرده است.');
    }
    mlr_greenpay_add_event($request_id, 'greenpay_from_pocket_updated', 'Pocket مبدأ پرونده برای GreenPay به‌روزرسانی شد.');
    return true;
}

function mlr_greenpay_schedule_request($request_id) {
    $request_id = absint($request_id);
    if (!$request_id) {
        return;
    }
    if (!wp_next_scheduled('mlr_greenpay_process_debit', [$request_id])) {
        wp_schedule_single_event(time() + 1, 'mlr_greenpay_process_debit', [$request_id]);
    }
    // Ask WP-Cron to spawn without making the current status transition depend on the API request.
    if ((!defined('DISABLE_WP_CRON') || !DISABLE_WP_CRON) && function_exists('spawn_cron')) {
        spawn_cron(time());
    }
}

function mlr_greenpay_queue_request($request_id, $source = 'status_transition') {
    global $wpdb;
    $request_id = absint($request_id);
    if (!$request_id) {
        return false;
    }

    mlr_greenpay_maybe_install_schema();
    $requests_table = $wpdb->prefix . 'loan_requests';
    $tx_table = mlr_greenpay_transactions_table();
    $request = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$requests_table} WHERE id = %d",
        $request_id
    ));
    if (!$request) {
        return false;
    }

    $existing = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$tx_table} WHERE request_id = %d", $request_id));
    if (!$existing || (int) $existing->eligible !== 1) {
        return false;
    }
    if (in_array($existing->status, ['success','unknown','processing','awaiting_otp','queued','failed'], true)) {
        return false;
    }
    if (!in_array($existing->status, ['not_started','cancelled'], true)) {
        return false;
    }

    $settings = mlr_greenpay_get_settings();
    if (empty($settings['enabled']) || !mlr_greenpay_status_is_docs_approved($request->status)) {
        return false;
    }

    $snapshot = mlr_greenpay_build_financial_snapshot($request, $settings, (string) ($existing->from_pocket ?? ''));
    if (is_wp_error($snapshot)) {
        mlr_greenpay_add_event($request_id, 'greenpay_queue_failed', $snapshot->get_error_message());
        mlr_greenpay_notify_admin($request_id, 'پرونده #' . $request_id . ' وارد صف GreenPay نشد: ' . $snapshot->get_error_message());
        return false;
    }

    $now = current_time('mysql');
    $updated = $wpdb->update($tx_table, [
        'amount' => $snapshot['amount'],
        'from_pocket' => $snapshot['from_pocket'],
        'to_pocket' => $snapshot['to_pocket'],
        'environment' => $snapshot['environment'],
        'config_snapshot' => $snapshot['config_snapshot'],
        'status' => 'queued',
        'trigger_source' => sanitize_key($source),
        'last_error' => null,
        'last_http_code' => null,
        'last_response' => null,
        'provider_status' => null,
        'provider_reference' => null,
        'otp_requested_at' => null,
        'send_time' => null,
        'reconciled_at' => null,
        'reconciled_by' => 0,
        'reconciliation_note' => null,
        'updated_at' => $now,
    ], [
        'request_id' => $request_id,
        'eligible' => 1,
        'status' => $existing->status,
    ]);

    if ((int) $updated !== 1) {
        mlr_greenpay_add_event($request_id, 'greenpay_queue_failed', 'ثبت صف GreenPay ناموفق بود؛ هیچ درخواست مالی به GreenPay ارسال نشد.');
        mlr_greenpay_notify_admin($request_id, 'ثبت صف GreenPay برای پرونده #' . $request_id . ' در دیتابیس ناموفق بود؛ هیچ کسری ارسال نشد.');
        if (function_exists('mlr_log')) {
            mlr_log([
                'event' => 'greenpay_queue_state_transition_failed',
                'request_id' => $request_id,
                'expected_status' => $existing->status,
                'db_error' => $wpdb->last_error,
            ]);
        }
        return false;
    }

    mlr_greenpay_add_event($request_id, 'greenpay_queued', 'کسری GreenPay در صف قرار گرفت. مبلغ: ' . number_format($snapshot['amount']) . ' ریال؛ مرجع: ' . $existing->ref_number);
    mlr_greenpay_schedule_request($request_id);
    return true;
}

function mlr_greenpay_on_status_changed($request_id, $old_status, $new_status, $source = '') {
    global $wpdb;
    $old = function_exists('mlr_normalize_status') ? mlr_normalize_status($old_status) : trim((string) $old_status);
    $new = function_exists('mlr_normalize_status') ? mlr_normalize_status($new_status) : trim((string) $new_status);
    if ($old === $new) {
        return;
    }

    if ($old === 'تأیید مدارک' && in_array($new, ['تکمیل مدارک','نقص مدارک','رد شده','در حال بررسی','تأیید کارشناس'], true)) {
        $tx = mlr_greenpay_get_transaction($request_id);
        if ($tx && (int) $tx->eligible === 1 && $tx->status === 'queued') {
            $updated = $wpdb->update(mlr_greenpay_transactions_table(), [
                'status' => 'not_started',
                'last_error' => 'پرونده پیش از شروع کسری از وضعیت تأیید مدارک خارج شد؛ درخواست مالی ارسال نشد.',
                'updated_at' => current_time('mysql'),
            ], [
                'request_id' => absint($request_id),
                'eligible' => 1,
                'status' => 'queued',
            ]);
            if ((int) $updated === 1) {
                wp_clear_scheduled_hook('mlr_greenpay_process_debit', [absint($request_id)]);
                mlr_greenpay_add_event($request_id, 'greenpay_queue_cancelled', 'صف کسری GreenPay به دلیل بازگشت/رد پرونده پیش از شروع پردازش لغو شد.');
            }
        }
    }

    if ($new !== 'تأیید مدارک') {
        return;
    }

    $settings = mlr_greenpay_get_settings();
    if (empty($settings['enabled']) || empty($settings['auto_debit_on_docs_approved']) || !mlr_greenpay_is_eligible($request_id)) {
        return;
    }

    mlr_greenpay_queue_request($request_id, $source ?: 'status_transition');
}
add_action('mlr_loan_status_changed', 'mlr_greenpay_on_status_changed', 10, 4);

function mlr_greenpay_mark_transaction($request_id, array $updates, $expected_status = null, $expected_eligible = null) {
    global $wpdb;
    $updates['updated_at'] = current_time('mysql');
    $where = ['request_id' => absint($request_id)];
    if ($expected_status !== null) {
        $where['status'] = (string) $expected_status;
    }
    if ($expected_eligible !== null) {
        $where['eligible'] = $expected_eligible ? 1 : 0;
    }
    return $wpdb->update(mlr_greenpay_transactions_table(), $updates, $where);
}

function mlr_greenpay_process_debit_callback($request_id) {
    global $wpdb;
    $request_id = absint($request_id);
    if (!$request_id) {
        return;
    }

    mlr_greenpay_maybe_install_schema();
    $table = mlr_greenpay_transactions_table();
    $tx = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE request_id = %d", $request_id));
    if (!$tx || $tx->status !== 'queued' || (int) $tx->eligible !== 1) {
        return;
    }

    // Atomic claim: exactly one worker can leave queued and enter processing.
    $claimed = $wpdb->query($wpdb->prepare(
        "UPDATE {$table}
         SET status = 'processing', attempts = attempts + 1, updated_at = %s
         WHERE request_id = %d AND status = 'queued' AND eligible = 1",
        current_time('mysql'),
        $request_id
    ));
    if ((int) $claimed !== 1) {
        return;
    }

    $tx = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE request_id = %d", $request_id));
    $current_settings = mlr_greenpay_get_settings();
    if (empty($current_settings['enabled'])) {
        mlr_greenpay_mark_transaction($request_id, [
            'status' => 'failed',
            'last_error' => 'GreenPay در تنظیمات غیرفعال شده است؛ هیچ درخواست مالی ارسال نشد.',
        ], 'processing', 1);
        return;
    }

    $settings = mlr_greenpay_settings_for_transaction($tx);
    if (is_wp_error($settings)) {
        mlr_greenpay_mark_transaction($request_id, [
            'status' => 'failed',
            'last_error' => $settings->get_error_message(),
        ], 'processing', 1);
        mlr_greenpay_add_event($request_id, 'greenpay_failed', $settings->get_error_message());
        mlr_greenpay_notify_admin($request_id, 'پردازش GreenPay پرونده #' . $request_id . ' متوقف شد چون تنظیمات پس از ایجاد Snapshot تغییر کرده است.');
        return;
    }

    if ((int) $tx->amount <= 0) {
        mlr_greenpay_mark_transaction($request_id, ['status' => 'failed', 'last_error' => 'مبلغ تسهیلات پرونده معتبر نیست.'], 'processing', 1);
        mlr_greenpay_add_event($request_id, 'greenpay_failed', 'کسری GreenPay انجام نشد: مبلغ تسهیلات معتبر نیست.');
        return;
    }

    $pocket_check = mlr_greenpay_validate_pockets($tx->from_pocket, $tx->to_pocket, (string) $tx->environment);
    if (is_wp_error($pocket_check)) {
        mlr_greenpay_mark_transaction($request_id, ['status' => 'failed', 'last_error' => $pocket_check->get_error_message()], 'processing', 1);
        mlr_greenpay_add_event($request_id, 'greenpay_failed', 'کسری GreenPay انجام نشد: ' . $pocket_check->get_error_message());
        mlr_greenpay_notify_admin($request_id, 'تنظیم Pocketهای GreenPay برای پرونده #' . $request_id . ' معتبر نیست.');
        return;
    }

    $api = new MLR_GreenPay_API($settings);
    $valid = $api->validate_configuration();
    if (is_wp_error($valid)) {
        mlr_greenpay_mark_transaction($request_id, ['status' => 'failed', 'last_error' => $valid->get_error_message()], 'processing', 1);
        mlr_greenpay_add_event($request_id, 'greenpay_failed', 'کسری GreenPay انجام نشد: ' . $valid->get_error_message());
        mlr_greenpay_notify_admin($request_id, 'کسری GreenPay پرونده #' . $request_id . ' انجام نشد: تنظیمات API ناقص است.');
        return;
    }

    $token = $api->get_token();
    if (is_wp_error($token)) {
        mlr_greenpay_mark_transaction($request_id, [
            'status' => 'failed',
            'last_error' => $token->get_error_message(),
            'last_response' => mlr_greenpay_encode_response($token),
        ], 'processing', 1);
        mlr_greenpay_add_event($request_id, 'greenpay_failed', 'دریافت توکن GreenPay ناموفق بود: ' . $token->get_error_message());
        mlr_greenpay_notify_admin($request_id, 'دریافت توکن GreenPay برای پرونده #' . $request_id . ' ناموفق بود.');
        return;
    }

    $is_test = (($settings['environment'] ?? 'test') !== 'live');
    $using_test_profile_1023 = $is_test && !empty($settings['test_profile_1023']);
    $skip_otp_request = $using_test_profile_1023 || ($is_test && !empty($settings['test_skip_otp_request']));
    $send_time = '';

    if (!$skip_otp_request) {
        $otp_response = $api->send_otp($token, $tx->from_pocket, (int) $tx->amount);
        if (is_wp_error($otp_response) || !mlr_greenpay_response_is_success($otp_response)) {
            // sendPurchaseOTP itself cannot debit the wallet, therefore a failure here is safe to retry.
            $error = mlr_greenpay_extract_error_message($otp_response, 'درخواست OTP GreenPay ناموفق بود.');
            mlr_greenpay_mark_transaction($request_id, [
                'status' => 'failed',
                'last_http_code' => is_array($otp_response) ? (int) ($otp_response['http_code'] ?? 0) : 0,
                'last_error' => $error,
                'last_response' => mlr_greenpay_encode_response($otp_response),
            ], 'processing', 1);
            mlr_greenpay_add_event($request_id, 'greenpay_failed', 'درخواست OTP GreenPay ناموفق بود: ' . $error);
            mlr_greenpay_notify_admin($request_id, 'درخواست OTP GreenPay پرونده #' . $request_id . ' ناموفق بود.');
            return;
        }

        $send_time = mlr_greenpay_extract_send_time(
            $otp_response,
            absint($settings['otp_time_buffer'] ?? 0),
            intval($settings['provider_time_shift_seconds'] ?? 30)
        );
        $otp_updates = [
            'otp_requested_at' => current_time('mysql'),
            'send_time' => $send_time,
            'provider_status' => sanitize_text_field((string) ($otp_response['Status'] ?? 'success')),
            'last_http_code' => (int) ($otp_response['http_code'] ?? 200),
            'last_error' => null,
            'last_response' => mlr_greenpay_encode_response($otp_response),
        ];

        if (!$is_test) {
            $otp_updates['status'] = 'awaiting_otp';
            $moved = mlr_greenpay_mark_transaction($request_id, $otp_updates, 'processing', 1);
            if ((int) $moved !== 1) {
                return;
            }
            mlr_greenpay_add_event($request_id, 'greenpay_otp_requested', 'OTP گرین‌پی درخواست شد. مرجع: ' . $tx->ref_number);
            mlr_greenpay_notify_admin($request_id, 'برای تکمیل کسری GreenPay پرونده #' . $request_id . ' رمز OTP را در صفحه مدیریت GreenPay وارد کنید.');
            return;
        }

        // Sandbox remains processing and immediately proceeds to transfer with the fixed test OTP.
        mlr_greenpay_mark_transaction($request_id, $otp_updates, 'processing', 1);
    } else {
        $send_time = mlr_greenpay_extract_send_time([], 0);
        mlr_greenpay_mark_transaction($request_id, [
            'send_time' => $send_time,
            'otp_requested_at' => null,
        ], 'processing', 1);
    }

    $otp = $using_test_profile_1023
        ? '123456'
        : preg_replace('/\D+/', '', (string) ($settings['test_otp'] ?? '123456'));
    if ($otp === '') {
        $otp = '123456';
    }
    if (strlen($otp) !== 6) {
        mlr_greenpay_mark_transaction($request_id, ['status' => 'failed', 'last_error' => 'OTP تست باید دقیقاً ۶ رقم باشد.'], 'processing', 1);
        mlr_greenpay_add_event($request_id, 'greenpay_failed', 'کسری GreenPay انجام نشد: OTP تست نامعتبر است.');
        return;
    }

    // SendTime belongs to transferByPocket itself; always refresh it directly
    // before the transfer, even in sandbox/immediate flows.
    $send_time = mlr_greenpay_fresh_transfer_send_time($settings);
    mlr_greenpay_mark_transaction($request_id, ['send_time' => $send_time], 'processing', 1);

    $result = $api->confirm_debit(
        $token,
        $tx->from_pocket,
        $tx->to_pocket,
        $otp,
        (int) $tx->amount,
        $tx->ref_number,
        $send_time
    );
    mlr_greenpay_finalize_transfer_result($request_id, $result);
}
add_action('mlr_greenpay_process_debit', 'mlr_greenpay_process_debit_callback', 10, 1);

function mlr_greenpay_finalize_transfer_result($request_id, $response) {
    global $wpdb;
    $request_id = absint($request_id);
    $table = mlr_greenpay_transactions_table();
    $tx = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE request_id = %d", $request_id));
    if (!$tx || $tx->status !== 'processing' || (int) $tx->eligible !== 1) {
        return false;
    }

    $http = is_array($response) ? (int) ($response['http_code'] ?? 0) : 0;
    $provider_status = is_array($response) ? sanitize_text_field((string) ($response['Status'] ?? $response['status'] ?? '')) : '';
    $provider_reference = is_array($response) ? mlr_greenpay_extract_provider_reference($response) : '';

    if (mlr_greenpay_response_is_success($response)) {
        $updated = mlr_greenpay_mark_transaction($request_id, [
            'status' => 'success',
            'provider_status' => $provider_status !== '' ? $provider_status : 'success',
            'provider_reference' => $provider_reference,
            'last_http_code' => $http,
            'last_error' => null,
            'last_response' => mlr_greenpay_encode_response($response),
            'debited_at' => current_time('mysql'),
        ], 'processing', 1);
        if ((int) $updated !== 1) {
            return false;
        }
        mlr_greenpay_add_event($request_id, 'greenpay_success', 'کسری GreenPay با موفقیت انجام شد. مبلغ: ' . number_format((int) $tx->amount) . ' ریال؛ مرجع: ' . $tx->ref_number);
        return true;
    }

    $status = mlr_greenpay_classify_failure($response);
    $error = mlr_greenpay_extract_error_message($response, $status === 'unknown' ? 'نتیجه تراکنش GreenPay نامشخص است و نباید خودکار تکرار شود.' : 'کسری GreenPay ناموفق بود.');
    $updated = mlr_greenpay_mark_transaction($request_id, [
        'status' => $status,
        'provider_status' => $provider_status,
        'provider_reference' => $provider_reference,
        'last_http_code' => $http,
        'last_error' => $error,
        'last_response' => mlr_greenpay_encode_response($response),
    ], 'processing', 1);
    if ((int) $updated !== 1) {
        return false;
    }

    mlr_greenpay_add_event($request_id, 'greenpay_' . $status, 'کسری GreenPay ' . ($status === 'unknown' ? 'با نتیجه نامشخص' : 'ناموفق') . ' بود: ' . $error . '؛ مرجع: ' . $tx->ref_number);
    mlr_greenpay_notify_admin(
        $request_id,
        $status === 'unknown'
            ? 'وضعیت کسری GreenPay پرونده #' . $request_id . ' نامشخص است؛ پیش از هر تلاش مجدد استعلام انجام دهید.'
            : 'کسری GreenPay پرونده #' . $request_id . ' ناموفق بود.'
    );
    return false;
}

function mlr_greenpay_confirm_with_admin_otp($request_id, $otp) {
    global $wpdb;
    $request_id = absint($request_id);
    $otp = preg_replace('/\D+/', '', mlr_greenpay_to_english_digits($otp));
    if (strlen($otp) !== 6) {
        return new WP_Error('greenpay_invalid_otp', 'OTP باید دقیقاً ۶ رقم باشد.');
    }

    $table = mlr_greenpay_transactions_table();
    $claimed = $wpdb->query($wpdb->prepare(
        "UPDATE {$table}
         SET status = 'processing', attempts = attempts + 1, updated_at = %s
         WHERE request_id = %d AND status = 'awaiting_otp' AND eligible = 1",
        current_time('mysql'),
        $request_id
    ));
    if ((int) $claimed !== 1) {
        return new WP_Error('greenpay_concurrent_processing', 'این تراکنش دیگر در وضعیت انتظار OTP نیست یا هم‌اکنون توسط عملیات دیگری پردازش می‌شود.');
    }

    $tx = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE request_id = %d", $request_id));
    $current_settings = mlr_greenpay_get_settings();
    if (empty($current_settings['enabled'])) {
        mlr_greenpay_mark_transaction($request_id, ['status' => 'failed', 'last_error' => 'GreenPay در تنظیمات غیرفعال است؛ انتقال ارسال نشد.'], 'processing', 1);
        return new WP_Error('greenpay_disabled', 'GreenPay در تنظیمات غیرفعال است.');
    }

    $settings = mlr_greenpay_settings_for_transaction($tx);
    if (is_wp_error($settings)) {
        mlr_greenpay_mark_transaction($request_id, ['status' => 'failed', 'last_error' => $settings->get_error_message()], 'processing', 1);
        return $settings;
    }

    $pocket_check = mlr_greenpay_validate_pockets($tx->from_pocket, $tx->to_pocket, (string) $tx->environment);
    if (is_wp_error($pocket_check)) {
        mlr_greenpay_mark_transaction($request_id, ['status' => 'failed', 'last_error' => $pocket_check->get_error_message()], 'processing', 1);
        return $pocket_check;
    }

    $api = new MLR_GreenPay_API($settings);
    $token = $api->get_token();
    if (is_wp_error($token)) {
        mlr_greenpay_mark_transaction($request_id, [
            'status' => 'failed',
            'last_error' => $token->get_error_message(),
            'last_response' => mlr_greenpay_encode_response($token),
        ], 'processing', 1);
        return $token;
    }

    // IMPORTANT: GreenPay's transfer window is based on SendTime of the transfer
    // request itself, not the moment the OTP SMS was requested. Generate a fresh
    // corrected timestamp immediately before transferByPocket so the time spent by
    // the user entering OTP can never consume the provider's transaction window.
    $send_time = mlr_greenpay_fresh_transfer_send_time($settings);
    $wpdb->update($table, [
        'send_time' => $send_time,
        'updated_at' => current_time('mysql'),
    ], [
        'request_id' => $request_id,
        'status' => 'processing',
        'eligible' => 1,
    ]);

    $result = $api->confirm_debit(
        $token,
        $tx->from_pocket,
        $tx->to_pocket,
        $otp,
        (int) $tx->amount,
        $tx->ref_number,
        $send_time
    );

    // GreenPay support asked for a fresh request with a clock shift when the
    // provider explicitly rejects the time window. Such a 4xx response is a
    // definite non-debit, so exactly ONE automatic retry is safe. Never retry
    // transport errors, 5xx responses or ambiguous results.
    if (mlr_greenpay_is_time_window_rejection($result)) {
        $retry_send_time = mlr_greenpay_fresh_transfer_send_time($settings, 1);
        $wpdb->update($table, [
            'send_time' => $retry_send_time,
            'updated_at' => current_time('mysql'),
        ], [
            'request_id' => $request_id,
            'status' => 'processing',
            'eligible' => 1,
        ]);
        mlr_greenpay_add_event($request_id, 'greenpay_time_sync_retry', 'GreenPay درخواست انتقال را به علت پنجره زمانی رد کرد؛ یک تلاش قطعی و امن با SendTime تازه و تصحیح ساعت انجام شد.');
        $result = $api->confirm_debit(
            $token,
            $tx->from_pocket,
            $tx->to_pocket,
            $otp,
            (int) $tx->amount,
            $tx->ref_number,
            $retry_send_time
        );

        // If GreenPay still rejects only the time window, do not convert the
        // transaction to a generic failed state. Keep it awaiting OTP so the
        // admin can request a fresh code in the same modal without a page reload.
        if (mlr_greenpay_is_time_window_rejection($result)) {
            $error = mlr_greenpay_extract_error_message($result, 'مهلت زمانی GreenPay دوباره رد شد.');
            mlr_greenpay_mark_transaction($request_id, [
                'status' => 'awaiting_otp',
                'last_http_code' => (int) ($result['http_code'] ?? 0),
                'last_error' => $error,
                'last_response' => mlr_greenpay_encode_response($result),
            ], 'processing', 1);
            return new WP_Error(
                'greenpay_time_window_retry_needed',
                'GreenPay دوباره پنجره زمانی را رد کرد. صفحه را رفرش نکنید؛ همین پنجره باز بماند و «ارسال کد جدید» را بزنید. تصحیح ساعت فعلی: ' . (int) (mlr_greenpay_get_settings()['provider_time_shift_seconds'] ?? 30) . ' ثانیه.',
                $result
            );
        }
    }

    $success = mlr_greenpay_finalize_transfer_result($request_id, $result);
    if (!$success) {
        $latest = mlr_greenpay_get_transaction($request_id);
        $message = ($latest && $latest->status === 'unknown')
            ? 'نتیجه انتقال GreenPay نامشخص است؛ برای جلوگیری از دوبارکسر، قبل از هر Retry از GreenPay استعلام کنید.'
            : mlr_greenpay_extract_error_message($result, 'کسری GreenPay ناموفق بود.');
        return new WP_Error('greenpay_debit_failed', $message, $result);
    }
    return true;
}

function mlr_greenpay_requeue_transaction($request_id, array $allowed_statuses, $refresh_financial_snapshot = true) {
    global $wpdb;
    $request_id = absint($request_id);
    if (!$request_id) {
        return false;
    }

    $table = mlr_greenpay_transactions_table();
    $tx = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE request_id = %d", $request_id));
    if (!$tx || (int) $tx->eligible !== 1 || !in_array($tx->status, $allowed_statuses, true)) {
        return false;
    }

    $updates = [
        'status' => 'queued',
        'last_error' => null,
        'last_http_code' => null,
        'last_response' => null,
        'provider_status' => null,
        'provider_reference' => null,
        'otp_requested_at' => null,
        'send_time' => null,
        'reconciled_at' => null,
        'reconciled_by' => 0,
        'reconciliation_note' => null,
        'updated_at' => current_time('mysql'),
    ];

    if ($refresh_financial_snapshot) {
        $requests_table = $wpdb->prefix . 'loan_requests';
        $request = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$requests_table} WHERE id = %d", $request_id));
        if (!$request) {
            return false;
        }
        $snapshot = mlr_greenpay_build_financial_snapshot($request, mlr_greenpay_get_settings(), (string) ($tx->from_pocket ?? ''));
        if (is_wp_error($snapshot)) {
            mlr_greenpay_notify_admin($request_id, 'تلاش مجدد GreenPay برای پرونده #' . $request_id . ' صف‌بندی نشد: ' . $snapshot->get_error_message());
            return false;
        }
        $updates['amount'] = $snapshot['amount'];
        $updates['from_pocket'] = $snapshot['from_pocket'];
        $updates['to_pocket'] = $snapshot['to_pocket'];
        $updates['environment'] = $snapshot['environment'];
        $updates['config_snapshot'] = $snapshot['config_snapshot'];
    }

    // Compare-and-swap blocks confirm-OTP/resend/retry races.
    $updated = $wpdb->update($table, $updates, [
        'request_id' => $request_id,
        'eligible' => 1,
        'status' => $tx->status,
    ]);
    if ((int) $updated !== 1) {
        return false;
    }

    mlr_greenpay_add_event($request_id, 'greenpay_queued', 'کسری GreenPay برای پردازش مجدد در صف قرار گرفت.');
    mlr_greenpay_schedule_request($request_id);
    return true;
}

function mlr_greenpay_retry_failed_request($request_id) {
    // A definite failed transfer is safe for an explicit retry; refresh amount/pocket/config snapshot.
    return mlr_greenpay_requeue_transaction($request_id, ['failed'], true);
}

function mlr_greenpay_resend_otp($request_id) {
    // No debit has occurred yet. Preserve the original financial/config snapshot and request a new OTP.
    return mlr_greenpay_requeue_transaction($request_id, ['awaiting_otp'], false);
}

function mlr_greenpay_reconcile_unknown($request_id, $outcome, $note, $provider_reference = '') {
    global $wpdb;
    $request_id = absint($request_id);
    $outcome = sanitize_key($outcome);
    $note = sanitize_textarea_field((string) $note);
    $provider_reference = sanitize_text_field((string) $provider_reference);

    if (!in_array($outcome, ['success','failed'], true)) {
        return new WP_Error('greenpay_invalid_reconciliation', 'نتیجه استعلام معتبر نیست.');
    }
    $note_length = function_exists('mb_strlen') ? mb_strlen($note) : strlen($note);
    if ($note_length < 5) {
        return new WP_Error('greenpay_reconciliation_note_required', 'برای ثبت نتیجه استعلام، توضیح کوتاه الزامی است.');
    }

    $table = mlr_greenpay_transactions_table();
    $tx = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE request_id = %d", $request_id));
    if (!$tx || (int) $tx->eligible !== 1 || $tx->status !== 'unknown') {
        return new WP_Error('greenpay_not_unknown', 'فقط تراکنش مشمول با وضعیت نامشخص قابل تعیین تکلیف است.');
    }

    $updates = [
        'status' => $outcome,
        'provider_status' => $outcome === 'success' ? 'reconciled_success' : 'reconciled_no_debit',
        'reconciled_at' => current_time('mysql'),
        'reconciled_by' => get_current_user_id(),
        'reconciliation_note' => $note,
        'last_error' => $outcome === 'success'
            ? null
            : 'طبق استعلام دستی GreenPay، کسر انجام نشده است. ' . $note,
    ];
    if ($provider_reference !== '') {
        $updates['provider_reference'] = $provider_reference;
    }
    if ($outcome === 'success') {
        $updates['debited_at'] = current_time('mysql');
    }

    $updated = mlr_greenpay_mark_transaction($request_id, $updates, 'unknown', 1);
    if ((int) $updated !== 1) {
        return new WP_Error('greenpay_concurrent_reconciliation', 'وضعیت تراکنش هم‌زمان تغییر کرده است؛ نتیجه استعلام ثبت نشد.');
    }

    mlr_greenpay_add_event(
        $request_id,
        'greenpay_reconciled_' . $outcome,
        ($outcome === 'success' ? 'استعلام دستی: کسر GreenPay انجام شده است. ' : 'استعلام دستی: کسر GreenPay انجام نشده است. ') . $note
    );
    return true;
}

function mlr_greenpay_recover_jobs() {
    if (get_transient('mlr_greenpay_recovery_lock')) {
        return;
    }
    set_transient('mlr_greenpay_recovery_lock', 1, MINUTE_IN_SECONDS);

    global $wpdb;
    mlr_greenpay_maybe_install_schema();
    $table = mlr_greenpay_transactions_table();

    $stale_before = date('Y-m-d H:i:s', current_time('timestamp') - (15 * MINUTE_IN_SECONDS));
    $stale_processing = $wpdb->get_col($wpdb->prepare(
        "SELECT request_id FROM {$table} WHERE status = 'processing' AND updated_at < %s LIMIT 20",
        $stale_before
    ));
    foreach ((array) $stale_processing as $request_id) {
        $updated = mlr_greenpay_mark_transaction($request_id, [
            'status' => 'unknown',
            'last_error' => 'پردازش قبلی بدون نتیجه قطعی متوقف شده است؛ برای جلوگیری از دوبارکسر، تلاش خودکار متوقف شد.',
        ], 'processing', 1);
        if ((int) $updated === 1) {
            mlr_greenpay_notify_admin($request_id, 'وضعیت تراکنش GreenPay پرونده #' . absint($request_id) . ' نامشخص شده است؛ استعلام لازم است.');
        }
    }

    $queued = $wpdb->get_col("SELECT request_id FROM {$table} WHERE status = 'queued' AND eligible = 1 ORDER BY updated_at ASC LIMIT 20");
    foreach ((array) $queued as $request_id) {
        mlr_greenpay_schedule_request($request_id);
    }
}
add_action('init', 'mlr_greenpay_recover_jobs', 50);

function mlr_greenpay_render_api_settings_section() {
    $settings = mlr_greenpay_get_settings();
    $display_settings = mlr_greenpay_apply_test_profile_1023($settings);
    $stored_settings = get_option('mlr_greenpay_settings', []);
    $stored_settings = is_array($stored_settings) ? $stored_settings : [];
    $password_saved = !empty($stored_settings['password']);
    $api_key_saved = !empty($stored_settings['api_key']);
    $required_live = [
        'username' => 'Username',
        'password' => 'Password',
        'api_key' => 'API Key',
        'terminal_number' => 'Terminal Number',
        'merchant_id' => 'Merchant ID',
        'auth_base_url' => 'Auth Base URL عملیاتی',
        'purchase_base_url' => 'Purchase Base URL عملیاتی',
    ];
    $live_missing = [];
    foreach ($required_live as $key => $label) {
        $value = $key === 'password'
            ? ($password_saved ? 'saved' : '')
            : ($key === 'api_key' ? ($api_key_saved ? 'saved' : '') : trim((string) ($settings[$key] ?? '')));
        if ($value === '') {
            $live_missing[] = $label;
        }
    }
    ?>
    <div class="mlr-settings-card" style="margin-top:16px;">
        <h2>اتصال GreenPay / BKI</h2>
        <p class="description">این اتصال مستقل از منطق اصلی بابانوئل است. فقط برای پرونده‌هایی که مدیر صراحتاً «مشمول GreenPay» کرده است، با ورود پرونده به «تأیید مدارک» و در صورت فعال بودن، کسری بر اساس <strong>فیلد مبلغ تسهیلات محاسبه‌شده (facility_amount؛ با fallback به loan_amount برای پرونده‌های قدیمی) و با واحد ریال</strong> در صف پردازش قرار می‌گیرد. خطای GreenPay وضعیت پرونده را تغییر نمی‌دهد.</p>
        <div class="notice notice-info inline" style="margin:12px 0;"><p><strong>پروفایل تست خرید آنلاین 1023:</strong> مطابق اطلاعات تحویلی، در Sandbox ارسال OTP انجام نمی‌شود؛ OTP همیشه <code>123456</code>، Password مؤثر Wallet برابر <code>1</code>، FromPocketNumber برابر <code>1262010000002551</code> و ToPocketNumber خالی است. اطلاعات «پیامک GreenPass» سرویس جداگانه‌ای است و در Wallet API استفاده نمی‌شود.</p></div>
        <?php if ($live_missing): ?>
            <div class="notice notice-warning inline" style="margin:12px 0;"><p><strong>Production هنوز کامل نیست.</strong> Username و Password به‌تنهایی برای انتقال کافی نیست. موارد باقی‌مانده/نیازمند کنترل: <?php echo esc_html(implode('، ', $live_missing)); ?>.</p></div>
        <?php else: ?>
            <div class="notice notice-success inline" style="margin:12px 0;"><p>فیلدهای پایه Production تکمیل به نظر می‌رسند. قبل از فعال‌سازی، «تست اتصال GreenPay» را از صفحه مدیریت اجرا کنید.</p></div>
        <?php endif; ?>
        <table class="form-table">
            <tr>
                <th>فعال‌سازی</th>
                <td>
                    <label><input type="checkbox" name="mlr_greenpay_settings[enabled]" value="1" <?php checked(!empty($settings['enabled'])); ?>> اتصال GreenPay فعال باشد</label><br>
                    <label><input type="checkbox" name="mlr_greenpay_settings[auto_debit_on_docs_approved]" value="1" <?php checked(!empty($settings['auto_debit_on_docs_approved'])); ?>> برای پرونده‌های مشمول، هنگام ورود به «تأیید مدارک» کسری در صف قرار گیرد</label>
                    <p class="description">برای ایمنی، نسخه جدید پس از نصب خودکار GreenPay را فعال نمی‌کند؛ پس از کنترل تنظیمات این گزینه را روشن کنید.</p>
                </td>
            </tr>
            <tr>
                <th>پروفایل تست</th>
                <td>
                    <label><input type="checkbox" name="mlr_greenpay_settings[test_profile_1023]" value="1" <?php checked(!empty($settings['test_profile_1023'])); ?>> استفاده از پروفایل تست «خرید آنلاین 1023» تحویلی GreenPay</label>
                    <p class="description">در محیط Test این گزینه مقادیر تستی قطعی را روی درخواست اعمال می‌کند و از ارسال اشتباه OTP یا ToPocket جلوگیری می‌کند. API Key همچنان باید از فایل «پذیرنده تست» در فیلد امن زیر وارد شود.</p>
                </td>
            </tr>
            <tr>
                <th><label for="mlr_gp_environment">محیط</label></th>
                <td>
                    <select id="mlr_gp_environment" name="mlr_greenpay_settings[environment]">
                        <option value="test" <?php selected($settings['environment'], 'test'); ?>>تست / Sandbox</option>
                        <option value="live" <?php selected($settings['environment'], 'live'); ?>>عملیاتی / Production</option>
                    </select>
                    <p class="description">در محیط عملیاتی فقط HTTPS پذیرفته می‌شود. Endpointها مطابق پلاگین مرجع GreenPay استفاده می‌شوند.</p>
                </td>
            </tr>
            <tr><th>Username</th><td><input type="text" name="mlr_greenpay_settings[username]" value="<?php echo esc_attr($display_settings['username']); ?>" class="regular-text ltr" autocomplete="off"></td></tr>
            <tr><th>Password</th><td><input type="password" name="mlr_greenpay_settings[password]" value="" class="regular-text ltr" autocomplete="new-password" placeholder="<?php echo esc_attr($password_saved ? '•••••••• (ذخیره شده؛ برای Production)' : 'Password عملیاتی'); ?>"><p class="description">در پروفایل تست 1023 این فیلد نادیده گرفته می‌شود و Password مؤثر Wallet همیشه 1 است. مقدار خالی، رمز ذخیره‌شده Production را پاک نمی‌کند.</p></td></tr>
            <tr><th>API Key <span style="color:#b32d2e;">*</span></th><td><input type="password" name="mlr_greenpay_settings[api_key]" value="" class="regular-text ltr" autocomplete="new-password" placeholder="<?php echo esc_attr($api_key_saved ? '•••••••• (ذخیره شده؛ برای تغییر مقدار جدید وارد کنید)' : 'API Key'); ?>"><p class="description">مقدار خالی، API Key ذخیره‌شده را پاک نمی‌کند. برای Sandbox، API Key را از فایل «پذیرنده تست.xlsx» وارد کنید؛ کلید در سورس افزونه Hard-code نشده است.</p></td></tr>
            <tr><th>Terminal Number <span style="color:#b32d2e;">*</span></th><td><input type="text" name="mlr_greenpay_settings[terminal_number]" value="<?php echo esc_attr($display_settings['terminal_number']); ?>" class="regular-text ltr"></td></tr>
            <tr><th>Merchant ID <span style="color:#b32d2e;">*</span></th><td><input type="text" name="mlr_greenpay_settings[merchant_id]" value="<?php echo esc_attr($display_settings['merchant_id']); ?>" class="regular-text ltr" inputmode="numeric"></td></tr>
            <tr><th>FromPocketNumber</th><td><input type="text" name="mlr_greenpay_settings[from_pocket_number]" value="<?php echo esc_attr($display_settings['from_pocket_number']); ?>" class="regular-text ltr" maxlength="16" inputmode="numeric"><p class="description">فقط برای Sandbox/حالت‌های سفارشی است. در Production شماره Pocket مبدأ هنگام مشمول‌کردن هر پرونده جداگانه ثبت می‌شود؛ این فیلد را خالی بگذارید.</p></td></tr>
            <tr><th>ToPocketNumber</th><td><input type="text" name="mlr_greenpay_settings[to_pocket_number]" value="<?php echo esc_attr($display_settings['to_pocket_number']); ?>" class="regular-text ltr" maxlength="16" inputmode="numeric"><p class="description">مطابق پلاگین مرجع GreenPay می‌تواند خالی باشد. برای تنظیم فعلی Production خالی بگذارید.</p></td></tr>
            <tr>
                <th>OTP محیط تست</th>
                <td>
                    <input type="text" name="mlr_greenpay_settings[test_otp]" value="<?php echo esc_attr($display_settings['test_otp']); ?>" class="small-text ltr" maxlength="6" inputmode="numeric">
                    <label style="margin-right:14px;"><input type="checkbox" name="mlr_greenpay_settings[test_skip_otp_request]" value="1" <?php checked(!empty($display_settings['test_skip_otp_request'])); ?>> در تست، API ارسال OTP فراخوانی نشود</label>
                    <p class="description">در پروفایل 1023 مقدار مؤثر همیشه 123456 و ارسال OTP همیشه Bypass است؛ تغییر ظاهری این دو فیلد روی آن پروفایل اثری ندارد. OTP در دیتابیس تراکنش ذخیره نمی‌شود.</p>
                </td>
            </tr>
            <tr><th>Auth Base URL</th><td><input type="url" name="mlr_greenpay_settings[auth_base_url]" value="<?php echo esc_attr($display_settings['auth_base_url']); ?>" class="large-text ltr"><p class="description">مطابق پلاگین مرجع GreenPay، برای دریافت Token مسیر <code>/walletbackend/api/sandbox</code> استفاده می‌شود.</p></td></tr>
            <tr><th>Purchase Base URL</th><td><input type="url" name="mlr_greenpay_settings[purchase_base_url]" value="<?php echo esc_attr($display_settings['purchase_base_url']); ?>" class="large-text ltr"><p class="description">مطابق پلاگین مرجع GreenPay، عملیات خرید از مسیر <code>/walletbackend/api/purchase</code> انجام می‌شود.</p></td></tr>
            <tr><th>Timeout</th><td><input type="number" min="5" max="60" name="mlr_greenpay_settings[request_timeout]" value="<?php echo esc_attr($settings['request_timeout']); ?>" class="small-text"> ثانیه</td></tr>
            <tr><th>بافر زمان OTP</th><td><input type="number" min="0" max="60" name="mlr_greenpay_settings[otp_time_buffer]" value="<?php echo esc_attr($settings['otp_time_buffer']); ?>" class="small-text"> ثانیه <p class="description">به‌طور معمول روی ۰ بماند. این مقدار فقط برای ثبت زمان مرحله درخواست OTP است؛ SendTime انتقال نهایی در لحظه تأیید کد از نو ساخته می‌شود.</p></td></tr>
            <tr><th>تصحیح ساعت GreenPay</th><td><input type="number" min="-120" max="120" name="mlr_greenpay_settings[provider_time_shift_seconds]" value="<?php echo esc_attr($settings['provider_time_shift_seconds']); ?>" class="small-text ltr"> ثانیه <p class="description">مستقیماً روی SendTime تازه‌ای که در لحظه transferByPocket ساخته می‌شود اعمال می‌شود. طبق اعلام پشتیبانی GreenPay، مقدار پیش‌فرض +۳۰ ثانیه است. تغییر این مقدار روی تراکنش‌های موجود هم فوراً اثر می‌گذارد و نیاز به بازسازی Snapshot ندارد.</p></td></tr>
        </table>
        <p><a class="button" href="<?php echo esc_url(admin_url('admin.php?page=mlr-greenpay-transactions')); ?>">مدیریت GreenPay</a></p>
    </div>
    <?php
}
add_action('mlr_api_settings_page_extra_sections', 'mlr_greenpay_render_api_settings_section');
