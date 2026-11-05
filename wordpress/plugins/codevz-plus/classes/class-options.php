<?php if ( ! defined( 'ABSPATH' ) ) {exit;} // Exit if accessed directly.

/**
 * Options and page settings
 */

class Codevz_Options {

	use Codevz_Plus_Instance;

	// Cache sliders.
	private static $revslider;

	// Translation strings.
	private static $trasnlation;

	// Get post type in admin.
	private static $admin_post_type;

	public function __construct() {

		// Enable fonts upload only on customizer.
		add_filter( 'wp_check_filetype_and_ext', [ $this, 'force_font_file_acceptance' ], 10, 4 );
		add_filter( 'upload_mimes', [ $this, 'customizer_allow_font_uploads' ] );

		// Options & Metabox
		add_action( 'init', [ $this, 'init' ], 999 );

		// Save customize settings
		add_action( 'customize_save_after', [ $this, 'customize_save_after' ], 10, 2 );

		// Enqueue inline styles
		if ( ! Codevz_Plus::_POST( 'vc_inline' ) ) {
			add_action( 'wp_enqueue_scripts', [ $this, 'wp_enqueue_scripts' ], 999 );
		}

		// Update single page CSS
		add_action( 'save_post', [ $this, 'save_post' ], 9999 );

		// Updated option.
		add_action( 'updated_option', [ $this, 'updated_option' ], 10, 3);

		// Save classic theme options values + trigger customizer save after functions.
		add_filter( 'pre_update_option_codevz_theme_options', function( $new_value, $old_value, $option_name ) {

		    // Static flag to ensure the code runs only once during the current request
		    static $manual_save_in_progress = false;

		    // Check if data is coming from the admin page (options.php)
		    if ( ! $manual_save_in_progress && isset( $_POST['codevz_theme_options'] ) && strpos( $_SERVER['REQUEST_URI'], 'options.php' ) !== false ) {

		        // Set the flag to prevent further updates within the same request
		        $manual_save_in_progress = true;

		        // Save the data manually
		        update_option( 'codevz_theme_options', $_POST[ 'codevz_theme_options' ] );

		        // Trigger the custom save function after saving the options
		        self::customize_save_after( true );

		        // Reset the flag and return the old value to prevent further updates
		        $manual_save_in_progress = false;

		        // Return the old value to prevent the default update from being triggered again
		        return $old_value;
		    }

		    // Allow the default behavior if it's not coming from options.php
		    return $new_value;

		}, 10, 3 );

	}

	/**
	 * Initial theme options and clear cache.
	 */
	public function init() {

		// Version init to clear CSS cache.
		$init_key = 'codevz_plus_clear_cache_v' . str_replace( '.', '_', Codevz_Plus::$ver );

		// Clear caches.
		if ( ! get_option( $init_key ) ) {

			update_option( $init_key, true );

			// Clear theme CSS cache.
			update_option( 'codevz_generate_css_out', true );

			// Clear Elementor cache.
			if ( did_action( 'elementor/loaded' ) ) {

				$elementor = \Elementor\Plugin::$instance;
				$elementor->files_manager->clear_cache( true );

			}

		}

		// Check.
		$preview = is_customize_preview();

		// Load options.
		if ( class_exists( 'Codevz_Framework' ) && ( is_admin() || $preview ) ) {

			global $pagenow;

			self::$trasnlation = [
				'left' 		=> esc_html__( 'Left', 'codevz-plus' ),
				'center' 	=> esc_html__( 'Center', 'codevz-plus' ),
				'right' 	=> esc_html__( 'Right', 'codevz-plus' ),
				'top' 		=> esc_html__( 'Top', 'codevz-plus' ),
				'middle' 	=> esc_html__( 'Middle', 'codevz-plus' ),
				'bottom' 	=> esc_html__( 'Bottom', 'codevz-plus' ),
				'Portfolio' => esc_html__( 'Portfolio', 'codevz-plus' ),
			];

			// Live theme options.
			if ( $pagenow === 'customize.php' || $pagenow === 'admin-ajax.php' || $pagenow === 'index.php' ) {

				Codevz_Framework_Customize::instance( self::options(), 'codevz_theme_options' );

			// Posts/pages meta box settings.
			} else if ( $pagenow === 'post.php' || $pagenow === 'post-new.php' || $pagenow === 'admin-ajax.php' ) {

				Codevz_Framework_Metabox::instance( self::metabox() );

			// Taxonomy settings.
			} else if ( $pagenow === 'edit-tags.php' || $pagenow === 'term.php' ) {

				$free = Codevz_Plus::$is_free;

				$tax_meta = [];

				foreach( [ 'post', 'portfolio', 'product' ] as $cpt ) {
					$tax_meta[] = [
						'id'       	=> 'codevz_cat_meta',
						'taxonomy' 	=> ( $cpt === 'post' ) ? 'category' : $cpt . '_cat',
						'fields' 	=> [
						  array(
							'id'        => '_css_page_title',
							'type'      => $free ? 'content' : 'cz_sk',
							'content' 	=> Codevz_Plus::pro_badge(),
							'title'     => esc_html__( 'Title background', 'codevz-plus' ),
							'button'    => esc_html__( 'Title background', 'codevz-plus' ),
							'settings'  => [ 'background' ]
						  ),
						]
					];
				}

				$tax_meta[] = [
					'id'       	=> 'codevz_brands',
					'taxonomy' 	=> 'codevz_brands',
					'fields' 	=> [
					  array(
						'id'        => 'brand_logo',
						'type'      => $free ? 'content' : 'image',
						'content' 	=> Codevz_Plus::pro_badge(),
						'title'     => esc_html__( 'Brand', 'codevz-plus' ),
						'button'    => esc_html__( 'Brand', 'codevz-plus' ),
						'settings'  => []
					  ),
					]
				];

				Codevz_Framework_Taxonomy::instance( $tax_meta );

			// Load options only in admin.
			} else if ( ! $preview ) {

				if ( $pagenow === 'admin.php' || $pagenow === 'options.php' ) {

					// Theem info.
					$theme = wp_get_theme();

					// Classic theme options.
					Codevz_Framework_Options::instance( [

						'option_name' 		=> 'codevz_theme_options',
				        'menu_parent'     	=> 'theme-activation',
						'framework_title' 	=> esc_html__( 'Theme Options', 'codevz-plus' ) . '<small><span>' . esc_html( $theme->name ) . '</span>' . esc_html__( 'Current version:', 'codevz-plus' ) . ' <strong>' . esc_html( $theme->version ) . '</strong></small>',
				        'menu_title'      	=> esc_html__( 'Classic Theme Options', 'codevz-plus' ),
				        'menu_type'       	=> 'submenu',
				        'menu_slug'       	=> 'codevz-theme-options',
				        'menu_icon'       	=> '',
				        'menu_capability' 	=> 'manage_options',
				        'menu_position'   	=> null,
						'sticky_header' 	=> true,
						'ajax_save' 		=> false,
						'save_defaults' 	=> false,
						'show_search' 		=> true,
						'show_reset' 		=> false,
						'show_all_options' 	=> false,
						'show_footer' 		=> true

					], self::options() );

				}

			} else {

				add_submenu_page(
					'theme-activation',
					esc_html__( 'Classic Theme Options', 'codevz-plus' ),
					esc_html__( 'Classic Theme Options', 'codevz-plus' ),
					'manage_options',
					'admin.php?page=codevz-theme-options'
				);

			}

		}

	}

	/**
	 * Change value on update_option trigger.
	 * 
	 * @return null
	 */
	public function updated_option( $option_name, $old_value, $value ) {

		global $pagenow;

		// Update posts per page for theme options from WP settings.
		if ( $option_name === 'posts_per_page' && $old_value != $value ) {

			$options = get_option( 'codevz_theme_options' );

			$options[ 'posts_per_page' ] = $value;

			update_option( 'codevz_theme_options', $options );

		}

	}

	/**
	 * Enable fonts upload.
	 * 
	 * @return array
	 */
	public function force_font_file_acceptance( $data, $file, $filename, $mimes ) {

		global $pagenow;

		if ( $pagenow === 'customize.php' || $pagenow === 'async-upload.php' ) {

		    $ext = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );

		    $font_mimes = [
		        'woff'  => 'font/woff',
		        'woff2' => 'font/woff2',
		        'ttf'   => 'font/ttf',
		        'otf'   => 'font/otf',
		        'svg'   => 'image/svg+xml',
		    ];

		    if ( isset( $font_mimes[$ext] ) ) {
		        return [
		            'ext'             => $ext,
		            'type'            => $font_mimes[$ext],
		            'proper_filename' => $filename,
		        ];
		    }

		}

	    return $data;
	}

	/**
	 * Enable fonts upload.
	 * 
	 * @return array
	 */
	public function customizer_allow_font_uploads( $mimes ) {

		global $pagenow;

		if ( $pagenow === 'customize.php' || $pagenow === 'async-upload.php' ) {

			$mimes['woff']  = 'font/woff';
			$mimes['woff2'] = 'font/woff2';
			$mimes['ttf']   = 'font/ttf';
			$mimes['otf']   = 'font/otf';
			$mimes['svg']   = 'image/svg+xml';

		}

		return $mimes;

	}

	/**
	 * Add inline styles to front-end
	 * 
	 * @return string
	 */
	public function wp_enqueue_scripts() {

		$is_singular = is_singular();

		$page_id = empty( Codevz_Plus::$post->ID ) ? 0 : Codevz_Plus::$post->ID;

		if ( $page_id ) {

			// Blog.
			if ( is_home() ) {

				$is_singular = true;

				$page_id = get_option( 'page_for_posts' );

			}

			// Single page CSS.
			if ( $is_singular ) {

				$meta = get_post_meta( $page_id, 'codevz_single_page_css', 1 );

				if ( ! Codevz_Plus::contains( $meta, '.cz-page-' . $page_id ) ) {
					self::save_post( $page_id );
					$meta = get_post_meta( $page_id, 'codevz_single_page_css', 1 );
				}

				wp_add_inline_style( 'codevz-plus', str_replace( 'Array', '', $meta ) );

			}

		}

		// Options json for customize preview
		if ( is_customize_preview() ) {

			wp_add_inline_style( 'codevz-plus', self::css_out( 1 ) );

			self::codevz_wp_footer_options_json();

		}

	}

	/**
	 * Get list of post types created via customizer
	 * 
	 * @return array
	 */
	public static function post_types( $a = array() ) {

		// Theme options CPT generator merge.
		$a = array_merge( $a, (array) get_option( 'codevz_post_types' ) );

		// Theme.
		$a[] = 'portfolio';

		// Custom post type UI
		if ( function_exists( 'cptui_get_post_type_slugs' ) ) {
			$cptui = cptui_get_post_type_slugs();
			if ( is_array( $cptui ) ) {
				$a = wp_parse_args( $cptui, $a );
			}
		}

		return apply_filters( 'codevz_post_types', $a );

	}

	public static function share_post_types() {

		$out = [];

		foreach ( self::post_types( array( 'post', 'page', 'product', 'download' ) ) as $cpt ) {

			if ( $cpt ) {

				$out[ $cpt ] = ucwords( $cpt );

			}
			
		}

		return $out;
	}

	/**
	 * Update single page CSS as metabox 'codevz_single_page_css'
	 * 
	 * @return string
	 */
	public function save_post( $post_id = '' ) {
		if ( empty( $post_id ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) ) {
			return;
		}

		delete_post_meta( $post_id, 'codevz_single_page_css' );
		$meta = self::css_out( 0, (array) get_post_meta( $post_id, 'codevz_page_meta', true ), $post_id );
		if ( $meta ) {
			update_post_meta( $post_id, 'codevz_single_page_css', $meta );
		}
	}

	/**
	 * Get post type in admin area
	 * 
	 * @return string
	 */
	public static function get_post_type_admin() {

		global $pagenow;

		if ( $pagenow === 'post.php' || $pagenow === 'post-new.php' ) {

			if ( ! self::$admin_post_type ) {

				$post_id 	= Codevz_Plus::_GET( 'post' );
				$post_type 	= $post_id ? get_post_type( $post_id ) : Codevz_Plus::_GET( 'post_type' );

				self::$admin_post_type = $post_type ? $post_type : 'post';

			}

			return self::$admin_post_type;

		}

	}

	/**
	 *
	 * Generate styles when customizer saves
	 * 
	 * @return array
	 *
	 */
	public static function css_out( $is_customize_preview = 0, $single_page = 0, $post_id = '' ) {
		$out = $dynamic = $dynamic_tablet = $dynamic_mobile = '';
		$fonts = [];

		// Options
		$opt = $single_page ? (array) $single_page : (array) get_option( 'codevz_theme_options' );

		// Generating styles
		foreach ( $opt as $id => $val ) {
			if ( $val && Codevz_Plus::contains( $id, '_css_' ) ) {
				if ( is_array( $val ) || Codevz_Plus::contains( $val, '[' ) ) {
					continue;
				}

				// Temp fix for live customizer fonts generation
				if ( $is_customize_preview ) {
					if ( Codevz_Plus::contains( $val, 'font-family' ) ) {
						$fonts[]['font'] = $val;
					}
					continue;
				}

				// CSS Selector
				$selector = Codevz_Plus::contains( $id, '_css_page_body_bg' ) ? 'html,body' : self::get_selector( $id );
				if ( $single_page ) {
					$page_id = '.cz-page-' . $post_id;
					$selector = ( $selector === 'html,body' ) ? 'body' . $page_id : $page_id . ' ' . $selector;
					if ( Codevz_Plus::contains( $selector, ',' ) ) {
						$selector = str_replace( ',', ',' . $page_id . ' ', $selector );
					}
				}

				// Fix custom css
				$val = str_replace( 'CDVZ', '', $val );

				// RTL
				if ( Codevz_Plus::contains( $val, 'RTL' ) ) {
					$rtl = Codevz_Plus::get_string_between( $val, 'RTL', 'RTL' );
					$val = str_replace( array( $rtl, 'RTL' ), '', $val );
				}

				// Set font family
				if ( Codevz_Plus::contains( $val, 'font-family' ) ) {

					$fonts[]['font'] = $val;

					// Extract font + params && Fix font for CSS
					$font = $o_font = Codevz_Plus::get_string_between( $val, 'font-family:', ';' );
					$font = str_replace( '=', ':', $font );
					$font = str_replace( "''", "", $font );
					$font = str_replace( "'", "", $font );
					$font = str_replace( "\\", "", $font );

					if ( Codevz_Plus::contains( $font, ':' ) ) {

						$font = explode( ':', $font );

						if ( ! empty( $font[0] ) ) {

							if ( ! Codevz_Plus::contains( $font[0], "'" ) ) {
								$font[0] = "'" . $font[0] . "'";
							}

							$val = str_replace( $o_font, $font[0], $val );

							if ( $id === '_css_body_typo' ) {
								$dynamic .= '[class*="cz_tooltip_"] [data-title]:after{font-family:' . $font[0] . '}';
							}

						}

					} else {

						if ( ! Codevz_Plus::contains( $font, "'" ) ) {
							$font = "'" . $font . "'";
						}

						$val = str_replace( $o_font, $font, $val );

						if ( $id === '_css_body_typo' ) {
							$dynamic .= '[class*="cz_tooltip_"] [data-title]:after{font-family:' . $font . '}';
						}

					}

				}

				// Remove unwanted in css
				if ( Codevz_Plus::contains( $val, '_class_' ) ) {
					$val = preg_replace( '/_class_[\s\S]+?;/', '', $val );
				}

				// Fix sticky styles priority and :focus
				if ( $id === '_css_container_header_5' || $id === '_css_row_header_5' || $id === '_css_container_mob_header_5' || $id === '_css_row_mob_header_5' || Codevz_Plus::contains( $selector, 'input:focus' ) ) {
					$val = str_replace( '!important', '', $val );
					$val = str_replace( ';', ' !important;', $val );
				}

				// Append to out
				if ( ! empty( $val ) && ! empty( $selector ) ) {
					if ( Codevz_Plus::contains( $id, '_tablet' ) ) {
						$dynamic_tablet .= $selector . '{' . $val . '}';
					} else if ( Codevz_Plus::contains( $id, '_mobile' ) ) {
						$dynamic_mobile .= $selector . '{' . $val . '}';
					} else {
						$dynamic .= $selector . '{' . $val . '}';
					}
				}

				// RTL.
				if ( ! empty( $rtl ) && $selector ) {

					$classes = [ '.cz-cpt-', '.cz-page-', '.home', 'body', '.woocommerce' ];

					//$selector = array_reduce( $classes, fn( $carry, $class ) => $carry || strpos( $selector, $class ) === 0, false) ? '.rtl' . $selector : '.rtl ' . $selector;
					$selector = array_reduce( $classes, function( $carry, $class ) use ( $selector ) { return $carry || strpos( $selector, $class ) === 0; }, false ) ? '.rtl' . $selector : '.rtl ' . $selector;

					$selector = str_replace( ', ', ',', $selector );
					$selector = str_replace( ',', ',.rtl ', $selector );

					foreach( $classes as $class ) {
						$selector = str_replace( ',.rtl ' . $class, ',.rtl' . $class, $selector );
					}

					$dynamic .= $selector . '{' . $rtl . '}';

				}

				$rtl = 0;

			}

		}

		// Single title color
		$post_id = get_the_id();
		$page_title_color = Codevz_Plus::meta( $post_id, 'page_title_color' );
		if ( $single_page && $page_title_color ) {
			$dynamic .= '.cz-page-' . $post_id . ' .page_title * {color: ' . $page_title_color . ' !important}';
		}

		// Final out
		if ( ! $is_customize_preview ) {
			$dynamic = $dynamic ? "\n\n/* Dynamic " . ( $single_page ? 'Single' : '' ) . " */" . $dynamic : '';
			if ( $single_page ) {
				$dynamic .= $dynamic_tablet ? '@media screen and (max-width:' . Codevz_Plus::option( 'tablet_breakpoint', '768px' ) . '){' . $dynamic_tablet . '}' : '';
				$dynamic .= $dynamic_mobile ? '@media screen and (max-width:' . Codevz_Plus::option( 'mobile_breakpoint', '480px' ) . '){' . $dynamic_mobile . '}' : '';
			}
		}

		$dynamic = str_replace( ';}', '}', $dynamic );

		// Single pages
		if ( $single_page ) {
			return $dynamic;
		}

		// Site Width & Boxed
		$site_width = empty( $opt['site_width'] ) ? 0 : $opt['site_width'];
		if ( $site_width ) {
			if ( empty( $opt['boxed'] ) ) {
				$out .= '.row,section.elementor-section.elementor-section-boxed>.elementor-container{width: ' . $site_width . '}.inner_layout .e-con {--content-width: min(100%, ' . $site_width . ')}';
			} else if ( $opt['boxed'] == '2' ) {
				$out .= '.layout_2,.layout_2 .cz_fixed_footer{width: ' . $site_width . '}.layout_2 .row{width: calc(' . $site_width . ' - 10%)}.layout_2 .e-con {--content-width: min(100%, ' . $site_width . ')}';
			} else {
				$out .= '.layout_1,.layout_1 .cz_fixed_footer{width: ' . $site_width . '}.layout_1 .row{width: calc(' . $site_width . ' - 10%)}.layout_1 .e-con {--content-width: min(100%, ' . $site_width . ')}';
			}
		}

		// Responsive CSS
		$bxw = empty( $opt['boxed'] ) ? '1240px' : '1300px';
		$rs1 = empty( $opt['site_width'] ) ? $bxw : ( Codevz_Plus::contains( $opt['site_width'], '%' ) ? '5000px' : $opt['site_width'] );

		// Responsive.
		$dynamic .= "\n\n/* Responsive */" . '@media screen and (max-width:' . $rs1 . '){#layout{width:100%!important}#layout.layout_1,#layout.layout_2{width:95%!important}.row{width:90% !important;padding:0}blockquote{padding:20px}footer .elms_center,footer .have_center .elms_left, footer .have_center .elms_center, footer .have_center .elms_right{float:none;display:block;text-align:center;margin:0 auto;flex:unset}}';

		// 768px.
		$dynamic .= '@media screen and (max-width:' . Codevz_Plus::option( 'tablet_breakpoint', '768px' ) . '){' . $dynamic_tablet . '}';

		// 480px.
		$dynamic .= '@media screen and (max-width:' . Codevz_Plus::option( 'mobile_breakpoint', '480px' ) . '){' . $dynamic_mobile . '}';

		// Fixed Border for Body
		if ( ! empty( $opt['_css_body'] ) && Codevz_Plus::contains( $opt['_css_body'], 'border-width' ) && Codevz_Plus::contains( $opt['_css_body'], 'border-color' ) ) {
			$out .= '.cz_fixed_top_border, .cz_fixed_bottom_border {border-top: ' . Codevz_Plus::get_string_between( $opt['_css_body'], 'border-width:', ';' ) . ' solid ' . Codevz_Plus::get_string_between( $opt['_css_body'], 'border-color:', ';' ) . '}';
		}

		// Site Colors
		if ( ! empty( $opt['site_color'] ) ) {
			$site_color = $opt['site_color'];

			$woo_bg = function_exists( 'is_woocommerce' ) ? ',.woocommerce input.button.alt.woocommerce #respond input#submit, .woocommerce a.button, .woocommerce button.button, .woocommerce input.button,.woocommerce .woocommerce-error .button,.woocommerce .woocommerce-info .button, .woocommerce .woocommerce-message .button, .woocommerce-page .woocommerce-error .button, .woocommerce-page .woocommerce-info .button, .woocommerce-page .woocommerce-message .button,#add_payment_method table.cart input, .woocommerce-cart table.cart input:not(.input-text), .woocommerce-checkout table.cart input,.woocommerce input.button:disabled, .woocommerce input.button:disabled[disabled],#add_payment_method table.cart input, #add_payment_method .wc-proceed-to-checkout a.checkout-button, .woocommerce-cart .wc-proceed-to-checkout a.checkout-button, .woocommerce-checkout .wc-proceed-to-checkout a.checkout-button,.woocommerce #payment #place_order, .woocommerce-page #payment #place_order,.woocommerce input.button.alt,.woocommerce #respond input#submit.alt:hover, .woocommerce button.button.alt:hover, .woocommerce input.button.alt:hover,.woocommerce #respond input#submit.alt:hover, .woocommerce a.button.alt:hover, .woocommerce nav.woocommerce-pagination ul li a:focus, .woocommerce nav.woocommerce-pagination ul li a:hover, .woocommerce nav.woocommerce-pagination ul li span.current, .widget_product_search #searchsubmit,.woocommerce .widget_price_filter .ui-slider .ui-slider-range, .woocommerce .widget_price_filter .ui-slider .ui-slider-handle, .woocommerce #respond input#submit, .woocommerce a.button, .woocommerce button.button, .woocommerce input.button, .woocommerce div.product form.cart .button, .xtra-product-icons,.woocommerce button.button.alt' : '';

			$out .= "\n\n/* Theme color */" . 'a:hover, .sf-menu > .cz.current_menu > a, .sf-menu > .cz .cz.current_menu > a,.sf-menu > .current-menu-parent > a,.comment-text .star-rating span {color: ' . $site_color . '} 
form button, .button, #edd-purchase-button, .edd-submit, .edd-submit.button.blue, .edd-submit.button.blue:hover, .edd-submit.button.blue:focus, [type=submit].edd-submit, .sf-menu > .cz > a:before,.sf-menu > .cz > a:before,
.post-password-form input[type="submit"], .wpcf7-submit, .submit_user, 
#commentform #submit, .commentlist li.bypostauthor > .comment-body:after,.commentlist li.comment-author-admin > .comment-body:after, 
 .pagination .current, .pagination > b, .pagination a:hover, .page-numbers .current, .page-numbers a:hover, .pagination .next:hover, 
.pagination .prev:hover, input[type=submit], .sticky:before, .commentlist li.comment-author-admin .fn,
input[type=submit],input[type=button],.cz_header_button,.cz_default_portfolio a,
.cz_readmore, .more-link, a.cz_btn, .cz_highlight_1:after, div.cz_btn ' . $woo_bg . ' {background-color: ' . $site_color . '}
.cs_load_more_doing, div.wpcf7 .wpcf7-form .ajax-loader {border-right-color: ' . $site_color . '}
input:focus,textarea:focus,select:focus {border-color: ' . $site_color . ' !important}
::selection {background-color: ' . $site_color . ';color: #fff}
::-moz-selection {background-color: ' . $site_color . ';color: #fff}';
		} // Primary Color

		// Magic mouse.
		if ( ! empty( $opt[ 'magic_mouse_inner_color' ] ) ) {

			$color = $opt['magic_mouse_inner_color'];

			$out .= '.codevz-magic-mouse div:first-child{background-color: ' . esc_html( $color ) . '}';

		}
		if ( ! empty( $opt[ 'magic_mouse_outer_color' ] ) ) {

			$color = $opt['magic_mouse_outer_color'];

			$out .= '.codevz-magic-mouse div:last-child{border-color: ' . esc_html( $color ) . '}';

		}
		if ( ! empty( $opt[ 'magic_mouse_on_hover' ] ) ) {

			$color = $opt['magic_mouse_on_hover'];

			$out .= '.codevz-magic-mouse-hover div:last-child{background-color: ' . esc_html( $color ) . '}';

		}

		// Custom CSS
		$out .= empty( $opt['css'] ) ? '' : "\n\n/* Custom */" . $opt['css'];

		// Enqueue Google Fonts
		if ( ! isset( $opt['_css_body_typo'] ) || ! Codevz_Plus::contains( $opt['_css_body_typo'], 'font-family' ) ) {
			$fonts[]['font'] = Codevz_Plus::$is_rtl ? 'font-family: "Cairo";' : 'font-family: "Open Sans";';
		}

		$fonts = wp_parse_args( (array) Codevz_Plus::option( 'wp_editor_fonts' ), $fonts );
		$final_fonts = array();
		foreach ( $fonts as $font ) {
			if ( isset( $font['font'] ) ) {
				$final_fonts[] = $font['font'];
				Codevz_Plus::load_font( $font['font'] );
			}
		}

		// Generated fonts
		update_option( 'codevz_fonts_out', $final_fonts );

		// Output
		return $out . $dynamic;
	}

	/**
	 *
	 * Get RGB numbers of HEX color
	 * 
	 * @var Hex color code
	 * @return string
	 *
	 */
	public static function hex2rgb( $c = '', $s = 0 ) {
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

		return implode( $s ? ', ' : ',', array( $r, $g, $b ) );
	}

	/**
	 * Update database, options for site colors changes
	 * 
	 * @var Old string and New string
	 */
	public static function updateDatabase( $o = '', $n = '', $custom_content ='' ) {

		if ( $o ) {

			$o = esc_html( $o );
			$n = esc_html( $n );

			$old_rgb = self::hex2rgb( $o );
			$new_rgb = self::hex2rgb( $n );
			$old_rgb_s = self::hex2rgb( $o, 1 );
			$new_rgb_s = self::hex2rgb( $n, 1 );

			if ( $custom_content ) {

				return str_replace( array( $o, $old_rgb, $old_rgb_s ), array( $n, $new_rgb, $new_rgb_s ), $custom_content );

			}

			$db = Codevz_Plus::database();

			// Posts and meta box.
			$db->query( "UPDATE " . $db->prefix . "posts SET post_content = replace(replace(replace(post_content, '" . $old_rgb_s . "','" . $new_rgb_s . "' ), '" . $old_rgb . "','" . $new_rgb . "' ), '" . $o . "','" . $n . "')" );
			$db->query( "UPDATE " . $db->prefix . "postmeta SET meta_value = replace(replace(meta_value, '" . strtoupper( $o ) . "','" . strtoupper( $n ) . "' ), '" . $o . "','" . $n . "' )" );
			$db->query( "UPDATE " . $db->prefix . "postmeta SET meta_value = replace(replace(meta_value, '" . $old_rgb_s . "','" . $new_rgb_s . "' ), '" . $old_rgb . "','" . $new_rgb . "' ) WHERE meta_key = '_elementor_data' AND meta_value LIKE '%rgba(%'" );

			// Widgets.
			$db->query( "UPDATE " . $db->prefix . "options SET option_value = replace(option_value, '" . $o . "','" . $n . "' ) WHERE option_name LIKE ('widget_%')" );

			// RevSlider.
			$db->query( "UPDATE " . $db->prefix . "revslider_slides SET layers = replace(replace(replace(layers, '" . $old_rgb_s . "','" . $new_rgb_s . "' ), '" . $old_rgb . "','" . $new_rgb . "' ), '" . $o . "','" . $n . "')" );
			$db->query( "UPDATE " . $db->prefix . "revslider_sliders SET params = replace(replace(replace(params, '" . $old_rgb_s . "','" . $new_rgb_s . "' ), '" . $old_rgb . "','" . $new_rgb . "' ), '" . $o . "','" . $n . "')" );

			// Theme options.
			$all = wp_json_encode( Codevz_Plus::option() );
			$all = str_replace( array( $o, $old_rgb, $old_rgb_s ), array( $n, $new_rgb, $new_rgb_s ), $all );
			update_option( 'codevz_theme_options', json_decode( $all, true ) );

			// Elementor.
			if ( did_action( 'elementor/loaded' ) ) {

				\Elementor\Plugin::$instance->files_manager->clear_cache();

			}

		}

	}

	/**
	 *  Check if page exists by slug.
	 */
	public static function check_post_exists_by_slug( $slug ) {

	    $args = [
	        'name'        => $slug,
	        'post_type'   => 'page',
	        'post_status' => 'any',
	        'numberposts' => 1
	    ];

	    $posts = get_posts( $args );

	    return !empty( $posts );

	}

	/**
	 *  Action after customizer saved
	 */
	public static function customize_save_after( $manage ) {

		$custom_stylekits_arr = wp_json_encode( Codevz_Plus::option( 'custom_stylekits', [] ) );

		if ( $custom_stylekits_arr !== get_option( 'xtra_custom_stylekits' ) ) {

			update_option( 'xtra_cache_selectors', false );
			update_option( 'xtra_custom_stylekits', $custom_stylekits_arr );

		}

		// Update new post types
		$new_cpt = Codevz_Plus::option( 'add_post_type' );
		if ( is_array( $new_cpt ) && wp_json_encode( $new_cpt ) !== wp_json_encode( get_option( 'codevz_post_types_org' ) ) ) {
			$post_types = array();
			foreach ( $new_cpt as $cpt ) {
				if ( isset( $cpt['name'] ) ) {
					$post_types[] = strtolower( $cpt['name'] );
				}
			}
			update_option( 'codevz_css_selectors', '' );
			update_option( 'codevz_post_types', $post_types );
			update_option( 'codevz_post_types_org', $new_cpt );
		} else if ( empty( $new_cpt ) ) {
			delete_option( 'codevz_post_types' );
		}

		// Update Google Fonts for WP editor
		$fonts = Codevz_Plus::option( 'wp_editor_fonts' );
		if ( wp_json_encode( $fonts ) !== wp_json_encode( get_option( 'codevz_wp_editor_google_fonts_org' ) ) ) {
			$wp_editor_fonts = '';
			$fonts = wp_parse_args( $fonts, array(
				array( 'font' => 'inherit' ),
				array( 'font' => 'Arial' ),
				array( 'font' => 'Arial Black' ),
				array( 'font' => 'Comic Sans MS' ),
				array( 'font' => 'Impact' ),
				array( 'font' => 'Lucida Sans Unicode' ),
				array( 'font' => 'Tahoma' ),
				array( 'font' => 'Trebuchet MS' ),
				array( 'font' => 'Verdana' ),
				array( 'font' => 'Courier New' ),
				array( 'font' => 'Lucida Console' ),
				array( 'font' => 'Georgia, serif' ),
				array( 'font' => 'Palatino Linotype' ),
				array( 'font' => 'Times New Roman' )
			));

			// Custom fonts
			$custom_fonts = (array) Codevz_Plus::option( 'upload_custom_fonts' );
			foreach ( $custom_fonts as $a ) {
				if ( ! empty( $a['font'] ) ) {
					$fonts[ $a['font'] ] = $a['font'];
				}
			}

			foreach ( $fonts as $font ) {
				if ( ! empty( $font['font'] ) ) {
					$font = $font['font'];
					if ( Codevz_Plus::contains( $font, ':' ) ) {
						$value = explode( ':', $font );
						$font = empty( $value[0] ) ? $font : $value[0];
						$wp_editor_fonts .= $font . '=' . $font . ';';
					} else {
						$title = ( $font === 'inherit' ) ? esc_html__( 'Inherit', 'codevz-plus' ) : $font;
						$wp_editor_fonts .= $title . '=' . $font . ';';
					}
				}
			}
			update_option( 'codevz_wp_editor_google_fonts', $wp_editor_fonts );
			update_option( 'codevz_wp_editor_google_fonts_org', $fonts );
		}

		// Update primary theme color
		$primary = Codevz_Plus::option( 'site_color' );
		$primary = str_replace( '#000000', '#000001', $primary );
		$primary = str_replace( '#ffffff', '#fffffe', $primary );
		$primary = str_replace( '#222222', '#222223', $primary );
		if ( $primary && $primary !== get_option( 'codevz_primary_color' ) ) {
			self::updateDatabase( get_option( 'codevz_primary_color', '#0045a0' ), $primary );
		}
		update_option( 'codevz_primary_color', $primary );

		// Update secondary theme color
		$secondary = Codevz_Plus::option( 'site_color_sec' );
		$secondary = str_replace( '#000000', '#000001', $secondary );
		$secondary = str_replace( '#ffffff', '#fffffe', $secondary );
		$secondary = str_replace( '#222222', '#222223', $secondary );
		if ( $secondary && $secondary !== get_option( 'codevz_secondary_color' ) ) {
			self::updateDatabase( get_option( 'codevz_secondary_color' ), $secondary );
		}
		update_option( 'codevz_secondary_color', $secondary );

		// Update Elementor global colors and add theme colors.
		$elmntr_kit = get_option( 'elementor_active_kit' );

		if ( $elmntr_kit ) {

		    $elmntr_settings = get_post_meta( $elmntr_kit, '_elementor_page_settings', true );

		    if ( isset( $elmntr_settings['custom_colors'] ) ) {

		    	$elmntr_settings['custom_colors'] = [];

		    	if ( $primary ) {

			        $elmntr_settings['custom_colors'][] = [
		        		'_id' => 'codevz_primary_color',
		        		'title' => esc_html__( 'Theme primary color', 'codevz-plus' ),
		        		'color' => $primary
		            ];

		    	}

		    	if ( $secondary ) {

			        $elmntr_settings['custom_colors'][] = [
		        		'_id' => 'codevz_secondary_color',
		        		'title' => esc_html__( 'Theme secondary color', 'codevz-plus' ),
		        		'color' => $secondary
		            ];

		    	}

				update_post_meta( $elmntr_kit, '_elementor_page_settings', $elmntr_settings );

		    }

		}

		// Fix and new generated CSS
		$options = get_option( 'codevz_theme_options' );

		$options['css_out'] = self::css_out();
		$options['site_color'] = $primary;
		$options['site_color_sec'] = $secondary;

		// Fix fonts
		$options['fonts_out'] = get_option( 'codevz_fonts_out' );

		// Create wishlist page.
		if ( ! get_option( 'xtra_woo_create_wishlist_compare' ) ) {

			if ( ! self::check_post_exists_by_slug( 'wishlist' ) ) {

				$wishlist = wp_insert_post(
					[
						'post_title'    => esc_html__( 'Wishlist', 'codevz-plus' ),
						'post_name' 	=> 'wishlist',
						'post_content'  => '[cz_wishlist]',
						'post_status'   => 'publish',
						'post_type' 	=> 'page'
					]
				);

			}

			if ( ! self::check_post_exists_by_slug( 'products-compare' ) ) {

				$compare = wp_insert_post(
					[
						'post_title'    => esc_html__( 'Products Compare', 'codevz-plus' ),
						'post_name' 	=> 'products-compare',
						'post_content'  => '[cz_compare]',
						'post_status'   => 'publish',
						'post_type' 	=> 'page'
					]
				);
				
			}

			update_option( 'xtra_woo_create_wishlist_compare', 1 );
		}

		// Reset white label.
		update_option( 'xtra_white_label', false );

		// Posts per page blog.
		if ( ! empty( $options[ 'posts_per_page' ] ) && get_option( 'posts_per_page' ) != $options[ 'posts_per_page' ] ) {
			update_option( 'posts_per_page', $options[ 'posts_per_page' ] );
		}

		// Update new options
		update_option( 'codevz_theme_options', $options );
	}

	/**
	 * List of custom sidebars
	 * 
	 * @return array list of available sidebars
	 */
	public static function custom_sidebars() {
		$out = [
			'primary' 	=> esc_html__( 'Primary', 'codevz-plus' ),
			'secondary' => esc_html__( 'Secondary', 'codevz-plus' ),
		];

		// Portfolio 
		$cpt = get_post_type_object( 'portfolio' );
		if ( ! empty( $cpt->labels->singular_name ) ) {
			$out[ 'portfolio-primary' ] = $cpt->labels->singular_name . ' ' . esc_html__( 'Primary', 'codevz-plus' );
			$out[ 'portfolio-secondary' ] = $cpt->labels->singular_name . ' ' . esc_html__( 'Secondary', 'codevz-plus' );
		}

		// Products 
		$cpt = get_post_type_object( 'product' );
		if ( ! empty( $cpt->labels->singular_name ) ) {
			$out[ 'product-primary' ] = $cpt->labels->singular_name . ' ' . esc_html__( 'Primary', 'codevz-plus' );
			$out[ 'product-secondary' ] = $cpt->labels->singular_name . ' ' . esc_html__( 'Secondary', 'codevz-plus' );
		}

		// Custom sidebars.
		$all = Codevz_Plus::option( 'custom_sidebars', [] );
		foreach( $all as $sidebar ) {
			if ( $sidebar ) {
				$out[ $sidebar ] = ucwords( str_replace( [ 'cz-custom-', '-' ], ' ', $sidebar ) );
			}
		}

		return $out;
	}

	/**
	 * Meta box for pages, posts, port types
	 * @return array
	 */
	public static function metabox() {

		$free = Codevz_Plus::$is_free;

		// Add one-page menu option for pages only
		add_filter( 'codevz_metabox', function( $a ) use ( $free ) {

			$a[0]['fields'][] = array(
				'id' 		=> 'one_page',
				'type' 		=> $free ? 'content' : 'select',
				'content' 	=> Codevz_Plus::pro_badge(),
				'title' 	=> esc_html__( 'Custom menu', 'codevz-plus' ),
				'desc' 		=> esc_html__( 'To manage menus, visit Dashboard > Appearance > Menus', 'codevz-plus' ),
				'options' 	=> array(
					'' 			=> esc_html__( '~ Default ~', 'codevz-plus' ), 
					'primary' 	=> esc_html__( 'Primary', 'codevz-plus' ), 
					'secondary' => esc_html__( 'Secondary', 'codevz-plus' ), 
					'1'  		=> esc_html__( 'One Page', 'codevz-plus' ), 
					'footer'  	=> esc_html__( 'Footer', 'codevz-plus' ),
					'mobile'  	=> esc_html__( 'Mobile', 'codevz-plus' ),
					'custom-1' 	=> esc_html__( 'Custom', 'codevz-plus' ) . ' 1', 
					'custom-2' 	=> esc_html__( 'Custom', 'codevz-plus' ) . ' 2', 
					'custom-3' 	=> esc_html__( 'Custom', 'codevz-plus' ) . ' 3',
					'custom-4' 	=> esc_html__( 'Custom', 'codevz-plus' ) . ' 4',
					'custom-5' 	=> esc_html__( 'Custom', 'codevz-plus' ) . ' 5'
				),
				'edit_link'  => get_admin_url( false, 'nav-menus.php' )
			);

			$a[0]['fields'][] = array(
				'id' 		=> 'hide_featured_image',
				'type' 		=> $free ? 'content' : 'select',
				'content' 	=> Codevz_Plus::pro_badge(),
				'title' 	=> esc_html__( 'Featured image', 'codevz-plus' ),
				'options' 	=> array(
					''  		=> esc_html__( '~ Default ~', 'codevz-plus' ),
					'1'  		=> esc_html__( 'Hide', 'codevz-plus' ),
					'2'  		=> esc_html__( 'Show', 'codevz-plus' ),
				)
			);

			$single_meta = array_flip( (array) Codevz_Plus::option( 'meta_data_post' ) );

			if ( self::get_post_type_admin() === 'post' && isset( $single_meta['source'] ) ) {

				$a[0]['fields'][] = array(
					'id' 		=> 'post_source_title',
					'type' 		=> 'text',
					'title' 	=> esc_html__( 'Source title', 'codevz-plus' )
				);

				$a[0]['fields'][] = array(
					'id' 		=> 'post_source_link',
					'type' 		=> 'text',
					'title' 	=> esc_html__( 'Source link', 'codevz-plus' )
				);

			}

			return $a;

		}, 999 );

		// SEO options
		$seo = Codevz_Plus::option( 'seo_meta_tags' ) ? array(
			array(
				'id' 		=> 'seo_desc',
				'type' 		=> 'textarea',
				'title' 	=> esc_html__( 'Description', 'codevz-plus' ),
			),
			array(
				'id' 		=> 'seo_keywords',
				'type' 		=> 'textarea',
				'title' 	=> esc_html__( 'Keywords', 'codevz-plus' ),
				'desc'		=> esc_html__( 'Separate with comma', 'codevz-plus' ),
			),
		) : array(
				array(
					'type'    => 'content',
					'content' => esc_html__( 'Unlock SEO options by going to Theme Options > General > SEO', 'codevz-plus' )
				),
		);
		$seo = array(
			  'name'   => 'page_seo_settings',
			  'title'  => esc_html__( 'SEO Settings', 'codevz-plus' ),
			  'icon'   => 'fa fa-search',
			  'fields' => $seo
		);

		// Post formats
		$post_formats = null;
		$pta = self::get_post_type_admin();
		if ( $pta === 'post' || post_type_supports( $pta, 'post-formats' ) ) {
			$post_formats = array(
				'name'   => 'post_format_settings',
				'title'  => esc_html__( 'Post format', 'codevz-plus' ),
				'icon'   => 'fa fa-cube',
				'fields' => array(
					array(
						'id' 		=> 'post_format',
						'type' 		=> 'codevz_image_select',
						'title' 	=> esc_html__( 'Post format', 'codevz-plus' ),
						'options' 		=> [
							'0' 				=> [ esc_html__( 'Standard', 'codevz-plus' ) 	, Codevz_Plus::$url . 'assets/img/post-standard.png' ],
							'gallery'			=> [ esc_html__( 'Gallery', 'codevz-plus' ) 		, Codevz_Plus::$url . 'assets/img/post-gallery.png' ],
							'video'				=> [ esc_html__( 'Video', 'codevz-plus' ) 		, Codevz_Plus::$url . 'assets/img/post-video.png' ],
							'audio'				=> [ esc_html__( 'Audio', 'codevz-plus' ) 		, Codevz_Plus::$url . 'assets/img/post-audio.png' ],
							'quote'				=> [ esc_html__( 'Quote', 'codevz-plus' ) 		, Codevz_Plus::$url . 'assets/img/post-quote.png' ],
						],
						'attributes' => array(
							'class' => 'post-formats-select'
						)
					),

					// Gallery format
					array(
						'id' 			=> 'gallery',
						'type' 			=> 'gallery',
						'title' 		=> esc_html__( 'Images', 'codevz-plus' ),
						'dependency' 	=> array( 'post_format', '==', 'gallery' ),
					),
					array(
						'id' 			=> 'gallery_layout',
						'type' 			=> 'codevz_image_select',
						'title' 		=> esc_html__( 'Layout', 'codevz-plus' ),
						'options' 		=> [
							'cz_grid_c1 cz_grid_l1'		=> [ '1 ' . esc_html__( 'Column', 'codevz-plus' ) 	, Codevz_Plus::$url . 'assets/img/gallery_2.png' ],
							'cz_grid_c2 cz_grid_l2'		=> [ '2 ' . esc_html__( 'Columns', 'codevz-plus' ) 	, Codevz_Plus::$url . 'assets/img/gallery_3.png' ],
							'cz_grid_c2'				=> [ '2 ' . esc_html__( 'Columns', 'codevz-plus' ) 	, Codevz_Plus::$url . 'assets/img/gallery_4.png' ],
							'cz_grid_c3'				=> [ '3 ' . esc_html__( 'Columns', 'codevz-plus' ) 	, Codevz_Plus::$url . 'assets/img/gallery_5.png' ],
							'cz_grid_c4'				=> [ '4 ' . esc_html__( 'Columns', 'codevz-plus' ) 	, Codevz_Plus::$url . 'assets/img/gallery_6.png' ],
							'cz_grid_c5'				=> [ '5 ' . esc_html__( 'Columns', 'codevz-plus' ) 	, Codevz_Plus::$url . 'assets/img/gallery_7.png' ],
							'cz_grid_c6'				=> [ '6 ' . esc_html__( 'Columns', 'codevz-plus' ) 	, Codevz_Plus::$url . 'assets/img/gallery_8.png' ],
							'cz_grid_c7'				=> [ '7 ' . esc_html__( 'Columns', 'codevz-plus' ) 	, Codevz_Plus::$url . 'assets/img/gallery_9.png' ],
							'cz_grid_c8'				=> [ '8 ' . esc_html__( 'Columns', 'codevz-plus' ) 	, Codevz_Plus::$url . 'assets/img/gallery_10.png' ],
							'cz_hr_grid cz_grid_c2'		=> [ '2 ' . esc_html__( 'Columns', 'codevz-plus' ) 	, Codevz_Plus::$url . 'assets/img/gallery_11.png' ],
							'cz_hr_grid cz_grid_c3'		=> [ '3 ' . esc_html__( 'Columns', 'codevz-plus' ) 	, Codevz_Plus::$url . 'assets/img/gallery_12.png' ],
							'cz_hr_grid cz_grid_c4'		=> [ '4 ' . esc_html__( 'Columns', 'codevz-plus' ) 	, Codevz_Plus::$url . 'assets/img/gallery_13.png' ],
							'cz_hr_grid cz_grid_c5'		=> [ '5 ' . esc_html__( 'Columns', 'codevz-plus' ) 	, Codevz_Plus::$url . 'assets/img/gallery_14.png' ],
							'cz_masonry cz_grid_c2'		=> [ '2 ' . esc_html__( 'Columns', 'codevz-plus' ) . ' ' . esc_html__( 'Masonry', 'codevz-plus' ) 	, Codevz_Plus::$url . 'assets/img/gallery_15.png' ],
							'cz_masonry cz_grid_c3'		=> [ '3 ' . esc_html__( 'Columns', 'codevz-plus' ) . ' ' . esc_html__( 'Masonry', 'codevz-plus' ) 	, Codevz_Plus::$url . 'assets/img/gallery_16.png' ],
							'cz_masonry cz_grid_c4'		=> [ '4 ' . esc_html__( 'Columns', 'codevz-plus' ) . ' ' . esc_html__( 'Masonry', 'codevz-plus' ) 	, Codevz_Plus::$url . 'assets/img/gallery_17.png' ],
							'cz_masonry cz_grid_c4 cz_grid_1big' => [ '3 ' . esc_html__( 'Columns', 'codevz-plus' ) . ' ' . esc_html__( 'Masonry', 'codevz-plus' ) 	, Codevz_Plus::$url . 'assets/img/gallery_18.png' ],
							'cz_masonry cz_grid_c5'		=> [ '5 ' . esc_html__( 'Columns', 'codevz-plus' ) . ' ' . esc_html__( 'Masonry', 'codevz-plus' ) 	, Codevz_Plus::$url . 'assets/img/gallery_19.png' ],
							'cz_metro_1 cz_grid_c4'		=> [ esc_html__( 'Metro', 'codevz-plus' ) . ' 1' 	, Codevz_Plus::$url . 'assets/img/gallery_20.png' ],
							'cz_metro_2 cz_grid_c4'		=> [ esc_html__( 'Metro', 'codevz-plus' ) . ' 2' 	, Codevz_Plus::$url . 'assets/img/gallery_21.png' ],
							'cz_metro_3 cz_grid_c4'		=> [ esc_html__( 'Metro', 'codevz-plus' ) . ' 3' 	, Codevz_Plus::$url . 'assets/img/gallery_22.png' ],
							'cz_metro_4 cz_grid_c4'		=> [ esc_html__( 'Metro', 'codevz-plus' ) . ' 4' 	, Codevz_Plus::$url . 'assets/img/gallery_23.png' ],
							'cz_metro_5 cz_grid_c3'		=> [ esc_html__( 'Metro', 'codevz-plus' ) . ' 5' 	, Codevz_Plus::$url . 'assets/img/gallery_24.png' ],
							'cz_metro_6 cz_grid_c3'		=> [ esc_html__( 'Metro', 'codevz-plus' ) . ' 6' 	, Codevz_Plus::$url . 'assets/img/gallery_25.png' ],
							'cz_metro_7 cz_grid_c7'		=> [ esc_html__( 'Metro', 'codevz-plus' ) . ' 7' 	, Codevz_Plus::$url . 'assets/img/gallery_26.png' ],
							'cz_metro_8 cz_grid_c4'		=> [ esc_html__( 'Metro', 'codevz-plus' ) . ' 8' 	, Codevz_Plus::$url . 'assets/img/gallery_27.png' ],
							'cz_metro_9 cz_grid_c6'		=> [ esc_html__( 'Metro', 'codevz-plus' ) . ' 9' 	, Codevz_Plus::$url . 'assets/img/gallery_28.png' ],
							'cz_metro_10 cz_grid_c6'	=> [ esc_html__( 'Metro', 'codevz-plus' ) . ' 10' 	, Codevz_Plus::$url . 'assets/img/gallery_29.png' ],
							'cz_grid_carousel'			=> [ esc_html__( 'Carousel Slider', 'codevz-plus' ) 	, Codevz_Plus::$url . 'assets/img/gallery_30.png' ],
						],
						'default' 		=> 'cz_grid_c3',
						'attributes' 	=> [ 'data-depend-id' => 'gallery_layout' ],
						'dependency' 	=> array( 'post_format', '==', 'gallery' ),
					),
					array(
						'id'        	=> 'gallery_gap',
						'type'      	=> 'slider',
						'title'     	=> esc_html__( 'Gap', 'codevz-plus' ),
						'options' 		=> array( 'unit' => 'px', 'step' => 1, 'min' => 0, 'max' => 100 ),
						'default' 		=> '20px',
						'dependency' 	=> array( 'post_format', '==', 'gallery' ),
					),
					array(
						'id'        	=> 'gallery_slides_to_show',
						'type'      	=> 'slider',
						'title'     	=> esc_html__( 'Slides', 'codevz-plus' ),
						'options' 		=> array( 'unit' => '', 'step' => 1, 'min' => 0, 'max' => 100 ),
						'default' 		=> '1',
						'dependency' 	=> array( 'post_format|gallery_layout', '==|==', 'gallery|cz_grid_carousel' ),
					),

					// Video format
					array(
						'id' 		=> 'video_type',
						'type' 		=> 'select',
						'title' 	=> esc_html__( 'Type', 'codevz-plus' ),
						'options' 	=> array(
							'url'  		=> esc_html__( 'Youtube or Vimeo', 'codevz-plus' ),
							'selfhost'  => esc_html__( 'Self hosted', 'codevz-plus' ),
							'embed'  	=> esc_html__( 'Embed', 'codevz-plus' ),
						),
						'dependency' 	=> array( 'post_format', '==', 'video' ),
					),
					array(
						'id' 		=> 'video_url',
						'type' 		=> 'text',
						'title' 	=> esc_html__( 'Video URL', 'codevz-plus' ),
						'dependency' 	=> array( 'post_format|video_type', '==|==', 'video|url' ),
					),
					array(
						'id'          => 'video_file',
						'type'        => 'upload',
						'title'       => esc_html__( 'MP4', 'codevz-plus' ),
						'settings'   => array(
							'upload_type'  => 'video/mp4',
							'insert_title' => esc_html__( 'Insert', 'codevz-plus' ),
						),
						'dependency' 	=> array( 'post_format|video_type', '==|==', 'video|selfhost' ),
					),
					array(
						'id' 		=> 'video_embed',
						'type' 		=> 'textarea',
						'title' 	=> esc_html__( 'Embed code', 'codevz-plus' ),
						'dependency' 	=> array( 'post_format|video_type', '==|==', 'video|embed' ),
					),

					// Audio format
					array(
						'id'          => 'audio_file',
						'type'        => 'upload',
						'title'       => esc_html__('MP3 or Stream URL', 'codevz-plus' ),
						'settings'   => array(
							'upload_type'  => 'audio/mpeg',
							'insert_title' => esc_html__( 'Insert', 'codevz-plus' ),
						),
						'dependency' 	=> array( 'post_format', '==', 'audio' ),
					),

					// Quote format
					array(
						'id' 		=> 'quote',
						'type' 		=> 'textarea',
						'title' 	=> esc_html__( 'Quote', 'codevz-plus' ),
						'dependency' 	=> array( 'post_format', '==', 'quote' ),
					),
					array(
						'id' 		=> 'cite',
						'type' 		=> 'text',
						'title' 	=> esc_html__( 'Cite', 'codevz-plus' ),
						'dependency' 	=> array( 'post_format', '==', 'quote' ),
					),
				)
			);
		}

		$post_types = array_flip( wp_parse_args( get_post_types(), array( 'post', 'page', 'product', 'download', 'forum', 'topic', 'reply' ) ) );
		$post_types = self::post_types( $post_types );

		// Remove products additional post types meta box.
		if ( isset( $post_types[ 'codevz_size_guide' ] ) ) {
			unset( $post_types[ 'codevz_size_guide' ] );
		}
		if ( isset( $post_types[ 'codevz_faq' ] ) ) {
			unset( $post_types[ 'codevz_faq' ] );
		}

		$fixed_side = Codevz_Plus::option( 'fixed_side' );

		// Return meta box
		return array(array(
			'id'           => 'codevz_page_meta',
			'title'        => esc_html__( 'Page Settings', 'codevz-plus' ),
			'post_type'    => $post_types,
			'context'      => 'normal',
			'priority'     => 'default',
			'show_restore' => true,
			'sections'     => apply_filters( 'codevz_metabox', array(

				array(
				  'name'   => 'page_general_settings',
				  'title'  => esc_html__( 'General Settings', 'codevz-plus' ),
				  'icon'   => 'fa fa-cog',
				  'fields' => array(
					array(
						'id' 			=> 'layout',
						'type' 			=> 'codevz_image_select',
						'title' 		=> esc_html__( 'Sidebar', 'codevz-plus' ),
						'desc'  		=> esc_html__( 'The default sidebar position can be adjusted in Theme Options > General > Sidebar position', 'codevz-plus' ),
						'options' 		=> [
							'1' 			=> [ esc_html__( '~ Default ~', 'codevz-plus' ) 	, Codevz_Plus::$url . 'assets/img/sidebar-0.png' ],
							'none' 			=> [ esc_html__( 'No Sidebar', 'codevz-plus' ) 			, Codevz_Plus::$url . 'assets/img/off.png' ],
							'bpnp' 			=> [ esc_html__( 'Fullwidth', 'codevz-plus' ) 			, Codevz_Plus::$url . 'assets/img/sidebar-2.png' ],
							'center'		=> [ esc_html__( 'Center Mode', 'codevz-plus' ) 			, Codevz_Plus::$url . 'assets/img/sidebar-13.png' ],
							'right' 		=> [ esc_html__( 'Right Sidebar', 'codevz-plus' ) 		, Codevz_Plus::$url . 'assets/img/sidebar-3.png' ],
							'right-s' 		=> [ esc_html__( 'Right Sidebar Small', 'codevz-plus' ) 	, Codevz_Plus::$url . 'assets/img/sidebar-4.png' ],
							'left' 			=> [ esc_html__( 'Left Sidebar', 'codevz-plus' ) 		, Codevz_Plus::$url . 'assets/img/sidebar-5.png' ],
							'left-s' 		=> [ esc_html__( 'Left Sidebar Small', 'codevz-plus' ) 	, Codevz_Plus::$url . 'assets/img/sidebar-6.png' ],
							'both-side' 	=> [ esc_html__( 'Both Sidebar', 'codevz-plus' ) . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ) 		, Codevz_Plus::$url . 'assets/img/sidebar-7.png' ],
							'both-side2' 	=> [ esc_html__( 'Both Sidebar Small', 'codevz-plus' ) . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ) 	, Codevz_Plus::$url . 'assets/img/sidebar-8.png' ],
							'both-right' 	=> [ esc_html__( 'Both Sidebar Right', 'codevz-plus' ) . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ) 	, Codevz_Plus::$url . 'assets/img/sidebar-9.png' ],
							'both-right2' 	=> [ esc_html__( 'Both Sidebar Right', 'codevz-plus' ) . ' 2' . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ) , Codevz_Plus::$url . 'assets/img/sidebar-10.png' ],
							'both-left' 	=> [ esc_html__( 'Both Sidebar Left', 'codevz-plus' ) . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ) 	, Codevz_Plus::$url . 'assets/img/sidebar-11.png' ],
							'both-left2' 	=> [ esc_html__( 'Both Sidebar Left', 'codevz-plus' ) . ' 2' . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' )  , Codevz_Plus::$url . 'assets/img/sidebar-12.png' ],
						],
						'default' 		=> ( self::get_post_type_admin() === 'page' ) ? 'none' : '1',
						'attributes' 	=> [ 'data-depend-id' => 'layout' ]
					),
					array(
						'id'      			=> 'primary',
						'type'    			=> 'select',
						'title'   			=> esc_html__( 'Primary Sidebar', 'codevz-plus' ),
						'desc'    			=> esc_html__( 'You can create custom sidebar from Appearance > Widgets then select it here.', 'codevz-plus' ),
						'options' 			=> self::custom_sidebars(),
						'edit_link' 		=> get_admin_url( false, 'widgets.php' ),
						'default_option' 	=> esc_html__( '~ Default ~', 'codevz-plus' ),
						'dependency' 		=> array( 'layout', 'any', 'right,right-s,left,left-s,both-side,both-side2,both-right,both-right2,both-left,both-left2' ),
					),
					array(
						'id'      			=> 'secondary',
						'type'    			=> 'select',
						'title'   			=> esc_html__( 'Secondary Sidebar', 'codevz-plus' ),
						'desc'    			=> esc_html__( 'You can create custom sidebar from Appearance > Widgets then select it here.', 'codevz-plus' ),
						'options' 			=> self::custom_sidebars(),
						'edit_link' 		=> get_admin_url( false, 'widgets.php' ),
						'default_option' 	=> esc_html__( '~ Default ~', 'codevz-plus' ),
						'dependency' 		=> array( 'layout', 'any', 'both-side,both-side2,both-right,both-right2,both-left,both-left2' ),
					),
					array(
						'id' 			=> 'page_content_margin',
						'type' 			=> 'codevz_image_select',
						'title' 		=> esc_html__( 'Page Content Gap', 'codevz-plus' ),
						'desc'    		=> esc_html__( 'The gap between header, content and footer', 'codevz-plus' ),
						'options' 		=> [
							'' 				=> [ esc_html__( '~ Default ~', 'codevz-plus' ) 							, Codevz_Plus::$url . 'assets/img/content-gap-1.png' ],
							'mt0' 			=> [ esc_html__( 'No gap between header and content', 'codevz-plus' ) 			, Codevz_Plus::$url . 'assets/img/content-gap-2.png' ],
							'mb0' 			=> [ esc_html__( 'No gap between content and footer', 'codevz-plus' ) 			, Codevz_Plus::$url . 'assets/img/content-gap-3.png' ],
							'mt0 mb0' 		=> [ esc_html__( 'No gap between header, content and footer', 'codevz-plus' ) 	, Codevz_Plus::$url . 'assets/img/content-gap-4.png' ],
						],
					),
			array(
				'id'        	=> '_css_page_body_bg',
				'type'      	=> 'cz_sk',
				'title'     	=> esc_html__( 'Page Background', 'codevz-plus' ),
				'button'    	=> esc_html__( 'Page Background', 'codevz-plus' ),
				'settings'    	=> array( 'background' ),
				'selector'    	=> '',
				'desc'   	=> esc_html__( 'Color or Image', 'codevz-plus' ),
			),
			array('id' => '_css_page_body_bg_tablet','type' => 'cz_sk_hidden','selector' => ''),
			array('id' => '_css_page_body_bg_mobile','type' => 'cz_sk_hidden','selector' => ''),

			array(
				'id'  		=> 'hide_header',
				'type' 		=> $free ? 'content' : 'switcher',
				'content' 	=> Codevz_Plus::pro_badge(),
				'title' 	=> esc_html__( 'Hide Header', 'codevz-plus' ),
				'desc'   	=> esc_html__( 'Hide it only on this page', 'codevz-plus' ),
			),
			array(
				'id'  		=> 'hide_footer',
				'type' 		=> $free ? 'content' : 'switcher',
				'content' 	=> Codevz_Plus::pro_badge(),
				'title' 	=> esc_html__( 'Hide Footer', 'codevz-plus' ),
				'desc'   	=> esc_html__( 'Hide it only on this page', 'codevz-plus' ),
			),
			array(
				'id'    	=> 'custom_header',
				'type' 		=> $free ? 'content' : 'select',
				'content' 	=> Codevz_Plus::pro_badge(),
				'title' 	=> esc_html__( 'Custom header', 'codevz-plus' ),
				'desc' 		=> esc_html__( 'This option lets you easily set a custom template.', 'codevz-plus' ),
				'options' 	=> Codevz_Plus::$array_pages,
				'edit_link' => true,
				'dependency' 	=> array( 'hide_header', '!=', 'true' )
			),
			array(
				'id'          	=> 'custom_logo',
				'type'        	=> 'upload',
				'preview' 		=> true,
				'title'       	=> esc_html__( 'Custom logo', 'codevz-plus' ),
				'desc' 			=> esc_html__( 'You can set custom logo for this individual page.', 'codevz-plus' ),
				'dependency' 	=> array( 'hide_header', '!=', 'true' )
			),
			array(
				'id'    	=> 'custom_footer',
				'type' 		=> $free ? 'content' : 'select',
				'content' 	=> Codevz_Plus::pro_badge(),
				'title' 	=> esc_html__( 'Custom footer', 'codevz-plus' ),
				'desc' 		=> esc_html__( 'This option lets you easily set a custom template.', 'codevz-plus' ),
				'options' 	=> Codevz_Plus::$array_pages,
				'edit_link' => true,
				'dependency' 	=> array( 'hide_footer', '!=', 'true' )
			),

		)
	  ), // page_general_settings

	  array(
		'name'   => 'page_header',
		'title'  => esc_html__( 'Header Settings', 'codevz-plus' ),
		'icon'   => 'fa fa-paint-brush',
		'fields' => array(
			array(
				'id' 			=> 'cover_than_header',
				'type' 			=> 'codevz_image_select',
				'title' 		=> esc_html__( 'Header Position', 'codevz-plus' ),
				'desc'      	=> esc_html__( 'The default option can be adjusted in Theme Options > Header > Title & Breadcrumbs', 'codevz-plus' ),
				'options' 		=> [
					'd' 					=> [ esc_html__( '~ Default ~', 'codevz-plus' ) 						, Codevz_Plus::$url . 'assets/img/sidebar-0.png' ],
					'header_top' 			=> [ esc_html__( 'Header before title', 'codevz-plus' ) 						, Codevz_Plus::$url . 'assets/img/header-before-title.png' ],
					'header_after_cover' 	=> [ esc_html__( 'Header after title', 'codevz-plus' ) 						, Codevz_Plus::$url . 'assets/img/header-after-title.png' ],
					'header_onthe_cover' 	=> [ esc_html__( 'Overlay only on desktop', 'codevz-plus' ) 				, Codevz_Plus::$url . 'assets/img/header-overlay-desktop.png' ],
					'header_onthe_cover header_onthe_cover_dt' 		=> [ esc_html__( 'Overlay only on desktop & tablet', 'codevz-plus' ) 	, Codevz_Plus::$url . 'assets/img/header-overlay-desktop-tablet.png' ],
					'header_onthe_cover header_onthe_cover_all' 	=> [ esc_html__( 'Overlay on all devices', 'codevz-plus' ) 	, Codevz_Plus::$url . 'assets/img/header-overlay-all.png' ],
				],
				'default'   => 'd',
			),
		  array(
			'id' 			=> 'page_cover',
			'type' 			=> 'codevz_image_select',
			'title' 		=> esc_html__( 'Title Type', 'codevz-plus' ),
			'options' 		=> [
				'1' 			=> [ esc_html__( '~ Default ~', 'codevz-plus' ) 	, Codevz_Plus::$url . 'assets/img/sidebar-0.png' ],
				'none' 			=> [ esc_html__( '~ Disable ~', 'codevz-plus' ) 	, Codevz_Plus::$url . 'assets/img/off.png' ],
				'title' 		=> [ esc_html__( 'Title & Breadcrumbs', 'codevz-plus' ) 	, Codevz_Plus::$url . 'assets/img/header-before-title.png' ],
				'rev'			=> [ esc_html__( 'Revolution Slider', 'codevz-plus' ) . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ) 	, Codevz_Plus::$url . 'assets/img/title-slider.png' ],
				'image' 		=> [ esc_html__( 'Custom Image', 'codevz-plus' ) 		, Codevz_Plus::$url . 'assets/img/title-image.png' ],
				'custom' 		=> [ esc_html__( 'Custom Shortcode', 'codevz-plus' ) . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ) 	, Codevz_Plus::$url . 'assets/img/title-custom-code.png' ],
				'page' 			=> [ esc_html__( 'Custom Page Content', 'codevz-plus' ) . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ) 	, Codevz_Plus::$url . 'assets/img/title-custom-page.png' ],
			],
			'default' 		=> '1',
			'desc' 			=> esc_html__( 'If you want to learn more about how title section works, set this to default then go to Theme Options > Header > Title & Breadcrumbs and change settings.', 'codevz-plus' ),
			'help' 			=> esc_html__( 'Title and breadcrumbs only can be set from Theme Options > Header > Title & Breadcrumbs', 'codevz-plus' ),
		  ),
		  array(
			'id'    		=> 'page_cover_image',
			'type'    		=> 'image',
			'title'   		=> esc_html__( 'Image', 'codevz-plus' ),
			'dependency' 	=> array( 'page_cover', '==', 'image' ),
		  ),
		  array(
			'id'    		=> 'page_cover_page',
			'type' 			=> $free ? 'content' : 'select',
			'content' 		=> Codevz_Plus::pro_badge(),
			'title'   		=> esc_html__( 'Content', 'codevz-plus' ),
			'desc'   		=> esc_html__( 'You can create custom page from Dashboard > Pages and assign it here, This will show instead title section for this page.', 'codevz-plus' ),
			'options'   	=> Codevz_Plus::$array_pages,
			'edit_link' 	=> true,
			'dependency' 	=> array( 'page_cover', '==', 'page' ),
		  ),
		  array(
			'id'    		=> 'page_cover_custom',
			'type' 			=> $free ? 'content' : 'textarea',
			'content' 		=> Codevz_Plus::pro_badge(),
			'title'   		=> esc_html__( 'Custom Shortcode', 'codevz-plus' ),
			'desc' 			=> esc_html__( 'Shortcode or custom HTML codes allowed, This will show instead title section.', 'codevz-plus' ),
			'dependency' 	=> array( 'page_cover', '==', 'custom' )
		  ),
		  array(
			'id'    		=> 'page_cover_rev',
			'type' 			=> $free ? 'content' : 'select',
			'content' 		=> Codevz_Plus::pro_badge(),
			'title'   		=> esc_html__( 'Select Slider', 'codevz-plus' ),
			'desc' 			=> esc_html__( 'You can create slider from Dashboard > Revolution Slider then assign it here.', 'codevz-plus' ),
			'options'   	=> self::revslider(),
			'edit_link' 	=> get_admin_url( false, 'admin.php?page=revslider' ),
			'dependency' 	=> array( 'page_cover', '==', 'rev' ),
			'default_option' => esc_html__( '~ Select ~', 'codevz-plus' ),
		  ),
		  array(
			'id'    		=> 'page_show_br',
			'type'    		=> 'switcher',
			'title'   		=> esc_html__( 'Title & Breadcrumbs', 'codevz-plus' ),
			'desc'   		=> esc_html__( 'Showing title and breadcrumbs section after above option', 'codevz-plus' ),
			'dependency' 	=> array( 'page_cover', 'any', 'rev,image,custom,page' )
		  ),
			array(
				'id'        => 'page_title_color',
				'type'      => 'color_picker',
				'title'     => esc_html__( 'Title Color', 'codevz-plus' ),
			), 
		  array(
			'id'        => '_css_page_title',
			'type' 		=> $free ? 'cz_sk_free' : 'cz_sk',
			'title'     => esc_html__( 'Title Background', 'codevz-plus' ),
			'button'    => esc_html__( 'Title Background', 'codevz-plus' ),
			'settings'  => array( 'background', 'border' ),
			'selector'  => ''
		  ),
		  array('id' => '_css_page_title_tablet','type' => 'cz_sk_hidden','selector' => ''),
		  array('id' => '_css_page_title_mobile','type' => 'cz_sk_hidden','selector' => ''),

		  array(
			'id'      	=> '_css_container_header_1',
			'type' 		=> $free ? 'cz_sk_free' : 'cz_sk',
			'title'    	=> esc_html__( 'Header Top Bar', 'codevz-plus' ),
			'button'    => esc_html__( 'Header Top Bar', 'codevz-plus' ),
			'settings' 	=> array( 'background', 'border' ),
			'selector' 	=> ''
		  ),
		  array('id' => '_css_container_header_1_tablet','type' => 'cz_sk_hidden','selector' => ''),
		  array('id' => '_css_container_header_1_mobile','type' => 'cz_sk_hidden','selector' => ''),

		  array(
			'id'      => '_css_container_header_2',
			'type' 		=> $free ? 'cz_sk_free' : 'cz_sk',
			'title'    => esc_html__( 'Header', 'codevz-plus' ),
			'button'    => esc_html__( 'Header', 'codevz-plus' ),
			'settings'    => array( 'background', 'border' ),
			'selector'    => ''
		  ),
		  array('id' => '_css_container_header_2_tablet','type' => 'cz_sk_hidden','selector' => ''),
		  array('id' => '_css_container_header_2_mobile','type' => 'cz_sk_hidden','selector' => ''),

		  array(
			'id'      => '_css_container_header_3',
			'type' 		=> $free ? 'cz_sk_free' : 'cz_sk',
			'title'    => esc_html__( 'Header Bottom Bar', 'codevz-plus' ),
			'button'    => esc_html__( 'Header Bottom Bar', 'codevz-plus' ),
			'settings'    => array( 'background', 'border' ),
			'selector'    => ''
		  ),
		  array('id' => '_css_container_header_3_tablet','type' => 'cz_sk_hidden','selector' => ''),
		  array('id' => '_css_container_header_3_mobile','type' => 'cz_sk_hidden','selector' => ''),

		  array(
			'id'        => '_css_header_container',
			'type' 		=> $free ? 'cz_sk_free' : 'cz_sk',
			'title'     => esc_html__( 'Overall Header', 'codevz-plus' ),
			'button'    => esc_html__( 'Overall Header', 'codevz-plus' ),
			'settings'  => array( 'background', 'border' ),
			'selector'  => ''
		  ),
		  array('id' => '_css_header_container_tablet','type' => 'cz_sk_hidden','selector' => ''),
		  array('id' => '_css_header_container_mobile','type' => 'cz_sk_hidden','selector' => ''),

		  array(
			'id'        => '_css_fixed_side_style',
			'type' 		=> $free ? 'cz_sk_free' : 'cz_sk',
			'title'     => esc_html__( 'Fixed Side', 'codevz-plus' ),
			'desc'      => esc_html__( 'You can activate "Fixed Side" option from Theme Options > Header > Fixed Side', 'codevz-plus' ),
			'button'    => esc_html__( 'Fixed Side', 'codevz-plus' ),
			'settings'  => array( 'background', 'width', 'border' ),
			'selector'  => '',
			'dependency'=> $fixed_side ? [] : [ 'xxx', '==', 'xxx' ]
		  ),
		  array('id' => '_css_fixed_side_style_tablet','type' => 'cz_sk_hidden','selector' => ''),
		  array('id' => '_css_fixed_side_style_mobile','type' => 'cz_sk_hidden','selector' => ''),

		)
	  ), // page_header_settings
				$seo,
				$post_formats
			))
		));
	}

	/**
	 *
	 * Breadcrumbs and title options
	 * 
	 * @var post type name, CSS selector
	 * @return array
	 *
	 */
	public static function title_options( $i = '', $c = '' ) {

		$free = Codevz_Plus::$is_free;

		if ( $i ) {
			return array(
				array(
					'id' 	=> 'page_cover' . $i,
					'type' 	=> 'codevz_image_select',
					'title' => esc_html__( 'Title Type', 'codevz-plus' ),
					'options' 		=> [
						( $i ? '1' : '' ) => [ esc_html__( '~ Default ~', 'codevz-plus' ) 	, Codevz_Plus::$url . 'assets/img/sidebar-0.png' ],
						'none' 			=> [ esc_html__( '~ Disable ~', 'codevz-plus' ) 	, Codevz_Plus::$url . 'assets/img/off.png' ],
						'title' 		=> [ esc_html__( 'Title & Breadcrumbs', 'codevz-plus' ) 	, Codevz_Plus::$url . 'assets/img/header-before-title.png' ],
						'rev'			=> [ esc_html__( 'Revolution Slider', 'codevz-plus' ) . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ) 	, Codevz_Plus::$url . 'assets/img/title-slider.png' ],
						'image' 		=> [ esc_html__( 'Custom Image', 'codevz-plus' ) 		, Codevz_Plus::$url . 'assets/img/title-image.png' ],
						'custom' 		=> [ esc_html__( 'Custom Shortcode', 'codevz-plus' ) . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ) 	, Codevz_Plus::$url . 'assets/img/title-custom-code.png' ],
						'page' 			=> [ esc_html__( 'Custom Page Content', 'codevz-plus' ) . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ) 	, Codevz_Plus::$url . 'assets/img/title-custom-page.png' ],
					],
					'help'  	=> esc_html__( 'The default option for all pages', 'codevz-plus' ),
					'default' 	=> $i ? '1' : 'none'
				),
				array(
					'id'    		=> 'page_cover_image' . $i,
					'type'    		=> 'image',
					'title'   		=> esc_html__( 'Image', 'codevz-plus' ),
					'dependency' 	=> array( 'page_cover' . $i, '==', 'image' ),
				),
				array(
					'id'            => 'page_cover_page' . $i,
					'type'          => 'select',
					'title'         => esc_html__( 'Content', 'codevz-plus' ),
					'help'   		=> esc_html__( 'You can create custom page from Dashboard > Pages and assign it here, This will show instead title section.', 'codevz-plus' ),
					'options'       => Codevz_Plus::$array_pages,
					'edit_link' 	=> true,
					'dependency' 	=> array( 'page_cover' . $i, '==', 'page' )
				),
				array(
					'id' 		=> 'page_cover_custom' . $i,
					'type' 		=> 'textarea',
					'title' 	=> esc_html__( 'Custom Shortcode', 'codevz-plus' ),
					'help' 		=> esc_html__( 'Shortcode and custom HTML code allowed.', 'codevz-plus' ),
					'dependency' => array( 'page_cover' . $i, '==', 'custom' )
				),
				array(
					'id' 			=> 'page_cover_rev' . $i,
					'type' 			=> 'select',
					'title' 		=> esc_html__( 'Select Slider', 'codevz-plus' ),
					'help' 			=> esc_html__( 'You can create slider from Dashboard > Revolution Slider then assign it here.', 'codevz-plus' ),
					'options' 		=> self::revslider(),
					'edit_link' 	=> get_admin_url( false, 'admin.php?page=revslider' ),
					'dependency' 	=> array( 'page_cover' . $i, '==', 'rev' ),
					'default_option' => esc_html__( '~ Select ~', 'codevz-plus' ),
				),
				array(
					'id' 			=> '_css_page_title' . $i,
					'type' 			=> 'cz_sk',
					'title' 		=> esc_html__( 'Container Background', 'codevz-plus' ),
					'button' 		=> esc_html__( 'Container Background', 'codevz-plus' ),
					'setting_args' 	=> [ 'transport' => 'postMessage' ],
					'settings' 		=> array( 'background', 'border' ),
					'selector' 		=> $c . '.page_title,' . $c . '.header_onthe_cover .page_title',
					'dependency' 	=> array( 'page_cover' . $i, '==', 'title' )
				),
				array(
					'id' 			=> '_css_page_title' . $i . '_tablet',
					'type' 			=> 'cz_sk_hidden',
					'setting_args' 	=> [ 'transport' => 'postMessage' ],
					'selector' 		=> $c . '.page_title,' . $c . '.header_onthe_cover .page_title'
				),
				array(
					'id' 			=> '_css_page_title' . $i . '_mobile',
					'type' 			=> 'cz_sk_hidden',
					'setting_args' 	=> [ 'transport' => 'postMessage' ],
					'selector' 		=> $c . '.page_title,' . $c . '.header_onthe_cover .page_title'
				),
			);
		} else {
			return array(
				array(
					'id' 			=> 'cover_than_header',
					'type' 			=> 'codevz_image_select',
					'title' 		=> esc_html__( 'Header position', 'codevz-plus' ),
					'help' 			=> esc_html__( 'The header position adjusts based on the page title and breadcrumbs', 'codevz-plus' ),
					'options' 		=> [
						'' 						=> [ esc_html__( '~ Default ~', 'codevz-plus' ) 				, Codevz_Plus::$url . 'assets/img/sidebar-0.png' ],
						'header_top' 			=> [ esc_html__( 'Header before title', 'codevz-plus' ) 		, Codevz_Plus::$url . 'assets/img/header-before-title.png' ],
						'header_after_cover' 	=> [ esc_html__( 'Header after title', 'codevz-plus' ) 		, Codevz_Plus::$url . 'assets/img/header-after-title.png' ],
						'header_onthe_cover' 	=> [ esc_html__( 'Overlay on desktop', 'codevz-plus' ) 		, Codevz_Plus::$url . 'assets/img/header-overlay-desktop.png' ],
						'header_onthe_cover header_onthe_cover_dt' 		=> [ esc_html__( 'Overlay on desktop & tablet', 'codevz-plus' ) 	, Codevz_Plus::$url . 'assets/img/header-overlay-desktop-tablet.png' ],
						'header_onthe_cover header_onthe_cover_all' 	=> [ esc_html__( 'Overlay on all devices', 'codevz-plus' ) 		, Codevz_Plus::$url . 'assets/img/header-overlay-all.png' ],
					],
				),
				array(
					'id' 	=> 'page_cover',
					'type' 	=> 'codevz_image_select',
					'title' => esc_html__( 'Title type', 'codevz-plus' ),
					'options' 		=> [
						'' 				=> [ esc_html__( '~ Default ~', 'codevz-plus' ) 	, Codevz_Plus::$url . 'assets/img/sidebar-0.png' ],
						'none' 			=> [ esc_html__( '~ Disable ~', 'codevz-plus' ) 	, Codevz_Plus::$url . 'assets/img/off.png' ],
						'title' 		=> [ esc_html__( 'Title & Breadcrumbs', 'codevz-plus' ) 	, Codevz_Plus::$url . 'assets/img/header-before-title.png' ],
						'rev'			=> [ esc_html__( 'Revolution Slider', 'codevz-plus' ) . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ) 	, Codevz_Plus::$url . 'assets/img/title-slider.png' ],
						'image' 		=> [ esc_html__( 'Custom Image', 'codevz-plus' ) 		, Codevz_Plus::$url . 'assets/img/title-image.png' ],
						'custom' 		=> [ esc_html__( 'Custom Shortcode', 'codevz-plus' ) . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ) 	, Codevz_Plus::$url . 'assets/img/title-custom-code.png' ],
						'page' 			=> [ esc_html__( 'Custom Page Content', 'codevz-plus' ) . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ) 	, Codevz_Plus::$url . 'assets/img/title-custom-page.png' ],
					],
					'help'  	=> esc_html__( 'This option applies to all internal pages of your website as default.', 'codevz-plus' ),
					'default' 	=> ''
				),
				array(
					'id'    		=> 'page_cover_image',
					'type'    		=> 'image',
					'title'   		=> esc_html__( 'Image', 'codevz-plus' ),
					'dependency' 	=> array( 'page_cover', '==', 'image' ),
				),
				array(
					'id'            => 'page_cover_page',
					'type'          => 'select',
					'title'         => esc_html__( 'Content', 'codevz-plus' ),
					'help'   		=> esc_html__( 'You can create custom page from Dashboard > Pages and assign it here, This will show instead title section.', 'codevz-plus' ),
					'options'       => Codevz_Plus::$array_pages,
					'edit_link' 	=> true,
					'dependency' 	=> array( 'page_cover', '==', 'page' )
				),
				array(
					'id' 		=> 'page_cover_custom',
					'type' 		=> 'textarea',
					'title' 	=> esc_html__( 'Custom Shortcode', 'codevz-plus' ),
					'help' 		=> esc_html__( 'Shortcode and custom HTML code allowed.', 'codevz-plus' ),
					'dependency' => array( 'page_cover', '==', 'custom' )
				),
				array(
					'id' 			=> 'page_cover_rev',
					'type' 			=> 'select',
					'title' 		=> esc_html__( 'Select Slider', 'codevz-plus' ),
					'help' 			=> esc_html__( 'You can create slider from Dashboard > Revolution Slider then assign it here.', 'codevz-plus' ),
					'options' 		=> self::revslider(),
					'edit_link' 	=> get_admin_url( false, 'admin.php?page=revslider' ),
					'dependency' 	=> array( 'page_cover', '==', 'rev' ),
					'default_option' => esc_html__( '~ Select ~', 'codevz-plus' ),
				),

				array(
					'id' 			=> 'page_title',
					'type' 			=> 'select',
					'title' 		=> esc_html__( 'Position', 'codevz-plus' ),
					'options' 		=> array(
						'1' 	=> esc_html__( '~ Default ~', 'codevz-plus' ),
						'3' 	=> esc_html__( 'Only Title', 'codevz-plus' ),
						'2' 	=> esc_html__( 'Title above content', 'codevz-plus' ),
						'4' 	=> esc_html__( 'Title and Breadcrumbs', 'codevz-plus' ),
						'5' 	=> esc_html__( 'Breadcrumbs and Title', 'codevz-plus' ),
						'6' 	=> esc_html__( 'Title left and Breadcrumbs right', 'codevz-plus' ),
						'7' 	=> esc_html__( 'Breadcrumbs', 'codevz-plus' ),
						'9' 	=> esc_html__( 'Breadcrumbs right', 'codevz-plus' ),
						'8' 	=> esc_html__( 'Breadcrumbs and title above content', 'codevz-plus' ),
						'10' 	=> esc_html__( 'Breadcrumbs + title above content', 'codevz-plus' ),
					),
					'dependency' 	=> array( 'page_cover', '==', 'title' ),
					'default' 		=> '1'
				),
				array(
					'id'      		=> 'post_views_count',
					'type'      	=> $free ? 'content' : 'switcher',
					'content' 		=> Codevz_Plus::pro_badge(),
					'title'   		=> esc_html__( 'Post views count', 'codevz-plus' ),
					'help'   		=> esc_html__( 'Showing post view count under the post title in single post', 'codevz-plus' ),
					'dependency'  	=> array( 'page_cover|page_title', 'any|any', 'title|8,10' )
				),
				array(
					'id'      		=> 'page_title_hide_breadcrumbs',
					'type'      	=> $free ? 'content' : 'switcher',
					'content' 		=> Codevz_Plus::pro_badge(),
					'title'   		=> esc_html__( 'Hide Breadcrumbs', 'codevz-plus' ),
					'help'   		=> esc_html__( 'Hide breadcrumbs if they are fewer than 3 levels', 'codevz-plus' ),
					'dependency'  	=> array( 'page_cover|page_title', 'any|any', 'title|4,5,6,7,8,9' )
				),
				array(
					'id'      		=> 'page_title_hide_current_breadcrumbs',
					'type'      	=> $free ? 'content' : 'switcher',
					'content' 		=> Codevz_Plus::pro_badge(),
					'title'   		=> esc_html__( 'Hide current item', 'codevz-plus' ),
					'help'   		=> esc_html__( 'This option only works on single posts and products', 'codevz-plus' ),
					'dependency'  	=> array( 'page_cover|page_title', 'any|any', 'title|4,5,6,7,8,9' )
				),
				array(
					'id'      		=> 'page_title_center',
					'type'      	=> $free ? 'content' : 'switcher',
					'content' 		=> Codevz_Plus::pro_badge(),
					'title'   		=> esc_html__( 'Center Mode', 'codevz-plus' ),
					'dependency'  	=> array( 'page_cover|page_title', 'any|any', 'title|3,4,5,7,8,9' )
				),
				array(
					'id' 			=> 'breadcrumbs_home_type',
					'type' 			=> $free ? 'content' : 'select',
					'title' 		=> esc_html__( 'Home type', 'codevz-plus' ),
					'options' 		=> array(
						'' 			=> esc_html__( 'Icon', 'codevz-plus' ),
						'title' 	=> esc_html__( 'Title', 'codevz-plus' ),
					),
					'dependency' 	=> array( 'page_cover|page_title', '==|any', 'title|4,5,6,7,8,9,10' )
				),
				array(
					'id'    		=> 'breadcrumbs_home_icon',
					'type'      	=> $free ? 'content' : 'icon',
					'content' 		=> Codevz_Plus::pro_badge(),
					'title' 		=> esc_html__( 'Icon', 'codevz-plus' ),
					'dependency' 	=> array( 'page_cover|page_title|breadcrumbs_home_type', '==|any|!=', 'title|4,5,6,7,8,9,10|title' ),
					'setting_args' 	=> [ 'transport' => 'postMessage' ]
				),
				array(
					'id'    		=> 'breadcrumbs_home_title',
					'type'      	=> $free ? 'content' : 'text',
					'content' 		=> Codevz_Plus::pro_badge(),
					'title' 		=> esc_html__( 'Title', 'codevz-plus' ),
					'dependency' 	=> array( 'page_cover|page_title|breadcrumbs_home_type', '==|any|==', 'title|4,5,6,7,8,9,10|title' )
				),
				array(
					'id'    		=> 'breadcrumbs_separator',
					'type'      	=> $free ? 'content' : 'icon',
					'content' 		=> Codevz_Plus::pro_badge(),
					'title' 		=> esc_html__( 'Delimiter', 'codevz-plus' ),
					'dependency' 	=> array( 'page_cover|page_title', '==|any', 'title|4,5,6,7,8,9,10' ),
					'setting_args' 	=> [ 'transport' => 'postMessage' ]
				),
				array(
					'type'    		=> 'notice',
					'class'   		=> 'info xtra-notice',
					'content' 		=> '<i class="fas fa-brush mr8"></i>' . esc_html__( 'Styles', 'codevz-plus' ),
					'dependency' 	=> array( 'page_cover', '==', 'title' )
				),
				array(
					'id' 			=> '_css_page_title',
					'type' 			=> 'cz_sk',
					'title' 		=> esc_html__( 'Container', 'codevz-plus' ),
					'button' 		=> esc_html__( 'Container', 'codevz-plus' ),
					'setting_args' 	=> [ 'transport' => 'postMessage' ],
					'settings' 		=> array( 'background', 'border' ),
					'selector' 		=> $c . '.page_title,' . $c . '.header_onthe_cover .page_title',
					'dependency' 	=> array( 'page_cover|page_title', '==|any', 'title|2,3,4,5,6,7,8,9' )
				),
				array(
					'id' 			=> '_css_page_title_tablet',
					'type' 			=> 'cz_sk_hidden',
					'setting_args' 	=> [ 'transport' => 'postMessage' ],
					'selector' 		=> $c . '.page_title,' . $c . '.header_onthe_cover .page_title'
				),
				array(
					'id' 			=> '_css_page_title_mobile',
					'type' 			=> 'cz_sk_hidden',
					'setting_args' 	=> [ 'transport' => 'postMessage' ],
					'selector' 		=> $c . '.page_title,' . $c . '.header_onthe_cover .page_title'
				),
				array(
					'id' 			=> '_css_page_title_inner_row',
					'type' 			=> 'cz_sk',
					'title' 		=> esc_html__( 'Inner Row', 'codevz-plus' ),
					'button' 		=> esc_html__( 'Inner Row', 'codevz-plus' ),
					'setting_args' 	=> [ 'transport' => 'postMessage' ],
					'settings' 		=> array( 'background', 'width' ),
					'selector' 		=> $c . '.page_title .row',
					'dependency' 	=> array( 'page_cover|page_title', '==|any', 'title|3,4,5,6,7,8,9' )
				),
				array(
					'id' 			=> '_css_page_title_inner_row_tablet',
					'type' 			=> 'cz_sk_hidden',
					'setting_args' 	=> [ 'transport' => 'postMessage' ],
					'selector' 		=> $c . '.page_title .row',
				),
				array(
					'id' 			=> '_css_page_title_inner_row_mobile',
					'type' 			=> 'cz_sk_hidden',
					'setting_args' 	=> [ 'transport' => 'postMessage' ],
					'selector' 		=> $c . '.page_title .row',
				),
				array(
					'id' 			=> '_css_page_title_color',
					'type' 			=> 'cz_sk',
					'title' 		=> esc_html__( 'Title', 'codevz-plus' ),
					'button' 		=> esc_html__( 'Title', 'codevz-plus' ),
					'setting_args' 	=> [ 'transport' => 'postMessage' ],
					'settings' 		=> array( 'color' ),
					'selector' 		=> $c . '.page_title .codevz-section-title',
					'dependency' 	=> array( 'page_cover|page_title', '==|any', 'title|3,4,5,6' )
				),
				array(
					'id' 			=> '_css_page_title_color_tablet',
					'type' 			=> 'cz_sk_hidden',
					'setting_args' 	=> [ 'transport' => 'postMessage' ],
					'selector' 		=> $c . '.page_title .codevz-section-title',
				),
				array(
					'id' 			=> '_css_page_title_color_mobile',
					'type' 			=> 'cz_sk_hidden',
					'setting_args' 	=> [ 'transport' => 'postMessage' ],
					'selector' 		=> $c . '.page_title .codevz-section-title',
				),
				array(
					'id' 			=> '_css_inner_title',
					'type' 			=> 'cz_sk',
					'title' 		=> esc_html__( 'Inner Title', 'codevz-plus' ),
					'button' 		=> esc_html__( 'Inner Title', 'codevz-plus' ),
					'setting_args' 	=> [ 'transport' => 'postMessage' ],
					'settings' 		=> array( 'color' ),
					'selector' 		=> $c . ' .content .xtra-post-title, ' . $c . ' .content .codevz-section-title',
					'dependency' 	=> array( 'page_cover|page_title', '==|any', 'title|2,8' )
				),
				array(
					'id' 			=> '_css_inner_title_tablet',
					'type' 			=> 'cz_sk_hidden',
					'setting_args' 	=> [ 'transport' => 'postMessage' ],
					'selector' 		=> $c . ' .content .xtra-post-title, ' . $c . ' .content .codevz-section-title'
				),
				array(
					'id' 			=> '_css_inner_title_mobile',
					'type' 			=> 'cz_sk_hidden',
					'setting_args' 	=> [ 'transport' => 'postMessage' ],
					'selector' 		=> $c . ' .content .xtra-post-title, ' . $c . ' .content .codevz-section-title'
				),
				array(
					'id' 			=> '_css_breadcrumbs_container',
					'type' 			=> 'cz_sk',
					'title' 		=> esc_html__( 'BR Container', 'codevz-plus' ),
					'button' 		=> esc_html__( 'BR Container', 'codevz-plus' ),
					'setting_args' 	=> [ 'transport' => 'postMessage' ],
					'settings' 		=> array( 'background', 'width' ),
					'selector' 		=> $c . '.breadcrumbs_container',
					'dependency' 	=> array( 'page_cover|page_title', '==|any', 'title|4,5,6,7,8,9,10' )
				),
				array(
					'id' 			=> '_css_breadcrumbs_container_tablet',
					'type' 			=> 'cz_sk_hidden',
					'setting_args' 	=> [ 'transport' => 'postMessage' ],
					'selector' 		=> $c . '.breadcrumbs_container',
				),
				array(
					'id' 			=> '_css_breadcrumbs_container_mobile',
					'type' 			=> 'cz_sk_hidden',
					'setting_args' 	=> [ 'transport' => 'postMessage' ],
					'selector' 		=> $c . '.breadcrumbs_container',
				),
				array(
					'id' 			=> '_css_breadcrumbs_inner_container',
					'type' 			=> 'cz_sk',
					'title' 		=> esc_html__( 'BR Inner Row', 'codevz-plus' ),
					'button' 		=> esc_html__( 'BR Inner Row', 'codevz-plus' ),
					'setting_args' 	=> [ 'transport' => 'postMessage' ],
					'settings' 		=> array( 'background', 'width' ),
					'selector' 		=> $c . '.breadcrumbs',
					'dependency' 	=> array( 'page_cover|page_title', '==|any', 'title|4,5,6,7,8,9,10' )
				),
				array(
					'id' 			=> '_css_breadcrumbs_inner_container_tablet',
					'type' 			=> 'cz_sk_hidden',
					'setting_args' 	=> [ 'transport' => 'postMessage' ],
					'selector' 		=> $c . '.breadcrumbs',
				),
				array(
					'id' 			=> '_css_breadcrumbs_inner_container_mobile',
					'type' 			=> 'cz_sk_hidden',
					'setting_args' 	=> [ 'transport' => 'postMessage' ],
					'selector' 		=> $c . '.breadcrumbs',
				),
				array(
					'id' 			=> '_css_page_title_breadcrumbs_color',
					'hover_id' 		=> '_css_page_title_breadcrumbs_color_hover',
					'type' 			=> 'cz_sk',
					'title' 		=> esc_html__( 'Breadcrumbs', 'codevz-plus' ),
					'button' 		=> esc_html__( 'Breadcrumbs', 'codevz-plus' ),
					'setting_args' 	=> [ 'transport' => 'postMessage' ],
					'settings' 		=> array( 'color' ),
					'selector' 		=> $c . '.breadcrumbs a,' . $c . '.breadcrumbs i',
					'dependency' 	=> array( 'page_cover|page_title', '==|any', 'title|4,5,6,7,8,9,10' )
				),
				array(
					'id' 			=> '_css_page_title_breadcrumbs_color_tablet',
					'type' 			=> 'cz_sk_hidden',
					'setting_args' 	=> [ 'transport' => 'postMessage' ],
					'selector' 		=> $c . '.breadcrumbs a,' . $c . '.breadcrumbs i',
				),
				array(
					'id' 			=> '_css_page_title_breadcrumbs_color_mobile',
					'type' 			=> 'cz_sk_hidden',
					'setting_args' 	=> [ 'transport' => 'postMessage' ],
					'selector' 		=> $c . '.breadcrumbs a,' . $c . '.breadcrumbs i',
				),
				array(
					'id' 			=> '_css_page_title_breadcrumbs_color_hover',
					'type' 			=> 'cz_sk_hidden',
					'setting_args' 	=> [ 'transport' => 'postMessage' ],
					'selector' 		=> $c . '.breadcrumbs a:hover',
				),

				array(
					'type'    		=> 'notice',
					'class'   		=> 'info xtra-notice mt30',
					'content' 		=> '<i class="far fa-snowflake mr8"></i>' . esc_html__( 'Particles', 'codevz-plus' ),
					'dependency'  	=> array( 'page_cover|page_title', 'any|any', 'title|3,4,5,6,7,9' )
				),
				array(
					'id'      		=> 'page_title_particles',
					'type'      	=> $free ? 'content' : 'switcher',
					'content' 		=> Codevz_Plus::pro_badge(),
					'title'   		=> esc_html__( 'Particles?', 'codevz-plus' ),
					'dependency'  	=> array( 'page_cover|page_title', 'any|any', 'title|3,4,5,6,7,9' )
				),
				array(
					'id'      		=> 'page_title_particles_visible',
					'type'      	=> $free ? 'content' : 'switcher',
					'content' 		=> Codevz_Plus::pro_badge(),
					'title'   		=> esc_html__( 'Overflow visible?', 'codevz-plus' ),
					'dependency'  	=> array( 'page_title_particles|page_cover|page_title', '!=|any|any', '|title|3,4,5,6,7,9' ),
				),
				array(
					'id'        	=> 'page_title_particles_min_height',
					'type'      	=> 'slider',
					'title'     	=> esc_html__( 'Minimum Height', 'codevz-plus' ),
					'options' 		=> array( 'unit' => 'px', 'step' => 1, 'min' => 300, 'max' => 1000 ),
					'dependency'  	=> array( 'page_title_particles|page_cover|page_title', '!=|any|any', '|title|3,4,5,6,7,9' ),
				),
				array(
					'id' 			=> 'page_title_particles_shape_type',
					'type' 			=> 'select',
					'title' 		=> esc_html__( 'Shapes', 'codevz-plus' ),
					'options' 		=> array(
						'' 				=> esc_html__( '~ Default ~', 'codevz-plus' ),
						'circle' 		=> esc_html__( 'Circle', 'codevz-plus' ),
						'edge' 			=> esc_html__( 'Edge', 'codevz-plus' ),
						'triangle' 		=> esc_html__( 'Triangle', 'codevz-plus' ),
						'polygon' 		=> esc_html__( 'Polygon', 'codevz-plus' ),
						'star' 			=> esc_html__( 'Star', 'codevz-plus' )
					),
					'dependency'  	=> array( 'page_title_particles|page_cover|page_title', '!=|any|any', '|title|3,4,5,6,7,9' ),
				),
				array(
					'id'        	=> 'page_title_particles_shapes_color',
					'type'      	=> 'color_picker',
					'title'    		=> esc_html__( 'Shapes color', 'codevz-plus' ),
					'dependency'  	=> array( 'page_title_particles|page_cover|page_title', '!=|any|any', '|title|3,4,5,6,7,9' ),
				),
				array(
					'id'        => 'page_title_particles_shapes_number',
					'type'      => 'slider',
					'title'     => esc_html__( 'Number of shapes', 'codevz-plus' ),
					'options' 	=> array( 'unit' => '', 'step' => 10, 'min' => 10, 'max' => 200 ),
					'dependency'  	=> array( 'page_title_particles|page_cover|page_title', '!=|any|any', '|title|3,4,5,6,7,9' ),
				),
				array(
					'id'        => 'page_title_particles_shapes_size',
					'type'      => 'slider',
					'title'     => esc_html__( 'Shapes size', 'codevz-plus' ),
					'options' 	=> array( 'unit' => '', 'step' => 5, 'min' => 5, 'max' => 200 ),
					'dependency'  	=> array( 'page_title_particles|page_cover|page_title', '!=|any|any', '|title|3,4,5,6,7,9' ),
				),
				array(
					'id'        => 'page_title_particles_lines_distance',
					'type'      => 'slider',
					'title'     => esc_html__( 'Lines Distance', 'codevz-plus' ),
					'options' 	=> array( 'unit' => '', 'step' => 10, 'min' => 100, 'max' => 700 ),
					'dependency'  	=> array( 'page_title_particles|page_cover|page_title', '!=|any|any', '|title|3,4,5,6,7,9' ),
				),
				array(
					'id'        	=> 'page_title_particles_lines_color',
					'type'      	=> 'color_picker',
					'title'    		=> esc_html__( 'Lines color', 'codevz-plus' ),
					'dependency'  	=> array( 'page_title_particles|page_cover|page_title', '!=|any|any', '|title|3,4,5,6,7,9' ),
				),
				array(
					'id' 			=> 'page_title_particles_move_direction',
					'type' 			=> 'select',
					'title' 		=> esc_html__( 'Move Direction', 'codevz-plus' ),
					'options' 		=> array(
						'' 				=> esc_html__( '~ Default ~', 'codevz-plus' ),
						'top' 			=> esc_html__( 'top', 'codevz-plus' ),
						'right' 		=> esc_html__( 'Right', 'codevz-plus' ),
						'bottom' 		=> esc_html__( 'Bottom', 'codevz-plus' ),
						'left' 			=> esc_html__( 'Left', 'codevz-plus' )
					),
					'dependency'  	=> array( 'page_title_particles|page_cover|page_title', '!=|any|any', '|title|3,4,5,6,7,9' )
				),
				array(
					'id'        => 'page_title_particles_move_speed',
					'type'      => 'slider',
					'title'     => esc_html__( 'Move Speed', 'codevz-plus' ),
					'options' 	=> array( 'unit' => '', 'step' => 1, 'min' => 1, 'max' => 50 ),
					'dependency'  	=> array( 'page_title_particles|page_cover|page_title', '!=|any|any', '|title|3,4,5,6,7,9' ),
				),
				array(
					'id' 			=> 'page_title_particles_move_out_mode',
					'type' 			=> 'select',
					'title' 		=> esc_html__( 'Move Out Mode', 'codevz-plus' ),
					'options' 		=> array(
						'' 				=> esc_html__( '~ Default ~', 'codevz-plus' ),
						'out' 			=> esc_html__( 'Out', 'codevz-plus' ),
						'bounce' 		=> esc_html__( 'Bounce', 'codevz-plus' )
					),
					'dependency'  	=> array( 'page_title_particles|page_cover|page_title', '!=|any|any', '|title|3,4,5,6,7,9' )
				),
				array(
					'id' 			=> 'page_title_particles_on_hover',
					'type' 			=> 'select',
					'title' 		=> esc_html__( 'On hover', 'codevz-plus' ),
					'options' 		=> array(
						'' 				=> esc_html__( '~ Default ~', 'codevz-plus' ),
						'grab' 			=> esc_html__( 'Grab', 'codevz-plus' ),
						'bubble' 		=> esc_html__( 'Bubble', 'codevz-plus' ),
						'repulse' 		=> esc_html__( 'Repulse', 'codevz-plus' )
					),
					'dependency'  	=> array( 'page_title_particles|page_cover|page_title', '!=|any|any', '|title|3,4,5,6,7,9' )
				),
				array(
					'id' 			=> 'page_title_particles_on_click',
					'type' 			=> 'select',
					'title' 		=> esc_html__( 'On Click', 'codevz-plus' ),
					'options' 		=> array(
						'' 				=> esc_html__( '~ Default ~', 'codevz-plus' ),
						'push' 			=> esc_html__( 'Push', 'codevz-plus' ),
						'remove' 		=> esc_html__( 'Remove', 'codevz-plus' ),
						'bubble' 		=> esc_html__( 'Bubble', 'codevz-plus' ),
						'repulse' 		=> esc_html__( 'Repulse', 'codevz-plus' )
					),
					'dependency'  	=> array( 'page_title_particles|page_cover|page_title', '!=|any|any', '|title|3,4,5,6,7,9' )
				),

			);
		}
	}

	/**
	 *
	 * Customize page options
	 * 
	 * @return array
	 *
	 */
	public static function options( $all = false ) {

		$options = [];

		$free = Codevz_Plus::$is_free;

		// Custom SK options.
		$custom_stylekits = [];

		$custom_stylekits[] = array(
			'type'    => 'notice',
			'class'   => 'info xtra-notice',
			'content' => '<i class="fas fa-plug-circle-plus mr8"></i>' . esc_html__( 'Custom StyleKits', 'codevz-plus' )
		);

		$custom_stylekits[] = array(
			'id' 			=> 'custom_stylekits',
			'type' 			=> $free ? 'content' : 'group',
			'title' 		=> esc_html__( 'Add Custom StyleKit', 'codevz-plus' ),
			'help' 			=> esc_html__( 'You can add custom StyleKit for any CSS selectors', 'codevz-plus' ),
			'desc' 			=> $free ? '' : esc_html__( 'Save and refresh is required', 'codevz-plus' ),
			'content' 		=> Codevz_Plus::pro_badge(),
			'button_title' 	=> esc_html__( 'Add', 'codevz-plus' ),
			'fields' 		=> [
				[
					'id'          => 'title',
					'type'        => 'text',
					'title'       => esc_html__( 'Title', 'codevz-plus' ),
					'setting_args'=> [ 'transport' => 'postMessage' ],
				],
				[
					'id'          => 'selector',
					'type'        => 'text',
					'title'       => esc_html__( 'Selector', 'codevz-plus' ),
					'setting_args'=> [ 'transport' => 'postMessage' ],
					'attributes'  => [ 'placeholder' => '.my-class' ],
				],
				[
					'id'          => 'hover',
					'type'        => 'text',
					'title'       => esc_html__( 'Hover', 'codevz-plus' ),
					'setting_args'=> [ 'transport' => 'postMessage' ],
					'attributes'  => [ 'placeholder' => '.my-class:hover' ],
				],
			],
			'setting_args' 	=> [ 'transport' => 'postMessage' ]
		);

		$custom_stylekits_arr = Codevz_Plus::option( 'custom_stylekits', [] );

		foreach( $custom_stylekits_arr as $sk ) {

			if ( isset( $sk[ 'title' ] ) ) {

				$id = sanitize_title_with_dashes( $sk[ 'selector' ] );

				$custom_stylekits[] = array(
					'id' 			=> '_css_custom_sk_' . $id,
					'hover_id' 		=> '_css_custom_sk_' . $id . '_hover',
					'type' 			=> 'cz_sk',
					'title' 		=> $sk[ 'title' ],
					'button' 		=> $sk[ 'title' ],
					'setting_args' 	=> [ 'transport' => 'postMessage' ],
					'settings' 		=> array( 'background', 'border' ),
					'selector' 		=> $sk[ 'selector' ]
				);
				$custom_stylekits[] = array(
					'id' 			=> '_css_custom_sk_' . $id . '_tablet',
					'type' 			=> 'cz_sk_hidden',
					'setting_args' 	=> [ 'transport' => 'postMessage' ],
					'selector' 		=> $sk[ 'selector' ]
				);
				$custom_stylekits[] = array(
					'id' 			=> '_css_custom_sk_' . $id . '_mobile',
					'type' 			=> 'cz_sk_hidden',
					'setting_args' 	=> [ 'transport' => 'postMessage' ],
					'selector' 		=> $sk[ 'selector' ]
				);
				$custom_stylekits[] = array(
					'id' 			=> '_css_custom_sk_' . $id . '_hover',
					'type' 			=> 'cz_sk_hidden',
					'setting_args' 	=> [ 'transport' => 'postMessage' ],
					'selector' 		=> $sk[ 'hover' ]
				);

			}

		}

		// Image sizes.
		$image_sizes = get_intermediate_image_sizes();
		$image_sizes = array_combine( $image_sizes, $image_sizes );
		$image_sizes = array_merge(
			[ '' => esc_html__( '~ Default ~', 'codevz-plus' ) ],
			$image_sizes
		);

		// General Options.
		$options[ 'general' ]   = array(
			'name' 		=> 'general',
			'title' 	=> esc_html__( 'General', 'codevz-plus' ),
			'sections' => array(

				array(
					'name'   => 'layout',
					'title'  => esc_html__( 'Layout', 'codevz-plus' ),
					'fields' => array(
						array(
							'id' 			=> 'layout',
							'type' 			=> 'codevz_image_select',
							'title' 		=> esc_html__( 'Sidebar', 'codevz-plus' ),
							'help'  		=> esc_html__( 'This option applies to all internal pages of your website as default.', 'codevz-plus' ),
							'options' 		=> [
								'none' 			=> [ esc_html__( 'No Sidebar', 'codevz-plus' ) 			, Codevz_Plus::$url . 'assets/img/off.png' ],
								'bpnp' 			=> [ esc_html__( 'Fullwidth', 'codevz-plus' ) 			, Codevz_Plus::$url . 'assets/img/sidebar-2.png' ],
								'center'		=> [ esc_html__( 'Center Mode', 'codevz-plus' ) 			, Codevz_Plus::$url . 'assets/img/sidebar-13.png' ],
								'right' 		=> [ esc_html__( 'Right Sidebar', 'codevz-plus' ) 		, Codevz_Plus::$url . 'assets/img/sidebar-3.png' ],
								'right-s' 		=> [ esc_html__( 'Right Sidebar Small', 'codevz-plus' ) 	, Codevz_Plus::$url . 'assets/img/sidebar-4.png' ],
								'left' 			=> [ esc_html__( 'Left Sidebar', 'codevz-plus' ) 		, Codevz_Plus::$url . 'assets/img/sidebar-5.png' ],
								'left-s' 		=> [ esc_html__( 'Left Sidebar Small', 'codevz-plus' ) 	, Codevz_Plus::$url . 'assets/img/sidebar-6.png' ],
								'both-side' 	=> [ esc_html__( 'Both Sidebar', 'codevz-plus' ) . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ) 		, Codevz_Plus::$url . 'assets/img/sidebar-7.png' ],
								'both-side2' 	=> [ esc_html__( 'Both Sidebar Small', 'codevz-plus' ) . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ) 	, Codevz_Plus::$url . 'assets/img/sidebar-8.png' ],
								'both-right' 	=> [ esc_html__( 'Both Sidebar Right', 'codevz-plus' ) . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ) 	, Codevz_Plus::$url . 'assets/img/sidebar-9.png' ],
								'both-right2' 	=> [ esc_html__( 'Both Sidebar Right', 'codevz-plus' ) . ' 2' . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ) , Codevz_Plus::$url . 'assets/img/sidebar-10.png' ],
								'both-left' 	=> [ esc_html__( 'Both Sidebar Left', 'codevz-plus' ) . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ) 	, Codevz_Plus::$url . 'assets/img/sidebar-11.png' ],
								'both-left2' 	=> [ esc_html__( 'Both Sidebar Left', 'codevz-plus' ) . ' 2' . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' )  , Codevz_Plus::$url . 'assets/img/sidebar-12.png' ],
							],
							'default' 		=> 'none',
							'attributes' 	=> [ 'data-depend-id' => 'layout' ]
						),
						array(
							'id' 			=> 'boxed',
							'type' 			=> 'codevz_image_select',
							'title' 		=> esc_html__( 'Layout', 'codevz-plus' ),
							'help' 			=> esc_html__( 'This option applies to overal website layout.', 'codevz-plus' ),
							'options' 		=> [
								'' 				=> [ esc_html__( 'Fullwidth', 'codevz-plus' ) 			, Codevz_Plus::$url . 'assets/img/layout-1.png' ],
								'1'				=> [ esc_html__( 'Boxed', 'codevz-plus' ) 				, Codevz_Plus::$url . 'assets/img/layout-2.png' ],
								'2'				=> [ esc_html__( 'Boxed Margin', 'codevz-plus' ) 		, Codevz_Plus::$url . 'assets/img/layout-3.png' ],
							],
							'setting_args'  => [ 'transport' => 'postMessage' ]
						),
						array(
							'id'        => 'site_width',
							'type'      => 'slider',
							'help' 		=> esc_html__( 'The site width is flexible, supporting units like px, %, and em', 'codevz-plus' ),
							'title'     => esc_html__( 'Site Width', 'codevz-plus' ),
							'options' 	=> array( 'unit' => 'px', 'step' => 10, 'min' => 1024, 'max' => 1400 ),
							'setting_args' 	  => [ 'transport' => 'postMessage' ]
						),
						array(
							'id'        => 'tablet_breakpoint',
							'type'      => 'slider',
							'title'     => esc_html__( 'Tablet breakpoint', 'codevz-plus' ),
							'options' 	=> array( 'unit' => 'px', 'step' => 1, 'min' => 481, 'max' => 1024 ),
							'dependency' 	=> [ 'disable_responsive', '==', '' ]
						),
						array(
							'id'        => 'mobile_breakpoint',
							'type'      => 'slider',
							'title'     => esc_html__( 'Mobile breakpoint', 'codevz-plus' ),
							'options' 	=> array( 'unit' => 'px', 'step' => 1, 'min' => 280, 'max' => 767 ),
							'dependency' 	=> [ 'disable_responsive', '==', '' ]
						),
						array(
							'id' 			=> 'disable_responsive',
							'type' 			=> $free ? 'content' : 'switcher',
							'title' 		=> esc_html__( 'Disable Responsive', 'codevz-plus' ),
							'help' 			=> esc_html__( 'To maintain the desktop layout across all smaller devices', 'codevz-plus' ),
							'content' 		=> Codevz_Plus::pro_badge()
						),
						array(
							'id' 			=> 'sticky',
							'type'          => $free ? 'content' : 'switcher',
							'title' 		=> esc_html__( 'Sticky Sidebar', 'codevz-plus' ),
							'help' 			=> esc_html__( "Sticky sidebar is a sidebar that remains fixed in place, ensuring it doesn't disappear when a user scrolls down the page", 'codevz-plus' ),
							'content' 		=> Codevz_Plus::pro_badge()
						),
						array(
							'id'            => 'rtl',
							'type'          => $free ? 'content' : 'switcher',
							'title'         => esc_html__( 'RTL Mode', 'codevz-plus' ),
							'help' 			=> esc_html__( 'Change website direction from right to left by loading RTL styles.', 'codevz-plus' ),
							'content' 		=> Codevz_Plus::pro_badge()
						),
					)
				),

				array(
					'name'   => 'styling',
					'title'  => esc_html__( 'Colors & Styling', 'codevz-plus' ),
					'fields' => wp_parse_args( $custom_stylekits, array(
						array(
							'id'        => 'site_color',
							'type'      => 'color_picker',
							'title'     => esc_html__( 'Accent Color', 'codevz-plus' ),
							'help'      => esc_html__( 'All primary website colors will replace.', 'codevz-plus' ),
							'setting_args' => [ 'transport' => 'postMessage' ]
						),
						array(
							'id'        	=> 'site_color_sec',
							'type'      	=> 'color_picker',
							'title'     	=> esc_html__( 'Secondary Color', 'codevz-plus' ),
							'help'      	=> esc_html__( 'All secondary website colors will replace.', 'codevz-plus' ) . ' ' . esc_html__( 'This color should be different from the accent color.', 'codevz-plus' ),
							'setting_args' 	=> [ 'transport' => 'postMessage' ]
						),
						array(
							'id' 			=> 'dark',
							'type' 			=> 'switcher',
							'title' 		=> esc_html__( 'Dark Mode', 'codevz-plus' ),
							'help' 			=> esc_html__( "Some sections feature dynamic colors, which may still appear in light mode. You'll need to locate and manually edit each setting", 'codevz-plus' )
						),
						array(
							'type' 			=> 'notice',
							'class' 		=> 'info',
							'content' 		=> '<i class="fas fa-brush mr8"></i>' . esc_html__( 'General styling', 'codevz-plus' )
						),
						array(
							'id' 			=> '_css_body',
							'type' 			=> 'cz_sk',
							'title' 		=> esc_html__( 'Body', 'codevz-plus' ),
							'button' 		=> esc_html__( 'Body', 'codevz-plus' ),
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'settings' 		=> array( 'background' ),
							'selector' 		=> 'html,body',
						),
						array(
							'id' 			=> '_css_body_mobile', 'type' => 'cz_sk_hidden', 'setting_args' => [ 'transport' => 'postMessage' ],
							'selector' 		=> 'html,body',
						),
						array(
							'id' 			=> '_css_body_tablet', 'type' => 'cz_sk_hidden', 'setting_args' => [ 'transport' => 'postMessage' ],
							'selector' 		=> 'html,body',
						),
						array(
							'id' 			=> '_css_layout_1',
							'type' 			=> 'cz_sk',
							'title' 		=> esc_html__( 'Boxed', 'codevz-plus' ),
							'button' 		=> esc_html__( 'Boxed', 'codevz-plus' ),
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'settings' 		=> array( 'background' ),
							'selector' 		=> '#layout'
						),
						array(
							'id' 			=> '_css_layout_1_mobile', 'type' => 'cz_sk_hidden', 'setting_args' => [ 'transport' => 'postMessage' ],
							'selector' 		=> 'html,body',
						),
						array(
							'id' 			=> '_css_layout_1_tablet', 'type' => 'cz_sk_hidden', 'setting_args' => [ 'transport' => 'postMessage' ],
							'selector' 		=> 'html,body',
						),
						array(
							'id' 			=> '_css_buttons',
							'hover_id' 		=> '_css_buttons_hover',
							'type' 			=> 'cz_sk',
							'title' 		=> esc_html__( 'Buttons', 'codevz-plus' ),
							'button' 		=> esc_html__( 'Buttons', 'codevz-plus' ),
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'settings' 		=> array( 'color', 'background', 'font-size', 'border' ),
							'selector' 		=> 'form button,.comment-form button,a.cz_btn,div.cz_btn,a.cz_btn_half_to_fill:before,a.cz_btn_half_to_fill_v:before,a.cz_btn_half_to_fill:after,a.cz_btn_half_to_fill_v:after,a.cz_btn_unroll_v:before, a.cz_btn_unroll_h:before,a.cz_btn_fill_up:before,a.cz_btn_fill_down:before,a.cz_btn_fill_left:before,a.cz_btn_fill_right:before,.wpcf7-submit,input[type=submit],input[type=button],.button,.cz_header_button,.woocommerce a.button,.woocommerce input.button,.woocommerce #respond input#submit.alt,.woocommerce a.button.alt,.woocommerce button.button.alt,.woocommerce input.button.alt,.woocommerce #respond input#submit, .woocommerce a.button, .woocommerce button.button, .woocommerce input.button, #edd-purchase-button, .edd-submit, [type=submit].edd-submit, .edd-submit.button.blue,.woocommerce #payment #place_order, .woocommerce-page #payment #place_order,.woocommerce button.button:disabled, .woocommerce button.button:disabled[disabled], .woocommerce a.button.wc-forward,.wp-block-search .wp-block-search__button,.woocommerce-message a.restore-item.button'
						),
						array(
							'id' 			=> '_css_buttons_mobile', 'type' => 'cz_sk_hidden', 'setting_args' => [ 'transport' => 'postMessage' ],
							'selector' 		=> 'form button,.comment-form button,a.cz_btn,div.cz_btn,a.cz_btn_half_to_fill:before,a.cz_btn_half_to_fill_v:before,a.cz_btn_half_to_fill:after,a.cz_btn_half_to_fill_v:after,a.cz_btn_unroll_v:before, a.cz_btn_unroll_h:before,a.cz_btn_fill_up:before,a.cz_btn_fill_down:before,a.cz_btn_fill_left:before,a.cz_btn_fill_right:before,.wpcf7-submit,input[type=submit],input[type=button],.button,.cz_header_button,.woocommerce a.button,.woocommerce input.button,.woocommerce #respond input#submit.alt,.woocommerce a.button.alt,.woocommerce button.button.alt,.woocommerce input.button.alt,.woocommerce #respond input#submit, .woocommerce a.button, .woocommerce button.button, .woocommerce input.button, #edd-purchase-button, .edd-submit, [type=submit].edd-submit, .edd-submit.button.blue,.woocommerce #payment #place_order, .woocommerce-page #payment #place_order,.woocommerce button.button:disabled, .woocommerce button.button:disabled[disabled], .woocommerce a.button.wc-forward,.wp-block-search .wp-block-search__button,.woocommerce-message a.restore-item.button'
						),
						array(
							'id' 			=> '_css_buttons_tablet', 'type' => 'cz_sk_hidden', 'setting_args' => [ 'transport' => 'postMessage' ],
							'selector' 		=> 'form button,.comment-form button,a.cz_btn,div.cz_btn,a.cz_btn_half_to_fill:before,a.cz_btn_half_to_fill_v:before,a.cz_btn_half_to_fill:after,a.cz_btn_half_to_fill_v:after,a.cz_btn_unroll_v:before, a.cz_btn_unroll_h:before,a.cz_btn_fill_up:before,a.cz_btn_fill_down:before,a.cz_btn_fill_left:before,a.cz_btn_fill_right:before,.wpcf7-submit,input[type=submit],input[type=button],.button,.cz_header_button,.woocommerce a.button,.woocommerce input.button,.woocommerce #respond input#submit.alt,.woocommerce a.button.alt,.woocommerce button.button.alt,.woocommerce input.button.alt,.woocommerce #respond input#submit, .woocommerce a.button, .woocommerce button.button, .woocommerce input.button, #edd-purchase-button, .edd-submit, [type=submit].edd-submit, .edd-submit.button.blue,.woocommerce #payment #place_order, .woocommerce-page #payment #place_order,.woocommerce button.button:disabled, .woocommerce button.button:disabled[disabled], .woocommerce a.button.wc-forward,.wp-block-search .wp-block-search__button,.woocommerce-message a.restore-item.button'
						),
						array(
							'id' 			=> '_css_buttons_hover', 'type' => 'cz_sk_hidden', 'setting_args' => [ 'transport' => 'postMessage' ],
							'selector' 		=> 'form button:hover,.comment-form button:hover,a.cz_btn:hover,div.cz_btn:hover,a.cz_btn_half_to_fill:hover:before, a.cz_btn_half_to_fill_v:hover:before,a.cz_btn_half_to_fill:hover:after, a.cz_btn_half_to_fill_v:hover:after,a.cz_btn_unroll_v:after, a.cz_btn_unroll_h:after,a.cz_btn_fill_up:after,a.cz_btn_fill_down:after,a.cz_btn_fill_left:after,a.cz_btn_fill_right:after,.wpcf7-submit:hover,input[type=submit]:hover,input[type=button]:hover,.button:hover,.cz_header_button:hover,.woocommerce a.button:hover,.woocommerce input.button:hover,.woocommerce #respond input#submit.alt:hover,.woocommerce a.button.alt:hover,.woocommerce button.button.alt:hover,.woocommerce input.button.alt:hover,.woocommerce #respond input#submit:hover, .woocommerce a.button:hover, .woocommerce button.button:hover, .woocommerce input.button:hover, #edd-purchase-button:hover, .edd-submit:hover, [type=submit].edd-submit:hover, .edd-submit.button.blue:hover, .edd-submit.button.blue:focus,.woocommerce #payment #place_order:hover, .woocommerce-page #payment #place_order:hover,.woocommerce div.product form.cart .button:hover,.woocommerce button.button:disabled:hover, .woocommerce button.button:disabled[disabled]:hover, .woocommerce a.button.wc-forward:hover,.wp-block-search .wp-block-search__button:hover,.woocommerce-message a.restore-item.button:hover'
						),
						array(
							'id' 			=> '_css_all_img_tags',
							'type' 			=> 'cz_sk',
							'title' 		=> esc_html__( 'Images', 'codevz-plus' ),
							'button' 		=> esc_html__( 'Images', 'codevz-plus' ),
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'settings' 		=> array( 'background', 'border' ),
							'selector'    	=> '.page_content img, a.cz_post_image img, footer img, .cz_image_in, .wp-block-gallery figcaption, .cz_grid .cz_grid_link'
						),
						array(
							'id' 			=> '_css_social_tooltip',
							'type' 			=> 'cz_sk',
							'title' 		=> esc_html__( 'Tooltips', 'codevz-plus' ),
							'button' 		=> esc_html__( 'Tooltips', 'codevz-plus' ),
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'settings' 		=> array( 'color', 'background', 'font-size', 'font-weight', 'letter-spacing', 'line-height', 'border' ),
							'selector' 		=> '[class*="cz_tooltip_"] [data-title]:after'
						),
						array(
							'id' 			=> '_css_input_textarea',
							'hover_id' 		=> '_css_input_textarea_focus',
							'type' 			=> 'cz_sk',
							'title' 		=> esc_html__( 'Inputs', 'codevz-plus' ),
							'button' 		=> esc_html__( 'Inputs', 'codevz-plus' ),
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'settings' 		=> array( 'color', 'background', 'border' ),
							'selector' 		=> 'input,textarea,select,.qty,.woocommerce-input-wrapper .select2-selection--single,#add_payment_method table.cart td.actions .coupon .input-text, .woocommerce-cart table.cart td.actions .coupon .input-text, .woocommerce-checkout table.cart td.actions .coupon .input-text,.woocommerce form .form-row .input-text, .woocommerce form .form-row select'
						),
						array(
							'id' 			=> '_css_input_textarea_tablet',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> 'input,textarea,select,.qty,.woocommerce-input-wrapper .select2-selection--single,#add_payment_method table.cart td.actions .coupon .input-text, .woocommerce-cart table.cart td.actions .coupon .input-text, .woocommerce-checkout table.cart td.actions .coupon .input-text,.woocommerce form .form-row .input-text, .woocommerce form .form-row select'
						),
						array(
							'id' 			=> '_css_input_textarea_mobile',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> 'input,textarea,select,.qty,.woocommerce-input-wrapper .select2-selection--single,#add_payment_method table.cart td.actions .coupon .input-text, .woocommerce-cart table.cart td.actions .coupon .input-text, .woocommerce-checkout table.cart td.actions .coupon .input-text,.woocommerce form .form-row .input-text, .woocommerce form .form-row select'
						),
						array(
							'id' 			=> '_css_input_textarea_focus',
							'type' 			=> 'cz_sk_hidden',
							'title' 		=> esc_html__( 'Focus', 'codevz-plus' ),
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> 'input:focus,textarea:focus,select:focus'
						),

						array(
							'type'    => 'notice',
							'class'   => 'info xtra-notice',
							'content' => '<i class="fas fa-brush mr8"></i>' . esc_html__( 'Sidebar & Widgets', 'codevz-plus' )
						),
						array(
							'id' 			=> '_css_sidebar_primary',
							'type' 			=> 'cz_sk',
							'title' 		=> esc_html__( 'Sidebar', 'codevz-plus' ),
							'button' 		=> esc_html__( 'Sidebar', 'codevz-plus' ),
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'settings' 		=> array( 'background', 'border' ),
							'selector' 		=> '.sidebar_inner'
						),
						array(
							'id' 			=> '_css_sidebar_primary_tablet',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> '.sidebar_inner'
						),
						array(
							'id' 			=> '_css_sidebar_primary_mobile',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> '.sidebar_inner'
						),
						array(
							'id' 			=> '_css_widgets',
							'type' 			=> 'cz_sk',
							'title' 		=> esc_html__( 'Widgets', 'codevz-plus' ),
							'button' 		=> esc_html__( 'Widgets', 'codevz-plus' ),
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'settings' 		=> array( 'color', 'background', 'border' ),
							'selector' 		=> '.widget'
						),
						array(
							'id' 			=> '_css_widgets_tablet',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> '.widget'
						),
						array(
							'id' 			=> '_css_widgets_mobile',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> '.widget'
						),
						array(
							'id' 			=> '_css_widgets_headline',
							'type' 			=> 'cz_sk',
							'title' 		=> esc_html__( 'Titles', 'codevz-plus' ),
							'button' 		=> esc_html__( 'Titles', 'codevz-plus' ),
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'settings' 		=> array( 'color', 'background', 'border' ),
							'selector' 		=> '.widget > .codevz-widget-title, .sidebar_inner .widget_block > div > div > h2'
						),
						array(
							'id' 			=> '_css_widgets_headline_tablet',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> '.widget > .codevz-widget-title, .sidebar_inner .widget_block > div > div > h2'
						),
						array(
							'id' 			=> '_css_widgets_headline_mobile',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> '.widget > .codevz-widget-title, .sidebar_inner .widget_block > div > div > h2'
						),
						array(
							'id' 			=> '_css_widgets_links',
							'hover_id' 		=> '_css_widgets_links_hover',
							'type' 			=> 'cz_sk',
							'title' 		=> esc_html__( 'Links', 'codevz-plus' ),
							'button' 		=> esc_html__( 'Links', 'codevz-plus' ),
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'settings' 		=> array( 'color' ),
							'selector' 		=> '.widget a'
						),
						array(
							'id' 			=> '_css_widgets_links_hover',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> '.widget a:hover'
						),
						array(
							'id' 			=> '_css_widgets_headline_before',
							'type'          => $free ? 'cz_sk_free' : 'cz_sk',
							'title' 		=> esc_html__( 'Title shape', 'codevz-plus' ) . ' 1',
							'button' 		=> esc_html__( 'Title shape', 'codevz-plus' ) . ' 1',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'settings' 		=> array( 'background', 'width', 'height', 'border', 'position', 'top', 'left', 'bottom', 'right' ),
							'selector' 		=> '.widget > .codevz-widget-title:before, .sidebar_inner .widget_block > div > div > h2:before'
						),
						array(
							'id' 			=> '_css_widgets_headline_before_tablet',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> '.widget > .codevz-widget-title:before, .sidebar_inner .widget_block > div > div > h2:before'
						),
						array(
							'id' 			=> '_css_widgets_headline_before_mobile',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> '.widget > .codevz-widget-title:before, .sidebar_inner .widget_block > div > div > h2:before'
						),
						array(
							'id' 			=> '_css_widgets_headline_after',
							'type'          => $free ? 'cz_sk_free' : 'cz_sk',
							'title' 		=> esc_html__( 'Title shape', 'codevz-plus' ) . ' 2',
							'button' 		=> esc_html__( 'Title shape', 'codevz-plus' ) . ' 2',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'settings' 		=> array( 'background', 'width', 'height', 'border', 'position', 'top', 'left', 'bottom', 'right' ),
							'selector' 		=> '.widget > .codevz-widget-title:after, .sidebar_inner .widget_block > div > div > h2:after'
						),
						array(
							'id' 			=> '_css_widgets_headline_after_tablet',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> '.widget > .codevz-widget-title:after, .sidebar_inner .widget_block > div > div > h2:after'
						),
						array(
							'id' 			=> '_css_widgets_headline_after_mobile',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> '.widget > .codevz-widget-title:after, .sidebar_inner .widget_block > div > div > h2:after'
						),
					) )
				),

				// Share
				array(
					'name'   => 'share',
					'title'  => esc_html__( 'Share Icons', 'codevz-plus' ),
					'fields' => array(

						array(
							'id' 		=> 'post_type',
							'type' 		=> 'checkbox',
							'title' 	=> esc_html__( 'Post type', 'codevz-plus' ),
							'help' 		=> esc_html__( 'In which post type would you like to display social share icons?', 'codevz-plus' ),
							'options' 	=> self::share_post_types()
						),

						array(
							'id' 		=> 'share',
							'type' 		=> 'checkbox',
							'title' 	=> esc_html__( 'Share icons', 'codevz-plus' ),
							'help' 		=> esc_html__( 'Which social share icons would you like to display?', 'codevz-plus' ),
							'options' 	=> array(
								'facebook'	=> esc_html__( 'Facebook', 'codevz-plus' ),
								'twitter'	=> esc_html__( 'X (Twitter)', 'codevz-plus' ),
								'pinterest'	=> esc_html__( 'Pinterest', 'codevz-plus' ),
								'reddit'	=> esc_html__( 'Reddit', 'codevz-plus' ),
								'delicious'	=> esc_html__( 'Delicious', 'codevz-plus' ),
								'linkedin'	=> esc_html__( 'Linkedin', 'codevz-plus' ),
								'whatsapp'	=> esc_html__( 'Whatsapp', 'codevz-plus' ),
								'telegram'	=> esc_html__( 'Telegram', 'codevz-plus' ),
								'envelope'	=> esc_html__( 'Email', 'codevz-plus' ),
								'print'		=> esc_html__( 'Print', 'codevz-plus' ),
								'copy'		=> esc_html__( 'Shortlink', 'codevz-plus' ),
							)
						),

						array(
							'id' 		=> 'share_box_title',
							'type' 		=> 'text',
							'title' 	=> esc_html__( 'Title', 'codevz-plus' ),
						),
						array(
							'id' 		=> 'share_color',
							'type' 		=> 'select',
							'title' 	=> esc_html__( 'Color mode', 'codevz-plus' ),
							'help' 		=> esc_html__( 'Original colors of social media icons', 'codevz-plus' ),
							'options' 	=> array(
								'cz_social_colored' 		=> esc_html__( 'Brand Colors', 'codevz-plus' ),
								'cz_social_colored_hover' 	=> esc_html__( 'Brand Colors on Hover', 'codevz-plus' ),
								'cz_social_colored_bg' 		=> esc_html__( 'Brand Background', 'codevz-plus' ),
								'cz_social_colored_bg_hover' => esc_html__( 'Brand Background on Hover', 'codevz-plus' ),
							),
							'default_option' => esc_html__( '~ Disable ~', 'codevz-plus' ),
						),

						array(
							'id' 		=> 'share_tooltip',
							'type' 		=> $free ? 'content' : 'switcher',
							'content' 	=> Codevz_Plus::pro_badge(),
							'title' 	=> esc_html__( 'Tooltip', 'codevz-plus' ),
							'help' 		=> esc_html__( 'StyleKit located in Theme Options > General > Colors & Styling', 'codevz-plus' )
						),

						array(
							'id' 		=> 'share_title',
							'type' 		=> $free ? 'content' : 'switcher',
							'content' 	=> Codevz_Plus::pro_badge(),
							'title' 	=> esc_html__( 'Inline title', 'codevz-plus' )
						),

						array(
							'type' 			=> 'notice',
							'class' 		=> 'info',
							'content' 		=> '<i class="fas fa-brush mr8"></i>' . esc_html__( 'Styling', 'codevz-plus' )
						),
						array(
							'id' 			=> '_css_share',
							'type' 			=> 'cz_sk',
							'title' 		=> esc_html__( 'Container', 'codevz-plus' ),
							'button' 		=> esc_html__( 'Container', 'codevz-plus' ),
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'settings' 		=> array( 'background', 'border' ),
							'selector' 		=> 'div.xtra-share'
						),
						array(
							'id' 			=> '_css_share_tablet',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> 'div.xtra-share'
						),
						array(
							'id' 			=> '_css_share_mobile',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> 'div.xtra-share'
						),

						array(
							'id' 			=> '_css_share_title',
							'type' 			=> 'cz_sk',
							'title' 		=> esc_html__( 'Title', 'codevz-plus' ),
							'button' 		=> esc_html__( 'Title', 'codevz-plus' ),
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'settings' 		=> array( 'color', 'background', 'border' ),
							'selector' 		=> 'div.xtra-share:before'
						),
						array(
							'id' 			=> '_css_share_title_tablet',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> 'div.xtra-share:before'
						),
						array(
							'id' 			=> '_css_share_title_mobile',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> 'div.xtra-share:before'
						),

						array(
							'id' 			=> '_css_share_a',
							'hover_id' 		=> '_css_share_a_hover',
							'type' 			=> 'cz_sk',
							'title' 		=> esc_html__( 'Icons', 'codevz-plus' ),
							'button' 		=> esc_html__( 'Icons', 'codevz-plus' ),
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'settings' 		=> array( 'color', 'background', 'border' ),
							'selector' 		=> 'div.xtra-share a'
						),
						array(
							'id' 			=> '_css_share_a_tablet',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> 'div.xtra-share a'
						),
						array(
							'id' 			=> '_css_share_a_mobile',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> 'div.xtra-share a'
						),
						array(
							'id' 			=> '_css_share_a_hover',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> 'div.xtra-share a:hover'
						),

						array(
							'id' 			=> '_css_share_inline_title',
							'hover_id' 		=> '_css_share_inline_title_hover',
							'type' 			=> 'cz_sk',
							'title' 		=> esc_html__( 'Inline title', 'codevz-plus' ),
							'button' 		=> esc_html__( 'Inline title', 'codevz-plus' ),
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'settings' 		=> array( 'color', 'background', 'border' ),
							'selector' 		=> 'div.xtra-share a span',
							'dependency' 	=> [ 'share_title', '!=', '' ]
						),
						array(
							'id' 			=> '_css_share_inline_title_tablet',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> 'div.xtra-share a span'
						),
						array(
							'id' 			=> '_css_share_inline_title_mobile',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> 'div.xtra-share a span'
						),
						array(
							'id' 			=> '_css_share_inline_title_hover',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> 'div.xtra-share a:hover span'
						),

					)
				),

				// SEO
				array(
					'name'   => 'general_seo',
					'title'  => esc_html__( 'SEO & Title tags', 'codevz-plus' ),
					'fields' => array(
						array(
							'id' 			=> 'page_title_tag',
							'type' 			=> $free ? 'content' : 'select',
							'content' 		=> Codevz_Plus::pro_badge(),
							'title' 		=> esc_html__( 'Pages title tag', 'codevz-plus' ),
							'help' 			=> esc_html__( 'Pages title tag in the Title and Breadcrumbs section, and above content.', 'codevz-plus' ),
							'options' 		=> array(
								'' 				=> 'H1',
								'h2' 			=> 'H2',
								'h3' 			=> 'H3',
								'h4' 			=> 'H4',
								'h5' 			=> 'H5',
								'h6' 			=> 'H6',
								'p' 			=> 'p',
								'div' 			=> 'div',
							),
						),
						array(
							'id' 			=> 'widgets_title_tag',
							'type' 			=> $free ? 'content' : 'select',
							'content' 		=> Codevz_Plus::pro_badge(),
							'title' 		=> esc_html__( 'Widgets title tag', 'codevz-plus' ),
							'help' 			=> esc_html__( "Applies to any pages where you've set a sidebar containing widgets with titles.", 'codevz-plus' ),
							'options' 		=> array(
								'h1' 			=> 'H1',
								'h2' 			=> 'H2',
								'h3' 			=> 'H3',
								'' 				=> 'H4',
								'h5' 			=> 'H5',
								'h6' 			=> 'H6',
								'p' 			=> 'p',
								'div' 			=> 'div',
							),
						),
						array(
							'id' 			  => 'seo_meta_tags',
							'type' 			  => 'switcher',
							'title' 		  => esc_html__( 'SEO meta tags', 'codevz-plus' ),
							'help' 			  => esc_html__( 'If you are not using any SEO plugin, So turn this option ON, This will automatically add meta tags to all pages according to page title, content and kewords.', 'codevz-plus' ),
							'setting_args' 	  => [ 'transport' => 'postMessage' ]
						),
						array(
							'id' 			  => 'seo_desc',
							'type' 			  => 'textarea',
							'title' 		  => esc_html__( 'Short description', 'codevz-plus' ),
							'setting_args' 	  => [ 'transport' => 'postMessage' ],
							'dependency' 	  => array( 'seo_meta_tags', '==', 'true' )
						),
						array(
							'id' 			  => 'seo_keywords',
							'type' 			  => 'textarea',
							'title' 		  => esc_html__( 'Keywords', 'codevz-plus' ),
							'help' 			  => esc_html__( 'Separate words with comma', 'codevz-plus' ),
							'setting_args' 	  => [ 'transport' => 'postMessage' ],
							'dependency' 	  => array( 'seo_meta_tags', '==', 'true' )
						),
					),
				),

				array(
					'name'   => 'loading',
					'title'  => esc_html__( 'Loading', 'codevz-plus' ),
					'fields' => array(
						array(
							'id'            => 'first_load_fade',
							'type'          => $free ? 'content' : 'switcher',
							'title'         => esc_html__( 'First load fade', 'codevz-plus' ),
							'content' 		=> Codevz_Plus::pro_badge()
						),
						array(
							'id'			=> 'pageloader',
							'type'          => $free ? 'content' : 'switcher',
							'content' 		=> Codevz_Plus::pro_badge(),
							'title'			=> esc_html__( 'Loading', 'codevz-plus' ),
							'help'			=> esc_html__( "After the page content loads, we'll smoothly animate the preloading screen out of view using a nice transition", 'codevz-plus' ),
						),
						array(
							'id' 			=> 'loading_out_fx',
							'type' 			=> $free ? 'content' : 'select',
							'title' 		=> esc_html__( 'Effect', 'codevz-plus' ),
							'help' 			=> esc_html__( 'Animate the preloading screen away from the viewport.', 'codevz-plus' ),
							'content' 		=> Codevz_Plus::pro_badge(),
							'options' 		=> array(
								''						=> esc_html__( 'Fade', 'codevz-plus' ),
								'pageloader_down'		=> esc_html__( 'Down', 'codevz-plus' ),
								'pageloader_up'			=> esc_html__( 'Up', 'codevz-plus' ),
								'pageloader_left'		=> esc_html__( 'Left', 'codevz-plus' ),
								'pageloader_right'		=> esc_html__( 'Right', 'codevz-plus' ),
								'pageloader_circle'		=> esc_html__( 'Circle', 'codevz-plus' ),
								'pageloader_center_h'	=> esc_html__( 'Center horizontal', 'codevz-plus' ),
								'pageloader_center_v'	=> esc_html__( 'Center vertical', 'codevz-plus' ),
								'pageloader_pa'			=> esc_html__( 'Polygon', 'codevz-plus' ) . ' 1',
								'pageloader_pb'			=> esc_html__( 'Polygon', 'codevz-plus' ) . ' 2',
								'pageloader_pc'			=> esc_html__( 'Polygon', 'codevz-plus' ) . ' 3',
								'pageloader_pd'			=> esc_html__( 'Polygon', 'codevz-plus' ) . ' 4',
								'pageloader_pe'			=> esc_html__( 'Polygon', 'codevz-plus' ) . ' 5',
							),
							'dependency'  	=> $free ? [] : array( 'pageloader', '==', true ),
						),
						array(
							'id'            => 'preloader_type',
							'type'          => $free ? 'content' : 'select',
							'title'         => esc_html__( 'Type', 'codevz-plus' ),
							'help' 			=> esc_html__( 'Choose between image, percentage and or custom HTML code.', 'codevz-plus' ),
							'content' 		=> Codevz_Plus::pro_badge(),
							'options'       => array(
								''				=> esc_html__( 'Image', 'codevz-plus' ),
								'percentage'	=> esc_html__( 'Percentage', 'codevz-plus' ),
								'custom'		=> esc_html__( 'Custom code', 'codevz-plus' ),
							),
							'dependency'  	=> $free ? [] : array( 'pageloader', '==', true ),
							'setting_args'  => [ 'transport' => 'postMessage' ]
						),
						array(
							'id'			=> 'pageloader_img',
							'type'			=> $free ? 'content' : 'upload',
							'title'			=> esc_html__('Image', 'codevz-plus' ),
							'content' 		=> Codevz_Plus::pro_badge(),
							'preview'       => 1,
							'dependency'  	=> $free ? [] : array( 'pageloader|preloader_type|preloader_type', '==|!=|!=', 'true|custom|percentage' ),
							'setting_args' 	=> [ 'transport' => 'postMessage' ]
						),
						array(
							'id'			=> 'pageloader_custom',
							'type'			=> $free ? 'content' : 'textarea',
							'title'			=> esc_html__('Custom code', 'codevz-plus' ),
							'help' 			=> esc_html__( 'Shortcode and custom HTML code allowed.', 'codevz-plus' ),
							'content' 		=> Codevz_Plus::pro_badge(),
							'preview'       => 1,
							'dependency'  	=> $free ? [] : array( 'pageloader|preloader_type', '==|==', 'true|custom' )
						),
						array(
							'type' 			=> 'notice',
							'class' 		=> 'info',
							'content' 		=> '<i class="fas fa-brush mr8"></i>' . esc_html__( 'Styling', 'codevz-plus' ),
							'dependency' 	=> $free ? [] : array( 'pageloader', '==', true )
						),
						array(
							'id' 			=> '_css_preloader',
							'type' 			=> $free ? 'cz_sk_free' : 'cz_sk',
							'title' 		=> esc_html__( 'Background', 'codevz-plus' ),
							'button' 		=> esc_html__( 'Background', 'codevz-plus' ),
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'settings' 		=> array( 'background' ),
							'selector' 		=> '.pageloader',
							'dependency' 	=> $free ? [] : array( 'pageloader', '==', true )
						),
						array(
							'id' 			=> '_css_preloader_percentage',
							'type' 			=> $free ? 'cz_sk_free' : 'cz_sk',
							'title' 		=> esc_html__( 'Loading', 'codevz-plus' ),
							'button' 		=> esc_html__( 'Loading', 'codevz-plus' ),
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'settings' 		=> array( 'background', 'border' ),
							'selector' 		=> '.pageloader > *',
							'dependency' 	=> $free ? [] : array( 'pageloader', '==', true )
						),
					),
				),

				array(
					'name'    => 'page_404',
					'title'   => esc_html__( 'Page 404', 'codevz-plus' ),
					'fields'  => array(
						array(
							'id'            => '404_title',
							'type'          => 'text',
							'title'         => esc_html__( 'Title', 'codevz-plus' ),
							'default'       => '404',
						),
						array(
							'id'            => '404_msg',
							'type'          => 'textarea',
							'title'         => esc_html__( 'Description', 'codevz-plus' ),
							'default'       => esc_html__( 'How did you get here?! It’s cool. We’ll help you out.', 'codevz-plus' ),
						),
						array(
							'id'            => '404_btn',
							'type'          => 'text',
							'title'         => esc_html__( 'Button', 'codevz-plus' ),
							'default'       => esc_html__( 'Back to homepage', 'codevz-plus' )
						),
						array(
							'type'    		=> 'notice',
							'class'   		=> 'info',
							'content' 		=> esc_html__( 'If you want to have custom page 404, Create a new page from Dashboard > Pages > Add New, set title to 404 and change slug to page-404 or not-found then save it as draft', 'codevz-plus' )
						),
					)
				),

				array(
					'name'   => 'custom_codes',
					'title'  => esc_html__( 'Custom Codes', 'codevz-plus' ),
					'fields' => array(
						array(
							'id'		=> 'css',
							'type' 		=> 'textarea',
							'title'		=> esc_html__('Custom CSS', 'codevz-plus' ),
							'help'		=> esc_html__('Insert codes without style tag', 'codevz-plus' ),
							'attributes' => array(
								'placeholder' => ".selector {font-size: 20px}",
			  					'style'       => "height: 180px;direction: ltr",
							),
							'setting_args' 	=> [ 'transport' => 'postMessage' ]
						),
						array(
							'id'		=> 'js',
							'type' 		=> $free ? 'content' : 'textarea',
							'content' 	=> Codevz_Plus::pro_badge(),
							'title'		=> esc_html__('Custom JS', 'codevz-plus' ),
							'help'		=> esc_html__('Insert codes without script tag', 'codevz-plus' ),
							'attributes' => array(
								'placeholder' => "jQuery('.selector').addClass('class');",
			  					'style'       => "height: 180px;direction: ltr",
							),
							'setting_args' 	=> [ 'transport' => 'postMessage' ]
						),
						array(
							'id'		=> 'head_codes',
							'type' 		=> $free ? 'content' : 'textarea',
							'content' 	=> Codevz_Plus::pro_badge(),
							'title'		=> esc_html__('Before closing &lt;/head&gt;', 'codevz-plus' ),
							'help'		=> esc_html__('Add your custom codes here such as google analytics.', 'codevz-plus' ),
							'attributes' => [ 'style' => "height: 180px;direction: ltr" ],
							'setting_args' 	=> [ 'transport' => 'postMessage' ]
						),
						array(
							'id'		=> 'foot_codes',
							'type' 		=> $free ? 'content' : 'textarea',
							'content' 	=> Codevz_Plus::pro_badge(),
							'title'		=> esc_html__('Before closing &lt;/body&gt;', 'codevz-plus' ),
							'attributes' => array(
							  'style'       => "height: 180px;direction: ltr",
							),
							'setting_args' 	=> [ 'transport' => 'postMessage' ]
						),
					),
				),

				array(
					'name'    => 'white_label',
					'title'   => esc_html__( 'White Label', 'codevz-plus' ),
					'fields'  => [
						[
							'id' 			=> 'disable',
							'type' 			=> $free ? 'content' : 'checkbox',
							'content' 		=> Codevz_Plus::pro_badge(),
							'title' 		=> esc_html__( 'Disable features', 'codevz-plus' ),
							'help' 			=> esc_html__( 'You can hide XTRA theme dashboard menus.', 'codevz-plus' ),
							'options' 		=> [
								'menu'			=> esc_html__( 'Hide XTRA menu', 'codevz-plus' ),
								'activation'	=> esc_html__( 'Hide Activation menu', 'codevz-plus' ),
								'videos'		=> esc_html__( 'Hide Elements videos', 'codevz-plus' ),
								'importer'		=> esc_html__( 'Hide Demo importer menu', 'codevz-plus' ),
								'options' 		=> esc_html__( 'Hide Theme options menu', 'codevz-plus' ),
								'docs'			=> esc_html__( 'Hide Documentation', 'codevz-plus' ),
								'youtube'		=> esc_html__( 'Hide Video Tutorials', 'codevz-plus' ),
								'changelog'		=> esc_html__( 'Hide Change Log', 'codevz-plus' ),
								'ticksy'		=> esc_html__( 'Hide Support', 'codevz-plus' ),
								'faq'			=> esc_html__( 'Hide F.A.Q', 'codevz-plus' ),
								'envato' 		=> esc_html__( 'Hide Dashboard Envato logo', 'codevz-plus' ),
								'templates'		=> esc_html__( 'Hide Page builder templates', 'codevz-plus' ),
							],
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
						],
						[
							'id' 			=> 'white_label_exclude_admin',
							'type' 			=> $free ? 'content' : 'switcher',
							'content' 		=> Codevz_Plus::pro_badge(),
							'title' 		=> esc_html__( 'Exclude admin', 'codevz-plus' ),
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
						],
						[
							'id' 			=> 'white_label_menu_icon',
							'type' 			=> $free ? 'content' : 'upload',
							'content' 		=> Codevz_Plus::pro_badge(),
							'title' 		=> esc_html__( 'Menu Icon', 'codevz-plus' ),
							'help' 			=> '20x20 PX',
							'preview' 		=> true,
							'setting_args'  => [ 'transport' => 'postMessage' ],
						],
						[
							'id' 			=> 'white_label_welcome_page_logo',
							'type' 			=> $free ? 'content' : 'upload',
							'content' 		=> Codevz_Plus::pro_badge(),
							'title' 		=> esc_html__( 'Welcome Page Logo', 'codevz-plus' ),
							'help' 			=> '90x90 PX',
							'preview' 		=> true,
							'setting_args'  => [ 'transport' => 'postMessage' ],
						],
						[
							'type' 			=> 'notice',
							'class' 		=> 'info',
							'content' 		=> esc_html__( 'Warning: If you change below options, your style.css for both parent and child theme will reset and override.', 'codevz-plus' )
						],
						[
							'id' 			=> 'white_label_theme_name',
							'type' 			=> $free ? 'content' : 'text',
							'content' 		=> Codevz_Plus::pro_badge(),
							'title' 		=> esc_html__( 'Theme Name', 'codevz-plus' ),
							'setting_args'  => [ 'transport' => 'postMessage' ]
						],
						[
							'id' 			=> 'white_label_theme_description',
							'type' 			=> $free ? 'content' : 'text',
							'content' 		=> Codevz_Plus::pro_badge(),
							'title' 		=> esc_html__( 'Description', 'codevz-plus' ),
							'setting_args'  => [ 'transport' => 'postMessage' ]
						],
						[
							'id' 			=> 'white_label_theme_screenshot',
							'type' 			=> $free ? 'content' : 'upload',
							'content' 		=> Codevz_Plus::pro_badge(),
							'title' 		=> esc_html__( 'Screenshot', 'codevz-plus' ),
							'preview' 		=> true,
							'help' 			=> '1200x900 PX',
							'setting_args'  => [ 'transport' => 'postMessage' ]
						],
						[
							'type' 			=> 'notice',
							'class' 		=> 'info',
							'content' 		=> '<i class="fas fa-plug-circle-plus mr8"></i>' . esc_html__( 'Core plugin details', 'codevz-plus' )
						],
						[
							'id' 			=> 'white_label_plugin_name',
							'type' 			=> $free ? 'content' : 'text',
							'content' 		=> Codevz_Plus::pro_badge(),
							'title' 		=> esc_html__( 'Name', 'codevz-plus' ),
							'setting_args'  => [ 'transport' => 'postMessage' ]
						],
						[
							'id' 			=> 'white_label_plugin_description',
							'type' 			=> $free ? 'content' : 'text',
							'content' 		=> Codevz_Plus::pro_badge(),
							'title' 		=> esc_html__( 'Description', 'codevz-plus' ),
							'setting_args'  => [ 'transport' => 'postMessage' ]
						],
						[
							'type' 			=> 'notice',
							'class' 		=> 'info',
							'content' 		=> '<i class="fas fa-user mr8"></i>' . esc_html__( 'Author and link', 'codevz-plus' )
						],
						[
							'id' 			=> 'white_label_author',
							'type' 			=> $free ? 'content' : 'text',
							'content' 		=> Codevz_Plus::pro_badge(),
							'title' 		=> esc_html__( 'Name', 'codevz-plus' ),
							'setting_args'  => [ 'transport' => 'postMessage' ]
						],
						[
							'id' 			=> 'white_label_link',
							'type' 			=> $free ? 'content' : 'text',
							'content' 		=> Codevz_Plus::pro_badge(),
							'title' 		=> esc_html__( 'Link', 'codevz-plus' ),
							'setting_args'  => [ 'transport' => 'postMessage' ]
						],
					]
				),

				array(
					'name'   => 'cookie',
					'title'  => esc_html__( 'Cookie Notice', 'codevz-plus' ),
					'fields' => array(
						array(
							'id'            => 'cookie',
							'type'          => $free ? 'content' : 'select',
							'title'         => esc_html__( 'Cookie Notice', 'codevz-plus' ),
							'help' 			=> esc_html__( 'A cookie notice is a banner that pops up as the first thing, when visitors arrive on your website and tell your visitor that the site is using cookies and then asks visitors to accept this.', 'codevz-plus' ),
							'content' 		=> Codevz_Plus::pro_badge(),
							'options'       => [
								'' 					=> esc_html__( '~ Disable ~', 'codevz-plus' ),
								'xtra-cookie-bl' 	=> esc_html__( 'Bottom left', 'codevz-plus' ),
								'xtra-cookie-br' 	=> esc_html__( 'Bottom right', 'codevz-plus' ),
								'xtra-cookie-tl' 	=> esc_html__( 'Top left', 'codevz-plus' ),
								'xtra-cookie-tr' 	=> esc_html__( 'Top right', 'codevz-plus' ),
							],
							'setting_args'  => [ 'transport' => 'postMessage' ]
						),
						array(
							'id'		=> 'cookie_content',
							'type'		=> $free ? 'content' : 'textarea',
							'content' 	=> Codevz_Plus::pro_badge(),
							'title'		=> esc_html__( 'Content', 'codevz-plus' ),
							'default' 	=> esc_html__( 'This website uses cookies to improve user experience.', 'codevz-plus' ),
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'dependency' 	=> $free ? [] : [ 'cookie', '!=', '' ]
						),
						array(
							'id'		=> 'cookie_button',
							'type'		=> $free ? 'content' : 'text',
							'content' 	=> Codevz_Plus::pro_badge(),
							'title'		=> esc_html__( 'Button', 'codevz-plus' ),
							'default'	=> esc_html__( 'Accept and close', 'codevz-plus' ),
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'dependency' 	=> $free ? [] : [ 'cookie', '!=', '' ]
						),
						array(
							'type' 			=> 'notice',
							'class' 		=> 'info',
							'content' 		=> '<i class="fas fa-brush mr8"></i>' . esc_html__( 'Styling', 'codevz-plus' ),
							'dependency' 	=> $free ? [] : [ 'cookie', '!=', '' ]
						),
						array(
							'id' 			=> '_css_cookie',
							'type' 			=> $free ? 'cz_sk_free' : 'cz_sk',
							'title' 		=> esc_html__( 'Container', 'codevz-plus' ),
							'button' 		=> esc_html__( 'Container', 'codevz-plus' ),
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'settings' 		=> array( 'background', 'border' ),
							'selector' 		=> 'div.xtra-cookie',
							'dependency' 	=> $free ? [] : [ 'cookie', '!=', '' ]
						),
						array(
							'id' 			=> '_css_cookie_tablet',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> 'div.xtra-cookie'
						),
						array(
							'id' 			=> '_css_cookie_mobile',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> 'div.xtra-cookie'
						),
						array(
							'id' 			=> '_css_cookie_button',
							'type' 			=> $free ? 'cz_sk_free' : 'cz_sk',
							'title' 		=> esc_html__( 'Button', 'codevz-plus' ),
							'button' 		=> esc_html__( 'Button', 'codevz-plus' ),
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'settings' 		=> array( 'background', 'border' ),
							'selector' 		=> '.xtra-cookie a.xtra-cookie-button',
							'dependency' 	=> $free ? [] : [ 'cookie', '!=', '' ]
						),
						array(
							'id' 			=> '_css_cookie_button_tablet',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> '.xtra-cookie a.xtra-cookie-button',
						),
						array(
							'id' 			=> '_css_cookie_button_mobile',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> '.xtra-cookie a.xtra-cookie-button',
						),
					),
				),

				array(
					'name'   => 'magic_mouse',
					'title'  => esc_html__( 'Magic Mouse', 'codevz-plus' ),
					'fields' => array(
						array(
							'id'            => 'magic_mouse',
							'type'          => $free ? 'content' : 'select',
							'title'         => esc_html__( 'Magic mouse', 'codevz-plus' ),
							'content' 		=> Codevz_Plus::pro_badge(),
							'options'       => [
								'' 				=> esc_html__( '~ Disable ~', 'codevz-plus' ),
								'1' 			=> esc_html__( 'Type', 'codevz-plus' ) . ' 1',
								'2' 			=> esc_html__( 'Type', 'codevz-plus' ) . ' 2',
								'3' 			=> esc_html__( 'Type', 'codevz-plus' ) . ' 3',
								'4' 			=> esc_html__( 'Type', 'codevz-plus' ) . ' 4',
								'5' 			=> esc_html__( 'Type', 'codevz-plus' ) . ' 5',
								'6' 			=> esc_html__( 'Type', 'codevz-plus' ) . ' 6',
								'7' 			=> esc_html__( 'Type', 'codevz-plus' ) . ' 7',
							]
						),
						array(
							'id'            => 'magic_mouse_hide_cursor',
							'type'          => $free ? 'content' : 'switcher',
							'title'         => esc_html__( 'Hide default cursor', 'codevz-plus' ),
							'content' 		=> Codevz_Plus::pro_badge(),
							'dependency' 	=> [ 'magic_mouse', '!=', '' ]
						),
						array(
							'id'            => 'magic_mouse_curzr_color_1',
							'type' 			=> $free ? 'content' : 'color_picker',
							'title'         => esc_html__( 'Color', 'codevz-plus' ) . ' 1',
							'content' 		=> Codevz_Plus::pro_badge(),
							'dependency' 	=> [ 'magic_mouse', 'any', '2,3,5,6,7' ]
						),
						array(
							'id'            => 'magic_mouse_curzr_color_2',
							'type' 			=> $free ? 'content' : 'color_picker',
							'title'         => esc_html__( 'Color', 'codevz-plus' ) . ' 2',
							'content' 		=> Codevz_Plus::pro_badge(),
							'dependency' 	=> [ 'magic_mouse', 'any', '2,3,5,6' ]
						),
						array(
							'id'            => 'magic_mouse_invert',
							'type'          => $free ? 'content' : 'switcher',
							'title'         => esc_html__( 'Invert color', 'codevz-plus' ),
							'content' 		=> Codevz_Plus::pro_badge(),
							'dependency' 	=> [ 'magic_mouse', '==', '1' ]
						),
						array(
							'id' 			=> 'magic_mouse_magnet',
							'type'          => $free ? 'content' : 'checkbox',
							'title' 		=> esc_html__( 'Magnet on', 'codevz-plus' ),
							'help' 			=> esc_html__( 'If you want enable magnet on any elements you can use custom class ".cz_magnet"', 'codevz-plus' ),
							'options' 		=> [
								'a,button,input[type=\'button\']'	=> esc_html__( 'All links', 'codevz-plus' ),
								'.logo a'						=> esc_html__( 'Logo', 'codevz-plus' ),
								'.cz_social a'					=> esc_html__( 'Social icons', 'codevz-plus' ),
								'.cz_btn'						=> esc_html__( 'Buttons', 'codevz-plus' ),
								'.cz_header_button'				=> esc_html__( 'Header buttons', 'codevz-plus' ),
								'.sf-menu a,.codevz-widget-custom-menu-horizontal a'		=> esc_html__( 'Menu items', 'codevz-plus' ),
								'.cz_elm > i'					=> esc_html__( 'Menu icon', 'codevz-plus' ),
								'.xtra-search-icon' 			=> esc_html__( 'Search icon', 'codevz-plus' ),
								'.shop_icon > i'				=> esc_html__( 'Shop cart icon', 'codevz-plus' ),
								'.wishlist_icon > i'			=> esc_html__( 'Wishlist icon', 'codevz-plus' ),
								'.compare_icon > i'				=> esc_html__( 'Compare icon', 'codevz-plus' ),
								'.backtotop'					=> esc_html__( 'Backtotop icon', 'codevz-plus' ),
								'i.fixed_contact'				=> esc_html__( 'Quick contact', 'codevz-plus' ),
								'.slick-arrow i'				=> esc_html__( 'Carousel arrows', 'codevz-plus' ),
								'.cz_magnet'					=> esc_html__( 'Custom class', 'codevz-plus' ),
							],
							'dependency' 	=> [ 'magic_mouse', '==', '1' ],
							'default' 		=> [ '.logo a', '.cz_elm > i' ]
						),
						array(
							'id' 			=> 'magic_mouse_inner_color',
							'type' 			=> $free ? 'content' : 'color_picker',
							'title' 		=> esc_html__( 'Inner circle', 'codevz-plus' ),
							'content' 		=> Codevz_Plus::pro_badge(),
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'settings' 		=> [ 'background', 'width', 'height', 'border' ],
							'dependency' 	=> [ 'magic_mouse', '==', '1' ]
						),
						array(
							'id' 			=> 'magic_mouse_outer_color',
							'type' 			=> $free ? 'content' : 'color_picker',
							'title' 		=> esc_html__( 'Outer circle', 'codevz-plus' ),
							'content' 		=> Codevz_Plus::pro_badge(),
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'settings' 		=> [ 'background', 'width', 'height', 'border' ],
							'dependency' 	=> [ 'magic_mouse', '==', '1' ]
						),
						array(
							'id' 			=> 'magic_mouse_on_hover',
							'type' 			=> $free ? 'content' : 'color_picker',
							'title' 		=> esc_html__( 'On hover', 'codevz-plus' ),
							'content' 		=> Codevz_Plus::pro_badge(),
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'settings' 		=> [ 'background', 'width', 'height', 'border' ],
							'dependency' 	=> [ 'magic_mouse', '==', '1' ]
						),
						array(
							'id' 			=> '_css_magic_mouse_inner',
							'type' 			=> $free ? 'cz_sk_free' : 'cz_sk',
							'title' 		=> esc_html__( 'Inner circle', 'codevz-plus' ),
							'button' 		=> esc_html__( 'Inner circle', 'codevz-plus' ),
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'settings' 		=> array( 'background', 'width', 'height', 'border' ),
							'selector' 		=> 'div.codevz-magic-mouse div:first-child',
							'dependency' 	=> [ 'magic_mouse', '==', '1' ]
						),
						array(
							'id' 			=> '_css_magic_mouse_outer',
							'type' 			=> $free ? 'cz_sk_free' : 'cz_sk',
							'title' 		=> esc_html__( 'Outer circle', 'codevz-plus' ),
							'button' 		=> esc_html__( 'Outer circle', 'codevz-plus' ),
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'settings' 		=> array( 'background', 'width', 'height', 'border' ),
							'selector' 		=> 'div.codevz-magic-mouse div:last-child',
							'dependency' 	=> [ 'magic_mouse', '==', '1' ]
						),
						array(
							'id' 			=> '_css_magic_mouse_on_hover',
							'type' 			=> $free ? 'cz_sk_free' : 'cz_sk',
							'title' 		=> esc_html__( 'On hover', 'codevz-plus' ),
							'button' 		=> esc_html__( 'On hover', 'codevz-plus' ),
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'settings' 		=> array( 'background', 'width', 'height', 'border' ),
							'selector' 		=> 'div.codevz-magic-mouse-hover div:last-child',
							'dependency' 	=> [ 'magic_mouse', '==', '1' ]
						),
					),
				),

				array(
					'name'   => 'general_pwa',
					'title'  => esc_html__( 'Progressive Web App', 'codevz-plus' ),
					'fields' => array(

						array(
							'type'    		=> 'notice',
							'class'   		=> 'info',
							'content' 		=> esc_html__( "Enable this option to convert your website into the web application. A progressive web app (PWA) is an app that's built using web platform technologies, but that provides a user experience like that of a platform-specific app", 'codevz-plus' )
						),
						array(
							'id' 			=> 'pwa',
							'type' 			=> $free ? 'content' : 'switcher',
							'title' 		=> esc_html__( 'Progressive Web App', 'codevz-plus' ),
							'content' 		=> Codevz_Plus::pro_badge()
						),
						array(
							'id' 			=> 'pwa_icon',
							'type' 			=> $free ? 'content' : 'upload',
							'title' 		=> esc_html__( 'Icon', 'codevz-plus' ),
							'help' 			=> esc_html__( 'The application icon should be in PNG format, 512x512 pixels, and high quality', 'codevz-plus' ),
							'content' 		=> Codevz_Plus::pro_badge(),
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'dependency' 	=> [ 'pwa', '==', 'true' ]
						),
						array(
							'id' 			=> 'pwa_title',
							'type' 			=> $free ? 'content' : 'text',
							'title' 		=> esc_html__( 'Title', 'codevz-plus' ),
							'content' 		=> Codevz_Plus::pro_badge(),
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'dependency' 	=> [ 'pwa', '==', 'true' ]
						),
						array(
							'id' 			=> 'pwa_content',
							'type' 			=> $free ? 'content' : 'textarea',
							'title' 		=> esc_html__( 'Description', 'codevz-plus' ),
							'content' 		=> Codevz_Plus::pro_badge(),
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'dependency' 	=> [ 'pwa', '==', 'true' ]
						),
						array(
							'id' 			=> 'pwa_name',
							'type' 			=> $free ? 'content' : 'text',
							'title' 		=> esc_html__( 'Name', 'codevz-plus' ),
							'content' 		=> Codevz_Plus::pro_badge(),
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'dependency' 	=> [ 'pwa', '==', 'true' ]
						),
						array(
							'id' 			=> 'pwa_short_name',
							'type' 			=> $free ? 'content' : 'text',
							'title' 		=> esc_html__( 'Short name', 'codevz-plus' ),
							'content' 		=> Codevz_Plus::pro_badge(),
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'dependency' 	=> [ 'pwa', '==', 'true' ]
						),
						array(
							'id' 			=> 'pwa_desc',
							'type' 			=> $free ? 'content' : 'textarea',
							'title' 		=> esc_html__( 'Description', 'codevz-plus' ),
							'content' 		=> Codevz_Plus::pro_badge(),
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'dependency' 	=> [ 'pwa', '==', 'true' ]
						),
						array(
							'id' 			=> 'pwa_theme_color',
							'type' 			=> $free ? 'content' : 'color_picker',
							'title' 		=> esc_html__( 'Theme color', 'codevz-plus' ),
							'content' 		=> Codevz_Plus::pro_badge(),
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'dependency' 	=> [ 'pwa', '==', 'true' ]
						),
						array(
							'id' 			=> 'pwa_background_color',
							'type' 			=> $free ? 'content' : 'color_picker',
							'title' 		=> esc_html__( 'Background color', 'codevz-plus' ),
							'content' 		=> Codevz_Plus::pro_badge(),
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'dependency' 	=> [ 'pwa', '==', 'true' ]
						),
						array(
							'id' 			=> 'pwa_cookie_name',
							'type' 			=> $free ? 'content' : 'text',
							'title' 		=> esc_html__( 'Custom cookie', 'codevz-plus' ),
							'help' 			=> esc_html__( 'Useful if you want to reset and display the PWA popup again to old users', 'codevz-plus' ),
							'attributes' 	=> [
								'placeholder' => 'cz_cookie_2'
							],
							'content' 		=> Codevz_Plus::pro_badge(),
							'dependency' 	=> [ 'pwa', '==', 'true' ]
						),

						array(
							'type'    		=> 'notice',
							'class'   		=> 'info',
							'content' 		=> '<i class="fas fa-brush mr8"></i>' . esc_html__( 'Styling', 'codevz-plus' ),
							'dependency' 	=> [ 'pwa', '==', 'true' ]
						),
						array(
							'id' 			=> '_css_pwa_overlay',
							'type'  		=> $free ? 'cz_sk_free' : 'cz_sk',
							'title' 		=> esc_html__( 'Overlay', 'codevz-plus' ),
							'button' 		=> esc_html__( 'Overlay', 'codevz-plus' ),
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'settings' 		=> array( 'background' ),
							'selector' 		=> '.codevz-pwa',
							'dependency' 	=> [ 'pwa', '==', 'true' ]
						),
						array(
							'id' 			=> '_css_pwa_popup',
							'type'  		=> $free ? 'cz_sk_free' : 'cz_sk',
							'title' 		=> esc_html__( 'Popup', 'codevz-plus' ),
							'button' 		=> esc_html__( 'Popup', 'codevz-plus' ),
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'settings' 		=> array( 'background' ),
							'selector' 		=> '.codevz-pwa > div',
							'dependency' 	=> [ 'pwa', '==', 'true' ]
						),
						array(
							'id' 			=> '_css_pwa_title',
							'type'  		=> $free ? 'cz_sk_free' : 'cz_sk',
							'title' 		=> esc_html__( 'Title', 'codevz-plus' ),
							'button' 		=> esc_html__( 'Title', 'codevz-plus' ),
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'settings' 		=> array( 'color', 'border', 'background' ),
							'selector' 		=> '.codevz-pwa-title',
							'dependency' 	=> [ 'pwa', '==', 'true' ]
						),
						array(
							'id' 			=> '_css_pwa_content',
							'type'  		=> $free ? 'cz_sk_free' : 'cz_sk',
							'title' 		=> esc_html__( 'Content', 'codevz-plus' ),
							'button' 		=> esc_html__( 'Content', 'codevz-plus' ),
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'settings' 		=> array( 'color', 'line-height' ),
							'selector' 		=> '.codevz-pwa > div > p',
							'dependency' 	=> [ 'pwa', '==', 'true' ]
						),
						array(
							'id' 			=> '_css_pwa_footer',
							'type'  		=> $free ? 'cz_sk_free' : 'cz_sk',
							'title' 		=> esc_html__( 'Footer', 'codevz-plus' ),
							'button' 		=> esc_html__( 'Footer', 'codevz-plus' ),
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'settings' 		=> array( 'color', 'border', 'background' ),
							'selector' 		=> '.codevz-pwa-footer',
							'dependency' 	=> [ 'pwa', '==', 'true' ]
						),
						array(
							'id' 			=> '_css_pwa_close',
							'type'  		=> $free ? 'cz_sk_free' : 'cz_sk',
							'title' 		=> esc_html__( 'Close icon', 'codevz-plus' ),
							'button' 		=> esc_html__( 'Close icon', 'codevz-plus' ),
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'settings' 		=> array( 'color', 'border', 'background' ),
							'selector' 		=> '.codevz-pwa-close',
							'dependency' 	=> [ 'pwa', '==', 'true' ]
						),

					),

				),

				array(
					'name'   => 'general_term_desc_overlay',
					'title'  => esc_html__( 'Terms archive description limit', 'codevz-plus' ),
					'fields' => array(
						array(
							'id'            => 'term_desc_limit',
							'type'          => $free ? 'content' : 'switcher',
							'title'         => esc_html__( 'Terms description limit', 'codevz-plus' ),
							'help'          => esc_html__( 'Enable a collapsible term description with a fade-out gradient effect and a Show More/Less toggle. Ideal for managing long category or taxonomy descriptions without overwhelming the layout', 'codevz-plus' ),
							'content' 		=> Codevz_Plus::pro_badge()
						),
						array(
							'id' 			=> 'term_desc_limit_height',
							'type' 			=> 'slider',
							'title' 		=> esc_html__( 'Height', 'codevz-plus' ),
							'options'		=> array( 'unit' => 'px', 'step' => 1, 'min' => 100, 'max' => 500 ),
							'default' 		=> '200px',
							'dependency' 	=> array( 'term_desc_limit', '!=', '' ),
						),
						array(
							'id' 			=> 'term_desc_limit_color',
							'type' 			=> 'color_picker',
							'title' 		=> esc_html__( 'Overlay color', 'codevz-plus' ),
							'dependency' 	=> array( 'term_desc_limit', '!=', '' ),
						),
					),
				),

				array(
					'name'   => 'general_more',
					'title'  => esc_html__( 'Advanced', 'codevz-plus' ),
					'fields' => array(
						array(
							'id'            => 'maintenance_mode',
							'type'          => $free ? 'content' : 'select',
							'title'         => esc_html__( 'Maintenance', 'codevz-plus' ),
							'content' 		=> Codevz_Plus::pro_badge(),
							'help'          => esc_html__( 'You can create a coming soon or maintenance mode page and assign it here, This will redirect all your website visitors to that designated page', 'codevz-plus' ),
							'options'       => wp_parse_args( Codevz_Plus::$array_pages, [
								'' 				=> esc_html__( '~ Disable ~', 'codevz-plus' ),
								'simple' 		=> esc_html__( '~ Simple ~', 'codevz-plus' )
							]),
							'edit_link' 	=> true,
							'setting_args'  => [ 'transport' => 'postMessage' ]
						),
						array(
							'id'			=> 'maintenance_message',
							'type'			=> 'textarea',
							'title'			=> esc_html__( 'Maintenance message', 'codevz-plus' ),
							'attributes' 	=> [
								'placeholder' 	=> esc_html__( 'We are currently in maintenance mode. We will be back soon', 'codevz-plus' )
							],
							'dependency' 	=> [ 'maintenance_mode', '==', 'simple' ]
						),
						array(
							'id'            => 'smooth_scroll',
							'type'          => $free ? 'content' : 'switcher',
							'title'         => esc_html__( 'Smooth scroll', 'codevz-plus' ),
							'help'          => esc_html__( 'Replace the default browser scrolling with a more fluid and controlled animation, offering a "buttery smooth" feel', 'codevz-plus' ),
							'content' 		=> Codevz_Plus::pro_badge()
						),
						array(
							'id'            => 'carousel_svg_cursor',
							'type'          => $free ? 'content' : 'switcher',
							'title'         => esc_html__( 'Carousel cursor', 'codevz-plus' ),
							'help'          => esc_html__( 'The cursor will switch to an SVG icon when hovering over carousel elements', 'codevz-plus' ),
							'content' 		=> Codevz_Plus::pro_badge()
						),
						array(
							'id'            => 'force_disable_comments',
							'type'          => $free ? 'content' : 'switcher',
							'title'         => esc_html__( 'Disable comments', 'codevz-plus' ),
							'help'          => esc_html__( 'Disable comments and comment form on all posts', 'codevz-plus' ),
							'content' 		=> Codevz_Plus::pro_badge()
						),
						array(
							'id'            => 'disable_lightbox',
							'type'          => $free ? 'content' : 'switcher',
							'title'         => esc_html__( 'Disable lightbox', 'codevz-plus' ),
							'content' 		=> Codevz_Plus::pro_badge()
						),
						array(
							'id'            => 'disable_rtl_numbers',
							'type'          => $free ? 'content' : 'switcher',
							'title'         => esc_html__( 'Disable RTL numbers', 'codevz-plus' ),
							'content' 		=> Codevz_Plus::pro_badge()
						),
						array(
							'id'            => 'popup',
							'type'          => 'select',
							'title'         => esc_html__( 'Popup', 'codevz-plus' ),
							'options'       => Codevz_Plus::$array_pages,
							'edit_link' 	=> true,
							'dependency' 	=> [ 'xxx', '==', 'xxx' ]
						),
						array(
							'id' 			=> 'add_post_type',
							'type' 			=> 'group',
							'title' 		=> esc_html__( 'Add', 'codevz-plus' ),
							'button_title' 	=> esc_html__( 'Add', 'codevz-plus' ),
							'fields' 		=> array(
								array(
									'id' 			=> 'name',
									'type' 			=> 'text',
									'title' 		=> esc_html__('Name', 'codevz-plus' ),
									'desc' 			=> 'e.g. cz_projects or cz_movies',
									'setting_args' 	=> [ 'transport' => 'postMessage' ],
								),
							),
							'dependency' 	=> [ 'xxx', '==', 'xxx' ]
						),

					),

				),
			),
		);

		$options[ 'typography' ]   = array(
			'name' 		=> 'typography',
			'title' 	=> esc_html__( 'Typography', 'codevz-plus' ),
			'fields' => array(
				array(
					'id' 			=> '_css_body_typo',
					'type' 			=> 'cz_sk',
					'title' 		=> esc_html__( 'Body', 'codevz-plus' ),
					'button' 		=> esc_html__( 'Body', 'codevz-plus' ),
					'setting_args' 	=> [ 'transport' => 'postMessage' ],
					'settings' 		=> array( 'color', 'font-family', 'font-size', 'line-height' ),
					'selector' 		=> 'body, body.rtl, .rtl form, .xtra-fixed-mobile-nav-e > div'
				),
				array(
					'id' 			=> '_css_body_typo_tablet',
					'type' 			=> 'cz_sk_hidden',
					'setting_args' 	=> [ 'transport' => 'postMessage' ],
					'selector' 		=> 'body, body.rtl, .rtl form, .xtra-fixed-mobile-nav-e > div'
				),
				array(
					'id' 			=> '_css_body_typo_mobile',
					'type' 			=> 'cz_sk_hidden',
					'setting_args' 	=> [ 'transport' => 'postMessage' ],
					'selector' 		=> 'body, body.rtl, .rtl form, .xtra-fixed-mobile-nav-e > div'
				),
				array(
					'id' 			=> '_css_menu_nav_typo',
					'type' 			=> 'cz_sk',
					'title' 		=> esc_html__( 'Menu', 'codevz-plus' ),
					'button' 		=> esc_html__( 'Menu', 'codevz-plus' ),
					'setting_args' 	=> [ 'transport' => 'postMessage' ],
					'settings' 		=> array( 'font-family' ),
					'selector' 		=> '.sf-menu, .sf-menu > .cz > a'
				),
				array(
					'id' 			=> '_css_menu_nav_typo_tablet',
					'type' 			=> 'cz_sk_hidden',
					'setting_args' 	=> [ 'transport' => 'postMessage' ],
					'selector' 		=> '.sf-menu, .sf-menu > .cz > a'
				),
				array(
					'id' 			=> '_css_menu_nav_typo_mobile',
					'type' 			=> 'cz_sk_hidden',
					'setting_args' 	=> [ 'transport' => 'postMessage' ],
					'selector' 		=> '.sf-menu, .sf-menu > .cz > a'
				),
				array(
					'id' 			=> '_css_all_headlines',
					'type' 			=> 'cz_sk',
					'title' 		=> esc_html__( 'Headlines', 'codevz-plus' ),
					'button' 		=> esc_html__( 'Headlines', 'codevz-plus' ),
					'setting_args' 	=> [ 'transport' => 'postMessage' ],
					'settings' 		=> array( 'color', 'font-family', 'line-height' ),
					'selector' 		=> 'h1,h2,h3,h4,h5,h6'
				),
				array(
					'id' 			=> '_css_all_headlines_tablet',
					'type' 			=> 'cz_sk_hidden',
					'setting_args' 	=> [ 'transport' => 'postMessage' ],
					'selector' 		=> 'h1,h2,h3,h4,h5,h6'
				),
				array(
					'id' 			=> '_css_all_headlines_mobile',
					'type' 			=> 'cz_sk_hidden',
					'setting_args' 	=> [ 'transport' => 'postMessage' ],
					'selector' 		=> 'h1,h2,h3,h4,h5,h6'
				),
				array(
					'id' 			=> '_css_h1',
					'type' 			=> 'cz_sk',
					'title' 		=> 'H1',
					'button' 		=> 'H1',
					'setting_args' 	=> [ 'transport' => 'postMessage' ],
					'settings' 		=> array( 'color', 'line-height' ),
					'selector' 		=> 'body h1'
				),
				array(
					'id' 			=> '_css_h1_tablet',
					'type' 			=> 'cz_sk_hidden',
					'setting_args' 	=> [ 'transport' => 'postMessage' ],
					'selector' 		=> 'body h1'
				),
				array(
					'id' 			=> '_css_h1_mobile',
					'type' 			=> 'cz_sk_hidden',
					'setting_args' 	=> [ 'transport' => 'postMessage' ],
					'selector' 		=> 'body h1'
				),
				array(
					'id' 			=> '_css_h2',
					'type' 			=> 'cz_sk',
					'title' 		=> 'H2',
					'button' 		=> 'H2',
					'setting_args' 	=> [ 'transport' => 'postMessage' ],
					'settings' 		=> array( 'color', 'line-height' ),
					'selector' 		=> 'body h2'
				),
				array(
					'id' 			=> '_css_h2_tablet',
					'type' 			=> 'cz_sk_hidden',
					'setting_args' 	=> [ 'transport' => 'postMessage' ],
					'selector' 		=> 'body h2'
				),
				array(
					'id' 			=> '_css_h2_mobile',
					'type' 			=> 'cz_sk_hidden',
					'setting_args' 	=> [ 'transport' => 'postMessage' ],
					'selector' 		=> 'body h2'
				),
				array(
					'id' 			=> '_css_h3',
					'type' 			=> 'cz_sk',
					'title' 		=> 'H3',
					'button' 		=> 'H3',
					'setting_args' 	=> [ 'transport' => 'postMessage' ],
					'settings' 		=> array( 'color', 'line-height' ),
					'selector' 		=> 'body h3'
				),
				array(
					'id' 			=> '_css_h3_tablet',
					'type' 			=> 'cz_sk_hidden',
					'setting_args' 	=> [ 'transport' => 'postMessage' ],
					'selector' 		=> 'body h3'
				),
				array(
					'id' 			=> '_css_h3_mobile',
					'type' 			=> 'cz_sk_hidden',
					'setting_args' 	=> [ 'transport' => 'postMessage' ],
					'selector' 		=> 'body h3'
				),
				array(
					'id' 			=> '_css_h4',
					'type'          => $free ? 'cz_sk_free' : 'cz_sk',
					'title' 		=> 'H4',
					'button' 		=> 'H4',
					'setting_args' 	=> [ 'transport' => 'postMessage' ],
					'settings' 		=> array( 'color', 'line-height' ),
					'selector' 		=> 'body h4'
				),
				array(
					'id' 			=> '_css_h4_tablet',
					'type' 			=> 'cz_sk_hidden',
					'setting_args' 	=> [ 'transport' => 'postMessage' ],
					'selector' 		=> 'body h4'
				),
				array(
					'id' 			=> '_css_h4_mobile',
					'type' 			=> 'cz_sk_hidden',
					'setting_args' 	=> [ 'transport' => 'postMessage' ],
					'selector' 		=> 'body h4'
				),
				array(
					'id' 			=> '_css_h5',
					'type'          => $free ? 'cz_sk_free' : 'cz_sk',
					'title' 		=> 'H5',
					'button' 		=> 'H5',
					'setting_args' 	=> [ 'transport' => 'postMessage' ],
					'settings' 		=> array( 'color', 'line-height' ),
					'selector' 		=> 'body h5'
				),
				array(
					'id' 			=> '_css_h5_tablet',
					'type' 			=> 'cz_sk_hidden',
					'setting_args' 	=> [ 'transport' => 'postMessage' ],
					'selector' 		=> 'body h5'
				),
				array(
					'id' 			=> '_css_h5_mobile',
					'type' 			=> 'cz_sk_hidden',
					'setting_args' 	=> [ 'transport' => 'postMessage' ],
					'selector' 		=> 'body h5'
				),
				array(
					'id' 			=> '_css_h6',
					'type'          => $free ? 'cz_sk_free' : 'cz_sk',
					'title' 		=> 'H6',
					'button' 		=> 'H6',
					'setting_args' 	=> [ 'transport' => 'postMessage' ],
					'settings' 		=> array( 'color', 'line-height' ),
					'selector' 		=> 'body h6'
				),
				array(
					'id' 			=> '_css_h6_tablet',
					'type' 			=> 'cz_sk_hidden',
					'setting_args' 	=> [ 'transport' => 'postMessage' ],
					'selector' 		=> 'body h6'
				),
				array(
					'id' 			=> '_css_h6_mobile',
					'type' 			=> 'cz_sk_hidden',
					'setting_args' 	=> [ 'transport' => 'postMessage' ],
					'selector' 		=> 'body h6'
				),
				array(
					'id' 			=> '_css_p',
					'type'          => $free ? 'cz_sk_free' : 'cz_sk',
					'title' 		=> esc_html__( 'Paragraphs', 'codevz-plus' ),
					'button' 		=> esc_html__( 'Paragraphs', 'codevz-plus' ),
					'setting_args' 	=> [ 'transport' => 'postMessage' ],
					'settings' 		=> array( 'color' ),
					'selector' 		=> 'p'
				),
				array(
					'id' 			=> '_css_p_tablet',
					'type' 			=> 'cz_sk_hidden',
					'setting_args' 	=> [ 'transport' => 'postMessage' ],
					'selector' 		=> 'p'
				),
				array(
					'id' 			=> '_css_p_mobile',
					'type' 			=> 'cz_sk_hidden',
					'setting_args' 	=> [ 'transport' => 'postMessage' ],
					'selector' 		=> 'p'
				),
				array(
					'id' 			=> '_css_a',
					'hover_id' 		=> '_css_a_hover',
					'type'          => $free ? 'cz_sk_free' : 'cz_sk',
					'title' 		=> esc_html__( 'Links', 'codevz-plus' ),
					'button' 		=> esc_html__( 'Links', 'codevz-plus' ),
					'setting_args' 	=> [ 'transport' => 'postMessage' ],
					'settings' 		=> array( 'color' ),
					'selector' 		=> 'a'
				),
				array(
					'id' 			=> '_css_a_tablet',
					'type' 			=> 'cz_sk_hidden',
					'setting_args' 	=> [ 'transport' => 'postMessage' ],
					'selector' 		=> 'a'
				),
				array(
					'id' 			=> '_css_a_mobile',
					'type' 			=> 'cz_sk_hidden',
					'setting_args' 	=> [ 'transport' => 'postMessage' ],
					'selector' 		=> 'a'
				),
				array(
					'id' 			=> '_css_a_hover',
					'type' 			=> 'cz_sk_hidden',
					'setting_args' 	=> [ 'transport' => 'postMessage' ],
					'selector' 		=> 'a:hover'
				),
				array(
					'type'    => 'notice',
					'class'   => 'info xtra-notice mt30',
					'content' => '<i class="fas fa-boxes-packing mr8"></i>' . esc_html__( 'Add Custom Fonts', 'codevz-plus' )
				),
				array(
					'id'              => 'upload_custom_fonts',
					'type'            => $free ? 'content' : 'group',
					'content' 		  => Codevz_Plus::pro_badge(),
					'title' 		  => esc_html__( 'Upload custom fonts', 'codevz-plus' ),
					'help' 			  => esc_html__( 'Add you custom fonts here and save theme options, then refresh the page you can see your fonts in theme fonts library', 'codevz-plus' ),
					'button_title'    => esc_html__( 'Add', 'codevz-plus' ),
					'fields'          => array(
						array(
							'id' 		     => 'font',
							'type' 		     => 'text',
							'title' 	     => esc_html__('Font CSS name', 'codevz-plus' )
						),
						array(
							'id' 			=> 'otf',
							'type' 			=> 'upload',
							'title' 		=> 'otf',
							'setting_args' 	=> array( 'transport' => 'postMessage' )
						),
						array(
							'id' 			=> 'ttf',
							'type' 			=> 'upload',
							'title' 		=> 'ttf',
							'setting_args' 	=> array( 'transport' => 'postMessage' )
						),
						array(
							'id' 			=> 'woff',
							'type' 			=> 'upload',
							'title' 		=> 'woff',
							'setting_args' 	=> array( 'transport' => 'postMessage' )
						),
						array(
							'id' 			=> 'woff2',
							'type' 			=> 'upload',
							'title' 		=> 'woff2',
							'setting_args' 	=> array( 'transport' => 'postMessage' )
						),
						array(
							'id' 			=> 'svg',
							'type' 			=> 'upload',
							'title' 		=> 'svg',
							'setting_args' 	=> array( 'transport' => 'postMessage' )
						),
					),
					'setting_args' 	  => [ 'transport' => 'postMessage' ]
				),

				array(
					'id'              => 'wp_editor_fonts',
					'type'            => $free ? 'content' : 'group',
					'content' 		  => Codevz_Plus::pro_badge(),
					'title' 		  => esc_html__( 'Add fonts for WP editor', 'codevz-plus' ),
					'help' 			  => esc_html__( 'You can add custom google fonts and use them inside WP Editor in posts or page builder elements', 'codevz-plus' ),
					'button_title'    => esc_html__( 'Add', 'codevz-plus' ),
					'fields'          => array(
						array(
							'id' 		     => 'font',
							'type' 		     => 'select_font',
							'title' 	     => esc_html__('Font family', 'codevz-plus' )
						),
					),
					'setting_args' 	  => [ 'transport' => 'postMessage' ]
				),

				array(
					'type'    => 'notice',
					'class'   => 'info xtra-notice mt30',
					'content' => '<i class="fas fa-boxes-packing mr8"></i>' . esc_html__( 'More', 'codevz-plus' )
				),
				array(
					'id'            => 'disable_loading_google_fonts',
					'type'          => $free ? 'content' : 'switcher',
					'title'         => esc_html__( 'Disable google fonts', 'codevz-plus' ),
					'content' 		=> Codevz_Plus::pro_badge()
				),

			),

		);

		$options[ 'header' ] = array(
			'name' 		=> 'header',
			'title' 	=> esc_html__( 'Header', 'codevz-plus' ),
			'sections' => array(
				array(
					'name'   => 'header_elementor',
					'title'  => esc_html__( 'Custom Header Template', 'codevz-plus' ),
					'fields' => array(
						array(
							'id'    	=> 'header_elementor',
							'type'  	=> 'select',
							'title' 	=> esc_html__( 'Select header', 'codevz-plus' ),
							'help' 		=> esc_html__( 'Create a template or page and assign it as custom template here.', 'codevz-plus' ),
							'options' 	=> Codevz_Plus::$array_pages,
							'edit_link' => true
						),
						array(
							'id'    	=> 'header_mobile_elementor',
							'type'  	=> $free ? 'content' : 'select',
							'content' 	=> Codevz_Plus::pro_badge(),
							'title' 	=> esc_html__( 'Tablet & mobile', 'codevz-plus' ),
							'options' 	=> Codevz_Plus::$array_pages,
							'edit_link' => true
						),
						array(
							'id'    	=> 'header_elementor_sticky',
							'type'  	=> $free ? 'content' : 'switcher',
							'content' 	=> Codevz_Plus::pro_badge(),
							'title' 	=> esc_html__( 'Sticky header?', 'codevz-plus' ),
						),
						array(
							'id'    	=> 'header_elementor_smart_sticky',
							'type'  	=> $free ? 'content' : 'switcher',
							'content' 	=> Codevz_Plus::pro_badge(),
							'title' 	=> esc_html__( 'Smart sticky?', 'codevz-plus' ),
							'dependency' => [ 'header_elementor_sticky', '==', 'true' ],
						),
						array(
							'id'    	=> 'header_elementor_custom_sticky',
							'type'  	=> $free ? 'content' : 'select',
							'content' 	=> Codevz_Plus::pro_badge(),
							'title' 	=> esc_html__( 'Custom sticky', 'codevz-plus' ),
							'options' 	=> Codevz_Plus::$array_pages,
							'edit_link' => true,
							'dependency' => [ 'header_elementor_sticky', '==', 'true' ],
						),
					),
				),

			  array(
				'name'   => 'header_logo',
				'title'  => esc_html__( 'Logo', 'codevz-plus' ),
				'fields' => array(
						array(
							'id' 			=> 'logo',
							'type' 			=> 'upload',
							'title' 		=> esc_html__( 'Logo', 'codevz-plus' ),
							'preview'       => 1,
							'setting_args' 	=> array('transport' => 'postMessage')
						),
						array(
							'id' 			=> 'logo_2',
							'type' 			=> $free ? 'content' : 'upload',
							'content' 		=> Codevz_Plus::pro_badge(),
							'title' 		=> esc_html__( 'Logo', 'codevz-plus' ) . ' 2',
							'help' 			=> esc_html__( 'Useful for sticky header or footer', 'codevz-plus' ),
							'preview'       => 1,
							'setting_args' 	=> array('transport' => 'postMessage')
						),
						array(
							'id'            => 'logo_hover_tooltip',
							'type'          => $free ? 'content' : 'select',
							'content' 		=> Codevz_Plus::pro_badge(),
							'title'         => esc_html__( 'Tooltip', 'codevz-plus' ),
							'options'       => Codevz_Plus::$array_pages,
							'edit_link' 	=> true
						),

						array(
							'type'    		=> 'notice',
							'class'   		=> 'info xtra-notice',
							'content' 		=> '<i class="fas fa-brush mr8"></i>' . esc_html__( 'Tilt effect', 'codevz-plus' )
						),
						array(
							'id'    		=> 'logo_tilt',
							'type'  		=> $free ? 'content' : 'switcher',
							'content' 		=> Codevz_Plus::pro_badge(),
							'title' 		=> esc_html__( 'Tilt effect', 'codevz-plus' ),
						),
						array(
							'id'            => 'logo_scale',
							'type'          => $free ? 'content' : 'select',
							'content' 		=> Codevz_Plus::pro_badge(),
							'title'         => esc_html__( 'Scale', 'codevz-plus' ),
							'options'       => array(
								'0.9' 			=> '0.9',
								'1' 			=> '1',
								'1.1' 			=> '1.1',
							),
							'default_option' => esc_html__( '~ Disable ~', 'codevz-plus' ),
							'dependency' 	=> array( 'logo_tilt', '!=', '' )
						),
						array(
							'id'            => 'logo_glare',
							'type'          => $free ? 'content' : 'select',
							'content' 		=> Codevz_Plus::pro_badge(),
							'title'         => esc_html__( 'Glare', 'codevz-plus' ),
							'options'       => array(
								'0.9' 			=> '0.9',
								'0.8' 			=> '0.8',
								'1' 			=> '1',
								'1.1' 			=> '1.1',
								'1.2' 			=> '1.2',
							),
							'default_option' => esc_html__( '~ Disable ~', 'codevz-plus' ),
							'dependency' 	=> array( 'logo_tilt', '!=', '' )
						),

						array(
							'type'    		=> 'notice',
							'class'   		=> 'info xtra-notice',
							'content' 		=> '<i class="fas fa-brush mr8"></i>' . esc_html__( 'Styling', 'codevz-plus' )
						),
						array(
							'id'            => '_css_logo_css',
							'type'          => 'cz_sk',
							'title' 		=> esc_html__( 'Logo', 'codevz-plus' ),
							'button' 		=> esc_html__( 'Logo', 'codevz-plus' ),
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'settings'      => array( 'color', 'background', 'font-family', 'font-size', 'border' ),
							'selector'      => '.logo > a, .logo > h1, .logo h2',
						),
						array(
							'id' 			=> '_css_logo_css_tablet',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> '.logo > a, .logo > h1, .logo h2',
						),
						array(
							'id' 			=> '_css_logo_css_mobile',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> '.logo > a, .logo > h1, .logo h2',
						),
						array(
							'id' 			=> '_css_logo_2_css',
							'type' 			=> 'cz_sk',
							'title' 		=> esc_html__( 'Logo', 'codevz-plus' ) . ' 2',
							'button' 		=> esc_html__( 'Logo', 'codevz-plus' ) . ' 2',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'settings'      => array( 'color', 'background', 'font-family', 'font-size', 'border' ),
							'selector' 		=> '.logo_2 > a, .logo_2 > h1',
							'dependency' 	=> $free ? [ 'xxx', '==', 'xxx' ] : []
						),
						array(
							'id' 			=> '_css_logo_2_css_tablet',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> '.logo_2 > a, .logo_2 > h1'
						),
						array(
							'id' 			=> '_css_logo_2_css_mobile',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> '.logo_2 > a, .logo_2 > h1'
						),

						array(
							'id' 			=> '_css_logo_hover_tooltip',
							'type' 			=> 'cz_sk',
							'title' 		=> esc_html__( 'Tooltip', 'codevz-plus' ),
							'button' 		=> esc_html__( 'Tooltip', 'codevz-plus' ),
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'settings' 		=> array( 'color', 'background', 'width', 'border' ),
							'selector' 		=> 'div.logo_hover_tooltip',
							'dependency' 	=> array( 'logo_hover_tooltip', '!=', '' )
						),

					)
				),

				array(
					'name'   => 'header_social',
					'title'  => esc_html__( 'Social Icons', 'codevz-plus' ),
					'fields' => array(
						array(
							'id'              => 'social',
							'type'            => 'group',
							'title'           => esc_html__( 'Social Icons', 'codevz-plus' ),
							'button_title'    => esc_html__( 'Add', 'codevz-plus' ),
							'accordion_title' => esc_html__( 'Add', 'codevz-plus' ),
							'fields'          => array(
								array(
									'id'    	=> 'title',
									'type'  	=> 'text',
									'title' 	=> esc_html__( 'Title', 'codevz-plus' )
								),
								array(
									'id'    	=> 'icon',
									'type'  	=> 'icon',
									'title' 	=> esc_html__( 'Icon', 'codevz-plus' ),
									'default' 	=> 'fa fa-facebook'
								),
								array(
									'id'    	=> 'link',
									'type'  	=> 'text',
									'title' 	=> esc_html__( 'Link', 'codevz-plus' )
								),
							),
							'setting_args' 	     => [ 'transport' => 'postMessage' ],
							'selective_refresh'  => array(
								'selector' 			=> '.elms_row .cz_social',
								'settings' 			=> 'codevz_theme_options[social]',
								'render_callback'  	=> function() {
									return Codevz_Plus::social();
								},
								'container_inclusive' => true
							),
						),
						array(
							'id'            => 'social_hover_fx',
							'type'          => 'select',
							'title'         => esc_html__( 'Icons Hover', 'codevz-plus' ),
							'options'       => array(
								'cz_social_fx_0' => esc_html__( 'Zoom In', 'codevz-plus' ),
								'cz_social_fx_1' => esc_html__( 'Zoom Out', 'codevz-plus' ),
								'cz_social_fx_2' => esc_html__( 'Bottom to Top', 'codevz-plus' ),
								'cz_social_fx_3' => esc_html__( 'Top to Bottom', 'codevz-plus' ),
								'cz_social_fx_4' => esc_html__( 'Left to Right', 'codevz-plus' ),
								'cz_social_fx_5' => esc_html__( 'Right to Left', 'codevz-plus' ),
								'cz_social_fx_6' => esc_html__( 'Rotate', 'codevz-plus' ),
								'cz_social_fx_7' => esc_html__( 'Infinite Shake', 'codevz-plus' ),
								'cz_social_fx_8' => esc_html__( 'Infinite Wink', 'codevz-plus' ),
								'cz_social_fx_9' => esc_html__( 'Quick Bob', 'codevz-plus' ),
								'cz_social_fx_10'=> esc_html__( 'Flip Horizontal', 'codevz-plus' ),
								'cz_social_fx_11'=> esc_html__( 'Flip Vertical', 'codevz-plus' ),
							),
							'default_option' => esc_html__( '~ Disable ~', 'codevz-plus' ),
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selective_refresh' => array(
								'selector' 			=> '.elms_row .cz_social',
								'settings' 			=> 'codevz_theme_options[social_hover_fx]',
								'render_callback' 	=> function() {
									return Codevz_Plus::social();
								},
								'container_inclusive' => true
							),
						),
						array(
							'id'            => 'social_color_mode',
							'type'          => 'select',
							'title'         => esc_html__( 'Color Mode', 'codevz-plus' ),
							'options'       => array(
								'cz_social_colored' 		=> esc_html__( 'Brand Colors', 'codevz-plus' ),
								'cz_social_colored_hover' 	=> esc_html__( 'Brand Colors on Hover', 'codevz-plus' ),
								'cz_social_colored_bg' 		=> esc_html__( 'Brand Background', 'codevz-plus' ),
								'cz_social_colored_bg_hover' => esc_html__( 'Brand Background on Hover', 'codevz-plus' ),
							),
							'default_option' => esc_html__( '~ Default ~', 'codevz-plus' ),
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selective_refresh' => array(
								'selector' 			=> '.elms_row .cz_social',
								'settings' 			=> 'codevz_theme_options[social_color_mode]',
								'render_callback' 	=> function() {
									return Codevz_Plus::social();
								},
								'container_inclusive' => true
							),
						),
						array(
							'id'            => 'social_tooltip',
							'type'          => $free ? 'content' : 'select',
							'content' 		=> Codevz_Plus::pro_badge(),
							'title'         => esc_html__( 'Tooltip', 'codevz-plus' ),
							'help'          => esc_html__( 'StyleKit located in Theme Options > General > Colors & Styling', 'codevz-plus' ),
							'options'       => array(
								'cz_tooltip cz_tooltip_up'    => esc_html__( 'Up', 'codevz-plus' ),
								'cz_tooltip cz_tooltip_down'  => esc_html__( 'Down', 'codevz-plus' ),
								'cz_tooltip cz_tooltip_right' => esc_html__( 'Right', 'codevz-plus' ),
								'cz_tooltip cz_tooltip_left'  => esc_html__( 'Left', 'codevz-plus' ),
							),
							'default_option' => esc_html__( '~ Default ~', 'codevz-plus' ),
							'setting_args'  => [ 'transport' => 'postMessage' ],
							'selective_refresh' => array(
								'selector'      => '.elms_row .cz_social',
								'settings'      => 'codevz_theme_options[social_tooltip]',
								'render_callback'   => function() {
									return Codevz_Plus::social();
								},
									'container_inclusive' => true
							),
						),

						array(
							'type' 			=> 'notice',
							'class' 		=> 'info',
							'content' 		=> '<i class="fas fa-brush mr8"></i>' . esc_html__( 'Styling', 'codevz-plus' )
						),
						array(
							'id' 			=> '_css_social',
							'type' 			=> 'cz_sk',
							'title' 		=> esc_html__( 'Container', 'codevz-plus' ),
							'button' 		=> esc_html__( 'Container', 'codevz-plus' ),
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'settings' 		=> array( 'background', 'border' ),
							'selector' 		=> '.elms_row .cz_social, .fixed_side .cz_social, #xtra-social-popup [class*="xtra-social-type-"]'
						),
						array(
							'id' 			=> '_css_social_tablet',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> '.elms_row .cz_social, .fixed_side .cz_social, #xtra-social-popup [class*="xtra-social-type-"]'
						),
						array(
							'id' 			=> '_css_social_mobile',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> '.elms_row .cz_social, .fixed_side .cz_social, #xtra-social-popup [class*="xtra-social-type-"]'
						),
						array(
							'id' 			=> '_css_social_a',
							'hover_id' 		=> '_css_social_a_hover',
							'type' 			=> 'cz_sk',
							'title' 		=> esc_html__( 'Icons', 'codevz-plus' ),
							'button' 		=> esc_html__( 'Icons', 'codevz-plus' ),
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'settings' 		=> array( 'color', 'background', 'font-size', 'border' ),
							'selector' 		=> '.elms_row .cz_social a, .fixed_side .cz_social a, #xtra-social-popup [class*="xtra-social-type-"] a'
						),
						array(
							'id' 			=> '_css_social_a_tablet',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> '.elms_row .cz_social a, .fixed_side .cz_social a, #xtra-social-popup [class*="xtra-social-type-"] a'
						),
						array(
							'id' 			=> '_css_social_a_mobile',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> '.elms_row .cz_social a, .fixed_side .cz_social a, #xtra-social-popup [class*="xtra-social-type-"] a'
						),
					  array(
						'id' 				=> '_css_social_a_hover',
						'type' 				=> 'cz_sk_hidden',
						'setting_args' 		=> [ 'transport' => 'postMessage' ],
						'selector' 			=> '.elms_row .cz_social a:hover, .fixed_side .cz_social a:hover, #xtra-social-popup [class*="xtra-social-type-"] a:hover'
					  ),

					),
				),
				array(
					'name'   => 'header_1',
					'title'  => esc_html__( 'Header top bar', 'codevz-plus' ),
					'fields' => self::row_options( 'header_1' )
				),
				array(
					'name'   => 'header_2',
					'title'  => esc_html__( 'Header', 'codevz-plus' ),
					'fields' => self::row_options( 'header_2' )
				),
				array(
					'name'   => 'header_3',
					'title'  => esc_html__( 'Header bottom bar', 'codevz-plus' ),
					'fields' => self::row_options( 'header_3' )
				),
				array(
					'name'   => 'header_5',
					'title'  => esc_html__( 'Sticky Header', 'codevz-plus' ),
					'fields' => self::row_options( 'header_5' )
				),
				array(
					'name'   => 'mobile_header',
					'title'  => esc_html__( 'Mobile Header', 'codevz-plus' ),
					'fields' => self::row_options( 'header_4' )
				),
				array(
					'name'   => 'mobile_fixed_navigation',
					'title'  => esc_html__( 'Mobile Fixed Navigation', 'codevz-plus' ),
					'fields' => [

						array(
							'id'    	=> 'mobile_fixed_navigation',
							'type'  	=> $free ? 'content' : 'switcher',
							'content' 	=> Codevz_Plus::pro_badge(),
							'title' 	=> esc_html__( 'Mobile Fixed Nav', 'codevz-plus' ),
							'help' 		=> esc_html__( 'Fixed navigation bars allow visitors to quickly access the main site links, remaining constantly visible at the bottom of the website', 'codevz-plus' ),
						),
						array(
							'id'              => 'mobile_fixed_navigation_items',
							'type'            => $free ? 'content' : 'group',
							'content' 		  => Codevz_Plus::pro_badge(),
							'title'           => esc_html__( 'Items', 'codevz-plus' ),
							'button_title'    => esc_html__( 'Add', 'codevz-plus' ),
							'accordion_title' => esc_html__( 'Add', 'codevz-plus' ),
							'fields'          => array(
								array(
									'id'    	=> 'title',
									'type'  	=> 'text',
									'title' 	=> esc_html__( 'Title', 'codevz-plus' )
								),
								array(
									'id'    	=> 'icon_type',
									'type'  	=> 'select',
									'title' 	=> esc_html__( 'Type', 'codevz-plus' ),
									'options' 	=> [
										'' 			=> esc_html__( '~ Select ~', 'codevz-plus' ),
										'icon' 		=> esc_html__( 'Icon', 'codevz-plus' ),
										'image' 	=> esc_html__( 'Image', 'codevz-plus' ),
									],
								),
								array(
									'id'    	=> 'icon',
									'type'  	=> 'icon',
									'title' 	=> esc_html__( 'Icon', 'codevz-plus' ),
									'default' 	=> 'fas fa-home',
									'dependency' => array( 'icon_type', '==', 'icon' ),
								),
								array(
									'id'    	=> 'image',
									'type'  	=> 'upload',
									'title' 	=> esc_html__( 'Image', 'codevz-plus' ),
									'preview' 	=> 1,
									'dependency' => array( 'icon_type', '==', 'image' ),
								),
								array(
									'id' 		=> 'image_size',
									'type' 		=> 'slider',
									'title' 	=> esc_html__( 'Size', 'codevz-plus' ),
									'options'	=> array( 'unit' => 'px', 'step' => 1, 'min' => 0, 'max' => 500 ),
									'dependency' => array( 'icon_type', '==', 'image' ),
								),
								array(
									'id'    	=> 'link',
									'type'  	=> 'text',
									'title' 	=> esc_html__( 'Link', 'codevz-plus' )
								),
								array(
									'id'    	=> 'target',
									'type'  	=> 'switcher',
									'title' 	=> esc_html__( 'New tab?', 'codevz-plus' )
								),
							),
							'setting_args' 	     => [ 'transport' => 'postMessage' ],
							'selective_refresh'  => array(
								'selector' 			=> '.xtra-fixed-mobile-nav',
								'settings' 			=> 'codevz_theme_options[mobile_fixed_navigation_items]',
								'render_callback'  	=> function() {
									return Codevz_Plus::mobile_fixed_navigation();
								},
								'container_inclusive' => true
							),
							'dependency' 	=> $free ? [] : array( 'mobile_fixed_navigation', '!=', '' )
						),
						array(
							'id'    	=> 'mobile_fixed_navigation_title',
							'type'  	=> $free ? 'content' : 'select',
							'content' 	=> Codevz_Plus::pro_badge(),
							'title' 	=> esc_html__( 'Items title', 'codevz-plus' ),
							'options' 	=> [

								'' => esc_html__( '~ Disable ~', 'codevz-plus' ),
								'xtra-fixed-mobile-nav-title-column' 	=> esc_html__( 'Block', 'codevz-plus' ),
								'xtra-fixed-mobile-nav-title-row' 		=> esc_html__( 'Inline', 'codevz-plus' ),

							],
							'setting_args' 	     => [ 'transport' => 'postMessage' ],
							'dependency' 	=> $free ? [] : array( 'mobile_fixed_navigation', '!=', '' )
						),
						array(
							'id'    	=> 'mobile_fixed_navigation_svg',
							'type'  	=> $free ? 'content' : 'switcher',
							'content' 	=> Codevz_Plus::pro_badge(),
							'title' 	=> esc_html__( 'Active menu shape', 'codevz-plus' ),
						),
						array(
							'id' 			=> '_css_mfn',
							'type'  		=> $free ? 'cz_sk_free' : 'cz_sk',
							'title' 		=> esc_html__( 'Container', 'codevz-plus' ),
							'button' 		=> esc_html__( 'Container', 'codevz-plus' ),
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'settings' 		=> array( 'background', 'border', 'box-shadow' ),
							'selector' 		=> '.xtra-fixed-mobile-nav',
							'dependency' 	=> $free ? [] : array( 'mobile_fixed_navigation', '!=', '' )
						),
						array(
							'id' 			=> '_css_mfn_a',
							'hover_id' 		=> '_css_mfn_a_hover',
							'type'  		=> $free ? 'cz_sk_free' : 'cz_sk',
							'title' 		=> esc_html__( 'Links', 'codevz-plus' ),
							'button' 		=> esc_html__( 'Links', 'codevz-plus' ),
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'settings' 		=> array( 'color', 'background', 'border' ),
							'selector' 		=> '.xtra-fixed-mobile-nav > a',
							'dependency' 	=> $free ? [] : array( 'mobile_fixed_navigation', '!=', '' )
						),
						array(
							'id' 			=> '_css_mfn_a_hover',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> '.xtra-fixed-mobile-nav > a:hover,.xtra-fixed-mobile-nav > .xtra-active',
							'dependency' 	=> array( 'mobile_fixed_navigation', '!=', '' )
						),
						array(
							'id' 			=> '_css_mfn_i',
							'hover_id' 		=> '_css_mfn_i_hover',
							'type'  		=> $free ? 'cz_sk_free' : 'cz_sk',
							'title' 		=> esc_html__( 'Icons', 'codevz-plus' ),
							'button' 		=> esc_html__( 'Icons', 'codevz-plus' ),
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'settings' 		=> array( 'color', 'background', 'border' ),
							'selector' 		=> '.xtra-fixed-mobile-nav > a i, .xtra-fixed-mobile-nav > a img',
							'dependency' 	=> $free ? [] : array( 'mobile_fixed_navigation', '!=', '' )
						),
						array(
							'id' 			=> '_css_mfn_i_hover',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> '.xtra-fixed-mobile-nav > a:hover i, .xtra-fixed-mobile-nav > a:hover img, .xtra-fixed-mobile-nav > .xtra-active i, .xtra-fixed-mobile-nav > .xtra-active img',
							'dependency' 	=> array( 'mobile_fixed_navigation', '!=', '' )
						),
						array(
							'id' 			=> '_css_mfn_title',
							'hover_id' 		=> '_css_mfn_title_hover',
							'type'  		=> $free ? 'cz_sk_free' : 'cz_sk',
							'title' 		=> esc_html__( 'Title', 'codevz-plus' ),
							'button' 		=> esc_html__( 'Title', 'codevz-plus' ),
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'settings' 		=> array( 'color', 'background', 'border' ),
							'selector' 		=> '.xtra-fixed-mobile-nav > a span',
							'dependency' 	=> $free ? [] : array( 'mobile_fixed_navigation', '!=', '' )
						),
						array(
							'id' 			=> '_css_mfn_title_hover',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> '.xtra-fixed-mobile-nav > a:hover span, .xtra-fixed-mobile-nav > .xtra-active span',
							'dependency' 	=> array( 'mobile_fixed_navigation', '!=', '' )
						),

						array(
							'type'    		=> 'notice',
							'class'   		=> 'info xtra-notice',
							'content' 		=> '<i class="fas fa-bars mr8"></i>' . esc_html__( 'Expandable special menu', 'codevz-plus' ),
							'dependency' 	=> array( 'mobile_fixed_navigation', '!=', '' )
						),
						array(
							'id' 		=> 'mobile_fixed_navigation_e',
							'type' 		=> 'switcher',
							'title' 	=> esc_html__( 'Expandable menu', 'codevz-plus' ),
							'dependency' => array( 'mobile_fixed_navigation', '!=', '' )
						),
						array(
							'id'         => 'mobile_fixed_navigation_e_icon',
							'type'       => 'icon',
							'title'      => esc_html__( 'Icon', 'codevz-plus' ),
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'dependency' => array( 'mobile_fixed_navigation|mobile_fixed_navigation_e', '!=|!=', '|' )
						),
						array(
							'id'    	=> 'mobile_fixed_navigation_e_position',
							'type'  	=> $free ? 'content' : 'select',
							'content' 	=> Codevz_Plus::pro_badge(),
							'title' 	=> esc_html__( 'Icon position', 'codevz-plus' ),
							'options' 	=> [
								'0' 		=> '1',
								'1' 		=> '2',
								'2' 		=> '3',
								'3' 		=> '4',
								'4' 		=> '5',
							],
							'default' 	 => '2',
							'dependency' => array( 'mobile_fixed_navigation|mobile_fixed_navigation_e', '!=|!=', '|' )
						),
						array(
							'id'    	=> 'mobile_fixed_navigation_e_icon_offset',
							'type'  	=> 'slider',
							'title' 	=> esc_html__( 'Icon offset', 'codevz-plus' ),
							'options'	=> array( 'unit' => 'px', 'step' => 1, 'min' => -70, 'max' => 40 ),
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'dependency' => array( 'mobile_fixed_navigation|mobile_fixed_navigation_e', '!=|!=', '|' )
						),
						array(
							'id'              => 'mobile_fixed_navigation_e_items',
							'type'            => $free ? 'content' : 'group',
							'content' 		  => Codevz_Plus::pro_badge(),
							'title'           => esc_html__( 'Inner menus', 'codevz-plus' ),
							'button_title'    => esc_html__( 'Add', 'codevz-plus' ),
							'accordion_title' => esc_html__( 'Add', 'codevz-plus' ),
							'fields'          => array(
								array(
									'id'    	=> 'title',
									'type'  	=> 'text',
									'title' 	=> esc_html__( 'Title', 'codevz-plus' )
								),
								array(
									'id'    	=> 'link',
									'type'  	=> 'text',
									'title' 	=> esc_html__( 'Link', 'codevz-plus' )
								),
							),
							'setting_args' 	     => [ 'transport' => 'postMessage' ],
							'selective_refresh'  => array(
								'selector' 			=> '.xtra-fixed-mobile-nav',
								'settings' 			=> 'codevz_theme_options[mobile_fixed_navigation_e_items]',
								'render_callback'  	=> function() {
									return Codevz_Plus::mobile_fixed_navigation();
								},
								'container_inclusive' => true
							),
							'dependency' => array( 'mobile_fixed_navigation|mobile_fixed_navigation_e', '!=|!=', '|' )
						),
						array(
							'id' 			=> '_css_mfn_e_icon',
							'hover_id' 		=> '_css_mfn_e_icon_hover',
							'type'  		=> $free ? 'cz_sk_free' : 'cz_sk',
							'title' 		=> esc_html__( 'Icon', 'codevz-plus' ),
							'button' 		=> esc_html__( 'Icon', 'codevz-plus' ),
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'settings' 		=> array( 'color', 'background', 'border' ),
							'selector' 		=> '.xtra-fixed-mobile-nav-e',
							'dependency' => array( 'mobile_fixed_navigation|mobile_fixed_navigation_e', '!=|!=', '|' )
						),
						array(
							'id' 			=> '_css_mfn_e_icon_hover',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> '.xtra-fixed-mobile-nav-e:hover, .xtra-fixed-mobile-nav-e.xtra-fixed-mobile-nav-e-active',
							'dependency' => array( 'mobile_fixed_navigation|mobile_fixed_navigation_e', '!=|!=', '|' )
						),
						array(
							'id' 			=> '_css_mfn_e_menu',
							'type'  		=> $free ? 'cz_sk_free' : 'cz_sk',
							'title' 		=> esc_html__( 'Menu', 'codevz-plus' ),
							'button' 		=> esc_html__( 'Menu', 'codevz-plus' ),
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'settings' 		=> array( 'background', 'border' ),
							'selector' 		=> '.xtra-fixed-mobile-nav-e > div',
							'dependency' => array( 'mobile_fixed_navigation|mobile_fixed_navigation_e', '!=|!=', '|' )
						),
						array(
							'id' 			=> '_css_mfn_e_items',
							'hover_id' 		=> '_css_mfn_e_items_hover',
							'type'  		=> $free ? 'cz_sk_free' : 'cz_sk',
							'title' 		=> esc_html__( 'Menu items', 'codevz-plus' ),
							'button' 		=> esc_html__( 'Menu items', 'codevz-plus' ),
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'settings' 		=> array( 'color', 'background', 'border' ),
							'selector' 		=> '.xtra-fixed-mobile-nav-e > div a',
							'dependency' => array( 'mobile_fixed_navigation|mobile_fixed_navigation_e', '!=|!=', '|' )
						),
						array(
							'id' 			=> '_css_mfn_e_items_hover',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> '.xtra-fixed-mobile-nav-e > div a:hover',
							'dependency' => array( 'mobile_fixed_navigation|mobile_fixed_navigation_e', '!=|!=', '|' )
						),

					]
				),

				array(
					'name'   => 'header_menus_hover_effects',
					'title'  => esc_html__( 'Menus Hover Effects', 'codevz-plus' ),
					'fields' => array(

						array(
							'id' 			=> 'dropdown_overlay',
							'type' 			=> 'switcher',
							'title' 		=> esc_html__( 'Dropdown overlay', 'codevz-plus' ),
							'help' 			=> esc_html__( 'Display an overlay background between the header and content area whenever a dropdown (such as menus, search, or shopping cart) is opened in the header', 'codevz-plus' )
						),
						array(
							'id'			=> 'menus_hover_effect_opacity',
							'type'			=> 'switcher',
							'title'			=> esc_html__( 'Menus hover opacity', 'codevz-plus' ),
							'help'			=> esc_html__( 'Dim all menu items except the one being hovered over', 'codevz-plus' ),
							'setting_args' 	=> [ 'transport' => 'postMessage' ]
						),
						array(
							'id'            => 'menus_hover_effect',
							'type'          => $free ? 'content' : 'select',
							'content' 		=> Codevz_Plus::pro_badge(),
							'title'         => esc_html__( 'Hover effect', 'codevz-plus' ),
							'options'       => array(
								'cz_menus_hover_effect_1'     => esc_html__( 'Rotate up', 'codevz-plus' ),
								'cz_menus_hover_effect_6'     => esc_html__( 'Rotate up', 'codevz-plus' ) . ' 2',
								'cz_menus_hover_effect_2'     => esc_html__( 'Rotate down', 'codevz-plus' ),
								'cz_menus_hover_effect_3'     => esc_html__( 'Rotate right', 'codevz-plus' ),
								'cz_menus_hover_effect_4'     => esc_html__( 'Rotate left', 'codevz-plus' ),
								'cz_menus_hover_effect_5'     => esc_html__( 'Clock rotate', 'codevz-plus' ),
								'cz_menus_hover_effect_7'     => esc_html__( 'Zoom In', 'codevz-plus' ),
								'cz_menus_hover_effect_8'     => esc_html__( 'Zoom Out', 'codevz-plus' ),
								'cz_menus_hover_effect_9'     => esc_html__( 'Glitch', 'codevz-plus' ),
								'cz_menus_hover_effect_10'     => esc_html__( 'Neon', 'codevz-plus' ),
							),
							'default_option' => esc_html__( '~ Default ~', 'codevz-plus' ),
							'setting_args'  => [ 'transport' => 'postMessage' ]
						),
						array(
							'id'            => 'menus_dropdown_effect',
							'type'          => $free ? 'content' : 'select',
							'content' 		=> Codevz_Plus::pro_badge(),
							'title'         => esc_html__( 'Dropdown effect', 'codevz-plus' ),
							'options'       => array(
								'cz_menus_dropdown_effect_1'     => esc_html__( 'Fade in up', 'codevz-plus' ),
								'cz_menus_dropdown_effect_2'     => esc_html__( 'Fade in down', 'codevz-plus' ),
								'cz_menus_dropdown_effect_3'     => esc_html__( 'Fade in left', 'codevz-plus' ),
								'cz_menus_dropdown_effect_4'     => esc_html__( 'Fade in right', 'codevz-plus' ),
								'cz_menus_dropdown_effect_5'     => esc_html__( 'Zoom In', 'codevz-plus' ),
								'cz_menus_dropdown_effect_6'     => esc_html__( 'Zoom Out', 'codevz-plus' ),
								'cz_menus_dropdown_effect_7'     => esc_html__( 'Skew left', 'codevz-plus' ),
								'cz_menus_dropdown_effect_8'     => esc_html__( 'Skew right', 'codevz-plus' ),
								'cz_menus_dropdown_effect_9'     => esc_html__( 'Open bottom', 'codevz-plus' ),
								'cz_menus_dropdown_effect_10'    => esc_html__( 'Open left', 'codevz-plus' ),
								'cz_menus_dropdown_effect_11'    => esc_html__( 'Open right', 'codevz-plus' ),
								'cz_menus_dropdown_effect_14'    => esc_html__( 'Fade in blur', 'codevz-plus' ),
								'cz_menus_dropdown_effect_15'    => esc_html__( 'Collapse', 'codevz-plus' ),
							),
							'default_option' => esc_html__( '~ Default ~', 'codevz-plus' ),
							'setting_args'  => [ 'transport' => 'postMessage' ]
						),
						array(
							'id'            => 'menus_dropdown_items_effect',
							'type'          => $free ? 'content' : 'select',
							'content' 		=> Codevz_Plus::pro_badge(),
							'title'         => esc_html__( 'Inner menus effect', 'codevz-plus' ),
							'options'       => array(
								'cz_menus_intro_effect_1'     => esc_html__( 'Fade in blur', 'codevz-plus' ),
								'cz_menus_intro_effect_2'     => esc_html__( 'Fade in left', 'codevz-plus' ),
								'cz_menus_intro_effect_3'     => esc_html__( 'Fade in right', 'codevz-plus' ),
								'cz_menus_intro_effect_4'     => esc_html__( 'Fade in up', 'codevz-plus' ),
								'cz_menus_intro_effect_5'     => esc_html__( 'Fade in down', 'codevz-plus' ),
								'cz_menus_intro_effect_6'     => esc_html__( 'Zoom In', 'codevz-plus' ),
								'cz_menus_intro_effect_7'     => esc_html__( 'Zoom Out', 'codevz-plus' ),
								'cz_menus_intro_effect_8'     => esc_html__( 'Rotate in', 'codevz-plus' ),
								'cz_menus_intro_effect_9'     => esc_html__( 'Rotate in blur', 'codevz-plus' ),
							),
							'default_option' => esc_html__( '~ Default ~', 'codevz-plus' ),
							'setting_args'  => [ 'transport' => 'postMessage' ]
						),

					)
				),

				array(
					'name'   => 'title_br',
					'title'  => esc_html__( 'Title & Breadcrumbs', 'codevz-plus' ),
					'fields' => self::title_options()
				),

				array(
					'name'   => 'title_separator',
					'title'  => esc_html__( 'Title & Breadcrumbs', 'codevz-plus' ) . ' ' . esc_html__( 'Separator', 'codevz-plus' ),
					'fields' => array(
						array(
							'id'    	=> 'hs_style',
							'type'  	=> $free ? 'content' : 'codevz_image_select',
							'content' 	=> Codevz_Plus::pro_badge(),
							'title' 	=> esc_html__( 'Separator', 'codevz-plus' ),
							'options' => [
								'' 		=> [ '-',  Codevz_Plus::$url . 'assets/img/off.png' ],
								'1' 	=> [ '1', Codevz_Plus::$url . 'assets/img/sep_1.jpg' ],
								'2' 	=> [ '2', Codevz_Plus::$url . 'assets/img/sep_2.jpg' ],
								'3' 	=> [ '3', Codevz_Plus::$url . 'assets/img/sep_3.jpg' ],
								'4' 	=> [ '4', Codevz_Plus::$url . 'assets/img/sep_4.jpg' ],
								'5' 	=> [ '5', Codevz_Plus::$url . 'assets/img/sep_5.jpg' ],
								'6' 	=> [ '6', Codevz_Plus::$url . 'assets/img/sep_6.jpg' ],
								'7' 	=> [ '7', Codevz_Plus::$url . 'assets/img/sep_7.jpg' ],
								'8' 	=> [ '8', Codevz_Plus::$url . 'assets/img/sep_8.jpg' ],
								'9' 	=> [ '9', Codevz_Plus::$url . 'assets/img/sep_9.jpg' ],
								'10' 	=> [ '10', Codevz_Plus::$url . 'assets/img/sep_10.jpg' ],
								'11' 	=> [ '11', Codevz_Plus::$url . 'assets/img/sep_11.jpg' ],
								'12' 	=> [ '12', Codevz_Plus::$url . 'assets/img/sep_12.jpg' ],
								'13' 	=> [ '13', Codevz_Plus::$url . 'assets/img/sep_13.jpg' ],
								'14' 	=> [ '14', Codevz_Plus::$url . 'assets/img/sep_14.jpg' ],
								'15' 	=> [ '15', Codevz_Plus::$url . 'assets/img/sep_15.jpg' ],
								'16' 	=> [ '16', Codevz_Plus::$url . 'assets/img/sep_16.jpg' ],
								'17' 	=> [ '17', Codevz_Plus::$url . 'assets/img/sep_17.jpg' ],
								'18' 	=> [ '18', Codevz_Plus::$url . 'assets/img/sep_18.jpg' ],
								'19' 	=> [ '19', Codevz_Plus::$url . 'assets/img/sep_19.jpg' ],
								'20' 	=> [ '20', Codevz_Plus::$url . 'assets/img/sep_20.jpg' ],
								'21' 	=> [ '21', Codevz_Plus::$url . 'assets/img/sep_21.jpg' ],
								'22' 	=> [ '22', Codevz_Plus::$url . 'assets/img/sep_22.jpg' ],
								'23' 	=> [ '23', Codevz_Plus::$url . 'assets/img/sep_23.jpg' ],
								'24' 	=> [ '24', Codevz_Plus::$url . 'assets/img/sep_24.jpg' ],
								'25' 	=> [ '25', Codevz_Plus::$url . 'assets/img/sep_25.jpg' ],
								'26' 	=> [ '26', Codevz_Plus::$url . 'assets/img/sep_26.jpg' ],
								'27' 	=> [ '27', Codevz_Plus::$url . 'assets/img/sep_27.jpg' ],
								'28' 	=> [ '28', Codevz_Plus::$url . 'assets/img/sep_28.jpg' ],
								'29' 	=> [ '29', Codevz_Plus::$url . 'assets/img/sep_29.jpg' ],
								'30' 	=> [ '30', Codevz_Plus::$url . 'assets/img/sep_30.jpg' ],
								'31' 	=> [ '31', Codevz_Plus::$url . 'assets/img/sep_31.jpg' ],
								'32' 	=> [ '32', Codevz_Plus::$url . 'assets/img/sep_32.jpg' ],
								'33' 	=> [ '33', Codevz_Plus::$url . 'assets/img/sep_33.jpg' ],
								'34' 	=> [ '34', Codevz_Plus::$url . 'assets/img/sep_34.jpg' ],
								'35' 	=> [ '35', Codevz_Plus::$url . 'assets/img/sep_35.jpg' ],
								'36' 	=> [ '36', Codevz_Plus::$url . 'assets/img/sep_36.jpg' ],
								'37' 	=> [ '37', Codevz_Plus::$url . 'assets/img/sep_37.jpg' ],
								'38' 	=> [ '38', Codevz_Plus::$url . 'assets/img/sep_38.jpg' ],
								'39' 	=> [ '39', Codevz_Plus::$url . 'assets/img/sep_39.jpg' ],
								'40' 	=> [ '40', Codevz_Plus::$url . 'assets/img/sep_40.jpg' ],
								'41' 	=> [ '41', Codevz_Plus::$url . 'assets/img/sep_41.jpg' ],
								'42' 	=> [ '42', Codevz_Plus::$url . 'assets/img/sep_42.jpg' ],
								'43' 	=> [ '43', Codevz_Plus::$url . 'assets/img/sep_43.jpg' ],
								'44' 	=> [ '44', Codevz_Plus::$url . 'assets/img/sep_44.jpg' ],
								'45' 	=> [ '45', Codevz_Plus::$url . 'assets/img/sep_45.jpg' ],
								'46' 	=> [ '46', Codevz_Plus::$url . 'assets/img/sep_46.jpg' ],
								'47' 	=> [ '47', Codevz_Plus::$url . 'assets/img/sep_47.jpg' ],
								'48' 	=> [ '48', Codevz_Plus::$url . 'assets/img/sep_48.jpg' ],
								'49' 	=> [ '49', Codevz_Plus::$url . 'assets/img/sep_49.jpg' ],
								'50' 	=> [ '50', Codevz_Plus::$url . 'assets/img/sep_50.jpg' ],
								'51' 	=> [ '51', Codevz_Plus::$url . 'assets/img/sep_51.jpg' ],
								'52' 	=> [ '52', Codevz_Plus::$url . 'assets/img/sep_52.jpg' ],
								'53' 	=> [ '53', Codevz_Plus::$url . 'assets/img/sep_53.jpg' ],
								'54' 	=> [ '54', Codevz_Plus::$url . 'assets/img/sep_54.jpg' ],
								'55' 	=> [ '55', Codevz_Plus::$url . 'assets/img/sep_55.jpg' ],
								'56' 	=> [ '56', Codevz_Plus::$url . 'assets/img/sep_56.jpg' ],
								'57' 	=> [ '57', Codevz_Plus::$url . 'assets/img/sep_57.jpg' ],
								'58' 	=> [ '58', Codevz_Plus::$url . 'assets/img/sep_58.jpg' ],
								'59' 	=> [ '59', Codevz_Plus::$url . 'assets/img/sep_59.jpg' ],
								'60' 	=> [ '60', Codevz_Plus::$url . 'assets/img/sep_60.jpg' ],
								'61' 	=> [ '61', Codevz_Plus::$url . 'assets/img/sep_61.jpg' ],
								'62' 	=> [ '62', Codevz_Plus::$url . 'assets/img/sep_62.jpg' ],
								'63' 	=> [ '63', Codevz_Plus::$url . 'assets/img/sep_63.jpg' ],
								'64' 	=> [ '64', Codevz_Plus::$url . 'assets/img/sep_64.jpg' ],
								'65' 	=> [ '65', Codevz_Plus::$url . 'assets/img/sep_65.jpg' ],
							]
						),
						array(
							'id'            => 'hs_transform',
							'type'          => $free ? 'content' : 'select',
							'content' 		=> Codevz_Plus::pro_badge(),
							'title'         => esc_html__( 'Transform', 'codevz-plus' ),
							'options'       => array(
								'' 							=> esc_html__( '~ Select ~', 'codevz-plus' ),
								'cz_sep_top' 				=> esc_html__( 'Top', 'codevz-plus' ),
								'cz_sep_rotatey_top' 		=> esc_html__( 'Top Flip Horizontal', 'codevz-plus' ),
								'cz_sep_bottom' 			=> esc_html__( 'Bottom', 'codevz-plus' ),
								'cz_sep_rotatey_bottom' 	=> esc_html__( 'Bottom Flip Horizontal', 'codevz-plus' ),
							),
							'dependency' 	=> [ 'hs_style', '!=', '' ]
						),
						array(
							'id'    	=> 'hs_sep_width',
							'type'  	=> 'slider',
							'title' 	=> esc_html__( 'Width', 'codevz-plus' ),
							'options'	=> array( 'unit' => '%', 'step' => 1, 'min' => 100, 'max' => 300 ),
							'dependency' 	=> [ 'hs_style', '!=', '' ]
						),
						array(
							'id'    	=> 'hs_sep_height',
							'type'  	=> 'slider',
							'title' 	=> esc_html__( 'Height', 'codevz-plus' ),
							'options'	=> array( 'unit' => 'px', 'step' => 1, 'min' => 10, 'max' => 500 ),
							'dependency' 	=> [ 'hs_style', '!=', '' ]
						),
						array(
							'id'    	=> 'hs_sep_height_tablet',
							'type'  	=> 'slider',
							'title' 	=> esc_html__( 'Height on tablet', 'codevz-plus' ),
							'options'	=> array( 'unit' => 'px', 'step' => 1, 'min' => 10, 'max' => 500 ),
							'dependency' 	=> [ 'hs_style', '!=', '' ]
						),
						array(
							'id'    	=> 'hs_sep_height_mobile',
							'type'  	=> 'slider',
							'title' 	=> esc_html__( 'Height on mobile', 'codevz-plus' ),
							'options'	=> array( 'unit' => 'px', 'step' => 1, 'min' => 10, 'max' => 500 ),
							'dependency' 	=> [ 'hs_style', '!=', '' ]
						),
						array(
							'id'    		=> 'hs_color1',
							'type'  		=> 'color_picker',
							'title' 		=> esc_html__( 'Color', 'codevz-plus' ) . ' 1',
							'dependency' 	=> [ 'hs_style', '!=', '' ]
						),
						array(
							'id'    		=> 'hs_color2',
							'type'  		=> 'color_picker',
							'title' 		=> esc_html__( 'Color', 'codevz-plus' ) . ' 2',
							'dependency' 	=> [ 'hs_style', '!=', '' ]
						),
						array(
							'id'    		=> 'hs_color3',
							'type'  		=> 'color_picker',
							'title' 		=> esc_html__( 'Color', 'codevz-plus' ) . ' 3',
							'dependency' 	=> [ 'hs_style', '!=', '' ]
						),
						array(
							'id'			=> 'hs_relative',
							'type'			=> 'switcher',
							'title'			=> esc_html__( 'With gap?', 'codevz-plus' ),
							'dependency' 	=> [ 'hs_style', '!=', '' ]
						),
						array(
							'id'			=> 'hs_priority',
							'type'			=> 'switcher',
							'title'			=> esc_html__( 'High priority?', 'codevz-plus' ),
							'dependency' 	=> [ 'hs_style', '!=', '' ]
						),
						array(
							'id' 			=> '_css_hs_separator',
							'type' 			=> 'cz_sk',
							'title' 		=> esc_html__( 'Separator', 'codevz-plus' ),
							'button' 		=> esc_html__( 'Separator', 'codevz-plus' ),
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'settings' 		=> array( 'background', 'border' ),
							'selector' 		=> '.xtra-separator-fs .cz_sep2',
							'dependency' 	=> [ 'hs_style', '!=', '' ]
						),
						array(
							'id' 			=> '_css_hs_separator_tablet',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> '.xtra-separator-fs .cz_sep2'
						),
						array(
							'id' 			=> '_css_hs_separator_mobile',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> '.xtra-separator-fs .cz_sep2'
						),
					),
				),

				array(
					'name'   => 'fixed_side_1',
					'title'  => esc_html__( 'Fixed Side', 'codevz-plus' ),
					'fields' => self::row_options( 'fixed_side_1', array('top','middle','bottom') )
				),

				array(
					'name'   => 'header_more',
					'title'  => esc_html__( 'More', 'codevz-plus' ),
					'fields' => array(

						array(
							'id' 			=> '_css_header_container',
							'type' 			=> 'cz_sk',
							'title' 		=> esc_html__( 'Header container', 'codevz-plus' ),
							'button' 		=> esc_html__( 'Header container', 'codevz-plus' ),
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'settings' 		=> array( 'background', 'border' ),
							'selector' 		=> '.page_header'
						),
						array(
							'id' 			=> '_css_header_container_tablet',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> '.page_header'
						),
						array(
							'id' 			=> '_css_header_container_mobile',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> '.page_header'
						),

						array(
							'type'    		=> 'notice',
							'class'   		=> 'info xtra-notice',
							'content' 		=> '<i class="fas fa-caret-down mr8"></i>' . esc_html__( 'Extra Panel', 'codevz-plus' )
						),
						array(
							'id'            => 'hidden_top_bar',
							'type'          => $free ? 'content' : 'select',
							'title'         => esc_html__( 'Extra Panel', 'codevz-plus' ),
							'help' 			=> esc_html__( 'Expand/collapse panel designed to optimize the display of content in limited spaces by means of an “expand/collapse” system.', 'codevz-plus' ),
							'content' 		=> Codevz_Plus::pro_badge(),
							'options'       => Codevz_Plus::$array_pages,
							'edit_link' 	=> true
						),
						array(
							'id'            => 'hidden_top_bar_icon',
							'type'          => 'icon',
							'title'         => esc_html__( 'Icon', 'codevz-plus' ),
							'dependency' 	=> array( 'hidden_top_bar', '!=', '' )
						),
						array(
							'id' 			=> '_css_hidden_top_bar',
							'type' 			=> 'cz_sk',
							'title' 		=> esc_html__( 'Panel', 'codevz-plus' ),
							'button' 		=> esc_html__( 'Panel', 'codevz-plus' ),
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'settings' 		=> array( 'color', 'background' ),
							'selector' 		=> '.hidden_top_bar',
							'dependency' 	=> array( 'hidden_top_bar', '!=', '' )
						),
						array(
							'id' 			=> '_css_hidden_top_bar_tablet',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> '.hidden_top_bar',
						),
						array(
							'id' 			=> '_css_hidden_top_bar_mobile',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> '.hidden_top_bar',
						),
						array(
							'id' 			=> '_css_hidden_top_bar_handle',
							'type' 			=> 'cz_sk',
							'title' 		=> esc_html__( 'Icon', 'codevz-plus' ),
							'button' 		=> esc_html__( 'Icon', 'codevz-plus' ),
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'settings' 		=> array( 'color', 'background' ),
							'selector' 		=> '.hidden_top_bar > i',
							'dependency' 	=> array( 'hidden_top_bar', '!=', '' )
						),
						array(
							'id' 			=> '_css_hidden_top_bar_handle_tablet',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> '.hidden_top_bar > i',
						),
						array(
							'id' 			=> '_css_hidden_top_bar_handle_mobile',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> '.hidden_top_bar > i',
						),

						array(
							'type'    		=> 'notice',
							'class'   		=> 'info xtra-notice',
							'content' 		=> '<i class="fas fa-window-maximize mr8"></i>' . esc_html__( 'Header banner', 'codevz-plus' )
						),
						array(
							'id'            => 'top_banner',
							'type'          => $free ? 'content' : 'select',
							'title'         => esc_html__( 'Top banner', 'codevz-plus' ),
							'content' 		=> Codevz_Plus::pro_badge(),
							'help'          => esc_html__( 'You can create a template and assign it here to show the template content', 'codevz-plus' ),
							'options'       => wp_parse_args( Codevz_Plus::$array_pages, [
								'' 				=> esc_html__( '~ Disable ~', 'codevz-plus' ),
								'simple' 		=> esc_html__( '~ Simple ~', 'codevz-plus' )
							]),
							'edit_link' 	=> true
						),
						array(
							'id'			=> 'top_banner_content',
							'type'			=> 'textarea',
							'title'			=> esc_html__( 'Content', 'codevz-plus' ),
							'dependency' 	=> [ 'top_banner', '==', 'simple' ]
						),
						array(
							'id'			=> 'top_banner_always',
							'type'			=> 'switcher',
							'title'			=> esc_html__( 'Show always?', 'codevz-plus' ),
							'dependency' 	=> [ 'top_banner', '!=', '' ]
						),
						array(
							'id'            => 'top_banner_icon',
							'type'          => 'icon',
							'title'         => esc_html__( 'Icon', 'codevz-plus' ),
							'dependency' 	=> [ 'top_banner', '!=', '' ]
						),
						array(
							'id' 			=> '_css_top_banner',
							'type' 			=> 'cz_sk',
							'title' 		=> esc_html__( 'Top banner', 'codevz-plus' ),
							'button' 		=> esc_html__( 'Top banner', 'codevz-plus' ),
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'settings' 		=> array( 'color', 'background', 'border' ),
							'selector' 		=> '.codevz-top-banner',
							'dependency' 	=> [ 'top_banner', '!=', '' ]
						),
						array(
							'id' 			=> '_css_top_banner_tablet',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> '.codevz-top-banner',
						),
						array(
							'id' 			=> '_css_top_banner_mobile',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> '.codevz-top-banner',
						),
						array(
							'id' 			=> '_css_top_banner_icon',
							'hover_id' 		=> '_css_top_banner_icon_hover',
							'type' 			=> 'cz_sk',
							'title' 		=> esc_html__( 'Icon', 'codevz-plus' ),
							'button' 		=> esc_html__( 'Icon', 'codevz-plus' ),
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'settings' 		=> array( 'color', 'background', 'border' ),
							'selector' 		=> '.codevz-top-banner > i',
							'dependency' 	=> [ 'top_banner', '!=', '' ]
						),
						array(
							'id' 			=> '_css_top_banner_icon_tablet',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> '.codevz-top-banner > i',
						),
						array(
							'id' 			=> '_css_top_banner_icon_mobile',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> '.codevz-top-banner > i',
						),
						array(
							'id' 			=> '_css_top_banner_icon_hover',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> '.codevz-top-banner > i:hover',
						),

					),
				),

			),
		);

		$options[ 'footer' ]   = array(
			'name' 		=> 'footer',
			'title' 	=> esc_html__( 'Footer', 'codevz-plus' ),
			'sections' => array(

				array(
					'name'   => 'footer_elementor',
					'title'  => esc_html__( 'Custom Footer Template', 'codevz-plus' ),
					'fields' => array(
						array(
							'id'    	=> 'footer_elementor',
							'type'  	=> 'select',
							'title' 	=> esc_html__( 'Select Footer', 'codevz-plus' ),
							'help' 		=> esc_html__( 'Create a template or page and assign it as custom template here.', 'codevz-plus' ),
							'options' 	=> Codevz_Plus::$array_pages,
							'edit_link' => true
						),
						array(
							'id'    	=> 'footer_mobile_elementor',
							'type'  	=> $free ? 'content' : 'select',
							'content' 	=> Codevz_Plus::pro_badge(),
							'title' 	=> esc_html__( 'Tablet & mobile', 'codevz-plus' ),
							'options' 	=> Codevz_Plus::$array_pages,
							'edit_link' => true
						),
					),
				),

				array(
					'name'   => 'footer_separator',
					'title'  => esc_html__( 'Footer Separator', 'codevz-plus' ),
					'fields' => array(
						array(
							'id'    	=> 'fs_style',
							'type'  	=> $free ? 'content' : 'codevz_image_select',
							'content' 	=> Codevz_Plus::pro_badge(),
							'title' 	=> esc_html__( 'Separator', 'codevz-plus' ),
							'options' => [
								'' 		=> [ '-',  Codevz_Plus::$url . 'assets/img/off.png' ],
								'1' 	=> [ '1', Codevz_Plus::$url . 'assets/img/sep_1.jpg' ],
								'2' 	=> [ '2', Codevz_Plus::$url . 'assets/img/sep_2.jpg' ],
								'3' 	=> [ '3', Codevz_Plus::$url . 'assets/img/sep_3.jpg' ],
								'4' 	=> [ '4', Codevz_Plus::$url . 'assets/img/sep_4.jpg' ],
								'5' 	=> [ '5', Codevz_Plus::$url . 'assets/img/sep_5.jpg' ],
								'6' 	=> [ '6', Codevz_Plus::$url . 'assets/img/sep_6.jpg' ],
								'7' 	=> [ '7', Codevz_Plus::$url . 'assets/img/sep_7.jpg' ],
								'8' 	=> [ '8', Codevz_Plus::$url . 'assets/img/sep_8.jpg' ],
								'9' 	=> [ '9', Codevz_Plus::$url . 'assets/img/sep_9.jpg' ],
								'10' 	=> [ '10', Codevz_Plus::$url . 'assets/img/sep_10.jpg' ],
								'11' 	=> [ '11', Codevz_Plus::$url . 'assets/img/sep_11.jpg' ],
								'12' 	=> [ '12', Codevz_Plus::$url . 'assets/img/sep_12.jpg' ],
								'13' 	=> [ '13', Codevz_Plus::$url . 'assets/img/sep_13.jpg' ],
								'14' 	=> [ '14', Codevz_Plus::$url . 'assets/img/sep_14.jpg' ],
								'15' 	=> [ '15', Codevz_Plus::$url . 'assets/img/sep_15.jpg' ],
								'16' 	=> [ '16', Codevz_Plus::$url . 'assets/img/sep_16.jpg' ],
								'17' 	=> [ '17', Codevz_Plus::$url . 'assets/img/sep_17.jpg' ],
								'18' 	=> [ '18', Codevz_Plus::$url . 'assets/img/sep_18.jpg' ],
								'19' 	=> [ '19', Codevz_Plus::$url . 'assets/img/sep_19.jpg' ],
								'20' 	=> [ '20', Codevz_Plus::$url . 'assets/img/sep_20.jpg' ],
								'21' 	=> [ '21', Codevz_Plus::$url . 'assets/img/sep_21.jpg' ],
								'22' 	=> [ '22', Codevz_Plus::$url . 'assets/img/sep_22.jpg' ],
								'23' 	=> [ '23', Codevz_Plus::$url . 'assets/img/sep_23.jpg' ],
								'24' 	=> [ '24', Codevz_Plus::$url . 'assets/img/sep_24.jpg' ],
								'25' 	=> [ '25', Codevz_Plus::$url . 'assets/img/sep_25.jpg' ],
								'26' 	=> [ '26', Codevz_Plus::$url . 'assets/img/sep_26.jpg' ],
								'27' 	=> [ '27', Codevz_Plus::$url . 'assets/img/sep_27.jpg' ],
								'28' 	=> [ '28', Codevz_Plus::$url . 'assets/img/sep_28.jpg' ],
								'29' 	=> [ '29', Codevz_Plus::$url . 'assets/img/sep_29.jpg' ],
								'30' 	=> [ '30', Codevz_Plus::$url . 'assets/img/sep_30.jpg' ],
								'31' 	=> [ '31', Codevz_Plus::$url . 'assets/img/sep_31.jpg' ],
								'32' 	=> [ '32', Codevz_Plus::$url . 'assets/img/sep_32.jpg' ],
								'33' 	=> [ '33', Codevz_Plus::$url . 'assets/img/sep_33.jpg' ],
								'34' 	=> [ '34', Codevz_Plus::$url . 'assets/img/sep_34.jpg' ],
								'35' 	=> [ '35', Codevz_Plus::$url . 'assets/img/sep_35.jpg' ],
								'36' 	=> [ '36', Codevz_Plus::$url . 'assets/img/sep_36.jpg' ],
								'37' 	=> [ '37', Codevz_Plus::$url . 'assets/img/sep_37.jpg' ],
								'38' 	=> [ '38', Codevz_Plus::$url . 'assets/img/sep_38.jpg' ],
								'39' 	=> [ '39', Codevz_Plus::$url . 'assets/img/sep_39.jpg' ],
								'40' 	=> [ '40', Codevz_Plus::$url . 'assets/img/sep_40.jpg' ],
								'41' 	=> [ '41', Codevz_Plus::$url . 'assets/img/sep_41.jpg' ],
								'42' 	=> [ '42', Codevz_Plus::$url . 'assets/img/sep_42.jpg' ],
								'43' 	=> [ '43', Codevz_Plus::$url . 'assets/img/sep_43.jpg' ],
								'44' 	=> [ '44', Codevz_Plus::$url . 'assets/img/sep_44.jpg' ],
								'45' 	=> [ '45', Codevz_Plus::$url . 'assets/img/sep_45.jpg' ],
								'46' 	=> [ '46', Codevz_Plus::$url . 'assets/img/sep_46.jpg' ],
								'47' 	=> [ '47', Codevz_Plus::$url . 'assets/img/sep_47.jpg' ],
								'48' 	=> [ '48', Codevz_Plus::$url . 'assets/img/sep_48.jpg' ],
								'49' 	=> [ '49', Codevz_Plus::$url . 'assets/img/sep_49.jpg' ],
								'50' 	=> [ '50', Codevz_Plus::$url . 'assets/img/sep_50.jpg' ],
								'51' 	=> [ '51', Codevz_Plus::$url . 'assets/img/sep_51.jpg' ],
								'52' 	=> [ '52', Codevz_Plus::$url . 'assets/img/sep_52.jpg' ],
								'53' 	=> [ '53', Codevz_Plus::$url . 'assets/img/sep_53.jpg' ],
								'54' 	=> [ '54', Codevz_Plus::$url . 'assets/img/sep_54.jpg' ],
								'55' 	=> [ '55', Codevz_Plus::$url . 'assets/img/sep_55.jpg' ],
								'56' 	=> [ '56', Codevz_Plus::$url . 'assets/img/sep_56.jpg' ],
								'57' 	=> [ '57', Codevz_Plus::$url . 'assets/img/sep_57.jpg' ],
								'58' 	=> [ '58', Codevz_Plus::$url . 'assets/img/sep_58.jpg' ],
								'59' 	=> [ '59', Codevz_Plus::$url . 'assets/img/sep_59.jpg' ],
								'60' 	=> [ '60', Codevz_Plus::$url . 'assets/img/sep_60.jpg' ],
								'61' 	=> [ '61', Codevz_Plus::$url . 'assets/img/sep_61.jpg' ],
								'62' 	=> [ '62', Codevz_Plus::$url . 'assets/img/sep_62.jpg' ],
								'63' 	=> [ '63', Codevz_Plus::$url . 'assets/img/sep_63.jpg' ],
								'64' 	=> [ '64', Codevz_Plus::$url . 'assets/img/sep_64.jpg' ],
								'65' 	=> [ '65', Codevz_Plus::$url . 'assets/img/sep_65.jpg' ],
							]
						),
						array(
							'id'            => 'fs_transform',
							'type'          => $free ? 'content' : 'select',
							'content' 		=> Codevz_Plus::pro_badge(),
							'title'         => esc_html__( 'Transform', 'codevz-plus' ),
							'options'       => array(
								'' 							=> esc_html__( '~ Select ~', 'codevz-plus' ),
								'cz_sep_top' 				=> esc_html__( 'Top', 'codevz-plus' ),
								'cz_sep_rotatey_top' 		=> esc_html__( 'Top Flip Horizontal', 'codevz-plus' ),
								'cz_sep_bottom' 			=> esc_html__( 'Bottom', 'codevz-plus' ),
								'cz_sep_rotatey_bottom' 	=> esc_html__( 'Bottom Flip Horizontal', 'codevz-plus' ),
							),
							'dependency' 	=> [ 'fs_style', '!=', '' ]
						),
						array(
							'id'    	=> 'fs_sep_width',
							'type'  	=> 'slider',
							'title' 	=> esc_html__( 'Width', 'codevz-plus' ),
							'options'	=> array( 'unit' => '%', 'step' => 1, 'min' => 100, 'max' => 300 ),
							'dependency' 	=> [ 'fs_style', '!=', '' ]
						),
						array(
							'id'    	=> 'fs_sep_height',
							'type'  	=> 'slider',
							'title' 	=> esc_html__( 'Height', 'codevz-plus' ),
							'options'	=> array( 'unit' => 'px', 'step' => 1, 'min' => 10, 'max' => 500 ),
							'dependency' 	=> [ 'fs_style', '!=', '' ]
						),
						array(
							'id'    	=> 'fs_sep_height_tablet',
							'type'  	=> 'slider',
							'title' 	=> esc_html__( 'Height on tablet', 'codevz-plus' ),
							'options'	=> array( 'unit' => 'px', 'step' => 1, 'min' => 10, 'max' => 500 ),
							'dependency' 	=> [ 'fs_style', '!=', '' ]
						),
						array(
							'id'    	=> 'fs_sep_height_mobile',
							'type'  	=> 'slider',
							'title' 	=> esc_html__( 'Height on mobile', 'codevz-plus' ),
							'options'	=> array( 'unit' => 'px', 'step' => 1, 'min' => 10, 'max' => 500 ),
							'dependency' 	=> [ 'fs_style', '!=', '' ]
						),
						array(
							'id'    		=> 'fs_color1',
							'type'  		=> 'color_picker',
							'title' 		=> esc_html__( 'Color', 'codevz-plus' ) . ' 1',
							'dependency' 	=> [ 'fs_style', '!=', '' ]
						),
						array(
							'id'    		=> 'fs_color2',
							'type'  		=> 'color_picker',
							'title' 		=> esc_html__( 'Color', 'codevz-plus' ) . ' 2',
							'dependency' 	=> [ 'fs_style', '!=', '' ]
						),
						array(
							'id'    		=> 'fs_color3',
							'type'  		=> 'color_picker',
							'title' 		=> esc_html__( 'Color', 'codevz-plus' ) . ' 3',
							'dependency' 	=> [ 'fs_style', '!=', '' ]
						),
						array(
							'id'			=> 'fs_relative',
							'type'			=> 'switcher',
							'title'			=> esc_html__( 'With gap?', 'codevz-plus' ),
							'dependency' 	=> [ 'fs_style', '!=', '' ]
						),
						array(
							'id'			=> 'fs_priority',
							'type'			=> 'switcher',
							'title'			=> esc_html__( 'High priority?', 'codevz-plus' ),
							'dependency' 	=> [ 'fs_style', '!=', '' ]
						),
						array(
							'id' 			=> '_css_header_separator',
							'type' 			=> 'cz_sk',
							'title' 		=> esc_html__( 'Separator', 'codevz-plus' ),
							'button' 		=> esc_html__( 'Separator', 'codevz-plus' ),
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'settings' 		=> array( 'background', 'border' ),
							'selector' 		=> '.xtra-separator-fs .cz_sep2',
							'dependency' 	=> [ 'fs_style', '!=', '' ]
						),
						array(
							'id' 			=> '_css_fs_tablet',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> '.xtra-separator-fs .cz_sep2'
						),
						array(
							'id' 			=> '_css_fs_mobile',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> '.xtra-separator-fs .cz_sep2'
						),
					),
				),

				array(
					'name'   => 'footer_1',
					'title'  => esc_html__( 'Footer Top Bar', 'codevz-plus' ),
					'fields' => self::row_options( 'footer_1' )
				),
				array(
					'name'   => 'footer_widgets',
					'title'  => esc_html__( 'Footer Widgets', 'codevz-plus' ),
					'fields' => array(
						array(
							'id' 	=> 'footer_layout',
							'type' 	=> 'select',
							'title' => esc_html__( 'Columns', 'codevz-plus' ),
							'help' 	=> esc_html__( 'Manage footer widgets from Theme Options > Widgets', 'codevz-plus' ),
							'options' => array(
								'' 					=> esc_html__( '~ Select ~', 'codevz-plus' ),
								's12'				=> '1/1',
								's6,s6'				=> '1/2 1/2',
								's4,s8'				=> '1/3 2/3',
								's8,s4'				=> '2/3 1/3',
								's3,s9'				=> '1/4 3/4',
								's9,s3'				=> '3/4 1/4',
								's4,s4,s4'			=> '1/3 1/3 1/3',
								's3,s6,s3'			=> '1/4 2/4 1/4',
								's3,s3,s6'			=> '1/4 1/4 2/4',
								's6,s3,s3'			=> '2/4 1/4 1/4',
								's2,s2,s8'			=> '1/6 1/6 4/6',
								's2,s8,s2'			=> '1/6 4/6 1/6',
								's8,s2,s2'			=> '4/6 1/6 1/6',
								's3,s3,s3,s3'		=> '1/4 1/4 1/4 1/4',
								's55,s55,s55,s55,s55' => '1/5 1/5 1/5 1/5 1/5',
								's6,s2,s2,s2'		=> '3/6 1/6 1/6 1/6',
								's2,s2,s2,s6'		=> '1/6 1/6 1/6 3/6',
								's2,s2,s2,s2,s4'	=> '1/6 1/6 1/6 1/6 2/6',
								's4,s2,s2,s2,s2'	=> '2/6 1/6 1/6 1/6 1/6',
								's2,s2,s4,s2,s2'	=> '1/6 1/6 2/6 1/6 1/6',
								's2,s2,s2,s2,s2,s2'	=> '1/6 1/6 1/6 1/6 1/6 1/6',
							),
						),
						array(
							'id' 			=> '_css_footer',
							'type' 			=> 'cz_sk',
							'title' 		=> esc_html__( 'Container', 'codevz-plus' ),
							'button' 		=> esc_html__( 'Container', 'codevz-plus' ),
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'settings' 		=> array( 'background', 'border' ),
							'selector' 		=> '.cz_middle_footer',
							'dependency' 	=> array( 'footer_layout', '!=', '' )
						),
						array(
							'id' 			=> '_css_footer_tablet',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> '.cz_middle_footer',
						),
						array(
							'id' 			=> '_css_footer_mobile',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> '.cz_middle_footer',
						),
						array(
							'id' 			=> '_css_footer_row',
							'type' 			=> 'cz_sk',
							'title' 		=> esc_html__( 'Row Inner', 'codevz-plus' ),
							'button' 		=> esc_html__( 'Row Inner', 'codevz-plus' ),
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'settings' 		=> array( 'width', 'background', 'border' ),
							'selector' 		=> '.cz_middle_footer > .row',
							'dependency' 	=> array( 'footer_layout', '!=', '' )
						),
						array(
							'id' 			=> '_css_footer_row_tablet',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> '.cz_middle_footer > .row',
						),
						array(
							'id' 			=> '_css_footer_row_mobile',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> '.cz_middle_footer > .row',
						),
						array(
							'id' 			=> '_css_footer_widget',
							'type' 			=> 'cz_sk',
							'title' 		=> esc_html__( 'Widgets', 'codevz-plus' ),
							'button' 		=> esc_html__( 'Widgets', 'codevz-plus' ),
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'settings' 		=> array( 'color', 'background', 'border' ),
							'selector' 		=> '.footer_widget',
							'dependency' 	=> array( 'footer_layout', '!=', '' )
						),
						array(
							'id' 			=> '_css_footer_widget_tablet',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> '.footer_widget',
						),
						array(
							'id' 			=> '_css_footer_widget_mobile',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> '.footer_widget',
						),
						array(
							'id' 			=> '_css_footer_widget_headlines',
							'type' 			=> 'cz_sk',
							'title' 		=> esc_html__( 'Titles', 'codevz-plus' ),
							'button' 		=> esc_html__( 'Titles', 'codevz-plus' ),
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'settings' 		=> array( 'color', 'background', 'font-size', 'line-height', 'border' ),
							'selector' 		=> '.footer_widget > .codevz-widget-title, footer .widget_block > div > div > h2',
							'dependency' 	=> array( 'footer_layout', '!=', '' )
						),
						array(
							'id' 			=> '_css_footer_widget_headlines_tablet',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> '.footer_widget > .codevz-widget-title, footer .widget_block > div > div > h2',
						),
						array(
							'id' 			=> '_css_footer_widget_headlines_mobile',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> '.footer_widget > .codevz-widget-title, footer .widget_block > div > div > h2',
						),
						array(
							'id' 			=> '_css_footer_widget_headlines_before',
							'type'          => $free ? 'cz_sk_free' : 'cz_sk',
							'title' 		=> esc_html__( 'Title shape', 'codevz-plus' ) . ' 1',
							'button' 		=> esc_html__( 'Title shape', 'codevz-plus' ) . ' 1',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'settings' 		=> array( 'background', 'width', 'height', 'border', 'top', 'left', 'bottom', 'right' ),
							'selector' 		=> '.footer_widget > .codevz-widget-title:before, footer .widget_block > div > div > h2:before',
							'dependency' 	=> array( 'footer_layout', '!=', '' )
						),
						array(
							'id' 			=> '_css_footer_widget_headlines_before_tablet',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> '.footer_widget > .codevz-widget-title:before, footer .widget_block > div > div > h2:before',
						),
						array(
							'id' 			=> '_css_footer_widget_headlines_before_mobile',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> '.footer_widget > .codevz-widget-title:before, footer .widget_block > div > div > h2:before',
						),
						array(
							'id' 			=> '_css_footer_widget_headlines_after',
							'type'          => $free ? 'cz_sk_free' : 'cz_sk',
							'title' 		=> esc_html__( 'Title shape', 'codevz-plus' ) . ' 2',
							'button' 		=> esc_html__( 'Title shape', 'codevz-plus' ) . ' 2',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'settings' 		=> array( 'background', 'width', 'height', 'border', 'top', 'left', 'bottom', 'right' ),
							'selector' 		=> '.footer_widget > .codevz-widget-title:after, footer .widget_block > div > div > h2:after',
							'dependency' 	=> array( 'footer_layout', '!=', '' )
						),
						array(
							'id' 			=> '_css_footer_widget_headlines_after_tablet',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> '.footer_widget > .codevz-widget-title:after, footer .widget_block > div > div > h2:after',
						),
						array(
							'id' 			=> '_css_footer_widget_headlines_after_mobile',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> '.footer_widget > .codevz-widget-title:after, footer .widget_block > div > div > h2:after',
						),
						array(
							'id' 			=> '_css_footer_a',
							'hover_id' 		=> '_css_footer_a_hover',
							'type'          => $free ? 'cz_sk_free' : 'cz_sk',
							'title' 		=> esc_html__( 'Links', 'codevz-plus' ),
							'button' 		=> esc_html__( 'Links', 'codevz-plus' ),
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'settings' 		=> array( 'color', 'font-style' ),
							'selector' 		=> '.cz_middle_footer a',
							'dependency' 	=> array( 'footer_layout', '!=', '' )
						),
						array(
							'id' 			=> '_css_footer_a_hover',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> '.cz_middle_footer a:hover',
						),
					),
				),
				array(
					'name'   => 'footer_2',
					'title'  => esc_html__( 'Footer Bottom Bar', 'codevz-plus' ),
					'fields' => self::row_options( 'footer_2' )
				),
				array(
					'name'   => 'footer_more',
					'title'  => esc_html__( 'More', 'codevz-plus' ),
					'fields' => array(
						array(
							'id' 			=> 'fixed_footer',
							'type' 			=> $free ? 'content' : 'switcher',
							'content' 		=> Codevz_Plus::pro_badge(),
							'title' 		=> esc_html__( 'Fixed footer', 'codevz-plus' ),
							'help'			=> esc_html__( "To ensure the fixed footer's visibility, set the body background color. Navigate to General > Colors > Body", 'codevz-plus' ),
						),
						array(
							'id'    		=> 'backtotop',
							'type'  		=> 'icon',
							'title' 		=> esc_html__( 'Back to top', 'codevz-plus' ),
							'help' 			=> esc_html__( 'The sticky back to top button is a helpful navigation element that helps users get back to the top of the web page they’re viewing.', 'codevz-plus' ),
							'default'		=> 'fa fa-angle-up',
							'setting_args' 	=> [ 'transport' => 'postMessage' ]
						),
						array(
							'id' 			=> 'cf7_beside_backtotop',
							'type' 			=> $free ? 'content' : 'select',
							'content' 		=> Codevz_Plus::pro_badge(),
							'title' 		=> esc_html__( 'Quick contact', 'codevz-plus' ),
							'help' 			=> esc_html__( 'Select the page that contains contact form element.', 'codevz-plus' ),
							'options'       => wp_parse_args( Codevz_Plus::$array_pages, [
								'' 				=> esc_html__( '~ Disable ~', 'codevz-plus' ),
								'link' 			=> esc_html__( '~ Direct Link ~', 'codevz-plus' )
							]),
							'edit_link' 	=> true
						),
						array(
							'id'    		=> 'cf7_beside_backtotop_link',
							'type'  		=> 'text',
							'title' 		=> esc_html__( 'Direct Link', 'codevz-plus' ),
							'dependency' 	=> array( 'cf7_beside_backtotop', '==', 'link' )
						),
						array(
							'id'    		=> 'cf7_beside_backtotop_icon',
							'type'  		=> 'icon',
							'title' 		=> esc_html__( 'Contact Icon', 'codevz-plus' ),
							'default'		=> 'fa fa-envelope-o',
							'dependency' => array( 'cf7_beside_backtotop', '!=', '' ),
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
						),
						array(
							'type' 			=> 'notice',
							'class' 		=> 'info',
							'content' 		=> '<i class="fas fa-brush mr8"></i>' . esc_html__( 'Styling', 'codevz-plus' )
						),
						array(
							'id' 			=> '_css_overal_footer',
							'type' 			=> 'cz_sk',
							'title' 		=> esc_html__( 'Footer', 'codevz-plus' ),
							'button' 		=> esc_html__( 'Footer', 'codevz-plus' ),
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'settings' 		=> array( 'background', 'border' ),
							'selector' 		=> '.page_footer'
						),
						array(
							'id' 			=> '_css_overal_footer_tablet',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> '.page_footer'
						),
						array(
							'id' 			=> '_css_overal_footer_mobile',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> '.page_footer'
						),
						array(
							'id' 			=> '_css_backtotop',
							'hover_id' 		=> '_css_backtotop_hover',
							'type' 			=> 'cz_sk',
							'title' 		=> esc_html__( 'Back to top', 'codevz-plus' ),
							'button' 		=> esc_html__( 'Back to top', 'codevz-plus' ),
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'settings' 		=> array( 'color', 'background', 'font-size', 'border' ),
							'selector' 		=> 'i.backtotop'
						),
						array(
							'id' 			=> '_css_backtotop_tablet',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> 'i.backtotop'
						),
						array(
							'id' 			=> '_css_backtotop_mobile',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> 'i.backtotop'
						),
						array(
							'id' 			=> '_css_backtotop_hover',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> 'i.backtotop:hover'
						),
						array(
							'id' 			=> '_css_cf7_beside_backtotop_container',
							'type' 			=> 'cz_sk',
							'title' 		=> esc_html__( 'Contact', 'codevz-plus' ),
							'button' 		=> esc_html__( 'Contact', 'codevz-plus' ),
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'settings' 		=> array( 'color', 'background', 'border' ),
							'selector' 		=> 'div.fixed_contact',
							'dependency' 	=> array( 'cf7_beside_backtotop', '!=', '' ),
						),
						array(
							'id' 			=> '_css_cf7_beside_backtotop',
							'hover_id' 		=> '_css_cf7_beside_backtotop_hover',
							'type' 			=> 'cz_sk',
							'title' 		=> esc_html__( 'Contact Icon', 'codevz-plus' ),
							'button' 		=> esc_html__( 'Contact Icon', 'codevz-plus' ),
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'settings' 		=> array( 'color', 'background', 'font-size', 'border' ),
							'selector' 		=> 'i.fixed_contact',
							'dependency' 	=> array( 'cf7_beside_backtotop', '!=', '' ),
						),
						array(
							'id' 			=> '_css_cf7_beside_backtotop_hover',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> 'i.fixed_contact:hover,i.fixed_contact_active',
						),
					),
				),
			),
		);

		$options[ 'posts' ]   = array(
			'name' 		=> 'posts',
			'title' 	=> esc_html__( 'Blog', 'codevz-plus' ),
			'sections' => array(

				array(
					'name'   => 'blog_settings',
					'title'  => esc_html__( 'Blog Settings', 'codevz-plus' ),
					'fields' => array(

						array(
							'id' 			=> 'layout_post',
							'type' 			=> 'codevz_image_select',
							'title' 		=> esc_html__( 'Sidebar', 'codevz-plus' ),
							'help'  		=> esc_html__( 'Sidebar position for archive and single posts', 'codevz-plus' ),
							'options' 		=> [
								'1' 			=> [ esc_html__( '~ Default ~', 'codevz-plus' ) 		, Codevz_Plus::$url . 'assets/img/sidebar-0.png' ],
								'ws' 			=> [ esc_html__( 'No Sidebar', 'codevz-plus' ) 			, Codevz_Plus::$url . 'assets/img/off.png' ],
								'bpnp' 			=> [ esc_html__( 'Fullwidth', 'codevz-plus' ) 			, Codevz_Plus::$url . 'assets/img/sidebar-2.png' ],
								'center'		=> [ esc_html__( 'Center Mode', 'codevz-plus' ) 			, Codevz_Plus::$url . 'assets/img/sidebar-13.png' ],
								'right' 		=> [ esc_html__( 'Right Sidebar', 'codevz-plus' ) 		, Codevz_Plus::$url . 'assets/img/sidebar-3.png' ],
								'right-s' 		=> [ esc_html__( 'Right Sidebar Small', 'codevz-plus' ) 	, Codevz_Plus::$url . 'assets/img/sidebar-4.png' ],
								'left' 			=> [ esc_html__( 'Left Sidebar', 'codevz-plus' ) 		, Codevz_Plus::$url . 'assets/img/sidebar-5.png' ],
								'left-s' 		=> [ esc_html__( 'Left Sidebar Small', 'codevz-plus' ) 	, Codevz_Plus::$url . 'assets/img/sidebar-6.png' ],
								'both-side' 	=> [ esc_html__( 'Both Sidebar', 'codevz-plus' ) . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ) 		, Codevz_Plus::$url . 'assets/img/sidebar-7.png' ],
								'both-side2' 	=> [ esc_html__( 'Both Sidebar Small', 'codevz-plus' ) . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ) 	, Codevz_Plus::$url . 'assets/img/sidebar-8.png' ],
								'both-right' 	=> [ esc_html__( 'Both Sidebar Right', 'codevz-plus' ) . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ) 	, Codevz_Plus::$url . 'assets/img/sidebar-9.png' ],
								'both-right2' 	=> [ esc_html__( 'Both Sidebar Right', 'codevz-plus' ) . ' 2' . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ) , Codevz_Plus::$url . 'assets/img/sidebar-10.png' ],
								'both-left' 	=> [ esc_html__( 'Both Sidebar Left', 'codevz-plus' ) . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ) 	, Codevz_Plus::$url . 'assets/img/sidebar-11.png' ],
								'both-left2' 	=> [ esc_html__( 'Both Sidebar Left', 'codevz-plus' ) . ' 2' . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' )  , Codevz_Plus::$url . 'assets/img/sidebar-12.png' ],
							],
							'default' 		=> 'right',
							'attributes' 	=> [ 'data-depend-id' => 'layout_post' ]
						),
						array(
							'id' 			=> 'template_style',
							'type' 			=> 'codevz_image_select',
							'title' 		=> esc_html__( 'Template', 'codevz-plus' ),
							'help'  		=> esc_html__( 'Archive, tag and category pages.', 'codevz-plus' ),
							'options' 		=> [
								'1' 			=> [ esc_html__( 'Template', 'codevz-plus' ) . ' 1' 	, Codevz_Plus::$url . 'assets/img/posts-1.png' ],
								'2' 			=> [ esc_html__( 'Template', 'codevz-plus' ) . ' 2' 	, Codevz_Plus::$url . 'assets/img/posts-2.png' ],
								'6' 			=> [ esc_html__( 'Template', 'codevz-plus' ) . ' 6' 	, Codevz_Plus::$url . 'assets/img/posts-1-2.png' ],
								'3' 			=> [ esc_html__( 'Template', 'codevz-plus' ) . ' 3' 	, Codevz_Plus::$url . 'assets/img/posts-3.png' ],
								'4' 			=> [ esc_html__( 'Template', 'codevz-plus' ) . ' 4' 	, Codevz_Plus::$url . 'assets/img/posts-4.png' ],
								'5' 			=> [ esc_html__( 'Template', 'codevz-plus' ) . ' 5' 	, Codevz_Plus::$url . 'assets/img/posts-5.png' ],
								'7' 			=> [ esc_html__( 'Template', 'codevz-plus' ) . ' 7' 	, Codevz_Plus::$url . 'assets/img/posts-7.png' ],
								'8' 			=> [ esc_html__( 'Template', 'codevz-plus' ) . ' 8' 	, Codevz_Plus::$url . 'assets/img/posts-8.png' ],
								'9' 			=> [ esc_html__( 'Template', 'codevz-plus' ) . ' 9' 	, Codevz_Plus::$url . 'assets/img/posts-9.png' ],
								'10' 			=> [ esc_html__( 'Template', 'codevz-plus' ) . ' 10' , Codevz_Plus::$url . 'assets/img/posts-10.png' ],
								'11' 			=> [ esc_html__( 'Template', 'codevz-plus' ) . ' 11' , Codevz_Plus::$url . 'assets/img/posts-11.png' ],
								'12' 			=> [ esc_html__( 'Template', 'codevz-plus' ) . ' 12' , Codevz_Plus::$url . 'assets/img/posts-12.png' ],
								'13' 			=> [ esc_html__( 'Template', 'codevz-plus' ) . ' 13' , Codevz_Plus::$url . 'assets/img/posts-13.png' ],
								'14' 			=> [ esc_html__( 'Template', 'codevz-plus' ) . ' 14' , Codevz_Plus::$url . 'assets/img/posts-14.png' ],
								'x' 			=> [ esc_html__( 'Custom Template', 'codevz-plus' ) . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ) 		, Codevz_Plus::$url . 'assets/img/posts-x.png' ],
							],
							'default' 		=> '1',
							'attributes' 	=> [ 'data-depend-id' => 'template_style' ]
						),
						array(
							'id'    		=> 'template_post',
							'type'   		=> $free ? 'content' : 'select',
							'content' 		=> Codevz_Plus::pro_badge(),
							'title'   		=> esc_html__( 'Custom Page', 'codevz-plus' ),
							'options'   	=> Codevz_Plus::$array_pages,
							'edit_link' 	=> true,
							'dependency'  	=> array( 'template_style', '==', 'x' ),
						),
						array(
							'id'    	=> 'posts_per_page',
							'type'  	=> 'slider',
							'title' 	=> esc_html__( 'Posts per page', 'codevz-plus' ),
							'options'	=> array( 'unit' => '', 'step' => 1, 'min' => -1, 'max' => 100 ),
							'default' 	=> get_option( 'posts_per_page' )
						),
						array(
							'id'    	=> 'posts_archive_desc_after',
							'type'  	=> $free ? 'content' : 'switcher',
							'content' 	=> Codevz_Plus::pro_badge(),
							'title' 	=> esc_html__( 'Description after posts', 'codevz-plus' ),
							'help' 		=> esc_html__( 'Displaying category and tags archive description after posts list', 'codevz-plus' )
						),
						array(
							'id'    		=> 'posts_image_size',
							'type'   		=> $free ? 'content' : 'select',
							'content' 		=> Codevz_Plus::pro_badge(),
							'title'   		=> esc_html__( 'Image size', 'codevz-plus' ),
							'options'   	=> $image_sizes,
							'dependency'  	=> array( 'template_style', '!=', 'x' )
						),
						array(
							'id'    		=> '2x_height_image',
							'type'  		=> $free ? 'content' : 'switcher',
							'content' 		=> Codevz_Plus::pro_badge(),
							'title' 		=> esc_html__( '2x Height Image', 'codevz-plus' ),
							'help' 			=> esc_html__( 'Enlarge the post thumbnails to a size larger than their current dimensions', 'codevz-plus' ),
							'dependency'	=> array( 'template_style|template_style|posts_image_size', '!=|!=|==', 'x|3|' )
						),
						array(
							'id'          => 'hover_icon_icon_post',
							'type'        => 'icon',
							'title'       => esc_html__('Hover Icon', 'codevz-plus' ),
							'default'	  => 'fa czico-109-link-symbol-1',
							'dependency'  	=> array( 'template_style', '!=', 'x' ),
						),
						array(
							'id' 			=> 'default_svg_post',
							'type' 			=> $free ? 'content' : 'switcher',
							'content' 		=> Codevz_Plus::pro_badge(),
							'title' 		=> esc_html__('Placeholder', 'codevz-plus' ),
							'help' 			=> esc_html__('Displaying an SVG cover for posts lacking a featured image', 'codevz-plus' ),
							'dependency' 	=> array( 'template_style', '!=', 'x' ),
						),
						array(
							'id'    		=> 'post_excerpt',
							'type'  		=> 'slider',
							'title'   		=> esc_html__( 'Excerpt', 'codevz-plus' ),
							'help' 	  		=> esc_html__( 'If you want show full content set -1', 'codevz-plus' ),
							'options'		=> array( 'unit' => '', 'step' => 1, 'min' => -1, 'max' => 50 ),
							'default' 		=> '20',
							'dependency' 	=> array( 'template_style|template_style|template_style|template_style', '!=|!=|!=|!=', 'x|12|13|14' )
						),
						array(
							'id' 			=> 'post_excerpt_type',
							'type' 			=> $free ? 'content' : 'select',
							'content' 		=> Codevz_Plus::pro_badge(),
							'title' 		=> esc_html__( 'Excerpt by', 'codevz-plus' ),
							'help' 			=> esc_html__( 'Excerpt by post words or characters.', 'codevz-plus' ),
							'options' 		=> [
								'' 			=> esc_html__( 'Words', 'codevz-plus' ),
								'2' 		=> esc_html__( 'Characters', 'codevz-plus' ),
							],
							'dependency'  => array( 'template_style|template_style|template_style|template_style', '!=|!=|!=|!=', 'x|12|13|14' )
						),
						array(
							'id'    	=> 'post_excerpt_dots',
							'type'  	=> 'text',
							'title'   	=> esc_html__( 'Excerpt Dots', 'codevz-plus' ),
							'default' 	=> ' ... ',
							'dependency'  => array( 'template_style|template_style|template_style|template_style', '!=|!=|!=|!=', 'x|12|13|14' )
						),
						array(
							'id'          => 'readmore_icon',
							'type'        => 'icon',
							'title'       => esc_html__( 'Read More', 'codevz-plus' ),
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'default'	  => 'fa fa-angle-right',
							'dependency'  => array( 'template_style|template_style|template_style|template_style|post_excerpt', '!=|!=|!=|!=|!=', 'x|12|13|14|-1' )
						),
						array(
							'id'          	=> 'readmore',
							'type'        	=> 'text',
							'title'       	=> esc_html__( 'Read More', 'codevz-plus' ),
							'default'	    => 'Read More',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'dependency'  	=> [ 'post_excerpt|template_style', '!=|!=', '-1|x' ]
						),
						array(
							'id'          	=> 'not_found',
							'type'        	=> 'text',
							'title'       	=> esc_html__( 'Not found', 'codevz-plus' ),
							'default'	  	=> esc_html__( 'Not found!', 'codevz-plus' ),
							'setting_args' 	=> [ 'transport' => 'postMessage' ]
						),
					),
				),

				array(
					'name'   => 'blog_styles',
					'title'  => esc_html__( 'Blog Styling', 'codevz-plus' ),
					'fields' => array(
						array(
							'id' 			=> '_css_posts_container',
							'type' 			=> 'cz_sk',
							'title' 		=> esc_html__( 'Container', 'codevz-plus' ),
							'button' 		=> esc_html__( 'Container', 'codevz-plus' ),
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'settings' 		=> array( 'background', 'border' ),
							'selector' 		=> '.cz-cpt-post .cz_posts_container',
						),
						array(
							'id' 			=> '_css_posts_container_tablet',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> '.cz-cpt-post .cz_posts_container',
						),
						array(
							'id' 			=> '_css_posts_container_mobile',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> '.cz-cpt-post .cz_posts_container',
						),
						array(
							'id' 			=> '_css_sticky_post',
							'type' 			=> 'cz_sk',
							'title' 		=> esc_html__( 'Sticky Post', 'codevz-plus' ),
							'button' 		=> esc_html__( 'Sticky Post', 'codevz-plus' ),
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'settings' 		=> array( 'background', 'border' ),
							'selector' 		=> '.cz_default_loop.sticky > div',
							'dependency' 	=> [ 'xxx', '==', 'xxx' ]
						),
						array(
							'id' 			=> '_css_sticky_post_mobile',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> '.cz_default_loop.sticky > div',
						),
						array(
							'id' 			=> '_css_overall_post',
							'hover_id' 		=> '_css_overall_post_hover',
							'type' 			=> 'cz_sk',
							'title' 		=> esc_html__( 'Posts', 'codevz-plus' ),
							'button' 		=> esc_html__( 'Posts', 'codevz-plus' ),
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'settings' 		=> array( 'background', 'border' ),
							'selector' 		=> '.cz-cpt-post .cz_default_loop > div',
						),
						array(
							'id' 			=> '_css_overall_post_tablet',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> '.cz-cpt-post .cz_default_loop > div',
						),
						array(
							'id' 			=> '_css_overall_post_mobile',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> '.cz-cpt-post .cz_default_loop > div',
						),
						array(
							'id' 			=> '_css_overall_post_hover',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> '.cz-cpt-post .cz_default_loop:hover > div',
						),
						array(
							'id' 			=> '_css_post_hover_icon',
							'hover_id' 		=> '_css_post_hover_icon_hover',
							'type' 			=> 'cz_sk',
							'title' 		=> esc_html__( 'Icon', 'codevz-plus' ),
							'button' 		=> esc_html__( 'Icon', 'codevz-plus' ),
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'settings' 		=> array( 'color', 'background', 'border' ),
							'selector' 		=> '.cz-cpt-post article .cz_post_icon',
						),
						array(
							'id' 			=> '_css_post_hover_icon_tablet',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> '.cz-cpt-post article .cz_post_icon',
						),
						array(
							'id' 			=> '_css_post_hover_icon_mobile',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> '.cz-cpt-post article .cz_post_icon',
						),
						array(
							'id' 			=> '_css_post_hover_icon_hover',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> '.cz-cpt-post article .cz_post_icon:hover',
						),
						array(
							'id' 			=> '_css_post_image',
							'hover_id' 		=> '_css_post_image_hover',
							'type' 			=> 'cz_sk',
							'title' 		=> esc_html__( 'Image', 'codevz-plus' ),
							'button' 		=> esc_html__( 'Image', 'codevz-plus' ),
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'settings' 		=> array( 'opacity', 'background', 'border' ),
							'selector' 		=> '.cz-cpt-post .cz_post_image, .cz-cpt-post .cz_post_svg',
						),
						array(
							'id' 			=> '_css_post_image_tablet',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> '.cz-cpt-post .cz_post_image, .cz-cpt-post .cz_post_svg',
						),
						array(
							'id' 			=> '_css_post_image_mobile',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> '.cz-cpt-post .cz_post_image, .cz-cpt-post .cz_post_svg',
						),
						array(
							'id' 			=> '_css_post_image_hover',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> '.cz-cpt-post article:hover .cz_post_image,.cz-cpt-post article:hover .cz_post_svg',
						),
						array(
							'id' 			=> '_css_post_con',
							'hover_id' 		=> '_css_post_con_hover',
							'type' 			=> 'cz_sk',
							'title' 		=> esc_html__( 'Content', 'codevz-plus' ),
							'button' 		=> esc_html__( 'Content', 'codevz-plus' ),
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'settings' 		=> array( 'color', 'background', 'border' ),
							'selector' 		=> '.cz-cpt-post .cz_default_loop .cz_post_con',
						),
						array(
							'id' 			=> '_css_post_con_tablet',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> '.cz-cpt-post .cz_default_loop .cz_post_con',
						),
						array(
							'id' 			=> '_css_post_con_mobile',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> '.cz-cpt-post .cz_default_loop .cz_post_con',
						),
						array(
							'id' 			=> '_css_post_con_hover',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> '.cz-cpt-post .cz_default_loop article:hover .cz_post_con',
						),
						array(
							'id' 			=> '_css_post_title',
							'hover_id' 		=> '_css_post_title_hover',
							'type' 			=> 'cz_sk',
							'title' 		=> esc_html__( 'Title', 'codevz-plus' ),
							'button' 		=> esc_html__( 'Title', 'codevz-plus' ),
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'settings' 		=> array( 'color', 'background', 'font-size', 'line-height', 'border' ),
							'selector' 		=> '.cz-cpt-post .cz_default_loop .cz_post_title h3',
						),
						array(
							'id' 			=> '_css_post_title_tablet',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> '.cz-cpt-post .cz_default_loop .cz_post_title h3',
						),
						array(
							'id' 			=> '_css_post_title_mobile',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> '.cz-cpt-post .cz_default_loop .cz_post_title h3',
						),
						array(
							'id' 			=> '_css_post_title_hover',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> '.cz-cpt-post .cz_default_loop .cz_post_title h3:hover',
						),

						array(
							'id' 			=> '_css_readmore',
							'hover_id' 		=> '_css_readmore_hover',
							'type' 			=> 'cz_sk',
							'title' 		=> esc_html__( 'Read more', 'codevz-plus' ),
							'button' 		=> esc_html__( 'Read more', 'codevz-plus' ),
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'settings' 		=> array( 'float', 'color', 'background', 'font-size', 'border' ),
							'selector' 		=> '.cz-cpt-post .cz_readmore, .cz-cpt-post .more-link'
						),
						array(
							'id' 			=> '_css_readmore_tablet',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> '.cz-cpt-post .cz_readmore, .cz-cpt-post .more-link'
						),
						array(
							'id' 			=> '_css_readmore_mobile',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> '.cz-cpt-post .cz_readmore, .cz-cpt-post .more-link'
						),
						array(
							'id' 			=> '_css_readmore_hover',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> '.cz-cpt-post .cz_readmore:hover, .cz-cpt-post .more-link:hover'
						),
						array(
							'id' 			=> '_css_readmore_i',
							'hover_id' 		=> '_css_readmore_i_hover',
							'type' 			=> 'cz_sk',
							'title' 		=> esc_html__( 'Read more icon', 'codevz-plus' ),
							'button' 		=> esc_html__( 'Read more icon', 'codevz-plus' ),
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'settings' 		=> array( 'color' ),
							'selector' 		=> '.cz-cpt-post .cz_readmore i, .cz-cpt-post .more-link i'
						),
						array(
							'id' 			=> '_css_readmore_i_tablet',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> '.cz-cpt-post .cz_readmore i, .cz-cpt-post .more-link i'
						),
						array(
							'id' 			=> '_css_readmore_i_mobile',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> '.cz-cpt-post .cz_readmore i, .cz-cpt-post .more-link i'
						),
						array(
							'id' 			=> '_css_readmore_i_hover',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> '.cz-cpt-post .cz_readmore:hover i, .cz-cpt-post .more-link:hover i',
						),
						array(
							'id' 			=> '_css_post_meta_overall',
							'type' 			=> 'cz_sk',
							'title' 		=> esc_html__( 'Meta', 'codevz-plus' ),
							'button' 		=> esc_html__( 'Meta', 'codevz-plus' ),
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'settings' 		=> array( 'float', 'background', 'border' ),
							'selector' 		=> '.cz-cpt-post .cz_default_loop .cz_post_meta',
						),
						array(
							'id' 			=> '_css_post_meta_overall_tablet',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> '.cz-cpt-post .cz_default_loop .cz_post_meta',
						),
						array(
							'id' 			=> '_css_post_meta_overall_mobile',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> '.cz-cpt-post .cz_default_loop .cz_post_meta',
						),
						array(
							'id' 			=> '_css_pagination_li',
							'hover_id' 		=> '_css_pagination_li_hover',
							'type' 			=> 'cz_sk',
							'title' 		=> esc_html__( 'Pagination', 'codevz-plus' ),
							'button' 		=> esc_html__( 'Pagination', 'codevz-plus' ),
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'settings' 		=> array( 'color', 'background', 'font-size', 'border' ),
							'selector' 		=> '.pagination a, .pagination > b, .pagination span, .page-numbers a, .page-numbers span, .woocommerce nav.woocommerce-pagination ul li a, .woocommerce nav.woocommerce-pagination ul li span'
						),
						array(
							'id' 			=> '_css_pagination_li_tablet',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> '.pagination a, .pagination > b, .pagination span, .page-numbers a, .page-numbers span, .woocommerce nav.woocommerce-pagination ul li a, .woocommerce nav.woocommerce-pagination ul li span'
						),
						array(
							'id' 			=> '_css_pagination_li_mobile',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> '.pagination a, .pagination > b, .pagination span, .page-numbers a, .page-numbers span, .woocommerce nav.woocommerce-pagination ul li a, .woocommerce nav.woocommerce-pagination ul li span'
						),
						array(
							'id' 			=> '_css_pagination_li_hover',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> '.pagination .current, .pagination > b, .pagination a:hover, .page-numbers .current, .page-numbers a:hover, .pagination .next:hover, .pagination .prev:hover, .woocommerce nav.woocommerce-pagination ul li a:focus, .woocommerce nav.woocommerce-pagination ul li a:hover, .woocommerce nav.woocommerce-pagination ul li span.current'
						),

						array(
							'id' 			=> 'xtra_control_badge_blog_styling',
							'type' 			=> 'content',
							'content' 		=> Codevz_Plus::pro_badge(),
							'dependency' 	=> $free ? [] : [ 'x', '==', 'x' ]
						),
						array(
							'id' 			=> '_css_post_avatar',
							'type' 			=> $free ? 'cz_sk_free' : 'cz_sk',
							'title' 		=> esc_html__( 'Avatar', 'codevz-plus' ),
							'button' 		=> esc_html__( 'Avatar', 'codevz-plus' ),
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'settings' 		=> array( 'background', 'width', 'height', 'border' ),
							'selector' 		=> '.cz-cpt-post .cz_default_loop .cz_post_author_avatar img',
						),
						array(
							'id' 			=> '_css_post_avatar_tablet',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> '.cz-cpt-post .cz_default_loop .cz_post_author_avatar img',
						),
						array(
							'id' 			=> '_css_post_avatar_mobile',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> '.cz-cpt-post .cz_default_loop .cz_post_author_avatar img',
						),
						array(
							'id' 			=> '_css_post_author',
							'type' 			=> $free ? 'cz_sk_free' : 'cz_sk',
							'title' 		=> esc_html__( 'Author', 'codevz-plus' ),
							'button' 		=> esc_html__( 'Author', 'codevz-plus' ),
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'settings' 		=> array( 'color', 'font-weight' ),
							'selector' 		=> '.cz-cpt-post .cz_default_loop .cz_post_author_name',
						),
						array(
							'id' 			=> '_css_post_author_tablet',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> '.cz-cpt-post .cz_default_loop .cz_post_author_name',
						),
						array(
							'id' 			=> '_css_post_author_mobile',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> '.cz-cpt-post .cz_default_loop .cz_post_author_name',
						),
						array(
							'id' 			=> '_css_post_date',
							'type' 			=> $free ? 'cz_sk_free' : 'cz_sk',
							'title' 		=> esc_html__( 'Date', 'codevz-plus' ),
							'button' 		=> esc_html__( 'Date', 'codevz-plus' ),
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'settings' 		=> array( 'color', 'font-style' ),
							'selector' 		=> '.cz-cpt-post .cz_default_loop .cz_post_date',
						),
						array(
							'id' 			=> '_css_post_date_tablet',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> '.cz-cpt-post .cz_default_loop .cz_post_date',
						),
						array(
							'id' 			=> '_css_post_date_mobile',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> '.cz-cpt-post .cz_default_loop .cz_post_date',
						),
						array(
							'id' 			=> '_css_post_excerpt',
							'type' 			=> $free ? 'cz_sk_free' : 'cz_sk',
							'title' 		=> esc_html__( 'Excerpt', 'codevz-plus' ),
							'button' 		=> esc_html__( 'Excerpt', 'codevz-plus' ),
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'settings' 		=> array( 'text-align', 'color', 'line-height' ),
							'selector' 		=> '.cz-cpt-post .cz_default_loop .cz_post_excerpt',
						),
						array(
							'id' 			=> '_css_post_excerpt_tablet',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> '.cz-cpt-post .cz_default_loop .cz_post_excerpt',
						),
						array(
							'id' 			=> '_css_post_excerpt_mobile',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> '.cz-cpt-post .cz_default_loop .cz_post_excerpt',
						),

					),
				),

				array(
					'name'   => 'single_settings',
					'title'  => esc_html__( 'Single Settings', 'codevz-plus' ),
					'fields' => array(
						array(
							'id' 			=> 'layout_single_post',
							'type' 			=> 'codevz_image_select',
							'title' 		=> esc_html__( 'Sidebar', 'codevz-plus' ),
							'help'  		=> esc_html__( 'Single Posts', 'codevz-plus' ),
							'options' 		=> [
								'1' 			=> [ esc_html__( '~ Default ~', 'codevz-plus' ) 			, Codevz_Plus::$url . 'assets/img/sidebar-0.png' ],
								'ws' 			=> [ esc_html__( 'No Sidebar', 'codevz-plus' ) 			, Codevz_Plus::$url . 'assets/img/off.png' ],
								'bpnp' 			=> [ esc_html__( 'Fullwidth', 'codevz-plus' ) 			, Codevz_Plus::$url . 'assets/img/sidebar-2.png' ],
								'center'		=> [ esc_html__( 'Center Mode', 'codevz-plus' ) 			, Codevz_Plus::$url . 'assets/img/sidebar-13.png' ],
								'right' 		=> [ esc_html__( 'Right Sidebar', 'codevz-plus' ) 		, Codevz_Plus::$url . 'assets/img/sidebar-3.png' ],
								'right-s' 		=> [ esc_html__( 'Right Sidebar Small', 'codevz-plus' ) 	, Codevz_Plus::$url . 'assets/img/sidebar-4.png' ],
								'left' 			=> [ esc_html__( 'Left Sidebar', 'codevz-plus' ) 		, Codevz_Plus::$url . 'assets/img/sidebar-5.png' ],
								'left-s' 		=> [ esc_html__( 'Left Sidebar Small', 'codevz-plus' ) 	, Codevz_Plus::$url . 'assets/img/sidebar-6.png' ],
								'both-side' 	=> [ esc_html__( 'Both Sidebar', 'codevz-plus' ) . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ) 		, Codevz_Plus::$url . 'assets/img/sidebar-7.png' ],
								'both-side2' 	=> [ esc_html__( 'Both Sidebar Small', 'codevz-plus' ) . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ) 	, Codevz_Plus::$url . 'assets/img/sidebar-8.png' ],
								'both-right' 	=> [ esc_html__( 'Both Sidebar Right', 'codevz-plus' ) . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ) 	, Codevz_Plus::$url . 'assets/img/sidebar-9.png' ],
								'both-right2' 	=> [ esc_html__( 'Both Sidebar Right', 'codevz-plus' ) . ' 2' . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ) , Codevz_Plus::$url . 'assets/img/sidebar-10.png' ],
								'both-left' 	=> [ esc_html__( 'Both Sidebar Left', 'codevz-plus' ) . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ) 	, Codevz_Plus::$url . 'assets/img/sidebar-11.png' ],
								'both-left2' 	=> [ esc_html__( 'Both Sidebar Left', 'codevz-plus' ) . ' 2' . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' )  , Codevz_Plus::$url . 'assets/img/sidebar-12.png' ],
							],
							'default' 		=> '1'
						),
						array(
							'id' 		=> 'meta_data_post',
							'type' 		=> 'checkbox',
							'title' 	=> esc_html__( 'Features', 'codevz-plus' ),
							'options' 	=> array(
								'image'		=> esc_html__( 'Post Image', 'codevz-plus' ),
								'source'	=> esc_html__( 'Source', 'codevz-plus' ),
								'author'	=> esc_html__( 'Author', 'codevz-plus' ),
								'date'		=> esc_html__( 'Date', 'codevz-plus' ),
								'cats'		=> esc_html__( 'Categories', 'codevz-plus' ),
								'tags'		=> esc_html__( 'Tags', 'codevz-plus' ),
								'next_prev' => esc_html__( 'Next Prev Posts', 'codevz-plus' ),
								'views' 	=> esc_html__( 'Post views', 'codevz-plus' ),
							),
							'default' 	=> array( 'image','date','author','cats','tags','author_box', 'next_prev' )
						),
						array(
							'id' 			=> 'single_post_meta_display',
							'type'  		=> $free ? 'content' : 'select',
							'content' 		=> Codevz_Plus::pro_badge(),
							'title' 		=> esc_html__( 'Post meta display', 'codevz-plus' ),
							'options' 		=> [
								'' 						=> esc_html__( 'Inline', 'codevz-plus' ),
								'cz_post_meta_block' 	=> esc_html__( 'Block', 'codevz-plus' ),
							]
						),
						array(
							'id' 		=> 'post_meta_title_instead_icon',
							'type'  	=> $free ? 'content' : 'switcher',
							'content' 	=> Codevz_Plus::pro_badge(),
							'title' 	=> esc_html__( 'Post meta title', 'codevz-plus' )
						),
						array(
							'id' 			=> 'related_post_col',
							'type' 			=> 'codevz_image_select',
							'title' 		=> esc_html__( 'Related posts', 'codevz-plus' ),
							'options' 		=> [
								'none' 			=> [ esc_html__( 'None', 'codevz-plus' ) 		, Codevz_Plus::$url . 'assets/img/off.png' ],
								's6' 			=> [ '2 ' . esc_html__( 'Columns', 'codevz-plus' ) 		, Codevz_Plus::$url . 'assets/img/cols-2.png' ],
								's4' 			=> [ '3 ' . esc_html__( 'Columns', 'codevz-plus' ) 		, Codevz_Plus::$url . 'assets/img/cols-3.png' ],
								's3' 			=> [ '4 ' . esc_html__( 'Columns', 'codevz-plus' ) 		, Codevz_Plus::$url . 'assets/img/cols-4.png' ],
							],
							'default' 		=> 's4'
						),
						array(
							'id'    		=> 'related_image_size',
							'type'   		=> $free ? 'content' : 'select',
							'content' 		=> Codevz_Plus::pro_badge(),
							'title'   		=> esc_html__( 'Image size', 'codevz-plus' ),
							'options'   	=> $image_sizes,
							'dependency'  	=> array( 'related_post_col', '!=', 'none' )
						),
						array(
							'id'          	=> 'related_posts_post',
							'type'        	=> 'text',
							'title'       	=> esc_html__('Related title', 'codevz-plus' ),
							'default'		=> 'Related Posts ...',
							'setting_args' 	=> array('transport' => 'postMessage'),
							'dependency'  	=> array( 'related_post_col', '!=', 'none' ),
						),
						array(
							'id' 			=> 'prev_post',
							'type' 			=> 'text',
							'title' 		=> esc_html__( 'Prev Surtitle', 'codevz-plus' ),
							'default' 		=> 'Previous',
							'setting_args' 	=> array('transport' => 'postMessage')
						),
						array(
							'id' 			=> 'next_post',
							'type' 			=> 'text',
							'title' 		=> esc_html__( 'Next Surtitle', 'codevz-plus' ),
							'default' 		=> 'Next',
							'setting_args' 	=> array('transport' => 'postMessage')
						),
						array(
							'id'    		=> 'no_comment',
							'type'  		=> 'text',
							'title' 		=> esc_html__( 'No comment', 'codevz-plus' ),
							'default' 		=> 'No comment',
							'setting_args' 	=> [ 'transport' => 'postMessage' ]
						),
						array(
							'id'    		=> 'comment',
							'type'  		=> 'text',
							'title' 		=> esc_html__( 'Comment', 'codevz-plus' ),
							'default' 		=> 'Comment',
							'setting_args' 	=> [ 'transport' => 'postMessage' ]
						),
						array(
							'id'    		=> 'comments',
							'type'  		=> 'text',
							'title' 		=> esc_html__( 'Comments', 'codevz-plus' ),
							'default' 		=> 'Comments',
							'setting_args' 	=> [ 'transport' => 'postMessage' ]
						),
						array(
							'id'          	=> 'cm_disabled',
							'type'        	=> 'text',
							'title'       	=> esc_html__( 'Comments disabled', 'codevz-plus' ),
							'default'	  	=> esc_html__( 'Comments are disabled.', 'codevz-plus' ),
							'setting_args' 	=> [ 'transport' => 'postMessage' ]
						),
					),
				),

				array(
					'name'   => 'single_styles',
					'title'  => esc_html__( 'Single Styling', 'codevz-plus' ),
					'fields' => array(
						array(
							'id' 			=> '_css_single_con',
							'type' 			=> 'cz_sk',
							'title' 		=> esc_html__( 'Container', 'codevz-plus' ),
							'button' 		=> esc_html__( 'Container', 'codevz-plus' ),
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'settings' 		=> array( 'color', 'background', 'border' ),
							'selector' 		=> '.single_con',
						),
						array(
							'id' 			=> '_css_single_con_tablet','type' => 'cz_sk_hidden','setting_args' => [ 'transport' => 'postMessage' ],
							'selector' 		=> '.single_con',
						),
						array(
							'id' 			=> '_css_single_con_mobile','type' => 'cz_sk_hidden','setting_args' => [ 'transport' => 'postMessage' ],
							'selector' 		=> '.single_con',
						),
						array(
							'id' 			=> '_css_single_title',
							'type' 			=> 'cz_sk',
							'title' 		=> esc_html__( 'Title', 'codevz-plus' ),
							'button' 		=> esc_html__( 'Title', 'codevz-plus' ),
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'settings' 		=> array( 'color', 'line-height' ),
							'selector' 		=> '.single .content .xtra-post-title',
						),
						array(
							'id' 			=> '_css_single_title_tablet',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> '.single .content .xtra-post-title',
						),
						array(
							'id' 			=> '_css_single_title_mobile',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> '.single .content .xtra-post-title',
						),
						array(
							'id' 			=> '_css_single_title_date',
							'hover_id' 		=> '_css_single_title_date_hover',
							'type' 			=> 'cz_sk',
							'title' 		=> esc_html__( 'Title meta', 'codevz-plus' ),
							'button' 		=> esc_html__( 'Title meta', 'codevz-plus' ),
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'settings' 		=> array( 'color', 'background', 'border' ),
							'selector' 		=> '.single .xtra-post-title-date a, .single .xtra-post-title-date .xtra-post-views',
						),
						array(
							'id' 			=> '_css_single_title_date_tablet',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> '.single .xtra-post-title-date a, .single .xtra-post-title-date .xtra-post-views',
						),
						array(
							'id' 			=> '_css_single_title_date_mobile',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> '.single .xtra-post-title-date a, .single .xtra-post-title-date .xtra-post-views',
						),
						array(
							'id' 			=> '_css_single_title_date_hover',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> '.single .xtra-post-title-date a:hover',
						),
						array(
							'id' 			=> '_css_single_fi',
							'type' 			=> 'cz_sk',
							'title' 		=> esc_html__( 'Image', 'codevz-plus' ),
							'button' 		=> esc_html__( 'Image', 'codevz-plus' ),
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'settings' 		=> array( 'background', 'border' ),
							'selector' 		=> '.single_con .cz_single_fi img',
						),
						array(
							'id' 			=> '_css_single_fi_tablet',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> '.single_con .cz_single_fi img',
						),
						array(
							'id' 			=> '_css_single_fi_mobile',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> '.single_con .cz_single_fi img',
						),
						array(
							'id' 			=> '_css_tags_categories',
							'hover_id' 		=> '_css_tags_categories_hover',
							'type' 			=> 'cz_sk',
							'title' 		=> esc_html__( 'Meta', 'codevz-plus' ),
							'button' 		=> esc_html__( 'Meta', 'codevz-plus' ),
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'settings' 		=> array( 'color', 'background', 'font-size', 'border' ),
							'selector' 		=> '.tagcloud a, .widget .tagcloud a, .cz_post_cat a, .cz_post_views a'
						),
						array(
							'id' 			=> '_css_tags_categories_tablet',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> '.tagcloud a, .widget .tagcloud a, .cz_post_cat a, .cz_post_views a'
						),
						array(
							'id' 			=> '_css_tags_categories_mobile',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> '.tagcloud a, .widget .tagcloud a, .cz_post_cat a, .cz_post_views a'
						),
						array(
							'id' 			=> '_css_tags_categories_hover',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> '.tagcloud a:hover, .widget .tagcloud a:hover, .cz_post_cat a:hover, .cz_post_views a:hover'
						),
						array(
							'id' 			=> '_css_tags_categories_icon',
							'type' 			=> 'cz_sk',
							'title' 		=> esc_html__( 'Meta Icon', 'codevz-plus' ),
							'button' 		=> esc_html__( 'Meta Icon', 'codevz-plus' ),
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'settings' 		=> array( 'color', 'background', 'font-size', 'border' ),
							'selector' 		=> '.single_con .tagcloud a:first-child, .single_con .cz_post_cat a:first-child, .cz_post_views a:first-child'
						),
						array(
							'id' 			=> '_css_tags_categories_icon_tablet',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> '.single_con .tagcloud a:first-child, .single_con .cz_post_cat a:first-child, .cz_post_views a:first-child'
						),
						array(
							'id' 			=> '_css_tags_categories_icon_mobile',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> '.single_con .tagcloud a:first-child, .single_con .cz_post_cat a:first-child, .cz_post_views a:first-child'
						),
						array(
							'type'    		=> 'notice',
							'class'   		=> 'info xtra-notice',
							'content' 		=> '<i class="fas fa-signs-post mr8"></i>' . esc_html__( 'Next & Previous Posts', 'codevz-plus' )
						),
						array(
							'id' 			=> '_css_next_prev_con',
							'type' 			=> 'cz_sk',
							'title' 		=> esc_html__( 'Container', 'codevz-plus' ),
							'button' 		=> esc_html__( 'Container', 'codevz-plus' ),
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'settings' 		=> array( 'background', 'border' ),
							'selector' 		=> '.next_prev'
						),
						array(
							'id' 			=> '_css_next_prev_con_tablet',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> '.next_prev'
						),
						array(
							'id' 			=> '_css_next_prev_con_mobile',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> '.next_prev'
						),
						array(
							'id' 			=> '_css_next_prev_icons',
							'hover_id' 		=> '_css_next_prev_icons_hover',
							'type' 			=> 'cz_sk',
							'title' 		=> esc_html__( 'Icons', 'codevz-plus' ),
							'button' 		=> esc_html__( 'Icons', 'codevz-plus' ),
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'settings' 		=> array( 'color', 'background', 'font-size', 'border' ),
							'selector' 		=> '.next_prev .previous i,.next_prev .next i'
						),
						array(
							'id' 			=> '_css_next_prev_icons_tablet',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> '.next_prev .previous i,.next_prev .next i'
						),
						array(
							'id' 			=> '_css_next_prev_icons_mobile',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> '.next_prev .previous i,.next_prev .next i'
						),
						array(
							'id' 			=> '_css_next_prev_icons_hover',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> '.next_prev .previous:hover i,.next_prev .next:hover i'
						),
						array(
							'id' 			=> '_css_next_prev_titles',
							'hover_id' 		=> '_css_next_prev_titles_hover',
							'type' 			=> 'cz_sk',
							'title' 		=> esc_html__( 'Titles', 'codevz-plus' ),
							'button' 		=> esc_html__( 'Titles', 'codevz-plus' ),
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'settings' 		=> array( 'color', 'line-height' ),
							'selector' 		=> '.next_prev h4'
						),
						array(
							'id' 			=> '_css_next_prev_titles_tablet',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> '.next_prev h4'
						),
						array(
							'id' 			=> '_css_next_prev_titles_mobile',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> '.next_prev h4'
						),
						array(
							'id' 			=> '_css_next_prev_titles_hover',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> '.next_prev li:hover h4'
						),
						array(
							'id' 			=> '_css_next_prev_surtitle',
							'type' 			=> 'cz_sk',
							'title' 		=> esc_html__( 'Sur Titles', 'codevz-plus' ),
							'button' 		=> esc_html__( 'Sur Titles', 'codevz-plus' ),
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'settings' 		=> array( 'color', 'background', 'font-size', 'border' ),
							'selector' 		=> '.next_prev h4 small'
						),
						array(
							'id' 			=> '_css_next_prev_surtitle_tablet',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> '.next_prev h4 small'
						),
						array(
							'id' 			=> '_css_next_prev_surtitle_mobile',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> '.next_prev h4 small'
						),

						array(
							'type'    		=> 'notice',
							'class'   		=> 'info xtra-notice',
							'content' 		=> '<i class="fas fa-sliders mr8"></i>' . esc_html__( 'Related Posts & Comments', 'codevz-plus' )
						),
						array(
							'id' 			=> '_css_related_posts_con',
							'type' 			=> 'cz_sk',
							'title' 		=> esc_html__( 'Container', 'codevz-plus' ),
							'button' 		=> esc_html__( 'Container', 'codevz-plus' ),
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'settings' 		=> array( 'background', 'border' ),
							'selector' 		=> '.xtra-comments,.content.cz_related_posts,.cz_author_box,.related.products,.upsells.products,.up-sells.products,.woocommerce-page .cart-collaterals .cart_totals,.woocommerce-page #customer_details,.woocommerce-page .codevz-checkout-details,.woocommerce-page .woocommerce-order-details,.woocommerce-page .woocommerce-customer-details,.woocommerce-page .cart-collaterals .cross-sells,.woocommerce-account .cz_post_content > .woocommerce'
						),
						array(
							'id' 			=> '_css_related_posts_con_tablet',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> '.xtra-comments,.content.cz_related_posts,.cz_author_box,.related.products,.upsells.products,.up-sells.products,.woocommerce-page .cart-collaterals .cart_totals,.woocommerce-page #customer_details,.woocommerce-page .codevz-checkout-details,.woocommerce-page .woocommerce-order-details,.woocommerce-page .woocommerce-customer-details,.woocommerce-page .cart-collaterals .cross-sells,.woocommerce-account .cz_post_content > .woocommerce'
						),
						array(
							'id' 			=> '_css_related_posts_con_mobile',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> '.xtra-comments,.content.cz_related_posts,.cz_author_box,.related.products,.upsells.products,.up-sells.products,.woocommerce-page .cart-collaterals .cart_totals,.woocommerce-page #customer_details,.woocommerce-page .codevz-checkout-details,.woocommerce-page .woocommerce-order-details,.woocommerce-page .woocommerce-customer-details,.woocommerce-page .cart-collaterals .cross-sells,.woocommerce-account .cz_post_content > .woocommerce'
						),
						array(
							'id' 			=> '_css_related_posts_sec_title',
							'type' 			=> 'cz_sk',
							'title' 		=> esc_html__( 'Title', 'codevz-plus' ),
							'button' 		=> esc_html__( 'Title', 'codevz-plus' ),
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'settings' 		=> array( 'color', 'background', 'font-size', 'border' ),
							'selector' 		=> '#comments > h3,.content.cz_related_posts > h4,.content.cz_author_box > h4,.related.products > h2,.upsells.products > h2,.up-sells.products > h2,.up-sells.products > h2,.woocommerce-page .cart-collaterals .cart_totals > h2,.woocommerce-page #customer_details > div:first-child > div:first-child > h3:first-child,.woocommerce-page .codevz-checkout-details > h3,.woocommerce-page .woocommerce-order-details > h2,.woocommerce-page .woocommerce-customer-details > h2,.woocommerce-page .cart-collaterals .cross-sells > h2'
						),
						array(
							'id' 			=> '_css_related_posts_sec_title_tablet',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> '#comments > h3,.content.cz_related_posts > h4,.content.cz_author_box > h4,.related.products > h2,.upsells.products > h2,.up-sells.products > h2,.up-sells.products > h2,.woocommerce-page .cart-collaterals .cart_totals > h2,.woocommerce-page #customer_details > div:first-child > div:first-child > h3:first-child,.woocommerce-page .codevz-checkout-details > h3,.woocommerce-page .woocommerce-order-details > h2,.woocommerce-page .woocommerce-customer-details > h2,.woocommerce-page .cart-collaterals .cross-sells > h2'
						),
						array(
							'id' 			=> '_css_related_posts_sec_title_mobile',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> '#comments > h3,.content.cz_related_posts > h4,.content.cz_author_box > h4,.related.products > h2,.upsells.products > h2,.up-sells.products > h2,.up-sells.products > h2,.woocommerce-page .cart-collaterals .cart_totals > h2,.woocommerce-page #customer_details > div:first-child > div:first-child > h3:first-child,.woocommerce-page .codevz-checkout-details > h3,.woocommerce-page .woocommerce-order-details > h2,.woocommerce-page .woocommerce-customer-details > h2,.woocommerce-page .cart-collaterals .cross-sells > h2'
						),
						array(
							'id' 			=> '_css_related_posts_sec_title_before',
							'type'          => $free ? 'cz_sk_free' : 'cz_sk',
							'title' 		=> esc_html__( 'Title shape', 'codevz-plus' ) . ' 1',
							'button' 		=> esc_html__( 'Title shape', 'codevz-plus' ) . ' 1',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'settings' 		=> array( 'background', 'width', 'height', 'border', 'position', 'top', 'left', 'bottom', 'right' ),
							'selector' 		=> '#comments > h3:before,.content.cz_related_posts > h4:before,.content.cz_author_box > h4:before,.related.products > h2:before,.upsells.products > h2:before,.up-sells.products > h2:before,.up-sells.products > h2:before,.woocommerce-page .cart-collaterals .cart_totals > h2:before,.woocommerce-page #customer_details > div:first-child > div:first-child > h3:first-child:before,.woocommerce-page .codevz-checkout-details > h3:before,.woocommerce-page .woocommerce-order-details > h2:before,.woocommerce-page .woocommerce-customer-details > h2:before,.woocommerce-page .cart-collaterals .cross-sells > h2:before'
						),
						array(
							'id' 			=> '_css_related_posts_sec_title_before_tablet',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> '#comments > h3:before,.content.cz_related_posts > h4:before,.content.cz_author_box > h4:before,.related.products > h2:before,.upsells.products > h2:before,.up-sells.products > h2:before,.up-sells.products > h2:before,.woocommerce-page .cart-collaterals .cart_totals > h2:before,.woocommerce-page #customer_details > div:first-child > div:first-child > h3:first-child:before,.woocommerce-page .codevz-checkout-details > h3:before,.woocommerce-page .woocommerce-order-details > h2:before,.woocommerce-page .woocommerce-customer-details > h2:before,.woocommerce-page .cart-collaterals .cross-sells > h2:before'
						),
						array(
							'id' 			=> '_css_related_posts_sec_title_before_mobile',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> '#comments > h3:before,.content.cz_related_posts > h4:before,.content.cz_author_box > h4:before,.related.products > h2:before,.upsells.products > h2:before,.up-sells.products > h2:before,.up-sells.products > h2:before,.woocommerce-page .cart-collaterals .cart_totals > h2:before,.woocommerce-page #customer_details > div:first-child > div:first-child > h3:first-child:before,.woocommerce-page .codevz-checkout-details > h3:before,.woocommerce-page .woocommerce-order-details > h2:before,.woocommerce-page .woocommerce-customer-details > h2:before,.woocommerce-page .cart-collaterals .cross-sells > h2:before'
						),
						array(
							'id' 			=> '_css_related_posts_sec_title_after',
							'type'          => $free ? 'cz_sk_free' : 'cz_sk',
							'title' 		=> esc_html__( 'Title shape', 'codevz-plus' ) . ' 2',
							'button' 		=> esc_html__( 'Title shape', 'codevz-plus' ) . ' 2',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'settings' 		=> array( 'background', 'width', 'height', 'border', 'position', 'top', 'left', 'bottom', 'right' ),
							'selector' 		=> '#comments > h3:after,.content.cz_related_posts > h4:after,.content.cz_author_box > h4:after,.related.products > h2:after,.upsells.products > h2:after,.up-sells.products > h2:after,.up-sells.products > h2:after,.woocommerce-page .cart-collaterals .cart_totals > h2:after,.woocommerce-page #customer_details > div:first-child > div:first-child > h3:first-child:after,.woocommerce-page .codevz-checkout-details > h3:after,.woocommerce-page .woocommerce-order-details > h2:after,.woocommerce-page .woocommerce-customer-details > h2:after,.woocommerce-page .cart-collaterals .cross-sells > h2:after'
						),
						array(
							'id' 			=> '_css_related_posts_sec_title_after_tablet',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> '#comments > h3:after,.content.cz_related_posts > h4:after,.content.cz_author_box > h4:after,.related.products > h2:after,.upsells.products > h2:after,.up-sells.products > h2:after,.up-sells.products > h2:after,.woocommerce-page .cart-collaterals .cart_totals > h2:after,.woocommerce-page #customer_details > div:first-child > div:first-child > h3:first-child:after,.woocommerce-page .codevz-checkout-details > h3:after,.woocommerce-page .woocommerce-order-details > h2:after,.woocommerce-page .woocommerce-customer-details > h2:after,.woocommerce-page .cart-collaterals .cross-sells > h2:after'
						),
						array(
							'id' 			=> '_css_related_posts_sec_title_after_mobile',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> '#comments > h3:after,.content.cz_related_posts > h4:after,.content.cz_author_box > h4:after,.related.products > h2:after,.upsells.products > h2:after,.up-sells.products > h2:after,.up-sells.products > h2:after,.woocommerce-page .cart-collaterals .cart_totals > h2:after,.woocommerce-page #customer_details > div:first-child > div:first-child > h3:first-child:after,.woocommerce-page .codevz-checkout-details > h3:after,.woocommerce-page .woocommerce-order-details > h2:after,.woocommerce-page .woocommerce-customer-details > h2:after,.woocommerce-page .cart-collaterals .cross-sells > h2:after'
						),
						array(
							'id' 			=> '_css_related_posts',
							'hover_id' 		=> '_css_related_posts_hover',
							'type' 			=> 'cz_sk',
							'title' 		=> esc_html__( 'Posts', 'codevz-plus' ),
							'button' 		=> esc_html__( 'Posts', 'codevz-plus' ),
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'settings' 		=> array( 'background', 'border' ),
							'selector' 		=> '.cz_related_posts .cz_related_post > div'
						),
						array(
							'id' => '_css_related_posts_tablet',
							'type' => 'cz_sk_hidden',
							'setting_args' => [ 'transport' => 'postMessage' ],
							'selector' => '.cz_related_posts .cz_related_post > div'
						),
						array(
							'id' => '_css_related_posts_mobile',
							'type' => 'cz_sk_hidden',
							'setting_args' => [ 'transport' => 'postMessage' ],
							'selector' => '.cz_related_posts .cz_related_post > div'
						),
						array(
							'id' 			=> '_css_related_posts_hover',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> '.cz_related_posts .cz_related_post:hover > div'
						),
						array(
							'id'      	=> '_css_related_posts_img',
							'hover_id' 	=> '_css_related_posts_img_hover',
							'type'      => 'cz_sk',
							'title'    => esc_html__( 'Images', 'codevz-plus' ),
							'button'    => esc_html__( 'Images', 'codevz-plus' ),
							'setting_args'  => [ 'transport' => 'postMessage' ],
							'settings'    => array( 'background', 'border' ),
							'selector'    => '.cz_related_posts .cz_related_post .cz_post_image'
						),
						array(
							'id' => '_css_related_posts_img_tablet',
							'type' => 'cz_sk_hidden',
							'setting_args' => [ 'transport' => 'postMessage' ],
							'selector'    => '.cz_related_posts .cz_related_post .cz_post_image'
						),
						array(
							'id' => '_css_related_posts_img_mobile',
							'type' => 'cz_sk_hidden',
							'setting_args' => [ 'transport' => 'postMessage' ],
							'selector'    => '.cz_related_posts .cz_related_post .cz_post_image'
						),
						array(
							'id' => '_css_related_posts_img_hover',
							'type' => 'cz_sk_hidden',
							'setting_args' => [ 'transport' => 'postMessage' ],
							'selector'    => '.cz_related_posts .cz_related_post:hover .cz_post_image'
						),
						array(
							'id' 			=> '_css_related_posts_title',
							'hover_id' 		=> '_css_related_posts_title_hover',
							'type' 			=> 'cz_sk',
							'title' 		=> esc_html__( 'Titles', 'codevz-plus' ),
							'button' 		=> esc_html__( 'Titles', 'codevz-plus' ),
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'settings' 		=> array( 'color', 'line-height' ),
							'selector' 		=> '.cz_related_posts .cz_related_post h3'
						),
						array(
							'id' 			=> '_css_related_posts_title_tablet',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> '.cz_related_posts .cz_related_post h3'
						),
						array(
							'id' 			=> '_css_related_posts_title_mobile',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> '.cz_related_posts .cz_related_post h3'
						),
						array(
							'id' 			=> '_css_related_posts_title_hover',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> '.cz_related_posts .cz_related_post:hover h3'
						),
						array(
							'id' 			=> '_css_related_posts_meta',
							'type' 			=> 'cz_sk',
							'title' 		=> esc_html__( 'Meta', 'codevz-plus' ),
							'button' 		=> esc_html__( 'Meta', 'codevz-plus' ),
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'settings' 		=> array( 'color' ),
							'selector' 		=> '.cz_related_posts .cz_related_post_date'
						),
						array(
							'id' 			=> '_css_related_posts_meta_mobile',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> '.cz_related_posts .cz_related_post_date'
						),
						array(
							'id' 			=> '_css_related_posts_meta_links',
							'hover_id' 		=> '_css_related_posts_meta_links_hover',
							'type' 			=> 'cz_sk',
							'title' 		=> esc_html__( 'Meta Links', 'codevz-plus' ),
							'button' 		=> esc_html__( 'Meta Links', 'codevz-plus' ),
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'settings' 		=> array( 'color' ),
							'selector' 		=> '.cz_related_posts .cz_related_post_date a'
						),
						array(
							'id' 			=> '_css_related_posts_meta_links_mobile',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> '.cz_related_posts .cz_related_post_date a'
						),
						array(
							'id' 			=> '_css_related_posts_meta_links_hover',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> '.cz_related_posts .cz_related_post_date a:hover'
						),
						array(
							'id' 			=> '_css_single_comments_li',
							'type' 			=> 'cz_sk',
							'title' 		=> esc_html__( 'Comments', 'codevz-plus' ),
							'button' 		=> esc_html__( 'Comments', 'codevz-plus' ),
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'settings' 		=> array( 'background', 'border' ),
							'selector' 		=> '.xtra-comments .commentlist li article'
						),
						array(
							'id' 			=> '_css_single_comments_li_tablet',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> '.xtra-comments .commentlist li article'
						),
						array(
							'id' 			=> '_css_single_comments_li_mobile',
							'type' 			=> 'cz_sk_hidden',
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'selector' 		=> '.xtra-comments .commentlist li article'
						),
					),
				),

			  array(
				'name'   => 'search_settings',
				'title'  => esc_html__( 'Search Page', 'codevz-plus' ),
				'fields' => array(
					array(
						'id' 			=> 'layout_search',
						'type' 			=> 'codevz_image_select',
						'title' 		=> esc_html__( 'Sidebar', 'codevz-plus' ),
						'help'  		=> esc_html__( 'The default sidebar setting can be adjusted in General > Layout', 'codevz-plus' ),
						'options' 		=> [
							'1' 			=> [ esc_html__( '~ Default ~', 'codevz-plus' ) 			, Codevz_Plus::$url . 'assets/img/sidebar-0.png' ],
							'ws' 			=> [ esc_html__( 'No Sidebar', 'codevz-plus' ) 			, Codevz_Plus::$url . 'assets/img/off.png' ],
							'bpnp' 			=> [ esc_html__( 'Fullwidth', 'codevz-plus' ) 			, Codevz_Plus::$url . 'assets/img/sidebar-2.png' ],
							'center'		=> [ esc_html__( 'Center Mode', 'codevz-plus' ) 			, Codevz_Plus::$url . 'assets/img/sidebar-13.png' ],
							'right' 		=> [ esc_html__( 'Right Sidebar', 'codevz-plus' ) 		, Codevz_Plus::$url . 'assets/img/sidebar-3.png' ],
							'right-s' 		=> [ esc_html__( 'Right Sidebar Small', 'codevz-plus' ) 	, Codevz_Plus::$url . 'assets/img/sidebar-4.png' ],
							'left' 			=> [ esc_html__( 'Left Sidebar', 'codevz-plus' ) 		, Codevz_Plus::$url . 'assets/img/sidebar-5.png' ],
							'left-s' 		=> [ esc_html__( 'Left Sidebar Small', 'codevz-plus' ) 	, Codevz_Plus::$url . 'assets/img/sidebar-6.png' ],
							'both-side' 	=> [ esc_html__( 'Both Sidebar', 'codevz-plus' ) . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ) 		, Codevz_Plus::$url . 'assets/img/sidebar-7.png' ],
							'both-side2' 	=> [ esc_html__( 'Both Sidebar Small', 'codevz-plus' ) . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ) 	, Codevz_Plus::$url . 'assets/img/sidebar-8.png' ],
							'both-right' 	=> [ esc_html__( 'Both Sidebar Right', 'codevz-plus' ) . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ) 	, Codevz_Plus::$url . 'assets/img/sidebar-9.png' ],
							'both-right2' 	=> [ esc_html__( 'Both Sidebar Right', 'codevz-plus' ) . ' 2' . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ) , Codevz_Plus::$url . 'assets/img/sidebar-10.png' ],
							'both-left' 	=> [ esc_html__( 'Both Sidebar Left', 'codevz-plus' ) . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ) 	, Codevz_Plus::$url . 'assets/img/sidebar-11.png' ],
							'both-left2' 	=> [ esc_html__( 'Both Sidebar Left', 'codevz-plus' ) . ' 2' . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' )  , Codevz_Plus::$url . 'assets/img/sidebar-12.png' ],
						],
						'default' 		=> 'right',
						'attributes' 	=> [ 'data-depend-id' => 'layout_search' ]
					),
					array(
						'id'      => 'search_title_prefix',
						'type'    => 'text',
						'title'   => esc_html__( 'Title Prefix', 'codevz-plus' ),
						'default' => esc_html__( 'Search result for:', 'codevz-plus' ),
					),
					array(
						'id' 		=> 'search_cpt',
						'type' 		=> $free ? 'content' : 'text',
						'content' 	=> Codevz_Plus::pro_badge(),
						'title'		=> esc_html__( 'Post Type(s)', 'codevz-plus' ),
						'help'		=> 'e.g. post,portfolio,product'
					),
					array(
						'id' 		=> 'search_count',
						'type' 		=> 'slider',
						'title'		=> esc_html__( 'Count', 'codevz-plus' ),
						'options' 	=> array( 'unit' => '', 'step' => 1, 'min' => 1, 'max' => 12 ),
					),
					array(
						'id' 		=> 'search_order',
						'type' 		=> $free ? 'content' : 'select',
						'content' 	=> Codevz_Plus::pro_badge(),
						'title' 	=> esc_html__( 'Posts Order', 'codevz-plus' ),
						'options' 	=> [
							'' 				=> esc_html__( '~ Default ~', 'codevz-plus' ),
							'ASC' 			=> esc_html__( 'Ascending', 'codevz-plus' ),
							'DESC' 			=> esc_html__( 'Descending', 'codevz-plus' ),
						],
					),
					array(
						'id' 		=> 'search_orderby',
						'type' 		=> $free ? 'content' : 'select',
						'content' 	=> Codevz_Plus::pro_badge(),
						'title' 	=> esc_html__( 'Order By', 'codevz-plus' ),
						'options' 	=> [
							'' 				=> esc_html__( '~ Default ~', 'codevz-plus' ),
							'date' 			=> esc_html__( 'Date', 'codevz-plus' ),
							'ID' 			=> esc_html__( 'ID', 'codevz-plus' ),
							'title' 		=> esc_html__( 'Title', 'codevz-plus' ),
							'rand' 			=> esc_html__( 'Random', 'codevz-plus' ),
							'menu_order' 	=> esc_html__( 'Menu order', 'codevz-plus' ),
							'comment_count' => esc_html__( 'Comments', 'codevz-plus' ),
						],
					),
				),
			  ),

			),
		);

		$dynamic_ctp = (array) get_option( 'codevz_post_types' );

		// Generate options for each post types
		foreach( self::post_types() as $cpt ) {
			if ( empty( $cpt ) ) {
				continue;
			}

			$name = get_post_type_object( $cpt );
			$name = isset( $name->label ) ? $name->label : ucwords( str_replace( '_', ' ', $cpt ) );

			$cpt_title = isset( self::$trasnlation[ $name ] ) ? self::$trasnlation[ $name ] : $name;

			$portfolio_slug = ( $cpt === 'portfolio' || in_array( $cpt, $dynamic_ctp ) ) ? array(
				'name'   => $cpt . '_slug',
				'title'  => esc_html__( 'Slug and Title', 'codevz-plus' ),
				'fields' => array(
					array(
						'id' 		=> 'disable_portfolio',
						'type'  	=> $free ? 'content' : 'switcher',
						'content' 	=> Codevz_Plus::pro_badge(),
						'title' 	=> esc_html__( 'Disable?', 'codevz-plus' )
					),
					array(
						'id' 	=> 'slug_' . $cpt,
						'type'  	=> $free ? 'content' : 'text',
						'content' 	=> Codevz_Plus::pro_badge(),
						'title' => esc_html__( 'Slug', 'codevz-plus' ),
						'attributes' => array( 'placeholder'	=> $cpt ),
						'setting_args' => array('transport' => 'postMessage')
					),
					array(
						'id' 	=> 'title_' . $cpt,
						'type'  	=> $free ? 'content' : 'text',
						'content' 	=> Codevz_Plus::pro_badge(),
						'title' => esc_html__( 'Archive title', 'codevz-plus' ),
						'attributes' => array( 'placeholder'	=> $name ),
						'setting_args' => array('transport' => 'postMessage')
					),
					array(
						'id' 	=> 'cat_' . $cpt,
						'type'  	=> $free ? 'content' : 'text',
						'content' 	=> Codevz_Plus::pro_badge(),
						'title' => esc_html__( 'Category slug', 'codevz-plus' ),
						'attributes' => array( 'placeholder'	=> $cpt . '/cat' ),
						'setting_args' => array('transport' => 'postMessage')
					),
					array(
						'id' 	=> 'cat_title_' . $cpt,
						'type'  	=> $free ? 'content' : 'text',
						'content' 	=> Codevz_Plus::pro_badge(),
						'title' => esc_html__( 'Category title', 'codevz-plus' ),
						'attributes' => array( 'placeholder'	=> 'Categories' ),
						'setting_args' => array('transport' => 'postMessage')
					),
					array(
						'id' 	=> 'tags_' . $cpt,
						'type'  	=> $free ? 'content' : 'text',
						'content' 	=> Codevz_Plus::pro_badge(),
						'title' => esc_html__( 'Tags slug', 'codevz-plus' ),
						'attributes' => array( 'placeholder'	=> $cpt . '/tags' ),
						'setting_args' => array('transport' => 'postMessage')
					),
					array(
						'id' 	=> 'tags_title_' . $cpt,
						'type'  	=> $free ? 'content' : 'text',
						'content' 	=> Codevz_Plus::pro_badge(),
						'title' => esc_html__( 'Tags title', 'codevz-plus' ),
						'attributes' => array( 'placeholder'	=> 'Tags' ),
						'setting_args' => array('transport' => 'postMessage')
					),
					array(
						'type'    => 'notice',
						'class'   => 'info',
						'content' => esc_html__( 'After changing slug, you should save options then go to Dashboard > Settings > Permalinks and save your permalinks.', 'codevz-plus' )
					),
				)
			) : null;

			$options[ 'post_type_' . $cpt ] = array(
				'name'   	=> 'post_type_' . $cpt,
				'title'  	=> $cpt_title,
				'sections' 	=> array(
					$portfolio_slug,

					array(
						'name'   => $cpt . '_settings',
						'title'  => $cpt_title . ' ' . esc_html__( 'Settings', 'codevz-plus' ),
						'fields' => wp_parse_args( 
							array(
								array(
									'id' 			=> 'layout_' . $cpt,
									'type' 			=> 'codevz_image_select',
									'title' 		=> esc_html__( 'Sidebar', 'codevz-plus' ),
									'help'  		=> $name . ' ' . esc_html__( 'archive and single pages', 'codevz-plus' ),
									'options' 		=> [
										'1' 			=> [ esc_html__( '~ Default ~', 'codevz-plus' ) 			, Codevz_Plus::$url . 'assets/img/sidebar-0.png' ],
										'ws' 			=> [ esc_html__( 'No Sidebar', 'codevz-plus' ) 			, Codevz_Plus::$url . 'assets/img/off.png' ],
										'bpnp' 			=> [ esc_html__( 'Fullwidth', 'codevz-plus' ) 			, Codevz_Plus::$url . 'assets/img/sidebar-2.png' ],
										'center'		=> [ esc_html__( 'Center Mode', 'codevz-plus' ) 			, Codevz_Plus::$url . 'assets/img/sidebar-13.png' ],
										'right' 		=> [ esc_html__( 'Right Sidebar', 'codevz-plus' ) 		, Codevz_Plus::$url . 'assets/img/sidebar-3.png' ],
										'right-s' 		=> [ esc_html__( 'Right Sidebar Small', 'codevz-plus' ) 	, Codevz_Plus::$url . 'assets/img/sidebar-4.png' ],
										'left' 			=> [ esc_html__( 'Left Sidebar', 'codevz-plus' ) 		, Codevz_Plus::$url . 'assets/img/sidebar-5.png' ],
										'left-s' 		=> [ esc_html__( 'Left Sidebar Small', 'codevz-plus' ) 	, Codevz_Plus::$url . 'assets/img/sidebar-6.png' ],
										'both-side' 	=> [ esc_html__( 'Both Sidebar', 'codevz-plus' ) . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ) 		, Codevz_Plus::$url . 'assets/img/sidebar-7.png' ],
										'both-side2' 	=> [ esc_html__( 'Both Sidebar Small', 'codevz-plus' ) . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ) 	, Codevz_Plus::$url . 'assets/img/sidebar-8.png' ],
										'both-right' 	=> [ esc_html__( 'Both Sidebar Right', 'codevz-plus' ) . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ) 	, Codevz_Plus::$url . 'assets/img/sidebar-9.png' ],
										'both-right2' 	=> [ esc_html__( 'Both Sidebar Right', 'codevz-plus' ) . ' 2' . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ) , Codevz_Plus::$url . 'assets/img/sidebar-10.png' ],
										'both-left' 	=> [ esc_html__( 'Both Sidebar Left', 'codevz-plus' ) . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ) 	, Codevz_Plus::$url . 'assets/img/sidebar-11.png' ],
										'both-left2' 	=> [ esc_html__( 'Both Sidebar Left', 'codevz-plus' ) . ' 2' . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' )  , Codevz_Plus::$url . 'assets/img/sidebar-12.png' ],
									],
									'default' 		=> '1',
									'attributes' 	=> [ 'data-depend-id' => 'layout_' . $cpt ]
								),
								array(
									'id' 			=> 'template_style_' . $cpt,
									'type' 			=> 'codevz_image_select',
									'title' 		=> esc_html__( 'Template', 'codevz-plus' ),
									'help'  		=> $name . ' ' . esc_html__( 'archive page, category page, tags page, etc.', 'codevz-plus' ),
									'options' 		=> [
										'1' 			=> [ esc_html__( 'Template', 'codevz-plus' ) . ' 1' 	, Codevz_Plus::$url . 'assets/img/posts-1.png' ],
										'1' 			=> [ esc_html__( 'Template', 'codevz-plus' ) . ' 2' 	, Codevz_Plus::$url . 'assets/img/posts-2.png' ],
										'6' 			=> [ esc_html__( 'Template', 'codevz-plus' ) . ' 6' 	, Codevz_Plus::$url . 'assets/img/posts-1-2.png' ],
										'3' 			=> [ esc_html__( 'Template', 'codevz-plus' ) . ' 3' 	, Codevz_Plus::$url . 'assets/img/posts-3.png' ],
										'4' 			=> [ esc_html__( 'Template', 'codevz-plus' ) . ' 4' 	, Codevz_Plus::$url . 'assets/img/posts-4.png' ],
										'5' 			=> [ esc_html__( 'Template', 'codevz-plus' ) . ' 5' 	, Codevz_Plus::$url . 'assets/img/posts-5.png' ],
										'7' 			=> [ esc_html__( 'Template', 'codevz-plus' ) . ' 7' 	, Codevz_Plus::$url . 'assets/img/posts-7.png' ],
										'8' 			=> [ esc_html__( 'Template', 'codevz-plus' ) . ' 8' 	, Codevz_Plus::$url . 'assets/img/posts-8.png' ],
										'9' 			=> [ esc_html__( 'Template', 'codevz-plus' ) . ' 9' 	, Codevz_Plus::$url . 'assets/img/posts-9.png' ],
										'10' 			=> [ esc_html__( 'Template', 'codevz-plus' ) . ' 10' , Codevz_Plus::$url . 'assets/img/posts-10.png' ],
										'11' 			=> [ esc_html__( 'Template', 'codevz-plus' ) . ' 11' , Codevz_Plus::$url . 'assets/img/posts-11.png' ],
										'12' 			=> [ esc_html__( 'Template', 'codevz-plus' ) . ' 12' , Codevz_Plus::$url . 'assets/img/posts-12.png' ],
										'13' 			=> [ esc_html__( 'Template', 'codevz-plus' ) . ' 13' , Codevz_Plus::$url . 'assets/img/posts-13.png' ],
										'14' 			=> [ esc_html__( 'Template', 'codevz-plus' ) . ' 14' , Codevz_Plus::$url . 'assets/img/posts-14.png' ],
										'x' 			=> [ esc_html__( 'Custom Template', 'codevz-plus' ) . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ) 	, Codevz_Plus::$url . 'assets/img/posts-x.png' ],
									],
									'default' 		=> '10',
									'attributes' 	=> [ 'data-depend-id' => 'template_style_' . $cpt ]
								),
								array(
									'id'    		=> 'template_' . $cpt,
									'type'    		=> $free ? 'content' : 'select',
									'content' 		=> Codevz_Plus::pro_badge(),
									'title'   		=> esc_html__( 'Custom Page', 'codevz-plus' ),
									'options'   	=> Codevz_Plus::$array_pages,
									'edit_link' 	=> true,
									'dependency'  	=> array( 'template_style_' . $cpt, '==', 'x' )
								),
								array(
									'id' 		=> 'desc_' . $cpt,
									'type' 		=> $free ? 'content' : 'textarea',
									'content' 	=> Codevz_Plus::pro_badge(),
									'title' 	=> esc_html__( 'Archive Description', 'codevz-plus' ),
									'help'  	=> esc_html__( 'Shortcode and custom HTML code allowed.', 'codevz-plus' ),
									'dependency'  	=> array( 'template_style_' . $cpt, '!=', 'x' )
								),
								array(
									'id'    	=> 'posts_per_page_' . $cpt,
									'type'  	=> 'slider',
									'title' 	=> esc_html__( 'Posts', 'codevz-plus' ),
									'options'	=> array( 'unit' => '', 'step' => 1, 'min' => -1, 'max' => 100 ),
									'dependency'  	=> array( 'template_style_' . $cpt, '!=', 'x' )
								),
								array(
									'id' 			=> 'order_' . $cpt,
									'type' 		=> $free ? 'content' : 'select',
									'content' 	=> Codevz_Plus::pro_badge(),
									'title' 		=> esc_html__( 'Order', 'codevz-plus' ),
									'options' 		=> [
										'' 				=> esc_html__( '~ Default ~', 'codevz-plus' ),
										'ASC' 			=> esc_html__( 'Ascending', 'codevz-plus' ),
										'DESC' 			=> esc_html__( 'Descending', 'codevz-plus' ),
									],
									'dependency'  	=> array( 'template_style_' . $cpt, '!=', 'x' )
								),
								array(
									'id' 			=> 'orderby_' . $cpt,
									'type' 		=> $free ? 'content' : 'select',
									'content' 	=> Codevz_Plus::pro_badge(),
									'title' 		=> esc_html__( 'Order by', 'codevz-plus' ),
									'options' 		=> [
										'' 				=> esc_html__( '~ Default ~', 'codevz-plus' ),
										'date' 			=> esc_html__( 'Date', 'codevz-plus' ),
										'ID' 			=> esc_html__( 'ID', 'codevz-plus' ),
										'title' 		=> esc_html__( 'Title', 'codevz-plus' ),
										'rand' 			=> esc_html__( 'Random', 'codevz-plus' ),
										'menu_order' 	=> esc_html__( 'Menu order', 'codevz-plus' ),
										'comment_count' => esc_html__( 'Reviews count', 'codevz-plus' ),
									],
									'dependency'  	=> array( 'template_style_' . $cpt, '!=', 'x' )
								),
								array(
									'id'    	=> '2x_height_image_' . $cpt,
									'type'  	=> $free ? 'content' : 'switcher',
									'content' 		=> Codevz_Plus::pro_badge(),
									'title' 	=> esc_html__( '2x Height Image', 'codevz-plus' ),
									'dependency'  => array( 'template_style_' . $cpt . '|template_style_' . $cpt, '!=|!=', 'x|3' )
								),
								array(
									'id' 		=> 'hover_icon_icon_' . $cpt,
									'type' 		=> 'icon',
									'title' 	=> esc_html__('Hover Icon', 'codevz-plus' ),
									'default' 	=> 'fa czico-109-link-symbol-1',
									'dependency'  	=> array( 'template_style_' . $cpt, '!=', 'x' ),
								),
								array(
									'id'    	=> 'post_excerpt_' . $cpt,
									'type'  	=> 'slider',
									'title'   	=> esc_html__( 'Excerpt', 'codevz-plus' ),
									'help' 	  	=> esc_html__( '-1 means full content without readmore button', 'codevz-plus' ),
									'options'	=> array( 'unit' => '', 'step' => 1, 'min' => -1, 'max' => 50 ),
									'default' 	=> '20',
									'dependency'  => array( 'template_style_' . $cpt . '|template_style_' . $cpt . '|template_style_' . $cpt . '|template_style_' . $cpt, '!=|!=|!=|!=', 'x|12|13|14' )
								),
								array(
									'id' 			=> $cpt . '_excerpt_type',
									'type' 			=> $free ? 'content' : 'select',
									'content' 		=> Codevz_Plus::pro_badge(),
									'title' 		=> esc_html__( 'Excerpt By', 'codevz-plus' ),
									'options' 		=> [
										'' 			=> esc_html__( 'Words', 'codevz-plus' ),
										'2' 		=> esc_html__( 'Characters', 'codevz-plus' ),
									],
									'dependency' 	=> array( 'template_style_' . $cpt . '|template_style_' . $cpt . '|template_style_' . $cpt . '|template_style_' . $cpt, '!=|!=|!=|!=', 'x|12|13|14' )
								),
								array(
									'id' 		=> $cpt . '_excerpt_dots',
									'type' 		=> 'text',
									'title' 	=> esc_html__( 'Excerpt Dots', 'codevz-plus' ),
									'default' 	=> ' ... ',
									'dependency' => array( 'template_style_' . $cpt . '|template_style_' . $cpt . '|template_style_' . $cpt . '|template_style_' . $cpt, '!=|!=|!=|!=', 'x|12|13|14' )
								),
								array(
									'id'          => 'readmore_icon_' . $cpt,
									'type'        => 'icon',
									'title'       => esc_html__('Read More', 'codevz-plus' ),
									'default'	  => 'fa fa-angle-right',
									'dependency' => array( 'template_style_' . $cpt . '|template_style_' . $cpt . '|template_style_' . $cpt . '|template_style_' . $cpt . '|hover_icon_icon_' . $cpt, '!=|!=|!=|!=|!=', 'x|12|13|14|-1' )
								),
								array(
									'id'          => 'readmore_' . $cpt,
									'type'        => 'text',
									'title'       => esc_html__( 'Read More', 'codevz-plus' ),
									'default'	    => 'Read More',
									'setting_args' => [ 'transport' => 'postMessage' ],
									'dependency'  => array( 'template_style_' . $cpt . '|post_excerpt_' . $cpt, '!=|!=', 'x|-1' )
								),
							),
							self::title_options( '_' . $cpt, '.cz-cpt-' . $cpt . ' ' )
						)
					),

					array(
						'name'   => $cpt . '_styles',
						'title'  => $cpt_title . ' ' . esc_html__( 'Styling', 'codevz-plus' ),
						'fields' => array(
							array(
								'id' 			=> '_css_posts_container_' . $cpt,
								'type' 			=> 'cz_sk',
								'title' 		=> esc_html__( 'Container', 'codevz-plus' ),
								'button' 		=> esc_html__( 'Container', 'codevz-plus' ),
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'settings' 		=> array( 'background', 'border' ),
								'selector' 		=> '.cz-cpt-' . $cpt . ' .cz_posts_container',
							),
							array(
								'id' 			=> '_css_posts_container_' . $cpt . '_tablet',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.cz-cpt-' . $cpt . ' .cz_posts_container',
							),
							array(
								'id' 			=> '_css_posts_container_' . $cpt . '_mobile',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.cz-cpt-' . $cpt . ' .cz_posts_container',
							),
							array(
								'id' 			=> '_css_overall_' . $cpt,
								'hover_id' 		=> '_css_overall_' . $cpt . '_hover',
								'type' 			=> 'cz_sk',
								'title' 		=> esc_html__( 'Posts', 'codevz-plus' ),
								'button' 		=> esc_html__( 'Posts', 'codevz-plus' ),
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'settings' 		=> array( 'background', 'border' ),
								'selector' 		=> '.cz-cpt-' . $cpt . ' .cz_default_loop > div',
							),
							array(
								'id' 			=> '_css_overall_' . $cpt . '_tablet',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.cz-cpt-' . $cpt . ' .cz_default_loop > div',
							),
							array(
								'id' 			=> '_css_overall_' . $cpt . '_mobile',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.cz-cpt-' . $cpt . ' .cz_default_loop > div',
							),
							array(
								'id' 			=> '_css_overall_' . $cpt . '_hover',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.cz-cpt-' . $cpt . ' .cz_default_loop:hover > div',
							),
							array(
								'id' 			=> '_css_' . $cpt . '_hover_icon',
								'hover_id' 		=> '_css_' . $cpt . '_hover_icon_hover',
								'type' 			=> 'cz_sk',
								'title' 		=> esc_html__( 'Icon', 'codevz-plus' ),
								'button' 		=> esc_html__( 'Icon', 'codevz-plus' ),
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'settings' 		=> array( 'color', 'background', 'border' ),
								'selector' 		=> '.cz-cpt-' . $cpt . ' article .cz_post_icon',
							),
							array(
								'id' 			=> '_css_' . $cpt . '_hover_icon_hover',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.cz-cpt-' . $cpt . ' article .cz_post_icon:hover',
							),
							array(
								'id' 			=> '_css_' . $cpt . '_image',
								'hover_id' 		=> '_css_' . $cpt . '_image_hover',
								'type' 			=> 'cz_sk',
								'title' 		=> esc_html__( 'Image', 'codevz-plus' ),
								'button' 		=> esc_html__( 'Image', 'codevz-plus' ),
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'settings' 		=> array( 'opacity', 'background', 'border' ),
								'selector' 		=> '.cz-cpt-' . $cpt . ' .cz_default_loop .cz_post_image, .cz-cpt-' . $cpt . ' .cz_post_svg',
							),
							array(
								'id' 			=> '_css_' . $cpt . '_image_tablet',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.cz-cpt-' . $cpt . ' .cz_default_loop .cz_post_image, .cz-cpt-' . $cpt . ' .cz_post_svg',
							),
							array(
								'id' 			=> '_css_' . $cpt . '_image_mobile',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.cz-cpt-' . $cpt . ' .cz_default_loop .cz_post_image, .cz-cpt-' . $cpt . ' .cz_post_svg',
							),
							array(
								'id' 			=> '_css_' . $cpt . '_image_hover',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.cz-cpt-' . $cpt . ' .cz_default_loop:hover .cz_post_image,.cz-cpt-' . $cpt . '  article:hover .cz_post_svg',
							),
							array(
								'id' 			=> '_css_' . $cpt . '_title',
								'hover_id' 		=> '_css_' . $cpt . '_title_hover',
								'type' 			=> 'cz_sk',
								'title' 		=> esc_html__( 'Title', 'codevz-plus' ),
								'button' 		=> esc_html__( 'Title', 'codevz-plus' ),
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'settings' 		=> array( 'color', 'background', 'font-size', 'line-height', 'border' ),
								'selector' 		=> '.cz-cpt-' . $cpt . ' .cz_default_loop .cz_post_title h3',
							),
							array(
								'id' 			=> '_css_' . $cpt . '_title_tablet',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.cz-cpt-' . $cpt . ' .cz_default_loop .cz_post_title h3',
							),
							array(
								'id' 			=> '_css_' . $cpt . '_title_mobile',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.cz-cpt-' . $cpt . ' .cz_default_loop .cz_post_title h3',
							),
							array(
								'id' 			=> '_css_' . $cpt . '_title_hover',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.cz-cpt-' . $cpt . ' .cz_default_loop .cz_post_title h3:hover',
							),
							array(
								'id' 			=> '_css_' . $cpt . '_meta_overall',
								'type' 			=> 'cz_sk',
								'title' 		=> esc_html__( 'Meta', 'codevz-plus' ),
								'button' 		=> esc_html__( 'Meta', 'codevz-plus' ),
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'settings' 		=> array( 'float', 'background', 'border' ),
								'selector' 		=> '.cz-cpt-' . $cpt . ' .cz_default_loop .cz_post_meta',
							),
							array(
								'id' 			=> '_css_' . $cpt . '_meta_overall_tablet',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.cz-cpt-' . $cpt . ' .cz_default_loop .cz_post_meta',
							),
							array(
								'id' 			=> '_css_' . $cpt . '_meta_overall_mobile',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.cz-cpt-' . $cpt . ' .cz_default_loop .cz_post_meta',
							),

							array(
								'id' 			=> '_css_readmore_' . $cpt,
								'hover_id' 		=> '_css_readmore_' . $cpt . '_hover',
								'type' 			=> 'cz_sk',
								'title' 		=> esc_html__( 'Read more', 'codevz-plus' ),
								'button' 		=> esc_html__( 'Read more', 'codevz-plus' ),
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'settings' 		=> array( 'float', 'color', 'background', 'font-size', 'border' ),
								'selector' 		=> '.cz-cpt-' . $cpt . ' .cz_readmore, .cz-cpt-' . $cpt . ' .more-link'
							),
							array(
								'id' 			=> '_css_readmore_' . $cpt . '_tablet',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.cz-cpt-' . $cpt . ' .cz_readmore, .cz-cpt-' . $cpt . ' .more-link'
							),
							array(
								'id' 			=> '_css_readmore_' . $cpt . '_mobile',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.cz-cpt-' . $cpt . ' .cz_readmore, .cz-cpt-' . $cpt . ' .more-link'
							),
							array(
								'id' 			=> '_css_readmore_' . $cpt . '_hover',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.cz-cpt-' . $cpt . ' .cz_readmore:hover, .cz-cpt-' . $cpt . ' .more-link:hover'
							),
							array(
								'id' 			=> '_css_readmore_i_' . $cpt,
								'hover_id' 		=> '_css_readmore_i_' . $cpt . '_hover',
								'type' 			=> 'cz_sk',
								'title' 		=> esc_html__( 'Icon', 'codevz-plus' ),
								'button' 		=> esc_html__( 'Icon', 'codevz-plus' ),
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'settings' 		=> array( 'color' ),
								'selector' 		=> '.cz-cpt-' . $cpt . ' .cz_readmore i, .cz-cpt-' . $cpt . ' .more-link',
							),
							array(
								'id' 			=> '_css_readmore_i_' . $cpt . '_hover',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.cz-cpt-' . $cpt . ' .cz_readmore:hover i, .cz-cpt-' . $cpt . ' .more-link:hover i',
							),

							array(
								'id' 			=> 'xtra_control_badge_' . $cpt . '_styling',
								'type' 			=> 'content',
								'content' 		=> Codevz_Plus::pro_badge(),
								'dependency' 	=> $free ? [] : [ 'x', '==', 'x' ]
							),
							array(
								'id' 			=> '_css_' . $cpt . '_avatar',
								'type' 			=> $free ? 'cz_sk_free' : 'cz_sk',
								'title' 		=> esc_html__( 'Avatar', 'codevz-plus' ),
								'button' 		=> esc_html__( 'Avatar', 'codevz-plus' ),
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'settings' 		=> array( 'background', 'width', 'height', 'border' ),
								'selector' 		=> '.cz-cpt-' . $cpt . ' .cz_default_loop .cz_post_author_avatar img',
							),
							array(
								'id' 			=> '_css_' . $cpt . '_avatar_tablet',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.cz-cpt-' . $cpt . ' .cz_default_loop .cz_post_author_avatar img',
							),
							array(
								'id' 			=> '_css_' . $cpt . '_avatar_mobile',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.cz-cpt-' . $cpt . ' .cz_default_loop .cz_post_author_avatar img',
							),
							array(
								'id' 			=> '_css_' . $cpt . '_author',
								'type' 			=> $free ? 'cz_sk_free' : 'cz_sk',
								'title' 		=> esc_html__( 'Author', 'codevz-plus' ),
								'button' 		=> esc_html__( 'Author', 'codevz-plus' ),
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'settings' 		=> array( 'color', 'font-weight' ),
								'selector' 		=> '.cz-cpt-' . $cpt . ' .cz_default_loop .cz_post_author_name',
							),
							array(
								'id' 			=> '_css_' . $cpt . '_author_tablet',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.cz-cpt-' . $cpt . ' .cz_default_loop .cz_post_author_name',
							),
							array(
								'id' 			=> '_css_' . $cpt . '_author_mobile',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.cz-cpt-' . $cpt . ' .cz_default_loop .cz_post_author_name',
							),
							array(
								'id' 			=> '_css_' . $cpt . '_date',
								'type' 			=> $free ? 'cz_sk_free' : 'cz_sk',
								'title' 		=> esc_html__( 'Date', 'codevz-plus' ),
								'button' 		=> esc_html__( 'Date', 'codevz-plus' ),
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'settings' 		=> array( 'color', 'font-style' ),
								'selector' 		=> '.cz-cpt-' . $cpt . ' .cz_default_loop .cz_post_date',
							),
							array(
								'id' 			=> '_css_' . $cpt . '_date_tablet',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.cz-cpt-' . $cpt . ' .cz_default_loop .cz_post_date',
							),
							array(
								'id' 			=> '_css_' . $cpt . '_date_mobile',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.cz-cpt-' . $cpt . ' .cz_default_loop .cz_post_date',
							),
							array(
								'id' 			=> '_css_' . $cpt . '_excerpt',
								'type' 			=> $free ? 'cz_sk_free' : 'cz_sk',
								'title' 		=> esc_html__( 'Excerpt', 'codevz-plus' ),
								'button' 		=> esc_html__( 'Excerpt', 'codevz-plus' ),
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'settings' 		=> array( 'text-align', 'color', 'line-height' ),
								'selector' 		=> '.cz-cpt-' . $cpt . ' .cz_default_loop .cz_post_excerpt',
							),
							array(
								'id' 			=> '_css_' . $cpt . '_excerpt_tablet',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.cz-cpt-' . $cpt . ' .cz_default_loop .cz_post_excerpt',
							),
							array(
								'id' 			=> '_css_' . $cpt . '_excerpt_mobile',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.cz-cpt-' . $cpt . ' .cz_default_loop .cz_post_excerpt',
							),

						),
					),

					array(
						'name'   => $cpt . '_single_settings',
						'title'  => esc_html__( 'Single Settings', 'codevz-plus' ),
						'fields' => array(
							array(
								'id' 	=> 'meta_data_' . $cpt,
								'type' 	=> 'checkbox',
								'title' => esc_html__( 'Features', 'codevz-plus' ),
								'options' => array(
									'image'		=> esc_html__( 'Post Image', 'codevz-plus' ),
									'author'	=> esc_html__( 'Author', 'codevz-plus' ),
									'date'		=> esc_html__( 'Date', 'codevz-plus' ),
									'cats'		=> esc_html__( 'Categories', 'codevz-plus' ),
									'tags'		=> esc_html__( 'Tags', 'codevz-plus' ),
									'next_prev' => esc_html__( 'Next Prev Posts', 'codevz-plus' ),
									'views' 	=> esc_html__( 'Post views', 'codevz-plus' ),
								),
								'default' => array( 'image','date','author','cats','tags','author_box', 'next_prev' )
							),
							array(
								'id' 			=> 'related_' . $cpt . '_col',
								'type' 			=> 'codevz_image_select',
								'title' 		=> esc_html__( 'Related posts', 'codevz-plus' ),
								'options' 		=> [
									'none' 			=> [ esc_html__( 'None', 'codevz-plus' ) 		, Codevz_Plus::$url . 'assets/img/off.png' ],
									's6' 			=> [ '2 ' . esc_html__( 'Columns', 'codevz-plus' ) 		, Codevz_Plus::$url . 'assets/img/cols-2.png' ],
									's4' 			=> [ '3 ' . esc_html__( 'Columns', 'codevz-plus' ) 		, Codevz_Plus::$url . 'assets/img/cols-3.png' ],
									's3' 			=> [ '4 ' . esc_html__( 'Columns', 'codevz-plus' ) 		, Codevz_Plus::$url . 'assets/img/cols-4.png' ],
								],
								'default' 		=> 's4',
								'dependency'  => array( 'related_' . $cpt . '_col', '!=', 'none' ),
							),
							array(
								'id'          	=> 'related_posts_' . $cpt,
								'type'        	=> 'text',
								'title'       	=> esc_html__('Related title', 'codevz-plus' ),
								'default'		=> 'You may also like ...',
								'setting_args' 	=> array('transport' => 'postMessage'),
								'dependency'  	=> array( 'related_' . $cpt . '_col', '!=', 'none' ),
							),
							array(
								'id' 			=> 'prev_' . $cpt,
								'type' 			=> 'text',
								'title' 		=> esc_html__( 'Prev Surtitle', 'codevz-plus' ),
								'default' 		=> 'Previous',
							),
							array(
								'id' 			=> 'next_' . $cpt,
								'type' 			=> 'text',
								'title' 		=> esc_html__( 'Next Surtitle', 'codevz-plus' ),
								'default' 		=> 'Next',
							),
						),
					),

					array(
						'name'   => $cpt . '_single_styles',
						'title'  => esc_html__( 'Single Styling', 'codevz-plus' ),
						'fields' => array(
							[
								'type' 			=> 'notice',
								'class' 		=> 'info',
								'content' 		=> esc_html__( 'General styling for all post types single posts located in the ', 'codevz-plus' ) . '<br /><a href="#" onclick="wp.customize.section( \'codevz_theme_options-single_styles\' ).focus()" style="color:white">' . esc_html__( 'Blog > Single Styling', 'codevz-plus' ) . '</a>'
							],

							array(
								'id' 			=> $cpt . '_custom_single_sk',
								'type' 			=> $free ? 'content' : 'switcher',
								'content' 		=> Codevz_Plus::pro_badge(),
								'title' 		=> esc_html__( 'Custom Single Styles?', 'codevz-plus' ),
								'help' 			=> esc_html__( 'Enable this option you will be able to override single posts styling for this post type.', 'codevz-plus' ),
							),
							array(
								'id' 			=> '_css_single_' . $cpt . '_con',
								'type' 			=> 'cz_sk',
								'title' 		=> esc_html__( 'Container', 'codevz-plus' ),
								'button' 		=> esc_html__( 'Container', 'codevz-plus' ),
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'settings' 		=> array( 'color', 'background', 'border' ),
								'selector' 		=> '.single-' . $cpt . '-sk .single_con',
								'dependency' 	=> [ $cpt . '_custom_single_sk', '!=', '' ]
							),
							array(
								'id' 			=> '_css_single_' . $cpt . '_con_tablet','type' => 'cz_sk_hidden','setting_args' => [ 'transport' => 'postMessage' ],
								'selector' 		=> '.single-' . $cpt . '-sk .single_con',
							),
							array(
								'id' 			=> '_css_single_' . $cpt . '_con_mobile','type' => 'cz_sk_hidden','setting_args' => [ 'transport' => 'postMessage' ],
								'selector' 		=> '.single-' . $cpt . '-sk .single_con',
							),
							array(
								'id' 			=> '_css_single_' . $cpt . '_title',
								'type' 			=> 'cz_sk',
								'title' 		=> esc_html__( 'Title', 'codevz-plus' ),
								'button' 		=> esc_html__( 'Title', 'codevz-plus' ),
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'settings' 		=> array( 'color', 'line-height' ),
								'selector' 		=> '.single-' . $cpt . '-sk .xtra-post-title',
								'dependency' 	=> [ $cpt . '_custom_single_sk', '!=', '' ]
							),
							array(
								'id' 			=> '_css_single_' . $cpt . '_title_tablet',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.single-' . $cpt . '-sk .xtra-post-title',
							),
							array(
								'id' 			=> '_css_single_' . $cpt . '_title_mobile',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.single-' . $cpt . '-sk .xtra-post-title',
							),
							array(
								'id' 			=> '_css_single_' . $cpt . '_title_date',
								'hover_id' 		=> '_css_single_' . $cpt . '_title_date_hover',
								'type' 			=> 'cz_sk',
								'title' 		=> esc_html__( 'Date', 'codevz-plus' ),
								'button' 		=> esc_html__( 'Date', 'codevz-plus' ),
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'settings' 		=> array( 'color', 'background', 'border' ),
								'selector' 		=> '.single-' . $cpt . '-sk .xtra-post-title-date a',
								'dependency' 	=> [ $cpt . '_custom_single_sk', '!=', '' ]
							),
							array(
								'id' 			=> '_css_single_' . $cpt . '_title_date_hover',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.single-' . $cpt . '-sk .xtra-post-title-date a:hover',
								'dependency' 	=> [ $cpt . '_custom_single_sk', '!=', '' ]
							),
							array(
								'id' 			=> '_css_single_' . $cpt . '_fi',
								'type' 			=> 'cz_sk',
								'title' 		=> esc_html__( 'Image', 'codevz-plus' ),
								'button' 		=> esc_html__( 'Image', 'codevz-plus' ),
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'settings' 		=> array( 'background', 'border' ),
								'selector' 		=> '.single-' . $cpt . '-sk .single_con .cz_single_fi img',
								'dependency' 	=> [ $cpt . '_custom_single_sk', '!=', '' ]
							),
							array(
								'id' 			=> '_css_single_' . $cpt . '_fi_tablet',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.single-' . $cpt . '-sk .single_con .cz_single_fi img',
							),
							array(
								'id' 			=> '_css_single_' . $cpt . '_fi_mobile',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.single-' . $cpt . '-sk .single_con .cz_single_fi img',
							),
							array(
								'id' 			=> '_css_single_' . $cpt . '_tags_categories',
								'hover_id' 		=> '_css_single_' . $cpt . '_tags_categories_hover',
								'type' 			=> 'cz_sk',
								'title' 		=> esc_html__( 'Meta', 'codevz-plus' ),
								'button' 		=> esc_html__( 'Meta', 'codevz-plus' ),
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'settings' 		=> array( 'color', 'background', 'font-size', 'border' ),
								'selector' 		=> '.single-' . $cpt . '-sk .tagcloud a, .single-' . $cpt . '-sk .cz_post_cat a',
								'dependency' 	=> [ $cpt . '_custom_single_sk', '!=', '' ]
							),
							array(
								'id' 			=> '_css_single_' . $cpt . '_tags_categories_tablet',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.single-' . $cpt . '-sk .tagcloud a, .single-' . $cpt . '-sk .cz_post_cat a',
								'dependency' 	=> [ $cpt . '_custom_single_sk', '!=', '' ]
							),
							array(
								'id' 			=> '_css_single_' . $cpt . '_tags_categories_mobile',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.single-' . $cpt . '-sk .tagcloud a, .single-' . $cpt . '-sk .cz_post_cat a'
							),
							array(
								'id' 			=> '_css_single_' . $cpt . '_tags_categories_hover',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.single-' . $cpt . '-sk .tagcloud a:hover, .single-' . $cpt . '-sk .cz_post_cat a:hover'
							),
							array(
								'id' 			=> '_css_single_' . $cpt . '_tags_categories_icon',
								'type' 			=> 'cz_sk',
								'title' 		=> esc_html__( 'Meta Icon', 'codevz-plus' ),
								'button' 		=> esc_html__( 'Meta Icon', 'codevz-plus' ),
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'settings' 		=> array( 'color', 'background', 'font-size', 'border' ),
								'selector' 		=> '.single-' . $cpt . '-sk .single_con .tagcloud a:first-child, .single-' . $cpt . '-sk .single_con .cz_post_cat a:first-child',
								'dependency' 	=> [ $cpt . '_custom_single_sk', '!=', '' ]
							),
							array(
								'id' 			=> '_css_single_' . $cpt . '_tags_categories_icon_tablet',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.single-' . $cpt . '-sk .single_con .tagcloud a:first-child, .single-' . $cpt . '-sk .single_con .cz_post_cat a:first-child'
							),
							array(
								'id' 			=> '_css_single_' . $cpt . '_tags_categories_icon_mobile',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.single-' . $cpt . '-sk .single_con .tagcloud a:first-child, .single-' . $cpt . '-sk .single_con .cz_post_cat a:first-child'
							),
							array(
								'type'    		=> 'notice',
								'class'   		=> 'info xtra-notice',
								'content' 		=> '<i class="fas fa-signs-post mr8"></i>' . esc_html__( 'Next & Previous Posts', 'codevz-plus' ),
								'dependency' 	=> [ $cpt . '_custom_single_sk', '!=', '' ]
							),
							array(
								'id' 			=> '_css_single_' . $cpt . '_next_prev_con',
								'type' 			=> 'cz_sk',
								'title' 		=> esc_html__( 'Container', 'codevz-plus' ),
								'button' 		=> esc_html__( 'Container', 'codevz-plus' ),
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'settings' 		=> array( 'background', 'border' ),
								'selector' 		=> '.single-' . $cpt . '-sk .next_prev',
								'dependency' 	=> [ $cpt . '_custom_single_sk', '!=', '' ]
							),
							array(
								'id' 			=> '_css_single_' . $cpt . '_next_prev_con_tablet',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.single-' . $cpt . '-sk .next_prev'
							),
							array(
								'id' 			=> '_css_single_' . $cpt . '_next_prev_con_mobile',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.single-' . $cpt . '-sk .next_prev'
							),
							array(
								'id' 			=> '_css_single_' . $cpt . '_next_prev_icons',
								'hover_id' 		=> '_css_single_' . $cpt . '_next_prev_icons_hover',
								'type' 			=> 'cz_sk',
								'title' 		=> esc_html__( 'Icons', 'codevz-plus' ),
								'button' 		=> esc_html__( 'Icons', 'codevz-plus' ),
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'settings' 		=> array( 'color', 'background', 'font-size', 'border' ),
								'selector' 		=> '.single-' . $cpt . '-sk .next_prev .previous i,.single-' . $cpt . '-sk .next_prev .next i',
								'dependency' 	=> [ $cpt . '_custom_single_sk', '!=', '' ]
							),
							array(
								'id' 			=> '_css_single_' . $cpt . '_next_prev_icons_tablet',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.single-' . $cpt . '-sk .next_prev .previous i,.single-' . $cpt . '-sk .next_prev .next i'
							),
							array(
								'id' 			=> '_css_single_' . $cpt . '_next_prev_icons_mobile',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.single-' . $cpt . '-sk .next_prev .previous i,.single-' . $cpt . '-sk .next_prev .next i'
							),
							array(
								'id' 			=> '_css_single_' . $cpt . '_next_prev_icons_hover',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.single-' . $cpt . '-sk .next_prev .previous:hover i,.single-' . $cpt . '-sk .next_prev .next:hover i'
							),
							array(
								'id' 			=> '_css_single_' . $cpt . '_next_prev_titles',
								'hover_id' 		=> '_css_single_' . $cpt . '_next_prev_titles_hover',
								'type' 			=> 'cz_sk',
								'title' 		=> esc_html__( 'Titles', 'codevz-plus' ),
								'button' 		=> esc_html__( 'Titles', 'codevz-plus' ),
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'settings' 		=> array( 'color', 'line-height' ),
								'selector' 		=> '.single-' . $cpt . '-sk .next_prev h4',
								'dependency' 	=> [ $cpt . '_custom_single_sk', '!=', '' ]
							),
							array(
								'id' 			=> '_css_single_' . $cpt . '_next_prev_titles_tablet',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.single-' . $cpt . '-sk .next_prev h4'
							),
							array(
								'id' 			=> '_css_single_' . $cpt . '_next_prev_titles_mobile',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.single-' . $cpt . '-sk .next_prev h4'
							),
							array(
								'id' 			=> '_css_single_' . $cpt . '_next_prev_titles_hover',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.single-' . $cpt . '-sk .next_prev li:hover h4'
							),
							array(
								'id' 			=> '_css_single_' . $cpt . '_next_prev_surtitle',
								'type' 			=> 'cz_sk',
								'title' 		=> esc_html__( 'Sur Titles', 'codevz-plus' ),
								'button' 		=> esc_html__( 'Sur Titles', 'codevz-plus' ),
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'settings' 		=> array( 'color', 'background', 'font-size', 'border' ),
								'selector' 		=> '.single-' . $cpt . '-sk .next_prev h4 small',
								'dependency' 	=> [ $cpt . '_custom_single_sk', '!=', '' ]
							),
							array(
								'id' 			=> '_css_single_' . $cpt . '_next_prev_surtitle_tablet',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.single-' . $cpt . '-sk .next_prev h4 small'
							),
							array(
								'id' 			=> '_css_single_' . $cpt . '_next_prev_surtitle_mobile',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.single-' . $cpt . '-sk .next_prev h4 small'
							),

							array(
								'type'    		=> 'notice',
								'class'   		=> 'info xtra-notice',
								'content' 		=> '<i class="fas fa-sliders mr8"></i>' . esc_html__( 'Related Posts & Comments', 'codevz-plus' ),
								'dependency' 	=> [ $cpt . '_custom_single_sk', '!=', '' ]
							),
							array(
								'id' 			=> '_css_single_' . $cpt . '_related_posts_con',
								'type' 			=> 'cz_sk',
								'title' 		=> esc_html__( 'Container', 'codevz-plus' ),
								'button' 		=> esc_html__( 'Container', 'codevz-plus' ),
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'settings' 		=> array( 'background', 'border' ),
								'selector' 		=> '.single-' . $cpt . '-sk .xtra-comments,.single-' . $cpt . '-sk .content.cz_related_posts,.single-' . $cpt . '-sk .cz_author_box,.single-' . $cpt . '-sk .related.products,.single-' . $cpt . '-sk .upsells.products,.single-' . $cpt . '-sk .up-sells.products',
								'dependency' 	=> [ $cpt . '_custom_single_sk', '!=', '' ]
							),
							array(
								'id' 			=> '_css_single_' . $cpt . '_related_posts_con_tablet',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.single-' . $cpt . '-sk .xtra-comments,.single-' . $cpt . '-sk .content.cz_related_posts,.single-' . $cpt . '-sk .cz_author_box,.single-' . $cpt . '-sk .related.products,.single-' . $cpt . '-sk .upsells.products,.single-' . $cpt . '-sk .up-sells.products'
							),
							array(
								'id' 			=> '_css_single_' . $cpt . '_related_posts_con_mobile',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.single-' . $cpt . '-sk .xtra-comments,.single-' . $cpt . '-sk .content.cz_related_posts,.single-' . $cpt . '-sk .cz_author_box,.single-' . $cpt . '-sk .related.products,.single-' . $cpt . '-sk .upsells.products,.single-' . $cpt . '-sk .up-sells.products'
							),
							array(
								'id' 			=> '_css_single_' . $cpt . '_related_posts_sec_title',
								'type' 			=> 'cz_sk',
								'title' 		=> esc_html__( 'Title', 'codevz-plus' ),
								'button' 		=> esc_html__( 'Title', 'codevz-plus' ),
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'settings' 		=> array( 'color', 'background', 'font-size', 'border' ),
								'selector' 		=> '.single-' . $cpt . '-sk #comments > h3,.single-' . $cpt . '-sk .content.cz_related_posts > h4,.single-' . $cpt . '-sk .cz_author_box h4,.single-' . $cpt . '-sk .related.products > h2,.single-' . $cpt . '-sk .upsells.products > h2,.single-' . $cpt . '-sk .up-sells.products > h2',
								'dependency' 	=> [ $cpt . '_custom_single_sk', '!=', '' ]
							),
							array(
								'id' 			=> '_css_single_' . $cpt . '_related_posts_sec_title_tablet',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.single-' . $cpt . '-sk #comments > h3,.single-' . $cpt . '-sk .content.cz_related_posts > h4,.single-' . $cpt . '-sk .cz_author_box h4,.single-' . $cpt . '-sk .related.products > h2,.single-' . $cpt . '-sk .upsells.products > h2,.single-' . $cpt . '-sk .up-sells.products > h2'
							),
							array(
								'id' 			=> '_css_single_' . $cpt . '_related_posts_sec_title_mobile',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.single-' . $cpt . '-sk #comments > h3,.single-' . $cpt . '-sk .content.cz_related_posts > h4,.single-' . $cpt . '-sk .cz_author_box h4,.single-' . $cpt . '-sk .related.products > h2,.single-' . $cpt . '-sk .upsells.products > h2,.single-' . $cpt . '-sk .up-sells.products > h2'
							),
							array(
								'id' 			=> '_css_single_' . $cpt . '_related_posts_sec_title_before',
								'type'          => $free ? 'cz_sk_free' : 'cz_sk',
								'title' 		=> esc_html__( 'Title shape', 'codevz-plus' ) . ' 1',
								'button' 		=> esc_html__( 'Title shape', 'codevz-plus' ) . ' 1',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'settings' 		=> array( 'background', 'width', 'height', 'border', 'position', 'top', 'left', 'bottom', 'right' ),
								'selector' 		=> '.single-' . $cpt . '-sk #comments > h3:before,.single-' . $cpt . '-sk .content.cz_related_posts > h4:before,.single-' . $cpt . '-sk .cz_author_box h4:before,.single-' . $cpt . '-sk .related.products > h2:before,.single-' . $cpt . '-sk .upsells.products > h2:before,.single-' . $cpt . '-sk .up-sells.products > h2:before',
								'dependency' 	=> [ $cpt . '_custom_single_sk', '!=', '' ]
							),
							array(
								'id' 			=> '_css_single_' . $cpt . '_related_posts_sec_title_before_tablet',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.single-' . $cpt . '-sk #comments > h3:before,.single-' . $cpt . '-sk .content.cz_related_posts > h4:before,.single-' . $cpt . '-sk .cz_author_box h4:before,.single-' . $cpt . '-sk .related.products > h2:before,.single-' . $cpt . '-sk .upsells.products > h2:before,.single-' . $cpt . '-sk .up-sells.products > h2:before'
							),
							array(
								'id' 			=> '_css_single_' . $cpt . '_related_posts_sec_title_before_mobile',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.single-' . $cpt . '-sk #comments > h3:before,.single-' . $cpt . '-sk .content.cz_related_posts > h4:before,.single-' . $cpt . '-sk .cz_author_box h4:before,.single-' . $cpt . '-sk .related.products > h2:before,.single-' . $cpt . '-sk .upsells.products > h2:before,.single-' . $cpt . '-sk .up-sells.products > h2:before'
							),
							array(
								'id' 			=> '_css_single_' . $cpt . '_related_posts_sec_title_after',
								'type'          => $free ? 'cz_sk_free' : 'cz_sk',
								'title' 		=> esc_html__( 'Title shape', 'codevz-plus' ) . ' 2',
								'button' 		=> esc_html__( 'Title shape', 'codevz-plus' ) . ' 2',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'settings' 		=> array( 'background', 'width', 'height', 'border', 'position', 'top', 'left', 'bottom', 'right' ),
								'selector' 		=> '.single-' . $cpt . '-sk #comments > h3:after,.single-' . $cpt . '-sk .content.cz_related_posts > h4:after,.single-' . $cpt . '-sk .cz_author_box h4:after,.single-' . $cpt . '-sk .related.products > h2:after,.single-' . $cpt . '-sk .upsells.products > h2:after,.single-' . $cpt . '-sk .up-sells.products > h2:after',
								'dependency' 	=> [ $cpt . '_custom_single_sk', '!=', '' ]
							),
							array(
								'id' 			=> '_css_single_' . $cpt . '_related_posts_sec_title_after_tablet',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.single-' . $cpt . '-sk #comments > h3:after,.single-' . $cpt . '-sk .content.cz_related_posts > h4:after,.single-' . $cpt . '-sk .cz_author_box h4:after,.single-' . $cpt . '-sk .related.products > h2:after,.single-' . $cpt . '-sk .upsells.products > h2:after,.single-' . $cpt . '-sk .up-sells.products > h2:after'
							),
							array(
								'id' 			=> '_css_single_' . $cpt . '_related_posts_sec_title_after_mobile',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.single-' . $cpt . '-sk #comments > h3:after,.single-' . $cpt . '-sk .content.cz_related_posts > h4:after,.single-' . $cpt . '-sk .cz_author_box h4:after,.single-' . $cpt . '-sk .related.products > h2:after,.single-' . $cpt . '-sk .upsells.products > h2:after,.single-' . $cpt . '-sk .up-sells.products > h2:after'
							),
							array(
								'id' 			=> '_css_single_' . $cpt . '_related_posts',
								'hover_id' 		=> '_css_single_' . $cpt . '_related_posts_hover',
								'type' 			=> 'cz_sk',
								'title' 		=> esc_html__( 'Posts', 'codevz-plus' ),
								'button' 		=> esc_html__( 'Posts', 'codevz-plus' ),
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'settings' 		=> array( 'background', 'border' ),
								'selector' 		=> '.single-' . $cpt . '-sk .cz_related_posts .cz_related_post > div',
								'dependency' 	=> [ $cpt . '_custom_single_sk', '!=', '' ]
							),
							array(
								'id' => '_css_single_' . $cpt . '_related_posts_tablet',
								'type' => 'cz_sk_hidden',
								'setting_args' => [ 'transport' => 'postMessage' ],
								'selector' => '.single-' . $cpt . '-sk .cz_related_posts .cz_related_post > div'
							),
							array(
								'id' => '_css_single_' . $cpt . '_related_posts_mobile',
								'type' => 'cz_sk_hidden',
								'setting_args' => [ 'transport' => 'postMessage' ],
								'selector' => '.single-' . $cpt . '-sk .cz_related_posts .cz_related_post > div'
							),
							array(
								'id' 			=> '_css_single_' . $cpt . '_related_posts_hover',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.single-' . $cpt . '-sk .cz_related_posts .cz_related_post:hover > div',
								'dependency' 	=> [ $cpt . '_custom_single_sk', '!=', '' ]
							),
							array(
								'id'      	=> '_css_single_' . $cpt . '_related_posts_img',
								'hover_id' 	=> '_css_single_' . $cpt . '_related_posts_img_hover',
								'type'      => 'cz_sk',
								'title'    => esc_html__( 'Images', 'codevz-plus' ),
								'button'    => esc_html__( 'Images', 'codevz-plus' ),
								'setting_args'  => [ 'transport' => 'postMessage' ],
								'settings'    => array( 'background', 'border' ),
								'selector'    => '.single-' . $cpt . '-sk .cz_related_posts .cz_related_post .cz_post_image',
								'dependency' 	=> [ $cpt . '_custom_single_sk', '!=', '' ]
							),
							array(
								'id' => '_css_single_' . $cpt . '_related_posts_img_tablet',
								'type' => 'cz_sk_hidden',
								'setting_args' => [ 'transport' => 'postMessage' ],
								'selector'    => '.single-' . $cpt . '-sk .cz_related_posts .cz_related_post .cz_post_image'
							),
							array(
								'id' => '_css_single_' . $cpt . '_related_posts_img_mobile',
								'type' => 'cz_sk_hidden',
								'setting_args' => [ 'transport' => 'postMessage' ],
								'selector'    => '.single-' . $cpt . '-sk .cz_related_posts .cz_related_post .cz_post_image'
							),
							array(
								'id' => '_css_single_' . $cpt . '_related_posts_img_hover',
								'type' => 'cz_sk_hidden',
								'setting_args' => [ 'transport' => 'postMessage' ],
								'selector'    => '.single-' . $cpt . '-sk .cz_related_posts .cz_related_post:hover .cz_post_image',
								'dependency' 	=> [ $cpt . '_custom_single_sk', '!=', '' ]
							),
							array(
								'id' 			=> '_css_single_' . $cpt . '_related_posts_title',
								'hover_id' 		=> '_css_single_' . $cpt . '_related_posts_title_hover',
								'type' 			=> 'cz_sk',
								'title' 		=> esc_html__( 'Titles', 'codevz-plus' ),
								'button' 		=> esc_html__( 'Titles', 'codevz-plus' ),
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'settings' 		=> array( 'color', 'line-height' ),
								'selector' 		=> '.single-' . $cpt . '-sk .cz_related_posts .cz_related_post h3',
								'dependency' 	=> [ $cpt . '_custom_single_sk', '!=', '' ]
							),
							array(
								'id' 			=> '_css_single_' . $cpt . '_related_posts_title_tablet',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.single-' . $cpt . '-sk .cz_related_posts .cz_related_post h3'
							),
							array(
								'id' 			=> '_css_single_' . $cpt . '_related_posts_title_mobile',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.single-' . $cpt . '-sk .cz_related_posts .cz_related_post h3'
							),
							array(
								'id' 			=> '_css_single_' . $cpt . '_related_posts_title_hover',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.single-' . $cpt . '-sk .cz_related_posts .cz_related_post:hover h3'
							),
							array(
								'id' 			=> '_css_single_' . $cpt . '_related_posts_meta',
								'type' 			=> 'cz_sk',
								'title' 		=> esc_html__( 'Meta', 'codevz-plus' ),
								'button' 		=> esc_html__( 'Meta', 'codevz-plus' ),
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'settings' 		=> array( 'color' ),
								'selector' 		=> '.single-' . $cpt . '-sk .cz_related_posts .cz_related_post_date',
								'dependency' 	=> [ $cpt . '_custom_single_sk', '!=', '' ]
							),
							array(
								'id' 			=> '_css_single_' . $cpt . '_related_posts_meta_mobile',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.single-' . $cpt . '-sk .cz_related_posts .cz_related_post_date'
							),
							array(
								'id' 			=> '_css_single_' . $cpt . '_related_posts_meta_links',
								'hover_id' 		=> '_css_single_' . $cpt . '_related_posts_meta_links_hover',
								'type' 			=> 'cz_sk',
								'title' 		=> esc_html__( 'Meta Links', 'codevz-plus' ),
								'button' 		=> esc_html__( 'Meta Links', 'codevz-plus' ),
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'settings' 		=> array( 'color' ),
								'selector' 		=> '.single-' . $cpt . '-sk .cz_related_posts .cz_related_post_date a',
								'dependency' 	=> [ $cpt . '_custom_single_sk', '!=', '' ]
							),
							array(
								'id' 			=> '_css_single_' . $cpt . '_related_posts_meta_links_mobile',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.single-' . $cpt . '-sk .cz_related_posts .cz_related_post_date a'
							),
							array(
								'id' 			=> '_css_single_' . $cpt . '_related_posts_meta_links_hover',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.single-' . $cpt . '-sk .cz_related_posts .cz_related_post_date a:hover'
							),
							array(
								'id' 			=> '_css_single_' . $cpt . '_comments_li',
								'type' 			=> 'cz_sk',
								'title' 		=> esc_html__( 'Comments', 'codevz-plus' ),
								'button' 		=> esc_html__( 'Comments', 'codevz-plus' ),
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'settings' 		=> array( 'background', 'border' ),
								'selector' 		=> '.single-' . $cpt . '-sk .xtra-comments .commentlist li article',
								'dependency' 	=> [ $cpt . '_custom_single_sk', '!=', '' ]
							),
							array(
								'id' 			=> '_css_single_' . $cpt . '_comments_li_tablet',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.single-' . $cpt . '-sk .xtra-comments .commentlist li article'
							),
							array(
								'id' 			=> '_css_single_' . $cpt . '_comments_li_mobile',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.single-' . $cpt . '-sk .xtra-comments .commentlist li article'
							),
						),
					),


				)
			);
		}

		// bbpress options
		if ( function_exists( 'is_bbpress' ) || $all ) {
			$options[ 'post_type_bbpress' ] = array(
				'name'   => 'post_type_bbpress',
				'title'  => esc_html__( 'BBPress', 'codevz-plus' ),
				'fields' => wp_parse_args( 
					array(
						array(
							'id' 			=> 'layout_bbpress',
							'type' 			=> 'codevz_image_select',
							'title' 		=> esc_html__( 'Sidebar', 'codevz-plus' ),
							'help'  		=> esc_html__( 'On all bbpress pages', 'codevz-plus' ),
							'options' 		=> [
								'1' 			=> [ esc_html__( '~ Default ~', 'codevz-plus' ) 			, Codevz_Plus::$url . 'assets/img/sidebar-0.png' ],
								'ws' 			=> [ esc_html__( 'No Sidebar', 'codevz-plus' ) 			, Codevz_Plus::$url . 'assets/img/off.png' ],
								'bpnp' 			=> [ esc_html__( 'Fullwidth', 'codevz-plus' ) 			, Codevz_Plus::$url . 'assets/img/sidebar-2.png' ],
								'center'		=> [ esc_html__( 'Center Mode', 'codevz-plus' ) 			, Codevz_Plus::$url . 'assets/img/sidebar-13.png' ],
								'right' 		=> [ esc_html__( 'Right Sidebar', 'codevz-plus' ) 		, Codevz_Plus::$url . 'assets/img/sidebar-3.png' ],
								'right-s' 		=> [ esc_html__( 'Right Sidebar Small', 'codevz-plus' ) 	, Codevz_Plus::$url . 'assets/img/sidebar-4.png' ],
								'left' 			=> [ esc_html__( 'Left Sidebar', 'codevz-plus' ) 		, Codevz_Plus::$url . 'assets/img/sidebar-5.png' ],
								'left-s' 		=> [ esc_html__( 'Left Sidebar Small', 'codevz-plus' ) 	, Codevz_Plus::$url . 'assets/img/sidebar-6.png' ],
								'both-side' 	=> [ esc_html__( 'Both Sidebar', 'codevz-plus' ) . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ) 		, Codevz_Plus::$url . 'assets/img/sidebar-7.png' ],
								'both-side2' 	=> [ esc_html__( 'Both Sidebar Small', 'codevz-plus' ) . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ) 	, Codevz_Plus::$url . 'assets/img/sidebar-8.png' ],
								'both-right' 	=> [ esc_html__( 'Both Sidebar Right', 'codevz-plus' ) . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ) 	, Codevz_Plus::$url . 'assets/img/sidebar-9.png' ],
								'both-right2' 	=> [ esc_html__( 'Both Sidebar Right', 'codevz-plus' ) . ' 2' . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ) , Codevz_Plus::$url . 'assets/img/sidebar-10.png' ],
								'both-left' 	=> [ esc_html__( 'Both Sidebar Left', 'codevz-plus' ) . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ) 	, Codevz_Plus::$url . 'assets/img/sidebar-11.png' ],
								'both-left2' 	=> [ esc_html__( 'Both Sidebar Left', 'codevz-plus' ) . ' 2' . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' )  , Codevz_Plus::$url . 'assets/img/sidebar-12.png' ],
							],
							'default' 		=> '1',
							'attributes' 	=> [ 'data-depend-id' => 'layout_bbpress' ]
						),
						array(
							'type'    		=> 'notice',
							'class'   		=> 'info',
							'content' 		=> '<i class="fas fa-brush mr8"></i>' . esc_html__( 'Styling', 'codevz-plus' )
						),
						array(
							'id' 			=> '_css_bbpress_search_container',
							'type' 			=> 'cz_sk',
							'title' 		=> esc_html__( 'Search', 'codevz-plus' ),
							'button' 		=> esc_html__( 'Search', 'codevz-plus' ),
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'settings' 		=> array( 'background', 'border' ),
							'selector' 		=> '.bbp-search-form'
						),
						array(
							'id' 			=> '_css_bbpress_search_input',
							'type' 			=> 'cz_sk',
							'title' 		=> esc_html__( 'Search Input', 'codevz-plus' ),
							'button' 		=> esc_html__( 'Search Input', 'codevz-plus' ),
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'settings' 		=> array( 'color', 'background', 'border' ),
							'selector' 		=> '.bbp-search-form #bbp_search'
						),
						array(
							'id' 			=> '_css_bbpress_search_button',
							'type' 			=> 'cz_sk',
							'title' 		=> esc_html__( 'Search Button', 'codevz-plus' ),
							'button' 		=> esc_html__( 'Search Button', 'codevz-plus' ),
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'settings' 		=> array( 'color', 'background', 'border' ),
							'selector' 		=> '.bbp-search-form #bbp_search_submit'
						),
						array(
							'id' 			=> '_css_bbpress_forums_container',
							'type' 			=> 'cz_sk',
							'title' 		=> esc_html__( 'Forums', 'codevz-plus' ),
							'button' 		=> esc_html__( 'Forums', 'codevz-plus' ),
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'settings' 		=> array( 'background', 'border' ),
							'selector' 		=> '#bbpress-forums ul.bbp-lead-topic, #bbpress-forums ul.bbp-topics, #bbpress-forums ul.bbp-forums, #bbpress-forums ul.bbp-replies, #bbpress-forums ul.bbp-search-results'
						),
						array(
							'id' 			=> '_css_bbpress_forums_table_hf',
							'type' 			=> 'cz_sk',
							'title' 		=> esc_html__( 'Table header, footer', 'codevz-plus' ),
							'button' 		=> esc_html__( 'Table header, footer', 'codevz-plus' ),
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'settings' 		=> array( 'color', 'background', 'border' ),
							'selector' 		=> '#bbpress-forums li.bbp-header, #bbpress-forums li.bbp-footer'
						),
						array(
							'id' 			=> '_css_bbpress_forum_topic_title',
							'type' 			=> 'cz_sk',
							'title' 		=> esc_html__( 'Topics Title', 'codevz-plus' ),
							'button' 		=> esc_html__( 'Topics Title', 'codevz-plus' ),
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'settings' 		=> array( 'font-size', 'color', 'background', 'border' ),
							'selector' 		=> '.bbp-forum-title, li.bbp-topic-title > a'
						),
						array(
							'id' 			=> '_css_bbpress_forum_topic_subtitle',
							'type' 			=> 'cz_sk',
							'title' 		=> esc_html__( 'Subtitle', 'codevz-plus' ),
							'button' 		=> esc_html__( 'Subtitle', 'codevz-plus' ),
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'settings' 		=> array( 'font-size', 'color', 'background', 'border' ),
							'selector' 		=> '#bbpress-forums .bbp-forum-info .bbp-forum-content, #bbpress-forums p.bbp-topic-meta'
						),
						array(
							'id' 			=> '_css_bbpress_author_part',
							'type' 			=> 'cz_sk',
							'title' 		=> esc_html__( 'Author', 'codevz-plus' ),
							'button' 		=> esc_html__( 'Author', 'codevz-plus' ),
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'settings' 		=> array( 'font-size', 'color', 'background', 'border' ),
							'selector' 		=> '#bbpress-forums .status-publish .bbp-topic-author, #bbpress-forums .status-publish .bbp-reply-author'
						),
						array(
							'id' 			=> '_css_bbpress_reply_part',
							'type' 			=> 'cz_sk',
							'title' 		=> esc_html__( 'Content', 'codevz-plus' ),
							'button' 		=> esc_html__( 'Content', 'codevz-plus' ),
							'setting_args' 	=> [ 'transport' => 'postMessage' ],
							'settings' 		=> array( 'font-size', 'color', 'background', 'border' ),
							'selector' 		=> '#bbpress-forums .status-publish .bbp-topic-content, #bbpress-forums .status-publish .bbp-reply-content'
						),
						array(
							'type'    		=> 'notice',
							'class'   		=> 'info xtra-notice',
							'content' 		=> '<i class="fas fa-location-arrow mr8"></i>' . esc_html__( 'Title & Breadcrumbs', 'codevz-plus' )
						),
					),
					self::title_options( '_bbpress', '.cz-cpt-bbpress ' )
				)
			);
		}

		// WooCommerce options
		if ( function_exists( 'is_woocommerce' ) || $all ) {
			$options[ 'post_type_product' ] = array(
				'name' 		=> 'post_type_product',
				'title' 	=> esc_html__( 'WooCommerce Pro', 'codevz-plus' ),
				'sections'  => array(

					array(
						'name'   => 'products',
						'title'  => esc_html__( 'Products Settings', 'codevz-plus' ),
						'fields' => wp_parse_args(
							array(
								array(
									'id' 			=> 'layout_product',
									'type' 			=> 'codevz_image_select',
									'title' 		=> esc_html__( 'Sidebar', 'codevz-plus' ),
									'help'  		=> esc_html__( 'On all product pages', 'codevz-plus' ),
									'options' 		=> [
										'1' 			=> [ esc_html__( '~ Default ~', 'codevz-plus' ) 			, Codevz_Plus::$url . 'assets/img/sidebar-0.png' ],
										'ws' 			=> [ esc_html__( 'No Sidebar', 'codevz-plus' ) 			, Codevz_Plus::$url . 'assets/img/off.png' ],
										'bpnp' 			=> [ esc_html__( 'Fullwidth', 'codevz-plus' ) 			, Codevz_Plus::$url . 'assets/img/sidebar-2.png' ],
										'center'		=> [ esc_html__( 'Center Mode', 'codevz-plus' ) 			, Codevz_Plus::$url . 'assets/img/sidebar-13.png' ],
										'right' 		=> [ esc_html__( 'Right Sidebar', 'codevz-plus' ) 		, Codevz_Plus::$url . 'assets/img/sidebar-3.png' ],
										'right-s' 		=> [ esc_html__( 'Right Sidebar Small', 'codevz-plus' ) 	, Codevz_Plus::$url . 'assets/img/sidebar-4.png' ],
										'left' 			=> [ esc_html__( 'Left Sidebar', 'codevz-plus' ) 		, Codevz_Plus::$url . 'assets/img/sidebar-5.png' ],
										'left-s' 		=> [ esc_html__( 'Left Sidebar Small', 'codevz-plus' ) 	, Codevz_Plus::$url . 'assets/img/sidebar-6.png' ],
										'both-side' 	=> [ esc_html__( 'Both Sidebar', 'codevz-plus' ) . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ) 		, Codevz_Plus::$url . 'assets/img/sidebar-7.png' ],
										'both-side2' 	=> [ esc_html__( 'Both Sidebar Small', 'codevz-plus' ) . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ) 	, Codevz_Plus::$url . 'assets/img/sidebar-8.png' ],
										'both-right' 	=> [ esc_html__( 'Both Sidebar Right', 'codevz-plus' ) . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ) 	, Codevz_Plus::$url . 'assets/img/sidebar-9.png' ],
										'both-right2' 	=> [ esc_html__( 'Both Sidebar Right', 'codevz-plus' ) . ' 2' . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ) , Codevz_Plus::$url . 'assets/img/sidebar-10.png' ],
										'both-left' 	=> [ esc_html__( 'Both Sidebar Left', 'codevz-plus' ) . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ) 	, Codevz_Plus::$url . 'assets/img/sidebar-11.png' ],
										'both-left2' 	=> [ esc_html__( 'Both Sidebar Left', 'codevz-plus' ) . ' 2' . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' )  , Codevz_Plus::$url . 'assets/img/sidebar-12.png' ],
									],
									'default' 		=> '1'
								),
								array(
									'id' 			=> 'woo_shop_title_tag',
									'type' 			=> 'select',
									'title' 		=> esc_html__( 'Shop title tag', 'codevz-plus' ),
									'options' 		=> array(
										'h1' 			=> 'H1',
										'h2' 			=> 'H2',
										'h3' 			=> 'H3',
										'h4' 			=> 'H4',
										'hide' 			=> esc_html__( 'Hide title', 'codevz-plus' )
									),
									'default' 		=> 'h2'
								),
								array(
									'id' 			=> 'woo_widgets_toggle',
									'type' 			=> $free ? 'content' : 'select',
									'content' 		=> Codevz_Plus::pro_badge(),
									'title' 		=> esc_html__( 'Widgets toggle', 'codevz-plus' ),
									'options' 		=> [
										'' 						=> esc_html__( '~ Default ~', 'codevz-plus' ),
										'codevz-widgets-toggle' => esc_html__( 'Hide all widgets', 'codevz-plus' ),
										'codevz-widgets-toggle codevz-widgets-toggle-first' => esc_html__( 'Open first widget', 'codevz-plus' ),
									],
									'dependency' 	=> array( 'layout_product', 'any', 'right,right-s,left,left-s,both-side,both-side2,both-right,both-right2,both-left,both-left2' )
								),
								array(
									'id' 			=> 'woo_col',
									'type' 			=> 'codevz_image_select',
									'title' 		=> esc_html__( 'Shop Columns', 'codevz-plus' ),
									'options' 		=> [
										'2' 			=> [ '2 ' . esc_html__( 'Columns', 'codevz-plus' ) 		, Codevz_Plus::$url . 'assets/img/cols-2.png' ],
										'3' 			=> [ '3 ' . esc_html__( 'Columns', 'codevz-plus' ) 		, Codevz_Plus::$url . 'assets/img/cols-3.png' ],
										'4' 			=> [ '4 ' . esc_html__( 'Columns', 'codevz-plus' ) 		, Codevz_Plus::$url . 'assets/img/cols-4.png' ],
										'5' 			=> [ '5 ' . esc_html__( 'Columns', 'codevz-plus' ) 		, Codevz_Plus::$url . 'assets/img/cols-5.png' ],
										'6' 			=> [ '6 ' . esc_html__( 'Columns', 'codevz-plus' ) 		, Codevz_Plus::$url . 'assets/img/cols-6.png' ],
									],
									'default' 		=> '4'
								),
								array(
									'id'    		=> 'woo_two_col_mobile',
									'type'  		=> 'switcher',
									'title' 		=> esc_html__( '2 Columns on mobile', 'codevz-plus' ),
								),
								array(
									'id'    		=> 'woo_columns_selector',
									'type'  		=> $free ? 'content' : 'switcher',
									'content' 		=> Codevz_Plus::pro_badge(),
									'title' 		=> esc_html__( 'Grid switcher', 'codevz-plus' ),
									'help' 			=> esc_html__( 'Showing grid icons columns selector above shop page', 'codevz-plus' )
								),
								array(
									'id'    		=> 'woo_desc_below',
									'type'  		=> $free ? 'content' : 'switcher',
									'content' 		=> Codevz_Plus::pro_badge(),
									'title' 		=> esc_html__( 'Description below', 'codevz-plus' ),
									'help' 			=> esc_html__( 'Showing archive description below products', 'codevz-plus' )
								),
								array(
									'id'    		=> 'woo_items_per_page',
									'type'  		=> 'slider',
									'title' 		=> esc_html__( 'Products count', 'codevz-plus' ),
									'options'		=> array( 'unit' => '', 'step' => 1, 'min' => -1, 'max' => 100 ),
								),
								array(
									'id'    		=> 'woo_ppp_dropdown',
									'type'  		=> $free ? 'content' : 'switcher',
									'content' 		=> Codevz_Plus::pro_badge(),
									'title' 		=> esc_html__( 'Count dropdown', 'codevz-plus' ),
									'help' 			=> esc_html__( 'Showing products count dropdown above products in archive pages.', 'codevz-plus' )
								),
								array(
									'id'    		=> 'woo_add_to_cart_title',
									'type'  		=> $free ? 'content' : 'text',
									'content' 		=> Codevz_Plus::pro_badge(),
									'title' 		=> esc_html__( 'Add to cart title', 'codevz-plus' )
								),
								array(
									'id' 			=> 'woo_hover_effect',
									'type' 			=> $free ? 'content' : 'select',
									'content' 		=> Codevz_Plus::pro_badge(),
									'title' 		=> esc_html__( 'Hover Effect', 'codevz-plus' ),
									'help' 			=> esc_html__( 'Image hover effects are effects that are applied to an image, activated when someone hovers the mouse over product.', 'codevz-plus' ),
									'options' 		=> [
										'' 				=> esc_html__( '~ Disable ~', 'codevz-plus' ),
										'no_effect' 	=> esc_html__( 'No effect', 'codevz-plus' ),
										'slow_fade' 	=> esc_html__( 'Slow Fade', 'codevz-plus' ),
										'simple_fade' 	=> esc_html__( 'Fast Fade', 'codevz-plus' ),
										'flip_h' 		=> esc_html__( 'Flip Horizontal', 'codevz-plus' ),
										'flip_v' 		=> esc_html__( 'Flip Vertical', 'codevz-plus' ),
										'fade_to_top' 	=> esc_html__( 'Fade To Top', 'codevz-plus' ),
										'fade_to_bottom' => esc_html__( 'Fade To Bottom', 'codevz-plus' ),
										'fade_to_left' 	=> esc_html__( 'Fade To Left', 'codevz-plus' ),
										'fade_to_right' => esc_html__( 'Fade To Right', 'codevz-plus' ),
										'zoom_in' 		=> esc_html__( 'Zoom In', 'codevz-plus' ),
										'zoom_out' 		=> esc_html__( 'Zoom Out', 'codevz-plus' ),
										'blurred' 		=> esc_html__( 'Blurred', 'codevz-plus' ),
									]
								),
								array(
									'id'            => 'woo_sale_percentage',
									'type'          => $free ? 'content' : 'select',
									'title'         => esc_html__( 'Sale badge?', 'codevz-plus' ),
									'help' 			=> esc_html__( 'Display the sale percentage as "-50%" instead of a sale badge', 'codevz-plus' ),
									'content' 		=> Codevz_Plus::pro_badge(),
									'options'       => array(
										''							=> esc_html__( '~ Default ~', 'codevz-plus' ),
										'woo-sale-percentage'		=> esc_html__( 'Percentage', 'codevz-plus' ),
										'woo-sale-text-percentage'	=> esc_html__( 'Text + Percentage', 'codevz-plus' ),
									)
								),
								array(
									'id'    		=> 'woo_added_to_cart_notification',
									'type'  		=> $free ? 'content' : 'switcher',
									'content' 		=> Codevz_Plus::pro_badge(),
									'title' 		=> esc_html__( 'Cart notification?', 'codevz-plus' ),
									'help' 			=> esc_html__( 'Displaying a notification message when a product has been successfully added to the cart', 'codevz-plus' )
								),
								array(
									'id'    		=> 'woo_new_label',
									'type'  		=> $free ? 'content' : 'switcher',
									'content' 		=> Codevz_Plus::pro_badge(),
									'title' 		=> esc_html__( 'New badge?', 'codevz-plus' ),
									'help' 			=> esc_html__( 'Adding a new badge to the product image, similar to a sale badge', 'codevz-plus' )
								),
								array(
									'id'    		=> 'woo_new_label_days',
									'type'  		=> $free ? 'content' : 'slider',
									'content' 		=> Codevz_Plus::pro_badge(),
									'title' 		=> esc_html__( 'New badge days', 'codevz-plus' ),
									'options'		=> array( 'unit' => '', 'step' => 1, 'min' => 1, 'max' => 365 ),
									'help' 			=> esc_html__( "How many days should the 'NEW' badge remain visible?", 'codevz-plus' ),
									'default' 		=> '1',
									'dependency' 	=> array( 'woo_new_label', '==', 'true' )
								),
								array(
									'id'    		=> 'woo_category_under_title',
									'type'  		=> $free ? 'content' : 'select',
									'content' 		=> Codevz_Plus::pro_badge(),
									'title' 		=> esc_html__( 'Products subtitle', 'codevz-plus' ),
									'options' 		=> array(
										''				=> esc_html__( '~ Disable ~', 'codevz-plus' ),
										'category'		=> esc_html__( 'Category', 'codevz-plus' ),
										'brand'			=> esc_html__( 'Brand', 'codevz-plus' ),
									),
								),
								array(
									'id'    		=> 'woo_product_title_single_line',
									'type'  		=> $free ? 'content' : 'switcher',
									'content' 		=> Codevz_Plus::pro_badge(),
									'title' 		=> esc_html__( 'Short products title?', 'codevz-plus' ),
									'help' 			=> esc_html__( 'Shortening product titles to maintain consistent product heights.', 'codevz-plus' )
								),
								array(
									'id'    		=> 'woo_products_short_desc',
									'type'  		=> $free ? 'content' : 'switcher',
									'content' 		=> Codevz_Plus::pro_badge(),
									'title' 		=> esc_html__( 'Short description?', 'codevz-plus' ),
									'help' 			=> esc_html__( 'Displaying the short description of products under the title and category', 'codevz-plus' )
								),
								array(
									'id'    		=> 'woo_wishlist',
									'type'  		=> $free ? 'content' : 'switcher',
									'content' 		=> Codevz_Plus::pro_badge(),
									'title' 		=> esc_html__( 'Wishlist', 'codevz-plus' ),
									'help' 			=> esc_html__( 'WooCommerce Wishlists allows guests and customers to create and add products to an unlimited number of Wishlists.', 'codevz-plus' )
								),
								array(
									'id'    		=> 'woo_compare',
									'type'  		=> $free ? 'content' : 'switcher',
									'content' 		=> Codevz_Plus::pro_badge(),
									'title' 		=> esc_html__( 'Compare', 'codevz-plus' ),
									'help' 			=> esc_html__( 'WooCommerce compare allows guests and customers to compare products between each others.', 'codevz-plus' )
								),
								array(
									'id'    		=> 'woo_quick_view',
									'type'  		=> $free ? 'content' : 'switcher',
									'content' 		=> Codevz_Plus::pro_badge(),
									'title' 		=> esc_html__( 'Quick View', 'codevz-plus' ),
									'help' 			=> esc_html__( 'The quick view is a button to show product details in a lightbox when clicked.', 'codevz-plus' )
								),
								array(
									'id'    		=> 'woo_wishlist_qv_center',
									'type'  		=> $free ? 'content' : 'switcher',
									'content' 		=> Codevz_Plus::pro_badge(),
									'title' 		=> esc_html__( 'Center Mode', 'codevz-plus' ),
									'help' 			=> esc_html__( 'Move wishlist, compare and quick view icons to the center of products.', 'codevz-plus' )
								),
								array(
									'id'    		=> 'woo_sold_out_badge',
									'type'  		=> $free ? 'content' : 'switcher',
									'content' 		=> Codevz_Plus::pro_badge(),
									'title' 		=> esc_html__( 'Sold out badge', 'codevz-plus' )
								),
								array(
									'id'    		=> 'woo_sold_out_grayscale',
									'type'  		=> $free ? 'content' : 'switcher',
									'content' 		=> Codevz_Plus::pro_badge(),
									'title' 		=> esc_html__( 'Sold out grayscale', 'codevz-plus' )
								),
								array(
									'id'    		=> 'woo_show_zero_count',
									'type'  		=> $free ? 'content' : 'switcher',
									'content' 		=> Codevz_Plus::pro_badge(),
									'title' 		=> esc_html__( 'Header cart zero number', 'codevz-plus' ),
								),
								array(
									'id'    		=> 'woo_cart_page_related_products',
									'type'  		=> $free ? 'content' : 'switcher',
									'content' 		=> Codevz_Plus::pro_badge(),
									'title' 		=> esc_html__( 'Cart related products', 'codevz-plus' ),
									'help' 			=> esc_html__( 'Show related products on the cart page based on items in the cart and random products when the cart is empty.', 'codevz-plus' )
								),
								array(
									'id' 			=> 'woo_cart_checkout_steps',
									'type' 			=> $free ? 'content' : 'select',
									'content' 		=> Codevz_Plus::pro_badge(),
									'title' 		=> esc_html__( 'Checkout steps', 'codevz-plus' ),
									'help' 			=> esc_html__( 'Show 3 steps above on cart, checkout and order completion pages.', 'codevz-plus' ),
									'options' 		=> array(
										''				=> esc_html__( '~ Disable ~', 'codevz-plus' ),
										'horizontal'	=> esc_html__( 'Horizontal', 'codevz-plus' ),
										'vertical'		=> esc_html__( 'Vertical', 'codevz-plus' ),
									),
								),
								array(
									'id'    		=> 'woo_free_shipping_bar',
									'type'  		=> $free ? 'content' : 'switcher',
									'content' 		=> Codevz_Plus::pro_badge(),
									'help' 			=> esc_html__( 'Showing free shipping process bar in the cart and checkout page', 'codevz-plus' ),
									'title' 		=> esc_html__( 'Free shipping bar', 'codevz-plus' ),
								),
								array(
									'id'    		=> 'woo_free_shipping_bar_threshold',
									'type'  		=> 'slider',
									'title' 		=> esc_html__( 'Free shipping amount', 'codevz-plus' ),
									'options'		=> array( 'unit' => '', 'step' => 10, 'min' => 0, 'max' => 10000 ),
									'dependency' 	=> array( 'woo_free_shipping_bar', '!=', '' )
								),
								array(
									'id'    		=> 'woo_sold_out_title',
									'type'  		=> 'text',
									'title' 		=> esc_html__( 'Sold out', 'codevz-plus' ),
									'default' 		=> esc_html__( 'Sold out', 'codevz-plus' ),
									'setting_args' 	=> [ 'transport' => 'postMessage' ],
								),
								array(
									'id'    		=> 'woo_cart',
									'type'  		=> 'text',
									'title' 		=> esc_html__( 'Cart', 'codevz-plus' ),
									'default' 		=> esc_html__( 'Cart subtotal', 'codevz-plus' ),
									'setting_args' 	=> [ 'transport' => 'postMessage' ],
								),
								array(
									'id'    		=> 'woo_checkout',
									'type'  		=> 'text',
									'title' 		=> esc_html__( 'Cart checkout', 'codevz-plus' ),
									'default' 		=> esc_html__( 'Process to Checkout', 'codevz-plus' ),
									'setting_args' 	=> [ 'transport' => 'postMessage' ],
								),
								array(
									'id'    		=> 'woo_cart_footer',
									'type'  		=> 'text',
									'title' 		=> esc_html__( 'Mini cart footer', 'codevz-plus' ),
									'setting_args' 	=> [ 'transport' => 'postMessage' ],
								),
								array(
									'id'    		=> 'woo_continue_shopping',
									'type'  		=> 'text',
									'title' 		=> esc_html__( 'Continue shop', 'codevz-plus' ),
									'default' 		=> esc_html__( 'Continue shopping', 'codevz-plus' ),
									'setting_args' 	=> [ 'transport' => 'postMessage' ],
								),
								array(
									'id'    		=> 'woo_no_products',
									'type'  		=> 'text',
									'title' 		=> esc_html__( 'Cart no prodcuts', 'codevz-plus' ),
									'default' 		=> esc_html__( "Cart's empty! Let's fill it up!", 'codevz-plus' ),
									'setting_args' 	=> [ 'transport' => 'postMessage' ],
								),
							),
							self::title_options( '_product', '.cz-cpt-product ' )
						)
					),

					array(
						'name'   => 'products_sk',
						'title'  => esc_html__( 'Products Styling', 'codevz-plus' ),
						'fields' => array(
							array(
								'id' 			=> '_css_woo_products_container',
								'type' 			=> 'cz_sk',
								'title' 		=> esc_html__( 'Container', 'codevz-plus' ),
								'button' 		=> esc_html__( 'Container', 'codevz-plus' ),
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'settings' 		=> array( 'background', 'border' ),
								'selector' 		=> '.archive.cz-cpt-product .content, .archive.cz-cpt-product .cz_is_blank'
							),
							array(
								'id' 			=> '_css_woo_products_container_tablet',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.archive.cz-cpt-product .content, .archive.cz-cpt-product .cz_is_blank'
							),
							array(
								'id' 			=> '_css_woo_products_container_mobile',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.archive.cz-cpt-product .content, .archive.cz-cpt-product .cz_is_blank'
							),
							array(
								'id' 			=> '_css_woo_products_overall',
								'hover_id' 		=> '_css_woo_products_overall_hover',
								'type' 			=> 'cz_sk',
								'title' 		=> esc_html__( 'Products', 'codevz-plus' ),
								'button' 		=> esc_html__( 'Products', 'codevz-plus' ),
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'settings' 		=> array( 'background', 'border' ),
								'selector' 		=> '.woocommerce ul.products li.product .woocommerce-loop-product__link'
							),
							array(
								'id' 			=> '_css_woo_products_overall_tablet',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.woocommerce ul.products li.product .woocommerce-loop-product__link'
							),
							array(
								'id' 			=> '_css_woo_products_overall_mobile',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.woocommerce ul.products li.product .woocommerce-loop-product__link'
							),
							array(
								'id' 			=> '_css_woo_products_overall_hover',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.woocommerce ul.products li.product:hover .woocommerce-loop-product__link'
							),
							array(
								'id' 			=> '_css_woo_products_thumbnails',
								'hover_id' 		=> '_css_woo_products_thumbnails_hover',
								'type' 			=> 'cz_sk',
								'title' 		=> esc_html__( 'Image', 'codevz-plus' ),
								'button' 		=> esc_html__( 'Image', 'codevz-plus' ),
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'settings' 		=> array( 'background', 'border', 'border-radius' ),
								'selector' 		=> '.woocommerce ul.products li.product a img'
							),
							array(
								'id' 			=> '_css_woo_products_thumbnails_tablet',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.woocommerce ul.products li.product a img'
							),
							array(
								'id' 			=> '_css_woo_products_thumbnails_mobile',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.woocommerce ul.products li.product a img'
							),
							array(
								'id' 			=> '_css_woo_products_thumbnails_hover',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.woocommerce ul.products li.product:hover a img'
							),
							array(
								'id' 			=> '_css_woo_products_title',
								'hover_id' 		=> '_css_woo_products_title_hover',
								'type' 			=> 'cz_sk',
								'title' 		=> esc_html__( 'Title', 'codevz-plus' ),
								'button' 		=> esc_html__( 'Title', 'codevz-plus' ),
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'settings' 		=> array( 'color', 'font-family', 'font-size', 'text-align', 'float' ),
								'selector' 		=> '.woocommerce ul.products li.product .woocommerce-loop-category__title, .woocommerce ul.products li.product .woocommerce-loop-product__title, .woocommerce ul.products li.product h3,.woocommerce.woo-template-2 ul.products li.product .woocommerce-loop-category__title, .woocommerce.woo-template-2 ul.products li.product .woocommerce-loop-product__title, .woocommerce.woo-template-2 ul.products li.product h3'
							),
							array(
								'id' 			=> '_css_woo_products_title_tablet',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.woocommerce ul.products li.product .woocommerce-loop-category__title, .woocommerce ul.products li.product .woocommerce-loop-product__title, .woocommerce ul.products li.product h3,.woocommerce.woo-template-2 ul.products li.product .woocommerce-loop-category__title, .woocommerce.woo-template-2 ul.products li.product .woocommerce-loop-product__title, .woocommerce.woo-template-2 ul.products li.product h3'
							),
							array(
								'id' 			=> '_css_woo_products_title_mobile',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.woocommerce ul.products li.product .woocommerce-loop-category__title, .woocommerce ul.products li.product .woocommerce-loop-product__title, .woocommerce ul.products li.product h3,.woocommerce.woo-template-2 ul.products li.product .woocommerce-loop-category__title, .woocommerce.woo-template-2 ul.products li.product .woocommerce-loop-product__title, .woocommerce.woo-template-2 ul.products li.product h3'
							),
							array(
								'id' 			=> '_css_woo_products_title_hover',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.woocommerce ul.products li.product:hover .woocommerce-loop-category__title, .woocommerce ul.products li.product:hover .woocommerce-loop-product__title, .woocommerce ul.products li.product:hover h3,.woocommerce.woo-template-2 ul.products li.product:hover .woocommerce-loop-category__title, .woocommerce.woo-template-2 ul.products li.product:hover .woocommerce-loop-product__title, .woocommerce.woo-template-2 ul.products li.product:hover h3'
							),
							array(
								'id' 			=> '_css_woo_products_title_cat',
								'hover_id' 		=> '_css_woo_products_title_cat_hover',
								'type' 			=> 'cz_sk',
								'title' 		=> esc_html__( 'Category', 'codevz-plus' ),
								'button' 		=> esc_html__( 'Category', 'codevz-plus' ),
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'settings' 		=> array( 'color', 'background' ),
								'selector' 		=> '.woocommerce ul.products li.product .codevz-product-category-after-title'
							),
							array(
								'id' 			=> '_css_woo_products_title_cat_tablet',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.woocommerce ul.products li.product .codevz-product-category-after-title'
							),
							array(
								'id' 			=> '_css_woo_products_title_cat_mobile',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.woocommerce ul.products li.product .codevz-product-category-after-title'
							),
							array(
								'id' 			=> '_css_woo_products_title_cat_hover',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.woocommerce ul.products li.product:hover .codevz-product-category-after-title'
							),
							array(
								'id' 			=> '_css_woo_products_onsale',
								'type' 			=> 'cz_sk',
								'title' 		=> esc_html__( 'Sale Badge', 'codevz-plus' ),
								'button' 		=> esc_html__( 'Sale Badge', 'codevz-plus' ),
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'settings' 		=> array( 'line-height', 'width', 'height', 'color', 'background', 'font-family', 'font-size', 'top', 'left', 'border' ),
								'selector' 		=> '.woocommerce span.onsale, .woocommerce ul.products li.product .onsale,.woocommerce.single span.onsale, .woocommerce.single ul.products li.product .onsale'
							),
							array(
								'id' 			=> '_css_woo_products_onsale_tablet',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.woocommerce span.onsale, .woocommerce ul.products li.product .onsale,.woocommerce.single span.onsale, .woocommerce.single ul.products li.product .onsale'
							),
							array(
								'id' 			=> '_css_woo_products_onsale_mobile',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.woocommerce span.onsale, .woocommerce ul.products li.product .onsale,.woocommerce.single span.onsale, .woocommerce.single ul.products li.product .onsale'
							),
							array(
								'id' 			=> '_css_woo_products_new_badge',
								'type' 			=> 'cz_sk',
								'title' 		=> esc_html__( 'New Badge', 'codevz-plus' ),
								'button' 		=> esc_html__( 'New Badge', 'codevz-plus' ),
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'settings' 		=> array( 'color', 'background' ),
								'selector' 		=> '.woocommerce span.onsale.cz_new_badge, .woocommerce ul.products li.product .onsale.cz_new_badge,.woocommerce.single span.onsale.cz_new_badge, .woocommerce.single ul.products li.product .onsale.cz_new_badge'
							),
							array(
								'id' 			=> '_css_woo_products_new_badge_tablet',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.woocommerce span.onsale.cz_new_badge, .woocommerce ul.products li.product .onsale.cz_new_badge,.woocommerce.single span.onsale.cz_new_badge, .woocommerce.single ul.products li.product .onsale.cz_new_badge'
							),
							array(
								'id' 			=> '_css_woo_products_new_badge_mobile',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.woocommerce span.onsale.cz_new_badge, .woocommerce ul.products li.product .onsale.cz_new_badge,.woocommerce.single span.onsale.cz_new_badge, .woocommerce.single ul.products li.product .onsale.cz_new_badge'
							),
							array(
								'id' 			=> '_css_woo_products_short_desc',
								'hover_id' 		=> '_css_woo_products_short_desc_hover',
								'type' 			=> 'cz_sk',
								'title' 		=> esc_html__( 'Short description', 'codevz-plus' ),
								'button' 		=> esc_html__( 'Short description', 'codevz-plus' ),
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'settings' 		=> array( 'color', 'background' ),
								'selector' 		=> '.codevz-product-short-desc'
							),
							array(
								'id' 			=> '_css_woo_products_short_desc_tablet',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.codevz-product-short-desc'
							),
							array(
								'id' 			=> '_css_woo_products_short_desc_mobile',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.codevz-product-short-desc'
							),
							array(
								'id' 			=> '_css_woo_products_short_desc_hover',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.woocommerce ul.products li.product:hover .codevz-product-short-desc'
							),
							array(
								'id' 			=> '_css_woo_products_stars',
								'hover_id' 		=> '_css_woo_products_stars_hover',
								'type' 			=> 'cz_sk',
								'title' 		=> esc_html__( 'Rating Stars', 'codevz-plus' ),
								'button' 		=> esc_html__( 'Rating Stars', 'codevz-plus' ),
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'settings' 		=> array( 'color' ),
								'selector' 		=> '.woocommerce ul.products li.product .star-rating'
							),
							array(
								'id' 			=> '_css_woo_products_stars_tablet',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.woocommerce ul.products li.product .star-rating'
							),
							array(
								'id' 			=> '_css_woo_products_stars_mobile',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.woocommerce ul.products li.product .star-rating'
							),
							array(
								'id' 			=> '_css_woo_products_stars_hover',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.woocommerce ul.products li.product:hover .star-rating'
							),
							array(
								'id' 			=> '_css_woo_products_outofstock',
								'type' 			=> 'cz_sk',
								'title' 		=> esc_html__( 'Out of stock', 'codevz-plus' ),
								'button' 		=> esc_html__( 'Out of stock', 'codevz-plus' ),
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'settings' 		=> array( 'line-height', 'width', 'height', 'color', 'background', 'font-family', 'font-size', 'top', 'left', 'border' ),
								'selector' 		=> '.xtra-outofstock'
							),
							array(
								'id' 			=> '_css_woo_products_outofstock_tablet',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.xtra-outofstock'
							),
							array(
								'id' 			=> '_css_woo_products_outofstock_mobile',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.xtra-outofstock'
							),
							array(
								'id' 			=> '_css_woo_products_price',
								'hover_id' 		=> '_css_woo_products_price_hover',
								'type' 			=> 'cz_sk',
								'title' 		=> esc_html__( 'Price', 'codevz-plus' ),
								'button' 		=> esc_html__( 'Price', 'codevz-plus' ),
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'settings' 		=> array( 'color', 'background', 'font-family', 'font-size', 'top', 'right' ),
								'selector' 		=> '.woocommerce ul.products li.product .price'
							),
							array(
								'id' 			=> '_css_woo_products_price_tablet',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.woocommerce ul.products li.product .price'
							),
							array(
								'id' 			=> '_css_woo_products_price_mobile',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.woocommerce ul.products li.product .price'
							),
							array(
								'id' 			=> '_css_woo_products_price_hover',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.woocommerce ul.products li.product:hover .price'
							),

							array(
								'id' 			=> '_css_woo_products_sale',
								'hover_id' 		=> '_css_woo_products_sale_hover',
								'type' 			=> 'cz_sk',
								'title' 		=> esc_html__( 'Sale Price', 'codevz-plus' ),
								'button' 		=> esc_html__( 'Sale Price', 'codevz-plus' ),
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'settings' 		=> array( 'color', 'background', 'font-size' ),
								'selector' 		=> '.woocommerce ul.products li.product .price del span'
							),
							array(
								'id' 			=> '_css_woo_products_sale_tablet',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.woocommerce ul.products li.product .price del span'
							),
							array(
								'id' 			=> '_css_woo_products_sale_mobile',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.woocommerce ul.products li.product .price del span'
							),
							array(
								'id' 			=> '_css_woo_products_sale_hover',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.woocommerce ul.products li.product:hover .price del span'
							),

							array(
								'id' 			=> '_css_woo_products_add_to_cart',
								'hover_id' 		=> '_css_woo_products_add_to_cart_hover',
								'type' 			=> 'cz_sk',
								'title' 		=> esc_html__( 'Add to cart', 'codevz-plus' ),
								'button' 		=> esc_html__( 'Add to cart', 'codevz-plus' ),
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'settings' 		=> array( 'color', 'font-family', 'font-size', 'opacity', 'float', 'background', 'border' ),
								'selector' 		=> '.woocommerce ul.products li.product .button.add_to_cart_button, .woocommerce ul.products li.product .button[class*="product_type_"]'
							),
							array(
								'id' 			=> '_css_woo_products_add_to_cart_tablet',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.woocommerce ul.products li.product .button.add_to_cart_button, .woocommerce ul.products li.product .button[class*="product_type_"]'
							),
							array(
								'id' 			=> '_css_woo_products_add_to_cart_mobile',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.woocommerce ul.products li.product .button.add_to_cart_button, .woocommerce ul.products li.product .button[class*="product_type_"]'
							),
							array(
								'id' 			=> '_css_woo_products_add_to_cart_hover',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.woocommerce ul.products li.product .button.add_to_cart_button:hover, .woocommerce ul.products li.product .button[class*="product_type_"]:hover'
							),
							array(
								'id' 			=> '_css_woo_products_added_to_cart',
								'hover_id' 		=> '_css_woo_products_added_to_cart_hover',
								'type' 			=> 'cz_sk',
								'title' 		=> esc_html__( 'View Cart', 'codevz-plus' ),
								'button' 		=> esc_html__( 'View Cart', 'codevz-plus' ),
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'settings' 		=> array( 'color', 'font-style' ),
								'selector' 		=> '.woocommerce a.added_to_cart'
							),
							array(
								'id' 			=> '_css_woo_products_added_to_cart_tablet',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.woocommerce a.added_to_cart'
							),
							array(
								'id' 			=> '_css_woo_products_added_to_cart_mobile',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.woocommerce a.added_to_cart'
							),
							array(
								'id' 			=> '_css_woo_products_added_to_cart_hover',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.woocommerce a.added_to_cart:hover'
							),
							array(
								'id' 			=> 'xtra_control_badge',
								'type' 			=> 'content',
								'content' 		=> Codevz_Plus::pro_badge(),
								'dependency' 	=> $free ? [] : [ 'x', '==', 'x' ]
							),
							array(
								'id' 			=> '_css_woo_products_result_count',
								'type' 			=> $free ? 'cz_sk_free' : 'cz_sk',
								'title' 		=> esc_html__( 'Result Count', 'codevz-plus' ),
								'button' 		=> esc_html__( 'Result Count', 'codevz-plus' ),
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'settings' 		=> array( 'color', 'background', 'font-size', 'border' ),
								'selector' 		=> '.woocommerce .woocommerce-result-count'
							),
							array(
								'id' 			=> '_css_woo_products_result_count_tablet',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.woocommerce .woocommerce-result-count'
							),
							array(
								'id' 			=> '_css_woo_products_result_count_mobile',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.woocommerce .woocommerce-result-count'
							),
							array(
								'id' 			=> '_css_woo_products_columns_switcher',
								'hover_id' 		=> '_css_woo_products_columns_switcher_hover',
								'type' 			=> $free ? 'cz_sk_free' : 'cz_sk',
								'title' 		=> esc_html__( 'Grid switcher', 'codevz-plus' ),
								'button' 		=> esc_html__( 'Grid switcher', 'codevz-plus' ),
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'settings' 		=> array( 'color', 'background', 'font-size', 'width', 'height', 'border' ),
								'selector' 		=> '.codevz-woo-columns span'
							),
							array(
								'id' 			=> '_css_woo_products_columns_switcher_hover',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.codevz-woo-columns span:hover, .codevz-woo-columns .codevz-current'
							),
							array(
								'id' 			=> '_css_woo_products_icons',
								'hover_id' 		=> '_css_woo_products_icons_hover',
								'type' 			=> $free ? 'cz_sk_free' : 'cz_sk',
								'title' 		=> esc_html__( 'Icons', 'codevz-plus' ),
								'button' 		=> esc_html__( 'Icons', 'codevz-plus' ),
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'settings' 		=> array( 'background', 'border', 'box-shadow' ),
								'selector' 		=> '.products .product .xtra-product-icons'
							),
							array(
								'id' 			=> '_css_woo_products_icons_tablet',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.products .product .xtra-product-icons'
							),
							array(
								'id' 			=> '_css_woo_products_icons_mobile',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.products .product .xtra-product-icons'
							),
							array(
								'id' 			=> '_css_woo_products_icons_hover',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.products .product:hover .xtra-product-icons'
							),
							array(
								'id' 			=> '_css_woo_products_wishlist',
								'hover_id' 		=> '_css_woo_products_wishlist_hover',
								'type' 			=> $free ? 'cz_sk_free' : 'cz_sk',
								'title' 		=> esc_html__( 'Wishlist', 'codevz-plus' ),
								'button' 		=> esc_html__( 'Wishlist', 'codevz-plus' ),
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'settings' 		=> array( 'color', 'background', 'border', 'box-shadow' ),
								'selector' 		=> '.products .product .xtra-add-to-wishlist'
							),
							array(
								'id' 			=> '_css_woo_products_wishlist_tablet',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.products .product .xtra-add-to-wishlist'
							),
							array(
								'id' 			=> '_css_woo_products_wishlist_mobile',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.products .product .xtra-add-to-wishlist'
							),
							array(
								'id' 			=> '_css_woo_products_wishlist_hover',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.products .product .xtra-add-to-wishlist:hover'
							),
							array(
								'id' 			=> '_css_woo_products_compare',
								'hover_id' 		=> '_css_woo_products_compare_hover',
								'type' 			=> $free ? 'cz_sk_free' : 'cz_sk',
								'title' 		=> esc_html__( 'Compare', 'codevz-plus' ),
								'button' 		=> esc_html__( 'Compare', 'codevz-plus' ),
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'settings' 		=> array( 'color', 'background', 'border', 'box-shadow' ),
								'selector' 		=> '.products .product .xtra-add-to-compare'
							),
							array(
								'id' 			=> '_css_woo_products_compare_tablet',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.products .product .xtra-add-to-compare'
							),
							array(
								'id' 			=> '_css_woo_products_compare_mobile',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.products .product .xtra-add-to-compare'
							),
							array(
								'id' 			=> '_css_woo_products_compare_hover',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.products .product .xtra-add-to-compare:hover'
							),
							array(
								'id' 			=> '_css_woo_products_quick_view',
								'hover_id' 		=> '_css_woo_products_quick_view_hover',
								'type' 			=> $free ? 'cz_sk_free' : 'cz_sk',
								'title' 		=> esc_html__( 'Quick View', 'codevz-plus' ),
								'button' 		=> esc_html__( 'Quick View', 'codevz-plus' ),
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'settings' 		=> array( 'color', 'background', 'border', 'box-shadow' ),
								'selector' 		=> '.products .product .xtra-product-quick-view'
							),
							array(
								'id' 			=> '_css_woo_products_quick_view_tablet',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.products .product .xtra-product-quick-view'
							),
							array(
								'id' 			=> '_css_woo_products_quick_view_mobile',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.products .product .xtra-product-quick-view'
							),
							array(
								'id' 			=> '_css_woo_products_quick_view_hover',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.products .product .xtra-product-quick-view:hover'
							),
							array(
								'type' 			=> 'notice',
								'class' 		=> 'info',
								'content' 		=> '<i class="fas fa-brush mr8"></i>' . esc_html__( 'More styling', 'codevz-plus' )
							),
							array(
								'id' 			=> '_css_woo_products_quick_view_popup',
								'type' 			=> $free ? 'cz_sk_free' : 'cz_sk',
								'title' 		=> esc_html__( 'Popup', 'codevz-plus' ),
								'button' 		=> esc_html__( 'Popup', 'codevz-plus' ),
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'settings' 		=> array( 'color', 'background', 'border', 'box-shadow' ),
								'selector' 		=> '#xtra_quick_view .cz_popup_in, #xtra_wish_compare .cz_popup_in'
							),
							array(
								'id' 			=> '_css_woo_products_quick_view_popup_tablet',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '#xtra_quick_view .cz_popup_in, #xtra_wish_compare .cz_popup_in'
							),
							array(
								'id' 			=> '_css_woo_products_quick_view_popup_mobile',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '#xtra_quick_view .cz_popup_in, #xtra_wish_compare .cz_popup_in'
							),
							array(
								'id' 			=> '_css_woo_notification_add_to_cart',
								'type' 			=> $free ? 'cz_sk_free' : 'cz_sk',
								'title' 		=> esc_html__( 'Cart notification', 'codevz-plus' ),
								'button' 		=> esc_html__( 'Cart notification', 'codevz-plus' ),
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'settings' 		=> array( 'color', 'background', 'border' ),
								'selector' 		=> '.codevz-added-to-cart-notif'
							),
							array(
								'id' 			=> '_css_woo_cart_footer',
								'type' 			=> $free ? 'cz_sk_free' : 'cz_sk',
								'title' 		=> esc_html__( 'Mini cart footer', 'codevz-plus' ),
								'button' 		=> esc_html__( 'Mini cart footer', 'codevz-plus' ),
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'settings' 		=> array( 'color', 'background', 'border' ),
								'selector' 		=> '.cz_cart_footer'
							),
						)
					),

					array(
						'name'   => 'product',
						'title'  => esc_html__( 'Product Settings', 'codevz-plus' ),
						'fields' => array(
							array(
								'id' 			=> 'layout_single_product',
								'type' 			=> 'codevz_image_select',
								'title' 		=> esc_html__( 'Sidebar', 'codevz-plus' ),
								'help'  		=> esc_html__( 'On all single product pages', 'codevz-plus' ),
								'options' 		=> [
									'1' 			=> [ esc_html__( '~ Default ~', 'codevz-plus' ) 			, Codevz_Plus::$url . 'assets/img/sidebar-0.png' ],
									'ws' 			=> [ esc_html__( 'No Sidebar', 'codevz-plus' ) 			, Codevz_Plus::$url . 'assets/img/off.png' ],
									'bpnp' 			=> [ esc_html__( 'Fullwidth', 'codevz-plus' ) 			, Codevz_Plus::$url . 'assets/img/sidebar-2.png' ],
									'center'		=> [ esc_html__( 'Center Mode', 'codevz-plus' ) 			, Codevz_Plus::$url . 'assets/img/sidebar-13.png' ],
									'right' 		=> [ esc_html__( 'Right Sidebar', 'codevz-plus' ) 		, Codevz_Plus::$url . 'assets/img/sidebar-3.png' ],
									'right-s' 		=> [ esc_html__( 'Right Sidebar Small', 'codevz-plus' ) 	, Codevz_Plus::$url . 'assets/img/sidebar-4.png' ],
									'left' 			=> [ esc_html__( 'Left Sidebar', 'codevz-plus' ) 		, Codevz_Plus::$url . 'assets/img/sidebar-5.png' ],
									'left-s' 		=> [ esc_html__( 'Left Sidebar Small', 'codevz-plus' ) 	, Codevz_Plus::$url . 'assets/img/sidebar-6.png' ],
									'both-side' 	=> [ esc_html__( 'Both Sidebar', 'codevz-plus' ) . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ) 		, Codevz_Plus::$url . 'assets/img/sidebar-7.png' ],
									'both-side2' 	=> [ esc_html__( 'Both Sidebar Small', 'codevz-plus' ) . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ) 	, Codevz_Plus::$url . 'assets/img/sidebar-8.png' ],
									'both-right' 	=> [ esc_html__( 'Both Sidebar Right', 'codevz-plus' ) . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ) 	, Codevz_Plus::$url . 'assets/img/sidebar-9.png' ],
									'both-right2' 	=> [ esc_html__( 'Both Sidebar Right', 'codevz-plus' ) . ' 2' . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ) , Codevz_Plus::$url . 'assets/img/sidebar-10.png' ],
									'both-left' 	=> [ esc_html__( 'Both Sidebar Left', 'codevz-plus' ) . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ) 	, Codevz_Plus::$url . 'assets/img/sidebar-11.png' ],
									'both-left2' 	=> [ esc_html__( 'Both Sidebar Left', 'codevz-plus' ) . ' 2' . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' )  , Codevz_Plus::$url . 'assets/img/sidebar-12.png' ],
								],
								'default' 		=> '1'
							),
							array(
								'id' 			=> 'woo_summary_layout',
								'type' 			=> $free ? 'content' : 'select',
								'content' 		=> Codevz_Plus::pro_badge(),
								'title' 		=> esc_html__( 'Single layout', 'codevz-plus' ),
								'options' 		=> [
									'' 											=> esc_html__( '~ Default ~', 'codevz-plus' ),
									//'codevz-plus-woo-single-layout-center' 	=> esc_html__( 'Center', 'codevz-plus' ),
									'codevz-plus-woo-single-layout-inverted' 	=> esc_html__( 'Inverted', 'codevz-plus' ),
								],
							),
							array(
								'id' 		=> 'woo_gallery_features',
								'type' 		=> $free ? 'content' : 'checkbox',
								'content' 	=> Codevz_Plus::pro_badge(),
								'title' 	=> esc_html__( 'Disable features', 'codevz-plus' ),
								'help' 		=> esc_html__( 'You can disable default built-in woocommerce features for single product.', 'codevz-plus' ),
								'options' 	=> array(
									'zoom'		=> esc_html__( 'Disable', 'codevz-plus' ) . ' ' . esc_html__( 'Hover zoom', 'codevz-plus' ),
									'lightbox'	=> esc_html__( 'Disable', 'codevz-plus' ) . ' ' . esc_html__( 'Lightbox', 'codevz-plus' ),
									'slider'	=> esc_html__( 'Disable', 'codevz-plus' ) . ' ' . esc_html__( 'Slider', 'codevz-plus' ),
								),
							),
							array(
								'id'    		=> 'woo_product_countdown',
								'type'  		=> $free ? 'content' : 'text',
								'content' 		=> Codevz_Plus::pro_badge(),
								'title' 		=> esc_html__( 'Countdown title', 'codevz-plus' )
							),
							array(
								'id' 			=> 'woo_product_title_tag',
								'type' 			=> $free ? 'content' : 'select',
								'content' 		=> Codevz_Plus::pro_badge(),
								'title' 		=> esc_html__( 'Product title tag', 'codevz-plus' ),
								'options' 		=> array(
									'h1' 			=> 'H1',
									'h2' 			=> 'H2',
									'h3' 			=> 'H3',
									'h4' 			=> 'H4',
									'hide' 			=> esc_html__( 'Hide title', 'codevz-plus' )
								),
								'default' 		=> 'h2'
							),
							array(
								'id' 		=> 'woo_live',
								'type' 		=> $free ? 'content' : 'select',
								'content' 	=> Codevz_Plus::pro_badge(),
								'title' 	=> esc_html__( 'Live visitors', 'codevz-plus' ),
								'options' 	=> array(
									''				=> esc_html__( '~ Disable ~', 'codevz-plus' ),
									'sessions'		=> esc_html__( 'Display number of product viewers', 'codevz-plus' ),
									'cart'			=> esc_html__( 'Display number of how many people added product to their cart', 'codevz-plus' )
								),
							),
							array(
								'id'    		=> 'woo_after_product_meta',
								'type'  		=> $free ? 'content' : 'textarea',
								'content' 		=> Codevz_Plus::pro_badge(),
								'title' 		=> esc_html__( 'Content after product meta', 'codevz-plus' ),
								'help' 			=> esc_html__( 'You can add text, content, HTML, or a shortcode, which will be displayed after the product meta on all product pages.', 'codevz-plus' )
							),
							array(
								'id'    		=> 'woo_secure_payment',
								'type'  		=> $free ? 'content' : 'text',
								'content' 		=> Codevz_Plus::pro_badge(),
								'title' 		=> esc_html__( 'Secure cards title', 'codevz-plus' ),
							),
							array(
								'id'    		=> 'woo_secure_payment_image',
								'type'  		=> 'upload',
								'title' 		=> esc_html__( 'Secure cards image', 'codevz-plus' ),
								'preview'       => 1
							),
							array(
								'id'    		=> 'woo_product_sticky_add_to_cart',
								'type'  		=> $free ? 'content' : 'switcher',
								'content' 		=> Codevz_Plus::pro_badge(),
								'title' 		=> esc_html__( 'Sticky add to cart?', 'codevz-plus' ),
								'help' 			=> esc_html__( 'Showing fixed add to cart section on scroll in the product single page.', 'codevz-plus' )
							),

							array(
								'type' 			=> 'notice',
								'class' 		=> 'info',
								'content' 		=> '<i class="fa fa-phone mr8"></i>' . esc_html__( 'Phone assistance box', 'codevz-plus' )
							),
							array(
								'id'    		=> 'woo_phone_assist',
								'type'  		=> $free ? 'content' : 'switcher',
								'content' 		=> Codevz_Plus::pro_badge(),
								'title' 		=> esc_html__( 'Phone assistance box', 'codevz-plus' ),
							),
							array(
								'id'    		=> 'woo_phone_assist_icon',
								'type'  		=> 'icon',
								'title' 		=> esc_html__( 'Icon', 'codevz-plus' ),
								'default'		=> 'fa fa-phone',
								'dependency' 	=> [ 'woo_phone_assist', '!=', '' ]
							),
							array(
								'id'    		=> 'woo_phone_assist_title',
								'type'  		=> $free ? 'content' : 'text',
								'content' 		=> Codevz_Plus::pro_badge(),
								'title' 		=> esc_html__( 'Small title', 'codevz-plus' ),
								'dependency' 	=> [ 'woo_phone_assist', '!=', '' ]
							),
							array(
								'id'    		=> 'woo_phone_assist_subtitle',
								'type'  		=> $free ? 'content' : 'text',
								'content' 		=> Codevz_Plus::pro_badge(),
								'title' 		=> esc_html__( 'Large title', 'codevz-plus' ),
								'dependency' 	=> [ 'woo_phone_assist', '!=', '' ]
							),
							array(
								'id'    		=> 'woo_phone_assist_link',
								'type'  		=> $free ? 'content' : 'text',
								'content' 		=> Codevz_Plus::pro_badge(),
								'title' 		=> esc_html__( 'Link', 'codevz-plus' ),
								'help' 			=> 'e.g. tel:00123456789 or your whatsapp link',
								'dependency' 	=> [ 'woo_phone_assist', '!=', '' ]
							),
							
							array(
								'type' 			=> 'notice',
								'class' 		=> 'info',
								'content' 		=> '<i class="fas fa-table-list mr8"></i>' . esc_html__( 'Product tabs', 'codevz-plus' )
							),
							array(
								'id' 		=> 'woo_product_tabs',
								'type' 		=> $free ? 'content' : 'select',
								'content' 	=> Codevz_Plus::pro_badge(),
								'title' 	=> esc_html__( 'Product tabs', 'codevz-plus' ),
								'help' 		=> esc_html__( 'Choose tabs type between default, center and vertical mode.', 'codevz-plus' ),
								'options' 	=> array(
									''			=> esc_html__( '~ Default ~', 'codevz-plus' ),
									'center'	=> esc_html__( 'Center', 'codevz-plus' ),
									'vertical'	=> esc_html__( 'Vertical', 'codevz-plus' ),
								),
							),
							array(
								'id'    		=> 'woo_product_tabs_sticky',
								'type'  		=> $free ? 'content' : 'switcher',
								'content' 		=> Codevz_Plus::pro_badge(),
								'title' 		=> esc_html__( 'Sticky tabs?', 'codevz-plus' ),
								'help' 			=> esc_html__( 'Make the product tabs row sticky at the top of the screen on page scrolls', 'codevz-plus' ),
								'dependency' 	=> [ 'woo_product_tabs', '!=', 'vertical' ]
							),
							array(
								'id'    		=> 'woo_product_tabs_icons',
								'type'  		=> $free ? 'content' : 'switcher',
								'content' 		=> Codevz_Plus::pro_badge(),
								'title' 		=> esc_html__( 'Tabs icons?', 'codevz-plus' )
							),
							array(
								'id'    		=> 'woo_product_brand_tab',
								'type'  		=> $free ? 'content' : 'switcher',
								'content' 		=> Codevz_Plus::pro_badge(),
								'title' 		=> esc_html__( 'Brand tab?', 'codevz-plus' ),
								'help' 			=> esc_html__( 'Showing brand info tab in the product page if product have a brand.', 'codevz-plus' )
							),
							array(
								'id'    		=> 'woo_product_size_guide_tab',
								'type'  		=> $free ? 'content' : 'switcher',
								'content' 		=> Codevz_Plus::pro_badge(),
								'title' 		=> esc_html__( 'Size guide tab?', 'codevz-plus' ),
								'help' 			=> esc_html__( 'Showing default size guide tab in the product page for all products, also you can add separate size guide for each product from Dashboard > Products > Size Guide.', 'codevz-plus' )
							),
							array(
								'id'    		=> 'woo_product_size_guide_tab_title',
								'type'  		=> $free ? 'content' : 'text',
								'content' 		=> Codevz_Plus::pro_badge(),
								'title' 		=> esc_html__( 'Tab title', 'codevz-plus' ),
								'help' 			=> esc_html__( 'If you want only show on some products, then leave this empty and from your dashboard add size guide and select it in edit product page.', 'codevz-plus' ),
								'dependency' 	=> [ 'woo_product_size_guide_tab', '==', 'true' ]
							),
							array(
								'id'    		=> 'woo_product_size_guide_tab_content',
								'type'  		=> $free ? 'content' : 'select',
								'options' 		=> 'posts',
								'edit_link' 	=> true,
								'query_args' 	=> [ 'post_type' => 'codevz_size_guide', 'value_title' => 1, 'posts_per_page' => -1 ],
								'default_option'=> esc_html__( '~ Disable ~', 'codevz-plus' ),
								'content' 		=> Codevz_Plus::pro_badge(),
								'title' 		=> esc_html__( 'Tab content', 'codevz-plus' ),
								'help' 			=> esc_html__( 'If you want only show on some products, then leave this empty and from your dashboard add size guide and select it in edit product page.', 'codevz-plus' ),
								'dependency' 	=> [ 'woo_product_size_guide_tab', '==', 'true' ]
							),
							array(
								'id'    		=> 'woo_product_faq_tab',
								'type'  		=> $free ? 'content' : 'switcher',
								'content' 		=> Codevz_Plus::pro_badge(),
								'title' 		=> esc_html__( 'FAQ tab?', 'codevz-plus' ),
								'help' 			=> esc_html__( 'Showing default FAQ tab in the product page for all products, also you can add separate FAQ for each product from Dashboard > Products > FAQ.', 'codevz-plus' )
							),
							array(
								'id'    		=> 'woo_product_faq_tab_title',
								'type'  		=> $free ? 'content' : 'text',
								'content' 		=> Codevz_Plus::pro_badge(),
								'title' 		=> esc_html__( 'Tab title', 'codevz-plus' ),
								'help' 			=> esc_html__( 'If you want only show on some products, then leave this empty and from your dashboard add FAQ and select it in edit product page.', 'codevz-plus' ),
								'dependency' 	=> [ 'woo_product_faq_tab', '==', 'true' ]
							),
							array(
								'id'    		=> 'woo_product_faq_tab_content',
								'type'  		=> $free ? 'content' : 'select',
								'options' 		=> 'posts',
								'edit_link' 	=> true,
								'query_args' 	=> [ 'post_type' => 'codevz_faq', 'value_title' => 1, 'posts_per_page' => -1 ],
								'default_option'=> esc_html__( '~ Disable ~', 'codevz-plus' ),
								'content' 		=> Codevz_Plus::pro_badge(),
								'title' 		=> esc_html__( 'Tab content', 'codevz-plus' ),
								'help' 			=> esc_html__( 'If you want only show on some products, then leave this empty and from your dashboard add FAQ and select it in edit product page.', 'codevz-plus' ),
								'dependency' 	=> [ 'woo_product_faq_tab', '==', 'true' ]
							),
							array(
								'id'    		=> 'woo_product_shipping_returns_tab',
								'type'  		=> $free ? 'content' : 'switcher',
								'content' 		=> Codevz_Plus::pro_badge(),
								'title' 		=> esc_html__( 'Shipping & Returns?', 'codevz-plus' ),
								'help' 			=> esc_html__( 'Showing shipping and return tab in the product page.', 'codevz-plus' )
							),
							array(
								'id'    		=> 'woo_product_shipping_returns_tab_title',
								'type'  		=> $free ? 'content' : 'text',
								'content' 		=> Codevz_Plus::pro_badge(),
								'title' 		=> esc_html__( 'Tab title', 'codevz-plus' ),
								'dependency' 	=> [ 'woo_product_shipping_returns_tab', '==', 'true' ]
							),
							array(
								'id'    		=> 'woo_product_shipping_returns_tab_content',
								'type'  		=> $free ? 'content' : 'textarea',
								'content' 		=> Codevz_Plus::pro_badge(),
								'title' 		=> esc_html__( 'Tab content', 'codevz-plus' ),
								'dependency' 	=> [ 'woo_product_shipping_returns_tab', '==', 'true' ]
							),
							array(
								'type' 			=> 'notice',
								'class' 		=> 'info',
								'content' 		=> '<i class="fas fa-sliders mr8"></i>' . esc_html__( 'More settings', 'codevz-plus' )
							),
							array(
								'id' 			=> 'woo_related_col',
								'type' 			=> 'codevz_image_select',
								'title' 		=> esc_html__( 'Related products', 'codevz-plus' ),
								'options' 		=> [
									'0' 			=> [ esc_html__( 'Off', 'codevz-plus' ) 					, Codevz_Plus::$url . 'assets/img/off.png' ],
									'2' 			=> [ '2 ' . esc_html__( 'Columns', 'codevz-plus' ) 		, Codevz_Plus::$url . 'assets/img/cols-2.png' ],
									'3' 			=> [ '3 ' . esc_html__( 'Columns', 'codevz-plus' ) 		, Codevz_Plus::$url . 'assets/img/cols-3.png' ],
									'4' 			=> [ '4 ' . esc_html__( 'Columns', 'codevz-plus' ) 		, Codevz_Plus::$url . 'assets/img/cols-4.png' ],
									'5' 			=> [ '5 ' . esc_html__( 'Columns', 'codevz-plus' ) 		, Codevz_Plus::$url . 'assets/img/cols-5.png' ],
								],
								'default' 		=> '3'
							),
							array(
								'id'    		=> 'woo_recently_viewed_products',
								'type'  		=> $free ? 'content' : 'switcher',
								'content' 		=> Codevz_Plus::pro_badge(),
								'title' 		=> esc_html__( 'Recently viewed products', 'codevz-plus' ),
							),
						),
					),

					array(
						'name'   => 'product_sk',
						'title'  => esc_html__( 'Product Styling', 'codevz-plus' ),
						'fields' => array(

							array(
								'id' 			=> '_css_woo_product_container',
								'type' 			=> 'cz_sk',
								'title' 		=> esc_html__( 'Container', 'codevz-plus' ),
								'button' 		=> esc_html__( 'Container', 'codevz-plus' ),
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'settings' 		=> array( 'background', 'border' ),
								'selector' 		=> '.woocommerce .xtra-single-product'
							),
							array(
								'id' 			=> '_css_woo_product_container_tablet',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.woocommerce .xtra-single-product'
							),
							array(
								'id' 			=> '_css_woo_product_container_mobile',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.woocommerce .xtra-single-product'
							),
							array(
								'id' 			=> '_css_woo_product_inner_container',
								'type' 			=> 'cz_sk',
								'title' 		=> esc_html__( 'Inner column', 'codevz-plus' ),
								'button' 		=> esc_html__( 'Inner column', 'codevz-plus' ),
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'settings' 		=> array( 'background', 'border' ),
								'selector' 		=> '.woocommerce div.product div.summary'
							),
							array(
								'id' 			=> '_css_woo_product_inner_container_tablet',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.woocommerce div.product div.summary'
							),
							array(
								'id' 			=> '_css_woo_product_inner_container_mobile',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.woocommerce div.product div.summary'
							),
							array(
								'id' 			=> '_css_woo_product_title',
								'type' 			=> 'cz_sk',
								'title' 		=> esc_html__( 'Title', 'codevz-plus' ),
								'button' 		=> esc_html__( 'Title', 'codevz-plus' ),
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'settings' 		=> array( 'text-align', 'color', 'font-family', 'font-size' ),
								'selector' 		=> '.woocommerce div.product .product_title'
							),
							array(
								'id' 			=> '_css_woo_product_title_tablet',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.woocommerce div.product .product_title'
							),
							array(
								'id' 			=> '_css_woo_product_title_mobile',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.woocommerce div.product .product_title'
							),
							array(
								'id' 			=> '_css_woo_product_thumbnail',
								'type' 			=> 'cz_sk',
								'title' 		=> esc_html__( 'Image', 'codevz-plus' ),
								'button' 		=> esc_html__( 'Image', 'codevz-plus' ),
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'settings' 		=> array( 'background', 'border' ),
								'selector' 		=> '.woocommerce div.product div.images img'
							),
							array(
								'id' 			=> '_css_woo_product_thumbnail_tablet',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.woocommerce div.product div.images img'
							),
							array(
								'id' 			=> '_css_woo_product_thumbnail_mobile',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.woocommerce div.product div.images img'
							),
							array(
								'id' 			=> '_css_woo_product_image_zoom',
								'type' 			=> 'cz_sk',
								'title' 		=> esc_html__( 'Zoom Icon', 'codevz-plus' ),
								'button' 		=> esc_html__( 'Zoom Icon', 'codevz-plus' ),
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'settings' 		=> array( 'background', 'border' ),
								'selector' 		=> '.woocommerce div.product div.images .woocommerce-product-gallery__trigger'
							),
							array(
								'id' 			=> '_css_woo_product_image_zoom_tablet',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.woocommerce div.product div.images .woocommerce-product-gallery__trigger'
							),
							array(
								'id' 			=> '_css_woo_product_image_zoom_mobile',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.woocommerce div.product div.images .woocommerce-product-gallery__trigger'
							),
							array(
								'id' 			=> '_css_woo_product_price',
								'type' 			=> 'cz_sk',
								'title' 		=> esc_html__( 'Price', 'codevz-plus' ),
								'button' 		=> esc_html__( 'Price', 'codevz-plus' ),
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'settings' 		=> array( 'color', 'background', 'font-family', 'font-size' ),
								'selector' 		=> '.woocommerce div.product .summary > p.price, .woocommerce div.product .summary > span.price'
							),
							array(
								'id' 			=> '_css_woo_product_price_tablet',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.woocommerce div.product .summary > p.price, .woocommerce div.product .summary > span.price'
							),
							array(
								'id' 			=> '_css_woo_product_price_mobile',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.woocommerce div.product .summary > p.price, .woocommerce div.product .summary > span.price'
							),
							array(
								'id' 			=> '_css_woo_product_sale',
								'type' 			=> 'cz_sk',
								'title' 		=> esc_html__( 'Sale Price', 'codevz-plus' ),
								'button' 		=> esc_html__( 'Sale Price', 'codevz-plus' ),
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'settings' 		=> array( 'color', 'background', 'font-family', 'font-size' ),
								'selector' 		=> '.woocommerce div.product .summary > p.price del span, .woocommerce div.product .summary > span.price del span'
							),
							array(
								'id' 			=> '_css_woo_product_sale_tablet',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.woocommerce div.product .summary > p.price del span, .woocommerce div.product .summary > span.price del span'
							),
							array(
								'id' 			=> '_css_woo_product_sale_mobile',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.woocommerce div.product .summary > p.price del span, .woocommerce div.product .summary > span.price del span'
							),
							array(
								'id' 			=> '_css_woo_product_onsale',
								'type' 			=> 'cz_sk',
								'title' 		=> esc_html__( 'Sale Badge', 'codevz-plus' ),
								'button' 		=> esc_html__( 'Sale Badge', 'codevz-plus' ),
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'settings' 		=> array( 'line-height', 'width', 'height', 'color', 'background', 'font-size', 'top', 'left', 'border' ),
								'selector' 		=> '.woocommerce.single span.onsale'
							),
							array(
								'id' 			=> '_css_woo_product_onsale_tablet',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.woocommerce.single span.onsale'
							),
							array(
								'id' 			=> '_css_woo_product_onsale_mobile',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.woocommerce.single span.onsale'
							),
							array(
								'id' 			=> '_css_woo_product_variation',
								'hover_id' 		=> '_css_woo_product_variation_hover',
								'type' 			=> 'cz_sk',
								'title' 		=> esc_html__( 'Variation option', 'codevz-plus' ),
								'button' 		=> esc_html__( 'Variation option', 'codevz-plus' ),
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'settings' 		=> array( 'color', 'opacity', 'float', 'background', 'border' ),
								'selector' 		=> '.woocommerce div.product form.cart .variations .codevz-variations-button label'
							),
							array(
								'id' 			=> '_css_woo_product_variation_tablet',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.woocommerce div.product form.cart .variations .codevz-variations-button label'
							),
							array(
								'id' 			=> '_css_woo_product_variation_mobile',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.woocommerce div.product form.cart .variations .codevz-variations-button label'
							),
							array(
								'id' 			=> '_css_woo_product_variation_hover',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.woocommerce div.product form.cart .variations .codevz-variations-button label:hover, .woocommerce div.product form.cart .variations .codevz-variations-button input[type="radio"]:checked + label'
							),
							array(
								'id' 			=> '_css_woo_product_add_to_cart',
								'hover_id' 		=> '_css_woo_product_add_to_cart_hover',
								'type' 			=> 'cz_sk',
								'title' 		=> esc_html__( 'Add to cart', 'codevz-plus' ),
								'button' 		=> esc_html__( 'Add to cart', 'codevz-plus' ),
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'settings' 		=> array( 'color', 'opacity', 'float', 'background', 'border' ),
								'selector' 		=> '.woocommerce div.product form.cart .button'
							),
							array(
								'id' 			=> '_css_woo_product_add_to_cart_tablet',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.woocommerce div.product form.cart .button'
							),
							array(
								'id' 			=> '_css_woo_product_add_to_cart_mobile',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.woocommerce div.product form.cart .button'
							),
							array(
								'id' 			=> '_css_woo_product_add_to_cart_hover',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.woocommerce div.product form.cart .button:hover'
							),
							array(
								'id' 			=> '_css_woo_product_oos',
								'type' 			=> 'cz_sk',
								'title' 		=> esc_html__( 'Out of stock', 'codevz-plus' ),
								'button' 		=> esc_html__( 'Out of stock', 'codevz-plus' ),
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'settings' 		=> array( 'color' ),
								'selector' 		=> '.woocommerce div.product .out-of-stock',
								'dependency' 	=> [ 'xxx', '==', 'xxx' ]
							),

							array(
								'id' 			=> 'xtra_control_badge_2',
								'type' 			=> 'content',
								'content' 		=> Codevz_Plus::pro_badge(),
								'dependency' 	=> $free ? [] : [ 'x', '==', 'x' ]
							),

							array(
								'id' 			=> '_css_woo_product_meta',
								'type' 			=> $free ? 'cz_sk_free' : 'cz_sk',
								'title' 		=> esc_html__( 'Meta text', 'codevz-plus' ),
								'button' 		=> esc_html__( 'Meta text', 'codevz-plus' ),
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'settings' 		=> array( 'color' ),
								'selector' 		=> '.woocommerce div.product .product_meta'
							),
							array(
								'id' 			=> '_css_woo_product_meta_a',
								'hover_id' 		=> '_css_woo_product_meta_a_hover',
								'type' 			=> $free ? 'cz_sk_free' : 'cz_sk',
								'title' 		=> esc_html__( 'Meta links', 'codevz-plus' ),
								'button' 		=> esc_html__( 'Meta links', 'codevz-plus' ),
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'settings' 		=> array( 'color' ),
								'selector' 		=> '.woocommerce div.product .product_meta a'
							),
							array(
								'id' 			=> '_css_woo_product_meta_a_hover',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.woocommerce div.product .product_meta a:hover'
							),
							array(
								'id' 			=> '_css_woo_product_qty_down',
								'hover_id' 		=> '_css_woo_product_qty_down_hover',
								'type' 			=> $free ? 'cz_sk_free' : 'cz_sk',
								'title' 		=> esc_html__( 'Qty Down', 'codevz-plus' ),
								'button' 		=> esc_html__( 'Qty Down', 'codevz-plus' ),
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'settings' 		=> array( 'color', 'background', 'border' ),
								'selector' 		=> '.quantity-down'
							),
							array(
								'id' 			=> '_css_woo_product_qty_down_tablet',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.quantity-down'
							),
							array(
								'id' 			=> '_css_woo_product_qty_down_mobile',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.quantity-down'
							),
							array(
								'id' 			=> '_css_woo_product_qty_down_hover',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.quantity-down:hover'
							),
							array(
								'id' 			=> '_css_woo_product_qty_up',
								'hover_id' 		=> '_css_woo_product_qty_up_hover',
								'type' 			=> $free ? 'cz_sk_free' : 'cz_sk',
								'title' 		=> esc_html__( 'Qty Up', 'codevz-plus' ),
								'button' 		=> esc_html__( 'Qty Up', 'codevz-plus' ),
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'settings' 		=> array( 'color', 'background', 'border' ),
								'selector' 		=> '.quantity-up'
							),
							array(
								'id' 			=> '_css_woo_product_qty_up_tablet',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.quantity-up'
							),
							array(
								'id' 			=> '_css_woo_product_qty_up_mobile',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.quantity-up'
							),
							array(
								'id' 			=> '_css_woo_product_qty_up_hover',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.quantity-up:hover'
							),
							array(
								'id' 			=> '_css_woo_product_qty',
								'hover_id' 		=> '_css_woo_product_qty_hover',
								'type' 			=> $free ? 'cz_sk_free' : 'cz_sk',
								'title' 		=> esc_html__( 'Qty Input', 'codevz-plus' ),
								'button' 		=> esc_html__( 'Qty Input', 'codevz-plus' ),
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'settings' 		=> array( 'color', 'input', 'background', 'border' ),
								'selector' 		=> '.woocommerce .quantity .qty'
							),
							array(
								'id' 			=> '_css_woo_product_qty_tablet',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.woocommerce .quantity .qty'
							),
							array(
								'id' 			=> '_css_woo_product_qty_mobile',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.woocommerce .quantity .qty'
							),
							array(
								'id' 			=> '_css_woo_product_qty_hover',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.woocommerce .quantity .qty:hover'
							),
							array(
								'id' 			=> '_css_woo_product_stars',
								'type' 			=> $free ? 'cz_sk_free' : 'cz_sk',
								'title' 		=> esc_html__( 'Rating Stars', 'codevz-plus' ),
								'button' 		=> esc_html__( 'Rating Stars', 'codevz-plus' ),
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'settings' 		=> array( 'color' ),
								'selector' 		=> '.woocommerce .woocommerce-product-rating .star-rating'
							),
							array(
								'id' 			=> '_css_woo_product_stars_tablet',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.woocommerce .woocommerce-product-rating .star-rating'
							),
							array(
								'id' 			=> '_css_woo_product_stars_mobile',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.woocommerce .woocommerce-product-rating .star-rating'
							),
							array(
								'id' 			=> '_css_woo_product_wishlist',
								'hover_id' 		=> '_css_woo_product_wishlist_hover',
								'type' 			=> $free ? 'cz_sk_free' : 'cz_sk',
								'title' 		=> esc_html__( 'Wishlist', 'codevz-plus' ),
								'button' 		=> esc_html__( 'Wishlist', 'codevz-plus' ),
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'settings' 		=> array( 'color', 'background', 'border', 'box-shadow' ),
								'selector' 		=> '.woocommerce .cart .xtra-product-icons-wishlist'
							),
							array(
								'id' 			=> '_css_woo_product_wishlist_tablet',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.woocommerce .cart .xtra-product-icons-wishlist'
							),
							array(
								'id' 			=> '_css_woo_product_wishlist_mobile',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.woocommerce .cart .xtra-product-icons-wishlist'
							),
							array(
								'id' 			=> '_css_woo_product_wishlist_hover',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.woocommerce .cart .xtra-product-icons-wishlist:hover'
							),
							array(
								'id' 			=> '_css_woo_product_compare',
								'hover_id' 		=> '_css_woo_product_compare_hover',
								'type' 			=> $free ? 'cz_sk_free' : 'cz_sk',
								'title' 		=> esc_html__( 'Compare', 'codevz-plus' ),
								'button' 		=> esc_html__( 'Compare', 'codevz-plus' ),
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'settings' 		=> array( 'color', 'background', 'border', 'box-shadow' ),
								'selector' 		=> '.woocommerce .cart .xtra-product-icons-compare'
							),
							array(
								'id' 			=> '_css_woo_product_compare_tablet',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.woocommerce .cart .xtra-product-icons-compare'
							),
							array(
								'id' 			=> '_css_woo_product_compare_mobile',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.woocommerce .cart .xtra-product-icons-compare'
							),
							array(
								'id' 			=> '_css_woo_product_compare_hover',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.woocommerce .cart .xtra-product-icons-compare:hover'
							),
							
							array(
								'type' 			=> 'notice',
								'class' 		=> 'info',
								'content' 		=> '<i class="fas fa-brush mr8"></i>' . esc_html__( 'More styling', 'codevz-plus' )
							),
							array(
								'id' 			=> '_css_woo_product_tabs',
								'hover_id' 		=> '_css_woo_product_tabs_hover',
								'type' 			=> $free ? 'cz_sk_free' : 'cz_sk',
								'title' 		=> esc_html__( 'Tabs', 'codevz-plus' ),
								'button' 		=> esc_html__( 'Tabs', 'codevz-plus' ),
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'settings' 		=> array( 'color', 'background', 'font-size', 'border' ),
								'selector' 		=> '.woocommerce div.product .woocommerce-tabs ul.tabs li'
							),
							array(
								'id' 			=> '_css_woo_product_tabs_hover',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.woocommerce div.product .woocommerce-tabs ul.tabs li:hover'
							),
							array(
								'id' 			=> '_css_woo_product_tabs_tablet',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.woocommerce div.product .woocommerce-tabs ul.tabs li'
							),
							array(
								'id' 			=> '_css_woo_product_tabs_mobile',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.woocommerce div.product .woocommerce-tabs ul.tabs li'
							),

							array(
								'id' 			=> '_css_woo_product_active_tab',
								'type' 			=> $free ? 'cz_sk_free' : 'cz_sk',
								'title' 		=> esc_html__( 'Active Tab', 'codevz-plus' ),
								'button' 		=> esc_html__( 'Active Tab', 'codevz-plus' ),
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'settings' 		=> array( 'color', 'background', 'font-size', 'border' ),
								'selector' 		=> '.woocommerce div.product .woocommerce-tabs ul.tabs li.active'
							),
							array(
								'id' 			=> '_css_woo_product_active_tab_tablet',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.woocommerce div.product .woocommerce-tabs ul.tabs li.active'
							),
							array(
								'id' 			=> '_css_woo_product_active_tab_mobile',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.woocommerce div.product .woocommerce-tabs ul.tabs li.active'
							),
							array(
								'id' 			=> '_css_woo_product_tab_content',
								'type' 			=> $free ? 'cz_sk_free' : 'cz_sk',
								'title' 		=> esc_html__( 'Tab Content', 'codevz-plus' ),
								'button' 		=> esc_html__( 'Tab Content', 'codevz-plus' ),
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'settings' 		=> array( 'color', 'background', 'font-size', 'border' ),
								'selector' 		=> '.woocommerce div.product .woocommerce-tabs .panel'
							),
							array(
								'id' 			=> '_css_woo_product_tab_content_tablet',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.woocommerce div.product .woocommerce-tabs .panel'
							),
							array(
								'id' 			=> '_css_woo_product_tab_content_mobile',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.woocommerce div.product .woocommerce-tabs .panel'
							),
							array(
								'id' 			=> '_css_woo_product_sticky_add_to_cart',
								'type' 			=> $free ? 'cz_sk_free' : 'cz_sk',
								'title' 		=> esc_html__( 'Sticky add to cart', 'codevz-plus' ),
								'button' 		=> esc_html__( 'Sticky add to cart', 'codevz-plus' ),
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'settings' 		=> array( 'color', 'background', 'border' ),
								'selector' 		=> '.cz-sticky-add-to-cart'
							),
						)
					),


					array(
						'name'   => 'products_others_sk',
						'title'  => esc_html__( 'Others Styling', 'codevz-plus' ),
						'fields' => array(

							array(
								'id' 			=> '_css_woo_others_message_box',
								'type' 			=> $free ? 'cz_sk_free' : 'cz_sk',
								'title' 		=> esc_html__( 'Message Wrapper', 'codevz-plus' ),
								'button' 		=> esc_html__( 'Message Wrapper', 'codevz-plus' ),
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'settings' 		=> array( 'color', 'border', 'border-radius' ),
								'selector' 		=> '.woocommerce .woocommerce-error, .woocommerce .woocommerce-info, .woocommerce .woocommerce-message,.woocommerce .wc-block-components-notice-banner'
							),
							array(
								'id' 			=> '_css_woo_others_message_box_tablet',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.woocommerce .woocommerce-error, .woocommerce .woocommerce-info, .woocommerce .woocommerce-message,.woocommerce .wc-block-components-notice-banner'
							),
							array(
								'id' 			=> '_css_woo_others_message_box_mobile',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.woocommerce .woocommerce-error, .woocommerce .woocommerce-info, .woocommerce .woocommerce-message,.woocommerce .wc-block-components-notice-banner'
							),

							array(
								'id' 			=> '_css_woo_others_message_box_icon',
								'type' 			=> $free ? 'cz_sk_free' : 'cz_sk',
								'title' 		=> esc_html__( 'Message SVG icon', 'codevz-plus' ),
								'button' 		=> esc_html__( 'Message SVG icon', 'codevz-plus' ),
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'settings' 		=> array( 'color', 'border', 'border-radius' ),
								'selector' 		=> '.woocommerce .wc-block-components-notice-banner > svg'
							),
							array(
								'id' 			=> '_css_woo_others_message_box_icon_tablet',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.woocommerce .wc-block-components-notice-banner > svg'
							),
							array(
								'id' 			=> '_css_woo_others_message_box_icon_mobile',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.woocommerce .wc-block-components-notice-banner > svg'
							),

							array(
								'id' 			=> '_css_woo_others_checkout_coupon',
								'type' 			=> $free ? 'cz_sk_free' : 'cz_sk',
								'title' 		=> esc_html__( 'Checkout Coupon', 'codevz-plus' ),
								'button' 		=> esc_html__( 'Checkout Coupon', 'codevz-plus' ),
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'settings' 		=> array( 'color', 'background', 'border' ),
								'selector' 		=> '.woocommerce .checkout_coupon'
							),
							array(
								'id' 			=> '_css_woo_others_checkout_coupon_tablet',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.woocommerce .checkout_coupon'
							),
							array(
								'id' 			=> '_css_woo_others_checkout_coupon_mobile',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.woocommerce .checkout_coupon'
							),

							array(
								'id' 			=> '_css_woo_others_cart_remove',
								'type' 			=> $free ? 'cz_sk_free' : 'cz_sk',
								'title' 		=> esc_html__( 'Cart Remove', 'codevz-plus' ),
								'button' 		=> esc_html__( 'Cart Remove', 'codevz-plus' ),
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'settings' 		=> array( 'color', 'background', 'border' ),
								'selector' 		=> '.woocommerce .cart_item a.remove'
							),
							array(
								'id' 			=> '_css_woo_others_cart_remove_tablet',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.woocommerce .cart_item a.remove'
							),
							array(
								'id' 			=> '_css_woo_others_cart_remove_mobile',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.woocommerce .cart_item a.remove'
							),

							array(
								'id' 			=> '_css_woo_others_cart_thumbnail',
								'type' 			=> $free ? 'cz_sk_free' : 'cz_sk',
								'title' 		=> esc_html__( 'Cart Thumbnail', 'codevz-plus' ),
								'button' 		=> esc_html__( 'Cart Thumbnail', 'codevz-plus' ),
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'settings' 		=> array( 'color', 'background', 'border' ),
								'selector' 		=> '#add_payment_method table.cart img, .woocommerce-cart table.cart img'
							),
							array(
								'id' 			=> '_css_woo_others_cart_thumbnail_tablet',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '#add_payment_method table.cart img, .woocommerce-cart table.cart img'
							),
							array(
								'id' 			=> '_css_woo_others_cart_thumbnail_mobile',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '#add_payment_method table.cart img, .woocommerce-cart table.cart img'
							),

							array(
								'id' 			=> '_css_woo_others_my_account_nav',
								'type' 			=> $free ? 'cz_sk_free' : 'cz_sk',
								'title' 		=> esc_html__( 'My account navigation', 'codevz-plus' ),
								'button' 		=> esc_html__( 'My account navigation', 'codevz-plus' ),
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'settings' 		=> array( 'background', 'border' ),
								'selector' 		=> '.woocommerce-MyAccount-navigation ul'
							),
							array(
								'id' 			=> '_css_woo_others_my_account_nav_tablet',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.woocommerce-MyAccount-navigation ul'
							),
							array(
								'id' 			=> '_css_woo_others_my_account_nav_mobile',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.woocommerce-MyAccount-navigation ul'
							),

							array(
								'id' 			=> '_css_woo_others_my_account_links',
								'hover_id' 		=> '_css_woo_others_my_account_links_hover',
								'type' 			=> $free ? 'cz_sk_free' : 'cz_sk',
								'title' 		=> esc_html__( 'My account menus', 'codevz-plus' ),
								'button' 		=> esc_html__( 'My account menus', 'codevz-plus' ),
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'settings' 		=> array( 'color', 'background', 'border' ),
								'selector' 		=> '.woocommerce-MyAccount-navigation a'
							),
							array(
								'id' 			=> '_css_woo_others_my_account_links_tablet',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.woocommerce-MyAccount-navigation a'
							),
							array(
								'id' 			=> '_css_woo_others_my_account_links_mobile',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.woocommerce-MyAccount-navigation a'
							),
							array(
								'id' 			=> '_css_woo_others_my_account_links_hover',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.woocommerce-MyAccount-navigation a:hover, .woocommerce-MyAccount-navigation .is-active a'
							),

							array(
								'id' 			=> '_css_woo_others_my_account_icons',
								'hover_id' 		=> '_css_woo_others_my_account_icons_hover',
								'type' 			=> $free ? 'cz_sk_free' : 'cz_sk',
								'title' 		=> esc_html__( 'My account icons', 'codevz-plus' ),
								'button' 		=> esc_html__( 'My account icons', 'codevz-plus' ),
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'settings' 		=> array( 'color', 'background', 'border' ),
								'selector' 		=> '.woocommerce-MyAccount-navigation a:before'
							),
							array(
								'id' 			=> '_css_woo_others_my_account_icons_tablet',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.woocommerce-MyAccount-navigation a:before'
							),
							array(
								'id' 			=> '_css_woo_others_my_account_icons_mobile',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.woocommerce-MyAccount-navigation a:before'
							),
							array(
								'id' 			=> '_css_woo_others_my_account_icons_hover',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.woocommerce-MyAccount-navigation a:hover:before, .woocommerce-MyAccount-navigation .is-active a:before'
							),

						)
					),

				)
			);
		}

		// BuddyPress options
		if ( function_exists( 'is_buddypress' ) || $all ) {
			$options[ 'post_type_buddypress' ] = array(
				'name'   => 'post_type_buddypress',
				'title'  => esc_html__( 'Buddy Press', 'codevz-plus' ),
				'fields' => wp_parse_args( 
					array(
						array(
							'id' 			=> 'layout_buddypress',
							'type' 			=> 'codevz_image_select',
							'title' 		=> esc_html__( 'Sidebar', 'codevz-plus' ),
							'options' 		=> [
								'1' 			=> [ esc_html__( '~ Default ~', 'codevz-plus' ) 		, Codevz_Plus::$url . 'assets/img/sidebar-0.png' ],
								'ws' 			=> [ esc_html__( 'No Sidebar', 'codevz-plus' ) 			, Codevz_Plus::$url . 'assets/img/off.png' ],
								'bpnp' 			=> [ esc_html__( 'Fullwidth', 'codevz-plus' ) 			, Codevz_Plus::$url . 'assets/img/sidebar-2.png' ],
								'center'		=> [ esc_html__( 'Center Mode', 'codevz-plus' ) 			, Codevz_Plus::$url . 'assets/img/sidebar-13.png' ],
								'right' 		=> [ esc_html__( 'Right Sidebar', 'codevz-plus' ) 		, Codevz_Plus::$url . 'assets/img/sidebar-3.png' ],
								'right-s' 		=> [ esc_html__( 'Right Sidebar Small', 'codevz-plus' ) 	, Codevz_Plus::$url . 'assets/img/sidebar-4.png' ],
								'left' 			=> [ esc_html__( 'Left Sidebar', 'codevz-plus' ) 		, Codevz_Plus::$url . 'assets/img/sidebar-5.png' ],
								'left-s' 		=> [ esc_html__( 'Left Sidebar Small', 'codevz-plus' ) 	, Codevz_Plus::$url . 'assets/img/sidebar-6.png' ],
								'both-side' 	=> [ esc_html__( 'Both Sidebar', 'codevz-plus' ) . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ) 		, Codevz_Plus::$url . 'assets/img/sidebar-7.png' ],
								'both-side2' 	=> [ esc_html__( 'Both Sidebar Small', 'codevz-plus' ) . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ) 	, Codevz_Plus::$url . 'assets/img/sidebar-8.png' ],
								'both-right' 	=> [ esc_html__( 'Both Sidebar Right', 'codevz-plus' ) . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ) 	, Codevz_Plus::$url . 'assets/img/sidebar-9.png' ],
								'both-right2' 	=> [ esc_html__( 'Both Sidebar Right', 'codevz-plus' ) . ' 2' . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ) , Codevz_Plus::$url . 'assets/img/sidebar-10.png' ],
								'both-left' 	=> [ esc_html__( 'Both Sidebar Left', 'codevz-plus' ) . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ) 	, Codevz_Plus::$url . 'assets/img/sidebar-11.png' ],
								'both-left2' 	=> [ esc_html__( 'Both Sidebar Left', 'codevz-plus' ) . ' 2' . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' )  , Codevz_Plus::$url . 'assets/img/sidebar-12.png' ],
							],
							'default' 		=> '1'
						),
					),
					self::title_options( '_buddypress', '.cz-cpt-buddypress ' )
				)
			);
		}

		// EDD options
		if ( function_exists( 'EDD' ) || $all ) {
			$options[ 'post_type_download' ] = array(
				'name'   => 'post_type_download',
				'title'  => esc_html__( 'Easy Digital Download', 'codevz-plus' ),
				'sections'  => array(

					array(
						'name'   => 'edd_settings',
						'title'  => esc_html__( 'EDD Settings', 'codevz-plus' ),
						'fields' => wp_parse_args(
							array(
								array(
									'id' 			=> 'layout_download',
									'type' 			=> 'codevz_image_select',
									'title' 		=> esc_html__( 'Sidebar', 'codevz-plus' ),
									'options' 		=> [
										'1' 			=> [ esc_html__( '~ Default ~', 'codevz-plus' ) 			, Codevz_Plus::$url . 'assets/img/sidebar-0.png' ],
										'ws' 			=> [ esc_html__( 'No Sidebar', 'codevz-plus' ) 			, Codevz_Plus::$url . 'assets/img/off.png' ],
										'bpnp' 			=> [ esc_html__( 'Fullwidth', 'codevz-plus' ) 			, Codevz_Plus::$url . 'assets/img/sidebar-2.png' ],
										'center'		=> [ esc_html__( 'Center Mode', 'codevz-plus' ) 			, Codevz_Plus::$url . 'assets/img/sidebar-13.png' ],
										'right' 		=> [ esc_html__( 'Right Sidebar', 'codevz-plus' ) 		, Codevz_Plus::$url . 'assets/img/sidebar-3.png' ],
										'right-s' 		=> [ esc_html__( 'Right Sidebar Small', 'codevz-plus' ) 	, Codevz_Plus::$url . 'assets/img/sidebar-4.png' ],
										'left' 			=> [ esc_html__( 'Left Sidebar', 'codevz-plus' ) 		, Codevz_Plus::$url . 'assets/img/sidebar-5.png' ],
										'left-s' 		=> [ esc_html__( 'Left Sidebar Small', 'codevz-plus' ) 	, Codevz_Plus::$url . 'assets/img/sidebar-6.png' ],
										'both-side' 	=> [ esc_html__( 'Both Sidebar', 'codevz-plus' ) . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ) 		, Codevz_Plus::$url . 'assets/img/sidebar-7.png' ],
										'both-side2' 	=> [ esc_html__( 'Both Sidebar Small', 'codevz-plus' ) . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ) 	, Codevz_Plus::$url . 'assets/img/sidebar-8.png' ],
										'both-right' 	=> [ esc_html__( 'Both Sidebar Right', 'codevz-plus' ) . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ) 	, Codevz_Plus::$url . 'assets/img/sidebar-9.png' ],
										'both-right2' 	=> [ esc_html__( 'Both Sidebar Right', 'codevz-plus' ) . ' 2' . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ) , Codevz_Plus::$url . 'assets/img/sidebar-10.png' ],
										'both-left' 	=> [ esc_html__( 'Both Sidebar Left', 'codevz-plus' ) . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ) 	, Codevz_Plus::$url . 'assets/img/sidebar-11.png' ],
										'both-left2' 	=> [ esc_html__( 'Both Sidebar Left', 'codevz-plus' ) . ' 2' . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' )  , Codevz_Plus::$url . 'assets/img/sidebar-12.png' ],
									],
									'default' 		=> '1'
								),
								array(
									'id' 			=> 'edd_col',
									'type' 			=> 'codevz_image_select',
									'title' 		=> esc_html__( 'Columns', 'codevz-plus' ),
									'options' 		=> [
										'2' 			=> [ '2 ' . esc_html__( 'Columns', 'codevz-plus' ) 		, Codevz_Plus::$url . 'assets/img/cols-2.png' ],
										'3' 			=> [ '3 ' . esc_html__( 'Columns', 'codevz-plus' ) 		, Codevz_Plus::$url . 'assets/img/cols-3.png' ],
										'4' 			=> [ '4 ' . esc_html__( 'Columns', 'codevz-plus' ) 		, Codevz_Plus::$url . 'assets/img/cols-4.png' ],
									],
									'default' 		=> '3'
								),
							),
							self::title_options( '_download', '.cz-cpt-download ' )
						)
					),

					array(
						'name'   => 'edd_styles',
						'title'  => esc_html__( 'EDD Styling', 'codevz-plus' ),
						'fields' => array(
							array(
								'id' 			=> '_css_edd_products',
								'hover_id' 		=> '_css_edd_products_hover',
								'type' 			=> 'cz_sk',
								'title' 		=> esc_html__( 'Product', 'codevz-plus' ),
								'button' 		=> esc_html__( 'Product', 'codevz-plus' ),
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'settings' 		=> array( 'background', 'border' ),
								'selector' 		=> '.cz_edd_item > article'
							),
							array(
								'id' 			=> '_css_edd_products_tablet',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.cz_edd_item > article'
							),
							array(
								'id' 			=> '_css_edd_products_mobile',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.cz_edd_item > article'
							),
							array(
								'id' 			=> '_css_edd_products_hover',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.cz_edd_item > article:hover'
							),
							array(
								'id' 			=> '_css_edd_products_img',
								'hover_id' 		=> '_css_edd_products_img_hover',
								'type' 			=> 'cz_sk',
								'title' 		=> esc_html__( 'Image', 'codevz-plus' ),
								'button' 		=> esc_html__( 'Image', 'codevz-plus' ),
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'settings' 		=> array( 'background', 'border' ),
								'selector' 		=> '.cz_edd_item .cz_edd_image'
							),
							array(
								'id' 			=> '_css_edd_products_img_hover',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.cz_edd_item > article:hover .cz_edd_image'
							),
							array(
								'id' 			=> '_css_edd_products_price',
								'hover_id' 		=> '_css_edd_products_price_hover',
								'type' 			=> 'cz_sk',
								'title' 		=> esc_html__( 'Price', 'codevz-plus' ),
								'button' 		=> esc_html__( 'Price', 'codevz-plus' ),
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'settings' 		=> array( 'font-size', 'font-weight', 'color', 'background', 'border' ),
								'selector' 		=> '.cz_edd_item .edd_price'
							),
							array(
								'id' 			=> '_css_edd_products_price_hover',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.cz_edd_item > article:hover .edd_price'
							),
							array(
								'id' 			=> '_css_edd_products_title',
								'hover_id' 		=> '_css_edd_products_title_hover',
								'type' 			=> 'cz_sk',
								'title' 		=> esc_html__( 'Title', 'codevz-plus' ),
								'button' 		=> esc_html__( 'Title', 'codevz-plus' ),
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'settings' 		=> array( 'font-size', 'font-weight', 'color', 'background', 'border' ),
								'selector' 		=> '.cz_edd_title h3'
							),
							array(
								'id' 			=> '_css_edd_products_title_hover',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.cz_edd_title h3:hover'
							),
							array(
								'id' 			=> '_css_edd_products_button',
								'hover_id' 		=> '_css_edd_products_button_hover',
								'type' 			=> 'cz_sk',
								'title' 		=> esc_html__( 'Button', 'codevz-plus' ),
								'button' 		=> esc_html__( 'Button', 'codevz-plus' ),
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'settings' 		=> array( 'font-size', 'font-weight', 'color', 'background', 'border' ),
								'selector' 		=> '.cz_edd_item a.edd-submit, .cz_edd_item .edd-submit.button.blue'
							),
							array(
								'id' 			=> '_css_edd_products_button_hover',
								'type' 			=> 'cz_sk_hidden',
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'selector' 		=> '.cz_edd_item a.edd-submit:hover, .cz_edd_item .edd-submit.button.blue:hover, .edd-submit.button.blue:focus'
							),
							array(
								'id' 			=> '_css_edd_products_purchase_options',
								'type' 			=> 'cz_sk',
								'title' 		=> esc_html__( 'Options', 'codevz-plus' ),
								'button' 		=> esc_html__( 'Options', 'codevz-plus' ),
								'setting_args' 	=> [ 'transport' => 'postMessage' ],
								'settings' 		=> array( 'color', 'background', 'border' ),
								'selector' 		=> '.cz_edd_container .edd_price_options'
							),

						)
					)
				)
			);
		}

		$options[ 'backup_section' ] = array(
			'name'   => 'backup_section',
			'title'  => esc_html__( 'Backup / Reset', 'codevz-plus' ),
			'priority' => 900,
			'fields' => array(
				array(
					'type' => 'backup'
				),
			)
		);

		return $options;
	}

	// Store only options IDs.
	public static function options_ids( $ids = [] ) {

		$options = self::options( true );

		foreach ( $options as $array ) {

			if ( isset( $array[ 'name' ] ) && ! isset( $ids[ $array[ 'name' ] ] ) ) {

				$ids[ $array[ 'name' ] ] = [];

			}

			if ( isset( $array[ 'fields' ] ) ) {

				foreach ( $array[ 'fields' ] as $field ) {

					if ( isset( $field['id'] ) ) {
						array_push( $ids[ $array[ 'name' ] ], $field['id'] );
					}

				}

			} else if ( isset( $array[ 'sections' ] ) ) {

				foreach ( $array[ 'sections' ] as $section ) {

					if ( isset( $section[ 'fields' ] ) ) {

						foreach ( $section[ 'fields' ] as $field ) {

							if ( isset( $field['id'] ) ) {
								array_push( $ids[ $array[ 'name' ] ], $field['id'] );
							}

						}

					}

				}

			}

		}

		return $ids;

	}

	/**
	 *
	 * Get CSS selector via option ID
	 * 
	 * @return string
	 *
	 */
	public static function get_selector( $i = '', $s = [] ) {

		// Current file size.
		$filesize = filesize( __FILE__ );

		// selectors array.
		$s = get_option( 'xtra_cache_selectors' );

		$s = is_array( $s ) ? $s : null;

		// Cache selectors array as a option.
		if ( $filesize != get_option( 'xtra_size_selectors' ) || ! $s ) {

			// Generate ID's for live customizer JS
			foreach( self::options( true ) as $option ) {
				if ( ! empty( $option['sections'] ) ) {
					foreach ( $option['sections'] as $section ) {
						if ( ! empty( $section['fields'] ) ) {
							foreach( $section['fields'] as $field ) {
								if ( ! empty( $field['id'] ) && ! empty( $field['selector'] ) ) {
									$s[ $field['id'] ] = $field['selector'];
								}
							}
						}
					}
				} else {
					if ( ! empty( $option['fields'] ) ) {
						foreach( $option['fields'] as $field ) {
							if ( ! empty( $field['id'] ) && ! empty( $field['selector'] ) ) {
								$s[ $field['id'] ] =  $field['selector'];
							}
						}
					}
				}
			}

			update_option( 'xtra_cache_selectors', $s );

			update_option( 'xtra_size_selectors', $filesize );

		}

		return ( $i === 'all' ) ? $s : ( isset( $s[ $i ] ) ? $s[ $i ] : '' );
	}

	/**
	 *
	 * General help texts for options
	 * 
	 * @return array
	 *
	 */
	public static function help( $i ) {

		$o = array(
			'4'				=> 'e.g. 10px 10px 10px 10px',
			'px'			=> 'e.g. 30px',
			'padding'		=> esc_html__( 'Space around an element, INSIDE of any defined margins and borders. Can set using px, %, em, ...', 'codevz-plus' ),
			'margin'		=> esc_html__( 'Space around an element, OUTSIDE of any defined borders. Can set using px, %, em, auto, ...', 'codevz-plus' ),
			'border'		=> esc_html__( 'Lines around element, e.g. 2px or manually set with this four positions respectively: <br />Top Right Bottom Left <br/><br/>e.g. 2px 2px 2px 2px', 'codevz-plus' ),
			'radius'		=> esc_html__( 'Generate the arc for lines around element, e.g. 10px or manually set with this four positions respectively: <br />Top Right Bottom Left <br/><br/>e.g. 10px 10px 10px 10px', 'codevz-plus' ),
			'default'		=> esc_html__( 'Default option', 'codevz-plus' ),
		);

		return isset( $o[ $i ] ) ? $o[ $i ] : '';
	}

	/**
	 *
	 * Header builder elements
	 * 
	 * @return array
	 *
	 */
	public static function elements( $id, $title, $dependency = array(), $pos = '' ) {

		$free = Codevz_Plus::$is_free;

		$is_fixed_side = Codevz_Plus::contains( $id, 'side' );
		$is_1_2_3 = Codevz_Plus::contains( $id, array( 'header_1', 'header_2', 'header_3' ) );
		$is_footer = Codevz_Plus::contains( $id, 'footer' );

		return array(
			'id'              => $id,
			'type'            => 'group',
			'title'           => $title,
			'button_title'    => esc_html__( 'Add', 'codevz-plus' ) . ' ' . ucwords( isset( self::$trasnlation[ $pos ] ) ? self::$trasnlation[ $pos ] : '' ),
			'accordion_title' => esc_html__( 'Add', 'codevz-plus' ) . ' ' . ucwords( isset( self::$trasnlation[ $pos ] ) ? self::$trasnlation[ $pos ] : '' ),
			'dependency'	  => $dependency,
			'setting_args' 	  => [ 'transport' => 'postMessage' ],
			'fields'          => array(

				array(
					'id' 	=> 'element',
					'type' 	=> 'select',
					'title' => esc_html__( 'Element', 'codevz-plus' ),
					'options' => array(
						'logo' 		=> esc_html__( 'Logo', 'codevz-plus' ),
						'logo_2' 	=> esc_html__( 'Logo alternative', 'codevz-plus' ) . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ),
						'menu' 		=> esc_html__( 'Menu', 'codevz-plus' ),
						'search' 	=> esc_html__( 'Search', 'codevz-plus' ),
						'social' 	=> esc_html__( 'Social icons', 'codevz-plus' ),
						'icon' 		=> esc_html__( 'Icon and text', 'codevz-plus' ),
						'icon_info' => esc_html__( 'Icon and text 2', 'codevz-plus' ),
						'line' 		=> esc_html__( 'Line', 'codevz-plus' ),
						'image' 	=> esc_html__( 'Image', 'codevz-plus' ),
						'button' 	=> esc_html__( 'Button', 'codevz-plus' ),
						'login' 	=> esc_html__( 'Login popup', 'codevz-plus' ),
						'shop_cart' => esc_html__( 'Shopping cart', 'codevz-plus' ),
						'wishlist'  => esc_html__( 'WooCommerce wishlist', 'codevz-plus' ) . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ),
						'compare' 	=> esc_html__( 'WooCommerce compare', 'codevz-plus' ) . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ),
						'wpml' 		=> esc_html__( 'WPML Selector', 'codevz-plus' ) . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ),
						'widgets' 	=> esc_html__( 'Offcanvas sidebar', 'codevz-plus' ),
						'hf_elm' 	=> esc_html__( 'Dropdown content', 'codevz-plus' ),
						'avatar' 	=> esc_html__( 'Logged-in user avatar', 'codevz-plus' ) . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ),
						'custom' 	=> esc_html__( 'Custom shortcode', 'codevz-plus' ) . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ),
						'custom_element' => esc_html__( 'Custom page', 'codevz-plus' ) . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ),
					),
					'default_option' => esc_html__( '~ Select ~', 'codevz-plus' ),
				),

				[
					'id'   		 => 'pro_message',
					'type'       => 'content',
					'content'    => esc_html__( 'Available only on PRO version.', 'codevz-plus' ),
					'dependency' => $free ? [ 'element', 'any', 'logo_2,wishlist,compare,wpml,avatar,custom,custom_element' ] : [ 'xxx', '==', 'xxx' ],
				],

				// Element ID for live customize
				array(
					'id'   		 => 'element_id',
					'title'   	 => 'ID',
					'type'       => 'text',
					'default'    => $id,
					'dependency' => array( 'xxx', '==', 'xxx' ),
				),

				// Custom
				array(
					'id' 			=> 'header_elements',
					'type' 			=> 'select',
					'title'			=> esc_html__( 'Content', 'codevz-plus' ),
					'options' 		=> Codevz_Plus::$array_pages,
					'edit_link' 	=> true,
					'dependency' 	=> array( 'element', '==', $free ? 'xxx' : 'custom_element' ),
				),
				array(
					'id'    		=> 'header_elements_width',
					'type'  		=> 'slider',
					'title' 		=> esc_html__( 'Size', 'codevz-plus' ),
					'options'		=> array( 'unit' => 'px', 'step' => 1, 'min' => 0, 'max' => 800 ),
					'dependency' 	=> array( 'element', '==', $free ? 'xxx' : 'custom_element' )
				),

				// Custom
				array(
					'id'    		=> 'custom',
					'type'  		=> 'textarea',
					'title' 		=> esc_html__( 'Custom Shortcode', 'codevz-plus' ),
					'default' 		=> 'Insert shortcode or HTML',
					'dependency' 	=> array( 'element', '==', $free ? 'xxx' : 'custom' ),
				),

				// Logo
				array(
					'id'    => 'logo_width',
					'type'  => 'slider',
					'title' => esc_html__( 'Size', 'codevz-plus' ),
					'options'	=> array( 'unit' => 'px', 'step' => 1, 'min' => 0, 'max' => 500 ),
					'dependency' => [ 'element', 'any', $free ? 'logo' : 'logo,logo_2' ],
				),
				array(
					'id'    => 'logo_width_sticky',
					'type'  => 'slider',
					'title' => esc_html__( 'Sticky Size', 'codevz-plus' ),
					'options'	=> array( 'unit' => 'px', 'step' => 1, 'min' => 0, 'max' => 500 ),
					'dependency' => array( 'element', 'any', $free ? 'logo' : 'logo,logo_2' ),
				),
				array(
					'id'    		=> 'logo_slogan',
					'type' 			=> $free ? 'content' : 'text',
					'content' 		=> Codevz_Plus::pro_badge(),
					'title' 		=> esc_html__( 'Logo slogan', 'codevz-plus' ),
					'dependency' => array( 'element', 'any', $free ? 'logo' : 'logo,logo_2' ),
				),
				array(
					'id' 			=> 'sk_logo_slogan',
					'type' 			=> $free ? 'content' : 'cz_sk',
					'content' 		=> Codevz_Plus::pro_badge(),
					'title' 		=> esc_html__( 'Slogan style', 'codevz-plus' ),
					'button' 		=> esc_html__( 'Slogan style', 'codevz-plus' ),
					'setting_args' 	=> [ 'transport' => 'postMessage' ],
					'settings' 		=> array( 'font-size', 'color', 'background', 'border' ),
					'dependency' => array( 'element', 'any', $free ? 'logo' : 'logo,logo_2' ),
				),

				// Menu
				array(
					'id' 		=> 'menu_location',
					'type' 		=> 'select',
					'title' 	=> esc_html__( 'Menu', 'codevz-plus' ),
					'help' 		=> esc_html__( 'To create or modify menus, visit Dashboard > Appearance > Menus', 'codevz-plus' ),
					'options' 	=> array(
						'' 			=> esc_html__( '~ Select ~', 'codevz-plus' ), 
						'primary' 	=> esc_html__( 'Primary', 'codevz-plus' ), 
						'secondary' => esc_html__( 'Secondary', 'codevz-plus' ), 
						'one-page'  => esc_html__( 'One Page', 'codevz-plus' ), 
						'footer'  	=> esc_html__( 'Footer', 'codevz-plus' ),
						'mobile'  	=> esc_html__( 'Mobile', 'codevz-plus' ),
						'custom-1' 	=> esc_html__( 'Custom 1', 'codevz-plus' ) . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ), 
						'custom-2' 	=> esc_html__( 'Custom 2', 'codevz-plus' ) . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ), 
						'custom-3' 	=> esc_html__( 'Custom 3', 'codevz-plus' ) . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ),
						'custom-4' 	=> esc_html__( 'Custom 4', 'codevz-plus' ) . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ),
						'custom-5' 	=> esc_html__( 'Custom 5', 'codevz-plus' ) . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ),
						'custom-6' 	=> esc_html__( 'Custom 6', 'codevz-plus' ) . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ),
						'custom-7' 	=> esc_html__( 'Custom 7', 'codevz-plus' ) . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ),
						'custom-8' 	=> esc_html__( 'Custom 8', 'codevz-plus' ) . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' )
					),
					'edit_link'  => get_admin_url( false, 'nav-menus.php' ),
					'dependency' => array( 'element', '==', 'menu' ),
				),
				array(
					'id'    => 'menu_type',
					'type'  => 'select',
					'title' => esc_html__( 'Type', 'codevz-plus' ),
					'options' 	=> array(
						'' 							   => esc_html__( '~ Default ~', 'codevz-plus' ),
						'offcanvas_menu_left' 		   => esc_html__( 'Offcanvas left', 'codevz-plus' ),
						'offcanvas_menu_right' 		   => esc_html__( 'Offcanvas right', 'codevz-plus' ),
						'fullscreen_menu' 			   => esc_html__( 'Full screen', 'codevz-plus' ),
						'dropdown_menu' 			   => esc_html__( 'Dropdown', 'codevz-plus' ),
						'open_horizontal inview_left'  => esc_html__( 'Sliding menu left', 'codevz-plus' ) . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ),
						'open_horizontal inview_right' => esc_html__( 'Sliding menu right', 'codevz-plus' ) . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ),
						'left_side_dots side_dots' 	   => esc_html__( 'Vertical dots left', 'codevz-plus' ) . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ),
						'right_side_dots side_dots'    => esc_html__( 'Vertical dots right', 'codevz-plus' ) . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ),
					),
					'dependency' => array( 'element', '==', 'menu' ),
				),
				array(
					'id'    		=> 'menu_icon',
					'type'  		=> 'icon',
					'title' 		=> esc_html__( 'Icon', 'codevz-plus' ),
					'dependency' 	=> array( 'element|menu_type', '==|any', 'menu|offcanvas_menu_left,offcanvas_menu_right,fullscreen_menu,dropdown_menu,open_horizontal inview_left,open_horizontal inview_right' ),
				),
				array(
					'id'    		=> 'menu_title',
					'type'  		=> 'text',
					'title' 		=> esc_html__( 'Title', 'codevz-plus' ),
					'dependency' 	=> array( 'element|menu_type', 'any|any', 'menu,widgets|offcanvas_menu_left,offcanvas_menu_right,fullscreen_menu,dropdown_menu,open_horizontal inview_left,open_horizontal inview_right' ),
				),
				array(
					'id' 			=> 'sk_menu_icon',
					'hover_id' 		=> 'sk_menu_icon_hover',
					'type' 			=> 'cz_sk',
					'title' 		=> esc_html__( 'Icon Style', 'codevz-plus' ),
					'button' 		=> esc_html__( 'Icon Style', 'codevz-plus' ),
					'setting_args' 	=> [ 'transport' => 'postMessage' ],
					'settings' 		=> array( 'font-size', 'color', 'background', 'border' ),
					'dependency' 	=> array( 'element|menu_type', '==|any', 'menu|offcanvas_menu_left,offcanvas_menu_right,fullscreen_menu,dropdown_menu,open_horizontal inview_left,open_horizontal inview_right' ),
				),
				array( 'id' => 'sk_menu_icon_hover', 'type' => 'cz_sk_hidden' ),
				array(
					'id' 			=> 'sk_menu_title',
					'hover_id' 		=> 'sk_menu_title_hover',
					'type' 			=> 'cz_sk',
					'title' 		=> esc_html__( 'Title Style', 'codevz-plus' ),
					'button' 		=> esc_html__( 'Title Style', 'codevz-plus' ),
					'setting_args' 	=> [ 'transport' => 'postMessage' ],
					'settings' 		=> array( 'font-size', 'font-family', 'color', 'background', 'border' ),
					'dependency' 	=> array( 'element|menu_title', 'any|!=', 'menu,widgets|' ),
				),
				array( 'id' => 'sk_menu_title_hover', 'type' => 'cz_sk_hidden' ),
				array(
					'id' 			=> 'menu_disable_dots',
					'type' 			=> 'switcher',
					'title'			=> esc_html__( 'Disable Dots', 'codevz-plus' ),
					'dependency' 	=> array( 'element|menu_type', '==|!=', 'menu|offcanvas_menu_left,offcanvas_menu_right,fullscreen_menu,dropdown_menu,open_horizontal inview_left,open_horizontal inview_right,left_side_dots side_dots,right_side_dots side_dots' ),
				),

				// Social
				array(
					'type'    		=> 'content',
					'content' 		=> '<a href="#" onclick="wp.customize.section( \'codevz_theme_options-header_social\' ).focus()" class="button xtra-goto"><i class="fa fa-cog mr8"></i> ' . esc_html__( 'Social Icons Settings', 'codevz-plus' ) . '</a>',
					'dependency' 	=> array( 'element', '==', 'social' ),
				),
				array(
					'id'    => 'social_type',
					'type'  => 'select',
					'title' => esc_html__( 'Type', 'codevz-plus' ),
					'options' 	=> array(
						'' 				=> esc_html__( '~ Default ~', 'codevz-plus' ),
						'popup' 		=> esc_html__( 'Popup', 'codevz-plus' ) . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ),
						'dropdown' 		=> esc_html__( 'Dropdown', 'codevz-plus' ) . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ),
					),
					'dependency' => array( 'element', '==', 'social' ),
				),
				array(
					'id' 			=> 'social_columnar',
					'type' 			=> $free ? 'content' : 'switcher',
					'content' 		=> Codevz_Plus::pro_badge(),
					'title' 		=> esc_html__( 'Columnar', 'codevz-plus' ),
					'dependency' 	=> array( 'element', '==', 'social' ),
				),
				array(
					'id' 			=> 'social_icon',
					'type' 			=> $free ? 'content' : 'icon',
					'content' 		=> Codevz_Plus::pro_badge(),
					'title' 		=> esc_html__( 'Icon', 'codevz-plus' ),
					'dependency' 	=> [ 'element|social_type', '==|!=', 'social|' ],
				),
				array(
					'id' 			=> 'sk_social_icon',
					'hover_id' 		=> 'sk_social_icon_hover',
					'type' 			=> 'cz_sk',
					'title' 		=> esc_html__( 'Icon', 'codevz-plus' ),
					'button' 		=> esc_html__( 'Icon', 'codevz-plus' ),
					'setting_args' 	=> [ 'transport' => 'postMessage' ],
					'settings' 		=> [ 'font-size', 'color', 'background', 'border' ],
					'dependency' 	=> [ 'element|social_type', '==|!=', 'social|' ],
				),
				array( 'id' => 'sk_social_icon_hover', 'type' => 'cz_sk_hidden' ),
				array(
					'id' 			=> 'sk_social_container',
					'type' 			=> 'cz_sk',
					'title' 		=> esc_html__( 'Container', 'codevz-plus' ),
					'button' 		=> esc_html__( 'Container', 'codevz-plus' ),
					'setting_args' 	=> [ 'transport' => 'postMessage' ],
					'settings' 		=> array( 'background', 'border' ),
					'dependency' 	=> array( 'element|social_type', '==|!=', 'social|' ),
				),

				// Image
				array(
					'id'    => 'image',
					'type'  => 'upload',
					'title' => esc_html__( 'Image', 'codevz-plus' ),
					'preview'       => 1,
					'dependency' => array( 'element', '==', 'image' ),
					'attributes' => array(
						'style'		=> 'display: block'
					)
				),
				array(
					'id'    => 'image_width',
					'type'  => 'slider',
					'title' => esc_html__( 'Size', 'codevz-plus' ),
					'options'	=> array( 'unit' => 'px', 'step' => 1, 'min' => 0, 'max' => 800 ),
					'dependency' => array( 'element', '==', 'image' ),
				),
				array(
					'id'    => 'image_link',
					'type'  => 'text',
					'title' => esc_html__( 'Link', 'codevz-plus' ),
					'dependency' => array( 'element', '==', 'image' ),
				),
				array(
					'id' 	=> 'image_new_tab',
					'type' 	=> 'switcher',
					'title' => esc_html__( 'New Tab?', 'codevz-plus' ),
					'dependency' => array( 'element', '==', 'image' ),
				),
				array(
					'id' 			=> 'sk_image',
					'type' 			=> 'cz_sk',
					'title' 		=> esc_html__( 'Image style', 'codevz-plus' ),
					'button' 		=> esc_html__( 'Image style', 'codevz-plus' ),
					'setting_args' 	=> [ 'transport' => 'postMessage' ],
					'settings' 		=> [ 'background', 'border' ],
					'dependency' 	=> array( 'element', '==', 'image' ),
				),

				// Icon & Text
				array(
					'id'    => 'it_icon',
					'type'  => 'icon',
					'title' => esc_html__( 'Icon', 'codevz-plus' ),
					'dependency' => array( 'element', 'any', 'icon,icon_info' ),
				),
				array(
					'id'    		=> 'it_text',
					'type'  		=> 'textarea',
					'title' 		=> esc_html__( 'Text', 'codevz-plus' ),
					'default'  		=> esc_html__( 'I am a text', 'codevz-plus' ),
					'help'  		=> esc_html__( 'Instead of the current year, you can use [codevz_year]', 'codevz-plus' ),
					'dependency' 	=> array( 'element', 'any', 'icon,icon_info' ),
				),
				array(
					'id'    		=> 'it_text_2',
					'type'  		=> 'textarea',
					'title' 		=> esc_html__( 'Text 2', 'codevz-plus' ),
					'default'  		=> esc_html__( 'I am text 2', 'codevz-plus' ),
					'help'  		=> esc_html__( 'Instead of the current year, you can use [codevz_year]', 'codevz-plus' ),
					'dependency' 	=> array( 'element', '==', 'icon_info' ),
				),
				array(
					'id' 			=> 'it_link',
					'type' 			=> 'text',
					'title' 		=> esc_html__( 'Link', 'codevz-plus' ),
					'dependency' 	=> array( 'element', 'any', 'icon,icon_info' ),
				),
				array(
					'id' 			=> 'it_link_target',
					'type' 			=> 'switcher',
					'title' 		=> esc_html__( 'New Tab?', 'codevz-plus' ),
					'dependency' 	=> array( 'element', 'any', 'icon,icon_info' ),
				),
				array(
					'id' 			=> 'sk_it_wrap',
					'hover_id' 		=> 'sk_it_wrap_hover',
					'type' 			=> 'cz_sk',
					'title' 		=> esc_html__( 'Wrap Style', 'codevz-plus' ),
					'button' 		=> esc_html__( 'Wrap Style', 'codevz-plus' ),
					'setting_args' 	=> [ 'transport' => 'postMessage' ],
					'settings' 		=> array( 'background', 'border' ),
					'dependency' 	=> array( 'element', 'any', 'icon_info' )
				),
				array( 'id' => 'sk_it_wrap_hover', 'type' => 'cz_sk_hidden' ),
				array(
					'id' 			=> 'sk_it',
					'hover_id' 		=> 'sk_it_hover',
					'type' 			=> 'cz_sk',
					'title' 		=> esc_html__( 'Text Style', 'codevz-plus' ),
					'button' 		=> esc_html__( 'Text Style', 'codevz-plus' ),
					'setting_args' 	=> [ 'transport' => 'postMessage' ],
					'settings' 		=> array( 'font-size', 'color', 'background' ),
					'dependency' 	=> array( 'element', 'any', 'icon,icon_info' )
				),
				array( 'id' => 'sk_it_hover', 'type' => 'cz_sk_hidden' ),
				array(
					'id' 			=> 'sk_it_2',
					'hover_id' 		=> 'sk_it_2_hover',
					'type' 			=> 'cz_sk',
					'title' 		=> esc_html__( 'Text 2 Style', 'codevz-plus' ),
					'button' 		=> esc_html__( 'Text 2 Style', 'codevz-plus' ),
					'setting_args' 	=> [ 'transport' => 'postMessage' ],
					'settings' 		=> array( 'font-size', 'color' ),
					'dependency' 	=> array( 'element', '==', 'icon_info' )
				),
				array( 'id' => 'sk_it_2_hover', 'type' => 'cz_sk_hidden' ),
				array(
					'id' 			=> 'sk_it_icon',
					'hover_id' 		=> 'sk_it_icon_hover',
					'type' 			=> 'cz_sk',
					'title' 		=> esc_html__( 'Icon Style', 'codevz-plus' ),
					'button' 		=> esc_html__( 'Icon Style', 'codevz-plus' ),
					'setting_args' 	=> [ 'transport' => 'postMessage' ],
					'settings' 		=> array( 'font-size', 'color', 'background', 'border' ),
					'dependency' 	=> array( 'element', 'any', 'icon,icon_info' )
				),
				array('id' => 'sk_it_icon_hover','type' => 'cz_sk_hidden'),

				// Search
				array(
					'id' 	=> 'search_type',
					'type' 	=> 'select',
					'title' => esc_html__( 'Type', 'codevz-plus' ),
					'options' 	=> array(
						'icon_dropdown' => esc_html__( 'Dropdown', 'codevz-plus' ),
						'form' 			=> esc_html__( 'Form', 'codevz-plus' ),
						'form_2' 		=> esc_html__( 'Form', 'codevz-plus' ) . ' 2',
						'icon_full' 	=> esc_html__( 'Full screen', 'codevz-plus' ),
					),
					'dependency' => array( 'element', '==', 'search' ),
				),
				array(
					'id'    => 'search_icon',
					'type'  => 'icon',
					'title' => esc_html__( 'Icon', 'codevz-plus' ),
					'dependency' => array( 'element', '==', 'search' ),
				),
				array(
					'id'    => 'search_placeholder',
					'type'  => 'text',
					'title' => esc_html__( 'Title', 'codevz-plus' ),
					'dependency' => array( 'element', '==', 'search' ),
				),
				array(
					'id'    => 'search_trending_title',
					'type'  => 'text',
					'title' => esc_html__( 'Trending title', 'codevz-plus' ),
					'dependency' => array( 'element|search_type', '==|any', 'search|icon_dropdown,icon_full' ),
				),
				array(
					'id'    => 'search_trending_items',
					'type'  => 'text',
					'title' => esc_html__( 'Trending', 'codevz-plus' ),
					'help'  => esc_html__( 'Separate with comma', 'codevz-plus' ),
					'dependency' => array( 'element|search_type', '==|any', 'search|icon_dropdown,icon_full' ),
				),
				array(
					'id'    => 'search_form_width',
					'type'  => 'slider',
					'title' => esc_html__( 'Size', 'codevz-plus' ),
					'options' => array( 'unit' => 'px', 'step' => 1, 'min' => 100, 'max' => 500 ),
					'dependency' => array( 'element|search_type', '==|any', 'search|form,form_2' ),
				),
				array(
					'id' 			=> 'sk_search_title',
					'type' 			=> 'cz_sk',
					'title' 		=> esc_html__( 'Title Style', 'codevz-plus' ),
					'button' 		=> esc_html__( 'Title Style', 'codevz-plus' ),
					'setting_args' 	=> [ 'transport' => 'postMessage' ],
					'settings' 		=> array( 'font-size', 'color' ),
					'dependency' 	=> array( 'element|search_type', '==|==', 'search|icon_full' )
				),
				array(
					'id' 			=> 'sk_search_trending',
					'hover_id' 		=> 'sk_search_trending_hover',
					'type' 			=> 'cz_sk',
					'title' 		=> esc_html__( 'Trending items', 'codevz-plus' ),
					'button' 		=> esc_html__( 'Trending items', 'codevz-plus' ),
					'setting_args' 	=> [ 'transport' => 'postMessage' ],
					'settings' 		=> array( 'font-size', 'color', 'background', 'border' ),
					'dependency' 	=> array( 'element|search_type', '==|any', 'search|icon_dropdown,icon_full' )
				),
				array( 'id' => 'sk_search_trending_hover','type' => 'cz_sk_hidden' ),
				array(
					'id' 			=> 'sk_search_con',
					'type' 			=> 'cz_sk',
					'title' 		=> esc_html__( 'Search', 'codevz-plus' ),
					'button' 		=> esc_html__( 'Search', 'codevz-plus' ),
					'setting_args' 	=> [ 'transport' => 'postMessage' ],
					'settings' 		=> array( 'background', 'border' ),
					'dependency' 	=> array( 'element', '==', 'search' ),
				),
				array(
					'id' 			=> 'sk_search_input',
					'type' 			=> 'cz_sk',
					'title' 		=> esc_html__( 'Search Input', 'codevz-plus' ),
					'button' 		=> esc_html__( 'Search Input', 'codevz-plus' ),
					'setting_args' 	=> [ 'transport' => 'postMessage' ],
					'settings' 		=> array( 'font-size', 'color', 'background', 'border' ),
					'dependency' 	=> array( 'element', '==', 'search' )
				),
				array(
					'id' 			=> 'sk_search_icon',
					'hover_id' 		=> 'sk_search_icon_hover',
					'type' 			=> 'cz_sk',
					'title' 		=> esc_html__( 'Search Icon', 'codevz-plus' ),
					'button' 		=> esc_html__( 'Search Icon', 'codevz-plus' ),
					'setting_args' 	=> [ 'transport' => 'postMessage' ],
					'settings' 		=> array( 'font-size', 'color', 'background', 'border' ),
					'dependency' 	=> array( 'element|search_type', '==|any', 'search|icon_dropdown,icon_full,icon_fullrow' ),
				),
				array( 'id' => 'sk_search_icon_hover','type' => 'cz_sk_hidden' ),
				array(
					'id' 			=> 'sk_search_icon_in',
					'type' 			=> 'cz_sk',
					'title' 		=> esc_html__( 'Input Icon', 'codevz-plus' ),
					'button' 		=> esc_html__( 'Input Icon', 'codevz-plus' ),
					'setting_args' 	=> [ 'transport' => 'postMessage' ],
					'settings' 		=> array( 'font-size', 'color', 'background', 'border' ),
					'dependency' 	=> array( 'element', '==', 'search' ),
				),
				array(
					'id' 		=> 'search_only_products',
					'type' 		=> $free ? 'content' : 'switcher',
					'content' 	=> Codevz_Plus::pro_badge(),
					'title'		=> esc_html__( 'Only products?', 'codevz-plus' ),
					'dependency' => array( 'element', '==', 'search' ),
				),
				array(
					'id' 		=> 'search_products_categories',
					'type' 		=> $free ? 'content' : 'switcher',
					'content' 	=> Codevz_Plus::pro_badge(),
					'title'		=> esc_html__( 'Category selection?', 'codevz-plus' ),
					'dependency' => array( 'element|search_only_products', '==|==', 'search|true' ),
				),
				array(
					'id' 			=> 'sk_search_cat_selection',
					'type' 			=> 'cz_sk',
					'title' 		=> esc_html__( 'Category selection', 'codevz-plus' ),
					'button' 		=> esc_html__( 'Category selection', 'codevz-plus' ),
					'setting_args' 	=> [ 'transport' => 'postMessage' ],
					'settings' 		=> array( 'font-size', 'color', 'background', 'border' ),
					'dependency' 	=> array( 'element|search_products_categories', '==|==', 'search|true' ),
				),
				array(
					'id' 			=> 'sk_search_cat_list',
					'type' 			=> 'cz_sk',
					'title' 		=> esc_html__( 'Categories list', 'codevz-plus' ),
					'button' 		=> esc_html__( 'Categories list', 'codevz-plus' ),
					'setting_args' 	=> [ 'transport' => 'postMessage' ],
					'settings' 		=> array( 'font-size', 'color', 'background', 'border' ),
					'dependency' 	=> array( 'element|search_products_categories', '==|==', 'search|true' ),
				),
				array(
					'id' 		=> 'ajax_search',
					'type' 		=> $free ? 'content' : 'switcher',
					'content' 	=> Codevz_Plus::pro_badge(),
					'title'		=> esc_html__( 'Ajax search?', 'codevz-plus' ),
					'help'		=> esc_html__( 'Navigate to Blog > Search Settings to configure the search query', 'codevz-plus' ),
					'dependency' => array( 'element', '==', 'search' ),
				),
				array(
					'id' 		=> 'search_count',
					'type' 		=> 'slider',
					'title'		=> esc_html__( 'Count', 'codevz-plus' ),
					'options' 	=> array( 'unit' => '', 'step' => 1, 'min' => 1, 'max' => 12 ),
					'dependency' => array( 'element', '==', 'search' ),
				),
				array(
					'id' 		=> 'search_no_thumbnail',
					'type' 		=> $free ? 'content' : 'switcher',
					'content' 	=> Codevz_Plus::pro_badge(),
					'title'		=> esc_html__( 'No Image', 'codevz-plus' ),
					'dependency' => array( 'ajax_search|element', '!=|==', '|search' ),
				),
				array(
					'id' 		=> 'search_post_icon',
					'type' 		=> $free ? 'content' : 'icon',
					'content' 	=> Codevz_Plus::pro_badge(),
					'title'		=> esc_html__( 'Icon', 'codevz-plus' ),
					'help'		=> esc_html__( 'Icon for posts without image', 'codevz-plus' ),
					'dependency' => array( 'ajax_search|element', '!=|==', '|search' ),
				),
				array(
					'id' 			=> 'sk_search_ajax',
					'type' 			=> $free ? 'content' : 'cz_sk',
					'content' 		=> Codevz_Plus::pro_badge(),
					'title' 		=> esc_html__( 'Container', 'codevz-plus' ),
					'button' 		=> esc_html__( 'Container', 'codevz-plus' ),
					'setting_args' 	=> [ 'transport' => 'postMessage' ],
					'settings' 		=> array( 'background', 'border' ),
					'dependency' => array( 'ajax_search|element', '!=|==', '|search' ),
				),
				array(
					'id' 			=> 'sk_search_post_icon',
					'type' 			=> $free ? 'content' : 'cz_sk',
					'content' 		=> Codevz_Plus::pro_badge(),
					'title' 		=> esc_html__( 'Posts Icon', 'codevz-plus' ),
					'button' 		=> esc_html__( 'Posts Icon', 'codevz-plus' ),
					'setting_args' 	=> [ 'transport' => 'postMessage' ],
					'settings' 		=> array( 'color', 'background', 'border' ),
					'dependency' => array( 'ajax_search|element', '!=|==', '|search' ),
				),

				// Offcanvas
				array(
					'id' 		=> 'inview_position_widget',
					'type' 		=> 'select',
					'title' 	=> esc_html__( 'Position', 'codevz-plus' ),
					'help' 		=> esc_html__( 'For adding or changing widgets in offcanvas area, go to Appearance > Widgets > Offcanvas', 'codevz-plus' ),
					'options' 	=> array(
						'inview_left' 	=> esc_html__( 'Left', 'codevz-plus' ),
						'inview_right' => esc_html__( 'Right', 'codevz-plus' ),
					),
					'dependency' => array( 'element', '==', 'widgets' ),
				),
				array(
					'id' 			=> 'sk_offcanvas',
					'type' 			=> 'cz_sk',
					'title' 		=> esc_html__( 'Offcanvas', 'codevz-plus' ),
					'button' 		=> esc_html__( 'Offcanvas', 'codevz-plus' ),
					'setting_args' 	=> [ 'transport' => 'postMessage' ],
					'settings' 		=> array( 'background', 'border' ),
					'dependency' 	=> array( 'element', '==', 'widgets' )
				),
				array(
					'id'    => 'offcanvas_icon',
					'type'  => 'icon',
					'title' => esc_html__( 'Icon', 'codevz-plus' ),
					'dependency' => array( 'element', '==', 'widgets' ),
				),
				array(
					'id' 			=> 'sk_offcanvas_icon',
					'hover_id' 		=> 'sk_offcanvas_icon_hover',
					'type' 			=> 'cz_sk',
					'title' 		=> esc_html__( 'Icon Style', 'codevz-plus' ),
					'button' 		=> esc_html__( 'Icon Style', 'codevz-plus' ),
					'setting_args' 	=> [ 'transport' => 'postMessage' ],
					'settings' 		=> array( 'color', 'background', 'border' ),
					'dependency' 	=> array( 'element', '==', 'widgets' )
				),
				array('id' => 'sk_offcanvas_icon_hover','type' => 'cz_sk_hidden'),

				// Button options
				array(
					'id'    	=> 'btn_title',
					'type'  	=> 'text',
					'title' 	=> esc_html__( 'Button title', 'codevz-plus' ),
					'default' 	=> esc_html__( 'Button title', 'codevz-plus' ),
					'dependency' => array( 'element', 'any', 'button,login' ),
				),
				array(
					'id'    	=> 'btn_title_after_login',
					'type'  	=> 'text',
					'title' 	=> esc_html__( 'Button after login', 'codevz-plus' ),
					'dependency' => array( 'element', '==', 'login' ),
				),
				array(
					'id'    => 'btn_link',
					'type'  => 'text',
					'title' => esc_html__( 'Link', 'codevz-plus' ),
					'dependency' => array( 'element', '==', 'button' ),
				),
				array(
					'id' 			=> 'btn_link_target',
					'type' 			=> 'switcher',
					'title' 		=> esc_html__( 'New Tab?', 'codevz-plus' ),
					'dependency' 	=> array( 'element', '==', 'button' ),
				),
				array(
					'id' 			=> 'sk_btn',
					'hover_id' 		=> 'sk_btn_hover',
					'type' 			=> 'cz_sk',
					'title' 		=> esc_html__( 'Button Style', 'codevz-plus' ),
					'button' 		=> esc_html__( 'Button Style', 'codevz-plus' ),
					'setting_args' 	=> [ 'transport' => 'postMessage' ],
					'settings' 		=> array( 'color', 'font-family', 'font-weight', 'background', 'border' ),
					'dependency' 	=> array( 'element', '==', 'button' )
				),
				array('id' => 'sk_btn_hover','type' => 'cz_sk_hidden'),

				// Hidden fullwidth content area
				array(
					'id' 			=> 'hf_elm_page',
					'type' 			=> 'select',
					'title'			=> esc_html__( 'Content', 'codevz-plus' ),
					'help' 			=> esc_html__( 'You can create a new page from Dashboard > Page and assign it here', 'codevz-plus' ),
					'options' 		=> Codevz_Plus::$array_pages,
					'edit_link' 	=> true,
					'dependency' 	=> array( 'element', '==', 'hf_elm' ),
				),
				array(
					'id' 			=> 'sk_hf_elm',
					'type' 			=> 'cz_sk',
					'title' 		=> esc_html__( 'Container', 'codevz-plus' ),
					'button' 		=> esc_html__( 'Container', 'codevz-plus' ),
					'setting_args' 	=> [ 'transport' => 'postMessage' ],
					'settings' 		=> array( 'background', 'border' ),
					'dependency' 	=> array( 'element', '==', 'hf_elm' )
				),

				array(
					'id'    => 'login',
					'type'  => 'switcher',
					'title' => esc_html__( 'Login form?', 'codevz-plus' ),
					'dependency' => array( 'element', '==', 'login' ),
				),
				array(
					'id'    => 'register',
					'type'  => 'switcher',
					'title' => esc_html__( 'Registration form?', 'codevz-plus' ),
					'dependency' => array( 'element', '==', 'login' ),
				),
				array(
					'id'    => 'pass_r',
					'type'  => 'switcher',
					'title' => esc_html__( 'Password recovery?', 'codevz-plus' ),
					'dependency' => array( 'element', '==', 'login' ),
				),
				array(
					'id'    => 'show',
					'type'  => 'switcher',
					'title' => esc_html__( 'Show for admin?', 'codevz-plus' ),
					'dependency' => array( 'element', '==', 'login' ),
				),
				array(
					'id' 		=> 'banner',
					'type' 		=> 'select',
					'title' 	=> esc_html__( 'Banner', 'codevz-plus' ),
					'options' 	=> array(
						''  => esc_html__( '~ Select ~', 'codevz-plus' ),
						'cz_lrpr_banner cz_lrpr_banner_left'  => esc_html__( 'Left', 'codevz-plus' ),
						'cz_lrpr_banner cz_lrpr_banner_right' => esc_html__( 'Right', 'codevz-plus' ),
					),
					'dependency' => array( 'element', '==', 'login' ),
				),
				array(
					'id' 		=> 'banner_visibility',
					'type' 		=> 'select',
					'title' 	=> esc_html__( 'Banner visibility', 'codevz-plus' ),
					'options' 	=> array(
						''  => esc_html__( 'Hide for logged in users', 'codevz-plus' ),
						'1' => esc_html__( 'Show for logged in users', 'codevz-plus' ),
					),
					'dependency' => array( 'element', '==', 'login' ),
				),
				array(
					'id'    	=> 'banner_image',
					'type'  	=> 'upload',
					'title' 	=> esc_html__( 'Banner image', 'codevz-plus' ),
					'preview'   => 1,
					'dependency' => array( 'element', '==', 'login' ),
					'attributes' => array(
						'style'		=> 'display: block'
					)
				),
				array(
					'id'    		=> 'content_l',
					'type'  		=> 'textarea',
					'title' 		=> esc_html__( 'Login form title', 'codevz-plus' ),
					'dependency' 	=> array( 'element', '==', 'login' ),
				),
				array(
					'id'    		=> 'content_r',
					'type'  		=> 'textarea',
					'title' 		=> esc_html__( 'Registration form title', 'codevz-plus' ),
					'dependency' 	=> array( 'element', '==', 'login' ),
				),
				array(
					'id'    		=> 'content_pr',
					'type'  		=> 'textarea',
					'title' 		=> esc_html__( 'Password recovery title', 'codevz-plus' ),
					'dependency' 	=> array( 'element', '==', 'login' ),
				),
				array(
					'id'    		=> 'redirect',
					'type'  		=> 'text',
					'title' 		=> esc_html__( 'Redirect URL', 'codevz-plus' ),
					'dependency' 	=> array( 'element', '==', 'login' ),
				),

				array(
					'id'    => 'hf_elm_icon',
					'type'  => 'icon',
					'title' => esc_html__( 'Icon', 'codevz-plus' ),
					'dependency' => array( 'element', 'any', 'hf_elm,button,login' ),
				),
				array(
					'id' 		=> 'btn_icon_pos',
					'type' 		=> 'select',
					'title' 	=> esc_html__( 'Icon position', 'codevz-plus' ),
					'options' 	=> array(
						'' 			=> esc_html__( 'Before Title', 'codevz-plus' ),
						'after' 	=> esc_html__( 'After Title', 'codevz-plus' ),
					),
					'dependency' => array( 'element', 'any', 'button,login' ),
				),

				array(
					'id' 			=> 'sk_hf_elm_icon',
					'hover_id' 		=> 'sk_hf_elm_icon_hover',
					'type' 			=> 'cz_sk',
					'title' 		=> esc_html__( 'Icon style', 'codevz-plus' ),
					'button' 		=> esc_html__( 'Icon style', 'codevz-plus' ),
					'setting_args' 	=> [ 'transport' => 'postMessage' ],
					'settings' 		=> array( 'color', 'background', 'border' ),
					'dependency' 	=> array( 'element', 'any', 'hf_elm,button,login' ),
				),
				array( 'id' => 'sk_hf_elm_icon_hover', 'type' => 'cz_sk_hidden' ),

				array(
					'id'    		=> 'css_width',
					'type'  		=> 'slider',
					'title' 		=> esc_html__( 'Container width', 'codevz-plus' ),
					'dependency' 	=> array( 'element', '==', 'login' )
				),
				array(
					'id' 			=> 'sk_popup',
					'type' 			=> 'cz_sk',
					'title' 		=> esc_html__( 'Container', 'codevz-plus' ),
					'button' 		=> esc_html__( 'Container', 'codevz-plus' ),
					'setting_args' 	=> [ 'transport' => 'postMessage' ],
					'settings' 		=> array( 'background', 'border' ),
					'dependency' 	=> array( 'element', '==', 'login' ),
				),
				array(
					'id' 			=> 'sk_con',
					'type' 			=> 'cz_sk',
					'title' 		=> esc_html__( 'Inner container', 'codevz-plus' ),
					'button' 		=> esc_html__( 'Inner container', 'codevz-plus' ),
					'setting_args' 	=> [ 'transport' => 'postMessage' ],
					'settings' 		=> array( 'background', 'border' ),
					'dependency' 	=> array( 'element', '==', 'login' ),
				),
				array(
					'id' 			=> 'sk_forms',
					'type' 			=> 'cz_sk',
					'title' 		=> esc_html__( 'Forms', 'codevz-plus' ),
					'button' 		=> esc_html__( 'Forms', 'codevz-plus' ),
					'setting_args' 	=> [ 'transport' => 'postMessage' ],
					'settings' 		=> array( 'background', 'border' ),
					'dependency' 	=> array( 'element', '==', 'login' ),
				),
				array(
					'id' 			=> 'sk_inputs',
					'type' 			=> 'cz_sk',
					'title' 		=> esc_html__( 'Inputs', 'codevz-plus' ),
					'button' 		=> esc_html__( 'Inputs', 'codevz-plus' ),
					'setting_args' 	=> [ 'transport' => 'postMessage' ],
					'settings' 		=> array( 'background', 'border' ),
					'dependency' 	=> array( 'element', '==', 'login' ),
				),
				array(
					'id' 			=> 'sk_forms_title',
					'type' 			=> 'cz_sk',
					'title' 		=> esc_html__( 'Forms title', 'codevz-plus' ),
					'button' 		=> esc_html__( 'Forms title', 'codevz-plus' ),
					'setting_args' 	=> [ 'transport' => 'postMessage' ],
					'settings' 		=> array( 'color', 'background', 'border' ),
					'dependency' 	=> array( 'element', '==', 'login' ),
				),
				array(
					'id' 			=> 'sk_buttons',
					'type' 			=> 'cz_sk',
					'title' 		=> esc_html__( 'Buttons', 'codevz-plus' ),
					'button' 		=> esc_html__( 'Buttons', 'codevz-plus' ),
					'setting_args' 	=> [ 'transport' => 'postMessage' ],
					'settings' 		=> array( 'color', 'background', 'border' ),
					'dependency' 	=> array( 'element', '==', 'login' ),
				),
				array(
					'id' 			=> 'sk_button_after_login',
					'type' 			=> 'cz_sk',
					'title' 		=> esc_html__( 'Buttons after login', 'codevz-plus' ),
					'button' 		=> esc_html__( 'Buttons after login', 'codevz-plus' ),
					'setting_args' 	=> [ 'transport' => 'postMessage' ],
					'settings' 		=> array( 'color', 'background', 'border' ),
					'dependency' 	=> array( 'element', '==', 'login' ),
				),
				array(
					'id' 			=> 'sk_links',
					'type' 			=> 'cz_sk',
					'title' 		=> esc_html__( 'Links', 'codevz-plus' ),
					'button' 		=> esc_html__( 'Links', 'codevz-plus' ),
					'setting_args' 	=> [ 'transport' => 'postMessage' ],
					'settings' 		=> array( 'color', 'background', 'border' ),
					'dependency' 	=> array( 'element', '==', 'login' ),
				),
				array(
					'id' 			=> 'sk_msg',
					'type' 			=> 'cz_sk',
					'title' 		=> esc_html__( 'Messages', 'codevz-plus' ),
					'button' 		=> esc_html__( 'Messages', 'codevz-plus' ),
					'setting_args' 	=> [ 'transport' => 'postMessage' ],
					'settings' 		=> array( 'color', 'background', 'border' ),
					'dependency' 	=> array( 'element', '==', 'login' ),
				),

				// Shop
				array(
					'id' 		=> 'shop_plugin',
					'type' 		=> 'select',
					'title' 	=> esc_html__( 'Plugin', 'codevz-plus' ),
					'options' 	=> array(
						'woo' 		=> esc_html__( 'Woocommerce', 'codevz-plus' ),
						'edd' 		=> esc_html__( 'Easy Digital Download', 'codevz-plus' ),
					),
					'dependency' => array( 'element', '==', 'shop_cart' ),
				),

				array(
					'type'    		=> 'content',
					'content' 		=> '<a href="#" onclick="wp.customize.section( \'codevz_theme_options-products\' ).focus()" class="button xtra-goto"><i class="fa fa-cog mr8"></i> ' . esc_html__( 'Products settings', 'codevz-plus' ) . '</a>',
					'dependency' 	=> array( 'element', '==', 'shop_cart' ),
				),
				array(
					'id'    		=> 'shopcart_icon',
					'type'  		=> 'icon',
					'title' 		=> esc_html__( 'Icon', 'codevz-plus' ),
					'dependency' => array( 'element', 'any', $free ? 'shop_cart' : 'shop_cart,wishlist,compare' ),
				),
				array(
					'id'    	=> 'shopcart_title',
					'type'  	=> 'text',
					'title' 	=> esc_html__( 'Title', 'codevz-plus' ),
					'dependency' => array( 'element', 'any', $free ? 'shop_cart' : 'shop_cart,wishlist,compare' ),
				),
				array(
					'id'    	=> 'shopcart_tooltip',
					'type'  	=> 'text',
					'title' 	=> esc_html__( 'Tooltip', 'codevz-plus' ),
					'dependency' => array( 'element', 'any', 'wishlist,compare' ),
				),
				array(
					'id' 			=> 'sk_shop_container',
					'type' 			=> 'cz_sk',
					'title' 		=> esc_html__( 'Container', 'codevz-plus' ),
					'button' 		=> esc_html__( 'Container', 'codevz-plus' ),
					'settings' 		=> array( 'background', 'border' ),
					'dependency' 	=> array( 'element', 'any', $free ? 'shop_cart' : 'shop_cart,wishlist,compare' )
				),
				array(
					'id' 			=> 'sk_shop_icon',
					'type' 			=> 'cz_sk',
					'title' 		=> esc_html__( 'Icon Style', 'codevz-plus' ),
					'button' 		=> esc_html__( 'Icon Style', 'codevz-plus' ),
					'settings' 		=> array( 'color', 'background', 'border' ),
					'dependency' 	=> array( 'element', 'any', $free ? 'shop_cart' : 'shop_cart,wishlist,compare' )
				),
				array(
					'id' 			=> 'sk_shop_count',
					'type' 			=> 'cz_sk',
					'title' 		=> esc_html__( 'Count Style', 'codevz-plus' ),
					'button' 		=> esc_html__( 'Count Style', 'codevz-plus' ),
					'settings' 		=> array( 'top', 'right', 'color', 'background', 'border' ),
					'dependency' 	=> array( 'element', 'any', $free ? 'shop_cart' : 'shop_cart,wishlist,compare' )
				),
				array(
					'id' 			=> 'sk_shop_content',
					'type' 			=> 'cz_sk',
					'title' 		=> esc_html__( 'Cart Style', 'codevz-plus' ),
					'button' 		=> esc_html__( 'Cart Style', 'codevz-plus' ),
					'settings' 		=> array( 'background', 'border' ),
					'dependency' 	=> array( 'element', '==', 'shop_cart' )
				),
 
				// Line
				array(
					'id' 	=> 'line_type',
					'type' 	=> 'select',
					'title' => esc_html__( 'Type', 'codevz-plus' ),
					'help'  => esc_html__( 'Background color for line is important that you can change it from line stysle button.', 'codevz-plus' ),
					'options' 	=> array(
		  				'header_line_2'   	=> esc_html__( '~ Default ~', 'codevz-plus' ),
						'header_line_1' 	=> esc_html__( 'Full height', 'codevz-plus' ),
						'header_line_3' 	=> esc_html__( 'Slash', 'codevz-plus' ),
						'header_line_4' 	=> esc_html__( 'Horizontal', 'codevz-plus' ),
					),
					'dependency' => array( 'element', '==', 'line' ),
				),
				array(
					'id' 			=> 'sk_line',
					'type' 			=> 'cz_sk',
					'title' 		=> esc_html__( 'Line Style', 'codevz-plus' ),
					'button' 		=> esc_html__( 'Line Style', 'codevz-plus' ),
					'setting_args' 	=> [ 'transport' => 'postMessage' ],
					'settings' 		=> array( 'background', 'width', 'height' ),
					'dependency' 	=> array( 'element', '==', 'line' )
				),

				// WPML
				array(
					'id' 			=> 'wpml_title',
					'type' 			=> 'select',
					'title' 		=> esc_html__( 'Title', 'codevz-plus' ),
					'options' 		=> array(
						'translated_name' 	=> esc_html__( 'Translated Name', 'codevz-plus' ),
						'language_code' 	=> esc_html__( 'Language code', 'codevz-plus' ),
						'native_name' 		=> esc_html__( 'Native name', 'codevz-plus' ),
						'translated_name' 	=> esc_html__( 'Translated name', 'codevz-plus' ),
						'no_title' 			=> esc_html__( 'No title', 'codevz-plus' ),
					),
					'dependency' 	=> array( 'element', '==', $free ? 'xxx' : 'wpml' ),
				),
				array(
					'id' 			=> 'wpml_flag',
					'type' 			=> 'switcher',
					'title' 		=> esc_html__( 'Flag', 'codevz-plus' ),
					'dependency' 	=> array( 'element', '==|!=', $free ? 'xxx' : 'wpml' ),
				),
				array(
					'id'    		=> 'wpml_current_color',
					'type'  		=> 'color_picker',
					'title' 		=> esc_html__( 'Current Language', 'codevz-plus' ),
					'dependency' 	=> array( 'element', '==', $free ? 'xxx' : 'wpml' ),
				),
				array(
					'id'    		=> 'wpml_background',
					'type'  		=> 'color_picker',
					'title' 		=> esc_html__( 'Background', 'codevz-plus' ),
					'dependency' 	=> array( 'element', '==', $free ? 'xxx' : 'wpml' ),
				),
				array(
					'id'    		=> 'wpml_color',
					'type'  		=> 'color_picker',
					'title' 		=> esc_html__( 'Inner Color', 'codevz-plus' ),
					'dependency' 	=> array( 'element', '==', $free ? 'xxx' : 'wpml' ),
				),
				array(
					'id' 			=> 'wpml_opposite',
					'type' 			=> 'switcher',
					'title' 		=> esc_html__( 'Toggle Mode', 'codevz-plus' ),
					'dependency' 	=> array( 'element', '==', $free ? 'xxx' : 'wpml' ),
				),

				// Avatar
				array(
					'id'    => 'avatar_size',
					'type'  => 'slider',
					'title' => esc_html__( 'Size', 'codevz-plus' ),
					'dependency' => array( 'element', '==', $free ? 'xxx' : 'avatar' ),
					'default' => '40px'
				),
				array(
					'id' 			=> 'sk_avatar',
					'type' 			=> 'cz_sk',
					'title' 		=> esc_html__( 'Avatar', 'codevz-plus' ),
					'button' 		=> esc_html__( 'Avatar', 'codevz-plus' ),
					'setting_args' 	=> [ 'transport' => 'postMessage' ],
					'settings' 		=> array( 'background', 'border' ),
					'dependency' 	=> array( 'element', '==', $free ? 'xxx' : 'avatar' )
				),
				array(
					'id'    => 'avatar_link',
					'type'  => 'text',
					'title' => esc_html__( 'Link', 'codevz-plus' ),
					'dependency' => array( 'element', '==', $free ? 'xxx' : 'avatar' ),
				),

				// Others
				array(
					'id' 		=> 'vertical',
					'type' 		=> $free ? 'content' : 'switcher',
					'content' 	=> Codevz_Plus::pro_badge(),
					'title'		=> esc_html__( 'Vertical', 'codevz-plus' ),
					'dependency' => $is_fixed_side ? array( 'element', 'any', 'social,icon' ) : array( 'element', '==', 'xxx' )
				),
				array(
					'id' 		=> 'elm_visibility',
					'type' 		=> $free ? 'content' : 'select',
					'content' 	=> Codevz_Plus::pro_badge(),
					'title' 	=> esc_html__( 'Visibility', 'codevz-plus' ),
					'help'  	=> esc_html__( 'You can show or hide this element for logged in or non-logged in users', 'codevz-plus' ),
					'options' 	=> array(
						'' 			=> esc_html__( '~ Default ~', 'codevz-plus' ),
						'1' 		=> esc_html__( 'Show for logged in users', 'codevz-plus' ),
						'2' 		=> esc_html__( 'Show for non-logged in users', 'codevz-plus' ),
					),
					'dependency' => array( 'element', '!=', '' )
				),
				array(
					'id' 		=> 'elm_on_sticky',
					'type' 		=> $free ? 'content' : 'select',
					'content' 	=> Codevz_Plus::pro_badge(),
					'title' 	=> esc_html__( 'On Sticky', 'codevz-plus' ),
					'help' 		=> esc_html__( 'You can enable sticky mode from Theme Options > Header > Sticky Header', 'codevz-plus' ),
					'options' 	=> array(
						'' 					=> esc_html__( '~ Default ~', 'codevz-plus' ),
						'show_on_sticky' 	=> esc_html__( 'Show on sticky', 'codevz-plus' ),
						'hide_on_sticky' 	=> esc_html__( 'Hide on sticky', 'codevz-plus' ),
					),
					'dependency' => $is_1_2_3 ? array( 'element', '!=', '' ) : array( 'element', '==', 'xxx' )
				),
				array(
					'id' 		=> 'elm_center',
					'type' 		=> 'switcher',
					'title'		=> esc_html__( 'Center Mode', 'codevz-plus' ),
					'dependency' => $is_fixed_side ? array( 'element', '!=', '' ) : array( 'element', '==', 'xxx' )
				),
				array(
					'id' 		=> 'hide_on_mobile',
					'type' 		=> 'switcher',
					'title'		=> esc_html__( 'Hide on Mobile', 'codevz-plus' ),
					'dependency' => $is_footer ? array( 'element', '!=', '' ) : array( 'element', '==', 'xxx' )
				),
				array(
					'id' 		=> 'hide_on_tablet',
					'type' 		=> 'switcher',
					'title'		=> esc_html__( 'Hide on Tablet', 'codevz-plus' ),
					'dependency' => $is_footer ? array( 'element', '!=', '' ) : array( 'element', '==', 'xxx' )
				),

				// Element margin
				array(
					'id'        => 'margin',
					'type'      => 'codevz_sizes',
					'title'     => esc_html__( 'Element margin', 'codevz-plus' ),
					'options'	=> array( 'unit' => 'px', 'step' => 1, 'min' => -20, 'max' => 100 ),
					'default'	=> array(
						'top' 		=> '20px',
						'right' 	=> '',
						'bottom' 	=> '20px',
						'left' 		=> '',
					),
					'help'		 => self::help( 'margin' ),
					'dependency' => array( 'element', '!=', '' )
				),

			)
		);
	}

	/**
	 *
	 * Header row builder options
	 * 
	 * @return array
	 *
	 */
	public static function row_options( $id, $positions = array('left', 'center', 'right') ) {

		$free = Codevz_Plus::$is_free;

		$elm = '.' . $id;
		$out = array();

		$menu_unique_id = '#menu_' . $id;

		// If is sticky so show dropdown option and create dependency
		if ( $id === 'header_5' ) {
			$elm = '.onSticky';
			$dependency = array( 'sticky_header', '==', '5' );
			
			$out[] = array(
				'id' 		=> 'sticky_header',
				'type' 		=> 'select',
				'title' 	=> esc_html__( 'Type', 'codevz-plus' ),
				'help' 		=> esc_html__( 'Keeping the header of your website in the same place on the screen while the user scrolls down the page.', 'codevz-plus' ),
				'options' 	=> array(
					''			=> esc_html__( '~ Disable ~', 'codevz-plus' ),
					'1'			=> esc_html__( 'Sticky top bar', 'codevz-plus' ),
					'2'			=> esc_html__( 'Sticky header', 'codevz-plus' ),
					'3'     	=> esc_html__( 'Sticky bottom bar', 'codevz-plus' ),
					'123'	  	=> esc_html__( 'All Headers Sticky', 'codevz-plus' ),
					'12'    	=> esc_html__( 'Header top bar + Header', 'codevz-plus' ) . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ),
					'23'    	=> esc_html__( 'Header + Header bottom bar', 'codevz-plus' ) . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ),
					'13'    	=> esc_html__( 'Header top bar + Header bottom bar', 'codevz-plus' ) . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ),
					$free ? 'x' : '5'			=> esc_html__( 'Create custom sticky', 'codevz-plus' ) . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ),
				)
			);

			$out[] = array(
				'id' 		=> 'smart_sticky',
				'type' 		=> $free ? 'content' : 'switcher',
				'content' 	=> Codevz_Plus::pro_badge(),
				'title' 	=> esc_html__( 'Smart Sticky', 'codevz-plus' ),
				'help' 		=> esc_html__( 'It will Hide the header when user scroll down but show the header when user scroll up.', 'codevz-plus' ),
				'dependency' => $free ? [] : array( 'sticky_header', 'any', '1,2,3,5' )
			);

			$out[] = array(
				'id' 		=> 'mobile_sticky',
				'type' 		=> $free ? 'content' : 'select',
				'content' 	=> Codevz_Plus::pro_badge(),
				'title' 	=> esc_html__( 'Mobile Sticky', 'codevz-plus' ),
				'help' 		=> esc_html__( 'Keeping the header of your website in the same place on the screen while the user scrolls down the page.', 'codevz-plus' ),
				'options' 	=> array(
					''								=> esc_html__( '~ Select ~', 'codevz-plus' ),
					'header_is_sticky'				=> esc_html__( 'Sticky', 'codevz-plus' ),
					'header_is_sticky smart_sticky'	=> esc_html__( 'Smart Sticky', 'codevz-plus' ),
				)
			);

		} else {
			$dependency = array();
		}

		// Fixed position before elements
		if ( $id === 'fixed_side_1' ) {
			$out[] = array(
				'id' 			=> 'fixed_side',
				'type' 			=> 'codevz_image_select',
				'title' 		=> esc_html__( 'Fixed Side', 'codevz-plus' ),
				'help' 			=> esc_html__( 'Visible area and it’s elements all the time and while scrolling the page.', 'codevz-plus' ),
				'options' 		=> [
					'' 				=> [ esc_html__( '~ Disable ~', 'codevz-plus' )	, Codevz_Plus::$url . 'assets/img/off.png' ],
					'left' 			=> [ esc_html__( 'Left', 'codevz-plus' ) 		, ( Codevz_Plus::$is_rtl ? Codevz_Plus::$url . 'assets/img/sidebar-3.png' : Codevz_Plus::$url . 'assets/img/sidebar-5.png' ) ],
					'right' 		=> [ esc_html__( 'Right', 'codevz-plus' ) 		, ( Codevz_Plus::$is_rtl ? Codevz_Plus::$url . 'assets/img/sidebar-5.png' : Codevz_Plus::$url . 'assets/img/sidebar-3.png' ) ],
				],
				'default' 		=> '',
				'attributes' => array( 'data-depend-id' => 'fixed_side' )
			);
			$dependency = array( 'fixed_side', 'any', 'left,right' );
		}

		// Tablet/Mobile header
		if ( $id === 'header_4' ) {

			$out[] = array(
			  'id'            => 'b_mobile_header',
			  'type'          => $free ? 'content' : 'select',
			  'content' 	  => Codevz_Plus::pro_badge(),
			  'title'         => esc_html__( 'Before header', 'codevz-plus' ),
			  'help' 		  => esc_html__( 'Assign the custom template section before the mobile header.', 'codevz-plus' ),
			  'options'       => Codevz_Plus::$array_pages,
			  'edit_link' 	  => true
			);

			$out[] = array(
			  'id'            => 'a_mobile_header',
			  'type'          => $free ? 'content' : 'select',
			  'content' 	  => Codevz_Plus::pro_badge(),
			  'title'         => esc_html__( 'After header', 'codevz-plus' ),
			  'help' 		  => esc_html__( 'Assign the custom template section after the mobile header.', 'codevz-plus' ),
			  'options'       => Codevz_Plus::$array_pages,
				'edit_link'   => true
			);

			$out[] = array(
				'type'    		=> 'notice',
				'class'   		=> 'info',
				'content' 		=> '<i class="fas fa-mobile mr8"></i>' . esc_html__( 'Mobile elements', 'codevz-plus' ),
				'dependency' 	=> $dependency
			);

		}

		// Left center right elements and style
		foreach( $positions as $num => $pos ) {
			$num++;
			$out[] = self::elements( $id . '_' . $pos, '', $dependency, $pos );
		}

		// If its fixed header so show dropdown option
		$out[] = array(
			'type'    		=> 'notice',
			'class'   		=> 'info',
			'content' 		=> '<i class="fas fa-brush mr8"></i>' . esc_html__( 'Row Styling', 'codevz-plus' ),
			'dependency' 	=> $dependency
		);

		if ( $id === 'fixed_side_1' ) {

			$out[] = array(
				'id' 			=> '_css_fixed_side_style',
				'type' 			=> 'cz_sk',
				'title' 		=> esc_html__( 'Container', 'codevz-plus' ),
				'button' 		=> esc_html__( 'Container', 'codevz-plus' ),
				'setting_args' 	=> [ 'transport' => 'postMessage' ],
				'settings' 		=> array( 'background', 'width', 'border' ),
				'selector' 		=> '.fixed_side, .fixed_side .theiaStickySidebar',
				'dependency' 	=> array( 'fixed_side', 'any', 'left,right' )
			);

		} else {

			$f_dependency = ( $id === 'header_5' ) ? array( 'sticky_header', '!=', '' ) : array();

			if ( $id === 'header_5' ) {

				$out[] = array(
					'type' 			=> 'notice',
					'class' 		=> 'info',
					'content' 		=> '<i class="fas fa-brush mr8"></i>' . esc_html__( 'Styling', 'codevz-plus' ),
					'dependency' 	=> $f_dependency
				);

			}

			$out[] = array(
				'id' 			=> '_css_container_' . $id,
				'type' 			=> 'cz_sk',
				'title' 		=> esc_html__( 'Container', 'codevz-plus' ),
				'button' 		=> esc_html__( 'Container', 'codevz-plus' ),
				'setting_args' 	=> [ 'transport' => 'postMessage' ],
				'settings' 		=> array( 'background', 'border' ),
				'selector' 		=> $elm,
				'dependency' 	=> $f_dependency
			);
			$out[] = array(
				'id' 			=> '_css_row_' . $id,
				'type' 			=> 'cz_sk',
				'title' 		=> esc_html__( 'Row inner', 'codevz-plus' ),
				'button' 		=> esc_html__( 'Row inner', 'codevz-plus' ),
				'setting_args' 	=> [ 'transport' => 'postMessage' ],
				'settings' 		=> array( 'background', '_class_shape', 'width', 'border' ),
				'selector' 		=> $elm . ' .row',
				'dependency' 	=> $f_dependency
			);

			if ( $id === 'header_5' ) {

				$out[] = array(
					'id' 			=> '_css_container_mob_' . $id,
					'type' 			=> 'cz_sk',
					'title' 		=> esc_html__( 'Mobile container', 'codevz-plus' ),
					'button' 		=> esc_html__( 'Mobile container', 'codevz-plus' ),
					'setting_args' 	=> [ 'transport' => 'postMessage' ],
					'settings' 		=> array( 'background', 'border' ),
					'selector' 		=> $elm . '.header_4',
					'dependency' 	=> $f_dependency
				);
				$out[] = array(
					'id' 			=> '_css_row_mob_' . $id,
					'type' 			=> 'cz_sk',
					'title' 		=> esc_html__( 'Mobile row inner', 'codevz-plus' ),
					'button' 		=> esc_html__( 'Mobile row inner', 'codevz-plus' ),
					'setting_args' 	=> [ 'transport' => 'postMessage' ],
					'settings' 		=> array( 'background', 'width', 'border' ),
					'selector' 		=> $elm . '.header_4 .row',
					'dependency' 	=> $f_dependency
				);
				$out[] = array(
					'id' 			=> '_css_sticky_menus_' . $id,
					'hover_id' 		=> '_css_sticky_menus_' . $id . '_hover',
					'type' 			=> 'cz_sk',
					'title' 		=> esc_html__( 'Menus', 'codevz-plus' ),
					'button' 		=> esc_html__( 'Menus', 'codevz-plus' ),
					'setting_args' 	=> [ 'transport' => 'postMessage' ],
					'settings' 		=> array( 'color', 'background', 'font-size', 'border' ),
					'selector' 		=> '#layout .onSticky .sf-menu > .cz > a',
					'dependency' 	=> $f_dependency
				);
				$out[] = array(
					'id' 			=> '_css_sticky_menus_' . $id . '_hover',
					'type' 			=> 'cz_sk_hidden',
					'button' 		=> '',
					'setting_args' 	=> [ 'transport' => 'postMessage' ],
					'selector' 		=> '#layout .onSticky .sf-menu > .cz > a:hover'
				);

			}

			if ( $id === 'footer_1' || $id === 'footer_2' ) {

				$out[] = array(
					'id' 			=> '_css_container_' . $id . '_tablet',
					'type' 			=> 'cz_sk_hidden',
					'setting_args' 	=> [ 'transport' => 'postMessage' ],
					'selector' 		=> $elm
				);
				$out[] = array(
					'id' 			=> '_css_container_' . $id . '_mobile',
					'type' 			=> 'cz_sk_hidden',
					'setting_args' 	=> [ 'transport' => 'postMessage' ],
					'selector' 		=> $elm
				);

				$out[] = array(
					'id' 			=> '_css_row_' . $id . '_tablet',
					'type' 			=> 'cz_sk_hidden',
					'setting_args' 	=> [ 'transport' => 'postMessage' ],
					'selector' 		=> $elm . ' .row'
				);
				$out[] = array(
					'id' 			=> '_css_row_' . $id . '_mobile',
					'type' 			=> 'cz_sk_hidden',
					'setting_args' 	=> [ 'transport' => 'postMessage' ],
					'selector' 		=> $elm . ' .row'
				);

			}

		}

		// Left center right elements and style
		foreach ( $positions as $num => $pos ) {

			$num++;

			$out[] = array(
				'id' 			=> '_css_' . $id . '_' . $pos,
				'type' 			=> 'cz_sk',
				'title' 		=> ucwords( isset( self::$trasnlation[ $pos ] ) ? self::$trasnlation[ $pos ] : '' ),
				'button' 		=> ucwords( isset( self::$trasnlation[ $pos ] ) ? self::$trasnlation[ $pos ] : '' ),
				'setting_args' 	=> [ 'transport' => 'postMessage' ],
				'settings' 		=> array( 'background', '_class_shape', 'border' ),
				'selector' 		=> $elm . ' .elms_' . $pos,
				'dependency' 	=> $dependency
			);

			if ( $id === 'footer_1' || $id === 'footer_2' ) {

				$out[] = array(
					'id' 			=> '_css_' . $id . '_' . $pos . '_tablet',
					'type' 			=> 'cz_sk_hidden',
					'setting_args' 	=> [ 'transport' => 'postMessage' ],
					'selector' 		=> $elm . ' .elms_' . $pos
				);
				$out[] = array(
					'id' 			=> '_css_' . $id . '_' . $pos . '_mobile',
					'type' 			=> 'cz_sk_hidden',
					'setting_args' 	=> [ 'transport' => 'postMessage' ],
					'selector' 		=> $elm . ' .elms_' . $pos
				);

			}

		}

		// Menus style for each row
		$out[] = array(
			'type' 			=> 'notice',
			'class' 		=> 'info xtra-notice',
			'content' 		=> '<i class="fas fa-brush mr8"></i>' . esc_html__( 'Menu Styling', 'codevz-plus' ),
			'dependency' 	=> $dependency
		);
		$out[] = array(
			'id' 			=> '_css_menu_container_' . $id,
			'type' 			=> 'cz_sk',
			'title' 		=> esc_html__( 'Container', 'codevz-plus' ),
			'button' 		=> esc_html__( 'Container', 'codevz-plus' ),
			'setting_args' 	=> [ 'transport' => 'postMessage' ],
			'settings' 		=> array( 'background', 'border' ),
			'selector' 		=> $menu_unique_id,
			'dependency' 	=> $dependency
		);
		$out[] = array(
			'id' 			=> '_css_menu_li_' . $id,
			'type' 			=> 'cz_sk',
			'title' 		=> esc_html__( 'Menus li', 'codevz-plus' ),
			'button' 		=> esc_html__( 'Menus li', 'codevz-plus' ),
			'setting_args' 	=> [ 'transport' => 'postMessage' ],
			'settings' 		=> array( 'float', 'text-align', 'border' ),
			'selector' 		=> $menu_unique_id . ' > .cz',
			'dependency' 	=> $dependency
		);
		$out[] = array(
			'id' 			=> '_css_menu_a_' . $id,
			'hover_id' 		=> '_css_menu_a_hover_' . $id,
			'type' 			=> 'cz_sk',
			'title' 		=> esc_html__( 'Menus', 'codevz-plus' ),
			'button' 		=> esc_html__( 'Menus', 'codevz-plus' ),
			'setting_args' 	=> [ 'transport' => 'postMessage' ],
			'settings' 		=> array( 'color', 'background', 'font-family', 'font-size', 'border' ),
			'selector' 		=> $menu_unique_id . ' > .cz > a',
			'dependency' 	=> $dependency
		);
		$out[] = array(
			'id' 			=> '_css_menu_a_hover_' . $id,
			'type' 			=> 'cz_sk_hidden',
			'button' 		=> '',
			'setting_args' 	=> [ 'transport' => 'postMessage' ],
			'selector' 		=> $menu_unique_id . ' > .cz > a:hover,' . $menu_unique_id . ' > .cz:hover > a,' . $menu_unique_id . ' > .cz.current_menu > a,' . $menu_unique_id . ' > .current-menu-parent > a',
			'dependency' 	=> $dependency
		);

		$out[] = array(
			'id' 			=> '_css_menu_a_hover_before_' . $id,
			'type' 			=> 'cz_sk',
			'title' 		=> esc_html__( 'Shape', 'codevz-plus' ),
			'button' 		=> esc_html__( 'Shape', 'codevz-plus' ),
			'setting_args' 	=> [ 'transport' => 'postMessage' ],
			'settings' 		=> array( '_class_menu_fx', 'background', 'height', 'width', 'left', 'bottom', 'border' ),
			'selector' 		=> $menu_unique_id . ' > .cz > a:before',
			'dependency' 	=> $dependency
		);
		$out[] = array(
			'id' 			=> '_css_menu_subtitle_' . $id,
			'hover_id' 		=> '_css_menu_subtitle_' . $id . '_hover',
			'type' 			=> 'cz_sk',
			'title' 		=> esc_html__( 'Subtitle', 'codevz-plus' ),
			'button' 		=> esc_html__( 'Subtitle', 'codevz-plus' ),
			'setting_args' 	=> [ 'transport' => 'postMessage' ],
			'settings' 		=> array( 'color', 'background', 'font-size' ),
			'selector' 		=> $menu_unique_id . ' > .cz > a > .cz_menu_subtitle',
			'dependency' 	=> $dependency
		);
		$out[] = array(
			'id' 			=> '_css_menu_subtitle_' . $id . '_hover',
			'type' 			=> 'cz_sk_hidden',
			'button' 		=> '',
			'setting_args' 	=> [ 'transport' => 'postMessage' ],
			'selector' 		=> $menu_unique_id . ' > .cz > a:hover > .cz_menu_subtitle,' . $menu_unique_id . ' > .cz:hover > a > .cz_menu_subtitle,' . $menu_unique_id . ' > .cz.current_menu > a > .cz_menu_subtitle,' . $menu_unique_id . ' > .current-menu-parent > a > .cz_menu_subtitle',
			'dependency' 	=> $dependency
		);

		$out[] = array(
			'id' 			=> '_css_menu_icon_' . $id,
			'hover_id' 		=> '_css_menu_icon_' . $id . '_hover',
			'type' 			=> 'cz_sk',
			'title' 		=> esc_html__( 'Icons', 'codevz-plus' ),
			'button' 		=> esc_html__( 'Icons', 'codevz-plus' ),
			'setting_args' 	=> [ 'transport' => 'postMessage' ],
			'settings' 		=> array( 'color', 'background', 'font-size', 'border', 'position', 'top', 'left', 'opacity' ),
			'selector' 		=> $menu_unique_id . ' > .cz > a span i',
			'dependency' 	=> $dependency
		);
		$out[] = array(
			'id' 			=> '_css_menu_icon_' . $id . '_hover',
			'type' 			=> 'cz_sk_hidden',
			'button' 		=> '',
			'setting_args' 	=> [ 'transport' => 'postMessage' ],
			'selector' 		=> $menu_unique_id . ' > .cz > a:hover span i,' . $menu_unique_id . ' > .cz:hover > a span i,' . $menu_unique_id . ' > .cz.current_menu > a span i,' . $menu_unique_id . ' > .current-menu-parent > a span i',
			'dependency' 	=> $dependency
		);

		$out[] = array(
			'id' 			=> '_css_menu_ul_' . $id,
			'type' 			=> 'cz_sk',
			'title' 		=> esc_html__( 'Dropdown', 'codevz-plus' ),
			'button' 		=> esc_html__( 'Dropdown', 'codevz-plus' ),
			'setting_args' 	=> [ 'transport' => 'postMessage' ],
			'settings' 		=> array( '_class_submenu_fx', 'width', 'background', 'border' ),
			'selector' 		=> $menu_unique_id . ' .cz .sub-menu:not(.cz_megamenu_inner_ul),' . $menu_unique_id . ' .cz_megamenu_inner_ul .cz_megamenu_inner_ul',
			'dependency' 	=> $dependency
		);
		$out[] = array(
			'id' 			=> '_css_menu_ul_a_' . $id,
			'hover_id' 		=> '_css_menu_ul_a_hover_' . $id,
			'type' 			=> 'cz_sk',
			'title' 		=> esc_html__( 'Inner Menus', 'codevz-plus' ),
			'button' 		=> esc_html__( 'Inner Menus', 'codevz-plus' ),
			'setting_args' 	=> [ 'transport' => 'postMessage' ],
			'settings' 		=> array( 'color', 'background', 'font-family', 'text-align', 'font-size', 'border' ),
			'selector' 		=> $menu_unique_id . ' .cz .cz a',
			'dependency' 	=> $dependency
		);

		$out[] = array(
			'id' 			=> 'xtra_control_badge_' . $id,
			'type' 			=> 'content',
			'content' 		=> Codevz_Plus::pro_badge(),
			'dependency' 	=> $free ? $dependency : [ 'x', '==', 'x' ]
		);

		$out[] = array(
			'id' 			=> '_css_menu_indicator_a_' . $id,
			'type' 			=> $free ? 'cz_sk_free' : 'cz_sk',
			'title' 		=> esc_html__( 'Indicator', 'codevz-plus' ),
			'button' 		=> esc_html__( 'Indicator', 'codevz-plus' ),
			'setting_args' 	=> [ 'transport' => 'postMessage' ],
			'settings' 		=> array( 'color', '_class_indicator' ),
			'selector' 		=> $menu_unique_id . ' > .cz > a .cz_indicator',
			'dependency' 	=> $dependency
		);
		$out[] = array(
			'id' 			=> '_css_menus_separator_' . $id,
			'type' 			=> $free ? 'cz_sk_free' : 'cz_sk',
			'title' 		=> esc_html__( 'Delimiter', 'codevz-plus' ),
			'button' 		=> esc_html__( 'Delimiter', 'codevz-plus' ),
			'setting_args' 	=> [ 'transport' => 'postMessage' ],
			'settings' 		=> array( 'content', 'rotate', 'color' ),
			'selector' 		=> $menu_unique_id . ' > .cz:after',
			'dependency' 	=> $dependency
		);
		$out[] = array(
			'id' 			=> '_css_menu_ul_a_hover_' . $id,
			'type' 			=> 'cz_sk_hidden',
			'button' 		=> '',
			'setting_args' 	=> [ 'transport' => 'postMessage' ],
			'selector' 		=> $menu_unique_id . ' .cz .cz a:hover,' . $menu_unique_id . ' .cz .cz:hover > a,' . $menu_unique_id . ' .cz .cz.current_menu > a,' . $menu_unique_id . ' .cz .current_menu > .current_menu',
			'dependency' 	=> $dependency
		);

		$out[] = array(
			'id' 			=> '_css_menu_ul_indicator_a_' . $id,
			'type' 			=> $free ? 'cz_sk_free' : 'cz_sk',
			'title' 		=> esc_html__( 'Inner Idicator', 'codevz-plus' ),
			'button' 		=> esc_html__( 'Inner Idicator', 'codevz-plus' ),
			'setting_args' 	=> [ 'transport' => 'postMessage' ],
			'settings' 		=> array( 'color', '_class_indicator' ),
			'selector' 		=> $menu_unique_id . ' .cz .cz a .cz_indicator',
			'dependency' 	=> $dependency
		);
		$out[] = array(
			'id' 			=> '_css_menu_ul_ul_' . $id,
			'type' 			=> $free ? 'cz_sk_free' : 'cz_sk',
			'title' 		=> esc_html__( '3rd Level', 'codevz-plus' ),
			'button' 		=> esc_html__( '3rd Level', 'codevz-plus' ),
			'setting_args' 	=> [ 'transport' => 'postMessage' ],
			'settings' 		=> array( 'margin' ),
			'selector' 		=> $menu_unique_id . ' .sub-menu .sub-menu:not(.cz_megamenu_inner_ul)',
			'dependency' 	=> $dependency
		);

		$out[] = array(
			'id' 			=> '_css_menu_inner_megamenu_' . $id,
			'type' 			=> $free ? 'cz_sk_free' : 'cz_sk',
			'title' 		=> esc_html__( 'Megamenu', 'codevz-plus' ),
			'button' 		=> esc_html__( 'Megamenu', 'codevz-plus' ),
			'setting_args' 	=> [ 'transport' => 'postMessage' ],
			'settings' 		=> array( 'background', 'border' ),
			'selector' 		=> $menu_unique_id . ' .cz_parent_megamenu > [class^="cz_megamenu_"] > .cz, .cz_parent_megamenu > [class*=" cz_megamenu_"] > .cz',
			'dependency' 	=> $dependency
		);
		$out[] = array(
			'id' 			=> '_css_menu_ul_a_h6_' . $id,
			'type' 			=> $free ? 'cz_sk_free' : 'cz_sk',
			'title' 		=> esc_html__( 'Title', 'codevz-plus' ),
			'button' 		=> esc_html__( 'Title', 'codevz-plus' ),
			'setting_args' 	=> [ 'transport' => 'postMessage' ],
			'settings' 		=> array( 'color', 'background', 'font-family', 'text-align', 'font-size', 'border' ),
			'selector' 		=> $menu_unique_id . ' .cz .cz h6',
			'dependency' 	=> $dependency
		);

		// Mobile additional

		if ( $id === 'header_4' ) {
			$out[] = array(
				'type' 			=> 'notice',
				'class' 		=> 'info xtra-notice',
				'content' 		=> '<i class="fas fa-sliders mr8"></i>' . esc_html__( 'Above Menu', 'codevz-plus' )
			);
			$out[] = array(
				'id' 			=> 'mobile_menu_search',
				'type' 			=> $free ? 'content' : 'switcher',
				'content' 		=> Codevz_Plus::pro_badge(),
				'title' 		=> esc_html__( 'Search form', 'codevz-plus' ),
			);
			$out[] = array(
				'id' 			=> 'mobile_menu_account',
				'type' 			=> $free ? 'content' : 'switcher',
				'content' 		=> Codevz_Plus::pro_badge(),
				'title' 		=> esc_html__( 'My account', 'codevz-plus' ),
			);
			$out[] = array(
				'id' 			=> '_css_mm_head_container',
				'type' 			=> 'cz_sk',
				'title' 		=> esc_html__( 'Container', 'codevz-plus' ),
				'button' 		=> esc_html__( 'Container', 'codevz-plus' ),
				'setting_args' 	=> [ 'transport' => 'postMessage' ],
				'settings' 		=> array( 'background' ),
				'selector' 		=> 'li.xtra-mobile-menu-head'
			);
			$out[] = array(
				'id' 			=> '_css_mm_head_account_icon',
				'type' 			=> 'cz_sk',
				'title' 		=> esc_html__( 'Account icon/image', 'codevz-plus' ),
				'button' 		=> esc_html__( 'Account icon/image', 'codevz-plus' ),
				'setting_args' 	=> [ 'transport' => 'postMessage' ],
				'settings' 		=> array( 'color', 'background' ),
				'selector' 		=> 'li.xtra-mobile-menu-head > a i, i.xtra-mobile-menu-head > a img'
			);
			$out[] = array(
				'id' 			=> '_css_mm_head_account_search_input',
				'type' 			=> 'cz_sk',
				'title' 		=> esc_html__( 'Search input', 'codevz-plus' ),
				'button' 		=> esc_html__( 'Search input', 'codevz-plus' ),
				'setting_args' 	=> [ 'transport' => 'postMessage' ],
				'settings' 		=> array( 'color', 'background' ),
				'selector' 		=> 'li.xtra-mobile-menu-head form input'
			);
			$out[] = array(
				'id' 			=> '_css_mm_head_account_search_icon',
				'type' 			=> 'cz_sk',
				'title' 		=> esc_html__( 'Search icon', 'codevz-plus' ),
				'button' 		=> esc_html__( 'Search icon', 'codevz-plus' ),
				'setting_args' 	=> [ 'transport' => 'postMessage' ],
				'settings' 		=> array( 'color', 'background' ),
				'selector' 		=> 'li.xtra-mobile-menu-head form i'
			);


			$out[] = array(
				'type' 			=> 'notice',
				'class' 		=> 'info xtra-notice',
				'content' 		=> '<i class="fas fa-sliders mr8"></i>' . esc_html__( 'Below Menu', 'codevz-plus' )
			);
			$out[] = array(
				'id' 			=> 'mobile_menu_social',
				'type' 			=> $free ? 'content' : 'switcher',
				'content' 		=> Codevz_Plus::pro_badge(),
				'title' 		=> esc_html__( 'Social Icons', 'codevz-plus' ),
				'help' 			=> esc_html__( 'Go to Theme Options > Header > Social Icons for add or remove social icons', 'codevz-plus' )
			);
			$out[] = array(
				'id' 			=> 'mobile_menu_social_color_mode',
				'type' 			=> $free ? 'content' : 'select',
				'content' 		=> Codevz_Plus::pro_badge(),
				'title' 		=> esc_html__( 'Color Mode', 'codevz-plus' ),
				'options' 		=> array(
					'cz_social_no_colored' 		=> esc_html__( '~ Disable ~', 'codevz-plus' ),
					'cz_social_colored' 		=> esc_html__( 'Brand Colors', 'codevz-plus' ),
					'cz_social_colored_hover' 	=> esc_html__( 'Brand Colors on Hover', 'codevz-plus' ),
					'cz_social_colored_bg' 		=> esc_html__( 'Brand Background', 'codevz-plus' ),
					'cz_social_colored_bg_hover' => esc_html__( 'Brand Background on Hover', 'codevz-plus' ),
				),
				'default_option' => esc_html__( '~ Default ~', 'codevz-plus' ),
			);
			$out[] = array(
				'id' 			=> 'mobile_menu_text',
				'type' 			=> 'textarea',
				'setting_args' 	=> [ 'transport' => 'postMessage' ],
				'title' 		=> esc_html__( 'Custom Text', 'codevz-plus' ),
				'help'  		=> esc_html__( 'Instead current year you can use [codevz_year]', 'codevz-plus' ),
			);
			$out[] = array(
				'id' 			=> '_css_mm_additional',
				'type' 			=> 'cz_sk',
				'title' 		=> esc_html__( 'Container', 'codevz-plus' ),
				'button' 		=> esc_html__( 'Container', 'codevz-plus' ),
				'setting_args' 	=> [ 'transport' => 'postMessage' ],
				'settings' 		=> array( 'text-align' ),
				'selector' 		=> 'li.xtra-mobile-menu-additional'
			);
			$out[] = array(
				'id' 			=> '_css_mm_text',
				'type' 			=> 'cz_sk',
				'title' 		=> esc_html__( 'Text style', 'codevz-plus' ),
				'button' 		=> esc_html__( 'Text style', 'codevz-plus' ),
				'setting_args' 	=> [ 'transport' => 'postMessage' ],
				'settings' 		=> array( 'color', 'background', 'border' ),
				'selector' 		=> '.xtra-mobile-menu-text',
			);

			$out[] = array(
				'id' 			=> 'xtra_control_badge_mms',
				'type' 			=> 'content',
				'content' 		=> Codevz_Plus::pro_badge(),
				'dependency' 	=> $free ? [] : [ 'x', '==', 'x' ]
			);

			$out[] = array(
				'id' 			=> '_css_mms_container',
				'type' 			=> $free ? 'cz_sk_free' : 'cz_sk',
				'title' 		=> esc_html__( 'Social', 'codevz-plus' ),
				'button' 		=> esc_html__( 'Social', 'codevz-plus' ),
				'setting_args' 	=> [ 'transport' => 'postMessage' ],
				'settings' 		=> array( 'background', 'border' ),
				'selector' 		=> 'li.xtra-mobile-menu-additional .cz_social',
			);

			$out[] = array(
				'id' 			=> '_css_mms_icons',
				'hover_id' 		=> '_css_mms_icons_hover',
				'type' 			=> $free ? 'cz_sk_free' : 'cz_sk',
				'title' 		=> esc_html__( 'Icons', 'codevz-plus' ),
				'button' 		=> esc_html__( 'Icons', 'codevz-plus' ),
				'setting_args' 	=> [ 'transport' => 'postMessage' ],
				'settings' 		=> array( 'color', 'background', 'border' ),
				'selector' 		=> 'li.xtra-mobile-menu-additional .cz_social a',
			);

			$out[] = array(
				'id' 			=> '_css_mms_icons_hover',
				'type' 			=> 'cz_sk_hidden',
				'button' 		=> '',
				'setting_args' 	=> [ 'transport' => 'postMessage' ],
				'settings' 		=> array( 'color', 'background', 'border' ),
				'selector' 		=> 'li.xtra-mobile-menu-additional .cz_social a:hover',
			);
		}

		return $out;
	}

	/**
	 *
	 * Generate json of options for customize footer and live changes
	 * 
	 * @return string
	 *
	 */
	public static function codevz_wp_footer_options_json() {
		$out = [];

		foreach ( Codevz_Plus::option() as $id => $val ) {
			if ( ! empty( $val ) && Codevz_Plus::contains( $id, '_css_' ) ) {
				$out[ $id ] = $val;
			}
		}

		wp_add_inline_script( 'codevz-customize', 'var codevz_selectors = ' . wp_json_encode( (array) self::get_selector( 'all' ) ) . ', codevz_customize_json = ' . wp_json_encode( $out ) . ';', 'before' );
	}

	/**
	 *
	 * Get sidebars
	 * 
	 * @return string
	 *
	 */
	public static function sidebars() {

		$options = array( '' => esc_html__( '~ Default ~', 'codevz-plus' ) );
		$sidebars = (array) get_option( 'sidebars_widgets' );

		foreach ( $sidebars as $i => $w ) {
			if ( isset( $i ) && ( $i !== 'array_version' && $i !== 'jr-insta-shortcodes' && $i !== 'wp_inactive_widgets' ) ) {
				$options[ $i ] = ucwords( $i );
			}
		}

		return $options;

	}

	/**
	 *
	 * Get list of Revolution Sliders
	 * 
	 * @return string
	 *
	 */
	public static function revSlider( $out = array() ) {

		// Cache.
		if ( self::$revslider ) {

			return self::$revslider;

		}

		// Find all sliders.
		if ( class_exists( 'RevSlider' ) ) {

			$db = Codevz_Plus::database();

			$sliders = (object) $db->get_results( $db->prepare( "SELECT id, title, alias FROM " . $db->prefix . "revslider_sliders WHERE `type` != 'folder' AND `type` != 'template' ORDER BY %s %s", [ 'id', 'ASC' ] ) );
			
			foreach (  $sliders as $slider ) {
				if ( isset( $slider->alias ) && isset( $slider->title ) ) {
					$out[ $slider->alias ] = $slider->title;
				}
			}

			if ( empty( $out ) ) {
				$out = array( esc_html__( 'Could not be found. Please create a new one from the Revolution Slider menu', 'codevz-plus' ) );
			}

		} else {

			$out = array( esc_html__( "Sorry, the Revolution Slider hasn't been installed or activated", 'codevz-plus' ) );

		}

		// Cache.
		self::$revslider = $out;

		return $out;

	}

}

Codevz_Options::instance();