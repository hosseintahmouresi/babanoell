<?php

namespace mihanpanel\pro\app;

use mihanpanel\app\login;
use mihanpanel\app\session;
use mihanpanel\app\tools as liteTools;
use mihanpanel\pro\app\thirdparty\google_login;

class login_system
{
    static function init()
    {
        if(\mihanpanel\app\options::getLoginGuardStatus())
        {
            login_guard::init();
        }
        if(\mihanpanel\pro\app\options::isLoginWithGoogleActive())
        {
            google_login::init();
        }

        add_action('mwpl_login_form_after_start_form', [__CLASS__, 'handleLoginFormTopMessage']);
        add_action('mwpl_register_form_after_start_form', [__CLASS__, 'handleRegisterFormTopMessage']);
        add_action('mwpl_login_enqueue_assets', [__CLASS__, 'enqueueLoginMainAssets']);
        add_action('mwpl_login_enqueue_assets_login_type_sms_login', [__CLASS__, 'enqueueSmsLoginFormAssets']);
        add_action('mwpl_login_enqueue_assets_login_type_smart_login', [__CLASS__, 'enqueueSmartLoginFormAssets']);

        // change login type
        add_filter('mwpl_login_type_form', [__CLASS__, 'filterLoginType']);
        add_filter('mwpl_login/login_form_view', [__CLASS__, 'filterLoginViewFile'], 10, 2);

        // add login form theme class to login form wrapper
        add_filter('mwpl_login_form_body_classes', [__CLASS__, 'filterLoginViewBodyClasses']);

        add_filter('mwpl_login_form_username_field_label_value', [__CLASS__, 'filter_username_label_value_in_login_form']);
        add_filter('mwpl_login_form_phone_field_label_value', [__CLASS__, 'filter_phone_label_value_in_login_form']);
        add_filter('mwpl_login_form_password_field_label_value', [__CLASS__, 'filter_password_label_value_in_login_form']);
        add_filter('mwpl_login_form_dynamic_code_field_label_value', [__CLASS__, 'filter_dynamic_code_label_value_in_login_form']);
        add_filter('mwpl_login_form_login_button_text_value', [__CLASS__, 'filter_login_button_text_value_in_login_form']);
        add_filter('mwpl_login_form_register_button_text_value', [__CLASS__, 'filter_register_button_text_value_in_login_form']);

        // filter redirect_to url after login
        add_filter('mwpl_after_login_redirect_url', [__CLASS__, 'filterAfterLoginUrl'], 15, 2);

        // redirect to certain url after logout
        add_action('wp_logout', [__CLASS__, 'redirectAfterLogout']);

    }
    static function storeUserID($userID)
    {
        return session::store('mwpl_login_form_user_id', $userID);
    }
    static function getStoredUserID()
    {
        return session::get('mwpl_login_form_user_id');
    }
    static function deleteStoredUserID()
    {
        session::unset('mwpl_login_form_user_id');
    }
    static function storeTempUserData($data)
    {
        return session::store('mwpl_login_form_temp_user_data', $data);
    }
    static function getTempUserData()
    {
        return session::get('mwpl_login_form_temp_user_data');
    }
    static function deleteTempUserData()
    {
        return session::unset('mwpl_login_form_temp_user_data');
    }

    static function getNewDynamicCodeData()
    {
        return login::getNewDynamicCodeData();
    }

    static function setUserDynamicCode($userID)
    {
        return login::setUserDynamicCode($userID);
    }
    static function generateAndStoreNewCodeDataForTempUser()
    {
        $codeData = self::getNewDynamicCodeData();
        return session::store('mwpl_login_form_smart_login_new_user_code', $codeData) ? $codeData : false;
    }
    static function getTempUserCodeData()
    {
        return session::get('mwpl_login_form_smart_login_new_user_code');
    }
    static function deleteTempUserCodeData()
    {
        return session::unset('mwpl_login_form_smart_login_new_user_code');
    }
    static function deleteUserDynamicCode($userID)
    {
        session::unset('mwpl_login_form_dynamic_code_data');
    }

    static function hasValidDynamicCode($userID)
    {
        $codeData = session::get('mwpl_login_form_dynamic_code_data');
        if (!$codeData || !isset($codeData['user_id']) || $codeData['user_id'] != $userID) {
            return false;
        }
        $expirationTime = isset($codeData['expiration']) ? $codeData['expiration'] : false;
        if (!$expirationTime) {
            return false;
        }
        $currentTime = strtotime('now');
        return $currentTime <= $expirationTime ? $codeData : false;
    }
    static function hasTempUserValidDynamicCode()
    {
        $codeData = self::getTempUserCodeData();
        if (!$codeData || !isset($codeData['code'])) {
            return false;
        }
        $expirationTime = isset($codeData['expiration']) ? $codeData['expiration'] : false;
        if (!$expirationTime) {
            return false;
        }
        return strtotime('now') <= $expirationTime ? $codeData : false;
    }
    static function validateDynamicCode($code, $userID)
    {
        return login::validateDynamicCode($code, $userID);
    }

    static function validateTempUserDynamicCode($code)
    {
        $validCodeData = self::hasTempUserValidDynamicCode();
        if (!$validCodeData) {
            return false;
        }
        return $code == intval($validCodeData['code']);
    }
    static function createNewUserViaEmail($emailAddress)
    {
        if (!$emailAddress) {
            return false;
        }

        $password = wp_generate_password();

        $newUserData = [
            'user_pass' => $password,
            'user_email' => $emailAddress,
        ];
        $newUserData['user_login'] = \mihanpanel\pro\app\options::get_smart_login_generate_random_username_status()
            ? \mihanpanel\pro\app\users::get_random_username()
            : $emailAddress;
        
        $userID = wp_insert_user($newUserData);
        if(is_wp_error($userID))
        {
            return false;
        }

        // change display name
        $displayName = 'u_' . $userID;
        \mihanpanel\pro\app\users::change_display_name($userID, $displayName);
        return $userID;
    }
    static function createNewUserViaPhoneNumber($phoneNumber)
    {
        $phoneNumber = \mihanpanel\pro\app\tools::getValidatedPhoneNumber($phoneNumber);
        if(!$phoneNumber)
        {
            return false;
        }
        $newUserData['user_pass'] = wp_generate_password();
        $newUserData['user_login'] = \mihanpanel\pro\app\options::get_smart_login_generate_random_username_status()
            ? \mihanpanel\pro\app\users::get_random_username()
            : $phoneNumber;

        $userID = wp_insert_user($newUserData);
        if(is_wp_error($userID))
        {
            return false;
        }
            
        \mihanpanel\pro\app\users::set_user_phone($userID, $phoneNumber);
        \mihanpanel\pro\app\users::set_has_valid_phone($userID);

        return $userID;
    }
    static function enqueueLoginMainAssets()
    {
        $version = liteTools::get_plugin_version();
        $loginPro = assets::get_css_url('user/login');
        wp_enqueue_style('mwpl_login_pro', $loginPro, [], $version);

        // handle options style
        $additionalStyle = '';
        if($loginButtonTextColor = options::get_login_button_text_color())
        {
            $additionalStyle .= '.mwpl-login-body .mwpl-form-fields .mwpl-input-item input[type="submit"]{ color: '.$loginButtonTextColor.';}';
        }
        if($registerButtonTextColor = options::get_register_button_text_color())
        {
            $additionalStyle .= '.mwpl-login-body .mwpl-form-fields .mwpl-input-item .mwpl-register-link{ color: '. $registerButtonTextColor .'; border-color: '.$registerButtonTextColor.'}';
        }
        if($registerButtonBgColor = options::get_register_button_bg_color())
        {
            $additionalStyle .= '.mwpl-login-body .mwpl-form-fields .mwpl-input-item .mwpl-register-link{ background-color: '. $registerButtonBgColor .'}';
        }
        if($loginFieldLabelTextColor = options::get_login_fields_label_color())
        {
            $additionalStyle .= '.mwpl-login-body .mwpl-form-fields .mwpl-input-item label .mwpl-field .mwpl-label-value{ color: '.$loginFieldLabelTextColor.' }';
        }
        if($loginFieldBgColor = options::get_login_fields_bg_color())
        {
            $additionalStyle .= '.mwpl-login-body .mwpl-form-fields .mwpl-input-item:not(.mwpl-field-type-radio) label{ background-color: '.$loginFieldBgColor.' }';
        }
        if($loginFieldTextColor = options::get_login_fields_text_color())
        {
            $additionalStyle .= '.mwpl-login-body .mwpl-form-fields .mwpl-input-item label .mwpl-field input, .mwpl-login-body .mwpl-form-fields .mwpl-input-item label .mwpl-field textarea { color: '.$loginFieldTextColor.'}';
        }
        if($loginInfoMessageBoxTextColor = options::get_login_info_messagebox_text_color())
        {
            $additionalStyle .= '.mwpl-login-body .mwpl-form-top-message { color: '.$loginInfoMessageBoxTextColor.' }';
        }
        if($loginInfoMessageBoxBgColor = options::get_login_info_messagebox_bg_color())
        {
            $additionalStyle .= '.mwpl-login-body .mwpl-form-top-message { background-color: '.$loginInfoMessageBoxBgColor.' }';
        }

        $font_name = apply_filters('mwpl_assets/main_font_name', 'iranyekan');
        if($font_name)
        {
            $additionalStyle .= 'body,a,h1,h2,h3,h5,h6,h4,span:not(.dashicons),td,tr,input,p{font-family:'.$font_name.'}';
        }

        if($additionalStyle)
        {
            wp_add_inline_style('mwpl_login_pro', $additionalStyle);
        }
    }
    static function enqueueSmsLoginFormAssets()
    {
        $version = liteTools::get_plugin_version();

        $smsLoginCss = assets::get_css_url('user/sms_login');
        wp_enqueue_style('mwpl_sms_login', $smsLoginCss, null, $version);

        $smsLoginJs = assets::get_js_url('user/sms_login');
        wp_enqueue_script('mwpl_sms_login', $smsLoginJs, ['jquery', 'mwpl_login'], $version, true);

        $data = [
            'au' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mwpl_login_nonce'),
            'msg' => [
                'missing_phone_field' => esc_html__('Please fill phone number field', 'mihanpanel'),
                'missing_code_field' => esc_html__('Please fill code field', 'mihanpanel'),
                'has_error_in_request' => esc_html__('Has error in request', 'mihanpanel'),
                'resend_dynamic_code' => __('Resend code', 'mihanpanel'),
            ],
        ];    
        wp_localize_script('mwpl_sms_login', 'mwpl_data', $data);
    }
    static function enqueueSmartLoginFormAssets()
    {
        $version = liteTools::get_plugin_version();

        $smartLoginCss = assets::get_css_url('user/smart_login');
        wp_enqueue_style('mwpl_smart_login', $smartLoginCss, null, $version);

        $smartLoginJs = assets::get_js_url('user/smart_login');
        wp_enqueue_script('mwpl_smart_login', $smartLoginJs, ['jquery', 'mwpl_login'], $version, true);

        $data = [
            'au' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mwpl_login_nonce'),
            'msg' => [
                'missing_username_field' => esc_html__('Please fill username field', 'mihanpanel'),
                'missing_password_field' => esc_html__('Please fill password field', 'mihanpanel'),
                'missing_code_field' => esc_html__('Please fill code field', 'mihanpanel'),
                'has_error_in_request' => esc_html__('Has error in request', 'mihanpanel'),
                'send_dynamic_code' => __('Login via dynamic code', 'mihanpanel'),
                'back' => __('Back', 'mihanpanel'),
                'resend_dynamic_code' => __('Resend code', 'mihanpanel'),
            ],
        ];    
        wp_localize_script('mwpl_smart_login', 'mwpl_data', $data);
    }

    static function handleLoginFormTopMessage()
    {
        $message = options::get_login_form_top_message_text();
        if(!$message)
        {
            return false;
        }
        ?>
        <span class="mwpl-notice-box mwpl-form-top-message"><?php echo $message?></span>
        <?php
    }
    static function handleRegisterFormTopMessage()
    {
        $message = options::get_register_head_text();
        if(!$message)
        {
            return false;
        }
        ?>
        <span class="mwpl-notice-box mwpl-form-top-message"><?php echo $message?></span>
        <?php
    }
    static function filterLoginType($loginType)
    {
        // check is active SmartLogin or Handle wp password or sms login
        $smartLoginStatus = options::get_smart_login_status();
        if($smartLoginStatus)
        {
            $loginType = 'smart_login';
        }else{
            if(options::get_current_password_type() != 'default')
            {
                $loginType = 'sms_login';
            }
        }
        return $loginType;
    }
    static function filterLoginViewFile($formView, $loginType)
    {
        if($loginType == 'smart_login' || $loginType == 'sms_login')
        {
            $formView = views::get('user.'.$loginType.'_form');
        }
        return $formView;
    }
    static function filterLoginViewBodyClasses($classList)
    {
        // check login form theme
        if($loginFormTheme = \mihanpanel\pro\app\options::get_login_form_theme())
        {
            $classList[] = 'mwpl-' . $loginFormTheme;
        }

        // check form position
        if($loginFormPosition = \mihanpanel\pro\app\options::get_login_form_position())
        {
            $classList[] = 'mwpl-' . $loginFormPosition;
        }
        return $classList;
    }

    public static function handle_load_login_lottie_assets(){
        $lottie_file = options::get_login_form_lottie_file();

        if (empty($lottie_file))
            return false;

        assets::enqueue_script('mp_lottie', assets::get_js_url('lottie.min'), null, null, false);
        assets::enqueue_script('mp_lottie_animation', assets::get_js_url('lottie_animation'));
    }

    public static function load_login_custom_style()
    {
        $file = MW_PRO_INC_DIR . 'css.php';

        if (!file_exists($file) || !is_readable($file))
            return false;

        ob_start();
        include_once $file;
        $css = ob_get_clean();
        $css = preg_replace('/<style[^>]*>(.*?)<\/style>/s', '$1', $css);
        if (!empty($css)){
            wp_add_inline_style('mwpl_login', $css);
        }
    }

    public static function handle_details_in_login_cover_wrapper()
    {
        $lottie_file = options::get_login_form_lottie_file();

        if (!empty($lottie_file)){
            echo "<div id='login-cover-lottie' class='mp-lottie-file-wrap' data-src='{$lottie_file}'></div>";
        }

        $login_form_bg_type = options::get_login_form_cover_background_option('type', 'simple');
        $login_form_video_url = options::get_login_form_cover_background_option('video');

        if ($login_form_bg_type === 'video' && !empty($login_form_video_url)){
            echo "<video src='{$login_form_video_url}' autoplay loop></video>";
        }
    }

    public static function handle_login_page_video_background()
    {
        $login_bg_type = options::get_login_page_background_option('type', 'simple');
        $login_video_url = options::get_login_page_background_option('video');

        if ($login_bg_type === 'video' && !empty($login_video_url)){
            echo "<video src='{$login_video_url}' autoplay loop class='login-body-video-bg'></video>";
        }
    }
    static function filter_username_label_value_in_login_form($value)
    {
        if(options::is_edit_username_field_label_in_login_form())
        {
            return options::get_username_label_value_in_login_form();
        }
        return $value;
    }
    static function filter_phone_label_value_in_login_form($value)
    {
        if(options::is_edit_phone_field_label_in_login_form())
        {
            return options::get_phone_label_value_in_login_form();
        }
        return $value;
    }
    static function filter_password_label_value_in_login_form($value)
    {
        if(options::is_edit_password_field_label_in_login_form())
        {
            return options::get_password_label_value_in_login_form();
        }
        return $value;
    }
    static function filter_dynamic_code_label_value_in_login_form($value)
    {
        if(options::is_edit_dynamic_code_field_label_in_login_form())
        {
            return options::get_dynamic_code_label_value_in_login_form();
        }
        return $value;
    }
    static function filter_login_button_text_value_in_login_form($value)
    {
        if(options::is_edit_login_button_text_in_login_form())
        {
            return options::get_login_button_text_value_in_login_form();
        }
        return $value;
    }
    static function filter_register_button_text_value_in_login_form($value)
    {
        if(options::is_edit_register_button_text_in_login_form())
        {
            return options::get_register_button_text_value_in_login_form();
        }
        return $value;
    }
    static function filterAfterLoginUrl($url, $userID)
    {
        // handle after login url option
        if(($newUrl = options::getNormalRolesAfterLoginRedirectUrl()) && !is_super_admin($userID))
        {
            return $newUrl;
        }
        return $url;
    }
    static function redirectAfterLogout($userID)
    {
        if($redirectUrl = Options::getAfterLogoutRedirectUrl())
        {
            wp_safe_redirect($redirectUrl);
            exit;
        }
    }
}
