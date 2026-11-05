<?php
/**
 * Schema Pro Init
 *
 * @package Schema Pro
 */

define( 'BSF_REMOVE_WP-SCHEMA-PRO_FROM_REGISTRATION_LISTING', true );

if ( ! class_exists( 'BSF_AIOSRS_Pro' ) ) {

	/**
	 * BSF_AIOSRS_Pro initial setup
	 *
	 * @since 1.0.0
	 */
	class BSF_AIOSRS_Pro {

		/**
		 * Class instance.
		 *
		 * @access private
		 * @var self
		 */
		private static $instance;

		/**
		 * Initiator
		 *
		 * @return self
		 */
		public static function get_instance(): self {
			if ( ! isset( self::$instance ) ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		/**
		 *  Constructor
		 */
		public function __construct() {
			// Includes Required Files.
			$this->includes();
			add_action( 'admin_notices', array( $this, 'how_to_schema_deprecated_notice' ) );
			add_action( 'wp_ajax_wp_schema_pro_setup_wizard_notice', array( $this, 'wp_schema_pro_setup_wizard_notice_callback' ) );
		}

		/**
		 * Display a notice about the deprecation of the HowTo schema.
		 */
		public function how_to_schema_deprecated_notice() {
			$screen = get_current_screen();

			// Only display the notice on the settings page of the Schema Pro
			if ( 'settings_page_aiosrs_pro_admin_menu_page' === $screen->id ) {
				echo '<div class="wp-schema-pro-how-to-deprecated-notice notice notice-warning is-dismissible">';
				echo '<p>' . esc_html__( 'Please be advised that the HowTo schema is now deprecated according to the latest Google guidelines ', 'wp-schema-pro' ) . '<a href="https://wpschema.com/docs/how-to-schema/" target="_blank">' . esc_html__( 'Take a look here.', 'wp-schema-pro' ) . '</a></p>';
				echo '</div>';
			}
		}

		/**
		 * Dismiss Notice
		 *
		 * @return void
		 */
		public function wp_schema_pro_setup_wizard_notice_callback(): void {
			check_ajax_referer( 'wp-schema-pro-setup-wizard-notice', 'nonce' );
			delete_transient( 'wp-schema-pro-activated' );
			wp_send_json_success();
		}

		/**
		 * Include required files.
		 *
		 * @since 1.0.0
		 * @return void
		 */
		public function includes(): void {
			require_once BSF_AIOSRS_PRO_DIR . 'classes/lib/target-rule/class-bsf-target-rule-fields.php';
			require_once BSF_AIOSRS_PRO_DIR . 'classes/lib/class-bsf-custom-post-list-table.php';
			require_once BSF_AIOSRS_PRO_DIR . 'classes/class-wp-schema-pro-yoast-compatibility.php';
			require_once BSF_AIOSRS_PRO_DIR . 'classes/class-bsf-aiosrs-pro-admin.php';
			require_once BSF_AIOSRS_PRO_DIR . 'classes/class-bsf-aiosrs-pro-schema.php';
			require_once BSF_AIOSRS_PRO_DIR . 'classes/class-bsf-aiosrs-pro-custom-fields-markup.php';
			require_once BSF_AIOSRS_PRO_DIR . 'classes/class-bsf-aiosrs-pro-branding.php';
			require_once BSF_AIOSRS_PRO_DIR . 'classes/class-bsf-aiosrs-pro-amp.php';

				// Load the NPS Survey library.
			if ( ! class_exists( 'Schema_Pro_Nps_Survey' ) ) {
				require_once BSF_AIOSRS_PRO_DIR . 'lib/class-schema-pro-nps-survey.php';
			}

			/**
			 * Frontend.
			 */
			require_once BSF_AIOSRS_PRO_DIR . 'classes/class-bsf-aiosrs-pro-schema-template.php';
			require_once BSF_AIOSRS_PRO_DIR . 'classes/class-bsf-aiosrs-pro-markup.php';
		}

	}
}

/**
 * Kicking this off by calling 'get_instance()' method
 */
BSF_AIOSRS_Pro::get_instance();
