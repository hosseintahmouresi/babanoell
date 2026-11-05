<?php if ( ! defined( 'ABSPATH' ) ) {exit;} // Exit if accessed directly.

/**
 * Elementor custom templates.
 * 
 * @since  5.1
 */
function codevz_elementor_enq() {

	$templates = apply_filters( 'codevz_config_templates', null );

	if ( $templates ) {

		wp_enqueue_script( 
			'codevz-elementor-templates', 
			Codevz_Plus::$url . 'elementor/assets/js/elementor-templates.js', 
			[ 'jquery', 'elementor-editor' ], 
			'1.0', 
			true 
		);

		wp_localize_script('codevz-elementor-templates', 'codevzData', [
			'ajaxurl' 	=> admin_url( 'admin-ajax.php' ),
			'nonce'   	=> wp_create_nonce( 'codevz_templates_nonce' ),
			'all'   	=> esc_html__( 'All', 'codevz-plus' ),
			'light'   	=> esc_html__( 'Light', 'codevz-plus' ),
			'dark'   	=> esc_html__( 'Dark', 'codevz-plus' ),
			'about'   	=> esc_html__( 'About', 'codevz-plus' ),
			'service'   => esc_html__( 'Service', 'codevz-plus' ),
			'gallery'   => esc_html__( 'Gallery', 'codevz-plus' ),
			'hero'   	=> esc_html__( 'Hero', 'codevz-plus' ),
			'blog'   	=> esc_html__( 'Blog', 'codevz-plus' ),
			'shop'   	=> esc_html__( 'Shop', 'codevz-plus' ),
			'faq'   	=> esc_html__( 'FAQ', 'codevz-plus' ),
			'slide'  	=> esc_html__( 'Slide', 'codevz-plus' ),
			'form'   	=> esc_html__( 'Form', 'codevz-plus' ),
		]);

	}

}
add_action( 'elementor/editor/after_enqueue_scripts', 'codevz_elementor_enq' );

// List of templates.
function codevz_elementor_templates() {

	check_ajax_referer( 'codevz_templates_nonce', 'security' );

	$api = apply_filters( 'codevz_config_api_templates', 'https://xtratheme.com/api/elementor-templates/' );
	$templates = apply_filters( 'codevz_config_templates', null );

	$response = [];

	if ( ! empty( $templates ) ) {

		foreach( $templates as $id => $desc ) {

			$response[] = [
				'id'       => $id,
				'title'    => ucfirst( str_replace( '-', ' ', $id ) ),
				'desc'     => $desc,
				'thumb'    => $api . $id . ".jpg",
				'json'     => $api . $id . ".json",
			];

		}

	}

	wp_send_json_success( $response );

}
add_action( 'wp_ajax_codevz_get_templates', 'codevz_elementor_templates' );

// Import section template.
function codevz_fetch_template_json() {

	check_ajax_referer('codevz_templates_nonce', 'security');

	$template_id = empty( $_POST['template_id'] ) ? '' : sanitize_text_field( $_POST['template_id'] );

	if ( ! $template_id ) {
		wp_send_json_error( 'No template ID provided' );
	}

	$api = apply_filters( 'codevz_config_api_templates', 'https://xtratheme.com/api/elementor-templates/' );

	$json_url = $api . $template_id . ".json";

	$response = wp_remote_get($json_url);
	if (is_wp_error($response)) {
		wp_send_json_error('Failed to fetch template JSON');
	}

	$body = wp_remote_retrieve_body($response);
	$data = json_decode($body, true);
	if (json_last_error() !== JSON_ERROR_NONE) {
		wp_send_json_error('Invalid JSON data');
	}

	wp_send_json_success( $data );

}
add_action( 'wp_ajax_codevz_fetch_template_json', 'codevz_fetch_template_json' );

// Import full custom template.
function codevz_import_inner_template() {
	check_ajax_referer('codevz_templates_nonce', 'security');

	$url = empty( $_POST['template_url'] ) ? '' : esc_url_raw( $_POST['template_url'] );

	if ( ! $url ) {
		wp_send_json_error(['msg' => 'No template URL']);
	}

	// Check if template already exists
	$existing = get_posts([
		'post_type'  => 'elementor_library',
		'meta_key'   => '_codevz_template_url',
		'meta_value' => $url,
		'numberposts'=> 1,
		'fields'     => 'ids',
	]);

	if (!empty($existing[0])) {
		wp_send_json_success(['id' => $existing[0], 'skipped' => true]);
	}

	$response = wp_remote_get($url);
	if (is_wp_error($response)) {
		wp_send_json_error(['msg' => 'Download failed']);
	}

	$body = wp_remote_retrieve_body($response);
	$json = json_decode($body, true);

	if (!$json || empty($json['content'])) {
		wp_send_json_error(['msg' => 'Invalid JSON']);
	}

	// Create Elementor library post
	$new_post_id = wp_insert_post([
		'post_title'   => $json['title'] ? $json['title'] : 'Section Template',
		'post_type'    => 'elementor_library',
		'post_status'  => 'publish'
	]);

	if ( ! $new_post_id ) {
		wp_send_json_error( [ 'msg' => 'Insert failed' ] );
	}

	// Save Elementor data
	update_post_meta( $new_post_id, '_elementor_data', wp_slash_strings_only( wp_json_encode( $json['content'] ) ) );
	update_post_meta( $new_post_id, '_elementor_edit_mode', 'builder' );
	update_post_meta( $new_post_id, '_elementor_template_type', 'section' );
	update_post_meta( $new_post_id, '_elementor_version', '3.4.3' );

	// Store original template URL to prevent duplicates
	update_post_meta( $new_post_id, '_codevz_template_url', $url );

	wp_send_json_success( [ 'id' => $new_post_id ] );

}
add_action( 'wp_ajax_codevz_import_inner_template', 'codevz_import_inner_template' );