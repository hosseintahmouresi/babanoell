<?php if ( ! defined( 'ABSPATH' ) ) {exit;} // Exit if accessed directly.

/**
 * Plugin Name: Codevz Plus
 * Plugin URI: 	https://xtratheme.ir/
 * Description: Exlusive plugin for elements, widgets, StyleKit, custom post types, options, and page builder features.
 * Version: 	5.3
 * Author: 		Codevz
 * Author URI: 	https://codevz.com/
 * Text Domain: codevz-plus
 * License: 	GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * 
 * Copyright (C) 2012 Codevz
*/

// Trait instance.
trait Codevz_Plus_Instance {

	protected static $instance = null;

	/**
	 * Get class instance.
	 *
	 * @return static
	 */
	public static function instance() {

		if ( static::$instance === null ) {
			static::$instance = new static();
		}

		return static::$instance;
	}

}

// Main class.
class Codevz_Plus {

	use Codevz_Plus_Instance;

	// Plugin version.
	public static $ver = '5.3';

	// Server API address.
	public static $api = 'https://xtratheme.ir/api/';

	// Plugin title.
	public static $title;

	// Directory.
	public static $dir;

	// Plugin URL.
	public static $url;

	// Cache post query.
	public static $post;

	// Check free mode.
	public static $is_free;

	// Cache free mode.
	public static $is_free_cache;

	// RTL mode.
	public static $is_rtl = false;

	// Customize mode.
	public static $preview = false;

	// Check administrator.
	public static $is_administrator = false;

	// Check admin pages.
	public static $is_admin = false;

	// Check WPBakery frontend.
	public static $vc_editable = false;

	// Get array list of pages.
	public static $array_pages = [];

	// Get old social icons.
	public static $social_fa_upgrade = [];

	// Core functionality.
	protected function __construct() {

		// Define
		self::$post 		= &$GLOBALS['post'];
		self::$vc_editable 	= ( self::_GET( 'vc_editable' ) || self::_GET( 'preview_id' ) || get_option( 'wpm_languages' ) );
		self::$is_admin 	= is_admin();
		self::$is_free 		= self::is_free();
		self::$dir 			= trailingslashit( plugin_dir_path( __FILE__ ) );
		self::$url 			= trailingslashit( plugin_dir_url( __FILE__ ) );

		// After plugin loaded.
		add_action( 'wp', [ $this, 'wp' ] );

		// Fix font awesome upgrade.
		self::$social_fa_upgrade = [ 'fa ', 'far ', 'fas ', 'fab ', 'fa-', 'fas-', 'far-', 'fab-', 'czico-', '-square', '-official', '-circle' ];

		// Required files.
		require_once( self::$dir . 'admin/codevz-framework.php' );
		require_once( self::$dir . 'classes/class-options.php' );
		require_once( self::$dir . 'classes/class-widgets.php' );
		require_once( self::$dir . 'classes/class-menu-walker.php' );
		require_once( self::$dir . 'classes/class-auto-update.php' );
		require_once( self::$dir . 'classes/class-woocommerce.php' );
		require_once( self::$dir . 'classes/class-duplicator.php' );
		require_once( self::$dir . 'classes/class-wpbakery.php' );
		require_once( self::$dir . 'classes/class-pwa.php' );
		require_once( self::$dir . 'rtl-xtra/rtl-xtra.php' );
		require_once( self::$dir . 'elementor/elementor.php' );
		require_once( self::$dir . 'elementor/elementor-templates.php' );

		// Check features.
		$disable = array_flip( (array) self::option( 'disable' ) );

		if ( self::option( 'white_label_exclude_admin' ) && self::$is_administrator ) {
			$disable = [];
		}

		// Demo importer.
		require_once( self::$dir . 'classes/class-demo-importer.php' );

		// Templates.
		if( ! isset( $disable['templates'] ) ) {
			require_once( self::$dir . 'classes/class-templates.php' );
		}

		// Force disable comments.
		if ( self::option( 'force_disable_comments' ) ) {
			add_filter( 'comments_open', '__return_false' );
		}

		// do_shortcode
		add_filter( 'widget_text', 'do_shortcode' );

		// Custom sidebars
		add_action( 'wp_ajax_codevz_custom_sidebars', [ $this, 'custom_sidebars' ] );

		// Custom default colors to WP Colorpicker
		add_action( 'admin_footer', [ $this, 'wp_color_palettes' ] );
		add_action( 'customize_controls_print_footer_scripts', [ $this, 'wp_color_palettes' ] );

		// Redirect maintenance page.
		add_filter( 'template_redirect', [ $this, 'template_redirect' ], 99 );

		// Ajax search result
		add_action( 'wp_ajax_codevz_ajax_search', [ $this, 'ajax_search' ] );
		add_action( 'wp_ajax_nopriv_codevz_ajax_search', [ $this, 'ajax_search' ] );

		// Post types query settings
		add_action( 'pre_get_posts', [ $this, 'pre_get_posts' ], 99 );

		// Advanced post type search in admin.
		add_action( 'admin_footer-edit.php', [ $this, 'advanced_cpt_search_admin' ], 10, 2 );

		// Actions and filters
		add_action( 'init', array( $this, 'init' ), 0 );
		add_action( 'init', array( $this, 'disable_emojis' ) );
		add_action( 'admin_init', array( $this, 'admin_init' ) );
		add_action( 'admin_notices', array( $this, 'admin_notices' ) );
		add_action( 'save_post', array( $this, 'save_post' ), 9999 );
		add_action( 'pre_update_option_codevz_theme_activation', array( $this, 'cta' ), 10, 2 );

		// Body custom classes
		add_filter( 'body_class', array( $this, 'body_class' ) );

		// Body custom classes for admin area.
		add_filter( 'admin_body_class', array( $this, 'admin_body_class' ) );

		// Head and footer
		add_action( 'wp_head', array( $this, 'wp_head' ) );
		add_action( 'wp_footer', array( $this, 'wp_footer' ) );

		// Body open.
		add_action( 'wp_body_open', [ $this, 'wp_body_open' ] );

		if ( ! isset( $disable['options'] ) ) {
			add_action( 'admin_bar_menu', array( $this, 'admin_bar_menu' ), 99 );
		}

		// Share icons.
		add_action( 'codevz/share', [ $this, 'share' ] );
		add_action( 'woocommerce_share', [ $this, 'share' ] );

		// Plugin white label.
		add_filter( 'all_plugins', [ $this, 'white_label' ] );

		// Disable autoptimize on page builder.
		add_filter( 'autoptimize_filter_noptimize', [ $this, 'vc_autoptimize' ] );

		// Disable wp-optimize on page builder.
		add_filter( 'wpo_minify_run_on_page', [ $this, 'wpo_minify_run_on_page' ], 11 );

		// Include tags to WordPress search query.
		add_filter( 'posts_search', [ $this, 'posts_search' ], 10, 2 );

	}

	// v5 temporary fix.
	public static function lazyload( $data = '' ) {
		return $data;
	}

	// On first load.
	public function wp() {

		self::$preview = is_customize_preview();

		self::$is_rtl = ( self::option( 'rtl' ) || is_rtl() || self::_GET( 'rtl' ) );

		self::$is_administrator = function_exists( 'current_user_can' ) && current_user_can( 'administrator' );

		// Force RTL mode with option.
		if ( self::option( 'rtl' ) && ! is_admin() ) {

			global $wp_locale;

			$wp_locale->text_direction = 'rtl';

		}

	}

	// Disable emojis.
	public static function disable_emojis() {

		remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
		remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
		remove_action( 'wp_print_styles', 'print_emoji_styles' );
		remove_action( 'admin_print_styles', 'print_emoji_styles' );
		remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
		remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
		remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );

	}

	/**
	 * Get page settings
	 * 
	 * @var $id = post id
	 * @var $key = array key
	 * 
	 * @return array|string|null
	 */
	public static function meta( $id = null, $key = null, $default = '' ) {

		// Post ID.
		if ( ! $id && isset( self::$post->ID ) ) {
			$id = self::$post->ID;
		}

		$meta = apply_filters( 'codevz/page/meta', get_post_meta( $id, 'codevz_page_meta', true ), $id );

		if ( $key ) {
			return empty( $meta[ $key ] ) ? $default : $meta[ $key ];
		} else {
			return ( $id && $meta ) ? $meta : $default;
		}

	}

	/**
	 * Get theme options
	 * 
	 * @var 	$key = option name
	 * @var 	$default = default value
	 * 
	 * @return 	array|string|null
	 */
	public static function option( $key = '', $default = '' ) {

		$options = apply_filters( 'codevz/options', get_option( 'codevz_theme_options' ) );

		return empty( $key ) ? $options : apply_filters( 'codevz/option/' . $key, ( empty( $options[ $key ] ) ? $default : $options[ $key ] ) );

	}

	/**
	 * Get and return current page URL param.
	 * 
	 * @return string|null
	 */
	public static function _GET( $key ) {

		return esc_html( filter_input( INPUT_GET, $key ) );

	}

	/**
	 * Get and return current page request param.
	 * 
	 * @return string|null
	 */
	public static function _POST( $key ) {

		return esc_html( filter_input( INPUT_POST, $key ) );

	}

	/**
	 * Get and return current page saved cookie request param.
	 * 
	 * @return String|null
	 */
	public static function _COOKIE( $key ) {

		return esc_html( filter_input( INPUT_COOKIE, $key ) );

	}

	/**
	 * Get WordPress database object.
	 * 
	 * @return Object
	 */
	public static function database() {

		return $GLOBALS[ 'wpdb' ];

	}

	/**
	 * Check if base screen is needed for admin enqueue.
	 * 
	 * @return Object
	 */
	public static function admin_enqueue() {

		$screen = get_current_screen();
		$bases  = [ 'customize', 'widgets', 'nav-menus', 'post', 'edit-tags', 'term' ];

		if ( self::contains( $screen->base, 'codevz-theme-options' ) ) {
			return 'codevz-theme-options';
		}

		return in_array( $screen->base, $bases ) ? $screen->base : false;

	}

	/**
	 * WP_Filesystem for theme usage.
	 * 
	 * @return object
	 */
	public static function wpfs() {

		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require ABSPATH . 'wp-admin/includes/file.php';
		}

		global $wp_filesystem;

		WP_Filesystem();

		return $wp_filesystem;

	}

	/**
	 * Get page ID by page title.
	 * 
	 * @return string|null
	 */
	public static function get_page_by_title( $page_title, $post_types = [ 'page', 'elementor_library', 'elementskit_template' ] ) {

		// Unique cache key for this query
		$cache_key = 'codevz_plus_page_id_' . md5( $page_title . serialize( $post_types ) );

		// Check transient cache
		$page_id = get_transient( $cache_key );
		if ( false !== $page_id ) {
			return $page_id;
		}

		// Prepare DB query
		$db = self::database();
		$post_types_str = "'" . implode( "','", array_map( 'esc_sql', $post_types ) ) . "'";

		$sql = $db->prepare(
			"
			SELECT ID
			FROM $db->posts
			WHERE post_title = %s
			AND post_type IN ($post_types_str)
			ORDER BY ID ASC
			LIMIT 1
		",
		$page_title
		);

		// Run query
		$page_id = $db->get_var( $sql );

		// Cache the result for 1 minutes
		set_transient( $cache_key, $page_id, 60 );

		return $page_id;

	}

	/**
	 * Check free and pro version.
	 * 
	 * @return bool
	 */
	public static function is_free( $is_free = false ) {

		// Cache.
		if ( self::$is_free_cache ) {

			return self::$is_free_cache;

		}

		// Purchase code.
		$code = get_option( 'codevz_theme_activation' );
		$code = isset( $code[ 'purchase_code' ] ) ? $code[ 'purchase_code' ] : '';

		// Not registered.
		if ( empty( $code ) ) {

			self::$is_free_cache = true;

			return true;

		}

		// Check code contains invalid characters.
		$invalid = self::contains( $code, [ '*', 'free', 'xxx', 'AAAA', 'websama', 'sama', '63007' ] );

		// Check and return.
		if ( $invalid || ctype_upper( $code ) || strlen( $code ) < 5 ) {

			delete_option( 'codevz_theme_activation' );

			self::$is_free_cache = true;

			return true;

		}

		self::$is_free_cache = false;

		return false;

	}

	/**
	 * Get pro badge.
	 * 
	 * @return string
	 */
	public static function pro_badge( $link = true ) {

		if ( $link ) {

			return '<a href="' . esc_url( get_admin_url() ) . 'admin.php?page=theme-activation" data-tooltip="' . esc_html__( 'Activate your theme with purchase code to access this feature.', 'codevz-plus' ) . '" target="_blank" class="xtra-pro"><span">' . esc_html__( 'PRO', 'codevz-plus' ) . '</span></a>';

		} else {

			return '<span class="xtra-pro"><span">' . esc_html__( 'PRO', 'codevz-plus' ) . '</span></span>';

		}

	}

	/**
	 * Get pro message.
	 * 
	 * @return string
	 */
	public static function pro_message( $prefix = '' ) {

		return is_user_logged_in() ? '<div class="xtra-inactive-notice"><span>' . ( $prefix ? $prefix . ', ' : '' ) . esc_html__( 'Activate your theme to access all PRO features.', 'codevz-plus' ) . '</span></div>' : '';

	}

	// Send email function.
	public static function sendMail( $email = '', $subject = '', $message = '', $headers = '' ) {

		return wp_mail( $email, $subject, $message, $headers );

	}

	public static function iframe( $src = '', $width = '', $height = '' ) {

		return '<iframe src="' . $src . '" width="' . $width . '" height="' . $height . '"></iframe>';

	}

	/**
	 * Add social share icons to post, page and products.
	 * 
	 * @return String
	 */
	public static function share() {

		$share = self::option( 'share' );
		$post_type = array_flip( (array) self::option( 'post_type' ) );

		if ( empty( $share ) || ! isset( $post_type[ self::get_post_type() ] ) ) {
			return false;
		}

		$classes = 'cz_social xtra-share';
		$classes .= self::option( 'share_color' ) ? ' ' . self::option( 'share_color' ) : '';
		$classes .= self::option( 'share_title' ) ? ' cz_social_inline_title' : '';

		$tooltip = self::option( 'share_tooltip' );
		$classes .= $tooltip ? ' cz_tooltip cz_tooltip_up' : '';

		$post_id 	= get_the_id();
		$post_title = get_the_title();
		$post_link  = get_the_permalink();
		$post_link  = get_the_permalink();

		$url = [

			'facebook-f' => [
				'title' => esc_html__( 'Facebook', 'codevz-plus' ),
				'url' 	=> 'https://facebook.com/share.php?u=' . $post_link . '&title=' . $post_title
			],
			'twitter' => [
				'title' => esc_html__( 'X', 'codevz-plus' ),
				'url' 	=> 'https://x.com/intent/tweet?text=' . $post_title . '+' . $post_link
			],
			'pinterest' => [
				'title' => esc_html__( 'Pinterest', 'codevz-plus' ),
				'url' 	=> 'https://pinterest.com/pin/create/bookmarklet/?media=' . get_the_post_thumbnail_url( $post_id, 'full' ) . '&url=' . $post_link . '&is_video=false&description=' . $post_title
			],
			'reddit' => [
				'title' => esc_html__( 'Reddit', 'codevz-plus' ),
				'url' 	=> 'https://reddit.com/submit?url=' . $post_link . '&title=' . $post_title
			],
			'delicious' => [
				'title' => esc_html__( 'Delicious', 'codevz-plus' ),
				'url' 	=> 'https://del.icio.us/post?url=' . $post_link . '&title=' . $post_title . '&notes=' . wp_strip_all_tags( wp_trim_words( do_shortcode( get_post_field( 'post_content', $post_id ) ), 25, '...' ) )
			],
			'linkedin' => [
				'title' => esc_html__( 'Linkedin', 'codevz-plus' ),
				'url' 	=> 'https://linkedin.com/shareArticle?mini=true&url=' . $post_link . '&title=' . $post_title . '&source=' . $post_link
			],
			'whatsapp' => [
				'title' => esc_html__( 'Whatsapp', 'codevz-plus' ),
				'url' 	=> 'whatsapp://send?text=' . $post_title . ' ' . $post_link
			],
			'telegram' => [
				'title' => esc_html__( 'Telegram', 'codevz-plus' ),
				'url' 	=> 'https://telegram.me/share/url?url=' . $post_link . '&text=' . $post_title
			],
			'envelope' => [
				'title' => esc_html__( 'Email', 'codevz-plus' ),
				'url' 	=> 'mailto:?body=' . $post_title . ' ' . $post_link
			],
			'print' => [
				'title' => esc_html__( 'Print', 'codevz-plus' ),
				'url' 	=> '#'
			],
			'copy' => [
				'title' => esc_html__( 'Shortlink', 'codevz-plus' ),
				'url' 	=> wp_get_shortlink( $post_id )
			],

		];

		echo '<div class="clr mb10"></div>';

		$data = self::option( 'share_box_title' ) ? ' data-title="' . esc_attr( do_shortcode( self::option( 'share_box_title' ) ) ) . '"' : '';

		echo '<div class="' . esc_attr( $classes ) . '"' . wp_kses_post( (string) $data ) . '>';

		if ( self::$preview ) {
			echo '<i class="codevz-section-focus fas fa-cog" data-section="share"></i>';
		}

		// Echo share icons.
		foreach( $share as $name ) {

			$name = ( $name === 'facebook' ) ? 'facebook-f' : $name;

			if ( isset( $url[ $name ] ) ) {

				$title_prefix = ( self::contains( $name, [ 'envelope', 'whatsapp', 'telegram' ] ) ) ? esc_html__( 'Share by', 'codevz-plus' ) : esc_html__( 'Share on', 'codevz-plus' );
				$title_prefix = ( self::contains( $name, [ 'copy' ] ) ) ? esc_html__( 'Copy', 'codevz-plus' ) . ' ' : $title_prefix;
				$title_prefix = ( $name === 'print' ) ? '' : $title_prefix;
				$icon_prefix = ( $name === 'envelope' || $name === 'print' ) ? 'fa' : 'fab';
				$icon_prefix = ( $name === 'copy' ) ? 'far' : $icon_prefix;
				$custom_data = ( $name === 'copy' ) ? ' data-copied="' . esc_html__( 'Link copied', 'codevz-plus' ) . '"' : '';

				$ico = ( $name === 'twitter' ) ? $icon_prefix . ' fa-x-twitter' : $icon_prefix . ' fa-' . $name;

				echo '<a href="' . esc_attr( $url[ $name ]['url'] ) . '" rel="noopener noreferrer nofollow" class="cz-' . esc_attr( ( ( $name === 'twitter' ) ? 'x-' : '' ) . $name ) . '" ' . ( $tooltip ? 'data-' : '' ) . 'title="' . esc_attr( $title_prefix . ' ' . $url[ $name ]['title'] ) . '" aria-label="' . esc_attr( $title_prefix . ' ' . $url[ $name ]['title'] ) . '"' . wp_kses_post( (string) $custom_data ) . '><i class="' . esc_attr( $ico ) . '"></i><span>' . esc_html( $url[ $name ]['title'] ) . '</span></a>';

			}

		}

		echo '</div>';

	}

	/**
	 * Disable autoptimize on page builder.
	 * 
	 * @return boolean
	 */
	public function vc_autoptimize() {

		return self::_GET( 'vc_editable' );

	}

	// Fix WPO for bakery.
	public function wpo_minify_run_on_page( $value ) {

		if ( self::$vc_editable ) {

			return false;

		}

		return $value;

	}

	/**
	 * New shortcut menus to WP admin bar
	 * @var object of WP admin bar
	 * @return object
	 */
	public static function admin_bar_menu( $i ) {

		if ( ! is_user_logged_in() ) {
			return false;
		}

		$admin = get_admin_url();
		$customize = $admin . 'customize.php?url=' . esc_url( $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] ) . '&';

		$i->add_node(array(
			'id' 	=> 'codevz_menu',
			'title' => esc_html__( 'Theme Options', 'codevz-plus' ), 
			'href' 	=> $customize
		));
		$i->add_node(array(
			'parent'=> 'codevz_menu',
			'id' 	=> 'codevz_menu_quick',
			'title' => esc_html__( 'Options without Preview', 'codevz-plus' ), 
			'href' 	=> $customize . '%2F%3Fcodevz_quick_options%3D1&'
		));
		$i->add_node(array(
			'parent'=> 'codevz_menu',
			'id' 	=> 'codevz_menu_classic',
			'title' => esc_html__( 'Classic Theme Options', 'codevz-plus' ), 
			'href' 	=> $admin . 'admin.php?page=codevz-theme-options'
		));
		$i->add_node(
			[
				'parent' 	=> 'codevz_menu',
				'id' 		=> 'codevz_menu_hr_1',
				'title' 	=> '<hr style="border:0; display: block; background: #444; height: 1px; top: 13px; position: relative;" />',
				'href' 		=> ''
			]
		);
		$i->add_node(array(
			'parent'=> 'codevz_menu',
			'id' 	=> 'codevz_menu_demos',
			'title' => esc_html__( 'Demo Importer', 'codevz-plus' ), 
			'href' 	=> $admin . 'admin.php?page=theme-importer',
		));
		$i->add_node(array(
			'parent'=> 'codevz_menu',
			'id' 	=> 'codevz_menu_favicon',
			'title' => esc_html__( 'Site Favicon', 'codevz-plus' ), 
			'href' 	=> $customize . 'autofocus[section]=title_tagline',
		));
		$i->add_node(array(
			'parent'=> 'codevz_menu',
			'id' 	=> 'codevz_menu_layout',
			'title' => esc_html__( 'Layout', 'codevz-plus' ), 
			'href' 	=> $customize . 'autofocus[section]=codevz_theme_options-layout',
		));
		$i->add_node(array(
			'parent'=> 'codevz_menu',
			'id' 	=> 'codevz_menu_colors',
			'title' => esc_html__( 'Theme Colors', 'codevz-plus' ), 
			'href' 	=> $customize . 'autofocus[section]=codevz_theme_options-styling',
		));
		$i->add_node(array(
			'parent'=> 'codevz_menu',
			'id' 	=> 'codevz_menu_typography',
			'title' => esc_html__( 'Typography', 'codevz-plus' ), 
			'href' 	=> $customize . 'autofocus[section]=codevz_theme_options-typography',
		));
		$i->add_node(array(
			'parent'=> 'codevz_menu',
			'id' 	=> 'codevz_menu_logo',
			'title' => esc_html__( 'Site Logo', 'codevz-plus' ), 
			'href' 	=> $customize . 'autofocus[control]=codevz_theme_options[logo]',
		));
		$i->add_node(array(
			'parent'=> 'codevz_menu',
			'id' 	=> 'codevz_menu_header',
			'title' => esc_html__( 'Header', 'codevz-plus' ), 
			'href' 	=> $customize . 'autofocus[section]=codevz_theme_options-header',
		));
		$i->add_node(array(
			'parent'=> 'codevz_menu',
			'id' 	=> 'codevz_menu_mobile_header',
			'title' => esc_html__( 'Mobile Header', 'codevz-plus' ), 
			'href' 	=> $customize . 'autofocus[section]=codevz_theme_options-mobile_header',
		));
		$i->add_node(array(
			'parent'=> 'codevz_menu',
			'id' 	=> 'codevz_menu_title',
			'title' => esc_html__( 'Title & Breadcrumbs', 'codevz-plus' ), 
			'href' 	=> $customize . 'autofocus[section]=codevz_theme_options-title_br',
		));
		$i->add_node(array(
			'parent'=> 'codevz_menu',
			'id' 	=> 'codevz_menu_back_to_top',
			'title' => esc_html__( 'Back to top icon', 'codevz-plus' ), 
			'href' 	=> $customize . 'autofocus[section]=codevz_theme_options-footer_more',
		));
		$i->add_node(array(
			'parent'=> 'codevz_menu',
			'id' 	=> 'codevz_menu_footer',
			'title' => esc_html__( 'Footer', 'codevz-plus' ), 
			'href' 	=> $customize . 'autofocus[section]=codevz_theme_options-footer',
		));
		$i->add_node(array(
			'parent'=> 'codevz_menu',
			'id' 	=> 'codevz_menu_copyright',
			'title' => esc_html__( 'Copyright text', 'codevz-plus' ), 
			'href' 	=> $customize . 'autofocus[section]=codevz_theme_options-footer_2',
		));
		$i->add_node(array(
			'parent'=> 'codevz_menu',
			'id' 	=> 'codevz_menu_posts',
			'title' => esc_html__( 'Blog Settings', 'codevz-plus' ), 
			'href' 	=> $customize . 'autofocus[section]=codevz_theme_options-posts',
		));
		$i->add_node(array(
			'parent'=> 'codevz_menu',
			'id' 	=> 'codevz_menu_portfolio',
			'title' => esc_html__( 'Portfolio Settings', 'codevz-plus' ), 
			'href' 	=> $customize . 'autofocus[section]=codevz_theme_options-portfolio',
		));
		$i->add_node(array(
			'parent'=> 'codevz_menu',
			'id' 	=> 'codevz_menu_product',
			'title' => esc_html__( 'WooCommerce', 'codevz-plus' ), 
			'href' 	=> $customize . 'autofocus[section]=codevz_theme_options-product',
		));
		$i->add_node(array(
			'parent'=> 'codevz_menu',
			'id' 	=> 'codevz_menu_custom_css',
			'title' => esc_html__( 'Additional CSS', 'codevz-plus' ), 
			'href' 	=> $customize . 'autofocus[section]=custom_css',
		));

		$mt = self::option( 'maintenance_mode' );
		if ( $mt && $mt !== 'none' ) {
			$i->add_node(array(
				'id' 	=> 'codevz_menu_maintenance',
				'title' => esc_html__( 'Maintenance mode is ON', 'codevz-plus' ), 
				'href' 	=> $customize . 'autofocus[control]=codevz_theme_options[maintenance_mode]',
			));
		}
		
		$i->remove_menu( 'customize' );
	}

	/**
	 * Body Classes for admin area.
	 * 
	 * @return string
	 */
	public function admin_body_class( $c = [] ) {

		if ( self::is_free() ) {

			$c .= ' codevz-plus-free';

		}

		return $c;

	}

	/**
	 * Body Classes
	 * 
	 * @return array
	 */
	public static function body_class( $c = [] ) {

		// Theme version.
		$theme = wp_get_theme();
		$c[] = 'theme-' . ( empty( $theme->parent() ) ? $theme->get( 'Version' ) : $theme->parent()->Version );

		// Plugins version.
		$c[] = 'codevz-plus-' . self::$ver;

		// Post type class
		$cpt = self::get_post_type();
		$cpt = $cpt ? $cpt : get_post_type();
		$cpt = ( ! $cpt || $cpt === 'page' || is_search() ) ? 'post' : $cpt;
		if ( $cpt ) {

			$c[] = 'cz-cpt-' . $cpt;

			// Woo single
			if ( $cpt === 'product' && is_single() ) {

				$tabs = self::option( 'woo_product_tabs' );
				if ( $tabs ) {
					$c[] = 'woo-product-tabs-' . $tabs;
				}

				if ( in_array( 'lightbox', (array) self::option( 'woo_gallery_features' ) ) ) {
					$c[] = 'woo-disable-lightbox';
				}

				if ( self::option( 'woo_product_tabs_sticky' ) && $tabs !== 'vertical' ) {
					$c[] = 'codevz-sticky-product-tabs';
				}

				$c[] = self::option( 'woo_summary_layout' );

			}

			if ( self::option( $cpt . '_custom_single_sk' ) ) {
				$c[] = 'single-' . $cpt . '-sk';
			}

		}

		// Woocommerce general.
		$c[] = self::option( 'woo_two_col_mobile' ) ? 'xtra-woo-two-col-mobile' : '';
		$c[] = self::option( 'woo_sold_out_grayscale' ) ? 'cz-outofstock-grayscale' : '';
		$c[] = self::option( 'woo_product_title_single_line' ) ? 'cz-products-short-title' : '';
		$c[] = self::option( 'woo_sale_percentage' );

		if ( $cpt === 'product' ) {
			$c[] = self::option( 'woo_widgets_toggle' );
		}

		// RTL
		if ( self::$is_rtl ) {

			$c[] = self::$is_rtl ? 'rtl' : '';

			$c[] = self::option( 'disable_rtl_numbers' ) ? 'codevz-disable-rtl-numbers' : '';

		}

		// Menus hover effects.
		$c[] = self::option( 'menus_hover_effect_opacity' ) ? 'codevz-menus-opacity' : '';
		$menus_effect = self::option( 'menus_hover_effect' );
		$c[] = $menus_effect ? $menus_effect : '';
		$menus_dropdown_effect = self::option( 'menus_dropdown_effect' );
		$c[] = $menus_dropdown_effect ? $menus_dropdown_effect : '';
		$menus_dropdown_items_effect = self::option( 'menus_dropdown_items_effect' );
		$c[] = $menus_dropdown_items_effect ? $menus_dropdown_items_effect : '';

		// Focus overlay header vs content.
		$c[] = self::option( 'dropdown_overlay' ) ? 'codevz-plus-dropdown-overlay' : '';

		// Sticky
		$c[] = self::option( 'sticky' ) ? 'cz_sticky' : '';

		// Carousel custom SVG cursor.
		$c[] = self::option( 'carousel_svg_cursor' ) ? 'codevz-carousel-cursor' : '';

		// Disable lightbox
		$c[] = self::option( 'disable_lightbox' ) ? 'xtra-disable-lightbox' : '';

		// Magic mouse hide default cursor.
		if ( self::option( 'magic_mouse' ) ) {
			$c[] = self::option( 'magic_mouse_hide_cursor' ) ? 'xtra-hide-cursor' : '';
			$c[] = self::option( 'magic_mouse_invert' ) ? 'xtra-magic-mouse-invert' : '';
		}

		// No fade on load.
		$c[] = self::option( 'first_load_fade' ) ? 'codevz-first-load-fade' : '';

		// Elementor classes.
		if ( did_action( 'elementor/loaded' ) && get_option( 'elementor_experiment-container' ) != 'inactive' ) {
			$c[] = 'cz-elementor-container';
		}

		// Fix
		$c[] = 'clr';

		// Page ID
		if ( get_the_id() ) {
			$c[] = 'cz-page-' . ( is_home() ? get_option( 'page_for_posts' ) : get_the_id() );
		}

		return $c;
	}

	/**
	 * wp_head
	 * 
	 * @return string
	 */
	public static function wp_head() {

		// Disable automatic telephone link for mobile.
		echo '<meta name="format-detection" content="telephone=no">';

		// Mobile theme color.
		echo '<meta name="theme-color" content="' . esc_html( self::option( 'site_color' ) ) . '">';

		// SEO meta tags
		if ( ! self::$vc_editable && self::option( 'seo_meta_tags' ) && ! defined( 'WPSEO_VERSION' ) ) {

			$title = $desc = $tags = '';

			if ( is_single() || is_page() ) {
				$url = get_the_permalink();
				$title = get_the_title();
				$desc = self::meta( false, 'seo_desc' );
				if ( ! $desc ) {
					$desc = self::$post->post_content;
					$desc = $desc ? wp_trim_words( do_shortcode( wp_strip_all_tags( $desc ) ), 30 ) : $title;
					$desc = preg_replace( '/(<style[^>]*>.+?<\/style>|<script[^>]*>.+?<\/script>)/ms', '', $desc );
				}
				$tags = self::meta( false, 'seo_keywords' );
				$tags = $tags ? $tags : rtrim( wp_strip_all_tags( str_replace( '</a>', ',', get_the_tag_list() ) ), ',' );
				$image = get_the_post_thumbnail_url();
				echo $image ? '<meta property="og:image" content="' . esc_url( $image ) . '" />' . "\n" : '';
			} else {
				global $wp;
				$url = trailingslashit( get_home_url() ) . $wp->request;
			}

			$title = $title ? $title : get_bloginfo( 'name' );

			if ( is_front_page() || ! $desc ) {
				$desc = self::option( 'seo_desc', get_bloginfo( 'description' ) );
			}

			$desc = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $desc ) ) );
			$tags = $tags ? $tags : self::option( 'seo_keywords' );

			if ( $desc && $title ) {

				echo '<meta property="og:title" content="' . esc_attr( $title ) . '" />' . "\n";
				echo $url ? '<meta property="og:url" content="' . esc_url( $url ) . '" />' . "\n" : '';
				echo '<meta name="description" content="' . esc_html( $desc ) . '" />' . "\n";
				echo $tags ? '<meta name="keywords" content="' . esc_html( $tags ) . '" />' . "\n" : '';
				echo '<meta property="og:description" content="' . esc_html( $desc ) . '" />' . "\n";
				echo '<meta property="og:type" content="website" />' . "\n";

			}

		}

		// Custom header codes
		echo do_shortcode( str_replace( '&', '&amp;', self::option( 'head_codes' ) ) );
	}

	/**
	 * Add content after body tag open.
	 * 
	 * @return string
	 */
	public function wp_body_open() {

		$top_banner = self::option( 'top_banner' );
		$top_banner_always = self::option( 'top_banner_always' );

		if ( ( ! isset( $_COOKIE[ 'codevz_top_banner' ] ) || $top_banner_always ) && $top_banner ) {

			echo '<div class="codevz-top-banner' . esc_attr( $top_banner_always ? ' codevz-top-banner-always' : '' ) . '">';

			echo '<div class="codevz-top-banner-inner">';

			if ( $top_banner === 'simple' ) {

				echo wp_kses_post( (string) do_shortcode( self::option( 'top_banner_content', '...' ) ) );

			} else {

				$top_banner = self::get_page_as_element( esc_html( $top_banner ) );

				echo '<div class="codevz-top-banner-template">' . do_shortcode( $top_banner ) . '</div>';

			}

			echo '</div>';

			echo $top_banner_always ? '' : '<i class="' . esc_attr( self::option( 'top_banner_icon', 'fa czico-198-cancel' ) ) . '" aria-hidden="true"></i>';

			echo '</div>';

		}

		// Magic mouse curzr.
		$mouse = self::option( 'magic_mouse' );

		if ( $mouse && $mouse != '1' ) {

			if ( $mouse === '7' ) {

				echo '<svg class="curzr curzr-7" hidden><filter id="motionblur" x="-100%" y="-100%" width="400%" height="400%"><feGaussianBlur class="curzr-motion-blur" stdDeviation="0, 0"/></filter><circle cx="50%" cy="50%" r="10" fill="' . esc_attr( self::option( 'magic_mouse_curzr_color_1', '#676767' ) ) . '" filter="url(#motionblur)" /></svg>';

			} else {

				echo '<div class="curzr curzr-' . esc_attr( $mouse ) .'" data-color-1="' . esc_attr( self::option( 'magic_mouse_curzr_color_1' ) ) . '" data-color-2="' . esc_attr( self::option( 'magic_mouse_curzr_color_2' ) ) . '" hidden>';

				if ( $mouse === '2' ) {
					echo '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32"><path class="inner" d="M25,30a5.82,5.82,0,0,1-1.09-.17l-.2-.07-7.36-3.48a.72.72,0,0,0-.35-.08.78.78,0,0,0-.33.07L8.24,29.54a.66.66,0,0,1-.2.06,5.17,5.17,0,0,1-1,.15,3.6,3.6,0,0,1-3.29-5L12.68,4.2a3.59,3.59,0,0,1,6.58,0l9,20.74A3.6,3.6,0,0,1,25,30Z" fill="' . esc_attr( self::option( 'magic_mouse_curzr_color_2', 'transparent' ) ) . '" /><path class="outer" d="M16,3A2.59,2.59,0,0,1,18.34,4.6l9,20.74A2.59,2.59,0,0,1,25,29a5.42,5.42,0,0,1-.86-.15l-7.37-3.48a1.84,1.84,0,0,0-.77-.17,1.69,1.69,0,0,0-.73.16l-7.4,3.31a5.89,5.89,0,0,1-.79.12,2.59,2.59,0,0,1-2.37-3.62L13.6,4.6A2.58,2.58,0,0,1,16,3m0-2h0A4.58,4.58,0,0,0,11.76,3.8L2.84,24.33A4.58,4.58,0,0,0,7,30.75a6.08,6.08,0,0,0,1.21-.17,1.87,1.87,0,0,0,.4-.13L16,27.18l7.29,3.44a1.64,1.64,0,0,0,.39.14A6.37,6.37,0,0,0,25,31a4.59,4.59,0,0,0,4.21-6.41l-9-20.75A4.62,4.62,0,0,0,16,1Z" fill="' . esc_attr( self::option( 'magic_mouse_curzr_color_1', '#676767' ) ) . '" /></svg>';
				} else if ( $mouse === '3' ) {
					echo '<div class="curzr-dot"></div>';
				} else if ( $mouse === '4' ) {
					echo '<div class="circle"></div><div class="dot"></div>';
				}

				echo '</div>';

			}

		}

	}

	/**
	 * Site footer.
	 * 
	 * @return string
	 */
	public function wp_footer() {

		// Back to top
		echo self::option( 'backtotop' ) ? '<i class="' . esc_attr( self::option( 'backtotop' ) ) . ' backtotop">' . ( self::$preview ? '<i class="codevz-section-focus fas fa-cog" data-section="footer_more"></i>' : '' ) . '</i>' : '';

		// Quick contact
		$cf7 = self::option( 'cf7_beside_backtotop' );

		if ( $cf7 ) {

			$cf7 = self::get_page_as_element( esc_html( $cf7 ) );

			$cf7_link = self::option( 'cf7_beside_backtotop_link' );

			$icon = '<i class="' . esc_attr( self::option( 'cf7_beside_backtotop_icon', 'fa fa-envelope-o' ) ) . ' fixed_contact">' . ( self::$preview ? '<i class="codevz-section-focus fas fa-cog" data-section="footer_more"></i>' : '' ) . '</i>';

			if ( $cf7_link && ! $cf7 ) {

				echo '<a href="' . esc_attr( $cf7_link ) . '" target="_blank" aria-label="Contact">' . wp_kses_post( (string) $icon ) . '</a>';

			} else if ( $cf7 ) {

				echo wp_kses_post( (string) $icon );

			}

			if ( $cf7 ) {
				echo '<div class="fixed_contact">' . do_shortcode( $cf7 ) . '</div>';
			}
		}

		// Popup
		$popup = self::get_page_as_element( esc_html( self::option( 'popup' ) ) );

		if ( $popup ) {
			echo '<div class="cz-pages-popup hidden">' . do_shortcode( $popup ) . '</div>';
		}

		echo '<div class="cz_fixed_top_border"></div><div class="cz_fixed_bottom_border"></div>';

		// Fixed mobile HTML.
		self::mobile_fixed_navigation();

		$preview = is_customize_preview();

		// Cookie notice.
		if ( empty( $_COOKIE[ 'xtra_cookie' ] ) || $preview ) {

			$custom_cookie = empty( $GLOBALS[ 'xtra_cookie' ] ) ? false : $GLOBALS[ 'xtra_cookie' ];

			$cookie = self::option( 'cookie', esc_html( $custom_cookie ) );

			if ( $cookie  || $preview ) {

				if ( $custom_cookie && ! empty( $GLOBALS[ 'xtra_cookie_content' ] ) ) {

					$content = wp_kses_post( (string) $GLOBALS[ 'xtra_cookie_content' ] );

				} else {

					$content = self::option( 'cookie_content', esc_html__( 'This website uses cookies to improve user experience.', 'codevz-plus' ) );

				}

				$button = self::option( 'cookie_button', esc_html__( 'Accept and close', 'codevz-plus' ) );

				echo '<div class="xtra-cookie ' . esc_attr( $cookie ) . '">';

				if ( self::$preview ) {
					echo '<i class="codevz-section-focus fas fa-cog" data-section="cookie"></i>';
				}

				echo '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 50 50"><path d="M 25 4 C 13.413593 4 4 13.413593 4 25 C 4 36.586407 13.413593 46 25 46 C 36.586407 46 46 36.586407 46 25 C 46 24.378171 45.959166 23.781074 45.910156 23.203125 A 1.0001 1.0001 0 0 0 44.376953 22.443359 C 43.826171 22.794789 43.19 23 42.5 23 C 41.016987 23 39.768891 22.083144 39.253906 20.791016 A 1.0001 1.0001 0 0 0 37.849609 20.28125 C 37.002044 20.739741 36.035672 21 35 21 C 31.674438 21 29 18.325562 29 15 C 29 13.964328 29.260259 12.997956 29.71875 12.150391 A 1.0001 1.0001 0 0 0 29.208984 10.746094 C 27.916856 10.231109 27 8.983013 27 7.5 C 27 6.81 27.205211 6.1738294 27.556641 5.6230469 A 1.0001 1.0001 0 0 0 26.796875 4.0898438 C 26.218926 4.0408304 25.621829 4 25 4 z M 38 4 A 2 2 0 0 0 36 6 A 2 2 0 0 0 38 8 A 2 2 0 0 0 40 6 A 2 2 0 0 0 38 4 z M 25 6 C 25.142822 6 25.291705 6.0168744 25.435547 6.0214844 C 25.28505 6.5181777 25 6.9539065 25 7.5 C 25 9.4370135 26.137695 10.982725 27.660156 11.960938 C 27.267841 12.905947 27 13.91642 27 15 C 27 19.406438 30.593562 23 35 23 C 36.08358 23 37.094054 22.732159 38.039062 22.339844 C 39.017275 23.862305 40.562986 25 42.5 25 C 43.046093 25 43.481823 24.71495 43.978516 24.564453 C 43.983126 24.708295 44 24.857178 44 25 C 44 35.505593 35.505593 44 25 44 C 14.494407 44 6 35.505593 6 25 C 6 14.494407 14.494407 6 25 6 z M 36.5 12 A 1.5 1.5 0 0 0 35 13.5 A 1.5 1.5 0 0 0 36.5 15 A 1.5 1.5 0 0 0 38 13.5 A 1.5 1.5 0 0 0 36.5 12 z M 21.5 15 A 1.5 1.5 0 0 0 20 16.5 A 1.5 1.5 0 0 0 21.5 18 A 1.5 1.5 0 0 0 23 16.5 A 1.5 1.5 0 0 0 21.5 15 z M 45 15 A 1 1 0 0 0 44 16 A 1 1 0 0 0 45 17 A 1 1 0 0 0 46 16 A 1 1 0 0 0 45 15 z M 15 20 A 3 3 0 0 0 12 23 A 3 3 0 0 0 15 26 A 3 3 0 0 0 18 23 A 3 3 0 0 0 15 20 z M 24.5 24 A 1.5 1.5 0 0 0 23 25.5 A 1.5 1.5 0 0 0 24.5 27 A 1.5 1.5 0 0 0 26 25.5 A 1.5 1.5 0 0 0 24.5 24 z M 17 31 A 2 2 0 0 0 15 33 A 2 2 0 0 0 17 35 A 2 2 0 0 0 19 33 A 2 2 0 0 0 17 31 z M 30.5 32 A 2.5 2.5 0 0 0 28 34.5 A 2.5 2.5 0 0 0 30.5 37 A 2.5 2.5 0 0 0 33 34.5 A 2.5 2.5 0 0 0 30.5 32 z"></path></svg>';

				echo '<span>' . do_shortcode( wp_kses_post( (string) $content ) ) . '</span>';
				echo '<a href="#" class="xtra-cookie-button" aria-label="Close">' . do_shortcode( wp_kses_post( (string) $button ) ) . '</a>';
				echo '</div>';

			}

		}

		// Custom footer codes.
		echo do_shortcode( str_replace( '&', '&amp;', self::option( 'foot_codes' ) ) );

	}

	/**
	 * Reset code.
	 */
	public static function cta( $new_value, $old_value ) {

		delete_transient( 'codevz_lcr' );
		delete_option( 'codevz_lc_dismiss' );

		return $new_value;

	}

	/**
	 * Mobile fixed navigation icons.
	 * 
	 * @return string
	 */
	public static function mobile_fixed_navigation() {

		if ( self::option( 'mobile_fixed_navigation' ) ) {

			$classes = self::option( 'mobile_fixed_navigation_title' );
			$classes .= self::option( 'mobile_fixed_navigation_svg' ) ? ' xtra-fixed-mobile-nav-svg' : '';

			echo '<div class="xtra-fixed-mobile-nav ' . esc_attr( $classes ) . '">';

			if ( self::$preview ) {
				echo '<i class="codevz-section-focus fas fa-cog" data-section="mobile_fixed_navigation"></i>';
			}

			$items = (array) self::option( 'mobile_fixed_navigation_items' );

			$n = 0;

			// Items.
			foreach( $items as $item ) {

				$item = wp_parse_args( $item,
					[
						'title' 		=> '',
						'icon_type' 	=> '',
						'icon' 			=> '',
						'image' 		=> '',
						'image_size' 	=> '',
						'link' 			=> '',
						'target' 		=> '',
					]
				);

				$icon = '';

				if ( $item[ 'icon_type' ] === 'icon' ) {

					$icon = '<i class="' . esc_attr( do_shortcode( $item['icon'] ) ) . '"></i>';

				} else if ( $item[ 'icon_type' ] === 'image' ) {

					$size = $item[ 'image_size' ] ? ' style="width: ' . esc_attr( $item[ 'image_size' ] ) . '"' : '';
					$icon = '<img loading="lazy" src="' . esc_attr( do_shortcode( $item['image'] ) ) . '" alt="mobile-nav"' . $size . ' />';

				}

				$target = $item[ 'target' ] ? ' target="_blank"' : '';

				$out = '<a href="' . esc_url( do_shortcode( $item['link'] ) ) . '" title="' . esc_attr( do_shortcode( $item['title'] ) ) . '"' . wp_kses_post( $target ) . '>' . wp_kses_post( (string) $icon ) . '<span>' . wp_kses_post( (string) do_shortcode( $item['title'] ) ) . '</span></a>';

				// Special plus expandable menu.
				if ( self::option( 'mobile_fixed_navigation_e' ) && self::option( 'mobile_fixed_navigation_e_position', 2 ) == $n ) {

					$offset = self::option( 'mobile_fixed_navigation_e_icon_offset' );
					$offset = $offset ? ' style="top:' . esc_attr( $offset ) . '"' : '';

					$icon = self::option( 'mobile_fixed_navigation_e_icon', 'far fa-plus' );

					echo '<div class="' . esc_attr( $icon ) . ' xtra-fixed-mobile-nav-e" data-icon="' . esc_attr( $icon ) . '"' . wp_kses_post( $offset ) . '><div>';

					$inner_items = (array) self::option( 'mobile_fixed_navigation_e_items' );

					foreach( $inner_items as $inner_item ) {

						$inner_item = wp_parse_args( $inner_item,
							[
								'title' 		=> '',
								'link' 			=> '',
							]
						);

						echo '<a href="' . esc_url( do_shortcode( $inner_item['link'] ) ) . '" title="' . esc_attr( do_shortcode( $inner_item['title'] ) ) . '"><span>' . wp_kses_post( (string) do_shortcode( $inner_item['title'] ) ) . '</span></a>';

					}

					echo '</div></div>';

				}

				echo do_shortcode( $out );

				$n++;

			}

			echo '</div>';

		}

	}

	/**
	 * Admin init for editing page content under GET.
	 */
	public function admin_init() {

		// Dismiss check.
		if ( isset( $_GET['codevz_dismiss_notice'] ) ) {
			update_option( 'codevz_lc_dismiss', time() + WEEK_IN_SECONDS );
		}

		// Fix for elementor loading issue.
		if ( ! get_option( 'elementor_editor_break_lines' ) ) {

			update_option( 'elementor_editor_break_lines', true );

		}

		// Fix for elementor SVG icons.
		if ( get_option( 'elementor_experiment-e_font_icon_svg' ) != 'inactive' ) {

			update_option( 'elementor_experiment-e_font_icon_svg', 'inactive' );

		}

		// Disable emojis.
		self::disable_emojis();

		// Check edit page link.
		$page_id = self::_GET( 'codevz_edit_content' );

		if ( $page_id ) {

			if ( ! is_numeric( $page_id ) ) {

				$page_id = self::get_page_by_title( $page_id );

				if ( ! $page_id ) {

					wp_die( 'Error, page ID not found.' );

				}

			}

			$post_content = get_post_field( 'post_content', $page_id );

			// Elementor.
			if ( get_post_meta( $page_id, '_elementor_edit_mode', true ) ) {

				$edit_url = admin_url( 'post.php?action=elementor&post=' . $page_id );

			// WPBakery.
			} else if ( strpos( $post_content, 'vc_row' ) !== false || strpos( $post_content, 'wpb_wrapper' ) !== false ) {

				$edit_url = admin_url( 'post.php?vc_action=vc_inline&post_id=' . $page_id );

			} else {

				// WordPress.
				$edit_url = admin_url( 'post.php?action=edit&post=' . $page_id );

			}

			// Redirect.
			wp_redirect( $edit_url . '&post_type=' . get_post_type( $page_id ) );

			exit();

		}

	}

	/**
	 * Showing admin notice.
	 * 
	 * @return string
	 */
	public function admin_notices() {

		global $pagenow;

		// Handle the form submission to dismiss the notice.
		if ( self::_POST( 'codevz_page_builders_notice' ) ) {

			set_transient( 'codevz_page_builders_notice', true, MONTH_IN_SECONDS );

		// Check if both Elementor and WPBakery Page Builder are active
		} else if ( ! get_transient( 'codevz_page_builders_notice' ) && class_exists( 'Elementor\Plugin' ) && function_exists( 'vc_map' ) ) {
			?>
			<div class="notice notice-warning is-dismissible" id="page-builders-notice">
				<p>
					<strong><?php esc_html_e( 'Notice:', 'codevz-plus' ); ?></strong> <?php esc_html_e( 'Both Elementor and WPBakery Page Builder are active. For better site speed and performance, it\'s recommended to keep only one page builder activate at a time.', 'codevz-plus' ); ?>
				</p>
				<form method="post">
					<input type="hidden" name="codevz_page_builders_notice" value="1" />
					<button type="submit" class="notice-dismiss">
						<span class="screen-reader-text">Dismiss this notice</span>
					</button>
				</form>
			</div>
			<?php
		}

	}

	/**
	 * Get shortcode from page ID + Generate styles
	 * 
	 * @var post ID
	 * @return string
	 */
	public static function get_page_as_element( $id = '', $query = false ) {

		// Escape
		$id = esc_html( $id );

		// Check
		if ( ! $id ) {
			return;
		}

		// Check number and 404.
		if ( ! is_numeric( $id ) || $id === '404' ) {

			$id = self::get_page_by_title( $id );

			if ( ! $id ) {
				return;
			}

		}

		$status = get_post_status( $id );

		// If post not exist or its same page.
		if ( ! $status || $status === 'inherit' || is_page( $id ) ) {
			return;
		}

		// WPML compatible
		if ( function_exists( 'icl_object_id' ) ) {
			$id = icl_object_id( $id, 'page', true, ICL_LANGUAGE_CODE );
		}

		// Elementor.
		if ( get_post_meta( $id, '_elementor_edit_mode', true ) && did_action( 'elementor/loaded' ) ) {

			// Prevent same page load.
			if ( get_the_ID() == $id ) {
				return;
			}

			// Return page builder content.
			return \Elementor\Plugin::instance()->frontend->get_builder_content( $id, true );

		}

		// Get post content by ID
		$o = get_post_field( 'post_content', $id );

		// Fix posts grid
		if ( $query ) {
			$o = str_replace( 'query=""', 'query="1"', $o );
		}
		
		// Get post meta
		$s = get_post_meta( $id, '_wpb_shortcodes_custom_css', 1 ) . get_post_meta( $id, 'cz_sc_styles', 1 ) . get_post_meta( $id, 'codevz_single_page_css', 1 );

		// Responsive page builder tablet styles
		$tablet = get_post_meta( $id, 'cz_sc_styles_tablet', 1 );
		if ( $tablet ) {
			if ( substr( $tablet, 0, 1 ) === '@' ) {
				$s .= $tablet;
			} else {
				$s .= '@media screen and (max-width:' . self::option( 'tablet_breakpoint', '768px' ) . '){' . $tablet . '}';
			}
		}

		// Responsive page builder mobile styles
		$mobile = get_post_meta( $id, 'cz_sc_styles_mobile', 1 );
		if ( $mobile ) {
			if ( substr( $mobile, 0, 1 ) === '@' ) {
				$s .= $mobile;
			} else {
				$s .= '@media screen and (max-width:' . self::option( 'mobile_breakpoint', '480px' ) . '){' . $mobile . '}';
			}
		}

		// Output
		if ( ! is_page( $id ) ) {
			$o = "<div data-cz-style='" . esc_attr( preg_replace( "/(.cz-page-)(.*)[{]/", "{", $s ) ) . "'>" . do_shortcode( $o ) . "</div>";
		} else {
			return;
		}

		return $o;
		
	}

	/**
	 * Get current post type name
	 * 
	 * @return string
	 */
	public static function get_post_type( $id = '', $page = false ) {

		if ( is_search() || is_tag() || is_404() ) {
			$cpt = '';
		} else if ( function_exists( 'is_bbpress' ) && is_bbpress() ) {
			$cpt = 'bbpress';
		} else if ( function_exists( 'is_woocommerce' ) && ( is_shop() || is_woocommerce() ) ) {
			$cpt = 'product';
		} else if ( function_exists( 'is_buddypress' ) && is_buddypress() ) {
			$cpt = 'buddypress';
		} else if ( ( ! $page && get_post_type( $id ) ) || is_singular() ) {
			$cpt = get_post_type( $id );
		} else if ( is_tax() ) {
			$term = get_term_by( 'slug', get_query_var( 'term' ), get_query_var( 'taxonomy' ) );
			if ( get_taxonomy( $term->taxonomy ) ) {
				$cpt = get_taxonomy( $term->taxonomy )->object_type[0];
			}
		} else if ( is_post_type_archive() ) {
			$cpt = get_post_type_object( get_query_var( 'post_type' ) )->name;
		} else {
			$cpt = 'post';
		}

		return $cpt;
	}

	/**
	 * WPBakery animation settings to elements.
	 * 
	 * @return object
	 */
	public static function wpb_animation_tab( $setting = false ) {
		return class_exists( 'WPBakeryShortCodesContainer' ) ? vc_map_add_css_animation( $setting ) : false;
	}

	/**
	 * WordPress init
	 * 
	 * @return object
	 */
	public function init() {

		// RV compatibility, Only run for Polylang.
		if ( function_exists( 'pll_languages_list' ) ) { 

			add_action( 'wpml_loaded', '__return_true', 10, 0 );
			do_action( 'wpml_loaded' );

		}

		// Load translation only if the admin language is NOT English OR if it's the frontend.
		load_textdomain( 'codevz-plus', self::$dir . 'languages/codevz-plus-' . ( is_admin() ? get_user_locale() : get_locale() ) . '.mo' );

		// Plugin title.
		self::$title = esc_html__( 'Codevz plus elements', 'codevz-plus' );

		// Strings.
		$plugin_name = esc_html__( 'Codevz Plus', 'codevz-plus' );
		$description = esc_html__( 'StyleKit, custom post types, options and page builder elements.', 'codevz-plus' );

		// Get list of all pages as array.
		if( self::$is_admin || self::$preview ) {

			self::$array_pages = [
				'' => esc_html__( '~ Default ~', 'codevz-plus' )
			];

			$pages = get_posts( [
				'post_type' 		=> [ 'page', 'elementor_library', 'elementskit_template' ],
				'posts_per_page' 	=> -1
			] );

			foreach( $pages as $page ) {
				if ( isset( $page->post_title ) && $page->post_title ) {
					self::$array_pages[ $page->post_title ] = $page->post_title;
				}
			}

		}

		// Menu locations.
		register_nav_menus(
			[
				'primary' 	=> esc_html__( 'Primary', 'codevz-plus' ), 
				'one-page' 	=> esc_html__( 'One Page', 'codevz-plus' ), 
				'secondary' => esc_html__( 'Secondary', 'codevz-plus' ), 
				'footer'  	=> esc_html__( 'Footer', 'codevz-plus' ),
				'mobile'  	=> esc_html__( 'Mobile', 'codevz-plus' ),
				'custom-1' 	=> esc_html__( 'Custom 1', 'codevz-plus' ), 
				'custom-2' 	=> esc_html__( 'Custom 2', 'codevz-plus' ), 
				'custom-3' 	=> esc_html__( 'Custom 3', 'codevz-plus' ), 
				'custom-4' 	=> esc_html__( 'Custom 4', 'codevz-plus' ), 
				'custom-5' 	=> esc_html__( 'Custom 5', 'codevz-plus' ), 
				'custom-6' 	=> esc_html__( 'Custom 6', 'codevz-plus' ), 
				'custom-7' 	=> esc_html__( 'Custom 7', 'codevz-plus' ), 
				'custom-8' 	=> esc_html__( 'Custom 8', 'codevz-plus' )
			]
		);

		// Register CPTs
		self::post_types();

		// Enqueue and register plugin assets
		add_action( 'wp_enqueue_scripts', array( $this, 'wp_enqueue_scripts' ) );

		// Fix WPBakery builder.
		if ( ! self::_POST( 'vc_inline' ) ) {

			add_action( 'wp_enqueue_scripts', array( $this, 'wp_enqueue_scripts_page_builder' ), 9999 );

		}

		// Admin assets for StyleKit and Theme colors for palettes
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
		add_action( 'elementor/editor/before_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );

		// Custom JS/CSS for VC popup box
		add_action( 'vc_edit_form_fields_after_render', array( $this, 'vc_edit_form_fields_after_render' ) );

		// Enable some features for WP Editor
		add_filter( 'mce_buttons_2', array( $this, 'mce_buttons_2' ) );

		// Customize some features of WP Editor.
		add_filter( 'tiny_mce_before_init', array( $this, 'tiny_mce_before_init' ) );

		// New Params for WPBalery.
		if ( function_exists( 'vc_add_shortcode_param' ) ) {

			vc_add_shortcode_param( 'cz_title', array( $this, 'vc_param_cz_title' ) );
			vc_add_shortcode_param( 'cz_sc_id', array( $this, 'vc_param_cz_sc_id' ) );
			vc_add_shortcode_param( 'cz_hidden', array( $this, 'vc_param_cz_hidden' ) );
			vc_add_shortcode_param( 'cz_sk', array( $this, 'vc_param_cz_sk' ) );
			vc_add_shortcode_param( 'cz_upload', array( $this, 'vc_param_cz_upload' ) );
			vc_add_shortcode_param( 'cz_icon', array( $this, 'vc_param_cz_icon' ) );
			vc_add_shortcode_param( 'cz_image_select', array( $this, 'vc_param_image_select' ) );
			vc_add_shortcode_param( 'cz_slider', array( $this, 'vc_param_cz_slider' ) );

		} else {

			// For non-wpbakery page builders, add shortcodes to WordPress.
			$elements = [ 'button', 'particles', 'title', 'countdown', 'login_register', 'posts', 'social_icons', 'stylish_list', 'popup', 'working_hours', 'gallery', 'carousel', 'subscribe', 'separator_2', 'show_more_less' ];

			foreach( $elements as $i ) {
				require_once( Codevz_Plus::$dir . 'wpbakery/' . $i . '.php' );
				$class = 'Codevz_WPBakery_' . $i;
				$new_class = new $class( 'cz_' . $i );
				$new_class->in();
			}

		}

		// Filter for moving animation param into new tab Animation.
		add_filter( 'vc_map_add_css_animation', array( $this, 'vc_map_add_css_animation' ) );

		// Useful shortcodes
		add_shortcode( 'br', array( $this, 'br' ) );
		add_shortcode( 'cz_lang', array( $this, 'shortcode_translate' ) );
		add_shortcode( 'codevz_year', array( $this, 'shortcode_get_current_year' ) );
		add_shortcode( 'cz_current_year', array( $this, 'shortcode_get_current_year' ) );
		add_shortcode( 'cz_google_font', array( $this, 'shortcode_google_font' ) );

		// Add loop animations to vc animations list
		add_filter( 'vc_param_animation_style_list', array( $this, 'vc_param_animation_style_list' ) );

	}

	/**
	 * Extract shortcode atts according to shortcode_atts and vc_map function.
	 * 
	 * @return array
	 */
	public static function shortcode_atts( $element, $atts = [] ) {

		$params = [];
		$vc_map = $element->in();

		if ( ! empty( $vc_map[ 'params' ] ) ) {

			foreach ( $vc_map[ 'params' ] as $param ) {

				if ( isset( $param['param_name'] ) && 'content' !== $param['param_name'] ) {

					$value = '';

					if ( isset( $param['type'] ) && 'checkbox' === $param['type'] ) {

						$value = false;

					} else if ( isset( $param['std'] ) ) {

						$value = $param['std'];

					} elseif ( isset( $param['value'] ) ) {

						if ( is_array( $param['value'] ) ) {

							$value = current( $param['value'] );

							if ( is_array( $value ) ) {

								$value = current( $value );
							}

						} else {

							$value = $param['value'];

						}

					}

					$params[ $param['param_name'] ] = $value;

				}

			}

		}

		return shortcode_atts( $params, $atts, $element->name );

	}

	/**
	 * WPBakery custom params
	 */
	public static function vc_param_cz_title( $s, $v ) {
		$c = empty( $s['class'] ) ? '' : ' class="' . $s['class'] . '"';
		$u = empty( $s['url'] ) ? '' : '<a href="' . $s['url'] . '" target="_blank">';
		return $u . '<h4' . $c . '>' . $s['content'] . '</h4>' . ( $u ? '</a>' : '' ) . '<input type="hidden" name="' . $s['param_name'] . '" class="wpb_vc_param_value ' . $s['param_name'] . ' '.$s['type'].'_field" value="'.$v.'" />';
	}

	public static function vc_param_cz_sc_id( $s, $v ) {
		return '<input type="hidden" name="' . $s['param_name'] . '" class="wpb_vc_param_value ' . $s['param_name'] . ' '.$s['type'].'_field" value="'.$v.'" />';
	}

	public static function vc_param_cz_hidden( $s, $v ) {
		return '<input type="hidden" name="' . $s['param_name'] . '" class="wpb_vc_param_value ' . $s['param_name'] . ' '.$s['type'].'_field" value="'.$v.'" />';
	}

	public static function vc_param_cz_sk( $s, $v ) {
		$hover = isset( $s['hover_id'] ) ? ' data-hover_id="' . $s['hover_id'] . '"' : '';
		$out = '<div class="cz_sk clr"><input type="hidden" name="'. $s['param_name'] . '"' . $hover . ' value="' . $v . '" class="codevz-onload wpb_vc_param_value ' . esc_attr( $s['param_name'] ) .' '. esc_attr( $s['type'] ) . '" data-selector="' . ( isset( $s['selector'] ) ? $s['selector'] : '' ) . '" data-fields="' . implode( ' ', $s['settings'] ) . '" />';

		$is_active = $v ? ' active_stylekit' : '';

		$bg = '';
		if ( self::contains( $v, 'http' ) ) {
			preg_match_all( '/(http|https):\/\/[^ ]+(\.gif|\.jpg|\.jpeg|\.png)/', $v, $img );
			$bg = isset( $img[0][0] ) ? ' style="background-image:url(' . $img[0][0] . ')"' : '';
		}

		$out .= '<a href="#" class="button cz_sk_btn' . $is_active . '"><span class="cz_skico cz_skico_vc"></span>' . $s['button'] . '</a><div class="sk_btn_preview_image"' . $bg . '></div></div>';

		return $out;
	}

	public static function vc_param_cz_upload( $s, $v ) {

		$f = array(
			'id'    => esc_attr( $s['param_name'] ),
			'name'  => esc_attr( $s['param_name'] ),
			'type'  => 'upload',
			'title' => '',
			'attributes' => array(
				'class' => 'codevz-onload wpb_vc_param_value '.esc_attr( $s['param_name'] ) .' '. esc_attr( $s['type'] ).''
			),
			'settings'   => array(
				'upload_type'  => esc_attr( $s['upload_type'] ),
				'frame_title'  => 'Upload / Select',
				'insert_title' => 'Insert',
			),
		);

		if ( function_exists('codevz_add_field') ) {
			return '<div class="codevz-onload">' . codevz_add_field( $f, $v ) . '</div>';
		} else {
			return '<div class="my_param_block">'
				.'<input name="' . esc_attr( $s['param_name'] ) . '" class="wpb_vc_param_value wpb-textinput ' .
				esc_attr( $s['param_name'] ) . ' ' .
				esc_attr( $s['type'] ) . '_field" type="text" value="' . esc_attr( $v ) . '" />' .
				'</div>';
		}

	}

	public static function vc_param_cz_icon( $s, $v ) {

		$f = array(
			'id'    => esc_attr( $s['param_name'] ),
			'name'  => esc_attr( $s['param_name'] ),
			'type'  => 'icon',
			'title' => '',
			'after'	=> '<input type="hidden" name="'.$s['param_name'].'" class="wpb_vc_param_value '.$s['param_name'].' '.$s['type'].'_field" value="'.$v.'" />',
			'attributes' => array(
				'class' => 'codevz-onload wpb_vc_param_value '.esc_attr( $s['param_name'] ) .' '. esc_attr( $s['type'] ).''
			),
		);

		if ( function_exists('codevz_add_field') ) {
			return '<div class="codevz-onload">' . codevz_add_field( $f, $v ) . '</div>';
		} else {
			return '<div class="my_param_block">'
				.'<input name="' . esc_attr( $s['param_name'] ) . '" class="wpb_vc_param_value wpb-textinput ' .
				esc_attr( $s['param_name'] ) . ' ' .
				esc_attr( $s['type'] ) . '_field" type="text" value="' . esc_attr( $v ) . '" />' .
				'</div>';
		}

	}

	public static function vc_param_image_select( $s, $v ) {

		$f = array(
			'id'    => esc_attr( $s['param_name'] ),
			'name'  => esc_attr( $s['param_name'] ),
			'type'  => 'image_select',
			'options' => isset( $s['options'] ) ? $s['options'] : [],
			'radio' => true,
			'title' => '',
			'after'	=> '<input type="hidden" name="' . $s['param_name'] . '" class="wpb_vc_param_value ' . $s['param_name'] . ' '.$s['type'].'_field" value="'.$v.'" />',
			'attributes' => array(
				'class' 			=> 'codevz-onload',
				'data-depend-id' 	=> esc_attr( $s['param_name'] )
			),
		);

		if ( function_exists('codevz_add_field') ) {
			return '<div class="codevz-onload">' . codevz_add_field( $f, $v ) . '</div>';
		} else {
			return '<div class="my_param_block">'
				.'<input name="' . esc_attr( $s['param_name'] ) . '" class="wpb_vc_param_value wpb-textinput ' .
				esc_attr( $s['param_name'] ) . ' ' .
				esc_attr( $s['type'] ) . '_field" type="text" value="' . esc_attr( $v ) . '" />' .
				'</div>';
		}

	}

	public static function vc_param_cz_slider( $s, $v ) {

		$f = array(
			'id'    => esc_attr( $s['param_name'] ),
			'name'  => esc_attr( $s['param_name'] ),
			'type'  => 'slider',
			'options' => isset( $s['options'] ) ? $s['options'] : array( 'unit' => 'px', 'step' => 1, 'min' => 0, 'max' => 120 ),
			'title' => '',
			'after'	=> '<input type="hidden" name="'.$s['param_name'].'" class="wpb_vc_param_value '.$s['param_name'].' '.$s['type'].'_field" value="'.$v.'" />',
			'attributes' => array(
				'class' => 'codevz-onload wpb_vc_param_value '.esc_attr( $s['param_name'] ) .' '. esc_attr( $s['type'] ).''
			),
		);

		if ( function_exists('codevz_add_field') ) {
			return '<div class="codevz-onload">' . codevz_add_field( $f, $v ) . '</div>';
		} else {
			return '<div class="my_param_block">'
				.'<input name="' . esc_attr( $s['param_name'] ) . '" class="wpb_vc_param_value wpb-textinput ' .
				esc_attr( $s['param_name'] ) . ' ' .
				esc_attr( $s['type'] ) . '_field" type="text" value="' . esc_attr( $v ) . '" />' .
				'</div>';
		}

	}

	/**
	 * Enqueue and register plugin assets
	 * 
	 * @return string
	 */
	public static function wp_enqueue_scripts() {
		
		if ( is_admin() ) {
			return;
		}

		$name = 'codevz-plus';

		// Plugin JS.
		wp_enqueue_script( $name, self::$url . 'assets/js/codevzplus.js', [ 'jquery' ], self::$ver, true );

		if ( is_rtl() && ! self::option( 'disable_rtl_numbers' ) ) {
			wp_enqueue_script( 'codevz-plus-rtl', self::$url . 'assets/js/codevzplus.rtl.js', [ 'jquery' ], self::$ver, true );
		}

		// bbpress.
		if ( function_exists( 'is_bbpress' ) ) {
			wp_enqueue_style( 'codevz-plus-bbpress', self::$url . 'assets/css/bbpress.css', [ $name ], self::$ver );
		}

		// EDD.
		if ( function_exists( 'EDD' ) ) {

			wp_enqueue_style( $name . '-edd', self::$url . 'assets/css/edd.css', [ $name ], self::$ver );

			if ( self::$is_rtl ) {
				wp_enqueue_style( $name . '-edd-rtl', self::$url . 'assets/css/edd.rtl.css', [ 'codevz-plus-edd' ], self::$ver );
			}

		}

		// Soundmanager.
		wp_register_script( 'codevz-soundmanager', 	self::$url . 'assets/soundmanager/script/soundmanager.js', [ $name ], self::$ver, true );
		wp_register_script( 'codevz-bar-ui', 		self::$url . 'assets/soundmanager/script/bar-ui.js', [ $name ], self::$ver, true );
		wp_register_style(  'codevz-bar-ui', 		self::$url . 'assets/soundmanager/css/bar-ui.css', [], self::$ver );

		// Titl.
		wp_register_script( 'codevz-tilt', 			self::$url . 'assets/js/tilt.js', [ $name ], self::$ver, true );
		wp_register_style(  'codevz-tilt', 			self::$url . 'assets/css/tilt.css', [], self::$ver );

		// Share.
		if ( self::option( 'share' ) ) {
			wp_enqueue_script( 'codevz-plus-share', 		self::$url . 'assets/js/share.js', [ $name ], self::$ver, true );
			wp_enqueue_style(  'codevz-plus-share', 		self::$url . 'assets/css/share.css', [], self::$ver );
		}

		// Mobile fixed nav.
		if ( self::option( 'mobile_fixed_navigation' ) ) {
			wp_enqueue_script( 'codevz-mobile-fixed-nav', self::$url . 'assets/js/mobile-nav.js', [ $name ], self::$ver, true );
			wp_enqueue_style(  'codevz-mobile-fixed-nav', self::$url . 'assets/css/mobile-nav.css', [], self::$ver );
		}

		// Lenis smooth scroll.
		if ( self::option( 'smooth_scroll' ) ) {
			wp_enqueue_script( 'lenis', self::$url . 'assets/js/lenis.min.js', [ $name ], self::$ver, true );
		}

		// Parallax.
		wp_register_script( 'cz_parallax', 		self::$url . 'assets/js/parallax.js', [ $name ], self::$ver, true );
		wp_register_style(  'cz_parallax', 		self::$url . 'assets/css/parallax.css', [], self::$ver );

		// Elements scripts.
		wp_register_script( 'codevz-tooltip', 		self::$url . 'assets/js/tooltips.js', [ $name ], self::$ver, true );
		wp_register_script( 'codevz-modernizer', 	self::$url . 'assets/js/modernizer.js', [ $name ], self::$ver, true );

		wp_register_script( 'cz_text_marquee', 		self::$url . 'wpbakery/assets/js/text_marquee.js', [ $name ], self::$ver, true );
		wp_register_script( 'cz_working_hours', 	self::$url . 'wpbakery/assets/js/working_hours.js', [ $name ], self::$ver, true );
		wp_register_script( 'cz_before_after', 		self::$url . 'wpbakery/assets/js/before_after.js', [ $name ], self::$ver, true );
		wp_register_script( 'cz_tabs', 				self::$url . 'wpbakery/assets/js/tabs.js', [ $name ], self::$ver, true );
		wp_register_script( 'cz_show_more_less', 	self::$url . 'wpbakery/assets/js/show_more_less.js', [ $name ], self::$ver, true );
		wp_register_script( 'cz_counter', 			self::$url . 'wpbakery/assets/js/counter.js', [ $name ], self::$ver, true );
		wp_register_script( 'cz_progress_bar', 		self::$url . 'wpbakery/assets/js/progress_bar.js', [ $name ], self::$ver, true );
		wp_register_script( 'cz_accordion', 		self::$url . 'wpbakery/assets/js/accordion.js', [ $name ], self::$ver, true );
		wp_register_script( 'cz_google_map', 		self::$url . 'wpbakery/assets/js/google_map.js', [ $name ], self::$ver, true );
		wp_register_script( 'cz_image_hover_zoom', 	self::$url . 'wpbakery/assets/js/image_hover_zoom.js', [ $name ], self::$ver, true );
		wp_register_script( 'cz_content_box', 		self::$url . 'wpbakery/assets/js/content_box.js', [ $name ], self::$ver, true );
		wp_register_script( 'cz_countdown', 		self::$url . 'wpbakery/assets/js/countdown.js', [ $name ], self::$ver, true );
		wp_register_script( 'cz_video_popup', 		self::$url . 'wpbakery/assets/js/video_popup.js', [ $name ], self::$ver, true );
		wp_register_script( 'cz_team', 				self::$url . 'wpbakery/assets/js/team.js', [ $name ], self::$ver, true );
		wp_register_script( 'cz_title', 			self::$url . 'wpbakery/assets/js/title.js', [ $name ], self::$ver, true );
		wp_register_script( 'cz_login_register', 	self::$url . 'wpbakery/assets/js/login_register.js', [ $name ], self::$ver, true );
		wp_register_script( 'cz_separator', 		self::$url . 'wpbakery/assets/js/separator.js', [ $name ], self::$ver, true );
		wp_register_script( 'cz_popup', 			self::$url . 'wpbakery/assets/js/popup.js', [ $name ], self::$ver, true );
		wp_register_script( 'cz_free_position_element', self::$url . 'wpbakery/assets/js/free_position_element.js', [ $name ], self::$ver, true );
		wp_register_script( 'cz_360_degree', 		self::$url . 'wpbakery/assets/js/360_degree.js', [ $name ], self::$ver, true );
		wp_register_script( 'cz_animated_text', 	self::$url . 'wpbakery/assets/js/animated_text.js', [ $name ], self::$ver, true );
		wp_register_script( 'cz_particles', 		self::$url . 'wpbakery/assets/js/particles.js', [ $name ], self::$ver, true );
		wp_register_script( 'cz_carousel', 			self::$url . 'wpbakery/assets/js/carousel.js', [ $name ], self::$ver, true );
		wp_register_script( 'cz_gallery', 			self::$url . 'wpbakery/assets/js/gallery.js', [ $name ], self::$ver, true );
		wp_register_script( 'cz_image', 			self::$url . 'wpbakery/assets/js/image.js', [ $name ], self::$ver, true );
		wp_register_script( 'cz_expanding_cards', 	self::$url . 'wpbakery/assets/js/expanding_cards.js', [ $name ], self::$ver, true );
		wp_register_script( 'cz_subscribe', 		self::$url . 'wpbakery/assets/js/subscribe.js', [ $name ], self::$ver, true );

		// Elements styles.
		wp_register_style( 'cz_button', 			self::$url . 'wpbakery/assets/css/button.css', [ $name ], self::$ver );
		wp_register_style( 'cz_button_rtl', 		self::$url . 'wpbakery/assets/css/button.rtl.css', [ $name ], self::$ver );
		wp_register_style( 'cz_testimonials', 		self::$url . 'wpbakery/assets/css/testimonials.css', [ $name ], self::$ver );
		wp_register_style( 'cz_testimonials_rtl', 	self::$url . 'wpbakery/assets/css/testimonials.rtl.css', [ $name ], self::$ver );
		wp_register_style( 'cz_progress_bar', 		self::$url . 'wpbakery/assets/css/progress_bar.css', [ $name ], self::$ver );
		wp_register_style( 'cz_progress_bar_rtl', 	self::$url . 'wpbakery/assets/css/progress_bar.rtl.css', [ $name ], self::$ver );
		wp_register_style( 'cz_working_hours', 		self::$url . 'wpbakery/assets/css/working_hours.css', [ $name ], self::$ver );
		wp_register_style( 'cz_tabs', 				self::$url . 'wpbakery/assets/css/tabs.css', [ $name ], self::$ver );
		wp_register_style( 'cz_tabs_rtl', 			self::$url . 'wpbakery/assets/css/tabs.rtl.css', [ $name ], self::$ver );
		wp_register_style( 'cz_team', 				self::$url . 'wpbakery/assets/css/team.css', [ $name ], self::$ver );
		wp_register_style( 'cz_before_after', 		self::$url . 'wpbakery/assets/css/before_after.css', [ $name ], self::$ver );
		wp_register_style( 'cz_counter', 			self::$url . 'wpbakery/assets/css/counter.css', [ $name ], self::$ver );
		wp_register_style( 'cz_countdown', 			self::$url . 'wpbakery/assets/css/countdown.css', [ $name ], self::$ver );
		wp_register_style( 'cz_video_popup', 		self::$url . 'wpbakery/assets/css/video_popup.css', [ $name ], self::$ver );
		wp_register_style( 'cz_hotspot', 			self::$url . 'wpbakery/assets/css/hotspot.css', [ $name ], self::$ver );
		wp_register_style( 'cz_process_road', 		self::$url . 'wpbakery/assets/css/process_road.css', [ $name ], self::$ver );
		wp_register_style( 'cz_attribute_box', 		self::$url . 'wpbakery/assets/css/attribute_box.css', [ $name ], self::$ver );
		wp_register_style( 'cz_menu_background', 	self::$url . 'wpbakery/assets/css/menu_background.css', [ $name ], self::$ver );
		wp_register_style( 'cz_banner_group', 		self::$url . 'wpbakery/assets/css/banner_group.css', [ $name ], self::$ver );
		wp_register_style( 'cz_banner', 			self::$url . 'wpbakery/assets/css/banner.css', [ $name ], self::$ver );
		wp_register_style( 'cz_banner_rtl', 		self::$url . 'wpbakery/assets/css/banner.rtl.css', [ $name ], self::$ver );
		wp_register_style( 'cz_timeline', 			self::$url . 'wpbakery/assets/css/timeline.css', [ $name ], self::$ver );
		wp_register_style( 'cz_2_buttons', 			self::$url . 'wpbakery/assets/css/2_buttons.css', [ $name ], self::$ver );
		wp_register_style( 'cz_360_degree', 		self::$url . 'wpbakery/assets/css/360_degree.css', [ $name ], self::$ver );
		wp_register_style( 'cz_quote', 				self::$url . 'wpbakery/assets/css/quote.css', [ $name ], self::$ver );
		wp_register_style( 'cz_quote_rtl', 			self::$url . 'wpbakery/assets/css/quote.rtl.css', [ $name ], self::$ver );
		wp_register_style( 'cz_title', 				self::$url . 'wpbakery/assets/css/title.css', [ $name ], self::$ver );
		wp_register_style( 'cz_title_rtl', 			self::$url . 'wpbakery/assets/css/title.rtl.css', [ $name ], self::$ver );
		wp_register_style( 'cz_svg', 				self::$url . 'wpbakery/assets/css/svg.css', [ $name ], self::$ver );
		wp_register_style( 'cz_gradient_title', 	self::$url . 'wpbakery/assets/css/gradient_title.css', [ $name ], self::$ver );
		wp_register_style( 'cz_show_more_less', 	self::$url . 'wpbakery/assets/css/show_more_less.css', [ $name ], self::$ver );
		wp_register_style( 'cz_news_ticker', 		self::$url . 'wpbakery/assets/css/news_ticker.css', [ $name ], self::$ver );
		wp_register_style( 'cz_animated_text', 		self::$url . 'wpbakery/assets/css/animated_text.css', [ $name ], self::$ver );
		wp_register_style( 'cz_free_line', 			self::$url . 'wpbakery/assets/css/free_line.css', [ $name ], self::$ver );
		wp_register_style( 'cz_free_position_element', self::$url . 'wpbakery/assets/css/free_position_element.css', [ $name ], self::$ver );
		wp_register_style( 'cz_music_player', 		self::$url . 'wpbakery/assets/css/music_player.css', [ $name ], self::$ver );
		wp_register_style( 'cz_subscribe', 			self::$url . 'wpbakery/assets/css/subscribe.css', [ $name ], self::$ver );
		wp_register_style( 'cz_image_hover_zoom', 	self::$url . 'wpbakery/assets/css/image_hover_zoom.css', [ $name ], self::$ver );
		wp_register_style( 'cz_google_map', 		self::$url . 'wpbakery/assets/css/google_map.css', [ $name ], self::$ver );
		wp_register_style( 'cz_login_register', 	self::$url . 'wpbakery/assets/css/login_register.css', [ $name ], self::$ver );
		wp_register_style( 'cz_separator', 			self::$url . 'wpbakery/assets/css/separator.css', [ $name ], self::$ver );
		wp_register_style( 'cz_popup', 				self::$url . 'wpbakery/assets/css/popup.css', [ $name ], self::$ver );
		wp_register_style( 'cz_service_box', 		self::$url . 'wpbakery/assets/css/service_box.css', [ $name ], self::$ver );
		wp_register_style( 'cz_service_box_rtl', 	self::$url . 'wpbakery/assets/css/service_box.rtl.css', [ $name ], self::$ver );
		wp_register_style( 'cz_history_line', 		self::$url . 'wpbakery/assets/css/history_line.css', [ $name ], self::$ver );
		wp_register_style( 'cz_history_line_rtl', 	self::$url . 'wpbakery/assets/css/history_line.rtl.css', [ $name ], self::$ver );
		wp_register_style( 'cz_process_line_vertical', self::$url . 'wpbakery/assets/css/process_line_vertical.css', [ $name ], self::$ver );
		wp_register_style( 'cz_stylish_list', 		self::$url . 'wpbakery/assets/css/stylish_list.css', [ $name ], self::$ver );
		wp_register_style( 'cz_carousel', 			self::$url . 'wpbakery/assets/css/carousel.css', [ $name ], self::$ver );
		wp_register_style( 'cz_particles', 			self::$url . 'wpbakery/assets/css/particles.css', [ $name ], self::$ver );
		wp_register_style( 'cz_accordion', 			self::$url . 'wpbakery/assets/css/accordion.css', [ $name ], self::$ver );
		wp_register_style( 'cz_accordion_rtl', 		self::$url . 'wpbakery/assets/css/accordion.rtl.css', [ $name ], self::$ver );
		wp_register_style( 'cz_text_marquee', 		self::$url . 'wpbakery/assets/css/text_marquee.css', [ $name ], self::$ver );
		wp_register_style( 'cz_content_box', 		self::$url . 'wpbakery/assets/css/content_box.css', [ $name ], self::$ver );
		wp_register_style( 'cz_gallery', 			self::$url . 'wpbakery/assets/css/gallery.css', [ $name ], self::$ver );
		wp_register_style( 'cz_gallery_rtl', 		self::$url . 'wpbakery/assets/css/gallery.rtl.css', [ $name ], self::$ver );
		wp_register_style( 'cz_logo', 				self::$url . 'wpbakery/assets/css/logo.css', [ $name ], self::$ver );
		wp_register_style( 'cz_expanding_cards', 	self::$url . 'wpbakery/assets/css/expanding_cards.css', [ $name ], self::$ver );

		// Single post/product related items carousel.
		if ( is_singular() && ! is_singular( 'page' ) ) {

			wp_enqueue_style( 'cz_carousel' );
			wp_enqueue_script( 'cz_carousel' );

		}

		// Magic mouse.
		$mouse = self::option( 'magic_mouse' );

		if ( $mouse == '1' ) {

			wp_enqueue_script( 'codevz-magic-mouse', self::$url . 'assets/js/magic-mouse.js', [ $name ], self::$ver, true );
			wp_enqueue_style(  'codevz-magic-mouse', self::$url . 'assets/css/magic-mouse.css', [], self::$ver );

			$magic_mouse_magnet = self::option( 'magic_mouse_magnet' );
			$magic_mouse_magnet = self::option( 'magic_mouse_magnet' );

			$str = implode( ', ', ( $magic_mouse_magnet ? $magic_mouse_magnet : [ '.xxx' ] ) );
			wp_add_inline_script( 'codevz-magic-mouse', 'var codevzMagnetSelectors = "' . $str . '";' );

		} else if ( $mouse ) {

			wp_enqueue_script( 'codevz-magic-mouse-' . esc_attr( $mouse ), self::$url . 'assets/js/magic-mouse-' . esc_attr( $mouse ) . '.js', [ $name ], self::$ver, true );

		}

		$preview = is_customize_preview();

		// Cookie.
		if ( empty( $_COOKIE[ 'xtra_cookie' ] ) || $preview ) {

			if ( $preview || self::option( 'cookie', ( ! empty( $GLOBALS[ 'xtra_cookie' ] ) ? $GLOBALS[ 'xtra_cookie' ] : '' ) ) ) {
				wp_enqueue_style( 'cz_button' );
				wp_enqueue_style( 'codevz-plus-cookie', 	self::$url . 'assets/css/cookie.css', [], self::$ver );
				wp_enqueue_script( 'codevz-plus-cookie', 	self::$url . 'assets/js/cookie.js', [ $name ], self::$ver, true );
			}

		}

		// Plugin CSS.
		wp_enqueue_style( $name, self::$url . 'assets/css/codevzplus.css', [], self::$ver );

		if ( ! self::option( 'disable_responsive' ) ) {

			wp_enqueue_style( 'codevz-plus-tablet', self::$url . 'assets/css/codevzplus-tablet.css', [ $name ], self::$ver, 'screen and (max-width: ' . self::option( 'tablet_breakpoint', '768px' ) . ')' );
			wp_enqueue_style( 'codevz-plus-mobile', self::$url . 'assets/css/codevzplus-mobile.css', [ $name ], self::$ver, 'screen and (max-width: ' . self::option( 'mobile_breakpoint', '480px' ) . ')' );

		}

		// Custom JS
		$js = self::option( 'js' );
		if ( $js ) {
			wp_add_inline_script( $name, 'jQuery( function( $ ) {' . $js . '});' );
		}

	}

	public static function wp_enqueue_scripts_page_builder( $css = '' ) {

		if ( is_admin() ) {
			return;
		}

		// Edit preview elements.
		if ( self::$preview ) {
			$css .= '.customize-partial-edit-shortcut button,.customize-partial-edit-shortcut button:hover, .widget .customize-partial-edit-shortcut button {color: #fff !important;border-color: #fff !important;background-color: #434343 !important}i.codevz-section-focus {display: none;color: #fff !important;top: -14px !important;left: -14px !important;width: 1em !important;height: 1em !important;padding: 7px !important;font-size: 12px !important;line-height: 1em !important;border: 2px solid #fff !important;background-color: #434343 !important;border-radius: 100px !important;outline: 0 !important;position: absolute;z-index: 999999999;cursor: pointer;box-sizing: content-box;transition: all .2s ease-in-out;box-shadow: 0 2px 1px rgba(46,68,83,.15)}i.codevz-section-focus:hover {color: #ffbb00 !important;border-color: #ffbb00 !important}.rtl i.codevz-section-focus {left: auto !important;right: -14px !important}i.codevz-section-focus-second {left: 20px !important}.rtl i.codevz-section-focus-second {left: auto !important;right: 20px !important}.page_footer > i.codevz-section-focus {margin: 20px}.customize-partial-edit-shortcuts-shown i:hover > i.codevz-section-focus,.customize-partial-edit-shortcuts-shown footer:hover > i.codevz-section-focus,.customize-partial-edit-shortcuts-shown div:hover > i.codevz-section-focus {display: block}.sidebar_offcanvas_area i.codevz-section-focus {margin: 18px;display: block}.customize-partial-edit-shortcut, .widget .customize-partial-edit-shortcut {margin: -15px}.sidebar_offcanvas_area .widget .customize-partial-edit-shortcut {display: none}.customize-partial-edit-shortcuts-shown i.backtotop, .customize-partial-edit-shortcuts-shown i.fixed_contact {overflow: visible}';

		// Admin style.
		} else if ( is_user_logged_in() ) {
			$css .= '.xtra-inactive-notice {color: #333;display: flex;background: #fff;line-height: 1.5;border-width: 0;font-size: 15px;padding: 20px 25px;border-radius: 5px;border-style: solid;margin-bottom: 30px;border-color: #ffbb00;border-left-width: 5px;box-shadow: 0 10px 40px rgba(17, 17, 17, 0.1)}#wp-admin-bar-codevz_menu > a.ab-item{color: #00cbff !important}#wp-admin-bar-codevz_menu_maintenance > a{color: #ff6262 !important}#wpadminbar a img{display: inline-block}';
		}

		$post_id = isset( self::$post->ID ) ? self::$post->ID : get_the_id();

		if ( $post_id && ! self::$vc_editable ) {

			// Page builder styles.
			$styles = get_post_meta( $post_id, 'cz_sc_styles', 1 );

			// Empty styles, Regenerate.
			if ( ! $styles && is_page() ) {

				$content = get_post_field( 'post_content', $post_id );

				if ( self::contains( $content, 'sk_' ) ) {
				
					// Regenrate dynamic styles.
					self::$vc_editable = true;
					self::save_post( $post_id );
					
					$styles = get_post_meta( $post_id, 'cz_sc_styles', 1 );

				}

			}

			// Responsive page builder tablet styles
			$tablet = get_post_meta( $post_id, 'cz_sc_styles_tablet', 1 );
			if ( $tablet && ! self::option( 'disable_responsive' ) ) {
				if ( self::contains( $tablet, '@media' ) ) {
					$styles .= $tablet;
				} else {
					$styles .= '@media screen and (max-width:' . self::option( 'tablet_breakpoint', '768px' ) . '){' . $tablet . '}';
				}
			}

			// Responsive page builder mobile styles
			$mobile = get_post_meta( $post_id, 'cz_sc_styles_mobile', 1 );
			if ( $mobile && ! self::option( 'disable_responsive' ) ) {
				if ( self::contains( $mobile, '@media' ) ) {
					$styles .= $mobile;
				} else {
					$styles .= '@media screen and (max-width:' . self::option( 'mobile_breakpoint', '480px' ) . '){' . $mobile . '}';
				}
			}

			$css .= $styles;

		}

		wp_add_inline_style( 'codevz-plus', $css );

		// Temp.
	    if ( self::is_speed() ) {

		    global $wp_scripts;
		    if (!empty($wp_scripts->queue)) {
			    $whitelist = [
			        'jquery' 		=> true
			    ];

			    foreach ($wp_scripts->queue as $handle) {
			        if (!isset($whitelist[$handle])) {
			            wp_dequeue_script($handle);
			            wp_deregister_script($handle);
			        }
			    }
		    }

			global $wp_styles;
			if (!empty($wp_styles->queue)) {

			    $keep_styles = [
			        'codevz' 		=> true,
			        'codevz-plus' 	=> true,
			        'codevz-laptop' => true,
			        'codevz-tablet' => true,
			        'codevz-mobile' => true,
			        'cz_title' 		=> true,
			        'cz_image' 		=> true
			    ];

			    $blacklist = [
			        'fontawesome', 'font-awesome', 'google-font', 'cz-', 'cz_', 
			        'codevz-plus', 'codevz-mobile-', 'codevz-woocommerce', 'codevz-loading', 
			        'codevz-bar', 'codevz-edd', 'codevz-tilt', 'codevz-magic', 'codevz-fixed', 
			        'codevz-blocks', 'font-', 'edd-', 'rs-', 'revslider-', 'tp-', 'sr7', 
			        'vc_', 'js_composer', 'waypoints', 'animate-', 'contact-', 
			        'woocommerce-', 'woocommerce_', 'brands-', 'rtl-'
			    ];

			    $pattern = '/' . implode('|', array_map('preg_quote', $blacklist)) . '/i';

			    foreach ($wp_styles->queue as $handle) {
			        if (!isset($keep_styles[$handle]) && preg_match($pattern, $handle)) {
			            wp_dequeue_style($handle);
			            wp_deregister_style($handle);
			        }
			    }
			}

			wp_add_inline_style( 'codevz', 'img,.cz_main_image,.cz_image_in{max-width:98%!important;margin-left:0;margin-right:0}[data-cz-style]{opacity:1!important}i.icon_offcanvas_menu{display: block}' );

		}

	}

	/**
	 *
	 * Custom JS/CSS for VC popup box
	 * 
	 * @return string
	 * 
	 */
	public static function vc_edit_form_fields_after_render() {

		$body_font = self::option( '_css_body_typo' );
		
		$body_font = empty( $body_font[0]['font-family'] ) ? '' : $body_font[0]['font-family'];
		
		$body_font = explode( ':', $body_font ); 

		?>

		<script>

			jQuery( function( $ ) {

				$( '.wpb_edit_form_elements' ).codevz_reload_script();

				$( '.vc_param_group-list' ).on( 'click', function() {
					var en = $( this );
					setTimeout(function() {
						$( '.vc_param', en ).each(function() {
							$( this ).codevz_reload_script();
						});
					}, 4000 );
				});

				setTimeout(function() {

					// Add theme colors to WPBakery pickr.
					$('.pcr-swatches').each(function () {
					    const $swatchBox = $(this);
					    const $lastSwatch = $swatchBox.find('button:last');

					    [codevz_primary_color, codevz_secondary_color].forEach((hex, i) => {
					        if (!hex) {
					        	return;
					        }

					        if ( i == 0 ) {
					        	$swatchBox.append( '<hr style="background: #e5e5e5;height: 1px;width: 60%;position: relative;transform: rotate(-70deg);" />' );
					        }

					        const $newSwatch = $lastSwatch.clone().css('--pcr-color', hex).addClass('custom-color');
					        
					        $newSwatch.on( 'click', function () {

					            const $pickerInput = $(this).closest('.pcr-app').find('.pcr-result');
					            if ($pickerInput.length) {
					                $pickerInput.val(hex).trigger('input');

					                // Simulate user typing
					                setTimeout(() => {
					                    let event = new Event('input', { bubbles: true });
					                    $pickerInput[0].dispatchEvent(event);
					                }, 100);
					            }
					        });

					        $swatchBox.append($newSwatch);
					    });
					});

					// WP editor font family and background color on white text.
					$( '#wpb_tinymce_content_ifr' ).contents().find( 'body' ).css({
						'background': 'rgba(167, 167, 167, 0.25)',
						'font-family': '<?php echo esc_html( empty( $body_font[0] ) ? 'Open Sans' : $body_font[0] ); ?>'
					});

					$( '#wpb_tinymce_content_ifr' ).contents().find( 'head' ).append( '<style>.cz_highlight_1 {position: relative}.cz_highlight_1:after {width: calc(100% + 6px);position: absolute;content: "";height: 10px;bottom: 0;left: -3px;background: "<?php echo esc_html( self::option( 'site_color' ) ); ?>";z-index: -1;opacity: .2;}</style>' );

					<?php 

						$disable = array_flip( (array) self::option( 'disable' ) );

						if ( ! isset( $disable['videos'] ) ) {
					?>

					// Elements video turoials
					var el = $( '[data-vc-shortcode]' ).attr( 'data-vc-shortcode' );
					var videos = {
						cz_2_buttons: 'FFCoaubH34M',
						cz_360_degree: 'AQTj8-bSHnI',
						cz_accordion:'VYzFWA_4iCM',
						cz_animated_text:'qbclDC43uS8',
						cz_banner:'l3ee8IIXbzA',
						cz_before_after:'cQCRTkNsB9I',
						cz_button:'TWkG6HtdSoo',
						cz_carousel:'R_iFLdOv2E8',
						cz_contact_form_7:'eIZa-QfOPWo',
						cz_content_box:'t26HZ_9tJ2c',
						cz_countdown:'R20yLL03jQI',
						cz_counter:'I9-Rjkygpmw',
						cz_free_line:'B3PyMvibmvA',
						cz_free_position_element:'0js4hNd-kh8',
						cz_gallery:'j5tD0NRSw7g',
						cz_gap:'s4M2nD2Pq9M',
						cz_gradient_title:'S5fzvQ3wO0g',
						cz_history_line:'n-p0416Qtnw',
						cz_hotspot:'QDPdMrVP0WA',
						cz_image:'Tw8SfSGRQdY',
						cz_image_hover_zoom:'yk05SzAovfM',
						cz_login_register:'t2K2Jp8LbHA',
						cz_music_player:'ajdB15T7Eos',
						cz_news_ticker:'wK3G2RtnAl8',
						cz_parallax_group:'MApojPfkwXk',
						cz_particles:'4Fxr4fAKYmM',
						cz_popup:'5QL5_EGEMTE',
						cz_posts:'lU0gjnueZDI',
						cz_process_line_vertical:'EE8MZbbJixw',
						cz_process_road:'eY5UM0ucfOE',
						cz_progress_bar:'XDoUabdAVn0',
						cz_quote:'nSRgDyiMm0U',
						cz_separator:'UzVfzx1w75M',
						cz_service_box:'biplj6KgTrU',
						cz_show_more_less:'4CeGd5Z-oZs',
						cz_social_icons:'kmJ82T9TISk',
						cz_stylish_list:'ANbqrPdkj1o',
						cz_svg:'aNgPan2wmHk',
						cz_tabs:'7PmbBFXMi6A',
						cz_team:'_94XN1VnYMA',
						cz_testimonials:'IeCYG7y3fUk',
						cz_timeline:'7ZPnUppKEi0',
						cz_title:'NRMXChwRxto',
						cz_video_popup:'ugEf_JIY6JY',
						cz_working_hours:'JQm3m71pTr0',
					};

					if ( videos[ el ] != 'undefined' && videos[ el ] ) {
						if ( ! $( '.cz_video_tutorial' ).length ) {
							$( '.vc_ui-dropdown-trigger' ).before( '<a class="cz_video_tutorial fa czico-192-youtube" target="_blank" href="https://www.youtube.com/watch?v=' + videos[ el ] + '"></a>' );
						} else {
							$( '.cz_video_tutorial' ).attr( 'href', 'https://www.youtube.com/watch?v=' + videos[ el ] );
						}
					}

					<?php } ?>

				}, 500 );

			});

		</script>

		<?php 

	}

	/**
	 *
	 * Enable some features for WP Editor
	 * 
	 * @param $i is array of default WP Editor features
	 * @return array
	 * 
	 */ 
	public static function mce_buttons_2( $i ) {
		array_shift( $i );
		array_unshift( $i, 'styleselect', 'fontselect', 'fontsizeselect', 'backcolor' );

		return $i;
	}

	/**
	 *
	 * Customize some features of WP Editor
	 * 
	 * @param $i is array of default WP Editor features values
	 * @return array
	 * 
	 */
	public static function tiny_mce_before_init( $i ) {
		$i['fontsize_formats'] = '6px 7px 8px 9px 10px 11px 12px 13px 14px 15px 16px 17px 18px 19px 20px 22px 24px 26px 28px 30px 32px 34px 36px 38px 40px 42px 44px 46px 48px 50px 52px 54px 56px 58px 60px 62px 64px 66px 68px 70px 72px 74px 76px 78px 80px 82px 84px 86px 88px 90px 92px 94px 96px 98px 100px 102px 104px 106px 108px 110px 120px 130px 140px 150px 160px 170px 180px 190px 200px 1em 2em 3em 4em 5em 6em 7em 8em 9em 10em 11em 12em 13em 14em 15em 16em 17em 18em 19em 20em';

		$primary_color = self::option( 'site_color', '#4e71fe' );
		$secondary_color = self::option( 'site_color_sec' );

			$colors = '"000000", "Black",
			  "993300", "Burnt orange",
			  "333300", "Dark olive",
			  "003300", "Dark green",
			  "003366", "Dark azure",
			  "000080", "Navy Blue",
			  "333399", "Indigo",
			  "333333", "Very dark gray",
			  "800000", "Maroon",
			  "FF6600", "Orange",
			  "808000", "Olive",
			  "008000", "Green",
			  "008080", "Teal",
			  "0000FF", "Blue",
			  "666699", "Grayish blue",
			  "666666", "Gray",
			  "FF0000", "Red",
			  "FF9900", "Amber",
			  "99CC00", "Yellow green",
			  "339966", "Sea green",
			  "33CCCC", "Turquoise",
			  "3366FF", "Royal blue",
			  "800080", "Purple",
			  "AAAAAA", "Medium gray",
			  "FF00FF", "Magenta",
			  "FFCC00", "Gold",
			  "FFFF00", "Yellow",
			  "00FF00", "Lime",
			  "00FFFF", "Aqua",
			  "00CCFF", "Sky blue",
			  "993366", "Red violet",
			  "FFFFFF", "White",
			  "FF99CC", "Pink",
			  "FFCC99", "Peach",
			  "FFFF99", "Light yellow",
			  "CCFFCC", "Pale green",
			  "CCFFFF", "Pale cyan"';

		$colors .= ',"' . $primary_color . '", "Primary Color"';
		$colors .= $secondary_color ? ',"' . $secondary_color . '", "Secondary Color"' : '';

		// Build colour grid default+custom colors
		$i['textcolor_map'] = '[' . str_replace( '#', '', $colors ) . ']';
		$i['textcolor_rows'] = 6;

		$fonts = get_option( 'codevz_wp_editor_google_fonts' );

		// Fonts for WP Editor from theme options
		$i['font_formats'] = apply_filters( 'codevz_wp_editor_google_fonts', $fonts );

		// New style_formats
		$style_formats = array(
			array(
				'title' => esc_html__( '100 | Thin', 'codevz-plus' ),
				'inline' => 'span',
				'styles' => array( 'font-weight' => '100' ),
				'wrapper' => false
			),
			array(
				'title' => esc_html__( '200 | Extra Light', 'codevz-plus' ),
				'inline' => 'span',
				'styles' => array( 'font-weight' => '200' ),
				'wrapper' => false
			),
			array(
				'title' => esc_html__( '300 | Light', 'codevz-plus' ),
				'inline' => 'span',
				'styles' => array( 'font-weight' => '300' ),
				'wrapper' => false
			),
			array(
				'title' => esc_html__( '400 | Normal', 'codevz-plus' ),
				'inline' => 'span',
				'styles' => array( 'font-weight' => '400' ),
				'wrapper' => false
			),
			array(
				'title' => esc_html__( '500 | Medium', 'codevz-plus' ),
				'inline' => 'span',
				'styles' => array( 'font-weight' => '500' ),
				'wrapper' => false
			),
			array(
				'title' => esc_html__( '600 | Semi Bold', 'codevz-plus' ),
				'inline' => 'span',
				'styles' => array( 'font-weight' => '600' ),
				'wrapper' => false
			),
			array(
				'title' => esc_html__( '700 | Bold', 'codevz-plus' ),
				'inline' => 'span',
				'styles' => array( 'font-weight' => '700' ),
				'wrapper' => false
			),
			array(
				'title' => esc_html__( '800 | Extra Bold', 'codevz-plus' ),
				'inline' => 'span',
				'styles' => array( 'font-weight' => '800' ),
				'wrapper' => false
			),
			array(
				'title' => esc_html__( '900 | High Bold', 'codevz-plus' ),
				'inline' => 'span',
				'styles' => array( 'font-weight' => '900' ),
				'wrapper' => false
			),
			array(
				'title' => esc_html__( 'Line height', 'codevz-plus' ) . ' 0.6',
				'block' => 'div',
				'wrapper' => false,
				'styles' => array( 'line-height' => '0.6' )
			),
			array(
				'title' => esc_html__( 'Line height', 'codevz-plus' ) . ' 0.8',
				'block' => 'div',
				'wrapper' => false,
				'styles' => array( 'line-height' => '0.8' )
			),
			array(
				'title' => esc_html__( 'Line height', 'codevz-plus' ) . ' 1',
				'block' => 'div',
				'wrapper' => false,
				'styles' => array( 'line-height' => '1' )
			),
			array(
				'title' => esc_html__( 'Line height', 'codevz-plus' ) . ' 1.1',
				'block' => 'div',
				'wrapper' => false,
				'styles' => array( 'line-height' => '1.1' )
			),
			array(
				'title' => esc_html__( 'Line height', 'codevz-plus' ) . ' 1.2',
				'block' => 'div',
				'wrapper' => false,
				'styles' => array( 'line-height' => '1.2' )
			),
			array(
				'title' => esc_html__( 'Line height', 'codevz-plus' ) . ' 1.3',
				'block' => 'div',
				'wrapper' => false,
				'styles' => array( 'line-height' => '1.3' )
			),
			array(
				'title' => esc_html__( 'Line height', 'codevz-plus' ) . ' 1.4',
				'block' => 'div',
				'wrapper' => false,
				'styles' => array( 'line-height' => '1.4' )
			),
			array(
				'title' => esc_html__( 'Line height', 'codevz-plus' ) . ' 1.5',
				'block' => 'div',
				'wrapper' => false,
				'styles' => array( 'line-height' => '1.5' )
			),
			array(
				'title' => esc_html__( 'Line height', 'codevz-plus' ) . ' 1.6',
				'block' => 'div',
				'wrapper' => false,
				'styles' => array( 'line-height' => '1.6' )
			),
			array(
				'title' => esc_html__( 'Line height', 'codevz-plus' ) . ' 1.7',
				'block' => 'div',
				'wrapper' => false,
				'styles' => array( 'line-height' => '1.7' )
			),
			array(
				'title' => esc_html__( 'Line height', 'codevz-plus' ) . ' 1.8',
				'block' => 'div',
				'wrapper' => false,
				'styles' => array( 'line-height' => '1.8' )
			),
			array(
				'title' => esc_html__( 'Line height', 'codevz-plus' ) . ' 1.9',
				'block' => 'div',
				'wrapper' => false,
				'styles' => array( 'line-height' => '1.9' )
			),
			array(
				'title' => esc_html__( 'Line height', 'codevz-plus' ) . ' 2',
				'block' => 'div',
				'wrapper' => false,
				'styles' => array( 'line-height' => '2' )
			),
			array(
				'title' => esc_html__( 'Letter Spacing', 'codevz-plus' ) . ' -2px',
				'inline' => 'span',
				'wrapper' => false,
				'styles' => array( 'letter-spacing' => '-2px' )
			),
			array(
				'title' => esc_html__( 'Letter Spacing', 'codevz-plus' ) . ' -1px',
				'inline' => 'span',
				'wrapper' => false,
				'styles' => array( 'letter-spacing' => '-1px' )
			),
			array(
				'title' => esc_html__( 'Letter Spacing', 'codevz-plus' ) . ' 0px',
				'inline' => 'span',
				'wrapper' => false,
				'styles' => array( 'letter-spacing' => '0px' )
			),
			array(
				'title' => esc_html__( 'Letter Spacing', 'codevz-plus' ) . ' 1px',
				'inline' => 'span',
				'wrapper' => false,
				'styles' => array( 'letter-spacing' => '1px' )
			),
			array(
				'title' => esc_html__( 'Letter Spacing', 'codevz-plus' ) . ' 2px',
				'inline' => 'span',
				'wrapper' => false,
				'styles' => array( 'letter-spacing' => '2px' )
			),
			array(
				'title' => esc_html__( 'Letter Spacing', 'codevz-plus' ) . ' 3px',
				'inline' => 'span',
				'wrapper' => false,
				'styles' => array( 'letter-spacing' => '3px' )
			),
			array(
				'title' => esc_html__( 'Letter Spacing', 'codevz-plus' ) . ' 4px',
				'inline' => 'span',
				'wrapper' => false,
				'styles' => array( 'letter-spacing' => '4px' )
			),
			array(
				'title' => esc_html__( 'Letter Spacing', 'codevz-plus' ) . ' 5px',
				'inline' => 'span',
				'wrapper' => false,
				'styles' => array( 'letter-spacing' => '5px' )
			),
			array(
				'title' => esc_html__( 'Letter Spacing', 'codevz-plus' ) . ' 6px',
				'inline' => 'span',
				'wrapper' => false,
				'styles' => array( 'letter-spacing' => '6px' )
			),
			array(
				'title' => esc_html__( 'Letter Spacing', 'codevz-plus' ) . ' 7px',
				'inline' => 'span',
				'wrapper' => false,
				'styles' => array( 'letter-spacing' => '7px' )
			),
			array(
				'title' => esc_html__( 'Letter Spacing', 'codevz-plus' ) . ' 8px',
				'inline' => 'span',
				'wrapper' => false,
				'styles' => array( 'letter-spacing' => '8px' )
			),
			array(
				'title' => esc_html__( 'Letter Spacing', 'codevz-plus' ) . ' 10px',
				'inline' => 'span',
				'wrapper' => false,
				'styles' => array( 'letter-spacing' => '10px' )
			),
			array(
				'title' => esc_html__( 'Margin 0px', 'codevz-plus' ),
				'inline' => 'span',
				'wrapper' => false,
				'styles' => array( 'margin' => '0px' )
			),
			array(
				'title' => esc_html__( 'Margin top 10px', 'codevz-plus' ),
				'inline' => 'span',
				'wrapper' => false,
				'styles' => array( 'margin-top' => '10px', 'display' => 'inline-block' )
			),
			array(
				'title' => esc_html__( 'Margin top 20px', 'codevz-plus' ),
				'inline' => 'span',
				'wrapper' => false,
				'styles' => array( 'margin-top' => '20px', 'display' => 'inline-block' )
			),
			array(
				'title' => esc_html__( 'Margin top 30px', 'codevz-plus' ),
				'inline' => 'span',
				'wrapper' => false,
				'styles' => array( 'margin-top' => '30px', 'display' => 'inline-block' )
			),
			array(
				'title' => esc_html__( 'Margin bottom 10px', 'codevz-plus' ),
				'inline' => 'span',
				'wrapper' => false,
				'styles' => array( 'margin-bottom' => '10px', 'display' => 'inline-block' )
			),
			array(
				'title' => esc_html__( 'Margin bottom 20px', 'codevz-plus' ),
				'inline' => 'span',
				'wrapper' => false,
				'styles' => array( 'margin-bottom' => '20px', 'display' => 'inline-block' )
			),
			array(
				'title' => esc_html__( 'Margin bottom 30px', 'codevz-plus' ),
				'inline' => 'span',
				'wrapper' => false,
				'styles' => array( 'margin-bottom' => '30px', 'display' => 'inline-block' )
			),
			array(
				'title'  => esc_html__( 'Highlight', 'codevz-plus' ),
				'inline' => 'span',
				'classes' => 'cz_highlight',
				'styles' => array(
					'margin' 		=> '0 2px',
					'padding' 		=> '1px 7px 2px',
					'background' 	=> 'rgba(167, 167, 167, 0.26)',
					'border-radius' => '2px',
				),
				'wrapper' => false
			),
			array(
				'title'  => esc_html__( 'Half Highlight', 'codevz-plus' ),
				'inline' => 'span',
				'classes' => 'cz_highlight_1',
				'wrapper' => false
			),
			array(
				'title'  => esc_html__( 'Border solid', 'codevz-plus' ),
				'inline' => 'span',
				'classes' => 'cz_brsolid',
				'styles' => array(
					'margin' 		=> '0 2px',
					'padding' 		=> '4px 8px 5px',
					'border' 		=> '1px solid',
					'border-radius' => '2px',
				),
				'wrapper' => false
			),
			array(
				'title'  => esc_html__( 'Border dotted', 'codevz-plus' ),
				'inline' => 'span',
				'classes' => 'cz_brdotted',
				'styles' => array(
					'margin' 		=> '0 2px',
					'padding' 		=> '4px 8px 5px',
					'border' 		=> '1px dotted',
					'border-radius' => '2px',
				),
				'wrapper' => false
			),
			array(
				'title'  => esc_html__( 'Border dashed', 'codevz-plus' ),
				'inline' => 'span',
				'classes' => 'cz_brdashed',
				'styles' => array(
					'margin' 		=> '0 2px',
					'padding' 		=> '4px 8px 5px',
					'border' 		=> '1px dashed',
					'border-radius' => '2px',
				),
				'wrapper' => false
			),
			array(
				'title'  => esc_html__( 'Underline', 'codevz-plus' ),
				'inline' => 'span',
				'classes' => 'cz_underline',
				'styles' => array(
					'margin' 		=> '0 2px',
					'padding' 		=> '1px 0 2px',
					'border-bottom' => '1px solid'
				),
				'wrapper' => false
			),
			array(
				'title'  => esc_html__( 'Underline Dashed', 'codevz-plus' ),
				'inline' => 'span',
				'classes' => 'cz_underline cz_underline_dashed',
				'styles' => array(
					'margin' 		=> '0 2px',
					'padding' 		=> '1px 0 2px',
					'border-bottom' => '1px dashed'
				),
				'wrapper' => false
			),
			array(
				'title'  => esc_html__( 'Topline', 'codevz-plus' ),
				'inline' => 'span',
				'classes' => 'cz_topline',
				'styles' => array(
					'margin' 		=> '0 2px',
					'padding' 		=> '1px 0 2px',
					'border-top' 	=> '1px solid'
				),
				'wrapper' => false
			),
			array(
				'title'  => esc_html__( 'Topline Dashed', 'codevz-plus' ),
				'inline' => 'span',
				'classes' => 'cz_topline cz_topline_dashed',
				'styles' => array(
					'margin' 		=> '0 2px',
					'padding' 		=> '1px 0 2px',
					'border-top' 	=> '1px dashed'
				),
				'wrapper' => false
			),
			array(
				'title'  => esc_html__( 'Blockquote', 'codevz-plus' ) . ' ' . esc_html__( 'Center', 'codevz-plus' ),
				'inline' => 'span',
				'classes' => 'blockquote',
				'styles' => array(
					'width' 		=> '75%',
					'margin' 		=> '0 auto',
					'display' 		=> 'table',
					'text-align' 	=> 'center',
				),
				'wrapper' => false
			),
			array(
				'title'  => esc_html__( 'Blockquote', 'codevz-plus' ) . ' ' . esc_html__( 'Left', 'codevz-plus' ),
				'inline' => 'span',
				'classes' => 'blockquote',
				'styles' => array(
					'float' 		=> 'left',
					'width' 		=> '40%',
					'margin' 		=> '0 20px 20px 0',
				),
				'wrapper' => false
			),
			array(
				'title'  => esc_html__( 'Blockquote', 'codevz-plus' ) . ' ' . esc_html__( 'Right', 'codevz-plus' ),
				'inline' => 'span',
				'classes' => 'blockquote',
				'styles' => array(
					'float' 		=> 'right',
					'width' 		=> '40%',
					'margin' 		=> '0 0 20px 20px',
				),
				'wrapper' => false
			),	
			array(
				'title'  => esc_html__( 'Float', 'codevz-plus' ) . ' ' . esc_html__( 'Right', 'codevz-plus' ),
				'inline' => 'span',
				'styles' => array( 'float' => 'right' ),
				'wrapper' => false
			),
			array(
				'title'  => esc_html__( 'Dropcap', 'codevz-plus' ),
				'inline' => 'span',
				'classes' => 'cz_dropcap',
				'styles' => array(
					'float' 		=> self::$is_rtl ? 'right' : 'left',
					'margin' 		=> self::$is_rtl ? '5px 0 0 12px' : '5px 12px 0 0',
					'width' 		=> '2em',
					'height' 		=> '2em',
					'line-height' 	=> '2em',
					'text-align' 	=> 'center',
				),
				'wrapper' => false
			),
			array(
				'title'  => esc_html__( 'Dropcap Border', 'codevz-plus' ),
				'inline' => 'span',
				'classes' => 'cz_dropcap',
				'styles' => array(
					'float' 		=> self::$is_rtl ? 'right' : 'left',
					'margin' 		=> self::$is_rtl ? '5px 0 0 12px' : '5px 12px 0 0',
					'width' 		=> '2em',
					'height' 		=> '2em',
					'line-height' 	=> '2em',
					'text-align' 	=> 'center',
					'border' 		=> '2px solid',
				),
				'wrapper' => false
			),
			array(
				'title'  => esc_html__( 'Sup', 'codevz-plus' ),
				'inline' => 'sup',
				'styles' => [],
				'wrapper' => false
			),
			array(
				'title'  => esc_html__( 'Sub', 'codevz-plus' ),
				'inline' => 'sub',
				'styles' => [],
				'wrapper' => false
			),
			array(
				'title'  => esc_html__( 'Small', 'codevz-plus' ),
				'inline' => 'small',
				'styles' => [],
				'wrapper' => false
			),
		);
		$i['style_formats'] = wp_json_encode( $style_formats );

		return $i;
	}

	/**
	 *
	 * Filter for moving animation param into new tab Advanced
	 * 
	 * @param $i is default css_animation settings
	 * @return array
	 * 
	 */
	public static function vc_map_add_css_animation( $i ) {
		$i['group'] = esc_html__( 'Advanced', 'codevz-plus' );
		return $i;
	}

	/**
	 *
	 * Useful shortcodes
	 * 
	 * @return string
	 * 
	 */
	public function br( $a, $c = '' ) {
		return '<br class="clr" />';
	}

	public function shortcode_get_current_year( $a, $c = '' ) {
		return current_time( 'Y' );
	}

	public function shortcode_google_font( $a, $c = '' ) {

		if ( isset( $a['font'] ) ) {

			self::load_font( do_shortcode( wp_kses_post( (string) $a['font'] ) ) );

		}

	}

	public function shortcode_translate( $a, $c = '' ) {
		if ( isset( $a['lang'] ) ) {

			$lang = get_locale();

			if ( defined( 'ICL_LANGUAGE_CODE' ) ) {
				$lang = ICL_LANGUAGE_CODE;

			} else if ( function_exists( 'pll_current_language' ) ) {
				$lang = pll_current_language();

			} else if ( class_exists( 'qtrans_getSortedLanguages' ) ) {
				global $q_config;
				$lang = isset( $q_config['language'] ) ? $q_config['language'] : $lang;
			}

			if ( self::contains( $lang, $a['lang'] ) ) {
				return $c;
			}
		}
	}

	/**
	 *
	 * Add loop animations to vc animations list
	 * 
	 * @return string
	 * 
	 */
	public static function vc_param_animation_style_list( $i ) {
		return wp_parse_args( array(
			array(
				'label' => esc_html__( 'Loop Animations', 'codevz-plus' ),
				'values' => array(
					esc_html__( 'Fast Spinner', 'codevz-plus' ) => array(
						'value' => 'cz_loop_spinner',
						'type' => 'in',
					),
					esc_html__( 'Normal Spinner', 'codevz-plus' ) => array(
						'value' => 'cz_loop_spinner_normal',
						'type' => 'in',
					),
					esc_html__( 'Slow Spinner', 'codevz-plus' ) => array(
						'value' => 'cz_loop_spinner_slow',
						'type' => 'in',
					),
					esc_html__( 'Pulse', 'codevz-plus' ) => array(
						'value' => 'cz_loop_pulse',
						'type' => 'in',
					),
					esc_html__( 'Tada', 'codevz-plus' ) => array(
						'value' => 'cz_loop_tada',
						'type' => 'in',
					),
					esc_html__( 'Flash', 'codevz-plus' ) => array(
						'value' => 'cz_loop_flash',
						'type' => 'in',
					),
					esc_html__( 'Swing', 'codevz-plus' ) => array(
						'value' => 'cz_loop_swing',
						'type' => 'in',
					),
					esc_html__( 'Jello', 'codevz-plus' ) => array(
						'value' => 'cz_loop_jello',
						'type' => 'in',
					),
					esc_html__( 'Animation', 'codevz-plus' ) . ' 1' => array(
						'value' => 'cz_infinite_anim_1',
						'type' => 'in',
					),
					esc_html__( 'Animation', 'codevz-plus' ) . ' 2' => array(
						'value' => 'cz_infinite_anim_2',
						'type' => 'in',
					),
					esc_html__( 'Animation', 'codevz-plus' ) . ' 3' => array(
						'value' => 'cz_infinite_anim_3',
						'type' => 'in',
					),
					esc_html__( 'Animation', 'codevz-plus' ) . ' 4' => array(
						'value' => 'cz_infinite_anim_4',
						'type' => 'in',
					),
					esc_html__( 'Animation', 'codevz-plus' ) . ' 5' => array(
						'value' => 'cz_infinite_anim_5',
						'type' => 'in',
					),
				),
			),
			array(
				'label' => esc_html__( 'Block Reveal', 'codevz-plus' ),
				'values' => array(
					esc_html__( 'Right', 'codevz-plus' ) => array(
						'value' => 'cz_brfx_right',
						'type' => 'in',
					),
					esc_html__( 'Left', 'codevz-plus' ) => array(
						'value' => 'cz_brfx_left',
						'type' => 'in',
					),
					esc_html__( 'Up', 'codevz-plus' ) => array(
						'value' => 'cz_brfx_up',
						'type' => 'in',
					),
					esc_html__( 'Down', 'codevz-plus' ) => array(
						'value' => 'cz_brfx_down',
						'type' => 'in',
					),
				),
			),
		), $i );
	}

	/**
	 * Required for admin
	 * 
	 * @return string
	 */
	public static function admin_enqueue_scripts( $hook = false ) {

		if ( $hook === 'xxwidgets.php' ) {
		    wp_add_inline_script('jquery-core', "
		        jQuery(function($) {
		            // Add search input above widgets
		            var searchBox = $('<div style=\"border: 1px solid #c3c4c7; box-shadow: 0 1px 1px rgba(0, 0, 0, .04); background: #fff; padding: 20px 30px; margin: 0 0 15px;\"><input type=\"text\" id=\"widget-search\" placeholder=\"Search in widgets...\" style=\"width:100%;padding:6px 10px;margin:10px 0;border:1px solid #ccc;border-radius:4px;\"></div>');
		            $('#available-widgets').before(searchBox);

		            // Live filter widgets
		            $('#widget-search').on('keyup', function() {
		                var val = $(this).val().toLowerCase();
		                $('#available-widgets .widget').each(function() {
		                    var title = $(this).find('.widget-title').text().toLowerCase();
		                    var desc = $(this).find('.widget-description, .description').text().toLowerCase();
		                    $(this).toggle(title.indexOf(val) !== -1 || desc.indexOf(val) !== -1);
		                });
		            });
		        });
		    ");
		}

		wp_add_inline_script( 'iris', 'var xtraColor = window.Color, codevz_primary_color = "' . self::option( 'site_color', '#4e71fe' ) . '", codevz_secondary_color = "' . self::option( 'site_color_sec' ) . '";' );

	}

	/**
	 * Add/Remove custom sidebar
	 * 
	 * @return string
	 */
	public static function custom_sidebars() {

		$org_name = self::_GET( 'sidebar_name' );

		if ( $org_name ) {

			$name 		= sanitize_title_with_dashes( $org_name );
			$options 	= get_option( 'codevz_theme_options' );
			$sidebars 	= is_array( $options['custom_sidebars'] ) ? $options['custom_sidebars'] : [];

			if ( self::_GET( 'add_sidebar' ) ) {

				$sidebars[] = 'cz-custom-' . $name;
				$options['custom_sidebars'] = $sidebars;
				update_option( 'codevz_theme_options', $options );

				echo 'done';

			} else if ( self::_GET( 'remove_sidebar' ) ) {

				foreach ( $sidebars as $key => $sidebar ) {
					if ( $sidebar == $org_name || $sidebar == $name ) {
						unset( $sidebars[ $key ] );
					}
				}

				$options['custom_sidebars'] = $sidebars;
				update_option( 'codevz_theme_options', $options );

				echo 'done';
			}

		}

		wp_die();
	}

	/**
	 * Generates unique ID
	 * 
	 * @return string
	 */
	public static function uniqid( $prefix = 'cz' ) {
		return $prefix . wp_rand( 1111, 9999 );
	}

	/**
	 * Check if string contains specific value(s)
	 * 
	 * @return string
	 */
	public static function contains( $v = '', $a = [] ) {
		if ( $v ) {
			foreach ( (array) $a as $k ) {
				if ( $k && strpos( (string) $v, (string) $k ) !== false ) {
					return 1;
					break;
				}
			}
		}
		
		return null;
	}

	/**
	 * Shortcode output
	 * 
	 * @return string|null
	 */
	public static function _out( $a, $c = '', $s = '', $enqueue = '', $enqueue_extra = '' ) {

		// Check free version.
		if ( self::is_free() && self::$vc_editable && ! self::_GET( 'preview_id' ) ) {

			return self::pro_message( '<b>' . ucwords( str_replace( [ 'cz_', '_' ], [ '', ' ' ], $enqueue ) ) . '</b> ' . esc_html__( 'is a PRO element', 'codevz-plus' ) );

		}

		// Element assets.
		wp_enqueue_style( $enqueue );
		wp_enqueue_script( $enqueue );

		if ( $enqueue_extra ) {
			wp_enqueue_style( $enqueue_extra );
			wp_enqueue_script( $enqueue_extra );
		}

		if ( self::$is_rtl ) {
			wp_enqueue_style( $enqueue . '_rtl' );
			wp_enqueue_style( $enqueue_extra . '_rtl' );
		}

		$m = $p = $o = '';

		// Parallax
		$ph = empty( $a['parallax_h'] ) ? '' : $a['parallax_h'];
		$pp = empty( $a['parallax'] ) ? '' : $a['parallax'];
		$pp .= empty( $a['parallax_stop'] ) ? '' : ' cz_parallax_stop';

		if ( ! empty( $a['mparallax'] ) && self::contains( $ph, 'mouse' ) ) {
			$m = '<div class="cz_mparallax_' . $a['mparallax'] . '">';
		}

		if ( $pp ) {

			$d = ( $ph == 'true' || $ph === 'truemouse' ) ? 'h' : 'v';
			$p = '<div class="clr cz_parallax_' . $d . '_' . $pp . '">';

			wp_enqueue_style( 'cz_parallax' );
			wp_enqueue_script( 'cz_parallax' );

			$o .= 'Codevz_Plus.parallax();';

		}

		// Front-end JS.
		if ( self::$vc_editable ) {

			$c .= '<script>if( typeof Codevz_Plus !== "undefined" ) {';

			$c .= 'setTimeout( function() {';

			foreach ( (array) $s as $v ) {
				$c .= self::contains( $v, ')' ) ? 'Codevz_Plus.' . $v . ';' : ( $v ? 'if( typeof Codevz_Plus.' . $v . ' !== "undefined" ) {Codevz_Plus.' . $v . '();}' : '' );
			}

			$c .= $o . 'jQuery( window ).trigger( "scroll.codevz" );';

			$c .= '}, 10 );';

			$c .= '}</script>';

			$p = $p ? $p : '<div class="cz_wrap clr">';
		}

		return $m . $p . $c . ( $p ? '</div>' : '' ) . ( $m ? '</div>' : '' );

	}

	/**
	 * Generate inline data style or style tag depend on define
	 * 
	 * @param CSS
	 * @return string|null
	 */
	public static function data_stlye( &$d = '', &$t = '', &$m = '' ) {

		$out = '';

		// Page builder styles
		$d = empty( $d ) ? '' : $d;

		// Page builder tablet styles
		if ( ! empty( $t ) && substr( $t, 0, 1 ) !== '@' ) {
			$t = '@media screen and (max-width:' . self::option( 'tablet_breakpoint', '768px' ) . '){' . $t . '}';
		}

		// Page builder mobile styles
		if ( ! empty( $m ) && substr( $m, 0, 1 ) !== '@' ) {
			$m = '@media screen and (max-width:' . self::option( 'mobile_breakpoint', '480px' ) . '){' . $m . '}';
		}

		if ( ! self::$is_admin && ! self::$vc_editable && ! self::$preview ) {
			$out .= ( $d || $t || $m ) ? " data-cz-style='" . str_replace( "'", '"', $d . $t . $m ) . "'" : '';
		} else {
			$out .= $d ? '><style class="cz_d_css" data-noptimize>' . $d . "</style" : '';
			$out .= $t ? '><style class="cz_t_css" data-noptimize>' . $t . "</style" : '';
			$out .= $m ? '><style class="cz_m_css" data-noptimize>' . $m . "</style" : '';
		}

		return $out;

	}

	/**
	 *
	 * Generate titl data attributes for shortcode
	 * 
	 * @param $atts array
	 * @return string|null
	 * 
	 */
	public static function tilt( $atts ) {

		if ( ! empty( $atts['tilt'] ) ) {

			wp_enqueue_style( 'codevz-tilt' );
			wp_enqueue_script( 'codevz-tilt' );

			$out = ' data-tilt';

			if ( ! empty( $atts['glare'] ) ) {
				$out .= ( $atts['glare'] != '0' ) ? ' data-tilt-maxGlare="' . $atts['glare'] . '" data-tilt-glare="true"' : '';
			}

			if ( ! empty( $atts['scale'] ) ) {
				$out .= ( $atts['scale'] != '1' ) ? ' data-tilt-scale="' . $atts['scale'] . '"' : '';
			}

			return $out;

		}

	}

	/**
	 *
	 * Generate class attribute for element related to $atts
	 * 
	 * @param $atts array and classes array
	 * @return string|null
	 * 
	 */
	public static function classes( $a, $o = [], $i = 0 ) {
		$o[] = $i ? '' : ( isset( $a['class'] ) ? esc_attr( $a['class'] ) : '' );

		$hod = !empty( $a['hide_on_d'] );
		$hot = !empty( $a['hide_on_t'] );
		$hom = !empty( $a['hide_on_m'] );

		$o[] = $hod ? 'hide_on_desktop' : '';
		$o[] = $hot ? 'hide_on_tablet' : '';
		$o[] = $hom ? 'hide_on_mobile' : '';

		if ( $hod && $hot ) {
			$o[] = 'show_on_mobile';
		} else if ( $hod && $hom ) {
			$o[] = 'show_on_tablet';
		} else if ( $hot && $hom ) {
			$o[] = 'show_on_desktop';
		}

		// Check animation name
		if ( ! empty( $a['css_animation'] ) && $a['css_animation'] !== 'none' ) {
			
			// WPBakery old versions
			wp_enqueue_script( 'waypoints' );
			wp_enqueue_style( 'animate-css' );

			// WPBakery after v6.x
			wp_enqueue_script( 'vc_waypoints' );
			wp_enqueue_style( 'vc_animate-css' );

			// Classes
			$o[] = 'wpb_animate_when_almost_visible ' . $a['css_animation'];
		}

		return ' class="' . implode( ' ', array_filter( $o ) ) . '"';
	}

	/**
	 *
	 * Generate link attributes for element according to vc_build_link
	 * 
	 * @param 	$a = encoded link attributes
	 * @return 	String
	 * 
	 */
	public static function link_attrs( $a = '', $out = '' ) {

		if ( $a ) {

			$params_pairs = explode( '|', $a );

			$a = [
				'url' 		=> '',
				'title' 	=> '',
				'target' 	=> '',
				'rel' 		=> ''
			];

			if ( ! empty( $params_pairs ) ) {
				foreach ( $params_pairs as $pair ) {
					$param = preg_split( '/\:/', $pair );
					if ( ! empty( $param[0] ) && isset( $param[1] ) ) {
						$a[ $param[0] ] = trim( rawurldecode( $param[1] ) );
					}
				}
			}

			if ( empty( $a['url'] ) ) {
				return '';
			}

			$out .= ' href="' . do_shortcode( $a['url'] ) . '"';
			$out .= empty( $a['rel'] ) ? '' : ' rel="nofollow"';
			$out .= empty( $a['target'] ) ? '' : ' target="_blank"';

			if ( ! empty( $a['title'] ) ) {

				$out .= ' title="' . do_shortcode( esc_attr( $a['title'] ) ) . '"';
				$out .= ' aria-label="' . do_shortcode( esc_attr( $a['title'] ) ) . '"';

			}

			return $out;

		} else {

			return ' href="#"';

		}

	}

	/**
	 * Custom default colors for WP Colorpicker
	 * 
	 * @return string
	 */
	public static function wp_color_palettes() {
		if ( wp_script_is( 'wp-color-picker', 'enqueued' ) ) {
	?>
		<script>

			jQuery( function( $ ) {

				var primary_color = typeof codevz_primary_color == 'string' ? codevz_primary_color : '',
					secondary_color = typeof codevz_secondary_color == 'string' ? codevz_secondary_color : '',
					palettes = ['#000000','#FFFFFF','#E53935','#FF5722','#FFEB3B','#8BC34A','#3F51B5','#9C27B0',primary_color];

				if ( secondary_color ) {
					palettes.push( secondary_color );
				}

				jQuery.wp.wpColorPicker.prototype.options = {
					hide: true,
					palettes: palettes
				};

			});

		</script>
	<?php
		}
	}

	/**
	 * Add custom dropdown for admin CPT search.
	 * 
	 * @return string
	 */
	public function advanced_cpt_search_admin() {
	    $selected = isset($_GET['search_in']) ? esc_attr($_GET['search_in']) : '';
	    ?>
	    <script>
	    jQuery(document).ready(function($) {
	        var selected = "<?php echo $selected; ?>";
	        var $searchBox = $('#post-search-input');

	        if ($searchBox.length) {
	            var selectHtml = '<select name="search_in">'
	                + '<option value=""><?php esc_html_e( '~ Default ~', 'codevz-plus' ); ?></option>'
	                + '<option value="title"' + (selected==='title'?' selected':'') + '><?php esc_html_e( 'Title', 'codevz-plus' ); ?></option>'
	                + '<option value="content"' + (selected==='content'?' selected':'') + '><?php esc_html_e( 'Content', 'codevz-plus' ); ?></option>'
	                + '<option value="meta"' + (selected==='meta'?' selected':'') + '><?php esc_html_e( 'Meta', 'codevz-plus' ); ?></option>'
	                + '<option value="all"' + (selected==='all'?' selected':'') + '><?php esc_html_e( 'All', 'codevz-plus' ); ?></option>'
	                + '</select>';
	            
	            $searchBox.after(selectHtml);
	        }
	    });
	    </script>
	    <?php
	}

	/**
	 * Set settings for post types
	 * 
	 * @var  $query current page/post query
	 * @return array
	 */
	public static function pre_get_posts( $query ) {

		if ( is_admin() || empty( $query ) ) {
			return $query;
		}

		$query->query[ 'post_type' ] = isset( $query->query[ 'post_type' ] ) ? $query->query[ 'post_type' ] : 'post';

		// Set new settings for post types
		$cpt = (array) get_option( 'codevz_post_types' );
		$cpt[] = 'portfolio';

		// Custom post type UI
		if ( function_exists( 'cptui_get_post_type_slugs' ) ) {
			$cptui = cptui_get_post_type_slugs();
			if ( is_array( $cptui ) ) {
				$cpt = wp_parse_args( $cptui, $cpt );
			}
		}
		
		foreach( $cpt as $name ) {

			$ppp = self::option( 'posts_per_page_' . $name );
			$order = self::option( 'order_' . $name );
			$orderby = self::option( 'orderby_' . $name );

			$is_cpt = ( is_post_type_archive( $name ) && $query->query[ 'post_type' ] === $name );

			// Tax
			$is_tax = false;

			if ( ! empty( $query->tax_query->queries[0]['taxonomy'] ) ) {

				$taxonomy = $query->tax_query->queries[0]['taxonomy'];

				if ( isset( $query->query[ $taxonomy ] ) && self::contains( $taxonomy, $name ) ) {
					$is_tax = true;
				}
			}

			if ( $is_cpt || $is_tax ) {

				if ( $ppp ) {
					$query->set( 'posts_per_page', $ppp );
				}

				if ( $order ) {
					$query->set( 'order', $order );
				}

				if ( $orderby ) {
					$query->set( 'orderby', $orderby );
				}

			}

		}

		// Search
		if ( $query->is_main_query() && $query->is_search() ) {

			$search_cpt = self::option( 'search_cpt' );

			if ( $search_cpt ) {

				$query->set( 'post_type', explode( ',', str_replace( ' ', '', $search_cpt ) ) );

			} else if ( $query->query[ 'post_type' ] !== 'product' ) {

				$query->set( 'post_type', [ 'post', 'portfolio', 'product' ] );

			}

			$query->set( 'order', self::option( 'search_order' ) );
			$query->set( 'orderby', self::option( 'search_orderby' ) );
			$query->set( 'posts_per_page', self::option( 'search_count' ) );

			// In product category search.
			$prcat = self::_GET( 'prcat' );

			if ( $prcat ) {

				$query->set( 'tax_query', array(
					array(
						'taxonomy' => 'product_cat',
						'field'    => 'slug',
						'terms'    => $prcat
					)
				));

			}

		}

		// Admin CPT search.
	    global $pagenow;
	    if ( is_admin() && $pagenow === 'edit.php' && $query->is_main_query() && ! empty( $_GET['s'] ) && isset( $_GET['search_in'] ) ) {
	        $search_in = sanitize_text_field( $_GET['search_in'] );
	        $search_term = $query->get( 's' );

	        // Remove default search behavior
	        $query->set( 's', '' );

	        // Build custom meta_query or where clause
			add_filter( 'posts_where', function( $where ) use ( $search_term, $search_in ) {
			    global $wpdb;

			    // Escape search term properly
			    $search_term_raw = $search_term;
			    $search_term = $wpdb->esc_like( $search_term );
			    // Replace dot with % wildcard to make 5.0 match 5-anything-0
			    $search_term = str_replace('.', '%', $search_term);

			    $like_pattern = '%' . $search_term . '%';

			    if ( $search_in === 'title' ) {
			        $where .= " AND {$wpdb->posts}.post_title LIKE '{$like_pattern}'";
			    } elseif ( $search_in === 'content' ) {
			        $where .= " AND {$wpdb->posts}.post_content LIKE '{$like_pattern}'";
			    } elseif ( $search_in === 'meta' ) {
			        $where .= " AND {$wpdb->posts}.ID IN (
			            SELECT post_id FROM {$wpdb->postmeta}
			            WHERE meta_value LIKE '{$like_pattern}'
			        )";
			    } elseif ( $search_in === 'all' ) {
			        $where .= " AND (
			            {$wpdb->posts}.post_title LIKE '{$like_pattern}'
			            OR {$wpdb->posts}.post_content LIKE '{$like_pattern}'
			            OR {$wpdb->posts}.ID IN (
			                SELECT post_id FROM {$wpdb->postmeta}
			                WHERE meta_value LIKE '{$like_pattern}'
			            )
			        )";
			    }

			    return $where;
			});
	    }

		return $query;

	}

	public static function add_menu( $title, $page_title, $cap, $slug, $func, $icon, $priority ) {

		add_menu_page( $title, $page_title, $cap, $slug, $func, $icon, $priority );

	}

	public static function add_sub( $parent, $title, $page_title, $cap, $slug, $func ) {

		add_submenu_page( $parent, $title, $page_title, $cap, $slug, $func );

	}

	/**
	 * Get image sizes.
	 * 
	 * @return array|bool
	 */
	public static function getimagesize( $url = '' ) {

		// Skip.
		if ( self::contains( $url, '.svg' ) || is_customize_preview() ) {
			return false;
		}

		// Remove params. 
		$url = preg_replace( '/\?.*/', '', $url );

		// Get attachment ID.
		$attachment_id = attachment_url_to_postid( $url );

		// Check ID.
		if ( $attachment_id ) {

			// Get metadata.
			$meta = wp_get_attachment_metadata( $attachment_id );

			// Generate new metadata.
			if ( ! isset( $meta[ 'width' ] ) ) {

				// Generate new metadata for the image
				$meta = wp_generate_attachment_metadata( $attachment_id, get_attached_file( $attachment_id ) );

				// Update the metadata in the DB.
				if ( isset( $meta[ 'width' ] ) ) {
					wp_update_attachment_metadata( $attachment_id, $meta );
				}

			}

			// Return the dimenssions.
			if ( isset( $meta[ 'width' ] ) ) {

				return [ $meta['width'], $meta['height'] ];

			}

		}

		return false;

	}

	/**
	 * Check bots.
	 * 
	 * @return bool
	 */
	public static function is_speed() {

	    if ( is_admin() || empty( $_SERVER['HTTP_USER_AGENT'] ) ) {
	    	return false;
	    }

	    $ua = strtolower( $_SERVER['HTTP_USER_AGENT'] );

	    $bots = [
	        'lighthouse', 'gtmetrix', 'metrix', 'pingdom', 'webpagetest', 'pagespeed',
	        'chrome-lighthouse', 'chrome-lighthouse-testing', 'speedcurve',
	        'sitechecker', 'seo spider', 'ahrefs', 'semrush',
	        'facebookexternalhit', 'twitterbot', 'linkedinbot', 'slackbot',
	        'telegrambot', 'discordbot', 'whatsapp', 'ahrefsbot'
	    ];

	    foreach ($bots as $bot) {
	        if (strpos($ua, $bot) !== false) {
	            return true;
	        }
	    }

	    return false;
	}

	/**
	 * Maintenance mode redirect
	 * 
	 * @return -
	 */
	public static function template_redirect( $i ) {

		// Get option.
		$mt = self::option( 'maintenance_mode' );

		// Simple.
		if ( $mt === 'simple' && ! is_user_logged_in() ) {

			wp_die( wp_kses_post( (string) self::option( 'maintenance_message', esc_html__( 'We are under maintenance mode, We will back soon.', 'codevz-plus' ) ) ) );

		// Custom page.
		} else if ( $mt && $mt !== 'none' ) {

			$mt = self::get_page_by_title( $mt );

			if ( ! is_user_logged_in() && ! is_page( $mt ) ) {

				wp_redirect( get_the_permalink( $mt ) );
				exit;

			}

		}

		// Check post search, if all posts are products then redirect to woo search template.
		if ( is_search() && ! is_admin() && self::_GET( 's' ) && ! self::_GET( 'post_type' ) ) {

		    global $wp_query;

		    // Ensure there are search results
		    if ( ! empty( $wp_query->posts ) ) {

		        $post_types = array_map( 'get_post_type', wp_list_pluck( $wp_query->posts, 'ID' ) );

		        if ( count( array_unique( $post_types ) ) === 1 && in_array( 'product', $post_types ) ) {

		            // Redirect with 'post_type=product' parameter
		            $redirect_url = add_query_arg( [ 's' => self::_GET( 's' ), 'post_type' => 'product' ], get_home_url() );

		            wp_redirect( $redirect_url );
		            exit;
		        }

		    }

		}

		return $i;
	}

	// Include taxonomies into WP searches.
	public function posts_search( $search, $query ) {

		if ( ! is_admin() && $query->is_search() && $query->is_main_query() && isset( $query->query[ 'post_type' ] ) && $query->query[ 'post_type' ] == 'product' ) {

			$search_query = $query->get('s');

			if ( $search_query ) {

				$db = Codevz_Plus::database();

				$search_query = '%' . $db->esc_like( $search_query ) . '%';

				$search .= $db->prepare(
					" OR EXISTS (
						SELECT *
						FROM $db->postmeta
						WHERE meta_key = '_sku'
						AND meta_value LIKE %s
					)",
					$search_query
				);

			}

		}

		return $search;

	}

	/**
	 * Ajax search process
	 * 
	 * @return string
	 */
	public static function ajax_search() {

		check_ajax_referer( 'ajax_search_nonce', 'nonce' );

		$l = self::option( 'search_count', 4 );
		$s = sanitize_text_field( $_GET['s'] );
		$c = explode( ',', str_replace( ' ', '', esc_html( self::option( 'search_cpt', 'any' ) ) ) );
		$o = self::option( 'search_order' );
		$ob = self::option( 'search_orderby' );
		$nt = empty( $_GET['no_thumbnail'] ) ? 0 : 1;
		$pi = empty( $_GET['search_post_icon'] ) ? 0 : esc_attr( $_GET['search_post_icon'] );
		$pis = empty( $_GET['sk_search_post_icon'] ) ? 0 : self::sk_inline_style( esc_attr( $_GET['sk_search_post_icon'] ) );

		if ( ! empty( $_GET['post_type'] ) ) {
			$c = $_GET['post_type'];
		}

		// Search in posts.
		$posts = get_posts(
			[
				's' 			=> $s,
				'post_type' 	=> $c,
				'order' 		=> $o,
				'orderby' 		=> $ob,
				'showposts' 	=> $l,
				'posts_per_page'=> $l,
				'fields' 		=> 'ids'
			]
		);

		// Search in tags.
		$terms = get_posts(
			[
				'post_type' 	=> $c,
				'order' 		=> $o,
				'orderby' 		=> $ob,
				'showposts' 	=> $l,
				'posts_per_page'=> $l,
				'fields' 		=> 'ids',
				'post__not_in' 	=> $posts,
				'tax_query' 	=> [
					[
						'taxonomy' 	=> 'post_tag',
						'field' 	=> 'slug',
						'terms' 	=> get_terms(
							array(
								'taxonomy' 		=> 'post_tag',
								'fields' 		=> 'slugs',
								'name__like' 	=> $s
							)
						)
					],
				],
			]
		);

		// Mix posts.
		$posts = wp_parse_args( $posts, $terms );

		// Filter for custom product category.
		if ( ! empty( $_GET['prcat'] ) ) {

			$terms = get_posts(array(
				'post_type'      => 'product',
				'showposts' 	 => $l,
				'posts_per_page' => $l,
				's' 			 => $s,
				'fields' 		 => 'ids',
				'tax_query'      => array(
					array(
						'taxonomy' => 'product_cat',
						'field'    => 'slug',
						'terms'    => esc_attr( $_GET['prcat'] )
					)
				)
			));

			$posts = [];

		}

		// Mix posts.
		$posts = wp_parse_args( $posts, $terms );

		// Search in products SKU.
		$db = self::database();
		$terms = array_column(
			$db->get_results(
				$db->prepare(
					"SELECT post_id FROM $db->postmeta WHERE meta_key = '_sku' AND meta_value LIKE %s",
					'%' . $db->esc_like( $s ) . '%'
				)
			),
			'post_id'
		);

		// Mix all posts.
		$posts = array_unique( wp_parse_args( $posts, $terms ) );

		// Posts found.
		if ( ! empty( $posts ) ) {

			$i = 0;
			foreach( $posts as $post_id ) {

				$post = get_post( $post_id );
				$cpt = self::get_post_type( $post_id );

				if ( $cpt === 'product' ) {
					$product = wc_get_product( $post_id );
				}

				if ( ( $cpt === 'page' && ! in_array( 'page', $c ) ) || $cpt === 'dwqa-answer' ) {
					continue;
				}

				echo '<div id="post-' . esc_attr( $post_id ) . '" class="item_small">';

				if ( has_post_thumbnail( $post_id ) && ! $nt ) {
					echo '<a class="theme_img_hover" href="' . esc_url( get_the_permalink( $post_id ) ) . '"><img loading="lazy" src="' . esc_url( get_the_post_thumbnail_url( $post_id, 'thumbnail' ) ) . '" width="80" height="80" /></a>';
				} else if ( $pi ) {
					echo '<a class="xtra-ajax-search-post-icon" href="' . esc_url( get_the_permalink( $post_id ) ) . '" style="' . esc_html( $pis ) . '"><span class="' . esc_attr( $pi ) . '"></span></a>';
				}

				echo do_shortcode( apply_filters( 'cz_ajax_search_instead_img', '' ) );
				echo '<div class="item-details">';
				echo '<h3><a href="' . esc_url( get_the_permalink( $post_id ) ) . '" rel="bookmark">' . wp_kses_post( (string) $post->post_title ) . '</a></h3>';

				if ( $cpt === 'product' ) {

					$sku = $product->get_sku();

					if ( $sku ) {
						echo '<small>' . esc_html__( 'SKU', 'codevz-plus' ) . ': ' . esc_html( $sku ) . '</small>';
					}

				}

				if ( $cpt === 'dwqa-question' ) {
					$cpt_name = esc_html__( 'Questions', 'codevz-plus' );
				} else if ( $cpt === 'product' ) {
					$cpt_name = esc_html__( 'Product', 'codevz-plus' );
				} else {
					$cpt_name = $cpt;
				}

				echo '<span class="cz_search_item_cpt mr10"><i class="fa fa-folder-o mr4"></i>' . esc_html( ucfirst( $cpt_name ) ) . '</span>';

				if ( $cpt === 'post' ) {

					echo '<span><i class="fa fa-clock-o mr4"></i>' . esc_html( get_the_date( false, $post_id ) ) . '</span>';

				} else if ( $cpt === 'product' ) {

					if ( $product->get_price_html() ) {
						echo '<span><i class="fa czico-036-commerce-6 mr4"></i>' . do_shortcode( $product->get_price_html() ) . '</span>';
					}

				}

				echo '</div></div>';

				$i++;

			}

			if ( $i === 0 ) {

				echo '<b class="ajax_search_error">' . esc_html( self::option( 'not_found', 'Not found!' ) ) . '</b>';
			
			} else if ( count( $posts ) >= $l ) {

				unset( $_GET['action'] );
				unset( $_GET['nonce'] );
				echo '<a class="va_results" href="' . esc_attr( trailingslashit( get_home_url() ) . '?' . http_build_query( $_GET ) ) . '"> ... </a>';

			}

		} else {

			echo '<b class="ajax_search_error">' . esc_html( self::option( 'not_found', 'Not found!' ) ) . '</b>';

		}

		do_action( 'codevz_ajax_search_after', $s );

		wp_die();
	}

	/**
	 * Generate social icons
	 * @return string
	 */
	public static function social( $args = [], $echo = false, $out = '' ) {

		$social = self::option( 'social' );

		if ( is_array( $social ) ) {

			$tooltip = self::option( 'social_tooltip' );

			$classes = [];
			$classes[] = 'cz_social';
			$classes[] = empty( $args[ 'color_mode' ] ) ? self::option( 'social_color_mode' ) : $args[ 'color_mode' ];
			$classes[] = self::option( 'social_hover_fx' );
			$classes[] = empty( $args[ 'type' ] ) ? '' : 'xtra-social-type-' . esc_attr( $args[ 'type' ] );
			$classes[] = empty( $args[ 'columnar' ] ) ? '' : 'xtra-social-columnar';
			$classes[] = self::option( 'social_inline_title' ) ? 'cz_social_inline_title' : '';
			$classes[] = $tooltip;

			$out .= '<div class="' . esc_attr( implode( ' ', array_filter( $classes ) ) ) . '">';

			foreach( $social as $soci ) {

				$soci[ 'icon' ] = empty( $soci[ 'icon' ] ) ? '' : $soci[ 'icon' ];
				$soci[ 'link' ] = empty( $soci[ 'link' ] ) ? '' : $soci[ 'link' ];
				$soci[ 'title' ] = empty( $soci[ 'title' ] ) ? '' : $soci[ 'title' ];

				$social_link_class = 'cz-' . str_replace( self::$social_fa_upgrade, '', esc_attr( $soci['icon'] ) );

				$target = ( Codevz_Plus::contains( $soci['link'], [ $_SERVER['HTTP_HOST'], 'tel:', 'mailto:' ] ) || $soci['link'] === '#' ) ? '' : ' target="_blank" rel="noopener noreferrer nofollow"';

				$out .= '<a class="' . esc_attr( $social_link_class ) . '" href="' . esc_attr( $soci['link'] ) . '" ' . ( $tooltip ? 'data-' : '' ) . 'title="' . esc_attr( $soci['title'] ) . '"' . ( $soci['title'] ? ' aria-label="' . esc_attr( $soci['title'] ) . '"' : '' ) . $target . '><i class="' . esc_attr( $soci['icon'] ) . '"></i><span>' . esc_html( $soci['title'] ) . '</span></a>';

			}

			$out .= '</div>';

		}

		if ( $echo ) {

			echo do_shortcode( $out );

		} else {

			return do_shortcode( $out );

		}

	}

	/**
	 * Content box effects
	 * @return array
	 */
	public static function fx( $hover = '' ) {
		$i = array(
			esc_html__( '~ Select ~', 'codevz-plus' ) 		=> '',
			esc_html__( 'Zoom', 'codevz-plus' ) . ' 1' 		=> 'fx_zoom_0' . $hover,
			esc_html__( 'Zoom', 'codevz-plus' ) . ' 2' 		=> 'fx_zoom_1' . $hover,
			esc_html__( 'Zoom', 'codevz-plus' ) . ' 3' 		=> 'fx_zoom_2' . $hover,
			esc_html__( 'Move up', 'codevz-plus' ) 		=> 'fx_up' . $hover,
			esc_html__( 'Move right', 'codevz-plus' ) 	=> 'fx_right' . $hover,
			esc_html__( 'Move down', 'codevz-plus' ) 		=> 'fx_down' . $hover,
			esc_html__( 'Move left', 'codevz-plus' ) 		=> 'fx_left' . $hover,
			esc_html__( 'Border inner', 'codevz-plus' ) 	=> 'fx_inner_line' . $hover,
			esc_html__( 'Grayscale', 'codevz-plus' ) 		=> 'fx_grayscale' . $hover,
			esc_html__( 'Remove Grayscale', 'codevz-plus' ) => 'fx_remove_grayscale' . $hover,
			esc_html__( 'Skew left', 'codevz-plus' ) 		=> 'fx_skew_left' . $hover,
			esc_html__( 'Skew right', 'codevz-plus' ) 	=> 'fx_skew_right' . $hover,
			esc_html__( 'Bob loop', 'codevz-plus' ) 		=> 'fx_bob' . $hover,
			esc_html__( 'Low opacity', 'codevz-plus' ) 	=> 'fx_opacity' . $hover,
		);

		if ( $hover ) {
			$i = array_merge( $i, array(
				esc_html__( 'Full opacity', 'codevz-plus' ) 		=> 'fx_full_opacity',
				esc_html__( '360° Z', 'codevz-plus' ) 			=> 'fx_z_hover',
				esc_html__( 'Bounce', 'codevz-plus' ) 			=> 'fx_bounce_hover',
				esc_html__( 'Shine', 'codevz-plus' ) 				=> 'fx_shine_hover',
				esc_html__( 'Grow rotate right', 'codevz-plus' ) 	=> 'fx_grow_rotate_right_hover',
				esc_html__( 'Grow rotate left', 'codevz-plus' ) 	=> 'fx_grow_rotate_left_hover',
				esc_html__( 'Wobble skew', 'codevz-plus' ) 		=> 'fx_wobble_skew_hover',
			) );
		}

		return $i;
	}
	
	/**
	 * Get RGB numbers of HEX color
	 * @var Hex color code
	 * @return string
	 */
	public static function hex2rgba( $c = '', $o = '1' ) {
		if ( empty( $c[0] ) ) {
			return '';
		}
		
		$c = substr( $c, 1 );
		if ( strlen( $c ) == 6 ) {
			list( $r, $g, $b ) = array( $c[0] . $c[1], $c[2] . $c[3], $c[4] . $c[5] );
		} elseif ( strlen( $c ) == 3 ) {
			list( $r, $g, $b ) = array( $c[0] . $c[0], $c[1] . $c[1], $c[2] . $c[2] );
		} else {
			return false;
		}
		$r = hexdec( $r );
		$g = hexdec( $g );
		$b = hexdec( $b );

		return 'rgba(' . implode( ',', array( $r, $g, $b ) ) . ',' . $o . ')';
	}

	/**
	 * List of safe fonts and skip google from loading them. 
	 * 
	 * @return ARRAY
	 */
	public static function web_safe_fonts() {

		$websafe = array(
			'inherit' 				=> 'inherit', 
			'initial' 				=> 'initial', 
			'FontAwesome' 			=> 'FontAwesome', 
			'Font Awesome 6 Free' 	=> 'Font Awesome 6 Free', 
			'czicons' 				=> 'czicons', 
			'fontelo' 				=> 'fontelo',
			'Arial' 				=> 'Arial',
			'Arial Black' 			=> 'Arial Black',
			'Comic Sans MS' 		=> 'Comic Sans MS',
			'Impact' 				=> 'Impact',
			'Lucida Sans Unicode'	=> 'Lucida Sans Unicode',
			'Tahoma' 				=> 'Tahoma',
			'Trebuchet MS' 			=> 'Trebuchet MS',
			'Verdana' 				=> 'Verdana',
			'Courier New' 			=> 'Courier New',
			'Lucida Console' 		=> 'Lucida Console',
			'Georgia, serif' 		=> 'Georgia, serif',
			'Palatino Linotype' 	=> 'Palatino Linotype',
			'Times New Roman' 		=> 'Times New Roman'
		);

		// Custom fonts
		$custom_fonts = (array) self::option( 'upload_custom_fonts' );
		foreach ( $custom_fonts as $a ) {
			if ( ! empty( $a['font'] ) ) {
				$websafe[ $a['font'] ] = $a['font'];
			}
		}

		return apply_filters( 'codevz/field/fonts/websafe', $websafe );

	}

	/**
	 * Enqueue google font
	 * 
	 * @return string|null
	 */
	public static function load_font( $f = '' ) {

		if ( ! $f || self::contains( $f, 'custom_' ) ) {
			return;
		} else {
			$f = self::contains( $f, ';' ) ? self::get_string_between( $f, 'font-family:', ';' ) : $f;
			$f = $f ? str_replace( '=', ':', $f ) : '';
			$f = $f ? str_replace( '\\', '', $f ) : '';
		}

		$skip = self::web_safe_fonts();

		// Custom fonts
		$custom_fonts = (array) self::option( 'upload_custom_fonts' );
		foreach ( $custom_fonts as $a ) {
			if ( ! empty( $a['font'] ) ) {
				$skip[ $a['font'] ] = $a['font'];
			}
		}

		$f = self::contains( $f, ':' ) ? $f : $f . ':300,400,700';
		$f = explode( ':', $f );
		$p = empty( $f[1] ) ? '' : ':' . trim( $f[1] );

		$font = isset( $f[0] ) ? trim( $f[0] ) : '';
		$font = str_replace( [ '"', "'" ], '', $font );

		$disable = apply_filters( 'codevz/disable/google_fonts', self::option( 'disable_loading_google_fonts', false ) );

		if ( $font && ! $disable && ! isset( $skip[ $font ] ) && ! self::contains( $font, 'sc_A' ) ) {
			wp_enqueue_style( 'google-font-' . sanitize_title_with_dashes( $font ), 'https://fonts.googleapis.com/css?family=' . str_replace( [ '"', "'" ], '', str_replace( ' ', '+', ucfirst( $font ) ) ) . $p, [], self::$ver );
		}

	}

	/**
	 *
	 * SK Style + load font
	 * 
	 * @return string
	 *
	 */
	public static function sk_inline_style( $sk = '', $important = false ) {
		$sk = str_replace( 'CDVZ', '', $sk );

		if ( self::contains( $sk, 'font-family' ) ) {

			self::load_font( $sk );

			// Font + params && Fix font for CSS
			$font = $o_font = self::get_string_between( $sk, 'font-family:', ';' );
			$font = str_replace( '=', ':', $font );
			$font = str_replace( "''", "", $font );
			$font = str_replace( "'", "", $font );
			$font = str_replace( "\\", "", $font );

			if ( self::contains( $font, ':' ) ) {

				$font = explode( ':', $font );

				if ( ! empty( $font[0] ) ) {

					if ( ! self::contains( $font[0], "'" ) ) {
						$font[0] = "'" . $font[0] . "'";
					}

					$sk = str_replace( $o_font, $font[0], $sk );

				}

			} else {

				if ( ! self::contains( $font, "'" ) ) {
					$font = "'" . $font . "'";
				}

				$sk = str_replace( $o_font, $font, $sk );

			}

		}

		if ( $important ) {
			$sk = str_replace( ';', ' !important;', $sk );
		}

		if ( self::$is_rtl ) {
			return str_replace( 'RTL', '', $sk );
		} else if ( self::contains( $sk, 'RTL' ) ) {
			return strstr( $sk, 'RTL', true );
		} else {
			return $sk;
		}
	}

	/**
	 *
	 * Return full CSS with selector and fixes plus loading fonts
	 * 
	 * @return string|null
	 * 
	 */
	public static function sk_style( $atts = [], $ids = [], $device = '' ) {
		$out = $rtl = '';
		foreach ( (array) $ids as $id => $selector ) {
			$is_array = is_array( $selector );
			$val = empty( $atts[ $id . $device ] ) ? '' : str_replace( "``", '"', $atts[ $id . $device ] );

			if ( $val ) {
				$val = str_replace( 'CDVZ', '', $val );

				// RTL
				if ( self::contains( $val, 'RTL' ) ) {
					$rtl = self::get_string_between( $val, 'RTL', 'RTL' );
					$val = str_replace( array( $rtl, 'RTL' ), '', $val );
				}

				// Fix and load google font
				if ( self::contains( $val, 'font-family' ) ) {
					self::load_font( $val ); // Enqueue font

					// Font + params && Fix font for CSS
					$font = $o_font = self::get_string_between( $val, 'font-family:', ';' );
					$font = str_replace( '=', ':', $font );
					$font = str_replace( 'custom_', '', $font );
					$font = str_replace( "''", "", $font );
					$font = str_replace( "'", "", $font );
					$font = str_replace( "\\", "", $font );

					if ( self::contains( $font, ':' ) ) {
						$font = explode( ':', $font );
						if ( ! empty( $font[0] ) ) {
							$val = str_replace( $o_font, "'" . $font[0] . "'", $val );
						}
					} else {
						$val = str_replace( $font, $font, $val );
					}
				}

				if ( $is_array ) {
					if ( ! $device ) {
						$val .= $selector[1];
					}
					$selector = $selector[0];
				}

				// SVG background layer
				if ( self::contains( $id, 'svg_bg' ) ) {
					$type = self::contains( $val, '_class_svg_type' ) ? self::get_string_between( $val, '_class_svg_type:', ';' ) : '';
					$size = ( $type === 'circle' ) ? '3' : '1';
					$size = self::contains( $val, '_class_svg_size' ) ? self::get_string_between( $val, '_class_svg_size:', ';' ) : '1';
					$color = self::contains( $val, '_class_svg_color' ) ? self::get_string_between( $val, '_class_svg_color:', ';' ) : '#222';
					$color = self::contains( $color, 'rgba' ) ? $color : self::hex2rgba( $color );

					if ( $type === 'circle' ) {
						$base = base64_encode( "<svg xmlns='http://www.w3.org/2000/svg' width='20' height='24'><circle cx='6' cy='6' r='" . $size . "' fill='none' stroke='" . $color . "' stroke-width='1' /></svg>" );
						$val .= 'background-image: url("data:image/svg+xml;base64,' . $base . '");';
					} else if ( $type === 'dots' ) {
						$base = base64_encode( "<svg xmlns='http://www.w3.org/2000/svg' width='20' height='24'><circle cx='6' cy='6' r='" . $size . "' fill='" . $color . "' /></svg>" );
						$val .= 'background-image: url("data:image/svg+xml;base64,' . $base . '");';
					} else if ( $type === 'x' ) {
						$base = base64_encode( "<svg width='24' height='24' xmlns='http://www.w3.org/2000/svg'><path d='M4.01,15.419L15.419,4.01l0.57,0.57L4.581,15.99Z' stroke='" . $color . "' stroke-width='" . $size . "'></path><path d='M15.419,15.99L4.01,4.581l0.57-.57L15.99,15.419Z' stroke='" . $color . "' stroke-width='" . $size . "'></path></svg>" );
						$val .= 'background-image: url("data:image/svg+xml;base64,' . $base . '");';
					} else if ( $type === 'line' ) {
						$base = base64_encode( "<svg width='24' height='24' xmlns='http://www.w3.org/2000/svg'><path d='M4.01,15.419L15.419,4.01l0.57,0.57L4.581,15.99Z' stroke='" . $color . "' stroke-width='" . $size . "'></path></svg>" );
						$val .= 'background-image: url("data:image/svg+xml;base64,' . $base . '");';
					}

					// Remove unwanted in css
					if ( self::contains( $val, '_class_' ) ) {
						$val = preg_replace( '/_class_[\s\S]+?;/', '', $val );
					}
				}

				// Append CSS
				$out .= $selector . '{' . $val . '}';

				// RTL
				if ( $rtl ) {
					$sp = self::contains( $selector, array( '.cz-cpt-', '.cz-page-', '.home', 'body', '.woocommerce' ) ) ? '' : ' ';
					$out .= '.rtl' . $sp . preg_replace( '/,\s+|,/', ',.rtl' . $sp, $selector ) . '{' . $rtl . '}';
				}
				$rtl = 0;

			} else if ( $is_array && ! $device && $selector[1] ) {
				$out .= $selector[0] . '{' . $selector[1] . '}';
			}
		}

		return str_replace( ';}', '}', $out );
	}

	/**
	 * Fix: Remove extra <p> and </p> from content of elements
	 * 
	 * @return string
	 */
	public static function fix_extra_p( $content = '' ) {
		return preg_replace( '/^<\/p>\n|<p>$/', '', $content );
	}

	/**
	 * Get string between two string
	 * 
	 * @return string
	 */
	public static function get_string_between( $c = '', $s = '', $e = '', $m = false ) {

		if ( $c ) {

			if ( $m ) {
				preg_match_all( '~' . preg_quote( $s, '~' ) . '(.*?)' . preg_quote( $e, '~' ) . '~s', $c, $matches );
				return $matches[0];
			}

			$r = explode( $s, $c );
			if ( isset( $r[1] ) ) {
				$r = explode( $e, $r[1] );
				return $r[0];
			}
		}

		return;
	}

	/**
	 * Get image by id or url
	 * 
	 * @var $i image ID or image url
	 * @var $s image size
	 * @var $url only return url of image
	 * @var $c custom class for image
	 * @return string
	 */
	public static function get_image( $i = '', $s = 0, $url = 0, $c = '' ) {

		if ( wp_get_attachment_image_src( $i ) && function_exists( 'wpb_getImageBySize' ) && ! self::contains( $i, '.' ) ) {

			$wpb_image = wpb_getImageBySize(
				[
					'attach_id' 	=> empty( $i ) ? null : $i,
					'thumb_size' 	=> empty( $s ) ? 'full' : $s,
					'class' 		=> $c
				]
			);

			if ( isset( $wpb_image['thumbnail'] ) ) {

				if ( self::contains( $wpb_image['thumbnail'], 'src=""' ) ) {
					$i = wp_get_attachment_image( $i, $s );
				} else {
					$i = $wpb_image['thumbnail'];
				}

			}

		} else if ( is_numeric( $i ) ) {

			$i = wp_get_attachment_image( $i, $s );

		}

		if ( empty( $i ) ) {

			$i = '<img loading="lazy" src="' . self::$url . 'assets/img/p.svg' . '" class="xtra-placeholder ' . $c . '" width="1000" height="1000" alt="image" />';

		} else if( ! self::contains( $i, 'src' ) ) {

			$i = '<img loading="lazy" src="' . $i . '" class="' . $c . '" width="500" height="500" alt="image" />';
		
		}

		return $url ? self::get_string_between( $i, 'src="', '"' ) : $i;
	}

	/**
	 * Get post data
	 * 
	 * @return string
	 */
	public static function get_post_data( $id, $w = 'date', $s = 0, $c = '', $ic = '', $tc = '' ) {

		$cls = $w;
		$w = self::contains( $w, ' ' ) ? substr( $w, 0, strpos( $w, ' ' ) ) : $w;

		if ( $w === 'date' || $w === 'date_1' ) {

			$date = get_the_date();
			$out = $s ? $date : '<a href="' . get_the_permalink( $id ) . '" aria-label="Post date">' . $date . '</a>';

		} else if ( $w === 'cats' || $w === 'cats_1' ) {

			$cpt = get_post_type( $id );
			$tax = ( empty( $cpt ) || $cpt === 'post' ) ? 'category' : $cpt . '_cat';
			$cats = get_the_term_list( $id, $tax, '', ', ', '');
			if ( is_string( $cats ) ) {
				$out = $s ? wp_strip_all_tags( $cats ) : $cats;
			}
			
		} else if ( self::contains( $w, array( 'cats_2', 'cats_3', 'cats_4', 'cats_5', 'cats_6', 'cats_7' ) ) ) {

			$out = self::get_cats( $id, $w, $s, $tc );

		} else if ( $w === 'tags' ) {

			$out = self::get_tags( $id, $s, $tc );
			
		} else if ( $w === 'author' ) {

			$author = get_the_author_meta( 'display_name', $id );
			$out = $s ? $author : '<a href="' . get_author_posts_url( $id ) . '" aria-label="Post author">' . $author . '</a>';
			
		} else if ( $w === 'author_avatar' || $w === 'author_full_date' || $w === 'author_icon_date' ) {

			$author = get_the_author_meta( 'display_name', $id );
			$avatar = ( $ic && $w === 'author_icon_date' ) ? '<i class="cz_sub_icon fa ' . $ic . '"></i>' : get_avatar( $id, 30 );
			$link = get_author_posts_url( $id );

			if ( $s ) {
				$out = '<span class="cz_post_author_avatar">' . $avatar . '</span>';
				$out .= '<span class="cz_post_inner_meta">';
				$out .= '<span class="cz_post_author_name">' . $author . '</span>';
				if ( $w === 'author_full_date' || $w === 'author_icon_date' ) {
					$out .= '<span class="cz_post_date">' . get_the_date() . '</span>';
				}
				$out .= '</span>';
			} else {
				$out = '<a class="cz_post_author_avatar" href="' . $link . '">' . $avatar . '</a>';
				$out .= '<span class="cz_post_inner_meta">';
				$out .= '<a class="cz_post_author_name" href="' . $link . '">' . $author . '</a>';
				if ( $w === 'author_full_date' || $w === 'author_icon_date' ) {
					$out .= '<span class="cz_post_date">' . get_the_date() . '</span>';
				}
				$out .= '</span>';
			}

		} else if ( $w === 'price' ) {

			if ( function_exists( 'get_woocommerce_currency_symbol' ) && function_exists( 'wc_get_product' ) ) {

				$_product = wc_get_product( $id );

				if ( ! empty( $_product ) ) {

					$cx = get_woocommerce_currency_symbol();

					$price = get_post_meta( $id, '_regular_price', true );
					$sale  = get_post_meta( $id, '_sale_price', true );

					// Variation.
					if ( ! $price && ( $_product->is_type( 'variable' ) || $_product->is_type( 'variation' ) ) ) {

						$price = $cx . ( (int) $_product->get_variation_regular_price() );
						$price .= ' - ' . $cx . ( (int) $_product->get_variation_regular_price( 'max' ) );

						$cx = '';

					}

					$out = $sale ? '<del><span>' . $cx . '</span>' . $price . '</del> ' . '<span>' . $cx . '</span>' . $sale : '<span>' . $cx . '</span>' . $price;

				}

			} else {
				$out = '---';
			}

		} else if ( $w === 'add_to_cart' ) {

			$out = do_shortcode( '[add_to_cart id="' . $id . '" style=""]' );

		} else if ( $w === 'comments' ) {

			$cm = number_format_i18n( get_comments_number( $id ) );
			$out = $s ? $cm . ' ' . $c : '<a href="' . get_the_permalink( $id ) . '#comments" aria-label="Comments">' . $cm . ' ' . $c . '</a>';
			
		} else if ( $w === 'custom_text' ) {
			
			$out = do_shortcode( $s );
		
		} else if ( $w === 'custom_meta' ) {
			
			$out = (string) get_post_meta( $id, $s, true );
		
		}

		// Icon.
		$ic = is_array( $ic ) ? $ic[ 'value' ] : $ic;

		// Prefix
		$pre = ( $ic && ! self::contains( $w, 'author_' ) ) ? '<i class="cz_sub_icon mr8 fa ' . $ic . '"></i>' : '';
		$pre .= ( $c && $w !== 'comments' ) ? '<span class="cz_data_sub_prefix mr4">' . $c . '</span>' : '';

		// Out
		return isset( $out ) ? '<span class="cz_post_data cz_data_' . $cls . '">' . $pre . $out . '</span>' : '';

	}

	/**
	 * Get post categories include colors
	 * 
	 * @return string
	 */
	public static function get_cats( $id, $style = '', $no_link = 0, $l = 10, $out = [] ) {

		$cpt = get_post_type( $id );
		$tax = ( empty( $cpt ) || $cpt === 'post' ) ? 'category' : $cpt . '_cat';

		$terms = get_the_terms( $id, $tax );

		if ( $terms ) {

			foreach( $terms as $term ) {

				if ( isset( $term->term_id ) ) {

					$color = get_term_meta( $term->term_id, 'codevz_cat_meta', true );
					$opacity = self::contains( $style, array( '6', '7' ) ) ? '1' : '0.1';
					$color = empty( $color['color'] ) ? '' : ' style="color:' . $color['color'] . ';border-color:' . $color['color'] . ';background: ' . self::hex2rgba( $color['color'], $opacity ) . '"';
					$out[] = $no_link ? '<span' . $color . '>' . $term->name . '</span>' : '<a aria-label="Post category" href="' . get_term_link( $term ) . '"' . $color . '>' . $term->name . '</a>';

				}

			}

		}

		$out = implode( '', array_slice( $out, 0, $l ) );

		return $out ? '<span class="cz_cats_2 cz_' . $style . '">' . $out . '</span>' : '';
	}

	/**
	 * Get post tags
	 * 
	 * @return string
	 */
	public static function get_tags( $id, $no_link = 0, $l = 10, $out = [] ) {

		$tax = get_object_taxonomies( get_post_type( $id ), 'objects' );

		foreach ( $tax as $tax_slug => $taks ) {

			if ( self::contains( $tax_slug, 'tag' ) ) {

				$terms = get_the_terms( $id, $tax_slug );

				if ( ! empty( $terms ) ) {
					foreach ( $terms as $term ) {
						$out[] = $no_link ? '#' . esc_html( $term->name ) . ' ' : '<a aria-label="Post tag" href="' . esc_url( get_term_link( $term->slug, $tax_slug ) ) . '">#' . esc_html( $term->name ) . '</a> ';
					}
				}

			}

		}

		$out = implode( '', array_slice( $out, 0, $l ) );

		return $out ? '<span class="cz_ptags">' . $out . '</span>' : '';

	}

	/**
	 * Limit words of string
	 * 
	 * @return string
	 */
	public static function read_more_button() {

		$more = '';
		$cpt = get_post_type();

		if ( $cpt && $cpt !== 'post' ) {
			$title = esc_html( self::option( 'readmore_' . $cpt ) );
			$icon = esc_attr( self::option( 'readmore_icon_' . $cpt ) );
		} else {
			$title = esc_html( self::option( 'readmore' ) );
			$icon = esc_attr( self::option( 'readmore_icon' ) );
		}

		$icon = $icon ? '<i class="' . $icon . '" aria-hidden="true"></i>' : '';

		return ( $title || $icon ) ? '<a class="cz_readmore' . ( $title ? '' : ' cz_readmore_no_title' ) . ( $icon ? '' : ' cz_readmore_no_icon' ) . '" href="' . esc_url( get_the_permalink() ) . esc_attr( $more ) . '">' . $icon . '<span>' . $title . '</span></a>' : '';

	}

	/**
	 * Limit words of string
	 * 
	 * @return string
	 */
	public static function limit_words( $string = '', $length = 12, $read_more = null ) {

		$count = count( (array) preg_split( '~[^\p{L}\p{N}\']+~u', $string ) ) - 1;

		// String length
		$length--;
		if ( $count > $length ) {
			$string = wp_strip_all_tags( $string );
			$string = preg_replace( '/((\w+\W*){' . $length . '}(\w+))(.*)/u', '${1}', $string ) . ' ...';
		}

		// Add read more
		if ( $read_more ) {
			$string .= self::read_more_button();
		}

		// Out
		return str_replace( [ '... ', 'Array' ], '', $string );
	}

	/**
	 * Register new Post types
	 * 
	 * @return object
	 */
	public static function post_types() {

		// Other post types.
		$options 	= (array) get_option( 'codevz_theme_options' );
		$post_types = (array) get_option( 'codevz_post_types' );

		$post_types[99] = 'portfolio';

		if ( self::option( 'disable_portfolio' ) ) {
			unset( $post_types[99] );
		}

		foreach ( $post_types as $cpt ) {

			if ( empty( $cpt ) ) {
				continue;
			}

			$cpt = strtolower( str_replace( ' ', '_', $cpt ) );

			$opt = array(
				'slug' 			=> empty( $options[ 'slug_' . $cpt ] ) ? $cpt : $options[ 'slug_' . $cpt ], 
				'title' 		=> empty( $options[ 'title_' . $cpt ] ) ? ( $cpt === 'portfolio' ? esc_html__( 'Portfolio', 'codevz-plus' ) : ucwords( $cpt ) ) : $options[ 'title_' . $cpt ], 
				'cat_slug' 		=> empty( $options[ 'cat_' . $cpt ] ) ? $cpt . '/cat' : $options[ 'cat_' . $cpt ], 
				'cat_title' 	=> empty( $options[ 'cat_title_' . $cpt ] ) ? esc_html__( 'Categories', 'codevz-plus' ) : $options[ 'cat_title_' . $cpt ], 
				'tags_slug' 	=> empty( $options[ 'tags_' . $cpt ] ) ? $cpt . '/tags' : $options[ 'tags_' . $cpt ], 
				'tags_title' 	=> empty( $options[ 'tags_title_' . $cpt ] ) ? esc_html__( 'Tags', 'codevz-plus' ) : $options[ 'tags_title_' . $cpt ]
			);

			register_taxonomy( $cpt . '_cat', $cpt, 
				array(
					'hierarchical'		=> true,
					'labels'			=> array(
						'name'				=> $opt['cat_title'],
						'menu_name'			=> $opt['cat_title']
					),
					'show_ui'			=> true,
					'show_admin_column'	=> true,
					'show_in_rest'		=> true,
					'query_var'			=> true,
					'rewrite'			=> array( 'slug' => $opt['cat_slug'], 'with_front' => false ),
				)
			);

			register_taxonomy( $cpt . '_tags', $cpt, 
				array(
					'hierarchical'		=> false,
					'labels'			=> array(
						'name'				=> $opt['tags_title'],
						'menu_name'			=> $opt['tags_title']
					),
					'show_ui'			=> true,
					'show_admin_column'	=> true,
					'show_in_rest'		=> true,
					'query_var'			=> true,
					'rewrite'			=> array( 'slug' => $opt['tags_slug'], 'with_front' => false ),
				)
			);

			$icon = $cpt === 'portfolio' ? 'dashicons-format-gallery' : 'dashicons-admin-post';

			$cpt_label = str_replace( '_', ' ', $opt['title'] );

			register_post_type( $cpt, 
				array(
					'labels'		=> array(
						'name'			=> $cpt_label,
						'menu_name'		=> $cpt_label
					),
					'public'			=> true,
					'menu_icon'		=> $icon,
					'supports'			=> array( 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields', 'comments', 'author', 'post-formats', 'elementor' ),
					'has_archive'		=> true,
					'show_in_rest'		=> true,
					'rewrite'			=> array( 'slug' => $opt['slug'], 'with_front' => false )
				)
			);
		}
	}

	/**
	 *
	 * Set short codes ID and generate styles then update post meta 'cz_sc_styles'
	 * 
	 * @return string
	 * 
	 */
	public static function save_post( $post_id = '' ) {

		if ( empty( $post_id ) || wp_is_post_revision( $post_id ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) ) {
			return;
		}

		// Get content
		$content = get_post_field( 'post_content', $post_id );

		// Extract Short codes
		$shortcodes = (array) self::get_string_between( $content, '[cz_', ']', 1 );
		if ( ! empty( $shortcodes ) ) {
			$styles = $tablet = $mobile = '';
			foreach ( $shortcodes as $sc ) {
				if ( ! empty( $sc ) ) {
					$do_shortcode = do_shortcode( $sc );
					$styles .= self::get_string_between( $do_shortcode, '<style class="cz_d_css" data-noptimize>', '</style>' );
					$tablet .= self::get_string_between( $do_shortcode, '<style class="cz_t_css" data-noptimize>', '</style>' );
					$mobile .= self::get_string_between( $do_shortcode, '<style class="cz_m_css" data-noptimize>', '</style>' );
				}
			}

			// Update meta box for new styles
			delete_post_meta( $post_id, 'cz_sc_styles' );
			update_post_meta( $post_id, 'cz_sc_styles', $styles );
			if ( $tablet ) {
				delete_post_meta( $post_id, 'cz_sc_styles_tablet' );
				update_post_meta( $post_id, 'cz_sc_styles_tablet', $tablet );
			}
			if ( $mobile ) {
				delete_post_meta( $post_id, 'cz_sc_styles_mobile' );
				update_post_meta( $post_id, 'cz_sc_styles_mobile', $mobile );
			}
			
		}
	}

	/**
	 *
	 * Script output of Google Maps new API solution.
	 * 
	 * @return string
	 * 
	 */
	public static function google_maps_script( $atts ) {

		$str = '';

		$map = $atts[ 'map' ];

		$str .= '<script>

		function mapfunction_' . $map . '() {

			const codevzPlusWaitForOverlay = setInterval(() => {

				if (typeof google.maps.OverlayView !== "undefined") {

					clearInterval(codevzPlusWaitForOverlay);

					google.maps.Map.prototype.setCenterWithOffset = function(latlng, offsetX, offsetY) {
					    var map = this;
					    var ov = new google.maps.OverlayView();
					    ov.onAdd = function() {
					        var proj = this.getProjection();
					        var aPoint = proj.fromLatLngToContainerPixel(latlng);
					        aPoint.x = aPoint.x+offsetX;
					        aPoint.y = aPoint.y+offsetY;
					        map.setCenter(proj.fromContainerPixelToLatLng(aPoint));
					    }; 
					    ov.draw = function() {}; 
					    ov.setMap(this); 
					};

					var windx = jQuery(window), 

						latlng = { lat:' . $atts['lat'] . ', lng:' . $atts['long'] . '},

						myOptions = {
							mapId: "' . ( ( $atts['apikey'] && ( $atts['color'] == 'standard' ) ) ? $atts['apikey'] : '' ) . '",
							zoom: ' . ( $atts['zoom'] ? $atts['zoom'] : 14 ) . ',
							scrollwheel: ' . ( ( $atts['scrollwheel'] === 'yes' ) ? 'true' : 'false' ) . ',
							disableDefaultUI: ' . ( ( $atts['hidecontrols'] === 'true' ) ? 'true' : 'false' ) . ',
							center: latlng,
							fullscreenControl: false,
							mapTypeControl: false,
							keyboardShortcuts: false
						},

						' . $map . ' = new google.maps.Map(document.getElementById("' . $map . '"), myOptions ),

						setCenterOffset = function() {

							if ( windx.width() < 768 ) {
								' . $map . '.setCenterWithOffset( latlng, 0, 0 );
							} else {
								' . $map . '.setCenterWithOffset( latlng, ' . $atts['offsetx'] . ', ' . $atts['offsety'] . ' );
							}

						};

						setCenterOffset();

						windx.on( "resize", function() {
							setCenterOffset();
						});
					';

					if ($atts['color']!='standard'){
						switch ($atts['color']) {
							case 'light':
								$str .= 'var styles = [{"elementType": "geometry","stylers": [{"color": "#f5f5f5"}]},{"elementType": "labels.icon","stylers": [{"visibility": "off"}]},{"elementType": "labels.text.fill","stylers": [{"color": "#616161"}]},{"elementType": "labels.text.stroke","stylers": [{"color": "#f5f5f5"}]},{"featureType": "administrative.land_parcel","elementType": "labels","stylers": [{"visibility": "off"}]},{"featureType": "administrative.land_parcel","elementType": "labels.text.fill","stylers": [{"color": "#bdbdbd"}]},{"featureType": "poi","elementType": "geometry","stylers": [{"color": "#eeeeee"}]},{"featureType": "poi","elementType": "labels.text","stylers": [{"visibility": "off"}]},{"featureType": "poi","elementType": "labels.text.fill","stylers": [{"color": "#757575"}]},{"featureType": "poi.park","elementType": "geometry","stylers": [{"color": "#e5e5e5"}]},{"featureType": "poi.park","elementType": "labels.text.fill","stylers": [{"color": "#9e9e9e"}]},{"featureType": "road","elementType": "geometry","stylers": [{"color": "#ffffff"}]},{"featureType": "road.arterial","elementType": "labels.text.fill","stylers": [{"color": "#757575"}]},{"featureType": "road.highway","elementType": "geometry","stylers": [{"color": "#dadada"}]},{"featureType": "road.highway","elementType": "labels.text.fill","stylers": [{"color": "#616161"}]},{"featureType": "road.local","elementType": "labels","stylers": [{"visibility": "off"}]},{"featureType": "road.local","elementType": "labels.text.fill","stylers": [{"color": "#9e9e9e"}]},{"featureType": "transit.line","elementType": "geometry","stylers": [{"color": "#e5e5e5"}]},{"featureType": "transit.station","elementType": "geometry","stylers": [{"color": "#eeeeee"}]},{"featureType": "water","elementType": "geometry","stylers": [{"color": "#c9c9c9"}]},{"featureType": "water","elementType": "labels.text.fill","stylers": [{"color": "#9e9e9e"}]}];';
								break;
							case 'dark':
								$str .= 'var styles =[{"elementType": "geometry","stylers": [{"color": "#212121"}]},{"elementType": "labels.icon","stylers": [{"visibility": "off"}]},{"elementType": "labels.text.fill","stylers": [{"color": "#757575"}]},{"elementType": "labels.text.stroke","stylers": [{"color": "#212121"}]},{"featureType": "administrative","elementType": "geometry","stylers": [{"color": "#757575"}]},{"featureType": "administrative.country","elementType": "labels.text.fill","stylers": [{"color": "#9e9e9e"}]},{"featureType": "administrative.land_parcel","stylers": [{"visibility": "off"}]},{"featureType": "administrative.land_parcel","elementType": "labels","stylers": [{"visibility": "off"}]},{"featureType": "administrative.locality","elementType": "labels.text.fill","stylers": [{"color": "#bdbdbd"}]},{"featureType": "poi","elementType": "labels.text","stylers": [{"visibility": "off"}]},{"featureType": "poi","elementType": "labels.text.fill","stylers": [{"color": "#757575"}]},{"featureType": "poi.park","elementType": "geometry","stylers": [{"color": "#181818"}]},{"featureType": "poi.park","elementType": "labels.text.fill","stylers": [{"color": "#616161"}]},{"featureType": "poi.park","elementType": "labels.text.stroke","stylers": [{"color": "#1b1b1b"}]},{"featureType": "road","elementType": "geometry.fill","stylers": [{"color": "#2c2c2c"}]},{"featureType": "road","elementType": "labels.text.fill","stylers": [{"color": "#8a8a8a"}]},{"featureType": "road.arterial","elementType": "geometry","stylers": [{"color": "#373737"}]},{"featureType": "road.highway","elementType": "geometry","stylers": [{"color": "#3c3c3c"}]},{"featureType": "road.highway.controlled_access","elementType": "geometry","stylers": [{"color": "#4e4e4e"}]},{"featureType": "road.local","elementType": "labels","stylers": [{"visibility": "off"}]},{"featureType": "road.local","elementType": "labels.text.fill","stylers": [{"color": "#616161"}]},{"featureType": "transit","elementType": "labels.text.fill","stylers": [{"color": "#757575"}]},{"featureType": "water","elementType": "geometry","stylers": [{"color": "#000000"}]},{"featureType": "water","elementType": "labels.text.fill","stylers": [{"color": "#3d3d3d"}]}];';
								break;
							case 'retro':
								$str .= 'var styles =[{"elementType": "geometry","stylers": [{"color": "#ebe3cd"}]},{"elementType": "labels.text.fill","stylers": [{"color": "#523735"}]},{"elementType": "labels.text.stroke","stylers": [{"color": "#f5f1e6"}]},{"featureType": "administrative","elementType": "geometry.stroke","stylers": [{"color": "#c9b2a6"}]},{"featureType": "administrative.land_parcel","elementType": "geometry.stroke","stylers": [{"color": "#dcd2be"}]},{"featureType": "administrative.land_parcel","elementType": "labels","stylers": [{"visibility": "off"}]},{"featureType": "administrative.land_parcel","elementType": "labels.text.fill","stylers": [{"color": "#ae9e90"}]},{"featureType": "landscape.natural","elementType": "geometry","stylers": [{"color": "#dfd2ae"}]},{"featureType": "poi","elementType": "geometry","stylers": [{"color": "#dfd2ae"}]},{"featureType": "poi","elementType": "labels.text","stylers": [{"visibility": "off"}]},{"featureType": "poi","elementType": "labels.text.fill","stylers": [{"color": "#93817c"}]},{"featureType": "poi.park","elementType": "geometry.fill","stylers": [{"color": "#a5b076"}]},{"featureType": "poi.park","elementType": "labels.text.fill","stylers": [{"color": "#447530"}]},{"featureType": "road","elementType": "geometry","stylers": [{"color": "#f5f1e6"}]},{"featureType": "road.arterial","elementType": "geometry","stylers": [{"color": "#fdfcf8"}]},{"featureType": "road.highway","elementType": "geometry","stylers": [{"color": "#f8c967"}]},{"featureType": "road.highway","elementType": "geometry.stroke","stylers": [{"color": "#e9bc62"}]},{"featureType": "road.highway.controlled_access","elementType": "geometry","stylers": [{"color": "#e98d58"}]},{"featureType": "road.highway.controlled_access","elementType": "geometry.stroke","stylers": [{"color": "#db8555"}]},{"featureType": "road.local","elementType": "labels","stylers": [{"visibility": "off"}]},{"featureType": "road.local","elementType": "labels.text.fill","stylers": [{"color": "#806b63"}]},{"featureType": "transit.line","elementType": "geometry","stylers": [{"color": "#dfd2ae"}]},{"featureType": "transit.line","elementType": "labels.text.fill","stylers": [{"color": "#8f7d77"}]},{"featureType": "transit.line","elementType": "labels.text.stroke","stylers": [{"color": "#ebe3cd"}]},{"featureType": "transit.station","elementType": "geometry","stylers": [{"color": "#dfd2ae"}]},{"featureType": "water","elementType": "geometry.fill","stylers": [{"color": "#b9d3c2"}]},{"featureType": "water","elementType": "labels.text.fill","stylers": [{"color": "#92998d"}]}];';
								break;
							case 'custom':
								if ($atts['custom_color']!=''){
									$str .= 'var styles = [{"featureType": "all",stylers: [{ hue: "'.$atts['custom_color'].'" },{ saturation: -20}]}];';		
								}
								break;
						}

						$str .= 'var styledMap = new google.maps.StyledMapType(styles,{name: "Styled Map"});';
						$str .=  $map . '.mapTypes.set("map_style", styledMap);  ';
						$str .=  $map . '.setMapTypeId("map_style");';
					}

					$img = isset( $atts[ 'markerimage' ] ) ? $atts[ 'markerimage' ] : '';

					if ( $atts['marker'] !='off' ) {

						$tooltip = empty( $atts['infowindow'] ) ? '<div class="tooltip"></div>' : '<div style="position:absolute;top: -10px;left: 50%;transform: translate(-50%, -100%);background: white;color: #202020;padding: 15px 20px;border-radius: 2px;font-size: 15px;white-space: nowrap;box-shadow: 0px 2px 10px rgba(0, 0, 0, 0.2);display: ' . ( ($atts['infowindowdefault'] == 'yes') ? 'block' : 'none' ) . ';font-family: Arial, sans-serif;" class="tooltip">
						                                	' . htmlspecialchars_decode( $atts['infowindow'] ) . '
						                                </div>';

						$str .= 'class CustomMarker extends google.maps.OverlayView {
						    constructor(position, map) {
						        super();
						        this.position = position;
						        this.map = map;
						        this.div = null;
						        this.setMap(map);
						    }

						    onAdd() {
						        this.div = document.createElement("div");
						        this.div.style.position = "absolute";
						        this.div.style.cursor = "pointer";
						        this.div.style.transform = "translate(-50%, -100%)";
						        this.div.innerHTML = `<div style="position:relative; text-align:center;">
						                                ' . $tooltip . '
						                                <img loading="lazy" src="' . esc_url( $img ) . '" style="display:block;" />
						                              </div>`;

						        this.getPanes().overlayMouseTarget.appendChild(this.div);
						        this.tooltip = this.div.querySelector(".tooltip");

						        // Show title on click
						        this.div.addEventListener("click", (event) => {
						            event.stopPropagation();
						            this.tooltip.style.display = this.tooltip.style.display === "block" ? "none" : "block";
						        });

						        // Hide title when clicking outside
						        google.maps.event.addListener(this.map, "click", () => {
						            this.tooltip.style.display = "none";
						        });
						    }

						    draw() {
						        const overlayProjection = this.getProjection();
						        const position = overlayProjection.fromLatLngToDivPixel(this.position);

						        if (position && this.div) {
						            this.div.style.left = `${position.x}px`;
						            this.div.style.top = `${position.y}px`;
						        }
						    }

						    onRemove() {
						        if (this.div) {
						            this.div.parentNode.removeChild(this.div);
						            this.div = null;
						        }
						    }
						}

						// Add a custom marker at a specific location
						new CustomMarker(new google.maps.LatLng(' . $atts['lat'] . ', ' . $atts['long'] . '), ' . $map . ');';
					}

			$str .= '}}, 250 );}</script>';

		return $str;

	}

	/**
	 * Plugin white label.
	 * 
	 * @since 3.2.0
	 */
	public static function white_label( $plugins ) {

		if ( isset( $plugins['codevz-plus/codevz-plus.php']['Name'] ) ) {

			$name 			= self::option( 'white_label_plugin_name' );
			$description 	= self::option( 'white_label_plugin_description' );
			$author 		= self::option( 'white_label_author' );
			$link 			= self::option( 'white_label_link' );

			if ( $name ) {
				$plugins['codevz-plus/codevz-plus.php']['Name'] = $name;
				$plugins['codevz-plus/codevz-plus.php']['Title'] = $name;
			}
			
			if ( $description ) {
				$plugins['codevz-plus/codevz-plus.php']['Description'] = $description;
			}
			
			if ( $author ) {
				$plugins['codevz-plus/codevz-plus.php']['Author'] = $author;
			}
			
			if ( $link ) {
				$plugins['codevz-plus/codevz-plus.php']['PluginURI'] = $link;
				$plugins['codevz-plus/codevz-plus.php']['AuthorURI'] = $link;
			}

		}

		return $plugins;
	}

	// Handle AJAX request.
	public static function subscribe_ajax() {

		// Security check.
		if ( ! empty( $_GET[ 'website' ] ) || empty( $_GET[ 'name' ] ) || empty( $_GET[ 'email' ] ) || empty( $_GET[ 'nonce' ] ) || ! wp_verify_nonce( $_GET['nonce'], 'cz_subscribe_nonce' ) ) {

			wp_send_json(
				[
					'type' 		=> '202',
					'message' 	=> esc_html__( 'Error, Please try again ...', 'codevz-plus' )
				]
			);

		}

		// Data.
		$name  = sanitize_text_field( $_GET['name'] );
		$email = sanitize_email( $_GET['email'] );

		// Check exists email.
		if ( email_exists( $email ) ) {

			wp_send_json(
				[
					'type' 		=> '202',
					'message' 	=> esc_html__( 'Already subscribed', 'codevz-plus' )
				]
			);

		}

		// Check last request before creating new.
		if ( get_transient( 'cz_sub_' . md5( $email ) ) ) {

			wp_send_json(
				[
					'type' 		=> '200',
					'message' 	=> $name . ', ' . esc_html__( 'You’ve already requested confirmation. Please check your email', 'codevz-plus' )
				]
			);

		}

		// Generate unique key.
		$key = md5( uniqid( $email, true ) );

		// Keep confirmation link for 1 day.
		set_transient( 'cz_sub_' . md5( $email ), compact( 'name','email','key' ), DAY_IN_SECONDS );

		$link = add_query_arg(
			[
				'cz_subscribe_confirm' 	=> 1,
				'email' 				=> urlencode( $email ),
				'key' 					=> $key
			],
			site_url()
		);

		// Email template.
		$message  = '<html><body style="font-family:Arial,sans-serif;background:#f9f9f9;padding:30px">';
		$message .= '<div style="max-width:700px;margin:0 auto;background:#fff;padding:40px;border-radius:6px;text-align:left">';
		$message .= '<h2 style="color:#0073aa;margin-top:0">' . esc_html__( 'Confirm your subscription', 'codevz-plus' ) . '</h2>';
		$message .= '<p style="margin:20px 0">' . esc_html__( 'Hi', 'codevz-plus' ) . ' ' . esc_html($name) . ',</p>';
		$message .= '<p style="margin:20px 0">' . esc_html__( 'Thanks for subscribing! Please click the button below to confirm your subscription.', 'codevz-plus' ) . '</p>';
		$message .= '<p style="margin:30px 0"><a href="' . esc_url($link) . '" style="display:inline-block;padding:12px 30px;background:#0073aa;color:#fff;text-decoration:none;border-radius:4px;font-weight:bold">' . esc_html__( 'Confirm Subscription', 'codevz-plus' ) . '</a></p>';
		$message .= '<p style="margin:20px 0;font-size:14px;color:#666">' . esc_html__( 'If you didn\'t request this, you can safely ignore this email.', 'codevz-plus' ) . '</p>';
		$message .= '<p style="margin-top:40px;font-size:13px;color:#aaa">&copy; ' . date('Y') . ' ' . esc_html( get_bloginfo('name') ) . '</p>';
		$message .= '</div></body></html>';

		// Send email.
		self::sendMail( $email, esc_html__( 'Confirm your subscription', 'codevz-plus' ), $message, [ 'Content-Type: text/html; charset=UTF-8' ] );

		wp_send_json(
			[
				'type' 		=> '200',
				'message' 	=> esc_html__( 'Thanks', 'codevz-plus' ) . ' ' . $name . ', ' . esc_html__( 'Email sent, Please check your email to confirm', 'codevz-plus' )
			]
		);

	}

	// Handle Confirmation.
	public static function subscribe_confirm() {

		if ( ! empty( $_GET[ 'cz_subscribe_confirm' ] ) ) {

			if ( empty( $_GET[ 'email' ] ) || empty( $_GET[ 'key' ] ) ) {
				wp_die( '❌ ' . esc_html__( 'Invalid or expired link', 'codevz-plus' ) );
			}

			$email = sanitize_email( $_GET['email'] );
			$key   = sanitize_text_field( $_GET['key'] );
			$data  = get_transient( 'cz_sub_' . md5( $email ) );

			if ( ! $data || $data['key'] !== $key ) {
				wp_die( '❌ ' . esc_html__( 'Invalid or expired link', 'codevz-plus' ) );
			}

			if ( email_exists( $email ) ) {
				wp_die( '✅ ' . esc_html__( 'Already subscribed', 'codevz-plus' ) );
			}

			$user_id = wp_create_user( sanitize_user(strtolower(str_replace(' ', '_', $data['name']))), wp_generate_password(), $email );

			if ( is_wp_error( $user_id ) ) {
				wp_die( '❌ ' . esc_html__( 'Failed to create user', 'codevz-plus' ) );
			}

			wp_update_user(
				[
					'ID' 			=> $user_id,
					'role' 			=> 'subscriber',
					'display_name' 	=> $data['name']
				]
			);

			delete_transient( 'cz_sub_' . md5( $email ) );

			wp_die( '✅ ' . esc_html__( 'Subscription confirmed', 'codevz-plus' ) );

		}

	}

} // Codevz_Plus

// Run
Codevz_Plus::instance();


/**
 * VC initial action
 * @return object
 */
function codevz_vc_before_init() {

	global $pagenow;

	if ( is_admin() && $pagenow !== 'post.php' && $pagenow !== 'post-new.php' && $pagenow !== 'admin-ajax.php' ) {

		return;

	}

	// Codevz Elements
	foreach( glob( Codevz_Plus::$dir . 'wpbakery/*.php' ) as $i ) {

		require_once( $i );

		$name = str_replace( '.php', '', basename( $i ) );
		$class = 'Codevz_WPBakery_' . $name;
		$new_class = new $class( 'cz_' . $name );
		$new_class->in( true );

		// Editor.
		if ( Codevz_Plus::_GET( 'vc_editable' ) ) {

			wp_enqueue_style( 'cz_' . $name );
			wp_enqueue_script( 'cz_' . $name );

			wp_enqueue_style( 'cz_parallax' );
			wp_enqueue_script( 'cz_parallax' );

			wp_enqueue_style( 'codevz-tilt' );
			wp_enqueue_script( 'codevz-tilt' );

			if ( Codevz_Plus::$is_rtl ) {
				wp_enqueue_style( 'cz_' . $name . '_rtl' );
				wp_enqueue_script( 'cz_' . $name . '_rtl' );
			}

		}

	}

	// Elements container
	if ( class_exists( 'WPBakeryShortCodesContainer' ) ) {
		class WPBakeryShortCode_cz_acc_child extends WPBakeryShortCodesContainer {}
		class WPBakeryShortCode_cz_accordion extends WPBakeryShortCodesContainer {}
		class WPBakeryShortCode_cz_carousel extends WPBakeryShortCodesContainer {}
		class WPBakeryShortCode_cz_content_box extends WPBakeryShortCodesContainer {}  
		class WPBakeryShortCode_cz_free_position_element extends WPBakeryShortCodesContainer {}
		class WPBakeryShortCode_cz_history_line extends WPBakeryShortCodesContainer {}
		class WPBakeryShortCode_cz_parallax extends WPBakeryShortCodesContainer {}
		class WPBakeryShortCode_cz_popup extends WPBakeryShortCodesContainer {}
		class WPBakeryShortCode_cz_process_line_vertical extends WPBakeryShortCodesContainer {}
		class WPBakeryShortCode_cz_show_more_less extends WPBakeryShortCodesContainer {}
		class WPBakeryShortCode_cz_speech_bubble extends WPBakeryShortCodesContainer {}
		class WPBakeryShortCode_cz_tab extends WPBakeryShortCodesContainer {}
		class WPBakeryShortCode_cz_tabs extends WPBakeryShortCodesContainer {}
		class WPBakeryShortCode_cz_timeline extends WPBakeryShortCodesContainer {}
		class WPBakeryShortCode_cz_timeline_item extends WPBakeryShortCodesContainer {}
		class WPBakeryShortCode_cz_particles extends WPBakeryShortCodesContainer {}
	}

	// Activate VC for post types
	$vc_cpts = (array) get_option( 'codevz_post_types' );
	$vc_cpts[] = 'page';
	$vc_cpts[] = 'post';
	$vc_cpts[] = 'portfolio';
	$vc_cpts[] = 'product';
	vc_set_default_editor_post_types( $vc_cpts );

}
add_action( 'vc_before_init', 'codevz_vc_before_init' );
