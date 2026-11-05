<?php
/**
 * صفحه ویرایش تاریخ تسویه — در دسترس برای نقش‌های administrator و mali (قابلیت mlr_edit_settlement_date)
 */
if (!defined('ABSPATH')) {
    exit;
}

function mlr_settlement_date_editor_page() {
    if (!current_user_can('mlr_edit_settlement_date')) {
        wp_die('شما دسترسی لازم برای مشاهده این صفحه را ندارید.');
    }

    global $wpdb;
    $table_requests = $wpdb->prefix . 'loan_requests';
    $users_table = $wpdb->prefix . 'users';
    $effective_acceptor_sql = function_exists('mlr_get_request_effective_acceptor_sql')
        ? mlr_get_request_effective_acceptor_sql('r')
        : 'COALESCE(r.acceptor_user_id, r.user_id)';

    if (function_exists('mlr_repair_granted_request_settlement_states_once')) {
        mlr_repair_granted_request_settlement_states_once();
    }

    $filter_acceptor = isset($_GET['acceptor_id']) ? absint($_GET['acceptor_id']) : 0;
    $filter_status = isset($_GET['settlement_filter']) ? sanitize_text_field($_GET['settlement_filter']) : '';

    $where = "TRIM(COALESCE(r.status, '')) = 'اعطا شده'";
    $prepare_params = [];

    if ($filter_acceptor > 0) {
        $where .= " AND {$effective_acceptor_sql} = %d";
        $prepare_params[] = $filter_acceptor;
    }

    if ($filter_status === 'waiting') {
        $where .= " AND (TRIM(COALESCE(r.settlement_status, '')) = 'منتظر تعیین تاریخ' OR r.settlement_status IS NULL OR r.settlement_status = '')";
    } elseif ($filter_status === 'scheduled') {
        $where .= " AND TRIM(COALESCE(r.settlement_status, '')) = 'در انتظار تسویه' AND r.settlement_date IS NOT NULL";
    }
    // همه (اعطا شده) به‌صورت پیش‌فرض

    $sql = "
        SELECT r.id, r.user_id, r.applicant_user_id, r.acceptor_user_id, r.request_origin, r.full_name, r.mobile_number, r.final_settlement_amount, r.settlement_status, r.settlement_date, r.last_edited_at, u.display_name AS acceptor_name
        FROM $table_requests r
        LEFT JOIN $users_table u ON {$effective_acceptor_sql} = u.ID
        WHERE $where
        ORDER BY r.settlement_date DESC, r.id DESC
    ";

    $requests = !empty($prepare_params)
        ? $wpdb->get_results($wpdb->prepare($sql, $prepare_params))
        : $wpdb->get_results($sql);

    $acceptors = $wpdb->get_results("
        SELECT DISTINCT u.ID, u.display_name
        FROM $users_table u
        INNER JOIN $table_requests r ON {$effective_acceptor_sql} = u.ID
        WHERE TRIM(COALESCE(r.status, '')) = 'اعطا شده'
          AND {$effective_acceptor_sql} > 0
        ORDER BY u.display_name
    ");

    $nonce = wp_create_nonce('mlr_settlement_date_edit_nonce');
    $base_url = admin_url('admin.php?page=mlr-settlement-date-editor');
    ?>
    <div class="wrap mlr-admin-wrap mlr-settlement-date-editor-wrap">
        <div class="mlr-page-header">
            <h1><span class="dashicons dashicons-edit"></span> ویرایش تاریخ تسویه</h1>
        </div>
        <p class="mlr-page-description">درخواست‌های اعطا شده را بر اساس پذیرنده یا وضعیت تسویه فیلتر کنید و تاریخ تسویه را ویرایش یا تعیین کنید. این بخش در دسترس نقش‌های <strong>مدیر</strong> و <strong>مالی</strong> است.</p>

        <div class="mlr-card mlr-filter-card" style="margin-bottom: 20px;">
            <div class="mlr-card-content">
                <form method="get" action="" class="mlr-inline-filter-form">
                    <input type="hidden" name="page" value="mlr-settlement-date-editor">
                    <label for="acceptor_id">پذیرنده:</label>
                    <select name="acceptor_id" id="acceptor_id" class="mlr-select">
                        <option value="0">همه</option>
                        <?php foreach ($acceptors as $acc): ?>
                            <option value="<?php echo esc_attr($acc->ID); ?>" <?php selected($filter_acceptor, $acc->ID); ?>><?php echo esc_html($acc->display_name); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <label for="settlement_filter" style="margin-right: 15px;">وضعیت تسویه:</label>
                    <select name="settlement_filter" id="settlement_filter" class="mlr-select">
                        <option value="">همه (اعطا شده)</option>
                        <option value="waiting" <?php selected($filter_status, 'waiting'); ?>>منتظر تعیین تاریخ</option>
                        <option value="scheduled" <?php selected($filter_status, 'scheduled'); ?>>در انتظار تسویه (دارای تاریخ)</option>
                    </select>
                    <button type="submit" class="button button-secondary">اعمال فیلتر</button>
                </form>
            </div>
        </div>

        <div id="mlr-settlement-date-message" class="notice" style="display:none; margin: 0 0 15px;"></div>

        <?php if (empty($requests)): ?>
            <div class="mlr-card">
                <div class="mlr-card-content" style="text-align: center; padding: 30px;">
                    <span class="dashicons dashicons-info-outline" style="font-size: 30px; color: #777;"></span>
                    <p>درخواستی با این فیلترها یافت نشد.</p>
                </div>
            </div>
        <?php else: ?>
            <div class="mlr-card mlr-table-card">
                <div class="mlr-card-content no-padding">
                    <table class="wp-list-table widefat fixed striped mlr-settlement-edit-table">
                        <thead>
                            <tr>
                                <th class="column-id">شناسه</th>
                                <th>پذیرنده</th>
                                <th>متقاضی</th>
                                <th>موبایل</th>
                                <th class="column-amount">مبلغ تسویه (ریال)</th>
                                <th>وضعیت تسویه</th>
                                <th class="column-date">تاریخ تسویه فعلی</th>
                                <th class="column-date-edit">تاریخ جدید / ویرایش</th>
                                <th class="column-actions">عملیات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($requests as $req): 
                                $current_date_display = !empty($req->settlement_date) && function_exists('mlr_gregorian_to_jalali')
                                    ? mlr_gregorian_to_jalali($req->settlement_date, 'Y/m/d')
                                    : '—';
                                $current_date_value = !empty($req->settlement_date) && function_exists('mlr_gregorian_to_jalali')
                                    ? mlr_gregorian_to_jalali($req->settlement_date, 'Y/m/d')
                                    : '';
                                $has_effective_acceptor = function_exists('mlr_request_has_effective_acceptor')
                                    ? mlr_request_has_effective_acceptor($req)
                                    : (!empty($req->acceptor_user_id) || trim((string) ($req->request_origin ?? '')) !== 'applicant_portal');
                                $acceptor_display_name = $has_effective_acceptor
                                    ? ($req->acceptor_name ?: '—')
                                    : 'نیازمند انتخاب پذیرنده';
                            ?>
                                <tr data-request-id="<?php echo esc_attr($req->id); ?>">
                                    <td class="column-id"><?php echo esc_html($req->id); ?></td>
                                    <td>
                                        <?php echo esc_html($acceptor_display_name); ?>
                                        <?php if (!$has_effective_acceptor): ?>
                                            <br><small class="mlr-missing-acceptor-note">ثبت تاریخ پس از اتصال پذیرنده فعال می‌شود.</small>
                                        <?php endif; ?>
                                    </td>
                                    <td><strong><?php echo esc_html($req->full_name); ?></strong></td>
                                    <td><?php echo esc_html($req->mobile_number); ?></td>
                                    <td class="column-amount"><?php echo number_format($req->final_settlement_amount); ?></td>
                                    <td class="mlr-current-settlement-status"><?php echo esc_html($req->settlement_status ?: 'منتظر تعیین تاریخ'); ?></td>
                                    <td class="column-date mlr-current-date" data-request-id="<?php echo esc_attr($req->id); ?>"><?php echo esc_html($current_date_display); ?></td>
                                    <td class="column-date-edit">
                                        <div class="mlr-date-edit-cell">
                                            <input type="text"
                                                   class="jalali-datepicker mlr-settlement-date-input"
                                                   data-request-id="<?php echo esc_attr($req->id); ?>"
                                                   placeholder="۱۴۰۳/۰۶/۱۵"
                                                   value="<?php echo esc_attr($current_date_value); ?>"
                                                   autocomplete="off"
                                                   style="width: 120px;"
                                                   <?php disabled(!$has_effective_acceptor); ?>>
                                            <button type="button" class="button button-small mlr-save-settlement-date-btn" data-request-id="<?php echo esc_attr($req->id); ?>" <?php disabled(!$has_effective_acceptor); ?> title="<?php echo !$has_effective_acceptor ? esc_attr('ابتدا باید پذیرنده پرونده مشخص شود.') : esc_attr('ذخیره تاریخ تسویه'); ?>">ذخیره</button>
                                        </div>
                                    </td>
                                    <td class="column-actions">
                                        <span class="mlr-row-msg" data-request-id="<?php echo esc_attr($req->id); ?>" style="font-size: 12px; color: #666;"></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <style>
        .mlr-settlement-date-editor-wrap .mlr-card { background: #fff; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04); }
        .mlr-settlement-date-editor-wrap .mlr-card-content { padding: 15px; }
        .mlr-settlement-date-editor-wrap .mlr-card-content.no-padding { padding: 0; }
        .mlr-settlement-date-editor-wrap .mlr-inline-filter-form { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; }
        .mlr-settlement-date-editor-wrap .mlr-select { min-width: 160px; }
        .mlr-settlement-date-editor-wrap .column-id { width: 60px; }
        .mlr-settlement-date-editor-wrap .column-amount { width: 120px; text-align: left; }
        .mlr-settlement-date-editor-wrap .column-date { width: 110px; }
        .mlr-settlement-date-editor-wrap .column-date-edit { width: 200px; }
        .mlr-settlement-date-editor-wrap .mlr-date-edit-cell { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
        .mlr-settlement-date-editor-wrap .mlr-save-settlement-date-btn { white-space: nowrap; }
        .mlr-settlement-date-editor-wrap .mlr-missing-acceptor-note { display: inline-block; margin-top: 4px; color: #b45309; font-size: 12px; line-height: 1.6; }
    </style>

    <script>
    jQuery(document).ready(function($) {
        var nonce = <?php echo json_encode($nonce); ?>;
        var ajaxUrl = <?php echo json_encode(admin_url('admin-ajax.php')); ?>;

        function showMessage(text, type) {
            var notice = $('#mlr-settlement-date-message');
            notice.removeClass('notice-success notice-error').addClass(type === 'success' ? 'notice-success' : 'notice-error').html('<p>' + text + '</p>').show();
        }

        function setRowState(row, loading) {
            var btn = row.find('.mlr-save-settlement-date-btn');
            var msg = row.find('.mlr-row-msg');
            if (loading) {
                btn.prop('disabled', true).text('در حال ذخیره...');
                msg.text('').css('color', '');
            } else {
                btn.prop('disabled', false).text('ذخیره');
            }
        }

        $(document).on('click', '.mlr-save-settlement-date-btn', function() {
            var btn = $(this);
            var requestId = btn.data('request-id');
            var row = btn.closest('tr');
            var input = row.find('.mlr-settlement-date-input[data-request-id="' + requestId + '"]');
            var dateVal = (input.val() || '').trim();

            if (!dateVal) {
                row.find('.mlr-row-msg').css('color', '#b32d2e').text('تاریخ را وارد کنید.');
                return;
            }

            setRowState(row, true);
            row.find('.mlr-row-msg').text('').css('color', '');

            $.post(ajaxUrl, {
                action: 'mlr_update_settlement_date',
                nonce: nonce,
                request_id: requestId,
                settlement_date: dateVal
            }, 'json').done(function(res) {
                setRowState(row, false);
                if (res && res.success) {
                    row.find('.mlr-current-date').text(res.data && res.data.settlement_date_display ? res.data.settlement_date_display : dateVal);
                    if (res.data && res.data.settlement_status_display) {
                        row.find('.mlr-current-settlement-status').text(res.data.settlement_status_display);
                    }
                    row.find('.mlr-row-msg').css('color', 'green').text(res.data && res.data.message ? res.data.message : 'ذخیره شد.');
                    showMessage(res.data && res.data.message ? res.data.message : 'تاریخ تسویه بروزرسانی شد.', 'success');
                } else {
                    row.find('.mlr-row-msg').css('color', '#b32d2e').text((res && res.data && res.data.message) ? res.data.message : 'خطا');
                    showMessage((res && res.data && res.data.message) ? res.data.message : 'خطا در بروزرسانی.', 'error');
                }
            }).fail(function(xhr) {
                setRowState(row, false);
                var err = 'خطای ارتباط با سرور.';
                if (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) err = xhr.responseJSON.data.message;
                else if (xhr && xhr.responseJSON && xhr.responseJSON.message) err = xhr.responseJSON.message;
                row.find('.mlr-row-msg').css('color', '#b32d2e').text(err);
                showMessage(err, 'error');
            });
        });
    });
    </script>
    <?php
}
