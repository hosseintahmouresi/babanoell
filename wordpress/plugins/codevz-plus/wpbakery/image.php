<?php if ( ! defined( 'ABSPATH' ) ) { die; } // Cannot access pages directly.

/**
 * Image
 * 
 * @author Codevz
 * @link http://codevz.com/
 */

class Codevz_WPBakery_image {

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
			'name'			=> esc_html__( 'Image', 'codevz-plus' ), 
			'description'	=> esc_html__( 'Customizable image', 'codevz-plus' ),
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
					"edit_field_class" => 'vc_col-xs-99',
					'admin_label' 	=> true
				),
				array(
					'type' 			=> 'dropdown',
					'heading' 		=> esc_html__( 'Hover effect', 'codevz-plus' ),
					'param_name' 	=> 'fx_hover',
					"edit_field_class" => 'vc_col-xs-99',
					'value'			=> array(
						__( 'Select', 'codevz-plus' ) 			=> 'cz_image_no_fx',
						__( 'Simple Fade', 'codevz-plus' ) 		=> 'cz_image_simple_fade',
						__( 'Flip Horizontal', 'codevz-plus' ) 	=> 'cz_image_flip_h',
						__( 'Flip Vertical', 'codevz-plus' ) 		=> 'cz_image_flip_v',
						__( 'Fade To Top', 'codevz-plus' ) 		=> 'cz_image_fade_to_top',
						__( 'Fade To Bottom', 'codevz-plus' ) 	=> 'cz_image_fade_to_bottom',
						__( 'Fade To Left', 'codevz-plus' ) 		=> 'cz_image_fade_to_left',
						__( 'Fade To Right', 'codevz-plus' ) 		=> 'cz_image_fade_to_right',
						__( 'Zoom In', 'codevz-plus' ) 			=> 'cz_image_zoom_in',
						__( 'Zoom Out', 'codevz-plus' ) 			=> 'cz_image_zoom_out',
						__( 'Blurred', 'codevz-plus' ) 			=> 'cz_image_blurred',
					),
					'std' 			=> 'cz_image_no_fx'
				),
				array(
					"type"        	=> "attach_image",
					"heading"     	=> esc_html__("Hover Image", 'codevz-plus' ),
					"param_name"  	=> "hover_image",
					"edit_field_class" => 'vc_col-xs-99',
					'dependency'	=> array(
						'element' 				=> 'fx_hover',
						'value_not_equal_to' 	=> array( 'cz_image_no_fx' )
					)
				),
				array(
					"type"        	=> "textfield",
					"heading"     	=> esc_html__("Image size", 'codevz-plus' ),
					"description"   => esc_html__('Enter image size (e.g: "thumbnail", "medium", "large", "full"), Alternatively enter size in pixels (e.g: 200x100 (Width x Height)).', 'codevz-plus' ),
					"value"  		=> "full",
					"param_name"  	=> "size",
					"edit_field_class" => 'vc_col-xs-99',
					'admin_label' 	=> true
				),
				array(
					'type'			=> 'dropdown',
					'heading'		=> esc_html__('Image position', 'codevz-plus' ),
					'description'	=> esc_html__('Position will affect according to image width', 'codevz-plus' ),
					'edit_field_class' => 'vc_col-xs-99',
					'param_name'	=> 'css_position',
					'value'			=> array(
						'inline' 					=> 'relative',
						'block' 					=> 'relative;display: block;text-align:center',
						'Left' 						=> 'relative;float:left',
						'Center' 					=> 'relative;display: table;margin:0 auto',
						'Right' 					=> 'relative;float:right',
						'Left (Center in mobile)' 	=> 'relative;float:left;margin:0 auto',
						'Right (Center in mobile)' 	=> 'relative;float:right;margin:0 auto',
					)
				),
				array(
					"type"        	=> "cz_slider",
					"heading"     	=> esc_html__("Custom width", 'codevz-plus' ),
					'edit_field_class' => 'vc_col-xs-99',
					"param_name"  	=> "css_width",
					'options' 		=> array( 'unit' => 'px', 'step' => 1, 'min' => 50, 'max' => 500 ),
					"description"   => esc_html__('e.g. 200px or 60% according to parent width', 'codevz-plus' ),
				),
				array(
					"type" => "dropdown",
					"heading" => esc_html__("Link",'codevz-plus'),
					"param_name" => "link_type",
					'edit_field_class' => 'vc_col-xs-99',
					"value" => array(
						esc_html__('None', 'codevz-plus' ) 	=> 'none',
						esc_html__('Link to large image (Lightbox)', 'codevz-plus' ) => 'lightbox',
						esc_html__('Custom', 'codevz-plus' ) 	=> 'custom',
					)
				),
				array(
					"type"        	=> "vc_link",
					"heading"     	=> esc_html__("Link", 'codevz-plus' ),
					"param_name"  	=> "link",
					'edit_field_class' => 'vc_col-xs-99',
					'dependency'	=> array(
						'element'		=> 'link_type',
						'value'			=> array( 'custom')
					)
				),
				array(
					"type"        	=> "textfield",
					"heading"     	=> esc_html__("Tooltip", 'codevz-plus' ),
					'description' 	=> esc_html__( 'StyleKit located in Theme Options > General > Colors & Styles', 'codevz-plus' ),
					"edit_field_class" => 'vc_col-xs-99',
					"param_name"  	=> "tooltip"
				),

				array(
					'type' 			=> 'cz_title',
					'param_name' 	=> 'cz_title',
					'class' 		=> '',
					'content' 		=> esc_html__( 'Styling', 'codevz-plus' )
				),
				array(
					'type' 			=> 'cz_sk',
					'param_name' 	=> 'sk_css',
					'hover_id'	 	=> 'sk_css_hover',
					"heading"     	=> esc_html__( "Image styling", 'codevz-plus' ),
					'button' 		=> esc_html__( "Image", 'codevz-plus' ),
					"edit_field_class" => 'vc_col-xs-99',
					'settings' 		=> array( 'background', 'border', 'box-shadow', 'opacity' )
				),
				array( 'type' => 'cz_hidden','param_name' => 'sk_css_tablet' ),
				array( 'type' => 'cz_hidden','param_name' => 'sk_css_mobile' ),
				array( 'type' => 'cz_hidden','param_name' => 'sk_css_hover' ),
				array(
					'type' 			=> 'cz_sk',
					'param_name' 	=> 'svg_bg',
					"heading"     	=> esc_html__( "Background layer", 'codevz-plus' ),
					'button' 		=> esc_html__( "Background layer", 'codevz-plus' ),
					'edit_field_class' => 'vc_col-xs-99',
					'settings' 		=> array( 'svg', 'background', 'top', 'left', 'width', 'height' )
				),
				array( 'type' => 'cz_hidden','param_name' => 'svg_bg_tablet' ),
				array( 'type' => 'cz_hidden','param_name' => 'svg_bg_mobile' ),

				// Caption and Info box.
				array(
					'type'			=> 'dropdown',
					'heading'		=> esc_html__('Type', 'codevz-plus' ),
					'edit_field_class' => 'vc_col-xs-99',
					'param_name'	=> 'type',
					'value'			=> array(
						esc_html__( 'Caption', 'codevz-plus' ) 		=> '',
						esc_html__( 'Info Box', 'codevz-plus' ) 	=> '2',
					),
					'group' 		=> esc_html__( 'Caption / Info box', 'codevz-plus' )
				),
				array(
					'type'			=> 'dropdown',
					'heading'		=> esc_html__('Position', 'codevz-plus' ),
					'edit_field_class' => 'vc_col-xs-99',
					'param_name'	=> 'position',
					'value'			=> array(
						esc_html__( 'Top right', 'codevz-plus' ) 		=> 'top-right',
						esc_html__( 'Top left', 'codevz-plus' ) 		=> 'top-left',
						esc_html__( 'Bottom right', 'codevz-plus' ) 	=> 'bottom-right',
						esc_html__( 'Bottom left', 'codevz-plus' ) 		=> 'bottom-left',
					),
					'default' 		=> 'top-right',
					'group' 		=> esc_html__( 'Caption / Info box', 'codevz-plus' ),
					'dependency'	=> array(
						'element' 			=> 'type',
						'value'				=> array( '2' )
					)
				),
				array(
					"type" 			=> "textarea_html",
					"heading" 		=> esc_html__("Content",'codevz-plus'),
					"param_name" 	=> "content",
					'edit_field_class' => 'vc_col-xs-99',
					'group' 		=> esc_html__( 'Caption / Info box', 'codevz-plus' )
				),
				array(
					'type' 				=> 'checkbox',
					'heading' 			=> esc_html__( 'Sticky on mouse hover?', 'codevz-plus' ),
					'param_name' 		=> 'sticky_caption',
					'edit_field_class' 	=> 'vc_col-xs-99',
					'group' 			=> esc_html__( 'Caption / Info box', 'codevz-plus' ),
					'dependency'		=> array(
						'element'				=> 'type',
						'value_not_equal_to'	=> array( '2' )
					)
				),
				array(
					"type"        	=> "cz_icon",
					"heading"     	=> esc_html__("Icon", 'codevz-plus' ),
					"param_name"  	=> "icon",
					'value'			=> 'fa fa-info',
					'edit_field_class' => 'vc_col-xs-99',
					'group' 		=> esc_html__( 'Caption / Info box', 'codevz-plus' ),
					'dependency'	=> array(
						'element'		=> 'type',
						'value'			=> array( '2')
					)
				),
				array(
					'type' 			=> 'cz_sk',
					'param_name' 	=> 'sk_icon',
					'hover_id' 		=> 'sk_icon_hover',
					"heading"     	=> esc_html__( "Icon", 'codevz-plus' ),
					'button' 		=> esc_html__( "Icon", 'codevz-plus' ),
					"edit_field_class" => 'vc_col-xs-99',
					'group' 		=> esc_html__( 'Caption / Info box', 'codevz-plus' ),
					'dependency'	=> array(
						'element'		=> 'type',
						'value'			=> array( '2')
					),
					'settings' 		=> array( 'color', 'border', 'background' )
				),
				array( 'type' => 'cz_hidden','param_name' => 'sk_icon_tablet' ),
				array( 'type' => 'cz_hidden','param_name' => 'sk_icon_mobile' ),
				array( 'type' => 'cz_hidden','param_name' => 'sk_icon_hover' ),
				array(
					'type' 			=> 'cz_sk',
					'param_name' 	=> 'sk_caption',
					'hover_id' 		=> 'sk_caption_hover',
					"heading"     	=> esc_html__( "Container", 'codevz-plus' ),
					'button' 		=> esc_html__( "Container", 'codevz-plus' ),
					"edit_field_class" => 'vc_col-xs-99',
					'group' 		=> esc_html__( 'Caption / Info box', 'codevz-plus' ),
					'settings' 		=> array( 'border', 'background' )
				),
				array( 'type' => 'cz_hidden','param_name' => 'sk_caption_tablet' ),
				array( 'type' => 'cz_hidden','param_name' => 'sk_caption_mobile' ),
				array( 'type' => 'cz_hidden','param_name' => 'sk_caption_hover' ),

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
					'content' 		=> esc_html__( 'Tilt effect', 'codevz-plus' ),
					'group' 		=> esc_html__( 'Advanced', 'codevz-plus' )
				),
				array(
					"type"        	=> "dropdown",
					"heading"     	=> esc_html__("Tilt effect", 'codevz-plus' ),
					"param_name"  	=> "tilt",
					'edit_field_class' => 'vc_col-xs-99',
					'value'		=> array(
						'Off'	=> '',
						'On'	=> 'on',
					),
					"group"  		=> esc_html__( 'Advanced', 'codevz-plus' )
				),
				 array(
					"type" => "dropdown",
					"heading" => esc_html__("Glare",'codevz-plus'),
					"param_name" => "glare",
					"edit_field_class" => 'vc_col-xs-99',
					"value" => array( '0','0.2','0.4','0.6','0.8','1' ),
					'dependency'	=> array(
						'element'		=> 'tilt',
						'value'			=> array( 'on')
					),
					"group"  		=> esc_html__( 'Advanced', 'codevz-plus' )
				),
				array(
					"type" => "dropdown",
					"heading" => esc_html__("Scale",'codevz-plus'),
					"param_name" => "scale",
					"edit_field_class" => 'vc_col-xs-99',
					"value" 	=> array('0.9','0.8','1','1.1','1.2'),
					"std" 		=> '1',
					'dependency'	=> array(
						'element'		=> 'tilt',
						'value'			=> array( 'on')
					),
					"group"  		=> esc_html__( 'Advanced', 'codevz-plus' )
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

		// Fix hover
		if ( ! $atts['hover_image'] && $atts['fx_hover'] !== 'cz_image_no_fx' ) {
			$atts['hover_image'] = $atts['image'];
		}

		// Images
		$image = Codevz_Plus::get_image( $atts['image'], $atts['size'] );
		$hover_image = $atts['hover_image'] ? Codevz_Plus::get_image( $atts['hover_image'], $atts['size'] ) : '' ;

		// Link
		$a_before = $a_after = '';
		if ( $atts['link_type'] === 'lightbox' ) {
			$link = Codevz_Plus::get_image( $atts['image'], 0, 1 );
			$a_before = '<a href="' . $link . '" data-xtra-lightbox>';
			$a_after = '</a>';
		} else if ( $atts['link_type'] === 'custom' ) {
	 		$a_before .= '<a'. Codevz_Plus::link_attrs( $atts['link'] ) . '>';
	 		$a_after = '</a>';
		}

		// Tooltip
		$tooltip = $atts['tooltip'] ? ' data-title="' . $atts['tooltip'] . '"' : '';

		// Styles
		if ( isset( $public ) || Codevz_Plus::$vc_editable || Codevz_Plus::$is_admin ) {
			$css_id = '#' . $atts['id'];

			$css_array = array(
				'sk_ir' 			=> $css_id,
				'sk_brfx' 			=> $css_id . ':before',
				'sk_css' 			=> $css_id . ' .cz_image_in',
				'sk_css_hover' 		=> $css_id . ':hover .cz_image_in, .cz_box_front_inner:hover > div > ' . $css_id . ' .cz_image_in',
				'sk_caption' 		=> $css_id . ' .cz_image_caption',
				'sk_caption_hover' 	=> $css_id . ':hover .cz_image_caption, .cz_box_front_inner:hover > div > ' . $css_id . ' .cz_image_caption',
				'sk_icon' 			=> $css_id . ' > div > i',
				'sk_icon_hover' 	=> $css_id . ' > div > i:hover',
				'svg_bg' 			=> $css_id . ' .cz_svg_bg:before'
			);

			// Inverted radius css.
			$atts = Codevz_WPBakery::inverted_radius_css( $atts );

			$css 	= Codevz_Plus::sk_style( $atts, $css_array );
			$css_t 	= Codevz_Plus::sk_style( $atts, $css_array, '_tablet' );
			$css_m 	= Codevz_Plus::sk_style( $atts, $css_array, '_mobile' );

			$custom = $atts['css_width'] ? 'width:' . $atts['css_width'] . ';' : '';
			$custom .= $atts['css_position'] ? 'position:' . $atts['css_position'] . ';' : '';
			$css .= $custom ? $css_id . ' > div{' . $custom . '}' : '';

			$css .= $atts['anim_delay'] ? $css_id . '{animation-delay:' . $atts['anim_delay'] . '}' : '';

		}

		// Hover
		$hover_image_tag = $hover_image ? '<div class="cz_hover_image">' . $hover_image .'</div>' : '';

		// Classes
		$classes = array();
		$classes[] = $atts['id'];
		$classes[] = 'cz_image clr';
		$classes[] = $atts['fx_hover'];
		$classes[] = $atts['tooltip'] ? 'cz_tooltip_up' : '';
		$classes[] = $atts['type'] ? 'codevz-plus-image-info-box' : '';
		$classes[] = $atts['sticky_caption'] ? 'cz_image_caption_sticky' : '';
		$classes[] = ( $atts['css_position'] === 'relative' ) ? 'center_on_mobile' : '';
		$classes[] = Codevz_Plus::contains( $atts['css_position'], 'margin' ) ? 'center_on_mobile' : '';
		$classes[] = empty( $atts['ir_position'] ) ? '' : 'codevz-plus-inverted-radius-' . esc_attr( $atts['ir_position'] );

		$position = empty( $atts[ 'position' ] ) ? '' : $atts[ 'position' ];

		// Content.
		if ( $content ) {
			$content = Codevz_Plus::fix_extra_p( $content );
			$content = '<div class="cz_image_caption mt10 cz_wpe_content">' . ( function_exists( 'wpb_js_remove_wpautop' ) ? wpb_js_remove_wpautop( $content, true ) : do_shortcode( $content ) ) . '</div>';
		}

		// Info box icon.
		if ( $atts['type'] ) {
			$content = '<i class="' . $atts['icon'] . '"></i>' . $content;
		}

		// Out
		$out = $a_before . '<div id="' . $atts['id'] . '" data-position="' . esc_attr( $position ) . '"' . Codevz_Plus::classes( $atts, $classes ) . Codevz_Plus::data_stlye( $css, $css_t, $css_m ) . '><div class="' . ( $atts['svg_bg'] ? 'cz_svg_bg' : '' ) . '" ' . $tooltip . '><div class="cz_image_in"' . Codevz_Plus::tilt( $atts ) . '><div class="cz_main_image">' . $image . '</div>' . $hover_image_tag . '</div>' . $content . '</div></div>' . $a_after;

		return Codevz_Plus::_out( $atts, $out, [ 'image( "' . $atts['id'] . '" )', 'tilt' ], $this->name );
	}
}