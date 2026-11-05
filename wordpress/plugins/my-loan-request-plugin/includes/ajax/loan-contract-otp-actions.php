<?php
if (!defined('ABSPATH')) {
    exit;
}

add_action('wp_ajax_mlr_send_loan_contract_otp', 'mlr_send_loan_contract_otp_callback');
add_action('wp_ajax_mlr_verify_loan_contract_otp', 'mlr_verify_loan_contract_otp_callback');
add_action('wp_ajax_mlr_send_public_loan_contract_otp', 'mlr_send_public_loan_contract_otp_callback');
add_action('wp_ajax_nopriv_mlr_send_public_loan_contract_otp', 'mlr_send_public_loan_contract_otp_callback');
add_action('wp_ajax_mlr_verify_public_loan_contract_otp', 'mlr_verify_public_loan_contract_otp_callback');
add_action('wp_ajax_nopriv_mlr_verify_public_loan_contract_otp', 'mlr_verify_public_loan_contract_otp_callback');

if (!function_exists('mlr_loan_contract_otp_error')) {
    function mlr_loan_contract_otp_error($code, $message, array $data = []) {
        $data = array_merge(['message' => (string) $message], $data);
        return new WP_Error($code, (string) $message, $data);
    }
}

if (!function_exists('mlr_loan_contract_json_from_result')) {
    function mlr_loan_contract_json_from_result($result) {
        if (is_wp_error($result)) {
            $data = $result->get_error_data();
            if (!is_array($data)) {
                $data = [];
            }
            if (empty($data['message'])) {
                $data['message'] = $result->get_error_message();
            }
            wp_send_json_error($data);
            return;
        }
        wp_send_json_success(is_array($result) ? $result : []);
    }
}

if (!function_exists('mlr_get_authorized_loan_contract_sign_request')) {
    function mlr_get_authorized_loan_contract_sign_request($request_id, $portal_context) {
        $portal_context = sanitize_key((string) $portal_context);
        if (!in_array($portal_context, ['acceptor', 'applicant'], true)) {
            return new WP_Error('mlr_contract_portal_invalid', 'مسیر دسترسی قرارداد نامعتبر است.');
        }

        if (!function_exists('mlr_get_authorized_document_request')) {
            return new WP_Error('mlr_contract_service_unavailable', 'سرویس اعتبارسنجی پرونده در دسترس نیست.');
        }

        $request = mlr_get_authorized_document_request(
            absint($request_id),
            $portal_context,
            ['completion_documents', 'pending_documents']
        );
        if (is_wp_error($request)) {
            return $request;
        }
        if (!function_exists('mlr_is_loan_contract_digital') || !mlr_is_loan_contract_digital($request, true)) {
            return new WP_Error('mlr_contract_not_digital', 'برای این پرونده، قرارداد به‌صورت فیزیکی تنظیم شده است.');
        }
        return $request;
    }
}

if (!function_exists('mlr_get_public_loan_contract_sign_request')) {
    function mlr_get_public_loan_contract_sign_request($token, $allow_signed = true) {
        $request_id = function_exists('mlr_parse_loan_contract_view_token')
            ? mlr_parse_loan_contract_view_token($token)
            : 0;
        if ($request_id <= 0) {
            return new WP_Error('mlr_public_contract_token_invalid', 'لینک قرارداد نامعتبر است.');
        }

        global $wpdb;
        $request = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM `{$wpdb->prefix}loan_requests` WHERE id = %d LIMIT 1",
            $request_id
        ));
        if (!$request) {
            return new WP_Error('mlr_public_contract_not_found', 'قرارداد مورد نظر یافت نشد.');
        }
        if (!function_exists('mlr_is_loan_contract_digital') || !mlr_is_loan_contract_digital($request, true)) {
            return new WP_Error('mlr_public_contract_not_digital', 'این پرونده قرارداد دیجیتال ندارد.');
        }
        if ($allow_signed && function_exists('mlr_is_loan_contract_signed') && mlr_is_loan_contract_signed($request_id)) {
            return $request;
        }
        $reissue_pending = function_exists('mlr_contract_reissue_is_pending') && mlr_contract_reissue_is_pending($request_id);
        if (!$reissue_pending && (!function_exists('mlr_loan_contract_signing_stage_allowed') || !mlr_loan_contract_signing_stage_allowed($request->status ?? ''))) {
            return new WP_Error('mlr_public_contract_stage_invalid', 'این قرارداد در وضعیت فعلی قابل امضا نیست.');
        }
        return $request;
    }
}

if (!function_exists('mlr_send_loan_contract_link_for_request')) {
    /**
     * Send only the secure contract preview/view link.
     *
     * IMPORTANT: this function intentionally does NOT generate an OTP, does NOT
     * create the OTP transient and does NOT start the 120-second OTP lifetime.
     * It is used by the per-request reissue workflow so the applicant can first
     * open and review the revised contract. The OTP is generated later, only
     * after the applicant explicitly accepts the contract and requests a code.
     */
    function mlr_send_loan_contract_link_for_request($request) {
        $request_id = absint($request->id ?? 0);
        if ($request_id <= 0) {
            return mlr_loan_contract_otp_error('mlr_contract_request_invalid', 'شناسه پرونده نامعتبر است.', ['otp_active' => false]);
        }

        $mobile = function_exists('mlr_normalize_mobile_for_cooperation')
            ? mlr_normalize_mobile_for_cooperation($request->mobile_number ?? '')
            : null;
        if (!$mobile) {
            return mlr_loan_contract_otp_error('mlr_contract_mobile_invalid', 'شماره موبایل معتبر متقاضی برای ارسال لینک قرارداد یافت نشد.', ['otp_active' => false]);
        }

        if (function_exists('mlr_rate_limit_check')) {
            $limit = mlr_rate_limit_check('loan_contract_link_send', 10, HOUR_IN_SECONDS, [$request_id, $mobile]);
            if (is_wp_error($limit)) {
                return mlr_loan_contract_otp_error('mlr_contract_link_rate_limited', $limit->get_error_message(), ['otp_active' => false]);
            }
        }

        // Link-only delivery must use exactly the same configured pattern that
        // the normal contract/cooperation flow uses. 8.4.6.78 accidentally
        // referenced $send_contract_link here even though this function has no
        // such parameter; that left $link_settings empty and could call the SMS
        // provider with Body ID 0, producing a false "Body ID invalid" notice.
        $link_settings = function_exists('mlr_loan_contract_get_link_sms_settings')
            ? mlr_loan_contract_get_link_sms_settings()
            : [];
        if (empty($link_settings['bodyId'])) {
            return mlr_loan_contract_otp_error('mlr_contract_link_sms_pattern_missing', 'الگوی پیامک لینک قرارداد همکاری (تایید مدارک) در تنظیمات سامانه یافت نشد.', ['otp_active' => false]);
        }

        // Build the current payload before notifying the applicant. This avoids
        // sending a link to a revision that cannot actually be rendered.
        $contract = mlr_get_loan_contract_payload($request, '', true);
        if (is_wp_error($contract)) {
            return mlr_loan_contract_otp_error('mlr_contract_payload_failed', $contract->get_error_message(), ['otp_active' => false]);
        }
        if (trim(wp_strip_all_tags((string) ($contract['contract_html'] ?? ''))) === '') {
            return mlr_loan_contract_otp_error('mlr_contract_text_empty', 'متن قرارداد این طرح تنظیم نشده است و امکان ارسال لینک وجود ندارد.', ['otp_active' => false]);
        }

        $sms_provider = new MLR_MeliPayamak_Provider();
        $link_variables = function_exists('mlr_loan_contract_build_link_sms_variables')
            ? mlr_loan_contract_build_link_sms_variables($link_settings, $request, (string) ($contract['plan_name'] ?? ''))
            : [];
        $link_sent = $sms_provider->send_pattern($mobile, $link_settings['bodyId'], $link_variables);
        if (is_wp_error($link_sent)) {
            if (function_exists('mlr_log')) {
                mlr_log('Loan contract preview link SMS send failed.', [
                    'request_id' => $request_id,
                    'body_id' => sanitize_text_field((string) $link_settings['bodyId']),
                    'variables_count' => count($link_variables),
                    'provider_error' => $link_sent->get_error_message(),
                ]);
            }
            return mlr_loan_contract_otp_error(
                'mlr_contract_link_sms_send_failed',
                'ارسال لینک پیش‌نمایش قرارداد انجام نشد: ' . $link_sent->get_error_message(),
                ['otp_active' => false]
            );
        }

        $contract_url = function_exists('mlr_get_loan_contract_view_url') ? mlr_get_loan_contract_view_url($request_id) : '';
        if (function_exists('mlr_log')) {
            mlr_log('Loan contract preview link SMS sent without OTP.', [
                'request_id' => $request_id,
                'contract_url' => $contract_url,
                'contract_revision' => max(1, absint($contract['contract_revision'] ?? 1)),
            ]);
        }

        return [
            'message' => 'لینک پیش‌نمایش قرارداد به شماره موبایل متقاضی ارسال شد. کد تأیید هنوز صادر نشده است.',
            'link_sent' => true,
            'otp_active' => false,
            'mobile_masked' => mlr_loan_contract_mask_mobile($mobile),
            'contract_url' => $contract_url,
            'contract_revision' => max(1, absint($contract['contract_revision'] ?? 1)),
        ];
    }
}

if (!function_exists('mlr_send_loan_contract_otp_for_request')) {
    function mlr_send_loan_contract_otp_for_request($request, $send_contract_link = true) {
        $request_id = absint($request->id ?? 0);
        if ($request_id <= 0) {
            return mlr_loan_contract_otp_error('mlr_contract_request_invalid', 'شناسه پرونده نامعتبر است.', ['otp_active' => false]);
        }

        if (function_exists('mlr_is_loan_contract_signed') && mlr_is_loan_contract_signed($request_id)) {
            $proof = mlr_get_loan_contract_signature($request_id);
            return [
                'message' => 'این قرارداد قبلاً به‌صورت دیجیتال تأیید شده است.',
                'signed' => true,
                'signed_at' => function_exists('mlr_loan_contract_signed_display_date') ? mlr_loan_contract_signed_display_date($proof) : '',
                'contract_url' => function_exists('mlr_get_loan_contract_view_url') ? mlr_get_loan_contract_view_url($request_id) : '',
            ];
        }

        $mobile = function_exists('mlr_normalize_mobile_for_cooperation')
            ? mlr_normalize_mobile_for_cooperation($request->mobile_number ?? '')
            : null;
        if (!$mobile) {
            return mlr_loan_contract_otp_error('mlr_contract_mobile_invalid', 'شماره موبایل معتبر متقاضی برای ارسال کد تأیید یافت نشد.', ['otp_active' => false]);
        }

        $transient_key = mlr_loan_contract_otp_transient_key($request_id);
        $active = get_transient($transient_key);
        if (is_array($active) && !empty($active['expires_at']) && absint($active['expires_at']) > time()) {
            $remaining = max(1, absint($active['expires_at']) - time());
            return mlr_loan_contract_otp_error(
                'mlr_contract_otp_still_active',
                "کد تأیید قبلی هنوز معتبر است. همان کد را وارد کنید؛ ارسال مجدد تا {$remaining} ثانیه دیگر امکان‌پذیر است.",
                [
                    'otp_active' => true,
                    'cooldown' => $remaining,
                    'mobile_masked' => mlr_loan_contract_mask_mobile($mobile),
                ]
            );
        }

        if (function_exists('mlr_rate_limit_check')) {
            $limit = mlr_rate_limit_check('loan_contract_otp_send', 5, HOUR_IN_SECONDS, [$request_id, $mobile]);
            if (is_wp_error($limit)) {
                return mlr_loan_contract_otp_error('mlr_contract_send_rate_limited', $limit->get_error_message(), ['otp_active' => false]);
            }
        }

        $otp_settings = function_exists('mlr_loan_contract_get_otp_settings') ? mlr_loan_contract_get_otp_settings() : [];
        if (empty($otp_settings['enabled']) || empty($otp_settings['bodyId'])) {
            return mlr_loan_contract_otp_error('mlr_contract_sms_pattern_missing', 'الگوی پیامک کد تأیید قرارداد همکاری در تنظیمات سامانه فعال نیست.', ['otp_active' => false]);
        }

        // A reissued contract has already delivered the preview link before the
        // applicant asks for OTP. In that path we must not require, validate or
        // touch the link Body ID again. Normal signing keeps the historical
        // behavior and sends the link before the OTP.
        $link_settings = [];
        if ($send_contract_link) {
            $link_settings = function_exists('mlr_loan_contract_get_link_sms_settings')
                ? mlr_loan_contract_get_link_sms_settings()
                : [];
            if (empty($link_settings['bodyId'])) {
                return mlr_loan_contract_otp_error('mlr_contract_link_sms_pattern_missing', 'الگوی پیامک لینک قرارداد همکاری (تایید مدارک) در تنظیمات سامانه یافت نشد.', ['otp_active' => false]);
            }
        }

        $lock_token = mlr_loan_contract_acquire_otp_lock($request_id);
        if (!$lock_token) {
            return mlr_loan_contract_otp_error('mlr_contract_send_locked', 'درخواست ارسال کد دیگری در حال پردازش است. چند لحظه بعد دوباره تلاش کنید.', ['otp_active' => false]);
        }

        $release = static function() use ($request_id, $lock_token) {
            mlr_loan_contract_release_otp_lock($request_id, $lock_token);
        };

        $active = get_transient($transient_key);
        if (is_array($active) && !empty($active['expires_at']) && absint($active['expires_at']) > time()) {
            $remaining = max(1, absint($active['expires_at']) - time());
            $release();
            return mlr_loan_contract_otp_error(
                'mlr_contract_otp_still_active',
                "کد تأیید قبلی هنوز معتبر است. همان کد را وارد کنید؛ ارسال مجدد تا {$remaining} ثانیه دیگر امکان‌پذیر است.",
                [
                    'otp_active' => true,
                    'cooldown' => $remaining,
                    'mobile_masked' => mlr_loan_contract_mask_mobile($mobile),
                ]
            );
        }

        $contract = mlr_get_loan_contract_payload($request, '', true);
        if (is_wp_error($contract)) {
            $release();
            return mlr_loan_contract_otp_error('mlr_contract_payload_failed', $contract->get_error_message(), ['otp_active' => false]);
        }
        if (trim(wp_strip_all_tags((string) $contract['contract_html'])) === '') {
            $release();
            return mlr_loan_contract_otp_error('mlr_contract_text_empty', 'متن قرارداد این طرح تنظیم نشده است و امکان امضای دیجیتال وجود ندارد.', ['otp_active' => false]);
        }

        try {
            $otp_code = (string) random_int(100000, 999999);
        } catch (Throwable $e) {
            $release();
            if (function_exists('mlr_log')) {
                mlr_log('Loan contract OTP generation failed.', ['request_id' => $request_id]);
            }
            return mlr_loan_contract_otp_error('mlr_contract_otp_generate_failed', 'ایجاد کد تأیید انجام نشد. لطفاً دوباره تلاش کنید.', ['otp_active' => false]);
        }

        $sms_provider = new MLR_MeliPayamak_Provider();

        // Normal/legacy flow may deliver the link together with OTP. Reissued
        // contracts call this function with $send_contract_link=false after the
        // applicant has already opened the preview link, so no duplicate link is
        // sent and the OTP lifetime starts only on explicit applicant action.
        if ($send_contract_link) {
            $link_variables = function_exists('mlr_loan_contract_build_link_sms_variables')
                ? mlr_loan_contract_build_link_sms_variables($link_settings, $request, $contract['plan_name'])
                : [];
            $link_sent = $sms_provider->send_pattern($mobile, $link_settings['bodyId'], $link_variables);
            if (is_wp_error($link_sent)) {
                $release();
                if (function_exists('mlr_log')) {
                    mlr_log('Loan contract link SMS send failed.', [
                        'request_id' => $request_id,
                        'body_id' => sanitize_text_field((string) $link_settings['bodyId']),
                        'variables_count' => count($link_variables),
                        'provider_error' => $link_sent->get_error_message(),
                    ]);
                }
                return mlr_loan_contract_otp_error(
                    'mlr_contract_link_sms_send_failed',
                    'ارسال لینک قرارداد انجام نشد: ' . $link_sent->get_error_message(),
                    ['otp_active' => false]
                );
            }
        }

        $expires_at = time() + 120;
        $otp_state = [
            'version' => 4,
            'request_id' => $request_id,
            'mobile' => $mobile,
            'otp_hash' => wp_hash_password($otp_code),
            'expires_at' => $expires_at,
            'attempts' => 0,
            'contract_digest' => (string) $contract['contract_digest'],
            'contract_date_mysql' => (string) $contract['contract_date_mysql'],
            'contract_date_display' => (string) $contract['contract_date_display'],
            'contract_html' => (string) $contract['contract_html'],
            'letterhead_url' => (string) $contract['letterhead_url'],
            'footer_url' => (string) $contract['footer_url'],
            'repeat_footer_html' => (string) ($contract['repeat_footer_html'] ?? ''),
            'plan_id' => absint($contract['plan_id']),
            'plan_name' => (string) $contract['plan_name'],
            'contract_revision' => max(1, absint($contract['contract_revision'] ?? 1)),
            'accepted' => true,
            'contract_view_token' => function_exists('mlr_loan_contract_view_token') ? mlr_loan_contract_view_token($request_id) : '',
        ];
        set_transient($transient_key, $otp_state, 2 * MINUTE_IN_SECONDS);

        $variables = mlr_loan_contract_build_otp_sms_variables($otp_settings, $request, $contract['plan_name'], $otp_code);
        $sent = $sms_provider->send_otp_pattern($mobile, $otp_settings['bodyId'], $variables);
        if (is_wp_error($sent)) {
            delete_transient($transient_key);
            $release();
            if (function_exists('mlr_log')) {
                mlr_log('Loan contract OTP SMS send failed.', [
                    'request_id' => $request_id,
                    'body_id' => sanitize_text_field((string) $otp_settings['bodyId']),
                    'variables_count' => count($variables),
                    'provider_error' => $sent->get_error_message(),
                ]);
            }
            return mlr_loan_contract_otp_error(
                'mlr_contract_sms_send_failed',
                'ارسال پیامک کد تأیید انجام نشد: ' . $sent->get_error_message(),
                ['otp_active' => false]
            );
        }

        $release();
        if (function_exists('mlr_log')) {
            mlr_log('Loan contract OTP SMS sent.', [
                'request_id' => $request_id,
                'contract_url' => function_exists('mlr_get_loan_contract_view_url') ? mlr_get_loan_contract_view_url($request_id) : '',
            ]);
        }
        return [
            'message' => $send_contract_link
                ? 'لینک قرارداد و کد تأیید ۶ رقمی به شماره موبایل متقاضی ارسال شد.'
                : 'کد تأیید ۶ رقمی به شماره موبایل متقاضی ارسال شد.',
            'otp_active' => true,
            'cooldown' => max(1, $expires_at - time()),
            'mobile_masked' => mlr_loan_contract_mask_mobile($mobile),
            'contract_url' => function_exists('mlr_get_loan_contract_view_url') ? mlr_get_loan_contract_view_url($request_id) : '',
        ];
    }
}

if (!function_exists('mlr_verify_loan_contract_otp_for_request')) {
    function mlr_verify_loan_contract_otp_for_request($request, $otp_entered, $portal_context) {
        // A digital contract might be converted to physical between the public
        // page opening and the verification request. Fail closed before saving
        // any OTP proof, even for a previously-issued still-valid code.
        if (!function_exists('mlr_is_loan_contract_digital') || !mlr_is_loan_contract_digital($request, true)) {
            return mlr_loan_contract_otp_error('mlr_contract_now_physical', 'روش قرارداد این پرونده به فیزیکی تغییر کرده است؛ کد دیجیتال قبلی قابل تأیید نیست.', ['otp_active' => false]);
        }
        $request_id = absint($request->id ?? 0);
        $otp_entered = function_exists('mlr_normalize_digits')
            ? mlr_normalize_digits($otp_entered, true)
            : preg_replace('/\D+/', '', (string) $otp_entered);
        if ($request_id <= 0 || strlen((string) $otp_entered) !== 6) {
            return mlr_loan_contract_otp_error('mlr_contract_otp_format_invalid', 'کد تأیید باید دقیقاً ۶ رقم باشد.', ['otp_active' => true]);
        }

        if (mlr_is_loan_contract_signed($request_id)) {
            $proof = mlr_get_loan_contract_signature($request_id);
            return [
                'message' => 'قرارداد قبلاً به‌صورت دیجیتال تأیید شده است.',
                'signed' => true,
                'signed_at' => mlr_loan_contract_signed_display_date($proof),
                'contract_url' => function_exists('mlr_get_loan_contract_view_url') ? mlr_get_loan_contract_view_url($request_id) : '',
            ];
        }

        if (function_exists('mlr_rate_limit_check')) {
            $limit = mlr_rate_limit_check('loan_contract_otp_verify', 12, 15 * MINUTE_IN_SECONDS, [$request_id]);
            if (is_wp_error($limit)) {
                return mlr_loan_contract_otp_error('mlr_contract_verify_rate_limited', $limit->get_error_message(), ['otp_active' => true]);
            }
        }

        $transient_key = mlr_loan_contract_otp_transient_key($request_id);
        $state = get_transient($transient_key);
        if (!is_array($state) || empty($state['otp_hash']) || empty($state['expires_at']) || absint($state['expires_at']) <= time()) {
            delete_transient($transient_key);
            return mlr_loan_contract_otp_error('mlr_contract_otp_expired', 'کد تأیید منقضی شده است. لطفاً کد جدید دریافت کنید.', ['otp_active' => false, 'cooldown' => 0]);
        }

        $current_contract = mlr_get_loan_contract_payload($request, (string) ($state['contract_date_mysql'] ?? ''), true);
        if (is_wp_error($current_contract) || !hash_equals((string) ($state['contract_digest'] ?? ''), (string) ($current_contract['contract_digest'] ?? ''))) {
            delete_transient($transient_key);
            return mlr_loan_contract_otp_error('mlr_contract_changed', 'متن یا تنظیمات قرارداد بعد از ارسال کد تغییر کرده است. قرارداد را دوباره بررسی و کد جدید دریافت کنید.', ['otp_active' => false]);
        }

        $attempts = absint($state['attempts'] ?? 0);
        if ($attempts >= 5) {
            delete_transient($transient_key);
            return mlr_loan_contract_otp_error('mlr_contract_attempts_exceeded', 'تعداد تلاش‌های ناموفق بیش از حد مجاز بود. لطفاً کد جدید دریافت کنید.', ['otp_active' => false, 'attempts_remaining' => 0]);
        }

        if (!wp_check_password((string) $otp_entered, (string) $state['otp_hash'])) {
            $attempts++;
            if ($attempts >= 5) {
                delete_transient($transient_key);
                return mlr_loan_contract_otp_error('mlr_contract_attempts_exceeded', 'تعداد تلاش‌های ناموفق بیش از حد مجاز بود. لطفاً کد جدید دریافت کنید.', ['otp_active' => false, 'attempts_remaining' => 0]);
            }
            $state['attempts'] = $attempts;
            $remaining_ttl = max(1, absint($state['expires_at']) - time());
            set_transient($transient_key, $state, $remaining_ttl);
            return mlr_loan_contract_otp_error('mlr_contract_otp_invalid', 'کد تأیید واردشده صحیح نیست.', [
                'otp_active' => true,
                'cooldown' => $remaining_ttl,
                'attempts_remaining' => max(0, 5 - $attempts),
            ]);
        }

        $signed_at = current_time('mysql');
        $proof = [
            'version' => 4,
            'request_id' => $request_id,
            'plan_id' => absint($state['plan_id'] ?? $request->installment_plan_id ?? 0),
            'plan_name' => sanitize_text_field((string) ($state['plan_name'] ?? '')),
            'contract_revision' => max(1, absint($state['contract_revision'] ?? 1)),
            'method' => 'sms_otp',
            'otp_length' => 6,
            'signed_at' => $signed_at,
            'contract_date' => sanitize_text_field((string) ($state['contract_date_display'] ?? '')),
            'contract_digest' => sanitize_text_field((string) ($state['contract_digest'] ?? '')),
            'contract_html' => wp_kses_post((string) ($state['contract_html'] ?? '')),
            'letterhead_url' => esc_url_raw((string) ($state['letterhead_url'] ?? '')),
            'footer_url' => esc_url_raw((string) ($state['footer_url'] ?? '')),
            'repeat_footer_html' => wp_kses_post((string) ($state['repeat_footer_html'] ?? '')),
            'signer_name' => sanitize_text_field((string) ($request->full_name ?? '')),
            'signer_mobile' => function_exists('mlr_normalize_mobile_for_cooperation') ? (string) mlr_normalize_mobile_for_cooperation($state['mobile'] ?? '') : preg_replace('/\D+/', '', (string) ($state['mobile'] ?? '')),
            'signer_mobile_masked' => mlr_loan_contract_mask_mobile($state['mobile'] ?? ''),
            'verified_by_user_id' => get_current_user_id(),
            'portal_context' => sanitize_key((string) $portal_context),
            'contract_view_token' => function_exists('mlr_loan_contract_view_token') ? mlr_loan_contract_view_token($request_id) : '',
            'buyer_confirmation_token' => function_exists('mlr_generate_loan_contract_party_token') ? mlr_generate_loan_contract_party_token('buyer') : '',
            'platform_confirmation_token' => function_exists('mlr_generate_loan_contract_party_token') ? mlr_generate_loan_contract_party_token('platform') : '',
        ];

        $option_name = mlr_loan_contract_signature_option_name($request_id);
        $stored = add_option($option_name, $proof, '', false);
        if (!$stored) {
            $existing = mlr_get_loan_contract_signature($request_id);
            if (!$existing) {
                return mlr_loan_contract_otp_error('mlr_contract_signature_store_failed', 'ثبت نهایی امضای دیجیتال انجام نشد. لطفاً دوباره تلاش کنید.', ['otp_active' => true]);
            }
            $proof = $existing;
        } else {
            if (function_exists('mlr_add_request_event')) {
                mlr_add_request_event($request_id, 'loan_contract_digitally_signed', [
                    'شرح' => 'قرارداد دریافت تسهیلات با کد تأیید پیامکی به‌صورت دیجیتال تأیید شد.',
                    'تاریخ امضا' => mlr_loan_contract_signed_display_date($proof),
                    'روش' => 'OTP پیامکی',
                    'مسیر' => $portal_context === 'public_link' ? 'لینک مشاهده قرارداد' : 'پنل کاربری',
                ]);
            }
            if (function_exists('mlr_create_notification')) {
                mlr_create_notification(
                    'loan_contract_signed',
                    'قرارداد دریافت تسهیلات پرونده #' . $request_id . ' به‌صورت دیجیتال تأیید شد.',
                    admin_url('admin.php?page=mlr-loan-requests&s=' . $request_id)
                );
            }
            if (function_exists('mlr_log')) {
                mlr_log('Loan contract digitally signed.', ['request_id' => $request_id, 'portal_context' => $portal_context]);
            }
        }

        if (function_exists('mlr_mark_contract_reissue_signed')) {
            mlr_mark_contract_reissue_signed($request_id, $proof);
        }

        delete_transient($transient_key);
        return [
            'message' => 'قرارداد با موفقیت امضا شد.',
            'signed' => true,
            'signed_at' => mlr_loan_contract_signed_display_date($proof),
            'contract_url' => function_exists('mlr_get_loan_contract_view_url') ? mlr_get_loan_contract_view_url($request_id) : '',
        ];
    }
}

function mlr_send_loan_contract_otp_callback() {
    check_ajax_referer('mlr_frontend_nonce', 'nonce');

    $request_id = isset($_POST['request_id']) ? absint($_POST['request_id']) : 0;
    $portal_context = isset($_POST['portal_context']) ? sanitize_key(wp_unslash($_POST['portal_context'])) : '';
    $accepted = isset($_POST['accepted']) && (string) wp_unslash($_POST['accepted']) === '1';
    if (!$accepted) {
        mlr_loan_contract_json_from_result(mlr_loan_contract_otp_error(
            'mlr_contract_not_accepted',
            'برای دریافت کد، ابتدا تأیید کنید که متن قرارداد را مطالعه کرده و می‌پذیرید.',
            ['otp_active' => false]
        ));
        return;
    }

    $request = mlr_get_authorized_loan_contract_sign_request($request_id, $portal_context);
    if (is_wp_error($request)) {
        mlr_loan_contract_json_from_result(mlr_loan_contract_otp_error($request->get_error_code(), $request->get_error_message(), ['otp_active' => false]));
        return;
    }
    mlr_loan_contract_json_from_result(mlr_send_loan_contract_otp_for_request($request));
}

function mlr_verify_loan_contract_otp_callback() {
    check_ajax_referer('mlr_frontend_nonce', 'nonce');

    $request_id = isset($_POST['request_id']) ? absint($_POST['request_id']) : 0;
    $portal_context = isset($_POST['portal_context']) ? sanitize_key(wp_unslash($_POST['portal_context'])) : '';
    $raw_otp = isset($_POST['otp_code']) ? wp_unslash($_POST['otp_code']) : '';

    $request = mlr_get_authorized_loan_contract_sign_request($request_id, $portal_context);
    if (is_wp_error($request)) {
        mlr_loan_contract_json_from_result(mlr_loan_contract_otp_error($request->get_error_code(), $request->get_error_message(), ['otp_active' => false]));
        return;
    }
    mlr_loan_contract_json_from_result(mlr_verify_loan_contract_otp_for_request($request, $raw_otp, $portal_context));
}

function mlr_send_public_loan_contract_otp_callback() {
    check_ajax_referer('mlr_public_loan_contract_nonce', 'nonce');

    $token = isset($_POST['contract_token']) ? sanitize_text_field(wp_unslash($_POST['contract_token'])) : '';
    $accepted = isset($_POST['accepted']) && (string) wp_unslash($_POST['accepted']) === '1';
    if (!$accepted) {
        mlr_loan_contract_json_from_result(mlr_loan_contract_otp_error(
            'mlr_contract_not_accepted',
            'برای دریافت کد، ابتدا تأیید کنید که متن قرارداد را مطالعه کرده و می‌پذیرید.',
            ['otp_active' => false]
        ));
        return;
    }

    $request = mlr_get_public_loan_contract_sign_request($token, true);
    if (is_wp_error($request)) {
        mlr_loan_contract_json_from_result(mlr_loan_contract_otp_error($request->get_error_code(), $request->get_error_message(), ['otp_active' => false]));
        return;
    }
    $reissue_pending = function_exists('mlr_contract_reissue_is_pending') && mlr_contract_reissue_is_pending(absint($request->id ?? 0));
    // In the reissue workflow the preview link was already sent by the admin.
    // Generate/send OTP only now, after the applicant opened the link, reviewed
    // the contract, ticked acceptance and explicitly requested the code.
    mlr_loan_contract_json_from_result(mlr_send_loan_contract_otp_for_request($request, !$reissue_pending));
}

function mlr_verify_public_loan_contract_otp_callback() {
    check_ajax_referer('mlr_public_loan_contract_nonce', 'nonce');

    $token = isset($_POST['contract_token']) ? sanitize_text_field(wp_unslash($_POST['contract_token'])) : '';
    $raw_otp = isset($_POST['otp_code']) ? wp_unslash($_POST['otp_code']) : '';
    $request = mlr_get_public_loan_contract_sign_request($token, true);
    if (is_wp_error($request)) {
        mlr_loan_contract_json_from_result(mlr_loan_contract_otp_error($request->get_error_code(), $request->get_error_message(), ['otp_active' => false]));
        return;
    }
    mlr_loan_contract_json_from_result(mlr_verify_loan_contract_otp_for_request($request, $raw_otp, 'public_link'));
}
