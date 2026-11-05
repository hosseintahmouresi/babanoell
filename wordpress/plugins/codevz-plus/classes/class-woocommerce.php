<?php if ( ! defined( 'ABSPATH' ) ) {exit;} // Exit if accessed directly.

/**
 * Woocommerce functions.
 */

// AJAX filters for sidebar widgets.
// Insert nonce into filters div
function codevz_insert_selected_filters_div() {
	echo '<div class="codevz-plus-shop-ajax-filters" data-nonce="' . esc_attr(wp_create_nonce('codevz_plus_shop_ajax_nonce')) . '"><i class="fa czico-Icon-Action-Filter"></i><div></div></div>';
}
//add_action('woocommerce_before_shop_loop', 'codevz_insert_selected_filters_div', 100);
//add_action('wp_ajax_filter_products_by_price', 'ajax_filter_products_by_price');
//add_action('wp_ajax_nopriv_filter_products_by_price', 'ajax_filter_products_by_price');

// AJAX handler for price filter
function ajax_filter_products_by_price() {

	// Nonce verification
	if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'codevz_plus_shop_ajax_nonce')) {
		wp_send_json_error(['error' => 'Invalid security token.'], 403);
	}

	// Sanitize input
	$min_price = floatval($_POST['min_price']);
	$max_price = floatval($_POST['max_price']);
	$paged     = max(1, intval($_POST['page'] ? $_POST['page'] : 1));
	$per_page  = 12;

	// Query products
	$args = [
		'post_type'      => 'product',
		'post_status'    => 'publish',
		'posts_per_page' => $per_page,
		'offset'         => ($paged - 1) * $per_page,
		'meta_query'     => [
			[
				'key'     => '_price',
				'value'   => [$min_price, $max_price],
				'compare' => 'BETWEEN',
				'type'    => 'NUMERIC',
			],
		],
		'fields' => 'ids',
	];

	$product_ids = get_posts($args);

	// Pagination
	$total = count(get_posts(array_merge($args, ['posts_per_page' => -1])));
	$max_pages = ceil($total / $per_page);

	// Output product list
	ob_start();
	foreach ($product_ids as $id) {
		setup_postdata($post = get_post($id));
		wc_get_template_part('content', 'product');
	}
	$products_html = ob_get_clean();
	wp_reset_postdata();

	// Output pagination
	$pagination_html = '';
	if ($max_pages > 1) {
		$pagination_html = '<nav class="woocommerce-pagination"><ul class="page-numbers">';
		for ($i = 1; $i <= $max_pages; $i++) {
			$pagination_html .= sprintf(
				'<li><a class="page-numbers%s" href="#" data-page="%d">%d</a></li>',
				$i === $paged ? ' current' : '', $i, $i
			);
		}
		$pagination_html .= '</ul></nav>';
	}

	// Send response
	wp_send_json([
		'products'   => $products_html,
		'pagination' => $pagination_html,
	]);
}







// Core class.
class Codevz_Plus_Woocommerce {

	use Codevz_Plus_Instance;

	// Get product ID before load product.
	protected $singular_product_id = null;

	// Init.
	public function __construct() {

		// WooCommerce init.
		add_action( 'woocommerce_init', [ $this, 'woocommerce_init' ], 11 );

		// Products search result page.
		add_filter( 'posts_search', [ $this, 'posts_search' ], 10, 2 );
		add_action( 'pre_get_posts', [ $this, 'pre_get_posts' ] );

	}

	/**
	 * Init WooCommerce actions and filters.
	 * 
	 * @return -
	 */
	public function woocommerce_init() {

		// Register size guide post type.
		register_post_type( 'codevz_size_guide',
			[
				'public' 	=> true,
				'label' 	=> esc_html__( 'Size guide', 'codevz-plus' ),
				'supports' 	=> [ 'title', 'editor' ],
				'rewrite' 	=> [ 'slug' => 'product-size-guide' ]
			]
		);

		// Register FAQ post type.
		register_post_type( 'codevz_faq',
			[
				'public' 	=> true,
				'label' 	=> esc_html__( 'FAQ', 'codevz-plus' ),
				'supports' 	=> [ 'title', 'editor' ],
				'rewrite' 	=> [ 'slug' => 'product-faq' ]
			]
		);

		// Register brands taxonomy.
		register_taxonomy( 'codevz_brands', 'product', [
			'labels' => [
				'name' 			=> esc_html__( 'Brands', 'codevz-plus' ),
				'singular_name' => esc_html__( 'Brand', 'codevz-plus' )
			],
			'hierarchical'               => true,
			'public'                     => false,
			'show_ui'                    => false,
			'show_admin_column'          => false,
			'show_in_nav_menus'          => false,
			'show_tagcloud'              => false,
			'rewrite'					 => [ 'slug' => 'product-brand' ]
		] );

		// Temporary migration for old users.
		if ( ! get_option( 'codevz_brands_migration_1' ) ) {
		    update_option( 'codevz_brands_migration_1', 1 );
			$old_taxonomy = 'codevz_brands';
			$new_taxonomy = 'product_brand';
			$terms = get_terms(['taxonomy' => $old_taxonomy, 'hide_empty' => false]);
			if ( ! empty($terms) && ! is_wp_error($terms)) {
				foreach ($terms as $term) {
				    $existing_term = term_exists($term->name, $new_taxonomy);

				    if (is_array($existing_term) && isset($existing_term['term_id'])) {
				        $new_term_id = $existing_term['term_id'];
				    } else {
				        $insert = wp_insert_term($term->name, $new_taxonomy, [
				            'slug'        => $term->slug,
				            'description' => $term->description
				        ]);
				        if (is_wp_error($insert)) { continue; }
				        $new_term_id = $insert['term_id'];
				    }

				    $brand_logo = get_term_meta($term->term_id, 'codevz_brands', true);
				    $brand_logo = empty($brand_logo['brand_logo']) ? '' : absint($brand_logo['brand_logo']);
				    if ($brand_logo) {
				        update_term_meta($new_term_id, 'thumbnail_id', $brand_logo);
				    }

				    $products = get_posts([
				        'post_type'      => 'product',
				        'posts_per_page' => -1,
				        'tax_query'      => [['taxonomy' => $old_taxonomy, 'field' => 'term_id', 'terms' => $term->term_id]],
				    ]);
				    foreach ($products as $product) {
				        wp_set_object_terms($product->ID, $new_term_id, $new_taxonomy, true);
				    }
				}
			}
	    }

		// Product brand and other tabs in single product page.
		add_filter( 'woocommerce_product_tabs', [ $this, 'product_tabs' ] );

		// Enqueue fragments JS.
		add_action( 'wp_enqueue_scripts', [ $this, 'wp_enqueue_scripts' ], 11 ); 

		// Number of products per page & columns.
		if ( ! class_exists( 'Woocommerce_Products_Per_Page' ) ) {

			add_filter( 'loop_shop_columns', [ $this, 'columns' ], 11 );
			add_filter( 'loop_shop_per_page', [ $this, 'loop_shop_per_page' ], 101, 1 );
			add_filter( 'woocommerce_product_query', [ $this, 'products_per_page' ], 11 );

			// Number of products browser request.
			$products_per_page = Codevz_Plus::_POST( 'ppp' );

			if ( $products_per_page ) {
				wc_setcookie( 'woocommerce_products_per_page', intval( $products_per_page ), time() + DAY_IN_SECONDS * 2, apply_filters( 'wc_session_use_secure_cookie', false ) );
			}

			// Show products per page dropdown.
			add_action( 'woocommerce_before_shop_loop', [ $this, 'products_per_page_dropdown' ], 99 );

		}

		// Disable search redirect.
		add_filter( 'woocommerce_redirect_single_search_result', '__return_false' );

		// Free shipping process bar.
		add_action( 'woocommerce_review_order_before_payment', [ $this, 'free_shipping_bar' ], 5 );
		add_action( 'woocommerce_proceed_to_checkout', [ $this, 'free_shipping_bar' ], 5 );

		// AJAX mini cart content.
		add_filter( 'woocommerce_add_to_cart_fragments', [ $this, 'cart' ], 11, 1 );

		// Number of  related products per page.
		add_filter( 'woocommerce_upsell_display_args', [ $this, 'related_products' ], 11 );
		add_filter( 'woocommerce_output_related_products_args', [ $this, 'related_products' ], 11 );

		// Customize products HTML and add quickview and wihlist.
		add_filter( 'woocommerce_post_class', [ $this, 'product_classes' ], 10, 2 );
		add_action( 'woocommerce_after_add_to_cart_button', [ $this, 'single_icons' ], 20 );
		add_action( 'woocommerce_before_shop_loop_item_title', [ $this, 'woocommerce_before_shop_loop_item_title_low' ], 9 );
		add_action( 'woocommerce_before_shop_loop_item_title', [ $this, 'woocommerce_before_shop_loop_item_title_high' ], 11 );

		// Single Wrap.
		add_action( 'woocommerce_before_single_product_summary', [ $this, 'before_single' ], 11 );
		add_action( 'woocommerce_after_single_product_summary', [ $this, 'after_single' ], 1 );

		// Recently viewed products.
		add_action( 'woocommerce_after_single_product', [ $this, 'recently_viewed_products' ], 20 );

		// Quickview AJAX function.
		add_action( 'wp_ajax_xtra_quick_view', [ $this, 'quickview' ] );
		add_action( 'wp_ajax_nopriv_xtra_quick_view', [ $this, 'quickview' ] );

		// Get wishlist & compare page content via AJAX.
		add_action( 'wp_ajax_xtra_wishlist_content', [ $this, 'wishlist_content' ] );
		add_action( 'wp_ajax_nopriv_xtra_wishlist_content', [ $this, 'wishlist_content' ] );
		add_action( 'wp_ajax_xtra_compare_content', [ $this, 'compare_content' ] );
		add_action( 'wp_ajax_nopriv_xtra_compare_content', [ $this, 'compare_content' ] );

		// Wishlist shortcode.
		add_shortcode( 'cz_wishlist', [ $this, 'wishlist_shortcode' ] );

		// Compare shortcode.
		add_shortcode( 'cz_compare', [ $this, 'compare_shortcode' ] );

		// Quickview popup content.
		add_filter( 'woocommerce_product_loop_end', [ $this, 'popup' ] );

		// Modify checkout page.
		add_action( 'woocommerce_checkout_after_customer_details', [ $this, 'checkout_order_review_before' ] );
		add_action( 'woocommerce_checkout_after_order_review', [ $this, 'checkout_order_review_after' ] );

		// Add back to store button on WooCommerce cart page.
		add_action( 'woocommerce_cart_actions', [ $this, 'continue_shopping' ] );

		// Out of stock badge.
		add_action( 'woocommerce_after_shop_loop_item_title', [ $this, 'out_of_stock' ] );

		// Remove product description h2 tab.
		add_filter( 'woocommerce_product_description_heading', '__return_null' );

		// Modify products title and add category name.
		if ( ! is_admin() && ( Codevz_Plus::option( 'woo_category_under_title' ) || is_customize_preview() ) ) {
			add_filter( 'the_title', [ $this, 'category_under_title' ], 10, 2 );
		}

		// Sale badge percentage. 
		if ( Codevz_Plus::option( 'woo_sale_percentage' ) ) {
			add_filter( 'woocommerce_sale_flash', [ $this, 'percentage_sale_flash' ], 10, 3 );
		}

		// Custom Badges.
		if ( ! Codevz_Plus::$is_free ) {
			add_filter( 'woocommerce_after_shop_loop_item_title', [ $this, 'custom_badges' ], 11 );
			add_filter( 'woocommerce_before_single_product_summary', [ $this, 'custom_badges' ], 11 );
		}

		// Single product page countdown.
		add_action( 'woocommerce_before_single_product_summary', [ $this, 'countdown' ], 1 );

		// Taxonomy add meta box.
		add_action( 'add_meta_boxes', [ $this, 'add_meta_boxes' ] );

		// Save post action to update taxonomy fields.
		add_action( 'save_post_product', [ $this, 'save_post_product' ] );

		// Post state for compare and wishlist in admin.
		add_filter( 'display_post_states', [ $this, 'display_post_states' ], 10, 2 );

		// Move new post types menu under products.
		add_action( 'admin_menu', [ $this, 'admin_menu' ] );

		// Remove duplicated custom woo pages.
		add_action( 'admin_init', [ $this, 'admin_init' ] );

		// Add cart empty SVG icon into cart page when its empty.
		remove_action( 'woocommerce_cart_is_empty', 'wc_empty_cart_message', 10 ); // remove
		add_action( 'woocommerce_cart_is_empty', [ $this, 'cart_empty_svg' ], 10 ); // add

		// Stock label. add future v6.
		//remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_price', 10 );
		//add_action( 'woocommerce_single_product_summary', [ $this, 'single_stock_label' ], 11 );
		//add_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_price', 12 );

		// Customize product meta HTML.
		remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_meta', 40 );
		add_action( 'woocommerce_single_product_summary', [ $this, 'product_meta' ], 40 );

		// Attribute variations settings and output.
		add_action( 'woocommerce_after_add_attribute_fields', [ $this, 'attribute_add_fields' ] );
		add_action( 'woocommerce_after_edit_attribute_fields', [ $this, 'attribute_add_fields' ] );
		add_action( 'woocommerce_attribute_added', [ $this, 'attribute_added_updated' ], 10, 2 );
		add_action( 'woocommerce_attribute_updated', [ $this, 'attribute_added_updated' ], 10, 2 );$attributes = (array) wc_get_attribute_taxonomies();

		$attributes = (array) wc_get_attribute_taxonomies();

		// Variations colorpicker.
		if ( ! empty( $attributes ) ) {

			foreach( $attributes as $attribute ) {

				add_action( 'pa_' . $attribute->attribute_name . '_edit_form_fields', [ $this, 'add_variation_colorpicker' ] );
				add_action( 'pa_' . $attribute->attribute_name . '_add_form_fields',  [ $this, 'add_variation_colorpicker' ] );

				add_filter( 'manage_edit-pa_' . $attribute->attribute_name . '_columns',  [ $this, 'add_variation_color_column' ], 1 );
				add_action( 'manage_pa_' . $attribute->attribute_name . '_custom_column', [ $this, 'show_variation_color_column' ], 1, 3 );

			}

		}

		// Variations save colorpicker.
		add_action( 'edit_term', [ $this, 'save_variation_colorpicker' ], 10, 3 );
		add_action( 'created_term', [ $this, 'save_variation_colorpicker' ], 10, 3 );

		// Variations output HTML.
		add_filter( 'woocommerce_dropdown_variation_attribute_options_html', [ $this, 'variations_output' ], 10, 2 );

		// Cart page related products according to cart items.
		if ( Codevz_Plus::option( 'woo_cart_page_related_products' ) && ! Codevz_Plus::$is_free ) {

			add_action( 'woocommerce_after_cart_totals', [ $this, 'cart_related_products' ] );
			add_action( 'woocommerce_cart_is_empty', [ $this, 'cart_empty_products_section' ] );

		}

		// Show 3 steps above on cart, checkout and order completion pages.
		if ( Codevz_Plus::option( 'woo_cart_checkout_steps' ) && ! Codevz_Plus::$is_free ) {

			add_action( 'woocommerce_before_cart', [ $this, 'cart_checkout_steps' ], 1 );
			add_action( 'woocommerce_before_checkout_form', [ $this, 'cart_checkout_steps' ], 1 );

		}

		// Avatar above the account nav.
		add_filter( 'woocommerce_before_account_navigation', [ $this, 'my_account_avatar' ] );

		// Fix new links in my account nav.
		$this->my_account_endpoints();

		// Add a new menus to my account page.
		add_filter( 'woocommerce_account_menu_items', [ $this, 'my_account_menus' ] );

		// My account menus content.
		add_action( 'woocommerce_account_wishlist_endpoint', [ $this, 'my_account_wishlist' ] );
		add_action( 'woocommerce_account_viewed_endpoint',   [ $this, 'my_account_viewed' ] );
		add_action( 'woocommerce_account_reviews_endpoint',  [ $this, 'my_account_reviews' ] );
		add_action( 'woocommerce_account_tracking_endpoint', [ $this, 'my_account_tracking' ] );
		add_action( 'woocommerce_before_customer_login_form', [ $this, 'before_my_account_form' ], 9 );
		add_action( 'woocommerce_after_customer_login_form', [ $this, 'after_my_account_form' ], 9 );
		add_action( 'woocommerce_before_lost_password_form', [ $this, 'before_my_account_form' ], 9 );
		add_action( 'woocommerce_after_lost_password_form', [ $this, 'after_my_account_form' ], 9 );

		// Product gallery navigation arrows.
		add_filter( 'woocommerce_single_product_carousel_options', [ $this, 'woo_flexslider_options' ] );

		// Add short description under product rating on shop archive pages
		add_action( 'woocommerce_after_shop_loop_item_title', [ $this, 'products_short_desc' ], 4 );

		// Move outofstock products to the end of loop.
		add_filter( 'posts_clauses', [ $this, 'move_outofstock_end_of_loop' ], 99 );

		// Change product page title tag to h2.
		remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_title', 5 );
		if ( Codevz_Plus::option( 'woo_product_title_tag' ) !== 'hide' ) {
			add_action( 'woocommerce_single_product_summary', [ $this, 'add_new_product_title_tag' ], 5 );
		}

		// Track live carts and product viewers count.
		add_action( 'woocommerce_single_product_summary', [ $this, 'log_sessions_script' ], 35 );
		add_action( 'woocommerce_add_to_cart', [ $this, 'track_cart_addition' ], 10, 6 );
		add_action( 'woocommerce_cart_item_removed', [ $this, 'track_cart_removal' ], 10, 2 );
		add_action( 'wp_ajax_codevz_woo_sessions', [ $this, 'codevz_woo_sessions' ] );
		add_action( 'wp_ajax_nopriv_codevz_woo_sessions', [ $this, 'codevz_woo_sessions' ] );
		add_action( 'wp_ajax_codevz_woo_live_cart', [ $this, 'codevz_woo_live_cart' ] );
		add_action( 'wp_ajax_nopriv_codevz_woo_live_cart', [ $this, 'codevz_woo_live_cart' ] );

		// Add phone assistance box after Add to Cart and before Product Meta
		add_action( 'woocommerce_single_product_summary', [ $this, 'phone_assistance' ], 36 );

		// Prevent slug changes for wishlist and compare pages.
		add_filter( 'wp_insert_post_data', [ $this, 'prevent_slug_change' ], 10, 2 );

		// Cart page product SKU under title.
		add_action( 'woocommerce_after_cart_item_name', [ $this, 'cart_product_sku' ], 11, 2 );

		// Add more options to orderby shop items.
		add_action( 'save_post', [ $this, 'calculate_and_store_discount_percentage' ] );
		add_action( 'woocommerce_update_product', [ $this, 'calculate_and_store_discount_percentage' ] );
		add_filter( 'woocommerce_default_catalog_orderby_options', [ $this, 'woocommerce_catalog_orderby' ] );
		add_filter( 'woocommerce_catalog_orderby', [ $this, 'woocommerce_catalog_orderby' ] );
		add_filter( 'woocommerce_get_catalog_ordering_args', [ $this, 'woocommerce_get_catalog_ordering_args' ], 10, 3 );

		// Archive columns grid icons.
		add_action( 'woocommerce_before_shop_loop', [ $this, 'shop_columns_grid_icons' ], 100 );

		// Change archive title to H2
		add_filter( 'woocommerce_show_page_title', '__return_false' );
		add_action( 'woocommerce_archive_description', [ $this, 'archive_title_tag' ], 1 );

		// Remove default Woo tax description.
		remove_action( 'woocommerce_archive_description', 'woocommerce_taxonomy_archive_description', 10 );

		// Archive tax description after/before products.
		if ( Codevz_Plus::option( 'woo_desc_below' ) ) {
			add_action( 'woocommerce_after_shop_loop', [ $this, 'term_description' ], 11 );
		} else {
			add_action( 'woocommerce_archive_description', [ $this, 'term_description' ], 11, 2 );
		}

		// Change add to cart text on single product page
		if ( Codevz_Plus::option( 'woo_add_to_cart_title' ) ) {

			add_filter( 'woocommerce_product_add_to_cart_text', [ $this, 'add_to_cart_title' ], 11 ); 
			add_filter( 'woocommerce_product_single_add_to_cart_text', [ $this, 'add_to_cart_title' ], 11 ); 

		}

		// Wailist mailing system for out of stock products.
        add_action( 'woocommerce_single_product_summary', [ $this, 'waitlist_box' ], 35 );
        add_action( 'wp_ajax_codevz_waitlist', [ $this, 'waitlist_handle' ] );
        add_action( 'wp_ajax_nopriv_codevz_waitlist', [ $this, 'waitlist_handle' ] );
        add_action( 'woocommerce_product_set_stock_status', [ $this, 'waitlist_send_emails' ], 10, 3 );

	}

	/**
	 * Onsale badge percentage
	 * 
	 * @return string
	 */
	public function percentage_sale_flash( $html, $post, $product ) {

		if ( $product->is_on_sale() ) {

			if ( $product->is_type( 'variable' ) ) {

				$regular_price 	= (int) $product->get_variation_regular_price( 'max' );
				$sale_price 	= (int) $product->get_variation_sale_price( 'min' );

			} else {

				$regular_price 	= (int) $product->get_regular_price();
				$sale_price 	= (int) $product->get_sale_price();

			}

			if ( $regular_price && $sale_price ) {

				// Calculate the percentage discount
				$percentage = round( ( ( $regular_price - $sale_price ) / $regular_price ) * 100 );

				$option = Codevz_Plus::option( 'woo_sale_percentage' );

				// Customize the HTML output to display the percentage discount
				if ( $option === 'woo-sale-percentage' ) {
					$html = '<span class="onsale codevz-woo-percentage">' . esc_html( $percentage ) . '%</span>';
				} else if ( $option === 'woo-sale-text-percentage' ) {
					$html .= '<span class="onsale codevz-woo-text-percentage">' . esc_html( $percentage ) . '%</span>';
				}

			}

		}

		return $html;

	}

	/**
	 * Showing custom and NEW badge on product image.
	 * 
	 * @return string
	 */
	public function custom_badges( $onsale ) {

		$product_id  = get_the_id();
		$custom_badge = get_post_meta( $product_id, 'codevz_custom_badge', true );

		if ( $custom_badge ) {
			echo '<span class="onsale codevz-woo-custom-badge">' . esc_html( $custom_badge ) . '</span>';
		}

		// NEW badge.
		if ( Codevz_Plus::option( 'woo_new_label' ) ) {

			$days = intval( abs( current_time( 'timestamp' ) - get_the_time( 'U', get_the_ID() ) ) / 86400 );

			if ( $days <= Codevz_Plus::option( 'woo_new_label_days', 1 ) ) {
				echo '<span class="onsale cz_new_badge">' . esc_html__( 'NEW', 'codevz-plus' ) . '</span>';
			}

		}

	}

	/**
	 * Run cart JS fragments on all pages for mini cart.
	 * 
	 * @return string
	 */
	public function wp_enqueue_scripts() {

		wp_enqueue_script( 'wc-cart-fragments' );

	}

	/**
	 * Change cart empty message with SVG icon.
	 * 
	 * @return string
	 */
	public function cart_empty_svg( $only_svg = true ) {

		echo '<svg class="codevz-cart-empty-svg" fill="#676767" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 231.523 231.523" xml:space="preserve">
				<g>
					<path d="M107.415,145.798c0.399,3.858,3.656,6.73,7.451,6.73c0.258,0,0.518-0.013,0.78-0.04c4.12-0.426,7.115-4.111,6.689-8.231
						l-3.459-33.468c-0.426-4.12-4.113-7.111-8.231-6.689c-4.12,0.426-7.115,4.111-6.689,8.231L107.415,145.798z"/>
					<path d="M154.351,152.488c0.262,0.027,0.522,0.04,0.78,0.04c3.796,0,7.052-2.872,7.451-6.73l3.458-33.468
						c0.426-4.121-2.569-7.806-6.689-8.231c-4.123-0.421-7.806,2.57-8.232,6.689l-3.458,33.468
						C147.235,148.377,150.23,152.062,154.351,152.488z"/>
					<path d="M96.278,185.088c-12.801,0-23.215,10.414-23.215,23.215c0,12.804,10.414,23.221,23.215,23.221
						c12.801,0,23.216-10.417,23.216-23.221C119.494,195.502,109.079,185.088,96.278,185.088z M96.278,216.523
						c-4.53,0-8.215-3.688-8.215-8.221c0-4.53,3.685-8.215,8.215-8.215c4.53,0,8.216,3.685,8.216,8.215
						C104.494,212.835,100.808,216.523,96.278,216.523z"/>
					<path d="M173.719,185.088c-12.801,0-23.216,10.414-23.216,23.215c0,12.804,10.414,23.221,23.216,23.221
						c12.802,0,23.218-10.417,23.218-23.221C196.937,195.502,186.521,185.088,173.719,185.088z M173.719,216.523
						c-4.53,0-8.216-3.688-8.216-8.221c0-4.53,3.686-8.215,8.216-8.215c4.531,0,8.218,3.685,8.218,8.215
						C181.937,212.835,178.251,216.523,173.719,216.523z"/>
					<path d="M218.58,79.08c-1.42-1.837-3.611-2.913-5.933-2.913H63.152l-6.278-24.141c-0.86-3.305-3.844-5.612-7.259-5.612H18.876
						c-4.142,0-7.5,3.358-7.5,7.5s3.358,7.5,7.5,7.5h24.94l6.227,23.946c0.031,0.134,0.066,0.267,0.104,0.398l23.157,89.046
						c0.86,3.305,3.844,5.612,7.259,5.612h108.874c3.415,0,6.399-2.307,7.259-5.612l23.21-89.25C220.49,83.309,220,80.918,218.58,79.08z
						 M183.638,165.418H86.362l-19.309-74.25h135.895L183.638,165.418z"/>
					<path d="M105.556,52.851c1.464,1.463,3.383,2.195,5.302,2.195c1.92,0,3.84-0.733,5.305-2.198c2.928-2.93,2.927-7.679-0.003-10.607
						L92.573,18.665c-2.93-2.928-7.678-2.927-10.607,0.002c-2.928,2.93-2.927,7.679,0.002,10.607L105.556,52.851z"/>
					<path d="M159.174,55.045c1.92,0,3.841-0.733,5.306-2.199l23.552-23.573c2.928-2.93,2.925-7.679-0.005-10.606
						c-2.93-2.928-7.679-2.925-10.606,0.005l-23.552,23.573c-2.928,2.93-2.925,7.679,0.005,10.607
						C155.338,54.314,157.256,55.045,159.174,55.045z"/>
					<path d="M135.006,48.311c0.001,0,0.001,0,0.002,0c4.141,0,7.499-3.357,7.5-7.498l0.008-33.311c0.001-4.142-3.356-7.501-7.498-7.502
						c-0.001,0-0.001,0-0.001,0c-4.142,0-7.5,3.357-7.501,7.498l-0.008,33.311C127.507,44.951,130.864,48.31,135.006,48.311z"/>
				</g>
			</svg>';

		if ( ! $only_svg ) {

			echo '<div class="codevz-cart-is-empty"><h2>' . esc_html__( 'Looks like your cart is empty!', 'codevz-plus' ) . '</h2><span>' . esc_html__( 'Time to start your shopping', 'codevz-plus' ) . '</span></div>';

		}

	}

	/**
	 * Free shipping process bar for mini cart, cart, checkout pages.
	 * 
	 * @return string
	 */
	public function free_shipping_bar() {

		if ( Codevz_Plus::option( 'woo_free_shipping_bar' ) ) {

			$threshold = Codevz_Plus::option( 'woo_free_shipping_bar_threshold', 0 );
			$threshold = (int) filter_var( $threshold, FILTER_SANITIZE_NUMBER_INT );
			$cart_total = WC()->cart->subtotal;

			$remaining = $threshold - $cart_total;
			$percent = min( 100, ( $cart_total / $threshold) * 100 );
			$percent = max( $percent, 5 );

			if ( $remaining > 0 ) {

				echo '<div class="codevz-plus-free-shipping-bar">';
				echo '<span><i class="fas fa-dolly mr10"></i> ' . sprintf( esc_html__( 'Add %s more to get free shipping!', 'codevz-plus' ), '<strong>' . wc_price( $remaining ) . '</strong>' ) . '</span>';
				echo '<div><div style="width:' . esc_attr( $percent ) . '%;"></div></div>';
				echo '</div>';

			}
			
		}

	}

	/**
	 * Get WooCommerce cart in header.
	 * 
	 * @return string
	 */
	public function cart( $fragments ) {

		$wc = WC();
		$count = $wc->cart->cart_contents_count;
		$total = $wc->cart->get_cart_total();

		ob_start(); ?>
			<div class="cz_cart">
				<?php if ( $count > 0 || Codevz_Plus::option( 'woo_show_zero_count' ) ) { ?>
				<span class="cz_cart_count"><?php echo esc_html( $count ); ?></span>
				<?php } ?>
				<div class="cz_cart_items"><div>
					<?php if ( $wc->cart->cart_contents_count == 0 ) { ?>
						<div class="cart_list">

							<div class="item_small xtra-empty-cart">

								<?php $this->cart_empty_svg( true ); ?>

								<?php echo '<span>' . esc_html( Codevz_Plus::option( 'woo_no_products', esc_html__( "Cart's empty! Let's fill it up!", 'codevz-plus' ) ) ); ?></span>

							</div>

						</div>
					<?php $fragments['.cz_cart'] = ob_get_clean(); return $fragments; } else { ?>
						<div class="cart_list">

							<?php foreach( $wc->cart->cart_contents as $cart_item_key => $cart_item ) {
								$id = $cart_item['product_id'];
								$_product = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );
							?>
								<div class="item_small">
									<a href="<?php echo esc_url( get_permalink( $id ) ); ?>">
										<?php echo wp_kses_post( (string) apply_filters( 'woocommerce_cart_item_thumbnail', $_product->get_image( 'codevz_600_600' ), $cart_item, $cart_item_key ) ); ?>
									</a>
									<div class="cart_list_product_title cz_tooltip_up">
										<h3><a href="<?php echo esc_url( get_permalink( $id ) ); ?>"><?php echo wp_kses_post( (string) get_the_title( $id ) ); ?></a></h3>
										<div class="cart_list_product_quantity"><?php echo wp_kses_post( (string) $cart_item['quantity'] ); ?> x <?php echo wp_kses_post( (string) $wc->cart->get_product_subtotal( $cart_item['data'], 1 ) ); ?> </div>
										<a href="<?php echo esc_url( wc_get_cart_remove_url( $cart_item_key ) ); ?>" class="remove" data-product_id="<?php echo esc_attr( $id ); ?>" data-title="<?php echo esc_attr__( 'Remove', 'codevz-plus' ); ?>"><i class="fa czico-198-cancel"></i></a>
									</div>
								</div>
							<?php } ?>
							</div>

							<div class="cz_cart_buttons clr">
								<a href="<?php echo esc_url( get_permalink( get_option('woocommerce_cart_page_id') ) ); ?>"><i class="fas fa-receipt"></i> <?php echo esc_html( do_shortcode( Codevz_Plus::option( 'woo_cart', esc_html__( 'Cart subtotal', 'codevz-plus' ) ) ) ); ?> <span><?php echo wp_kses_post( (string) $wc->cart->get_cart_total() ); ?></span></a>
							</div>
						<?php } ?>
					</div>

					<?php $this->free_shipping_bar(); ?>

					<a class="button" href="<?php echo esc_url( get_permalink( get_option('woocommerce_checkout_page_id') ) ); ?>"><?php echo esc_html( do_shortcode( Codevz_Plus::option( 'woo_checkout', esc_html__( 'Process to Checkout', 'codevz-plus' ) ) ) ); ?></a>

					<?php if ( Codevz_Plus::option( 'woo_cart_footer' ) ) { ?>
						<span class="cz_cart_footer"><?php echo esc_html( Codevz_Plus::option( 'woo_cart_footer' ) ); ?></span>
					<?php } ?>

				</div>
			</div>
		<?php 

		$fragments['.cz_cart'] = ob_get_clean();

		return $fragments;
	}

	/**
	 * WooCommerce products columns
	 * 
	 * @return string
	 */
	public function columns() {

		$site_id = ( function_exists( 'is_multisite' ) && is_multisite() ) ? get_current_blog_id() : '1';

		if ( isset( $_COOKIE[ 'codevz_columns_' . $site_id ] ) && Codevz_Plus::option( 'woo_columns_selector' ) ) {

			return intval( esc_html( $_COOKIE[ 'codevz_columns_' . $site_id ] ) );

		}

		return Codevz_Plus::option( 'woo_col', 4 );

	}

	/**
	 * WooCommerce products per page
	 * 
	 * @return object
	 */
	public function products_per_page( $query ) {

		$query->set( 'posts_per_page', $this->loop_shop_per_page() );

	}

	/**
	 * WooCommerce products per page
	 * 
	 * @return int
	 */
	public function loop_shop_per_page( $per_page = 6 ) {

		$ppp = Codevz_Plus::_POST( 'ppp' );

		$ppp = $ppp ? $ppp : Codevz_Plus::_COOKIE( 'woocommerce_products_per_page' );

		return intval( esc_html( $ppp ? $ppp : Codevz_Plus::option( 'woo_items_per_page', $per_page ) ) );

	}

	/**
	 * WooCommerce excluding variations and attributes from search results.
	 * 
	 * @return string
	 */
	public function posts_search( $search, $query ) {

		if ( $query->is_search() && ! is_admin() ) {

			global $wpdb;

			$search .= " AND {$wpdb->posts}.post_type != 'product_variation'";

		}

		return $search;

	}

	/**
	 * WooCommerce set posts per page for search result page.
	 */
	public function pre_get_posts( $query ) {

		if ( $query->is_search() && ! is_admin() && Codevz_Plus::_GET( 'post_type' ) === 'product' ) {

			$ppp = $this->loop_shop_per_page();

			$query->set( 'showposts', $ppp );
			$query->set( 'posts_per_page', $ppp );

		}

	}

	/**
	 * WooCommerce show products per page dropdown.
	 * 
	 * @return string
	 */
	public function products_per_page_dropdown() {

		// Only show on product categories
		if ( ! Codevz_Plus::option( 'woo_ppp_dropdown' ) || ! woocommerce_products_will_display() ) {
			return;
		}

		global $wp_query;

		$ppp = $this->loop_shop_per_page( 6 );

		// Set the products per page options.
		if ( $ppp % 2 == 0 ) {
			$numbers = [ 16, 32, 48, 64, 128 ];
		} else {
			$numbers = [ 18, 36, 45, 72, 90 ];
		}

		// Get action URL.
		$cat = $wp_query->get_queried_object();

		if ( isset( $cat->term_id ) && isset( $cat->taxonomy ) ) {
			$action = get_term_link( $cat->term_id, $cat->taxonomy );
		} else {
			$action = get_the_permalink( get_option( 'woocommerce_shop_page_id' ) );
		}

		// Set action url if option behaviour is true, Paste QUERY string after for filter and orderby support
		$query_string = ! empty( $_SERVER['QUERY_STRING'] ) ? '?' . add_query_arg( array( 'ppp' => false ), $_SERVER['QUERY_STRING'] ) : null;
		$action = $action . $query_string;

		?><form method="post" action="<?php echo esc_url( $action ); ?>" class="codevz-products-per-page">
			<select name="ppp" onchange="this.form.submit()">

				<?php

				$default_ppp = Codevz_Plus::option( 'woo_items_per_page', $ppp );

				if ( $numbers[0] > $default_ppp ) {

					echo '<option value="' . esc_attr( $default_ppp ) . '"' . ( in_array( $default_ppp, $numbers ) ? ' selected="selected"' : '' ) . '>' . sprintf( esc_html__( '%d Products', 'codevz-plus' ), esc_html( $default_ppp ) ) . '</option>';

				}

				foreach( $numbers as $key => $value ) :

					?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $value, $ppp ); ?>><?php
						/* translators: %s is replaced with string */
						printf( esc_html__( '%d Products', 'codevz-plus' ), esc_html( $value ) );
					?></option><?php

				endforeach;

			?></select><?php

		?></form><?php

	}

	/**
	 * WooCommerce products per page
	 * 
	 * @return array
	 */
	public function related_products( $args ) {

		$columns = (int) Codevz_Plus::option( 'woo_related_col' );

		$args['columns'] 		= $columns;
		$args['posts_per_page'] = $columns * 2;

		return $args;

	}

	/**
	 * Wishlist container shortcode.
	 * 
	 * @return string
	 */
	public function wishlist_shortcode( $a, $c = '' ) {
		return '<div class="woocommerce xtra-wishlist xtra-icon-loading" data-col="3" data-empty="' . esc_html__( 'Your wishlist list is empty.', 'codevz-plus' ) . '" data-nonce="' . wp_create_nonce( 'xtra_wishlist_content' ) . '"></div>';
	}

	/**
	 * Compare container shortcode.
	 * 
	 * @return string
	 */
	public function compare_shortcode( $a, $c = '' ) {
		return '<div class="woocommerce xtra-compare xtra-icon-loading" data-empty="' . esc_html__( 'Your products compare list is empty.', 'codevz-plus' ) . '" data-nonce="' . wp_create_nonce( 'xtra_compare_content' ) . '"></div>';
	}

	/**
	 * Get wishlist products via AJAX.
	 * 
	 * @return string
	 */
	public function wishlist_content() {

		if ( empty( $_POST['ids'] ) && empty( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'xtra_wishlist_content' ) ) {
			wp_die( '<b>' . esc_html__( 'Server error, Please reload page ...', 'codevz-plus' ) . '</b>' );
		}

		if ( isset( $_POST['check'] ) ) {

			$new = '';

			$ids = explode( ',', $_POST['ids'] );

			foreach( $ids as $id ) {

				if ( $id && $id !== 'undefined' ) {

					$id = str_replace( ' ', '', $id );

					$post = get_post( $id );

					if ( ! empty( $post->post_title ) ) {

						$new .= $id . ',';

					}

				}

			}

			wp_die( esc_html( $new ) );

		}

		$col = isset( $_POST['col'] ) ? $_POST['col'] : '3';

		wp_die( do_shortcode( '[products ids="' . esc_html( $_POST['ids'] ) . '" columns="' . esc_html( $col ) . '"]' ) );

	}

	/**
	 * Get compare products via AJAX.
	 * 
	 * @return string
	 */
	public function compare_content() {

		if ( empty( $_POST['ids'] ) && empty( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'xtra_compare_content' ) ) {
			wp_die( '<b>' . esc_html__( 'Server error, Please reload page ...', 'codevz-plus' ) . '</b>' );
		}

		$out = '';

		if ( isset( $_POST['check'] ) ) {

			$ids = explode( ',', $_POST['ids'] );

			foreach( $ids as $id ) {

				if ( $id && $id !== 'undefined' ) {

					$id = str_replace( ' ', '', $id );

					$post = get_post( $id );

					if ( ! empty( $post->post_title ) ) {

						$out .= $id . ',';

					}

				}

			}

			wp_die( esc_html( $out ) );

		} else {

			$ids = explode( ',', $_POST['ids'] );

			$out .= '<table class="cz-compare cz-compare-col-' . esc_attr( count( $ids ) - 1 ) . '"><tbody>';

			$tr = [

				'general' 			=> [ 'td' => '', 'title' => '' ],
				'price' 			=> [ 'td' => '', 'title' => esc_html__( 'Price', 'codevz-plus' ) ],
				'brand' 			=> [ 'td' => '', 'title' => esc_html__( 'Brand', 'codevz-plus' ) ],
				'desc' 				=> [ 'td' => '', 'title' => esc_html__( 'Description', 'codevz-plus' ) ],
				'sku' 				=> [ 'td' => '', 'title' => esc_html__( 'Product SKU', 'codevz-plus' ) ],
				'availablity' 		=> [ 'td' => '', 'title' => esc_html__( 'Availablity', 'codevz-plus' ) ],
				'sold_individually' => [ 'td' => '', 'title' => esc_html__( 'Individual sale', 'codevz-plus' ) ],
				'tax_status' 		=> [ 'td' => '', 'title' => esc_html__( 'Tax status', 'codevz-plus' ) ],
				'weight' 			=> [ 'td' => '', 'title' => esc_html__( 'Weight', 'codevz-plus' ) ],
				'length' 			=> [ 'td' => '', 'title' => esc_html__( 'Length', 'codevz-plus' ) ],
				'height' 			=> [ 'td' => '', 'title' => esc_html__( 'Height', 'codevz-plus' ) ],
				'width' 			=> [ 'td' => '', 'title' => esc_html__( 'Width', 'codevz-plus' ) ],
				'average_rating' 	=> [ 'td' => '', 'title' => esc_html__( 'Average rating', 'codevz-plus' ) ],
				'review_count' 		=> [ 'td' => '', 'title' => esc_html__( 'Review count', 'codevz-plus' ) ],

			];

			foreach( $ids as $id ) {

				if ( $id && $id !== 'undefined' ) {

					$id = str_replace( ' ', '', $id );

					$product = wc_get_product( $id );

					if ( empty( $product ) ) {
						continue;
					}

					$tr[ 'general' ][ 'td' ] .= '<td><a href="' . get_permalink( $id ) . '">' . $product->get_image() . '<h4 data-id="' . $id . '">' . get_the_title( $id ) . '</h4></a>' . do_shortcode( '[add_to_cart id=' . $id . ' show_price="false"]' ) . '</td>';
					$tr[ 'price' ][ 'td' ] .= '<td>' . $product->get_price_html() . '</td>';

					$brands  = (array) get_the_terms( $id, 'product_brand', true );

					if ( ! empty( $brands[ 0 ]->term_id ) ) {

						$tr[ 'brand' ][ 'td' ] .= '<td>';
						$term_meta = get_term_meta( $brands[ 0 ]->term_id, 'thumbnail_id', true );
						$term_value = empty( $term_meta ) ? $brands[ 0 ]->name : wp_get_attachment_image( $term_meta, 'full' );
						$tr[ 'brand' ][ 'td' ] .= '<a href="' . get_term_link( $brands[ 0 ]->term_id ) . '">' . do_shortcode( $term_value ) . '</a>';
						$tr[ 'brand' ][ 'td' ] .= '</td>';

					} else {
						$tr[ 'brand' ][ 'td' ] .= '';
					}

					$tr[ 'desc' ][ 'td' ] .= $product->get_short_description() ? '<td>' . $product->get_short_description() . '</td>' : '';
					$tr[ 'sku' ][ 'td' ] .= $product->get_sku() ? '<td>' . $product->get_sku() . '</td>' : '';

					// Product stock status.
					$get_stock_status = $product->get_stock_status();
					if ( $get_stock_status === 'instock' ) {
						$get_stock_status = esc_html__( 'Instock', 'codevz-plus' );
					} else if ( $get_stock_status === 'outofstock' ) {
						$get_stock_status = esc_html__( 'Out of stock', 'codevz-plus' );
					}

					$tr[ 'availablity' ][ 'td' ] .= '<td>' . ( ( $product->get_stock_quantity() || ! $product->get_manage_stock() ) ? '<i class="fa fa-check"></i>' : '<i class="fa fa-times"></i>' ) . $product->get_stock_quantity() . ' ' . ucwords( $get_stock_status ) . '</td>';
					$tr[ 'sold_individually' ][ 'td' ] .= '<td>' . ( $product->get_sold_individually() ? '<i class="fa fa-check"></i>' : '<i class="fa fa-times"></i>' ) . '</td>';

					// Product tax status.
					$get_tax_status = $product->get_tax_status();

					if ( $get_tax_status === 'taxable' ) {

						$get_tax_status = esc_html__( 'Taxable', 'codevz-plus' );

					} else if ( $get_tax_status === 'shipping' ) {

						$get_tax_status = esc_html__( 'Shipping only', 'codevz-plus' );

					} else if ( $get_tax_status === 'none' ) {

						$get_tax_status = esc_html__( 'None', 'codevz-plus' );

					}

					$tr[ 'tax_status' ][ 'td' ] .= $get_tax_status ? '<td>' . ( $get_tax_status ? '<i class="fa fa-check"></i>' : '<i class="fa fa-times"></i>' ) . ucwords( $get_tax_status ) . '</td>' : '';

					$tr[ 'weight' ][ 'td' ] .= $product->get_weight() ? '<td>' . $product->get_weight() . ' ' . get_option( 'woocommerce_weight_unit' ) . '</td>' : '<td><i class="fa fa-times"></i></td>';
					$tr[ 'length' ][ 'td' ] .= $product->get_length() ? '<td>' . $product->get_length() . ' ' . get_option( 'woocommerce_dimension_unit' ) . '</td>' : '<td><i class="fa fa-times"></i></td>';
					$tr[ 'height' ][ 'td' ] .= $product->get_height() ? '<td>' . $product->get_height() . ' ' . get_option( 'woocommerce_dimension_unit' ) . '</td>' : '<td><i class="fa fa-times"></i></td>';
					$tr[ 'width' ][ 'td' ]  .= $product->get_width()  ? '<td>' . $product->get_width() . ' ' . get_option( 'woocommerce_dimension_unit' ) . '</td>' : '<td><i class="fa fa-times"></i></td>';
					$tr[ 'average_rating' ][ 'td' ] .= '<td>' . ( $product->get_average_rating() ? '<i class="fa fa-star"></i>' . $product->get_average_rating() : '<i class="fa fa-times"></i>' ) . '</td>';
					$tr[ 'review_count' ][ 'td' ] .= '<td>' . ( $product->get_review_count() ? $product->get_review_count() : '<i class="fa fa-times"></i>' ) . '</td>';

					$all_attributes = [];
					foreach ( $ids as $id ) {
					    $id = trim( $id );
					    if ( empty( $id ) || $id === 'undefined' ) {
					        continue;
					    }
					    $product = wc_get_product( $id );
					    if ( ! $product ) {
					        continue;
					    }
					    foreach ( $product->get_attributes() as $attr ) {
					        $name = $attr->get_name();
					        if ( ! in_array( $name, $all_attributes, true ) ) {
					            $all_attributes[] = $name;
					        }
					    }
					}

					foreach ( $all_attributes as $attr_name ) {
					    $tr[ $attr_name ] = [
					        'title' => wc_attribute_label( $attr_name ),
					        'td' => '',
					    ];
					}

					foreach ( $ids as $id ) {
					    $id = trim( $id );
					    if ( empty( $id ) || $id === 'undefined' ) {
					        continue;
					    }
					    $product = wc_get_product( $id );
					    if ( ! $product ) {
					        continue;
					    }

					    foreach ( $all_attributes as $attr_name ) {
					        $value = $product->get_attribute( $attr_name );

					        // output values or icon if empty
					        $cell = $value ? esc_html( $value ) : '<i class="fa fa-times"></i>';

					        $tr[ $attr_name ]['td'] .= '<td>' . $cell . '</td>';
					    }
					}

				}

			}

			foreach( $tr as $class => $inner ) {

				if ( empty( $inner['td'] ) ) {
					$inner['td'] = '<td><i class="fa fa-times"></i></td>';
				}

				$out .= '<tr class="cz-compare-tr-' . esc_attr( $class ) . '">';

				if ( $class === 'general' ) {
					$inner['title'] = '';
				}

				$out .= '<th>' . esc_html( $inner['title'] ) . '</th>';
				$out .= $inner['td'];
				$out .= '</tr>';

			}

			$out .= '</tbody></table><ul class="hide"><li></li></ul>';

			wp_die( do_shortcode( $out ) );

		}

	}

	/**
	 * Add wishlist icon into single product page.
	 * 
	 * @return string
	 */
	public function single_icons() {

		$product_id  = get_the_id();

		// Display custom button next to Add to Cart button on single product page
		$button_title = get_post_meta( $product_id, 'codevz_custom_button_title', true );
		$button_link  = get_post_meta( $product_id, 'codevz_custom_button_link', true );

		if ( $button_title && $button_link ) {

			echo '<a href="' . esc_url( do_shortcode( $button_link ) ) . '" onclick="window.open(\'' . do_shortcode( esc_url( $button_link ) ) . '\', \'_blank\');return false;" target="_blank" class="codevz-product-second-button button alt">' . esc_html( do_shortcode( $button_title ) ) . '</a>';

		}

		if ( Codevz_Plus::option( 'woo_wishlist' ) ) {

			echo '<div class="xtra-product-icons xtra-product-icons-wishlist cz_tooltip_up" data-id="' . esc_attr( $product_id ) . '">';
			echo '<i class="fa fa-heart-o xtra-add-to-wishlist" data-title="' . esc_html__( 'Add to wishlist', 'codevz-plus' ) . '"></i>';
			echo '</div>';

		}

		if ( Codevz_Plus::option( 'woo_compare' ) ) {

			echo '<div class="xtra-product-icons xtra-product-icons-compare cz_tooltip_up" data-id="' . esc_attr( $product_id ) . '">';
			echo '<i class="fa czico-shuffle xtra-add-to-compare" data-title="' . esc_html__( 'Add to compare', 'codevz-plus' ) . '"></i>';
			echo '</div>';

		}

		echo '<div class="clr"></div>';

	}

	/**
	 * Add custom tabs to product single page tabs.
	 * 
	 * @return Array
	 */
	public function product_tabs( $tabs ) {

		if ( Codevz_Plus::$is_free ) {

			return $tabs;

		}

		// Product ID.
		$product_id = get_the_ID();

		// Size guide tab.
		if ( Codevz_Plus::option( 'woo_product_size_guide_tab' ) ) {

			$size_guide = get_post_meta( $product_id, 'codevz_size_guide', true );
			$size_guide = $size_guide ? $size_guide : Codevz_Plus::option( 'woo_product_size_guide_tab_content' );

			if ( $size_guide ) {

				$tabs[ 'codevz-size-guide' ] = array(
					'title'     => Codevz_Plus::option( 'woo_product_size_guide_tab_title', esc_html__( 'Size guide', 'codevz-plus' ) ),
					'priority'  => 50,
					'callback'  => function() use ( $size_guide ) {

						echo do_shortcode( is_numeric( $size_guide ) ? Codevz_Plus::get_page_as_element( $size_guide ) : $size_guide );

					}

				);

			}

		}

		// FAQ tab.
		if ( Codevz_Plus::option( 'woo_product_faq_tab' ) ) {

			$faq = get_post_meta( $product_id, 'codevz_faq', true );
			$faq = $faq ? $faq : Codevz_Plus::option( 'woo_product_faq_tab_content' );

			if ( $faq ) {

				$tabs[ 'codevz-faq' ] = array(
					'title'     => Codevz_Plus::option( 'woo_product_faq_tab_title', esc_html__( 'FAQ', 'codevz-plus' ) ),
					'priority'  => 60,
					'callback'  => function() use ( $faq ) {

						echo do_shortcode( is_numeric( $faq ) ? Codevz_Plus::get_page_as_element( $faq ) : $faq );

					}

				);

			}

		}

		// Brand tab.
		if ( Codevz_Plus::option( 'woo_product_brand_tab' ) ) {

			$terms = (array) get_the_terms( $product_id, 'product_brand' );

			if ( ! empty( $terms[0] ) ) {

				foreach( $terms as $term ) {

					$tabs[ 'codevz-' . $term->slug ] = array(
						'title'     => $term->name,
						'priority'  => 70,
						'callback'  => function() use ( $term ) {

							$logo = get_term_meta( $term->term_id, 'thumbnail_id', true );
							$logo = empty( $logo ) ? '' : wp_get_attachment_image( $logo, 'full' );
							$logo = do_shortcode( $logo ? '<div class="codevz-product-brands">' . $logo . '</div>' : '' );

							echo '<h2 class="codevz-section-title mb30">' . esc_html( $term->name ) . '</h2>';
							echo '<div class="clr">' . do_shortcode( $logo ) . '<p>' . do_shortcode( $term->description ) . '</p></div>';
							echo '<a href="' . esc_url( get_term_link( $term ) ) . '" class="button" style="margin-top:30px">' . esc_html__( 'View all Products', 'codevz-plus' ) . '</a>';

						}
					);

					continue;

				}

			}

		}

		// Shipping & Returns
		if ( Codevz_Plus::option( 'woo_product_shipping_returns_tab' ) ) {

			$tabs[ 'codevz-shipping-returns' ] = array(
				'title'     => Codevz_Plus::option( 'woo_product_shipping_returns_tab_title', esc_html__( 'Shipping & Returns' , 'codevz-plus' ) ),
				'priority'  => 80,
				'callback'  => function() {

					echo do_shortcode( Codevz_Plus::option( 'woo_product_shipping_returns_tab_content', '...' ) );

				}
			);

		}

		// Adding icon to all tabs.
		if ( Codevz_Plus::option( 'woo_product_tabs_icons' ) ) {

			foreach( $tabs as $key => &$tab ) {

				switch ( $key ) {
					case 'description':
						$icon_class = 'fas fa-info-circle';
						break;
					case 'reviews':
						$icon_class = 'fas fa-star';
						break;
					case 'additional_information':
						$icon_class = 'fas fa-list';
						break;
					case 'specifications':
						$icon_class = 'fas fa-cogs';
						break;
					case 'codevz-shipping-returns':
						$icon_class = 'fas fa-truck';
						break;
					case 'codevz-faq':
						$icon_class = 'fas fa-question-circle';
						break;
					case 'codevz-size-guide':
						$icon_class = 'fas fa-ruler';
						break;
					default:
						$icon_class = 'fas fa-tag';
				}

				$tab['title'] = '<i class="' . esc_attr( $icon_class ) . ' mr10"></i> ' . wp_kses_post( $tab['title'] );
			}

		}

		return $tabs;

	}

	/**
	 * Add extra custom classes to products.
	 * 
	 * @return array
	 */
	public function product_classes( $classes, $product ) {

		// Hover effect name.
		$hover = Codevz_Plus::option( 'woo_hover_effect' );

		if ( $hover ) {

			$hover = 'cz_image cz_image_' . esc_attr( $hover );

			$attachment_ids = $product->get_gallery_image_ids();

			// Fix single page.
			if ( is_singular( 'product' ) && ! $this->singular_product_id ) {
				$hover = '';
			}

			// Check gallery first image.
			if ( is_array( $attachment_ids ) && isset( $attachment_ids[0] ) ) {

				$classes[] = $hover;

			}
		}

		// Custom badge.
		if ( get_post_meta( $product->get_id(), 'codevz_custom_badge', true ) ) {
			$classes[] = 'codevz-product-custom-badge';
		}

		return $classes;

	}

	public function quickview() {
		if ( ! isset( $_POST['id'] ) && ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'xtra_quick_view' ) ) {
			wp_die( '<b>' . esc_html__( 'Server error, Please reload page ...', 'codevz-plus' ) . '</b>' );
		}

		echo '<div class="xtra-qv-product-content">';

		echo do_shortcode( '[product_page id="' . $_POST['id'] . '"]' );

		echo '</div>';

		echo '<link media="all" href="' . esc_attr( plugins_url( 'codevz-plus/assets/css/share.css' ) ) . '" rel="stylesheet"/>';

		wp_die();
	}

	public function woocommerce_before_shop_loop_item_title_low() {
		echo '<div class="xtra-product-thumbnail">';

		$product_id = get_the_ID();

		// Countdown.
		$date = get_post_meta( $product_id, '_sale_price_dates_to', true );

		if ( $date ) {

			$time = current_time( 'timestamp' );
			$day  = ( ( $date - $time ) > 86400 ) ? '.' : '';

			if ( $date > $time ) {
				echo do_shortcode( '[cz_countdown date="' . esc_attr( gmdate( 'Y/m/d', $date + 86400 ) ) . '" day="' . esc_attr( $day ) . '"]' );
			}

		}

		// Icons.
		$wishlist 		= Codevz_Plus::option( 'woo_wishlist' );
		$compare 		= Codevz_Plus::option( 'woo_compare' );
		$quick_view 	= Codevz_Plus::option( 'woo_quick_view' );

		if ( $wishlist || $quick_view ) {

			$center = Codevz_Plus::option( 'woo_wishlist_qv_center' ) ? ' xtra-product-icons-center' : '';
			$center .= $center ? ' cz_tooltip_up' : ( ( Codevz_Plus::$is_rtl || is_rtl() ) ? ' cz_tooltip_right' : ' cz_tooltip_left' );

			echo '<div class="xtra-product-icons' . esc_attr( $center ) . '" data-id="' . esc_attr( $product_id ) . '">';
			echo $wishlist 		? '<i class="fa fa-heart-o xtra-add-to-wishlist" data-title="' . esc_html__( 'Add to wishlist', 'codevz-plus' ) . '"></i>' : '';
			echo $compare 		? '<i class="fa czico-shuffle xtra-add-to-compare" data-title="' . esc_html__( 'Add to compare', 'codevz-plus' ) . '" data-nonce="' . esc_attr( wp_create_nonce( 'xtra_compare' ) ) . '"></i>' : '';
			echo $quick_view 	? '<i class="fa czico-146-search-4 xtra-product-quick-view" data-title="' . esc_html__( 'Quick view', 'codevz-plus' ) . '" data-nonce="' . esc_attr( wp_create_nonce( 'xtra_quick_view' ) ) . '"></i>' : '';
			echo '</div>';

		}

		$hover = Codevz_Plus::option( 'woo_hover_effect' );

		if ( $hover ) {

			$product = wc_get_product( $product_id );
			if ( $product ) {
				$attachment_ids = $product->get_gallery_image_ids();

				if ( is_array( $attachment_ids ) && isset( $attachment_ids[0] ) ) {

					echo '<div class="cz_image_in">';
					echo '<div class="cz_main_image">';

				}
			}
		}
	}

	public function woocommerce_before_shop_loop_item_title_high() {

		$hover = Codevz_Plus::option( 'woo_hover_effect' );

		if ( $hover ) {

			$product = wc_get_product( get_the_ID() );
			if ( $product ) {
				$attachment_ids = $product->get_gallery_image_ids();

				if ( is_array( $attachment_ids ) && isset( $attachment_ids[0] ) ) {

					echo '</div><div class="cz_hover_image">';

					echo do_shortcode( Codevz_Plus::get_image( $attachment_ids[0], 'woocommerce_thumbnail' ) );

					echo '</div></div>';

				}
			}

		}

		echo '</div>';

	}

	/**
	 * Quick view, wishlist and compare added popup content.
	 * 
	 * @return string
	 */
	public function popup( $content = '' ) {

		// Quickview popup.
		if ( Codevz_Plus::option( 'woo_quick_view' ) ) {

			wp_enqueue_script( 'wc-single-product' );
			wp_enqueue_script( 'wc-add-to-cart-variation' );
			wp_enqueue_script( 'wc-zoom' );
			wp_enqueue_script( 'wc-flexslider' );
			wp_enqueue_script( 'wc-photoswipe-ui-default' );
			wp_enqueue_script( 'wc-photoswipe' );
			wp_enqueue_style( 'wc-photoswipe' );
			wp_enqueue_style( 'wc-photoswipe-default-skin' );

			$content .= do_shortcode( '[cz_popup id_popup="xtra_quick_view" id="cz_xtra_quick_view" icon="fa czico-198-cancel" sk_icon="color:#ffffff;"][/cz_popup]' );

		}

		// Wishlist & Compare popup.
		if ( Codevz_Plus::option( 'woo_wishlist' ) || Codevz_Plus::option( 'woo_compare' ) ) {

			$content .= do_shortcode( '[cz_popup id_popup="xtra_wish_compare" id="cz_xtra_wish_compare" icon="fa czico-198-cancel" sk_icon="color:#ffffff;"]<i class="fa fa-check"></i><h3>...</h3><span>' . esc_html__( 'Product has been added to your list.', 'codevz-plus' ) . '</span><a href="#" class="button"><span><strong></strong><i class="fas fa-chevron-' . ( is_rtl() ? 'left' : 'right' ) . '"></i></span></a>[/cz_popup]' );

		}

		// Added to cart notification for AJAX buttons.
		if ( Codevz_Plus::option( 'woo_added_to_cart_notification' ) ) {

			$content .= '<div class="codevz-added-to-cart-notif"><i class="fa fa-check"></i><span>"<strong></strong>" ' . esc_html__( 'has been added to your cart.', 'codevz-plus' ) . '<a class="button" href="' . esc_url( get_permalink( get_option( 'woocommerce_cart_page_id' ) ) ) . '">' . esc_html__( 'View cart', 'codevz-plus' ) . '</a></span></div>';

		}

		return $content;

	}

	/**
	 * Modify checkout page and add wrap to order details.
	 * 
	 * @return string
	 */
	public function checkout_order_review_before() {
		echo '<div class="xtra-woo-checkout-details cz_sticky_col"><div class="codevz-checkout-details">';
	}

	/**
	 * Modify checkout page and add wrap to order details.
	 * 
	 * @return string
	 */
	public function checkout_order_review_after() {
		echo '</div></div>';
	}

	/**
	 * Single product add wrap div.
	 * 
	 * @return string
	 */
	public function before_single() {

		// Remove stock HTML in single page.
		add_filter( 'woocommerce_get_stock_html', '__return_null' );

		// Get current product ID.
		if ( is_singular( 'product' ) ) {

			$this->singular_product_id = get_the_ID();

		}

		// Product container.
		echo '<div class="xtra-single-product clr">';

	}

	public function after_single() {

		echo '</div>';

		// Sticky add to cart row.
		if ( is_singular( 'product' ) && Codevz_Plus::option( 'woo_product_sticky_add_to_cart' ) ) {

			echo '<div class="cz-sticky-add-to-cart"><div class="row clr"></div></div>';

		}

	}

	/**
	 * Add product to recently viewed list on single product page.
	 * 
	 * @return string
	 */
	public function recently_viewed_products() {

		if ( Codevz_Plus::option( 'woo_recently_viewed_products' ) ) {

			$product_id = get_the_ID();
			$columns = (int) Codevz_Plus::option( 'woo_related_col' );

			// Get recently viewed products from cookie.
			$rvp = Codevz_Plus::_COOKIE( 'codevz_rvp' );
			$rvp = $rvp ? json_decode( wp_unslash( $rvp ), true ) : [];

			if ( is_array( $rvp ) ) {

				// Remove product ID if already exists
				$key = array_search( $product_id, $rvp );
				if ( $key !== false ) {
					unset( $rvp[ $key ] );
				}

				// Add new product ID to the beginning
				array_unshift( $rvp, $product_id );

				// Keep only unique product IDs and limit to 5
				$rvp = array_unique( $rvp );
				$rvp = array_slice( $rvp, 0, 50 );

				echo '<script>document.cookie = "codevz_rvp=' . wp_json_encode( $rvp ) . '; expires=' . esc_attr( gmdate( 'D, d M Y H:i:s \G\M\T', time() + 3600 * 24 * 30 ) ) . '; path=/";</script>';

				// Show products.
				if ( ! empty( $rvp ) ) {

					echo '<div class="codevz-recently-viewed-products related products mt50">';
					echo '<h2>' . esc_html__( 'Recently viewed products', 'codevz-plus' ) . '</h2>';
					echo do_shortcode( '[products ids="' . implode( ',', $rvp ) . '" columns="' . esc_attr( $columns ) . '" limit="' . esc_attr( $columns * 2 ) . '"]' );
					echo '</div>';

				}

			}

		}

	}

	/**
	 * Continue shopping button in cart page.
	 * 
	 * @return string
	 */
	public function continue_shopping() {
		echo '<a class="button wc-backward" href="' . esc_url( get_the_permalink( get_option( 'woocommerce_shop_page_id' ) ) ) . '">' . esc_html( Codevz_Plus::option( 'woo_continue_shopping', esc_html__( 'Continue shopping', 'codevz-plus' ) ) ) . '</a>';
	}

	/**
	 * Out of stock button title.
	 * 
	 * @return string
	 */
	public function out_of_stock() {

		if ( Codevz_Plus::option( 'woo_sold_out_badge' ) ) {

			global $product;

			if ( $product && $product->get_stock_status() === 'outofstock' ) {
				echo '<span class="xtra-outofstock">' . esc_html( Codevz_Plus::option( 'woo_sold_out_title', esc_html__( 'Sold out', 'codevz-plus' ) ) ) . '</span>';
			}
			
		}

	}

	/**
	 * Single product page countdown
	 * 
	 * @return string
	 */
	public function countdown() {

		$date = get_post_meta( get_the_ID(), '_sale_price_dates_to', true );

		if ( $date ) {

			$time = current_time( 'timestamp' );
			$day  = ( ( $date - $time ) > 86400 ) ? '.' : '';

			if ( $date > $time ) {

				$countdown = do_shortcode( '[cz_countdown date="' . esc_attr( gmdate( 'Y/m/d', $date + 86400 ) ) . '" day="' . esc_attr( $day ) . '"]' );
				echo '<div class="woocommerce-notices-wrapper codevz-plus-woo-countdown"><div class="woocommerce-message" role="alert" tabindex="-1">' . Codevz_Plus::option( 'woo_product_countdown', esc_html__( 'Limited-time offer – Don’t miss out! Sale ending soon!', 'codevz-plus' ) ) . ' ' . $countdown . '</div></div>';

			}

		}

	}

	/**
	 * Single product page taxonomy meta box getting from its post type.
	 * 
	 * @return string
	 */
	public function add_meta_boxes() {

		add_meta_box( 'codevz_woo_meta_box', esc_html__( 'Advanced', 'codevz-plus' ), [ $this, 'metabox_content' ], 'product', 'side', 'default' );

	}

	/**
	 * Custom product options as side meta box.
	 * 
	 * @return string
	 */
	public function metabox_content( $post ) {

		$is_free = Codevz_Plus::is_free();
		$post_id = $post->ID;

		codevz_add_field( [
			'echo'    		=> true,
			'id'    		=> 'codevz_custom_badge',
			'type'  		=> $is_free ? 'content' : 'text',
			'title' 		=> esc_html__( 'Custom Badge', 'codevz-plus' ),
			'content' 		=> Codevz_Plus::pro_badge()
		], get_post_meta( $post_id, 'codevz_custom_badge', true ) );

		codevz_add_field( [
			'echo'    		=> true,
			'id'    		=> 'codevz_custom_button_title',
			'type'  		=> $is_free ? 'content' : 'text',
			'title' 		=> esc_html__( 'Custom button title', 'codevz-plus' ),
			'content' 		=> Codevz_Plus::pro_badge()
		], get_post_meta( $post_id, 'codevz_custom_button_title', true ) );

		codevz_add_field( [
			'echo'    		=> true,
			'id'    		=> 'codevz_custom_button_link',
			'type'  		=> $is_free ? 'content' : 'text',
			'title' 		=> esc_html__( 'Custom button link', 'codevz-plus' ),
			'content' 		=> Codevz_Plus::pro_badge()
		], get_post_meta( $post_id, 'codevz_custom_button_link', true ) );

		codevz_add_field( [
			'echo'    		=> true,
			'id'    		=> 'codevz_custom_meta',
			'type'  		=> $is_free ? 'content' : 'group',
			'title' 		=> esc_html__( 'Custom product meta', 'codevz-plus' ),
			'button_title' 	=> esc_html__( 'Add', 'codevz-plus' ),
			'content' 		=> Codevz_Plus::pro_badge(),
			'fields' 		=> array(
				array(
					'id' 			=> 'title',
					'type' 			=> 'text',
					'title' 		=> esc_html__( 'Title', 'codevz-plus' )
				),
				array(
					'id' 			=> 'value',
					'type' 			=> 'textarea',
					'title' 		=> esc_html__( 'Value', 'codevz-plus' )
				),
			)
		], get_post_meta( $post_id, 'codevz_custom_meta', true ) );

		codevz_add_field( [
			'echo'    		=> true,
			'id'    		=> 'codevz_size_guide',
			'type'  		=> $is_free ? 'content' : 'select',
			'title' 		=> esc_html__( 'Size guide tab', 'codevz-plus' ),
			'options' 		=> 'posts',
			'query_args' 	=> [ 'post_type' => 'codevz_size_guide', 'posts_per_page' => -1 ],
			'default_option'=> esc_html__( '~ Default ~', 'codevz-plus' ),
			'content' 		=> Codevz_Plus::pro_badge()
		], get_post_meta( $post_id, 'codevz_size_guide', true ) );

		codevz_add_field( [
			'echo'    		=> true,
			'id'    		=> 'codevz_faq',
			'type'  		=> $is_free ? 'content' : 'select',
			'title' 		=> esc_html__( 'FAQ tab', 'codevz-plus' ),
			'options' 		=> 'posts',
			'query_args' 	=> [ 'post_type' => 'codevz_faq', 'posts_per_page' => -1 ],
			'default_option'=> esc_html__( '~ Default ~', 'codevz-plus' ),
			'content' 		=> Codevz_Plus::pro_badge()
		], get_post_meta( $post_id, 'codevz_faq', true ) );

	}

	/**
	 * Update taxonomy value on product save.
	 * 
	 * @return -
	 */
	public function save_post_product( $post_id ) {

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		$badge 	= Codevz_Plus::_POST( 'codevz_custom_badge' );
		$title 	= Codevz_Plus::_POST( 'codevz_custom_button_title' );
		$link 	= Codevz_Plus::_POST( 'codevz_custom_button_link' );
		$meta 	= filter_input( INPUT_POST, 'codevz_custom_meta', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY );
		$guide 	= Codevz_Plus::_POST( 'codevz_size_guide' );
		$faq 	= Codevz_Plus::_POST( 'codevz_faq' );

		update_post_meta( $post_id, 'codevz_custom_badge', $badge );
		update_post_meta( $post_id, 'codevz_custom_button_title', $title );
		update_post_meta( $post_id, 'codevz_custom_button_link', $link );
		update_post_meta( $post_id, 'codevz_custom_meta', $meta );
		update_post_meta( $post_id, 'codevz_size_guide', $guide );
		update_post_meta( $post_id, 'codevz_faq', $faq );

	}

	// Post state for compare and wishlist in admin.
	public function display_post_states( $states, $post ) {

		$original_slugs = [ 'products-compare', 'wishlist' ];

		// Post state for custom woocommerce pages.
		if ( in_array( $post->post_name, $original_slugs, true ) ) {
			$states[] = esc_html__( 'WooCommerce', 'codevz-plus' );
		}

		// Post state for custom maintenance.
		if ( $post->post_title === Codevz_Plus::option( 'maintenance_mode' ) ) {
			$states[] = esc_html__( 'Maintenance', 'codevz-plus' );
		}

		// Post state for custom header.
		if ( $post->post_title === Codevz_Plus::option( 'header_elementor' ) ) {
			$states[] = esc_html__( 'Custom Header', 'codevz-plus' );
		}

		// Post state for footer.
		if ( $post->post_title === Codevz_Plus::option( 'footer_elementor' ) ) {
			$states[] = esc_html__( 'Custom Footer', 'codevz-plus' );
		}

		// Post state for quick contact.
		if ( $post->post_title === Codevz_Plus::option( 'cf7_beside_backtotop' ) ) {
			$states[] = esc_html__( 'Quick Contact', 'codevz-plus' );
		}

		return $states;
	}

	/**
	 * Move new post types menu under products menu.
	 * 
	 * @return -
	 */
	public function admin_menu() {

		// Remove post type menu.
		remove_menu_page( 'edit.php?post_type=codevz_size_guide' );
		remove_menu_page( 'edit.php?post_type=codevz_faq' );

		global $submenu;

		// Add post type as taxonomy menu under products menu.
		$submenu['edit.php?post_type=product'][] = [ esc_html__( 'Size guide', 'codevz-plus' ), 'manage_options', 'edit.php?post_type=codevz_size_guide' ];
		$submenu['edit.php?post_type=product'][] = [ esc_html__( 'FAQ', 'codevz-plus' ), 'manage_options', 'edit.php?post_type=codevz_faq' ];

	}

	// Remove duplicated custom woo pages.
	public function admin_init() {

		if ( isset( $_GET['post_type'] ) && $_GET['post_type'] === 'page' ) {

			$base_slugs = [ 'products-compare', 'wishlist' ];

			foreach ( $base_slugs as $base ) {

				// Delete duplicates like -2, -3, -2-2 etc.
				foreach ( range( 2, 6 ) as $i ) {

					$slug = $base . '-' . $i;
					$page = get_page_by_path( $slug, OBJECT, 'page' );
					if ( $page && ! empty( $page->post_name ) && $page->post_name === $slug ) {
						wp_trash_post( $page->ID );
					}

					// retry again -ID
					$slug_sub = $slug . '-' . $i;
					$page_sub = get_page_by_path( $slug_sub, OBJECT, 'page' );
					if ( $page_sub && ! empty( $page_sub->post_name ) && $page_sub->post_name === $slug_sub ) {
						wp_trash_post( $page_sub->ID );
					}

				}

				// Create original page if not exists
				$original_page = get_page_by_path( $base, OBJECT, 'page' );

				if ( empty( $original_page ) ) {

					// Set correct shortcode
					$shortcode = ( $base === 'wishlist' ) ? '[cz_wishlist]' : '[cz_compare]';

					// Create new page
					wp_insert_post( [
						'post_title'   => ucwords( str_replace( '-', ' ', $base ) ),
						'post_name'    => $base,
						'post_status'  => 'publish',
						'post_type'    => 'page',
						'post_content' => $shortcode,
					] );

				}

			}

		}

	}

	/**
	 * Adding custom stock label after title and before single product price.
	 * 
	 * @return string
	 */
	public function single_stock_label() {

		global $product;

		if ( $product ) {

			if ( $product->is_in_stock() ) {
				echo '<span class="codevz-woo-stock-label"><span>' . esc_html__( 'Instock', 'codevz-plus' ) . '</span></span>';
			} else {
				echo '<span class="codevz-woo-stock-label codevz-woo-outofstock-label"><span>' . esc_html__( 'Out of stock', 'codevz-plus' ) . '</span></span>';
			}

		}
	}

	/**
	 * Customize product meta HTML tags for styling.
	 * 
	 * @return string
	 */
	public function product_meta() {

		global $product;

		$id = $product->get_id();

		$sku 	= $product->get_sku();
		$cats 	= wc_get_product_category_list( $id, ', ', '', '' );
		$tags 	= wc_get_product_tag_list( $id, ', ', '', '' );
		$stock 	= $product->get_availability();
		$stock 	= isset( $stock[ 'availability' ] ) ? $stock[ 'availability' ] : '';
		$status = $product->get_stock_status();
		$icon 	= ( $status === 'outofstock' ? '<i class="fa fa-shop-lock" aria-hidden="true"></i>' : '<i class="fa fa-check" data-title="' . esc_attr( $stock ) . '" aria-hidden="true"></i>' );
		$custom_meta = get_post_meta( $id, 'codevz_custom_meta', true );

		// Show brand name and logo in single product page.
		$brands = (array) get_the_terms( $id, 'product_brand', true );
		foreach( $brands as $term ) {

			if ( ! empty( $term->name ) ) {

				$name = $term->name;
				$link = get_term_link( $term );

				// Logo.
				$logo = get_term_meta( $term->term_id, 'thumbnail_id', true );
				$logo = empty( $logo ) ? '' : wp_get_attachment_image( $logo, 'full' );
				$logo = $logo ? '<div class="codevz-product-brands"><a href="' . esc_url( $link ) . '" title="' . esc_attr( $term->name ) . '">' . do_shortcode( $logo ) . '</a></div>' : '';

				// Brand name.
				//$brand =  $logo ? esc_html( $name ) : '<a href="' . esc_url( $link ) . '">' . esc_html( $name ) . '</a>';
				$brand =  '<a href="' . esc_url( $link ) . '">' . esc_html( $name ) . '</a>';

				break;

			}

		}

		echo '<div class="product_meta clr">';

		echo !empty( $logo ) ? do_shortcode( $logo ) : ''; // brand logo.

		echo !empty( $brand ) ? '<div class="codevz-pm-brand"><strong>' . esc_html__( 'Brand', 'codevz-plus' ) . '</strong><span>' . do_shortcode( $brand ) . '</span></div>' : '';
		echo $sku  ? '<div class="codevz-pm-sku"><strong>' . esc_html__( 'SKU', 'codevz-plus' ) . '</strong><span>' . esc_html( $sku ) . '</span></div>' : '';
		echo $stock? '<div class="codevz-pm-status codevz-pm-status-' . esc_attr( $status ) . '"><strong>' . esc_html__( 'Status', 'codevz-plus' ) . '</strong><span>' . do_shortcode( preg_replace( '/(\d+)/', '<b>$1</b>', $stock ) . $icon ) . '</span></div>' : '';

		// Custom meta.
		foreach( (array) $custom_meta as $key => $meta ) {

			if ( isset( $meta[ 'title' ] ) && isset( $meta[ 'value' ] ) ) {

				echo '<div class="codevz-pm-' . esc_attr( sanitize_title_with_dashes( $meta[ 'title' ] ) ) . ' clr"><strong>' . esc_html( $meta[ 'title' ] ) . '</strong><span>' . esc_html( $meta[ 'value' ] ) . '</span></div>';

			}

		}

		// Category and tags.
		echo $tags ? '<div class="codevz-pm-tags clr"><strong>' . esc_html__( 'Tags', 'codevz-plus' ) . '</strong><span>' . do_shortcode( $tags ) . '</span></div>' : '';
		echo $cats ? '<div class="codevz-pm-cats clr"><strong>' . esc_html__( 'Categories', 'codevz-plus' ) . '</strong><span>' . do_shortcode( $cats ) . '</span></div>' : '';

		echo '</div>';

		// Check.
		if ( Codevz_Plus::$is_free ) {
			return;
		}

		// Custom text after meta.
		if ( Codevz_Plus::option( 'woo_after_product_meta' ) ) {

			echo '<div class="codevz-custom-product-meta">';

			if ( Codevz_Plus::$preview ) {
				echo '<i class="codevz-section-focus fas fa-cog" data-section="product"></i>';
			}

			echo do_shortcode( Codevz_Plus::option( 'woo_after_product_meta' ) );

			echo '</div>';

		}

		// Secure cards payment section.
		$secure_title = Codevz_Plus::option( 'woo_secure_payment' );
		$secure_image = Codevz_Plus::option( 'woo_secure_payment_image' );

		if ( $secure_title || $secure_image ) {

			echo '<div class="codevz-woo-secure-payment">';
			echo $secure_title ? '<div>' . esc_html( $secure_title ) . '</div>' : '';
			echo $secure_image ? '<img src="' . esc_url( $secure_image ) . '" alt="Secure payment cards" />' : '';
			echo '</div>';

		}

	}

	/**
	 * Hook to add custom option field to product attribute settings
	 * 
	 * @return string
	 */
	public function attribute_add_fields() {

		$id = absint( Codevz_Plus::_GET( 'edit' ) );
		$value = get_option( 'codevz_pa_' . $id );

		$types = [
			'select' 	=> esc_html__( 'Dropdown', 'codevz-plus' ),
			'button' 	=> esc_html__( 'Button', 'codevz-plus' ),
			'thumbnail' => esc_html__( 'Thumbnail', 'codevz-plus' ),
			'color' 	=> esc_html__( 'Color', 'codevz-plus' ),
		];

		echo '<' . ( $id ? 'tr' : 'div' ) . ' class="form-field" style="position: relative">';
		echo '<th scope="row" valign="top">';
		echo '<label for="my-field">' . esc_html__( 'Attribute swatch type', 'codevz-plus' ) . '</label>';
		echo '</th><td>';

		if ( Codevz_Plus::is_free() ) {

			echo do_shortcode( Codevz_Plus::pro_badge() );

		} else {

			echo '<select name="codevz_pa" id="codevz_pa">';

			foreach ( $types as $key => $title ) {
				echo '<option value="' . esc_attr( $key ) . '"' . ( $value === $key ? ' selected' : '' ) . '>' . esc_html( $title ) . '</option>';
			}

			echo '</select>';

		}

		echo '<p class="description">' . esc_html__( "Default output for product attributes' variations in HTML: dropdowns or swatches.", 'codevz-plus' ) . '</p>';

		echo '</td></' . ( $id ? 'tr' : 'div' ) . '>';

	}

	/**
	 * Save option value when adding/editing product attribute term.
	 * 
	 * @return -
	 */
	public function attribute_added_updated( $id ) {

		update_option( 'codevz_pa_' . $id, Codevz_Plus::_POST( 'codevz_pa' ) );

	}

	/**
	 * Add color picker to attribute taxonomy configuration.
	 * 
	 * @return string
	 */
	public function add_variation_colorpicker( $term ) {

		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wp-color-picker' );

		?>
			<tr class="form-field">
				<th scope="row" valign="top">
					<label for="codevz_attribute_color"><?php esc_html_e( 'Attribute Color', 'codevz-plus' ); ?></label>
				</th>
				<td>
					<input type="text" class="codevz-color-picker" name="codevz_attribute_color" id="codevz_attribute_color" value="<?php echo esc_attr( empty( $term->term_id ) ? '' : get_term_meta( $term->term_id, 'codevz_attribute_color', true ) ); ?>" />
					<p class="description"><?php esc_html_e( 'Select a color for this attribute.', 'codevz-plus' ); ?></p>
				</td>
			</tr>
			<script type="text/javascript">
				jQuery( function( $ ) {
					$( '.codevz-color-picker' ).wpColorPicker();
				});
			</script>
		<?php

	}

	/**
	 * Save custom color field value when adding product attribute term
	 * 
	 * @return string
	 */
	public function save_variation_colorpicker( $term_id, $tt_id, $taxonomy ) {

		update_term_meta( $term_id, 'codevz_attribute_color', Codevz_Plus::_POST( 'codevz_attribute_color' ) );

	}

	/**
	 * Add custom column to the term list table e.g. 'pa_color'
	 * 
	 * @return array
	 */
	public function add_variation_color_column( $columns ) {

		// Create an array of columns to add
		$new_columns = array(
			'codevz_attribute_color' => esc_html__( 'Color', 'codevz-plus' ),
		);

		// Split the columns array into two parts
		$first_columns = array_slice( $columns, 0, 1 ); // Get the first column
		$last_columns = array_slice( $columns, 1 ); // Get the rest of the columns

		// Merge the new column after the first position
		$columns = array_merge( $first_columns, $new_columns, $last_columns );

		return $columns;

	}

	/**
	 * Display content for the custom column in pa_* taxonomy.
	 * 
	 * @return array
	 */
	public function show_variation_color_column( $content, $column_name, $term_id ) {

		if ( 'codevz_attribute_color' === $column_name ) {
			$custom_color = get_term_meta( $term_id, 'codevz_attribute_color', true );
			$content .= '<div style="border-radius:100px;width: 20px; height: 20px;border-radius: 100px; margin: 1px auto 0; background-color: ' . esc_attr( $custom_color ) . '"></div>';
		}

		return $content;

	}

	/**
	 * Modify variations output HTML and add swatches like color, thumbnail and button type.
	 * 
	 * @return array
	 */
	public function variations_output( $html, $args ) {

		global $product;

		// Attribute type.
		$type = get_option( 'codevz_pa_' . wc_attribute_taxonomy_id_by_name( $args[ 'attribute' ] ) );

		// Default variation selected for product.
		$default = $product->get_default_attributes();

		// Get all product variations.
		$variations = $product->get_available_variations();

		ob_start();

		echo '<div class="codevz-variations codevz-variations-' . esc_attr( $type ) . '">';

		// Get terms.
		$terms = wc_get_product_terms(
			$product->get_id(),
			$args[ 'attribute' ],
			array(
				'fields' => 'all',
			)
		);

		if ( $type === 'color' ) {

			foreach( $terms as $term ) {

				$selected = ( isset( $default[ $args[ 'attribute' ] ] ) && $default[ $args[ 'attribute' ] ] === $term->slug ) ? ' checked="checked"' : '';

				echo '<input type="radio" id="codevz_' . esc_attr( $term->term_id ) . '" name="attribute_' . esc_attr( $args[ 'attribute' ] ) . '" value="' . esc_attr( $term->slug ) . '"' . wp_kses_post( (string) $selected ) . ' />';
				echo '<label title="' . esc_attr( $term->name ) . '" for="codevz_' . esc_attr( $term->term_id ) . '" style="background-color:' . esc_attr( get_term_meta( $term->term_id, 'codevz_attribute_color', true ) ) . '">' . esc_html( $term->name ) . '</label>';

			}

		} else if ( $type === 'button' ) {

			foreach( $terms as $term ) {

				$selected = ( isset( $default[ $args[ 'attribute' ] ] ) && $default[ $args[ 'attribute' ] ] === $term->slug ) ? 'checked="checked"' : '';

				echo '<input type="radio" id="codevz_' . esc_attr( $term->term_id ) . '" name="attribute_' . esc_attr( $args[ 'attribute' ] ) . '" value="' . esc_attr( $term->slug ) . '"' . wp_kses_post( (string) $selected ) . ' />';
				echo '<label for="codevz_' . esc_attr( $term->term_id ) . '">' . esc_html( $term->name ) . '</label>';

			}

		} else if ( $type === 'thumbnail' ) {

			foreach( $terms as $term ) {

				foreach( $variations as $key => $variation ) {

					if ( isset( $variation[ 'attributes' ][ 'attribute_' . $args[ 'attribute' ] ] ) && $variation[ 'attributes' ][ 'attribute_' . $args[ 'attribute' ] ] === $term->slug ) {

						if ( ! empty( $variation[ 'image' ][ 'url' ] ) ) {

							$image = $variation[ 'image' ][ 'url' ];

							break;

						}

					}

				}

				$selected = ( isset( $default[ $args[ 'attribute' ] ] ) && $default[ $args[ 'attribute' ] ] === $term->slug ) ? 'checked="checked"' : '';

				echo '<input type="radio" id="codevz_' . esc_attr( $term->term_id ) . '" name="attribute_' . esc_attr( $args[ 'attribute' ] ) . '" value="' . esc_attr( $term->slug ) . '"' . wp_kses_post( (string) $selected ) . ' />';
				echo '<label for="codevz_' . esc_attr( $term->term_id ) . '"><img loading="lazy" src="' . esc_attr( isset( $image ) ? $image : '' ) . '" alt="thumbnail" /></label>';

				$image = '';

			}

		}

		echo do_shortcode( $html );

		echo '</div>';

		$html = ob_get_clean();

		return $html;

	}

	/**
	 * Display cart page related products according to cart items.
	 * 
	 * @return string
	 */
	public function cart_related_products() {

		echo '</div>'; // Close cart total div.

		$cart = wc()->cart->get_cart();

		$linked_producs = false;

		// Find linked products.
		foreach( $cart as $cart_item_key => $cart_item ) {

			$linked_producs = empty( get_post_meta( $cart_item['product_id'], '_crosssell_ids', true ) ) ? false : true;

		}

		// Check and find related.
		if ( ! $linked_producs ) {

			$related = array_reduce( $cart, function( $result, $cart_item ) {
				return array_merge( $result, wc_get_related_products( $cart_item[ 'product_id' ] ) );
			}, [] );

			$related = array_slice( array_diff( array_unique( $related ), array_column( $cart, 'product_id' ) ), 0, 2 );

			if ( ! empty( $related ) ) {

				echo '<div class="codevz-cart-ralated related products"><h2>' . esc_html__( 'You may be interested in ...', 'codevz-plus' ) . '</h2>';
				echo do_shortcode( '[products ids="' . implode( ',', $related ) . '" columns="2"]' );
				echo '</div>';

			}

		}

	}

	/**
	 * Display cart page interested products when cart is empty.
	 * 
	 * @return string
	 */
	public function cart_empty_products_section() {

		$products = wc_get_products( [ 'limit' => 4, 'orderby' => 'rand' ] );

		if ( ! empty( $products ) ) {

			$ids = [];

			foreach( $products as $product ) {
				$ids[] = $product->get_id();
			}

			echo '<div class="related products mt50"><h2>' . esc_html__( 'You may be interested in ...', 'codevz-plus' ) . '</h2>';
			echo do_shortcode( '[products ids="' . implode( ',', $ids ) . '" columns="4"]' );
			echo '</div>';

		}

	}

	/**
	 * Show 3 steps above on cart, checkout and order completion pages
	 * 
	 * @return string
	 */
	public function cart_checkout_steps() {

		$steps = [
			[
				'name' => esc_html__( 'Shopping cart', 'codevz-plus' ),
				'link' => wc_get_cart_url()
			],
			[
				'name' => esc_html__( 'Checkout details', 'codevz-plus' ),
				'link' => wc_get_checkout_url()
			],
			[
				'name' => esc_html__( 'Order complete', 'codevz-plus' ),
				'link' => '#'
			]
		];

		echo '<div class="codevz-cart-checkout-steps' . ( Codevz_Plus::option( 'woo_cart_checkout_steps' ) === 'vertical' ? ' codevz-cart-checkout-steps-col' : '' ) . '">';

		foreach( $steps as $index => $step ) {

			$active_class = ( is_cart() && ! $index ) || ( is_checkout() && $index === 1 ) || ( is_order_received_page() && $index === 2 ) ? 'codevz-current-step' : '';

			echo '<a href="' . esc_url( $step['link'] ) . '" class="' . esc_attr( $active_class ) . '"><span>' . esc_html( $index + 1 ) . '</span><strong>' . esc_html( $step['name'] ) . '</strong></a>';
			echo $index !== 2 ? '<i class="fa czico-Icon-Navigation-Arrow-Right" aria-hidden="true"></i>' : '';

		}

		echo '</div>';

	}

	// Avatar above the account nav.
	public function my_account_avatar() {

		$user = wp_get_current_user();

		echo '<div class="codevz-account-avatar">';
		echo '<img loading="lazy" src="' . esc_url( get_avatar_url( $user->ID, [ 'size' => 150 ] ) ) . '">';
		echo '<strong>' . esc_html( ucwords( $user->first_name . ' ' . $user->last_name ) ) . '</strong>';
		echo '<span>' . esc_html( $user->user_email ) . '</span>';
		echo '</div>';

		echo '<script>document.addEventListener("DOMContentLoaded",function(){const a=document.querySelector(".codevz-account-avatar"),b=document.querySelector(".woocommerce-MyAccount-navigation");a&&b&&(b.prepend(a),a.style.display="block");});</script>';

	}

	// Fix new links in my account nav.
	public function my_account_endpoints() {

		add_rewrite_endpoint( 'tracking', EP_PAGES );
		add_rewrite_endpoint( 'wishlist', EP_PAGES );
		add_rewrite_endpoint( 'viewed',   EP_PAGES );
		add_rewrite_endpoint( 'reviews',  EP_PAGES );

		flush_rewrite_rules();

	}

	// Add a new menus to my account page.
	public function my_account_menus( $items ) {

		$menus = [
			'tracking' 	=> esc_html__( 'Tracking orders', 'codevz-plus' ),
			'wishlist' 	=> esc_html__( 'Wishlist', 'codevz-plus' ),
			'viewed' 	=> esc_html__( 'Recently viewed', 'codevz-plus' ),
			'reviews' 	=> esc_html__( 'Reviews', 'codevz-plus' ),
		];

		return array_slice( $items, 0, 2, true ) + $menus + array_slice( $items, 2, null, true );

	}

	// My account wishlist tab content.
	public function my_account_wishlist() {

		echo '<div class="codevz-my-account-wishlist">';
		echo '<h3 class="codevz-section-title">' . esc_html__( 'Wishlist', 'codevz-plus' ) . '</h3><br />';
		echo '<div class="woocommerce xtra-wishlist xtra-icon-loading" data-col="2" data-empty="' . esc_html__( 'Your wishlist list is empty', 'codevz-plus' ) . '" data-nonce="' . esc_attr( wp_create_nonce( 'xtra_wishlist_content' ) ) . '"></div>';
		echo '</div>';

	}

	// My account recently viewed tab content.
	public function my_account_viewed() {

		$product_id = get_the_ID();

		// Get recently viewed products from cookie.
		$rvp = Codevz_Plus::_COOKIE( 'codevz_rvp' );
		$rvp = $rvp ? json_decode( wp_unslash( $rvp ), true ) : null;

		if ( is_array( $rvp ) ) {

			// Remove product ID if already exists
			$key = array_search( $product_id, $rvp );
			if ( $key !== false ) {
				unset( $rvp[ $key ] );
			}

			// Add new product ID to the beginning
			array_unshift( $rvp, $product_id );

			// Keep only unique product IDs and limit to 5
			$rvp = array_slice( array_unique( $rvp ), 0, 20 );

			// Show products.
			if ( ! empty( $rvp ) ) {

				echo '<div class="codevz-recently-viewed-products">';
				echo '<h3 class="codevz-section-title">' . esc_html__( 'Recently viewed products', 'codevz-plus' ) . '</h3>';
				echo do_shortcode( '[products ids="' . esc_attr( implode( ',', $rvp ) ) . '" columns="2"]' );
				echo '</div>';

			}

		} else {
			echo '<h3 class="codevz-section-title">' . esc_html__( 'Recently viewed products', 'codevz-plus' ) . '</h3>';
			echo 'No products found here. Please explore some products.';
		}

	}

	// My account reviews tab content.
	public function my_account_reviews() {
		$user_id = get_current_user_id();
		$reviews = get_comments(array(
			'user_id' => $user_id,
			'status' => 'approve',
			'post_type' => 'product',
		));

		echo '<h3 class="codevz-section-title">' . esc_html__( 'Your rating and reviews', 'codevz-plus' ) . '</h3>';

		if ( $reviews ) {

			foreach( $reviews as $review ) {

				echo '<div class="codevz-my-account-reviews">';

				$product = wc_get_product( $review->comment_post_ID );

				echo '<div class="codevz-my-account-reviews-title">';
				echo do_shortcode( $product->get_image() );
				echo '<span><strong>' . esc_html( $product->get_name() ) . '</strong><span>' . esc_html( $review->comment_date ) . '</span></span></div>';

				echo '<div>';
				echo do_shortcode( wc_get_rating_html( get_comment_meta( $review->comment_ID, 'rating', true ) ) );
				echo '<p>' . esc_html( $review->comment_content ) . '</p>';
				echo '</div>';

				echo '</div>';

			}

		} else {
			echo '<p>' . esc_html__( 'You haven\'t submitted any reviews yet, Please visit the product pages of your orders to share your feedback.', 'codevz-plus' ) . '</p>';
		}
	}

	// My account order tracking tab content.
	public function my_account_tracking() { ?>

		<div class="codevz-order-tracking-form">
			<h3 class="codevz-section-title"><?php esc_html_e( 'Tracking order', 'codevz-plus' ); ?></h3>
			<form action="" method="post">
				<p>
					<label for="order_id"><?php esc_html_e( 'Order ID', 'codevz-plus' ); ?></label>
					<input type="text" name="order_id" id="order_id" required>
				</p>
				<p>
					<label for="customer_email"><?php esc_html_e( 'Your email', 'codevz-plus' ); ?></label>
					<input type="email" name="customer_email" id="customer_email" required>
				</p>
				<input type="submit" value="<?php esc_html_e( 'Track order', 'codevz-plus' ); ?>">
			</form>
		</div>

		<?php

		$order_id 		= Codevz_Plus::_POST( 'order_id' );
		$customer_email = Codevz_Plus::_POST( 'customer_email' );

		// Display order tracking results
		if ( $order_id && $customer_email ) {

			// Check if wc_get_order function is available
			$order = wc_get_order( intval( $order_id ) );

			if ( $order && $order->get_billing_email() === sanitize_email( $customer_email ) ) {

				echo '<div class="codevz-order-tracking related products mt50">';

				// Display order status as steps
				$order_status = $order->get_status();

				echo '<h2>' . esc_html__( 'Tracking info of order #', 'codevz-plus' ) . wp_kses_post( (string) $order->get_order_number() ) . '</h2>';
				echo '<table>';

				echo '<thead><tr><th>' . esc_html__( 'Order number', 'codevz-plus' ) . '</th><th>' . wp_kses_post( (string) $order->get_order_number() ) . '</th></tr></thead>';

				echo '<tr><th>' . esc_html__( 'Order date', 'codevz-plus' ) . '</th><td>' . wp_kses_post( (string) wc_format_datetime($order->get_date_created()) ) . '</td></tr>';
				echo '<tr><th>' . esc_html__( 'Order status', 'codevz-plus' ) . '</th><td>' . wp_kses_post( (string) wc_get_order_status_name($order_status) ) . '</td></tr>';
				echo '<tr><th>' . esc_html__( 'Last update', 'codevz-plus' ) . '</th><td>' . wp_kses_post( (string) wc_format_datetime($order->get_date_modified()) ) . '</td></tr>';

				// Products List
				$items = $order->get_items();
				echo '<tr><th>' . esc_html__( 'Product(s)', 'codevz-plus' ) . '</th><td>';
				foreach( $items as $item ) {
					echo '<strong>' . wp_kses_post( (string) $item->get_name() ) . '</strong> x ' . wp_kses_post( (string) $item->get_quantity() ) . ' = ' . wp_kses_post( (string) wc_price( $item->get_subtotal() ) ) . '<br />';
				}
				echo '</td></tr>';

				echo '<tr><th>' . esc_html__( 'Total amount', 'codevz-plus' ) . '</th><td>' . wp_kses_post( (string) wc_price($order->get_total()) ) . '</td></tr>';
				echo '<tr><th>' . esc_html__( 'Name', 'codevz-plus' ) . '</th><td>' . wp_kses_post( (string) $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ) . '</td></tr>';
				echo '<tr><th>' . esc_html__( 'Phone', 'codevz-plus' ) . '</th><td>' . wp_kses_post( (string) $order->get_billing_phone() ) . '</td></tr>';
				echo '<tr><th>' . esc_html__( 'Email', 'codevz-plus' ) . '</th><td>' . wp_kses_post( (string) $order->get_billing_email() ) . '</td></tr>';
				echo '<tr><th>' . esc_html__( 'Address', 'codevz-plus' ) . '</th><td>' . wp_kses_post( (string) $order->get_formatted_billing_address() ) . '</td></tr>';
				echo '<tr><th>' . esc_html__( 'Order note', 'codevz-plus' ) . '</th><td>' . wp_kses_post( (string) $order->get_customer_note() ) . '</td></tr>';
				echo '<tr><th>' . esc_html__( 'Delivery method', 'codevz-plus' ) . '</th><td>' . wp_kses_post( (string) $order->get_shipping_method() ) . '</td></tr>';
				echo '<tr><th>' . esc_html__( 'Payment method', 'codevz-plus' ) . '</th><td>' . wp_kses_post( (string) $order->get_payment_method_title() ) . '</td></tr>';

				echo '</table>';
			} else {
				echo '<strong class="mt50" style="display:block;color:red">' . esc_html__( 'Invalid Order ID or Email. Please try again.', 'codevz-plus' ) . '</strong>';
			}

			echo '</div>';

		}

	}

	// Fix my account login form container div.
	public static function before_my_account_form() {
		echo '<div class="codevz-woo-login-form">';
	}

	// Fix my account login form container div.
	public static function after_my_account_form() {
		echo '</div>';
	}

	// Modify products title and add category name under it.
	public function category_under_title( $title, $product_id ) {

		$subtitle = Codevz_Plus::option( 'woo_category_under_title' );

		if ( $subtitle === 'brand' ) {

			$brands = (array) get_the_terms( $product_id, 'product_brand', true );

			foreach( $brands as $term ) {

				if ( ! empty( $term->name ) ) {

					$title .= '<span class="codevz-product-category-after-title">' . esc_html( $term->name ) . '</span>';

					break;

				}

			}

		} else if ( $subtitle ) {

			$post = get_post( $product_id );

			if ( $post && $post->post_type === 'product' && $product_id !== $this->singular_product_id ) {

				// Get product categories
				$categories = get_the_terms( $product_id, 'product_cat' );

				// Check if there are categories and get the first one
				if ( $categories && ! is_wp_error( $categories ) ) {

					$category = array_shift( $categories );

					$title .= '<span class="codevz-product-category-after-title">' . $category->name . '</span>';

				}

			}

		}

		return $title;

	}

	// Product gallery navigation arrows.
	public function woo_flexslider_options( $options ) {

		$options['directionNav'] = true;

		return $options;

	}

	// Add brand logo to taxonomy page.
	public function term_description() {

    	if ( is_product_taxonomy() ) {

			$term = get_queried_object();

			if ( $term && ! is_wp_error( $term ) ) {

				$description = get_term_field( 'description', $term, $term->taxonomy );

				// Logo for brand page.
				if ( is_tax( 'product_brand' ) ) {

					$logo = get_term_meta( $term->term_id, 'thumbnail_id', true );
					$logo = empty( $logo ) ? '' : wp_get_attachment_image( $logo, 'full' );
					$logo = do_shortcode( $logo ? '<div class="codevz-product-brands">' . $logo . '</div>' : '' );

					$description = $logo . $description;

				}

				// Add category cover to cat page.
				if ( is_tax( 'product_cat' ) ) {

					$cover = get_term_meta( $term->term_id, 'thumbnail_id', true );

					if ( $cover ) {
						$description = '<div class="codevz-product-brands">' . wp_get_attachment_image( $cover, 'full' ) . '</div>' . $description;
					}

				}

				// Term description limit height.
				if ( $description && Codevz_Plus::option( 'term_desc_limit' ) ) {

					$description = do_shortcode( '[cz_show_more_less height="' . esc_attr( Codevz_Plus::option( 'term_desc_limit_height', '200px' ) ) . '" gradient="' . esc_attr( Codevz_Plus::option( 'term_desc_limit_color' ) ) . '" more="" icon_more="fa czico-200-down-arrow" less="" icon_less="fa czico-199-up-arrow" center="true"]' . $description . '[/cz_show_more_less]' );

				}

				// Show desc.
				if ( $description ) {
					echo '<div class="term-description">' . do_shortcode( $description ) . '</div>';
				}

			}

    	}

	}

	// Add short description under product rating on shop archive pages
	public function products_short_desc() {

		if ( Codevz_Plus::option( 'woo_products_short_desc' ) ) {

			global $product;

			$desc = $product->get_short_description();

			if ( $desc ) {

				$desc = wp_strip_all_tags( $desc );

				echo '<div class="codevz-product-short-desc">';
				echo wp_kses_post( (string) mb_strimwidth( $desc, 0, 90, '...' ) );
				echo '</div>';

			}

		}

	}

	// Move outofstock to end of loop.
	public function move_outofstock_end_of_loop( $posts_clauses ) {

		if ( ! is_admin() && is_woocommerce() && ( is_shop() || is_product_category() || is_product_tag() ) ) {

			if ( Codevz_Plus::contains( $posts_clauses['where'], "product" ) ) {

				$db = Codevz_Plus::database();

				$posts_clauses['join'] .= " INNER JOIN $db->postmeta istockstatus ON ($db->posts.ID = istockstatus.post_id) ";
				$posts_clauses['orderby'] = " istockstatus.meta_value ASC, " . $posts_clauses['orderby'];
				$posts_clauses['where'] = " AND istockstatus.meta_key = '_stock_status' AND istockstatus.meta_value <> '' " . $posts_clauses['where'];

			}

		}

		return $posts_clauses;
	}

	// Change product title tag to another tag.
	public function add_new_product_title_tag() {

		$tag = Codevz_Plus::option( 'woo_product_title_tag', 'h2' );

		the_title( '<' . esc_attr( $tag ) . ' class="product_title entry-title">', '</' . esc_attr( $tag ) . '>' );

	}

	// How many people viewing this product.
	public function log_sessions_script() {

		$live = Codevz_Plus::option( 'woo_live' );

		if ( $live === 'cart' ) {

			global $product;

			$product_id = $product->get_id();
			$transient_name = 'codevz_track_user_cart_count_' . $product_id;

			$count = get_transient( $transient_name );
			$count = $count ? $count : 0;

			if ( $count === 1 ) {
			    $message = sprintf(
			        esc_html__( '%s person has added this product to their cart', 'codevz-plus' ),
			        '<span>' . esc_html( $count ) . '</span>'
			    );
			} else {
			    $message = sprintf(
			        esc_html__( '%s people have added this product to their cart', 'codevz-plus' ),
			        '<span>' . esc_html( $count ) . '</span>'
			    );
			}

			echo '<div class="codevz-product-live codevz-product-live-cart' . ( $count > 0 ? '' : ' hidden' ) . '"><i class="fa czico-039-commerce-3 mr8"></i>' . wp_kses_post( $message ) . '</div>';

			echo '<script>
				function codevzWooLiveCart() {

					var element = jQuery( ".codevz-product-live-cart" );

					jQuery.ajax({
						url: "' . esc_url( admin_url( 'admin-ajax.php' ) ) . '",
						type: "POST",
						data: {
							action: "codevz_woo_live_cart",
							product_id: jQuery( ".cart .button" ).val(),
							nonce: "' . esc_html( wp_create_nonce( 'codevz_woo_live' ) ) . '"
						},
						success: function( response ) {

							element[ response > 0 ? "removeClass" : "addClass" ]( "hidden" );

							element.find( "span" ).html( response );
						}
					});
				}

				setInterval( codevzWooLiveCart, 20000 );

			</script>';

		} else if ( $live === 'sessions' ) {

			$sessions = get_transient( 'codevz_active_sessions' );
			$sessions = $sessions ? $sessions : [ 1 ];
			$sessions = count( $sessions );

			if ( $sessions === 1 ) {
			    $message = sprintf(
			        esc_html__( '%s person is viewing this product right now', 'codevz-plus'),
			        '<span>' . esc_html( $sessions ) . '</span>'
			    );
			} else {
			    $message = sprintf(
			        esc_html__( '%s people are viewing this product right now', 'codevz-plus'),
			        '<span>' . esc_html( $sessions ) . '</span>'
			    );
			}

			echo '<div class="codevz-product-live codevz-product-live-sessions' . ( $sessions <= 1 ? ' hidden' : '' ) . '"><i class="fa fa-eye mr8"></i>' . wp_kses_post( $message ) . '</div>';

			echo '<script>
				function codevzWooSessions() {

					var element = jQuery( ".codevz-product-live-sessions" );

					jQuery.ajax({
						url: "' . esc_url( admin_url( 'admin-ajax.php' ) ) . '",
						type: "POST",
						data: {
							action: "codevz_woo_sessions",
							nonce: "' . esc_html( wp_create_nonce( 'codevz_woo_live' ) ) . '"
						},
						success: function( response ) {

							element[ response <= 1 ? "addClass" : "removeClass" ]( "hidden" );

							element.find( "span" ).html( response );
						}
					});
				}

				setInterval( codevzWooSessions, 20000 );

			</script>';

		}

	}

	// Get and set active session.
	public function codevz_woo_sessions() {

		if ( ! wp_verify_nonce( Codevz_Plus::_POST( 'nonce' ), 'codevz_woo_live' ) ) {
			wp_die();
		}

		if ( session_status() == PHP_SESSION_NONE ) {
			session_start();
		}

		if ( session_status() == PHP_SESSION_ACTIVE ) {

			$session_id = session_id();
			$active_sessions = get_transient( 'codevz_active_sessions' );

			if ( ! $active_sessions ) {

				$active_sessions = [ $session_id => time() ];
				set_transient( 'codevz_active_sessions', $active_sessions, 60 );

			} else {

				foreach( $active_sessions as $key => $value ) {

					if ( time() - $value > 60 ) {
						unset( $active_sessions[ $key ] );
					}

				}

				$active_sessions[ $session_id ] = time();

				set_transient( 'codevz_active_sessions', $active_sessions, 60 );

			}

			// Log count of active sessions array
			$active_sessions_count = count( $active_sessions );

			echo esc_html( $active_sessions_count );

		} else {

			echo 1;

		}

		wp_die();

	}

	// Track carts count on add to cart.
	public function track_cart_addition( $cart_item_key, $product_id ) {

		$transient_name = 'codevz_track_user_cart_count_' . $product_id;

		$count = get_transient( $transient_name );
		$count = $count ? $count : 0;

		$count++;

		set_transient( $transient_name, $count, DAY_IN_SECONDS * 2 );

	}

	// Track carts count on remove.
	public function track_cart_removal( $cart_item_key, $cart ) {

		$product_id 	= $cart->removed_cart_contents[ $cart_item_key ][ 'product_id' ];
		$transient_name = 'codevz_track_user_cart_count_' . $product_id;

		$count = get_transient( $transient_name );
		$count = $count ? $count : 0;

		if ( $count > 0 ) {

			$count--;

			set_transient( $transient_name, $count, DAY_IN_SECONDS * 2 );

		}

	}

	// Get all users carts count.
	public function codevz_woo_live_cart() {

		if ( empty( $_POST['product_id'] ) || empty( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'codevz_woo_live' ) ) {
			wp_die();
		}

		$transient_name = 'codevz_track_user_cart_count_' . esc_html( $_POST['product_id'] );

		$count = get_transient( $transient_name );
		$count = $count ? $count : 0;

		echo esc_html( $count );

		wp_die();

	}

	// Add phone assistance box after Add to Cart and before Product Meta
	public function phone_assistance() {

		if ( Codevz_Plus::option( 'woo_phone_assist' ) ) {

		    ?><a class="codevz-woo-assistance" href="<?php echo esc_url( Codevz_Plus::option( 'woo_phone_assist_link', '#' ) ); ?>">
		        <i class="<?php echo Codevz_Plus::option( 'woo_phone_assist_icon', 'fa fa-phone' ); ?>"></i>
		        <div>
		            <span>
		            	<?php echo do_shortcode( Codevz_Plus::option( 'woo_phone_assist_title', esc_html__( 'Our customer representative is waiting for you', 'codevz-plus' ) ) ); ?>
		            </span>
		            <br>
		            <strong>
		            	<?php echo do_shortcode( Codevz_Plus::option( 'woo_phone_assist_subtitle', esc_html__( 'Call for immediate assistance at 1-312-800-0000', 'codevz-plus' ) ) ); ?>
		            </strong>
		        </div>
		    </a><?php

		}

	}

	// Protect wishlist and compare page slug.
	public function prevent_slug_change( $data, $postarr ) {

		// Check if the post type is 'page' and if the ID is set
		if ( $data['post_type'] == 'page' && isset( $postarr['ID'] ) ) {

			// Array of slugs you want to protect
			$protected = array( 'products-compare', 'wishlist' );

			// Get the current post slug
			$current_slug = get_post_field( 'post_name', $postarr['ID'] );

			// If the current slug is in the protected list and it is being changed, revert it
			if ( in_array( $current_slug, $protected ) && $data['post_name'] != $current_slug ) {
				$data['post_name'] = $current_slug;
			}

		}

		return $data;

	}

	// Cart page product SKU under title.
	public function cart_product_sku( $cart_item, $cart_item_key ) {

		if ( ! empty( $cart_item['data'] ) ) {

			$sku = $cart_item['data']->get_sku();

			if ( $sku ) {
				echo '<small style="opacity:.6">' . esc_html__( 'SKU', 'codevz-plus' ) . ': ' . esc_html( $sku ) . '</small>';
			}

		}

	}

	// Calculate and store discount percentage
	public function calculate_and_store_discount_percentage( $post_id ) {

	    if ( get_post_type( $post_id ) != 'product' ) {
	        return;
	    }

	    $regular_price  = get_post_meta( $post_id, '_regular_price', true );
	    $sale_price 	= get_post_meta( $post_id, '_sale_price', true );
	    
	    if ( $regular_price && $sale_price && $regular_price > 0 ) {
	        $discount_percentage = (( $regular_price - $sale_price ) / $regular_price ) * 100;
	        update_post_meta( $post_id, '_discount_percentage', $discount_percentage );
	    } else {
	        delete_post_meta( $post_id, '_discount_percentage' );
	    }

	}

	// Add custom sorting options to WooCommerce Sort
	public function woocommerce_catalog_orderby( $sortby ) {

	    $sortby = array(
	        'menu_order' 		=> esc_html__('Default', 'codevz-plus'),
	        'popularity' 		=> esc_html__('Popular', 'codevz-plus'),
	        'featured' 			=> esc_html__('Featured', 'codevz-plus'),
	        'rating' 			=> esc_html__('Top Rated', 'codevz-plus'),
	        'relevance' 		=> esc_html__('Relevance', 'codevz-plus'),
	        'date' 				=> esc_html__('Sort by latest', 'codevz-plus'),
	        'date-asc' 			=> esc_html__('Sort by oldest', 'codevz-plus'),
	        'price' 			=> esc_html__('Price low to high', 'codevz-plus'),
	        'price-desc' 		=> esc_html__('Price high to low', 'codevz-plus'),
	        'discount-desc' 	=> esc_html__('Highest Discount', 'codevz-plus'),
	        'discount-asc' 		=> esc_html__('Lowest Discount', 'codevz-plus'),
	        'title' 			=> esc_html__('Alphabetically, A-Z', 'codevz-plus'),
	        'title-desc' 		=> esc_html__('Alphabetically, Z-A', 'codevz-plus')
	    );

	    return $sortby;

	}

	// Add custom sorting functionality
	public function woocommerce_get_catalog_ordering_args( $args, $orderby, $order ) {

	    switch( $orderby ) {

	        case 'featured':
	            add_filter( 'posts_clauses', function( $clauses ) {
	               	$db = Codevz_Plus::database();

	                // Get the IDs of featured products
	                $featured_ids = wc_get_featured_product_ids();
	                if ( empty( $featured_ids ) ) {
	                    return $clauses;
	                }

	                // Modify the ORDER BY clause to prioritize featured products
	                $ids = implode( ',', array_map( 'intval', $featured_ids ) );
	                $clauses['orderby'] = "FIELD( {$db->posts}.ID, {$ids} ) DESC, {$db->posts}.post_date DESC";

	                return $clauses;
	            });
	            break;

	        case 'discount':
	            $args['orderby']  = 'meta_value_num';
	            $args['meta_key'] = '_discount_percentage';
	            $args['order']    = ( 'ASC' === $order ) ? 'ASC' : 'DESC';
	            break;

	    }

	    return $args;

	}


	// Shop columns grid icons.
	public function shop_columns_grid_icons() {

		if ( ! Codevz_Plus::option( 'woo_columns_selector' ) ) {
			return;
		}

		$site_id = ( function_exists( 'is_multisite' ) && is_multisite() ) ? get_current_blog_id() : '1';

	    ?>

		<div class="codevz-woo-columns">

			<span data-col="2">
				<svg width="1em" height="1em" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
					<rect x="3" y="3" width="6" height="6" fill="currentColor" rx="1" ry="1"/>
					<rect x="15" y="3" width="6" height="6" fill="currentColor" rx="1" ry="1"/>
					<rect x="3" y="15" width="6" height="6" fill="currentColor" rx="1" ry="1"/>
					<rect x="15" y="15" width="6" height="6" fill="currentColor" rx="1" ry="1"/>
				</svg>
			</span>

			<span data-col="3">
				<svg width="1em" height="1em" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
					<rect x="3" y="3" width="4" height="4" fill="currentColor" rx="1" ry="1"/>
					<rect x="10" y="3" width="4" height="4" fill="currentColor" rx="1" ry="1"/>
					<rect x="17" y="3" width="4" height="4" fill="currentColor" rx="1" ry="1"/>
					<rect x="3" y="10" width="4" height="4" fill="currentColor" rx="1" ry="1"/>
					<rect x="10" y="10" width="4" height="4" fill="currentColor" rx="1" ry="1"/>
					<rect x="17" y="10" width="4" height="4" fill="currentColor" rx="1" ry="1"/>
					<rect x="3" y="17" width="4" height="4" fill="currentColor" rx="1" ry="1"/>
					<rect x="10" y="17" width="4" height="4" fill="currentColor" rx="1" ry="1"/>
					<rect x="17" y="17" width="4" height="4" fill="currentColor" rx="1" ry="1"/>
				</svg>
			</span>

			<span data-col="4">
				<svg width="1em" height="1em" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
					<rect x="2" y="2" width="4" height="4" fill="currentColor" rx="1" ry="1"/>
					<rect x="8" y="2" width="4" height="4" fill="currentColor" rx="1" ry="1"/>
					<rect x="14" y="2" width="4" height="4" fill="currentColor" rx="1" ry="1"/>
					<rect x="20" y="2" width="4" height="4" fill="currentColor" rx="1" ry="1"/>
					<rect x="2" y="8" width="4" height="4" fill="currentColor" rx="1" ry="1"/>
					<rect x="8" y="8" width="4" height="4" fill="currentColor" rx="1" ry="1"/>
					<rect x="14" y="8" width="4" height="4" fill="currentColor" rx="1" ry="1"/>
					<rect x="20" y="8" width="4" height="4" fill="currentColor" rx="1" ry="1"/>
					<rect x="2" y="14" width="4" height="4" fill="currentColor" rx="1" ry="1"/>
					<rect x="8" y="14" width="4" height="4" fill="currentColor" rx="1" ry="1"/>
					<rect x="14" y="14" width="4" height="4" fill="currentColor" rx="1" ry="1"/>
					<rect x="20" y="14" width="4" height="4" fill="currentColor" rx="1" ry="1"/>
					<rect x="2" y="20" width="4" height="4" fill="currentColor" rx="1" ry="1"/>
					<rect x="8" y="20" width="4" height="4" fill="currentColor" rx="1" ry="1"/>
					<rect x="14" y="20" width="4" height="4" fill="currentColor" rx="1" ry="1"/>
					<rect x="20" y="20" width="4" height="4" fill="currentColor" rx="1" ry="1"/>
				</svg>
			</span>

		</div>

	    <script type="text/javascript">
	        document.addEventListener('DOMContentLoaded', function() {
	            const gridIcons = document.querySelectorAll('.codevz-woo-columns span');
	            const productList = document.querySelector('.products');
	            const products = productList.querySelectorAll('.product');
	            const currentClass = 'codevz-current';
	            const productsPerPage = <?php echo esc_html( $this->columns() ); ?>;

	            function updateProductClasses(columns) {
	                productList.className = `products columns-${columns}`;
	                products.forEach((product, index) => {
	                    product.classList.toggle('first', index % columns === 0);
	                    product.classList.toggle('last', (index + 1) % columns === 0);
	                });
	                // Set the current class on the selected icon
	                gridIcons.forEach(icon => {
	                    icon.classList.toggle(currentClass, icon.dataset.col == columns);
	                });
	                // Set a cookie to remember the selected columns
	                document.cookie = `codevz_columns_<?php echo esc_html( $site_id ); ?>=${columns}; path=/; max-age=${60 * 60 * 24 * 30}`;
	            }

	            gridIcons.forEach(icon => {
	                icon.addEventListener('click', () => {
	                    updateProductClasses(icon.dataset.col);
	                });
	            });

	            // Check for the cookie and use its value if it exists
	            const columns = document.cookie.split('; ').find(row => row.startsWith('codevz_columns_<?php echo esc_html( $site_id ); ?>='));
	            const defaultColumns = columns ? columns.split('=')[1] : productsPerPage;

	            if ( defaultColumns < 5 ) {
	            	updateProductClasses(defaultColumns);
	            }

				// Fix shop grid icons align with select fields.
			    var wooColumns = document.querySelector('.codevz-woo-columns'),
			    	wooOrdering = document.querySelector('.woocommerce-ordering');

			    if (wooColumns && wooOrdering) {

				    if ( wooOrdering.offsetHeight == 45 ) {
				    	wooOrdering.offsetHeight = 40;
				    } else {
				    	wooOrdering.offsetHeight = wooOrdering.offsetHeight - 3;
				    }

			        wooColumns.style.height = wooOrdering.offsetHeight + 'px';

			    }

	        });
	    </script>
	    <?php

	}

	// Change add to cart text on single product page
	public function add_to_cart_title( $title ) {

		global $product;

		if ( $product && $product->get_stock_status() === 'instock' ) {

			$add_to_cart = Codevz_Plus::option( 'woo_add_to_cart_title' );

			return esc_html( $add_to_cart ? $add_to_cart : $title ); 

		}

		return $title;
	}

	// Shop archive title tag.
	public function archive_title_tag() {

		$tag = Codevz_Plus::option( 'woo_shop_title_tag', 'h2' );

		if ( $tag !== 'hide' ) {

			echo '<' . esc_attr( $tag ) . ' class="page-title">';
			woocommerce_page_title();
			echo '</' . esc_attr( $tag ) . '>';

		}

	}

    // Waitlist system box HTML form.
    public function waitlist_box() {
        global $product;
        if ( $product->is_in_stock() ) {
        	return;
        }
        ?>
        <div id="codevz-waitlist-box" style="border:1px solid #67676733;padding:30px 40px;margin-top:1.5em;border-radius:10px">
            <h3 style="margin:5px 0;"><?php echo esc_html__( 'This product is currently out of stock', 'codevz-plus' ); ?></h3>
            <p style="margin:5px 0 25px;color:#666;"><?php echo esc_html__( "Don't worry! Enter your email address and we'll notify you when it's available again", 'codevz-plus' ); ?></p>
            <form id="codevz-waitlist-form">
                <input type="email" name="email" placeholder="youremail@gmail.com" required
                       style="padding:8px;width:65%;max-width:280px">
                <button type="submit" style="margin: 0 10px;padding:9px 15px">
                    <?php echo esc_html__( 'Add me to waitlist', 'codevz-plus' ); ?>
                </button>
                <input type="hidden" name="product_id" value="<?php echo esc_attr( $product->get_id() ); ?>">
                <input type="hidden" name="action" value="codevz_waitlist">
                <?php wp_nonce_field( 'codevz_waitlist', 'nonce' ); ?>
            </form>
        </div>

        <script>
        jQuery(function($){
            $('#codevz-waitlist-form').on('submit', function(e){
                e.preventDefault();
                $.post('<?php echo admin_url("admin-ajax.php"); ?>', $(this).serialize(), function(r){
                    if(r.success){
                        $('#codevz-waitlist-box').html('<p style="font-weight:bold">'+r.data.message+'</p>');
                    } else {
                        alert(r.data.message);
                    }
                }, 'json');
            });
        });
        </script>
        <?php
    }

    // Waitlist system Handle AJAX
    public function waitlist_handle() {
        check_ajax_referer( 'codevz_waitlist', 'nonce' );
        $email = sanitize_email( $_POST['email'] ?? '' );
        $pid   = absint( $_POST['product_id'] ?? 0 );
        if ( ! $pid || ! is_email( $email ) ) {
        	wp_send_json_error( ['message'=> esc_html__( 'Invalid email address', 'codevz-plus' ) ] );
        }

        $list = (array) get_post_meta( $pid, '_codevz_waitlist', true );
        if ( ! in_array( $email, $list ) ) {
            $list[] = $email;
            update_post_meta( $pid, '_codevz_waitlist', $list );
        }
        wp_send_json_success( [ 'message'=> esc_html__( 'Thank you, Your email added to waitlist', 'codevz-plus' ) ] );
    }

    // Waitlist system Send emails when stock changes to "instock"
    public function waitlist_send_emails( $product_id, $stock_status, $product ) {
        if ( $stock_status !== 'instock' ) {
        	return;
        }

        $list = (array) get_post_meta( $product_id, '_codevz_waitlist', true );
        if ( empty( $list ) ) {
        	return;
        }

        $subject 	  = sprintf(
        	esc_html__( 'Product Back in Stock: %s', 'codevz-plus' ), 
        	$product->get_name()
        );
		$link         = esc_url( get_permalink( $product_id ) );
		$product_name = esc_html( $product->get_name() );
		$greeting  = esc_html__( 'Good news!', 'codevz-plus' );
		$body      = sprintf(
		    esc_html__( 'The product (%s) is back in stock', 'codevz-plus' ),
		    $product_name
		);
		$cta       = esc_html__( 'Buy it here', 'codevz-plus' );
		$thanks    = esc_html__( 'Thank you!', 'codevz-plus' );

		// Now safely assemble using HTML outside the translated strings
		$message = sprintf(
		    '%1$s<br><br>%2$s<br><br>👉 <a href="%3$s" target="_blank" style="color:#0073aa;">%4$s</a><br><br>%5$s',
		    $greeting,
		    $body,
		    $link,
		    $cta,
		    $thanks
		);

        foreach ( $list as $email ) {
            Codevz_Plus::sendMail( $email, $subject, $message );
        }

        // Clear waitlist after emails sent
        delete_post_meta( $product_id, '_codevz_waitlist' );
    }

}

Codevz_Plus_Woocommerce::instance();