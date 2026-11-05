<?php
/**
 * Explicit, audited corrections to individual cooperation and loan requests.
 * No unattended changes, no contract/OTP/signature or financial state editing.
 */
if (!defined('ABSPATH')) exit;

function mlr_rc_url($kind = 'cooperation', $id = 0, $notice = '') {
    $args = ['page' => 'mlr-record-corrections', 'kind' => $kind];
    if ($id) $args['request_id'] = absint($id);
    if ($notice) $args['mlr_notice'] = $notice;
    return add_query_arg($args, admin_url('admin.php'));
}

function mlr_rc_editable_fields($kind, $row = []) {
    if ($kind === 'cooperation') {
        $fields = [];
        $definitions = function_exists('mlr_get_all_cooperation_fields') ? mlr_get_all_cooperation_fields() : [];
        $data = json_decode((string) ($row['data'] ?? '{}'), true);
        if (!is_array($data)) return [];
        foreach ($definitions as $key => $info) {
            if (($info['type'] ?? '') === 'file') continue;
            $form = $info['form'] ?? 'both';
            if ($form !== 'both' && $form !== ($row['request_type'] ?? '')) continue;
            $fields[$key] = [ 'label' => $info['label'], 'type' => $info['type'] === 'textarea' ? 'textarea' : 'text' ];
        }
        return $fields;
    }
    // Only profile/contact metadata. Contracts, signatures, money, status and
    // bank settlement are deliberately excluded from general-purpose editing.
    return [
        'full_name' => ['label' => 'نام متقاضی', 'type' => 'text'],
        'national_code' => ['label' => 'کد ملی', 'type' => 'text'],
        'mobile_number' => ['label' => 'شماره همراه', 'type' => 'text'],
        'phone_number' => ['label' => 'تلفن ثابت', 'type' => 'text'],
        'postal_code' => ['label' => 'کد پستی', 'type' => 'text'],
        'residential_address' => ['label' => 'نشانی محل سکونت', 'type' => 'textarea'],
        'work_address' => ['label' => 'نشانی محل کار', 'type' => 'textarea'],
        'occupation' => ['label' => 'شغل', 'type' => 'text'],
        'description' => ['label' => 'توضیحات پرونده', 'type' => 'textarea'],
    ];
}

function mlr_rc_history_add($kind, $id, $before, $after, $reason) {
    $key = 'mlr_rc_history_' . $kind . '_' . absint($id);
    $history = get_option($key, []);
    if (!is_array($history)) $history = [];
    $history[] = [
        'admin' => get_current_user_id(), 'time' => current_time('mysql'),
        'reason' => $reason, 'before' => $before, 'after' => $after,
    ];
    // Store only the last 25 revisions, and do not autoload these private records.
    $history = array_slice($history, -25);
    if (get_option($key, null) === null) add_option($key, $history, '', false);
    else update_option($key, $history, false);
}

add_action('admin_post_mlr_rc_save', 'mlr_rc_save');
function mlr_rc_save() {
    if (!current_user_can('manage_options')) wp_die('دسترسی غیرمجاز.');
    check_admin_referer('mlr_rc_save');
    $kind = isset($_POST['kind']) ? sanitize_key(wp_unslash($_POST['kind'])) : '';
    $id = absint($_POST['request_id'] ?? 0);
    if (!$id || !in_array($kind, ['cooperation', 'loan'], true)) wp_die('اطلاعات پرونده نامعتبر است.');
    global $wpdb;
    $table = $wpdb->prefix . ($kind === 'cooperation' ? 'cooperation_requests' : 'loan_requests');
    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM `{$table}` WHERE id = %d", $id), ARRAY_A);
    if (!$row) wp_die('پرونده یافت نشد.');
    $reason = sanitize_textarea_field(wp_unslash($_POST['reason'] ?? ''));
    if (trim($reason) === '') wp_die('علت اصلاح الزامی است.');
    $mode = sanitize_key(wp_unslash($_POST['mode'] ?? 'details'));
    $notice = 'error';

    if ($kind === 'loan' && $mode === 'reset_settlement') {
        // This resets only the Babanoel/acceptor settlement, never the BANK one.
        // A cycle membership must be undone via the cycle's own accounting path.
        $status = function_exists('mlr_normalize_status') ? mlr_normalize_status($row['status']) : $row['status'];
        $cycle = absint($row['settlement_cycle_id'] ?? 0);
        if ($status !== 'اعطا شده') wp_die('فقط پرونده اعطاشده قابل بازنشانی تسویه است.');
        if ($cycle) wp_die('این پرونده عضو سیکل تسویه پذیرندگان است؛ ابتدا سیکل را در فرآیند مالی بررسی و اصلاح کنید.');
        if (empty($_POST['acknowledge'])) wp_die('تأیید آگاهانه عملیات الزامی است.');
        $guard = function_exists('mlr_begin_financial_write_guard') ? mlr_begin_financial_write_guard([$table]) : false;
        if (!$guard) wp_die('قفل امن دیتابیس برقرار نشد؛ هیچ تغییری انجام نشد.');
        $locked = $wpdb->get_row($wpdb->prepare("SELECT status, settlement_cycle_id, settlement_status, settlement_date FROM `{$table}` WHERE id = %d" . ($guard === 'transaction' ? ' FOR UPDATE' : ''), $id), ARRAY_A);
        if (!$locked || absint($locked['settlement_cycle_id'] ?? 0) ||
            (function_exists('mlr_normalize_status') ? mlr_normalize_status($locked['status'] ?? '') : $locked['status']) !== 'اعطا شده') {
            mlr_end_financial_write_guard($guard, false);
            wp_die('وضعیت پرونده تغییر کرده یا عضو سیکل است؛ اصلاح انجام نشد.');
        }
        $updated = $wpdb->query($wpdb->prepare(
            "UPDATE `{$table}` SET settlement_date = NULL, settlement_status = %s
             WHERE id = %d AND (settlement_cycle_id IS NULL OR settlement_cycle_id = 0) AND status = %s",
            'منتظر تعیین تاریخ', $id, $locked['status']
        ));
        $verify = $wpdb->get_row($wpdb->prepare("SELECT settlement_date, settlement_status, settlement_cycle_id FROM `{$table}` WHERE id = %d", $id), ARRAY_A);
        $ok = $updated !== false && $verify && empty($verify['settlement_date']) && !$verify['settlement_cycle_id'] && $verify['settlement_status'] === 'منتظر تعیین تاریخ';
        mlr_end_financial_write_guard($guard, $ok);
        if ($ok) {
            mlr_rc_history_add($kind, $id,
                ['settlement_date' => $locked['settlement_date'], 'settlement_status' => $locked['settlement_status']],
                ['settlement_date' => null, 'settlement_status' => 'منتظر تعیین تاریخ'], $reason);
            if (function_exists('mlr_add_request_event')) mlr_add_request_event($id, 'admin_settlement_date_reset', 'حذف تاریخ تسویه پذیرنده و بازگشت به منتظر تعیین تاریخ؛ دلیل: ' . $reason);
            $notice = 'reset';
        }
    } elseif ($mode === 'details') {
        if ($kind === 'cooperation' && ($row['contract_signed_status'] ?? '') === 'signed' && empty($_POST['acknowledge_signed'])) {
            wp_die('این درخواست قرارداد امضاشده دارد. برای اصلاح مشخصات، آگاهی از اثر تغییر روی نمایش قرارداد را تأیید کنید.');
        }
        $fields = mlr_rc_editable_fields($kind, $row);
        $posted = isset($_POST['fields']) && is_array($_POST['fields']) ? wp_unslash($_POST['fields']) : [];
        $old_values = [];
        $new_values = [];
        $data = $kind === 'cooperation' ? json_decode((string) $row['data'], true) : [];
        if ($kind === 'cooperation' && !is_array($data)) wp_die('ساختار JSON پرونده نامعتبر است.');
        foreach ($fields as $key => $info) {
            if (!array_key_exists($key, $posted) || !is_scalar($posted[$key])) continue;
            $raw = (string) $posted[$key];
            $value = $info['type'] === 'textarea' ? sanitize_textarea_field($raw) : sanitize_text_field($raw);
            if ($key === 'email' && $value !== '' && !is_email($value)) wp_die('آدرس ایمیل نامعتبر است.');
            if ($key === 'applicant_id_number' && $value !== '' && !preg_match('/^[0-9]{10,11}$/', $value)) wp_die('کد/شناسه ملی باید ۱۰ یا ۱۱ رقم باشد.');
            if ($key === 'national_code' && $value !== '' && !preg_match('/^[0-9]{10}$/', $value)) wp_die('کد ملی باید ۱۰ رقم باشد.');
            if ($key === 'mobile_number' && $value !== '' && !preg_match('/^09[0-9]{9}$/', $value)) wp_die('شماره موبایل باید ۱۱ رقم و با 09 آغاز شود.');
            if (in_array($key, ['company_name', 'store_name', 'contact_person', 'full_name'], true) && $value === '') wp_die('نام اصلی پرونده نمی‌تواند خالی باشد.');
            $previous = $kind === 'cooperation' ? (string) ($data[$key] ?? '') : (string) ($row[$key] ?? '');
            if ($previous === $value) continue;
            $old_values[$key] = $previous;
            $new_values[$key] = $value;
            if ($kind === 'cooperation') $data[$key] = $value;
        }
        if ($new_values) {
            if ($kind === 'cooperation') {
                $new_json = wp_json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
                if ($new_json === false) wp_die('ساختار داده قابل ذخیره نیست.');
                // Optimistic concurrency protection across all DB engines.
                $affected = $wpdb->query($wpdb->prepare(
                    "UPDATE `{$table}` SET data = %s WHERE id = %d AND BINARY data = BINARY %s",
                    $new_json, $id, (string) $row['data']
                ));
            } else {
                // One atomic statement; condition also protects other writers.
                $set = []; $where = []; $params = [];
                foreach ($new_values as $field => $value) { $set[] = "`{$field}` = %s"; $params[] = $value; }
                foreach ($old_values as $field => $value) {
                    if ($row[$field] === null) $where[] = "`{$field}` IS NULL";
                    else { $where[] = "BINARY `{$field}` = BINARY %s"; $params[] = $value; }
                }
                $params[] = $id;
                // Bind SET, THEN WHERE, THEN id.
                $set_params = array_values($new_values);
                $where_params = [];
                foreach ($old_values as $field => $value) if ($row[$field] !== null) $where_params[] = $value;
                $params = array_merge($set_params, [$id], $where_params);
                $affected = $wpdb->query($wpdb->prepare(
                    "UPDATE `{$table}` SET " . implode(', ', $set) . " WHERE id = %d AND " . implode(' AND ', $where), $params
                ));
            }
            if ($affected === 1) {
                mlr_rc_history_add($kind, $id, $old_values, $new_values, $reason);
                if ($kind === 'loan' && function_exists('mlr_add_request_event')) mlr_add_request_event($id, 'admin_profile_correction', 'اصلاح اطلاعات هویتی/تماس؛ دلیل: ' . $reason);
                $notice = 'updated';
            } else $notice = 'conflict';
        } else $notice = 'unchanged';
    }
    wp_safe_redirect(mlr_rc_url($kind, $id, $notice));
    exit;
}

function mlr_rc_page() {
    if (!current_user_can('manage_options')) wp_die('دسترسی غیرمجاز.');
    global $wpdb;
    $kind = isset($_GET['kind']) && sanitize_key(wp_unslash($_GET['kind'])) === 'loan' ? 'loan' : 'cooperation';
    $id = absint($_GET['request_id'] ?? 0);
    $table = $wpdb->prefix . ($kind === 'cooperation' ? 'cooperation_requests' : 'loan_requests');
    $row = $id ? $wpdb->get_row($wpdb->prepare("SELECT * FROM `{$table}` WHERE id = %d", $id), ARRAY_A) : null;
    $notice = sanitize_key(wp_unslash($_GET['mlr_notice'] ?? ''));
    $notices = ['updated' => 'اطلاعات پرونده ذخیره شد.', 'reset' => 'تاریخ تسویه پذیرنده حذف و وضعیت منتظر تعیین تاریخ ثبت شد.', 'unchanged' => 'تغییری نسبت به اطلاعات فعلی وجود نداشت.', 'conflict' => 'هم‌زمان اطلاعات پرونده تغییر کرده یا ذخیره ناموفق بود؛ صفحه را دوباره بارگذاری کنید.', 'error' => 'اصلاح انجام نشد.'];
    ?>
    <div class="wrap" dir="rtl" style="max-width:1120px;margin:24px auto;padding:0 12px">
      <h1>اصلاح اطلاعات پرونده‌ها</h1>
      <p class="description">ویرایش کنترل‌شده برای هر شناسه؛ بدون تغییر قرارداد، OTP، فایل‌ها، وضعیت اعطا یا اطلاعات تسویه بانک. همه تغییرها به نام مدیر ثبت می‌شوند.</p>
      <?php if (isset($notices[$notice])): ?><div class="notice <?php echo $notice === 'conflict' || $notice === 'error' ? 'notice-error' : 'notice-success'; ?>"><p><?php echo esc_html($notices[$notice]); ?></p></div><?php endif; ?>
      <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" style="background:#fff;border:1px solid #dcdcde;border-radius:12px;padding:18px;display:flex;gap:14px;align-items:end;flex-wrap:wrap;margin:22px 0">
        <input type="hidden" name="page" value="mlr-record-corrections">
        <label>نوع درخواست<br><select name="kind" style="min-width:190px"><option value="cooperation" <?php selected($kind, 'cooperation'); ?>>درخواست همکاری</option><option value="loan" <?php selected($kind, 'loan'); ?>>درخواست تسهیلات</option></select></label>
        <label>شناسه پرونده<br><input type="number" min="1" name="request_id" value="<?php echo $id ? esc_attr($id) : ''; ?>" placeholder="مثلاً 128 یا 1563" required></label>
        <button type="submit" class="button button-primary">نمایش پرونده</button>
      </form>
      <?php if ($id && !$row): ?><div class="notice notice-error"><p>شناسه <?php echo esc_html($id); ?> در بخش انتخاب‌شده پیدا نشد.</p></div><?php endif; ?>
      <?php if ($row):
        $fields = mlr_rc_editable_fields($kind, $row);
        $data = $kind === 'cooperation' ? json_decode((string)$row['data'], true) : [];
        $title = $kind === 'cooperation' ? ($data['company_name'] ?? $data['store_name'] ?? '') : ($row['full_name'] ?? '');
        $history = get_option('mlr_rc_history_' . $kind . '_' . $id, []);
        ?>
        <div style="background:#fff;padding:22px;border-radius:12px;border:1px solid #dcdcde;margin-bottom:22px">
          <h2>پرونده #<?php echo esc_html($id); ?> — <?php echo esc_html($title); ?></h2>
          <p>وضعیت: <strong><?php echo esc_html($row['status']); ?></strong> | نوع: <?php echo esc_html($kind === 'cooperation' ? 'همکاری' : 'تسهیلات'); ?></p>
          <?php if ($kind === 'loan'): ?>
            <div class="notice notice-info inline"><p>فقط مشخصات پایه قابل اصلاح هستند. اگر قرارداد این پرونده قبلاً امضا شده، برای تغییر متن یا اطلاعات حقوقی قرارداد از «بازصدور قرارداد» استفاده کنید؛ این فرم امضا یا Snapshot را بازنویسی نمی‌کند.</p></div>
          <?php endif; ?>
          <?php if ($kind === 'cooperation' && ($row['contract_signed_status'] ?? '') === 'signed'): ?>
            <div class="notice notice-warning inline"><p>این درخواست قرارداد همکاری امضاشده دارد. تغییر مشخصات ممکن است در نمایش داینامیک قرارداد تأثیر بگذارد. قبل از اصلاح، نسخه امضاشده را بایگانی و موضوع را تأیید کنید.</p></div>
          <?php endif; ?>
          <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="mlr_rc_save"><input type="hidden" name="mode" value="details">
            <input type="hidden" name="kind" value="<?php echo esc_attr($kind); ?>"><input type="hidden" name="request_id" value="<?php echo esc_attr($id); ?>">
            <?php wp_nonce_field('mlr_rc_save'); ?>
            <?php if ($kind === 'cooperation' && ($row['contract_signed_status'] ?? '') === 'signed'): ?>
              <p><label><input type="checkbox" name="acknowledge_signed" value="1" required> از تغییر احتمالی نمایش اطلاعات در قرارداد همکاری امضاشده آگاه هستم.</label></p>
            <?php endif; ?>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:18px">
            <?php foreach ($fields as $key => $info): $value = $kind === 'cooperation' ? (string)($data[$key] ?? '') : (string)($row[$key] ?? ''); ?>
              <label style="display:block;font-weight:600"><?php echo esc_html($info['label']); ?>
                <?php if ($info['type'] === 'textarea'): ?><textarea name="fields[<?php echo esc_attr($key); ?>]" rows="3" style="width:100%;margin-top:6px;direction:rtl"><?php echo esc_textarea($value); ?></textarea>
                <?php else: ?><input name="fields[<?php echo esc_attr($key); ?>]" type="text" value="<?php echo esc_attr($value); ?>" style="width:100%;margin-top:6px;direction:rtl"> <?php endif; ?>
              </label>
            <?php endforeach; ?>
            </div>
            <p><label>علت اصلاح (الزامی)<br><textarea name="reason" rows="2" required style="width:100%" placeholder="مثلاً اصلاح مشخصات حقوقی اعلام‌شده توسط کارفرما"></textarea></label></p>
            <button type="submit" class="button button-primary" onclick="return confirm('فقط اطلاعات همین شناسه اصلاح شود؟');">ذخیره اطلاعات همین پرونده</button>
          </form>
        </div>
        <?php if ($kind === 'loan'): ?>
          <div style="background:#fff;border:1px solid #dcdcde;border-radius:12px;padding:22px;margin-bottom:22px">
            <h2>بازنشانی تاریخ تسویه بابانوئل با پذیرنده</h2>
            <p>وضعیت فعلی: <strong><?php echo esc_html($row['settlement_status'] ?: '—'); ?></strong> | تاریخ: <strong><?php echo esc_html($row['settlement_date'] ?: '—'); ?></strong> | شناسه سیکل پذیرنده: <strong><?php echo esc_html($row['settlement_cycle_id'] ?: '—'); ?></strong></p>
            <p class="description">فقط فیلدهای <code>settlement_date</code> و <code>settlement_status</code> پرونده را تغییر می‌دهد؛ تسویه بانک با بابانوئل دست‌نخورده می‌ماند. پرونده‌های داخل سیکل تسویه قابل بازنشانی مستقیم نیستند.</p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
              <input type="hidden" name="action" value="mlr_rc_save"><input type="hidden" name="mode" value="reset_settlement"><input type="hidden" name="kind" value="loan"><input type="hidden" name="request_id" value="<?php echo esc_attr($id); ?>">
              <?php wp_nonce_field('mlr_rc_save'); ?>
              <p><label>علت بازنشانی<br><textarea name="reason" rows="2" required style="width:100%"></textarea></label></p>
              <p><label><input type="checkbox" name="acknowledge" value="1" required> تأیید می‌کنم این عملیات مربوط به تسویه بابانوئل با پذیرنده است، نه تسویه بانک.</label></p>
              <button class="button" type="submit" <?php disabled(!empty($row['settlement_cycle_id'])); ?> onclick="return confirm('تاریخ تسویه حذف و وضعیت به منتظر تعیین تاریخ برگردد؟');">حذف تاریخ و بازگشت به منتظر تعیین تاریخ</button>
            </form>
          </div>
        <?php endif; ?>
        <?php if ($history && is_array($history)): ?>
          <div style="background:#fff;border:1px solid #dcdcde;border-radius:12px;padding:20px"><h2>سوابق اصلاحات این شناسه</h2><table class="widefat striped"><thead><tr><th>زمان</th><th>مدیر</th><th>علت</th><th>تغییرات</th></tr></thead><tbody>
          <?php foreach (array_reverse($history) as $item): ?><tr><td><?php echo esc_html($item['time']); ?></td><td><?php echo esc_html($item['admin']); ?></td><td><?php echo esc_html($item['reason']); ?></td><td><?php echo esc_html(implode('، ', array_keys($item['after']))); ?></td></tr><?php endforeach; ?>
          </tbody></table></div>
        <?php endif; ?>
      <?php endif; ?>
    </div>
    <?php
}
