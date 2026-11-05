<?php if ( ! defined( 'ABSPATH' ) ) { die; } // Cannot access pages directly.

/**
 * Image hover zoom
 * 
 * @author Codevz
 * @link http://codevz.com/
 */

class Codevz_WPBakery_image_hover_zoom {

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
			'name'			=> esc_html__( 'Image Hover Zoom', 'codevz-plus' ),
			'description'	=> esc_html__( 'Mouse hover zoom image', 'codevz-plus' ),
			'icon'			=> 'czi',
			'params'		=> array(
				array(
					'type' 			=> 'cz_sc_id',
					'param_name' 	=> 'id',
					'save_always' 	=> true
				),
				array(
					"type"        	=> "attach_image",
					"heading"     	=> esc_html__("Image", 'codevz-plus' ),
					"param_name"  	=> "image",
					'edit_field_class' => 'vc_col-xs-99',
					'admin_label' 	=> true
				),
				array(
					"type"        	=> "textfield",
					"heading"     	=> esc_html__("Image size", 'codevz-plus' ),
					"description"   => esc_html__('Enter image size (e.g: "thumbnail", "medium", "large", "full"), Alternatively enter size in pixels (e.g: 200x100 (Width x Height)).', 'codevz-plus' ),
					"value"  		=> "full",
					"param_name"  	=> "size",
					'edit_field_class' => 'vc_col-xs-99',
					'admin_label' 	=> true
				),

				array(
					'type' 			=> 'cz_sk',
					'param_name' 	=> 'sk_css',
					"heading"     	=> esc_html__( "Container styling", 'codevz-plus' ),
					'button' 		=> esc_html__( "Container", 'codevz-plus' ),
					'edit_field_class' => 'vc_col-xs-99',
					'settings' 		=> array( 'background', 'border', 'box-shadow' )
				),
				array(
					'type' 			=> 'cz_sk',
					'param_name' 	=> 'svg_bg',
					"heading"     	=> esc_html__( "Background layer", 'codevz-plus' ),
					'button' 		=> esc_html__( "Background layer", 'codevz-plus' ),
					'edit_field_class' => 'vc_col-xs-99',
					'settings' 		=> array( 'svg', 'background', 'top', 'left', 'width', 'height' )
				),

				// Inverted radius.
				array(
					"type" => "dropdown",
					"holder" => "div",
					"heading" => esc_html__("Position",'codevz-plus'),
					"param_name" => "ir_position",
					'edit_field_class' => 'vc_col-xs-99',
					"value" => array(
						esc_html__( "~ Select ~", 'codevz-plus' ) 	=> '',
						esc_html__( "Top left", 'codevz-plus' ) 	=> 'top-left',
						esc_html__( "Top right", 'codevz-plus' ) 	=> 'top-right',
						esc_html__( "Bottom left", 'codevz-plus' ) 	=> 'bottom-left',
						esc_html__( "Bottom right", 'codevz-plus' ) 	=> 'top-right',
					),
					'group' 		=> esc_html__( 'Inverted Radius', 'codevz-plus' )
				),
				array(
					"type"        	=> "cz_slider",
					"heading"     	=> esc_html__( "Radius", 'codevz-plus' ),
					'edit_field_class' => 'vc_col-xs-99',
					"param_name"  	=> "ir_radius",
					'options' 		=> array( 'unit' => 'px', 'step' => 1, 'min' => 0, 'max' => 100 ),
					'dependency'	=> array(
						'element'				=> 'ir_position',
						'value_not_equal_to'	=> array( '' )
					),
					'group' 		=> esc_html__( 'Inverted Radius', 'codevz-plus' )
				),
				array(
					"type"        	=> "cz_slider",
					"heading"     	=> esc_html__( "Size", 'codevz-plus' ),
					'edit_field_class' => 'vc_col-xs-99',
					"param_name"  	=> "ir_size",
					'options' 		=> array( 'unit' => 'px', 'step' => 1, 'min' => 0, 'max' => 150 ),
					'dependency'	=> array(
						'element'				=> 'ir_position',
						'value_not_equal_to'	=> array( '' )
					),
					'group' 		=> esc_html__( 'Inverted Radius', 'codevz-plus' )
				),
				array(
					"type"        	=> "cz_slider",
					"heading"     	=> esc_html__( "Offset X", 'codevz-plus' ),
					'edit_field_class' => 'vc_col-xs-99',
					"param_name"  	=> "ir_x",
					'options' 		=> array( 'unit' => 'px', 'step' => 1, 'min' => 0, 'max' => 400 ),
					'dependency'	=> array(
						'element'				=> 'ir_position',
						'value_not_equal_to'	=> array( '' )
					),
					'group' 		=> esc_html__( 'Inverted Radius', 'codevz-plus' )
				),
				array(
					"type"        	=> "cz_slider",
					"heading"     	=> esc_html__( "Offset Y", 'codevz-plus' ),
					'edit_field_class' => 'vc_col-xs-99',
					"param_name"  	=> "ir_y",
					'options' 		=> array( 'unit' => 'px', 'step' => 1, 'min' => 0, 'max' => 400 ),
					'dependency'	=> array(
						'element'				=> 'ir_position',
						'value_not_equal_to'	=> array( '' )
					),
					'group' 		=> esc_html__( 'Inverted Radius', 'codevz-plus' )
				),
				array(
					"type"        	=> "cz_slider",
					"heading"     	=> esc_html__( "Mobile", 'codevz-plus' ) . ' - ' . esc_html__( "Radius", 'codevz-plus' ),
					'edit_field_class' => 'vc_col-xs-99',
					"param_name"  	=> "ir_radius_mobile",
					'options' 		=> array( 'unit' => 'px', 'step' => 1, 'min' => 0, 'max' => 100 ),
					'dependency'	=> array(
						'element'				=> 'ir_position',
						'value_not_equal_to'	=> array( '' )
					),
					'group' 		=> esc_html__( 'Inverted Radius', 'codevz-plus' )
				),
				array(
					"type"        	=> "cz_slider",
					"heading"     	=> esc_html__( "Mobile", 'codevz-plus' ) . ' - ' . esc_html__( "Size", 'codevz-plus' ),
					'edit_field_class' => 'vc_col-xs-99',
					"param_name"  	=> "ir_size_mobile",
					'options' 		=> array( 'unit' => 'px', 'step' => 1, 'min' => 0, 'max' => 150 ),
					'dependency'	=> array(
						'element'				=> 'ir_position',
						'value_not_equal_to'	=> array( '' )
					),
					'group' 		=> esc_html__( 'Inverted Radius', 'codevz-plus' )
				),
				array(
					"type"        	=> "cz_slider",
					"heading"     	=> esc_html__( "Mobile", 'codevz-plus' ) . ' - ' . esc_html__( "Offset X", 'codevz-plus' ),
					'edit_field_class' => 'vc_col-xs-99',
					"param_name"  	=> "ir_x_mobile",
					'options' 		=> array( 'unit' => 'px', 'step' => 1, 'min' => 0, 'max' => 400 ),
					'dependency'	=> array(
						'element'				=> 'ir_position',
						'value_not_equal_to'	=> array( '' )
					),
					'group' 		=> esc_html__( 'Inverted Radius', 'codevz-plus' )
				),
				array(
					"type"        	=> "cz_slider",
					"heading"     	=> esc_html__( "Mobile", 'codevz-plus' ) . ' - ' . esc_html__( "Offset Y", 'codevz-plus' ),
					'edit_field_class' => 'vc_col-xs-99',
					"param_name"  	=> "ir_y_mobile",
					'options' 		=> array( 'unit' => 'px', 'step' => 1, 'min' => 0, 'max' => 400 ),
					'dependency'	=> array(
						'element'				=> 'ir_position',
						'value_not_equal_to'	=> array( '' )
					),
					'group' 		=> esc_html__( 'Inverted Radius', 'codevz-plus' )
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

		// Settings.
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
				'sk_css' 	=> array( $css_id, $custom ),
				'sk_ir' 	=> $css_id,
				'sk_brfx' 	=> $css_id . ':before',
				'svg_bg' 	=> $css_id . '.cz_svg_bg:before'
			);

			// Inverted radius css.
			$atts = Codevz_WPBakery::inverted_radius_css( $atts );

			$css = Codevz_Plus::sk_style( $atts, $css_array );
			$css_t = Codevz_Plus::sk_style( $atts, $css_array, '_tablet' );
			$css_m = Codevz_Plus::sk_style( $atts, $css_array, '_mobile' );
		}

		// Image
		$img = Codevz_Plus::get_image( $atts['image'], $atts['size'] );
		$link = Codevz_Plus::get_image( $atts['image'], 0, 1 );
		
		// Classes
		$classes = array();
		$classes[] = $atts['id'];
		$classes[] = 'cz_image_hover_zoom';
		$classes[] = $atts['svg_bg'] ? 'cz_svg_bg' : '';
		$classes[] = empty( $atts['ir_position'] ) ? '' : 'codevz-plus-inverted-radius-' . esc_attr( $atts['ir_position'] );

		// Out
		$out = '<div id="' . $atts['id'] . '"' . Codevz_Plus::classes( $atts, $classes ) . Codevz_Plus::data_stlye( $css, $css_t, $css_m ) . '><div><a href="' . $link . '" data-xtra-lightbox>' . $img . '</a></div></div>';

		return Codevz_Plus::_out( $atts, $out, [ 'image_zoom' ], $this->name );
	}
}