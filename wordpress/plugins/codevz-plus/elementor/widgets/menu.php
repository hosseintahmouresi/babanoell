<?php if ( ! defined( 'ABSPATH' ) ) {exit;} // Exit if accessed directly.

use Codevz_Plus as Codevz_Plus;
use Elementor\Widget_Base;
use Elementor\Icons_Manager;
use Elementor\Controls_Manager;

class Xtra_Elementor_Widget_menu extends Widget_Base {

	protected $id = 'cz_menu';

	public function get_name() {
		return $this->id;
	}

	public function get_title() {
		return esc_html__( 'Header - Menu', 'codevz-plus' );
	}

	public function get_icon() {
		return 'xtra-menu';
	}

	public function get_categories() {
		return [ 'xtra' ];
	}

	public function get_keywords() {

		return [

			esc_html__( 'codevz', 'codevz-plus' ),
			esc_html__( 'Menu', 'codevz-plus' ),
			esc_html__( 'Navigation', 'codevz-plus' ),
			esc_html__( 'Header', 'codevz-plus' ),
			esc_html__( 'Footer', 'codevz-plus' ),

		];

	}

	public function register_controls() {

		$free = Codevz_Plus::is_free();

		$this->start_controls_section(
			'section_content',
			[
				'label' 	=> esc_html__( 'Settings', 'codevz-plus' ),
				'tab' 		=> Controls_Manager::TAB_CONTENT,
			]
		);

		$this->add_control(
			'menu_location',
			[
				'label' 		=> esc_html__( 'Menu', 'codevz-plus' ),
				'type' 			=> Controls_Manager::SELECT,
				'options' 		=> [
					'' 			=> esc_html__( '~ Select ~', 'codevz-plus' ), 
					'primary' 	=> esc_html__( 'Primary', 'codevz-plus' ), 
					'secondary' => esc_html__( 'Secondary', 'codevz-plus' ), 
					'one-page'  => esc_html__( 'One Page', 'codevz-plus' ), 
					'footer'  	=> esc_html__( 'Footer', 'codevz-plus' ),
					'mobile'  	=> esc_html__( 'Mobile', 'codevz-plus' ),
					'custom-1' 	=> esc_html__( 'Custom 1', 'codevz-plus' ), 
					'custom-2' 	=> esc_html__( 'Custom 2', 'codevz-plus' ), 
					'custom-3' 	=> esc_html__( 'Custom 3', 'codevz-plus' ),
					'custom-4' 	=> esc_html__( 'Custom 4', 'codevz-plus' ),
					'custom-5' 	=> esc_html__( 'Custom 5', 'codevz-plus' ),
					'custom-6' 	=> esc_html__( 'Custom 6', 'codevz-plus' ),
					'custom-7' 	=> esc_html__( 'Custom 7', 'codevz-plus' ),
					'custom-8' 	=> esc_html__( 'Custom 8', 'codevz-plus' )
				],
			]
		);

		$this->add_control(
			'menu_type',
			[
				'label' 		=> esc_html__( 'Type', 'codevz-plus' ),
				'type' 			=> Controls_Manager::SELECT,
				'options' 		=> [
					'' 							   => esc_html__( '~ Default ~', 'codevz-plus' ),
					'offcanvas_menu_left' 		   => esc_html__( 'Offcanvas left', 'codevz-plus' ),
					'offcanvas_menu_right' 		   => esc_html__( 'Offcanvas right', 'codevz-plus' ),
					'fullscreen_menu' 			   => esc_html__( 'Full screen', 'codevz-plus' ),
					'dropdown_menu' 			   => esc_html__( 'Dropdown', 'codevz-plus' ),
					'open_horizontal inview_left'  => esc_html__( 'Sliding menu left', 'codevz-plus' ),
					'open_horizontal inview_right' => esc_html__( 'Sliding menu right', 'codevz-plus' ),
					'left_side_dots side_dots' 	   => esc_html__( 'Vertical dots left', 'codevz-plus' ),
					'right_side_dots side_dots'    => esc_html__( 'Vertical dots right', 'codevz-plus' ),
				],
			]
		);

		$this->add_control(
			'menu_position',
			[
				'label' => esc_html__( 'Position', 'codevz-plus' ),
				'type' => Controls_Manager::SELECT,
				'options' => [
					'' 						=> esc_html__( '~ Default ~', 'codevz-plus' ),
					'cz_helm_pos_left' 		=> esc_html__( 'Left', 'codevz-plus' ),
					'cz_helm_pos_center' 	=> esc_html__( 'Center', 'codevz-plus' ),
					'cz_helm_pos_right' 	=> esc_html__( 'Right', 'codevz-plus' ),
				]
			]
		);

		$this->add_control(
			'menu_icon',
			[
				'label' => esc_html__( 'Icon', 'codevz-plus' ),
				'type' => Controls_Manager::ICONS,
				'skin' => 'inline',
				'label_block' => false,
				'default' 		=> [
					'value' 		=> 'fas fa-bars',
					'library' 		=> 'fa-solid',
				],
				'condition' 	=> [
					'menu_type' 	=> [
						'offcanvas_menu_left',
						'offcanvas_menu_right',
						'fullscreen_menu',
						'dropdown_menu',
						'open_horizontal inview_left',
						'open_horizontal inview_right'
					]
				]
			]
		);

		$this->add_control(
			'menu_title',
			[
				'label' => esc_html__( 'Title', 'codevz-plus' ),
				'type' => Controls_Manager::TEXT,
				'condition' 	=> [
					'menu_type' 	=> [
						'offcanvas_menu_left',
						'offcanvas_menu_right',
						'fullscreen_menu',
						'dropdown_menu',
						'open_horizontal inview_left',
						'open_horizontal inview_right'
					]
				]
			]
		);

		$this->add_control(
			'menu_disable_dots',
			[
				'label' => esc_html__( 'Disable Dots', 'codevz-plus' ),
				'type' => Controls_Manager::SWITCHER,
				'condition' 	=> [
					'menu_type' 	=> ''
				]
			]
		);

		$this->add_control(
			'indicator_icon',
			[
				'label' => esc_html__( 'Indicator', 'codevz-plus' ),
				'type' => Controls_Manager::ICONS,
				'skin' => 'inline',
				'label_block' => false
			]
		);

		$this->add_control(
			'indicator_icon2',
			[
				'label' => esc_html__( 'Indicator Inner', 'codevz-plus' ),
				'type' => Controls_Manager::ICONS,
				'skin' => 'inline',
				'label_block' => false
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
			'sk_menu_icon',
			[
				'label' 	=> esc_html__( 'Icon', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'color', 'background', 'border' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.czi:not(.cz_close_popup), .elementor-widget-container > i:not(.cz_close_popup)' ),
			]
		);

		$this->add_responsive_control(
			'sk_menu_title',
			[
				'label' 	=> esc_html__( 'Title', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'color', 'background', 'border' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.czi span, .elementor-widget-container > i span' ),
			]
		);

		$this->add_responsive_control(
			'sk_menu_con',
			[
				'label' 	=> esc_html__( 'Container', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'color', 'background', 'border' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.sf-menu' ),
			]
		);

		$this->add_responsive_control(
			'sk_menu_li',
			[
				'label' 	=> esc_html__( 'Menus li', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'color', 'background', 'border' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.sf-menu > .cz' ),
			]
		);

		$this->add_responsive_control(
			'sk_menu_a',
			[
				'label' 	=> esc_html__( 'Menus', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'color', 'background', 'border' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.sf-menu > .cz > a', '.sf-menu > .cz > a:hover, .sf-menu > .cz:hover > a, .sf-menu > .cz.current_menu > a, .sf-menu > .current-menu-parent > a' ),
			]
		);

		$this->add_responsive_control(
			'sk_menu_shape',
			[
				'label' 	=> esc_html__( 'Shape', 'codevz-plus' ),
				'type' 		=> $free ? 'stylekit_pro' : 'stylekit',
				'settings' 	=> [ 'color', 'background', 'border' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.sf-menu > .cz > a:before' ),
			]
		);

		$this->add_responsive_control(
			'sk_menu_subtitle',
			[
				'label' 	=> esc_html__( 'Subtitle', 'codevz-plus' ),
				'type' 		=> $free ? 'stylekit_pro' : 'stylekit',
				'settings' 	=> [ 'color', 'background', 'border' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.sf-menu > .cz > a > .cz_menu_subtitle', '.sf-menu > .cz > a:hover > .cz_menu_subtitle, .sf-menu > .cz:hover > a > .cz_menu_subtitle, .sf-menu > .cz.current_menu > a > .cz_menu_subtitle, .sf-menu > .current-menu-parent > a > .cz_menu_subtitle' ),
			]
		);

		$this->add_responsive_control(
			'sk_menu_icons',
			[
				'label' 	=> esc_html__( 'Icons', 'codevz-plus' ),
				'type' 		=> $free ? 'stylekit_pro' : 'stylekit',
				'settings' 	=> [ 'color', 'background', 'border' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.sf-menu > .cz > a span i', '.sf-menu > .cz > a:hover span i, .sf-menu > .cz:hover > a span i, .sf-menu > .cz.current_menu > a span i, .sf-menu > .current-menu-parent > a span i' ),
			]
		);

		$this->add_responsive_control(
			'sk_menu_indicator',
			[
				'label' 	=> esc_html__( 'Indicator', 'codevz-plus' ),
				'type' 		=> $free ? 'stylekit_pro' : 'stylekit',
				'settings' 	=> [ 'color', 'background', 'border' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.sf-menu > .cz > a .cz_indicator' ),
			]
		);

		$this->add_responsive_control(
			'sk_menu_delimiter',
			[
				'label' 	=> esc_html__( 'Delimiter', 'codevz-plus' ),
				'type' 		=> $free ? 'stylekit_pro' : 'stylekit',
				'settings' 	=> [ 'color', 'background', 'border' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.sf-menu > .cz:after' ),
			]
		);

		$this->add_responsive_control(
			'sk_menu_dropdown',
			[
				'label' 	=> esc_html__( 'Dropdown', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'color', 'background', 'border' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.sf-menu .cz .sub-menu:not(.cz_megamenu_inner_ul), .sf-menu .cz_megamenu_inner_ul .cz_megamenu_inner_ul' ),
			]
		);

		$this->add_responsive_control(
			'sk_menu_ul_a',
			[
				'label' 	=> esc_html__( 'Inner Menus', 'codevz-plus' ),
				'type' 		=> 'stylekit',
				'settings' 	=> [ 'color', 'background', 'border' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.sf-menu .cz .cz a', '.sf-menu .cz .cz a:hover, .sf-menu .cz .cz:hover > a, .sf-menu .cz .cz.current_menu > a, .sf-menu .cz .current_menu > .current_menu' ),
			]
		);

		$this->add_responsive_control(
			'sk_menu_ul_a_indicator',
			[
				'label' 	=> esc_html__( 'Inner Idicator', 'codevz-plus' ),
				'type' 		=> $free ? 'stylekit_pro' : 'stylekit',
				'settings' 	=> [ 'color', 'background', 'border' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.sf-menu .cz .cz a .cz_indicator' ),
			]
		);

		$this->add_responsive_control(
			'sk_menu_ul_ul',
			[
				'label' 	=> esc_html__( '3rd Level', 'codevz-plus' ),
				'type' 		=> $free ? 'stylekit_pro' : 'stylekit',
				'settings' 	=> [ 'color', 'background', 'border' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.sf-menu .sub-menu .sub-menu:not(.cz_megamenu_inner_ul)' ),
			]
		);

		$this->add_responsive_control(
			'sk_menu_inner_megamenu',
			[
				'label' 	=> esc_html__( 'Megamenu', 'codevz-plus' ),
				'type' 		=> $free ? 'stylekit_pro' : 'stylekit',
				'settings' 	=> [ 'color', 'background', 'border' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.sf-menu .cz_parent_megamenu > [class^="cz_megamenu_"] > .cz, .sf-menu .cz_parent_megamenu > [class*=" cz_megamenu_"] > .cz' ),
			]
		);

		$this->add_responsive_control(
			'sk_menu_a_h6',
			[
				'label' 	=> esc_html__( 'Megamenu Title', 'codevz-plus' ),
				'type' 		=> $free ? 'stylekit_pro' : 'stylekit',
				'settings' 	=> [ 'color', 'background', 'border' ],
				'selectors' => Xtra_Elementor::sk_selectors( '.sf-menu .cz .cz h6' ),
			]
		);

		$this->end_controls_section();

	}

	public function render() {

		$settings = $this->get_settings_for_display();

		Xtra_Elementor::parallax( $settings );

		//$icon = empty( $settings['shopcart_icon'] ) ? 'fa fa-bars' : $settings['shopcart_icon'];

		ob_start();
		Icons_Manager::render_icon( $settings['menu_icon'], [ 'class' => 'xtra-search-icon', 'data-xtra-icon' => ( empty( $settings['menu_icon'][ 'value' ] ) ? 'fa fa-bars' : $settings['menu_icon'][ 'value' ] ) ] );
		$menu_icon = ob_get_clean();

		$type = empty( $settings['menu_type'] ) ? 'cz_menu_default' : $settings['menu_type'];
		if ( $type === 'offcanvas_menu_left' ) {
			$type = 'offcanvas_menu inview_left';
		} else if ( $type === 'offcanvas_menu_right' ) {
			$type = 'offcanvas_menu inview_right';
		}

		$menu_title = isset( $settings['menu_title'] ) ? do_shortcode( $settings['menu_title'] ) : '';
		$menu_icon_class = $menu_title ? ' icon_plus_text' : '';

		// Pos icon.
		if ( $settings[ 'menu_position' ] ) {
			$menu_icon_class .= ' ' . $settings[ 'menu_position' ];
		}

		// Add icon and mobile menu
		if ( $type && $type !== 'offcanvas_menu' && $type !== 'cz_menu_default' ) {
			echo '<i class="' . esc_attr( ( empty( $settings['menu_icon'][ 'value' ] ) ? 'fa fa-bars' : $settings['menu_icon'][ 'value' ] ) . ' icon_' . $type . $menu_icon_class ) . '"><span>' . esc_html( $menu_title ) . '</span></i>';
		}
		if ( $settings[ 'menu_type' ] != 'offcanvas_menu_left' && $settings[ 'menu_type' ] != 'offcanvas_menu_right' ) {
			$menu_icon_class .= ' hide';
		}
		echo '<i class="' . esc_attr( ( empty( $settings['menu_icon'][ 'value' ] ) ? 'fa fa-bars' : $settings['menu_icon'][ 'value' ] ) . ' icon_mobile_' . $type . $menu_icon_class ) . '"><span>' . esc_html( $menu_title ) . '</span></i>';

		// Default
		if ( empty( $settings['menu_location'] ) ) {
			$settings['menu_location'] = 'primary';
		}

		// Check for meta box and set one page instead primary
		$page_menu = Codevz_Plus::meta( 0, 'one_page' );
		if ( $settings['menu_location'] === 'primary' && $page_menu ) {
			$settings['menu_location'] = ( $page_menu === '1' ) ? 'one-page' : $page_menu;
		}

		// Disable three dots auto responsive
		$type .= empty( $settings['menu_disable_dots'] ) ? '' : ' cz-not-three-dots';

		// Indicators
		$indicator  = empty( $settings['indicator_icon'][ 'value' ] ) ? '' : $settings['indicator_icon'][ 'value' ];
		$indicator2  = empty( $settings['indicator_icon2'][ 'value' ] ) ? '' : $settings['indicator_icon2'][ 'value' ];

		// Pos.
		if ( $settings[ 'menu_position' ] ) {
			$type .= ' ' . $settings[ 'menu_position' ];
		}

		$rand = 'cz_menu_' . wp_rand( 111, 999 );

		// Menu
		wp_nav_menu(
			apply_filters( 'codevz_nav_menu',
				[
					'theme_location' 	=> esc_attr( $settings['menu_location'] ),
					'cz_row_id' 		=> '',
					'cz_indicator' 		=> $indicator,
					'container' 		=> false,
					'fallback_cb' 		=> false,
					'walker' 			=> class_exists( 'Codevz_Walker_nav' ) ? new Codevz_Walker_nav() : false,
					'items_wrap' 		=> '<ul class="sf-menu clr ' . esc_attr( $rand ) . ' ' . esc_attr( $type ) . '" data-indicator="' . esc_attr( $indicator ) . '" data-indicator2="' . esc_attr( $indicator2 ) . '">%3$s</ul>'
				]
			)
		);

		echo '<i class="fa czico-198-cancel cz_close_popup xtra-close-icon hide"></i>';

		// Fix live.
		if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
			echo '<style>.menu-item-has-children:hover > ul {display:block!important}</style>';
			echo "<script>

				jQuery( function( $ ) {

					var x 			= $( '." . esc_attr( $rand ) . "' ),
						indicator 	= x.attr( 'data-indicator' ),
						indicator2 	= x.attr( 'data-indicator2' );

					// Menu indicators.
					$( '.sub-menu', x ).parent().each( function() {

						var en = $( this ),
							a = en.find( '> a, > h6' );

						if ( ! a.find( '.cz_indicator' ).length ) {

							if ( $( '.cz_menu_subtitle', a ).length ) {
								$( '.cz_menu_subtitle', a ).before( '<i class=\"cz_indicator\" aria-hidden=\"true\"></i>' );
							} else {
								a.append( '<i class=\"cz_indicator\" aria-hidden=\"true\"></i>' );
							}

						}

						if ( indicator || indicator2 ) {
							$( '.cz_indicator', a ).addClass( a.closest( '.sub-menu' ).length ? indicator2 : indicator );
						}

						if ( ! en.find( 'li, div' ).length ) {
							en.find( '.cz_indicator' ).remove();
							en.next( 'ul' ).remove();
						}

					});

				});

			</script>";
		}

		Xtra_Elementor::parallax( $settings, true );

	}

}