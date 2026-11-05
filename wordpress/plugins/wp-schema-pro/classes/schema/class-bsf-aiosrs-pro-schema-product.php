<?php
/**
 * Schemas Template.
 *
 * @package Schema Pro
 * @since 1.0.0
 */

if ( ! class_exists( 'BSF_AIOSRS_Pro_Schema_Product' ) ) {

	/**
	 * AIOSRS Schemas Initialization
	 *
	 * @since 1.0.0
	 */
	class BSF_AIOSRS_Pro_Schema_Product {

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
			$schema['@type']    = 'Product';

			if ( ! empty( $data['productGroupID'] ) ) {
				$schema['productGroupID'] = wp_strip_all_tags( (string) $data['productGroupID'] );
			}
			
			// Name field with a maximum length of 150 characters
			if ( ! empty( $data['name'] ) ) {
				$name       = wp_strip_all_tags( trim( (string) $data['name'] ) );
				$max_length = 150;
			
				if ( strlen( $name ) > 0 && strlen( $name ) <= $max_length ) {
					$schema['name'] = $name;
				} else {
					$schema['name'] = substr( $name, 0, $max_length );
				}
			}
			
			// Image schema
			if ( isset( $data['image'] ) && ! empty( $data['image'] ) ) {
				$schema['image'] = BSF_AIOSRS_Pro_Schema_Template::get_image_schema( $data['image'] );
			}

			$schema['description'] = ! empty( $data['description'] ) ? wp_strip_all_tags( (string) $data['description'] ) : null;

			$schema['sku'] = ! empty( $data['sku'] ) ? wp_strip_all_tags( (string) $data['sku'] ) : null;
			$schema['mpn'] = ! empty( $data['mpn'] ) ? wp_strip_all_tags( (string) $data['mpn'] ) : null;

			if ( ! empty( $data['brand-name'] ) ) {
				$schema['brand']['@type'] = 'Brand';
				$schema['brand']['name']  = wp_strip_all_tags( (string) $data['brand-name'] );
			}

			// Aggregate rating schema
			if ( ( isset( $data['rating'] ) && ! empty( $data['rating'] ) ) ||
				( isset( $data['review-count'] ) && ! empty( $data['review-count'] ) ) ) {

				$schema['aggregateRating'] = array(
					'@type'       => 'AggregateRating',
					'ratingValue' => ! empty( $data['rating'] ) ? wp_strip_all_tags( (string) $data['rating'] ) : null,
					'reviewCount' => ! empty( $data['review-count'] ) ? wp_strip_all_tags( (string) $data['review-count'] ) : null,
					'bestRating'  => '5',
					'worstRating' => '1',
				);
			}

			// Shipping details schema
			$shipping_details = array();

			if ( ! empty( $data['shippingDetails'] ) ) {
				foreach ( $data['shippingDetails'] as $shipping_detail ) {
					$shipping = array();

					$delivery_time = array(
						'@type' => 'ShippingDeliveryTime',
					);

					if ( ! empty( $shipping_detail['handlingTimeMinValue'] ) || ! empty( $shipping_detail['handlingTimeMaxValue'] ) ) {
						$delivery_time['handlingTime'] = array(
							'@type'    => 'QuantitativeValue',
							'minValue' => ! empty( $shipping_detail['handlingTimeMinValue'] ) ? wp_strip_all_tags( (string) $shipping_detail['handlingTimeMinValue'] ) : null,
							'maxValue' => ! empty( $shipping_detail['handlingTimeMaxValue'] ) ? wp_strip_all_tags( (string) $shipping_detail['handlingTimeMaxValue'] ) : null,
							'unitCode' => ! empty( $shipping_detail['unitCode'] ) ? wp_strip_all_tags( (string) $shipping_detail['unitCode'] ) : 'd',
						);
					}

					if ( ! empty( $shipping_detail['transitTimeMinValue'] ) || ! empty( $shipping_detail['transitTimeMaxValue'] ) ) {
						$delivery_time['transitTime'] = array(
							'@type'    => 'QuantitativeValue',
							'minValue' => ! empty( $shipping_detail['transitTimeMinValue'] ) ? wp_strip_all_tags( (string) $shipping_detail['transitTimeMinValue'] ) : null,
							'maxValue' => ! empty( $shipping_detail['transitTimeMaxValue'] ) ? wp_strip_all_tags( (string) $shipping_detail['transitTimeMaxValue'] ) : null,
							'unitCode' => ! empty( $shipping_detail['unitCode'] ) ? wp_strip_all_tags( (string) $shipping_detail['unitCode'] ) : 'd',
						);
					}

					if ( ! empty( $delivery_time ) ) {
						$shipping['deliveryTime'] = $delivery_time;
					}

					if ( ! empty( $shipping_detail['shippingDestination'] ) && $shipping_detail['shippingDestination'] !== 'none' ) {
						$shipping['shippingDestination'] = array(
							'@type'          => 'DefinedRegion',
							'addressCountry' => $shipping_detail['shippingDestination'],
						);
					}

					if ( ! empty( $shipping_detail['shippingRate'] ) ) {
						$shipping['shippingRate'] = array(
							'@type'    => 'MonetaryAmount',
							'value'    => $shipping_detail['shippingRate'],
							'currency' => ! empty( $shipping_detail['shippingCurrency'] ) ? $shipping_detail['shippingCurrency'] : 'USD',
						);
					}

					$shipping_details[] = $shipping;
				}

				$schema['offers']['shippingDetails'] = $shipping_details;
			}

			// Offers schema
			if ( apply_filters( 'wp_schema_pro_remove_product_offers', true ) ) {
				$schema['offers']['@type']           = 'Offer';
				$schema['offers']['price']           = ! empty( $data['price'] ) ? wp_strip_all_tags( (string) $data['price'] ) : null;
				$schema['offers']['priceValidUntil'] = ! empty( $data['price-valid-until'] ) ? wp_strip_all_tags( (string) $data['price-valid-until'] ) : null;

				if ( isset( $data['url'] ) && ! empty( $data['url'] ) ) {
					$schema['offers']['url'] = esc_url( $data['url'] );
				}

				if ( ( isset( $data['currency'] ) && ! empty( $data['currency'] ) ) ||
					( isset( $data['avail'] ) && ! empty( $data['avail'] ) ) ) {

					$schema['offers']['priceCurrency'] = ! empty( $data['currency'] ) ? wp_strip_all_tags( (string) $data['currency'] ) : null;
					$schema['offers']['availability']  = ! empty( $data['avail'] ) ? wp_strip_all_tags( (string) $data['avail'] ) : null;
				}
			}

			// Reviews schema
			if ( apply_filters( 'wp_schema_pro_remove_product_reviews', true ) && isset( $data['product-review'] ) && ! empty( $data['product-review'] ) ) {
				$total_rating = 0;
				$review_count = 0;

				foreach ( $data['product-review'] as $value ) {
					if ( isset( $value['product-rating'] ) && ! empty( $value['product-rating'] ) ) {
						$rating        = floatval( $value['product-rating'] );
						$total_rating += $rating;
						$review_count++;
					}
				}

				if ( $review_count > 0 ) {
					$schema['aggregateRating'] = array(
						'@type'       => 'AggregateRating',
						'ratingValue' => round( $total_rating / $review_count, 1 ),
						'reviewCount' => $review_count,
					);
				}

				foreach ( $data['product-review'] as $key => $value ) {
					if ( ( isset( $value['reviewer-name'] ) && ! empty( $value['reviewer-name'] ) ) && ( isset( $value['product-rating'] ) && ! empty( $value['product-rating'] ) ) ) {
						$schema['review'][ $key ]['@type']  = 'Review';
						$schema['review'][ $key ]['author'] = array(
							'@type' => 'Person',
							'name'  => substr( wp_strip_all_tags( trim( (string) $value['reviewer-name'] ) ), 0, 100 ), // Limiting name length to 100 chars
						);

						$schema['aggregateRating'] = array(
							'@type'       => 'AggregateRating',
							'ratingValue' => round( $total_rating / $review_count, 1 ),
							'reviewCount' => $review_count,
							'bestRating'  => '5',
							'worstRating' => '1',
						);

						$schema['review'][ $key ]['reviewBody'] = ! empty( $value['review-body'] ) ? wp_strip_all_tags( (string) $value['review-body'] ) : null;

												// Properly format positiveNotes as ItemList
						if ( isset( $value['positiveNotes'] ) && ! empty( $value['positiveNotes'] ) && 'Array' !== $value['positiveNotes'] ) {
								$notes = is_array( $value['positiveNotes'] ) ? $value['positiveNotes'] : array( $value['positiveNotes'] );
								$notes = array_filter( $notes, 'strlen' );

							if ( ! empty( $notes ) ) {
														$schema['review'][ $key ]['positiveNotes'] = array(
															'@type'           => 'ItemList',
															'itemListElement' => array_map(
																function( $note ) {
																		return array(
																			'@type' => 'ListItem',
																			'name'  => wp_strip_all_tags( (string) $note ),
																		);
																},
																$notes
															),
														);
							}
						}

												// Properly format negativeNotes as ItemList
						if ( isset( $value['negativeNotes'] ) && ! empty( $value['negativeNotes'] ) && 'Array' !== $value['negativeNotes'] ) {
								$notes = is_array( $value['negativeNotes'] ) ? $value['negativeNotes'] : array( $value['negativeNotes'] );
								$notes = array_filter( $notes, 'strlen' );

							if ( ! empty( $notes ) ) {
														$schema['review'][ $key ]['negativeNotes'] = array(
															'@type'           => 'ItemList',
															'itemListElement' => array_map(
																function( $note ) {
																		return array(
																			'@type' => 'ListItem',
																			'name'  => wp_strip_all_tags( (string) $note ),
																		);
																},
																$notes
															),
														);
							}
						}
					}
				}
			}

			// Merchant return policy schema
			if ( isset( $data['merchant-return-policy'] ) && ! empty( $data['merchant-return-policy'] ) ) {
				$schema['offers']['hasMerchantReturnPolicy'] = array();

				foreach ( $data['merchant-return-policy'] as $policy ) {
					$return_policy = array(
						'@type'                => 'MerchantReturnPolicy',
						'applicableCountry'    => isset( $policy['applicableCountry'] ) ? substr( wp_strip_all_tags( (string) $policy['applicableCountry'] ), 0, 2 ) : null,
						'returnPolicyCategory' => isset( $policy['returnPolicyCategory'] ) ? esc_url( $policy['returnPolicyCategory'] ) : null,
						'merchantReturnDays'   => isset( $policy['merchantReturnDays'] ) ? intval( $policy['merchantReturnDays'] ) : null,
						'returnFees'           => isset( $policy['returnFees'] ) ? esc_url( $policy['returnFees'] ) : null,
						'returnMethod'         => isset( $policy['returnMethod'] ) ? esc_url( $policy['returnMethod'] ) : null,
						'refundType'           => isset( $policy['refundType'] ) ? esc_url( $policy['refundType'] ) : null,
					);

					if (
						isset( $policy['returnFees'] ) &&
						$policy['returnFees'] === 'https://schema.org/ReturnShippingFees' &&
						isset( $policy['returnShippingFeesAmount'] ) &&
						! empty( $policy['returnShippingFeesAmount'] )
					) {
						$return_policy['returnShippingFeesAmount'] = array(
							'@type'    => 'MonetaryAmount',
							'value'    => floatval( $policy['returnShippingFeesAmount'] ),
							'currency' => ! empty( $policy['merchantCurrency'] ) ? $policy['merchantCurrency'] : null,
						);
					}

					$schema['offers']['hasMerchantReturnPolicy'][] = $return_policy;
				}
			}

			// Handle product variants.
			if ( ! empty( $data['hasVariant'] ) && is_array( $data['hasVariant'] ) ) {
				$schema['hasVariant'] = array();

				foreach ( $data['hasVariant'] as $variant ) {
					$variant_schema = array(
						'@type' => 'Product',
					);

					// Name
					if ( ! empty( $variant['name'] ) ) {
						$variant_schema['name'] = wp_strip_all_tags( (string) $variant['name'] );
					}

					// Description
					if ( ! empty( $variant['description'] ) ) {
						$variant_schema['description'] = wp_strip_all_tags( (string) $variant['description'] );
					}

					// SKU
					if ( ! empty( $variant['sku'] ) ) {
						$variant_schema['sku'] = wp_strip_all_tags( (string) $variant['sku'] );
					}

					// Product Group ID (from parent)
					if ( ! empty( $data['productGroupID'] ) ) {
						$variant_schema['productGroupID'] = wp_strip_all_tags( (string) $data['productGroupID'] );
					}

					// Color
					if ( ! empty( $variant['color'] ) ) {
						$variant_schema['color'] = wp_strip_all_tags( (string) $variant['color'] );
					}

					// Size
					if ( ! empty( $variant['size'] ) ) {
						$variant_schema['size'] = wp_strip_all_tags( (string) $variant['size'] );
					}

					// Image
					if ( ! empty( $variant['image'] ) ) {
						$variant_schema['image'] = BSF_AIOSRS_Pro_Schema_Template::get_image_schema( $variant['image'] );
					}

					// Offers block (with availability, shippingDetails, hasMerchantReturnPolicy, currency)
					if ( ! empty( $variant['price'] ) ) {
						$variant_offer = array(
							'@type' => 'Offer',
							'price' => wp_strip_all_tags( (string) $variant['price'] ),
						);

						// Currency
						if ( ! empty( $variant['currency'] ) ) {
							$variant_offer['priceCurrency'] = wp_strip_all_tags( (string) $variant['currency'] );
						} elseif ( ! empty( $data['currency'] ) ) {
							$variant_offer['priceCurrency'] = wp_strip_all_tags( (string) $data['currency'] );
						}

						// Availability (variant-level, else parent)
						if ( ! empty( $variant['availability'] ) ) {
							$variant_offer['availability'] = wp_strip_all_tags( (string) $variant['availability'] );
						} elseif ( ! empty( $data['avail'] ) ) {
							$variant_offer['availability'] = wp_strip_all_tags( (string) $data['avail'] );
						}

						// ShippingDetails (variant-level, else parent)
						if ( ! empty( $variant['shippingDetails'] ) ) {
							$variant_offer['shippingDetails'] = $variant['shippingDetails'];
						} elseif ( ! empty( $schema['offers']['shippingDetails'] ) ) {
							$variant_offer['shippingDetails'] = $schema['offers']['shippingDetails'];
						}

						// hasMerchantReturnPolicy (variant-level, else parent)
						if ( ! empty( $variant['hasMerchantReturnPolicy'] ) ) {
							$variant_offer['hasMerchantReturnPolicy'] = $variant['hasMerchantReturnPolicy'];
						} elseif ( ! empty( $schema['offers']['hasMerchantReturnPolicy'] ) ) {
							$variant_offer['hasMerchantReturnPolicy'] = $schema['offers']['hasMerchantReturnPolicy'];
						}

						$variant_schema['offers'] = $variant_offer;
					}

					$schema['hasVariant'][] = $variant_schema;
				}
			}




			if ( defined( 'WC_VERSION' ) && apply_filters( 'wp_schema_pro_add_woocommerce_review', false ) ) {
				$comments = get_comments(
					array(
						'number'      => 5,
						'post_id'     => $post['ID'],
						'status'      => 'approve',
						'post_status' => 'publish',
						'post_type'   => 'product',
						'parent'      => 0,
						'meta_query'  => array(
							array(
								'key'     => 'rating',
								'type'    => 'NUMERIC',
								'compare' => '>',
								'value'   => 0,
							),
						),
					)
				);

				if ( $comments ) {
					foreach ( $comments as $key => $comment ) {
						$schema['review'][ $key ] = array(
							'@type'        => 'Review',
							'reviewRating' => array(
								'@type'       => 'Rating',
								'ratingValue' => get_comment_meta( $comment->comment_ID, 'rating', true ),
							),
							'author'       => array(
								'@type' => 'Person',
								'name'  => get_comment_author( $comment ),
							),
							'reviewBody'   => get_comment_text( $comment ),
						);
					}
				}
			}

			return apply_filters( 'wp_schema_pro_schema_product', $schema, $data, $post );
		}
	}
}
