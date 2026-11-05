<?php
if (!defined('ABSPATH')) {
    exit;
}

add_shortcode('mlr_user_settlement_cycles', 'mlr_user_settlement_cycles_shortcode');

function mlr_user_settlement_cycles_shortcode() {
    if (!is_user_logged_in()) {
        return '<p>برای مشاهده این بخش، لطفاً ابتدا وارد شوید.</p>';
    }

    $user_id = get_current_user_id();
    
    global $wpdb;
    $table_cycles = $wpdb->prefix . 'mlr_settlement_cycles';
    $user = get_userdata($user_id);

    if($wpdb->get_var("SHOW TABLES LIKE '$table_cycles'") != $table_cycles) {
        return '<p>خطای سیستمی: جدول مورد نیاز یافت نشد. لطفاً افزونه را یکبار غیرفعال و سپس فعال کنید.</p>';
    }

    $cycles = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table_cycles WHERE user_id = %d ORDER BY DATE(settlement_date) DESC, id DESC",
        $user_id
    ));

    $sheba_number = get_user_meta($user_id, 'shebaaa', true) ?: 'ثبت نشده';

    wp_enqueue_style('dashicons');
    wp_enqueue_style('mlr-frontend-css', MLR_PLUGIN_URL . 'assets/css/frontend.css', [], '6.8.0');
    wp_enqueue_script('mlr-user-requests-js', MLR_PLUGIN_URL . 'assets/js/mlr-user-requests.js', ['jquery'], '6.8.0', true);
    wp_localize_script('mlr-user-requests-js', 'mlr_ajax_obj', [
        'ajax_url'      => admin_url('admin-ajax.php'),
        'details_nonce' => wp_create_nonce('mlr_frontend_nonce'),
        'cycle_nonce'   => wp_create_nonce('mlr_user_cycle_nonce')
    ]);

    ob_start();
    ?>
    <div class="mlr-user-requests mlr-settlement-cycles">
        <div class="mlr-table-responsive-wrapper">
            <table class="mlr-requests-table">
                <thead>
                    <tr>
                        <th style="width: 5%;">ردیف</th>
                        <th>مبلغ تسویه تجمیعی (ریال)</th>
                        <th>تاریخ تسویه تجمیعی</th>
                        <th>پذیرنده</th>
                        <th>شماره شبا</th>
                        <th>توضیحات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($cycles)): ?>
                        <tr><td colspan="6" style="text-align: center; padding: 20px;">هیچ سیکل تسویه‌ای برای شما ثبت نشده است.</td></tr>
                    <?php else: ?>
                        <?php $counter = count($cycles); ?>
                        <?php foreach ($cycles as $cycle): ?>
                            <tr class="mlr-cycle-row-frontend" data-cycle-id="<?php echo esc_attr($cycle->id); ?>" style="cursor: pointer;">
                                <td data-label="ردیف"><?php echo number_format_i18n($counter--); ?></td>
                                <td data-label="مبلغ تسویه تجمیعی (ریال)"><?php echo number_format($cycle->total_amount); ?></td>
                                <td data-label="تاریخ تسویه تجمیعی"><?php echo mlr_gregorian_to_jalali($cycle->settlement_date, 'Y/m/d'); ?></td>
                                <td data-label="پذیرنده"><?php echo esc_html($user->display_name); ?></td>
                                <td data-label="شماره شبا"><?php echo esc_html($sheba_number); ?></td>
                                <td data-label="توضیحات"><?php echo esc_html($cycle->description ?: '---'); ?></td>
                            </tr>
                            <tr class="mlr-cycle-details-row-frontend" id="cycle-details-frontend-<?php echo esc_attr($cycle->id); ?>" style="display: none;">
                                <td colspan="6" class="cycle-details-container">
                                    <div class="spinner-container" style="text-align:center; padding: 20px;">
                                        <span class="spinner is-active"></span>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <div class="mlr-modal-overlay" style="display:none;"></div>
        <div class="mlr-modal" style="display:none;">
            <button class="mlr-modal-close">&times;</button>
            <div class="mlr-modal-content"></div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}