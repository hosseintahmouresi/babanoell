<?php
defined( 'ABSPATH' ) || exit;

function kalamatino_register_admin_menus() {
    add_menu_page(
        __('Kalamatino Options','kalamatino'),
        __('Kalamatino','kalamatino'),
        'manage_options',
        'kalamatino_options',
        'page_word_list_kalamatino',
        KALAMATN_ASSETS.'/svg/menu_icon.svg'
    );
}
add_action('admin_menu' , 'kalamatino_register_admin_menus');


function page_word_list_kalamatino() {
    require_once KALAMATN_TPL.'/words-page.php';
}