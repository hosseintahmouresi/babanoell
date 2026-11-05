<?php
if (!defined('ABSPATH')) {
    exit;
}

add_action('wp_ajax_mlr_upload_additional_docs', 'mlr_upload_additional_docs_callback');

function mlr_upload_additional_docs_callback() {
    if (!function_exists('mlr_handle_additional_documents_submission')) {
        wp_send_json_error(['message' => 'سرویس بارگذاری مدارک در دسترس نیست.']);
    }
    mlr_handle_additional_documents_submission('acceptor');
}
