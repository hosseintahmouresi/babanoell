<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Internal Babanoell view for Bank -> Babanoell settlement tracking.
 * This page is intentionally isolated from merchant settlement_status / settlement_date / cycles.
 */
if (!function_exists('mlr_can_view_bank_settlements_admin')) {
    function mlr_can_view_bank_settlements_admin() {
        if (function_exists('is_super_admin') && is_super_admin(get_current_user_id())) {
            return true;
        }
        return current_user_can('manage_options') || current_user_can('mlr_view_bank_settlements');
    }
}

if (!function_exists('mlr_bank_settlement_recorded_sql')) {
    function mlr_bank_settlement_recorded_sql($alias = 'r') {
        $alias = preg_replace('/[^A-Za-z0-9_]/', '', (string) $alias);
        if ($alias === '') {
            $alias = 'r';
        }
        return "TRIM(COALESCE({$alias}.bank_settlement_status, '')) IN ('انجام شده', 'تسویه شده', 'ثبت شده')";
    }
}

if (!function_exists('mlr_bank_settlement_build_where')) {
    function mlr_bank_settlement_build_where($include_status_filter = true) {
        global $wpdb;

        $clauses = [
            "TRIM(COALESCE(r.status, '')) = 'اعطا شده'",
            'COALESCE(r.assigned_bank_user_id, 0) > 0',
        ];
        $params = [];
        $recorded_sql = mlr_bank_settlement_recorded_sql('r');

        if ($include_status_filter) {
            $tracking_status = isset($_GET['bank_tracking_status']) ? sanitize_key(wp_unslash($_GET['bank_tracking_status'])) : 'all';
            if ($tracking_status === 'recorded') {
                $clauses[] = $recorded_sql;
            } elseif ($tracking_status === 'unrecorded') {
                $clauses[] = "NOT ({$recorded_sql})";
            }
        }

        $bank_user_id = isset($_GET['bank_user_id']) ? absint($_GET['bank_user_id']) : 0;
        if ($bank_user_id > 0) {
            $clauses[] = 'r.assigned_bank_user_id = %d';
            $params[] = $bank_user_id;
        }

        $search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
        if ($search !== '') {
            if (ctype_digit($search)) {
                $clauses[] = '(r.id = %d OR r.national_code LIKE %s OR r.mobile_number LIKE %s OR r.full_name LIKE %s)';
                $params[] = (int) $search;
                $like = '%' . $wpdb->esc_like($search) . '%';
                $params[] = $like;
                $params[] = $like;
                $params[] = $like;
            } else {
                $like = '%' . $wpdb->esc_like($search) . '%';
                $clauses[] = '(r.full_name LIKE %s OR r.national_code LIKE %s OR r.mobile_number LIKE %s)';
                $params[] = $like;
                $params[] = $like;
                $params[] = $like;
            }
        }

        $date_from = isset($_GET['bank_date_from']) ? sanitize_text_field(wp_unslash($_GET['bank_date_from'])) : '';
        $date_to = isset($_GET['bank_date_to']) ? sanitize_text_field(wp_unslash($_GET['bank_date_to'])) : '';
        if ($date_from !== '' && function_exists('mlr_jalali_to_gregorian')) {
            $date_from_g = mlr_jalali_to_gregorian($date_from);
            if ($date_from_g) {
                $clauses[] = 'r.bank_settled_at >= %s';
                $params[] = $date_from_g . ' 00:00:00';
            }
        }
        if ($date_to !== '' && function_exists('mlr_jalali_to_gregorian')) {
            $date_to_g = mlr_jalali_to_gregorian($date_to);
            if ($date_to_g) {
                $clauses[] = 'r.bank_settled_at <= %s';
                $params[] = $date_to_g . ' 23:59:59';
            }
        }

        return [
            'sql' => implode(' AND ', $clauses),
            'params' => $params,
        ];
    }
}

if (!function_exists('mlr_bank_settlement_prepare_query')) {
    function mlr_bank_settlement_prepare_query($sql, array $params) {
        global $wpdb;
        return $params ? $wpdb->prepare($sql, $params) : $sql;
    }
}

if (!function_exists('mlr_bank_settlement_status_label')) {
    function mlr_bank_settlement_status_label($status) {
        return in_array(trim((string) $status), ['انجام شده', 'تسویه شده', 'ثبت شده'], true) ? 'انجام شده' : 'ثبت نشده';
    }
}

if (!function_exists('mlr_bank_settlement_export_admin')) {
    function mlr_bank_settlement_export_admin() {
        if (!is_admin() || !isset($_GET['page']) || sanitize_key(wp_unslash($_GET['page'])) !== 'mlr-bank-settlements') {
            return;
        }
        if (!isset($_GET['export_bank_settlements']) || (string) wp_unslash($_GET['export_bank_settlements']) !== '1') {
            return;
        }
        if (!mlr_can_view_bank_settlements_admin()) {
            wp_die('شما دسترسی لازم برای دریافت این خروجی را ندارید.');
        }
        check_admin_referer('mlr_export_bank_settlements');

        global $wpdb;
        $table = $wpdb->prefix . 'loan_requests';
        $users = $wpdb->users;
        $plans = $wpdb->prefix . 'installment_plans';
        $filter = mlr_bank_settlement_build_where(true);

        $sql = "SELECT r.*, b.display_name AS bank_expert_name, rec.display_name AS settlement_recorder_name, p.name AS plan_name
                FROM {$table} r
                LEFT JOIN {$users} b ON b.ID = r.assigned_bank_user_id
                LEFT JOIN {$users} rec ON rec.ID = r.bank_settled_by
                LEFT JOIN {$plans} p ON p.id = r.installment_plan_id
                WHERE {$filter['sql']}
                ORDER BY r.bank_settlement_updated_at DESC, r.id DESC";
        $rows = $wpdb->get_results(mlr_bank_settlement_prepare_query($sql, $filter['params']));
        if (!is_array($rows)) {
            $rows = [];
        }

        while (ob_get_level()) {
            ob_end_clean();
        }
        nocache_headers();
        header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
        header('Content-Disposition: attachment; filename="bank-settlements-' . gmdate('Y-m-d') . '.xls"');
        echo "\xEF\xBB\xBF";
        echo '<html><head><meta charset="UTF-8"></head><body><table border="1"><thead><tr>';
        foreach (['کد پرونده','کارشناس بانک','متقاضی','کد ملی','شماره همراه','طرح','مبلغ تسهیلات (ریال)','وضعیت ثبت تسویه بانک با بابانوئل','تاریخ تسویه بانک با بابانوئل','ثبت‌کننده','یادداشت داخلی','آخرین بروزرسانی'] as $heading) {
            echo '<th>' . esc_html($heading) . '</th>';
        }
        echo '</tr></thead><tbody>';
        foreach ($rows as $row) {
            $facility_amount = isset($row->facility_amount) && $row->facility_amount !== '' && $row->facility_amount !== null
                ? (float) $row->facility_amount
                : (float) ($row->loan_amount ?? 0);
            $settled_date = !empty($row->bank_settled_at) && function_exists('mlr_gregorian_to_jalali')
                ? mlr_gregorian_to_jalali($row->bank_settled_at, 'Y/m/d')
                : (!empty($row->bank_settled_at) ? substr((string) $row->bank_settled_at, 0, 10) : '—');
            $updated_at = !empty($row->bank_settlement_updated_at) && function_exists('mlr_gregorian_to_jalali')
                ? mlr_gregorian_to_jalali($row->bank_settlement_updated_at, 'Y/m/d H:i')
                : ($row->bank_settlement_updated_at ?: '—');
            $cells = [
                (int) $row->id,
                $row->bank_expert_name ?: '—',
                $row->full_name ?: '—',
                $row->national_code ?: '—',
                $row->mobile_number ?: '—',
                $row->plan_name ?: '—',
                number_format($facility_amount, 0, '.', ''),
                mlr_bank_settlement_status_label($row->bank_settlement_status ?? ''),
                $settled_date,
                $row->settlement_recorder_name ?: '—',
                $row->bank_settlement_note ?: '—',
                $updated_at,
            ];
            echo '<tr>';
            foreach ($cells as $cell) {
                echo '<td>' . esc_html((string) $cell) . '</td>';
            }
            echo '</tr>';
        }
        echo '</tbody></table></body></html>';
        exit;
    }
}
add_action('admin_init', 'mlr_bank_settlement_export_admin', 2);

function mlr_bank_settlements_page() {
    if (!mlr_can_view_bank_settlements_admin()) {
        wp_die('شما دسترسی لازم برای مشاهده تسویه بانک را ندارید.');
    }

    global $wpdb;
    $table = $wpdb->prefix . 'loan_requests';
    $users = $wpdb->users;
    $plans = $wpdb->prefix . 'installment_plans';

    $required_columns = ['bank_settlement_status', 'bank_settlement_note', 'bank_settled_at', 'bank_settled_by', 'bank_settlement_updated_at', 'assigned_bank_user_id'];
    $missing_columns = [];
    foreach ($required_columns as $column) {
        $found = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM `{$table}` LIKE %s", $column));
        if (!$found) {
            $missing_columns[] = $column;
        }
    }

    $tracking_status = isset($_GET['bank_tracking_status']) ? sanitize_key(wp_unslash($_GET['bank_tracking_status'])) : 'all';
    if (!in_array($tracking_status, ['all', 'recorded', 'unrecorded'], true)) {
        $tracking_status = 'all';
    }

    $base_where = "TRIM(COALESCE(r.status, '')) = 'اعطا شده' AND COALESCE(r.assigned_bank_user_id, 0) > 0";
    $recorded_sql = mlr_bank_settlement_recorded_sql('r');
    $counts = ['all' => 0, 'recorded' => 0, 'unrecorded' => 0];
    if (!$missing_columns) {
        $count_sql = "SELECT COUNT(*) AS total,
                             SUM(CASE WHEN {$recorded_sql} THEN 1 ELSE 0 END) AS recorded,
                             SUM(CASE WHEN {$recorded_sql} THEN 0 ELSE 1 END) AS unrecorded
                      FROM {$table} r WHERE {$base_where}";
        $count_row = $wpdb->get_row($count_sql);
        if ($count_row) {
            $counts['all'] = (int) $count_row->total;
            $counts['recorded'] = (int) $count_row->recorded;
            $counts['unrecorded'] = (int) $count_row->unrecorded;
        }
    }

    $filter = mlr_bank_settlement_build_where(true);
    $items_per_page = 25;
    $current_page = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
    $offset = ($current_page - 1) * $items_per_page;
    $total_items = 0;
    $rows = [];

    if (!$missing_columns) {
        $total_sql = "SELECT COUNT(r.id) FROM {$table} r WHERE {$filter['sql']}";
        $total_items = (int) $wpdb->get_var(mlr_bank_settlement_prepare_query($total_sql, $filter['params']));

        $list_sql = "SELECT r.*, b.display_name AS bank_expert_name, rec.display_name AS settlement_recorder_name, p.name AS plan_name
                     FROM {$table} r
                     LEFT JOIN {$users} b ON b.ID = r.assigned_bank_user_id
                     LEFT JOIN {$users} rec ON rec.ID = r.bank_settled_by
                     LEFT JOIN {$plans} p ON p.id = r.installment_plan_id
                     WHERE {$filter['sql']}
                     ORDER BY COALESCE(r.bank_settlement_updated_at, r.last_edited_at, r.created_at) DESC, r.id DESC
                     LIMIT %d OFFSET %d";
        $list_params = array_merge($filter['params'], [$items_per_page, $offset]);
        $rows = $wpdb->get_results($wpdb->prepare($list_sql, $list_params));
        if (!is_array($rows)) {
            $rows = [];
        }
    }

    $total_pages = max(1, (int) ceil($total_items / $items_per_page));
    $bank_users = get_users([
        'role' => 'bank',
        'orderby' => 'display_name',
        'order' => 'ASC',
        'fields' => ['ID', 'display_name'],
    ]);

    $current_bank_user_id = isset($_GET['bank_user_id']) ? absint($_GET['bank_user_id']) : 0;
    $search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
    $date_from = isset($_GET['bank_date_from']) ? sanitize_text_field(wp_unslash($_GET['bank_date_from'])) : '';
    $date_to = isset($_GET['bank_date_to']) ? sanitize_text_field(wp_unslash($_GET['bank_date_to'])) : '';

    $base_tab_args = ['page' => 'mlr-bank-settlements'];
    foreach (['s' => $search, 'bank_user_id' => $current_bank_user_id, 'bank_date_from' => $date_from, 'bank_date_to' => $date_to] as $key => $value) {
        if ($value !== '' && $value !== 0) {
            $base_tab_args[$key] = $value;
        }
    }

    $export_args = array_merge($base_tab_args, [
        'bank_tracking_status' => $tracking_status,
        'export_bank_settlements' => 1,
    ]);
    $export_url = wp_nonce_url(add_query_arg($export_args, admin_url('admin.php')), 'mlr_export_bank_settlements');
    ?>
    <div class="wrap mlr-admin-wrap mlr-bank-settlements-page">
        <div class="mlr-page-header">
            <div>
                <h1>تسویه بانک</h1>
                <p class="description">نمای داخلی «تسویه بانک با بابانوئل». این بخش مستقل از تسویه پذیرندگان و سیکل‌های مالی پذیرنده است.</p>
            </div>
        </div>

        <?php if ($missing_columns) : ?>
            <div class="notice notice-error"><p>ساختار دیتابیس تسویه بانک کامل نیست. یک‌بار صفحه مدیریت را تازه‌سازی کنید. ستون‌های موجود نیست: <code><?php echo esc_html(implode(', ', $missing_columns)); ?></code></p></div>
        <?php else : ?>
            <div class="mlr-bank-settlement-summary" role="group" aria-label="خلاصه تسویه بانک">
                <a class="mlr-bank-summary-card <?php echo $tracking_status === 'all' ? 'is-active' : ''; ?>" href="<?php echo esc_url(add_query_arg(array_merge($base_tab_args, ['bank_tracking_status' => 'all']), admin_url('admin.php'))); ?>">
                    <span>همه پرونده‌های بانکی اعطاشده</span><strong><?php echo esc_html(number_format_i18n($counts['all'])); ?></strong>
                </a>
                <a class="mlr-bank-summary-card <?php echo $tracking_status === 'unrecorded' ? 'is-active' : ''; ?>" href="<?php echo esc_url(add_query_arg(array_merge($base_tab_args, ['bank_tracking_status' => 'unrecorded']), admin_url('admin.php'))); ?>">
                    <span>ثبت نشده</span><strong><?php echo esc_html(number_format_i18n($counts['unrecorded'])); ?></strong>
                </a>
                <a class="mlr-bank-summary-card <?php echo $tracking_status === 'recorded' ? 'is-active' : ''; ?>" href="<?php echo esc_url(add_query_arg(array_merge($base_tab_args, ['bank_tracking_status' => 'recorded']), admin_url('admin.php'))); ?>">
                    <span>انجام شده</span><strong><?php echo esc_html(number_format_i18n($counts['recorded'])); ?></strong>
                </a>
            </div>

            <div class="mlr-filters-card">
                <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>">
                    <input type="hidden" name="page" value="mlr-bank-settlements">
                    <input type="hidden" name="bank_tracking_status" value="<?php echo esc_attr($tracking_status); ?>">
                    <div class="mlr-filters-grid mlr-bank-filter-grid">
                        <div class="mlr-filter-item mlr-bank-search-field">
                            <label for="mlr-bank-settlement-search">جستجو</label>
                            <input id="mlr-bank-settlement-search" type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="کد پرونده، نام، کد ملی یا موبایل">
                        </div>
                        <div class="mlr-filter-item">
                            <label for="mlr-bank-user-filter">کارشناس بانک</label>
                            <select id="mlr-bank-user-filter" name="bank_user_id">
                                <option value="0">همه کارشناسان بانک</option>
                                <?php foreach ($bank_users as $bank_user) : ?>
                                    <option value="<?php echo esc_attr($bank_user->ID); ?>" <?php selected($current_bank_user_id, $bank_user->ID); ?>><?php echo esc_html($bank_user->display_name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mlr-filter-item">
                            <label for="mlr-bank-date-from">از تاریخ تسویه بانک</label>
                            <input id="mlr-bank-date-from" class="jalali-datepicker" type="text" name="bank_date_from" value="<?php echo esc_attr($date_from); ?>" placeholder="۱۴۰۵/۰۷/۰۱" autocomplete="off">
                        </div>
                        <div class="mlr-filter-item">
                            <label for="mlr-bank-date-to">تا تاریخ تسویه بانک</label>
                            <input id="mlr-bank-date-to" class="jalali-datepicker" type="text" name="bank_date_to" value="<?php echo esc_attr($date_to); ?>" placeholder="۱۴۰۵/۰۷/۳۰" autocomplete="off">
                        </div>
                        <div class="mlr-filter-buttons">
                            <button type="submit" class="button button-primary">اعمال فیلتر</button>
                            <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=mlr-bank-settlements')); ?>">حذف فیلترها</a>
                            <a class="button button-secondary" href="<?php echo esc_url($export_url); ?>"><span class="dashicons dashicons-media-spreadsheet" aria-hidden="true"></span> خروجی اکسل</a>
                        </div>
                    </div>
                </form>
            </div>

            <div class="mlr-table-wrapper">
                <table class="wp-list-table widefat striped mlr-modern-table loan-requests-table mlr-bank-settlement-table">
                    <thead>
                        <tr>
                            <th>پرونده</th>
                            <th>کارشناس بانک</th>
                            <th>متقاضی</th>
                            <th>کد ملی</th>
                            <th>طرح</th>
                            <th>مبلغ تسهیلات (ریال)</th>
                            <th>ثبت تسویه بانک با بابانوئل</th>
                            <th>تاریخ اعلام‌شده</th>
                            <th>ثبت‌کننده</th>
                            <th>یادداشت داخلی</th>
                            <th>آخرین بروزرسانی</th>
                            <th>جزئیات</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!$rows) : ?>
                        <tr class="no-items"><td colspan="12">پرونده‌ای مطابق این فیلترها یافت نشد.</td></tr>
                    <?php else : ?>
                        <?php foreach ($rows as $row) :
                            $is_recorded = mlr_bank_settlement_status_label($row->bank_settlement_status ?? '') === 'انجام شده';
                            $facility_amount = isset($row->facility_amount) && $row->facility_amount !== '' && $row->facility_amount !== null
                                ? (float) $row->facility_amount
                                : (float) ($row->loan_amount ?? 0);
                            $settled_date = !empty($row->bank_settled_at) && function_exists('mlr_gregorian_to_jalali')
                                ? mlr_gregorian_to_jalali($row->bank_settled_at, 'Y/m/d')
                                : (!empty($row->bank_settled_at) ? substr((string) $row->bank_settled_at, 0, 10) : '—');
                            $updated_at = !empty($row->bank_settlement_updated_at) && function_exists('mlr_gregorian_to_jalali')
                                ? mlr_gregorian_to_jalali($row->bank_settlement_updated_at, 'Y/m/d H:i')
                                : (!empty($row->bank_settlement_updated_at) ? $row->bank_settlement_updated_at : '—');
                            $note = trim((string) ($row->bank_settlement_note ?? ''));
                            $note_short = $note !== '' ? wp_html_excerpt($note, 80, '…') : '—';
                        ?>
                        <tr class="mlr-request-row" data-id="<?php echo esc_attr($row->id); ?>">
                            <td class="column-id"><strong>#<?php echo esc_html($row->id); ?></strong></td>
                            <td><?php echo esc_html($row->bank_expert_name ?: '—'); ?></td>
                            <td><strong><?php echo esc_html($row->full_name ?: '—'); ?></strong></td>
                            <td><?php echo esc_html($row->national_code ?: '—'); ?></td>
                            <td><?php echo esc_html($row->plan_name ?: '—'); ?></td>
                            <td class="column-amount"><?php echo esc_html(number_format($facility_amount)); ?></td>
                            <td><span class="mlr-bank-status-badge <?php echo $is_recorded ? 'is-recorded' : 'is-unrecorded'; ?>"><?php echo $is_recorded ? 'انجام شده' : 'ثبت نشده'; ?></span></td>
                            <td><?php echo esc_html($settled_date); ?></td>
                            <td><?php echo esc_html($row->settlement_recorder_name ?: '—'); ?></td>
                            <td class="mlr-bank-note-cell" title="<?php echo esc_attr($note); ?>"><?php echo esc_html($note_short); ?></td>
                            <td><?php echo esc_html($updated_at); ?></td>
                            <td class="mlr-actions-container">
                                <button type="button" class="button button-small mlr-view-details-granted" data-id="<?php echo esc_attr($row->id); ?>" aria-label="مشاهده جزئیات پرونده <?php echo esc_attr($row->id); ?>">
                                    <span class="dashicons dashicons-visibility" aria-hidden="true"></span>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($total_pages > 1) :
                $page_args = is_array($_GET) ? wp_unslash($_GET) : [];
                unset($page_args['export_bank_settlements'], $page_args['_wpnonce']);
                $page_args['paged'] = '%#%';
                $pagination_base = add_query_arg($page_args, admin_url('admin.php'));
            ?>
                <div class="mlr-pagination-nav">
                    <div class="tablenav-pages">
                        <span class="displaying-num"><?php echo esc_html(number_format_i18n($total_items)); ?> مورد</span>
                        <span class="pagination-links"><?php echo wp_kses_post(paginate_links([
                            'base' => $pagination_base,
                            'format' => '',
                            'prev_text' => '«',
                            'next_text' => '»',
                            'total' => $total_pages,
                            'current' => $current_page,
                        ])); ?></span>
                    </div>
                </div>
            <?php endif; ?>

            <div id="mlr-overlay" style="display:none;" aria-hidden="true"></div>
            <div id="mlr-details-content" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="mlr-modal-title">
                <button id="mlr-close-details" aria-label="بستن پنجره جزئیات">&times;</button>
                <div id="mlr-details-inner"></div>
            </div>
        <?php endif; ?>
    </div>

    <style>
        .mlr-bank-settlements-page .mlr-page-header{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:18px}.mlr-bank-settlements-page .mlr-page-header h1{margin-bottom:6px}.mlr-bank-settlements-page .description{margin:0;color:#646970;max-width:900px;line-height:1.8}.mlr-bank-settlement-summary{display:grid;grid-template-columns:repeat(3,minmax(180px,1fr));gap:12px;margin:18px 0}.mlr-bank-summary-card{display:flex;align-items:center;justify-content:space-between;text-decoration:none;background:#fff;border:1px solid #dcdcde;border-radius:10px;padding:15px 18px;color:#1d2327;box-shadow:0 1px 2px rgba(0,0,0,.03)}.mlr-bank-summary-card strong{font-size:22px}.mlr-bank-summary-card.is-active{border-color:#2271b1;box-shadow:0 0 0 1px #2271b1}.mlr-bank-filter-grid{grid-template-columns:minmax(260px,1.5fr) repeat(3,minmax(180px,1fr));}.mlr-bank-filter-grid input,.mlr-bank-filter-grid select{width:100%}.mlr-bank-filter-grid .mlr-filter-buttons{grid-column:1/-1;display:flex;gap:8px;flex-wrap:wrap}.mlr-bank-settlement-table{min-width:1450px!important}.mlr-bank-status-badge{display:inline-flex;align-items:center;justify-content:center;min-width:82px;padding:5px 9px;border-radius:999px;font-size:12px;font-weight:700}.mlr-bank-status-badge.is-recorded{background:#edfaef;color:#146c2e;border:1px solid #b8e4c2}.mlr-bank-status-badge.is-unrecorded{background:#f6f7f7;color:#50575e;border:1px solid #dcdcde}.mlr-bank-note-cell{max-width:240px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.mlr-bank-settlement-table .column-amount{white-space:nowrap}.mlr-bank-settlement-table .mlr-actions-container{text-align:center}.mlr-bank-settlement-table .mlr-view-details-granted .dashicons{vertical-align:middle}@media(max-width:900px){.mlr-bank-settlement-summary{grid-template-columns:1fr}.mlr-bank-filter-grid{grid-template-columns:1fr}}
    </style>
    <?php
}
