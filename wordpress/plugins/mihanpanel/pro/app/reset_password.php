<?php
namespace mihanpanel\pro\app;

class reset_password
{
    public static function init(){
        add_filter('mp_disable_reset_password_checked', [__CLASS__, 'handle_disable_reset_password_checked_option']);

        if(!\mihanpanel\app\options::is_active_reset_password()) return false;

        add_filter('mp_reset_password_fields', [__CLASS__, 'handle_filter_change_reset_password_input']);
        add_filter('mp_reset_password_method', [__CLASS__, 'handle_filter_change_reset_password_method'], 10, 2);
        add_filter('mp_reset_password_get_user_data', [__CLASS__, 'handle_filter_user_data_before_start_ajax_request'], 10, 2);
        add_action('mp_reset_password_before_set_code', [__CLASS__, 'handle_reset_password_before_send_verify_code'], 10, 2);
        add_action('mp_reset_password_before_send_code', [__CLASS__, 'handle_reset_password_send_code_by_sms'], 10, 3);
        add_action('mp_reset_password_user_data_for_verify_code', [__CLASS__, 'handle_filter_change_user_data_for_verify_code'], 10, 2);
    }

    public static function handle_filter_change_reset_password_input($fields){
        $is_smart_login = options::get_smart_login_status();
        $state = $is_smart_login ? 'smart_login' : 'normal_login';
        $icon = 'email-icon';
        $label = __('Username or Email', 'mihanpanel');

        if ($is_smart_login){
            $label = __('Mobile or Email', 'mihanpanel');
        }

        $fields['username'] = [
            'icon' => \mihanpanel\app\assets::get_image_url($icon, 'svg'),
            'label' => $label,
            'state' => $state
        ];
        return $fields;
    }

    public static function send_reset_password_code_by_sms($user, $code){
        $sendSmsResult = sms::send_dynamic_code_sms($user, $code);

        if(!$sendSmsResult || !isset($sendSmsResult['status']) || $sendSmsResult['status'] !== 200)
        {
            login_system::deleteUserDynamicCode($user->ID);
            return false;
        }

        return $code;
    }

    public static function handle_filter_user_data_before_start_ajax_request($user, $username){
        if (options::get_smart_login_status()){
            if (filter_var($username, FILTER_VALIDATE_EMAIL)){
                return email_exists($username) ? get_user_by('email', $username) : 0;
            } else {
                return users::get_user_by_phone($username);
            }
        } elseif (options::is_dynamic_password_mode()){
            return users::get_user_by_phone($username);
        } else {
            return $user;
        }
    }

    public static function handle_filter_change_user_data_for_verify_code($user, $username){
        if(!username_exists($username) && !email_exists($username)){
            return users::get_user_by_phone($username);
        }
        return $user;
    }

    public static function handle_filter_change_reset_password_method($method, $username){
        return (options::is_dynamic_password_mode()) ? 'phone' : (options::get_smart_login_status() ? (filter_var($username, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone') : $method);
    }

    public static function handle_reset_password_send_code_by_sms($user, $code, $sendBy){
        if ($sendBy != 'phone') return false;

        $res = ['code' => 400];
        $send = self::send_reset_password_code_by_sms($user, $code);

        if ($send){
            $res['code'] = 200;
            $res['msg'] = __('Verification code has been sent to mobile.', 'mihanpanel');
        } else {
            $res['msg'] = __('An error occurred!', 'mihanpanel');
        }
        \mihanpanel\app\tools::send_json_response($res);
    }

    public static function handle_reset_password_before_send_verify_code($user, $sendBy){
        if ($sendBy != 'phone') return false;

        $has_valid_phone = users::has_valid_phone($user->ID);
        if (!$has_valid_phone) {
            $res = [
                'code' => 400,
                'msg' => esc_html__("Sorry, but you do not have a valid phone.", 'mihanpanel')
            ];
            \mihanpanel\app\tools::send_json_response($res);
        }
    }

    public static function handle_disable_reset_password_checked_option($checked){
        if (options::is_dynamic_password_mode()){
            return 'checked readonly';
        }

        return $checked;
    }
}