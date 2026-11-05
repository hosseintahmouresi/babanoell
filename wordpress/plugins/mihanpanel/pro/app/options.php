<?php
namespace mihanpanel\pro\app;
class options
{
    const DEFAULT_PASSWORD_TYPE = 'default';
    const DYNAMIC_PASSWORD_TYPE = 'dynamic';

    public static function set_default_ban_roles()
    {
        $roles = ['subscriber'];
        return update_option('mp_role_cant_use_admin_panel', $roles);
    }
    public static function get_roles_cant_use_admin_list()
    {
        $option = get_option('mp_role_cant_use_admin_panel');
        return $option;
    }
    static function is_blog_section_enable()
    {
        return get_option('mp_is_blog_section_enable');
    }
    static function get_panel_blog_section_title()
    {
        return get_option('mp_blog_section_title');
    }
    static function get_panel_blog_section_title_link()
    {
        return get_option('mp_blog_section_title_link');
    }
    static function get_panel_blog_section_posts_category()
    {
        return get_option('mp_blog_section_posts_category');
    }
    static function get_panel_blog_section_posts_count()
    {
        $value = intval(get_option('mp_blog_section_posts_count', 1));
        return $value ? $value : 1;
    }
    static function is_wc_recent_products_section_enable()
    {
        return get_option('mp_is_wc_recent_products_section_enable');
    }
    static function get_panel_wc_recent_products_section_title()
    {
        return get_option('mp_wc_recent_products_section_title');
    }
    static function get_panel_wc_recent_products_section_link()
    {
        return get_option('mp_wc_recent_products_section_link');
    }
    static function get_panel_wc_recent_products_section_items_categories()
    {
        return (array)get_option('mp_wc_recent_products_section_items_categories');
    }
    static function get_panel_wc_recent_products_section_items_count()
    {
        $value = intval(get_option('mp_wc_recent_products_section_items_count', 1));
        return $value ? $value : 1;
    }
    static function get_register_head_text()
    {
        return get_option('mp_register_text');
    }
    public static function get_roles_user_can_select()
    {
        return get_option('mp_roles_user_can_select');
    }
    static function get_password_types()
    {
        return [
            self::DEFAULT_PASSWORD_TYPE => esc_html__('Wordpress Password Mode', 'mihanpanel'),
            self::DYNAMIC_PASSWORD_TYPE => esc_html__('Login with SMS', 'mihanpanel'),
        ];
    }
    public static function get_non_login_user_redirect_slug()
    {
        $option = get_option('mp_redirect_normal_users_to');
        return $option ? $option : false;
    }
    static function getNormalRolesAfterLoginRedirectUrl()
    {
        $option = get_option('mp_redirect_normal_users_to');
        return $option && filter_var($option, FILTER_VALIDATE_URL) ? esc_url($option) : false;
    }
    static function getAfterLogoutRedirectUrl()
    {
        $option = get_option('mp_after_logout_redirect_url');
        return $option && filter_var($option, FILTER_VALIDATE_URL) ? esc_url($option) : false;
    }
    static function get_login_form_top_message_text()
    {
        return get_option('mp_login_form_top_message_text');
    }
    static function is_mihan_avatar_enable()
    {
        $option = get_option('mp_use_mihan_avatar');
        return $option;    
    }
    static function is_show_register_day_count_widget()
    {
        return get_option('mp_is_show_register_day_count_widget', 'on');
    }
    static function getRegisterDayCountWidgetText()
    {
        $value = get_option('mp_register_day_count_widget_text');
        return $value ? $value : __('You are our user', 'mihanpanel');
    }
    
    public static function is_show_comment_widget()
    {
        return get_option('mp_is_show_comment_count_widget', 'on');
    }
    static function getCommentsCountWidgetText()
    {
        $value = get_option('mp_comments_count_widget_text');
        return $value ? $value : __('Your Comments', 'mihanpanel');
    }
    public static function is_show_woocommerce_widget()
    {
        return get_option('mp_is_show_woocommerce_widget', 'on');
    }
    static function getWoocommerceOrdersWidgetText()
    {
        $value = get_option('mp_woocommerce_orders_widget_text');
        return $value ? $value : __('Your purchase count', 'mihanpanel');
    }
    public static function is_show_edd_widget()
    {
        return get_option('mp_is_show_edd_widget', 'on');
    }
    static function getEddWidgetText()
    {
        $value = get_option('mp_edd_widget_text');
        return $value ? $value : __('Purchased files', 'mihanpanel');
    }
    public static function is_show_awesome_support_widget()
    {
        return get_option('mp_is_show_awesome_support_widget', 'on');
    }
    static function getAwesomeSupportWidgetText()
    {
        $value = get_option('mp_awesome_support_widget_text');
        return $value ? $value : __('Your tickets', 'mihanpanel');
    }
    static function is_show_poshtvan_widget()
    {
        return get_option('mp_is_show_mihanticket_widget', 'on');
    }
    static function getPoshtvanWidgetText()
    {
        $value = get_option('mp_poshtvan_widget_text');
        return $value ? $value : __('Your tickets', 'mihanpanel');
    }
    static function get_wpml_sms_just_in_persian_status()
    {
        return get_option('mp_wpml_sms_just_in_persian');
    }
    static function get_is_force_obtain_phone_number_smart_login()
    {
        return get_option('mp_force_get_phone_number_in_panel');
    }
    static function is_edit_username_field_label_in_login_form()
    {
        return (bool)get_option('mp_edit_username_field_label_in_login_form');
    }
    static function get_username_label_value_in_login_form()
    {
        $usernameLabelValue = get_option('mp_username_field_label_value_in_login_form');
        $optionValue = $usernameLabelValue ? $usernameLabelValue : __('Username', 'mihanpanel');
        return apply_filters('mihanpanel/options/username_label_value_in_login_form', $optionValue, 'username');
    }
    static function is_edit_phone_field_label_in_login_form()
    {
        return (bool)get_option('mp_edit_phone_field_label_in_login_form');
    }
    static function get_phone_label_value_in_login_form()
    {
        $phoneLabelValue = get_option('mp_phone_field_label_value_in_login_form');
        $optionValue = $phoneLabelValue ? $phoneLabelValue : __('Phone', 'mihanpanel');
        return apply_filters('mihanpanel/options/phone_label_value_in_login_form', $optionValue, 'phone');
    }
    static function is_edit_password_field_label_in_login_form()
    {
        return (bool)get_option('mp_edit_password_field_label_in_login_form');
    }
    static function get_password_label_value_in_login_form()
    {
        $passwordLabelValue = get_option('mp_password_field_label_value_in_login_form');
        $optionValue = $passwordLabelValue ? $passwordLabelValue : __('Password', 'mihanpanel');
        return apply_filters('mihanpanel/options/username_label_value_in_login_form', $optionValue, 'password');
    }
    static function is_edit_dynamic_code_field_label_in_login_form()
    {
        return (bool)get_option('mp_edit_dynamic_code_field_label_in_login_form');
    }
    static function get_dynamic_code_label_value_in_login_form()
    {
        $dynamicCodeLabelValue = get_option('mp_dynamic_code_field_label_value_in_login_form');
        $optionValue = $dynamicCodeLabelValue ? $dynamicCodeLabelValue : __('Dynamic code', 'mihanpanel');
        return apply_filters('mihanpanel/options/username_label_value_in_login_form', $optionValue, 'dynamic_code');
    }
    static function is_edit_login_button_text_in_login_form()
    {
        return (bool)get_option('mp_edit_login_button_text_in_login_form');
    }
    static function get_login_button_text_value_in_login_form()
    {
        $loginButtonTextValue = get_option('mp_login_button_text_value_in_login_form');
        $optionValue = $loginButtonTextValue ? $loginButtonTextValue : __('Login', 'mihanpanel');
        return apply_filters('mihanpanel/options/username_label_value_in_login_form', $optionValue, 'login_button');
    }
    static function is_edit_register_button_text_in_login_form()
    {
        return (bool)get_option('mp_edit_register_button_text_in_login_form');
    }
    static function get_register_button_text_value_in_login_form()
    {
        $RegisterButtonTextValue = get_option('mp_register_button_text_value_in_login_form');
        $optionValue = $RegisterButtonTextValue ? $RegisterButtonTextValue : __('Register', 'mihanpanel');
        return apply_filters('mihanpanel/options/username_label_value_in_login_form', $optionValue, 'register_button');
    }
    
    
    static function get_smart_login_status()
    {
        return (bool)get_option('mp_smart_login');
    }
    static function canUsingEmailInSmartLoginForRegister()
    {
        return (bool)get_option('mp_smart_login_can_using_email_for_register', true);
    }
    static function canUsingPhoneInSmartLoginForRegister()
    {
        return (bool)get_option('mp_smart_login_can_using_phone_for_register', true);
    }
    static function get_smart_login_generate_random_username_status()
    {
        return (bool)get_option('mp_smart_login_generate_random_username');
    }
    static function get_is_redirect_wc_checkout_to_login_page()
    {
        return (bool)get_option('mp_wc_redirect_from_checkout_is_not_logged_in');
    }
    static function get_is_add_mihanpanel_to_wp_nav_menu()
    {
        return (bool)get_option('mp_is_add_mihanpanel_item_to_wp_nav_menu');
    }
    static function get_mihanpanel_wp_nav_menu_location()
    {
        return get_option('mp_mihanpanel_nav_menus_item_menu');
    }
    static function whenIsAddMihanpnelToWpNavMenuOptionUpdated($oldValue, $newValue)
    {
        if(is_null($newValue))
        {
            delete_option('mp_mihanpanel_nav_menus_item_menu');
        }
    }
    static function isLoginWithGoogleActive()
    {
        return (bool)get_option('is_login_with_google_active');
    }
    static function getLoginWithGoogleBtnText()
    {
        $option = get_option('login_with_google_btn_text');
        return $option ? $option : __('Login with Google', 'mihanpanel');
    }
    static function getLoginWithGoogleClientID()
    {
        return get_option('login_with_google_client_id');
    }
    static function getLoginWithGoogleClientSecret()
    {
        return get_option('login_with_google_client_secret');
    }
    static function get_current_password_type()
    {
        $type = get_option('mp_password_type');
        $type_keys = array_keys(self::get_password_types());
        return $type && in_array($type, $type_keys) ? $type : array_shift($type_keys);
    }
    static function is_wp_default_password_mode()
    {
        $type = self::get_current_password_type();
        return $type == self::DEFAULT_PASSWORD_TYPE ? true : false;
    }
    static function is_dynamic_password_mode()
    {
        $type = self::get_current_password_type();
        return $type == self::DYNAMIC_PASSWORD_TYPE ? true : false;
    }
    static function is_hide_email_field_in_register_form()
    {
        return get_option('mp_hide_email_field_in_register_form');
    }
    static function is_hide_username_field_in_register_form()
    {
        return get_option('mp_hide_username_field_in_register_form');
    }
    static function is_hide_password_field_in_register_form()
    {
        return get_option('mp_hide_password_field_in_register_form');
    }
    static function is_auto_confirm_phone_after_resgister()
    {
        return get_option('mp_auto_confirm_phone_after_register');
    }
    static function user_can_change_phone()
    {
        return get_option('mp_can_change_phone');
    }
    static function get_active_sms_provider()
    {
        return get_option('mp_sms_provider');
    }
    static function get_phone_validation_sms_content()
    {
        return get_option('mp_phone_validation_sms_content');
    }
    static function get_dynamic_code_sms_content()
    {
        return get_option('mp_dynamic_code_sms_content');
    }
    static function get_panel_theme()
    {
        return get_option('mwpl_panel_theme');
    }
    static function get_font_name()
    {
        return get_option('mp_panelfont', 'IRANYekan');
    }
    static function get_raw_off_code_text()
    {
        $default = sprintf(esc_html__('%1$s off code for your next purchase', 'mihanpanel'), '[[number]]');
        return get_option('mp_off_code_text', $default);
    }
    static function filter_off_code_text($value)
    {
        $offer_code_raw_value = self::get_raw_off_code_text();
        if(!$offer_code_raw_value)
        {
            return $value;
        }
        $percentage = \mihanpanel\app\options::get_offer_code_percentage_value();
        $value = str_replace('[[number]]', $percentage, $offer_code_raw_value);
        return $value;
    }
    static function get_panel_sidebar_bg_color()
    {
        $color = get_option('mp_sidebar_bg_color');
        return $color ? $color : '#2b2f3e';
    }
    static function get_panel_sidebar_color()
    {
        $color = get_option('mp_sidebar_color');
        return $color ? $color : '#fff';
    }
    static function get_login_form_theme()
    {
        $value = get_option('login_form_theme', 'light');
        return $value ? $value : 'light';
    }
    static function get_login_form_position(){
        $value = get_option('login_form_position', 'center');
        return $value ? $value : 'center';
    }
    static function get_login_button_text_color()
    {
        $color = get_option('login_button_text_color', '#ffffff');
        return $color ? $color : '#ffffff';
    }
    static function get_register_button_bg_color()
    {
        $color = get_option('register_button_bg_color', '#ffffff');
        return $color ? $color : '#ffffff';
    }
    static function get_register_button_text_color()
    {
        $color = get_option('register_button_text_color', '#000');
        return $color ? $color : '#000';
    }
    static function get_login_fields_bg_color()
    {
        return get_option('login_fields_bg_color');
    }
    static function get_login_fields_label_color()
    {
        return get_option('login_fields_label_color');
    }
    static function get_login_fields_text_color()
    {
        return get_option('login_fields_text_color');
    }
    static function get_login_info_messagebox_text_color()
    {
        $value = get_option('login_info_messagebox_text_color', '#ffffff');
        return $value ? $value : '#ffffff';
    }
    static function get_login_info_messagebox_bg_color()
    {
        $value = get_option('login_info_messagebox_bg_color', '#246cfc');
        return $value ? $value : '#246cfc';
    }

    static function get_login_header_additional_code()
    {
        return get_option('mp_login_header_additional_code');
    }
    static function set_login_header_additional_code($value)
    {
        return $value ? update_option('mp_login_header_additional_code', $value) : delete_option('mp_login_header_additional_code');
    }

    static function get_raw_dynamic_code_email_subject()
    {
        return get_option('dynamic_code_email_subject');
    }
    static function get_raw_dynamic_code_email_content()
    {
        return get_option('dynamic_code_email_content');
    }
    static function get_dynamic_code_email_subject()
    {
        $value = self::get_raw_dynamic_code_email_subject();
        if(!$value)
        {
            $value = __('Dynamic Code', 'mihanpanel');
            $blogname = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );
            if($blogname)
            {
                $value = sprintf('[%s] %s', $blogname, $value);
            }
        }
        return $value;
    }
    static function get_dynamic_code_email_content()
    {
        $value = self::get_raw_dynamic_code_email_content();
        if(!$value)
        {
            $value = sprintf('%s: [[code]]', __('Your dynamic code is', 'mihanpanel'));
        }
        return $value;
    }

    public static function is_active_google_recaptcha(){
        return get_option('is_active_google_recaptcha') == 1;
    }

    public static function get_google_recaptcha_type(){
        return get_option('recaptcha_type');
    }

    public static function get_google_recaptcha_site_key(){
        return get_option('recaptcha_site_key');
    }

    public static function get_google_recaptcha_secret_key(){
        return get_option('recaptcha_secret_key');
    }

    public static function is_active_google_recaptcha_in_login(){
        return get_option('recaptcha_active_in_login') == 1;
    }

    public static function is_active_google_recaptcha_in_register(){
        return get_option('recaptcha_active_in_register') == 1;
    }

    public static function is_active_google_recaptcha_in_reset_password(){
        return get_option('recaptcha_active_in_reset_password') == 1;
    }

    public static function is_active_custom_captcha(){
        return get_option('is_active_custom_captcha') == 1;
    }

    public static function is_custom_captcha_case_sensitive(){
        return get_option('custom_captcha_case_sensitive') == 1;
    }

    public static function get_custom_captcha_type(){
        $type = get_option('custom_captcha_type');
        return $type ? $type : 'number';
    }

    public static function is_active_custom_captcha_in_login(){
        return get_option('custom_captcha_active_in_login') == 1;
    }

    public static function is_active_custom_captcha_in_register(){
        return get_option('custom_captcha_active_in_register') == 1;
    }

    public static function is_active_custom_captcha_in_reset_password(){
        return get_option('custom_captcha_active_in_reset_password') == 1;
    }

    public static function get_login_page_background_option($suffix = 'image', $default = false){
        return \mihanpanel\app\options::get_option('mp_page_bg_' . $suffix, $default);
    }

    public static function get_login_form_cover_background_option($suffix = 'image', $default = false){
        return \mihanpanel\app\options::get_option('mp_bg_' . $suffix, $default);
    }

    public static function get_login_form_box_background_option($suffix = 'image', $default = false){
        return \mihanpanel\app\options::get_option('mp_form_bg_' . $suffix, $default);
    }

    public static function get_login_form_lottie_file(){
        return \mihanpanel\app\options::get_option('mp_form_lottie_file');
    }

    public static function is_allowed_upload_json_file(){
        return \mihanpanel\app\options::get_option('mp_allowed_json_file');
    }
    static function isActiveLoginFormCoverSection()
    {
        return (bool)get_option('mp_is_active_login_form_cover_section', 1);
    }
    
    static function register_settings()
    {
        register_setting('mihanpanelsettings', 'login_form_theme');
        register_setting('mihanpanelsettings', 'login_form_position');
        register_setting('mihanpanelsettings', 'login_button_text_color');
        register_setting('mihanpanelsettings', 'register_button_bg_color');
        register_setting('mihanpanelsettings', 'register_button_text_color');
        register_setting('mihanpanelsettings', 'login_fields_bg_color');
        register_setting('mihanpanelsettings', 'login_fields_label_color');
        register_setting('mihanpanelsettings', 'login_fields_text_color');
        register_setting('mihanpanelsettings', 'login_info_messagebox_text_color');
        register_setting('mihanpanelsettings', 'login_info_messagebox_bg_color');
        register_setting('mihanpanelsettings', 'mp_redirect_normal_users_to');
        register_setting('mihanpanelsettings', 'mp_after_logout_redirect_url');
        register_setting('mihanpanelsettings', 'mp_login_form_top_message_text');

        $background_controls_meta_prefix = ['mp_page_bg', 'mp_bg', 'mp_form_bg'];

        $background_controls_suffix = ['_type', '_color', '_color2', '_direction', '_image', '_video', '_repeat', '_size', '_position'];

        foreach ($background_controls_meta_prefix as $_bg_prefix){
            foreach ($background_controls_suffix as $bg_suffix){
                register_setting('mihanpanelsettings', $_bg_prefix . $bg_suffix);
            }
        }
        register_setting('mihanpanelsettings', 'mp_form_lottie_file');

        register_setting('mihanpanelsettings', 'mp_is_active_login_form_cover_section');
        register_setting('mihanpanelsettings', 'mp_wc_redirect_from_checkout_is_not_logged_in');
        register_setting('mihanpanelsettings', 'mp_is_add_mihanpanel_item_to_wp_nav_menu');
        register_setting('mihanpanelsettings', 'mp_mihanpanel_nav_menus_item_menu');
        register_setting('mihanpanelsettings-panel', 'mp_off_code_text');
        register_setting('mihanpanelsettings-panel', 'mp_sidebar_bg_color');
        register_setting('mihanpanelsettings-panel', 'mp_sidebar_color');
        register_setting('mihanpanelsettings-panel', 'mp_is_show_register_day_count_widget');
        register_setting('mihanpanelsettings-panel', 'mp_register_day_count_widget_text');
        register_setting('mihanpanelsettings-panel', 'mp_is_show_comment_count_widget');
        register_setting('mihanpanelsettings-panel', 'mp_comments_count_widget_text');
        register_setting('mihanpanelsettings-panel', 'mp_is_show_woocommerce_widget');
        register_setting('mihanpanelsettings-panel', 'mp_woocommerce_orders_widget_text');
        register_setting('mihanpanelsettings-panel', 'mp_is_show_edd_widget');
        register_setting('mihanpanelsettings-panel', 'mp_edd_widget_text');
        register_setting('mihanpanelsettings-panel', 'mp_is_show_awesome_support_widget');
        register_setting('mihanpanelsettings-panel', 'mp_awesome_support_widget_text');
        register_setting('mihanpanelsettings-panel', 'mp_is_show_mihanticket_widget');
        register_setting('mihanpanelsettings-panel', 'mp_poshtvan_widget_text');
        register_setting('mihanpanelsettings-panel', 'mwpl_panel_theme');
        register_setting('mihanpanelsettings-panel', 'mp_panelfont');
        register_setting('mihanpanelsettings-panel', 'mp_role_cant_use_admin_panel');
        register_setting('mihanpanelsettings-panel', 'mp_is_blog_section_enable');
        register_setting('mihanpanelsettings-panel', 'mp_blog_section_title');
        register_setting('mihanpanelsettings-panel', 'mp_blog_section_title_link');
        register_setting('mihanpanelsettings-panel', 'mp_blog_section_posts_category');
        register_setting('mihanpanelsettings-panel', 'mp_blog_section_posts_count');

        register_setting('mihanpanelsettings-panel', 'mp_wc_recent_products_section_title');
        register_setting('mihanpanelsettings-panel', 'mp_is_wc_recent_products_section_enable');
        register_setting('mihanpanelsettings-panel', 'mp_wc_recent_products_section_link');
        register_setting('mihanpanelsettings-panel', 'mp_wc_recent_products_section_items_categories');
        register_setting('mihanpanelsettings-panel', 'mp_wc_recent_products_section_items_count');

        register_setting('mihanpanelsettings-register', 'mp_register_text');
        register_setting('mihanpanelsettings-register', 'mp_roles_user_can_select');
        $dynamic_password_group = 'mihanpanelsettings-professional_login_options';
        register_setting($dynamic_password_group, 'mp_wpml_sms_just_in_persian');
        register_setting($dynamic_password_group, 'mp_force_get_phone_number_in_panel');
        register_setting($dynamic_password_group, 'mp_smart_login');
        register_setting($dynamic_password_group, 'mp_smart_login_can_using_email_for_register');
        register_setting($dynamic_password_group, 'mp_smart_login_can_using_phone_for_register');
        register_setting($dynamic_password_group, 'mp_smart_login_generate_random_username');
        register_setting($dynamic_password_group, 'is_login_with_google_active');
        register_setting($dynamic_password_group, 'login_with_google_btn_text');
        register_setting($dynamic_password_group, 'login_with_google_client_id');
        register_setting($dynamic_password_group, 'login_with_google_client_secret');
        register_setting($dynamic_password_group, 'mp_password_type');
        register_setting($dynamic_password_group, 'mp_hide_email_field_in_register_form');
        register_setting($dynamic_password_group, 'mp_hide_username_field_in_register_form');
        register_setting($dynamic_password_group, 'mp_hide_password_field_in_register_form');
        register_setting($dynamic_password_group, 'mp_auto_confirm_phone_after_register');
        register_setting($dynamic_password_group, 'mp_can_change_phone');
        register_setting($dynamic_password_group, 'mp_sms_provider');
        do_action('mihanpanel_register_sms_provider_setting', $dynamic_password_group);
        register_setting($dynamic_password_group, 'mp_phone_validation_sms_content');
        register_setting($dynamic_password_group, 'mp_dynamic_code_sms_content');

        register_setting($dynamic_password_group, 'mp_edit_username_field_label_in_login_form');
        register_setting($dynamic_password_group, 'mp_username_field_label_value_in_login_form');

        register_setting($dynamic_password_group, 'mp_edit_phone_field_label_in_login_form');
        register_setting($dynamic_password_group, 'mp_phone_field_label_value_in_login_form');
        
        register_setting($dynamic_password_group, 'mp_edit_password_field_label_in_login_form');
        register_setting($dynamic_password_group, 'mp_password_field_label_value_in_login_form');

        register_setting($dynamic_password_group, 'mp_edit_dynamic_code_field_label_in_login_form');
        register_setting($dynamic_password_group, 'mp_dynamic_code_field_label_value_in_login_form');

        register_setting($dynamic_password_group, 'mp_edit_login_button_text_in_login_form');
        register_setting($dynamic_password_group, 'mp_login_button_text_value_in_login_form');

        register_setting($dynamic_password_group, 'mp_edit_register_button_text_in_login_form');
        register_setting($dynamic_password_group, 'mp_register_button_text_value_in_login_form');

        register_setting('mihanpanelsettings-email', 'dynamic_code_email_subject');
        register_setting('mihanpanelsettings-email', 'dynamic_code_email_content');

        $security_group_key = 'mihanpanelsettings-security';

        register_setting($security_group_key, 'is_active_google_recaptcha');
        register_setting($security_group_key, 'recaptcha_type');
        register_setting($security_group_key, 'recaptcha_site_key');
        register_setting($security_group_key, 'recaptcha_secret_key');
        register_setting($security_group_key, 'recaptcha_active_in_login');
        register_setting($security_group_key, 'recaptcha_active_in_register');
        register_setting($security_group_key, 'recaptcha_active_in_reset_password');

        register_setting($security_group_key, 'is_active_custom_captcha');
        register_setting($security_group_key, 'custom_captcha_case_sensitive');
        register_setting($security_group_key, 'custom_captcha_type');
        register_setting($security_group_key, 'custom_captcha_active_in_login');
        register_setting($security_group_key, 'custom_captcha_active_in_register');
        register_setting($security_group_key, 'custom_captcha_active_in_reset_password');

        register_setting($security_group_key, 'mp_allowed_json_file');
    }
}