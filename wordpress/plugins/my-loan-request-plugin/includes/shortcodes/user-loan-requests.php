<?php
if (!defined('ABSPATH')) {
    exit;
}

add_shortcode('mlr_user_loan_requests', 'mlr_user_loan_requests_shortcode');

function mlr_user_loan_requests_shortcode() {
    // This shortcode shows ALL requests, so we pass an empty array for statuses
    return mlr_render_user_requests_table('درخواست‌های تسهیلات شما', []);
}