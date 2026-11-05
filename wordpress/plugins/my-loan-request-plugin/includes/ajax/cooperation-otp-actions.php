<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('mlr_cooperation_signing_status_allowed')) {
    function mlr_cooperation_signing_status_allowed($status) {
        return in_array((string) $status, ['تایید مدارک', 'عقد قرارداد'], true);
    }
}

if (!function_exists('mlr_cooperation_otp_remaining_seconds')) {
    function mlr_cooperation_otp_remaining_seconds($expires_at) {
        if (empty($expires_at)) {
            return 0;
        }
        $timestamp = strtotime((string) $expires_at);
        if (!$timestamp) {
            return 0;
        }
        return max(0, $timestamp - time());
    }
}

if (!function_exists('mlr_cooperation_build_otp_sms_variables')) {
    /**
     * Build SMS pattern variables from the existing admin configuration.
     * For backward compatibility, installations that did not configure variable
     * fields continue to send the OTP as the single pattern variable.
     */
    function mlr_cooperation_build_otp_sms_variables(array $otp_settings, $request, $otp_code) {
        $configured = [];
        $contains_otp_placeholder = false;

        for ($i = 0; $i < 3; $i++) {
            $key = 'var_' . $i;
            $template = isset($otp_settings[$key]) ? trim((string) $otp_settings[$key]) : '';
            if ($template === '') {
                continue;
            }
            if (strpos($template, '[[otp_code]]') !== false) {
                $contains_otp_placeholder = true;
            }
            $configured[] = function_exists('mlr_replace_cooperation_placeholders')
                ? mlr_replace_cooperation_placeholders($template, $request, $otp_code)
                : str_replace('[[otp_code]]', $otp_code, $template);
        }

        if (!empty($configured) && $contains_otp_placeholder) {
            return $configured;
        }

        if (!empty($configured) && !$contains_otp_placeholder && function_exists('mlr_log')) {
            mlr_log('Cooperation OTP variables do not contain [[otp_code]]; falling back to one OTP variable.', [
                'request_id' => isset($request->id) ? (int) $request->id : 0,
            ]);
        }

        return [(string) $otp_code];
    }
}

// AJAX action for sending OTP.
add_action('wp_ajax_mlr_send_cooperation_otp', 'mlr_send_cooperation_otp_callback');
add_action('wp_ajax_nopriv_mlr_send_cooperation_otp', 'mlr_send_cooperation_otp_callback');

function mlr_send_cooperation_otp_callback() {
    check_ajax_referer('mlr_send_cooperation_otp_nonce', 'nonce');

    $token = isset($_POST['request_token']) ? sanitize_text_field(wp_unslash($_POST['request_token'])) : '';
    if ($token === '') {
        wp_send_json_error(['message' => 'شناسه قرارداد نامعتبر است.', 'otp_active' => false]);
        return;
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'cooperation_requests';
    $request = $wpdb->get_row($wpdb->prepare(
        "SELECT id, request_type, status, data, contract_token, otp_expires_at, contract_signed_status
         FROM `{$table_name}`
         WHERE contract_token = %s",
        $token
    ));

    if (!$request) {
        wp_send_json_error(['message' => 'درخواست همکاری یافت نشد.', 'otp_active' => false]);
        return;
    }
    if ($request->contract_signed_status === 'signed') {
        wp_send_json_error(['message' => 'این قرارداد قبلاً امضا شده است.', 'otp_active' => false]);
        return;
    }
    if (!mlr_cooperation_signing_status_allowed($request->status)) {
        wp_send_json_error(['message' => 'این قرارداد در وضعیت فعلی قابل امضا نیست.', 'otp_active' => false]);
        return;
    }

    $remaining_time = mlr_cooperation_otp_remaining_seconds($request->otp_expires_at);
    if ($remaining_time > 0) {
        wp_send_json_error([
            'message' => "کد تأیید قبلی هنوز معتبر است. همان کد را وارد کنید؛ ارسال مجدد تا {$remaining_time} ثانیه دیگر امکان‌پذیر است.",
            'otp_active' => true,
            'cooldown' => $remaining_time,
        ]);
        return;
    }

    // Count only actual new-send attempts; clicking while an existing OTP is still
    // valid must not consume the hourly send quota.
    if (function_exists('mlr_rate_limit_check')) {
        $limit = mlr_rate_limit_check('cooperation_otp_send', 5, HOUR_IN_SECONDS, [$token]);
        if (is_wp_error($limit)) {
            wp_send_json_error(['message' => $limit->get_error_message(), 'otp_active' => false]);
            return;
        }
    }

    $sms_options = get_option('mlr_sms_settings', []);
    $otp_settings = $sms_options['otp_settings']['cooperation_sign'] ?? [];
    if (empty($otp_settings['enabled']) || empty($otp_settings['bodyId'])) {
        if (function_exists('mlr_log')) {
            mlr_log('Cooperation OTP SMS configuration unavailable.', ['request_id' => (int) $request->id]);
        }
        wp_send_json_error(['message' => 'امکان ارسال کد تأیید در حال حاضر وجود ندارد.', 'otp_active' => false]);
        return;
    }

    $request_data = json_decode($request->data, true);
    if (!is_array($request_data)) {
        $request_data = [];
    }

    $mobile_number = null;
    foreach (['mobile_number', 'mobile', 'phone_number', 'phone', 'شماره همراه', 'موبایل'] as $key) {
        if (isset($request_data[$key]) && $request_data[$key] !== '') {
            $mobile_number = mlr_normalize_mobile_for_cooperation($request_data[$key]);
            if ($mobile_number) {
                break;
            }
        }
    }
    // Compatibility with old/custom cooperation-form payloads.
    if (!$mobile_number) {
        foreach ($request_data as $value) {
            if (is_scalar($value) && $value !== '') {
                $mobile_number = mlr_normalize_mobile_for_cooperation($value);
                if ($mobile_number) {
                    break;
                }
            }
        }
    }
    if (!$mobile_number) {
        if (function_exists('mlr_log')) {
            mlr_log('Cooperation OTP mobile missing.', ['request_id' => (int) $request->id]);
        }
        wp_send_json_error(['message' => 'شماره موبایل معتبری برای ارسال کد یافت نشد.', 'otp_active' => false]);
        return;
    }

    // Canonical cooperation-signing OTP is exactly six digits.
    $otp_code = (string) random_int(100000, 999999);
    $otp_expiry_time = time() + 60;
    $otp_expiry_mysql = date('Y-m-d H:i:s', $otp_expiry_time);
    $otp_hash = wp_hash_password($otp_code);
    $now_mysql = date('Y-m-d H:i:s');

    // Atomic claim: two simultaneous clicks cannot activate two different OTPs.
    $updated = $wpdb->query($wpdb->prepare(
        "UPDATE `{$table_name}`
         SET otp_code = %s, otp_expires_at = %s
         WHERE id = %d
           AND COALESCE(contract_signed_status, 'pending') <> 'signed'
           AND (otp_expires_at IS NULL OR otp_expires_at = '' OR otp_expires_at <= %s)",
        $otp_hash,
        $otp_expiry_mysql,
        (int) $request->id,
        $now_mysql
    ));

    if ($updated === false) {
        if (function_exists('mlr_log')) {
            mlr_log('Cooperation OTP DB save failed.', [
                'request_id' => (int) $request->id,
                'db_error' => $wpdb->last_error,
            ]);
        }
        wp_send_json_error(['message' => 'خطا در ذخیره‌سازی کد تأیید.', 'otp_active' => false]);
        return;
    }

    if ((int) $updated !== 1) {
        $current = $wpdb->get_row($wpdb->prepare(
            "SELECT otp_expires_at, contract_signed_status FROM `{$table_name}` WHERE id = %d",
            (int) $request->id
        ));
        if ($current && $current->contract_signed_status === 'signed') {
            wp_send_json_error(['message' => 'این قرارداد قبلاً امضا شده است.', 'otp_active' => false]);
            return;
        }
        $remaining_time = $current ? mlr_cooperation_otp_remaining_seconds($current->otp_expires_at) : 0;
        if ($remaining_time > 0) {
            wp_send_json_error([
                'message' => "کد تأیید قبلی هنوز معتبر است. همان کد را وارد کنید؛ ارسال مجدد تا {$remaining_time} ثانیه دیگر امکان‌پذیر است.",
                'otp_active' => true,
                'cooldown' => $remaining_time,
            ]);
            return;
        }
        wp_send_json_error(['message' => 'امکان ایجاد کد تأیید جدید در حال حاضر وجود ندارد.', 'otp_active' => false]);
        return;
    }

    $attempt_key = 'mlr_coop_otp_attempts_' . substr(hash('sha256', $request->id . '|' . $token), 0, 32);
    delete_transient($attempt_key);

    $sms_variables = mlr_cooperation_build_otp_sms_variables($otp_settings, $request, $otp_code);
    $sms_provider = new MLR_MeliPayamak_Provider();
    $result = $sms_provider->send_otp_pattern($mobile_number, $otp_settings['bodyId'], $sms_variables);

    if (is_wp_error($result)) {
        // Clear only the OTP created by this request. A newer concurrent OTP, if any,
        // must never be invalidated by an older failed SMS request.
        $wpdb->query($wpdb->prepare(
            "UPDATE `{$table_name}` SET otp_code = NULL, otp_expires_at = NULL WHERE id = %d AND otp_code = %s",
            (int) $request->id,
            $otp_hash
        ));
        if (function_exists('mlr_log')) {
            mlr_log('Cooperation OTP SMS send failed.', [
                'request_id' => (int) $request->id,
                'provider_error' => $result->get_error_message(),
            ]);
        }
        wp_send_json_error(['message' => 'خطا در ارسال پیامک کد تأیید. لطفاً دوباره تلاش کنید.', 'otp_active' => false]);
        return;
    }

    $remaining_time = max(1, $otp_expiry_time - time());
    if (function_exists('mlr_log')) {
        mlr_log('Cooperation OTP SMS sent.', ['request_id' => (int) $request->id]);
    }
    wp_send_json_success([
        'message' => 'کد تأیید ۶ رقمی به شماره موبایل شما ارسال شد.',
        'cooldown' => $remaining_time,
        'otp_active' => true,
    ]);
}


// AJAX action for verifying OTP.
add_action('wp_ajax_mlr_verify_cooperation_otp', 'mlr_verify_cooperation_otp_callback');
add_action('wp_ajax_nopriv_mlr_verify_cooperation_otp', 'mlr_verify_cooperation_otp_callback');

function mlr_verify_cooperation_otp_callback() {
    check_ajax_referer('mlr_verify_cooperation_otp_nonce', 'nonce');

    $token = isset($_POST['request_token']) ? sanitize_text_field(wp_unslash($_POST['request_token'])) : '';
    $raw_otp = isset($_POST['otp_code']) ? wp_unslash($_POST['otp_code']) : '';
    $otp_entered = function_exists('mlr_normalize_digits')
        ? mlr_normalize_digits($raw_otp, true)
        : preg_replace('/[^0-9]/', '', (string) $raw_otp);

    // Six digits are canonical; five digits remain accepted only for an OTP that
    // may have been issued immediately before this plugin upgrade.
    if ($token === '' || !in_array(strlen($otp_entered), [5, 6], true)) {
        wp_send_json_error(['message' => 'اطلاعات ارسال‌شده نامعتبر است (توکن یا فرمت کد).', 'otp_active' => false]);
        return;
    }

    if (function_exists('mlr_rate_limit_check')) {
        $limit = mlr_rate_limit_check('cooperation_otp_verify', 12, 15 * MINUTE_IN_SECONDS, [$token]);
        if (is_wp_error($limit)) {
            wp_send_json_error(['message' => $limit->get_error_message(), 'otp_active' => true]);
            return;
        }
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'cooperation_requests';
    $request = $wpdb->get_row($wpdb->prepare(
        "SELECT id, status, otp_code, otp_expires_at, contract_signed_status
         FROM `{$table_name}`
         WHERE contract_token = %s",
        $token
    ));

    if (!$request) {
        wp_send_json_error(['message' => 'درخواست همکاری یافت نشد.', 'otp_active' => false]);
        return;
    }
    if ($request->contract_signed_status === 'signed') {
        wp_send_json_error(['message' => 'این قرارداد قبلاً امضا شده است.', 'otp_active' => false]);
        return;
    }
    if (!mlr_cooperation_signing_status_allowed($request->status)) {
        wp_send_json_error(['message' => 'این قرارداد در وضعیت فعلی قابل امضا نیست.', 'otp_active' => false]);
        return;
    }

    $attempt_key = 'mlr_coop_otp_attempts_' . substr(hash('sha256', $request->id . '|' . $token), 0, 32);
    $attempts = (int) get_transient($attempt_key);
    if ($attempts >= 5) {
        $wpdb->update(
            $table_name,
            ['otp_code' => null, 'otp_expires_at' => null],
            ['id' => $request->id],
            ['%s', '%s'],
            ['%d']
        );
        delete_transient($attempt_key);
        wp_send_json_error([
            'message' => 'تعداد تلاش‌های ناموفق بیش از حد مجاز بود. لطفاً کد جدید دریافت کنید.',
            'otp_active' => false,
            'attempts_remaining' => 0,
        ]);
        return;
    }

    $remaining_time = mlr_cooperation_otp_remaining_seconds($request->otp_expires_at);
    if ($remaining_time <= 0 || empty($request->otp_code)) {
        $wpdb->update(
            $table_name,
            ['otp_code' => null, 'otp_expires_at' => null],
            ['id' => $request->id],
            ['%s', '%s'],
            ['%d']
        );
        delete_transient($attempt_key);
        wp_send_json_error([
            'message' => 'کد تأیید منقضی شده است. لطفاً کد جدید دریافت کنید.',
            'otp_active' => false,
            'cooldown' => 0,
        ]);
        return;
    }

    $stored = (string) $request->otp_code;
    if (strpos($stored, '$') === 0) {
        $valid = wp_check_password($otp_entered, $stored);
    } else {
        // Backward compatibility for plaintext OTPs created by an older version.
        $valid = hash_equals($stored, $otp_entered);
    }

    if (!$valid) {
        $attempts++;
        $attempts_remaining = max(0, 5 - $attempts);
        set_transient($attempt_key, $attempts, 10 * MINUTE_IN_SECONDS);

        if ($attempts >= 5) {
            $wpdb->update(
                $table_name,
                ['otp_code' => null, 'otp_expires_at' => null],
                ['id' => $request->id],
                ['%s', '%s'],
                ['%d']
            );
            delete_transient($attempt_key);
            wp_send_json_error([
                'message' => 'تعداد تلاش‌های ناموفق بیش از حد مجاز بود. لطفاً کد جدید دریافت کنید.',
                'otp_active' => false,
                'attempts_remaining' => 0,
            ]);
            return;
        }

        wp_send_json_error([
            'message' => 'کد تأیید واردشده صحیح نمی‌باشد.',
            'otp_active' => true,
            'cooldown' => $remaining_time,
            'attempts_remaining' => $attempts_remaining,
        ]);
        return;
    }

    $signed_time = current_time('mysql');
    $expiry_time_mysql = date('Y-m-d H:i:s', strtotime('+1 year', strtotime($signed_time)));

    // Atomic finalization: the same OTP cannot sign the same contract twice in a race.
    $updated = $wpdb->query($wpdb->prepare(
        "UPDATE `{$table_name}`
         SET contract_signed_status = 'signed',
             contract_signed_at = %s,
             contract_expires_at = %s,
             otp_code = NULL,
             otp_expires_at = NULL
         WHERE id = %d
           AND COALESCE(contract_signed_status, 'pending') <> 'signed'
           AND otp_code = %s",
        $signed_time,
        $expiry_time_mysql,
        (int) $request->id,
        $stored
    ));

    if ($updated === false) {
        if (function_exists('mlr_log')) {
            mlr_log('Cooperation contract sign DB update failed.', [
                'request_id' => (int) $request->id,
                'db_error' => $wpdb->last_error,
            ]);
        }
        wp_send_json_error(['message' => 'خطا در ثبت امضای قرارداد.', 'otp_active' => true, 'cooldown' => $remaining_time]);
        return;
    }

    if ((int) $updated !== 1) {
        $signed_status = $wpdb->get_var($wpdb->prepare(
            "SELECT contract_signed_status FROM `{$table_name}` WHERE id = %d",
            (int) $request->id
        ));
        if ($signed_status === 'signed') {
            delete_transient($attempt_key);
            wp_send_json_success(['message' => 'قرارداد با موفقیت امضا شد.']);
            return;
        }
        wp_send_json_error(['message' => 'کد تأیید دیگر معتبر نیست. لطفاً کد جدید دریافت کنید.', 'otp_active' => false]);
        return;
    }

    delete_transient($attempt_key);
    if (function_exists('mlr_log')) {
        mlr_log('Cooperation contract signed.', ['request_id' => (int) $request->id]);
    }
    wp_send_json_success(['message' => 'قرارداد با موفقیت امضا شد.']);
}
