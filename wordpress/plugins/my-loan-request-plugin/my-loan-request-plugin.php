<?php
/*
Plugin Name: درخواست تسهیلات بابانوئل
Description: افزونه‌ای برای مدیریت درخواست‌های تسهیلات با قابلیت محاسبه اقساط و ثبت-نام پذیرندگان
Version: 8.4.6.90.7
Author: شرکت پدیده تجارت
*/

if (!defined('ABSPATH')) {
    exit;
}

define('MLR_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MLR_PLUGIN_URL', plugin_dir_url(__FILE__));
define('MLR_VERSION', '8.4.6.90.7');


// --- START: MLR Log Path Helpers ---
if (!function_exists('mlr_get_log_file_path')) {
    function mlr_get_log_file_path() {
        // Prefer an explicit private directory. Otherwise use the system temp
        // directory, which is normally outside the public web root.
        $log_dir = defined('MLR_LOG_DIR') && is_string(MLR_LOG_DIR) && trim(MLR_LOG_DIR) !== ''
            ? rtrim(MLR_LOG_DIR, '/\\')
            : trailingslashit(get_temp_dir()) . 'mlr-private-logs';
        return trailingslashit($log_dir) . 'mlr_debug.log';
    }
}

if (!function_exists('mlr_ensure_log_dir')) {
    function mlr_ensure_log_dir() {
        $log_file = mlr_get_log_file_path();
        $log_dir  = dirname($log_file);

        if (!file_exists($log_dir)) {
            wp_mkdir_p($log_dir);
        }

        // Create protection files (best-effort)
        $index_file = trailingslashit($log_dir) . 'index.html';
        if (!file_exists($index_file)) {
            @file_put_contents($index_file, '');
        }

        $htaccess_file = trailingslashit($log_dir) . '.htaccess';
        if (!file_exists($htaccess_file)) {
            @file_put_contents($htaccess_file, "Require all denied\nDeny from all\n");
        }
        $index_php = trailingslashit($log_dir) . 'index.php';
        if (!file_exists($index_php)) {
            @file_put_contents($index_php, "<?php http_response_code(404); exit;\n");
        }
        $web_config = trailingslashit($log_dir) . 'web.config';
        if (!file_exists($web_config)) {
            @file_put_contents($web_config, '<configuration><system.webServer><authorization><deny users="*" /></authorization></system.webServer></configuration>');
        }

        // One-time best-effort migration of the old public uploads log.
        $upload_dir = wp_upload_dir(null, false);
        if (!empty($upload_dir['basedir'])) {
            $legacy_file = trailingslashit($upload_dir['basedir']) . 'mlr-logs/mlr_debug.log';
            if ($legacy_file !== $log_file && is_file($legacy_file)) {
                if (!is_file($log_file)) {
                    @copy($legacy_file, $log_file);
                }
                @unlink($legacy_file);
            }
        }
    }
}
// --- END: MLR Log Path Helpers ---



// --- START: MLR Logging Function ---
if (!function_exists('mlr_log')) {
    function mlr_log($message, $context = null) {
        $log_file = mlr_get_log_file_path();
        mlr_ensure_log_dir();
        $timestamp = date('Y-m-d H:i:s');

        // Many reliability paths pass structured context as a second argument. Keep it for
        // diagnostics, but redact while it is still structured so sensitive keys are removed
        // more reliably than they would be after print_r().
        $payload = $context !== null ? ['message' => $message, 'context' => $context] : $message;
        if (function_exists('mlr_redact_log_content')) {
            $payload = mlr_redact_log_content($payload);
        }

        if (is_array($payload) || is_object($payload)) {
            $log_content = print_r($payload, true);
        } else {
            $log_content = (string) $payload;
        }

        // Startup fallback before hardening.php is loaded.
        if (!function_exists('mlr_redact_log_content')) {
            $log_content = preg_replace('/(password|client_secret|Authorization|api[_-]?key|token|otp)(\'|")?\s*(=>|:)\s*(\'|")?([^\'"\s]+)(\'|")?/i', '$1$2$3$4*****$6', $log_content);
            $log_content = preg_replace('/\b09\d{9}\b/u', '09*******XX', $log_content);
            $log_content = preg_replace('/\b\d{10}\b/u', '**********', $log_content);
        }

        $formatted_message = sprintf("[%s] %s\n", $timestamp, $log_content);
        
        // Add file locking for safer concurrent writes
        $file_handle = @fopen($log_file, 'a');
        if ($file_handle) {
            if (flock($file_handle, LOCK_EX)) {
                fwrite($file_handle, $formatted_message);
                fflush($file_handle);
                flock($file_handle, LOCK_UN);
            }
            fclose($file_handle);
        } else {
             error_log("MLR_Log Error: Could not open log file for writing: " . $log_file);
        }
    }
}
// --- END: MLR Logging Function ---


// --- START: افزودن فیلدهای سفارشی به پروفایل کاربر ---
add_action('show_user_profile', 'mlr_add_custom_user_profile_fields');
add_action('edit_user_profile', 'mlr_add_custom_user_profile_fields');
function mlr_add_custom_user_profile_fields($user) {
    global $wpdb;
    $table_installment_plans = $wpdb->prefix . 'installment_plans';
    $plan_order_sql = function_exists('mlr_get_installment_plan_order_by_sql') ? mlr_get_installment_plan_order_by_sql() : 'ORDER BY id DESC';
    $all_plans = $wpdb->get_results("SELECT id, name, is_active, settings FROM `{$table_installment_plans}` {$plan_order_sql}");
    $disabled_plan_ids = function_exists('mlr_get_user_disabled_installment_plan_ids')
        ? mlr_get_user_disabled_installment_plan_ids($user->ID)
        : [];
    $user_roles = (array) ($user->roles ?? []);
    $show_acceptor_marketplace_fields = current_user_can('manage_options')
        || in_array('acceptor', $user_roles, true)
        || (function_exists('mlr_user_has_role') && mlr_user_has_role($user->ID, 'acceptor'));
    ?>
    <h3>اطلاعات تکمیلی پذیرنده</h3>
    <table class="form-table">
        <tr>
            <th><label for="shebaaa">شماره شبا</label></th>
            <td>
                <input type="text" name="shebaaa" id="shebaaa" value="<?php echo esc_attr(get_the_author_meta('shebaaa', $user->ID)); ?>" class="regular-text ltr" pattern="[0-9]{24}" title="شماره شبا 24 رقمی" />
                <p class="description">شماره شبا ۲۴ رقمی بدون IR</p>
            </td>
        </tr>
        <?php if (current_user_can('manage_options') || current_user_can('edit_user', $user->ID)): ?>
        <?php if (current_user_can('manage_options')): ?>
        <tr>
            <th><label for="acceptor_commission_percent">کمیسیون پذیرنده (%)</label></th>
            <td>
                <input type="number" name="acceptor_commission_percent" id="acceptor_commission_percent" value="<?php echo esc_attr(get_the_author_meta('acceptor_commission_percent', $user->ID)); ?>" class="small-text ltr" step="0.01" min="0" max="100" />
                <p class="description">این مقدار در محاسبه‌گر فرم ثبت‌نام استفاده خواهد شد (پیش‌فرض تنظیمات طرح اعمال می‌شود).</p>
            </td>
        </tr>
        <tr>
            <th><label>طرح‌های غیرفعال برای این پذیرنده</label></th>
            <td>
                <input type="hidden" name="mlr_disabled_installment_plans_field_present" value="1">
                <div style="max-width:680px;padding:12px 14px;border-right:4px solid #00a32a;background:#f0f6fc;border-radius:6px;line-height:1.9;">
                    <strong>قاعده پیش‌فرض:</strong>
                    هر طرحی که در تنظیمات خود طرح «فعال» و برای «پنل پذیرنده» قابل نمایش باشد، به‌صورت خودکار برای همه پذیرنده‌ها فعال است.
                </div>
                <p style="margin:12px 0 0;">فقط طرح‌هایی را تیک بزنید که می‌خواهید <strong style="color:#b32d2e;">برای همین پذیرنده غیرفعال و غیرقابل استفاده</strong> باشند.</p>
                <div style="margin-top:12px;padding:12px;border:1px solid #ccd0d4;border-radius:6px;max-width:680px;background:#fff;">
                    <?php if (empty($all_plans)) : ?>
                        <p style="margin:0;">هنوز هیچ طرحی تعریف نشده است.</p>
                    <?php else : ?>
                        <?php foreach ($all_plans as $plan) : ?>
                            <?php
                                $visible_in_acceptor_panel = function_exists('mlr_is_installment_plan_visible_in_context')
                                    ? mlr_is_installment_plan_visible_in_context($plan, 'mlr_loan_request_form')
                                    : true;
                            ?>
                            <label style="display:block;margin:0 0 9px;line-height:1.8;">
                                <input type="checkbox" name="mlr_disabled_installment_plan_ids[]" value="<?php echo esc_attr($plan->id); ?>" <?php checked(in_array((int) $plan->id, $disabled_plan_ids, true)); ?> />
                                <strong><?php echo esc_html($plan->name); ?></strong>
                                <?php if (!(int) $plan->is_active) : ?>
                                    <span style="color:#856404;">(غیرفعال سراسری)</span>
                                <?php elseif (!$visible_in_acceptor_panel) : ?>
                                    <span style="color:#856404;">(نمایش در پنل پذیرنده غیرفعال است)</span>
                                <?php else : ?>
                                    <span style="color:#008a20;">(فعال برای همه پذیرنده‌ها)</span>
                                <?php endif; ?>
                            </label>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <p class="description">اگر هیچ طرحی تیک نخورد، این پذیرنده تمام طرح‌های فعالِ مخصوص پنل پذیرنده را مشاهده می‌کند. تیک‌زدن یک طرح، آن را در فرم پذیرنده، محاسبه‌گر مرتبط و تمام کنترل‌های سمت سرور برای همین حساب مسدود می‌کند.</p>
            </td>
        </tr>
        <?php endif; ?>
         <tr>
             <th><label for="acceptor_mobile_number">شماره موبایل پذیرنده (برای پیامک)</label></th>
             <td>
                 <input type="text" name="acceptor_mobile_number" id="acceptor_mobile_number" value="<?php echo esc_attr(get_the_author_meta('acceptor_mobile_number', $user->ID)); ?>" class="regular-text ltr" pattern="09[0-9]{9}" title="شماره موبایل 11 رقمی با شروع 09" />
                 <p class="description">این شماره موبایل برای دریافت پیامک‌های مربوط به وضعیت درخواست‌های تسهیلات استفاده خواهد شد. (جایگزین شماره موبایل پیش‌فرض حساب کاربری یا ووکامرس)</p>
             </td>
         </tr>
        <?php if ($show_acceptor_marketplace_fields): ?>
        <?php
            $seller_post_id = function_exists('mlr_get_acceptor_profile_post_id') ? mlr_get_acceptor_profile_post_id($user->ID) : 0;
            $seller_categories = function_exists('mlr_get_acceptor_categories_from_seller_post') ? mlr_get_acceptor_categories_from_seller_post($user->ID) : [];
            $seller_locations = function_exists('mlr_get_acceptor_locations_from_seller_post') ? mlr_get_acceptor_locations_from_seller_post($user->ID) : [];
            $marketplace_enabled = get_user_meta($user->ID, 'mlr_acceptor_marketplace_enabled', true);
            $seller_post_type = function_exists('mlr_get_acceptor_cpt_slug') ? mlr_get_acceptor_cpt_slug() : 'seller';
            $seller_post_type_label = $seller_post_type;
            $seller_post_type_object = post_type_exists($seller_post_type) ? get_post_type_object($seller_post_type) : null;
            if ($seller_post_type_object) {
                $seller_post_type_label = $seller_post_type_object->labels->singular_name ?: $seller_post_type_object->label ?: $seller_post_type;
            }
        ?>
        <tr>
            <th><label for="mlr_acceptor_marketplace_enabled">بازار درخواست‌های متقاضی</label></th>
            <td>
                <input type="hidden" name="mlr_acceptor_marketplace_fields_present" value="1">
                <label>
                    <input type="checkbox" name="mlr_acceptor_marketplace_enabled" id="mlr_acceptor_marketplace_enabled" value="1" <?php checked($marketplace_enabled !== '0'); ?>>
                    نمایش درخواست‌های آزاد متناسب با این پذیرنده فعال باشد
                </label>
            </td>
        </tr>
        <tr>
            <th><label for="mlr_acceptor_shop_name">نام نمایشی فروشگاه/پذیرنده</label></th>
            <td>
                <input type="text" name="mlr_acceptor_shop_name" id="mlr_acceptor_shop_name" value="<?php echo esc_attr(get_user_meta($user->ID, 'mlr_acceptor_shop_name', true)); ?>" class="regular-text">
            </td>
        </tr>
        <tr>
            <th>پروفایل seller متصل</th>
            <td>
                <?php if (current_user_can('manage_options')): ?>
                    <?php wp_nonce_field('mlr_acceptor_profile_post_save_' . $user->ID, 'mlr_acceptor_profile_post_nonce'); ?>
                    <div class="mlr-seller-profile-picker">
                        <input type="hidden" name="mlr_acceptor_profile_post_id" class="mlr-seller-profile-post-id" value="<?php echo esc_attr($seller_post_id); ?>">
                        <div class="mlr-seller-profile-selected">
                            <?php if ($seller_post_id > 0): ?>
                                <strong>پروفایل انتخاب‌شده:</strong>
                                <a href="<?php echo esc_url(get_edit_post_link($seller_post_id)); ?>" target="_blank" rel="noopener">
                                    #<?php echo esc_html($seller_post_id); ?> - <?php echo esc_html(get_the_title($seller_post_id)); ?>
                                </a>
                            <?php else: ?>
                                <strong>پروفایل انتخاب‌شده:</strong> هیچ پروفایلی انتخاب نشده است.
                            <?php endif; ?>
                        </div>
                        <div class="mlr-seller-profile-search-wrap">
                            <input type="search" class="regular-text mlr-seller-profile-search" placeholder="جستجو در <?php echo esc_attr($seller_post_type_label); ?>ها بر اساس نام یا شناسه..." autocomplete="off">
                            <button type="button" class="button mlr-clear-seller-profile">حذف اتصال</button>
                            <div class="mlr-seller-profile-results" hidden></div>
                        </div>
                        <p class="description">از اینجا یکی از پست‌های CPT <?php echo esc_html($seller_post_type); ?> را به این حساب پذیرنده وصل کنید. حوزه فعالیت و موقعیت همچنان از taxonomyهای همان پست خوانده می‌شود.</p>
                    </div>
                <?php endif; ?>
                <?php if ($seller_post_id > 0): ?>
                    <p style="margin-top:0;"><a href="<?php echo esc_url(get_edit_post_link($seller_post_id)); ?>">#<?php echo esc_html($seller_post_id); ?> - <?php echo esc_html(get_the_title($seller_post_id)); ?></a></p>
                    <p class="description">حوزه فعالیت: <?php echo esc_html(!empty($seller_categories) ? implode('، ', wp_list_pluck($seller_categories, 'name')) : 'تعریف نشده'); ?></p>
                    <p class="description">موقعیت: <?php echo esc_html(!empty($seller_locations) ? implode('، ', wp_list_pluck($seller_locations, 'name')) : 'تعریف نشده'); ?></p>
                <?php else: ?>
                    <p style="margin-top:0;color:#b32d2e;">پست seller متصل به این پذیرنده پیدا نشد.</p>
                <?php endif; ?>
                <p class="description">حوزه فعالیت و موقعیت از taxonomyهای تنظیم‌شده در پست seller خوانده می‌شود.</p>
            </td>
        </tr>
        <tr>
            <th><label for="mlr_acceptor_max_facility_amount">حداکثر مبلغ تسهیلات قابل پذیرش</label></th>
            <td>
                <input type="number" name="mlr_acceptor_max_facility_amount" id="mlr_acceptor_max_facility_amount" value="<?php echo esc_attr(get_user_meta($user->ID, 'mlr_acceptor_max_facility_amount', true)); ?>" class="regular-text ltr" min="0" step="1">
                <p class="description">اختیاری؛ صفر یا خالی یعنی بدون محدودیت مبلغ.</p>
            </td>
        </tr>
        <?php endif; ?>
        <?php endif; ?>
    </table>
    <?php
}

add_action('personal_options_update', 'mlr_save_custom_user_profile_fields');
add_action('edit_user_profile_update', 'mlr_save_custom_user_profile_fields');
function mlr_save_custom_user_profile_fields($user_id) {
    if (!current_user_can('edit_user', $user_id)) {
        return false;
    }

    if (isset($_POST['shebaaa'])) {
        $sheba = preg_replace('/[^0-9]/', '', $_POST['shebaaa']);
        if (strlen($sheba) === 24) {
            update_user_meta($user_id, 'shebaaa', $sheba);
        } else {
             delete_user_meta($user_id, 'shebaaa');
        }
    }

    if (current_user_can('manage_options') && isset($_POST['acceptor_commission_percent'])) {
        $commission_value = sanitize_text_field($_POST['acceptor_commission_percent']);
        if (is_numeric($commission_value) && $commission_value >= 0 && $commission_value <= 100) {
             update_user_meta($user_id, 'acceptor_commission_percent', $commission_value);
        } else {
             delete_user_meta($user_id, 'acceptor_commission_percent');
        }
    }

    if (current_user_can('manage_options') && isset($_POST['mlr_disabled_installment_plans_field_present'])) {
        // مدل جدید دسترسی طرح‌ها، deny-list است: طرح‌های فعال برای همه پذیرنده‌ها مجازند
        // و فقط شناسه‌های ذخیره‌شده در این متا برای همان پذیرنده مسدود می‌شوند.
        $disabled_plan_ids = isset($_POST['mlr_disabled_installment_plan_ids']) && is_array($_POST['mlr_disabled_installment_plan_ids'])
            ? array_values(array_unique(array_filter(array_map('absint', wp_unslash($_POST['mlr_disabled_installment_plan_ids'])))))
            : [];

        if (!empty($disabled_plan_ids)) {
            update_user_meta($user_id, 'mlr_disabled_installment_plan_ids', $disabled_plan_ids);
        } else {
            delete_user_meta($user_id, 'mlr_disabled_installment_plan_ids');
        }

        // پاک‌سازی کلیدهای قدیمی پس از اولین ذخیره پروفایل با نسخه جدید.
        delete_user_meta($user_id, 'mlr_allowed_installment_plan_ids');
        delete_user_meta($user_id, 'mlr_limit_installment_plans');
    }
    
    if (isset($_POST['acceptor_mobile_number'])) {
        $mobile = preg_replace('/[^0-9]/', '', $_POST['acceptor_mobile_number']);
         if (preg_match('/^09\d{9}$/', $mobile)) {
            update_user_meta($user_id, 'acceptor_mobile_number', $mobile);
        } else {
             delete_user_meta($user_id, 'acceptor_mobile_number');
        }
    }

    if (isset($_POST['mlr_acceptor_marketplace_fields_present'])) {
        update_user_meta($user_id, 'mlr_acceptor_marketplace_enabled', isset($_POST['mlr_acceptor_marketplace_enabled']) ? '1' : '0');
        update_user_meta($user_id, 'mlr_acceptor_shop_name', sanitize_text_field(wp_unslash($_POST['mlr_acceptor_shop_name'] ?? '')));

        if (current_user_can('manage_options') && isset($_POST['mlr_acceptor_profile_post_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['mlr_acceptor_profile_post_nonce'])), 'mlr_acceptor_profile_post_save_' . $user_id)) {
            $profile_post_id = isset($_POST['mlr_acceptor_profile_post_id']) ? absint($_POST['mlr_acceptor_profile_post_id']) : 0;
            mlr_connect_acceptor_user_to_seller_profile($user_id, $profile_post_id);
        }

        $max_facility_amount = isset($_POST['mlr_acceptor_max_facility_amount'])
            ? preg_replace('/[^0-9.]/', '', wp_unslash($_POST['mlr_acceptor_max_facility_amount']))
            : '';
        if ($max_facility_amount !== '' && (float) $max_facility_amount > 0) {
            update_user_meta($user_id, 'mlr_acceptor_max_facility_amount', (string) (float) $max_facility_amount);
        } else {
            delete_user_meta($user_id, 'mlr_acceptor_max_facility_amount');
        }
    }
}

add_action('user_profile_update_errors', 'mlr_validate_acceptor_profile_post_field', 10, 3);
function mlr_validate_acceptor_profile_post_field($errors, $update, $user) {
    if (!current_user_can('manage_options') || empty($_POST['mlr_acceptor_profile_post_nonce']) || empty($user->ID)) {
        return;
    }

    if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['mlr_acceptor_profile_post_nonce'])), 'mlr_acceptor_profile_post_save_' . absint($user->ID))) {
        $errors->add('mlr_acceptor_profile_post_nonce', 'اعتبارسنجی اتصال پروفایل seller ناموفق بود.');
        return;
    }

    $profile_post_id = isset($_POST['mlr_acceptor_profile_post_id']) ? absint($_POST['mlr_acceptor_profile_post_id']) : 0;
    if ($profile_post_id <= 0) {
        return;
    }

    $post_type = function_exists('mlr_get_acceptor_cpt_slug') ? mlr_get_acceptor_cpt_slug() : 'seller';
    if (!post_type_exists($post_type) || get_post_type($profile_post_id) !== $post_type) {
        $errors->add('mlr_acceptor_profile_post_invalid', 'پروفایل انتخاب‌شده از نوع CPT پذیرنده نیست.');
    }
}

function mlr_get_seller_profile_explicit_owner_id($post_id) {
    $post_id = absint($post_id);
    if ($post_id <= 0) {
        return 0;
    }

    foreach ([
        'acceptor_user_id',
        '_acceptor_user_id',
        'mlr_acceptor_user_id',
        '_mlr_acceptor_user_id',
        'seller_user_id',
        '_seller_user_id',
        'user_id',
        '_user_id',
        'vendor_id',
        '_vendor_id',
    ] as $meta_key) {
        $owner_id = absint(get_post_meta($post_id, $meta_key, true));
        if ($owner_id > 0 && get_userdata($owner_id)) {
            return $owner_id;
        }
    }

    $users = get_users([
        'meta_key' => 'mlr_acceptor_profile_post_id',
        'meta_value' => $post_id,
        'number' => 1,
        'fields' => 'ID',
    ]);

    return !empty($users[0]) ? absint($users[0]) : 0;
}

function mlr_connect_acceptor_user_to_seller_profile($user_id, $post_id) {
    $user_id = absint($user_id);
    $post_id = absint($post_id);
    if ($user_id <= 0 || !get_userdata($user_id)) {
        return false;
    }

    $post_type = function_exists('mlr_get_acceptor_cpt_slug') ? mlr_get_acceptor_cpt_slug() : 'seller';
    if ($post_id > 0 && (!post_type_exists($post_type) || get_post_type($post_id) !== $post_type)) {
        return false;
    }

    $old_post_ids = array_filter(array_unique(array_map('absint', [
        get_user_meta($user_id, 'mlr_acceptor_profile_post_id', true),
        function_exists('mlr_get_acceptor_profile_post_id') ? mlr_get_acceptor_profile_post_id($user_id) : 0,
    ])));

    foreach ($old_post_ids as $old_post_id) {
        if ($old_post_id === $post_id) {
            continue;
        }
        foreach ([
            'acceptor_user_id',
            '_acceptor_user_id',
            'mlr_acceptor_user_id',
            '_mlr_acceptor_user_id',
            'seller_user_id',
            '_seller_user_id',
            'user_id',
            '_user_id',
            'vendor_id',
            '_vendor_id',
        ] as $meta_key) {
            if (absint(get_post_meta($old_post_id, $meta_key, true)) === $user_id) {
                delete_post_meta($old_post_id, $meta_key);
            }
        }
    }

    if ($post_id <= 0) {
        delete_user_meta($user_id, 'mlr_acceptor_profile_post_id');
        return true;
    }

    $previous_users = get_users([
        'meta_key' => 'mlr_acceptor_profile_post_id',
        'meta_value' => $post_id,
        'fields' => 'ID',
        'number' => 50,
    ]);
    foreach ($previous_users as $previous_user_id) {
        $previous_user_id = absint($previous_user_id);
        if ($previous_user_id > 0 && $previous_user_id !== $user_id) {
            delete_user_meta($previous_user_id, 'mlr_acceptor_profile_post_id');
        }
    }

    update_user_meta($user_id, 'mlr_acceptor_profile_post_id', $post_id);
    update_post_meta($post_id, 'acceptor_user_id', $user_id);
    update_post_meta($post_id, 'mlr_acceptor_user_id', $user_id);

    return true;
}

add_action('wp_ajax_mlr_search_acceptor_seller_posts', 'mlr_search_acceptor_seller_posts_callback');
function mlr_search_acceptor_seller_posts_callback() {
    check_ajax_referer('mlr_acceptor_seller_profile_picker', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'دسترسی غیرمجاز.']);
    }

    $post_type = function_exists('mlr_get_acceptor_cpt_slug') ? mlr_get_acceptor_cpt_slug() : 'seller';
    if (!post_type_exists($post_type)) {
        wp_send_json_error(['message' => 'CPT پذیرنده پیدا نشد.']);
    }

    $term = isset($_POST['term']) ? trim(sanitize_text_field(wp_unslash($_POST['term']))) : '';
    $posts = [];

    if (ctype_digit($term)) {
        $exact_post = get_post(absint($term));
        if ($exact_post && $exact_post->post_type === $post_type) {
            $posts[$exact_post->ID] = $exact_post;
        }
    }

    $query_posts = get_posts([
        'post_type' => $post_type,
        'post_status' => ['publish', 'private', 'draft', 'pending'],
        'posts_per_page' => 20,
        's' => $term,
        'orderby' => 'title',
        'order' => 'ASC',
    ]);

    foreach ($query_posts as $post) {
        $posts[$post->ID] = $post;
    }

    $results = [];
    foreach ($posts as $post) {
        $owner_id = mlr_get_seller_profile_explicit_owner_id($post->ID);
        $owner_label = '';
        $status_object = get_post_status_object($post->post_status);
        if ($owner_id > 0) {
            $owner = get_userdata($owner_id);
            $owner_label = $owner ? ($owner->display_name . ' (#' . $owner_id . ')') : ('#' . $owner_id);
        }

        $results[] = [
            'id' => absint($post->ID),
            'title' => get_the_title($post),
            'status' => $status_object ? $status_object->label : $post->post_status,
            'owner' => $owner_label,
            'edit_url' => get_edit_post_link($post->ID, ''),
        ];
    }

    wp_send_json_success(['results' => $results]);
}

add_action('admin_enqueue_scripts', 'mlr_enqueue_acceptor_profile_picker_assets');
function mlr_enqueue_acceptor_profile_picker_assets($hook) {
    if (!in_array($hook, ['profile.php', 'user-edit.php'], true) || !current_user_can('manage_options')) {
        return;
    }

    wp_enqueue_script('jquery');
    wp_add_inline_style('common', '
        .mlr-seller-profile-picker { max-width: 760px; }
        .mlr-seller-profile-selected { margin: 0 0 10px; padding: 10px 12px; background: #fff; border: 1px solid #dcdcde; border-radius: 4px; }
        .mlr-seller-profile-search-wrap { position: relative; display: flex; gap: 8px; align-items: flex-start; max-width: 760px; }
        .mlr-seller-profile-search-wrap .mlr-seller-profile-search { flex: 1; max-width: 520px; }
        .mlr-seller-profile-results { position: absolute; z-index: 10000; top: 34px; right: 0; left: 96px; max-height: 280px; overflow: auto; background: #fff; border: 1px solid #8c8f94; box-shadow: 0 8px 18px rgba(0,0,0,.12); border-radius: 4px; }
        .mlr-seller-profile-result { display: block; width: 100%; padding: 10px 12px; border: 0; border-bottom: 1px solid #f0f0f1; background: #fff; text-align: right; cursor: pointer; }
        .mlr-seller-profile-result:hover { background: #f6f7f7; }
        .mlr-seller-profile-result small { display: block; color: #646970; margin-top: 3px; }
    ');

    $config = [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('mlr_acceptor_seller_profile_picker'),
        'noResults' => 'پروفایلی پیدا نشد.',
        'loading' => 'در حال جستجو...',
        'selectedPrefix' => 'پروفایل انتخاب‌شده:',
        'emptySelected' => 'هیچ پروفایلی انتخاب نشده است.',
    ];

    wp_add_inline_script('jquery', 'window.mlrSellerProfilePicker = ' . wp_json_encode($config, JSON_UNESCAPED_UNICODE) . ';', 'before');
    wp_add_inline_script('jquery', <<<'JS'
jQuery(function($) {
    const cfg = window.mlrSellerProfilePicker || {};
    let searchTimer = null;

    function renderSelected($picker, item) {
        const selected = $picker.find('.mlr-seller-profile-selected').empty();
        $('<strong/>').text((cfg.selectedPrefix || 'پروفایل انتخاب‌شده:') + ' ').appendTo(selected);
        if (item && item.id) {
            const label = '#' + item.id + ' - ' + (item.title || '');
            if (item.edit_url) {
                $('<a/>', { href: item.edit_url, target: '_blank', rel: 'noopener' }).text(label).appendTo(selected);
            } else {
                selected.append(document.createTextNode(label));
            }
        } else {
            selected.append(document.createTextNode(cfg.emptySelected || 'هیچ پروفایلی انتخاب نشده است.'));
        }
    }

    function renderResults($picker, items) {
        const box = $picker.find('.mlr-seller-profile-results').empty().prop('hidden', false);
        if (!items || !items.length) {
            $('<div/>', { class: 'mlr-seller-profile-result' }).text(cfg.noResults || 'پروفایلی پیدا نشد.').appendTo(box);
            return;
        }
        items.forEach(function(item) {
            const button = $('<button/>', {
                type: 'button',
                class: 'mlr-seller-profile-result'
            }).data('seller-profile', item);
            $('<strong/>').text('#' + item.id + ' - ' + (item.title || '')).appendTo(button);
            const meta = [];
            if (item.status) meta.push(item.status);
            if (item.owner) meta.push('متصل به: ' + item.owner);
            if (meta.length) $('<small/>').text(meta.join(' | ')).appendTo(button);
            button.appendTo(box);
        });
    }

    function searchSellerProfiles($picker, term) {
        const box = $picker.find('.mlr-seller-profile-results').empty().prop('hidden', false);
        $('<div/>', { class: 'mlr-seller-profile-result' }).text(cfg.loading || 'در حال جستجو...').appendTo(box);
        $.post(cfg.ajaxUrl, {
            action: 'mlr_search_acceptor_seller_posts',
            nonce: cfg.nonce,
            term: term || ''
        }).done(function(response) {
            if (response && response.success) {
                renderResults($picker, response.data.results || []);
            } else {
                renderResults($picker, []);
            }
        }).fail(function() {
            renderResults($picker, []);
        });
    }

    $(document).on('focus input', '.mlr-seller-profile-search', function() {
        const input = $(this);
        const picker = input.closest('.mlr-seller-profile-picker');
        clearTimeout(searchTimer);
        searchTimer = setTimeout(function() {
            searchSellerProfiles(picker, input.val());
        }, 250);
    });

    $(document).on('click', '.mlr-seller-profile-result', function() {
        const item = $(this).data('seller-profile');
        if (!item || !item.id) return;
        const picker = $(this).closest('.mlr-seller-profile-picker');
        picker.find('.mlr-seller-profile-post-id').val(item.id);
        picker.find('.mlr-seller-profile-search').val('');
        picker.find('.mlr-seller-profile-results').prop('hidden', true).empty();
        renderSelected(picker, item);
    });

    $(document).on('click', '.mlr-clear-seller-profile', function() {
        const picker = $(this).closest('.mlr-seller-profile-picker');
        picker.find('.mlr-seller-profile-post-id').val('0');
        picker.find('.mlr-seller-profile-search').val('');
        picker.find('.mlr-seller-profile-results').prop('hidden', true).empty();
        renderSelected(picker, null);
    });

    $(document).on('click', function(event) {
        if ($(event.target).closest('.mlr-seller-profile-picker').length) return;
        $('.mlr-seller-profile-results').prop('hidden', true);
    });
});
JS
    );
}
// --- END: افزودن فیلدهای سفارشی ---


add_action('admin_init', 'mlr_check_and_update_database_schema');
function mlr_check_and_update_database_schema() {
    global $wpdb;
    $table_requests = $wpdb->prefix . 'loan_requests';
    $table_cooperation = $wpdb->prefix . 'cooperation_requests';
    $table_messages = $wpdb->prefix . 'mlr_request_messages';
    $table_plans = $wpdb->prefix . 'installment_plans';
    $version_option_name = 'mlr_db_version';
    $current_db_version = '2.9'; // 2.9: bank expert completion visibility + isolated bank settlement tracking
    $installed_ver = get_option($version_option_name);
    $plan_order_backfill_ok = true;
    $identity_repair_ok = true;

    // Compatibility migration only while the schema version is behind; never run DDL on every admin request.
    if ($installed_ver != $current_db_version && $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_requests)) === $table_requests) {
        $col = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM `{$table_requests}` LIKE %s", 'facility_amount'));
        if (empty($col)) {
            $ok = $wpdb->query("ALTER TABLE `{$table_requests}` ADD COLUMN `facility_amount` DECIMAL(15,2) DEFAULT NULL AFTER `loan_amount`");
            if ($ok !== false && function_exists('mlr_log')) {
                mlr_log("Added missing column facility_amount to {$table_requests}");
            }
        }

        $document_receipt_col = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM `{$table_requests}` LIKE %s", 'document_receipt_status'));
        if (empty($document_receipt_col)) {
            $ok = $wpdb->query("ALTER TABLE `{$table_requests}` ADD COLUMN `document_receipt_status` varchar(30) NOT NULL DEFAULT 'بررسی نشده' AFTER `settlement_status`");
            if ($ok !== false && function_exists('mlr_log')) {
                mlr_log("Added missing column document_receipt_status to {$table_requests}");
            }
        }
        $transfer_receipt_col = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM `{$table_requests}` LIKE %s", 'transfer_receipt_files'));
        if (empty($transfer_receipt_col)) {
            $ok = $wpdb->query("ALTER TABLE `{$table_requests}` ADD COLUMN `transfer_receipt_files` LONGTEXT DEFAULT NULL");
            if ($ok !== false && function_exists('mlr_log')) {
                mlr_log("Added missing column transfer_receipt_files to {$table_requests}");
            }
        }

        $document_received_date_col = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM `{$table_requests}` LIKE %s", 'document_received_date'));
        if (empty($document_received_date_col)) {
            $ok = $wpdb->query("ALTER TABLE `{$table_requests}` ADD COLUMN `document_received_date` DATETIME DEFAULT NULL AFTER `document_receipt_status`");
            if ($ok !== false && function_exists('mlr_log')) {
                mlr_log("Added missing column document_received_date to {$table_requests}");
            }
        }
        $financier_payment_status_col = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM `{$table_requests}` LIKE %s", 'financier_payment_status'));
        if (empty($financier_payment_status_col)) {
            $ok = $wpdb->query("ALTER TABLE `{$table_requests}` ADD COLUMN `financier_payment_status` varchar(30) NOT NULL DEFAULT 'بررسی نشده' AFTER `transfer_receipt_files`");
            if ($ok !== false && function_exists('mlr_log')) {
                mlr_log("Added missing column financier_payment_status to {$table_requests}");
            }
        }

        $applicant_col = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM `{$table_requests}` LIKE %s", 'applicant_user_id'));
        if (empty($applicant_col)) {
            $wpdb->query("ALTER TABLE `{$table_requests}` ADD COLUMN `applicant_user_id` BIGINT(20) UNSIGNED DEFAULT NULL AFTER `user_id`");
        }
        $acceptor_col = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM `{$table_requests}` LIKE %s", 'acceptor_user_id'));
        if (empty($acceptor_col)) {
            $wpdb->query("ALTER TABLE `{$table_requests}` ADD COLUMN `acceptor_user_id` BIGINT(20) UNSIGNED DEFAULT NULL AFTER `applicant_user_id`");
        }
        $origin_col = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM `{$table_requests}` LIKE %s", 'request_origin'));
        if (empty($origin_col)) {
            $wpdb->query("ALTER TABLE `{$table_requests}` ADD COLUMN `request_origin` VARCHAR(30) NOT NULL DEFAULT 'acceptor_portal' AFTER `acceptor_user_id`");
        }
        $marketplace_columns = [
            'acceptor_assignment_mode' => "VARCHAR(30) DEFAULT '' AFTER `request_origin`",
            'marketplace_status' => "VARCHAR(30) DEFAULT '' AFTER `acceptor_assignment_mode`",
            'marketplace_claimed_by' => "BIGINT(20) UNSIGNED DEFAULT NULL AFTER `marketplace_status`",
            'marketplace_claimed_at' => "DATETIME DEFAULT NULL AFTER `marketplace_claimed_by`",
            'requested_service_scope' => "VARCHAR(30) DEFAULT '' AFTER `marketplace_claimed_at`",
            'requested_cities' => "LONGTEXT DEFAULT NULL AFTER `requested_service_scope`",
            'requested_location_terms' => "LONGTEXT DEFAULT NULL AFTER `requested_cities`",
            'requested_activity_categories' => "LONGTEXT DEFAULT NULL AFTER `requested_location_terms`",
            'request_public_summary' => "TEXT DEFAULT NULL AFTER `requested_activity_categories`",
            'acceptor_invitation_code' => "VARCHAR(100) DEFAULT NULL AFTER `request_public_summary`",
            'acceptor_invited_by' => "BIGINT(20) UNSIGNED DEFAULT NULL AFTER `acceptor_invitation_code`",
            'acceptor_invitation_locked' => "TINYINT(1) DEFAULT 0 AFTER `acceptor_invited_by`",
        ];
        foreach ($marketplace_columns as $column_name => $column_definition) {
            $column_exists = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM `{$table_requests}` LIKE %s", $column_name));
            if (empty($column_exists)) {
                $wpdb->query("ALTER TABLE `{$table_requests}` ADD COLUMN `{$column_name}` {$column_definition}");
            }
        }
        $wpdb->query("UPDATE `{$table_requests}` SET `request_origin` = 'acceptor_portal' WHERE `request_origin` IS NULL OR TRIM(`request_origin`) = ''");
        if (!$wpdb->get_results($wpdb->prepare("SHOW INDEX FROM `{$table_requests}` WHERE Key_name = %s", 'idx_applicant_user_id'))) {
            $wpdb->query("ALTER TABLE `{$table_requests}` ADD INDEX `idx_applicant_user_id` (`applicant_user_id`);");
        }
        if (!$wpdb->get_results($wpdb->prepare("SHOW INDEX FROM `{$table_requests}` WHERE Key_name = %s", 'idx_acceptor_user_id'))) {
            $wpdb->query("ALTER TABLE `{$table_requests}` ADD INDEX `idx_acceptor_user_id` (`acceptor_user_id`);");
        }
        if (!$wpdb->get_results($wpdb->prepare("SHOW INDEX FROM `{$table_requests}` WHERE Key_name = %s", 'idx_request_origin'))) {
            $wpdb->query("ALTER TABLE `{$table_requests}` ADD INDEX `idx_request_origin` (`request_origin`);");
        }
        foreach ([
            'idx_acceptor_assignment_mode' => 'acceptor_assignment_mode',
            'idx_marketplace_status' => 'marketplace_status',
            'idx_marketplace_claimed_by' => 'marketplace_claimed_by',
            'idx_acceptor_invitation_code' => 'acceptor_invitation_code',
            'idx_acceptor_invited_by' => 'acceptor_invited_by',
        ] as $index_name => $column_name) {
            if (!$wpdb->get_results($wpdb->prepare("SHOW INDEX FROM `{$table_requests}` WHERE Key_name = %s", $index_name))) {
                $wpdb->query("ALTER TABLE `{$table_requests}` ADD INDEX `{$index_name}` (`{$column_name}`);");
            }
        }
    }

    if ($installed_ver != $current_db_version) {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        // --- Loan Requests Table Updates ---
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_requests)) == $table_requests) {
             $existing_columns_requests = $wpdb->get_col("DESC `{$table_requests}`", 0);            // expert_note column retained for backward compatibility            // bank_note column retained for backward compatibility

             $required_columns_requests = [
                 'applicant_user_id' => "BIGINT(20) UNSIGNED DEFAULT NULL AFTER `user_id`",
                 'acceptor_user_id' => "BIGINT(20) UNSIGNED DEFAULT NULL AFTER `applicant_user_id`",
                 'request_origin' => "VARCHAR(30) NOT NULL DEFAULT 'acceptor_portal' AFTER `acceptor_user_id`",
                 'acceptor_assignment_mode' => "VARCHAR(30) DEFAULT '' AFTER `request_origin`",
                 'marketplace_status' => "VARCHAR(30) DEFAULT '' AFTER `acceptor_assignment_mode`",
                 'marketplace_claimed_by' => "BIGINT(20) UNSIGNED DEFAULT NULL AFTER `marketplace_status`",
                 'marketplace_claimed_at' => "DATETIME DEFAULT NULL AFTER `marketplace_claimed_by`",
                 'requested_service_scope' => "VARCHAR(30) DEFAULT '' AFTER `marketplace_claimed_at`",
                 'requested_cities' => "LONGTEXT DEFAULT NULL AFTER `requested_service_scope`",
                 'requested_location_terms' => "LONGTEXT DEFAULT NULL AFTER `requested_cities`",
                 'requested_activity_categories' => "LONGTEXT DEFAULT NULL AFTER `requested_location_terms`",
                 'request_public_summary' => "TEXT DEFAULT NULL AFTER `requested_activity_categories`",
                 'acceptor_invitation_code' => "VARCHAR(100) DEFAULT NULL AFTER `request_public_summary`",
                 'acceptor_invited_by' => "BIGINT(20) UNSIGNED DEFAULT NULL AFTER `acceptor_invitation_code`",
                 'acceptor_invitation_locked' => "TINYINT(1) DEFAULT 0 AFTER `acceptor_invited_by`",
                 'phone_number' => "varchar(20) DEFAULT '' NOT NULL AFTER `mobile_number`",
                 'birth_date' => "date DEFAULT NULL AFTER `phone_number`",
                 'occupation' => "varchar(100) DEFAULT '' NOT NULL AFTER `birth_date`",
                 'residential_address' => "text AFTER `occupation`",
                 'postal_code' => "varchar(10) DEFAULT '' NOT NULL AFTER `residential_address`",
                 'work_address' => "text AFTER `postal_code`",
                 'product_name' => "text AFTER `work_address`",
                 'comments' => "TEXT DEFAULT NULL",
                 'facility_amount' => "DECIMAL(15,2) DEFAULT NULL AFTER `loan_amount`",
                 'prepayment' => "DECIMAL(15,2) DEFAULT 0 AFTER `facility_amount`",
                 'installment_amount' => "DECIMAL(15,2) DEFAULT 0 AFTER `installment_count`",
                 'total_payment' => "DECIMAL(15,2) DEFAULT 0 AFTER `installment_amount`",
                 'profit' => "DECIMAL(15,2) DEFAULT 0 AFTER `total_payment`",
                 'wallet_charge' => "DECIMAL(15,2) DEFAULT 0 AFTER `prepayment`",
                 'acceptor_payment_amount' => "DECIMAL(15,2) DEFAULT 0 AFTER `wallet_charge`",
                 'final_settlement_amount' => "DECIMAL(15,2) DEFAULT 0 AFTER `acceptor_payment_amount`",
                 'leasing_deposit_amount' => "DECIMAL(15,2) DEFAULT NULL AFTER `final_settlement_amount`",
                 'document_receipt_status' => "varchar(30) NOT NULL DEFAULT 'بررسی نشده' AFTER `settlement_status`",
                 'document_received_date' => "DATETIME DEFAULT NULL AFTER `document_receipt_status`",
                 'assigned_bank_user_id' => "bigint(20) UNSIGNED DEFAULT NULL AFTER `document_receipt_status`",
                 'settlement_cycle_id' => "bigint(20) UNSIGNED DEFAULT NULL AFTER `assigned_bank_user_id`",
                 'settlement_date' => "datetime DEFAULT NULL AFTER `settlement_cycle_id`",
                 'bank_settlement_status' => "varchar(30) NOT NULL DEFAULT '' AFTER `settlement_date`",
                 'bank_settlement_note' => "TEXT DEFAULT NULL AFTER `bank_settlement_status`",
                 'bank_settled_at' => "DATETIME DEFAULT NULL AFTER `bank_settlement_note`",
                 'bank_settled_by' => "bigint(20) UNSIGNED DEFAULT NULL AFTER `bank_settled_at`",
                 'bank_settlement_updated_at' => "DATETIME DEFAULT NULL AFTER `bank_settled_by`",
                 'order_total' => "DECIMAL(15,2) DEFAULT 0 AFTER `order_items`",
                 'form_data' => "LONGTEXT DEFAULT NULL AFTER `order_total`",
                 'credit_report_json' => "LONGTEXT DEFAULT NULL AFTER `form_data`",
                 'bank_validation_file' => "TEXT DEFAULT NULL AFTER `credit_report_json`",
                 'signature_image' => "TEXT DEFAULT NULL AFTER `bank_validation_file`",
                 'contract_file' => "TEXT DEFAULT NULL AFTER `signature_image`",
                 'check_images' => "LONGTEXT DEFAULT NULL AFTER `contract_file`",
                 'transfer_receipt_files' => "LONGTEXT DEFAULT NULL",
                 'financier_payment_status' => "varchar(30) NOT NULL DEFAULT 'بررسی نشده' AFTER `transfer_receipt_files`",
                 'guarantor_info' => "LONGTEXT DEFAULT NULL AFTER `comments`",
                 'guarantor_info_2' => "LONGTEXT DEFAULT NULL AFTER `guarantor_info`",
                 'pending_docs_attachments' => "LONGTEXT DEFAULT NULL AFTER `guarantor_info_2`",
                 'assigned_babanoel_user_id' => "bigint(20) UNSIGNED DEFAULT NULL AFTER `assigned_bank_user_id`",
                 'description' => "TEXT DEFAULT NULL",
                 'national_card_front' => "TEXT DEFAULT NULL",
                 'national_card_back' => "TEXT DEFAULT NULL"
             ];
             
             $current_columns_requests = $wpdb->get_col("DESC `{$table_requests}`", 0);
             foreach ($required_columns_requests as $column_name => $column_definition) {
                 if (!in_array($column_name, $current_columns_requests)) {
                     if ($wpdb->query("ALTER TABLE `{$table_requests}` ADD COLUMN `{$column_name}` {$column_definition};") === false) {
                          mlr_log("Error adding column {$column_name} to {$table_requests}: " . $wpdb->last_error);
                     } else {
                          mlr_log("Successfully added column {$column_name} to {$table_requests}");
                          $current_columns_requests[] = $column_name;
                     }
                 }
             }

             if (in_array('document_receipt_status', $current_columns_requests, true)) {
                 $wpdb->query("UPDATE `{$table_requests}` SET `document_receipt_status` = 'بررسی نشده' WHERE `document_receipt_status` IS NULL OR TRIM(`document_receipt_status`) = ''");
             }
             if (in_array('financier_payment_status', $current_columns_requests, true)) {
                 $wpdb->query("UPDATE `{$table_requests}` SET `financier_payment_status` = 'بررسی نشده' WHERE `financier_payment_status` IS NULL OR TRIM(`financier_payment_status`) = ''");
             }

             if (in_array('request_origin', $current_columns_requests, true)) {
                 $wpdb->query("UPDATE `{$table_requests}` SET `request_origin` = 'acceptor_portal' WHERE `request_origin` IS NULL OR TRIM(`request_origin`) = ''");
             }

             // Add Indexes
             if (!$wpdb->get_results($wpdb->prepare("SHOW INDEX FROM `{$table_requests}` WHERE Key_name = %s", 'idx_settlement_cycle_id'))) {
                 $wpdb->query("ALTER TABLE `{$table_requests}` ADD INDEX `idx_settlement_cycle_id` (`settlement_cycle_id`);");
             }
             if (!$wpdb->get_results($wpdb->prepare("SHOW INDEX FROM `{$table_requests}` WHERE Key_name = %s", 'idx_assigned_bank_user_id'))) {
                 $wpdb->query("ALTER TABLE `{$table_requests}` ADD INDEX `idx_assigned_bank_user_id` (`assigned_bank_user_id`);");
             }
             if (!$wpdb->get_results($wpdb->prepare("SHOW INDEX FROM `{$table_requests}` WHERE Key_name = %s", 'idx_bank_settlement_status'))) {
                 $wpdb->query("ALTER TABLE `{$table_requests}` ADD INDEX `idx_bank_settlement_status` (`bank_settlement_status`);");
             }
             if (!$wpdb->get_results($wpdb->prepare("SHOW INDEX FROM `{$table_requests}` WHERE Key_name = %s", 'idx_applicant_user_id'))) {
                 $wpdb->query("ALTER TABLE `{$table_requests}` ADD INDEX `idx_applicant_user_id` (`applicant_user_id`);");
             }
             if (!$wpdb->get_results($wpdb->prepare("SHOW INDEX FROM `{$table_requests}` WHERE Key_name = %s", 'idx_acceptor_user_id'))) {
                 $wpdb->query("ALTER TABLE `{$table_requests}` ADD INDEX `idx_acceptor_user_id` (`acceptor_user_id`);");
             }
             if (!$wpdb->get_results($wpdb->prepare("SHOW INDEX FROM `{$table_requests}` WHERE Key_name = %s", 'idx_request_origin'))) {
                 $wpdb->query("ALTER TABLE `{$table_requests}` ADD INDEX `idx_request_origin` (`request_origin`);");
             }
             foreach ([
                 'idx_acceptor_assignment_mode' => 'acceptor_assignment_mode',
                 'idx_marketplace_status' => 'marketplace_status',
                 'idx_marketplace_claimed_by' => 'marketplace_claimed_by',
                 'idx_acceptor_invitation_code' => 'acceptor_invitation_code',
                 'idx_acceptor_invited_by' => 'acceptor_invited_by',
             ] as $index_name => $column_name) {
                 if (!$wpdb->get_results($wpdb->prepare("SHOW INDEX FROM `{$table_requests}` WHERE Key_name = %s", $index_name))) {
                     $wpdb->query("ALTER TABLE `{$table_requests}` ADD INDEX `{$index_name}` (`{$column_name}`);");
                 }
             }
        }
        
        // --- Add guarantor_info column ---
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_requests)) == $table_requests) {
            if (!$wpdb->get_results("SHOW COLUMNS FROM `{$table_requests}` LIKE 'guarantor_info'")) {
                $wpdb->query("ALTER TABLE `{$table_requests}` ADD COLUMN `guarantor_info` LONGTEXT DEFAULT NULL AFTER `comments`;");
            }
        }

        // --- Add guarantor_info_2 (ضامن دوم اختیاری) ---
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_requests)) == $table_requests) {
            if (!$wpdb->get_results("SHOW COLUMNS FROM `{$table_requests}` LIKE 'guarantor_info_2'")) {
                $wpdb->query("ALTER TABLE `{$table_requests}` ADD COLUMN `guarantor_info_2` LONGTEXT DEFAULT NULL AFTER `guarantor_info`;");
            }
        }

        // --- Add pending_docs_attachments (پیوست‌های مرحله در انتظار مدارک برای نمایش به پذیرنده) ---
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_requests)) == $table_requests) {
            if (!$wpdb->get_results("SHOW COLUMNS FROM `{$table_requests}` LIKE 'pending_docs_attachments'")) {
                $wpdb->query("ALTER TABLE `{$table_requests}` ADD COLUMN `pending_docs_attachments` LONGTEXT DEFAULT NULL AFTER `guarantor_info`;");
            }
        }

        // --- Add assigned_babanoel_user_id (کارشناس بابانوئل) ---
        // Transfer receipt files column for finance-only payment receipt uploads.
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_requests)) == $table_requests) {
            if (!$wpdb->get_results("SHOW COLUMNS FROM `{$table_requests}` LIKE 'transfer_receipt_files'")) {
                $wpdb->query("ALTER TABLE `{$table_requests}` ADD COLUMN `transfer_receipt_files` LONGTEXT DEFAULT NULL;");
            }
        }

        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_requests)) == $table_requests) {
            if (!$wpdb->get_results("SHOW COLUMNS FROM `{$table_requests}` LIKE 'assigned_babanoel_user_id'")) {
                $wpdb->query("ALTER TABLE `{$table_requests}` ADD COLUMN `assigned_babanoel_user_id` bigint(20) UNSIGNED DEFAULT NULL AFTER `assigned_bank_user_id`;");
                if (function_exists('mlr_log')) mlr_log("Added column assigned_babanoel_user_id to {$table_requests}");
            }
        }
        
        
        // --- Admin Notifications Table Updates (Add read_by column) ---
        $table_notifications = $wpdb->prefix . 'mlr_admin_notifications';
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_notifications)) == $table_notifications) {
            $existing_columns_notif = $wpdb->get_col("DESC `{$table_notifications}`", 0);
            if (!in_array('read_by', $existing_columns_notif)) {
                $wpdb->query("ALTER TABLE `{$table_notifications}` ADD COLUMN `read_by` longtext DEFAULT NULL AFTER `is_read`;");
            }
        }

        // --- Request Messages Table Updates (FIX for Attachments) ---
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_messages)) == $table_messages) {
            $existing_columns_msgs = $wpdb->get_col("DESC `{$table_messages}`", 0);
            if (!in_array('attachments', $existing_columns_msgs)) {
                $wpdb->query("ALTER TABLE `{$table_messages}` ADD COLUMN `attachments` text DEFAULT NULL AFTER `message`;");
                if (function_exists('mlr_log')) mlr_log("Added attachments column to {$table_messages}");
            }
        }

        // --- Cooperation Requests Table Updates ---
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_cooperation)) == $table_cooperation) {
             $existing_columns_coop = $wpdb->get_col("DESC `{$table_cooperation}`", 0);
             $required_columns_coop = [
                 'contract_token' => "VARCHAR(64) DEFAULT NULL AFTER `status`",
                 'contract_signed_status' => "VARCHAR(20) DEFAULT 'pending' NOT NULL AFTER `contract_token`",
                 'contract_signed_at' => "DATETIME DEFAULT NULL AFTER `contract_signed_status`",
                 'contract_expires_at' => "DATETIME DEFAULT NULL AFTER `contract_signed_at`",
                 'otp_code' => "VARCHAR(255) DEFAULT NULL AFTER `contract_expires_at`",
                 'otp_expires_at' => "DATETIME DEFAULT NULL AFTER `otp_code`"
             ];
             foreach ($required_columns_coop as $column_name => $column_definition) {
                if (!in_array($column_name, $existing_columns_coop)) {
                    $wpdb->query("ALTER TABLE `{$table_cooperation}` ADD COLUMN `{$column_name}` {$column_definition};");
                    $existing_columns_coop[] = $column_name;
                }
             }
             
             if (!$wpdb->get_results($wpdb->prepare("SHOW INDEX FROM `{$table_cooperation}` WHERE Key_name = %s", 'idx_contract_token'))) {
                 if (in_array('contract_token', $existing_columns_coop)) {
                    $wpdb->query("ALTER TABLE `{$table_cooperation}` ADD UNIQUE INDEX `idx_contract_token` (`contract_token`);");
                 }
             }
             if (!$wpdb->get_results($wpdb->prepare("SHOW INDEX FROM `{$table_cooperation}` WHERE Key_name = %s", 'idx_signed_status'))) {
                  if (in_array('contract_signed_status', $existing_columns_coop)) { 
                      $wpdb->query("ALTER TABLE `{$table_cooperation}` ADD INDEX `idx_signed_status` (`contract_signed_status`);"); 
                  }
             }
        }

        // --- Installment plans: canonical global display order (2.7) ---
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_plans)) === $table_plans) {
            $plan_order_column = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM `{$table_plans}` LIKE %s", 'display_order'));
            if (empty($plan_order_column)) {
                $added_plan_order = $wpdb->query("ALTER TABLE `{$table_plans}` ADD COLUMN `display_order` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `is_active`");
                if ($added_plan_order === false) {
                    $plan_order_backfill_ok = false;
                }
            }

            // While upgrading from a pre-2.7 schema no administrator could have saved the new
            // ordering yet, so it is safe to initialise every row deterministically in one query.
            $plan_order_column_now = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM `{$table_plans}` LIKE %s", 'display_order'));
            if (!empty($plan_order_column_now)) {
                $legacy_plan_ids = array_values(array_filter(array_map('absint', (array) $wpdb->get_col("SELECT id FROM `{$table_plans}` ORDER BY id DESC"))));
                if (!empty($legacy_plan_ids)) {
                    $case_parts = [];
                    $id_parts = [];
                    $position = 10;
                    foreach ($legacy_plan_ids as $legacy_plan_id) {
                        $case_parts[] = "WHEN {$legacy_plan_id} THEN {$position}";
                        $id_parts[] = (string) $legacy_plan_id;
                        $position += 10;
                    }
                    $backfill_sql = "UPDATE `{$table_plans}` SET `display_order` = CASE `id` " . implode(' ', $case_parts) . " ELSE `display_order` END WHERE `id` IN (" . implode(',', $id_parts) . ")";
                    if ($wpdb->query($backfill_sql) === false) {
                        $plan_order_backfill_ok = false;
                    }
                }
            }
        }

        // 2.8: Prefer a transactional engine for cooperation requests. The status handler
        // still has a WRITE-lock fallback, so installations that cannot ALTER the engine
        // remain operational instead of blocking status changes.
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_cooperation)) === $table_cooperation) {
            $cooperation_engine = function_exists('mlr_get_table_storage_engine')
                ? mlr_get_table_storage_engine($table_cooperation)
                : '';
            if ($cooperation_engine !== '' && !in_array($cooperation_engine, ['innodb', 'xtradb'], true)) {
                $engine_migrated = $wpdb->query("ALTER TABLE `{$table_cooperation}` ENGINE=InnoDB");
                if ($engine_migrated === false) {
                    if (function_exists('mlr_log')) {
                        mlr_log('Could not convert cooperation_requests to InnoDB; safe table-lock fallback remains active.', [
                            'table' => $table_cooperation,
                            'engine' => $cooperation_engine,
                            'db_error' => $wpdb->last_error,
                        ]);
                    }
                } elseif (function_exists('mlr_log')) {
                    mlr_log('Converted cooperation_requests storage engine to InnoDB.', ['table' => $table_cooperation]);
                }
            }
        }

        // 2.8: Repair the precise invalid-origin pattern created when an applicant account
        // reached the acceptor-only form. Do not rewrite valid historical acceptor rows.
        // A candidate is repaired only when its stored acceptor equals user_id, no applicant
        // identity was stored, the account is currently an applicant, and it is not a valid
        // acceptor account/profile.
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_requests)) === $table_requests) {
            $identity_candidates = $wpdb->get_results(
                "SELECT id, user_id, applicant_user_id, acceptor_user_id, request_origin
                 FROM `{$table_requests}`
                 WHERE request_origin = 'acceptor_portal'
                   AND (applicant_user_id IS NULL OR applicant_user_id = 0)
                   AND acceptor_user_id IS NOT NULL
                   AND acceptor_user_id > 0
                   AND acceptor_user_id = user_id"
            );
            foreach ((array) $identity_candidates as $identity_candidate) {
                $candidate_user_id = absint($identity_candidate->user_id ?? 0);
                if ($candidate_user_id <= 0) {
                    continue;
                }
                $is_applicant_account = function_exists('mlr_is_applicant_user') && mlr_is_applicant_user($candidate_user_id);
                $is_valid_acceptor = function_exists('mlr_user_has_role') && mlr_user_has_role($candidate_user_id, 'acceptor');
                if (!$is_applicant_account || $is_valid_acceptor) {
                    continue;
                }

                $repair_result = $wpdb->update(
                    $table_requests,
                    [
                        'applicant_user_id' => $candidate_user_id,
                        'acceptor_user_id' => null,
                        'request_origin' => 'applicant_portal',
                        'acceptor_assignment_mode' => '',
                        'marketplace_status' => '',
                        'marketplace_claimed_by' => null,
                        'marketplace_claimed_at' => null,
                        'acceptor_invitation_code' => null,
                        'acceptor_invited_by' => null,
                        'acceptor_invitation_locked' => 0,
                    ],
                    ['id' => absint($identity_candidate->id)],
                    ['%d', '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%d', '%d'],
                    ['%d']
                );
                if ($repair_result === false) {
                    $identity_repair_ok = false;
                    if (function_exists('mlr_log')) {
                        mlr_log('Failed to repair invalid applicant-as-acceptor request identity.', [
                            'request_id' => absint($identity_candidate->id),
                            'applicant_user_id' => $candidate_user_id,
                            'db_error' => $wpdb->last_error,
                        ]);
                    }
                } elseif (function_exists('mlr_log')) {
                    mlr_log('Repaired invalid applicant-as-acceptor request identity.', [
                        'request_id' => absint($identity_candidate->id),
                        'applicant_user_id' => $candidate_user_id,
                    ]);
                }
            }
        }

        // 2.9: Bank -> Babanoell settlement fields are intentionally separate from
        // settlement_status / settlement_date / settlement_cycle_id. Do not pre-fill
        // a pseudo-pending state for granted requests; this record exists only after
        // an explicit internal action by the assigned bank expert.

        // Mark a migration complete only after the required schema is verifiably present.
        $schema_ok = true;
        $missing_schema_parts = [];
        $request_table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_requests)) === $table_requests;
        if (!$request_table_exists) {
            $schema_ok = false;
            $missing_schema_parts[] = 'loan_requests:table_missing';
        } else {
            $verified_request_columns = $wpdb->get_col("DESC `{$table_requests}`", 0);
            $required_request_keys = isset($required_columns_requests) && is_array($required_columns_requests) ? array_keys($required_columns_requests) : [];
            $missing_request_columns = array_values(array_diff($required_request_keys, $verified_request_columns));
            if ($missing_request_columns) {
                $schema_ok = false;
                $missing_schema_parts[] = 'loan_requests:' . implode(',', $missing_request_columns);
            }
        }

        $cooperation_table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_cooperation)) === $table_cooperation;
        if (!$cooperation_table_exists) {
            $schema_ok = false;
            $missing_schema_parts[] = 'cooperation_requests:table_missing';
        } else {
            $verified_coop_columns = $wpdb->get_col("DESC `{$table_cooperation}`", 0);
            $required_coop_keys = isset($required_columns_coop) && is_array($required_columns_coop) ? array_keys($required_columns_coop) : [];
            $missing_coop_columns = array_values(array_diff($required_coop_keys, $verified_coop_columns));
            if ($missing_coop_columns) {
                $schema_ok = false;
                $missing_schema_parts[] = 'cooperation_requests:' . implode(',', $missing_coop_columns);
            }
        }
        $plans_table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_plans)) === $table_plans;
        if (!$plans_table_exists) {
            $schema_ok = false;
            $missing_schema_parts[] = 'installment_plans:table_missing';
        } else {
            $verified_plan_columns = $wpdb->get_col("DESC `{$table_plans}`", 0);
            if (!in_array('display_order', (array) $verified_plan_columns, true)) {
                $schema_ok = false;
                $missing_schema_parts[] = 'installment_plans:display_order';
            }
        }
        if (!$plan_order_backfill_ok) {
            $schema_ok = false;
            $missing_schema_parts[] = 'installment_plans:display_order_backfill';
        }
        if (!$identity_repair_ok) {
            $schema_ok = false;
            $missing_schema_parts[] = 'loan_requests:identity_repair';
        }

        if ($schema_ok) {
            update_option($version_option_name, $current_db_version);
            if (function_exists('mlr_log')) mlr_log("Database schema verified at version {$current_db_version}");
        } elseif (function_exists('mlr_log')) {
            mlr_log('Database migration incomplete; version not advanced.', ['missing' => $missing_schema_parts, 'db_error' => $wpdb->last_error]);
        }
    }
}


// =============================================================================
// Core module loading (order matters — do not reorder without careful review)
// 8.4.6.46: bank expert workflow completion visibility and bank-side settlement tracking.
// =============================================================================

// SMS provider + templates
require_once MLR_PLUGIN_DIR . 'includes/sms-functions.php';
// Loan installment calculation helpers (PMT / market / leasing)
require_once MLR_PLUGIN_DIR . 'includes/loan-calculations.php';
// Shared helpers, status aliases, plan helpers, request access, events, messages, ...
require_once MLR_PLUGIN_DIR . 'includes/functions.php';
// Occupation-specific document rules
require_once MLR_PLUGIN_DIR . 'includes/occupation-documents.php';
// Security hardening (upload, capability, nonce helpers)
require_once MLR_PLUGIN_DIR . 'includes/hardening.php';
// Loan/facility contract signing mode + digital OTP signature helpers
require_once MLR_PLUGIN_DIR . 'includes/loan-contract-signing.php';
// Per-request contract reissue/versioning workflow (admin + audit history)
require_once MLR_PLUGIN_DIR . 'includes/loan-contract-reissue.php';
// Configurable loan request ID increment / sequence allocator
require_once MLR_PLUGIN_DIR . 'includes/request-id-sequence.php';
// Payment gateway callback handling
require_once MLR_PLUGIN_DIR . 'includes/payment-callback.php';
// Document upload service (chunked / single file)
require_once MLR_PLUGIN_DIR . 'includes/document-upload-service.php';

// GreenPay is an additive integration and is intentionally isolated from the core loan workflow.
// It must remain after the core helpers above.
require_once MLR_PLUGIN_DIR . 'includes/greenpay/greenpay-service.php';

// Consolidated AJAX handlers (requires all files under includes/ajax/)
require_once MLR_PLUGIN_DIR . 'includes/ajax-handlers.php';



/**
 * 8.4.6.48: Normalize only the isolated bank -> Babanoell tracking field.
 * No acceptor settlement fields are touched.
 */
add_action('admin_init', 'mlr_normalize_bank_babanoell_tracking_once', 22);
function mlr_normalize_bank_babanoell_tracking_once() {
    if (
        !current_user_can('manage_options')
        && !current_user_can('mlr_amaliat_operations')
        && !current_user_can('mlr_mali_operations')
    ) {
        return;
    }

    $normalization_version = '8.4.6.48';
    $option_name = 'mlr_bank_babanoell_tracking_normalized_version';
    if (get_option($option_name) === $normalization_version) {
        return;
    }

    global $wpdb;
    $table = $wpdb->prefix . 'loan_requests';
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) {
        return;
    }

    $column = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM `{$table}` LIKE %s", 'bank_settlement_status'));
    if (empty($column)) {
        return;
    }

    $guard_mode = function_exists('mlr_begin_financial_write_guard')
        ? mlr_begin_financial_write_guard([$table])
        : false;
    if (!$guard_mode) {
        return;
    }

    $ok = true;
    $queries = [
        "UPDATE `{$table}` SET `bank_settlement_status` = '' WHERE TRIM(COALESCE(`bank_settlement_status`, '')) = 'در انتظار تسویه'",
        "UPDATE `{$table}` SET `bank_settlement_status` = 'انجام شده' WHERE TRIM(COALESCE(`bank_settlement_status`, '')) IN ('تسویه شده', 'ثبت شده')",
    ];
    foreach ($queries as $query) {
        if ($wpdb->query($query) === false) {
            $ok = false;
            break;
        }
    }

    $guard_end_ok = mlr_end_financial_write_guard($guard_mode, $ok);
    if ($guard_mode === 'transaction' && (!$ok || !$guard_end_ok)) {
        $wpdb->query('ROLLBACK');
        $ok = false;
    }

    if ($ok) {
        update_option($option_name, $normalization_version, false);
    } elseif (function_exists('mlr_log')) {
        mlr_log('Bank-to-Babanoell tracking normalization failed.', ['db_error' => $wpdb->last_error]);
    }
}

// قابلیت ویرایش تاریخ تسویه برای نقش‌های administrator و mali
add_action('init', 'mlr_register_settlement_date_edit_capability', 20);
function mlr_register_settlement_date_edit_capability() {
    $cap = 'mlr_edit_settlement_date';
    $admin = get_role('administrator');
    if ($admin && !$admin->has_cap($cap)) {
        $admin->add_cap($cap);
    }
    $mali = get_role('mali');
    if ($mali && !$mali->has_cap($cap)) {
        $mali->add_cap($cap);
    }
}

// قابلیت کارشناس عملیات بابانوئل (نقش amaliat)
add_action('init', 'mlr_register_amaliat_capability', 20);
function mlr_register_amaliat_capability() {
    $cap = 'mlr_amaliat_operations';
    $amaliat = get_role('amaliat');
    if ($amaliat && !$amaliat->has_cap($cap)) {
        $amaliat->add_cap($cap);
    }
}

// قابلیت کارشناس مالی بابانوئل (نقش mali)
add_action('init', 'mlr_register_mali_capability', 20);
function mlr_register_mali_capability() {
    $cap = 'mlr_mali_operations';
    $mali = get_role('mali');
    if ($mali && !$mali->has_cap($cap)) {
        $mali->add_cap($cap);
    }
}

// دسترسی مشاهده منوی مستقل «تسویه بانک» برای مدیران و کارشناسان مالی بابانوئل.
add_action('init', 'mlr_register_bank_settlement_admin_capability', 21);
function mlr_register_bank_settlement_admin_capability() {
    $cap = 'mlr_view_bank_settlements';
    foreach (['administrator', 'mali'] as $role_name) {
        $role = get_role($role_name);
        if ($role && !$role->has_cap($cap)) {
            $role->add_cap($cap);
        }
    }
}

add_action('init', 'mlr_register_applicant_role', 20);
function mlr_register_applicant_role() {
    if (!get_role('mlr_applicant')) {
        add_role('mlr_applicant', 'متقاضی تسهیلات', ['read' => true]);
    } else {
        $role = get_role('mlr_applicant');
        if ($role && !$role->has_cap('read')) {
            $role->add_cap('read');
        }
    }
}

add_action('admin_init', 'mlr_redirect_applicants_from_admin');
function mlr_redirect_applicants_from_admin() {
    if (defined('DOING_AJAX') && DOING_AJAX) {
        return;
    }
    if (!is_user_logged_in() || !function_exists('mlr_is_applicant_user') || !mlr_is_applicant_user()) {
        return;
    }
    if (current_user_can('manage_options') || current_user_can('mlr_amaliat_operations') || current_user_can('mlr_mali_operations')) {
        return;
    }

    $current_user = wp_get_current_user();
    if (array_intersect(['bank', 'acceptor', 'mali', 'amaliat'], (array) $current_user->roles)) {
        return;
    }

    wp_safe_redirect(function_exists('mlr_get_applicant_portal_url') ? mlr_get_applicant_portal_url() : home_url('/'));
    exit;
}

// =============================================================================
// Admin-only pages (loaded only when is_admin() is true)
// =============================================================================
if (is_admin()) {
    require_once MLR_PLUGIN_DIR . 'includes/admin-filters.php';
    require_once MLR_PLUGIN_DIR . 'includes/admin-pages/loan-requests.php';
    require_once MLR_PLUGIN_DIR . 'includes/admin-pages/installment-plans.php';
    require_once MLR_PLUGIN_DIR . 'includes/admin-pages/granted-requests.php';
    require_once MLR_PLUGIN_DIR . 'includes/admin-pages/settlement-date-editor.php';
    require_once MLR_PLUGIN_DIR . 'includes/admin-pages/unsettled-requests.php';
    require_once MLR_PLUGIN_DIR . 'includes/admin-pages/settled-requests.php';
    require_once MLR_PLUGIN_DIR . 'includes/admin-pages/cooperation-requests.php';
    require_once MLR_PLUGIN_DIR . 'includes/admin-pages/record-corrections.php';
    require_once MLR_PLUGIN_DIR . 'includes/admin-pages/form-editor.php';
    require_once MLR_PLUGIN_DIR . 'includes/admin-pages/sms-settings.php';
    require_once MLR_PLUGIN_DIR . 'includes/admin-pages/log-viewer.php';
    require_once MLR_PLUGIN_DIR . 'includes/admin-pages/cooperation-contract-settings.php';
    require_once MLR_PLUGIN_DIR . 'includes/admin-pages/amaliat-operations.php';
    require_once MLR_PLUGIN_DIR . 'includes/admin-pages/mali-requests.php';
    require_once MLR_PLUGIN_DIR . 'includes/admin-pages/bank-settlements.php';
    require_once MLR_PLUGIN_DIR . 'includes/admin-pages/greenpay-transactions.php';
}

// =============================================================================
// Frontend shortcodes (always registered; assets are enqueued conditionally)
// =============================================================================
require_once MLR_PLUGIN_DIR . 'includes/shortcodes/loan-request-form.php';
require_once MLR_PLUGIN_DIR . 'includes/shortcodes/applicant-loan-request-form.php';
require_once MLR_PLUGIN_DIR . 'includes/shortcodes/acceptor-open-applicant-requests.php';
require_once MLR_PLUGIN_DIR . 'includes/shortcodes/acceptor-invitation-link.php';
require_once MLR_PLUGIN_DIR . 'includes/shortcodes/user-loan-requests.php';
require_once MLR_PLUGIN_DIR . 'includes/shortcodes/applicant-dashboard.php';
require_once MLR_PLUGIN_DIR . 'includes/shortcodes/needs-review-requests.php';
require_once MLR_PLUGIN_DIR . 'includes/shortcodes/processing-requests.php';
require_once MLR_PLUGIN_DIR . 'includes/shortcodes/granted-user-requests.php';
require_once MLR_PLUGIN_DIR . 'includes/shortcodes/user-settlement-cycles.php';
require_once MLR_PLUGIN_DIR . 'includes/shortcodes/bank-expert-dashboard.php';
require_once MLR_PLUGIN_DIR . 'includes/shortcodes/bank-all-requests-dashboard.php';
require_once MLR_PLUGIN_DIR . 'includes/shortcodes/bank-documents-completion-dashboard.php';
require_once MLR_PLUGIN_DIR . 'includes/shortcodes/granted-expert-dashboard.php';
require_once MLR_PLUGIN_DIR . 'includes/bank-settlement-cycles.php';
require_once MLR_PLUGIN_DIR . 'includes/shortcodes/verified-docs-dashboard.php';
require_once MLR_PLUGIN_DIR . 'includes/shortcodes/cooperation-request-form.php';
require_once MLR_PLUGIN_DIR . 'includes/shortcodes/loan-calculator.php';
require_once MLR_PLUGIN_DIR . 'includes/shortcodes/user-stats.php';
require_once MLR_PLUGIN_DIR . 'includes/shortcodes/invoice.php';
require_once MLR_PLUGIN_DIR . 'includes/shortcodes/edit-requests.php';
require_once MLR_PLUGIN_DIR . 'includes/shortcodes/complete-request.php';
require_once MLR_PLUGIN_DIR . 'includes/shortcodes/loan-contract-public-viewer.php';
require_once MLR_PLUGIN_DIR . 'includes/shortcodes/cooperation-contract-viewer.php';

// پنل مخفی ویرایش/حذف درخواست‌ها (لینک در منوها نیست؛ ورود: ?mlr_secret_editor=1)
require_once MLR_PLUGIN_DIR . 'includes/secret-editor/mlr-secret-editor.php';



// Ensure required DB tables exist even if plugin was updated without re-activation
add_action('admin_init', 'mlr_maybe_create_required_tables', 20);
function mlr_maybe_create_required_tables() {
    global $wpdb;
    $guard_option = 'mlr_required_tables_guard_version';
    $guard_version = '8.4.6.31';
    if (get_option($guard_option) === $guard_version) {
        return;
    }
    if (!function_exists('dbDelta')) {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    }

    $table_request_messages = $wpdb->prefix . 'mlr_request_messages';
    $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_request_messages));
    if ($exists !== $table_request_messages) {
        $charset_collate = $wpdb->get_charset_collate();
        $sql_request_messages = "CREATE TABLE $table_request_messages (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            request_id bigint(20) UNSIGNED NOT NULL,
            user_id bigint(20) UNSIGNED DEFAULT NULL,
            user_role varchar(50) DEFAULT '' NOT NULL,
            message longtext NOT NULL,
        attachments text DEFAULT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY request_id (request_id),
            KEY user_id (user_id),
            KEY created_at (created_at)
        ) $charset_collate;";
        dbDelta($sql_request_messages);
    }

    // Ensure installment_plans has display_fields_context column (for per-shortcode calculator fields).
    // Never issue ALTER against a table that does not exist; the canonical migration/activation
    // is responsible for creating primary tables.
    $table_installment_plans = $wpdb->prefix . 'installment_plans';
    $plans_table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_installment_plans)) === $table_installment_plans;
    if ($plans_table_exists) {
        $col_exists = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM `$table_installment_plans` LIKE %s", 'display_fields_context'));
        if (empty($col_exists)) {
            $wpdb->query("ALTER TABLE `$table_installment_plans` ADD COLUMN display_fields_context longtext");
        }
        $display_order_exists = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM `$table_installment_plans` LIKE %s", 'display_order'));
        if (empty($display_order_exists)) {
            $added_display_order = $wpdb->query("ALTER TABLE `$table_installment_plans` ADD COLUMN display_order INT UNSIGNED NOT NULL DEFAULT 0 AFTER is_active");
            if ($added_display_order !== false) {
                $legacy_plan_ids = array_values(array_filter(array_map('absint', (array) $wpdb->get_col("SELECT id FROM `$table_installment_plans` ORDER BY id DESC"))));
                if (!empty($legacy_plan_ids)) {
                    $case_parts = [];
                    $id_parts = [];
                    $position = 10;
                    foreach ($legacy_plan_ids as $legacy_plan_id) {
                        $case_parts[] = "WHEN {$legacy_plan_id} THEN {$position}";
                        $id_parts[] = (string) $legacy_plan_id;
                        $position += 10;
                    }
                    $wpdb->query("UPDATE `$table_installment_plans` SET `display_order` = CASE `id` " . implode(' ', $case_parts) . " ELSE `display_order` END WHERE `id` IN (" . implode(',', $id_parts) . ")");
                }
            }
        }
    }

    $table_requests = $wpdb->prefix . 'loan_requests';
    if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_requests)) === $table_requests) {
        $applicant_col = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM `{$table_requests}` LIKE %s", 'applicant_user_id'));
        if (empty($applicant_col)) {
            $wpdb->query("ALTER TABLE `{$table_requests}` ADD COLUMN `applicant_user_id` BIGINT(20) UNSIGNED DEFAULT NULL AFTER `user_id`");
        }
        $acceptor_col = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM `{$table_requests}` LIKE %s", 'acceptor_user_id'));
        if (empty($acceptor_col)) {
            $wpdb->query("ALTER TABLE `{$table_requests}` ADD COLUMN `acceptor_user_id` BIGINT(20) UNSIGNED DEFAULT NULL AFTER `applicant_user_id`");
        }
        $origin_col = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM `{$table_requests}` LIKE %s", 'request_origin'));
        if (empty($origin_col)) {
            $wpdb->query("ALTER TABLE `{$table_requests}` ADD COLUMN `request_origin` VARCHAR(30) NOT NULL DEFAULT 'acceptor_portal' AFTER `acceptor_user_id`");
        }
        foreach ([
            'acceptor_assignment_mode' => "VARCHAR(30) DEFAULT '' AFTER `request_origin`",
            'marketplace_status' => "VARCHAR(30) DEFAULT '' AFTER `acceptor_assignment_mode`",
            'marketplace_claimed_by' => "BIGINT(20) UNSIGNED DEFAULT NULL AFTER `marketplace_status`",
            'marketplace_claimed_at' => "DATETIME DEFAULT NULL AFTER `marketplace_claimed_by`",
            'requested_service_scope' => "VARCHAR(30) DEFAULT '' AFTER `marketplace_claimed_at`",
            'requested_cities' => "LONGTEXT DEFAULT NULL AFTER `requested_service_scope`",
            'requested_location_terms' => "LONGTEXT DEFAULT NULL AFTER `requested_cities`",
            'requested_activity_categories' => "LONGTEXT DEFAULT NULL AFTER `requested_location_terms`",
            'request_public_summary' => "TEXT DEFAULT NULL AFTER `requested_activity_categories`",
            'acceptor_invitation_code' => "VARCHAR(100) DEFAULT NULL AFTER `request_public_summary`",
            'acceptor_invited_by' => "BIGINT(20) UNSIGNED DEFAULT NULL AFTER `acceptor_invitation_code`",
            'acceptor_invitation_locked' => "TINYINT(1) DEFAULT 0 AFTER `acceptor_invited_by`",
        ] as $column_name => $column_definition) {
            $column_exists = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM `{$table_requests}` LIKE %s", $column_name));
            if (empty($column_exists)) {
                $wpdb->query("ALTER TABLE `{$table_requests}` ADD COLUMN `{$column_name}` {$column_definition}");
            }
        }
        $wpdb->query("UPDATE `{$table_requests}` SET `request_origin` = 'acceptor_portal' WHERE `request_origin` IS NULL OR TRIM(`request_origin`) = ''");

        foreach ([
            'idx_applicant_user_id' => 'applicant_user_id',
            'idx_acceptor_user_id' => 'acceptor_user_id',
            'idx_request_origin' => 'request_origin',
            'idx_acceptor_assignment_mode' => 'acceptor_assignment_mode',
            'idx_marketplace_status' => 'marketplace_status',
            'idx_marketplace_claimed_by' => 'marketplace_claimed_by',
            'idx_acceptor_invitation_code' => 'acceptor_invitation_code',
            'idx_acceptor_invited_by' => 'acceptor_invited_by',
        ] as $index_name => $column_name) {
            if (!$wpdb->get_results($wpdb->prepare("SHOW INDEX FROM `{$table_requests}` WHERE Key_name = %s", $index_name))) {
                $wpdb->query("ALTER TABLE `{$table_requests}` ADD INDEX `{$index_name}` (`{$column_name}`);");
            }
        }
    }

    // Ensure admin notifications table exists (even without re-activation) so notifications always work
    $table_notifications = $wpdb->prefix . 'mlr_admin_notifications';
    $notif_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_notifications));
    if ($notif_exists !== $table_notifications) {
        $charset_collate = $wpdb->get_charset_collate();
        $sql_notifications = "CREATE TABLE $table_notifications (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            type varchar(50) NOT NULL,
            message text NOT NULL,
            link text DEFAULT NULL,
            is_read tinyint(1) DEFAULT 0,
            read_by longtext DEFAULT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY is_read (is_read),
            KEY created_at (created_at)
        ) $charset_collate;";
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql_notifications);
        if (function_exists('mlr_log')) {
            mlr_log('mlr_maybe_create_required_tables: created table ' . $table_notifications);
        }
    } else {
        // Table exists: ensure read_by column exists (old installs may not have it)
        $read_by_col = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM `$table_notifications` LIKE %s", 'read_by'));
        if (empty($read_by_col)) {
            $wpdb->query("ALTER TABLE `$table_notifications` ADD COLUMN read_by longtext DEFAULT NULL AFTER is_read");
            if (function_exists('mlr_log')) {
                mlr_log('mlr_maybe_create_required_tables: added read_by to ' . $table_notifications);
            }
        }
    }

    // Only suppress future repair checks after the auxiliary schema is actually present.
    $messages_ok = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_request_messages)) === $table_request_messages
        && (bool) $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM `{$table_request_messages}` LIKE %s", 'attachments'));
    $plans_ok = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_installment_plans)) === $table_installment_plans
        && (bool) $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM `{$table_installment_plans}` LIKE %s", 'display_fields_context'))
        && (bool) $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM `{$table_installment_plans}` LIKE %s", 'display_order'));
    $notifications_ok = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_notifications)) === $table_notifications
        && (bool) $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM `{$table_notifications}` LIKE %s", 'read_by'));

    $requests_ok = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_requests)) === $table_requests;
    if ($requests_ok) {
        $required_aux_request_columns = [
            'applicant_user_id', 'acceptor_user_id', 'request_origin', 'acceptor_assignment_mode',
            'marketplace_status', 'marketplace_claimed_by', 'marketplace_claimed_at',
            'requested_service_scope', 'requested_cities', 'requested_location_terms',
            'requested_activity_categories', 'request_public_summary', 'acceptor_invitation_code',
            'acceptor_invited_by', 'acceptor_invitation_locked',
        ];
        $present_aux_request_columns = $wpdb->get_col("DESC `{$table_requests}`", 0);
        $requests_ok = empty(array_diff($required_aux_request_columns, (array) $present_aux_request_columns));
    }
    if ($messages_ok && $plans_ok && $notifications_ok && $requests_ok) {
        update_option($guard_option, $guard_version, false);
    } elseif (function_exists('mlr_log')) {
        mlr_log('Auxiliary schema repair remains incomplete; it will be retried on a later admin request.', [
            'messages_ok' => $messages_ok,
            'plans_ok' => $plans_ok,
            'notifications_ok' => $notifications_ok,
            'requests_ok' => $requests_ok,
        ]);
    }
}


register_activation_hook(__FILE__, 'mlr_install_and_update');
register_deactivation_hook(__FILE__, 'mlr_deactivate');

add_action('mlr_daily_notification_purge', 'mlr_purge_daily_notifications');
add_action('wp', 'mlr_schedule_daily_notification_purge');
function mlr_schedule_daily_notification_purge() {
    if (get_transient('mlr_daily_purge_scheduled')) {
        return;
    }
    if (wp_next_scheduled('mlr_daily_notification_purge')) {
        set_transient('mlr_daily_purge_scheduled', 1, DAY_IN_SECONDS);
        return;
    }
    wp_schedule_event(time(), 'daily', 'mlr_daily_notification_purge');
    set_transient('mlr_daily_purge_scheduled', 1, DAY_IN_SECONDS);
}

function mlr_install_and_update() {
    // Ensure log directory exists
    if (function_exists('mlr_ensure_log_dir')) { mlr_ensure_log_dir(); }
    if (function_exists('mlr_register_applicant_role')) { mlr_register_applicant_role(); }

    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

    // Unified bigInt definition
    $table_requests = $wpdb->prefix . 'loan_requests';
    $sql_requests = "CREATE TABLE $table_requests (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id bigint(20) UNSIGNED NOT NULL,
        applicant_user_id bigint(20) UNSIGNED DEFAULT NULL,
        acceptor_user_id bigint(20) UNSIGNED DEFAULT NULL,
        request_origin varchar(30) NOT NULL DEFAULT 'acceptor_portal',
        acceptor_assignment_mode varchar(30) DEFAULT '',
        marketplace_status varchar(30) DEFAULT '',
        marketplace_claimed_by bigint(20) UNSIGNED DEFAULT NULL,
        marketplace_claimed_at datetime DEFAULT NULL,
        requested_service_scope varchar(30) DEFAULT '',
        requested_cities longtext DEFAULT NULL,
        requested_location_terms longtext DEFAULT NULL,
        requested_activity_categories longtext DEFAULT NULL,
        request_public_summary text DEFAULT NULL,
        acceptor_invitation_code varchar(100) DEFAULT NULL,
        acceptor_invited_by bigint(20) UNSIGNED DEFAULT NULL,
        acceptor_invitation_locked tinyint(1) DEFAULT 0,
        full_name varchar(255) NOT NULL,
        national_code varchar(10) NOT NULL,
        mobile_number varchar(15) NOT NULL,
        phone_number varchar(20) DEFAULT '' NOT NULL,
        birth_date date DEFAULT NULL,
        occupation varchar(100) DEFAULT '' NOT NULL,
        residential_address text,
        postal_code varchar(10) DEFAULT '' NOT NULL,
        work_address text,
        product_name text,
        installment_plan_id bigint(20) UNSIGNED NOT NULL,
        loan_amount DECIMAL(15,2) NOT NULL,
        facility_amount DECIMAL(15,2) DEFAULT NULL,
        prepayment DECIMAL(15,2) DEFAULT 0,
        wallet_charge DECIMAL(15,2) DEFAULT 0,
        acceptor_payment_amount DECIMAL(15,2) DEFAULT 0,
        final_settlement_amount DECIMAL(15,2) DEFAULT 0,
        leasing_deposit_amount DECIMAL(15,2) DEFAULT NULL,
        installment_interval int(11) NOT NULL DEFAULT 1,
        installment_count int(11) NOT NULL,
        installment_amount DECIMAL(15,2) DEFAULT 0,
        total_payment DECIMAL(15,2) DEFAULT 0,
        profit DECIMAL(15,2) DEFAULT 0,
        required_documents longtext,
        order_items longtext,
        order_total DECIMAL(15,2) DEFAULT 0,
        form_data longtext,
        credit_report_json longtext DEFAULT NULL,
        bank_validation_file text,
        signature_image text,
        contract_file text,
        check_images longtext,
        comments longtext,
        guarantor_info longtext,
        guarantor_info_2 longtext,
        pending_docs_attachments longtext,
        description text,
        national_card_front text,
        national_card_back text,
        status varchar(50) NOT NULL,
        settlement_status varchar(50) DEFAULT '',
        document_receipt_status varchar(30) NOT NULL DEFAULT 'بررسی نشده',
        document_received_date datetime DEFAULT NULL,
        transfer_receipt_files longtext,
        financier_payment_status varchar(30) NOT NULL DEFAULT 'بررسی نشده',
        assigned_bank_user_id bigint(20) UNSIGNED DEFAULT NULL,
        assigned_babanoel_user_id bigint(20) UNSIGNED DEFAULT NULL,
        settlement_cycle_id bigint(20) UNSIGNED DEFAULT NULL,
        settlement_date datetime DEFAULT NULL,
        bank_settlement_status varchar(30) NOT NULL DEFAULT '',
        bank_settlement_note text DEFAULT NULL,
        bank_settled_at datetime DEFAULT NULL,
        bank_settled_by bigint(20) UNSIGNED DEFAULT NULL,
        bank_settlement_updated_at datetime DEFAULT NULL,
        created_at datetime NOT NULL,
        last_edited_at datetime DEFAULT NULL,
        PRIMARY KEY (id),
        KEY user_id (user_id),
        KEY idx_applicant_user_id (applicant_user_id),
        KEY idx_acceptor_user_id (acceptor_user_id),
        KEY idx_request_origin (request_origin),
        KEY idx_acceptor_assignment_mode (acceptor_assignment_mode),
        KEY idx_marketplace_status (marketplace_status),
        KEY idx_marketplace_claimed_by (marketplace_claimed_by),
        KEY idx_acceptor_invitation_code (acceptor_invitation_code),
        KEY idx_acceptor_invited_by (acceptor_invited_by),
        KEY status (status),
        KEY national_code (national_code),
        KEY installment_plan_id (installment_plan_id),
        KEY idx_settlement_cycle_id (settlement_cycle_id),
        KEY idx_assigned_bank_user_id (assigned_bank_user_id),
        KEY idx_bank_settlement_status (bank_settlement_status)
    ) $charset_collate;";
    dbDelta($sql_requests);

    $table_installment_plans = $wpdb->prefix . 'installment_plans';
    $sql_installment_plans = "CREATE TABLE $table_installment_plans (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        name varchar(255) NOT NULL,
        type varchar(50) NOT NULL,
        is_active tinyint(1) DEFAULT 1,
        display_order int unsigned NOT NULL DEFAULT 0,
        settings longtext,
        display_fields longtext,
                display_fields_context longtext,
required_documents text,
        created_at datetime NOT NULL,
        PRIMARY KEY (id)
    ) $charset_collate;";
    dbDelta($sql_installment_plans);

    $table_cooperation_requests = $wpdb->prefix . 'cooperation_requests';
    $sql_cooperation_requests = "CREATE TABLE $table_cooperation_requests (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        request_type varchar(50) NOT NULL,
        status varchar(50) NOT NULL DEFAULT 'در حال بررسی',
        contract_token varchar(64) DEFAULT NULL,
        contract_signed_status varchar(20) NOT NULL DEFAULT 'pending',
        contract_signed_at datetime DEFAULT NULL,
        contract_expires_at datetime DEFAULT NULL,
        otp_code varchar(255) DEFAULT NULL,
        otp_expires_at datetime DEFAULT NULL,
        data longtext,
        files longtext,
        created_at datetime NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY idx_contract_token (contract_token),
        KEY status (status),
        KEY request_type (request_type),
        KEY idx_signed_status (contract_signed_status)
    ) ENGINE=InnoDB $charset_collate;";
    dbDelta($sql_cooperation_requests);

    $table_settlement_cycles = $wpdb->prefix . 'mlr_settlement_cycles';
    $sql_settlement_cycles = "CREATE TABLE $table_settlement_cycles (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id bigint(20) UNSIGNED NOT NULL,
        total_amount DECIMAL(15,2) NOT NULL,
        description text,
        settlement_date datetime NOT NULL,
        PRIMARY KEY (id),
        KEY user_id (user_id)
    ) $charset_collate;";
    dbDelta($sql_settlement_cycles);

    // --- Request Events & Messages Tables ---
    $table_request_events = $wpdb->prefix . 'mlr_request_events';
    $sql_request_events = "CREATE TABLE $table_request_events (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        request_id bigint(20) UNSIGNED NOT NULL,
        user_id bigint(20) UNSIGNED DEFAULT NULL,
        user_role varchar(50) DEFAULT '' NOT NULL,
        event_type varchar(100) DEFAULT '' NOT NULL,
        details longtext,
        created_at datetime NOT NULL,
        PRIMARY KEY (id),
        KEY request_id (request_id),
        KEY user_id (user_id),
        KEY event_type (event_type),
        KEY created_at (created_at)
    ) $charset_collate;";
    dbDelta($sql_request_events);

    $table_request_messages = $wpdb->prefix . 'mlr_request_messages';
    $sql_request_messages = "CREATE TABLE $table_request_messages (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        request_id bigint(20) UNSIGNED NOT NULL,
        user_id bigint(20) UNSIGNED DEFAULT NULL,
        user_role varchar(50) DEFAULT '' NOT NULL,
        message longtext NOT NULL,
        attachments text DEFAULT NULL,
        created_at datetime NOT NULL,
        PRIMARY KEY (id),
        KEY request_id (request_id),
        KEY user_id (user_id),
        KEY created_at (created_at)
    ) $charset_collate;";
    dbDelta($sql_request_messages);

    // 7. جدول ناتیفیکیشن‌های ادمین (Admin Notifications)
    $table_notifications = $wpdb->prefix . 'mlr_admin_notifications';
    $sql_notifications = "CREATE TABLE $table_notifications (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        type varchar(50) NOT NULL,
        message text NOT NULL,
        link text DEFAULT NULL,
        is_read tinyint(1) DEFAULT 0,
        read_by longtext DEFAULT NULL,
        created_at datetime NOT NULL,
        PRIMARY KEY (id),
        KEY is_read (is_read)
    ) $charset_collate;";
    dbDelta($sql_notifications);

    mlr_check_and_update_database_schema();
    if (function_exists('mlr_greenpay_install_schema')) {
        mlr_greenpay_install_schema();
    }

    // Schedule daily purge of admin notifications (clear list every day)
    if (!wp_next_scheduled('mlr_daily_notification_purge')) {
        wp_schedule_event(time(), 'daily', 'mlr_daily_notification_purge');
    }
    if (function_exists('mlr_hardening_activate')) {
        mlr_hardening_activate();
    }

    // Create contract viewer page
    $contract_page_slug = 'cooperation-contract';
    if ( null == get_page_by_path( $contract_page_slug ) ) {
      wp_insert_post(
        array(
          'comment_status' => 'closed',
          'ping_status'    => 'closed',
          'post_author'    => (get_current_user_id() ? get_current_user_id() : 1),
          'post_name'      => $contract_page_slug,
          'post_title'     => 'قرارداد همکاری',
          'post_status'    => 'publish',
          'post_type'      => 'page',
          'post_content'   => '[mlr_cooperation_contract_viewer]'
        )
      );
    }
}

function mlr_deactivate() {
    if (function_exists('mlr_hardening_deactivate')) {
        mlr_hardening_deactivate();
    } else {
        wp_clear_scheduled_hook('mlr_daily_notification_purge');
        wp_clear_scheduled_hook('mlr_hourly_housekeeping');
        wp_clear_scheduled_hook('mlr_api_settings_snapshot_event');
    }
}


add_action('admin_menu', 'mlr_admin_menu');
function mlr_admin_menu() {
    add_menu_page('عملیات تسهیلات', 'عملیات تسهیلات', 'manage_options', 'mlr-loan-requests', 'mlr_loan_requests_page', 'dashicons-money-alt', 6);
    add_submenu_page('mlr-loan-requests', 'همه‌درخواست‌ها', 'همه‌درخواست‌ها', 'manage_options', 'mlr-loan-requests', 'mlr_loan_requests_page');
    add_submenu_page('mlr-loan-requests', 'در حال بررسی', 'در حال بررسی', 'manage_options', 'mlr-loan-requests-review', 'mlr_loan_requests_page');
    add_submenu_page('mlr-loan-requests', 'نقص مدارک', 'نقص مدارک', 'manage_options', 'mlr-loan-requests-missing-docs', 'mlr_loan_requests_page');
    add_submenu_page('mlr-loan-requests', 'رد شده', 'رد شده', 'manage_options', 'mlr-loan-requests-rejected', 'mlr_loan_requests_page');
    add_submenu_page('mlr-loan-requests', 'تأیید کارشناس', 'تأیید کارشناس', 'manage_options', 'mlr-loan-requests-expert-approved', 'mlr_loan_requests_page');
    add_submenu_page('mlr-loan-requests', 'تکمیل مدارک', 'تکمیل مدارک', 'manage_options', 'mlr-loan-requests-docs-complete', 'mlr_loan_requests_page');
    add_submenu_page('mlr-loan-requests', 'تأیید مدارک', 'تأیید مدارک', 'manage_options', 'mlr-loan-requests-docs-approved', 'mlr_loan_requests_page');
    
    add_submenu_page('mlr-loan-requests', 'اعطا شده', 'اعطا شده', 'manage_options', 'mlr-granted-requests', 'mlr_granted_requests_page');
    add_submenu_page('mlr-loan-requests', 'در انتظار تسویه', 'در انتظار تسویه', 'manage_options', 'mlr-unsettled-requests', 'mlr_unsettled_requests_page');
    add_submenu_page('mlr-loan-requests', 'تسویه شده', 'تسویه شده', 'manage_options', 'mlr-settled-requests', 'mlr_settled_requests_page');
    add_submenu_page('mlr-loan-requests', 'طرح‌های اقساطی', 'طرح‌های اقساطی', 'manage_options', 'mlr-installment-plans', 'mlr_installment_plans_page');
    add_submenu_page('mlr-loan-requests', 'تنظیمات API', 'تنظیمات API', 'manage_options', 'mlr-api-settings', 'mlr_api_settings_page_callback');
    add_submenu_page('mlr-loan-requests', 'تنظیمات بازار پذیرنده', 'بازار پذیرنده', 'manage_options', 'mlr-acceptor-marketplace-settings', 'mlr_acceptor_marketplace_settings_page');

    // کارشناس عملیات بابانوئل (نقش amaliat) — فقط برای کاربران دارای قابلیت mlr_amaliat_operations
    add_submenu_page('mlr-loan-requests', 'کارشناس عملیات', 'کارشناس عملیات', 'mlr_amaliat_operations', 'mlr-amaliat-operations', 'mlr_amaliat_operations_page');

    // کارشناس مالی بابانوئل (نقش mali) — فقط برای کاربران دارای قابلیت mlr_mali_operations
    add_submenu_page('mlr-loan-requests', 'کارشناس مالی', 'کارشناس مالی', 'mlr_mali_operations', 'mlr-mali-requests', 'mlr_mali_requests_page');

    add_submenu_page('mlr-loan-requests', 'اصلاح اطلاعات پرونده‌ها', 'اصلاح اطلاعات پرونده‌ها', 'manage_options', 'mlr-record-corrections', 'mlr_rc_page');

    add_menu_page('مدیریت همکاری', 'مدیریت همکاری', 'manage_options', 'mlr-cooperation-main', 'mlr_cooperation_requests_page', 'dashicons-groups', 7);
    add_submenu_page('mlr-cooperation-main', 'درخواست‌های همکاری', 'درخواست‌های همکاری', 'manage_options', 'mlr-cooperation-main', 'mlr_cooperation_requests_page');
    add_submenu_page('mlr-cooperation-main', 'ویرایشگر فرم همکاری', 'ویرایشگر فرم', 'manage_options', 'mlr-form-editor', 'mlr_form_editor_page');
    add_submenu_page('mlr-cooperation-main', 'تنظیمات قرارداد', 'تنظیمات قرارداد', 'manage_options', 'mlr-cooperation-contract-settings', 'mlr_cooperation_contract_settings_page');

    add_menu_page('تنظیمات پیامک', 'تنظیمات پیامک', 'manage_options', 'mlr-sms-settings', 'mlr_sms_settings_page', 'dashicons-email-alt', 8);
    add_submenu_page('mlr-sms-settings', 'مشاهده لاگ', 'مشاهده لاگ', 'manage_options', 'mlr-log-viewer', 'mlr_log_viewer_page');

    // منوی مستقل «تسویه بانک» فقط برای مدیر/سوپرادمین و نقش مالی بابانوئل.
    add_menu_page('تسویه بانک با بابانوئل', 'تسویه بانک', 'mlr_view_bank_settlements', 'mlr-bank-settlements', 'mlr_bank_settlements_page', 'dashicons-bank', 6.25);

    // منوی «تاریخ تسویه» برای نقش‌های administrator و mali
    add_menu_page('تاریخ تسویه', 'تاریخ تسویه', 'mlr_edit_settlement_date', 'mlr-settlement-date-editor', 'mlr_settlement_date_editor_page', 'dashicons-calendar-alt', 6.3);
    add_submenu_page('mlr-settlement-date-editor', 'ویرایش تاریخ تسویه', 'ویرایش تاریخ تسویه', 'mlr_edit_settlement_date', 'mlr-settlement-date-editor', 'mlr_settlement_date_editor_page');
    add_submenu_page('mlr-settlement-date-editor', 'اعطا شده (تعیین تاریخ)', 'اعطا شده', 'mlr_edit_settlement_date', 'mlr-granted-requests', 'mlr_granted_requests_page');
}


add_action('admin_init', 'mlr_register_plugin_settings');
function mlr_register_plugin_settings() {
    register_setting('mlr_api_settings_group', 'mlr_shahkar_client_id', ['sanitize_callback' => 'mlr_sanitize_preserve_shahkar_client_id']);
    register_setting('mlr_api_settings_group', 'mlr_shahkar_client_secret', ['sanitize_callback' => 'mlr_sanitize_preserve_shahkar_client_secret']);
    register_setting('mlr_api_settings_group', 'mlr_shahkar_owner_nid', ['sanitize_callback' => 'mlr_sanitize_preserve_shahkar_owner_nid']);
    register_setting('mlr_api_settings_group', 'mlr_calculator_plan_selector_mode', ['sanitize_callback' => 'mlr_sanitize_calculator_plan_selector_mode', 'default' => 'tabs']);
    register_setting('mlr_sms_settings_group', 'mlr_sms_settings', ['sanitize_callback' => 'mlr_sanitize_sms_settings']);
    register_setting('mlr_cooperation_contract_settings_group', 'mlr_cooperation_contract_settings', ['sanitize_callback' => 'mlr_sanitize_cooperation_contract_settings']);
    register_setting('mlr_cooperation_form_editor_group', 'mlr_cooperation_form_options', ['sanitize_callback' => 'mlr_sanitize_cooperation_form_options']);
    register_setting('mlr_cooperation_form_editor_group', 'mlr_cooperation_form_config', ['sanitize_callback' => 'mlr_sanitize_cooperation_form_config']);
    register_setting('mlr_acceptor_marketplace_settings_group', 'mlr_acceptor_marketplace_settings', ['sanitize_callback' => 'mlr_sanitize_acceptor_marketplace_settings']);
}

function mlr_sanitize_calculator_plan_selector_mode($input) {
    $input = sanitize_key($input);
    return in_array($input, ['tabs', 'dropdown'], true) ? $input : 'tabs';
}

function mlr_sanitize_cooperation_form_options($input) {
    $sanitized_input = [];
    $option_keys = ['activity_field_options', 'how_heard_options'];
    foreach ($option_keys as $key) {
        if (isset($input[$key])) {
            $lines = explode("\n", $input[$key]);
            $options = [];
            foreach ($lines as $line) {
                $trimmed = trim($line);
                if (!empty($trimmed)) $options[] = sanitize_text_field($trimmed);
            }
            $sanitized_input[$key] = $options;
        } else {
            $sanitized_input[$key] = [];
        }
    }
    return $sanitized_input;
}

function mlr_sanitize_cooperation_form_config($input) {
    $sanitized_input = [];
    $all_fields = mlr_get_all_cooperation_fields();
    if (!is_array($input)) $input = [];

    foreach ($all_fields as $field_key => $field_info) {
        if ($field_info['type'] === 'file') {
             $sanitized_input[$field_key]['visible'] = isset($input[$field_key]['visible']) ? 1 : 0;
        } else {
             $sanitized_input[$field_key]['visible'] = 1;
        }
        $is_visible = $sanitized_input[$field_key]['visible'];
        $sanitized_input[$field_key]['required'] = (isset($input[$field_key]['required']) && $is_visible) ? 1 : 0;
    }
    return $sanitized_input;
}

function mlr_sanitize_acceptor_marketplace_settings($input) {
    if (!is_array($input)) {
        $input = [];
    }

    $sanitized_input = [];
    foreach ([
        'enabled',
        'allow_applicant_selected_acceptor',
        'allow_open_marketplace',
        'require_category',
        'require_location',
        'allow_all_country',
        'show_claimed_cards_to_other_acceptors',
        'enable_acceptor_invitation_links',
        'allow_override_existing_invitation_lock',
    ] as $key) {
        $sanitized_input[$key] = isset($input[$key]) ? 1 : 0;
    }

    if (!isset($input['require_category']) && isset($input['require_activity_category'])) {
        $sanitized_input['require_category'] = !empty($input['require_activity_category']) ? 1 : 0;
    }
    if (!isset($input['require_location']) && isset($input['require_city_when_selected_scope'])) {
        $sanitized_input['require_location'] = !empty($input['require_city_when_selected_scope']) ? 1 : 0;
    }

    $post_type = sanitize_key($input['acceptor_cpt_slug'] ?? 'seller');
    $category_taxonomy = sanitize_key($input['acceptor_category_taxonomy'] ?? 'seller-sub');
    $location_taxonomy = sanitize_key($input['acceptor_location_taxonomy'] ?? 'seller-cat');
    $sanitized_input['acceptor_cpt_slug'] = $post_type ?: 'seller';
    $sanitized_input['acceptor_category_taxonomy'] = $category_taxonomy ?: 'seller-sub';
    $sanitized_input['acceptor_location_taxonomy'] = $location_taxonomy ?: 'seller-cat';

    $sanitized_input['claimed_cards_visible_days'] = isset($input['claimed_cards_visible_days'])
        ? max(1, min(90, absint($input['claimed_cards_visible_days'])))
        : 7;
    $sanitized_input['invitation_lock_days'] = isset($input['invitation_lock_days'])
        ? max(1, min(365, absint($input['invitation_lock_days'])))
        : 30;
    $sanitized_input['cities'] = [];
    $sanitized_input['activity_categories'] = [];
    $sanitized_input['applicant_help_text'] = isset($input['applicant_help_text']) ? sanitize_textarea_field(wp_unslash($input['applicant_help_text'])) : '';
    $sanitized_input['acceptor_shortcode_help_text'] = isset($input['acceptor_shortcode_help_text']) ? sanitize_textarea_field(wp_unslash($input['acceptor_shortcode_help_text'])) : '';

    return $sanitized_input;
}

function mlr_sanitize_cooperation_contract_settings($input) {
    $sanitized_input = [];
    if (!is_array($input)) $input = [];
    $sanitized_input['header_image_url'] = isset($input['header_image_url']) ? esc_url_raw(trim($input['header_image_url'])) : '';
    $sanitized_input['footer_image_url'] = isset($input['footer_image_url']) ? esc_url_raw(trim($input['footer_image_url'])) : '';
    $sanitized_input['contract_text'] = isset($input['contract_text']) ? wp_kses_post($input['contract_text']) : '';
    return $sanitized_input;
}

function mlr_sanitize_sms_settings($input) {
    $sanitized_input = [];
    if (!is_array($input)) $input = [];

    $sanitized_input['username'] = isset($input['username']) ? sanitize_text_field(trim($input['username'])) : '';
    $current = get_option('mlr_sms_settings', []);
    if (!empty($input['password'])) {
        $sanitized_input['password'] = sanitize_text_field(trim($input['password']));
    } elseif (isset($current['password'])) {
        $sanitized_input['password'] = $current['password'];
    } else {
        $sanitized_input['password'] = '';
    }

    foreach (['cooperation_statuses', 'loan_statuses', 'otp_settings'] as $group_key) {
        if (isset($input[$group_key]) && is_array($input[$group_key])) {
            foreach ($input[$group_key] as $key => $settings) {
                if (!is_array($settings)) continue;
                $sanitized_key = sanitize_text_field($key);
                $sanitized_input[$group_key][$sanitized_key]['enabled'] = isset($settings['enabled']) ? 1 : 0;
                $sanitized_input[$group_key][$sanitized_key]['bodyId'] = isset($settings['bodyId']) ? sanitize_text_field(trim($settings['bodyId'])) : '';

                for ($i = 0; $i < 3; $i++) {
                    $var = "var_{$i}";
                    $sanitized_input[$group_key][$sanitized_key][$var] = isset($settings[$var]) ? sanitize_text_field(trim($settings[$var])) : '';
                }

                if ($group_key === 'loan_statuses') {
                    $sanitized_input[$group_key][$sanitized_key]['send_to_applicant'] = isset($settings['send_to_applicant']) ? 1 : 0;
                    $sanitized_input[$group_key][$sanitized_key]['send_to_acceptor'] = isset($settings['send_to_acceptor']) ? 1 : 0;
                }
            }
        }
    }
    return $sanitized_input;
}

function mlr_get_all_cooperation_fields() {
    return [
        'store_name' => ['type' => 'text', 'form' => 'individual', 'label' => 'نام فروشگاه', 'default_required' => true, 'default_visible' => true],
        'store_owner_name' => ['type' => 'text', 'form' => 'individual', 'label' => 'نام مالک فروشگاه', 'default_required' => true, 'default_visible' => true],
        'store_address' => ['type' => 'textarea', 'form' => 'individual', 'label' => 'آدرس فروشگاه', 'default_required' => true, 'default_visible' => true],
        'store_images' => ['type' => 'file', 'form' => 'individual', 'label' => 'تصاویر فروشگاه', 'default_required' => true, 'default_visible' => true],

        'company_name' => ['type' => 'text', 'form' => 'legal', 'label' => 'نام شرکت', 'default_required' => true, 'default_visible' => true],
        'contact_person' => ['type' => 'text', 'form' => 'legal', 'label' => 'نام رابط', 'default_required' => true, 'default_visible' => true],
        'company_address' => ['type' => 'textarea', 'form' => 'legal', 'label' => 'آدرس شرکت', 'default_required' => true, 'default_visible' => true],
        'email' => ['type' => 'email', 'form' => 'legal', 'label' => 'ایمیل', 'default_required' => true, 'default_visible' => true],
        'company_images' => ['type' => 'file', 'form' => 'legal', 'label' => 'تصاویر شرکت', 'default_required' => true, 'default_visible' => true],
        'company_docs' => ['type' => 'file', 'form' => 'legal', 'label' => 'اساسنامه و روزنامه رسمی', 'default_required' => true, 'default_visible' => true],

        'activity_field' => ['type' => 'select', 'form' => 'both', 'label' => 'زمینه فعالیت', 'options_key' => 'activity_field_options', 'default_required' => true, 'default_visible' => true],
        'how_heard' => ['type' => 'select', 'form' => 'both', 'label' => 'نحوه آشنایی با ما', 'options_key' => 'how_heard_options', 'default_required' => false, 'default_visible' => true],
        'activity_description' => ['type' => 'text', 'form' => 'both', 'label' => 'شرح فعالیت', 'default_required' => false, 'default_visible' => true],
        'postal_code' => ['type' => 'text', 'form' => 'both', 'label' => 'کد پستی', 'default_required' => true, 'default_visible' => true],
        'applicant_id_number' => ['type' => 'text', 'form' => 'both', 'label' => 'کد ملی / شناسه ملی', 'default_required' => true, 'default_visible' => true],
        'mobile_number' => ['type' => 'text', 'form' => 'both', 'label' => 'شماره همراه', 'default_required' => true, 'default_visible' => true],
        'phone_number' => ['type' => 'text', 'form' => 'both', 'label' => 'تلفن ثابت', 'default_required' => false, 'default_visible' => true],
        'website' => ['type' => 'text', 'form' => 'both', 'label' => 'آدرس سایت', 'default_required' => false, 'default_visible' => true],
        'branches' => ['type' => 'textarea', 'form' => 'both', 'label' => 'آدرس شعب', 'default_required' => false, 'default_visible' => true],
        'description' => ['type' => 'textarea', 'form' => 'both', 'label' => 'توضیحات تکمیلی', 'default_required' => false, 'default_visible' => true],

        'national_card' => ['type' => 'file', 'form' => 'both', 'label' => 'کارت ملی (رو و پشت)', 'default_required' => true, 'default_visible' => true],
        'id_card' => ['type' => 'file', 'form' => 'both', 'label' => 'شناسنامه (تمام صفحات)', 'default_required' => true, 'default_visible' => true],
        'business_license' => ['type' => 'file', 'form' => 'both', 'label' => 'جواز کسب', 'default_required' => true, 'default_visible' => true],
        'ownership_or_lease' => ['type' => 'file', 'form' => 'both', 'label' => 'سند مالکیت یا اجاره‌نامه', 'default_required' => true, 'default_visible' => true],
        'thana_certificate' => ['type' => 'file', 'form' => 'both', 'label' => 'گواهی ثبت ثنا', 'default_required' => true, 'default_visible' => true],
        'logo' => ['type' => 'file', 'form' => 'both', 'label' => 'لوگو', 'default_required' => false, 'default_visible' => true],
        'check_image' => ['type' => 'file', 'form' => 'both', 'label' => 'تصویر چک صیادی', 'default_required' => false, 'default_visible' => true],
        'signature_certificate' => ['type' => 'file', 'form' => 'both', 'label' => 'گواهی امضا', 'default_required' => false, 'default_visible' => true],
        'tax_info' => ['type' => 'file', 'form' => 'both', 'label' => 'اطلاعات سامانه مالیاتی', 'default_required' => false, 'default_visible' => true],
        'enamad' => ['type' => 'file', 'form' => 'both', 'label' => 'تصویر اینماد', 'default_required' => false, 'default_visible' => true],
    ];
}


function mlr_api_settings_page_callback() {
    ?>
    <div class="wrap mlr-admin-wrap">
        <h1>تنظیمات API و محاسبه‌گر</h1>
        <p>تنظیمات سرویس شاهکار و ظاهر محاسبه‌گر مرکزی از این بخش مدیریت می‌شود.</p>
        <form method="post" action="options.php">
            <?php settings_fields('mlr_api_settings_group'); ?>
            <?php do_settings_sections('mlr_api_settings_group'); ?>
             <div class="mlr-settings-card">
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row"><label for="mlr_shahkar_client_id">Client ID</label></th>
                        <td><input type="text" id="mlr_shahkar_client_id" name="mlr_shahkar_client_id" value="<?php echo esc_attr(get_option('mlr_shahkar_client_id')); ?>" class="regular-text ltr" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><label for="mlr_shahkar_client_secret">Client Secret</label></th>
                        <td><input type="password" id="mlr_shahkar_client_secret" name="mlr_shahkar_client_secret" value="" placeholder="<?php echo esc_attr(get_option('mlr_shahkar_client_secret')) ? '•••••••• (برای تغییر، مقدار جدید را وارد کنید)' : ''; ?>" class="regular-text ltr" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><label for="mlr_shahkar_owner_nid">Owner NID <br>(کد ملی مالک سرویس)</label></th>
                        <td><input type="text" id="mlr_shahkar_owner_nid" name="mlr_shahkar_owner_nid" value="<?php echo esc_attr(get_option('mlr_shahkar_owner_nid')); ?>" class="regular-text ltr" pattern="[0-9]{10}" title="کد ملی 10 رقمی" />
                        <p class="description">کد ملی شخصی که سرویس فینوتک به نام ایشان ثبت شده است.</p></td>
                    </tr>
                </table>
            </div>
            <div class="mlr-settings-card" style="margin-top:16px;">
                <h2>تنظیمات محاسبه‌گر مرکزی</h2>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">حالت نمایش انتخاب طرح</th>
                        <td>
                            <?php $selector_mode = get_option('mlr_calculator_plan_selector_mode', 'tabs'); ?>
                            <fieldset>
                                <label style="display:block;margin-bottom:8px;">
                                    <input type="radio" name="mlr_calculator_plan_selector_mode" value="tabs" <?php checked($selector_mode, 'tabs'); ?> />
                                    حالت دکمه‌ای فعلی
                                </label>
                                <label style="display:block;margin-bottom:8px;">
                                    <input type="radio" name="mlr_calculator_plan_selector_mode" value="dropdown" <?php checked($selector_mode, 'dropdown'); ?> />
                                    حالت دراپ‌داون سفارشی
                                </label>
                            </fieldset>
                            <p class="description">برای زمانی که تعداد طرح‌های تسهیلات زیاد است، حالت دراپ‌داون باعث خلوت‌تر شدن محاسبه‌گر صفحه اصلی می‌شود.</p>
                        </td>
                    </tr>
                </table>
            </div>
            <?php do_action('mlr_api_settings_page_extra_sections'); ?>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

function mlr_acceptor_marketplace_settings_page() {
    if (!current_user_can('manage_options')) {
        wp_die('شما دسترسی لازم برای مشاهده این صفحه را ندارید.');
    }

    if (
        $_SERVER['REQUEST_METHOD'] === 'POST'
        && isset($_POST['mlr_acceptor_marketplace_settings_nonce'])
        && check_admin_referer('mlr_acceptor_marketplace_settings_save', 'mlr_acceptor_marketplace_settings_nonce')
    ) {
        $input = isset($_POST['mlr_acceptor_marketplace_settings'])
            ? wp_unslash($_POST['mlr_acceptor_marketplace_settings'])
            : [];
        update_option('mlr_acceptor_marketplace_settings', mlr_sanitize_acceptor_marketplace_settings($input));
        wp_safe_redirect(add_query_arg([
            'page' => 'mlr-acceptor-marketplace-settings',
            'settings-updated' => 'true',
        ], admin_url('admin.php')));
        exit;
    }

    $settings = function_exists('mlr_get_acceptor_marketplace_settings') ? mlr_get_acceptor_marketplace_settings() : [];
    $post_types = get_post_types([], 'objects');
    $taxonomies = get_taxonomies([], 'objects');
    $selected_post_type = sanitize_key($settings['acceptor_cpt_slug'] ?? 'seller');
    $selected_category_taxonomy = sanitize_key($settings['acceptor_category_taxonomy'] ?? 'seller-sub');
    $selected_location_taxonomy = sanitize_key($settings['acceptor_location_taxonomy'] ?? 'seller-cat');

    $seller_count = 0;
    if (post_type_exists($selected_post_type)) {
        $counts = wp_count_posts($selected_post_type);
        foreach (['publish', 'private', 'draft', 'pending'] as $status_key) {
            $seller_count += isset($counts->{$status_key}) ? absint($counts->{$status_key}) : 0;
        }
    }
    $category_count = taxonomy_exists($selected_category_taxonomy) ? wp_count_terms(['taxonomy' => $selected_category_taxonomy, 'hide_empty' => false]) : 0;
    $location_count = taxonomy_exists($selected_location_taxonomy) ? wp_count_terms(['taxonomy' => $selected_location_taxonomy, 'hide_empty' => false]) : 0;
    $category_count = is_wp_error($category_count) ? 0 : absint($category_count);
    $location_count = is_wp_error($location_count) ? 0 : absint($location_count);
    ?>
    <div class="wrap mlr-admin-wrap">
        <h1>تنظیمات بازار پذیرنده</h1>
        <p>این تنظیمات فقط روی اتصال درخواست‌های مستقیم متقاضی به پذیرنده اثر می‌گذارد. دسته‌بندی و موقعیت از taxonomyهای وردپرس خوانده می‌شود و لیست دستی استفاده نمی‌شود.</p>

        <?php if (isset($_GET['settings-updated']) && $_GET['settings-updated']) : ?>
            <div id="message" class="updated notice is-dismissible"><p>تنظیمات با موفقیت ذخیره شد.</p></div>
        <?php endif; ?>

        <form method="post" action="<?php echo esc_url(admin_url('admin.php?page=mlr-acceptor-marketplace-settings')); ?>">
            <?php wp_nonce_field('mlr_acceptor_marketplace_settings_save', 'mlr_acceptor_marketplace_settings_nonce'); ?>
            <div class="mlr-settings-card">
                <h2>وضعیت قابلیت</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">فعال‌سازی</th>
                        <td>
                            <label><input type="checkbox" name="mlr_acceptor_marketplace_settings[enabled]" value="1" <?php checked(!empty($settings['enabled'])); ?>> بازار پذیرنده برای فرم مستقیم متقاضی فعال باشد</label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">حالت‌های مجاز</th>
                        <td>
                            <label style="display:block;margin-bottom:8px;"><input type="checkbox" name="mlr_acceptor_marketplace_settings[allow_applicant_selected_acceptor]" value="1" <?php checked(!empty($settings['allow_applicant_selected_acceptor'])); ?>> متقاضی بتواند پذیرنده مشخص انتخاب کند</label>
                            <label style="display:block;"><input type="checkbox" name="mlr_acceptor_marketplace_settings[allow_open_marketplace]" value="1" <?php checked(!empty($settings['allow_open_marketplace'])); ?>> متقاضی بتواند درخواست را در بازار آزاد پذیرنده‌ها ثبت کند</label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">اعتبارسنجی فرم</th>
                        <td>
                            <label style="display:block;margin-bottom:8px;"><input type="checkbox" name="mlr_acceptor_marketplace_settings[require_location]" value="1" <?php checked(!empty($settings['require_location'])); ?>> در حالت موقعیت‌های منتخب، انتخاب حداقل یک موقعیت الزامی باشد</label>
                            <label style="display:block;margin-bottom:8px;"><input type="checkbox" name="mlr_acceptor_marketplace_settings[require_category]" value="1" <?php checked(!empty($settings['require_category'])); ?>> انتخاب حداقل یک حوزه فعالیت الزامی باشد</label>
                            <label style="display:block;"><input type="checkbox" name="mlr_acceptor_marketplace_settings[allow_all_country]" value="1" <?php checked(!empty($settings['allow_all_country'])); ?>> گزینه «کل کشور» در فرم متقاضی فعال باشد</label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">کارت‌های پذیرفته‌شده</th>
                        <td>
                            <label style="display:block;margin-bottom:8px;"><input type="checkbox" name="mlr_acceptor_marketplace_settings[show_claimed_cards_to_other_acceptors]" value="1" <?php checked(!empty($settings['show_claimed_cards_to_other_acceptors'])); ?>> کارت‌های claim شده برای سایر پذیرنده‌ها به صورت غیرفعال نمایش داده شود</label>
                            <label for="mlr-claimed-days">مدت نمایش:</label>
                            <input type="number" min="1" max="90" id="mlr-claimed-days" name="mlr_acceptor_marketplace_settings[claimed_cards_visible_days]" value="<?php echo esc_attr($settings['claimed_cards_visible_days'] ?? 7); ?>" class="small-text"> روز
                        </td>
                    </tr>
                </table>
            </div>

            <div class="mlr-settings-card">
                <h2>منبع پذیرنده‌ها و taxonomyها</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="mlr-acceptor-cpt-slug">CPT پذیرنده</label></th>
                        <td>
                            <select id="mlr-acceptor-cpt-slug" name="mlr_acceptor_marketplace_settings[acceptor_cpt_slug]">
                                <?php foreach ($post_types as $post_type): ?>
                                    <?php if (in_array($post_type->name, ['attachment', 'revision', 'nav_menu_item', 'custom_css', 'customize_changeset', 'user_request', 'wp_block', 'wp_template', 'wp_template_part', 'wp_navigation'], true)) { continue; } ?>
                                    <option value="<?php echo esc_attr($post_type->name); ?>" <?php selected($selected_post_type, $post_type->name); ?>>
                                        <?php echo esc_html($post_type->labels->singular_name ?: $post_type->label ?: $post_type->name); ?> (<?php echo esc_html($post_type->name); ?>)
                                    </option>
                                <?php endforeach; ?>
                                <?php if (!isset($post_types[$selected_post_type])): ?>
                                    <option value="<?php echo esc_attr($selected_post_type); ?>" selected><?php echo esc_html($selected_post_type); ?></option>
                                <?php endif; ?>
                            </select>
                            <p class="description">
                                وضعیت: <?php echo post_type_exists($selected_post_type) ? '<span style="color:#008a20;">شناخته شد</span>' : '<span style="color:#b32d2e;">پیدا نشد</span>'; ?>
                                | تعداد پست‌های مرتبط: <?php echo esc_html(number_format_i18n($seller_count)); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="mlr-category-taxonomy">taxonomy حوزه فعالیت</label></th>
                        <td>
                            <select id="mlr-category-taxonomy" name="mlr_acceptor_marketplace_settings[acceptor_category_taxonomy]">
                                <?php foreach ($taxonomies as $taxonomy): ?>
                                    <option value="<?php echo esc_attr($taxonomy->name); ?>" <?php selected($selected_category_taxonomy, $taxonomy->name); ?>>
                                        <?php echo esc_html($taxonomy->labels->singular_name ?: $taxonomy->label ?: $taxonomy->name); ?> (<?php echo esc_html($taxonomy->name); ?>)
                                    </option>
                                <?php endforeach; ?>
                                <?php if (!isset($taxonomies[$selected_category_taxonomy])): ?>
                                    <option value="<?php echo esc_attr($selected_category_taxonomy); ?>" selected><?php echo esc_html($selected_category_taxonomy); ?></option>
                                <?php endif; ?>
                            </select>
                            <p class="description">
                                وضعیت: <?php echo taxonomy_exists($selected_category_taxonomy) ? '<span style="color:#008a20;">شناخته شد</span>' : '<span style="color:#b32d2e;">پیدا نشد</span>'; ?>
                                | تعداد term: <?php echo esc_html(number_format_i18n($category_count)); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="mlr-location-taxonomy">taxonomy موقعیت</label></th>
                        <td>
                            <select id="mlr-location-taxonomy" name="mlr_acceptor_marketplace_settings[acceptor_location_taxonomy]">
                                <?php foreach ($taxonomies as $taxonomy): ?>
                                    <option value="<?php echo esc_attr($taxonomy->name); ?>" <?php selected($selected_location_taxonomy, $taxonomy->name); ?>>
                                        <?php echo esc_html($taxonomy->labels->singular_name ?: $taxonomy->label ?: $taxonomy->name); ?> (<?php echo esc_html($taxonomy->name); ?>)
                                    </option>
                                <?php endforeach; ?>
                                <?php if (!isset($taxonomies[$selected_location_taxonomy])): ?>
                                    <option value="<?php echo esc_attr($selected_location_taxonomy); ?>" selected><?php echo esc_html($selected_location_taxonomy); ?></option>
                                <?php endif; ?>
                            </select>
                            <p class="description">
                                وضعیت: <?php echo taxonomy_exists($selected_location_taxonomy) ? '<span style="color:#008a20;">شناخته شد</span>' : '<span style="color:#b32d2e;">پیدا نشد</span>'; ?>
                                | تعداد term: <?php echo esc_html(number_format_i18n($location_count)); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">لینک اختصاصی پذیرنده</th>
                        <td>
                            <label style="display:block;margin-bottom:8px;"><input type="checkbox" name="mlr_acceptor_marketplace_settings[enable_acceptor_invitation_links]" value="1" <?php checked(!empty($settings['enable_acceptor_invitation_links'])); ?>> لینک اختصاصی ثبت درخواست فعال باشد</label>
                            <label for="mlr-invitation-lock-days">مدت قفل پذیرنده:</label>
                            <input type="number" min="1" max="365" id="mlr-invitation-lock-days" name="mlr_acceptor_marketplace_settings[invitation_lock_days]" value="<?php echo esc_attr($settings['invitation_lock_days'] ?? 30); ?>" class="small-text"> روز
                            <br>
                            <label style="display:block;margin-top:8px;"><input type="checkbox" name="mlr_acceptor_marketplace_settings[allow_override_existing_invitation_lock]" value="1" <?php checked(!empty($settings['allow_override_existing_invitation_lock'])); ?>> لینک جدید بتواند قفل قبلی متقاضی را جایگزین کند</label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="mlr-applicant-help-text">متن راهنمای متقاضی</label></th>
                        <td><textarea id="mlr-applicant-help-text" name="mlr_acceptor_marketplace_settings[applicant_help_text]" rows="3" class="large-text"><?php echo esc_textarea($settings['applicant_help_text'] ?? ''); ?></textarea></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="mlr-acceptor-help-text">متن راهنمای پذیرنده</label></th>
                        <td><textarea id="mlr-acceptor-help-text" name="mlr_acceptor_marketplace_settings[acceptor_shortcode_help_text]" rows="3" class="large-text"><?php echo esc_textarea($settings['acceptor_shortcode_help_text'] ?? ''); ?></textarea></td>
                    </tr>
                </table>
            </div>

            <?php submit_button('ذخیره تنظیمات'); ?>
        </form>
    </div>
    <?php
}

add_action('admin_enqueue_scripts', 'mlr_enqueue_admin_scripts');
function mlr_enqueue_admin_scripts($hook) {
    // --- 1. Enqueue Admin Notifications (Global for all admin pages) ---
    wp_enqueue_style('mlr-admin-notifications-css', MLR_PLUGIN_URL . 'assets/css/admin-notifications.css', [], MLR_VERSION);
    wp_enqueue_script('mlr-admin-notifications-js', MLR_PLUGIN_URL . 'assets/js/admin-notifications.js', ['jquery'], MLR_VERSION, true);
    wp_localize_script('mlr-admin-notifications-js', 'mlrNotificationAjax', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('mlr_ajax_nonce_action'),
        'sound_url' => MLR_PLUGIN_URL . 'assets/sounds/notification.mp3'
    ]);

    // --- 2. Check if we are on a plugin specific page ---
     $plugin_pages = [
        'toplevel_page_mlr-loan-requests',
        'mlr-loan-requests_page_mlr-granted-requests',
        'mlr-loan-requests_page_mlr-settlement-date-editor',
        'mlr-loan-requests_page_mlr-unsettled-requests',
        'mlr-loan-requests_page_mlr-settled-requests',
        'mlr-loan-requests_page_mlr-installment-plans',
        'mlr-loan-requests_page_mlr-api-settings',
        'mlr-loan-requests_page_mlr-acceptor-marketplace-settings',
        'عملیات-تسهیلات_page_mlr-granted-requests',
        'عملیات-تسهیلات_page_mlr-settlement-date-editor',
        'عملیات-تسهیلات_page_mlr-unsettled-requests',
        'عملیات-تسهیلات_page_mlr-settled-requests',
        'عملیات-تسهیلات_page_mlr-installment-plans',
        'عملیات-تسهیلات_page_mlr-api-settings',
        'عملیات-تسهیلات_page_mlr-acceptor-marketplace-settings',
        'toplevel_page_mlr-cooperation-main',
        'مدیریت-همکاری_page_mlr-form-editor',
        'مدیریت-همکاری_page_mlr-cooperation-contract-settings',
        'toplevel_page_mlr-sms-settings',
        'تنظیمات-پیامک_page_mlr-log-viewer',
        'toplevel_page_mlr-bank-settlements',
        'toplevel_page_mlr-settlement-date-editor',
        'تاریخ-تسویه_page_mlr-settlement-date-editor',
    ];

    $is_plugin_page = in_array($hook, $plugin_pages);

    // Fallback: some environments may produce different hook suffixes for submenu pages.
    if (!$is_plugin_page && isset($_GET['page'])) {
        $page = sanitize_key(wp_unslash($_GET['page']));
        if (in_array($page, ['mlr-granted-requests', 'mlr-settlement-date-editor', 'mlr-unsettled-requests', 'mlr-settled-requests', 'mlr-bank-settlements', 'mlr-installment-plans', 'mlr-api-settings', 'mlr-acceptor-marketplace-settings', 'mlr-loan-requests', 'mlr-loan-requests-review', 'mlr-loan-requests-missing-docs', 'mlr-loan-requests-rejected', 'mlr-loan-requests-expert-approved', 'mlr-loan-requests-docs-complete', 'mlr-loan-requests-docs-approved', 'mlr-amaliat-operations', 'mlr-mali-requests'], true)) {
            $is_plugin_page = true;
        }
        if (!$is_plugin_page && isset($_GET['page']) && $_GET['page'] === 'mlr-settlement-date-editor') {
            $is_plugin_page = true;
        }
    }
    if (!$is_plugin_page && ($hook === 'profile.php' || $hook === 'user-edit.php')) {
         $is_plugin_page = true;
    }

    if (!$is_plugin_page) return;

    wp_enqueue_style('mlr-admin-css', MLR_PLUGIN_URL . 'assets/css/admin.css', [], MLR_VERSION);
    // Unified popup/modal styles (admin + frontend) - must load after admin.css to override
    wp_enqueue_style('mlr-modal-unified-css', MLR_PLUGIN_URL . 'assets/css/mlr-modal-unified.css', ['mlr-admin-css'], MLR_VERSION);

    if ($hook === 'مدیریت-همکاری_page_mlr-cooperation-contract-settings' || $hook === 'عملیات-تسهیلات_page_mlr-installment-plans') {
  // Add for enqueuing admin notification
        wp_enqueue_style('mlr-admin-notifications', MLR_PLUGIN_URL . 'assets/css/admin-notifications.css', array(), MLR_VERSION);
        wp_enqueue_media();
    }

    wp_enqueue_script('mlr-jalali-datepicker', MLR_PLUGIN_URL . 'assets/js/jalali-datepicker.js', ['jquery'], MLR_VERSION, true);
    wp_enqueue_script('mlr-file-upload-helper', MLR_PLUGIN_URL . 'assets/js/mlr-file-upload-helper.js', [], MLR_VERSION, true);
    wp_enqueue_script('mlr-guarantor-form', MLR_PLUGIN_URL . 'assets/js/mlr-guarantor-form.js', ['jquery', 'mlr-file-upload-helper'], MLR_VERSION, true);
    wp_enqueue_style('mlr-contract-print-css', MLR_PLUGIN_URL . 'assets/css/contract-print.css', [], MLR_VERSION);
    wp_enqueue_script('mlr-contract-print-js', MLR_PLUGIN_URL . 'assets/js/mlr-contract-print.js', [], MLR_VERSION, true);
    wp_enqueue_script('mlr-admin-js', MLR_PLUGIN_URL . 'assets/js/admin.js', ['jquery', 'mlr-jalali-datepicker', 'mlr-guarantor-form', 'mlr-contract-print-js'], MLR_VERSION, true);

    wp_localize_script('mlr-admin-js', 'mlrAjax', [
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce'   => wp_create_nonce('mlr_ajax_nonce_action')

    ]);

    if ($hook === 'مدیریت-همکاری_page_mlr-form-editor') {
        wp_add_inline_script('mlr-admin-js', '
            jQuery(document).ready(function($){
                function updateRequiredState(visibilityToggle) {
                    var isChecked = $(visibilityToggle).is(":checked");
                    var controlsDiv = $(visibilityToggle).closest(".mlr-field-controls");
                    var requiredSwitch = controlsDiv.find(".mlr-required-switch");
                    var requiredLabel = controlsDiv.find(".mlr-required-label");
                    var requiredInput = requiredSwitch.find("input[type=checkbox]");

                    if (isChecked) {
                        requiredSwitch.removeClass("mlr-disabled");
                        requiredLabel.removeClass("mlr-disabled");
                        requiredInput.prop("disabled", false);
                    } else {
                        requiredSwitch.addClass("mlr-disabled");
                        requiredLabel.addClass("mlr-disabled");
                        requiredInput.prop("disabled", true).prop("checked", false);
                    }
                }
                $(".mlr-visibility-toggle").on("change", function(){ updateRequiredState(this); });
                $(".mlr-visibility-toggle").each(function(){ updateRequiredState(this); });
            });
        ');
    }
}

add_action('admin_init', 'mlr_process_pdf_export');

function mlr_process_pdf_export() {
    if ((!isset($_GET['export_pdf_credit']) && !isset($_GET['export_pdf_dossier']))) {
        return;
    }
    if (!current_user_can('manage_options') && !current_user_can('mlr_amaliat_operations') && !current_user_can('mlr_mali_operations')) {
        return;
    }

    $type = isset($_GET['export_pdf_credit']) ? 'credit' : 'dossier';
    
    global $wpdb;
    $table_requests = $wpdb->prefix . 'loan_requests';
    $users_table = $wpdb->prefix . 'users';
    $plans_table = $wpdb->prefix . 'installment_plans';

    $page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';
    $extra_where_pdf = [];
    if ($page === 'mlr-granted-requests') {
        $extra_where_pdf[] = "TRIM(COALESCE(r.status, '')) = 'اعطا شده'";
        $extra_where_pdf[] = "r.settlement_date IS NULL";
    } elseif ($page === 'mlr-settled-requests') {
        $extra_where_pdf[] = "TRIM(COALESCE(r.status, '')) = 'اعطا شده'";
        $extra_where_pdf[] = "TRIM(COALESCE(r.settlement_status, '')) = 'تسویه شده'";
    }
    // mlr-amaliat-operations: no extra_where, only form filters

    // --- Re-use Filter Logic (shared with Excel and list) ---
    if (function_exists('mlr_build_admin_request_filters')) {
        $filter_result = mlr_build_admin_request_filters('r', $extra_where_pdf);
        $sql_where = 'WHERE ' . $filter_result['where'];
        $prepare_params = $filter_result['params'];
        if (!empty($filter_result['acceptor_search_where'])) {
            $sql_where .= ' AND ' . $filter_result['acceptor_search_where'];
            $prepare_params = array_merge($prepare_params, $filter_result['acceptor_search_params']);
        }
    } else {
        $search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
        $status_from_url = isset($_GET['status']) ? sanitize_text_field(wp_unslash($_GET['status'])) : '';
        $date_from_jalali = isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : '';
        $date_to_jalali = isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : '';
        $date_from_gregorian = !empty($date_from_jalali) && function_exists('mlr_jalali_to_gregorian') ? mlr_jalali_to_gregorian($date_from_jalali) : '';
        $date_to_gregorian = !empty($date_to_jalali) && function_exists('mlr_jalali_to_gregorian') ? mlr_jalali_to_gregorian($date_to_jalali) : '';
        $where_clauses = ["1=1"];
        $prepare_params = [];
        if (!empty($search)) {
             $search_like = '%' . $wpdb->esc_like($search) . '%';
             $where_clauses[] = "(r.full_name LIKE %s OR r.national_code LIKE %s OR r.mobile_number LIKE %s)";
             $prepare_params[] = $search_like;
             $prepare_params[] = $search_like;
             $prepare_params[] = $search_like;
             if(is_numeric($search)){
                 $where_clauses[count($where_clauses)-1] = substr($where_clauses[count($where_clauses)-1], 0, -1);
                 $where_clauses[count($where_clauses)-1] .= " OR r.id = %d)";
                 $prepare_params[] = intval($search);
             }
        }
        if (!empty($status_from_url)) {
             $where_clauses[] = "r.status = %s";
             $prepare_params[] = $status_from_url;
        }
        if (!empty($date_from_gregorian)) {
            $where_clauses[] = "r.created_at >= %s";
            $prepare_params[] = $date_from_gregorian . ' 00:00:00';
        }
        if (!empty($date_to_gregorian)) {
            $where_clauses[] = "r.created_at <= %s";
            $prepare_params[] = $date_to_gregorian . ' 23:59:59';
        }
        $sql_where = "WHERE " . implode(' AND ', $where_clauses);
    }
    
    $query = "SELECT r.*, u.display_name, p.name as plan_name
                   FROM $table_requests r
                   LEFT JOIN $users_table u ON r.user_id = u.ID
                   LEFT JOIN $plans_table p ON r.installment_plan_id = p.id
                   $sql_where
                   ORDER BY r.created_at DESC";
    
    $requests = !empty($prepare_params) ? $wpdb->get_results($wpdb->prepare($query, $prepare_params)) : $wpdb->get_results($query);

    // --- Generate HTML ---
    ?>
    <!DOCTYPE html>
    <html lang="fa" dir="rtl">
    <head>
        <meta charset="UTF-8">
        <title>چاپ گزارش - <?php echo $type === 'credit' ? 'نتایج اعتبارسنجی' : 'پرونده کامل'; ?></title>
        <style>
            @import url('https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;700&display=swap');
            body { font-family: 'Vazirmatn', Tahoma, sans-serif; direction: rtl; background: #eee; margin: 0; padding: 20px; color: #333; }
            .page-container { max-width: 210mm; margin: 0 auto; background: #fff; padding: 10mm; box-shadow: 0 0 10px rgba(0,0,0,0.1); margin-bottom: 20px; position: relative; min-height: 297mm; box-sizing: border-box; }
            .header { text-align: center; border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom: 20px; }
            .header h1 { margin: 0; font-size: 20px; }
            .header .meta { font-size: 12px; margin-top: 5px; display: flex; justify-content: space-between; }
            .section { margin-bottom: 15px; border: 1px solid #ddd; border-radius: 5px; overflow: hidden; }
            .section-title { background: #f0f0f0; padding: 8px 10px; font-weight: bold; font-size: 14px; border-bottom: 1px solid #ddd; }
            .section-content { padding: 10px; font-size: 13px; }
            .row { display: flex; flex-wrap: wrap; margin-bottom: 5px; }
            .col { flex: 1; min-width: 45%; margin-bottom: 5px; }
            .label { color: #666; font-size: 12px; }
            .value { font-weight: bold; }
            .credit-score-box { text-align: center; padding: 15px; background: #f9f9f9; border-radius: 8px; margin-bottom: 15px; border: 1px solid #eee; }
            .score-val { font-size: 24px; font-weight: bold; color: #2c3e50; }
            .risk-val { font-size: 16px; margin-top: 5px; }
            table { width: 100%; border-collapse: collapse; font-size: 12px; margin-top: 10px; }
            th, td { border: 1px solid #ddd; padding: 6px; text-align: center; }
            th { background: #f5f5f5; }
            .badge { padding: 2px 6px; border-radius: 4px; color: #fff; font-size: 10px; }
            .badge-success { background: #10b981; }
            .badge-warning { background: #f59e0b; }
            .badge-danger { background: #ef4444; }
            .grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px; margin-top: 10px; }
            .summary-card { background: #f9f9f9; border: 1px solid #eee; padding: 10px; border-radius: 5px; text-align: center; }
            
            @media print {
                body { background: #fff; padding: 0; }
                .page-container { box-shadow: none; margin: 0; page-break-after: always; width: 100%; max-width: none; min-height: auto; height: auto; }
                .no-print { display: none; }
            }
        </style>
    </head>
    <body>
        <div class="no-print" style="text-align:center; margin-bottom:20px;">
            <button onclick="window.print();" style="padding:10px 20px; font-size:16px; cursor:pointer; background:#0073aa; color:#fff; border:none; border-radius:5px;">چاپ / ذخیره به عنوان PDF</button>
        </div>

        <?php foreach ($requests as $req): 
            $form_data = !empty($req->form_data) ? json_decode($req->form_data, true) : [];
            $credit_report = null;
            if (!empty($req->credit_report_json)) {
                $credit_report = json_decode($req->credit_report_json, true);
            } elseif (isset($form_data['credit_report_json'])) {
                $cr_data = $form_data['credit_report_json'];
                if (is_string($cr_data)) {
                    $credit_report = json_decode($cr_data, true);
                    if (json_last_error() !== JSON_ERROR_NONE) $credit_report = json_decode(stripslashes($cr_data), true);
                } else {
                    $credit_report = $cr_data;
                }
            }
            
            $created_at = function_exists('mlr_gregorian_to_jalali') ? mlr_gregorian_to_jalali($req->created_at, 'Y/m/d H:i') : $req->created_at;
        ?>
        <div class="page-container">
            <div class="header">
                <h1>گزارش <?php echo $type === 'credit' ? 'اعتبارسنجی' : 'پرونده'; ?> درخواست تسهیلات</h1>
                <div class="meta">
                    <span>شماره درخواست: <strong><?php echo $req->id; ?></strong></span>
                    <span>تاریخ ثبت: <?php echo $created_at; ?></span>
                    <span>وضعیت: <?php echo $req->status; ?></span>
                </div>
            </div>

            <div class="section">
                <div class="section-title">اطلاعات متقاضی</div>
                <div class="section-content">
                    <div class="row">
                        <div class="col"><span class="label">نام و نام خانوادگی:</span> <span class="value"><?php echo esc_html($req->full_name); ?></span></div>
                        <div class="col"><span class="label">کد ملی:</span> <span class="value"><?php echo esc_html($req->national_code); ?></span></div>
                    </div>
                    <div class="row">
                        <div class="col"><span class="label">شماره موبایل:</span> <span class="value"><?php echo esc_html($req->mobile_number); ?></span></div>
                        <div class="col"><span class="label">طرح:</span> <span class="value"><?php echo esc_html($req->plan_name); ?></span></div>
                    </div>
                    <div class="row">
                        <div class="col"><span class="label">مبلغ درخواستی:</span> <span class="value"><?php echo number_format($req->loan_amount); ?> ریال</span></div>
                        <div class="col"><span class="label">مبلغ تسهیلات:</span> <span class="value"><?php echo number_format(isset($req->facility_amount) && $req->facility_amount !== '' ? (float)$req->facility_amount : (float)$req->loan_amount); ?> ریال</span></div>
                        <div class="col"><span class="label">فاصله اقساط (تناوب):</span> <span class="value"><?php echo isset($req->installment_interval) && $req->installment_interval !== '' ? esc_html($req->installment_interval) . ' ماه' : '—'; ?></span></div>
                        <div class="col"><span class="label">پذیرنده:</span> <span class="value"><?php echo esc_html($req->display_name); ?></span></div>
                    </div>
                    <div class="row">
                        <div class="col"><span class="label">دریافت مدارک:</span> <span class="value"><?php echo esc_html(function_exists('mlr_get_document_receipt_status_label') ? mlr_get_document_receipt_status_label($req->document_receipt_status ?? '') : 'بررسی نشده'); ?></span></div>
                        <div class="col"><span class="label">وضعیت تسویه:</span> <span class="value"><?php echo esc_html(trim((string) ($req->settlement_status ?? '')) ?: '—'); ?></span></div>
                    </div>
                </div>
            </div>

            <?php if ($credit_report): 
                $scoreObj = $credit_report['score'] ?? [];
                $reportObj = $credit_report['report'] ?? [];
                $score = $scoreObj['score'] ?? 0;
                $risk = $scoreObj['risk'] ?? 'نامشخص';
                $summary = ($reportObj['summaryInformationRelatedToContractsSum'][0]) ?? [];
                $bouncedObj = $credit_report['bouncedCheque'] ?? [];
                $bouncedCount = isset($bouncedObj['bouncedCheques']) && is_array($bouncedObj['bouncedCheques']) ? count($bouncedObj['bouncedCheques']) : 0;
            ?>
            <div class="section">
                <div class="section-title">نتیجه اعتبارسنجی</div>
                <div class="section-content">
                    <div class="credit-score-box">
                        <div class="score-val">امتیاز: <?php echo $score; ?></div>
                        <div class="risk-val">ریسک: <?php echo $risk; ?></div>
                    </div>
                    
                    <?php // Personal Info from Credit Report ?>
                    <?php 
                    $pInfo = $scoreObj['personInformation'] ?? ($reportObj['personInformation'] ?? []);
                    if (!empty($pInfo)): ?>
                        <div style="margin-top:15px; font-weight:bold;">اطلاعات هویتی (تأیید شده بانکی):</div>
                        <div class="row">
                            <div class="col"><span class="label">نام و نام خانوادگی:</span> <span class="value"><?php echo esc_html(($pInfo['name'] ?? $pInfo['firstName'] ?? '') . ' ' . ($pInfo['family'] ?? $pInfo['lastName'] ?? '')); ?></span></div>
                            <div class="col"><span class="label">نام پدر:</span> <span class="value"><?php echo esc_html($pInfo['fatherName'] ?? '-'); ?></span></div>
                        </div>
                    <?php endif; ?>

                    <div class="row">
                        <div class="col"><span class="label">تسهیلات جاری:</span> <span class="value"><?php echo $summary['inProgressContractsCount'] ?? 0; ?></span></div>
                        <div class="col"><span class="label">معوقات:</span> <span class="value"><?php echo number_format($summary['overdueAmount'] ?? 0); ?> ریال</span></div>
                    </div>
                    <div class="row">
                        <div class="col"><span class="label">چک برگشتی:</span> <span class="value"><?php echo $bouncedCount; ?> عدد</span></div>
                        <div class="col"><span class="label">کل تسهیلات:</span> <span class="value"><?php echo number_format($summary['totalAmount'] ?? 0); ?> ریال</span></div>
                    </div>

                    <?php // Direct Contracts (Loans) ?>
                    <?php if (!empty($reportObj['directContracts'])): ?>
                        <div style="margin-top:15px; font-weight:bold;">تسهیلات بانکی (وام‌ها):</div>
                        <table>
                            <thead><tr><th>بانک</th><th>نوع</th><th>مبلغ کل</th><th>مانده بدهی</th><th>وضعیت</th></tr></thead>
                            <tbody>
                                <?php foreach($reportObj['directContracts'] as $loan): 
                                    $statusText = $loan['negativeContractStatus'] ?? 'جاری';
                                    $statusClass = (strpos($statusText, 'معوق') !== false || strpos($statusText, 'مشکوک') !== false) ? 'badge-danger' : ((strpos($statusText, 'گذشته') !== false) ? 'badge-warning' : 'badge-success');
                                ?>
                                <tr>
                                    <td><?php echo esc_html($loan['organizationName'] ?? '-'); ?></td>
                                    <td><?php echo esc_html($loan['typeOfFinancingTitle'] ?? '-'); ?></td>
                                    <td><?php echo number_format($loan['totalAmount'] ?? 0); ?></td>
                                    <td><?php echo number_format($loan['overdueAmount'] ?? 0); ?></td>
                                    <td><span class="badge <?php echo $statusClass; ?>"><?php echo esc_html($statusText); ?></span></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>

                    <?php // Indirect Contracts (Guarantees) ?>
                    <?php if (!empty($reportObj['indirectContracts'])): ?>
                        <div style="margin-top:15px; font-weight:bold;">تعهدات (ضمانت‌ها):</div>
                        <table>
                            <thead><tr><th>بانک</th><th>مبلغ ضمانت</th><th>معوقات وام‌گیرنده</th><th>وضعیت</th></tr></thead>
                            <tbody>
                                <?php foreach($reportObj['indirectContracts'] as $g): 
                                    $statusText = $g['negativeContractStatus'] ?? 'جاری';
                                    $statusClass = (strpos($statusText, 'معوق') !== false) ? 'badge-danger' : 'badge-success';
                                ?>
                                <tr>
                                    <td><?php echo esc_html($g['organizationName'] ?? '-'); ?></td>
                                    <td><?php echo number_format($g['totalAmount'] ?? 0); ?></td>
                                    <td><?php echo number_format($g['overdueAmount'] ?? 0); ?></td>
                                    <td><span class="badge <?php echo $statusClass; ?>"><?php echo esc_html($statusText); ?></span></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>

                    <?php // Pichak (Cheques) ?>
                    <?php 
                    $pichakItems = $credit_report['pichak']['personChequesData']['personChequeItems'] ?? [];
                    if (!empty($pichakItems)): ?>
                        <div style="margin-top:15px; font-weight:bold;">وضعیت چک‌های صیادی (پیچک):</div>
                        <table>
                            <thead><tr><th>وضعیت</th><th>تعداد</th><th>مبلغ کل (ریال)</th></tr></thead>
                            <tbody>
                                <?php foreach($pichakItems as $item): if (($item['count'] ?? 0) == 0) continue; ?>
                                <tr>
                                    <td><?php echo esc_html($item['checkStatusTitle'] ?? '-'); ?></td>
                                    <td><?php echo esc_html($item['count'] ?? 0); ?></td>
                                    <td><?php echo number_format($item['totalAmount'] ?? 0); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>

                    <?php if (!empty($bouncedObj['bouncedCheques'])): ?>
                        <div style="margin-top:10px; font-weight:bold;">جزئیات چک‌های برگشتی:</div>
                        <table>
                            <thead><tr><th>بانک</th><th>مبلغ</th><th>تاریخ برگشت</th><th>تاریخ رفع سوءاثر</th></tr></thead>
                            <tbody>
                                <?php foreach($bouncedObj['bouncedCheques'] as $chk): ?>
                                <tr>
                                    <td><?php echo esc_html($chk['organizationName'] ?? '-'); ?></td>
                                    <td><?php echo number_format($chk['amount'] ?? 0); ?></td>
                                    <td><?php echo esc_html(!empty($chk['bouncedDate']) ? substr($chk['bouncedDate'], 0, 10) : '-'); ?></td>
                                    <td><?php echo esc_html(!empty($chk['clearedDate']) ? substr($chk['clearedDate'], 0, 10) : '---'); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>

                    <?php // Tax ?>
                    <?php 
                    $taxItems = $credit_report['tax']['taxResult'] ?? [];
                    $hasTax = false; foreach($taxItems as $t) { if(!empty($t['year'])) $hasTax = true; }
                    if ($hasTax): ?>
                        <div style="margin-top:15px; font-weight:bold;">وضعیت مالیاتی:</div>
                        <table>
                            <thead><tr><th>سال</th><th>درآمد ابرازی</th><th>مالیات قطعی</th><th>بدهی</th></tr></thead>
                            <tbody>
                                <?php foreach($taxItems as $item): if(empty($item['year'])) continue; ?>
                                <tr>
                                    <td><?php echo esc_html($item['year']); ?></td>
                                    <td><?php echo number_format($item['daramadEbrazi'] ?? 0); ?></td>
                                    <td><?php echo number_format($item['maliatGhati'] ?? 0); ?></td>
                                    <td><?php echo number_format($item['bedehiMaliatAmalkard'] ?? 0); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>

                    <?php // Judiciary ?>
                    <?php 
                    $jud = $credit_report['judiciary']['judiciaryData'] ?? [];
                    if (($jud['sumExecuteCount'] ?? 0) > 0 || ($jud['sumNotExecuteCount'] ?? 0) > 0): 
                    ?>
                        <div style="margin-top:15px; font-weight:bold;">پرونده‌های قضایی:</div>
                        <div class="row">
                            <div class="col"><span class="label">پرونده‌های اجرا شده:</span> <span class="value"><?php echo esc_html($jud['sumExecuteCount']); ?></span></div>
                            <div class="col"><span class="label">پرونده‌های اجرا نشده:</span> <span class="value"><?php echo esc_html($jud['sumNotExecuteCount']); ?></span></div>
                            <div class="col" style="flex:100%"><span class="label">محکومیت اجرا نشده:</span> <span class="value"><?php echo number_format($jud['sumNotExecuteSumPenaltyAmount'] ?? 0); ?> ریال</span></div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php else: ?>
                <div class="section">
                    <div class="section-content" style="text-align:center; color:#999;">گزارش اعتبارسنجی موجود نیست.</div>
                </div>
            <?php endif; ?>

            <?php if ($type === 'dossier'): ?>
                <?php 
                $occupations_map = [
                    'official_employee' => 'کارمند رسمی', 
                    'contract_employee' => 'کارمند قراردادی', 
                    'worker'            => 'کارگر', 
                    'freelancer'        => 'آزاد', 
                    'retired'           => 'بازنشسته', 
                    'pensioner'         => 'مستمری بگیر', 
                    'employer'          => 'کارفرما', 
                    'other'             => 'سایر'
                ];
                $occupation_display = isset($occupations_map[$req->occupation]) ? $occupations_map[$req->occupation] : $req->occupation;
                ?>
                <div class="section">
                    <div class="section-title">جزئیات تکمیلی پرونده</div>
                    <div class="section-content">
                        <div class="row">
                            <div class="col"><span class="label">شغل:</span> <span class="value"><?php echo esc_html($occupation_display); ?></span></div>
                            <div class="col"><span class="label">کد پستی:</span> <span class="value"><?php echo esc_html($req->postal_code); ?></span></div>
                        </div>
                        <div class="row">
                            <div class="col" style="flex:100%;"><span class="label">آدرس:</span> <span class="value"><?php echo esc_html($req->residential_address); ?></span></div>
                        </div>
                        <div class="row" style="margin-top:10px;">
                            <div class="col"><span class="label">تعداد اقساط:</span> <span class="value"><?php echo $req->installment_count; ?></span></div>
                            <div class="col"><span class="label">مبلغ قسط:</span> <span class="value"><?php echo number_format($req->installment_amount); ?> ریال</span></div>
                        </div>
                        
                        <?php if (!empty($req->description)): ?>
                            <div style="margin-top:10px; border-top:1px dashed #eee; padding-top:5px;">
                                <span class="label">توضیحات متقاضی:</span>
                                <p style="margin:5px 0;"><?php echo nl2br(esc_html($req->description)); ?></p>
                            </div>
                        <?php endif; ?>

                        <?php 
                        // --- Documents Section ---
                        $uploaded_docs = function_exists('mlr_get_request_uploaded_documents')
                            ? mlr_get_request_uploaded_documents($req->required_documents)
                            : maybe_unserialize($req->required_documents);
                        if (!is_array($uploaded_docs)) $uploaded_docs = [];
                        
                        $all_files_to_show = [];
                        $doc_labels = function_exists('mlr_get_all_document_options') ? mlr_get_all_document_options() : [];

                        foreach ($uploaded_docs as $key => $val) {
                            if (empty($val)) continue;
                            $label = isset($doc_labels[$key]) ? $doc_labels[$key] : $key;
                            $urls = function_exists('mlr_extract_document_urls')
                                ? mlr_extract_document_urls($val)
                                : (is_array($val) ? $val : [$val]);
                            foreach ($urls as $url) {
                                if (!empty($url)) $all_files_to_show[] = ['label' => $label, 'url' => $url];
                            }
                        }

                        if (!empty($req->bank_validation_file)) $all_files_to_show[] = ['label' => 'فایل اعتبارسنجی بانک', 'url' => $req->bank_validation_file];
                        if (!empty($req->signature_image)) $all_files_to_show[] = ['label' => 'تصویر امضا', 'url' => $req->signature_image];
                        if (!empty($req->contract_file)) $all_files_to_show[] = ['label' => 'فایل قرارداد', 'url' => $req->contract_file];
                        
                        if (!empty($req->check_images)) {
                            $checks = function_exists('mlr_extract_document_urls') ? mlr_extract_document_urls($req->check_images) : [];
                            if (!empty($checks)) {
                                foreach ($checks as $chk_url) {
                                    if (!empty($chk_url)) $all_files_to_show[] = ['label' => 'تصویر چک', 'url' => $chk_url];
                                }
                            }
                        }

                        // مدارک ضامن اول
                        $guarantor_labels = ['national_card' => 'ضامن: کارت ملی', 'id_card' => 'ضامن: شناسنامه', 'payslip' => 'ضامن: فیش حقوقی/جواز', 'sana' => 'ضامن: گواهی ثنا', 'check' => 'ضامن: تصویر چک', 'residence_deed' => 'ضامن: سند/اجاره سکونت', 'work_deed' => 'ضامن: سند/اجاره محل کار', 'account_statement' => 'ضامن: گردش حساب', 'extra_docs' => 'ضامن: مدارک تکمیلی'];
                        if (!empty($req->guarantor_info)) {
                            $guarantor_info = is_string($req->guarantor_info) ? json_decode($req->guarantor_info, true) : $req->guarantor_info;
                            if (is_array($guarantor_info)) {
                                foreach ($guarantor_labels as $gk => $gl) {
                                    if (!empty($guarantor_info[$gk]) && is_string($guarantor_info[$gk]) && strpos($guarantor_info[$gk], 'http') === 0) {
                                        $all_files_to_show[] = ['label' => $gl, 'url' => $guarantor_info[$gk]];
                                    }
                                }
                            }
                        }
                        // مدارک ضامن دوم
                        $guarantor2_labels = ['national_card' => 'ضامن دوم: کارت ملی', 'id_card' => 'ضامن دوم: شناسنامه', 'payslip' => 'ضامن دوم: فیش حقوقی/جواز', 'sana' => 'ضامن دوم: گواهی ثنا', 'check' => 'ضامن دوم: تصویر چک', 'residence_deed' => 'ضامن دوم: سند/اجاره سکونت', 'work_deed' => 'ضامن دوم: سند/اجاره محل کار', 'account_statement' => 'ضامن دوم: گردش حساب', 'extra_docs' => 'ضامن دوم: مدارک تکمیلی'];
                        if (!empty($req->guarantor_info_2)) {
                            $guarantor_info_2 = is_string($req->guarantor_info_2) ? json_decode($req->guarantor_info_2, true) : $req->guarantor_info_2;
                            if (is_array($guarantor_info_2)) {
                                foreach ($guarantor2_labels as $gk => $gl) {
                                    if (!empty($guarantor_info_2[$gk]) && is_string($guarantor_info_2[$gk]) && strpos($guarantor_info_2[$gk], 'http') === 0) {
                                        $all_files_to_show[] = ['label' => $gl, 'url' => $guarantor_info_2[$gk]];
                                    }
                                }
                            }
                        }
                        // پیوست‌های مرحله در انتظار مدارک (فاکتور، قرارداد و سایر)
                        if (!empty($req->pending_docs_attachments)) {
                            $pending = is_string($req->pending_docs_attachments) ? json_decode($req->pending_docs_attachments, true) : $req->pending_docs_attachments;
                            $pending_slot_labels = ['invoice' => 'فاکتور', 'contract' => 'قرارداد', 'doc3' => 'پیوست ۳', 'doc4' => 'پیوست ۴', 'doc5' => 'پیوست ۵'];
                            if (is_array($pending)) {
                                foreach ($pending as $pkey => $pitem) {
                                    if (is_array($pitem) && !empty($pitem['url']) && strpos($pitem['url'], 'http') === 0) {
                                        $plabel = !empty($pitem['label']) ? $pitem['label'] : ($pending_slot_labels[$pkey] ?? $pkey);
                                        $all_files_to_show[] = ['label' => $plabel, 'url' => $pitem['url']];
                                    }
                                }
                            }
                        }

                        if (!empty($all_files_to_show)): ?>
                        <div style="margin-top:15px; border-top:1px dashed #eee; padding-top:10px;">
                            <span class="label" style="display:block; margin-bottom:5px; font-weight:bold;">مدارک و فایل‌های پیوست:</span>
                            <div class="mlr-pdf-docs-container" style="display: flex; flex-direction: column; gap: 20px;">
                                <?php foreach($all_files_to_show as $file): ?>
                                <div style="border: 1px solid #eee; padding: 15px; border-radius: 8px; page-break-inside: avoid;">
                                    <div class="label" style="margin-bottom: 10px; font-weight: bold; font-size: 14px; color: #444;"><?php echo esc_html($file['label']); ?>:</div>
                                    <?php 
                                    $file_url = $file['url'];
                                    $ext = strtolower(pathinfo(parse_url($file_url, PHP_URL_PATH), PATHINFO_EXTENSION));
                                    $image_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                                    
                                    if (in_array($ext, $image_exts)): ?>
                                        <div style="text-align: center;">
                                            <img src="<?php echo esc_url($file_url); ?>" style="max-width: 100%; max-height: 500px; height: auto; border: 1px solid #ddd; display: block; margin: 0 auto; border-radius: 4px;">
                                        </div>
                                    <?php else: ?>
                                        <a href="<?php echo esc_url($file_url); ?>" target="_blank" style="text-decoration:none; color:#0073aa; font-size: 13px;">[مشاهده/دانلود فایل غیر تصویری (<?php echo strtoupper($ext); ?>)]</a>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

        </div>
        <?php endforeach; ?>
        
        <script>window.print();</script>
    </body>
    </html>
    <?php
    exit;
}

add_action('admin_init', 'mlr_process_dossier_excel_export');
/**
 * Handles the export of a single loan request dossier to Excel with specific columns.
 */
function mlr_process_dossier_excel_export() {
    if (!isset($_GET['action']) || $_GET['action'] !== 'mlr_export_dossier_excel') return;
    if (!current_user_can('manage_options')) wp_die('دسترسی غیرمجاز');
    
    $nonce = isset($_GET['nonce']) ? sanitize_text_field($_GET['nonce']) : '';
    if (!wp_verify_nonce($nonce, 'mlr_ajax_nonce_action')) wp_die('خطای امنیتی');

    $rid = isset($_GET['request_id']) ? intval($_GET['request_id']) : 0;
    if (!$rid) wp_die('شناسه نامعتبر');

    global $wpdb;
    $req = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}loan_requests WHERE id = %d", $rid));
    if (!$req) wp_die('درخواست یافت نشد');

    $form_data = !empty($req->form_data) ? json_decode($req->form_data, true) : [];
    
    // Split full name into first and last name (best effort)
    $names = explode(' ', trim($req->full_name), 2);
    $first_name = $names[0] ?? '';
    $last_name = $names[1] ?? '';
    
    $columns = [
        'شناسه' => $req->id,
        'تاریخ ثبت' => function_exists('mlr_gregorian_to_jalali') ? mlr_gregorian_to_jalali($req->created_at, 'Y/m/d H:i') : $req->created_at,
        'نام' => $first_name,
        'نام خانوادگی' => $last_name,
        'جنسیت' => '', // Not available in current schema
        'کدملی' => $req->national_code,
        'نام پدر' => $form_data['father_name'] ?? '',
        'شماره شناسنامه' => $form_data['certificate_number'] ?? '',
        'تاریخ تولد' => function_exists('mlr_gregorian_to_jalali') ? mlr_gregorian_to_jalali($req->birth_date, 'Y/m/d') : $req->birth_date,
        'محل تولد (شهر)' => '', // Not available
        'تاریخ صدور' => '', // Not available
        'محل صدور (شهر)' => $form_data['certificate_city'] ?? '',
        'استان محل سکونت' => '', // Not available
        'شهر محل سکونت' => '', // Not available
        'آدرس محل سکونت' => $req->residential_address,
        'تلفن ثابت محل سکونت' => $req->phone_number,
        'کدپستی' => $req->postal_code,
        'محل سکونت' => '', // Not available
        'تلفن همراه' => $req->mobile_number,
    ];

    $filename = 'dossier-' . $rid . '.xls';
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
    echo '<head><meta http-equiv="Content-Type" content="text/html; charset=utf-8" /><!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet><x:Name>Dossier</x:Name><x:WorksheetOptions><x:DisplayRightToLeft/></x:WorksheetOptions></x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]--></head>';
    echo '<body style="direction:rtl;"><table border="1"><tr>';
    foreach (array_keys($columns) as $header) echo '<th style="background:#f2f2f2; padding:5px;">' . esc_html($header) . '</th>';
    echo '</tr><tr>';
    foreach ($columns as $val) echo '<td style="padding:5px; mso-number-format:\'\@\';">' . esc_html($val) . '</td>';
    echo '</tr></table></body></html>';
    exit;
}



add_action('wp_enqueue_scripts', 'mlr_enqueue_frontend_scripts');
function mlr_enqueue_frontend_scripts() {
    global $post;

    // ثبت اسکریپت فرم همکاری تا در هر جایی (محتوا، ویجت، بلوک) که شورتکد رندر شود، enqueue و localize در خود شورتکد در دسترس باشد
    wp_register_script('mlr-file-upload-helper', MLR_PLUGIN_URL . 'assets/js/mlr-file-upload-helper.js', [], MLR_VERSION, true);
    wp_register_script('mlr-guarantor-form', MLR_PLUGIN_URL . 'assets/js/mlr-guarantor-form.js', ['jquery', 'mlr-file-upload-helper'], MLR_VERSION, true);
    wp_register_script('mlr-dashboard-document-upload', MLR_PLUGIN_URL . 'assets/js/mlr-dashboard-document-upload.js', ['jquery', 'mlr-file-upload-helper'], MLR_VERSION, true);
    wp_register_script('mlr-cooperation-form-js', MLR_PLUGIN_URL . 'assets/js/cooperation-form.js', ['jquery', 'mlr-file-upload-helper'], '8.4.6.0', true);
    // Register contract assets unconditionally. The shortcode itself enqueues/localizes them,
    // so the signing flow also works when Elementor/widgets render the shortcode dynamically.
    wp_register_style('mlr-contract-viewer-css', MLR_PLUGIN_URL . 'assets/css/contract-viewer.css', [], MLR_VERSION);
    wp_register_script('mlr-contract-viewer-js', MLR_PLUGIN_URL . 'assets/js/contract-viewer.js', ['jquery', 'mlr-contract-print-js'], MLR_VERSION, true);
    wp_register_style('mlr-contract-print-css', MLR_PLUGIN_URL . 'assets/css/contract-print.css', [], MLR_VERSION);
    wp_register_script('mlr-contract-print-js', MLR_PLUGIN_URL . 'assets/js/mlr-contract-print.js', [], MLR_VERSION, true);

    // Isolated print assets are lightweight and intentionally available on every plugin frontend view.
    // They prevent theme/Elementor @media print rules from blanking the contract.
    wp_enqueue_style('mlr-contract-print-css');
    wp_enqueue_script('mlr-contract-print-js');

    wp_enqueue_style('mlr-frontend-css', MLR_PLUGIN_URL . 'assets/css/frontend.css', [], MLR_VERSION);
    // Unified popup/modal styles (admin + frontend) - must load after frontend.css to override
    wp_enqueue_style('mlr-modal-unified-css', MLR_PLUGIN_URL . 'assets/css/mlr-modal-unified.css', ['mlr-frontend-css'], MLR_VERSION);
    wp_enqueue_script('mlr-jalali-datepicker', MLR_PLUGIN_URL . 'assets/js/jalali-datepicker.js', ['jquery'], MLR_VERSION, true);

    if (is_a($post, 'WP_Post') && (has_shortcode($post->post_content, 'mlr_individual_request_form') || has_shortcode($post->post_content, 'mlr_legal_request_form'))) {
         wp_enqueue_script('mlr-file-upload-helper');
         wp_enqueue_script('mlr-cooperation-form-js');
         wp_localize_script('mlr-cooperation-form-js', 'mlr_coop_ajax', [
             'ajax_url' => admin_url('admin-ajax.php'),
             'nonce'    => wp_create_nonce('mlr_cooperation_request_action'),
             'context'  => 'cooperation_request',
         ]);
    }

     if (is_a($post, 'WP_Post') && (
         has_shortcode($post->post_content, 'mlr_user_loan_requests') ||
         has_shortcode($post->post_content, 'mlr_needs_review_requests') ||
         has_shortcode($post->post_content, 'mlr_processing_requests') ||
         has_shortcode($post->post_content, 'mlr_granted_user_requests') ||
         has_shortcode($post->post_content, 'mlr_user_settlement_cycles') ||
         has_shortcode($post->post_content, 'mlr_bank_expert_dashboard') ||
         has_shortcode($post->post_content, 'mlr_verified_docs_dashboard')
         )) {
        wp_enqueue_script('mlr-file-upload-helper');
        wp_enqueue_script('mlr-guarantor-form');
        wp_enqueue_script('mlr-dashboard-document-upload');
        wp_enqueue_script('mlr-user-requests-js', MLR_PLUGIN_URL . 'assets/js/mlr-user-requests.js', ['jquery', 'mlr-jalali-datepicker', 'mlr-dashboard-document-upload', 'mlr-guarantor-form', 'mlr-contract-print-js'], MLR_VERSION, true);
        wp_localize_script('mlr-user-requests-js', 'mlr_ajax_obj', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'filter_nonce'    => wp_create_nonce('mlr_user_requests_nonce'),
            'details_nonce'   => wp_create_nonce('mlr_frontend_nonce'),
            'cycle_nonce'     => wp_create_nonce('mlr_user_cycle_nonce'),
            'allowed_statuses' => []
        ]);
     }

     if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'mlr_bank_expert_dashboard')) {
          wp_enqueue_style('mlr-bank-expert-dashboard-css', MLR_PLUGIN_URL . 'assets/css/bank-expert-dashboard.css', [], MLR_VERSION);
          wp_enqueue_script('mlr-bank-expert-dashboard-js', MLR_PLUGIN_URL . 'assets/js/bank-expert-dashboard.js', ['jquery', 'mlr-jalali-datepicker'], MLR_VERSION, true);
          wp_localize_script('mlr-bank-expert-dashboard-js', 'mlr_bank_ajax_obj', [
              'ajax_url' => admin_url('admin-ajax.php'),
              'nonce'    => wp_create_nonce('mlr_frontend_nonce'),
          ]);
     }

     if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'mlr_verified_docs_dashboard')) {
          wp_enqueue_script('mlr-verified-docs-dashboard-js', MLR_PLUGIN_URL . 'assets/js/verified-docs-dashboard.js', ['jquery', 'mlr-jalali-datepicker'], MLR_VERSION, true);
          wp_localize_script('mlr-verified-docs-dashboard-js', 'mlr_verified_ajax_obj', [
              'ajax_url' => admin_url('admin-ajax.php'),
              'nonce'    => wp_create_nonce('mlr_frontend_nonce'),
          ]);
     }

     if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'mlr_loan_request_form')) {
         wp_enqueue_style('mlr-loan-form-style', MLR_PLUGIN_URL . 'assets/css/loan-request-form.css', [], MLR_VERSION);
         wp_enqueue_script('mlr-front-form-js', MLR_PLUGIN_URL . 'assets/js/loan-request-form.js', ['jquery'], MLR_VERSION, true);

         $user_commission = null;
         if (is_user_logged_in()) {
             $user_commission = get_user_meta(get_current_user_id(), 'acceptor_commission_percent', true);
         }

         wp_localize_script('mlr-front-form-js', 'mlr_form_ajax', [
             'ajax_url' => admin_url('admin-ajax.php'),
             'nonce' => wp_create_nonce('mlr_frontend_nonce'),
             'calc_nonce' => wp_create_nonce('mlr_frontend_calc_nonce'),
             'submit_nonce' => wp_create_nonce('mlr_loan_request_action'),
             'plans' => mlr_get_js_plans(null, 'mlr_loan_request_form'),
             'user_commission' => $user_commission,
             'context' => 'mlr_loan_request_form'
         ]);
     }

     if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'mlr_applicant_loan_request_form')) {
         wp_enqueue_style('mlr-loan-form-style', MLR_PLUGIN_URL . 'assets/css/loan-request-form.css', [], MLR_VERSION);
         wp_enqueue_script('mlr-file-upload-helper');
         wp_enqueue_script('mlr-applicant-form-js', MLR_PLUGIN_URL . 'assets/js/applicant-loan-request-form.js', ['jquery', 'mlr-file-upload-helper'], MLR_VERSION, true);
         wp_localize_script('mlr-applicant-form-js', 'mlr_applicant_form_ajax', [
             'ajax_url' => admin_url('admin-ajax.php'),
             'nonce' => wp_create_nonce('mlr_frontend_nonce'),
             'calc_nonce' => wp_create_nonce('mlr_applicant_frontend_calc_nonce'),
             'submit_nonce' => wp_create_nonce('mlr_applicant_loan_request_action'),
             'acceptor_search_nonce' => wp_create_nonce('mlr_applicant_acceptor_search_nonce'),
             'plans' => mlr_get_applicant_js_plans(),
             'user_commission' => null,
             'context' => 'mlr_applicant_loan_request_form',
         ]);
     }

     if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'mlr_applicant_dashboard')) {
         wp_enqueue_style('mlr-frontend-css', MLR_PLUGIN_URL . 'assets/css/frontend.css', [], MLR_VERSION);
         wp_enqueue_style('mlr-modal-unified-css', MLR_PLUGIN_URL . 'assets/css/mlr-modal-unified.css', ['mlr-frontend-css'], MLR_VERSION);
         wp_enqueue_script('mlr-file-upload-helper');
         wp_enqueue_script('mlr-guarantor-form');
         wp_enqueue_script('mlr-dashboard-document-upload');
         wp_enqueue_script('mlr-applicant-requests-js', MLR_PLUGIN_URL . 'assets/js/mlr-applicant-requests.js', ['jquery', 'mlr-jalali-datepicker', 'mlr-dashboard-document-upload', 'mlr-guarantor-form', 'mlr-contract-print-js'], MLR_VERSION, true);
         wp_localize_script('mlr-applicant-requests-js', 'mlr_applicant_ajax_obj', [
             'ajax_url' => admin_url('admin-ajax.php'),
             'filter_nonce' => wp_create_nonce('mlr_applicant_requests_nonce'),
             'details_nonce' => wp_create_nonce('mlr_frontend_nonce'),
             'cycle_nonce' => wp_create_nonce('mlr_user_cycle_nonce'),
             'allowed_statuses' => [],
             'table_view' => 'default',
         ]);
     }

     if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'mlr_acceptor_open_applicant_requests')) {
         wp_enqueue_style('mlr-frontend-css', MLR_PLUGIN_URL . 'assets/css/frontend.css', [], MLR_VERSION);
         wp_enqueue_script('mlr-acceptor-marketplace-js', MLR_PLUGIN_URL . 'assets/js/acceptor-open-applicant-requests.js', ['jquery'], MLR_VERSION, true);
         wp_localize_script('mlr-acceptor-marketplace-js', 'mlr_acceptor_marketplace_ajax', [
             'ajax_url' => admin_url('admin-ajax.php'),
             'nonce' => wp_create_nonce('mlr_acceptor_marketplace_nonce'),
         ]);
     }

     if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'mlr_loan_calculator')) {
        wp_enqueue_script('mlr-calculator-js', MLR_PLUGIN_URL . 'assets/js/loan-calculator.js', ['jquery'], MLR_VERSION, true);
        $user_commission = null;
         if (is_user_logged_in()) {
             $user_commission = get_user_meta(get_current_user_id(), 'acceptor_commission_percent', true);
         }
        wp_localize_script('mlr-calculator-js', 'mlrCalculatorAjax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('mlr_frontend_calc_nonce'),
            'plans'   => mlr_get_js_plans(null, 'mlr_loan_calculator'),
            'user_commission' => $user_commission
        ]);
     }


    // Final responsive layer: loaded after every frontend/dashboard/form stylesheet.
    wp_enqueue_style(
        'mlr-responsive-css',
        MLR_PLUGIN_URL . 'assets/css/mlr-responsive.css',
        ['mlr-frontend-css', 'mlr-modal-unified-css'],
        MLR_VERSION
    );
}

function mlr_get_js_plans($user_id = null, $visibility_context = 'mlr_loan_request_form') {
    if ($user_id === null) {
        $user_id = is_user_logged_in() ? get_current_user_id() : 0;
    }

    if (function_exists('mlr_get_available_installment_plans_for_user')) {
        $plans = mlr_get_available_installment_plans_for_user($user_id, [
            'active_only' => true,
            'fields' => ['id', 'name', 'type', 'settings', 'required_documents'],
            'order_by' => 'display_order',
            'visibility_context' => sanitize_key($visibility_context),
        ]);
    } else {
        global $wpdb;
        $table_installment_plans = $wpdb->prefix . 'installment_plans';
        $plan_order_sql = function_exists('mlr_get_installment_plan_order_by_sql') ? mlr_get_installment_plan_order_by_sql() : 'ORDER BY id DESC';
        $plans = $wpdb->get_results("SELECT id, name, type, settings, required_documents FROM `{$table_installment_plans}` WHERE is_active = 1 {$plan_order_sql}");
    }

    if (!is_array($plans)) {
        return [];
    }

    if (function_exists('mlr_prepare_frontend_js_plans')) {
        return mlr_prepare_frontend_js_plans($plans);
    }

    return [];
}

function mlr_get_applicant_js_plans() {
    if (function_exists('mlr_get_applicant_visible_installment_plans')) {
        $plans = mlr_get_applicant_visible_installment_plans([
            'active_only' => true,
            'fields' => ['id', 'name', 'type', 'settings', 'required_documents'],
            'order_by' => 'display_order',
        ]);
    } else {
        $plans = [];
    }

    if (!is_array($plans)) {
        return [];
    }

    if (function_exists('mlr_prepare_frontend_js_plans')) {
        return mlr_prepare_frontend_js_plans($plans);
    }

    return [];
}

add_action('wp_ajax_mlr_verify_shahkar', 'mlr_verify_shahkar_callback');
add_action('wp_ajax_nopriv_mlr_verify_shahkar', 'mlr_verify_shahkar_callback');
function mlr_verify_shahkar_callback() {
    check_ajax_referer('mlr_frontend_nonce', 'nonce');

    $user_national_code = function_exists('mlr_normalize_digits')
        ? mlr_normalize_digits($_POST['national_code'] ?? '', true)
        : preg_replace('/\D+/', '', sanitize_text_field($_POST['national_code'] ?? ''));
    $user_mobile = function_exists('mlr_normalize_digits')
        ? mlr_normalize_digits($_POST['mobile_number'] ?? '', true)
        : preg_replace('/\D+/', '', sanitize_text_field($_POST['mobile_number'] ?? ''));

    if (!preg_match('/^\d{10}$/', $user_national_code) || !preg_match('/^09\d{9}$/', $user_mobile)) {
        wp_send_json_error(['message' => 'کد ملی یا شماره موبایل وارد شده نامعتبر است.']);
        return;
    }

    $shahkar_cache_key = 'mlr_shahkar_ok_' . substr(hash('sha256', (function_exists('mlr_get_client_identity') ? mlr_get_client_identity() : (string) get_current_user_id()) . '|' . $user_national_code . '|' . $user_mobile), 0, 40);
    if (get_transient($shahkar_cache_key)) {
        wp_send_json_success(['message' => 'تطابق شماره موبایل و کد ملی تأیید شد.', 'cached' => true]);
        return;
    }
    if (function_exists('mlr_rate_limit_check')) {
        $limit_check = mlr_rate_limit_check('shahkar_verify', 8, HOUR_IN_SECONDS, [$user_national_code, $user_mobile]);
        if (is_wp_error($limit_check)) {
            wp_send_json_error(['message' => $limit_check->get_error_message(), 'retry_after' => (int) (($limit_check->get_error_data()['retry_after'] ?? 0))]);
            return;
        }
    }

    $client_id = get_option('mlr_shahkar_client_id');
    $client_secret = get_option('mlr_shahkar_client_secret');
    $owner_nid = get_option('mlr_shahkar_owner_nid');

    if (empty($client_id) || empty($client_secret) || empty($owner_nid)) {
        mlr_log("Shahkar API Error: Settings not configured.");
        wp_send_json_error(['message' => 'سرویس احراز هویت در دسترس نیست.']);
        return;
    }

    $token_url = 'https://api.finnotech.ir/dev/v2/oauth2/token';
    $auth_string = base64_encode("{$client_id}:{$client_secret}");
    $scope_for_token = 'card:shahkar:get';

    $token_args = [
        'method' => 'POST',
        'timeout' => 20,
        'headers' => [
            'Content-Type' => 'application/json',
            'Authorization' => 'Basic ' . $auth_string,
        ],
        'body' => json_encode([
            'grant_type' => 'client_credentials',
            'nid' => $owner_nid,
            'scopes' => $scope_for_token,
        ]),
    ];

    $token_response = wp_remote_post($token_url, $token_args);

    if (is_wp_error($token_response)) {
        mlr_log("Shahkar API Token Error: " . $token_response->get_error_message());
        wp_send_json_error(['message' => 'خطا در ارتباط با سرویس احراز هویت (مرحله ۱).']);
        return;
    }

    $token_body_raw = wp_remote_retrieve_body($token_response);
    $token_body = json_decode($token_body_raw, true);
    $access_token = isset($token_body['result']['value']) ? $token_body['result']['value'] : null;
    $response_code = wp_remote_retrieve_response_code($token_response);

    if ($response_code !== 200 || !$access_token) {
        // Robust Error Handling for non-JSON or HTML error pages
        $err_msg = 'خطای سرویس توکن';
        if(is_array($token_body) && isset($token_body['error'])) {
            $err_msg = $token_body['error']['message'] ?? $err_msg;
        } else {
             $err_msg = "Status: $response_code. Body: " . substr($token_body_raw, 0, 100);
        }
        mlr_log("Shahkar Token Failed: " . $err_msg);
        wp_send_json_error(['message' => 'دریافت مجوز احراز هویت ناموفق بود.']);
        return;
    }

    $verify_base_url = "https://api.finnotech.ir/mpg/v2/clients/{$client_id}/shahkar/verify";
    $track_id = 'MLR' . time() . random_int(100, 999);

    $verify_url_with_params = add_query_arg([
        'trackId' => $track_id,
        'mobile' => $user_mobile,
        'nationalCode' => $user_national_code,
    ], $verify_base_url);


    $verify_args = [
        'method' => 'GET',
        'timeout' => 25,
        'headers' => [
            'Authorization' => 'Bearer ' . $access_token,
        ],
    ];

    $verify_response = wp_remote_get($verify_url_with_params, $verify_args);

    if (is_wp_error($verify_response)) {
        mlr_log("Shahkar API Verify Error: " . $verify_response->get_error_message());
        wp_send_json_error(['message' => 'خطا در ارتباط با سرویس احراز هویت (مرحله ۲).']);
        return;
    }

    $verify_body_raw = wp_remote_retrieve_body($verify_response);
    $verify_body = json_decode($verify_body_raw, true);
    $verify_response_code = wp_remote_retrieve_response_code($verify_response);

    // Robust Handling for Verify Response
    if ($verify_response_code === 200 && isset($verify_body['result']['isValid']) && $verify_body['result']['isValid'] === true && isset($verify_body['status']) && $verify_body['status'] === 'DONE') {
        set_transient($shahkar_cache_key, 1, 10 * MINUTE_IN_SECONDS);
        wp_send_json_success(['message' => 'تطابق شماره موبایل و کد ملی تأیید شد.']);
    } else {
        $error_detail = 'عدم تطابق یا خطای نامشخص.';
        if (isset($verify_body['result']['isValid']) && $verify_body['result']['isValid'] === false) {
             $error_detail = 'شماره موبایل وارد شده متعلق به این کد ملی نمی‌باشد.';
        } elseif (is_array($verify_body) && isset($verify_body['error']['message'])) {
             $error_detail = $verify_body['error']['message'];
        } elseif ($verify_response_code !== 200) {
            $error_detail = "خطای سرویس (کد: {$verify_response_code}).";
        }
        
        mlr_log("Shahkar Verify Failed: " . $error_detail);
        wp_send_json_error(['message' => $error_detail]);
    }
}
?>
