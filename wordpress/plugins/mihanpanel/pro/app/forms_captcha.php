<?php
namespace mihanpanel\pro\app;

use mihanpanel\app\session;

class forms_captcha
{
    public static function init()
    {
        add_action('login_enqueue_scripts', [__CLASS__, 'load_captcha_scripts']);

        add_filter('mwpl_login_form_body_classes', [__CLASS__, 'add_class_to_login_body']);

        if (options::is_active_custom_captcha_in_login()){
            add_action('mwpl_login_form_before_submit_button', [__CLASS__, 'display_captcha_wrapper']);
            add_action('mwpl_login_start_process_response', [__CLASS__, 'captcha_process_login_form'], 1);
            add_action('mwpl_sms_login_start_process_response', [__CLASS__, 'captcha_process_login_form'], 1);
            add_action('mwpl_smart_login_start_process_response', [__CLASS__, 'captcha_process_login_form'], 1);
        }

        if (options::is_active_custom_captcha_in_register()){
            add_action('mwpl_register_form_before_submit_button', [__CLASS__, 'display_captcha_wrapper']);
            add_action('mwpl_register_start_process_response', [__CLASS__, 'captcha_process_login_form'], 1);
        }

        if (options::is_active_custom_captcha_in_reset_password()){
            add_action('mwpl_reset_password_form_before_submit_button', [__CLASS__, 'display_captcha_wrapper']);
            add_action('mwpl_reset_password_start_process_response', [__CLASS__, 'captcha_process_login_form'], 1);
        }
    }

    public static function load_captcha_scripts()
    {
        assets::enqueue_script('captcha-custom', assets::get_js_url('captcha'));
        assets::localize_script('captcha-custom', 'mwpl_captcha', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'captcha_type' => options::get_custom_captcha_type(),
        ]);
    }

    public static function display_captcha_wrapper()
    {
        $case_sensitive = options::is_custom_captcha_case_sensitive();
        ?>
        <div class="captcha-field-group">
            <div class="mwpl-input-item">
                <label for="security_code">
                    <span class="mwpl-icon">
                        <img src="<?php echo \mihanpanel\app\assets::get_image_url('security-code-icon', 'svg') ?>" alt="security-code-icon">
                    </span>
                    <span class="mwpl-field">
                        <input autocomplete="off" type="text" name="user_security_code" id="user_security_code" class="security-code-input" placeholder="<?php echo $case_sensitive ? esc_attr__('Case Sensitive', 'mihanpanel') : '' ?>">
                        <span class="mwpl-label-value"><?php _e('Security Code', 'mihanpanel') ?></span>
                    </span>
                </label>
            </div>
            <div class="captcha-code">
                <div class="mwpl-reload-captcha mp-show-reload">
                    <img src="<?php echo \mihanpanel\app\assets::get_image_url('refresh-icon', 'svg') ?>" alt="refresh-icon">
                </div>
                <div class="mwpl-captcha" id="mwpl-captcha-<?php echo rand(100,9999) ?>"><img src="" class="captcha-img" style="display: none"></div>
            </div>
        </div>
    <?php
    }

    public static function captcha_process_login_form($res)
    {
        if ('POST' === $_SERVER['REQUEST_METHOD']) {
            if (true !== self::validate_captcha()) {
                $res['msg'] = esc_html__('Security code verification failed.', 'mihanpanel');
                \mihanpanel\app\tools::send_json_response($res);
            }
            session::unset('mp_captcha_token');
        }
    }

    public static function add_class_to_login_body($classes)
    {
        if (options::is_active_custom_captcha()){
            $classes[] = 'mwpl-with-custom-captcha';
        }
        return $classes;
    }

    public static function validate_captcha()
    {
        $user_response = isset($_POST['user_security_code']) ? (string) \mihanpanel\app\tools::convertNumberLocale($_POST['user_security_code']) : '';
        if (!options::is_custom_captcha_case_sensitive()){
            $user_response = strtolower($user_response);
        }
        $captcha_response = session::get('mp_captcha_token');
        return !empty($user_response) && !empty($captcha_response) && md5($user_response) == $captcha_response;
    }

    static function isGenerateImageAccessible()
    {
        return function_exists('imagecreatetruecolor');
    }
    
    public static function generateCaptchaImage($width, $height, $text) {
        if (!self::isGenerateImageAccessible()) return false;
        $image = imagecreatetruecolor($width, $height);
        $bgColor = imagecolorallocate($image, 255, 255, 255);
        imagefill($image, 0, 0, $bgColor);
        $textColor = imagecolorallocate($image, 0, 0, 0);
        imagettftext($image, 15, 0, 0, 33, $textColor, MW_MIHANPANEL_DIR . '/css/fonts/ttf/captcha-font.ttf', $text);
        ob_start();
        header('Content-type: image/png');
        imagepng($image);
        $imageData = ob_get_clean();
        if (!options::is_custom_captcha_case_sensitive()){
            $text = strtolower($text);
        }
        $hash = md5($text);
        session::store('mp_captcha_token', $hash);
        imagedestroy($image);
        return ['hash' => $hash, 'image' => base64_encode($imageData)];
    }
}