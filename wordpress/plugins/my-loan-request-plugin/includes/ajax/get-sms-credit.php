<?php
if (!defined('ABSPATH')) {
    exit;
}

add_action('wp_ajax_mlr_get_sms_credit', 'mlr_get_sms_credit_callback');

function mlr_get_sms_credit_callback() {
    check_ajax_referer('mlr_ajax_nonce_action', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'شما دسترسی لازم را ندارید.']);
        return;
    }

    if (!class_exists('MLR_MeliPayamak_Provider')) {
         wp_send_json_error(['message' => 'کلاس ارائه‌دهنده پیامک یافت نشد.']);
        return;
    }

    $sms_provider = new MLR_MeliPayamak_Provider();
    $credit = $sms_provider->get_credit();

    if (is_wp_error($credit)) {
        wp_send_json_error(['message' => $credit->get_error_message()]);
    } else {
        // The credit is already formatted with commas, just send it
        wp_send_json_success(['credit' => $credit]);
    }
}