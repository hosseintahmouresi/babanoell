<?php if ( ! defined( 'ABSPATH' ) ) {exit;} // Exit if accessed directly.

use Codevz_Plus as Codevz_Plus;
use Elementor\Utils;
use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Image_Size;

class Xtra_Elementor_Widget_banner extends Widget_Base { 

	protected $id = 'cz_banner';

	public function get_name() {
		return $this->id;
	}

	public function get_title() {
		return esc_html__( 'Banner', 'codevz-plus' );;
	}

	public function get_icon() {
		return 'xtra-banner';
	}

	public function get_categories() {
		return [ 'xtra' ];
	}

	public function get_keywords() {

		return [
			esc_html__( 'XTRA', 'codevz-plus' ),
			esc_html__( 'Banner', 'codevz-plus' ),
			esc_html__( 'Image', 'codevz-plus' ),
			esc_html__( 'Text', 'codevz-plus' ),
			esc_html__( 'Advertisement', 'codevz-plus' )
		];

	}

	public function get_style_depends() {

		$array = [ $this->id, 'codevz-tilt', 'cz_parallax' ];

		if ( Codevz_Plus::$is_rtl ) {
			$array[] = $this->id . '_rtl';
		}

		return $array;

	}

	public function get_script_depends() {
		return [ $this->id, 'cz_parallax', 'codevz-tilt' ];
	}

	public function register_controls() {

		$free = Codevz_Plus::is_free();

		$this->start_controls_section(
			'section_settings',
			[
				'label' => esc_html__( 'Settings', 'codevz-plus' ),
				'tab' => Controls_Manager::TAB_CONTENT,
			]
		);

		$this->add_control(
			'style',
			[
				'label' => esc_html__( 'Style', 'codevz-plus' ),
				'type' => Controls_Manager::SELECT,
				'default' => 'style1',
				'options' => [
					'style1' => esc_html__( 'Style', 'codevz-plus' ) . ' 1',
					'style2' => esc_html__( 'Style', 'codevz-plus' ) . ' 2',
					'style3' => esc_html__( 'Style', 'codevz-plus' ) . ' 3',
					'style4' => esc_html__( 'Style', 'codevz-plus' ) . ' 4',
					'style5' => esc_html__( 'Style', 'codevz-plus' ) . ' 5',
					'style6' => esc_html__( 'Style', 'codevz-plus' ) . ' 6' . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ),
					'style7' => esc_html__( 'Style', 'codevz-plus' ) . ' 7' . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ),
					'style8' => esc_html__( 'Style', 'codevz-plus' ) . ' 8' . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ),
					'style9' => esc_html__( 'Style', 'codevz-plus' ) . ' 9' . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ),
					'style10' => esc_html__( 'Style', 'codevz-plus' ) . ' 10' . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ),
					'style11' => esc_html__( 'Style', 'codevz-plus' ) . ' 11' . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ),
					'style12' => esc_html__( 'Style', 'codevz-plus' ) . ' 12' . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ),
					'style13' => esc_html__( 'Style', 'codevz-plus' ) . ' 13' . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ),
					'style14' => esc_html__( 'Style', 'codevz-plus' ) . ' 14' . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ),
					'style15' => esc_html__( 'Style', 'codevz-plus' ) . ' 15' . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ),
					'style16' => esc_html__( 'Style', 'codevz-plus' ) . ' 16' . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ),
					'style17' => esc_html__( 'Style', 'codevz-plus' ) . ' 17' . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ),
					'style18' => esc_html__( 'Style', 'codevz-plus' ) . ' 18' . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ),
					'style19' => esc_html__( 'Style', 'codevz-plus' ) . ' 19' . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ),
					'style20' => esc_html__( 'Style', 'codevz-plus' ) . ' 20' . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ),
					'style21' => esc_html__( 'Style', 'codevz-plus' ) . ' 21' . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ),
					'style22' => esc_html__( 'Style', 'codevz-plus' ) . ' 22' . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' ),
				],
			]
		);

		$this->add_control(
			'title',
			[
				'label' 	=> esc_html__('Title', 'codevz-plus' ),
				'type' 		=> Controls_Manager::TEXT,
				'default' 	=> esc_html__( 'Banner title', 'codevz-plus' ),
			]
		);

		$this->add_control(
			'image',
			[
				'label' => esc_html__( 'Image', 'codevz-plus' ),
				'type' => Controls_Manager::MEDIA,
				'default' => [
					'url' => Codevz_Plus::$url . 'assets/img/p.svg',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Image_Size::get_type(),
			[
				'name' => 'image', // Usage: `{name}_size` and `{name}_custom_dimension`, in this case `image_size` and `image_custom_dimension`.
				'default' => 'large',
				'separator' => 'none',
			]
		);

		$this->add_control(
			'image_opacity',
			[
				'label' => esc_html__( 'Image opacity', 'codevz-plus' ),
				'type' => $free ? 'codevz_pro' : Controls_Manager::SELECT,
				'options' => [
					''      => esc_html__( '~ Select ~', 'codevz-plus' ),
					'0'     => '0',
					'0.1'   => '0.1',
					'0.2'   => '0.2',
					'0.3'   => '0.3',
					'0.4'   => '0.4',
					'0.5'   => '0.5',
					'0.6'   => '0.6',
					'0.7'   => '0.7',
					'0.8'   => '0.8',
					'0.9'   => '0.9',
					'1'     => '1'
				],
				'selectors' => [
					'{{WRAPPER}} .cz_banner img' => 'opacity: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'image_hover_opacity',
			[
				'label' => esc_html__( 'Image hover opacity', 'codevz-plus' ),
				'type' => $free ? 'codevz_pro' : Controls_Manager::SELECT,
				'options' => [
					''      => esc_html__( '~ Select ~', 'codevz-plus' ),
					'0'     => '0',
					'0.1'   => '0.1',
					'0.2'   => '0.2',
					'0.3'   => '0.3',
					'0.4'   => '0.4',
					'0.5'   => '0.5',
					'0.6'   => '0.6',
					'0.7'   => '0.7',
					'0.8'   => '0.8',
					'0.9'   => '0.9',
					'1'     => '1'
				],
				'selectors' => [
					'{{WRAPPER}} .cz_banner:hover img' => 'opacity: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'content',
			[
				'label' 	=> esc_html__('Caption', 'codevz-plus' ),
				'type' 		=> Controls_Manager::WYSIWYG,
				'default' 	=> esc_html__( 'Banner description', 'codevz-plus' ),
			]
		);

		$this->add_control(
			'link',
			[
				'label' 	=> esc_html__( 'Link', 'codevz-plus' ),
				'type' 		=> Controls_Manager::URL,
				'default' 	=> [ 'url' => '#' ]
			]
		);

		$this->add_control(
			'text_center',
			[
				'label' => esc_html__( 'Center on mobile?', 'codevz-plus' ),
				'type' => Controls_Manager::SELECT,
				'default' => '',
				'options' => [
					'' => esc_html__( 'No', 'codevz-plus' ),
					'1' => esc_html__( 'Yes', 'codevz-plus' ),
				],
			]
		);

		$this->end_controls_section();

		// Inverted radius controls.
		Xtra_Elementor::inverted_radius_controls( $this );

		// Tilt controls.
		Xtra_Elementor::tilt_controls( $this );

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
			'sk_box',
			[
				'label' 	=> esc_html__( 'Container', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'background', 'border' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_banner figure' ),
			]
		);

		$this->add_responsive_control(
			'sk_title',
			[
				'label' 	=> esc_html__( 'Title', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'color', 'font-family', 'font-size' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_banner h4' ),
			]
		);

		$this->add_responsive_control(
			'sk_caption',
			[
				'label' 	=> esc_html__( 'Content', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'background', 'border' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_banner figcaption' ),
			]
		);

		$this->add_responsive_control(
			'svg_bg',
			[
				'label' 	=> esc_html__( 'Background layer', 'codevz-plus' ),
				'type' 		=> $free ? 'stylekit_pro' : 'stylekit',
				'settings' 	=> [ 'background', 'top', 'left', 'border', 'width', 'height' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_banner.cz_svg_bg:before' ),
			]
		);
		$this->end_controls_section();
	}

	public function render() {

		$settings = $this->get_settings_for_display();

		$this->add_link_attributes( 'link', $settings['link'] );

		$image =  $settings['image'];

		$content = $settings['content'] ? '<p class="cz_wpe_content">' . do_shortcode( Codevz_Plus::fix_extra_p( $settings['content'] ) ) . '</p>' : '';

		// Classes
		$classes = [];
		$classes[] = 'cz_banner clr';
		$classes[] = $settings['text_center'] ? 'cz_mobile_text_center' : '';
		$classes[] = empty( $settings['svg_bg'] ) ? '' : 'cz_svg_bg';
		$classes[] = empty( $settings['ir_position'] ) ? '' : 'codevz-plus-inverted-radius-' . esc_attr( $settings['ir_position'] );

		// Parallax.
		Xtra_Elementor::parallax( $settings );

		$title = $settings['title'] ? '<h4>' . $settings['title'] . '</h4>' : '';

		?>
		<div<?php echo wp_kses_post( (string) Codevz_Plus::classes( [], $classes ) ); ?>>
			<figure class="effect-<?php echo esc_attr( $settings['style'] ) . '"' . wp_kses_post( (string) Codevz_Plus::tilt( $settings ) ); ?>">
				<?php echo do_shortcode( Group_Control_Image_Size::get_attachment_image_html( $settings ) ); ?>
				<figcaption>
					<div>
						<?php echo wp_kses_post( (string) $title ); ?>
						<?php echo wp_kses_post( (string) $content ); ?>
					</div> 
					<a <?php echo wp_kses_post( (string) $this->get_render_attribute_string( 'link' ) ); ?>></a>
				</figcaption>
			</figure>
		</div>
		<?php

		// Close parallax.
		Xtra_Elementor::parallax( $settings, true );
	}

	public function content_template() {
		?>
		<#
		if ( settings.image.url ) {
			var image = {
				id: settings.image.id,
				url: settings.image.url,
				size: settings.image_size,
				dimension: settings.image_custom_dimension,
				model: view.getEditModel()
			};

			var image_url = elementor.imagesManager.getImageUrl( image );

			if ( ! image_url ) {
				return;
			}
		}

		var classes = 'cz_banner clr', 
			classes = classes + ( settings.svg_bg ? ' cz_svg_bg' : '' ),
			classes = classes + ( settings.text_center ? ' cz_mobile_text_center' : '' ),
			classes = classes + ( settings.ir_position ? ' codevz-plus-inverted-radius-' + settings.ir_position : '' ),

			parallaxOpen = xtraElementorParallax( settings ),
			parallaxClose = xtraElementorParallax( settings, true ),

			tilt = xtraElementorTilt( settings );

		#>

		{{{ parallaxOpen }}}

		<div class="{{{ classes }}}">
			<figure class="effect-{{{ settings.style }}}"{{{ tilt }}}>
				<img src="{{ image_url }}"/>
				<figcaption>
					<div>
						<h4>{{{ settings.title }}}</h4>
						<p class="cz_wpe_content">{{{ settings.content }}}</p>
					</div>
					<a href="{{{ settings.link.url }}}"> </a>
				</figcaption>
			</figure>
		</div>

		{{{ parallaxClose }}}
		<?php
	}
}