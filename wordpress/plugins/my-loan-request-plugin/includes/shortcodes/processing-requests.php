<?php
if (!defined('ABSPATH')) {
    exit;
}

add_shortcode('mlr_processing_requests', 'mlr_processing_requests_shortcode');

function mlr_processing_requests_shortcode() {
    // Define the specific statuses for this table
    $processing_statuses = ['تکمیل مدارک', 'تأیید مدارک'];
    
    // Call the central rendering function with the title and allowed statuses
    return mlr_render_user_requests_table('درخواست‌های در حال پردازش', $processing_statuses);
}