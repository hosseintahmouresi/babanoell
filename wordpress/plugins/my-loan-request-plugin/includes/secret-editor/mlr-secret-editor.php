<?php
/**
 * Secret Editor – پنل مخفی ویرایش و حذف درخواست‌ها
 * لینک ورود: https://YOUR-SITE.com/?mlr_secret_editor=1 (در هیچ منویی نمایش داده نمی‌شود)
 * نام کاربری پیش‌فرض: mlr_super_editor
 * رمز عبور پیش‌فرض: MlR#Edit2025!
 */

if (!defined('ABSPATH')) {
    exit;
}

const MLR_SECRET_EDITOR_SLUG = 'mlr_secret_editor';
const MLR_SECRET_EDITOR_COOKIE = 'mlr_secret_editor_token';

// پیش‌فرض: اگر گزینه‌ها خالی باشند، یک بار مقداردهی می‌شود
if (get_option('mlr_secret_editor_pass_hash') === false) {
    update_option('mlr_secret_editor_user', 'mlr_super_editor');
    update_option('mlr_secret_editor_pass_hash', password_hash('MlR#Edit2025!', PASSWORD_DEFAULT));
}

function mlr_secret_editor_get_user() {
    return get_option('mlr_secret_editor_user', 'mlr_super_editor');
}

function mlr_secret_editor_verify_password($password) {
    $hash = get_option('mlr_secret_editor_pass_hash');
    if ($hash) {
        return password_verify($password, $hash);
    }
    return $password === 'MlR#Edit2025!';
}

function mlr_secret_editor_is_logged_in() {
    if (empty($_COOKIE[MLR_SECRET_EDITOR_COOKIE])) {
        return false;
    }
    $stored = get_option('mlr_secret_editor_session');
    return $stored && is_string($stored) && hash_equals($stored, $_COOKIE[MLR_SECRET_EDITOR_COOKIE]);
}

function mlr_secret_editor_set_session() {
    $token = bin2hex(random_bytes(32));
    update_option('mlr_secret_editor_session', $token, false);
    $expire = time() + (24 * 3600); // 24 ساعت
    setcookie(MLR_SECRET_EDITOR_COOKIE, $token, $expire, '/', '', is_ssl(), true);
    setcookie(MLR_SECRET_EDITOR_COOKIE, $token, $expire, '/', '', false, true);
}

function mlr_secret_editor_logout() {
    delete_option('mlr_secret_editor_session');
    setcookie(MLR_SECRET_EDITOR_COOKIE, '', time() - 3600, '/', '', true, true);
    setcookie(MLR_SECRET_EDITOR_COOKIE, '', time() - 3600, '/', '', false, true);
}

add_action('init', 'mlr_secret_editor_register_query_var');
function mlr_secret_editor_register_query_var() {
    add_rewrite_tag('%' . MLR_SECRET_EDITOR_SLUG . '%', '1');
}

add_filter('query_vars', 'mlr_secret_editor_query_vars');
function mlr_secret_editor_query_vars($vars) {
    $vars[] = MLR_SECRET_EDITOR_SLUG;
    return $vars;
}

add_action('template_redirect', 'mlr_secret_editor_handle_request', 1);
function mlr_secret_editor_handle_request() {
    $val = isset($_GET[MLR_SECRET_EDITOR_SLUG]) ? $_GET[MLR_SECRET_EDITOR_SLUG] : get_query_var(MLR_SECRET_EDITOR_SLUG);
    if ($val !== '1' && $val !== true) {
        return;
    }

    if (isset($_GET['mlr_secret_logout'])) {
        mlr_secret_editor_logout();
        wp_safe_redirect(add_query_arg(MLR_SECRET_EDITOR_SLUG, '1', home_url('/')));
        exit;
    }

    if (isset($_POST['mlr_secret_editor_login']) && isset($_POST['mlr_secret_user']) && isset($_POST['mlr_secret_pass'])) {
        check_admin_referer('mlr_secret_editor_login');
        $user = sanitize_text_field($_POST['mlr_secret_user']);
        $pass = $_POST['mlr_secret_pass'];
        $expected_user = mlr_secret_editor_get_user();
        if ($user === $expected_user && mlr_secret_editor_verify_password($pass)) {
            mlr_secret_editor_set_session();
            wp_safe_redirect(add_query_arg(MLR_SECRET_EDITOR_SLUG, '1', home_url('/')));
            exit;
        }
    }

    if (!mlr_secret_editor_is_logged_in()) {
        mlr_secret_editor_render_login();
        exit;
    }

    mlr_secret_editor_render_dashboard();
    exit;
}

function mlr_secret_editor_render_login() {
    wp_enqueue_style('dashicons');
    wp_enqueue_style('mlr-secret-editor-css', MLR_PLUGIN_URL . 'assets/css/secret-editor.css', [], MLR_VERSION);
    $url = add_query_arg(MLR_SECRET_EDITOR_SLUG, '1', home_url('/'));
    header('Content-Type: text/html; charset=utf-8');
    ?>
    <!DOCTYPE html>
    <html dir="rtl" lang="fa">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>ورود به پنل ویرایش</title>
        <?php wp_head(); ?>
    </head>
    <body class="mlr-secret-editor-body mlr-secret-login">
        <div class="mlr-secret-editor-blur"></div>
        <div class="mlr-secret-editor-login-box">
            <div class="mlr-secret-editor-login-header">
                <span class="dashicons dashicons-lock"></span>
                <h1>ورود به پنل ویرایش درخواست‌ها</h1>
                <p>این صفحه در منوها نمایش داده نمی‌شود.</p>
            </div>
            <form method="post" action="<?php echo esc_url($url); ?>" class="mlr-secret-editor-login-form">
                <?php wp_nonce_field('mlr_secret_editor_login'); ?>
                <input type="hidden" name="mlr_secret_editor_login" value="1">
                <div class="mlr-secret-field">
                    <label for="mlr_secret_user">نام کاربری</label>
                    <input type="text" id="mlr_secret_user" name="mlr_secret_user" autocomplete="username" required>
                </div>
                <div class="mlr-secret-field">
                    <label for="mlr_secret_pass">رمز عبور</label>
                    <input type="password" id="mlr_secret_pass" name="mlr_secret_pass" autocomplete="current-password" required>
                </div>
                <button type="submit" class="mlr-secret-btn mlr-secret-btn-primary">ورود</button>
            </form>
        </div>
        <?php wp_footer(); ?>
    </body>
    </html>
    <?php
}

function mlr_secret_editor_render_dashboard() {
    wp_enqueue_style('dashicons');
    wp_enqueue_style('mlr-secret-editor-css', MLR_PLUGIN_URL . 'assets/css/secret-editor.css', [], MLR_VERSION);
    wp_enqueue_script('mlr-secret-editor-js', MLR_PLUGIN_URL . 'assets/js/secret-editor.js', ['jquery'], MLR_VERSION, true);
    wp_localize_script('mlr-secret-editor-js', 'mlr_secret_editor', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('mlr_secret_editor_nonce'),
        'logout_url' => add_query_arg('mlr_secret_logout', '1', add_query_arg(MLR_SECRET_EDITOR_SLUG, '1', home_url('/'))),
    ]);
    $logout_url = add_query_arg('mlr_secret_logout', '1', add_query_arg(MLR_SECRET_EDITOR_SLUG, '1', home_url('/')));
    header('Content-Type: text/html; charset=utf-8');
    ?>
    <!DOCTYPE html>
    <html dir="rtl" lang="fa">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>پنل ویرایش درخواست‌ها</title>
        <?php wp_head(); ?>
    </head>
    <body class="mlr-secret-editor-body mlr-secret-dashboard">
        <div class="mlr-secret-editor-blur"></div>
        <div class="mlr-secret-editor-wrap">
            <header class="mlr-secret-editor-header">
                <h1><span class="dashicons dashicons-edit-large"></span> پنل ویرایش و حذف درخواست‌ها</h1>
                <a href="<?php echo esc_url($logout_url); ?>" class="mlr-secret-btn mlr-secret-btn-outline">خروج</a>
            </header>
            <div class="mlr-secret-editor-toolbar">
                <input type="search" id="mlr-secret-search" class="mlr-secret-input" placeholder="جستجو (شناسه، نام، کد ملی، موبایل...)">
                <select id="mlr-secret-status" class="mlr-secret-select">
                    <option value="">همه وضعیت‌ها</option>
                    <?php
                    $statuses = ['در حال بررسی', 'نقص مدارک', 'رد شده', 'تأیید کارشناس', 'تکمیل مدارک', 'تأیید مدارک', 'اعطا شده'];
                    foreach ($statuses as $s) {
                        echo '<option value="' . esc_attr($s) . '">' . esc_html($s) . '</option>';
                    }
                    ?>
                </select>
                <button type="button" id="mlr-secret-filter" class="mlr-secret-btn mlr-secret-btn-primary">فیلتر</button>
            </div>
            <div class="mlr-secret-editor-table-wrap">
                <table class="mlr-secret-editor-table" id="mlr-secret-requests-table">
                    <thead>
                        <tr>
                            <th>شناسه</th>
                            <th>متقاضی</th>
                            <th>کد ملی</th>
                            <th>وضعیت</th>
                            <th>مبلغ</th>
                            <th>تاریخ ثبت</th>
                            <th>عملیات</th>
                        </tr>
                    </thead>
                    <tbody id="mlr-secret-requests-tbody">
                        <tr><td colspan="7" class="mlr-secret-loading">در حال بارگذاری...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        <div id="mlr-secret-modal-overlay" class="mlr-secret-modal-overlay" style="display:none;"></div>
        <div id="mlr-secret-modal" class="mlr-secret-modal" style="display:none;">
            <div class="mlr-secret-modal-head">
                <h2 id="mlr-secret-modal-title">ویرایش درخواست</h2>
                <button type="button" class="mlr-secret-modal-close">&times;</button>
            </div>
            <div class="mlr-secret-modal-body" id="mlr-secret-modal-body">
                <!-- محتوا از AJAX پر می‌شود -->
            </div>
        </div>
        <?php wp_footer(); ?>
    </body>
    </html>
    <?php
}
