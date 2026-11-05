<?php
add_action('wp_ajax_mlr_get_user_actions', 'mlr_get_user_actions_callback');
function mlr_get_user_actions_callback() {
    check_ajax_referer('mlr_ajax_nonce_action', 'nonce');
    global $wpdb;
    $table_name = $wpdb->prefix . 'loan_requests';
    $request_id = intval($_POST['request_id']);
    $user_id = get_current_user_id();

    $request = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $request_id));
    if ($request && function_exists('mlr_can_access_loan_request') && !mlr_can_access_loan_request($request)) {
        $request = null;
    } elseif ($request && !function_exists('mlr_can_access_loan_request') && (int) $request->user_id !== (int) $user_id) {
        $request = null;
    }
    if ($request) {
        $actions = '';

        // Details button
        $actions .= '<button class="mlr-details-btn" data-id="' . esc_attr($request->id) . '">جزئیات</button>';

        // Edit option for "نقص مدارک" status
        if ($request->status === 'نقص مدارک') {
            $edit_url = add_query_arg([
                'mlr_edit' => $request->id,
                'nonce' => wp_create_nonce('mlr_edit_' . $request->id)
            ], home_url('/edit-request/'));
            $actions .= '<a href="' . esc_url($edit_url) . '" class="button">ویرایش</a>';
        }

        $canonical_status = function_exists('mlr_normalize_status') ? mlr_normalize_status($request->status) : $request->status;

        // Backward-compatible completion action for the legacy document page.
        if ($canonical_status === 'تکمیل مدارک' || ($canonical_status === 'تأیید مدارک' && !$request->order_items)) {
            $complete_url = add_query_arg([
                'mlr_complete' => $request->id,
                'nonce' => wp_create_nonce('mlr_complete_' . $request->id)
            ], home_url('/complete-request/'));
            $actions .= '<a href="' . esc_url($complete_url) . '" class="button">تکمیل درخواست</a>';
        }

        // Invoice option for "تأیید نهایی" with order items
        if ($canonical_status === 'تأیید مدارک' && $request->order_items) {
            $invoice_url = add_query_arg([
                'mlr_invoice' => $request->id,
                'nonce' => wp_create_nonce('mlr_invoice_' . $request->id)
            ], home_url('/invoice/'));
            $actions .= '<a href="' . esc_url($invoice_url) . '" target="_blank" class="button">پرینت فاکتور</a>';
        }

        // Show comments if available for "نقص مدارک"
        if ($request->status === 'نقص مدارک' && $request->comments) {
            $actions .= '<p>توضیحات: ' . esc_html($request->comments) . '</p>';
        }

        wp_send_json_success($actions);
    } else {
        wp_send_json_error('درخواست یافت نشد.');
    }
}
?>
