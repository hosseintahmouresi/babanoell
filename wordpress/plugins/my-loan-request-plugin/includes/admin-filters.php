<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Build WHERE clauses and prepare params for admin loan request lists (administrator only).
 * Used by: loan-requests page, granted-requests page, Excel export, PDF export.
 *
 * @param string $table_alias Table alias for loan_requests (e.g. 'r').
 * @param array  $extra_where Optional extra AND conditions (e.g. status = 'اعطا شده' AND settlement_date IS NULL).
 * @return array [ 'where' => string, 'params' => array ]
 */
function mlr_build_admin_request_filters($table_alias = 'r', $extra_where = []) {
    global $wpdb;
    $where_clauses = array_merge(['1=1'], $extra_where);
    $prepare_params = [];

    $search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
    $status_filter = isset($_GET['status']) ? sanitize_text_field(wp_unslash($_GET['status'])) : '';
    $settlement_status = isset($_GET['settlement_status']) ? sanitize_text_field(wp_unslash($_GET['settlement_status'])) : '';
    $document_receipt_status = isset($_GET['document_receipt_status']) ? sanitize_text_field(wp_unslash($_GET['document_receipt_status'])) : '';
    $plan_id = isset($_GET['plan_id']) ? absint($_GET['plan_id']) : 0;
    $installment_count_val = 0;
    $ic_raw = isset($_GET['installment_count']) ? $_GET['installment_count'] : '';
    if ($ic_raw !== '' && $ic_raw !== null && $ic_raw !== '0') {
        if (is_numeric($ic_raw)) {
            $n = (int) $ic_raw;
            if ($n > 0) {
                $installment_count_val = $n;
            }
        }
    }
    $acceptor_id = isset($_GET['acceptor_id']) ? absint($_GET['acceptor_id']) : 0;
    $acceptor_search = isset($_GET['acceptor_search']) ? sanitize_text_field(wp_unslash($_GET['acceptor_search'])) : '';
    $bank_expert_id = isset($_GET['bank_expert_id']) ? absint($_GET['bank_expert_id']) : 0;
    $babanoel_expert_id = isset($_GET['babanoel_expert_id']) ? absint($_GET['babanoel_expert_id']) : 0;
    $effective_acceptor_sql = function_exists('mlr_get_request_effective_acceptor_sql')
        ? mlr_get_request_effective_acceptor_sql($table_alias)
        : "COALESCE({$table_alias}.acceptor_user_id, {$table_alias}.user_id)";

    $date_created_from = isset($_GET['date_created_from']) ? sanitize_text_field(wp_unslash($_GET['date_created_from'])) : '';
    $date_created_to = isset($_GET['date_created_to']) ? sanitize_text_field(wp_unslash($_GET['date_created_to'])) : '';
    $date_edit_from = isset($_GET['date_edit_from']) ? sanitize_text_field(wp_unslash($_GET['date_edit_from'])) : '';
    $date_edit_to = isset($_GET['date_edit_to']) ? sanitize_text_field(wp_unslash($_GET['date_edit_to'])) : '';
    $date_grant_from = isset($_GET['date_grant_from']) ? sanitize_text_field(wp_unslash($_GET['date_grant_from'])) : '';
    $date_grant_to = isset($_GET['date_grant_to']) ? sanitize_text_field(wp_unslash($_GET['date_grant_to'])) : '';
    $date_settlement_from = isset($_GET['date_settlement_from']) ? sanitize_text_field(wp_unslash($_GET['date_settlement_from'])) : '';
    $date_settlement_to = isset($_GET['date_settlement_to']) ? sanitize_text_field(wp_unslash($_GET['date_settlement_to'])) : '';

    $amt = function($key) {
        $v = isset($_GET[$key]) ? $_GET[$key] : '';
        if ($v === '' || $v === null || $v === '0') return null;
        return is_numeric($v) ? floatval($v) : null;
    };
    $amount_facility_from = $amt('amount_facility_from');
    $amount_facility_to = $amt('amount_facility_to');
    $amount_wallet_from = $amt('amount_wallet_from');
    $amount_wallet_to = $amt('amount_wallet_to');
    $amount_settlement_from = $amt('amount_settlement_from');
    $amount_settlement_to = $amt('amount_settlement_to');
    $can_filter_leasing_amount = function_exists('mlr_can_view_leasing_deposit_amount') && mlr_can_view_leasing_deposit_amount();
    $amount_leasing_from = $can_filter_leasing_amount ? $amt('amount_leasing_from') : null;
    $amount_leasing_to = $can_filter_leasing_amount ? $amt('amount_leasing_to') : null;

    if (!empty($search)) {
        $search_like = '%' . $wpdb->esc_like($search) . '%';
        $where_clauses[] = "({$table_alias}.full_name LIKE %s OR {$table_alias}.national_code LIKE %s OR {$table_alias}.mobile_number LIKE %s OR {$table_alias}.id = %d)";
        $prepare_params[] = $search_like;
        $prepare_params[] = $search_like;
        $prepare_params[] = $search_like;
        $prepare_params[] = is_numeric($search) ? intval($search) : 0;
    }
    if (!empty($status_filter)) {
        if (function_exists('mlr_add_status_where_clause')) {
            mlr_add_status_where_clause("{$table_alias}.status", $status_filter, $where_clauses, $prepare_params);
        } else {
            $where_clauses[] = "{$table_alias}.status = %s";
            $prepare_params[] = $status_filter;
        }
    }
    if (!empty($settlement_status)) {
        $where_clauses[] = "TRIM(COALESCE({$table_alias}.settlement_status, '')) = %s";
        $prepare_params[] = $settlement_status;
    }
    if (!empty($document_receipt_status) && function_exists('mlr_normalize_document_receipt_status')) {
        $normalized_document_receipt_status = mlr_normalize_document_receipt_status($document_receipt_status);
        if ($normalized_document_receipt_status === 'عدم نیاز به دریافت مدارک') {
            $where_clauses[] = "TRIM(COALESCE({$table_alias}.document_receipt_status, '')) IN (%s, %s)";
            $prepare_params[] = $normalized_document_receipt_status;
            $prepare_params[] = 'عدم نیاز به مدارک';
        } else {
            $where_clauses[] = "TRIM(COALESCE({$table_alias}.document_receipt_status, '')) = %s";
            $prepare_params[] = $normalized_document_receipt_status;
        }
    }
    if ($plan_id > 0) {
        $where_clauses[] = "{$table_alias}.installment_plan_id = %d";
        $prepare_params[] = $plan_id;
    }
    if ($installment_count_val > 0) {
        $safe_num = absint($installment_count_val);
        $where_clauses[] = "({$table_alias}.installment_count = " . $safe_num . ")";
    }
    if ($acceptor_id > 0) {
        $where_clauses[] = "{$effective_acceptor_sql} = %d";
        $prepare_params[] = $acceptor_id;
        $where_clauses[] = "(COALESCE({$table_alias}.request_origin, 'acceptor_portal') <> 'applicant_portal' OR COALESCE({$table_alias}.acceptor_user_id, 0) > 0)";
    }
    $acceptor_search_where = '';
    $acceptor_search_params = [];
    if (!empty($acceptor_search)) {
        $acceptor_search_where = "(u.display_name LIKE %s AND {$effective_acceptor_sql} > 0)";
        $acceptor_search_params[] = '%' . $wpdb->esc_like($acceptor_search) . '%';
    }
    if ($bank_expert_id > 0) {
        $where_clauses[] = "{$table_alias}.assigned_bank_user_id = %d";
        $prepare_params[] = $bank_expert_id;
    }
    if ($babanoel_expert_id > 0) {
        $where_clauses[] = "{$table_alias}.assigned_babanoel_user_id = %d";
        $prepare_params[] = $babanoel_expert_id;
    }

    if (!empty($date_created_from) && function_exists('mlr_jalali_to_gregorian')) {
        $g = mlr_jalali_to_gregorian($date_created_from);
        if ($g) {
            $where_clauses[] = "{$table_alias}.created_at >= %s";
            $prepare_params[] = $g . ' 00:00:00';
        }
    }
    if (!empty($date_created_to) && function_exists('mlr_jalali_to_gregorian')) {
        $g = mlr_jalali_to_gregorian($date_created_to);
        if ($g) {
            $where_clauses[] = "{$table_alias}.created_at <= %s";
            $prepare_params[] = $g . ' 23:59:59';
        }
    }
    if (!empty($date_edit_from) && function_exists('mlr_jalali_to_gregorian')) {
        $g = mlr_jalali_to_gregorian($date_edit_from);
        if ($g) {
            $where_clauses[] = "{$table_alias}.last_edited_at >= %s";
            $prepare_params[] = $g . ' 00:00:00';
        }
    }
    if (!empty($date_edit_to) && function_exists('mlr_jalali_to_gregorian')) {
        $g = mlr_jalali_to_gregorian($date_edit_to);
        if ($g) {
            $where_clauses[] = "{$table_alias}.last_edited_at <= %s";
            $prepare_params[] = $g . ' 23:59:59';
        }
    }
    if (function_exists('mlr_jalali_to_gregorian')) {
        $grant_from_g = !empty($date_grant_from) ? mlr_jalali_to_gregorian($date_grant_from) : '';
        $grant_to_g = !empty($date_grant_to) ? mlr_jalali_to_gregorian($date_grant_to) : '';
        if ($grant_from_g || $grant_to_g) {
            $where_clauses[] = "({$table_alias}.status = 'اعطا شده' AND {$table_alias}.last_edited_at IS NOT NULL)";
            if ($grant_from_g) {
                $where_clauses[] = "{$table_alias}.last_edited_at >= %s";
                $prepare_params[] = $grant_from_g . ' 00:00:00';
            }
            if ($grant_to_g) {
                $where_clauses[] = "{$table_alias}.last_edited_at <= %s";
                $prepare_params[] = $grant_to_g . ' 23:59:59';
            }
        }
    }
    if (!empty($date_settlement_from) && function_exists('mlr_jalali_to_gregorian')) {
        $g = mlr_jalali_to_gregorian($date_settlement_from);
        if ($g) {
            $where_clauses[] = "{$table_alias}.settlement_date >= %s";
            $prepare_params[] = $g . ' 00:00:00';
        }
    }
    if (!empty($date_settlement_to) && function_exists('mlr_jalali_to_gregorian')) {
        $g = mlr_jalali_to_gregorian($date_settlement_to);
        if ($g) {
            $where_clauses[] = "{$table_alias}.settlement_date <= %s";
            $prepare_params[] = $g . ' 23:59:59';
        }
    }

    if ($amount_facility_from !== null && $amount_facility_from !== '') {
        $where_clauses[] = "COALESCE({$table_alias}.facility_amount, {$table_alias}.loan_amount) >= %s";
        $prepare_params[] = (string) $amount_facility_from;
    }
    if ($amount_facility_to !== null && $amount_facility_to !== '') {
        $where_clauses[] = "COALESCE({$table_alias}.facility_amount, {$table_alias}.loan_amount) <= %s";
        $prepare_params[] = (string) $amount_facility_to;
    }
    if ($amount_wallet_from !== null && $amount_wallet_from !== '') {
        $where_clauses[] = "{$table_alias}.wallet_charge >= %s";
        $prepare_params[] = (string) $amount_wallet_from;
    }
    if ($amount_wallet_to !== null && $amount_wallet_to !== '') {
        $where_clauses[] = "{$table_alias}.wallet_charge <= %s";
        $prepare_params[] = (string) $amount_wallet_to;
    }
    if ($amount_settlement_from !== null && $amount_settlement_from !== '') {
        $where_clauses[] = "CASE WHEN {$table_alias}.final_settlement_amount IS NOT NULL AND {$table_alias}.final_settlement_amount != '' AND {$table_alias}.final_settlement_amount > 0 THEN {$table_alias}.final_settlement_amount WHEN {$table_alias}.acceptor_payment_amount IS NOT NULL AND {$table_alias}.acceptor_payment_amount != '' AND {$table_alias}.acceptor_payment_amount > 0 THEN {$table_alias}.acceptor_payment_amount ELSE 0 END >= %s";
        $prepare_params[] = (string) $amount_settlement_from;
    }
    if ($amount_settlement_to !== null && $amount_settlement_to !== '') {
        $where_clauses[] = "CASE WHEN {$table_alias}.final_settlement_amount IS NOT NULL AND {$table_alias}.final_settlement_amount != '' AND {$table_alias}.final_settlement_amount > 0 THEN {$table_alias}.final_settlement_amount WHEN {$table_alias}.acceptor_payment_amount IS NOT NULL AND {$table_alias}.acceptor_payment_amount != '' AND {$table_alias}.acceptor_payment_amount > 0 THEN {$table_alias}.acceptor_payment_amount ELSE 0 END <= %s";
        $prepare_params[] = (string) $amount_settlement_to;
    }
    if ($amount_leasing_from !== null && $amount_leasing_from !== '') {
        $where_clauses[] = "COALESCE({$table_alias}.leasing_deposit_amount, 0) >= %s";
        $prepare_params[] = (string) $amount_leasing_from;
    }
    if ($amount_leasing_to !== null && $amount_leasing_to !== '') {
        $where_clauses[] = "COALESCE({$table_alias}.leasing_deposit_amount, 0) <= %s";
        $prepare_params[] = (string) $amount_leasing_to;
    }

    $result = [
        'where' => implode(' AND ', $where_clauses),
        'params' => $prepare_params,
    ];
    if ($acceptor_search_where !== '') {
        $result['acceptor_search_where'] = $acceptor_search_where;
        $result['acceptor_search_params'] = $acceptor_search_params;
        $result['need_user_join'] = true;
    }
    return $result;
}
