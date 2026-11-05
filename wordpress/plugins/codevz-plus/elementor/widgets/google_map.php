<?php if ( ! defined( 'ABSPATH' ) ) {exit;} // Exit if accessed directly.

use Elementor\Utils;
use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Image_Size;
use Elementor\Core\Kits\Documents\Tabs\Global_Colors;

class Xtra_Elementor_Widget_google_map extends Widget_Base {

	protected $id = 'cz_google_map';

	public function get_name() {
		return $this->id;
	}

	public function get_title() {
		return esc_html__( 'Google Map', 'codevz-plus' );
	}

	public function get_icon() {
		return 'xtra-google-map';
	}

	public function get_categories() {
		return [ 'xtra' ];
	}

	public function get_keywords() {

		return [

			esc_html__( 'XTRA', 'codevz-plus' ),
			esc_html__( 'Google', 'codevz-plus' ),
			esc_html__( 'Map', 'codevz-plus' ),
			esc_html__( 'Location', 'codevz-plus' ),
			esc_html__( 'Address', 'codevz-plus' ),

		];

	}

	public function get_style_depends() {
		return [ $this->id, 'cz_parallax' ];
	}

	public function get_script_depends() {
		return [ $this->id, 'cz_parallax' ];
	}

	public function register_controls() {

		$free = Codevz_Plus::is_free();

		$this->start_controls_section(
			'section_content',
			[
				'label' => esc_html__( 'Settings', 'codevz-plus' ),
				'tab' => Controls_Manager::TAB_CONTENT,
			]
		);

		$this->add_control(
			'apikey',
			[
				'label' => esc_html__( 'Google API Key', 'codevz-plus' ),
				'type' => Controls_Manager::TEXT,
				'description' => esc_html__("Get your API Key from",'codevz-plus') . ' <a href="https://goo.gl/wVcKPP" target="_blank">' . esc_html__("Here",'codevz-plus') . '</a>',
			]
		);

		$this->add_control(
			'lat',
			[
				'label' 		=> esc_html__( 'Lat', 'codevz-plus' ),
				'type' 			=> Controls_Manager::TEXT,
				'description' 	=> esc_html__("Find your location Lat and Long from",'codevz-plus') . ' <a href="http://www.latlong.net" target="_blank">' . esc_html__("Here",'codevz-plus') . '</a>',
				'default' 		=> '40.712784',
				'placeholder' 	=> '40.712784',
			]
		);
		
		$this->add_control(
			'long',
			[
				'label' 		=> esc_html__( 'Long', 'codevz-plus' ),
				'type' 			=> Controls_Manager::TEXT,
				'default' 		=> '-74.005941',
				'placeholder' 	=> '-74.005941',
			]
		);

		$this->add_responsive_control(
			'sk_map',
			[
				'label' 	=> esc_html__( 'Map size', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'width', 'height', 'border' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.gmap' ),
			]
		);

		$this->add_control(
			'zoom',
			[
				'label' => esc_html__( 'Zoom', 'codevz-plus' ),
				'type' => $free ? 'codevz_pro' : Controls_Manager::NUMBER,
				'min' => 0,
				'max' => 19,
				'step' => 1,
				'default' => 14,
			]
		);

		$this->add_control(
			'offsetx',
			[
				'label' => esc_html__( 'Marker Offset X', 'codevz-plus' ),
				'type' => $free ? 'codevz_pro' : Controls_Manager::NUMBER,
				'min' => -400,
				'max' => 400,
				'step' => 10,
				'default' => 0,
			]
		);

		$this->add_control(
			'offsety',
			[
				'label' => esc_html__( 'Marker Offset Y', 'codevz-plus' ),
				'type' => $free ? 'codevz_pro' : Controls_Manager::NUMBER,
				'min' => -400,
				'max' => 400,
				'step' => 10,
				'default' => 0,
			]
		);
		$this->end_controls_section();

		$this->start_controls_section(
			'section_settings',
			[
				'label' => esc_html__( 'Settings', 'codevz-plus' ),
			]
		);

		$this->add_control(
			'color',
			[
				'label' => esc_html__( 'Color scheme', 'codevz-plus' ),
				'type' => $free ? 'codevz_pro' : Controls_Manager::SELECT,
				'default' => 'standard',
				'options' => [
					'standard'  => esc_html__( 'Standard', 'codevz-plus' ),
					'light' => esc_html__( 'Light (Silver)', 'codevz-plus' ),
					'dark' => esc_html__( 'Dark', 'codevz-plus' ),
					'retro' => esc_html__( 'Retro', 'codevz-plus' ),
					'custom' => esc_html__( 'Custom Color', 'codevz-plus' ),
				],
			]
		);

		$this->add_control(
			'custom_color',
			[
				'label' => esc_html__( 'Custom color', 'codevz-plus' ),
				'type' => $free ? 'codevz_pro' : Controls_Manager::COLOR,
				'global' => [
					'default' => Global_Colors::COLOR_ACCENT,
				],
				'condition' => [
					'color' => 'custom',
				]
			]
		);

		$this->add_control(
			'grayscale',
			[
				'label' => esc_html__( 'Grayscale Effect?', 'codevz-plus' ),
				'type' 	=> $free ? 'codevz_pro' : Controls_Manager::SWITCHER
			]
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_marker',
			[
				'label' => esc_html__( 'Marker', 'codevz-plus' ),
			]
		);

		$this->add_control(
			'marker',
			[
				'label' => esc_html__( 'Marker', 'codevz-plus' ),
				'type' => Controls_Manager::SELECT,
				'default' => 'off',
				'options' => [
					'off'  => esc_html__( 'Off', 'codevz-plus' ),
					'default' => esc_html__( 'Default Marker', 'codevz-plus' ),
					'custom' => esc_html__( 'Custom Image', 'codevz-plus' ),
				],
			]
		);

		$this->add_control(
			'image',
			[
				'label' => esc_html__( 'Marker image', 'codevz-plus' ),
				'type' => Controls_Manager::MEDIA,
				'condition' => [
					'marker' => 'custom',
				]
			]
		);

		$this->add_control(
			'infowindow',
			[
				'label' => esc_html__( 'Info text', 'codevz-plus' ),
				'type' => Controls_Manager::TEXT,
				'condition' => [
					'marker' => ['custom', 'default'],
				]
			]
		);

		$this->add_control(
			'infowindowdefault',
			[
				'label' => esc_html__( 'Show info by default?', 'codevz-plus' ),
				'type' => $free ? 'codevz_pro' : Controls_Manager::SWITCHER,
				'condition' => [
					'marker' => ['default','custom'],
				]
			]
		);

		$this->add_control(
			'scrollwheel',
			[
				'label' => esc_html__( 'Enable mouse wheel?', 'codevz-plus' ),
				'type' => $free ? 'codevz_pro' : Controls_Manager::SWITCHER,
				'label_on' => esc_html__( 'Yes', 'codevz-plus' ),
				'label_off' => esc_html__( 'No', 'codevz-plus' ),
				'return_value' => 'yes',
				'default' => 'no',
				'condition' => [
					'marker' => ['default','custom'],
				]
			]
		);

		$this->add_control(
			'hidecontrols',
			[
				'label' => esc_html__( 'Hide map controls?', 'codevz-plus' ),
				'type' => $free ? 'codevz_pro' : Controls_Manager::SWITCHER,
				'label_on' => esc_html__( 'Yes', 'codevz-plus' ),
				'label_off' => esc_html__( 'No', 'codevz-plus' ),
				'return_value' => 'yes',
				'default' => 'no',
				'condition' => [
					'marker' => ['default','custom'],
				]
			]
		);

		$this->end_controls_section();

		// Inverted radius controls.
		Xtra_Elementor::inverted_radius_controls( $this );

		// Parallax settings.
		Xtra_Elementor::parallax_settings( $this );

	}

	public function render() {

		$settings = $this->get_settings_for_display();

		$settings['id'] = 'cz' . wp_rand( 1111,9999 );

		$isfront = false;

		// Data
		$str_data_apikey = $str_apikey = '';

		if ( $settings['apikey'] ) {
			$str_apikey = '?key=' . $settings['apikey'] ;
			$str_data_apikey = ' data-api-key="' . $settings['apikey'] . '"' ;
		} else {
			return '<div style="border:1px solid #ccc;border-radius:5px;color:#bb0000;margin:20px 0;padding:30px 0;text-align: center;">
			<h3>Google API Key Error!</h3><h5 style="color:#555">Get your API Key from <a style="color:#bb0000" href="https://goo.gl/wVcKPP">here</a></h5></div>';
		}

		$classes = [];
		$classes[] = 'gmap';
		$classes[] = ( $settings['grayscale'] === 'yes' ) ? 'fx_grayscale fx_remove_grayscale_hover' : '';
		$classes[] = empty( $settings['ir_position'] ) ? '' : 'codevz-plus-inverted-radius-' . esc_attr( $settings['ir_position'] );

		$str = '<div id="'. $settings['id'] .'"'. Codevz_Plus::classes( [], $classes ) . $str_data_apikey .  '> </div>';

		// Map Marker.
		if ( $settings[ 'marker' ] === 'custom' && ! empty( $settings[ 'image' ] ) ) {
			$settings[ 'image_size' ] = 'full';
			$settings[ 'markerimage' ] = Group_Control_Image_Size::get_attachment_image_src( $settings[ 'image' ][ 'id' ], 'image', $settings );
		}

		// Map ID.
		$settings[ 'map' ] = $settings[ 'id' ];

		// Maps script.
		$str .= Codevz_Plus::google_maps_script( $settings );

		Xtra_Elementor::parallax( $settings );

		$str = apply_filters( 'codevz_google_map', $str, $settings );

		echo do_shortcode( $str );
  
		Xtra_Elementor::parallax( $settings, true );

	}

}