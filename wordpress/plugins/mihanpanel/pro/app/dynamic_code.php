<?php
namespace mihanpanel\pro\app;

use mihanpanel\app\session;

class dynamic_code
{
    static $_dynamic_code = 'mw_user_dynamic_pass';
    static $_expiration_time = 'mw_user_dynamic_pass_expiration';
    static function generate_new_code()
    {
        $unc = random_int(100000, 999999);
        return $unc;
    }
    static function get_code_time_limit()
    {
        return 2*60;
    }
    static function set_smart_login_new_code($phone, &$code=false, &$expiration_time=false)
    {
        $code = self::generate_new_code();
        $current_time = strtotime('now');
        $limit = self::get_code_time_limit();
        $expiration_time = $current_time + $limit;
        // store user code
        $code_data['code'] = $code;
        $code_data['expiration'] = $expiration_time;
        $code_data['phone'] = $phone;
        return session::store('mwpl_smart_login_code_data', $code_data);
    }
    static function has_valid_smart_login_code($phone)
    {
        $data = session::get('mwpl_smart_login_code_data');
        if(!$data || !isset($data['phone']) || $phone != $data['phone'])
        {
            return false;
        }
        $expiration_time =  isset($data['expiration']) ? $data['expiration'] : false;
        if(!$expiration_time)
        {
            return false;
        }
        $current_time = strtotime('now');
        return $current_time <= $expiration_time ? $expiration_time : false;
    }
    static function remove_smart_login_code()
    {
        session::unset('mwpl_smart_login_code_data');
    }
    static function validate_smart_login_code($code, $phone)
    {
        $code_data = session::get('mwpl_smart_login_code_data');
        if(!isset($code_data['code']) || !isset($code_data['expiration']) || !isset($code_data['phone']))
        {
            return false;
        }
        if($phone != $code_data['phone'] || intval($code_data['code']) != $code)
        {
            return false;
        }
        // validate code time
        $current_time = strtotime('now');
        return $current_time <= $code_data['expiration'];
    }
    static function set_user_new_code($uid, &$code=false, &$expiration_time=false)
    {
        $code = self::generate_new_code();
        $current_time = strtotime('now');
        $limit = self::get_code_time_limit();
        $expiration_time = $current_time + $limit;
        $user_code_res = update_user_meta($uid, self::$_dynamic_code, $code);
        $expiration_time_res = update_user_meta($uid, self::$_expiration_time, $expiration_time);
        return $user_code_res && $expiration_time_res;
    }
    static function get_user_code($uid)
    {
        return get_user_meta($uid, self::$_dynamic_code, true);
    }
    static function get_user_code_expiration_time($uid)
    {
        return intval(get_user_meta($uid, self::$_expiration_time, true));
    }
    static function user_has_valid_code($uid, &$expiration_time=false)
    {
        $expiration_time =  self::get_user_code_expiration_time($uid);
        if(!$expiration_time)
        {
            return false;
        }
        $current_time = strtotime('now');
        return $current_time <= $expiration_time;
    }
    static function validate_code_time($uid, $code)
    {
        $expiration_time = self::get_user_code_expiration_time($uid);
        $current_time = strtotime('now');
        return $current_time <= $expiration_time;
    }
    static function validate_code($uid, $code)
    {
        $user_code = self::get_user_code($uid);
        return intval($user_code) == $code ? self::validate_code_time($uid, $code) : false;
    }
    static function remove_user_dynamic_code($uid)
    {
        delete_user_meta($uid, self::$_dynamic_code);
        delete_user_meta($uid, self::$_expiration_time);
    }
}