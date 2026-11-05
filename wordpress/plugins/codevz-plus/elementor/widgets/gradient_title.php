<?php if ( ! defined( 'ABSPATH' ) ) {exit;} // Exit if accessed directly.

use Elementor\Widget_Base;
use Elementor\Controls_Manager;

class Xtra_Elementor_Widget_gradient_title extends Widget_Base { 

	protected $id = 'cz_gradient_title';

	public function get_name() {
		return $this->id;
	}

	public function get_title() {
		return esc_html__( 'Gradient Title', 'codevz-plus' );
	}

	public function get_icon() {
		return 'xtra-gradient-title';
	}

	public function get_categories() {
		return [ 'xtra' ];
	}

	public function get_keywords() {

		return [

			esc_html__( 'XTRA', 'codevz-plus' ),
			esc_html__( 'Title', 'codevz-plus' ),
			esc_html__( 'Gradient', 'codevz-plus' ),
			esc_html__( 'Text', 'codevz-plus' ),

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
			'content',
			[
				'label' => esc_html__('Text', 'codevz-plus' ),
				'type' => Controls_Manager::WYSIWYG,
				'default' => '<h1 style="text-align:center"><strong>Example Gradient Title</strong></h1>',
			]
		);

		$this->add_control(
			'animation',
			[
				'label' 	=> esc_html__( 'Animation', 'codevz-plus' ),
				'type' 		=> $free ? 'codevz_pro' : Controls_Manager::SWITCHER
			]
		);

		$this->add_control(
			'text_center',
			[
				'label' 	=> esc_html__( 'Center on mobile?', 'codevz-plus' ),
				'type' 		=> $free ? 'codevz_pro' : Controls_Manager::SWITCHER
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
			'sk_css',
			[
				'label' 	=> esc_html__( 'Background', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'background' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_gradient_title' ),
			]
		);

		$this->add_responsive_control(
			'sk_h1',
			[
				'label' 	=> esc_html__( 'H1', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'font-size', 'font-weight', 'line-height' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_gradient_title h1' ),
			]
		);

		$this->add_responsive_control(
			'sk_h2',
			[
				'label' 	=> esc_html__( 'H2', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'font-size', 'font-weight', 'line-height' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_gradient_title h2' ),
			]
		);

		$this->add_responsive_control(
			'sk_h3',
			[
				'label' 	=> esc_html__( 'H3', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'font-size', 'font-weight', 'line-height' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_gradient_title h3' ),
			]
		);

		$this->end_controls_section();
	}

	public function render() {

		$settings = $this->get_settings_for_display();

		// Classes
		$classes = array();
		$classes[] = 'cz_gradient_title';
		$classes[] = $settings['text_center'] ? 'cz_mobile_text_center' : '';
		$classes[] = $settings['animation'] ? 'cz_gradient_title_animation' : '';
		if ( Codevz_Plus::contains( $settings['content'], array( ': center;', ':center;' ) ) ) {
			$classes[] = 'cz_gradient_title_center';
		}

		Xtra_Elementor::parallax( $settings );

		?>
		<div<?php echo wp_kses_post( (string) Codevz_Plus::classes( [], $classes ) );  ?>>
			<div class="cz_wpe_content"><?php echo do_shortcode( Codevz_Plus::fix_extra_p( $settings['content'] ) ); ?></div>
		</div>
		<?php

		Xtra_Elementor::parallax( $settings, true );
	}

	protected function content_template() {
	?>
	<#
		var content = settings.content,
			classes = 'cz_gradient_title',
			classes = classes + ( settings.text_center ? ' cz_mobile_text_center' : '' ),
			classes = classes + ( settings.animation ? ' cz_gradient_title_animation' : '' ),
			classes = classes + ( content.indexOf( 'center' ) >= 0 ? ' cz_gradient_title_center' : '' ),
			parallaxOpen = xtraElementorParallax( settings ),
			parallaxClose = xtraElementorParallax( settings, true );
	#>

	{{{ parallaxOpen }}}
	<div class="{{{classes}}}">
		<div class="cz_wpe_content">{{{content}}}</div>
	</div>

	{{{ parallaxClose }}}
	<?php
	}
}
	