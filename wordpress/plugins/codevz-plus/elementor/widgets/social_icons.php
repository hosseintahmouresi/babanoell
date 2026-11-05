<?php if ( ! defined( 'ABSPATH' ) ) {exit;} // Exit if accessed directly.

use Elementor\Repeater;
use Elementor\Widget_Base;
use Elementor\Icons_Manager;
use Elementor\Controls_Manager;

class Xtra_Elementor_Widget_social_icons extends Widget_Base {

	protected $id = 'cz_social_icons';

	public function get_name() {
		return $this->id;
	}

	public function get_title() {
		return esc_html__( 'Icon', 'codevz-plus' );
	}
	
	public function get_icon() {
		return 'xtra-social-icons';
	}

	public function get_categories() {
		return [ 'xtra' ];
	}

	public function get_keywords() {

		return [

			esc_html__( 'XTRA', 'codevz-plus' ),
			esc_html__( 'Social', 'codevz-plus' ),
			esc_html__( 'Icon', 'codevz-plus' ),
			esc_html__( 'Share', 'codevz-plus' ),

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

		$repeater = new Repeater();

		$repeater->add_control(
			'title', [
				'label' => esc_html__( 'Title', 'codevz-plus' ),
				'type' => Controls_Manager::TEXT,
			]
		);

		$repeater->add_control (
			'icon',
			[
				'label' => esc_html__( 'Icon', 'codevz-plus' ),
				'type' => Controls_Manager::ICONS,
				'skin' => 'inline',
				'label_block' => false,
				'default' => [
					'value' => 'fa fa-facebook-f',
					'library' => 'solid',
				],
			]
		);

		$repeater->add_control(
			'link',
			[
				'label' => esc_html__( 'Link', 'codevz-plus' ),
				'type' => Controls_Manager::URL,
				'show_external' => true
			]
		);

		$this->add_control(
			'social',
			[
				'label' => esc_html__( 'Add icon(s)', 'codevz-plus' ),
				'type' => Controls_Manager::REPEATER,
				'prevent_empty' => false,
				'fields' => $repeater->get_controls(),
				'default' => [
					[
						'icon' => 'fas fa-facebook',
						'title' => '',
						'link' => '#'
					],
				],
			]
		);

		$this->add_control(
			'position',
			[
				'label' => esc_html__( 'Position', 'codevz-plus' ),
				'type' => Controls_Manager::SELECT,
				'default' => 'tal',
				'options' => [
					'tal' => esc_html__( 'Left', 'codevz-plus' ),
					'tac' => esc_html__( 'Center', 'codevz-plus' ),
					'tar' => esc_html__( 'Right', 'codevz-plus' ),
				],
			]
		);

		$this->add_control(
			'tooltip',
			[
				'label' => esc_html__( 'Tooltip?', 'codevz-plus' ),
				'type' => $free ? 'codevz_pro' : Controls_Manager::SELECT,
				'default' => '',
				'options' => [
					'' => esc_html__( 'Select', 'codevz-plus' ),
					'cz_tooltip cz_tooltip_up' => esc_html__( 'Up', 'codevz-plus' ),
					'cz_tooltip cz_tooltip_down' => esc_html__( 'Down', 'codevz-plus' ),
					'cz_tooltip cz_tooltip_left' => esc_html__( 'Left', 'codevz-plus' ),
					'cz_tooltip cz_tooltip_right' => esc_html__( 'Right', 'codevz-plus' ),
				],
			]
		);

		$this->add_control(
			'fx',
			[
				'label' => esc_html__( 'Hover effect', 'codevz-plus' ),
				'type' => $free ? 'codevz_pro' : Controls_Manager::SELECT,
				'default' => '',
				'options' => [
					'' => esc_html__( 'Select', 'codevz-plus' ),
					'cz_social_fx_0' => esc_html__( 'Zoom In', 'codevz-plus' ),
					'cz_social_fx_1' => esc_html__( 'Zoom Out', 'codevz-plus' ),
					'cz_social_fx_2' => esc_html__( 'Bottom to Top', 'codevz-plus' ),
					'cz_social_fx_3' => esc_html__( 'Top to Bottom', 'codevz-plus' ),
					'cz_social_fx_4' => esc_html__( 'Left to Right', 'codevz-plus' ),
					'cz_social_fx_5' => esc_html__( 'Right to Left', 'codevz-plus' ),
					'cz_social_fx_6' => esc_html__( 'Rotate', 'codevz-plus' ),
					'cz_social_fx_7' => esc_html__( 'Infinite Shake', 'codevz-plus' ),
					'cz_social_fx_8' => esc_html__( 'Infinite Wink', 'codevz-plus' ),
					'cz_social_fx_9' => esc_html__( 'Quick Bob', 'codevz-plus' ),
					'cz_social_fx_10' => esc_html__( 'Flip Horizontal', 'codevz-plus' ),
					'cz_social_fx_11' => esc_html__( 'Flip Vertical', 'codevz-plus' ),
				],
			]
		);

		$this->add_control(
			'inline_title',
			[
				'label' => esc_html__( 'Inline title?', 'codevz-plus' ),
				'type' => $free ? 'codevz_pro' : Controls_Manager::SWITCHER,
				
			]
		);

		$this->add_control(
			'color_mode',
			[
				'label' => esc_html__( 'Color Mode', 'codevz-plus' ),
				'type' => Controls_Manager::SELECT,
				'default' => '',
				'options' => [
					'' => esc_html__( 'Select', 'codevz-plus' ),
					'cz_social_colored' => esc_html__( 'Original colors', 'codevz-plus' ),
					'cz_social_colored_hover' => esc_html__( 'Original colors on hover', 'codevz-plus' ),
					'cz_social_colored_bg' => esc_html__( 'Original background', 'codevz-plus' ),
					'cz_social_colored_bg_hover' => esc_html__( 'Original background on hover', 'codevz-plus' ),
				],
			]
		);

		$this->add_control(
			'center_on_mobile',
			[
				'label' => esc_html__( 'Center on mobile', 'codevz-plus' ),
				'type' => $free ? 'codevz_pro' : Controls_Manager::SWITCHER
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
			'sk_con',
			[
				'label' 	=> esc_html__( 'Container', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'background', 'border', 'box-shadow' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_social_icons' ),
			]
		);

		$this->add_responsive_control(
			'sk_icons',
			[
				'label' 	=> esc_html__( 'Icons', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'width', 'color', 'background', 'border' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_social_icons a', '.cz_social_icons a:hover' ),
			]
		);


		$this->add_responsive_control(
			'sk_inner_icon',
			[
				'label' 	=> esc_html__( 'Inner icons', 'codevz-plus' ),
				'type' 		=> $free ? 'stylekit_pro' : 'stylekit',
				'settings' 	=> [ 'width', 'height', 'color', 'line-height', 'font-size', 'background', 'border' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_social_icons a i:before', '.cz_social_icons a:hover i:before' ),
			]
		);

		$this->add_responsive_control(
			'sk_title',
			[
				'label' 	=> esc_html__( 'Inline title', 'codevz-plus' ),
				'type' 		=> $free ? 'stylekit_pro' : 'stylekit',
				'settings' 	=> [ 'color', 'font-family', 'font-size' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.cz_social_icons span', '.cz_social_icons a:hover span' ),
			]
		);

		$this->end_controls_section();
	}

	protected function render() {

		$settings = $this->get_settings_for_display();

		$inline_title = $settings['inline_title'] ? 'cz_social_inline_title' : '';

		$social = '';
		foreach ( $settings['social'] as $index => $i ) {
			
			if ( empty( $i[ 'icon' ][ 'value' ] ) ) {
				continue;
			}

			$i['title'] = empty( $i['title'] ) ? '' : $i['title'];
			$social_class = 'cz-' . str_replace( Codevz_Plus::$social_fa_upgrade, '', $i[ 'icon' ][ 'value' ] );

			$this->add_link_attributes( 'link_' . $index, $i['link'] );

			$social .= '<a ' . $this->get_render_attribute_string( 'link_' . $index ) . ' class="' . $social_class . '"' . ( ( $settings['tooltip'] && $i['title'] ) ? ' data-' : '' ) . 'title="' . $i['title'] . '"' . ( $i['title'] ? ' aria-label="' . ( $i['title'] ) . '"' : '' ) . '><i class="' . $i[ 'icon' ][ 'value' ] . '">' . ( $inline_title ? '<span class="ml10">' . $i['title'] . '</span>' : '' ) . '</i></a>';
		
			$index++;
		}

		// Classes
		$classes = array();
		$classes[] = 'cz_social_icons cz_social clr';
		$classes[] = $inline_title;
		$classes[] = $settings['fx'];
		$classes[] = $settings['position'];
		$classes[] = $settings['color_mode'];
		$classes[] = $settings['tooltip'];
		$classes[] = $settings['center_on_mobile'] ? 'center_on_mobile' : '';
		
		Xtra_Elementor::parallax( $settings );
		
		?>
			<div<?php echo wp_kses_post( (string) Codevz_Plus::classes( [], $classes ) ); ?>><?php echo wp_kses_post( (string) $social ); ?></div>
		<?php

		Xtra_Elementor::parallax( $settings, true );
	}

	protected function content_template() {
		?>
		<#

		var inline_title = settings.inline_title ? 'cz_social_inline_title' : '',
			social ='';

		_.each( settings.social, function( i ) {

			if ( i.icon.value ) {

				var social_class = 'cz-' + i.icon.value.toString().replace( /fa-|far-|fas-|fab-|fa |fas |far |fab |czico-|-square|-official|-circle/gi, '' );

				social = social + '<a href="' + i.link.url + '" class="' + social_class + '"' + ( settings.tooltip && i.title ? ' data-' : '' ) + 'title="' + i.title + '"' + ( i.title ? ' aria-label="' + i.title + '"' : '' ) + '><i class="' + i.icon.value + '">' + ( inline_title ? '<span class="ml10">' + i.title + '</span>' : '' ) + '</i></a>';

			}

		});

		var classes = 'cz_social_icons cz_social clr', 
			classes = settings.fx ? classes + ' ' + settings.fx : classes;
			classes = inline_title ? classes + ' ' + inline_title : classes;
			classes = settings.position ? classes + ' ' + settings.position : classes;
			classes = settings.color_mode ? classes + ' ' + settings.color_mode : classes;
			classes = settings.tooltip ? classes + ' ' + settings.tooltip : classes,
			classes = settings.center_on_mobile ? classes + ' center_on_mobile' : classes;

			parallaxOpen = xtraElementorParallax( settings ),
			parallaxClose = xtraElementorParallax( settings, true );
		#>

		{{{ parallaxOpen }}}

		<div class="{{{classes}}}">{{{social}}}</div>

		{{{ parallaxClose }}}

		<?php
	}
}