<?php if ( ! defined( 'ABSPATH' ) ) {exit;} // Exit if accessed directly.

use Elementor\Utils;
use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Image_Size;
use Elementor\Core\Kits\Documents\Tabs\Global_Colors;

class Xtra_Elementor_Widget_Mapp extends Widget_Base {

	protected $id = 'cz_persian_map';

	public function get_name() {
		return $this->id;
	}

	public function get_title() {
		return esc_html__( 'Persian Map', 'codevz-plus' );
	}

	public function get_icon() {
		return 'xtra-persian-map';
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
			esc_html__( 'Persian', 'codevz-plus' ),

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
				'label' => esc_html__( 'Map.ir API Key', 'codevz-plus' ),
				'type' => Controls_Manager::TEXT,
				'description' => esc_html__("Get your API Key from","codevz") . ' <a href="https://accounts.map.ir/login" target="_blank">' . esc_html__("Here","codevz") . '</a>',
			]
		);

		$this->add_control(
			'lat',
			[
				'label' 		=> esc_html__( 'Lat', 'codevz-plus' ),
				'type' 			=> Controls_Manager::TEXT,
				'description' 	=> esc_html__("Find your location Lat and Long from","codevz") . ' <a href="http://www.latlong.net" target="_blank">' . esc_html__("Here","codevz") . '</a>',
				'default' 		=> '35.700511',
				'placeholder' 	=> '35.700511',
			]
		);
		
		$this->add_control(
			'long',
			[
				'label' 		=> esc_html__( 'Long', 'codevz-plus' ),
				'type' 			=> Controls_Manager::TEXT,
				'default' 		=> '51.365479',
				'placeholder' 	=> '51.365479',
			]
		);

		$this->add_responsive_control(
			'sk_map',
			[
				'label' 	=> esc_html__( 'Map size', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'width', 'height', 'border' ],
				'selectors' => Xtra_Elementor::sk_selectors( '[id*="codevz_mapp_"]' ),
			]
		);

		$this->add_control(
			'zoom',
			[
				'label' => esc_html__( 'Zoom', 'codevz-plus' ),
				'type' => $free ? 'codevz_pro' : Controls_Manager::NUMBER,
				'min' => 6,
				'max' => 19,
				'step' => 1,
				'default' => 17
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
			'dark_mode',
			[
				'label' => esc_html__( 'Dark mode?', 'codevz-plus' ),
				'type' 	=> $free ? 'codevz_pro' : Controls_Manager::SWITCHER
			]
		);

		$this->add_control(
			'gray_mode',
			[
				'label' => esc_html__( 'Gray mode?', 'codevz-plus' ),
				'type' 	=> $free ? 'codevz_pro' : Controls_Manager::SWITCHER
			]
		);

		$this->add_control(
			'fullscreen',
			[
				'label' => esc_html__( 'Fullscreen?', 'codevz-plus' ),
				'type' 	=> $free ? 'codevz_pro' : Controls_Manager::SWITCHER
			]
		);

		$this->add_control(
			'zoom_controls',
			[
				'label' => esc_html__( 'Zoom controls?', 'codevz-plus' ),
				'type' 	=> $free ? 'codevz_pro' : Controls_Manager::SWITCHER
			]
		);

		$this->add_control(
			'my_location',
			[
				'label' => esc_html__( 'My location?', 'codevz-plus' ),
				'type' 	=> $free ? 'codevz_pro' : Controls_Manager::SWITCHER
			]
		);

		$this->add_control(
			'right_click',
			[
				'label' => esc_html__( 'Right click?', 'codevz-plus' ),
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
			'image',
			[
				'label' => esc_html__( 'Marker', 'codevz-plus' ),
				'type' => Controls_Manager::MEDIA
			]
		);

		$this->add_control(
			'marker_title',
			[
				'label' => esc_html__( 'Marker title', 'codevz-plus' ),
				'type' => $free ? 'codevz_pro' : Controls_Manager::TEXT
			]
		);

		$this->add_control(
			'marker_desc',
			[
				'label' => esc_html__( 'Marker description', 'codevz-plus' ),
				'type' => $free ? 'codevz_pro' : Controls_Manager::TEXT
			]
		);

		$this->end_controls_section();

		// Parallax settings.
		Xtra_Elementor::parallax_settings( $this );

	}

	public function render() {

		$settings = $this->get_settings_for_display();

		$atts = [
			'lat' 			=> $settings['lat'],
			'lng' 			=> $settings['long'],
			'zoom' 			=> $settings['zoom'],
			'api_key' 		=> $settings['apikey'],
			'dark_mode' 	=> $settings['dark_mode'],
			'fullscreen' 	=> $settings['fullscreen'],
			'zoom_controls' => $settings['zoom_controls'],
			'my_location' 	=> $settings['my_location'],
			'right_click' 	=> $settings['right_click'],
			'marker' 		=> isset( $settings[ 'image' ][ 'url' ] ) ? $settings[ 'image' ][ 'url' ] : '',
			'marker_title' 	=> $settings['marker_title'],
			'marker_desc' 	=> $settings['marker_desc']
		];

		$shortcode = '[codevz_persian_map ';

		foreach( $atts as $key => $value ) {
			$shortcode .= $key . '="' . $value . '" ';
		}

		$shortcode .= ']';

		Xtra_Elementor::parallax( $settings );

		echo do_shortcode( $shortcode );

		Xtra_Elementor::parallax( $settings, true );

	}

}