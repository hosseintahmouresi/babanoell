<?php if ( ! defined( 'ABSPATH' ) ) {exit;} // Exit if accessed directly.

use Elementor\Widget_Base;
use Elementor\Icons_Manager;
use Elementor\Controls_Manager;

class Xtra_Elementor_Widget_show_more_less extends Widget_Base {
	
	protected $id = 'cz_show_more_less';

	public function get_name() {
		return $this->id;
	}

	public function get_title() {
		return esc_html__( 'Show More,Less', 'codevz-plus' );
	}

	public function get_icon() {
		return 'xtra-show-more-less';
	}

	public function get_categories() {
		return [ 'xtra' ];
	}

	public function get_keywords() {

		return [

			esc_html__( 'XTRA', 'codevz-plus' ),
			esc_html__( 'More', 'codevz-plus' ),
			esc_html__( 'Less', 'codevz-plus' ),
			esc_html__( 'Show', 'codevz-plus' ),
			esc_html__( 'Collapse', 'codevz-plus' ),
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
			'settings',
			[
				'label' 	=> esc_html__( 'Settings', 'codevz-plus' ),
				'tab' 		=> Controls_Manager::TAB_CONTENT,
			]
		);

		$this->add_control(
			'xtra_elementor_template',
			[
				'label' 	=> esc_html__( 'Select template', 'codevz-plus' ),
				'type' 		=> Controls_Manager::SELECT,
				'options' 	=> Xtra_Elementor::get_templates()
			]
		);

		$this->add_responsive_control(
			'height',
			[
				'label' => esc_html__( 'Height', 'codevz-plus' ),
				'type' => Controls_Manager::SLIDER,
				'default' => [
					'unit' => 'px',
					'size' => 100,
				],
				'size_units' => [ 'px' ],
				'range' => [
					'px' => [
						'min' => 50,
						'max' => 400,
						'step' => 10,
					],
				],
				'selectors' => [
					'{{WRAPPER}} .cz_sml_inner' => 'height: {{SIZE}}{{UNIT}}',
				],
			]
		);
		$this->add_control(
			'center',
			[
				'label' => esc_html__( 'Center?', 'codevz-plus' ),
				'type' => $free ? 'codevz_pro' : Controls_Manager::SWITCHER 
			]
		);

		$this->add_control(
			'more',
			[
				'label' 	=> esc_html__('Show more', 'codevz-plus' ),
				'type' 		=> Controls_Manager::TEXT,
				'default' 	=> esc_html__('Show more', 'codevz-plus' ),
				'placeholder' => esc_html__('Show more', 'codevz-plus' ),
			]
		);

		$this->add_control(
			'icon_more',
			[
				'label' => esc_html__( 'Icon', 'codevz-plus' ),
				'type' => Controls_Manager::ICONS,
				'skin' => 'inline',
				'label_block' => false,
				'default' => [
					'value' => 'fas fa-angle-down',
					'library' => 'solid',
				],
			]
		);

		$this->add_control(
			'less',
			[
				'label' 	=> esc_html__('Show less', 'codevz-plus' ),
				'type' 		=> Controls_Manager::TEXT,
				'default' 	=> esc_html__('Show less', 'codevz-plus' ),
				'placeholder' => esc_html__('Show less', 'codevz-plus' ),
			]
		);

		$this->add_control(
			'icon_less',
			[
				'label' => esc_html__( 'Icon', 'codevz-plus' ),
				'type' => Controls_Manager::ICONS,
				'skin' => 'inline',
				'label_block' => false,
				'default' => [
					'value' => 'fas fa-angle-up',
					'library' => 'solid',
				],
			]
		);

		$this->end_controls_section();

		// Parallax settings.
		Xtra_Elementor::parallax_settings( $this );

		$this->start_controls_section(
			'section_style',
			[
				'label' => esc_html( 'Style', 'codevz-plus' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_control(
			'gradient',
			[
				'label' => esc_html__( 'Overlay color', 'codevz-plus' ),
				'type' => Controls_Manager::COLOR,
				'default' => '#fff',
				'selectors' => [
					'{{WRAPPER}} .cz_sml .cz_sml_overlay' => 'background:linear-gradient(to bottom, rgba(255,255,255,0), {{VALUE}})',
				]
			]
		);

		$this->add_responsive_control(
			'sk_button',
			[
				'label' 	=> esc_html__( 'Button styling', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'display', 'color', 'text-align', 'font-family', 'font-size', 'font-weight', 'line-height', 'letter-spacing', 'background', 'border', 'box-shadow', 'text-shadow' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_sml > a' ),
			]
		);

		$this->end_controls_section();
	}

	public function render() {

		// Settings.
		$settings = $this->get_settings_for_display();

		// Icons
		ob_start();
		Icons_Manager::render_icon( $settings['icon_more'], [ 'class' => 'ml8' ] );
		$icon_more = ob_get_clean();

		ob_start();
		Icons_Manager::render_icon( $settings['icon_less'], [ 'class' => 'ml8' ] );
		$icon_less = ob_get_clean();

		Xtra_Elementor::parallax( $settings );

		$center = empty( $settings['center'] ) ? '' : 'cz_sml_center';
		$height = empty( $settings['height']['size'] ) ? '100px' : $settings['height']['size'] . 'px';

	?>

		<div<?php echo wp_kses_post( (string) Codevz_Plus::classes( [], array( 'cz_sml', esc_attr( $center ) ) ) ); ?>>

			<div class="cz_sml_inner" data-height="<?php echo esc_attr( $height ); ?>">

				<div class="cz_wpe_content">
					<?php echo do_shortcode( Codevz_Plus::get_page_as_element( esc_html( $settings[ 'xtra_elementor_template' ] ) ) ); ?>
				</div>

				<div class="cz_sml_overlay"></div>

			</div>

			<a id="cz_show_more_less_btn" href="#more_less">
				<span>
					<?php echo do_shortcode( $settings['more'] . $icon_more ); ?>
				</span>
				<span>
					<?php echo do_shortcode( $settings['less'] . $icon_less ); ?>
				</span>
			</a>

		</div>

	<?php

		Xtra_Elementor::parallax( $settings, true );

	}

}