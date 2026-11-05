<?php

namespace mihanpanel\pro\app;

use mihanpanel\app\options;
use mihanpanel\app\tools;

class live_edit
{
    static protected $menu_name = 'mihanpanel_live_edit';
    protected $hook_suffix;
    protected $current_page;
    static private $_instance;
    static function getInstance()
    {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }
    function __construct()
    {
        $this->hooks();
    }
    function hooks()
    {
        add_action('mwpl_live_edit_before_header_actions', [$this, 'headerActions']);
        add_action('mwpl_live_edit_before_footer_actions', [$this, 'footerActions']);
    }
    function headerActions()
    {
        assets::enqueue_style('mw_fontawesome_css', \mihanpanel\app\assets::get_css_url('fa/css/all'));
        assets::enqueue_style('mw_fontawesome_icon_picker_css', \mihanpanel\app\assets::get_css_url('font-awesome-icon-picker'));
        // assets::enqueue_style('live-edit-panel', assets::get_css_url('live-edit-panel'));
        assets::enqueue_style('live-edit-panel', \mihanpanel\app\assets::get_css_url('live-edit-panel'));
        if(!is_rtl())
        {
            assets::enqueue_style('live-edit-panel-ltr', \mihanpanel\app\assets::get_css_url('live-edit-panel-ltr'));
        }
    }
    function footerActions()
    {
        assets::enqueue_script('font-awesome-icon-picker', \mihanpanel\app\assets::get_js_url('font-awesome-icon-picker'));
        assets::enqueue_script('live-edit-panel', assets::get_js_url('admin-live-edit-panel'), ['jquery', 'jquery-ui-sortable']);
        $data = [
            'au' => admin_url('admin-ajax.php'),
            'assets' => [
                'styles' => [
                    'iframe_style_file' => \mihanpanel\app\assets::get_css_url('live-edit-panel-iframe'),
                ],
            ],
            'texts' => [
                'labels' => [
                    'content' => esc_html__('Content', 'mihanpanel'),
                ],
                'new_item' => esc_html__('New Item', 'mihanpanel'),
                'delete_msg' => esc_html__('Are you sure you want to delete this item?', 'mihanpanel'),
                'item_name' => esc_html__('Item Name', 'mihanpanel'),
                'edit_content_btn_text' => esc_html__('Edit Content', 'mihanpanel'),
                'its_done' => esc_html__("It's Done", 'mihanpanel'),
                'has_error' => esc_html__('Has error in some process', 'mihanpanel'),
            ],
        ];
        if(!is_rtl())
        {
            $data['assets']['styles']['iframe_style_file_ltr'] = \mihanpanel\app\assets::get_css_url('live-edit-panel-iframe-ltr');
        }
        assets::localize_script('live-edit-panel', 'mwp_data', $data);
    }
}
