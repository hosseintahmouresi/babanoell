<?php
if (!defined('ABSPATH')) {
    exit;
}

add_action('wp_ajax_mlr_get_front_actions', 'mlr_get_front_actions_callback');
add_action('wp_ajax_nopriv_mlr_get_front_actions', 'mlr_get_front_actions_callback');

function mlr_get_front_actions_callback() {
    check_ajax_referer('mlr_ajax_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'دسترسی غیرمجاز.']);
        return;
    }
    
    $request_id = isset($_POST['request_id']) ? intval($_POST['request_id']) : 0;
    $user_id = get_current_user_id();
    
    if (!$request_id) {
        wp_send_json_error(['message' => 'شناسه درخواست نامعتبر است.']);
        return;
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'loan_requests';
    $request = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d LIMIT 1", $request_id));
    
    if (!$request || (function_exists('mlr_can_access_loan_request') && !mlr_can_access_loan_request($request, $user_id))) {
        wp_send_json_error(['message' => 'درخواست یافت نشد یا دسترسی ندارید.']);
        return;
    }

    if (!function_exists('mlr_can_access_loan_request') && intval($request->user_id ?? 0) !== intval($user_id)) {
        wp_send_json_error(['message' => 'درخواست یافت نشد یا دسترسی ندارید.']);
        return;
    }
    
    ob_start();
    ?>
    <div class="mlr-front-actions">
        <h3>عملیات درخواست</h3>
        
        <?php if ($request->status === 'نقص مدارک') : ?>
            <a href="<?php echo home_url('/edit-request/?id=' . $request->id); ?>" class="button">تکمیل مدارک</a>
        <?php elseif ($request->status === 'تکمیل مدارک') : ?>
            <p>درخواست شما در حال بررسی است.</p>
        <?php elseif ($request->status === 'تأیید مدارک') : ?>
            <p>مدارک شما تأیید شده است. در انتظار اعطا.</p>
        <?php elseif ($request->status === 'اعطا شده') : ?>
            <p>وام شما اعطا شده است.</p>
            <?php if ($request->contract_file) : ?>
                <a href="<?php echo esc_url(wp_get_attachment_url($request->contract_file)); ?>" class="button" target="_blank">دانلود قرارداد</a>
            <?php endif; ?>
        <?php elseif ($request->status === 'رد شده') : ?>
            <p>درخواست شما رد شده است.</p>
            <?php if ($request->comments) : ?>
                <div class="mlr-comments">
                    <h4>دلیل رد:</h4>
                    <p><?php echo esc_html($request->comments); ?></p>
                </div>
            <?php endif; ?>
        <?php else : ?>
            <p>وضعیت فعلی: <?php echo esc_html($request->status); ?></p>
        <?php endif; ?>
    </div>
    <?php
    $html = ob_get_clean();
    
    wp_send_json_success(['html' => $html]);
}
