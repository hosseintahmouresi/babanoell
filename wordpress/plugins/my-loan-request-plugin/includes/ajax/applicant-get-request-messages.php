<?php
// FILE: my-loan-request-plugin/includes/ajax/get-request-messages.php
if (!defined('ABSPATH')) { exit; }

add_action('wp_ajax_mlr_applicant_get_request_messages', 'mlr_applicant_get_request_messages_callback');

function mlr_applicant_get_request_messages_callback() {
    if (!function_exists('mlr_verify_any_nonce') || !mlr_verify_any_nonce('nonce')) {
        wp_send_json_error(['message' => 'خطای امنیتی.']);
        return;
    }

    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'کاربر وارد نشده است.']);
        return;
    }

    $request_id = isset($_POST['request_id']) ? absint($_POST['request_id']) : 0;
    if (!$request_id) {
        wp_send_json_error(['message' => 'شناسه نامعتبر.']);
        return;
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'loan_requests';
    $req = $wpdb->get_row($wpdb->prepare("SELECT id,user_id,applicant_user_id,acceptor_user_id,request_origin,assigned_bank_user_id FROM {$table_name} WHERE id=%d", $request_id));
    if (!$req) {
        wp_send_json_error(['message' => 'درخواست یافت نشد.']);
        return;
    }

    if (!function_exists('mlr_is_applicant_origin_request') || !mlr_is_applicant_origin_request($req) || !function_exists('mlr_can_access_loan_request') || !mlr_can_access_loan_request($req)) {
        wp_send_json_error(['message' => 'دسترسی ندارید.']);
        return;
    }

    $current_user = wp_get_current_user();
    $uid = get_current_user_id();
    $is_admin = current_user_can('manage_options');
    $is_bank  = in_array('bank', (array)$current_user->roles, true);

    if (!$is_admin && !function_exists('mlr_can_access_loan_request')) {
        if ($is_bank) {
            if (intval($req->assigned_bank_user_id) !== $uid) {
                wp_send_json_error(['message' => 'دسترسی ندارید.']);
                return;
            }
        } else {
            if (intval($req->user_id) !== $uid) {
                wp_send_json_error(['message' => 'دسترسی ندارید.']);
                return;
            }
        }
    }

    if (!function_exists('mlr_get_request_messages')) {
        wp_send_json_success(['messages_html' => '']);
        return;
    }

    $msgs = mlr_get_request_messages($request_id, 500);
    $existing_comments_json = $wpdb->get_var($wpdb->prepare("SELECT comments FROM {$table_name} WHERE id = %d", $request_id));
    $comments = !empty($existing_comments_json) ? json_decode($existing_comments_json, true) : [];
    if (!is_array($comments)) { $comments = []; }
    $html = function_exists('mlr_render_request_messages_html') ? mlr_render_request_messages_html($msgs) : '';

    wp_send_json_success([
        'messages_html' => $html,
        'conversation_html' => function_exists('mlr_render_request_conversation_html') ? mlr_render_request_conversation_html($comments, $msgs) : ''
    ]);
}
