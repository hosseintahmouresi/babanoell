<?php if ( ! defined( 'ABSPATH' ) ) {exit;} // Cannot access pages directly.

/**
 * 360 Degree
 * 
 * @author Codevz
 * @link http://codevz.com/
 */

class Codevz_WPBakery_360_degree {

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
			//'deprecated' 	=> '4.6',
			'base'			=> $this->name,
			'name'			=> esc_html__( '360 Degree', 'codevz-plus' ),
			'description'	=> esc_html__( '360 degree rotate image', 'codevz-plus' ),
			'icon'			=> 'czi',
			'params'		=> array(
				array(
					'type' 			=> 'cz_sc_id',
					'param_name' 	=> 'id',
					'save_always' 	=> true
				),
				array(
					"type"        	=> "attach_image",
					"heading"     	=> esc_html__("Placeholder (loading image)", 'codevz-plus' ),
					'edit_field_class' => 'vc_col-xs-6',
					"param_name"  	=> "placeholder_image",
					"value"			=> "https://xtratheme.com/img/360.jpg"
				),
				array(
					"type"        	=> "attach_image",
					"heading"     	=> esc_html__("Stripe Image", 'codevz-plus' ),
					'edit_field_class' => 'vc_col-xs-6',
					"param_name"  	=> "stripe_image",
					"value"			=> "https://xtratheme.com/img/360s.jpg"
				),
				array(
					"type"        	=> "cz_slider",
					"heading"     	=> esc_html__("Frames Count", 'codevz-plus' ),
					'edit_field_class' => 'vc_col-xs-99',
					"param_name"  	=> "count",
					"value"			=> "8",
					'options' 		=> array( 'unit' => '', 'step' => 1, 'min' => 1, 'max' => 40 ),
				),
				array(
					'type'			=> 'dropdown',
					'heading'		=> esc_html__('Rotate by', 'codevz-plus' ),
					'param_name'	=> 'action',
					'edit_field_class' => 'vc_col-xs-99',
					'value'			=> array(
						esc_html__( 'Mouse Dragging', 'codevz-plus' ) 	=> 'drag',
						esc_html__( 'Mouse Hover', 'codevz-plus' ) 		=> 'hover',
					)
				),
				array(
					'type' 			=> 'cz_sk',
					'param_name' 	=> 'sk_con',
					"heading"     	=> esc_html__( "Container", 'codevz-plus' ),
					'button' 		=> esc_html__( "Container", 'codevz-plus' ),
					'edit_field_class' => 'vc_col-xs-99',
					'settings' 		=> array( 'background', 'border' )
				),
				array( 'type' => 'cz_hidden','param_name' => 'sk_con_tablet' ),
				array( 'type' => 'cz_hidden','param_name' => 'sk_con_mobile' ),
				array(
					"type"        	=> "checkbox",
					"heading"     	=> esc_html__("Show Handle?", 'codevz-plus' ),
					'edit_field_class' => 'vc_col-xs-99',
					"param_name"  	=> "handle"
				),
				array(
					'type' 			=> 'cz_sk',
					'param_name' 	=> 'sk_handle',
					"heading"     	=> esc_html__( "Handle ", 'codevz-plus' ),
					'button' 		=> esc_html__( "Handle", 'codevz-plus' ),
					'edit_field_class' => 'vc_col-xs-99',
					'dependency'	=> array(
						'element'		=> 'handle',
						'not_empty'		=> true
					),
					'settings' 		=> array( 'color', 'background' )
				),
				array( 'type' => 'cz_hidden','param_name' => 'sk_handle_tablet' ),
				array( 'type' => 'cz_hidden','param_name' => 'sk_handle_mobile' ),
				array(
					'type' 			=> 'cz_sk',
					'param_name' 	=> 'sk_bar',
					"heading"     	=> esc_html__( "Bar", 'codevz-plus' ),
					'button' 		=> esc_html__( "Bar", 'codevz-plus' ),
					'edit_field_class' => 'vc_col-xs-99',
					'dependency'	=> array(
						'element'		=> 'handle',
						'not_empty'		=> true
					),
					'settings' 		=> array( 'background', 'border' )
				),
				array( 'type' => 'cz_hidden','param_name' => 'sk_bar_tablet' ),
				array( 'type' => 'cz_hidden','param_name' => 'sk_bar_mobile' ),
			)
		);

		return $wpb ? vc_map( $settings ) : $settings;
	}

	public function out( $atts, $content = '' ) {

		// Settings.
		$atts = Codevz_Plus::shortcode_atts( $this, $atts );

		// ID
		if ( ! $atts['id'] ) {
			$atts['id'] = Codevz_Plus::uniqid();
			$public = 1;
		}

		// Image
		$imgsrc = Codevz_Plus::get_image( $atts['stripe_image'], 0, 1 );
		$plc_imgsrc = Codevz_Plus::get_image( $atts['placeholder_image'], 0, 1 );

		// Count
		$count = $atts['count'] ? $atts['count'] : '16';

		// Styles
		if ( isset( $public ) || Codevz_Plus::$vc_editable || Codevz_Plus::$is_admin ) {
			
			$css_id = '#' . $atts['id'];

			$css_array = array(
				'sk_xxx'	=> $css_id,
				'sk_con'	=> $css_id . ' .product-viewer',
				'sk_handle'	=> $css_id . ' .handle',
				'sk_bar'	=> $css_id . ' .cz_product-viewer-handle',
			);

			$css 	= Codevz_Plus::sk_style( $atts, $css_array );
			$css_t 	= Codevz_Plus::sk_style( $atts, $css_array, '_tablet' );
			$css_m 	= Codevz_Plus::sk_style( $atts, $css_array, '_mobile' );
		}

		// Classes
		$classes = array();
		$classes[] = $atts['id'];
		$classes[] = 'cz_product-viewer-wrapper';

		// Out
		$out ='<div id="' . $atts['id'] . '" data-frame="' . $count . '" data-friction="0.33" data-action="' . $atts['action'] . '"' . Codevz_Plus::classes( $atts, $classes ) . Codevz_Plus::data_stlye( $css, $css_t, $css_m ) . '>
				<div><figure class="product-viewer">
						<img loading="lazy" src="' . $plc_imgsrc . '" alt="Loading">
						<div class="product-sprite" data-image="' . $imgsrc . '" style="width:' . ( $count * 100 ) . '%;background-image:url(' . $imgsrc . ')"></div>
					</figure>';

		if ( $atts['handle'] == true ) {	
			$out .='<div class="cz_product-viewer-handle"><span class="fill"></span><span class="handle"><i class="fa fa-arrows-h"></i></span></div>';
		}
		$out .='</div></div>';

		wp_enqueue_script( 'codevz-modernizer' );

		return Codevz_Plus::_out( $atts, $out, 'r360degree', $this->name );
	}

}