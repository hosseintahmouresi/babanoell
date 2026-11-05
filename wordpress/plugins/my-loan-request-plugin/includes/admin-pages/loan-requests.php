<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Main callback function for all loan request admin pages.
 * It dynamically filters requests based on the 'status' GET parameter or the current menu slug.
 */
function mlr_loan_requests_page() {
    // وضعیت از URL یا از slug منو (برای لینک‌های زیرمنو مثل «در حال بررسی»)
    $page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';
    $status_from_url = isset($_GET['status']) ? sanitize_text_field(wp_unslash($_GET['status'])) : '';
    $page_to_status = [
        'mlr-loan-requests-review'       => 'در حال بررسی',
        'mlr-loan-requests-missing-docs' => 'نقص مدارک',
        'mlr-loan-requests-rejected'     => 'رد شده',
        'mlr-loan-requests-expert-approved' => 'تأیید کارشناس',
        'mlr-loan-requests-docs-complete' => 'تکمیل مدارک',
        'mlr-loan-requests-docs-approved' => 'تأیید مدارک',
    ];
    if (empty($status_from_url) && isset($page_to_status[$page])) {
        $status_from_url = $page_to_status[$page];
        $_GET['status'] = $status_from_url; // تا فیلتر لیست و فرم از همین وضعیت استفاده کنند
    }
    if (!empty($status_from_url) && function_exists('mlr_normalize_status')) {
        $status_from_url = mlr_normalize_status($status_from_url);
        $_GET['status'] = $status_from_url;
    }
    $status_filter = $status_from_url;

    // Define titles for specific status pages.
    $page_titles = [
        'در حال بررسی' => 'درخواست‌های در حال بررسی',
        'نقص مدارک' => 'درخواست‌های نقص مدارک',
        'رد شده' => 'درخواست‌های رد شده',
        'تأیید کارشناس' => 'درخواست‌های تأیید کارشناس',
        'تکمیل مدارک' => 'درخواست‌های تکمیل مدارک',
        'تأیید مدارک' => 'درخواست‌های تأیید مدارک',
    ];

    $page_title = $page_titles[$status_filter] ?? 'مدیریت درخواست‌های تسهیلات';
    mlr_display_requests_page($status_filter, $page_title, $page);
}


/**
 * Renders the main admin page for viewing loan requests, filtered by status.
 *
 * @param string $status_filter The status to filter by (e.g., 'در حال بررسی'). Empty string shows all.
 * @param string $page_title The title to display for the page.
 * @param string $current_page_slug Current admin page slug (e.g. mlr-loan-requests or mlr-loan-requests-review) for form action and hidden inputs.
 */
function mlr_display_requests_page($status_filter = '', $page_title = 'مدیریت درخواست‌ها', $current_page_slug = '') {
    if (empty($current_page_slug)) {
        $current_page_slug = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : 'mlr-loan-requests';
    }
    // Check user capabilities.
    if (!current_user_can('manage_options')) {
        wp_die('شما دسترسی لازم برای مشاهده این صفحه را ندارید.');
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'loan_requests';
    $users_table = $wpdb->prefix . 'users';
    $plans_table = $wpdb->prefix . 'installment_plans';
    if (function_exists('mlr_repair_request_status_labels_once')) {
        mlr_repair_request_status_labels_once();
    }
    $all_valid_statuses = mlr_get_all_valid_statuses(); // Get the list of all possible statuses.
    $can_view_leasing_deposit_amount = function_exists('mlr_can_view_leasing_deposit_amount') && mlr_can_view_leasing_deposit_amount();

    // --- START: Status Transition Rules ---
    // Define the allowed transitions from each status
    $status_transitions = [
        'در حال بررسی' => ['در حال بررسی', 'نقص مدارک', 'رد شده', 'تأیید کارشناس'],
        'نقص مدارک' => ['نقص مدارک', 'در حال بررسی'],
        'تأیید کارشناس' => ['تأیید کارشناس', 'نقص مدارک', 'رد شده', 'تکمیل مدارک'],
        'تکمیل مدارک' => ['تکمیل مدارک', 'تأیید مدارک'],
        'تأیید مدارک' => ['تأیید مدارک', 'اعطا شده', 'تکمیل مدارک'],
        'رد شده' => ['رد شده', 'در حال بررسی'], // Allow reopening a rejected case
        'اعطا شده' => ['اعطا شده'], // Cannot be changed from here (handled by settlement pages)
        // Default fallback: allow all valid statuses if current status is unknown
        'default' => $all_valid_statuses
    ];
    // --- END: Status Transition Rules ---

    // --- Handle Status Update Form Submission ---
    if (isset($_POST['mlr_update_status']) && check_admin_referer('mlr_update_status_action', '_wpnonce')) {
        $request_id = isset($_POST['request_id']) ? intval($_POST['request_id']) : 0;
        $new_status = isset($_POST['status']) ? sanitize_text_field(wp_unslash($_POST['status'])) : '';
        if (function_exists('mlr_normalize_status')) {
            $new_status = mlr_normalize_status($new_status);
        }
        $assigned_bank_user_id = isset($_POST['assigned_bank_user_id']) ? intval($_POST['assigned_bank_user_id']) : 0;
        $document_receipt_status = function_exists('mlr_normalize_document_receipt_status')
            ? mlr_normalize_document_receipt_status(isset($_POST['document_receipt_status']) ? wp_unslash($_POST['document_receipt_status']) : '')
            : 'بررسی نشده';

        // Validate request ID
        if ($request_id > 0) {
            $current_request = $wpdb->get_row($wpdb->prepare("SELECT user_id, applicant_user_id, acceptor_user_id, request_origin, status, settlement_date, assigned_bank_user_id, document_receipt_status FROM $table_name WHERE id = %d", $request_id));

            if ($current_request) {
                // Get the allowed transitions for the *current* status
                $current_status = function_exists('mlr_normalize_status') ? mlr_normalize_status($current_request->status) : $current_request->status;
                $allowed_next_statuses = $status_transitions[$current_status] ?? $status_transitions['default'];

                // *** Check if the new status is allowed ***
                if (!in_array($new_status, $allowed_next_statuses)) {
                     echo '<div id="message" class="error notice is-dismissible"><p>خطا: تغییر وضعیت از "' . esc_html($current_status) . '" به "' . esc_html($new_status) . '" مجاز نیست.</p></div>';
                }
                // Check if bank expert assignment is required
                elseif ($new_status === 'تأیید کارشناس' && empty($assigned_bank_user_id) && $assigned_bank_user_id !== $current_request->assigned_bank_user_id) {
                    echo '<div id="message" class="error notice is-dismissible"><p>خطا: برای وضعیت "تأیید کارشناس"، لطفاً یک کارشناس بانک برای ارجاع انتخاب کنید.</p></div>';
                } else {
                    // All checks passed, prepare data for update
                    $data_to_update = ['last_edited_at' => current_time('mysql')];
                    $is_status_change = $current_status !== $new_status;
                    $current_document_receipt_status = function_exists('mlr_get_document_receipt_status_label')
                        ? mlr_get_document_receipt_status_label($current_request->document_receipt_status ?? '')
                        : trim((string) ($current_request->document_receipt_status ?? ''));
                    $is_document_receipt_status_change = $current_document_receipt_status !== $document_receipt_status;
                    $current_assigned_bank_user_id = (int) ($current_request->assigned_bank_user_id ?? 0);
                    $is_bank_assignment_change = $assigned_bank_user_id > 0 && $assigned_bank_user_id !== $current_assigned_bank_user_id;

                    $data_to_update['status'] = $new_status;
                    $data_to_update['document_receipt_status'] = $document_receipt_status;
                    // Automatically set settlement status when moving to 'اعطا شده'
                    if ($new_status === 'اعطا شده' && $current_status !== 'اعطا شده') {
                        $has_acceptor = function_exists('mlr_get_request_acceptor_user_id') ? mlr_get_request_acceptor_user_id($current_request) > 0 : !empty($current_request->user_id);
                        $is_applicant_direct_without_acceptor = function_exists('mlr_is_applicant_origin_request') && mlr_is_applicant_origin_request($current_request) && !$has_acceptor;
                        $current_settlement_date_key = function_exists('mlr_get_request_settlement_date_key')
                            ? mlr_get_request_settlement_date_key((string) ($current_request->settlement_date ?? ''))
                            : trim((string) ($current_request->settlement_date ?? ''));
                        if ($current_settlement_date_key === '') {
                            $data_to_update['settlement_status'] = 'منتظر تعیین تاریخ';
                        }
                    }
                    // Assign bank expert if selected
                    if ($assigned_bank_user_id > 0) {
                        $data_to_update['assigned_bank_user_id'] = $assigned_bank_user_id;
                    }

                    // Perform the update
                    $update_result = $wpdb->update($table_name, $data_to_update, ['id' => $request_id]);

                    if ($update_result !== false) {
                        echo '<div id="message" class="updated notice is-dismissible"><p>اطلاعات پرونده با موفقیت به‌روزرسانی شد.</p></div>';

                        // Publish a generic transition event after the core workflow update has succeeded.
                        // Additive integrations (such as GreenPay) listen to this without controlling the workflow.
                        if ($is_status_change) {
                            do_action('mlr_loan_status_changed', $request_id, $current_status, $new_status, 'admin_loan_requests');
                        }
                        
                        // --- Added Logging for Status & Settlement Changes ---
                        if (function_exists('mlr_add_request_event')) {
                            if ($is_status_change) {
                                mlr_add_request_event($request_id, 'status_change', 'تغییر وضعیت به: ' . $new_status);
                            }
                            if (isset($data_to_update['settlement_status'])) {
                                mlr_add_request_event($request_id, 'settlement_status_change', 'تغییر وضعیت تسویه به: ' . $data_to_update['settlement_status']);
                            }
                            if ($is_document_receipt_status_change) {
                                mlr_add_request_event($request_id, 'document_receipt_status_change', 'تغییر وضعیت دریافت مدارک به: ' . $document_receipt_status);
                            }
                            if ($is_bank_assignment_change) {
                                $assigned_bank_user = get_userdata($assigned_bank_user_id);
                                $assigned_bank_name = $assigned_bank_user ? $assigned_bank_user->display_name : ('کاربر #' . $assigned_bank_user_id);
                                mlr_add_request_event(
                                    $request_id,
                                    'bank_assignment',
                                    'ارجاع پرونده به کارشناس بانک: ' . $assigned_bank_name . ' (ID: ' . $assigned_bank_user_id . ')'
                                );
                            }
                        }
                        // ----------------------------------------------------

                        // Send SMS only if the status actually changed
                        if ($is_status_change) {
                            mlr_send_sms_on_loan_status_change($request_id, $new_status);
                            // Notify all admins of loan request status change
                            if (function_exists('mlr_create_notification')) {
                                mlr_create_notification('status_change', 'وضعیت درخواست #' . $request_id . ' به «' . $new_status . '» تغییر کرد.', admin_url('admin.php?page=mlr-loan-requests&s=' . $request_id));
                            }
                        }
                    } else {
                         mlr_log("Error updating loan status for ID {$request_id}: " . $wpdb->last_error);
                         echo '<div id="message" class="error notice is-dismissible"><p>خطا در به‌روزرسانی دیتابیس.</p></div>';
                    }
                }
            } else {
                 echo '<div id="message" class="error notice is-dismissible"><p>خطا: درخواست مورد نظر یافت نشد.</p></div>';
            }
        } else {
             echo '<div id="message" class="error notice is-dismissible"><p>خطا: اطلاعات ارسال شده برای به‌روزرسانی وضعیت نامعتبر است.</p></div>';
        }
    }
    // --- End Handle Status Update ---


    // --- Filter & Search Logic (administrator: full filters via mlr_build_admin_request_filters) ---
    $status_from_url = isset($_GET['status']) ? sanitize_text_field(wp_unslash($_GET['status'])) : $status_filter;
    if (!empty($status_from_url) && function_exists('mlr_normalize_status')) {
        $status_from_url = mlr_normalize_status($status_from_url);
    }
    if (!empty($status_from_url) && !in_array($status_from_url, $all_valid_statuses)) {
        $status_from_url = '';
    }

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
        $search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
        $date_from_jalali = isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : '';
        $date_to_jalali = isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : '';
        $date_from_gregorian = !empty($date_from_jalali) ? mlr_jalali_to_gregorian($date_from_jalali) : '';
        $date_to_gregorian = !empty($date_to_jalali) ? mlr_jalali_to_gregorian($date_to_jalali) : '';
        $where_clauses = ["1=1"];
        $prepare_params = [];
        if (!empty($search)) {
            $search_like = '%' . $wpdb->esc_like($search) . '%';
            $where_clauses[] = "(r.full_name LIKE %s OR r.national_code LIKE %s OR r.mobile_number LIKE %s)";
            $prepare_params[] = $search_like;
            $prepare_params[] = $search_like;
            $prepare_params[] = $search_like;
            if (is_numeric($search)) {
                $where_clauses[count($where_clauses) - 1] .= " OR r.id = %d)";
                $prepare_params[] = intval($search);
            }
        }
        if (!empty($status_from_url)) {
            $where_clauses[] = "r.status = %s";
            $prepare_params[] = $status_from_url;
        }
        if (!empty($date_from_gregorian)) {
            $where_clauses[] = "r.created_at >= %s";
            $prepare_params[] = $date_from_gregorian . ' 00:00:00';
        }
        if (!empty($date_to_gregorian)) {
            $where_clauses[] = "r.created_at <= %s";
            $prepare_params[] = $date_to_gregorian . ' 23:59:59';
        }
        $sql_where = "WHERE " . implode(' AND ', $where_clauses);
    }

    // --- Pagination Logic ---
    $items_per_page = 20;
    $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $offset = ($current_page - 1) * $items_per_page;
    $effective_acceptor_sql = function_exists('mlr_get_request_effective_acceptor_sql')
        ? mlr_get_request_effective_acceptor_sql('r')
        : 'COALESCE(r.acceptor_user_id, r.user_id)';

    // Get total items count (وقتی جستجوی پذیرنده داریم باید JOIN کاربر باشد)
    $count_from = $need_user_join ? "FROM $table_name r LEFT JOIN $users_table u ON {$effective_acceptor_sql} = u.ID" : "FROM $table_name r";
    $total_items_query = "SELECT COUNT(r.id) " . $count_from . " " . $sql_where;
    $total_items = !empty($prepare_params)
        ? $wpdb->get_var($wpdb->prepare($total_items_query, $prepare_params))
        : $wpdb->get_var($total_items_query);
    if ($total_items === null) { $total_items = 0; }
    $total_pages = ceil($total_items / $items_per_page);

    // Get requests for the current page (join plans for plan name)
    $requests_query = "SELECT r.*, u.display_name, p.name AS plan_name
                       FROM $table_name r
                       LEFT JOIN $users_table u ON {$effective_acceptor_sql} = u.ID
                       LEFT JOIN $plans_table p ON r.installment_plan_id = p.id
                       $sql_where
                       ORDER BY r.created_at DESC
                       LIMIT %d
                       OFFSET %d";
    $prepare_params[] = $items_per_page;
    $prepare_params[] = $offset;
    $requests = $wpdb->get_results($wpdb->prepare($requests_query, $prepare_params));

    // Get list of users with 'bank' role for assignment dropdown
    $bank_users = get_users(['role' => 'bank', 'orderby' => 'display_name', 'order' => 'ASC']);
    // For administrator filters: plans and acceptors (users who have at least one request)
    $plan_order_sql = function_exists('mlr_get_installment_plan_order_by_sql') ? mlr_get_installment_plan_order_by_sql() : 'ORDER BY id DESC';
    $plans_for_filter = $wpdb->get_results("SELECT id, name FROM $plans_table {$plan_order_sql}");
    $acceptors_for_filter = $wpdb->get_results("SELECT DISTINCT u.ID, u.display_name FROM $users_table u INNER JOIN $table_name r ON {$effective_acceptor_sql} = u.ID WHERE {$effective_acceptor_sql} > 0 ORDER BY u.display_name ASC");
    // --- End Filter & Search & Pagination Logic ---

    // Determine if the actions row should be displayed (only on specific status pages)
    $show_actions_row = !empty($status_filter);

    ?>
    <div class="wrap mlr-admin-wrap">
        <div class="mlr-page-header">
            <h1><?php echo esc_html($page_title); ?></h1>
            <?php // Add New button or other header actions if applicable ?>
        </div>

        <?php // Filters Card (full for administrator) — action صریح تا فیلترها حتماً در URL اعمال شوند ?>
        <div class="mlr-filters-card">
            <form method="get" id="mlr-admin-filters-form" action="<?php echo esc_url(admin_url('admin.php')); ?>">
                <input type="hidden" name="page" value="<?php echo esc_attr($current_page_slug); ?>">
                <?php if (!empty($status_filter)) : ?><input type="hidden" name="status" value="<?php echo esc_attr($status_filter); ?>"><?php endif; ?>
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
                                <option value="<?php echo esc_attr($stat); ?>" <?php selected($status_from_url, $stat); ?>><?php echo esc_html($stat); ?></option>
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
                        <?php
                        $ic_get = isset($_GET['installment_count']) ? $_GET['installment_count'] : '';
                        $ic_display = ($ic_get !== '' && $ic_get !== null && is_numeric($ic_get) && (int)$ic_get > 0) ? (int)$ic_get : '';
                        ?>
                        <input type="number" id="installment_count" name="installment_count" value="<?php echo esc_attr($ic_display); ?>" placeholder="—" min="1" style="width:100%;">
                    </div>
                    <div class="mlr-filter-item">
                        <label for="acceptor_search">جستجو در پذیرنده</label>
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
                        <label for="date_created_from">تاریخ ثبت از</label>
                        <input type="text" id="date_created_from" name="date_created_from" class="jalali-datepicker" value="<?php echo esc_attr(isset($_GET['date_created_from']) ? wp_unslash($_GET['date_created_from']) : ''); ?>" placeholder="انتخاب..." readonly>
                    </div>
                    <div class="mlr-filter-item">
                        <label for="date_created_to">تاریخ ثبت تا</label>
                        <input type="text" id="date_created_to" name="date_created_to" class="jalali-datepicker" value="<?php echo esc_attr(isset($_GET['date_created_to']) ? wp_unslash($_GET['date_created_to']) : ''); ?>" placeholder="انتخاب..." readonly>
                    </div>
                    <div class="mlr-filter-item">
                        <label for="date_edit_from">تاریخ ویرایش از</label>
                        <input type="text" id="date_edit_from" name="date_edit_from" class="jalali-datepicker" value="<?php echo esc_attr(isset($_GET['date_edit_from']) ? wp_unslash($_GET['date_edit_from']) : ''); ?>" placeholder="انتخاب..." readonly>
                    </div>
                    <div class="mlr-filter-item">
                        <label for="date_edit_to">تاریخ ویرایش تا</label>
                        <input type="text" id="date_edit_to" name="date_edit_to" class="jalali-datepicker" value="<?php echo esc_attr(isset($_GET['date_edit_to']) ? wp_unslash($_GET['date_edit_to']) : ''); ?>" placeholder="انتخاب..." readonly>
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
                    <?php
                    $num_val = function($key) {
                        $v = isset($_GET[$key]) ? $_GET[$key] : '';
                        if ($v === '' || $v === null || (string)$v === '0') return '';
                        return is_numeric($v) ? (float)$v : '';
                    };
                    ?>
                    <div class="mlr-filter-item">
                        <label for="amount_facility_from">مبلغ تسهیلات از (ریال)</label>
                        <input type="number" id="amount_facility_from" name="amount_facility_from" value="<?php echo esc_attr($num_val('amount_facility_from')); ?>" placeholder="—" min="0" step="1" style="width:100%;" dir="ltr">
                    </div>
                    <div class="mlr-filter-item">
                        <label for="amount_facility_to">مبلغ تسهیلات تا (ریال)</label>
                        <input type="number" id="amount_facility_to" name="amount_facility_to" value="<?php echo esc_attr($num_val('amount_facility_to')); ?>" placeholder="—" min="0" step="1" style="width:100%;" dir="ltr">
                    </div>
                    <div class="mlr-filter-item">
                        <label for="amount_wallet_from">اعتبار خرید از (ریال)</label>
                        <input type="number" id="amount_wallet_from" name="amount_wallet_from" value="<?php echo esc_attr($num_val('amount_wallet_from')); ?>" placeholder="—" min="0" step="1" style="width:100%;" dir="ltr">
                    </div>
                    <div class="mlr-filter-item">
                        <label for="amount_wallet_to">اعتبار خرید تا (ریال)</label>
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
                    <div class="mlr-filter-buttons" style="grid-column: 1 / -1;">
                        <button type="submit" class="button button-primary">فیلتر</button>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=mlr-loan-requests')); ?>" class="button">حذف فیلترها</a>
                        <button type="submit" name="export_excel" value="1" class="button button-secondary"><span class="dashicons dashicons-media-spreadsheet" style="vertical-align:middle;"></span> خروجی اکسل</button>
                        <button type="submit" name="export_pdf_credit" value="1" class="button button-secondary" formtarget="_blank"><span class="dashicons dashicons-media-document" style="vertical-align:middle;"></span> PDF اعتبارسنجی</button>
                        <button type="submit" name="export_pdf_dossier" value="1" class="button button-secondary" formtarget="_blank"><span class="dashicons dashicons-portfolio" style="vertical-align:middle;"></span> PDF پرونده</button>
                    </div>
                </div>
            </form>
        </div>

        <?php // Results Table Wrapper — colgroup برای عرض ثابت ستون‌ها و جلوگیری از روی هم رفتن ?>
        <div class="mlr-table-wrapper">
            <table class="wp-list-table widefat striped mlr-modern-table loan-requests-table" role="grid">
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
                    <?php if ($can_view_leasing_deposit_amount): ?>
                        <col class="col-amount">
                    <?php endif; ?>
                    <col class="col-status">
                    <col class="col-status">
                    <col class="col-status">
                    <col class="col-bank-expert">
                    <col class="col-santa-expert">
                    <col class="col-date">
                    <col class="col-date">
                    <col class="col-date">
                    <col class="col-date">
                    <col class="col-actions">
                </colgroup>
                <thead>
                    <tr>
                        <th class="manage-column column-id" scope="col">شناسه</th>
                        <th scope="col">پذیرنده</th>
                        <th scope="col">متقاضی</th>
                        <th scope="col" class="column-national-code">کد ملی</th>
                        <th scope="col" class="column-plan">طرح اقساطی</th>
                        <th scope="col" class="column-installment-count">تعداد اقساط</th>
                        <th scope="col" class="column-amount">مبلغ تسهیلات (ریال)</th>
                        <th scope="col" class="column-amount">اعتبار خرید (ریال)</th>
                        <th scope="col" class="column-amount">مبلغ تسویه پذیرنده (ریال)</th>
                        <?php if ($can_view_leasing_deposit_amount): ?>
                            <th scope="col" class="column-amount">مبلغ (واریزی لیزینگ) (ریال)</th>
                        <?php endif; ?>
                        <th scope="col">وضعیت</th>
                        <th scope="col">وضعیت تسویه</th>
                        <th scope="col">دریافت مدارک</th>
                        <th scope="col" class="column-bank-expert">کارشناس بانک</th>
                        <th scope="col" class="column-santa-expert">کارشناس بابانوئل</th>
                        <th scope="col" class="column-date">تاریخ ثبت</th>
                        <th scope="col" class="column-date">تاریخ ویرایش</th>
                        <th scope="col" class="column-date">تاریخ اعطا</th>
                        <th scope="col" class="column-date">تاریخ تسویه</th>
                        <th class="manage-column column-actions" scope="col">
                           <?php echo $show_actions_row ? 'عملیات' : 'جزئیات'; ?>
                        </th>
                    </tr>
                </thead>
                <tbody id="the-list" data-wp-lists="list:loan_request">
                    <?php if (empty($requests)) : ?>
                        <tr class="no-items"><td class="colspanchange" colspan="<?php echo $can_view_leasing_deposit_amount ? 19 : 18; ?>">هیچ درخواستی یافت نشد.</td></tr>
                    <?php else: ?>
                        <?php foreach ($requests as $request) :
                            $assigned_user_info = null;
                            if (!empty($request->assigned_bank_user_id)) {
                                $assigned_user_info = get_userdata($request->assigned_bank_user_id);
                            }
                            $acceptor_display_name = $request->display_name ?: 'کاربر حذف شده';
                            if (function_exists('mlr_is_applicant_origin_request') && mlr_is_applicant_origin_request($request) && (!function_exists('mlr_get_request_acceptor_user_id') || !mlr_get_request_acceptor_user_id($request))) {
                                $acceptor_display_name = 'درخواست مستقیم متقاضی';
                            }
                            $request_origin_label = function_exists('mlr_get_request_origin_label') ? mlr_get_request_origin_label($request) : '';
            $current_row_status = function_exists('mlr_normalize_status') ? mlr_normalize_status($request->status) : $request->status;
                            $allowed_transitions = $status_transitions[$current_row_status] ?? $status_transitions['default'];
                            $facility_val = (isset($request->facility_amount) && $request->facility_amount !== '' && $request->facility_amount !== null) ? floatval($request->facility_amount) : floatval($request->loan_amount);
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
                            $grant_date_display = ($current_row_status === 'اعطا شده' && !empty($request->last_edited_at)) ? mlr_gregorian_to_jalali($request->last_edited_at, 'Y/m/d H:i') : '—';
                        ?>
                        <tr class="mlr-request-row" data-id="<?php echo esc_attr($request->id); ?>">
                            <td class="column-id"><?php echo esc_html($request->id); ?><?php if ($request_origin_label) : ?><br><small class="mlr-origin-badge"><?php echo esc_html($request_origin_label); ?></small><?php endif; ?></td>
                            <td><?php echo esc_html($acceptor_display_name); ?></td>
                            <td><strong><?php echo esc_html($request->full_name); ?></strong></td>
                            <td class="column-national-code"><?php echo esc_html($request->national_code); ?></td>
                            <td class="column-plan"><?php echo esc_html($request->plan_name ?? '—'); ?></td>
                            <td class="column-installment-count"><?php echo esc_html(isset($request->installment_count) && $request->installment_count !== '' ? $request->installment_count : '—'); ?></td>
                            <td class="column-amount"><?php echo number_format($facility_val); ?></td>
                            <td class="column-amount"><?php echo isset($request->wallet_charge) && $request->wallet_charge !== '' && $request->wallet_charge !== null ? number_format((float)$request->wallet_charge) : '—'; ?></td>
                            <td class="column-amount"><?php echo esc_html($acceptor_settlement_amount_display); ?></td>
                            <?php if ($can_view_leasing_deposit_amount): ?>
                                <td class="column-amount"><?php echo esc_html($leasing_deposit_amount_display); ?></td>
                            <?php endif; ?>
                            <td><?php echo esc_html($current_row_status); ?></td>
                            <td><?php echo esc_html($settlement_status_display); ?></td>
                            <td class="column-document-receipt-status"><?php echo esc_html($document_receipt_status_display); ?></td>
                            <td class="column-bank-expert"><?php echo $assigned_user_info ? esc_html($assigned_user_info->display_name) : '—'; ?></td>
                            <td class="column-santa-expert"><?php
                                if (!empty($request->assigned_babanoel_user_id)) {
                                    $babanoel_user = get_userdata($request->assigned_babanoel_user_id);
                                    echo $babanoel_user ? esc_html($babanoel_user->display_name) : '—';
                                } else {
                                    echo '—';
                                }
                            ?></td>
                            <td class="column-date"><?php echo mlr_gregorian_to_jalali($request->created_at, 'Y/m/d H:i'); ?></td>
                            <td class="column-date"><?php echo !empty($request->last_edited_at) ? mlr_gregorian_to_jalali($request->last_edited_at, 'Y/m/d H:i') : '—'; ?></td>
                            <td class="column-date"><?php echo $grant_date_display; ?></td>
                            <td class="column-date"><?php echo !empty($request->settlement_date) ? mlr_gregorian_to_jalali($request->settlement_date, 'Y/m/d') : '—'; ?></td>
                            <td class="mlr-actions-container column-actions">
                                <button class="mlr-actions-btn-icon" data-id="<?php echo esc_attr($request->id); ?>" title="مشاهده جزئیات کامل" aria-label="مشاهده جزئیات کامل درخواست <?php echo esc_attr($request->id); ?>">
                                    <span class="dashicons dashicons-visibility" aria-hidden="true"></span>
                                </button>
                                <?php // Display toggle only if actions row is enabled for this view ?>
                                <?php if ($show_actions_row): ?>
                                    <button class="mlr-actions-toggle-btn-icon" data-id="<?php echo esc_attr($request->id); ?>" title="تغییر وضعیت" aria-label="تغییر وضعیت درخواست <?php echo esc_attr($request->id); ?>">
                                        <span class="dashicons dashicons-admin-generic" aria-hidden="true"></span>
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php // Conditionally display the actions row ?>
                        <?php if ($show_actions_row): ?>
                            <tr class="mlr-actions-row" id="actions-row-<?php echo esc_attr($request->id); ?>" style="display:none;">
                                <td colspan="<?php echo $can_view_leasing_deposit_amount ? 19 : 18; ?>">
                                    <div class="action-buttons">
                                        <form method="post" action="<?php echo esc_url(remove_query_arg('settings-updated')); // Prevent resubmission message ?>">
                                            <?php wp_nonce_field('mlr_update_status_action', '_wpnonce'); ?>
                                            <input type="hidden" name="request_id" value="<?php echo esc_attr($request->id); ?>">

                                            <?php // *** MODIFIED: Status Dropdown with Transition Logic *** ?>
                                            <?php if ($current_row_status !== 'اعطا شده'): ?>
                                                <label for="status_<?php echo esc_attr($request->id); ?>" class="screen-reader-text">وضعیت جدید</label>
                                                <select name="status" id="status_<?php echo esc_attr($request->id); ?>" class="mlr-status-select-action">
                                                    <?php // Loop through the *allowed* transitions for this status ?>
                                                    <?php foreach ($allowed_transitions as $stat) : ?>
                                                        <option value="<?php echo esc_attr($stat); ?>" <?php selected($current_row_status, $stat); ?>>
                                                            <?php echo esc_html($stat); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            <?php else: ?>
                                                <span class="mlr-status-message">وضعیت اصلی این درخواست نهایی شده است.</span>
                                            <?php endif; ?>
                                            <?php // *** END MODIFICATION *** ?>


                                            <?php // Bank expert assignment dropdown - Show only for relevant statuses ?>
                                            <?php if (in_array($current_row_status, ['در حال بررسی', 'نقص مدارک', 'تأیید کارشناس'])) : ?>
                                                <label for="bank_user_<?php echo esc_attr($request->id); ?>" class="screen-reader-text">ارجاع به کارشناس بانک</label>
                                                <select name="assigned_bank_user_id" id="bank_user_<?php echo esc_attr($request->id); ?>" class="mlr-bank-user-select">
                                                    <option value="">-- ارجاع به کارشناس بانک --</option>
                                                    <?php if (!empty($bank_users)): ?>
                                                        <?php foreach ($bank_users as $user): ?>
                                                            <option value="<?php echo esc_attr($user->ID); ?>" <?php selected($request->assigned_bank_user_id, $user->ID); ?>><?php echo esc_html($user->display_name); ?></option>
                                                        <?php endforeach; ?>
                                                    <?php else: ?>
                                                        <option value="" disabled>کارشناس بانکی یافت نشد</option>
                                                    <?php endif; ?>
                                                </select>
                                            <?php endif; ?>

                                            <label for="document_receipt_status_<?php echo esc_attr($request->id); ?>" class="screen-reader-text">دریافت مدارک</label>
                                            <select name="document_receipt_status" id="document_receipt_status_<?php echo esc_attr($request->id); ?>" class="mlr-bank-user-select">
                                                <?php foreach ((function_exists('mlr_get_document_receipt_status_options') ? mlr_get_document_receipt_status_options() : ['بررسی نشده', 'دریافت شده', 'عدم دریافت', 'عدم نیاز به دریافت مدارک']) as $document_receipt_option) : ?>
                                                    <option value="<?php echo esc_attr($document_receipt_option); ?>" <?php selected($document_receipt_status_display, $document_receipt_option); ?>><?php echo esc_html($document_receipt_option); ?></option>
                                                <?php endforeach; ?>
                                            </select>

                                            <?php // Submit button - Hide if status is 'اعطا شده' ?>
                                            <?php if ($current_row_status !== 'اعطا شده'): ?>
                                                <button type="submit" name="mlr_update_status" class="button button-primary mlr-update-button">به‌روزرسانی</button>
                                            <?php endif; ?>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; // End conditional display of actions row ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div> <?php // End mlr-table-wrapper ?>

        <?php // Pagination Navigation — base شامل همه پارامترهای فیلتر تا با صفحه‌بندی از بین نروند ?>
        <?php if ($total_pages > 1): ?>
            <?php
            $pagination_args = is_array($_GET) ? wp_unslash($_GET) : [];
            $pagination_args['paged'] = '%#%';
            $pagination_base = add_query_arg($pagination_args, admin_url('admin.php'));
            ?>
            <div class="mlr-pagination-nav">
                <div class="tablenav-pages">
                    <span class="displaying-num"><?php echo number_format($total_items); ?> آیتم</span>
                    <span class="pagination-links">
                        <?php
                        echo paginate_links([
                            'base' => $pagination_base,
                            'format' => '',
                            'prev_text' => '«',
                            'next_text' => '»',
                            'total' => $total_pages,
                            'current' => $current_page,
                        ]);
                        ?>
                    </span>
                </div>
            </div>
        <?php endif; ?>


        <?php // Modal structure for displaying details ?>
        <div id="mlr-overlay" style="display:none;" aria-hidden="true"></div>
        <div id="mlr-details-content" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="mlr-modal-title">
            <button id="mlr-close-details" aria-label="بستن پنجره جزئیات">&times;</button>
            <div id="mlr-details-inner">
                <?php // Content loaded via AJAX ?>
            </div>
        </div>
    </div> <?php // End wrap ?>

    <?php // Embedded CSS styles ?>
    <style>
        /* === START: Modern Loan Requests Styles (Mimicking Cooperation Style V6/V7) === */

        /* === START: Ultra Modern & Chic Admin Styles (Unified V2) === */
        :root {
            --mlr-primary: #4f46e5; /* Indigo 600 */
            --mlr-primary-hover: #4338ca; /* Indigo 700 */
            --mlr-bg: #f8fafc; /* Slate 50 */
            --mlr-surface: #ffffff;
            --mlr-border: #e2e8f0; /* Slate 200 */
            --mlr-text-main: #1e293b; /* Slate 800 */
            --mlr-text-muted: #64748b; /* Slate 500 */
            --mlr-shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --mlr-shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --mlr-radius: 12px;
        }

        .mlr-admin-wrap { padding: 25px; background-color: var(--mlr-bg); font-family: 'Vazirmatn', system-ui, sans-serif; }
        .mlr-admin-wrap h1 { font-weight: 800; color: var(--mlr-text-main); margin-bottom: 30px; font-size: 26px; letter-spacing: -0.5px; }
        
        /* Filters Card */
        .mlr-filters-card { background: var(--mlr-surface); border-radius: var(--mlr-radius); padding: 24px; box-shadow: var(--mlr-shadow-sm); border: 1px solid var(--mlr-border); margin-bottom: 30px; }
        .mlr-filters-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; align-items: end; }
        .mlr-filter-item label { display: block; font-weight: 600; margin-bottom: 8px; font-size: 13px; color: var(--mlr-text-muted); }
        .mlr-filter-item input, .mlr-filter-item select { width: 100%; padding: 12px 14px; border: 1px solid var(--mlr-border); border-radius: 8px; font-size: 14px; color: var(--mlr-text-main); transition: all 0.2s; background-color: #f8fafc; }
        .mlr-filter-item input:focus, .mlr-filter-item select:focus { border-color: var(--mlr-primary); background-color: #fff; box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1); outline: none; }
        
        .mlr-filter-buttons { display: flex; gap: 10px; }
        .mlr-filter-buttons .button { padding: 10px 20px; height: auto; border-radius: 8px; font-weight: 600; transition: all 0.2s; border: none; }
        .mlr-filter-buttons .button-primary { background: var(--mlr-primary); box-shadow: 0 4px 6px -1px rgba(79, 70, 229, 0.3); }
        .mlr-filter-buttons .button-primary:hover { background: var(--mlr-primary-hover); transform: translateY(-1px); }

        /* جدول: عرض ستون‌ها با colgroup تا محتوا روی هم نرود و ظاهر منظم باشد */
        .mlr-admin-wrap .mlr-table-wrapper { overflow-x: auto; -webkit-overflow-scrolling: touch; margin-top: 16px; border: 1px solid var(--mlr-border); border-radius: var(--mlr-radius); background: var(--mlr-surface); }
        .mlr-admin-wrap .loan-requests-table { border-collapse: collapse; width: 100%; table-layout: fixed; min-width: 1200px; margin: 0; }
        .mlr-admin-wrap .loan-requests-table col.col-id { width: 72px; min-width: 72px; }
        .mlr-admin-wrap .loan-requests-table col.col-acceptor { width: 100px; }
        .mlr-admin-wrap .loan-requests-table col.col-applicant { width: 120px; }
        .mlr-admin-wrap .loan-requests-table col.col-national-code { width: 95px; }
        .mlr-admin-wrap .loan-requests-table col.col-plan { width: 85px; }
        .mlr-admin-wrap .loan-requests-table col.col-installment { width: 70px; }
        .mlr-admin-wrap .loan-requests-table col.col-amount { width: 105px; }
        .mlr-admin-wrap .loan-requests-table col.col-status { width: 95px; }
        .mlr-admin-wrap .loan-requests-table col.col-bank-expert { width: 100px; }
        .mlr-admin-wrap .loan-requests-table col.col-santa-expert { width: 85px; }
        .mlr-admin-wrap .loan-requests-table col.col-date { width: 95px; }
        .mlr-admin-wrap .loan-requests-table col.col-actions { width: 68px; }
        .mlr-admin-wrap .loan-requests-table th,
        .mlr-admin-wrap .loan-requests-table td { box-sizing: border-box; vertical-align: middle; border: 1px solid var(--mlr-border); padding: 10px 8px; text-align: right; font-size: 13px; line-height: 1.35; overflow: hidden; text-overflow: ellipsis; word-break: break-word; }
        .mlr-admin-wrap .loan-requests-table thead th { font-weight: 700; color: var(--mlr-text-muted); background: #f1f5f9; border-bottom: 2px solid var(--mlr-border); }
        .mlr-admin-wrap .loan-requests-table tbody tr { background: var(--mlr-surface); }
        .mlr-admin-wrap .loan-requests-table tbody tr:nth-child(even) { background: #f8fafc; }
        .mlr-admin-wrap .loan-requests-table tbody tr:hover { background: #eef2ff; }
        .mlr-admin-wrap .loan-requests-table tbody td { color: var(--mlr-text-main); }
        .mlr-admin-wrap .loan-requests-table .column-actions { overflow: visible; text-overflow: clip; }
        .mlr-modern-table { margin-top: 10px; }
        
        /* شناسه: همیشه در یک خط و کاملاً پیدا و خوانا */
        .mlr-admin-wrap .loan-requests-table td.column-id,
        .mlr-admin-wrap .loan-requests-table th.column-id { overflow: visible; text-overflow: clip; white-space: nowrap; min-width: 72px; font-weight: 700; color: var(--mlr-primary); }
        .mlr-admin-wrap .loan-requests-table .column-plan { max-width: 90px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .column-amount { font-family: 'Vazirmatn', sans-serif; font-weight: 600; }
        
        /* Actions */
        .mlr-actions-container { display: flex; gap: 8px; justify-content: flex-end; }
        .mlr-actions-btn-icon { width: 36px; height: 36px; border-radius: 10px; background: #f1f5f9; color: var(--mlr-text-muted); border: none; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.2s; }
        .mlr-actions-btn-icon:hover { background: var(--mlr-primary); color: #fff; }
        .mlr-actions-btn-icon .dashicons { font-size: 18px; }

        /* Pagination */
        .mlr-pagination-nav { margin-top: 30px; display: flex; justify-content: center; gap: 5px; }
        .mlr-pagination-nav .pagination-links a, .mlr-pagination-nav .pagination-links span { padding: 8px 14px; background: var(--mlr-surface); border: 1px solid var(--mlr-border); border-radius: 8px; color: var(--mlr-text-main); text-decoration: none; font-weight: 600; transition: all 0.2s; }
        .mlr-pagination-nav .pagination-links a:hover { border-color: var(--mlr-primary); color: var(--mlr-primary); }
        .mlr-pagination-nav .pagination-links .current { background: var(--mlr-primary); color: #fff; border-color: var(--mlr-primary); }

        /* Modal */
        #mlr-details-content { border-radius: 24px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); border: none; overflow: hidden; }
        .mlr-modal-header { background: #fff; padding: 25px 30px; border-bottom: 1px solid var(--mlr-border); }
        .mlr-modal-title { font-size: 20px; font-weight: 800; color: var(--mlr-text-main); }
        #mlr-details-inner { padding: 30px; background: #f8fafc; }
        .mlr-modal-card { background: #fff; border-radius: 16px; padding: 25px; box-shadow: var(--mlr-shadow-sm); border: 1px solid var(--mlr-border); margin-bottom: 25px; }
        .mlr-modal-subtitle { font-size: 16px; font-weight: 700; color: var(--mlr-primary); margin-bottom: 20px; display: flex; align-items: center; gap: 8px; }
        .mlr-modal-subtitle .dashicons { font-size: 20px; width: 20px; height: 20px; }

        /* Details Table */
        .mlr-details-table td { padding: 12px 0; border-bottom: 1px dashed var(--mlr-border); }
        .mlr-details-table tr:last-child td { border-bottom: none; }
        .mlr-details-table td:first-child { color: var(--mlr-text-muted); width: 40%; }
        .mlr-details-table td:last-child { font-weight: 600; color: var(--mlr-text-main); }

        /* === END: Ultra Modern Styles === */

    </style>
    <script>
    (function() {
        var sel = document.getElementById('acceptor_id');
        var searchInput = document.getElementById('acceptor_search');
        if (!sel || !searchInput) return;
        function filterAcceptorOptions() {
            var q = (searchInput.value || '').trim().toLowerCase();
            var opts = sel.querySelectorAll('option');
            for (var i = 0; i < opts.length; i++) {
                if (i === 0) { opts[i].disabled = false; continue; }
                opts[i].disabled = q ? opts[i].textContent.toLowerCase().indexOf(q) === -1 : false;
            }
        }
        searchInput.addEventListener('input', filterAcceptorOptions);
        searchInput.addEventListener('change', filterAcceptorOptions);
        filterAcceptorOptions();
    })();
    </script>
    <?php
}
?>
