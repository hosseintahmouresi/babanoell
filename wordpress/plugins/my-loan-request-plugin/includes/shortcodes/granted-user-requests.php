<?php
if (!defined('ABSPATH')) {
    exit;
}

add_shortcode('mlr_granted_user_requests', 'mlr_granted_user_requests_shortcode');

function mlr_granted_user_requests_shortcode() {
    // Define the specific statuses for this table
    $granted_statuses = ['اعطا شده', 'تسویه شده', 'در انتظار تسویه'];
    
    // Call the central rendering function with the title and allowed statuses
    return mlr_render_user_requests_table('درخواست‌های اعطا شده و تسویه شده', $granted_statuses, 'granted');
}