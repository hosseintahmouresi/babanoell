<?php
add_action('wp_ajax_mlr_applicant_get_requests', 'mlr_applicant_get_requests_callback');
function mlr_applicant_get_requests_callback() {
    // Clean output buffer to prevent JSON errors
    if (ob_get_length()) ob_clean();

    check_ajax_referer('mlr_applicant_requests_nonce', 'nonce');

    global $wpdb;
    $table_name = $wpdb->prefix . 'loan_requests';
    $plans_table = $wpdb->prefix . 'installment_plans';
    $user_id = get_current_user_id();

    if (!$user_id) {
        wp_send_json_error(['message' => 'کاربر وارد نشده است.']);
    }

    $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
    $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';
    $settlement_status = isset($_POST['settlement_status']) ? sanitize_text_field($_POST['settlement_status']) : '';
    $date_from_jalali = isset($_POST['date_from']) ? sanitize_text_field($_POST['date_from']) : '';
    $date_to_jalali = isset($_POST['date_to']) ? sanitize_text_field($_POST['date_to']) : '';
    $allowed_statuses = isset($_POST['allowed_statuses']) && is_array($_POST['allowed_statuses']) ? array_map('sanitize_text_field', $_POST['allowed_statuses']) : [];
    $table_view = isset($_POST['table_view']) ? sanitize_text_field($_POST['table_view']) : 'default';
    
    $date_from_gregorian = !empty($date_from_jalali) ? mlr_jalali_to_gregorian($date_from_jalali) : '';
    $date_to_gregorian = !empty($date_to_jalali) ? mlr_jalali_to_gregorian($date_to_jalali) : '';

    if (!current_user_can('manage_options') && function_exists('mlr_is_applicant_user') && !mlr_is_applicant_user($user_id)) {
        wp_send_json_error(['message' => 'دسترسی به پنل متقاضی برای حساب شما فعال نیست.']);
    }

    $where_sql = $wpdb->prepare(" WHERE r.applicant_user_id = %d AND r.request_origin = %s", $user_id, 'applicant_portal');
    
    if (!empty($search)) {
        $search_term = '%' . $wpdb->esc_like($search) . '%';
        $where_sql .= $wpdb->prepare(" AND (r.full_name LIKE %s OR r.national_code LIKE %s OR r.mobile_number LIKE %s OR r.id LIKE %s)", $search_term, $search_term, $search_term, $search_term);
    }
    // وضعیت‌های تسویه (تسویه شده، در انتظار تسویه) در دیتابیس در ستون settlement_status هستند؛ status همیشه اعطا شده است.
    $grant_view_statuses = ['اعطا شده', 'تسویه شده', 'در انتظار تسویه'];
    $is_grant_view = !empty($allowed_statuses) && count(array_diff($allowed_statuses, $grant_view_statuses)) === 0;
    if (!empty($status)) {
        if ($status === 'تسویه شده') {
            $where_sql .= $wpdb->prepare(" AND r.settlement_status = %s", 'تسویه شده');
            if ($is_grant_view) {
                $where_sql .= $wpdb->prepare(" AND r.status = %s", 'اعطا شده');
            }
        } elseif ($status === 'در انتظار تسویه') {
            $where_sql .= $wpdb->prepare(" AND r.settlement_status = %s", 'در انتظار تسویه');
            if ($is_grant_view) {
                $where_sql .= $wpdb->prepare(" AND r.status = %s", 'اعطا شده');
            }
        } else {
            $where_sql .= $wpdb->prepare(" AND r.status = %s", $status);
        }
    }
    if (!empty($settlement_status)) {
        $where_sql .= $wpdb->prepare(" AND r.settlement_status = %s", $settlement_status);
    }
    if (!empty($date_from_gregorian)) {
        $where_sql .= $wpdb->prepare(" AND r.created_at >= %s", $date_from_gregorian . ' 00:00:00');
    }
    if (!empty($date_to_gregorian)) {
        $where_sql .= $wpdb->prepare(" AND r.created_at <= %s", $date_to_gregorian . ' 23:59:59');
    }
    if (!empty($allowed_statuses)) {
        if ($is_grant_view) {
            $where_sql .= $wpdb->prepare(" AND r.status = %s", 'اعطا شده');
        } else {
            $status_placeholders = implode(', ', array_fill(0, count($allowed_statuses), '%s'));
            $where_sql .= $wpdb->prepare(" AND r.status IN ($status_placeholders)", $allowed_statuses);
        }
    }

    $query = "
        SELECT r.id, r.full_name, r.mobile_number, r.national_code, r.loan_amount, r.facility_amount, r.wallet_charge, r.installment_count, r.status, r.settlement_status, r.created_at, r.settlement_date, r.last_edited_at, r.acceptor_user_id, r.acceptor_assignment_mode, r.marketplace_status, r.marketplace_claimed_by, p.name as plan_name 
        FROM {$table_name} as r
        LEFT JOIN {$plans_table} as p ON r.installment_plan_id = p.id
        {$where_sql}
        ORDER BY r.created_at DESC
    ";
    
    $requests = $wpdb->get_results($query);
    
    if ($wpdb->last_error) {
        wp_send_json_error(['message' => 'خطا در بازیابی درخواست‌ها: ' . $wpdb->last_error]);
    }

    $html_output = '';
    $colspan = 12;
    if (empty($requests)) {
        $html_output = '<tr><td colspan="' . $colspan . '" style="text-align:center; padding: 20px;">هیچ درخواستی با این مشخصات یافت نشد.</td></tr>';
    } else {
        foreach ($requests as $request) {
            $acceptor_status_text = function_exists('mlr_get_acceptor_marketplace_status_text') ? mlr_get_acceptor_marketplace_status_text($request) : '';
            $acceptor_name = !empty($request->acceptor_user_id) && function_exists('mlr_get_acceptor_display_name') ? mlr_get_acceptor_display_name($request->acceptor_user_id) : '';
            $acceptor_display = trim($acceptor_status_text . ($acceptor_name ? ' - ' . $acceptor_name : ''));
            $html_output .= '<tr data-id="' . esc_attr($request->id) . '">';
            if ($table_view === 'granted') {
                $wallet_charge = isset($request->wallet_charge) ? round(floatval($request->wallet_charge)) : 0;
                $facility_display = (isset($request->facility_amount) && $request->facility_amount !== '' && $request->facility_amount !== null) ? round(floatval($request->facility_amount)) : round(floatval($request->loan_amount));
                $html_output .= '<td data-label="شناسه">' . esc_html($request->id) . '</td>';
                $html_output .= '<td data-label="متقاضی">' . esc_html($request->full_name) . '</td>';
                $html_output .= '<td data-label="شماره موبایل">' . esc_html($request->mobile_number) . '</td>';
                $html_output .= '<td data-label="کد ملی">' . esc_html($request->national_code) . '</td>';
                $html_output .= '<td data-label="مبلغ تسهیلات">' . number_format($facility_display) . '</td>';
                $html_output .= '<td data-label="مبلغ اعتبار خرید">' . number_format($wallet_charge) . '</td>';
                $html_output .= '<td data-label="طرح">' . esc_html($request->plan_name ?: '---') . '</td>';
                $html_output .= '<td data-label="تعداد اقساط">' . esc_html($request->installment_count ?: '---') . '</td>';
                $html_output .= '<td data-label="پذیرنده">' . esc_html($acceptor_display ?: '---') . '</td>';
                $html_output .= '<td data-label="وضعیت"><span class="mlr-status-badge" data-status="' . esc_attr($request->status) . '">' . esc_html($request->status) . '</span></td>';
                $html_output .= '<td data-label="تاریخ ثبت">' . mlr_gregorian_to_jalali($request->created_at, 'Y/m/d H:i') . '</td>';
                $html_output .= '<td data-label="عملیات"><button type="button" class="mlr-view-details-btn" title="مشاهده جزئیات پرونده"><span class="dashicons dashicons-visibility"></span></button></td>';
            } else {
                $facility_display_other = (isset($request->facility_amount) && $request->facility_amount !== '' && $request->facility_amount !== null) ? round(floatval($request->facility_amount)) : round(floatval($request->loan_amount));
                $wallet_charge_other = isset($request->wallet_charge) ? round(floatval($request->wallet_charge)) : 0;
                $html_output .= '<td data-label="شناسه">' . esc_html($request->id) . '</td>';
                $html_output .= '<td data-label="متقاضی">' . esc_html($request->full_name) . '</td>';
                $html_output .= '<td data-label="شماره موبایل">' . esc_html($request->mobile_number) . '</td>';
                $html_output .= '<td data-label="کد ملی">' . esc_html($request->national_code) . '</td>';
                $html_output .= '<td data-label="مبلغ تسهیلات">' . number_format($facility_display_other) . '</td>';
                $html_output .= '<td data-label="مبلغ اعتبار خرید">' . number_format($wallet_charge_other) . '</td>';
                $html_output .= '<td data-label="طرح">' . esc_html($request->plan_name ?: '---') . '</td>';
                $html_output .= '<td data-label="تعداد اقساط">' . esc_html($request->installment_count ?: '---') . '</td>';
                $html_output .= '<td data-label="پذیرنده">' . esc_html($acceptor_display ?: '---') . '</td>';
                $html_output .= '<td data-label="وضعیت"><span class="mlr-status-badge" data-status="' . esc_attr($request->status) . '">' . esc_html($request->status) . '</span></td>';
                $html_output .= '<td data-label="تاریخ ثبت">' . mlr_gregorian_to_jalali($request->created_at, 'Y/m/d H:i') . '</td>';
                $html_output .= '<td data-label="عملیات"><button class="mlr-view-details-btn" title="مشاهده جزئیات"><span class="dashicons dashicons-admin-generic"></span></button></td>';
            }
            $html_output .= '</tr>';
        }
    }

    wp_send_json_success(['html' => $html_output]);
}
