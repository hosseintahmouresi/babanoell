<?php if ( ! defined( 'ABSPATH' ) ) { die; } // Cannot access pages directly.

/**
 * Video Popup and inline
 * 
 * @author Codevz
 * @link http://codevz.com/
 */

class Codevz_WPBakery_google_map {

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
			'name'			=> esc_html__( 'Google Map', 'codevz-plus' ),
			'description'	=> esc_html__( 'Customisable google map', 'codevz-plus' ),
			'icon'			=> 'czi',
			'params'		=> array(
				array(
					'type' 			=> 'cz_sc_id',
					'param_name' 	=> 'id',
					'save_always' 	=> true
				),
				array(
					"type" => "textfield",
					"heading" => esc_html__("Google API Key",'codevz-plus'),
					"param_name" => "apikey",
					'edit_field_class' => 'vc_col-xs-99',
					"description" => esc_html__("Get your API Key from",'codevz-plus') . ' <a href="https://goo.gl/wVcKPP" target="_blank">' . esc_html__("Here",'codevz-plus') . '</a>',
				),
				array(
					"type" => "textfield",
					"heading" => esc_html__("Lat",'codevz-plus'),
					"param_name" => "lat",
					"value" => "40.712784",
					'edit_field_class' => 'vc_col-xs-99',
					"description" => esc_html__("Find your location Lat and Long from",'codevz-plus') . ' <a href="http://www.latlong.net" target="_blank">' . esc_html__("Here",'codevz-plus') . '</a>',
				),
				array(
					"type" => "textfield",
					"heading" => esc_html__("Long",'codevz-plus'),
					"param_name" => "long",
					"value" => "-74.005941",
					'edit_field_class' => 'vc_col-xs-99',
				),
				array(
					"type" => "cz_slider",
					"heading" => esc_html__("Zoom",'codevz-plus'),
					"param_name" => "zoom",
					"value"		=> "14",
					'edit_field_class' => 'vc_col-xs-99',
					'options' 		=> array( 'unit' => '', 'step' => 1, 'min' => 0, 'max' => 19 ),
				),
				array(
					"type" => "cz_slider",
					"heading" => esc_html__("Marker Offset X",'codevz-plus'),
					"param_name" => "offsetx",
					"value"		=> "0",
					'edit_field_class' => 'vc_col-xs-99',
					'options' 		=> array( 'unit' => '', 'step' => 10, 'min' => -400, 'max' => 400 ),
				),
				array(
					"type" => "cz_slider",
					"heading" => esc_html__("Marker Offset Y",'codevz-plus'),
					"param_name" => "offsety",
					"value"		=> "0",
					'edit_field_class' => 'vc_col-xs-99',
					'options' 		=> array( 'unit' => '', 'step' => 10, 'min' => -400, 'max' => 400 ),
				),

				array(
					'type' 			=> 'cz_title',
					'param_name' 	=> 'cz_title',
					'class' 		=> '',
					'content' 		=> esc_html__( 'Styling', 'codevz-plus' ),
				),
				array(
					'type' 			=> 'cz_sk',
					'param_name' 	=> 'sk_map',
					"heading"     	=> esc_html__( "Map size", 'codevz-plus' ),
					'button' 		=> esc_html__( "Map container", 'codevz-plus' ),
					'edit_field_class' => 'vc_col-xs-99',
					'settings' 		=> array( 'width', 'height', 'border' )
				),
				array( 'type' => 'cz_hidden','param_name' => 'sk_map_tablet' ),
				array( 'type' => 'cz_hidden','param_name' => 'sk_map_mobile' ),
				array(
					"type" => "dropdown",
					"heading" => esc_html__("Color scheme",'codevz-plus'),
					"param_name" => "color",
					'edit_field_class' => 'vc_col-xs-99',
					"value" => array(
						esc_html__('Standard','codevz-plus') 			=> 'standard',
						esc_html__('Light (Silver)','codevz-plus') 		=> 'light',
						esc_html__('Dark','codevz-plus') 				=> 'dark',
						esc_html__('Retro','codevz-plus') 				=> 'retro',
						esc_html__('Custom Color','codevz-plus') 		=> 'custom',
					)
				 ),
				 array(
					"type" => "colorpicker",
					"heading" => esc_html__("Custom color",'codevz-plus'),
					"param_name" => "custom_color",
					'edit_field_class' => 'vc_col-xs-99',
					"value" => '',
					'dependency'	=> array(
						'element'		=> 'color',
						'value'			=> array( 'custom')
					)
				 ),
				 array(
					"type" => "checkbox",
					"heading" => esc_html__("Grayscale Effect?",'codevz-plus'),
					'edit_field_class' => 'vc_col-xs-99',
					"param_name" => "grayscale",
					"value" => array( esc_html__( "Yes, please", 'codevz-plus' ) => 'yes' )
				),

				array(
					'type' 			=> 'cz_title',
					'param_name' 	=> 'cz_title',
					'class' 		=> '',
					'content' 		=> esc_html__( 'Marker', 'codevz-plus' ),
				),
				array(
					"type" => "dropdown",
					"heading" => esc_html__("Marker",'codevz-plus'),
					"param_name" => "marker",
					'edit_field_class' => 'vc_col-xs-99',
					"value" => array(
						'Off' => 'off',
						'Default Marker' => 'default',
						'Custom Image' => 'custom',
					)
				),
				array(
					"type" => "attach_image",
					"heading" => esc_html__("Marker image",'codevz-plus'),
					"param_name" => "markerimage",
					'edit_field_class' => 'vc_col-xs-99',
					'dependency'	=> array(
						'element'		=> 'marker',
						'value'			=> array( 'custom')
					)
				),
				array(
					"type" => "textfield",
					"heading" => esc_html__("Info text",'codevz-plus'),
					"param_name" => "infowindow",
					'edit_field_class' => 'vc_col-xs-99',
					'dependency'	=> array(
						'element'		=> 'marker',
						'value'			=> array( 'default','custom')
					)
				),
				array(
					"type" => "checkbox",
					"heading" => esc_html__("Show info by default?",'codevz-plus'),
					"param_name" => "infowindowdefault",
					'edit_field_class' => 'vc_col-xs-99',
					"value" => array( esc_html__( "Yes, please", 'codevz-plus' ) => 'yes' ),
					'dependency'	=> array(
						'element'		=> 'marker',
						'value'			=> array( 'default','custom')
					)
				),

				array(
					'type' 			=> 'cz_title',
					'param_name' 	=> 'cz_title',
					'class' 		=> '',
					'content' 		=> esc_html__( 'Advanced', 'codevz-plus' ),
				),
				array(
					"type" => "checkbox",
					"heading" => esc_html__("Enable mouse wheel?",'codevz-plus'),
					"param_name" => "scrollwheel",
					'edit_field_class' => 'vc_col-xs-99',
					"value" => array( esc_html__( "Yes, please", 'codevz-plus' ) => 'yes' )
				),
				array(
					"type" => "checkbox",
					"heading" => esc_html__("Hide map controls?",'codevz-plus'),
					"param_name" => "hidecontrols",
					'edit_field_class' => 'vc_col-xs-99',
					"value" => array( esc_html__( "Yes, please", 'codevz-plus' ) => 'yes' )
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

		$atts = Codevz_Plus::shortcode_atts( $this, $atts );
		$map = $atts['id'] ? $atts['id'] : Codevz_Plus::uniqid( 'cz_map_' );

		if ( ! $map ) {
			$public = 1;
		}

		$isfront = Codevz_Plus::$vc_editable;

		// Data
		$str_data_apikey = $str_apikey = '';
		if( $atts['apikey'] ) {
			$str_apikey = '?key=' . $atts['apikey'] ;
			$str_data_apikey = ' data-api-key="' . $atts['apikey'] . '"' ;
		} else {
			return '<div style="border:1px solid #ccc;border-radius:5px;color:#bb0000;margin:20px 0;padding:30px 0;text-align: center;">
			<h3>Google API Key Error!</h3><h5 style="color:#555">Get your API Key from <a style="color:#bb0000" href="https://goo.gl/wVcKPP">here</a></h5></div>';
		}

		// Styles
		if ( isset( $public ) || $isfront || Codevz_Plus::$is_admin ) {
			$css_id = '#' . $map;
			$custom = $atts['anim_delay'] ? 'animation-delay:' . $atts['anim_delay'] . ';' : '';

			$css_array = array(
				'sk_map' 	=> array( $css_id, $custom ),
				'sk_ir' 	=> $css_id,
				'sk_brfx' 	=> $css_id . ':before',
			);

			// Inverted radius css.
			$atts = Codevz_WPBakery::inverted_radius_css( $atts );

			$css 	= Codevz_Plus::sk_style( $atts, $css_array );
			$css_t 	= Codevz_Plus::sk_style( $atts, $css_array, '_tablet' );
			$css_m 	= Codevz_Plus::sk_style( $atts, $css_array, '_mobile' );
		}

		// Classes
		$classes = array();
		$classes[] = $map;
		$classes[] = 'gmap';
		$classes[] = ( $atts['grayscale'] === 'yes' ) ? 'fx_grayscale fx_remove_grayscale_hover' : '';
		$classes[] = empty( $atts['ir_position'] ) ? '' : 'codevz-plus-inverted-radius-' . esc_attr( $atts['ir_position'] );

		$str = '<div id="' . $map . '"' . Codevz_Plus::classes( $atts, $classes ) . $str_data_apikey . '> </div><div '. Codevz_Plus::data_stlye( $css, $css_t, $css_m ) . '></div>';

		// Map Marker.
		if ( $atts[ 'marker' ] === 'custom' && ! empty( $atts[ 'markerimage' ] ) ) {
			$atts[ 'markerimage' ] = Codevz_Plus::get_image( $atts[ 'markerimage' ], 0, 1 );
		}

		// Map ID.
		$atts[ 'map' ] = $map;

		// Map script.
		$str .= Codevz_Plus::google_maps_script( $atts );

		$out = $str;
		$out .= $isfront ? '<script>setTimeout(function() { if ( typeof google != "undefined"){mapfunction_' . $map . '();} }, 2000 );</script>' : '';

		$out = apply_filters( 'codevz_google_map', $out, $atts );

		return Codevz_Plus::_out( $atts, $out, 'google_map', $this->name );
	}

}