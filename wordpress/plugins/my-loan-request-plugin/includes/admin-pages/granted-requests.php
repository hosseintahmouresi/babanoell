<?php
if (!defined('ABSPATH')) {
    exit;
}

function mlr_granted_requests_page() {
    if (!current_user_can('manage_options') && !current_user_can('mlr_edit_settlement_date')) {
        wp_die('شما دسترسی لازم برای مشاهده این صفحه را ندارید.');
    }

    global $wpdb;
    $table_requests = $wpdb->prefix . 'loan_requests';
    // استفاده از جدول کاربران وردپرس (در مولتی‌سایت هم درست کار می‌کند)
    $users_table = $wpdb->users;
    $effective_acceptor_sql = function_exists('mlr_get_request_effective_acceptor_sql')
        ? mlr_get_request_effective_acceptor_sql('r')
        : 'COALESCE(r.acceptor_user_id, r.user_id)';
    $success_message = '';
    $error_message = '';
    $can_view_leasing_deposit_amount = function_exists('mlr_can_view_leasing_deposit_amount') && mlr_can_view_leasing_deposit_amount();

    if (function_exists('mlr_repair_granted_request_settlement_states_once')) {
        mlr_repair_granted_request_settlement_states_once();
    }

    $empty_settlement_date_sql = "(r.settlement_date IS NULL OR TRIM(COALESCE(r.settlement_date, '')) IN ('', '0000-00-00', '0000-00-00 00:00:00'))";

    // پردازش فرم — تراکنش روی InnoDB و قفل کوتاه‌مدت جدول روی نصب‌های قدیمی MyISAM.
    // در حالت legacy هیچ تبدیل خودکار موتور جدول انجام نمی‌شود و قبل از آزادکردن قفل، نتیجه دوباره verify می‌شود.
    if (isset($_POST['mlr_assign_date']) && check_admin_referer('mlr_assign_date_action')) {
        $request_ids = isset($_POST['request_ids'])
            ? array_values(array_unique(array_filter(array_map('absint', (array) $_POST['request_ids']))))
            : [];
        $settlement_date_jalali = isset($_POST['settlement_date']) ? sanitize_text_field(wp_unslash($_POST['settlement_date'])) : '';

        if ($settlement_date_jalali === '') {
            $error_message = 'خطا: انتخاب تاریخ تسویه الزامی است.';
        } elseif (empty($request_ids)) {
            $error_message = 'هیچ درخواستی انتخاب نشده است.';
        } else {
            $settlement_date_gregorian = mlr_jalali_to_gregorian($settlement_date_jalali);
            if (!$settlement_date_gregorian) {
                $error_message = 'فرمت تاریخ نامعتبر است.';
            } else {
                $settlement_date_gregorian .= ' ' . current_time('H:i:s');
                $ids_placeholder = implode(',', array_fill(0, count($request_ids), '%d'));
                $guard_mode = function_exists('mlr_begin_financial_write_guard')
                    ? mlr_begin_financial_write_guard([$table_requests])
                    : false;
                $write_committed = false;
                $selected_requests = [];

                if (!$guard_mode) {
                    $error_message = 'شروع عملیات امن تعیین تاریخ تسویه ممکن نشد. هیچ تغییری ثبت نشد.';
                } else {
                    $lock_suffix = $guard_mode === 'transaction' ? ' FOR UPDATE' : '';
                    $selected_requests = $wpdb->get_results($wpdb->prepare(
                        "SELECT id, user_id, applicant_user_id, acceptor_user_id, request_origin, status, settlement_status, settlement_cycle_id, settlement_date
                         FROM {$table_requests}
                         WHERE id IN ($ids_placeholder){$lock_suffix}",
                        $request_ids
                    ));

                    $invalid_request_ids = [];
                    $selected_requests_by_id = [];
                    foreach ((array) $selected_requests as $selected_request) {
                        $selected_requests_by_id[(int) $selected_request->id] = $selected_request;
                    }

                    foreach ($request_ids as $request_id_for_check) {
                        $selected_request = $selected_requests_by_id[(int) $request_id_for_check] ?? null;
                        if (!$selected_request) {
                            $invalid_request_ids[] = (int) $request_id_for_check;
                            continue;
                        }
                        $normalized_status = function_exists('mlr_normalize_status')
                            ? mlr_normalize_status($selected_request->status ?? '')
                            : trim((string) ($selected_request->status ?? ''));
                        $has_effective_acceptor = function_exists('mlr_request_has_effective_acceptor')
                            ? mlr_request_has_effective_acceptor($selected_request)
                            : (!empty($selected_request->acceptor_user_id) || trim((string) ($selected_request->request_origin ?? '')) !== 'applicant_portal');
                        $cycle_id = (int) ($selected_request->settlement_cycle_id ?? 0);
                        $existing_date = trim((string) ($selected_request->settlement_date ?? ''));
                        $date_is_empty = in_array($existing_date, ['', '0000-00-00', '0000-00-00 00:00:00'], true);

                        if ($normalized_status !== 'اعطا شده' || !$has_effective_acceptor || $cycle_id > 0 || !$date_is_empty) {
                            $invalid_request_ids[] = (int) $request_id_for_check;
                        }
                    }

                    if ($invalid_request_ids) {
                        $error_message = 'برخی پرونده‌های انتخاب‌شده دیگر شرایط تعیین تاریخ را ندارند؛ هیچ تغییری ثبت نشد. شناسه‌ها: ' . implode('، ', array_unique($invalid_request_ids));
                    } else {
                        // Rows are already serialized by FOR UPDATE or WRITE table lock, so the write can be
                        // keyed by the validated IDs. The post-write verification below is the final authority.
                        $updated_count = $wpdb->query($wpdb->prepare(
                            "UPDATE {$table_requests}
                             SET settlement_status = 'در انتظار تسویه', settlement_date = %s
                             WHERE id IN ($ids_placeholder)",
                            array_merge([$settlement_date_gregorian], $request_ids)
                        ));

                        $verified_rows = $wpdb->get_results($wpdb->prepare(
                            "SELECT id, settlement_status, settlement_cycle_id, settlement_date
                             FROM {$table_requests}
                             WHERE id IN ($ids_placeholder)",
                            $request_ids
                        ));
                        $verified_by_id = [];
                        foreach ((array) $verified_rows as $verified_row) {
                            $verified_by_id[(int) $verified_row->id] = $verified_row;
                        }
                        $verify_ok = $updated_count !== false && count($verified_by_id) === count($request_ids);
                        foreach ($request_ids as $verify_id) {
                            $verified_row = $verified_by_id[(int) $verify_id] ?? null;
                            $normalized_settlement = $verified_row && function_exists('mlr_normalize_status')
                                ? mlr_normalize_status($verified_row->settlement_status ?? '')
                                : trim((string) ($verified_row->settlement_status ?? ''));
                            if (
                                !$verified_row
                                || $normalized_settlement !== 'در انتظار تسویه'
                                || (int) ($verified_row->settlement_cycle_id ?? 0) > 0
                                || (string) ($verified_row->settlement_date ?? '') !== $settlement_date_gregorian
                            ) {
                                $verify_ok = false;
                                break;
                            }
                        }

                        if (!$verify_ok) {
                            $error_message = 'تأیید نهایی تغییرات تسویه ناموفق بود؛ هیچ تغییر ناقصی نگه‌داری نشد.';
                            // MyISAM has no rollback, so restore the exact pre-write values while the WRITE lock is still held.
                            if ($guard_mode === 'table_lock') {
                                foreach ((array) $selected_requests as $before_row) {
                                    $wpdb->update(
                                        $table_requests,
                                        [
                                            'settlement_status' => $before_row->settlement_status,
                                            'settlement_cycle_id' => ((int) ($before_row->settlement_cycle_id ?? 0) > 0) ? (int) $before_row->settlement_cycle_id : null,
                                            'settlement_date' => $before_row->settlement_date,
                                        ],
                                        ['id' => (int) $before_row->id],
                                        null,
                                        ['%d']
                                    );
                                }
                            }
                        } else {
                            if ($guard_mode === 'transaction') {
                                $write_committed = function_exists('mlr_end_financial_write_guard') && mlr_end_financial_write_guard($guard_mode, true);
                                if (!$write_committed) {
                                    if (function_exists('mlr_end_financial_write_guard')) mlr_end_financial_write_guard($guard_mode, false);
                                    $error_message = 'ثبت نهایی تاریخ‌های تسویه ناموفق بود.';
                                }
                            } else {
                                // Data is already durable on a non-transactional table; release the serialization lock.
                                $write_committed = function_exists('mlr_end_financial_write_guard') && mlr_end_financial_write_guard($guard_mode, true);
                                if (!$write_committed) {
                                    // The connection itself will release MySQL table locks; do not tell the user the
                                    // write failed after verification, otherwise a retry could create misleading UX.
                                    $write_committed = true;
                                    if (function_exists('mlr_log')) {
                                        mlr_log('MyISAM settlement-date write verified but UNLOCK TABLES returned false.', ['request_ids' => $request_ids]);
                                    }
                                }
                            }
                        }
                    }

                    if (!$write_committed) {
                        if (function_exists('mlr_end_financial_write_guard')) {
                            mlr_end_financial_write_guard($guard_mode, false);
                        }
                        if (function_exists('mlr_log') && $error_message !== '') {
                            mlr_log('Bulk settlement date write guard ended without commit.', [
                                'mode' => $guard_mode,
                                'request_ids' => $request_ids,
                                'db_error' => $wpdb->last_error,
                            ]);
                        }
                    } else {
                        $success_message = count($request_ids) . ' درخواست با موفقیت تعیین تکلیف شدند.';
                        if (function_exists('mlr_add_request_event')) {
                            foreach ($request_ids as $rid) {
                                mlr_add_request_event($rid, 'settlement_status_change', 'تغییر وضعیت تسویه به: در انتظار تسویه (تاریخ: ' . $settlement_date_jalali . ')');
                            }
                        }
                    }
                }
            }
        }
    }

    $plans_table = $wpdb->prefix . 'installment_plans';
    $status_granted = 'اعطا شده';
    if (function_exists('mlr_get_all_valid_statuses')) {
        $valid = mlr_get_all_valid_statuses();
        if (in_array($status_granted, $valid, true)) {
            $status_granted = $valid[array_search($status_granted, $valid, true)];
        }
    }
    
    // حل باگ پنهان شدن فایل بعد از ویرایش - پشتیبانی از NULL و رشته خالی
    $extra_where_granted = [$empty_settlement_date_sql];
    $request_origin_filter = isset($_GET['request_origin_filter']) ? sanitize_key(wp_unslash($_GET['request_origin_filter'])) : '';
    if ($request_origin_filter === 'acceptor') {
        $extra_where_granted[] = "(r.request_origin IS NULL OR r.request_origin = '' OR r.request_origin = 'acceptor_portal' OR COALESCE(r.acceptor_user_id, 0) > 0)";
    } elseif ($request_origin_filter === 'applicant_direct') {
        $extra_where_granted[] = "(r.request_origin = 'applicant_portal' AND COALESCE(r.acceptor_user_id, 0) = 0)";
    }
    
    $use_admin_filters = current_user_can('manage_options') && function_exists('mlr_build_admin_request_filters');
    if ($use_admin_filters) {
        $filter_result = mlr_build_admin_request_filters('r', $extra_where_granted);
        $filter_result['where'] .= " AND TRIM(COALESCE(r.status, '')) = %s";
        $filter_result['params'][] = $status_granted;
        $sql_where = 'WHERE ' . $filter_result['where'];
        $prepare_params = $filter_result['params'];
    } else {
        $sql_where = $wpdb->prepare("WHERE TRIM(COALESCE(r.status, '')) = %s AND {$empty_settlement_date_sql}", $status_granted);
        $prepare_params = [];
    }
    $has_facility_column = $wpdb->get_var($wpdb->prepare("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = 'facility_amount'", $table_requests));
    $facility_select = $has_facility_column ? 'r.facility_amount,' : '';
    $query_granted = "SELECT r.id, r.user_id, r.applicant_user_id, r.acceptor_user_id, r.request_origin, r.full_name, r.mobile_number, r.wallet_charge, r.leasing_deposit_amount, r.final_settlement_amount, r.acceptor_payment_amount, {$facility_select} r.loan_amount, r.installment_count, r.installment_interval, r.product_name, r.last_edited_at, r.settlement_status, r.settlement_date, r.assigned_bank_user_id, r.installment_plan_id, u.display_name, p.name AS plan_name
        FROM {$table_requests} r
        LEFT JOIN {$users_table} u ON {$effective_acceptor_sql} = u.ID
        LEFT JOIN {$plans_table} p ON r.installment_plan_id = p.id
        {$sql_where}
        ORDER BY u.display_name, r.last_edited_at DESC";
    $granted_requests = !empty($prepare_params) ? $wpdb->get_results($wpdb->prepare($query_granted, $prepare_params)) : $wpdb->get_results($query_granted);
    if ($wpdb->last_error && current_user_can('manage_options')) {
        $error_message = ($error_message ? $error_message . ' ' : '') . 'خطای دیتابیس: ' . $wpdb->last_error;
    }

    ?>
    <div class="wrap mlr-admin-wrap mlr-settlement-page">
        <div class="mlr-page-header">
             <h1><span class="dashicons dashicons-calendar-alt"></span> درخواست‌های اعطا شده</h1>
        </div>
        <p class="mlr-page-description">فقط پرونده‌های «اعطا شده» که هنوز تاریخ تسویه برایشان تعیین نشده در زیر نمایش داده می‌شوند. پس از تعیین تاریخ، پرونده از این لیست خارج شده و در «در انتظار تسویه» یا «تسویه شده» قابل مشاهده است.</p>

        <?php if ($success_message) : ?><div id="message" class="notice notice-success is-dismissible mlr-notice"><p><?php echo wp_kses_post($success_message); ?></p></div><?php endif; ?>
        <?php if ($error_message) : ?><div id="message" class="notice notice-error is-dismissible mlr-notice"><p><?php echo wp_kses_post($error_message); ?></p></div><?php endif; ?>

        <?php if (current_user_can('manage_options')) : ?>
        <div class="mlr-filters-card mlr-granted-filters" style="margin-bottom: 20px;">
            <form method="get" action="">
                <input type="hidden" name="page" value="mlr-granted-requests">
                <div class="mlr-filters-grid" style="display:grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 12px; align-items: end;">
                    <div class="mlr-filter-item">
                        <label for="gr-s">جستجو</label>
                        <input type="search" id="gr-s" name="s" value="<?php echo esc_attr(isset($_GET['s']) ? wp_unslash($_GET['s']) : ''); ?>" placeholder="نام، کد ملی، موبایل، شناسه" style="width:100%;">
                    </div>
                    <div class="mlr-filter-item">
                        <label for="gr-plan_id">طرح</label>
                        <?php $plan_order_sql_gr = function_exists('mlr_get_installment_plan_order_by_sql') ? mlr_get_installment_plan_order_by_sql() : 'ORDER BY id DESC'; $plans_gr = $wpdb->get_results("SELECT id, name FROM {$plans_table} {$plan_order_sql_gr}"); ?>
                        <select id="gr-plan_id" name="plan_id" style="width:100%;">
                            <option value="">همه</option>
                            <?php if (!empty($plans_gr)) : foreach ($plans_gr as $pl) : ?>
                                <option value="<?php echo esc_attr($pl->id); ?>" <?php selected(isset($_GET['plan_id']) ? (int)$_GET['plan_id'] : 0, (int)$pl->id); ?>><?php echo esc_html($pl->name); ?></option>
                            <?php endforeach; endif; ?>
                        </select>
                    </div>
                    <div class="mlr-filter-item">
                        <label for="gr-acceptor_id">پذیرنده</label>
                        <?php $acceptors_gr = $wpdb->get_results($wpdb->prepare("SELECT DISTINCT u.ID, u.display_name FROM {$users_table} u INNER JOIN {$table_requests} r ON {$effective_acceptor_sql} = u.ID WHERE TRIM(COALESCE(r.status, '')) = %s AND {$empty_settlement_date_sql} AND {$effective_acceptor_sql} > 0 ORDER BY u.display_name ASC", $status_granted)); ?>
                        <select id="gr-acceptor_id" name="acceptor_id" style="width:100%;">
                            <option value="">همه</option>
                            <?php if (!empty($acceptors_gr)) : foreach ($acceptors_gr as $acc) : ?>
                                <option value="<?php echo esc_attr($acc->ID); ?>" <?php selected(isset($_GET['acceptor_id']) ? (int)$_GET['acceptor_id'] : 0, (int)$acc->ID); ?>><?php echo esc_html($acc->display_name); ?></option>
                            <?php endforeach; endif; ?>
                        </select>
                    </div>
                    <div class="mlr-filter-item">
                        <label for="gr-request-origin-filter">مبدأ درخواست</label>
                        <select id="gr-request-origin-filter" name="request_origin_filter" style="width:100%;">
                            <option value="" <?php selected($request_origin_filter, ''); ?>>همه</option>
                            <option value="acceptor" <?php selected($request_origin_filter, 'acceptor'); ?>>پذیرنده</option>
                            <option value="applicant_direct" <?php selected($request_origin_filter, 'applicant_direct'); ?>>متقاضی مستقیم</option>
                        </select>
                    </div>
                    <div class="mlr-filter-item">
                        <?php $bank_users_gr = get_users(['role' => 'bank', 'orderby' => 'display_name', 'order' => 'ASC']); ?>
                        <label for="gr-bank_expert_id">کارشناس بانک</label>
                        <select id="gr-bank_expert_id" name="bank_expert_id" style="width:100%;">
                            <option value="">همه</option>
                            <?php if (!empty($bank_users_gr)) : foreach ($bank_users_gr as $u) : ?>
                                <option value="<?php echo esc_attr($u->ID); ?>" <?php selected(isset($_GET['bank_expert_id']) ? (int)$_GET['bank_expert_id'] : 0, (int)$u->ID); ?>><?php echo esc_html($u->display_name); ?></option>
                            <?php endforeach; endif; ?>
                        </select>
                    </div>
                    <div class="mlr-filter-buttons" style="display:flex; gap: 8px; flex-wrap: wrap;">
                        <button type="submit" class="button button-primary">فیلتر</button>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=mlr-granted-requests')); ?>" class="button">حذف فیلترها</a>
                        <button type="submit" name="export_excel" value="1" class="button button-secondary"><span class="dashicons dashicons-media-spreadsheet" style="vertical-align:middle;"></span> خروجی اکسل</button>
                        <button type="submit" name="export_pdf_credit" value="1" class="button button-secondary" formtarget="_blank"><span class="dashicons dashicons-media-document" style="vertical-align:middle;"></span> PDF اعتبارسنجی</button>
                        <button type="submit" name="export_pdf_dossier" value="1" class="button button-secondary" formtarget="_blank"><span class="dashicons dashicons-portfolio" style="vertical-align:middle;"></span> PDF پرونده</button>
                    </div>
                </div>
            </form>
        </div>
        <?php endif; ?>

        <?php if (empty($granted_requests)): ?>
            <?php
            $granted_without_date_count = 0;
            if (current_user_can('manage_options') && function_exists('mlr_get_all_valid_statuses')) {
                $st = 'اعطا شده';
                $valid = mlr_get_all_valid_statuses();
                if (in_array($st, $valid, true)) { $st = $valid[array_search($st, $valid, true)]; }
                $granted_without_date_count = (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$table_requests} WHERE TRIM(COALESCE(status, '')) = %s AND (settlement_date IS NULL OR TRIM(COALESCE(settlement_date, '')) IN ('', '0000-00-00', '0000-00-00 00:00:00'))",
                    $st
                ));
            }
            ?>
            <div class="mlr-card">
                <div class="mlr-card-content" style="text-align: center; padding: 30px;">
                    <span class="dashicons dashicons-info-outline" style="font-size: 30px; color: #777;"></span>
                    <p>هیچ پرونده‌ای با وضعیت «اعطا شده» و بدون تعیین تاریخ تسویه در این لیست یافت نشد.</p>
                    <?php if (current_user_can('manage_options') && $granted_without_date_count > 0): ?>
                        <p style="color: #856404; background: #fff3cd; padding: 8px 12px; border-radius: 4px; margin: 10px 0;">در دیتابیس <?php echo (int) $granted_without_date_count; ?> درخواست اعطا شده بدون تاریخ تسویه وجود دارد. در صورت عدم نمایش، پیشوند جدول یا اتصال دیتابیس را بررسی کنید.</p>
                    <?php endif; ?>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=mlr-loan-requests')); ?>" class="button button-primary">مشاهده همه درخواست‌ها</a>
                </div>
            </div>
        <?php else: ?>
            <form method="post" id="mlr-assign-date-form">
                <?php wp_nonce_field('mlr_assign_date_action'); ?>
                <div class="mlr-card mlr-bulk-action-card">
                    <div class="mlr-card-content">
                        <label for="settlement_date" class="mlr-label">تاریخ تسویه (برای ردیف‌های منتظر تعیین تاریخ):</label>
                        <div class="mlr-bulk-action-controls" style="position: relative; display: flex; align-items: center;">
                            
                            <div class="mlr-date-input-wrapper" style="position: relative;">
                                <input type="text" name="settlement_date" id="settlement_date" class="jalali-datepicker mlr-input" placeholder="انتخاب تاریخ" autocomplete="off" style="padding-left: 30px; width: 140px; cursor: pointer;">
                                <span class="dashicons dashicons-calendar-alt mlr-datepicker-trigger" style="position: absolute; left: 5px; top: 50%; transform: translateY(-50%); color: #666; cursor: pointer; z-index: 2;"></span>
                            </div>

                            <button type="submit" name="mlr_assign_date" class="button button-primary" style="margin-right: 10px;">ثبت و انتقال</button>
                        </div>
                    </div>
                </div>

        <div class="mlr-card mlr-table-card">
                     <div class="mlr-card-content no-padding">
                        <div class="mlr-table-wrapper">
                        <table class="wp-list-table widefat striped mlr-modern-table mlr-granted-table" role="grid">
                            <thead>
                                <tr>
                                    <th scope="col" id="cb1" class="manage-column column-cb check-column"><input type="checkbox" id="mlr-select-all-waiting" title="فقط ردیف‌های منتظر تعیین تاریخ"></th>
                                    <th scope="col" class="column-id">شناسه</th>
                                    <th scope="col" class="column-acceptor">پذیرنده</th>
                                    <th scope="col" class="column-name">متقاضی</th>
                                    <th scope="col" class="column-mobile">موبایل</th>
                                    <th scope="col" class="column-amount">مبلغ تسهیلات (ریال)</th>
                                    <th scope="col" class="column-amount">مبلغ تسویه پذیرنده (ریال)</th>
                                    <?php if ($can_view_leasing_deposit_amount): ?>
                                        <th scope="col" class="column-amount">مبلغ (واریزی لیزینگ) (ریال)</th>
                                    <?php endif; ?>
                                    <th scope="col" class="column-expert">کارشناس بانک</th>
                                    <th scope="col" class="column-plan">طرح</th>
                                    <th scope="col" class="column-settlement-status">وضعیت تسویه</th>
                                    <th scope="col" class="column-date">تاریخ اعطا</th>
                                    <th scope="col" class="column-date">تاریخ تسویه</th>
                                    <th scope="col" class="column-actions">جزئیات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($granted_requests as $request):
                                    $expert_name = null;
                                    if (!empty($request->assigned_bank_user_id)) {
                                        $expert = get_userdata($request->assigned_bank_user_id);
                                        $expert_name = $expert ? $expert->display_name : null;
                                    }
                                    $facility_display = (isset($request->facility_amount) && $request->facility_amount !== '' && $request->facility_amount !== null) ? round(floatval($request->facility_amount)) : round(floatval($request->loan_amount));
                                    $acceptor_display_name = $request->display_name ?: '-';
                                    $has_effective_acceptor = function_exists('mlr_request_has_effective_acceptor')
                                        ? mlr_request_has_effective_acceptor($request)
                                        : (!empty($request->acceptor_user_id) || !(function_exists('mlr_is_applicant_origin_request') && mlr_is_applicant_origin_request($request)));
                                    if (function_exists('mlr_is_applicant_origin_request') && mlr_is_applicant_origin_request($request) && !$has_effective_acceptor) {
                                        $acceptor_display_name = 'نیازمند انتخاب پذیرنده';
                                    }
                                    $acceptor_settlement_amount_display = function_exists('mlr_format_acceptor_settlement_amount') ? mlr_format_acceptor_settlement_amount($request) : '—';
                                    $leasing_deposit_amount_display = function_exists('mlr_format_leasing_deposit_amount') ? mlr_format_leasing_deposit_amount($request) : '—';
                                    $settlement_status_display = trim((string) ($request->settlement_status ?? '')) ?: 'منتظر تعیین تاریخ';
                                    $can_assign_date = in_array($settlement_status_display, ['منتظر تعیین تاریخ'], true) && $has_effective_acceptor;
                                ?>
                                <tr class="mlr-request-row" data-id="<?php echo esc_attr($request->id); ?>">
                                    <th scope="row" class="column-cb check-column">
                                        <?php if ($can_assign_date): ?>
                                            <input type="checkbox" name="request_ids[]" value="<?php echo esc_attr($request->id); ?>" class="mlr-request-checkbox-waiting">
                                        <?php elseif (!$has_effective_acceptor): ?>
                                            <input type="checkbox" disabled class="mlr-request-checkbox-waiting" title="ابتدا باید پذیرنده پرونده مشخص شود.">
                                        <?php else: ?>
                                            <input type="checkbox" disabled class="mlr-request-checkbox-waiting" title="این پرونده قبلاً تعیین تاریخ شده است.">
                                        <?php endif; ?>
                                    </th>
                                    <td class="column-id"><?php echo esc_html($request->id); ?></td>
                                    <td>
                                        <?php echo esc_html($acceptor_display_name); ?>
                                        <?php if (!$has_effective_acceptor): ?>
                                            <br><small class="mlr-missing-acceptor-note">ثبت تاریخ پس از اتصال پذیرنده فعال می‌شود.</small>
                                        <?php endif; ?>
                                    </td>
                                    <td><strong><?php echo esc_html($request->full_name); ?></strong></td>
                                    <td><?php echo esc_html($request->mobile_number); ?></td>
                                    <td class="column-amount"><?php echo number_format($facility_display); ?></td>
                                    <td class="column-amount"><?php echo esc_html($acceptor_settlement_amount_display); ?></td>
                                    <?php if ($can_view_leasing_deposit_amount): ?>
                                        <td class="column-amount"><?php echo esc_html($leasing_deposit_amount_display); ?></td>
                                    <?php endif; ?>
                                    <td><?php echo $expert_name ? esc_html($expert_name) : '—'; ?></td>
                                    <td><?php echo esc_html($request->plan_name ?: '—'); ?></td>
                                    <td><span class="mlr-settlement-status-badge"><?php echo esc_html($settlement_status_display); ?></span></td>
                                    <td class="column-date"><?php echo mlr_gregorian_to_jalali($request->last_edited_at, 'Y/m/d'); ?></td>
                                    <td class="column-date"><?php echo !empty($request->settlement_date) ? mlr_gregorian_to_jalali($request->settlement_date, 'Y/m/d') : '—'; ?></td>
                                    <td class="column-actions"><button type="button" class="button button-small mlr-view-details-granted" data-id="<?php echo esc_attr($request->id); ?>" title="مشاهده جزئیات پرونده"><span class="dashicons dashicons-visibility"></span></button></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        </div>
                    </div>
                </div>
            </form>
        <?php endif; ?>

        <?php // Overlay و مودال جزئیات (مشترک با سایر صفحات ادمین) ?>
        <div id="mlr-overlay" style="display:none;" aria-hidden="true"></div>
        <div id="mlr-details-content" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="mlr-modal-title">
            <button id="mlr-close-details" aria-label="بستن پنجره جزئیات">&times;</button>
            <div id="mlr-details-inner"><?php // محتوا از طریق AJAX بارگذاری می‌شود ?></div>
        </div>
    </div>

    <style>
        /* === استایل یکپارچه با صفحه «همه درخواست‌ها» و رفع به‌هم‌ریختگی جدول === */
        .mlr-settlement-page.mlr-admin-wrap { padding: 25px; background-color: #f8fafc; font-family: 'Vazirmatn', system-ui, sans-serif; }
        .mlr-settlement-page .mlr-page-header h1 { font-weight: 800; color: #1e293b; margin-bottom: 8px; font-size: 26px; }
        .mlr-settlement-page .mlr-page-description { color: #64748b; margin-bottom: 24px; font-size: 14px; }
        .mlr-settlement-page .mlr-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 1px 2px rgba(0,0,0,.05); margin-bottom: 20px; overflow: hidden; }
        .mlr-settlement-page .mlr-card-content { padding: 15px 24px; }
        .mlr-settlement-page .mlr-card-content.no-padding { padding: 0; }
        .mlr-settlement-page .mlr-bulk-action-card .mlr-card-content { background: #f8fafc; display: flex; align-items: center; flex-wrap: wrap; gap: 12px; }
        .mlr-settlement-page .mlr-label { font-weight: 600; margin-left: 10px; color: #475569; font-size: 14px; }
        .mlr-settlement-page .mlr-input { height: 36px; line-height: normal; padding: 0 12px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 14px; }

        /* === UI/UX Fix: Sticky Column & Horizontal Scroll (Option 2) === */
        .mlr-settlement-page .mlr-table-wrapper { overflow-x: auto; -webkit-overflow-scrolling: touch; width: 100%; position: relative; }
        .mlr-settlement-page .mlr-table-wrapper table.wp-list-table { min-width: 1300px !important; table-layout: auto !important; width: 100%; border-collapse: collapse; }
        .mlr-settlement-page .mlr-table-wrapper th, .mlr-settlement-page .mlr-table-wrapper td { white-space: nowrap; padding: 12px 14px; text-align: right; font-size: 14px; line-height: 1.4; border-bottom: 1px solid #e2e8f0; color: #1e293b; vertical-align: middle; }
        .mlr-settlement-page .mlr-table-wrapper thead th { font-size: 13px; font-weight: 700; color: #64748b; background: #f8fafc; border-bottom: 2px solid #e2e8f0; }
        
        /* Sticky Columns */
        .mlr-settlement-page .mlr-table-wrapper th.column-cb, .mlr-settlement-page .mlr-table-wrapper td.column-cb {
            position: sticky; right: 0; z-index: 2; width: 44px !important; min-width: 44px !important; max-width: 44px !important; padding-right: 10px !important; padding-left: 10px !important;
        }
        .mlr-settlement-page .mlr-table-wrapper th.column-id, .mlr-settlement-page .mlr-table-wrapper td.column-id {
            position: sticky; right: 44px; z-index: 2; width: 80px !important; min-width: 80px !important; max-width: 80px !important; font-weight: 700; color: #4f46e5;
        }
        /* Shadow for scrolling depth */
        .mlr-settlement-page .mlr-table-wrapper th.column-id::after, .mlr-settlement-page .mlr-table-wrapper td.column-id::after {
            content: ''; position: absolute; top: 0; left: 0; bottom: 0; width: 5px; box-shadow: inset 5px 0 5px -5px rgba(0,0,0,0.15); pointer-events: none;
        }
        /* Proper Z-index for headers */
        .mlr-settlement-page .mlr-table-wrapper thead th.column-cb, .mlr-settlement-page .mlr-table-wrapper thead th.column-id { z-index: 4; background-color: #f8fafc !important; }
        
        /* Solidify Row Backgrounds for Sticky Cells */
        .mlr-settlement-page .mlr-table-wrapper tbody tr { background-color: #fff !important; }
        .mlr-settlement-page .mlr-table-wrapper tbody tr:nth-child(even) { background-color: #f8fafc !important; }
        .mlr-settlement-page .mlr-table-wrapper tbody tr:hover { background-color: #f1f5f9 !important; }
        
        .mlr-settlement-page .mlr-table-wrapper tbody tr td.column-cb, .mlr-settlement-page .mlr-table-wrapper tbody tr td.column-id { background-color: #fff !important; }
        .mlr-settlement-page .mlr-table-wrapper tbody tr:nth-child(even) td.column-cb, .mlr-settlement-page .mlr-table-wrapper tbody tr:nth-child(even) td.column-id { background-color: #f8fafc !important; }
        .mlr-settlement-page .mlr-table-wrapper tbody tr:hover td.column-cb, .mlr-settlement-page .mlr-table-wrapper tbody tr:hover td.column-id { background-color: #f1f5f9 !important; }

        .mlr-settlement-page .mlr-granted-table .column-amount { font-family: 'Vazirmatn', sans-serif; font-weight: 600; direction: ltr; text-align: left; }
        .mlr-settlement-page .mlr-granted-table .mlr-settlement-status-badge { display: inline-block; padding: 4px 10px; border-radius: 6px; font-size: 12px; font-weight: 600; background: #e0e7ff; color: #4338ca; white-space: nowrap; }
        .mlr-settlement-page .mlr-missing-acceptor-note { display: inline-block; margin-top: 4px; color: #b45309; font-size: 12px; line-height: 1.6; }
        .mlr-settlement-page .mlr-granted-table .column-actions { white-space: nowrap; overflow: visible; }
        .mlr-settlement-page .mlr-granted-table .mlr-view-details-granted { width: 36px; height: 36px; padding: 0; border-radius: 8px; background: #f1f5f9; color: #64748b; border: none; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; transition: all 0.2s; }
        .mlr-settlement-page .mlr-granted-table .mlr-view-details-granted:hover { background: #4f46e5; color: #fff; }
        .mlr-settlement-page .mlr-granted-table .mlr-view-details-granted .dashicons { font-size: 18px; width: 18px; height: 18px; }
    </style>

    <script>
    jQuery(document).ready(function($) {
        $('#mlr-select-all-waiting').on('click', function() {
            var isChecked = $(this).is(':checked');
            $('.mlr-request-checkbox-waiting:not(:disabled)').prop('checked', isChecked);
        });
        $('#mlr-assign-date-form').on('submit', function(e) {
            var checkedCount = $('.mlr-request-checkbox-waiting:checked').length;
            if (checkedCount > 0 && !$('#settlement_date').val()) {
                alert('برای درخواست‌های انتخاب‌شده لطفاً تاریخ تسویه را انتخاب کنید.');
                e.preventDefault();
            } else if (checkedCount === 0) {
                alert('لطفاً حداقل یک درخواست «منتظر تعیین تاریخ» را انتخاب کنید.');
                e.preventDefault();
            }
        });
    });
    </script>
    <?php
}
?>
