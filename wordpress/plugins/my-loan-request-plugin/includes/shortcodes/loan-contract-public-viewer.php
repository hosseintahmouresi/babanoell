<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('mlr_render_public_loan_contract_viewer')) {
    function mlr_render_public_loan_contract_viewer($token) {
        $request = function_exists('mlr_get_public_loan_contract_sign_request')
            ? mlr_get_public_loan_contract_sign_request($token, true)
            : new WP_Error('mlr_public_contract_service_missing', 'سرویس قرارداد دیجیتال در دسترس نیست.');

        if (is_wp_error($request)) {
            return '<div class="mlr-contract-container error"><p>' . esc_html($request->get_error_message()) . '</p></div>';
        }

        $request_id = absint($request->id ?? 0);
        $proof = function_exists('mlr_get_loan_contract_signature_for_display')
            ? mlr_get_loan_contract_signature_for_display($request_id, $request)
            : (function_exists('mlr_get_loan_contract_signature') ? mlr_get_loan_contract_signature($request_id) : null);
        $signed = is_array($proof);
        $payload = null;
        if ($signed) {
            $payload = [
                'contract_html' => (string) ($proof['contract_html'] ?? ''),
                'letterhead_url' => (string) ($proof['letterhead_url'] ?? ''),
                'footer_url' => (string) ($proof['footer_url'] ?? ''),
                'repeat_footer_html' => (string) ($proof['repeat_footer_html'] ?? ''),
                'plan_name' => (string) ($proof['plan_name'] ?? ''),
            ];
        } elseif (function_exists('mlr_get_loan_contract_payload')) {
            $payload = mlr_get_loan_contract_payload($request);
        }

        if (is_wp_error($payload) || !is_array($payload)) {
            $message = is_wp_error($payload) ? $payload->get_error_message() : 'اطلاعات قرارداد قابل نمایش نیست.';
            return '<div class="mlr-contract-container error"><p>' . esc_html($message) . '</p></div>';
        }

        if (!wp_style_is('mlr-contract-viewer-css', 'registered')) {
            wp_register_style('mlr-contract-viewer-css', MLR_PLUGIN_URL . 'assets/css/contract-viewer.css', [], MLR_VERSION);
        }
        if (!wp_style_is('mlr-contract-print-css', 'registered')) {
            wp_register_style('mlr-contract-print-css', MLR_PLUGIN_URL . 'assets/css/contract-print.css', [], MLR_VERSION);
        }
        if (!wp_script_is('mlr-contract-print-js', 'registered')) {
            wp_register_script('mlr-contract-print-js', MLR_PLUGIN_URL . 'assets/js/mlr-contract-print.js', [], MLR_VERSION, true);
        }
        if (!wp_script_is('mlr-loan-contract-public-viewer-js', 'registered')) {
            wp_register_script('mlr-loan-contract-public-viewer-js', MLR_PLUGIN_URL . 'assets/js/loan-contract-public-viewer.js', ['jquery', 'mlr-contract-print-js'], MLR_VERSION, true);
        }
        wp_enqueue_style('mlr-contract-viewer-css');
        wp_enqueue_style('mlr-contract-print-css', MLR_PLUGIN_URL . 'assets/css/contract-print.css', [], MLR_VERSION);
        wp_enqueue_script('mlr-contract-print-js', MLR_PLUGIN_URL . 'assets/js/mlr-contract-print.js', [], MLR_VERSION, true);
        wp_enqueue_script('mlr-loan-contract-public-viewer-js');
        wp_localize_script('mlr-loan-contract-public-viewer-js', 'mlr_loan_contract_public_ajax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mlr_public_loan_contract_nonce'),
            'cooldown' => 120,
            'otp_length' => 6,
            'print_css_url' => MLR_PLUGIN_URL . 'assets/css/contract-print.css',
        ]);

        $contract_html = (string) ($payload['contract_html'] ?? '');
        $letterhead_url = (string) ($payload['letterhead_url'] ?? '');
        $footer_url = (string) ($payload['footer_url'] ?? '');
        $repeat_footer_html = (string) ($payload['repeat_footer_html'] ?? '');
        $payload_plan_name = (string) ($payload['plan_name'] ?? ($proof['plan_name'] ?? ''));
        if (function_exists('mlr_apply_hami_footer_policy')) {
            $footer_policy = mlr_apply_hami_footer_policy(
                $contract_html,
                $footer_url,
                $repeat_footer_html,
                (string) ($proof['signer_name'] ?? $request->full_name ?? ''),
                $payload_plan_name
            );
            $contract_html = (string) ($footer_policy['contract_html'] ?? $contract_html);
            $repeat_footer_html = (string) ($footer_policy['repeat_footer_html'] ?? '');
        }
        $signed_at = $signed && function_exists('mlr_loan_contract_signed_display_date')
            ? mlr_loan_contract_signed_display_date($proof)
            : '';
        $signature_certificate = $signed && function_exists('mlr_render_loan_contract_signature_certificate')
            ? mlr_render_loan_contract_signature_certificate($proof)
            : '';

        ob_start();
        ?>
        <div class="mlr-contract-container mlr-loan-public-contract" data-loan-contract-token="<?php echo esc_attr($token); ?>">
            <?php if ($signed): ?>
                <div class="mlr-contract-signed-notice">
                    <div class="mlr-signed-icon">✓</div>
                    <h2>قرارداد دریافت تسهیلات امضا و تأیید شده است</h2>
                    <?php if ($signed_at): ?>
                        <p>این قرارداد در تاریخ و ساعت <strong><?php echo esc_html($signed_at); ?></strong> به‌صورت دیجیتال امضا و تأیید شده است.</p>
                    <?php endif; ?>
                    <button type="button" class="mlr-print-button mlr-loan-contract-isolated-print"><span class="dashicons dashicons-printer"></span> چاپ نسخه امضا شده</button>
                </div>
            <?php endif; ?>

            <table class="mlr-print-layout-table">
                <thead>
                    <tr><td>
                        <?php if ($letterhead_url): ?>
                            <div class="mlr-contract-header"><img src="<?php echo esc_url($letterhead_url); ?>" alt="سربرگ قرارداد" class="mlr-contract-media-image"></div>
                        <?php endif; ?>
                    </td></tr>
                </thead>
                <tbody>
                    <tr><td>
                        <div class="mlr-contract-body<?php echo $signed ? ' mlr-contract-body-signed' : ''; ?>">
                            <?php echo $contract_html !== '' ? wp_kses_post(mlr_normalize_contract_markup_for_display($contract_html)) : '<p>متن قرارداد برای این طرح تنظیم نشده است.</p>'; ?>
                            <?php if ($signature_certificate !== ''): ?>
                                <?php echo wp_kses_post($signature_certificate); ?>
                            <?php endif; ?>
                        </div>
                    </td></tr>
                </tbody>
                <tfoot>
                    <tr><td>
                        <?php if ($repeat_footer_html !== ''): ?>
                            <?php echo wp_kses_post($repeat_footer_html); ?>
                        <?php endif; ?>
                        <?php if ($footer_url): ?>
                            <div class="mlr-contract-footer"><img src="<?php echo esc_url($footer_url); ?>" alt="پابرگ قرارداد" class="mlr-contract-media-image"></div>
                        <?php endif; ?>
                    </td></tr>
                </tfoot>
            </table>

            <?php if (!$signed): ?>
                <div class="mlr-contract-signature-section mlr-loan-public-signature-section" style="margin-top:24px;padding:22px;border:1px solid #dbeafe;border-radius:14px;background:#f8fbff;">
                    <label style="display:flex;align-items:flex-start;gap:8px;line-height:1.9;cursor:pointer;">
                        <input type="checkbox" class="mlr-public-loan-consent" value="1" style="margin-top:7px;">
                        <span>متن کامل قرارداد دریافت تسهیلات را مطالعه کردم و مفاد آن را می‌پذیرم.</span>
                    </label>
                    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-top:14px;">
                        <button type="button" class="mlr-sign-button mlr-public-loan-send-otp" disabled>ارسال کد تأیید</button>
                        <span class="mlr-public-loan-timer" style="font-size:13px;color:#64748b;"></span>
                    </div>
                    <div class="mlr-public-loan-otp-panel" style="display:none;margin-top:14px;">
                        <p style="margin-bottom:8px;">کد ۶ رقمی ارسال‌شده به شماره موبایل متقاضی را وارد کنید:</p>
                        <div class="mlr-otp-input-group">
                            <input type="text" class="mlr-otp-input mlr-public-loan-otp" maxlength="6" pattern="[0-9۰-۹٠-٩]*" inputmode="numeric" autocomplete="one-time-code" placeholder="------" aria-label="کد تأیید شش رقمی">
                            <button type="button" class="mlr-verify-button mlr-public-loan-verify-otp">تأیید کد و امضا</button>
                        </div>
                    </div>
                    <div class="mlr-sign-feedback mlr-public-loan-message" role="alert" aria-live="polite"></div>
                </div>
                <div class="mlr-contract-actions">
                    <button type="button" class="mlr-print-button mlr-loan-contract-isolated-print"><span class="dashicons dashicons-printer"></span> چاپ پیش‌نویس</button>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
}
