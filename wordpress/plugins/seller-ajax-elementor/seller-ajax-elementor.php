<?php
/**
 * Plugin Name: Seller AJAX Search for Elementor
 * Description: AJAX title search for the seller post type, connected to an Elementor Pro Loop Grid via the official Query ID hook.
 * Version: 1.0.0
 * Author: Raavi Custom
 * Requires at least: 6.2
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class SAS_Elementor_Seller_Search {
    private const QUERY_ID       = 'seller_ajax';
    private const POST_TYPE      = 'seller';
    private const REQUEST_PARAM  = 'seller_search';
    private const SEARCH_CLASS   = '.seller-ajax-search';
    private const RESULTS_CLASS  = '.seller-ajax-results';
    private const QUERY_VAR      = 'sas_seller_title_term';

    public function __construct() {
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );

        // Official Elementor custom-query hook for the Loop Grid Query ID.
        add_action( 'elementor/query/' . self::QUERY_ID, [ $this, 'filter_elementor_query' ], 10, 2 );

        // Scope SQL changes only to our marked Elementor query.
        add_filter( 'posts_where', [ $this, 'filter_posts_where' ], 20, 2 );
        add_filter( 'posts_orderby', [ $this, 'filter_posts_orderby' ], 20, 2 );
    }

    public function enqueue_assets(): void {
        $base = plugin_dir_url( __FILE__ );

        wp_enqueue_style(
            'sas-elementor-seller-search',
            $base . 'assets/seller-ajax-search.css',
            [],
            '1.0.0'
        );

        wp_enqueue_script(
            'sas-elementor-seller-search',
            $base . 'assets/seller-ajax-search.js',
            [],
            '1.0.0',
            true
        );

        wp_localize_script(
            'sas-elementor-seller-search',
            'SASSellerSearch',
            [
                'searchSelector'  => self::SEARCH_CLASS,
                'resultsSelector' => self::RESULTS_CLASS,
                'requestParam'    => self::REQUEST_PARAM,
                'debounce'        => 400,
                'minChars'        => 1,
                'errorMessage'    => 'خطا در دریافت نتایج. لطفاً دوباره تلاش کنید.',
            ]
        );
    }

    /**
     * Keep the Loop Grid on seller posts and add our title-search query marker.
     */
    public function filter_elementor_query( \WP_Query $query, $widget = null ): void {
        $query->set( 'post_type', self::POST_TYPE );
        $query->set( 'post_status', 'publish' );

        $term = $this->get_request_term();
        if ( '' === $term ) {
            return;
        }

        // Disable WordPress' broad title/content/excerpt search. We apply a precise title-only search below.
        $query->set( 's', '' );
        $query->set( self::QUERY_VAR, $term );
    }

    /**
     * Restrict the marked query to seller titles only.
     * Each entered token must exist in the title; Persian/Arabic ی/ي and ک/ك variants are tolerated.
     */
    public function filter_posts_where( string $where, \WP_Query $query ): string {
        $term = (string) $query->get( self::QUERY_VAR );
        if ( '' === $term ) {
            return $where;
        }

        global $wpdb;

        $tokens = preg_split( '/[\s\x{200C}]+/u', $this->normalize_persian( $term ), -1, PREG_SPLIT_NO_EMPTY );
        if ( empty( $tokens ) ) {
            return $where;
        }

        $token_groups = [];

        foreach ( $tokens as $token ) {
            $variants = $this->text_variants( $token );
            $or_parts = [];

            foreach ( $variants as $variant ) {
                $like       = '%' . $wpdb->esc_like( $variant ) . '%';
                $or_parts[] = $wpdb->prepare( "{$wpdb->posts}.post_title LIKE %s", $like );
            }

            if ( $or_parts ) {
                $token_groups[] = '(' . implode( ' OR ', $or_parts ) . ')';
            }
        }

        if ( $token_groups ) {
            $where .= ' AND (' . implode( ' AND ', $token_groups ) . ')';
        }

        return $where;
    }

    /**
     * Rank exact title first, then titles starting with the phrase, then other contains matches.
     */
    public function filter_posts_orderby( string $orderby, \WP_Query $query ): string {
        $term = (string) $query->get( self::QUERY_VAR );
        if ( '' === $term ) {
            return $orderby;
        }

        global $wpdb;

        $variants = $this->text_variants( $this->normalize_persian( $term ) );
        if ( empty( $variants ) ) {
            return $orderby;
        }

        $exact_parts = [];
        $start_parts = [];

        foreach ( $variants as $variant ) {
            $exact_parts[] = $wpdb->prepare( "{$wpdb->posts}.post_title = %s", $variant );
            $start_parts[] = $wpdb->prepare( "{$wpdb->posts}.post_title LIKE %s", $wpdb->esc_like( $variant ) . '%' );
        }

        return 'CASE '
            . 'WHEN (' . implode( ' OR ', $exact_parts ) . ') THEN 0 '
            . 'WHEN (' . implode( ' OR ', $start_parts ) . ') THEN 1 '
            . 'ELSE 2 END ASC, '
            . "{$wpdb->posts}.post_title ASC";
    }

    private function get_request_term(): string {
        if ( ! isset( $_GET[ self::REQUEST_PARAM ] ) ) {
            return '';
        }

        $raw = wp_unslash( $_GET[ self::REQUEST_PARAM ] );
        if ( ! is_scalar( $raw ) ) {
            return '';
        }

        return trim( sanitize_text_field( (string) $raw ) );
    }

    private function normalize_persian( string $text ): string {
        $text = strtr(
            $text,
            [
                'ي' => 'ی',
                'ى' => 'ی',
                'ك' => 'ک',
            ]
        );

        $text = preg_replace( '/[\x{200C}\s]+/u', ' ', $text );
        return trim( (string) $text );
    }

    private function text_variants( string $text ): array {
        $persian = $this->normalize_persian( $text );
        $arabic  = strtr(
            $persian,
            [
                'ی' => 'ي',
                'ک' => 'ك',
            ]
        );

        return array_values( array_unique( array_filter( [ $persian, $arabic ] ) ) );
    }
}

new SAS_Elementor_Seller_Search();
