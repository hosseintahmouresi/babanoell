<?php if ( ! defined( 'ABSPATH' ) ) {exit;} // Exit if accessed directly.

use Elementor\Widget_Base;
use Elementor\Controls_Manager;

class Xtra_Elementor_Widget_text_marquee extends Widget_Base {

	protected $id = 'cz_text_marquee';

	public function get_name() {
		return $this->id;
	}

	public function get_title() {
		return esc_html__( 'Text Marquee', 'codevz-plus' );
	}
	
	public function get_icon() {
		return 'xtra-text-marque';
	}

	public function get_categories() {
		return [ 'xtra' ];
	}

	public function get_keywords() {

		return [

			esc_html__( 'XTRA', 'codevz-plus' ),
			esc_html__( 'Scroll', 'codevz-plus' ),
			esc_html__( 'Marquee', 'codevz-plus' ),
			esc_html__( 'Text', 'codevz-plus' ),
			esc_html__( 'Slider', 'codevz-plus' ),
			esc_html__( 'Rotate', 'codevz-plus' ),

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
				'label' => esc_html__( "Settings", 'codevz-plus' ),
				'tab' => Controls_Manager::TAB_CONTENT,
			]
		);

		$this->add_control(
			'content',
			[
				'label' => esc_html__( "Text", 'codevz-plus' ),
				'type' => Controls_Manager::WYSIWYG,
				'default' => esc_html__( 'I am marquee text, you can edit me and insert your text ...', 'codevz-plus' ),
				'placeholder' => esc_html__( 'I am marquee text, you can edit me and insert your text ...', 'codevz-plus' ),
			]
		);

		$this->add_control(
			'duration',
			[
				'label' => esc_html__( 'Duration', 'codevz-plus' ),
				'type' => $free ? 'codevz_pro' : Controls_Manager::NUMBER,
				'min' => 1,
				'max' => 20,
				'step' => 1,
				'default' => 8,
			]
		);

		$this->add_control(
			'direction',
			[
				'label' => esc_html__( 'Direction', 'codevz-plus' ),
				'type' => $free ? 'codevz_pro' : Controls_Manager::SELECT,
				'default' => 'left',
				'options' => [
					'left'  => esc_html__( 'Left', 'codevz-plus' ),
					'right' => esc_html__( 'Right', 'codevz-plus' ),
				],
			]
		);

		$this->add_control(
			'duplicate',
			[
				'label' => esc_html__( 'Duplicate', 'codevz-plus' ),
				'type' => $free ? 'codevz_pro' : Controls_Manager::SELECT,
				'default' => 'false',
				'options' => [
					'false'  => esc_html__( 'No', 'codevz-plus' ),
					'true' => esc_html__( 'Yes', 'codevz-plus' ),
				],
			]
		);

		$this->add_control(
			'stop_on_hover',
			[
				'label' => esc_html__( 'Stop on hover?', 'codevz-plus' ),
				'type' 	=> $free ? 'codevz_pro' : Controls_Manager::SWITCHER
			]
		);

		$this->add_control(
			'gap',
			[
				'label' => esc_html__( 'Gap', 'codevz-plus' ),
				'type' => $free ? 'codevz_pro' : Controls_Manager::NUMBER,
				'min' => 50,
				'max' => 1200,
				'step' => 5,
				'default' => 100
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
			'sk_marquee',
			[
				'label' 	=> esc_html__( 'Styling', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'color' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_text_marquee' ),
			]
		);

		$this->add_control(
			'overlay_gradient',
			[
				'label' 	=> esc_html__( 'Overlay gradient?', 'codevz-plus' ),
				'type' 		=> $free ? 'codevz_pro' : Controls_Manager::SWITCHER
			]
		);

		$this->add_control(
			'overlay_gradient_color',
			[
				'label' 	=> esc_html__( 'Gradient color', 'codevz-plus' ),
				'type' 		=> Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .codevz-plus-overlay-gradient:after' => '--overlay-gr: {{VALUE}};',
				],
				'condition' => [
					'overlay_gradient!' 	=> ''
				],
			]
		);

		$this->end_controls_section();
	}

	public function render() {

		// Settings.
		$settings = $this->get_settings_for_display();

		// Classes
		$classes = array();
		$classes[] = 'cz_text_marquee';
		$classes[] = $settings['stop_on_hover'] ? 'cz_text_marquee_soh' : '';
		$classes[] = empty( $settings['overlay_gradient'] ) ? '' : 'codevz-plus-overlay-gradient';

		// Data
		$data = ' data-duration="' . $settings['duration'] . '000"';
		$data .= ' data-direction="' . $settings['direction'] . '"';
		$data .= ' data-duplicated="' . $settings['duplicate'] . '"';
		$data .= ' data-gap="' . $settings['gap'] . '"';  

		Xtra_Elementor::parallax( $settings );

		?>
		<div<?php echo wp_kses_post( (string) Codevz_Plus::classes( [], $classes ) ); ?> <?php echo wp_kses_post( (string) $data ); ?>>
			<?php echo do_shortcode( wp_kses_post( (string) $settings['content'] ) ); ?>
		</div>

		<?php

		Xtra_Elementor::parallax( $settings, true );

		// Fix live preivew.
		Xtra_Elementor::render_js( 'text_marquee' );

	}

	public function content_template() {
		?>
		<#

		var classes = 'cz_text_marquee', 
			classes = settings.stop_on_hover ? classes + ' cz_text_marquee_soh' : classes,
			classes = settings.overlay_gradient ? classes + ' codevz-plus-overlay-gradient' : classes,

			data = ' data-duration="' + settings.duration + '000"',
			data = data + ' data-direction="' + settings.direction + '"',
			data = data + ' data-duplicated="' + settings.duplicate + '"',
			data = data + ' data-gap="' + settings.gap + '"',

			parallaxOpen = xtraElementorParallax( settings ),
			parallaxClose = xtraElementorParallax( settings, true );

		#>

		{{{ parallaxOpen }}}

		<div class="{{{classes}}}" {{{data}}}>{{{settings.content}}}</div>

		{{{ parallaxClose }}}
		<?php
	}

}