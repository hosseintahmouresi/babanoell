<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Ensure the current request is made by a logged-in bank expert.
 *
 * @return WP_User
 */
function mlr_get_current_bank_expert_or_error() {
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'کاربر وارد نشده است.']);
    }

    $current_user = wp_get_current_user();
    if (!in_array('bank', (array) $current_user->roles, true)) {
        wp_send_json_error(['message' => 'شما دسترسی لازم را ندارید.']);
    }

    return $current_user;
}

/**
 * Build canonical SQL variants for one or more request statuses.
 */
function mlr_get_bank_expert_status_variants($statuses) {
    $variants = [];

    foreach ((array) $statuses as $status) {
        $canonical = function_exists('mlr_normalize_status')
            ? mlr_normalize_status((string) $status)
            : trim((string) $status);

        $status_variants = function_exists('mlr_get_status_sql_variants')
            ? mlr_get_status_sql_variants($canonical)
            : [$canonical];

        foreach ((array) $status_variants as $variant) {
            $variant = trim((string) $variant);
            if ($variant !== '') {
                $variants[] = $variant;
            }
        }
    }

    return array_values(array_unique($variants));
}


/**
 * Check whether all bank-settlement columns from schema 2.9 are available.
 *
 * @param string $table_name Loan requests table.
 * @return array{ready:bool,missing:array}
 */
function mlr_get_bank_settlement_schema_state($table_name) {
    global $wpdb;

    $required_columns = [
        'bank_settlement_status',
        'bank_settlement_note',
        'bank_settled_at',
        'bank_settled_by',
        'bank_settlement_updated_at',
    ];
    $existing_columns = (array) $wpdb->get_col("DESC `{$table_name}`", 0);
    $missing_columns = array_values(array_diff($required_columns, $existing_columns));

    return [
        'ready' => empty($missing_columns),
        'missing' => $missing_columns,
    ];
}

/**
 * Shared helper to fetch only records assigned to the current bank expert.
 *
 * @param array $statuses Allowed request statuses.
 * @return array
 */
function mlr_fetch_expert_requests_helper($statuses, $options = []) {
    check_ajax_referer('mlr_frontend_nonce', 'nonce');
    $current_user = mlr_get_current_bank_expert_or_error();

    global $wpdb;
    $table_name = $wpdb->prefix . 'loan_requests';
    $plans_table = $wpdb->prefix . 'installment_plans';
    $events_table = $wpdb->prefix . 'mlr_request_events';
    $user_id = (int) $current_user->ID;

    $options = wp_parse_args((array) $options, [
        'date_basis' => 'last_edited',
        'include_milestones' => false,
    ]);

    $events_table_exists = false;
    if (!empty($options['include_milestones'])) {
        $events_table_like = $wpdb->esc_like($events_table);
        $events_table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $events_table_like)) === $events_table;
    }
    $milestone_join = '';
    $milestone_select = "NULL AS bank_referred_at, NULL AS granted_event_at";

    if (!empty($options['include_milestones']) && $events_table_exists) {
        // Historical records did not have dedicated referral/grant timestamp columns.
        // Reconstruct them from the immutable request-event timeline. For referral we
        // prefer the explicit bank_assignment event (8.4.6.71+) and fall back to the
        // workflow transition to «تأیید کارشناس» for older records.
        $milestone_join = "\n              LEFT JOIN (\n                  SELECT\n                      request_id,\n                      MAX(CASE WHEN event_type = 'bank_assignment' THEN created_at END) AS bank_referred_at,\n                      MAX(CASE WHEN event_type = 'status_change' AND details LIKE '%تأیید کارشناس%' THEN created_at END) AS status_referred_at,\n                      MAX(CASE WHEN event_type = 'status_change' AND details LIKE '%اعطا شده%' THEN created_at END) AS granted_at\n                  FROM {$events_table}\n                  GROUP BY request_id\n              ) AS ev ON ev.request_id = r.id";
        $milestone_select = "COALESCE(ev.bank_referred_at, ev.status_referred_at) AS bank_referred_at, ev.granted_at AS granted_event_at";
    }

    $status_variants = mlr_get_bank_expert_status_variants($statuses);
    $where_sql = $wpdb->prepare(" WHERE r.assigned_bank_user_id = %d", $user_id);
    if (!empty($status_variants)) {
        $status_placeholders = implode(',', array_fill(0, count($status_variants), '%s'));
        $where_sql .= $wpdb->prepare(
            " AND TRIM(COALESCE(r.status, '')) IN ($status_placeholders)",
            $status_variants
        );
    }

    $search = isset($_POST['search']) ? sanitize_text_field(wp_unslash($_POST['search'])) : '';
    $date_from_jalali = isset($_POST['date_from']) ? sanitize_text_field(wp_unslash($_POST['date_from'])) : '';
    $date_to_jalali = isset($_POST['date_to']) ? sanitize_text_field(wp_unslash($_POST['date_to'])) : '';

    $date_from_gregorian = !empty($date_from_jalali) && function_exists('mlr_jalali_to_gregorian')
        ? mlr_jalali_to_gregorian($date_from_jalali)
        : '';
    $date_to_gregorian = !empty($date_to_jalali) && function_exists('mlr_jalali_to_gregorian')
        ? mlr_jalali_to_gregorian($date_to_jalali)
        : '';

    if ($search !== '') {
        $search_term = '%' . $wpdb->esc_like($search) . '%';
        $where_sql .= $wpdb->prepare(
            " AND (r.full_name LIKE %s OR r.national_code LIKE %s OR r.mobile_number LIKE %s OR CAST(r.id AS CHAR) LIKE %s)",
            $search_term,
            $search_term,
            $search_term,
            $search_term
        );
    }

    $date_expression = 'r.last_edited_at';
    if ($options['date_basis'] === 'grant' && !empty($options['include_milestones']) && $events_table_exists) {
        $date_expression = 'COALESCE(ev.granted_at, r.last_edited_at)';
    }

    if (!empty($date_from_gregorian)) {
        $where_sql .= $wpdb->prepare(" AND {$date_expression} >= %s", $date_from_gregorian . ' 00:00:00');
    }
    if (!empty($date_to_gregorian)) {
        $where_sql .= $wpdb->prepare(" AND {$date_expression} <= %s", $date_to_gregorian . ' 23:59:59');
    }

    $settlement_schema = mlr_get_bank_settlement_schema_state($table_name);
    $bank_settlement_select = !empty($settlement_schema['ready'])
        ? "r.bank_settlement_status, r.bank_settled_at"
        : "NULL AS bank_settlement_status, NULL AS bank_settled_at";

    $query = "SELECT
                r.id,
                r.full_name,
                r.mobile_number,
                r.national_code,
                r.loan_amount,
                r.status,
                r.created_at,
                r.last_edited_at,
                {$bank_settlement_select},
                {$milestone_select},
                p.name AS plan_name
              FROM {$table_name} AS r
              LEFT JOIN {$plans_table} AS p ON r.installment_plan_id = p.id
              {$milestone_join}
              {$where_sql}
              ORDER BY {$date_expression} DESC, r.id DESC";

    return (array) $wpdb->get_results($query);
}

function mlr_render_bank_expert_standard_rows($requests, $empty_message, $date_label = 'تاریخ آخرین تغییر') {
    if (empty($requests)) {
        return '<tr><td colspan="9" style="text-align:center; padding:20px;">' . esc_html($empty_message) . '</td></tr>';
    }

    $html_output = '';
    foreach ($requests as $request) {
        $date_display = !empty($request->last_edited_at) && function_exists('mlr_gregorian_to_jalali')
            ? mlr_gregorian_to_jalali($request->last_edited_at, 'Y/m/d H:i')
            : (!empty($request->last_edited_at) ? $request->last_edited_at : '—');

        $html_output .= '<tr data-id="' . esc_attr($request->id) . '">';
        $html_output .= '<td data-label="شناسه">' . esc_html($request->id) . '</td>';
        $html_output .= '<td data-label="متقاضی">' . esc_html($request->full_name) . '</td>';
        $html_output .= '<td data-label="شماره موبایل">' . esc_html($request->mobile_number) . '</td>';
        $html_output .= '<td data-label="کد ملی">' . esc_html($request->national_code) . '</td>';
        $html_output .= '<td data-label="مبلغ">' . esc_html(number_format((float) $request->loan_amount)) . '</td>';
        $html_output .= '<td data-label="طرح">' . esc_html($request->plan_name ?: '---') . '</td>';
        $status_note = isset($request->contract_review_note) ? trim((string) $request->contract_review_note) : '';
        $html_output .= '<td data-label="وضعیت"><span class="mlr-status-badge" data-status="' . esc_attr($request->status) . '">' . esc_html($request->status) . '</span>';
        if ($status_note !== '') {
            $html_output .= '<small class="mlr-contract-review-row-note" style="display:block;margin-top:5px;line-height:1.6;color:#64748b;">' . esc_html($status_note) . '</small>';
        }
        $html_output .= '</td>';
        $html_output .= '<td data-label="' . esc_attr($date_label) . '">' . esc_html($date_display) . '</td>';
        $html_output .= '<td data-label="عملیات"><button type="button" class="mlr-view-details-btn" title="مشاهده جزئیات"><span class="dashicons dashicons-admin-generic"></span></button></td>';
        $html_output .= '</tr>';
    }

    return $html_output;
}


/**
 * Render granted bank rows with an explicit bank-to-Babanoell settlement indicator.
 * This is display-only and deliberately does not alter the separate acceptor settlement flow.
 */
function mlr_render_bank_granted_rows($requests, $empty_message) {
    if (empty($requests)) {
        return '<tr><td colspan="12" style="text-align:center; padding:20px;">' . esc_html($empty_message) . '</td></tr>';
    }

    $html_output = '';
    $recorded_statuses = ['انجام شده', 'تسویه شده', 'ثبت شده'];

    foreach ($requests as $request) {
        $grant_source = !empty($request->granted_event_at)
            ? $request->granted_event_at
            : (!empty($request->last_edited_at) ? $request->last_edited_at : '');
        $grant_date = $grant_source !== '' && function_exists('mlr_gregorian_to_jalali')
            ? mlr_gregorian_to_jalali($grant_source, 'Y/m/d H:i')
            : ($grant_source !== '' ? $grant_source : '—');

        $bank_status = trim((string) ($request->bank_settlement_status ?? ''));
        $is_bank_settled = in_array($bank_status, $recorded_statuses, true);
        $bank_settled_date = '—';
        if ($is_bank_settled && !empty($request->bank_settled_at)) {
            $bank_settled_date = function_exists('mlr_gregorian_to_jalali')
                ? mlr_gregorian_to_jalali($request->bank_settled_at, 'Y/m/d')
                : (string) $request->bank_settled_at;
        }

        $settlement_status_html = $is_bank_settled
            ? '<span class="mlr-bank-settlement-list-badge is-settled">تسویه شده</span>'
            : '<span class="mlr-bank-settlement-list-badge is-pending">تسویه نشده</span>';

        $html_output .= '<tr data-id="' . esc_attr($request->id) . '">';
        $html_output .= '<td data-label="انتخاب">' . ($is_bank_settled ? '<span title="قبلاً تسویه شده">—</span>' : '<input type="checkbox" class="mlr-bank-cycle-check" value="' . esc_attr($request->id) . '" aria-label="انتخاب پرونده ' . esc_attr($request->id) . '">') . '</td>';
        $html_output .= '<td data-label="شناسه">' . esc_html($request->id) . '</td>';
        $html_output .= '<td data-label="متقاضی">' . esc_html($request->full_name) . '</td>';
        $html_output .= '<td data-label="شماره موبایل">' . esc_html($request->mobile_number) . '</td>';
        $html_output .= '<td data-label="کد ملی">' . esc_html($request->national_code) . '</td>';
        $html_output .= '<td data-label="مبلغ">' . esc_html(number_format((float) $request->loan_amount)) . '</td>';
        $html_output .= '<td data-label="طرح">' . esc_html($request->plan_name ?: '---') . '</td>';
        $html_output .= '<td data-label="وضعیت"><span class="mlr-status-badge" data-status="' . esc_attr($request->status) . '">' . esc_html($request->status) . '</span></td>';
        $html_output .= '<td data-label="تاریخ اعطا" class="mlr-bank-milestone-date">' . esc_html($grant_date) . '</td>';
        $html_output .= '<td data-label="وضعیت تسویه بانک" class="mlr-bank-settlement-status-cell">' . $settlement_status_html . '</td>';
        $html_output .= '<td data-label="تاریخ تسویه بانک" class="mlr-bank-settlement-date-cell">' . esc_html($bank_settled_date) . '</td>';
        $html_output .= '<td data-label="عملیات"><button type="button" class="mlr-view-details-btn" title="مشاهده جزئیات"><span class="dashicons dashicons-admin-generic"></span></button></td>';
        $html_output .= '</tr>';
    }

    return $html_output;
}

// -----------------------------------------------------------------------------
// Dashboard data handlers
// -----------------------------------------------------------------------------
add_action('wp_ajax_mlr_get_bank_expert_requests', 'mlr_get_bank_expert_requests_callback');
add_action('wp_ajax_mlr_get_bank_all_requests', 'mlr_get_bank_all_requests_callback');
add_action('wp_ajax_mlr_get_bank_completion_requests', 'mlr_get_bank_completion_requests_callback');
add_action('wp_ajax_mlr_get_verified_docs_requests', 'mlr_get_verified_docs_requests_callback');
add_action('wp_ajax_mlr_get_granted_expert_requests', 'mlr_get_granted_expert_requests_callback');
add_action('wp_ajax_mlr_export_bank_expert_requests', 'mlr_export_bank_expert_requests_callback');

function mlr_get_bank_expert_requests_callback() {
    $requests = mlr_fetch_expert_requests_helper(['تأیید کارشناس']);
    wp_send_json_success([
        'html' => mlr_render_bank_expert_standard_rows(
            $requests,
            'هیچ درخواستی به شما ارجاع داده نشده است.',
            'تاریخ ارجاع'
        ),
    ]);
}

function mlr_get_bank_all_requests_callback() {
    // View-only master list: every request explicitly assigned to this bank expert,
    // regardless of workflow status. Write controls are removed by the detail context.
    $requests = mlr_fetch_expert_requests_helper([]);
    wp_send_json_success([
        'html' => mlr_render_bank_expert_standard_rows(
            $requests,
            'هیچ پرونده‌ای به نام شما تعریف نشده است.',
            'آخرین تغییر'
        ),
    ]);
}

function mlr_get_bank_completion_requests_callback() {
    $requests = mlr_fetch_expert_requests_helper(['تکمیل مدارک']);
    wp_send_json_success([
        'html' => mlr_render_bank_expert_standard_rows(
            $requests,
            'هیچ پرونده‌ای در مرحله تکمیل مدارک برای شما یافت نشد.',
            'آخرین تغییر'
        ),
    ]);
}

function mlr_get_verified_docs_requests_callback() {
    // This stage must contain only files whose final documents were actually submitted.
    // «تکمیل مدارک» remains in its own workflow stage and is visible from the read-only
    // «همه درخواست‌ها» dashboard when the assigned expert needs to inspect it.
    $requests = mlr_fetch_expert_requests_helper(['تأیید مدارک']);
    foreach ($requests as $request) {
        $request->contract_review_note = 'مدارک نهایی ارسال شده و پرونده آماده بررسی است.';
    }
    wp_send_json_success([
        'html' => mlr_render_bank_expert_standard_rows(
            $requests,
            'هیچ پرونده تکمیل‌شده‌ای برای تأیید مدارک یافت نشد.',
            'آخرین تغییر'
        ),
    ]);
}

function mlr_get_granted_expert_requests_callback() {
    // Granted files stay scoped to the assigned bank expert. The list now exposes
    // the independent bank-to-Babanoell settlement state/date so settled and
    // unsettled cases are distinguishable without opening each modal.
    $requests = mlr_fetch_expert_requests_helper(['اعطا شده'], [
        'date_basis' => 'grant',
        'include_milestones' => true,
    ]);

    wp_send_json_success([
        'html' => mlr_render_bank_granted_rows(
            $requests,
            'هیچ پرونده اعطا شده‌ای برای شما یافت نشد.'
        ),
    ]);
}

// -----------------------------------------------------------------------------
// Bank expert workflow status update.
// Uses a real transaction on InnoDB and a short WRITE lock on legacy MyISAM.
// -----------------------------------------------------------------------------
add_action('wp_ajax_mlr_submit_bank_expert_review', 'mlr_submit_bank_expert_review_callback');
function mlr_submit_bank_expert_review_callback() {
    check_ajax_referer('mlr_frontend_nonce', 'nonce');
    $current_user = mlr_get_current_bank_expert_or_error();

    $request_id = isset($_POST['request_id']) ? absint($_POST['request_id']) : 0;
    $new_status = isset($_POST['new_status']) ? sanitize_text_field(wp_unslash($_POST['new_status'])) : '';
    $comment_text = isset($_POST['comment_text']) ? sanitize_textarea_field(wp_unslash($_POST['comment_text'])) : '';

    if (!$request_id || $new_status === '') {
        wp_send_json_error(['message' => 'اطلاعات بررسی پرونده ناقص است.']);
    }

    $new_status = function_exists('mlr_normalize_status') ? mlr_normalize_status($new_status) : $new_status;

    global $wpdb;
    $table_name = $wpdb->prefix . 'loan_requests';

    $guard_mode = function_exists('mlr_begin_financial_write_guard')
        ? mlr_begin_financial_write_guard([$table_name])
        : false;

    if (!$guard_mode) {
        wp_send_json_error(['message' => 'قفل امن برای ثبت بررسی پرونده ایجاد نشد. هیچ تغییری ثبت نشد.']);
    }

    $row_lock_sql = $guard_mode === 'transaction' ? ' FOR UPDATE' : '';
    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT id, status, comments
         FROM `{$table_name}`
         WHERE id = %d AND assigned_bank_user_id = %d{$row_lock_sql}",
        $request_id,
        $current_user->ID
    ));

    if (!$row) {
        mlr_end_financial_write_guard($guard_mode, false);
        wp_send_json_error(['message' => 'درخواست یافت نشد یا به شما ارجاع داده نشده است.']);
    }

    $current_status_normalized = function_exists('mlr_normalize_status')
        ? mlr_normalize_status($row->status)
        : trim((string) $row->status);

    $allowed_statuses = [];
    if ($current_status_normalized === 'تأیید کارشناس') {
        $allowed_statuses = ['تکمیل مدارک', 'نقص مدارک', 'رد شده'];
    } elseif ($current_status_normalized === 'تأیید مدارک') {
        $allowed_statuses = ['اعطا شده', 'تکمیل مدارک', 'رد شده'];
    }

    if (!in_array($new_status, $allowed_statuses, true)) {
        mlr_end_financial_write_guard($guard_mode, false);
        wp_send_json_error(['message' => 'تغییر به این وضعیت در مرحله فعلی مجاز نیست.']);
    }

    $data_to_update = [
        'status' => $new_status,
        'last_edited_at' => current_time('mysql'),
    ];

    if ($comment_text !== '') {
        $comments = !empty($row->comments) ? json_decode($row->comments, true) : [];
        if (!is_array($comments)) {
            $comments = [];
        }
        $comments[] = [
            'user_id' => (int) $current_user->ID,
            'display_name' => $current_user->display_name,
            'text' => $comment_text,
            'timestamp' => current_time('mysql'),
        ];
        $data_to_update['comments'] = wp_json_encode($comments, JSON_UNESCAPED_UNICODE);
    }

    $updated = $wpdb->update(
        $table_name,
        $data_to_update,
        [
            'id' => $request_id,
            'assigned_bank_user_id' => $current_user->ID,
        ]
    );

    if ($updated === false) {
        mlr_end_financial_write_guard($guard_mode, false);
        if (function_exists('mlr_log')) {
            mlr_log('Bank expert request update failed.', [
                'request_id' => $request_id,
                'bank_user_id' => (int) $current_user->ID,
                'db_error' => $wpdb->last_error,
            ]);
        }
        wp_send_json_error(['message' => 'ثبت بررسی پرونده انجام نشد؛ هیچ تغییر ناقصی ذخیره نشد.']);
    }

    $guard_end_ok = mlr_end_financial_write_guard($guard_mode, true);
    if ($guard_mode === 'transaction' && !$guard_end_ok) {
        $wpdb->query('ROLLBACK');
        wp_send_json_error(['message' => 'ثبت نهایی بررسی پرونده انجام نشد؛ لطفاً دوباره تلاش کنید.']);
    }
    if ($guard_mode === 'table_lock' && !$guard_end_ok && function_exists('mlr_log')) {
        mlr_log('Bank expert request updated but explicit table unlock failed.', [
            'request_id' => $request_id,
            'db_error' => $wpdb->last_error,
        ]);
    }

    // Secondary timeline/event integrations only after the canonical row write succeeds.
    if ($comment_text !== '') {
        if (function_exists('mlr_add_request_message')) {
            $message_id = mlr_add_request_message($request_id, $comment_text);
            if (!$message_id && function_exists('mlr_log')) {
                mlr_log('Bank expert timeline message insert failed after canonical comment commit.', [
                    'request_id' => $request_id,
                ]);
            }
        }
        if (function_exists('mlr_add_request_event')) {
            mlr_add_request_event($request_id, 'note', 'یادداشت کارشناس بانک');
        }
    }

    do_action('mlr_loan_status_changed', $request_id, $current_status_normalized, $new_status, 'bank_expert');

    if (function_exists('mlr_add_request_event')) {
        mlr_add_request_event($request_id, 'status_change', 'تغییر وضعیت توسط کارشناس بانک به: ' . $new_status);
    }
    if (function_exists('mlr_send_sms_on_loan_status_change')) {
        mlr_send_sms_on_loan_status_change($request_id, $new_status);
    }
    if (function_exists('mlr_create_notification')) {
        mlr_create_notification(
            'expert_review',
            'کارشناس بانک وضعیت درخواست #' . $request_id . ' را به «' . $new_status . '» تغییر داد.',
            admin_url('admin.php?page=mlr-loan-requests&s=' . $request_id)
        );
    }

    wp_send_json_success(['message' => 'بررسی شما با موفقیت ثبت شد.']);
}

// -----------------------------------------------------------------------------
// Bank-side settlement tracking.
// This DOES NOT change settlement_status / settlement_cycle_id / settlement_date.
// -----------------------------------------------------------------------------
add_action('wp_ajax_mlr_update_bank_settlement', 'mlr_update_bank_settlement_callback');
function mlr_update_bank_settlement_callback() {
    check_ajax_referer('mlr_frontend_nonce', 'nonce');
    $current_user = mlr_get_current_bank_expert_or_error();

    $request_id = isset($_POST['request_id']) ? absint($_POST['request_id']) : 0;
    $is_settled = isset($_POST['settled']) && (string) wp_unslash($_POST['settled']) === '1';
    $settlement_date_jalali = isset($_POST['settlement_date']) ? sanitize_text_field(wp_unslash($_POST['settlement_date'])) : '';
    $note = isset($_POST['note']) ? sanitize_textarea_field(wp_unslash($_POST['note'])) : '';

    if (!$request_id) {
        wp_send_json_error(['message' => 'شناسه پرونده نامعتبر است.']);
    }

    if (function_exists('mb_strlen') && mb_strlen($note, 'UTF-8') > 3000) {
        $note = mb_substr($note, 0, 3000, 'UTF-8');
    } elseif (strlen($note) > 12000) {
        $note = substr($note, 0, 12000);
    }

    $settlement_datetime = null;
    if ($is_settled) {
        if ($settlement_date_jalali === '') {
            wp_send_json_error(['message' => 'برای ثبت تسویه بانک با بابانوئل، تاریخ تسویه را وارد کنید.']);
        }

        if (!preg_match('/^(\d{4})[\/\-](\d{1,2})[\/\-](\d{1,2})$/', $settlement_date_jalali, $matches)) {
            wp_send_json_error(['message' => 'فرمت تاریخ تسویه صحیح نیست. تاریخ را به‌صورت ۱۴۰۵/۰۷/۰۱ وارد کنید.']);
        }

        $canonical_jalali = sprintf('%04d/%02d/%02d', (int) $matches[1], (int) $matches[2], (int) $matches[3]);
        if (!function_exists('mlr_jalali_to_gregorian') || !function_exists('mlr_gregorian_to_jalali')) {
            wp_send_json_error(['message' => 'امکان تبدیل تاریخ در دسترس نیست؛ لطفاً با مدیریت سامانه تماس بگیرید.']);
        }

        $gregorian_date = mlr_jalali_to_gregorian($canonical_jalali);
        $round_trip = $gregorian_date ? mlr_gregorian_to_jalali($gregorian_date . ' 00:00:00', 'Y/m/d') : '';
        if (!$gregorian_date || $round_trip !== $canonical_jalali) {
            wp_send_json_error(['message' => 'تاریخ تسویه واردشده معتبر نیست.']);
        }

        // The expert explicitly chooses the business date; time is intentionally neutral.
        $settlement_datetime = $gregorian_date . ' 00:00:00';
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'loan_requests';

    $schema_state = mlr_get_bank_settlement_schema_state($table_name);
    if (empty($schema_state['ready'])) {
        wp_send_json_error([
            'message' => 'ساختار دیتابیس بخش تسویه بانک با بابانوئل هنوز کامل بروزرسانی نشده است. یک‌بار مدیریت وردپرس را تازه‌سازی کنید.',
        ]);
    }

    $guard_mode = function_exists('mlr_begin_financial_write_guard')
        ? mlr_begin_financial_write_guard([$table_name])
        : false;

    if (!$guard_mode) {
        wp_send_json_error(['message' => 'قفل امن برای ثبت اطلاعات تسویه ایجاد نشد.']);
    }

    $row_lock_sql = $guard_mode === 'transaction' ? ' FOR UPDATE' : '';
    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT id, status, bank_settlement_status, bank_settlement_note, bank_settled_at, bank_settled_by
         FROM `{$table_name}`
         WHERE id = %d AND assigned_bank_user_id = %d{$row_lock_sql}",
        $request_id,
        $current_user->ID
    ));

    if (!$row) {
        mlr_end_financial_write_guard($guard_mode, false);
        wp_send_json_error(['message' => 'پرونده یافت نشد یا به شما ارجاع داده نشده است.']);
    }

    if (function_exists('mlr_bank_cycle_is_member') && mlr_bank_cycle_is_member($request_id)) {
        mlr_end_financial_write_guard($guard_mode, false);
        wp_send_json_error(['message' => 'این پرونده عضو سیکل ثبت‌شده بانک است؛ اطلاعات تسویه آن از مسیر تک‌پرونده‌ای قابل تغییر نیست.']);
    }

    $request_status = function_exists('mlr_normalize_status')
        ? mlr_normalize_status($row->status)
        : trim((string) $row->status);

    if ($request_status !== 'اعطا شده') {
        mlr_end_financial_write_guard($guard_mode, false);
        wp_send_json_error(['message' => 'ثبت تسویه بانک با بابانوئل فقط برای پرونده‌های «اعطا شده» مجاز است.']);
    }

    $now = current_time('mysql');
    $data_to_update = [
        // This field is isolated from settlement_status / settlement_date / settlement_cycle_id.
        'bank_settlement_status' => $is_settled ? 'انجام شده' : '',
        'bank_settlement_note' => $note,
        'bank_settlement_updated_at' => $now,
        'bank_settled_at' => $is_settled ? $settlement_datetime : null,
        'bank_settled_by' => $is_settled ? (int) $current_user->ID : null,
    ];

    $updated = $wpdb->update(
        $table_name,
        $data_to_update,
        [
            'id' => $request_id,
            'assigned_bank_user_id' => $current_user->ID,
        ]
    );

    if ($updated === false) {
        mlr_end_financial_write_guard($guard_mode, false);
        if (function_exists('mlr_log')) {
            mlr_log('Bank-to-Babanoell settlement update failed.', [
                'request_id' => $request_id,
                'bank_user_id' => (int) $current_user->ID,
                'db_error' => $wpdb->last_error,
            ]);
        }
        wp_send_json_error(['message' => 'اطلاعات تسویه ثبت نشد؛ هیچ تغییر ناقصی ذخیره نشد.']);
    }

    $guard_end_ok = mlr_end_financial_write_guard($guard_mode, true);
    if ($guard_mode === 'transaction' && !$guard_end_ok) {
        $wpdb->query('ROLLBACK');
        wp_send_json_error(['message' => 'ثبت نهایی اطلاعات تسویه انجام نشد؛ لطفاً دوباره تلاش کنید.']);
    }
    if ($guard_mode === 'table_lock' && !$guard_end_ok && function_exists('mlr_log')) {
        mlr_log('Bank-to-Babanoell settlement updated but explicit table unlock failed.', [
            'request_id' => $request_id,
            'db_error' => $wpdb->last_error,
        ]);
    }

    if (function_exists('mlr_log')) {
        mlr_log('Bank-to-Babanoell settlement tracking updated.', [
            'request_id' => $request_id,
            'bank_user_id' => (int) $current_user->ID,
            'recorded' => $is_settled ? 1 : 0,
            'bank_babanoell_date' => $is_settled ? $settlement_datetime : null,
            'has_note' => $note !== '' ? 1 : 0,
        ]);
    }

    $status_display = $is_settled ? 'انجام شده' : 'ثبت نشده';
    $settled_at_display = $is_settled && $settlement_datetime && function_exists('mlr_gregorian_to_jalali')
        ? mlr_gregorian_to_jalali($settlement_datetime, 'Y/m/d')
        : ($is_settled ? $settlement_datetime : '—');

    if (function_exists('mlr_create_notification')) {
        mlr_create_notification(
            'bank_settlement',
            'اطلاعات تسویه بانک با بابانوئل برای پرونده #' . $request_id . ' توسط کارشناس بانک بروزرسانی شد.',
            admin_url('admin.php?page=mlr-loan-requests&s=' . $request_id)
        );
    }

    wp_send_json_success([
        'message' => 'اطلاعات تسویه بانک با بابانوئل با موفقیت ذخیره شد.',
        'status_display' => $status_display,
        'settled_at_display' => $settled_at_display,
    ]);
}

// -----------------------------------------------------------------------------
// Expert export (SpreadsheetML).
// -----------------------------------------------------------------------------
function mlr_export_bank_expert_requests_callback() {
    check_ajax_referer('mlr_frontend_nonce', 'nonce');

    if (!is_user_logged_in()) {
        wp_die('not_logged_in');
    }

    $current_user = wp_get_current_user();
    if (!in_array('bank', (array) $current_user->roles, true)) {
        wp_die('no_access');
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'loan_requests';
    $plans_table = $wpdb->prefix . 'installment_plans';
    $user_id = (int) $current_user->ID;

    $status_raw = isset($_GET['status']) ? sanitize_text_field(wp_unslash($_GET['status'])) : '';
    $allowed_statuses = ['تأیید کارشناس', 'تکمیل مدارک', 'تأیید مدارک', 'اعطا شده'];
    if ($status_raw === 'contract_review') {
        $export_statuses = ['تأیید مدارک'];
    } else {
        $status = function_exists('mlr_normalize_status') ? mlr_normalize_status($status_raw) : trim($status_raw);
        if (!in_array($status, $allowed_statuses, true)) {
            $status = 'تأیید کارشناس';
        }
        $export_statuses = [$status];
    }

    $status_variants = mlr_get_bank_expert_status_variants($export_statuses);
    $status_placeholders = implode(',', array_fill(0, count($status_variants), '%s'));
    $where_sql = $wpdb->prepare(
        " WHERE r.assigned_bank_user_id = %d AND TRIM(COALESCE(r.status, '')) IN ($status_placeholders)",
        array_merge([$user_id], $status_variants)
    );

    $search = isset($_GET['search']) ? sanitize_text_field(wp_unslash($_GET['search'])) : '';
    $date_from_jalali = isset($_GET['date_from']) ? sanitize_text_field(wp_unslash($_GET['date_from'])) : '';
    $date_to_jalali = isset($_GET['date_to']) ? sanitize_text_field(wp_unslash($_GET['date_to'])) : '';
    $date_from_gregorian = !empty($date_from_jalali) && function_exists('mlr_jalali_to_gregorian')
        ? mlr_jalali_to_gregorian($date_from_jalali)
        : '';
    $date_to_gregorian = !empty($date_to_jalali) && function_exists('mlr_jalali_to_gregorian')
        ? mlr_jalali_to_gregorian($date_to_jalali)
        : '';

    if ($search !== '') {
        $search_term = '%' . $wpdb->esc_like($search) . '%';
        $where_sql .= $wpdb->prepare(
            " AND (r.full_name LIKE %s OR r.national_code LIKE %s OR r.mobile_number LIKE %s OR CAST(r.id AS CHAR) LIKE %s)",
            $search_term,
            $search_term,
            $search_term,
            $search_term
        );
    }
    if (!empty($date_from_gregorian)) {
        $where_sql .= $wpdb->prepare(" AND r.last_edited_at >= %s", $date_from_gregorian . ' 00:00:00');
    }
    if (!empty($date_to_gregorian)) {
        $where_sql .= $wpdb->prepare(" AND r.last_edited_at <= %s", $date_to_gregorian . ' 23:59:59');
    }

    $query = "SELECT r.*, p.name AS plan_name
              FROM {$table_name} r
              LEFT JOIN {$plans_table} p ON r.installment_plan_id = p.id
              {$where_sql}
              ORDER BY r.last_edited_at DESC, r.id DESC";
    $rows = $wpdb->get_results($query);

    $filename = 'bank-requests-' . date('Ymd-His') . '.xls';
    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    echo "\xEF\xBB\xBF";

    $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $xml .= '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet" xmlns:html="http://www.w3.org/TR/REC-html40">' . "\n";
    $xml .= '<DocumentProperties xmlns="urn:schemas-microsoft-com:office:office"><Title>Bank Requests</Title></DocumentProperties>' . "\n";
    $xml .= '<ExcelWorkbook xmlns="urn:schemas-microsoft-com:office:excel"><ProtectStructure>False</ProtectStructure><ProtectWindows>False</ProtectWindows></ExcelWorkbook>' . "\n";
    $xml .= '<Styles>' . "\n";
    $xml .= '<Style ss:ID="Default" ss:Name="Normal"><Alignment ss:Vertical="Center" ss:ReadingOrder="RightToLeft"/><Borders/><Font ss:FontName="Calibri" ss:Size="11"/><Interior/><NumberFormat/><Protection/></Style>' . "\n";
    $xml .= '<Style ss:ID="Header"><Font ss:Bold="1"/><Alignment ss:Horizontal="Center" ss:Vertical="Center" ss:ReadingOrder="RightToLeft"/><Interior ss:Color="#F2F2F2" ss:Pattern="Solid"/></Style>' . "\n";
    $xml .= '<Style ss:ID="Text"><Alignment ss:ReadingOrder="RightToLeft"/><NumberFormat ss:Format="@"/></Style>' . "\n";
    $xml .= '<Style ss:ID="Number"><Alignment ss:ReadingOrder="RightToLeft"/><NumberFormat ss:Format="0"/></Style>' . "\n";
    $xml .= '</Styles>' . "\n";
    $xml .= '<Worksheet ss:Name="Requests"><Table>' . "\n";

    $headers = [
        'شناسه',
        'متقاضی',
        'کد ملی',
        'موبایل',
        'مبلغ وام',
        'طرح',
        'تعداد اقساط',
        'وضعیت',
        'تاریخ ایجاد',
        'تاریخ اعطا',
    ];

    $xml .= '<Row ss:StyleID="Header">';
    foreach ($headers as $header) {
        $xml .= '<Cell><Data ss:Type="String">' . htmlspecialchars($header, ENT_XML1 | ENT_COMPAT, 'UTF-8') . '</Data></Cell>';
    }
    $xml .= '</Row>' . "\n";

    foreach ((array) $rows as $row) {
        $created = !empty($row->created_at) && function_exists('mlr_gregorian_to_jalali')
            ? mlr_gregorian_to_jalali($row->created_at, 'Y/m/d H:i')
            : $row->created_at;
        $row_status = function_exists('mlr_normalize_status')
            ? mlr_normalize_status((string) $row->status)
            : trim((string) $row->status);
        $grant_date = ($row_status === 'اعطا شده' && !empty($row->last_edited_at))
            ? (function_exists('mlr_gregorian_to_jalali')
                ? mlr_gregorian_to_jalali($row->last_edited_at, 'Y/m/d H:i')
                : $row->last_edited_at)
            : '—';
        $installment_count = isset($row->installment_count) ? trim((string) $row->installment_count) : '';
        $installment_cell = ($installment_count !== '' && is_numeric($installment_count))
            ? ['type' => 'Number', 'style' => 'Number', 'value' => $installment_count]
            : ['type' => 'String', 'style' => 'Text', 'value' => '—'];

        $cells = [
            ['type' => 'Number', 'style' => 'Number', 'value' => (string) $row->id],
            ['type' => 'String', 'style' => 'Text', 'value' => (string) $row->full_name],
            ['type' => 'String', 'style' => 'Text', 'value' => (string) $row->national_code],
            ['type' => 'String', 'style' => 'Text', 'value' => (string) $row->mobile_number],
            ['type' => 'Number', 'style' => 'Number', 'value' => (string) $row->loan_amount],
            ['type' => 'String', 'style' => 'Text', 'value' => (string) $row->plan_name],
            $installment_cell,
            ['type' => 'String', 'style' => 'Text', 'value' => (string) $row->status],
            ['type' => 'String', 'style' => 'Text', 'value' => (string) $created],
            ['type' => 'String', 'style' => 'Text', 'value' => (string) $grant_date],
        ];

        $xml .= '<Row>';
        foreach ($cells as $cell) {
            $value = htmlspecialchars($cell['value'], ENT_XML1 | ENT_COMPAT, 'UTF-8');
            $xml .= '<Cell ss:StyleID="' . $cell['style'] . '"><Data ss:Type="' . $cell['type'] . '">' . $value . '</Data></Cell>';
        }
        $xml .= '</Row>' . "\n";
    }

    $xml .= '</Table></Worksheet></Workbook>';

    echo $xml;
    exit;
}
