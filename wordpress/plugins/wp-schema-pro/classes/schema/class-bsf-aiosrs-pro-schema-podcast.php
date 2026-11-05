<?php
/**
 * Podcast Schema Template.
 *
 * @package Schema Pro
 * @since 1.0.0
 */

if ( ! class_exists( 'BSF_AIOSRS_Pro_Schema_Podcast' ) ) {

	/**
	 * Podcast Schema Renderer.
	 *
	 * @since 1.0.0
	 */
	class BSF_AIOSRS_Pro_Schema_Podcast {

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
			$schema['@type']    = 'PodcastEpisode';

			// Main Entity (if set).
			if ( ! empty( $data['main-entity'] ) ) {
				$schema['mainEntityOfPage'] = array(
					'@type' => 'WebPage',
					'@id'   => esc_url( $data['main-entity'] ),
				);
			}

			// Part of Series.
			if ( ! empty( $data['name'] ) ) {
				$schema['partOfSeries'] = array(
					'@type' => 'PodcastSeries',
					'name'  => wp_strip_all_tags( $data['name'] ),
				);
			}

			// Headline / Title of the Episode.
			if ( ! empty( $data['episode-name'] ) ) {
				$schema['headline'] = wp_strip_all_tags( $data['episode-name'] );
				$schema['name']     = wp_strip_all_tags( $data['episode-name'] );
			}

			// Description.
			if ( ! empty( $data['episode-description'] ) ) {
				$schema['description'] = wp_strip_all_tags( $data['episode-description'] );
			} elseif ( ! empty( $data['podcast-description'] ) ) {
				$schema['description'] = wp_strip_all_tags( $data['podcast-description'] );
			}

			// Episode URL.
			if ( ! empty( $data['episode-url'] ) ) {
				$schema['url'] = esc_url_raw( $data['episode-url'] );
			}

			// Image.
			if ( ! empty( $data['episode-image-url'] ) && is_array( $data['episode-image-url'] ) ) {
				$schema['image'] = BSF_AIOSRS_Pro_Schema_Template::get_image_schema( $data['episode-image-url'] );
			} elseif ( ! empty( $data['podcast-image'] ) && is_array( $data['podcast-image'] ) ) {
				$schema['image'] = BSF_AIOSRS_Pro_Schema_Template::get_image_schema( $data['podcast-image'] );
			} else {
				$featured_image = wp_get_attachment_image_src( get_post_thumbnail_id( $post['ID'] ), 'full' );
				if ( $featured_image ) {
					$schema['image'] = BSF_AIOSRS_Pro_Schema_Template::get_image_schema( $featured_image );
				}
			}

			// Audio.
			if ( ! empty( $data['audio-file-url'] ) ) {
				$schema['audio'] = array(
					'@type'      => 'AudioObject',
					'contentUrl' => esc_url_raw( $data['audio-file-url'] ),
				);
			}

			// Duration (ISO 8601).
			if ( ! empty( $data['episode-duration'] ) ) {
				$schema['duration'] = wp_strip_all_tags( $data['episode-duration'] );
			}

			// Author.
			if ( ! empty( $data['author-name'] ) ) {
				$schema['author'] = array(
					'@type' => 'Person',
					'name'  => wp_strip_all_tags( $data['author-name'] ),
				);
			}

			// Publisher (with logo).
			if ( ! empty( $data['owner-name'] ) || ! empty( $data['owner-email'] ) ) {
				$schema['publisher'] = array(
					'@type' => 'Organization',
					'name'  => ! empty( $data['owner-name'] ) ? wp_strip_all_tags( $data['owner-name'] ) : '',
				);

				if ( ! empty( $data['owner-email'] ) ) {
					$schema['publisher']['email'] = sanitize_email( $data['owner-email'] );
				}

				if ( isset( $data['site-logo'] ) && ! empty( $data['site-logo'] ) ) {
					$schema['publisher']['logo'] = BSF_AIOSRS_Pro_Schema_Template::get_image_schema( $data['site-logo'], 'ImageObject2' );
				} else {
					$logo_id = get_post_thumbnail_id( $post['ID'] );
					if ( isset( $general_settings['site-logo'] ) && 'custom' === $general_settings['site-logo'] ) {
						$logo_id = $general_settings['site-logo-custom'] ?? '';
					}
					if ( $logo_id ) {
						add_filter( 'intermediate_image_sizes_advanced', 'BSF_AIOSRS_Pro_Schema_Template::logo_image_sizes' );
						$logo_image = wp_get_attachment_image_src( (int) $logo_id, 'aiosrs-logo-size' );
						if ( isset( $logo_image[3] ) && 1 !== $logo_image[3] ) {
							BSF_AIOSRS_Pro_Schema_Template::generate_logo_by_width( (int) $logo_id );
							$logo_image = wp_get_attachment_image_src( (int) $logo_id, 'aiosrs-logo-size' );
						}
						remove_filter( 'intermediate_image_sizes_advanced', 'BSF_AIOSRS_Pro_Schema_Template::logo_image_sizes' );
						$schema['publisher']['logo'] = BSF_AIOSRS_Pro_Schema_Template::get_image_schema( (array) $logo_image, 'ImageObject' );
					}
				}
			}

			// Episode Number.
			if ( ! empty( $data['episode-number'] ) ) {
				$schema['episodeNumber'] = (int) $data['episode-number'];
			}

			// Part of Season.
			if ( ! empty( $data['season-number'] ) ) {
				$schema['partOfSeason'] = array(
					'@type'        => 'PodcastSeason',
					'seasonNumber' => (int) $data['season-number'],
				);

				if ( ! empty( $data['season-name'] ) ) {
					$schema['partOfSeason']['name'] = wp_strip_all_tags( $data['season-name'] );
				}
				if ( ! empty( $data['season-url'] ) ) {
					$schema['partOfSeason']['url'] = esc_url_raw( $data['season-url'] );
				}
			}

			// Genre.
			if ( ! empty( $data['podcast-category'] ) ) {
				$schema['genre'] = wp_strip_all_tags( $data['podcast-category'] );
			}

			// Copyright.
			if ( ! empty( $data['copyright-text'] ) ) {
				$schema['copyrightHolder'] = array(
					'@type' => 'Organization',
					'name'  => wp_strip_all_tags( $data['copyright-text'] ),
				);
			}

			return apply_filters( 'wp_schema_pro_schema_podcast', $schema, $data, $post );
		}
	}
}
