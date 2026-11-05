<?php if ( ! defined( 'ABSPATH' ) ) { die; } // Cannot access pages directly.

/**
 * Parallax Group
 * 
 * @author Codevz
 * @link http://codevz.com/
 */

class Codevz_WPBakery_parallax_group {

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
			'name'			=> esc_html__( 'Parallax Layers', 'codevz-plus' ),
			'description'	=> esc_html__( 'Group of layered items', 'codevz-plus' ),
			'icon'			=> 'czi',
			'params'		=> array(
				array(
					'type' 			=> 'cz_sc_id',
					'param_name' 	=> 'id',
					'save_always' 	=> true
				),
				array(
					'type' => 'param_group',
					'heading' => esc_html__( 'Layers', 'codevz-plus' ),
					'param_name' => 'items',
					'params' => array(
						array(
							"type"        	=> "attach_image",
							"heading"     	=> esc_html__( "Image", 'codevz-plus' ),
							"param_name"  	=> "image",
							'edit_field_class' => 'vc_col-xs-99',
							'admin_label'	=> true
						),
						array(
							'type' 			=> 'cz_sk',
							'param_name' 	=> 'sk_pos',
							"heading"     	=> esc_html__( "Position", 'codevz-plus' ),
							'button' 		=> esc_html__( "Position", 'codevz-plus' ),
							'edit_field_class' => 'vc_col-xs-99',
							'settings' 		=> array( 'top', 'left', 'right', 'bottom' )
						),
						array( 'type' => 'cz_hidden','param_name' => 'sk_pos_tablet' ),
						array( 'type' => 'cz_hidden','param_name' => 'sk_pos_mobile' ),
						array(
							'type' 			=> 'cz_sk',
							'param_name' 	=> 'sk_item',
							"heading"     	=> esc_html__( "Item styling", 'codevz-plus' ),
							'button' 		=> esc_html__( "Item styling", 'codevz-plus' ),
							'edit_field_class' => 'vc_col-xs-99',
							'settings' 		=> array( 'rotate', 'blur', 'opacity', 'width', 'height', 'background', 'border', 'box-shadow' )
						),
						array( 'type' => 'cz_hidden','param_name' => 'sk_item_tablet' ),
						array( 'type' => 'cz_hidden','param_name' => 'sk_item_mobile' ),
						array(
							'type' 			=> 'checkbox',
							'heading' 		=> esc_html__( 'Hide on tablet?', 'codevz-plus' ),
							'param_name' 	=> 'hide_on_tablet',
							'edit_field_class' => 'vc_col-xs-99'
						), 
						array(
							'type' 			=> 'checkbox',
							'heading' 		=> esc_html__( 'Hide on mobile?', 'codevz-plus' ),
							'param_name' 	=> 'hide_on_mobile',
							'edit_field_class' => 'vc_col-xs-99'
						), 
						array(
							'type' 			=> 'cz_title',
							'param_name' 	=> 'cz_title',
							'class' 		=> '',
							'content' 		=> esc_html__( 'Parallax', 'codevz-plus' )
						),
						array(
							"type"        	=> "dropdown",
							"heading"     	=> esc_html__( "Parallax", 'codevz-plus' ),
							"param_name"  	=> "h",
							'edit_field_class' => 'vc_col-xs-99',
							'value'		=> array(
								esc_html__( 'Select', 'codevz-plus' )					=> '',
								
								esc_html__( 'Vertical', 'codevz-plus' )					=> 'v',
								esc_html__( 'Vertical + Mouse parallax', 'codevz-plus' )		=> 'vmouse',
								esc_html__( 'Horizontal', 'codevz-plus' )				=> 'true',
								esc_html__( 'Horizontal + Mouse parallax', 'codevz-plus' )	=> 'truemouse',
								esc_html__( 'Mouse parallax', 'codevz-plus' )				=> 'mouse',
							),
						),
						array(
							"type"        	=> "cz_slider",
							"heading"     	=> esc_html__( "Parallax speed", 'codevz-plus' ),
							"description"   => esc_html__( "Parallax is according to page scrolling", 'codevz-plus' ),
							'edit_field_class' => 'vc_col-xs-99',
							"param_name"  	=> "p",
							"value"  		=> "0",
							'options' 		=> array( 'unit' => '', 'step' => 1, 'min' => -50, 'max' => 50 ),
							'dependency'	=> array(
								'element'		=> 'h',
								'value'			=> array( 'v', 'vmouse', 'true', 'truemouse' )
							),
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
							"param_name"  	=> "m",
							"value"  		=> "0",
							'options' 		=> array( 'unit' => '', 'step' => 1, 'min' => -30, 'max' => 30 ),
							'dependency'	=> array(
								'element'		=> 'h',
								'value'			=> array( 'vmouse', 'truemouse', 'mouse' )
							),
						),
						array(
							'type' 			=> 'cz_title',
							'param_name' 	=> 'cz_title',
							'class' 		=> '',
							"content"     	=> esc_html__("Animation", 'codevz-plus' ),
						),
						Codevz_Plus::wpb_animation_tab( false ),
						array(
							"type"        	=> "cz_slider",
							"heading"     	=> esc_html__("Animation Delay", 'codevz-plus' ),
							"description" 	=> 'e.g. 500ms',
							"param_name"  	=> "anim_delay",
							'options' 		=> array( 'unit' => 'ms', 'step' => 100, 'min' => 0, 'max' => 5000 ),
						),
					),
				),
				array(
					'type' 			=> 'cz_sk',
					'param_name' 	=> 'sk_con',
					"heading"     	=> esc_html__( "Container styling", 'codevz-plus' ),
					'button' 		=> esc_html__( "Container", 'codevz-plus' ),
					'edit_field_class' => 'vc_col-xs-99',
					'settings' 		=> array( 'width', 'height', 'background', 'border', 'overflow' )
				),
				array( 'type' => 'cz_hidden','param_name' => 'sk_con_tablet' ),
				array( 'type' => 'cz_hidden','param_name' => 'sk_con_mobile' ),
				array(
					"type"        	=> "textfield",
					"heading"     	=> esc_html__("Extra class", 'codevz-plus' ),
					'edit_field_class' => 'vc_col-xs-99',
					"param_name"  	=> "class"
				)
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

		$item_css 	= '';
		$item_css_t = '';
		$item_css_m = '';

		// Layers
		$layers = '';
		$items = json_decode( urldecode( $atts[ 'items' ] ), true );
		foreach ( $items as $key => $i ) {

			$item_id = $atts['id'] . '_layer_parallax_' . $key;

			// CSS.
			$css_array = array(
				'sk_pos'  => '#' . $item_id,
				'sk_item' => '#' . $item_id . ' > div'
			);
			$item_css 	.= Codevz_Plus::sk_style( $i, $css_array );
			$item_css_t .= Codevz_Plus::sk_style( $i, $css_array, '_tablet' );
			$item_css_m .= Codevz_Plus::sk_style( $i, $css_array, '_mobile' );
			$item_css   .= !empty( $i['anim_delay'] ) ? '#' . $item_id . '{animation-delay:' . $i['anim_delay'] . '}' : '';

			// Item out.
			$i['class'] = '';
			$i['class'] .= empty( $i['hide_on_tablet'] ) ? '' : 'hide_on_tablet';
			$i['class'] .= empty( $i['hide_on_mobile'] ) ? '' : 'hide_on_mobile';
			$image = empty( $i['image'] ) ? '' : Codevz_Plus::get_image( $i['image'] );
			$layers .= '<div id="' . esc_attr( $item_id ) . '"' . Codevz_Plus::classes( $i, array( 'cz_layer_parallax' ) ) . '>' . Codevz_Plus::_out(array(
				'parallax' 		=> isset( $i['p'] ) ? $i['p'] : '',
				'mparallax' 	=> isset( $i['m'] ) ? $i['m'] : '',
				'parallax_h' 	=> isset( $i['h'] ) ? $i['h'] : '',
			), '<div>' . $image . '</div>' ) . '</div>';
		}

		// Styles
		if ( isset( $public ) || Codevz_Plus::$vc_editable || Codevz_Plus::$is_admin ) {
			$css_id = '#' . $atts['id'];
			$css_array = array(
				'sk_con' => $css_id
			);

			$css 	= Codevz_Plus::sk_style( $atts, $css_array );
			$css_t 	= Codevz_Plus::sk_style( $atts, $css_array, '_tablet' );
			$css_m 	= Codevz_Plus::sk_style( $atts, $css_array, '_mobile' );

			$css   .= $item_css;
			$css_t .= $item_css_t;
			$css_m .= $item_css_m;
		}

		// Out
		$out = '<div id="' . $atts['id'] . '"' . Codevz_Plus::classes( $atts, array( $atts['id'], 'cz_group_parallax' ) ) . Codevz_Plus::data_stlye( $css, $css_t, $css_m ) . '>' . $layers . '</div>';

		return Codevz_Plus::_out( $atts, $out, [ 'parallax' ], $this->name, 'cz_parallax' );
	}

}