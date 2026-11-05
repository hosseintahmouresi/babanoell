<?php
/**
 * Schemas Template.
 *
 * @package Schema Pro
 * @since 1.0.0
 */

if ( ! class_exists( 'BSF_AIOSRS_Pro_Schema_Review' ) ) {

	/**
	 * AIOSRS Schemas Initialization
	 *
	 * @since 1.0.0
	 */
	class BSF_AIOSRS_Pro_Schema_Review {

		/**
		 * Render Schema.
		 *
		 * @param  array $data Meta Data.
		 * @param  array $post Current Post Array.
		 * @return array
		 */
		public static function render( $data, $post ) {
			$schema             = array();
			$schema['@context'] = 'https://schema.org';
			$schema['@type']    = 'Review';

			// Auto-detect rating scale based on rating values
			$rating_scale = self::detect_rating_scale( $data );
			$rating_scale_worst = $rating_scale['worst'];
			$rating_scale_best = $rating_scale['best'];

			/* start Book schema fields */

			$data['schema-type'] = isset( $data['schema-type'] ) ? $data['schema-type'] : '';

			switch ( $data['schema-type'] ) {
				case 'bsf-aiosrs-book':
					$schema['itemReviewed']['@type'] = 'Book';
					$schema['itemReviewed']['name']  = ! empty( $data['bsf-aiosrs-book-name'] ) ? wp_strip_all_tags( (string) $data['bsf-aiosrs-book-name'] ) : null;
					if ( isset( $data['bsf-aiosrs-book-author'] ) && ! empty( $data['bsf-aiosrs-book-author'] ) ) {
						$schema['itemReviewed']['author']['@type']  = 'Person';
						$schema['itemReviewed']['author']['name']   = wp_strip_all_tags( (string) $data['bsf-aiosrs-book-author'] );
						$schema['itemReviewed']['author']['sameAs'] = wp_strip_all_tags( (string) $data['bsf-aiosrs-book-same-As'] );

					}
					$schema['itemReviewed']['isbn'] = ! empty( $data['bsf-aiosrs-book-serial-number'] ) ? wp_strip_all_tags( (string) $data['bsf-aiosrs-book-serial-number'] ) : null;
					$schema['description']          = ! empty( $data['bsf-aiosrs-book-description'] ) ? wp_strip_all_tags( (string) $data['bsf-aiosrs-book-description'] ) : null;
					$book_url                       = get_permalink( $post['ID'] );
					if ( isset( $book_url ) && ! empty( $book_url ) ) {
						$schema['url'] = esc_url( $book_url );
					}
					// Add aggregateRating for Book
					if ( ( isset( $data['bsf-aiosrs-book-rating'] ) && ! empty( $data['bsf-aiosrs-book-rating'] ) ) ||
						( isset( $data['bsf-aiosrs-book-review-count'] ) && ! empty( $data['bsf-aiosrs-book-review-count'] ) ) ) {
						$schema['itemReviewed']['aggregateRating']['@type']       = 'AggregateRating';
						$schema['itemReviewed']['aggregateRating']['ratingValue'] = ! empty( $data['bsf-aiosrs-book-rating'] ) ? wp_strip_all_tags( (string) $data['bsf-aiosrs-book-rating'] ) : null;
						$schema['itemReviewed']['aggregateRating']['reviewCount'] = ! empty( $data['bsf-aiosrs-book-review-count'] ) ? wp_strip_all_tags( (string) $data['bsf-aiosrs-book-review-count'] ) : null;
						$schema['itemReviewed']['aggregateRating']['bestRating']  = $rating_scale_best;
						$schema['itemReviewed']['aggregateRating']['worstRating'] = $rating_scale_worst;
					}
					break;
				case 'bsf-aiosrs-course':
					$schema['itemReviewed']['@type']       = 'Course';
					$schema['itemReviewed']['name']        = ! empty( $data['bsf-aiosrs-course-name'] ) ? wp_strip_all_tags( (string) $data['bsf-aiosrs-course-name'] ) : null;
					$schema['itemReviewed']['description'] = ! empty( $data['bsf-aiosrs-course-description'] ) ? wp_strip_all_tags( (string) $data['bsf-aiosrs-course-description'] ) : null;

					if ( isset( $data['bsf-aiosrs-course-orgnization-name'] ) && ! empty( $data['bsf-aiosrs-course-orgnization-name'] ) ) {
						$schema['itemReviewed']['provider']['@type'] = 'Organization';
						$schema['itemReviewed']['provider']['name']  = wp_strip_all_tags( (string) $data['bsf-aiosrs-course-orgnization-name'] );
					}
					//phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase.
					// Initialize 'hasCourseInstance' array.
					$hasCourseInstance = array(); 
					$schema['itemReviewed']['hasCourseInstance']['course-instance'] = ! empty( $data['bsf-aiosrs-course-course-instance'] ) ? wp_strip_all_tags( (string) $data['bsf-aiosrs-course-course-instance'] ) : null;
					$schema['itemReviewed']['hasCourseInstance']['courseMode']      = ! empty( $data['bsf-aiosrs-course-courseMode'] ) ? wp_strip_all_tags( (string) $data['bsf-aiosrs-course-courseMode'] ) : null;
					$schema['itemReviewed']['hasCourseInstance']['courseWorkload']  = ! empty( $data['bsf-aiosrs-course-course-workload'] ) ? wp_strip_all_tags( (string) $data['bsf-aiosrs-course-course-workload'] ) : null;

					// Initialize 'offers' array.
					$offers                                       = array();
					$schema['itemReviewed']['offers']['price']    = ! empty( $data['bsf-aiosrs-course-price'] ) ? wp_strip_all_tags( (string) $data['bsf-aiosrs-course-price'] ) : null;
					$schema['itemReviewed']['offers']['currency'] = ! empty( $data['bsf-aiosrs-course-currency'] ) ? wp_strip_all_tags( (string) $data['bsf-aiosrs-course-currency'] ) : null;
					$schema['itemReviewed']['offers']['category'] = ! empty( $data['bsf-aiosrs-course-category'] ) ? wp_strip_all_tags( (string) $data['bsf-aiosrs-course-category'] ) : null;
					// Add aggregateRating for Course
					if ( ( isset( $data['bsf-aiosrs-course-rating'] ) && ! empty( $data['bsf-aiosrs-course-rating'] ) ) ||
						( isset( $data['bsf-aiosrs-course-review-count'] ) && ! empty( $data['bsf-aiosrs-course-review-count'] ) ) ) {
						$schema['itemReviewed']['aggregateRating']['@type']       = 'AggregateRating';
						$schema['itemReviewed']['aggregateRating']['ratingValue'] = ! empty( $data['bsf-aiosrs-course-rating'] ) ? wp_strip_all_tags( (string) $data['bsf-aiosrs-course-rating'] ) : null;
						$schema['itemReviewed']['aggregateRating']['reviewCount'] = ! empty( $data['bsf-aiosrs-course-review-count'] ) ? wp_strip_all_tags( (string) $data['bsf-aiosrs-course-review-count'] ) : null;
						$schema['itemReviewed']['aggregateRating']['bestRating']  = $rating_scale_best;
						$schema['itemReviewed']['aggregateRating']['worstRating'] = $rating_scale_worst;
					}
					break;
										
				case 'bsf-aiosrs-event':
					$schema['itemReviewed']['@type']       = 'Event';
					$schema['itemReviewed']['name']        = ! empty( $data['bsf-aiosrs-event-name'] ) ? wp_strip_all_tags( (string) $data['bsf-aiosrs-event-name'] ) : null;
					$schema['itemReviewed']['description'] = ! empty( $data['bsf-aiosrs-event-description'] ) ? wp_strip_all_tags( (string) $data['bsf-aiosrs-event-description'] ) : null;
					if ( isset( $data['bsf-aiosrs-event-image'] ) && ! empty( $data['bsf-aiosrs-event-image'] ) ) {
						$schema['itemReviewed']['image'] = BSF_AIOSRS_Pro_Schema_Template::get_image_schema( $data['bsf-aiosrs-event-image'] );
					}
					if ( isset( $data['bsf-aiosrs-event-start-date'] ) && ! empty( $data['bsf-aiosrs-event-start-date'] ) ) {
						if ( 'OfflineEventAttendanceMode' !== $data['bsf-aiosrs-event-event-attendance-mode'] ) {
							$start_date                          = gmdate( DATE_ISO8601, strtotime( $data['bsf-aiosrs-event-start-date'] ) );
							$schema['itemReviewed']['startDate'] = wp_strip_all_tags( (string) $start_date );
						} else {
							$schema['itemReviewed']['startDate'] = wp_strip_all_tags( (string) $data['bsf-aiosrs-event-start-date'] );
						}
					}
					$schema['itemReviewed']['endDate']     = ! empty( $data['bsf-aiosrs-event-end-date'] ) ? wp_strip_all_tags( (string) $data['bsf-aiosrs-event-end-date'] ) : null;
					$schema['itemReviewed']['eventStatus'] = ! empty( $data['bsf-aiosrs-event-event-status'] ) ? wp_strip_all_tags( (string) $data['bsf-aiosrs-event-event-status'] ) : null;

					$schema['itemReviewed']['eventAttendanceMode'] = ! empty( $data['bsf-aiosrs-event-event-attendance-mode'] ) ? wp_strip_all_tags( (string) $data['bsf-aiosrs-event-event-attendance-mode'] ) : null;

					$schema['itemReviewed']['previousStartDate'] = ! empty( $data['bsf-aiosrs-event-previous-date'] ) && 'EventRescheduled' === $data['bsf-aiosrs-event-event-status'] ? wp_strip_all_tags( (string) $data['bsf-aiosrs-event-previous-date'] ) : null;
					if ( isset( $data['bsf-aiosrs-event-online-location'] ) && ! empty( $data['bsf-aiosrs-event-online-location'] ) &&
					( 'OfflineEventAttendanceMode' !== $data['bsf-aiosrs-event-event-attendance-mode'] ) ||
					( 'MixedEventAttendanceMode' === $data['bsf-aiosrs-event-event-attendance-mode'] ) ) {
						$schema['itemReviewed']['location']['@type'] = 'VirtualLocation';
						$schema['itemReviewed']['location']['url']   = esc_url( $data['bsf-aiosrs-event-online-location'] );
					}
					if ( isset( $data['bsf-aiosrs-event-performer'] ) && ! empty( $data['bsf-aiosrs-event-performer'] ) ) {
						$schema['itemReviewed']['performer']['@type'] = 'Person';
						$schema['itemReviewed']['performer']['name']  = wp_strip_all_tags( (string) $data['bsf-aiosrs-event-performer'] );
					}
					if ( isset( $data['bsf-aiosrs-event-location'] ) && ! empty( $data['bsf-aiosrs-event-location'] ) && 'OnlineEventAttendanceMode' !== $data['bsf-aiosrs-event-event-attendance-mode'] ) {
						$schema['itemReviewed']['location']['@type'] = 'Place';
						$schema['itemReviewed']['location']['name']  = wp_strip_all_tags( (string) $data['bsf-aiosrs-event-location'] );
					}
					if ( ( ( isset( $data['bsf-aiosrs-event-location-street'] ) && ! empty( $data['bsf-aiosrs-event-location-street'] ) ) ||
					( isset( $data['bsf-aiosrs-event-location-locality'] ) && ! empty( $data['bsf-aiosrs-event-location-locality'] ) ) ||
					( isset( $data['bsf-aiosrs-event-location-postal'] ) && ! empty( $data['bsf-aiosrs-event-location-postal'] ) ) ||
					( isset( $data['bsf-aiosrs-event-location-region'] ) && ! empty( $data['bsf-aiosrs-event-location-region'] ) ) ||
					( isset( $data['bsf-aiosrs-event-location-country'] ) && ! empty( $data['bsf-aiosrs-event-location-country'] ) ) ) && ( 'OnlineEventAttendanceMode' !== $data['bsf-aiosrs-event-event-attendance-mode'] ) ) {
						$schema['itemReviewed']['location']['address']['@type'] = 'PostalAddress';

						$schema['itemReviewed']['location']['address']['streetAddress']   = ! empty( $data['bsf-aiosrs-event-location-street'] ) ? wp_strip_all_tags( (string) $data['bsf-aiosrs-event-location-street'] ) : null;
						$schema['itemReviewed']['location']['address']['addressLocality'] = ! empty( $data['bsf-aiosrs-event-location-locality'] ) ? wp_strip_all_tags( (string) $data['bsf-aiosrs-event-location-locality'] ) : null;
						$schema['itemReviewed']['location']['address']['postalCode']      = ! empty( $data['bsf-aiosrs-event-location-postal'] ) ? wp_strip_all_tags( (string) $data['bsf-aiosrs-event-location-postal'] ) : null;
						$schema['itemReviewed']['location']['address']['addressRegion']   = ! empty( $data['bsf-aiosrs-event-location-region'] ) ? wp_strip_all_tags( (string) $data['bsf-aiosrs-event-location-region'] ) : null;
						$schema['itemReviewed']['location']['address']['addressCountry']  = ! empty( $data['bsf-aiosrs-event-location-country'] ) ? wp_strip_all_tags( (string) $data['bsf-aiosrs-event-location-country'] ) : null;
					}
					$schema['itemReviewed']['offers']['@type'] = 'Offer';

					if ( ( isset( $data['bsf-aiosrs-event-avail'] ) && ! empty( $data['bsf-aiosrs-event-avail'] ) ) ||
						( isset( $data['bsf-aiosrs-event-currency'] ) && ! empty( $data['bsf-aiosrs-event-currency'] ) ) ||
						( isset( $data['bsf-aiosrs-event-valid-from'] ) && ! empty( $data['bsf-aiosrs-event-valid-from'] ) ) ||
						( isset( $data['bsf-aiosrs-event-ticket-buy-url'] ) && ! empty( $data['bsf-aiosrs-event-ticket-buy-url'] ) ) ) {
						if ( isset( $data['bsf-aiosrs-event-ticket-buy-url'] ) && ! empty( $data['bsf-aiosrs-event-ticket-buy-url'] ) ) {
							$schema['itemReviewed']['offers']['url'] = esc_url( $data['bsf-aiosrs-event-ticket-buy-url'] );
						}
						$schema['itemReviewed']['offers']['price']         = ! empty( $data['bsf-aiosrs-event-price'] ) ? wp_strip_all_tags( (string) $data['bsf-aiosrs-event-price'] ) : null;
						$schema['itemReviewed']['offers']['availability']  = ! empty( $data['bsf-aiosrs-event-avail'] ) ? wp_strip_all_tags( (string) $data['bsf-aiosrs-event-avail'] ) : null;
						$schema['itemReviewed']['offers']['priceCurrency'] = ! empty( $data['bsf-aiosrs-event-currency'] ) ? wp_strip_all_tags( (string) $data['bsf-aiosrs-event-currency'] ) : null;
						$schema['itemReviewed']['offers']['validFrom']     = ! empty( $data['bsf-aiosrs-event-valid-from'] ) ? wp_strip_all_tags( (string) $data['bsf-aiosrs-event-valid-from'] ) : null;
					}
					if ( ( isset( $data['bsf-aiosrs-event-event-organizer-name'] ) && ! empty( $data['bsf-aiosrs-event-event-organizer-name'] ) ) ||
						( isset( $data['bsf-aiosrs-event-event-organizer-url'] ) && ! empty( $data['bsf-aiosrs-event-event-organizer-url'] ) ) ) {

						$schema['itemReviewed']['organizer']['@type'] = 'Organization';

						$schema['itemReviewed']['organizer']['name'] = ! empty( $data['bsf-aiosrs-event-event-organizer-name'] ) ? wp_strip_all_tags( (string) $data['bsf-aiosrs-event-event-organizer-name'] ) : null;
						if ( isset( $data['bsf-aiosrs-event-event-organizer-url'] ) && ! empty( $data['bsf-aiosrs-event-event-organizer-url'] ) ) {
							$schema['itemReviewed']['organizer']['url'] = esc_url( $data['bsf-aiosrs-event-event-organizer-url'] );
						}
					}
					// Add aggregateRating for Event
					if ( ( isset( $data['bsf-aiosrs-event-rating'] ) && ! empty( $data['bsf-aiosrs-event-rating'] ) ) ||
						( isset( $data['bsf-aiosrs-event-review-count'] ) && ! empty( $data['bsf-aiosrs-event-review-count'] ) ) ) {
						$schema['itemReviewed']['aggregateRating']['@type']       = 'AggregateRating';
						$schema['itemReviewed']['aggregateRating']['ratingValue'] = ! empty( $data['bsf-aiosrs-event-rating'] ) ? wp_strip_all_tags( (string) $data['bsf-aiosrs-event-rating'] ) : null;
						$schema['itemReviewed']['aggregateRating']['reviewCount'] = ! empty( $data['bsf-aiosrs-event-review-count'] ) ? wp_strip_all_tags( (string) $data['bsf-aiosrs-event-review-count'] ) : null;
						$schema['itemReviewed']['aggregateRating']['bestRating']  = $rating_scale_best;
						$schema['itemReviewed']['aggregateRating']['worstRating'] = $rating_scale_worst;
					}
					break;
				case 'bsf-aiosrs-local-business':
					$schema['itemReviewed']['@type'] = 'LocalBusiness';
					$schema['itemReviewed']['name']  = ! empty( $data['bsf-aiosrs-local-business-name'] ) ? wp_strip_all_tags( (string) $data['bsf-aiosrs-local-business-name'] ) : null;
					if ( isset( $data['bsf-aiosrs-local-business-image'] ) && ! empty( $data['bsf-aiosrs-local-business-image'] ) ) {

						$schema['itemReviewed']['image'] = BSF_AIOSRS_Pro_Schema_Template::get_image_schema( $data['bsf-aiosrs-local-business-image'] );
					}
					$schema['itemReviewed']['telephone'] = ! empty( $data['bsf-aiosrs-local-business-telephone'] ) ? wp_strip_all_tags( (string) $data['bsf-aiosrs-local-business-telephone'] ) : null;
					if ( ( isset( $data['bsf-aiosrs-local-business-location-street'] ) && ! empty( $data['bsf-aiosrs-local-business-location-street'] ) ) ||
						( isset( $data['bsf-aiosrs-local-business-location-locality'] ) && ! empty( $data['bsf-aiosrs-local-business-location-locality'] ) ) ||
						( isset( $data['bsf-aiosrs-local-business-location-postal'] ) && ! empty( $data['bsf-aiosrs-local-business-location-postal'] ) ) ||
						( isset( $data['bsf-aiosrs-local-business-location-region'] ) && ! empty( $data['bsf-aiosrs-local-business-location-region'] ) ) ||
						( isset( $data['bsf-aiosrs-local-business-location-country'] ) && ! empty( $data['bsf-aiosrs-local-business-location-country'] ) ) ) {

						$schema['itemReviewed']['address']['@type'] = 'PostalAddress';

						$schema['itemReviewed']['address']['streetAddress']   = ! empty( $data['bsf-aiosrs-local-business-location-street'] ) ? wp_strip_all_tags( (string) $data['bsf-aiosrs-local-business-location-street'] ) : null;
						$schema['itemReviewed']['address']['addressLocality'] = ! empty( $data['bsf-aiosrs-local-business-location-locality'] ) ? wp_strip_all_tags( (string) $data['bsf-aiosrs-local-business-location-locality'] ) : null;
						$schema['itemReviewed']['address']['postalCode']      = ! empty( $data['bsf-aiosrs-local-business-location-postal'] ) ? wp_strip_all_tags( (string) $data['bsf-aiosrs-local-business-location-postal'] ) : null;
						$schema['itemReviewed']['address']['addressRegion']   = ! empty( $data['bsf-aiosrs-local-business-location-region'] ) ? wp_strip_all_tags( (string) $data['bsf-aiosrs-local-business-location-region'] ) : null;
						$schema['itemReviewed']['address']['addressCountry']  = ! empty( $data['bsf-aiosrs-local-business-location-country'] ) ? wp_strip_all_tags( (string) $data['bsf-aiosrs-local-business-location-country'] ) : null;
					}
					$schema['itemReviewed']['priceRange'] = ! empty( $data['bsf-aiosrs-local-business-price-range'] ) ? wp_strip_all_tags( (string) $data['bsf-aiosrs-local-business-price-range'] ) : null;
					// Add aggregateRating for LocalBusiness
					if ( ( isset( $data['bsf-aiosrs-local-business-rating'] ) && ! empty( $data['bsf-aiosrs-local-business-rating'] ) ) ||
						( isset( $data['bsf-aiosrs-local-business-review-count'] ) && ! empty( $data['bsf-aiosrs-local-business-review-count'] ) ) ) {
						$schema['itemReviewed']['aggregateRating']['@type']       = 'AggregateRating';
						$schema['itemReviewed']['aggregateRating']['ratingValue'] = ! empty( $data['bsf-aiosrs-local-business-rating'] ) ? wp_strip_all_tags( (string) $data['bsf-aiosrs-local-business-rating'] ) : null;
						$schema['itemReviewed']['aggregateRating']['reviewCount'] = ! empty( $data['bsf-aiosrs-local-business-review-count'] ) ? wp_strip_all_tags( (string) $data['bsf-aiosrs-local-business-review-count'] ) : null;
						$schema['itemReviewed']['aggregateRating']['bestRating']  = $rating_scale_best;
						$schema['itemReviewed']['aggregateRating']['worstRating'] = $rating_scale_worst;
					}
					break;
				case 'bsf-aiosrs-recipe':
					$schema['itemReviewed']['@type'] = 'Recipe';
					$schema['itemReviewed']['name']  = ! empty( $data['bsf-aiosrs-recipe-name'] ) ? wp_strip_all_tags( (string) $data['bsf-aiosrs-recipe-name'] ) : null;
					if ( isset( $data['bsf-aiosrs-recipe-image'] ) && ! empty( $data['bsf-aiosrs-recipe-image'] ) ) {
						$schema['itemReviewed']['image'] = BSF_AIOSRS_Pro_Schema_Template::get_image_schema( $data['bsf-aiosrs-recipe-image'] );
					}
					if ( isset( $data['bsf-aiosrs-recipe-author'] ) && ! empty( $data['bsf-aiosrs-recipe-author'] ) ) {
						$schema['itemReviewed']['author']['@type'] = 'Person';
						$schema['itemReviewed']['author']['name']  = wp_strip_all_tags( (string) $data['bsf-aiosrs-recipe-author'] );
					}
					$schema['itemReviewed']['description']    = ! empty( $data['bsf-aiosrs-recipe-description'] ) ? wp_strip_all_tags( (string) $data['bsf-aiosrs-recipe-description'] ) : null;
					$schema['itemReviewed']['prepTime']       = ! empty( $data['bsf-aiosrs-recipe-preperation-time'] ) ? wp_strip_all_tags( (string) $data['bsf-aiosrs-recipe-preperation-time'] ) : null;
					$schema['itemReviewed']['cookTime']       = ! empty( $data['bsf-aiosrs-recipe-cook-time'] ) ? wp_strip_all_tags( (string) $data['bsf-aiosrs-recipe-cook-time'] ) : null;
					$schema['itemReviewed']['keywords']       = ! empty( $data['bsf-aiosrs-recipe-recipe-keywords'] ) ? wp_strip_all_tags( (string) $data['bsf-aiosrs-recipe-recipe-keywords'] ) : null;
					$schema['itemReviewed']['recipeCategory'] = ! empty( $data['bsf-aiosrs-recipe-recipe-category'] ) ? wp_strip_all_tags( (string) $data['bsf-aiosrs-recipe-recipe-category'] ) : null;
					$schema['itemReviewed']['recipeCuisine']  = ! empty( $data['bsf-aiosrs-recipe-recipe-cuisine'] ) ? wp_strip_all_tags( (string) $data['bsf-aiosrs-recipe-recipe-cuisine'] ) : null;
					if ( ( isset( $data['bsf-aiosrs-recipe-rating'] ) && ! empty( $data['bsf-aiosrs-recipe-rating'] ) ) ||
					( isset( $data['bsf-aiosrs-recipe-review-count'] ) && ! empty( $data['bsf-aiosrs-recipe-review-count'] ) ) ) {
						$schema['itemReviewed']['aggregateRating']['@type']       = 'AggregateRating';
						$schema['itemReviewed']['aggregateRating']['ratingValue'] = ! empty( $data['bsf-aiosrs-recipe-rating'] ) ? wp_strip_all_tags( (string) $data['bsf-aiosrs-recipe-rating'] ) : null;
						$schema['itemReviewed']['aggregateRating']['reviewCount'] = ! empty( $data['bsf-aiosrs-recipe-review-count'] ) ? wp_strip_all_tags( (string) $data['bsf-aiosrs-recipe-review-count'] ) : null;
						$schema['itemReviewed']['aggregateRating']['bestRating']  = $rating_scale_best;
						$schema['itemReviewed']['aggregateRating']['worstRating'] = $rating_scale_worst;
					}
					if ( isset( $data['bsf-aiosrs-recipe-nutrition'] ) && ! empty( $data['bsf-aiosrs-recipe-nutrition'] ) ) {
						$schema['itemReviewed']['nutrition']['@type']    = 'NutritionInformation';
						$schema['itemReviewed']['nutrition']['calories'] = wp_strip_all_tags( (string) $data['bsf-aiosrs-recipe-nutrition'] );
					}
					if ( isset( $data['bsf-aiosrs-recipe-ingredients'] ) && ! empty( $data['bsf-aiosrs-recipe-ingredients'] ) ) {
						$recipe_ingredients = explode( ',', $data['bsf-aiosrs-recipe-ingredients'] );
						foreach ( $recipe_ingredients as $key => $value ) {
							$schema['itemReviewed']['recipeIngredient'][ $key ] = wp_strip_all_tags( (string) $value );
						}
					}
					if ( isset( $data['bsf-aiosrs-recipe-recipe-instructions'] ) && ! empty( $data['bsf-aiosrs-recipe-recipe-instructions'] ) ) {
								$recipe_instructions = explode( ',', $data['bsf-aiosrs-recipe-recipe-instructions'] );
						foreach ( $recipe_instructions as $key => $value ) {
							if ( isset( $value ) && ! empty( $value ) ) {
								$schema['itemReviewed']['recipeInstructions'][ $key ]['@type'] = 'HowToStep';
								$schema['itemReviewed']['recipeInstructions'][ $key ]['text']  = wp_strip_all_tags( (string) $value );
							}
						}
					}

					if ( isset( $data['bsf-aiosrs-recipe-video-name'] ) && ! empty( $data['bsf-aiosrs-recipe-video-name'] ) ) {
								$schema['itemReviewed']['video']['@type'] = 'VideoObject';
								$schema['itemReviewed']['video']['name']  = wp_strip_all_tags( (string) $data['bsf-aiosrs-recipe-video-name'] );
						$schema['itemReviewed']['video']['description']   = ! empty( $data['bsf-aiosrs-recipe-video-desc'] ) ? wp_strip_all_tags( (string) $data['bsf-aiosrs-recipe-video-desc'] ) : null;
						if ( isset( $data['bsf-aiosrs-recipe-video-image'] ) && ! empty( $data['bsf-aiosrs-recipe-video-image'] ) ) {
							$schema['itemReviewed']['video']['thumbnailUrl'] = BSF_AIOSRS_Pro_Schema_Template::get_image_schema( $data['bsf-aiosrs-recipe-video-image'], 'URL' );
						}
						if ( isset( $data['bsf-aiosrs-recipe-recipe-video-content-url'] ) && ! empty( $data['bsf-aiosrs-recipe-recipe-video-content-url'] ) ) {
							$schema['itemReviewed']['video']['contentUrl'] = esc_url( $data['bsf-aiosrs-recipe-recipe-video-content-url'] );
						}
						if ( isset( $data['bsf-aiosrs-recipe-recipe-video-embed-url'] ) && ! empty( $data['bsf-aiosrs-recipe-recipe-video-embed-url'] ) ) {
							$schema['itemReviewed']['video']['embedUrl'] = esc_url( $data['bsf-aiosrs-recipe-recipe-video-embed-url'] );
						}
						$schema['itemReviewed']['video']['duration']         = ! empty( $data['bsf-aiosrs-recipe-recipe-video-duration'] ) ? wp_strip_all_tags( (string) $data['bsf-aiosrs-recipe-recipe-video-duration'] ) : null;
						$schema['itemReviewed']['video']['uploadDate']       = ! empty( $data['bsf-aiosrs-recipe-recipe-video-upload-date'] ) ? wp_strip_all_tags( (string) $data['bsf-aiosrs-recipe-recipe-video-upload-date'] ) : null;
						$schema['itemReviewed']['video']['interactionCount'] = ! empty( $data['bsf-aiosrs-recipe-recipe-video-interaction-count'] ) ? wp_strip_all_tags( (string) $data['bsf-aiosrs-recipe-recipe-video-interaction-count'] ) : null;
						$schema['itemReviewed']['video']['expires']          = ! empty( $data['bsf-aiosrs-recipe-recipe-video-expires-date'] ) ? wp_strip_all_tags( (string) $data['bsf-aiosrs-recipe-recipe-video-expires-date'] ) : null;
					}
					break;
				case 'bsf-aiosrs-software-application':
					$schema['itemReviewed']['@type'] = 'SoftwareApplication';
					$schema['itemReviewed']['name']  = ! empty( $data['bsf-aiosrs-software-application-name'] ) ? wp_strip_all_tags( (string) $data['bsf-aiosrs-software-application-name'] ) : null;

					$schema['itemReviewed']['operatingSystem'] = ! empty( $data['bsf-aiosrs-software-application-operating-system'] ) ? wp_strip_all_tags( (string) $data['bsf-aiosrs-software-application-operating-system'] ) : null;

					$schema['itemReviewed']['applicationCategory'] = ! empty( $data['bsf-aiosrs-software-application-category'] ) ? wp_strip_all_tags( (string) $data['bsf-aiosrs-software-application-category'] ) : null;

					if ( ( isset( $data['bsf-aiosrs-software-application-rating'] ) && ! empty( $data['bsf-aiosrs-software-application-rating'] ) ) ||
						( isset( $data['bsf-aiosrs-software-application-review-count'] ) && ! empty( $data['bsf-aiosrs-software-application-review-count'] ) ) ) {

						$schema['itemReviewed']['aggregateRating']['@type'] = 'AggregateRating';

						$schema['itemReviewed']['aggregateRating']['ratingValue'] = ! empty( $data['bsf-aiosrs-software-application-rating'] ) ? wp_strip_all_tags( (string) $data['bsf-aiosrs-software-application-rating'] ) : null;
						$schema['itemReviewed']['aggregateRating']['reviewCount'] = ! empty( $data['bsf-aiosrs-software-application-review-count'] ) ? wp_strip_all_tags( (string) $data['bsf-aiosrs-software-application-review-count'] ) : null;
						$schema['itemReviewed']['aggregateRating']['bestRating']  = $rating_scale_best;
						$schema['itemReviewed']['aggregateRating']['worstRating'] = $rating_scale_worst;
					}
					if ( true === apply_filters( 'wp_schema_pro_remove_software_application_offers_review_type', true ) ) {
						$schema['itemReviewed']['offers']['@type'] = 'Offer';
						$schema['itemReviewed']['offers']['price'] = '0';

						$schema['itemReviewed']['offers']['price'] = ! empty( $data['bsf-aiosrs-software-application-price'] ) ? wp_strip_all_tags( (string) $data['bsf-aiosrs-software-application-price'] ) : null;

						$schema['itemReviewed']['offers']['priceCurrency'] = ! empty( $data['bsf-aiosrs-software-application-currency'] ) ? wp_strip_all_tags( (string) $data['bsf-aiosrs-software-application-currency'] ) : null;
					}
					break;
				case 'bsf-aiosrs-product':
					$schema['itemReviewed']['@type'] = 'Product';
					$schema['itemReviewed']['name']  = ! empty( $data['bsf-aiosrs-product-name'] ) ? wp_strip_all_tags( (string) $data['bsf-aiosrs-product-name'] ) : null;
					if ( isset( $data['bsf-aiosrs-product-image'] ) && ! empty( $data['bsf-aiosrs-product-image'] ) ) {
						$schema['itemReviewed']['image'] = BSF_AIOSRS_Pro_Schema_Template::get_image_schema( $data['bsf-aiosrs-product-image'] );
					}

					$schema['itemReviewed']['description'] = ! empty( $data['bsf-aiosrs-product-description'] ) ? wp_strip_all_tags( (string) $data['bsf-aiosrs-product-description'] ) : null;

					$schema['itemReviewed']['sku'] = ! empty( $data['bsf-aiosrs-product-sku'] ) ? wp_strip_all_tags( (string) $data['bsf-aiosrs-product-sku'] ) : null;
					$schema['itemReviewed']['mpn'] = ! empty( $data['bsf-aiosrs-product-mpn'] ) ? wp_strip_all_tags( (string) $data['bsf-aiosrs-product-mpn'] ) : null;
					if ( isset( $data['bsf-aiosrs-product-brand-name'] ) && ! empty( $data['bsf-aiosrs-product-brand-name'] ) ) {
						$schema['itemReviewed']['brand']['@type'] = 'Brand';
						$schema['itemReviewed']['brand']['name']  = wp_strip_all_tags( (string) $data['bsf-aiosrs-product-brand-name'] );
					}

					if ( ( isset( $data['bsf-aiosrs-product-rating'] ) && ! empty( $data['bsf-aiosrs-product-rating'] ) ) ||
						( isset( $data['bsf-aiosrs-product-review-count'] ) && ! empty( $data['bsf-aiosrs-product-review-count'] ) ) ) {

						$schema['itemReviewed']['aggregateRating']['@type'] = 'AggregateRating';

						$schema['itemReviewed']['aggregateRating']['ratingValue'] = ! empty( $data['bsf-aiosrs-product-rating'] ) ? wp_strip_all_tags( (string) $data['bsf-aiosrs-product-rating'] ) : null;
						$schema['itemReviewed']['aggregateRating']['reviewCount'] = ! empty( $data['bsf-aiosrs-product-review-count'] ) ? wp_strip_all_tags( (string) $data['bsf-aiosrs-product-review-count'] ) : null;
						$schema['itemReviewed']['aggregateRating']['bestRating']  = $rating_scale_best;
						$schema['itemReviewed']['aggregateRating']['worstRating'] = $rating_scale_worst;
					}
					if ( apply_filters( 'wp_schema_pro_remove_product_offers', true ) ) {
						$schema['itemReviewed']['offers']['@type']           = 'Offer';
						$schema['itemReviewed']['offers']['price']           = '0';
						$schema['itemReviewed']['offers']['price']           = ! empty( $data['bsf-aiosrs-product-price'] ) ? wp_strip_all_tags( (string) $data['bsf-aiosrs-product-price'] ) : null;
						$schema['itemReviewed']['offers']['priceValidUntil'] = ! empty( $data['bsf-aiosrs-product-price-valid-until'] ) ? wp_strip_all_tags( (string) $data['bsf-aiosrs-product-price-valid-until'] ) : null;

							$schema['itemReviewed']['offers']['url'] = get_permalink( $post['ID'] );

						if ( ( isset( $data['bsf-aiosrs-product-currency'] ) && ! empty( $data['bsf-aiosrs-product-currency'] ) ) ||
							( isset( $data['bsf-aiosrs-product-avail'] ) && ! empty( $data['bsf-aiosrs-product-avail'] ) ) ) {

							$schema['itemReviewed']['offers']['priceCurrency'] = ! empty( $data['bsf-aiosrs-product-currency'] ) ? wp_strip_all_tags( (string) $data['bsf-aiosrs-product-currency'] ) : null;
							$schema['itemReviewed']['offers']['availability']  = ! empty( $data['bsf-aiosrs-product-avail'] ) ? wp_strip_all_tags( (string) $data['bsf-aiosrs-product-avail'] ) : null;
						}
					}

					// Add the current review to the product's review array
					if ( isset( $data['rating'] ) && ! empty( $data['rating'] ) ) {
						$current_review = array(
							'@type' => 'Review',
							'reviewRating' => array(
								'@type'       => 'Rating',
								'ratingValue' => wp_strip_all_tags( (string) $data['rating'] ),
								'bestRating'  => $rating_scale_best,
								'worstRating' => $rating_scale_worst,
							),
						);

						// Add reviewer information
						if ( isset( $data['reviewer-name'] ) && ! empty( $data['reviewer-name'] ) ) {
							$current_review['author'] = array(
								'@type' => ! empty( $data['reviewer-type'] ) ? wp_strip_all_tags( (string) $data['reviewer-type'] ) : 'Person',
								'name'  => wp_strip_all_tags( (string) $data['reviewer-name'] ),
							);
						}

						// Add review body
						if ( isset( $data['review-body'] ) && ! empty( $data['review-body'] ) ) {
							$current_review['reviewBody'] = wp_strip_all_tags( (string) $data['review-body'] );
						}

						// Add date published
						if ( isset( $data['date'] ) && ! empty( $data['date'] ) ) {
							$current_review['datePublished'] = wp_strip_all_tags( (string) $data['date'] );
						}

						// Add the review to the product
						$schema['itemReviewed']['review'] = array( $current_review );
					}

					break;
				case 'bsf-aiosrs-movie':
					$schema['itemReviewed']['@type']  = 'Movie';
					$schema['itemReviewed']['name']   = ! empty( $data['bsf-aiosrs-movie-name'] ) ? wp_strip_all_tags( (string) $data['bsf-aiosrs-movie-name'] ) : null;
					$schema['itemReviewed']['sameAs'] = ! empty( $data['bsf-aiosrs-movie-same-As'] ) ? wp_strip_all_tags( (string) $data['bsf-aiosrs-movie-same-As'] ) : null;
					if ( isset( $data['bsf-aiosrs-movie-image'] ) && ! empty( $data['bsf-aiosrs-movie-image'] ) ) {
						$schema['itemReviewed']['image'] = BSF_AIOSRS_Pro_Schema_Template::get_image_schema( $data['bsf-aiosrs-movie-image'] );
					}
					$schema['itemReviewed']['dateCreated'] = ! empty( $data['bsf-aiosrs-movie-dateCreated'] ) ? wp_strip_all_tags( (string) $data['bsf-aiosrs-movie-dateCreated'] ) : null;
					if ( isset( $data['bsf-aiosrs-movie-director-name'] ) && ! empty( $data['bsf-aiosrs-movie-director-name'] ) ) {
						$schema['itemReviewed']['director']['@type'] = 'Person';
						$schema['itemReviewed']['director']['name']  = wp_strip_all_tags( (string) $data['bsf-aiosrs-movie-director-name'] );
					}
					$schema['description'] = ! empty( $data['bsf-aiosrs-movie-description'] ) ? wp_strip_all_tags( (string) $data['bsf-aiosrs-movie-description'] ) : null;
					// Add aggregateRating for Movie
					if ( ( isset( $data['bsf-aiosrs-movie-rating'] ) && ! empty( $data['bsf-aiosrs-movie-rating'] ) ) ||
						( isset( $data['bsf-aiosrs-movie-review-count'] ) && ! empty( $data['bsf-aiosrs-movie-review-count'] ) ) ) {
						$schema['itemReviewed']['aggregateRating']['@type']       = 'AggregateRating';
						$schema['itemReviewed']['aggregateRating']['ratingValue'] = ! empty( $data['bsf-aiosrs-movie-rating'] ) ? wp_strip_all_tags( (string) $data['bsf-aiosrs-movie-rating'] ) : null;
						$schema['itemReviewed']['aggregateRating']['reviewCount'] = ! empty( $data['bsf-aiosrs-movie-review-count'] ) ? wp_strip_all_tags( (string) $data['bsf-aiosrs-movie-review-count'] ) : null;
						$schema['itemReviewed']['aggregateRating']['bestRating']  = $rating_scale_best;
						$schema['itemReviewed']['aggregateRating']['worstRating'] = $rating_scale_worst;
					}

					break;
				case 'bsf-aiosrs-game':
					$schema['itemReviewed']['@type'] = 'VideoGame';
					$schema['itemReviewed']['name']  = ! empty( $data['bsf-aiosrs-game-name'] ) ? wp_strip_all_tags( (string) $data['bsf-aiosrs-game-name'] ) : null;
					$schema['itemReviewed']['description'] = ! empty( $data['bsf-aiosrs-game-description'] ) ? wp_strip_all_tags( (string) $data['bsf-aiosrs-game-description'] ) : null;
					if ( isset( $data['bsf-aiosrs-game-image'] ) && ! empty( $data['bsf-aiosrs-game-image'] ) ) {
						$schema['itemReviewed']['image'] = BSF_AIOSRS_Pro_Schema_Template::get_image_schema( $data['bsf-aiosrs-game-image'] );
					}
					$schema['itemReviewed']['sameAs'] = ! empty( $data['bsf-aiosrs-game-same-As'] ) ? wp_strip_all_tags( (string) $data['bsf-aiosrs-game-same-As'] ) : null;
					if ( isset( $data['bsf-aiosrs-game-datePublished'] ) && ! empty( $data['bsf-aiosrs-game-datePublished'] ) ) {
						$publish_date = gmdate( DATE_ISO8601, strtotime( $data['bsf-aiosrs-game-datePublished'] ) );
						$schema['itemReviewed']['datePublished'] = wp_strip_all_tags( (string) $publish_date );
					}
					
					// Developer/Publisher
					if ( isset( $data['bsf-aiosrs-game-author'] ) && ! empty( $data['bsf-aiosrs-game-author'] ) ) {
						$schema['itemReviewed']['author']['@type'] = 'Organization';
						$schema['itemReviewed']['author']['name']  = wp_strip_all_tags( (string) $data['bsf-aiosrs-game-author'] );
					}
					
					// Platform
					$schema['itemReviewed']['gamePlatform'] = ! empty( $data['bsf-aiosrs-game-platform'] ) ? wp_strip_all_tags( (string) $data['bsf-aiosrs-game-platform'] ) : null;
					
					// Genre
					$schema['itemReviewed']['genre'] = ! empty( $data['bsf-aiosrs-game-genre'] ) ? wp_strip_all_tags( (string) $data['bsf-aiosrs-game-genre'] ) : null;
					
					// Game Edition
					$schema['itemReviewed']['gameEdition'] = ! empty( $data['bsf-aiosrs-game-edition'] ) ? wp_strip_all_tags( (string) $data['bsf-aiosrs-game-edition'] ) : null;
					
					// Play Mode
					$schema['itemReviewed']['playMode'] = ! empty( $data['bsf-aiosrs-game-playMode'] ) ? wp_strip_all_tags( (string) $data['bsf-aiosrs-game-playMode'] ) : null;
					
					// Actor
					if ( isset( $data['bsf-aiosrs-game-actor'] ) && ! empty( $data['bsf-aiosrs-game-actor'] ) ) {
						$schema['itemReviewed']['actor']['@type'] = 'Person';
						$schema['itemReviewed']['actor']['name']  = wp_strip_all_tags( (string) $data['bsf-aiosrs-game-actor'] );
					}
					
					// Director
					if ( isset( $data['bsf-aiosrs-game-director'] ) && ! empty( $data['bsf-aiosrs-game-director'] ) ) {
						$schema['itemReviewed']['director']['@type'] = 'Person';
						$schema['itemReviewed']['director']['name']  = wp_strip_all_tags( (string) $data['bsf-aiosrs-game-director'] );
					}
					
					// Music By
					if ( isset( $data['bsf-aiosrs-game-musicBy'] ) && ! empty( $data['bsf-aiosrs-game-musicBy'] ) ) {
						$schema['itemReviewed']['musicBy']['@type'] = 'Person';
						$schema['itemReviewed']['musicBy']['name']  = wp_strip_all_tags( (string) $data['bsf-aiosrs-game-musicBy'] );
					}
					
					// Cheat Code
					if ( isset( $data['bsf-aiosrs-game-cheatCode'] ) && ! empty( $data['bsf-aiosrs-game-cheatCode'] ) ) {
						$schema['itemReviewed']['cheatCode']['@type'] = 'CreativeWork';
						$schema['itemReviewed']['cheatCode']['text']  = wp_strip_all_tags( (string) $data['bsf-aiosrs-game-cheatCode'] );
					}
					
					// Game Tip
					if ( isset( $data['bsf-aiosrs-game-gameTip'] ) && ! empty( $data['bsf-aiosrs-game-gameTip'] ) ) {
						$schema['itemReviewed']['gameTip']['@type'] = 'CreativeWork';
						$schema['itemReviewed']['gameTip']['text']  = wp_strip_all_tags( (string) $data['bsf-aiosrs-game-gameTip'] );
					}
					
					// Trailer
					if ( isset( $data['bsf-aiosrs-game-trailer-name'] ) && ! empty( $data['bsf-aiosrs-game-trailer-name'] ) ) {
						$schema['itemReviewed']['trailer']['@type'] = 'VideoObject';
						$schema['itemReviewed']['trailer']['name']  = wp_strip_all_tags( (string) $data['bsf-aiosrs-game-trailer-name'] );
						$schema['itemReviewed']['trailer']['description'] = ! empty( $data['bsf-aiosrs-game-trailer-description'] ) ? wp_strip_all_tags( (string) $data['bsf-aiosrs-game-trailer-description'] ) : null;
						if ( isset( $data['bsf-aiosrs-game-trailer-image'] ) && ! empty( $data['bsf-aiosrs-game-trailer-image'] ) ) {
							$schema['itemReviewed']['trailer']['thumbnailUrl'] = BSF_AIOSRS_Pro_Schema_Template::get_image_schema( $data['bsf-aiosrs-game-trailer-image'], 'URL' );
						}
						if ( isset( $data['bsf-aiosrs-game-trailer-url'] ) && ! empty( $data['bsf-aiosrs-game-trailer-url'] ) ) {
							$schema['itemReviewed']['trailer']['contentUrl'] = esc_url( $data['bsf-aiosrs-game-trailer-url'] );
						}
						if ( isset( $data['bsf-aiosrs-game-trailer-embed-url'] ) && ! empty( $data['bsf-aiosrs-game-trailer-embed-url'] ) ) {
							$schema['itemReviewed']['trailer']['embedUrl'] = esc_url( $data['bsf-aiosrs-game-trailer-embed-url'] );
						}
						$schema['itemReviewed']['trailer']['duration'] = ! empty( $data['bsf-aiosrs-game-trailer-duration'] ) ? wp_strip_all_tags( (string) $data['bsf-aiosrs-game-trailer-duration'] ) : null;
						if ( isset( $data['bsf-aiosrs-game-trailer-uploadDate'] ) && ! empty( $data['bsf-aiosrs-game-trailer-uploadDate'] ) ) {
							$upload_date = gmdate( DATE_ISO8601, strtotime( $data['bsf-aiosrs-game-trailer-uploadDate'] ) );
							$schema['itemReviewed']['trailer']['uploadDate'] = wp_strip_all_tags( (string) $upload_date );
						}
					}
					
					// Add aggregateRating for Game
					if ( ( isset( $data['bsf-aiosrs-game-rating'] ) && ! empty( $data['bsf-aiosrs-game-rating'] ) ) ||
						( isset( $data['bsf-aiosrs-game-review-count'] ) && ! empty( $data['bsf-aiosrs-game-review-count'] ) ) ) {
						$schema['itemReviewed']['aggregateRating']['@type']       = 'AggregateRating';
						$schema['itemReviewed']['aggregateRating']['ratingValue'] = ! empty( $data['bsf-aiosrs-game-rating'] ) ? wp_strip_all_tags( (string) $data['bsf-aiosrs-game-rating'] ) : null;
						$schema['itemReviewed']['aggregateRating']['reviewCount'] = ! empty( $data['bsf-aiosrs-game-review-count'] ) ? wp_strip_all_tags( (string) $data['bsf-aiosrs-game-review-count'] ) : null;
						$schema['itemReviewed']['aggregateRating']['bestRating']  = $rating_scale_best;
						$schema['itemReviewed']['aggregateRating']['worstRating'] = $rating_scale_worst;
					}

					break;
				case 'bsf-aiosrs-music':
					$schema['itemReviewed']['@type'] = 'MusicRecording';
					$schema['itemReviewed']['name']  = ! empty( $data['bsf-aiosrs-music-name'] ) ? wp_strip_all_tags( (string) $data['bsf-aiosrs-music-name'] ) : null;
					$schema['itemReviewed']['description'] = ! empty( $data['bsf-aiosrs-music-description'] ) ? wp_strip_all_tags( (string) $data['bsf-aiosrs-music-description'] ) : null;
					if ( isset( $data['bsf-aiosrs-music-image'] ) && ! empty( $data['bsf-aiosrs-music-image'] ) ) {
						$schema['itemReviewed']['image'] = BSF_AIOSRS_Pro_Schema_Template::get_image_schema( $data['bsf-aiosrs-music-image'] );
					}
					$schema['itemReviewed']['sameAs'] = ! empty( $data['bsf-aiosrs-music-same-As'] ) ? wp_strip_all_tags( (string) $data['bsf-aiosrs-music-same-As'] ) : null;
					if ( isset( $data['bsf-aiosrs-music-datePublished'] ) && ! empty( $data['bsf-aiosrs-music-datePublished'] ) ) {
						$publish_date = gmdate( DATE_ISO8601, strtotime( $data['bsf-aiosrs-music-datePublished'] ) );
						$schema['itemReviewed']['datePublished'] = wp_strip_all_tags( (string) $publish_date );
					}
					
					// Artist (byArtist)
					if ( isset( $data['bsf-aiosrs-music-byArtist'] ) && ! empty( $data['bsf-aiosrs-music-byArtist'] ) ) {
						$artist_type = ! empty( $data['bsf-aiosrs-music-byArtist-type'] ) ? wp_strip_all_tags( (string) $data['bsf-aiosrs-music-byArtist-type'] ) : 'Person';
						$schema['itemReviewed']['byArtist']['@type'] = $artist_type; // Can be 'Person' or 'MusicGroup'
						$schema['itemReviewed']['byArtist']['name']  = wp_strip_all_tags( (string) $data['bsf-aiosrs-music-byArtist'] );
					}
					
					// Duration
					$schema['itemReviewed']['duration'] = ! empty( $data['bsf-aiosrs-music-duration'] ) ? wp_strip_all_tags( (string) $data['bsf-aiosrs-music-duration'] ) : null;
					
					// In Album
					if ( isset( $data['bsf-aiosrs-music-inAlbum'] ) && ! empty( $data['bsf-aiosrs-music-inAlbum'] ) ) {
						$schema['itemReviewed']['inAlbum']['@type'] = 'MusicAlbum';
						$schema['itemReviewed']['inAlbum']['name']  = wp_strip_all_tags( (string) $data['bsf-aiosrs-music-inAlbum'] );
					}
					
					// In Playlist
					if ( isset( $data['bsf-aiosrs-music-inPlaylist'] ) && ! empty( $data['bsf-aiosrs-music-inPlaylist'] ) ) {
						$schema['itemReviewed']['inPlaylist']['@type'] = 'MusicPlaylist';
						$schema['itemReviewed']['inPlaylist']['name']  = wp_strip_all_tags( (string) $data['bsf-aiosrs-music-inPlaylist'] );
					}
					
					// ISRC Code
					$schema['itemReviewed']['isrcCode'] = ! empty( $data['bsf-aiosrs-music-isrcCode'] ) ? wp_strip_all_tags( (string) $data['bsf-aiosrs-music-isrcCode'] ) : null;
					
					// Recording Of (MusicComposition)
					if ( isset( $data['bsf-aiosrs-music-recordingOf'] ) && ! empty( $data['bsf-aiosrs-music-recordingOf'] ) ) {
						$schema['itemReviewed']['recordingOf']['@type'] = 'MusicComposition';
						$schema['itemReviewed']['recordingOf']['name']  = wp_strip_all_tags( (string) $data['bsf-aiosrs-music-recordingOf'] );
					}
					
					// Genre
					$schema['itemReviewed']['genre'] = ! empty( $data['bsf-aiosrs-music-genre'] ) ? wp_strip_all_tags( (string) $data['bsf-aiosrs-music-genre'] ) : null;
					
					// Record Label
					if ( isset( $data['bsf-aiosrs-music-recordLabel'] ) && ! empty( $data['bsf-aiosrs-music-recordLabel'] ) ) {
						$schema['itemReviewed']['recordLabel']['@type'] = 'Organization';
						$schema['itemReviewed']['recordLabel']['name']  = wp_strip_all_tags( (string) $data['bsf-aiosrs-music-recordLabel'] );
					}
					
					// Content URL (for streaming/listening)
					if ( isset( $data['bsf-aiosrs-music-contentUrl'] ) && ! empty( $data['bsf-aiosrs-music-contentUrl'] ) ) {
						$schema['itemReviewed']['contentUrl'] = esc_url( $data['bsf-aiosrs-music-contentUrl'] );
					}
					
					// Embed URL
					if ( isset( $data['bsf-aiosrs-music-embedUrl'] ) && ! empty( $data['bsf-aiosrs-music-embedUrl'] ) ) {
						$schema['itemReviewed']['embedUrl'] = esc_url( $data['bsf-aiosrs-music-embedUrl'] );
					}
					
					// Add aggregateRating for Music
					if ( ( isset( $data['bsf-aiosrs-music-rating'] ) && ! empty( $data['bsf-aiosrs-music-rating'] ) ) ||
						( isset( $data['bsf-aiosrs-music-review-count'] ) && ! empty( $data['bsf-aiosrs-music-review-count'] ) ) ) {
						$schema['itemReviewed']['aggregateRating']['@type']       = 'AggregateRating';
						$schema['itemReviewed']['aggregateRating']['ratingValue'] = ! empty( $data['bsf-aiosrs-music-rating'] ) ? wp_strip_all_tags( (string) $data['bsf-aiosrs-music-rating'] ) : null;
						$schema['itemReviewed']['aggregateRating']['reviewCount'] = ! empty( $data['bsf-aiosrs-music-review-count'] ) ? wp_strip_all_tags( (string) $data['bsf-aiosrs-music-review-count'] ) : null;
						$schema['itemReviewed']['aggregateRating']['bestRating']  = $rating_scale_best;
						$schema['itemReviewed']['aggregateRating']['worstRating'] = $rating_scale_worst;
					}

					break;
				case 'bsf-aiosrs-organization':
					$schema['itemReviewed']['@type'] = 'Organization';
					$schema['itemReviewed']['name']  = ! empty( $data['bsf-aiosrs-organization-name'] ) ? wp_strip_all_tags( (string) $data['bsf-aiosrs-organization-name'] ) : null;
					if ( ( isset( $data['bsf-aiosrs-organization-location-street'] ) && ! empty( $data['bsf-aiosrs-organization-location-street'] ) ) ||
						( isset( $data['bsf-aiosrs-organization-location-locality'] ) && ! empty( $data['bsf-aiosrs-organization-location-locality'] ) ) ||
						( isset( $data['bsf-aiosrs-organization-location-postal'] ) && ! empty( $data['bsf-aiosrs-organization-location-postal'] ) ) ||
						( isset( $data['bsf-aiosrs-organization-location-region'] ) && ! empty( $data['bsf-aiosrs-organization-location-region'] ) ) ||
						( isset( $data['bsf-aiosrs-organization-location-country'] ) && ! empty( $data['bsf-aiosrs-organization-location-country'] ) ) ) {

						$schema['itemReviewed']['address']['@type'] = 'PostalAddress';

						$schema['itemReviewed']['address']['streetAddress']   = ! empty( $data['bsf-aiosrs-organization-location-street'] ) ? wp_strip_all_tags( (string) $data['bsf-aiosrs-organization-location-street'] ) : null;
						$schema['itemReviewed']['address']['addressLocality'] = ! empty( $data['bsf-aiosrs-organization-location-locality'] ) ? wp_strip_all_tags( (string) $data['bsf-aiosrs-organization-location-locality'] ) : null;
						$schema['itemReviewed']['address']['postalCode']      = ! empty( $data['bsf-aiosrs-organization-location-postal'] ) ? wp_strip_all_tags( (string) $data['bsf-aiosrs-organization-location-postal'] ) : null;
						$schema['itemReviewed']['address']['addressRegion']   = ! empty( $data['bsf-aiosrs-organization-location-region'] ) ? wp_strip_all_tags( (string) $data['bsf-aiosrs-organization-location-region'] ) : null;
						$schema['itemReviewed']['address']['addressCountry']  = ! empty( $data['bsf-aiosrs-organization-location-country'] ) ? wp_strip_all_tags( (string) $data['bsf-aiosrs-organization-location-country'] ) : null;
					}
					// Add aggregateRating for Organization
					if ( ( isset( $data['bsf-aiosrs-organization-rating'] ) && ! empty( $data['bsf-aiosrs-organization-rating'] ) ) ||
						( isset( $data['bsf-aiosrs-organization-review-count'] ) && ! empty( $data['bsf-aiosrs-organization-review-count'] ) ) ) {
						$schema['itemReviewed']['aggregateRating']['@type']       = 'AggregateRating';
						$schema['itemReviewed']['aggregateRating']['ratingValue'] = ! empty( $data['bsf-aiosrs-organization-rating'] ) ? wp_strip_all_tags( (string) $data['bsf-aiosrs-organization-rating'] ) : null;
						$schema['itemReviewed']['aggregateRating']['reviewCount'] = ! empty( $data['bsf-aiosrs-organization-review-count'] ) ? wp_strip_all_tags( (string) $data['bsf-aiosrs-organization-review-count'] ) : null;
						$schema['itemReviewed']['aggregateRating']['bestRating']  = $rating_scale_best;
						$schema['itemReviewed']['aggregateRating']['worstRating'] = $rating_scale_worst;
					}
					break;
				default:
					break;
			}
			/*Review schema fields*/

			if ( isset( $data['rating'] ) && ! empty( $data['rating'] ) ) {
				$schema['reviewRating']['@type']       = 'Rating';
				// Sanitize the rating value and validate against selected scale
				$rating_value = wp_strip_all_tags( (string) $data['rating'] );
				$rating_scale_selected = isset( $data['rating-scale'] ) ? $data['rating-scale'] : '1-5';

				// Validate rating value is numeric and within the selected scale
				if ( is_numeric( $rating_value ) && self::validate_rating_value( $rating_value, $rating_scale_selected ) ) {
					$schema['reviewRating']['ratingValue'] = $rating_value;
				} else {
					// If invalid, still include for backward compatibility
					$schema['reviewRating']['ratingValue'] = $rating_value;
				}

				$schema['reviewRating']['bestRating']  = $rating_scale_best;
				$schema['reviewRating']['worstRating'] = $rating_scale_worst;
			}
			$schema['reviewBody']      = ! empty( $data['review-body'] ) ? wp_strip_all_tags( (string) $data['review-body'] ) : null;
			$schema['datePublished']   = ! empty( $data['date'] ) ? wp_strip_all_tags( (string) $data['date'] ) : null;
			$schema['author']['@type'] = ! empty( $data['reviewer-type'] ) ? wp_strip_all_tags( (string) $data['reviewer-type'] ) : 'Person';
			if ( isset( $data['reviewer-name'] ) && ! empty( $data['reviewer-name'] ) ) {
				$schema['author']['name']   = wp_strip_all_tags( (string) $data['reviewer-name'] );
				$author_data                = get_userdata( $post['post_author'] );
					$author_name            = ( isset( $author_data->user_nicename ) ) ? $author_data->user_nicename : '';
					$author_url             = get_author_posts_url( $post['ID'] );
					$final_url              = $author_url . '' . $author_name;
					$is_available           = true;
				$schema['author']['sameAs'] = esc_url( $final_url );
			}
			if ( isset( $data['publisher-name'] ) && ! empty( $data['publisher-name'] ) ) {
				$schema['publisher']['@type']  = 'Organization';
				$schema['publisher']['name']   = wp_strip_all_tags( (string) $data['publisher-name'] );
				$prg_url_value                 = get_bloginfo( 'url' );
				$schema['publisher']['sameAs'] = esc_url( $prg_url_value );
			}

			return apply_filters( 'wp_schema_pro_schema_review', $schema, $data, $post );
		}

		/**
		 * Validate rating value against the selected rating scale
		 *
		 * @param  float  $rating_value The rating value to validate.
		 * @param  string $rating_scale The rating scale ('1-5', '1-10').
		 * @return bool   True if valid, false otherwise
		 */
		private static function validate_rating_value( $rating_value, $rating_scale = '1-5' ) {
			if ( ! is_numeric( $rating_value ) ) {
				return false;
			}

			$value = (float) $rating_value;

			switch ( $rating_scale ) {
				case '1-5':
					return $value >= 1 && $value <= 5;
				case '1-10':
					return $value >= 1 && $value <= 10;
				default:
					return false;
			}
		}

		/**
		 * Get rating scale from explicit field or auto-detect from rating values
		 *
		 * Checks if rating-scale is explicitly set in the data. If not, auto-detects
		 * the appropriate scale (1-5 or 1-10) based on the maximum rating value found.
		 * Defaults to 1-5 scale if no ratings are present.
		 *
		 * @param  array $data Meta data containing rating values and optional rating-scale field.
		 * @return array Array with 'worst' and 'best' rating values.
		 */
		private static function detect_rating_scale( $data ) {
			if ( isset( $data['rating-scale'] ) && ! empty( $data['rating-scale'] ) ) {
				$rating_scale = sanitize_text_field( $data['rating-scale'] );

				switch ( $rating_scale ) {
					case '1-5':
						return array( 'worst' => '1', 'best' => '5' );
					case '1-10':
						return array( 'worst' => '1', 'best' => '10' );
					default:
						return array( 'worst' => '1', 'best' => '5' );
				}
			}

			$rating_fields = array(
				'rating',
				'ratingValue',
				'bsf-aiosrs-book-rating',
				'bsf-aiosrs-course-rating',
				'bsf-aiosrs-event-rating',
				'bsf-aiosrs-local-business-rating',
				'bsf-aiosrs-recipe-rating',
				'bsf-aiosrs-software-application-rating',
				'bsf-aiosrs-product-rating',
				'bsf-aiosrs-movie-rating',
				'bsf-aiosrs-game-rating',
				'bsf-aiosrs-music-rating',
				'bsf-aiosrs-organization-rating',
			);

			$ratings = array();
			foreach ( $rating_fields as $field ) {
				if ( isset( $data[ $field ] ) && ! empty( $data[ $field ] ) && is_numeric( $data[ $field ] ) ) {
					$ratings[] = (float) $data[ $field ];
				}
			}

			if ( empty( $ratings ) ) {
				return array( 'worst' => '1', 'best' => '5' );
			}

			$max_rating = max( $ratings );
			return $max_rating <= 5 ? array( 'worst' => '1', 'best' => '5' ) : array( 'worst' => '1', 'best' => '10' );
		}


	}
}
