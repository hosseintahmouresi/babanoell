<?php if ( ! defined( 'ABSPATH' ) ) {exit;} // Exit if accessed directly.

use Codevz_Plus as Codevz_Plus;
use Elementor\Utils;
use Elementor\Widget_Base;
use Elementor\Icons_Manager;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Image_Size;

class Xtra_Elementor_Widget_image extends Widget_Base { 

	protected $id = 'cz_image';

	public function get_name() {
		return $this->id;
	}

	public function get_title() {
		return esc_html__( 'Image', 'codevz-plus' );
	}

	public function get_icon() {
		return 'xtra-image';
	}

	public function get_categories() {
		return [ 'xtra' ];
	}

	public function get_keywords() {

		return [
			esc_html__( 'XTRA', 'codevz-plus' ),
			esc_html__( 'Image', 'codevz-plus' ),
			esc_html__( 'Photo', 'codevz-plus' ),
			esc_html__( 'Shot', 'codevz-plus' )
		];

	}

	public function get_style_depends() {
		return [ $this->id, 'cz_parallax', 'codevz-tilt' ];
	}

	public function get_script_depends() {
		return [ $this->id, 'cz_parallax', 'codevz-tilt' ];
	}

	protected function register_controls() {

		$free = Codevz_Plus::is_free();

		$this->start_controls_section(
			'section_image',
			[
				'label' => esc_html__( 'Settings', 'codevz-plus' ),
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

		$this->add_control(
			'fx_hover',
			[
				'label' => esc_html__( 'Hover effect', 'codevz-plus' ),
				'type' => $free ? 'codevz_pro' : Controls_Manager::SELECT,
				'default' => 'cz_image_no_fx',
				'options' => [
					'cz_image_no_fx' => esc_html__( 'Select', 'codevz-plus' ),
					'cz_image_simple_fade' => esc_html__( 'Simple Fade', 'codevz-plus' ),
					'cz_image_flip_h' => esc_html__( 'Flip Horizontal', 'codevz-plus' ),
					'cz_image_flip_v' => esc_html__( 'Flip Vertical', 'codevz-plus' ),
					'cz_image_fade_to_top' => esc_html__( 'Fade To Top', 'codevz-plus' ),
					'cz_image_fade_to_bottom' => esc_html__( 'Fade To Bottom', 'codevz-plus' ),
					'cz_image_fade_to_left' => esc_html__( 'Fade To Left', 'codevz-plus' ),
					'cz_image_fade_to_right' => esc_html__( 'Fade To Right', 'codevz-plus' ),
					'cz_image_zoom_in' => esc_html__( 'Zoom In', 'codevz-plus' ),
					'cz_image_zoom_out' => esc_html__( 'Zoom Out', 'codevz-plus' ),
					'cz_image_blurred' => esc_html__( 'Blurred', 'codevz-plus' ),
				],
			]
		);

		$this->add_control(
			'hover_image',
			[
				'label' => esc_html__( 'Hover Image', 'codevz-plus' ),
				'type' => Controls_Manager::MEDIA,
				'default' => [
					'url' => Codevz_Plus::$url . 'assets/img/p.svg',
				],
				'condition' => [
					'fx_hover' => [
						'cz_image_simple_fade',
						'cz_image_flip_h',
						'cz_image_flip_v',
						'cz_image_fade_to_top',
						'cz_image_fade_to_bottom',
						'cz_image_fade_to_left',
						'cz_image_fade_to_right',
						'cz_image_zoom_in',
						'cz_image_zoom_out',
						'cz_image_blurred',
					],
				],
			]
		); 
		
		$this->add_group_control(
			Group_Control_Image_Size::get_type(),
			[
				'name' => 'image', // Usage: `{name}_size` and `{name}_custom_dimension`, in this case `image_size` and `image_custom_dimension`.
				'default' => 'full',
				'separator' => 'none',
			]
		);

		$this->add_control(
			'css_position',
			[
				'label' => esc_html__( 'Image Position', 'codevz-plus' ),
				'type' => Controls_Manager::SELECT,
				'default' => 'relative',
				'options' => [
					'relative' 									=> esc_html__( 'Inline', 'codevz-plus' ),
					'relative;display: block;text-align:center' => esc_html__( 'Block', 'codevz-plus' ),
					'relative;float:left' 						=> esc_html__( 'Left', 'codevz-plus' ),
					'relative;display: table;margin:0 auto' 	=> esc_html__( 'Center', 'codevz-plus' ),
					'relative;float:right' 						=> esc_html__( 'Right', 'codevz-plus' ),
					'relative;float:left;margin:0 auto' 		=> esc_html__( 'Left (center_in_mobile)', 'codevz-plus' ),
					'relative;float:right;margin:0 auto' 		=> esc_html__( 'Right (center_in_mobile)', 'codevz-plus' ),
				],
				'selectors' => [
					'{{WRAPPER}} .cz_image > div' => 'position: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'link_type',
			[
				'label' 	=> esc_html__( 'Link Type', 'codevz-plus' ),
				'type' 		=> Controls_Manager::SELECT,
				'default' 	=> 'none',
				'options' 	=> [
					'none' 		=> esc_html__( 'None', 'codevz-plus' ),
					'lightbox' 	=> esc_html__( 'Link to large image (Lightbox)', 'codevz-plus' ),
					'custom' 	=> esc_html__( 'Custom', 'codevz-plus' ),
				],
			]
		);

		$this->add_control(
			'link',
			[
				'label' 	=> esc_html__( 'Link', 'codevz-plus' ),
				'type' 		=> Controls_Manager::URL,
				'condition' 	=> [
					'link_type' 	=> 'custom',
				]
			]
		);

		$this->add_control(
			'tooltip',
			[
				'label' => esc_html__( 'Tooltip', 'codevz-plus' ),
				'type' => $free ? 'codevz_pro' : Controls_Manager::TEXT,
			]
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_caption',
			[
				'label' => esc_html__( 'Caption / Info box', 'codevz-plus' )
			]
		);

		$this->add_control(
			'type',
			[
				'label' 	=> esc_html__( 'Type', 'codevz-plus' ),
				'type' 		=> Controls_Manager::SELECT,
				'options' 	=> [
					'' 			=> esc_html__( 'Caption', 'codevz-plus' ),
					'2' 		=> esc_html__( 'Info Box', 'codevz-plus' ),
				],
			]
		);

		$this->add_control(
			'position',
			[
				'label' 	=> esc_html__( 'Position', 'codevz-plus' ),
				'type' 		=> Controls_Manager::SELECT,
				'default' 	=> 'top-right',
				'options' 	=> [
					'top-right' 	=> esc_html__( 'Top right', 'codevz-plus' ),
					'top-left' 		=> esc_html__( 'Top left', 'codevz-plus' ),
					'bottom-right' 	=> esc_html__( 'Bottom right', 'codevz-plus' ),
					'bottom-left' 	=> esc_html__( 'Bottom left', 'codevz-plus' ),
				],
				'condition' => [
					'type' 			=> '2'
				]
			]
		);

		$this->add_control(
			'content',
			[
				'label' 	=> esc_html__( 'Caption', 'codevz-plus' ),
				'type' 		=> Controls_Manager::WYSIWYG
			]
		);

		$this->add_control(
			'icon',
			[
				'label' 	=> esc_html__( 'Icon', 'codevz-plus' ),
				'type' 		=> Controls_Manager::ICONS,
				'default' => [
					'value' => 'fa fa-info',
					'library' => 'solid',
				],
				'skin' 		=> 'inline',
				'label_block' => false,
				'condition' => [
					'type' 			=> '2'
				]
			]
		);

		$this->add_control(
			'sticky_caption',
			[
				'label' 	=> esc_html__( 'Sticky on mouse hover?', 'codevz-plus' ),
				'type' 		=> $free ? 'codevz_pro' : Controls_Manager::SWITCHER,
				'condition' => [
					'type' 			=> ''
				]
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
			'section_style_image',
			[
				'label' => esc_html__( 'Image', 'codevz-plus' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_responsive_control(
			'css_width',
			[
				'label' => esc_html__( 'Custom Width', 'codevz-plus' ),
				'type' => Controls_Manager::SLIDER,
				'default' => [
					'unit' => '%',
				],
				'size_units' => [ '%', 'px', 'vw' ],
				'range' => [
					'%' => [
						'min' => 1,
						'max' => 100,
					],
					'px' => [
						'min' => 50,
						'max' => 2000,
					],
					'vw' => [
						'min' => 1,
						'max' => 100,
					],
				],
				'selectors' => [
					'{{WRAPPER}} .cz_image > div' => 'width: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->add_responsive_control(
			'sk_image',
			[
				'label' 	=> esc_html__( 'Container', 'codevz-plus' ),
				'type' 		=> $free ? 'stylekit_pro' : 'stylekit',
				'settings' 	=> [ 'background', 'border', 'box-shadow' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_image' ),
			]
		);

		$this->add_responsive_control(
			'sk_image_in',
			[
				'label' 	=> esc_html__( 'Image', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'background', 'border', 'box-shadow' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_image_in', '.cz_image:hover .cz_image_in' ),
			]
		);

		$this->add_responsive_control(
			'svg_bg',
			[
				'label' 	=> esc_html__( 'Background layer', 'codevz-plus' ),
				'type' 		=> $free ? 'stylekit_pro' : 'stylekit',
				'settings' 	=> [ 'background', 'top', 'left', 'width', 'height' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_svg_bg:before' ),
			]
		);

		$this->add_responsive_control(
			'sk_caption',
			[
				'label' 	=> esc_html__( 'Caption', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'background', 'color', 'border', 'position', 'top', 'left' ],
				'selectors' => Xtra_Elementor::sk_selectors( 
					'.cz_image_caption', 
					'.cz_image:hover .cz_image_caption'
				),
			]
		);

		$this->add_responsive_control(
			'sk_icon',
			[
				'label' 	=> esc_html__( 'Icon', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'background', 'color', 'border' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_image > div > i' ),
				'condition' => [
					'type' 		=> '2'
				]
			]
		);

	}

	public function render() {

		$settings = $this->get_settings_for_display();

		// Link.
		$a_before = $a_after = '';

		if ( $settings['link_type'] === 'lightbox' && ! empty( $settings[ 'image' ][ 'url' ] ) ) {

			$a_before = '<a href="' . $settings[ 'image' ][ 'url' ] . '">';
			$a_after = '</a>';

		} else if ( $settings['link_type'] === 'custom' ) {

			$this->add_link_attributes( 'link', $settings['link'] );

			$a_before = '<a '. $this->get_render_attribute_string( 'link' ) . '>';
			$a_after = '</a>';

		}

		// Tooltip.
		$tooltip = $settings['tooltip'] ? ' data-title="' . $settings['tooltip'] . '"' : '';

		// Get images.
		$image = Group_Control_Image_Size::get_attachment_image_html( $settings );

		// Hover effect.
		if ( ! empty( $settings['hover_image']['id'] ) ) {
			
			$settings['image'] = $settings['hover_image'];
			$hover_image = Group_Control_Image_Size::get_attachment_image_html( $settings );

		} else {

			$hover_image = $image;

		}

		// Hover.
		$hover_image_tag = '<div class="cz_hover_image">' . $hover_image .'</div>';

		$caption = '';

		// Caption.
		if ( $settings['content'] ) {
			$caption = Codevz_Plus::fix_extra_p( $settings['content'] );
			$caption = '<div class="cz_image_caption mt10 cz_wpe_content">' . do_shortcode( $caption ) . '</div>';
		}

		// Info box icon.
		if ( $settings['type'] ) {

			ob_start();
			Icons_Manager::render_icon( $settings['icon'] );
			$icon = ob_get_clean();

			$caption = $icon . $caption;

		}

		// Background layer.
		$svg_bg = empty( $settings['svg_bg'] ) ? '' : ' class="cz_svg_bg"';

		// Widget classes.
		$classes = array();
		$classes[] = 'cz_image clr';
		$classes[] = $settings['fx_hover'];
		$classes[] = $settings['tooltip'] ? 'cz_tooltip_up' : '';
		$classes[] = $settings['sticky_caption'] ? 'cz_image_caption_sticky' : '';
		$classes[] = $settings['type'] ? 'codevz-plus-image-info-box' : '';
		if ( ! empty( $settings['css_position'] ) ) {	
			$classes[] = ( $settings['css_position'] === 'relative' ) ? 'center_on_mobile' : '';
			$classes[] = Codevz_Plus::contains( $settings['css_position'], 'margin' ) ? 'center_on_mobile' : '';
		}
		$classes[] = empty( $settings['ir_position'] ) ? '' : 'codevz-plus-inverted-radius-' . esc_attr( $settings['ir_position'] );

		// Fullwidth image.
		if ( ! empty( $settings[ 'css_width' ][ 'size' ] ) ) {

			if ( $settings[ 'css_width' ][ 'size' ] == '100' && $settings[ 'css_width' ][ 'unit' ] == '%' ) {

				$classes[] = 'xtra-image-full-width';

			}

		}
		if ( ! empty( $settings[ 'css_width_tablet' ][ 'size' ] ) ) {

			if ( $settings[ 'css_width_tablet' ][ 'size' ] == '100' && $settings[ 'css_width_tablet' ][ 'unit' ] == '%' ) {

				$classes[] = 'xtra-image-full-width-tablet';

			}

		}
		if ( ! empty( $settings[ 'css_width_mobile' ][ 'size' ] ) ) {

			if ( $settings[ 'css_width_mobile' ][ 'size' ] == '100' && $settings[ 'css_width_mobile' ][ 'unit' ] == '%' ) {

				$classes[] = 'xtra-image-full-width-mobile';

			}

		}

		// Parallax.
		Xtra_Elementor::parallax( $settings );

		// Link before.
		echo wp_kses_post( (string) $a_before );

		$position = empty( $settings[ 'position' ] ) ? '' : $settings[ 'position' ];

	?>

		<div<?php echo wp_kses_post( (string) Codevz_Plus::classes( [], $classes ) ); echo ' data-position="' . esc_attr( $position ) . '"'; ?>>

			<div<?php echo wp_kses_post( (string) $svg_bg . $tooltip ); ?>>

				<div class="cz_image_in"<?php echo wp_kses_post( (string) Codevz_Plus::tilt( $settings ) ); ?>>

					<div class="cz_main_image"><?php echo wp_kses_post( (string) $image ); ?></div>

					<?php echo wp_kses_post( (string) $hover_image_tag ); ?>

				</div>

				<?php echo wp_kses_post( (string) $caption ); ?>

			</div>

		</div>

	<?php

		// Close link.
		echo wp_kses_post( (string) $a_after );

		// Close parallax.
		Xtra_Elementor::parallax( $settings, true );

	}

	protected function content_template(){
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

		if ( settings.hover_image.url ) {
			var hover_image = {
				id: settings.hover_image.id,
				url: settings.hover_image.url,
				size: settings.image_size,
				dimension: settings.image_custom_dimension,
				model: view.getEditModel()
			};

			var hover_image_url = elementor.imagesManager.getImageUrl( hover_image );

			if ( ! hover_image_url ) {
				return;
			}
		}

	// Images.
	var image = '<img src="' + image_url + '" />',
		image_url = settings.hover_image.id ? hover_image_url : image_url,
		hover_image = '<img src="' + image_url + '" />',
		a_before = a_after = '';

	// Link.
	if ( settings.link_type === 'lightbox' ) {
		a_before = '<a href="{{ settings.link.url }}" data-xtra-lightbox>';
		a_after = '</a>';
	} else if ( settings.link_type === 'custom' ) {
		a_before = '<a href="{{ settings.link.url }}">';
		a_after = '</a>';
	}

	// Widget classes.
	var classes = 'cz_image clr', 
		classes = classes + ( settings.fx_hover ? ' ' + settings.fx_hover : '' ),
		classes = classes + ( settings.tooltip ? ' cz_tooltip_up' : '' ),
		classes = classes + ( settings.sticky_caption ? ' cz_image_caption_sticky' : '' ),
		classes = classes + ( settings.type ? ' codevz-plus-image-info-box' : '' ),
		classes = classes + ( settings.css_position === 'relative' ? ' center_on_mobile' : '' ),
		classes = classes + ( settings.css_position.indexOf( 'margin' ) >= 0 ? ' center_on_mobile' : '' ),
		classes = classes + ( settings.ir_position ? ' codevz-plus-inverted-radius-' + settings.ir_position : '' ),

		caption = settings.content ? '<div class="cz_image_caption mt10">' + settings.content + '</div>' : '',

		tooltip = settings.tooltip ? ' data-title="' + settings.tooltip + '"' : '',
		hover_image_tag = hover_image ? '<div class="cz_hover_image">' + hover_image +'</div>' : '',

		parallaxOpen = xtraElementorParallax( settings ),
		parallaxClose = xtraElementorParallax( settings, true ),

		tilt = xtraElementorTilt( settings ),
		svg_bg = settings.svg_bg ? ' class="cz_svg_bg"' : '',

		position = settings.position ? settings.position : 'top-right',
		icon = '';

		// Info box icon.
		if ( settings.type && settings.icon ) {

			icon = settings.icon.value ? '<i class="' + settings.icon.value + '"></i>' : '<i class="fa fa-info"></i>',
			caption = icon + caption;

		}

	#>

	{{{ parallaxOpen }}}

	{{{ a_before }}}

	<div class="{{{ classes }}}" data-position="{{{ position }}}">

		<div{{{ svg_bg }}}{{{ tooltip }}}>

			<div class="cz_image_in" {{{ tilt }}}>

				<div class="cz_main_image">{{{ image }}}</div>

				{{{ hover_image_tag }}}

			</div>

			{{{ caption }}}

		</div>

	</div>

	{{{ a_after }}}

	{{{ parallaxClose }}}

	<?php

	}

}