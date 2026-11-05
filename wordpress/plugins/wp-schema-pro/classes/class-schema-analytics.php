<?php
/**
 * Schema Analytics.
 *
 * @package Schema Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'SCHEMA_Analytics' ) ) {
	/**
	 * Class SCHEMA_Analytics
	 *
	 * Handles analytics-related functionality for Schema Pro.
	 *
	 * @since 2.10.5
	 */
	class SCHEMA_Analytics {

		/**
		 * Schema Pro Analytics constructor.
		 *
		 * Initializing Schema Pro Analytics.
		 *
		 * @since 2.10.5
		 * @access public
		 */
		public function __construct() {

				// Migrate analytics tracking as early as possible.
				$this->schema_maybe_migrate_analytics_tracking();
			/**
			 * BSF analytics.
			 */
			if ( ! class_exists( 'BSF_Analytics_Loader' ) ) {
				require_once BSF_AIOSRS_PRO_DIR . 'admin/bsf-analytics/class-bsf-analytics-loader.php';
			}

			$bsf_analytics = BSF_Analytics_Loader::get_instance();

			$bsf_analytics->set_entity(
				array(
					'schema_pro' => array(
						'product_name'        => 'Schema Pro',
						'path'                => BSF_AIOSRS_PRO_DIR . 'admin/bsf-analytics',
						'author'              => 'Schema Pro',
						'time_to_display'     => '+24 hours',
						'deactivation_survey' => array(
							array(
								'id'                => 'deactivation-survey-wp-schema-pro', // 'deactivation-survey-<your-plugin-slug>'
								'popup_logo'        => BSF_AIOSRS_PRO_URI . 'admin/assets/images/schema-pro60x60.png',
								'plugin_slug'       => 'wp-schema-pro', // <your-plugin-slug>
								'plugin_version'    => BSF_AIOSRS_PRO_VER,
								'popup_title'       => 'Quick Feedback',
								'support_url'       => 'https://wpschema.com/contact/',
								'popup_description' => 'If you have a moment, please share why you are deactivating Schema Pro:',
								'show_on_screens'   => array( 'plugins' ),
							),
						),
						'hide_optin_checkbox' => true,
					),
				),
			);

			add_filter( 'bsf_core_stats', array( $this, 'add_schema_analytics_data' ) );
		}

		/**
		 * Migrates analytics tracking option from 'bsf_analytics_optin' to 'schema_pro_analytics_optin'.
		 *
		 * Checks if the old analytics tracking option ('bsf_analytics_optin') is set to 'yes'
		 * and if the new option ('schema_pro_analytics_optin') is not already set.
		 * If so, updates the new tracking option to 'yes' to maintain user consent during migration.
		 *
		 * @since 2.3.2
		 * @access public
		 *
		 * @return void
		 */
		public function schema_maybe_migrate_analytics_tracking() {
			$old_tracking = get_option( 'bsf_analytics_optin', false );
			$new_tracking = get_option( 'schema_pro_analytics_optin', false );
			if ( 'yes' === $old_tracking && false === $new_tracking ) {
				update_option( 'schema_pro_analytics_optin', 'yes' );
				$time = get_option( 'bsf_analytics_installed_time' );
				update_option( 'schema_pro_analytics_installed_time', $time );
			}
		}

		/**
		 * Callback function to add specific analytics data.
		 *
		 * @param array $stats_data existing stats_data.
		 * @since 2.10.5
		 * @return array
		 */
		public function add_schema_analytics_data( $stats_data ) {

			$global_schema_options = get_option( 'wp-schema-pro-global-schemas' );
			$general_settings      = get_option( 'wp-schema-pro-general-settings' ); 
			
			$breadcrumb_enabled = isset( $global_schema_options['breadcrumb'] ) && $global_schema_options['breadcrumb'] === '1';

			$site_links_enabled = isset( $global_schema_options['sitelink-search-box'] ) && $global_schema_options['sitelink-search-box'] === '1';

			$website_represents = isset( $general_settings['site-represent'] ) ? $general_settings['site-represent'] : '';
			$organization_type  = isset( $general_settings['organization'] ) ? $general_settings['organization'] : '';
			
			$stats_data['plugin_data']['schema_pro'] = array(
				'pro_version'          => BSF_AIOSRS_PRO_VER,
				'site_language'        => get_locale(),
				'onboarding_triggered' => ( 'yes' === get_option( 'wpsp_onboarding_triggered' ) ) ? 'yes' : 'no',
				'breadcrumb_settings'  => $breadcrumb_enabled ? 'yes' : 'no',
				'site_links_enabled'   => $site_links_enabled ? 'yes' : 'no',
				'website_represents'   => $website_represents,
				'organization_type'    => $organization_type,
			);
			
			$schema_posts = get_posts(
				array(
					'post_type'   => 'aiosrs-schema',
					'post_status' => 'publish',
					'numberposts' => -1,
				) 
			);

			$stats_data['plugin_data']['schema_pro']['numeric_values'] = array(
				'total_schema_post' => count( $schema_posts ),
			);
			

			$stats_data['plugin_data']['schema_pro']['numeric_values'] = $this->get_schema_type_count( $schema_posts );
			return $stats_data;

		}

		public function get_schema_type_count( $schema_posts ) {
			$schema_type_counts = array(
				'article'              => 0,
				'book'                 => 0,
				'course'               => 0,
				'event'                => 0,
				'job-posting'          => 0,
				'local-business'       => 0,
				'review'               => 0,
				'person'               => 0,
				'product'              => 0,
				'recipe'               => 0,
				'service'              => 0,
				'software-application' => 0,
				'video-object'         => 0,
				'faq'                  => 0,
				'how-to'               => 0,
				'custom-markup'        => 0,
				'image-license'        => 0,
				'vehicle-listing'      => 0,
				'podcast'              => 0,
				'movie-carousel'       => 0,
			);
			foreach ( $schema_posts as $post ) {
				$id          = $post->ID;
				$schema_type = get_post_meta( $id, 'bsf-aiosrs-schema-type', true );
				if ( isset( $schema_type_counts[ $schema_type ] ) ) {
					$schema_type_counts[ $schema_type ]++;
				}
			}
			return $schema_type_counts;
		}
	}
}
new SCHEMA_Analytics();
