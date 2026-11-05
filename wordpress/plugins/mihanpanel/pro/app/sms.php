<?php
namespace mihanpanel\pro\app;
class sms
{
    private static function filter_sms_content($user, $code, $content)
    {
        $search = ['[[user_login]]', '[[display_name]]', '[[code]]'];
        $username = $user ? $user->user_login : '';
        $display_name = $user ? $user->display_name : '';
        $replace = [$username, $display_name, $code];
        $content = str_replace($search, $replace, $content);
        return $content;
    }
    static function get_phone_validation_sms_content($user, $code)
    {
        $content = options::get_phone_validation_sms_content();
        if(!$content)
        {
            $content = esc_html__('Your validation code is: [[code]]', 'mihanpanel');
        }
        $content = self::filter_sms_content($user, $code, $content);
        return $content;
    }
    static function get_login_dynamic_code_sms_content($user, $code)
    {
        $content = options::get_dynamic_code_sms_content();
        if(!$content)
        {
            $content = esc_html__('Your login code is: [[code]]', 'mihanpanel');
        }
        $content = self::filter_sms_content($user, $code, $content);
        return $content;
    }
    static function send_to_user($uid, $content)
    {
        $phone = users::get_user_phone($uid);
        if(!$phone)
        {
            return false;
        }
        $phone = tools::getValidatedPhoneNumber($phone);
        $send_response = sms_provider::send_message($phone, $content);
        $validate_send_response = sms_provider::validate_send_message($send_response);
        return $validate_send_response;
    }
    static function send_phone_validation_sms($user, $code)
    {
        $content = self::get_phone_validation_sms_content($user, $code);
        return self::send_to_user($user->ID, $content);
    }
    static function send_dynamic_code_sms($user, $code)
    {
        $content = self::get_login_dynamic_code_sms_content($user, $code);
        return self::send_to_user($user->ID, $content);
    }
    static function send_smart_login_code($phone, $code)
    {
        $content = self::get_login_dynamic_code_sms_content(false, $code);
        $phone = tools::getValidatedPhoneNumber($phone);
        if(!$phone)
        {
            return false;
        }
        $send_response = sms_provider::send_message($phone, $content);
        $validate_send_response = sms_provider::validate_send_message($send_response);
        return $validate_send_response;
    }
}