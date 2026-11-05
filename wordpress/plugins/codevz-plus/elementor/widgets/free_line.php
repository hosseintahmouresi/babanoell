<?php if ( ! defined( 'ABSPATH' ) ) {exit;} // Exit if accessed directly.

use Elementor\Widget_Base;
use Elementor\Controls_Manager;

class Xtra_Elementor_Widget_free_line extends Widget_Base {

	protected $id = 'cz_free_line';

	public function get_name() {
		return $this->id;
	}

	public function get_title() {
		return esc_html__( 'Free Line', 'codevz-plus' );
	}
	
	public function get_icon() {
		return 'xtra-free-line';
	}

	public function get_categories() {
		return [ 'xtra' ];
	}

	public function get_keywords() {

		return [

			esc_html__( 'XTRA', 'codevz-plus' ),
			esc_html__( 'Line', 'codevz-plus' ),
			esc_html__( 'Separator', 'codevz-plus' ),

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
				'tab' => Controls_Manager::TAB_CONTENT
			]
		);

		$this->add_control(
			'title',
			[
				'label' => esc_html__( 'Title', 'codevz-plus' ),
				'type' => Controls_Manager::TEXT
			]
		);

		$this->add_control(
			'circles',
			[
				'label' => esc_html__( 'Circles type', 'codevz-plus' ),
				'type' => $free ? 'codevz_pro' : Controls_Manager::SELECT,
				'default' => '',
				'options' => [
					'' => esc_html__( 'Select', 'codevz-plus' ),
					'cz_line_before_circle' => esc_html__( 'Before', 'codevz-plus' ),
					'cz_line_after_circle' => esc_html__( 'After', 'codevz-plus' ),
					'cz_line_before_circle cz_line_after_circle' => esc_html__( 'Both Sides', 'codevz-plus' ),
				],
			]
		);

		$this->add_control(
			'position',
			[
				'label' => esc_html__( 'Position', 'codevz-plus' ),
				'help'  => esc_html__( 'Only works when line have custom width', 'codevz-plus' ),
				'type'  => Controls_Manager::SELECT,
				'default' => 'tal',
				'options' => [
					'tal' => esc_html__( 'Left', 'codevz-plus' ),
					'tac' => esc_html__( 'Center', 'codevz-plus' ),
					'tar' => esc_html__( 'Right', 'codevz-plus' ),
					'tal tac_in_mobile' => esc_html__( 'Left (Center in Small Devices)', 'codevz-plus' ),
					'tar tac_in_mobile' => esc_html__( 'Right (Center in Small Devices)', 'codevz-plus' ),
				],
			]
		);

		$this->end_controls_section();

		// Parallax settings.
		Xtra_Elementor::parallax_settings( $this );

		$this->start_controls_section(
			'section_style',
			[
				'label' => esc_html__( 'Style', 'codevz-plus' ),
				'tab' => Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_responsive_control(
			'sk_line',
			[
				'label' 	=> esc_html__( 'Line', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'width', 'height', 'transform', 'top', 'right', 'bottom', 'left', 'background' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_line' ),
			]
		);

		$this->add_responsive_control(
			'sk_title',
			[
				'label' 	=> esc_html__( 'Title', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'color', 'border', 'background' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_line_title' ),
			]
		);

		$this->add_responsive_control(
			'sk_circles',
			[
				'label' 	=> esc_html__( 'Circles', 'codevz-plus' ),
				'type' 		=> $free ? 'stylekit_pro' : 'stylekit',
				'settings' 	=> [ 'width', 'height', 'top', 'right', 'bottom', 'left', 'background', 'border' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_line:before, .cz_line:after' ),
			]
		);

		$this->end_controls_section();
	}

	public function render() {

		$settings = $this->get_settings_for_display();

		// Classes
		$classes = [];
		$classes[] = 'cz_line';
		$classes[] = $settings['circles'];
		$classes[] = $settings['position'];

		// Title.
		$title = empty( $settings['title'] ) ? '' : '<div class="cz_line_title">' . do_shortcode( $settings['title'] ) . '</div>';

		Xtra_Elementor::parallax( $settings );
		?>
		<div class="relative">
			<div <?php echo wp_kses_post( (string) Codevz_Plus::classes( [], $classes ) );  ?>><?php echo wp_kses_post( $title ); ?></div>
		</div>
		<div class="clr"></div>
		<?php

		Xtra_Elementor::parallax( $settings, true );
	}

	public function content_template() {
		?>
		<#
		var classes = 'cz_line', 
			classes = classes + ( settings.road ? ' ' + settings.road : '' ),
			classes = classes + ( settings.circles ? ' ' + settings.circles : '' ),
			classes = classes + ( settings.position ? ' ' + settings.position : '' ),
			title = settings.title ? '<div class="cz_line_title">' + settings.title + '</div>' : '';

			parallaxOpen = xtraElementorParallax( settings ),
			parallaxClose = xtraElementorParallax( settings, true );
		#>

		{{{ parallaxOpen }}}
		
		<div class="relative">
			<div class="{{{classes}}}">{{{title}}}</div>
		</div>
		<div class="clr"></div>

		{{{ parallaxClose }}}
		<?php
	}
}