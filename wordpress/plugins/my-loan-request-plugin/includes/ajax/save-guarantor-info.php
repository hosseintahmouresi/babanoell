<?php
if (!defined('ABSPATH')) {
    exit;
}

add_action('wp_ajax_mlr_save_guarantor_info', 'mlr_save_guarantor_info_callback');
add_action('wp_ajax_nopriv_mlr_save_guarantor_info', 'mlr_save_guarantor_info_callback');

function mlr_save_guarantor_info_callback() {
    if (ob_get_length()) {
        ob_clean();
    }

    $restore_blog = false;
    if (function_exists('is_multisite') && is_multisite() && function_exists('switch_to_blog') && function_exists('restore_current_blog')) {
        $blog_id = 0;
        if (isset($_GET['blog_id'])) {
            $blog_id = absint($_GET['blog_id']);
        } elseif (isset($_POST['blog_id'])) {
            $blog_id = absint($_POST['blog_id']);
        } elseif (isset($_REQUEST['blog_id'])) {
            $blog_id = absint($_REQUEST['blog_id']);
        }
        if ($blog_id > 0 && get_current_blog_id() !== $blog_id) {
            switch_to_blog($blog_id);
            $restore_blog = true;
        }
    }

    if (!is_user_logged_in()) {
        if ($restore_blog) {
            restore_current_blog();
        }
        wp_send_json_error(['message' => 'لطفاً ابتدا وارد شوید.']);
        return;
    }

    if (!function_exists('mlr_verify_any_nonce') || !mlr_verify_any_nonce('nonce')) {
        if ($restore_blog) {
            restore_current_blog();
        }
        wp_send_json_error(['message' => 'خطای امنیتی. لطفاً صفحه را رفرش کرده و دوباره تلاش کنید.']);
        return;
    }

    $request_id = 0;
    if (isset($_GET['request_id']) && absint($_GET['request_id']) > 0) {
        $request_id = absint($_GET['request_id']);
    }
    if (!$request_id && !empty($_SERVER['HTTP_X_MLR_REQUEST_ID'])) {
        $request_id = absint($_SERVER['HTTP_X_MLR_REQUEST_ID']);
    }
    if (!$request_id && isset($_POST['request_id'])) {
        $raw = is_array($_POST['request_id']) ? (string) reset($_POST['request_id']) : (string) $_POST['request_id'];
        $request_id = absint($raw);
    }
    if (!$request_id && isset($_REQUEST['request_id'])) {
        $raw = is_array($_REQUEST['request_id']) ? (string) reset($_REQUEST['request_id']) : (string) $_REQUEST['request_id'];
        $request_id = absint($raw);
    }
    if (!$request_id) {
        if ($restore_blog) {
            restore_current_blog();
        }
        wp_send_json_error(['message' => 'شناسه درخواست نامعتبر است. لطفاً دوباره جزئیات پرونده را باز کنید و ذخیره کنید.']);
        return;
    }

    global $wpdb;
    $uid = get_current_user_id();
    $current_user = wp_get_current_user();
    $is_admin = current_user_can('manage_options');
    $is_bank_expert = in_array('bank', (array) $current_user->roles, true);

    if (!function_exists('mlr_handle_wp_upload')) {
        if ($restore_blog) {
            restore_current_blog();
        }
        wp_send_json_error(['message' => 'سیستم آپلود در دسترس نیست.']);
        return;
    }

    // پیدا کردن درخواست در هر جدول ممکن؛ وابسته به پیشوند ارسالی از کلاینت نیستیم.
    $table = null;
    $req = null;
    $candidate_tables = [];

    // ۱) جدول با پیشوند فعلی (بعد از switch_to_blog) — همیشه اول
    $current_table = $wpdb->prefix . 'loan_requests';
    $candidate_tables[] = $current_table;

    // ۲) در multisite جدول سایت اصلی را هم امتحان کن
    if (function_exists('is_multisite') && is_multisite()) {
        $base_table = $wpdb->base_prefix . 'loan_requests';
        if ($base_table !== $current_table && !in_array($base_table, $candidate_tables, true)) {
            $candidate_tables[] = $base_table;
        }
    }

    // فقط ستون‌های حتماً موجود را انتخاب کن (guarantor_info ممکن است در نصب‌های قدیمی نباشد و کوئری را خراب کند)
    foreach ($candidate_tables as $candidate) {
        $req = $wpdb->get_row($wpdb->prepare(
            "SELECT id, user_id, applicant_user_id, acceptor_user_id, request_origin, assigned_bank_user_id FROM `{$candidate}` WHERE id = %d LIMIT 1",
            $request_id
        ));
        if ($req) {
            $table = $candidate;
            break;
        }
    }

    if (!$req || !$table) {
        if ($restore_blog) {
            restore_current_blog();
        }
        wp_send_json_error([
            'message' => 'درخواست یافت نشد. (شناسه: ' . $request_id . ') لطفاً مودال را ببندید، روی همان ردیف پرونده کلیک کنید و دوباره ذخیره کنید.',
        ]);
        return;
    }

    $can_edit = function_exists('mlr_can_access_loan_request')
        ? mlr_can_access_loan_request($req)
        : (
            $is_admin
            || ((int) $req->user_id === $uid)
            || ($is_bank_expert && (int) $req->assigned_bank_user_id === $uid)
        );
    if (!$can_edit) {
        if ($restore_blog) {
            restore_current_blog();
        }
        wp_send_json_error(['message' => 'دسترسی غیرمجاز.']);
        return;
    }

    // ضامن اول (۱) یا ضامن دوم (۲)
    $guarantor_index = isset($_POST['guarantor_index']) ? absint($_POST['guarantor_index']) : (isset($_GET['guarantor_index']) ? absint($_GET['guarantor_index']) : 1);
    if ($guarantor_index !== 2) {
        $guarantor_index = 1;
    }
    $column_name = ($guarantor_index === 2) ? 'guarantor_info_2' : 'guarantor_info';

    // Schema changes are performed only by the versioned migration, never inside user AJAX.
    $col = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM `{$table}` LIKE %s", $column_name));
    if (empty($col)) {
        if (function_exists('mlr_log')) mlr_log('Guarantor save blocked: database schema is outdated.', ['column' => $column_name]);
        if ($restore_blog) restore_current_blog();
        wp_send_json_error(['message' => 'ساختار پایگاه داده نیازمند بروزرسانی افزونه است. لطفاً یک‌بار وارد مدیریت وردپرس شوید و دوباره تلاش کنید.']);
        return;
    }

    // مقدار فعلی را بخوان (ستون مربوط به ضامن اول یا دوم)
    $guarantor_info_raw = $wpdb->get_var($wpdb->prepare("SELECT `{$column_name}` FROM `{$table}` WHERE id = %d LIMIT 1", $request_id));
    $info = !empty($guarantor_info_raw) ? json_decode($guarantor_info_raw, true) : [];
    if (!is_array($info)) {
        $info = [];
    }

    // Text fields (use wp_unslash for POST data)
    $text_fields = ['full_name', 'national_code', 'father_name', 'id_number', 'issued_from', 'birth_date', 'mobile', 'phone', 'job', 'postal_code', 'address'];
    foreach ($text_fields as $f) {
        if (isset($_POST[$f])) {
            $info[$f] = sanitize_text_field(wp_unslash($_POST[$f]));
        }
    }

    // File fields. New clients upload each document through the resilient staged
    // uploader first, then this final request contains only URL/token proofs. This
    // avoids large multipart requests being reset by HTTP/2/proxy/WAF layers.
    // Direct $_FILES handling remains as a backward-compatible fallback for an
    // already-cached page that still runs the previous JavaScript.
    $file_fields = function_exists('mlr_guarantor_upload_fields')
        ? mlr_guarantor_upload_fields()
        : ['national_card', 'id_card', 'payslip', 'sana', 'check', 'residence_deed', 'work_deed', 'account_statement', 'extra_docs'];

    $new_upload_urls = [];
    $staged_upload_urls = [];
    $staged_raw = isset($_POST['guarantor_staged_uploads']) ? $_POST['guarantor_staged_uploads'] : '';
    if ($staged_raw !== '' && function_exists('mlr_parse_guarantor_staged_uploads')) {
        $staged = mlr_parse_guarantor_staged_uploads($staged_raw, $request_id, $guarantor_index);
        if (is_wp_error($staged)) {
            if ($restore_blog) restore_current_blog();
            wp_send_json_error(['message' => $staged->get_error_message()]);
            return;
        }
        foreach ($staged as $field => $url) {
            if (!in_array($field, $file_fields, true)) {
                continue;
            }
            $info[$field] = $url;
            $staged_upload_urls[$field] = $url;
            $new_upload_urls[] = $url;
        }
    } elseif ($staged_raw !== '') {
        if ($restore_blog) restore_current_blog();
        wp_send_json_error(['message' => 'سرویس تأیید مدارک ضامن در دسترس نیست.']);
        return;
    }

    foreach ($file_fields as $f) {
        if (!empty($_FILES[$f]['name']) && ($_FILES[$f]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $upload = mlr_handle_wp_upload($_FILES[$f]);
            if (is_wp_error($upload)) {
                if ($new_upload_urls && function_exists('mlr_delete_local_document_uploads')) {
                    mlr_delete_local_document_uploads($new_upload_urls);
                }
                if ($restore_blog) restore_current_blog();
                wp_send_json_error(['message' => $upload->get_error_message()]);
                return;
            }
            $uploaded_url = is_array($upload) ? ($upload['url'] ?? '') : $upload;
            if (!is_string($uploaded_url) || strpos($uploaded_url, 'http') !== 0) {
                if ($new_upload_urls && function_exists('mlr_delete_local_document_uploads')) {
                    mlr_delete_local_document_uploads($new_upload_urls);
                }
                if ($restore_blog) restore_current_blog();
                wp_send_json_error(['message' => 'ذخیره یکی از مدارک ضامن ناموفق بود.']);
                return;
            }
            $info[$f] = $uploaded_url;
            $new_upload_urls[] = $uploaded_url;
        }
    }

    $json = wp_json_encode($info, JSON_UNESCAPED_UNICODE);
    $updated = $wpdb->update(
        $table,
        [$column_name => $json],
        ['id' => $request_id],
        ['%s'],
        ['%d']
    );

    if ($updated === false) {
        if (!empty($new_upload_urls) && function_exists('mlr_delete_local_document_uploads')) {
            mlr_delete_local_document_uploads($new_upload_urls);
        }
        if ($restore_blog) {
            restore_current_blog();
        }
        $err = $wpdb->last_error ? $wpdb->last_error : 'خطای نامشخص دیتابیس';
        if (function_exists('mlr_log')) {
            mlr_log('save_guarantor_info update failed: ' . $err);
        }
        wp_send_json_error(['message' => 'خطا در ذخیره اطلاعات. لطفاً دوباره تلاش کنید.']);
        return;
    }

    // The database now references these staged files, so their orphan-cleanup
    // timers are no longer needed. Even if clearing a cron event fails, the cleanup
    // callback re-checks DB references and will preserve live files.
    if (!empty($staged_upload_urls) && function_exists('mlr_cancel_request_staged_upload_cleanup')) {
        foreach ($staged_upload_urls as $field => $url) {
            $field_key = function_exists('mlr_guarantor_upload_field_key')
                ? mlr_guarantor_upload_field_key($guarantor_index, $field)
                : 'guarantor_' . $guarantor_index . '_' . sanitize_key($field);
            mlr_cancel_request_staged_upload_cleanup($url, $request_id, $field_key, 'guarantor');
        }
    }

    if ($restore_blog) {
        restore_current_blog();
    }
    $msg = ($guarantor_index === 2) ? 'اطلاعات و مدارک ضامن دوم با موفقیت ذخیره شد.' : 'اطلاعات و مدارک ضامن با موفقیت ذخیره شد.';
    wp_send_json_success(['message' => $msg]);
}
