<?php if ( ! defined( 'ABSPATH' ) ) {exit;} // Exit if accessed directly.

use Codevz_Plus as Codevz_Plus;
use Elementor\Utils;
use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Image_Size;

class Xtra_Elementor_Widget_logo extends Widget_Base { 

	protected $id = 'cz_logo';

	public function get_name() {
		return $this->id;
	}

	public function get_title() {
		return esc_html__( 'Header - Logo', 'codevz-plus' );
	}

	public function get_icon() {
		return 'xtra-logo';
	}

	public function get_categories() {
		return [ 'xtra' ];
	}

	public function get_keywords() {

		return [
			esc_html__( 'XTRA', 'codevz-plus' ),
			esc_html__( 'Image', 'codevz-plus' ),
			esc_html__( 'Photo', 'codevz-plus' ),
			esc_html__( 'Logo', 'codevz-plus' ),
			esc_html__( 'Site', 'codevz-plus' )
		];

	}

	public function get_style_depends() {
		return [ $this->id, 'cz_parallax', 'codevz-tilt' ];
	}

	public function get_script_depends() {
		return [ $this->id, 'cz_parallax', 'codevz-tilt' ];
	}

	protected function register_controls() {

		$this->start_controls_section(
			'section_logo',
			[
				'label' => esc_html__( 'Settings', 'codevz-plus' ),
			]
		);

		$this->add_control(
			'image',
			[
				'label' => esc_html__( 'Logo', 'codevz-plus' ),
				'type' => Controls_Manager::MEDIA,
				'default' => [
					'url' => Codevz_Plus::$url . 'assets/img/p.svg',
				],
			]
		);
		
		$this->add_group_control(
			Group_Control_Image_Size::get_type(),
			[
				'name' 			=> 'image', // Usage: `{name}_size` and `{name}_custom_dimension`, in this case `image_size` and `image_custom_dimension`.
				'default' 		=> 'full',
				'separator' 	=> 'none'
			]
		);

		$this->add_responsive_control(
			'logo_width',
			[
				'label' => esc_html__( 'Custom Width', 'codevz-plus' ),
				'type' => Controls_Manager::SLIDER,
				'default' => [
					'unit' => 'px',
				],
				'size_units' => [ 'px', '%', 'vw' ],
				'range' => [
					'%' => [
						'min' => 1,
						'max' => 100,
					],
					'px' => [
						'min' => 50,
						'max' => 500,
					],
					'vw' => [
						'min' => 1,
						'max' => 100,
					],
				],
				'selectors' => [
					'{{WRAPPER}} .cz_logo img' => 'width: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->add_control(
			'logo_position',
			[
				'label' => esc_html__( 'Position', 'codevz-plus' ),
				'type' => Controls_Manager::SELECT,
				'options' => [
					'' 						=> esc_html__( '~ Default ~', 'codevz-plus' ),
					'cz_helm_pos_left' 		=> esc_html__( 'Left', 'codevz-plus' ),
					'cz_helm_pos_center' 	=> esc_html__( 'Center', 'codevz-plus' ),
					'cz_helm_pos_right' 	=> esc_html__( 'Right', 'codevz-plus' ),
				]
			]
		);

		$this->add_control(
			'link',
			[
				'label' => esc_html__( 'Custom link', 'codevz-plus' ),
				'type' => Controls_Manager::URL,
				'default' 	=> [ 'url' => '#' ]
			]
		);

		$this->end_controls_section();

		// Parallax settings.
		Xtra_Elementor::parallax_settings( $this );

		$this->start_controls_section(
			'section_style_logo',
			[
				'label' => esc_html__( 'Styling', 'codevz-plus' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_responsive_control(
			'sk_logo',
			[
				'label' 	=> esc_html__( 'Logo', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'background', 'border', 'box-shadow' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_logo img' ),
			]
		);

	}

	public function render() {

		$settings = $this->get_settings_for_display();

		$image = Group_Control_Image_Size::get_attachment_image_html( $settings );

		if ( ! empty( $settings['link']['url'] ) ) {

			$this->add_link_attributes( 'link', $settings['link'] );

			$logo = '<a '. $this->get_render_attribute_string( 'link' ) . '>' . wp_kses_post( (string) $image ) . '</a>';

		} else {

			$logo = '<a href="' . esc_url( get_site_url() ) . '">' . wp_kses_post( (string) $image ) . '</a>';

		}

		// Widget classes.
		$classes = array();
		$classes[] = 'cz_logo clr';
		$classes[] = $settings[ 'logo_position' ];

		// Parallax.
		Xtra_Elementor::parallax( $settings );

		echo '<div' . wp_kses_post( (string) Codevz_Plus::classes( [], $classes ) ) . '>';

		echo wp_kses_post( (string) $logo );

		echo '</div>';

		// Close parallax.
		Xtra_Elementor::parallax( $settings, true );

	}

}