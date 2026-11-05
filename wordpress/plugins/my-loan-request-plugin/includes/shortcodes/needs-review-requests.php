<?php
if (!defined('ABSPATH')) {
    exit;
}

add_shortcode('mlr_needs_review_requests', 'mlr_needs_review_requests_shortcode');

function mlr_needs_review_requests_shortcode() {
    // Define the specific status for this table
    $needs_review_statuses = ['نقص مدارک'];

    // Call the central rendering function
    return mlr_render_user_requests_table('درخواست‌های نیازمند بازبینی', $needs_review_statuses);
}