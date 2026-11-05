<?php
if (!defined('ABSPATH')) {
    exit;
}

add_action('wp_ajax_mlr_save_expert_notes', 'mlr_save_expert_notes_callback');

function mlr_save_expert_notes_callback() {
    // 1. Security Checks
    check_ajax_referer('mlr_ajax_nonce', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'شما دسترسی لازم برای این عملیات را ندارید.']);
        return;
    }

    // 2. Input Validation & Sanitization
    $request_id = isset($_POST['request_id']) ? intval($_POST['request_id']) : 0;
    $expert_note = isset($_POST['expert_note']) ? sanitize_textarea_field($_POST['expert_note']) : '';
    $bank_note = isset($_POST['bank_note']) ? sanitize_textarea_field($_POST['bank_note']) : '';

    if (!$request_id) {
        wp_send_json_error(['message' => 'شناسه درخواست نامعتبر است.']);
        return;
    }

    // 3. Database Update
    global $wpdb;
    $table_name = $wpdb->prefix . 'loan_requests';

    $updated = $wpdb->update(
        $table_name,
        [
            'expert_note' => $expert_note,
            'bank_note'   => $bank_note
        ],
        ['id' => $request_id]
    );

    if ($updated === false) {
        wp_send_json_error(['message' => 'خطایی در هنگام ذخیره یادداشت‌ها رخ داد.']);
        return;
    }

    // 4. Send Success Response
    wp_send_json_success(['message' => 'یادداشت‌ها با موفقیت ذخیره شدند.']);
}