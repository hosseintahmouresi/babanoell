<?php
if (!defined('ABSPATH')) {
    exit;
}

add_shortcode('mlr_cooperation_contract_viewer', 'mlr_cooperation_contract_viewer_shortcode');

/**
 * Shortcode to display the cooperation contract based on a token.
 */
function mlr_cooperation_contract_viewer_shortcode() {
    $token = isset($_GET['token']) ? sanitize_text_field($_GET['token']) : '';

    if (empty($token)) {
        return '<div class="mlr-contract-container error"><p>لینک قرارداد نامعتبر است یا منقضی شده است.</p></div>';
    }

    // Loan/facility digital contracts intentionally share this public route so the
    // existing cooperation SMS pattern (which may hard-code this URL) keeps working.
    if (function_exists('mlr_parse_loan_contract_view_token') && mlr_parse_loan_contract_view_token($token) > 0) {
        return function_exists('mlr_render_public_loan_contract_viewer')
            ? mlr_render_public_loan_contract_viewer($token)
            : '<div class="mlr-contract-container error"><p>سرویس نمایش قرارداد تسهیلات در دسترس نیست.</p></div>';
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'cooperation_requests';

    // Added 'created_at' to the query for placeholders
    $request = $wpdb->get_row($wpdb->prepare(
        "SELECT id, request_type, status, data, created_at, contract_token, contract_signed_status, contract_signed_at, contract_expires_at
         FROM $table_name
         WHERE contract_token = %s",
        $token
    ));

    if (!$request) {
        return '<div class="mlr-contract-container error"><p>قرارداد یافت نشد.</p></div>';
    }

    // Check statuses where signing/viewing is allowed
    $allowed_view_statuses = ['تایید مدارک', 'عقد قرارداد'];
    if (!in_array($request->status, $allowed_view_statuses) && $request->contract_signed_status !== 'signed') {
         return '<div class="mlr-contract-container error"><p>این قرارداد در حال حاضر قابل مشاهده یا امضا نیست (وضعیت فعلی: '. esc_html($request->status) .').</p></div>';
    }

    // Retrieve contract settings
    $settings = get_option('mlr_cooperation_contract_settings', [
        'header_image_url' => '',
        'footer_image_url' => '',
        'contract_text'    => '',
    ]);

    // Enqueue/localize here as the authoritative path so Elementor/widgets and other
    // dynamic shortcode renderers do not depend on has_shortcode() detection.
    if (!wp_style_is('mlr-contract-viewer-css', 'registered')) {
        wp_register_style('mlr-contract-viewer-css', MLR_PLUGIN_URL . 'assets/css/contract-viewer.css', [], MLR_VERSION);
    }
    if (!wp_style_is('mlr-contract-print-css', 'registered')) {
        wp_register_style('mlr-contract-print-css', MLR_PLUGIN_URL . 'assets/css/contract-print.css', [], MLR_VERSION);
    }
    if (!wp_script_is('mlr-contract-print-js', 'registered')) {
        wp_register_script('mlr-contract-print-js', MLR_PLUGIN_URL . 'assets/js/mlr-contract-print.js', [], MLR_VERSION, true);
    }
    if (!wp_script_is('mlr-contract-viewer-js', 'registered')) {
        wp_register_script('mlr-contract-viewer-js', MLR_PLUGIN_URL . 'assets/js/contract-viewer.js', ['jquery', 'mlr-contract-print-js'], MLR_VERSION, true);
    }
    wp_enqueue_style('mlr-contract-viewer-css');
    wp_enqueue_style('mlr-contract-print-css');
    wp_enqueue_script('mlr-contract-print-js');
    wp_enqueue_script('mlr-contract-viewer-js');
    wp_localize_script('mlr-contract-viewer-js', 'mlr_contract_ajax', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'send_otp_nonce' => wp_create_nonce('mlr_send_cooperation_otp_nonce'),
        'verify_otp_nonce' => wp_create_nonce('mlr_verify_cooperation_otp_nonce'),
        'resend_cooldown' => 60,
        'otp_length' => 6,
    ]);

    ob_start(); 
    ?>
    <div class="mlr-contract-container">
        <?php if ($request->contract_signed_status === 'signed') : ?>
            <?php // Display Signed Contract Message ?>
            <div class="mlr-contract-signed-notice">
                <div class="mlr-signed-icon">✓</div>
                <h2>قرارداد امضا شده است</h2>
                <p>
                    این قرارداد در تاریخ
                    <strong><?php echo esc_html(mlr_gregorian_to_jalali($request->contract_signed_at, 'Y/m/d H:i')); ?></strong>
                    امضا شده و تا تاریخ
                    <strong><?php echo esc_html(mlr_gregorian_to_jalali($request->contract_expires_at, 'Y/m/d')); ?></strong>
                    اعتبار دارد.
                </p>
                 <button class="mlr-print-button mlr-cooperation-contract-isolated-print">
                     <span class="dashicons dashicons-printer"></span> چاپ نسخه امضا شده
                 </button>
            </div>

            <table class="mlr-print-layout-table">
                <thead>
                    <tr>
                        <td>
                            <?php if (!empty($settings['header_image_url'])) : ?>
                                <div class="mlr-contract-header" style="margin-top: 30px;">
                                    <img src="<?php echo esc_url($settings['header_image_url']); ?>" alt="سربرگ قرارداد" class="mlr-contract-media-image">
                                </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            <div class="mlr-contract-body" style="opacity: 0.7; pointer-events: none;"> 
                                <?php
                                $processed_text = mlr_replace_contract_placeholders($settings['contract_text'], $request);
                                echo wpautop(wp_kses_post($processed_text));
                                ?>
                            </div>
                        </td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr>
                        <td>
                            <?php if (!empty($settings['footer_image_url'])) : ?>
                                <div class="mlr-contract-footer">
                                    <img src="<?php echo esc_url($settings['footer_image_url']); ?>" alt="پابرگ قرارداد" class="mlr-contract-media-image">
                                </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                </tfoot>
            </table>


        <?php else : // Contract is not signed ?>
            <table class="mlr-print-layout-table">
                <thead>
                    <tr>
                        <td>
                            <?php if (!empty($settings['header_image_url'])) : ?>
                                <div class="mlr-contract-header">
                                    <img src="<?php echo esc_url($settings['header_image_url']); ?>" alt="سربرگ قرارداد" class="mlr-contract-media-image">
                                </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            <div class="mlr-contract-body">
                                <?php
                                $processed_text = mlr_replace_contract_placeholders($settings['contract_text'], $request);
                                echo wpautop(wp_kses_post($processed_text));
                                ?>
                            </div>
                        </td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr>
                        <td>
                            <?php if (!empty($settings['footer_image_url'])) : ?>
                                <div class="mlr-contract-footer">
                                    <img src="<?php echo esc_url($settings['footer_image_url']); ?>" alt="پابرگ قرارداد" class="mlr-contract-media-image">
                                </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                </tfoot>
            </table>

            <?php // Signature Section ?>
            <div class="mlr-contract-signature-section" data-token="<?php echo esc_attr($token); ?>">
                 <div id="mlr-sign-initial-view">
                     <button type="button" id="mlr-initiate-sign-btn" class="mlr-sign-button">
                         <span class="dashicons dashicons-edit"></span> امضای دیجیتال قرارداد
                     </button>
                 </div>
                 <div id="mlr-sign-otp-view" style="display: none;">
                    <p>کد ۶ رقمی ارسال شده به شماره موبایل خود را وارد کنید:</p>
                    <div class="mlr-otp-input-group">
                        <input type="text" id="mlr-otp-code" maxlength="6" pattern="[0-9۰-۹٠-٩]*" inputmode="numeric" autocomplete="one-time-code" placeholder="------" class="mlr-otp-input" aria-label="کد تایید شش رقمی">
                        <button type="button" id="mlr-verify-otp-btn" class="mlr-verify-button">تایید کد و امضا</button>
                    </div>
                    <div class="mlr-otp-timer-resend">
                        <span id="mlr-otp-timer">ارسال مجدد تا <span id="mlr-timer-countdown">60</span> ثانیه دیگر</span>
                        <button type="button" id="mlr-resend-otp-btn" class="mlr-resend-button" disabled>ارسال مجدد کد</button>
                    </div>
                 </div>
                 <div id="mlr-sign-message" class="mlr-sign-feedback" role="alert" aria-live="polite"></div>
            </div>

            <div class="mlr-contract-actions">
                 <button class="mlr-print-button mlr-cooperation-contract-isolated-print">
                     <span class="dashicons dashicons-printer"></span> چاپ پیش‌نویس
                 </button>
            </div>
        <?php endif; ?>

    </div>
    <?php
    return ob_get_clean(); 
}
?>