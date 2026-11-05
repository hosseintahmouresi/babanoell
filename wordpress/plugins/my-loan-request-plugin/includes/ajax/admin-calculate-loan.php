<?php
if (!defined('ABSPATH')) {
    exit;
}

add_action('wp_ajax_mlr_admin_calculate_loan', 'mlr_admin_calculate_loan_callback');

function mlr_admin_calculate_loan_callback() {
    try {
        // --- START: اصلاح نانس ---
        check_ajax_referer('mlr_ajax_nonce_action', 'nonce'); // استفاده از نانس صحیح ادمین
        // --- END: اصلاح نانس ---

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'دسترسی غیرمجاز.']);
        }

        $plan_id = isset($_POST['plan_id']) ? intval($_POST['plan_id']) : 0;
        if (!$plan_id) {
            wp_send_json_error(['message' => 'لطفاً یک طرح را انتخاب کنید.']);
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'installment_plans';
        $plan = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $plan_id));

        if (!$plan) {
            wp_send_json_error(['message' => 'طرح انتخاب شده یافت نشد.']);
        }

        $settings = maybe_unserialize($plan->settings);
        if (!is_array($settings)) $settings = [];
        // در محاسبه‌گر زنده بک‌اند همه فیلدهای محاسبه‌شده بدون محدودیت نمایش داده می‌شوند (display_fields نادیده گرفته می‌شود)
        $display_fields = []; // خالی = نمایش همه

        $amount = function_exists('mlr_parse_decimal_number') ? mlr_parse_decimal_number($_POST['amount'] ?? 0) : (isset($_POST['amount']) ? floatval(str_replace(',', '', $_POST['amount'])) : 0);
        $installments = absint($_POST['installments'] ?? 0);
        $installment_interval = max(1, absint($_POST['installment_interval'] ?? 1));

        // Admin may simulate inactive plans, but the same numeric/business rules still apply.
        $validation_plan = clone $plan;
        $validation_plan->is_active = 1;
        $plan_validation = function_exists('mlr_validate_plan_request_values') ? mlr_validate_plan_request_values($validation_plan, $amount, $installments, $installment_interval) : null;
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
        
        // --- START: Get user commission (relevant if admin wants to simulate) ---
        // For admin calculator, we usually don't need user-specific commission.
        // We can pass null or 0 to use the plan's default commission.
        $user_commission = null; // Or fetch logged-in user's commission if needed for simulation
        // --- END: Get user commission ---

        $results = [];

        if ($plan->type === 'market') {
            $results = calculateMarketLoan($amount, $installments, $settings, $user_commission);
        } elseif ($plan->type === 'leasing') {
            $results = calculateLeasingLoan($amount, $installments, $installment_interval, $settings, $user_commission);
        }

        if (empty($results)) {
            wp_send_json_error(['message' => 'نوع طرح نامعتبر است یا محاسبات انجام نشد.']);
        }

        $labels_with_units = mlr_get_calculation_labels(); // Get the labels array including units
        
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
        echo '<h4>نتایج محاسبه:</h4>';
        echo '<table class="widefat striped">';
        foreach ($results as $key => $value) {
            $display_key = isset($key_map[$key]) ? $key_map[$key] : $key;

            // محاسبه‌گر زنده بک‌اند: نمایش همه فیلدها (display_fields خالی است)
            // --- START: اصلاح نحوه دسترسی به لیبل و واحد ---
            $label_info = $labels_with_units[$display_key] ?? ($labels_with_units[$key] ?? ['label' => ucwords(str_replace('_', ' ', $key)), 'unit' => '']);
            $label = $label_info['label'];
            $unit = $label_info['unit'];
            
            // Format value based on unit
            $formatted_value = '';
            if ($unit === 'درصد') {
                // Assuming value is a decimal like 0.23 for 23%
                $formatted_value = number_format_i18n($value * 100, 2) . ' ' . $unit; 
            } else {
                $formatted_value = number_format_i18n(round($value)) . ' ' . $unit;
            }
            // --- END: اصلاح نحوه دسترسی به لیبل و واحد ---

            echo '<tr>';
            echo '<td><strong>' . esc_html($label) . '</strong></td>';
            echo '<td>' . esc_html($formatted_value) . '</td>';
            echo '</tr>';
        }
        echo '</table>';
        $html = ob_get_clean();

        // Check if any fields were actually displayed
         if (empty(trim(strip_tags($html)))) {
            $html = '<p>هیچ فیلدی برای نمایش در محاسبه‌گر این طرح تنظیم نشده است.</p>';
        }

        wp_send_json_success(['html' => $html]);

    } catch (Exception $e) {
        mlr_log('Admin Calculator Error: ' . $e->getMessage()); // Log error
        wp_send_json_error(['message' => 'خطای سرور: ' . $e->getMessage()]);
    }
}