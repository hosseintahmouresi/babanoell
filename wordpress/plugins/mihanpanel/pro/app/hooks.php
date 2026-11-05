<?php
namespace mihanpanel\pro\app;
class hooks
{
    static function init()
    {
        add_action('mwpl_befrore_activation_redirect', [__CLASS__, 'activation_hook']);
        add_action('mwpl_deactiation_hook', [__CLASS__, 'deactivation_hook']);
        add_action('mwpl_load_front_assets', ['\mihanpanel\pro\app\assets', 'load_front_assets']);
        add_action('load_mihanpanel_profile_widget_assets', ['\mihanpanel\pro\app\assets', 'load_widget_assets']);
        add_action('mwpl_before_update_db_version', ['\mihanpanel\pro\app\config', 'before_update_db_version']);
        add_action('mwpl_load_panel_assets', ['\mihanpanel\pro\app\assets', 'load_panel_assets']);
        add_action('mwpl_load_admin_panel_assets', ['\mihanpanel\pro\app\assets', 'load_admin_panel_assets']);
        add_action('mwpl_load_admin_user_field_menu_assets', ['\mihanpanel\pro\app\assets', 'load_admin_user_fields_menu']);
        add_action('mwpl_load_admin_tabs_menu_assets', ['\mihanpanel\pro\app\assets', 'load_admin_tabs_menu_assets']);
        add_action('mwpl_load_admin_notifications_menu_assets', ['\mihanpanel\pro\app\assets', 'loadNotificationAdminPanelAssets']);
        add_filter('mihanpanel/tools/check_db_table_exists', ['\mihanpanel\pro\app\sundry', 'checkMihanPanelDbTablesForHealthNotice']);

        add_filter('mwpl_middlewares/panel_content', ['\mihanpanel\pro\app\handle_view', 'panel_content_middleware']);
        add_filter('mwpl_middleware/option_panel', ['\mihanpanel\pro\app\handle_view', 'option_panel_content_middlewares']);
        add_filter('upload_mimes', ['\mihanpanel\pro\app\config', 'filter_upload_mime_types']);

        // init shortcodes
        add_action('init', ['\mihanpanel\pro\app\shortcode', 'init']);

        add_filter('get_avatar_url', ['\mihanpanel\pro\app\avatar', 'filter_avatar_url'], 1, 3);
        
        // redirect baned roles from admin panel after login
        add_filter('login_redirect', ['\mihanpanel\pro\app\config', 'redirect_non_admin_after_login'], 2, 3);
        
        // redirect banned roles from admin panel
        add_action('admin_init', ['\mihanpanel\pro\app\config', 'redirect_ban_roles_from_admin_panel'], 1);

        // google recaptcha
        if (options::is_active_google_recaptcha()){
            add_action('plugins_loaded', ['\mihanpanel\pro\app\thirdparty\google_recaptcha_hooks', 'init']);
        }
        add_filter('mwpl_option_panel/render_method/google_recaptcha', ['\mihanpanel\pro\app\handle_view', 'handle_option_panel_google_recaptcha_field_render_method']);

        // forms custom captcha
        if (options::is_active_custom_captcha()){
            add_action('plugins_loaded', ['\mihanpanel\pro\app\forms_captcha', 'init']);
        }
        add_filter('mwpl_option_panel/render_method/custom_captcha', ['\mihanpanel\pro\app\handle_view', 'handle_option_panel_custom_captcha_field_render_method']);

        add_filter('mwpl_option_panel/render_method/login_page_background', ['\mihanpanel\pro\app\handle_view', 'handle_option_panel_login_page_background_fields_render_method']);
        add_filter('mwpl_option_panel/render_method/login_form_background', ['\mihanpanel\pro\app\handle_view', 'handle_option_panel_login_form_background_fields_render_method']);

        add_filter('mwpl_option_panel/render_method/files_allowed_upload', ['\mihanpanel\pro\app\handle_view', 'handle_option_panel_files_allowed_upload_render_method']);

        add_action('login_init', ['\mihanpanel\pro\app\config', 'filter_register_form_message']);
    
        add_action('register_form', ['\mihanpanel\pro\app\config', 'add_roles_input_to_register_form'], 11);
        add_action('mwpl_register_form_after_default_fields', ['\mihanpanel\pro\app\config', 'add_roles_input_to_register_form'], 11);
        add_action('mwpl_register_form_before_create_new_user', ['\mihanpanel\pro\app\register', 'handle_user_role_validation_on_register_form_process']);
        add_action('mwpl_register_form_after_create_new_user', ['\mihanpanel\pro\app\config', 'store_roles_input_in_register_form']);
        add_filter('registration_errors', ['\mihanpanel\pro\app\config', 'handle_roles_input_error_in_register_form']);
        add_action('user_register', ['\mihanpanel\pro\app\config', 'store_roles_input_in_register_form']);

        add_action('mihanpanel_after_validate_phone', ['\mihanpanel\pro\app\dynamic_code', 'remove_user_dynamic_code']);
        add_action('admin_init', ['\mihanpanel\pro\app\options', 'register_settings']);
    
        add_filter('mihanpanel_sms_providers', ['\mihanpanel\pro\app\sms_provider', 'init_default_provider']);
        add_action('mihanpanel_register_sms_provider_setting', ['\mihanpanel\pro\app\sms_provider', 'add_provider_settings']);
    
        add_filter('mwpl_option_panel_title', ['\mihanpanel\pro\app\config', 'filter_option_panel_title']);
        add_filter('mwpl_assets/main_font_name', ['\mihanpanel\pro\app\options', 'get_font_name']);
        add_filter('mwpl_assets/main_font_url', ['\mihanpanel\pro\app\config', 'main_font_url']);
        add_action('mwpl_after_save_profile_extra_fields', ['\mihanpanel\pro\app\sundry', 'handle_profile_files_fields_update']);
    
        add_filter('mwpl_register_form_extra_fields_validation_methods', ['\mihanpanel\pro\app\sundry', 'handle_register_form_extra_fields_error']);
        add_filter('mwpl_register_form_extra_field_save_handler_methods', ['\mihanpanel\pro\app\sundry', 'handle_register_form_extra_fields_save_methods']);

        add_action('mw_before_render_user_fields', ['\mihanpanel\pro\app\user_fields', 'load_fields_deps'], 10, 2);
        add_filter('mwpl_user_fields_render_permission', ['\mihanpanel\pro\app\user_fields', 'render_fields_permission'], 10, 3);
        add_action('delete_user', ['\mihanpanel\pro\app\users', 'remove_uploaded_field_files']);
    
        add_action('mwpl_option_panel/before_render_user_fields_list', ['\mihanpanel\pro\app\form\admin_user_fields', 'renderDefaultWordpressFields']);
        add_action('mwpl_option_panel/before_render_user_fields_list', ['\mihanpanel\pro\app\form\admin_user_fields', 'render_woocommerce_fields']);
        add_action('mwpl_option_panel/handle_user_fields_form_submit', ['\mihanpanel\pro\app\form\admin_user_fields', 'handle_form_submit']);
        
        add_filter('mwpl_option_panel/user_fields/new_mode/user_fields_meta', ['\mihanpanel\pro\app\form\admin_user_fields', 'filter_user_fields_meta'], 10, 2);
        add_action('mwpl_option_panel/user_fields/after_render_field', ['\mihanpanel\pro\app\form\admin_user_fields', 'render_meta_after_render_user_field']);
        add_action('mwpl_option_panel/user_fields/edit_form/before_render_submit_button', ['\mihanpanel\pro\app\form\admin_user_fields', 'render_uploader_icon_after_render_user_field']);
        add_action('mwpl_option_panel/user_fields/new_record_form/after_render_fields', ['\mihanpanel\pro\app\form\admin_user_fields', 'render_uploader_icon_after_render_new_user_field']);
        add_action('mwpl_option_panel/user_fields/edit_form/before_render_submit_button', ['\mihanpanel\pro\app\form\admin_user_fields', 'user_fields_extra_fields_edit_form']);
        add_action('mwpl_option_panel/user_fields/new_record_form/before_render_submit_button', ['\mihanpanel\pro\app\form\admin_user_fields', 'user_fields_extra_fields_new_record_form']);
        add_action('mwpl_option_panel/user_fields/new_record_form/after_render_fields', ['\mihanpanel\pro\app\form\admin_user_fields', 'render_meta_fields_in_user_fields_new_record_form']);
        add_action('mwpl_option_panel/tabs_menu/edit_section/before_render_action_buttons', ['\mihanpanel\pro\app\form\admin_tabs_fields', 'render_upload_button']);
        add_filter('mwpl_tab_item_icon_element', ['\mihanpanel\pro\app\presenter\tabs_menu', 'render_tab_item_icon'], 10, 2);
    
        add_action('mwpl_panel/profile/after_submit_form', ['\mihanpanel\pro\app\form\profile', 'handle_form_submit']);

        add_filter('mwpl_panel/profile/render_fields_method', ['\mihanpanel\pro\app\presenter\user_fields', 'filter_panel_user_field_render_methods'], 10, 2);

        add_filter('mwpl_panel_widgets_middlewares_register_day', ['\mihanpanel\pro\app\handle_view', 'panel_widget_middleware_register_day']);
        add_filter('mwpl_panel_widgets_middlewares_comment', ['\mihanpanel\pro\app\handle_view', 'panel_widget_middleware_comment']);

        add_filter('mwpl_panel_widgets_middlewares_edd', ['\mihanpanel\pro\app\handle_view', 'panel_widget_middleware_edd']);
        add_filter('mwpl_panel_widgets_middlewares_woocommerce', ['\mihanpanel\pro\app\handle_view', 'panel_widget_middleware_woocommerce']);
        add_filter('mwpl_panel_widgets_middlewares_awesome_support', ['\mihanpanel\pro\app\handle_view', 'panel_widget_middleware_awesome_support']);
        add_filter('mwpl_panel_widgets_middlewares_poshtvan', ['\mihanpanel\pro\app\handle_view', 'panel_widget_middleware_poshtvan']);
        
        add_filter('mwpl_panel/profile/form_enctype', ['\mihanpanel\pro\app\form\profile', 'handle_form_enctype']);
        add_action('mwpl_panel/profile/before_render_fields', ['\mihanpanel\pro\app\presenter\user_fields', 'render_avatar_field']);
        add_action('mwpl_panel/profile/after_password_field', ['\mihanpanel\pro\app\presenter\user_fields', 'after_password_fields']);
    
        add_action('mwpl_live_view_wrapper_content', ['\mihanpanel\pro\app\option_panel', 'general_live_view_wrapper_content']);
        add_filter('mwpl_option_panel/render_method/login_form_theme', ['\mihanpanel\pro\app\handle_view', 'handle_option_panel_login_theme_field_render_method']);
        add_filter( 'mwpl_option_panel/render_method/login_form_position', ['\mihanpanel\pro\app\handle_view', 'handle_option_panel_login_form_position_field_render_method']);
        add_filter( 'mwpl_option_panel/render_method/login_button_text_color', ['\mihanpanel\pro\app\handle_view', 'handle_option_panel_login_button_text_color']);
        add_filter( 'mwpl_option_panel/render_method/register_button_bg_color', ['\mihanpanel\pro\app\handle_view', 'handle_option_panel_register_button_bg_color']);
        add_filter( 'mwpl_option_panel/render_method/register_button_text_color', ['\mihanpanel\pro\app\handle_view', 'handle_option_panel_register_button_text_color']);
        add_filter( 'mwpl_option_panel/render_method/login_fields_bg_color', ['\mihanpanel\pro\app\handle_view', 'handle_option_panel_login_fields_bg_color']);
        add_filter( 'mwpl_option_panel/render_method/login_fields_label_color', ['\mihanpanel\pro\app\handle_view', 'handle_option_panel_login_fields_label_color']);
        add_filter( 'mwpl_option_panel/render_method/login_fields_text_color', ['\mihanpanel\pro\app\handle_view', 'handle_option_panel_login_fields_text_color']);
        add_filter( 'mwpl_option_panel/render_method/login_info_messagebox_bg_color', ['\mihanpanel\pro\app\handle_view', 'handle_option_panel_login_info_messagebox_bg_color']);
        add_filter( 'mwpl_option_panel/render_method/login_info_messagebox_text_color', ['\mihanpanel\pro\app\handle_view', 'handle_option_panel_login_info_messagebox_text_color']);
        add_filter( 'mwpl_option_panel/render_method/exclude_login_route_in_litespeed_cache', ['\mihanpanel\pro\app\handle_view', 'handle_option_panel_exclude_login_route_in_litespeed_cache']);

        add_filter('mwpl_option_panel/render_method/redirect_normal_user', ['\mihanpanel\pro\app\handle_view', 'handle_option_panel_redirect_normal_user_field_render_method']);
        add_filter('mwpl_option_panel/render_method/redirect_url_after_logout', ['\mihanpanel\pro\app\handle_view', 'handle_option_panel_redirect_url_after_logout_field_render_method']);
        add_filter('mwpl_option_panel/render_method/use_mihan_avatar', ['\mihanpanel\pro\app\handle_view', 'handle_option_panel_use_mihan_avatar_field_render_method']);
        add_filter('mwpl_option_panel/render_method/panel_theme', ['\mihanpanel\pro\app\handle_view', 'handle_option_panel_panel_theme_field_render_method']);
        add_filter('mwpl_option_panel/render_method/panel_font', ['\mihanpanel\pro\app\handle_view', 'handle_option_panel_panel_font_field_render_method']);
        add_filter('mwpl_option_panel/render_method/off_code_text', ['\mihanpanel\pro\app\handle_view', 'handle_option_panel_off_code_text_render_method']);
        add_filter('mwpl_option_panel/render_method/sidebar_bg_color', ['\mihanpanel\pro\app\handle_view', 'handle_option_panel_sidebar_bg_color_field_render_method']);
        add_filter('mwpl_option_panel/render_method/sidebar_color', ['\mihanpanel\pro\app\handle_view', 'handle_option_panel_sidebar_color_field_render_method']);
    
        add_filter('mwpl_option_panel/render_method/is_show_register_day_count_widget', ['\mihanpanel\pro\app\handle_view', 'handle_option_panel_is_show_register_day_count_widget_field_render_method']);
        add_filter('mihanpanel/dashboard/widgets/register_day_count_widget_text', ['\mihanpanel\pro\app\handle_view', 'filterRegisterDayCountWidgetText']);
        
        add_filter('mwpl_option_panel/render_method/is_show_comment_widget', ['\mihanpanel\pro\app\handle_view', 'handle_option_panel_is_show_comment_widget_field_render_method']);
        add_filter('mihanpanel/dashboard/widgets/comments_count_widget_text', ['\mihanpanel\pro\app\handle_view', 'filterCommentsCountWidgetText']);
        
        add_filter('mwpl_option_panel/render_method/is_show_woocommerce_widget', ['\mihanpanel\pro\app\handle_view', 'handle_option_panel_is_show_woocommerce_widget_field_render_method']);
        add_filter('mihanpanel/dashboard/widgets/woocommerce_orders_count_widget_text', ['\mihanpanel\pro\app\handle_view', 'filterWoocommerceOrdersCountWidgetText']);
        
        add_filter('mwpl_option_panel/render_method/is_show_edd_widget', ['\mihanpanel\pro\app\handle_view', 'handle_option_panel_is_show_edd_widget_field_render_method']);
        add_filter('mihanpanel/dashboard/widgets/edd_widget_text', ['\mihanpanel\pro\app\handle_view', 'filterEddWidgetText']);
        
        add_filter('mwpl_option_panel/render_method/is_show_awesome_support_widget', ['\mihanpanel\pro\app\handle_view', 'handle_option_panel_is_show_awesome_support_widget_field_render_method']);
        add_filter('mihanpanel/dashboard/widgets/awesome_support_widget_text', ['\mihanpanel\pro\app\handle_view', 'filterAwesomeSupportWidgetText']);
        
        add_filter('mwpl_option_panel/render_method/is_show_poshtvan_widget', ['\mihanpanel\pro\app\handle_view', 'handle_option_panel_is_show_poshtvan_widget_field_render_method']);
        add_filter('mihanpanel/dashboard/widgets/poshtvan_widget_text', ['\mihanpanel\pro\app\handle_view', 'filterPoshtvanWidgetText']);
        

        add_filter('mwpl_option_panel/render_method/is_blog_section_enable', ['\mihanpanel\pro\app\handle_view', 'handle_option_panel_is_blog_section_enable_field_render_method']);
        add_filter('mwpl_option_panel/render_method/blog_section_title', ['\mihanpanel\pro\app\handle_view', 'handle_option_panel_blog_section_title_field_render_method']);
        add_filter('mwpl_option_panel/render_method/blog_section_title_link', ['\mihanpanel\pro\app\handle_view', 'handle_option_panel_blog_section_title_link_field_render_method']);
        add_filter('mwpl_option_panel/render_method/blog_section_posts_category', ['\mihanpanel\pro\app\handle_view', 'handle_option_panel_blog_section_posts_category_field_render_method']);
        add_filter('mwpl_option_panel/render_method/blog_section_posts_count', ['\mihanpanel\pro\app\handle_view', 'handle_option_panel_blog_section_posts_count_field_render_method']);
        add_filter('mwpl_option_panel/render_method/is_wc_recent_products_enable', ['\mihanpanel\pro\app\handle_view', 'handle_option_panel_is_wc_recent_products_section_enable_field_render_method']);
        add_filter('mwpl_option_panel/render_method/wc_recent_products_section_title', ['\mihanpanel\pro\app\handle_view', 'handle_option_panel_wc_recent_products_section_title_field_render_method']);
        add_filter('mwpl_option_panel/render_method/wc_recent_products_section_link', ['\mihanpanel\pro\app\handle_view', 'handle_option_panel_wc_recent_products_section_link_field_render_method']);
        add_filter('mwpl_option_panel/render_method/wc_recent_products_section_items_category', ['\mihanpanel\pro\app\handle_view', 'handle_option_panel_wc_recent_products_section_items_category_field_render_method']);
        add_filter('mwpl_option_panel/render_method/wc_products_section_items_count', ['\mihanpanel\pro\app\handle_view', 'handle_option_panel_wc_recent_products_section_item_field_render_method']);

        add_filter('mwpl_option_panel/render_method/login_form_top_message', ['\mihanpanel\pro\app\handle_view', 'handle_option_panel_login_form_top_message_render_method']);
        
        add_filter('mwpl_option_panel/render_method/ban_roles', ['\mihanpanel\pro\app\handle_view', 'handle_option_panel_ban_roles_field_render_method']);
        add_filter('mwpl_option_panel/render_method/register_text', ['\mihanpanel\pro\app\handle_view', 'handle_option_panel_register_text_field_render_method']);
        add_filter('mwpl_option_panel/render_method/roles_user_can_select', ['\mihanpanel\pro\app\handle_view', 'handle_option_panel_roles_user_can_select_field_render_method']);
        
        add_filter('mwpl_option_panel/render_method/wpml_sms_just_in_persian', ['\mihanpanel\pro\app\handle_view', 'handle_option_panel_wpml_sms_just_in_persian_render_method']);
        add_filter('mwpl_option_panel/render_method/force_get_phone_number_in_smart_login_mode', ['\mihanpanel\pro\app\handle_view', 'handle_option_panel_force_get_phone_number_in_smart_login_mode_render_method']);
        add_filter('mwpl_option_panel/render_method/edit_login_fields_labels/username', ['\mihanpanel\pro\app\handle_view', 'handle_option_panel_edit_username_label_in_login_form_field_render_method']);
        add_filter('mwpl_option_panel/render_method/edit_login_fields_labels/username_value', ['\mihanpanel\pro\app\handle_view', 'handle_option_panel_username_label_value_in_login_form_field_render_method']);

        add_filter('mwpl_option_panel/render_method/edit_login_fields_labels/phone', ['\mihanpanel\pro\app\handle_view', 'handle_option_panel_edit_phone_label_in_login_form_field_render_method']);
        add_filter('mwpl_option_panel/render_method/edit_login_fields_labels/phone_value', ['\mihanpanel\pro\app\handle_view', 'handle_option_panel_phone_label_value_in_login_form_field_render_method']);

        add_filter('mwpl_option_panel/render_method/edit_login_fields_labels/password', ['\mihanpanel\pro\app\handle_view', 'handle_option_panel_edit_password_label_in_login_form_field_render_method']);
        add_filter('mwpl_option_panel/render_method/edit_login_fields_labels/password_value', ['\mihanpanel\pro\app\handle_view', 'handle_option_panel_password_label_value_in_login_form_field_render_method']);
        add_filter('mwpl_option_panel/render_method/edit_login_fields_labels/dynamic_code', ['\mihanpanel\pro\app\handle_view', 'handle_option_panel_edit_dynamic_code_label_in_login_form_field_render_method']);
        add_filter('mwpl_option_panel/render_method/edit_login_fields_labels/dynamic_code_value', ['\mihanpanel\pro\app\handle_view', 'handle_option_panel_dynamic_code_label_value_in_login_form_field_render_method']);

        add_filter('mwpl_option_panel/render_method/edit_login_fields_labels/login_button', ['\mihanpanel\pro\app\handle_view', 'handle_option_panel_edit_login_button_text_in_login_form_field_render_method']);
        add_filter('mwpl_option_panel/render_method/edit_login_fields_labels/login_button_text_value', ['\mihanpanel\pro\app\handle_view', 'handle_option_panel_login_button_text_value_in_login_form_field_render_method']);

        add_filter('mwpl_option_panel/render_method/edit_login_fields_labels/register_button', ['\mihanpanel\pro\app\handle_view', 'handle_option_panel_edit_register_button_text_in_login_form_field_render_method']);
        add_filter('mwpl_option_panel/render_method/edit_login_fields_labels/register_button_text_value', ['\mihanpanel\pro\app\handle_view', 'handle_option_panel_register_button_text_value_in_login_form_field_render_method']);
        
        
        
        add_filter('mwpl_option_panel/render_method/smart_login', ['\mihanpanel\pro\app\handle_view', 'handle_option_panel_smart_login_field_render_method']);
        add_filter('mwpl_option_panel/render_method/smart_login_can_using_email_for_register', ['\mihanpanel\pro\app\handle_view', 'handle_option_panel_smart_login_can_using_email_for_register_field_render_method']);
        add_filter('mwpl_option_panel/render_method/smart_login_can_using_phone_for_register', ['\mihanpanel\pro\app\handle_view', 'handle_option_panel_smart_login_can_using_phone_for_register_field_render_method']);

        add_filter('mwpl_option_panel/render_method/general_is_redirect_wc_checkout_page_to_login_page', ['\mihanpanel\pro\app\handle_view', 'handle_option_panel_is_redirect_wc_checkout_page_to_login_page_field_render_method']);
        add_filter('mwpl_option_panel/render_method/general_is_add_mihanpanel_to_wp_nav_menu', ['\mihanpanel\pro\app\handle_view', 'handle_option_panel_is_add_mihanpanel_to_wp_nav_menu_field_render_method']);
        add_filter('mwpl_option_panel/render_method/general_mihanpanel_wp_nav_menu', ['\mihanpanel\pro\app\handle_view', 'handle_option_panel_mihanpanel_wp_nav_menu_field_render_method']);
        add_action('update_option_mp_is_add_mihanpanel_item_to_wp_nav_menu', ['\mihanpanel\pro\app\options', 'whenIsAddMihanpnelToWpNavMenuOptionUpdated'], 10, 2);

        add_filter('mwpl_option_panel/render_method/smart_login_generate_random_username', ['\mihanpanel\pro\app\handle_view', 'handle_option_panel_smart_login_generate_random_username_field_render_method']);
        add_filter('mwpl_option_panel/render_method/login_with_google', ['\mihanpanel\pro\app\handle_view', 'handle_option_panel_login_with_google_field_render_method']);
        add_filter('mwpl_option_panel/render_method/password_type', ['\mihanpanel\pro\app\handle_view', 'handle_option_panel_password_type_field_render_method']);
        add_filter('mwpl_option_panel/render_method/hide_email_in_register_form', ['\mihanpanel\pro\app\handle_view', 'handle_option_panel_hide_email_field_in_register_form_render_method']);
        add_filter('mwpl_option_panel/render_method/hide_username_field_in_register_form', ['\mihanpanel\pro\app\handle_view', 'handle_option_panel_hide_username_field_in_register_form_render_method']);
        add_filter('mwpl_option_panel/render_method/hide_password_field_in_register_form', ['\mihanpanel\pro\app\handle_view', 'handle_option_panel_hide_password_field_in_register_form_render_method']);
        add_filter('mwpl_option_panel/render_method/user_phone_section', ['\mihanpanel\pro\app\handle_view', 'handle_option_panel_user_phone_section_render_method']);
        add_filter('mwpl_option_panel/render_method/sms_provider', ['\mihanpanel\pro\app\handle_view', 'handle_option_panel_sms_provider_field_render_method']);

        add_action('mwpl_option_panel/sms_provider_settings', ['\mihanpanel\pro\app\sms_provider', 'show_provider_settings']);
        add_action('mwpl_option_panel/sms_provider_settings/before_render_provider_settings', ['\mihanpanel\pro\app\sms_provider', 'show_provider_pre_settings']);

        add_filter('mwpl_option_panel/render_method/phone_validation_sms_content', ['\mihanpanel\pro\app\handle_view', 'handle_option_panel_phone_validation_sms_content_field_render_method']);
        add_filter('mwpl_option_panel/render_method/dynamic_code_sms_content', ['\mihanpanel\pro\app\handle_view', 'handle_option_panel_dynamic_code_sms_content_field_render_method']);

        add_filter('mwpl_option_panel/render_method/login_header_additional_code', ['\mihanpanel\pro\app\handle_view', 'handle_option_panel_login_header_additional_code_field_render_method']);
        add_filter('mwpl_option_panel/render_method/transfer_digits_phone_numbers', ['\mihanpanel\pro\app\handle_view', 'handle_option_panel_transfer_digits_phone_numbers_field_render_method']);
        add_filter('mwpl_option_panel/render_method/delete_ghost_users', ['\mihanpanel\pro\app\handle_view', 'handle_option_panel_delete_ghost_users_field_render_method']);
        
        add_filter('mwpl_profile/woocommerce_active_fields', ['\mihanpanel\pro\app\adapter\woo', 'filter_active_fields']);
        add_action('mwpl_before_enqueue_panel_theme_assets', ['\mihanpanel\pro\app\assets', 'before_load_panel_theme_assets']);
        add_filter('mihanpanel/panel/off_code_text', ['\mihanpanel\pro\app\options', 'filter_off_code_text']);

        // dynamic code email
        add_filter('mwpl_option_panel/render_method/dynamic_code_email_subject', ['\mihanpanel\pro\app\handle_view', 'handle_option_panel_dynamic_code_email_subject_render_method']);
        add_filter('mwpl_option_panel/render_method/dynamic_code_email_content', ['\mihanpanel\pro\app\handle_view', 'handle_option_panel_dynamic_code_email_content_render_method']);
        
        // admin tabs menu fields render handler
        add_filter('mwpl_admin_tabs_menu/render_method/field_id', ['\mihanpanel\pro\app\handle_view', 'handle_admin_tabs_fields_field_id_render_method']);
        add_filter('mwpl_admin_tabs_menu/render_method/is_show_by_role', ['\mihanpanel\pro\app\handle_view', 'handle_admin_tabs_fields_is_show_by_roles_render_method']);
        
        // admin tabs menu fields meta handler
        add_filter('mwpl_option_panel/panel_tabs/tabs_field_meta', ['\mihanpanel\pro\app\form\admin_tabs_fields', 'filter_tabs_fields_meta'], 10, 2);
        add_filter('mwpl_panel/sidebar/item_attrs', ['\mihanpanel\pro\app\presenter\tabs_menu', 'render_tab_menu_id'], 10, 2);
        add_filter('mwpl_panel/sidebar/permission', ['\mihanpanel\pro\app\presenter\tabs_menu', 'handle_render_menu_permission'], 10, 2);

        add_action('mwpl_option_panel/panel_tabs/new_record_form/before_submit_button', ['\mihanpanel\pro\app\form\admin_tabs_fields', 'render_meta_fields_in_tabs_fields_new_record_form']);

        // placeholder filter
        add_filter('mwpl_user_fields/field/placeholder', ['\mihanpanel\pro\app\presenter\user_fields', 'handle_placeholder_value'], 10, 3);

        // handle login head additional code
        add_action('login_head', ['\mihanpanel\pro\app\assets', 'load_login_header_additional_code']);
        add_action('login_enqueue_scripts', ['\mihanpanel\pro\app\login_system', 'load_login_custom_style']);
        add_action('login_enqueue_scripts', ['\mihanpanel\pro\app\login_system', 'handle_load_login_lottie_assets']);
        add_action('mp_login_cover_wrapper', ['\mihanpanel\pro\app\login_system', 'handle_details_in_login_cover_wrapper']);
        add_action('mp_login_footer', ['\mihanpanel\pro\app\login_system', 'handle_login_page_video_background']);

        add_action('mihanpanel/panel/dashboard/before_close_widgets_container', ['\mihanpanel\pro\app\sundry', 'handle_dashboard_news']);
        add_action('mihanpanel/panel/dashboard/before_close_widgets_container', ['\mihanpanel\pro\app\sundry', 'renderRecentProductsInPanel']);

        // handle tools menu submisstion
        add_action('after_submit_tools_menu_form', ['\mihanpanel\pro\app\admin_menu', 'handle_admin_tools_menu_submission']);

        // create notification table in database after submit table creation in tools page
        add_action('after_create_default_database_tables_tools_page', ['\mihanpanel\pro\app\notifications', 'createNotificationsTable']);
        add_action('after_create_default_database_tables_tools_page', ['\mihanpanel\pro\app\notifications', 'createNotificationMetaTable']);

        add_action('mwpl/panel/after_panel_widgets', ['\mihanpanel\pro\app\notifications', 'renderNotificationInPanel']);

        add_action('template_redirect', ['\mihanpanel\pro\app\config', 'redirect_wc_checkout_page_to_login_page']);



        // custom login & register form

        add_filter('mwpl_register_form_extra_field_validation_handler_method', ['\mihanpanel\pro\app\register', 'addExtraFieldsTypeValidationMethod']);
        add_filter('mwpl_register_form_extra_field_store_data_handler_method', ['\mihanpanel\pro\app\register', 'addExtraFieldsTypeStoreMethod']);

        if(options::is_dynamic_password_mode()){
            add_filter('mp_register_form_fields', ['\mihanpanel\pro\app\register', 'handle_register_form_fields']);

            add_filter('mwpl_register_user_data', ['\mihanpanel\pro\app\register', 'handle_user_register_data_after_disable_form_fields']);
            add_filter('mp_user_register_field_username-exists_validation', ['\mihanpanel\pro\app\register', 'handle_disabled_fields_validation_in_register_process'], 10, 2);
            add_filter('mp_user_register_field_email-check_validation', ['\mihanpanel\pro\app\register', 'handle_disabled_fields_validation_in_register_process'], 10, 2);
            add_filter('mp_user_register_field_password-check_validation', ['\mihanpanel\pro\app\register', 'handle_disabled_fields_validation_in_register_process'], 10, 2);

            add_action('mwpl_register_form_after_create_new_user', ['\mihanpanel\pro\app\register', 'handle_user_mobile_after_register']);
        }

        add_action('show_user_profile', ['\mihanpanel\pro\app\sundry', 'render_phone_field_in_admin_edit_profile']);
        add_action('edit_user_profile', ['\mihanpanel\pro\app\sundry', 'render_phone_field_in_admin_edit_profile']);
        add_action('personal_options_update', ['\mihanpanel\pro\app\sundry', 'handle_phone_input_in_admin_edit_profile']);
        add_action('edit_user_profile_update', ['\mihanpanel\pro\app\sundry', 'handle_phone_input_in_admin_edit_profile']);
        add_action('user_profile_update_errors', ['\mihanpanel\pro\app\sundry', 'handle_edit_phone_number_error'], 10, 3);

        add_action('plugins_loaded', ['\mihanpanel\pro\app\reset_password', 'init']);

        add_filter('mwpl_is_active_cover_form_section', ['\mihanpanel\pro\app\options', 'isActiveLoginFormCoverSection']);

        if(\mihanpanel\app\tools::is_elementor_active()){
            add_filter('mwpl_register_elementor_widgets', ['\mihanpanel\pro\app\elementor\Elementor', 'register_widgets']);
            add_action('wp_enqueue_scripts', ['\mihanpanel\pro\app\elementor\Elementor_Assets', 'enqueue_elementor_post_scripts'], 12);
			add_action('wp_footer', ['\mihanpanel\pro\app\elementor\Elementor_Assets', 'enqueue_elementor_post_scripts'], 12);
        }

        if(options::get_is_add_mihanpanel_to_wp_nav_menu())
        {
            add_filter('wp_nav_menu_objects', ['\mihanpanel\pro\app\sundry', 'addMihanpanelMenuToNavMenu'], 10, 2);
            // add_filter('wp_nav_menu_items', ['\mihanpanel\pro\app\sundry', 'addMihanpanelMenuToNavMenu'], 10, 2);
        }

        // edit-profile user wp-default fields
        add_action('mwpl_panel/profile/before_render_fields', ['\mihanpanel\pro\app\presenter\user_fields', 'renderUsernameField']);
        $editProfileWpDefaultFieldItems = user_fields::getEditProfileWordpressDefaultFields();
        $editProfileActiveWpDefaultFields = user_fields::getActiveWordpressDefaultFields();
        foreach($editProfileWpDefaultFieldItems as $fieldKey => $fieldLabel)
        {
            if(!in_array($fieldKey, $editProfileActiveWpDefaultFields))
            {
                add_filter('mihanpanel/edit_profile/show_'.$fieldKey.'_field_permission', '__return_false');
            }
        }
        
        // integrate some options string with wpml
        if(\mihanpanel\app\tools::is_wpml_active())
        {
            add_action('admin_init', ['\mihanpanel\pro\app\config', 'WpmlRegisterSingleStringText']);
            add_filter('mihanpanel/options/username_label_value_in_login_form', ['\mihanpanel\pro\app\config', 'WpmlTranslateLabelsOptionsInLoginForm'], 10, 2);
            add_filter('mihanpanel/options/phone_label_value_in_login_form', ['\mihanpanel\pro\app\config', 'WpmlTranslateLabelsOptionsInLoginForm'], 10, 2);
        }
    }
    static function activation_hook()
    {
        options::set_default_ban_roles();
    }
    static function deactivation_hook()
    {
        wp_clear_scheduled_hook('mwpl_check_license_event');
    }
}
