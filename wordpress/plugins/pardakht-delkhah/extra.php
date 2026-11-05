<?php
defined('ABSPATH') or die('No script kiddies please!');
function cupri_normalize_mobile($mobile)
{
    $mobile = _wpm_persian_digit_to_eng($mobile);
    $mobile = trim($mobile);
    $mobile = str_replace(array('+'), '', $mobile);
    $mobile = ltrim($mobile, '0');
    if (substr($mobile, 0, 2) == '98') {
        $mobile = ltrim($mobile, '9');
        $mobile = ltrim($mobile, '8');
    }
    if (strlen($mobile) != 10) {
        return false;
    }
    $mobile = '0' . $mobile;
    preg_match('/^(\0098|98|0)?09\d{9}$/m', $mobile, $mobileMatch);
    if (!$mobileMatch) {
        return false;
    }

    return apply_filters('cupri_normalize_mobile', $mobile);
}

/**
 * Posts columns
 */
add_filter('manage_cupri_pay_posts_columns', 'add_cupri_pay_columns', 99999999);
function add_cupri_pay_columns($columns)
{
    $new_columns = array(
        'cb' => '<input type="checkbox" />',
        // 'title' => __('Title' ),
        // 'post_id' => __('ID' ),
        'date' => __('Date'),
        // 'price' => __('Price' , 'cupri'),
        'status' => __('Status', 'cupri'),
        'result_code' => __('Result Code', 'cupri'),
    );

    $_cupri = get_option('_cupri', cupri_get_defaults_fields());
    foreach ($_cupri['type'] as $wc_cf_key => $wc_cf) {
        $key = 'cupri_f' . $wc_cf_key;
        $key = '_' . $key;
        $new_columns[$key] = $_cupri['name'][$wc_cf_key];
    }

    unset($new_columns['_cupri_femail']);

    return $new_columns;
}

add_action('manage_cupri_pay_posts_custom_column', 'custom_cupri_pay_column', 99999999, 2);
function custom_cupri_pay_column($column, $post_id)
{
    if (strpos($column, 'cupri_f')) {
        $value = get_post_meta($post_id, $column, true);
        $value = esc_html($value);
        if ($column == '_cupri_fmobile') {
            $email_value = get_post_meta($post_id, '_cupri_femail', true);
            $email_value = esc_html($email_value);
            $email_value = (empty($email_value) ? '-' : $email_value);
            echo esc_html($value) . '<br>' . esc_html($email_value);
        } else {
            echo(empty($value) ? '-' : esc_html($value));
        }
    }

    if ($column == 'post_id') {
        echo (int)$post_id;
    }
    // if($column == 'title2')
    // {
    //     echo '<a href="'.admin_url('post.php?post='.$post_id.'&action=edit' ).'">'.__('More Details','cupri').'</a>';
    // }
    if ($column == 'status') {
        $get_post_status_object = get_post_status_object(get_post_status($post_id));
        if (is_object($get_post_status_object) && !is_wp_error($get_post_status_object)) {
            echo esc_html($get_post_status_object->label);
        }

    }
    if ($column == 'result_code') {
        $result_code = get_post_meta($post_id, '_cupri_result_code', true);
        $result_code = (empty($result_code) ? '-' : $result_code);
        echo esc_html($result_code);


    }

}

/**
 * Register Post status
 */
register_post_status('cupri_waiting', array(
        'label' => 'منتظر پرداخت',
        'public' => true,
        'exclude_from_search' => true,
        'show_in_admin_all_list' => true,
        'show_in_admin_status_list' => true,
        'label_count' => _n_noop('منتظر پرداخت <span class="count">(%s)</span>', 'منتظر پرداخت <span class="count">(%s)</span>'),
    )
);
register_post_status('cupri_paid', array(
        'label' => 'پرداخت شده',
        'public' => true,
        'exclude_from_search' => true,
        'show_in_admin_all_list' => true,
        'show_in_admin_status_list' => true,
        'label_count' => _n_noop('پرداخت شده <span class="count">(%s)</span>', 'پرداخت شده <span class="count">(%s)</span>'),
    )
);
register_post_status('cupri_failed', array(
        'label' => 'ناموفق',
        'public' => true,
        'exclude_from_search' => true,
        'show_in_admin_all_list' => true,
        'show_in_admin_status_list' => true,
        'label_count' => _n_noop('ناموفق <span class="count">(%s)</span>', 'ناموفق <span class="count">(%s)</span>'),
    )
);

/**
 * Fns
 */

function cupri_msg($msg, $order_id = false, $type = false)
{
    $cupri_general = cupri_get_opt();
    $_msg = '<!doctype html><html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>' . get_bloginfo('name') . ' | ' . __('Payment', 'cupri') . '</title></head><body dir="' . (is_rtl() ? 'rtl' : 'ltr') . '">';
    $_msg .= '
    <style>
        body,html{
            font-family:IRANSans,vazir,yekan,"b yekan","b nazanin",tahoma;
        }
        .cupri-msg{
            direction:rtl;
            padding:20px ;
            background:#f9f9f9;
        }
        .cupri-msg img{
            display: inline-block;
            vertical-align: middle;
            margin: 3px;
        }
        .cupri-home-link {
            background: ' . $cupri_general['form_color'] . ';
            padding: 5px;
            display: block;
            margin: auto;
            text-align: center;
            width: 80px;
            color: #000;
            text-decoration:none;
        }

    .tfooter,.tfooter td{background:#f3f3f3;text-align:center !important;border:1px solid #eee;}
    .cupri_order_details_wrapper{
        display: block;
        max-width: 500px;
        width:100%;
        margin: auto;
        padding: 15px;
    }
    .cupri_order_details tr td{width:50%;}
    .cupri_order_details tbody,.cupri_order_details tr{width:100%;}
    .cupri_order_details_wrapper table caption {
    background: #f19a04;
    padding: 10px;
    font-size: 14px;
    color: #fff;
    text-shadow: none;
    font-weight: bold;
    }
    .cupri_order_details_wrapper table {
        color:#666;
        font-size:12px;
        text-shadow: 1px 1px 0px #fff;
        background:#f9f9f9;
        border:#ccc 1px solid;

        -moz-border-radius:3px;
        -webkit-border-radius:3px;
        border-radius:3px;

        -moz-box-shadow: 0 1px 2px #d1d1d1;
        -webkit-box-shadow: 0 1px 2px #d1d1d1;
        box-shadow: 0 1px 2px #d1d1d1;
        width:100%;
    }
    .cupri_order_details_wrapper table th {
        padding:21px 25px 22px 25px;
        border-top:1px solid #fafafa;
        border-bottom:1px solid #e0e0e0;

        background: #ededed;
        background: -webkit-gradient(linear, left top, left bottom, from(#ededed), to(#ebebeb));
        background: -moz-linear-gradient(top,  #ededed,  #ebebeb);
    }
    .cupri_order_details_wrapper table th:first-child {
        text-align: right;
        padding-right:20px;
    }
    .cupri_order_details_wrapper table tr:first-child th:first-child {
        -moz-border-radius-topleft:3px;
        -webkit-border-top-right-radius:3px;
        border-top-right-radius:3px;
    }
    .cupri_order_details_wrapper table tr:first-child th:last-child {
        -moz-border-radius-topright:3px;
        -webkit-border-top-left-radius:3px;
        border-top-left-radius:3px;
    }
   .cupri_order_details_wrapper table tr {
        text-align: center;
        padding-right:20px;
    }
    .cupri_order_details_wrapper table td:first-child {
        text-align: right;
        padding-right:20px;
        border-right: 0;
    }
    .cupri_order_details_wrapper table td {
        padding:18px;
        border-top: 1px solid #ffffff;
        border-bottom:1px solid #e0e0e0;
        border-right: 1px solid #e0e0e0;

        background: #fafafa;
        background: -webkit-gradient(linear, right top, right bottom, from(#fbfbfb), to(#fafafa));
        background: -moz-linear-gradient(top,  #fbfbfb,  #fafafa);
    }
    .cupri_order_details_wrapper table tr.even td {
        background: #f6f6f6;
        background: -webkit-gradient(linear, right top, right bottom, from(#f8f8f8), to(#f6f6f6));
        background: -moz-linear-gradient(top,  #f8f8f8,  #f6f6f6);
    }
    .cupri_order_details_wrapper table tr:last-child td {
        border-bottom:0;
    }
    .cupri_order_details_wrapper table tr:last-child td:first-child {
        -moz-border-radius-bottomright:3px;
        -webkit-border-bottom-right-radius:3px;
        border-bottom-right-radius:3px;
    }
    .cupri_order_details_wrapper table tr:last-child td:last-child {
        -moz-border-radius-bottomright:3px;
        -webkit-border-bottom-left-radius:3px;
        border-bottom-left-radius:3px;
    }
    .cupri_order_details_wrapper table tr:hover td {
        background: #f2f2f2;
        background: -webkit-gradient(linear, right top, right bottom, from(#f2f2f2), to(#f0f0f0));
        background: -moz-linear-gradient(top,  #f2f2f2,  #f0f0f0);  
    }
    </style>';
    $home_link = __('Home');
    $home_url = '';
    $order_details = '';
    if ($order_id) {
        $price = get_post_meta($order_id, '_cupri_fprice', true);
        $res_code = get_post_meta($order_id, '_cupri_result_code', true);
        $status = '-';
        $get_post_status_object = get_post_status_object(get_post_status($order_id));
        if (is_object($get_post_status_object) && !is_wp_error($get_post_status_object)) {
            $status = $get_post_status_object->label;
        }

        $pay_currency = get_post_meta($order_id, '_cupri_currency', true);
        $pay_currency = strtolower($pay_currency);
        $current_currency = cupri_get_currency_value();
        $current_currency = strtolower($current_currency);
        if ($pay_currency == 'rial' || $pay_currency == 'toman') {
            // fix IR currency show correct price
            // default is toman
            if ($current_currency == 'rial') {
                $price = $price * 10;
            }

        }


        $order_details .= '
        <div class="cupri_order_details_wrapper">
        <table class="cupri_order_details">
               <caption> جزيیات پرداخت </caption>
            <tbody>
            <tr>
                <td><strong>' . __('Price', 'cupri') . ':</strong> </td>
                <td>' . number_format($price) . ' <small>(' . cupri_get_currency() . ')</small></td>
            </tr>
            <tr>
                <td><strong>' . __('Payment Status', 'cupri') . ':</strong> </td>
                <td>' . $status . ' </td>
            </tr>
            <tr>
                <td><strong>' . __('Result Code', 'cupri') . ':</strong> </td>
                <td>' . $res_code . ' </td>
            </tr>
            ';

        if ($type == 'success') {
            $new_columns = array();
            $_cupri = get_option('_cupri', cupri_get_defaults_fields());
            foreach ($_cupri['type'] as $wc_cf_key => $wc_cf) {
                $key = 'cupri_f' . $wc_cf_key;
                $key = '_' . $key;
                if ($key == '_cupri_fprice' || (isset($_cupri['disable'][$wc_cf_key]) && $_cupri['disable'][$wc_cf_key] == 1)) continue;
                $order_details .= '<tr>
                        <td><strong>' . $_cupri['name'][$wc_cf_key] . ':</strong> </td>
                        <td>' . get_post_meta($order_id, $key, true) . ' </td>
                    </tr>';
            }

        }

        $order_details .= '
            </tbody>
              <tfoot>
                <tr class="tfooter">
                  <td colspan=2>' . get_the_date('Y/m/d - g:i A', $order_id) . '</td>
                </tr>
              </tfoot>

            ';


        $order_details .= '
        </table>
        </div>
        ';
        $home_url = '<a class="cupri-home-link" href="' . get_bloginfo('url') . '"> ℹ ' . $home_link . '</a>';
    }
    $_msg .= '<div class="cupri_msg_wrapper"> ' . $msg . $order_details . $home_url . '</div>';
    return $_msg;
}

function cupri_success_msg($msg, $order_id = false)
{
    $msg = '<p style="color:green;text-align:center;border:1px solid #ededed;" class="cupri-msg cupri-success"><img src="' . cupri_url . '/assets/checked.png" width="50" height="50" >' . $msg . '</p>';
    return cupri_msg($msg, $order_id, $type = 'success');
}

function cupri_failed_msg($msg, $order_id = false)
{
    $msg = '<p style="color:red;text-align:center;border:1px solid #ededed;" class="cupri-msg cupri-error"><img src="' . cupri_url . '/assets/cancel.png" width="50" height="50" >' . $msg . '</p>';
    return cupri_msg($msg, $order_id, $type = 'failed');
}


function cupri_add_tbl_head()
{
    $screen = get_current_screen();
    if ($screen->id == 'edit-cupri_pay') {
        require_once cupri_dir . 'admin-table-header.php';
    }

    if (isset($screen->id) && strpos($screen->id, 'cupri') !== false) {
        if (!class_exists('SoapClient')) {
            echo '<div class="notice notice-warning is-dismissible">
             <p> <strong>' . __('pardakht delkhah', 'cupri') . ': </strong> ' . sprintf(esc_html__('%s is not active on your host, some gateways may not work properly.', 'cupri'), 'Soap') . '</p>
         </div>';
        }
    }


}

add_action('admin_notices', 'cupri_add_tbl_head');


function cupri_get_defaults_fields()
{
    $def = array();
    /**
     * Price Field
     */
    $def['type']['price'] = 'price';
    $def['name']['price'] = __('Price', 'cupri');
    $def['min']['price'] = '';
    $def['default']['price'] = '';
    $def['text_placeholder']['price'] = __('Please enter a price', 'cupri');

    /**
     * Name
     */
    $def['type'][] = 'text';
    $def['name'][] = __('Name', 'cupri');
    $def['disable'][] = '';
    $def['required'][] = 1;
    $def['text_placeholder'][] = __('Please enter your name', 'cupri');


    /**
     * Mobile Field
     */
    $def['type']['mobile'] = 'mobile';
    $def['name']['mobile'] = __('Mobile', 'cupri');
    $def['disable']['mobile'] = '';
    $def['required']['mobile'] = 1;
    $def['text_placeholder']['mobile'] = __('Please enter your mobile', 'cupri');

    /**
     * Email Field
     */
    $def['type']['email'] = 'email';
    $def['name']['email'] = __('Email', 'cupri');
    $def['disable']['email'] = '';
    $def['text_placeholder']['email'] = __('Please enter your email', 'cupri');


    return $def;
}

/**
 * Extend payments list search to seek in the post_meta table also
 * @thanksTo http://wordpress.stackexchange.com/a/12356
 */

add_filter('posts_join', 'cupri_pay_search_join');
function cupri_pay_search_join($join)
{
    global $pagenow, $wpdb;
    // I want the filter only when performing a search on edit page of Custom Post Type named "cupri_pay"
    if (is_admin() && $pagenow == 'edit.php' && $_GET['post_type'] == 'cupri_pay' && isset($_GET['s']) && $_GET['s'] != '') {
        $join .= 'LEFT JOIN ' . $wpdb->postmeta . ' ON ' . $wpdb->posts . '.ID = ' . $wpdb->postmeta . '.post_id ';
    }
    return $join;
}

add_filter('posts_where', 'cupri_pay_search_where');
function cupri_pay_search_where($where)
{
    global $pagenow, $wpdb;
    // I want the filter only when performing a search on edit page of Custom Post Type named "cupri_pay"
    if (is_admin() && $pagenow == 'edit.php' && $_GET['post_type'] == 'cupri_pay' && isset($_GET['s']) && $_GET['s'] != '') {
        $where = preg_replace(
            "/\(\s*" . $wpdb->posts . ".post_title\s+LIKE\s*(\'[^\']+\')\s*\)/",
            "(" . $wpdb->posts . ".post_title LIKE $1) OR (" . $wpdb->postmeta . ".meta_value LIKE $1)", $where);
    }
    return $where;
}


/**
 * cupri currency
 */
function cupri_get_currency()
{
    __('Toman', 'cupri');
    __('Rial', 'cupri');
    return __(cupri_get_currency_value(), 'cupri');
}

function cupri_get_currency_value()
{
    $cupri_gateways_settings = get_option('cupri_gateways_settings');
    $currency = 'Toman';
    if (isset($cupri_gateways_settings['currency'])) {
        $currency = $cupri_gateways_settings['currency'];

    }


    return $currency;
}


/**
 * تبدیل اعداد فارسی به انگلیسی
 */
function _wpm_persian_digit_to_eng($str)
{
    //fa
    $str = str_replace(
        array('۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'),
        array('0', '1', '2', '3', '4', '5', '6', '7', '8', '9'),
        $str);
    //ar
    $str = str_replace(
        array('٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'),
        array('0', '1', '2', '3', '4', '5', '6', '7', '8', '9'),
        $str);
    return $str;
}

/**
 * Prevent From woocommerce to redirect to my-account page
 */
add_action('wp_loaded', 'cupri_check_wc_redirection');
function cupri_check_wc_redirection()
{
    if (current_user_can('manage_cupri_pays') && !current_user_can('manage_options')) {
        add_filter('woocommerce_prevent_admin_access', '__return_false');
        add_filter('woocommerce_disable_admin_bar', '__return_false');
    }
}


function cupri_mail($to, $subject, $title, $msg)
{
    if (!function_exists('wp_mail')) {
        require_once ABSPATH . 'wp-includes/pluggable.php';
    }
    $body =
        '
    <div style="direction:rtl;text-align:right;font-family:byekan,yekan,\'b yekan\',tahoma;font-size:1em;">
    <h3>' . $title . '</h3>
    <p>' . $msg . '</p>
    </div>
    ';
    $headers = array('Content-Type: text/html; charset=UTF-8');
    wp_mail($to, $subject, $body, $headers);

}


function cupri_pay_row_actions($actions, $post)
{
    if ($post->post_type == 'cupri_pay') {
        $actions = array();
        $delete_nonce = wp_create_nonce('cupri_row_delete');
        $actions['details'] = '<a href="' . get_edit_post_link($post->ID) . '" title="" rel="permalink">' . __('Details', 'cupri') . '</a>';
        $actions['delete'] = '<a href="#" class="_cupri_delete_row" data-nonce="' . $delete_nonce . '" data-post-id="' . $post->ID . '" title="" rel="permalink">' . __('Delete', 'cupri') . '</a>';

        return $actions;
    }
    return $actions;
}

add_filter('post_row_actions', 'cupri_pay_row_actions', 10, 2);


add_action('wp_ajax_cupri_delete_post', 'cupri_delete_post');
function cupri_delete_post()
{
    if (!current_user_can('manage_options')) {
        return __('You dont have access to do this action', 'cupri');
    }
    $nonce = sanitize_text_field($_POST['nonce']);
    if (!wp_verify_nonce($nonce, 'cupri_row_delete')) {
        return;
    }
    if (!isset($_POST['post_id'])) return;
    $post_id = (int)$_POST['post_id'];
    if (empty($post_id) || $post_id == 0) return;
    echo json_encode(array('ok' => 'ok'));
    wp_delete_post($post_id, true);
    die();
}

add_action('wp_ajax_cupri_send_test_sms', 'cupri_send_test_sms');
function cupri_send_test_sms()
{
    if (!current_user_can('manage_options')) {
        echo json_encode([__('You dont have access to do this action', 'cupri')]);
        die();
    }
    $nonce = sanitize_text_field($_POST['nonce']);
    if (!wp_verify_nonce($nonce, 'cupri_send_test_sms_mobile_nonce')) {
        echo json_encode([__('You dont have access to do this action', 'cupri')]);
        die();

    }
    if (!isset($_POST['mobile'])) {
        echo json_encode([__('Mobile is empty', 'cupri')]);
        die();

    }
    $mobile = sanitize_text_field($_POST['mobile']);
    if (empty($mobile)) {
        echo json_encode([__('Mobile is empty', 'cupri')]);
        die();

    }
    if (!function_exists('wp_sms_send')) {
        echo json_encode([__('wp-sms plugin not detected', 'cupri')]);
        die();

    }
    $to [] = $mobile;
    $msg = __('Test sms from pardakht-delkhah plugin') . '(' . get_bloginfo('url') . ')';
    $is_flash = false;
    $log = wp_sms_send($to, $msg, $is_flash);
    echo json_encode([var_export($log, 1)]);

    die();
}


function cupri_get_possible_notification_variables()
{
    $keys = [
        '{sitename}' => __('Site name', 'cupri'),
        '{paydate}' => __('Payment date', 'cupri'),
        '{payhour}' => __('Payment hour', 'cupri'),
        '{currency}' => __('Currency', 'cupri'),
    ];

    $_cupri = get_option('_cupri', cupri_get_defaults_fields());
    foreach ($_cupri['type'] as $wc_cf_key => $wc_cf) {
        $keys['{' . $wc_cf_key . '}'] = $_cupri['name'][$wc_cf_key];
    }
    $to_return = '';

    foreach ($keys as $key => $title) {
        $to_return .= '<strong>' . $title . '</strong>=<code>' . $key . '</code>  ,  ';
    }
    return $to_return;
}

function cupri_replace_notification_variables($order_id, $messgae)
{
    $sitename = get_bloginfo('name');
    $paydate = get_the_date('Y/m/d', $order_id);
    $payhour = get_the_date('g:i A', $order_id);
    /**
     * واحد در هر صورت تومان هست.پس تومان میزنیم
     */
    $messgae = str_replace(array('{sitename}', '{paydate}', '{payhour}', '{currency}'), array($sitename, $paydate, $payhour, __('Toman', 'cupri')), $messgae);

    $_cupri = get_option('_cupri', cupri_get_defaults_fields());
    foreach ($_cupri['type'] as $wc_cf_key => $wc_cf) {
        $key = '_cupri_f' . $wc_cf_key;
        $key_format = '{' . $wc_cf_key . '}';
        $key_value = get_post_meta($order_id, $key, true);
        $messgae = str_replace($key_format, $key_value, $messgae);

    }


    return $messgae;
}


function cupri_log_user_details($order_id)
{
    // IP
    $ip = '';
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = sanitize_text_field($_SERVER['HTTP_CLIENT_IP']);
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = sanitize_text_field($_SERVER['HTTP_X_FORWARDED_FOR']);
    } else {
        $ip = sanitize_text_field($_SERVER['REMOTE_ADDR']);
    }


    $agent = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : ''; // Unreliable to find a connecting client's browser, but can be useful sometimes.
    $href = isset($_SERVER['HTTP_REFERER']) ? sanitize_text_field($_SERVER['HTTP_REFERER']) : ''; // If you're linking this to someone directly, it will usually be nothing.
    $hostname = gethostbyaddr(sanitize_text_field($_SERVER['REMOTE_ADDR'])); // Attempt to resolve hostname from IP address.


    $details = get_post_meta($order_id, '_cupri_log', true);
    if (!is_array($details)) {
        $details = [];
    }

    $details['ip'] = $ip;
    $details['user-agent'] = $agent;
    $details['referer'] = $href;
    $details['hostname'] = $hostname;

    $details = update_post_meta($order_id, '_cupri_log', $details);

}

add_action('cupri_gateways___private__paid_tabs_contents', 'cupri_show_paid_gateways');
function cupri_show_paid_gateways()
{
    $return = '';
    $paid_gateways = [
        'پلاگین پرداخت دلخواه برای بانک پاسارگاد' => 'https://wp-master.ir/?p=10657',
        'پلاگین پرداخت دلخواه برای بانک پارسیان' => 'https://wp-master.ir/?p=1970',
        'پلاگین پرداخت دلخواه برای بانک سامان' => 'https://wp-master.ir/?p=1857',
        'پلاگین پرداخت دلخواه برای پرداخت نوین آرین(اقتصاد نوین)' => 'https://wp-master.ir/?p=13480',
        'پلاگین پرداخت دلخواه برای ایران کیش(جدید)' => 'https://wp-master.ir/?p=13874',
        'سفارش درگاه' => 'https://wp-master.ir/?p=1502',
        'سفارش پلاگین' => 'https://wp-master.ir/?p=2',
    ];

    foreach ($paid_gateways as $g_title => $g_url) {
        $return .= '<p><a target="_blank" href="' . $g_url . '">👑 ' . $g_title . '</a></p>';
    }

    echo wp_kses($return, ['a' => ['target' => [], 'href' => [], 'id' => [], 'class' => [], 'style' => []], 'html' => [], 'body' => [], 'style' => [], 'script' => ['type' => []], 'img' => ['src' => [], 'width' => [], 'height' => []], 'form' => ['method' => [], 'action' => [], 'name' => [], 'id' => []], 'pre' => [], 'strong' => [], 'ul' => [], 'div' => ['id' => [], 'style' => [], 'class' => []], 'p' => ['class' => [], 'style' => []], 'label' => [], 'br' => [], 'input' => ['checked' => [], 'type' => [], 'value' => [], 'name' => []], 'hr' => [], 'h2' => [], 'h3' => [], 'h4' => [], 'li' => ['data-tab-id' => [], 'class' => []], 'a' => ['href' => []], 'table' => [], 'tbody' => [], 'tfoot' => [], 'tr' => ['class' => []], 'td' => ['colspan' => []]]);

}


function cupri_get_opt($key = false)
{
    $defaults = array(
        'disable_readonly_predefined_values' => 0,
        'admin_sms_format' => __("New payment at {sitename} :
Price:
 {price} ({currency})
Mobile:
 {mobile}
Date : 
{paydate}", 'cupri'),
        'form_color' => '#51cbee',
        'admin_email_format' => __("<strong>New payment at {sitename}:</strong>
<h5>Price={price}  ({currency})</h5>
<h5>Mobile={mobile}</h5>
<h5>Email={email}</h5>
<h5>Date={paydate}</h5>", 'cupri'),
        'emails' => get_option('admin_email'),
        'active_email_notification' => 1,
        'active_user_receipt_with_email' => 0,
        'user_email_format' => __("<strong>Your payment at {sitename}:</strong>
<h5>Price={price}  ({currency})</h5>
<h5>Mobile={mobile}</h5>
<h5>Email={email}</h5>
<h5>Date={paydate}</h5>", 'cupri'),
        'active_user_receipt_with_sms' => 0,
        'user_sms_format' => __("Your payment at {sitename} :
Price:
 {price}  ({currency})
Date : 
{paydate}", 'cupri'),

    );
    $cupri_general = get_option('cupri_general_settings', $defaults);
    $cupri_general = array_merge($defaults, $cupri_general);


    if ($key && isset($cupri_general[$key])) return $cupri_general[$key];
    return $cupri_general;

}


function cupri_array_map_recursive($callback, $array)
{
    $func = function ($item) use (&$func, &$callback) {
        return is_array($item) ? cupri_array_map_recursive($func, $item) : call_user_func($callback, $item);
    };

    return array_map($func, $array);
}

function cupri_wp_kses($html)
{
    return wp_kses($html, ['img' => ['class' => [], 'src' => [], 'width' => [], 'style' => []], 'button' => ['id' => [], 'class' => [], 'name' => []], 'option' => ['value' => [], 'selected' => []], 'select' => ['name' => [], 'class' => [], 'option' => []], 'span' => ['id' => [], 'class' => [], 'data-current-id' => [], 'title' => []], 'strong' => ['class' => []], 'ul' => [], 'div' => ['id' => [], 'class' => []], 'p' => ['class' => []], 'label' => ['class' => []], 'br' => [], 'input' => ['checked' => [], 'type' => [], 'value' => [], 'name' => []], 'hr' => [], 'h3' => [], 'h4' => [], 'li' => ['data-tab-id' => [], 'class' => []], 'a' => ['href' => []]]);
}
