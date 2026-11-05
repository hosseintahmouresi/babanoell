<?php
/**
 * Vehicle Listing Schema Template.
 *
 * @package Schema Pro
 * @since 1.0.0
 */

if ( ! class_exists( 'BSF_AIOSRS_Pro_Schema_Vehicle_Listing' ) ) {

	/**
	 * Vehicle Listing Schema Renderer.
	 *
	 * @since 1.0.0
	 */
	class BSF_AIOSRS_Pro_Schema_Vehicle_Listing {

		/**
		 * Render Schema.
		 *
		 * @param  array<string, mixed> $data Meta Data.
		 * @param  array<string, mixed> $post Current Post Array.
		 * @return array<string, mixed>
		 */
		public static function render( array $data, array $post ): array {
			$schema           = array();
			$general_settings = BSF_AIOSRS_Pro_Helper::$settings['wp-schema-pro-general-settings'];

			$schema['@context'] = 'https://schema.org';
			$schema['@type']    = 'Car';

			// Vehicle Name.
			if ( ! empty( $data['name'] ) ) {
				$schema['name'] = wp_strip_all_tags( (string) $data['name'] );
			}

			// Image.
			if ( ! empty( $data['image'] ) && is_array( $data['image'] ) ) {
				$schema['image'] = BSF_AIOSRS_Pro_Schema_Template::get_image_schema( $data['image'] );
			} else {
				// Fallback to featured image.
				$featured_image = wp_get_attachment_image_src( get_post_thumbnail_id( $post['ID'] ), 'full' );
				if ( ! empty( $featured_image ) ) {
					$schema['image'] = array( $featured_image[0] );
				}
			}

			// Brand.
			if ( ! empty( $data['brand-name'] ) ) {
				$schema['brand'] = array(
					'@type' => 'Brand',
					'name'  => wp_strip_all_tags( (string) $data['brand-name'] ),
				);
			}

			// Model.
			if ( ! empty( $data['model'] ) ) {
				$schema['model'] = wp_strip_all_tags( (string) $data['model'] );
			}

			// VIN.
			if ( ! empty( $data['vehicle-identification-number'] ) ) {
				$schema['vehicleIdentificationNumber'] = wp_strip_all_tags( (string) $data['vehicle-identification-number'] );
			}

			// Model Year.
			if ( ! empty( $data['vehicle-model-date'] ) ) {
				$schema['vehicleModelDate'] = wp_strip_all_tags( (string) $data['vehicle-model-date'] );
			}

			// Condition.
			if ( ! empty( $data['item-condition'] ) ) {
				$schema['itemCondition'] = $data['item-condition'];
			}

			// Mileage.
			if ( ! empty( $data['mileage-from-odometer'] ) && is_array( $data['mileage-from-odometer'] ) ) {
				foreach ( $data['mileage-from-odometer'] as $mileage_entry ) {
					if ( isset( $mileage_entry['value'], $mileage_entry['unit-code'] ) ) {
						$schema['mileageFromOdometer'] = array(
							'@type'    => 'QuantitativeValue',
							'value'    => (float) $mileage_entry['value'],
							'unitCode' => $mileage_entry['unit-code'],
						);
						break;
					}
				}
			}

			// Color.
			if ( ! empty( $data['color'] ) ) {
				$schema['color'] = wp_strip_all_tags( (string) $data['color'] );
			}

			// Body Type.
			if ( ! empty( $data['body-type'] ) ) {
				$schema['bodyType'] = wp_strip_all_tags( (string) $data['body-type'] );
			}

			// Number of Doors.
			if ( ! empty( $data['number-of-doors'] ) ) {
				$schema['numberOfDoors'] = (int) $data['number-of-doors'];
			}

			// Vehicle Engine with Fuel Type.
			if ( ! empty( $data['fuel-type'] ) ) {
				$schema['vehicleEngine'] = array(
					'@type'    => 'EngineSpecification',
					'fuelType' => wp_strip_all_tags( (string) $data['fuel-type'] ),
				);
			}

			// Transmission.
			if ( ! empty( $data['vehicle-transmission'] ) ) {
				$schema['vehicleTransmission'] = wp_strip_all_tags( (string) $data['vehicle-transmission'] );
			}

			// Offers.
			if ( ! empty( $data['offers'] ) && is_array( $data['offers'] ) ) {
				$offers = array();
				foreach ( $data['offers'] as $offer_entry ) {
					$offer = array( '@type' => 'Offer' );

					if ( isset( $offer_entry['offer-availability'] ) ) {
						$offer['availability'] = $offer_entry['offer-availability'];
					}
					if ( isset( $offer_entry['priceCurrency'] ) ) {
						$offer['priceCurrency'] = $offer_entry['priceCurrency'];
					}
					if ( isset( $offer_entry['price'] ) ) {
						$offer['price'] = (float) $offer_entry['price'];
					}

					$offers[] = $offer;
				}
				$schema['offers'] = ( count( $offers ) === 1 ) ? $offers[0] : $offers;
			}

			return apply_filters( 'wp_schema_pro_schema_vehicle_listing', $schema, $data, $post );
		}
	}
}
