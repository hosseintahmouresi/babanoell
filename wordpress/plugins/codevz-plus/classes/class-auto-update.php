<?php if ( ! defined( 'ABSPATH' ) ) {exit;} // Exit if accessed directly.

/**
 * Automatic plugins update
 * 
 * @since  2.0.0
 */

class Codevz_Plugins_Update {

	use Codevz_Plus_Instance;

	private $plugins = [];

	public function __construct() {

		// WordPress admin init.
		add_action( 'admin_init', [ $this, 'admin_init' ], 99 );

		// Inform WordPress version of plugins
		add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'transient' ], 99 );

		// Set plugin changelog
		add_filter( 'plugins_api', [ $this, 'changelog' ], 99, 3 );

		// Inform WordPress of plugins zip files
		add_filter( 'upgrader_pre_download', [ $this, 'download' ], 99, 3 );

	}

	// Init plugins updater.
	public function admin_init() {

		// Automatic update for this plugins.
		$this->plugins = apply_filters( 'codevz_plugins_update', 
			[
				'codevz-plus' 	=> Codevz_Plus::option( 'white_label_plugin_name', esc_html__( 'Codevz Plus', 'codevz-plus' ) ), 
				'js_composer' 	=> esc_html__( 'WPBakery Page Builder', 'codevz-plus' ), 
				'revslider' 	=> esc_html__( 'Revolution Slider', 'codevz-plus' ),
			]
		);

		// Remove plugins default updater.
		remove_filter( 'upgrader_pre_download', [ 'Vc_Updater', 'preUpgradeFilter' ], 99 );
		remove_filter( 'pre_set_site_transient_update_plugins', [ 'Vc_Updating_Manager', 'check_update' ], 99 );
		remove_filter( 'in_plugin_update_message-js_composer/js_composer.php', [ 'Vc_Updating_Manager', 'addUpgradeMessageLink' ], 99 );
		remove_filter( 'pre_set_site_transient_update_plugins', [ 'RevSliderUpdate', 'set_update_transient' ], 99 );

	}

	// Inform WP about new plugins version.
	public function transient( $transient ) {

		$api = apply_filters( 'codevz_config_api', Codevz_Plus::$api );

		// Get new versions
		$versions = get_transient( 'codevz_versions_json' );

		if ( empty( $versions['plugins'] ) || isset( $_GET[ 'force-check' ] ) ) {

			$request = wp_remote_get( $api . 'versions.json', [ 'timeout' => 300 ] );

			if ( ! is_wp_error( $request ) ) {

				$body = wp_remote_retrieve_body( $request );

				$versions = json_decode( $body, true );

	        	// Store JSON for 6 hours.
				set_transient( 'codevz_versions_json', $versions, 21600 );

			}
			
		}

		// There is no new versions.
		if ( ! isset( $versions['plugins'] ) ) {
			return $transient;
		}

		// Fix when there is no any plugins.
		if ( empty( $transient->response ) ) {
			$transient->response = [];
		}

		// Get current plugins versions.
		$plugins = function_exists( 'get_plugins' ) ? get_plugins() : [];

		// Inform WordPress about new plugins versions.
		foreach( $this->plugins as $slug => $title ) {

			if ( class_exists( 'stdClass' ) && isset( $versions['plugins'][ $slug ]['version'] ) ) {

				// Get current plugin version.
				$current_ver = isset( $plugins[ $slug . '/' . $slug . '.php' ]['Version'] ) ? $plugins[ $slug . '/' . $slug . '.php' ]['Version'] : '999';
				$new_version = $versions['plugins'][ $slug ]['version'];
				
				// Compare current and new plugin version.
				if ( $current_ver != $new_version && version_compare( $current_ver, $new_version, '<' ) ) {

					$obj 				= new stdClass();
					$obj->slug 			= $slug;
					$obj->name 			= $title;
					$obj->title 		= $title;
					$obj->new_version 	= $versions['plugins'][ $slug ]['version'];
					$obj->url 			= '';
					$obj->tested 		= '99.0';
					$obj->package 		= $api . $slug . '.zip';

					$transient->response[ $slug . '/' . $slug . '.php' ] = $obj;

				} else if ( isset( $transient->response[ $slug . '/' . $slug . '.php' ] ) ) {
					unset( $transient->response[ $slug . '/' . $slug . '.php' ] );
				}

			}

		}

		return $transient;

	}

	/**
	 * Set change log for plugins.
	 */
	public function changelog( $false, $action, $arg ) {

		// Create new information for codevz plus.
		if ( isset( $arg->slug ) && $arg->slug === 'codevz-plus' ) {

			$obj 				= new stdClass();
			$obj->author 		= Codevz_Plus::option( 'white_label_author', 'codevz-plus' );
			$obj->name 			= Codevz_Plus::option( 'white_label_plugin_name', 'Codevz Plus' );
			$obj->slug 			= 'codevz-plus';
			$obj->plugin_name 	= 'codevz-plus';
			$obj->description 	= '';
			$obj->sections 		= [ 'changelog' => 'Codevz Plus plugin by codevz.com' ];

			return $obj;
		}

		return $false;
	}

	/**
	 * Inform WordPress for plugins zip files.
	 */
	public function download( $reply, $package, $updater ) {

		foreach( $this->plugins as $slug => $title ) {

			if ( Codevz_Plus::contains( $package, $slug ) ) {

				return false;

			}

		}

		return $reply;
	}

}

// Automatic updates.
Codevz_Plugins_Update::instance();