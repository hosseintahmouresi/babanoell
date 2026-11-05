<?php if ( ! defined( 'ABSPATH' ) ) { die; } // Cannot access pages directly.

/**
 * CS Free line
 * 
 * @author Codevz
 * @link http://codevz.com/
 */

class Codevz_WPBakery_free_line {

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
			'name'			=> esc_html__( 'Free Line', 'codevz-plus' ),
			'description'	=> esc_html__( 'Rotatable stylish line', 'codevz-plus' ),
			'icon'			=> 'czi',
			'params'		=> array(
				array(
					'type' 			=> 'cz_sc_id',
					'param_name' 	=> 'id',
					'save_always' 	=> true
				),
				array(
					"type"        	=> "textfield",
					"heading"     	=> esc_html__("Title", 'codevz-plus' ),
					"param_name"  	=> "title",
					'edit_field_class' => 'vc_col-xs-99',
				),
				array(
					'type' => 'dropdown',
					'heading' => esc_html__( 'Circles type', 'codevz-plus' ),
					'edit_field_class' => 'vc_col-xs-99',
					'param_name' => 'circles',
					'value'		=> array(
						esc_html__( 'Select', 'codevz-plus' )		=> '',
						esc_html__( 'Before', 'codevz-plus' )		=> 'cz_line_before_circle',
						esc_html__( 'After', 'codevz-plus' )			=> 'cz_line_after_circle',
						esc_html__( 'Both Sides', 'codevz-plus' )	=> 'cz_line_before_circle cz_line_after_circle',
					)
				),
				array(
					"type"        	=> "dropdown",
					"heading"     	=> esc_html__("Line position", 'codevz-plus' ),
					"description"   => esc_html__("Only works when line have custom width", 'codevz-plus' ),
					"param_name"  	=> "position",
					'edit_field_class' => 'vc_col-xs-99',
					'value'		=> array(
						esc_html__('Left', 'codevz-plus' ) 		=> 'tal',
						esc_html__('Center', 'codevz-plus' ) 	=> 'tac',
						esc_html__('Right', 'codevz-plus' ) 	=> 'tar',
						esc_html__('Left (Center in Small Devices)', 'codevz-plus' ) 	=> 'tal tac_in_mobile',
						esc_html__('Right (Center in Small Devices)', 'codevz-plus' ) 	=> 'tar tac_in_mobile',
					)
				),
				array(
					'type' 			=> 'cz_sk',
					'param_name' 	=> 'sk_line',
					"heading"     	=> esc_html__( "Line", 'codevz-plus' ),
					'button' 		=> esc_html__( "Line", 'codevz-plus' ),
					'edit_field_class' => 'vc_col-xs-99',
					'settings' 		=> array( 'width', 'height', 'transform', 'top', 'right', 'bottom', 'left', 'background' )
				),
				array( 'type' => 'cz_hidden','param_name' => 'sk_line_tablet' ),
				array( 'type' => 'cz_hidden','param_name' => 'sk_line_mobile' ),
				array(
					'type' 			=> 'cz_sk',
					'param_name' 	=> 'sk_title',
					"heading"     	=> esc_html__( "Title", 'codevz-plus' ),
					'button' 		=> esc_html__( "Title", 'codevz-plus' ),
					'edit_field_class' => 'vc_col-xs-99',
					'settings' 		=> array( 'color', 'font-family', 'background', 'border' )
				),
				array( 'type' => 'cz_hidden','param_name' => 'sk_title_tablet' ),
				array( 'type' => 'cz_hidden','param_name' => 'sk_title_mobile' ),
				array(
					'type' 			=> 'cz_sk',
					'param_name' 	=> 'sk_circles',
					"heading"     	=> esc_html__( "Circles", 'codevz-plus' ),
					'button' 		=> esc_html__( "Circles", 'codevz-plus' ),
					'edit_field_class' => 'vc_col-xs-99',
					'settings' 		=> array( 'width', 'height', 'top', 'right', 'bottom', 'left', 'background', 'border' )
				),

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

			$css_array = array(
				'sk_line' 		=> $css_id,
				'sk_brfx' 		=> $css_id . ':before',
				'sk_title' 		=> $css_id . ' .cz_line_title',
				'sk_circles' 	=> $css_id . ':before, ' . $css_id . ':after',
			);

			$css 	= Codevz_Plus::sk_style( $atts, $css_array );
			$css_t 	= Codevz_Plus::sk_style( $atts, $css_array, '_tablet' );
			$css_m 	= Codevz_Plus::sk_style( $atts, $css_array, '_mobile' );
		}

		// Animation
		$animation = '';
		if ( Codevz_Plus::contains( $atts['css_animation'], 'cz_brfx_' ) ) {
			
			// WPBakery old versions
			wp_enqueue_script( 'waypoints' );
			wp_enqueue_style( 'animate-css' );

			// WPBakery after v6.x
			wp_enqueue_script( 'vc_waypoints' );
			wp_enqueue_style( 'vc_animate-css' );
			
			$delay = $atts['anim_delay'] ? ' style="animation-delay:' . $atts['anim_delay'] . ';"' : '';
			$animation = ' class="relative wpb_animate_when_almost_visible wpb_' . $atts['css_animation'] . ' ' . $atts['css_animation'] . '"' . $delay;
			unset( $atts['css_animation'] );
		} else {
			$animation = ' class="relative"';
		}

		// Classes
		$classes = array();
		$classes[] = $atts['id'];
		$classes[] = 'cz_line';
		$classes[] = $atts['circles'];
		$classes[] = $atts['position'];

		// Title.
		$title = empty( $atts['title'] ) ? '' : '<div class="cz_line_title">' . do_shortcode( $atts['title'] ) . '</div>';

		// Out
		$out = '<div' . $animation . '><div id="' . esc_attr( $atts['id'] ) . '"' . Codevz_Plus::classes( $atts, $classes ) . Codevz_Plus::data_stlye( $css, $css_t, $css_m ) . '>' . wp_kses_post( $title ) . '</div></div><div class="clr"></div>';

		return Codevz_Plus::_out( $atts, $out, false, $this->name );
	}
}