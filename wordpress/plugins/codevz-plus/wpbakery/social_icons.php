<?php if ( ! defined( 'ABSPATH' ) ) { die; } // Cannot access pages directly.

/**
 * Social Icons
 * 
 * @author Codevz
 * @link http://codevz.com/
 */

class Codevz_WPBakery_social_icons {

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
			'name'			=> esc_html__( 'Icon', 'codevz-plus' ),
			'description'	=> esc_html__( 'Icon(s) or Social icons', 'codevz-plus' ),
			'icon'			=> 'czi',
			'params'		=> array(
				array(
					'type' 			=> 'cz_sc_id',
					'param_name' 	=> 'id',
					'save_always' 	=> true
				),
				array(
					'type' => 'param_group',
					'heading' => esc_html__( 'Add icon(s)', 'codevz-plus' ),
					'param_name' => 'social',
					'params' => array(
						array(
							"type"        	=> "cz_icon",
							"heading"     	=> esc_html__("Icon", 'codevz-plus' ),
							'edit_field_class' => 'vc_col-xs-99',
							"param_name"  	=> "icon",
							'value' 		=> 'fa fa-facebook'
						),
						array(
							"type"        	=> "textfield",
							"heading"     	=> esc_html__("Title", 'codevz-plus' ),
							'edit_field_class' => 'vc_col-xs-99',
							"param_name"  	=> "title",
							'admin_label'	=> true
						),
						array(
							"type"        	=> "textfield",
							"heading"     	=> esc_html__("Link", 'codevz-plus' ),
							'edit_field_class' => 'vc_col-xs-99',
							"param_name"  	=> "link"
						),
						array(
							"type"        	=> "checkbox",
							"heading"     	=> esc_html__("Open in same page?", 'codevz-plus' ),
							'edit_field_class' => 'vc_col-xs-99',
							"param_name"  	=> "link_target"
						)
					)
				),
				array(
					'type' 			=> 'cz_title',
					'param_name' 	=> 'cz_title',
					'class' 		=> '',
					'content' 		=> esc_html__( 'Settings', 'codevz-plus' ),
				),
				array(
					'type'			=> 'dropdown',
					'heading'		=> esc_html__('Position', 'codevz-plus' ),
					'edit_field_class' => 'vc_col-xs-99',
					'param_name'	=> 'position',
					'value'			=> array(
						esc_html__( 'Left', 'codevz-plus' ) => 'tal',
						esc_html__( 'Center', 'codevz-plus' ) => 'tac',
						esc_html__( 'Right', 'codevz-plus' ) => 'tar',
					)
				),
				array(
					'type'			=> 'dropdown',
					'heading'		=> esc_html__('Tooltip?', 'codevz-plus' ),
					'description' 	=> esc_html__( 'StyleKit located in Theme Options > General > Colors & Styles', 'codevz-plus' ),
					'param_name'	=> 'tooltip',
					'edit_field_class' => 'vc_col-xs-99',
					'value'			=> array(
						esc_html__( 'Select', 'codevz-plus' ) 	=> '',
						esc_html__( 'Up', 'codevz-plus' ) 		=> 'cz_tooltip cz_tooltip_up',
						esc_html__( 'Down', 'codevz-plus' ) 		=> 'cz_tooltip cz_tooltip_down',
						esc_html__( 'Left', 'codevz-plus' ) 		=> 'cz_tooltip cz_tooltip_left',
						esc_html__( 'Right', 'codevz-plus' ) 	=> 'cz_tooltip cz_tooltip_right',
					),
				),
				array(
					'type'			=> 'dropdown',
					'heading'		=> esc_html__('Hover effect', 'codevz-plus' ),
					'param_name'	=> 'fx',
					'edit_field_class' => 'vc_col-xs-99',
					'value'			=> array(
						esc_html__( 'Select', 'codevz-plus' ) 			=> '',
						esc_html__( 'Zoom In', 'codevz-plus' ) 			=> 'cz_social_fx_0',
						esc_html__( 'Zoom Out', 'codevz-plus' ) 			=> 'cz_social_fx_1',
						esc_html__( 'Bottom to Top', 'codevz-plus' ) 	=> 'cz_social_fx_2',
						esc_html__( 'Top to Bottom', 'codevz-plus' ) 	=> 'cz_social_fx_3',
						esc_html__( 'Left to Right', 'codevz-plus' ) 	=> 'cz_social_fx_4',
						esc_html__( 'Right to Left', 'codevz-plus' ) 	=> 'cz_social_fx_5',
						esc_html__( 'Rotate', 'codevz-plus' ) 			=> 'cz_social_fx_6',
						esc_html__( 'Infinite Shake', 'codevz-plus' )	=> 'cz_social_fx_7',
						esc_html__( 'Infinite Wink', 'codevz-plus' ) 	=> 'cz_social_fx_8',
						esc_html__( 'Quick Bob', 'codevz-plus' ) 		=> 'cz_social_fx_9',
						esc_html__( 'Flip Horizontal', 'codevz-plus' ) 	=> 'cz_social_fx_10',
						esc_html__( 'Flip Vertical', 'codevz-plus' ) 	=> 'cz_social_fx_11',
					),
				),
				array(
					'type'			=> 'checkbox',
					'heading'		=> esc_html__('Inline title', 'codevz-plus' ),
					'param_name'	=> 'inline_title',
					'edit_field_class' => 'vc_col-xs-99',
				),
				array(
					'type'			=> 'dropdown',
					'heading'		=> esc_html__('Social icons color', 'codevz-plus' ),
					'edit_field_class' => 'vc_col-xs-99',
					'param_name'	=> 'color_mode',
					'value'			=> array(
						esc_html__( 'Select', 'codevz-plus' ) 						=> '',
						esc_html__( 'Original colors', 'codevz-plus' ) 				=> 'cz_social_colored',
						esc_html__( 'Original colors on hover', 'codevz-plus' ) 		=> 'cz_social_colored_hover',
						esc_html__( 'Original background', 'codevz-plus' ) 			=> 'cz_social_colored_bg',
						esc_html__( 'Original background on hover', 'codevz-plus' ) => 'cz_social_colored_bg_hover',
					)
				),

				array(
					'type' 			=> 'cz_title',
					'param_name' 	=> 'cz_title',
					'class' 		=> '',
					'content' 		=> esc_html__( 'Styling', 'codevz-plus' ),
				),
				array(
					'type' 			=> 'cz_sk',
					'param_name' 	=> 'sk_con',
					"heading"     	=> esc_html__( "Container", 'codevz-plus' ),
					'button' 		=> esc_html__( "Container", 'codevz-plus' ),
					'edit_field_class' => 'vc_col-xs-99',
					'settings' 		=> array( 'background', 'border', 'box-shadow' )
				),
				array( 'type' => 'cz_hidden','param_name' => 'sk_con_mobile' ),

				array(
					'type' 			=> 'cz_sk',
					'param_name' 	=> 'sk_icons',
					'hover_id'	 	=> 'sk_hover',
					"heading"     	=> esc_html__( "Icons", 'codevz-plus' ),
					'button' 		=> esc_html__( "Icons", 'codevz-plus' ),
					'edit_field_class' => 'vc_col-xs-99',
					'settings' 		=> array( 'width', 'color', 'background', 'border' )
				),
				array( 'type' => 'cz_hidden','param_name' => 'sk_icons_tablet' ),
				array( 'type' => 'cz_hidden','param_name' => 'sk_icons_mobile' ),
				array( 'type' => 'cz_hidden','param_name' => 'sk_hover' ),

				array(
					'type' 			=> 'cz_sk',
					'param_name' 	=> 'sk_inner_icon',
					'hover_id'	 	=> 'sk_inner_icon_hover',
					"heading"     	=> esc_html__( "Inner icons", 'codevz-plus' ),
					'button' 		=> esc_html__( "Inner icons", 'codevz-plus' ),
					'edit_field_class' => 'vc_col-xs-99',
					'settings' 		=> array( 'width', 'height', 'color', 'line-height', 'font-size', 'background', 'border' ),
					'dependency'	=> array(
						'element'		=> 'inline_title',
						'not_empty'		=> true
					),
				),
				array( 'type' => 'cz_hidden','param_name' => 'sk_inner_icon_tablet' ),
				array( 'type' => 'cz_hidden','param_name' => 'sk_inner_icon_mobile' ),
				array( 'type' => 'cz_hidden','param_name' => 'sk_inner_icon_hover' ),

				array(
					'type' 			=> 'cz_sk',
					'param_name' 	=> 'sk_title',
					'hover_id'	 	=> 'sk_title_hover',
					"heading"     	=> esc_html__( "Inline title", 'codevz-plus' ),
					'button' 		=> esc_html__( "Inline title", 'codevz-plus' ),
					'edit_field_class' => 'vc_col-xs-99',
					'settings' 		=> array( 'color', 'font-family', 'font-size' ),
					'dependency'	=> array(
						'element'		=> 'inline_title',
						'not_empty'		=> true
					),
				),
				array( 'type' => 'cz_hidden','param_name' => 'sk_title_tablet' ),
				array( 'type' => 'cz_hidden','param_name' => 'sk_title_mobile' ),
				array( 'type' => 'cz_hidden','param_name' => 'sk_title_hover' ),

				// Advanced
				array(
					'type' 			=> 'checkbox',
					'heading' 		=> esc_html__( 'Hide on Desktop?', 'codevz-plus' ),
					'param_name' 	=> 'hide_on_d',
					'edit_field_class' => 'vc_col-xs-3',
					'group' 		=> esc_html__( 'Advanced', 'codevz-plus' )
				), 
				array(
					'type' 			=> 'checkbox',
					'heading' 		=> esc_html__( 'Hide on Tablet?', 'codevz-plus' ),
					'param_name' 	=> 'hide_on_t',
					'edit_field_class' => 'vc_col-xs-3',
					'group' 		=> esc_html__( 'Advanced', 'codevz-plus' )
				), 
				array(
					'type' 			=> 'checkbox',
					'heading' 		=> esc_html__( 'Hide on Mobile?', 'codevz-plus' ),
					'param_name' 	=> 'hide_on_m',
					'edit_field_class' => 'vc_col-xs-3',
					'group' 		=> esc_html__( 'Advanced', 'codevz-plus' )
				),
				array(
					'type' 			=> 'checkbox',
					'heading' 		=> esc_html__( 'Center on Mobile?', 'codevz-plus' ),
					'param_name' 	=> 'center_on_mobile',
					'edit_field_class' => 'vc_col-xs-3',
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
						esc_html__( 'Select', 'codevz-plus' )					=> '',
						
						esc_html__( 'Vertical', 'codevz-plus' )					=> 'v',
						esc_html__( 'Vertical + Mouse parallax', 'codevz-plus' )		=> 'vmouse',
						esc_html__( 'Horizontal', 'codevz-plus' )				=> 'true',
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
				array(
					'type' 			=> 'cz_title',
					'param_name' 	=> 'cz_title',
					'class' 		=> '',
					'content' 		=> esc_html__( 'Animation & Class', 'codevz-plus' ),
					'group' 		=> esc_html__( 'Advanced', 'codevz-plus' )
				),
				Codevz_Plus::wpb_animation_tab( false ),
				array(
					'type' 			=> 'cz_sk',
					'param_name' 	=> 'sk_brfx',
					"heading"     	=> esc_html__( "Block Reveal", 'codevz-plus' ),
					'button' 		=> esc_html__( "Block Reveal", 'codevz-plus' ),
					'edit_field_class' => 'vc_col-xs-99 hidden',
					'group' 	=> esc_html__( 'Advanced', 'codevz-plus' ),
					'settings' 		=> array( 'background' )
				),
				array(
					"type"        	=> "cz_slider",
					"heading"     	=> esc_html__("Animation Delay", 'codevz-plus' ),
					"description" 	=> 'e.g. 500ms',
					"param_name"  	=> "anim_delay",
					'options' 		=> array( 'unit' => 'ms', 'step' => 100, 'min' => 0, 'max' => 5000 ),
					'edit_field_class' => 'vc_col-xs-6',
					'group' 		=> esc_html__( 'Advanced', 'codevz-plus' )
				),
				array(
					"type"        	=> "textfield",
					"heading"     	=> esc_html__( "Extra Class", 'codevz-plus' ),
					"param_name"  	=> "class",
					'edit_field_class' => 'vc_col-xs-6',
					'group' 		=> esc_html__( 'Advanced', 'codevz-plus' )
				),

			)

		);

		return $wpb ? vc_map( $settings ) : $settings;
	}

	/**
	 *
	 * Shortcode output
	 * 
	 * @return string
	 * 
	 */
	public function out( $atts, $content = '' ) {
		$atts = Codevz_Plus::shortcode_atts( $this, $atts );

		// ID
		if ( ! $atts['id'] ) {
			$atts['id'] = Codevz_Plus::uniqid();
			$public = 1;
		}

		// Styles
		if ( isset( $public ) || Codevz_Plus::$vc_editable || Codevz_Plus::$is_admin ) {
			$css_id = '#' . $atts['id'];
			$custom = $atts['anim_delay'] ? 'animation-delay:' . $atts['anim_delay'] . ';' : '';

			$css_array = array(
				'sk_brfx' 				=> $css_id . ':before',
				'sk_con' 				=> array( $css_id, $custom ),
				'sk_icons' 				=> $css_id . ' a',
				'sk_hover' 				=> $css_id . ' a:hover',
				'sk_inner_icon' 		=> $css_id . ' a i:before',
				'sk_inner_icon_hover' 	=> $css_id . ' a:hover i:before',
				'sk_title' 				=> $css_id . ' span',
				'sk_title_hover' 		=> $css_id . ' a:hover span'
			);

			$css 	= Codevz_Plus::sk_style( $atts, $css_array );
			$css_t 	= Codevz_Plus::sk_style( $atts, $css_array, '_tablet' );
			$css_m 	= Codevz_Plus::sk_style( $atts, $css_array, '_mobile' );

		} else {
			Codevz_Plus::load_font( $atts['sk_title'] );
		}

		// Title
		$inline_title = $atts['inline_title'] ? 'cz_social_inline_title' : '';

		// Social icons
		$social_icons = json_decode( urldecode( $atts[ 'social' ] ), true );
		
		$social = '';
		foreach( (array) $social_icons as $i ) {
			if ( empty( $i['icon'] ) ) {
				continue;
			}
			$i['title'] = empty( $i['title'] ) ? '#' : $i['title'];
			$social_class = 'cz-' . str_replace( Codevz_Plus::$social_fa_upgrade, '', $i['icon'] );
			$i['link'] = empty( $i['link'] ) ? '' : ' href="' . $i['link'] . '"';
			$target = empty( $i['link_target'] ) ? ' target="_blank" rel="noopener noreferrer" ' : '';
			$social .= '<a' . $i['link'] . ' class="' . $social_class . '"' . $target . ( $atts['tooltip'] ? 'data-' : '' ) . 'title="' . $i['title'] . '"' . ( $i['title'] ? ' aria-label="' . esc_attr( $i['title'] ) . '"' : '' ) . '><i class="' . $i['icon'] . '">' . ( $inline_title ? '<span class="ml10">' . $i['title'] . '</span>' : '' ) . '</i></a>';
		}

		// Classes
		$classes = array();
		$classes[] = $atts['id'];
		$classes[] = 'cz_social_icons cz_social clr';
		$classes[] = $inline_title;
		$classes[] = $atts['fx'];
		$classes[] = $atts['position'];
		$classes[] = $atts['color_mode'];
		$classes[] = $atts['tooltip'];
		$classes[] = $atts['center_on_mobile'] ? 'center_on_mobile' : '';

		// Out
		$out = '<div id="' . $atts['id'] . '"' . Codevz_Plus::classes( $atts, $classes ) . Codevz_Plus::data_stlye( $css, $css_t, $css_m ) . '>' . $social . '</div>';

		return Codevz_Plus::_out( $atts, $out, false, $this->name );
	}
}