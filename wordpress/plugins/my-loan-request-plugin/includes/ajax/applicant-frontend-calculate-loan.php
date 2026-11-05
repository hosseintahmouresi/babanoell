<?php
if (!defined('ABSPATH')) {
    exit;
}

add_action('wp_ajax_mlr_applicant_frontend_calculate_loan', 'mlr_applicant_frontend_calculate_loan_callback');

function mlr_applicant_frontend_calculate_loan_callback() {
    try {
        check_ajax_referer('mlr_applicant_frontend_calc_nonce', 'nonce');
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'برای محاسبه، ابتدا وارد شوید.']);
        }

        $plan_id = isset($_POST['plan_id']) ? intval($_POST['plan_id']) : 0;
        if (!$plan_id) {
            wp_send_json_error(['message' => 'لطفاً یک طرح را انتخاب کنید.']);
        }

        if (!function_exists('mlr_applicant_can_access_installment_plan') || !mlr_applicant_can_access_installment_plan($plan_id, true)) {
            wp_send_json_error(['message' => 'طرح انتخاب‌شده برای حساب شما در دسترس نیست.']);
        }

        if (!function_exists('calculateMarketLoan') || !function_exists('calculateLeasingLoan')) {
            if (file_exists(MLR_PLUGIN_DIR . 'includes/loan-calculations.php')) {
                require_once MLR_PLUGIN_DIR . 'includes/loan-calculations.php';
            }
        }
        if (!function_exists('calculateMarketLoan') || !function_exists('calculateLeasingLoan')) {
            wp_send_json_error(['message' => 'ماژول محاسبات در دسترس نیست.']);
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'installment_plans';
        $plan = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d AND is_active = 1", $plan_id));

        if (!$plan) {
            wp_send_json_error(['message' => 'طرح انتخاب شده یافت نشد یا غیرفعال است.']);
        }

        $settings = maybe_unserialize($plan->settings);
        if (!is_array($settings)) {
            $settings = [];
        }
        $display_fields = maybe_unserialize(isset($plan->display_fields) ? $plan->display_fields : null);
        if (!is_array($display_fields)) {
            $display_fields = [];
        }
        $display_fields_context = maybe_unserialize(isset($plan->display_fields_context) ? $plan->display_fields_context : null);
        if (!is_array($display_fields_context)) {
            $display_fields_context = [];
        }

        $context = 'mlr_applicant_loan_request_form';
        if (isset($display_fields_context[$context]) && is_array($display_fields_context[$context]) && !empty($display_fields_context[$context])) {
            $display_fields = $display_fields_context[$context];
        }

        $amount = function_exists('mlr_parse_decimal_number') ? mlr_parse_decimal_number($_POST['amount'] ?? 0) : (isset($_POST['amount']) ? floatval(str_replace([',', '٬'], '', (string) $_POST['amount'])) : 0);
        $installments = isset($_POST['installments']) ? intval($_POST['installments']) : 0;

        $user_commission = null;

        $installment_interval = isset($_POST['installment_interval']) ? max(1, absint($_POST['installment_interval'])) : 1;
        $plan_validation = function_exists('mlr_validate_plan_request_values')
            ? mlr_validate_plan_request_values($plan, $amount, $installments, $installment_interval)
            : null;
        if (is_wp_error($plan_validation)) {
            wp_send_json_error(['message' => $plan_validation->get_error_message()]);
        }
        if ($amount <= 0 || $installments <= 0) {
            wp_send_json_error(['message' => 'مبلغ و تعداد اقساط باید معتبر باشند.']);
        }
        if (is_array($plan_validation)) {
            $settings = $plan_validation['settings'];
            $installment_interval = (int) $plan_validation['interval'];
        }

        $results = [];
        if ($plan->type === 'market') {
            $results = calculateMarketLoan($amount, $installments, $settings, $user_commission);
        } elseif ($plan->type === 'leasing') {
            $results = calculateLeasingLoan($amount, $installments, $installment_interval, $settings, $user_commission);
        }

        if (empty($results) || !is_array($results)) {
            wp_send_json_error(['message' => 'نوع طرح نامعتبر است یا محاسبات انجام نشد.']);
        }

        if (!function_exists('mlr_get_calculation_labels')) {
            wp_send_json_error(['message' => 'تابع برچسب‌ها در دسترس نیست.']);
        }
        $labels_with_units = mlr_get_calculation_labels();
        
        if (isset($labels_with_units['operationCostWithVat'])) {
            $labels_with_units['operationCostWithVat']['label'] = 'هزینه خدمات';
        }
        
        // نگاشت کلیدهای اسنیک‌کیس به کمل‌کیس
        $key_map = [
            'loan_amount' => 'loanAmount',
            'installment_count' => 'installmentCount',
            'prepayment' => 'prepayment',
            'total_payment' => 'totalPayment',
            'installment_amount' => 'installmentAmount',
            'profit' => 'profit',
            'operation_cost' => 'operationCost',
            'wallet_charge' => 'walletCharge',
            'acceptor_payment' => 'acceptorPayment',
            'guarantee_check_principal' => 'guaranteeCheckPrincipal',
            'guarantee_check_total' => 'guaranteeCheckTotal',
            'number_of_installments' => 'numberOfInstallments',
            'operation_cost_percent' => 'operationCostPercent',
            'operation_cost_with_vat' => 'operationCostWithVat',
            'net_leasing_payment' => 'netLeasingPayment',
            'acceptor_commission' => 'acceptorCommission',
            'acceptor_commission_with_vat' => 'acceptorCommissionWithVat',
        ];

        ob_start();
        echo '<table>';
        foreach ($results as $key => $value) {
            $display_key = isset($key_map[$key]) ? $key_map[$key] : $key;

            if (empty($display_fields) || (is_array($display_fields) && (in_array($display_key, $display_fields) || in_array($key, $display_fields)))) {
                $label_info = $labels_with_units[$display_key] ?? ($labels_with_units[$key] ?? ['label' => ucwords(str_replace('_', ' ', $key)), 'unit' => '']);
                $label = $label_info['label'];
                $unit = $label_info['unit'];
                $formatted_value = '';

                if ($unit === 'درصد') {
                    $formatted_value = number_format($value * 100, 3) . ' ' . $unit;
                } else {
                    $formatted_value = number_format(round($value)) . ' ' . $unit;
                }
                
                echo '<tr>';
                echo '<td><strong>' . esc_html($label) . '</strong></td>';
                echo '<td>' . esc_html($formatted_value) . '</td>';
                echo '</tr>';
            }
        }
        echo '</table>';
        $html = ob_get_clean();

        if (empty(trim(strip_tags($html)))) {
            $html = '<p>هیچ فیلدی برای نمایش در این طرح تنظیم نشده است. لطفاً با مدیر سایت تماس بگیرید.</p>';
        }

        wp_send_json_success(['html' => $html]);

    } catch (Exception $e) {
        if (function_exists('mlr_log')) mlr_log('Frontend calculator exception.', ['error' => $e->getMessage()]);
        wp_send_json_error(['message' => 'خطای داخلی سرور.']);
    }
}
