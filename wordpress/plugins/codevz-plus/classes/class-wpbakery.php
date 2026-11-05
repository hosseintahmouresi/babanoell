<?php if ( ! defined( 'ABSPATH' ) ) {exit;} // Exit if accessed directly.

/**
 * WPBakery functions.
 */

class Codevz_WPBakery {

	use Codevz_Plus_Instance;

	public function __construct() {

		add_action( 'vc_before_init', [ $this, 'init' ], 11 );

	}

	public function init() {

		// Autocomplete actions for WPBakery query fields.
		add_filter( 'vc_autocomplete_cz_posts_filters_callback', [ $this, 'taxonomies_callback' ], 10, 1 );
		add_filter( 'vc_autocomplete_cz_posts_filters_render', [ $this, 'taxonomies_render' ], 10, 1 );

		$elements = [ 'posts', 'news_ticker' ];

		foreach( $elements as $element ) {

			add_filter( 'vc_autocomplete_cz_' . $element . '_cat_callback', [ $this, 'taxonomies_callback' ], 10, 1 );
			add_filter( 'vc_autocomplete_cz_' . $element . '_cat_render', [ $this, 'taxonomies_render' ], 10, 1 );

			add_filter( 'vc_autocomplete_cz_' . $element . '_cat_exclude_callback', [ $this, 'taxonomies_callback' ], 10, 1 );
			add_filter( 'vc_autocomplete_cz_' . $element . '_cat_exclude_render', [ $this, 'taxonomies_render' ], 10, 1 );

			add_filter( 'vc_autocomplete_cz_' . $element . '_tag_id_callback', [ $this, 'taxonomies_callback' ], 10, 1 );
			add_filter( 'vc_autocomplete_cz_' . $element . '_tag_id_render', [ $this, 'taxonomies_render' ], 10, 1 );

			add_filter( 'vc_autocomplete_cz_' . $element . '_tag_exclude_callback', [ $this, 'taxonomies_callback' ], 10, 1 );
			add_filter( 'vc_autocomplete_cz_' . $element . '_tag_exclude_render', [ $this, 'taxonomies_render' ], 10, 1 );

			add_filter( 'vc_autocomplete_cz_' . $element . '_post_type_callback', [ $this, 'post_type_callback' ], 10, 1 );
			add_filter( 'vc_autocomplete_cz_' . $element . '_post_type_render', [ $this, 'same_search_string' ], 10, 1 );

			add_filter( 'vc_autocomplete_cz_' . $element . '_post__in_callback', [ $this, 'post__in_callback' ], 10, 1 );
			add_filter( 'vc_autocomplete_cz_' . $element . '_post__in_render', [ $this, 'same_search_string' ], 10, 1 );

			add_filter( 'vc_autocomplete_cz_' . $element . '_author__in_callback', [ $this, 'author__in_callback' ], 10, 1 );
			add_filter( 'vc_autocomplete_cz_' . $element . '_author__in_render', [ $this, 'author__in_render' ], 10, 1 );

		}

	}

	/**
	 * VC Autocomplete callback for saved taxonomies values
	 * 
	 * @return array
	 */
	public static function taxonomies_render( $term ) {

		$data = false;

		$terms = get_terms( array( 'include' => array( $term['value'] ), 'hide_empty' => false ) );

		if ( is_array( $terms ) && 1 === count( $terms ) ) {

			$term = $terms[0];
			$data = vc_get_term_object( $term );

			if ( ! empty( $data[ 'group' ] ) ) {
				$data[ 'label' ] .= ' (' .$data[ 'group_id' ] . ')';
			}

		}

		return $data;

	}

	/**
	 * VC Autocomplete taxonomies search process
	 * 
	 * @return string
	 */
	public static function taxonomies_callback( $search_string ) {

		$data = [];

		$vc_taxonomies = get_terms( array( 'hide_empty' => false, 'search' => $search_string ) );

		if ( is_array( $vc_taxonomies ) && ! empty( $vc_taxonomies ) ) {
			foreach ( $vc_taxonomies as $t ) {
				if ( is_object( $t ) ) {

					$new_data = vc_get_term_object( $t );

					if ( ! empty( $new_data[ 'group' ] ) ) {
						$new_data[ 'label' ] .= ' (' .$new_data[ 'group_id' ] . ')';
					}
					$data[] = $new_data;
				}
			}
		}

		return $data;
	}

	/**
	 * VC Autocomplete callback for saved post types values
	 * 
	 * @return array
	 */
	public static function post_type_callback( $term ) {

		// Post types
		$cpts = get_post_types( array( 'public' => true ) );

		// Custom post type UI
		if ( function_exists( 'cptui_get_post_type_slugs' ) ) {
			$cptui = cptui_get_post_type_slugs();
			if ( is_array( $cptui ) ) {
				$cpts = wp_parse_args( $cptui, $cpts );
			}
		}

		$data = [];
		foreach ( $cpts as $cpt ) {
			if ( Codevz_Plus::contains( $cpt, $term ) ) {
				$data[] = [
					'label' => $cpt,
					'value' => $cpt
				];
			}
		}

		return $data;
	}

	/**
	 * VC Autocomplete return same search process
	 * 
	 * @return string
	 */
	public static function same_search_string( $search_string ) {

		if ( isset( $search_string[ 'value' ] ) && is_numeric( $search_string[ 'value' ] ) ) {
			$search_string[ 'label' ] = get_the_title( $search_string[ 'value' ] );
		}

		return $search_string;

	}

	/**
	 * VC Autocomplete callback for saved posts values
	 * 
	 * @return array
	 */
	public static function post__in_callback( $term ) {

		$query = new WP_Query([ 
			's' 				=> $term,
			'post_type' 		=> 'any',
			'posts_per_page' 	=> 20
		]);

		$data = [];
		if ( $query->have_posts() ) {
			while ( $query->have_posts() ) {
				$query->the_post();

				$data[] = [
					'label' => get_the_title(),
					'value' => get_the_ID(),
					'group' => get_post_type()
				];
			}
		}

		wp_reset_postdata();
		wp_reset_query();

		return $data;
	}

	/**
	 * VC Autocomplete callback for saved authors values
	 * 
	 * @return array
	 */
	public static function author__in_callback( $term ) {

		$users = get_users([
		       'orderby' => 'post_count',
		       'order'   => 'DESC',
		       'fields'  => [ 'ID', 'user_login' ],
		]);

		$data = [];
		foreach ( $users as $user ) {
		       if ( isset( $user->user_login ) && Codevz_Plus::contains( $user->user_login, $term ) ) {
		               $data[] = [
		                       'label' => $user->user_login,
		                       'value' => $user->ID
		               ];
		       }
		}

		return $data;
	}

	/**
	 * VC Autocomplete return save authors names
	 * 
	 * @return string
	 */
	public static function author__in_render( $search_string ) {
		if ( isset( $search_string[ 'value' ] ) ) {
			$search_string[ 'label' ] = get_the_author_meta( 'user_login', $search_string[ 'value' ] );
		}

		return $search_string;
	}

	/**
	 * Inverted radius CSS output.
	 * 
	 * @return string
	 */
	public static function inverted_radius_css( $atts ) {

		if ( empty( $atts['ir_position'] ) ) {
			return $atts;
		}

		$atts[ 'sk_ir' ] = '';
		$atts[ 'sk_ir_mobile' ] = '';

		// Inverted radius CSS.
		$attributes = [ 'radius', 'size', 'x', 'y' ];

		foreach( $attributes as $key ) {

		    $sub = substr( $key, 0, 1 );
		    $key = 'ir_' . $key;

		    if ( ! empty( $atts[ $key ] ) ) {
		        $atts[ 'sk_ir' ] .= '--' . $sub . ':' . esc_html( $atts[ $key ] ) . ';';
		    }

		    if ( ! empty( $atts[ $key . '_mobile' ] ) ) {
		        $atts[ 'sk_ir_mobile' ] .= '--' . $sub . ':' . esc_html( $atts[ $key . '_mobile' ] ) . ';';
		    }

		}

		return $atts;

	}

}

Codevz_WPBakery::instance();