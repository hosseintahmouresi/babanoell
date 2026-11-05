<?php
if (!defined('ABSPATH')) {
    exit;
}

function mlr_log_viewer_page() {
    if (!current_user_can('manage_options')) {
        wp_die('شما دسترسی لازم برای مشاهده این صفحه را ندارید.');
    }

    $log_file = function_exists('mlr_get_log_file_path') ? mlr_get_log_file_path() : (MLR_PLUGIN_DIR . 'mlr_debug.log');
    if (function_exists('mlr_ensure_log_dir')) { mlr_ensure_log_dir(); }

    if (isset($_POST['mlr_clear_log']) && check_admin_referer('mlr_clear_log_nonce')) {
        if (file_exists($log_file)) {
            file_put_contents($log_file, '');
            echo '<div class="updated"><p>فایل لاگ با موفقیت پاک شد.</p></div>';
        }
    }

    $log_content = file_exists($log_file) ? file_get_contents($log_file) : 'فایل لاگ خالی است یا هنوز ایجاد نشده است.';
    ?>
    <div class="wrap">
        <h1>مشاهده لاگ سیستم پیامک</h1>
        <p>در این بخش می‌توانید وقایع مربوط به ارسال پیامک و ارتباط با وب‌سرویس را مشاهده و اشکال‌زدایی کنید.</p>

        <div id="log-viewer-container">
            <textarea readonly id="log-content"><?php echo esc_textarea($log_content); ?></textarea>
        </div>

        <form method="post" style="margin-top: 20px;">
            <?php wp_nonce_field('mlr_clear_log_nonce'); ?>
            <button type="submit" name="mlr_clear_log" class="button button-primary">پاک کردن فایل لاگ</button>
            <a href="<?php echo esc_url(add_query_arg('mlr_download_log', 'true')); ?>" class="button">دانلود فایل لاگ</a>
        </form>
    </div>
    <style>
        #log-viewer-container {
            border: 1px solid #ddd;
            background: #fff;
            padding: 10px;
        }
        #log-content {
            width: 100%;
            height: 600px;
            direction: ltr;
            text-align: left;
            font-family: monospace;
            white-space: pre;
            overflow: auto;
            border: none;
            resize: none;
        }
    </style>
    <?php
}

add_action('admin_init', function() {
    if (isset($_GET['mlr_download_log']) && $_GET['mlr_download_log'] === 'true' && current_user_can('manage_options')) {
        $log_file = function_exists('mlr_get_log_file_path') ? mlr_get_log_file_path() : (MLR_PLUGIN_DIR . 'mlr_debug.log');
    if (function_exists('mlr_ensure_log_dir')) { mlr_ensure_log_dir(); }
        if (file_exists($log_file)) {
            header('Content-Type: text/plain');
            header('Content-Disposition: attachment; filename="mlr_debug.log"');
            readfile($log_file);
            exit;
        }
    }
});