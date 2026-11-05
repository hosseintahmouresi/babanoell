<?php if ( ! defined( 'ABSPATH' ) ) { die; } // Cannot access pages directly.

/**
 * Service Box
 * 
 * @author Codevz
 * @link http://codevz.com/
 */

class Codevz_WPBakery_service_box {

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
			'name'			=> esc_html__( 'Service Box', 'codevz-plus' ),
			'description'	=> esc_html__( 'Icon and text box', 'codevz-plus' ),
			'icon'			=> 'czi',
			'params'		=> array(
				array(
					'type' 			=> 'cz_sc_id',
					'param_name' 	=> 'id',
					'save_always' 	=> true
				),
				array(
					"type" 			=> "dropdown",
					"heading" 		=> esc_html__("Layout",'codevz-plus'),
					"param_name" 	=> "type",
					'edit_field_class' => 'vc_col-xs-99',
					"value" 	=> array(
						'Horizontal'	=>'horizontal',
						'Vertical'		=>'vertical'
					)
				),
				array(
					"type" => "dropdown",
					"heading" => esc_html__("Position",'codevz-plus'),
					"param_name" => "align",
					'edit_field_class' => 'vc_col-xs-99',
					"value" => array(
						esc_html__( "Default", 'codevz-plus' )		=> '',
						esc_html__( "Left",   'codevz-plus' )		=> 'left',
						esc_html__( "Right",  'codevz-plus' )		=> 'right'
					)
				),
				array(
					"type" => "textarea",
					"heading" => esc_html__("Title",'codevz-plus'),
					"param_name" => "title",
					'edit_field_class' => 'vc_col-xs-99',
					"value" => esc_html__("Your Title",'codevz-plus')
				),
				array(
					"type" 			=> "textarea_html",
					"heading" 		=> esc_html__("Description",'codevz-plus'),
					"param_name" 	=> "content",
					'edit_field_class' => 'vc_col-xs-99',
					"value" 		=> "Your company have been great at keeping me in work, they always line something else up."
				),
				array(
					"type" => "textfield",
					"heading" => esc_html__("Button",'codevz-plus'),
					"param_name" => "btn",
					'edit_field_class' => 'vc_col-xs-99'
				),
				array(
					"type" => "dropdown",
					"heading" => esc_html__("Button position",'codevz-plus'),
					"param_name" => "btn_pos",
					'edit_field_class' => 'vc_col-xs-99',
					"value" => array(
						esc_html__( "Default",'codevz-plus' )	=> '',
						esc_html__( "Left",'codevz-plus' )		=> 'left',
						esc_html__( "Center",'codevz-plus' )		=> 'center',
						esc_html__( "Right",'codevz-plus' )		=> 'right'
					),
					'dependency' => array(
						'element' => 'btn',
						'not_empty'=> true
					)
				),
				array(
					"type"        	=> "vc_link",
					"heading"     	=> esc_html__("Link", 'codevz-plus' ),
					"param_name"  	=> "link",
					'edit_field_class' => 'vc_col-xs-99',
				),
				array(
					"type" => "checkbox",
					"heading" => esc_html__("Link only button",'codevz-plus'),
					"param_name" => "link_only_btn",
					'edit_field_class' => 'vc_col-xs-99',
					'dependency' => array(
						'element' => 'btn',
						'not_empty'=> true
					)
				),

				// Icon
				array(
					'type' 			=> 'cz_title',
					'param_name' 	=> 'cz_title_icons',
					'class' 		=> '',
					'content' 		=> esc_html__( 'Icon', 'codevz-plus' )
				),
				array(
					"type" 			=> "dropdown",
					"holder" 		=> "div",
					"heading" 		=> esc_html__("Icon type",'codevz-plus'),
					"param_name" 	=> "style",
					'edit_field_class' => 'vc_col-xs-99',
					"value" 		=> array(
						esc_html__( "Icon",'codevz-plus')			=> 'style1',
						esc_html__( 'Hexagon Icon','codevz-plus')	=> 'style9',
						esc_html__( "Image",'codevz-plus')			=> 'style11',
						esc_html__( "Number",'codevz-plus')			=> 'style10',
					),
				),
				array(
					'type' 			=> 'cz_icon',
					'heading' 		=> esc_html__("Icon",'codevz-plus'),
					'param_name' 	=> 'icon',
					'value' 		=> 'fa fa-bolt',
					'edit_field_class' => 'vc_col-xs-99',
					'dependency'	=> array(
						'element'		=> 'style',
						'value'			=> array( 'style1','style9' )
					)
				),
				array(
					'type'			=> 'dropdown',
					'heading'		=> esc_html__('Hover effect?', 'codevz-plus' ),
					'param_name'	=> 'icon_fx',
					'edit_field_class' => 'vc_col-xs-99',
					'value'			=> array(
						esc_html__( 'Select', 'codevz-plus' ) 			=> '',
						esc_html__( 'Zoom In', 'codevz-plus' ) 			=> 'cz_sbi_fx_0',
						esc_html__( 'Zoom Out', 'codevz-plus' ) 			=> 'cz_sbi_fx_1',
						esc_html__( 'Bottom to Top', 'codevz-plus' ) 	=> 'cz_sbi_fx_2',
						esc_html__( 'Top to Bottom', 'codevz-plus' ) 	=> 'cz_sbi_fx_3',
						esc_html__( 'Left to Right', 'codevz-plus' ) 	=> 'cz_sbi_fx_4',
						esc_html__( 'Right to Left', 'codevz-plus' ) 	=> 'cz_sbi_fx_5',
						esc_html__( 'Rotate', 'codevz-plus' ) 			=> 'cz_sbi_fx_6',
						esc_html__( 'Shake', 'codevz-plus' )				=> 'cz_sbi_fx_7a',
						esc_html__( 'Shake Infinite', 'codevz-plus' )	=> 'cz_sbi_fx_7',
						esc_html__( 'Wink', 'codevz-plus' ) 				=> 'cz_sbi_fx_8a',
						esc_html__( 'Wink Infinite', 'codevz-plus' ) 	=> 'cz_sbi_fx_8',
						esc_html__( 'Quick Bob', 'codevz-plus' ) 		=> 'cz_sbi_fx_9a',
						esc_html__( 'Quick Bob Infinite', 'codevz-plus' )=> 'cz_sbi_fx_9',
						esc_html__( 'Flip Horizontal', 'codevz-plus' ) 	=> 'cz_sbi_fx_10',
						esc_html__( 'Flip Vertical', 'codevz-plus' ) 	=> 'cz_sbi_fx_11',
					)
				),
				array(
					'type' 			=> 'cz_sk',
					'param_name' 	=> 'sk_icon',
					'hover_id'	 	=> 'sk_icon_hover',
					"heading"     	=> esc_html__( "Icon styling", 'codevz-plus' ),
					'button' 		=> esc_html__( "Icon", 'codevz-plus' ),
					'edit_field_class' => 'vc_col-xs-99',
					'dependency'	=> array(
						'element'		=> 'style',
						'value'			=> array( 'style1','style9' )
					),
					'settings' 		=> array( 'color', 'background', 'border' )
				),
				array( 'type' => 'cz_hidden','param_name' => 'sk_icon_mobile' ),
				array( 'type' => 'cz_hidden','param_name' => 'sk_icon_hover' ),

				array(
					'type' 			=> 'cz_sk',
					'param_name' 	=> 'sk_icon_con',
					"heading"     	=> esc_html__( "Icon container", 'codevz-plus' ),
					'button' 		=> esc_html__( "Icon container", 'codevz-plus' ),
					'edit_field_class' => 'vc_col-xs-99',
					'dependency'	=> array(
						'element'		=> 'style',
						'value'			=> array( 'style1','style9' )
					),
					'settings' 		=> array( 'background', 'border', 'box-shadow' )
				),
				array( 'type' => 'cz_hidden','param_name' => 'sk_icon_con_mobile' ),

				array(
					"type" => "attach_image",
					"heading" => esc_html__("Image",'codevz-plus'),
					"param_name" => "image",
					"value" => "",
					'edit_field_class' => 'vc_col-xs-99',
					'dependency' => array(
						'element' 				=> 'style',
						'value' 	=> array( 'style11' )
					),
				),
				array(
					"type" => "attach_image",
					"heading" => esc_html__("Image hover",'codevz-plus'),
					"param_name" => "image_hover",
					"value" => "",
					'edit_field_class' => 'vc_col-xs-99',
					'dependency' => array(
						'element' 				=> 'style',
						'value' 	=> array( 'style11' )
					),
				),
				array(
					"type"        	=> "textfield",
					"heading"     	=> esc_html__("Image size", 'codevz-plus' ),
					"description"   => esc_html__('Enter image size (e.g: "thumbnail", "medium", "large", "full"), Alternatively enter size in pixels (e.g: 200x100 (Width x Height)).', 'codevz-plus' ),
					"value"  		=> "thumbnail",
					"param_name"  	=> "size",
					'edit_field_class' => 'vc_col-xs-99',
					'dependency' => array(
						'element' 				=> 'style',
						'value' 	=> array( 'style11' )
					),
				),
				array(
					'type' 			=> 'cz_sk',
					'param_name' 	=> 'sk_image',
					'hover_id'	 	=> 'sk_image_hover',
					"heading"     	=> esc_html__( "Image styling", 'codevz-plus' ),
					'button' 		=> esc_html__( "Image", 'codevz-plus' ),
					'edit_field_class' => 'vc_col-xs-99',
					'dependency' => array(
						'element' 	=> 'style',
						'value' 	=> array( 'style11' )
					),
					'settings' 		=> array( 'background', 'border' )
				),
				array( 'type' => 'cz_hidden','param_name' => 'sk_image_tablet' ),
				array( 'type' => 'cz_hidden','param_name' => 'sk_image_mobile' ),
				array( 'type' => 'cz_hidden','param_name' => 'sk_image_hover' ),

				array(
					"type" => "textfield",
					"heading" => esc_html__("Number",'codevz-plus'),
					"param_name" => "number",
					'edit_field_class' => 'vc_col-xs-99',
					"value" => "1",
					'dependency'	=> array(
						'element'		=> 'style',
						'value'			=> array( 'style10' )
					)
				),
				array(
					'type' 			=> 'cz_sk',
					'param_name' 	=> 'sk_num',
					'hover_id'	 	=> 'sk_num_hover',
					"heading"     	=> esc_html__( "Number styling", 'codevz-plus' ),
					'button' 		=> esc_html__( "Number", 'codevz-plus' ),
					'edit_field_class' => 'vc_col-xs-99',
					'dependency'	=> array(
						'element'		=> 'style',
						'value'			=> array( 'style10' )
					),
					'settings' 		=> array( 'color', 'font-family', 'font-size', 'background', 'border' )
				),
				array( 'type' => 'cz_hidden','param_name' => 'sk_num_mobile' ),
				array( 'type' => 'cz_hidden','param_name' => 'sk_num_hover' ),

				// Styling
				array(
					'type' 			=> 'cz_title',
					'param_name' 	=> 'cz_title_styling',
					'class' 		=> '',
					'content' 		=> esc_html__( 'Other Styling', 'codevz-plus' )
				),
				array(
					'type' 			=> 'cz_sk',
					'param_name' 	=> 'sk_overall',
					'hover_id'	 	=> 'sk_overall_hover',
					"heading"     	=> esc_html__( "Box container", 'codevz-plus' ),
					'button' 		=> esc_html__( "Box container", 'codevz-plus' ),
					'edit_field_class' => 'vc_col-xs-99',
					'settings' 		=> array( 'background', 'border', 'box-shadow' )
				),
				array( 'type' => 'cz_hidden','param_name' => 'sk_overall_mobile' ),
				array( 'type' => 'cz_hidden','param_name' => 'sk_overall_tablet' ),
				array( 'type' => 'cz_hidden','param_name' => 'sk_overall_hover' ),

				array(
					'type' 			=> 'cz_sk',
					'param_name' 	=> 'sk_title',
					'hover_id'	 	=> 'sk_title_hover',
					"heading"     	=> esc_html__( "Title styling", 'codevz-plus' ),
					'button' 		=> esc_html__( "Title", 'codevz-plus' ),
					'edit_field_class' => 'vc_col-xs-99',
					'settings' 		=> array( 'color', 'text-align', 'font-family', 'font-size', 'font-weight', 'line-height', 'letter-spacing', 'background', 'border', 'box-shadow', 'text-shadow' )
				),
				array( 'type' => 'cz_hidden','param_name' => 'sk_title_tablet' ),
				array( 'type' => 'cz_hidden','param_name' => 'sk_title_mobile' ),
				array( 'type' => 'cz_hidden','param_name' => 'sk_title_hover' ),

				array(
					'type' 			=> 'cz_sk',
					'param_name' 	=> 'sk_con',
					'hover_id'	 	=> 'sk_con_hover',
					"heading"     	=> esc_html__( "Content styling", 'codevz-plus' ),
					'button' 		=> esc_html__( "Content", 'codevz-plus' ),
					'edit_field_class' => 'vc_col-xs-99',
					'settings' 		=> array( 'background', 'border', 'box-shadow' )
				),
				array( 'type' => 'cz_hidden','param_name' => 'sk_con_mobile' ),
				array( 'type' => 'cz_hidden','param_name' => 'sk_con_tablet' ),
				array( 'type' => 'cz_hidden','param_name' => 'sk_con_hover' ),

				array(
					'type' 			=> 'cz_sk',
					'param_name' 	=> 'sk_button',
					'hover_id'	 	=> 'sk_button_hover',
					"heading"     	=> esc_html__( "Button", 'codevz-plus' ),
					'button' 		=> esc_html__( "Button", 'codevz-plus' ),
					'edit_field_class' => 'vc_col-xs-99',
					'settings' 		=> array( 'color', 'background', 'border', 'box-shadow' )
				),
				array( 'type' => 'cz_hidden','param_name' => 'sk_button_mobile' ),
				array( 'type' => 'cz_hidden','param_name' => 'sk_button_tablet' ),
				array( 'type' => 'cz_hidden','param_name' => 'sk_button_hover' ),

				// Separator
				array(
					'type'			=> 'dropdown',
					'heading'		=> esc_html__('Separator', 'codevz-plus' ),
					'edit_field_class' => 'vc_col-xs-99',
					'param_name'	=> 'separator',
					'value'			=> array(
						'Off' 	=> 'off',
						'Line' 	=> 'line',
						'Icon' 	=> 'icon',
					),
					'group' 		=> esc_html__( 'Separator', 'codevz-plus' ),
					'dependency' => array(
						'element' => 'type',
						'value'=>array('vertical')
					)
				),
				array(
					"type"        	=> "cz_icon",
					"heading"     	=> esc_html__("Icon", 'codevz-plus' ),
					"param_name"  	=> "sep_icon",
					'group' 		=> esc_html__( 'Separator', 'codevz-plus' ),
					'edit_field_class' => 'vc_col-xs-99',
					'dependency'	=> array(
						'element'		=> 'separator',
						'value'			=> array( 'icon')
					)
				),
				array(
					'type' 			=> 'cz_sk',
					'param_name' 	=> 'sk_sep',
					'hover_id'	 	=> 'sk_sep_hover',
					"heading"     	=> esc_html__( "Icon styling", 'codevz-plus' ),
					'button' 		=> esc_html__( "Icon separator", 'codevz-plus' ),
					'edit_field_class' => 'vc_col-xs-99',
					'group' 		=> esc_html__( 'Separator', 'codevz-plus' ),
					'dependency'	=> array(
						'element'		=> 'separator',
						'value'			=> array( 'icon')
					),
					'settings' 		=> array( 'color', 'background', 'border' )
				),
				array( 'type' => 'cz_hidden','param_name' => 'sk_sep_mobile' ),
				array( 'type' => 'cz_hidden','param_name' => 'sk_sep_hover' ),

				array(
					'type' 			=> 'cz_sk',
					'param_name' 	=> 'sk_line',
					'hover_id'	 	=> 'sk_line_hover',
					"heading"     	=> esc_html__( "Line", 'codevz-plus' ),
					'button' 		=> esc_html__( "Line", 'codevz-plus' ),
					'group' 		=> esc_html__( 'Separator', 'codevz-plus' ),
					'edit_field_class' => 'vc_col-xs-99',
					'dependency'	=> array(
						'element'		=> 'separator',
						'value'			=> array( 'line')
					),
					'edit_field_class' => 'vc_col-xs-99',
					'settings' 		=> array( 'width', 'height', 'background', 'border' )
				),
				array( 'type' => 'cz_hidden','param_name' => 'sk_line_mobile' ),
				array( 'type' => 'cz_hidden','param_name' => 'sk_line_hover' ),

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
					'type' 			=> 'cz_title',
					'param_name' 	=> 'cz_title',
					'class' 		=> '',
					'content' 		=> esc_html__( 'Hover Effect', 'codevz-plus' ),
					'group' 		=> esc_html__( 'Advanced', 'codevz-plus' )
				),
				array(
					'type' => 'dropdown',
					'heading' => esc_html__( '~ Default ~', 'codevz-plus' ),
					'param_name' => 'fx',
					'value'		=> Codevz_Plus::fx(),
					'edit_field_class' => 'vc_col-xs-99',
					'group' => esc_html__( 'Advanced', 'codevz-plus' ),
				),
				array(
					'type' => 'dropdown',
					'heading' => esc_html__( 'Hover', 'codevz-plus' ),
					'param_name' => 'fx_hover',
					'value'		=> Codevz_Plus::fx( '_hover' ),
					'edit_field_class' => 'vc_col-xs-99',
					'group' => esc_html__( 'Advanced', 'codevz-plus' ),

				),
				array(
					'type' 			=> 'cz_title',
					'param_name' 	=> 'cz_title',
					'class' 		=> '',
					'content' 		=> esc_html__( 'Responsive', 'codevz-plus' ),
					'group' 		=> esc_html__( 'Advanced', 'codevz-plus' )
				),
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

	public function out( $atts, $content = '' ) {
		$atts = Codevz_Plus::shortcode_atts( $this, $atts );

		// ID
		if ( ! $atts['id'] ) {
			$atts['id'] = Codevz_Plus::uniqid();
			$public = 1;
		}

		// Style
		$style = $atts['style'];

		// Styles
		if ( isset( $public ) || Codevz_Plus::$vc_editable || Codevz_Plus::$is_admin ) {
			$css_id = '#' . $atts['id'];
			$custom = $atts['anim_delay'] ? 'animation-delay:' . $atts['anim_delay'] . ';' : '';

			$css_array = array(
				'sk_ir' 			=> $css_id,
				'sk_brfx' 			=> $css_id . ':before',
				'sk_overall' 		=> array( $css_id, $custom ),
				'sk_overall_hover' 	=> $css_id . ':hover',
				'sk_con' 			=> $css_id . ' .service_text',
				'sk_con_hover' 		=> $css_id . ':hover .service_text',
				'sk_title' 			=> $css_id . ' h3,' . $css_id . ' h3 a',
				'sk_title_hover' 	=> $css_id . ':hover h3,' . $css_id . ':hover h3 a',
				'sk_button' 		=> $css_id . ' .cz_btn',
				'sk_button_hover' 	=> $css_id . ':hover .cz_btn',
				'sk_num' 			=> $css_id . ' .service_number',
				'sk_num_hover' 		=> $css_id . ':hover .service_number',
				'sk_line' 			=> $css_id . ' .cz_sb_sep_line',
				'sk_line_hover' 	=> $css_id . ':hover .cz_sb_sep_line',
				'sk_sep' 			=> $css_id . ' .cz_sb_sep_icon',
				'sk_sep_hover' 		=> $css_id . ':hover .cz_sb_sep_icon',
				'sk_icon' 			=> $css_id . ( ( $style == 'style9' ) ? ' .cz_hexagon' :  ' i:not(.cz_sb_sep_icon)' ),
				'sk_icon_hover' 	=> $css_id . ':hover' . ( ( $style == 'style9' ) ? ' .cz_hexagon' :  ' i' ),
				'sk_icon_con' 		=> $css_id . ' .service_custom',
				'sk_image' 			=> $css_id . ' .service_img:not(.service_number)',
				'sk_image_hover' 	=> $css_id . ':hover .service_img:not(.service_number)',
			);

			// Inverted radius css.
			$atts = Codevz_WPBakery::inverted_radius_css( $atts );

			$css 	= Codevz_Plus::sk_style( $atts, $css_array );
			$css_t 	= Codevz_Plus::sk_style( $atts, $css_array, '_tablet' );
			$css_m 	= Codevz_Plus::sk_style( $atts, $css_array, '_mobile' );

		} else {
			Codevz_Plus::load_font( $atts['sk_title'] );
			Codevz_Plus::load_font( $atts['sk_num'] );
		}

		// Title
		$title = $atts['title'] ? '<h3>' . str_replace( '-br-', '<br />', do_shortcode( Codevz_Plus::fix_extra_p( $atts['title'] ) ) ) . '</h3>' : '';

		$return2 = '';
		if ( $style === 'style10' ) {
			$return2 = '<div class="service_img service_number">' . $atts['number'] . '</div>' ;
		} else {
			if ( $atts['image'] ) {
			  	$img = Codevz_Plus::get_image( $atts['image'], $atts['size'], 0, 'service-icon' );

			  	$img_have_hover = '';
			  	if ( $atts['image_hover'] ) {
			  		$img .= Codevz_Plus::get_image( $atts['image_hover'], $atts['size'], 0, 'service-icon' );
			  		$img_have_hover = ' services_img_have_hover';
			  	}
				
				$return2 = '<div class="service_img' . $img_have_hover . '">' . $img . '</div>' ;
			} else if ( $atts['icon'] ){
				if ( $style == 'style9' ) {
					$return2 = '<div class="cz_hexagon service_custom"><i class="' . $atts['icon'] . '"></i></div>';
				} else {
					$return2 = '<i class="' . $atts['icon'] . '"></i>';
				}
			}
		}

		if ( $style == 'style1' ) {
			$return2 = '<div class="service_custom">' . $return2 .'</div>';
		}

		// Content
		$content = do_shortcode( Codevz_Plus::fix_extra_p( $content ) );

		// Separator
		$separator = '';
		if ( $atts['separator'] === 'line' ) {
			$separator = '<span class="cz_sb_sep_line bar"></span>';
		} else if ( $atts['separator'] === 'icon' ) {
			$separator = '<i class="cz_sb_sep_icon ' . $atts['sep_icon'] . '"></i>';
		}

		// Link 
		$a_attr = $atts['link'] ? Codevz_Plus::link_attrs( $atts['link'] ) : '';

		// Button
		$btn = '';
		if ( $atts['btn'] ) {
			$btn_pos = $atts['btn_pos'] ? ' xtra-service-btn-' . $atts['btn_pos'] : '';
			$btn = $atts['link_only_btn'] ? '<a' . $a_attr . ' class="cz_btn' . $btn_pos . '">' . $atts['btn'] . '</a>' : '<div' . $a_attr . ' class="cz_btn' . $btn_pos . '">' . $atts['btn'] . '</div>';
		}

		// Classes
		$classes = array();
		$classes[] = $atts['id'];
		$classes[] = 'services clr';
		$classes[] = $style;
		$classes[] = $atts['icon_fx'] ? $atts['icon_fx'] : '';
		$classes[] = empty( $atts['ir_position'] ) ? '' : 'codevz-plus-inverted-radius-' . esc_attr( $atts['ir_position'] );

		if ( $atts['type'] === 'vertical' && $atts['align'] ) {
			$return2 .= '<div class="clr"></div>';
		}

		// Type
		if ( $atts['type'] === 'vertical' ) {
			$classes[] = 'services_b';
			$classes[] = $atts['align'];
			$return = '<div id="' . $atts['id'] . '"' . Codevz_Plus::classes( $atts, $classes ) . Codevz_Plus::data_stlye( $css, $css_t, $css_m ) . '>';
			$return .= $return2 . '<div class="service_text">' . $title . $separator . '<div class="cz_wpe_content">' . $content . '</div>' . $btn . '</div></div>';
		} else {
			$classes[] = $atts['align'] ? $atts['align'] : 'left';
			$return = '<div id="' . $atts['id'] . '"' . Codevz_Plus::classes( $atts, $classes ) . Codevz_Plus::data_stlye( $css, $css_t, $css_m ) . '>';
			$return .= $return2 . '<div class="service_text">' . $title . '<div class="cz_wpe_content">' . $content . '</div>' . $btn . '</div></div>';
		}

		// Out
		$out = $atts['fx'] ? '<div class="xtra-service-box ' . $atts['fx'] . '">' : '';
		$out .= $atts['fx_hover'] ? '<div class="xtra-service-box-hover ' . $atts['fx_hover'] . '">' : '';

		if ( $a_attr && ! $atts['link_only_btn'] ) {
			$return = '<a' . $a_attr . '>' . preg_replace( '/<a .*?<\/a>/', '', $return ) . '</a>';
		}

		$out .= $return;
		$out .= $atts['fx_hover'] ? '</div>' : '';
		$out .= $atts['fx'] ? '</div>' : '';

		return Codevz_Plus::_out( $atts, $out, false, $this->name, 'cz_button' );
	}
}