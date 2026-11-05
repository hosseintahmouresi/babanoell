<?php if ( ! defined( 'ABSPATH' ) ) {exit;} // Exit if accessed directly.

use Codevz_Plus as Codevz_Plus;
use Elementor\Utils;
use Elementor\Widget_Base;
use Elementor\Icons_Manager;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Image_Size;

class Xtra_Elementor_Widget_button extends Widget_Base {

	protected $id = 'cz_button';

	public function get_name() {
		return $this->id;
	}

	public function get_title() {
		return esc_html__( 'Button', 'codevz-plus' );
	}

	public function get_icon() {
		return 'xtra-button';
	}

	public function get_categories() {
		return [ 'xtra' ];
	}

	public function get_keywords() {

		return [

			esc_html__( 'XTRA', 'codevz-plus' ),
			esc_html__( 'Button', 'codevz-plus' ),
			esc_html__( 'Btn', 'codevz-plus' ),
			esc_html__( 'Call to action', 'codevz-plus' ),

		];

	}

	public function get_style_depends() {

		$array = [ $this->id, 'cz_parallax' ];

		if ( Codevz_Plus::$is_rtl ) {
			$array[] = $this->id . '_rtl';
		}

		return $array;

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
			'title',
			[
				'label' 	=> esc_html__( 'Title', 'codevz-plus' ),
				'type' 		=> Controls_Manager::TEXT,
				'default' 	=> esc_html__( 'Button title', 'codevz-plus' ),
				'placeholder' => esc_html__( 'Button title', 'codevz-plus' ),
			]
		);

		$this->add_control(
			'subtitle',
			[
				'label' => esc_html__( 'Subtitle', 'codevz-plus' ),
				'type'  => $free ? 'codevz_pro' : Controls_Manager::TEXT
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
			'btn_position',
			[
				'label' => esc_html__( 'Position', 'codevz-plus' ),
				'type' => Controls_Manager::SELECT,
				'default' => '',
				'options' => [
					'' 				=> esc_html__( 'Select', 'codevz-plus' ),
					'cz_btn_inline' => esc_html__( 'Inline', 'codevz-plus' ),
					'cz_btn_block' 	=> esc_html__( 'Block', 'codevz-plus' ),
					'cz_btn_left' 	=> ( Codevz_Plus::$is_rtl ? esc_html__( "Right", 'codevz-plus' ) : esc_html__( "Left", 'codevz-plus' ) ),
					'cz_btn_center' => esc_html__( "Center", 'codevz-plus' ),
					'cz_btn_right' 	=> ( Codevz_Plus::$is_rtl ? esc_html__( "Left", 'codevz-plus' ) : esc_html__( "Right", 'codevz-plus' ) ),
					'cz_btn_left cz_mobile_btn_center' 	=> ( Codevz_Plus::$is_rtl ? esc_html__( "Right", 'codevz-plus' ) : esc_html__( "Left", 'codevz-plus' ) ) . ' ' . esc_html__( '(Center in mobile)', 'codevz-plus' ),
					'cz_btn_right cz_mobile_btn_center'	=> ( Codevz_Plus::$is_rtl ? esc_html__( "Left", 'codevz-plus' ) : esc_html__( "Right", 'codevz-plus' ) ) . ' ' . esc_html__( '(Center in mobile)', 'codevz-plus' ),
					'cz_btn_left cz_mobile_btn_block' 	=> ( Codevz_Plus::$is_rtl ? esc_html__( "Right", 'codevz-plus' ) : esc_html__( "Left", 'codevz-plus' ) ) . ' ' . esc_html__( '(Block in mobile)', 'codevz-plus' ),
					'cz_btn_right cz_mobile_btn_block' 	=> ( Codevz_Plus::$is_rtl ? esc_html__( "Left", 'codevz-plus' ) : esc_html__( "Right", 'codevz-plus' ) ) . ' ' . esc_html__( '(Block in mobile)', 'codevz-plus' ),
				],
			]
		);

		$this->add_control(
			'icon_type',
			[
				'label' => esc_html__( 'Icon Type', 'codevz-plus' ),
				'type' => Controls_Manager::SELECT,
				'default' => 'icon',
				'options' => [
					'icon' => esc_html__( 'Icon', 'codevz-plus' ),
					'image' => esc_html__( 'Image', 'codevz-plus' ) . ( $free ? ' [' . esc_html__( 'PRO', 'codevz-plus' ) . ']' : '' )
				],
			]
		);

		$this->add_control(
			'icon',
			[
				'label' => esc_html__( 'Icon', 'codevz-plus' ),
				'type' => Controls_Manager::ICONS,
				'skin' => 'inline',
				'label_block' => false,
				'condition' => [
					'icon_type' => 'icon'
				]
			]
		);

		$this->add_control(
			'icon_position',
			[
				'label' => esc_html__( 'Icon Position', 'codevz-plus' ),
				'type' => Controls_Manager::SELECT,
				'options' => [
					'before' => esc_html__( 'Before', 'codevz-plus' ),
					'after' => esc_html__( 'After', 'codevz-plus' ),
				],
				'default' => 'before'
			]
		);


		$this->add_control(
			'image',
			[
				'label' => esc_html__( 'Choose Image', 'codevz-plus' ),
				'type' => Controls_Manager::MEDIA,
				'default' => [
					'url' => Codevz_Plus::$url . 'assets/img/p.svg',
				],
				'condition' => [
					'icon_type' => 'image',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Image_Size::get_type(),
			[
				'name' => 'image',
				'default' => 'full',
				'separator' => 'none',
				'condition' => [
					'icon_type' => 'image',
				],
			]
		);
		$this->add_control(
			'hover_image',
			[
				'label' 	=> esc_html__( 'Hover Image', 'codevz-plus' ),
				'type' 		=> Controls_Manager::MEDIA,
				'condition' => [
					'icon_type' => 'image',
				],
			]
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_button_fx',
			[
				'label' 	=> esc_html__( 'Effects', 'codevz-plus' ),
				'tab' 		=> Controls_Manager::TAB_CONTENT,
			]
		);
			  
		$this->add_control(
			'btn_effect',
			[
				'label' 	=> esc_html__( 'Button Effect', 'codevz-plus' ),
				'type' 		=> $free ? 'codevz_pro' : Controls_Manager::SELECT,
				'default' 	=> 'cz_btn_no_fx',
				'options' 	=> [
					'cz_btn_no_fx' => esc_html__( 'Select', 'codevz-plus' ),
					'cz_btn_move_up' => esc_html__( 'Move Up', 'codevz-plus' ),
					'cz_btn_zoom_in' => esc_html__( 'Zoom In', 'codevz-plus' ),
					'cz_btn_zoom_out' => esc_html__( 'Zoom Out', 'codevz-plus' ),
					'cz_btn_winkle' => esc_html__( 'Winkle', 'codevz-plus' ),
					'cz_btn_absorber' => esc_html__( 'Absorber', 'codevz-plus' ),
					'cz_btn_half_to_fill' => esc_html__( 'Low to Fill', 'codevz-plus' ),
					'cz_btn_half_to_fill_v' => esc_html__( 'Low to Fill Vertical', 'codevz-plus' ),
					'cz_btn_fill_up' => esc_html__( 'Fill Up', 'codevz-plus' ),
					'cz_btn_fill_down' => esc_html__( 'Fill Down', 'codevz-plus' ),
					'cz_btn_fill_left' => esc_html__( 'Fill Left', 'codevz-plus' ),
					'cz_btn_fill_right' => esc_html__( 'Fill Right', 'codevz-plus' ),
					'cz_btn_beat' => esc_html__( 'Single Hard Beat', 'codevz-plus' ),
					'cz_btn_flash' => esc_html__( 'Flash', 'codevz-plus' ),
					'cz_btn_shine' => esc_html__( 'Shine', 'codevz-plus' ),
					'cz_btn_circle_fade' => esc_html__( 'Circle Fade', 'codevz-plus' ),
					'cz_btn_blur' => esc_html__( 'Blur', 'codevz-plus' ),
					'cz_btn_unroll_v' => esc_html__( 'Unroll Vertical', 'codevz-plus' ),
					'cz_btn_unroll_h' => esc_html__( 'Unroll Horizontal', 'codevz-plus' ),
					'cz_btn_two_borders' => esc_html__( 'Outer borders dark', 'codevz-plus' ),
					'cz_btn_two_borders cz_btn_two_borders_light' => esc_html__( 'Outer borders light', 'codevz-plus' ),
				],
			]
		);

		$this->add_control(
			'text_effect',
			[
				'label' => esc_html__( 'Text Effect', 'codevz-plus' ),
				'type' => $free ? 'codevz_pro' : Controls_Manager::SELECT,
				'default' => 'cz_btn_txt_no_fx',
				'options' => [
					'cz_btn_txt_no_fx' 		=> esc_html__( 'Select', 'codevz-plus' ),
					'cz_btn_txt_fade' 		=> esc_html__( 'Simple Fade', 'codevz-plus' ),
					'cz_btn_txt_move_up' 	=> esc_html__( 'Text Move Up', 'codevz-plus' ),
					'cz_btn_txt_move_down' 	=> esc_html__( 'Text Move Down', 'codevz-plus' ),
					'cz_btn_txt_move_right' => esc_html__( 'Text Move Right', 'codevz-plus' ),
					'cz_btn_txt_move_left' 	=> esc_html__( 'Text Move Left', 'codevz-plus' ),
					'cz_btn_icon_move_up' 	=> esc_html__( 'Icon Move Up', 'codevz-plus' ),
					'cz_btn_icon_move_down' => esc_html__( 'Icon Move Down', 'codevz-plus' ),
					'cz_btn_icon_move_right' => esc_html__( 'Icon Move Right', 'codevz-plus' ),
					'cz_btn_icon_move_left' => esc_html__( 'Icon Move Left', 'codevz-plus' ),
					'cz_btn_move_up_icon' 	=> esc_html__( 'Move Up Show Icon', 'codevz-plus' ),
					'cz_btn_show_hidden_icon' => esc_html__( 'Show Hidden Icon', 'codevz-plus' ),
					'cz_btn_ghost_icon' 	=> esc_html__( 'Ghost Icon', 'codevz-plus' ),
					'cz_btn_zoom_out_in' 	=> esc_html__( 'Zoom Out In', 'codevz-plus' ), 
				],
			]
		);
		
		$this->add_control(
			'alt_title',
			[
				'label' => esc_html__( 'Alternative title', 'codevz-plus' ),
				'type' => Controls_Manager::TEXT,
				'condition' => [
					'text_effect!' => 'cz_btn_txt_no_fx'
				],
			]
		);

		$this->add_control(
			'alt_subtitle',
			[
				'label' => esc_html__( 'Alternative subtitle', 'codevz-plus' ),
				'type' => Controls_Manager::TEXT,
				'condition' => [
					'text_effect!' => 'cz_btn_txt_no_fx'
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
			'sk_button',
			[
				'label' 	=> esc_html__( 'Button', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'color', 'background', 'border' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_btn, .cz_btn:before', '.cz_btn:hover, .cz_btn:after' ),
			]
		);

		$this->add_responsive_control(
			'sk_icon',
			[
				'label' 	=> esc_html__( 'Icon', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'color', 'background' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_btn i', '.cz_btn:hover i' ),
			]
		);

		$this->add_responsive_control(
			'sk_subtitle',
			[
				'label' 	=> esc_html__( 'Subtitle', 'codevz-plus' ),
				'type' 		=> $free ? 'stylekit_pro' : 'stylekit',
				'settings' 	=> [ 'color', 'background' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_btn small', '.cz_btn:hover small' ),
			]
		);

		$this->end_controls_section();
	}

	protected function render() {

		$settings = $this->get_settings_for_display();

		$this->add_link_attributes( 'link', $settings['link'] );
	
		$icon = $icon_after = '';

		if ( $settings['icon_type'] === 'icon' && $settings['icon'] ) {

			ob_start();
			Icons_Manager::render_icon( $settings['icon'] );
			$icon = ob_get_clean();

		} else if ( $settings['icon_type'] === 'image' && $settings['image'] ) {

			$icon = '<i>' . Group_Control_Image_Size::get_attachment_image_html( $settings );

			if ( empty( $settings[ 'hover_image' ][ 'url' ] ) ) {
				$settings[ 'hover_image' ] = $settings[ 'image' ];
			}

			$icon .= Group_Control_Image_Size::get_attachment_image_html( $settings, 'image', 'hover_image' );

			$icon .= '</i>';

		}
		
		// Icon position.	
		if( $settings['icon_position'] === 'after' ) {
			$icon_after = $icon;
			$icon = '';
			$settings['btn_effect'] .= ' cz_btn_icon_after';
		}

		// Subtitle
		$subtitle = $settings['subtitle'] ? '<small>' . $settings['subtitle'] . '</small>' : '';
		$alt_subtitle = $settings['alt_subtitle'] ? '<small>' . $settings['alt_subtitle'] . '</small>' : $subtitle;

		// Classes
		$classes = [];
		$classes[] = 'cz_btn';
		$classes[] = $subtitle ? 'cz_btn_subtitle' : '';
		$classes[] = $settings['text_effect'];
		$classes[] = $settings['btn_effect'];
		$classes[] = empty( $settings['btn_position'] ) ? 'cz_mobile_btn_center' : '';
		$classes[] = $settings['icon_type'] === 'image' ? 'cz_btn_has_image' : '';
		$clr = Codevz_Plus::contains( $settings['btn_position'], [ 'btn_left', 'btn_right' ] ) ? '<div class="clr"></div>' : '';

		// Parallax.
		Xtra_Elementor::parallax( $settings );

		?>
		<div class="<?php echo esc_attr( $settings['btn_position'] ); ?>">
			<div>
				<a <?php echo wp_kses_post( (string) $this->get_render_attribute_string( 'link' ) . Codevz_Plus::classes( [], $classes ) ); ?>>

					<span><?php echo do_shortcode( $icon ); ?>
						<strong><?php echo do_shortcode( wp_kses_post( (string) $settings['title'] ) ); ?>
							<?php echo do_shortcode( wp_kses_post( (string) $subtitle ) ); ?>
						</strong>
						<?php echo do_shortcode( $icon_after ); ?>
					</span>

					<b class="cz_btn_onhover">
						<?php echo do_shortcode( $icon ); ?>
						<strong>
							<?php echo do_shortcode( wp_kses_post( (string) $settings['alt_title'] ? $settings['alt_title'] : $settings['title'] ) ); ?>
							<?php echo do_shortcode( wp_kses_post( (string) $alt_subtitle ) ); ?>
						</strong>
						<?php echo do_shortcode( $icon_after ); ?>
					</b>

				</a>
			</div>
		</div> <?php echo wp_kses_post( (string) $clr ); ?>
		<?php

		// Close parallax.
		Xtra_Elementor::parallax( $settings, true );
	}

	protected function content_template() {
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

		var iconHTML = elementor.helpers.renderIcon( view, settings.icon, { 'aria-hidden': true }, 'i' , 'object' ),

			subtitle = settings.subtitle ? '<small>' + settings.subtitle + '</small>' : '',
			alt_subtitle = settings.alt_subtitle  ? '<small>' + settings.alt_subtitle + '</small>' : subtitle,

			icon = icon_after = '',
			clr = '';

		if ( settings.btn_position.indexOf( 'left' ) >= 0 || settings.btn_position.indexOf( 'right' ) >= 0 ) {
			clr = '<div class="clr"></div>';
		}

		if ( settings.icon_type === 'icon' && settings.icon ) {

			icon = iconHTML.value;

		} else if ( settings.icon_type === 'image' && settings.image ) {

			hover_image_url = hover_image_url ? hover_image_url : image_url;

			icon = '<i><img loading="lazy" src="' + image_url + '"><img loading="lazy" src="' + hover_image_url + '"></i>';

		}

		// Icon position.	
		if ( settings.icon_position === 'after' ) {
			icon_after = icon;
			icon = '';
			settings.btn_effect = settings.btn_effect + ' cz_btn_icon_after';
		}

		var classes = 'cz_btn',
			classes = classes + ( subtitle ? ' cz_btn_subtitle': '' ), 
			classes = classes + ' ' + settings.text_effect,
			classes = classes + ' ' + settings.btn_effect,
			classes = classes + ( settings.btn_position ? ' cz_mobile_btn_center' : '' ),
			classes = classes + ( settings.icon_type == 'image' ? ' cz_btn_has_image': '' ),

			parallaxOpen = xtraElementorParallax( settings ),
			parallaxClose = xtraElementorParallax( settings, true );

		#>

		{{{ parallaxOpen }}}

		<div class="{{{settings.btn_position}}}">
			<div>
				<a class="{{{classes}}}" href="{{{settings.link.url}}}">
				
				<span>{{{ icon }}}
					<strong>{{{settings.title}}}
						{{{subtitle}}}
					</strong>
					{{{icon_after}}}
				</span>

				<b class="cz_btn_onhover">
				{{{icon}}}
				<strong>
					<#
						if ( settings.alt_title ) {
							#>{{{settings.alt_title}}}<#
						} else {
							#>{{{settings.title}}}<#
						}
					 #>
					{{{ alt_subtitle }}}
				</strong>
				{{{icon_after}}}</b></a>
			</div>
		</div>{{{clr}}}

		{{{ parallaxClose }}}
		<?php
	}
}