<?php
if (!defined('ABSPATH')) {
    exit;
}

add_action('wp_ajax_mlr_get_user_cycle_details', 'mlr_get_user_cycle_details_callback');

function mlr_get_user_cycle_details_callback() {
    check_ajax_referer('mlr_user_cycle_nonce', 'nonce');

    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'لطفاً ابتدا وارد شوید.']);
        return;
    }

    $cycle_id = isset($_POST['cycle_id']) ? intval($_POST['cycle_id']) : 0;
    if (!$cycle_id) {
        wp_send_json_error(['message' => 'شناسه سیکل نامعتبر است.']);
        return;
    }

    global $wpdb;
    $table_requests = $wpdb->prefix . 'loan_requests';
    $user_id = get_current_user_id();
    $effective_acceptor_sql = function_exists('mlr_get_request_effective_acceptor_sql')
        ? mlr_get_request_effective_acceptor_sql('r')
        : 'COALESCE(r.acceptor_user_id, r.user_id)';

    // A claimed applicant-origin request keeps the applicant in user_id and stores the merchant
    // in acceptor_user_id. Filter by the effective acceptor so both legacy acceptor-origin rows
    // and claimed applicant-origin rows appear in the same settlement cycle details.
    $requests = $wpdb->get_results($wpdb->prepare(
        "SELECT r.id, r.full_name, r.national_code, r.loan_amount, r.facility_amount, r.installment_interval, r.final_settlement_amount, r.wallet_charge, r.settlement_date
         FROM {$table_requests} r
         WHERE r.settlement_cycle_id = %d AND {$effective_acceptor_sql} = %d
         ORDER BY r.id ASC",
        $cycle_id,
        $user_id
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
                <th>شناسه</th>
                <th>نام متقاضی</th>
                <th>کد ملی متقاضی</th>
                <th>مبلغ درخواستی (ریال)</th>
                <th>مبلغ تسهیلات (ریال)</th>
                <th>فاصله اقساط</th>
                <th>اعتبار خرید (ریال)</th>
                <th>مبلغ تسویه (ریال)</th>
                <th>تاریخ تسویه</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($requests as $request): 
                $facility_val = (isset($request->facility_amount) && $request->facility_amount !== '' && $request->facility_amount !== null) ? (float)$request->facility_amount : (float)$request->loan_amount;
                $interval_display = isset($request->installment_interval) && $request->installment_interval !== '' && $request->installment_interval !== null ? $request->installment_interval . ' ماه' : '—';
            ?>
                <tr class="mlr-request-row-frontend" data-id="<?php echo esc_attr($request->id); ?>" style="cursor:pointer;">
                    <td><?php echo esc_html($request->id); ?></td>
                    <td><?php echo esc_html($request->full_name); ?></td>
                    <td><?php echo esc_html($request->national_code); ?></td>
                    <td><?php echo number_format($request->loan_amount); ?></td>
                    <td><?php echo number_format($facility_val); ?></td>
                    <td><?php echo esc_html($interval_display); ?></td>
                    <td><?php echo number_format($request->wallet_charge); ?></td>
                    <td><?php echo number_format($request->final_settlement_amount); ?></td>
                    <td><?php echo mlr_gregorian_to_jalali($request->settlement_date, 'Y/m/d'); ?></td>
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