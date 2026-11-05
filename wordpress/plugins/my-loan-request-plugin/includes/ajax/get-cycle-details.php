<?php
if (!defined('ABSPATH')) {
    exit;
}

add_action('wp_ajax_mlr_get_cycle_details', 'mlr_get_cycle_details_callback');

function mlr_get_cycle_details_callback() {
    // Using the standard, secure way to check nonces in admin-ajax calls.
    // The action 'mlr_ajax_nonce_action' must match what's in wp_localize_script.
    check_ajax_referer('mlr_ajax_nonce_action', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'شما دسترسی لازم برای این عملیات را ندارید.']);
        return;
    }

    $cycle_id = isset($_POST['cycle_id']) ? intval($_POST['cycle_id']) : 0;
    if (!$cycle_id) {
        wp_send_json_error(['message' => 'شناسه سیکل نامعتبر است.']);
        return;
    }

    global $wpdb;
    $table_requests = $wpdb->prefix . 'loan_requests';
    $plans_table = $wpdb->prefix . 'installment_plans';

    $requests = $wpdb->get_results($wpdb->prepare(
        "SELECT
            r.id,
            r.full_name,
            r.national_code,
            r.loan_amount,
            r.facility_amount,
            r.installment_count,
            r.final_settlement_amount,
            r.acceptor_payment_amount,
            r.settlement_date,
            p.name AS plan_name
         FROM {$table_requests} r
         LEFT JOIN {$plans_table} p ON r.installment_plan_id = p.id
         WHERE r.settlement_cycle_id = %d 
         ORDER BY r.id ASC",
        $cycle_id
    ));

    if (empty($requests)) {
        wp_send_json_error(['message' => 'هیچ درخواستی در این سیکل یافت نشد.']);
        return;
    }

    ob_start();
    ?>
    <div class="mlr-uni-wrapper">
    <div class="mlr-uni-body" style="padding:15px;">
    <table class="mlr-uni-table">
        <thead>
            <tr>
                <th>شناسه درخواست</th>
                <th>نام متقاضی</th>
                <th>کد ملی</th>
                <th>نام طرح</th>
                <th>تعداد اقساط</th>
                <th>مبلغ پرداختی لیزینگ (ریال)</th>
                <th>مبلغ واریزی پذیرنده (ریال)</th>
                <th>تاریخ تسویه</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($requests as $request): 
                $facility_val = (isset($request->facility_amount) && $request->facility_amount !== '' && $request->facility_amount !== null) ? (float)$request->facility_amount : (float)$request->loan_amount;
                $acceptor_amount = (isset($request->final_settlement_amount) && $request->final_settlement_amount !== '' && $request->final_settlement_amount !== null)
                    ? (float) $request->final_settlement_amount
                    : ((isset($request->acceptor_payment_amount) && $request->acceptor_payment_amount !== '' && $request->acceptor_payment_amount !== null) ? (float) $request->acceptor_payment_amount : 0);
            ?>
                <tr class="mlr-request-row" data-id="<?php echo esc_attr($request->id); ?>" style="cursor:pointer;">
                    <td><?php echo esc_html($request->id); ?></td>
                    <td><strong><?php echo esc_html($request->full_name); ?></strong></td>
                    <td><?php echo esc_html($request->national_code); ?></td>
                    <td><?php echo esc_html($request->plan_name ?: '—'); ?></td>
                    <td><?php echo esc_html(isset($request->installment_count) && $request->installment_count !== '' ? $request->installment_count : '—'); ?></td>
                    <td><?php echo number_format($facility_val); ?></td>
                    <td><?php echo number_format($acceptor_amount); ?></td>
                    <td><?php echo !empty($request->settlement_date) ? (function_exists('mlr_gregorian_to_jalali') ? mlr_gregorian_to_jalali($request->settlement_date, 'Y/m/d') : $request->settlement_date) : '—'; ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    </div>
    <?php
    $html_output = ob_get_clean();

    wp_send_json_success(['html' => $html_output]);
}
