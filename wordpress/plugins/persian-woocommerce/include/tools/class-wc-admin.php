<?php

defined( 'ABSPATH' ) || exit;


use Carbon\Carbon;
use Morilog\Jalali\Jalalian;

if ( ! class_exists( 'PW_WC_Admin' ) ) {

	class PW_WC_Admin {

		/**
		 * @const array valid pages
		 */
		public const PAGES = [
			'wc-admin',
		];

		/**
		 * @const array analytics paths
		 */
		public const PATHS = [
			'/analytics/overview',
			'/analytics/products',
			'/analytics/revenue',
			'/analytics/orders',
			'/analytics/variations',
			'/analytics/categories',
			'/analytics/coupons',
			'/analytics/taxes',
			'/analytics/downloads',
			'/analytics/stock',
			'/analytics/settings',
		];

		/**
		 * @const array report types
		 */
		public const REPORTS = [
			'revenue',
			'categories',
			'coupons',
			'customers',
			'downloads',
			'orders',
			'products',
			'taxes',
			'variations',
			'coupons_stats',
			'customers_stats',
			'downloads_stats',
			'orders_stats',
			'products_stats',
			'taxes_stats',
			'variations_stats',
		];

		/**
		 * Handle jalali enabling in the wc-admin client area for analytics
		 */
		public function __construct() {

			$is_jalali_enabled = PW()->get_options( 'enable_jalali_analytics', 'no' ) == 'yes';

			if ( ! $is_jalali_enabled ) {
				return;
			}

			add_action( 'init', [ $this, 'update_woocommerce_default_date_range' ] );

			if ( $this->is_target_page() ) {
				add_action( 'admin_enqueue_scripts', [ $this, 'jalali_frontend' ] );
				add_action( 'admin_enqueue_scripts', [ $this, 'admin_assets' ] );
			}

			foreach ( self::REPORTS as $report ) {

				add_filter( 'woocommerce_analytics_' . $report . '_query_args', [
					$this,
					'gregorian_query_dates',
				], 100 );

				add_filter( 'woocommerce_analytics_' . $report . '_select_query',
					[ $this, 'jalali_result_dates' ],
					100, 2
				);

			}

		}

		/**
		 * Update default date range based on current options we provided
		 *
		 * @return void
		 */
		public function update_woocommerce_default_date_range(): void {
			update_option( 'woocommerce_default_date_range', 'period=year&compare=previous_period' );
		}

		/**
		 * Check if current viewing page is one of analytics subpages
		 *
		 * @return bool
		 */
		public function is_target_page(): bool {

			if ( ! isset( $_GET['path'] ) || ! isset( $_GET['page'] ) ) {
				return false;
			}

			$current_path = urldecode( $_GET['path'] );
			$current_page = $_GET['page'];

			$is_valid_page = in_array( $current_path, self::PATHS );
			$is_valid_path = in_array( $current_page, self::PAGES );

			if ( ! $is_valid_path || ! $is_valid_page ) {
				return false;
			}

			return true;
		}

		/**
		 * Enqueue jalali moment and its configuration
		 *
		 * @return void
		 */
		public function jalali_frontend(): void {

			wp_enqueue_script( 'moment-jalali',
				PW()->plugin_url( 'assets/js/jalali-moment/jalali-moment.browser.js' ),
				[ 'moment' ],
				PW_VERSION
			);

			$jalali_moment_config = <<< JALALI_CONFIG
                                            moment.locale('fa');
                                            moment.useJalaliSystemPrimarily({ useGregorianParser: true });
                                            JALALI_CONFIG;

			wp_add_inline_script( 'moment-jalali', $jalali_moment_config );

		}

		/**
		 * Configure admin area ( analytics ) with css and js
		 *
		 * @return void
		 */
		public function admin_assets(): void {

			wp_enqueue_style( 'pw-admin-client',
				PW()->plugin_url( 'assets/css/admin-client.css' ),
				[],
			);

			wp_enqueue_script( 'pw-admin-client',
				PW()->plugin_url( 'assets/js/admin-client.js' ),
				[ 'jquery', 'moment' ],
				PW_VERSION
			);

		}

		/**
		 * Convert gregorian dates to jalali in woocommerce data array
		 */
		public function gregorian_query_dates( $query_vars ) {

			$keys = [ 'after', 'before', 'order_after', 'date_start', 'date_end' ];

			foreach ( $keys as $key ) {
				if ( isset( $query_vars[ $key ] ) ) {
					$query_vars[ $key ] = $this->convert_jalali_gregorian( $query_vars[ $key ] );
				}
			}

			return $query_vars;
		}

		public function jalali_result_dates( $result ) {

			$this->convert_gregorian_jalali_object( $result );

			return $result;
		}

		/**
		 * Convert gregorian date to jalali in array
		 */
		public function convert_gregorian_jalali_array( $array ) {

			foreach ( $array as $key => $value ) {

				if ( is_string( $value ) && strtotime( $value ) !== false ) {
					$array[ $key ] = $this->convert_gregorian_jalali( $value );
				} elseif ( is_array( $value ) ) {
					$array[ $key ] = $this->convert_gregorian_jalali_array( $value );
				} elseif ( is_object( $value ) ) {
					$array[ $key ] = $this->convert_gregorian_jalali_object( $value );
				}

			}

			return $array;
		}

		/**
		 * Convert gregorian date to jalali in object
		 */
		public function convert_gregorian_jalali_object( $object ) {

			foreach ( $object as $key => $value ) {

				if ( is_string( $value ) && strtotime( $value ) !== false ) {
					$object->$key = $this->convert_gregorian_jalali( $value );
				} elseif ( is_array( $value ) ) {
					$object->$key = $this->convert_gregorian_jalali_array( $value );
				} elseif ( is_object( $value ) ) {
					$object->$key = $this->convert_gregorian_jalali_object( $value );
				}

			}

			return $object;
		}

		/**
		 * Convert gregorian date to jalali in string
		 *
		 * @return string
		 */
		public function convert_gregorian_jalali( $date ): string {

			try {

				$jalali_date = Jalalian::fromCarbon( Carbon::parse( $date ) )->format( 'Y-m-d H:i:s' );

				return $jalali_date;

			} catch ( Exception $e ) {
				// If parsing fails, return the original date string
				return $date;
			}

		}

		/**
		 * Convert jalali date to gregorian for backend usage
		 */
		public function convert_jalali_gregorian( $jalali_date ) {

			try {

				$jalalian       = Jalalian::fromFormat( 'Y-m-d\TH:i:s', $jalali_date );
				$gregorian_date = $jalalian->toCarbon()->toString();

				return $gregorian_date;

			} catch ( Exception $e ) {
				// If parsing fails, return the original date string
				return $jalali_date;
			}

		}

	}

}

PW()->tools->wc_admin = new PW_WC_Admin();
