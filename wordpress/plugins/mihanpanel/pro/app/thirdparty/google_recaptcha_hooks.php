<?php
namespace mihanpanel\pro\app\thirdparty;

use mihanpanel\app\tools;
use mihanpanel\pro\app\assets;
use mihanpanel\pro\app\options;

class google_recaptcha_hooks
{
    public static function init(){
        add_action('login_enqueue_scripts', [__CLASS__, 'load_recaptcha_scripts']);

        add_filter('mwpl_login_form_body_classes', [__CLASS__, 'add_class_to_login_body']);

        if (options::is_active_google_recaptcha_in_login()){
            add_action('mwpl_login_form_before_submit_button', [__CLASS__, 'display_recaptcha_wrapper']);
            add_action('mwpl_login_start_process_response', [__CLASS__, 'recaptcha_process_login_form'], 1);
            add_action('mwpl_sms_login_start_process_response', [__CLASS__, 'recaptcha_process_login_form'], 1);
            add_action('mwpl_smart_login_start_process_response', [__CLASS__, 'recaptcha_process_login_form'], 1);
        }

        if (options::is_active_google_recaptcha_in_register()){
            add_action('mwpl_register_form_before_submit_button', [__CLASS__, 'display_recaptcha_wrapper']);
            add_action('mwpl_register_start_process_response', [__CLASS__, 'recaptcha_process_login_form'], 1);
        }

        if (options::is_active_google_recaptcha_in_reset_password()){
            add_action('mwpl_reset_password_form_before_submit_button', [__CLASS__, 'display_recaptcha_wrapper']);
            add_action('mwpl_reset_password_start_process_response', [__CLASS__, 'recaptcha_process_login_form'], 1);
        }
    }

    public static function load_recaptcha_scripts(){
        $version = tools::get_plugin_version();
        $captcha_type = google_recaptcha::get_captcha_type();
        $site_key = google_recaptcha::get_site_key();

        if (empty($site_key))
            return false;

        wp_enqueue_script('mwp-google-recaptcha-custom', assets::get_js_url('google_recaptcha'), array(), $version, false);
        wp_localize_script('mwp-google-recaptcha-custom', 'agrRecaptcha', array('site_key' => $site_key));

        $api_url = google_recaptcha::get_api_url($captcha_type);

        if (!empty($api_url)) {
            wp_enqueue_script('mwp-google-recaptcha-api', $api_url, array(), $version, false);
        }
    }

    public static function display_recaptcha_wrapper(){
        $recaptcha_type = google_recaptcha::get_captcha_type();
        if ($recaptcha_type === 'v2'){
            echo '<div class="agr-recaptcha-wrapper"></div>';
        } elseif ($recaptcha_type === 'v3'){
            echo '<input type="hidden" name="g-recaptcha-response" class="g-recaptcha-response">';
        }
    }

    public static function recaptcha_process_login_form($res){
        $has_error = false;
        $type = google_recaptcha::get_captcha_type();
        $is_post = 'POST' === $_SERVER['REQUEST_METHOD'];

        if ($is_post && isset($_POST['grr_reject']) && $_POST['grr_reject'] == 'true')
            return false;

        if ($is_post && isset($_POST['g-recaptcha-response'])) {
            if (true !== google_recaptcha::validate_recaptcha()) {
                $has_error = true;
                $res['msg'] = sprintf(esc_html__('Google Recaptcha verification failed %s', 'mihanpanel'), ($type == 'v2' ? '.' : sprintf(', %s', __('reload the page', 'mihanpanel'))));
            }
        } else {
            $has_error = true;
            $res['msg'] = sprintf(esc_html__('Please Check Google Recaptcha %s', 'mihanpanel'), ($type == 'v2' ? '.' : sprintf(', %s', __('reload the page', 'mihanpanel'))));
        }
        if ($has_error){
            tools::send_json_response($res);
        }
    }

    public static function add_class_to_login_body($classes){
        if (options::is_active_google_recaptcha_in_login()){
            $classes[] = 'mwpl-with-grecaptcha';
        }
        return $classes;
    }
}