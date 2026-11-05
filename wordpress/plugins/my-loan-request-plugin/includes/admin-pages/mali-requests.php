<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * صفحه کارشناس مالی بابانوئل (نقش mali).
 * جدول با ستون‌ها و فیلترهای مطابق طرح؛ خروجی اکسل مطابق نتیجه فیلتر.
 * تاریخ تسویه فقط به صورت تاریخ (بدون ساعت).
 */
function mlr_mali_requests_page() {
    if (!function_exists('mlr_can_view_leasing_deposit_amount') || !mlr_can_view_leasing_deposit_amount()) {
        wp_die('شما دسترسی لازم برای مشاهده این صفحه را ندارید.');
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'loan_requests';
    $users_table = $wpdb->prefix . 'users';
    $plans_table = $wpdb->prefix . 'installment_plans';
    $all_valid_statuses = function_exists('mlr_get_all_valid_statuses') ? mlr_get_all_valid_statuses() : [];
    $current_page_slug = 'mlr-mali-requests';

    $use_admin_filters = function_exists('mlr_build_admin_request_filters');
    $need_user_join = false;
    if ($use_admin_filters) {
        $filter_result = mlr_build_admin_request_filters('r', []);
        $sql_where = 'WHERE ' . $filter_result['where'];
        $prepare_params = $filter_result['params'];
        if (!empty($filter_result['acceptor_search_where'])) {
            $sql_where .= ' AND ' . $filter_result['acceptor_search_where'];
            $prepare_params = array_merge($prepare_params, $filter_result['acceptor_search_params']);
            $need_user_join = !empty($filter_result['need_user_join']);
        }
    } else {
        $sql_where = 'WHERE 1=1';
        $prepare_params = [];
    }

    $items_per_page = 20;
    $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $offset = ($current_page - 1) * $items_per_page;
    $effective_acceptor_sql = function_exists('mlr_get_request_effective_acceptor_sql')
        ? mlr_get_request_effective_acceptor_sql('r')
        : 'COALESCE(r.acceptor_user_id, r.user_id)';

    $count_from = $need_user_join ? "FROM $table_name r LEFT JOIN $users_table u ON {$effective_acceptor_sql} = u.ID" : "FROM $table_name r";
    $total_items_query = "SELECT COUNT(r.id) " . $count_from . " " . $sql_where;
    $total_items = !empty($prepare_params) ? $wpdb->get_var($wpdb->prepare($total_items_query, $prepare_params)) : $wpdb->get_var($total_items_query);
    if ($total_items === null) { $total_items = 0; }
    $total_pages = ceil($total_items / $items_per_page);

    $requests_query = "SELECT r.*, u.display_name, p.name AS plan_name
                       FROM $table_name r
                       LEFT JOIN $users_table u ON {$effective_acceptor_sql} = u.ID
                       LEFT JOIN $plans_table p ON r.installment_plan_id = p.id
                       $sql_where
                       ORDER BY r.created_at DESC
                       LIMIT %d OFFSET %d";
    $prepare_params[] = $items_per_page;
    $prepare_params[] = $offset;
    $requests = $wpdb->get_results($wpdb->prepare($requests_query, $prepare_params));

    $bank_users = get_users(['role' => 'bank', 'orderby' => 'display_name', 'order' => 'ASC']);
    $plan_order_sql = function_exists('mlr_get_installment_plan_order_by_sql') ? mlr_get_installment_plan_order_by_sql() : 'ORDER BY id DESC';
    $plans_for_filter = $wpdb->get_results("SELECT id, name FROM $plans_table {$plan_order_sql}");
    $acceptors_for_filter = $wpdb->get_results("SELECT DISTINCT u.ID, u.display_name FROM $users_table u INNER JOIN $table_name r ON {$effective_acceptor_sql} = u.ID WHERE {$effective_acceptor_sql} > 0 ORDER BY u.display_name ASC");

    $num_val = function($key) {
        $v = isset($_GET[$key]) ? $_GET[$key] : '';
        if ($v === '' || $v === null || (string)$v === '0') return '';
        return is_numeric($v) ? (float)$v : '';
    };
    $ic_get = isset($_GET['installment_count']) ? $_GET['installment_count'] : '';
    $ic_display = ($ic_get !== '' && $ic_get !== null && is_numeric($ic_get) && (int)$ic_get > 0) ? (int)$ic_get : '';
    ?>
    <div class="wrap mlr-admin-wrap">
        <div class="mlr-page-header">
            <h1>کارشناس مالی</h1>
        </div>

        <div class="mlr-filters-card">
            <form method="get" id="mlr-mali-filters-form" action="<?php echo esc_url(admin_url('admin.php')); ?>">
                <input type="hidden" name="page" value="<?php echo esc_attr($current_page_slug); ?>">
                <div class="mlr-filters-grid">
                    <div class="mlr-filter-item" style="grid-column: 1 / -1;">
                        <label for="s">جستجو</label>
                        <input type="search" id="s" name="s" value="<?php echo esc_attr(isset($_GET['s']) ? wp_unslash($_GET['s']) : ''); ?>" placeholder="جستجو در نام متقاضی، کد ملی، موبایل، شناسه...">
                    </div>
                    <div class="mlr-filter-item">
                        <label for="status">وضعیت</label>
                        <select id="status" name="status">
                            <option value="">همه وضعیت‌ها</option>
                            <?php foreach ($all_valid_statuses as $stat) : ?>
                                <option value="<?php echo esc_attr($stat); ?>" <?php selected(isset($_GET['status']) ? $_GET['status'] : '', $stat); ?>><?php echo esc_html($stat); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mlr-filter-item">
                        <label for="settlement_status">وضعیت تسویه</label>
                        <select id="settlement_status" name="settlement_status">
                            <option value="">همه</option>
                            <option value="منتظر تعیین تاریخ" <?php selected(isset($_GET['settlement_status']) ? $_GET['settlement_status'] : '', 'منتظر تعیین تاریخ'); ?>>منتظر تعیین تاریخ</option>
                            <option value="در انتظار تسویه" <?php selected(isset($_GET['settlement_status']) ? $_GET['settlement_status'] : '', 'در انتظار تسویه'); ?>>در انتظار تسویه</option>
                            <option value="تسویه شده" <?php selected(isset($_GET['settlement_status']) ? $_GET['settlement_status'] : '', 'تسویه شده'); ?>>تسویه شده</option>
                        </select>
                    </div>
                    <div class="mlr-filter-item">
                        <label for="document_receipt_status">دریافت مدارک</label>
                        <select id="document_receipt_status" name="document_receipt_status">
                            <option value="">همه</option>
                            <?php foreach ((function_exists('mlr_get_document_receipt_status_options') ? mlr_get_document_receipt_status_options() : ['بررسی نشده', 'دریافت شده', 'عدم دریافت', 'عدم نیاز به دریافت مدارک']) as $document_receipt_option) : ?>
                                <option value="<?php echo esc_attr($document_receipt_option); ?>" <?php selected(isset($_GET['document_receipt_status']) ? $_GET['document_receipt_status'] : '', $document_receipt_option); ?>><?php echo esc_html($document_receipt_option); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mlr-filter-item">
                        <label for="plan_id">طرح</label>
                        <select id="plan_id" name="plan_id">
                            <option value="">همه طرح‌ها</option>
                            <?php if (!empty($plans_for_filter)) : foreach ($plans_for_filter as $pl) : ?>
                                <option value="<?php echo esc_attr($pl->id); ?>" <?php selected(isset($_GET['plan_id']) ? (int)$_GET['plan_id'] : 0, (int)$pl->id); ?>><?php echo esc_html($pl->name); ?></option>
                            <?php endforeach; endif; ?>
                        </select>
                    </div>
                    <div class="mlr-filter-item">
                        <label for="installment_count">تعداد اقساط</label>
                        <input type="number" id="installment_count" name="installment_count" value="<?php echo esc_attr($ic_display); ?>" placeholder="—" min="1" style="width:100%;">
                    </div>
                    <div class="mlr-filter-item">
                        <label for="acceptor_search">پذیرنده (جستجو)</label>
                        <input type="text" id="acceptor_search" name="acceptor_search" value="<?php echo esc_attr(isset($_GET['acceptor_search']) ? wp_unslash($_GET['acceptor_search']) : ''); ?>" placeholder="نام پذیرنده..." autocomplete="off">
                    </div>
                    <div class="mlr-filter-item">
                        <label for="acceptor_id">پذیرنده (انتخاب دقیق)</label>
                        <select id="acceptor_id" name="acceptor_id" class="mlr-select-searchable">
                            <option value="">همه</option>
                            <?php if (!empty($acceptors_for_filter)) : foreach ($acceptors_for_filter as $acc) : ?>
                                <option value="<?php echo esc_attr($acc->ID); ?>" <?php selected(isset($_GET['acceptor_id']) ? (int)$_GET['acceptor_id'] : 0, (int)$acc->ID); ?>><?php echo esc_html($acc->display_name); ?></option>
                            <?php endforeach; endif; ?>
                        </select>
                    </div>
                    <div class="mlr-filter-item">
                        <label for="bank_expert_id">کارشناس بانک</label>
                        <select id="bank_expert_id" name="bank_expert_id">
                            <option value="">همه</option>
                            <?php if (!empty($bank_users)) : foreach ($bank_users as $u) : ?>
                                <option value="<?php echo esc_attr($u->ID); ?>" <?php selected(isset($_GET['bank_expert_id']) ? (int)$_GET['bank_expert_id'] : 0, (int)$u->ID); ?>><?php echo esc_html($u->display_name); ?></option>
                            <?php endforeach; endif; ?>
                        </select>
                    </div>
                    <div class="mlr-filter-item">
                        <label for="date_grant_from">تاریخ اعطا از</label>
                        <input type="text" id="date_grant_from" name="date_grant_from" class="jalali-datepicker" value="<?php echo esc_attr(isset($_GET['date_grant_from']) ? wp_unslash($_GET['date_grant_from']) : ''); ?>" placeholder="انتخاب..." readonly>
                    </div>
                    <div class="mlr-filter-item">
                        <label for="date_grant_to">تاریخ اعطا تا</label>
                        <input type="text" id="date_grant_to" name="date_grant_to" class="jalali-datepicker" value="<?php echo esc_attr(isset($_GET['date_grant_to']) ? wp_unslash($_GET['date_grant_to']) : ''); ?>" placeholder="انتخاب..." readonly>
                    </div>
                    <div class="mlr-filter-item">
                        <label for="date_settlement_from">تاریخ تسویه از</label>
                        <input type="text" id="date_settlement_from" name="date_settlement_from" class="jalali-datepicker" value="<?php echo esc_attr(isset($_GET['date_settlement_from']) ? wp_unslash($_GET['date_settlement_from']) : ''); ?>" placeholder="انتخاب..." readonly>
                    </div>
                    <div class="mlr-filter-item">
                        <label for="date_settlement_to">تاریخ تسویه تا</label>
                        <input type="text" id="date_settlement_to" name="date_settlement_to" class="jalali-datepicker" value="<?php echo esc_attr(isset($_GET['date_settlement_to']) ? wp_unslash($_GET['date_settlement_to']) : ''); ?>" placeholder="انتخاب..." readonly>
                    </div>
                    <div class="mlr-filter-item">
                        <label for="amount_facility_from">مبلغ تسهیلات از (ریال)</label>
                        <input type="number" id="amount_facility_from" name="amount_facility_from" value="<?php echo esc_attr($num_val('amount_facility_from')); ?>" placeholder="—" min="0" step="1" style="width:100%;" dir="ltr">
                    </div>
                    <div class="mlr-filter-item">
                        <label for="amount_facility_to">مبلغ تسهیلات تا (ریال)</label>
                        <input type="number" id="amount_facility_to" name="amount_facility_to" value="<?php echo esc_attr($num_val('amount_facility_to')); ?>" placeholder="—" min="0" step="1" style="width:100%;" dir="ltr">
                    </div>
                    <div class="mlr-filter-item">
                        <label for="amount_wallet_from">مبلغ اعتبار خرید از (ریال)</label>
                        <input type="number" id="amount_wallet_from" name="amount_wallet_from" value="<?php echo esc_attr($num_val('amount_wallet_from')); ?>" placeholder="—" min="0" step="1" style="width:100%;" dir="ltr">
                    </div>
                    <div class="mlr-filter-item">
                        <label for="amount_wallet_to">مبلغ اعتبار خرید تا (ریال)</label>
                        <input type="number" id="amount_wallet_to" name="amount_wallet_to" value="<?php echo esc_attr($num_val('amount_wallet_to')); ?>" placeholder="—" min="0" step="1" style="width:100%;" dir="ltr">
                    </div>
                    <div class="mlr-filter-item">
                        <label for="amount_settlement_from">مبلغ تسویه پذیرنده از (ریال)</label>
                        <input type="number" id="amount_settlement_from" name="amount_settlement_from" value="<?php echo esc_attr($num_val('amount_settlement_from')); ?>" placeholder="—" min="0" step="1" style="width:100%;" dir="ltr">
                    </div>
                    <div class="mlr-filter-item">
                        <label for="amount_settlement_to">مبلغ تسویه پذیرنده تا (ریال)</label>
                        <input type="number" id="amount_settlement_to" name="amount_settlement_to" value="<?php echo esc_attr($num_val('amount_settlement_to')); ?>" placeholder="—" min="0" step="1" style="width:100%;" dir="ltr">
                    </div>
                    <div class="mlr-filter-item">
                        <label for="amount_leasing_from">مبلغ واریزی لیزینگ از (ریال)</label>
                        <input type="number" id="amount_leasing_from" name="amount_leasing_from" value="<?php echo esc_attr($num_val('amount_leasing_from')); ?>" placeholder="—" min="0" step="1" style="width:100%;" dir="ltr">
                    </div>
                    <div class="mlr-filter-item">
                        <label for="amount_leasing_to">مبلغ واریزی لیزینگ تا (ریال)</label>
                        <input type="number" id="amount_leasing_to" name="amount_leasing_to" value="<?php echo esc_attr($num_val('amount_leasing_to')); ?>" placeholder="—" min="0" step="1" style="width:100%;" dir="ltr">
                    </div>
                    <div class="mlr-filter-buttons" style="grid-column: 1 / -1;">
                        <button type="submit" class="button button-primary">فیلتر</button>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=' . $current_page_slug)); ?>" class="button">حذف فیلترها</a>
                        <button type="submit" name="export_excel" value="1" class="button button-secondary"><span class="dashicons dashicons-media-spreadsheet" style="vertical-align:middle;"></span> خروجی اکسل</button>
                    </div>
                </div>
            </form>
        </div>

        <div class="mlr-table-wrapper">
            <table class="wp-list-table widefat striped mlr-modern-table loan-requests-table mlr-mali-table" role="grid">
                <colgroup>
                    <col class="col-id">
                    <col class="col-acceptor">
                    <col class="col-applicant">
                    <col class="col-national-code">
                    <col class="col-plan">
                    <col class="col-installment">
                    <col class="col-amount">
                    <col class="col-amount">
                    <col class="col-amount">
                    <col class="col-amount">
                    <col class="col-status">
                    <col class="col-status">
                    <col class="col-status">
                    <col class="col-bank-expert">
                    <col class="col-santa-expert">
                    <col class="col-date">
                    <col class="col-date">
                    <col class="col-actions">
                </colgroup>
                <thead>
                    <tr>
                        <th scope="col">شناسه</th>
                        <th scope="col">پذیرنده</th>
                        <th scope="col">متقاضی</th>
                        <th scope="col">کد ملی</th>
                        <th scope="col">طرح</th>
                        <th scope="col">تعداد اقساط</th>
                        <th scope="col">مبلغ تسهیلات (ریال)</th>
                        <th scope="col">اعتبار خرید (ریال)</th>
                        <th scope="col">مبلغ تسویه پذیرنده (ریال)</th>
                        <th scope="col">مبلغ (واریزی لیزینگ) (ریال)</th>
                        <th scope="col">وضعیت</th>
                        <th scope="col">وضعیت تسویه</th>
                        <th scope="col">دریافت مدارک</th>
                        <th scope="col">کارشناس بانک</th>
                        <th scope="col">کارشناس</th>
                        <th scope="col">تاریخ اعطا</th>
                        <th scope="col">تاریخ تسویه</th>
                        <th scope="col">جزئیات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($requests)) : ?>
                        <tr class="no-items"><td class="colspanchange" colspan="18">هیچ درخواستی یافت نشد.</td></tr>
                    <?php else : ?>
                        <?php foreach ($requests as $request) :
                            $assigned_bank = !empty($request->assigned_bank_user_id) ? get_userdata($request->assigned_bank_user_id) : null;
                            $assigned_babanoel = !empty($request->assigned_babanoel_user_id) ? get_userdata($request->assigned_babanoel_user_id) : null;
                            $acceptor_display_name = $request->display_name ?: 'کاربر حذف شده';
                            if (function_exists('mlr_is_applicant_origin_request') && mlr_is_applicant_origin_request($request) && (!function_exists('mlr_get_request_acceptor_user_id') || !mlr_get_request_acceptor_user_id($request))) {
                                $acceptor_display_name = 'درخواست مستقیم متقاضی';
                            }
                            $request_origin_label = function_exists('mlr_get_request_origin_label') ? mlr_get_request_origin_label($request) : '';
                            $facility_val = (isset($request->facility_amount) && $request->facility_amount !== '' && $request->facility_amount !== null) ? floatval($request->facility_amount) : floatval($request->loan_amount);
                            $wallet_display = (isset($request->wallet_charge) && $request->wallet_charge !== '' && $request->wallet_charge !== null) ? number_format((float)$request->wallet_charge) : '—';
                            $acceptor_settlement_amount_display = function_exists('mlr_format_acceptor_settlement_amount')
                                ? mlr_format_acceptor_settlement_amount($request)
                                : '—';
                            $leasing_deposit_amount_display = function_exists('mlr_format_leasing_deposit_amount')
                                ? mlr_format_leasing_deposit_amount($request)
                                : '—';
                            $settlement_status_display = trim((string)($request->settlement_status ?? '')) ?: '—';
                            $document_receipt_status_display = function_exists('mlr_get_document_receipt_status_label')
                                ? mlr_get_document_receipt_status_label($request->document_receipt_status ?? '')
                                : 'بررسی نشده';
                            $grant_date_display = ($request->status === 'اعطا شده' && !empty($request->last_edited_at)) ? (function_exists('mlr_gregorian_to_jalali') ? mlr_gregorian_to_jalali($request->last_edited_at, 'Y/m/d H:i') : $request->last_edited_at) : '—';
                            $settlement_date_display = !empty($request->settlement_date) ? (function_exists('mlr_gregorian_to_jalali') ? mlr_gregorian_to_jalali($request->settlement_date, 'Y/m/d') : substr($request->settlement_date, 0, 10)) : '—';
                        ?>
                        <tr class="mlr-request-row" data-id="<?php echo esc_attr($request->id); ?>">
                            <td class="column-id"><?php echo esc_html($request->id); ?><?php if ($request_origin_label) : ?><br><small class="mlr-origin-badge"><?php echo esc_html($request_origin_label); ?></small><?php endif; ?></td>
                            <td><?php echo esc_html($acceptor_display_name); ?></td>
                            <td><strong><?php echo esc_html($request->full_name); ?></strong></td>
                            <td class="column-national-code"><?php echo esc_html($request->national_code); ?></td>
                            <td class="column-plan"><?php echo esc_html($request->plan_name ?? '—'); ?></td>
                            <td class="column-installment-count"><?php echo esc_html(isset($request->installment_count) && $request->installment_count !== '' ? $request->installment_count : '—'); ?></td>
                            <td class="column-amount"><?php echo number_format($facility_val); ?></td>
                            <td class="column-amount"><?php echo $wallet_display; ?></td>
                            <td class="column-amount"><?php echo esc_html($acceptor_settlement_amount_display); ?></td>
                            <td class="column-amount"><?php echo esc_html($leasing_deposit_amount_display); ?></td>
                            <td><?php echo esc_html($request->status); ?></td>
                            <td><?php echo esc_html($settlement_status_display); ?></td>
                            <td class="column-document-receipt-status"><?php echo esc_html($document_receipt_status_display); ?></td>
                            <td class="column-bank-expert"><?php echo $assigned_bank ? esc_html($assigned_bank->display_name) : '—'; ?></td>
                            <td class="column-santa-expert"><?php echo $assigned_babanoel ? esc_html($assigned_babanoel->display_name) : '—'; ?></td>
                            <td class="column-date"><?php echo $grant_date_display; ?></td>
                            <td class="column-date"><?php echo $settlement_date_display; ?></td>
                            <td class="mlr-actions-container column-actions">
                                <button class="mlr-actions-btn-icon" data-id="<?php echo esc_attr($request->id); ?>" title="مشاهده جزئیات کامل" aria-label="مشاهده جزئیات درخواست <?php echo esc_attr($request->id); ?>">
                                    <span class="dashicons dashicons-visibility" aria-hidden="true"></span>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($total_pages > 1) : ?>
            <?php
            $pagination_args = is_array($_GET) ? wp_unslash($_GET) : [];
            $pagination_args['paged'] = '%#%';
            $pagination_base = add_query_arg($pagination_args, admin_url('admin.php'));
            ?>
            <div class="mlr-pagination-nav">
                <div class="tablenav-pages">
                    <span class="displaying-num"><?php echo number_format($total_items); ?> آیتم</span>
                    <span class="pagination-links">
                        <?php echo paginate_links([
                            'base' => $pagination_base,
                            'format' => '',
                            'prev_text' => '«',
                            'next_text' => '»',
                            'total' => $total_pages,
                            'current' => $current_page,
                        ]); ?>
                    </span>
                </div>
            </div>
        <?php endif; ?>

        <div id="mlr-overlay" style="display:none;" aria-hidden="true"></div>
        <div id="mlr-details-content" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="mlr-modal-title">
            <button id="mlr-close-details" aria-label="بستن پنجره جزئیات">&times;</button>
            <div id="mlr-details-inner"></div>
        </div>
    </div>

    <style>
        .mlr-mali-table .col-id { width: 72px; }
        .mlr-mali-table .col-acceptor { width: 100px; }
        .mlr-mali-table .col-applicant { width: 120px; }
        .mlr-mali-table .col-national-code { width: 95px; }
        .mlr-mali-table .col-plan { width: 90px; }
        .mlr-mali-table .col-installment { width: 70px; }
        .mlr-mali-table .col-amount { width: 105px; }
        .mlr-mali-table .col-status { width: 95px; }
        .mlr-mali-table .col-bank-expert, .mlr-mali-table .col-santa-expert { width: 100px; }
        .mlr-mali-table .col-date { width: 95px; }
        .mlr-mali-table .col-actions { width: 68px; }
    </style>
    <script>
    (function() {
        var sel = document.getElementById('acceptor_id');
        var searchInput = document.getElementById('acceptor_search');
        if (sel && searchInput) {
            function filterAcceptorOptions() {
                var q = (searchInput.value || '').trim().toLowerCase();
                var opts = sel.querySelectorAll('option');
                for (var i = 0; i < opts.length; i++) {
                    opts[i].disabled = (i === 0) ? false : (q ? opts[i].textContent.toLowerCase().indexOf(q) === -1 : false);
                }
            }
            searchInput.addEventListener('input', filterAcceptorOptions);
            searchInput.addEventListener('change', filterAcceptorOptions);
            filterAcceptorOptions();
        }
        if (typeof jQuery !== 'undefined' && jQuery('#mlr-details-content').length) {
            jQuery(document).on('click', '.mlr-mali-table .mlr-actions-btn-icon', function() {
                var id = jQuery(this).data('id');
                if (id && window.mlrOpenDetailsModal) window.mlrOpenDetailsModal(id);
            });
        }
    })();
    </script>
    <?php
}
