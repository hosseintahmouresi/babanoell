<?php
if (!defined('ABSPATH')) {
    exit;
}

add_shortcode('mlr_granted_expert_dashboard', 'mlr_granted_expert_dashboard_shortcode');

function mlr_granted_expert_dashboard_shortcode() {
    // Check if user is logged in and has the 'bank' role
    if (!is_user_logged_in()) {
        return '<p>برای دسترسی به این بخش، لطفاً ابتدا وارد شوید.</p>';
    }

    $current_user = wp_get_current_user();
    if (!in_array('bank', (array) $current_user->roles)) {
        return '<p>شما دسترسی لازم برای مشاهده این صفحه را ندارید.</p>';
    }

    // Enqueue necessary scripts and styles
    wp_enqueue_style('dashicons');
    wp_enqueue_style('persian-datepicker-style', 'https://unpkg.com/persian-datepicker@1.2.0/dist/css/persian-datepicker.min.css');
    wp_enqueue_script('persian-date', 'https://unpkg.com/persian-date@1.1.0/dist/persian-date.min.js', [], '1.1.0', true);
    wp_enqueue_script('persian-datepicker-script', 'https://unpkg.com/persian-datepicker@1.2.0/dist/js/persian-datepicker.min.js', ['jquery', 'persian-date'], '1.2.0', true);
    
    wp_enqueue_style('mlr-frontend-css', MLR_PLUGIN_URL . 'assets/css/frontend.css', [], MLR_VERSION);
    // Use shared CSS
    wp_enqueue_style('mlr-expert-dashboard-css', MLR_PLUGIN_URL . 'assets/css/expert-dashboard.css', [], MLR_VERSION);

    // Use shared JS
    wp_enqueue_script('mlr-expert-dashboard-js', MLR_PLUGIN_URL . 'assets/js/expert-dashboard.js', ['jquery'], MLR_VERSION, true);
    wp_localize_script('mlr-expert-dashboard-js', 'mlr_expert_ajax', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('mlr_frontend_nonce'),
    ]);
    
    wp_enqueue_script('mlr-bank-settlement-cycles', MLR_PLUGIN_URL . 'assets/js/bank-settlement-cycles.js', ['jquery', 'mlr-expert-dashboard-js', 'persian-datepicker-script'], MLR_VERSION, true);

    $unique_id = uniqid('mlr-expert-dashboard-');

    // Render dashboard using the shared function
    $output = mlr_render_expert_dashboard_html([
        'id' => $unique_id,
        'title' => 'داشبورد تسهیلات اعطا شده',
        'icon' => 'dashicons-awards',
        'extra_classes' => 'mlr-expert-dashboard mlr-granted-settlement-dashboard',
        'columns' => [
            'انتخاب',
            'شناسه',
            'متقاضی',
            'شماره موبایل',
            'کد ملی',
            'مبلغ (ریال)',
            'طرح',
            'وضعیت',
            'تاریخ اعطا',
            'وضعیت تسویه بانک',
            'تاریخ تسویه بانک',
            'عملیات',
        ],
    ]);

    $config = [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('mlr_frontend_nonce'),
        'ajax_action' => 'mlr_get_granted_expert_requests',
        'export_action' => 'mlr_export_bank_expert_requests',
        'export_status' => 'اعطا شده',
        // Settlement details: full granted case view without conversations.
        // The bank settlement/date control is rendered at the top of the popup.
        'details_context' => 'bank_settlement',
        'column_count' => 12,
    ];

    ob_start();
    ?>
    <script>
    jQuery(document).ready(function($) {
        $('#<?php echo esc_js($unique_id); ?>').data('config', <?php echo wp_json_encode($config, JSON_UNESCAPED_UNICODE); ?>).data('bankCycle', <?php echo wp_json_encode(['ajax_url' => admin_url('admin-ajax.php'), 'nonce' => wp_create_nonce('mlr_frontend_nonce')]); ?>);
    });
    </script>
    <?php
    $script = ob_get_clean();
    $builder = '<section class="mlr-bank-cycle-builder" aria-label="ایجاد سیکل تسویه بانک">'
        . '<h3>ایجاد سیکل تسویه بانک با بابانوئل</h3>'
        . '<p>از جدول زیر پرونده‌های تسویه‌نشده را انتخاب کنید. این سیکل فقط تسویه بانک با بابانوئل را ثبت می‌کند و ارتباطی به سیکل تسویه پذیرندگان ندارد.</p>'
        . '<div class="mlr-bank-cycle-form"><label>تاریخ تسویه بانک (شمسی) <input type="text" class="mlr-bank-cycle-date" placeholder="۱۴۰۵/۰۷/۲۲" autocomplete="off" required></label>'
        . '<label>توضیحات اختیاری <textarea class="mlr-bank-cycle-note" rows="1" maxlength="3000" placeholder="شماره پیگیری یا توضیح مربوط به این سیکل"></textarea></label></div>'
        . '<div class="mlr-bank-cycle-actions"><strong>تعداد انتخاب‌شده: <span class="mlr-bank-cycle-selected-count">۰</span></strong>'
        . '<button type="button" class="mlr-btn mlr-btn-primary mlr-bank-cycle-submit" disabled>ایجاد سیکل تسویه بانک</button>'
        . '<button type="button" class="mlr-btn mlr-btn-secondary mlr-bank-cycle-clear">پاک‌کردن انتخاب‌ها</button></div>'
        . '<div class="mlr-bank-cycle-selected-ids" aria-live="polite"></div>'
        . '<div class="mlr-bank-cycle-message" aria-live="polite"></div></section>';
    // Insert above the list, keeping the existing filter/details modal unchanged.
    $output = str_replace('<div class="mlr-glass-card mlr-table-section">', $builder . '<div class="mlr-glass-card mlr-table-section">', $output);
    return $output . $script;
}