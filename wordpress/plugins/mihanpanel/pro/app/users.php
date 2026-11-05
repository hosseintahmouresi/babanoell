<?php
namespace mihanpanel\pro\app;


class users
{
    static $_user_phone_option = 'mw_user_phone';
    static $_user_has_valid_phone_option = 'mw_user_has_valid_phone';

    static function get_random_username($length=8)
    {
        $chars = 'abcdefghigklmnopqrstuvwxyz1234567890';
        return 'u_' . time() . '_' . substr(str_shuffle($chars), 0, $length);
    }
    static function set_user_phone($uid, $phone)
    {
        $phone = tools::getValidatedPhoneNumber($phone);
        if(!$phone)
        {
            return false;
        }
        $last_phone = self::get_user_phone($uid);
        if($phone == $last_phone)
        {
            return false;
        }
        self::remove_has_valid_phone($uid);
        if(empty($phone))
        {
            return self::remove_user_phone($uid);
        }
        return $phone ? update_user_meta($uid, self::$_user_phone_option, $phone) : false;
    }

    static function get_user_phone($uid)
    {
        return get_user_meta($uid, self::$_user_phone_option, true);
    }
    static function remove_user_phone($uid)
    {
        return delete_user_meta($uid, self::$_user_phone_option);
    }

    static function has_valid_phone($uid)
    {
        return get_user_meta($uid, self::$_user_has_valid_phone_option, true);
    }
    static function set_has_valid_phone($uid)
    {
        return update_user_meta($uid, self::$_user_has_valid_phone_option, 1);
    }
    static function remove_has_valid_phone($uid)
    {
        return delete_user_meta($uid, self::$_user_has_valid_phone_option);
    }

    static function can_use_this_phone($phone, $uid=false)
    {
        $phone = tools::getValidatedPhoneNumber($phone);
        if(!$phone)
        {
            return false;
        }
        if($uid)
        {
            $old_phone = users::get_user_phone($uid);
            if($phone == $old_phone)
            {
                return true;
            }
        }
        $user = get_users([
            'meta_key'  =>  self::$_user_phone_option,
            'meta_value' => $phone,
            'fields' => ['id']
        ]);
        return $user ? false : true;
    }
    static function get_user_by_phone($phone)
    {
        $phone = tools::getValidatedPhoneNumber($phone);
        if(!$phone)
        {
            return false;
        }
        $user_data = get_users([
            'meta_key' => self::$_user_phone_option,
            'meta_value' => $phone,
            'number' => 1
        ]);
        $user_data = $user_data && is_array($user_data) ? array_shift($user_data) : $user_data;
        return $user_data instanceof \WP_User ? $user_data : false;
    }
    static function get_user_by_email($email)
    {
        if(!$email)
        {
            return false;
        }
        return get_user_by('email', $email);
    }

    static function remove_uploaded_field_files($uid)
    {
        $all_fields = file_uploader::get_all_fields(['slug']);
        foreach($all_fields as $field)
        {
            // remove file
            file_uploader::remove_file($field->slug, $uid);
        }
    }
    static function login_user($user_id, $rememeber=false)
    {
        if(!$user_id)
        {
            return false;
        }
        $user = get_user_by('id', $user_id);
        wp_clear_auth_cookie();
        wp_set_current_user($user->ID);
        wp_set_auth_cookie($user->ID, $rememeber);
        do_action('wp_login', $user->user_login, $user);
        wp_safe_redirect(user_admin_url());
        exit;
    }
    static function register_user_in_smart_login($clue)
    {
        if(username_exists($clue))
        {
            return false;
        }
        $isPhone = \mihanpanel\pro\app\tools::getValidatedPhoneNumber($clue);
        // check has user with this phone
        if($isPhone)
        {
            $user = users::get_user_by_phone($clue);
            if($user)
            {
                return false;
            }
        }else{
            $user = users::get_user_by_email($clue);
            if($user)
            {
                return false;
            }
        }
        $password = wp_generate_password();
        $user_data = [
            'user_login' => $clue,
            'user_pass' => $password
        ];
        if(options::get_smart_login_generate_random_username_status())
        {
            $user_data['user_login'] = self::get_random_username();
        }
        if(!$isPhone && filter_var($clue, FILTER_VALIDATE_EMAIL))
        {
            $user_data['user_email'] = $clue;
        }
        $user_id = wp_insert_user($user_data);
        if(is_wp_error($user_id))
        {
            return false;
        }
        // use user id as a uesrname
        $display_name = 'u_' . $user_id;
        // self::change_username($user_id, $username);
        self::change_display_name($user_id, $display_name);
        // update user phone number
        if($isPhone)
        {
            users::set_user_phone($user_id, $clue);
            users::set_has_valid_phone($user_id);
        }
        return $user_id;
    }
    static function change_username($user_id, $new_username)
    {
        global $wpdb;
        return $wpdb->update(
            $wpdb->users,
            [
                'user_login' => $new_username,
                'user_nicename' => $new_username
            ],
            [
                'ID' => $user_id
            ]
        );
    }
    static function get_display_name($user_id)
    {
        $user_data = get_userdata($user_id);
        return $user_data->display_name;
    }
    static function change_display_name($user_id, $display_name)
    {
        return wp_update_user(['ID' => $user_id, 'display_name' => $display_name]);
    }
}