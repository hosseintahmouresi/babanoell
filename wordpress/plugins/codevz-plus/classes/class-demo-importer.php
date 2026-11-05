<?php if ( ! defined( 'ABSPATH' ) ) {exit;} // Exit if accessed directly.

/**
 * Demo importer
 */

class Codevz_Demo_Importer {

	use Codevz_Plus_Instance;

	public function __construct() {

		add_action( 'wp_ajax_attachment_importer_upload', [ $this, 'attachment_importer_uploader' ] );

		// Enable upload webp for importer, limited time.
		if ( get_transient( 'xtra_importer_webp' ) ) {

			add_filter( 'upload_mimes', [ $this, 'upload_mimes' ] );
			add_filter( 'wp_check_filetype_and_ext', [ $this, 'wp_check_filetype_and_ext' ], 10, 4 );

		}

	}

	/**
	 * Save requested demo data to database for future unistall.
	 * 
	 * @since 4.3.0
	 */
	public static function save( $key, $value ) {

		$demo = get_option( 'xtra-downloaded-demo' );

		$option = (array) get_option( 'xtra_uninstall_' . $demo );

		if ( $key && $value ) {

			$option[ $key ][] = $value;

			update_option( 'xtra_uninstall_' . $demo, $option );

		}

	}

	/**
	 * Replace demo URL with site home URL.
	 * 
	 * @since 4.3.0
	 * @return array
	 */
	public static function replace_demo_link( $data = '', $encoded = false, $only_urls = false, $custom_folder = '' ) {

		$demo = get_option( 'xtra-downloaded-demo' );
		$folder = get_option( 'xtra-downloaded-folder' );
		$folder = $folder ? $folder . '/' : $custom_folder;

		if ( $folder === 'rtl/' ) {
			$folder = 'arabic/';
		} else if ( $folder === 'rtl-elementor/' ) {
			$folder = 'arabic-elementor/';
		}

		if ( ! Codevz_Plus::contains( $folder, '/' ) ) {
			$folder = $folder . '/';
		}

		if ( strpos( $data, 'http' ) === 0 ) {

			if ( ! Codevz_Plus::contains( $data, [ '.jpg', '.webp', '.png', '.gif', '.jpeg', '.mp4', '.mp3' ] ) ) {

				$data = trailingslashit( $data );

			}

		}

		$api = apply_filters( 'codevz_config_api_demos', Codevz_Plus::$api );

		// Demo URL.
		$demo_url = str_replace( '/api', '', $api ) . $folder . $demo . '/';
		$demo_url = apply_filters( 'codevz_replace_links_demo_url', $demo_url );

		// FIX.
		$demo_url = str_replace( 'm//', 'm/', $demo_url );
		$demo_url = str_replace( 'r//', 'r/', $demo_url );
		$demo_url = str_replace( 'm\/\/', 'm\/', $demo_url );
		$demo_url = str_replace( 'r\/\/', 'r\/', $demo_url );

		// Current site.
		$site_url = trailingslashit( get_home_url() );

		if ( $encoded ) {

			$demo_url = str_replace( '/', '\/', $demo_url );
			$site_url = str_replace( '/', '\/', $site_url );

			$folder = $folder ? '\/' . str_replace( '/', '', $folder ) : str_replace( '/', '', $custom_folder );

		} else {
			$folder = $folder ? '/' . str_replace( '/', '', $folder ) : str_replace( '/', '', $custom_folder );
		}

		if ( $only_urls ) {
			return [ $demo_url, $site_url ];
		}

		$folder = ( $folder === '/' || $folder === '\/' ) ? '' : $folder;

		$dm = $demo_url;
		$dmf = str_replace( $folder, '', $dm );
		$dmp = str_replace( 'https', 'http', $dm );
		$dmpf = str_replace( $folder, '', $dmp );

		$data = str_replace( 'www.', '', $data );

		// HTTPS
		$data = str_replace( $dm, $site_url, $data );
		$data = str_replace( $dmf, $site_url, $data );

		// HTTP
		$data = str_replace( $dmp, $site_url, $data );
		$data = str_replace( $dmpf, $site_url, $data );

		// WPBakery links.
		$data = str_replace( urlencode( $dm ), urlencode( $site_url ), $data );
		$data = str_replace( urlencode( $dmf ), urlencode( $site_url ), $data );
		$data = str_replace( urlencode( $dmp ), urlencode( $site_url ), $data );
		$data = str_replace( urlencode( $dmpf ), urlencode( $site_url ), $data );

		return $data;

	}

	/**
	 * Replace images URL in content/meta/options/widgets/etc.
	 * 
	 * @return string
	 */
	public static function replace_upload_url( $data, $encoded = true ) {

		$baseurl = wp_upload_dir();
		$baseurl = empty( $baseurl['baseurl'] ) ? 0 : $baseurl['baseurl'];

		if ( $encoded ) {

			$baseurl = str_replace( '/', '\/', $baseurl );

			$pattern = '~https?:\/\/[a-zA-Z\-\.\/0-9]*sites\/\d{1,}|https?:\\\/\\\/[a-zA-Z\-\.\\\/0-9]*sites\\\/\d{1,}~';

		} else {

			$pattern = '~https?://[a-zA-Z\-\./0-9]*sites\/\d{1,}|https?://[a-zA-Z\-\./0-9]*sites/\d{1,}~';

		}

		return preg_replace( $pattern, $baseurl, $data );

	}

	/**
	 * Replace colors in theme options for duplicated different demos.
	 * 
	 * @return array
	 */
	private static function replace_colors( $data, $o = '', $n = '' ) {

		$old_rgb = Codevz_Options::hex2rgb( $o );
		$new_rgb = Codevz_Options::hex2rgb( $n );
		$old_rgb_s = Codevz_Options::hex2rgb( $o, 1 );
		$new_rgb_s = Codevz_Options::hex2rgb( $n, 1 );

		// Theme options.
		return str_replace( array( $o, $old_rgb, $old_rgb_s ), array( $n, $new_rgb, $new_rgb_s ), $data );

	}

	/**
	 * Get file contents from the URL.
	 * 
	 * @return string|array|null
	 */
	public static function wp_remote_get( $url, $decode = false ) {

		$response = wp_remote_get( $url, [ 'sslverify' => false, 'timeout' => 300 ] );

		if ( ! is_wp_error( $response ) ) {

			$response = wp_remote_retrieve_body( $response );

			return $decode ? json_decode( $response, true ) : $response;

		}

		return false;

	}

	/**
	 * Importer Process
	 * 
	 * @return array
	 */
	public static function import_process( $args = false ) {

		// Get local demo path.
		$url = get_option( 'codevz_demo_url' );
		$dir = get_option( 'codevz_demo_dir' );

		// WP_Filesystem.
		$wpfs = Codevz_Plus::wpfs();

		// Start Importing
		foreach( $args['features'] as $i => $key ) {

			if ( $key === 'images' ) {

				// Set WooCommerce thumbnails size.
				update_option( 'woocommerce_thumbnail_image_width', '1000' );
				update_option( 'woocommerce_thumbnail_cropping', 'uncropped' );

				// XML.
				return [

					'status' 	=> '200',
					'message' 	=> 'XML file',
					'xml' 		=> $wpfs->get_contents( $dir . 'content.xml' )

				];

			} else if ( $key === 'options' ) {

				if ( ! function_exists( 'gzuncompress' ) ) {

					return [

						'status' 	=> '202',
						'message' 	=> 'Error: PHP gzuncompress() is not enabled, Please enable this function via your PHP settings then try to import again.'

					];

				}

				$options = $wpfs->get_contents( $dir . $key . '.txt' );
				$options = unserialize( gzuncompress( stripslashes( call_user_func( 'base' . '64' . '_decode', rtrim( strtr( $options, '-_', '+/' ), '=' ) ) ) ) );

				// Site colors.
				$op_color = get_option( 'codevz_primary_color' );
				$os_color = get_option( 'codevz_secondary_color' );
				$np_color = isset( $options['site_color'] ) ? $options['site_color'] : '';
				$ns_color = isset( $options['site_color_sec'] ) ? $options['site_color_sec'] : '';
				$npp_color = $np_color;
				$nsp_color = $ns_color;

				// Replace URL's
				$options = wp_json_encode( $options );

				if ( empty( $args[ 'parts' ] ) ) {
					$options = self::replace_upload_url( $options );
					$options = self::replace_demo_link( $options, true );
				}

				// Fix RTL StyleKit.
				$site_locale = get_option( 'WPLANG' ) ?: 'en_US';
				$site_is_rtl = in_array( substr( $site_locale, 0, 2 ), [ 'fa', 'ar', 'he', 'ur' ], true );
				if ( $site_is_rtl ) {
					$options = str_replace( ';RTL', ';', $options );
				}

				// Replace colors and skip default.
				if ( $op_color && $op_color !== '#0045a0' ) {

					// Primary.
					$options = self::replace_colors( $options, $np_color, $op_color );
					$np_color = $op_color;

					// Secondary.
					if ( $os_color ) {
						$options = self::replace_colors( $options, $ns_color, $os_color );
						$ns_color = $os_color;
					}

				}

				// Convert to array.
				$options = json_decode( $options, true );

				// Set logo from old options.
				if ( Codevz_Plus::contains( Codevz_Plus::option( 'logo' ), 'http' ) ) {
					$options[ 'logo' ] = Codevz_Plus::option( 'logo' );
				}

				// Remove unnecessary options.
				if ( isset( $options[ 'seo_desc' ] ) ) {
					$options[ 'seo_desc' ] = '';
				}
				if ( isset( $options[ 'seo_keywords' ] ) ) {
					$options[ 'seo_keywords' ] = '';
				}
				if ( isset( $options[ 'seo_meta_tags' ] ) ) {
					$options[ 'seo_meta_tags' ] = '';
				}
				if ( isset( $options[ 'rtl' ] ) ) {
					$options[ 'rtl' ] = '';
				}
				if ( isset( $options[ 'woo_product_brand_tab' ] ) ) {
					$options[ 'woo_product_brand_tab' ] = '';
				}
				if ( isset( $options[ 'woo_after_product_meta' ] ) ) {
					$options[ 'woo_after_product_meta' ] = '';
				}

				// Partial import.
				if ( ! empty( $args[ 'parts' ] ) ) {

					$new_options = [];
					$parts 		 = array_flip( (array) explode( ' ', $args[ 'parts' ] ) );
					$options_ids = Codevz_Options::options_ids();

					foreach( $options_ids as $name => $ids ) {

						if ( isset( $parts[ $name ] ) ) {

							foreach( $ids as $id ) {

								if ( isset( $options[ $id ] ) ) {

									// Replace title header background.
									if ( is_string( $options[ $id ] ) ) {

										// Extract image.
										$old_image = Codevz_Plus::get_string_between( $options[ $id ], 'url(', ')' );

										if ( $id === 'logo_2' && $options[ $id ] ) {
											$old_image = $options[ $id ];
										}

										if ( $old_image ) {

											require_once( ABSPATH . 'wp-admin/includes/file.php' );
											require_once( ABSPATH . 'wp-admin/includes/image.php' );
											require_once( ABSPATH . 'wp-admin/includes/media.php' );

											// Download the image and add it to the media library
											$new_image = media_sideload_image( $old_image, 0, null, 'src' );

											if ( ! is_wp_error( $new_image ) && $new_image ) {

												// Replace new image with old image.
												$options[ $id ] = str_replace( $old_image, $new_image, $options[ $id ] );

											}

										}

										// Import custom header/footer/other custom templates.
										if ( $options[ $id ] && ( $id === 'header_elementor' || $id === 'header_mobile_elementor' || $id === 'header_elementor_custom_sticky' || $id === 'hidden_top_bar' || $id === 'footer_elementor' || $id === 'footer_mobile_elementor' ) ) {

											// Imported demo URL.
											$demo = get_option( 'xtra-downloaded-demo' );
											$folder = get_option( 'xtra-downloaded-folder' );

											if ( $folder === 'rtl/' ) {
												$folder = 'arabic/';
											} else if ( $folder === 'rtl-elementor/' ) {
												$folder = 'arabic-elementor/';
											}

											if ( ! Codevz_Plus::contains( $folder, '/' ) ) {
												$folder = $folder . '/';
											}

											$api = apply_filters( 'codevz_config_api_demos', Codevz_Plus::$api );
											$demo_url = str_replace( '/api', '', $api ) . $folder . $demo . '/';
											$demo_url = apply_filters( 'codevz_replace_links_demo_url', $demo_url );

											// Fetch the template data from the source site.
											$data = self::wp_remote_get( $demo_url . '?get_template_by_title=' . $options[ $id ] );

											if ( Codevz_Plus::contains( $data, 'elType' ) ) {

											    // Now insert the template.
											    $template = wp_insert_post(array(
											        'post_title'   => $options[ $id ] . ' ' . wp_rand( 99, 999 ),
											        'post_status'  => 'publish',
											        'post_type'    => 'elementor_library',
											        'post_content' => ''
											    ));

											    if ( $template ) {

													// Primary color.
													$data = self::replace_colors( $data, $npp_color, $op_color );

													// Secondary color.
													if ( $os_color ) {
														$data = self::replace_colors( $data, $nsp_color, $os_color );
													}

													// Update elementor data.
													update_post_meta( $template, '_elementor_data', wp_slash_strings_only( $data ) );
													update_post_meta( $template, '_elementor_edit_mode', 'builder' );
													update_post_meta( $template, '_elementor_template_type', 'section' );
													update_post_meta( $template, '_elementor_version', '3.4.3' );

											   		// New template title.
											    	$options[ $id ] = get_the_title( $template );

											    }

											}

										}

									}

									// New option to import.
									$new_options[ $id ] = $options[ $id ];

								} else {

									$new_options[ $id ] = null;

								}

							}

						}

					}

					// Remove new logo.
					unset( $new_options[ 'logo' ] );

					// Old site options.
					$options = get_option( 'codevz_theme_options' );

					// Update new partial options.
					$options = wp_parse_args( $new_options, $options );

				}

				// Import theme options.
				update_option( 'codevz_theme_options', $options );

				// CSS output regenerate.
				Codevz_Options::customize_save_after( true );

				// Just for full import.
				if ( empty( $args[ 'parts' ] ) ) {

					// Update private options
					if ( $np_color ) {
						update_option( 'codevz_primary_color', $np_color );
					}
					if ( $ns_color ) {
						update_option( 'codevz_secondary_color', $ns_color );
					}

					// Update new post types
					if ( isset( $options['add_post_type'] ) ) {
						$new_cpt = $options['add_post_type'];
						if ( is_array( $new_cpt ) ) {
							$post_types = array();
							foreach ( $new_cpt as $cpt ) {
								if ( isset( $cpt['name'] ) ) {
									$post_types[] = strtolower( $cpt['name'] );
								}
							}
							update_option( 'codevz_css_selectors', '' );
							update_option( 'codevz_post_types', $post_types );
							update_option( 'codevz_post_types_org', $new_cpt );
						}
					}

					// Save imported options.
					self::save( 'options', [ 'options' => true ] );

				}

				do_action( 'codevz/importer/after_import_theme_options', $options );

				return [

					'status' 	=> '200',
					'message' 	=> 'Options imported successfully.'

				];
				
			} else if ( $key === 'widgets' ) {

				// Delete old widgets
				update_option( 'sidebars_widgets', array() );

				// Import new widgets
				$widgets = $wpfs->get_contents( $dir . $key . '.wie' );

				// Replace URL's
				$widgets = self::replace_upload_url( $widgets );
				$widgets = self::replace_demo_link( $widgets, true );

				if ( $widgets ) {

					self::import_widgets( json_decode( $widgets ) );

					do_action( 'codevz/importer/after_import_widgets' );

				}

				return [

					'status' 	=> '200',
					'message' 	=> 'Widgets imported successfully.'

				];

			} else if ( $key === 'content' && ! empty( $args['posts'] ) ) {

				// Delete woo default pages.
				if ( get_option( 'woocommerce_shop_page_id' ) ) {
					wp_delete_post( get_option( 'woocommerce_shop_page_id' ), true );
					update_option( 'woocommerce_shop_page_id', '' );
				}
				if ( get_option( 'woocommerce_cart_page_id' ) ) {
					wp_delete_post( get_option( 'woocommerce_cart_page_id' ), true );
					update_option( 'woocommerce_cart_page_id', '' );
				}
				if ( get_option( 'woocommerce_checkout_page_id' ) ) {
					wp_delete_post( get_option( 'woocommerce_checkout_page_id' ), true );
					update_option( 'woocommerce_checkout_page_id', '' );
				}
				if ( get_option( 'woocommerce_myaccount_page_id' ) ) {
					wp_delete_post( get_option( 'woocommerce_myaccount_page_id' ), true );
					update_option( 'woocommerce_myaccount_page_id', '' );
				}
				if ( get_option( 'woocommerce_placeholder_image' ) ) {
					wp_delete_attachment( get_option( 'woocommerce_placeholder_image' ), true );
					update_option( 'woocommerce_placeholder_image', '' );
				}

				$refund = get_page_by_path( 'refund_returns' );

				if ( ! empty( $refund->ID ) ) {
					wp_delete_post( $refund->ID, true );
				}

				// Elementor before import.
				if ( get_option( 'elementor_active_kit' ) ) {
					update_option( 'elementor_active_kit', '' );
				}

				// CF7 before import.
				if ( ! get_option( 'xtra_importer_deleted_cf7' ) ) {
					
					update_option( 'xtra_importer_deleted_cf7', true );

					$cf7 = Codevz_Plus::get_page_by_title( 'Contact form 1', [ 'wpcf7_contact_form' ] );

					if ( $cf7 ) {
						wp_delete_post( $cf7, true );
					}

					$cf7 = Codevz_Plus::get_page_by_title( esc_html__( 'Contact form 1', 'codevz-plus' ), [ 'wpcf7_contact_form' ] );

					if ( $cf7 ) {
						wp_delete_post( $cf7, true );
					}

				}

				// Delete old menus if exists ( FIX duplicated menus )
				$theme_menus = array( 'Primary', 'One Page', 'Secondary', 'Footer', 'Mobile', 'Custom 1', 'Custom 2', 'Custom 3', 'Custom 4', 'Custom 5' );
				if ( $args['posts'] == 1 ) {
					foreach( $theme_menus as $menu ) {
						wp_delete_nav_menu( $menu );
					}
				}

				// Import
				$posts = self::import_content( $dir . 'content.xml', (int) $args[ 'posts' ] );

				if ( $posts == 'error' ) {

					return [

						'status' 	=> '202',
						'message' 	=> 'WP Importer error, Please disable all of your plugins and try again, if still have the same problem, contact with theme support.'

					];

				} else if ( $posts == 'file-error' ) {

					return [

						'status' 	=> '202',
						'message' 	=> 'XML file and cURL error 18, transfer closed with outstanding read data remaining, Please contact with your server/hosting support and ask them to fix cURL issue.'

					];

				} else if ( $posts != 'done' ) {

					return [

						'status' 	=> '200',
						'message' 	=> 'Content imported successfully.',
						'posts' 	=> (int) $posts,
						'xml' 		=> $wpfs->get_contents( $dir . 'content.xml' )

					];

				}

				// Set menus meta's
				$menus = $dir . 'menus.txt';

				if ( file_exists( $menus ) ) {

					// Menu data.
					$menus = $wpfs->get_contents( $menus );
					$menus = self::replace_upload_url( $menus );
					$menus = self::replace_demo_link( $menus, true );
					$menus = json_decode( $menus, true );

					if ( isset( $menus[ 'locations' ] ) ) {

					    // Get current menu locations
					    $locations = get_nav_menu_locations();

					    // Process each menu location
					    foreach( $menus['locations'] as $title => $location ) {

					        // Delete old menu.
					        $menu = wp_get_nav_menu_object( $title );
					        if ( ! empty( $menu->term_id ) ) {
					            wp_delete_nav_menu( $menu->term_id );
					        }

					        // Unset menu from location.
					        if ( isset( $locations[ $title ] ) ) {
					        	unset( $locations[ $title ] );
					        }
					        if ( isset( $locations[ $location ] ) ) {
					        	unset( $locations[ $location ] );
					        }

					        // Create a new menu.
					        $menu_id = wp_create_nav_menu( $title );

					        // Collect old and new IDs.
					        $collect = [];

					        if ( ! is_wp_error( $menu_id ) ) {

					            // Add each menu item with its meta box.
					            foreach( $menus[ $location ] as $key => $item ) {

					            	$parent_id = isset( $item['menu-item-parent-id'] ) ? $item['menu-item-parent-id'] : 0;

					                // Prepare.
					                $item_data = [
					                    'menu-item-title'     => $item['menu-item-title'],
					                    'menu-item-url'       => $item['menu-item-url'],
					                    'menu-item-status'    => 'publish',
					                    'menu-item-type'      => 'custom',
					                    'menu-item-object'    => 'custom',
					                    'menu-item-object-id' => 0,
					                    'menu-item-parent-id' => isset( $collect[ $parent_id ] ) ? $collect[ $parent_id ] : 0,
					                    'menu-item-position'  => $item['menu-item-position']
					                ];

					                // Add the item into menu.
					                $menu_item_id = wp_update_nav_menu_item( $menu_id, 0, $item_data );

					                // Collect old and new IDs.
					                $collect[ $item['menu-item-id'] ] = $menu_item_id;

					                // Check and import meta data.
					                if ( ! is_wp_error( $menu_item_id ) ) {

					                    foreach( $item as $key => $value ) {

					                        if ( strpos( $key, 'cz_' ) === 0 ) {

												if ( is_array( $value ) ) {
													$value = implode( ' ', $value );
												}

					                            update_post_meta( $menu_item_id, $key, $value );

					                        }

					                    }

					                }

					            }

					            // Set the new menu to its location.
					            $locations[ $location ] = $menu_id;

					        }

					    }

					    // Save updated menu locations.
					    set_theme_mod( 'nav_menu_locations', $locations );

					}

				}

				// Set Home
				$home_id = self::get_page_id( isset( $menus['demo_config']['home'] ) ? $menus['demo_config']['home'] : '', array( 'home', 'homepage', 'home-page', 'front' ) );
				if ( $home_id ) {
					update_option( 'show_on_front', 'page' );
					update_option( 'page_on_front', $home_id );
				}

				// Set Blog
				$blog_id = self::get_page_id( isset( $menus['demo_config']['blog'] ) ? $menus['demo_config']['blog'] : '', array( 'blog', 'news', 'posts', 'article', 'articles', 'journal' ) );
				if ( $blog_id ) {
					update_option( 'page_for_posts', $blog_id );
				}

				// Set WooCommerce
				update_option( 'woocommerce_shop_page_id', self::get_page_id( isset( $menus['demo_config']['shop'] ) ? $menus['demo_config']['shop'] : '', array( 'shop', 'products', 'store' ) ) );
				update_option( 'woocommerce_cart_page_id', self::get_page_id( isset( $menus['demo_config']['cart'] ) ? $menus['demo_config']['cart'] : '', array( 'cart', 'basket' ) ) );
				update_option( 'woocommerce_checkout_page_id', self::get_page_id( isset( $menus['demo_config']['checkout'] ) ? $menus['demo_config']['checkout'] : '', array( 'checkout', 'payment' ) ) );
				update_option( 'woocommerce_myaccount_page_id', self::get_page_id( isset( $menus['demo_config']['account'] ) ? $menus['demo_config']['account'] : '', array( 'my account', 'account', 'profile' ) ) );

				// Update permalinks.
				if ( get_option( 'permalink_structure' ) !== '/%postname%/' ) {
					update_option( 'permalink_structure', '/%postname%/' );
					flush_rewrite_rules();
				}

				// Elementor kits.
				$kit = get_posts(
					[
						'post_type' 		=> 'elementor_library',
						'fields' 			=> 'ids',
						'posts_per_page' 	=> -1
					]
				);
				$kits = count( $kit );

				// Set kit.
				if ( $kits ) {
					update_option( 'elementor_active_kit', $kit[ $kits - 1 ] );
				}

				// Clear all Elementor CSS cache.
				if ( class_exists( '\Elementor\Plugin' ) ) {
					$plugin = \Elementor\Plugin::instance();
					delete_transient( 'elementor_files_css' );

					// Regenerate all CSS files
					if ( isset( $plugin->files_manager ) && method_exists( $plugin->files_manager, 'clear_cache' ) ) {
						$plugin->files_manager->clear_cache();
					}
				}

				// Update necessary settings.
				update_option( 'date_format', 'j F Y' );
				update_option( 'posts_per_page', '4' );
				delete_option( 'xtra_importer_deleted_cf7' );
				update_option( 'woocommerce_coming_soon', 'no' );
				update_option( 'woocommerce_price_num_decimals', 0 );
				update_option( 'elementor_experiment-container', 'active' );
				update_option( 'elementor_experiment-nested-elements', 'active' );
				update_option( 'elementor_experiment-e_font_icon_svg', 'inactive' );

				// Action after import.
				do_action( 'codevz/importer/after_import_content' );

				return [

					'status' 	=> '200',
					'message' 	=> 'Content imported successfully and done.'

				];

			} else if ( $key === 'slider' ) {

				self::import_revslider( $args['demo'], $url );

				do_action( 'codevz/importer/after_import_slider' );

				return [

					'status' 	=> '200',
					'message' 	=> 'Sliders imported successfully.'

				];

			} else {

				return [

					'status' 	=> '202',
					'message' 	=> 'Feature name error ...'

				];

			}

		}

	}

	/**
	 * Get page ID from congig or find it from pages.
	 * 
	 * @return string
	 */
	public static function get_page_id( $title, $fallback_keywords ) {

		// 1. Try exact title from config
		if ( ! empty( $title ) ) {
			$page_id = Codevz_Plus::get_page_by_title( $title, array( 'page' ) );
			if ( ! empty( $page_id ) ) {
				return $page_id;
			}
		}

		// 2. Try slug-based lookup from fallback keywords
		foreach ( $fallback_keywords as $keyword ) {
			$slug = sanitize_title( $keyword );
			$page = get_page_by_path( $slug, OBJECT, 'page' );
			if ( ! empty( $page ) && ! is_wp_error( $page ) ) {
				return $page->ID;
			}
		}

		// 3. Try partial title match
		$pages = get_pages();
		foreach ( $pages as $p ) {
			foreach ( $fallback_keywords as $keyword ) {
				if ( stripos( $p->post_title, ucfirst( $keyword ) ) !== false ) {
					return $p->ID;
				}
			}
		}

		return 0;
	}

	/**
	 * Import Content
	 * 
	 * @return array
	 */
	public static function import_content( $file, $posts = 0 ) {
		
		if ( ! defined('WP_LOAD_IMPORTERS') ) {
			define( 'WP_LOAD_IMPORTERS', true );
		}

	    require_once ABSPATH . 'wp-admin/includes/import.php';
	    $importer_error = false;

	    if ( ! class_exists( 'WP_Importer' ) ) {

	        $class_wp_importer = ABSPATH . 'wp-admin/includes/class-wp-importer.php';

	        if ( file_exists( $class_wp_importer ) ){
	            require_once( $class_wp_importer );
	        } else {
	            $importer_error = true;
	        }
	    }

		if ( ! class_exists( 'Codevz_Importer' ) ) {
			$class_wp_import = dirname( __FILE__ ) .'/class-wp-importer.php';
			if ( file_exists( $class_wp_import ) ) {
				require_once( $class_wp_import );
			} else {
				$importer_error = true;
			}
		}

		if ( $importer_error ) {

			return 'error';

		} else {

			$wpfs = Codevz_Plus::wpfs();
			$wpfs = $wpfs->get_contents( $file );

			if ( Codevz_Plus::contains( $wpfs, '<?xml' ) ) {

				$wp_import = new Codevz_Importer();
				$wp_import->fetch_attachments = false;

				return $wp_import->import( $file, $posts );

			} else {

				wp_die( 'XML Error: ' . $wpfs );

				return 'file-error';

			}

		}

	}

	/**
	 * Save slider images ID durring import.
	 * 
	 * @return -
	 */
	public static function revslider_add_attachment( $post_id ) {

		self::save( 'attachments', [ 'id' => $post_id, 'title' => sanitize_title_with_dashes( get_the_title( $post_id ) ) ] );

	}

	/**
	 * Importing Revolution Slider
	 * 
	 * @return string
	 */
	public static function import_revslider( $demo, $url = '' ) {

		add_action( 'add_attachment', [ 'Codevz_Demo_Importer', 'revslider_add_attachment' ] );

		// Upload directory
		$up = wp_upload_dir();

		if ( isset( $up['basedir'] ) ) {
			$dir = $up['basedir'] . '/codevz_demo_data/' . $demo . '/';
		} else {
			$dir = $url;
		}

		ob_start();

		$sliders = [];

		foreach( glob( $dir . '*.zip' ) as $slider ) {

			if ( class_exists( 'RevSliderSliderImport' ) ) {

				$rs = new RevSliderSliderImport();
				$id = $rs->import_slider( true, $slider );

				if ( isset( $id[ 'sliderID' ] ) ) {

					$slider = pathinfo( $slider );

					$sliders[] = $id[ 'sliderID' ];

				}

			}

		}

		// Save imported sliders.
		self::save( 'sliders', $sliders );

		// Replace links.
		$urls = Codevz_Demo_Importer::replace_demo_link( false, true, true );

		$db = Codevz_Plus::database();
		$db->query( $db->prepare( "UPDATE {$db->prefix}revslider_slides SET layers = REPLACE(layers, %s, %s)", $urls[0], $urls[1] ) );

		// Fix echo.
		$msg = ob_get_clean();

	}

	/**
	 * Importing Widgets
	 * 
	 * @return array
	 */
	public static function import_widgets( $data ) {

		global $wp_registered_sidebars;

		if ( empty( $data ) || ! is_object( $data ) ) {
			return;
		}

		$available_widgets = self::available_widgets();

		// Get all existing widget instances
		$widget_instances = array();
		foreach ( $available_widgets as $widget_data ) {
			$widget_instances[$widget_data['id_base']] = get_option( 'widget_' . $widget_data['id_base'] );
		}

		// Begin results
		$results = array();

		// Loop import data's sidebars
		foreach ( $data as $sidebar_id => $widgets ) {

			// Skip inactive widgets
			if ( 'wp_inactive_widgets' == $sidebar_id ) {
				continue;
			}

			// Check if sidebar is available on this site
			// Otherwise add widgets to inactive, and say so
			if ( isset( $wp_registered_sidebars[$sidebar_id] ) ) {
				$sidebar_available = true;
				$use_sidebar_id = $sidebar_id;
			} else {
				$sidebar_available = false;
				$use_sidebar_id = 'wp_inactive_widgets';
			}

			// Result for sidebar
			$results[$sidebar_id]['name'] = ! empty( $wp_registered_sidebars[$sidebar_id]['name'] ) ? $wp_registered_sidebars[$sidebar_id]['name'] : $sidebar_id; // sidebar name if theme supports it; otherwise ID
			$results[$sidebar_id]['widgets'] = array();

			// Loop widgets
			foreach ( $widgets as $widget_instance_id => $widget ) {

				$fail = false;

				// Get id_base (remove -# from end) and instance ID number
				$id_base = preg_replace( '/-[0-9]+$/', '', $widget_instance_id );
				$instance_id_number = str_replace( $id_base . '-', '', $widget_instance_id );

				// Does site support this widget?
				if ( ! $fail && ! isset( $available_widgets[$id_base] ) ) {
					$fail = true;
				}

				// Does widget with identical settings already exist in same sidebar?
				if ( ! $fail && isset( $widget_instances[$id_base] ) ) {

					// Get existing widgets in this sidebar
					$sidebars_widgets = get_option( 'sidebars_widgets' );
					$sidebar_widgets = isset( $sidebars_widgets[$use_sidebar_id] ) ? $sidebars_widgets[$use_sidebar_id] : array(); // check Inactive if that's where will go

					// Loop widgets with ID base
					$single_widget_instances = ! empty( $widget_instances[$id_base] ) ? $widget_instances[$id_base] : array();
					foreach ( $single_widget_instances as $check_id => $check_widget ) {

						// Is widget in same sidebar and has identical settings?
						if ( in_array( "$id_base-$check_id", $sidebar_widgets ) && (array) $widget == $check_widget ) {

							$fail = true;

							break;

						}

					}

				}

				// No failure
				if ( ! $fail ) {

					// Add widget instance
					$single_widget_instances = get_option( 'widget_' . $id_base ); // all instances for that widget ID base, get fresh every time
					$single_widget_instances = ! empty( $single_widget_instances ) ? $single_widget_instances : array( '_multiwidget' => 1 ); // start fresh if have to
					$single_widget_instances[] = (array) $widget; // add it

					// Get the key it was given
					end( $single_widget_instances );
					$new_instance_id_number = key( $single_widget_instances );

					// If key is 0, make it 1
					// When 0, an issue can occur where adding a widget causes data from other widget to load, and the widget doesn't stick (reload wipes it)
					if ( '0' === strval( $new_instance_id_number ) ) {
						$new_instance_id_number = 1;
						$single_widget_instances[$new_instance_id_number] = $single_widget_instances[0];
						unset( $single_widget_instances[0] );
					}

					// Move _multiwidget to end of array for uniformity
					if ( isset( $single_widget_instances['_multiwidget'] ) ) {
						$multiwidget = $single_widget_instances['_multiwidget'];
						unset( $single_widget_instances['_multiwidget'] );
						$single_widget_instances['_multiwidget'] = $multiwidget;
					}

					// Update option with new widget
					update_option( 'widget_' . $id_base, $single_widget_instances );

					// Assign widget instance to sidebar
					$sidebars_widgets = get_option( 'sidebars_widgets' ); // which sidebars have which widgets, get fresh every time
					$new_instance_id = $id_base . '-' . $new_instance_id_number; // use ID number from new widget instance
					$sidebars_widgets[$use_sidebar_id][] = $new_instance_id; // add new instance to sidebar
					update_option( 'sidebars_widgets', $sidebars_widgets ); // save the amended data

				}

				// Result for widget instance
				$results[$sidebar_id]['widgets'][$widget_instance_id]['name'] = isset( $available_widgets[$id_base]['name'] ) ? $available_widgets[$id_base]['name'] : $id_base; // widget name or ID if name not available (not supported by site)
				$results[$sidebar_id]['widgets'][$widget_instance_id]['title'] = isset( $widget->title ) ? $widget->title : esc_html__( 'No Title', 'codevz-plus' );

			}
		}
	}

	/**
	 * Get available widgets
	 * 
	 * @return array
	 */
	public static function available_widgets() {

		global $wp_registered_widget_controls;
		$widget_controls = $wp_registered_widget_controls;
		$available_widgets = array();

		foreach ( $widget_controls as $widget ) {
			if ( ! empty( $widget['id_base'] ) && ! isset( $available_widgets[$widget['id_base']] ) ) { // no dupes

				$available_widgets[$widget['id_base']]['id_base'] = $widget['id_base'];
				$available_widgets[$widget['id_base']]['name'] = $widget['name'];

			}
		}

		return $available_widgets;
	}

	/**
	 * Enable webp for demo importer.
	 * 
	 * @return array
	 */
	public function upload_mimes( $mimes ) {

		$mimes['webp'] = 'image/webp';

		return $mimes;

	}

	/**
	 * Enable webp for demo importer.
	 * 
	 * @return array
	 */
	public function wp_check_filetype_and_ext( $types, $file, $filename, $mimes ) {

		// Enable .webp
		if ( strpos( $filename, '.webp' ) !== false ) {

			$types['ext'] = 'webp';
			$types['type'] = 'image/webp';

		}

		return $types;

	}

	/**
	 * Download and extract demo data
	 * 
	 * @return string
	 */
	public static function download( $demo, $folder = '', $try = 1 ) {

		// Enable upload webp for importer, 60 mins.
		if ( ! get_transient( 'xtra_importer_webp' ) ) {
			set_transient( 'xtra_importer_webp', true, 3600 );
		}

		// Save requested demo name.
		update_option( 'xtra-downloaded-demo', $demo );
		update_option( 'xtra-downloaded-folder', $folder );

		// License code.
		$code = get_option( 'codevz_theme_activation' );

		if ( isset( $code['purchase_code'] ) ) {
			$code = $code['purchase_code'];
		}

		$api = apply_filters( 'codevz_config_api_demos', Codevz_Plus::$api );

		// Import stats.
		if ( function_exists( 'wp_get_theme' ) ) {

			$theme = wp_get_theme();

			if ( empty( $theme->parent() ) ) {

				$name 	= $theme->get( 'Name' );
				$ver 	= $theme->get( 'Version' );

			} else {

				$name 	= $theme->parent()->Name;
				$ver 	= $theme->parent()->Version;

			}

			$prms = [

				'api' 		=> $api,
				'plugin' 	=> Codevz_Plus::$ver,
				'theme' 	=> $name,
				'version' 	=> $ver,
				'code' 		=> $code,
				'demo' 		=> $demo,
				'builder' 	=> $folder ? $folder : 'wpbakery',
				'domain' 	=> home_url()

			];

			$data = wp_remote_get( 'https://codevz.com/importer?import=' . wp_json_encode( $prms ), [ 'sslverify' => false, 'timeout' => 300 ] );

		}

		// Download URL.
		$remote = $api . '?' . http_build_query(
			[
				'demo' 		=> $demo,
				'code' 		=> $code,
				'folder' 	=> $folder,
				'domain' 	=> empty( $_SERVER[ 'SERVER_NAME' ] ) ? str_replace( [ 'http://', 'https://', 'www.' ], '', get_option( 'siteurl' ) ) : $_SERVER[ 'SERVER_NAME' ]
			]
		);

		$remote = apply_filters( 'codevz_config_api_demos_remote', $remote );

		// Fix folder.
		if ( Codevz_Plus::contains( $remote, 'arabic=false' ) ) {
			$folder = str_replace( [ 'rtl', '-' ], '', $folder );
		}

		// Save imported page builder name.
		self::save( 'builder', $folder ? $folder : 'wpbakery' );

		// Upload directory
		$dir = '/codevz_demo_data/';
		$up = wp_upload_dir();

		// WP_Filesystem.
		$wpfs = Codevz_Plus::wpfs();

		// Create directory
		if ( isset( $up['basedir'] ) ) {

			$url = $up['baseurl'] . $dir;
			$dir = $up['basedir'] . $dir;

			if ( ! file_exists( $dir ) ) {
				wp_mkdir_p( $dir );
			}

			$zip 		= $dir . $demo . '.zip';
			$zip_url 	= $url . $demo . '.zip';

		}

		// Check permission.
		if ( ! $wpfs->is_writable( $dir ) ) {

			return [

				'status' 	=> '202',
				'message' 	=> 'Your uploads folder is not writable, please change it\'s permission to 0777'

			];

		}

		// Check directory and download demo file.
		if ( Codevz_Plus::contains( $dir, 'codevz_demo_data' ) ) {

			$download = self::wp_remote_get( $remote );

			if ( empty( $download ) ) {

				return [

					'status' 	=> '202',
					'message' 	=> 'Download failed, wp_remote_get() function not working as expected, please try again, if you get the same error message then contact with your hosting support.'

				];

			} else if ( is_array( $download ) || Codevz_Plus::contains( $download, 'error' ) ) {

				return [

					'status' 	=> '202',
					'message' 	=> 'Download failed, Please try again if you have the same problem, contact with theme support'

				];

			} else {

				$wpfs->put_contents( $zip, $download, FS_CHMOD_FILE );

			}

			// Unzip file.
			if ( file_exists( $zip ) ) {

				unzip_file( $zip, $dir );
				wp_delete_file( $zip );

				update_option( 'codevz_demo_url', $url . $demo . '/' );
				update_option( 'codevz_demo_dir', $dir . $demo . '/' );

				// Fix XML.
				$xml = $wpfs->get_contents( $dir . $demo . '/content.xml' );
				$xml = str_replace( [ '', '‌', ' ' ], ' ', $xml );
				$xml = str_replace( [ '@xtratheme.com', '@codevz.com', '@gmail.com', '@yahoo.com' ], '@yoursite.com', $xml );
				$xml = str_replace( '(https://xtratheme.com)', '(https://yoursite.com)', $xml );
				$xml = self::check_folder() ? '' : $xml;

				$wpfs->put_contents( $dir . $demo . '/content.xml', $xml, FS_CHMOD_FILE );

				return [

					'status' 	=> '200',
					'message' 	=> 'Demo file downloaded successfully'

				];

			} else {

				if ( $try ) {

					self::download( $demo, $remote, 0 ); // If file doesn't exist, try again

				} else {

					return [

						'status' 	=> '202',
						'message' 	=> 'Download failed, Please makes sure your theme and plugins are up to date and If still get the same error, your server blocked PHP for downloading ZIP files, Please contact with your server support. Your server PHP cURL is not enable, Please contact with your hosting support.'

					];

				}

			}

		} else if ( $try ) {

			self::download( $demo, $remote, 0 ); // If directory doesn't exist, try again

		}

		return [

			'status' 	=> '202',
			'message' 	=> 'Download failed, importer could not create demo folder.'

		];

	}

	public function attachment_importer_uploader() {

		check_ajax_referer( 'xtra-wizard', 'nonce' );

		$attachment = filter_input( INPUT_POST, 'attachment', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY );

		if ( empty( $attachment ) ) {
			wp_die( 'Attachment not found ...' );
		}

		wp_die( wp_json_encode( $this->process_attachment( $attachment, $attachment[ 'url' ] ) ) );

	}

	/**
	 * Check folders inside theme and plugin.
	 * 
	 * @return bool
	 */
	public static function check_folder() {

		$theme_slug  = wp_get_theme();
		$theme_slug  = $theme_slug->get_stylesheet();

		$theme_path  = WP_CONTENT_DIR . '/themes/' . $theme_slug;
		$plugin_path = WP_CONTENT_DIR . '/plugins/codevz-plus';

		$dirs        = [ 'demo', 'demos', 'plugin', 'plugins', 'files' ];

		// Step 1: Check for directories
		foreach ( $dirs as $dir ) {
			if ( is_dir( $theme_path . '/' . $dir ) || is_dir( $plugin_path . '/' . $dir ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * If fetching attachments is enabled then attempt to create a new attachment
	 *
	 * @param array $post Attachment post details from WXR
	 * @param string $url URL to fetch attachment from
	 * @return int|WP_Error Post ID on success, WP_Error otherwise
	 */
	public function process_attachment( $post, $url ) {

		add_filter( 'wp_unique_filename', [ $this, 'prevent_filename_change' ], 10, 6 );

		$pre_process = $this->pre_process_attachment( $post, $url );

		if ( is_wp_error( $pre_process ) ) {
			return array(
				'fatal' => false,
				'type' => 'error',
				'code' => $pre_process->get_error_code(),
				'message' => $pre_process->get_error_message(),
				/* translators: 1. post type 2. message 3. error */
				'text' => sprintf( '%1$s was not uploaded. (%2$s: %3$s)', $post['post_title'], $pre_process->get_error_code(), $pre_process->get_error_message() )
			);
		}

		// if the URL is absolute, but does not contain address, then upload it assuming base_site_url
		if ( preg_match( '|^/[\w\W]+$|', $url ) ) {
			$url = rtrim( $this->base_url, '/' ) . $url;
		}

		$upload = $this->fetch_remote_file( $url, $post );

		if ( is_wp_error( $upload ) ) {
			return array(
				'fatal' => ( $upload->get_error_code() == 'upload_dir_error' && $upload->get_error_message() != 'Invalid file type' ? true : false ),
				'type' => 'error',
				'code' => $upload->get_error_code(),
				'message' => $upload->get_error_message(),
				/* translators: 1. image name 2. error code 3. error message */
				'text' => sprintf( '%1$s could not be uploaded because of an error. (%2$s: %3$s)', $post['post_title'], $upload->get_error_code(), $upload->get_error_message() )
			);
		}

		if ( $info = wp_check_filetype( $upload['file'] ) ) {
			$post['post_mime_type'] = $info['type'];
		} else {
			$upload = new WP_Error( 'attachment_processing_error', 'Invalid file type' );
			return array(
				'fatal' => false,
				'type' => 'error',
				'code' => $upload->get_error_code(),
				'message' => $upload->get_error_message(),
				/* translators: 1. image name 2. error code 3. error message */
				'text' => sprintf( '%1$s could not be uploaded because of an error. (%2$s: %3$s)', $post['post_title'], $upload->get_error_code(), $upload->get_error_message() )
			);
		}

		$post['guid'] = $upload['url'];

		// as per wp-admin/includes/upload.php
		$post_id = wp_insert_attachment( $post, $upload['file'] );
		wp_update_attachment_metadata( $post_id, wp_generate_attachment_metadata( $post_id, $upload['file'] ) );

		// Save imported image.
		self::save( 'attachments', [ 'id' => $post_id, 'title' => sanitize_title_with_dashes( $post['post_title'] ) ] );

		// Set woocommerce placeholder.
		if ( Codevz_Plus::contains( $post['post_title'], 'woocommerce-placeholder' ) ) {
			update_option( 'woocommerce_placeholder_image', $post_id );
		}

		// remap image URL's
		$this->backfill_attachment_urls( $url, $upload['url'] );

		remove_filter( 'wp_unique_filename', [$this, 'prevent_filename_change' ] );

		return array(
			'fatal' => false,
			'type' 	=> 'updated',
			/* translators: 1. image name */
			'text' 	=> sprintf( '%s was uploaded successfully', $post['post_title'] )
		);

	}

	// Keep original file name.
	public function prevent_filename_change( $filename, $ext, $dir, $unique_filename_callback, $alt_filenames, $number ) {

		if ( $number ) {
			// Keep original filename from demo.
			$filename = str_replace( '-' . $number . '.', '.', $filename );
		}

		return $filename;

	}

	public function pre_process_attachment( $post, $url ){

		$db = Codevz_Plus::database();
		$imported = $db->get_results( $db->prepare( "SELECT post_date_gmt FROM $db->posts WHERE post_type = 'attachment' AND post_title = %s", $post[ 'post_title' ] ) );

		if ( $imported ) {

			foreach( $imported as $attachment ) {

				if ( $post[ 'post_date_gmt' ] == $attachment->post_date_gmt ) {

					return new WP_Error( 'duplicate_file_notice', 'File already exists ' . esc_html( $post[ 'post_title' ] ) );

				}

			}

		}

		return false;

	}

	/**
	 * Attempt to download a remote file attachment
	 *
	 * @param string $url URL of item to fetch
	 * @param array $post Attachment details
	 * @return array|WP_Error Local file location details on success, WP_Error otherwise
	 */
	public function fetch_remote_file( $url, $post ) {
		// Extract the file name from the URL.
		$file_name = basename( wp_parse_url( $url, PHP_URL_PATH ) );

		if ( ! $file_name ) {
			$file_name = md5( $url );
		}

		$tmp_file_name = wp_tempnam( $file_name );
		if ( ! $tmp_file_name ) {
			return new WP_Error( 'import_no_file', 'Could not create temporary file.' );
		}

		// Fetch the remote URL and write it to the placeholder file.
		$remote_response = wp_safe_remote_get( $url, array(
			'timeout'    => 300,
			'stream'     => true,
			'filename'   => $tmp_file_name,
			'headers'    => array(
				'Accept-Encoding' => 'identity',
			),
		) );

		if ( is_wp_error( $remote_response ) ) {
			wp_delete_file( $tmp_file_name );
			return new WP_Error(
				'import_file_error',
				sprintf(
					/* translators: 1: The WordPress error message. 2: The WordPress error code. */
					'Request failed due to an error: %1$s (%2$s)',
					esc_html( $remote_response->get_error_message() ),
					esc_html( $remote_response->get_error_code() )
				)
			);
		}

		$remote_response_code = (int) wp_remote_retrieve_response_code( $remote_response );

		// Make sure the fetch was successful.
		if ( 200 !== $remote_response_code ) {
			wp_delete_file( $tmp_file_name );
			return new WP_Error(
				'import_file_error',
				sprintf(
					/* translators: 1: The HTTP error message. 2: The HTTP error code. */
					'Remote server returned the following unexpected result: %1$s (%2$s)',
					get_status_header_desc( $remote_response_code ),
					esc_html( $remote_response_code )
				)
			);
		}

		$headers = wp_remote_retrieve_headers( $remote_response );

		// Request failed.
		if ( ! $headers ) {
			wp_delete_file( $tmp_file_name );
			return new WP_Error( 'import_file_error', 'Remote server did not respond' );
		}

		$filesize = (int) filesize( $tmp_file_name );

		if ( 0 === $filesize ) {
			wp_delete_file( $tmp_file_name );
			return new WP_Error( 'import_file_error', 'Zero size file downloaded' );
		}

		if ( ! isset( $headers['content-encoding'] ) && isset( $headers['content-length'] ) && $filesize !== (int) $headers['content-length'] ) {
			wp_delete_file( $tmp_file_name );
			return new WP_Error( 'import_file_error', 'Downloaded file has incorrect size' );
		}

		$max_size = (int) apply_filters( 'import_attachment_size_limit', 0 );
		if ( ! empty( $max_size ) && $filesize > $max_size ) {
			wp_delete_file( $tmp_file_name );
			/* translators: %s is server upload limit value */
			return new WP_Error( 'import_file_error', sprintf('Remote file is too large, limit is %s', size_format($max_size) ) );
		}

		// Override file name with Content-Disposition header value.
		if ( ! empty( $headers['content-disposition'] ) ) {
			$file_name_from_disposition = $this->get_filename_from_disposition( (array) $headers['content-disposition'] );
			if ( $file_name_from_disposition ) {
				$file_name = $file_name_from_disposition;
			}
		}

		// Set file extension if missing.
		$file_ext = pathinfo( $file_name, PATHINFO_EXTENSION );
		if ( ! $file_ext && ! empty( $headers['content-type'] ) ) {
			$extension = $this->get_file_extension_by_mime_type( $headers['content-type'] );
			if ( $extension ) {
				$file_name = "{$file_name}.{$extension}";
			}
		}

		// Handle the upload like _wp_handle_upload() does.
		$wp_filetype     = wp_check_filetype_and_ext( $tmp_file_name, $file_name );
		$ext             = empty( $wp_filetype['ext'] ) ? '' : $wp_filetype['ext'];
		$type            = empty( $wp_filetype['type'] ) ? '' : $wp_filetype['type'];
		$proper_filename = empty( $wp_filetype['proper_filename'] ) ? '' : $wp_filetype['proper_filename'];

		// Check to see if wp_check_filetype_and_ext() determined the filename was incorrect.
		if ( $proper_filename ) {
			$file_name = $proper_filename;
		}

		if ( ( ! $type || ! $ext ) && ! current_user_can( 'unfiltered_upload' ) ) {
			return new WP_Error( 'import_file_error', 'Sorry, this file type is not permitted for security reasons.' );
		}

		$uploads = wp_upload_dir( $post['post_date'] );
		if ( ! ( $uploads && false === $uploads['error'] ) ) {
			return new WP_Error( 'upload_dir_error', $uploads['error'] );
		}

		// Move the file to the uploads dir.
		$file_name     = wp_unique_filename( $uploads['path'], $file_name );
		$new_file      = $uploads['path'] . "/$file_name";
		$move_new_file = copy( $tmp_file_name, $new_file );

		if ( ! $move_new_file ) {
			wp_delete_file( $tmp_file_name );
			return new WP_Error( 'import_file_error', 'The uploaded file could not be moved' );
		}

		// WP_Filesystem.
		$wpfs = Codevz_Plus::wpfs();

		// Set correct file permissions.
		$stat  = stat( dirname( $new_file ) );
		$perms = $stat['mode'] & 0000666;
		$wpfs->chmod( $new_file, $perms );

		$upload = array(
			'file'  => $new_file,
			'url'   => $uploads['url'] . "/$file_name",
			'type'  => $wp_filetype['type'],
			'error' => false,
		);

		return $upload;
	}

	/**
	 * Retrieves file extension by mime type.
	 *
	 * @since 0.7.0
	 *
	 * @param string $mime_type Mime type to search extension for.
	 * @return string|null File extension if available, or null if not found.
	 */
	protected function get_file_extension_by_mime_type( $mime_type ) {
		static $map = null;

		if ( is_array( $map ) ) {
			return isset( $map[ $mime_type ] ) ? $map[ $mime_type ] : null;
		}

		$mime_types = wp_get_mime_types();
		$map        = array_flip( $mime_types );

		// Some types have multiple extensions, use only the first one.
		foreach ( $map as $type => $extensions ) {
			$map[ $type ] = strtok( $extensions, '|' );
		}

		return isset( $map[ $mime_type ] ) ? $map[ $mime_type ] : null;
	}

	/**
	 * Parses filename from a Content-Disposition header value.
	 */
	protected function get_filename_from_disposition( $disposition_header ) {
		// Get the filename.
		$filename = null;

		foreach ( $disposition_header as $value ) {
			$value = trim( $value );

			if ( strpos( $value, ';' ) === false ) {
				continue;
			}

			list( $type, $attr_parts ) = explode( ';', $value, 2 );

			$attr_parts = explode( ';', $attr_parts );
			$attributes = array();

			foreach ( $attr_parts as $part ) {
				if ( strpos( $part, '=' ) === false ) {
					continue;
				}

				list( $key, $value ) = explode( '=', $part, 2 );

				$attributes[ trim( $key ) ] = trim( $value );
			}

			if ( empty( $attributes['filename'] ) ) {
				continue;
			}

			$filename = trim( $attributes['filename'] );

			// Unquote quoted filename, but after trimming.
			if ( substr( $filename, 0, 1 ) === '"' && substr( $filename, -1, 1 ) === '"' ) {
				$filename = substr( $filename, 1, -1 );
			}
		}

		return $filename;
	}

	/**
	 * Use stored mapping information to update old attachment URLs
	 */
	public function backfill_attachment_urls( $from_url, $to_url ) {

		$db = Codevz_Plus::database();

		$from_url = self::replace_demo_link( $from_url );

		$db->query(
			$db->prepare(
				"UPDATE {$db->posts} SET post_content = REPLACE(post_content, %s, %s)",
				$from_url, $to_url
			)
		);

		$db->query(
			$db->prepare(
				"UPDATE {$db->postmeta} SET meta_value = REPLACE(meta_value, %s, %s)",
				$from_url, $to_url
			)
		);

		$db->query(
			$db->prepare(
				"UPDATE {$db->postmeta} SET meta_value = REPLACE(meta_value, %s, %s)",
				str_replace( '/', '\/', $from_url ), $to_url
			)
		);

	}

}

Codevz_Demo_Importer::instance();
