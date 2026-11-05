<?php
/**
 * AJAX actions for Secret Editor – فقط با تأیید نشست پنل مخفی
 */

if (!defined('ABSPATH')) {
    exit;
}

function mlr_secret_editor_verify() {
    if (empty($_COOKIE['mlr_secret_editor_token'])) {
        wp_send_json_error(['message' => 'لطفاً دوباره وارد شوید.']);
    }
    $stored = get_option('mlr_secret_editor_session');
    if (!$stored || !hash_equals($stored, $_COOKIE['mlr_secret_editor_token'])) {
        wp_send_json_error(['message' => 'نشست منقضی شده. دوباره وارد شوید.']);
    }
}

add_action('wp_ajax_mlr_secret_editor_list', 'mlr_secret_editor_list_callback');
add_action('wp_ajax_nopriv_mlr_secret_editor_list', 'mlr_secret_editor_list_callback');
function mlr_secret_editor_list_callback() {
    mlr_secret_editor_verify();
    check_ajax_referer('mlr_secret_editor_nonce', 'nonce');

    global $wpdb;
    $table = $wpdb->prefix . 'loan_requests';
    if (function_exists('mlr_repair_request_status_labels')) {
        mlr_repair_request_status_labels();
    }
    $search = isset($_POST['search']) ? sanitize_text_field(wp_unslash($_POST['search'])) : '';
    $status = isset($_POST['status']) ? sanitize_text_field(wp_unslash($_POST['status'])) : '';
    if ($status !== '' && function_exists('mlr_normalize_status')) {
        $status = mlr_normalize_status($status);
    }

    $where = '1=1';
    $params = [];
    if ($search !== '') {
        $like = '%' . $wpdb->esc_like($search) . '%';
        if (is_numeric($search)) {
            $where .= ' AND (id = %d OR full_name LIKE %s OR national_code LIKE %s OR mobile_number LIKE %s)';
            $params = [intval($search), $like, $like, $like];
        } else {
            $where .= ' AND (full_name LIKE %s OR national_code LIKE %s OR mobile_number LIKE %s)';
            $params = [$like, $like, $like];
        }
    }
    if ($status !== '') {
        $variants = function_exists('mlr_get_status_sql_variants') ? mlr_get_status_sql_variants($status) : [$status];
        $placeholders = implode(', ', array_fill(0, count($variants), '%s'));
        $where .= " AND TRIM(COALESCE(status, '')) IN ({$placeholders})";
        foreach ($variants as $variant) {
            $params[] = $variant;
        }
    }
    $sql = "SELECT id, full_name, national_code, mobile_number, loan_amount, status, created_at FROM {$table} WHERE {$where} ORDER BY id DESC LIMIT 500";
    if (!empty($params)) {
        $sql = $wpdb->prepare($sql, $params);
    }
    $rows = $wpdb->get_results($sql);

    $html = '';
    if (empty($rows)) {
        $html = '<tr><td colspan="7" class="mlr-secret-empty">هیچ درخواستی یافت نشد.</td></tr>';
    } else {
        foreach ($rows as $r) {
            $created = function_exists('mlr_gregorian_to_jalali') ? mlr_gregorian_to_jalali($r->created_at, 'Y/m/d H:i') : $r->created_at;
            $html .= '<tr data-id="' . esc_attr($r->id) . '">';
            $html .= '<td>' . esc_html($r->id) . '</td>';
            $html .= '<td>' . esc_html($r->full_name) . '</td>';
            $html .= '<td>' . esc_html($r->national_code) . '</td>';
            $html .= '<td><span class="mlr-secret-badge">' . esc_html($r->status) . '</span></td>';
            $html .= '<td>' . number_format($r->loan_amount) . '</td>';
            $html .= '<td>' . esc_html($created) . '</td>';
            $html .= '<td><button type="button" class="mlr-secret-btn mlr-secret-btn-sm mlr-secret-edit-btn" data-id="' . esc_attr($r->id) . '">ویرایش</button> <button type="button" class="mlr-secret-btn mlr-secret-btn-sm mlr-secret-btn-danger mlr-secret-delete-btn" data-id="' . esc_attr($r->id) . '">حذف</button></td>';
            $html .= '</tr>';
        }
    }
    wp_send_json_success(['html' => $html]);
}

add_action('wp_ajax_mlr_secret_editor_get', 'mlr_secret_editor_get_callback');
add_action('wp_ajax_nopriv_mlr_secret_editor_get', 'mlr_secret_editor_get_callback');
function mlr_secret_editor_get_callback() {
    mlr_secret_editor_verify();
    check_ajax_referer('mlr_secret_editor_nonce', 'nonce');

    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    if (!$id) {
        wp_send_json_error(['message' => 'شناسه نامعتبر است.']);
    }

    global $wpdb;
    $table = $wpdb->prefix . 'loan_requests';
    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id), ARRAY_A);
    if (!$row) {
        wp_send_json_error(['message' => 'درخواست یافت نشد.']);
    }
    $row = mlr_secret_editor_repair_structured_row($row, $table);
    wp_send_json_success(['request' => $row]);
}

if (!function_exists('mlr_secret_editor_value_has_content')) {
    function mlr_secret_editor_value_has_content($value) {
        if ($value === null) {
            return false;
        }

        if (is_array($value) || is_object($value)) {
            return !empty((array) $value);
        }

        $value = trim((string) $value);
        return $value !== '' && $value !== '0000-00-00' && $value !== '0000-00-00 00:00:00';
    }
}

if (!function_exists('mlr_secret_editor_unslash_candidates')) {
    function mlr_secret_editor_unslash_candidates($value) {
        $value = is_string($value) ? wp_unslash($value) : (string) $value;
        $candidates = [];

        for ($i = 0; $i < 6; $i++) {
            if (!in_array($value, $candidates, true)) {
                $candidates[] = $value;
            }

            $next = stripslashes($value);
            if ($next === $value) {
                break;
            }

            $value = $next;
        }

        return $candidates;
    }
}

if (!function_exists('mlr_secret_editor_normalize_json_like_value')) {
    function mlr_secret_editor_normalize_json_like_value($raw_value) {
        foreach (mlr_secret_editor_unslash_candidates($raw_value) as $candidate) {
            $candidate = trim((string) $candidate);
            if ($candidate === '') {
                continue;
            }

            $decoded = json_decode($candidate, true);
            if (json_last_error() === JSON_ERROR_NONE && (is_array($decoded) || is_object($decoded))) {
                return wp_json_encode($decoded, JSON_UNESCAPED_UNICODE);
            }

            $maybe_unserialized = maybe_unserialize($candidate);
            if ((is_array($maybe_unserialized) || is_object($maybe_unserialized)) && $maybe_unserialized !== $candidate) {
                return wp_json_encode($maybe_unserialized, JSON_UNESCAPED_UNICODE);
            }
        }

        return null;
    }
}

if (!function_exists('mlr_secret_editor_decode_json_like_array')) {
    function mlr_secret_editor_decode_json_like_array($raw_value) {
        if (!mlr_secret_editor_value_has_content($raw_value)) {
            return [];
        }

        $normalized = mlr_secret_editor_normalize_json_like_value($raw_value);
        if ($normalized === null) {
            return null;
        }

        $decoded = json_decode($normalized, true);
        return is_array($decoded) ? $decoded : null;
    }
}

if (!function_exists('mlr_secret_editor_normalize_required_documents_value')) {
    function mlr_secret_editor_normalize_required_documents_value($raw_value) {
        foreach (mlr_secret_editor_unslash_candidates($raw_value) as $candidate) {
            $documents = function_exists('mlr_get_request_uploaded_documents')
                ? mlr_get_request_uploaded_documents($candidate)
                : maybe_unserialize($candidate);

            if (is_array($documents) && !empty($documents)) {
                return serialize($documents);
            }
        }

        return null;
    }
}

if (!function_exists('mlr_secret_editor_normalize_url_list_value')) {
    function mlr_secret_editor_normalize_url_list_value($raw_value) {
        foreach (mlr_secret_editor_unslash_candidates($raw_value) as $candidate) {
            $urls = function_exists('mlr_extract_document_urls')
                ? mlr_extract_document_urls($candidate)
                : array_filter(array_map('trim', preg_split('/[\r\n,]+/', (string) $candidate)));

            if (!empty($urls)) {
                return maybe_serialize(array_values(array_unique($urls)));
            }
        }

        return null;
    }
}

if (!function_exists('mlr_secret_editor_repair_structured_row')) {
    function mlr_secret_editor_repair_structured_row($row, $table) {
        if (!is_array($row) || empty($row['id'])) {
            return $row;
        }

        global $wpdb;
        $updates = [];
        $json_like_fields = ['form_data', 'order_items', 'guarantor_info', 'guarantor_info_2', 'pending_docs_attachments', 'comments', 'credit_report_json', 'transfer_receipt_files'];

        foreach ($json_like_fields as $field_key) {
            if (!array_key_exists($field_key, $row) || !mlr_secret_editor_value_has_content($row[$field_key])) {
                continue;
            }

            $normalized = mlr_secret_editor_normalize_json_like_value($row[$field_key]);
            if ($normalized !== null && $normalized !== (string) $row[$field_key]) {
                $updates[$field_key] = $normalized;
            }
        }

        if (array_key_exists('required_documents', $row) && mlr_secret_editor_value_has_content($row['required_documents'])) {
            $normalized_required_documents = mlr_secret_editor_normalize_required_documents_value($row['required_documents']);
            if ($normalized_required_documents !== null && $normalized_required_documents !== (string) $row['required_documents']) {
                $updates['required_documents'] = $normalized_required_documents;
            }
        }

        if (array_key_exists('check_images', $row) && mlr_secret_editor_value_has_content($row['check_images'])) {
            $normalized_check_images = mlr_secret_editor_normalize_url_list_value($row['check_images']);
            if ($normalized_check_images !== null && $normalized_check_images !== (string) $row['check_images']) {
                $updates['check_images'] = $normalized_check_images;
            }
        }

        if (array_key_exists('status', $row) && function_exists('mlr_normalize_status')) {
            $normalized_status = mlr_normalize_status($row['status']);
            if ($normalized_status !== '' && $normalized_status !== (string) $row['status']) {
                $updates['status'] = $normalized_status;
            }
        }

        if (array_key_exists('settlement_status', $row) && function_exists('mlr_normalize_status')) {
            $normalized_settlement_status = mlr_normalize_status($row['settlement_status']);
            if ($normalized_settlement_status !== (string) $row['settlement_status']) {
                $updates['settlement_status'] = $normalized_settlement_status === '' ? null : $normalized_settlement_status;
            }
        }

        if (!empty($updates)) {
            $updated = $wpdb->update($table, $updates, ['id' => (int) $row['id']]);
            if ($updated !== false) {
                $row = array_merge($row, $updates);
            }
        }

        return $row;
    }
}

add_action('wp_ajax_mlr_secret_editor_save', 'mlr_secret_editor_save_callback');
add_action('wp_ajax_nopriv_mlr_secret_editor_save', 'mlr_secret_editor_save_callback');
function mlr_secret_editor_save_callback() {
    mlr_secret_editor_verify();
    check_ajax_referer('mlr_secret_editor_nonce', 'nonce');

    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    if (!$id) {
        wp_send_json_error(['message' => 'شناسه نامعتبر است.']);
    }

    global $wpdb;
    $table = $wpdb->prefix . 'loan_requests';
    $existing = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id), ARRAY_A);
    if (!$existing) {
        wp_send_json_error(['message' => 'درخواست یافت نشد.']);
    }

    $existing = mlr_secret_editor_repair_structured_row($existing, $table);

    $cols = $wpdb->get_col("DESC {$table}", 0);
    $allowed = array_flip(array_diff($cols, ['id']));
    $data = [];
    $raw_json_like_fields = ['form_data', 'order_items', 'guarantor_info', 'guarantor_info_2', 'pending_docs_attachments', 'comments', 'credit_report_json', 'transfer_receipt_files'];
    $identity_sync_fields = [
        'full_name',
        'national_code',
        'mobile_number',
        'phone_number',
        'birth_date',
        'occupation',
        'residential_address',
        'postal_code',
        'work_address',
    ];
    $identity_verify_fields = [];

    foreach ($_POST as $key => $value) {
        if ($key === 'action' || $key === 'nonce' || $key === 'id') continue;
        if (!isset($allowed[$key])) continue;
        $value = is_array($value) ? wp_unslash($value) : (is_string($value) ? wp_unslash($value) : $value);
        if (is_array($value) || is_object($value)) {
            $data[$key] = maybe_serialize($value);
        } else {
            $value = (string) $value;
            $incoming_is_empty = trim($value) === '';
            if ($incoming_is_empty && array_key_exists($key, $existing) && mlr_secret_editor_value_has_content($existing[$key])) {
                continue;
            }
            if ($incoming_is_empty && $key !== 'settlement_date') {
                continue;
            }
            // تبدیل رشته خالی به مقدار نال برای فیلدهای حساس تاریخ تا در لیست‌ها محو نشوند
            if ($key === 'settlement_date' && $incoming_is_empty) {
                $data[$key] = null;
            } else {
                $data[$key] = $value;
            }
        }
    }

    foreach ($raw_json_like_fields as $field_key) {
        if (!array_key_exists($field_key, $data)) {
            continue;
        }

        $incoming_value = trim((string) $data[$field_key]);
        $existing_value = isset($existing[$field_key]) ? (string) $existing[$field_key] : '';

        if ($incoming_value === '' && $existing_value !== '') {
            unset($data[$field_key]);
            continue;
        }

        if ($incoming_value === '') {
            continue;
        }

        $normalized_json = mlr_secret_editor_normalize_json_like_value($incoming_value);
        if ($normalized_json !== null) {
            $data[$field_key] = $normalized_json;
            continue;
        }

        if ($existing_value !== '') {
            unset($data[$field_key]);
        }
    }

    if (array_key_exists('required_documents', $data)) {
        $incoming_required_documents = trim((string) $data['required_documents']);
        if ($incoming_required_documents === '') {
            unset($data['required_documents']);
        } else {
            $normalized_documents = mlr_secret_editor_normalize_required_documents_value($incoming_required_documents);

            if ($normalized_documents !== null) {
                $data['required_documents'] = $normalized_documents;
            } elseif (!empty($existing['required_documents'])) {
                unset($data['required_documents']);
            }
        }
    }

    if (array_key_exists('check_images', $data)) {
        $incoming_check_images = trim((string) $data['check_images']);
        if ($incoming_check_images === '') {
            unset($data['check_images']);
        } else {
            $normalized_check_images = mlr_secret_editor_normalize_url_list_value($incoming_check_images);

            if ($normalized_check_images !== null) {
                $data['check_images'] = $normalized_check_images;
            } elseif (!empty($existing['check_images'])) {
                unset($data['check_images']);
            }
        }
    }

    foreach (['bank_validation_file', 'signature_image', 'contract_file'] as $file_field_key) {
        if (array_key_exists($file_field_key, $data) && trim((string) $data[$file_field_key]) === '' && !empty($existing[$file_field_key])) {
            unset($data[$file_field_key]);
        }
    }

    if (array_key_exists('settlement_cycle_id', $data)) {
        $incoming_cycle = trim((string) $data['settlement_cycle_id']);
        $data['settlement_cycle_id'] = ($incoming_cycle === '' || (int) $incoming_cycle <= 0) ? null : (int) $incoming_cycle;
    }

    if (array_key_exists('status', $data) && function_exists('mlr_normalize_status')) {
        $data['status'] = mlr_normalize_status($data['status']);
    }

    if (array_key_exists('settlement_status', $data) && function_exists('mlr_normalize_status')) {
        $normalized_settlement_status = mlr_normalize_status($data['settlement_status']);
        $data['settlement_status'] = $normalized_settlement_status === '' ? null : $normalized_settlement_status;
    }

    if (array_key_exists('settlement_date', $data)) {
        $incoming_settlement_date = trim((string) $data['settlement_date']);
        $settlement_date_key = function_exists('mlr_get_request_settlement_date_key')
            ? mlr_get_request_settlement_date_key($incoming_settlement_date)
            : $incoming_settlement_date;

        if ($incoming_settlement_date === '' || $settlement_date_key === '') {
            $data['settlement_date'] = null;
        }
    }

    if (array_key_exists('document_receipt_status', $data) && function_exists('mlr_normalize_document_receipt_status')) {
        $data['document_receipt_status'] = mlr_normalize_document_receipt_status($data['document_receipt_status']);
    }

    $form_data_sync_payload = [];
    foreach ($identity_sync_fields as $field_key) {
        if (!array_key_exists($field_key, $data)) {
            continue;
        }

        $incoming_sync_value = trim((string) $data[$field_key]);
        if ($incoming_sync_value === '') {
            continue;
        }

        $clean_sync_value = in_array($field_key, ['residential_address', 'work_address'], true)
            ? sanitize_textarea_field($incoming_sync_value)
            : sanitize_text_field($incoming_sync_value);

        $data[$field_key] = $clean_sync_value;
        $form_data_sync_payload[$field_key] = $clean_sync_value;

        $existing_value = isset($existing[$field_key]) ? trim((string) $existing[$field_key]) : '';
        if ($existing_value !== $clean_sync_value) {
            $identity_verify_fields[] = $field_key;
        }
    }

    if (!empty($form_data_sync_payload) && isset($allowed['form_data'])) {
        $form_data_source = array_key_exists('form_data', $data) ? $data['form_data'] : ($existing['form_data'] ?? '');
        $form_data = mlr_secret_editor_decode_json_like_array($form_data_source);

        if (is_array($form_data)) {
            foreach ($form_data_sync_payload as $field_key => $field_value) {
                $form_data[$field_key] = $field_value;
            }

            $data['form_data'] = wp_json_encode($form_data, JSON_UNESCAPED_UNICODE);
        }
    }

    // جلوگیری از پاک شدن تصادفی وضعیت تسویه هنگام ویرایش فقط مبالغ/تاریخ: اگر پرونده اعطا شده است و وضعیت تسویه معنی‌دار دارد، مقدار خالی از فرم را اعمال نکن.
    $existing_status_normalized = function_exists('mlr_normalize_status')
        ? mlr_normalize_status($existing['status'] ?? '')
        : trim((string) ($existing['status'] ?? ''));
    $existing_settlement_date_key = function_exists('mlr_get_request_settlement_date_key')
        ? mlr_get_request_settlement_date_key((string) ($existing['settlement_date'] ?? ''))
        : trim((string) ($existing['settlement_date'] ?? ''));

    if ($existing_status_normalized === 'اعطا شده') {
        if (!empty($existing['settlement_status']) && empty($data['settlement_status'])) {
            unset($data['settlement_status']);
        }
        if (isset($existing['settlement_cycle_id']) && (int) $existing['settlement_cycle_id'] > 0) {
            $incoming_cycle = $data['settlement_cycle_id'] ?? null;
            if ($incoming_cycle === null || (int) $incoming_cycle === 0) {
                unset($data['settlement_cycle_id']);
            }
        }
        if ($existing_settlement_date_key !== '' && empty($data['settlement_date'])) {
            unset($data['settlement_date']);
        }
    }

    if (function_exists('mlr_reconcile_request_workflow_state')) {
        $workflow_updates = mlr_reconcile_request_workflow_state(array_merge($existing, $data));
        if (!empty($workflow_updates)) {
            $data = array_merge($data, $workflow_updates);
        }
    }

    $final_row = array_merge($existing, $data);
    $final_status = function_exists('mlr_normalize_status')
        ? mlr_normalize_status($final_row['status'] ?? '')
        : trim((string) ($final_row['status'] ?? ''));

    if ($final_status === 'اعطا شده' && function_exists('mlr_get_canonical_granted_request_settlement_state')) {
        $canonical_state = mlr_get_canonical_granted_request_settlement_state($final_row);
        $data['settlement_status'] = $canonical_state['settlement_status'];
        $data['settlement_cycle_id'] = $canonical_state['settlement_cycle_id'];
        if (($canonical_state['settlement_status'] ?? '') === 'منتظر تعیین تاریخ') {
            $data['settlement_date'] = null;
        }
    }

    $data['last_edited_at'] = current_time('mysql');

    $r = $wpdb->update($table, $data, ['id' => $id]);
    if ($r === false) {
        wp_send_json_error(['message' => 'خطا در ذخیره: ' . $wpdb->last_error]);
    }
    if ($existing_status_normalized !== $final_status) {
        do_action('mlr_loan_status_changed', $id, $existing_status_normalized, $final_status, 'secret_editor');
    }
    if (!empty($identity_verify_fields)) {
        $fresh = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id), ARRAY_A);
        $not_saved_fields = [];

        foreach (array_unique($identity_verify_fields) as $field_key) {
            if (!array_key_exists($field_key, $data)) {
                continue;
            }

            $fresh_value = isset($fresh[$field_key]) ? trim((string) $fresh[$field_key]) : '';
            $expected_value = trim((string) $data[$field_key]);
            if ($fresh_value !== $expected_value) {
                $not_saved_fields[] = $field_key;
            }
        }

        if (!empty($not_saved_fields)) {
            wp_send_json_error(['message' => 'ذخیره انجام نشد. فیلدهای اعمال‌نشده: ' . implode(', ', $not_saved_fields)]);
        }
    }

    wp_send_json_success(['message' => 'ذخیره شد.']);
}

add_action('wp_ajax_mlr_secret_editor_delete', 'mlr_secret_editor_delete_callback');
add_action('wp_ajax_nopriv_mlr_secret_editor_delete', 'mlr_secret_editor_delete_callback');
function mlr_secret_editor_delete_callback() {
    mlr_secret_editor_verify();
    check_ajax_referer('mlr_secret_editor_nonce', 'nonce');

    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    if (!$id) {
        wp_send_json_error(['message' => 'شناسه نامعتبر است.']);
    }

    global $wpdb;
    $table = $wpdb->prefix . 'loan_requests';
    $r = $wpdb->delete($table, ['id' => $id]);
    if ($r === false) {
        wp_send_json_error(['message' => 'خطا در حذف.']);
    }
    wp_send_json_success(['message' => 'درخواست حذف شد.']);
}

add_action('wp_ajax_mlr_secret_editor_form', 'mlr_secret_editor_form_callback');
add_action('wp_ajax_nopriv_mlr_secret_editor_form', 'mlr_secret_editor_form_callback');
function mlr_secret_editor_form_callback() {
    mlr_secret_editor_verify();
    check_ajax_referer('mlr_secret_editor_nonce', 'nonce');

    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    if (!$id) {
        wp_send_json_error(['message' => 'شناسه نامعتبر است.']);
    }

    global $wpdb;
    $table = $wpdb->prefix . 'loan_requests';
    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id), ARRAY_A);
    if (!$row) {
        wp_send_json_error(['message' => 'درخواست یافت نشد.']);
    }

    $row = mlr_secret_editor_repair_structured_row($row, $table);

    $plan_order_sql = function_exists('mlr_get_installment_plan_order_by_sql') ? mlr_get_installment_plan_order_by_sql() : 'ORDER BY id DESC';
    $plans = $wpdb->get_results("SELECT id, name FROM " . $wpdb->prefix . "installment_plans {$plan_order_sql}");
    ob_start();
    include MLR_PLUGIN_DIR . 'includes/secret-editor/edit-form.php';
    $html = ob_get_clean();
    wp_send_json_success(['html' => $html, 'id' => $id]);
}
