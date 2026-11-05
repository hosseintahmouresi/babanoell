<?php if ( ! defined( 'ABSPATH' ) ) {exit;} // Exit if accessed directly.

use Elementor\Repeater;
use Elementor\Widget_Base;
use Elementor\Icons_Manager;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Image_Size;

class Xtra_Elementor_Widget_team extends Widget_Base {

	protected $id = 'cz_team';

	public function get_name() {
		return $this->id;
	}

	public function get_title() {
		return esc_html__( 'Team Member', 'codevz-plus' );
	}

	public function get_icon() {
		return 'xtra-team';
	}

	public function get_categories() {
		return [ 'xtra' ];
	}

	public function get_keywords() {

		return [

			esc_html__( 'XTRA', 'codevz-plus' ),
			esc_html__( 'Member', 'codevz-plus' ),
			esc_html__( 'Team', 'codevz-plus' ),
			esc_html__( 'Group', 'codevz-plus' ),
			esc_html__( 'About', 'codevz-plus' ),
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
			'style',
			[
				'label' => esc_html__( 'Style', 'codevz-plus' ),
				'type' => Controls_Manager::SELECT,
				'default' => 'cz_team_1',
				'options' => [
					'cz_team_1'  => esc_html__( 'No hover', 'codevz-plus' ),
					'cz_team_2' => esc_html__( 'Social icons on image', 'codevz-plus' ),
					'cz_team_3' => esc_html__( 'Social icons on image', 'codevz-plus' ) . ' 2',
					'cz_team_4' => esc_html__( 'Social and title on image', 'codevz-plus' ),
					'cz_team_5' => esc_html__( 'Social and title on image', 'codevz-plus' ) . ' 2',
					'cz_team_6' => esc_html__( 'Only title on mouse moves', 'codevz-plus' ),
					'cz_team_7' => esc_html__( 'Title on mouse moves and social below image', 'codevz-plus' ),
				],
			]
		);

		$this->add_control(
			'hover_mode',
			[
				'label' => esc_html__( 'Hover mode?', 'codevz-plus' ),
				'type' => $free ? 'codevz_pro' : Controls_Manager::SELECT,
				'options' => [
					''  => esc_html__( 'Select', 'codevz-plus' ),
					'cz_team_rev_hover' => esc_html__( 'Reverse hover mode', 'codevz-plus' ),
					'cz_team_always_show' => esc_html__( 'Always show details', 'codevz-plus' ),
				],
			]
		);

		$this->add_control(
			'image',
			[
				'label' => esc_html__( 'Image', 'codevz-plus' ),
				'type' => Controls_Manager::MEDIA,
				'default' => [
					'url' => 'https://xtratheme.com/img/450x450.jpg',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Image_Size::get_type(),
			[
				'name' => 'image',
				'default' => 'full',
				'separator' => 'none',
			]
		);

		$this->add_control(
			'content',
			[
				'label' => esc_html__('Name and job title', 'codevz-plus' ),
				'type' => Controls_Manager::WYSIWYG,
				'default' => '<h4><strong>John Carter</strong></h4><span style="color: #999999;">Developer</span>',
				'placeholder' => '<h4><strong>John Carter</strong></h4><span style="color: #999999;">Developer</span>',
			]
		);

		$this->add_control(
			'link',
			[
				'label' => esc_html__( 'Link', 'codevz-plus' ),
				'type' => Controls_Manager::URL
			]
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_icon',
			[
				'label' => esc_html__( 'Icon', 'codevz-plus' ),
			]
		);

		$repeater = new Repeater();

		$repeater->add_control(
			'icon',
			[
				'label' => esc_html__( 'Icon', 'codevz-plus' ),
				'type' => Controls_Manager::ICONS,
				'skin' => 'inline',
				'label_block' => false,
			]
		);

		$repeater->add_control(
			'title', 
			[
				'label' => esc_html__( 'Title', 'codevz-plus' ),
				'type' => Controls_Manager::TEXT
			]
		);

		$repeater->add_control(
			'link',
			[
				'label' => esc_html__( 'Link', 'codevz-plus' ),
				'type' => Controls_Manager::URL
			]
		);

		$this->add_control(
			'social',
			[
				'label' 		=> esc_html__( 'Social', 'codevz-plus' ),
				'type' 			=> Controls_Manager::REPEATER,
				'prevent_empty' => false,
				'fields' 		=> $repeater->get_controls()
			]
		);
		
		$this->add_control(
			'color_mode',
			[
				'label' => esc_html__( 'Color mode?', 'codevz-plus' ),
				'type' => Controls_Manager::SELECT,
				'options' => [
					''  => esc_html__( 'Select', 'codevz-plus' ),
					'cz_social_colored' => esc_html__( 'Original colors', 'codevz-plus' ),
					'cz_social_colored_hover' => esc_html__( 'Original colors on hover', 'codevz-plus' ),
					'cz_social_colored_bg'  => esc_html__( 'Original background', 'codevz-plus' ),
					'cz_social_colored_bg_hover'  => esc_html__( 'Original background on hover', 'codevz-plus' ),
				],
			]
		);

		$this->add_control(
			'social_tooltip',
			[
				'label' => esc_html__( 'Tooltip?', 'codevz-plus' ),
				'type' => $free ? 'codevz_pro' : Controls_Manager::SELECT,
				'options' => [
					''  => esc_html__( 'Select', 'codevz-plus' ),
					'cz_tooltip cz_tooltip_up' => esc_html__( 'Up', 'codevz-plus' ),
					'cz_tooltip cz_tooltip_down' => esc_html__( 'Down', 'codevz-plus' ),
					'cz_tooltip cz_tooltip_left'  => esc_html__( 'Left', 'codevz-plus' ),
					'cz_tooltip cz_tooltip_right'  => esc_html__( 'Right', 'codevz-plus' ),
				],
			]
		);

		$this->add_control(
			'social_align',
			[
				'label' => esc_html__( 'Position?', 'codevz-plus' ),
				'type' => Controls_Manager::SELECT,
				'options' => [
					''  	=> esc_html__( '~ Default ~', 'codevz-plus' ),
					'tal' 	=> esc_html__( 'Left', 'codevz-plus' ),
					'tac'  	=> esc_html__( 'Center', 'codevz-plus' ),
					'tar' 	=> esc_html__( 'Right', 'codevz-plus' ),
				],
			]
		);

		$this->add_control(
			'fx',
			[
				'label' => esc_html__( 'Hover effect?', 'codevz-plus' ),
				'type' => $free ? 'codevz_pro' : Controls_Manager::SELECT,
				'options' => [
					''  => esc_html__( 'Select', 'codevz-plus' ),
					'cz_social_fx_0' => esc_html__( 'Zoom In', 'codevz-plus' ),
					'cz_social_fx_1' => esc_html__( 'Zoom Out', 'codevz-plus' ),
					'cz_social_fx_2'  => esc_html__( 'Bottom to Top', 'codevz-plus' ),
					'cz_social_fx_3'  => esc_html__( 'Top to Bottom', 'codevz-plus' ),
					'cz_social_fx_4'  => esc_html__( 'Left to Right', 'codevz-plus' ),
					'cz_social_fx_5'  => esc_html__( 'Right to Left', 'codevz-plus' ),
					'cz_social_fx_6'  => esc_html__( 'Rotate', 'codevz-plus' ),
					'cz_social_fx_7'  => esc_html__( 'Infinite Shake', 'codevz-plus' ),
					'cz_social_fx_8'  => esc_html__( 'Infinite Wink', 'codevz-plus' ),
					'cz_social_fx_9'  => esc_html__( 'Quick Bob', 'codevz-plus' ),
					'cz_social_fx_10'  => esc_html__( 'Flip Horizontal', 'codevz-plus' ),
					'cz_social_fx_11'  => esc_html__( 'Flip Vertical', 'codevz-plus' ),
				],
			]
		);

		$this->add_control(
			'social_v',
			[
				'label' => esc_html__( 'Vertical mode?', 'codevz-plus' ),
				'type' => $free ? 'codevz_pro' : Controls_Manager::SWITCHER
			]
		);

		$this->end_controls_section();

		// Inverted radius controls.
		Xtra_Elementor::inverted_radius_controls( $this );

		// Tilt controls.
		Xtra_Elementor::tilt_controls( $this );

		// Parallax settings.
		Xtra_Elementor::parallax_settings( $this );

		$this->start_controls_section (
			'section_style',
			[
				'label' => esc_html__( 'Style', 'codevz-plus' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_responsive_control (
			'sk_overall',
			[
				'label' 	=> esc_html__( 'Container', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'background', 'border' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_team', '.cz_team:hover' ),
			]
		);

		$this->add_responsive_control (
			'svg_bg',
			[
				'label' 	=> esc_html__( 'Background layer', 'codevz-plus' ),
				'type' 		=> $free ? 'stylekit_pro' : 'stylekit',
				'settings' 	=> [ 'background', 'top', 'left', 'border', 'width', 'height' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_svg_bg:before' ),
			]
		);

		$this->add_responsive_control (
			'sk_image_con',
			[
				'label' 	=> esc_html__( 'Image container', 'codevz-plus' ),
				'type' 		=> $free ? 'stylekit_pro' : 'stylekit',
				'settings' 	=> [ 'background' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_team .cz_team_img' ),
			]
		);

		$this->add_responsive_control (
			'sk_image_img',
			[
				'label' 	=> esc_html__( 'Image', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'grayscale', 'opacity', 'background', 'border' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_team .cz_team_img img', '.cz_team:hover .cz_team_img img' ),
			]
		);

		$this->add_responsive_control(
			'sk_social_con',
			[
				'label' 	=> esc_html__( 'Icons container', 'codevz-plus' ),
				'type' 		=> $free ? 'stylekit_pro' : 'stylekit',
				'settings' 	=> [ 'background', 'border' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_team .cz_team_social_in' ),
			]
		);

		$this->add_responsive_control(
			'sk_icons',
			[
				'label' 	=> esc_html__( 'Icons', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'color', 'background', 'border' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_team .cz_team_social a', '.cz_team .cz_team_social a:hover' ),
			]
		);

		$this->add_responsive_control (
			'sk_content',
			[
				'label' 	=> esc_html__( 'Content', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'color', 'background', 'border' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_team .cz_team_content' ),
			]
		);

		$this->add_responsive_control (
			'sk_content_h3',
			[
				'label' 	=> esc_html__( 'Content H3', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'color', 'background', 'border' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_team .cz_team_content h3' ),
			]
		);

		$this->add_responsive_control (
			'sk_content_h4',
			[
				'label' 	=> esc_html__( 'Content H4', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'color', 'background', 'border' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_team .cz_team_content h4' ),
			]
		);

		$this->end_controls_section();
	}

	public function render() {

		$settings = $this->get_settings_for_display();

		$this->add_link_attributes( 'link', $settings['link'] );
		$a_attr = $this->get_render_attribute_string( 'link' );
		$img = Group_Control_Image_Size::get_attachment_image_html( $settings );
		$content = '<div class="cz_team_content cz_wpe_content">' . do_shortcode( $settings['content'] ) . '</div>';

		if ( $a_attr ) {
			$img = '<a ' . $a_attr . '>' . $img . '</a>';
			$content = '<a ' . $a_attr . '>' . $content . '</a>';
		}

		// Social
		$social = '<div class="' . esc_attr( implode( ' ', array_filter( array( 'cz_team_social cz_social clr', $settings['color_mode'], $settings['fx'], $settings['social_align'], $settings['social_tooltip'] ) ) ) ) . '">';
		$social .= '<div class="cz_team_social_in">';

		foreach ( $settings['social'] as $index => $i ) {

			if ( ! empty( $i['icon'][ 'value' ] ) ) {

				ob_start();
				Icons_Manager::render_icon( $i['icon'] );
				$icon = ob_get_clean();

				$class = isset( $i[ 'icon' ][ 'value' ] ) ? 'cz-' . esc_attr( str_replace( Codevz_Plus::$social_fa_upgrade, '', $i[ 'icon' ][ 'value' ] ) ) : '';

				if ( empty( $i['link']['url'] ) ) {
					$i['link']['url'] = '#';
				}

				$this->add_link_attributes( $class . $index, $i['link'] );

				$social .= '<a ' . $this->get_render_attribute_string( $class . $index ) . ' class="' . esc_attr( $class ) . '"' . ( empty( $i['title'] ) ? '' : ( $settings['social_tooltip'] ? ' data-title' : ' title' ) . '="' . esc_attr( $i['title'] ) . '"' ) . '>' . do_shortcode( $icon ) .'</a>';

				$index++;
			}

		}

		$social .= '</div></div>';
		$social = empty( $settings['social'] ) ? '' : $social;

		// Classes
		$classes = array();
		$classes[] = 'cz_team mb30 clr';
		$classes[] = $settings['hover_mode'];
		$classes[] = empty( $settings['svg_bg'] ) ? '' : 'cz_svg_bg';
		$classes[] = $settings['style'];
		$classes[] = $settings['social_v'] ? 'cz_social_v' : '';
		$classes[] = empty( $settings['ir_position'] ) ? '' : 'codevz-plus-inverted-radius-' . esc_attr( $settings['ir_position'] );

		Xtra_Elementor::parallax( $settings );

		$out = '<div' . wp_kses_post( (string) Codevz_Plus::classes( [], $classes ) ) . '>';

		if ( empty( $settings['style'] ) || $settings['style'] === 'cz_team_1' ) {
			$out .= '<div class="cz_team_img"' . Codevz_Plus::tilt( $settings ) . '>' . $img . '</div>' . $content . $social;
		} else if ( $settings['style'] === 'cz_team_2' || $settings['style'] === 'cz_team_4' ) {
			$out .= '<div class="cz_team_img"' . Codevz_Plus::tilt( $settings ) . '>' . $img . $social . '</div>' . $content;
		} else if ( $settings['style'] === 'cz_team_3' || $settings['style'] === 'cz_team_5' ) {
			$out .= '<div class="cz_team_img"' . Codevz_Plus::tilt( $settings ) . '>' . $img . $content . $social . '</div>';
		} else if ( $settings['style'] === 'cz_team_6' ) {
			$out .= '<div class="cz_team_img"' . Codevz_Plus::tilt( $settings ) . '>' . $img . $content . '</div>';
		} else if ( $settings['style'] === 'cz_team_7' ) {
			$out .= '<div class="cz_team_img"' . Codevz_Plus::tilt( $settings ) . '>' . $img . $content . '</div>' . $social;
		}

		$out .= '</div>';

		echo do_shortcode( $out );
		
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

		var iconHTML = elementor.helpers.renderIcon( view, settings.icon, { 'aria-hidden': true }, 'i' , 'object' ),
			img = '<img src="' + image_url + '" />',
			content = '<div class="cz_team_content cz_wpe_content">' + settings.content + '</div>';

		if ( settings.link.url ) {
			img = '<a href="' + settings.link.url + '">' + img + '</a>';
			content = '<a href="' + settings.link.url + '">' + content + '</a>';
		}

		var social = '<div class="cz_team_social cz_social clr ' + settings.color_mode + ' ' + settings.fx + ' ' + settings.social_align + ' ' + settings.social_tooltip + '">',
			social = social + '<div class="cz_team_social_in">';

		_.each( settings.social, function( i, index ) {

			if ( i.icon.value ) {

				var iconHTML = elementor.helpers.renderIcon( view, i.icon, { 'aria-hidden': true }, 'i' , 'object' ),
					classname = 'cz-' + ( i.icon.value.toString().replace( /fa-|far-|fas-|fab-|fa |fas |far |fab |czico-|-square|-official|-circle/g, '' ) );

				social = social + ( iconHTML.value ? '<a href="' + i.link.url  + '" class="' + classname + '"' + ( i.title ? ( settings.social_tooltip ? ' data-title="' : ' title="' ) + i.title : '' ) + '">' + iconHTML.value + '</a>' : '' );

			}

		});
		social = social + '</div></div>';
		social = settings.social.length ? social : '';

		var classes = 'cz_team mb30 clr', 
			classes = settings.hover_mode 	? classes + ' ' + settings.hover_mode : classes,
			classes = settings.style 		? classes + ' ' + settings.style : classes,
			classes = settings.svg_bg 		? classes + ' cz_svg_bg' : classes,
			classes = settings.social_v 	? classes + ' cz_social_v' : classes,
			classes = classes + ( settings.ir_position ? ' codevz-plus-inverted-radius-' + settings.ir_position : '' ),

			parallaxOpen = xtraElementorParallax( settings ),
			parallaxClose = xtraElementorParallax( settings, true );
			tilt = xtraElementorTilt( settings );
		#>

		{{{ parallaxOpen }}}
		
		<div  class="{{{classes}}}">
		<# if ( settings.style  || settings.style  === 'cz_team_1' ) { #>
			 <div class="cz_team_img" {{{ tilt }}}><img src="{{ image_url }}"></div>{{{content}}}{{{social}}} 
		<# } else if ( settings.style  === 'cz_team_2' || settings.style  === 'cz_team_4' ) { #>
			 <div class="cz_team_img" {{{ tilt }}}><img src="{{ image_url }}">{{{social}}}</div>content;
		<# } else if ( settings.style  === 'cz_team_3' || settings.style  === 'cz_team_5' ) { #>
			 <div class="cz_team_img" {{{ tilt }}}><img src="{{ image_url }}">{{{content}}}{{{social}}}</div>
		<# } else if ( settings.style  === 'cz_team_6' ) { #>
			<div class="cz_team_img" {{{ tilt }}}><img src="{{ image_url }}">{{{content}}}</div>
		<# } else if ( settings.style  === 'cz_team_7' ) { #>
			<div class="cz_team_img" {{{ tilt }}}><img src="{{ image_url }}">{{{content}}}</div>{{{social}}};
		<# } #>
		</div>
		
		{{{ parallaxClose }}}
		<?php
	}


}