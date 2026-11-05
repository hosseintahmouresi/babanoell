<?php if ( ! defined( 'ABSPATH' ) ) { die; } // Cannot access pages directly.

/**
 * Video Popup and inline
 * 
 * @author Codevz
 * @link http://codevz.com/
 */

class Codevz_WPBakery_video_popup {

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
			'name'			=> esc_html__( 'Video Player', 'codevz-plus' ),
			'description'	=> esc_html__( 'Popup video player', 'codevz-plus' ),
			'icon'			=> 'czi',
			'params'		=> array(
				array(
					'type' 			=> 'cz_sc_id',
					'param_name' 	=> 'id',
					'save_always' 	=> true
				),
				array(
					"type"        	=> "dropdown",
					"heading"     	=> esc_html__( "Video Type", 'codevz-plus' ),
					"param_name"  	=> "type",
					'value'			=> array(
						esc_html__( 'Youtube or Vimeo', 'codevz-plus' ) 		=> '1',
						esc_html__( 'Self-hosted MP4', 'codevz-plus' ) 		=> '2',
						esc_html__( 'Custom code', 'codevz-plus' ) 			=> '3'
					),
					'edit_field_class' => 'vc_col-xs-99'
				),
				array(
					"type"        	=> "textfield",
					"heading"     	=> esc_html__("Video URL", 'codevz-plus' ),
					"description"   => esc_html__("Youtube or Vimeo video URL", 'codevz-plus' ),
					"param_name"  	=> "video",
					'edit_field_class' => 'vc_col-xs-99',
					'admin_label' 	=> true,
					'value'			=> 'https://www.youtube.com/watch?v=fY85ck-pI5c',
					'dependency'	=> array(
						'element'		=> 'type',
						'value'	 		=> [ '1' ]
					),
				),
				array(
					"type"        	=> "cz_upload",
					"heading"     	=> esc_html__("MP4", 'codevz-plus' ),
					"param_name"  	=> "mp4",
					'upload_type' 	=> 'video/mp4',
					'edit_field_class' => 'vc_col-xs-99',
					'admin_label' 	=> true,
					'dependency'	=> array(
						'element'		=> 'type',
						'value'	 		=> [ '2' ]
					),
				),
				array(
					"type"        	=> "textarea_raw_html",
					"heading"     	=> esc_html__("Custom code", 'codevz-plus' ),
					"param_name"  	=> "content",
					'edit_field_class' => 'vc_col-xs-99',
					'dependency'	=> array(
						'element'		=> 'type',
						'value'	 		=> [ '3' ]
					),
				),
				array(
					"type"        	=> "attach_image",
					"heading"     	=> esc_html__("Image placeholder", 'codevz-plus' ),
					"param_name"  	=> "image",
					'edit_field_class' => 'vc_col-xs-99',
				),
				array(
					"type"        	=> "checkbox",
					"heading"     	=> esc_html__("Inline video?", 'codevz-plus' ),
					"param_name"  	=> "inline",
					'edit_field_class' => 'vc_col-xs-99',
					'dependency'	=> array(
						'element'		=> 'type',
						'value'	 		=> [ '1' ]
					),
				),
				array(
					'type' 			=> 'cz_title',
					'param_name' 	=> 'cz_title',
					'class' 		=> '',
					'content' 		=> esc_html__( 'Title and icon', 'codevz-plus' )
				),
				array(
					"type"        	=> "textfield",
					"heading"     	=> esc_html__("Title", 'codevz-plus' ),
					"param_name"  	=> "title",
					'edit_field_class' => 'vc_col-xs-99',
				),
				array(
					"type"        	=> "textfield",
					"heading"     	=> esc_html__("Sub title", 'codevz-plus' ),
					"param_name"  	=> "subtitle",
					'edit_field_class' => 'vc_col-xs-99',
				),
				array(
					"type"        	=> "cz_icon",
					"heading"     	=> esc_html__("Icon", 'codevz-plus' ),
					"param_name"  	=> "icon",
					'edit_field_class' => 'vc_col-xs-99',
					'value'			=> 'fa fa-play'
				),
				array(
					"type"        	=> "dropdown",
					"heading"     	=> esc_html__("Icon position", 'codevz-plus' ),
					"param_name"  	=> "position",
					'value'			=> array(
						'Center' 		=> 'cz_video_popup_c',
						'Top Left'		=> 'cz_video_popup_tl',
						'Top Right'		=> 'cz_video_popup_tr',
						'Bottom Left'	=> 'cz_video_popup_bl',
						'Bottom Right'	=> 'cz_video_popup_br',
					),
					'edit_field_class' => 'vc_col-xs-99',
					'dependency'	=> array(
						'element'		=> 'icon',
						'not_empty'	 	=> true
					),
				),

				array(
					'type' 			=> 'cz_title',
					'param_name' 	=> 'cz_title',
					'class' 		=> '',
					'content' 		=> esc_html__( 'Styling', 'codevz-plus' )
				),
				array(
					'type' 			=> 'cz_sk',
					'param_name' 	=> 'sk_overall',
					"heading"     	=> esc_html__( "Container", 'codevz-plus' ),
					'button' 		=> esc_html__( "Container", 'codevz-plus' ),
					'edit_field_class' => 'vc_col-xs-99',
					'settings' 		=> array( 'background', 'border', 'box-shadow' )
				),
				array( 'type' => 'cz_hidden','param_name' => 'sk_overall_tablet' ),
				array( 'type' => 'cz_hidden','param_name' => 'sk_overall_mobile' ),

				array(
					'type' 			=> 'cz_sk',
					'param_name' 	=> 'sk_icon',
					'hover_id'	 	=> 'sk_icon_hover',
					"heading"     	=> esc_html__( "Icon", 'codevz-plus' ),
					'button' 		=> esc_html__( "Icon", 'codevz-plus' ),
					'edit_field_class' => 'vc_col-xs-99',
					'settings' 		=> array( 'color', 'background', 'border' ),
					'dependency'	=> array(
						'element'		=> 'icon',
						'not_empty'	 	=> true
					),
				),
				array( 'type' => 'cz_hidden','param_name' => 'sk_icon_tablet' ),
				array( 'type' => 'cz_hidden','param_name' => 'sk_icon_mobile' ),
				array( 'type' => 'cz_hidden','param_name' => 'sk_icon_hover' ),

				array(
					'type' 			=> 'cz_sk',
					'param_name' 	=> 'sk_title',
					"heading"     	=> esc_html__( "Title", 'codevz-plus' ),
					'button' 		=> esc_html__( "Title", 'codevz-plus' ),
					'edit_field_class' => 'vc_col-xs-99',
					'settings' 		=> array( 'color', 'font-family', 'font-size', 'background', 'border' )
				),
				array( 'type' => 'cz_hidden','param_name' => 'sk_title_tablet' ),
				array( 'type' => 'cz_hidden','param_name' => 'sk_title_mobile' ),

				array(
					'type' 			=> 'cz_sk',
					'param_name' 	=> 'sk_subtitle',
					"heading"     	=> esc_html__( "Subtitle", 'codevz-plus' ),
					'button' 		=> esc_html__( "Subtitle", 'codevz-plus' ),
					'edit_field_class' => 'vc_col-xs-99',
					'settings' 		=> array( 'color', 'font-family', 'font-size', 'background', 'border' )
				),
				array( 'type' => 'cz_hidden','param_name' => 'sk_subtitle_tablet' ),
				array( 'type' => 'cz_hidden','param_name' => 'sk_subtitle_mobile' ),
				
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
					'content' 		=> esc_html__( 'Tilt effect on hover', 'codevz-plus' ),
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

		// Styles
		if ( isset( $public ) || Codevz_Plus::$vc_editable || Codevz_Plus::$is_admin ) {
			$css_id = '#' . $atts['id'];
			$custom = $atts['anim_delay'] ? 'animation-delay:' . $atts['anim_delay'] . ';' : '';

			$css_array = array(
				'sk_ir' 		=> $css_id,
				'sk_overall' 	=> array( $css_id . ' .cz_vp_c', $custom ),
				'sk_brfx' 		=> $css_id . ':before',
				'sk_icon' 		=> $css_id . ' span',
				'sk_icon_hover' => $css_id . ':hover span',
				'sk_title' 		=> $css_id . ' .cz_vp_inner',
				'sk_subtitle' 	=> $css_id . ' .cz_vp_inner small',
				'svg_bg' 		=> $css_id . '.cz_svg_bg:before'
			);

			// Inverted radius css.
			$atts = Codevz_WPBakery::inverted_radius_css( $atts );

			$css 	= Codevz_Plus::sk_style( $atts, $css_array );
			$css_t 	= Codevz_Plus::sk_style( $atts, $css_array, '_tablet' );
			$css_m 	= Codevz_Plus::sk_style( $atts, $css_array, '_mobile' );
			
		} else {
			Codevz_Plus::load_font( $atts['sk_title'] );
			Codevz_Plus::load_font( $atts['sk_subtitle'] );
		}

		// Title.
		$atts['title'] = $atts['title'] ? '<h4>' . $atts['title'] . ( $atts['subtitle'] ? '<small>' . $atts['subtitle'] . '</small>' : '' ) . '</h4>' : '';

		// Classes.
		$classes = array();
		$classes[] = $atts['id'];
		$classes[] = 'cz_video_popup clr';
		$classes[] = $atts['position'];
		$classes[] = $atts['svg_bg'] ? 'cz_svg_bg' : '';
		$classes[] = $atts['inline'] ? 'cz_video_inline xtra-disable-lightbox' : '';
		$classes[] = empty( $atts['ir_position'] ) ? '' : 'codevz-plus-inverted-radius-' . esc_attr( $atts['ir_position'] );

		// MP4.
		$data_html = $content_html = $code = '';

		if ( $atts['type'] !== '1' ) {

			if ( $atts['type'] === '3' ) {

				$code = rawurldecode( base64_decode( wp_strip_all_tags( $content ) ) );

			} else if ( $atts['mp4'] ) {

				$code = '<video class="lg-video-object lg-html5" controls="" preload="metadata"><source src="' . esc_attr( $atts['mp4'] ) . '" type="video/mp4">Your browser does not support HTML5 video.</video>';

			}

			$atts['video'] = '';
			$data_html = ' data-html="#' . $atts['id'] . '_mp4"';
			$content_html = '<div style="display:none;" id="' . $atts['id'] . '_mp4">' . do_shortcode( $code ) . '</div>';

		} else if ( ! $atts['video'] ) {

			$atts['video'] = 'https://www.youtube.com/watch?v=fY85ck-pI5c';

		}

		$image = '';

		// Image
		if ( ! $atts['image'] && Codevz_Plus::contains( $atts['video'], 'youtube' ) ) {

			$yt = wp_parse_url( $atts['video'] );

			if ( isset( $yt['query'] ) ) {
				parse_str( $yt['query'], $yt );
				$image = Codevz_Plus::get_image( 'https://img.youtube.com/vi/' . $yt['v'] . '/sddefault.jpg' );
			}

		} else {
			$image = Codevz_Plus::get_image( $atts['image'] );
		}

		$out = '';

		if ( $content_html ) {
			$out .= '<div class="xtra-custom-video-parent">';
		}

		$out .= '<div id="' . $atts['id'] . '"' . Codevz_Plus::classes( $atts, $classes ) . Codevz_Plus::data_stlye( $css, $css_t, $css_m ) . '><div' . Codevz_Plus::tilt( $atts ) . '><div class="cz_vp_c"><a href="' . $atts['video'] . '"' . ( $atts['inline'] ? ' class="xtra-disable-lightbox"' : '' ) . $data_html . '>' . $image . '<div class="cz_vp_inner"><span><i class="' . $atts['icon'] . '"></i></span>' . $atts['title'] . '</div></a></div></div></div>' . $content_html;

		if ( $content_html ) {
			$out .= '</div>';
		}

		return Codevz_Plus::_out( $atts, $out, array( 'tilt', 'inline_video( true )' ), $this->name );
	}
}