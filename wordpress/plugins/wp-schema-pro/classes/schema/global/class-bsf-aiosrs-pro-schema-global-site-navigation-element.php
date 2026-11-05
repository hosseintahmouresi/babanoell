<?php
/**
 * Schemas Template.
 *
 * @package Schema Pro
 * @since 1.1.0
 */

if ( ! class_exists( 'BSF_AIOSRS_Pro_Schema_Global_Site_Navigation_Element' ) ) {

	/**
	 * AIOSRS Schemas Initialization
	 *
	 * @since 1.1.0
	 */
	class BSF_AIOSRS_Pro_Schema_Global_Site_Navigation_Element {

		/**
		 * Render Schema.
		 *
		 * @param  array $post Current Post Array.
		 * @return array
		 */
		public static function render( $post ) {
			$schema = array();

			$names = array();
			$urls  = array();

			$settings = BSF_AIOSRS_Pro_Helper::$settings['wp-schema-pro-global-schemas'];
			if ( isset( $settings['site-navigation-element'] ) && ! empty( $settings['site-navigation-element'] ) ) {
				$navigation_links = wp_get_nav_menu_items( $settings['site-navigation-element'] );

				if ( $navigation_links ) {
					foreach ( $navigation_links as $link ) {
						$names[] = wp_strip_all_tags( $link->title );
						$urls[]  = esc_url( $link->url );
					}
				}
			}

			/**
			* Convert normal array into associative array
			*/
			$combine_array = array_combine( $names, $urls );
			$new_arr[]     = array();
			$j             = 0;
			foreach ( $combine_array as $key => $value ) {
				$new_arr[ $j ]['name'] = $key;
				$new_arr[ $j ]['url']  = $value;
				$j++;
			}

				$schema['@context'] = 'https://schema.org';

			if ( isset( $new_arr ) && ! empty( $new_arr ) ) {
				$graph_index = 0;
				foreach ( $new_arr as $key2 => $value2 ) {
					// Skip navigation items with empty URLs to prevent phantom /item URLs
					if ( empty( $value2['url'] ) || ! isset( $value2['url'] ) ) {
						continue;
					}
					
					$schema['@graph'][ $graph_index ]['@context'] = 'https://schema.org';
					$schema['@graph'][ $graph_index ]['@type']    = 'SiteNavigationElement';
					$schema['@graph'][ $graph_index ]['id']       = 'site-navigation';
					$schema['@graph'][ $graph_index ]['name']     = isset( $value2['name'] ) ? $value2['name'] : '';
					$schema['@graph'][ $graph_index ]['url']      = esc_url( $value2['url'] );
					$graph_index++;
				}
			}

			return apply_filters( 'wp_schema_pro_global_schema_site_navigation_element', $schema, $post );
		}

	}
}
