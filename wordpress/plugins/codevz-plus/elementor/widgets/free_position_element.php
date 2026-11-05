<?php if ( ! defined( 'ABSPATH' ) ) {exit;} // Exit if accessed directly.

use Elementor\Widget_Base;
use Elementor\Controls_Manager;

class Xtra_Elementor_Widget_free_position_element extends Widget_Base {

	protected $id = 'cz_free_position_element';

	public function get_name() {
		return $this->id;
	}

	public function get_title() {
		return esc_html__( 'Free Position Element', 'codevz-plus' );
	}

	public function get_icon() {
		return 'xtra-free-position-element';
	}

	public function get_categories() {
		return [ 'xtra' ];
	}

	public function get_keywords() {

		return [

			esc_html__( 'XTRA', 'codevz-plus' ),
			esc_html__( 'Position', 'codevz-plus' ),
			esc_html__( 'Place', 'codevz-plus' ),
			esc_html__( 'Location', 'codevz-plus' ),
			esc_html__( 'Absolute', 'codevz-plus' ),

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

		$this->add_responsive_control(
			'css_top',
			[
				'label' => esc_html__( 'Top offset', 'codevz-plus' ),
				'type' => Controls_Manager::SLIDER,
				'default' => [
					'unit' => '%',
				],
				'size_units' => [ '%' ],
				'range' => [
					'%' => [
						'min' => -200,
						'max' => 200,
						'step' => 1,
					],
				],
				'selectors' => [
					'{{WRAPPER}}' => 'position:absolute;top: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->add_responsive_control(
			'css_left',
			[
				'label' => esc_html__( 'Left offset', 'codevz-plus' ),
				'type' => Controls_Manager::SLIDER,
				'default' => [
					'unit' => '%',
				],
				'size_units' => [ '%' ],
				'range' => [
					'%' => [
						'min' => -200,
						'max' => 200,
						'step' => 1,
					],
				],
				'selectors' => [
					'{{WRAPPER}}' => 'left: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->add_responsive_control(
			'css_width',
			[
				'label' => esc_html__( 'Custom Width', 'codevz-plus' ),
				'type' => Controls_Manager::SLIDER,
				'default' => [
					'unit' => 'px',
				],
				'size_units' => [ 'px' ],
				'range' => [
					'px' => [
						'min' => 0,
						'max' => 500,
						'step' => 10,
					],
				],
				'selectors' => [
					'{{WRAPPER}}' => 'width: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->add_control(
			'type', [
				'label' 	=> esc_html__( 'Content type', 'codevz-plus' ),
				'type' 		=> Controls_Manager::SELECT,
				'options' 	=> [
					'' 			=> esc_html__( 'Content', 'codevz-plus' ),
					'template' 	=> esc_html__( 'Saved template', 'codevz-plus' ),
				]
			]
		);

		$this->add_control(
			'content', [
				'label' 	=> esc_html__( 'Content', 'codevz-plus' ),
				'type' 		=> Controls_Manager::WYSIWYG,
				'default' 	=> 'Hello World ...',
				'placeholder' => 'Hello World ...',
				'condition' => [
					'type' 		=> ''
				],
			]
		);

		$this->add_control(
			'xtra_elementor_template',
			[
				'label' 	=> esc_html__( 'Select template', 'codevz-plus' ),
				'type' 		=> Controls_Manager::SELECT,
				'options' 	=> Xtra_Elementor::get_templates(),
				'condition' => [
					'type' => 'template'
				],
			]
		);

		$this->add_responsive_control(
			'css_transform',
			[
				'label' => esc_html__( 'Custom Rotate', 'codevz-plus' ),
				'type' => $free ? 'codevz_pro' : Controls_Manager::SLIDER,
				'default' => [
					'unit' => 'deg',
				],
				'size_units' => [ 'deg' ],
				'range' => [
					'deg' => [
						'min' => 0,
						'max' => 360,
						'step' => 1,
					],
				],
				'selectors' => [
					'{{WRAPPER}} > div' => 'transform: rotate( {{SIZE}}{{UNIT}} );',
				],
			]
		);

		$this->add_control(
			'css_z-index',
			[
				'label' => esc_html__( 'Layer Priority', 'codevz-plus' ),
				'type' => $free ? 'codevz_pro' : Controls_Manager::SELECT,
				'default' => '0',
				'options' => [
					'-2'  => '-2',
					'-1'  => '-1',
					'0'  => '0',
					'1'  => '1',
					'2'  => '2',
					'3'  => '3',
					'4'  => '4',
					'5'  => '5',
					'6'  => '6',
					'7'  => '7',
					'8'  => '8',
					'9'  => '9',
					'10'  => '10',
					'99'  => '99',
					'999'  => '999',
				],
				'selectors' => [
					'{{WRAPPER}}' => 'z-index: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'onhover',
			[
				'label' => esc_html__( 'Hover', 'codevz-plus' ),
				'type' => $free ? 'codevz_pro' : Controls_Manager::SELECT,
				'options' => [
					''  => esc_html__( 'Select', 'codevz-plus' ),
					'cz_hide_onhover'  => esc_html__( 'Hide on parent hover', 'codevz-plus' ),
					'cz_show_onhover'  => esc_html__( 'Show on parent hover FadeIn', 'codevz-plus' ),
					'cz_show_onhover cz_show_fadeup'  => esc_html__( 'Show on parent hover FadeUp', 'codevz-plus' ),
					'cz_show_onhover cz_show_fadedown'  => esc_html__( 'Show on parent hover FadeDown', 'codevz-plus' ),
					'cz_show_onhover cz_show_fadeleft'  => esc_html__( 'Show on parent hover FadeLeft', 'codevz-plus' ),
					'cz_show_onhover cz_show_faderight'  => esc_html__( 'Show on parent hover FadeRight', 'codevz-plus' ),
					'cz_show_onhover cz_show_zoomin'  => esc_html__( 'Show on parent hover ZoomIn', 'codevz-plus' ),
					'cz_show_onhover cz_show_zoomout'  => esc_html__( 'Show on parent hover ZoomOut', 'codevz-plus' ),
				],
			]
		);

		$this->add_control(
			'animation',
			[
				'label' => esc_html__( 'Loop animation', 'codevz-plus' ),
				'type' => $free ? 'codevz_pro' : Controls_Manager::SELECT,
				'options' => [
					''  => esc_html__( 'Select', 'codevz-plus' ),
					'cz_infinite_anim_1'  => esc_html__( 'Animation', 'codevz-plus' ) . ' 1',
					'cz_infinite_anim_2'  => esc_html__( 'Animation', 'codevz-plus' ) . ' 2',
					'cz_infinite_anim_3'  => esc_html__( 'Animation', 'codevz-plus' ) . ' 3',
					'cz_infinite_anim_4'  => esc_html__( 'Animation', 'codevz-plus' ) . ' 4',
					'cz_infinite_anim_5'  => esc_html__( 'Animation', 'codevz-plus' ) . ' 5',
				],
			]
		);

		$this->end_controls_section();

		// Parallax settings.
		Xtra_Elementor::parallax_settings( $this );
	}

	public function render() {

		// Settings.
		$atts = $this->get_settings_for_display();

		// Classes
		$classes = array();
		$classes[] = 'cz_free_position_element';
		$classes[] = $atts['animation'];
		$classes[] = $atts['onhover'];

		if ( $atts[ 'type' ] === 'template' ) {
			$content = Codevz_Plus::get_page_as_element( esc_html( $atts[ 'xtra_elementor_template' ] ) );
		} else {
			$content = $atts[ 'content' ];
		}

		echo '<div' . wp_kses_post( (string) Codevz_Plus::classes( [], $classes ) ) . '><div>' . do_shortcode( $content ) . '</div></div>';

	}

}