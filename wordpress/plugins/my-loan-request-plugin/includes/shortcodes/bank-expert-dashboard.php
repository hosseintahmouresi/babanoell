<?php
// FILE: my-loan-request-plugin/includes/shortcodes/bank-expert-dashboard.php
if (!defined('ABSPATH')) {
    exit;
}

add_shortcode('mlr_bank_expert_dashboard', 'mlr_bank_expert_dashboard_shortcode');

function mlr_bank_expert_dashboard_shortcode() {
    // Check if user is logged in and has the 'bank' role
    if (!is_user_logged_in()) {
        return '<p>برای دسترسی به این بخش، لطفاً ابتدا وارد شوید.</p>';
    }

    $current_user = wp_get_current_user();
    if (!in_array('bank', (array) $current_user->roles, true)) {
        return '<p>شما دسترسی لازم برای مشاهده این صفحه را ندارید.</p>';
    }

    // Enqueue necessary scripts and styles
    wp_enqueue_style('dashicons');
    
    // Persian Datepicker Assets (CDN)
    wp_enqueue_style('persian-datepicker-style', 'https://unpkg.com/persian-datepicker@1.2.0/dist/css/persian-datepicker.min.css');
    wp_enqueue_script('persian-date', 'https://unpkg.com/persian-date@1.1.0/dist/persian-date.min.js', [], '1.1.0', true);
    wp_enqueue_script('persian-datepicker-script', 'https://unpkg.com/persian-datepicker@1.2.0/dist/js/persian-datepicker.min.js', ['jquery', 'persian-date'], '1.2.0', true);
    
    // Enqueue the main frontend CSS
    wp_enqueue_style('mlr-frontend-css', MLR_PLUGIN_URL . 'assets/css/frontend.css', [], MLR_VERSION);
    // Enqueue the shared CSS for expert dashboards
    wp_enqueue_style('mlr-expert-dashboard-css', MLR_PLUGIN_URL . 'assets/css/expert-dashboard.css', [], MLR_VERSION);

    // Enqueue the shared JS for expert dashboards
    wp_enqueue_script('mlr-expert-dashboard-js', MLR_PLUGIN_URL . 'assets/js/expert-dashboard.js', ['jquery'], MLR_VERSION, true);
    
    // Localize script for AJAX (Global fallback)
    wp_localize_script('mlr-expert-dashboard-js', 'mlr_expert_ajax', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('mlr_frontend_nonce'),
    ]);
    
    $unique_id = uniqid('mlr-expert-dashboard-');

    // Render dashboard using the shared function
    $output = mlr_render_expert_dashboard_html([
        'id' => $unique_id,
        'title' => 'داشبورد کارشناس بانک',
        'icon' => 'dashicons-businessman',
        'extra_classes' => 'mlr-expert-dashboard', // Marker class
    ]);

    // Append configuration data for this specific instance
    $config = [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('mlr_frontend_nonce'),
        'ajax_action' => 'mlr_get_bank_expert_requests',
        'export_action' => 'mlr_export_bank_expert_requests',
        'export_status' => 'تأیید کارشناس',
        'column_count' => 9
    ];
    
    ob_start();
    ?>
    <script>
    jQuery(document).ready(function($) {
        $('#<?php echo esc_js($unique_id); ?>').data('config', <?php echo wp_json_encode($config, JSON_UNESCAPED_UNICODE); ?>);
    });
    </script>
    <?php
    $output .= ob_get_clean();

    return $output;
}
?>