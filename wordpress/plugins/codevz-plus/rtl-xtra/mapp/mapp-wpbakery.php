<?php if ( ! defined( 'ABSPATH' ) ) { die; } // Cannot access pages directly.

/**
 * Persian map
 * 
 * @author Codevz
 * @link http://codevz.com/
 */

class Codevz_WPBakery_Mapp {

	public $name = false;

	public function __construct() {
		$this->name = 'cz_persian_map';
	}

	/**
	 * Shortcode settings
	 */
	public function in( $wpb = false ) {
		add_shortcode( $this->name, [ $this, 'out' ] );

		$settings = array(
			'category'		=> Codevz_Plus::$title,
			'base'			=> $this->name,
			'name'			=> esc_html__( 'Persian Map', 'codevz-plus' ),
			'description'	=> esc_html__( 'Persian Map', 'codevz-plus' ),
			'icon'			=> 'czi',
			'params'		=> array(
				array(
					'type' 			=> 'cz_sc_id',
					'param_name' 	=> 'id',
					'save_always' 	=> true
				),
				array(
					"type" => "textfield",
					"heading" => esc_html__("Google API Key","codevz"),
					"param_name" => "apikey",
					'edit_field_class' => 'vc_col-xs-99',
					"description" => esc_html__("Get your API Key from","codevz") . ' <a href="https://accounts.map.ir/login" target="_blank">' . esc_html__("Here","codevz") . '</a>',
				),
				array(
					"type" => "textfield",
					"heading" => esc_html__("Lat","codevz"),
					"param_name" => "lat",
					"value" => "40.712784",
					'edit_field_class' => 'vc_col-xs-99',
					"description" => esc_html__("Find your location Lat and Long from","codevz") . ' <a href="http://www.latlong.net" target="_blank">' . esc_html__("Here","codevz") . '</a>',
				),
				array(
					"type" => "textfield",
					"heading" => esc_html__("Long","codevz"),
					"param_name" => "long",
					"value" => "-74.005941",
					'edit_field_class' => 'vc_col-xs-99',
				),
				array(
					"type" => "cz_slider",
					"heading" => esc_html__("Zoom","codevz"),
					"param_name" => "zoom",
					"value"		=> "14",
					'edit_field_class' => 'vc_col-xs-99',
					'options' 		=> array( 'unit' => '', 'step' => 1, 'min' => 0, 'max' => 19 ),
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
					"heading"     	=> esc_html__( "Map size", 'codevz-plus'),
					'button' 		=> esc_html__( "Map container", 'codevz-plus'),
					'edit_field_class' => 'vc_col-xs-99',
					'settings' 		=> array( 'width', 'height', 'border' )
				),
				array( 'type' => 'cz_hidden','param_name' => 'sk_map_tablet' ),
				array( 'type' => 'cz_hidden','param_name' => 'sk_map_mobile' ),
				array(
					"type" => "checkbox",
					"heading" => esc_html__("Dark mode?","codevz"),
					'edit_field_class' => 'vc_col-xs-99',
					"param_name" => "dark_mode",
					"value" => array( esc_html__( "Yes, please", "codevz" ) => 'yes' )
				),
				array(
					"type" => "checkbox",
					"heading" => esc_html__("Gray mode?","codevz"),
					'edit_field_class' => 'vc_col-xs-99',
					"param_name" => "gray_mode",
					"value" => array( esc_html__( "Yes, please", "codevz" ) => 'yes' )
				),

				array(
					'type' 			=> 'cz_title',
					'param_name' 	=> 'cz_title',
					'class' 		=> '',
					'content' 		=> esc_html__( 'Marker', 'codevz-plus' ),
				),
				array(
					"type" => "attach_image",
					"heading" => esc_html__("Marker image","codevz"),
					"param_name" => "marker",
					'edit_field_class' => 'vc_col-xs-99'
				),
				array(
					"type" => "textfield",
					"heading" => esc_html__("Marker title","codevz"),
					"param_name" => "marker_title",
					'edit_field_class' => 'vc_col-xs-99'
				),
				array(
					"type" => "textfield",
					"heading" => esc_html__("Marker description","codevz"),
					"param_name" => "marker_desc",
					'edit_field_class' => 'vc_col-xs-99'
				),

				array(
					'type' 			=> 'cz_title',
					'param_name' 	=> 'cz_title',
					'class' 		=> '',
					'content' 		=> esc_html__( 'Advanced Settings', 'codevz-plus' ),
				),
				array(
					"type" => "checkbox",
					"heading" => esc_html__("Fullscreen?","codevz"),
					"param_name" => "fullscreen",
					'edit_field_class' => 'vc_col-xs-99',
					"value" => array( esc_html__( "Yes, please", "codevz" ) => 'yes' )
				),
				array(
					"type" => "checkbox",
					"heading" => esc_html__("Zoom controls?","codevz"),
					"param_name" => "zoom_controls",
					'edit_field_class' => 'vc_col-xs-99',
					"value" => array( esc_html__( "Yes, please", "codevz" ) => 'yes' )
				),
				array(
					"type" => "checkbox",
					"heading" => esc_html__("My location?","codevz"),
					"param_name" => "my_location",
					'edit_field_class' => 'vc_col-xs-99',
					"value" => array( esc_html__( "Yes, please", "codevz" ) => 'yes' )
				),
				array(
					"type" => "checkbox",
					"heading" => esc_html__("Right click?","codevz"),
					"param_name" => "right_click",
					'edit_field_class' => 'vc_col-xs-99',
					"value" => array( esc_html__( "Yes, please", "codevz" ) => 'yes' )
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
					"heading"     	=> esc_html__("Mouse speed", 'codevz-plus'),
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
					"heading"     	=> esc_html__( "Block Reveal", 'codevz-plus'),
					'button' 		=> esc_html__( "Block Reveal", 'codevz-plus'),
					'edit_field_class' => 'vc_col-xs-99 hidden',
					'group' 	=> esc_html__( 'Advanced', 'codevz-plus' ),
					'settings' 		=> array( 'background' )
				),
				array(
					"type"        	=> "cz_slider",
					"heading"     	=> esc_html__("Animation Delay", 'codevz-plus'),
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
			$atts['id'] = Codevz_Plus::uniqid( 'cz_map_' );
			$public = 1;
		}

		$isfront = Codevz_Plus::$vc_editable ? true : false;

		// Styles
		if ( isset( $public ) || $isfront || Codevz_Plus::$is_admin ) {
			$css_id = '#' . $atts['id'];
			$custom = $atts['anim_delay'] ? 'animation-delay:' . $atts['anim_delay'] . ';' : '';

			$css_array = array(
				'sk_map' 	=> array( $css_id, $custom ),
				'sk_brfx' 	=> $css_id . ':before',
			);

			$css 	= Codevz_Plus::sk_style( $atts, $css_array );
			$css_t 	= Codevz_Plus::sk_style( $atts, $css_array, '_tablet' );
			$css_m 	= Codevz_Plus::sk_style( $atts, $css_array, '_mobile' );
		}

		// Classes
		$classes = array();
		$classes[] = $atts['id'];

		$out = '<div id="' . $atts['id'] . '"' . Codevz_Plus::classes( $atts, $classes ) . '><div '. Codevz_Plus::data_stlye( $css, $css_t, $css_m ) . '></div>';

		$map = [
			'lat' 			=> $atts['lat'],
			'lng' 			=> $atts['long'],
			'zoom' 			=> $atts['zoom'],
			'api_key' 		=> $atts['apikey'],
			'dark_mode' 	=> $atts['dark_mode'],
			'fullscreen' 	=> $atts['fullscreen'],
			'zoom_controls' => $atts['zoom_controls'],
			'my_location' 	=> $atts['my_location'],
			'right_click' 	=> $atts['right_click'],
			'marker' 		=> Codevz_Plus::get_image( $atts['marker'], 0, 1 ),
			'marker_title' 	=> $atts['marker_title'],
			'marker_desc' 	=> $atts['marker_desc']
		];

		$shortcode = '[codevz_persian_map ';

		foreach( $map as $key => $value ) {
			$shortcode .= $key . '="' . $value . '" ';
		}

		$shortcode .= ']';

		$out .= do_shortcode( $shortcode );

		$out .= '</div>';

		return Codevz_Plus::_out( $atts, $out, 'persian_map', $this->name );
	}
}