<?php
/**
 * Movie Carousel Schema Template (Single Movie Schema).
 *
 * @package Schema Pro
 * @since 1.0.0
 */

if ( ! class_exists( 'BSF_AIOSRS_Pro_Schema_Movie_Carousel' ) ) {

	/**
	 * Movie Carousel Schema Renderer.
	 *
	 * @since 1.0.0
	 */
	class BSF_AIOSRS_Pro_Schema_Movie_Carousel {

		/**
		 * Render Schema.
		 *
		 * @param  array<string, mixed> $data Meta Data.
		 * @param  array<string, mixed> $post Current Post Array.
		 * @return array<string, mixed>
		 */
		public static function render( array $data, array $post ): array {
			$schema = array();
			
			if ( ! isset( $data['movies'][0]['name'] ) || empty( $data['movies'][0]['name'] ) ) {
				return $schema;
			}
			
			$schema['@context'] = 'https://schema.org';
			$schema['@type']    = 'ItemList';
			
			$list_items = array();
			
			foreach ( $data['movies'] as $index => $movie_data ) {
				if ( empty( $movie_data['name'] ) ) {
					continue;
				}
				
				$movie_schema = array(
					'@type'    => 'ListItem',
					'position' => $index + 1,
					'item'     => array(
						'@type' => 'Movie',
					),
				);
				
				// Movie name.
				if ( ! empty( $movie_data['name'] ) ) {
					$movie_schema['item']['name'] = wp_strip_all_tags( (string) $movie_data['name'] );
				}
				
				// URL.
				if ( ! empty( $movie_data['url'] ) ) {
					$movie_schema['item']['url'] = esc_url_raw( $movie_data['url'] );
				}
				
				// Image schema - handle different possible image data structures.
				if ( isset( $movie_data['image'] ) && ! empty( $movie_data['image'] ) ) {
					// Try to get the image URL using get_image_schema.
					$image_result = BSF_AIOSRS_Pro_Schema_Template::get_image_schema( $movie_data['image'], 'URL' );
					
					// If get_image_schema returns empty or array, try direct access.
					if ( empty( $image_result ) || is_array( $image_result ) ) {
						if ( is_array( $movie_data['image'] ) && isset( $movie_data['image'][0] ) ) {
							// Direct URL from array.
							$movie_schema['item']['image'] = esc_url( $movie_data['image'][0] );
						} elseif ( is_string( $movie_data['image'] ) ) {
							// Direct URL string.
							$movie_schema['item']['image'] = esc_url( $movie_data['image'] );
						}
					} else {
						// Use the result from get_image_schema.
						$movie_schema['item']['image'] = $image_result;
					}
				}
				
				// Fallback to featured image if no movie image was set.
				if ( ! isset( $movie_schema['item']['image'] ) || empty( $movie_schema['item']['image'] ) ) {
					$featured_image = wp_get_attachment_image_src( get_post_thumbnail_id( $post['ID'] ), 'full' );
					if ( ! empty( $featured_image ) && isset( $featured_image[0] ) ) {
						$movie_schema['item']['image'] = esc_url( $featured_image[0] );
					}
				}
				
				// Release date.
				if ( ! empty( $movie_data['dateCreated'] ) ) {
					$movie_schema['item']['dateCreated'] = wp_strip_all_tags( (string) $movie_data['dateCreated'] );
				}
				
				// Director.
				if ( ! empty( $movie_data['director-name'] ) ) {
					$movie_schema['item']['director'] = array(
						'@type' => 'Person',
						'name'  => wp_strip_all_tags( (string) $movie_data['director-name'] ),
					);
				}
				
				// Individual review schema.
				if ( ! empty( $movie_data['review-rating'] ) ) {
					$review_schema = array(
						'@type'        => 'Review',
						'reviewRating' => array(
							'@type'       => 'Rating',
							'ratingValue' => (float) $movie_data['review-rating'],
						),
					);
					
					// Add author if available.
					if ( ! empty( $movie_data['review-author'] ) ) {
						$review_schema['author'] = array(
							'@type' => 'Person',
							'name'  => wp_strip_all_tags( (string) $movie_data['review-author'] ),
						);
					}
					
					$movie_schema['item']['review'] = $review_schema;
				}
				
				// Aggregate rating.
				$aggregate_rating = null;
				$rating_count     = 0;
				
				// Use aggregate-rating if available, otherwise fallback to rating.
				if ( ! empty( $movie_data['aggregate-rating'] ) ) {
					$aggregate_rating = (float) $movie_data['aggregate-rating'];
				} elseif ( ! empty( $movie_data['rating'] ) ) {
					$aggregate_rating = (float) $movie_data['rating'];
				}
				
				// Use rating-count if available, otherwise try review-count.
				if ( ! empty( $movie_data['rating-count'] ) ) {
					$rating_count = (int) $movie_data['rating-count'];
				} elseif ( ! empty( $movie_data['review-count'] ) ) {
					$rating_count = (int) $movie_data['review-count'];
				}
				
				// Set aggregateRating if we have rating data.
				if ( $aggregate_rating !== null && $rating_count > 0 ) {
					$movie_schema['item']['aggregateRating'] = array(
						'@type'       => 'AggregateRating',
						'ratingValue' => $aggregate_rating,
						'ratingCount' => $rating_count,
						'bestRating'  => 100,
						'worstRating' => 1,
					);
				}
				
				$list_items[] = $movie_schema;
			}
			
			if ( ! empty( $list_items ) ) {
				$schema['itemListElement'] = $list_items;
			}
			
			return apply_filters( 'wp_schema_pro_schema_movie_carousel', $schema, $data, $post );
		}
		
	}
}
