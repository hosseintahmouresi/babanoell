<?php
/**
 * Plugin Name: Raavi Seller AJAX Search for Elementor
 * Description: Dedicated Elementor AJAX search widget for the seller post type. Filters an existing Elementor Loop Grid without re-rendering it.
 * Version: 1.1.0
 * Author: Raavi
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * Text Domain: raavi-seller-ajax-search
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Raavi_Seller_Ajax_Search_Plugin {
    const VERSION = '1.1.0';
    const AJAX_ACTION = 'raavi_seller_ajax_search';
    const NONCE_ACTION = 'raavi_seller_ajax_search_nonce';
    const QUERY_FLAG = 'raavi_seller_title_search';

    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'plugins_loaded', array( $this, 'boot_elementor' ), 20 );
        add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ), 5 );
        add_action( 'elementor/frontend/after_register_scripts', array( $this, 'register_assets' ) );
        add_action( 'elementor/frontend/after_register_styles', array( $this, 'register_assets' ) );

        add_action( 'wp_ajax_' . self::AJAX_ACTION, array( $this, 'ajax_search' ) );
        add_action( 'wp_ajax_nopriv_' . self::AJAX_ACTION, array( $this, 'ajax_search' ) );

        add_filter( 'posts_where', array( $this, 'filter_where' ), 20, 2 );
        add_filter( 'posts_orderby', array( $this, 'filter_orderby' ), 20, 2 );
    }

    public function boot_elementor() {
        if ( ! did_action( 'elementor/loaded' ) ) {
            add_action( 'admin_notices', array( $this, 'elementor_missing_notice' ) );
            return;
        }

        add_action( 'elementor/widgets/register', array( $this, 'register_widget' ) );
    }

    public function elementor_missing_notice() {
        if ( ! current_user_can( 'activate_plugins' ) ) {
            return;
        }
        echo '<div class="notice notice-warning"><p>' . esc_html__( 'Raavi Seller AJAX Search برای اجرا به Elementor نیاز دارد.', 'raavi-seller-ajax-search' ) . '</p></div>';
    }

    public function register_assets() {
        $base = plugin_dir_url( __FILE__ );

        if ( ! wp_style_is( 'raavi-seller-ajax-search', 'registered' ) ) {
            wp_register_style(
                'raavi-seller-ajax-search',
                $base . 'assets/css/search.css',
                array(),
                self::VERSION
            );
        }

        if ( ! wp_script_is( 'raavi-seller-ajax-search', 'registered' ) ) {
            wp_register_script(
                'raavi-seller-ajax-search',
                $base . 'assets/js/search.js',
                array(),
                self::VERSION,
                true
            );

            wp_localize_script(
                'raavi-seller-ajax-search',
                'RaaviSellerAjaxSearch',
                array(
                    'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                    'action'  => self::AJAX_ACTION,
                    'nonce'   => wp_create_nonce( self::NONCE_ACTION ),
                    'genericError' => 'خطا در جستجو. لطفاً دوباره تلاش کنید.',
                    'targetError'  => 'لیست فروشگاه‌ها برای جستجو پیدا نشد.',
                )
            );
        }
    }

    public function register_widget( $widgets_manager ) {
        require_once __DIR__ . '/includes/class-raavi-seller-search-widget.php';
        $widgets_manager->register( new \Raavi_Seller_Search_Widget() );
    }

    public function ajax_search() {
        check_ajax_referer( self::NONCE_ACTION, 'nonce' );

        $term = isset( $_POST['term'] ) ? wp_unslash( $_POST['term'] ) : '';
        if ( is_array( $term ) || is_object( $term ) ) {
            wp_send_json_error( array( 'message' => 'عبارت جستجو نامعتبر است.' ), 400 );
        }

        $term = trim( sanitize_text_field( (string) $term ) );
        if ( function_exists( 'mb_substr' ) ) {
            $term = mb_substr( $term, 0, 100, 'UTF-8' );
        } else {
            $term = substr( $term, 0, 100 );
        }

        if ( '' === $term ) {
            wp_send_json_success( array( 'ids' => array(), 'count' => 0, 'empty' => true ) );
        }

        $max_results = (int) apply_filters( 'raavi_seller_ajax_search_max_results', 5000 );
        if ( $max_results < 1 ) {
            $max_results = 5000;
        }

        $query = new \WP_Query(
            array(
                'post_type'              => 'seller',
                'post_status'            => 'publish',
                'posts_per_page'         => $max_results,
                'fields'                 => 'ids',
                'no_found_rows'          => true,
                'ignore_sticky_posts'    => true,
                'update_post_meta_cache' => false,
                'update_post_term_cache' => false,
                'orderby'                => 'title',
                'order'                  => 'ASC',
                self::QUERY_FLAG         => $term,
            )
        );

        $ids = array_values( array_unique( array_map( 'absint', $query->posts ) ) );

        wp_send_json_success(
            array(
                'ids'   => $ids,
                'count' => count( $ids ),
            )
        );
    }

    public function filter_where( $where, $query ) {
        $term = $query->get( self::QUERY_FLAG );
        if ( ! is_string( $term ) || '' === trim( $term ) ) {
            return $where;
        }

        global $wpdb;

        $tokens = preg_split( '/[\s\x{200C}\x{200F}\x{200E}]+/u', $this->normalize_persian( $term ), -1, PREG_SPLIT_NO_EMPTY );
        if ( empty( $tokens ) ) {
            return $where;
        }

        $groups = array();
        foreach ( $tokens as $token ) {
            $variants = $this->variants( $token );
            $parts = array();

            foreach ( $variants as $variant ) {
                $parts[] = $wpdb->prepare(
                    "{$wpdb->posts}.post_title LIKE %s",
                    '%' . $wpdb->esc_like( $variant ) . '%'
                );
            }

            if ( ! empty( $parts ) ) {
                $groups[] = '(' . implode( ' OR ', $parts ) . ')';
            }
        }

        if ( ! empty( $groups ) ) {
            $where .= ' AND (' . implode( ' AND ', $groups ) . ')';
        }

        return $where;
    }

    public function filter_orderby( $orderby, $query ) {
        $term = $query->get( self::QUERY_FLAG );
        if ( ! is_string( $term ) || '' === trim( $term ) ) {
            return $orderby;
        }

        global $wpdb;
        $variants = $this->variants( $this->normalize_persian( $term ) );
        if ( empty( $variants ) ) {
            return $orderby;
        }

        $exact = array();
        $starts = array();
        foreach ( $variants as $variant ) {
            $exact[] = $wpdb->prepare( "{$wpdb->posts}.post_title = %s", $variant );
            $starts[] = $wpdb->prepare( "{$wpdb->posts}.post_title LIKE %s", $wpdb->esc_like( $variant ) . '%' );
        }

        return 'CASE '
            . 'WHEN (' . implode( ' OR ', $exact ) . ') THEN 0 '
            . 'WHEN (' . implode( ' OR ', $starts ) . ') THEN 1 '
            . 'ELSE 2 END ASC, ' . $wpdb->posts . '.post_title ASC';
    }

    private function normalize_persian( $text ) {
        $text = strtr(
            (string) $text,
            array(
                'ي' => 'ی',
                'ى' => 'ی',
                'ك' => 'ک',
                'ة' => 'ه',
            )
        );
        $text = preg_replace( '/[\x{200C}\x{200F}\x{200E}\s]+/u', ' ', $text );
        return trim( (string) $text );
    }

    private function variants( $text ) {
        $fa = $this->normalize_persian( $text );
        $ar = strtr( $fa, array( 'ی' => 'ي', 'ک' => 'ك' ) );
        return array_values( array_unique( array_filter( array( $fa, $ar ) ) ) );
    }
}

Raavi_Seller_Ajax_Search_Plugin::instance();
