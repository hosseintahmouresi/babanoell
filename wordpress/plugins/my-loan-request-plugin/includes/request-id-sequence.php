<?php
/**
 * Configurable loan request identifier sequence.
 *
 * Keeps the existing loan_requests.id primary key as the canonical case ID, but
 * allows administrators to choose the positive increment used for NEW requests.
 * Existing request IDs are never rewritten.
 */
if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('mlr_get_loan_request_id_step')) {
    function mlr_get_loan_request_id_step() {
        $step = absint(get_option('mlr_loan_request_id_step', 1));
        if ($step < 1) {
            $step = 1;
        }
        // Keep the arithmetic safely inside normal operational ranges while still
        // allowing very large custom gaps when needed.
        return min($step, 1000000000);
    }
}

if (!function_exists('mlr_sanitize_loan_request_id_step')) {
    function mlr_sanitize_loan_request_id_step($value) {
        $value = function_exists('mlr_normalize_digits') ? mlr_normalize_digits($value, true) : (string) $value;
        $step = absint($value);
        if ($step < 1) {
            $step = 1;
        }
        return min($step, 1000000000);
    }
}

if (!function_exists('mlr_get_loan_request_id_sequence_state')) {
    function mlr_get_loan_request_id_sequence_state() {
        global $wpdb;
        $table = $wpdb->prefix . 'loan_requests';
        $max_id = (int) $wpdb->get_var("SELECT MAX(id) FROM `{$table}`");
        $status = $wpdb->get_row($wpdb->prepare('SHOW TABLE STATUS WHERE Name = %s', $table));
        $auto_increment = $status && isset($status->Auto_increment) ? (int) $status->Auto_increment : 0;
        $last_allocated = (int) get_option('mlr_loan_request_id_last_allocated', 0);
        $base = max($max_id, $last_allocated, max(0, $auto_increment - 1));
        $step = mlr_get_loan_request_id_step();
        $next_id = $base > 0 ? $base + $step : 1;

        return [
            'step' => $step,
            'max_id' => $max_id,
            'auto_increment' => $auto_increment,
            'last_allocated' => $last_allocated,
            'base' => $base,
            'next_id' => $next_id,
        ];
    }
}

if (!function_exists('mlr_insert_loan_request_with_sequence')) {
    /**
     * Insert a loan request and return the canonical request ID (int) or WP_Error.
     *
     * For step=1 this is the normal WordPress/MySQL AUTO_INCREMENT path.
     * For step>1 we allocate an explicit primary key under a DB advisory lock,
     * so concurrent frontend submissions cannot accidentally receive the same ID.
     */
    function mlr_insert_loan_request_with_sequence(array $data, $formats = null) {
        global $wpdb;
        $table = $wpdb->prefix . 'loan_requests';
        $step = mlr_get_loan_request_id_step();

        unset($data['id']);

        if ($step <= 1) {
            $result = $formats === null
                ? $wpdb->insert($table, $data)
                : $wpdb->insert($table, $data, $formats);
            if ($result === false) {
                return new WP_Error('mlr_request_insert_failed', 'ذخیره درخواست در پایگاه داده ناموفق بود.');
            }
            $request_id = (int) $wpdb->insert_id;
            if ($request_id > 0) {
                update_option('mlr_loan_request_id_last_allocated', $request_id, false);
            }
            return $request_id;
        }

        $lock_name = 'mlr_req_id_' . substr(md5(DB_NAME . '|' . $wpdb->prefix), 0, 32);
        $lock_acquired = (int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, %d)', $lock_name, 10));
        if ($lock_acquired !== 1) {
            if (function_exists('mlr_log')) {
                mlr_log('Loan request ID allocation lock timeout.', ['step' => $step]);
            }
            return new WP_Error('mlr_request_id_lock_timeout', 'در حال حاضر ثبت همزمان دیگری در جریان است. لطفاً چند ثانیه دیگر دوباره تلاش کنید.');
        }

        try {
            for ($attempt = 1; $attempt <= 3; $attempt++) {
                $max_id = (int) $wpdb->get_var("SELECT MAX(id) FROM `{$table}`");
                $status = $wpdb->get_row($wpdb->prepare('SHOW TABLE STATUS WHERE Name = %s', $table));
                $auto_increment = $status && isset($status->Auto_increment) ? (int) $status->Auto_increment : 0;
                $last_allocated = (int) get_option('mlr_loan_request_id_last_allocated', 0);
                $base = max($max_id, $last_allocated, max(0, $auto_increment - 1));
                $next_id = $base > 0 ? $base + $step : 1;

                if ($next_id <= 0 || $next_id > PHP_INT_MAX) {
                    return new WP_Error('mlr_request_id_overflow', 'محدوده شناسه پرونده به سقف مجاز رسیده است.');
                }

                $insert_data = array_merge(['id' => $next_id], $data);
                $insert_formats = null;
                if (is_array($formats)) {
                    $insert_formats = array_merge(['%d'], array_values($formats));
                }

                $result = $insert_formats === null
                    ? $wpdb->insert($table, $insert_data)
                    : $wpdb->insert($table, $insert_data, $insert_formats);

                if ($result !== false) {
                    update_option('mlr_loan_request_id_last_allocated', $next_id, false);
                    return (int) $next_id;
                }

                $is_duplicate = stripos((string) $wpdb->last_error, 'Duplicate entry') !== false;
                if (!$is_duplicate || $attempt >= 3) {
                    if (function_exists('mlr_log')) {
                        mlr_log('Loan request sequenced insert failed.', [
                            'step' => $step,
                            'attempt' => $attempt,
                            'db_error' => $wpdb->last_error,
                        ]);
                    }
                    return new WP_Error('mlr_request_insert_failed', 'ذخیره درخواست در پایگاه داده ناموفق بود.');
                }
            }
        } finally {
            $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock_name));
        }

        return new WP_Error('mlr_request_insert_failed', 'ذخیره درخواست در پایگاه داده ناموفق بود.');
    }
}

add_action('admin_init', function() {
    register_setting('mlr_request_id_sequence_group', 'mlr_loan_request_id_step', [
        'type' => 'integer',
        'sanitize_callback' => 'mlr_sanitize_loan_request_id_step',
        'default' => 1,
    ]);
});

add_action('admin_menu', function() {
    add_submenu_page(
        'mlr-loan-requests',
        'تنظیم شناسه پرونده',
        'تنظیم شناسه پرونده',
        'manage_options',
        'mlr-request-id-sequence',
        'mlr_request_id_sequence_admin_page'
    );
}, 30);

if (!function_exists('mlr_request_id_sequence_admin_page')) {
    function mlr_request_id_sequence_admin_page() {
        if (!current_user_can('manage_options')) {
            wp_die('دسترسی غیرمجاز.');
        }
        $state = mlr_get_loan_request_id_sequence_state();
        ?>
        <div class="wrap" dir="rtl">
            <h1>تنظیم ترتیب شناسه پرونده</h1>
            <p style="max-width:900px;line-height:2">این تنظیم فقط روی <strong>درخواست‌های تسهیلات جدید</strong> اعمال می‌شود و شناسه پرونده‌های قبلی را تغییر نمی‌دهد. مقدار ۱ همان رفتار عادی قبلی است.</p>

            <div style="max-width:900px;background:#fff;border:1px solid #dcdcde;border-radius:10px;padding:18px 20px;margin:18px 0">
                <form method="post" action="options.php">
                    <?php settings_fields('mlr_request_id_sequence_group'); ?>
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><label for="mlr_loan_request_id_step">گام افزایش شناسه</label></th>
                            <td>
                                <input type="number" min="1" max="1000000000" step="1" class="regular-text" id="mlr_loan_request_id_step" name="mlr_loan_request_id_step" value="<?php echo esc_attr($state['step']); ?>">
                                <p class="description">مثال: ۱ = تک‌تک، ۴ = چهار تا چهار تا، ۱۰ = ده تا ده تا.</p>
                            </td>
                        </tr>
                    </table>
                    <?php submit_button('ذخیره تنظیمات شناسه'); ?>
                </form>
            </div>

            <div style="max-width:900px;background:#f6f7f7;border-right:4px solid #2271b1;border-radius:8px;padding:14px 18px;line-height:2">
                <strong>وضعیت فعلی:</strong><br>
                آخرین شناسه موجود: <code><?php echo esc_html($state['max_id']); ?></code><br>
                گام فعلی: <code><?php echo esc_html($state['step']); ?></code><br>
                شناسه تقریبی درخواست بعدی با وضعیت فعلی: <code><?php echo esc_html($state['next_id']); ?></code>
                <p style="margin-bottom:0;color:#646970">در ثبت همزمان چند درخواست، تخصیص شناسه با قفل پایگاه داده انجام می‌شود تا شناسه تکراری ایجاد نشود.</p>
            </div>
        </div>
        <?php
    }
}
