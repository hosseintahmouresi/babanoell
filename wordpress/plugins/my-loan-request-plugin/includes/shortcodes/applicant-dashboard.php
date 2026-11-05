<?php
if (!defined('ABSPATH')) {
    exit;
}

add_shortcode('mlr_applicant_dashboard', 'mlr_applicant_dashboard_shortcode');

function mlr_applicant_dashboard_shortcode() {
    if (!is_user_logged_in()) {
        return '<p>برای مشاهده پنل متقاضی، لطفاً ابتدا وارد شوید.</p>';
    }

    $user_id = get_current_user_id();
    if (!current_user_can('manage_options') && function_exists('mlr_is_applicant_user') && !mlr_is_applicant_user($user_id)) {
        return '<p>دسترسی به پنل متقاضی برای حساب شما فعال نیست.</p>';
    }

    wp_enqueue_style('dashicons');
    wp_enqueue_style('mlr-frontend-css', MLR_PLUGIN_URL . 'assets/css/frontend.css', [], MLR_VERSION);
    wp_enqueue_style('mlr-modal-unified-css', MLR_PLUGIN_URL . 'assets/css/mlr-modal-unified.css', ['mlr-frontend-css'], MLR_VERSION);
    wp_enqueue_style('mlr-contract-print-css', MLR_PLUGIN_URL . 'assets/css/contract-print.css', [], MLR_VERSION);
    wp_enqueue_script('mlr-contract-print-js', MLR_PLUGIN_URL . 'assets/js/mlr-contract-print.js', [], MLR_VERSION, true);
    wp_enqueue_script('mlr-jalali-datepicker', MLR_PLUGIN_URL . 'assets/js/jalali-datepicker.js', ['jquery'], MLR_VERSION, true);
    wp_enqueue_script('mlr-file-upload-helper', MLR_PLUGIN_URL . 'assets/js/mlr-file-upload-helper.js', [], MLR_VERSION, true);
    wp_enqueue_script('mlr-dashboard-document-upload', MLR_PLUGIN_URL . 'assets/js/mlr-dashboard-document-upload.js', ['jquery', 'mlr-file-upload-helper'], MLR_VERSION, true);
    wp_enqueue_script('mlr-applicant-requests-js', MLR_PLUGIN_URL . 'assets/js/mlr-applicant-requests.js', ['jquery', 'mlr-jalali-datepicker', 'mlr-dashboard-document-upload', 'mlr-contract-print-js'], MLR_VERSION, true);
    wp_localize_script('mlr-applicant-requests-js', 'mlr_applicant_ajax_obj', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'filter_nonce' => wp_create_nonce('mlr_applicant_requests_nonce'),
        'details_nonce' => wp_create_nonce('mlr_frontend_nonce'),
        'cycle_nonce' => wp_create_nonce('mlr_user_cycle_nonce'),
        'allowed_statuses' => [],
        'table_view' => 'default',
    ]);

    ob_start();
    ?>
    <?php if (isset($_GET['mlr_submitted']) && absint($_GET['mlr_submitted']) === 1) :
        $submitted_request_id = isset($_GET['request_id']) ? absint($_GET['request_id']) : 0;
        ?>
        <div class="mlr-applicant-submit-notice" style="margin: 0 0 18px; padding: 16px 18px; border-radius: 8px; background: #ecfdf3; border: 1px solid #86efac; color: #166534; font-weight: 700; line-height: 1.9;">
            <?php echo esc_html('درخواست شما با موفقیت ثبت شد.' . ($submitted_request_id ? ' کد پیگیری: #' . $submitted_request_id : '')); ?>
        </div>
    <?php endif; ?>
    <div class="mlr-glass-dashboard mlr-user-requests mlr-applicant-requests" id="<?php echo esc_attr(uniqid('mlr-applicant-dashboard-')); ?>">
        <div class="mlr-dashboard-wrapper">
            <h2 class="mlr-dashboard-title"><span class="dashicons dashicons-list-view"></span> پنل درخواست‌های متقاضی</h2>

            <div class="mlr-glass-card mlr-table-section">
                <div class="mlr-table-responsive-wrapper">
                    <table class="mlr-requests-table" id="mlr-requests-table" data-table-view="default">
                        <thead>
                            <tr>
                                <th>شناسه</th>
                                <th>متقاضی</th>
                                <th>شماره موبایل</th>
                                <th>کد ملی</th>
                                <th>مبلغ تسهیلات</th>
                                <th>مبلغ اعتبار خرید</th>
                                <th>طرح</th>
                                <th>تعداد اقساط</th>
                                <th>پذیرنده</th>
                                <th>وضعیت</th>
                                <th>تاریخ ثبت</th>
                                <th>عملیات</th>
                            </tr>
                        </thead>
                        <tbody id="mlr-requests-table-body" class="mlr-requests-table-body">
                            <tr><td colspan="12" style="text-align:center; padding:20px;">در حال بارگذاری درخواست‌ها...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
