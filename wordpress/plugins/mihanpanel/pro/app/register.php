<?php

namespace mihanpanel\pro\app;

class register
{
    static function addExtraFieldsTypeValidationMethod($methods)
    {
        $methods['file_uploader'] = [__CLASS__, 'validateFeileFieldsMethod'];
        return $methods;
    }
    static function addExtraFieldsTypeStoreMethod($methods)
    {
        $methods['file_uploader'] = [__CLASS__, 'handleSaveFileFieldsData'];
        return $methods;
    }
    static function validateFeileFieldsMethod($field)
    {
        // check is required
        if(
            isset($field->required)
            && $field->required == 'yes'
            && (
                !isset($_FILES['mw_fields_files'])
                || !isset($_FILES['mw_fields_files']['name'][$field->slug])
                || !$_FILES['mw_fields_files']['name'][$field->slug])
                )
        {
            $metaData = isset($field->meta) && $field->meta ? unserialize($field->meta) : null;
            if(!isset($metaData['data']['just_in_profile']))
            {
                return new \WP_Error('mwpl_register_form_fields_validation', __('Missing files data', 'mihanpanel'));
            }
            return true;
        }
        // check for error
        if($error = $_FILES['mw_fields_files']['error'][$field->slug])
        {
            // check is empty and is required
            if($error == UPLOAD_ERR_NO_FILE && $field->required == 'no')
            {
                return true;
            }
            
            $error_txt = file_uploader::get_error_text($error);
            $error_txt = sprintf('%1$s: %2$s', $field->label, $error_txt);
            return new \WP_Error('mwpl_register_form_fields_validation', $error_txt);
        }
        // validate file type
        $valdiate_file_type = file_uploader::validate_file_type($field, $_FILES['mw_fields_files']['type'][$field->slug]);
        if(!$valdiate_file_type)
        {
            $msg = sprintf(esc_html__('%1$s: Invalid file type', 'mihanpanel'), $field->label);
            return new \WP_Error('mwpl_register_form_fields_validation', $msg);
        }
        // validate file size
        $validate_file_size = file_uploader::validate_file_size($field, $_FILES['mw_fields_files']['size'][$field->slug]);
        if(!$validate_file_size)
        {
            $msg = sprintf(esc_html__('%1$s: Invalid file size', 'mihanpanel'), $field->label);
            return new \WP_Error('mwpl_register_form_fields_validation', $msg);
        }
        return true;
    }
    static function handleSaveFileFieldsData($field, $userID)
    {
        $filesData = isset($_FILES['mw_fields_files']);
        $uploadRes = file_uploader::upload_file($filesData['name'][$field->slug], $filesData['tmp_name'][$field->slug], $field->slug, $userID);
        return $uploadRes ? $uploadRes : new \WP_Error('mwpl_register_form_fields_store', __('File was not uploaded', 'mihanpanel'));
    }

    public static function handle_user_role_validation_on_register_form_process($res){
        $roles_user_can_select = options::get_roles_user_can_select();
        $error = false;
        if (!$roles_user_can_select)
            return false;

        if (empty($_POST['user_role'])){
            $error = true;
            $res['msg'] = __('Select a user role.', 'mihanpanel');
        } elseif (!\mihanpanel\app\users::role_exists($_POST['user_role'])){
            $error = true;
            $res['msg'] = __('The user role is invalid.', 'mihanpanel');
        }

        if ($error === true){
            $res['code'] = 400;
            \mihanpanel\app\tools::send_json_response($res);
        }
    }

    public static function handle_user_register_data_after_disable_form_fields($user_data){
        $is_hide_email = options::is_hide_email_field_in_register_form();
        $is_hide_password = options::is_hide_password_field_in_register_form();
        $is_hide_username = options::is_hide_username_field_in_register_form();
        $mobile = isset($_POST['phone']) ? tools::getValidatedPhoneNumber($_POST['phone']) : false;

        if ($is_hide_email){
            $user_data['user_email'] = '';
        }
        if ($is_hide_password){
            $user_data['user_pass'] = '';
        }
        if ($is_hide_username){
            $user_data['user_login'] = 'user_' . $mobile;
        }

        return $user_data;
    }

    public static function handle_disabled_fields_validation_in_register_process($validation_value, $filter_key){
        $is_hide_email = options::is_hide_email_field_in_register_form();
        $is_hide_username = options::is_hide_username_field_in_register_form();
        $is_hide_password = options::is_hide_password_field_in_register_form();

        // disable field validation process
        if (
            ($is_hide_email && $filter_key === 'email-check') ||
            ($is_hide_username && $filter_key === 'username-exists') ||
            ($is_hide_password && $filter_key === 'password-check')
        ){
            return false;
        }

        return $validation_value;
    }

    public static function handle_user_mobile_after_register($user_id){
        $mobile = isset($_POST['phone']) ? tools::getValidatedPhoneNumber($_POST['phone']) : false;
        if($mobile){
            users::set_user_phone($user_id, $mobile);
            if(options::is_auto_confirm_phone_after_resgister())
            {
                users::set_has_valid_phone($user_id);
            }
        }
    }

    public static function handle_register_form_fields($fields){
        $hide_username = options::is_hide_username_field_in_register_form();
        $hide_email = options::is_hide_email_field_in_register_form();
        $hide_pass = options::is_hide_password_field_in_register_form();

        if(options::is_dynamic_password_mode()){
            $phone_field = [
                'label' => __('Phone Number', 'mihanpanel'),
                'icon' => \mihanpanel\app\assets::get_image_url('phone-icon', 'svg'),
                'type' => 'text',
            ];
            if (isset($fields['password'])){
                $password_data = $fields['password'];
                unset($fields['password']);
                $fields['phone'] = $phone_field;
                $fields['password'] = $password_data;
            } else {
                $fields['phone'] = $phone_field;
            }
        }

        if ($hide_username){
            unset($fields['username']);
        }
        if ($hide_email){
            unset($fields['email']);
        }
        if ($hide_pass){
            unset($fields['password']);
        }

        return $fields;
    }
}
