<?php if ( ! defined( 'ABSPATH' ) ) { die; } // Cannot access pages directly.

/**
 * Gradient title
 * 
 * @author Codevz
 * @link http://codevz.com/
 */

class Codevz_WPBakery_gradient_title {

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
			'name'			=> esc_html__( 'Gradient Title', 'codevz-plus' ),
			'description'	=> esc_html__( 'Text with background', 'codevz-plus' ),
			'icon'			=> 'czi',
			'params'		=> array(
				array(
					'type' 			=> 'cz_sc_id',
					'param_name' 	=> 'id',
					'save_always' 	=> true
				),
				array(
					"type"        	=> "textarea_html",
					"heading"     	=> esc_html__("Text", 'codevz-plus' ),
					"value"  		=> '<h1 style="text-align:center"><strong>Example Gradient Title</strong></h1>',
					"param_name"  	=> "content",
					'admin_label' 	=> true
				),
				array(
					'type' 			=> 'checkbox',
					'heading' 		=> esc_html__( 'Animation', 'codevz-plus' ),
					'param_name' 	=> 'animation',
					'edit_field_class' => 'vc_col-xs-99',
					'group' 		=> esc_html__( 'Advanced', 'codevz-plus' )
				), 
				array(
					'type' 			=> 'checkbox',
					'heading' 		=> esc_html__( 'Center on mobile?', 'codevz-plus' ),
					'param_name' 	=> 'text_center',
					'edit_field_class' => 'vc_col-xs-99',
					'group' 		=> esc_html__( 'Advanced', 'codevz-plus' )
				), 
				array(
					'type' 			=> 'cz_sk',
					'param_name' 	=> 'sk_css',
					"heading"     	=> esc_html__( "Background", 'codevz-plus' ),
					'button' 		=> esc_html__( "Background", 'codevz-plus' ),
					'edit_field_class' => 'vc_col-xs-99',
					'settings' 		=> array( 'background' )
				),
				array(
					'type' 			=> 'cz_sk',
					'param_name' 	=> 'sk_h1',
					"heading"     	=> esc_html__( "H1", 'codevz-plus' ),
					'button' 		=> esc_html__( "H1", 'codevz-plus' ),
					'edit_field_class' => 'vc_col-xs-99',
					'settings' 		=> array( 'font-size', 'font-weight', 'line-height' )
				),
				array(
					'type' 			=> 'cz_sk',
					'param_name' 	=> 'sk_h2',
					"heading"     	=> esc_html__( "H2", 'codevz-plus' ),
					'button' 		=> esc_html__( "H2", 'codevz-plus' ),
					'edit_field_class' => 'vc_col-xs-99',
					'settings' 		=> array( 'font-size', 'font-weight', 'line-height' )
				),
				array(
					'type' 			=> 'cz_sk',
					'param_name' 	=> 'sk_h3',
					"heading"     	=> esc_html__( "H3", 'codevz-plus' ),
					'button' 		=> esc_html__( "H3", 'codevz-plus' ),
					'edit_field_class' => 'vc_col-xs-99',
					'settings' 		=> array( 'font-size', 'font-weight', 'line-height' )
				),

				// Advanced
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
						esc_html__( '~ Disable ~', 'codevz-plus' )					=> '',
						esc_html__( 'Vertical', 'codevz-plus' )						=> 'v',
						esc_html__( 'Vertical + Mouse parallax', 'codevz-plus' )	=> 'vmouse',
						esc_html__( 'Horizontal', 'codevz-plus' )					=> 'true',
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
				'sk_css' 	=> array( $css_id, $custom ),
				'sk_h1' 	=> $css_id . ' h1',
				'sk_h2' 	=> $css_id . ' h2',
				'sk_h3' 	=> $css_id . ' h3',
				'sk_brfx' 	=> $css_id . ':before',
			);

			$css 	= Codevz_Plus::sk_style( $atts, $css_array );
			$css_t 	= Codevz_Plus::sk_style( $atts, $css_array, '_tablet' );
			$css_m 	= Codevz_Plus::sk_style( $atts, $css_array, '_mobile' );
		}

		// Classes
		$classes = array();
		$classes[] = $atts['id'];
		$classes[] = 'cz_gradient_title';
		$classes[] = $atts['text_center'] ? 'cz_mobile_text_center' : '';
		$classes[] = $atts['animation'] ? 'cz_gradient_title_animation' : '';
		if ( Codevz_Plus::contains( $content, array( ': center;', ':center;' ) ) ) {
			$classes[] = 'cz_gradient_title_center';
		}

		// Out
		$out = '<div id="' . $atts['id'] . '"' . Codevz_Plus::classes( $atts, $classes ) . Codevz_Plus::data_stlye( $css, $css_t, $css_m ) . '><div class="cz_wpe_content">' . do_shortcode( Codevz_Plus::fix_extra_p( $content ) ) . '</div></div>';

		return Codevz_Plus::_out( $atts, $out, false, $this->name );
	}

}