<?php
if (!defined('ABSPATH')) {
    exit;
}

add_shortcode('mlr_settlement_cycles', 'mlr_user_settlement_cycles_shortcode');

function mlr_user_settlement_cycles_shortcode() {
    if (!is_user_logged_in()) {
        return '<p>برای مشاهده این بخش، لطفاً ابتدا وارد شوید.</p>';
    }

    $user_id = get_current_user_id();
    
    global $wpdb;
    $table_cycles = $wpdb->prefix . 'mlr_settlement_cycles';
    $user = get_userdata($user_id);

    // بررسی وجود جدول قبل از کوئری زدن
    if($wpdb->get_var("SHOW TABLES LIKE '$table_cycles'") != $table_cycles) {
        return '<p>خطا: جدول سیکل‌های تسویه یافت نشد. لطفاً با مدیر سایت تماس بگیرید.</p>';
    }

    $cycles = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table_cycles WHERE user_id = %d ORDER BY DATE(settlement_date) DESC, id DESC",
        $user_id
    ));

    $sheba_number = get_user_meta($user_id, 'shebaaa', true) ?: 'ثبت نشده';

    ob_start();
    ?>
    <div class="mlr-user-requests mlr-settlement-cycles">
        <h2>سیکل‌های تسویه حساب</h2>
        <div class="mlr-table-responsive-wrapper">
            <table class="mlr-requests-table">
                <thead>
                    <tr>
                        <th>ردیف</th>
                        <th>مبلغ تسویه (ریال)</th>
                        <th>تاریخ تسویه</th>
                        <th>پذیرنده</th>
                        <th>شماره شبا</th>
                        <th>توضیحات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($cycles)): ?>
                        <tr><td colspan="6" style="text-align: center; padding: 20px;">هیچ سیکل تسویه‌ای برای شما ثبت نشده است.</td></tr>
                    <?php else: ?>
                        <?php $counter = 1; ?>
                        <?php foreach ($cycles as $cycle): ?>
                            <tr>
                                <td data-label="ردیف"><?php echo number_format_i18n($counter++); ?></td>
                                <td data-label="مبلغ تسویه"><?php echo number_format($cycle->total_amount); ?></td>
                                <td data-label="تاریخ تسویه"><?php echo mlr_gregorian_to_jalali($cycle->settlement_date, 'Y/m/d'); ?></td>
                                <td data-label="پذیرنده"><?php echo esc_html($user->display_name); ?></td>
                                <td data-label="شماره شبا"><?php echo esc_html($sheba_number); ?></td>
                                <td data-label="توضیحات"><?php echo esc_html($cycle->description ?: '---'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php
    return ob_get_clean();
}