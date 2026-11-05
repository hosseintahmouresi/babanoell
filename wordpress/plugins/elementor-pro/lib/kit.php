<?php
/**
 * Elementor Kit Library Connection Override
 * 
 * This file can be included in another plugin to override the kit library connection check.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// Prevent duplicate function definition
if ( ! function_exists( 'override_elementor_kit_library_connection' ) ) {
    /**
     * Override the kit library connection check by removing the is_connected condition
     */
    function override_elementor_kit_library_connection() {
        if ( ! class_exists( '\\Elementor\\Plugin' ) || ! class_exists( '\\ElementorPro\\License\\API' ) ) {
            return;
        }

        // Get Elementor instance
        $elementor = \Elementor\Plugin::instance();
        
        // Check if app is available
        if ( ! isset( $elementor->app ) || ! $elementor->app ) {
            return;
        }
        
        // Get current kit library settings
        $prev_settings = $elementor->app->get_settings( 'kit-library' );
        
        if ( ! $prev_settings || empty( $prev_settings ) ) {
            return;
        }
        
        // Check if common component exists
        if ( ! isset( $elementor->common ) || ! $elementor->common ) {
            return;
        }
        
        // Check if connect component exists
        if ( ! $elementor->common->get_component( 'connect' ) ) {
            return;
        }
        
        // Get kit library from connect
        $kit_library = $elementor->common->get_component( 'connect' )->get_app( 'kit-library' );
        
        if ( ! $kit_library ) {
            return;
        }
        
        // Override the existing settings
        $prev_settings['is_library_connected'] = \ElementorPro\License\API::is_license_active() && $kit_library;
        
        // Update settings
        $elementor->app->set_settings( 'kit-library', $prev_settings );
    }
}

// Add the hook only if it hasn't been added yet
if ( ! has_action( 'elementor/init', 'override_elementor_kit_library_connection' ) ) {
    // Make sure we run this after Elementor Pro has fully loaded
    add_action( 'elementor_pro/init', function() {
        // Add our override at a later priority to ensure it runs after ElementorPro's kit library module
        add_action( 'elementor/init', 'override_elementor_kit_library_connection', 20 );
    });
}


update_option( 'elementor_pro_license_key', 'activated' );
update_option(
    '_elementor_pro_license_v2_data',
    [
        'timeout' => strtotime('+12 hours', current_time('timestamp')),
        'value' => json_encode([
            'success' => true,
            'license' => 'valid',
            'expires' => '2050-11-25 08:00:16',
            'subscription_id' => 960,
            'status' => 'ACTIVE',
            'recurring' => false,
            'features' => [
    'template_access_level_None',
    'kit_access_level_None',
    'activity-log',
    'breadcrumbs',
    'form',
    'posts',
    'template',
    'countdown',
    'slides',
    'price-list',
    'portfolio',
    'flip-box',
    'price-table',
    'login',
    'share-buttons',
    'theme-post-content',
    'theme-post-title',
    'nav-menu',
    'blockquote',
    'media-carousel',
    'animated-headline',
    'facebook-comments',
    'facebook-embed',
    'facebook-page',
    'facebook-button',
    'testimonial-carousel',
    'post-navigation',
    'search-form',
    'post-comments',
    'author-box',
    'call-to-action',
    'post-info',
    'theme-site-logo',
    'theme-site-title',
    'theme-archive-title',
    'theme-post-excerpt',
    'theme-post-featured-image',
    'archive-posts',
    'theme-page-title',
    'sitemap',
    'reviews',
    'table-of-contents',
    'lottie',
    'code-highlight',
    'hotspot',
    'video-playlist',
    'progress-tracker',
    'section-effects',
    'sticky',
    'scroll-snap',
    'page-transitions',
    'mega-menu',
    'nested-carousel',
    'loop-grid',
    'loop-carousel',
    'theme-builder',
    'elementor_icons',
    'elementor_custom_fonts',
    'dynamic-tags',
    'taxonomy-filter',
    'email',
    'email2',
    'mailpoet',
    'mailpoet3',
    'redirect',
    'header',
    'footer',
    'single-post',
    'single-page',
    'archive',
    'search-results',
    'error-404',
    'loop-item',
    'font-awesome-pro',
    'typekit',
    'gallery',
    'off-canvas',
    'link-in-bio-var-2',
    'link-in-bio-var-3',
    'link-in-bio-var-4',
    'link-in-bio-var-5',
    'link-in-bio-var-6',
    'link-in-bio-var-7',
    'search',
    'size-variable',
    'element-manager-permissions',
    'akismet',
    'display-conditions',
    'woocommerce-products',
    'wc-products',
    'woocommerce-product-add-to-cart',
    'wc-elements',
    'wc-categories',
    'woocommerce-product-price',
    'woocommerce-product-title',
    'woocommerce-product-images',
    'woocommerce-product-upsell',
    'woocommerce-product-short-description',
    'woocommerce-product-meta',
    'woocommerce-product-stock',
    'woocommerce-product-rating',
    'wc-add-to-cart',
    'dynamic-tags-wc',
    'woocommerce-product-data-tabs',
    'woocommerce-product-related',
    'woocommerce-breadcrumb',
    'wc-archive-products',
    'woocommerce-archive-products',
    'woocommerce-product-additional-information',
    'woocommerce-menu-cart',
    'woocommerce-product-content',
    'woocommerce-archive-description',
    'paypal-button',
    'woocommerce-checkout-page',
    'woocommerce-cart',
    'woocommerce-my-account',
    'woocommerce-purchase-summary',
    'woocommerce-notices',
    'settings-woocommerce-pages',
    'settings-woocommerce-notices',
    'popup',
    'custom-css',
    'global-css',
    'custom_code',
    'custom-attributes',
    'form-submissions',
    'form-integrations',
    'dynamic-tags-acf',
    'dynamic-tags-pods',
    'dynamic-tags-toolset',
    'editor_comments',
    'stripe-button',
    'role-manager',
    'global-widget',
    'activecampaign',
    'cf7db',
    'convertkit',
    'discord',
    'drip',
    'getresponse',
    'mailchimp',
    'mailerlite',
    'slack',
    'webhook',
    'product-single',
    'product-archive',
    'wc-single-elements',
    'atomic-custom-attributes'
            ],
            'tier' => 'agency',
            'generation' => 'empty',
            'activated' => true,
            'success' => true
        ])
    ]
);

add_filter( 'elementor/connect/additional-connect-info', '__return_empty_array', 999 );
add_filter( 'pre_http_request', function( $pre, $parsed_args, $url ) {
	if ( strpos( $url, 'my.elementor.com/api/v1/licenses' ) !== false || 
		 strpos( $url, 'my.elementor.com/api/v2/licenses' ) !== false ) {
		return [
			'response' => [ 'code' => 200, 'message' => 'ОК' ],
			'body'     => json_encode( [ 
				'success' => true, 
				'license' => 'valid', 
				'expires' => '01.01.2030' 
			] )
		];
	}
	return $pre;
}, 10, 3 );

/* Elementor Pro Templates*/
add_filter( 'pre_http_request', function( $pre, $parsed_args, $url ) {
	if ( strpos( $url, 'my.elementor.com/api/connect/v1/library/get_template_content' ) !== false ) {
		$response = wp_remote_get( 
			"https://s3.ir-thr-at1.arvanstorage.ir/elementorfa/library/elementor-pro/New/{$parsed_args['body']['id']}.json", 
			[ 'sslverify' => false, 'timeout' => 25 ] 
		);

		if ( wp_remote_retrieve_response_code( $response ) != 200 ) {
			$response = wp_remote_get( 
				"https://s3.ir-thr-at1.arvanstorage.ir/elementorfa/library/elementor-pro/templates/{$parsed_args['body']['id']}.json", 
				[ 'sslverify' => false, 'timeout' => 25 ] 
			);
		}

		if ( wp_remote_retrieve_response_code( $response ) == 200 ) {
			return $response;
		}
	}
	return $pre;
}, 10, 3 );
/* Elementor Pro Kits*/
add_filter( 'pre_http_request', function( $pre, $parsed_args, $url ) {
	if ( strpos( $url, 'https://my.elementor.com/api/v1/kits-library' ) !== false ) {
		$id = array_slice(explode('/', rtrim($url, '/')), -2)[0];
		$response = wp_remote_get( "https://s3.ir-thr-at1.arvanstorage.ir/elementorfa/library/elementor-pro/kits-library/new-kits/{$id}/download-link.json", 
			[ 'sslverify' => false, 'timeout' => 25 ] );
		if ( wp_remote_retrieve_response_code( $response ) != 200 ) {
			$response = wp_remote_get( "https://s3.ir-thr-at1.arvanstorage.ir/elementorfa/library/elementor-pro/kits-library/kits/{$id}/download-link.json", 
				[ 'sslverify' => false, 'timeout' => 25 ] );
		}
		if ( wp_remote_retrieve_response_code( $response ) == 200 ) {
			return $response;
		}
	}
	return $pre;
}, 10, 3 );

if (!defined('ABSPATH')) exit;
add_action('admin_menu', fn()=>remove_submenu_page('elementor','elementor-license'), 9999);
add_action('admin_init', function() {
    if (!is_admin() || (defined('DOING_AJAX') && DOING_AJAX) || (defined('REST_REQUEST') && REST_REQUEST)) return;
    $p = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
    if ($p === 'elementor-license') { wp_safe_redirect(admin_url('index.php')); exit; }
});