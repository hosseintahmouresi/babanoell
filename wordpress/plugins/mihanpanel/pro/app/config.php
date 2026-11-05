<?php
namespace mihanpanel\pro\app;

use mihanpanel\app\options as liteOptions;
use mihanpanel\app\session;
use mihanpanel\app\users;

class config
{
    static function filter_option_panel_title()
    {
        return esc_html__('MihanPanel Pro', 'mihanpanel');
    }
    static function main_font_url($font_url)
    {
        $font_name = options::get_font_name();
        $font_name = $font_name ? strtolower($font_name) : false;
        if(!$font_name)
        {
            return $font_url;
        }
        if ($font_name == 'iransans') {
            $font_url = assets::get_css_url('font-face-' . $font_name);
        }
        return $font_url;
    }
    static function before_update_db_version($old_version)
    {
        if($old_version < 3)
        {
            options::set_default_ban_roles();
        }
        if($old_version < 8)
        {
            // create notification tables
            notifications::createNotificationsTable();
            notifications::createNotificationMetaTable();
        }
        if($old_version < 9)
        {
            // update dashboard widgets options
            if(!get_option('mp_hide_register_day_count_widget'))
            {
                update_option('mp_is_show_register_day_count_widget', 'on');
            }else{
                delete_option('mp_hide_register_day_count_widget');
                update_option('mp_is_show_register_day_count_widget', '');
            }
            if(!get_option('mp_hide_comment_widget'))
            {
                update_option('mp_is_show_comment_count_widget', 'on');
            }else{
                delete_option('mp_hide_comment_widget');
                update_option('mp_is_show_comment_count_widget', '');
            }
            if(!get_option('mp_hide_woocommerce_widget'))
            {
                update_option('mp_is_show_woocommerce_widget', 'on');
            }else{
                delete_option('mp_hide_woocommerce_widget');
                update_option('mp_is_show_woocommerce_widget', '');
            }
            if(!get_option('mp_hide_edd_widget'))
            {
                update_option('mp_is_show_edd_widget', 'on');
            }else{
                delete_option('mp_hide_edd_widget');
                update_option('mp_is_show_edd_widget', '');
            }
            if(!get_option('mp_hide_awesome_support_widget'))
            {
                update_option('mp_is_show_awesome_support_widget', 'on');
            }else{
                delete_option('mp_hide_awesome_support_widget');
                update_option('mp_is_show_awesome_support_widget', '');
            }
        }
        if($old_version < 13)
        {
            // set deafult value for edit-profile wp default fields
            $fields = [
                'first_name',
                'last_name',
                'password',
                'bio',
            ];
            user_fields::setActiveWordpressDefaultFields($fields);
        }
    }
    public static function redirect_non_admin_after_login($redirectTo, $requested_redirect_to, $user)
    {
        if(users::is_admin_user($user->ID) || ($redirectTo && $redirectTo !== admin_url()))
        {
            return $redirectTo;
        }
        $ban_roles = options::get_roles_cant_use_admin_list();
        $user_role = $user->roles && is_array($user->roles) ? array_shift($user->roles) : false;
        $role_in_ban_list = !$user_role || (is_array($ban_roles) && in_array($user_role, $ban_roles)) ? true : false;
        if ($role_in_ban_list)
        {
            $redirect = options::get_non_login_user_redirect_slug();
            $redirect= $redirect ? home_url($redirect) : liteOptions::get_panel_url();
            if ( class_exists( 'Easy_Digital_Downloads' ) ){
			    $edd_checkout_uri = edd_get_checkout_uri();
      			if (wp_get_referer() != $edd_checkout_uri){
                  	$redirectTo = $redirect;
                }
            } else {
                     $redirectTo = $redirect;
            }
        }
        return $redirectTo;
    }
    public static function redirect_ban_roles_from_admin_panel()
    {
        if(!is_user_logged_in() || users::is_admin_user())
        {
            return false;
        }
        $ban_roles = options::get_roles_cant_use_admin_list();
        $current_user = get_user_by('id', get_current_user_id());
        $user_role = isset($current_user->roles) && is_array($current_user->roles) ? array_shift($current_user->roles) : false;
        $role_in_ban_list = !$user_role || (is_array($ban_roles) && in_array($user_role, $ban_roles)) ? true : false;
        if ($role_in_ban_list && !(wp_doing_ajax()))
        {
            wp_safe_redirect(liteOptions::get_panel_url());
            exit();
        }
    }
    static function filter_register_form_message()
    {
        if(options::get_register_head_text())
        {
            add_filter('gettext', function($translated_text, $text, $domain){
                if($domain == 'default' && $text == 'Register For This Site')
                {
                    $translated_text = options::get_register_head_text();
                }
                return $translated_text;
            }, 20, 3);   
        }
    }
    public static function add_roles_input_to_register_form()
    {
        $roles_user_can_select = options::get_roles_user_can_select();
        if (!$roles_user_can_select)
            return false;
        ?>
            <div class="mw-input-user-role">
                <select name="user_role" id="mw_user_role" class="mwpl-select">
                    <option value=""><?php echo __('Select User Role...', 'mihanpanel') ?></option>
                    <?php foreach ($roles_user_can_select as $role_key => $role_name): ?>
                        <option value="<?php echo esc_attr($role_key); ?>"><?php echo esc_html(translate_user_role($role_name)); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php
    }
    public static function handle_roles_input_error_in_register_form($errors)
    {
        $roles_user_can_select = options::get_roles_user_can_select();
        if ($roles_user_can_select && empty($_POST['mw_user_role']))
        {
            $errors->add('role_error', esc_html__("Role must not be empty", "mihanpanel"));
        }
        return $errors;
    }
    public static function store_roles_input_in_register_form($user_id)
    {
        $roles_user_can_register = options::get_roles_user_can_select();
        if (isset($_POST['user_role']) && $roles_user_can_register && $selected_role = sanitize_text_field($_POST['user_role']))
        {
            if (!in_array($selected_role, array_keys($roles_user_can_register)))
                return false;
            $user_data = get_user_by('id', $user_id);
            $user_data->set_role($selected_role);
        }
    }
    static function filter_upload_mime_types($types)
    {
        $types['svg'] = 'image/svg+xml';

        if (options::is_allowed_upload_json_file()){
            $types['json'] = 'application/json';
        }

        return $types;
    }

    /**
     * called from template_redirect action hook
     */
    static function redirect_wc_checkout_page_to_login_page()
    {
        if(!is_user_logged_in() && \mihanpanel\app\tools::is_woocommerce_active() && is_checkout() && options::get_is_redirect_wc_checkout_to_login_page())
        {
            $checkoutUrl= wc_get_checkout_url();
            wp_safe_redirect(\mihanpanel\app\options::getMihanPanelLoginUrl(['redirect_to' => $checkoutUrl]));
            exit;
        }
    }
    static function WpmlRegisterSingleStringText()
    {
        $loginFormFields = [];
        if(options::is_edit_username_field_label_in_login_form())
        {
            $loginFormFields['username'] = options::get_username_label_value_in_login_form();
        }
        if(options::is_edit_phone_field_label_in_login_form())
        {
            $loginFormFields['phone'] = options::get_phone_label_value_in_login_form();
        }
        if(options::is_edit_password_field_label_in_login_form())
        {
            $loginFormFields['password'] = options::get_password_label_value_in_login_form();
        }
        if(options::is_edit_dynamic_code_field_label_in_login_form())
        {
            $loginFormFields['dynamic_code'] = options::get_dynamic_code_label_value_in_login_form();
        }
        if(options::is_edit_login_button_text_in_login_form())
        {
            $loginFormFields['login_button'] = options::get_login_button_text_value_in_login_form();
        }
        if(options::is_edit_register_button_text_in_login_form())
        {
            $loginFormFields['register_button'] = options::get_register_button_text_value_in_login_form();
        }
        if($loginFormFields)
        {
            $langCode = apply_filters('wpml_current_language', null);
            foreach($loginFormFields as $fieldKey => $value)
            {
                $labelOptionSlug = 'login_form_' . $fieldKey . '_label';
                
                do_action('wpml_register_single_string', 'mihanpanel', $labelOptionSlug, $value, false, $langCode);
            }
        }
    }
    static function WpmlTranslateLabelsOptionsInLoginForm($labelValue, $fieldSlug)
    {
        if(!$labelValue)
        {
            return $labelValue;
        }
        $optionSlug = 'login_form_'.$fieldSlug.'_label';

        $langCode = apply_filters('wpml_current_language', null);
        $labelValue = apply_filters('wpml_translate_single_string', $labelValue, 'mihanpanel', $optionSlug, $langCode);
        return $labelValue;
    }
}