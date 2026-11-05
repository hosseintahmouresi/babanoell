<?php
/*
Plugin Name: پرداخت دلخواه
Plugin URI: https://wp-master.ir/pardakht-delkhah/
Author: استاد وردپرس
Author URI: https://wp-master.ir
Version: 3.0.0
Description: با این پلاگین میتونید سیستم پرداخت خودتون رو راه اندازی کنید.
Text Domain: cupri
Domain Path: /languages
 */
defined('ABSPATH') or die('No script kiddies please!');

/**
 * activate action
 * like redirect to admin settings and ...
 */
register_activation_hook(__FILE__, ['cupri', 'activation_hook']);


class cupri
{
    private static $instance = null;

    public static function get_instance()
    {
        if (!isset(self::$instance)) {
            self::$instance = new self;
        }

        return self::$instance;
    }

    public static function activation_hook()
    {
        add_option('cupri_redirect_after_activation_option', true);
    }

    function __construct()
    {
        load_plugin_textdomain('cupri', false, dirname(plugin_basename(__FILE__)) . '/languages');
        __('cupri', 'cupri');
        __('Custom price payment', 'cupri');
        __('pardakht delkhah', 'cupri');

        $this->defines();
        $this->includes();
        add_action('after_setup_theme', array($this, 'init'), 10);
        add_action('plugins_loaded', array($this, 'plugins_loaded'), 10);
        add_action('admin_init', array($this, 'add_caps'), 10);
        add_action('admin_menu', array($this, '_admin_menu'));
        add_shortcode('cupri', array($this, 'shortcode'));
        add_shortcode('pardakht_delkhah', array($this, 'shortcode'));
        add_action('wp_ajax_cupri_action', array($this, 'ajax'));
        add_action('wp_ajax_nopriv_cupri_action', array($this, 'ajax'));
        add_action('admin_enqueue_scripts', array($this, 'admin_scripts'));
        add_action('wp_enqueue_scripts', array($this, 'scripts'));

        if (is_admin()) {
            add_action('load-post.php', array($this, 'start_metabox_class'));
            add_action('load-post-new.php', array($this, 'start_metabox_class'));
        }


    }

    function defines()
    {
        $defs = array(
            'cupri_url' => plugin_dir_url(__FILE__),
            'cupri_dir' => plugin_dir_path(__FILE__),
        );
        foreach ($defs as $def_name => $def_val) {
            define($def_name, $def_val);
        }

    }

    function includes()
    {
        require_once cupri_dir . 'extra.php';
        require_once cupri_dir . 'gateways' . DIRECTORY_SEPARATOR . 'initial.php';
        require_once cupri_dir . 'class-fields-generator.php';
        require_once cupri_dir . 'class-meta-box.php';
        require_once cupri_dir . 'widget.php'; //todo

    }

    function plugins_loaded()
    {
        if (get_option('cupri_redirect_after_activation_option', false)) {
            delete_option('cupri_redirect_after_activation_option');
            exit(wp_redirect(admin_url('edit.php?post_type=cupri_pay')));
        }
    }

    function init()
    {
        /**
         * Listen to hear from returning requests
         */
        $this->listen();
        $this->register_post_type();
        if (is_admin()) {
            // process excel export
            $this->export_excel();
        }

    }

    public function listen()
    {
        if (!isset($_GET['cupri_listen'])) return;
        $cupri_gateway = sanitize_text_field($_REQUEST['cupri_gateway']);
        if (isset($_REQUEST['cupri_gateway']) && !empty($cupri_gateway)) {
            do_action('cupri_end_payment_' . $cupri_gateway);
            die();

        }
    }

    function register_post_type()
    {

        $labels = array(
            'name' => __('payment', 'cupri'),
            'singular_name' => __('payment', 'cupri'),
            'add_new' => '', //null , we dont need to this
            'add_new_item' => '', //null , we dont need to this
            'edit_item' => '', //null , we dont need to this
            'new_item' => '', //null , we dont need to this
            'view_item' => '', //null , we dont need to this
            'search_items' => '', //null , we dont need to this
            'not_found' => __('No payments found', 'cupri'),
            'not_found_in_trash' => __('No payments found in Trash', 'cupri'),
            'parent_item_colon' => '', //null , we dont need to this
            'menu_name' => __('Custom payment', 'cupri'),
        );

        $args = array(
            'labels' => $labels,
            'hierarchical' => false,
            // 'description' => 'description',
            'taxonomies' => array(),
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => true,
            'show_in_admin_bar' => false,
            'menu_position' => null,
            'menu_icon' => null,
            'show_in_nav_menus' => false,
            'publicly_queryable' => false,
            'exclude_from_search' => true,
            'has_archive' => false,
            'query_var' => false,
            'can_export' => true,
            'rewrite' => false,
            'capability_type' => array('cupri_pay', 'cupri_pays'),
            'map_meta_cap' => true,
            // 'capabilities' => array(
            // 	'create_posts' => 'do_not_allow',
            // 	'edit_post' => 'edit_cupri_pay',
            // 	'read_post' => 'read_cupri_pay',
            // 	'delete_post' => 'delete_cupri_pay',
            // 	'edit_posts' => 'edit_cupri_pays',
            // 	'edit_others_posts' => 'edit_others_cupri_pays',
            // 	'publish_posts' => 'publish_cupri_pays',
            // 	'read_private_posts' => 'read_private_cupri_pays',
            // 	// 'create_posts' => 'create_cupri_pays',
            // 	'edit_published_posts' => 'edit_published_cupri_pays',
            // 	),
            'supports' => array('title'),
        );

        register_post_type('cupri_pay', $args);

    }

    function export_excel()
    {
        if (isset($_POST['cupri_baze']) && !empty($_POST['cupri_baze'])) {

            if (!empty($_POST) && (!isset($_POST['cupriExport_form_nonce']) || !wp_verify_nonce($_POST['cupriExport_form_nonce'], 'cupriExport_form_nonce'))) {
                echo cupri_failed_msg('مقادیر ارسالی از منبع معتبری نیستند');
                return;
            }
            $months = sanitize_text_field($_POST['cupri_baze']);
            $months = str_replace('month', '', $months);
            $months = (float)$months;
            //check if any data
            $post_date_after = $months * 30;
            $post_date_after = round($post_date_after);
            $args = array(
                'post_type' => 'cupri_pay',
                'posts_per_page' => -1,
//                'post_status'   => 'any',
                'date_query' => array(
                    'column' => 'post_date',
                    'after' => '- ' . $post_date_after . ' days'
                )
            );
            $user_query = [];
            $query = new WP_Query($args);
            if ($query->have_posts()) {
                while ($query->have_posts()) {
                    $query->the_post();
                    $order_id = get_the_ID();
                    $gateway = get_post_meta($order_id, '_cupri_gateway', true);
                    $price = get_post_meta($order_id, '_cupri_fprice', true);
                    $currency = get_post_meta($order_id, '_cupri_currency', true);
                    $currency = __($currency, 'cupri');
                    $res_code = get_post_meta($order_id, '_cupri_result_code', true);
                    $status = '-';
                    $get_post_status_object = get_post_status_object(get_post_status($order_id));
                    if (is_object($get_post_status_object) && !is_wp_error($get_post_status_object)) {
                        $status = $get_post_status_object->label;
                    }

                    $user_query[$order_id][__('Gateway', 'cupri')] = $gateway;
                    $user_query[$order_id][__('Date')] = get_post_time(
                        'F j, Y',      // format
                        TRUE,          // GMT
                        $order_id,  // Post ID
                        TRUE           // translate, use date_i18n()
                    );
                    $user_query[$order_id][__('Price', 'cupri')] = $price;
                    $user_query[$order_id][__('Currency', 'cupri')] = $currency;
                    $user_query[$order_id][__('status', 'cupri')] = $status;
                    $user_query[$order_id][__('res_code', 'cupri')] = $res_code;


                    $_cupri = get_option('_cupri', cupri_get_defaults_fields());
                    foreach ($_cupri['type'] as $wc_cf_key => $wc_cf) {
                        $key = 'cupri_f' . $wc_cf_key;
                        $key = '_' . $key;
                        if ($key == '_cupri_fprice' || (isset($_cupri['disable'][$wc_cf_key]) && $_cupri['disable'][$wc_cf_key] == 1)) continue;
                        $user_query[$order_id][$_cupri['name'][$wc_cf_key]] = get_post_meta($order_id, $key, true);
                    }
                    // Other Details
                    $details = get_post_meta($order_id, '_cupri_log', true);
                    if (!is_array($details)) {
                        $details = [];
                    }
//                    $details['ip']= $user_query[$order_id]['ip']=isset($details['ip'])?$details['ip']:'';
//                    $details['port']= $user_query[$order_id]['port']=isset($details['port'])?$details['port']:'';
//                    $details['user-agent']= $user_query[$order_id]['user-agent']=isset($details['user-agent'])?$details['user-agent']:'';
//                    $details['referer']= $user_query[$order_id]['referer']=isset($details['referer'])?$details['referer']:'';
//                    $details['hostname']= $user_query[$order_id]['hostname']=isset($details['hostname'])?$details['hostname']:'';


                    foreach ($details as $d_name => $d_value) {
                        $user_query[$order_id][$d_name] = esc_html($d_value);
                    }


                }

                if (!empty($user_query)) {
                    $filename = "pardakht-delkhah-" . $months . "-months[" . date('Y-m-d H:i:s') . "].csv"; // File Name
                    // UFT8
                    header('Content-Encoding: UTF-8');
                    // Download file
                    header("Content-Disposition: attachment; filename=\"$filename\"");
                    header("Content-Type: application/vnd.ms-excel ; charset=utf-8");
                    // Write data to file
                    ob_start();
                    echo "\xEF\xBB\xBF"; // Byte Order Mark  - UTF8 BOM
                    $file_handle = fopen("php://output", 'w');
                    $flag = false;
                    foreach ($user_query as $row) {
                        if (!$flag) {
                            // display field/column names as first row
                            echo cupri_wp_kses(implode(",", array_keys($row)) . "\r\n");
                            $flag = true;
                        }
                        fputcsv($file_handle, $row);
//                        echo implode("\t", array_values($row)) . "\r\n";
                    }
                    fclose($file_handle);
                    $csv = ob_get_clean();
                    echo cupri_wp_kses($csv); // should send headers first!
                    die();
                }


            } else {
                $_POST['cupri_export_msg'] = __('No payments found in this range', 'cupri');
            }

        }


    }

    function add_caps()
    {
        /**
         * حذف منوهای غیر ضروری
         */
        if (current_user_can('manage_options') || current_user_can('manage_cupri_pays')) {

            remove_submenu_page('edit.php?post_type=cupri_pay', 'post-new.php?post_type=cupri_pay');
            // remove_menu_page('profile.php');
            // remove_menu_page('index.php');
        }

        /**
         * افودن نقش ها
         */
        remove_role('manage_cupri_pays');
        add_role('manage_cupri_pays', __('Custom payments manager', 'cupri'), array('read' => true));

        /**
         * Valid Roles
         */
        $roles = array('administrator', 'manage_cupri_pays');

        foreach ($roles as $role) {

            $admins = get_role($role);

            $admins->add_cap('edit_cupri_pay');
            $admins->add_cap('read_cupri_pay');
            $admins->add_cap('delete_cupri_pay');
            $admins->add_cap('edit_cupri_pays');
            $admins->add_cap('edit_others_cupri_pays');
            $admins->add_cap('publish_cupri_pays');
            $admins->add_cap('read_private_cupri_pays');
            // $admins->add_cap('create_cupri_pays');
            $admins->add_cap('edit_published_cupri_pays');


        }

    }

    function admin_scripts()
    {
        if (is_admin() && isset($_GET['post_type']) && $_GET['post_type'] == 'cupri_pay') {
            wp_enqueue_style('cupri-admin-css', cupri_url . '/assets/admin.min.css');
            wp_enqueue_script('cupri-admin-js', cupri_url . '/assets/admin.js', array('jquery'));
        }
    }

    function scripts()
    {
        if (!defined('CUPRI_SCRIPTS_LOADED')) {

            $form_page_id = cupri_get_opt('form_page_id') ?? false;
            if ($form_page_id && is_page($form_page_id)) {
                wp_enqueue_style('cupri-user-css', cupri_url . '/assets/user.css');
                wp_enqueue_script('cupri-user-js', cupri_url . '/assets/user.js', array('jquery'));
                define('CUPRI_SCRIPTS_LOADED', true);
            }
        }
    }

    function start_metabox_class()
    {
        new cupri_meta_box();
    }

    function _admin_menu()
    {
        add_submenu_page('edit.php?post_type=cupri_pay', __('Custom Fields', 'cupri'), __('Custom Fields', 'cupri'), 'manage_options', 'cupri-fields', array($this, 'admin_menu_fields'));
        add_submenu_page('edit.php?post_type=cupri_pay', __('Gateway Settings', 'cupri'), __('Gateway Settings', 'cupri'), 'manage_options', 'cupri-gateways', array($this, 'admin_menu_gateways'));
        add_submenu_page('edit.php?post_type=cupri_pay', __('Settings', 'cupri'), __('Settings', 'cupri'), 'manage_options', 'cupri-settings', array($this, 'admin_menu_settings'));
        add_submenu_page('edit.php?post_type=cupri_pay', __('Custom link', 'cupri'), __('Custom link', 'cupri'), 'manage_options', 'cupri-custom-link', array($this, 'admin_menu_custom_link'));
        add_submenu_page('edit.php?post_type=cupri_pay', __('Export'), __('Export'), 'manage_options', 'cupri-export', array($this, 'admin_menu_export'));
        add_submenu_page('edit.php?post_type=cupri_pay', __('Help', 'cupri'), __('Help', 'cupri'), 'manage_options', 'cupri-help', array($this, 'admin_menu_help'));
    }

    function admin_menu()
    {
        require_once cupri_dir . 'admin-table-header.php';
    }

    function admin_menu_fields()
    {
        /**
         * Custom Fields
         */
        require_once cupri_dir . 'admin-custom-fields.php';
    }

    function admin_menu_gateways()
    {
        require_once cupri_dir . 'gateways.php';
    }

    function admin_menu_settings()
    {
        require_once cupri_dir . 'admin-settings.php';
    }

    function admin_menu_custom_link()
    {
        require_once cupri_dir . 'admin-custom-link.php';
    }

    function admin_menu_export()
    {
        require_once cupri_dir . 'admin-export.php';
    }

    function admin_menu_help()
    {
        require_once cupri_dir . 'help.php';
    }

    function shortcode()
    {
        ob_start();
        require_once cupri_dir . 'shortcode.php';
        return ob_get_clean();

    }

    function ajax()
    {
        $cupri_gateways_settings = get_option('cupri_gateways_settings');
        if (!isset($cupri_gateways_settings['default']) || empty($cupri_gateways_settings['default'])) {
            if (current_user_can('manage_options')) {
                _e('Please set the default gateway from admin', 'cupri');
                echo '  ';
                echo '<a href="' . esc_url(admin_url('edit.php?post_type=cupri_pay&page=cupri-gateways')) . '">' . __('Settings', 'cupri') . '</a>';
            } else {
                _e('No defualt gateway was set', 'cupri');

            }
            if (DOING_AJAX)
                die();
            else
                return;
        }
        //custom fields check
        $gateway = $cupri_gateways_settings['default'];
        $_cupri = get_option('_cupri', cupri_get_defaults_fields());
        $errors = array();
        foreach ($_cupri['type'] as $wc_cf_key => $wc_cf) {
            // if($_cupri['type'][$wc_cf_key]=='text'){}
            $field_type = $_cupri['type'][$wc_cf_key];
            /**
             * Validation ** Required?
             */
            $required = false;
            if (isset($_cupri['required'][$wc_cf_key]) && $_cupri['required'][$wc_cf_key] == 1 && (isset($_cupri['disable'][$wc_cf_key]) && $_cupri['disable'][$wc_cf_key] != 1) && $_cupri['type'][$wc_cf_key] != 'paragraph') {
                $required = true;
            }

            $_submitted_name = 'cupri_f' . $wc_cf_key;

            if ($required) {
                if (!isset($_POST[$_submitted_name]) || empty($_POST[$_submitted_name])) {
                    $errors[] = __('Please Fill This Field:', 'cupri') . ' <i>' . $_cupri['name'][$wc_cf_key] . '</i>';
                }
            }

            /**
             * Validation ** Correct Data
             */
            if ($field_type == 'select' && isset($_POST[$_submitted_name])) {
                if (!in_array($_POST[$_submitted_name], $_cupri['combobox_choices'][$wc_cf_key])) {
                    $errors[] = __('Plrease Check This Field Value:', 'cupri') . ' <i>' . $_cupri['name'][$wc_cf_key] . '</i>';
                }
            }

        }

        /**
         * Price Check
         */
        if (!isset($_POST['cupri_fprice']) || empty($_POST['cupri_fprice'])) {
            $errors[] = __('Please Enter Price', 'cupri');
        } else {
            $min_price = $_cupri['min']['price'];
            $entered_price = sanitize_text_field($_POST['cupri_fprice']);
            if (!empty($min_price) && $entered_price < $min_price) {
                $errors[] = __('Minimum price is : ', 'cupri') . $min_price;
            }
            if (!is_numeric(_wpm_persian_digit_to_eng($_POST['cupri_fprice']))) {
                $errors[] = __('Price value is not correct ', 'cupri') . $min_price;
            }
        }

        /**
         * Mobile Check
         */
        $cupri_fmobile = isset($_POST['cupri_fmobile']) ? sanitize_text_field($_POST['cupri_fmobile']) : '';
        if ($_cupri['required']['mobile'] == 1 && !empty($cupri_fmobile)) {
            $mobile = cupri_normalize_mobile(sanitize_text_field($_POST['cupri_fmobile']));
            if (!$mobile) {
                $errors[] = __('Entered mobile is not correct ', 'cupri');
            } else {
                $cupri_fmobile = $mobile;
            }
        }


        /**
         * Email Check
         */
        $cupri_femail = isset($_POST['cupri_femail']) ? sanitize_email($_POST['cupri_femail']) : '';
        if (isset($_cupri['required'][$wc_cf_key]) && $_cupri['required'][$wc_cf_key] == 1 && isset($_POST['cupri_femail']) && !empty($_POST['cupri_femail'])) {
            $email = sanitize_email($_POST['cupri_femail']);
            $email = filter_var($email, FILTER_VALIDATE_EMAIL);
            if (!$email) {
                $errors[] = __('Entered email is not correct ', 'cupri');
            } else {
                $cupri_femail = $email;
            }
        }


        if (!empty($errors)) {
            echo '<ul class="alert alert-warning cupri-errors">';
            foreach ($errors as $_err) {
                echo('<li >- ' . esc_html($_err) . '</li>');

            }
            echo '</ul>';
            if (DOING_AJAX)
                die();
            else
                return;
        }

        $cupri_fprice = _wpm_persian_digit_to_eng($_POST['cupri_fprice']);
        $currency = cupri_get_currency_value();
        if (strtolower($currency) == 'rial') {
            $cupri_fprice = $cupri_fprice / 10; // convert to Toman(Default)
        }


        $order_post = $order_id = wp_insert_post(array('post_type' => 'cupri_pay', 'post_status' => 'cupri_waiting'), true);
        if (!$order_post) {
            echo(__('Error in payment creation', 'cupri'));
            if (DOING_AJAX)
                die();
            else
                return;
        }
        //log user details
        cupri_log_user_details($order_id);

        //	add payer details
        update_post_meta($order_id, '_wpm_order_type', 'donate');
        //	custom fields
        $_all_data = array();
        foreach ($_cupri['type'] as $wc_cf_key => $wc_cf) {
            $_submitted_name = 'cupri_f' . $wc_cf_key;
            if (isset($_POST[$_submitted_name]) && !empty($_POST[$_submitted_name])) {

                $value = sanitize_text_field($_POST[$_submitted_name]);
//                $value = esc_html($value);
                /**
                 * wp_slash = برای عدم حذف خط جدید مثل /n و /r/n
                 */
                $_all_data[$_cupri['name'][$wc_cf_key]] = $value;
                update_post_meta($order_id, '_' . $_submitted_name, wp_slash($value));
            }

        }
        // ensure having all data for later (if some fields missed)
        update_post_meta($order_id, '_cupri_fields', $_all_data);
        update_post_meta($order_id, '_cupri_fprice', $cupri_fprice);
        update_post_meta($order_id, '_cupri_fmobile', $cupri_fmobile);
        update_post_meta($order_id, '_cupri_femail', $cupri_femail);
        update_post_meta($order_id, '_cupri_currency', cupri_get_currency_value());
        update_post_meta($order_id, '_cupri_gateway', $gateway);

        $payment_data =
            array
            (
                'order_id' => $order_id,
                'price' => $cupri_fprice,
            );
        ob_start();
        /*
            Payment action Goes Here
        */


        do_action('cupri_start_payment_' . $gateway, $payment_data);

        $html = ob_get_clean();
        $html = str_replace(array("\n", "\r"), ' ', $html);
        echo wp_kses($html, ['a' => ['target' => [], 'href' => [], 'id' => [], 'class' => [], 'style' => []], 'html' => [], 'body' => [], 'style' => [], 'script' => ['type' => []], 'img' => ['src' => [], 'width' => [], 'height' => []], 'form' => ['method' => [], 'action' => [], 'name' => [], 'id' => []], 'pre' => [], 'strong' => [], 'ul' => [], 'div' => ['id' => [], 'style' => [], 'class' => []], 'p' => ['class' => [], 'style' => []], 'label' => [], 'br' => [], 'input' => ['checked' => [], 'type' => [], 'value' => [], 'name' => []], 'hr' => [], 'h2' => [], 'h3' => [], 'h4' => [], 'li' => ['data-tab-id' => [], 'class' => []], 'a' => ['href' => []], 'table' => [], 'tbody' => [], 'tfoot' => [], 'tr' => ['class' => []], 'td' => ['colspan' => []]]);

        if (DOING_AJAX)
            die();
        else
            return;
    }


    function _wpm_jdate($format, $time)
    {
        if (function_exists('jdate')) {
            return jdate($format, $time);
        } elseif (class_exists('bn_parsidate')) {
            $bndate = bn_parsidate::getInstance();
            return $bndate = $bndate->persian_date($format, $time);
        } else {
            return date($format, $time);
        }
    }

}

cupri::get_instance();
