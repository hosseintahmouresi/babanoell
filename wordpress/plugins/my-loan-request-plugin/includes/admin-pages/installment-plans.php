<?php
/**
 * Build the canonical admin URL for the installment-plan screen.
 */
if (!function_exists('mlr_installment_plans_admin_url')) {
    function mlr_installment_plans_admin_url(array $args = []) {
        $url = add_query_arg(['page' => 'mlr-installment-plans'], admin_url('admin.php'));
        return $args ? add_query_arg($args, $url) : $url;
    }
}

/**
 * Redirect back to the plan screen after an admin action.
 * Keeping mutations on admin-post.php means redirects happen before admin HTML output.
 */
if (!function_exists('mlr_installment_plans_admin_redirect')) {
    function mlr_installment_plans_admin_redirect(array $args = []) {
        wp_safe_redirect(mlr_installment_plans_admin_url($args));
        exit;
    }
}

/**
 * Create a short, database-backed action lock. add_option() is atomic because option_name
 * is unique, so simultaneous double-click requests cannot both acquire the lock.
 */
if (!function_exists('mlr_installment_plan_action_lock_acquire')) {
    function mlr_installment_plan_action_lock_acquire($action, $plan_id, $ttl = 15) {
        // The lock is global per action/plan (not per-user), preventing two administrators
        // from racing on the same copy name/display order at the same time.
        $key = 'mlr_plan_' . sanitize_key($action) . '_lock_' . absint($plan_id);
        $now = time();
        $existing = get_option($key, false);
        if ($existing !== false) {
            $existing = (int) $existing;
            if ($existing > 0 && ($now - $existing) <= max(1, (int) $ttl)) {
                return false;
            }
            delete_option($key); // stale lock from an interrupted request
        }
        return add_option($key, $now, '', 'no') ? $key : false;
    }
}

if (!function_exists('mlr_installment_plan_action_lock_release')) {
    function mlr_installment_plan_action_lock_release($lock_key) {
        if (is_string($lock_key) && $lock_key !== '') {
            delete_option($lock_key);
        }
    }
}

/**
 * Admin-post handler for plan duplication.
 * It copies the source row verbatim, changing only identity/name/visibility/order/timestamp,
 * then reads the inserted row back and verifies business-critical payloads before success.
 */
if (!function_exists('mlr_handle_duplicate_installment_plan')) {
    function mlr_handle_duplicate_installment_plan() {
        if (!current_user_can('manage_options')) {
            wp_die('شما دسترسی لازم برای تکثیر طرح را ندارید.');
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'installment_plans';
        $source_plan_id = isset($_GET['plan_id']) ? absint($_GET['plan_id']) : 0;
        if ($source_plan_id <= 0) {
            mlr_installment_plans_admin_redirect(['mlr_plan_action_error' => 'invalid_source']);
        }
        check_admin_referer('mlr_duplicate_plan_' . $source_plan_id);

        $lock_key = mlr_installment_plan_action_lock_acquire('duplicate', $source_plan_id, 15);
        if ($lock_key === false) {
            mlr_installment_plans_admin_redirect(['mlr_plan_action_error' => 'duplicate_busy']);
        }

        try {
            $source_plan = $wpdb->get_row(
                $wpdb->prepare("SELECT * FROM `{$table_name}` WHERE `id` = %d LIMIT 1", $source_plan_id),
                ARRAY_A
            );
            if (!$source_plan) {
                mlr_installment_plan_action_lock_release($lock_key);
                mlr_installment_plans_admin_redirect(['mlr_plan_action_error' => 'source_missing']);
            }

            $source_name = sanitize_text_field((string) ($source_plan['name'] ?? 'طرح'));
            $base_duplicate_name = $source_name . ' — کپی';
            $duplicate_name = $base_duplicate_name;
            $copy_number = 2;
            while ((int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM `{$table_name}` WHERE `name` = %s",
                $duplicate_name
            )) > 0 && $copy_number <= 100) {
                $duplicate_name = $base_duplicate_name . ' ' . $copy_number;
                $copy_number++;
            }
            if ((int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM `{$table_name}` WHERE `name` = %s",
                $duplicate_name
            )) > 0) {
                $duplicate_name = $base_duplicate_name . ' ' . wp_date('Ymd-His');
            }

            $duplicate_data = $source_plan;
            unset($duplicate_data['id']);
            $duplicate_data['name'] = $duplicate_name;
            $duplicate_data['is_active'] = 0; // never publish a copy before admin review
            $duplicate_data['created_at'] = current_time('mysql');
            if (array_key_exists('display_order', $duplicate_data)) {
                $max_display_order = (int) $wpdb->get_var("SELECT MAX(`display_order`) FROM `{$table_name}`");
                $duplicate_data['display_order'] = $max_display_order + 10;
            }

            $inserted = $wpdb->insert($table_name, $duplicate_data);
            $new_plan_id = $inserted === false ? 0 : (int) $wpdb->insert_id;
            if ($inserted === false || $new_plan_id <= 0) {
                if (function_exists('mlr_log')) {
                    mlr_log('Installment plan duplication failed.', [
                        'source_plan_id' => $source_plan_id,
                        'db_error' => $wpdb->last_error,
                    ]);
                }
                mlr_installment_plan_action_lock_release($lock_key);
                mlr_installment_plans_admin_redirect(['mlr_plan_action_error' => 'duplicate_db']);
            }

            // Verify the clone before reporting success. Serialized settings/documents must remain byte-for-byte intact.
            $inserted_plan = $wpdb->get_row(
                $wpdb->prepare("SELECT * FROM `{$table_name}` WHERE `id` = %d LIMIT 1", $new_plan_id),
                ARRAY_A
            );
            $verified = is_array($inserted_plan)
                && (int) ($inserted_plan['id'] ?? 0) === $new_plan_id;
            // Verify every inserted column, not just today's known settings. This keeps future
            // plan columns safe as the schema evolves without silently producing partial clones.
            foreach ($duplicate_data as $verify_key => $expected_value) {
                $verified = $verified
                    && array_key_exists($verify_key, $inserted_plan)
                    && (string) $inserted_plan[$verify_key] === (string) $expected_value;
            }

            if (!$verified) {
                // Fail closed: never leave a copy that does not faithfully match the source.
                $wpdb->delete($table_name, ['id' => $new_plan_id], ['%d']);
                if (function_exists('mlr_log')) {
                    mlr_log('Installment plan duplication verification failed; copy removed.', [
                        'source_plan_id' => $source_plan_id,
                        'new_plan_id' => $new_plan_id,
                    ]);
                }
                mlr_installment_plan_action_lock_release($lock_key);
                mlr_installment_plans_admin_redirect(['mlr_plan_action_error' => 'duplicate_verify']);
            }

            mlr_installment_plan_action_lock_release($lock_key);
            mlr_installment_plans_admin_redirect([
                'duplicated_plan_id' => $new_plan_id,
                'mlr_duplicate_success' => 1,
            ]);
        } catch (Throwable $e) {
            mlr_installment_plan_action_lock_release($lock_key);
            if (function_exists('mlr_log')) {
                mlr_log('Installment plan duplication threw an exception.', [
                    'source_plan_id' => $source_plan_id,
                    'exception' => $e->getMessage(),
                ]);
            }
            mlr_installment_plans_admin_redirect(['mlr_plan_action_error' => 'duplicate_exception']);
        }
    }
}
add_action('admin_post_mlr_duplicate_installment_plan', 'mlr_handle_duplicate_installment_plan');

/**
 * Delete only unused plans. Historical loan rows must never lose their referenced plan.
 */
if (!function_exists('mlr_handle_delete_installment_plan')) {
    function mlr_handle_delete_installment_plan() {
        if (!current_user_can('manage_options')) {
            wp_die('شما دسترسی لازم برای حذف طرح را ندارید.');
        }
        global $wpdb;
        $table_name = $wpdb->prefix . 'installment_plans';
        $requests_table = $wpdb->prefix . 'loan_requests';
        $plan_id = isset($_GET['plan_id']) ? absint($_GET['plan_id']) : 0;
        if ($plan_id <= 0) {
            mlr_installment_plans_admin_redirect(['mlr_plan_action_error' => 'invalid_source']);
        }
        check_admin_referer('mlr_delete_plan_' . $plan_id);

        $exists = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM `{$table_name}` WHERE `id` = %d", $plan_id));
        if ($exists <= 0) {
            mlr_installment_plans_admin_redirect(['mlr_plan_action_error' => 'source_missing']);
        }
        $requests_table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $requests_table)) === $requests_table;
        if ($requests_table_exists) {
            $reference_count = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM `{$requests_table}` WHERE `installment_plan_id` = %d",
                $plan_id
            ));
            if ($reference_count > 0) {
                mlr_installment_plans_admin_redirect(['mlr_plan_action_error' => 'plan_in_use']);
            }
        }

        $deleted = $wpdb->delete($table_name, ['id' => $plan_id], ['%d']);
        if ($deleted !== 1) {
            if (function_exists('mlr_log')) {
                mlr_log('Installment plan delete failed.', ['plan_id' => $plan_id, 'db_error' => $wpdb->last_error]);
            }
            mlr_installment_plans_admin_redirect(['mlr_plan_action_error' => 'delete_db']);
        }
        mlr_installment_plans_admin_redirect(['mlr_plan_deleted' => 1]);
    }
}
add_action('admin_post_mlr_delete_installment_plan', 'mlr_handle_delete_installment_plan');

function mlr_installment_plans_page() {
    // --- START: PHP Logic (Existing code - unchanged) ---
    if (!current_user_can('manage_options')) {
        wp_die('شما دسترسی لازم برای مشاهده این صفحه را ندارید.');
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'installment_plans';
    $errors = [];
    // Action notices (duplicate/delete redirects) are intentionally isolated from form validation.
    // A stale query-string notice must never block saving/editing a plan.
    $action_notice_errors = [];
    $success = '';
    $request_method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

    wp_enqueue_media(); // Ensure media library scripts are loaded
    wp_enqueue_script('jquery-ui-sortable');

    $all_possible_documents = mlr_get_all_document_options();
    $occupation_options = function_exists('mlr_get_occupation_options') ? mlr_get_occupation_options() : [
        'official_employee' => 'کارمند رسمی', 'contract_employee' => 'کارمند قراردادی', 'worker' => 'کارگر',
        'freelancer' => 'آزاد', 'retired' => 'بازنشسته', 'pensioner' => 'مستمری بگیر', 'employer' => 'کارفرما', 'other' => 'سایر'
    ];
    $calculation_labels = mlr_get_calculation_labels(); // Get labels for calculator output
    $visibility_contexts = function_exists('mlr_get_installment_plan_visibility_contexts')
        ? mlr_get_installment_plan_visibility_contexts()
        : [];

    // --- Global plan display order ---
    if (isset($_POST['mlr_save_plan_order'])) {
        if (!isset($_POST['mlr_plan_order_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['mlr_plan_order_nonce'])), 'mlr_save_plan_order_action')) {
            $errors[] = 'اعتبار درخواست ذخیره ترتیب طرح‌ها نامعتبر است.';
        } elseif (version_compare((string) get_option('mlr_db_version', '0'), '2.7', '<')) {
            $errors[] = 'مهاجرت دیتابیس مربوط به ترتیب طرح‌ها هنوز کامل نشده است؛ برای جلوگیری از ذخیره ناقص، هیچ تغییری انجام نشد.';
        } elseif (!function_exists('mlr_installment_plan_display_order_column_exists') || !mlr_installment_plan_display_order_column_exists()) {
            $errors[] = 'ستون ترتیب نمایش هنوز در دیتابیس آماده نشده است. یک‌بار صفحه را تازه‌سازی کنید؛ در صورت تداوم خطا، migration دیتابیس را بررسی کنید.';
        } else {
            $ordered_ids = isset($_POST['plan_order']) && is_array($_POST['plan_order'])
                ? array_values(array_unique(array_filter(array_map('absint', wp_unslash($_POST['plan_order'])))))
                : [];
            $submitted_signature = isset($_POST['plan_order_signature']) ? sanitize_text_field(wp_unslash($_POST['plan_order_signature'])) : '';
            $current_order_rows = $wpdb->get_results("SELECT id, display_order FROM `{$table_name}` ORDER BY id ASC");
            $existing_ids = [];
            $signature_parts = [];
            foreach ((array) $current_order_rows as $current_order_row) {
                $current_id = absint($current_order_row->id ?? 0);
                if ($current_id <= 0) continue;
                $existing_ids[] = $current_id;
                $signature_parts[] = $current_id . ':' . (int) ($current_order_row->display_order ?? 0);
            }
            $current_signature = hash('sha256', implode('|', $signature_parts));
            $submitted_sorted = $ordered_ids; sort($submitted_sorted, SORT_NUMERIC);
            $existing_sorted = $existing_ids; sort($existing_sorted, SORT_NUMERIC);

            if ($submitted_signature === '' || !hash_equals($current_signature, $submitted_signature)) {
                $errors[] = 'ترتیب طرح‌ها از زمان باز شدن این صفحه تغییر کرده است. برای جلوگیری از بازنویسی تغییرات جدید، صفحه را تازه‌سازی و دوباره مرتب کنید.';
            } elseif (empty($ordered_ids) || $submitted_sorted !== $existing_sorted) {
                $errors[] = 'لیست ترتیب ناقص یا نامعتبر بود؛ هیچ تغییری ذخیره نشد.';
            } else {
                $case_parts = [];
                $id_parts = [];
                $position = 10;
                foreach ($ordered_ids as $ordered_id) {
                    $ordered_id = absint($ordered_id);
                    $case_parts[] = "WHEN {$ordered_id} THEN {$position}";
                    $id_parts[] = (string) $ordered_id;
                    $position += 10;
                }
                $order_update_sql = "UPDATE `{$table_name}` SET `display_order` = CASE `id` " . implode(' ', $case_parts) . " ELSE `display_order` END WHERE `id` IN (" . implode(',', $id_parts) . ")";
                $order_update_result = $wpdb->query($order_update_sql);
                if ($order_update_result === false) {
                    $errors[] = 'ذخیره ترتیب طرح‌ها با خطای دیتابیس مواجه شد و انجام نشد.';
                    if (function_exists('mlr_log')) mlr_log('Installment plan display order update failed.', ['db_error' => $wpdb->last_error]);
                } else {
                    $success = 'ترتیب نمایش طرح‌ها با موفقیت ذخیره شد و در همه بخش‌های افزونه اعمال می‌شود.';
                }
            }
        }
    }

    // Duplicate/delete mutations are handled through admin-post.php before admin output.

    // Redirect notices are GET-only and informational for the page action that just completed.
    // They MUST NOT participate in POST form validation (e.g. a previous failed delete must not
    // prevent editing occupation-specific document settings on the same plan).
    if ($request_method === 'GET') {
        if (isset($_GET['mlr_duplicate_success'], $_GET['duplicated_plan_id']) && absint($_GET['duplicated_plan_id']) > 0) {
            $success = 'نسخه کپی طرح با موفقیت ساخته شد. این نسخه برای جلوگیری از نمایش ناخواسته در سایت، غیرفعال است؛ پس از بررسی و ویرایش می‌توانید آن را فعال کنید.';
        }

        if (isset($_GET['mlr_plan_deleted']) && absint($_GET['mlr_plan_deleted']) === 1) {
            $success = 'طرح استفاده‌نشده با موفقیت حذف شد.';
        }
        if (!empty($_GET['mlr_plan_action_error'])) {
            $action_error = sanitize_key(wp_unslash($_GET['mlr_plan_action_error']));
            $action_error_messages = [
                'invalid_source' => 'شناسه طرح نامعتبر است و هیچ تغییری انجام نشد.',
                'source_missing' => 'طرح موردنظر پیدا نشد و هیچ تغییری انجام نشد.',
                'duplicate_busy' => 'درخواست تکثیر دیگری برای همین طرح در حال پردازش است. چند ثانیه بعد دوباره تلاش کنید.',
                'duplicate_db' => 'تکثیر طرح با خطای دیتابیس مواجه شد و هیچ نسخه ناقصی ایجاد نشد.',
                'duplicate_verify' => 'نسخه کپی پس از ایجاد با طرح مبدا تطابق کامل نداشت؛ نسخه ناقص به‌صورت خودکار حذف شد.',
                'duplicate_exception' => 'در زمان تکثیر طرح یک خطای غیرمنتظره رخ داد و عملیات متوقف شد.',
                'plan_in_use' => 'حذف طرح انجام نشد: این طرح در پرونده‌های موجود استفاده شده و برای حفظ سوابق قابل حذف نیست؛ در صورت نیاز آن را غیرفعال کنید.',
                'delete_db' => 'حذف طرح با خطای دیتابیس مواجه شد و انجام نشد.',
            ];
            if (isset($action_error_messages[$action_error])) {
                $action_notice_errors[] = $action_error_messages[$action_error];
            }
        }
    }

    // --- Form Submission Handling ---
    if (isset($_POST['mlr_submit_plan']) && check_admin_referer('mlr_installment_plan_action', '_wpnonce')) {
        $plan_id = isset($_POST['plan_id']) ? intval($_POST['plan_id']) : 0;
        $name = sanitize_text_field(trim($_POST['plan_name']));
        $type = sanitize_key(wp_unslash($_POST['plan_type'] ?? ''));
        if (!in_array($type, ['market', 'leasing'], true)) {
            $errors[] = 'نوع طرح نامعتبر است.';
            $type = 'market'; // Safe prefix only; the error prevents persistence.
        }
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $posted_visibility_contexts = isset($_POST['visibility_contexts']) && is_array($_POST['visibility_contexts'])
            ? array_map('sanitize_key', wp_unslash($_POST['visibility_contexts']))
            : [];
        $posted_visibility_contexts = array_values(array_unique(array_filter($posted_visibility_contexts)));
        $visibility_context_keys = array_keys($visibility_contexts);
        $selected_visibility_contexts = array_values(array_intersect($visibility_context_keys, $posted_visibility_contexts));
        $display_fields = isset($_POST['display_fields']) && is_array($_POST['display_fields']) ? array_map('sanitize_text_field', $_POST['display_fields']) : [];
        $display_fields_context = isset($_POST['display_fields_context']) && is_array($_POST['display_fields_context']) ? $_POST['display_fields_context'] : [];
        $sanitized_display_fields_context = [];
        if (!empty($display_fields_context) && is_array($display_fields_context)) {
            foreach ($display_fields_context as $context_key => $context_fields) {
                $context_key = sanitize_text_field($context_key);
                if (!$context_key) { continue; }
                if (!is_array($context_fields)) { $context_fields = []; }
                $context_fields = array_map('sanitize_text_field', $context_fields);
                $context_fields = array_values(array_filter(array_unique($context_fields)));
                $sanitized_display_fields_context[$context_key] = $context_fields;
            }
        }
        $required_documents = isset($_POST['required_documents']) && is_array($_POST['required_documents']) ? array_map('sanitize_key', wp_unslash($_POST['required_documents'])) : [];
        $required_documents = array_values(array_filter(array_unique($required_documents), function($document_key) use ($all_possible_documents) {
            return isset($all_possible_documents[$document_key]);
        }));

        $occupation_documents_enabled = isset($_POST['occupation_documents_enabled']) ? 1 : 0;
        $posted_occupation_documents = isset($_POST['required_documents_by_occupation']) && is_array($_POST['required_documents_by_occupation'])
            ? wp_unslash($_POST['required_documents_by_occupation'])
            : [];
        $required_documents_by_occupation = [];
        foreach ($occupation_options as $occupation_key => $occupation_label) {
            $raw_docs = isset($posted_occupation_documents[$occupation_key]) && is_array($posted_occupation_documents[$occupation_key])
                ? $posted_occupation_documents[$occupation_key]
                : [];
            $clean_docs = array_map('sanitize_key', $raw_docs);
            $clean_docs = array_values(array_filter(array_unique($clean_docs), function($document_key) use ($all_possible_documents) {
                return isset($all_possible_documents[$document_key]);
            }));
            $required_documents_by_occupation[$occupation_key] = $clean_docs;
            if ($occupation_documents_enabled && empty($clean_docs)) {
                $errors[] = 'برای شغل «' . $occupation_label . '» حداقل یک مدرک انتخاب کنید یا تفکیک مدارک بر اساس شغل را غیرفعال کنید.';
            }
        }

        $posted_document_descriptions = isset($_POST['required_document_descriptions']) && is_array($_POST['required_document_descriptions'])
            ? wp_unslash($_POST['required_document_descriptions'])
            : [];
        $required_document_descriptions = [];
        if (!empty($posted_document_descriptions)) {
            foreach ($posted_document_descriptions as $document_key => $document_description) {
                $document_key = sanitize_key($document_key);
                if ($document_key === '' || !isset($all_possible_documents[$document_key])) {
                    continue;
                }
                $document_description = sanitize_text_field($document_description);
                if ($document_description !== '') {
                    $required_document_descriptions[$document_key] = $document_description;
                }
            }
        }

        $settings = [];
        $prefix = $type;

        // Function to parse formatted numbers
        $parse_number = function($value) {
            if (empty($value)) return 0;
            $persian_numbers_map = ['۰'=>'0', '۱'=>'1', '۲'=>'2', '۳'=>'3', '۴'=>'4', '۵'=>'5', '۶'=>'6', '۷'=>'7', '۸'=>'8', '۹'=>'9'];
            $english_str = strtr($value, $persian_numbers_map);
            return floatval(preg_replace('/[^\d.]/', '', $english_str)); // Keep decimal point for rates
        };

        // Common fields
        $settings['requires_bank_validation'] = isset($_POST[$prefix . '_requires_bank_validation']) ? 1 : 0;
        $settings['visibility_contexts'] = $selected_visibility_contexts;
        // نگهداری کلید قدیمی برای سازگاری افزونه‌های جانبی و نسخه‌های قبلی.
        $settings['applicant_visible'] = in_array('mlr_applicant_loan_request_form', $selected_visibility_contexts, true) ? 1 : 0;
        $settings['min_amount'] = $parse_number($_POST[$prefix . '_min_amount'] ?? 0);
        $settings['max_amount'] = $parse_number($_POST[$prefix . '_max_amount'] ?? 0);
        $raw_active_installments = mlr_normalize_digits($_POST[$prefix . '_active_installments'] ?? '', false);
        $normalized_installments = [];
        foreach (preg_split('/\s*[,،]\s*/u', (string) $raw_active_installments) as $installment_value) {
            $installment_value = absint($installment_value);
            if ($installment_value > 0 && $installment_value <= 600) $normalized_installments[] = $installment_value;
        }
        $normalized_installments = array_values(array_unique($normalized_installments));
        sort($normalized_installments, SORT_NUMERIC);
        $settings['active_installments'] = implode(',', $normalized_installments);

        $read_percent = static function($key) {
            return isset($_POST[$key]) ? (float) mlr_parse_decimal_number(wp_unslash($_POST[$key])) : 0.0;
        };
        $settings['supplier_costs_percent'] = $read_percent($prefix . '_supplier_costs_percent');
        $settings['intermediary_deductions_percent'] = $read_percent($prefix . '_intermediary_deductions_percent');
        $settings['babanovel_deductions_percent'] = $read_percent($prefix . '_babanovel_deductions_percent');
        $settings['insurance_percent'] = $read_percent($prefix . '_insurance_percent');
        $settings['guarantee_check_percent'] = $read_percent($prefix . '_guarantee_check_percent');
        $settings['acceptor_commission_percent'] = $read_percent($prefix . '_acceptor_commission_percent');
        $settings['additional_deductions_percent'] = $read_percent($prefix . '_additional_deductions_percent');
        $settings['acceptor_deductions_amount'] = $parse_number($_POST[$prefix . '_acceptor_deductions_amount'] ?? 0);
        $settings['vat_percent'] = $read_percent($prefix . '_vat_percent');

        if ($type === 'market') {
            $settings['fixed_monthly_rate'] = $read_percent('market_fixed_monthly_rate');
            $settings['prepayment_percent'] = $read_percent('market_prepayment_percent');
        } elseif ($type === 'leasing') {
            $settings['min_interval'] = absint($_POST['leasing_min_interval'] ?? 1);
            $settings['max_interval'] = absint($_POST['leasing_max_interval'] ?? 1);
            $settings['interest_rate'] = $read_percent('leasing_interest_rate');
            $settings['effective_interest_rate'] = $read_percent('leasing_effective_interest_rate');
            $settings['credit_share_percent'] = $read_percent('leasing_credit_share_percent');
            $settings['other_deductions_amount'] = $parse_number($_POST['leasing_other_deductions_amount'] ?? 0);
        }

        $settings['plan_letterhead_url'] = isset($_POST['plan_letterhead_url']) ? esc_url_raw(trim($_POST['plan_letterhead_url'])) : '';
        $settings['plan_footer_url'] = isset($_POST['plan_footer_url']) ? esc_url_raw(trim($_POST['plan_footer_url'])) : '';
        if (isset($_POST['plan_contract_text'])) {
            $settings['plan_contract_text'] = wp_kses_post(wp_unslash($_POST['plan_contract_text']));
        }
        $settings['plan_contract_template_key'] = isset($_POST['plan_contract_template_key'])
            ? sanitize_key(wp_unslash($_POST['plan_contract_template_key']))
            : '';
        $first_due_rule = isset($_POST['contract_first_due_rule']) ? sanitize_key(wp_unslash($_POST['contract_first_due_rule'])) : 'interval';
        $settings['contract_first_due_rule'] = in_array($first_due_rule, ['interval', 'jalali_month_end_half'], true) ? $first_due_rule : 'interval';
        // Per-plan contract signing mode. Existing plans remain physical unless this switch is enabled.
        $settings['contract_signing_mode'] = isset($_POST['contract_signing_digital']) ? 'digital' : 'physical';
        // Existing plans keep the legacy requirement unless an admin explicitly turns this switch off.
        $settings['completed_check_files_enabled'] = isset($_POST['completed_check_files_enabled']) ? 1 : 0;
        $settings['plan_conditions'] = isset($_POST['plan_conditions']) ? wp_kses_post(wp_unslash($_POST['plan_conditions'])) : '';
        $settings['required_document_descriptions'] = $required_document_descriptions;
        $settings['occupation_documents_enabled'] = $occupation_documents_enabled;
        $settings['required_documents_by_occupation'] = $required_documents_by_occupation;
        $downloadable_files = [];
        if (isset($_POST['plan_downloadable_files']) && is_array($_POST['plan_downloadable_files'])) {
            foreach ($_POST['plan_downloadable_files'] as $file_url) {
                if (!empty(trim($file_url))) {
                    $downloadable_files[] = esc_url_raw(trim($file_url));
                }
            }
        }
        $settings['plan_downloadable_files'] = $downloadable_files;

        $data = [ 'name' => $name, 'type' => $type, 'is_active' => $is_active, 'settings' => serialize($settings), 'display_fields' => serialize($display_fields), 'display_fields_context' => serialize($sanitized_display_fields_context), 'required_documents' => serialize($required_documents) ];

        if (empty($name)) $errors[] = 'نام طرح الزامی است.';
        if ($settings['min_amount'] < 0) $errors[] = 'حداقل مبلغ نمی‌تواند منفی باشد.';
        if ($settings['max_amount'] <= 0) $errors[] = 'حداکثر مبلغ باید بزرگتر از صفر باشد.';
        elseif ($settings['max_amount'] < $settings['min_amount']) $errors[] = 'حداکثر مبلغ نمی‌تواند کمتر از حداقل مبلغ باشد.';

        $bounded_percent_fields = [
            'supplier_costs_percent', 'intermediary_deductions_percent', 'babanovel_deductions_percent',
            'insurance_percent', 'acceptor_commission_percent', 'additional_deductions_percent', 'vat_percent'
        ];
        foreach ($bounded_percent_fields as $field_key) {
            if (($settings[$field_key] ?? 0) < 0 || ($settings[$field_key] ?? 0) > 100) {
                $errors[] = 'مقدار درصدی «' . $field_key . '» باید بین صفر تا ۱۰۰ باشد.';
            }
        }
        if (($settings['guarantee_check_percent'] ?? 0) < 0 || ($settings['guarantee_check_percent'] ?? 0) > 1000) {
            $errors[] = 'درصد چک ضمانت باید بین صفر تا ۱۰۰۰ باشد.';
        }
        if (($settings['acceptor_deductions_amount'] ?? 0) < 0) $errors[] = 'مبلغ کسورات پذیرنده نمی‌تواند منفی باشد.';
        if ($type === 'market') {
            if (($settings['fixed_monthly_rate'] ?? 0) < 0 || ($settings['fixed_monthly_rate'] ?? 0) > 1000) $errors[] = 'نرخ ماهانه نامعتبر است.';
            if (($settings['prepayment_percent'] ?? 0) < 0 || ($settings['prepayment_percent'] ?? 0) > 100) $errors[] = 'درصد پیش‌پرداخت باید بین صفر تا ۱۰۰ باشد.';
        } elseif ($type === 'leasing') {
            if (($settings['min_interval'] ?? 0) < 1 || ($settings['max_interval'] ?? 0) < ($settings['min_interval'] ?? 1) || ($settings['max_interval'] ?? 0) > 60) $errors[] = 'بازه فاصله اقساط نامعتبر است.';
            if (($settings['interest_rate'] ?? 0) < 0 || ($settings['interest_rate'] ?? 0) > 1000) $errors[] = 'نرخ سود نامعتبر است.';
            if (($settings['effective_interest_rate'] ?? 0) < 0 || ($settings['effective_interest_rate'] ?? 0) > 1000) $errors[] = 'نرخ سود مؤثر نامعتبر است.';
            if (($settings['credit_share_percent'] ?? 0) < 0 || ($settings['credit_share_percent'] ?? 0) > 100) $errors[] = 'سهم اعتبار باید بین صفر تا ۱۰۰ باشد.';
            if (($settings['other_deductions_amount'] ?? 0) < 0) $errors[] = 'سایر کسورات نمی‌تواند منفی باشد.';
        }

        if (empty($errors)) {
            if ($plan_id > 0) {
                $db_result = $wpdb->update($table_name, $data, ['id' => $plan_id]);
                if ($db_result === false) {
                    $errors[] = 'خطا در بروزرسانی طرح. هیچ تغییری ثبت نشد.';
                    if (function_exists('mlr_log')) mlr_log('Installment plan update failed.', ['plan_id' => $plan_id, 'db_error' => $wpdb->last_error]);
                } else {
                    $success = 'طرح با موفقیت به‌روزرسانی شد.';
                }
            } else {
                $data['created_at'] = current_time('mysql');
                if (function_exists('mlr_installment_plan_display_order_column_exists') && mlr_installment_plan_display_order_column_exists()) {
                    $max_display_order = (int) $wpdb->get_var("SELECT MAX(`display_order`) FROM `{$table_name}`");
                    $data['display_order'] = $max_display_order + 10;
                }
                $db_result = $wpdb->insert($table_name, $data);
                if ($db_result === false) {
                    $errors[] = 'خطا در ایجاد طرح. هیچ داده ناقصی ثبت نشد.';
                    if (function_exists('mlr_log')) mlr_log('Installment plan insert failed.', ['db_error' => $wpdb->last_error]);
                } else {
                    $success = 'طرح جدید با موفقیت ایجاد شد.';
                }
            }
        }
    }

    // --- Fetch Data for Display ---
    $plan_order_sql = function_exists('mlr_get_installment_plan_order_by_sql') ? mlr_get_installment_plan_order_by_sql() : 'ORDER BY id DESC';
    $plans = $wpdb->get_results("SELECT * FROM $table_name {$plan_order_sql}");
    $plan_signature_rows = is_array($plans) ? $plans : [];
    usort($plan_signature_rows, static function($a, $b) { return ((int) $a->id) <=> ((int) $b->id); });
    $plan_signature_parts = array_map(static function($plan) { return ((int) $plan->id) . ':' . ((int) ($plan->display_order ?? 0)); }, $plan_signature_rows);
    $plan_order_signature = hash('sha256', implode('|', $plan_signature_parts));

    $js_plans = array_map(function($plan) {
        $plan->settings = maybe_unserialize($plan->settings);
        $plan->display_fields = maybe_unserialize($plan->display_fields);
        $plan->required_documents = maybe_unserialize($plan->required_documents);
        $plan->display_fields_context = maybe_unserialize($plan->display_fields_context);
        if (!is_array($plan->settings)) $plan->settings = [];
        if (!is_array($plan->display_fields)) $plan->display_fields = [];
        if (!is_array($plan->required_documents)) $plan->required_documents = [];
        if (!is_array($plan->display_fields_context)) $plan->display_fields_context = [];
        $plan->resolved_visibility_contexts = function_exists('mlr_get_plan_visibility_contexts')
            ? mlr_get_plan_visibility_contexts($plan)
            : [];

        // Hami retirees HTML is already fully structured with <p> blocks.
        // Do not re-run wpautop over this specific template when loading TinyMCE:
        // it can disrupt paragraph boundaries. Other plans keep their legacy path.
        // Display-only preparation; this does not modify saved plan data.
        if (!empty($plan->settings['plan_contract_text']) &&
            strpos((string) $plan->settings['plan_contract_text'], 'mlr-hami-retirees-contract') === false) {
            $plan->settings['plan_contract_text'] = wpautop($plan->settings['plan_contract_text']);
        }

        return $plan;
    }, $plans);

    // --- END: PHP Logic ---
    ?>
    <div class="wrap mlr-admin-wrap mlr-plans-page">
        <div class="mlr-page-header">
            <h1>مدیریت طرح‌های اقساطی</h1>
            <button type="button" class="page-title-action" id="mlr-add-new-plan-btn"><span class="dashicons dashicons-plus-alt2"></span> افزودن طرح جدید</button>
        </div>

        <?php if ($success) : ?><div id="message" class="notice notice-success is-dismissible mlr-notice"><p><?php echo esc_html($success); ?></p></div><?php endif; ?>
        <?php if (!empty($action_notice_errors)) : ?><div class="notice notice-warning is-dismissible mlr-notice mlr-action-notice"><p><?php echo implode('<br>', array_map('esc_html', $action_notice_errors)); ?></p></div><?php endif; ?>
        <?php if (!empty($errors)) : ?><div id="message" class="notice notice-error is-dismissible mlr-notice"><p><?php echo implode('<br>', array_map('esc_html', $errors)); ?></p></div><?php endif; ?>

        <div id="mlr-plans-layout">
            
            <div class="mlr-plans-list-column">
                <div class="mlr-card mlr-plans-list-card">
                    <h2 class="mlr-card-title"><span class="dashicons dashicons-list-view"></span> لیست و ترتیب نمایش طرح‌ها</h2>
                    <div class="mlr-card-content no-padding">
                        <form method="post" action="<?php echo esc_url(mlr_installment_plans_admin_url()); ?>" id="mlr-plan-order-form">
                            <?php wp_nonce_field('mlr_save_plan_order_action', 'mlr_plan_order_nonce'); ?>
                            <input type="hidden" name="mlr_save_plan_order" value="1">
                            <input type="hidden" name="plan_order_signature" value="<?php echo esc_attr($plan_order_signature); ?>">
                            <div class="mlr-plan-order-help">
                                <span class="dashicons dashicons-move"></span>
                                ردیف‌ها را با دستگیره جابه‌جا کنید؛ ترتیب ذخیره‌شده در فرم متقاضی، پذیرنده، محاسبه‌گرها، فیلترهای مدیریتی و سایر لیست‌های طرح اعمال می‌شود.
                            </div>
                        <table class="wp-list-table widefat striped mlr-compact-table mlr-sortable-plans-table">
                            <thead>
                                <tr>
                                    <th class="mlr-order-handle-col">ترتیب</th>
                                    <th>نام طرح</th>
                                    <th>نوع</th>
                                    <th>وضعیت</th>
                                    <th>محل‌های نمایش</th>
                                    <th class="mlr-actions-col">عملیات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($plans)): ?>
                                    <tr class="no-items"><td colspan="6">هیچ طرحی یافت نشد.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($plans as $plan) : ?>
                                        <tr class="mlr-plan-row <?php echo !$plan->is_active ? 'inactive-row' : ''; ?>" data-plan-id="<?php echo esc_attr($plan->id); ?>">
                                            <td class="mlr-order-handle-cell"><span class="dashicons dashicons-move mlr-plan-drag-handle" title="برای جابه‌جایی بکشید"></span><span class="mlr-order-position"></span><input type="hidden" name="plan_order[]" value="<?php echo esc_attr($plan->id); ?>"></td>
                                            <td>
                                                <a href="#" class="edit-plan mlr-plan-name" data-id="<?php echo esc_attr($plan->id); ?>"><?php echo esc_html($plan->name); ?></a>
                                            </td>
                                            <td><?php $plan_types = ['market' => 'بازاری', 'leasing' => 'لیزینگی']; echo esc_html($plan_types[$plan->type] ?? $plan->type); ?></td>
                                            <td><?php echo $plan->is_active ? '<span class="mlr-status-active"><span class="dashicons dashicons-yes-alt"></span> فعال</span>' : '<span class="mlr-status-inactive"><span class="dashicons dashicons-minus"></span> غیرفعال</span>'; ?></td>
                                            <td>
                                                <?php
                                                $plan_visibility_keys = function_exists('mlr_get_plan_visibility_contexts')
                                                    ? mlr_get_plan_visibility_contexts($plan)
                                                    : [];
                                                $visibility_short_labels = [
                                                    'mlr_loan_request_form' => 'پذیرنده',
                                                    'mlr_applicant_loan_request_form' => 'متقاضی',
                                                    'mlr_loan_calculator' => 'محاسبه‌گر',
                                                ];
                                                ?>
                                                <div class="mlr-plan-list-visibility">
                                                    <?php if (empty($plan_visibility_keys)): ?>
                                                        <span class="mlr-visibility-list-empty">بدون نمایش</span>
                                                    <?php else: ?>
                                                        <?php foreach ($plan_visibility_keys as $visibility_key): ?>
                                                            <?php if (isset($visibility_short_labels[$visibility_key])): ?>
                                                                <span class="mlr-visibility-list-badge"><?php echo esc_html($visibility_short_labels[$visibility_key]); ?></span>
                                                            <?php endif; ?>
                                                        <?php endforeach; ?>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td class="mlr-actions-col">
                                                <button type="button" class="button button-small button-secondary edit-plan" data-id="<?php echo esc_attr($plan->id); ?>" title="ویرایش"><span class="dashicons dashicons-edit"></span></button>
                                                <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=mlr_duplicate_installment_plan&plan_id=' . $plan->id), 'mlr_duplicate_plan_' . $plan->id)); ?>" class="button button-small button-secondary mlr-duplicate-plan-link" title="تکثیر طرح"><span class="dashicons dashicons-admin-page"></span></a>
                                                <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=mlr_delete_installment_plan&plan_id=' . $plan->id), 'mlr_delete_plan_' . $plan->id)); ?>" class="button button-small button-link-delete mlr-delete-link" title="حذف"><span class="dashicons dashicons-trash"></span></a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                        <?php if (!empty($plans)): ?>
                            <div class="mlr-plan-order-actions">
                                <button type="submit" class="button button-primary"><span class="dashicons dashicons-saved"></span> ذخیره ترتیب نمایش</button>
                                <span class="description">تا زمانی که این دکمه را نزنید، جابه‌جایی‌ها ذخیره نمی‌شوند.</span>
                            </div>
                        <?php endif; ?>
                        </form>
                    </div>
                </div>
                
                <div class="mlr-card mlr-live-calculator-card">
                     <h2 class="mlr-card-title"><span class="dashicons dashicons-calculator"></span> محاسبه‌گر زنده</h2>
                     <div class="mlr-card-content">
                        <div class="mlr-form-row">
                            <label for="calc_plan_select">انتخاب طرح (همه طرح‌ها، فعال و غیرفعال)</label>
                            <select id="calc_plan_select" class="mlr-select">
                                <option value="">-- انتخاب کنید --</option>
                                <?php foreach ($plans as $plan): ?>
                                    <?php
                                    // این محاسبه‌گر مخصوص مدیران است؛ وضعیت فعال/غیرفعال نباید باعث حذف طرح شود.
                                    $calculator_plan_status = ((int) $plan->is_active === 1) ? 'فعال' : 'غیرفعال';
                                    ?>
                                    <option
                                        value="<?php echo esc_attr($plan->id); ?>"
                                        data-type="<?php echo esc_attr($plan->type); ?>"
                                        data-is-active="<?php echo esc_attr((int) $plan->is_active); ?>"
                                    ><?php echo esc_html($plan->name . ' — ' . $calculator_plan_status); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mlr-form-row">
                            <label id="calc_amount_label">مبلغ (ریال)</label>
                            <input type="text" id="calc_amount" class="mlr-input mlr-formatted-number" value="350,000,000">
                        </div>
                        <div class="mlr-form-row">
                             <label>تعداد اقساط</label>
                             <select id="calc_installments" class="mlr-select"></select>
                         </div>
                         <div class="mlr-form-row" id="calc_interval_wrapper" style="display:none;">
                             <label>فاصله اقساط (ماه)</label>
                             <input type="number" id="calc_interval" class="mlr-input small-input" value="1">
                         </div>
                        <button id="run_calculator" class="button button-secondary"><span class="dashicons dashicons-controls-play"></span> محاسبه</button>
                        <div id="calculator_result" class="mlr-calculator-result">نتیجه اینجا نمایش داده می‌شود.</div>
                     </div>
                </div>
                
            </div>

            <div class="mlr-plan-form-column">
                <form method="post" action="<?php echo esc_url(mlr_installment_plans_admin_url()); ?>" id="mlr-plan-form" class="validate mlr-card mlr-plan-form-card">
                     <h2 class="mlr-card-title"><span class="dashicons dashicons-edit-page"></span> <span id="plan-form-title">افزودن طرح جدید</span></h2>
                     <div class="mlr-card-content">
                        <?php wp_nonce_field('mlr_installment_plan_action', '_wpnonce'); ?>
                        <input type="hidden" name="plan_id" id="plan_id" value="0">

                        <div class="mlr-tabs">
                            <button type="button" class="mlr-tab-button active" data-tab="general">اطلاعات و محل نمایش</button>
                            <button type="button" class="mlr-tab-button" data-tab="settings">تنظیمات محاسباتی</button>
                            <button type="button" class="mlr-tab-button" data-tab="docs">مدارک و فیلدها</button>
                            <button type="button" class="mlr-tab-button" data-tab="contract">قرارداد و فایل‌ها</button>
                        </div>

                        <div class="mlr-tab-content active" id="tab-general">
                            <div class="mlr-form-row"><label for="plan_name">نام طرح <span class="mlr-required">*</span></label><input type="text" name="plan_name" id="plan_name" class="mlr-input" required></div>
                            <div class="mlr-form-row"><label for="plan_type">نوع طرح <span class="mlr-required">*</span></label><select name="plan_type" id="plan_type" class="mlr-select" required><option value="market">بازاری</option><option value="leasing">لیزینگی</option></select></div>
                            <div class="mlr-form-row mlr-plan-active-row">
                                <label><input type="checkbox" name="is_active" value="1" checked> <strong>طرح فعال باشد</strong></label>
                                <p class="description">طرح غیرفعال در هیچ پنل یا شورتکدی نمایش داده نمی‌شود؛ حتی اگر محل‌های نمایش زیر انتخاب شده باشند.</p>
                            </div>

                            <div class="mlr-plan-visibility-section">
                                <div class="mlr-visibility-heading">
                                    <div>
                                        <h3 class="mlr-inner-title">محل نمایش خودِ طرح</h3>
                                        <p class="description">مشخص کنید نام این طرح در کدام فرم یا محاسبه‌گر به‌عنوان گزینه قابل انتخاب دیده شود. این بخش با «فیلدهای نتیجه محاسبه» در تب مدارک و فیلدها متفاوت است.</p>
                                    </div>
                                    <div id="mlr-visibility-summary" class="mlr-visibility-summary" aria-live="polite"></div>
                                </div>
                                <div class="mlr-visibility-grid">
                                    <?php foreach ($visibility_contexts as $context_key => $context_info): ?>
                                        <label class="mlr-visibility-card">
                                            <span class="mlr-visibility-card-check">
                                                <input type="checkbox" class="mlr-plan-visibility-input" name="visibility_contexts[]" value="<?php echo esc_attr($context_key); ?>" <?php checked($context_key !== 'mlr_applicant_loan_request_form'); ?>>
                                            </span>
                                            <span class="mlr-visibility-card-content">
                                                <span class="mlr-visibility-card-title"><?php echo esc_html($context_info['label']); ?></span>
                                                <span class="mlr-visibility-card-panel"><?php echo esc_html($context_info['panel']); ?></span>
                                                <code class="mlr-shortcode-code"><?php echo esc_html($context_info['shortcode']); ?></code>
                                                <span class="mlr-visibility-card-description"><?php echo esc_html($context_info['description']); ?></span>
                                            </span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                                <div class="notice notice-info inline mlr-visibility-note">
                                    <p><strong>نکته:</strong> انتخاب «فرم پذیرنده» به‌تنهایی کافی نیست؛ طرح باید در پروفایل همان پذیرنده نیز مجاز شده باشد. انتخاب نکردن هیچ موردی یعنی طرح فعال می‌ماند اما در هیچ‌یک از این سه مسیر قابل انتخاب نخواهد بود.</p>
                                </div>
                            </div>
                        </div>

                        <div class="mlr-tab-content" id="tab-settings">
                            <div id="market_fields" class="plan-settings-fields">
                                <h3 class="mlr-inner-title">تنظیمات طرح بازاری</h3>
                                <div class="mlr-grid-2">
                                    <div class="mlr-form-row"><label>حداقل مبلغ (ریال)</label><input type="text" name="market_min_amount" value="10,000,000" class="mlr-input mlr-formatted-number"></div>
                                    <div class="mlr-form-row"><label>حداکثر مبلغ (ریال)</label><input type="text" name="market_max_amount" value="500,000,000" class="mlr-input mlr-formatted-number"></div>
                                    <div class="mlr-form-row"><label>سود ثابت ماهیانه (%)</label><input type="number" name="market_fixed_monthly_rate" step="0.01" value="3" class="mlr-input small-input"></div>
                                    <div class="mlr-form-row"><label>درصد پیش پرداخت (%)</label><input type="number" name="market_prepayment_percent" step="0.1" value="20" class="mlr-input small-input"></div>
                                    <div class="mlr-form-row"><label>هزینه عملیات (تامین کننده) (%)</label><input type="number" name="market_supplier_costs_percent" step="0.01" value="1" class="mlr-input small-input"></div>
                                    <div class="mlr-form-row"><label>کسورات واسطه (%)</label><input type="number" name="market_intermediary_deductions_percent" step="0.01" value="1" class="mlr-input small-input"></div>
                                    <div class="mlr-form-row"><label>کسورات بابانوئل (%)</label><input type="number" name="market_babanovel_deductions_percent" step="0.01" value="1" class="mlr-input small-input"></div>
                                    <div class="mlr-form-row"><label>بیمه تسهیلات (%)</label><input type="number" name="market_insurance_percent" step="0.01" value="1" class="mlr-input small-input"></div>
                                    <div class="mlr-form-row"><label>چک ضمانت (%)</label><input type="number" name="market_guarantee_check_percent" step="0.1" value="150" class="mlr-input small-input"></div>
                                    <div class="mlr-form-row"><label>کمیسیون پذیرنده (%)</label><input type="number" name="market_acceptor_commission_percent" step="0.01" value="1" class="mlr-input small-input"></div>
                                    <div class="mlr-form-row"><label>سایر کسورات (%)</label><input type="number" name="market_additional_deductions_percent" step="0.01" value="1" class="mlr-input small-input"></div>
                                    <div class="mlr-form-row"><label>مبلغ سایر کسورات (ریال)</label><input type="text" name="market_acceptor_deductions_amount" value="30,000,000" class="mlr-input mlr-formatted-number"></div>
                                    <div class="mlr-form-row"><label>ارزش افزوده (%)</label><input type="number" name="market_vat_percent" step="0.1" value="10" class="mlr-input small-input"></div>
                                </div>
                                <div class="mlr-form-row"><label>تعداد اقساط فعال (با کاما جدا کنید)</label><input type="text" name="market_active_installments" value="6,12,18,24" class="mlr-input ltr"></div>
                                <div class="mlr-form-row"><label><input type="checkbox" name="market_requires_bank_validation" value="1"> نیازمند اعتبارسنجی بانکی</label></div>
                            </div>

                            <div id="leasing_fields" class="plan-settings-fields" style="display:none;">
                               <h3 class="mlr-inner-title">تنظیمات طرح لیزینگی</h3>
                               <div class="mlr-grid-2">
                                    <div class="mlr-form-row"><label>حداقل مبلغ خرید (ریال)</label><input type="text" name="leasing_min_amount" value="100,000,000" class="mlr-input mlr-formatted-number"></div>
                                    <div class="mlr-form-row"><label>حداکثر مبلغ خرید (ریال)</label><input type="text" name="leasing_max_amount" value="1,000,000,000" class="mlr-input mlr-formatted-number"></div>
                                    <div class="mlr-form-row"><label>حداقل فاصله اقساط (ماه)</label><input type="number" name="leasing_min_interval" value="1" class="mlr-input small-input"></div>
                                    <div class="mlr-form-row"><label>حداکثر فاصله اقساط (ماه)</label><input type="number" name="leasing_max_interval" value="3" class="mlr-input small-input"></div>
                                    <div class="mlr-form-row"><label>نرخ سود طرح (%)</label><input type="number" name="leasing_interest_rate" step="0.01" value="23" class="mlr-input small-input"></div>
                                    <div class="mlr-form-row"><label>نرخ سود موثر (%)</label><input type="number" name="leasing_effective_interest_rate" step="0.01" value="25" class="mlr-input small-input"></div>
                                    <div class="mlr-form-row"><label>درصد سهم اعتباری (%)</label><input type="number" name="leasing_credit_share_percent" step="0.1" value="80" class="mlr-input small-input"></div>
                                    <div class="mlr-form-row"><label>هزینه های تامین کننده مالی (%)</label><input type="number" name="leasing_supplier_costs_percent" step="0.01" value="1" class="mlr-input small-input"></div>
                                    <div class="mlr-form-row"><label>کسورات واسطه (%)</label><input type="number" name="leasing_intermediary_deductions_percent" step="0.01" value="0.5" class="mlr-input small-input"></div>
                                    <div class="mlr-form-row"><label>کسورات بابانوئل (%)</label><input type="number" name="leasing_babanovel_deductions_percent" step="0.01" value="1" class="mlr-input small-input"></div>
                                    <div class="mlr-form-row"><label>بیمه تسهیلات (%)</label><input type="number" name="leasing_insurance_percent" step="0.01" value="0.5" class="mlr-input small-input"></div>
                                    <div class="mlr-form-row"><label>چک ضمانت (%)</label><input type="number" name="leasing_guarantee_check_percent" step="0.1" value="130" class="mlr-input small-input"></div>
                                    <div class="mlr-form-row"><label>کمیسیون پذیرنده (%)</label><input type="number" name="leasing_acceptor_commission_percent" step="0.01" value="2" class="mlr-input small-input"></div>
                                    <div class="mlr-form-row"><label>سایر کسورات (پذیرنده) (%)</label><input type="number" name="leasing_additional_deductions_percent" step="0.01" value="0" class="mlr-input small-input"></div>
                                    <div class="mlr-form-row"><label>ارزش افزوده (%)</label><input type="number" name="leasing_vat_percent" step="0.1" value="10" class="mlr-input small-input"></div>
                                </div>
                                <div class="mlr-form-row"><label>تعداد اقساط فعال (با کاما جدا کنید)</label><input type="text" name="leasing_active_installments" value="12,18,24,36" class="mlr-input ltr"></div>
                                <div class="mlr-form-row"><label>مبلغ سایر کسورات (لیزینگ)</label><input type="text" name="leasing_other_deductions_amount" value="0" class="mlr-input mlr-formatted-number"></div>
                                <div class="mlr-form-row"><label>مبلغ سایر کسورات (پذیرنده)</label><input type="text" name="leasing_acceptor_deductions_amount" value="0" class="mlr-input mlr-formatted-number"></div>
                                <div class="mlr-form-row"><label><input type="checkbox" name="leasing_requires_bank_validation" value="1"> نیازمند اعتبارسنجی بانکی</label></div>
                            </div>
                        </div>

                        <div class="mlr-tab-content" id="tab-docs">
                             <div class="mlr-checkbox-group required-documents-container">
                                <h3 class="mlr-inner-title">مدارک پیش‌فرض طرح</h3>
                                <p class="description" style="margin:0 0 12px">این لیست برای طرح‌های قدیمی و زمانی که تفکیک شغلی غیرفعال است استفاده می‌شود. در محاسبه‌گر عمومی نیز همین فهرست به‌عنوان پیش‌نمایش نمایش داده می‌شود.</p>
                                <?php foreach ($all_possible_documents as $key => $label): ?>
                                    <div class="mlr-checkbox-label mlr-doc-config-row">
                                        <label class="mlr-doc-config-main">
                                            <input type="checkbox" name="required_documents[]" value="<?php echo esc_attr($key); ?>">
                                            <span><?php echo esc_html($label); ?></span>
                                        </label>
                                        <input type="text" name="required_document_descriptions[<?php echo esc_attr($key); ?>]" class="mlr-input mlr-doc-description-input" data-doc-key="<?php echo esc_attr($key); ?>" placeholder="توضیح کوتاه مدرک (اختیاری)">
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="mlr-occupation-documents-config" style="margin-top:22px">
                                <label class="mlr-checkbox-label" style="font-weight:700;align-items:center">
                                    <input type="checkbox" name="occupation_documents_enabled" id="occupation_documents_enabled" value="1">
                                    <span>تفکیک مدارک بر اساس شغل متقاضی</span>
                                </label>
                                <p class="description" style="margin:8px 0 14px">با فعال‌کردن این گزینه، در فرم ثبت درخواست فقط مدارک مربوط به شغل انتخاب‌شده نمایش داده و همان مدارک در سرور اجباری می‌شوند.</p>
                                <div id="mlr-occupation-documents-panel" style="display:none">
                                    <div style="margin-bottom:12px">
                                        <button type="button" class="button" id="mlr-copy-default-docs-to-occupations">کپی مدارک پیش‌فرض برای همه شغل‌ها</button>
                                        <span class="description" style="margin-right:8px">برای شروع سریع؛ بعد می‌توانید هر شغل را جدا تغییر دهید.</span>
                                    </div>
                                    <div class="mlr-occupation-documents-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:14px">
                                        <?php foreach ($occupation_options as $occupation_key => $occupation_label): ?>
                                            <div class="mlr-occupation-documents-card" data-occupation="<?php echo esc_attr($occupation_key); ?>" style="border:1px solid #dcdcde;border-radius:10px;padding:14px;background:#fff">
                                                <h4 style="margin:0 0 10px"><?php echo esc_html($occupation_label); ?></h4>
                                                <?php foreach ($all_possible_documents as $doc_key => $doc_label): ?>
                                                    <label style="display:flex;gap:7px;align-items:flex-start;margin:7px 0">
                                                        <input type="checkbox" class="mlr-occupation-doc-checkbox" name="required_documents_by_occupation[<?php echo esc_attr($occupation_key); ?>][]" value="<?php echo esc_attr($doc_key); ?>">
                                                        <span><?php echo esc_html($doc_label); ?></span>
                                                    </label>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="mlr-form-row mlr-plan-conditions-row">
                                <label for="plan_conditions">شرایط طرح</label>
                                <textarea name="plan_conditions" id="plan_conditions" class="mlr-input mlr-plan-conditions-input" rows="5" placeholder="شرایط کلی این طرح را وارد کنید."></textarea>
                                <p class="description">این متن در کنار مدارک مورد نیاز طرح در فرم‌ها و محاسبه‌گر نمایش داده می‌شود.</p>
                            </div>
                            <div class="mlr-checkbox-group display-fields-container">
                                <h3 class="mlr-inner-title">فیلدهای نتیجه محاسبه <span class="mlr-not-visibility-badge">این بخش محل نمایش طرح نیست</span></h3>
                                <p class="description" style="margin:0 0 12px 0;">پس از انتخاب طرح و انجام محاسبه، تعیین می‌کند کدام ردیف‌های نتیجه مثل مبلغ قسط، پیش‌پرداخت یا سود نمایش داده شوند. نمایش یا عدم نمایش نام خودِ طرح از تب «اطلاعات و محل نمایش» کنترل می‌شود.</p>

                                <div class="mlr-display-fields-scope" data-context="default">
                                    <h4 style="margin: 12px 0 8px 0;">تنظیم پیش‌فرض نتایج</h4>
                                    <p class="description">هر شورتکدی که تنظیم اختصاصی نداشته باشد از انتخاب‌های این بخش استفاده می‌کند. خالی گذاشتن پیش‌فرض یعنی نمایش همه فیلدهای نتیجه.</p>
                                    <?php foreach ($calculation_labels as $key => $label_info): ?>
                                        <label class="mlr-checkbox-label"><input type="checkbox" name="display_fields[]" value="<?php echo esc_attr($key); ?>"> <?php echo esc_html($label_info['label']); ?></label>
                                    <?php endforeach; ?>
                                </div>

                                <?php
                                $mlr_display_contexts = [
                                    'mlr_loan_request_form'            => 'فرم ثبت درخواست پذیرنده — [mlr_loan_request_form]',
                                    'mlr_applicant_loan_request_form'  => 'فرم ثبت درخواست متقاضی — [mlr_applicant_loan_request_form]',
                                    'mlr_loan_calculator'              => 'محاسبه‌گر عمومی اقساط — [mlr_loan_calculator]',
                                ];
                            ?>

                                <?php foreach ($mlr_display_contexts as $ctx_key => $ctx_label): ?>
                                    <div class="mlr-display-fields-scope display-fields-context-container" data-context="<?php echo esc_attr($ctx_key); ?>">
                                        <h4 style="margin: 18px 0 8px 0;">نتایج اختصاصی در <?php echo esc_html($ctx_label); ?></h4>
                                        <p class="description">در صورت خالی گذاشتن این قسمت، تنظیم پیش‌فرض بالا اعمال می‌شود.</p>
                                        <?php foreach ($calculation_labels as $key => $label_info): ?>
                                            <label class="mlr-checkbox-label"><input type="checkbox" name="display_fields_context[<?php echo esc_attr($ctx_key); ?>][]" value="<?php echo esc_attr($key); ?>"> <?php echo esc_html($label_info['label']); ?></label>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <div class="mlr-tab-content" id="tab-contract">
                             <h3 class="mlr-inner-title">تنظیمات قرارداد و فایل‌های پیوست</h3>
                             <div class="mlr-form-row mlr-contract-signing-mode-box" style="padding:16px 18px; margin-bottom:18px; border:1px solid #dcdcde; border-radius:10px; background:#f8fafc;">
                                 <div style="display:flex;align-items:center;justify-content:space-between;gap:18px;flex-wrap:wrap;">
                                     <div>
                                         <strong style="display:block;font-size:14px;margin-bottom:5px;">امضای دیجیتال قرارداد دریافت تسهیلات</strong>
                                         <span class="description" style="margin:0;">خاموش: قرارداد فیزیکی و آپلود نسخه امضاشده — روشن: تأیید قرارداد با کد ۶ رقمی پیامکی و بدون آپلود فایل قرارداد.</span>
                                     </div>
                                     <label class="mlr-switch" style="margin:0;" title="انتخاب نوع قرارداد این طرح">
                                         <input type="checkbox" name="contract_signing_digital" id="contract_signing_digital" value="1">
                                         <span class="mlr-slider round"></span>
                                     </label>
                                 </div>
                                 <div id="mlr-contract-signing-mode-label" style="margin-top:9px;font-weight:600;color:#50575e;">نوع قرارداد: فیزیکی</div>
                             </div>
                             <div class="mlr-form-row mlr-check-files-mode-box" style="padding:16px 18px; margin-bottom:18px; border:1px solid #dcdcde; border-radius:10px; background:#fff;">
                                 <div style="display:flex;align-items:center;justify-content:space-between;gap:18px;flex-wrap:wrap;">
                                     <div>
                                         <strong style="display:block;font-size:14px;margin-bottom:5px;">دریافت تصاویر چک در مرحله تکمیل مدارک</strong>
                                         <span class="description" style="margin:0;">روشن: تصاویر چک برای این طرح نمایش داده می‌شود و الزامی است — خاموش: فیلد تصاویر چک برای این طرح نمایش داده نمی‌شود.</span>
                                     </div>
                                     <label class="mlr-switch" style="margin:0;" title="فعال/غیرفعال کردن تصاویر چک برای این طرح">
                                         <input type="checkbox" name="completed_check_files_enabled" id="completed_check_files_enabled" value="1" checked>
                                         <span class="mlr-slider round"></span>
                                     </label>
                                 </div>
                                 <div id="mlr-check-files-mode-label" style="margin-top:9px;font-weight:600;color:#50575e;">تصاویر چک: فعال و الزامی</div>
                             </div>
                             <input type="hidden" name="plan_contract_template_key" id="plan_contract_template_key" value="">
                             <div class="mlr-form-row" style="padding:16px 18px; margin-bottom:18px; border:1px solid #c3e6cb; border-radius:10px; background:#f6fff8;">
                                 <div style="display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap;">
                                     <div style="max-width:760px;">
                                         <strong style="display:block;font-size:14px;margin-bottom:5px;">قالب مصوب «حامی بازنشستگان»</strong>
                                         <span class="description" style="margin:0;line-height:1.9;">این دکمه متن فایل «قرارداد نهایی» را با متغیرهای سامانه وارد می‌کند. هنگام استفاده، قاعده اولین سررسید نیز مطابق همان قرارداد روی «پایان ماه» تنظیم می‌شود.</span>
                                     </div>
                                     <button type="button" class="button button-secondary" id="mlr-load-hami-retirees-contract"><span class="dashicons dashicons-media-text"></span> بارگذاری قرارداد نهایی حامی بازنشستگان</button>
                                 </div>
                             </div>
                             <div class="mlr-form-row">
                                 <label for="contract_first_due_rule">قاعده اولین سررسید قرارداد</label>
                                 <select name="contract_first_due_rule" id="contract_first_due_rule" class="mlr-input">
                                     <option value="interval">فاصله اقساط پس از تاریخ قرارداد (رفتار قبلی)</option>
                                     <option value="jalali_month_end_half">روز ۱ تا ۱۵: پایان همان ماه / روز ۱۶ به بعد: پایان ماه بعد</option>
                                 </select>
                                 <p class="description">برای قالب حامی بازنشستگان گزینه دوم باید فعال باشد.</p>
                             </div>
                            <div class="mlr-grid-2">
                                <div class="mlr-form-row">
                                    <label for="upload_letterhead_button">سربرگ قرارداد</label>
                                    <div class="image-uploader-wrapper">
                                        <img src="" id="letterhead_preview" class="mlr-image-preview">
                                        <input type="hidden" name="plan_letterhead_url" id="plan_letterhead_url">
                                        <button type="button" class="button" id="upload_letterhead_button"><span class="dashicons dashicons-cloud-upload"></span> انتخاب تصویر</button>
                                        <button type="button" class="button button-link-delete" id="remove_letterhead_button" style="display: none;"><span class="dashicons dashicons-no-alt"></span> حذف</button>
                                    </div>
                                </div>
                                <div class="mlr-form-row">
                                     <label for="upload_footer_button">پابرگ قرارداد</label>
                                     <div class="image-uploader-wrapper">
                                        <img src="" id="footer_preview" class="mlr-image-preview">
                                        <input type="hidden" name="plan_footer_url" id="plan_footer_url">
                                        <button type="button" class="button" id="upload_footer_button"><span class="dashicons dashicons-cloud-upload"></span> انتخاب تصویر</button>
                                        <button type="button" class="button button-link-delete" id="remove_footer_button" style="display: none;"><span class="dashicons dashicons-no-alt"></span> حذف</button>
                                     </div>
                                </div>
                            </div>
                            <div class="mlr-form-row">
                                 <label for="plan_contract_text">متن قرارداد</label>
                                 <div class="description mlr-description" style="margin-bottom: 10px; line-height: 1.8; background: #f9f9f9; padding: 10px; border: 1px solid #e5e5e5; border-radius: 4px;">
                                     <strong style="display:block; margin-bottom:8px; color:#333;">متغیرهای هوشمند قابل استفاده در متن قرارداد:</strong>
                                     <?php 
                                     if (function_exists('mlr_get_installment_contract_placeholders')) {
                                         $placeholders = mlr_get_installment_contract_placeholders();
                                         foreach ($placeholders as $code => $desc) {
                                             echo '<span style="display:inline-block; margin-left:6px; margin-bottom:6px; background:#fff; padding:3px 8px; border:1px solid #ddd; border-radius:4px; font-size:12px;"><code>' . esc_html($code) . '</code> <span style="color:#666;">' . esc_html($desc) . '</span></span> ';
                                         }
                                     }
                                     ?>
                                 </div>
                                 <?php 
                                 wp_editor('', 'plan_contract_text', [
                                     'textarea_name' => 'plan_contract_text', 
                                     'editor_height' => 450, 
                                     'teeny' => false, 
                                     'media_buttons' => true,
                                     'tinymce' => [
                                         'toolbar1' => 'formatselect | bold italic underline strikethrough | alignleft aligncenter alignright alignjustify | bullist numlist | outdent indent | blockquote | link unlink | image media | forecolor backcolor | removeformat | undo redo | fullscreen',
                                     ],
                                     'quicktags' => true
                                 ]); 
                                 ?>
                            </div>
                             <div class="mlr-form-row">
                                <label>فایل‌های پیوست قابل دانلود (اختیاری)</label>
                                <div id="downloadable_files_container"></div>
                                <button type="button" class="button" id="add_downloadable_file_button"><span class="dashicons dashicons-paperclip"></span> افزودن فایل</button>
                             </div>
                        </div>

                        <div class="mlr-form-actions">
                            <button type="submit" name="mlr_submit_plan" class="button button-primary button-large" id="publish"><span class="dashicons dashicons-saved"></span> ذخیره طرح</button>
                            <button type="button" class="button button-secondary button-large" id="mlr-cancel-edit-btn" style="display: none;">انصراف</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <style>
        /* --- General Page Styles --- */
        .mlr-plans-page { background-color: #f6f7f7; }
        .mlr-page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; padding: 0 5px; }
        .mlr-page-header h1 { font-weight: 600; color: #1d2327; margin: 0; font-size: 23px; }
        .mlr-notice { margin: 15px 0 !important; }

        /* --- Layout --- */
        #mlr-plans-layout { display: flex; flex-direction: column; gap: 25px; }
        @media (min-width: 960px) {
            #mlr-plans-layout { flex-direction: row; align-items: flex-start; }
            .mlr-plans-list-column { flex: 1; min-width: 400px; }
            .mlr-plan-form-column { flex: 2; max-width: 70%; position: sticky; top: 40px; }
        }

        /* --- Cards --- */
        .mlr-card { background: #fff; border: 1px solid #dcdcde; border-radius: 8px; box-shadow: 0 1px 1px rgba(0,0,0,.04); overflow: hidden; margin-bottom: 25px; }
        .mlr-card-title { font-size: 16px; font-weight: 600; padding: 14px 20px; margin: 0; border-bottom: 1px solid #dcdcde; color: #1d2327; display: flex; align-items: center; gap: 8px; background-color: #fff; }
        .mlr-card-title .dashicons { color: #50575e; }
        .mlr-card-content { padding: 20px; }
        .mlr-card-content.no-padding { padding: 0; }

        /* --- Plan List Table --- */
        .mlr-compact-table th { font-size: 12px; padding: 10px 12px; }
        .mlr-compact-table td { padding: 10px 12px; font-size: 13px; vertical-align: middle; }
        .mlr-compact-table .mlr-plan-name { font-weight: 600; color: #007cba; }
        .mlr-status-active, .mlr-status-inactive { display: inline-flex; align-items: center; gap: 4px; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 500; }
        .mlr-status-active { background-color: #e6f7d5; color: #466907; }
        .mlr-status-inactive { background-color: #fdeaea; color: #a51e1e; }
        .mlr-status-active .dashicons, .mlr-status-inactive .dashicons { font-size: 14px; line-height: 1; height: auto; width: auto; }
        .mlr-plan-list-visibility { display: flex; flex-wrap: wrap; gap: 4px; }
        .mlr-visibility-list-badge, .mlr-visibility-list-empty { display: inline-block; padding: 2px 6px; border-radius: 4px; font-size: 10px; line-height: 1.6; }
        .mlr-visibility-list-badge { background: #f0f6fc; color: #0a4b78; border: 1px solid #c5d9ed; }
        .mlr-visibility-list-empty { background: #fcf0f1; color: #8a2424; border: 1px solid #f1c7cb; }
        .mlr-actions-col { width: 80px; text-align: left !important; padding-left: 15px !important; }
        .mlr-compact-table .button-small { min-height: 28px; height: 28px; padding: 0 6px; }
        .mlr-compact-table .button-small .dashicons { font-size: 16px; line-height: 26px; }
        .inactive-row { opacity: 0.7; }
        .inactive-row:hover { opacity: 1; }

        /* --- Form Styles --- */
        .mlr-plan-form-card { margin-bottom: 0; }
        .mlr-tabs { display: flex; border-bottom: 1px solid #dcdcde; margin: -20px -20px 20px -20px; padding: 0 20px; background: #f6f7f7; }
        .mlr-tab-button { background: none; border: none; padding: 12px 15px; cursor: pointer; font-size: 14px; color: #50575e; border-bottom: 3px solid transparent; margin-bottom: -1px; transition: color 0.2s, border-color 0.2s; }
        .mlr-tab-button:hover { color: #007cba; }
        .mlr-tab-button.active { color: #007cba; border-bottom-color: #007cba; font-weight: 600; }
        .mlr-tab-content { display: none; padding: 5px 0; }
        .mlr-tab-content.active { display: block; }
        .mlr-inner-title { font-size: 14px; font-weight: 600; margin: 15px 0 15px; padding-bottom: 8px; border-bottom: 1px solid #e0e0e0; color: #333; }
        .mlr-form-row { margin-bottom: 15px; }
        .mlr-form-row label { display: block; margin-bottom: 6px; font-weight: 500; font-size: 13px; color: #1d2327; }
        .mlr-form-row label input[type="checkbox"] { margin-left: 5px; vertical-align: middle; }
        .mlr-input, .mlr-select { width: 100%; padding: 8px 10px; border-radius: 4px; border: 1px solid #8c8f94; box-sizing: border-box; }
        .mlr-input:focus, .mlr-select:focus { border-color: #007cba; box-shadow: 0 0 0 1px #007cba; outline: 1px solid transparent; }
        .small-input { max-width: 80px; }
        .ltr { direction: ltr; text-align: left; }
        .mlr-grid-2 { display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; }
        .mlr-checkbox-group { border: 1px solid #e0e0e0; border-radius: 4px; margin-top: 15px; background: #fafafa; }
        .mlr-checkbox-group h3 { margin: 0; padding: 10px 15px; border-bottom: 1px solid #e0e0e0; background: #f9f9f9; }
        .mlr-checkbox-group .mlr-checkbox-label { display: block; padding: 8px 15px; border-bottom: 1px solid #f0f0f0; margin: 0; background: #fff; }
        .mlr-checkbox-group .mlr-checkbox-label:last-child { border-bottom: none; }
        .mlr-checkbox-label input { margin-left: 8px; }
        .mlr-checkbox-group .mlr-doc-config-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }
        .mlr-doc-config-main {
            display: inline-flex !important;
            align-items: center;
            gap: 8px;
            min-width: 180px;
            margin: 0 !important;
        }
        .mlr-doc-description-input {
            flex: 1;
            min-width: 240px;
            max-width: 100%;
        }
        .mlr-plan-conditions-row {
            margin-top: 16px;
            padding: 14px;
            border: 1px solid #dbeafe;
            border-radius: 8px;
            background: #eff6ff;
        }
        .mlr-plan-conditions-input {
            min-height: 110px;
            resize: vertical;
        }
        @media (max-width: 782px) {
            .mlr-checkbox-group .mlr-doc-config-row {
                align-items: stretch;
                flex-direction: column;
            }
            .mlr-doc-config-main,
            .mlr-doc-description-input {
                width: 100%;
                min-width: 0;
            }
        }
        .mlr-plan-active-row { padding: 14px 16px; border: 1px solid #c3c4c7; border-radius: 6px; background: #fff; }
        .mlr-plan-active-row .description { margin: 6px 24px 0 0; }
        .mlr-plan-visibility-section { margin-top: 22px; }
        .mlr-visibility-heading { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; margin-bottom: 12px; }
        .mlr-visibility-heading .mlr-inner-title { margin-top: 0; }
        .mlr-visibility-summary { flex: 0 0 auto; padding: 6px 10px; border-radius: 999px; background: #f0f6fc; color: #0a4b78; font-size: 12px; font-weight: 700; white-space: nowrap; }
        .mlr-visibility-summary.is-empty { background: #fcf0f1; color: #8a2424; }
        .mlr-visibility-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 12px; }
        .mlr-visibility-card { position: relative; display: flex !important; align-items: flex-start; gap: 10px; min-height: 170px; margin: 0 !important; padding: 16px; border: 1px solid #c3c4c7; border-radius: 8px; background: #fff; cursor: pointer; box-sizing: border-box; transition: border-color .15s, box-shadow .15s, background-color .15s; }
        .mlr-visibility-card:hover { border-color: #2271b1; box-shadow: 0 2px 8px rgba(34,113,177,.12); }
        .mlr-visibility-card.is-selected { border-color: #2271b1; background: #f6fbff; box-shadow: inset 0 0 0 1px #2271b1; }
        .mlr-visibility-card-check { padding-top: 2px; }
        .mlr-visibility-card-content { display: flex; flex-direction: column; align-items: flex-start; gap: 5px; min-width: 0; }
        .mlr-visibility-card-title { font-size: 14px; font-weight: 700; color: #1d2327; }
        .mlr-visibility-card-panel { display: inline-block; padding: 2px 7px; border-radius: 4px; background: #f0f0f1; color: #50575e; font-size: 11px; }
        .mlr-shortcode-code { direction: ltr; unicode-bidi: embed; max-width: 100%; padding: 4px 7px; background: #1d2327; color: #fff; border-radius: 4px; font-size: 11px; overflow-wrap: anywhere; }
        .mlr-visibility-card-description { color: #50575e; font-size: 12px; font-weight: 400; line-height: 1.8; }
        .mlr-visibility-note { margin: 14px 0 0 !important; }
        .mlr-not-visibility-badge { display: inline-block; margin-right: 6px; padding: 2px 7px; border-radius: 999px; background: #fcf0f1; color: #8a2424; font-size: 11px; vertical-align: middle; }
        @media (max-width: 782px) {
            .mlr-visibility-heading { flex-direction: column; }
            .mlr-visibility-grid { grid-template-columns: 1fr; }
        }
        .mlr-required { color: #d63638; font-weight: bold; }
        .mlr-description { font-size: 12px; color: #50575e; margin: 5px 0 0 0; }
        .mlr-description code { background: #e0e0e0; padding: 1px 4px; border-radius: 3px; font-size: 11px; }

        /* Image/File Uploaders */
        .image-uploader-wrapper { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
        .mlr-image-preview { max-width: 150px; height: auto; border: 1px solid #ddd; padding: 3px; background: #f9f9f9; display: block; margin-bottom: 8px; }
        .downloadable-file-item { display: flex; gap: 8px; margin-bottom: 8px; align-items: center; background: #f9f9f9; padding: 5px 10px; border: 1px solid #e0e0e0; border-radius: 4px; }
        .downloadable-file-item span { flex-grow: 1; text-align: left; direction: ltr; font-size: 12px; color: #333; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .downloadable-file-item .button-link-delete { color: #b32d2e !important; text-decoration: none; font-size: 12px; padding: 0 5px; }

        /* Form Actions */
        .mlr-form-actions { margin-top: 25px; padding-top: 15px; border-top: 1px solid #dcdcde; display: flex; justify-content: space-between; gap: 10px; }
        .mlr-form-actions .button .dashicons { vertical-align: middle; margin-top: -2px; margin-left: 4px; font-size: 18px; }

        /* Global plan ordering */
        .mlr-plan-order-help { padding: 12px 14px; background: #f0f6fc; border-bottom: 1px solid #dcdcde; line-height: 1.9; color: #1d2327; }
        .mlr-plan-order-help .dashicons { vertical-align: middle; margin-left: 5px; }
        .mlr-order-handle-col { width: 76px; text-align: center !important; }
        .mlr-order-handle-cell { text-align: center; white-space: nowrap; }
        .mlr-plan-drag-handle { cursor: grab; color: #646970; vertical-align: middle; }
        .mlr-plan-drag-handle:active { cursor: grabbing; }
        .mlr-order-position { display: inline-block; min-width: 20px; margin-right: 5px; color: #646970; font-weight: 600; }
        .mlr-sortable-placeholder td { height: 48px; background: #f0f6fc !important; border: 2px dashed #72aee6 !important; }
        .mlr-plan-order-actions { display: flex; align-items: center; gap: 12px; padding: 12px 14px; border-top: 1px solid #dcdcde; background: #fff; }
        .mlr-plan-order-actions .dashicons { vertical-align: middle; margin: 2px 0 0 4px; }

        /* Calculator */
        .mlr-live-calculator-card .mlr-card-content { display: flex; flex-direction: column; gap: 15px; }
        .mlr-calculator-result { margin-top: 10px; border: 1px solid #dcdcde; padding: 15px; background: #fdfdfd; border-radius: 4px; font-size: 13px; }
        .mlr-calculator-result h4 { margin: 0 0 10px; font-size: 14px; }
        .mlr-calculator-result table { width: 100%; border-collapse: collapse; }
        .mlr-calculator-result td { padding: 5px 0; border-bottom: 1px dotted #e0e0e0; }
        .mlr-calculator-result tr:last-child td { border-bottom: none; }
        .mlr-calculator-result strong { color: #333; }
    </style>

    <script>
        jQuery(document).ready(function($) {
            const allPlansData = <?php echo json_encode($js_plans); ?>;
            const hamiRetireesFinalContractTemplate = <?php echo wp_json_encode(function_exists('mlr_get_hami_retirees_contract_template') ? mlr_get_hami_retirees_contract_template() : ''); ?>;

            function mlrRefreshPlanOrderPositions() {
                $('.mlr-sortable-plans-table tbody .mlr-plan-row').each(function(index) {
                    $(this).find('.mlr-order-position').text(index + 1);
                });
            }
            mlrRefreshPlanOrderPositions();
            if ($.fn.sortable) {
                $('.mlr-sortable-plans-table tbody').sortable({
                    items: '> tr.mlr-plan-row',
                    handle: '.mlr-plan-drag-handle',
                    axis: 'y',
                    placeholder: 'mlr-sortable-placeholder',
                    forcePlaceholderSize: true,
                    update: mlrRefreshPlanOrderPositions
                });
            }

             // --- START: Number Formatting ---
             function formatNumber(num) { /* ... same as before ... */
                if (!num || isNaN(num)) return '';
                // Use 'fa-IR' for Persian formatting
                try {
                    return new Intl.NumberFormat('fa-IR').format(num);
                } catch (e) {
                    // Fallback for environments without Intl support for fa-IR
                    return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
                }
             }
             function parseFormattedNumber(str) { /* ... same as before ... */
                 if (!str) return 0;
                 const persianNumbersMap = { '۰': '0', '۱': '1', '۲': '2', '۳': '3', '۴': '4', '۵': '5', '۶': '6', '۷': '7', '۸': '8', '۹': '9' };
                 let englishStr = String(str);
                 englishStr = englishStr.replace(/[۰-۹]/g, (match) => persianNumbersMap[match]);
                 englishStr = englishStr.replace(/[٬,.]|\s/g, ''); // Remove comma, dot, space
                 // Use parseFloat for potential decimal values in rates/percentages
                 const parsed = parseFloat(englishStr);
                 return isNaN(parsed) ? 0 : parsed;
             }
             // Apply formatting on input/blur
             $(document).on('input', '.mlr-formatted-number', function(e) {
                 if (['ArrowLeft', 'ArrowRight', 'Backspace', 'Delete', 'Tab'].includes(e.key)) return;
                 let rawValue = parseFormattedNumber($(this).val());
                 let formattedValue = formatNumber(rawValue);
                 // Prevent cursor jump
                 let currentCursorPos = this.selectionStart;
                 let oldValueLength = $(this).val().length;
                 $(this).val(formattedValue);
                 let newValueLength = formattedValue.length;
                 this.selectionStart = this.selectionEnd = currentCursorPos + (newValueLength - oldValueLength);
             });
             $(document).on('blur', '.mlr-formatted-number', function() {
                 let rawValue = parseFormattedNumber($(this).val());
                 $(this).val(formatNumber(rawValue));
             });
             // Remove formatting on form submit
             $('#mlr-plan-form').on('submit', function() {
                 $('.mlr-formatted-number').each(function() {
                     $(this).val(parseFormattedNumber($(this).val()));
                 });
             });
            // --- END: Number Formatting ---

            // --- Media Uploader ---
            function setupMediaUploader(buttonId, previewId, inputId, removeBtnId) {
                let frame;
                $(document).on('click', buttonId, function(e) { e.preventDefault(); /* ... */
                    if (frame) { frame.open(); return; }
                    frame = wp.media({ title: 'انتخاب تصویر', button: { text: 'استفاده' }, multiple: false });
                    frame.on('select', function() {
                        const att = frame.state().get('selection').first().toJSON();
                        $(inputId).val(att.url);
                        $(previewId).attr('src', att.url).css('display', 'block'); // Use CSS display
                        $(removeBtnId).css('display', 'inline-block');
                    });
                    frame.open();
                 });
                $(document).on('click', removeBtnId, function(e) { e.preventDefault(); $(inputId).val(''); $(previewId).attr('src', '').hide(); $(this).hide(); });
            }
            setupMediaUploader('#upload_letterhead_button', '#letterhead_preview', '#plan_letterhead_url', '#remove_letterhead_button');
            setupMediaUploader('#upload_footer_button', '#footer_preview', '#plan_footer_url', '#remove_footer_button');

            // --- Downloadable Files ---
            let downloadableFilesFrame;
            $('#add_downloadable_file_button').on('click', function(e) { e.preventDefault(); /* ... */
                if (downloadableFilesFrame) { downloadableFilesFrame.open(); return; }
                downloadableFilesFrame = wp.media({ title: 'انتخاب فایل‌ها', button: { text: 'افزودن' }, multiple: true });
                downloadableFilesFrame.on('select', () => downloadableFilesFrame.state().get('selection').toJSON().forEach(att => addDownloadableFileRow(att.url, att.filename)));
                downloadableFilesFrame.open();
             });
            function addDownloadableFileRow(url, filename) { /* ... */
                const container = $('#downloadable_files_container');
                const newRow = $('<div class="downloadable-file-item"></div>')
                    .append('<input type="hidden" name="plan_downloadable_files[]" value="' + esc_attr(url) + '">') // Escape URL
                    .append('<span>' + esc_html(filename) + '</span>') // Escape filename
                    .append('<button type="button" class="button button-link-delete remove-file-btn"><span class="dashicons dashicons-no-alt"></span></button>');
                container.append(newRow);
             }
             // Simplified escaping functions for JS
             function esc_attr(str) { return $('<div>').text(str).html(); }
             function esc_html(str) { return $('<div>').text(str).html(); }

            $(document).on('click', '.remove-file-btn', function() { $(this).closest('.downloadable-file-item').remove(); });

            // --- Tabs ---
            $('.mlr-tab-button').on('click', function() {
                const tabId = $(this).data('tab');
                $('.mlr-tab-button').removeClass('active');
                $(this).addClass('active');
                $('.mlr-tab-content').removeClass('active').hide(); // Hide all
                $('#tab-' + tabId).addClass('active').show(); // Show selected
            });
            // Trigger click on the first tab initially if needed
             $('.mlr-tab-button[data-tab="general"]').trigger('click');
             updateVisibilityCards();


            // --- Plan Type Switching ---
            function togglePlanFields() {
                var planType = $('#plan_type').val();
                $('.plan-settings-fields').hide(); // Hide all specific setting divs first
                $('#' + planType + '_fields').show(); // Show the relevant one
            }
            $('#plan_type').on('change', togglePlanFields).trigger('change');

             function updateVisibilityCards() {
                 const selectedCount = $('.mlr-plan-visibility-input:checked').length;
                 const totalCount = $('.mlr-plan-visibility-input').length;
                 $('.mlr-visibility-card').each(function() {
                     $(this).toggleClass('is-selected', $(this).find('.mlr-plan-visibility-input').is(':checked'));
                 });
                 $('#mlr-visibility-summary')
                     .toggleClass('is-empty', selectedCount === 0)
                     .text(selectedCount === 0 ? 'در هیچ مسیری نمایش داده نمی‌شود' : selectedCount + ' از ' + totalCount + ' محل انتخاب شده');
             }
             $(document).on('change', '.mlr-plan-visibility-input', updateVisibilityCards);
             $(document).on('change', '#contract_signing_digital', function() {
                 $('#mlr-contract-signing-mode-label').text($(this).is(':checked') ? 'نوع قرارداد: دیجیتال با کد تأیید' : 'نوع قرارداد: فیزیکی');
             });
             $(document).on('change', '#completed_check_files_enabled', function() {
                 $('#mlr-check-files-mode-label').text($(this).is(':checked') ? 'تصاویر چک: فعال و الزامی' : 'تصاویر چک: غیرفعال');
             });
             $(document).on('click', '#mlr-load-hami-retirees-contract', function(e) {
                 e.preventDefault();
                 if (!hamiRetireesFinalContractTemplate) {
                     window.alert('فایل قالب قرارداد حامی بازنشستگان در بسته افزونه یافت نشد.');
                     return;
                 }
                 const currentValue = (typeof tinymce !== 'undefined' && tinymce.get('plan_contract_text'))
                     ? tinymce.get('plan_contract_text').getContent({format: 'raw'})
                     : $('#plan_contract_text').val();
                 if (String(currentValue || '').trim() !== '' && !window.confirm('متن فعلی قرارداد با نسخه نهایی حامی بازنشستگان جایگزین شود؟')) {
                     return;
                 }
                 if (typeof tinymce !== 'undefined' && tinymce.get('plan_contract_text')) {
                     tinymce.get('plan_contract_text').setContent(hamiRetireesFinalContractTemplate);
                 } else {
                     $('#plan_contract_text').val(hamiRetireesFinalContractTemplate);
                 }
                 $('#plan_contract_template_key').val('hami-retirees-final-1405-v2');
                 $('#contract_first_due_rule').val('jalali_month_end_half');
                 $('#contract_signing_digital').prop('checked', true).trigger('change');
                 window.alert('قالب اصلاح‌شده حامی بازنشستگان بارگذاری شد. متن قرارداد، چیدمان چاپ و قاعده اولین سررسید تنظیم شد؛ سپس طرح را ذخیره کنید.');
             });

             // --- Reset Form Function ---
             function resetPlanForm() {
                 $('#mlr-plan-form')[0].reset();
                 $('#plan_id').val('0');
                 $('#plan-form-title').text('افزودن طرح جدید');
                 $('#publish').html('<span class="dashicons dashicons-saved"></span> ذخیره طرح');
                 $('#mlr-cancel-edit-btn').hide();
                 $('#plan_type').trigger('change'); // Ensure correct fields are shown
                 $('input[name="is_active"]').prop('checked', true); // Default to active
                 $('.mlr-plan-visibility-input').prop('checked', false);
                 $('.mlr-plan-visibility-input[value="mlr_loan_request_form"], .mlr-plan-visibility-input[value="mlr_loan_calculator"]').prop('checked', true);
                 updateVisibilityCards();
                 // Clear images and files
                 $('#letterhead_preview, #footer_preview').attr('src', '').hide();
                 $('#remove_letterhead_button, #remove_footer_button').hide();
                 $('#plan_letterhead_url, #plan_footer_url').val('');
                 $('#downloadable_files_container').empty();
                 $('#contract_signing_digital').prop('checked', false).trigger('change');
                 $('#completed_check_files_enabled').prop('checked', true).trigger('change');
                 $('#plan_contract_template_key').val('');
                 $('#contract_first_due_rule').val('interval');

                 // Clear TinyMCE editor
                if (typeof tinymce !== 'undefined' && tinymce.get('plan_contract_text')) {
                    tinymce.get('plan_contract_text').setContent('');
                } else {
                    $('#plan_contract_text').val('');
                }

                 // Reset checkboxes for docs and display fields
                 $('.required-documents-container input[type="checkbox"]').prop('checked', false);
                 $('.mlr-occupation-doc-checkbox').prop('checked', false);
                 $('#occupation_documents_enabled').prop('checked', false);
                 $('#mlr-occupation-documents-panel').hide();
                 $('.display-fields-container input[type="checkbox"]').prop('checked', false);
                 $('#plan_conditions').val('');
                 $('.mlr-doc-description-input').val('');
                 
                 // Reset formatted numbers
                 $('.mlr-formatted-number').each(function(){ $(this).val(formatNumber(parseFormattedNumber($(this).attr('value') || '0'))); });

                 // Go to first tab
                 $('.mlr-tab-button[data-tab="general"]').trigger('click');
                 $('html, body').animate({ scrollTop: $('#mlr-plan-form').offset().top - 50 }, 'fast'); // Scroll to form
             }

            // --- Edit Plan Population ---
            $('.edit-plan').on('click', function(e) {
                e.preventDefault();
                resetPlanForm(); // Reset form before populating
                const planId = $(this).data('id');
                const planData = allPlansData.find(p => p.id == planId);
                if (!planData) return;

                const settings = planData.settings || {};
                const displayFields = planData.display_fields || [];
                const requiredDocuments = planData.required_documents || [];

                $('#plan-form-title').text('ویرایش طرح: ' + planData.name);
                $('#publish').html('<span class="dashicons dashicons-update"></span> به‌روزرسانی طرح'); // Update button text
                $('#mlr-cancel-edit-btn').show();
                $('#plan_id').val(planData.id);
                $('#plan_name').val(planData.name);
                $('#plan_type').val(planData.type).trigger('change');
                $('input[name="is_active"]').prop('checked', planData.is_active == 1);
                const resolvedVisibilityContexts = Array.isArray(planData.resolved_visibility_contexts) ? planData.resolved_visibility_contexts : [];
                $('.mlr-plan-visibility-input').prop('checked', false);
                resolvedVisibilityContexts.forEach(function(contextKey) {
                    $('.mlr-plan-visibility-input[value="' + contextKey + '"]').prop('checked', true);
                });
                updateVisibilityCards();
                const prefix = planData.type;
                 // Loop through all *form inputs* within the correct type section and populate from settings
                 $('#' + prefix + '_fields').find('input, select, textarea').each(function(){
                     const input = $(this);
                     const inputName = input.attr('name');
                     if (!inputName) return; // Skip if no name attribute

                     // Extract the setting key from the input name (remove prefix and underscore)
                     let settingKey = inputName.replace(prefix + '_', '');

                     if (settings.hasOwnProperty(settingKey)) {
                         const value = settings[settingKey];
                         if (input.is(':checkbox')) {
                             input.prop('checked', value == 1);
                         } else if (input.hasClass('mlr-formatted-number')) {
                             input.val(formatNumber(value)).trigger('input'); // Format number fields
                         } else {
                             input.val(value);
                         }
                     }
                 });


                // Populate contract/file fields (outside the type-specific divs)
                $('#plan_letterhead_url').val(settings.plan_letterhead_url || '');
                $('#letterhead_preview').attr('src', settings.plan_letterhead_url || '').toggle(!!settings.plan_letterhead_url);
                $('#remove_letterhead_button').toggle(!!settings.plan_letterhead_url);

                $('#plan_footer_url').val(settings.plan_footer_url || '');
                $('#footer_preview').attr('src', settings.plan_footer_url || '').toggle(!!settings.plan_footer_url);
                $('#remove_footer_button').toggle(!!settings.plan_footer_url);
                $('#contract_signing_digital').prop('checked', String(settings.contract_signing_mode || 'physical') === 'digital').trigger('change');
                $('#completed_check_files_enabled').prop('checked', !settings.hasOwnProperty('completed_check_files_enabled') || Number(settings.completed_check_files_enabled) === 1).trigger('change');
                $('#plan_contract_template_key').val(settings.plan_contract_template_key || '');
                $('#contract_first_due_rule').val(settings.contract_first_due_rule || 'interval');

                if (typeof tinymce !== 'undefined' && tinymce.get('plan_contract_text')) {
                    tinymce.get('plan_contract_text').setContent(settings.plan_contract_text || '');
                } else {
                    $('#plan_contract_text').val(settings.plan_contract_text || '');
                }

                $('#downloadable_files_container').empty();
                if (Array.isArray(settings.plan_downloadable_files)) {
                    settings.plan_downloadable_files.forEach(fileUrl => addDownloadableFileRow(fileUrl, fileUrl.split('/').pop()));
                }

                // Populate checkboxes
                $('.display-fields-container input[type="checkbox"]').prop('checked', false);
                if (Array.isArray(displayFields)) { displayFields.forEach(key => $('.mlr-display-fields-scope[data-context="default"] input[value="' + key + '"]').prop('checked', true)); }
                const displayFieldsByContext = planData.display_fields_context || {};
                if (displayFieldsByContext && typeof displayFieldsByContext === 'object') {
                    Object.keys(displayFieldsByContext).forEach(function(ctx){
                        const fields = displayFieldsByContext[ctx];
                        if (Array.isArray(fields)) {
                            fields.forEach(function(key){
                                $('.mlr-display-fields-scope[data-context="' + ctx + '"] input[value="' + key + '"]').prop('checked', true);
                            });
                        }
                    });
                }
                $('.required-documents-container input[type="checkbox"]').prop('checked', false);
                if (Array.isArray(requiredDocuments)) { requiredDocuments.forEach(key => $('.required-documents-container input[value="' + key + '"]').prop('checked', true)); }
                const occupationDocsEnabled = Number(settings.occupation_documents_enabled || 0) === 1;
                $('#occupation_documents_enabled').prop('checked', occupationDocsEnabled);
                $('.mlr-occupation-doc-checkbox').prop('checked', false);
                const occupationDocumentMap = settings.required_documents_by_occupation || {};
                if (occupationDocumentMap && typeof occupationDocumentMap === 'object') {
                    Object.keys(occupationDocumentMap).forEach(function(occupationKey) {
                        const docs = occupationDocumentMap[occupationKey];
                        if (!Array.isArray(docs)) return;
                        docs.forEach(function(docKey) {
                            $('.mlr-occupation-documents-card[data-occupation="' + occupationKey + '"] .mlr-occupation-doc-checkbox[value="' + docKey + '"]').prop('checked', true);
                        });
                    });
                }
                $('#mlr-occupation-documents-panel').toggle(occupationDocsEnabled);
                $('#plan_conditions').val(settings.plan_conditions || '');
                $('.mlr-doc-description-input').val('');
                const documentDescriptions = settings.required_document_descriptions || {};
                if (documentDescriptions && typeof documentDescriptions === 'object') {
                    Object.keys(documentDescriptions).forEach(function(key) {
                        $('.mlr-doc-description-input').filter(function() {
                            return String($(this).data('doc-key')) === String(key);
                        }).val(documentDescriptions[key] || '');
                    });
                }

                $('html, body').animate({ scrollTop: $('#mlr-plan-form').offset().top - 50 }, 'fast'); // Scroll to form
            });
            
             $('#occupation_documents_enabled').on('change', function() {
                 $('#mlr-occupation-documents-panel').stop(true, true).slideToggle($(this).is(':checked'));
             });

             $('#mlr-copy-default-docs-to-occupations').on('click', function() {
                 const selectedDefaults = [];
                 $('.required-documents-container input[name="required_documents[]"]:checked').each(function() {
                     selectedDefaults.push(String($(this).val()));
                 });
                 if (!selectedDefaults.length) {
                     alert('ابتدا حداقل یک مدرک پیش‌فرض انتخاب کنید.');
                     return;
                 }
                 $('.mlr-occupation-doc-checkbox').prop('checked', false);
                 selectedDefaults.forEach(function(docKey) {
                     $('.mlr-occupation-doc-checkbox[value="' + docKey + '"]').prop('checked', true);
                 });
             });

             // --- Add New / Cancel Edit Buttons ---
             $('#mlr-add-new-plan-btn').on('click', resetPlanForm);
             $('#mlr-cancel-edit-btn').on('click', resetPlanForm);


            // --- Duplicate / Delete Confirmation ---
            $('.mlr-duplicate-plan-link').on('click', function(e) {
                if (!window.confirm('یک نسخه کپیِ غیرفعال از این طرح ساخته شود؟ تمام تنظیمات طرح کپی می‌شوند.')) {
                    e.preventDefault();
                }
            });
            $('.mlr-delete-link').on('click', function(e) {
                if (!window.confirm('آیا برای حذف این طرح مطمئن هستید؟ طرحی که در پرونده‌ای استفاده شده باشد حذف نخواهد شد.')) {
                    e.preventDefault();
                }
            });

            const duplicatedPlanId = <?php echo isset($_GET['duplicated_plan_id']) ? absint($_GET['duplicated_plan_id']) : 0; ?>;
            if (duplicatedPlanId > 0) {
                const duplicatedEditButton = $('.edit-plan[data-id="' + duplicatedPlanId + '"]').first();
                if (duplicatedEditButton.length) {
                    duplicatedEditButton.trigger('click');
                }
            }

            // One-shot action notices must not linger in the browser URL. Keeping them there can
            // make later edits look related to an old duplicate/delete operation. The server has
            // already consumed them above, so removing them from history is safe and data-neutral.
            try {
                const cleanUrl = new URL(window.location.href);
                let changed = false;
                ['mlr_duplicate_success', 'duplicated_plan_id', 'mlr_plan_deleted', 'mlr_plan_action_error'].forEach(function(key) {
                    if (cleanUrl.searchParams.has(key)) {
                        cleanUrl.searchParams.delete(key);
                        changed = true;
                    }
                });
                if (changed && window.history && window.history.replaceState) {
                    window.history.replaceState({}, document.title, cleanUrl.pathname + (cleanUrl.search ? cleanUrl.search : '') + cleanUrl.hash);
                }
            } catch (e) {
                // URL cleanup is cosmetic; never interfere with plan management if unavailable.
            }

            // --- Live Calculator ---
            $('#calc_plan_select').on('change', function() {
                var selectedPlanId = $(this).val();
                 var installmentsSelect = $('#calc_installments');
                 installmentsSelect.html('');
                 $('#calc_interval_wrapper').hide();
                if (!selectedPlanId) return;

                var planData = allPlansData.find(p => p.id == selectedPlanId);
                 if (planData && planData.settings && planData.settings.active_installments) {
                    const installmentsOptions = String(planData.settings.active_installments).split(','); // Ensure it's a string
                    installmentsOptions.forEach(inst => installmentsSelect.append(`<option value="${inst.trim()}">${inst.trim()} ماه</option>`));
                 }
                if (planData.type === 'leasing') { $('#calc_interval_wrapper').show(); $('#calc_amount_label').text('مبلغ خرید (ریال)'); }
                else { $('#calc_interval_wrapper').hide(); $('#calc_amount_label').text('مبلغ تسهیلات (ریال):'); }
                $('#calc_amount').val(formatNumber((planData.settings && planData.settings.min_amount) || 0));
                $('#calculator_result').html('نتیجه اینجا نمایش داده می‌شود.');
            }).trigger('change');

            $('#run_calculator').on('click', function() {
                var selectedPlanId = $('#calc_plan_select').val();
                if (!selectedPlanId) { $('#calculator_result').html('<p style="color:red;">لطفاً یک طرح را انتخاب کنید.</p>'); return; }

                var data = {
                    action: 'mlr_admin_calculate_loan',
                    nonce: '<?php echo wp_create_nonce("mlr_ajax_nonce_action"); ?>',
                    plan_id: selectedPlanId,
                    amount: parseFormattedNumber($('#calc_amount').val()),
                    installments: $('#calc_installments').val(),
                    installment_interval: $('#calc_interval').val(),
                };
                $('#calculator_result').html('در حال محاسبه...');
                $.post(ajaxurl, data, function(res) {
                    $('#calculator_result').html(res.success ? res.data.html : '<p style="color:red;">' + (res.data.message || 'خطا') + '</p>');
                }).fail(() => $('#calculator_result').html('<p style="color:red;">خطای ارتباط با سرور.</p>'));
            });

             // Initialize editor if conditions are met (avoids JS errors if editor isn't loaded)
            if (typeof tinymce !== 'undefined' && $('#plan_contract_text').length > 0 && !tinymce.get('plan_contract_text')) {
                // You might need more specific tinymce init options here if required
                 wp.editor.initialize('plan_contract_text', { tinymce: true, quicktags: true });
            }


        });
    </script>
    

    
<?php
}
// --- END: Function mlr_installment_plans_page() ---
?>
