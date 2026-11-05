<?php if ( ! defined( 'ABSPATH' ) ) { die; } // Cannot access pages directly.

/**
 * Title
 * 
 * @author Codevz
 * @link http://codevz.com/
 */

class Codevz_WPBakery_title {

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
			'name'			=> esc_html__( 'Title and Text', 'codevz-plus' ),
			'description'	=> esc_html__( 'Section customizable title', 'codevz-plus' ),
			'icon'			=> 'czi',
			'params'		=> array(
				array(
					'type' 			=> 'cz_sc_id',
					'param_name' 	=> 'id',
					'save_always' 	=> true
				),
				array(
					"type"        	=> "textarea_html",
					"heading"     	=> esc_html__("Title", 'codevz-plus' ),
					'edit_field_class' => 'vc_col-xs-99',
					"param_name"  	=> "content",
					"value"  		=> "<h3>Title Element</h3>",
					'admin_label' 	=> true
				),
				array(
					"type"        	=> "dropdown",
					"heading"     	=> esc_html__("Positon", 'codevz-plus' ),
					"param_name"  	=> "title_pos",
					'edit_field_class' => 'vc_col-xs-99',
					'value'		=> array(
						__( 'Inline', 'codevz-plus' ) 	=> 'cz_title_pos_inline',
						__( 'Block', 'codevz-plus' ) 	=> 'cz_title_pos_block',
						__( 'Left', 'codevz-plus' ) 		=> 'cz_title_pos_left',
						__( 'Center', 'codevz-plus' )	=> 'cz_title_pos_center',
						__( 'Right', 'codevz-plus' )		=> 'cz_title_pos_right'
					)
				),
				
				array(
					'type' 			=> 'cz_sk',
					'param_name' 	=> 'sk_overall',
					'hover_id'	 	=> 'sk_overall_hover',
					"heading"     	=> esc_html__( "Container", 'codevz-plus' ),
					'button' 		=> esc_html__( "Container", 'codevz-plus' ),
					'edit_field_class' => 'vc_col-xs-99',
					'settings' 		=> array( 'background', 'border' )
				),
				array( 'type' => 'cz_hidden','param_name' => 'sk_overall_tablet' ),
				array( 'type' => 'cz_hidden','param_name' => 'sk_overall_mobile' ),
				array( 'type' => 'cz_hidden','param_name' => 'sk_overall_hover' ),
				
				array(
					'type' 			=> 'cz_sk',
					'param_name' 	=> 'sk_h1',
					'hover_id'	 	=> 'sk_h1_hover',
					"heading"     	=> "H1",
					'button' 		=> "H1",
					'edit_field_class' => 'vc_col-xs-99',
					'settings' 		=> array( 'color', 'background', 'border' )
				),
				array( 'type' => 'cz_hidden','param_name' => 'sk_h1_tablet' ),
				array( 'type' => 'cz_hidden','param_name' => 'sk_h1_mobile' ),
				array( 'type' => 'cz_hidden','param_name' => 'sk_h1_hover' ),
				
				array(
					'type' 			=> 'cz_sk',
					'param_name' 	=> 'sk_h2',
					'hover_id'	 	=> 'sk_h2_hover',
					"heading"     	=> "H2",
					'button' 		=> "H2",
					'edit_field_class' => 'vc_col-xs-99',
					'settings' 		=> array( 'color', 'background', 'border' )
				),
				array( 'type' => 'cz_hidden','param_name' => 'sk_h2_tablet' ),
				array( 'type' => 'cz_hidden','param_name' => 'sk_h2_mobile' ),
				array( 'type' => 'cz_hidden','param_name' => 'sk_h2_hover' ),
				
				array(
					'type' 			=> 'cz_sk',
					'param_name' 	=> 'sk_h3',
					'hover_id'	 	=> 'sk_h3_hover',
					"heading"     	=> "H3",
					'button' 		=> "H3",
					'edit_field_class' => 'vc_col-xs-99',
					'settings' 		=> array( 'color', 'background', 'border' )
				),
				array( 'type' => 'cz_hidden','param_name' => 'sk_h3_tablet' ),
				array( 'type' => 'cz_hidden','param_name' => 'sk_h3_mobile' ),
				array( 'type' => 'cz_hidden','param_name' => 'sk_h3_hover' ),
				
				array(
					'type' 			=> 'cz_sk',
					'param_name' 	=> 'sk_h4',
					'hover_id'	 	=> 'sk_h4_hover',
					"heading"     	=> "H4",
					'button' 		=> "H4",
					'edit_field_class' => 'vc_col-xs-99',
					'settings' 		=> array( 'color', 'background', 'border' )
				),
				array( 'type' => 'cz_hidden','param_name' => 'sk_h4_tablet' ),
				array( 'type' => 'cz_hidden','param_name' => 'sk_h4_mobile' ),
				array( 'type' => 'cz_hidden','param_name' => 'sk_h4_hover' ),
				
				array(
					'type' 			=> 'cz_sk',
					'param_name' 	=> 'sk_h5',
					'hover_id'	 	=> 'sk_h5_hover',
					"heading"     	=> "H5",
					'button' 		=> "H5",
					'edit_field_class' => 'vc_col-xs-99',
					'settings' 		=> array( 'color', 'background', 'border' )
				),
				array( 'type' => 'cz_hidden','param_name' => 'sk_h5_tablet' ),
				array( 'type' => 'cz_hidden','param_name' => 'sk_h5_mobile' ),
				array( 'type' => 'cz_hidden','param_name' => 'sk_h5_hover' ),
				
				array(
					'type' 			=> 'cz_sk',
					'param_name' 	=> 'sk_h6',
					'hover_id'	 	=> 'sk_h6_hover',
					"heading"     	=> "H6",
					'button' 		=> "H6",
					'edit_field_class' => 'vc_col-xs-99',
					'settings' 		=> array( 'color', 'background', 'border' )
				),
				array( 'type' => 'cz_hidden','param_name' => 'sk_h6_tablet' ),
				array( 'type' => 'cz_hidden','param_name' => 'sk_h6_mobile' ),
				array( 'type' => 'cz_hidden','param_name' => 'sk_h6_hover' ),

				array(
					'type' 			=> 'cz_sk',
					'param_name' 	=> 'sk_links',
					'hover_id'	 	=> 'sk_links_hover',
					"heading"     	=> esc_html__( "Links", 'codevz-plus' ),
					'button' 		=> esc_html__( "Links", 'codevz-plus' ),
					'edit_field_class' => 'vc_col-xs-99',
					'settings' 		=> array( 'color', 'background', 'border' )
				),
				array( 'type' => 'cz_hidden','param_name' => 'sk_links_tablet' ),
				array( 'type' => 'cz_hidden','param_name' => 'sk_links_mobile' ),
				array( 'type' => 'cz_hidden','param_name' => 'sk_links_hover' ),

				array(
					'type' 			=> 'cz_sk',
					'param_name' 	=> 'sk_lists',
					"heading"     	=> esc_html__( "Lists", 'codevz-plus' ),
					'button' 		=> esc_html__( "Lists", 'codevz-plus' ),
					'edit_field_class' => 'vc_col-xs-99',
					'settings' 		=> array( 'color', 'background', 'border' )
				),
				array( 'type' => 'cz_hidden','param_name' => 'sk_lists_tablet' ),
				array( 'type' => 'cz_hidden','param_name' => 'sk_lists_mobile' ),

				array(
					'type' 			=> 'cz_sk',
					'param_name' 	=> 'sk_lists_li',
					"heading"     	=> esc_html__( "Lists li", 'codevz-plus' ),
					'button' 		=> esc_html__( "Lists li", 'codevz-plus' ),
					'edit_field_class' => 'vc_col-xs-99',
					'settings' 		=> array( 'color', 'background', 'border' )
				),
				array( 'type' => 'cz_hidden','param_name' => 'sk_lists_li_tablet' ),
				array( 'type' => 'cz_hidden','param_name' => 'sk_lists_li_mobile' ),

				array(
					"type"        	=> "vc_link",
					"heading"     	=> esc_html__("Link", 'codevz-plus' ),
					"param_name"  	=> "link",
					'edit_field_class' => 'vc_col-xs-99',
				),

				array(
					'type' 			=> 'cz_title',
					'param_name' 	=> 'cz_title_resp',
					'class' 		=> '',
					'content' 		=> esc_html__( 'Title Line', 'codevz-plus' )
				),
				array(
					"type"        	=> "dropdown",
					"heading"     	=> esc_html__( "Type", 'codevz-plus' ),
					'edit_field_class' => 'vc_col-xs-99',
					"param_name"  	=> "bline",
					'value'			=> array(
						esc_html__( 'Select', 'codevz-plus' ) 		=> '',
						esc_html__( 'Above title', 'codevz-plus' )	=> 'cz_line_before_title',
						esc_html__( 'Below title', 'codevz-plus' ) 	=> 'cz_line_after_title',
						esc_html__( 'Left Side', 'codevz-plus' ) 	=> 'cz_line_left_side',
						esc_html__( 'Right Side', 'codevz-plus' ) 	=> 'cz_line_right_side',
						esc_html__( 'Both side', 'codevz-plus' ) 	=> 'cz_line_both_side',
					),
				),
				array(
					"type"        	=> "cz_slider",
					"heading"     	=> esc_html__("Horizontal Offset Right Line", 'codevz-plus' ),
					'edit_field_class' => 'vc_col-xs-99',
					"param_name"  	=> "css_right_line_left",
					'dependency'	=> array(
						'element'		=> 'bline',
						'value'			=> array( 'cz_line_right_side', 'cz_line_both_side' )
					),
					'options' 		=> array( 'unit' => 'px', 'step' => 1, 'min' => -80, 'max' => 80 ),
				),
				array(
					'type' 			=> 'cz_sk',
					'param_name' 	=> 'sk_lines',
					"heading"     	=> esc_html__( "Line(s) styling", 'codevz-plus' ),
					'button' 		=> esc_html__( "Line(s)", 'codevz-plus' ),
					'edit_field_class' => 'vc_col-xs-99',
					'settings' 		=> array( 'background', 'width', 'height', 'top', 'left' ),
					'dependency'	=> array(
						'element'		=> 'bline',
						'not_empty'		=> true
					),
				),
				array( 'type' => 'cz_hidden','param_name' => 'sk_lines_tablet' ),
				array( 'type' => 'cz_hidden','param_name' => 'sk_lines_mobile' ),
				array(
					'type' 			=> 'cz_sk',
					'param_name' 	=> 'sk_lines_con',
					"heading"     	=> esc_html__( "Line(s) container", 'codevz-plus' ),
					'button' 		=> esc_html__( "Line(s) container", 'codevz-plus' ),
					'edit_field_class' => 'vc_col-xs-99',
					'settings' 		=> array( 'background', 'height', 'top' ),
					'dependency'	=> array(
						'element'		=> 'bline',
						'not_empty'		=> true
					),
				),
				array( 'type' => 'cz_hidden','param_name' => 'sk_lines_con_tablet' ),
				array( 'type' => 'cz_hidden','param_name' => 'sk_lines_con_mobile' ),

				// Icons
				array(
					'type' 			=> 'cz_title',
					'param_name' 	=> 'cz_title_icbt',
					'class' 		=> '',
					'content' 		=> esc_html__( 'Icon before title', 'codevz-plus' ),
					'group' 		=> esc_html__( 'Icon', 'codevz-plus' )
				),
				array(
					"type"        	=> "dropdown",
					"heading"     	=> esc_html__( "Type", 'codevz-plus' ),
					"param_name"  	=> "icon_before_type",
					'edit_field_class' => 'vc_col-xs-99',
					'value'			=> array(
						esc_html__( 'Select', 'codevz-plus' ) 	=> '',
						esc_html__( 'Icon', 'codevz-plus' ) 		=> 'icon',
						esc_html__( 'Image', 'codevz-plus' ) 	=> 'image',
						esc_html__( 'Number', 'codevz-plus' ) 	=> 'number',
					),
					"group"  		=> esc_html__( 'Icon', 'codevz-plus' )
				),
				array(
					"type"        	=> "cz_icon",
					"heading"     	=> esc_html__("Icon", 'codevz-plus' ),
					'edit_field_class' => 'vc_col-xs-99',
					"param_name"  	=> "icon",
					'dependency'	=> array(
						'element'		=> 'icon_before_type',
						'value'			=> array( 'icon' )
					),
					"group"  		=> esc_html__( 'Icon', 'codevz-plus' )
				),
				array(
					"type"        	=> "attach_image",
					"heading"     	=> esc_html__("Image", 'codevz-plus' ),
					'edit_field_class' => 'vc_col-xs-99',
					"param_name"  	=> "image_as_icon",
					'dependency'	=> array(
						'element'		=> 'icon_before_type',
						'value'			=> array( 'image' )
					),
					"group"  		=> esc_html__( 'Icon', 'codevz-plus' )
				),
				array(
					"type"			=> "textfield",
					"heading"     	=> esc_html__("Image size", 'codevz-plus' ),
					"description"   => esc_html__('Enter image size (e.g: "thumbnail", "medium", "large", "full"), Alternatively enter size in pixels (e.g: 200x100 (Width x Height)).', 'codevz-plus' ),
					"value"  		=> "thumbnail",
					'edit_field_class' => 'vc_col-xs-99',
					"param_name"  	=> "image_as_icon_size",
					'dependency'	=> array(
						'element'		=> 'icon_before_type',
						'value'			=> array( 'image' )
					),
					"group"  		=> esc_html__( 'Icon', 'codevz-plus' )
				),
				array(
					"type"        	=> "textfield",
					"heading"     	=> esc_html__("Number", 'codevz-plus' ),
					'edit_field_class' => 'vc_col-xs-99',
					"param_name"  	=> "number",
					'dependency'	=> array(
						'element'		=> 'icon_before_type',
						'value'			=> array( 'number' )
					),
					"group"  		=> esc_html__( 'Icon', 'codevz-plus' )
				),
				array(
					'type' 			=> 'cz_sk',
					'param_name' 	=> 'sk_icon_before',
					'hover_id'	 	=> 'sk_icon_before_hover',
					"heading"     	=> esc_html__( "Styling", 'codevz-plus' ),
					'button' 		=> esc_html__( "Icon before title", 'codevz-plus' ),
					'edit_field_class' => 'vc_col-xs-99',
					'group' 		=> esc_html__( 'Icon', 'codevz-plus' ),
					'settings' 		=> array( 'transform', 'color', 'background', 'border' ),
					'dependency'	=> array(
						'element'		=> 'icon_before_type',
						'not_empty'		=> true
					),
				),
				array( 'type' => 'cz_hidden','param_name' => 'sk_icon_before_tablet' ),
				array( 'type' => 'cz_hidden','param_name' => 'sk_icon_before_mobile' ),
				array( 'type' => 'cz_hidden','param_name' => 'sk_icon_before_hover' ),

				array(
					'type' 			=> 'cz_title',
					'param_name' 	=> 'cz_title_icat',
					'class' 		=> '',
					'content' 		=> esc_html__( 'Icon after title', 'codevz-plus' ),
					'group' 		=> esc_html__( 'Icon', 'codevz-plus' )
				),
				array(
					"type"        	=> "dropdown",
					"heading"     	=> esc_html__( "Type", 'codevz-plus' ),
					"param_name"  	=> "icon_after_type",
					'edit_field_class' => 'vc_col-xs-99',
					'value'			=> array(
						esc_html__( 'Select', 'codevz-plus' ) 	=> '',
						esc_html__( 'Icon', 'codevz-plus' ) 		=> 'icon',
						esc_html__( 'Image', 'codevz-plus' ) 	=> 'image',
						esc_html__( 'Number', 'codevz-plus' ) 	=> 'number',
					),
					"group"  		=> esc_html__( 'Icon', 'codevz-plus' )
				),
				array(
					"type"        	=> "cz_icon",
					"heading"     	=> esc_html__("Icon", 'codevz-plus' ),
					'edit_field_class' => 'vc_col-xs-99',
					"param_name"  	=> "icon_after",
					'dependency'	=> array(
						'element'		=> 'icon_after_type',
						'value'			=> array( 'icon' )
					),
					"group"  		=> esc_html__( 'Icon', 'codevz-plus' )
				),
				array(
					"type"        	=> "attach_image",
					"heading"     	=> esc_html__("Image", 'codevz-plus' ),
					'edit_field_class' => 'vc_col-xs-99',
					"param_name"  	=> "image_as_icon_after",
					'dependency'	=> array(
						'element'		=> 'icon_after_type',
						'value'			=> array( 'image' )
					),
					"group"  		=> esc_html__( 'Icon', 'codevz-plus' )
				),
				array(
					"type"			=> "textfield",
					"heading"     	=> esc_html__("Image size", 'codevz-plus' ),
					"description"   => esc_html__('Enter image size (e.g: "thumbnail", "medium", "large", "full"), Alternatively enter size in pixels (e.g: 200x100 (Width x Height)).', 'codevz-plus' ),
					"value"  		=> "thumbnail",
					'edit_field_class' => 'vc_col-xs-99',
					"param_name"  	=> "image_as_icon_after_size",
					'dependency'	=> array(
						'element'		=> 'icon_after_type',
						'value'			=> array( 'image' )
					),
					"group"  		=> esc_html__( 'Icon', 'codevz-plus' )
				),
				array(
					"type"        	=> "textfield",
					"heading"     	=> esc_html__("Number", 'codevz-plus' ),
					'edit_field_class' => 'vc_col-xs-99',
					"param_name"  	=> "number_after",
					'dependency'	=> array(
						'element'		=> 'icon_after_type',
						'value'			=> array( 'number' )
					),
					"group"  		=> esc_html__( 'Icon', 'codevz-plus' )
				),
				array(
					'type' 			=> 'cz_sk',
					'param_name' 	=> 'sk_icon_after',
					'hover_id'	 	=> 'sk_icon_after_hover',
					"heading"     	=> esc_html__( "Styling", 'codevz-plus' ),
					'button' 		=> esc_html__( "Icon after title", 'codevz-plus' ),
					'edit_field_class' => 'vc_col-xs-99',
					'group' 		=> esc_html__( 'Icon', 'codevz-plus' ),
					'settings' 		=> array( 'transform', 'color', 'background', 'border' ),
					'dependency'	=> array(
						'element'		=> 'icon_after_type',
						'not_empty'		=> true
					),
				),
				array( 'type' => 'cz_hidden','param_name' => 'sk_icon_after_tablet' ),
				array( 'type' => 'cz_hidden','param_name' => 'sk_icon_after_mobile' ),
				array( 'type' => 'cz_hidden','param_name' => 'sk_icon_after_hover' ),

				// Shape
				array(
					'type' 			=> 'cz_title',
					'param_name' 	=> 'cz_title_shp',
					'class' 		=> '',
					'content' 		=> esc_html__( 'Shape', 'codevz-plus' ),
					'group' 		=> esc_html__( 'Shape', 'codevz-plus' )
				),
				array(
					"type"        	=> "dropdown",
					"heading"     	=> esc_html__("Shape", 'codevz-plus' ),
					'edit_field_class' => 'vc_col-xs-99',
					"param_name"  	=> "shape",
					'value'			=> array(
						esc_html__( 'Select', 'codevz-plus' ) 			=> '',
						esc_html__( 'Text', 'codevz-plus' )				=> 'text',
						esc_html__( 'Icon', 'codevz-plus' ) 				=> 'icon',
						esc_html__( 'Image', 'codevz-plus' ) 			=> 'image',
						esc_html__( 'Circle', 'codevz-plus' ) 			=> 'circle',
						esc_html__( 'Circle Outline', 'codevz-plus' ) 	=> 'circle cz_title_shape_outline',
						esc_html__( 'Square', 'codevz-plus' ) 			=> 'square',
						esc_html__( 'Square Outline', 'codevz-plus' ) 	=> 'square cz_title_shape_outline',
						esc_html__( 'Rhombus', 'codevz-plus' ) 			=> 'rhombus',
						esc_html__( 'Rhombus Outline', 'codevz-plus' ) 	=> 'rhombus cz_title_shape_outline',
						esc_html__( 'Rhombus Radius', 'codevz-plus' ) 	=> 'rhombus_radius',
						esc_html__( 'Rhombus Radius Outline', 'codevz-plus' ) => 'rhombus_radius cz_title_shape_outline',
						esc_html__( 'Rectangle', 'codevz-plus' ) 		=> 'rectangle',
						esc_html__( 'Rectangle Outline', 'codevz-plus' ) => 'rectangle cz_title_shape_outline',
					),
					'std'			=> '',
					"group"  		=> esc_html__( 'Shape', 'codevz-plus' )
				),
				array(
					"type"        	=> "textfield",
					"heading"     	=> esc_html__("Text", 'codevz-plus' ),
					"param_name"  	=> "shape_text",
					'edit_field_class' => 'vc_col-xs-99',
					'dependency'	=> array(
						'element'		=> 'shape',
						'value'			=> array( 'text' )
					),
					"group"  		=> esc_html__( 'Shape', 'codevz-plus' )
				),
				array(
					"type"        	=> "cz_icon",
					"heading"     	=> esc_html__("Icon", 'codevz-plus' ),
					"param_name"  	=> "shape_icon",
					'edit_field_class' => 'vc_col-xs-99',
					'dependency'	=> array(
						'element'		=> 'shape',
						'value'			=> array( 'icon' )
					),
					"group"  		=> esc_html__( 'Shape', 'codevz-plus' )
				),
				array(
					"type"        	=> "attach_image",
					"heading"     	=> esc_html__("Image", 'codevz-plus' ),
					"param_name"  	=> "image",
					'edit_field_class' => 'vc_col-xs-99',
					'dependency'	=> array(
						'element'		=> 'shape',
						'value'			=> array( 'image' )
					),
					"group"  		=> esc_html__( 'Shape', 'codevz-plus' )
				),
				array(
					'type' 			=> 'cz_sk',
					'param_name' 	=> 'sk_shape',
					'hover_id'	 	=> 'sk_shape_hover',
					"heading"     	=> esc_html__( "Styling", 'codevz-plus' ),
					'button' 		=> esc_html__( "Shape", 'codevz-plus' ),
					'edit_field_class' => 'vc_col-xs-99',
					'group' 	=> esc_html__( 'Shape', 'codevz-plus' ),
					'settings' 		=> array( 'top', 'left', 'width', 'height', 'color', 'text-align', 'font-size', 'background', 'border' ),
					'dependency'	=> array(
						'element'		=> 'shape',
						'not_empty'		=> true
					),
				),
				array( 'type' => 'cz_hidden','param_name' => 'sk_shape_tablet' ),
				array( 'type' => 'cz_hidden','param_name' => 'sk_shape_mobile' ),
				array( 'type' => 'cz_hidden','param_name' => 'sk_shape_hover' ),

				array(
					'type' 			=> 'cz_title',
					'param_name' 	=> 'cz_title_shp',
					'class' 		=> '',
					'content' 		=> esc_html__( 'Shape 2', 'codevz-plus' ),
					'group' 		=> esc_html__( 'Shape', 'codevz-plus' )
				),
				array(
					"type"        	=> "dropdown",
					"heading"     	=> esc_html__("Shape 2", 'codevz-plus' ),
					'edit_field_class' => 'vc_col-xs-99',
					"param_name"  	=> "shape2",
					'value'			=> array(
						esc_html__( 'Select', 'codevz-plus' ) 			=> '',
						esc_html__( 'Text', 'codevz-plus' )				=> 'text',
						esc_html__( 'Icon', 'codevz-plus' ) 				=> 'icon',
						esc_html__( 'Image', 'codevz-plus' ) 			=> 'image',
						esc_html__( 'Circle', 'codevz-plus' ) 			=> 'circle',
						esc_html__( 'Circle Outline', 'codevz-plus' ) 	=> 'circle cz_title_shape_outline',
						esc_html__( 'Square', 'codevz-plus' ) 			=> 'square',
						esc_html__( 'Square Outline', 'codevz-plus' ) 	=> 'square cz_title_shape_outline',
						esc_html__( 'Rhombus', 'codevz-plus' ) 			=> 'rhombus',
						esc_html__( 'Rhombus Outline', 'codevz-plus' ) 	=> 'rhombus cz_title_shape_outline',
						esc_html__( 'Rhombus Radius', 'codevz-plus' ) 	=> 'rhombus_radius',
						esc_html__( 'Rhombus Radius Outline', 'codevz-plus' ) => 'rhombus_radius cz_title_shape_outline',
						esc_html__( 'Rectangle', 'codevz-plus' ) 		=> 'rectangle',
						esc_html__( 'Rectangle Outline', 'codevz-plus' ) => 'rectangle cz_title_shape_outline',
					),
					'std'			=> '',
					"group"  		=> esc_html__( 'Shape', 'codevz-plus' )
				),
				array(
					"type"        	=> "textfield",
					"heading"     	=> esc_html__("Text", 'codevz-plus' ),
					"param_name"  	=> "shape_text2",
					'edit_field_class' => 'vc_col-xs-99',
					'dependency'	=> array(
						'element'		=> 'shape2',
						'value'			=> array( 'text' )
					),
					"group"  		=> esc_html__( 'Shape', 'codevz-plus' )
				),
				array(
					"type"        	=> "cz_icon",
					"heading"     	=> esc_html__("Icon", 'codevz-plus' ),
					"param_name"  	=> "shape_icon2",
					'edit_field_class' => 'vc_col-xs-99',
					'dependency'	=> array(
						'element'		=> 'shape2',
						'value'			=> array( 'icon' )
					),
					"group"  		=> esc_html__( 'Shape', 'codevz-plus' )
				),
				array(
					"type"        	=> "attach_image",
					"heading"     	=> esc_html__("Image", 'codevz-plus' ),
					"param_name"  	=> "image2",
					'edit_field_class' => 'vc_col-xs-99',
					'dependency'	=> array(
						'element'		=> 'shape2',
						'value'			=> array( 'image' )
					),
					"group"  		=> esc_html__( 'Shape', 'codevz-plus' )
				),
				array(
					'type' 			=> 'cz_sk',
					'param_name' 	=> 'sk_shape2',
					'hover_id'	 	=> 'sk_shape2_hover',
					"heading"     	=> esc_html__( "Styling", 'codevz-plus' ),
					'button' 		=> esc_html__( "Shape 2", 'codevz-plus' ),
					'edit_field_class' => 'vc_col-xs-99',
					'group' 	=> esc_html__( 'Shape', 'codevz-plus' ),
					'settings' 		=> array( 'top', 'left', 'width', 'height', 'color', 'text-align', 'font-size', 'background', 'border' ),
					'dependency'	=> array(
						'element'		=> 'shape2',
						'not_empty'		=> true
					),
				),
				array( 'type' => 'cz_hidden','param_name' => 'sk_shape2_tablet' ),
				array( 'type' => 'cz_hidden','param_name' => 'sk_shape2_mobile' ),
				array( 'type' => 'cz_hidden','param_name' => 'sk_shape2_hover' ),

				// Advanced
				array(
					'type' 				=> 'dropdown',
					'heading' 			=> esc_html__( 'Fancy animation', 'codevz-plus' ),
					'param_name' 		=> 'fancy',
					'edit_field_class' 	=> 'vc_col-xs-99',
					'value' 			=> array(
						esc_html__( 'Select', 'codevz-plus' ) 			=> '',
						esc_html__( 'Fancy words', 'codevz-plus' ) 		=> 'codevz-fancy-words',
						esc_html__( 'Fancy letters', 'codevz-plus' ) 	=> 'codevz-fancy-letters',
						esc_html__( 'Fancy line', 'codevz-plus' ) 		=> 'codevz-fancy-line',
					),
					'group' 		=> esc_html__( 'Advanced', 'codevz-plus' )
				), 
				array(
					'type' 				=> 'cz_slider',
					'heading' 			=> esc_html__( 'Fancy speed', 'codevz-plus' ),
					'edit_field_class' 	=> 'vc_col-xs-99',
					'options' 			=> array( 'unit' => '', 'step' => 100, 'min' => 100, 'max' => 3000 ),
					'param_name' 		=> 'fancy_speed',
					'group' 			=> esc_html__( 'Advanced', 'codevz-plus' )
				),
				array(
					'type' 				=> 'cz_slider',
					'heading' 			=> esc_html__( 'Fancy delay', 'codevz-plus' ),
					'edit_field_class' 	=> 'vc_col-xs-99',
					'options' 			=> array( 'unit' => '', 'step' => 250, 'min' => 0, 'max' => 3000 ),
					'param_name' 		=> 'fancy_delay',
					'group' 			=> esc_html__( 'Advanced', 'codevz-plus' )
				),
				array(
					"type"        	=> "dropdown",
					"heading"     	=> esc_html__("Vertical", 'codevz-plus' ),
					"param_name"  	=> "vertical",
					'edit_field_class' => 'vc_col-xs-99',
					'value'		=> array(
						esc_html__( 'Select', 'codevz-plus' ) 					=> '',
						esc_html__( 'Vertical', 'codevz-plus' ) . ' 1' 			=> 'cz_title_vertical',
						esc_html__( 'Vertical Outside', 'codevz-plus' ) . ' 1' 	=> 'cz_title_vertical cz_title_vertical_outside',
						esc_html__( 'Vertical', 'codevz-plus' ) . ' 2' 			=> 'cz_title_vertical_2',
						esc_html__( 'Vertical Outside', 'codevz-plus' ) . ' 2' 	=> 'cz_title_vertical_2 cz_title_vertical_outside',
					),
					'group' 		=> esc_html__( 'Advanced', 'codevz-plus' )
				),
				array(
					'type' 				=> 'cz_slider',
					'heading' 			=> esc_html__( 'Rotate', 'codevz-plus' ),
					'description' 		=> 'e.g. 45deg',
					'edit_field_class' 	=> 'vc_col-xs-99',
					'options' 			=> array( 'unit' => 'deg', 'step' => 1, 'min' => 0, 'max' => 360 ),
					'param_name' 		=> 'css_transform',
					'group' 			=> esc_html__( 'Advanced', 'codevz-plus' )
				),
				array(
					'type' 				=> 'cz_title',
					'param_name' 		=> 'cz_title_resp',
					'class' 			=> '',
					'content' 			=> esc_html__( 'Responsive', 'codevz-plus' ),
					'group' 			=> esc_html__( 'Advanced', 'codevz-plus' )
				),
				array(
					'type' 				=> 'checkbox',
					'heading' 			=> esc_html__( 'Text center?', 'codevz-plus' ),
					'param_name' 		=> 'text_center',
					'edit_field_class' 	=> 'vc_col-xs-99',
					'group' 			=> esc_html__( 'Advanced', 'codevz-plus' )
				), 
				array(
					'type' 			=> 'checkbox',
					'heading' 		=> esc_html__( 'Hide on Desktop?', 'codevz-plus' ),
					'param_name' 	=> 'hide_on_d',
					'edit_field_class' => 'vc_col-xs-99',
					'group' 		=> esc_html__( 'Advanced', 'codevz-plus' )
				), 
				array(
					'type' 			=> 'checkbox',
					'heading' 		=> esc_html__( 'Hide on Tablet?', 'codevz-plus' ),
					'param_name' 	=> 'hide_on_t',
					'edit_field_class' => 'vc_col-xs-99',
					'group' 		=> esc_html__( 'Advanced', 'codevz-plus' )
				), 
				array(
					'type' 			=> 'checkbox',
					'heading' 		=> esc_html__( 'Hide on Mobile?', 'codevz-plus' ),
					'param_name' 	=> 'hide_on_m',
					'edit_field_class' => 'vc_col-xs-99',
					'group' 		=> esc_html__( 'Advanced', 'codevz-plus' )
				),
				array(
					'type' 			=> 'cz_title',
					'param_name' 	=> 'cz_title_plx',
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
					'param_name' 	=> 'cz_title_anim',
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
	public function out( $atts, $content = '<h3>Title Element</h3>' ) {
		$atts = Codevz_Plus::shortcode_atts( $this, $atts );

		$line_before = $line_after = $icon = $icon_after = '';

		// Content
		$content = Codevz_Plus::fix_extra_p( $content );

		// ID
		if ( ! $atts['id'] ) {
			$atts['id'] = Codevz_Plus::uniqid();
			$public = 1;
		}

		// Shape
		$shape = $atts['shape'];
		if ( $shape === 'text' ) {
			$shape_content = $atts['shape_text'];
		} else if ( $shape === 'icon' ) {
			$shape_content = '<i class="' . $atts['shape_icon'] . '"></i>';
		} else if ( $shape === 'image' ) {
			$shape_content = Codevz_Plus::get_image( $atts['image'] );
		} else {
			$shape_content = '';
		}
		$shape_out = '';
		if ( $shape ) {
			$shape_out .= $shape ? '<div class="cz_title_shape shape_' . $shape . ' cz_shape_1">' . $shape_content . '</div>' : '';
		}

		// Shape 2
		$shape2 = $atts['shape2'];
		if ( $shape2 === 'text' ) {
			$shape_content2 = $atts['shape_text2'];
		} else if ( $shape2 === 'icon' ) {
			$shape_content2 = '<i class="' . $atts['shape_icon2'] . '"></i>';
		} else if ( $shape2 === 'image' ) {
			$shape_content2 = Codevz_Plus::get_image( $atts['image2'] );
		} else {
			$shape_content2 = '';
		}
		if ( $shape2 ) {
			$shape_out .= $shape2 ? '<div class="cz_title_shape shape_' . $shape2 . ' cz_shape_2">' . $shape_content2 . '</div>' : '';
		}

		if ( $atts['bline'] ) {
			$line = '<div class="cz_title_line ' . $atts['bline'] . '"><span>_</span></div>';
			$line_before = ( $atts['bline'] === 'cz_line_before_title' ) ? $line : '';
			$line_before = ( $atts['bline'] === 'cz_line_left_side' || $atts['bline'] === 'cz_line_both_side' ) ? '<span class="cz_line_side_solo">_</span>' : $line_before;
			$line_after = ( $atts['bline'] === 'cz_line_after_title' ) ? $line : '';
			$bline_css = ( $atts['css_right_line_left'] && $atts['bline'] === 'cz_line_both_side' ) ? ' style="' . ( Codevz_Plus::$is_rtl ? 'right' : 'left' ) . ':' . $atts['css_right_line_left'] . '"' : '';
			$line_after = ( $atts['bline'] === 'cz_line_both_side' || $atts['bline'] === 'cz_line_right_side' ) ? '<span class="cz_line_side_solo cz_line_side_after"' . $bline_css . '>_</span>' : $line_after;
		}

		// Icon before
		if ( $atts['image_as_icon'] ) {
			$icon = Codevz_Plus::get_image( $atts['image_as_icon'], $atts['image_as_icon_size'] );
			$icon = '<span class="cz_title_icon_before cz_title_image">' . $icon . '</span>';
		} else if ( $atts['icon'] ) {
			$icon = '<i class="cz_title_icon_before ' . $atts['icon'] . '"></i>';
		} else if ( $atts['number'] ) {
			$icon = '<i class="cz_title_icon_before cz_title_number"><span>' . $atts['number'] . '</span></i>';
		}

		// Icon after
		if ( $atts['image_as_icon_after'] ) {
			$icon_after = Codevz_Plus::get_image( $atts['image_as_icon_after'], $atts['image_as_icon_after_size'] );
			$icon_after = '<span class="cz_title_icon_after cz_title_image">' . $icon_after . '</span>';
		} else if ( $atts['icon_after'] ) {
			$icon_after = '<i class="cz_title_icon_after ' . $atts['icon_after'] . ' icon_after"></i>';
		} else if ( $atts['number_after'] ) {
			$icon_after = '<i class="cz_title_icon_after cz_title_number"><span>' . $atts['number_after'] . '</span></i>';
		}

		// Styles
		if ( isset( $public ) || Codevz_Plus::$vc_editable || Codevz_Plus::$is_admin ) {
			$css_id = '#' . $atts['id'];

			$icon_b4_rotate = Codevz_Plus::get_string_between( $atts['sk_icon_before'], 'transform:', ';' );
			$wcss = '';
			if ( $icon_b4_rotate ) {
				$wcss .= $css_id . ' .cz_title_content > .cz_title_icon_before:before, ' . $css_id . ' .cz_title_content > .cz_title_icon_before span, ' . $css_id . ' .cz_title_content > span.cz_title_icon_before{transform:' . ( Codevz_Plus::contains( $icon_b4_rotate, '-' ) ? str_replace( '-', '', $icon_b4_rotate ) : str_replace( '(', '(-', $icon_b4_rotate ) ) . '}';
			}

			$icon_after_rotate = Codevz_Plus::get_string_between( $atts['sk_icon_after'], 'transform:', ';' );
			if ( $icon_after_rotate ) {
				$wcss .= $css_id . ' .cz_title_content > .cz_title_icon_after:before, ' . $css_id . ' .cz_title_content > .cz_title_icon_after span, ' . $css_id . ' .cz_title_content > span.cz_title_icon_after{transform:' . ( Codevz_Plus::contains( $icon_after_rotate, '-' ) ? str_replace( '-', '', $icon_after_rotate ) : '-' . str_replace( '(', '(-', $icon_after_rotate ) ) . '}';
			}

			$wcss .= $atts['css_transform'] ? $css_id . ' .cz_wpe_content{transform:rotate(' . $atts['css_transform'] . ')}' : '';

			$css_array = array(
				'sk_brfx' 				=> $css_id . ':before',
				'sk_overall' 			=> $css_id . ' .cz_title_content',
				'sk_overall_hover' 		=> $css_id . ':hover .cz_title_content, .cz_title_parent_box:hover ' . $css_id . ' .cz_title_content',
				'sk_h1' 				=> $css_id . ' .cz_title_content h1',
				'sk_h1_hover' 			=> $css_id . ':hover .cz_title_content h1',
				'sk_h2' 				=> $css_id . ' .cz_title_content h2',
				'sk_h2_hover' 			=> $css_id . ':hover .cz_title_content h2',
				'sk_h3' 				=> $css_id . ' .cz_title_content h3',
				'sk_h3_hover' 			=> $css_id . ':hover .cz_title_content h3',
				'sk_h4' 				=> $css_id . ' .cz_title_content h4',
				'sk_h4_hover' 			=> $css_id . ':hover .cz_title_content h4',
				'sk_h5' 				=> $css_id . ' .cz_title_content h5',
				'sk_h5_hover' 			=> $css_id . ':hover .cz_title_content h5',
				'sk_h6' 				=> $css_id . ' .cz_title_content h6',
				'sk_h6_hover' 			=> $css_id . ':hover .cz_title_content h6',
				'sk_links' 				=> $css_id . ' .cz_title_content a',
				'sk_links_hover' 		=> $css_id . ' .cz_title_content a:hover',
				'sk_lists' 				=> $css_id . ' .cz_title_content ul,' . $css_id . ' .cz_title_content ol',
				'sk_lists' 				=> $css_id . ' .cz_title_content ul li,' . $css_id . ' .cz_title_content ol li',
				'sk_shape' 				=> $css_id . ' .cz_shape_1',
				'sk_shape_hover' 		=> $css_id . ':hover .cz_shape_1',
				'sk_shape2' 			=> $css_id . ' .cz_shape_2',
				'sk_shape2_hover' 		=> $css_id . ':hover .cz_shape_2',
				'sk_lines_con' 			=> $css_id . ' .cz_title_line',
				'sk_lines' 				=> $css_id . ' .cz_title_line span,' . $css_id . ' .cz_line_side_solo',
				'sk_icon_before' 		=> $css_id . ' .cz_title_icon_before',
				'sk_icon_before_hover' 	=> $css_id . ':hover .cz_title_icon_before',
				'sk_icon_after' 		=> $css_id . ' .cz_title_icon_after',
				'sk_icon_after_hover' 	=> $css_id . ':hover .cz_title_icon_after',
			);

			$css 	= Codevz_Plus::sk_style( $atts, $css_array ) . $wcss;
			$css_t 	= Codevz_Plus::sk_style( $atts, $css_array, '_tablet' );
			$css_m 	= Codevz_Plus::sk_style( $atts, $css_array, '_mobile' );

		} else {
			Codevz_Plus::load_font( $atts['sk_overall'] );
			Codevz_Plus::load_font( $atts['sk_h1'] );
			Codevz_Plus::load_font( $atts['sk_h2'] );
			Codevz_Plus::load_font( $atts['sk_h3'] );
			Codevz_Plus::load_font( $atts['sk_h4'] );
			Codevz_Plus::load_font( $atts['sk_h5'] );
			Codevz_Plus::load_font( $atts['sk_h6'] );
			Codevz_Plus::load_font( $atts['sk_links'] );
			Codevz_Plus::load_font( $atts['sk_shape'] );
			Codevz_Plus::load_font( $atts['sk_icon_before'] );
			Codevz_Plus::load_font( $atts['sk_icon_after'] );
		}

		// Animation
		$animation = '';
		if ( ! empty( $atts['css_animation'] ) && Codevz_Plus::contains( $atts[ 'css_animation' ], 'cz_brfx_' ) ) {
			
			// WPBakery old versions
			wp_enqueue_script( 'waypoints' );
			wp_enqueue_style( 'animate-css' );

			// WPBakery after v6.x
			wp_enqueue_script( 'vc_waypoints' );
			wp_enqueue_style( 'vc_animate-css' );
			
			$delay = $atts['anim_delay'] ? ' style="animation-delay:' . $atts['anim_delay'] . ';"' : '';
			$animation = ' class="wpb_animate_when_almost_visible wpb_' . $atts['css_animation'] . ' ' . $atts['css_animation'] . ' relative ' . $atts['class'] . '"' . $delay;
			unset( $atts['css_animation'] );
			$atts['class'] = '';
		} else {

			$delay = $atts['anim_delay'] ? ' style="animation-delay:' . esc_html( $atts['anim_delay'] ) . ';"' : '';
		
		}

		// Classes
		$classes = array();
		$classes[] = $atts['id'];
		$classes[] = 'cz_title clr';
		$classes[] = $atts['text_center'] ? 'cz_mobile_text_center' : '';
		$classes[] = ( $icon || $icon_after ) ? 'cz_title_has_icon' : '';
		$classes[] = Codevz_Plus::contains( $atts['sk_overall'], 'background' ) ? 'cz_title_has_bg' : '';
		$classes[] = Codevz_Plus::contains( $atts['sk_overall'], 'border-width' ) ? 'cz_title_has_border' : '';
		$classes[] = ( Codevz_Plus::contains( $atts['bline'], 'before' ) || Codevz_Plus::contains( $atts['bline'], 'after' ) ) ? 'cz_title_ba_line' : '';
		$classes[] = $atts['vertical'];
		$classes[] = $atts['title_pos'];
		$classes[] = $atts['fancy'];
		if ( strpos( $content, 'center;' ) !== false || strpos( $content, ': center' ) !== false ) {
			$classes[] = 'tac';
		} else if ( strpos( $content, 'right;' ) !== false || strpos( $content, ': right' ) !== false ) {
			$classes[] = 'tar';
		}

		// Final content
		$out_content = $shape_out . '<div class="cz_title_content">' . $line_before . $icon . '<div class="cz_wpe_content">' . ( function_exists( 'wpb_js_remove_wpautop' ) ? wpb_js_remove_wpautop( $content, true ) : do_shortcode( $content ) ) . '</div>' . $icon_after . $line_after. '</div>';

		// Check link
		if ( $atts['link'] ) {
			$a_attr = Codevz_Plus::link_attrs( $atts['link'] );
			$out_content = '<a' . $a_attr . '>' . preg_replace( '/<a .*?<\/a>/', '', $out_content ) . '</a>';
		}

		// Output
		$out = $animation ? '<div' . $animation . '>' : '';
		$out .= '<div id="' . $atts['id'] . '"' . $delay . Codevz_Plus::classes( $atts, $classes ) . ( $atts['fancy_speed'] ? ' data-fancy-speed="' . $atts['fancy_speed'] . '"' : '' ) . ( $atts['fancy_delay'] ? ' data-fancy-delay="' . $atts['fancy_delay'] . '"' : '' ) . Codevz_Plus::data_stlye( $css,  $css_t, $css_m ) . '>' . $out_content . '</div>';
		$out .= $animation ? '</div>' : '';

		return Codevz_Plus::_out( $atts, $out, [ 'title' ], $this->name );
	}

}