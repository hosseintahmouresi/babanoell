<?php
/*
Plugin Name: Kalamatino
Description: You can do changes on your website words.
Author: Shokrino Team
Version: 1.0.1
Author URI: https://shokrino.com
License: GPL v2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Textdomain: kalamatino
*/
defined( 'ABSPATH' ) || exit;

$plugin_data = get_file_data(__FILE__, array('Version' => 'Version'), false);
$plugin_data_name = get_file_data(__FILE__, array('Plugin Name' => 'Plugin Name'), false);
$plugin_version = $plugin_data['Version'];
$plugin_name = $plugin_data_name['Plugin Name'];
define('KALAMATN_NAME', $plugin_name);
define('KALAMATN_VERSION', $plugin_version);
define('KALAMATN_PATH' , WP_CONTENT_DIR.'/plugins'.'/kalamatino');
define('KALAMATN_URL' , plugin_dir_url( __DIR__ ).'kalamatino');
define('KALAMATN_INC' , KALAMATN_PATH.'/inc');
define('KALAMATN_CORE' , KALAMATN_PATH.'/inc/core');
define('KALAMATN_TPL' , KALAMATN_PATH.'/inc/templates');
define('KALAMATN_ASSETS' , plugin_dir_url( __DIR__ ).'kalamatino/assets');

///////////////////////////////////////////////////////////////////////////////////

if (is_admin()) {
    include_once KALAMATN_INC . '/admin/menus.php';
}

///////////////////////////////////////////////////////////////////////////////////

function klmtn_load_textdomain() {
    load_plugin_textdomain( 'kalamatino', false, basename( dirname( __FILE__ ) ) . '/languages/' );
}
add_action( 'init', 'klmtn_load_textdomain' );

function klmtn_settings_link( $links ) {
    $url = esc_url( add_query_arg(
        'page',
        'kalamatino_options',
        get_admin_url() . 'admin.php'
    ) );
    $url2 = esc_url( add_query_arg(
        'page',
        'kalamatino_words',
        get_admin_url() . 'admin.php'
    ) );
    $settings_link = "<a href='$url'>" . __( 'Settings' ) . '</a>';
    array_push(
        $links,
        $settings_link
    );
    return $links;
}
add_filter( 'plugin_action_links_kalamatino/kalamatino.php', 'klmtn_settings_link' );

function kalamatino_admin_notices() {
    if (isset($_POST['savewords']) && empty($_POST['from-text']) && empty($_POST['to-text'])) {
    ?>
    <div class="notice notice-error is-dismissible">
        <p><?php _e( 'Both fields must be filled! if you want to hide a text use SPACE in "to" field', 'kalamatino' ); ?></p>
    </div>
    <?php
    }
}
add_action( 'admin_notices', 'kalamatino_admin_notices' );

///////////////////////////////////////////////////////////////////////////////////

function Word_replace_text_kalamatino($translated_text)
{
    $this_string = get_option('klmtn_from_text','');
    $to_string = get_option('klmtn_to_text','');
    if (!empty($this_string) && !empty($to_string)) {
        $translated_text = str_replace($this_string, $to_string, $translated_text);
    }
    return $translated_text;
}

add_filter('gettext', 'Word_replace_text_kalamatino', 9999999);

function Word_replace_content_kalamatino($translated_text)
{
    $this_string = get_option('klmtn_from_text','');
    $to_string = get_option('klmtn_to_text','');
    if (!empty($this_string) && !empty($to_string)) {
        $translated_text = str_replace($this_string, $to_string, $translated_text);
    }
    return $translated_text;
}

add_filter('the_content', 'Word_replace_content_kalamatino', 9999999);

function Word_replace_widget_kalamatino($translated_text)
{
    $this_string = get_option('klmtn_from_text','');
    $to_string = get_option('klmtn_to_text','');
    if (!empty($this_string) && !empty($to_string)) {
        $translated_text = str_replace($this_string, $to_string, $translated_text);
    }
    return $translated_text;
}

add_filter('widget_text', 'Word_replace_widget_kalamatino', 9999999);

///////////////////////////////////////////////////////////////////////////////////



