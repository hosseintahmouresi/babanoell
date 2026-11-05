<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

// Persian map element.
require_once( Codevz_Plus::$dir . 'rtl-xtra/mapp/mapp.php' );

// Download English/arabic demos.
function codevz_api_org_zip( $remote ) {

	$is_xtratheme = ( strpos( $remote, 'xtratheme' ) !== false );

	if ( $is_xtratheme && strpos( $remote, 'folder=rtl' ) !== false ) {

		$remote = str_replace( '.ir', '.com', $remote );
		$remote = str_replace( 'folder=rtl-elementor', 'folder=elementor', $remote );
		$remote = str_replace( 'folder=rtl', 'folder=', $remote );
		$remote = $remote . '&arabic=false';

	} else if ( $is_xtratheme && strpos( $remote, 'folder=arabic' ) !== false ) {

		$remote = str_replace( '.ir', '.com', $remote );
		$remote = str_replace( 'arabic', 'rtl', $remote );
		$remote = $remote . '&arabic=true';

	}

	update_option( 'codevz_latest_imported_remote_url', $remote );

	return $remote;

}
add_filter( 'codevz_config_api_demos_remote', 'codevz_api_org_zip' );

// Fix replace links.
function codevz_replace_links_demo_url_func( $demo_url ) {

	$is_xtratheme = ( strpos( $demo_url, 'xtratheme' ) !== false );

	if ( $is_xtratheme ) {

		$latest_remote = get_option( 'codevz_latest_imported_remote_url' );

		if ( Codevz_Plus::contains( $latest_remote, '.com' ) ) {
			$demo_url = str_replace( '.ir', '.com', $demo_url );
		}

		if ( Codevz_Plus::contains( $latest_remote, 'arabic=false' ) ) {
			$demo_url = str_replace( [ 'arabic', 'arabic-' ], '', $demo_url );
		}

		$demo_url = str_replace( [ '.ir//', '.com//', '/-' ], [ '.ir/', '.com/', '/' ], $demo_url );

	}

	return $demo_url;

}
add_filter( 'codevz_replace_links_demo_url', 'codevz_replace_links_demo_url_func' );


// Notice older users.
function xtra_admin_notice_warning() {

	if ( is_plugin_active( 'rtl-xtra/rtl-xtra.php' ) ) {
		?>
			<div class="notice notice-warning">
				<p>لطفا افزونه "افزودنی های فارسی قالب اکسترا" را غیرفعال کنید (سپس پاک کنید) <br/>
از نگارش 4.0.0 به بالاتر دیگر نیازی به این افزونه نمی باشد.
</p>
			</div>
		<?php
	}

	$cz = esc_html__( 'Codevz Plus', 'codevz-plus' );
	$cs = esc_html__( 'StyleKit, custom post types, options and page builder elements.', 'codevz-plus' );

}
add_action( 'admin_notices', 'xtra_admin_notice_warning' );


// Plugins textdomain.
function xtra_plugin_load_my_own_textdomain( $mofile, $domain ) {

	if ( 'revslider' === $domain && strpos( $mofile, WP_LANG_DIR . '/plugins/' ) !== false ) {
		$locale = apply_filters( 'plugin_locale', determine_locale(), $domain );
		$mofile = WP_PLUGIN_DIR . '/' . dirname( plugin_basename( __FILE__ ) ) . '/languages/revslider-fa_IR.mo';
	}
	return $mofile;
}
add_filter( 'load_textdomain_mofile', 'xtra_plugin_load_my_own_textdomain', 10, 2 );


// Styles & Scripts.
function xtra_add_rtl_xtra_front() {

	wp_enqueue_style( 'cz-icons-farsi', plugin_dir_url(__FILE__) . 'icons/codevz-farsi.css' );
	wp_enqueue_style( 'rtl-xtra-front', plugin_dir_url(__FILE__) . 'css/rtl-xtra-front.css' );

	if ( wp_style_is( 'js_composer_front', 'registered' ) ) {
		wp_enqueue_style( 'rtl-js_composer_front', plugin_dir_url(__FILE__) . 'css/xtra_js_composer.min.css', [ 'js_composer_front' ] );
	}

}
add_action( 'wp_enqueue_scripts', 'xtra_add_rtl_xtra_front' );

// Admin Styles & Scripts.
function xtra_add_rtl_xtra_back() {
	wp_enqueue_style( 'cz-icons-farsi', plugin_dir_url(__FILE__) . 'icons/codevz-farsi.css' );
	wp_enqueue_style( 'rtl-xtra-back', plugin_dir_url(__FILE__) . 'css/rtl-xtra-back.css' );
}
add_action( 'admin_enqueue_scripts', 'xtra_add_rtl_xtra_back' );


// Farsi icons for codevz plus library.
function codevz_farsi_icons( $icons ) {
	array_unshift( $icons, plugin_dir_path( __FILE__ ) . 'icons/codevz-farsi.json' );
	return $icons;
}
add_filter( 'codevz/load/icons/json', 'codevz_farsi_icons' );

// Farsi icons for Elementor library.
function codevz_plus_elementor_icons_manager( $tabs = [] ) {

	if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {

		$tabs['codevz-farsi-icons'] = [
			'name'          => 'codevz-farsi-icons',
			'label'         => esc_html__( 'Persian icons', 'codevz-plus' ),
			'labelIcon'     => 'czi czico-xtra',
			'prefix'        => '',
			'displayPrefix' => 'fa',
			'url'           => plugin_dir_url(__FILE__) . 'icons/codevz-farsi.css',
			'fetchJson'     => plugin_dir_url(__FILE__) . 'icons/codevz-farsi.json',
			'ver'           => '1.0',
			'native'        => false
		];

	}
	return $tabs;
}
add_filter( 'elementor/icons_manager/additional_tabs', 'codevz_plus_elementor_icons_manager' );


// WPBakery.
function xtra_vc_backend_editor_enqueue_js_css() {
	wp_enqueue_style( 'rtl-js_composer', plugin_dir_url(__FILE__) . 'css/xtra_js_composer_backend_editor.min.css', [ 'js_composer' ] );
}
add_action( 'vc_backend_editor_enqueue_js_css', 'xtra_vc_backend_editor_enqueue_js_css' );

function xtra_vc_load_iframe_jscss() {
	wp_enqueue_style( 'rtl-js_composer_front', plugin_dir_url(__FILE__) . 'css/xtra_js_composer.min.css', [ 'js_composer_front' ] );
	wp_enqueue_style( 'rtl-vc_inline_css_iframe', plugin_dir_url(__FILE__) . 'css/xtra_js_composer_frontend_editor_iframe.min.css', [ 'vc_inline_css' ] );
}
add_action( 'vc_load_iframe_jscss', 'xtra_vc_load_iframe_jscss' );

function xtra_vc_frontend_editor_enqueue_js_css() {
	wp_enqueue_style( 'rtl-vc_inline_css', plugin_dir_url(__FILE__) . 'css/xtra_js_composer_frontend_editor.min.css', [ 'vc_inline_css' ] );
}
add_action( 'vc_frontend_editor_enqueue_js_css', 'xtra_vc_frontend_editor_enqueue_js_css' );


function xtra_revslider_data_get_font_familys( $fonts ) {

	$persian_fonts = [];

	//Web Safe fonts

	$persian_fonts[] = array('type' => 'websafe', 'version' => __('Persian Fonts', 'revslider'), 'label' => 'iranyekan');
	$persian_fonts[] = array('type' => 'websafe', 'version' => __('Persian Fonts', 'revslider'), 'label' => 'iranyekan_standard');
	$persian_fonts[] = array('type' => 'websafe', 'version' => __('Persian Fonts', 'revslider'), 'label' => 'IRANSans');
	$persian_fonts[] = array('type' => 'websafe', 'version' => __('Persian Fonts', 'revslider'), 'label' => 'IRANSans_standard');
	$persian_fonts[] = array('type' => 'websafe', 'version' => __('Persian Fonts', 'revslider'), 'label' => 'iransansdn');
	$persian_fonts[] = array('type' => 'websafe', 'version' => __('Persian Fonts', 'revslider'), 'label' => 'iransansdn_standard');
	$persian_fonts[] = array('type' => 'websafe', 'version' => __('Persian Fonts', 'revslider'), 'label' => 'Iran Kharazmi');
	$persian_fonts[] = array('type' => 'websafe', 'version' => __('Persian Fonts', 'revslider'), 'label' => 'Pelak');
	$persian_fonts[] = array('type' => 'websafe', 'version' => __('Persian Fonts', 'revslider'), 'label' => 'Pelak_standard');
	$persian_fonts[] = array('type' => 'websafe', 'version' => __('Persian Fonts', 'revslider'), 'label' => 'Javan');
	$persian_fonts[] = array('type' => 'websafe', 'version' => __('Persian Fonts', 'revslider'), 'label' => 'Azhdar');
	$persian_fonts[] = array('type' => 'websafe', 'version' => __('Persian Fonts', 'revslider'), 'label' => 'Azhdar_standard');
	$persian_fonts[] = array('type' => 'websafe', 'version' => __('Persian Fonts', 'revslider'), 'label' => 'mahboubeh_mehravar');
	$persian_fonts[] = array('type' => 'websafe', 'version' => __('Persian Fonts', 'revslider'), 'label' => 'Shabnam');
	$persian_fonts[] = array('type' => 'websafe', 'version' => __('Persian Fonts', 'revslider'), 'label' => 'Lalezar');
	$persian_fonts[] = array('type' => 'websafe', 'version' => __('Persian Fonts', 'revslider'), 'label' => 'broyabold');
	$persian_fonts[] = array('type' => 'websafe', 'version' => __('Persian Fonts', 'revslider'), 'label' => 'rezvan');
	$persian_fonts[] = array('type' => 'websafe', 'version' => __('Persian Fonts', 'revslider'), 'label' => 'khodkar');
	$persian_fonts[] = array('type' => 'websafe', 'version' => __('Persian Fonts', 'revslider'), 'label' => 'DastNevis');
	$persian_fonts[] = array('type' => 'websafe', 'version' => __('Persian Fonts', 'revslider'), 'label' => 'BTitrBold');
	$persian_fonts[] = array('type' => 'websafe', 'version' => __('Persian Fonts', 'revslider'), 'label' => 'BYekan');
	$persian_fonts[] = array('type' => 'websafe', 'version' => __('Persian Fonts', 'revslider'), 'label' => 'BZar');
	$persian_fonts[] = array('type' => 'websafe', 'version' => __('Persian Fonts', 'revslider'), 'label' => 'BSinaBold');
	$persian_fonts[] = array('type' => 'websafe', 'version' => __('Persian Fonts', 'revslider'), 'label' => 'BZiba');
	$persian_fonts[] = array('type' => 'websafe', 'version' => __('Persian Fonts', 'revslider'), 'label' => 'aviny');
	$persian_fonts[] = array('type' => 'websafe', 'version' => __('Persian Fonts', 'revslider'), 'label' => 'dana');
	$persian_fonts[] = array('type' => 'websafe', 'version' => __('Persian Fonts', 'revslider'), 'label' => 'Anjoman');
	$persian_fonts[] = array('type' => 'websafe', 'version' => __('Persian Fonts', 'revslider'), 'label' => 'Anjoman_standard');
	$persian_fonts[] = array('type' => 'websafe', 'version' => __('Persian Fonts', 'revslider'), 'label' => 'iransharp');
	$persian_fonts[] = array('type' => 'websafe', 'version' => __('Persian Fonts', 'revslider'), 'label' => 'IRAN');
	$persian_fonts[] = array('type' => 'websafe', 'version' => __('Persian Fonts', 'revslider'), 'label' => 'Kalameh');
	$persian_fonts[] = array('type' => 'websafe', 'version' => __('Persian Fonts', 'revslider'), 'label' => 'Farhang');
	$persian_fonts[] = array('type' => 'websafe', 'version' => __('Persian Fonts', 'revslider'), 'label' => 'Peyda');
	$persian_fonts[] = array('type' => 'websafe', 'version' => __('Persian Fonts', 'revslider'), 'label' => 'Damavand');
	$persian_fonts[] = array('type' => 'websafe', 'version' => __('Persian Fonts', 'revslider'), 'label' => 'Gohar');
	$persian_fonts[] = array('type' => 'websafe', 'version' => __('Persian Fonts', 'revslider'), 'label' => 'Ahang');
	$persian_fonts[] = array('type' => 'websafe', 'version' => __('Persian Fonts', 'revslider'), 'label' => 'Ahang Sharp');
	$persian_fonts[] = array('type' => 'websafe', 'version' => __('Persian Fonts', 'revslider'), 'label' => 'Borna');
	$persian_fonts[] = array('type' => 'websafe', 'version' => __('Persian Fonts', 'revslider'), 'label' => 'Capsule Black');
	$persian_fonts[] = array('type' => 'websafe', 'version' => __('Persian Fonts', 'revslider'), 'label' => 'Doran');
	$persian_fonts[] = array('type' => 'websafe', 'version' => __('Persian Fonts', 'revslider'), 'label' => 'Modam');
	$persian_fonts[] = array('type' => 'websafe', 'version' => __('Persian Fonts', 'revslider'), 'label' => 'Ravi');
	$persian_fonts[] = array('type' => 'websafe', 'version' => __('Persian Fonts', 'revslider'), 'label' => 'Yekan Bakh');
	
	return wp_parse_args( $fonts, $persian_fonts );
}
add_filter( 'revslider_data_get_font_familys', 'xtra_revslider_data_get_font_familys' );

function xtra_csf_fonts_library( $fonts ) {

	$persian_fonts = [];

	//Web Safe Fonts
	$persian_fonts['iranyekan'] = 'iranyekan';
	$persian_fonts['iranyekan_standard'] = 'iranyekan_standard';
	$persian_fonts['IRANSans'] = 'IRANSans';
	$persian_fonts['IRANSans_standard'] = 'IRANSans_standard';
	$persian_fonts['iransansdn'] = 'iransansdn';
	$persian_fonts['iransansdn_standard'] = 'iransansdn_standard';
	$persian_fonts['Iran Kharazmi'] = 'Iran Kharazmi';
	$persian_fonts['Pelak'] = 'Pelak';
	$persian_fonts['Javan'] = 'Javan';
	$persian_fonts['Azhdar'] = 'Azhdar';
	$persian_fonts['Azhdar_standard'] = 'Azhdar_standard';
	$persian_fonts['mahboubeh_mehravar'] = 'mahboubeh_mehravar';
	$persian_fonts['Shabnam'] = 'Shabnam';
	$persian_fonts['Lalezar'] = 'Lalezar';
	$persian_fonts['broyabold'] = 'broyabold';
	$persian_fonts['rezvan'] = 'rezvan';
	$persian_fonts['khodkar'] = 'khodkar';
	$persian_fonts['DastNevis'] = 'DastNevis';
	$persian_fonts['BTitrBold'] = 'BTitrBold';
	$persian_fonts['BYekan'] = 'BYekan';
	$persian_fonts['BZar'] = 'BZar';
	$persian_fonts['BSinaBold'] = 'BSinaBold';
	$persian_fonts['BZiba'] = 'BZiba';
	$persian_fonts['aviny'] = 'aviny';
	$persian_fonts['dana'] = 'dana';
	$persian_fonts['Anjoman'] = 'Anjoman';
	$persian_fonts['Anjoman_standard'] = 'Anjoman_standard';
	$persian_fonts['iransharp'] = 'iransharp';
	$persian_fonts['IRAN'] = 'IRAN';	
	$persian_fonts['Kalameh'] = 'Kalameh';
	$persian_fonts['Farhang'] = 'Farhang';
	$persian_fonts['Damavand'] = 'Damavand';
	$persian_fonts['Peyda'] = 'Peyda';
	$persian_fonts['Gohar'] = 'Gohar';
	$persian_fonts['Ahang'] = 'Ahang';
	$persian_fonts['Ahang Sharp'] = 'Ahang Sharp';
	$persian_fonts['Borna'] = 'Borna';
	$persian_fonts['Capsule Black'] = 'Capsule Black';
	$persian_fonts['Doran'] = 'Doran';
	$persian_fonts['Modam'] = 'Modam';
	$persian_fonts['Ravi'] = 'Ravi';
	$persian_fonts['Yekan Bakh'] = 'Yekan Bakh';
	

	return wp_parse_args( $fonts, $persian_fonts );
}
add_filter( 'codevz/field/fonts/websafe', 'xtra_csf_fonts_library' );

// Persian fonts for WP Editor.
function codevz_custom_tinymce_fonts( $fonts ) {

    $fonts .= 'iranyekan=iranyekan;iranyekan_standard=iranyekan_standard;IRANSans=IRANSans;IRANSans_standard=IRANSans_standard;iransansdn=iransansdn;iransansdn_standard=iransansdn_standard;Iran Kharazmi=Iran Kharazmi;Pelak=Pelak;Javan=Javan;Azhdar=Azhdar;Azhdar_standard=Azhdar_standard;mahboubeh_mehravar=mahboubeh_mehravar;Shabnam=Shabnam;Lalezar=Lalezar;broyabold=broyabold;rezvan=rezvan;khodkar=khodkar;DastNevis=DastNevis;BTitrBold=BTitrBold;BYekan=BYekan;BZar=BZar;BSinaBold=BSinaBold;BZiba=BZiba;aviny=aviny;dana=dana;Anjoman=Anjoman;Anjoman_standard=Anjoman_standard;iransharp=iransharp;IRAN=IRAN;Kalameh=Kalameh;Farhang=Farhang;Damavand=Damavand;Peyda=Peyda;Gohar=Gohar;Ahang=Ahang;Ahang Sharp=Ahang Sharp;Borna=Borna;Capsule Black=Capsule Black;Doran=Doran;Modam=Modam;Ravi=Ravi;Yekan Bakh=Yekan Bakh;';

    return $fonts;

}
add_filter( 'codevz_wp_editor_google_fonts', 'codevz_custom_tinymce_fonts' );

// Add fonts group elementor.
function codevz_elementor_fonts_group( $font_groups ) {
	$new_font_group = array( 'custom' => esc_html__( 'Custom', 'codevz' ) );
	return array_merge( $new_font_group, $font_groups );
}
add_filter( 'elementor/fonts/groups', 'codevz_elementor_fonts_group' );

// Add fonts list elementor.
function codevz_elementor_fonts( $additional_fonts ) {

    $additional_fonts['Iranyekan'] = 'custom';
    $additional_fonts['Iranyekan Standard'] = 'custom';
    $additional_fonts['IRANSans'] = 'custom';
    $additional_fonts['IRANSans Standard'] = 'custom';
    $additional_fonts['Iransans DN'] = 'custom';
    $additional_fonts['Iransans DN Standard'] = 'custom';
    $additional_fonts['Iran Kharazmi'] = 'custom';
    $additional_fonts['Pelak'] = 'custom';
    $additional_fonts['Javan'] = 'custom';
    $additional_fonts['Azhdar'] = 'custom';
    $additional_fonts['Azhdar Standard'] = 'custom';
    $additional_fonts['Mahboubeh Mehravar'] = 'custom';
    $additional_fonts['Shabnam'] = 'custom';
    $additional_fonts['Lalezar'] = 'custom';
    $additional_fonts['Broya Bold'] = 'custom';
    $additional_fonts['Rezvan'] = 'custom';
    $additional_fonts['Khodkar'] = 'custom';
    $additional_fonts['Dast Nevis'] = 'custom';
    $additional_fonts['BTitr Bold'] = 'custom';
    $additional_fonts['BYekan'] = 'custom';
    $additional_fonts['BZar'] = 'custom';
    $additional_fonts['BSina Bold'] = 'custom';
    $additional_fonts['BZiba'] = 'custom';
    $additional_fonts['Aviny'] = 'custom';
    $additional_fonts['Dana'] = 'custom';
    $additional_fonts['Anjoman'] = 'custom';
    $additional_fonts['Anjoman Standard'] = 'custom';
    $additional_fonts['IranSharp'] = 'custom';
    $additional_fonts['IRAN'] = 'custom';
    $additional_fonts['Kalameh'] = 'custom';
    $additional_fonts['Farhang'] = 'custom';
    $additional_fonts['Damavand'] = 'custom';
    $additional_fonts['Peyda'] = 'custom';
    $additional_fonts['Gohar'] = 'custom';
    $additional_fonts['Ahang'] = 'custom';
    $additional_fonts['Ahang Sharp'] = 'custom';
    $additional_fonts['Borna'] = 'custom';
    $additional_fonts['Capsule Black'] = 'custom';
    $additional_fonts['Doran'] = 'custom';
    $additional_fonts['Modam'] = 'custom';
    $additional_fonts['Ravi'] = 'custom';
    $additional_fonts['Yekan Bakh'] = 'custom';

    return $additional_fonts;
}
add_filter( 'elementor/fonts/additional_fonts', 'codevz_elementor_fonts' );
