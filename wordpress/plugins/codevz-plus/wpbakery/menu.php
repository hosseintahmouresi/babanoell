<?php if ( ! defined( 'ABSPATH' ) ) {exit;} // Cannot access pages directly.

/**
 * Menu navigation
 * 
 * @author Codevz
 * @link http://codevz.com/
 */

class Codevz_WPBakery_menu {

	public $name = false;

	public function __construct( $name ) {
		$this->name = $name;
	}

	/**
	 * Shortcode settings
	 */
	public function in( $wpb = false ) {
		add_shortcode( $this->name, [ $this, 'out' ] );

		$settings = array(
			'category'		=> Codevz_Plus::$title,
			'base'			=> $this->name,
			'name'			=> esc_html__( 'Header - Menu', 'codevz-plus' ),
			'description'	=> esc_html__( 'Navigation', 'codevz-plus' ),
			'icon'			=> 'czi',
			'params'		=> array(
				array(
					'type' 			=> 'cz_sc_id',
					'param_name' 	=> 'id',
					'save_always' 	=> true
				),
	            array(
	                'type'       => 'dropdown',
	                'heading'    => esc_html__('Menu', 'codevz-plus'),
	                'param_name' => 'menu_location',
					'edit_field_class' => 'vc_col-xs-99',
	                'value'      => array(
	                    esc_html__('~ Select ~', 'codevz-plus') => '',
	                    esc_html__('Primary', 'codevz-plus') => 'primary',
	                    esc_html__('Secondary', 'codevz-plus') => 'secondary',
	                    esc_html__('One Page', 'codevz-plus') => 'one-page',
	                    esc_html__('Footer', 'codevz-plus') => 'footer',
	                    esc_html__('Mobile', 'codevz-plus') => 'mobile',
	                    esc_html__('Custom 1', 'codevz-plus') => 'custom-1',
	                    esc_html__('Custom 2', 'codevz-plus') => 'custom-2',
	                    esc_html__('Custom 3', 'codevz-plus') => 'custom-3',
	                    esc_html__('Custom 4', 'codevz-plus') => 'custom-4',
	                    esc_html__('Custom 5', 'codevz-plus') => 'custom-5',
	                    esc_html__('Custom 6', 'codevz-plus') => 'custom-6',
	                    esc_html__('Custom 7', 'codevz-plus') => 'custom-7',
	                    esc_html__('Custom 8', 'codevz-plus') => 'custom-8',
	                ),
	            ),
	            array(
	                'type'       => 'dropdown',
	                'heading'    => esc_html__('Type', 'codevz-plus'),
	                'param_name' => 'menu_type',
					'edit_field_class' => 'vc_col-xs-99',
	                'value'      => array(
	                    esc_html__('~ Default ~', 'codevz-plus') => '',
	                    esc_html__('Offcanvas left', 'codevz-plus') => 'offcanvas_menu_left',
	                    esc_html__('Offcanvas right', 'codevz-plus') => 'offcanvas_menu_right',
	                    esc_html__('Full screen', 'codevz-plus') => 'fullscreen_menu',
	                    esc_html__('Dropdown', 'codevz-plus') => 'dropdown_menu',
	                    esc_html__('Sliding menu left', 'codevz-plus') => 'open_horizontal inview_left',
	                    esc_html__('Sliding menu right', 'codevz-plus') => 'open_horizontal inview_right',
	                    esc_html__('Vertical dots left', 'codevz-plus') => 'left_side_dots side_dots',
	                    esc_html__('Vertical dots right', 'codevz-plus') => 'right_side_dots side_dots',
	                ),
	            ),
	            array(
	                'type'       => 'dropdown',
	                'heading'    => esc_html__('Position', 'codevz-plus'),
	                'param_name' => 'menu_position',
					'edit_field_class' => 'vc_col-xs-99',
	                'value'      => array(
	                    esc_html__('~ Default ~', 'codevz-plus') => '',
	                    esc_html__('Left', 'codevz-plus') => 'cz_helm_pos_left',
	                    esc_html__('Center', 'codevz-plus') => 'cz_helm_pos_center',
	                    esc_html__('Right', 'codevz-plus') => 'cz_helm_pos_right',
	                ),
	            ),
	            array(
	                'type'       => 'cz_icon',
	                'heading'    => esc_html__('Icon', 'codevz-plus'),
	                'param_name' => 'menu_icon',
					'edit_field_class' => 'vc_col-xs-99',
	            ),
	            array(
	                'type'       => 'textfield',
	                'heading'    => esc_html__('Title', 'codevz-plus'),
	                'param_name' => 'menu_title',
					'edit_field_class' => 'vc_col-xs-99',
	                'dependency' => array(
	                    'element' => 'menu_type',
	                    'value'   => array('offcanvas_menu_left', 'offcanvas_menu_right', 'fullscreen_menu', 'dropdown_menu', 'open_horizontal inview_left', 'open_horizontal inview_right'),
	                ),
	            ),
	            array(
	                'type'       => 'checkbox',
	                'heading'    => esc_html__('Disable Dots', 'codevz-plus'),
	                'param_name' => 'menu_disable_dots',
					'edit_field_class' => 'vc_col-xs-99',
	            ),
	            array(
	                'type'       => 'cz_icon',
	                'heading'    => esc_html__('Indicator', 'codevz-plus'),
	                'param_name' => 'indicator_icon',
					'edit_field_class' => 'vc_col-xs-99',
	            ),
	            array(
	                'type'       => 'cz_icon',
	                'heading'    => esc_html__('Indicator Inner', 'codevz-plus'),
	                'param_name' => 'indicator_icon2',
					'edit_field_class' => 'vc_col-xs-99',
	            ),

	            // Sk.
				array(
					'type' 			=> 'cz_sk',
					'param_name' 	=> 'sk_menu_icon',
					'hover_id'		=> 'sk_menu_icon_hover',
					"heading"     	=> esc_html__( "Icon", 'codevz-plus' ),
					'button' 		=> esc_html__( "Icon", 'codevz-plus' ),
					'group' 		=> esc_html__( "Styling", 'codevz-plus' ),
					'edit_field_class' => 'vc_col-xs-99',
					'settings' 		=> array( 'color', 'background', 'border' )
				),
				array( 'type' => 'cz_hidden','param_name' => 'sk_menu_icon_mobile' ),
				array( 'type' => 'cz_hidden','param_name' => 'sk_menu_icon_tablet' ),
				array( 'type' => 'cz_hidden','param_name' => 'sk_menu_icon_hover' ),
				array(
					'type' 			=> 'cz_sk',
					'param_name' 	=> 'sk_menu_title',
					'hover_id'		=> 'sk_menu_title_hover',
					"heading"     	=> esc_html__( "Title", 'codevz-plus' ),
					'button' 		=> esc_html__( "Title", 'codevz-plus' ),
					'group' 		=> esc_html__( "Styling", 'codevz-plus' ),
					'edit_field_class' => 'vc_col-xs-99',
					'settings' 		=> array( 'color', 'background', 'border' )
				),
				array( 'type' => 'cz_hidden','param_name' => 'sk_menu_title_mobile' ),
				array( 'type' => 'cz_hidden','param_name' => 'sk_menu_title_tablet' ),
				array( 'type' => 'cz_hidden','param_name' => 'sk_menu_title_hover' ),
				array(
					'type' 			=> 'cz_sk',
					'param_name' 	=> 'sk_menu_con',
					"heading"     	=> esc_html__( "Container", 'codevz-plus' ),
					'button' 		=> esc_html__( "Container", 'codevz-plus' ),
					'group' 		=> esc_html__( "Styling", 'codevz-plus' ),
					'edit_field_class' => 'vc_col-xs-99',
					'settings' 		=> array( 'background', 'border' )
				),
				array( 'type' => 'cz_hidden','param_name' => 'sk_menu_con_mobile' ),
				array( 'type' => 'cz_hidden','param_name' => 'sk_menu_con_tablet' ),
				array(
					'type' 			=> 'cz_sk',
					'param_name' 	=> 'sk_menu_li',
					"heading"     	=> esc_html__( "Menus li", 'codevz-plus' ),
					'button' 		=> esc_html__( "Menus li", 'codevz-plus' ),
					'group' 		=> esc_html__( "Styling", 'codevz-plus' ),
					'edit_field_class' => 'vc_col-xs-99',
					'settings' 		=> array( 'background', 'border' )
				),
				array( 'type' => 'cz_hidden','param_name' => 'sk_menu_li_mobile' ),
				array( 'type' => 'cz_hidden','param_name' => 'sk_menu_li_tablet' ),
				array(
					'type' 			=> 'cz_sk',
					'param_name' 	=> 'sk_menu_a',
					'hover_id'		=> 'sk_menu_a_hover',
					"heading"     	=> esc_html__( "Menus", 'codevz-plus' ),
					'button' 		=> esc_html__( "Menus", 'codevz-plus' ),
					'group' 		=> esc_html__( "Styling", 'codevz-plus' ),
					'edit_field_class' => 'vc_col-xs-99',
					'settings' 		=> array( 'color', 'background', 'border' )
				),
				array( 'type' => 'cz_hidden','param_name' => 'sk_menu_a_mobile' ),
				array( 'type' => 'cz_hidden','param_name' => 'sk_menu_a_tablet' ),
				array( 'type' => 'cz_hidden','param_name' => 'sk_menu_a_hover' ),
				array(
					'type' 			=> 'cz_sk',
					'param_name' 	=> 'sk_menu_shape',
					"heading"     	=> esc_html__( "Shape", 'codevz-plus' ),
					'button' 		=> esc_html__( "Shape", 'codevz-plus' ),
					'group' 		=> esc_html__( "Styling", 'codevz-plus' ),
					'edit_field_class' => 'vc_col-xs-99',
					'settings' 		=> array( 'background', 'border' )
				),
				array( 'type' => 'cz_hidden','param_name' => 'sk_menu_shape_mobile' ),
				array( 'type' => 'cz_hidden','param_name' => 'sk_menu_shape_tablet' ),
				array(
					'type' 			=> 'cz_sk',
					'param_name' 	=> 'sk_menu_subtitle',
					'hover_id'		=> 'sk_menu_subtitle_hover',
					"heading"     	=> esc_html__( "Subtitle", 'codevz-plus' ),
					'button' 		=> esc_html__( "Subtitle", 'codevz-plus' ),
					'group' 		=> esc_html__( "Styling", 'codevz-plus' ),
					'edit_field_class' => 'vc_col-xs-99',
					'settings' 		=> array( 'color', 'background', 'border' )
				),
				array( 'type' => 'cz_hidden','param_name' => 'sk_menu_subtitle_mobile' ),
				array( 'type' => 'cz_hidden','param_name' => 'sk_menu_subtitle_tablet' ),
				array( 'type' => 'cz_hidden','param_name' => 'sk_menu_subtitle_hover' ),
				array(
					'type' 			=> 'cz_sk',
					'param_name' 	=> 'sk_menu_icons',
					'hover_id'		=> 'sk_menu_icons_hover',
					"heading"     	=> esc_html__( "Icons", 'codevz-plus' ),
					'button' 		=> esc_html__( "Icons", 'codevz-plus' ),
					'group' 		=> esc_html__( "Styling", 'codevz-plus' ),
					'edit_field_class' => 'vc_col-xs-99',
					'settings' 		=> array( 'color', 'background', 'border' )
				),
				array( 'type' => 'cz_hidden','param_name' => 'sk_menu_icons_mobile' ),
				array( 'type' => 'cz_hidden','param_name' => 'sk_menu_icons_tablet' ),
				array( 'type' => 'cz_hidden','param_name' => 'sk_menu_icons_hover' ),
				array(
					'type' 			=> 'cz_sk',
					'param_name' 	=> 'sk_menu_indicator',
					'hover_id'		=> 'sk_menu_indicator_hover',
					"heading"     	=> esc_html__( "Indicator", 'codevz-plus' ),
					'button' 		=> esc_html__( "Indicator", 'codevz-plus' ),
					'group' 		=> esc_html__( "Styling", 'codevz-plus' ),
					'edit_field_class' => 'vc_col-xs-99',
					'settings' 		=> array( 'color', 'background', 'border' )
				),
				array( 'type' => 'cz_hidden','param_name' => 'sk_menu_indicator_mobile' ),
				array( 'type' => 'cz_hidden','param_name' => 'sk_menu_indicator_tablet' ),
				array( 'type' => 'cz_hidden','param_name' => 'sk_menu_indicator_hover' ),
				array(
					'type' 			=> 'cz_sk',
					'param_name' 	=> 'sk_menu_delimiter',
					"heading"     	=> esc_html__( "Delimiter", 'codevz-plus' ),
					'button' 		=> esc_html__( "Delimiter", 'codevz-plus' ),
					'group' 		=> esc_html__( "Styling", 'codevz-plus' ),
					'edit_field_class' => 'vc_col-xs-99',
					'settings' 		=> array( 'color', 'background', 'border' )
				),
				array( 'type' => 'cz_hidden','param_name' => 'sk_menu_delimiter_mobile' ),
				array( 'type' => 'cz_hidden','param_name' => 'sk_menu_delimiter_tablet' ),
				array(
					'type' 			=> 'cz_sk',
					'param_name' 	=> 'sk_menu_dropdown',
					"heading"     	=> esc_html__( "Dropdown", 'codevz-plus' ),
					'button' 		=> esc_html__( "Dropdown", 'codevz-plus' ),
					'group' 		=> esc_html__( "Styling", 'codevz-plus' ),
					'edit_field_class' => 'vc_col-xs-99',
					'settings' 		=> array( 'background', 'border' )
				),
				array( 'type' => 'cz_hidden','param_name' => 'sk_menu_dropdown_mobile' ),
				array( 'type' => 'cz_hidden','param_name' => 'sk_menu_dropdown_tablet' ),
				array(
					'type' 			=> 'cz_sk',
					'param_name' 	=> 'sk_menu_ul_a',
					'hover_id'		=> 'sk_menu_ul_a_hover',
					"heading"     	=> esc_html__( "Inner Menus", 'codevz-plus' ),
					'button' 		=> esc_html__( "Inner Menus", 'codevz-plus' ),
					'group' 		=> esc_html__( "Styling", 'codevz-plus' ),
					'edit_field_class' => 'vc_col-xs-99',
					'settings' 		=> array( 'color', 'background', 'border' )
				),
				array( 'type' => 'cz_hidden','param_name' => 'sk_menu_ul_a_mobile' ),
				array( 'type' => 'cz_hidden','param_name' => 'sk_menu_ul_a_tablet' ),
				array( 'type' => 'cz_hidden','param_name' => 'sk_menu_ul_a_hover' ),
				array(
					'type' 			=> 'cz_sk',
					'param_name' 	=> 'sk_menu_ul_a_indicator',
					'hover_id'		=> 'sk_menu_ul_a_indicator_hover',
					"heading"     	=> esc_html__( "Inner Idicator", 'codevz-plus' ),
					'button' 		=> esc_html__( "Inner Idicator", 'codevz-plus' ),
					'group' 		=> esc_html__( "Styling", 'codevz-plus' ),
					'edit_field_class' => 'vc_col-xs-99',
					'settings' 		=> array( 'color', 'background', 'border' )
				),
				array( 'type' => 'cz_hidden','param_name' => 'sk_menu_ul_indicator_a_mobile' ),
				array( 'type' => 'cz_hidden','param_name' => 'sk_menu_ul_indicator_a_tablet' ),
				array( 'type' => 'cz_hidden','param_name' => 'sk_menu_ul_indicator_a_hover' ),
				array(
					'type' 			=> 'cz_sk',
					'param_name' 	=> 'sk_menu_ul_ul',
					"heading"     	=> esc_html__( "3rd Level", 'codevz-plus' ),
					'button' 		=> esc_html__( "3rd Level", 'codevz-plus' ),
					'group' 		=> esc_html__( "Styling", 'codevz-plus' ),
					'edit_field_class' => 'vc_col-xs-99',
					'settings' 		=> array( 'background', 'border' )
				),
				array( 'type' => 'cz_hidden','param_name' => 'sk_menu_ul_ul_mobile' ),
				array( 'type' => 'cz_hidden','param_name' => 'sk_menu_ul_ul_tablet' ),
				array(
					'type' 			=> 'cz_sk',
					'param_name' 	=> 'sk_menu_inner_megamenu',
					"heading"     	=> esc_html__( "Megamenu", 'codevz-plus' ),
					'button' 		=> esc_html__( "Megamenu", 'codevz-plus' ),
					'group' 		=> esc_html__( "Styling", 'codevz-plus' ),
					'edit_field_class' => 'vc_col-xs-99',
					'settings' 		=> array( 'background', 'border' )
				),
				array( 'type' => 'cz_hidden','param_name' => 'sk_menu_inner_megamenu_mobile' ),
				array( 'type' => 'cz_hidden','param_name' => 'sk_menu_inner_megamenu_tablet' ),
				array(
					'type' 			=> 'cz_sk',
					'param_name' 	=> 'sk_menu_h6',
					"heading"     	=> esc_html__( "Megamenu Title", 'codevz-plus' ),
					'button' 		=> esc_html__( "Megamenu Title", 'codevz-plus' ),
					'group' 		=> esc_html__( "Styling", 'codevz-plus' ),
					'edit_field_class' => 'vc_col-xs-99',
					'settings' 		=> array( 'color', 'background', 'border' )
				),
				array( 'type' => 'cz_hidden','param_name' => 'sk_menu_a_h6_mobile' ),
				array( 'type' => 'cz_hidden','param_name' => 'sk_menu_a_h6_tablet' ),

				// Advanced
				array(
					'type' 			=> 'checkbox',
					'heading' 		=> esc_html__( 'Hide on Desktop?', 'codevz-plus' ),
					'param_name' 	=> 'hide_on_d',
					'edit_field_class' => 'vc_col-xs-4',
					'group' 		=> esc_html__( 'Advanced', 'codevz-plus' )
				), 
				array(
					'type' 			=> 'checkbox',
					'heading' 		=> esc_html__( 'Hide on Tablet?', 'codevz-plus' ),
					'param_name' 	=> 'hide_on_t',
					'edit_field_class' => 'vc_col-xs-4',
					'group' 		=> esc_html__( 'Advanced', 'codevz-plus' )
				), 
				array(
					'type' 			=> 'checkbox',
					'heading' 		=> esc_html__( 'Hide on Mobile?', 'codevz-plus' ),
					'param_name' 	=> 'hide_on_m',
					'edit_field_class' => 'vc_col-xs-4',
					'group' 		=> esc_html__( 'Advanced', 'codevz-plus' )
				),
				array(
					'type' 			=> 'cz_title',
					'param_name' 	=> 'cz_title',
					'class' 		=> '',
					'content' 		=> esc_html__( 'Parallax', 'codevz-plus' ),
					'group' 		=> esc_html__( 'Advanced', 'codevz-plus' )
				),
				array(
					"type"        	=> "dropdown",
					"heading"     	=> esc_html__( "Parallax", 'codevz-plus' ),
					"param_name"  	=> "parallax_h",
					'edit_field_class' => 'vc_col-xs-99',
					'value'		=> array(
						esc_html__( 'Select', 'codevz-plus' )						=> '',
						esc_html__( 'Disable', 'codevz-plus' )						=> 'o',
						esc_html__( 'Vertical', 'codevz-plus' )						=> 'v',
						esc_html__( 'Vertical + Mouse parallax', 'codevz-plus' )		=> 'vmouse',
						esc_html__( 'Horizontal', 'codevz-plus' )					=> 'true',
						esc_html__( 'Horizontal + Mouse parallax', 'codevz-plus' )	=> 'truemouse',
						esc_html__( 'Mouse parallax', 'codevz-plus' )				=> 'mouse',
					),
					"group"  		=> esc_html__( 'Advanced', 'codevz-plus' )
				),
				array(
					"type"        	=> "cz_slider",
					"heading"     	=> esc_html__( "Parallax speed", 'codevz-plus' ),
					"description"   => esc_html__( "Parallax is according to page scrolling", 'codevz-plus' ),
					'edit_field_class' => 'vc_col-xs-99',
					"param_name"  	=> "parallax",
					"value"  		=> "0",
					'options' 		=> array( 'unit' => '', 'step' => 1, 'min' => -50, 'max' => 50 ),
					'dependency'	=> array(
						'element'		=> 'parallax_h',
						'value'			=> array( 'v', 'vmouse', 'true', 'truemouse' )
					),
					"group"  		=> esc_html__( 'Advanced', 'codevz-plus' )
				),
				array(
					'type' 			=> 'checkbox',
					'heading' 		=> esc_html__( 'Stop when done', 'codevz-plus' ),
					'param_name' 	=> 'parallax_stop',
					'edit_field_class' => 'vc_col-xs-99',
					'dependency'	=> array(
						'element'		=> 'parallax_h',
						'value'			=> array( 'v', 'vmouse', 'true', 'truemouse' )
					),
					'group' 		=> esc_html__( 'Advanced', 'codevz-plus' )
				), 
				array(
					"type"        	=> "cz_slider",
					"heading"     	=> esc_html__("Mouse speed", 'codevz-plus' ),
					"description"   => esc_html__( "Mouse parallax is according to mouse move", 'codevz-plus' ),
					'edit_field_class' => 'vc_col-xs-99',
					"param_name"  	=> "mparallax",
					"value"  		=> "0",
					'options' 		=> array( 'unit' => '', 'step' => 1, 'min' => -30, 'max' => 30 ),
					'dependency'	=> array(
						'element'		=> 'parallax_h',
						'value'			=> array( 'vmouse', 'truemouse', 'mouse' )
					),
					"group"  		=> esc_html__( 'Advanced', 'codevz-plus' )
				),
			)
		);

		return $wpb ? vc_map( $settings ) : $settings;
	}

	/**
	 * Shortcode output
	 */
	public function out( $atts, $content = '' ) {
		$atts = Codevz_Plus::shortcode_atts( $this, $atts );

		// ID
		if ( ! $atts['id'] ) {
			$atts['id'] = Codevz_Plus::uniqid();
			$public = 1;
		}

		// Styles
		if( isset( $public ) || Codevz_Plus::$vc_editable || Codevz_Plus::$is_admin ) {
			$css_id = '#' . $atts['id'];

			$css_array = array(
				'sk_menu_icon' 			=> $css_id . ' .czi:not(.cz_close_popup)',
				'sk_menu_icon_hover' 	=> $css_id . ' .czi:not(.cz_close_popup):hover',
				'sk_menu_title' 		=> $css_id . ' .czi span',
				'sk_menu_title_hover' 	=> $css_id . ' .czi:hover span',
				'sk_menu_con' 			=> $css_id . ' .sf-menu',
				'sk_menu_li' 			=> $css_id . ' .sf-menu > .cz',
				'sk_menu_a' 			=> $css_id . ' .sf-menu > .cz > a',
				'sk_menu_a_hover' 		=> $css_id . ' .sf-menu > .cz > a:hover, ' . $css_id . ' .sf-menu > .cz:hover > a, ' . $css_id . ' .sf-menu > .cz.current_menu > a, ' . $css_id . ' .sf-menu > .current-menu-parent > a',
				'sk_menu_shape' 		=> $css_id . ' .sf-menu > .cz > a:before',
				'sk_menu_subtitle' 		=> $css_id . ' .sf-menu > .cz > a > .cz_menu_subtitle',
				'sk_menu_subtitle_hover' => $css_id . ' .sf-menu > .cz > a:hover > .cz_menu_subtitle, ' . $css_id . ' .sf-menu > .cz:hover > a > .cz_menu_subtitle, ' . $css_id . ' .sf-menu > .cz.current_menu > a > .cz_menu_subtitle, ' . $css_id . ' .sf-menu > .current-menu-parent > a > .cz_menu_subtitle',
				'sk_menu_icons' 		=> $css_id . ' .sf-menu > .cz > a span i',
				'sk_menu_icons_hover' 	=> $css_id . ' .sf-menu > .cz > a:hover span i, ' . $css_id . ' .sf-menu > .cz:hover > a span i, ' . $css_id . ' .sf-menu > .cz.current_menu > a span i, ' . $css_id . ' .sf-menu > .current-menu-parent > a span i',
				'sk_menu_indicator' 	=> $css_id . ' .sf-menu > .cz > a .cz_indicator',
				'sk_menu_indicator_hover' => $css_id . ' .sf-menu > .cz:hover > a .cz_indicator',
				'sk_menu_delimiter' 	=> $css_id . ' .sf-menu > .cz:after',
				'sk_menu_dropdown' 		=> $css_id . ' .sf-menu .cz .sub-menu:not(.cz_megamenu_inner_ul), ' . $css_id . ' .sf-menu .cz_megamenu_inner_ul .cz_megamenu_inner_ul',
				'sk_menu_ul_a' 			=> $css_id . ' .sf-menu .cz .cz a',
				'sk_menu_ul_a_hover' 	=> $css_id . ' .sf-menu .cz .cz a:hover, ' . $css_id . ' .sf-menu .cz .cz:hover > a, ' . $css_id . ' .sf-menu .cz .cz.current_menu > a, ' . $css_id . ' .sf-menu .cz .current_menu > .current_menu',
				'sk_menu_ul_a_indicator' => $css_id . ' .sf-menu .cz .cz a .cz_indicator',
				'sk_menu_ul_a_indicator_hover' => $css_id . ' .sf-menu .cz .cz:hover a .cz_indicator',
				'sk_menu_ul_ul' 		=> $css_id . ' .sf-menu .sub-menu .sub-menu:not(.cz_megamenu_inner_ul)',
				'sk_menu_inner_megamenu' => $css_id . ' .sf-menu .cz_parent_megamenu > [class^="cz_megamenu_"] > .cz, ' . $css_id . ' .sf-menu .cz_parent_megamenu > [class*=" cz_megamenu_"] > .cz',
				'sk_menu_h5' 			=> $css_id . ' .sf-menu .cz .cz h6',
			);

			$css 	= Codevz_Plus::sk_style( $atts, $css_array );
			$css_t 	= Codevz_Plus::sk_style( $atts, $css_array, '_tablet' );
			$css_m 	= Codevz_Plus::sk_style( $atts, $css_array, '_mobile' );

		} else {
			Codevz_Plus::load_font( $atts['sk_menu_title'] );
			Codevz_Plus::load_font( $atts['sk_menu_con'] );
			Codevz_Plus::load_font( $atts['sk_menu_a'] );
			Codevz_Plus::load_font( $atts['sk_menu_ul_a'] );
		}

		ob_start();
		echo $atts[ 'menu_icon' ] ? '<i class="' . esc_attr( $atts[ 'menu_icon' ] ) . '"></i>' : '';
		$menu_icon = ob_get_clean();

		ob_start();
		$type = empty( $atts['menu_type'] ) ? 'cz_menu_default' : $atts['menu_type'];
		if ( $type === 'offcanvas_menu_left' ) {
			$type = 'offcanvas_menu inview_left';
		} else if ( $type === 'offcanvas_menu_right' ) {
			$type = 'offcanvas_menu inview_right';
		}

		$menu_title = isset( $atts['menu_title'] ) ? do_shortcode( $atts['menu_title'] ) : '';
		$menu_icon_class = $menu_title ? ' icon_plus_text' : '';

		// Pos icon.
		if ( $atts[ 'menu_position' ] ) {
			$menu_icon_class .= ' ' . $atts[ 'menu_position' ];
		}

		// Add icon and mobile menu
		if ( $type && $type !== 'offcanvas_menu' && $type !== 'cz_menu_default' ) {
			echo '<i class="' . esc_attr( ( empty( $atts['menu_icon'][ 'value' ] ) ? 'fa fa-bars' : $atts['menu_icon'][ 'value' ] ) . ' icon_' . $type . $menu_icon_class ) . '"><span>' . esc_html( $menu_title ) . '</span></i>';
		}
		if ( $atts[ 'menu_type' ] != 'offcanvas_menu_left' && $atts[ 'menu_type' ] != 'offcanvas_menu_right' ) {
			$menu_icon_class .= ' hide';
		}
		echo '<i class="' . esc_attr( ( empty( $atts['menu_icon'][ 'value' ] ) ? 'fa fa-bars' : $atts['menu_icon'][ 'value' ] ) . ' icon_mobile_' . $type . $menu_icon_class ) . '"><span>' . esc_html( $menu_title ) . '</span></i>';

		// Default
		if ( empty( $atts['menu_location'] ) ) {
			$atts['menu_location'] = 'primary';
		}

		// Check for meta box and set one page instead primary
		$page_menu = Codevz_Plus::meta( 0, 'one_page' );
		if ( $atts['menu_location'] === 'primary' && $page_menu ) {
			$atts['menu_location'] = ( $page_menu === '1' ) ? 'one-page' : $page_menu;
		}

		// Disable three dots auto responsive
		$type .= empty( $atts['menu_disable_dots'] ) ? '' : ' cz-not-three-dots';

		// Indicators
		$indicator  = empty( $atts['indicator_icon'] ) ? '' : $atts['indicator_icon'];
		$indicator2  = empty( $atts['indicator_icon2'] ) ? '' : $atts['indicator_icon2'];

		// Pos.
		if ( $atts[ 'menu_position' ] ) {
			$type .= ' ' . $atts[ 'menu_position' ];
		}

		$rand = 'cz_menu_' . wp_rand( 111, 999 );

		// Menu
		wp_nav_menu(
			apply_filters( 'codevz_nav_menu',
				[
					'theme_location' 	=> esc_attr( $atts['menu_location'] ),
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
		if ( Codevz_Plus::$vc_editable ) {
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

		$out = ob_get_clean();

		// Classes
		$classes = array();
		$classes[] = $atts['id'];
		$classes[] = 'cz_menu';

		$out = '<div id="' . esc_attr( $atts['id'] ) . '"' . Codevz_Plus::classes( $atts, $classes ) . Codevz_Plus::data_stlye( $css, $css_t, $css_m ) . '>' . do_shortcode( $out ) . '</div>';

		return Codevz_Plus::_out( $atts, $out, false, $this->name );
	}

}