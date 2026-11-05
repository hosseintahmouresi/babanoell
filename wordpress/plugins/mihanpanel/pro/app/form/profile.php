<?php
namespace mihanpanel\pro\app\form;

use mihanpanel\pro\app\avatar;
use mihanpanel\pro\app\file_uploader;
use mihanpanel\pro\app\options;
use mihanpanel\pro\app\smart_login;
use mihanpanel\pro\app\users;

class profile
{
    private static $_uid;
    static function handle_form_enctype($type)
    {
        if(options::is_mihan_avatar_enable())
        {
            $type = 'multipart/form-data';
        }
        return $type;
    }
    static function handle_form_submit($uid)
    {
        #HTHIS
        self::$_uid = $uid;
        // check for remove avatar
        if(isset($_POST['mw_remove_user_avatar']))
        {
            self::remove_avatar_handler();
        }
        // check for avatar
        if(
            isset($_FILES['mw_user_avatar'])
            && !$_FILES['mw_user_avatar']['error']
            && $_FILES['mw_user_avatar']['size'])
        {
            $avatar_res = self::upload_avatar_handler($_FILES['mw_user_avatar']);
            if(!$avatar_res)
            {
                \mihanpanel\app\tools::do_redirect();
            }
        }

        // check files field
        if(isset($_FILES['mw_fields_files']))
        {
            file_uploader::upload_field_handle($_FILES['mw_fields_files'], self::$_uid);
        }
        // check remove files fields value
        if(isset($_POST['remove_file_field_value']))
        {
            file_uploader::remove_handler($_POST['remove_file_field_value'], self::$_uid);
        }

        if(isset($_POST['other']))
        {
            self::handle_other_fields($_POST['other']);
        }
    }
    
    static function upload_avatar_handler($avatar_data)
    {
        avatar::upload_and_set_avatar('mw_user_avatar', self::$_uid);
    }
    static function remove_avatar_handler()
    {
        avatar::remove_user_avatar(self::$_uid);
    }
    static function handle_other_fields($form_data)
    {
        // handle phone number
        self::handle_update_phone_number($form_data);
        // handle display name
        self::handle_update_display_name($form_data);
    }
    static function handle_update_phone_number($form_data)
    {
        $phone = isset($form_data['mw_phone_number']) && $form_data['mw_phone_number'] ? \mihanpanel\pro\app\tools::getValidatedPhoneNumber($form_data['mw_phone_number']) : '';
        if(!$phone || !options::user_can_change_phone() || users::can_use_this_phone($phone, self::$_uid))
        {
            return false;
        }
        users::set_user_phone(self::$_uid, $phone);
    }
    static function handle_update_display_name($form_data)
    {
        $display_name = isset($form_data['mw_display_name']) && $form_data['mw_display_name'] ? sanitize_text_field($form_data['mw_display_name']) : false;
        if(\mihanpanel\pro\app\options::get_smart_login_status())
        {
            users::change_display_name(get_current_user_id(), $display_name);
        }
    }
}