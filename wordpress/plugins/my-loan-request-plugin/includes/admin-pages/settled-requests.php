<?php
function mlr_settled_requests_page() {
    if (!current_user_can('manage_options')) {
        wp_die('شما دسترسی لازم برای مشاهده این صفحه را ندارید.');
    }

    global $wpdb;
    $table_cycles = $wpdb->prefix . 'mlr_settlement_cycles';
    $table_requests = $wpdb->prefix . 'loan_requests'; // Needed for request count and details
    $users_table = $wpdb->prefix . 'users';
    $effective_acceptor_sql = function_exists('mlr_get_request_effective_acceptor_sql')
        ? mlr_get_request_effective_acceptor_sql('r')
        : 'COALESCE(r.acceptor_user_id, r.user_id)';

    if (function_exists('mlr_repair_granted_request_settlement_states_once')) {
        mlr_repair_granted_request_settlement_states_once();
    }

    // فیلتر پذیرنده برای گزارش سیکل‌ها و درخواست‌های تسویه‌شده بدون سیکل
    $acceptor_filter = isset($_GET['acceptor_id']) ? intval($_GET['acceptor_id']) : 0;

    // لیست پذیرنده‌ها برای فیلتر
    $acceptors_for_filter = $wpdb->get_results("
        SELECT DISTINCT u.ID, u.display_name
        FROM $users_table u
        INNER JOIN $table_requests r ON {$effective_acceptor_sql} = u.ID
        WHERE TRIM(COALESCE(r.status, '')) = 'اعطا شده'
          AND {$effective_acceptor_sql} > 0
        ORDER BY u.display_name ASC
    ");

    // Query cycles and count requests within each cycle.
    // بر اساس جدول سیکل، بدون وابستگی سخت به settlement_status تا در صورت تغییر دستی وضعیت، سیکل از گزارش حذف نشود.
    $cycles_params = [];
    $cycles_where = [];
    if ($acceptor_filter > 0) {
        $cycles_where[] = 'c.user_id = %d';
        $cycles_params[] = $acceptor_filter;
    }

    $cycles_sql = "
        SELECT
            c.*,
            u.display_name,
            COUNT(r.id) AS request_count,
            SUM(CASE
                WHEN r.facility_amount IS NOT NULL AND r.facility_amount != '' THEN r.facility_amount
                ELSE r.loan_amount
            END) AS total_facility_amount,
            SUM(CASE
                WHEN r.final_settlement_amount IS NOT NULL AND r.final_settlement_amount != '' THEN r.final_settlement_amount
                WHEN r.acceptor_payment_amount IS NOT NULL AND r.acceptor_payment_amount != '' THEN r.acceptor_payment_amount
                ELSE 0
            END) AS total_acceptor_amount
        FROM $table_cycles c
        INNER JOIN $table_requests r ON c.id = r.settlement_cycle_id
        LEFT JOIN $users_table u ON c.user_id = u.ID
    ";
    if (!empty($cycles_where)) {
        $cycles_sql .= ' WHERE ' . implode(' AND ', $cycles_where);
    }
    $cycles_sql .= ' GROUP BY c.id ORDER BY DATE(c.settlement_date) DESC, c.id DESC';

    if (!empty($cycles_params)) {
        $cycles = $wpdb->get_results($wpdb->prepare($cycles_sql, $cycles_params));
    } else {
        $cycles = $wpdb->get_results($cycles_sql);
    }

    // درخواست‌هایی که وضعیت تسویه آن‌ها «تسویه شده» است ولی به هیچ سیکلی تخصیص نیافته‌اند (مثلاً با ویرایش دستی)
    if ($acceptor_filter > 0) {
        $settled_without_cycle = $wpdb->get_results($wpdb->prepare("
            SELECT r.id, r.full_name, r.national_code, r.final_settlement_amount, r.settlement_date, r.settlement_status, u.display_name AS acceptor_name
            FROM $table_requests r
            LEFT JOIN $users_table u ON {$effective_acceptor_sql} = u.ID
            WHERE TRIM(COALESCE(r.status, '')) = 'اعطا شده'
              AND TRIM(COALESCE(r.settlement_status, '')) = 'تسویه شده'
              AND (r.settlement_cycle_id IS NULL OR r.settlement_cycle_id = 0)
              AND {$effective_acceptor_sql} = %d
            ORDER BY r.settlement_date DESC, r.id DESC
        ", $acceptor_filter));
    } else {
        $settled_without_cycle = $wpdb->get_results("
            SELECT r.id, r.full_name, r.national_code, r.final_settlement_amount, r.settlement_date, r.settlement_status, u.display_name AS acceptor_name
            FROM $table_requests r
            LEFT JOIN $users_table u ON {$effective_acceptor_sql} = u.ID
            WHERE TRIM(COALESCE(r.status, '')) = 'اعطا شده' AND TRIM(COALESCE(r.settlement_status, '')) = 'تسویه شده' AND (r.settlement_cycle_id IS NULL OR r.settlement_cycle_id = 0)
            ORDER BY r.settlement_date DESC, r.id DESC
        ");
    }

    wp_enqueue_script('jquery'); // Ensure jQuery is loaded for JS toggle
    // Ensure admin.js (containing toggle logic) is enqueued (likely done in main plugin file)
    ?>
    <div class="wrap mlr-admin-wrap mlr-settlement-page mlr-settled-page">
        <div class="mlr-page-header">
             <h1><span class="dashicons dashicons-archive"></span> گزارش تسویه‌ها (سیکل‌های تسویه شده)</h1>
        </div>
        <p class="mlr-page-description">در این صفحه می‌توانید تاریخچه سیکل‌های تسویه شده را مشاهده کنید. برای دیدن درخواست‌های هر سیکل، روی ردیف آن کلیک کنید.</p>

        <div class="mlr-filters-card">
            <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>">
                <input type="hidden" name="page" value="mlr-settled-requests">
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
                        <a href="<?php echo esc_url(admin_url('admin.php?page=mlr-settled-requests')); ?>" class="button">حذف فیلترها</a>
                    </div>
                </div>
            </form>
        </div>

        <?php if (!empty($settled_without_cycle)) : ?>
        <div class="mlr-card mlr-table-card mlr-settled-no-cycle-card">
            <h2 class="mlr-card-title"><span class="dashicons dashicons-warning"></span> درخواست‌های تسویه شده بدون سیکل</h2>
            <div class="mlr-card-content no-padding">
                <div class="mlr-table-wrapper">
                <table class="wp-list-table widefat fixed striped mlr-compact-table">
                    <thead>
                        <tr>
                            <th class="column-id">شناسه</th>
                            <th>پذیرنده</th>
                            <th>متقاضی</th>
                            <th>کد ملی</th>
                            <th class="column-amount">مبلغ واریزی پذیرنده (ریال)</th>
                            <th class="column-date">تاریخ تسویه</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($settled_without_cycle as $req) : ?>
                        <tr>
                            <td class="column-id"><?php echo esc_html($req->id); ?></td>
                            <td><?php echo esc_html($req->acceptor_name ?: '—'); ?></td>
                            <td><?php echo esc_html($req->full_name); ?></td>
                            <td><?php echo esc_html($req->national_code); ?></td>
                            <td class="column-amount"><?php echo number_format($req->final_settlement_amount); ?></td>
                            <td class="column-date"><?php echo !empty($req->settlement_date) && function_exists('mlr_gregorian_to_jalali') ? mlr_gregorian_to_jalali($req->settlement_date, 'Y/m/d') : '—'; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="mlr-card mlr-table-card">
            <h2 class="mlr-card-title"><span class="dashicons dashicons-list-view"></span> لیست سیکل‌های تسویه</h2>
            <div class="mlr-card-content no-padding">
                <div class="mlr-table-wrapper">
                <table class="wp-list-table widefat fixed striped mlr-compact-table mlr-expandable-table">
                    <thead>
                        <tr>
                            <th class="mlr-expand-col"></th>
                            <th class="column-id">شناسه سیکل</th>
                            <th>پذیرنده</th>
                            <th>تعداد درخواست</th>
                            <th class="column-amount">مبلغ پرداختی لیزینگ (ریال)</th>
                            <th class="column-amount">مبلغ واریزی پذیرنده (ریال)</th>
                            <th class="column-date">تاریخ تسویه</th>
                            <th>توضیحات</th>
                        </tr>
                    </thead>
                    <tbody id="the-list">
                        <?php if (empty($cycles)) : ?>
                            <tr class="no-items"><td class="colspanchange" colspan="8">هیچ سیکل تسویه شده‌ای یافت نشد.</td></tr>
                        <?php else: ?>
                            <?php foreach ($cycles as $cycle) :
                                $settlement_date_display = mlr_gregorian_to_jalali($cycle->settlement_date, 'Y/m/d');
                            ?>
                            <tr class="mlr-cycle-row" data-cycle-id="<?php echo esc_attr($cycle->id); ?>">
                                <td class="mlr-expand-col toggle-indicator"><span class="dashicons dashicons-arrow-down-alt2"></span></td>
                                <td class="column-id"><strong><?php echo esc_html($cycle->id); ?></strong></td>
                                <td><?php echo esc_html($cycle->display_name ?: 'کاربر حذف شده'); ?></td>
                                <td><?php echo esc_html(number_format_i18n($cycle->request_count)); ?></td>
                                <td class="column-amount"><?php echo number_format((float) ($cycle->total_facility_amount ?? 0)); ?></td>
                                <td class="column-amount"><?php echo number_format((float) ($cycle->total_acceptor_amount ?? 0)); ?></td>
                                <td class="column-date"><?php echo $settlement_date_display; ?></td>
                                <td><?php echo esc_html($cycle->description ?: '---'); ?></td>
                            </tr>
                            <tr class="mlr-cycle-details-row" id="cycle-details-<?php echo esc_attr($cycle->id); ?>" style="display: none;">
                                <td colspan="8" class="cycle-details-container"> <?php // Adjusted colspan ?>
                                    <div class="spinner-container" style="text-align:center; padding: 20px;">
                                        <span class="spinner is-active"></span> در حال بارگذاری جزئیات...
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
                </div>
            </div>
        </div>
        <?php // Modal for request details (loaded via AJAX) ?>
        <div id="mlr-overlay" style="display:none;" aria-hidden="true"></div>
        <div id="mlr-details-content" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="mlr-modal-title">
            <button id="mlr-close-details" aria-label="بستن پنجره جزئیات">&times;</button>
            <div id="mlr-details-inner">
                <?php // Content loaded via AJAX ?>
            </div>
        </div>
    </div>

    <?php // --- START: Add Styles directly --- ?>
    <style>
        /* --- Settlement Pages Common Styles --- */
        .mlr-settlement-page .mlr-page-description { color: #50575e; margin-top: 5px; font-size: 14px; margin-bottom: 20px; padding: 0 5px; }
        .mlr-settlement-page .mlr-card { background: #fff; border: 1px solid #dcdcde; border-radius: 8px; margin-bottom: 25px; box-shadow: 0 1px 1px rgba(0,0,0,.04); overflow: hidden; }
        .mlr-settlement-page .mlr-card-title { font-size: 16px; font-weight: 600; padding: 14px 20px; margin: 0; border-bottom: 1px solid #dcdcde; color: #1d2327; display: flex; align-items: center; gap: 8px; background-color: #f6f7f7; }
        .mlr-settlement-page .mlr-card-title .dashicons { color: #50575e; }
        .mlr-settlement-page .mlr-card-content { padding: 20px; }
        .mlr-settlement-page .mlr-card-content.no-padding { padding: 0; }
        .mlr-settlement-page .mlr-compact-table { margin-bottom: 0; border-collapse: collapse; }
        .mlr-settlement-page .mlr-compact-table th, .mlr-settlement-page .mlr-compact-table thead td { font-size: 13px; padding: 12px 14px; background: #f9f9f9; text-align: right; color: #50575e; border-bottom: 1px solid #e2e8f0; white-space: nowrap;}
        .mlr-settlement-page .mlr-compact-table tbody td, .mlr-settlement-page .mlr-compact-table tbody th { padding: 12px 14px; font-size: 14px; vertical-align: middle; text-align: right; border-bottom: 1px solid #e2e8f0; white-space: nowrap; }
        .mlr-settlement-page .column-id { font-weight: 600; color: #4f46e5; }
        .mlr-settlement-page .mlr-notice { margin: 15px 5px !important; }
        .mlr-settlement-page .no-items td { text-align: center; padding: 25px; color: #50575e; }
        .mlr-settled-page .mlr-settled-no-cycle-card { border-color: #dba617; background: #fefcf0; }
        .mlr-settled-page .mlr-settled-no-cycle-desc { margin: 15px 20px; font-size: 13px; color: #50575e; line-height: 1.6; }

        /* فیلتر بالای صفحه تسویه‌ها */
        .mlr-settlement-page .mlr-filters-card { background: #fff; border-radius: 8px; padding: 16px 20px; box-shadow: 0 1px 2px rgba(0,0,0,0.03); border: 1px solid #dcdcde; margin-bottom: 20px; }
        .mlr-settlement-page .mlr-filters-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px; align-items: end; }
        .mlr-settlement-page .mlr-filter-item label { display: block; font-weight: 500; margin-bottom: 6px; font-size: 13px; color: #50575e; }
        .mlr-settlement-page .mlr-filter-item select { width: 100%; padding: 8px 10px; border: 1px solid #dcdcde; border-radius: 4px; font-size: 13px; background-color: #fff; }
        .mlr-settlement-page .mlr-filter-buttons { display: flex; gap: 8px; }

        /* === UI/UX Fix: Sticky Column & Horizontal Scroll (Option 2) === */
        .mlr-table-wrapper { overflow-x: auto; -webkit-overflow-scrolling: touch; width: 100%; position: relative; border-radius: 0 0 8px 8px; }
        .mlr-table-wrapper table.wp-list-table { min-width: 1200px !important; table-layout: auto !important; width: 100%; border-collapse: collapse; }
        
        /* Sticky Columns */
        .mlr-table-wrapper th.mlr-expand-col, .mlr-table-wrapper td.mlr-expand-col,
        .mlr-table-wrapper th.column-cb, .mlr-table-wrapper td.column-cb {
            position: sticky; right: 0; z-index: 2; width: 44px !important; min-width: 44px !important; max-width: 44px !important; text-align: center !important; padding-right: 5px !important; padding-left: 5px !important;
        }
        .mlr-table-wrapper th.column-id, .mlr-table-wrapper td.column-id {
            position: sticky; right: 44px; z-index: 2; width: 90px !important; min-width: 90px !important; max-width: 90px !important;
        }
        /* Shadow for scrolling depth */
        .mlr-table-wrapper th.column-id::after, .mlr-table-wrapper td.column-id::after {
            content: ''; position: absolute; top: 0; left: 0; bottom: 0; width: 5px; box-shadow: inset 5px 0 5px -5px rgba(0,0,0,0.15); pointer-events: none;
        }
        /* Proper Z-index for headers */
        .mlr-table-wrapper thead th.mlr-expand-col, .mlr-table-wrapper thead th.column-cb, .mlr-table-wrapper thead th.column-id { z-index: 4; background-color: #f9f9f9 !important; }
        
        /* Solidify Row Backgrounds for Sticky Cells */
        .mlr-table-wrapper tbody tr { background-color: #fff !important; }
        .mlr-table-wrapper tbody tr:nth-child(even) { background-color: #f8fafc !important; }
        
        /* Apply explicit backgrounds to sticky cells */
        .mlr-table-wrapper tbody tr td.mlr-expand-col, .mlr-table-wrapper tbody tr td.column-cb, .mlr-table-wrapper tbody tr td.column-id { background-color: #fff !important; }
        .mlr-table-wrapper tbody tr:nth-child(even) td.mlr-expand-col, .mlr-table-wrapper tbody tr:nth-child(even) td.column-cb, .mlr-table-wrapper tbody tr:nth-child(even) td.column-id { background-color: #f8fafc !important; }
        
        /* Hover Override */
        .mlr-table-wrapper tbody tr.mlr-cycle-row:hover td { background-color: #f0f6fc !important; }

        /* --- Settled Requests Page Specific Styles --- */
        .mlr-settled-page .mlr-expandable-table .mlr-cycle-row { cursor: pointer; transition: background-color 0.15s ease; }
        .mlr-settled-page .mlr-expand-col .dashicons { font-size: 20px; transition: transform 0.2s ease-in-out; color: #787c82; }
        .mlr-settled-page .mlr-cycle-row.open .mlr-expand-col .dashicons { transform: rotate(180deg); color: #2271b1; }
        .mlr-settled-page .mlr-cycle-row.open td { background-color: #f0f6fc !important; } /* Highlight open row */
        
        /* Ensure details row does NOT have sticky behavior */
        .mlr-settled-page .mlr-cycle-details-row td { position: static !important; padding: 0 !important; background: #fdfdff !important; border: none !important; }
        .mlr-settled-page .mlr-cycle-details-row td::after { display: none !important; }
        .mlr-settled-page .mlr-cycle-details-row td > div { border-top: 1px solid #d9e8f7 !important; border-bottom: 1px solid #d9e8f7 !important; }
        
        .mlr-settled-page .cycle-details-container { padding: 20px 25px 25px 25px !important; }
        .mlr-settled-page .cycle-details-container .wp-list-table { margin-top: 0; box-shadow: none; border: 1px solid #e5e5e5; border-radius: 4px; min-width: 100% !important; }
        .mlr-settled-page .cycle-details-container .wp-list-table th { background: #f9f9f9; padding: 8px 10px; font-size: 12px;}
        .mlr-settled-page .cycle-details-container .wp-list-table td { padding: 8px 10px; font-size: 13px;}
        .mlr-settled-page .cycle-details-container .spinner-container { color: #50575e; font-size: 13px; }
        .mlr-settled-page .cycle-details-container .spinner { vertical-align: middle; margin-left: 5px; }
        .mlr-settled-page .cycle-details-container .mlr-request-row { cursor: pointer; }
        .mlr-settled-page .cycle-details-container .mlr-request-row:hover td { background-color: #eaf2fa !important; }

        /* --- Modal Styles (Copied from admin.css for consistency if not loaded globally) --- */
        #mlr-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(10, 22, 41, 0.7); z-index: 10000; backdrop-filter: blur(4px); -webkit-backdrop-filter: blur(4px); }
        #mlr-details-content { display: none; position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 90%; max-width: 950px; background: rgba(246, 247, 247, 0.95); border: 1px solid rgba(255, 255, 255, 0.2); z-index: 10001; border-radius: 12px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.3); text-align: right; max-height: 90vh; display: flex; flex-direction: column; }
        #mlr-close-details { position: absolute; top: 12px; left: 15px; border: none; background: rgba(0,0,0,0.1); font-size: 18px; cursor: pointer; color: #333; width: 32px; height: 32px; border-radius: 50%; line-height: 32px; text-align: center; z-index: 10; transition: all .2s; }
        #mlr-close-details:hover { background: rgba(0,0,0,0.2); transform: rotate(90deg); }
        .mlr-modal-header { padding: 16px 25px; border-bottom: 1px solid rgba(0,0,0,0.08); background-color: rgba(255,255,255, 0.85); border-top-left-radius: 12px; border-top-right-radius: 12px; position: sticky; top: 0; z-index: 5; backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px); }
        .mlr-modal-header .mlr-modal-title { padding: 0; margin: 0; font-size: 18px; font-weight: 600; color: #1d2327; border: none; }
        #mlr-details-inner { overflow-y: auto; padding: 25px; }
    </style>
     <?php // --- END: Add Styles --- ?>
    <?php
    // JS for toggling is handled by admin.js, which should be enqueued on this page.
}
?>
