<?php
if (!defined('ABSPATH')) {
    exit;
}

add_action('wp_ajax_mlr_acceptor_claim_applicant_request', 'mlr_acceptor_claim_applicant_request_callback');

function mlr_acceptor_claim_applicant_request_callback() {
    check_ajax_referer('mlr_acceptor_marketplace_nonce', 'nonce');

    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'برای پذیرش پرونده ابتدا وارد شوید.']);
    }

    $acceptor_id = get_current_user_id();
    if (!function_exists('mlr_user_has_role') || !mlr_user_has_role($acceptor_id, 'acceptor')) {
        wp_send_json_error(['message' => 'برای پذیرش پرونده باید با حساب پذیرنده وارد شوید.']);
    }

    $settings = function_exists('mlr_get_acceptor_marketplace_settings') ? mlr_get_acceptor_marketplace_settings() : [];
    if (empty($settings['enabled']) || empty($settings['allow_open_marketplace'])) {
        wp_send_json_error(['message' => 'بازار درخواست‌های آزاد در حال حاضر فعال نیست.']);
    }

    $request_id = isset($_POST['request_id']) ? absint($_POST['request_id']) : 0;
    if ($request_id <= 0) {
        wp_send_json_error(['message' => 'شناسه پرونده معتبر نیست.']);
    }

    global $wpdb;
    $table_requests = $wpdb->prefix . 'loan_requests';
    $request = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_requests} WHERE id = %d", $request_id));
    if (!$request) {
        wp_send_json_error(['message' => 'پرونده یافت نشد.']);
    }

    if ($request->request_origin !== 'applicant_portal' || $request->acceptor_assignment_mode !== 'open_acceptor_marketplace' || $request->marketplace_status !== 'open' || absint($request->acceptor_user_id) > 0) {
        wp_send_json_error(['message' => 'این پرونده لحظاتی قبل توسط پذیرنده دیگری پذیرفته شد.']);
    }

    if (function_exists('mlr_acceptor_matches_applicant_request')) {
        $match_result = mlr_acceptor_matches_applicant_request($acceptor_id, $request, true);
        if (empty($match_result['matched'])) {
            wp_send_json_error(['message' => $match_result['message'] ?: 'این پرونده با شرایط پذیرنده شما همخوانی ندارد.']);
        }
    }

    $now = current_time('mysql');
    $affected = $wpdb->query($wpdb->prepare(
        "UPDATE {$table_requests}
         SET acceptor_user_id = %d,
             user_id = %d,
             marketplace_status = %s,
             marketplace_claimed_by = %d,
             marketplace_claimed_at = %s,
             last_edited_at = %s
         WHERE id = %d
           AND request_origin = %s
           AND acceptor_assignment_mode = %s
           AND marketplace_status = %s
           AND (acceptor_user_id IS NULL OR acceptor_user_id = 0)",
        $acceptor_id,
        $acceptor_id,
        'claimed',
        $acceptor_id,
        $now,
        $now,
        $request_id,
        'applicant_portal',
        'open_acceptor_marketplace',
        'open'
    ));

    if ($affected !== 1) {
        wp_send_json_error(['message' => 'این پرونده لحظاتی قبل توسط پذیرنده دیگری پذیرفته شد.']);
    }

    if (function_exists('mlr_add_request_event')) {
        mlr_add_request_event($request_id, 'acceptor_claimed_applicant_request', 'پذیرنده پرونده متقاضی مستقیم را پذیرفت.');
    }

    if (function_exists('mlr_create_notification')) {
        $acceptor_name = function_exists('mlr_get_acceptor_display_name') ? mlr_get_acceptor_display_name($acceptor_id) : '';
        mlr_create_notification(
            'acceptor_claimed_applicant_request',
            'پرونده #' . $request_id . ' توسط پذیرنده ' . ($acceptor_name ?: $acceptor_id) . ' پذیرفته شد.',
            admin_url('admin.php?page=mlr-loan-requests&s=' . $request_id)
        );
    }

    wp_send_json_success([
        'request_id' => $request_id,
        'message' => 'پرونده با موفقیت توسط شما پذیرفته شد.',
    ]);
}
