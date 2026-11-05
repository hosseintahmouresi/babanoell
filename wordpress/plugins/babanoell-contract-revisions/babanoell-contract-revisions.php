<?php
/**
 * Plugin Name: Babanoell - Three Contract Revisions
 * Description: Audited, display-only unsigned contract revisions for loan IDs 1348, 1350 and 1352. Preserves signed records and OTP evidence.
 * Version: 1.0.0
 * Author: Ravi Business Development
 * Requires PHP: 7.4
 */
if (!defined('ABSPATH')) { exit; }

final class BCR_Three_Contract_Revisions {
    private const IDS = [1348, 1350, 1352];
    private const PREFIX = 'bcr_three_20261011_';

    public static function boot() {
        foreach (self::IDS as $id) {
            add_filter('option_mlr_lc_sig_' . $id, [__CLASS__, 'filter_signed_option'], 100, 2);
        }
        add_action('admin_menu', [__CLASS__, 'admin_menu']);
        add_action('admin_post_bcr_three_save', [__CLASS__, 'save']);
    }

    public static function filter_signed_option($value, $option) {
        if (!is_array($value) || !get_option(self::PREFIX . 'active', false)) return $value;
        if (!preg_match('/^mlr_lc_sig_(1348|1350|1352)$/D', $option, $m)) return $value;
        $id = (int) $m[1];
        if ((int) ($value['request_id'] ?? 0) !== $id || (int) ($value['plan_id'] ?? 0) !== 34) return $value;
        $revision = get_option(self::PREFIX . 'rendered_' . $id, []);
        if (!is_array($revision) || empty($revision['html']) || empty($revision['sha256'])) return $value;
        if (!hash_equals($revision['sha256'], hash('sha256', $revision['html']))) return $value;
        // The signed option in the database is NOT changed. This is a new, unsigned display revision.
        // Do NOT rewrite contract_digest/signed_at/OTP/tokens or imply the old signature applies.
        $notice = '<div dir="rtl" role="note" style="direction:rtl;margin:12px 0 20px;padding:15px;border:2px solid #9b6700;background:#fff7df;color:#382900;font:700 14px/2 Tahoma,sans-serif;page-break-inside:avoid">'
                . 'نسخه اصلاحی قرارداد — امضای دیجیتال قبلی مربوط به نسخه پیشین است و به متن این نسخه تسری ندارد. این نسخه تا زمان تأیید و امضای مجدد، سند امضاشده محسوب نمی‌شود. '
                . '<span style="white-space:nowrap">شناسه پرونده: ' . $id . '</span>'
                . '</div>';
        $value['contract_html'] = $notice . $revision['html'];
        $value['bcr_revision_unsigned'] = true;
        $value['bcr_revision_at'] = $revision['created_at'] ?? '';
        $value['bcr_original_digest'] = $value['contract_digest'] ?? '';
        return $value;
    }

    private static function load_loan($id) {
        global $wpdb;
        $table = $wpdb->prefix . 'loan_requests';
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM `{$table}` WHERE id = %d", $id));
    }

    private static function load_original_signature($id) {
        // Read the original raw database value, not our presentation filter.
        global $wpdb;
        $raw = $wpdb->get_var($wpdb->prepare(
            "SELECT option_value FROM `{$wpdb->options}` WHERE option_name = %s LIMIT 1",
            'mlr_lc_sig_' . $id
        ));
        return $raw === null ? null : maybe_unserialize($raw);
    }

    private static function source_map($loan, $signature) {
        $form = json_decode((string) ($loan->form_data ?? ''), true);
        if (!is_array($form)) $form = [];
        $map = [];
        foreach ((array) $loan as $key => $value) {
            if (is_scalar($value) && $value !== null && $value !== '' && strtoupper((string) $value) !== 'NULL') {
                $map[$key] = (string) $value;
            }
        }
        foreach ($form as $key => $value) {
            if (is_scalar($value) && $value !== null && $value !== '') {
                $map[$key] = (string) $value;
            }
        }
        // These aliases are unambiguous based on the audited schema.
        if (isset($map['installment_count'])) $map['payment_count'] = $map['installment_count'];
        if (isset($signature['contract_date'])) $map['contract_date'] = (string) $signature['contract_date'];
        if (isset($loan->id)) $map['request_id'] = (string) $loan->id;
        if (!empty($signature['signer_name'])) $map['full_name'] = (string) $signature['signer_name'];
        // Do not invent total_price, due dates, money-in-words or legal document numbers.
        return $map;
    }

    private static function decode_overrides($str) {
        if (trim($str) === '') return [];
        $decoded = json_decode($str, true);
        if (!is_array($decoded) || (count($decoded) > 0 && array_keys($decoded) === range(0, count($decoded) - 1))) return new WP_Error('invalid_json', 'مقادیر تکمیلی باید یک شیء JSON معتبر باشد.');
        foreach ($decoded as $key => $value) {
            if (!preg_match('/^[a-z][a-z0-9_]*$/i', (string) $key) || !is_scalar($value)) {
                return new WP_Error('invalid_json', 'نام یا نوع یکی از فیلدهای JSON معتبر نیست.');
            }
        }
        return $decoded;
    }

    private static function render_one($template, $id, $overrides) {
        $loan = self::load_loan($id);
        $sig = self::load_original_signature($id);
        if (!$loan || !is_array($sig) || (int) ($loan->installment_plan_id ?? 0) !== 34 || (int) ($sig['request_id'] ?? 0) !== $id || (int) ($sig['plan_id'] ?? 0) !== 34) {
            return new WP_Error('data_mismatch', 'پرونده یا رکورد امضا موجود نیست یا متعلق به طرح ۳۴ نیست.');
        }
        $vars = array_merge(self::source_map($loan, $sig), $overrides);
        preg_match_all('/\[([a-z][a-z0-9_]*)\]/i', $template, $found);
        $used = array_unique($found[1]);
        $missing = [];
        $replace = [];
        foreach ($used as $key) {
            if (!array_key_exists($key, $vars) || trim((string) $vars[$key]) === '') {
                $missing[] = $key;
            } else {
                $replace['[' . $key . ']'] = esc_html((string) $vars[$key]);
            }
        }
        return [
            'html' => strtr($template, $replace),
            'missing' => $missing,
            'original_digest' => $sig['contract_digest'] ?? '',
            'original_signed_at' => $sig['signed_at'] ?? '',
            'original_html_hash' => hash('sha256', (string) ($sig['contract_html'] ?? '')),
        ];
    }

    public static function admin_menu() {
        add_management_page('نسخه اصلاحی سه قرارداد', 'اصلاح سه قرارداد', 'manage_options', 'bcr-three-contracts', [__CLASS__, 'admin_page']);
    }

    public static function admin_page() {
        if (!current_user_can('manage_options')) wp_die('دسترسی غیرمجاز');
        $template = (string) get_option(self::PREFIX . 'template', '');
        $active = (bool) get_option(self::PREFIX . 'active', false);
        $messages = get_transient(self::PREFIX . 'notice_' . get_current_user_id());
        delete_transient(self::PREFIX . 'notice_' . get_current_user_id());
        echo '<div class="wrap" dir="rtl" style="direction:rtl;max-width:1180px">';
        echo '<h1>نسخه اصلاحی قراردادها — پرونده‌های ۱۳۴۸، ۱۳۵۰، ۱۳۵۲</h1>';
        echo '<p>رکوردهای امضاشده، اثرانگشت‌ها، تاریخچه OTP و توکن‌ها در دیتابیس دست‌نخورده می‌مانند. فقط نمایش HTML با نسخه اصلاحیِ دارای هشدار «نیازمند امضای مجدد» جایگزین می‌شود. غیرفعال‌سازی، نمایش اصلی را برمی‌گرداند.</p>';
        if ($messages) echo '<div class="notice notice-info"><p>' . esc_html($messages) . '</p></div>';
        echo '<p><strong>وضعیت نمایش:</strong> ' . ($active ? 'نسخه اصلاحی فعال' : 'نسخه اصلی فعال') . '</p>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('bcr_three_save');
        echo '<input type="hidden" name="action" value="bcr_three_save">';
        echo '<h2>۱. HTML کامل قرارداد</h2><p>همان کد قرارداد ارسالی را بدون حذف بخش‌ها در کادر زیر وارد کنید. متغیرهای داخل براکت از داده پرونده یا فیلدهای تکمیلی پر می‌شوند.</p>';
        echo '<textarea name="template" rows="18" style="width:100%;font-family:monospace;direction:ltr;text-align:left" spellcheck="false">' . esc_textarea($template) . '</textarea>';
        echo '<h2>۲. مقادیر اختصاصی هر پرونده (JSON اختیاری)</h2>';
        echo '<p>برخی متغیرها مثل تاریخ پایان، مبلغ به حروف، شماره فاکتور و نرخ سود از بکاپ به‌صورت قطعی قابل استنتاج نیستند. اگر بررسی متغیری را ناقص گزارش کرد، مقدار درست آن را برای هر پرونده در JSON وارد کنید؛ مثال: <code>{"contract_number":"شماره واقعی","invoice_number":"شماره فاکتور واقعی"}</code>.</p>';
        foreach (self::IDS as $id) {
            $sig = self::load_original_signature($id);
            $loan = self::load_loan($id);
            $overrides = get_option(self::PREFIX . 'fields_' . $id, []);
            $overrides = is_array($overrides) ? $overrides : [];
            echo '<section style="border:1px solid #ccd0d4;background:#fff;padding:16px;margin:12px 0">';
            echo '<h3>پرونده ' . $id . '</h3>';
            echo '<p>طرح: ' . esc_html((string) ($loan->installment_plan_id ?? 'یافت نشد')) . ' | امضا: ' . esc_html((string) ($sig['signed_at'] ?? 'یافت نشد')) . '</p>';
            echo '<textarea name="fields[' . $id . ']" rows="4" style="width:100%;direction:ltr;text-align:left;font-family:monospace">' . esc_textarea($overrides ? wp_json_encode($overrides, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) : '{}') . '</textarea>';
            if ($template) {
                $r = self::render_one($template, $id, $overrides);
                if (is_wp_error($r)) echo '<p style="color:#b32d2e">' . esc_html($r->get_error_message()) . '</p>';
                else echo '<p><strong>متغیرهای حل‌نشده:</strong> ' . ($r['missing'] ? esc_html(implode('، ', $r['missing'])) : 'هیچ موردی') . '</p>';
            }
            echo '</section>';
        }
        echo '<p>پس از ذخیره، اگر همه متغیرها مشخص باشند، می‌توانید نمایش نسخه اصلاحی را فعال کنید. این کار هرگز امضای قبلی را به نسخه جدید منتقل نمی‌کند.</p>';
        echo '<p><button class="button button-secondary button-large" name="bcr_operation" value="save">ذخیره و بررسی</button> '; 
        echo '<button class="button button-primary button-large" name="bcr_operation" value="enable">ذخیره و فعال‌سازی نسخه اصلاحی</button> ';
        echo '<button class="button button-secondary button-large" name="bcr_operation" value="disable">بازگرداندن نمایش قراردادهای اصلی</button></p>';
        echo '</form></div>';
    }

    public static function save() {
        if (!current_user_can('manage_options')) wp_die('دسترسی غیرمجاز');
        check_admin_referer('bcr_three_save');
        $op = sanitize_key((string) ($_POST['bcr_operation'] ?? 'save'));
        if ($op === 'disable') {
            update_option(self::PREFIX . 'active', false, false);
            self::redirect('نمایش نسخه‌های اصلی دوباره فعال شد؛ رکوردهای امضاشده تغییری نکردند.');
        }
        $template = isset($_POST['template']) && is_string($_POST['template']) ? wp_unslash($_POST['template']) : '';
        if (strpos($template, 'mlr-contract-document') === false || strlen($template) < 500) {
            self::redirect('کد HTML قرارداد کامل نیست.');
        }
        if (stripos($template, '<script') !== false || stripos($template, '<?') !== false) {
            self::redirect('وجود PHP یا SCRIPT در قالب مجاز نیست.');
        }
        $fields = [];
        foreach (self::IDS as $id) {
            $raw = $_POST['fields'][$id] ?? '{}';
            if (!is_string($raw)) self::redirect('مقدار فیلدهای تکمیلی نامعتبر است.');
            $extra = self::decode_overrides(wp_unslash($raw));
            if (is_wp_error($extra)) self::redirect('JSON پرونده ' . $id . ': ' . $extra->get_error_message());
            $fields[$id] = $extra;
        }
        update_option(self::PREFIX . 'template', $template, false);
        foreach (self::IDS as $id) update_option(self::PREFIX . 'fields_' . $id, $fields[$id], false);
        if ($op === 'save') {
            update_option(self::PREFIX . 'active', false, false);
            self::redirect('قالب و مقادیر تکمیلی ذخیره شدند. وضعیت هر پرونده و متغیرهای حل‌نشده را مشاهده کنید.');
        }
        if ($op !== 'enable') self::redirect('عملیات نامعتبر است.');
        $rendered = [];
        $issues = [];
        foreach (self::IDS as $id) {
            $r = self::render_one($template, $id, $fields[$id]);
            if (is_wp_error($r)) { $issues[] = $id . ': ' . $r->get_error_message(); continue; }
            if ($r['missing']) { $issues[] = $id . ': ' . implode(', ', $r['missing']); continue; }
            $rendered[$id] = [
                'html' => $r['html'],
                'sha256' => hash('sha256', $r['html']),
                'created_at' => current_time('mysql'),
                'original_digest' => $r['original_digest'],
                'original_html_hash' => $r['original_html_hash'],
                'original_signed_at' => $r['original_signed_at'],
                'revision_status' => 'unsigned_requires_new_signature',
            ];
        }
        if ($issues) {
            update_option(self::PREFIX . 'active', false, false);
            self::redirect('فعال نشد؛ ابتدا متغیرهای نامشخص/خطاها را اصلاح کنید: ' . implode(' | ', $issues));
        }
        foreach (self::IDS as $id) update_option(self::PREFIX . 'rendered_' . $id, $rendered[$id], false);
        update_option(self::PREFIX . 'active', true, false);
        self::redirect('نمایش نسخه اصلاحی فقط برای سه شناسه فعال شد. نسخه‌های امضاشده قبلی دست‌نخورده هستند و امضای قبلی به نسخه جدید منتقل نشده است.');
    }

    private static function redirect($message) {
        set_transient(self::PREFIX . 'notice_' . get_current_user_id(), $message, 90);
        wp_safe_redirect(admin_url('tools.php?page=bcr-three-contracts'));
        exit;
    }
}

BCR_Three_Contract_Revisions::boot();
