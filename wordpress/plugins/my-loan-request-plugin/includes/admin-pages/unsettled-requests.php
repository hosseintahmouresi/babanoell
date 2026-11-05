<?php
function mlr_unsettled_requests_page() {
    if (!current_user_can('manage_options')) {
        wp_die('شما دسترسی لازم برای مشاهده این صفحه را ندارید.');
    }

    global $wpdb;
    $table_requests = $wpdb->prefix . 'loan_requests';
    $table_cycles = $wpdb->prefix . 'mlr_settlement_cycles';
    $users_table = $wpdb->prefix . 'users';
    $plans_table = $wpdb->prefix . 'installment_plans';
    $effective_acceptor_sql = function_exists('mlr_get_request_effective_acceptor_sql')
        ? mlr_get_request_effective_acceptor_sql('r')
        : 'COALESCE(r.acceptor_user_id, r.user_id)';
    $success_message = '';
    $error_message = '';

    if (function_exists('mlr_repair_granted_request_settlement_states_once')) {
        mlr_repair_granted_request_settlement_states_once();
    }

    // فیلتر پذیرنده برای لیست واریزی‌های آماده‌ی ایجاد سیکل
    $acceptor_filter = isset($_GET['acceptor_id']) ? intval($_GET['acceptor_id']) : 0;

    // --- Process form to CREATE a settlement cycle ---
    // InnoDB uses a real transaction/row locks. Legacy MyISAM installations use a
    // short WRITE table lock plus post-write verification and compensating cleanup.
    if (isset($_POST['mlr_create_settlement_cycle']) && check_admin_referer('mlr_create_settlement_cycle_action')) {
        $request_ids = isset($_POST['request_ids']) ? array_values(array_unique(array_filter(array_map('absint', (array) $_POST['request_ids'])))) : [];
        $description = isset($_POST['settlement_description']) ? sanitize_textarea_field(wp_unslash($_POST['settlement_description'])) : '';
        $fallback_now = current_time('mysql');

        if (empty($request_ids)) {
            $error_message = 'هیچ درخواستی برای ایجاد سیکل تسویه انتخاب نشده است.';
        } else {
            $ids_placeholder = implode(',', array_fill(0, count($request_ids), '%d'));
            $guard_mode = function_exists('mlr_begin_financial_write_guard')
                ? mlr_begin_financial_write_guard([$table_requests, $table_cycles])
                : false;
            $cycle_id = 0;
            $selected_requests = [];
            $transaction_ok = false;

            if (!$guard_mode) {
                $error_message = 'شروع عملیات امن ایجاد سیکل تسویه ممکن نشد. هیچ تغییری ثبت نشد.';
            } else {
                $lock_suffix = $guard_mode === 'transaction' ? ' FOR UPDATE' : '';
                $selected_requests = $wpdb->get_results($wpdb->prepare(
                    "SELECT id, user_id, applicant_user_id, acceptor_user_id, request_origin, status, settlement_status, settlement_cycle_id, settlement_date, final_settlement_amount, acceptor_payment_amount
                     FROM {$table_requests}
                     WHERE id IN ($ids_placeholder){$lock_suffix}",
                    $request_ids
                ));

                if (count($selected_requests) !== count($request_ids)) {
                    $error_message = 'خطا: برخی از پرونده‌های انتخاب‌شده دیگر معتبر نیستند. هیچ تغییری ثبت نشد.';
                } else {
                    $first_req = reset($selected_requests);
                    $user_id = function_exists('mlr_get_request_acceptor_user_id')
                        ? mlr_get_request_acceptor_user_id($first_req)
                        : (int) ($first_req->user_id ?? 0);
                    $settlement_date_full = (string) ($first_req->settlement_date ?? '');
                    $settlement_date_only = function_exists('mlr_get_request_settlement_date_key')
                        ? mlr_get_request_settlement_date_key($settlement_date_full)
                        : ($settlement_date_full ? date('Y-m-d', strtotime($settlement_date_full)) : '');

                    $all_valid = $user_id > 0 && $settlement_date_only !== '';
                    $invalid_request_ids = [];
                    $total_cycle_amount = 0.0;

                    foreach ($selected_requests as $selected_req) {
                        $normalized_status = function_exists('mlr_normalize_status') ? mlr_normalize_status($selected_req->status ?? '') : trim((string) ($selected_req->status ?? ''));
                        $canonical_state = function_exists('mlr_get_canonical_granted_request_settlement_state')
                            ? mlr_get_canonical_granted_request_settlement_state($selected_req)
                            : [
                                'settlement_status' => trim((string) ($selected_req->settlement_status ?? '')),
                                'settlement_cycle_id' => !empty($selected_req->settlement_cycle_id) ? (int) $selected_req->settlement_cycle_id : null,
                            ];
                        $request_date_key = function_exists('mlr_get_request_settlement_date_key')
                            ? mlr_get_request_settlement_date_key((string) ($selected_req->settlement_date ?? ''))
                            : (!empty($selected_req->settlement_date) ? date('Y-m-d', strtotime($selected_req->settlement_date)) : '');
                        $selected_acceptor_id = function_exists('mlr_get_request_acceptor_user_id')
                            ? mlr_get_request_acceptor_user_id($selected_req)
                            : (int) ($selected_req->acceptor_user_id ?: $selected_req->user_id);

                        $is_valid_row = $normalized_status === 'اعطا شده'
                            && $selected_acceptor_id > 0
                            && $selected_acceptor_id === $user_id
                            && $request_date_key === $settlement_date_only
                            && ($canonical_state['settlement_status'] ?? '') === 'در انتظار تسویه'
                            && empty($canonical_state['settlement_cycle_id']);
                        if (!$is_valid_row) {
                            $all_valid = false;
                            $invalid_request_ids[] = (int) $selected_req->id;
                        }
                        $total_cycle_amount += ($selected_req->final_settlement_amount !== '' && $selected_req->final_settlement_amount !== null)
                            ? (float) $selected_req->final_settlement_amount
                            : (($selected_req->acceptor_payment_amount !== '' && $selected_req->acceptor_payment_amount !== null) ? (float) $selected_req->acceptor_payment_amount : 0.0);
                    }

                    if (!$all_valid) {
                        $error_message = 'پرونده‌های انتخاب‌شده دیگر همگی در وضعیت قابل تسویه نیستند. هیچ تغییری ثبت نشد.';
                        if ($invalid_request_ids) {
                            $error_message .= ' شناسه‌های ناسازگار: ' . implode('، ', $invalid_request_ids);
                        }
                    } else {
                        $cycle_insert_result = $wpdb->insert($table_cycles, [
                            'user_id' => $user_id,
                            'total_amount' => $total_cycle_amount,
                            'description' => $description,
                            'settlement_date' => $settlement_date_full ?: $fallback_now,
                        ]);
                        if ($cycle_insert_result === false) {
                            $error_message = 'خطا در ایجاد رکورد سیکل تسویه. هیچ تغییری ثبت نشد.';
                        } else {
                            $cycle_id = (int) $wpdb->insert_id;
                            $update_result = $wpdb->query($wpdb->prepare(
                                "UPDATE {$table_requests}
                                 SET settlement_cycle_id = %d, settlement_status = 'تسویه شده'
                                 WHERE id IN ($ids_placeholder)
                                   AND (settlement_cycle_id IS NULL OR settlement_cycle_id = 0)",
                                array_merge([$cycle_id], $request_ids)
                            ));

                            $verified_rows = $wpdb->get_results($wpdb->prepare(
                                "SELECT id, settlement_cycle_id, settlement_status FROM {$table_requests} WHERE id IN ($ids_placeholder)",
                                $request_ids
                            ));
                            $verified_by_id = [];
                            foreach ((array) $verified_rows as $verified_row) {
                                $verified_by_id[(int) $verified_row->id] = $verified_row;
                            }
                            $verify_ok = $update_result !== false && count($verified_by_id) === count($request_ids);
                            foreach ($request_ids as $verify_id) {
                                $verified_row = $verified_by_id[(int) $verify_id] ?? null;
                                $verified_status = $verified_row && function_exists('mlr_normalize_status')
                                    ? mlr_normalize_status($verified_row->settlement_status ?? '')
                                    : trim((string) ($verified_row->settlement_status ?? ''));
                                if (!$verified_row || (int) ($verified_row->settlement_cycle_id ?? 0) !== $cycle_id || $verified_status !== 'تسویه شده') {
                                    $verify_ok = false;
                                    break;
                                }
                            }
                            $cycle_exists = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table_cycles} WHERE id = %d", $cycle_id)) === 1;
                            $verify_ok = $verify_ok && $cycle_exists;

                            if (!$verify_ok) {
                                $error_message = 'تأیید نهایی ایجاد سیکل تسویه ناموفق بود؛ تغییر ناقصی نگه‌داری نشد.';
                                if ($guard_mode === 'table_lock') {
                                    // Restore exact request state while both tables are still WRITE-locked.
                                    foreach ($selected_requests as $before_row) {
                                        $wpdb->update(
                                            $table_requests,
                                            [
                                                'settlement_cycle_id' => ((int) ($before_row->settlement_cycle_id ?? 0) > 0) ? (int) $before_row->settlement_cycle_id : null,
                                                'settlement_status' => $before_row->settlement_status,
                                            ],
                                            ['id' => (int) $before_row->id],
                                            null,
                                            ['%d']
                                        );
                                    }
                                    if ($cycle_id > 0) {
                                        $wpdb->delete($table_cycles, ['id' => $cycle_id], ['%d']);
                                    }
                                }
                            } else {
                                if ($guard_mode === 'transaction') {
                                    $transaction_ok = function_exists('mlr_end_financial_write_guard') && mlr_end_financial_write_guard($guard_mode, true);
                                    if (!$transaction_ok) {
                                        if (function_exists('mlr_end_financial_write_guard')) mlr_end_financial_write_guard($guard_mode, false);
                                        $error_message = 'ثبت نهایی تراکنش تسویه ناموفق بود.';
                                    }
                                } else {
                                    $transaction_ok = function_exists('mlr_end_financial_write_guard') && mlr_end_financial_write_guard($guard_mode, true);
                                    if (!$transaction_ok) {
                                        // Writes have already been verified on MyISAM. Connection close also releases
                                        // table locks, so do not encourage a duplicate retry after a verified write.
                                        $transaction_ok = true;
                                        if (function_exists('mlr_log')) {
                                            mlr_log('MyISAM settlement cycle verified but UNLOCK TABLES returned false.', ['cycle_id' => $cycle_id, 'request_ids' => $request_ids]);
                                        }
                                    }
                                }
                            }
                        }
                    }
                }

                if (!$transaction_ok) {
                    if (function_exists('mlr_end_financial_write_guard')) {
                        mlr_end_financial_write_guard($guard_mode, false);
                    }
                    if (function_exists('mlr_log')) {
                        mlr_log('Settlement cycle write guard ended without commit.', [
                            'mode' => $guard_mode,
                            'request_ids' => $request_ids,
                            'cycle_id' => $cycle_id,
                            'db_error' => $wpdb->last_error,
                        ]);
                    }
                } else {
                    $success_message = 'سیکل تسویه با شناسه ' . esc_html($cycle_id) . ' برای ' . count($request_ids) . ' درخواست با موفقیت ایجاد شد.';
                    if (function_exists('mlr_add_request_event')) {
                        foreach ($request_ids as $rid) {
                            mlr_add_request_event($rid, 'settlement_status_change', 'تغییر وضعیت تسویه به: تسویه شده (سیکل ' . $cycle_id . ')');
                        }
                    }
                }
            }
        }
    }

    // فقط درخواست‌هایی که تاریخ تسویه دارند و هنوز به سیکلی تخصیص نیافته‌اند
    $requests_params = [];
    $requests_where_extra = '';
    if ($acceptor_filter > 0) {
        $requests_where_extra = " AND {$effective_acceptor_sql} = %d";
        $requests_params[] = $acceptor_filter;
    }

    $requests_sql = "
        SELECT
            r.id,
            r.full_name,
            r.national_code,
            r.final_settlement_amount,
            r.acceptor_payment_amount,
            r.leasing_deposit_amount,
            r.facility_amount,
            r.loan_amount,
            r.installment_count,
            r.installment_interval,
            r.installment_plan_id,
            r.user_id,
            {$effective_acceptor_sql} AS effective_acceptor_id,
            r.settlement_date,
            u.display_name,
            p.name AS plan_name
        FROM $table_requests r
        LEFT JOIN $users_table u ON {$effective_acceptor_sql} = u.ID
        LEFT JOIN $plans_table p ON r.installment_plan_id = p.id
        WHERE TRIM(COALESCE(r.status, '')) = 'اعطا شده'
          AND TRIM(COALESCE(r.settlement_status, '')) = 'در انتظار تسویه'
          AND (r.settlement_cycle_id IS NULL OR r.settlement_cycle_id = 0)
          AND {$effective_acceptor_sql} > 0
          AND r.settlement_date IS NOT NULL
          AND r.settlement_date != ''
          $requests_where_extra
        ORDER BY r.settlement_date ASC, u.display_name, r.id ASC
    ";

    if (!empty($requests_params)) {
        $requests_ready = $wpdb->get_results($wpdb->prepare($requests_sql, $requests_params));
    } else {
        $requests_ready = $wpdb->get_results($requests_sql);
    }

    // لیست پذیرنده‌ها برای فیلتر (فقط آن‌هایی که واریزی آماده دارند)
    $acceptors_for_filter = $wpdb->get_results("
        SELECT DISTINCT u.ID, u.display_name
        FROM $users_table u
        INNER JOIN $table_requests r ON {$effective_acceptor_sql} = u.ID
        WHERE TRIM(COALESCE(r.status, '')) = 'اعطا شده'
          AND TRIM(COALESCE(r.settlement_status, '')) = 'در انتظار تسویه'
          AND (r.settlement_cycle_id IS NULL OR r.settlement_cycle_id = 0)
          AND {$effective_acceptor_sql} > 0
          AND r.settlement_date IS NOT NULL
          AND r.settlement_date != ''
        ORDER BY u.display_name ASC
    ");

    // Group requests by user and then by settlement date
    $grouped_requests = [];
    foreach ($requests_ready as $request) {
        $effective_acceptor_id = absint($request->effective_acceptor_id ?? 0);
        $user_key = $effective_acceptor_id . '_' . sanitize_key($request->display_name ?: 'کاربر-حذف-شده');
        $date_key = function_exists('mlr_get_request_settlement_date_key')
            ? mlr_get_request_settlement_date_key((string) $request->settlement_date)
            : date('Y-m-d', strtotime($request->settlement_date));
        if ($date_key === '') {
            $date_key = 'invalid-' . (int) $request->id;
        }
        if (!isset($grouped_requests[$user_key])) {
            $grouped_requests[$user_key] = [
                'name' => $request->display_name ?: 'کاربر حذف شده',
                'user_id' => $effective_acceptor_id,
                'min_settlement_date' => $date_key, // برای مرتب‌سازی: نزدیک‌ترین واریزی این پذیرنده
                'dates' => []
            ];
        }
        if (!isset($grouped_requests[$user_key]['dates'][$date_key])) {
             $grouped_requests[$user_key]['dates'][$date_key] = [
                 'jalali_date' => strpos($date_key, 'invalid-') === 0 ? 'تاریخ نامعتبر' : mlr_gregorian_to_jalali($date_key, 'Y/m/d'),
                 'date_key' => $date_key,
                 'requests' => [],
                 'total_facility_amount' => 0,
                 'total_leasing_amount' => 0,
                 'total_acceptor_amount' => 0
             ];
        }
        $request->mlr_facility_amount = (isset($request->facility_amount) && $request->facility_amount !== '' && $request->facility_amount !== null)
            ? (float) $request->facility_amount
            : (float) $request->loan_amount;
        $request->mlr_leasing_amount = function_exists('mlr_get_leasing_deposit_amount')
            ? mlr_get_leasing_deposit_amount($request)
            : ((isset($request->leasing_deposit_amount) && $request->leasing_deposit_amount !== '' && $request->leasing_deposit_amount !== null) ? (float) $request->leasing_deposit_amount : null);
        $request->mlr_leasing_amount = $request->mlr_leasing_amount !== null ? (float) $request->mlr_leasing_amount : 0;
        $request->mlr_acceptor_amount = function_exists('mlr_get_acceptor_settlement_amount')
            ? mlr_get_acceptor_settlement_amount($request)
            : ((isset($request->final_settlement_amount) && $request->final_settlement_amount !== '' && $request->final_settlement_amount !== null)
                ? (float) $request->final_settlement_amount
                : ((isset($request->acceptor_payment_amount) && $request->acceptor_payment_amount !== '' && $request->acceptor_payment_amount !== null) ? (float) $request->acceptor_payment_amount : null));
        $request->mlr_acceptor_amount = $request->mlr_acceptor_amount !== null ? (float) $request->mlr_acceptor_amount : 0;
        $grouped_requests[$user_key]['dates'][$date_key]['requests'][] = $request;
        $grouped_requests[$user_key]['dates'][$date_key]['total_facility_amount'] += (float) $request->mlr_facility_amount;
        $grouped_requests[$user_key]['dates'][$date_key]['total_leasing_amount'] += (float) $request->mlr_leasing_amount;
        $grouped_requests[$user_key]['dates'][$date_key]['total_acceptor_amount'] += (float) $request->mlr_acceptor_amount;
        if ($date_key < $grouped_requests[$user_key]['min_settlement_date']) {
            $grouped_requests[$user_key]['min_settlement_date'] = $date_key;
        }
    }

    // مرتب‌سازی پذیرندگان: کسی که نزدیک‌ترین واریزی را دارد در بالای لیست
    uasort($grouped_requests, function ($a, $b) {
        $date_a = $a['min_settlement_date'] ?? '9999-99-99';
        $date_b = $b['min_settlement_date'] ?? '9999-99-99';
        $cmp = strcmp($date_a, $date_b);
        if ($cmp !== 0) {
            return $cmp;
        }
        return strcmp($a['name'] ?? '', $b['name'] ?? '');
    });

    // داخل هر پذیرنده، واریزی‌ها (تاریخ‌ها) را از نزدیک به دور مرتب کن
    foreach (array_keys($grouped_requests) as $uk) {
        ksort($grouped_requests[$uk]['dates'], SORT_STRING);
    }

    $mlr_format_persian_amount = static function ($amount) {
        $formatted = number_format((float) $amount);
        return strtr($formatted, [
            '0' => '۰',
            '1' => '۱',
            '2' => '۲',
            '3' => '۳',
            '4' => '۴',
            '5' => '۵',
            '6' => '۶',
            '7' => '۷',
            '8' => '۸',
            '9' => '۹',
        ]);
    };

    wp_enqueue_script('jquery');
    // Ensure admin.js (containing toggle logic) is enqueued
    ?>
    <div class="wrap mlr-admin-wrap mlr-settlement-page mlr-unsettled-page">
         <div class="mlr-page-header">
             <h1><span class="dashicons dashicons-controls-repeat"></span> مرحله ۲: ایجاد سیکل تسویه</h1>
             <a href="<?php echo esc_url(add_query_arg('export_excel', '1')); ?>" class="button button-secondary page-title-action"><span class="dashicons dashicons-media-spreadsheet"></span> خروجی اکسل لیست</a>
        </div>
        <p class="mlr-page-description">درخواست‌هایی که تاریخ تسویه آنها تعیین شده در اینجا بر اساس پذیرنده و تاریخ گروه‌بندی شده‌اند. درخواست‌های مربوط به یک گروه (یک پذیرنده و یک تاریخ مشخص) را انتخاب کرده و برای آنها سیکل تسویه ایجاد کنید. با کلیک روی هدر هر گروه تاریخ، لیست درخواست‌های آن باز/بسته می‌شود.</p>

        <div class="mlr-filters-card">
            <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>">
                <input type="hidden" name="page" value="mlr-unsettled-requests">
                <div class="mlr-filters-grid">
                    <div class="mlr-filter-item">
                        <label for="acceptor_id">پذیرنده</label>
                        <select id="acceptor_id" name="acceptor_id">
                            <option value="">همه پذیرنده‌ها</option>
                            <?php if (!empty($acceptors_for_filter)) : foreach ($acceptors_for_filter as $acc) : ?>
                                <option value="<?php echo esc_attr($acc->ID); ?>" <?php selected($acceptor_filter, (int) $acc->ID); ?>>
                                    <?php echo esc_html($acc->display_name); ?>
                                </option>
                            <?php endforeach; endif; ?>
                        </select>
                    </div>
                    <div class="mlr-filter-buttons" style="grid-column: 1 / -1;">
                        <button type="submit" class="button button-primary">فیلتر</button>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=mlr-unsettled-requests')); ?>" class="button">حذف فیلترها</a>
                    </div>
                </div>
            </form>
        </div>

        <?php if ($success_message) : ?><div id="message" class="notice notice-success is-dismissible mlr-notice"><p><?php echo wp_kses_post($success_message); ?></p></div><?php endif; ?>
        <?php if ($error_message) : ?><div id="message" class="notice notice-error is-dismissible mlr-notice"><p><?php echo wp_kses_post($error_message); ?></p></div><?php endif; ?>

        <?php if (empty($grouped_requests)): ?>
             <div class="mlr-card">
                <div class="mlr-card-content" style="text-align: center; padding: 30px;">
                    <span class="dashicons dashicons-info-outline" style="font-size: 30px; color: #777; margin-bottom: 10px; display: block;"></span>
                    <p>در حال حاضر هیچ درخواستی آماده ایجاد سیکل نیست.</p>
                     <a href="<?php echo admin_url('admin.php?page=mlr-granted-requests'); ?>" class="button">بازگشت به مرحله تعیین تاریخ</a>
                </div>
            </div>
        <?php else: ?>
            <form id="mlr-settlement-form" method="post">
                <?php wp_nonce_field('mlr_create_settlement_cycle_action'); ?>

                 <div class="mlr-card mlr-bulk-action-card">
                    <div class="mlr-card-content">
                        <label for="settlement_description" class="mlr-label">توضیحات سیکل تسویه (اختیاری):</label>
                        <div class="mlr-bulk-action-controls">
                            <textarea id="settlement_description" name="settlement_description" placeholder="مثلا: تسویه مهر ماه ۱۴۰۴" rows="1" class="mlr-input"></textarea>
                            <button type="submit" name="mlr_create_settlement_cycle" class="button button-primary" value="create_cycle"><span class="dashicons dashicons-plus-alt"></span> ایجاد سیکل برای موارد انتخابی</button>
                        </div>
                         <p class="description mlr-form-description">توجه: فقط درخواست‌های مربوط به **یک پذیرنده** و **یک تاریخ تسویه** را همزمان انتخاب کنید.</p>
                    </div>
                </div>

                <?php foreach ($grouped_requests as $user_key => $user_data): ?>
                    <div class="mlr-user-group-card mlr-card">
                        <h2 class="mlr-card-title"><span class="dashicons dashicons-admin-users"></span> پذیرنده: <?php echo esc_html($user_data['name']); ?></h2>
                        <div class="mlr-card-content no-padding">
                            <?php foreach ($user_data['dates'] as $date_key => $date_data):
                                // Generate a unique group key for checkboxes AND toggle
                                $group_key_attr = esc_attr($user_data['user_id'] . '-' . $date_key);
                                $details_id = 'details-group-' . $group_key_attr; // Unique ID for details section
                                $group_total_facility = $mlr_format_persian_amount($date_data['total_facility_amount'] ?? 0);
                                $group_total_leasing = $mlr_format_persian_amount($date_data['total_leasing_amount'] ?? 0);
                                $group_total_acceptor = $mlr_format_persian_amount($date_data['total_acceptor_amount'] ?? 0);
                            ?>
                                <div class="mlr-date-group">
                                    <div class="mlr-date-group-header mlr-group-toggle" data-target="#<?php echo $details_id; ?>">
                                        <div class="mlr-group-header-left">
                                            <span class="mlr-toggle-indicator dashicons dashicons-arrow-down-alt2"></span>
                                            <label onclick="event.stopPropagation();"> <?php // Prevent header click when clicking label/checkbox ?>
                                                <input type="checkbox" class="mlr-select-all-group" data-group-key="<?php echo $group_key_attr; ?>">
                                                تاریخ تسویه: <strong><?php echo esc_html($date_data['jalali_date']); ?></strong>
                                            </label>
                                        </div>
                                        <span class="mlr-group-count"><?php echo count($date_data['requests']); ?> درخواست</span>
                                        <div class="mlr-group-header-right">
                                            <span class="mlr-group-amount-badge">جمع مبالغ تسهیلات: <?php echo esc_html($group_total_facility); ?> ریال</span>
                                            <span class="mlr-group-amount-badge">جمع پرداختی لیزینگ: <?php echo esc_html($group_total_leasing); ?> ریال</span>
                                            <span class="mlr-group-amount-badge">جمع واریزی پذیرنده: <?php echo esc_html($group_total_acceptor); ?> ریال</span>
                                            <span class="mlr-group-amount-badge mlr-selected-total-badge" data-group-key="<?php echo $group_key_attr; ?>" data-total-source="facility">جمع انتخابی مبالغ تسهیلات: ۰ ریال</span>
                                            <span class="mlr-group-amount-badge mlr-selected-total-badge" data-group-key="<?php echo $group_key_attr; ?>" data-total-source="acceptor">جمع انتخابی واریزی پذیرنده: ۰ ریال</span>
                                        </div>
                                    </div>
                                    <div class="mlr-group-details" id="<?php echo $details_id; ?>" style="display: none;"> <?php // Hidden by default ?>
                                        <div class="mlr-table-wrapper">
                                            <table class="wp-list-table widefat fixed striped mlr-inner-table mlr-compact-table">
                                                <thead>
                                                    <tr>
                                                        <th class="manage-column column-cb check-column"></th>
                                                        <th class="column-id">شناسه</th>
                                                        <th>نام متقاضی</th>
                                                        <th class="column-amount">مبلغ تسهیلات (ریال)</th>
                                                        <th class="column-plan">نام طرح</th>
                                                        <th class="column-installment-count">تعداد اقساط</th>
                                                        <th class="column-amount">مبلغ پرداختی لیزینگ (ریال)</th>
                                                        <th class="column-amount">مبلغ واریزی پذیرنده (ریال)</th>
                                                        <th class="column-date">تاریخ تسویه</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($date_data['requests'] as $request) : ?>
                                                    <tr class="mlr-request-row" data-id="<?php echo esc_attr($request->id); ?>">
                                                        <td class="column-cb check-column">
                                                            <input type="checkbox" name="request_ids[]" value="<?php echo esc_attr($request->id); ?>" class="mlr-request-checkbox" data-group-key="<?php echo $group_key_attr; ?>" data-facility-amount="<?php echo esc_attr((float) ($request->mlr_facility_amount ?? 0)); ?>" data-acceptor-amount="<?php echo esc_attr((float) ($request->mlr_acceptor_amount ?? 0)); ?>">
                                                        </td>
                                                        <td class="column-id"><?php echo esc_html($request->id); ?></td>
                                                        <td><?php echo esc_html($request->full_name); ?></td>
                                                        <td class="column-amount"><?php echo esc_html($mlr_format_persian_amount($request->mlr_facility_amount ?? 0)); ?></td>
                                                        <td class="column-plan"><?php echo esc_html($request->plan_name ?: '—'); ?></td>
                                                        <td class="column-installment-count"><?php echo esc_html(isset($request->installment_count) && $request->installment_count !== '' ? $request->installment_count : '—'); ?></td>
                                                        <td class="column-amount"><?php echo esc_html($mlr_format_persian_amount($request->mlr_leasing_amount ?? 0)); ?></td>
                                                        <td class="column-amount"><?php echo esc_html($mlr_format_persian_amount($request->mlr_acceptor_amount ?? 0)); ?></td>
                                                        <td class="column-date"><?php echo !empty($request->settlement_date) ? mlr_gregorian_to_jalali($request->settlement_date, 'Y/m/d') : '—'; ?></td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; // End date group loop ?>
                        </div>
                    </div>
                <?php endforeach; // End user group loop ?>
            <?php endif; ?>
        </form>
    </div>

    <?php // --- START: Add Styles directly --- ?>
    <style>
        /* --- Settlement Pages Common Styles --- */
        .mlr-settlement-page .mlr-page-description { color: var(--mlr-text-muted); margin-top: 5px; font-size: 14px; margin-bottom: 20px; padding: 0 5px; }
        .mlr-settlement-page .mlr-card { background: var(--mlr-surface); border: 1px solid var(--mlr-border); border-radius: 12px; margin-bottom: 25px; box-shadow: var(--mlr-shadow-sm); overflow: hidden; }
        .mlr-settlement-page .mlr-card-title { font-size: 16px; font-weight: 700; padding: 18px 25px; margin: 0; border-bottom: 1px solid var(--mlr-border); color: var(--mlr-text-main); display: flex; align-items: center; gap: 8px; background-color: #fff; }
        .mlr-settlement-page .mlr-card-title .dashicons { color: var(--mlr-text-muted); }
        .mlr-settlement-page .mlr-card-content { padding: 20px; }
        .mlr-settlement-page .mlr-card-content.no-padding { padding: 0; }
        .mlr-settlement-page .mlr-compact-table { margin-bottom: 0; }
        .mlr-settlement-page .mlr-compact-table th, .mlr-settlement-page .mlr-compact-table thead td { font-size: 13px; padding: 15px 20px; background: var(--mlr-bg); text-align: right; color: var(--mlr-text-muted); font-weight: 600; border-bottom: 1px solid var(--mlr-border); white-space: nowrap; }
        .mlr-settlement-page .mlr-compact-table tbody td, .mlr-settlement-page .mlr-compact-table tbody th { padding: 15px 20px; font-size: 14px; vertical-align: middle; text-align: right; border-bottom: 1px solid var(--mlr-border); color: var(--mlr-text-main); white-space: nowrap; }
        .mlr-settlement-page .column-id { font-weight: 700; color: var(--mlr-primary); }
        .mlr-settlement-page .column-amount { font-weight: 600; }
        .mlr-settlement-page .column-date { font-size: 13px; color: var(--mlr-text-muted); }
        .mlr-settlement-page .mlr-notice { margin: 15px 5px !important; }

        /* کارت فیلتر پذیرنده در صفحه واریزی‌ها */
        .mlr-settlement-page .mlr-filters-card { background: var(--mlr-surface); border-radius: 12px; padding: 16px 20px; box-shadow: var(--mlr-shadow-sm); border: 1px solid var(--mlr-border); margin-bottom: 20px; }
        .mlr-settlement-page .mlr-filters-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px; align-items: end; }
        .mlr-settlement-page .mlr-filter-item label { display: block; font-weight: 600; margin-bottom: 6px; font-size: 13px; color: var(--mlr-text-muted); }
        .mlr-settlement-page .mlr-filter-item select { width: 100%; padding: 8px 10px; border: 1px solid var(--mlr-border); border-radius: 8px; font-size: 13px; background-color: #f8fafc; }
        .mlr-settlement-page .mlr-filter-item select:focus { border-color: var(--mlr-primary); background-color: #fff; box-shadow: 0 0 0 2px rgba(79,70,229,0.08); outline: none; }
        .mlr-settlement-page .mlr-filter-buttons { display: flex; gap: 8px; }

        /* --- Unsettled Requests Page (Step 2) Specific Styles --- */
        .mlr-unsettled-page .mlr-bulk-action-card .mlr-card-content { display: flex; flex-direction: column; gap: 10px; background-color: #f0f6fc; border-bottom: 1px solid #cce2ff; padding: 25px; }
        .mlr-unsettled-page .mlr-label { font-weight: 600; color: var(--mlr-text-main); margin-bottom: 5px; display: block; }
        .mlr-unsettled-page .mlr-bulk-action-controls { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
        .mlr-unsettled-page .mlr-bulk-action-controls textarea.mlr-input { flex-grow: 1; min-width: 250px; height: 45px; padding: 12px 14px; border-radius: 10px; border: 1px solid var(--mlr-border); box-sizing: border-box; transition: all 0.2s; }
        .mlr-unsettled-page .mlr-bulk-action-controls textarea.mlr-input:focus { border-color: var(--mlr-primary); box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1); outline: none; }
        .mlr-unsettled-page .mlr-bulk-action-controls .button .dashicons { vertical-align: middle; margin-top: -2px; margin-left: 2px; font-size: 18px; }
        .mlr-unsettled-page .mlr-form-description { font-size: 12px; color: var(--mlr-text-muted); margin-top: 5px; }

        .mlr-user-group-card { margin-bottom: 30px; }
        .mlr-user-group-card > .mlr-card-content.no-padding { padding: 10px 15px 15px; } /* Add padding back */
        .mlr-date-group { margin-bottom: 15px; border: 1px solid var(--mlr-border); border-radius: 12px; overflow: hidden; background: #fff; box-shadow: var(--mlr-shadow-sm); }
        .mlr-date-group:last-child { margin-bottom: 0; }
        .mlr-date-group-header { background-color: var(--mlr-bg); padding: 15px 20px; border-bottom: 1px solid var(--mlr-border); display: flex; justify-content: space-between; align-items: center; gap: 12px; cursor: pointer; transition: background 0.2s; flex-wrap: wrap; }
        .mlr-date-group-header:hover { background-color: #f1f5f9; }
        .mlr-date-group-header .mlr-group-header-left { display: flex; align-items: center; gap: 8px; }
        .mlr-date-group-header .mlr-group-header-right { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; justify-content: flex-end; }
        .mlr-date-group-header .mlr-toggle-indicator { font-size: 18px; color: var(--mlr-text-muted); transition: transform 0.2s ease-in-out; }
        .mlr-date-group-header.open .mlr-toggle-indicator { transform: rotate(180deg); color: var(--mlr-primary); }
        .mlr-date-group-header label { font-weight: 600; font-size: 14px; color: var(--mlr-text-main); display: flex; align-items: center; gap: 8px; margin: 0;}
        .mlr-date-group-header input[type="checkbox"] { margin-left: 5px; cursor: pointer;}
        .mlr-date-group-header strong { color: var(--mlr-primary); }
        .mlr-group-count { font-size: 12px; color: var(--mlr-text-muted); background: #e2e8f0; padding: 4px 10px; border-radius: 20px; font-weight: 600; }
        .mlr-group-amount-badge { font-size: 12px; color: var(--mlr-text-main); background: #fff; border: 1px solid var(--mlr-border); padding: 4px 10px; border-radius: 999px; font-weight: 600; white-space: nowrap; }
        .mlr-selected-total-badge { background: #eff6ff; border-color: #bfdbfe; color: #1d4ed8; }
        
        .mlr-group-details { padding: 0; background: #fdfdff; border-top: 1px solid var(--mlr-border);}
        .mlr-inner-table { margin-bottom: 0 !important; border: none !important; box-shadow: none !important; border-top: none !important; }
        .mlr-inner-table thead { display: table-header-group; }
        .mlr-inner-table tr:last-child td, .mlr-inner-table tr:last-child th { border-bottom: none; }
        .mlr-inner-table input[type="checkbox"]:disabled { opacity: 0.5; cursor: not-allowed;}
        
        /* === UI/UX Fix: Sticky Column & Horizontal Scroll (Option 2) === */
        .mlr-table-wrapper { overflow-x: auto; -webkit-overflow-scrolling: touch; width: 100%; position: relative; }
        .mlr-table-wrapper table.wp-list-table { min-width: 1200px !important; table-layout: auto !important; width: 100%; border-collapse: collapse; }
        
        /* Sticky Columns */
        .mlr-table-wrapper th.column-cb, .mlr-table-wrapper td.column-cb {
            position: sticky; right: 0; z-index: 2; width: 44px !important; min-width: 44px !important; max-width: 44px !important; text-align: center !important; padding-right: 5px !important; padding-left: 5px !important;
        }
        .mlr-table-wrapper th.column-id, .mlr-table-wrapper td.column-id {
            position: sticky; right: 44px; z-index: 2; width: 80px !important; min-width: 80px !important; max-width: 80px !important;
        }
        /* Shadow for scrolling depth */
        .mlr-table-wrapper th.column-id::after, .mlr-table-wrapper td.column-id::after {
            content: ''; position: absolute; top: 0; left: 0; bottom: 0; width: 5px; box-shadow: inset 5px 0 5px -5px rgba(0,0,0,0.15); pointer-events: none;
        }
        /* Proper Z-index for headers */
        .mlr-table-wrapper thead th.column-cb, .mlr-table-wrapper thead th.column-id { z-index: 4; background-color: var(--mlr-bg) !important; }
        
        /* Solidify Row Backgrounds for Sticky Cells */
        .mlr-table-wrapper tbody tr { background-color: #fff !important; }
        .mlr-table-wrapper tbody tr:nth-child(even) { background-color: #f8fafc !important; }
        
        /* Apply explicit backgrounds to sticky cells */
        .mlr-table-wrapper tbody tr td.column-cb, .mlr-table-wrapper tbody tr td.column-id { background-color: #fff !important; }
        .mlr-table-wrapper tbody tr:nth-child(even) td.column-cb, .mlr-table-wrapper tbody tr:nth-child(even) td.column-id { background-color: #f8fafc !important; }
        
        /* Hover Override */
        .mlr-table-wrapper tbody tr.mlr-request-row:hover td { background-color: #f1f5f9 !important; }

    </style>
    <?php // --- END: Add Styles --- ?>

    <?php // --- START: Add JS --- ?>
    <script>
    jQuery(document).ready(function($) {

        function mlrFormatSettlementAmount(value) {
            var numericValue = parseFloat(value || 0);
            if (isNaN(numericValue)) {
                numericValue = 0;
            }
            try {
                return new Intl.NumberFormat('fa-IR').format(Math.round(numericValue));
            } catch (e) {
                return Math.round(numericValue).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',').replace(/[0-9]/g, function(d) { return '۰۱۲۳۴۵۶۷۸۹'[d]; });
            }
        }

        function mlrEscapeAttrValue(value) {
            return String(value || '').replace(/\\/g, '\\\\').replace(/"/g, '\\"');
        }

        function mlrGroupSelector(groupKey) {
            return '[data-group-key="' + mlrEscapeAttrValue(groupKey) + '"]';
        }

        function mlrGroupRequestCheckboxes(groupKey) {
            return $('.mlr-request-checkbox' + mlrGroupSelector(groupKey));
        }

        function mlrPreserveSettlementScroll(callback) {
            var x = window.pageXOffset || document.documentElement.scrollLeft || 0;
            var y = window.pageYOffset || document.documentElement.scrollTop || 0;
            callback();
            window.requestAnimationFrame(function() {
                if (Math.abs((window.pageYOffset || 0) - y) > 8) {
                    window.scrollTo(x, y);
                }
            });
        }

        function mlrUnlockSettlementPageIfNoModal() {
            if (!$('#mlr-overlay:visible, #mlr-details-content:visible, #mlr-global-modal-overlay:visible, #mlr-global-modal:visible').length) {
                $('body').removeClass('mlr-modal-open');
            }
        }

        function mlrSetSelectedTotalText($badge, selectedTotal) {
            var prefix = $badge.attr('data-total-prefix');
            var currency = $badge.attr('data-total-currency');
            if (!prefix) {
                var currentText = $badge.text() || '';
                var parts = currentText.split(':');
                prefix = parts[0] || 'Selected total';
                $badge.attr('data-total-prefix', prefix);
            }
            if (!currency) {
                var currencyMatch = ($badge.text() || '').match(/\s+([^\s]+)\s*$/);
                currency = currencyMatch ? currencyMatch[1] : '';
                $badge.attr('data-total-currency', currency);
            }
            $badge.text(prefix + ': ' + mlrFormatSettlementAmount(selectedTotal) + (currency ? ' ' + currency : ''));
        }

        function mlrUpdateSelectedTotals(groupKeyToUpdate) {
            var $badges = groupKeyToUpdate
                ? $('.mlr-selected-total-badge' + mlrGroupSelector(groupKeyToUpdate))
                : $('.mlr-selected-total-badge');

            $badges.each(function() {
                var groupKey = $(this).attr('data-group-key');
                var totalSource = $(this).attr('data-total-source') || 'acceptor';
                var amountDataKey = totalSource + '-amount';
                var selectedTotal = 0;
                mlrGroupRequestCheckboxes(groupKey).filter(':checked').each(function() {
                    selectedTotal += parseFloat($(this).data(amountDataKey) || 0);
                });
                mlrSetSelectedTotalText($(this), selectedTotal);
            });
        }

        function mlrRefreshSettlementSelection(changedGroupKey) {
            mlrUnlockSettlementPageIfNoModal();

            var $requestCheckboxes = $('.mlr-request-checkbox');
            var $groupCheckboxes = $('.mlr-select-all-group');
            var $checkedCheckboxes = $requestCheckboxes.filter(':checked');

            if (!$checkedCheckboxes.length) {
                $requestCheckboxes.prop('disabled', false);
                $groupCheckboxes.prop('disabled', false).prop('checked', false);
                mlrUpdateSelectedTotals();
                return;
            }

            var selectedGroupKey = String($checkedCheckboxes.first().attr('data-group-key') || changedGroupKey || '');

            $requestCheckboxes.each(function() {
                var isSameGroup = String($(this).attr('data-group-key') || '') === selectedGroupKey;
                this.disabled = !isSameGroup;
                if (!isSameGroup) {
                    this.checked = false;
                }
            });

            $groupCheckboxes.each(function() {
                var isSameGroup = String($(this).attr('data-group-key') || '') === selectedGroupKey;
                this.disabled = !isSameGroup;
                if (!isSameGroup) {
                    this.checked = false;
                }
            });

            var $selectedGroupCheckboxes = mlrGroupRequestCheckboxes(selectedGroupKey);
            var enabledCount = $selectedGroupCheckboxes.not(':disabled').length;
            var checkedCount = $selectedGroupCheckboxes.filter(':checked').length;
            $('.mlr-select-all-group' + mlrGroupSelector(selectedGroupKey)).prop('checked', enabledCount > 0 && enabledCount === checkedCount);
            mlrUpdateSelectedTotals();
        }

        // --- Group Details Toggle ---
        $('.mlr-group-toggle').on('click', function(e) {
             // Prevent toggle if clicking on checkbox or its label
            if ($(e.target).is('input[type="checkbox"]') || $(e.target).closest('label').length > 0) {
                 return;
            }
            var targetId = $(this).data('target');
            $(this).toggleClass('open');
            $(targetId).slideToggle(200);
        });

        $('#mlr-settlement-form').on('click', 'input[type="checkbox"], label', function(e) {
            e.stopPropagation();
        });

        $('#mlr-settlement-form').on('click', '.check-column', function(e) {
            if ($(e.target).is('input[type="checkbox"]')) {
                return;
            }
            var $checkbox = $(this).find('input[type="checkbox"]:not(:disabled)').first();
            if (!$checkbox.length) {
                e.stopPropagation();
                return;
            }
            e.preventDefault();
            e.stopPropagation();
            $checkbox.prop('checked', !$checkbox.prop('checked')).triggerHandler('change');
        });

        // --- Checkbox Logic ---
        $('.mlr-select-all-group').on('click', function(e) {
            e.stopPropagation();
            var groupKey = $(this).attr('data-group-key');
            var isChecked = $(this).is(':checked');
            mlrPreserveSettlementScroll(function() {
                mlrGroupRequestCheckboxes(groupKey).not(':disabled').prop('checked', isChecked);
                mlrRefreshSettlementSelection(groupKey);
            });
         });

        $('.mlr-request-checkbox').on('change', function(e) {
            e.stopPropagation();
            var groupKey = $(this).attr('data-group-key');
            mlrPreserveSettlementScroll(function() {
                mlrRefreshSettlementSelection(groupKey);
            });
         });
        $('#mlr-settlement-form').on('submit', function(e) { /* ... */
            if ($('input[name="request_ids[]"]:checked').length === 0) { alert('لطفاً حداقل یک درخواست را انتخاب کنید.'); e.preventDefault(); return false; }
            if (!confirm('آیا از ایجاد سیکل تسویه مطمئن هستید؟ وضعیت به "تسویه شده" تغییر خواهد کرد.')) { e.preventDefault(); return false; }
         });
        mlrUnlockSettlementPageIfNoModal();
        mlrUpdateSelectedTotals();
    });
    </script>
     <?php // --- END: Add JS --- ?>
    <?php
}
?>
