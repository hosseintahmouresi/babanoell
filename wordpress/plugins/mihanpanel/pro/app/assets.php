<?php
namespace mihanpanel\pro\app;

use mihanpanel\app\tools;

class assets
{
    static function get_handle_name($name)
    {
        return 'mwpl_pro_' . $name;
    }
    static function enqueue_script($name, $src, $deps = ['jquery'], $version=null, $in_footer=true)
    {
        $name = self::get_handle_name($name);
        $version = $version ? $version : tools::get_plugin_version();
        wp_enqueue_script($name, $src, $deps, $version, $in_footer);
    }
    static function enqueue_style($name, $src, $version=null, $deps=[])
    {
        $name = self::get_handle_name($name);
        $version = $version ? $version : tools::get_plugin_version();
        wp_enqueue_style($name, $src, $deps, $version);
    }
    static function register_script($name, $src, $deps = ['jquery'], $version=null, $in_footer=true)
    {
        $name = self::get_handle_name($name);
        $version = $version ? $version : tools::get_plugin_version();
        wp_register_script($name, $src, $deps, $version, $in_footer);
    }
    static function register_style($name, $src, $version=null, $deps=[])
    {
        $name = self::get_handle_name($name);
        $version = $version ? $version : tools::get_plugin_version();
        wp_register_style($name, $src, $deps, $version);
    }

    static function localize_script($name, $object_name, $data)
    {
        $name = self::get_handle_name($name);
        wp_localize_script($name, $object_name, $data);
    }
    private static function get_file_url($name, $type, $extension=false)
    {
        $extension = $extension ? $extension : $type;
        return MIHANPANEL_PRO_DIR_URL . 'assets/' . $type . '/' . $name . '.' . $extension;
    }
    static function get_js_url($name)
    {
        return self::get_file_url($name, 'js');
    }
    static function get_css_url($name)
    {
        return self::get_file_url($name, 'css');
    }
    static function get_img_url($name, $extension='png')
    {
        return self::get_file_url($name, 'img', $extension);
    }

    static function load_license_assets()
    {
        $license_style = self::get_css_url('license');
        $license_js = self::get_js_url('license');
        self::enqueue_style('license', $license_style);
        self::enqueue_script('license', $license_js);
        $data = [
            'au' => admin_url('admin-ajax.php'),
            'security' => wp_create_nonce('mwpl_license_process')
        ];
        self::localize_script('license', 'mwpl_data', $data);
    }

    static function load_panel_assets()
    {
        $panel_js = self::get_js_url('panel');
        $panel_css = self::get_css_url('panel');
        $panel_theme = options::get_panel_theme();
        if($panel_theme)
        {
            if(views::is_view_exists('assets.css.panel-theme-' . $panel_theme, 'css'))
            {
                $panel_theme_css = self::get_css_url('panel-theme-' . $panel_theme);
                do_action('mwpl_before_enqueue_panel_theme_assets', $panel_theme);
                self::enqueue_style('panel-theme', $panel_theme_css);
            }
        }
        self::enqueue_style('panel', $panel_css);
        self::enqueue_script('panel', $panel_js);
    }
    static function before_load_panel_theme_assets($panel_theme)
    {
        if($panel_theme == 'modern')
        {
            $sidebar_bg_color = options::get_panel_sidebar_bg_color();
            $css = "
                @media only screen and (min-width: 901px)
                {
                    .mihanpanelpanel .nocss.main-panel.mwtabb
                    {
                        background-color: {$sidebar_bg_color} !important;
                    }
                }
                .mihanpanelpanel .mp-nav-tabs li.active p,
                .mihanpanelpanel .mp-nav-tabs li.active i
                {
                    color: {$sidebar_bg_color} !important;
                }
            ";
            wp_add_inline_style('mwstyle-css', $css);
        }
    }
    static function load_admin_panel_assets()
    {
        $panel_js = self::get_js_url('admin-panel');
        $option_panel_css = self::get_css_url('option-panel');
        $live_view_js = self::get_js_url('admin-live-view');
        $live_view_css = self::get_css_url('admin-live-view');
        self::enqueue_style('option-panel', $option_panel_css);
        self::enqueue_script('admin-panel', $panel_js);
        self::enqueue_script('admin-live-view', $live_view_js, ['jquery', 'mwpl-admin-live-view']);
        self::enqueue_style('admin-live-view', $live_view_css);

        $data = [
            'texts' => [
                'sms_provider_settings_loading' => __('Loading SMS provider settings', 'mihanpanel'),
            ],
        ];
        self::localize_script('admin-panel', 'mwp_pro_data', $data);
    }
    static function load_admin_user_fields_menu()
    {
        $admin_user_fields = self::get_js_url('admin-user-fields');
        self::enqueue_script('admin-user-fields-menu', $admin_user_fields);
    }
    static function load_admin_tabs_menu_assets()
    {
        $admin_tab_js = self::get_js_url('admin-menu-tabs');
        self::enqueue_script('admin-tabs-menu', $admin_tab_js);
    }
    static function load_mobile_verification_assets()
    {
        $mb_verifiation_css = self::get_css_url('mobile_verification');
        $mb_verifiation_js = self::get_js_url('mobile_verification');
        self::enqueue_script('mobile_verification', $mb_verifiation_js);
        self::enqueue_style('mobile_verification', $mb_verifiation_css);
        $data = [
            'au'    =>  admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mwpl_mobile_verification'),
            'errors'    =>  [
                'use_valid_phone'   =>  __('Please use valid phone number.', 'mihanpanel')
            ]
        ];
        self::localize_script('mobile_verification', 'mwp_data', $data);
    }
    static function load_widget_assets()
    {
        $sidebar_bg_color = get_option('mp_sidebar_bg_color');
        $style = '';
        if($sidebar_bg_color != null )
        {
            $style = "
                .mihanpanel-profile-widget{
                    background-color: {$sidebar_bg_color} !important;
                }
            ";
        }
        $sidebar_color = get_option('mp_sidebar_color');
        if($sidebar_color != null)
        {
            $style .= "
                .mihanpanel-profile-widget .mp-nav-tabs-mpwrapper .mp-nav-tabs li a,
                .mihanpanel-profile-widget .mp-nav-tabs-mpwrapper .mp-nav-tabs li p
                {
                    color: {$sidebar_color} !important;
                }
            ";
        }
        $style .= '
        .mihanpanel-profile-widget .mp-nav-tabs-mpwrapper .mp-nav-tabs li a
        {
            display: flex;
        }
        .mihanpanel-profile-widget.mp_ltr_widget .mp-nav-tabs-mpwrapper .mp-nav-tabs li a p
        {
            padding-left: 10px;
        }
        .mihanpanel-profile-widget.mp_rtl_widget .mp-nav-tabs-mpwrapper .mp-nav-tabs li a p
        {
            padding-right: 10px;
        }
        .mihanpanel-profile-widget .mp-nav-tabs-mpwrapper .mp-nav-tabs li a i,
        .mihanpanel-profile-widget .mp-nav-tabs-mpwrapper .mp-nav-tabs li a img
        {
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: flex-start;
        }
        .mihanpanel-profile-widget .mp-nav-tabs-mpwrapper .mp-nav-tabs li a img
        {
            max-width: 30px;
            max-height: 30px;
        }
        ';
        wp_add_inline_style('mw-profile-widget', $style);
    }
    static function load_front_assets()
    {
        $sidebar_bg_color = get_option('mp_sidebar_bg_color');
        $style = '';
        if($sidebar_bg_color != null )
        {
            $style = "
                .mihanpanelpanel .mpsidebar{
                    background-color: {$sidebar_bg_color} !important;
                }
            ";
        }
        $sidebar_color = get_option('mp_sidebar_color');
        if($sidebar_color != null)
        {
            $style .= "
                .mihanpanelpanel .mpsidebar .nav p,
                .mihanpanelpanel .mpsidebar .nav i
                {
                    color: {$sidebar_color} !important;
                }
            ";
        }
        if(!$style)
        {
            return false;
        }
        wp_add_inline_style('mwstyle-css', $style);
    }
    static function load_login_header_additional_code()
    {
        $additional_code = options::get_login_header_additional_code();
        echo $additional_code;
    }
    static function loadNotificationAdminPanelAssets()
    {
        $viewMode = isset($_GET['type']) ? sanitize_text_field($_GET['type']) : false;
        if($viewMode == 'edit' || $viewMode == 'new')
        {
            // load js file
            self::enqueue_script('admin-notification-edit-fields', self::get_js_url('admin-notification-edit-fields'));
        }
    }
}