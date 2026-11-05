<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Renders the admin page for managing cooperation requests.
 */
function mlr_cooperation_requests_page() {
    // Check user capabilities
    if (!current_user_can('manage_options')) {
        wp_die('شما دسترسی لازم برای مشاهده این صفحه را ندارید.');
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'cooperation_requests';
    $all_statuses = mlr_get_cooperation_statuses(); // Get defined statuses
    // Get options for "How Heard" filter dropdown from saved settings
    $form_options = get_option('mlr_cooperation_form_options', []);
    $how_heard_options = $form_options['how_heard_options'] ?? []; // Default to empty array if not set

    // --- START: Filter & Search Logic ---

    // Sanitize filter inputs from GET request
    $search_term = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : ''; // Use wp_unslash for safety
    $request_type_filter = isset($_GET['request_type']) ? sanitize_text_field($_GET['request_type']) : '';
    $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
    $how_heard_filter = isset($_GET['how_heard']) ? sanitize_text_field($_GET['how_heard']) : ''; // New filter
    $date_from_jalali = isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : ''; // Created At From
    $date_to_jalali = isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : ''; // Created At To
    $signed_date_from_jalali = isset($_GET['signed_date_from']) ? sanitize_text_field($_GET['signed_date_from']) : ''; // Signed At From
    $signed_date_to_jalali = isset($_GET['signed_date_to']) ? sanitize_text_field($_GET['signed_date_to']) : ''; // Signed At To


    $where_clauses = []; // Array to hold WHERE conditions
    $params = []; // Array for prepared statement parameters (though not strictly needed for LIKE with %)

    // --- Updated Search Logic (Removed Address Search) ---
    if (!empty($search_term)) {
        $search_wild = '%' . $wpdb->esc_like($search_term) . '%'; // Prepare wildcard search term
        // Define JSON fields to search within (excluding address)
        $json_search_fields = [
            '$.store_name', '$.store_owner_name', '$.company_name', '$.contact_person',
            '$.mobile_number', '$.how_heard' // Removed address fields
        ];
        $search_conditions = [];
        foreach ($json_search_fields as $json_path) {
            // Ensure data column exists before using JSON_EXTRACT
            $search_conditions[] = $wpdb->prepare("JSON_UNQUOTE(JSON_EXTRACT(data, %s)) LIKE %s", $json_path, $search_wild);
        }

        // Include ID search if the term is numeric
        if (is_numeric($search_term)) {
             $id_condition = $wpdb->prepare("id = %d", intval($search_term));
             $where_clauses[] = "(" . $id_condition . " OR " . implode(' OR ', $search_conditions) . ")";
        } else {
             $where_clauses[] = "(" . implode(' OR ', $search_conditions) . ")";
        }
    }
    // --- End Updated Search Logic ---

    // Add filters for request type, status, and how_heard
    if (!empty($request_type_filter)) {
        $where_clauses[] = $wpdb->prepare("request_type = %s", $request_type_filter);
    }
    if (!empty($status_filter)) {
        $where_clauses[] = $wpdb->prepare("status = %s", $status_filter);
    }
    if (!empty($how_heard_filter)) {
         $where_clauses[] = $wpdb->prepare("JSON_UNQUOTE(JSON_EXTRACT(data, '$.how_heard')) = %s", $how_heard_filter);
    }

    // Add date range filters for Created At
    if (!empty($date_from_jalali) && ($gregorian_from = mlr_jalali_to_gregorian($date_from_jalali))) {
        $where_clauses[] = $wpdb->prepare("created_at >= %s", $gregorian_from . ' 00:00:00');
    }
    if (!empty($date_to_jalali) && ($gregorian_to = mlr_jalali_to_gregorian($date_to_jalali))) {
        $where_clauses[] = $wpdb->prepare("created_at <= %s", $gregorian_to . ' 23:59:59');
    }

    // Add Signed At Date Filter
    if (!empty($signed_date_from_jalali) && ($signed_gregorian_from = mlr_jalali_to_gregorian($signed_date_from_jalali))) {
        $where_clauses[] = $wpdb->prepare("contract_signed_at >= %s", $signed_gregorian_from . ' 00:00:00');
    }
    if (!empty($signed_date_to_jalali) && ($signed_gregorian_to = mlr_jalali_to_gregorian($signed_date_to_jalali))) {
        $where_clauses[] = $wpdb->prepare("contract_signed_at <= %s", $signed_gregorian_to . ' 23:59:59');
    }

    // Combine all WHERE clauses
    $sql_where = !empty($where_clauses) ? "WHERE " . implode(' AND ', $where_clauses) : '';

    // --- Pagination Removed: show all results without LIMIT/OFFSET ---
    // Get total items count
    $total_items_query = "SELECT COUNT(id) FROM $table_name $sql_where";
    $total_items = $wpdb->get_var($wpdb->prepare($total_items_query, $prepare_params));
    if ($total_items === null) { $total_items = 0; }

    // Get all requests (no pagination)
    $requests_query = "SELECT * FROM $table_name $sql_where ORDER BY created_at DESC";
    $requests = $wpdb->get_results($wpdb->prepare($requests_query, $prepare_params), ARRAY_A);
    // --- END: Filter & Search & Pagination Logic ---

    // Start HTML output
    ?>
    <div class="wrap mlr-admin-wrap mlr-cooperation-requests-wrap">
        <div class="mlr-page-header">
            <h1>مدیریت درخواست‌های همکاری</h1>
            <?php // Potential placeholder for an "Add New" button or similar actions ?>
        </div>

        <?php // Filter Form section (Assuming styles are inherited or globally defined) ?>
        <form method="get" class="mlr-filters-form">
            <input type="hidden" name="page" value="<?php echo esc_attr($_REQUEST['page']); ?>"> <?php // Keep current page context ?>
            <div class="mlr-filters-grid" style="grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));"> <?php /* Adjust column width */ ?>
                <?php // Search Input ?>
                <div class="mlr-filter-item search-item" style="grid-column: span 3;">
                    <label for="s">جستجو</label>
                    <input type="search" id="s" name="s" value="<?php echo esc_attr($search_term); ?>" placeholder="شناسه، نام، موبایل، نحوه آشنایی...">
                </div>
                <?php // Request Type Filter ?>
                <div class="mlr-filter-item">
                    <label for="request_type">نوع درخواست</label>
                    <select id="request_type" name="request_type">
                        <option value="">همه</option>
                        <option value="individual" <?php selected($request_type_filter, 'individual'); ?>>حقیقی</option>
                        <option value="legal" <?php selected($request_type_filter, 'legal'); ?>>حقوقی</option>
                    </select>
                </div>
                <?php // Status Filter ?>
                <div class="mlr-filter-item">
                    <label for="status">وضعیت</label>
                    <select id="status" name="status">
                        <option value="">همه</option>
                        <?php foreach ($all_statuses as $status): ?>
                            <option value="<?php echo esc_attr($status); ?>" <?php selected($status_filter, $status); ?>><?php echo esc_html($status); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                 <?php // How Heard Filter Dropdown ?>
                 <div class="mlr-filter-item">
                    <label for="how_heard">نحوه آشنایی</label>
                    <select id="how_heard" name="how_heard">
                        <option value="">همه</option>
                        <?php // Populate options dynamically from saved settings ?>
                        <?php if (!empty($how_heard_options)): ?>
                            <?php foreach ($how_heard_options as $option): ?>
                                <option value="<?php echo esc_attr($option); ?>" <?php selected($how_heard_filter, $option); ?>><?php echo esc_html($option); ?></option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>
                <?php // Created At Date Filters ?>
                <div class="mlr-filter-item">
                    <label for="date_from">تاریخ ثبت (از)</label>
                    <input type="text" id="date_from" name="date_from" class="jalali-datepicker" value="<?php echo esc_attr($date_from_jalali); ?>" placeholder="انتخاب..." readonly>
                </div>
                <div class="mlr-filter-item">
                    <label for="date_to">تاریخ ثبت (تا)</label>
                    <input type="text" id="date_to" name="date_to" class="jalali-datepicker" value="<?php echo esc_attr($date_to_jalali); ?>" placeholder="انتخاب..." readonly>
                </div>
                 <?php // Signed At Date Filters ?>
                 <div class="mlr-filter-item">
                    <label for="signed_date_from">تاریخ عقد (از)</label>
                    <input type="text" id="signed_date_from" name="signed_date_from" class="jalali-datepicker" value="<?php echo esc_attr($signed_date_from_jalali); ?>" placeholder="انتخاب..." readonly>
                </div>
                <div class="mlr-filter-item">
                    <label for="signed_date_to">تاریخ عقد (تا)</label>
                    <input type="text" id="signed_date_to" name="signed_date_to" class="jalali-datepicker" value="<?php echo esc_attr($signed_date_to_jalali); ?>" placeholder="انتخاب..." readonly>
                </div>
                 <?php // Filter Action Buttons ?>
                <div class="mlr-filter-buttons" style="grid-column: span 2;">
                    <button type="submit" class="button button-primary">فیلتر</button>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=' . esc_attr($_REQUEST['page']))); ?>" class="button">حذف فیلترها</a>
                </div>
            </div>
        </form>

        <?php // Results Table Wrapper ?>
        <div class="mlr-table-wrapper">
            <table class="wp-list-table widefat fixed striped mlr-modern-table cooperation-requests-table">
                <thead>
                    <tr>
                        <th class="manage-column column-id" scope="col">شناسه</th>
                        <th scope="col">نوع</th>
                        <th scope="col">نام فروشگاه / شرکت</th>
                        <th scope="col">نام رابط / مالک</th>
                        <th scope="col">وضعیت</th>
                        <?php /* Address column removed */ ?>
                        <th scope="col" class="column-mobile">موبایل</th>
                        <th scope="col" class="column-how-heard">آشنایی</th>
                        <th scope="col" class="column-date">عقد ق.</th>
                        <th scope="col" class="column-date">پایان ق.</th>
                        <th class="manage-column column-actions" scope="col">جزئیات</th>
                    </tr>
                </thead>
                <tbody id="the-list" data-wp-lists="list:cooperation_request">
                    <?php if (empty($requests)) : ?>
                        <tr class="no-items"><td class="colspanchange" colspan="10">هیچ درخواستی با این مشخصات یافت نشد.</td></tr> <?php // Colspan adjusted ?>
                    <?php else: ?>
                        <?php foreach ($requests as $request) :
                            // Decode JSON data safely, default to empty array if decoding fails or data is null
                            $request_data = json_decode($request['data'], true);
                            if (!is_array($request_data)) $request_data = [];

                            // Extract data for table columns with fallbacks
                            $name = $request['request_type'] === 'individual' ? ($request_data['store_name'] ?? '-') : ($request_data['company_name'] ?? '-');
                            $contact = $request['request_type'] === 'individual' ? ($request_data['store_owner_name'] ?? '-') : ($request_data['contact_person'] ?? '-');
                            $mobile = $request_data['mobile_number'] ?? '-';
                            $how_heard = $request_data['how_heard'] ?? '-'; // Extract how_heard
                            $type_display = $request['request_type'] === 'individual' ? 'حقیقی' : 'حقوقی';
                            // Corrected Date Format
                            $signed_at_jalali = ($request['contract_signed_at']) ? mlr_gregorian_to_jalali($request['contract_signed_at'], 'Y/m/d') : '-'; // Use Y (capital)
                            $expires_at_jalali = ($request['contract_expires_at']) ? mlr_gregorian_to_jalali($request['contract_expires_at'], 'Y/m/d') : '-'; // Use Y (capital)
                        ?>
                        <tr class="mlr-cooperation-request-row" data-id="<?php echo esc_attr($request['id']); ?>">
                            <td class="column-id"><?php echo esc_html($request['id']); ?></td>
                            <td><?php echo esc_html($type_display); ?></td>
                            <td><strong><?php echo esc_html($name); ?></strong></td>
                            <td><?php echo esc_html($contact); ?></td>
                            <td class="column-status">
                                <?php // Inline status editor dropdown ?>
                                <div class="mlr-status-select-wrapper"> <?php // Wrapper Added ?>
                                    <select class="mlr-status-select" data-id="<?php echo esc_attr($request['id']); ?>" aria-label="تغییر وضعیت درخواست <?php echo esc_attr($request['id']); ?>">
                                        <?php foreach ($all_statuses as $status): ?>
                                            <option value="<?php echo esc_attr($status); ?>" <?php selected($request['status'], $status); ?>><?php echo esc_html($status); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <span class="spinner" aria-hidden="true"></span>
                                </div>
                            </td>
                            <?php /* Address Cell Removed */ ?>
                            <td class="column-mobile"><?php echo esc_html($mobile); ?></td>
                            <td class="column-how-heard"><?php echo esc_html($how_heard); ?></td>
                            <td class="column-date"><?php echo esc_html($signed_at_jalali); ?></td>
                            <td class="column-date"><?php echo esc_html($expires_at_jalali); ?></td>
                            <td class="mlr-actions-container column-actions">
                                 <?php // Button to trigger modal view ?>
                                 <button class="mlr-actions-btn-icon" data-id="<?php echo esc_attr($request['id']); ?>" title="مشاهده جزئیات کامل" aria-label="مشاهده جزئیات کامل درخواست <?php echo esc_attr($request['id']); ?>">
                                    <span class="dashicons dashicons-visibility" aria-hidden="true"></span>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div> <?php // End mlr-table-wrapper ?>



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
        /* === START: Modern Cooperation Page Styles (Embedded - V7 Loan Table Style - Centered - Shorter Rows) === */

        /* General Wrap & Header */
        .mlr-cooperation-requests-wrap { padding-top: 15px; background-color: #f6f7f7; }
        .mlr-cooperation-requests-wrap h1 { font-weight: 600; color: #1d2327; margin-bottom: 20px;}
        .mlr-page-header { margin-bottom: 25px;}

        /* Filter Form (Inherited styles assumed) */
        .mlr-filters-form { margin-bottom: 30px; background: #fff; border: 1px solid #dcdcde; border-radius: 6px; padding: 20px 25px; box-shadow: 0 1px 1px rgba(0,0,0,.04); }
        .mlr-filters-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px 18px; align-items: end; }
        .mlr-filter-item label { display: block; font-weight: 500; margin-bottom: 4px; font-size: 12px; color: #444; }
        .mlr-filter-item input, .mlr-filter-item select { width: 100%; box-sizing: border-box; font-size: 13px; height: 30px; vertical-align: middle; margin: 0; padding: 0 8px; border-radius: 4px; border: 1px solid #8c8f94;}
        .mlr-filter-item input[type="search"] { height: 30px; }
        .mlr-filter-item.search-item { grid-column: 1 / -1; }
        @media (min-width: 1200px) { .mlr-filter-item.search-item { grid-column: span 3; } }
        @media (min-width: 960px) and (max-width: 1199px) { .mlr-filter-item.search-item { grid-column: span 2; } }
        .mlr-filter-buttons { display: flex; gap: 8px; align-items: center; grid-column: 1 / -1; margin-top: 10px;}
        @media (min-width: 782px) { .mlr-filter-buttons { grid-column: auto; margin-top: 0;} }
        .mlr-filter-buttons .button { height: 30px; padding: 0 10px; font-size: 13px; line-height: 28px; }

        /* Table Wrapper - Remove extra styling */
        .mlr-table-wrapper { /* No extra styles needed */ }

        /* Table Style - Mimicking Loan Request Table (Separate Rows) - Centered - Shorter */
        .mlr-modern-table { border: none !important; box-shadow: none !important; border-collapse: separate !important; border-spacing: 0 6px !important; /* Reduced spacing */ background: transparent !important; margin-top: 15px !important; width: 100%; }
        .mlr-modern-table thead th { background: transparent !important; border: none !important; border-bottom: none !important; font-size: 12px !important; color: #50575e !important; text-align: center !important; /* Centered Header */ padding: 6px 10px !important; /* Reduced Padding */ font-weight: 500; white-space: nowrap; text-transform: none; letter-spacing: normal; }
        .mlr-modern-table tbody tr { background-color: #fff !important; box-shadow: 0 1px 2px rgba(0,0,0,0.04), 0 1px 1px rgba(0,0,0,0.03) !important; border-radius: 5px !important; /* Slightly smaller radius */ transition: transform 0.15s ease, box-shadow 0.15s ease !important; cursor: pointer; border: 1px solid #dcdcde !important; }
        .mlr-modern-table tbody tr:hover { transform: translateY(-2px) !important; box-shadow: 0 3px 8px rgba(0,0,0,0.07) !important; border-color: #c3c4c7 !important; }
        .mlr-modern-table tbody td { border: none !important; padding: 10px 10px !important; /* Reduced Padding */ vertical-align: middle !important; color: #444; font-size: 13px; line-height: 1.4; text-align: center !important; /* Centered Content */ }
        .mlr-modern-table tbody td:first-child { border-top-right-radius: 5px; border-bottom-right-radius: 5px; }
        .mlr-modern-table tbody td:last-child { border-top-left-radius: 5px; border-bottom-left-radius: 5px; }
        .mlr-modern-table strong { font-weight: 600; color: #1d2327; }
        .mlr-modern-table .column-id { width: 5%; font-weight: 500; color: #646970; }
        .mlr-modern-table .column-actions { width: 60px; }
        .mlr-modern-table .column-date { width: 8%; white-space: nowrap; font-size: 12px; color: #646970;}
        .mlr-modern-table .column-mobile { width: 11%; white-space: nowrap; direction: ltr; /* Keep LTR for mobile */ text-align: center !important; /* Center mobile */ font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;}
        .mlr-modern-table .column-how-heard { width: 10%; }
        .mlr-modern-table .column-status { width: 15%; }
        .mlr-modern-table .no-items td { text-align: center !important; padding: 30px 20px !important; color: #646970; background-color: #f6f7f7 !important; border: 1px dashed #dcdcde !important; box-shadow: none !important; border-radius: 5px !important; }


        .mlr-modern-table .mlr-status-select-wrapper { display: flex; align-items: center; justify-content: center; /* Center select and spinner */ gap: 5px; }
        .mlr-modern-table .mlr-status-select { min-width: 120px; /* Adjust width as needed */ padding: 4px 6px; border-radius: 4px; border: 1px solid #8c8f94; background-position: left 0.4rem center; vertical-align: middle; background-color: #fff; font-size: 12px; height: 28px; line-height: 1.4; color: #3c434a; appearance: none; -webkit-appearance: none; background-image: url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%2350575E%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13-5.4H18.4c-5%200-9.3%201.8-12.9%205.4A17.6%2017.6%200%200%200%200%2082.2c0%205%201.8%209.3%205.4%2012.9l128%20127.9c3.6%203.6%207.8%205.4%2012.8%205.4s9.2-1.8%2012.8-5.4L287%2095c3.5-3.5%205.4-7.8%205.4-12.8%200-5-1.9-9.2-5.5-12.8z%22%2F%3E%3C%2Fsvg%3E'); background-repeat: no-repeat; background-size: .5em auto; padding-left: 1.5em; /* Space for arrow */ }
        .mlr-modern-table .mlr-status-select:focus { border-color: #2271b1; box-shadow: 0 0 0 1px #2271b1; outline: 2px solid transparent; }
        .mlr-modern-table .spinner { float: none; vertical-align: middle; margin: 0; visibility: visible; width: 16px; height: 16px; }

        /* Actions Button - Loan Table Style */
        .mlr-actions-container { text-align: center !important; /* Center button */ padding-left: 5px !important; padding-right: 5px !important;}
        .mlr-actions-btn-icon { background: transparent !important; border: none !important; color: #50575e !important; cursor: pointer; width: 30px; /* Slightly smaller */ height: 30px; border-radius: 50% !important; transition: all 0.2s ease-in-out !important; display: inline-flex !important; align-items: center; justify-content: center; }
        .mlr-actions-btn-icon:hover { background-color: #e3effc !important; color: #2271b1 !important; }
        .mlr-actions-btn-icon .dashicons { font-size: 18px; line-height: 1; } /* Smaller Icon */

        /* Pagination Refined V3 - Minimalist */
        .mlr-pagination-nav { border-radius: 6px; background: #fff; margin-top: 25px; border: 1px solid #dcdcde; box-shadow: 0 1px 1px rgba(0,0,0,.04); padding: 8px 12px; display: flex; justify-content: space-between; align-items: center;}
        .mlr-pagination-nav .displaying-num { color: #50575e; font-size: 13px; margin: 0 !important; line-height: 26px;}
        .mlr-pagination-nav .pagination-links { float: none; display: flex; gap: 4px;}
        .mlr-pagination-nav .pagination-links a,
        .mlr-pagination-nav .pagination-links span { padding: 5px 10px; border-radius: 4px; text-decoration: none; font-size: 13px; line-height: 1.5; border: 1px solid #dcdcde; background: #f6f7f7; color: #2271b1; display: inline-flex; align-items: center; justify-content: center; min-width: 32px; height: 32px;}
        .mlr-pagination-nav .pagination-links a .dashicons,
        .mlr-pagination-nav .pagination-links span .dashicons { font-size: 18px; line-height: 1;}
        .mlr-pagination-nav .pagination-links a:hover { background-color: #f0f0f1; border-color: #c3c4c7; color: #1d2327;}
        .mlr-pagination-nav .pagination-links span.current { color: #444; background-color: #fff; border-color: #dcdcde; font-weight: 600; box-shadow: inset 0 0 0 1px #dcdcde;}
        .mlr-pagination-nav .pagination-links .prev, .mlr-pagination-nav .pagination-links .next { padding: 5px; }
        .mlr-pagination-nav .screen-reader-text { border: 0; clip: rect(1px, 1px, 1px, 1px); -webkit-clip-path: inset(50%); clip-path: inset(50%); height: 1px; margin: -1px; overflow: hidden; padding: 0; position: absolute; width: 1px; word-wrap: normal !important; }


        /* Modal Styles (Ensure consistency - inherit from admin.css or refine here) */
        #mlr-overlay { background: rgba(17, 24, 39, 0.7); backdrop-filter: blur(5px); -webkit-backdrop-filter: blur(5px); }
        #mlr-details-content { background: #f9fafb; border: none; border-radius: 10px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04); max-width: 900px; }
        #mlr-close-details { background: rgba(0,0,0,0.05); color: #6b7280; width: 30px; height: 30px; border-radius: 50%; line-height: 30px; font-size: 16px; top: 15px; left: 15px; }
        #mlr-close-details:hover { background: rgba(0,0,0,0.1); color: #1f2937; }
        .mlr-modal-header { padding: 16px 25px; border-bottom: 1px solid #e5e7eb; background-color: #fff; border-top-left-radius: 10px; border-top-right-radius: 10px; position: sticky; top: 0; z-index: 5; }
        .mlr-modal-header .mlr-modal-title { color: #1f2937; }
        #mlr-details-inner { padding: 25px; }
        .mlr-modal-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 1px 2px rgba(0,0,0,0.03); }
        .mlr-modal-subtitle { color: #4f46e5; border-bottom: 2px solid #a5b4fc; padding: 10px 18px; font-size: 14px; gap: 6px; display: inline-flex; margin-bottom: 15px;}
        .mlr-modal-subtitle .dashicons { font-size: 18px; height: auto; width: auto; color: #6366f1;}
        .mlr-details-table.modern td { padding: 10px 18px; font-size: 13px; border-bottom-color: #f3f4f6;}
        .mlr-details-table.modern td:first-child { background: #f9fafb; color: #4b5563; width: 25%; font-weight: 500;}
        .mlr-details-table.modern td:last-child { color: #374151;}
        .mlr-file-grid { gap: 12px; padding: 18px; }
        .mlr-file-item { background-color: #f9fafb; border-color: #e5e7eb; padding: 12px; }
        .mlr-file-item strong { color: #374151; font-size: 13px;}
        .mlr-file-item .mlr-file-links a { background-color: #eef2ff; border-color: #c7d2fe; color: #4338ca; font-size: 12px; padding: 5px 10px;}
        .mlr-file-item .mlr-file-links a:hover { background-color: #e0e7ff; border-color: #a5b4fc; color: #3730a3; }
        .mlr-contract-link-card { background-color: #f0fdf4; border-color: #bbf7d0; }
        .mlr-contract-link-card .mlr-modal-subtitle { color: #166534; border-bottom-color: #86efac; }
        .mlr-contract-link-content { padding: 15px 18px; }
        .mlr-contract-link-content p { color: #374151; font-size: 13px; }
        .mlr-signature-status-badge.status-pending { background-color: #fef9c3; color: #854d0e; border-color: #fde68a; } /* Amber */
        .mlr-signature-status-badge.status-signed { background-color: #dcfce7; color: #166534; border-color: #86efac; } /* Green */
        /* === END: Modern Styles === */

    </style>
    <?php
}
?>