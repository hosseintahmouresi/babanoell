<?php
if (!defined('ABSPATH')) {
    exit;
}

add_action('wp_ajax_mlr_applicant_submit_completed_docs', 'mlr_applicant_submit_completed_docs_callback');

function mlr_applicant_submit_completed_docs_callback() {
    if (!function_exists('mlr_handle_completed_documents_submission')) {
        wp_send_json_error(['message' => 'سرویس بارگذاری مدارک در دسترس نیست.']);
    }
    mlr_handle_completed_documents_submission('applicant');
}
