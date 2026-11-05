<?php
if (!defined('ABSPATH')) {
    exit;
}

add_action('admin_menu', 'mlr_greenpay_add_admin_menu', 30);
function mlr_greenpay_add_admin_menu() {
    add_submenu_page(
        'mlr-loan-requests',
        'مدیریت GreenPay',
        'مدیریت GreenPay',
        'manage_options',
        'mlr-greenpay-transactions',
        'mlr_greenpay_transactions_page'
    );
}

function mlr_greenpay_status_label($status) {
    $map = [
        'not_started'    => 'کسر نشده',
        'queued'         => 'کسر نشده / در صف',
        'processing'     => 'در حال کسر',
        'awaiting_otp'   => 'کسر نشده / در انتظار OTP',
        'success'        => 'کسر شده',
        'failed'         => 'کسر نشده / خطا',
        'unknown'        => 'نامشخص / نیازمند استعلام',
        'cancelled'      => 'کسر نشده / لغو شده',
        'not_applicable' => 'مشمول GreenPay نیست',
    ];
    return $map[$status] ?? ($status ?: 'کسر نشده');
}

function mlr_greenpay_status_badge_style($status, $eligible) {
    if (!$eligible) {
        return 'background:#f0f0f1;color:#50575e;border:1px solid #c3c4c7;';
    }
    $map = [
        'success'      => 'background:#edfaef;color:#116329;border:1px solid #8fd19e;',
        'processing'   => 'background:#eef6ff;color:#135e96;border:1px solid #9ec5e5;',
        'queued'       => 'background:#fff8e5;color:#8a5a00;border:1px solid #e7c66b;',
        'awaiting_otp' => 'background:#fff8e5;color:#8a5a00;border:1px solid #e7c66b;',
        'failed'       => 'background:#fcf0f1;color:#b32d2e;border:1px solid #e5a5a8;',
        'unknown'      => 'background:#fcf0f1;color:#8a2424;border:1px solid #dba0a0;',
    ];
    return $map[$status] ?? 'background:#f6f7f7;color:#3c434a;border:1px solid #c3c4c7;';
}

function mlr_greenpay_set_admin_notice($type, $message) {
    set_transient('mlr_greenpay_notice_' . get_current_user_id(), [
        'type' => $type,
        'message' => $message,
    ], MINUTE_IN_SECONDS);
}

function mlr_greenpay_get_admin_notice() {
    $key = 'mlr_greenpay_notice_' . get_current_user_id();
    $notice = get_transient($key);
    delete_transient($key);
    return is_array($notice) ? $notice : null;
}

function mlr_greenpay_admin_selected_ids() {
    $ids = [];
    if (!empty($_POST['request_ids']) && is_array($_POST['request_ids'])) {
        $ids = array_map('absint', wp_unslash($_POST['request_ids']));
    }
    if (!empty($_POST['request_id'])) {
        $ids[] = absint($_POST['request_id']);
    }
    return array_values(array_unique(array_filter($ids)));
}


/**
 * GreenPay OTP admin flow without page navigation.
 * Sending OTP, opening the OTP dialog and confirming transfer all happen over AJAX
 * so the short-lived OTP window is not wasted on WordPress admin redirects/reloads.
 */
function mlr_greenpay_ajax_guard() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'دسترسی غیرمجاز.'], 403);
    }
    check_ajax_referer('mlr_greenpay_admin_ajax', 'nonce');
}

function mlr_greenpay_ajax_state_payload($request_id) {
    $tx = mlr_greenpay_get_transaction(absint($request_id));
    if (!$tx) {
        return [
            'request_id' => absint($request_id),
            'status' => 'not_found',
            'status_label' => 'رکورد پیدا نشد',
            'message' => 'رکورد GreenPay پرونده پیدا نشد.',
        ];
    }
    return [
        'request_id' => absint($request_id),
        'status' => (string) $tx->status,
        'status_label' => mlr_greenpay_status_label((string) $tx->status),
        'message' => (string) ($tx->last_error ?: ''),
        'send_time' => (string) ($tx->send_time ?: ''),
        'otp_requested_at' => (string) ($tx->otp_requested_at ?: ''),
        'provider_reference' => (string) ($tx->provider_reference ?: ''),
    ];
}

function mlr_greenpay_ajax_finish_state($request_id, $success_message = '') {
    $payload = mlr_greenpay_ajax_state_payload($request_id);
    if ($payload['status'] === 'awaiting_otp') {
        $payload['otp_required'] = true;
        $payload['message'] = $success_message ?: 'کد تأیید GreenPay ارسال شد. بدون خروج از صفحه، کد ۶ رقمی را وارد کنید.';
        wp_send_json_success($payload);
    }
    if ($payload['status'] === 'success') {
        $payload['completed'] = true;
        $payload['message'] = $success_message ?: 'کسری GreenPay با موفقیت نهایی شد.';
        wp_send_json_success($payload);
    }
    if ($payload['status'] === 'unknown') {
        wp_send_json_error(array_merge($payload, ['message' => 'نتیجه انتقال نامشخص است؛ برای جلوگیری از دوبارکسر، ابتدا از GreenPay استعلام بگیرید.']), 409);
    }
    if ($payload['status'] === 'failed') {
        wp_send_json_error(array_merge($payload, ['message' => $payload['message'] ?: 'پردازش GreenPay ناموفق بود.']), 422);
    }
    if ($payload['status'] === 'processing') {
        wp_send_json_error(array_merge($payload, ['message' => 'تراکنش هم‌اکنون در حال پردازش است. چند لحظه بعد دوباره وضعیت را بررسی کنید.']), 409);
    }
    wp_send_json_error(array_merge($payload, ['message' => $payload['message'] ?: 'وضعیت فعلی برای ادامه فرآیند OTP مناسب نیست.']), 409);
}

add_action('wp_ajax_mlr_greenpay_start_otp_flow', 'mlr_greenpay_start_otp_flow_ajax');
function mlr_greenpay_start_otp_flow_ajax() {
    mlr_greenpay_ajax_guard();
    $request_id = absint($_POST['request_id'] ?? 0);
    if (!$request_id) {
        wp_send_json_error(['message' => 'شناسه پرونده معتبر نیست.'], 400);
    }

    $tx = mlr_greenpay_get_transaction($request_id);
    if (!$tx || (int) $tx->eligible !== 1) {
        wp_send_json_error(['message' => 'این پرونده مشمول GreenPay نیست یا رکورد مالی آن پیدا نشد.'], 404);
    }

    if ($tx->status === 'awaiting_otp') {
        mlr_greenpay_ajax_finish_state($request_id, 'کد قبلاً درخواست شده است. همان کد را بدون رفرش صفحه وارد کنید یا در صورت نیاز «ارسال کد جدید» را بزنید.');
    }
    if ($tx->status === 'success') {
        mlr_greenpay_ajax_finish_state($request_id);
    }
    if (in_array($tx->status, ['unknown','processing','failed'], true)) {
        mlr_greenpay_ajax_finish_state($request_id);
    }

    if (in_array($tx->status, ['not_started','cancelled'], true)) {
        if (!mlr_greenpay_queue_request($request_id, 'admin_ajax_start')) {
            wp_send_json_error(['message' => 'پرونده برای شروع کسری آماده نیست؛ وضعیت پرونده، مشمول بودن و Pocket را بررسی کنید.'], 422);
        }
    }

    // The AJAX request itself owns the immediate execution. Remove the one-shot cron
    // created by queueing so it cannot race the interactive OTP request.
    wp_clear_scheduled_hook('mlr_greenpay_process_debit', [$request_id]);
    mlr_greenpay_process_debit_callback($request_id);
    mlr_greenpay_ajax_finish_state($request_id);
}

add_action('wp_ajax_mlr_greenpay_confirm_otp_ajax', 'mlr_greenpay_confirm_otp_ajax');
function mlr_greenpay_confirm_otp_ajax() {
    mlr_greenpay_ajax_guard();
    $request_id = absint($_POST['request_id'] ?? 0);
    $otp = wp_unslash($_POST['otp'] ?? '');
    $result = mlr_greenpay_confirm_with_admin_otp($request_id, $otp);
    if (is_wp_error($result)) {
        $payload = mlr_greenpay_ajax_state_payload($request_id);
        $payload['message'] = $result->get_error_message();
        wp_send_json_error($payload, 422);
    }
    mlr_greenpay_ajax_finish_state($request_id, 'کد تأیید پذیرفته شد و کسری GreenPay با موفقیت انجام شد.');
}

add_action('wp_ajax_mlr_greenpay_resend_otp_ajax', 'mlr_greenpay_resend_otp_ajax');
function mlr_greenpay_resend_otp_ajax() {
    mlr_greenpay_ajax_guard();
    $request_id = absint($_POST['request_id'] ?? 0);
    if (!mlr_greenpay_resend_otp($request_id)) {
        wp_send_json_error(['message' => 'ارسال مجدد فقط برای تراکنش در انتظار OTP امکان‌پذیر است.'], 422);
    }
    wp_clear_scheduled_hook('mlr_greenpay_process_debit', [$request_id]);
    mlr_greenpay_process_debit_callback($request_id);
    mlr_greenpay_ajax_finish_state($request_id, 'کد جدید GreenPay ارسال شد. کد قبلی را کنار بگذارید و همین‌جا کد جدید را وارد کنید.');
}

add_action('wp_ajax_mlr_greenpay_retry_failed_ajax', 'mlr_greenpay_retry_failed_ajax');
function mlr_greenpay_retry_failed_ajax() {
    mlr_greenpay_ajax_guard();
    $request_id = absint($_POST['request_id'] ?? 0);
    if (!$request_id) {
        wp_send_json_error(['message' => 'شناسه پرونده معتبر نیست.'], 400);
    }
    if (!mlr_greenpay_retry_failed_request($request_id)) {
        wp_send_json_error(['message' => 'تلاش مجدد فقط برای تراکنش قطعیِ ناموفق امکان‌پذیر است.'], 422);
    }
    // Interactive retry owns execution; prevent a concurrent one-shot cron run.
    wp_clear_scheduled_hook('mlr_greenpay_process_debit', [$request_id]);
    mlr_greenpay_process_debit_callback($request_id);
    mlr_greenpay_ajax_finish_state($request_id, 'درخواست جدید GreenPay ارسال شد. اگر OTP لازم باشد همین‌جا کد جدید را وارد کنید.');
}

add_action('admin_post_mlr_greenpay_admin_action', 'mlr_greenpay_admin_action_handler');
function mlr_greenpay_admin_action_handler() {
    if (!current_user_can('manage_options')) {
        wp_die('دسترسی غیرمجاز.');
    }
    check_admin_referer('mlr_greenpay_admin_action');

    $operation = sanitize_key($_POST['operation'] ?? '');
    $request_id = absint($_POST['request_id'] ?? 0);
    $redirect = admin_url('admin.php?page=mlr-greenpay-transactions');

    if (in_array($operation, ['mark_eligible','mark_ineligible'], true)) {
        $ids = mlr_greenpay_admin_selected_ids();
        if (!$ids) {
            mlr_greenpay_set_admin_notice('error', 'هیچ پرونده‌ای انتخاب نشده است.');
        } else {
            $want_eligible = $operation === 'mark_eligible';
            $settings = mlr_greenpay_get_settings();
            $is_live = (($settings['environment'] ?? 'test') === 'live');
            $single_from_pocket = preg_replace('/\D+/', '', mlr_greenpay_to_english_digits(wp_unslash($_POST['from_pocket'] ?? '')));
            if ($want_eligible && $is_live && count($ids) !== 1) {
                mlr_greenpay_set_admin_notice('error', 'در Production هر پرونده باید با Pocket ۱۶ رقمی خودش جداگانه مشمول GreenPay شود؛ عملیات گروهی مجاز نیست.');
                wp_safe_redirect($redirect);
                exit;
            }
            if ($want_eligible && $is_live && strlen($single_from_pocket) !== 16) {
                mlr_greenpay_set_admin_notice('error', 'برای مشمول‌کردن پرونده در Production، FromPocketNumber ۱۶ رقمی متقاضی الزامی است.');
                wp_safe_redirect($redirect);
                exit;
            }
            $ok = 0;
            $errors = [];
            foreach ($ids as $id) {
                $result = mlr_greenpay_set_eligibility($id, $want_eligible, 'admin_selection', $want_eligible ? $single_from_pocket : '');
                if (is_wp_error($result)) {
                    $errors[] = '#' . $id . ': ' . $result->get_error_message();
                } else {
                    $ok++;
                }
            }
            $message = number_format_i18n($ok) . ' پرونده به‌روزرسانی شد.';
            if ($errors) {
                $message .= ' خطا: ' . implode(' | ', array_slice($errors, 0, 5));
                mlr_greenpay_set_admin_notice('error', $message);
            } else {
                mlr_greenpay_set_admin_notice('success', $message);
            }
        }
    } elseif ($operation === 'update_from_pocket') {
        $result = mlr_greenpay_update_from_pocket($request_id, wp_unslash($_POST['from_pocket'] ?? ''));
        if (is_wp_error($result)) {
            mlr_greenpay_set_admin_notice('error', $result->get_error_message());
        } else {
            mlr_greenpay_set_admin_notice('success', 'FromPocketNumber پرونده ذخیره شد.');
        }
    } elseif ($operation === 'queue_ready') {
        global $wpdb;
        $requests_table = $wpdb->prefix . 'loan_requests';
        $tx_table = mlr_greenpay_transactions_table();
        $variants = function_exists('mlr_get_status_sql_variants') ? mlr_get_status_sql_variants('تأیید مدارک') : ['تأیید مدارک','تایید مدارک'];
        $variants = array_values(array_unique(array_filter(array_map('strval', (array) $variants))));
        $placeholders = implode(',', array_fill(0, count($variants), '%s'));
        $sql = $wpdb->prepare(
            "SELECT r.id FROM {$requests_table} r INNER JOIN {$tx_table} g ON g.request_id = r.id
             WHERE g.eligible = 1 AND g.status IN ('not_started','cancelled')
             AND TRIM(COALESCE(r.status,'')) IN ({$placeholders}) ORDER BY r.id ASC LIMIT 500",
            $variants
        );
        $ids = $wpdb->get_col($sql);
        $queued = 0;
        foreach ((array) $ids as $id) {
            if (mlr_greenpay_queue_request($id, 'admin_queue_ready')) {
                $queued++;
            }
        }
        mlr_greenpay_set_admin_notice('success', number_format_i18n($queued) . ' پرونده مشمول و آماده در صف کسری قرار گرفت.');
    } elseif ($operation === 'queue_now') {
        if (mlr_greenpay_queue_request($request_id, 'admin_queue_now')) {
            mlr_greenpay_set_admin_notice('success', 'پرونده در صف کسری GreenPay قرار گرفت.');
        } else {
            mlr_greenpay_set_admin_notice('error', 'پرونده باید مشمول GreenPay، در مرحله مناسب و بدون تراکنش فعال/موفق باشد.');
        }
    } elseif ($operation === 'confirm_otp') {
        $result = mlr_greenpay_confirm_with_admin_otp($request_id, $_POST['otp'] ?? '');
        if (is_wp_error($result)) {
            mlr_greenpay_set_admin_notice('error', $result->get_error_message());
        } else {
            mlr_greenpay_set_admin_notice('success', 'کسری GreenPay با موفقیت نهایی شد.');
        }
    } elseif ($operation === 'retry') {
        if (mlr_greenpay_retry_failed_request($request_id)) {
            mlr_greenpay_set_admin_notice('success', 'تراکنش برای تلاش مجدد در صف قرار گرفت.');
        } else {
            mlr_greenpay_set_admin_notice('error', 'فقط پرونده مشمول با تراکنش ناموفق قطعی قابل تلاش مجدد است.');
        }
    } elseif ($operation === 'resend_otp') {
        if (mlr_greenpay_resend_otp($request_id)) {
            mlr_greenpay_set_admin_notice('success', 'درخواست ارسال مجدد OTP در صف قرار گرفت.');
        } else {
            mlr_greenpay_set_admin_notice('error', 'این تراکنش مشمول GreenPay و در وضعیت انتظار OTP نیست.');
        }
    } elseif ($operation === 'process_now') {
        mlr_greenpay_process_debit_callback($request_id);
        $latest = mlr_greenpay_get_transaction($request_id);
        if (!$latest) {
            mlr_greenpay_set_admin_notice('error', 'رکورد GreenPay پرونده پیدا نشد.');
        } elseif ($latest->status === 'success') {
            mlr_greenpay_set_admin_notice('success', 'پردازش انجام شد و کسری GreenPay با موفقیت نهایی شد.');
        } elseif ($latest->status === 'awaiting_otp') {
            mlr_greenpay_set_admin_notice('success', 'OTP درخواست شد؛ برای تکمیل کسری، OTP را در همین صفحه وارد کنید.');
        } elseif ($latest->status === 'unknown') {
            mlr_greenpay_set_admin_notice('error', 'نتیجه انتقال نامشخص است؛ Retry نکنید و ابتدا از GreenPay استعلام بگیرید.');
        } elseif ($latest->status === 'failed') {
            mlr_greenpay_set_admin_notice('error', $latest->last_error ?: 'پردازش GreenPay ناموفق بود.');
        } else {
            mlr_greenpay_set_admin_notice('success', 'پردازش اجرا شد؛ وضعیت فعلی: ' . mlr_greenpay_status_label($latest->status));
        }
    } elseif ($operation === 'reconcile_unknown') {
        if (empty($_POST['confirmed'])) {
            mlr_greenpay_set_admin_notice('error', 'ثبت نتیجه استعلام نیازمند تأیید صریح مدیر است.');
        } else {
            $result = mlr_greenpay_reconcile_unknown(
                $request_id,
                $_POST['reconciliation_outcome'] ?? '',
                wp_unslash($_POST['reconciliation_note'] ?? ''),
                wp_unslash($_POST['provider_reference'] ?? '')
            );
            if (is_wp_error($result)) {
                mlr_greenpay_set_admin_notice('error', $result->get_error_message());
            } else {
                mlr_greenpay_set_admin_notice('success', 'نتیجه استعلام GreenPay ثبت شد.');
            }
        }
    } elseif ($operation === 'test_connection') {
        $settings = mlr_greenpay_get_settings();
        $api = new MLR_GreenPay_API($settings);
        $valid = $api->validate_configuration();
        if (is_wp_error($valid)) {
            mlr_greenpay_set_admin_notice('error', 'تست اتصال ناموفق: ' . $valid->get_error_message());
        } else {
            $token = $api->get_token();
            if (is_wp_error($token)) {
                mlr_greenpay_set_admin_notice('error', 'تست اتصال ناموفق: ' . $token->get_error_message());
            } else {
                $ready = $api->is_ready($token);
                if (is_wp_error($ready)) {
                    // The GreenPay reference WooCommerce plugin defines isReady(), but does not
                    // use it anywhere in the actual payment flow. A successful /submit token is
                    // therefore treated as successful API authentication; isReady is diagnostic
                    // only and must not block sandbox/production transaction testing.
                    mlr_greenpay_set_admin_notice(
                        'warning',
                        'توکن GreenPay با موفقیت دریافت شد و احراز هویت API برقرار است. سرویس تشخیصی isReady پاسخ موفق نداد (' . $ready->get_error_message() . '). این Endpoint در جریان پرداخت پلاگین مرجع GreenPay استفاده نمی‌شود؛ برای تأیید نهایی Sandbox، یک تراکنش آزمایشی transferByPocket انجام دهید.'
                    );
                } elseif (!$ready) {
                    mlr_greenpay_set_admin_notice(
                        'warning',
                        'توکن GreenPay با موفقیت دریافت شد و احراز هویت API برقرار است، اما مقدار برگشتی isReady قابل تأیید نبود. isReady در جریان پرداخت پلاگین مرجع استفاده نمی‌شود؛ برای تأیید نهایی Sandbox، یک تراکنش آزمایشی transferByPocket انجام دهید.'
                    );
                } else {
                    mlr_greenpay_set_admin_notice('success', 'احراز هویت GreenPay و تست تشخیصی isReady با موفقیت انجام شد.');
                }
            }
        }
    }

    wp_safe_redirect($redirect);
    exit;
}

function mlr_greenpay_transactions_page() {
    if (!current_user_can('manage_options')) {
        wp_die('شما دسترسی لازم را ندارید.');
    }

    global $wpdb;
    mlr_greenpay_maybe_install_schema();
    $tx_table = mlr_greenpay_transactions_table();
    $requests_table = $wpdb->prefix . 'loan_requests';
    $settings = mlr_greenpay_get_settings();
    $notice = mlr_greenpay_get_admin_notice();

    $scope = sanitize_key($_GET['gp_scope'] ?? '');
    $loan_status = sanitize_text_field(wp_unslash($_GET['loan_status'] ?? ''));
    $search = sanitize_text_field(wp_unslash($_GET['s'] ?? ''));

    $where = ['1=1'];
    $args = [];
    if ($scope === 'eligible') {
        $where[] = 'COALESCE(t.eligible,0) = 1';
    } elseif ($scope === 'not_eligible') {
        $where[] = 'COALESCE(t.eligible,0) = 0';
    } elseif ($scope === 'debited') {
        $where[] = "COALESCE(t.eligible,0) = 1 AND t.status = 'success'";
    } elseif ($scope === 'not_debited') {
        $where[] = "COALESCE(t.eligible,0) = 1 AND COALESCE(t.status,'not_started') <> 'success'";
    } elseif ($scope === 'attention') {
        $where[] = "COALESCE(t.eligible,0) = 1 AND t.status IN ('failed','unknown','awaiting_otp')";
    }
    if ($loan_status !== '') {
        $variants = function_exists('mlr_get_status_sql_variants') ? mlr_get_status_sql_variants($loan_status) : [$loan_status];
        $variants = array_values(array_unique(array_filter(array_map('strval', (array) $variants))));
        if ($variants) {
            $where[] = 'TRIM(COALESCE(r.status,\'\')) IN (' . implode(',', array_fill(0, count($variants), '%s')) . ')';
            foreach ($variants as $variant) {
                $args[] = $variant;
            }
        }
    }
    if ($search !== '') {
        $where[] = '(CAST(r.id AS CHAR) = %s OR r.full_name LIKE %s OR r.national_code LIKE %s)';
        $args[] = $search;
        $args[] = '%' . $wpdb->esc_like($search) . '%';
        $args[] = '%' . $wpdb->esc_like($search) . '%';
    }

    $sql = "SELECT r.id, r.full_name, r.national_code, r.status AS loan_status, r.loan_amount, r.facility_amount,
                   t.id AS tx_id, t.eligible, t.status AS gp_status, t.amount AS gp_amount, t.ref_number,
                   t.provider_reference, t.from_pocket, t.to_pocket, t.last_http_code, t.last_error, t.updated_at, t.debited_at,
                   t.reconciled_at, t.reconciled_by, t.reconciliation_note
            FROM {$requests_table} r
            LEFT JOIN {$tx_table} t ON t.request_id = r.id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY r.id DESC LIMIT 300";
    if ($args) {
        $sql = $wpdb->prepare($sql, $args);
    }
    $rows = $wpdb->get_results($sql);

    $loan_statuses = ['در حال بررسی','نقص مدارک','رد شده','تأیید کارشناس','تکمیل مدارک','تأیید مدارک','اعطا شده'];
    ?>
    <div class="wrap mlr-admin-wrap" dir="rtl">
        <h1>مدیریت GreenPay</h1>
        <p>GreenPay یک قابلیت مستقل و اختیاری است. <strong>هیچ پرونده‌ای تا زمانی که صراحتاً «مشمول GreenPay» نشود وارد کسری نخواهد شد.</strong> مبلغ کسری همان «مبلغ تسهیلات» پرونده است.</p>

        <?php if ($notice): ?>
            <?php $notice_class = $notice['type'] === 'error' ? 'error' : ($notice['type'] === 'warning' ? 'warning' : 'success'); ?>
            <div class="notice notice-<?php echo esc_attr($notice_class); ?> is-dismissible"><p><?php echo esc_html($notice['message']); ?></p></div>
        <?php endif; ?>

        <?php if (empty($settings['enabled'])): ?>
            <div class="notice notice-warning"><p>اتصال GreenPay غیرفعال است. انتخاب «مشمول» ذخیره می‌شود ولی تا فعال‌سازی اتصال، کسری ارسال نمی‌شود.</p></div>
        <?php endif; ?>
        <?php if (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON): ?>
            <div class="notice notice-warning"><p>WP-Cron غیرفعال است. برای پردازش خودکار صف باید Cron واقعی سرور wp-cron.php را اجرا کند.</p></div>
        <?php endif; ?>

        <div style="display:flex;gap:8px;flex-wrap:wrap;margin:14px 0;align-items:center;">
            <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=mlr-api-settings')); ?>">تنظیمات API</a>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-block;">
                <input type="hidden" name="action" value="mlr_greenpay_admin_action">
                <input type="hidden" name="operation" value="test_connection">
                <?php wp_nonce_field('mlr_greenpay_admin_action'); ?>
                <button type="submit" class="button">تست اتصال GreenPay</button>
            </form>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-block;" onsubmit="return confirm('فقط پرونده‌های مشمول GreenPay که اکنون در وضعیت تأیید مدارک و هنوز کسر نشده‌اند صف‌بندی می‌شوند. ادامه می‌دهید؟');">
                <input type="hidden" name="action" value="mlr_greenpay_admin_action">
                <input type="hidden" name="operation" value="queue_ready">
                <?php wp_nonce_field('mlr_greenpay_admin_action'); ?>
                <button type="submit" class="button button-primary">صف‌بندی مشمول‌های آماده</button>
            </form>
        </div>

        <form method="get" style="margin:12px 0;padding:12px;background:#fff;border:1px solid #dcdcde;display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
            <input type="hidden" name="page" value="mlr-greenpay-transactions">
            <input type="search" name="s" placeholder="شناسه، نام یا کد ملی" value="<?php echo esc_attr($search); ?>">
            <select name="loan_status">
                <option value="">همه وضعیت‌های پرونده</option>
                <?php foreach ($loan_statuses as $st): ?><option value="<?php echo esc_attr($st); ?>" <?php selected($loan_status, $st); ?>><?php echo esc_html($st); ?></option><?php endforeach; ?>
            </select>
            <select name="gp_scope">
                <option value="">همه پرونده‌ها</option>
                <option value="eligible" <?php selected($scope, 'eligible'); ?>>فقط مشمول GreenPay</option>
                <option value="not_eligible" <?php selected($scope, 'not_eligible'); ?>>فقط غیرمشمول</option>
                <option value="debited" <?php selected($scope, 'debited'); ?>>فقط کسر شده</option>
                <option value="not_debited" <?php selected($scope, 'not_debited'); ?>>مشمول و کسر نشده</option>
                <option value="attention" <?php selected($scope, 'attention'); ?>>نیازمند رسیدگی</option>
            </select>
            <button class="button">فیلتر</button>
            <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=mlr-greenpay-transactions')); ?>">پاک کردن فیلتر</a>
        </form>

        <form id="mlr-gp-bulk-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin:10px 0;display:flex;gap:8px;align-items:center;flex-wrap:wrap;" onsubmit="var op=this.querySelector('[name=operation]').value; return op !== 'mark_eligible' || confirm('پرونده‌های انتخاب‌شده مشمول GreenPay می‌شوند؛ مواردی که هم‌اکنون در «تأیید مدارک» هستند ممکن است بلافاصله وارد صف کسری شوند. ادامه می‌دهید؟');">
            <input type="hidden" name="action" value="mlr_greenpay_admin_action">
            <?php wp_nonce_field('mlr_greenpay_admin_action'); ?>
            <select name="operation" required>
                <option value="">عملیات گروهی…</option>
                <?php if (($settings['environment'] ?? 'test') !== 'live'): ?><option value="mark_eligible">مشمول GreenPay شود</option><?php endif; ?>
                <option value="mark_ineligible">از GreenPay خارج شود</option>
            </select>
            <button class="button">اعمال به پرونده‌های انتخاب‌شده</button>
            <span class="description">در Production مشمول‌کردن گروهی غیرفعال است؛ هر پرونده باید Pocket ۱۶ رقمی خودش را داشته باشد. پرونده کسرشده، درحال‌پردازش یا نامشخص قابل خروج نیست.</span>
        </form>

        <div style="overflow:auto;background:#fff;border:1px solid #dcdcde;border-radius:6px;">
            <table class="widefat striped" style="min-width:1250px;">
                <thead><tr>
                    <th style="width:36px;"><input type="checkbox" id="mlr-gp-select-all"></th>
                    <th>پرونده</th><th>متقاضی</th><th>مبلغ تسهیلات</th><th>وضعیت پرونده</th><th>مشمول GreenPay</th><th>وضعیت کیف پول</th><th>مرجع / جزئیات</th><th>آخرین پیام</th><th>عملیات</th>
                </tr></thead>
                <tbody>
                <?php if (!$rows): ?>
                    <tr><td colspan="10" style="text-align:center;padding:28px;">پرونده‌ای مطابق فیلتر پیدا نشد.</td></tr>
                <?php else: foreach ($rows as $row):
                    $eligible = (int) ($row->eligible ?? 0) === 1;
                    $gp_status = $row->gp_status ?: ($eligible ? 'not_started' : 'not_applicable');
                    $facility = ($row->facility_amount !== null && $row->facility_amount !== '') ? (float) $row->facility_amount : (float) $row->loan_amount;
                    $snapshot = $row->gp_amount !== null ? (float) $row->gp_amount : $facility;
                    $locked = $eligible && in_array($gp_status, ['success','processing','awaiting_otp','unknown'], true);
                    $docs_approved = mlr_greenpay_status_is_docs_approved($row->loan_status);
                    ?>
                    <tr data-mlr-gp-request-id="<?php echo esc_attr($row->id); ?>">
                        <td><input form="mlr-gp-bulk-form" class="mlr-gp-row-check" type="checkbox" name="request_ids[]" value="<?php echo esc_attr($row->id); ?>"></td>
                        <td><strong>#<?php echo esc_html($row->id); ?></strong><br><a href="<?php echo esc_url(admin_url('admin.php?page=mlr-loan-requests&s=' . absint($row->id))); ?>">نمایش در پرونده‌ها</a></td>
                        <td><?php echo esc_html($row->full_name ?: '—'); ?><br><small><?php echo esc_html($row->national_code ?: ''); ?></small></td>
                        <td><strong><?php echo esc_html(number_format($facility)); ?></strong> ریال<?php if ($row->tx_id && !in_array($gp_status, ['not_started','not_applicable','cancelled'], true) && (float)$snapshot !== (float)$facility): ?><br><small style="color:#996800;">مبلغ Snapshot تراکنش: <?php echo esc_html(number_format($snapshot)); ?></small><?php endif; ?></td>
                        <td><?php echo esc_html($row->loan_status ?: '—'); ?></td>
                        <td>
                            <?php if ($eligible): ?>
                                <span style="display:inline-block;padding:4px 8px;border-radius:14px;background:#edfaef;color:#116329;border:1px solid #8fd19e;">✓ مشمول</span>
                            <?php else: ?>
                                <span style="display:inline-block;padding:4px 8px;border-radius:14px;background:#f0f0f1;color:#50575e;border:1px solid #c3c4c7;">— غیرمشمول</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="mlr-gp-status-badge" style="display:inline-block;padding:4px 8px;border-radius:14px;<?php echo esc_attr(mlr_greenpay_status_badge_style($gp_status, $eligible)); ?>"><strong><?php echo esc_html(mlr_greenpay_status_label($gp_status)); ?></strong></span>
                            <?php if ($gp_status === 'success' && !empty($row->debited_at)): ?><br><small><?php echo esc_html(function_exists('mlr_gregorian_to_jalali') ? mlr_gregorian_to_jalali($row->debited_at, 'Y/m/d H:i') : $row->debited_at); ?></small><?php endif; ?>
                        </td>
                        <td class="ltr" style="direction:ltr;text-align:left;">
                            <?php echo $row->ref_number ? esc_html($row->ref_number) : '—'; ?>
                            <?php if (!empty($row->provider_reference)): ?><br><small><?php echo esc_html($row->provider_reference); ?></small><?php endif; ?>
                            <?php if (!empty($row->from_pocket)): ?><br><small><?php echo esc_html(mlr_greenpay_mask_pocket($row->from_pocket)); ?></small><?php endif; ?>
                            <?php if ($row->last_http_code !== null): ?><br><small>HTTP <?php echo esc_html($row->last_http_code); ?></small><?php endif; ?>
                        </td>
                        <td style="max-width:280px;white-space:normal;">
                            <?php echo esc_html($row->last_error ?: ($gp_status === 'success' ? 'کسری با موفقیت انجام شده است.' : '—')); ?>
                            <?php if (!empty($row->reconciliation_note)): ?>
                                <br><small style="display:block;margin-top:5px;color:#50575e;">استعلام دستی: <?php echo esc_html($row->reconciliation_note); ?></small>
                            <?php endif; ?>
                        </td>
                        <td class="mlr-gp-actions-cell" style="min-width:270px;">
                            <div style="display:flex;gap:5px;flex-wrap:wrap;align-items:center;">
                                <?php if (!$eligible): ?>
                                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:flex;gap:4px;align-items:center;flex-wrap:wrap;" onsubmit="return confirm('این پرونده مشمول GreenPay شود؟ اگر اکنون در «تأیید مدارک» باشد، ممکن است بلافاصله وارد صف کسری شود.');">
                                        <input type="hidden" name="action" value="mlr_greenpay_admin_action"><input type="hidden" name="operation" value="mark_eligible"><input type="hidden" name="request_id" value="<?php echo esc_attr($row->id); ?>"><?php wp_nonce_field('mlr_greenpay_admin_action'); ?>
                                        <?php if (($settings['environment'] ?? 'test') === 'live'): ?><input type="text" name="from_pocket" maxlength="16" inputmode="numeric" pattern="[0-9]{16}" class="regular-text ltr" style="width:175px;" placeholder="FromPocket 16 رقمی" required><?php endif; ?>
                                        <button class="button button-small button-primary">مشمول GreenPay شود</button>
                                    </form>
                                <?php elseif (!$locked): ?>
                                    <?php if (($settings['environment'] ?? 'test') === 'live' && in_array($gp_status, ['not_started','cancelled','failed'], true)): ?>
                                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:flex;gap:4px;align-items:center;flex-wrap:wrap;">
                                        <input type="hidden" name="action" value="mlr_greenpay_admin_action"><input type="hidden" name="operation" value="update_from_pocket"><input type="hidden" name="request_id" value="<?php echo esc_attr($row->id); ?>"><?php wp_nonce_field('mlr_greenpay_admin_action'); ?>
                                        <input type="text" name="from_pocket" value="<?php echo esc_attr($row->from_pocket ?: ''); ?>" maxlength="16" inputmode="numeric" pattern="[0-9]{16}" class="regular-text ltr" style="width:175px;" placeholder="FromPocket 16 رقمی" required>
                                        <button class="button button-small">ذخیره Pocket</button>
                                    </form>
                                    <?php endif; ?>
                                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('این پرونده از فرآیند GreenPay خارج شود؟');">
                                        <input type="hidden" name="action" value="mlr_greenpay_admin_action"><input type="hidden" name="operation" value="mark_ineligible"><input type="hidden" name="request_id" value="<?php echo esc_attr($row->id); ?>"><?php wp_nonce_field('mlr_greenpay_admin_action'); ?><button class="button button-small">خروج از GreenPay</button>
                                    </form>
                                <?php endif; ?>

                                <?php if ($eligible && in_array($gp_status, ['not_started','cancelled','queued'], true) && $docs_approved): ?>
                                    <button type="button" class="button button-small button-primary mlr-gp-start-otp" data-request-id="<?php echo esc_attr($row->id); ?>">ارسال کد و ادامه</button>
                                <?php elseif ($eligible && $gp_status === 'failed'): ?>
                                    <button type="button" class="button button-small mlr-gp-retry-failed" data-request-id="<?php echo esc_attr($row->id); ?>">تلاش مجدد بدون رفرش</button>
                                <?php elseif ($eligible && $gp_status === 'awaiting_otp'): ?>
                                    <button type="button" class="button button-small button-primary mlr-gp-open-otp" data-request-id="<?php echo esc_attr($row->id); ?>">ورود کد تأیید</button>
                                <?php elseif ($eligible && $gp_status === 'unknown'): ?>
                                    <details style="width:100%;max-width:520px;border:1px solid #dba0a0;background:#fff7f7;border-radius:6px;padding:8px;">
                                        <summary style="cursor:pointer;color:#8a2424;font-weight:600;">ثبت نتیجه استعلام GreenPay</summary>
                                        <p class="description" style="margin:8px 0;">Retry مستقیم عمداً غیرفعال است. ابتدا نتیجه را در پنل/پشتیبانی GreenPay با مرجع تراکنش استعلام کنید؛ سپس فقط نتیجه قطعی را ثبت کنید.</p>
                                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:grid;gap:6px;" onsubmit="return confirm('نتیجه استعلام GreenPay را شخصاً بررسی کرده‌اید و از ثبت آن مطمئن هستید؟');">
                                            <input type="hidden" name="action" value="mlr_greenpay_admin_action">
                                            <input type="hidden" name="operation" value="reconcile_unknown">
                                            <input type="hidden" name="request_id" value="<?php echo esc_attr($row->id); ?>">
                                            <?php wp_nonce_field('mlr_greenpay_admin_action'); ?>
                                            <select name="reconciliation_outcome" required>
                                                <option value="">نتیجه استعلام…</option>
                                                <option value="success">کسر انجام شده است</option>
                                                <option value="failed">کسر انجام نشده است</option>
                                            </select>
                                            <input type="text" name="provider_reference" class="regular-text ltr" placeholder="مرجع/Trace گرین‌پی (اختیاری)">
                                            <textarea name="reconciliation_note" rows="2" required placeholder="روش و نتیجه استعلام را کوتاه ثبت کنید"></textarea>
                                            <label><input type="checkbox" name="confirmed" value="1" required> نتیجه را از GreenPay بررسی و تأیید کرده‌ام.</label>
                                            <button class="button button-small button-primary">ثبت نتیجه استعلام</button>
                                        </form>
                                    </details>
                                <?php elseif ($eligible && $gp_status === 'success'): ?>
                                    <span style="color:#116329;font-weight:600;">✓ کسری نهایی شده</span>
                                <?php elseif ($eligible && !$docs_approved): ?>
                                    <span class="description">منتظر ورود پرونده به «تأیید مدارک»</span>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <p class="description" style="margin-top:10px;">حداکثر ۳۰۰ پرونده مطابق فیلتر نمایش داده می‌شود. برای پرونده‌های قدیمی از جستجو یا فیلتر استفاده کنید.</p>
    </div>
    <div id="mlr-gp-otp-modal" class="mlr-gp-modal" aria-hidden="true">
        <div class="mlr-gp-modal-backdrop" data-mlr-gp-close></div>
        <div class="mlr-gp-modal-card" role="dialog" aria-modal="true" aria-labelledby="mlr-gp-modal-title">
            <button type="button" class="mlr-gp-modal-close" data-mlr-gp-close aria-label="بستن">×</button>
            <div class="mlr-gp-modal-icon">✓</div>
            <h2 id="mlr-gp-modal-title">تأیید کسری GreenPay</h2>
            <p id="mlr-gp-modal-message" class="mlr-gp-modal-message">در حال آماده‌سازی درخواست…</p>
            <div id="mlr-gp-otp-form" class="mlr-gp-otp-form" hidden>
                <label for="mlr-gp-otp-input">کد تأیید ۶ رقمی</label>
                <input id="mlr-gp-otp-input" type="text" maxlength="6" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9۰-۹٠-٩]{6}" placeholder="------" dir="ltr">
                <button type="button" id="mlr-gp-confirm-otp" class="button button-primary button-hero">تأیید کد و انجام کسری</button>
                <button type="button" id="mlr-gp-resend-otp" class="button">ارسال کد جدید</button>
            </div>
            <div id="mlr-gp-modal-spinner" class="mlr-gp-spinner" aria-hidden="true"></div>
        </div>
    </div>
    <style>
        .mlr-gp-modal{position:fixed;inset:0;z-index:100000;display:none;align-items:center;justify-content:center;padding:24px;direction:rtl}.mlr-gp-modal.is-open{display:flex}.mlr-gp-modal-backdrop{position:absolute;inset:0;background:rgba(8,18,32,.58);backdrop-filter:blur(3px)}.mlr-gp-modal-card{position:relative;width:min(520px,100%);background:#fff;border-radius:20px;padding:30px 30px 26px;box-shadow:0 28px 80px rgba(0,0,0,.28);text-align:center}.mlr-gp-modal-close{position:absolute;left:16px;top:12px;border:0;background:transparent;font-size:30px;line-height:1;cursor:pointer;color:#50575e}.mlr-gp-modal-icon{width:54px;height:54px;margin:0 auto 12px;border-radius:18px;display:grid;place-items:center;background:#edf7ff;color:#2271b1;font-size:28px;font-weight:700}.mlr-gp-modal-card h2{margin:0 0 10px;font-size:21px}.mlr-gp-modal-message{margin:0 auto 20px;line-height:1.9;color:#50575e;max-width:420px}.mlr-gp-modal-message.is-error{color:#b32d2e}.mlr-gp-modal-message.is-success{color:#116329;font-weight:600}.mlr-gp-otp-form{display:grid;gap:12px;justify-items:center}.mlr-gp-otp-form label{font-weight:600}.mlr-gp-otp-form input{width:230px;max-width:100%;height:54px;font-size:28px;font-weight:700;text-align:center;letter-spacing:10px;border:2px solid #8c8f94;border-radius:12px}.mlr-gp-otp-form input:focus{border-color:#2271b1;box-shadow:0 0 0 2px #72aee633;outline:0}.mlr-gp-spinner{width:34px;height:34px;border:3px solid #dcdcde;border-top-color:#2271b1;border-radius:50%;margin:18px auto 0;animation:mlrGpSpin .7s linear infinite}.mlr-gp-spinner[aria-hidden="true"]{display:none}@keyframes mlrGpSpin{to{transform:rotate(360deg)}}body.mlr-gp-modal-open{overflow:hidden}
    </style>
    <script>
    (function(){
        var all = document.getElementById('mlr-gp-select-all');
        if (all) all.addEventListener('change', function(){
            document.querySelectorAll('.mlr-gp-row-check').forEach(function(cb){ cb.checked = all.checked; });
        });

        var modal = document.getElementById('mlr-gp-otp-modal');
        if (!modal) return;
        var msg = document.getElementById('mlr-gp-modal-message');
        var form = document.getElementById('mlr-gp-otp-form');
        var otp = document.getElementById('mlr-gp-otp-input');
        var confirmBtn = document.getElementById('mlr-gp-confirm-otp');
        var resendBtn = document.getElementById('mlr-gp-resend-otp');
        var spinner = document.getElementById('mlr-gp-modal-spinner');
        var nonce = <?php echo wp_json_encode(wp_create_nonce('mlr_greenpay_admin_ajax')); ?>;
        var activeRequestId = 0;
        var busy = false;

        function setBusy(on, text){
            busy = !!on;
            spinner.setAttribute('aria-hidden', on ? 'false' : 'true');
            confirmBtn.disabled = on;
            resendBtn.disabled = on;
            if (text) setMessage(text, '');
        }
        function setMessage(text, type){
            msg.textContent = text || '';
            msg.className = 'mlr-gp-modal-message' + (type ? ' is-' + type : '');
        }
        function openModal(id, loadingText){
            activeRequestId = parseInt(id,10) || 0;
            modal.classList.add('is-open');
            modal.setAttribute('aria-hidden','false');
            document.body.classList.add('mlr-gp-modal-open');
            form.hidden = true;
            otp.value = '';
            setBusy(true, loadingText || 'در حال درخواست کد تأیید GreenPay…');
        }
        function revealOtp(message){
            setBusy(false);
            setMessage(message || 'کد تأیید ارسال شد. همین‌جا وارد کنید.', 'success');
            form.hidden = false;
            window.setTimeout(function(){ otp.focus(); }, 60);
        }
        function closeModal(){
            if (busy) return;
            modal.classList.remove('is-open');
            modal.setAttribute('aria-hidden','true');
            document.body.classList.remove('mlr-gp-modal-open');
            activeRequestId = 0;
            otp.value = '';
        }
        function api(action, payload){
            var fd = new FormData();
            fd.append('action', action);
            fd.append('nonce', nonce);
            Object.keys(payload || {}).forEach(function(k){ fd.append(k, payload[k]); });
            return fetch(window.ajaxurl, {method:'POST', body:fd, credentials:'same-origin'})
                .then(function(r){ return r.json().catch(function(){ throw new Error('پاسخ نامعتبر از سرور دریافت شد.'); }); })
                .then(function(json){
                    if (!json || !json.success) {
                        var data = json && json.data ? json.data : null;
                        var message = data && data.message ? data.message : 'عملیات GreenPay ناموفق بود.';
                        var err = new Error(message); err.data = data || {}; throw err;
                    }
                    return json.data || {};
                });
        }
        function updateRow(data){
            if (!data || !data.request_id) return;
            var row = document.querySelector('tr[data-mlr-gp-request-id="' + data.request_id + '"]');
            if (!row) return;
            var badge = row.querySelector('.mlr-gp-status-badge strong');
            if (badge && data.status_label) badge.textContent = data.status_label;
            if (data.status === 'success') {
                var cell = row.querySelector('.mlr-gp-actions-cell');
                if (cell) cell.innerHTML = '<span style="color:#116329;font-weight:600;">✓ کسری نهایی شده</span>';
            }
        }
        function startFlow(id){
            openModal(id, 'در حال ارسال درخواست OTP به GreenPay…');
            api('mlr_greenpay_start_otp_flow', {request_id:id}).then(function(data){
                updateRow(data);
                if (data.completed) {
                    setBusy(false); form.hidden = true; setMessage(data.message || 'کسری با موفقیت انجام شد.', 'success');
                } else if (data.otp_required) {
                    revealOtp(data.message);
                }
            }).catch(function(err){ setBusy(false); form.hidden = true; setMessage(err.message, 'error'); });
        }
        document.addEventListener('click', function(e){
            var start = e.target.closest('.mlr-gp-start-otp');
            if (start) { e.preventDefault(); startFlow(start.getAttribute('data-request-id')); return; }
            var retryFailed = e.target.closest('.mlr-gp-retry-failed');
            if (retryFailed) {
                e.preventDefault();
                openModal(retryFailed.getAttribute('data-request-id'), 'در حال ارسال درخواست جدید GreenPay…');
                api('mlr_greenpay_retry_failed_ajax',{request_id:activeRequestId}).then(function(data){
                    updateRow(data);
                    if (data.otp_required) revealOtp(data.message);
                    else if (data.completed) { setBusy(false); form.hidden = true; setMessage(data.message || 'کسری با موفقیت انجام شد.','success'); }
                }).catch(function(err){ setBusy(false); form.hidden = true; setMessage(err.message,'error'); });
                return;
            }
            var open = e.target.closest('.mlr-gp-open-otp');
            if (open) { e.preventDefault(); openModal(open.getAttribute('data-request-id'),'در حال بررسی وضعیت OTP…'); api('mlr_greenpay_start_otp_flow',{request_id:activeRequestId}).then(function(data){updateRow(data); if(data.otp_required) revealOtp(data.message); else if(data.completed){setBusy(false);form.hidden=true;setMessage(data.message,'success');}}).catch(function(err){setBusy(false);form.hidden=true;setMessage(err.message,'error');}); return; }
            if (e.target.closest('[data-mlr-gp-close]')) { e.preventDefault(); closeModal(); }
        });
        confirmBtn.addEventListener('click', function(){
            if (busy || !activeRequestId) return;
            var code = (otp.value || '').replace(/[^0-9۰-۹٠-٩]/g,'');
            if (code.length !== 6) { setMessage('کد تأیید باید دقیقاً ۶ رقم باشد.', 'error'); otp.focus(); return; }
            setBusy(true, 'در حال تأیید کد و انجام کسری…');
            api('mlr_greenpay_confirm_otp_ajax',{request_id:activeRequestId,otp:code}).then(function(data){
                updateRow(data); setBusy(false); form.hidden = true; setMessage(data.message || 'کسری با موفقیت انجام شد.', 'success');
            }).catch(function(err){ setBusy(false); form.hidden = false; setMessage(err.message, 'error'); otp.select(); });
        });
        resendBtn.addEventListener('click', function(){
            if (busy || !activeRequestId) return;
            otp.value = ''; form.hidden = true; setBusy(true, 'در حال ارسال کد جدید…');
            api('mlr_greenpay_resend_otp_ajax',{request_id:activeRequestId}).then(function(data){ updateRow(data); revealOtp(data.message); }).catch(function(err){ setBusy(false); form.hidden = false; setMessage(err.message,'error'); });
        });
        otp.addEventListener('keydown', function(e){ if (e.key === 'Enter') { e.preventDefault(); confirmBtn.click(); } });
        document.addEventListener('keydown', function(e){ if (e.key === 'Escape' && modal.classList.contains('is-open')) closeModal(); });
    })();
    </script>
    <?php
}
