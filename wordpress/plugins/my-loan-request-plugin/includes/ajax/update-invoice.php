<?php
// AJAX handler for updating invoice
add_action('wp_ajax_mlr_update_invoice', 'mlr_update_invoice_callback');
function mlr_update_invoice_callback() {
    check_ajax_referer('mlr_ajax_nonce', 'nonce');

    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'کاربر وارد نشده است.']);
        return;
    }

    $request_id = isset($_POST['request_id']) ? absint($_POST['request_id']) : 0;
    if (!$request_id) {
        wp_send_json_error(['message' => 'شناسه درخواست نامعتبر است.']);
        return;
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'loan_requests';

    $request = $wpdb->get_row($wpdb->prepare("SELECT id, assigned_bank_user_id FROM {$table_name} WHERE id = %d", $request_id), ARRAY_A);
    if (!$request) {
        wp_send_json_error(['message' => 'درخواست یافت نشد.']);
        return;
    }

    $current_user = wp_get_current_user();
    $is_admin = current_user_can('manage_options');
    $is_bank = in_array('bank', (array) $current_user->roles, true);
    $is_assigned_bank_user = ($is_bank && (int) ($request['assigned_bank_user_id'] ?? 0) === (int) get_current_user_id());

    if (!$is_admin && !$is_assigned_bank_user) {
        wp_send_json_error(['message' => 'دسترسی غیرمجاز']);
        return;
    }

    $items_json = isset($_POST['items']) ? wp_unslash($_POST['items']) : '[]';
    $items = json_decode($items_json, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $items = json_decode(stripslashes($items_json), true);
    }

    if (json_last_error() !== JSON_ERROR_NONE || !is_array($items)) {
        wp_send_json_error(['message' => 'اطلاعات اقلام فاکتور نامعتبر است.']);
        return;
    }

    $cleaned_items = [];
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }

        // Keep the legacy order_qty key for callers that still use it, but also persist
        // the canonical quantity key used by the invoice renderer. The line total is
        // always recomputed server-side and never trusted from the browser.
        $order_qty = max(0, intval($item['order_qty'] ?? ($item['quantity'] ?? ($item['qty'] ?? 0))));
        $sent_qty = max(0, intval($item['sent_qty'] ?? 0));
        $returned_qty = max(0, intval($item['returned_qty'] ?? 0));
        $unit_price = function_exists('mlr_parse_decimal_number')
            ? mlr_parse_decimal_number($item['unit_price'] ?? ($item['price'] ?? 0))
            : floatval($item['unit_price'] ?? ($item['price'] ?? 0));

        if ($order_qty <= 0 || $unit_price < 0 || !is_finite((float) $unit_price)) {
            wp_send_json_error(['message' => 'تعداد یا مبلغ یکی از اقلام فاکتور نامعتبر است.']);
            return;
        }

        $line_total = round($order_qty * $unit_price, 2);
        $cleaned_items[] = [
            'code' => sanitize_text_field($item['code'] ?? ''),
            'description' => sanitize_text_field($item['description'] ?? ''),
            'quantity' => $order_qty,
            'order_qty' => $order_qty,
            'sent_qty' => $sent_qty,
            'returned_qty' => $returned_qty,
            'unit' => sanitize_text_field($item['unit'] ?? ''),
            'unit_price' => $unit_price,
            'total_price' => $line_total,
        ];
    }

    if (empty($cleaned_items)) {
        wp_send_json_error(['message' => 'اقلام فاکتور خالی است و برای جلوگیری از حذف اطلاعات قبلی ذخیره انجام نشد.']);
        return;
    }

    $updated = $wpdb->update(
        $table_name,
        [
            'order_items' => wp_json_encode($cleaned_items),
            'order_total' => array_sum(array_column($cleaned_items, 'total_price')),
        ],
        ['id' => $request_id],
        ['%s', '%f'],
        ['%d']
    );

    if ($updated === false) {
        wp_send_json_error(['message' => 'خطایی در هنگام ذخیره فاکتور رخ داد.']);
        return;
    }

    wp_send_json_success(['message' => 'تغییرات با موفقیت ذخیره شد.']);
}
