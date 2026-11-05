<?php if ( ! defined( 'ABSPATH' ) ) {exit;} // Exit if accessed directly.

use Codevz_Plus as Codevz_Plus;
use Elementor\Utils;
use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Image_Size;

class Xtra_Elementor_Widget_before_after extends Widget_Base {

	protected $id = 'cz_before_after';

	public function get_name() {
		return $this->id;
	}

	public function get_title() {
		return esc_html__( 'Before After', 'codevz-plus' );
	}

	public function get_icon() {
		return 'xtra-before-after';
	}

	public function get_categories() {
		return [ 'xtra' ];
	}

	public function get_keywords() {

		return [

			esc_html__( 'XTRA', 'codevz-plus' ),
			esc_html__( 'Before', 'codevz-plus' ),
			esc_html__( 'After', 'codevz-plus' ),
			esc_html__( 'Image', 'codevz-plus' ),
			esc_html__( 'Comparision', 'codevz-plus' ),

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
			'section_ba',
			[
				'label' => esc_html__( 'Settings', 'codevz-plus' ),
			]
		);

		$this->add_control(
			'image_2',
			[
				'label' => esc_html__( 'Before image', 'codevz-plus' ),
				'type' => Controls_Manager::MEDIA,
				'default' => [
					'url' => Codevz_Plus::$url . 'assets/img/p.svg',
				],
			]
		);

		$this->add_control(
			'title_2',
			[
				'label' => esc_html__('Before title', 'codevz-plus' ),
				'type' => Controls_Manager::TEXT,
				'default' => esc_html__('Before', 'codevz-plus' ),
				'placeholder' => esc_html__('Before', 'codevz-plus' ),
			]
		);

		$this->add_control(
			'image_1',
			[
				'label' => esc_html__( 'After image', 'codevz-plus' ),
				'type' => Controls_Manager::MEDIA,
				'default' => [
					'url' => Codevz_Plus::$url . 'assets/img/p.svg',
				],
			]
		);

		$this->add_control(
			'title_1',
			[
				'label' => esc_html__('After title', 'codevz-plus' ),
				'type' => Controls_Manager::TEXT,
				'default' => esc_html__('After', 'codevz-plus' ),
				'placeholder' => esc_html__('After', 'codevz-plus' ),
			]
		);

		$this->add_group_control(
			Group_Control_Image_Size::get_type(),
			[
				'name' => 'image',
				'default' => 'full'
			]
		);

		$this->end_controls_section();

		// Inverted radius controls.
		Xtra_Elementor::inverted_radius_controls( $this );

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
			'sk_overall',
			[
				'label' 	=> esc_html__( 'Container', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'background', 'border', 'box-shadow' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_image_comparison_slider' ),
			]
		);

		$this->add_responsive_control(
			'sk_title',
			[
				'label' 	=> esc_html__( 'Title', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'color', 'font-family', 'font-size', 'background', 'border' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_image_comparison_slider .cz_image_label' ),
			]
		);

		$this->add_responsive_control(
			'sk_handle',
			[
				'label' 	=> esc_html__( 'Handle', 'codevz-plus' ),
				'type' 		=> $free ? 'stylekit_pro' : 'stylekit',
				'settings' 	=> [ 'color', 'text-align', 'font-size', 'background', 'border' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_image_comparison_slider .cz_handle' ),
			]
		);

		$this->add_responsive_control(
			'svg_bg',
			[
				'label' 	=> esc_html__( 'Background layer', 'codevz-plus' ),
				'type' 		=> $free ? 'stylekit_pro' : 'stylekit',
				'settings' 	=> [ 'color', 'text-align', 'font-size', 'background', 'border' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_svg_bg:before' ),
			]
		);

		$this->end_controls_section();
	}

	public function render() {

		// Settings.
		$settings = $this->get_settings_for_display();

		if ( empty( $settings['image_2']['url'] ) ) {
			return;
		}

		if ( empty( $settings['image_1']['url'] ) ) {
			return;
		}
		// Images
		$img1 = $settings['image_1'];
		$img2 = $settings['image_2'];

		// Classes
		$classes = array();
		$classes[] = 'cz_image_comparison_slider';
		$classes[] = empty( $settings['svg_bg'] ) ? '' : 'cz_svg_bg';
		$classes[] = empty( $settings['ir_position'] ) ? '' : 'codevz-plus-inverted-radius-' . esc_attr( $settings['ir_position'] );

		Xtra_Elementor::parallax( $settings );
		?>
		<div<?php echo wp_kses_post( (string) Codevz_Plus::classes( [], $classes ) ); ?>>
			<figure class="cz_image_container is_visible">
				<?php echo do_shortcode( Group_Control_Image_Size::get_attachment_image_html( $settings, 'image', 'image_1' ) ); ?>
				<span class="cz_image_label" data-type="original"><?php echo esc_html( $settings['title_1'] ); ?></span>
				<div class="cz_resize_img"><?php echo do_shortcode( Group_Control_Image_Size::get_attachment_image_html( $settings, 'image', 'image_2' ) ); ?>
					<span class="cz_image_label" data-type="modified"><?php echo esc_html( $settings['title_2'] ); ?></span>
				</div>
				<span class="cz_handle"></span>
			</figure>
		</div>
		<?php

		Xtra_Elementor::parallax( $settings, true );
	}

	public function content_template() {
		?>
		<#
		if ( settings.image_2.url ) {
			var image_2 = {
				id: settings.image_2.id,
				url: settings.image_2.url,
				size: settings.image_size,
				dimension: settings.image_custom_dimension,
				model: view.getEditModel()
			};

			var image_url_2 = elementor.imagesManager.getImageUrl( image_2 );

			if ( ! image_url_2 ) {
				return;
			}
		}

		if ( settings.image_1.url ) {
			var image_1 = {
				id: settings.image_1.id,
				url: settings.image_1.url,
				size: settings.image_size,
				dimension: settings.image_custom_dimension,
				model: view.getEditModel()
			};

			var image_url_1 = elementor.imagesManager.getImageUrl( image_1 );

			if ( ! image_url_1 ) {
				return;
			}
		}

		var parallaxOpen = xtraElementorParallax( settings ),
			parallaxClose = xtraElementorParallax( settings, true ),

			svg_bg = settings.svg_bg ? 'cz_svg_bg' : '',
			svg_bg = svg_bg + ( settings.ir_position ? ' codevz-plus-inverted-radius-' + settings.ir_position : '' );
		#>

		{{{ parallaxOpen }}}
		<div class="cz_image_comparison_slider {{{ svg_bg }}}">
		<figure class="cz_image_container is_visible"> <img src="{{ image_url_1 }}"/> <span class="cz_image_label" data-type="original">{{{settings.title_1}}}</span>
		<div class="cz_resize_img"> <img src="{{ image_url_2 }}"/><span class="cz_image_label" data-type="modified">{{{settings.title_2}}}</span>
		</div>	<span class="cz_handle"></span>
		</figure>
		</div>

		{{{ parallaxClose }}}
		<?php
	}
}