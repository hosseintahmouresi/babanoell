<?php
/**
 * Bank -> Babanoell batch settlement cycles (separate from acceptor settlement cycles).
 * Historical rows are intentionally append-only. Never changes settlement_status,
 * settlement_cycle_id or settlement_date of a loan request.
 */
if (!defined('ABSPATH')) { exit; }

function mlr_bank_cycles_tables() {
    global $wpdb;
    return [$wpdb->prefix . 'mlr_bank_settlement_cycles', $wpdb->prefix . 'mlr_bank_settlement_cycle_items'];
}

function mlr_bank_cycles_tables_ready() {
    global $wpdb;
    foreach (mlr_bank_cycles_tables() as $table) {
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))) !== $table) {
            return false;
        }
    }
    return true;
}

function mlr_bank_cycles_install() {
    if (get_option('mlr_bank_cycles_schema') === '1.0' && mlr_bank_cycles_tables_ready()) { return; }
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    [$cycles, $items] = mlr_bank_cycles_tables();
    $collate = $wpdb->get_charset_collate();
    dbDelta("CREATE TABLE {$cycles} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        bank_user_id bigint(20) unsigned NOT NULL,
        created_by bigint(20) unsigned NOT NULL,
        settlement_date date NOT NULL,
        notes text DEFAULT NULL,
        item_count int(11) unsigned NOT NULL DEFAULT 0,
        total_loan_amount decimal(20,2) NOT NULL DEFAULT 0,
        created_at datetime NOT NULL,
        PRIMARY KEY  (id),
        KEY bank_user_id (bank_user_id),
        KEY settlement_date (settlement_date),
        KEY created_at (created_at)
    ) ENGINE=InnoDB {$collate};");
    dbDelta("CREATE TABLE {$items} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        cycle_id bigint(20) unsigned NOT NULL,
        request_id bigint(20) unsigned NOT NULL,
        applicant_name varchar(255) NOT NULL DEFAULT '',
        national_code varchar(20) NOT NULL DEFAULT '',
        loan_amount decimal(20,2) NOT NULL DEFAULT 0,
        PRIMARY KEY  (id),
        UNIQUE KEY request_id (request_id),
        KEY cycle_id (cycle_id)
    ) ENGINE=InnoDB {$collate};");
    if (mlr_bank_cycles_tables_ready()) { update_option('mlr_bank_cycles_schema', '1.0', false); }
}
add_action('init', 'mlr_bank_cycles_install', 31);

function mlr_bank_cycle_date($date) {
    $date = function_exists('mlr_normalize_digits') ? mlr_normalize_digits($date) : $date;
    $date = trim((string) $date);
    if (!preg_match('~^(\d{4})[/-](\d{1,2})[/-](\d{1,2})$~', $date, $m)) { return false; }
    $jalali = sprintf('%04d/%02d/%02d', (int)$m[1], (int)$m[2], (int)$m[3]);
    if (!function_exists('mlr_jalali_to_gregorian') || !function_exists('mlr_gregorian_to_jalali')) { return false; }
    $gregorian = mlr_jalali_to_gregorian($jalali);
    if (!$gregorian || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $gregorian)) { return false; }
    return mlr_gregorian_to_jalali($gregorian . ' 00:00:00', 'Y/m/d') === $jalali ? $gregorian : false;
}

function mlr_bank_cycle_is_member($request_id) {
    global $wpdb;
    if (!mlr_bank_cycles_tables_ready()) { return false; }
    [, $items] = mlr_bank_cycles_tables();
    return (int) $wpdb->get_var($wpdb->prepare("SELECT cycle_id FROM `{$items}` WHERE request_id = %d LIMIT 1", $request_id));
}

add_action('wp_ajax_mlr_create_bank_settlement_cycle', 'mlr_create_bank_settlement_cycle');
function mlr_create_bank_settlement_cycle() {
    check_ajax_referer('mlr_frontend_nonce', 'nonce');
    $user = function_exists('mlr_get_current_bank_expert_or_error') ? mlr_get_current_bank_expert_or_error() : null;
    if (!$user) { wp_send_json_error(['message' => 'دسترسی نامعتبر است.'], 403); }
    if (!mlr_bank_cycles_tables_ready()) { wp_send_json_error(['message' => 'جداول سیکل بانک آماده نیستند؛ مدیریت وردپرس را باز کنید و دوباره امتحان کنید.'], 503); }

    $ids_input = isset($_POST['request_ids']) ? wp_unslash($_POST['request_ids']) : [];
    if (!is_array($ids_input) || count($ids_input) > 100) {
        wp_send_json_error(['message' => 'حداکثر ۱۰۰ پرونده در یک سیکل قابل انتخاب است.'], 400);
    }
    $ids = array_values(array_unique(array_filter(array_map('absint', $ids_input))));
    sort($ids, SORT_NUMERIC);
    if (!$ids) { wp_send_json_error(['message' => 'حداقل یک پرونده انتخاب کنید.'], 400); }
    $date_str = isset($_POST['settlement_date']) ? sanitize_text_field(wp_unslash($_POST['settlement_date'])) : '';
    $date = mlr_bank_cycle_date($date_str);
    if (!$date) { wp_send_json_error(['message' => 'تاریخ تسویه شمسی معتبر نیست.'], 400); }
    $notes = isset($_POST['notes']) ? sanitize_textarea_field(wp_unslash($_POST['notes'])) : '';
    if ((function_exists('mb_strlen') ? mb_strlen($notes, 'UTF-8') : strlen($notes)) > 3000) { wp_send_json_error(['message' => 'توضیحات باید حداکثر ۳۰۰۰ نویسه باشد.'], 400); }

    global $wpdb;
    [$cycles, $items] = mlr_bank_cycles_tables();
    $requests = $wpdb->prefix . 'loan_requests';
    // A batch must be all-or-nothing. Refuse legacy nontransactional engines.
    foreach ([$requests, $cycles, $items] as $table) {
        if (!function_exists('mlr_table_supports_transactions') || !mlr_table_supports_transactions($table)) {
            wp_send_json_error(['message' => 'برای ثبت امن سیکل، جدول‌های دیتابیس باید از تراکنش پشتیبانی کنند (InnoDB).'], 503);
        }
    }
    if ($wpdb->query('START TRANSACTION') === false) {
        wp_send_json_error(['message' => 'شروع تراکنش دیتابیس ناموفق بود.'], 500);
    }

    $error = '';
    $cycle_id = 0;
    $placeholders = implode(',', array_fill(0, count($ids), '%d'));
    $sql = $wpdb->prepare(
        "SELECT id, assigned_bank_user_id, status, bank_settlement_status, full_name, national_code, loan_amount
         FROM `{$requests}` WHERE id IN ({$placeholders}) ORDER BY id FOR UPDATE", $ids
    );
    $rows = $wpdb->get_results($sql);
    if (count((array) $rows) !== count($ids)) { $error = 'یک یا چند پرونده پیدا نشدند.'; }
    if (!$error) {
        foreach ($rows as $row) {
            $status = function_exists('mlr_normalize_status') ? mlr_normalize_status($row->status) : trim($row->status);
            if ((int)$row->assigned_bank_user_id !== (int)$user->ID || $status !== 'اعطا شده') {
                $error = 'پرونده #' . $row->id . ' اعطاشده یا متعلق به این کارشناس نیست.'; break;
            }
            if (trim((string)$row->bank_settlement_status) !== '' && trim((string)$row->bank_settlement_status) !== 'در انتظار تسویه') {
                $error = 'پرونده #' . $row->id . ' قبلاً برای تسویه بانک ثبت شده است.'; break;
            }
        }
    }
    if (!$error) {
        $dups = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM `{$items}` WHERE request_id IN ({$placeholders})", $ids));
        if ($dups > 0) { $error = 'حداقل یک پرونده قبلاً در سیکل بانک قرار گرفته است؛ فهرست را تازه‌سازی کنید.'; }
    }
    $total = 0;
    foreach ((array)$rows as $row) { $total += (float)$row->loan_amount; }
    $now = current_time('mysql');
    if (!$error) {
        $ok = $wpdb->insert($cycles, [
            'bank_user_id' => (int)$user->ID, 'created_by' => (int)$user->ID,
            'settlement_date' => $date, 'notes' => $notes,
            'item_count' => count($ids), 'total_loan_amount' => number_format($total, 2, '.', ''),
            'created_at' => $now,
        ]);
        if (!$ok) { $error = 'ساخت سیکل تسویه در دیتابیس ناموفق بود.'; }
        else { $cycle_id = (int)$wpdb->insert_id; }
    }
    if (!$error) {
        foreach ($rows as $row) {
            if (!$wpdb->insert($items, [
                'cycle_id' => $cycle_id, 'request_id' => (int)$row->id,
                'applicant_name' => (string)$row->full_name,
                'national_code' => (string)$row->national_code,
                'loan_amount' => (string)$row->loan_amount,
            ])) { $error = 'ثبت پرونده‌ها در سیکل ناموفق بود یا پرونده تکراری است.'; break; }
            $updated = $wpdb->update($requests, [
                'bank_settlement_status' => 'انجام شده',
                'bank_settled_at' => $date . ' 00:00:00',
                'bank_settled_by' => (int)$user->ID,
                'bank_settlement_note' => $notes,
                'bank_settlement_updated_at' => $now,
            ], ['id' => (int)$row->id, 'assigned_bank_user_id' => (int)$user->ID]);
            if ($updated !== 1) { $error = 'بروزرسانی وضعیت پرونده #' . $row->id . ' ناموفق بود.'; break; }
        }
    }
    if ($error || $wpdb->query('COMMIT') === false) {
        $wpdb->query('ROLLBACK');
        wp_send_json_error(['message' => $error ?: 'ثبت نهایی تراکنش ناموفق بود؛ وضعیت پایگاه داده را بررسی کنید.'], 500);
    }
    if (function_exists('mlr_create_notification')) {
        mlr_create_notification('bank_settlement', 'سیکل تسویه بانک با بابانوئل #' . $cycle_id . ' شامل ' . count($ids) . ' پرونده ثبت شد.', admin_url('admin.php?page=mlr-bank-settlement-cycles&cycle_id=' . $cycle_id));
    }
    wp_send_json_success([
        'message' => 'سیکل تسویه بانک با بابانوئل با موفقیت ثبت شد.',
        'cycle_id' => $cycle_id, 'count' => count($ids),
    ]);
}

// Operators can review cycles only; creation always remains exclusive to bank experts.
add_action('init', function () {
    foreach (['administrator', 'mali', 'amaliat'] as $role_name) {
        $role = get_role($role_name);
        if ($role && !$role->has_cap('mlr_view_bank_cycles')) {
            $role->add_cap('mlr_view_bank_cycles');
        }
    }
}, 22);
add_action('admin_menu', function () {
    add_menu_page('سیکل‌های تسویه بانک با بابانوئل', 'سیکل‌های تسویه بانک', 'mlr_view_bank_cycles', 'mlr-bank-settlement-cycles', 'mlr_bank_settlement_cycles_admin_page', 'dashicons-list-view', 6.26);
}, 25);

function mlr_bank_cycle_jalali($date) {
    if (!$date) { return '—'; }
    return function_exists('mlr_gregorian_to_jalali') ? mlr_gregorian_to_jalali($date, 'Y/m/d') : $date;
}
function mlr_bank_settlement_cycles_admin_page() {
    if (!current_user_can('mlr_view_bank_cycles') && !current_user_can('manage_options')) {
        wp_die('شما مجوز مشاهده سیکل‌های تسویه بانک را ندارید.');
    }
    if (!mlr_bank_cycles_tables_ready()) {
        echo '<div class="wrap"><div class="notice notice-error"><p>جداول سیکل‌های تسویه بانک هنوز ایجاد نشده‌اند؛ صفحه مدیریت را تازه‌سازی کنید.</p></div></div>';
        return;
    }
    global $wpdb;
    [$cycles, $items] = mlr_bank_cycles_tables();
    $page_url = admin_url('admin.php?page=mlr-bank-settlement-cycles');
    $cycle_id = isset($_GET['cycle_id']) ? absint($_GET['cycle_id']) : 0;
    echo '<div class="wrap mlr-bank-cycles-admin" dir="rtl"><style>
      .mlr-bank-cycles-admin{max-width:1320px;line-height:1.85;font-family:inherit}.mlr-bank-cycles-admin h1{font-size:25px;margin:16px 0}
      .mlr-bc-card{background:#fff;border:1px solid #e3e8ed;border-radius:14px;padding:22px;margin:20px 0;box-shadow:0 3px 20px #0a254008}
      .mlr-bc-muted{color:#697586}.mlr-bc-stats{display:flex;gap:16px;flex-wrap:wrap}.mlr-bc-stats>div{padding:14px 19px;background:#f5f8fb;border-radius:9px;min-width:170px}
      .mlr-bank-cycles-admin .widefat{border:0;box-shadow:none}.mlr-bank-cycles-admin .widefat th{font-weight:700;background:#f5f7fa}.mlr-bank-cycles-admin .widefat td,.mlr-bank-cycles-admin .widefat th{padding:13px 10px;text-align:right;vertical-align:middle}
      .mlr-bc-overflow{overflow-x:auto}.mlr-bank-cycles-admin .button{border-radius:7px}
    </style>';
    echo '<h1>سیکل‌های تسویه بانک با بابانوئل</h1><p class="mlr-bc-muted">گزارش اختصاصی سیکل‌های ثبت‌شده توسط کارشناسان بانک؛ مستقل از چرخه تسویه بابانوئل با پذیرندگان.</p>';
    if ($cycle_id) {
        $cycle = $wpdb->get_row($wpdb->prepare("SELECT * FROM `{$cycles}` WHERE id = %d", $cycle_id));
        if (!$cycle) { echo '<div class="notice notice-error"><p>سیکل موردنظر یافت نشد.</p></div>'; }
        else {
            $owner = get_userdata((int)$cycle->bank_user_id);
            $creator = get_userdata((int)$cycle->created_by);
            echo '<div class="mlr-bc-card"><a class="button" href="' . esc_url($page_url) . '">← بازگشت به همه سیکل‌ها</a><h2>جزئیات سیکل #' . esc_html($cycle->id) . '</h2>';
            echo '<div class="mlr-bc-stats"><div><strong>تاریخ تسویه</strong><br>' . esc_html(mlr_bank_cycle_jalali($cycle->settlement_date . ' 00:00:00')) . '</div>';
            echo '<div><strong>کارشناس بانک</strong><br>' . esc_html($owner ? $owner->display_name : '#' . $cycle->bank_user_id) . '</div>';
            echo '<div><strong>ثبت‌کننده</strong><br>' . esc_html($creator ? $creator->display_name : '#' . $cycle->created_by) . '</div>';
            echo '<div><strong>تعداد پرونده</strong><br>' . esc_html($cycle->item_count) . '</div>';
            echo '<div><strong>جمع مبالغ پرونده‌ها (ریال)</strong><br>' . esc_html(number_format((float)$cycle->total_loan_amount)) . '</div></div>';
            echo '<p><strong>زمان ثبت:</strong> ' . esc_html(function_exists('mlr_gregorian_to_jalali') ? mlr_gregorian_to_jalali($cycle->created_at, 'Y/m/d H:i') : $cycle->created_at) . '</p><p><strong>توضیحات:</strong> ' . nl2br(esc_html($cycle->notes ?: '—')) . '</p>';
            $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM `{$items}` WHERE cycle_id = %d ORDER BY id", $cycle_id));
            echo '<div class="mlr-bc-overflow"><table class="widefat striped"><thead><tr><th>شناسه پرونده</th><th>نام متقاضی در زمان ثبت</th><th>کد ملی</th><th>مبلغ (ریال)</th><th>جزئیات پرونده</th></tr></thead><tbody>';
            foreach ($rows as $row) {
                echo '<tr><td>#' . esc_html($row->request_id) . '</td><td>' . esc_html($row->applicant_name) . '</td><td>' . esc_html($row->national_code) . '</td><td>' . esc_html(number_format((float)$row->loan_amount)) . '</td><td><a href="' . esc_url(admin_url('admin.php?page=mlr-loan-requests&s=' . $row->request_id)) . '">مشاهده پرونده</a></td></tr>';
            }
            echo '</tbody></table></div></div>';
        }
    } else {
        $total_cycles = (int)$wpdb->get_var("SELECT COUNT(*) FROM `{$cycles}`");
        $total_cases = (int)$wpdb->get_var("SELECT COALESCE(SUM(item_count), 0) FROM `{$cycles}`");
        $total_amount = (float)$wpdb->get_var("SELECT COALESCE(SUM(total_loan_amount), 0) FROM `{$cycles}`");
        $page = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
        $per_page = 30;
        $offset = ($page - 1) * $per_page;
        $search = isset($_GET['s']) ? trim(sanitize_text_field(wp_unslash($_GET['s']))) : '';
        $bank_filter = isset($_GET['bank_user_id']) ? absint($_GET['bank_user_id']) : 0;
        $date_input = isset($_GET['settlement_date']) ? sanitize_text_field(wp_unslash($_GET['settlement_date'])) : '';
        $date_filter = $date_input !== '' ? mlr_bank_cycle_date($date_input) : false;
        $where = ['1=1'];
        $params = [];
        if ($bank_filter) { $where[] = 'c.bank_user_id = %d'; $params[] = $bank_filter; }
        if ($date_filter) { $where[] = 'c.settlement_date = %s'; $params[] = $date_filter; }
        if ($search !== '') {
            if (ctype_digit(function_exists('mlr_normalize_digits') ? mlr_normalize_digits($search) : $search)) {
                $search_id = (int)(function_exists('mlr_normalize_digits') ? mlr_normalize_digits($search) : $search);
                $where[] = "(c.id = %d OR EXISTS(SELECT 1 FROM `{$items}` ci WHERE ci.cycle_id = c.id AND ci.request_id = %d))";
                $params[] = $search_id; $params[] = $search_id;
            } else { $where[] = '0=1'; }
        }
        $where_sql = implode(' AND ', $where);
        $filtered_total_sql = "SELECT COUNT(*) FROM `{$cycles}` c WHERE {$where_sql}";
        $filtered_total = (int)$wpdb->get_var($params ? $wpdb->prepare($filtered_total_sql, $params) : $filtered_total_sql);
        $sql = "SELECT c.* FROM `{$cycles}` c WHERE {$where_sql} ORDER BY c.id DESC LIMIT %d OFFSET %d";
        $rows = $wpdb->get_results($wpdb->prepare($sql, array_merge($params, [$per_page, $offset])));
        echo '<div class="mlr-bc-stats"><div><strong>سیکل‌های ثبت‌شده</strong><br>' . esc_html($total_cycles) . '</div><div><strong>پرونده‌ها در سیکل‌ها</strong><br>' . esc_html($total_cases) . '</div><div><strong>جمع مبالغ (ریال)</strong><br>' . esc_html(number_format($total_amount)) . '</div></div>';
        echo '<form class="mlr-bc-card" method="get" action="' . esc_url(admin_url('admin.php')) . '"><input type="hidden" name="page" value="mlr-bank-settlement-cycles">';
        echo '<label>جستجوی شناسه سیکل یا پرونده: <input type="text" name="s" value="' . esc_attr($search) . '" placeholder="مثلاً ۱۳۴۸"></label> &nbsp; ';
        echo '<label>شناسه کارشناس بانک: <input type="number" min="1" name="bank_user_id" value="' . esc_attr($bank_filter ?: '') . '" style="max-width:110px"></label> &nbsp; ';
        echo '<label>تاریخ تسویه (شمسی): <input type="text" name="settlement_date" value="' . esc_attr($date_input) . '" placeholder="۱۴۰۵/۰۷/۲۲"></label> &nbsp; ';
        echo '<button type="submit" class="button button-primary">جستجو و فیلتر</button> <a class="button" href="' . esc_url($page_url) . '">پاک‌کردن فیلتر</a>';
        echo '<p class="description">' . esc_html($filtered_total) . ' سیکل مطابق فیلتر یافت شد.</p></form>';
        echo '<div class="mlr-bc-card"><div class="mlr-bc-overflow"><table class="widefat striped"><thead><tr><th>شماره سیکل</th><th>کارشناس بانک</th><th>تاریخ تسویه</th><th>تعداد</th><th>مجموع مبالغ (ریال)</th><th>زمان ایجاد</th><th>جزئیات</th></tr></thead><tbody>';
        if (!$rows) { echo '<tr><td colspan="7">هنوز سیکلی ثبت نشده است.</td></tr>'; }
        foreach ($rows as $row) {
            $owner = get_userdata((int)$row->bank_user_id);
            echo '<tr><td><strong>#' . esc_html($row->id) . '</strong></td><td>' . esc_html($owner ? $owner->display_name : '#' . $row->bank_user_id) . '</td><td>' . esc_html(mlr_bank_cycle_jalali($row->settlement_date . ' 00:00:00')) . '</td><td>' . esc_html($row->item_count) . '</td><td>' . esc_html(number_format((float)$row->total_loan_amount)) . '</td><td>' . esc_html(function_exists('mlr_gregorian_to_jalali') ? mlr_gregorian_to_jalali($row->created_at, 'Y/m/d H:i') : $row->created_at) . '</td><td><a class="button" href="' . esc_url(add_query_arg('cycle_id', $row->id, $page_url)) . '">مشاهده جزئیات</a></td></tr>';
        }
        echo '</tbody></table></div>';
        $pages = (int)ceil($filtered_total / $per_page);
        if ($pages > 1) {
            echo '<p>';
            for ($p = 1; $p <= $pages; $p++) {
                echo ' <a class="button ' . ($p === $page ? 'button-primary' : '') . '" href="' . esc_url(add_query_arg(['paged' => $p, 's' => $search, 'bank_user_id' => $bank_filter, 'settlement_date' => $date_input], $page_url)) . '">' . esc_html($p) . '</a>';
            }
            echo '</p>';
        }
        echo '</div>';
    }
    echo '</div>';
}
