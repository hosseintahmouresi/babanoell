<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Calculates payment for a loan based on constant payments and a constant interest rate.
 * Fixed: Handles division by zero.
 */
function pmt($rate, $nper, $pv) {
    // Prevent division by zero if number of periods is 0 or negative
    if ($nper <= 0) {
        return 0;
    }
    
    // If rate is 0, simple division
    if ($rate == 0) {
        return -($pv / $nper);
    }

    // Calculate (1 + rate)^nper
    $pow = pow(1 + $rate, $nper);

    // Prevent division by zero in formula
    if (($pow - 1) == 0) {
        return 0;
    }

    return -($pv * $rate * $pow) / ($pow - 1);
}

/**
 * Calculates the present value of a loan.
 * Fixed: Handles division by zero.
 */
function pv($rate, $nper, $pmt) {
    if ($nper <= 0) return 0;

    if ($rate == 0) {
        return -($pmt * $nper);
    }
    
    $pow = pow(1 + $rate, -$nper);
    return -($pmt * (1 - $pow)) / $rate;
}


function calculateMarketLoan($loanAmount, $installments, $settings, $user_commission = null) {
    // Basic validation to prevent errors
    $loanAmount = floatval($loanAmount);
    $installments = intval($installments);

    if ($loanAmount <= 0 || $installments <= 0) {
        return []; // Return empty if inputs are invalid
    }

    $fixedMonthlyRate = floatval($settings['fixed_monthly_rate'] ?? 0) / 100;
    $prepaymentPercent = floatval($settings['prepayment_percent'] ?? 0) / 100;
    $supplierCostsPercent = floatval($settings['supplier_costs_percent'] ?? 0) / 100;
    $intermediaryDeductionsPercent = floatval($settings['intermediary_deductions_percent'] ?? 0) / 100;
    $babanovelDeductionsPercent = floatval($settings['babanovel_deductions_percent'] ?? 0) / 100;
    $insurancePercent = floatval($settings['insurance_percent'] ?? 0) / 100;
    $guaranteeCheckPercent = floatval($settings['guarantee_check_percent'] ?? 0) / 100;
    
    if ($user_commission !== null && is_numeric($user_commission)) {
        $acceptorCommissionPercent = floatval($user_commission) / 100;
    } else {
        $acceptorCommissionPercent = floatval($settings['acceptor_commission_percent'] ?? 0) / 100;
    }

    $additionalDeductionsPercent = floatval($settings['additional_deductions_percent'] ?? 0) / 100;
    $acceptorDeductionsAmount = floatval($settings['acceptor_deductions_amount'] ?? 0);
    $vatPercent = floatval($settings['vat_percent'] ?? 0) / 100;

    $calc_C12 = $loanAmount * $prepaymentPercent;
    $calc_F12 = ($loanAmount - $calc_C12) * $fixedMonthlyRate * $installments;
    $calc_D12 = ($loanAmount * (1 - $prepaymentPercent)) + $calc_F12;
    $calc_E12 = ($installments > 0) ? $calc_D12 / $installments : 0;
    $calc_G12 = $loanAmount * ($supplierCostsPercent + $intermediaryDeductionsPercent + $babanovelDeductionsPercent + $insurancePercent) * (1 + $vatPercent);
    $calc_H12 = $loanAmount - $calc_C12;
    $calc_I12 = ($loanAmount * (1 - ($intermediaryDeductionsPercent + $babanovelDeductionsPercent + $insurancePercent + $additionalDeductionsPercent + $prepaymentPercent + (($supplierCostsPercent + $acceptorCommissionPercent) * (1 + $vatPercent))))) - $acceptorDeductionsAmount;
    $calc_J12 = $loanAmount * $guaranteeCheckPercent;
    $calc_K12 = ($calc_D12 + $calc_C12) * $guaranteeCheckPercent;
    
    return [
        'loanAmount'              => round($loanAmount),
        'installmentCount'        => $installments,
        'prepayment'              => round($calc_C12),
        'totalPayment'            => round($calc_D12),
        'installmentAmount'       => round($calc_E12),
        'profit'                  => round($calc_F12),
        'operationCost'           => round($calc_G12),
        'walletCharge'            => round($calc_H12),
        'acceptorPayment'         => round($calc_I12),
        'guaranteeCheckPrincipal' => round($calc_J12),
        'guaranteeCheckTotal'     => round($calc_K12),
    ];
}

function calculateLeasingLoan($purchase_amount, $total_installments_by_user, $installment_interval_months, $settings, $user_commission = null) {
    // Basic validation to prevent division by zero
    $purchase_amount = floatval($purchase_amount);
    $total_installments_by_user = intval($total_installments_by_user);
    $installment_interval_months = intval($installment_interval_months);

    if ($purchase_amount <= 0 || $total_installments_by_user <= 0 || $installment_interval_months <= 0) {
        return [];
    }

    $nominal_interest_rate = floatval($settings['interest_rate'] ?? 0) / 100;
    $effective_interest_rate = floatval($settings['effective_interest_rate'] ?? 0) / 100;
    $loan_percentage = floatval($settings['credit_share_percent'] ?? 0) / 100;
    $supplier_vat_costs_percentage = floatval($settings['supplier_costs_percent'] ?? 0) / 100;
    $intermediary_deduction_percentage = floatval($settings['intermediary_deductions_percent'] ?? 0) / 100;
    $babanowell_deduction_percentage = floatval($settings['babanovel_deductions_percent'] ?? 0) / 100;
    $insurance_percentage = floatval($settings['insurance_percent'] ?? 0) / 100;
    $other_deductions_amount = floatval($settings['other_deductions_amount'] ?? 0);
    $guarantee_check_percentage = floatval($settings['guarantee_check_percent'] ?? 0) / 100;
    
    if ($user_commission !== null && is_numeric($user_commission)) {
        $merchant_commission_percentage = floatval($user_commission) / 100;
    } else {
        $merchant_commission_percentage = floatval($settings['acceptor_commission_percent'] ?? 0) / 100;
    }

    $extra_merchant_deduction_percentage = floatval($settings['additional_deductions_percent'] ?? 0) / 100;
    $extra_merchant_deduction_amount = floatval($settings['acceptor_deductions_amount'] ?? 0);
    $vat_percentage = floatval($settings['vat_percent'] ?? 0) / 100;

    $loan_amount = $purchase_amount * $loan_percentage;
    $installments_count_display = $total_installments_by_user;
    
    // Prevent division by zero
    $number_of_payments = ($installment_interval_months > 0) ? $total_installments_by_user / $installment_interval_months : 0;
    
    $down_payment = (1 - $loan_percentage) * $purchase_amount;
    
    $rate_per_period_nominal = ($nominal_interest_rate / 12) * $installment_interval_months;
    $installment_amount_raw = ($number_of_payments > 0) ? pmt($rate_per_period_nominal, $number_of_payments, -$loan_amount) : 0;
    
    // Using round to ensure integer values for display/db
    $installment_amount = round($installment_amount_raw);
    
    $total_payment_amount = $installment_amount * $number_of_payments;
    $total_interest = $total_payment_amount - $loan_amount;
    
    $guarantee_check_principal = $loan_amount * $guarantee_check_percentage;
    $guarantee_check_principal_and_interest = $total_payment_amount * $guarantee_check_percentage;
    
    $merchant_commission_base = $purchase_amount * $merchant_commission_percentage;
    $merchant_commission_with_vat = $merchant_commission_base * (1 + $vat_percentage);
    
    $rate_per_period_effective = ($effective_interest_rate / 12) * $installment_interval_months;
    
    $pmt_effective = ($number_of_payments > 0) ? pmt($rate_per_period_effective, $number_of_payments, -$loan_amount) : 0;
    $pmt_nominal = ($number_of_payments > 0) ? pmt($rate_per_period_nominal, $number_of_payments, -$loan_amount) : 0;
    
    $payment_difference = $pmt_effective - $pmt_nominal;
    $present_value_of_difference = ($number_of_payments > 0) ? pv($rate_per_period_effective, $number_of_payments, -$payment_difference) : 0;
    
    $operation_cost_percentage = ($loan_amount > 0) ? $present_value_of_difference / $loan_amount : 0;
    
    $leasing_operation_cost_amount = round(($loan_amount * ($operation_cost_percentage + $supplier_vat_costs_percentage + $insurance_percentage)) + $other_deductions_amount);
    $leasing_operation_cost_with_vat = $leasing_operation_cost_amount * (1 + $vat_percentage);
    
    $net_leasing_payment_to_platform = $loan_amount - $leasing_operation_cost_with_vat;
    $total_credit_deduction_percentage = $intermediary_deduction_percentage + $babanowell_deduction_percentage;
    
    $wallet_charge_amount = ((1 - $total_credit_deduction_percentage) * $loan_amount) - $leasing_operation_cost_with_vat;
    // Q12 = H12 - (H12*((N4*(100%+Q4))+O4)) - P4  (مبلغ واریزی برای پذیرنده)
    $net_payment_to_merchant = $wallet_charge_amount
        - ($wallet_charge_amount * (($merchant_commission_percentage * (1 + $vat_percentage)) + $extra_merchant_deduction_percentage))
        - $extra_merchant_deduction_amount;

    return [
        'loanAmount' => round($loan_amount),
        'installmentCount' => $installments_count_display,
        'numberOfInstallments' => $number_of_payments,
        'prepayment' => round($down_payment),
        'installmentAmount' => round($installment_amount),
        'totalPayment' => round($total_payment_amount),
        'profit' => round($total_interest),
        'walletCharge' => round($wallet_charge_amount),
        'guaranteeCheckPrincipal' => round($guarantee_check_principal),
        'guaranteeCheckTotal' => round($guarantee_check_principal_and_interest),
        'operationCostPercent' => $operation_cost_percentage,
        'operationCost' => round($leasing_operation_cost_amount),
        'operationCostWithVat' => round($leasing_operation_cost_with_vat),
        'netLeasingPayment' => round($net_leasing_payment_to_platform),
        'acceptorCommission' => round($merchant_commission_base),
        'acceptorCommissionWithVat' => round($merchant_commission_with_vat),
        'acceptorPayment' => round($net_payment_to_merchant),
    ];
}